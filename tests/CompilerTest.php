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


class CompilerTest extends TestCase
{

	#[DataProvider('dataScalar')]
	#[DataProvider('dataStructs')]
	#[DataProvider('dataOperations')]
	#[DataProvider('dataFinalValWithSymbol')]
	function testCompile(string $code, $expected)
	{
		$this->assertEquals($expected, $this->compile($code));
	}



	function testReturnStructWithBind()
	{
		$this->assertEquals(new FinalVal((object) [
			'a' => new FinalVal(45, 'Int'),
			'b' => new FinalVal('"abc"', 'Str'),
			'c' => new FinalVal(88, 'Int'),
		], 'Dict'), $this->compile('
{a: a, b: "abc", c: c}')
		->apply(['a' => new FinalVal(45, 'Int'), 'c' => new FinalVal(88, 'Int')])
		);
	}



	function _testDevelop()
	{
		$src = "a = 554\n{a: 42, b: a + 1}";

		dump($this->compile($src));
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataScalar(): array
	{
		return [
			['42', new FinalVal(42, 'Int')],
			['"Ahoj"', new FinalVal('"Ahoj"', 'Str')],

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataStructs(): array
	{
		return [
			['{}', new FinalVal((object) [], 'Dict')],
			['{a: 42}', new FinalVal((object) ['a' => new FinalVal(42, 'Int')], 'Dict')],
			['{a: 42, b: 555}', new FinalVal((object) [
				'a' => new FinalVal(42, 'Int'),
				'b' => new FinalVal(555, 'Int'),
				], 'Dict')],

			["a = 555\n{a: 42, b: a}", new FinalVal((object) [
				'a' => new FinalVal(42, 'Int'),
				'b' => new FinalVal(555, 'Int'),
				], 'Dict')],

			["a = 554\n{a: 42, b: a + 1}", new FinalVal((object) [
				'a' => new FinalVal(42, 'Int'),
				'b' => new FinalVal(555, 'Int'),
				], 'Dict')],

			["a = 100 + 454\n{a: 42, b: a + 1}", new FinalVal((object) [
				'a' => new FinalVal(42, 'Int'),
				'b' => new FinalVal(555, 'Int'),
				], 'Dict')],

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataOperations(): array
	{
		return [
			['40 + 2', new FinalVal(42, 'Int')],
			['40 + (1 + 1)', new FinalVal(42, 'Int')],
			['(10 + 30) + (1 + 1)', new FinalVal(42, 'Int')],
			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataFinalValWithSymbol(): array
	{
		return [
			["a = 2\n40 + a", new FinalVal(42, 'Int')],
			["a = 2\nb = 40\nb + a", new FinalVal(42, 'Int')],
			["a = 2\nb = 20\n(b + b) + a", new FinalVal(42, 'Int')],
			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataLambdas(): array
	{
		return [
			['40 + a', new VariadicVal(new BuildinMathOperator('+')
				, 'Unknown'
				, [ new FinalVal(40, 'Int')
					, new BindVal('a', '?'),
					])],

/* @TODO			['40 + (a + a)', new VariadicVal(new BuildinMathOperator('+')
				, 'Unknown'
				, [ new FinalVal(40, 'Int')
					, new BindVal('a', '?')
				])],
				*/

			['40 + (a + b)', new VariadicVal(new BuildinMathOperator('+')
				, 'Unknown'
				, [ new FinalVal(40, 'Int')
					, new VariadicVal(new BuildinMathOperator('+')
						, 'Unknown'
						, [ new BindVal('a', '?')
							, new BindVal('b', '?'),
							]),
					])],

			// @TODO
		];
	}



	private function compile($src)
	{
		return (new Compiler())->compile($src);
	}

}
