<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


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

}
