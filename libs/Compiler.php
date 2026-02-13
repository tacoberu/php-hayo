<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;
use InvalidArgumentException;


/**
 * PHP Compiler Hayo.
 * Výsledek je php kod, který se dá uložit do souboru, který se načíst pomocí require. Toto uložení by ale asi mělo být volitelné, jako cache.
 */
class Compiler
{

	/**
	 * @var list<SymbolProvider>
	 */
	private array $operators = [];

	/**
	 * @var list<SymbolProvider>
	 */
	private array $functions = [];

	/**
	 * @param list<SymbolProvider> $operators
	 * @param list<SymbolProvider> $functions
	 */
	function __construct(array $operators = [], array $functions = [])
	{
		$this->operators = array_merge([
			new BuildinMathOperatorProvider(),
		], $operators);
		$this->functions = array_merge([
			new BuildinStringFunctionProvider(),
			new BuildinListFunctionProvider(),
		], $functions);
	}



	/**
	 * Vrací konečnou hodnotu, nebo funkci, kterou je třeba naplnit argumenty.
	 */
	function compile(string $source): Val
	{
		$decoder = new HayoDecoder();
		$term = $decoder->decode($source);
		if ( ! $term instanceof Term) {
			throw new LogicException("Invalid source code.");
		}

		// Vytáhnu si všechny závislosti. Pokusím se je dohledat; například buildin funkce, a podobně.
		// A ty co nejsou zůstanou jako parametry funkce.
		$term = $this->appliesGlobalSymbols($term);

		// První fáze: vyhodnotíme nabindované symboly. Vypočítáme všechny věci, které jdou vypočítat staticky.
		$term = self::partialEvaluate($term);
		if ( ! $term instanceof Term) {
			throw new LogicException("Invalid source code.");
		}

		// Druhá váze: převedem term -> val
		return self::compileRuntimeValue($term);
	}



	/**
	 * Pokud term obsahuje nějaké symboli, vytáhnu si je z globálního uložiště
	 * a term převedu na Scope.
	 */
	private function appliesGlobalSymbols(Term $term): Term
	{
		// @var array<string, List>
		$lets = [];
		if ($term instanceof HasRefs) {
			foreach ($term->refs() as $x) {
				if (($symbol = $this->lookupGlobalSymbol($x)) instanceof Let) {
					$lets[] = $symbol;
				}
			}
		}
		if (count($lets)) {
            return new Scope($lets, $term);
        }
		return $term;
	}



	/**
	 * Vytahuje globální symboly, jako matematické opertáry, funkce pro práci
	 * s textem, poly, a uživatelsky definované funkce.
	 * @TODO Možnost lokálního importu.
	 */
	private function lookupGlobalSymbol(string $x): ?Let
	{
		if (self::is_operator($x)) {
			foreach ($this->operators as $provider) {
				if ($fn = $provider->lookup($x)) {
					return new Let($x, $fn);
				}
			}
			return Null;
		}

		foreach ($this->functions as $provider) {
			if ($fn = $provider->lookup($x)) {
				return new Let($x, $fn);
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
	 * @param Term | Val | string $term
	 * @return Term | string
	 */
	private static function partialEvaluate($term)
	{
		switch (True) {
			case is_string($term) && self::is_operator($term):
			case is_string($term) && self::is_bind($term):
			case $term instanceof Literal:
			case $term instanceof StructTuple:
			case $term instanceof StructList:
			case $term instanceof StructDict:
			case $term instanceof BuildinFunc:

			// @TODO Prostor pro optimalizaci: Labda se nedá vykonat celá, protože závisí na stavu argumentu.
			// ale části toho Expr by možná šli. Záleží jak moc je ta lambda košatá.
			case $term instanceof Lambda:
				return $term;

			// Všechny symboly z lokálního scope přesunout na místo užití, a následně symbol i scope zaniká.
			// Provede **částečné vyhodnocení** výrazu, u kterého očekáváme jako výsledek hodnotu.
			case $term instanceof Scope && $term->refs() === []:
				return self::partialEvaluateScope($term);

			// Některé symboly nejsou vyřešené, jsou to symboly dodané jako argumenty z vnějšku.
			// Provede **částečné vyhodnocení** výrazu, u kterého očekáváme jako výsledek funkci.
			// @TODO to vypadá jako funkce; zobecnit?
			case $term instanceof Scope && $term->refs() !== []:
				return self::partialEvaluateScope($term);

			// Může se jednat o volání funkce: `format(1 2 3)`, vrátíme hodnotu
			// Může se jednat o operaci: `1 + 1`, vrátíme hodnotu
			case $term instanceof Expr && $term->refs() === []:
				return self::partialEvaluateExprFinal($term);

			// Může se jednat o volání funkce: `format(1 a 3)`, protože "a" neznáme, vrátíme funkci.
			// Může se jednat o operaci: `1 + a`, protože "a" neznáme, vrátíme funkci.
			// Může se jednat o predikát: `equals(1, 1) and a == 42`, protože "a" neznáme, vrátíme funkci.
			case $term instanceof Expr && $term->refs() !== []:
				return self::partialEvaluateExpr($term);

			default:
				throw self::UnsupportedException('partial evaluate', $term);
		}
	}



	/**
	 * Provede **částečné vyhodnocení** výrazu, u kterého očekáváme jako výsledek lambdu.
	 * Očekáváme, že, všechny závislosti jsou vyřešeny, a ty které nejsou jsou vnější.
	 */
	private static function partialEvaluateExpr(Expr $term): Term
	{
		$items = $term->getItems();
		switch (True) {
			// operátor
			case count($items) === 3 && $items[1] instanceof BuildinFunc:
				throw new LogicException("Comming soon...");
				//~ $arg1 = array_shift($items);
				//~ $fn = array_shift($items);
				//~ $items = array_merge([$arg1], $items);
				//~ $items = array_map([self::class, 'compileRuntimeValue'], $items);

				//~ return $fn->apply(self::combineBindWithValues($fn, $items));

			// funkce
			case isset($items[0]) && $items[0] instanceof BuildinFunc:
				foreach ($items as $i => $x) {
					if ( ! is_string($x) && ! $x instanceof BuildinFunc) {
						list($x, ) = self::castAny($x, False);
						$items[$i] = $x;
					}
				}
				return new Expr($items);

			default:
				throw self::UnsupportedException('partial evaluate expr of term', $term);
		}
	}



	/**
	 * Zpracování volání funkce. Očekáváme konečnou hodnotu.
	 *
	 * `41 + 3`
	 * `strings.len "hi"`
	 * `strings.split "," "une, deux, trois"`
	 * `list.at 2 ["une", "deux", "trois"]`
	 */
	private static function partialEvaluateExprFinal(Expr $term): Term
	{
		$items = $term->getItems();
		switch (True) {
			// operátor
			case count($items) === 3 && $items[1] instanceof BuildinFunc:
				$arg1 = array_shift($items);
				$fn = array_shift($items);
				$items = array_map([self::class, 'compileRuntimeValue'], array_merge([$arg1], $items));
				return $fn->apply(self::combineBindWithValues($fn, $items));

			// operátor
			case isset($items[0]) && $items[0] instanceof BuildinFunc:
				$fn = array_shift($items);
				$items = array_map([self::class, 'compileRuntimeValue'], $items);
				return $fn->apply(self::combineBindWithValues($fn, $items));

			default:
				throw self::UnsupportedException('partial evaluate const expr', $term);
		}
	}



	/**
	 * Provede **částečné vyhodnocení** výrazu.
	 * @return Term | string
	 */
	private static function partialEvaluateScope(Scope $term)
	{
		switch (True) {
			case $term->getTerm() instanceof Scope:
				$lets = array_merge($term->getLets(), $term->getTerm()->getLets());
				return self::partialEvaluateScope(new Scope($lets, $term->getTerm()->getTerm()));

			case $term->getTerm() instanceof Expr:
				// @TODO A co konstrukce v Let, ty jsou vyrenderované?
				return self::partialEvaluate(new Expr(self::partialEvaluateItems($term)));

			case $term->getTerm() instanceof StructDict:
				return self::partialEvaluate(new StructDict(self::partialEvaluateItems($term)));

			case $term->getTerm() instanceof StructList:
				return self::partialEvaluate(new StructList(self::partialEvaluateItems($term)));

			case $term->getTerm() instanceof StructTuple:
				return self::partialEvaluate(new StructTuple(self::partialEvaluateItems($term)));

			//~ case $term->getTerm() instanceof BuildinFunc:
				//~ $fn = $term->getTerm();
				//~ foreach ($fn->refs() as $x) {
					//~ $args[$x] = $term->requireSymbol($x);
				//~ }

			default:
				throw self::UnsupportedException('partial evaluate const scope', $term->getTerm());
		}
	}



	/**
	 * @return array<string|int, Term|string>
	 */
	private static function partialEvaluateItems(Scope $parent): array
	{
		$xs = [];
		foreach ($parent->getTerm()->getItems() as $k => $x) {
			$xs[$k] = self::partialEvaluateItem($parent, $x);
		}
		return $xs;
	}



	/**
	 * @param Term | string $term
	 * @return Term | string
	 */
	private static function partialEvaluateItem(Scope $parent, $term)
	{
		if (is_string($term) && $ref = $parent->selectSymbol($term)) {
			$term = $ref;
		}
		if ( ! is_string($term) && $term instanceof HasRefs && count($term->refs())) {
			$term = new Scope($parent->getLets(), $term);
		}
		return self::partialEvaluate($term);
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
	 * @param Term | FinalVal $term
	 */
	private static function compileRuntimeValue(Term $term): Val
	{
		list($val, $binds) = self::castAny($term, True);
		switch (True) {
			case $val instanceof FinalVal:
				return $val;

			case $val instanceof Expr:
				return VariadicVal::expr($val, '?', array_values($binds));

			case $val instanceof StructDict:
				return VariadicVal::dict($val, array_values($binds));

			case $val instanceof StructList:
				return VariadicVal::list_($val, array_values($binds));

			case $val instanceof StructTuple:
				return VariadicVal::tuple_($val, array_values($binds));

			default:
				throw self::UnsupportedException('compile value', $term);
		}
	}



	/**
	 * @param Term | Val $term
	 * @param bool $packref Když narazíme na symbol závislosti, tak nědy se nám nehodí, že se zabalí do BindVal
	 * @return array{0: Term, 1: array<string, BindVal>}
	 */
	private static function castAny($term, bool $packref): array
	{
		switch (True) {
			case $term instanceof Literal:
				return self::castLiteral($term);

			case $term instanceof Lambda:
				return self::castLambda($term);

			case $term instanceof StructTuple:
				return self::castStructTuple($term, $packref);

			case $term instanceof StructList:
				return self::castStructList($term, $packref);

			case $term instanceof StructDict:
				return self::castStructDict($term, $packref);

			case $term instanceof Expr:
				return self::castExpr($term, $packref);

			case $term instanceof FinalVal:
				return [$term, []];

			default:
				throw self::UnsupportedException('casting of term', $term);
		}
	}



	private static function is_bind(string $m): bool
	{
		return (bool) preg_match('~[a-z][a-zA-Z0-9\_]*~', $m);
	}



	private static function is_operator(string $m): bool
    {
        // Mathematic
        return in_array($m, ['+', '-', '*', 'div', 'mod'], True);
    }



	/**
	 * @return array{0: FinalVal, 1: array<string, BindVal>}
	 */
	private static function castLiteral(Literal $val): array
	{
		return [new FinalVal($val->getValue(), self::castType($val->type())), []];
	}



	/**
	 * @return array{0: FinalVal, 1: array<string, BindVal>}
	 */
	private static function castLambda(): array
    {
        //~ $args = [];
        //~ foreach ($val->getArgs() as $arg) {
        //~ $args[] = is_string($arg)
        //~ ? new BindVal($arg, '?')
        //~ : self::compileRuntimeValue($arg);
        //~ }
        throw new LogicException("Comming soon...");
    }



	/**
	 * @return array{0: Term, 1: array<string, BindVal>}
	 */
	private static function castStructTuple(StructTuple $src, bool $packref): array
	{
		list($items, $lets) = self::castStructItems($src->getItems(), $packref);
		return $src->refs() === []
			? [new FinalVal($items, 'Tuple'), $lets]
			: [new StructTuple($items), $lets];
	}



	/**
	 * @return array{0: Term, 1: array<string, BindVal>}
	 */
	private static function castStructList(StructList $src, bool $packref): array
	{
		list($items, $lets) = self::castStructItems($src->getItems(), $packref);
		return $src->refs() === []
			? [new FinalVal($items, 'List'), $lets]
			: [new StructList($items), $lets];
	}



	/**
	 * @return array{0: Term, 1: array<string, BindVal>}
	 */
	private static function castStructDict(StructDict $src, bool $packref): array
	{
		list($items, $lets) = self::castStructItems($src->getItems(), $packref);
		return $src->refs() === []
			? [new FinalVal((object) $items, 'Dict'), $lets]
			: [new StructDict($items), $lets];
	}



	/**
	 * První element je vždy operátor/funkce.
	 * @return array{0: Term, 1: array<string, BindVal>}
	 */
	private static function castExpr(Expr $src, bool $packref): array
	{
		list($items, $lets) = self::castStructItems($src->getItems(), $packref);
		return [new Expr($items), $lets];
	}



	/**
	 * @param array<string|int, string | Term> $src
	 * @return array{0: array<Val>, 1: array<string, BindVal>}
	 */
	private static function castStructItems(array $src, bool $packref): array
	{
		$lets = [];
		$items = [];
		foreach ($src as $k => $x) {
			if (is_string($x)) {
				if ($packref) {
					$lets[$x] = $items[$k] = new BindVal($x, '?');
				}
				else {
					$items[$k] = $x;
				}
			}
			elseif ($x instanceof BuildinFunc) {
				$items[$k] = $x;
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
			case 'STRING':
			case 'STR':
				return 'Str';
			default:
				return 'Unknown';
		}
	}



	/**
	 * Funkce má svou signaturu argumentů.
	 * Ve $values máme hodnoty těchto argumentů.
	 * Spojíme je podle indexů.
	 *
	 * @param list<Val> $values
	 * @return array<strign, Val>
	 */
	private static function combineBindWithValues(BuildinFunc $fn, array $values): array
	{
		$refs = array_map(static function (BindVal $x): string {
			return $x->getBindName();
		}, $fn->getBinds());

		if (count($refs) !== count($values)) {
			$expected = count($refs);
			$passed = count($values);
			throw new InvalidArgumentException("Too few arguments to function {$fn}, {$passed} passed and exactly {$expected} expected.");
		}
		return array_combine($refs, $values);
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
