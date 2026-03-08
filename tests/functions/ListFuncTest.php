<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class ListFuncTest extends TestCase
{

	function testLen()
	{
		$inst = new ListFunc('list.len');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindVal('src', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame(['src'], $inst->refs());
		$this->assertEquals(new FinalValue(2, 'Int'), $inst->apply([
			'src' => new FinalValue([
				new FinalValue(1, 'Int'),
				new FinalValue(1, 'Int'),
			], 'List'),
		]));
	}



	function testFirst()
	{
		$inst = new ListFunc('list.first');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindVal('src', 'List<a>'),
			new BindVal('default', 'a'),
		], $inst->getBinds());
		$this->assertSame(['src', 'default'], $inst->refs());
		$this->assertEquals(new FinalValue(2, 'a'), $inst->apply([
			'src' => new FinalValue([
				new FinalValue(2, 'Int'),
				new FinalValue(4, 'Int'),
			], 'List'),
			'default' => new FinalValue(0, 'Int'),
		]));
	}



	function testFirstDefault()
	{
		$inst = new ListFunc('list.first');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindVal('src', 'List<a>'),
			new BindVal('default', 'a'),
		], $inst->getBinds());
		$this->assertSame(['src', 'default'], $inst->refs());
		$this->assertEquals(new FinalValue(42, 'a'), $inst->apply([
			'src' => new FinalValue([], 'List'),
			'default' => new FinalValue(42, 'Int'),
		]));
	}



	function testAt()
	{
		$inst = new ListFunc('list.at');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindVal('index', 'Int'),
			new BindVal('src', 'List<a>'),
			new BindVal('default', 'a'),
		], $inst->getBinds());
		$this->assertSame(['index', 'src', 'default'], $inst->refs());
		$this->assertEquals(new FinalValue(4, 'a'), $inst->apply([
			'index' => new FinalValue(1, 'Int'),
			'src' => new FinalValue([
				new FinalValue(2, 'Int'),
				new FinalValue(4, 'Int'),
			], 'List'),
			'default' => new FinalValue(0, 'Int'),
		]));
	}



	function testAt_out()
	{
		$inst = new ListFunc('list.at');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindVal('index', 'Int'),
			new BindVal('src', 'List<a>'),
			new BindVal('default', 'a'),
		], $inst->getBinds());
		$this->assertSame(['index', 'src', 'default'], $inst->refs());
		$this->assertEquals(new FinalValue(42, 'a'), $inst->apply([
			'index' => new FinalValue(999, 'Int'),
			'src' => new FinalValue([
				new FinalValue(2, 'Int'),
				new FinalValue(4, 'Int'),
			], 'List'),
			'default' => new FinalValue(42, 'Int'),
		]));
	}



	function testExist()
	{
		$inst = new ListFunc('list.exist');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindVal('index', 'Int'),
			new BindVal('src', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame(['index', 'src'], $inst->refs());
		$this->assertEquals(new FinalValue(true, 'Bool'), $inst->apply([
			'index' => new FinalValue(1, 'Int'),
			'src' => new FinalValue([
				new FinalValue(2, 'Int'),
				new FinalValue(4, 'Int'),
			], 'List'),
		]));
	}

}
