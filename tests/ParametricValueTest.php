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
		), 'Num', [new BindValue('a', 'Num'), new BindValue('b', 'Num')]);
		$this->assertSame('Num', $inst->getTypeName());
		$this->assertSame(['a', 'b'], $inst->refs());
		$this->assertEquals([
			new BindValue('a', 'Num'),
			new BindValue('b', 'Num'),
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
		), 'Num', [new BindValue('a', 'Num')]);
		$this->assertSame('Num', $inst->getTypeName());
		$this->assertSame(['a'], $inst->refs());
		$this->assertEquals([
			new BindValue('a', 'Num'),
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
		), 'Num', []);
		$this->assertSame('Num', $inst->getTypeName());
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



	/**
	 * A lambda `x -> x + factor`, where `factor` is a closed-over free
	 * variable and `x` is its own formal argument. Only `factor` may be
	 * resolved ahead of time via partialApply(); `x` must still come from
	 * the eventual caller.
	 */
	function testPartialApplyClosesOverFreeVariableOnly()
	{
		$inst = ParametricValue::Expr_(Expr::Bin_(
			'x',
			new MathOperator('+'),
			'factor'
		), 'Num', [new BindValue('factor', 'Num'), new BindValue('x', 'Num')])
			->markClosureNames(['factor']);

		// Supplying an unrelated value under a different name changes nothing.
		$untouched = $inst->partialApply(['unrelated' => new FinalValue(1, 'Int')]);
		$this->assertEquals([
			new BindValue('factor', 'Num'),
			new BindValue('x', 'Num'),
			], $untouched->getBinds());

		// factor is available in the surrounding scope -> gets closed over,
		// leaving only the lambda's own argument `x` to be supplied later.
		$closed = $inst->partialApply(['factor' => new FinalValue(10, 'Int'), 'x' => new FinalValue(999, 'Int')]);
		$this->assertEquals([
			new BindValue('x', 'Num'),
			], $closed->getBinds());

		$this->assertEquals(new FinalValue(11, 'Int'), $closed->apply(['x' => new FinalValue(1, 'Int')]));
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
