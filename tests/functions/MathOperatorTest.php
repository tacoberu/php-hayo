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


class MathOperatorTest extends TestCase
{

	function testMathsProvider()
	{
		$this->assertEquals(new MathOperator('+'), (new MathsProvider())->lookupFunc('+'));
	}



	// ---- + -----------------------------------------------------------------

	function testPlus()
	{
		$inst = new MathOperator('+');
		$this->assertSame('Num', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Num'),
			new BindValue('b', 'Num'),
		], $inst->getBinds());
		$this->assertSame('<Math.+>', (string) $inst);
	}



	/**
	 * @dataProvider dataPlusApply
	 */
	#[DataProvider('dataPlusApply')]
	function testPlusApply($a, $b, $expected)
	{
		$inst = new MathOperator('+');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'Num'),
			new FinalValue($b, 'Num'),
		])->unpack());
	}



	// ---- - -----------------------------------------------------------------

	function testMinus()
	{
		$inst = new MathOperator('-');
		$this->assertSame('Num', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Num'),
			new BindValue('b', 'Num'),
		], $inst->getBinds());
		$this->assertSame('<Math.->', (string) $inst);
	}



	/**
	 * @dataProvider dataMinusApply
	 */
	#[DataProvider('dataMinusApply')]
	function testMinusApply($a, $b, $expected)
	{
		$inst = new MathOperator('-');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'Int'),
			new FinalValue($b, 'Int'),
		])->unpack());
	}



	// ---- * -----------------------------------------------------------------

	function testMultiply()
	{
		$inst = new MathOperator('*');
		$this->assertSame('Num', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Num'),
			new BindValue('b', 'Num'),
		], $inst->getBinds());
		$this->assertSame('<Math.*>', (string) $inst);
	}



	/**
	 * @dataProvider dataMultiplyApply
	 */
	#[DataProvider('dataMultiplyApply')]
	function testMultiplyApply($a, $b, $expected)
	{
		$inst = new MathOperator('*');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'Int'),
			new FinalValue($b, 'Int'),
		])->unpack());
	}



	// ---- div ---------------------------------------------------------------

	function testDiv()
	{
		$inst = new MathOperator('div');
		$this->assertSame('Num', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Num'),
			new BindValue('b', 'Num'),
		], $inst->getBinds());
		$this->assertSame('<Math.div>', (string) $inst);
	}



	/**
	 * @dataProvider dataDivApply
	 */
	#[DataProvider('dataDivApply')]
	function testDivApply($a, $b, $expected)
	{
		$inst = new MathOperator('div');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'Int'),
			new FinalValue($b, 'Int'),
		])->unpack());
	}



	// ---- mod ---------------------------------------------------------------

	function testMod()
	{
		$inst = new MathOperator('mod');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Int'),
			new BindValue('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame('<Math.mod>', (string) $inst);
	}



	/**
	 * @dataProvider dataModApply
	 */
	#[DataProvider('dataModApply')]
	function testModApply(int $a, int $b, int $expected)
	{
		$inst = new MathOperator('mod');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'Int'),
			new FinalValue($b, 'Int'),
		])->unpack());
	}



	// ---- ceil --------------------------------------------------------------

	function testCeil()
	{
		$inst = new MathOperator('ceil');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Num'),
		], $inst->getBinds());
		$this->assertSame('<Math.ceil>', (string) $inst);
	}



	/**
	 * @dataProvider dataCeilApply
	 */
	#[DataProvider('dataCeilApply')]
	function testCeilApply(float $val, int $expected)
	{
		$inst = new MathOperator('ceil');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($val, 'Real'),
		])->unpack());
	}



	// ---- floor -------------------------------------------------------------

	function testFloor()
	{
		$inst = new MathOperator('floor');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Num'),
		], $inst->getBinds());
		$this->assertSame('<Math.floor>', (string) $inst);
	}



	/**
	 * @dataProvider dataFloorApply
	 */
	#[DataProvider('dataFloorApply')]
	function testFloorApply(float $val, int $expected)
	{
		$inst = new MathOperator('floor');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($val, 'Real'),
		])->unpack());
	}



	// ---- round -------------------------------------------------------------

	function testRound()
	{
		$inst = new MathOperator('round');
		$this->assertSame('Real', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Num'),
			new BindValue('precision', 'Int'),
		], $inst->getBinds());
		$this->assertSame('<Math.round>', (string) $inst);
	}



	/**
	 * @dataProvider dataRoundApply
	 */
	#[DataProvider('dataRoundApply')]
	function testRoundApply(float $val, int $precision, float $expected)
	{
		$inst = new MathOperator('round');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($val, 'Real'),
			new FinalValue($precision, 'Int'),
		])->unpack());
	}



	// ---- data providers ----------------------------------------------------

	/**
	 * @return array<mixed>
	 */
	static function dataPlusApply(): array
	{
		return [
			// Int
			[1, 1, 2],
			[0, 5, 5],
			// Real
			[1.1, 1.1, 2.2],
			[0.2, 5.5, 5.7],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataMinusApply(): array
	{
		return [
			// Int
			[1, 1, 0],
			[5, 3, 2],
			// Real
			[1.1, 1.1, 0.0],
			[1.5, 1.0, 0.5],
			[0.2, 5.5, -5.3],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataMultiplyApply(): array
	{
		return [
			// Int
			[2, 2, 4],
			[3, 4, 12],
			// Real
			[2.2, 2.3, 5.06],
			[3.6, 4.1, 14.76],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataDivApply(): array
	{
		return [
			// Int
			[4, 2, 2],
			[7, 2, 3],
			// Real
			[5.0, 2.5, 2.0],
			[3.6, 4.5, 0.8],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataModApply(): array
	{
		return [
			[5, 2, 1],
			[6, 3, 0],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataCeilApply(): array
	{
		return [
			[12.0, 12],
			[12.1, 13],
			[12.3, 13],
			[12.5, 13],
			[12.9, 13],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataFloorApply(): array
	{
		return [
			[12.0, 12],
			[12.1, 12],
			[12.3, 12],
			[12.5, 12],
			[12.9, 12],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataRoundApply(): array
	{
		return [
			[12.0, 0, 12.0],
			[12.3, 0, 12.0],
			[12.49, 0, 12.0],
			[12.499, 0, 12.0],
			[12.499, 1, 12.5],
			[12.4499, 2, 12.45],
			[12.5, 0, 13.0],
			[12.6, 0, 13.0],
			[12.9, 0, 13.0],
		];
	}

}
