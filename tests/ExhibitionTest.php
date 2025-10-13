<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class ExhibitionTest extends TestCase
{

	function testMathPlus()
	{
		//~ dump($this->compile('41 + 3'));
		$this->assertEquals(new FinalVal(44, 'Int'), $this->compile('41 + 3'));
		$this->assertEquals(44, $this->compile('41 + 3')->unpack());
	}



	function testBindLocalVars()
	{
		$this->assertEquals(new FinalVal(46, 'Int'), $this->compile('
a = 5
-- nějaký komentář
41 + a'));
	}



	function testReturnStructConst()
	{
		$this->assertEquals(new FinalVal((object) [
			'a' => new FinalVal(4, 'Int'),
			'b' => new FinalVal('abc', 'Str'),
		], 'Dict'), $this->compile('
{a: 4, b: "abc"}'));
	}



	function testReturnStructWithBind()
	{
		$this->assertEquals(new FinalVal((object) [
			'a' => new FinalVal(45, 'Int'),
			'b' => new FinalVal('abc', 'Str'),
			'c' => new FinalVal(88, 'Int'),
		], 'Dict'), $this->compile('
{a: a, b: "abc", c: c}')
		->apply(['a' => new FinalVal(45, 'Int'), 'c' => new FinalVal(88, 'Int')])
		);
	}



	/**
	 * Takhle bych si to představoval používat.
	 * Ale místo callbacku bude zkomilovaná rutina, která se dá uložit do souboru.
	 * Ačkoliv, ona celá ta rutina se dá uložit do PHP souboru.
	 */
	function testVolaniFunkce()
	{
		$fn = VariadicVal::expr(new Expr([
			new BuildinMathOperator('+'),
			new FinalVal(41, 'Int'),
			'a',
		]), 'Int', [new BindVal('a', 'Int')]);
		//~ $fn = new VariadicVal(static function ($args) {
			//~ return 41 + $args[0]->unpack();
		//~ }, 'Int', [new BindVal('a', 'Int')]);
		$this->assertSame('Int', $fn->getTypeName());
		$this->assertEquals(['a'], $fn->refs());
		$this->assertEquals([new BindVal('a', 'Int')], $fn->getBinds());
		//~ $this->assertSame(['a' => 'Int'], $fn->getBindNames());
		$this->assertEquals(new FinalVal(42, 'Int'), $fn->apply(['a' => new FinalVal(1, 'Int')]));
	}



	function testStringLen()
	{
		$this->assertEquals(new FinalVal(2, 'Int'), $this->compile('
(strings.len "hi")')
		);
	}



	function testStringLenAssignAsSymbol()
	{
		$this->assertEquals(new FinalVal(2, 'Int'), $this->compile('
a = (strings.len "hi")
a')
		);
	}



	function testStringSplit()
	{
		$this->assertEquals(new FinalVal([
			new FinalVal('une', 'Str'),
			new FinalVal(' deux', 'Str'),
			new FinalVal(' trois', 'Str'),
		], 'List'), $this->compile('
(strings.split "," "une, deux, trois")')
		);
	}



	function testListFirst()
	{
		$this->assertEquals(new FinalVal("une", 'a'), $this->compile('
xs = (strings.split "," "une, deux, trois")
-- xs = []
(list.first xs "")')
		);
	}



	function testListFirstDefault()
	{
		$this->assertEquals(new FinalVal('', 'a'), $this->compile('
xs = []
(list.first xs "")')
		);
	}



	function testListExist()
	{
		$this->assertEquals(new FinalVal(true, 'Bool'), $this->compile('
xs = (strings.split "," "une, deux, trois")
(list.exist 0 xs)')
		);
	}



	function testListAt()
	{
		$this->assertEquals(new FinalVal(' deux', 'a'), $this->compile('
xs = (strings.split "," "une, deux, trois")
(list.at 1 xs "")')
		);
	}



	function testComposeDictStatic()
	{
		$this->assertEquals(new FinalVal((object) [
			'une' => new FinalVal('une', 'a'),
			'deux' => new FinalVal(' deux', 'a'),
			'trois' => new FinalVal(' trois', 'a'),
		], 'Dict'), $this->compile('
xs = (strings.split "," "une, deux, trois")
{
	une: (list.first xs "")
	deux: (list.at 1 xs "")
	trois: (list.at 2 xs "")
}')
		);
	}



	function testComposeListStatic()
	{
		$this->assertEquals(new FinalVal([
			new FinalVal('une', 'a'),
			new FinalVal(' deux', 'a'),
			new FinalVal(' trois', 'a'),
		], 'List'), $this->compile('
xs = (strings.split "," "une, deux, trois")
[
	(list.first xs "")
	(list.at 1 xs "")
	(list.at 2 xs "")
]')
		);
	}



	function testComposeTupleStatic()
	{
		$this->assertEquals(new FinalVal([
			new FinalVal('une', 'a'),
			new FinalVal(' deux', 'a'),
			new FinalVal(' trois', 'a'),
		], 'Tuple'), $this->compile('
xs = (strings.split "," "une, deux, trois")
(
	(list.first xs "")
	(list.at 1 xs "")
	(list.at 2 xs "")
)')
		);
	}



	function testComposeDict()
	{
		$call = $this->compile('
xs = (strings.split "," src)
{
	une: (list.first xs "")
	deux: (list.at 1 xs "")
	trois: (list.at 2 xs "")
}');
		$this->assertSame("?", $call->type());
		$this->assertEquals([
			new BindVal('src', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalVal((object) [
			'une' => new FinalVal('Lorem ipsum', 'a'),
			'deux' => new FinalVal(' doler ist', 'a'),
			'trois' => new FinalVal('', 'a'),
			], 'Dict')
			, $call->apply(['src' => new FinalVal("Lorem ipsum, doler ist", 'Str')]));
	}



	private function compile($src)
	{
		return (new Compiler())->compile($src);
	}

}
