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
 * The value requires some arguments. The result must therefore be computed.
 * When this function is called, all dependencies must already be resolved,
 * at the latest by the passed arguments.
 *
 * $binds stores both resolved and unresolved dependencies.
 */
class ParametricValue implements HasRefs, Value
{

	/**
	 * @var Expr | Form | Composite | BindVal
	 */
	private $expr;

	private string $type;

	/**
	 * @var list<BindVal>
	 */
	private array $binds;

	/**
	 * @param Expr | Form | Composite | BindVal $expr
	 * @param list<BindVal> $binds
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
	 * @param list<BindVal> $binds
	 */
	static function Expr_(Expr $expr, string $type, array $binds): self
	{
		return new self($expr, $type, $binds);
	}



	/**
	 * @param list<BindVal> $binds
	 */
	static function Form_(Form $expr, string $type, array $binds): self
	{
		return new self($expr, $type, $binds);
	}



	/**
	 * @param list<BindVal> $binds
	 */
	static function Dict_(Composite $expr, array $binds): self
	{
		return new self($expr, Composite::TypeDict, $binds);
	}



	/**
	 * @param list<BindVal> $binds
	 */
	static function Record_(Composite $expr, array $binds): self
	{
		return new self($expr, 'Record', $binds);
	}



	/**
	 * @param list<BindVal> $binds
	 */
	static function List_(Composite $expr, array $binds): self
	{
		return new self($expr, Composite::TypeList, $binds);
	}



	/**
	 * @param list<BindVal> $binds
	 */
	static function Tuple_(Composite $expr, array $binds): self
	{
		return new self($expr, Composite::TypeTuple, $binds);
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
		return array_map(static function($x) {
			return $x->getName();
			}, $this->getBinds());
	}



	/**
	 * @param array<string, FinalVal | ParametricValue> $args
	 * @return FinalVal | ParametricValue
	 */
	function apply(array $args)
	{
		self::assertArguments($args);
		self::assertBindArguments($this->getBinds(), $args);
		return Interpret::applyAny($this->expr, $args);
	}



	static function ShortLinkBind(BindVal $expr): self
	{
		return new self($expr, '?', [$expr]);
	}



	/**
	 * @param array<string, FinalVal | ParametricValue> $xs
	 */
	private static function assertBindInArguments(BindVal $bind, array $xs): void // @phpstan-ignore method.unused
	{
		if ( ! array_key_exists($bind->getName(), $xs)) {
			throw new InvalidArgumentException("Missing args: '{$bind->getBindName()}'.");
		}
	}



	/**
	 * Validates that the correct number of elements was passed.
	 *
	 * @param array<BindVal> $binds
	 * @param array<string, FinalVal | ParametricValue> $args
	 */
	private static function assertBindArguments(array $binds, array $args): void
	{
		$binds = array_map(static function(BindVal $x): string {
			return $x->getName();
		}, $binds);
		$args = array_keys($args);
		if (count($binds) !== count($args)) {
			$binds = implode(', ', array_map(static function(string $x): string { return "'{$x}'";}, $binds));
			$args = implode(', ', array_map(static function(string $x): string { return "'{$x}'";}, $args));
			throw new InvalidArgumentException("Invalid count of arguments. Expected {$binds}; given {$args}.");
		}
		$missing = array_diff($binds, $args);
		$extra = array_diff($args, $binds);
		if (count($missing) || count($extra)) {
			$binds = implode(', ', array_map(static function(string $x): string { return "'{$x}'";}, $binds));
			$args = implode(', ', array_map(static function(string $x): string { return "'{$x}'";}, $args));
			throw new InvalidArgumentException("Invalid arguments. Expected {$binds}; given {$args}.");
		}
	}



	/**
	 * @param array<string, FinalVal | ParametricValue> $args
	 */
	private static function assertArguments(array $args): void
	{
		foreach ($args as $key => $val) {
			self::assertArgument($key, $val);
		}
	}



	/**
	 * @param FinalVal | ParametricValue $val
	 */
	private static function assertArgument(string $key, $val): void // @phpstan-ignore void.pure
	{
		if ( ! $val instanceof FinalVal // @phpstan-ignore booleanAnd.alwaysFalse
				&& ! $val instanceof self) { // @phpstan-ignore instanceof.alwaysTrue
			throw new InvalidArgumentException("Argument '{$key}' must be package into FinalVal or ParametricValue; " . (is_object($val) ? get_class($val) : gettype($val)) . " given."); // @phpstan-ignore function.alreadyNarrowedType
		}
	}



	function __toString(): string
	{
		$binds = implode(', ', array_map(static function($x) { return $x->getBindName(); }, $this->binds));
		return "CallableValue: {$this->expr} [$binds]";
	}

}
