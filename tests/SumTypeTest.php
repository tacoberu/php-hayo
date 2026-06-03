<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class SumTypeTest extends TestCase
{

	/**
	 * type declaration + constructors
	 */
	function testNoArgConstructor(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
Color.Red'
		);

		$this->assertInstanceOf(SumTypeValue::class, $result);
		$this->assertSame('Color', $result->getTypeName());
		$this->assertSame('Red', $result->getVariant());
		$this->assertSame([], $result->getPayload());
	}



	function testOneArgConstructor(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Point
Shape.Circle 3.14'
		);

		$this->assertInstanceOf(SumTypeValue::class, $result);
		$this->assertSame('Shape', $result->getTypeName());
		$this->assertSame('Circle', $result->getVariant());
		$this->assertSame(3.14, $result->getPayload()[0]->unpack());
	}



	function testTwoArgConstructor(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Rectangle Real Real | Point
Shape.Rectangle 10.0 5.0'
		);

		$this->assertInstanceOf(SumTypeValue::class, $result);
		$this->assertSame('Rectangle', $result->getVariant());
		$this->assertSame(10.0, $result->getPayload()[0]->unpack());
		$this->assertSame(5.0, $result->getPayload()[1]->unpack());
	}



	function testConstructorWithLocalVar(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Point
r = 3.14
Shape.Circle r'
		);

		$this->assertInstanceOf(SumTypeValue::class, $result);
		$this->assertSame('Circle', $result->getVariant());
	}



	/**
	 * Introspect.is works with sum type values
	 */
	function testIntrospectIsType(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
Introspect.is Color.Red "Color"'
		);

		$this->assertTrue($result);
	}



	function testIntrospectOfType(): void
	{
		$result = $this->engine()->evaluate(
'type Color = Red | Green | Blue
Introspect.of Color.Red'
		);

		$this->assertSame('Color', $result);
	}


	// -------------------------------------------------------------------------
	// match expression

	function testMatchNoArgVariants(): void
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



	function testMatchWithPayload(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Rectangle Real Real | Point
s = Shape.Circle 3.0
match s
case Shape.Circle r      then r * r * 3.0
case Shape.Rectangle w h then w * h
case Shape.Point         then 0.0'
		);

		$this->assertSame(27.0, $result);
	}



	function testMatchWithExternalArg(): void
	{
		$result = $this->engine()->evaluate(
'type Shape = Circle Real | Rectangle Real Real | Point
match s
case Shape.Circle r      then r * r
case Shape.Rectangle w h then w * h
case Shape.Point         then 0.0',
			['s' => new SumTypeValue('Shape', 'Rectangle', [
				new FinalValue(4.0, 'Real'),
				new FinalValue(5.0, 'Real'),
			])]
		);

		$this->assertSame(20.0, $result);
	}



	function testMatchWildcard(): void
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



	function testMatchInList(): void
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



	private function engine(): HayoEngine
	{
		return HayoEngine::WithDefaultLibraries();
	}

}
