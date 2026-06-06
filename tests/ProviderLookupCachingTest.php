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
 * Ověřuje, že provider není dotazován opakovaně na tentýž symbol.
 *
 * Scénář: Scope (let-block) s více vazbami odkazujícími na tutéž funkci.
 * Scope::refs() neodstraňuje duplikáty, takže createGlobalSymbols() zavolá
 * lookup() pro každý výskyt zvlášť. Každé volání vytvoří novou instanci
 * BuildinFunc — žádné kešování neexistuje.
 */
class ProviderLookupCachingTest extends TestCase
{

	function testCorrectResultWhenFunctionUsedTwiceInScope(): void
	{
		$engine = new HayoEngine([
			$this->makeSpy(),
			new MathsProvider(),
			new PredicatesProvider(),
		]);

		$result = $engine->evaluate(
			"a = Spy.len x\nb = Spy.len y\na + b",
			['x' => 'hello', 'y' => 'world']
		);

		$this->assertSame(10, $result);
	}



	function testLookupCalledOncePerUniqueSymbol(): void
	{
		$spy = $this->makeSpy();
		$engine = new HayoEngine([
			$spy,
			new MathsProvider(),
			new PredicatesProvider(),
		]);

		$engine->evaluate(
			"a = Spy.len x\nb = Spy.len y\na + b",
			['x' => 'hello', 'y' => 'world']
		);

		$count = $spy->getLookupCount('len'); // @phpstan-ignore method.notFound
		$this->assertSame(1, $count, 'lookup("len") mělo být voláno jednou, ale bylo voláno ' . $count . '×.');
	}



	private function makeSpy(): FuncProvider
	{
		return new class (new StringsProvider()) implements FuncProvider {

			/**
			 * @var array<string, int>
			 */
			private array $lookupCount = [];

			private FuncProvider $inner;

			function __construct(FuncProvider $inner)
			{
				$this->inner = $inner;
			}



			function getNamespace(): string
			{
				return 'Spy';
			}



			function lookupFunc(string $symbol): ?BuildinFunc
			{
				$this->lookupCount[$symbol] = ($this->lookupCount[$symbol] ?? 0) + 1;
				return $this->inner->lookupFunc($symbol);
			}



			function getLookupCount(string $symbol): int
			{
				return $this->lookupCount[$symbol] ?? 0;
			}

		};
	}

}
