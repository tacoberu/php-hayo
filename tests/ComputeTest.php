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
	 * @param array<string, FinalValue> $args
	 */
	#[DataProvider('dataOperations')]
	#[DataProvider('dataStrings')]
	#[DataProvider('dataExpressions')]
	#[DataProvider('dataLists')]
	#[DataProvider('dataPredicators')]
	#[DataProvider('dataPaths')]
	#[DataProvider('dataPipeOperator')]
	#[DataProvider('dataForms')]
	#[DataProvider('dataComplex')]
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
				, new FinalValue(42, 'Num'),
				],
			['40 + (a + a)'
				, [ 'a' => new FinalValue(45, 'Int')]
				, new FinalValue(130, 'Num'),
				],
			['40 + (1 + a)'
				, [ 'a' => new FinalValue(45, 'Int')]
				, new FinalValue(86, 'Num'),
				],
			['(10 + a) + (a + 1)'
				, [ 'a' => new FinalValue(8, 'Int')]
				, new FinalValue(27, 'Num'),
				],
			['(10 + a) or (a + 1)'
				, [ 'a' => new FinalValue(8, 'Int')]
				, new FinalValue(True, 'Bool'),
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
				, new FinalValue([], 'List<Str>'),
				],
			['Str.split a " "'
				, [ 'a' => new FinalValue("", 'Str')]
				, new FinalValue([], 'List<Str>'),
				],
			['Str.split a " "'
				, [ 'a' => new FinalValue("Une deux trois", 'Str')]
				, new FinalValue([
					new FinalValue("Une", 'Str'),
					new FinalValue("deux", 'Str'),
					new FinalValue("trois", 'Str'),
					], 'List<Str>'),
				],

			['Str.concat ["Hello", "World"] sep',
				[ 'sep' => new FinalValue(" ", 'Str'),
					],
				new FinalValue("Hello World", 'Str'),
				],
			['Str.concat [a, b] " "',
				[ 'a' => new FinalValue("Hello", 'Str'),
					'b' => new FinalValue("World!", 'Str'),
					],
				new FinalValue("Hello World!", 'Str'),
				],
			['Str.concat [a, b] ""',
				[ 'a' => new FinalValue("Hello", 'Str'),
					'b' => new FinalValue("World!", 'Str'),
					],
				new FinalValue("HelloWorld!", 'Str'),
				],
			['Str.concat [a] ""',
				[ 'a' => new FinalValue("Hello", 'Str'),
					],
				new FinalValue("Hello", 'Str'),
				],
			['Str.concat [] sep',
				[ 'sep' => new FinalValue("", 'Str'),
					],
				new FinalValue("", 'Str'),
				],
			['Str.concat [a, (Str.concat [ b, "!"] "")] " "',
				[ 'a' => new FinalValue("Hello", 'Str'),
					'b' => new FinalValue("World", 'Str'),
					],
				new FinalValue("Hello World!", 'Str'),
				],

			['List.len a'
				, [ 'a' => new FinalValue([], 'List<Int>')]
				, new FinalValue(0, 'Int'),
				],
			['List.len a'
				, [ 'a' => new FinalValue([1,2,3,], 'List<Int>')]
				, new FinalValue(3, 'Int'),
				],
			['List.first xs ""'
				, [ 'xs' => new FinalValue(["1","2","3",], 'List<Int>')]
				, new FinalValue("1", 'a'),
				],
			['List.first xs 0'
				, [ 'xs' => new FinalValue([1,2,3,], 'List<Int>')]
				, new FinalValue(1, 'a'),
				],
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
				, new FinalValue(42, 'Num'),
				],
			["b = 40 \nb + (a + a)"
				, [ 'a' => new FinalValue(45, 'Int'),
					]
				, new FinalValue(130, 'Num'),
				],
			["b = 40 \nb + ((b + 1) + a)"
				, [ 'a' => new FinalValue(45, 'Int'),
					]
				, new FinalValue(126, 'Num'),
				],
			["b = 40 \n(10 + a) + (a + b)"
				, [ 'a' => new FinalValue(8, 'Int'),
					]
				, new FinalValue(66, 'Num'),
				],
			["b = 40 \n"
			."(Str.len src) + b"
				, [ 'src' => new FinalValue("8", 'Str'),
					]
				, new FinalValue(41, 'Num'),
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
				, new FinalValue(82, 'Num'),
				],
			["b = c\n"
			."c = a\n"
			."b + b"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(82, 'Num'),
				],

			["x = y \n"
			."(Str.len src) + x"
				, [ 'src' => new FinalValue("9", 'Str'),
					'y' => new FinalValue(8, "Int"),
					]
				, new FinalValue(9, 'Num'),
				],

			// Lambdy
			["inc = x ->\n"
			."	x + 1 \n"
			."inc x"
				, [ 'x' => new FinalValue(9, 'Int'),
					]
				, new FinalValue(10, 'Num'),
				],
			["inc = x ->\n"
			."	y = 2 \n"
			."	x + y \n"
			."inc x"
				, [ 'x' => new FinalValue(9, 'Int'),
					]
				, new FinalValue(11, 'Num'),
				],
			["inc = x ->\n"
			."	y = 2 \n"
			."	sqr = x ->\n"
			."		x * x \n"
			."	(sqr x) + (sqr y) \n"
			."inc x"
				, [ 'x' => new FinalValue(9, 'Int'),
					]
				, new FinalValue(85, 'Num'),
				],
			// separator ;
			["inc = x -> y = 1; x + y \n"
			."inc x"
				, [ 'x' => new FinalValue(9, 'Int'),
					]
				, new FinalValue(10, 'Num'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataLists(): array
	{
		return [
			["a * 2",
				[ 'a' => new FinalValue(41, 'Int'),
					],
				new FinalValue(82, 'Num'),
				],

			// `List.slice`
			["List.slice xs 1 2",
				[ 'xs' => new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List'),
					],
				new FinalValue([
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					], 'List<a>'),
				],

			// List.map
			["List.map xs (x -> x * x)",
				[ 'xs' => new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List'),
					],
				new FinalValue([
					new FinalValue(1, 'Num'),
					new FinalValue(4, 'Num'),
					new FinalValue(9, 'Num'),
					new FinalValue(16, 'Num'),
					], 'List'),
				],

			// List.sort
			["List.sort xs (a b -> if a == 4 then -1 elif a == b then 0 elif a < b then -1 else 1)",
				[ 'xs' => new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List'),
					],
				new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List'),
				],
			["List.sort xs (a b -> if b == 4 then 1 elif a == b then 0 elif a < b then -1 else 1)",
				[ 'xs' => new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List'),
					],
				new FinalValue([
					new FinalValue(4, 'Int'),
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					], 'List'),
				],
			["List.sort xs (a b -> if b == 4 then 1 \nelif a == b then 0\nelif a < b then -1\nelse 1)",
				[ 'xs' => new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List'),
					],
				new FinalValue([
					new FinalValue(4, 'Int'),
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					], 'List'),
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
				, new FinalValue(82, 'Num'),
				],
			["a || 2"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(True, 'Bool'),
				],
			["a && 2"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(True, 'Bool'),
				],
			["a && False"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(False, 'Bool'),
				],
			["not a"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(False, 'Bool'),
				],
			["not (not a)"
				, [ 'a' => new FinalValue(41, 'Int'),
					]
				, new FinalValue(True, 'Bool'),
				],
			["not a"
				, [ 'a' => new FinalValue(False, 'Bool'),
					]
				, new FinalValue(True, 'Bool'),
				],
			["not (1 or a)"
				, [ 'a' => new FinalValue(False, 'Bool'),
					]
				, new FinalValue(False, 'Bool'),
				],
			["(not 1) or a"
				, [ 'a' => new FinalValue(True, 'Bool'),
					]
				, new FinalValue(True, 'Bool'),
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
				new FinalValue(42, 'Num'),
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
				new FinalValue(30, 'Num'),
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
				new FinalValue(30, 'Num'),
				],

			["src\n"
			."	|> Str.split \",\"\n"
			."",
				[ 'src' => new FinalValue("Majakovskeho 13, Karlovy Vary, Czech republic", 'Str')],
				new FinalValue([
					new FinalValue("Majakovskeho 13", 'Str'),
					new FinalValue(" Karlovy Vary", 'Str'),
					new FinalValue(" Czech republic", 'Str'),
					], 'List<Str>'),
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
			['List.sort xs ((a b) -> a + b)',
				CompileException::class,
				$msg],
			['List.sort xs ((a b c) -> a + b)',
				CompileException::class,
				$msg],
			['List.sort xs ((a) -> a + 1)',
				CompileException::class,
				$msg],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataComplex(): array
	{
		return [
			[trim("
xs = (Str.split src \",\")
-- xs = (Str.split \", \" \"Majakovskeho 13, Karlovy Vary, Czech republic\")
{
	street: (List.first xs \"\")
	city: (List.at xs 1 \"\") |> Str.trim
	country: (List.at xs 2 \"\") |> Str.trim
}

	"),
				[ 'src' => new FinalValue("Majakovskeho 13, Karlovy Vary, Czech republic", 'Str')],
				new FinalValue((object) [
					'street' => new FinalValue("Majakovskeho 13", 'a'),
					'city' => new FinalValue("Karlovy Vary", 'Str'),
					'country' => new FinalValue("Czech republic", 'Str'),
					], 'Dict'),
				],
		];
	}



	private function compile($src)
	{
		return (new Compiler([
			'predicate' => new PredicatesProvider(),
			'Math' => new MathsProvider(),
			'Str' => new StringsProvider(),
			'List' => new ListsProvider(),
			]))
			->compile($src);
	}

}
