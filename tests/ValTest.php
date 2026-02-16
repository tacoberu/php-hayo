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
		$val = new FinalVal('Lorem ispum colder.', 'Str');
		$this->assertSame('Lorem ispum colder.', $val->unpack());
		$this->assertSame('Str', $val->getTypeName());
	}



	function testVariadic3()
	{
		$inst = VariadicVal::Expr_(Expr::Bin_('a',
			new MathOperator('+'),
			'b'
		), 'Int', [new BindVal('a', 'Int'), new BindVal('b', 'Int')]);
		$this->assertSame('Int', $inst->getTypeName());
		$this->assertSame(['a', 'b'], $inst->refs());
		$this->assertEquals([
			new BindVal('a', 'Int'),
			new BindVal('b', 'Int'),
		], $inst->getBinds());
		$this->assertEquals(new FinalVal(16, 'Int')
			, $inst->apply([
				'a' => new FinalVal(8, 'Int'),
				'b' => new FinalVal(8, 'Int'),
				]));
	}



	function testVariadic1()
	{
		$inst = VariadicVal::Expr_(Expr::Bin_(
			new FinalVal(41, 'Int'),
			new MathOperator('+'),
			'a'
		), 'Int', [new BindVal('a', 'Int')]);
		$this->assertSame('Int', $inst->getTypeName());
		$this->assertSame(['a'], $inst->refs());
		$this->assertEquals([
			new BindVal('a', 'Int'),
		], $inst->getBinds());
		$this->assertEquals(new FinalVal(49, 'Int')
			, $inst->apply(['a' => new FinalVal(8, 'Int')]));
	}



	function testVariadic2()
	{
		$inst = VariadicVal::Expr_(Expr::Bin_(
			new FinalVal(41, 'Int'),
			new MathOperator('+'),
			new FinalVal(11, 'Int')
		), 'Int', []);
		$this->assertSame('Int', $inst->getTypeName());
		$this->assertSame([], $inst->refs());
		$this->assertEquals([], $inst->getBinds());
		$this->assertEquals(new FinalVal(52, 'Int')
			, $inst->apply([]));
	}

}
