<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;
use InvalidArgumentException;


class ListsProvider implements SymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		if (! in_array($symbol, ['len', 'first', 'at', 'exist',], True)) {
			return Null;
		}

		return new ListFunc($symbol);
	}

}



/**
 * `list.len src: List<a> :: Int` - Délka seznamu.
 * `list.first src: List<a> :: a` - První prvek ze seznamu.
 * `list.at index: Int, src: List<a> :: a` - Vrácení hodnoty z konktérního indexu.
 * `list.exist index: Int, src: List<a> :: Bool` - Zda na konkrétním indexu je nějaký prvek.
 * `list.split` - ...
 * `list.slice` - ...
 * `list.fold` - ...
 * `list.find` - ...
 * `list.concat` - ...
 * `list.map` - ...
 */
class ListFunc implements BuildinFunc
{

	private string $name;

	function __construct(string $name)
	{
		$this->name = $name;
	}



	function type(): string
	{
		switch ($this->name) {
			case 'list.len':
			case 'len':
				return 'Int';

			case 'list.first':
			case 'list.at':
			case 'first':
			case 'at':
				return 'a';

			case 'list.exist':
			case 'exist':
				return 'Bool';

			default:
				throw new LogicException("Unsupported function: {$this->name}.");
		}
	}



	/**
	 * Které argumenty to vyžaduje.
	 * @return list<BindVal>
	 */
	function getBinds(): array
	{
		switch ($this->name) {
			case 'list.len':
			case 'len':
				return [
					new BindVal('src', 'List<a>'),
				];

			case 'list.first':
			case 'first':
				return [
					new BindVal('src', 'List<a>'),
					new BindVal('default', 'a'),
				];

			case 'list.at':
			case 'at':
				return [
					new BindVal('index', 'Int'),
					new BindVal('src', 'List<a>'),
					new BindVal('default', 'a'),
				];

			case 'list.exist':
			case 'exist':
				return [
					new BindVal('index', 'Int'),
					new BindVal('src', 'List<a>'),
				];

			default:
				throw new LogicException("Unsupported operator: {$this->name}.");
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
			case 'list.len':
			case 'len':
				self::assertArgumentExist($args, 0, 'src: List<a>');
				return new FinalValue(count($args[0]), 'Int');

			case 'list.first':
			case 'first':
				self::assertArgumentExist($args, 0, 'src: List<a>');
				self::assertArgumentExist($args, 1, 'default: a');
				$xs = $args[0];
				if (count($xs) > 0) {
					return new FinalValue($xs[0], 'a');
				}
				return new FinalValue($args[1], 'a');

			case 'list.at':
			case 'at':
				self::assertArgumentExist($args, 0, 'index: Int');
				self::assertArgumentExist($args, 1, 'src: List<a>');
				self::assertArgumentExist($args, 2, 'default: a');
				$index = $args[0];
				$xs = $args[1] ?? [];
				return array_key_exists($index, $xs)
					? new FinalValue($xs[$index], 'a')
					: new FinalValue($args[2], 'a');

			case 'list.exist':
			case 'exist':
				self::assertArgumentExist($args, 0, 'index: Int');
				self::assertArgumentExist($args, 1, 'src: List<a>');
				$index = $args[0];
				$xs = $args[1];
				return new FinalValue(array_key_exists($index, $xs), 'Bool');

			default:
				throw new LogicException("Unsupported operator: {$this->name}.");
		}
	}



	/**
	 * @param array<int, mixed> $src
	 */
	private static function assertArgumentExist(array $src, int $index, string $label): void
	{
		if ( ! array_key_exists($index, $src)) {
			throw new InvalidArgumentException("Missing {$index}'th argument '{$label}'.");
		}
	}



	function __toString(): string
	{
		return '<' . $this->name . ' ' . implode(' ', $this->refs()) . '>';
	}

}
