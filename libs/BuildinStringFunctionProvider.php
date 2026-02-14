<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

class BuildinStringFunctionProvider implements SymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		if (strncmp('strings.', $symbol, 8) !== 0) {
			return Null;
		}

		if (! in_array($symbol, ['strings.len', 'strings.split', 'strings.concat',], True)) {
			return Null;
		}

		return new BuildinStringFunction($symbol);
	}

}
