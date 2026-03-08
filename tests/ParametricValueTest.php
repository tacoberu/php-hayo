<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class ParametricValueTest extends TestCase
{

	function testVariadic3()
	{
		$inst = ParametricValue::Expr_(Expr::Bin_(
			'a',
			new MathOperator('+'),
			'b'
		), 'Int', [new BindValue('a', 'Int'), new BindValue('b', 'Int')]);
		$this->assertSame('Int', $inst->getTypeName());
		$this->assertSame(['a', 'b'], $inst->refs());
		$this->assertEquals([
			new BindValue('a', 'Int'),
			new BindValue('b', 'Int'),
		], $inst->getBinds());
		$this->assertEquals(new FinalValue(16, 'Int')
			, $inst->apply([
				'a' => new FinalValue(8, 'Int'),
				'b' => new FinalValue(8, 'Int'),
				]));
	}



	function testVariadic1()
	{
		$inst = ParametricValue::Expr_(Expr::Bin_(
			new FinalValue(41, 'Int'),
			new MathOperator('+'),
			'a'
		), 'Int', [new BindValue('a', 'Int')]);
		$this->assertSame('Int', $inst->getTypeName());
		$this->assertSame(['a'], $inst->refs());
		$this->assertEquals([
			new BindValue('a', 'Int'),
		], $inst->getBinds());
		$this->assertEquals(new FinalValue(49, 'Int')
			, $inst->apply(['a' => new FinalValue(8, 'Int')]));
	}



	function testVariadic2()
	{
		$inst = ParametricValue::Expr_(Expr::Bin_(
			new FinalValue(41, 'Int'),
			new MathOperator('+'),
			new FinalValue(11, 'Int')
		), 'Int', []);
		$this->assertSame('Int', $inst->getTypeName());
		$this->assertSame([], $inst->refs());
		$this->assertEquals([], $inst->getBinds());
		$this->assertEquals(new FinalValue(52, 'Int')
			, $inst->apply([]));
	}



	function testCompositeDict()
	{
		$inst = ParametricValue::Dict_(Composite::Dict_([
			'a' => Composite::Dict_([
				'b' => new BindValue('content', '?'),
			]),
		]), [new BindValue('content', 'Str')]);
		$this->assertSame('Dict', $inst->getTypeName());
		// Because ParametricValue has content bound, there are no further dependencies.
		// It also converts the ambiguous symbol represented as a string into an unambiguously bound symbol.
		$this->assertSame([], $inst->refs());
		$this->assertEquals([
			new BindValue('content', 'Str'),
		], $inst->getBinds());
		$this->assertEquals(new FinalValue((object) [
			'a' => new FinalValue((object) [
				'b' => new FinalValue("Lorem ipsum", 'Str'),
			], 'Dict')], 'Dict')
			, $inst->apply(['content' => new FinalValue("Lorem ipsum", 'Str')]));
	}



	function ____testCompositeDictTerm()
	{
		$inst = ParametricValue::Dict_(Composite::Dict_([
			'a' => ParametricValue::Dict_(Composite::Dict_([
				'b' => 'content',
			])),
		]));
		$this->assertSame('DICT', $inst->type());
		$this->assertSame(['content'], $inst->refs());
	}

}
