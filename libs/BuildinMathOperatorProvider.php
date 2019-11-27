<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

class BuildinMathOperatorProvider implements SymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		if ( ! in_array($symbol, ['+', '-', '*', 'div', 'mod'], True)) {
			return Null;
		}

		return new BuildinMathOperator($symbol);
	}

}
