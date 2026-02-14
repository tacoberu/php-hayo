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
			// @TODO
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



	private function compile($src)
	{
		return (new Compiler())->compile($src);
	}

}
