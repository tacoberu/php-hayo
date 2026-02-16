<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * Val = ValScalar String String
 *     | ValList List String
 *     | ValDict List String Val
 *     | ValFn Fn String
 *     | ValExpr Fn String
 * class Val a where
 *     getTypeName :: a -> String
 * instance Val ValScalar where
 *     getTypeName (ValScalar a) = a `at` 1
 */

interface Val
{

	function getTypeName(): string;



	/**
	 * @return mixed
	 */
	function unpack();

}



interface SymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc;

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
