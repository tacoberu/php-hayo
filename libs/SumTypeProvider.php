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
 * to $libs['Color']->lookupFunc('Green'). Without this provider the compiler cannot find
 * constructors at all — SumTypeValue only exists at runtime, after a constructor was called.
 *
 * Also implements SumTypeDef (a TypeDef) so the compiler can collect it for
 * the type inferrer and perform exhaustiveness checks.
 */
class SumTypeProvider implements FuncProvider, SumTypeDef
{

	private string $typeName;

	/**
	 * Type parameter names (empty for monomorphic types).
	 * @var list<string>
	 */
	private array $typeParams;

	/**
	 * variant name => list of argument type-name strings
	 * @var array<string, list<string>>
	 */
	private array $variants;

	/**
	 * @param list<string> $typeParams
	 * @param array<string, list<string>> $variants
	 */
	function __construct(string $typeName, array $typeParams, array $variants)
	{
		$this->typeName = $typeName;
		$this->typeParams = $typeParams;
		$this->variants = $variants;
	}



	function getTypeName(): string
	{
		return $this->typeName;
	}



	function getNamespace(): string
	{
		return $this->typeName;
	}



	function lookupFunc(string $symbol): ?BuildinFunc
	{
		if (isset($this->variants[$symbol])) {
			return new SumTypeConstructor($this->typeName, $this->typeParams, $symbol, $this->variants[$symbol]);
		}
		return Null;
	}



	/**
	 * @return list<string>
	 */
	function getVariantNames(): array
	{
		return array_keys($this->variants);
	}



	/**
	 * @return list<string>
	 */
	function getTypeParams(): array
	{
		return $this->typeParams;
	}



	/**
	 * @return list<string>
	 */
	function getVariantArgTypes(string $variant): array
	{
		return $this->variants[$variant] ?? [];
	}

}
