<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * The typing environment: maps variable names to their type schemes.
 *
 * During inference the environment grows as new bindings are introduced
 * (lambda parameters, let bindings). It is threaded immutably through
 * the inference algorithm — every extension returns a new instance.
 *
 * Built-in functions are NOT stored here: they appear as BuildinFunc
 * objects directly in the AST and are handled by TypeInferrer::schemeFor().
 */
class TypeEnv
{

	/**
	 * @var array<string, TScheme>
	 */
	private $bindings;

	/**
	 * @param array<string, TScheme> $bindings
	 */
	function __construct(array $bindings = [])
	{
		$this->bindings = $bindings;
	}



	/**
	 * Returns a new environment extended with a single binding.
	 *
	 * Called whenever the compiler enters a new lexical scope and needs to
	 * introduce a variable without affecting the surrounding environment.
	 * Two primary use cases:
	 *
	 * 1. LAMBDA PARAMETERS (TypeInferrer::inferLambda)
	 *    Before inferring the lambda body, each parameter is added with a
	 *    fresh type variable so that occurrences of the parameter inside the
	 *    body resolve to that variable:
	 *
	 *      x -> x + 1
	 *      extend('x', mono(t0)) → infer body → unify t0 with Int
	 *
	 * 2. LET BINDINGS (future Scope handling)
	 *    Each local variable is added before inferring the remainder of the
	 *    expression, so that later lines can refer to it with the inferred type.
	 *
	 * Immutability is the point: extend() returns a NEW instance and leaves
	 * the original unchanged. This means both branches of an if/then/else
	 * share the same outer environment without interfering with each other —
	 * a binding introduced in one branch is invisible to the other.
	 */
	function extend(string $name, TScheme $scheme): self
	{
		$new = clone $this;
		$new->bindings[$name] = $scheme;
		return $new;
	}



	function lookup(string $name): ?TScheme
	{
		return $this->bindings[$name] ?? Null;
	}



	/**
	 * Returns all free type variables that appear in any scheme in this env.
	 * Used by generalize() to avoid capturing env-level variables.
	 * @return list<string>
	 */
	function freeVars(): array
	{
		$vars = [];
		foreach ($this->bindings as $scheme) {
			foreach ($scheme->freeVars() as $v) {
				$vars[] = $v;
			}
		}
		return array_values(array_unique($vars));
	}



	/**
	 * Generalizes $type into a TScheme by universally quantifying over
	 * all type variables that are free in $type but NOT free in this env.
	 *
	 * This is the "let-generalization" step of Hindley-Milner:
	 * variables not constrained by the environment can be made polymorphic.
	 */
	function generalize(Type_ $type): TScheme
	{
		$forall = array_values(array_diff(
			$type->freeVars(),
			$this->freeVars()
		));
		return new TScheme($forall, $type);
	}



	/**
	 * Returns a new environment with every scheme updated by applying $s.
	 * Called when a substitution is learned and must be propagated into
	 * the environment so that subsequent lookups reflect the new knowledge.
	 */
	function apply(Substitution $s): self
	{
		$new = clone $this;
		foreach ($new->bindings as $name => $scheme) {
			$new->bindings[$name] = $s->applyToScheme($scheme);
		}
		return $new;
	}

}
