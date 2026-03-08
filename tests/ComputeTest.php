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
use LogicException;


class ComputeTest extends TestCase
{

	/**
	 * @param array<string, FinalValue> $args
	 */
	#[DataProvider('dataOperations')]
	#[DataProvider('dataStrings')]
	#[DataProvider('dataExpressions')]
	#[DataProvider('dataPredicators')]
	#[DataProvider('dataPaths')]
	#[DataProvider('dataPipeOperator')]
	#[DataProvider('dataForms')]
	function testCompute(string $code, array $args, $expected)
	{
		$this->assertEquals($expected, $this->compile($code)->apply($args));
	}



	/**
	 * @param class-string<\Throwable> $exception
	 */
	#[DataProvider('dataErrors')]
	function testComputeErrors(string $code, string $exception, string $message): void
	{
		$this->expectException($exception);
		$this->expectExceptionMessage($message);
		$this->compile($code);
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataOperations(): array
	{
		return [
			['a'
				, [ 'a' => new FinalValue(42, 'Int')]
				, new FinalValue(42, 'Int'),
				],
			['40 + a'
				, [ 'a' => new FinalValue(2, 'Int')]
				, new FinalValue(42, 'Int'),
				],
			['40 + (a + a)'
				, [ 'a' => new FinalValue(45, 'Int')]
				, new FinalValue(130, 'Int'),
				],
			['40 + (1 + a)'
				, [ 'a' => new FinalValue(45, 'Int')]
				, new FinalValue(86, 'Int'),
				],
			['(10 + a) + (a + 1)'
				, [ 'a' => new FinalValue(8, 'Int')]
				, new FinalValue(27, 'Int'),
				],
			['(10 + a) or (a + 1)'
				, [ 'a' => new FinalValue(8, 'Int')]
				, new FinalValue(True, 'Symbol'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataStrings(): array
	{
		return [
			['Str.len a'
				, [ 'a' => new FinalValue("", 'Str')]
				, new FinalValue(0, 'Int'),
				],
			['Str.len a'
				, [ 'a' => new FinalValue("a", 'Str')]
				, new FinalValue(1, 'Int'),
				],
			['Str.len a'
				, [ 'a' => new FinalValue("Lorem ipsum doler O'hara.", 'Str')]
				, new FinalValue(25, 'Int'),
				],
			['Str.len a'
				, [ 'a' => new FinalValue("Lorem ipsum doler O'hařa.", 'Str')]
				, new FinalValue(25, 'Int'),
				],
			['Str.len a'
				, [ 'a' => new FinalValue("Latin-Ελληνικά-Русский-中文-😊 = Multilingvální test! 🌐✨", 'Str')]
				, new FinalValue(53, 'Int'),
				],

			['Str.split a ""'
				, [ 'a' => new FinalValue("", 'Str')]
				, new FinalValue([], 'List'),
				],
			['Str.split a " "'
				, [ 'a' => new FinalValue("", 'Str')]
				, new FinalValue([], 'List'),
				],
			['Str.split a " "'
				, [ 'a' => new FinalValue("Une deux trois", 'Str')]
				, new FinalValue([
					new FinalValue("Une", 'Str'),
					new FinalValue("deux", 'Str'),
					new FinalValue("trois", 'Str'),
					], 'List'),
				],

			['Str.concat sep ["Hello", "World"]',
				[ 'sep' => new FinalValue(" ", 'Str'),
					],
				new FinalValue("Hello World", 'Str'),
				],
			['Str.concat " " [a, b]',
				[ 'a' => new FinalValue("Hello", 'Str'),
					'b' => new FinalValue("World!", 'Str'),
					],
				new FinalValue("Hello World!", 'Str'),
				],
			['Str.concat "" [a, b]',
				[ 'a' => new FinalValue("Hello", 'Str'),
					'b' => new FinalValue("World!", 'Str'),
					],
				new FinalValue("HelloWorld!", 'Str'),
				],
			['Str.concat "" [a]',
				[ 'a' => new FinalValue("Hello", 'Str'),
					],
				new FinalValue("Hello", 'Str'),
				],
			['Str.concat sep []',
				[ 'sep' => new FinalValue("", 'Str'),
					],
				new FinalValue("", 'Str'),
				],
			['Str.concat " " [a, (Str.concat "" [ b, "!"])]',
				[ 'a' => new FinalValue("Hello", 'Str'),
					'b' => new FinalValue("World", 'Str'),
					],
				new FinalValue("Hello World!", 'Str'),
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
				, [ 'a' => new FinalValue(2, 'Int'),
					]
				, new FinalValue(42, 'Int'),
				],
			["b = 40 \nb + (a + a)"
				, [ 'a' => new FinalValue(45, 'Int'),
					]
				, new FinalValue(130, 'Int'),
				],
			["b = 40 \nb + ((b + 1) + a)"
				, [ 'a' => new FinalValue(45, 'Int'),
					]
				, new FinalValue(126, 'Int'),
				],
			["b = 40 \n(10 + a) + (a + b)"
				, [ 'a' => new FinalValue(8, 'Int'),
					]
				, new FinalValue(66, 'Int'),
				],
			["b = 40 \n"
			."(Str.len src) + b"
				, [ 'src' => new FinalValue("8", 'Str'),
					]
				, new FinalValue(41, 'Int'),
				],
			["b = a\n"
			."b"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(41, 'Int'),
				],
			["b = a\n"
			."b + b"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(82, 'Int'),
				],
			["b = c\n"
			."c = a\n"
			."b + b"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(82, 'Int'),
				],

			["x = y \n"
			."(Str.len src) + x"
				, [ 'src' => new FinalValue("9", 'Str'),
					'y' => new FinalValue(8, "Int"),
					]
				, new FinalValue(9, 'Int'),
				],

			// Lambdy
			["inc = x ->\n"
			."	x + 1 \n"
			."inc x"
				, [ 'x' => new FinalValue(9, 'Int'),
					]
				, new FinalValue(10, 'Int'),
				],
			["inc = x ->\n"
			."	y = 2 \n"
			."	x + y \n"
			."inc x"
				, [ 'x' => new FinalValue(9, 'Int'),
					]
				, new FinalValue(11, 'Int'),
				],
			["inc = x ->\n"
			."	y = 2 \n"
			."	sqr = x ->\n"
			."		x * x \n"
			."	(sqr x) + (sqr y) \n"
			."inc x"
				, [ 'x' => new FinalValue(9, 'Int'),
					]
				, new FinalValue(85, 'Int'),
				],
			// separator ;
			["inc = x -> y = 1; x + y \n"
			."inc x"
				, [ 'x' => new FinalValue(9, 'Int'),
					]
				, new FinalValue(10, 'Int'),
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
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(82, 'Int'),
				],
			["a || 2"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(True, 'Symbol'),
				],
			["a && 2"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(True, 'Symbol'),
				],
			["a && False"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(False, 'Symbol'),
				],
			["not a"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(False, 'Symbol'),
				],
			["not (not a)"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(True, 'Symbol'),
				],
			["not a"
				, [ 'a' => new FinalValue(False, 'Bool'),
					]
				, new FinalValue(True, 'Symbol'),
				],
			["not (1 or a)"
				, [ 'a' => new FinalValue(False, 'Bool'),
					]
				, new FinalValue(False, 'Symbol'),
				],
			["(not 1) or a"
				, [ 'a' => new FinalValue(True, 'Bool'),
					]
				, new FinalValue(True, 'Symbol'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataPaths(): array
	{
		return [
			["1 + x.foo.doo",
				[ 'x' => new FinalValue((object) [
					'foo' => (object) [
						'doo' => 41,
						],
					], 'Dict'),
					],
				new FinalValue(42, 'Int'),
				],
			["Str.len x.foo.doo",
				[ 'x' => new FinalValue((object) [
					'foo' => (object) [
						'doo' => "Lorem ipsum doler ist",
						],
					], 'Dict'),
					],
				new FinalValue(21, 'Int'),
				],
			["x.foo.doo",
				[ 'x' => new FinalValue((object) [
					'foo' => (object) [
						'doo' => "Lorem ipsum doler ist",
						],
					], 'Dict'),
					],
				new FinalValue('Lorem ipsum doler ist', '?'),
				],
			["x.foo.nothing",
				[ 'x' => new FinalValue((object) [
					'foo' => (object) [
						'doo' => "Lorem ipsum doler ist",
						],
					], 'Dict'),
					],
				new FinalValue(Null, '?'),
				],
			["x.nothing",
				[ 'x' => new FinalValue((object) [
					'foo' => (object) [
						'doo' => "Lorem ipsum doler ist",
						],
					], 'Dict'),
					],
				new FinalValue(Null, '?'),
				],
			["x.pravda",
				[ 'x' => new FinalValue((object) [
					'foo' => (object) [
						'doo' => "Lorem ipsum doler ist",
						],
					'pravda' => True,
					], 'Dict'),
					],
				new FinalValue(true, '?'),
				],
			["x.nepravda",
				[ 'x' => new FinalValue((object) [
					'foo' => (object) [
						'doo' => "Lorem ipsum doler ist",
						],
					'nepravda' => False,
					], 'Dict'),
					],
				new FinalValue(False, '?'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataForms(): array
	{
		$code = '
if (List.len xs) < 2 then "A"
elif (List.len xs) < 4 then "B"
elif (List.len xs) < 8 then "C"
else "D"
';
		return [
			[$code,
				[ 'xs' => new FinalValue([], 'List'),
					],
				new FinalValue('A', 'Str'),
				],
			[$code,
				[ 'xs' => new FinalValue([
						new FinalValue(1, 'Int'),
						new FinalValue(2, 'Int'),
						], 'List'),
					],
				new FinalValue('B', 'Str'),
				],
			[$code,
				[ 'xs' => new FinalValue([
						new FinalValue(1, 'Int'),
						new FinalValue(2, 'Int'),
						new FinalValue(3, 'Int'),
						new FinalValue(4, 'Int'),
						], 'List'),
					],
				new FinalValue('C', 'Str'),
				],
			[$code,
				[ 'xs' => new FinalValue([
						new FinalValue(1, 'Int'),
						new FinalValue(2, 'Int'),
						new FinalValue(3, 'Int'),
						new FinalValue(4, 'Int'),
						new FinalValue(5, 'Int'),
						new FinalValue(6, 'Int'),
						new FinalValue(7, 'Int'),
						new FinalValue(8, 'Int'),
						], 'List'),
					],
				new FinalValue('D', 'Str'),
				],
			[$code,
				[ 'xs' => new FinalValue([
						new FinalValue(1, 'Int'),
						new FinalValue(2, 'Int'),
						new FinalValue(3, 'Int'),
						new FinalValue(4, 'Int'),
						new FinalValue(5, 'Int'),
						new FinalValue(6, 'Int'),
						new FinalValue(7, 'Int'),
						new FinalValue(8, 'Int'),
						new FinalValue(9, 'Int'),
						new FinalValue(10, 'Int'),
						], 'List'),
					],
				new FinalValue('D', 'Str'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataPipeOperator(): array
	{
		return [
			["List.fold (List.map xs (x -> x * x)) 0 (prev curr -> prev + curr)",
				[ 'xs' => new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List'),
					],
				new FinalValue(30, 'Int'),
				],

			["xs\n"
			."	|> List.map (x -> x * x)\n"
			."	|> List.fold 0 (prev curr -> prev + curr)",
				[ 'xs' => new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List<Int>')],
				new FinalValue(30, 'Int'),
				],

			["src\n"
			."	|> Str.split \",\"\n"
			."",
				[ 'src' => new FinalValue("Majakovskeho 13, Karlovy Vary, Czech republic", 'Str')],
				new FinalValue([
					new FinalValue("Majakovskeho 13", 'Str'),
					new FinalValue(" Karlovy Vary", 'Str'),
					new FinalValue(" Czech republic", 'Str'),
					], 'List'),
				],

			["src\n" // @TODO
			."	|> Str.split \",\"\n"
			."	|> List.map (x -> Str.trim x)\n"
			."",
				[ 'src' => new FinalValue("Majakovskeho 13, Karlovy Vary, Czech republic", 'Str')],
				new FinalValue([
					new FinalValue("Majakovskeho 13", 'Str'),
					new FinalValue("Karlovy Vary", 'Str'),
					new FinalValue("Czech republic", 'Str'),
					], 'List'),
				],

		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataErrors(): array
	{
		$msg = 'Lambda arguments must be simple names, not expressions. Use `(a b -> ...)` instead of `((a b) -> ...)` or `((a) -> ...)`.';
		return [
			['List.sort ((a b) -> a + b) xs',
				LogicException::class,
				$msg],
			['List.sort ((a b c) -> a + b) xs',
				LogicException::class,
				$msg],
			['List.sort ((a) -> a + 1) xs',
				LogicException::class,
				$msg],
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
