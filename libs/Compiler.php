<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use DivisionByZeroError;
use InvalidArgumentException;


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
		$term = self::decodeSource($source);

		// @TODO Přesunout do decodeSource()
		if ( ! $term instanceof Value) {
			// `a` -- returning argument
			if (is_string($term)) {
				return ParametricValue::ShortLinkBind(new BindValue($term, '?'));
			}
			throw CompileException::InvalidSourceCode();
		}

		// Extract all dependencies. Try to resolve them; e.g. builtin functions, etc.
		// Those that cannot be resolved will remain as function parameters.
		$context = $this->createGlobalSymbols($term);

		// First phase: evaluate bound symbols. Compute everything that can be resolved statically.
		$term = self::partialEvaluate($context, $term);

		// Second phase: convert term -> val
		$term = self::compileRuntimeValue($term);
		self::assertMissingSymbols($term);

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
		if (isset($this->libs[$ns]) && $fn = $this->libs[$ns]->lookup($symbol)) {
			return [$x, $fn];
		}

		return Null;
	}



	private function normalizeShortSymbols(string $x): string
	{
		if (strpos($x, '.')) {
			return $x;
		}

		return $this->short[strtolower($x)] ?? $x;
	}



	private static function decodeSource(string $source)
	{
		$decoder = new HayoDecoder();
		try {
			return $decoder->decode($source);
		}
		catch (HayoParserException $e) {
			throw CompileException::HayoParser($e);
		}
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
			case is_string($term):
				return self::partialEvaluateSymbol($context, $term);

			// Numbers, final values, and builtin functions have nothing to process
			case $term instanceof Scalar:
			case $term instanceof FinalValue:
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
				throw CompileException::UnsupportedException('partial evaluate', $term);

			// Could be a function call: `format(1 a 3)`, since "a" is unknown, return a function.
			// Could be an operation: `1 + a`, since "a" is unknown, return a function.
			// Could be a predicate: `equals(1, 1) and a == 42`, since "a" is unknown, return a function.
			case $term instanceof Expr && $term->refs() !== []:
				return self::partialEvaluateExpr($context, $term);

			case $term instanceof Form && $term->getName() === 'if-then-else':
				return self::partialEvaluateFormIfThenElse($context, $term);

			default:
				throw CompileException::UnsupportedException('partial evaluate', $term);
		}
	}



	private static function partialEvaluateSymbol(Context $context, string $term)
	{
		// Check whether we have a Dict stored in the context; select by the first key in the path x.foo.doo
		$id = new BindValue($term, '?');
		if ($value = $context->selectSymbol($id->getName())) {
			if ($id->isPath()) {
				list($value, ) = self::castAny($value, False);
				return self::selectByPath($id, $value);
			}
			return $value;
		}

		// Builtin functions such as `str.len` also contain a dot
		if ($value = $context->selectSymbol($term)) {
			return $value;
		}

		return $term;
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
					try {
						return $items[1]->apply([ // @phpstan-ignore method.nonObject
							self::castAny($items[0], False)[0],
							self::castAny($items[2], False)[0],
							]);
					}
					catch (DivisionByZeroError | ScriptTypeException | InvalidArgumentException $e) {
						throw CompileException::EvaluationError($e);
					}
				}

				// Validate types of resolved operands against the operator signature
				if ($items[1] instanceof BuildinFunc) { // @phpstan-ignore instanceof.alwaysTrue
					try {
						TypeValidator::assertPartialArgTypes(
							$items[1]->getQualifiedName(),
							$items[1]->getBinds(),
							[self::castAny($items[0], False)[0], self::castAny($items[2], False)[0]]
						);
					}
					catch (InvalidArgumentException $e) {
						throw CompileException::EvaluationError($e);
					}
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
                    $expr = self::partialEvaluateApplicable($items[0], $args);
                    if (is_string($expr)) {
						throw CompileException::UnresolvedExpression($term);
					}
                    return $expr;
                }

				// Rovnou vyhodnotit
				if ($items[0] instanceof Lambda && count($items[0]->getArgs()) === count($items) - 1) {
                    $args = array_slice($items, 1);
                    $fn = $items[0];
                    $context = new Context(array_combine($fn->getArgs(), $args));
                    return self::partialEvaluateExpr($context, $fn->getExpr());
                }

				// Validate types of resolved args against the function signature (only full arity calls)
				if ($items[0] instanceof BuildinFunc) {
					$callArgs = array_values(array_slice($items, 1));
					if (count($callArgs) === count($items[0]->getBinds())) {
						try {
							TypeValidator::assertPartialArgTypes(
								$items[0]->getQualifiedName(),
								$items[0]->getBinds(),
								array_map(static function($x) { return self::castAny($x, False)[0]; }, $callArgs)
							);
						}
						catch (InvalidArgumentException $e) {
							throw CompileException::EvaluationError($e);
						}
					}
				}

				// There are some arguments
				return $term;

			default:
				throw CompileException::UnsupportedException('partial evaluate expr of term', $term);
		}
	}



	/**
	 * 1/ Condition is final and true -> only branch A is processed
	 * 2/ Condition is final and false -> only branch B is processed
	 * 3/ Condition is not final -> ....? both branches are processed, relying on absence of side-effects.
	 */
	private static function partialEvaluateFormIfThenElse(Context $context, Form $term): Value
	{
		$chains = [];
		foreach ($term->getItems() as $usecase) {
			$chains[] = (object) [
				'cond' => $usecase->cond ? self::partialEvaluate($context, $usecase->cond) : Null,
				'expr' => self::partialEvaluate($context, $usecase->expr),
			];
		}
		$else = array_pop($chains);
		return Form::IfThenElse_($chains, $else->expr);
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
				try {
					return $fn->apply($args); // @phpstan-ignore argument.type
				}
				catch (DivisionByZeroError | ScriptTypeException | InvalidArgumentException $e) {
					throw CompileException::EvaluationError($e);
				}

			default:
				throw CompileException::Unexpected();
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
				throw CompileException::UnsupportedException('partial evaluate const scope of scope', $src->getExpr());

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
				return self::partialEvaluate($context2, $term);

			// `a = 5; (a, 5)`
			// `a = 5; [1, a]`
			// `a = 5; {a: a}`
			case $src->getExpr() instanceof Composite:
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

				return self::partialEvaluate($context2, $src->getExpr());

			default:
				throw CompileException::UnsupportedException('partial evaluate const scope', $src->getExpr());
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
		foreach ($src->getArgs() as $id) {
			if ($id === false || $id === null) {
				throw CompileException::UnsupportedZeroArgLambda();
			}
			if ( ! is_string($id)) {
				throw CompileException::InvalidLambdaArguments();
			}
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
				throw CompileException::Unexpected();
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
	 * @return ParametricValue | FinalValue
	 */
	private static function compileRuntimeValue(Value $src)
	{
		list($val, $binds) = self::castAny($src, True);
		switch (True) {
			case $val instanceof FinalValue:
				return $val;

			case $val instanceof BindValue:
				return ParametricValue::ShortLinkBind($val);

			case $val instanceof Expr:
				return ParametricValue::Expr_($val, self::inferExprReturnType($val), array_values($binds));

			case $val instanceof Form:
				return ParametricValue::Form_($val, '?', array_values($binds));

			case $val instanceof Composite && $val->type() === Composite::TypeDict:
				return ParametricValue::Dict_($val, array_values($binds));

			case $val instanceof Composite && $val->type() === Composite::TypeList:
				return ParametricValue::List_($val, array_values($binds));

			case $val instanceof Composite && $val->type() === Composite::TypeTuple:
				return ParametricValue::Tuple_($val, array_values($binds));

			default:
				throw CompileException::UnsupportedException('compile value', $val);
		}
	}



	/**
	 * Infers the return type of a partially-evaluated expression from the operator's signature.
	 * Returns '?' when the operator is unknown or has a generic/polymorphic return type.
	 */
	private static function inferExprReturnType(Expr $expr): string
	{
		$items = $expr->getItems();
		switch ($expr->getNotation()) {
			case Expr::NotationInfix:
				if (isset($items[1]) && $items[1] instanceof BuildinFunc) {
					$t = $items[1]->type();
					return $t !== '' && $t !== 'a'
						? $t
						: '?';
				}
				return '?';

			case Expr::NotationPrefix:
				if (isset($items[0]) && $items[0] instanceof BuildinFunc) {
					$t = $items[0]->type();
					return $t !== '' && $t !== 'a'
						? $t
						: '?';
				}
				return '?';

			default:
				return '?';
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
			case $src instanceof FinalValue:
				return [$src, []];

			case $src instanceof Scalar:
				return self::castScalar($src);

			case $src instanceof Lambda:
				return self::castLambda($src);

			case $src instanceof Composite:
				return self::castComposite($src, $packref);

			case $src instanceof Expr:
				return self::castExpr($src, $packref);

			case $src instanceof Form && $src->getName() === 'if-then-else':
				return self::castFormIfThenElse($src, $packref);

			case is_string($src):
				if ($packref) {
					$x = new BindValue($src, "?");
					return [$x, [$x]];
				}
				return [$src, []];

			// Deadcode
			// `{a = 1; x}`
			// `{a = b; x}`
			case $src instanceof Scope && is_string($src->getExpr()):
				return self::castAny($src->getExpr(), $packref);

			default:
				throw CompileException::UnsupportedException('casting', $src);
		}
	}



	/**
	 * @return array{0: FinalValue, 1: array<string, BindValue>}
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
		return [new FinalValue($value, self::castType($val->type())), []];
	}



	/**
	 * @return array{0: ParametricValue, 1: array<string, BindValue>}
	 */
	private static function castLambda(Lambda $val): array
    {
		$binds = [];
		//~ foreach ($val->getArgs() as $x) {
			//~ $binds[$x] = new BindValue($x, '?');
		//~ }
		foreach ($val->refs() as $x) {
			$binds[$x] = new BindValue($x, '?');
		}
		$expr = $val->getExpr();
		if ($expr instanceof Form) {
			return [ParametricValue::Form_($expr, '?', array_values($binds)), $binds];
		}
		return [ParametricValue::Expr_($expr, '?', array_values($binds)), $binds];
    }



	/**
	 * A composite value may or may not contain symbols and expressions. At this
	 * stage all optimization opportunities are exhausted and we simply wrap it
	 * into a plain value if possible, or into a function if necessary.
	 *
	 * @return array{0: FinalValue | Composite, 1: array<string, BindValue>}
	 */
	private static function castComposite(Composite $src, bool $packref): array
	{
		// No elements, no problems
		if ((array) $src->getItems() === []) {
			switch ($src->type()) {
				case Composite::TypeTuple:
					return [new FinalValue([], 'Tuple'), []];

				case Composite::TypeList:
					return [new FinalValue([], 'List'), []];

				case Composite::TypeDict:
					return [new FinalValue((object) [], 'Dict'), []];

				default:
					throw CompileException::UnsupportedException('casting composite', $src);
			}
		}

		list($items, $lets) = self::castCompositeItems((array) $src->getItems(), $packref);
		switch ($src->type()) {
			case Composite::TypeTuple:
				return $src->refs() === []
					? [new FinalValue($items, 'Tuple'), []]
					: [Composite::Tuple_($items), $lets];

			case Composite::TypeList:
				return $src->refs() === []
					? [new FinalValue($items, 'List'), []]
					: [Composite::List_($items), $lets];

			case Composite::TypeDict:
				return $src->refs() === []
					? [new FinalValue((object) $items, 'Dict'), []]
					: [Composite::Dict_($items), $lets];

			default:
				throw CompileException::UnsupportedException('casting composite', $src);
		}
	}



	/**
	 * @return array{0: Value, 1: array<string, BindValue>}
	 */
	private static function castExpr(Expr $src, bool $packref): array
	{
		$typeHints = self::buildTypeHints($src);
		list($items, $lets) = self::castCompositeItems($src->getItems(), $packref, $typeHints);
		if ($src->refs() === []) {
			throw CompileException::Unexpected();
		}
		switch ($src->getNotation()) {
			case Expr::NotationInfix:
				return [Expr::Bin_($items[0], $items[1], $items[2]), $lets];

			case Expr::NotationPrefix:
				return [Expr::Func_($items[0], array_slice($items, 1)), $lets];

			default:
				throw CompileException::Unexpected();
		}
	}



	/**
	 * Builds a position-keyed map of expected parameter types from the operator/function
	 * in the expression. Used to annotate unresolved BindValues with inferred types.
	 *
	 * Infix: position 0 = left arg, position 1 = operator (skip), position 2 = right arg
	 * Prefix: position 0 = function (skip), positions 1..n = args 0..n-1
	 *
	 * @return array<int, BindValue|null>
	 */
	private static function buildTypeHints(Expr $src): array
	{
		$rawItems = $src->getItems();
		switch ($src->getNotation()) {
			case Expr::NotationInfix:
				if (isset($rawItems[1]) && $rawItems[1] instanceof BuildinFunc) {
					$sig = $rawItems[1]->getBinds();
					return [
						0 => $sig[0] ?? Null,
						2 => $sig[1] ?? Null,
					];
				}
				return [];

			case Expr::NotationPrefix:
				if (isset($rawItems[0]) && $rawItems[0] instanceof BuildinFunc) {
					$sig = $rawItems[0]->getBinds();
					$hints = [];
					foreach ($sig as $i => $bind) {
						$hints[$i + 1] = $bind;
					}
					return $hints;
				}
				return [];

			default:
				return [];
		}
	}



	/**
	 * @return array{0: Value, 1: array<string, BindValue>}
	 */
	private static function castFormIfThenElse(Form $src, bool $packref): array
	{
		$chains = [];
		$depends = [];
		foreach ($src->getItems() as $block) {
			if ($block->cond === Null) {
				list($elseexpr, $depends1) = self::castAny($block->expr, True);
				$depends = array_merge($depends, $depends1);
				return [Form::IfThenElse_($chains, $elseexpr), $depends];
			}
			list($block->cond, $depends1) = self::castAny($block->cond, True);
			$depends = array_merge($depends, $depends1);
			list($block->expr, $depends1) = self::castAny($block->expr, True);
			$depends = array_merge($depends, $depends1);
			$chains[] = $block;
		}
		throw CompileException::Unexpected();
	}



	/**
	 * @param array<string|int, string | Value> $src
	 * @param array<int|string, BindValue|null> $typeHints expected parameter types keyed by item position
	 * @return array{0: array<Value>, 1: array<string, BindValue>}
	 */
	private static function castCompositeItems(array $src, bool $packref, array $typeHints = []): array
	{
		$lets = [];
		$items = [];
		foreach ($src as $k => $x) {
			if (is_string($x)) {
				$inferredType = isset($typeHints[$k])
					? $typeHints[$k]->getTypeName()
					: '?';
				$items[$k] = $packref
					? $lets[$x] = new BindValue($x, $inferredType)
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
	 * If the bound value is a path: `x.foo`, we expect
	 * $src to be a dictionary and extract the correct value from it.
	 */
	private static function selectByPath(BindValue $id, FinalValue $src): FinalValue
	{
		//~ self::assertDict($src);
		$curr = (object)[
			$id->getName() => $src->unpack(),
		];
		foreach (explode('.', $id->getBindName()) as $x) {
			$curr = $curr->{$x};
		}
		return new FinalValue($curr, '?');
	}



	private static function assertMissingSymbols($term): void
	{
		if ($term instanceof FinalValue) {
			return;
		}
		$missing = [];
		foreach ($term->getBinds() as $x) {
			if ($x->isLibrarySymbol()) {
				$missing[] = $x->getBindName();
			}
		}
		if (count($missing)) {
			$missing = implode(',', $missing);
			throw SymbolNotFound::MissingSymbols($missing);
		}
	}

}
