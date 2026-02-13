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


/**
 * `strings.len src: Str :: Int` - Délka řetězce.
 * `strings.split sep: Str, src: Str :: List<Str>` - Rozdělení řetězce podle separátoru.
 * `strings.concat sep: Str, src: List<Str> :: Str` - Spojení seznamu řetězců se separátorem.
 */
class BuildinStringFunction implements BuildinFunc
{

	private string $name;

	function __construct(string $name)
	{
		$this->name = $name;
	}



	function type(): string
	{
		switch ($this->name) {
			case 'strings.len':
				return 'Int';

			case 'strings.split':
				return 'List<Str>';

			case 'strings.concat':
				return 'Str';

			default:
				throw new LogicException("Unsupported function: {$this->name}.");
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
	 * Které argumenty to vyžaduje.
	 * @return list<BindVal>
	 */
	function getBinds(): array
	{
		switch ($this->name) {
			case 'strings.len':
				return [
					new BindVal('src', 'Str'),
				];

			case 'strings.split':
				return [
					new BindVal('sep', 'Str'),
					new BindVal('src', 'Str'),
				];

			case 'strings.concat':
				return [
					new BindVal('sep', 'Str'),
					new BindVal('src', 'List<Str>'),
				];

			default:
				throw new LogicException("Unsupported operator: {$this->name}.");
		}
	}



	/**
	 * Předáme požadované argumenty a vypočítáme výsledek. Argumenty už musí
	 * být finální hodnoty.
	 * @param array<string, Term> $args
	 */
	function apply(array $args): Term
	{
		$args = array_values($args);
		$args = array_map(static function (Term $x) {
			return $x instanceof FinalVal
				? $x->unpack()
				: $x;
		}, $args);
		switch ($this->name) {
			case 'strings.split':
				return new FinalVal(array_map(static function (string $x): FinalVal {
					return new FinalVal($x, 'Str');
				}, self::applySplit($args)), 'List'); // @phpstan-ignore argument.type

			case 'strings.len':
				return new FinalVal(self::applyLen($args), 'Int'); // @phpstan-ignore argument.type

			case 'strings.concat':
				return new FinalVal(self::applyConcat($args), 'Str'); // @phpstan-ignore argument.type

			default:
				throw new LogicException("Comming soon: {$this->name}");
		}
	}



	/**
	 * @param array{0: non-empty-string, 1: non-empty-string} $args
	 * @return list<string>
	 */
	private static function applySplit(array $args): array
	{
		self::assertArgumentExist($args, 0, 'sep: Str');
		self::assertArgumentExist($args, 1, 'src: Str');
		return explode($args[0], $args[1]);
	}



	/**
	 * @param array{0: string} $args
	 */
	private static function applyLen(array $args): int
	{
		self::assertArgumentExist($args, 0, 'src: Str');
		return strlen($args[0]);
	}



	/**
	 * @param array{0: string, 1: list<string>} $args
	 */
	private static function applyConcat(array $args): string
	{
		self::assertArgumentExist($args, 0, 'sep: Str');
		self::assertArgumentExist($args, 1, 'src: List<Str>');
		return implode($args[0], $args[1]);
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
