<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

class StringsProvider implements FuncProvider
{

	/**
	 * @var list<string>
	 */
	private static array $functionMap = [];

	function getNamespace(): string
	{
		return 'Str';
	}



	function lookupFunc(string $symbol): ?BuildinFunc
	{
		if (self::$functionMap === []) {
			self::$functionMap = Utils::getFunctionsFrom(StringFunc::class);
		}

		if (! in_array($symbol, self::$functionMap, True)) {
			return Null;
		}

		return new StringFunc($symbol);
	}

}



class StringFunc implements BuildinFunc
{

	const Name = 'Str';

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
	 * String length.
	 * @signature "src: Str -> Int"
	 * @phpstan-ignore method.unused
	 */
	private static function applyLen(FinalValue $src): FinalValue
	{
		$src = $src->unpack();
		TypeValidator::assertStr($src);
		return new FinalValue(mb_strlen($src), 'Int');
	}



	/**
	 * Split string by separator.
	 * @signature "src: Str, sep: Str -> List<Str>"
	 * @phpstan-ignore method.unused
	 */
	private static function applySplit(FinalValue $src, FinalValue $sep): FinalValue
	{
		$src = $src->unpack();
		$sep = $sep->unpack();
		TypeValidator::assertStr($src);
		TypeValidator::assertStr($sep);
		if ($src === '') {
			return new FinalValue([], 'List<Str>');
		}
		if ($sep === '') {
			return new FinalValue([new FinalValue($src, 'Str')], 'List<Str>');
		}
		return new FinalValue(array_map(static function (string $x): FinalValue {
			return new FinalValue($x, 'Str');
		}, explode($sep, $src)), 'List<Str>');
	}



	/**
	 * Join list of strings with separator.
	 * @signature "src: List<Str>, sep: Str -> Str"
	 * @phpstan-ignore method.unused
	 */
	private static function applyConcat(FinalValue $src, FinalValue $sep): FinalValue
	{
		$sep = $sep->unpack();
		$src = $src->unpack();
		TypeValidator::assertStr($sep);
		TypeValidator::assertListOfStr($src);
		return new FinalValue(implode($sep, $src), 'Str');
	}



	/**
	 * Find a substring.
	 * @signature "src: Str, fragment: Str -> Int"
	 * @phpstan-ignore method.unused
	 */
	private static function applyIndexOf(FinalValue $src, FinalValue $fragment): FinalValue
	{
		$src = $src->unpack();
		$fragment = $fragment->unpack();
		TypeValidator::assertStr($src);
		TypeValidator::assertStr($fragment);
		if ($fragment === '') {
			return new FinalValue(0, 'Int');
		}
		$index = mb_strpos($src, $fragment);
		return new FinalValue($index === False ? -1 : $index, 'Int');
	}



	/**
	 * Determines whether the first Str contains the second.
	 * @signature "src: Str, fragment: Str -> Bool"
	 * @phpstan-ignore method.unused
	 */
	private static function applyContains(FinalValue $src, FinalValue $fragment): FinalValue
	{
		$src = $src->unpack();
		$fragment = $fragment->unpack();
		TypeValidator::assertStr($src);
		TypeValidator::assertStr($fragment);
		if ($fragment === '') {
			return new FinalValue(True, 'Bool');
		}
		return new FinalValue(mb_strpos($src, $fragment) !== False, 'Bool');
	}



	/**
	 * Check if the given Str starts with a value.
	 * @signature "src: Str, fragment: Str -> Bool"
	 * @phpstan-ignore method.unused
	 */
	private static function applyStartsWith(FinalValue $src, FinalValue $fragment): FinalValue
	{
		$src = $src->unpack();
		$fragment = $fragment->unpack();
		TypeValidator::assertStr($src);
		TypeValidator::assertStr($fragment);
		return new FinalValue(strncmp($src, $fragment, strlen($fragment)) === 0, 'Bool');
	}



	/**
	 * Check if the given Str ends with a value.
	 * @signature "src: Str, fragment: Str -> Bool"
	 * @phpstan-ignore method.unused
	 */
	private static function applyEndsWith(FinalValue $src, FinalValue $fragment): FinalValue
	{
		$src = $src->unpack();
		$fragment = $fragment->unpack();
		TypeValidator::assertStr($src);
		TypeValidator::assertStr($fragment);
		return new FinalValue(substr_compare($src, $fragment, -strlen($fragment)) === 0, 'Bool');
	}



	/**
	 * Substring based on start and len.
	 * @signature "src: Str, start: Int, len: Int -> Str"
	 * @phpstan-ignore method.unused
	 */
	private static function applySub(FinalValue $src, FinalValue $start, FinalValue $len): FinalValue
	{
		$src = $src->unpack();
		$start = $start->unpack();
		$len = $len->unpack();
		TypeValidator::assertStr($src);
		TypeValidator::assertInt($start);
		TypeValidator::assertInt($len);
		return new FinalValue(mb_substr($src, $start, $len), 'Str');
	}



	/**
	 * Convert to lowercase.
	 * @signature "src: Str -> Str"
	 * @phpstan-ignore method.unused
	 */
	private static function applyToLower(FinalValue $src): FinalValue
	{
		$src = $src->unpack();
		TypeValidator::assertStr($src);
		return new FinalValue(mb_strtolower($src), 'Str');
	}



	/**
	 * Convert to uppercase.
	 * @signature "src: Str -> Str"
	 * @phpstan-ignore method.unused
	 */
	private static function applyToUpper(FinalValue $src): FinalValue
	{
		$src = $src->unpack();
		TypeValidator::assertStr($src);
		return new FinalValue(mb_strtoupper($src), 'Str');
	}



	/**
	 * Format text into a template.
	 * @signature "src: Str, args: Dict<Str> -> Str"
	 * @phpstan-ignore method.unused
	 */
	private static function applyFormat(FinalValue $src, FinalValue $args): FinalValue
	{
		$src = $src->unpack();
		$args = (array) $args->unpack();
		TypeValidator::assertStr($src);
		$map = [];
		foreach ($args as $key => $value) {
			$map["\${{$key}}"] = (string) $value;
		}
		return new FinalValue(strtr($src, $map), 'Str');
	}



	/**
	 * Trim whitespace from both sides.
	 * @signature "src: Str -> Str"
	 * @phpstan-ignore method.unused
	 */
	private static function applyTrim(FinalValue $src): FinalValue
	{
		$src = $src->unpack();
		TypeValidator::assertStr($src);
		return new FinalValue(trim($src), 'Str');
	}



	function __toString(): string
	{
		return '<Str.' . $this->name . '>';
	}

}
