<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class ExhibitionTest extends TestCase
{

	function testSample()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate("1 + 1");
		$this->assertEquals(2, $result);
	}



	function testSampleWithArguments()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate("1 + a", ['a' => 2]);
		$this->assertEquals(3, $result);

		$result = HayoEngine::WithDefaultLibraries()
			->evaluate("1 + a", [2]);
		$this->assertEquals(3, $result);
	}



	function testBindLocalVars()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate('
x = 5
-- nějaký komentář
a + x', [10]);
		$this->assertEquals(15, $result);
	}



	function testReturnStructConst()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate('{a: 4, b: "abc"}');
		$this->assertEquals((object) [
			'a' => 4,
			'b' => "abc",
		], $result);
	}



	function testUseCache()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->setCache(new FileBaseCache(__dir__ . '/../temp/cache'))
			->evaluate('{a: 4, b: "abc", c: arg1}', ["Lorem ispum doler ist"]);
		$this->assertEquals((object) [
			'a' => 4,
			'b' => "abc",
			'c' => "Lorem ispum doler ist",
		], $result);
	}



	function testGetCompiledRoutine()
	{
		$routine = HayoEngine::WithDefaultLibraries()
			->setCache(new FileBaseCache(__dir__ . '/../temp/cache'))
			->compile("1 + a");
		$this->assertEquals(new FinalValue(6, 'Int'),
			$routine->apply(['a' => new FinalValue(5, 'Int')]));
	}



	function testRegisterLibrary()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->registerLibrary('my_fns', new class implements SymbolProvider {

				function lookup(string $name): ?BuildinFunc
				{
					if ($name === 'calculate') {
						return new class implements BuildinFunc
						{

							function type(): string
							{
								return '?';
							}


							// phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
							function getBinds(): array
							{
								return [];
							}



							// phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
							function apply(array $args): Value
							{
								return reset($args);
							}



							function __toString(): string
							{
								return 'my_fns.calculate';
							}

						};
					}

					return Null;
				}

			})
			->evaluate('my_fns.calculate src', [42]);
		$this->assertSame(42, $result);
	}



	function testAllTypes()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate('{
	str: str
	num: num
	real: real
	yes: yes
	no: no
	nothing: nothing
	tuple: tuple
	list: list
	dict: dict
}', [
				'str' => "Hi",
				'num' => 42,
				'real' => 3.141592,
				'yes' => True,
				'no' => False,
				'nothing' => Null,
				'tuple' => new FinalValue([], 'Tuple'),
				'list' => [1, 2, 3],
				'dict' => (object) ['a' => 1, 'b' => 2, 'c' => 3],
			]);
		$this->assertEquals((object) [
			'str' => "Hi",
			'num' => 42,
			'real' => 3.141592,
			'yes' => True,
			'no' => False,
			'nothing' => Null,
			'tuple' => [],
			'list' => [1, 2, 3],
			'dict' => (object) ['a' => 1, 'b' => 2, 'c' => 3],
		], $result);
	}

}
