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


class TypeTest extends TestCase
{

	// -------------------------------------------------------------------------
	// TVar

	function testTVarFreeVars(): void
	{
		$this->assertSame(['a'], (new TVar('a'))->freeVars());
	}



	function testTVarToString(): void
	{
		$this->assertSame('a', (string) new TVar('a'));
	}



	// -------------------------------------------------------------------------
	// TCon

	function testTConFreeVars(): void
	{
		$this->assertSame([], (new TCon('Int'))->freeVars());
	}



	/**
	 * @dataProvider dataTConToString
	 */
	#[DataProvider('dataTConToString')]
	function testTConToString(string $name): void
	{
		$this->assertSame($name, (string) new TCon($name));
	}



	// -------------------------------------------------------------------------
	// TApp

	function testTAppFreeVarsOneArg(): void
	{
		$t = new TApp('List', [new TVar('a')]);
		$this->assertSame(['a'], $t->freeVars());
	}



	function testTAppFreeVarsTwoArgs(): void
	{
		$t = new TApp('Result', [new TVar('a'), new TVar('b')]);
		$this->assertSame(['a', 'b'], $t->freeVars());
	}



	function testTAppFreeVarsDeduplicates(): void
	{
		$t = new TApp('Pair', [new TVar('a'), new TVar('a')]);
		$this->assertSame(['a'], $t->freeVars());
	}



	function testTAppFreeVarsNone(): void
	{
		$t = new TApp('List', [new TCon('Int')]);
		$this->assertSame([], $t->freeVars());
	}



	function testTAppToStringOneArg(): void
	{
		$t = new TApp('List', [new TVar('a')]);
		$this->assertSame('List<a>', (string) $t);
	}



	function testTAppToStringTwoArgs(): void
	{
		$t = new TApp('Result', [new TCon('Str'), new TVar('b')]);
		$this->assertSame('Result<Str, b>', (string) $t);
	}



	// -------------------------------------------------------------------------
	// TFun

	function testTFunFreeVars(): void
	{
		$t = new TFun(new TVar('a'), new TVar('b'));
		$this->assertSame(['a', 'b'], $t->freeVars());
	}



	function testTFunFreeVarsDeduplicates(): void
	{
		$t = new TFun(new TVar('a'), new TVar('a'));
		$this->assertSame(['a'], $t->freeVars());
	}



	function testTFunFreeVarsNested(): void
	{
		// (a -> b) -> c
		$t = new TFun(new TFun(new TVar('a'), new TVar('b')), new TVar('c'));
		$this->assertSame(['a', 'b', 'c'], $t->freeVars());
	}



	function testTFunToStringSimple(): void
	{
		$t = new TFun(new TCon('Int'), new TCon('Str'));
		$this->assertSame('Int -> Str', (string) $t);
	}



	function testTFunToStringRightAssoc(): void
	{
		// Int -> Str -> Bool  ==  Int -> (Str -> Bool)  — no parens needed on right
		$t = new TFun(new TCon('Int'), new TFun(new TCon('Str'), new TCon('Bool')));
		$this->assertSame('Int -> Str -> Bool', (string) $t);
	}



	function testTFunToStringLeftParens(): void
	{
		// (Int -> Str) -> Bool  — parens needed on left
		$t = new TFun(new TFun(new TCon('Int'), new TCon('Str')), new TCon('Bool'));
		$this->assertSame('(Int -> Str) -> Bool', (string) $t);
	}



	// -------------------------------------------------------------------------
	// TScheme

	function testTSchemeFreeVarsNoBound(): void
	{
		$s = new TScheme([], new TVar('a'));
		$this->assertSame(['a'], $s->freeVars());
	}



	function testTSchemeFreeVarsBoundRemoved(): void
	{
		// ∀a. a -> b  →  free: [b]
		$s = new TScheme(['a'], new TFun(new TVar('a'), new TVar('b')));
		$this->assertSame(['b'], $s->freeVars());
	}



	function testTSchemeToStringMono(): void
	{
		$s = TScheme::mono(new TCon('Int'));
		$this->assertSame('Int', (string) $s);
	}



	function testTSchemeToStringPoly(): void
	{
		$s = new TScheme(['a'], new TApp('List', [new TVar('a')]));
		$this->assertSame('∀a. List<a>', (string) $s);
	}



	function testTSchemeToStringMultipleVars(): void
	{
		$s = new TScheme(
			['a', 'b'],
			new TApp('Result', [new TVar('a'), new TVar('b')])
		);
		$this->assertSame('∀a b. Result<a, b>', (string) $s);
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataTConToString(): array
	{
		return [
			['Int'],
			['Str'],
			['Bool'],
			['Real'],
			['Null'],
			['DateTime'],
		];
	}

}
