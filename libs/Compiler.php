<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;
use Nette\Utils\Validators;


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

		// @var array<string, List>
		$lets = [];

		// Vytáhnu si všechny závislosti. Pokusím se je dohledat; například buildin funkce, a podobně. A ty co nejsou zůstanou jako parametry funkce.
		if ($term instanceof Expr && count($term->refs())) {
			$term = self::repackExprWith($term, [], $this);
		}
		elseif ($term instanceof HasRefs) {
			foreach ($term->refs() as $x) {
				if ($symbol = $this->resolveGlobalSymbols($x)) {
					$lets[$symbol->getSymbol()] = $symbol;
				}
			}
		}

		// První fáze: vyhodnotíme nabindované symboly. Vypočítáme všechny věci, které jdou vypočítat staticky.
		$term = self::partialEvaluate($term, $lets);
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
	 * @param array<string, Let> $lets
	 */
	private static function repackExprWith(Expr $term, array $lets, self $self): Expr
	{
		// @var array<string, List>
		$lets = [];
		foreach ($term->refs() as $x) {
			if ($symbol = $self->resolveGlobalSymbols($x)) {
				$lets[$symbol->getSymbol()] = $symbol;
			}
		}
		return new Expr($term->getItems(), array_merge($term->getLets(), $lets));
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
	 * @param array<string, Let> $lets
	 * @return Term | string
	 */
	private static function partialEvaluate($term, array $lets)
	{
		switch (True) {
			case is_string($term) && self::is_operator($term):
				$term = $lets[$term] ?? throw new LogicException("Symbol '{$term}' is not found.");
				return $term->getTerm();

			case is_string($term) && self::is_bind($term):
				if (isset($lets[$term])) {
					$term = $lets[$term]->getTerm();
					if ($term instanceof Expr) {
						$term = new Expr($term->getItems(), $lets);
					}
					$term = self::partialEvaluate($term, $lets);
				}
				return $term;

			case $term instanceof Literal:
				return $term;

			// Může se jednat o volání funkce: `format(1 2 3)`, vrátíme výsledek
			// Může se jednat o operaci: `1 + 1`, vrátíme výsledek
			case $term instanceof Expr && $term->refs() === []:
				return self::partialEvaluateConstExpr($term);

			// Může se jednat o volání funkce: `format(1 a 3)`, protoře "a" neznáme, vrátíme funkci.
			// Může se jednat o operaci: `1 + a`, vrátíme protoře "a" neznáme, vrátíme funkci.
			// Může se jednat o predikát: `equals(1, 1) and a == 42`, protoře "a" neznáme, vrátíme funkci.
			case $term instanceof Expr && $term->refs() !== []:
				return self::partialEvaluateExpr($term);

			case $term instanceof StructTuple && $term->refs() === []:
			case $term instanceof StructList && $term->refs() === []:
			case $term instanceof StructDict && $term->refs() === []:
				return $term;

			case $term instanceof StructDict && $term->refs() !== []:
				return self::partialEvaluateStructDict($term, $lets);

			case $term instanceof StructList && $term->refs() !== []:
				return self::partialEvaluateStructList($term, $lets);

			case $term instanceof StructTuple && $term->refs() !== []:
				return self::partialEvaluateStructTuple($term, $lets);

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
	 */
	private static function partialEvaluateConstExpr(Expr $term): Term | FinalVal
	{
		$operator = Null;
		$xs = [];
		foreach ($term->getItems() as $node) {
			if (is_string($node) && self::is_operator($node)) {
				$operator = self::partialEvaluate($node, $term->getLets());
			}
			elseif ($node instanceof Expr) {
				// Pokud má podřízený prvek nějaké navázané symboly, tak přepíšou ty z rodiče.
				$node = new Expr($node->getItems(), array_merge($term->getLets(), $node->getLets()));
				$xs[] = self::partialEvaluate($node, []);
			}
			else {
				$xs[] = self::partialEvaluate($node, $term->getLets());
			}
		}

		if ($operator instanceof BuildinFunc) {
			$xs = array_map([self::class, 'compileRuntimeValue'], $xs);
			return $operator->apply(self::combineBindWithValues($operator, $xs));// @phpstan-ignore argument.type
		}

		if (count($xs) === 1) {
			return reset($xs); // @phpstan-ignore return.type
		}

		if ($xs[0] instanceof BuildinFunc) {
			$fn = array_shift($xs);
			$xs = array_map([self::class, 'compileRuntimeValue'], $xs);
			$val = $fn->apply($xs); // @phpstan-ignore method.nonObject
			if (is_scalar($val)) {
				return new Literal($val, '?');
			}
			if (Validators::isList($val)) {
				return new StructList($val);
			}
			if (is_array($val)) {
				return new StructDict($val);
			}
			if ($val instanceof FinalVal) {
				return $val;
			}

			throw new LogicException("Comming soon...");
		}

		throw new LogicException("Comming soon...");
	}



	/**
	 * Provede **částečné vyhodnocení** výrazu, u kterého očekáváme jako výsledek lambdu.
	 */
	private static function partialEvaluateExpr(Expr $term): Term
	{
		$operator = Null;
		$xs = [];
		foreach ($term->getItems() as $node) {
			if (is_string($node) && self::is_operator($node)) {
				$operator = self::partialEvaluate($node, $term->getLets());
			}
			elseif (is_string($node) && self::is_bind($node)) {
				$xs[] = self::partialEvaluate($node, $term->getLets());
			}
			elseif ($node instanceof Expr) {
				// Pokud má podřízený prvek nějaké navázané symboly, tak přepíšou ty z rodiče.
				$node = new Expr($node->getItems(), array_merge($term->getLets(), $node->getLets()));
				$xs[] = self::partialEvaluate($node, []);
			}
			else {
				$xs[] = self::partialEvaluate($node, $term->getLets());
			}
		}

		if (is_string($operator)) {
			throw new SymbolNotFound("Unresolved symbol: '{$operator}'.");
		}

		// Operátor přesuneme na začátek, aby se choval jako prostá funkce.
		if ($operator) {
			return new Expr(array_merge([$operator], $xs)); // @phpstan-ignore argument.type
		}

		if (is_string($xs[0])) {
			throw new SymbolNotFound("Unresolved symbol: '{$xs[0]}'.");
		}

		return new Expr($xs/*, $term->getLets()*/);// @phpstan-ignore argument.type
	}



	/**
	 * @param array<string, Let> $lets
	 */
	private static function partialEvaluateStructTuple(StructTuple $term, array $lets): StructTuple
	{
		$xs = [];
		foreach ($term->getItems() as $i => $node) {
			if (is_string($node) && self::is_operator($node)) {
				throw new LogicException("Comming soon...");
			}
			elseif ($node instanceof Expr) {
				// Pokud má podřízený prvek nějaké navázané symboly, tak přepíšou ty z rodiče.
				$node = new Expr($node->getItems(), array_merge($lets, $node->getLets()));
				$xs[$i] = self::partialEvaluate($node, []);
			}
			else {
				$xs[$i] = self::partialEvaluate($node, $lets);
			}
		}

		return new StructTuple($xs);// @phpstan-ignore argument.type
	}



	/**
	 * @param array<string, Let> $lets
	 */
	private static function partialEvaluateStructList(StructList $term, array $lets): StructList
	{
		$xs = [];
		foreach ($term->getItems() as $i => $node) {
			if (is_string($node) && self::is_operator($node)) {
				throw new LogicException("Comming soon...");
			}
			elseif ($node instanceof Expr) {
				// Pokud má podřízený prvek nějaké navázané symboly, tak přepíšou ty z rodiče.
				$node = new Expr($node->getItems(), array_merge($lets, $node->getLets()));
				$xs[$i] = self::partialEvaluate($node, []);
			}
			else {
				$xs[$i] = self::partialEvaluate($node, $lets);
			}
		}

		return new StructList($xs);// @phpstan-ignore argument.type
	}



	/**
	 * @param array<string, Let> $lets
	 */
	private static function partialEvaluateStructDict(StructDict $term, array $lets): StructDict
	{
		$xs = [];
		foreach ($term->getItems() as $name => $node) {
			if (is_string($node) && self::is_operator($node)) {
				throw new LogicException("Comming soon...");
			}
			elseif ($node instanceof Expr) {
				// Pokud má podřízený prvek nějaké navázané symboly, tak přepíšou ty z rodiče.
				$node = new Expr($node->getItems(), array_merge($lets, $node->getLets()));
				$xs[$name] = self::partialEvaluate($node, []);
			}
			else {
				$xs[$name] = self::partialEvaluate($node, $lets);
			}
		}

		return new StructDict($xs);// @phpstan-ignore argument.type
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
		switch (True) {
			case $term instanceof Literal:
				return self::castLiteral($term);

			case $term instanceof Lambda:
				return self::castLambda($term);

			case $term instanceof StructTuple:
				return self::castStructTuple($term);

			case $term instanceof StructList:
				return self::castStructList($term);

			case $term instanceof StructDict:
				return self::castStructDict($term);

			case $term instanceof Expr:
				return self::castExpr($term);

			case $term instanceof FinalVal:
				return $term;

			default:
				throw new LogicException("Unsupported term (" . (is_object($term) ? get_class($term) : gettype($term)) . "): '{$term}'."); // @phpstan-ignore function.alreadyNarrowedType, encapsedStringPart.nonString
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
		$args = [];
		foreach ($val->getArgs() as $arg) {
			$args[] = is_string($arg)// @phpstan-ignore function.alreadyNarrowedType
				? new BindVal($arg, '?')
				: self::compileRuntimeValue($arg);
		}

		throw new LogicException("Comming soon...");
		/*
		return new VariadicVal($val->getExpr()
			, self::castType($val->type())
			, $args);
			*/
	}



	private static function castStructTuple(StructTuple $src): Val
	{
		if (empty($src->refs())) {
			$items = [];
			foreach ($src->getItems() as $x) {
				$items[] = is_string($x)
					? new BindVal($x, '?')
					: self::compileRuntimeValue($x); // @phpstan-ignore argument.type
			}
			return new FinalVal($items, 'Tuple');
		}
		throw new LogicException("Comming soon...");
	}



	private static function castStructList(StructList $src): Val
	{
		if (empty($src->refs())) {
			$items = [];
			foreach ($src->getItems() as $x) {
				$items[] = is_string($x)
					? new BindVal($x, '?')
					: self::compileRuntimeValue($x); // @phpstan-ignore argument.type
			}
			return new FinalVal($items, 'List');
		}
		throw new LogicException("Comming soon...");
	}



	private static function castStructDict(StructDict $src): Val
	{
		if (empty($src->refs())) {
			$items = [];
			foreach ($src->getItems() as $k => $x) {
				$items[$k] = is_string($x)
					? new BindVal($x, '?')
					: self::compileRuntimeValue($x); // @phpstan-ignore argument.type
			}
			return new FinalVal((object) $items, 'Dict');
		}

		$items = [];
		foreach ($src->getItems() as $k => $x) {
			$items[$k] = is_string($x)
				? new BindVal($x, '?')
				: self::compileRuntimeValue($x); // @phpstan-ignore argument.type
		}

		$lets = [];
		foreach ($src->refs() as $x) {
			$lets[] = isset($items[$x])
				? $items[$x]
				: new BindVal($x, '?');
		}

		return VariadicVal::dict(new StructDict($items), 'Dict'
			, $lets); // @phpstan-ignore argument.type
	}



	/**
	 * První element je vždy operátor/funkce.
	 */
	private static function castExpr(Expr $src): VariadicVal
	{
		$xs = [];
		foreach ($src->getItems() as $x) {
			switch (True) {
				case is_string($x):
				case $x instanceof BuildinFunc:
					$xs[] = $x;
					break;

				case $x instanceof Literal:
					$xs[] = self::castLiteral($x);
					break;

				case $x instanceof StructTuple:
					$xs[] = self::castStructTuple($x);
					break;

				case $x instanceof StructList:
					$xs[] = self::castStructList($x);
					break;

				case $x instanceof StructDict:
					$xs[] = self::castStructDict($x);
					break;

				case $x instanceof Expr:
					$xs[] = self::castExpr($x);
					break;

				default:
					dump($x);
					throw new LogicException("Unsupported term (" . (is_object($x)
						? get_class($x)
						: gettype($term)) . "): '{$x}'."); // @phpstan-ignore encapsedStringPart.nonString
			}
		}

		$lets = array_map(static function($x) {
			return new BindVal($x, '?');
		}, $src->refs());
		return VariadicVal::expr(new Expr($xs), '?', $lets);
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
	 * @param list<Val> $values
	 * @return array<strign, Val>
	 */
	private static function combineBindWithValues(BuildinFunc $fn, array $values): array
	{
		$refs = array_map(static function($x) {
			return $x->getBindName();
		}, $fn->getBinds());

		return array_combine($refs, $values);
	}

}
