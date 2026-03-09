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
use DateInterval;
use DivisionByZeroError;
use RuntimeException;


class HayoEngineTest extends TestCase
{

	#[DataProvider('dataCorrect')]
	function testCorrect(string $code, array $args, $expected)
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate($code, $args);
		$this->assertEquals($expected, $result);
	}



	/**
	 * @param class-string<\Throwable> $exception
	 */
	#[DataProvider('dataSymbolNotFoundErrors')]
	#[DataProvider('dataCompileErrors')]
	#[DataProvider('dataInvalidArgumentErrors')]
	#[DataProvider('dataValidationErrors')]
	function testCompileWithErrors(string $code, array $args, string $exception, string $message): void
	{
		$this->expectException($exception);
		$this->expectExceptionMessage($message);
		HayoEngine::WithDefaultLibraries()
			->evaluate($code, $args);
	}



	/**
	 * @param class-string<\Throwable> $exception
	 */
	#[DataProvider('dataRuntimeErrors')]
	function testRuntimeErrors(string $code, array $args, string $exception, string $message): void
	{
		$this->expectException($exception);
		$this->expectExceptionMessage($message);
		HayoEngine::WithDefaultLibraries()
			->evaluate($code, $args);
	}



	function testCacheNotWritable(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Location for file cache: '/proc' is not writeable.");
		HayoEngine::WithDefaultLibraries()
			->setCache(new FileBaseCache('/proc'))
			->evaluate('1 + 1', []);
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataCorrect(): array
	{
		return [
			["1 + 1", [],
				2,
				],
			['"""Sinead O\'Connor"""', [],
				"Sinead O'Connor",
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataSymbolNotFoundErrors(): array
	{
		return [
			// Unknown symbol → LogicException from Compiler
			['List.noth (a b -> a + b) xs', ['xs' => []],
				SymbolNotFound::class,
				'Unable to find symbols: List.noth.'],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataCompileErrors(): array
	{
		return [
			// Comment-only source → decoder returns null → LogicException from Compiler::compile() line 63
			['-- comment only', [],
				CompileException::class,
				'Invalid source code.'],
			// Double operator → global \LogicException from PrattParser (vendor)
			['1 * + 2', [],
				CompileException::class,
				'Očekáván operand, dostal jsem operátor:'],
			// Unrecognised character → global \Exception from Lexer (vendor)
			["foo \x01 bar", [],
				CompileException::class,
				"Couldn't tokenise:"],
			// Lambda whose body is an undefined symbol, applied with all args resolved
			["f = x -> z\nf 1", ['z' => 1],
				CompileException::class,
				'Unresolved expression \'(x) -> z 1 : Int\''],
			// Lambda with a non-string argument (list pattern) → CompileException from partialEvaluateLambda
			["f = [a b] -> a + b\nf 5", [],
				CompileException::class,
				'Lambda arguments must be simple names, not expressions.'],
			// Incomplete if-then-else → HayoParserException from Parser (vendor)
			['if a > 0 then', [],
				CompileException::class,
				'Required closing bracked: EOF.'],
			// Lambda with parenthesised arg → global \LogicException from Parser (vendor)
			['((a b) -> a + b)', [],
				CompileException::class,
				'Lambda arguments must be simple names, not expressions.'],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataInvalidArgumentErrors(): array
	{
		return [
			// Too few positional arguments → Taco\Hayo\InvalidArgumentException from HayoEngine::combineBindWithValues
			['List.first xs', [],
				InvalidArgumentException::class,
				'Too few arguments to function CallableValue: <List.first> <?xs> :: ? [xs], 0 passed and exactly 1 expected.'],
			// Too many positional arguments → Taco\Hayo\InvalidArgumentException from HayoEngine::combineBindWithValues
			['1 + a', [10, 99],
				InvalidArgumentException::class,
				'Too few arguments to function CallableValue: 1 <Math.+> <?a> :: ? [a], 2 passed and exactly 1 expected.'],
			// Unsupported PHP argument type → Taco\Hayo\InvalidArgumentException from HayoEngine::gauseType
			['a', [new DateInterval('P1D')],
				InvalidArgumentException::class,
				"Invalid type of value: 'DateInterval Object\n"],
			// Wrong named argument → global \InvalidArgumentException from ParametricValue::assertBindArguments
			['1 + a', ['b' => 10],
				InvalidArgumentException::class,
				"Invalid arguments. Expected 'a'; given 'b'."],
			// Extra named argument → global \InvalidArgumentException from ParametricValue::assertBindArguments
			['1 + a', ['a' => 10, 'b' => 20],
				InvalidArgumentException::class,
				"Invalid count of arguments. Expected 'a'; given 'a', 'b'."],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataValidationErrors(): array
	{
		return [

			// Math operator on wrong types → TypeError from PHP runtime
			['a + b', ['a' => 'hello', 'b' => 2],
				ValidationException::class,
				'Invalid arguments of Math.+: Expected int, got string'],

			// IN predicate on non-array → TypeError from PHP built-in in_array()
			['1 IN xs', ['xs' => 'hello'],
				ValidationException::class,
				'Invalid arguments of Predicate.in: Expected array, got string'],

			// List.len on non-array → TypeError from PHP built-in count()
			['List.len xs', ['xs' => 'hello'],
				ValidationException::class,
				'Invalid arguments of List.len: Expected array, got string'],
			// List.map on non-array → TypeError from PHP built-in array_map()
			['List.map xs (x -> x + 1)', ['xs' => 42],
				ValidationException::class,
				'Invalid arguments of List.map: Expected array, got integer'],

			// Wrong argument type for builtin → global \InvalidArgumentException from StringsProvider::assertStr
			['Str.len xs', ['xs' => 42],
				ValidationException::class,
				'Expected string, got integer'],

			// DateTime.fromDate with non-int year → TypeError from DateTime::setDate()
			['DateTime.fromDate y m d', ['y' => 'not-a-year', 'm' => 1, 'd' => 1],
				ValidationException::class,
				'Invalid arguments of DateTime.fromDate: Expected int, got string'],
			// DateTime.fromTimestamp with invalid string → Exception from DateTime constructor
			['DateTime.fromTimestamp src', ['src' => 'abc'],
				ValidationException::class,
				'Invalid arguments of DateTime.fromTimestamp: Expected int, got string'],

		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataRuntimeErrors(): array
	{
		return [
			// Modulo by zero → DivisionByZeroError from PHP runtime
			['10 mod a', ['a' => 0],
				DivisionByZeroError::class,
				'Modulo by zero'],
			// Integer division by zero → DivisionByZeroError from PHP runtime
			['10 div a', ['a' => 0],
				DivisionByZeroError::class,
				'Division by zero'],
		];
	}

}
