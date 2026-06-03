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
	static function dataSortApply(): array
	{
		return [
			[[1, 2, 4, 3], 'List.Desc', [4, 3, 2, 1]],
			[[1, 2, 4, 3], 'List.Asc', [1, 2, 3, 4]],
		];
	}

}
