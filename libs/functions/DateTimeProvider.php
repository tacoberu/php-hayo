<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use DateTime;


class DateTimeProvider implements SymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		if (! in_array($symbol, [
				// Converts to timestamp
				'toTimestamp',
				// Creates from timestamp
				'fromTimestamp',
				// Creates from date components `(calendar.fromData 2025 1 5)`
				'fromDate',
				// Creates from date-time components `(calendar.fromDataTime 2025 1 5 12 24 55)`
				'fromDateTime',
				// Formats a date according to a mask: `calendar.format "%y. %j. $d" src`
				'format',
				], True)) {
			return Null;
		}

		return new DateTimeFunc($symbol);
	}

}



class DateTimeFunc implements BuildinFunc
{

	const Name = 'DateTime';

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
		$func = Utils::formatApplyFunc($this->name);
		if (method_exists(self::class, $func)) {
			TypeValidator::assertArguments(self::Name . '.' . $this->name, $this->getBinds(), $args);
			return call_user_func_array([self::class, $func], $args); // @phpstan-ignore argument.type
		}
		throw SymbolNotFound::UnsupportedFunc(self::Name, $this->name);
	}



	/**
	 * @signature "year: Int, month: Int, day: Int, hour: Int, minute: Int, sec: Int -> DateTime"
	 * @phpstan-ignore method.unused
	 */
	private static function applyFromDateTime(FinalValue $year, FinalValue $month, FinalValue $day, FinalValue $hour, FinalValue $minute, FinalValue $sec): FinalValue
	{
		$value = new DateTime();
		$value->setDate($year->unpack(), $month->unpack(), $day->unpack());
		$value->setTime($hour->unpack(), $minute->unpack(), $sec->unpack());
		return new FinalValue($value, self::Name);
	}



	/**
	 * @signature "year: Int, month: Int, day: Int -> DateTime"
	 * @phpstan-ignore method.unused
	 */
	private static function applyFromDate(FinalValue $year, FinalValue $month, FinalValue $day): FinalValue
	{
		$value = new DateTime();
		$value->setDate($year->unpack(), $month->unpack(), $day->unpack());
		$value->setTime(0, 0, 0);
		return new FinalValue($value, self::Name);
	}



	/**
	 * @signature "src: DateTime -> Int"
	 * @phpstan-ignore method.unused
	 */
	private static function applyToTimestamp(FinalValue $src): FinalValue
	{
		return new FinalValue($src->unpack()->getTimestamp(), 'Int');
	}



	/**
	 * @signature "src: Int -> DateTime"
	 * @phpstan-ignore method.unused
	 */
	private static function applyFromTimestamp(FinalValue $src): FinalValue
	{
		$src = $src->unpack();
		return new FinalValue(new DateTime("@{$src}"), self::Name);
	}



	/**
	 * @signature "format: Str, src: DateTime -> Str"
	 * @phpstan-ignore method.unused
	 */
	private static function applyFormat(FinalValue $format, FinalValue $src): FinalValue
	{
		$format = $format->unpack();
		$src = $src->unpack();
		return new FinalValue($src->format($format), 'Str');
	}



	function __toString(): string
	{
		return '<' . self::Name . '.' . $this->name . '>';
	}

}
