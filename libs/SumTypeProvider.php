<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * Symbol namespace for one declared sum type, registered in $libs under the type name.
 *
 * Needed because symbol resolution splits 'Color.Green' on the dot and dispatches
 * to $libs['Color']->lookup('Green'). Without this provider the compiler cannot find
 * constructors at all — SumTypeValue only exists at runtime, after a constructor was called.
 *
 * Also implements TypeDescriptor so HayoEngine::gauseType() can identify a SumTypeValue
 * passed as an external argument.
 */
class SumTypeProvider implements SymbolProvider, TypeDescriptor
{

	/**
	 * @var string
	 */
	private $typeName;

	/**
	 * variant name => list of argument type-name strings
	 * @var array<string, list<string>>
	 */
	private $variants;

	/**
	 * @param array<string, list<string>> $variants
	 */
	function __construct(string $typeName, array $variants)
	{
		$this->typeName = $typeName;
		$this->variants = $variants;
	}



	function getTypeName(): string
	{
		return $this->typeName;
	}



	function lookup(string $symbol): ?BuildinFunc
	{
		if (isset($this->variants[$symbol])) {
			return new SumTypeConstructor($this->typeName, $symbol, $this->variants[$symbol]);
		}
		return Null;
	}

}
