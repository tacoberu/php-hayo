<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * A finite mapping from type-variable names to types — the compiler's
 * running record of everything it has learned about unknowns so far.
 *
 * WHY IT EXISTS
 * -------------
 * During type inference the compiler encounters expressions whose types are
 * not yet known. It introduces a fresh type variable (t0, t1, …) as a
 * stand-in. As it processes more of the code it collects constraints:
 * "t0 must be Int", "t1 must be List<t2>", etc. A Substitution is the
 * disciplined container for all of that accumulated knowledge, letting the
 * compiler say "apply everything I know at once to any type".
 *
 * EXAMPLE
 * -------
 * Processing xs -> List.fold xs 0 (acc x -> acc + x) :
 *
 *   step 1 xs is unknown → introduce t0
 *   step 2 fold expects List<a> → t0 = List<t1>
 *   step 3 init argument is 0 (Int) → b = Int
 *   step 4 acc + x uses +: Num->Num->Num → t1 = Num, acc = Num
 *
 *   Final substitution: { t0: List<Num>, b: Int, t1: Num }
 *   Applied to the lambda: xs : List<Num> -> Int
 *
 * SOLVED FORM
 * -----------
 * After compose() the result is in solved form: no key variable appears
 * free in any value. This means apply() resolves a type in a single pass
 * without recursion. The invariant is maintained by also applying the
 * inner substitution to the outer's values during composition.
 *
 * IMMUTABILITY
 * ------------
 * Every operation (apply, compose, applyToScheme) returns a new instance.
 * The original substitution is never modified.
 *
 * COMPOSITION CONVENTION
 * ----------------------
 * $outer->compose($inner)
 *   — $inner is applied first, then $outer.
 *   — Equivalent to: (outer ∘ inner)(t) = outer(inner(t)).
 */
class Substitution
{

	/**
	 * @var array<string, Type_>
	 */
	private $bindings;

	/**
	 * @param array<string, Type_> $bindings
	 */
	function __construct(array $bindings = [])
	{
		$this->bindings = $bindings;
	}



	static function empty_(): self
	{
		return new self([]);
	}



	static function single(string $var, Type_ $type): self
	{
		return new self([$var => $type]);
	}



	/**
	 * Returns the type bound to $var, or null if $var is unbound.
	 */
	function get(string $var): ?Type_
	{
		return $this->bindings[$var] ?? Null;
	}



	/**
	 * Applies this substitution to $type, replacing all bound variables.
	 */
	function apply(Type_ $type): Type_
	{
		return $type->apply($this);
	}



	/**
	 * Returns a new substitution that combines $inner and $this into one,
	 * equivalent to first applying $inner, then applying $this.
	 *
	 *   (outer ∘ inner)(t) = outer(inner(t))
	 *   $outer->compose($inner)
	 *
	 * WHY IT IS NEEDED
	 * ----------------
	 * The compiler collects constraints from different parts of an expression.
	 * From the left branch it gets $inner, from the right branch $this (outer).
	 * compose() merges them into a single substitution so the rest of the
	 * compiler only ever has to work with one accumulated record of knowledge.
	 *
	 * WHY NOT JUST array_merge
	 * ------------------------
	 * A plain merge of {a: Int} and {b: List<a>} gives {a: Int, b: List<a>}.
	 * Applying that to `b` returns List<a> — `a` is still unresolved.
	 * A second apply() would be needed, which breaks the single-pass guarantee.
	 *
	 * compose() maintains the SOLVED-FORM invariant: no key variable appears
	 * free in any value. It does this in two steps:
	 *
	 *   step 1 — for each (x: t) in $inner: store (x: outer(t))
	 *            outer's knowledge is applied to inner's values.
	 *              (a: Int) → outer({a:Int}) = Int → (a: Int)
	 *
	 *   step 2 — for each (y: t) in $this not yet in result: store (y: inner(t))
	 *            inner's knowledge is applied to outer's values.
	 *              (b: List<a>) → inner(List<a>) = List<Int> → (b: List<Int>)
	 *
	 *   result: {a: Int, b: List<Int>} — single apply() is now sufficient.
	 *
	 * USAGE IN UNIFIER
	 * ----------------
	 *   $s1 = unify(t1->from, t2->from); // knowledge from "from"
	 *   $s2 = unify($s1->apply(t1->to), ...); // knowledge from "to"
	 *   return $s2->compose($s1); // s1 first, s2 on top
	 */
	function compose(self $inner): self
	{
		$result = [];

		// Apply $this (outer) to every type in $inner (inner).
		foreach ($inner->bindings as $var => $type) {
			$result[$var] = $this->apply($type);
		}

		// Add bindings from $this that inner does not already cover.
		// Apply $inner to them so that variables bound by $inner are resolved
		// (ensures the result is in solved form: no key appears free in a value).
		foreach ($this->bindings as $var => $type) {
			if (!array_key_exists($var, $result)) {
				$result[$var] = $inner->apply($type);
			}
		}

		return new self($result);
	}



	/**
	 * Applies this substitution to a scheme, skipping its bound variables.
	 */
	function applyToScheme(TScheme $scheme): TScheme
	{
		$restricted = $this->without($scheme->getVars());
		return new TScheme(
			$scheme->getVars(),
			$restricted->apply($scheme->getType())
		);
	}



	/** @return array<string, Type_> */
	function getBindings(): array
	{
		return $this->bindings;
	}



	/**
	 * Returns a copy of this substitution with the given variables removed.
	 * @param list<string> $vars
	 */
	private function without(array $vars): self
	{
		$result = $this->bindings;
		foreach ($vars as $var) {
			unset($result[$var]);
		}
		return new self($result);
	}

}
