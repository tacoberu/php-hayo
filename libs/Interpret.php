<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;


final class Interpret
{

	/**
	 * @param string|FinalValue|self|BindValue| Value $src
	 * @param array<string, FinalValue | ParametricValue> $lets
	 * @return FinalValue | ParametricValue
	 */
	static function applyAny($src, array $lets)
	{
		switch (True) {
			case is_string($src):
				// @TODO validace
				return $lets[$src];

			case $src instanceof BindValue:
				//~ self::assertBindInArguments($src, $lets);
				$value = $lets[$src->getName()];
				if ( ! $value instanceof FinalValue) {
					throw new LogicException("Comming soon...");
				}
				if ($src->isPath()) {
                    return self::selectByPath($src, $value);
                }
				return $value;

			case $src instanceof ParametricValue:
				// @TODO some restriction so that not all lets are sent, only those present in getBindNames()
				return $src->apply($lets);

			case $src instanceof Expr:
				return self::applyExpr($src, $lets);

			case $src instanceof Form && $src->getName() === 'if-then-else':
				return self::applyFormIfThenElse($src, $lets);

			case $src instanceof Composite && $src->type() === Composite::TypeDict:
				return self::applyStructDict($src, $lets);

			case $src instanceof Composite && $src->type() === Composite::TypeList:
				return self::applyStructList($src, $lets);

			case $src instanceof Composite && $src->type() === Composite::TypeTuple:
				return self::applyStructTuple($src, $lets);

			case $src instanceof Scalar:
				return new FinalValue($src->getValue(), '?');

			case $src instanceof FinalValue:
				return $src;

			default:
				throw new LogicException("Unsupported applicable token: " . get_debug_type($src) . ".");
		}
	}



	/**
	 * @param array<string, FinalValue | ParametricValue> $lets
	 * @return FinalValue | ParametricValue
	 */
	private static function applyExpr(Expr $expr, array $lets)
	{
		$items = $expr->getItems();
		foreach ($items as $i => $x) {
			if ($x instanceof BuildinFunc) {
				// pass
			}
			elseif ($x instanceof ParametricValue) {
				// pass
			}
			else {
				$items[$i] = self::applyAny($x, $lets);
			}
		}

		// function call
		if ($items[0] instanceof BuildinFunc) {
			$fn = array_shift($items);
			//~ $items = array_map(function($x) {
				//~ return new LazyValue($x);
			//~ }, $items);
			return $fn->apply($items); // @phpstan-ignore method.nonObject
		}
		// operator call
		if (isset($items[1]) && $items[1] instanceof BuildinFunc) {
			$fn1 = array_shift($items);
			$fn = array_shift($items);
			return $fn->apply(array_merge([$fn1], $items)); // @phpstan-ignore method.nonObject
		}

		throw new LogicException("oops.");
		// The result can be an expression, but also a value
		//~ return $items[0];
	}



	/**
	 * @param array<string, FinalValue | ParametricValue> $lets
	 * @return FinalValue | ParametricValue
	 */
	private static function applyFormIfThenElse(Form $src, array $lets)
	{
		foreach ($src->getItems() as $block) {
			/** @var object{cond: mixed, expr: mixed} $block */
			if ($block->cond === Null) {
				return self::applyAny($block->expr, $lets);
			}
			$cond = self::applyAny($block->cond, $lets);
			if ($cond->unpack()) {
				return self::applyAny($block->expr, $lets);
			}
		}
		throw new LogicException("oops.");
	}



	/**
	 * @param array<string, FinalValue | ParametricValue> $lets
	 */
	private static function applyStructList(Composite $expr, array $lets): FinalValue
	{
		$items = [];
		foreach ((array) $expr->getItems() as $k => $x) {
			$items[$k] = self::applyAny($x, $lets);
		}
		return new FinalValue($items, 'List');
	}



	/**
	 * @param array<string, FinalValue | ParametricValue> $lets
	 */
	private static function applyStructDict(Composite $expr, array $lets): FinalValue
	{
		$items = [];
		foreach ((array) $expr->getItems() as $k => $x) {
			$items[$k] = self::applyAny($x, $lets);
		}

		return new FinalValue((object) $items, 'Dict');
	}



	/**
	 * @param array<string, FinalValue | ParametricValue> $lets
	 */
	private static function applyStructTuple(Composite $expr, array $lets): FinalValue
	{
		$items = [];
		foreach ((array) $expr->getItems() as $k => $x) {
			$items[$k] = self::applyAny($x, $lets);
		}

		return new FinalValue((object) $items, 'Tuple');
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
			if (!isset($curr->{$x})) {
				return new FinalValue(Null, '?');
			}
			$curr = $curr->{$x};
		}
		return new FinalValue($curr, '?');
	}

}
