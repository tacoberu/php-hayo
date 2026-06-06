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

	function testStringsProvider()
	{
		$this->assertEquals(new StringFunc('len'), (new StringsProvider())->lookupFunc('len'));
	}



	// ---- len ---------------------------------------------------------------

	function testLen()
	{
		$inst = new StringFunc('len');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.len>', (string) $inst);
	}



	/**
	 * @dataProvider dataLenApply
	 */
	#[DataProvider('dataLenApply')]
	function testLenApply(string $src, int $expected)
	{
		$inst = new StringFunc('len');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
		])->unpack());
	}



	// ---- split -------------------------------------------------------------

	function testSplit()
	{
		$inst = new StringFunc('split');
		$this->assertSame('List<Str>', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
			new BindValue('sep', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.split>', (string) $inst);
	}



	/**
	 * @param array<string> $expected
	 * @dataProvider dataSplitApply
	 */
	#[DataProvider('dataSplitApply')]
	function testSplitApply(string $src, string $sep, array $expected)
	{
		$inst = new StringFunc('split');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($src, 'Str'),
			new FinalValue($sep, 'Str'),
		])->unpack());
	}



	// ---- concat ------------------------------------------------------------

	function testConcat()
	{
		$inst = new StringFunc('concat');
		$this->assertSame('Str', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'List<Str>'),
			new BindValue('sep', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.concat>', (string) $inst);
	}



	/**
	 * @param array<string> $src
	 * @dataProvider dataConcatApply
	 */
	#[DataProvider('dataConcatApply')]
	function testConcatApply(string $sep, string $expected, array $src)
	{
		$inst = new StringFunc('concat');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($src, 'List<Str>'),
			new FinalValue($sep, 'Str'),
		])->unpack());
	}



	// ---- indexOf -----------------------------------------------------------

	function testIndexOf()
	{
		$inst = new StringFunc('indexOf');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
			new BindValue('fragment', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.indexOf>', (string) $inst);
	}



	/**
	 * @dataProvider dataIndexOfApply
	 */
	#[DataProvider('dataIndexOfApply')]
	function testIndexOfApply(string $src, string $fragment, int $expected)
	{
		$inst = new StringFunc('indexOf');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
			new FinalValue($fragment, 'Str'),
		])->unpack());
	}



	// ---- contains ----------------------------------------------------------

	function testContains()
	{
		$inst = new StringFunc('contains');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
			new BindValue('fragment', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.contains>', (string) $inst);
	}



	/**
	 * @dataProvider dataContainsApply
	 */
	#[DataProvider('dataContainsApply')]
	function testContainsApply(string $src, string $fragment, bool $expected)
	{
		$inst = new StringFunc('contains');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
			new FinalValue($fragment, 'Str'),
		])->unpack());
	}



	// ---- startsWith --------------------------------------------------------

	function testStartsWith()
	{
		$inst = new StringFunc('startsWith');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
			new BindValue('fragment', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.startsWith>', (string) $inst);
	}



	/**
	 * @dataProvider dataStartsWithApply
	 */
	#[DataProvider('dataStartsWithApply')]
	function testStartsWithApply(string $src, string $fragment, bool $expected)
	{
		$inst = new StringFunc('startsWith');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
			new FinalValue($fragment, 'Str'),
		])->unpack());
	}



	// ---- endsWith ----------------------------------------------------------

	function testEndsWith()
	{
		$inst = new StringFunc('endsWith');
		$this->assertSame('Bool', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
			new BindValue('fragment', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.endsWith>', (string) $inst);
	}



	/**
	 * @dataProvider dataEndsWithApply
	 */
	#[DataProvider('dataEndsWithApply')]
	function testEndsWithApply(string $src, string $fragment, bool $expected)
	{
		$inst = new StringFunc('endsWith');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
			new FinalValue($fragment, 'Str'),
		])->unpack());
	}



	// ---- sub ---------------------------------------------------------------

	function testSub()
	{
		$inst = new StringFunc('sub');
		$this->assertSame('Str', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
			new BindValue('start', 'Int'),
			new BindValue('len', 'Int'),
		], $inst->getBinds());
		$this->assertSame('<Str.sub>', (string) $inst);
	}



	/**
	 * @dataProvider dataSubApply
	 */
	#[DataProvider('dataSubApply')]
	function testSubApply(string $src, int $start, int $len, string $expected)
	{
		$inst = new StringFunc('sub');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
			new FinalValue($start, 'Int'),
			new FinalValue($len, 'Int'),
		])->unpack());
	}



	// ---- toLower -----------------------------------------------------------

	function testToLower()
	{
		$inst = new StringFunc('toLower');
		$this->assertSame('Str', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.toLower>', (string) $inst);
	}



	/**
	 * @dataProvider dataToLowerApply
	 */
	#[DataProvider('dataToLowerApply')]
	function testToLowerApply(string $src, string $expected)
	{
		$inst = new StringFunc('toLower');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
		])->unpack());
	}



	// ---- toUpper -----------------------------------------------------------

	function testToUpper()
	{
		$inst = new StringFunc('toUpper');
		$this->assertSame('Str', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.toUpper>', (string) $inst);
	}



	/**
	 * @dataProvider dataToUpperApply
	 */
	#[DataProvider('dataToUpperApply')]
	function testToUpperApply(string $src, string $expected)
	{
		$inst = new StringFunc('toUpper');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
		])->unpack());
	}



	// ---- format ------------------------------------------------------------

	function testFormat()
	{
		$inst = new StringFunc('format');
		$this->assertSame('Str', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
			new BindValue('args', 'Dict<Str>'),
		], $inst->getBinds());
		$this->assertSame('<Str.format>', (string) $inst);
	}



	/**
	 * @param array<string> $args
	 * @dataProvider dataFormatApply
	 */
	#[DataProvider('dataFormatApply')]
	function testFormatApply(string $src, array $args, string $expected)
	{
		$inst = new StringFunc('format');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
			new FinalValue($args, 'Dict<Str>'),
		])->unpack());
	}



	// ---- trim --------------------------------------------------------------

	function testTrim()
	{
		$inst = new StringFunc('trim');
		$this->assertSame('Str', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
		], $inst->getBinds());
		$this->assertSame('<Str.trim>', (string) $inst);
	}



	/**
	 * @dataProvider dataTrimApply
	 */
	#[DataProvider('dataTrimApply')]
	function testTrimApply(string $src, string $expected)
	{
		$inst = new StringFunc('trim');
		$this->assertSame($expected, $inst->apply([
			new FinalValue($src, 'Str'),
		])->unpack());
	}



	// ---- data providers ----------------------------------------------------

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
			["", " ", []],
			[" ", " ", ['', '']],
			["b", "a", ['b']],
			["b ", " ", ['b', '']],
			["ab cd", " ", ['ab', 'cd']],
			["1 2 3", " ", ['1', '2', '3']],
			["ab, cd, aloha", ",", ['ab', ' cd', ' aloha']],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataConcatApply(): array
	{
		return [
			[" ", "", []],
			[" ", "ab cd", ['ab', 'cd']],
			[" ", "1 2 3", ['1', '2', '3']],
			[",", "ab, cd, aloha", ['ab', ' cd', ' aloha']],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataIndexOfApply(): array
	{
		return [
			["", "", 0],
			[" ", "x", -1],
			["čača", "č", 0],
			["čača", "ač", 1],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataContainsApply(): array
	{
		return [
			["", "", True],
			[" ", "x", False],
			["čača", "č", True],
			["čača", "ač", True],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataStartsWithApply(): array
	{
		return [
			["abc", "a", True],
			["abc", "x", False],
			["", "", True],
			[" ", "x", False],
			["čača", "č", True],
			["čača", "ač", False],
			["čača", "ča", True],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataEndsWithApply(): array
	{
		return [
			["abc", "bc", True],
			["abc", "c", True],
			["abc", "a", False],
			["", "", True],
			[" ", "x", False],
			["čača", "a", True],
			["čača", "ač", False],
			["čača", "ča", True],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataSubApply(): array
	{
		return [
			["abcd", 1, 2, "bc"],
			["čača", 1, 2, "ač"],
			["čača", 1, 0, ""],
			["", 1, 0, ""],
			["", 0, 0, ""],
			["čača", 1, -1, "ač"],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataToLowerApply(): array
	{
		return [
			["abCd", "abcd"],
			["čAča", "čača"],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataToUpperApply(): array
	{
		return [
			["abCd", "ABCD"],
			["čAča", "ČAČA"],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataFormatApply(): array
	{
		return [
			['Lorem ${a} doler ist.', ['a' => 'ipsum'],
				"Lorem ipsum doler ist."],
			['Lorem ${a} ${b} ist ${a}.', ['a' => 'ipsum', 'b' => 'doler'],
				"Lorem ipsum doler ist ipsum."],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataTrimApply(): array
	{
		return [
			[' a ', 'a'],
			['  hello  ', 'hello'],
		];
	}

}
