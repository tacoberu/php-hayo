<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;
use Throwable;


/**
 * The value requires some arguments. The result must therefore be computed.
 * When this function is called, all dependencies must already be resolved,
 * at the latest by the passed arguments.
 *
 * $binds stores both resolved and unresolved dependencies.
 */
class ParametricValue implements HasRefs, Value
{

	/**
	 * @var Expr | Form | Composite | BindValue | FinalValue
	 */
	private $expr;

	private string $type;

	/**
	 * @var list<BindValue>
	 */
	private array $binds;

	/**
	 * Names among $binds that were captured from an enclosing scope (closure
	 * over a lambda's free variables), as opposed to the callable's own
	 * formal parameters. Only these may be resolved ahead of time, before
	 * this value is handed to a caller (e.g. List.map) that supplies the
	 * remaining, genuinely-required arguments.
	 * @var list<string>
	 */
	private array $closureNames = [];

	/**
	 * Values already resolved for names removed from $binds by partialApply().
	 * @var array<string, FinalValue | ParametricValue>
	 */
	private array $closure = [];

	/**
	 * @param Expr | Form | Composite | BindValue | FinalValue $expr
	 * @param list<BindValue> $binds
	 */
	private function __construct($expr, string $type, array $binds)
	{
		$this->expr = $expr;
		$this->type = $type;
		$this->binds = $binds;
	}



	/**
	 * All unresolved dependencies of $expr must be captured in $binds.
	 *
	 * @param list<BindValue> $binds
	 */
	static function Expr_(Expr $expr, string $type, array $binds): self
	{
		return new self($expr, $type, $binds);
	}



	/**
	 * @param list<BindValue> $binds
	 */
	static function Form_(Form $expr, string $type, array $binds): self
	{
		return new self($expr, $type, $binds);
	}



	/**
	 * @param list<BindValue> $binds
	 */
	static function Dict_(Composite $expr, array $binds): self
	{
		return new self($expr, Composite::TypeDict, $binds);
	}



	/**
	 * @param list<BindValue> $binds
	 */
	static function Record_(Composite $expr, array $binds): self
	{
		return new self($expr, 'Record', $binds);
	}



	/**
	 * @param list<BindValue> $binds
	 */
	static function List_(Composite $expr, array $binds): self
	{
		return new self($expr, Composite::TypeList, $binds);
	}



	/**
	 * @param list<BindValue> $binds
	 */
	static function Tuple_(Composite $expr, array $binds): self
	{
		return new self($expr, Composite::TypeTuple, $binds);
	}



	static function ShortLinkBind(BindValue $expr): self
	{
		return new self($expr, '?', [$expr]);
	}



	/**
	 * Body that only refers to a symbol or a path, such as `x -> x` or `x -> x.id`.
	 * Unlike ShortLinkBind the callable may take other arguments than the linked one.
	 *
	 * @param list<BindValue> $binds
	 */
	static function Link_(BindValue $expr, array $binds): self
	{
		return new self($expr, '?', $binds);
	}



	/**
	 * Body that is a constant, such as `x -> 1`. The arguments are required, but ignored.
	 *
	 * @param list<BindValue> $binds
	 */
	static function Const_(FinalValue $expr, array $binds): self
	{
		return new self($expr, $expr->type(), $binds);
	}



	/**
	 * Marks which of $binds are free variables closed over from an enclosing
	 * scope, and therefore safe to resolve early via partialApply().
	 * @param list<string> $names
	 */
	function markClosureNames(array $names): self
	{
		$clone = clone $this;
		$clone->closureNames = $names;
		return $clone;
	}



	/**
	 * Resolves any of this value's closure names that are already available
	 * in $lets, baking them in and shrinking $binds to only what the eventual
	 * caller still needs to supply. Own formal parameters (not in
	 * $closureNames) are never touched here.
	 *
	 * @param array<string, FinalValue | ParametricValue> $lets
	 */
	function partialApply(array $lets): self
	{
		if ($this->closureNames === []) {
			return $this;
		}

		$resolved = $this->closure;
		$remainingBinds = [];
		$remainingClosureNames = [];
		foreach ($this->binds as $bind) {
			$name = $bind->getName();
			if (in_array($name, $this->closureNames, True) && array_key_exists($name, $lets)) {
				$resolved[$name] = $lets[$name];
			}
			else {
				$remainingBinds[] = $bind;
				if (in_array($name, $this->closureNames, True)) {
					$remainingClosureNames[] = $name;
				}
			}
		}

		if ($resolved === $this->closure) {
			return $this;
		}

		$clone = clone $this;
		$clone->binds = $remainingBinds;
		$clone->closure = $resolved;
		$clone->closureNames = $remainingClosureNames;
		return $clone;
	}



	function type(): string
	{
		return $this->type;
	}



	function getTypeName(): string
	{
		return $this->type;
	}



	function unpack(): string
	{
		return "<lambda> -> {$this->type}";
	}



	/**
	 * Which arguments are required.
	 * @return list<BindValue>
	 */
	function getBinds(): array
	{
		return $this->binds;
	}



	/**
	 * Depends on some symbols that we were unable to resolve.
	 * @return list<string>
	 */
	function refs(): array
	{
		$xs = [];
		switch (True) {
			case $this->expr instanceof HasRefs:
				$xs = array_merge($xs, $this->expr->refs());
				break;

			default:
				throw new LogicException("oops.");
		}

		return $xs;
	}



	/**
	 * @return list<string>
	 */
	function getArgs(): array
	{
		return array_map(static function(BindValue $x): string {
			return $x->getName();
			}, $this->getBinds());
	}



	/**
	 * @param array<string, FinalValue | ParametricValue> $args
	 * @return FinalValue | ParametricValue
	 */
	function apply(array $args)
	{
		try {
			self::assertArguments($args);
			$this->assertBindArguments($this->getBinds(), $args);
			return Interpret::applyAny($this->expr, array_merge($this->closure, $args));
		}
		catch (ArgumentsException | ScriptRuntimeException | SymbolNotFound $e) {
			throw $e;
		}
		catch (Throwable $e) {
			throw ScriptRuntimeException::From($e);
		}
	}



	/**
	 * Validates that the correct number of elements was passed.
	 *
	 * @param array<BindValue> $binds
	 * @param array<string, FinalValue | ParametricValue> $args
	 */
	private function assertBindArguments(array $binds, array $args): void
	{
		$binds = array_values(array_map(static function(BindValue $x): string {
			return $x->getName();
		}, $binds));
		$args = array_keys($args);
		if (count($binds) !== count($args)) {
			throw ArgumentsException::InvalidCountOfArguments((string) $this, $binds, $args);
		}
		$missing = array_diff($binds, $args);
		$extra = array_diff($args, $binds);
		if (count($missing) || count($extra)) {
			throw ArgumentsException::InvalidArguments($binds, $args);
		}
	}



	/**
	 * @param array<string, FinalValue | ParametricValue> $args
	 */
	private static function assertArguments(array $args): void
	{
		foreach ($args as $key => $val) {
			self::assertArgument($key, $val);
		}
	}



	/**
	 * @param FinalValue | ParametricValue $val
	 */
	private static function assertArgument(string $key, $val): void // @phpstan-ignore void.pure
	{
		if ( ! $val instanceof FinalValue // @phpstan-ignore booleanAnd.alwaysFalse
				&& ! $val instanceof self) { // @phpstan-ignore instanceof.alwaysTrue
			throw SymbolNotFound::InvalidArgumentWrapper($key, is_object($val) ? get_class($val) : gettype($val)); // @phpstan-ignore argument.type, function.alreadyNarrowedType
		}
	}



	function __toString(): string
	{
		$binds = implode(', ', array_map(static function(BindValue $x): string { return $x->getBindName(); }, $this->binds));
		return "CallableValue: {$this->expr} [$binds]";
	}

}
