<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class BuildinStringFunctionTest extends TestCase
{

	function testStringsLen()
	{
		$inst = new BuildinStringFunction('strings.len');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindVal('src', 'Str'),
		], $inst->getBinds());
		$this->assertSame(['src'], $inst->refs());
		$this->assertEquals(new FinalVal(4, 'Int'), $inst->apply([
			new FinalVal("abcd", 'Str'),
		]));
	}



	function testStringsSplit()
	{
		$inst = new BuildinStringFunction('strings.split');
		$this->assertSame('List<Str>', $inst->type());
		$this->assertEquals([
			new BindVal('sep', 'Str'),
			new BindVal('src', 'Str'),
		], $inst->getBinds());
		$this->assertSame(['sep', 'src'], $inst->refs());
		$this->assertEquals(new FinalVal([
			new FinalVal("ab", 'Str'),
			new FinalVal("cd", 'Str'),
		], 'List'), $inst->apply([
			new FinalVal(" ", 'Str'),
			new FinalVal("ab cd", 'Str'),
		]));
	}



	function testStringsConcat()
	{
		$inst = new BuildinStringFunction('strings.concat');
		$this->assertSame('Str', $inst->type());
		$this->assertEquals([
			new BindVal('sep', 'Str'),
			new BindVal('src', 'List<Str>'),
		], $inst->getBinds());
		$this->assertSame(['sep', 'src'], $inst->refs());
		$this->assertEquals(new FinalVal("ab cd", 'Str'), $inst->apply([
			new FinalVal(" ", 'Str'),
			new FinalVal([
				new FinalVal("ab", 'Str'),
				new FinalVal("cd", 'Str'),
			], 'List'),
		]));
	}

}
