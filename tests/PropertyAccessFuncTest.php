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
use InvalidArgumentException;


class PropertyAccessFuncTest extends TestCase
{

	function testBasics()
	{
		$inst = new PropertyAccessFunc('product');
		$this->assertSame('?', $inst->type());
		$this->assertEquals([new BindValue('src', '?')], $inst->getBinds());
		$this->assertSame('<.product>', (string) $inst);
	}



	/**
	 * @dataProvider dataApply
	 */
	#[DataProvider('dataApply')]
	function testApply(string $field, FinalValue $src, FinalValue $expected)
	{
		$inst = new PropertyAccessFunc($field);
		$this->assertEquals($expected, $inst->apply([$src]));
	}



	function testWrongArity()
	{
		$this->expectException(InvalidArgumentException::class);
		(new PropertyAccessFunc('a'))->apply([]);
	}



	/**
	 * @return array<mixed>
	 */
	static function dataApply(): array
	{
		return [
			'existing field' => [
				'product', new FinalValue((object) ['product' => 'apple'], 'Dict'),
				new FinalValue('apple', '?'),
				],
			'missing field' => [
				'nothing', new FinalValue((object) ['product' => 'apple'], 'Dict'),
				new FinalValue(Null, '?'),
				],
			'nested dict value' => [
				'foo', new FinalValue((object) ['foo' => (object) ['doo' => 41]], 'Dict'),
				new FinalValue((object) ['doo' => 41], '?'),
				],
			'base is not a record' => [
				'foo', new FinalValue(42, 'Int'),
				new FinalValue(Null, '?'),
				],
			'base is a list' => [
				'foo', new FinalValue([1, 2, 3], 'List'),
				new FinalValue(Null, '?'),
				],
			'field wrapped as FinalValue in the source object' => [
				'a', new FinalValue((object) ['a' => new FinalValue(5, 'Int')], 'Dict'),
				new FinalValue(5, '?'),
				],
		];
	}

}
