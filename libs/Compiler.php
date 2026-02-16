<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;


/**
 * PHP Compiler Hayo.
 * Výsledek je php kod, který se dá uložit do souboru, který se načíst pomocí require. Toto uložení by ale asi mělo být volitelné, jako cache.
 */
class Compiler
{

	/**
	 * @var list<SymbolProvider>
	 */
	private array $libs = [];

	/**
	 * @param list<SymbolProvider> $libs
	 */
	function __construct(array $libs)
	{
		$this->libs = $libs;
	}



	static function WithDefaultLibraries(): self
	{
		return new self([
			'predicate' => new PredicatesProvider(),
			'math' => new MathsProvider(),
			'str' => new StringsProvider(),
			'list' => new ListsProvider(),
		]);
	}



	/**
	 * Vrací konečnou hodnotu, nebo funkci, kterou je třeba naplnit argumenty.
	 */
	function compile(string $source): Val
	{
		$decoder = new HayoDecoder();
		$term = $decoder->decode($source);
		if ( ! $term instanceof Value) {
			// `a` -- vracíme argument
			if (is_string($term)) {
				return VariadicVal::ShortLinkBind(new BindVal($term, '?'));
			}
			throw new LogicException("Invalid source code.");
		}

		// Vytáhnu si všechny závislosti. Pokusím se je dohledat; například buildin funkce, a podobně.
		// A ty co nejsou zůstanou jako parametry funkce.
		$context = $this->createGlobalSymbols($term);

		// První fáze: vyhodnotíme nabindované symboly. Vypočítáme všechny věci, které jdou vypočítat staticky.
		$term = self::partialEvaluate($context, $term);
		if ( ! $term instanceof Value) {
			throw new LogicException("Invalid source code.");
		}

		// Druhá váze: převedem term -> val
		return self::compileRuntimeValue($term);
	}



	private function createGlobalSymbols(Value $src): Context
	{
		$lets = [];
		if ($src instanceof HasRefs) {
			foreach ($src->refs() as $x) {
				if ($pair = $this->lookupGlobalSymbol($x)) {
					$lets[$pair[0]] = $pair[1];
				}
			}
		}
		return new Context($lets);
	}



	/**
	 * Vytahuje globální symboly, jako matematické opertáry, funkce pro práci
	 * s textem, poly, a uživatelsky definované funkce.
	 *
	 * @TODO Možnost lokálního importu.
	 * @return array{0: string, 1: Value}
	 */
	private function lookupGlobalSymbol(string $x): ?array
	{
		foreach ($this->libs as $provider) {
			if ($fn = $provider->lookup($x)) {
				return [$x, $fn];
			}
		}

		return Null;
	}



	/**
	 * Provede **částečné vyhodnocení** výrazu.
	 *
	 * Tato funkce rekurzivně prochází strom výrazů (AST) a snaží se
	 * vyhodnotit všechny části, které lze určit už v aktuálním kontextu.
	 *
	 * - Pokud jsou všechny operandy výrazu známé (konstanty nebo hodnoty
	 *   dostupné v kontextu), výraz se okamžitě spočítá a nahradí výsledkem.
	 * - Pokud je známá jen část operandů, funkce zachová výraz v původní
	 *   struktuře, ale dosadí známé hodnoty a případně zjednoduší operace.
	 * - Pokud není možné nic vyhodnotit, výraz zůstává beze změny.
	 *
	 * Typickým příkladem je situace, kdy máme:
	 *     a = 10
	 *     výraz: a * 2 + b
	 *
	 * Po částečném vyhodnocení vznikne:
	 *     20 + b
	 *
	 * Cílem funkce je snížit složitost výrazu před jeho úplným vyhodnocením
	 * nebo kompilací, a tím zrychlit pozdější provádění.
	 *
	 * @param Value | string $term
	 * @return Value | string
	 */
	private static function partialEvaluate(Context $context, $term)
	{
		switch (True) {
			// Symboly musí zpracovat rodič, zde s tím nic neudělám.
			case is_string($term):
				return $context->trySelectSymbol($term);

			// Na číslech, konkečných hodnotách a vestavěných funkcích není co zpravovávat
			case $term instanceof Scalar:
			case $term instanceof FinalVal:
			case $term instanceof BuildinFunc:
				return $term;

			// Ve slovnících etc sice mohou být navázány symboly, nebo volání funkce, ale to musíme zpracovat o úroven víš, ve Scope.
			case $term instanceof Composite:
				return self::partialEvaluateComposite($context, $term);

			//~ case $term instanceof BuildinFunc:
			// @TODO Prostor pro optimalizaci: Labda se nedá vykonat celá, protože závisí na stavu argumentu.
			// ale části toho Expr by možná šli. Záleží jak moc je ta lambda košatá.
			case $term instanceof Lambda:
				return self::partialEvaluateLambda($context, $term);

			// Všechny symboly z lokálního scope přesunout na místo užití, a následně symbol i scope zaniká.
			// Provede **částečné vyhodnocení** výrazu, u kterého očekáváme jako výsledek hodnotu.
			case $term instanceof Scope:
				return self::partialEvaluateScope($context, $term);

			// Může se jednat o volání funkce: `format(1 2 3)`, vrátíme hodnotu
			// Může se jednat o operaci: `1 + 1`, vrátíme hodnotu
			case $term instanceof Expr && $term->refs() === []:
				throw self::UnsupportedException('partial evaluate', $term);

			// Může se jednat o volání funkce: `format(1 a 3)`, protože "a" neznáme, vrátíme funkci.
			// Může se jednat o operaci: `1 + a`, protože "a" neznáme, vrátíme funkci.
			// Může se jednat o predikát: `equals(1, 1) and a == 42`, protože "a" neznáme, vrátíme funkci.
			case $term instanceof Expr && $term->refs() !== []:
				return self::partialEvaluateExpr($context, $term);

			default:
				throw self::UnsupportedException('partial evaluate', $term);
		}
	}



	/**
	 * Provede **částečné vyhodnocení** výrazu, u kterého očekáváme jako výsledek lambdu.
	 * Očekáváme, že, všechny závislosti jsou vyřešeny, a ty které nejsou jsou vnější.
	 *
	 * `1 + 1`
	 * `41 + a`
	 * `1 + (1 + a)`
	 * `strings.len a`
	 * `strings.split "," src`
	 * `list.at 2 src`
	 * `list.at 2 ["une", a, "trois"]`
	 *
	 * Nabindované symboly si vytáhneme z contextu. Než je ale vykonáme tak musíme vykonat zanořené expression.
	 *
	 * @TODO Special form
	 * @TODO Forma? Podmíněné vyhodnocování? Vzhledem k tomu, že nemáme sideeffecty, tak to není tak horký.
	 */
	private static function partialEvaluateExpr(Context $context, Expr $term): Value
	{
		$items = $term->getItems();

		switch (True) {
			case $term->getNotation() === Expr::NotationInfix:
				foreach ($items as $k => $x) {
					$items[$k] = self::partialEvaluate($context, $x);
				}

				$term = Expr::Bin_($items[0], $items[1], $items[2]);

				// Rovnou vyhodnotit
				if ($term->refs() === []) {
					return $items[1]->apply([ // @phpstan-ignore method.nonObject
						self::castAny($items[0], False)[0],
						self::castAny($items[2], False)[0],
						]);
				}

				// Jsou tam nějaké argumenty
				return $term;

			case $term->getNotation() === Expr::NotationPrefix:
				foreach ($items as $k => $x) {
					$items[$k] = self::partialEvaluate($context, $x);
				}

				$term = Expr::Func_($items[0], array_slice($items, 1));

				// Rovnou vyhodnotit
				if ($term->refs() === []) {
					$args = array_map(static function($x) {
						return self::castAny($x, False)[0];
					}, array_slice($items, 1));

					// phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
					if ($items[0] instanceof Scalar && $items[1] == Composite::Tuple_([])) {
						return $items[0];
					}
					return self::partialEvaluateApplicable($items[0], $args);
				}
				else {
					if ($items[0] instanceof Lambda
							&& count($items[0]->getArgs()) === (count($items) - 1)) {

						// @TODO Toto píšu poněkud unaven. Myslím, že by se to mělo řešit poněkud jinak.
						$term = self::optimalizeLambdaCalling($items[0], array_slice($items, 1));
					}
				}

				// Jsou tam nějaké argumenty
				return $term;

			default:
				throw self::UnsupportedException('partial evaluate expr of term', $term);
		}
	}



	private static function optimalizeLambdaCalling(Lambda $fn, array $args)
	{
		$context = new Context(array_combine($fn->getArgs(), $args));
		$term = self::partialEvaluateExpr($context, $fn->getExpr());
		return $term;
	}



	/**
	 * @param list<string | Value> $args
	 * @return Value | string
	 */
	private static function partialEvaluateApplicable(Applicable $fn, array $args)
	{
		switch (True) {
			case $fn instanceof Lambda:
				$context = new Context([]);
				foreach ($fn->getArgs() as $i => $id) {
					$context->shadow($id, $args[$i]);
				}
				return self::partialEvaluate($context, $fn->getExpr());

			case $fn instanceof BuildinFunc:
				return $fn->apply($args);// @phpstan-ignore method.nonObject

			default:
				throw new LogicException("oops.");
		}
	}



	/**
	 * Provede **částečné vyhodnocení** výrazu.
	 * @return Value | string
	 */
	private static function partialEvaluateScope(Context $context, Scope $src)
	{
		switch (True) {
			case is_string($src->getExpr()):
				//~ if ($term = $context->selectSymbol($src->getExpr())) {
					//~ die("\n------\n" . __file__ . ':' . __line__ . "\n");
				//~ }
				return $src;

			// Zanořené scope není podporováno.
			case $src->getExpr() instanceof Scope:
				throw self::UnsupportedException('partial evaluate const scope of scope', $src->getExpr());

			// `{1 + 1}` -- protože sčítání je taky symbol -> `{+ = buildin; 1 + 1}`
			// `{a = 1; a + a}`
			case $src->getExpr() instanceof Expr:
				$context2 = clone $context;
				$seconds = [];
				// 1/ Nejdříve zpracujeme bezpečné hodnoty
				foreach ($src->getLets() as $id => $value) {
					if (is_string($value) && strpos($value, '.')) {
						$seconds[$id] = $value;
					}
					elseif (is_string($value)) {
						$context2->shadowAnotherSymbol($id, $value);
					}
					elseif ( ! $value instanceof HasRefs) {
						$context2->shadow($id, self::partialEvaluate($context, $value)); // @phpstan-ignore argument.type
					}
					elseif ($value->refs() === []) {
						$context2->shadow($id, self::partialEvaluate($context, $value)); // @phpstan-ignore argument.type
					}
					else {
						$seconds[$id] = $value;
					}
				}

				// 2/ Hodnoty, které šahají do rodičovského scope
				// @TODO Recurse
				foreach ($seconds as $id => $value) {
					$context2->shadow($id, self::partialEvaluate($context2, $value)); // @phpstan-ignore argument.type
				}

				$term = self::partialEvaluate($context2, $src->getExpr());
				$term2 = self::partialEvaluate($context2, $term);

				return $term2;

			// `a = 5; (a, 5)`
			// `a = 5; [1, a]`
			// `a = 5; {a: a}`
			case $src->getExpr() instanceof Composite:
				$context2 = clone $context;
				$seconds = [];
				// 1/ Nejdříve zpracujeme bezpečné hodnoty
				foreach ($src->getLets() as $id => $value) {
					if (is_string($value)) {
						$context2->shadowAnotherSymbol($id, $value);
					}
					elseif ( ! $value instanceof HasRefs) {
						$context2->shadow($id, self::partialEvaluate($context, $value));
					}
					elseif ($value->refs() === []) {
						$context2->shadow($id, self::partialEvaluate($context, $value));
					}
					else {
						$seconds[$id] = $value;
					}
				}

				// 2/ Hodnoty, které šahají do rodičovského scope
				// @TODO Recurse
				foreach ($seconds as $id => $value) {
					$context2->shadow($id, self::partialEvaluate($context2, $value));
				}

				return self::partialEvaluate($context2, $src->getExpr());

			default:
				throw self::UnsupportedException('partial evaluate const scope', $src->getExpr());
		}
	}



	/**
	 * Potřebujeme zkopírovat hodnoty vnějšího kontextu.
	 * Argumenty překrývají vnější kontext a vnitřní kontext zase překryje argumenty.
	 * Výsledek je lambda = hodnota.
	 */
	private static function partialEvaluateLambda(Context $context, Lambda $src): Lambda
	{
		$context2 = clone $context;
		// @TODO Je to správně?
		foreach ($src->getArgs() as $id) {
			$context2->shadowByArg($id);
		}
		return new Lambda($src->getArgs(), self::partialEvaluate($context2, $src->getExpr()));
	}



	private static function partialEvaluateComposite(Context $context, Composite $src): Composite
	{
		if ($src->refs() === []) {
			return $src;
		}
		$items = [];
		foreach ($src->getItems() as $k => $x) {
			$items[$k] = self::partialEvaluate($context, $x);
		}

		switch ($src->type()) {
			case Composite::TypeList:
				return Composite::List_($items);

			case Composite::TypeDict:
				return Composite::Dict_($items);

			case Composite::TypeTuple:
				return Composite::Tuple_($items);

			default:
				throw new LogicException("Comming soon... (2026.02.18 16:30:34 CET): {$src}");
		}
	}



	/**
	 * Přeloží (zkompiluje) předzpracovaný AST do výsledné **runtime hodnoty**.
	 *
	 * Funkce přijímá již částečně vyhodnocený strom výrazů (AST), který byl
	 * upraven funkcí `partialEvaluate()`, a převádí jej do finální podoby,
	 * kterou lze přímo použít za běhu programu.
	 *
	 * Výsledkem může být:
	 *  - **konkrétní hodnota**, pokud je celý výraz známý už v době kompilace,
	 *  - nebo **funkce (uzávěr, lambda)**, která při pozdějším volání provede
	 *    samotné výpočty na základě dostupných parametrů a kontextu.
	 *
	 * Důležité je, že tato funkce **neprovádí výpočty** – pouze zkonstruuje
	 * reprezentaci, která tyto výpočty provede až při volání.
	 *
	 * Příklad:
	 *     AST: a + 1
	 *     Výsledek: funkce (context) => context["a"] + 1
	 *
	 * Cílem funkce je vytvořit efektivní a znovupoužitelnou runtime rutinu,
	 * která představuje konečnou podobu daného výrazu pro provádění v klientovi.
	 * @param Value | FinalVal $term
	 */
	private static function compileRuntimeValue(Value $term): Val
	{
		list($val, $binds) = self::castAny($term, True);
		switch (True) {
			case $val instanceof FinalVal:
				return $val;

			case $val instanceof BindVal:
				return VariadicVal::ShortLinkBind($val);

			case $val instanceof Expr:
				return VariadicVal::Expr_($val, '?', array_values($binds));

			case $val instanceof Composite && $val->type() === Composite::TypeDict:
				return VariadicVal::Dict_($val, array_values($binds));

			case $val instanceof Composite && $val->type() === Composite::TypeList:
				return VariadicVal::List_($val, array_values($binds));

			case $val instanceof Composite && $val->type() === Composite::TypeTuple:
				return VariadicVal::Tuple_($val, array_values($binds));

			default:
				throw self::UnsupportedException('compile value', $val);
		}
	}



	/**
	 * @param Value | Val $term
	 * @param bool $packref Když narazíme na symbol závislosti, tak nědy se nám nehodí, že se zabalí do BindVal
	 * @return array{0: Value, 1: array<string, BindVal>}
	 */
	private static function castAny($src, bool $packref): array
	{
		switch (True) {
			case $src instanceof FinalVal:
				return [$src, []];

			case $src instanceof Scalar:
				return self::castScalar($src);

			case $src instanceof Lambda:
				return self::castLambda($src);

			case $src instanceof Composite:
				return self::castComposite($src, $packref);

			case $src instanceof Expr:
				return self::castExpr($src, $packref);

			case is_string($src):
				if ($packref) {
					$x = new BindVal($src, "?");
					return [$x, [$x]];
				}
				return [$src, []];

			// Deadcode
			// `{a = 1; x}`
			// `{a = b; x}`
			case $src instanceof Scope && is_string($src->getExpr()):
				return self::castAny($src->getExpr(), $packref);

			default:
				throw self::UnsupportedException('casting', $src);
		}
	}



	/**
	 * @return array{0: FinalVal, 1: array<string, BindVal>}
	 */
	private static function castScalar(Scalar $val): array
	{
		if ($val->type() === 'Symbol' && $val->getValue() === 'True') {
			$value = True;
		}
		elseif ($val->type() === 'Symbol' && $val->getValue() === 'False') {
			$value = False;
		}
		elseif ($val->type() === 'Symbol' && $val->getValue() === 'Null') {
			$value = Null;
		}
		else {
			$value = $val->getValue();
		}
		return [new FinalVal($value, self::castType($val->type())), []];
	}



	/**
	 * @return array{0: VariadicVal, 1: array<string, BindVal>}
	 */
	private static function castLambda(Lambda $val): array
    {
		$binds = [];
		//~ foreach ($val->getArgs() as $x) {
			//~ $binds[$x] = new BindVal($x, '?');
		//~ }
		foreach ($val->refs() as $x) {
			$binds[$x] = new BindVal($x, '?');
		}
		return [VariadicVal::Expr_($val->getExpr(), '?', array_values($binds)), $binds];
    }



	/**
	 * Kompozitní hodnota může nebo nemusí obsahovat symboly a výrazy. V této
	 * fázy už jsme veškeré možnosti optimalizace vyčerpali a už to pouze přebalíme
	 * na čistou hodnotu, jde-li to, nebo na funkci, je-li to nutné.
	 *
	 * @return array{0: FinalVal | Composite, 1: array<string, BindVal>}
	 */
	private static function castComposite(Composite $src, bool $packref): array
	{
		// Zádné prvky, žádné problémy
		if ((array) $src->getItems() === []) {
			switch ($src->type()) {
				case Composite::TypeTuple:
					return [new FinalVal([], 'Tuple'), []];

				case Composite::TypeList:
					return [new FinalVal([], 'List'), []];

				case Composite::TypeDict:
					return [new FinalVal((object) [], 'Dict'), []];

				default:
					throw self::UnsupportedException('casting composite', $src);
			}
		}

		list($items, $lets) = self::castCompositeItems((array) $src->getItems(), $packref);
		switch ($src->type()) {
			case Composite::TypeTuple:
				return $src->refs() === []
					? [new FinalVal($items, 'Tuple'), []]
					: [Composite::Tuple_($items), $lets];

			case Composite::TypeList:
				return $src->refs() === []
					? [new FinalVal($items, 'List'), []]
					: [Composite::List_($items), $lets];

			case Composite::TypeDict:
				return $src->refs() === []
					? [new FinalVal((object) $items, 'Dict'), []]
					: [Composite::Dict_($items), $lets];

			default:
				throw self::UnsupportedException('casting composite', $src);
		}
	}



	/**
	 * @return array{0: Value, 1: array<string, BindVal>}
	 */
	private static function castExpr(Expr $src, bool $packref): array
	{
		list($items, $lets) = self::castCompositeItems($src->getItems(), $packref);
		if ($src->refs() === []) {
			throw new LogicException("Comming soon... (2026.02.20 02:49:33 CET)");
		}
		switch ($src->getNotation()) {
			case Expr::NotationInfix:
				return [Expr::Bin_($items[0], $items[1], $items[2]), $lets];

			case Expr::NotationPrefix:
				return [Expr::Func_($items[0], array_slice($items, 1)), $lets];

			default:
				throw new LogicException("oops.");
		}
	}



	/**
	 * @param array<string|int, string | Value> $src
	 * @return array{0: array<Val>, 1: array<string, BindVal>}
	 */
	private static function castCompositeItems(array $src, bool $packref): array
	{
		$lets = [];
		$items = [];
		foreach ($src as $k => $x) {
			if (is_string($x)) {
				$items[$k] = $packref
					? $lets[$x] = new BindVal($x, '?')
					: $x;
			}
			elseif ($x instanceof BuildinFunc) {
				$items[$k] = $x;
			}
			elseif ($x instanceof Lambda) {
				list($term, $lets2) = self::castAny($x, $packref);
				foreach ($x->getArgs() as $key) {
					unset($lets2[$key]);
				}
				$lets = array_merge($lets, $lets2);
				$items[$k] = $term;
			}
			else {
				list($x, $lets2) = self::castAny($x, $packref);
				$lets = array_merge($lets, $lets2);
				$items[$k] = $x;
			}
		}
		return [$items, $lets];
	}



	private static function castType(string $m): string
	{
		switch (strtoupper($m)) {
			case 'NUMBER':
			case 'INT':
				return 'Int';

			case 'REAL':
				return 'Real';

			case 'STRING':
			case 'STR':
				return 'Str';

			case 'SYMBOL':
				return 'Symbol';

			default:
				return 'Unknown';
		}
	}



	/**
	 * @param mixed $term
	 */
	private static function UnsupportedException(string $label, $term): LogicException
	{
		return new LogicException("Unsupported {$label} (" . (is_object($term)
					? get_class($term)
					: gettype($term)) . "): '{$term}'.");
	}

}
