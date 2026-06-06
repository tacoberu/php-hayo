<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


/**
 * Probe for HayoEngine::value(): a custom value is built from PHP using only a
 * registered TypeProvider (no functions, no special value class), namespaced type.
 */
class EngineValueTest extends TestCase
{

	function testBuildsFinalValueOfType(): void
	{
		$v = $this->engine()->value([9900, 'CZK'], 'Probe.Money');

		$this->assertInstanceOf(FinalValue::class, $v);
		$this->assertSame('Probe.Money', $v->type());
	}



	function testPayloadIsWrappedFields(): void
	{
		$v = $this->engine()->value([9900, 'CZK'], 'Probe.Money');

		$payload = $v->getValue();
		$this->assertCount(2, $payload);
		$this->assertSame(9900, $payload[0]->unpack());
		$this->assertSame('Int', $payload[0]->type());
		$this->assertSame('CZK', $payload[1]->unpack());
		$this->assertSame('Str', $payload[1]->type());
	}



	function testPassesIntoScriptAndIntrospects(): void
	{
		$engine = $this->engine();
		$money = $engine->value([9900, 'CZK'], 'Probe.Money');

		$this->assertSame('Probe.Money', $engine->evaluate('Introspect.of src', ['src' => $money]));
		$this->assertTrue($engine->evaluate('Introspect.is src "Probe.Money"', ['src' => $money]));
	}



	function testValidatesFieldType(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->engine()->value(['not-an-int', 'CZK'], 'Probe.Money');
	}



	function testValidatesFieldCount(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->engine()->value([9900], 'Probe.Money');
	}



	function testRejectsUnknownType(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->engine()->value([9900, 'CZK'], 'Probe.Nonexistent');
	}



	function testRejectsUnqualifiedType(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->engine()->value([9900, 'CZK'], 'Money');
	}



	/**
	 * Jedna knihovna poskytuje více typů — každý se adresuje pod svým plným jménem.
	 */
	function testProvidesMultipleTypes(): void
	{
		$engine = $this->engine();

		$money = $engine->value([9900, 'CZK'], 'Probe.Money');
		$weight = $engine->value([5], 'Probe.Weight');

		$this->assertSame('Probe.Money', $money->type());
		$this->assertSame('Probe.Weight', $weight->type());

		$this->assertSame('Probe.Money', $engine->evaluate('Introspect.of src', ['src' => $money]));
		$this->assertSame('Probe.Weight', $engine->evaluate('Introspect.of src', ['src' => $weight]));
	}



	/**
	 * Sum typ: value() s variantou vyrobí SumTypeValue nesoucí plné jméno typu.
	 */
	function testBuildsSumValueWithVariant(): void
	{
		$v = $this->engine()->value([3.14], 'Probe.Shape', 'Circle');

		$this->assertSame('Probe.Shape', $v->type());
		$inner = $v->getValue();
		$this->assertInstanceOf(SumTypeValue::class, $inner);
		$this->assertSame('Probe.Shape', $inner->getTypeName());
		$this->assertSame('Circle', $inner->getVariant());
		$this->assertSame(3.14, $inner->getPayload()[0]->unpack());
	}



	function testSumValueIntrospects(): void
	{
		$engine = $this->engine();
		$shape = $engine->value([10.0, 5.0], 'Probe.Shape', 'Rectangle');

		$this->assertSame('Probe.Shape', $engine->evaluate('Introspect.of src', ['src' => $shape]));
		$this->assertTrue($engine->evaluate('Introspect.is src "Probe.Shape"', ['src' => $shape]));
	}



	function testSumVariantValidatesArgTypes(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->engine()->value(['not-a-real'], 'Probe.Shape', 'Circle');
	}



	function testSumRequiresVariant(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->engine()->value([3.14], 'Probe.Shape');
	}



	function testSumRejectsUnknownVariant(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->engine()->value([3.14], 'Probe.Shape', 'Nonexistent');
	}



	function testProductRejectsVariant(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->engine()->value([9900, 'CZK'], 'Probe.Money', 'Circle');
	}



	/**
	 * Sum hodnota z value() je plnohodnotná v `match` — rozlišení variant i binding
	 * payloadu (typ Shape teče přes collectSumTypes do inferreru pod plným jménem).
	 */
	function testSumValueWorksInMatch(): void
	{
		$engine = $this->engine();
		$script =
'match src
	case Probe.Shape.Circle r then r
	case Probe.Shape.Rectangle w h then w * h
	case Probe.Shape.Point then 0.0';

		$this->assertSame(3.14, $engine->evaluate($script, ['src' => $engine->value([3.14], 'Probe.Shape', 'Circle')]));
		$this->assertSame(50.0, $engine->evaluate($script, ['src' => $engine->value([10.0, 5.0], 'Probe.Shape', 'Rectangle')]));
	}



	/**
	 * Konstruktor sum typu z knihovny lze volat přímo ve skriptu
	 * (`Probe.Shape.Circle 3.14`); výsledná hodnota nese plně kvalifikované
	 * jméno typu (prefix namespace knihovny), stejně jako hodnota z value().
	 */
	function testSumConstructorInScript(): void
	{
		$engine = $this->engine();

		// Varianta s argumentem.
		$circle = $engine->evaluate('Probe.Shape.Circle 3.14');
		$this->assertInstanceOf(SumTypeValue::class, $circle);
		$this->assertSame('Probe.Shape', $circle->getTypeName());
		$this->assertSame('Circle', $circle->getVariant());
		$this->assertSame(3.14, $circle->getPayload()[0]->unpack());

		// Bezargumentová varianta.
		$point = $engine->evaluate('Probe.Shape.Point');
		$this->assertInstanceOf(SumTypeValue::class, $point);
		$this->assertSame('Probe.Shape', $point->getTypeName());
		$this->assertSame('Point', $point->getVariant());

		// Plně kvalifikované jméno teče i do introspekce.
		$this->assertSame('Probe.Shape', $engine->evaluate('Introspect.of (Probe.Shape.Rectangle 10.0 5.0)'));
	}



	/**
	 * Hodnota vyrobená konstruktorem ve skriptu je plnohodnotná v `match` —
	 * konstrukce i rozlišení variant proběhne celé uvnitř skriptu.
	 */
	function testSumConstructorInScriptWorksInMatch(): void
	{
		$result = $this->engine()->evaluate(
'shape = Probe.Shape.Rectangle 10.0 5.0
match shape
	case Probe.Shape.Circle r then r * r * 3.14159
	case Probe.Shape.Rectangle w h then w * h
	case Probe.Shape.Point then 0.0');

		$this->assertSame(50.0, $result);
	}



	private function engine(): HayoEngine
	{
		return HayoEngine::WithDefaultLibraries()
			->registerLibrary(new ProbeTypeLibrary());
	}

}



/**
 * Library providing product types (Money, Weight) and a sum type (Shape) — no functions.
 */
class ProbeTypeLibrary implements FuncProvider, TypeProvider
{

	function getNamespace(): string
	{
		return 'Probe';
	}



	function lookupFunc(string $symbol): ?BuildinFunc
	{
		return Null;
	}



	function lookupType(string $name): ?TypeDef
	{
		switch ($name) {
			case 'Money': // Money Int Str
				return new ProbeTypeDef(['Int', 'Str']);
			case 'Weight': // Weight Int
				return new ProbeTypeDef(['Int']);
			case 'Shape': // Circle Real | Rectangle Real Real | Point
				return new ProbeShapeDef();
			default:
				return Null;
		}
	}



	/**
	 * @return list<string>
	 */
	function getProvidedTypeNames(): array
	{
		return ['Money', 'Weight', 'Shape'];
	}

}



/**
 * Sum typ Shape pro test tvorby varianty přes value().
 */
class ProbeShapeDef implements SumTypeDef
{

	function getTypeName(): string
	{
		return 'Shape';
	}



	/**
	 * @return list<string>
	 */
	function getVariantNames(): array
	{
		return ['Circle', 'Rectangle', 'Point'];
	}



	/**
	 * @return list<string>
	 */
	function getTypeParams(): array
	{
		return [];
	}



	/**
	 * @return list<string>
	 */
	function getVariantArgTypes(string $variant): array
	{
		switch ($variant) {
			case 'Circle': return ['Real'];
			case 'Rectangle': return ['Real', 'Real'];
			default: return [];
		}
	}

}



/**
 * Parametrizovaný produktový typ — pole daná v konstruktoru.
 */
class ProbeTypeDef implements ProductTypeDef
{

	/**
	 * @var list<string>
	 */
	private array $fieldTypes;

	/**
	 * @param list<string> $fieldTypes
	 */
	function __construct(array $fieldTypes)
	{
		$this->fieldTypes = $fieldTypes;
	}



	/**
	 * @return list<string>
	 */
	function getFieldTypes(): array
	{
		return $this->fieldTypes;
	}

}
