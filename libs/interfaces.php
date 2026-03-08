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
 * Provider implementing this interface will use a symbol without a namespace.
 */
interface ShortSymbolProvider
{

	/**
	 * @return list<string>
	 */
	function getShortSymbolTable(): array;

}



interface BuildinFunc extends Applicable
{

	/**
	 * Which arguments are required.
	 * @return list<BindValue>
	 */
	function getBinds(): array;



	/**
	 * Pass the required arguments and compute the result. Arguments must already
	 * be final values.
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value;

}



interface Cache
{

	/**
	 * @param callable $cb
	 * @return FinalValue | ParametricValue
	 */
	function load(string $key, $cb);

}
