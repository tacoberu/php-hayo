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
	function testCompileWithErrors(string $code, array $args, string $exception, string $message): void
	{
		$this->expectException($exception);
		$this->expectExceptionMessage($message);
		HayoEngine::WithDefaultLibraries()
			->evaluate($code, $args);
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



	function testCacheNotWritable(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Location for file cache: '/proc' is not writeable.");
		HayoEngine::WithDefaultLibraries()
			->setCache(new FileBaseCache('/proc'))
			->evaluate('1 + 1', []);
	}

}
