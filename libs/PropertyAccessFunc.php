<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use stdClass;


/**
 * Runtime counterpart of `PropertyAccess` (tacoberu/hayo-ast): extracts one
 * field from whatever value its base evaluates to — `(List.first xs Null).product`,
 * `[1, 2].len`, `{a: 1}.a`.
 *
 * Unlike a real BuildinFunc, this one is never looked up by symbol/namespace —
 * `Compiler` instantiates it directly while casting a `PropertyAccess` node
 * (see `Compiler::castPropertyAccess()`), the same way it wraps an operator
 * or a function call into an `Expr`. Missing field or a non-record base
 * resolve to `Null`, matching the leniency of `Interpret::selectByPath()`
 * used for `x.foo` on a plain bareword symbol.
 */
final class PropertyAccessFunc implements BuildinFunc
{

	private string $field;

	function __construct(string $field)
	{
		$this->field = $field;
	}



	function type(): string
	{
		return '?';
	}



	/**
	 * @return list<BindValue>
	 */
	function getBinds(): array
	{
		return [new BindValue('src', '?')];
	}



	/**
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value
	{
		TypeValidator::assertArguments((string) $this, $this->getBinds(), $args);
		$src = array_values($args)[0];
		$val = $src->unpack();
		if ($val instanceof stdClass && isset($val->{$this->field})) {
			return new FinalValue($val->{$this->field}, '?');
		}
		return new FinalValue(Null, '?');
	}



	function __toString(): string
	{
		return "<.{$this->field}>";
	}

}
