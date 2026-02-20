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

	function testPlusl()
	{
		$inst = new MathOperator('+');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindVal('a', 'Int'),
			new BindVal('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame(['a', 'b'], $inst->refs());
		$this->assertEquals(new FinalVal(2, 'Int'), $inst->apply([
			new FinalVal(1, 'Int'),
			new FinalVal(1, 'Int'),
		]));
	}



	function testMinus()
	{
		$inst = new MathOperator('-');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindVal('a', 'Int'),
			new BindVal('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame(['a', 'b'], $inst->refs());
		$this->assertEquals(new FinalVal(0, 'Int'), $inst->apply([
			new FinalVal(1, 'Int'),
			new FinalVal(1, 'Int'),
		]));
	}



	function testMultiple()
	{
		$inst = new MathOperator('*');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindVal('a', 'Int'),
			new BindVal('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame(['a', 'b'], $inst->refs());
		$this->assertEquals(new FinalVal(4, 'Int'), $inst->apply([
			new FinalVal(2, 'Int'),
			new FinalVal(2, 'Int'),
		]));
	}



	function testDiv()
	{
		$inst = new MathOperator('div');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindVal('a', 'Int'),
			new BindVal('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame(['a', 'b'], $inst->refs());
		$this->assertEquals(new FinalVal(2, 'Int'), $inst->apply([
			new FinalVal(4, 'Int'),
			new FinalVal(2, 'Int'),
		]));
	}



	function testMod()
	{
		$inst = new MathOperator('mod');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindVal('a', 'Int'),
			new BindVal('b', 'Int'),
		], $inst->getBinds());
		$this->assertSame(['a', 'b'], $inst->refs());
		$this->assertEquals(new FinalVal(1, 'Int'), $inst->apply([
			new FinalVal(5, 'Int'),
			new FinalVal(2, 'Int'),
		]));
	}



	#[DataProvider('dataCeilApply')]
	function testCeilApply(float $val, $expected)
	{
		$inst = new MathOperator('ceil');
		$this->assertEquals($expected, $inst->apply([
			new FinalVal($val, 'Real'),
		])->unpack());
	}



	/**
	 * @return array<mixed>
	 */
	static function dataCeilApply(): array
	{
		return [
			[12.0, 12, ],
			[12.1, 13, ],
			[12.3, 13, ],
			[12.5, 13, ],
			[12.9, 13, ],
		];
	}



	#[DataProvider('dataFloorApply')]
	function testFloorApply(float $val, $expected)
	{
		$inst = new MathOperator('floor');
		$this->assertEquals($expected, $inst->apply([
			new FinalVal($val, 'Real'),
		])->unpack());
	}



	/**
	 * @return array<mixed>
	 */
	static function dataFloorApply(): array
	{
		return [
			[12.0, 12, ],
			[12.1, 12, ],
			[12.3, 12, ],
			[12.5, 12, ],
			[12.9, 12, ],
		];
	}



	#[DataProvider('dataRoundApply')]
	function testRoundApply(float $val, int $precision, $expected)
	{
		$inst = new MathOperator('round');
		$this->assertEquals($expected, $inst->apply([
			new FinalVal($val, 'Real'),
			new FinalVal($precision, 'Int'),
		])->unpack());
	}



	/**
	 * @return array<mixed>
	 */
	static function dataRoundApply(): array
	{
		return [
			[12.0, 0, 12.0,	],
			[12.3, 0, 12.0,	],
			[12.49, 0, 12.0,	],
			[12.499, 0, 12.0,	],
			[12.499, 1, 12.5,	],
			[12.4499, 2, 12.45,	],
			[12.5, 0, 13.0,	],
			[12.6, 0, 13.0,	],
			[12.9, 0, 13.0,	],
		];
	}

}
