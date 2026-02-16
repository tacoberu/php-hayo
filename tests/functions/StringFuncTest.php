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


class StringFuncTest extends TestCase
{

	function testLen()
	{
		$inst = new StringFunc('strings.len');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindVal('src', 'Str'),
		], $inst->getBinds());
		$this->assertSame(['src'], $inst->refs());
	}



	#[DataProvider('dataLenApply')]
	function testLenApply(string $src, int $expected)
	{
		$inst = new StringFunc('strings.len');
		$this->assertEquals($expected, $inst->apply([
			new FinalVal($src, 'Str'),
		])->unpack());
	}



	function testSplit()
	{
		$inst = new StringFunc('strings.split');
		$this->assertSame('List<Str>', $inst->type());
		$this->assertEquals([
			new BindVal('sep', 'Str'),
			new BindVal('src', 'Str'),
		], $inst->getBinds());
		$this->assertSame(['sep', 'src'], $inst->refs());
	}



	/**
	 * @param array<string> $expected
	 */
	#[DataProvider('dataSplitApply')]
	function testSplitApply(string $sep, string $src, array $expected)
	{
		$inst = new StringFunc('strings.split');
		$this->assertEquals($expected, $inst->apply([
			new FinalVal($sep, 'Str'),
			new FinalVal($src, 'Str'),
		])->unpack());
	}



	function testConcat()
	{
		$inst = new StringFunc('strings.concat');
		$this->assertSame('Str', $inst->type());
		$this->assertEquals([
			new BindVal('sep', 'Str'),
			new BindVal('src', 'List<Str>'),
		], $inst->getBinds());
		$this->assertSame(['sep', 'src'], $inst->refs());
	}



	/**
	 * @param array<string> $src
	 */
	#[DataProvider('dataConcatApply')]
	function testConcatApply(string $sep, string $expected, array $src)
	{
		$inst = new StringFunc('strings.concat');
		$this->assertEquals($expected, $inst->apply([
			new FinalVal($sep, 'Str'),
			new FinalVal($src, 'Str'),
		])->unpack());
	}



	/**
	 * @return array<mixed>
	 */
	static function dataLenApply(): array
	{
		return [
			["", 0],
			[" ", 1],
			["x", 1],
			["čača", 4],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataSplitApply(): array
	{
		return [
			[" ", "",
				[],
				],
			[" ", " ",
				['', ''],
				],
			["a", "b",
				['b'],
				],
			[" ", "b ",
				['b', ''],
				],
			[" ", "ab cd",
				['ab', 'cd'],
				],
			[" ", "1 2 3",
				['1', '2', '3'],
				],
			/*["", "123", @TODO not supported
				[]
				],
				*/
			[",", "ab, cd, aloha",
				['ab', ' cd', ' aloha'],
				],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataConcatApply(): array
	{
		return [
			[" ", "",
				[],
				],
			[" ", "",
				[],
				],
			[" ", "ab cd",
				['ab', 'cd'],
				],
			[" ", "1 2 3",
				['1', '2', '3'],
				],
			/*["", "123", @TODO not supported
				[]
				],
				*/
			[",", "ab, cd, aloha",
				['ab', ' cd', ' aloha'],
				],
		];
	}

}
