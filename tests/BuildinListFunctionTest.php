<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class BuildinListFunctionTest extends TestCase
{

	function testLen()
	{
		$inst = new BuildinListFunction('list.len');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindVal('src', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame(['src'], $inst->refs());
		$this->assertEquals(new FinalVal(2, 'Int'), $inst->apply([
			'src' => new FinalVal([
				new FinalVal(1, 'Int'),
				new FinalVal(1, 'Int'),
			], 'List'),
		]));
	}



	function testFirst()
	{
		$inst = new BuildinListFunction('list.first');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindVal('src', 'List<a>'),
			new BindVal('default', 'a'),
		], $inst->getBinds());
		$this->assertSame(['src', 'default'], $inst->refs());
		$this->assertEquals(new FinalVal(2, 'a'), $inst->apply([
			'src' => new FinalVal([
				new FinalVal(2, 'Int'),
				new FinalVal(4, 'Int'),
			], 'List'),
			'default' => new FinalVal(0, 'Int'),
		]));
	}



	function testFirstDefault()
	{
		$inst = new BuildinListFunction('list.first');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindVal('src', 'List<a>'),
			new BindVal('default', 'a'),
		], $inst->getBinds());
		$this->assertSame(['src', 'default'], $inst->refs());
		$this->assertEquals(new FinalVal(42, 'a'), $inst->apply([
			'src' => new FinalVal([], 'List'),
			'default' => new FinalVal(42, 'Int'),
		]));
	}



	function testAt()
	{
		$inst = new BuildinListFunction('list.at');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindVal('index', 'Int'),
			new BindVal('src', 'List<a>'),
			new BindVal('default', 'a'),
		], $inst->getBinds());
		$this->assertSame(['index', 'src', 'default'], $inst->refs());
		$this->assertEquals(new FinalVal(4, 'a'), $inst->apply([
			'index' => new FinalVal(1, 'Int'),
			'src' => new FinalVal([
				new FinalVal(2, 'Int'),
				new FinalVal(4, 'Int'),
			], 'List'),
			'default' => new FinalVal(0, 'Int'),
		]));
	}



	function testAt_out()
	{
		$inst = new BuildinListFunction('list.at');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindVal('index', 'Int'),
			new BindVal('src', 'List<a>'),
			new BindVal('default', 'a'),
		], $inst->getBinds());
		$this->assertSame(['index', 'src', 'default'], $inst->refs());
		$this->assertEquals(new FinalVal(42, 'a'), $inst->apply([
			'index' => new FinalVal(999, 'Int'),
			'src' => new FinalVal([
				new FinalVal(2, 'Int'),
				new FinalVal(4, 'Int'),
			], 'List'),
			'default' => new FinalVal(42, 'Int'),
		]));
	}



	function testExist()
	{
		$inst = new BuildinListFunction('list.exist');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindVal('index', 'Int'),
			new BindVal('src', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame(['index', 'src'], $inst->refs());
		$this->assertEquals(new FinalVal(true, 'Bool'), $inst->apply([
			'index' => new FinalVal(1, 'Int'),
			'src' => new FinalVal([
				new FinalVal(2, 'Int'),
				new FinalVal(4, 'Int'),
			], 'List'),
		]));
	}

}
