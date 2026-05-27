<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use DateTimeInterface;
use InvalidArgumentException;
use stdClass;


final class TypeValidator
{

	/**
	 * Checks whether the required arguments matching the signature were provided.
	 * @param list<BindValue> $signature
	 * @param array<string, FinalValue> $args
	 */
	static function assertArguments(string $name, array $signature, array $args): void
	{
		$mistakes = self::checkArguments($signature, $args);
		if ($mistakes !== []) {
			throw new InvalidArgumentException("Invalid arguments of {$name}: " . implode(', ', $mistakes));
		}
	}



	/**
	 * Validates count and types of arguments against the signature.
	 * Returns a list of error messages; empty array means no errors.
	 * @param list<BindValue> $signature
	 * @param array<FinalValue> $args
	 * @return list<string>
	 */
	static function checkArguments(array $signature, array $args): array
	{
		$errors = [];

		$expected = count($signature);
		$actual = count($args);
		if ($expected !== $actual) {
			$errors[] = "Expected {$expected} argument(s), got {$actual}.";
			return $errors;
		}

		$argValues = array_values($args);
		foreach ($signature as $i => $bind) {
			$type = $bind->getTypeName();
			$val = $argValues[$i]->unpack();
			$error = self::checkTypeValue($type, $val);
			if ($error !== Null) {
				$errors[] = $error;
			}
		}

		return $errors;
	}



	/**
	 * Validates only the resolved (FinalValue) arguments in a partial function application.
	 * Skips any argument that is not a FinalValue (unresolved parameters, sub-expressions, etc.).
	 * Call this during compilation when some arguments are known and some are not yet.
	 *
	 * @param list<BindValue> $signature
	 * @param array<int, mixed> $args positional — mix of FinalValue and unresolved nodes
	 */
	static function assertPartialArgTypes(string $fnName, array $signature, array $args): void
	{
		$errors = [];
		foreach ($signature as $i => $bind) {
			$val = $args[$i] ?? Null;
			if (!$val instanceof FinalValue) {
				continue;
			}
			$error = self::checkTypeValue($bind->getTypeName(), $val->unpack());
			if ($error !== Null) {
				$errors[] = $error;
			}
		}
		if ($errors !== []) {
			throw new InvalidArgumentException("Invalid arguments of {$fnName}: " . implode(', ', $errors));
		}
	}



	/**
	 * @param mixed $val
	 */
	static function assertStr($val): void
	{
		if (!is_string($val)) {
			throw ScriptTypeException::expectedStr(gettype($val));
		}
	}



	/**
	 * @param mixed $val
	 */
	static function assertInt($val): void
	{
		if (!is_int($val)) {
			throw ScriptTypeException::expectedInt(gettype($val));
		}
	}



	/**
	 * @param mixed $val
	 */
	static function assertListOfStr($val): void
	{
		if (!is_array($val)) {
			throw ScriptTypeException::expectedList(gettype($val));
		}
		foreach ($val as $key => $item) {
			if (!is_string($item)) {
				throw ScriptTypeException::expectedStrElement(
					$key,
					is_object($item) ? get_class($item) : gettype($item)
				);
			}
		}
	}



	/**
	 * Returns an error message if the value does not match the type, or null if it does.
	 * @param mixed $val
	 */
	private static function checkTypeValue(string $type, $val): ?string
	{
		if ($type === '?' || $type === 'a') {
			return Null;
		}
		if ($type === 'Int') {
			return is_int($val)
				? Null
				: 'Expected int, got ' . gettype($val);
		}
		if ($type === 'Num') {
			return is_int($val) || is_float($val)
				? Null
				: 'Expected int or float, got ' . gettype($val);
		}
		if ($type === 'Str' || $type === 'String') {
			return is_string($val)
				? Null
				: 'Expected string, got ' . gettype($val);
		}
		if ($type === 'Bool') {
			return is_bool($val)
				? Null
				: 'Expected bool, got ' . gettype($val);
		}
		if ($type === 'Real' || $type === 'Float') {
			return is_float($val)
				? Null
				: 'Expected float, got ' . gettype($val);
		}
		if (strncmp($type, 'List', strlen('List')) === 0) {
			return is_array($val)
				? Null
				: 'Expected array, got ' . gettype($val);
		}
		if ($type === 'Dict') {
			return $val instanceof stdClass
				? Null
				: 'Expected Dict (stdClass), got ' . gettype($val);
		}
		if ($type === 'DateTime') {
			return $val instanceof DateTimeInterface
				? Null
				: 'Expected DateTime, got ' . gettype($val);
		}
		// Callable, generic type parameters — skip validation
		return Null;
	}

}
