<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use ArrayIterator;


/**
 * Helper class serving as a bank of bound symbols. Semantic support
 * for cloning -> nesting, and overwriting symbols.
 */
final class Context
{

	/**
	 * @var array<string, string | Value>
	 */
	private array $items;

	/**
	 * @param array<string, string | Value> $xs
	 */
	function __construct(array $xs)
	{
		$this->items = $xs;
	}



	static function FromScope(Scope $src): self
	{
		return new self($src->getLets());
	}



	function shadow(string $name, Value $value): void
	{
		$this->items[$name] = $value;
	}



	function shadowAnotherSymbol(string $name, string $value): void
	{
		$this->items[$name] = $value;
	}



	/**
	 * `a = 1;inc = (a) -> a + 1;inc 41`
	 * In the outer context, `a` is bound to 1. But we want to pass it as an
	 * argument. By removing this value from the context, we treat it as a value
	 * to be received from outside when calling the lambda.
	 */
	function shadowByArg(string $name): void
	{
		unset($this->items[$name]);
	}



	/**
	 * @return Value | string | null
	 */
	function selectSymbol(string $name)
	{
		return $this->items[$name] ?? Null;
	}



	/**
	 * @return Value | string
	 */
	function trySelectSymbol(string $name)
	{
		if ($x = $this->selectSymbol($name)) {
			return $x;
		}
		return $name;
	}



    /**
     * @return ArrayIterator<string, string | Value>
     */
	function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->items);
	}

}
