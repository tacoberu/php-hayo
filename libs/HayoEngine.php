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
use stdClass;


final class HayoEngine
{

	/**
	 * @var array<array<string, SymbolProvider>>
	 */
	private array $libs;

	private ?Cache $cache = Null;

	/**
	 * @param array<array<string, SymbolProvider>> $libs
	 */
	function __construct(array $libs)
	{
		$this->libs = $libs;
	}



	static function WithDefaultLibraries(): self
	{
		return new self([
			'predicate' => new PredicatesProvider(),
			'math' => new MathsProvider(),
			'str' => new StringsProvider(),
			'list' => new ListsProvider(),
		]);
	}



	function registerLibrary(string $ns, SymbolProvider $lib): self
	{
		$this->libs[] = $lib;
		return $this;
	}



	function setCache(Cache $adapter): self
	{
		$this->cache = $adapter;
		return $this;
	}



	/**
	 * Zpracová do "bytecode" a případně uloží.
	 * @return FinalVal | VariadicVal
	 */
	function compile(string $code)
	{
		if ($this->cache) {
			$key = self::calculateCacheKey($code, []);
			return $this->cache->load($key, function() use ($code) {
				return $this->compileInner($code);
			});
		}

		return $this->compileInner($code);
	}



	/**
	 * @param list<mixed> | array<string, mixed> $args
	 * @return mixed
	 */
	function evaluate(string $code, array $args = [])
	{
		$expr = $this->compile($code);
		switch (True) {
			case $expr instanceof FinalVal:
				return $expr->unpack();
			case $expr instanceof VariadicVal:
				return $expr
					->apply(self::buildAppliableArguments($expr, $args))
					->unpack();
			default:
				throw new LogicException("oops: {$expr}");
		}
	}



	/**
	 * @return FinalVal | VariadicVal
	 */
	private function compileInner(string $code)
	{
		return $this->getCompiler()->compile($code);
	}



	private function getCompiler(): Compiler
	{
		return new Compiler($this->libs);
	}



	/**
	 * Když předáme argumenty nepojmenované, ale jen jako pole.
	 * Funkce má svou signaturu argumentů. Ve $values máme hodnoty těchto argumentů. Spojíme je podle indexů.
	 * @param list<mixed> $values
	 * @return array<string, mixed>
	 */
	private static function combineBindWithValues(VariadicVal $fn, array $values): array
	{
		$refs = array_map(static function (BindVal $x): string {
			return $x->getBindName();
		}, $fn->getBinds());

		if (count($refs) !== count($values)) {
			$expected = count($refs);
			$passed = count($values);
			throw new InvalidArgumentException("Too few arguments to function {$fn}, {$passed} passed and exactly {$expected} expected.");
		}

		return array_combine($refs, $values);
	}



	/**
	 * Přebalíme argumenty z prostých hodnot do FinalVal
	 *
	 * @param list<mixed> | array<string, mixed> $args
	 * @return array<string, FinalVal>
	 */
	private static function buildAppliableArguments(VariadicVal $expr, array $args): array
	{
		if ( ! is_string(key($args))) {
			$args = self::combineBindWithValues($expr, $args);
		}
		return array_map(static function ($x): FinalVal {
			return $x instanceof FinalVal
				? $x
				: new FinalVal($x, self::gauseType($x));
		}, $args);
	}



	/**
	 * @param mixed $val
	 */
	private static function pack($val): FinalValue
	{
		if ($val instanceof FinalValue) {
			return $val;
		}
		$type = self::gauseType($val);
		switch ($type) {
			case 'Tuple':
			case 'List':
				return new FinalValue(array_map(static function ($x): FinalValue {
					return self::pack($x);
				}, $val), $type);

			case 'Dict':
				return new FinalValue((object) array_map(static function ($x): FinalValue {
					return self::pack($x);
				}, (array) $val), $type);

			default:
				return new FinalValue($val, $type);
		}
	}



	/**
	 * @param mixed $src
	 */
	private static function gauseType($src): string
	{
		switch (True) {
			case is_null($src):
				return 'Null';

			case is_bool($src):
				return 'Bool';

			case is_int($src):
				return 'Int';

			case is_float($src):
				return 'Real';

			case is_string($src):
				return 'Str';

			case self::is_list($src):
				return 'List';

			case self::is_dict($src):
				return 'Dict';

			default:
				throw new LogicException("Invalid type of value: '" . print_r($src, True) . "'.");
		}
	}



	/**
	 * @param mixed $src
	 */
	private static function is_dict($src): bool
	{
		if (is_object($src) && $src instanceof stdClass) {
			$src = (array) $src;
		}
		if ( ! is_array($src)) {
			return False;
		}
		if ($src === []) {
			return True;
		}
        // first key
        return is_string(key($src));
	}



	/**
	 * @param mixed $src
	 */
	private static function is_list($src): bool
	{
		if ( ! is_array($src)) {
			return False;
		}
		if ($src === []) {
			return True;
		}
        // first key
        return is_numeric(key($src));
	}



	/**
	 * @param array<string, mixed> $args
	 */
	private static function calculateCacheKey(string $code, array $args): string
	{
		return self::hashKey(serialize([$code, $args]));
	}



	private static function hashKey(string $data): string
	{
		static $algo = null;

		if ($algo === null) {
			$available = hash_algos();
			if (in_array('xxh3', $available, True)) {
				$algo = 'xxh3';
			}
			elseif (in_array('xxh64', $available, True)) {
				$algo = 'xxh64';
			}
			elseif (in_array('fnv1a64', $available, True)) {
				$algo = 'fnv1a64';
			}
			elseif (in_array('fnv1a32', $available, True)) {
				$algo = 'fnv1a32';
			}
			else {
				$algo = 'crc32b';
			}
		}

		return hash($algo, $data) . "__" . $algo;
	}

}
