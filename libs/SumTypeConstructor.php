<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * BuildinFunc representing one constructor of a sum type (e.g. Shape.Circle).
 *
 * Needed because constructors with arguments (Shape.Circle 3.14) go through the
 * same partial-evaluation and argument-binding machinery as any other function.
 * getBinds() tells the compiler how many arguments to expect and what types they
 * carry; apply() wraps the collected FinalValues into a SumTypeValue.
 *
 * Zero-argument variants are handled by the same class: getBinds() returns [],
 * so the compiler evaluates them immediately without waiting for arguments.
 */
class SumTypeConstructor implements BuildinFunc
{

	/**
	 * @var string
	 */
	private $typeName;

	/**
	 * @var string
	 */
	private $variant;

	/**
	 * @var list<string>
	 */
	private $argTypeNames;

	/**
	 * @param list<string> $argTypeNames
	 */
	function __construct(string $typeName, string $variant, array $argTypeNames)
	{
		$this->typeName = $typeName;
		$this->variant = $variant;
		$this->argTypeNames = $argTypeNames;
	}



	function getQualifiedName(): string
	{
		return "{$this->typeName}.{$this->variant}";
	}



	function type(): string
	{
		return $this->typeName;
	}



	/**
	 * @return list<BindValue>
	 */
	function getBinds(): array
	{
		$binds = [];
		foreach ($this->argTypeNames as $i => $typeName) {
			$binds[] = new BindValue("a{$i}", $typeName ?: '?');
		}
		return $binds;
	}



	/**
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value
	{
		$payload = array_values($args);
		return new FinalValue(
			new SumTypeValue($this->typeName, $this->variant, $payload),
			$this->typeName
		);
	}



	function __toString(): string
	{
		return "<{$this->typeName}.{$this->variant}>";
	}

}
