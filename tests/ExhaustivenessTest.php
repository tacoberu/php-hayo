<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class ExhaustivenessTest extends TestCase
{

	function testCompleteMatchAcceptedNoArgVariants(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Red
match c
case Color.Red   then "r"
case Color.Green then "g"
case Color.Blue  then "b"'
		);

		$this->assertSame('r', $result);
	}



	function testCompleteMatchAcceptedWithPayload(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Rectangle Real Real | Point
s = Shape.Rectangle 3.0 4.0
match s
case Shape.Circle r      then r * r
case Shape.Rectangle w h then w * h
case Shape.Point         then 0.0'
		);

		$this->assertSame(12.0, $result);
	}



	function testWildcardMakesMatchExhaustive(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Green
match c
case Color.Red then "red"
case _         then "other"'
		);

		$this->assertSame('other', $result);
	}



	function testBoolMatchExhaustive(): void
	{
		$result = $this->engine()->evaluate(
'b = True
match b
case True  then "yes"
case False then "no"'
		);

		$this->assertSame('yes', $result);
	}



	/**
	 * Negative: non-exhaustive matches are rejected at compile time
	 */
	function testNonExhaustiveColorMissingTwo(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Non-exhaustive match on type 'Color': missing variant(s) Green, Blue.");

		$this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Red
match c
case Color.Red then "red"'
		);
	}



	function testNonExhaustiveColorMissingOne(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("missing variant(s) Blue");

		$this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Red
match c
case Color.Red   then "red"
case Color.Green then "green"'
		);
	}



	function testNonExhaustiveShapeMissingPoint(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Non-exhaustive match on type 'Shape': missing variant(s) Point.");

		$this->engine()->evaluate(
'type Shape = Circle Real | Rectangle Real Real | Point
s = Shape.Circle 1.0
match s
case Shape.Circle r      then r
case Shape.Rectangle w h then w * h'
		);
	}



	function testNonExhaustiveBoolMissingFalse(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Non-exhaustive match on type 'Bool': missing variant(s) False.");

		$this->engine()->evaluate(
'b = True
match b
case True then "yes"'
		);
	}



	function testNonExhaustiveBoolMissingTrue(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("missing variant(s) True");

		$this->engine()->evaluate(
'b = False
match b
case False then "no"'
		);
	}



	/**
	 * External argument: type is inferred from match patterns (HM unification)
	 */
	function testNonExhaustiveOnExternalArgument(): void
	{
		// Subject `c` is an external argument with no a-priori type.
		// The first `Color.Red` pattern unifies `c`'s type with Color, so the
		// missing variants Green and Blue are detected at compile time.
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Non-exhaustive match on type 'Color': missing variant(s) Green, Blue.");

		$this->engine()->compile(
'type Color = Red | Green | Blue
match c
case Color.Red then "r"'
		);
	}



	function testCompleteMatchOnExternalArgument(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
match c
case Color.Red   then "r"
case Color.Green then "g"
case Color.Blue  then "b"',
			['c' => new SumTypeValue('Color', 'Green', [])]
		);

		$this->assertSame('g', $result);
	}



	/**
	 * Edge cases
	 */
	function testWildcardAfterIncompleteIsExhaustive(): void
	{
		// Wildcard at the end covers all uncovered variants
		$result = $this->engine()->evaluate(
'type Dir = North | South | East | West
d = Dir.West
match d
case Dir.North then "N"
case _         then "?"'
		);

		$this->assertSame('?', $result);
	}



	function testWildcardOnlyArmIsExhaustive(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Green
match c
case _ then "anything"'
		);

		$this->assertSame('anything', $result);
	}



	/**
	 * Match on a value of unknown sum type — no check performed (subject type
	 * is not a registered sum type, so we cannot verify completeness)
	 */
	function testMatchOnScalarSubjectNotChecked(): void
	{
		// Subject is an Int, not a sum type — exhaustiveness check skipped.
		// (Future: literal-pattern exhaustiveness is undecidable in general.)
		$result = $this->engine()->evaluate(
'n = 1
match n
case 1 then "one"
case 2 then "two"'
		);

		$this->assertSame('one', $result);
	}



	private function engine(): HayoEngine
	{
		return HayoEngine::WithDefaultLibraries();
	}

}
