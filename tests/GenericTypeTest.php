<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class GenericTypeTest extends TestCase
{

	function testMaybeJustInt(): void
	{
		$result = $this->engine()->evaluate(
'type Maybe<a> = Just a | Nothing
Maybe.Just 42'
		);

		$this->assertInstanceOf(SumTypeValue::class, $result);
		$this->assertSame('Maybe', $result->getTypeName());
		$this->assertSame('Just', $result->getVariant());
		$this->assertSame(42, $result->getPayload()[0]->unpack());
	}



	function testMaybeJustStr(): void
	{
		$result = $this->engine()->evaluate(
'type Maybe<a> = Just a | Nothing
Maybe.Just "hello"'
		);

		$this->assertInstanceOf(SumTypeValue::class, $result);
		$this->assertSame('hello', $result->getPayload()[0]->unpack());
	}



	function testMaybeNothing(): void
	{
		$result = $this->engine()->evaluate(
'type Maybe<a> = Just a | Nothing
Maybe.Nothing'
		);

		$this->assertInstanceOf(SumTypeValue::class, $result);
		$this->assertSame('Nothing', $result->getVariant());
		$this->assertSame([], $result->getPayload());
	}



	function testMatchOnMaybe(): void
	{
		$result = $this->engine()->evaluate(
'type Maybe<a> = Just a | Nothing
m = Maybe.Just 99
match m
case Maybe.Just n  then n
case Maybe.Nothing then 0'
		);

		$this->assertSame(99, $result);
	}



	function testMatchOnMaybeNothing(): void
	{
		$result = $this->engine()->evaluate(
'type Maybe<a> = Just a | Nothing
m = Maybe.Nothing
match m
case Maybe.Just n  then n
case Maybe.Nothing then 0'
		);

		$this->assertSame(0, $result);
	}



	/**
	 * Two-parameter generic type — Result<a, b>
	 */
	function testResultOkInt(): void
	{
		$result = $this->engine()->evaluate(
'type Result<a, b> = Ok a | Err b
Result.Ok 42'
		);

		$this->assertSame('Result', $result->getTypeName());
		$this->assertSame('Ok', $result->getVariant());
	}



	function testResultErrStr(): void
	{
		$result = $this->engine()->evaluate(
'type Result<a, b> = Ok a | Err b
Result.Err "boom"'
		);

		$this->assertSame('Err', $result->getVariant());
		$this->assertSame('boom', $result->getPayload()[0]->unpack());
	}



	function testMatchOnResultOk(): void
	{
		$result = $this->engine()->evaluate(
'type Result<a, b> = Ok a | Err b
r = Result.Ok 7
match r
case Result.Ok  n   then n * 2
case Result.Err msg then 0'
		);

		$this->assertSame(14, $result);
	}



	function testMatchOnResultErr(): void
	{
		$result = $this->engine()->evaluate(
'type Result<a, b> = Ok a | Err b
r = Result.Err "fail"
match r
case Result.Ok  n   then "ok"
case Result.Err msg then msg'
		);

		$this->assertSame('fail', $result);
	}



	/**
	 * Exhaustiveness still works on generic types
	 */
	function testExhaustivenessOnGeneric(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Non-exhaustive match on type 'Maybe': missing variant(s) Nothing.");

		$this->engine()->evaluate(
'type Maybe<a> = Just a | Nothing
m = Maybe.Just 1
match m
case Maybe.Just n then n'
		);
	}



	function testExhaustivenessOnResult(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("missing variant(s) Err");

		$this->engine()->evaluate(
'type Result<a, b> = Ok a | Err b
r = Result.Ok 1
match r
case Result.Ok n then n'
		);
	}



	/**
	 * Polymorphism — same constructor used with different concrete types
	 */
	function testMaybeReusedWithDifferentTypes(): void
	{
		// Maybe.Just at integer site, Maybe.Just at string site — each gets
		// its own type instantiation thanks to polymorphism.
		$result = $this->engine()->evaluate(
'type Maybe<a> = Just a | Nothing
intM = Maybe.Just 42
strM = Maybe.Just "hello"
intVal =
	match intM
	case Maybe.Just n  then n
	case Maybe.Nothing then 0
strVal =
	match strM
	case Maybe.Just s  then s
	case Maybe.Nothing then ""
intVal'
		);

		$this->assertSame(42, $result);
	}



	/**
	 * Introspect still returns the type name (without parameters)
	 */
	function testIntrospectOfGenericType(): void
	{
		$result = $this->engine()->evaluate(
'type Maybe<a> = Just a | Nothing
m = Maybe.Just 1
Introspect.of m'
		);

		$this->assertSame('Maybe', $result);
	}



	function testIntrospectIsOnGenericType(): void
	{
		$result = $this->engine()->evaluate(
'type Maybe<a> = Just a | Nothing
m = Maybe.Just 1
Introspect.is m "Maybe"'
		);

		$this->assertTrue($result);
	}



	private function engine(): HayoEngine
	{
		return HayoEngine::WithDefaultLibraries();
	}

}
