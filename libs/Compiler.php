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
 * The result is PHP code that can be saved to a file and loaded via require. This caching should probably be optional.
 */
class Compiler
{

	/**
	 * @var array<string, SymbolProvider>
	 */
	private array $libs = [];

	/**
	 * Table of short names, like `+`, `div`, `and`, etc.
	 * @var array<string, string>
	 */
	private array $short = [];

	/**
	 * @param array<string, SymbolProvider> $libs
	 */
	function __construct(array $libs)
	{
		$this->libs = $libs;
		foreach ($libs as $ns => $prov) {
			if ($prov instanceof ShortSymbolProvider) {
				foreach ($prov->getShortSymbolTable() as $x) {
					$this->short[$x] = "{$ns}.{$x}";
				}
			}
		}
	}



	/**
	 * Returns a final value, or a function that needs to be filled with arguments.
	 * @return FinalValue | ParametricValue
	 */
	function compile(string $source)
	{
		$decoder = new HayoDecoder();
		$term = $decoder->decode($source);
		if ( ! $term instanceof Value) {
			// `a` -- returning argument
			if (is_string($term)) {
				return ParametricValue::ShortLinkBind(new BindVal($term, '?'));
			}
			throw new LogicException("Invalid source code.");
		}

		// Extract all dependencies. Try to resolve them; e.g. builtin functions, etc.
		// Those that cannot be resolved will remain as function parameters.
		$context = $this->createGlobalSymbols($term);

		// First phase: evaluate bound symbols. Compute everything that can be resolved statically.
		$term = self::partialEvaluate($context, $term);
		if ( ! $term instanceof Value) {
			throw new LogicException("Invalid source code.");
		}

		// Second phase: convert term -> val
		$term = self::compileRuntimeValue($term);
		//~ self::assertMissingSymbols($term);

		return $term;
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
	 * Retrieves global symbols such as math operators, string functions,
	 * fields, and user-defined functions.
	 *
	 * @return array{0: string, 1: Value}
	 */
	private function lookupGlobalSymbol(string $x): ?array
	{
		// In the source we have `+`, we look for `math.+`, but must return just `+`.
		// In the source we have `str.len`, we look for `str.len`, and return `str.len`.
		$nx = $this->normalizeShortSymbols($x);

		if ( ! strpos($nx, '.')) {
			return Null;
		}

		// Symbol consists of a namespace and a function name.
		// We assume exactly one dot. We do not nest namespaces. If needed,
		// that should be handled at the provider level.
		list($ns, $symbol) = explode('.', $nx, 2);
		if (isset($this->libs[$ns])) {
			if ($fn = $this->libs[$ns]->lookup($symbol)) {
				return [$x, $fn];
			}
		}

		return Null;
	}



	private function normalizeShortSymbols(string $x): string
	{
		if (strpos($x, '.')) {
			return $x;
		}

		if (isset($this->short[strtolower($x)])) {
			return $this->short[strtolower($x)];
		}

		return $x;
	}



	/**
	 * Performs **partial evaluation** of an expression.
	 *
	 * This function recursively traverses the expression tree (AST) and tries
	 * to evaluate all parts that can be determined in the current context.
	 *
	 * - If all operands of an expression are known (constants or values
	 *   available in the context), the expression is immediately computed and replaced with the result.
	 * - If only some operands are known, the function preserves the expression in its original
	 *   structure, substitutes the known values, and simplifies where possible.
	 * - If nothing can be evaluated, the expression remains unchanged.
	 *
	 * A typical example:
	 *     a = 10
	 *     expression: a * 2 + b
	 *
	 * After partial evaluation:
	 *     20 + b
	 *
	 * The goal is to reduce the complexity of the expression before full evaluation
	 * or compilation, thereby speeding up later execution.
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

			// Numbers, final values, and builtin functions have nothing to process
			case $term instanceof Scalar:
			case $term instanceof FinalVal:
			case $term instanceof BuildinFunc:
				return $term;

			// Dicts etc. may contain bound symbols or function calls, but those must be handled one level up, in Scope.
			case $term instanceof Composite:
				return self::partialEvaluateComposite($context, $term);

			//~ case $term instanceof BuildinFunc:
			// @TODO Room for optimization: Lambda cannot be fully executed because it depends on argument state.
			// But parts of the Expr might be. Depends on how complex the lambda is.
			case $term instanceof Lambda:
				return self::partialEvaluateLambda($context, $term);

			// Move all symbols from the local scope to their usage site; the symbol and scope then cease to exist.
			// Performs **partial evaluation** of an expression expected to produce a value.
			case $term instanceof Scope:
				return self::partialEvaluateScope($context, $term);

			// Could be a function call: `format(1 2 3)`, return value
			// Could be an operation: `1 + 1`, return value
			case $term instanceof Expr && $term->refs() === []:
				throw self::UnsupportedException('partial evaluate', $term);

			// Could be a function call: `format(1 a 3)`, since "a" is unknown, return a function.
			// Could be an operation: `1 + a`, since "a" is unknown, return a function.
			// Could be a predicate: `equals(1, 1) and a == 42`, since "a" is unknown, return a function.
			case $term instanceof Expr && $term->refs() !== []:
				return self::partialEvaluateExpr($context, $term);

			default:
				throw self::UnsupportedException('partial evaluate', $term);
		}
	}



	/**
	 * Performs **partial evaluation** of an expression expected to produce a lambda.
	 * Assumes all dependencies are resolved; those that are not are external.
	 *
	 * `1 + 1`
	 * `41 + a`
	 * `1 + (1 + a)`
	 * `strings.len a`
	 * `strings.split "," src`
	 * `list.at 2 src`
	 * `list.at 2 ["une", a, "trois"]`
	 *
	 * We pull bound symbols from the context. Before executing them, we must evaluate nested expressions.
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

				// There are some arguments
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
					return self::partialEvaluateApplicable($items[0], $args); // @phpstan-ignore argument.type, return.type
				}
				else {
					if ($items[0] instanceof Lambda
							&& count($items[0]->getArgs()) === (count($items) - 1)) {

						// @TODO Toto píšu poněkud unaven. Myslím, že by se to mělo řešit poněkud jinak.
						$term = self::optimalizeLambdaCalling($items[0], array_slice($items, 1));
					}
				}

				// There are some arguments
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
				return $fn->apply($args); // @phpstan-ignore argument.type

			default:
				throw new LogicException("oops.");
		}
	}



	/**
	 * Performs **partial evaluation** of an expression.
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

			// Nested scope is not supported.
			case $src->getExpr() instanceof Scope:
				throw self::UnsupportedException('partial evaluate const scope of scope', $src->getExpr());

			// `{1 + 1}` -- because addition is also a symbol -> `{+ = buildin; 1 + 1}`
			// `{a = 1; a + a}`
			case $src->getExpr() instanceof Expr:
				$context2 = clone $context;
				$seconds = [];
				// 1/ First process safe values
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

				// 2/ Values that reach into the parent scope
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
				// 1/ First process safe values
				foreach ($src->getLets() as $id => $value) {
					if (is_string($value)) {
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

				// 2/ Values that reach into the parent scope
				// @TODO Recurse
				foreach ($seconds as $id => $value) {
					$context2->shadow($id, self::partialEvaluate($context2, $value)); // @phpstan-ignore argument.type
				}

				return self::partialEvaluate($context2, $src->getExpr());

			default:
				throw self::UnsupportedException('partial evaluate const scope', $src->getExpr());
		}
	}



	/**
	 * We need to copy the values of the outer context.
	 * Arguments shadow the outer context, and the inner context shadows the arguments.
	 * The result is lambda = value.
	 */
	private static function partialEvaluateLambda(Context $context, Lambda $src): Lambda
	{
		$context2 = clone $context;
		// @TODO Is this correct?
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
	 * Translates (compiles) the pre-processed AST into the resulting **runtime value**.
	 *
	 * The function receives an already partially evaluated expression tree (AST), modified
	 * by `partialEvaluate()`, and converts it to its final form that can be used directly at runtime.
	 *
	 * The result may be:
	 *  - a **concrete value**, if the entire expression is known at compile time,
	 *  - or a **function (closure, lambda)**, which performs the actual computation
	 *    when called later based on available parameters and context.
	 *
	 * Importantly, this function **does not perform computations** – it only constructs
	 * a representation that will perform them when invoked.
	 *
	 * Example:
	 *     AST: a + 1
	 *     Result: function (context) => context["a"] + 1
	 *
	 * The goal is to create an efficient, reusable runtime routine representing
	 * the final form of the expression for execution in the client.
	 *
	 * @return ParametricValue | FinalVal
	 */
	private static function compileRuntimeValue(Value $src)
	{
		list($val, $binds) = self::castAny($src, True);
		switch (True) {
			case $val instanceof FinalVal:
				return $val;

			case $val instanceof BindVal:
				return ParametricValue::ShortLinkBind($val);

			case $val instanceof Expr:
				return ParametricValue::Expr_($val, '?', array_values($binds));

			case $val instanceof Composite && $val->type() === Composite::TypeDict:
				return ParametricValue::Dict_($val, array_values($binds));

			case $val instanceof Composite && $val->type() === Composite::TypeList:
				return ParametricValue::List_($val, array_values($binds));

			case $val instanceof Composite && $val->type() === Composite::TypeTuple:
				return ParametricValue::Tuple_($val, array_values($binds));

			default:
				throw self::UnsupportedException('compile value', $val);
		}
	}



	/**
	 * @param string | Value $src
	 * @param bool $packref When we encounter a dependency symbol, sometimes we don't want it wrapped in a BindValue
	 * @return array{0: Value, 1: array<string, BindValue>}
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
	 * @return array{0: ParametricValue, 1: array<string, BindVal>}
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
		return [ParametricValue::Expr_($val->getExpr(), '?', array_values($binds)), $binds];
    }



	/**
	 * A composite value may or may not contain symbols and expressions. At this
	 * stage all optimization opportunities are exhausted and we simply wrap it
	 * into a plain value if possible, or into a function if necessary.
	 *
	 * @return array{0: FinalVal | Composite, 1: array<string, BindVal>}
	 */
	private static function castComposite(Composite $src, bool $packref): array
	{
		// No elements, no problems
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
	 * @return array{0: array<Value>, 1: array<string, BindVal>}
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
