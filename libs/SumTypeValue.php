<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * Runtime value of a sum type (discriminated union) declared with `type`.
 *
 * Each value carries:
 *   - typeName — the declared type (e.g. "Shape")
 *   - variant — the chosen variant (e.g. "Circle")
 *   - payload — the variant's data fields as FinalValues
 *
 * HayoValue::getHayoType() returns typeName so that Introspect.is and
 * the type inferrer can recognize the value without knowing its variant.
 *
 * Usage inside Hayo:
 *
 *   type Shape = Circle Real | Rectangle Real Real | Point
 *
 *   Shape.Circle 3.14 -- SumTypeValue("Shape", "Circle", [3.14])
 *   Shape.Rectangle 10.0 5.0 -- SumTypeValue("Shape", "Rectangle", [10.0, 5.0])
 *   Shape.Point -- SumTypeValue("Shape", "Point", [])
 *
 *   match s
 *   | Shape.Circle r -> r * r * 3.14159
 *   | Shape.Rectangle w h -> w * h
 *   | Shape.Point -> 0
 */
class SumTypeValue implements HayoValue
{

	private string $typeName;

	private string $variant;

	/**
	 * @var list<FinalValue>
	 */
	private array $payload;

	/**
	 * @param list<FinalValue> $payload
	 */
	function __construct(string $typeName, string $variant, array $payload)
	{
		$this->typeName = $typeName;
		$this->variant = $variant;
		$this->payload = $payload;
	}



	function getTypeName(): string
	{
		return $this->typeName;
	}



	function getVariant(): string
	{
		return $this->variant;
	}



	/**
	 * @return list<FinalValue>
	 */
	function getPayload(): array
	{
		return $this->payload;
	}



	function getHayoType(): string
	{
		return $this->typeName;
	}



	function __toString(): string
	{
		$args = implode(' ', array_map(static function (FinalValue $v): string {
			return (string) $v->unpack();
		}, $this->payload));
		return $args !== '' && $args !== '0'
			? "{$this->typeName}.{$this->variant} {$args}"
			: "{$this->typeName}.{$this->variant}";
	}

}
