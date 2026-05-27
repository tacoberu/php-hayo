<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Verifies that a compiled script has the expected signature (input parameters)
 * and returns the expected result type.
 *
 * Use this to validate that a user-supplied script is compatible with the
 * contract your application requires — i.e. it accepts the right parameters
 * and produces the right kind of result.
 */
class ScriptSignatureTest extends TestCase
{

	/**
	 * @param list<BindValue> $expectedBinds
	 */
	#[DataProvider('dataNoParams')]
	#[DataProvider('dataWithParams')]
	function testSignature(string $code, array $expectedBinds): void
	{
		$compiled = $this->compile($code);
		$this->assertEquals($expectedBinds, $this->getBinds($compiled));
	}



	#[DataProvider('dataReturnType')]
	function testReturnType(string $code, string $expectedType): void
	{
		$compiled = $this->compile($code);
		$this->assertSame($expectedType, $compiled->type());
	}



	/**
	 * @param list<BindValue> $expectedBinds
	 */
	#[DataProvider('dataContract')]
	function testContract(string $code, array $expectedBinds, string $expectedType): void
	{
		$compiled = $this->compile($code);
		$this->assertEquals($expectedBinds, $this->getBinds($compiled), 'signature mismatch');
		$this->assertSame($expectedType, $compiled->type(), 'return type mismatch');
	}



	/**
	 * data: scripts that need no parameters
	 * @return array<array<mixed>>
	 */
	static function dataNoParams(): array
	{
		return [
			'integer constant' => ['42', []],
			'real constant' => ['3.14', []],
			'string constant' => ['"hello"', []],
			'True symbol' => ['True', []],
			'False symbol' => ['False', []],
			'Null symbol' => ['Null', []],
			'empty list' => ['[]', []],
			'empty dict' => ['{}', []],
			'empty tuple' => ['()', []],
			'pure arithmetic' => ['1 + 1', []],
			'local var fully resolved'=> ["a = 5\na + 1", []],
			'two local vars resolved' => ["a = 2\nb = 40\nb + a", []],
			'constant dict' => ['{x: 1, y: 2}', []],
			'constant list' => ['[1, 2, 3]', []],
		];
	}



	/**
	 * data: scripts that require parameters
	 * @return array<array<mixed>>
	 */
	static function dataWithParams(): array
	{
		return [
			'single param' => [
				'a + 1',
				[new BindValue('a', '?')],
				],

			'two params' => [
				'a + b',
				[new BindValue('a', '?'), new BindValue('b', '?')],
				],

			'local var shadows outer' => [
				"vat = 1.23\nprice * vat",
				[new BindValue('price', '?')],
				],

			'alias for param' => [
				"x = a\nx + x",
				[new BindValue('a', '?')],
				],

			'dict with params' => [
				'{name: name, age: age}',
				[new BindValue('name', '?'), new BindValue('age', '?')],
				],

			'dict with mixed' => [
				'{label: "hello", value: val}',
				[new BindValue('val', '?')],
				],

			'list with param' => [
				'[a, 1, 2]',
				[new BindValue('a', '?')],
				],

			'if-then-else with param' => [
				'if a > 0 then "pos" else "neg"',
				[new BindValue('a', '?')],
				],

			'pipe chain' => [
				"xs\n\t|> List.map (x -> x * x)\n\t|> List.fold 0 (prev curr -> prev + curr)",
				[new BindValue('xs', '?')],
				],

			'nested dict with param' => [
				"content = [{key: \"name\", value: name}]\n{title: \"form\", fields: content}",
				[new BindValue('name', '?')],
				],
		];
	}



	/**
	 * data: return type
	 * @return array<array<mixed>>
	 */
	static function dataReturnType(): array
	{
		return [
			'Int' => ['42', 'Int'],
			'Real' => ['3.14', 'Real'],
			'Str' => ['"hello"', 'Str'],
			'Symbol' => ['True', 'Symbol'],
			'empty List' => ['[]', 'List'],
			'empty Dict' => ['{}', 'Dict'],
			'empty Tuple' => ['()', 'Tuple'],
			'Num from add' => ['1 + 1', 'Num'],
			'Dict static' => ['{a: 1, b: 2}', 'Dict'],
			'List static' => ['[1, 2, 3]', 'List'],

			// Expressions with unresolved params → type is '?'
			'expr with param' => ['a + 1', '?'],
			'if-then-else with param' => ['if a > 0 then 1 else 2', '?'],

			// Composite structures with unresolved params keep their structural type
			'Dict with param' => ['{x: val, y: 2}', 'Dict'],
			'List with param' => ['[a, 1, 2]', 'List'],
		];
	}



	/**
	 * data: full contract (signature + return type)
	 * @return array<array<mixed>>
	 */
	static function dataContract(): array
	{
		return [
			'price calculation' => [
				"vat = 1.23\nprice * vat",
				[new BindValue('price', '?')],
				'?',
				],

			'address parsing' => [
				trim('
xs = (Str.split src ",")
{
	street:  (List.first xs "")
	city:    (List.at xs 1 "") |> Str.trim
	country: (List.at xs 2 "") |> Str.trim
}'),
				[new BindValue('src', '?')],
				'Dict',
				],

			'list squaring' => [
				"List.map xs (x -> x * x)",
				[new BindValue('xs', '?')],
				'?',
				],

			'constant result, no params' => [
				'{name: "Alice", role: "admin"}',
				[],
				'Dict',
				],

			'scoring with branches' => [
				trim('
if score < 50 then "F"
elif score < 70 then "C"
elif score < 90 then "B"
else "A"'),
				[new BindValue('score', '?')],
				'?',
				],

			'email template dict' => [
				trim('
{
	name:      "contact"
	recipient: email
	content:   message
}'),
				[new BindValue('email', '?'), new BindValue('message', '?')],
				'Dict',
				],
		];
	}



	/**
	 * Returns the list of required input parameters for the compiled script.
	 * A FinalValue (no parameters needed) returns an empty array.
	 *
	 * @param FinalValue|ParametricValue $compiled
	 * @return list<BindValue>
	 */
	private function getBinds($compiled): array
	{
		if ($compiled instanceof FinalValue) {
			return [];
		}
		return $compiled->getBinds();
	}



	/**
	 * @return FinalValue|ParametricValue
	 */
	private function compile(string $code)
	{
		return (new Compiler([
			'predicate' => new PredicatesProvider(),
			'Math' => new MathsProvider(),
			'Str' => new StringsProvider(),
			'List' => new ListsProvider(),
			'Dict' => new DictsProvider(),
			]))
			->compile($code);
	}

}
