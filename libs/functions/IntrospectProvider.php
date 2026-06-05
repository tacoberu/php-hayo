<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

class IntrospectProvider implements SymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		switch ($symbol) {
			case 'of': return new IntrospectFunc('of');
			case 'is': return new IntrospectFunc('is');
			default: return Null;
		}
	}

}



class IntrospectFunc implements BuildinFunc
{

	const Name = 'Introspect';

	private string $name;

	function __construct(string $name)
	{
		$this->name = $name;
	}



	function getQualifiedName(): string
	{
		return self::Name . '.' . $this->name;
	}



	function type(): string
	{
		switch ($this->name) {
			case 'of': return 'Str';
			case 'is': return 'Bool';
			default: return '?';
		}
	}



	/**
	 * @return list<BindValue>
	 */
	function getBinds(): array
	{
		switch ($this->name) {
			case 'of': return [new BindValue('src', '?')];
			case 'is': return [new BindValue('src', '?'), new BindValue('type', 'Str')];
			default: return [];
		}
	}



	/**
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value
	{
		$a = array_values($args);
		switch ($this->name) {
			case 'of': return self::applyOf($a[0]);
			case 'is': return self::applyIs($a[0], $a[1]);
			default: throw SymbolNotFound::UnsupportedFunc(self::Name, $this->name);
		}
	}



	/**
	 * Returns the type name of a value.
	 * @signature "src: ? -> Str"
	 */
	private static function applyOf(FinalValue $src): FinalValue
	{
		return new FinalValue($src->type(), 'Str');
	}



	/**
	 * Returns true if the value is of the given type.
	 * @signature "src: ?, type: Str -> Bool"
	 */
	private static function applyIs(FinalValue $src, FinalValue $type): FinalValue
	{
		return new FinalValue($src->type() === $type->unpack(), 'Bool');
	}



	function __toString(): string
	{
		return '<' . self::Name . '.' . $this->name . '>';
	}

}
