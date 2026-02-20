<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;


class PredicatesProvider implements SymbolProvider, ShortSymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		$symbol = strtolower($symbol);
		if (! in_array($symbol, ['==', '!=', '<', '>', '<=', '>=', 'and', 'or', '&&', '||', 'not', 'in', 'has', 'superset', 'subset', 'intersects',], True)) {
			return Null;
		}

		return new PredicateFunction($symbol);
	}



	/**
	 * @retrun list<string>
	 */
	function getShortSymbolTable(): array
	{
		return ['==', '!=', '<', '>', '<=', '>=',
			'and', 'or', '&&', '||', 'not',
			'in', 'has', 'superset', 'subset', 'intersects',
			];
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
		$this->name = strtolower($name);
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
			case 'and':
			case 'or':
			case '&&':
			case '||':
			case 'in': // prvek left je ve množině right
			case 'has': // v množině left je prvek right
			case 'superset': // existuje alespoň jeden prvek v left, který je také v right. @todo ta definice je divná
			case 'subset': // existuje alespoň jeden prvek v right, který je také v left @todo ta definice je divná
			case 'intersects': // množiny left a right mají alespon jeden společný prvek.
				return 'Bool';

			default:
				throw new LogicException("Unsupported predicate: {$this->name}.");
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
			case 'and':
			case 'or':
			case '&&':
			case '||':
				return [
					new BindVal('a', '?'),
					new BindVal('b', '?'),
				];

			case 'in': // prvek left je ve množině right
				return [
					new BindVal('a', 'a'),
					new BindVal('b', 'List<a>'),
				];

			case 'has': // v množině left je prvek right
				return [
					new BindVal('a', 'List<a>'),
					new BindVal('b', 'a'),
				];

			case 'superset': // existuje alespoň jeden prvek v left, který je také v right.
			case 'subset': // existuje alespoň jeden prvek v right, který je také v left
			case 'intersects': // množiny left a right mají alespon jeden společný prvek.
				return [
					new BindVal('a', 'List<a>'),
					new BindVal('b', 'List<a>'),
				];

			default:
				throw new LogicException("Unsupported predicate: {$this->name}.");
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

			case 'and':
			case '&&':
				return new FinalVal($args[0] && $args[1], 'Symbol');

			case 'or':
			case '||':
				return new FinalVal($args[0] || $args[1], 'Symbol');

			case 'not':
				return new FinalVal( ! $args[0], 'Symbol');

			// prvek left je ve množině right
			case 'in':
				return new FinalVal(in_array($args[0], $args[1], True), 'Symbol');

			// v množině left je prvek right
			case 'has':
				return new FinalVal(in_array($args[1], $args[0], True), 'Symbol');

			// Existuje alespoň jeden prvek v left, který je také v right.
			case 'superset':
				throw new LogicException("Comming soon... (2026.02.18 23:58:07 CET)");

			// Existuje alespoň jeden prvek v right, který je také v left
			case 'subset':
				throw new LogicException("Comming soon... (2026.02.18 23:58:07 CET)");

			// Množiny left a right mají alespon jeden společný prvek.
			case 'intersects':
				throw new LogicException("Comming soon... (2026.02.18 23:58:07 CET)");

			default:
				throw new LogicException("Unsupported predicate: {$this->name}.");
		}
	}



	function __toString(): string
	{
		return '<' . $this->name . ' ' . implode(' ', $this->refs()) . '>';
	}

}
