<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;
use InvalidArgumentException;


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
	 * @var Expr | Composite
	 */
	private $expr;

	private string $type;

	/**
	 * @var list<BindVal>
	 */
	private array $binds;

	/**
	 * @param Expr | Composite $expr
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
	static function Dict_(Composite $expr, array $binds): self
	{
		return new self($expr, Composite::TypeDict, $binds);
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
		return array_merge([], $this->expr->refs());
		/*
		$xs = [];
		switch (True) {
			case $this->expr instanceof HasRefs:
				$xs = array_merge($xs, $this->expr->refs());
				break;

			default:
				throw new LogicException("oops.");
		}

		return $xs;
		*/
	}



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
		return self::applyAny($this->expr, $args);
	}



	static function ShortLinkBind(BindVal $expr)
	{
		return new self($expr, '?', [$expr]);
	}



	/**
	 * @param string|FinalVal|self|BindVal| Value $src
	 * @param array<string, FinalVal | ParametricValue> $lets
	 * @return FinalVal | ParametricValue
	 */
	private static function applyAny($src, array $lets)
	{
		switch (True) {
			case is_string($src):
				// @TODO validace
				return $lets[$src];

			case $src instanceof BindVal:
				self::assertBindInArguments($src, $lets);
				$src = $lets[$src->getBindName()];
				if ( ! $src instanceof FinalVal) {
					throw new LogicException("Comming soon...");
				}
				return $src;

			case $src instanceof self:
				// @TODO nějaké omezení, aby se neposílaly všeechny lets, ale jen ty, co jsou v getBindNames()
				return $src->apply($lets);

			case $src instanceof Expr:
				return self::applyExpr($src, $lets);

			case $src instanceof Composite && $src->type() === Composite::TypeDict:
				return self::applyStructDict($src, $lets);

			case $src instanceof Composite && $src->type() === Composite::TypeList:
				return self::applyStructList($src, $lets);

			case $src instanceof Composite && $src->type() === Composite::TypeTuple:
				return self::applyStructTuple($src, $lets);

			case $src instanceof Scalar:
				return new FinalVal($src->getValue(), '?');

			case $src instanceof FinalVal:
				return $src;

			default:
				throw new LogicException("oops.");
		}
	}



	/**
	 * @param array<string, FinalVal | ParametricValue> $lets
	 * @return FinalVal | self
	 */
	private static function applyExpr(Expr $expr, array $lets)
	{
		$items = $expr->getItems();
		foreach ($items as $i => $x) {
			// funkce na úrovni expression, bude pravděpobodně to ta první. Ta nemá žádné závislosti.
			// naopak, nejdříve se musí vyřešit závislosti ze stejné úrovně - což právě děláme.
			if ($x instanceof BuildinFunc) {
				continue;
			}
			$items[$i] = self::applyAny($x, $lets);
		}

		// volání funkce
		if ($items[0] instanceof BuildinFunc) {
			$fn = array_shift($items);
			return $fn->apply($items); // @phpstan-ignore method.nonObject
		}
		// volání operátoru
		if (isset($items[1]) && $items[1] instanceof BuildinFunc) {
			$fn1 = array_shift($items);
			$fn = array_shift($items);
			return $fn->apply(array_merge([$fn1], $items)); // @phpstan-ignore method.nonObject
		}
		// Výsledkem může být expresion, ale také hodnota
		return $items[0]; // @phpstan-ignore return.type
	}



	/**
	 * @param array<string, FinalVal | ParametricValue> $lets
	 */
	private static function applyStructList(Composite $expr, array $lets): FinalVal
	{
		$items = [];
		foreach ($expr->getItems() as $k => $x) {
			$items[$k] = self::applyAny($x, $lets);
		}
		return new FinalVal($items, 'List');
	}



	/**
	 * @param array<string, FinalVal | ParametricValue> $lets
	 */
	private static function applyStructDict(Composite $expr, array $lets): FinalVal
	{
		$items = [];
		foreach ($expr->getItems() as $k => $x) {
			$items[$k] = self::applyAny($x, $lets);
		}

		return new FinalVal((object) $items, 'Dict');
	}



	/**
	 * @param array<string, FinalVal | ParametricValue> $xs
	 */
	private static function assertBindInArguments(BindVal $bind, array $xs): void
	{
		if ( ! array_key_exists($bind->getBindName(), $xs)) {
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
		$binds = array_map(static function(BindVal $x): string { return $x->getBindName(); }, $binds);
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
		if ( ! $val instanceof FinalVal
				&& ! $val instanceof self) { // @phpstan-ignore instanceof.alwaysTrue, booleanAnd.alwaysFalse
			throw new InvalidArgumentException("Argument '{$key}' must be package into FinalVal or ParametricValue.");
		}
	}



	function __toString(): string
	{
		$binds = implode(', ', array_map(static function($x) { return $x->getBindName(); }, $this->binds));
		return "CallableValue: {$this->expr} [$binds]";
	}

}
