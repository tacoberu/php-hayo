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


class DictFuncTest extends TestCase
{

	function testDictsProvider()
	{
		$this->assertEquals(new DictFunc('keys'), (new DictsProvider())->lookup('keys'));
	}



	// ---- keys --------------------------------------------------------------

	function testKeys()
	{
		$inst = new DictFunc('keys');
		$this->assertSame('List<Str>', $inst->type());
		$this->assertEquals([
			new BindValue('xs', 'Dict'),
		], $inst->getBinds());
		$this->assertSame('<Dict.keys>', (string) $inst);
	}



	/**
	 * @param array<mixed> $expected
	 */
	#[DataProvider('dataKeysApply')]
	function testKeysApply($src, array $expected)
	{
		$inst = new DictFunc('keys');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($src, 'Dict'),
		])->unpack());
	}



	// ---- values ------------------------------------------------------------

	function testValues()
	{
		$inst = new DictFunc('values');
		$this->assertSame('List<?>', $inst->type());
		$this->assertEquals([
			new BindValue('xs', 'Dict'),
		], $inst->getBinds());
		$this->assertSame('<Dict.values>', (string) $inst);
	}



	/**
	 * @param array<mixed> $expected
	 */
	#[DataProvider('dataValuesApply')]
	function testValuesApply($src, array $expected)
	{
		$inst = new DictFunc('values');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($src, 'Dict'),
		])->unpack());
	}



	// ---- has ---------------------------------------------------------------

	function testHas()
	{
		$inst = new DictFunc('has');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('xs', 'Dict'),
			new BindValue('key', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Dict.has>', (string) $inst);
	}



	#[DataProvider('dataHasApply')]
	function testHasApply($src, string $key, bool $expected)
	{
		$inst = new DictFunc('has');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Dict'),
			new FinalValue($key, 'Str'),
		])->unpack());
	}



	// ---- get ---------------------------------------------------------------

	function testGet()
	{
		$inst = new DictFunc('get');
		$this->assertSame('?', $inst->type());
		$this->assertEquals([
			new BindValue('xs', 'Dict'),
			new BindValue('key', 'Str'),
			new BindValue('default', '?'),
		], $inst->getBinds());
		$this->assertSame('<Dict.get>', (string) $inst);
	}



	#[DataProvider('dataGetApply')]
	function testGetApply($src, string $key, $default, $expected)
	{
		$inst = new DictFunc('get');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Dict'),
			new FinalValue($key, 'Str'),
			new FinalValue($default, '?'),
		])->unpack());
	}



	// ---- merge -------------------------------------------------------------

	function testMerge()
	{
		$inst = new DictFunc('merge');
		$this->assertSame('Dict', $inst->type());
		$this->assertEquals([
			new BindValue('base', 'Dict'),
			new BindValue('exts', 'Dict'),
		], $inst->getBinds());
		$this->assertSame('<Dict.merge>', (string) $inst);
	}



	#[DataProvider('dataMergeApply')]
	function testMergeApply($base, $exts, $expected)
	{
		$inst = new DictFunc('merge');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($base, 'Dict'),
			new FinalValue($exts, 'Dict'),
		])->unpack());
	}



	// ---- data providers ----------------------------------------------------

	/**
	 * @return array<mixed>
	 */
	static function dataKeysApply(): array
	{
		return [
			[(object) [], []],
			[(object) ['a' => 'foo', 'b' => 'bar'], ['a', 'b']],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataValuesApply(): array
	{
		return [
			[(object) [], []],
			[(object) ['a' => 'foo', 'b' => 'bar'], ['foo', 'bar']],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataHasApply(): array
	{
		return [
			[(object) ['a' => 'foo', 'b' => 'bar'], 'a',
				true],
			[(object) ['a' => 'foo', 'b' => 'bar'], 'z',
				false],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataGetApply(): array
	{
		return [
			[(object) ['a' => 'foo', 'b' => 'bar'], 'a', 'default',
				'foo'],
			[(object) ['a' => 'foo', 'b' => 'bar'], 'z', 'default',
				'default'],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataMergeApply(): array
	{
		return [
			[(object) ['a' => 1], (object) ['b' => 2],
				(object) ['a' => 1, 'b' => 2]],
			[(object) ['a' => 1, 'b' => 2], (object) ['b' => 99],
				(object) ['a' => 1, 'b' => 99]],
		];
	}

}
