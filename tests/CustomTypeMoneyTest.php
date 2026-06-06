<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use DateInterval;


/**
 * Demonstrates adding a custom composite type (Money) via a TypeProvider library:
 * the type is a product type described by a TypeDef and its values are plain
 * FinalValue (no special value class). Values are built from PHP through
 * HayoEngine::value(), or inside the script through the library's own functions.
 */
class CustomTypeMoneyTest extends TestCase
{

	/**
	 * Vytvoření hodnoty uvnitř skriptu
	 */
	function testCreateInsideScript(): void
	{
		$result = $this->engine()->evaluate('TestMyMoney.fromAmount 9900 "CZK"');

		$this->assertSame([9900, 'CZK'], $result);
	}



	function testFormatInsideScript(): void
	{
		$result = $this->engine()->evaluate('TestMyMoney.format (TestMyMoney.fromAmount 9900 "CZK")');

		$this->assertSame('9900 CZK', $result);
	}



	function testAddInsideScript(): void
	{
		$result = $this->engine()->evaluate('TestMyMoney.add (TestMyMoney.fromAmount 9900 "CZK") (TestMyMoney.fromAmount 2079 "CZK")');

		$this->assertSame([11979, 'CZK'], $result);
	}



	function testScriptWithLocalVars(): void
	{
		$result = $this->engine()->evaluate(
'price = TestMyMoney.fromAmount 9900 "CZK"
tax   = TestMyMoney.fromAmount 2079 "CZK"
total = TestMyMoney.add price tax
TestMyMoney.format total'
		);

		$this->assertSame('11979 CZK', $result);
	}



	/**
	 * Předání hodnoty z PHP do skriptu přes value() + evaluate()
	 */
	function testPassFromPhp(): void
	{
		$engine = $this->engine();
		$result = $engine->evaluate(
			'TestMyMoney.format src',
			['src' => $engine->value([9900, 'CZK'], 'TestMyMoney.Money')]
		);

		$this->assertSame('9900 CZK', $result);
	}



	function testPassFromPhpAndOperate(): void
	{
		$engine = $this->engine();
		$result = $engine->evaluate(
			'TestMyMoney.format (TestMyMoney.add base surcharge)',
			[
				'base' => $engine->value([9900, 'CZK'], 'TestMyMoney.Money'),
				'surcharge' => $engine->value([500, 'CZK'], 'TestMyMoney.Money'),
			]
		);

		$this->assertSame('10400 CZK', $result);
	}



	/**
	 * Hodnota prochází podmínkami a strukturami
	 */
	function testPassesThroughIfElse(): void
	{
		$engine = $this->engine();
		$script = 'if flag then (TestMyMoney.format a) else (TestMyMoney.format b)';

		$this->assertSame('100 CZK', $engine->evaluate($script, [
			'flag' => true,
			'a' => $engine->value([100, 'CZK'], 'TestMyMoney.Money'),
			'b' => $engine->value([200, 'CZK'], 'TestMyMoney.Money'),
		]));

		$this->assertSame('200 CZK', $engine->evaluate($script, [
			'flag' => false,
			'a' => $engine->value([100, 'CZK'], 'TestMyMoney.Money'),
			'b' => $engine->value([200, 'CZK'], 'TestMyMoney.Money'),
		]));
	}



	function testUsedAsFieldInDict(): void
	{
		$result = $this->engine()->evaluate('{label: "cena", amount: (TestMyMoney.fromAmount 9900 "CZK")}');

		$this->assertEquals((object) [
			'label' => 'cena',
			'amount' => [9900, 'CZK'],
		], $result);
	}



	function testListMapOverMoneyValues(): void
	{
		$engine = $this->engine();
		$result = $engine->evaluate(
			'List.map prices (p -> TestMyMoney.format p)',
			['prices' => [
				$engine->value([100, 'CZK'], 'TestMyMoney.Money'),
				$engine->value([200, 'CZK'], 'TestMyMoney.Money'),
				$engine->value([300, 'CZK'], 'TestMyMoney.Money'),
			]]
		);

		$this->assertSame(['100 CZK', '200 CZK', '300 CZK'], $result);
	}



	function testListFoldSumMoneyValues(): void
	{
		$engine = $this->engine();
		$result = $engine->evaluate(
			'List.fold prices (TestMyMoney.fromAmount 0 "CZK") (acc p -> TestMyMoney.add acc p)',
			['prices' => [
				$engine->value([100, 'CZK'], 'TestMyMoney.Money'),
				$engine->value([200, 'CZK'], 'TestMyMoney.Money'),
				$engine->value([300, 'CZK'], 'TestMyMoney.Money'),
			]]
		);

		$this->assertSame([600, 'CZK'], $result);
	}



	/**
	 * Zjištění typu hodnoty přes Introspect.of
	 */
	function testTypeOf(): void
	{
		$engine = $this->engine();
		$result = $engine->evaluate(
			'Introspect.of src',
			['src' => $engine->value([9900, 'CZK'], 'TestMyMoney.Money')]
		);

		$this->assertSame('TestMyMoney.Money', $result);
	}



	function testTypeOfInCondition(): void
	{
		$engine = $this->engine();
		$result = $engine->evaluate(
			'if (Introspect.of src) == "TestMyMoney.Money" then "je to peníze" else "něco jiného"',
			['src' => $engine->value([9900, 'CZK'], 'TestMyMoney.Money')]
		);

		$this->assertSame('je to peníze', $result);
	}



	/**
	 * Hodnota vyrobená funkcí ve skriptu i hodnota z PHP přes value() nesou
	 * shodný plně kvalifikovaný runtime typ (prefix doplní NamespacedFunc resp. engine).
	 */
	function testTypeOfIsNamespacedAndConsistent(): void
	{
		$engine = $this->engine();

		$fromFunc = $engine->evaluate('Introspect.of (TestMyMoney.fromAmount 9900 "CZK")');
		$fromValue = $engine->evaluate(
			'Introspect.of src',
			['src' => $engine->value([9900, 'CZK'], 'TestMyMoney.Money')]
		);

		$this->assertSame('TestMyMoney.Money', $fromFunc);
		$this->assertSame('TestMyMoney.Money', $fromValue);
	}



	/**
	 * Chybový scénář: value() validuje typy polí
	 */
	function testValueValidatesFieldTypes(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->engine()->value(['not-an-int', 'CZK'], 'TestMyMoney.Money');
	}



	/**
	 * Chybový scénář: předání objektu neznámého typu (gause() ho nerozpozná)
	 */
	function testPassingUnknownObjectThrows(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->expectExceptionMessageMatches('/Invalid type of value/');

		$this->engine()->evaluate(
			'TestMyMoney.format src',
			['src' => new DateInterval('P1D')]
		);
	}



	/**
	 * Chybová hláška používá jméno z kódu (namespace volajícího), nikoli interní
	 * qualified name knihovny. `cur` je parametr → kontrola jde přes
	 * assertPartialArgTypes při kompilaci.
	 */
	function testCompileErrorUsesCallSiteName(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessageMatches('/Invalid arguments of TestMyMoney\.fromAmount/');

		$this->engine()->compile('TestMyMoney.fromAmount "x" cur');
	}



	private function engine(): HayoEngine
	{
		return HayoEngine::WithDefaultLibraries()
			->registerLibrary(new MoneyLibrary());
	}

}



/**
 * Testovací knihovna: poskytuje funkce a typ Money (produktový typ Money Int Str)
 * jako objekt přes TypeProvider. Hodnota typu Money je prostá FinalValue se dvěma
 * poli (amount: Int, currency: Str), bez zvláštní třídy. Namespace je deklarován
 * v knihovně (getNamespace), funkce s ním prefixují vlastní typ samy.
 */
class MoneyLibrary implements FuncProvider, TypeProvider
{

	const Ns = 'TestMyMoney';

	const Type = 'Money';

	function getNamespace(): string
	{
		return self::Ns;
	}



	// --- FuncProvider: funkce knihovny (dostanou namespace, ať prefixují typy) ---

	function lookupFunc(string $symbol): ?BuildinFunc
	{
		switch ($symbol) {
			case 'fromAmount': // fromAmount amount currency -> Money
			case 'add': // add a b -> Money
			case 'format': // format src -> Str
				return new MoneyFunc(self::Ns, $symbol);
			default:
				return Null;
		}
	}



	// --- TypeProvider: typy jako objekty ---

	function lookupType(string $name): ?TypeDef
	{
		return $name === self::Type
			? new MoneyTypeDef()
			: Null;
	}



	/**
	 * @return list<string>
	 */
	function getProvidedTypeNames(): array
	{
		return [self::Type];
	}

}



/**
 * Typový objekt Money — produktový typ s poli (Int, Str).
 */
class MoneyTypeDef implements ProductTypeDef
{

	/**
	 * @return list<string>
	 */
	function getFieldTypes(): array
	{
		return ['Int', 'Str'];
	}

}



class MoneyFunc implements BuildinFunc
{

	private string $ns;

	private string $name;

	function __construct(string $ns, string $name)
	{
		$this->ns = $ns;
		$this->name = $name;
	}



	function type(): string
	{
		switch ($this->name) {
			case 'fromAmount':
			case 'add':
				return $this->money();
			case 'format':
				return 'Str';
			default:
				throw SymbolNotFound::UnsupportedFunc($this->ns, $this->name);
		}
	}



	/**
	 * @return list<BindValue>
	 */
	function getBinds(): array
	{
		switch ($this->name) {
			case 'fromAmount':
				return [new BindValue('amount', 'Int'), new BindValue('currency', 'Str')];
			case 'add':
				return [new BindValue('a', $this->money()), new BindValue('b', $this->money())];
			case 'format':
				return [new BindValue('src', $this->money())];
			default:
				throw SymbolNotFound::UnsupportedFunc($this->ns, $this->name);
		}
	}



	/**
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value
	{
		$a = array_values($args);
		switch ($this->name) {
			case 'fromAmount':
				TypeValidator::assertInt($a[0]->unpack());
				TypeValidator::assertStr($a[1]->unpack());
				return $this->make($a[0]->unpack(), $a[1]->unpack());

			case 'add':
				[$amountA, $currency] = self::fields($a[0]);
				[$amountB,] = self::fields($a[1]);
				return $this->make($amountA + $amountB, $currency);

			case 'format':
				[$amount, $currency] = self::fields($a[0]);
				return new FinalValue("{$amount} {$currency}", 'Str');

			default:
				throw SymbolNotFound::UnsupportedFunc($this->ns, $this->name);
		}
	}



	/**
	 * Fully-qualified name of the Money type ("TestMyMoney.Money") — the library
	 * knows its namespace, so its functions prefix the type themselves.
	 */
	private function money(): string
	{
		return $this->ns . '.' . MoneyLibrary::Type;
	}



	/**
	 * Builds a Money value via the shared composite() factory — the value shape
	 * (FinalValue with a positional field payload) lives in FinalValue, not here.
	 */
	private function make(int $amount, string $currency): FinalValue
	{
		return FinalValue::composite([
			new FinalValue($amount, 'Int'),
			new FinalValue($currency, 'Str'),
		], $this->money());
	}



	/**
	 * @return array{0: int, 1: string}
	 */
	private static function fields(FinalValue $src): array
	{
		$fields = $src->fields();
		if (count($fields) !== 2) {
			throw new InvalidArgumentException(MoneyLibrary::Type . ': expected a Money value.');
		}
		return [$fields[0]->unpack(), $fields[1]->unpack()];
	}



	function __toString(): string
	{
		return '<' . $this->ns . '.' . $this->name . '>';
	}

}
