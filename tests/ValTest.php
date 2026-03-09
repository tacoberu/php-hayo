<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class ValTest extends TestCase
{

	function testFinal()
	{
		$val = new FinalValue('Lorem ispum colder.', 'Str');
		$this->assertSame('Lorem ispum colder.', $val->unpack());
		$this->assertSame('Str', $val->type());
	}



	function testVariadic3()
	{
		$inst = ParametricValue::Expr_(Expr::Bin_('a',
			new MathOperator('+'),
			'b'
		), 'Num', [new BindValue('a', 'Num'), new BindValue('b', 'Num')]);
		$this->assertSame('Num', $inst->getTypeName());
		$this->assertSame(['a', 'b'], $inst->refs());
		$this->assertEquals([
			new BindValue('a', 'Num'),
			new BindValue('b', 'Num'),
		], $inst->getBinds());
		$this->assertEquals(new FinalValue(16, 'Num')
			, $inst->apply([
				'a' => new FinalValue(8, 'Num'),
				'b' => new FinalValue(8, 'Num'),
				]));
	}



	function testVariadic1()
	{
		$inst = ParametricValue::Expr_(Expr::Bin_(
			new FinalValue(41, 'Num'),
			new MathOperator('+'),
			'a'
		), 'Num', [new BindValue('a', 'Num')]);
		$this->assertSame('Num', $inst->getTypeName());
		$this->assertSame(['a'], $inst->refs());
		$this->assertEquals([
			new BindValue('a', 'Num'),
		], $inst->getBinds());
		$this->assertEquals(new FinalValue(49, 'Num')
			, $inst->apply(['a' => new FinalValue(8, 'Num')]));
	}



	function testVariadic2()
	{
		$inst = ParametricValue::Expr_(Expr::Bin_(
			new FinalValue(41, 'Num'),
			new MathOperator('+'),
			new FinalValue(11, 'Num')
		), 'Num', []);
		$this->assertSame('Num', $inst->getTypeName());
		$this->assertSame([], $inst->refs());
		$this->assertEquals([], $inst->getBinds());
		$this->assertEquals(new FinalValue(52, 'Num')
			, $inst->apply([]));
	}

}
