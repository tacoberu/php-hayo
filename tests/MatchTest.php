<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class MatchTest extends TestCase
{

	/**
	 * Základní syntax match
	 */
	function testMatchAsScriptResult(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Green
match c
case Color.Red   then "red"
case Color.Green then "green"
case Color.Blue  then "blue"'
		);

		$this->assertSame('green', $result);
	}



	function testMatchInLetBinding(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Blue
result =
	match c
	case Color.Red   then 1
	case Color.Green then 2
	case Color.Blue  then 3
result'
		);

		$this->assertSame(3, $result);
	}



	function testMatchResultUsedInArithmetic(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Red
x =
	match c
	case Color.Red   then 10
	case Color.Green then 20
	case Color.Blue  then 30
x * 2'
		);

		$this->assertSame(20, $result);
	}


	// -------------------------------------------------------------------------
	/**
	 * Payload binding
	 */
	function testMatchBindsSingleField(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Point
Shape.Circle 5.0
|> (s ->
	match s
	case Shape.Circle r then r * r
	case Shape.Point    then 0.0)'
		);

		$this->assertSame(25.0, $result);
	}



	function testMatchBindsTwoFields(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Rectangle Real Real | Point
s = Shape.Rectangle 3.0 4.0
match s
case Shape.Rectangle w h then w * h
case Shape.Point         then 0.0'
		);

		$this->assertSame(12.0, $result);
	}



	function testMatchBindingUsedInComputation(): void
	{
		$result = $this->engine()->evaluate(
'type Expr = Add Int Int | Neg Int | Lit Int
e = Expr.Add 3 4
match e
case Expr.Add a b then a + b
case Expr.Neg n   then 0 - n
case Expr.Lit v   then v'
		);

		$this->assertSame(7, $result);
	}



	/**
	 * Wildcard
	 */
	function testWildcardMatchesUnhandledVariants(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Blue
match c
case Color.Red then "red"
else "other"'
		);

		$this->assertSame('other', $result);
	}



	function testWildcardAsOnlyArm(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Green
match c
else "any"'
		);

		$this->assertSame('any', $result);
	}



	/**
	 * Předání hodnoty jako argument
	 */
	function testMatchOnExternalArgument(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Rectangle Real Real | Point
match s
case Shape.Circle r      then r * r * 3.0
case Shape.Rectangle w h then w * h
case Shape.Point         then 0.0',
			['s' => new SumTypeValue('Shape', 'Circle', [new FinalValue(2.0, 'Real')])]
		);

		$this->assertSame(12.0, $result);
	}



	function testMatchAllVariantsViaExternalArg(): void
	{
		$script = 'type Dir = North | South | East | West
match d
case Dir.North then "N"
case Dir.South then "S"
case Dir.East  then "E"
case Dir.West  then "W"';

		$engine = $this->engine();
		$this->assertSame('N', $engine->evaluate($script, ['d' => new SumTypeValue('Dir', 'North', [])]));
		$this->assertSame('S', $engine->evaluate($script, ['d' => new SumTypeValue('Dir', 'South', [])]));
		$this->assertSame('E', $engine->evaluate($script, ['d' => new SumTypeValue('Dir', 'East', [])]));
		$this->assertSame('W', $engine->evaluate($script, ['d' => new SumTypeValue('Dir', 'West', [])]));
	}



	/**
	 * Match vrací složené hodnoty
	 */
	function testMatchReturnsDict(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Rectangle Real Real
s = Shape.Rectangle 3.0 4.0
match s
case Shape.Circle r      then {kind: "circle", area: r * r * 3.0}
case Shape.Rectangle w h then {kind: "rect",   area: w * h}'
		);

		$this->assertEquals(
			(object) ['kind' => 'rect', 'area' => 12.0],
			$result
		);
	}



	function testMatchReturnsList(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Rectangle Real Real
s = Shape.Circle 1.0
match s
case Shape.Circle r      then [r, r * r]
case Shape.Rectangle w h then [w, h]'
		);

		$this->assertSame([1.0, 1.0], $result);
	}



	/**
	 * Match uvnitř lambda
	 */
	function testMatchInsideLambdaInLetBinding(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
toInt = c ->
	match c
	case Color.Red   then 1
	case Color.Green then 2
	case Color.Blue  then 3
toInt Color.Green'
		);

		$this->assertSame(2, $result);
	}



	function testMatchInsideLambdaPassedToListMap(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
colors = [Color.Red, Color.Green, Color.Blue]
List.map colors (c ->
	match c
	case Color.Red   then 1
	case Color.Green then 2
	case Color.Blue  then 3)'
		);

		$this->assertSame([1, 2, 3], $result);
	}



	function testMatchInsideListFold(): void
	{
		$result = $this->engine()->evaluate(
'type Sign = Pos | Neg | Zero
signs = [Sign.Pos, Sign.Neg, Sign.Zero, Sign.Pos]
List.fold signs 0 (acc s ->
	match s
	case Sign.Pos  then acc + 1
	case Sign.Neg  then acc - 1
	case Sign.Zero then acc)'
		);

		$this->assertSame(1, $result);
	}



	/**
	 * Více typů ve stejném skriptu
	 */
	function testMultipleTypesInScript(): void
	{
		$result = $this->engine()->evaluate(
'type Bool_ = True_ | False_
type Opt = Some Int | None
flag = Bool_.True_
opt  = Opt.Some 99
flagVal =
	match flag
	case Bool_.True_  then 1
	case Bool_.False_ then 0
optVal =
	match opt
	case Opt.Some n then n
	case Opt.None   then 0
flagVal + optVal'
		);

		$this->assertSame(100, $result);
	}



	/**
	 * Inline syntax
	 */
	function testInlineMatch(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Green
match c case Color.Red then 1 case Color.Green then 2 case Color.Blue then 3'
		);

		$this->assertSame(2, $result);
	}



	/**
	 * Multi-pattern: case P1 | P2 then body
	 */
	function testMultiPatternCase(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
c = Color.Green
match c
case Color.Red | Color.Green then "warm"
case Color.Blue              then "cool"'
		);

		$this->assertSame('warm', $result);
	}



	function testScalarIntMatch(): void
	{
		$result = $this->engine()->evaluate(
'match n
case 1 then "one"
case 2 then "two"
else "other"',
			['n' => 2]
		);

		$this->assertSame('two', $result);
	}



	function testScalarIntMatchElse(): void
	{
		$result = $this->engine()->evaluate(
'match n
case 1 then "one"
case 2 then "two"
else "other"',
			['n' => 99]
		);

		$this->assertSame('other', $result);
	}



	function testScalarIntMultiPattern(): void
	{
		$result = $this->engine()->evaluate(
'match n
case 1 | 2 then "low"
case 3 | 4 then "high"
else "other"',
			['n' => 3]
		);

		$this->assertSame('high', $result);
	}



	/**
	 * Chybový scénář: žádné rameno neodpovídá (runtime error)
	 */
	function testNonExhaustiveMatchThrows(): void
	{
		$this->expectException(ScriptRuntimeException::class);
		$this->expectExceptionMessageMatches('/Non-exhaustive match/');

		$this->engine()->evaluate(
'type Color = Red | Green | Blue
match c
case Color.Red then "red"',
			['c' => new SumTypeValue('Color', 'Blue', [])]
		);
	}



	private function engine(): HayoEngine
	{
		return HayoEngine::WithDefaultLibraries();
	}

}
