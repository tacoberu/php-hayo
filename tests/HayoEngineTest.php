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
use DateInterval;
use RuntimeException;


class HayoEngineTest extends TestCase
{

	/**
	 * @param array<mixed> $args
	 * @dataProvider dataCorrect
	 */
	#[DataProvider('dataCorrect')]
	function testCorrect(string $code, array $args, $expected)
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate($code, $args);
		$this->assertEquals($expected, $result);
	}



	/**
	 * @param class-string<\Throwable> $exception
	 * @param array<mixed> $args
	 * @dataProvider dataSymbolNotFoundErrors
	 * @dataProvider dataCompileErrors
	 * @dataProvider dataInvalidArgumentErrors
	 * @dataProvider dataScriptRuntimeErrors
	 */
	#[DataProvider('dataSymbolNotFoundErrors')]
	#[DataProvider('dataCompileErrors')]
	#[DataProvider('dataInvalidArgumentErrors')]
	#[DataProvider('dataScriptRuntimeErrors')]
	function testCompileWithErrors(string $code, array $args, string $exception, string $message): void
	{
		$this->expectException($exception);
		$this->expectExceptionMessage($message);
		HayoEngine::WithDefaultLibraries()
			->evaluate($code, $args);
	}



	function testCacheNotWritable(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Location for file cache: '/proc' is not writeable.");
		HayoEngine::WithDefaultLibraries()
			->setCache(new FileBaseCache('/proc'))
			->evaluate('1 + 1', []);
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataCorrect(): array
	{
		return [
			["1 + 1", [],
				2,
				],
			['"""Sinead O\'Connor"""', [],
				"Sinead O'Connor",
				],

			// a + 1 with Int argument
			['a + 1', ['a' => 41],
				42,
				],

			// a + 1 with Real argument
			['a + 1', ['a' => 41.0],
				42.0,
				],

			// Lambda passed to List.map closes over an external script
			// parameter (not just its own bound argument).
			['List.map xs (x -> x + factor)', ['xs' => [1, 2, 3], 'factor' => 10],
				[11, 12, 13],
				],

			// Same, but the closed-over variable is a local constant rather
			// than an external parameter.
			["factor = 42\nList.map xs (x -> x + factor)", ['xs' => [1, 2, 3]],
				[43, 44, 45],
				],

			// Body of a lambda that is not an expression: a path, a symbol,
			// a constant, a composite.
			['List.map xs (x -> x.id)', ['xs' => [(object) ['id' => 5], (object) ['id' => 8]]],
				[5, 8],
				],
			['List.map xs (x -> x)', ['xs' => [1, 2]],
				[1, 2],
				],
			['List.map xs (x -> 1)', ['xs' => ['a', 'b']],
				[1, 1],
				],
			['List.map xs (x -> Null)', ['xs' => ['a']],
				[Null],
				],
			['List.map xs (x -> [x.id, 1])', ['xs' => [(object) ['id' => 5], (object) ['id' => 8]]],
				[[5, 1], [8, 1]],
				],
			['List.map xs (x -> {a: x.id})', ['xs' => [(object) ['id' => 5]]],
				[(object) ['a' => 5]],
				],
			['List.map xs (x -> x.nothing)', ['xs' => [(object) ['id' => 5]]],
				[Null],
				],

			// A path inside an expression; a path to a closed-over variable.
			['List.filter xs (x -> x.id == 5)', ['xs' => [(object) ['id' => 5], (object) ['id' => 8]]],
				[(object) ['id' => 5]],
				],
			['List.map xs (x -> x.id + cfg.n)', ['xs' => [(object) ['id' => 1], (object) ['id' => 2]], 'cfg' => (object) ['n' => 100]],
				[101, 102],
				],
			['List.map xs (x -> cfg.n)', ['xs' => [1, 2], 'cfg' => (object) ['n' => 100]],
				[100, 100],
				],
			['List.map xs (x -> n)', ['xs' => [1, 2], 'n' => 7],
				[7, 7],
				],

			// The order of parameters is kept when a path leads through one of them.
			['List.fold xs 0 (acc b -> acc + b.id)', ['xs' => [(object) ['id' => 1], (object) ['id' => 3]]],
				4,
				],
			['List.fold xs 0 (b acc -> b + acc.id)', ['xs' => [(object) ['id' => 1], (object) ['id' => 3]]],
				4,
				],

			// List.groupBy by a field of a record.
			['List.groupBy xs (x -> x.id)', ['xs' => [
				(object) ['id' => 5, 'count' => 1],
				(object) ['id' => 8, 'count' => 2],
				(object) ['id' => 5, 'count' => 11],
				(object) ['id' => 2, 'count' => 3],
				(object) ['id' => 2, 'count' => 1],
				]],
				[
					[(object) ['id' => 5, 'count' => 1], (object) ['id' => 5, 'count' => 11]],
					[(object) ['id' => 8, 'count' => 2]],
					[(object) ['id' => 2, 'count' => 3], (object) ['id' => 2, 'count' => 1]],
				],
				],
			['List.groupBy xs (x -> {a: x.id, b: 1})', ['xs' => [(object) ['id' => 5], (object) ['id' => 8], (object) ['id' => 5]]],
				[[(object) ['id' => 5], (object) ['id' => 5]], [(object) ['id' => 8]]],
				],
			['List.groupBy xs (x -> x)', ['xs' => [(object) ['a' => 1, 'b' => 2], (object) ['b' => 2, 'a' => 1], (object) ['a' => 2]]],
				[[(object) ['a' => 1, 'b' => 2], (object) ['b' => 2, 'a' => 1]], [(object) ['a' => 2]]],
				],

			// F3 — `.pole` on the result of an arbitrary expression, not just
			// a bareword symbol.
			['(List.first xs Null).product', ['xs' => [(object) ['product' => 'apple'], (object) ['product' => 'pear']]],
				'apple',
				],
			['(List.first xs Null).product', ['xs' => []],
				Null,
				],
			['List.map xss (xs -> {product: (List.first xs Null).product})', ['xss' => [
				[(object) ['product' => 'apple']],
				[],
				]],
				[(object) ['product' => 'apple'], (object) ['product' => Null]],
				],
			['{a: 1, b: 2}.a', [],
				1,
				],
			['(if c then {a: 1} else {a: 2}).a', ['c' => True],
				1,
				],
			// Chaining `.a.b` on the result of a lambda call.
			['((x -> {a: {b: x}}) 5).a.b', [],
				5,
				],
			// Chaining through a missing field resolves to Null, not an error.
			['((x -> {a: x}) 5).a.nothing', [],
				Null,
				],
			// A base that is not a record does not crash; the field is Null.
			['(1 + 1).foo', [],
				Null,
				],
			// The plain bareword path still works unchanged.
			['x.product', ['x' => (object) ['product' => 'kiwi']],
				'kiwi',
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataSymbolNotFoundErrors(): array
	{
		return [
			// Unknown symbol → LogicException from Compiler
			['List.noth (a b -> a + b) xs', ['xs' => []],
				SymbolNotFound::class,
				'Unable to find symbols: List.noth.'],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataCompileErrors(): array
	{
		return [
			// Comment-only source → decoder returns null → LogicException from Compiler::compile() line 63
			['-- comment only', [],
				CompileException::class,
				'Invalid source code.'],
			// Double operator → global \LogicException from PrattParser (vendor)
			['1 * + 2', [],
				CompileException::class,
				'Očekáván operand, dostal jsem operátor:'],
			// Unrecognised character → global \Exception from Lexer (vendor)
			["foo \x01 bar", [],
				CompileException::class,
				"Couldn't tokenise:"],
			// Lambda whose body is an undefined symbol, applied with all args resolved
			["f = x -> z\nf 1", ['z' => 1],
				CompileException::class,
				'Unresolved expression \'(x) -> z 1 : Int\''],
			// Lambda with a non-string argument (list pattern) → CompileException from partialEvaluateLambda
			["f = [a b] -> a + b\nf 5", [],
				CompileException::class,
				'Lambda arguments must be simple names, not expressions.'],
			// Incomplete if-then-else → HayoParserException from Parser (vendor)
			['if a > 0 then', [],
				CompileException::class,
				'Required closing bracked: EOF.'],
			// Lambda with parenthesised arg → global \LogicException from Parser (vendor)
			['((a b) -> a + b)', [],
				CompileException::class,
				'Lambda arguments must be simple names, not expressions.'],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataInvalidArgumentErrors(): array
	{
		return [
			// Too few positional arguments → ArgumentsException from HayoEngine::combineBindWithValues
			['List.first xs', [],
				ArgumentsException::class,
				"Invalid count of arguments func 'CallableValue: <List.first> <?xs> :: List<a> [xs]'. Expected 'xs'; given empty."],
			// Too many positional arguments → ArgumentsException from HayoEngine::combineBindWithValues
			['1 + a', [10, 99],
				ArgumentsException::class,
				"Invalid count of arguments func 'CallableValue: 1 <Math.+> <?a> :: Num [a]'. Expected 'a'; given '10', '99'."],
			// Unsupported PHP argument type → ArgumentsException from HayoEngine::gauseType
			['a', [new DateInterval('P1D')],
				ArgumentsException::class,
				"Invalid type of value: 'DateInterval Object\n"],
			// Wrong named argument → ArgumentsException from ParametricValue::assertBindArguments
			['1 + a', ['b' => 10],
				ArgumentsException::class,
				"Invalid arguments. Expected 'a'; given 'b'."],
			// Extra named argument → ArgumentsException from ParametricValue::assertBindArguments
			['1 + a', ['a' => 10, 'b' => 20],
				ArgumentsException::class,
				"Invalid count of arguments func 'CallableValue: 1 <Math.+> <?a> :: Num [a]'. Expected 'a'; given 'a', 'b'."],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataScriptRuntimeErrors(): array
	{
		return [

			// Math operator on wrong types → ScriptRuntimeException from TypeValidator::assertArguments
			['a + b', ['a' => 'hello', 'b' => 2],
				ScriptRuntimeException::class,
				'Invalid arguments of Math.+: Expected int or float, got string'],
			['a - b', ['a' => 'hello', 'b' => 2],
				ScriptRuntimeException::class,
				'Invalid arguments of Math.-: Expected int or float, got string'],
			['a * b', ['a' => 'hello', 'b' => 2],
				ScriptRuntimeException::class,
				'Invalid arguments of Math.*: Expected int or float, got string'],
			['a div b', ['a' => 'hello', 'b' => 2],
				ScriptRuntimeException::class,
				'Invalid arguments of Math.div: Expected int or float, got string'],

			// IN predicate on non-array → ScriptRuntimeException from TypeValidator::assertArguments
			['1 IN xs', ['xs' => 'hello'],
				ScriptRuntimeException::class,
				'Invalid arguments of Predicate.in: Expected array, got string'],

			// List.len on non-array → ScriptRuntimeException from TypeValidator::assertArguments
			['List.len xs', ['xs' => 'hello'],
				ScriptRuntimeException::class,
				'Invalid arguments of List.len: Expected array, got string'],
			// List.map on non-array → ScriptRuntimeException from TypeValidator::assertArguments
			['List.map xs (x -> x + 1)', ['xs' => 42],
				ScriptRuntimeException::class,
				'Invalid arguments of List.map: Expected array, got integer'],

			// Wrong argument type for builtin → ScriptRuntimeException from TypeValidator::assertArguments
			['Str.len xs', ['xs' => 42],
				ScriptRuntimeException::class,
				'Expected string, got integer'],

			// DateTime.fromDate with non-int year → ScriptRuntimeException from TypeValidator::assertArguments
			['DateTime.fromDate y m d', ['y' => 'not-a-year', 'm' => 1, 'd' => 1],
				ScriptRuntimeException::class,
				'Invalid arguments of DateTime.fromDate: Expected int, got string'],
			// DateTime.fromTimestamp with invalid string → ScriptRuntimeException from TypeValidator::assertArguments
			['DateTime.fromTimestamp src', ['src' => 'abc'],
				ScriptRuntimeException::class,
				'Invalid arguments of DateTime.fromTimestamp: Expected int, got string'],

			// Modulo by zero → ScriptRuntimeException (wraps DivisionByZeroError from PHP runtime)
			['10 mod a', ['a' => 0],
				ScriptRuntimeException::class,
				'Modulo by zero'],
			// Integer division by zero → ScriptRuntimeException (wraps DivisionByZeroError from PHP runtime)
			['10 div a', ['a' => 0],
				ScriptRuntimeException::class,
				'Division by zero'],

		];
	}

}
