<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * Hindley-Milner unification.
 *
 * Provides:
 *  - fresh() — generates unique type variables
 *  - unify() — Robinson's unification algorithm
 */
class Unifier
{

	/**
	 * @var int
	 */
	private $counter = 0;

	/**
	 * Returns a fresh type variable with an optional name prefix.
	 */
	function fresh(string $prefix = 't'): TVar
	{
		return new TVar($prefix . $this->counter++);
	}



	/**
	 * Finds the most-general unifier (MGU) of $t1 and $t2.
	 *
	 * Returns the smallest substitution $s such that
	 *   $s->apply($t1) equals $s->apply($t2).
	 *
	 * "Most general" means as few constraints as possible: for
	 * unify(a, b) the answer is {a: b}, not {a: Int, b: Int} —
	 * leaving maximum freedom for the rest of the program.
	 *
	 * WHEN IT IS CALLED
	 * -----------------
	 * Every time the compiler encounters two things that must be the
	 * same type — an argument passed to a function, the two branches
	 * of an if expression, a variable used in two different positions.
	 * Each call yields a substitution; the compiler folds them together
	 * with compose() into one growing record of knowledge.
	 *
	 * THE FIVE CASES
	 * --------------
	 *   unify(a, T) → {a: T} bind the variable (occurs check first)
	 *   unify(a, a) → {} variable equals itself, nothing new
	 *   unify(Con, Con) → {} same atomic type, nothing new
	 *   unify(Con₁, Con₂) → error contradiction, no substitution exists
	 *   unify(F<a₁…>, F<b₁…>)
	 *   unify(a→b, c→d) → recurse pairwise, thread substitution with compose
	 *   unify(a, T∋a) → error occurs check: would create infinite type
	 *
	 * EXAMPLES
	 * --------
	 *   unify( a -> b, Int -> Str ) → { a: Int, b: Str }
	 *   unify( List<a>, List<Int> ) → { a: Int }
	 *   unify( Int, Int ) → {}
	 *   unify( Int, Str ) → error "Cannot unify Int with Str"
	 *   unify( a, List<a> ) → error "Occurs check: a occurs in List<a>"
	 *
	 * @throws CompileException if the types cannot be unified
	 */
	function unify(Type_ $t1, Type_ $t2): Substitution
	{
		if ($t1 instanceof TVar) {
			return $this->bindVar($t1->getName(), $t2);
		}

		if ($t2 instanceof TVar) {
			return $this->bindVar($t2->getName(), $t1);
		}

		if ($t1 instanceof TCon && $t2 instanceof TCon) {
			if ($t1->getName() === $t2->getName()) {
				return Substitution::empty_();
			}
			throw CompileException::CannotUnify($t1, $t2);
		}

		if ($t1 instanceof TApp && $t2 instanceof TApp) {
			if ($t1->getName() !== $t2->getName()) {
				throw CompileException::CannotUnify($t1, $t2);
			}
			if (count($t1->getArgs()) !== count($t2->getArgs())) {
				throw CompileException::CannotUnify($t1, $t2);
			}
			return $this->unifyLists($t1->getArgs(), $t2->getArgs());
		}

		if ($t1 instanceof TFun && $t2 instanceof TFun) {
			$s1 = $this->unify($t1->getFrom(), $t2->getFrom());
			$s2 = $this->unify($s1->apply($t1->getTo()), $s1->apply($t2->getTo()));
			return $s2->compose($s1);
		}

		throw CompileException::CannotUnify($t1, $t2);
	}



	/**
	 * Binds variable $var to $type after performing the occurs check.
	 *
	 * @throws CompileException
	 */
	private function bindVar(string $var, Type_ $type): Substitution
	{
		if ($type instanceof TVar && $type->getName() === $var) {
			return Substitution::empty_();
		}
		if ($this->occursIn($var, $type)) {
			throw CompileException::OccursCheck($var, $type);
		}
		return Substitution::single($var, $type);
	}



	/**
	 * Unifies two lists of types pairwise, threading the substitution through.
	 *
	 * @param list<Type_> $ts1
	 * @param list<Type_> $ts2
	 */
	private function unifyLists(array $ts1, array $ts2): Substitution
	{
		$s = Substitution::empty_();
		foreach (array_keys($ts1) as $i) {
			$s2 = $this->unify($s->apply($ts1[$i]), $s->apply($ts2[$i]));
			$s = $s2->compose($s);
		}
		return $s;
	}



	/**
	 * Returns true if $var appears free inside $type (occurs check).
	 */
	private function occursIn(string $var, Type_ $type): bool
	{
		return in_array($var, $type->freeVars(), True);
	}

}
