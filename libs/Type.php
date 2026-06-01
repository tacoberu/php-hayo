<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * A Hayo type. All type nodes are immutable value objects.
 */
interface Type_
{

	/**
	 * Returns the names of all free (unbound) type variables in this type.
	 *
	 * A type variable is free when no enclosing TScheme has declared it as
	 * bound (∀a. …). Free variables represent things the compiler does not
	 * yet know — unknowns still waiting to be resolved.
	 *
	 * Used in three places:
	 *
	 * 1. OCCURS CHECK (Unifier::bindVar)
	 *    Before recording "a = List<a>", the unifier verifies that 'a' does
	 *    not appear free inside List<a>. Without this check the compiler
	 *    would create an infinite type List<List<List<…>>>.
	 *
	 * 2. SKIPPING BOUND VARIABLES (Substitution::applyToScheme)
	 *    ∀a. a -> b has free vars [b] and bound vars [a].
	 *    A substitution {a: Int, b: Str} may only replace the free variable
	 *    b — it must leave the bound a alone.
	 *
	 * 3. GENERALISATION (future Compiler phase)
	 *    When a type is promoted to a scheme the compiler quantifies over
	 *    exactly those variables that are free in the type but not free in
	 *    the surrounding type environment:
	 *      ∀ (freeVars(type) \ freeVars(environment)). type
	 *
	 * In short: freeVars() answers "what do we still not know about this type?"
	 *
	 * @return list<string>
	 */
	function freeVars(): array;



	/**
	 * Returns a new type with every free variable replaced according to $s.
	 *
	 * Substitutes every free type variable that $s knows about; variables
	 * absent from $s are left unchanged (they are still unknown).
	 *
	 *   substitution: { a: Int, b: Str }
	 *   type: a -> List<b>
	 *   result: Int -> List<Str>
	 *
	 * Each implementing class handles its own case:
	 *   TVar — look up the name in $s; return the bound type or $this if absent
	 *   TCon — return $this, no variables to replace
	 *   TApp — recursively apply $s to every argument
	 *   TFun — recursively apply $s to both sides of the arrow
	 *
	 * The method lives on Type_ (not on Substitution) so that each class
	 * contains its own transformation logic — no instanceof chains needed.
	 * Substitution::apply(Type_) is the public entry point; it delegates here.
	 *
	 * Relationship to freeVars(): freeVars() answers "what do we not know?",
	 * apply() answers "fill in what we do know." They are two sides of the
	 * same coin and are always used together during inference.
	 */
	function apply(Substitution $s): self;



	function __toString(): string;

}



/**
 * A type variable: a, b, t0, …
 */
class TVar implements Type_
{

	/**
	 * @var string
	 */
	private $name;

	function __construct(string $name)
	{
		$this->name = $name;
	}



	function getName(): string
	{
		return $this->name;
	}



	/**
	 * @return list<string>
	 */
	function freeVars(): array
	{
		return [$this->name];
	}



	function apply(Substitution $s): Type_
	{
		return $s->get($this->name) ?? $this;
	}



	function __toString(): string
	{
		return $this->name;
	}

}



/**
 * A nullary type constructor: Int, Str, Bool, Real, Null, DateTime, …
 */
class TCon implements Type_
{

	/**
	 * @var string
	 */
	private $name;

	function __construct(string $name)
	{
		$this->name = $name;
	}



	function getName(): string
	{
		return $this->name;
	}



	/**
	 * @return list<string>
	 */
	function freeVars(): array
	{
		return [];
	}



	function apply(Substitution $s): Type_
	{
		return $this;
	}



	function __toString(): string
	{
		return $this->name;
	}

}



/**
 * A type constructor applied to one or more type arguments: List<a>, Result<a, b>, …
 */
class TApp implements Type_
{

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var list<Type_>
	 */
	private $args;

	/**
	 * @param list<Type_> $args
	 */
	function __construct(string $name, array $args)
	{
		$this->name = $name;
		$this->args = $args;
	}



	function getName(): string
	{
		return $this->name;
	}



	/**
	 * @return list<Type_>
	 */
	function getArgs(): array
	{
		return $this->args;
	}



	/**
	 * @return list<string>
	 */
	function freeVars(): array
	{
		$vars = [];
		foreach ($this->args as $arg) {
			foreach ($arg->freeVars() as $v) {
				$vars[] = $v;
			}
		}
		return array_values(array_unique($vars));
	}



	function apply(Substitution $s): Type_
	{
		return new self($this->name, array_map(static function (Type_ $t) use ($s): Type_ {
			return $t->apply($s);
		}, $this->args));
	}



	function __toString(): string
	{
		$args = implode(', ', array_map(static function (Type_ $t): string {
			return (string) $t;
		}, $this->args));
		return "{$this->name}<{$args}>";
	}

}



/**
 * A function type: from -> to
 */
class TFun implements Type_
{

	/**
	 * @var Type_
	 */
	private $from;

	/**
	 * @var Type_
	 */
	private $to;

	function __construct(Type_ $from, Type_ $to)
	{
		$this->from = $from;
		$this->to = $to;
	}



	function getFrom(): Type_
	{
		return $this->from;
	}



	function getTo(): Type_
	{
		return $this->to;
	}



	/**
	 * @return list<string>
	 */
	function freeVars(): array
	{
		return array_values(array_unique(array_merge(
			$this->from->freeVars(),
			$this->to->freeVars()
		)));
	}



	function apply(Substitution $s): Type_
	{
		return new self(
			$this->from->apply($s),
			$this->to->apply($s)
		);
	}



	function __toString(): string
	{
		// Parenthesise the left side only when it is itself a function type,
		// since -> is right-associative: Int -> Str -> Bool = Int -> (Str -> Bool)
		$from = $this->from instanceof self
			? "({$this->from})"
			: (string) $this->from;
		return "{$from} -> {$this->to}";
	}

}



/**
 * A polytype (type scheme): ∀a b. type
 *
 * Represents a universally-quantified type, e.g. ∀a. List<a> -> Int.
 * Used for let-polymorphism and built-in function signatures.
 */
class TScheme
{

	/**
	 * @var list<string>
	 */
	private $vars;

	/**
	 * @var Type_
	 */
	private $type;

	/**
	 * @param list<string> $vars bound type variables
	 */
	function __construct(array $vars, Type_ $type)
	{
		$this->vars = $vars;
		$this->type = $type;
	}



	/**
	 * Creates a monomorphic scheme with no quantified variables.
	 */
	static function mono(Type_ $type): self
	{
		return new self([], $type);
	}



	/**
	 * @return list<string>
	 */
	function getVars(): array
	{
		return $this->vars;
	}



	function getType(): Type_
	{
		return $this->type;
	}



	/**
	 * Replaces each bound variable with a fresh type variable.
	 */
	function instantiate(Unifier $unifier): Type_
	{
		if ($this->vars === []) {
			return $this->type;
		}
		$bindings = [];
		foreach ($this->vars as $var) {
			$bindings[$var] = $unifier->fresh();
		}
		return (new Substitution($bindings))->apply($this->type);
	}



	/**
	 * Free variables of the scheme: free vars of the body minus the bound vars.
	 * @return list<string>
	 */
	function freeVars(): array
	{
		return array_values(array_diff(
			$this->type->freeVars(),
			$this->vars
		));
	}



	function __toString(): string
	{
		if ($this->vars === []) {
			return (string) $this->type;
		}
		return '∀' . implode(' ', $this->vars) . '. ' . $this->type;
	}

}
