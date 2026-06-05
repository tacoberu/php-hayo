<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;


class ListsProvider implements SymbolProvider
{

	/**
	 * @var list<string>
	 */
	private static array $functionMap = [];

	function lookup(string $symbol): ?BuildinFunc
	{
		if (self::$functionMap === []) {
			self::$functionMap = Utils::getFunctionsFrom(ListFunc::class);
		}

		if (! in_array($symbol, self::$functionMap, True)) {
			return Null;
		}

		return new ListFunc($symbol);
	}

}



/**
 * `List.indexOf match: Callable, offset: Int, src: List<a> -> Int` -
 */
class ListFunc implements BuildinFunc
{

	const Name = 'List';

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
	 * Length of the list.
	 * @signature "src: List<a> -> Int"
	 * @phpstan-ignore method.unused
	 */
	private static function applyLen(FinalValue $src): FinalValue
	{
		$src = $src->unpack();
		return new FinalValue(count($src), 'Int');
	}



	/**
	 * First element of the list.
	 * @signature "src: List<a>, default: a -> a"
	 * @phpstan-ignore method.unused
	 */
	private static function applyFirst(FinalValue $src, FinalValue $default): FinalValue
	{
		$src = $src->unpack();
		$default = $default->unpack();
		if (count($src) > 0) {
			return new FinalValue($src[0], 'a');
		}
		return new FinalValue($default, 'a');
	}



	/**
	 * Return value at a specific index.
	 * @signature "src: List<a>, index: Int, default: a -> a"
	 * @phpstan-ignore method.unused
	 */
	private static function applyAt(FinalValue $src, FinalValue $index, FinalValue $default): FinalValue
	{
		$index = $index->unpack();
		$src = $src->unpack();
		$default = $default->unpack();
		return array_key_exists($index, $src)
			? new FinalValue($src[$index], 'a')
			: new FinalValue($default, 'a');
	}



	/**
	 * Whether any element exists at the given index.
	 * @signature "src: List<a>, index: Int -> Bool"
	 * @phpstan-ignore method.unused
	 */
	private static function applyExist(FinalValue $src, FinalValue $index): FinalValue
	{
		$index = $index->unpack();
		$src = $src->unpack();
		return new FinalValue(array_key_exists($index, $src), 'Bool');
	}



	/**
	 * Returns a subset of the list starting at start with at most length elements.
	 * @signature "src: List<a>, start: Int, len: Int -> List<a>"
	 * @phpstan-ignore method.unused
	 */
	private static function applySlice(FinalValue $src, FinalValue $start, FinalValue $len): FinalValue
	{
		$start = $start->unpack();
		$len = $len->unpack();
		$src = $src->getValue();
		return new FinalValue(array_slice($src, $start, $len), 'List<a>');
	}



	/**
	 * Concatenates two lists.
	 * @signature "first: List<a>, second: List<a> -> List<a>"
	 * @phpstan-ignore method.unused
	 */
	private static function applyConcat(FinalValue $first, FinalValue $second): FinalValue
	{
		return new FinalValue(array_merge($first->getValue(), $second->getValue()), 'List<a>');
	}



	/**
	 * Appends a record to the end of the list.
	 * @signature "xs: List<a>, x: a -> List<a>"
	 * @phpstan-ignore method.unused
	 */
	private static function applyPush(FinalValue $src, FinalValue $x): FinalValue
	{
		$xs = $src->unpack();
		$xs[] = $x->unpack();
		return new FinalValue($xs, 'List<a>');
	}



	/**
	 * Applies transformation `fn` to each element.
	 * @signature "xs: List<a>, cb: (a -> b) -> List<b>"
	 * @phpstan-ignore method.unused
	 */
	private static function applyMap(FinalValue $src, ParametricValue $cb): FinalValue
	{
		$args = $cb->getArgs();
		return new FinalValue(array_map(static function($x) use ($cb, $args) {
			if (!is_array($x)) {
				$x = [$x];
			}
			return $cb->apply(array_combine($args, $x));
		}, $src->getValue()), 'List');
	}



	/**
	 * Returns only elements matching the filter.
	 * @signature "src: List<a>, cb: (a -> Bool) -> List<a>"
	 * @phpstan-ignore method.unused
	 */
	private static function applyFilter(FinalValue $src, ParametricValue $cb): FinalValue
	{
		$args = $cb->getArgs();
		return new FinalValue(array_values(array_filter($src->getValue(), static function($x) use ($cb, $args) {
			if (!is_array($x)) {
				$x = [$x];
			}
			return $cb->apply(array_combine($args, $x))->unpack();
		})), 'List');
	}



	/**
	 * The function transforms a collection of elements into a single output
	 * value by applying a binary operation step by step. The process starts
	 * with an initial value (init) and gradually adds each element of the
	 * list to it, with the result of each step becoming a new accumulator
	 * for the next step.
	 * @signature "src: List<a>, init: b, cb: (b -> a -> b) -> b"
	 * @return FinalValue|ParametricValue
	 * @phpstan-ignore method.unused
	 */
	private static function applyFold(FinalValue $src, FinalValue $init, ParametricValue $cb)
	{
		$value = $init;
		$args = $cb->getArgs();
		foreach ($src->getValue() as $x) {
			if (!is_array($x)) {
				$x = [$x];
			}
			array_unshift($x, $value);
			$value = $cb->apply(array_combine($args, $x));
		}
		return $value;
	}



	/**
	 * Splits the list by function into `limit` parts.
	 * @signature "src: List<a>, sep: (a -> Bool), limit: Int -> List<List<a>>"
	 * @phpstan-ignore method.unused
	 */
	private static function applySplit(FinalValue $src, ParametricValue $sep, FinalValue $limit): FinalValue
	{
		$limit = $limit->unpack();
		if ($limit === 0) {
			return new FinalValue([], 'List');
		}
		if ($limit === 1) {
			return new FinalValue([$src], 'List');
		}

		$args = $sep->getArgs();
		$parts = [];
		$acu = [];
		foreach ($src->getValue() as $x) {
			if ($limit < 2) {
				$acu[] = $x;
				continue;
			}
			$filt = is_array($x)
				? $x
				: [$x];
			if ($sep->apply(array_combine($args, $filt))->unpack()) {
				$parts[] = new FinalValue($acu, 'List');
				$acu = [];
				$limit--;
			}
			else {
				$acu[] = $x;
			}
		}
		$parts[] = new FinalValue($acu, 'List');
		return new FinalValue($parts, 'List');
	}



	/**
	 * Finds the index of the desired value. Returns -1 on failure. Second argument is the offset.
	 * @signature "src: List<a>, match: (a -> Bool), offset: Int -> Int"
	 * @phpstan-ignore method.unused
	 */
	private static function applyIndexOf(FinalValue $src, ParametricValue $match, FinalValue $offset): FinalValue
	{
		$offset = $offset->unpack();
		$args = $match->getArgs();
		$acu = [];
		foreach ($src->getValue() as $index => $x) {
			if ($offset > 0) {
				$acu[] = $x;
				$offset--;
				continue;
			}
			$filt = is_array($x)
				? $x
				: [$x];
			if ($match->apply(array_combine($args, $filt))->unpack()) {
				return new FinalValue($index, 'Int');
			}
			$acu[] = $x;
		}
		return new FinalValue(-1, 'Int');
	}



	/**
	 * @signature "src: List<a>, fn: (a -> a -> Int) -> List<a>"
	 * @param FinalValue|ParametricValue $fn
	 * @phpstan-ignore method.unused
	 */
	private static function applySort(FinalValue $src, $fn): FinalValue
	{
		$out = $src->getValue();

		if ($fn instanceof ParametricValue) {
			$args = $fn->getArgs();
			usort($out, static function($a, $b) use ($fn, $args) {
				$a = is_array($a)
					? $a
					: [$a];
				$b = is_array($b)
					? $b
					: [$b];
				$params = array_map(static function($x): FinalValue {
					return $x instanceof FinalValue
						? $x
						: new FinalValue($x, '?');
				}, array_merge($a, $b));
				return $fn->apply(array_combine($args, $params))->unpack();
			});
			return new FinalValue($out, $src->type());
		}

		$fn = $fn->unpack();
		if (is_string($fn)) {
			list(, $fn) = explode('.', $fn, 2);
		}
		switch (True) {
			case $fn === 'Desc':
				rsort($out);
				break;

			case $fn === 'Asc':
				sort($out);
				break;

			default:
				throw new LogicException("Unsupported sort function: " . print_r($fn, true));
		}
		return new FinalValue($out, $src->type());
	}



	function __toString(): string
	{
		return '<' . self::Name . '.' . $this->name . '>';
	}

}
