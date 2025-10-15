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
 * Hodnota vyžaduje nějaké argumenty. Výsledek je tedy třeba vypočítat.
 * Při volání této funkce už musí být všechny závislosti vyřešeny, nejpozději
 * předanými argumenty.
 *
 * V $binds jsou uloženy jak vyřešené, tak nevyřešené závislosti.
 *
 * @TODO rename CallableVal
 */
class VariadicVal implements Val, HasRefs, Term
{

	/**
	 * @var Expr | StructDict | StructList | StructTuple
	 */
	private $expr;

	private string $type;

	/**
	 * @var list<BindVal>
	 */
	private array $binds;

	/**
	 * @param Expr | StructDict | StructList | StructTuple $expr
	 * @param list<BindVal> $binds
	 */
	private function __construct($expr, string $type, array $binds)
	{
		$this->expr = $expr;
		$this->type = $type;
		$this->binds = $binds;
	}



	/**
	 * Všechny nevyřešené závislosti $expr musí být podchyceny v $binds.
	 *
	 * @param list<BindVal> $binds
	 */
	static function expr(Expr $expr, string $type, array $binds): self
	{
		$refs = $expr->refs();
		$binds2 = [];
		foreach ($binds as $x) {
			if (array_search($x->getBindName(), $refs, True) === False) {
				throw new InvalidArgumentException("The remaining argument: '{$x->getBindName()}'.");
			}
			$binds2[] = $x->getBindName();
		}
		foreach ($refs as $x) {
			if (array_search($x, $binds2, True) === False) {
				throw new InvalidArgumentException("Missing argument: '{$x}'.");
			}
		}
		return new self($expr, $type, $binds);
	}



	/**
	 * @param list<BindVal> $binds
	 */
	static function dict(StructDict $expr, string $type, array $binds): self
	{
		// @TODO Nějaká validace
		return new self($expr, $type, $binds);
	}



	function type(): string
	{
		return $this->type;
	}



	function getTypeName(): string
	{
		return $this->type;
	}



	/**
	 * @return mixed
	 */
	function unpack()
	{
		return "<lambda> -> {$this->type}";
	}



	/**
	 * Které argumenty to vyžaduje.
	 * @return list<BindVal>
	 */
	function getBinds(): array
	{
		return $this->binds;
	}



	/**
	 * Závisí na nějakých symbolech, které se nám nepodařilo získat.
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



	/**
	 * @param array<string, Val> $args
	 */
	function apply(array $args): Term
	{
		foreach ($args as $key => $val) {
			if ( ! is_string($key)) { // @phpstan-ignore function.alreadyNarrowedType
				throw new InvalidArgumentException("Argument must be with name.");
			}
			if ( ! $val instanceof Val) { // @phpstan-ignore instanceof.alwaysTrue
				throw new InvalidArgumentException("Argument '{$key}' must be package into Val.");
			}
		}

		// @TODO Přidat validaci, zda jsem předal správný počet prvků.
		switch (True) {
			case $this->expr instanceof Expr:
				return self::applyExpr($this->expr, $args);// @phpstan-ignore return.type

			case $this->expr instanceof StructDict:
				$lets = array_merge([], $args);
				return self::applyStructDict($this->expr, $lets);// @phpstan-ignore return.type

			default:
				throw new LogicException("oops.");
		}
	}



	/**
	 * @param array<string, Val> $lets
	 */
	private static function applyExpr(Expr $expr, array $lets): Val
	{
		$items = $expr->getItems();
		foreach ($items as $i => $x) {
			switch (True) {
				case is_string($x):
					// @TODO validace
					$items[$i] = $lets[$x];
					break;

				case $x instanceof self:
					// @TODO nějaké omezení, aby se neposílaly všeechny lets, ale jen ty, co jsou v getBindNames()
					$items[$i] = $x->apply($lets);
					break;

				// funkce na úrovni expression, bude pravděpobodně to ta první. Ta nemá žádné závislosti.
				// naopak, nejdříve se musí vyřešit závislosti ze stejné úrovně - což právě děláme.
				case $x instanceof BuildinFunc:
					break;

				case $x instanceof FinalVal:
					break;

				default:
					dump(['@' . __method__ . ':' . __line__, $x]);
					throw new LogicException("oops.");
			}
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
	 * @param array<string, Val> $lets
	 */
	private static function applyStructDict(StructDict $expr, array $lets): Val
	{
		$items = [];
		foreach ($expr->getItems() as $k => $x) {
			switch (True) {
				case $x instanceof BindVal:
					$x = $lets[$x->getBindName()];
					if ( ! $x instanceof FinalVal) {
						throw new LogicException("Comming soon...");
					}
					$items[$k] = $x;
					break;

				case $x instanceof self:
					$args2 = [];
					foreach ($x->refs() as $x2) {
						$args2[$x2] = $lets[$x2];
					}
					$items[$k] = $x->apply($args2);
					break;

				case $x instanceof FinalVal:
					$items[$k] = $x;
					break;

				default:
					dump($x);
					throw new LogicException("oops.");
			}
		}

		return new FinalVal((object) $items, 'Dict');
	}



	/**
	 * @param list<string> $args
	 * /
	private static function assertBindInArguments(BindVal $bind, array $args): void
	{
		if ( ! array_key_exists($bind->getBindName(), $args)) {
			throw new LogicException("Missing args: {$bind->getBindName()}.");
		}
	} //*/

}
