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


class ComputeTest extends TestCase
{

	/**
	 * @param array<string, FinalVal> $args
	 */
	#[DataProvider('dataOperations')]
	#[DataProvider('dataStrings')]
	#[DataProvider('dataExpressions')]
	#[DataProvider('dataPredicators')]
	function testCompute(string $code, array $args, $expected)
	{
		$this->assertEquals($expected, $this->compile($code)->apply($args));
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataOperations(): array
	{
		return [
			['a'
				, [ 'a' => new FinalVal(42, 'Int')]
				, new FinalVal(42, 'Int'),
				],
			['40 + a'
				, [ 'a' => new FinalVal(2, 'Int')]
				, new FinalVal(42, 'Int'),
				],
			['40 + (a + a)'
				, [ 'a' => new FinalVal(45, 'Int')]
				, new FinalVal(130, 'Int'),
				],
			['40 + (1 + a)'
				, [ 'a' => new FinalVal(45, 'Int')]
				, new FinalVal(86, 'Int'),
				],
			['(10 + a) + (a + 1)'
				, [ 'a' => new FinalVal(8, 'Int')]
				, new FinalVal(27, 'Int'),
				],
			['(10 + a) or (a + 1)'
				, [ 'a' => new FinalVal(8, 'Int')]
				, new FinalVal(True, 'Symbol'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataStrings(): array
	{
		return [
			['strings.len a'
				, [ 'a' => new FinalVal("", 'Str')]
				, new FinalVal(0, 'Int'),
				],
			['strings.len a'
				, [ 'a' => new FinalVal("a", 'Str')]
				, new FinalVal(1, 'Int'),
				],
			['strings.len a'
				, [ 'a' => new FinalVal("Lorem ipsum doler O'hara.", 'Str')]
				, new FinalVal(25, 'Int'),
				],
			['strings.len a'
				, [ 'a' => new FinalVal("Lorem ipsum doler O'hařa.", 'Str')]
				, new FinalVal(25, 'Int'),
				],
			['strings.len a'
				, [ 'a' => new FinalVal("Latin-Ελληνικά-Русский-中文-😊 = Multilingvální test! 🌐✨", 'Str')]
				, new FinalVal(53, 'Int'),
				],

			['strings.split "" a'
				, [ 'a' => new FinalVal("", 'Str')]
				, new FinalVal([], 'List'),
				],
			['strings.split " " a'
				, [ 'a' => new FinalVal("", 'Str')]
				, new FinalVal([], 'List'),
				],
			['strings.split " " a'
				, [ 'a' => new FinalVal("Une deux trois", 'Str')]
				, new FinalVal([
					new FinalVal("Une", 'Str'),
					new FinalVal("deux", 'Str'),
					new FinalVal("trois", 'Str'),
					], 'List'),
				],

			['strings.concat sep ["Hello", "World"]',
				[ 'sep' => new FinalVal(" ", 'Str'),
					],
				new FinalVal("Hello World", 'Str'),
				],
			['strings.concat " " [a, b]',
				[ 'a' => new FinalVal("Hello", 'Str'),
					'b' => new FinalVal("World!", 'Str'),
					],
				new FinalVal("Hello World!", 'Str'),
				],
			['strings.concat "" [a, b]',
				[ 'a' => new FinalVal("Hello", 'Str'),
					'b' => new FinalVal("World!", 'Str'),
					],
				new FinalVal("HelloWorld!", 'Str'),
				],
			['strings.concat "" [a]',
				[ 'a' => new FinalVal("Hello", 'Str'),
					],
				new FinalVal("Hello", 'Str'),
				],
			['strings.concat sep []',
				[ 'sep' => new FinalVal("", 'Str'),
					],
				new FinalVal("", 'Str'),
				],
			['strings.concat " " [a, (strings.concat "" [ b, "!"])]',
				[ 'a' => new FinalVal("Hello", 'Str'),
					'b' => new FinalVal("World", 'Str'),
					],
				new FinalVal("Hello World!", 'Str'),
				],

			['list.len a'
				, [ 'a' => new FinalVal([], 'List<Int>')]
				, new FinalVal(0, 'Int'),
				],
			['list.len a'
				, [ 'a' => new FinalVal([1,2,3,], 'List<Int>')]
				, new FinalVal(3, 'Int'),
				],
			['list.first xs ""'
				, [ 'xs' => new FinalVal(["1","2","3",], 'List<Int>')]
				, new FinalVal("1", 'a'),
				],
			['list.first xs 0'
				, [ 'xs' => new FinalVal([1,2,3,], 'List<Int>')]
				, new FinalVal(1, 'a'),
				],

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataExpressions(): array
	{
		return [
			["b = 40 \na + b"
				, [ 'a' => new FinalVal(2, 'Int'),
					]
				, new FinalVal(42, 'Int'),
				],
			["b = 40 \nb + (a + a)"
				, [ 'a' => new FinalVal(45, 'Int'),
					]
				, new FinalVal(130, 'Int'),
				],
			["b = 40 \nb + ((b + 1) + a)"
				, [ 'a' => new FinalVal(45, 'Int'),
					]
				, new FinalVal(126, 'Int'),
				],
			["b = 40 \n(10 + a) + (a + b)"
				, [ 'a' => new FinalVal(8, 'Int'),
					]
				, new FinalVal(66, 'Int'),
				],
			["b = 40 \n"
			."(strings.len src) + b"
				, [ 'src' => new FinalVal("8", 'Str'),
					]
				, new FinalVal(41, 'Int'),
				],
			["b = a\n"
			."b"
				, [ 'a' => new FinalVal(41, 'Int'),
					]
				, new FinalVal(41, 'Int'),
				],
			["b = a\n"
			."b + b"
				, [ 'a' => new FinalVal(41, 'Int'),
					]
				, new FinalVal(82, 'Int'),
				],
/*			["b = c\n"		// @FIXME
			."c = a\n"
			."b + b"
				, [ 'a' => new FinalVal(41, 'Int'),
					]
				, new FinalVal(82, 'Int'),
				],
				*/

			["x = y \n"
			."(strings.len src) + x"
				, [ 'src' => new FinalVal("9", 'Str'),
					'y' => new FinalVal(8, "Int"),
					]
				, new FinalVal(9, 'Int'),
				],

			// Lambdy
			["inc = x ->\n"
			."	x + 1 \n"
			."inc x"
				, [ 'x' => new FinalVal(9, 'Int'),
					]
				, new FinalVal(10, 'Int'),
				],
			["inc = x ->\n"
			."	y = 2 \n"
			."	x + y \n"
			."inc x"
				, [ 'x' => new FinalVal(9, 'Int'),
					]
				, new FinalVal(11, 'Int'),
				],
			["inc = x ->\n"
			."	y = 2 \n"
			."	sqr = x ->\n"
			."		x * x \n"
			."	(sqr x) + (sqr y) \n"
			."inc x"
				, [ 'x' => new FinalVal(9, 'Int'),
					]
				, new FinalVal(85, 'Int'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataPredicators(): array
	{
		return [
			["a * 2"
				, [ 'a' => new FinalVal(41, 'Int'),
					]
				, new FinalVal(82, 'Int'),
				],
			["a || 2"
				, [ 'a' => new FinalVal(41, 'Int'),
					]
				, new FinalVal(True, 'Symbol'),
				],
			["a && 2"
				, [ 'a' => new FinalVal(41, 'Int'),
					]
				, new FinalVal(True, 'Symbol'),
				],
			["a && False"
				, [ 'a' => new FinalVal(41, 'Int'),
					]
				, new FinalVal(False, 'Symbol'),
				],
			["not a"
				, [ 'a' => new FinalVal(41, 'Int'),
					]
				, new FinalVal(False, 'Symbol'),
				],
			["not (not a)"
				, [ 'a' => new FinalVal(41, 'Int'),
					]
				, new FinalVal(True, 'Symbol'),
				],
			["not a"
				, [ 'a' => new FinalVal(False, 'Bool'),
					]
				, new FinalVal(True, 'Symbol'),
				],
			["not (1 or a)"
				, [ 'a' => new FinalVal(False, 'Bool'),
					]
				, new FinalVal(False, 'Symbol'),
				],
			["(not 1) or a"
				, [ 'a' => new FinalVal(True, 'Bool'),
					]
				, new FinalVal(True, 'Symbol'),
				],
		];
	}



	private function compile($src)
	{
		return (new Compiler([
			'predicate' => new PredicatesProvider(),
			'math' => new MathsProvider(),
			'str' => new StringsProvider(),
			'strings' => new StringsProvider(),
			'list' => new ListsProvider(),
			]))
			->compile($src);
	}

}
