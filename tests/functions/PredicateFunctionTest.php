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

	function testEquals()
	{
		$inst = new PredicateFunction('==');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindVal('a', '?'),
			new BindVal('b', '?'),
		], $inst->getBinds());
		$this->assertSame(['a', 'b'], $inst->refs());
	}



	#[DataProvider('dataEqualsApply')]
	function testEqualsApply($left, $right, bool $expected)
	{
		$inst = new PredicateFunction('==');
		$this->assertEquals($expected, $inst->apply([
			new FinalVal($left, '?'),
			new FinalVal($right, '?'),
		])->unpack());
	}



	function testIn()
	{
		$inst = new PredicateFunction('IN');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindVal('a', 'a'),
			new BindVal('b', 'List<a>'),
		], $inst->getBinds());
		$this->assertSame(['a', 'b'], $inst->refs());
	}



	/**
	 * @param mixed $left
	 * @param array<mixed> $right
	 */
	#[DataProvider('dataInApply')]
	function testInApply($left, array $right, bool $expected)
	{
		$inst = new PredicateFunction('IN');
		$this->assertEquals($expected, $inst->apply([
			new FinalVal($left, '?'),
			new FinalVal($right, 'List<a>'),
		])->unpack());
	}



	/**
	 * @return array<mixed>
	 */
	static function dataEqualsApply(): array
	{
		return [
			["", 0, False],
			[" ", 0, False],
			[" ", 1, False],
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
	static function dataInApply(): array
	{
		return [
			["", [], False],
			[" ", [], False],
			[" ", [], False],
			[" ", [], False],
			["a", ['b'], False],

			[" ", [" "], True],
			["a", ["a"], True],
			["a", ['b', "a"], True],
			["a", ["a", 'b'], True],

		];
	}

}
