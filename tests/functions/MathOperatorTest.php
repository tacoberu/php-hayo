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
		$this->assertEquals(new MathOperator('+'), (new MathsProvider())->lookup('+'));
	}



	// ---- + -----------------------------------------------------------------

	function testPlus()
	{
		$inst = new MathOperator('+');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Int'),
			new BindValue('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame('<Math.+>', (string) $inst);
	}



	#[DataProvider('dataPlusApply')]
	function testPlusApply(int $a, int $b, int $expected)
	{
		$inst = new MathOperator('+');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'Int'),
			new FinalValue($b, 'Int'),
		])->unpack());
	}



	// ---- - -----------------------------------------------------------------

	function testMinus()
	{
		$inst = new MathOperator('-');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Int'),
			new BindValue('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame('<Math.->', (string) $inst);
	}



	#[DataProvider('dataMinusApply')]
	function testMinusApply(int $a, int $b, int $expected)
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
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Int'),
			new BindValue('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame('<Math.*>', (string) $inst);
	}



	#[DataProvider('dataMultiplyApply')]
	function testMultiplyApply(int $a, int $b, int $expected)
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
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Int'),
			new BindValue('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame('<Math.div>', (string) $inst);
	}



	#[DataProvider('dataDivApply')]
	function testDivApply(int $a, int $b, int $expected)
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
			new BindValue('a', 'Real'),
		], $inst->getBinds());
		$this->assertSame('<Math.ceil>', (string) $inst);
	}



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
			new BindValue('a', 'Real'),
		], $inst->getBinds());
		$this->assertSame('<Math.floor>', (string) $inst);
	}



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
			new BindValue('a', 'Real'),
			new BindValue('precision', 'Int'),
		], $inst->getBinds());
		$this->assertSame('<Math.round>', (string) $inst);
	}



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
			[1, 1, 2],
			[0, 5, 5],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataMinusApply(): array
	{
		return [
			[1, 1, 0],
			[5, 3, 2],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataMultiplyApply(): array
	{
		return [
			[2, 2, 4],
			[3, 4, 12],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataDivApply(): array
	{
		return [
			[4, 2, 2],
			[7, 2, 3],
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
