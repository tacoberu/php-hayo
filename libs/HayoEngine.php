<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use stdClass;


final class HayoEngine
{

	/**
	 * @var array<string, LibraryProvider>
	 */
	private array $libs = [];

	private ?Cache $cache = Null;

	/**
	 * @param list<LibraryProvider> $libs
	 */
	function __construct(array $libs = [])
	{
		foreach ($libs as $lib) {
			$this->libs[$lib->getNamespace()] = $lib;
		}
	}



	static function WithDefaultLibraries(): self
	{
		return new self([
			new PredicatesProvider(),
			new BoolProvider(),
			new MathsProvider(),
			new StringsProvider(),
			new ListsProvider(),
			new DictsProvider(),
			new DateTimeProvider(),
			new IntrospectProvider(),
		]);
	}



	function registerLibrary(LibraryProvider $lib): self
	{
		$this->libs[$lib->getNamespace()] = $lib;
		return $this;
	}



	function setCache(Cache $adapter): self
	{
		$this->cache = $adapter;
		return $this;
	}



	/**
	 * Builds a value of a custom type from plain PHP data and a fully-qualified
	 * type name — the PHP-side counterpart of constructing a value inside a script.
	 *
	 * `$type` is namespaced ("Ns.Type"); the engine splits it and asks
	 * $libs[Ns]->lookupType("Type") (a TypeProvider) for the TypeDef. The values
	 * are validated against the declared field types and wrapped into a FinalValue
	 * carrying the full type name, ready to pass into evaluate().
	 *
	 * For a product type pass just the fields. For a sum type pass the chosen
	 * variant as $variant (its argument types are then validated); the result is
	 * a SumTypeValue. Passing $variant for a product type — or omitting it for a
	 * sum type — is an error.
	 *
	 * @param list<mixed> $values
	 */
	function value(array $values, string $type, ?string $variant = Null): FinalValue
	{
		$def = $this->lookupType($type);

		if ($def instanceof ProductTypeDef) {
			if ($variant !== Null) {
				throw new ArgumentsException("Product type '{$type}' takes no variant.");
			}
			$payload = $this->packFields($values, $def->getFieldTypes(), $type);
			return FinalValue::composite($payload, $type);
		}

		if ($def instanceof SumTypeDef) {
			if ($variant === Null || ! in_array($variant, $def->getVariantNames(), True)) {
				throw new ArgumentsException("Sum type '{$type}' requires one of its variants; given '" . ($variant ?? 'null') . "'.");
			}
			$payload = $this->packFields($values, $def->getVariantArgTypes($variant), $type);
			return new FinalValue(new SumTypeValue($type, $variant, $payload), $type);
		}

		throw ArgumentsException::InvalidValueType($type);
	}



	/**
	 * Compiles to "bytecode" and optionally saves it.
	 * @return FinalValue | ParametricValue
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
			case $expr instanceof FinalValue:
				return $expr->unpack();

			case $expr instanceof ParametricValue: // @phpstan-ignore instanceof.alwaysTrue
				return $expr
					->apply($this->buildAppliableArguments($expr, $args))
					->unpack();

			default:
				throw CompileException::UnexpectedResult($expr);
		}
	}



	/**
	 * Validates plain values against declared field types and packs them into a
	 * positional payload of FinalValues.
	 *
	 * @param list<mixed> $values
	 * @param list<string> $argTypes
	 * @return list<FinalValue>
	 */
	private function packFields(array $values, array $argTypes, string $type): array
	{
		if (count($values) !== count($argTypes)) {
			$given = array_map(function ($x): string {
				return $this->gauseType($x);
			}, $values);
			throw ArgumentsException::InvalidCountOfArguments($type, $argTypes, $given);
		}

		$payload = [];
		foreach ($values as $i => $val) {
			$packed = $this->pack($val);
			self::assertFieldType($argTypes[$i], $packed->type());
			$payload[] = $packed;
		}
		return $payload;
	}



	/**
	 * Resolves a fully-qualified type name ("Ns.Type") to its TypeDef via the
	 * owning library's TypeProvider, or Null if unknown.
	 */
	private function lookupType(string $type): ?TypeDef
	{
		if (strpos($type, '.') === False) {
			return Null;
		}
		list($ns, $local) = explode('.', $type, 2);
		$lib = $this->libs[$ns] ?? Null;
		if ( ! $lib instanceof TypeProvider) {
			return Null;
		}
		return $lib->lookupType($local);
	}



	/**
	 * @return FinalValue | ParametricValue
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
	 * Wrap arguments from plain values into FinalValue
	 *
	 * @param list<mixed> | array<string, mixed> $args
	 * @return array<string, FinalValue>
	 */
	private function buildAppliableArguments(ParametricValue $expr, array $args): array
	{
		if ( ! is_string(key($args))) {
			$args = self::combineBindWithValues($expr, $args); // @phpstan-ignore argument.type
		}
		return array_map(function ($x): FinalValue { // @phpstan-ignore return.type
			return $this->pack($x);
		}, $args);
	}



	/**
	 * @param mixed $val
	 */
	private function pack($val): FinalValue
	{
		if ($val instanceof FinalValue) {
			return $val;
		}
		$type = $this->gauseType($val);
		switch ($type) {
			case 'Tuple':
			case 'List':
				return new FinalValue(array_map(function ($x): FinalValue {
					return $this->pack($x);
				}, $val), $type);

			case 'Dict':
				return new FinalValue((object) array_map(function ($x): FinalValue {
					return $this->pack($x);
				}, (array) $val), $type);

			default:
				return new FinalValue($val, $type);
		}
	}



	/**
	 * @param mixed $src
	 */
	private function gauseType($src): string
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

			case $src instanceof SumTypeValue:
				return $src->getTypeName();

			default:
				throw ArgumentsException::InvalidValueType($src);
		}
	}



	private static function assertFieldType(string $expected, string $actual): void
	{
		if ($expected === '?' || $expected === 'a' || $expected === $actual) {
			return;
		}
		// Num/Real accept both Int and Real, mirroring the numeric tower elsewhere.
		if (($expected === 'Num' || $expected === 'Real') && ($actual === 'Int' || $actual === 'Real')) {
			return;
		}
		throw ArgumentsException::InvalidArguments([$expected], [$actual]);
	}



	/**
	 * When arguments are passed unnamed, just as an array.
	 * The function has its own argument signature. $values contains the values for those arguments. We combine them by index.
	 * @param list<mixed> $values
	 * @return array<string, mixed>
	 */
	private static function combineBindWithValues(ParametricValue $fn, array $values): array
	{
		$refs = array_map(static function (BindValue $x): string {
			return $x->getBindName();
		}, $fn->getBinds());

		if (count($refs) !== count($values)) {
			throw ArgumentsException::InvalidCountOfArguments((string) $fn, $refs, $values);
		}

		return array_combine($refs, $values);
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
