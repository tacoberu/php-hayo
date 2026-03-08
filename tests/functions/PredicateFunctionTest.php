<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;


class PredicateFunctionTest extends TestCase
{

	function testPredicatesProvider()
	{
		$this->assertEquals(new PredicateFunction('=='), (new PredicatesProvider())->lookup('=='));
	}



	// ---- == ----------------------------------------------------------------

	function testEq()
	{
		$inst = new PredicateFunction('==');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'a'),
			new BindValue('b', 'a'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.==>', (string) $inst);
	}



	#[DataProvider('dataEqApply')]
	function testEqApply($a, $b, bool $expected)
	{
		$inst = new PredicateFunction('==');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, '?'),
			new FinalValue($b, '?'),
		])->unpack());
	}



	// ---- != ----------------------------------------------------------------

	function testNeq()
	{
		$inst = new PredicateFunction('!=');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'a'),
			new BindValue('b', 'a'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.!=>', (string) $inst);
	}



	#[DataProvider('dataNeqApply')]
	function testNeqApply($a, $b, bool $expected)
	{
		$inst = new PredicateFunction('!=');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, '?'),
			new FinalValue($b, '?'),
		])->unpack());
	}



	// ---- < -----------------------------------------------------------------

	function testLt()
	{
		$inst = new PredicateFunction('<');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'a'),
			new BindValue('b', 'a'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.<>', (string) $inst);
	}



	#[DataProvider('dataLtApply')]
	function testLtApply($a, $b, bool $expected)
	{
		$inst = new PredicateFunction('<');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, '?'),
			new FinalValue($b, '?'),
		])->unpack());
	}



	// ---- > -----------------------------------------------------------------

	function testGt()
	{
		$inst = new PredicateFunction('>');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'a'),
			new BindValue('b', 'a'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.>>', (string) $inst);
	}



	#[DataProvider('dataGtApply')]
	function testGtApply($a, $b, bool $expected)
	{
		$inst = new PredicateFunction('>');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, '?'),
			new FinalValue($b, '?'),
		])->unpack());
	}



	// ---- <= ----------------------------------------------------------------

	function testLte()
	{
		$inst = new PredicateFunction('<=');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'a'),
			new BindValue('b', 'a'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.<=>', (string) $inst);
	}



	#[DataProvider('dataLteApply')]
	function testLteApply($a, $b, bool $expected)
	{
		$inst = new PredicateFunction('<=');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, '?'),
			new FinalValue($b, '?'),
		])->unpack());
	}



	// ---- >= ----------------------------------------------------------------

	function testGte()
	{
		$inst = new PredicateFunction('>=');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'a'),
			new BindValue('b', 'a'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.>=>', (string) $inst);
	}



	#[DataProvider('dataGteApply')]
	function testGteApply($a, $b, bool $expected)
	{
		$inst = new PredicateFunction('>=');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, '?'),
			new FinalValue($b, '?'),
		])->unpack());
	}



	// ---- and / && ----------------------------------------------------------

	function testAnd()
	{
		$inst = new PredicateFunction('and');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Bool'),
			new BindValue('b', 'Bool'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.and>', (string) $inst);
	}



	function testAndAlias()
	{
		$inst = new PredicateFunction('&&');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Bool'),
			new BindValue('b', 'Bool'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.&&>', (string) $inst);
	}



	#[DataProvider('dataAndApply')]
	function testAndApply(bool $a, bool $b, bool $expected)
	{
		$inst = new PredicateFunction('and');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, '?'),
			new FinalValue($b, '?'),
		])->unpack());
	}



	// ---- or / || -----------------------------------------------------------

	function testOr()
	{
		$inst = new PredicateFunction('or');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Bool'),
			new BindValue('b', 'Bool'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.or>', (string) $inst);
	}



	function testOrAlias()
	{
		$inst = new PredicateFunction('||');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Bool'),
			new BindValue('b', 'Bool'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.||>', (string) $inst);
	}



	#[DataProvider('dataOrApply')]
	function testOrApply(bool $a, bool $b, bool $expected)
	{
		$inst = new PredicateFunction('or');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, '?'),
			new FinalValue($b, '?'),
		])->unpack());
	}



	// ---- not ---------------------------------------------------------------

	function testNot()
	{
		$inst = new PredicateFunction('not');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'Bool'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.not>', (string) $inst);
	}



	#[DataProvider('dataNotApply')]
	function testNotApply(bool $a, bool $expected)
	{
		$inst = new PredicateFunction('not');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, '?'),
		])->unpack());
	}



	// ---- in ----------------------------------------------------------------

	function testIn()
	{
		$inst = new PredicateFunction('in');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'a'),
			new BindValue('b', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.in>', (string) $inst);
	}



	/**
	 * @param array<mixed> $b
	 */
	#[DataProvider('dataInApply')]
	function testInApply($a, array $b, bool $expected)
	{
		$inst = new PredicateFunction('in');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'a'),
			new FinalValue($b, 'List<a>'),
		])->unpack());
	}



	// ---- has ---------------------------------------------------------------

	function testHas()
	{
		$inst = new PredicateFunction('has');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'List<a>'),
			new BindValue('b', 'a'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.has>', (string) $inst);
	}



	/**
	 * @param array<mixed> $a
	 */
	#[DataProvider('dataHasApply')]
	function testHasApply(array $a, $b, bool $expected)
	{
		$inst = new PredicateFunction('has');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'List<a>'),
			new FinalValue($b, 'a'),
		])->unpack());
	}



	// ---- data providers ----------------------------------------------------

	function testSuperset()
	{
		$inst = new PredicateFunction('superset');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'List<a>'),
			new BindValue('b', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.superset>', (string) $inst);
	}



	/**
	 * @param array<mixed> $a
	 * @param array<mixed> $b
	 */
	#[DataProvider('dataSupersetApply')]
	function testSupersetApply(array $a, array $b, bool $expected)
	{
		$inst = new PredicateFunction('superset');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'List<a>'),
			new FinalValue($b, 'List<a>'),
		])->unpack());
	}



	// ---- subset ------------------------------------------------------------

	function testSubset()
	{
		$inst = new PredicateFunction('subset');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'List<a>'),
			new BindValue('b', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.subset>', (string) $inst);
	}



	/**
	 * @param array<mixed> $a
	 * @param array<mixed> $b
	 */
	#[DataProvider('dataSubsetApply')]
	function testSubsetApply(array $a, array $b, bool $expected)
	{
		$inst = new PredicateFunction('subset');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'List<a>'),
			new FinalValue($b, 'List<a>'),
		])->unpack());
	}



	// ---- intersects --------------------------------------------------------

	function testIntersects()
	{
		$inst = new PredicateFunction('intersects');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('a', 'List<a>'),
			new BindValue('b', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame('<Predicate.intersects>', (string) $inst);
	}



	/**
	 * @param array<mixed> $a
	 * @param array<mixed> $b
	 */
	#[DataProvider('dataIntersectsApply')]
	function testIntersectsApply(array $a, array $b, bool $expected)
	{
		$inst = new PredicateFunction('intersects');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($a, 'List<a>'),
			new FinalValue($b, 'List<a>'),
		])->unpack());
	}



	/**
	 * @return array<mixed>
	 */
	static function dataEqApply(): array
	{
		return [
			["", 0, False],
			[" ", "", False],
			[1, 1.0, False],
			[0, 0, True],
			[1, 1, True],
			[" ", " ", True],
			["a", "a", True],
			[["a"], ["a"], True],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataNeqApply(): array
	{
		return [
			["", 0, True],
			[" ", "", True],
			[1, 1.0, True],
			[0, 0, False],
			[1, 1, False],
			["a", "a", False],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataLtApply(): array
	{
		return [
			[1, 2, True],
			[0, 1, True],
			[1, 1, False],
			[2, 1, False],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataGtApply(): array
	{
		return [
			[2, 1, True],
			[1, 0, True],
			[1, 1, False],
			[1, 2, False],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataLteApply(): array
	{
		return [
			[1, 2, True],
			[1, 1, True],
			[2, 1, False],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataGteApply(): array
	{
		return [
			[2, 1, True],
			[1, 1, True],
			[1, 2, False],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataAndApply(): array
	{
		return [
			[True, True, True],
			[True, False, False],
			[False, True, False],
			[False, False, False],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataOrApply(): array
	{
		return [
			[True, True, True],
			[True, False, True],
			[False, True, True],
			[False, False, False],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataNotApply(): array
	{
		return [
			[True, False],
			[False, True],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataInApply(): array
	{
		return [
			["a", [], False],
			["a", ['b'], False],
			[" ", [" "], True],
			["a", ["a"], True],
			["a", ['b', "a"], True],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataHasApply(): array
	{
		return [
			[[], "a", False],
			[['b'], "a", False],
			[[" "], " ", True],
			[["a"], "a", True],
			[['b', "a"], "a", True],
		];
	}// ---- superset ----------------------------------------------------------




	// ---- data providers (superset/subset/intersects) -----------------------

	/**
	 * @return array<mixed>
	 */
	static function dataSupersetApply(): array
	{
		// superset: all elements of b are in a
		return [
			[['a', 'b', 'c'], ['a', 'b'], True],
			[['a', 'b', 'c'], ['a', 'b', 'c'], True],
			[['a', 'b', 'c'], [], True],
			[[], [], True],
			[['a', 'b'], ['a', 'b', 'c'], False],
			[['a'], ['b'], False],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataSubsetApply(): array
	{
		// subset: all elements of a are in b
		return [
			[['a', 'b'], ['a', 'b', 'c'], True],
			[['a', 'b', 'c'], ['a', 'b', 'c'], True],
			[[], ['a', 'b'], True],
			[[], [], True],
			[['a', 'b', 'c'], ['a', 'b'], False],
			[['b'], ['a'], False],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataIntersectsApply(): array
	{
		// intersects: sets share at least one common element
		return [
			[['a', 'b'], ['b', 'c'], True],
			[['a'], ['a'], True],
			[['a', 'b', 'c'], ['c', 'd'], True],
			[[], [], False],
			[['a'], ['b'], False],
			[['a', 'b'], ['c', 'd'], False],
		];
	}

}
