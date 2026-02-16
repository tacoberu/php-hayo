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
 * Pomocná třída sloužící jako banka vázaných symbolů. Sémantická podpora
 * pro klonování -> zanořování, a přepisování symbolů.
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
	 * Ve vnějším kontextu je jako `a` nabindována 1. Ale mi ji chceme předat jako
	 * argument. Když tuto hodnotu z kontextu odstraníme, budeme to chápat, jako hodnotu,
	 * kterou máme dostat z vnějšku, při volání lambdy.
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
