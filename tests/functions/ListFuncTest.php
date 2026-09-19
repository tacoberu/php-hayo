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


class ListFuncTest extends TestCase
{

	// ---- len ---------------------------------------------------------------

	function testLen()
	{
		$inst = new ListFunc('len');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame('<List.len>', (string) $inst);
	}



	/**
	 * @param array<mixed> $items
	 * @dataProvider dataLenApply
	 */
	#[DataProvider('dataLenApply')]
	function testLenApply(array $items, int $expected)
	{
		$inst = new ListFunc('len');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($items, 'List<a>'),
		])->unpack());
	}



	// ---- first -------------------------------------------------------------

	function testFirst()
	{
		$inst = new ListFunc('first');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'List<a>'),
			new BindValue('default', 'a'),
		], $inst->getBinds());
		$this->assertSame('<List.first>', (string) $inst);
	}



	/**
	 * @param array<mixed> $items
	 * @dataProvider dataFirstApply
	 */
	#[DataProvider('dataFirstApply')]
	function testFirstApply(array $items, $default, $expected)
	{
		$inst = new ListFunc('first');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($items, 'List<a>'),
			new FinalValue($default, 'a'),
		])->unpack());
	}



	// ---- at ----------------------------------------------------------------

	function testAt()
	{
		$inst = new ListFunc('at');
		$this->assertSame('a', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'List<a>'),
			new BindValue('index', 'Int'),
			new BindValue('default', 'a'),
		], $inst->getBinds());
		$this->assertSame('<List.at>', (string) $inst);
	}



	/**
	 * @param array<mixed> $items
	 * @dataProvider dataAtApply
	 */
	#[DataProvider('dataAtApply')]
	function testAtApply(array $items, int $index, $default, $expected)
	{
		$inst = new ListFunc('at');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($items, 'List<a>'),
			new FinalValue($index, 'Int'),
			new FinalValue($default, 'a'),
		])->unpack());
	}



	// ---- exist -------------------------------------------------------------

	function testExist()
	{
		$inst = new ListFunc('exist');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'List<a>'),
			new BindValue('index', 'Int'),
		], $inst->getBinds());
		$this->assertSame('<List.exist>', (string) $inst);
	}



	/**
	 * @param array<mixed> $items
	 * @dataProvider dataExistApply
	 */
	#[DataProvider('dataExistApply')]
	function testExistApply(int $index, array $items, bool $expected)
	{
		$inst = new ListFunc('exist');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($items, 'List<a>'),
			new FinalValue($index, 'Int'),
		])->unpack());
	}



	// ---- push --------------------------------------------------------------

	function testPush()
	{
		$inst = new ListFunc('push');
		$this->assertSame('List<a>', $inst->type());
		$this->assertEquals([
			new BindValue('xs', 'List<a>'),
			new BindValue('x', 'a'),
		], $inst->getBinds());
		$this->assertSame('<List.push>', (string) $inst);
	}



	/**
	 * @param array<mixed> $xs
	 * @param array<mixed> $expected
	 * @dataProvider dataPushApply
	 */
	#[DataProvider('dataPushApply')]
	function testPushApply(array $xs, $x, array $expected)
	{
		$inst = new ListFunc('push');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($xs, 'List<a>'),
			new FinalValue($x, 'a'),
		])->unpack());
	}


	// @TODO test for List.map
	// @TODO test for List.fold
	// @TODO test for List.filter
	// @TODO test for List.split
	// @TODO test for List.indexOf
	// @TODO test for List.slice


	// ---- groupBy -----------------------------------------------------------

	function testGroupBy()
	{
		$inst = new ListFunc('groupBy');
		$this->assertSame('List<List<a>>', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'List<a>'),
			new BindValue('cb', '(a -> k)'),
		], $inst->getBinds());
		$this->assertSame('<List.groupBy>', (string) $inst);
	}



	/**
	 * The element itself serves as the key (identity lambda), so the test
	 * exercises how keys of the given type are compared.
	 *
	 * @param list<FinalValue> $xs
	 * @param array<mixed> $expected
	 * @dataProvider dataGroupByApply
	 */
	#[DataProvider('dataGroupByApply')]
	function testGroupByApply(array $xs, array $expected)
	{
		$inst = new ListFunc('groupBy');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($xs, 'List<a>'),
			ParametricValue::ShortLinkBind(new BindValue('x', '?')),
		])->unpack());
	}


	// ---- sort --------------------------------------------------------------

	function testSort()
	{
		$inst = new ListFunc('sort');
		$this->assertSame('List<a>', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'List<a>'),
			new BindValue('fn', '(a -> a -> Int)'),
		], $inst->getBinds());
		$this->assertSame('<List.sort>', (string) $inst);
	}



	/**
	 * @param array<mixed> $xs
	 * @param array<mixed> $expected
	 * @dataProvider dataSortApply
	 */
	#[DataProvider('dataSortApply')]
	function testSortApply(array $xs, string $direction, array $expected)
	{
		$inst = new ListFunc('sort');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($xs, 'List<a>'),
			new FinalValue($direction, 'a'),
		])->unpack());
	}



	// ---- data providers ----------------------------------------------------

	/**
	 * @return array<mixed>
	 */
	static function dataLenApply(): array
	{
		return [
			[[new FinalValue(1, 'Int'), new FinalValue(2, 'Int'), new FinalValue(3, 'Int')], 3],
			[[], 0],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataFirstApply(): array
	{
		return [
			[[new FinalValue(2, 'Int'), new FinalValue(4, 'Int')], 0, 2],
			[[], 42, 42],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataAtApply(): array
	{
		return [
			[[new FinalValue(2, 'Int'), new FinalValue(4, 'Int')], 1, 0, 4],
			[[new FinalValue(2, 'Int'), new FinalValue(4, 'Int')], 999, 42, 42],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataExistApply(): array
	{
		return [
			[1, [new FinalValue(2, 'Int'), new FinalValue(4, 'Int')], true],
			[999, [new FinalValue(2, 'Int'), new FinalValue(4, 'Int')], false],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataPushApply(): array
	{
		return [
			[[], 42, [42]],
			[[1, 2, 3], 4, [1, 2, 3, 4]],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataGroupByApply(): array
	{
		$int = static function(int $x): FinalValue {
			return new FinalValue($x, 'Int');
		};
		$str = static function(string $x): FinalValue {
			return new FinalValue($x, 'Str');
		};
		$rec = static function(array $x): FinalValue {
			return new FinalValue((object) array_map(static function($v): FinalValue {
				return new FinalValue($v, 'Int');
			}, $x), 'Dict');
		};
		$list = static function(array $x) use ($int): FinalValue {
			return new FinalValue(array_map($int, $x), 'List');
		};
		$sum = static function(string $variant, array $payload): FinalValue {
			return new FinalValue(new SumTypeValue('Shape', $variant, array_map(static function(int $x): FinalValue {
				return new FinalValue($x, 'Int');
			}, $payload)), 'Shape');
		};

		return [
			'empty' => [[], []],
			'groups ordered by first occurrence, items keep their order' => [
				[$int(5), $int(8), $int(5), $int(2), $int(2)],
				[[5, 5], [8], [2, 2]],
				],
			'single group' => [
				[$str('a'), $str('a')],
				[['a', 'a']],
				],
			'strict keys, 1 is not "1"' => [
				[$int(1), $str('1'), $int(1)],
				[[1, 1], ['1']],
				],
			'record keys are compared by content' => [
				[$rec(['id' => 1]), $rec(['id' => 2]), $rec(['id' => 1])],
				[[(object) ['id' => 1], (object) ['id' => 1]], [(object) ['id' => 2]]],
				],
			'record keys ignore the order of fields' => [
				[$rec(['a' => 1, 'b' => 2]), $rec(['b' => 2, 'a' => 1]), $rec(['a' => 1, 'b' => 3])],
				[[(object) ['a' => 1, 'b' => 2], (object) ['b' => 2, 'a' => 1]], [(object) ['a' => 1, 'b' => 3]]],
				],
			'list keys are compared by content' => [
				[$list([1, 2]), $list([3]), $list([1, 2]), $list([2, 1])],
				[[[1, 2], [1, 2]], [[3]], [[2, 1]]],
				],
			'sum type keys are compared by variant and payload' => [
				[$sum('Circle', [1]), $sum('Circle', [2]), $sum('Circle', [1]), $sum('Point', [])],
				[
					[new SumTypeValue('Shape', 'Circle', [new FinalValue(1, 'Int')]), new SumTypeValue('Shape', 'Circle', [new FinalValue(1, 'Int')])],
					[new SumTypeValue('Shape', 'Circle', [new FinalValue(2, 'Int')])],
					[new SumTypeValue('Shape', 'Point', [])],
				],
				],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataSortApply(): array
	{
		return [
			[[1, 2, 4, 3], 'List.Desc', [4, 3, 2, 1]],
			[[1, 2, 4, 3], 'List.Asc', [1, 2, 3, 4]],
		];
	}

}
