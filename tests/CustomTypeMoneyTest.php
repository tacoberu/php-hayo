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
 * Demonstrates adding a custom type (Money) via HayoValue + TypeDescriptor.
 */
class CustomTypeMoneyTest extends TestCase
{

	/**
	 * Vytvoření hodnoty uvnitř skriptu
	 */
	function testCreateInsideScript(): void
	{
		$result = $this->engine()->evaluate('TestMyMoney.fromAmount 9900 "CZK"');

		$this->assertEquals(new MoneyValue(9900, 'CZK'), $result);
	}



	function testFormatInsideScript(): void
	{
		$result = $this->engine()->evaluate('TestMyMoney.format (TestMyMoney.fromAmount 9900 "CZK")');

		$this->assertSame('9900 CZK', $result);
	}



	function testAddInsideScript(): void
	{
		$result = $this->engine()->evaluate(
			'TestMyMoney.add (TestMyMoney.fromAmount 9900 "CZK") (TestMyMoney.fromAmount 2079 "CZK")'
		);

		$this->assertEquals(new MoneyValue(11979, 'CZK'), $result);
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
	 * Předání hodnoty z PHP do skriptu přes evaluate()
	 */
	function testPassFromPhp(): void
	{
		$result = $this->engine()->evaluate(
			'TestMyMoney.format src',
			['src' => new MoneyValue(9900, 'CZK')]
		);

		$this->assertSame('9900 CZK', $result);
	}



	function testPassFromPhpAndOperate(): void
	{
		$result = $this->engine()->evaluate(
			'TestMyMoney.format (TestMyMoney.add base surcharge)',
			[
				'base' => new MoneyValue(9900, 'CZK'),
				'surcharge' => new MoneyValue(500, 'CZK'),
			]
		);

		$this->assertSame('10400 CZK', $result);
	}



	/**
	 * Hodnota prochází podmínkami a strukturami
	 */
	function testPassesThroughIfElse(): void
	{
		$script = 'if flag then (TestMyMoney.format a) else (TestMyMoney.format b)';

		$this->assertSame('100 CZK', $this->engine()->evaluate($script, [
			'flag' => true,
			'a' => new MoneyValue(100, 'CZK'),
			'b' => new MoneyValue(200, 'CZK'),
		]));

		$this->assertSame('200 CZK', $this->engine()->evaluate($script, [
			'flag' => false,
			'a' => new MoneyValue(100, 'CZK'),
			'b' => new MoneyValue(200, 'CZK'),
		]));
	}



	function testUsedAsFieldInDict(): void
	{
		$result = $this->engine()->evaluate('{label: "cena", amount: (TestMyMoney.fromAmount 9900 "CZK")}');

		$this->assertEquals((object) [
			'label' => 'cena',
			'amount' => new MoneyValue(9900, 'CZK'),
		], $result);
	}



	function testListMapOverMoneyValues(): void
	{
		$result = $this->engine()->evaluate(
			'List.map prices (p -> TestMyMoney.format p)',
			['prices' => [
				new MoneyValue(100, 'CZK'),
				new MoneyValue(200, 'CZK'),
				new MoneyValue(300, 'CZK'),
			]]
		);

		$this->assertSame(['100 CZK', '200 CZK', '300 CZK'], $result);
	}



	function testListFoldSumMoneyValues(): void
	{
		$result = $this->engine()->evaluate(
			'List.fold prices (TestMyMoney.fromAmount 0 "CZK") (acc p -> TestMyMoney.add acc p)',
			['prices' => [
				new MoneyValue(100, 'CZK'),
				new MoneyValue(200, 'CZK'),
				new MoneyValue(300, 'CZK'),
			]]
		);

		$this->assertEquals(new MoneyValue(600, 'CZK'), $result);
	}



	/**
	 * Zjištění typu hodnoty přes Introspect.of
	 */
	function testTypeOf(): void
	{
		$result = $this->engine()->evaluate(
			'Introspect.of src',
			['src' => new MoneyValue(9900, 'CZK')]
		);

		$this->assertSame('Money', $result);
	}



	function testTypeOfInCondition(): void
	{
		$result = $this->engine()->evaluate(
			'if (Introspect.of src) == "Money" then "je to peníze" else "něco jiného"',
			['src' => new MoneyValue(9900, 'CZK')]
		);

		$this->assertSame('je to peníze', $result);
	}



	/**
	 * Chybový scénář: předání nesouvisejícího objektu (neimplementuje HayoValue)
	 */
	function testPassingUnknownObjectThrows(): void
	{
		$this->expectException(ArgumentsException::class);
		$this->expectExceptionMessageMatches('/Invalid type of value/');

		// DateInterval není HayoValue ani stdClass → gauseType() hodí ArgumentsException
		$this->engine()->evaluate(
			'TestMyMoney.format src',
			['src' => new DateInterval('P1D')]
		);
	}



	private function engine(): HayoEngine
	{
		return HayoEngine::WithDefaultLibraries()
			->registerLibrary(MoneyProvider::Ns, new MoneyProvider());
	}

}



/**
 * Testovací typ Money — vzor pro vlastní typy s HayoValue + TypeDescriptor
 */
class MoneyValue implements HayoValue
{

	/**
	 * @var int
	 */
	private $amount;

	/**
	 * @var string
	 */
	private $currency;

	function __construct(int $amount, string $currency)
	{
		$this->amount = $amount;
		$this->currency = $currency;
	}



	function getHayoType(): string
	{
		return MoneyProvider::Name;
	}



	function getAmount(): int
	{
		return $this->amount;
	}



	function getCurrency(): string
	{
		return $this->currency;
	}



	function __toString(): string
	{
		return "{$this->amount} {$this->currency}";
	}

}



class MoneyProvider implements SymbolProvider, TypeDescriptor
{

	const Ns = 'TestMyMoney';
	const Name = 'Money';

	function getTypeName(): string
	{
		return self::Name;
	}



	function lookup(string $symbol): ?BuildinFunc
	{
		switch ($symbol) {
			case 'fromAmount': // TestMyMoney.fromAmount amount currency → Money
				return new MoneyFunc('fromAmount');
			case 'add': // TestMyMoney.add a b → Money
				return new MoneyFunc('add');
			case 'format': // TestMyMoney.format src → Str
				return new MoneyFunc('format');
			default:
				return null;
		}
	}

}



class MoneyFunc implements BuildinFunc
{

	/**
	 * @var string
	 */
	private $name;

	function __construct(string $name)
	{
		$this->name = $name;
	}



	function getQualifiedName(): string
	{
		return MoneyProvider::Ns . '.' . $this->name;
	}



	function type(): string
	{
		switch ($this->name) {
			case 'fromAmount':
			case 'add':
				return MoneyProvider::Name;
			case 'format':
				return 'Str';
			default:
				return '?';
		}
	}



	/** @return list<BindValue> */
	function getBinds(): array
	{
		switch ($this->name) {
			case 'fromAmount':
				return [new BindValue('amount', 'Int'), new BindValue('currency', 'Str')];
			case 'add':
				return [new BindValue('a', MoneyProvider::Name), new BindValue('b', MoneyProvider::Name)];
			case 'format':
				return [new BindValue('src', MoneyProvider::Name)];
			default:
				return [];
		}
	}



	/** @param array<string, FinalValue> $args */
	function apply(array $args): Value
	{
		$a = array_values($args);
		switch ($this->name) {
			case 'fromAmount':
				return $this->applyFromAmount($a[0], $a[1]);
			case 'add':
				return $this->applyAdd($a[0], $a[1]);
			case 'format':
				return $this->applyFormat($a[0]);
			default:
				throw SymbolNotFound::UnsupportedFunc(MoneyProvider::Ns, $this->name);
		}
	}



	private static function applyFromAmount(FinalValue $amount, FinalValue $currency): FinalValue
	{
		TypeValidator::assertInt($amount->unpack());
		TypeValidator::assertStr($currency->unpack());
		return new FinalValue(new MoneyValue($amount->unpack(), $currency->unpack()), MoneyProvider::Name);
	}



	private static function applyAdd(FinalValue $a, FinalValue $b): FinalValue
	{
		$aVal = $a->getValue();
		$bVal = $b->getValue();
		if ( ! $aVal instanceof MoneyValue || ! $bVal instanceof MoneyValue) {
			throw new InvalidArgumentException(MoneyProvider::Ns . '.add: both arguments must be Money values.');
		}
		return new FinalValue(new MoneyValue($aVal->getAmount() + $bVal->getAmount(), $aVal->getCurrency()), MoneyProvider::Name);
	}



	private static function applyFormat(FinalValue $src): FinalValue
	{
		$money = $src->getValue();
		if ( ! $money instanceof MoneyValue) {
			throw new InvalidArgumentException(MoneyProvider::Ns . '.format: argument must be a Money value.');
		}
		return new FinalValue("{$money->getAmount()} {$money->getCurrency()}", 'Str');
	}



	function __toString(): string
	{
		return '<' . MoneyProvider::Ns . '.' . $this->name . '>';
	}

}
