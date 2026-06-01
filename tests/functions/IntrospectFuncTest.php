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


class IntrospectFuncTest extends TestCase
{

	/**
	 * @param array<mixed> $args
	 */
	#[DataProvider('dataOf')]
	function testOf(string $code, array $args, string $expected): void
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate($code, $args);

		$this->assertSame($expected, $result);
	}



	/**
	 * @param array<mixed> $args
	 */
	#[DataProvider('dataIs')]
	function testIs(string $code, array $args, bool $expected): void
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate($code, $args);

		$this->assertSame($expected, $result);
	}



	function testOfUsedInCondition(): void
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate(
				'if (Introspect.of src) == "Int" then "číslo" else "jiný typ"',
				['src' => 42]
			);

		$this->assertSame('číslo', $result);
	}



	function testIsUsedInCondition(): void
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate(
				'if Introspect.is src "Int" then "číslo" else "jiný typ"',
				['src' => 42]
			);

		$this->assertSame('číslo', $result);
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataOf(): array
	{
		return [
			['Introspect.of src', ['src' => 42], 'Int'],
			['Introspect.of src', ['src' => 3.14], 'Real'],
			['Introspect.of src', ['src' => 'hello'], 'Str'],
			['Introspect.of src', ['src' => true], 'Bool'],
			['Introspect.of src', ['src' => false], 'Bool'],
			['Introspect.of src', ['src' => null], 'Null'],
			['Introspect.of src', ['src' => [1, 2, 3]], 'List'],
			['Introspect.of src', ['src' => (object) ['a' => 1]], 'Dict'],
			['Introspect.of 42', [], 'Int'],
			['Introspect.of "hi"', [], 'Str'],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataIs(): array
	{
		return [
			['Introspect.is src "Int"', ['src' => 42], true],
			['Introspect.is src "Int"', ['src' => 'hello'], false],
			['Introspect.is src "Str"', ['src' => 'hello'], true],
			['Introspect.is src "Real"', ['src' => 3.14], true],
			['Introspect.is src "Bool"', ['src' => true], true],
			['Introspect.is src "Null"', ['src' => null], true],
			['Introspect.is src "List"', ['src' => [1, 2]], true],
			['Introspect.is src "Dict"', ['src' => (object) ['a' => 1]], true],
		];
	}

}
