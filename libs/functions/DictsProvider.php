<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

class DictsProvider implements SymbolProvider
{

	/**
	 * @var list<string>
	 */
	private static array $functionMap = [];

	function lookup(string $symbol): ?BuildinFunc
	{
		if (self::$functionMap === []) {
			self::$functionMap = Utils::getFunctionsFrom(DictFunc::class);
		}

		if (! in_array($symbol, self::$functionMap, True)) {
			return Null;
		}

		return new DictFunc($symbol);
	}

}



class DictFunc implements BuildinFunc
{

	const Name = "Dict";

	private string $name;

	/**
	 * @var array<string, array{0: list<BindValue>, 1: string}>
	 */
	private static array $functionMap = [];

	function __construct(string $name)
	{
		$this->name = $name;
		if (self::$functionMap === []) {
			self::$functionMap = Utils::getApplyMethodFrom(self::class);
		}
	}



	function getQualifiedName(): string
	{
		return self::Name . '.' . $this->name;
	}



	function type(): string
	{
		return Utils::selectReturnType(self::$functionMap, $this->name);
	}



	/**
	 * Which arguments are required.
	 * @return list<BindValue>
	 */
	function getBinds(): array
	{
		return Utils::selectArgumentsSignature(self::$functionMap, $this->name);
	}



	/**
	 * Pass the required arguments and compute the result. Arguments must already
	 * be final values.
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value
	{
		$func = "apply" . ucfirst($this->name);
		if (method_exists(self::class, $func)) {
			TypeValidator::assertArguments(self::Name . '.' . $this->name, $this->getBinds(), $args);
			return call_user_func_array([self::class, $func], $args); // @phpstan-ignore argument.type
		}
		throw SymbolNotFound::UnsupportedFunc(self::Name, $this->name);
	}



	/**
	 * Collection of keys
	 * @signature "xs: Dict -> List<Str>"
	 * @phpstan-ignore method.unused
	 */
	private static function applyKeys(FinalValue $xs): FinalValue
	{
		$xs = (array) $xs->unpack();
		return new FinalValue(array_map(static function ($x): FinalValue {
			return new FinalValue($x, 'Str');
		}, array_keys($xs)), 'List<Str>');
	}



	/**
	 * Collection of values.
	 * @signature "xs: Dict -> List<?>"
	 * @phpstan-ignore method.unused
	 */
	private static function applyValues(FinalValue $xs): FinalValue
	{
		$xs = (array) $xs->unpack();
		return new FinalValue(array_map(static function ($x): FinalValue {
			return new FinalValue($x, '?');
		}, array_values($xs)), 'List<?>');
	}



	/**
	 * Check whether a value exists.
	 * @signature "xs: Dict, key: Str -> Bool"
	 * @phpstan-ignore method.unused
	 */
	private static function applyHas(FinalValue $xs, FinalValue $key): FinalValue
	{
		$xs = (array) $xs->unpack();
		$key = $key->unpack();
		return new FinalValue(array_key_exists($key, $xs), 'Bool');
	}



	/**
	 * Get value by key. If the key does not exist, returns the default value.
	 * Dot notation should also work: `val.key`.
	 * @signature "xs: Dict, key: Str, default: a -> a"
	 * @phpstan-ignore method.unused
	 */
	private static function applyGet(FinalValue $xs, FinalValue $key, FinalValue $default): FinalValue
	{
		$xs = (array) $xs->unpack();
		$key = $key->unpack();
		return array_key_exists($key, $xs)
			? new FinalValue($xs[$key], 'a')
			: $default;
	}



	/**
	 * Merge two dicts; the left is overwritten by the right
	 * @signature "base: Dict, exts: Dict -> Dict"
	 * @phpstan-ignore method.unused
	 */
	private static function applyMerge(FinalValue $base, FinalValue $exts): FinalValue
	{
		$base = (array) $base->unpack();
		$exts = (array) $exts->unpack();
		return new FinalValue((object) array_merge($base, $exts), 'Dict');
	}



	function __toString(): string
	{
		return '<Dict.' . $this->name . '>';
	}

}
