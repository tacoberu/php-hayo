<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class VariadicValTest extends TestCase
{

	function testVariadic3()
	{
		$inst = VariadicVal::Expr_(Expr::Bin_(
			'a',
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



	function testCompositeDict()
	{
		$inst = VariadicVal::Dict_(Composite::Dict_([
			'a' => Composite::Dict_([
				'b' => new BindVal('content', '?'),
			]),
		]), [new BindVal('content', 'Str')]);
		$this->assertSame('Dict', $inst->getTypeName());
		// Protože VariadicVal má nabindován content, tak nejsou žádné další závislosti.
		// A navíc má převedený nejasný symbol reprezontovaný stringem na jendoznačně bindovaný symbol.
		$this->assertSame([], $inst->refs());
		$this->assertEquals([
			new BindVal('content', 'Str'),
		], $inst->getBinds());
		$this->assertEquals(new FinalVal((object) [
			'a' => new FinalVal((object) [
				'b' => new FinalVal("Lorem ipsum", 'Str'),
			], 'Dict')], 'Dict')
			, $inst->apply(['content' => new FinalVal("Lorem ipsum", 'Str')]));
	}



	function ____testCompositeDictTerm()
	{
		$inst = VariadicVal::Dict_(Composite::Dict_([
			'a' => VariadicVal::Dict_(Composite::Dict_([
				'b' => 'content',
			])),
		]));
		$this->assertSame('DICT', $inst->type());
		$this->assertSame(['content'], $inst->refs());
	}

}
