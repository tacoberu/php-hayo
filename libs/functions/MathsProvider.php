<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;


class MathsProvider implements SymbolProvider, ShortSymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		if ( ! in_array($symbol, ['+', '-', '*', 'div', 'mod',
				'ceil', 'floor', 'round'
				], True)) {
			return Null;
		}

		return new MathOperator($symbol);
	}



	/**
	 * @retrun list<string>
	 */
	function getShortSymbolTable(): array
	{
		return ['+', '-', '*', 'div', 'mod'];
	}

}



/**
 * `a: Int + b :: Int` - Sčítání
 * `a: Int - b :: Int` - Odčítání
 * `a: Int * b :: Int` - Násobení
 * `a: Int div b :: Int` - Celočíselné dělení
 * `a: Int mod b :: Int` - Zbytek po celočíselném dělení.
 */
class MathOperator implements BuildinFunc
{

	private string $op;

	function __construct(string $op)
	{
		$this->op = $op;
	}



	function type(): string
	{
		return 'Int';
	}



	/**
	 * @return list<string>
	 */
	function refs(): array
	{
		return array_map(static function (BindVal $x): string {
			return $x->getBindName();
		}, $this->getBinds());
	}



	/**
	 * Které argumenty to vyžaduje.
	 * @return list<BindVal>
	 */
	function getBinds(): array
	{
		switch ($this->op) {
			case '+':
			case '-':
			case '*':
			case 'div':
			case 'mod':
				return [
					new BindVal('a', 'Int'),
					new BindVal('b', 'Int'),
				];

			default:
				throw new LogicException("Unsupported operator: {$this->op}.");
		}
	}



	/**
	 * Předáme požadované argumenty a vypočítáme výsledek. Argumenty už musí
	 * být finální hodnoty.
	 * @param array<string, Term> $args
	 */
	function apply(array $args): Value
	{
		$args = array_values($args);
		$args = array_map(static function (Value $x) {
			return $x instanceof FinalVal
				? $x->unpack()
				: $x;
		}, $args);
		switch ($this->op) {
			case '+':
				return new FinalVal($args[0] + $args[1], $this->type());

			case '-':
				return new FinalVal($args[0] - $args[1], $this->type());

			case '*':
				return new FinalVal($args[0] * $args[1], $this->type());

			case 'div':
				return new FinalVal(intdiv($args[0], $args[1]), $this->type());

			case 'mod':
				return new FinalVal($args[0] % $args[1], $this->type());

			case 'ceil':
				return new FinalVal((int) ceil($args[0]), $this->type());

			case 'floor':
				return new FinalVal((int) floor($args[0]), $this->type());

			case 'round':
				return new FinalVal(round($args[0], $args[1]), $this->type());

			default:
				throw new LogicException("Unsupported operator: {$this->op}.");
		}
	}



	function __toString(): string
	{
		return '<' . $this->op . ' ' . implode(' ', $this->refs()) . '>';
	}

}
