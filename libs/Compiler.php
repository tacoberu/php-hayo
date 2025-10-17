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



	function compile(string $source): Val
	{
		$decoder = new HayoDecoder();
		$term = $decoder->decode($source);

		// Vytáhnu si všechny závislosti. Pokusím se je dohledat; například buildin funkce, a podobně.
		// A ty co nejsou zůstanou jako parametry funkce.
		// @var array<string, List>
		$lets = [];
		if ($term instanceof HasRefs) {
			foreach ($term->refs() as $x) {
				if ($symbol = $this->resolveGlobalSymbols($x)) {
					$lets[] = $symbol;
				}
			}
		}
		if (count($lets)) {
			if ($term instanceof Term) {
				$term = new Scope($lets, $term);
			}
			else {
				throw new LogicException("Comming soon...");
			}
		}

		// První fáze: vyhodnotíme nabindované symboly. Vypočítáme všechny věci, které jdou vypočítat staticky.
		$term = self::partialEvaluate($term);

		if (is_string($term)) {
			throw new LogicException("Comming soon...");
		}

		// Druhá váze: převedem term -> val
		return self::compileRuntimeValue($term);
	}



	private function resolveGlobalSymbols(string $op): ?Let
	{
		if (self::is_operator($op)) {
			foreach ($this->operators as $provider) {
				if ($fn = $provider->lookup($op)) {
					return new Let($op, $fn);
				}
			}
			return Null;
		}

		foreach ($this->functions as $provider) {
			if ($fn = $provider->lookup($op)) {
				return new Let($op, $fn);
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
				return $term;

			case $term instanceof Scope && $term->refs() === []:
				return self::partialEvaluateConstScope($term);

			case $term instanceof Scope && $term->refs() !== []:
				return self::partialEvaluateScope($term);

			// Může se jednat o volání funkce: `format(1 2 3)`, vrátíme výsledek
			// Může se jednat o operaci: `1 + 1`, vrátíme výsledek
			case $term instanceof Expr && $term->refs() === []:
				return self::partialEvaluateConstExpr_2($term);

			// Může se jednat o volání funkce: `format(1 a 3)`, protoře "a" neznáme, vrátíme funkci.
			// Může se jednat o operaci: `1 + a`, vrátíme protoře "a" neznáme, vrátíme funkci.
			// Může se jednat o predikát: `equals(1, 1) and a == 42`, protoře "a" neznáme, vrátíme funkci.
			case $term instanceof Expr && $term->refs() !== []:
				return self::partialEvaluateExpr($term);

			case $term instanceof StructTuple && $term->refs() === []:
			case $term instanceof StructList && $term->refs() === []:
			case $term instanceof StructDict && $term->refs() === []:
			case $term instanceof StructDict && $term->refs() !== []:
			case $term instanceof StructList && $term->refs() !== []:
			case $term instanceof StructTuple && $term->refs() !== []:
				return $term;

			case $term instanceof BuildinFunc:
				return $term;

			// @TODO Prostor pro optimalizaci: Labda se nedá vykonata celá, protože závisí na stavu argumentu.
			// ale části toho Expr by možná šli. Záleží jak moc je ta lambda košatá.
			case $term instanceof Lambda:
				return $term;

			default:
				throw new LogicException("Unsupported term (" . (is_object($term) ? get_class($term) : gettype($term)) . "): '{$term}'."); // @phpstan-ignore encapsedStringPart.nonString
		}
	}



	/**
	 * Provede **částečné vyhodnocení** výrazu, u kterého očekáváme jako výsledek konstantu.
	 * @return Term | string
	 */
	private static function partialEvaluateConstScope(Scope $term)
	{
		switch (True) {
			case $term->getTerm() instanceof Scope:
				$lets = array_merge($term->getLets(), $term->getTerm()->getLets());
				$x = new Scope($lets, $term->getTerm()->getTerm());
				return self::partialEvaluateConstScope($x);

			case $term->getTerm() instanceof Expr:
				$lets = $term->getLets();
				// @TODO A co konstrukce v Let, ty jsou vyrenderované?
				$xs = [];
				foreach ($term->getTerm()->getItems() as $x) {
					if (is_string($x)) {
						$x = $term->requireSymbol($x);
					}
					if ($x instanceof HasRefs && count($x->refs())) {
						$x = new Scope($lets, $x);
					}
					$xs[] = self::partialEvaluate($x);
				}
				return self::partialEvaluate(new Expr($xs));

			case $term->getTerm() instanceof StructDict:
				$lets = $term->getLets();
				$xs = [];
				foreach ($term->getTerm()->getItems() as $prop => $x) {
					if (is_string($x)) {
						$x = $term->requireSymbol($x);
					}
					if ($x instanceof HasRefs && count($x->refs())) {
						$x = new Scope($lets, $x);
					}
					$xs[$prop] = self::partialEvaluate($x);
				}
				return self::partialEvaluate(new StructDict($xs));

			case $term->getTerm() instanceof StructList:
				$lets = $term->getLets();
				$xs = [];
				foreach ($term->getTerm()->getItems() as $i => $x) {
					if (is_string($x)) {
						$x = $term->requireSymbol($x);
					}
					if ($x instanceof HasRefs && count($x->refs())) {
						$x = new Scope($lets, $x);
					}
					$xs[$i] = self::partialEvaluate($x);
				}
				return self::partialEvaluate(new StructList($xs));

			case $term->getTerm() instanceof StructTuple:
				$lets = $term->getLets();
				$xs = [];
				foreach ($term->getTerm()->getItems() as $i => $x) {
					if (is_string($x)) {
						$x = $term->requireSymbol($x);
					}
					if ($x instanceof HasRefs && count($x->refs())) {
						$x = new Scope($lets, $x);
					}
					$xs[$i] = self::partialEvaluate($x);
				}
				return self::partialEvaluate(new StructTuple($xs));

			default:
				throw new LogicException("oops: {$term->getTerm()}");
		}
	}



	/**
	 * @return string | Term
	 */
	private static function partialEvaluateConstExpr_2(Expr $term)
	{
		$items = $term->getItems();
		// operátor
		if (count($items) === 3 && $items[1] instanceof BuildinFunc) {
			$arg1 = array_shift($items);
			$fn = array_shift($items);
			$items = array_merge([$arg1], $items);
			$items = array_map([self::class, 'compileRuntimeValue'], $items);
			return $fn->apply(self::combineBindWithValues($fn, $items));
		}
		// funkce
		elseif (isset($items[0]) && $items[0] instanceof BuildinFunc) {
			$fn = array_shift($items);
			$items = array_map([self::class, 'compileRuntimeValue'], $items);
 			return $fn->apply(self::combineBindWithValues($fn, $items));
		}
		else {
			throw new LogicException("oops: {$term}");
		}
	}



	private static function partialEvaluateScope(Scope $term)
	{
		switch (True) {
			case $term->getTerm() instanceof Scope:
				$lets = array_merge($term->getLets(), $term->getTerm()->getLets());
				$x = new Scope($lets, $term->getTerm()->getTerm());
				return self::partialEvaluateScope($x);

			case $term->getTerm() instanceof Expr:
				$lets = $term->getLets();
				// @TODO A co konstrukce v Let, ty jsou vyrenderované?
				$xs = [];
				foreach ($term->getTerm()->getItems() as $x) {
					if (is_string($x)) {
						if ($ref = $term->selectSymbol($x)) {
							$x = $ref;
						}
					}
					if (! is_string($x) && $x instanceof HasRefs && count($x->refs())) {
						$x = new Scope($lets, $x);
					}
					$xs[] = self::partialEvaluate($x);
				}
				return self::partialEvaluate(new Expr($xs));

			case $term->getTerm() instanceof StructDict:
				$lets = $term->getLets();
				$xs = [];
				foreach ($term->getTerm()->getItems() as $prop => $x) {
					if (is_string($x)) {
						$x = $term->requireSymbol($x);
					}
					if ($x instanceof HasRefs && count($x->refs())) {
						$x = new Scope($lets, $x);
					}
					$xs[$prop] = self::partialEvaluate($x);
				}
				//~ $x = self::partialEvaluate(new StructDict($xs));
				return new StructDict($xs);


			case $term->getTerm() instanceof StructList:
				$lets = $term->getLets();
				$xs = [];
				foreach ($term->getTerm()->getItems() as $i => $x) {
					if (is_string($x)) {
						$x = $term->requireSymbol($x);
					}
					if ($x instanceof HasRefs && count($x->refs())) {
						$x = new Scope($lets, $x);
					}
					$xs[$i] = self::partialEvaluate($x);
				}
				return new StructList($xs);

			case $term->getTerm() instanceof StructTuple:
				$lets = $term->getLets();
				$xs = [];
				foreach ($term->getTerm()->getItems() as $i => $x) {
					if (is_string($x)) {
						$x = $term->requireSymbol($x);
					}
					if ($x instanceof HasRefs && count($x->refs())) {
						$x = new Scope($lets, $x);
					}
					$xs[$i] = self::partialEvaluate($x);
				}
				return new StructTuple($xs);

			//~ case $term->getTerm() instanceof BuildinFunc:
				//~ $fn = $term->getTerm();
				//~ foreach ($fn->refs() as $x) {
					//~ $args[$x] = $term->requireSymbol($x);
				//~ }
//~ dump($args);
//~ die("\n------\n" . __file__ . ':' . __line__ . "\n");
//~ $items = array_map([self::class, 'compileRuntimeValue'], $items);
//~ return $fn->apply(self::combineBindWithValues($fn, $items));
			default:
				throw new LogicException("oops: {$term}");
		}
	}



	/**
	 * Provede **částečné vyhodnocení** výrazu, u kterého očekáváme jako výsledek lambdu.
	 * Očekáváme, že, všechny závislosti jsou vyřešeny, a ty které nejsou jsou vnější.
	 */
	private static function partialEvaluateExpr(Expr $term): Term
	{
		$items = $term->getItems();
		// operátor
		if (count($items) === 3 && $items[1] instanceof BuildinFunc) {
			throw new LogicException("Comming soon...");
			//~ $arg1 = array_shift($items);
			//~ $fn = array_shift($items);
			//~ $items = array_merge([$arg1], $items);
			//~ $items = array_map([self::class, 'compileRuntimeValue'], $items);

			//~ return $fn->apply(self::combineBindWithValues($fn, $items));
		}
		// funkce
		elseif (isset($items[0]) && $items[0] instanceof BuildinFunc) {
			foreach ($items as $i => $x) {
				if ( ! is_string($x) && ! $x instanceof BuildinFunc) {
					list($x, ) = self::castAny($x, False);
					$items[$i] = $x;
				}
			}
			return new Expr($items);
		}
		else {
			throw new LogicException("oops: {$term}");
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
	 * @param Term | FinalVal $term
	 */
	private static function compileRuntimeValue($term): Val
	{
		list($val, $binds) = self::castAny($term, True);

		if ($val instanceof FinalVal) {
			return $val;
		}
		if ($val instanceof Expr) {
			return VariadicVal::expr($val, '?', array_values($binds));
		}
		if ($val instanceof StructDict) {
			return VariadicVal::dict($val, array_values($binds));
		}
		if ($val instanceof StructList) {
			return VariadicVal::list_($val, array_values($binds));
		}
		if ($val instanceof StructTuple) {
			return VariadicVal::tuple_($val, array_values($binds));
		}
		throw new LogicException("Comming soon...");
	}



	/**
	 * @param Term | Val $term
	 * @param bool $packref Když narazíme na symbol závislosti, tak nědy se nám nehodí, že se zabalí do BindVal
	 * @return array{0: Term, 1: array<string, BindVal>}
	 */
	private static function castAny($term, bool $packref)
	{
		switch (True) {
			case $term instanceof Literal:
				return [self::castLiteral($term), []];

			case $term instanceof Lambda:
				throw new LogicException("Comming soon...");
				//~ return self::castLambda($term);

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
				throw new LogicException("Unsupported term (" . (is_object($term)
					? get_class($term)
					: gettype($term)) . "): '{$term}'.");
		}
	}



	private static function is_bind(string $m): bool
	{
		return (bool) preg_match('~[a-z][a-zA-Z0-9\_]*~', $m);
	}



	private static function is_operator(string $m): bool
	{
		// Mathematic
		if (in_array($m, ['+', '-', '*', 'div', 'mod'], True)) {
			return True;
		}
		return False;
	}



	private static function castLiteral(Literal $val): FinalVal
	{
		return new FinalVal($val->getValue(), self::castType($val->type()));
	}



	private static function castLambda(Lambda $val): VariadicVal
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
	private static function castStructTuple(StructTuple $src, bool $packref)
	{
		$lets = [];
		$items = [];
		foreach ($src->getItems() as $i => $x) {
			if (is_string($x)) {
				if ($packref) {
					$lets[$x] = $items[$i] = new BindVal($x, '?');
				}
				else {
					$items[$i] = $x;
				}
			}
			else {
				list($x, $lets2) = self::castAny($x, $packref);
				$lets = array_merge($lets, $lets2);
				$items[$i] = $x;
			}
		}
		if (empty($src->refs())) {
			return [new FinalVal($items, 'Tuple'), $lets];
		}
		return [new StructTuple($items), $lets];
	}



	/**
	 * @return array{0: Term, 1: array<string, BindVal>}
	 */
	private static function castStructList(StructList $src, bool $packref)
	{
		$lets = [];
		$items = [];
		foreach ($src->getItems() as $i => $x) {
			if (is_string($x)) {
				if ($packref) {
					$lets[$x] = $items[$i] = new BindVal($x, '?');
				}
				else {
					$items[$i] = $x;
				}
			}
			else {
				list($x, $lets2) = self::castAny($x, $packref);
				$lets = array_merge($lets, $lets2);
				$items[$i] = $x;
			}
		}
		if (empty($src->refs())) {
			return [new FinalVal($items, 'List'), $lets];
		}
		return [new StructList($items), $lets];
	}



	/**
	 * @return array{0: Term, 1: array<string, BindVal>}
	 */
	private static function castStructDict(StructDict $src, bool $packref)
	{
		$lets = [];
		$items = [];
		foreach ($src->getItems() as $k => $x) {
			if (is_string($x)) {
				if ($packref) {
					$lets[$x] = $items[$k] = new BindVal($x, '?');
				}
				else {
					$items[$k] = $x;
				}
			}
			else {
				list($x, $lets2) = self::castAny($x, $packref);
				$lets = array_merge($lets, $lets2);
				$items[$k] = $x;
			}
		}
		if (empty($src->refs())) {
			return [new FinalVal((object) $items, 'Dict'), $lets];
		}
		return [new StructDict($items), $lets];
	}



	/**
	 * První element je vždy operátor/funkce.
	 * @return array{0: Term, 1: array<string, BindVal>}
	 */
	private static function castExpr(Expr $src, bool $packref)
	{
		$items = [];
		$lets = [];
		foreach ($src->getItems() as $x) {
			if (is_string($x)) {
				if ($packref) {
					$lets[$x] = $items[] = new BindVal($x, '?');
				}
				else {
					$items[] = $x;
				}
			}
			elseif ($x instanceof BuildinFunc) {
				$items[] = $x;
			}
			else {
				list($x, $lets2) = self::castAny($x, $packref);
				$lets = array_merge($lets, $lets2);
				$items[] = $x;
			}
		}

		return [new Expr($items), $lets];
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
		$refs = array_map(static function($x) {
			return $x->getBindName();
		}, $fn->getBinds());

		if (count($refs) !== count($values)) {
			$expected = count($refs);
			$passed = count($values);
			throw new LogicException("Too few arguments to function {$fn}, {$passed} passed and exactly {$expected} expected.");
		}
		return array_combine($refs, $values);
	}

}
