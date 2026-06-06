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
	 * @var array<string, LibraryProvider>
	 */
	private array $libs = [];

	/**
	 * Table of short names, like `+`, `div`, `and`, etc.
	 * @var array<string, string>
	 */
	private array $short = [];

	/**
	 * @param array<string, LibraryProvider> $libs
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
		// Phase 0: extract type declarations, register constructors in this compiler instance
		$source = $this->extractTypeDeclarations($source);

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
		$term = $this->partialEvaluate($context, $term);

		// Second phase: type inference — catches type errors at compile time.
		$this->runTypeInference($term);

		// Third phase: convert term -> val
		$term = self::compileRuntimeValue($term);
		self::assertMissingSymbols($term);

		return $term;
	}



	private function createGlobalSymbols(Value $src): Context
	{
		$lets = [];
		if ($src instanceof HasRefs) {
			foreach ($src->refs() as $x) {
				if (isset($lets[$x])) {
					continue;
				}
				if ($pair = $this->lookupGlobalSymbol($x)) {
					$lets[$pair[0]] = $pair[1];
				}
			}
		}

		// Also resolve constructor Symbol scalars (e.g. Color.Red, Shape.Circle)
		// which are not visible via refs() since Scalar does not implement HasRefs.
		foreach ($this->scanForConstructors($src) as $x) {
			if (!isset($lets[$x]) && $pair = $this->lookupGlobalSymbol($x)) {
				$lets[$pair[0]] = $pair[1];
			}
		}

		return new Context($lets);
	}



	/**
	 * Recursively collects Symbol scalar values that look like constructor references
	 * (e.g. 'Color.Red', 'Shape.Circle') — they contain a dot and every segment
	 * starts with an uppercase letter.
	 *
	 * @return list<string>
	 */
	private function scanForConstructors(Value $src): array
	{
		$symbols = [];

		if ($src instanceof Scalar && $src->type() === 'Symbol') {
			$val = $src->getValue();
			if ($this->lookupGlobalSymbol($val)) {
				$symbols[] = $val;
			}
			return $symbols;
		}

		if ($src instanceof Expr) {
			foreach ($src->getItems() as $item) {
				if ($item instanceof Value) {
					$symbols = array_merge($symbols, $this->scanForConstructors($item));
				}
			}
			return $symbols;
		}

		if ($src instanceof Lambda) {
			$expr = $src->getExpr();
			if ($expr instanceof Value) {
                return array_merge($symbols, $this->scanForConstructors($expr));
            }
			return $symbols;
		}

		if ($src instanceof Scope) {
			foreach ($src->getLets() as $let) {
				if ($let instanceof Value) {
					$symbols = array_merge($symbols, $this->scanForConstructors($let));
				}
			}
			$expr = $src->getExpr();
			if ($expr instanceof Value) {
                return array_merge($symbols, $this->scanForConstructors($expr));
            }
			return $symbols;
		}

		if ($src instanceof Form) {
			foreach ($src->getItems() as $item) {
				if ($item instanceof Value) {
					$symbols = array_merge($symbols, $this->scanForConstructors($item));
				}
				elseif (is_object($item)) { // @phpstan-ignore function.impossibleType
					// if-then-else blocks: {cond, expr} — match arms: {pattern, binds, expr}
					foreach (['subject', 'cond', 'expr'] as $field) {
						if (isset($item->$field) && $item->$field instanceof Value) {
							$symbols = array_merge($symbols, $this->scanForConstructors($item->$field));
						}
					}
				}
			}
			return $symbols;
		}

		if ($src instanceof Composite) {
			foreach ((array) $src->getItems() as $item) {
				if ($item instanceof Value) {
					$symbols = array_merge($symbols, $this->scanForConstructors($item));
				}
			}
			return $symbols;
		}

		return $symbols;
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
		$lib = $this->libs[$ns] ?? Null;
		if ($lib instanceof FuncProvider && $fn = $lib->lookupFunc($symbol)) {
			return [$x, $fn];
		}

		// Library sum-type constructor: `Ns.Type.Variant` (e.g. Geo.Shape.Circle).
		// Resolves against a TypeProvider's SumTypeDef and builds a constructor
		// carrying the fully-qualified type name ("Geo.Shape"), so the prefix
		// propagates into the resulting SumTypeValue — matching value()-built ones.
		if ($lib instanceof TypeProvider && strpos($symbol, '.')) {
			list($type, $variant) = explode('.', $symbol, 2);
			if ( ! strpos($variant, '.')) {
				$def = $lib->lookupType($type);
				if ($def instanceof SumTypeDef && in_array($variant, $def->getVariantNames(), True)) {
					$fn = new SumTypeConstructor(
						"{$ns}.{$type}",
						$def->getTypeParams(),
						$variant,
						$def->getVariantArgTypes($variant)
					);
					return [$x, $fn];
				}
			}
		}

		return Null;
	}



	private function normalizeShortSymbols(string $x): string
	{
		if (strpos($x, '.')) {
			return $x;
		}

		// Case-sensitive lookup first — preserves uppercase symbol/constructor names
		// (True, False, …) per Hayo convention: types start uppercase, functions lowercase.
		// Falls back to case-insensitive lookup for operators and word-style operators
		// (and, or, not, AND, OR, NOT, …).
		return $this->short[$x] ?? $this->short[strtolower($x)] ?? $x;
	}



	/**
	 * Extracts type declarations from the source, registers constructors in
	 * $this->libs, and returns the stripped source.
	 *
	 * Supported forms:
	 *   type Color = Red | Green | Blue (no parameters)
	 *   type Shape = Circle Real | Rectangle Real Real | Point
	 *   type Result<a, b> = Ok a | Err b (Rust-style params)
	 *   type Maybe<a> = Just a | Nothing
	 *
	 * Type parameters use `<a, b>` Rust-style; constructor arguments stay
	 * positional Haskell-style for consistency with builtin signatures.
	 *
	 * Only single-line declarations are supported in this phase.
	 */
	private function extractTypeDeclarations(string $source): string
	{
		return (string) preg_replace_callback(
			'/^type\s+([A-Z]\w*)(?:<([^>]+)>)?\s*=\s*(.+)$/m',
			function (array $m): string {
				$typeName = $m[1];
				$typeParams = $m[2] !== ''
					? array_values(array_filter(array_map('trim', explode(',', $m[2]))))
					: [];
				$variantsStr = $m[3];
				$variants = [];

				foreach (explode('|', $variantsStr) as $part) {
					$tokens = preg_split('/\s+/', trim($part)) ?: [];
					$varName = (string) array_shift($tokens);
					$variants[$varName] = $tokens; // remaining = arg type names
				}

				$this->libs[$typeName] = new SumTypeProvider($typeName, $typeParams, $variants);
				return ''; // strip from source
			},
			$source
		);
	}



	/**
	 * @param Value|string $term
	 */
	private function runTypeInference($term): void
	{
		$this->getInferrer()->infer(new TypeEnv(), $term);
	}



	/**
	 * Returns a fresh TypeInferrer wired to this compiler's sum-type registry.
	 * Used both by the main inference pass and by per-Expr pre-fold validation.
	 */
	private function getInferrer(): TypeInferrer
	{
		return new TypeInferrer(new Unifier(), $this->collectSumTypes());
	}



	/**
	 * Runs inference on a single Expr that partial evaluation is about to fold.
	 * Catches typing mistakes between fully-resolved constants — without this
	 * check, expressions like `List.concat [1,2] ["a","b"]` would be silently
	 * evaluated before the main inference pass got a chance to look at them.
	 */
	private function validateFoldableExpr(Expr $term): void
	{
		$this->getInferrer()->infer(new TypeEnv(), $term);
	}



	/**
	 * Collects type declarations from registered libraries — used by the type
	 * inferrer for exhaustiveness checking and instantiation of polymorphic types.
	 *
	 * Two sources: sum types registered under namespace == type name (Bool, script
	 * `type X = …`) are keyed by their bare name; types provided by a TypeProvider
	 * are namespaced (keyed "Ns.Type"), matching their runtime type names.
	 *
	 * @return array<string, SumTypeDef> typeName => descriptor
	 */
	private function collectSumTypes(): array
	{
		$result = [];
		foreach ($this->libs as $ns => $lib) {
			if ($lib instanceof SumTypeDef) {
				$result[$lib->getTypeName()] = $lib;
			}
			if ($lib instanceof TypeProvider) {
				// Only sum types belong in the inferrer; product types are inert here.
				foreach ($lib->getProvidedTypeNames() as $local) {
					$def = $lib->lookupType($local);
					if ($def instanceof SumTypeDef) {
						$result["{$ns}.{$local}"] = $def;
					}
				}
			}
		}
		return $result;
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
	private function partialEvaluate(Context $context, $term)
	{
		switch (True) {
			case is_string($term):
				return $this->partialEvaluateSymbol($context, $term);

			// Numbers, final values, and builtin functions have nothing to process
			case $term instanceof FinalValue:
			case $term instanceof BuildinFunc:
				return $term;

			// A Symbol scalar (e.g. Color.Red, True, False) may be a constructor
			// reference — try to resolve it through the context first.
			case $term instanceof Scalar && $term->type() === 'Symbol':
				$resolved = $this->partialEvaluateSymbol($context, $term->getValue());
				if ($resolved instanceof BuildinFunc && $resolved->getBinds() === []) {
					// Zero-arg constructor: evaluate immediately
					try {
						return $resolved->apply([]);
					}
					catch (DivisionByZeroError | ScriptTypeException | InvalidArgumentException $e) {
						throw CompileException::EvaluationError($e);
					}
				}
				if ($resolved instanceof Value) {
					return $resolved; // Non-zero-arg constructor or other value
				}
				return $term;

			case $term instanceof Scalar:
				return $term;

			// Dicts etc. may contain bound symbols or function calls, but those must be handled one level up, in Scope.
			case $term instanceof Composite:
				return $this->partialEvaluateComposite($context, $term);

			//~ case $term instanceof BuildinFunc:
			// @TODO Room for optimization: Lambda cannot be fully executed because it depends on argument state.
			// But parts of the Expr might be. Depends on how complex the lambda is.
			case $term instanceof Lambda:
				return $this->partialEvaluateLambda($context, $term);

			// Move all symbols from the local scope to their usage site; the symbol and scope then cease to exist.
			// Performs **partial evaluation** of an expression expected to produce a value.
			case $term instanceof Scope:
				return $this->partialEvaluateScope($context, $term);

			// Function call or operator. Dispatches to immediate evaluation when
			// all operands are known; preserves expression when some remain free.
			case $term instanceof Expr:
				return $this->partialEvaluateExpr($context, $term);

			case $term instanceof Form && $term->getName() === 'if-then-else':
				return $this->partialEvaluateFormIfThenElse($context, $term);

			case $term instanceof Form && $term->getName() === 'match':
				return $this->partialEvaluateFormMatch($context, $term);

			default:
				throw CompileException::UnsupportedException('partial evaluate', $term);
		}
	}



	/**
	 * @return Value|string
	 */
	private function partialEvaluateSymbol(Context $context, string $term)
	{
		// Check whether we have a Dict stored in the context; select by the first key in the path x.foo.doo
		$id = new BindValue($term, '?');
		if ($value = $context->selectSymbol($id->getName())) {
			if ($id->isPath()) {
				list($value, ) = self::castAny($value, False);
				assert($value instanceof FinalValue);
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
	private function partialEvaluateExpr(Context $context, Expr $term): Value
	{
		$items = $term->getItems();

		switch (True) {
			case $term->getNotation() === Expr::NotationInfix:
				$callName = self::callSiteName($items[1]);
				foreach ($items as $k => $x) {
					$items[$k] = $this->partialEvaluate($context, $x);
				}

				$term = Expr::Bin_($items[0], $items[1], $items[2]);

				// Rovnou vyhodnotit — only when operands are fully resolved FinalValues
				if ($term->refs() === []) {
					$left = self::castAny($items[0], False)[0];
					$right = self::castAny($items[2], False)[0];
					if ($left instanceof FinalValue && $right instanceof FinalValue) {
						// Type-check before folding so constant-only mismatches are caught
						$this->validateFoldableExpr($term);
						try {
							return $items[1]->apply([$left, $right]); // @phpstan-ignore method.nonObject
						}
						catch (DivisionByZeroError | ScriptTypeException | InvalidArgumentException $e) {
							throw CompileException::EvaluationError($e);
						}
					}
				}

				// Validate types of resolved operands against the operator signature
				if ($items[1] instanceof BuildinFunc) {
					$left = self::castAny($items[0], False)[0];
					$right = self::castAny($items[2], False)[0];
					if ($left instanceof FinalValue && $right instanceof FinalValue) {
						try {
							TypeValidator::assertPartialArgTypes(
								$callName ?? (string) $items[1],
								$items[1]->getBinds(),
								[$left, $right]
							);
						}
						catch (InvalidArgumentException $e) {
							throw CompileException::EvaluationError($e);
						}
					}
				}

				// There are some arguments
				return $term;

			case $term->getNotation() === Expr::NotationPrefix:
				$callName = self::callSiteName($items[0]);
				foreach ($items as $k => $x) {
					$items[$k] = $this->partialEvaluate($context, $x);
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
					// Type-check before folding so constant-only mismatches are caught
					if ($items[0] instanceof BuildinFunc) {
						$this->validateFoldableExpr($term);
					}
					$expr = $this->partialEvaluateApplicable($items[0], $args); // @phpstan-ignore argument.type
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
					return $this->partialEvaluateExpr($context, $fn->getExpr()); // @phpstan-ignore argument.type
				}

				// Validate types of resolved args against the function signature (only full arity calls)
				if ($items[0] instanceof BuildinFunc) {
					$callArgs = array_slice($items, 1);
					if (count($callArgs) === count($items[0]->getBinds())) {
						try {
							TypeValidator::assertPartialArgTypes(
								$callName ?? (string) $items[0],
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
	private function partialEvaluateFormIfThenElse(Context $context, Form $term): Value
	{
		$chains = [];
		foreach ($term->getItems() as $usecase) {
			/** @var object{cond: mixed, expr: mixed} $usecase */
			$chains[] = (object) [
				'cond' => $usecase->cond ? $this->partialEvaluate($context, $usecase->cond) : Null,
				'expr' => $this->partialEvaluate($context, $usecase->expr),
			];
		}
		$else = array_pop($chains);
		return Form::IfThenElse_($chains, $else->expr);
	}



	private function partialEvaluateFormMatch(Context $context, Form $term): Form
	{
		$items = $term->getItems();
		$subject = $this->partialEvaluate($context, $items[0]);

		$arms = [];
		foreach (array_slice($items, 1) as $arm) {
			/** @var object{pattern: string, binds: list<string>, expr: Value|string} $arm */
			$armContext = clone $context;
			foreach ($arm->binds as $bind) {
				$armContext->shadowByArg($bind);
			}
			$arms[] = (object) [
				'pattern' => $arm->pattern,
				'binds' => $arm->binds,
				'expr' => $this->partialEvaluate($armContext, $arm->expr),
			];
		}

		return Form::Match_($subject, $arms);
	}



	/**
	 * @param list<string | Value> $args
	 * @return Value | string
	 */
	private function partialEvaluateApplicable(Applicable $fn, array $args)
	{
		switch (True) {
			case $fn instanceof Lambda:
				$context = new Context([]);
				foreach ($fn->getArgs() as $i => $id) {
					$context->shadow($id, $args[$i]);
				}
				return $this->partialEvaluate($context, $fn->getExpr());

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
	private function partialEvaluateScope(Context $context, Scope $src)
	{
		switch (True) {
			case is_string($src->getExpr()):
				//~ if ($term = $context->selectSymbol($src->getExpr())) {
					//~ die("\n------\n" . __file__ . ':' . __line__ . "\n");
				//~ }
				return $src;

			// Nested scope is not supported.
			case $src->getExpr() instanceof Scope: // @phpstan-ignore instanceof.alwaysFalse
				throw CompileException::UnsupportedException('partial evaluate const scope of scope', $src->getExpr());

			// `{1 + 1}` -- because addition is also a symbol -> `{+ = buildin; 1 + 1}`
			// `{a = 1; a + a}`
			case $src->getExpr() instanceof Expr: // @phpstan-ignore instanceof.alwaysTrue
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
						$context2->shadow($id, $this->partialEvaluate($context, $value)); // @phpstan-ignore argument.type
					}
					elseif ($value->refs() === []) {
						$context2->shadow($id, $this->partialEvaluate($context, $value)); // @phpstan-ignore argument.type
					}
					else {
						$seconds[$id] = $value;
					}
				}

				// 2/ Values that reach into the parent scope
				// @TODO Recurse
				foreach ($seconds as $id => $value) {
					$context2->shadow($id, $this->partialEvaluate($context2, $value)); // @phpstan-ignore argument.type
				}

				$term = $this->partialEvaluate($context2, $src->getExpr());
				return $this->partialEvaluate($context2, $term);

			// `a = 5; (a, 5)`
			// `a = 5; [1, a]`
			// `a = 5; {a: a}`
			case $src->getExpr() instanceof Composite: // @phpstan-ignore instanceof.alwaysFalse

			// `c = Color.Red; match c | ...`
			case $src->getExpr() instanceof Form: // @phpstan-ignore instanceof.alwaysFalse
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
						$context2->shadow($id, $this->partialEvaluate($context, $value)); // @phpstan-ignore argument.type
					}
					elseif ($value->refs() === []) {
						$context2->shadow($id, $this->partialEvaluate($context, $value)); // @phpstan-ignore argument.type
					}
					else {
						$seconds[$id] = $value;
					}
				}
				// 2/ Values that reach into the parent scope
				// @TODO Recurse
				foreach ($seconds as $id => $value) {
					$context2->shadow($id, $this->partialEvaluate($context2, $value)); // @phpstan-ignore argument.type
				}
				return $this->partialEvaluate($context2, $src->getExpr());

			default:
				throw CompileException::UnsupportedException('partial evaluate const scope', $src->getExpr());
		}
	}



	/**
	 * We need to copy the values of the outer context.
	 * Arguments shadow the outer context, and the inner context shadows the arguments.
	 * The result is lambda = value.
	 */
	private function partialEvaluateLambda(Context $context, Lambda $src): Lambda
	{
		$context2 = clone $context;
		foreach ($src->getArgs() as $id) {
			if ($id === false) { // @phpstan-ignore identical.alwaysFalse
				throw CompileException::UnsupportedZeroArgLambda();
			}
			if ($id === null) { // @phpstan-ignore identical.alwaysFalse
				throw CompileException::UnsupportedZeroArgLambda();
			}
			if ( ! is_string($id)) { // @phpstan-ignore function.alreadyNarrowedType
				throw CompileException::InvalidLambdaArguments();
			}
			$context2->shadowByArg($id);
		}
		return new Lambda($src->getArgs(), $this->partialEvaluate($context2, $src->getExpr())); // @phpstan-ignore argument.type
	}



	private function partialEvaluateComposite(Context $context, Composite $src): Composite
	{
		// Cannot skip when refs()===[] — Symbol scalars (constructors like Color.Red)
		// are not counted by refs() but still need to be resolved here.
		$items = [];
		foreach ($src->getItems() as $k => $x) {
			$items[$k] = $this->partialEvaluate($context, $x);
		}

		switch ($src->type()) {
			case Composite::TypeList:
				return Composite::List_($items); // @phpstan-ignore argument.type

			case Composite::TypeDict:
				return Composite::Dict_($items);

			case Composite::TypeTuple:
				return Composite::Tuple_($items); // @phpstan-ignore argument.type

			default:
				throw CompileException::Unexpected();
		}
	}



	/**
	 * The token a function/operator was referenced by at the call site — e.g.
	 * the source name "TestMyMoney.format" or "+". Used for error messages so
	 * they reflect what the script wrote, not the library's internal qualified
	 * name (the namespace is the caller's choice, unknown to the library).
	 *
	 * Returns Null when the operand is not a plain symbol; callers then fall
	 * back to the resolved function's __toString().
	 *
	 * @param mixed $item the operand before it is resolved to a value
	 */
	private static function callSiteName($item): ?string
	{
		if (is_string($item)) {
			return $item;
		}
		if ($item instanceof Scalar && $item->type() === 'Symbol') {
			return (string) $item->getValue();
		}
		return Null;
	}



	/**
	 * @return Value|string|null
	 */
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
	 * @param Value|string $src
	 * @return ParametricValue | FinalValue
	 */
	private static function compileRuntimeValue($src)
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
	 * @return array{0: Value|string, 1: array<string, BindValue>}
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
				return self::castFormIfThenElse($src);

			case $src instanceof Form && $src->getName() === 'match':
				return self::castFormMatch($src);

			case is_string($src):
				if ($packref) {
					$x = new BindValue($src, "?");
					return [$x, [$src => $x]]; // @phpstan-ignore return.type
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
		$value = $val->type() === 'Symbol' && $val->getValue() === 'Null'
			? Null
			: $val->getValue();
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
		if ($expr instanceof Form) { // @phpstan-ignore instanceof.alwaysFalse
			return [ParametricValue::Form_($expr, '?', array_values($binds)), $binds];
		}
		return [ParametricValue::Expr_($expr, '?', array_values($binds)), $binds]; // @phpstan-ignore argument.type
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
					: [Composite::Tuple_($items), $lets]; // @phpstan-ignore argument.type

			case Composite::TypeList:
				return $src->refs() === []
					? [new FinalValue($items, 'List'), []]
					: [Composite::List_($items), $lets]; // @phpstan-ignore argument.type

			case Composite::TypeDict:
				return $src->refs() === []
					? [new FinalValue((object) $items, 'Dict'), []]
					: [Composite::Dict_($items), $lets]; // @phpstan-ignore argument.type

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
		switch ($src->getNotation()) {
			case Expr::NotationInfix:
				return [Expr::Bin_($items[0], $items[1], $items[2]), $lets]; // @phpstan-ignore argument.type, argument.type, argument.type

			case Expr::NotationPrefix:
				return [Expr::Func_($items[0], array_slice($items, 1)), $lets]; // @phpstan-ignore argument.type, argument.type

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
	private static function castFormIfThenElse(Form $src): array
	{
		$chains = [];
		$depends = [];
		foreach ($src->getItems() as $block) {
			/** @var object{cond: mixed, expr: mixed} $block */
			if ($block->cond === Null) {
				list($elseexpr, $depends1) = self::castAny($block->expr, True);
				$depends = array_merge($depends, $depends1);
				return [Form::IfThenElse_($chains, $elseexpr), $depends];
			}
			list($cond, $depends1) = self::castAny($block->cond, True);
			$depends = array_merge($depends, $depends1);
			list($expr, $depends1) = self::castAny($block->expr, True);
			$depends = array_merge($depends, $depends1);
			$chains[] = (object)['cond' => $cond, 'expr' => $expr];
		}
		throw CompileException::Unexpected();
	}



	/**
	 * @return array{0: Value, 1: array<string, BindValue>}
	 */
	private static function castFormMatch(Form $src): array
	{
		$items = $src->getItems();
		$depends = [];

		list($subject, $deps) = self::castAny($items[0], True);
		$depends = array_merge($depends, $deps);

		$arms = [];
		foreach (array_slice($items, 1) as $arm) {
			/** @var object{pattern: string, binds: list<string>, expr: Value|string} $arm */
			list($expr, $deps) = self::castAny($arm->expr, True);
			// Bound variable names are local to the arm — remove them from deps
			foreach ($arm->binds as $bind) {
				unset($deps[$bind]);
			}
			$depends = array_merge($depends, $deps);
			$arms[] = (object) [
				'pattern' => $arm->pattern,
				'binds' => $arm->binds,
				'expr' => $expr,
			];
		}

		return [Form::Match_($subject, $arms), $depends];
	}



	/**
	 * @param array<string|int, string | Value> $src
	 * @param array<int|string, BindValue|null> $typeHints expected parameter types keyed by item position
	 * @return array{0: array<int|string, string|Value|BindValue>, 1: array<string, BindValue>}
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



	/**
	 * @param FinalValue|ParametricValue $term
	 */
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
