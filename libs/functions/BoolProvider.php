<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * Built-in provider for the Bool type.
 *
 * Registers True and False as zero-arg constructors under the 'Bool' namespace.
 * Implements ShortSymbolProvider so the compiler maps bare 'True'/'False' symbols
 * to 'Bool.True'/'Bool.False' via the short-name table, giving them the same
 * resolution path as user-declared sum type constructors (e.g. Color.Red).
 *
 * Symbol names are case-sensitive following the Hayo convention: types and
 * constructors start with an uppercase letter, functions and operators with
 * lowercase. Therefore only 'True'/'False' are recognised, not 'true'/'false'.
 *
 * Constructors return FinalValue(bool, 'Bool') — not SumTypeValue — so all
 * existing boolean operators (&&, ||, not, if-then-else) continue to work
 * against native PHP booleans without modification.
 */
class BoolProvider implements SymbolProvider, ShortSymbolProvider
{

	/**
	 * @return list<string>
	 */
	function getShortSymbolTable(): array
	{
		return ['True', 'False'];
	}



	function lookup(string $symbol): ?BuildinFunc
	{
		switch ($symbol) {
			case 'True':
				return new BoolConstructor(True);

			case 'False':
				return new BoolConstructor(False);

			default:
				return Null;
		}
	}

}



class BoolConstructor implements BuildinFunc
{

	/**
	 * @var bool
	 */
	private $value;

	function __construct(bool $value)
	{
		$this->value = $value;
	}



	function getQualifiedName(): string
	{
		return $this->value
			? 'Bool.True'
			: 'Bool.False';
	}



	function type(): string
	{
		return 'Bool';
	}



	/**
	 * Which arguments are required.
	 * @return list<BindValue>
	 */
	function getBinds(): array
	{
		return [];
	}



	/**
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value
	{
		return new FinalValue($this->value, 'Bool');
	}



	function __toString(): string
	{
		return $this->value
			? '<Bool.True>'
			: '<Bool.False>';
	}

}
