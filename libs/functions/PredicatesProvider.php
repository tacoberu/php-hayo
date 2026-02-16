<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;


class PredicatesProvider implements SymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		$symbol = strtoupper($symbol);
		if (! in_array($symbol, ['==', '!=', '<', '>', '<=', '>=', 'AND', 'OR', '&&', '||', 'IN', 'HAS', 'SUPERSET', 'SUBSET', 'INTERSECTS',], True)) {
			return Null;
		}

		return new PredicateFunction($symbol);
	}

}



/**
 * `==` - Je rovno.
 * `!=` - Není rovno.
 * `<` - Menší jak.
 * `>` - Větší jak.
 * `<=` - Menší nebo rovno jak.
 * `>=` - Větší nebo rovno jak.
 * `IN` - prvek left je ve množině right.
 * `HAS` - v množině left je prvek right.
 * `SUPERSET` - Všechny prvky z pravé množiny jsou v levé.
 * `SUBSET` - Všechny prvky z levé množiny jsou v pravé
 * `INTERSECTS` - Množiny left a right mají alespon jeden společný prvek.
 */
class PredicateFunction implements BuildinFunc
{

	private string $name;

	function __construct(string $name)
	{
		$this->name = $name;
	}



	function type(): string
	{
		switch ($this->name) {
			case '==':
			case '!=':
			case '<':
			case '>':
			case '<=':
			case '>=':
			case 'AND':
			case 'OR':
			case '&&':
			case '||':
			case 'IN': // prvek left je ve množině right
			case 'HAS': // v množině left je prvek right
			case 'SUPERSET': // Existuje alespoň jeden prvek v left, který je také v right. @TODO Ta definice je divná
			case 'SUBSET': // Existuje alespoň jeden prvek v right, který je také v left @TODO Ta definice je divná
			case 'INTERSECTS': // Množiny left a right mají alespon jeden společný prvek.
				return 'Bool';

			default:
				throw new LogicException("Unsupported operator: {$this->name}.");
		}
	}



	/**
	 * Které argumenty to vyžaduje.
	 * @return list<BindVal>
	 */
	function getBinds(): array
	{
		switch ($this->name) {
			case '==':
			case '!=':
			case '<':
			case '>':
			case '<=':
			case '>=':
			case 'AND':
			case 'OR':
			case '&&':
			case '||':
				return [
					new BindVal('a', '?'),
					new BindVal('b', '?'),
				];

			case 'IN': // prvek left je ve množině right
				return [
					new BindVal('a', 'a'),
					new BindVal('b', 'List<a>'),
				];

			case 'HAS': // v množině left je prvek right
				return [
					new BindVal('a', 'List<a>'),
					new BindVal('b', 'a'),
				];

			case 'SUPERSET': // Existuje alespoň jeden prvek v left, který je také v right.
			case 'SUBSET': // Existuje alespoň jeden prvek v right, který je také v left
			case 'INTERSECTS': // Množiny left a right mají alespon jeden společný prvek.
				return [
					new BindVal('a', 'List<a>'),
					new BindVal('b', 'List<a>'),
				];

			default:
				throw new LogicException("Unsupported operator: {$this->op}.");
		}
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
	 * Předáme požadované argumenty a vypočítáme výsledek. Argumenty už musí
	 * být finální hodnoty.
	 * @param array<string, FinalVal> $args
	 */
	function apply(array $args): Value
	{
		$args = array_values($args);
		$args = array_map(static function(Value $x) {
			return $x instanceof FinalVal
				? $x->unpack()
				: $x;
		}, $args);

		switch ($this->name) {
			case '==':
				return new FinalVal($args[0] === $args[1], 'Symbol');

			case '!=':
				return new FinalVal($args[0] !== $args[1], 'Symbol');

			case '>':
				return new FinalVal($args[0] > $args[1], 'Symbol');

			case '<':
				return new FinalVal($args[0] < $args[1], 'Symbol');

			case '<=':
				return new FinalVal($args[0] <= $args[1], 'Symbol');

			case '>=':
				return new FinalVal($args[0] >= $args[1], 'Symbol');

			case 'AND':
			case '&&':
				return new FinalVal($args[0] && $args[1], 'Symbol');

			case 'OR':
			case '||':
				return new FinalVal($args[0] || $args[1], 'Symbol');

			// prvek left je ve množině right
			case 'IN':
				return new FinalVal(in_array($args[0], $args[1], True), 'Symbol');

			// v množině left je prvek right
			case 'HAS':
				return new FinalVal(in_array($args[1], $args[0], True), 'Symbol');

			// Existuje alespoň jeden prvek v left, který je také v right.
			case 'SUPERSET':
				throw new LogicException("Comming soon... (2026.02.18 23:58:07 CET)");

			// Existuje alespoň jeden prvek v right, který je také v left
			case 'SUBSET':
				throw new LogicException("Comming soon... (2026.02.18 23:58:07 CET)");

			// Množiny left a right mají alespon jeden společný prvek.
			case 'INTERSECTS':
				throw new LogicException("Comming soon... (2026.02.18 23:58:07 CET)");

			default:
				throw new LogicException("Unsupported operator: {$this->name}.");
		}
	}



	function __toString(): string
	{
		return '<' . $this->name . ' ' . implode(' ', $this->refs()) . '>';
	}

}
