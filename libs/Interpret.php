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
					throw new LogicException("Argument '{$src->getName()}' received a partially-applied script (ParametricValue) instead of a final value. Ensure all parameters of the inner script are bound before passing it as a value.");
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

			case $src instanceof Form && $src->getName() === 'match':
				return self::applyFormMatch($src, $lets);

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
	 */
	private static function applyFormMatch(Form $src, array $lets): FinalValue
	{
		$items = $src->getItems();
		$subject = self::applyAny($items[0], $lets);
		assert($subject instanceof FinalValue);

		$val = $subject->getValue();

		foreach (array_slice($items, 1) as $arm) {
			/** @var object{pattern: string, binds: list<string>, expr: Value|string} $arm */
			// Wildcard arm
			if ($arm->pattern === '_') {
				$result = self::applyAny($arm->expr, $lets);
				assert($result instanceof FinalValue);
				return $result;
			}

			if ($val instanceof SumTypeValue) {
				// Match variant name (last segment after the last dot, e.g. 'Shape.Circle' → 'Circle')
				$dotPos = strrpos($arm->pattern, '.');
				$variant = $dotPos !== False
					? (string) substr($arm->pattern, $dotPos + 1)
					: $arm->pattern;

				if ($val->getVariant() === $variant) {
					// Bind payload fields to the bound variable names
					$armLets = $lets;
					$payload = $val->getPayload();
					foreach ($arm->binds as $i => $bindName) {
						$armLets[$bindName] = $payload[$i] ?? new FinalValue(Null, 'Null');
					}
					$result = self::applyAny($arm->expr, $armLets);
					assert($result instanceof FinalValue);
					return $result;
				}
			}
			elseif (self::scalarPatternMatches($arm->pattern, $val)) {
				// Scalar value matching: int, float, string, bool
				$result = self::applyAny($arm->expr, $lets);
				assert($result instanceof FinalValue);
				return $result;
			}
		}

		$label = $val instanceof SumTypeValue
			? "variant '{$val->getVariant()}' of type '{$val->getTypeName()}'"
			: (string) $val;
		throw new LogicException("Non-exhaustive match: no arm matched {$label}.");
	}



	/**
	 * @param mixed $val
	 */
	private static function scalarPatternMatches(string $pattern, $val): bool
	{
		if (is_int($val)) {
			return is_numeric($pattern) && (int) $pattern === $val;
		}
		if (is_float($val)) {
			return is_numeric($pattern) && (float) $pattern === $val;
		}
		if (is_string($val)) {
			// String literal patterns are stored with surrounding quotes
			$len = strlen($pattern);
			if ($len >= 2 && ($pattern[0] === '"' || $pattern[0] === "'")) {
				return (string) substr($pattern, 1, $len - 2) === $val;
			}
			return $pattern === $val;
		}
		if (is_bool($val)) {
			return ($pattern === 'True' && $val) || ($pattern === 'False' && $val === False);
		}
		return False;
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
