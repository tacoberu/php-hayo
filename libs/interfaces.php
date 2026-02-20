<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

interface SymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc;

}



/**
 * Provider, který poskytuje toto rozhraní bude použit symbol bez namespace.
 */
interface ShortSymbolProvider
{

	/**
	 * @retrun list<string>
	 */
	function getShortSymbolTable(): array;

}



interface BuildinFunc extends Applicable
{

	/**
	 * Které argumenty to vyžaduje.
	 * @return list<BindVal>
	 */
	function getBinds(): array;



	/**
	 * Předáme požadované argumenty a vypočítáme výsledek. Argumenty už musí
	 * být finální hodnoty.
	 * @param array<string, FinalVal> $args
	 */
	function apply(array $args): Value;

}



interface Cache
{

	/**
	 * @param callable $cb
	 * @return FinalVal | ParametricValue
	 */
	function load(string $key, $cb);

}
