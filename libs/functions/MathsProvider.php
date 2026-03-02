<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

class MathsProvider implements SymbolProvider, ShortSymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		if (! in_array($symbol, ['+', '-', '*', 'div', 'mod', 'ceil', 'floor', 'round'], True)) {
			return Null;
		}

		return new MathOperator($symbol);
	}



	/**
	 * @return list<string>
	 */
	function getShortSymbolTable(): array
	{
		return ['+', '-', '*', 'div', 'mod'];
	}

}



class MathOperator implements BuildinFunc
{

	const Name = "Math";

	/** Mapping of operator symbol to PHP method name */
	private const OP_MAP = [
		'+' => 'plus',
		'-' => 'minus',
		'*' => 'multiply',
		'div' => 'div',
		'mod' => 'mod',
		'ceil' => 'ceil',
		'floor' => 'floor',
		'round' => 'round',
	];

	private string $op;

	/**
	 * @var array<string, array{0: list<BindValue>, 1: string}>
	 */
	private static array $functionMap = [];

	function __construct(string $op)
	{
		$this->op = $op;
		if (self::$functionMap === []) {
			self::$functionMap = Utils::getApplyMethodFrom(self::class);
		}
	}



	function getQualifiedName(): string
	{
		return self::Name . '.' . $this->op;
	}



	function type(): string
	{
		return Utils::selectReturnType(self::$functionMap, self::OP_MAP[$this->op]);
	}



	/**
	 * Which arguments are required.
	 * @return list<BindValue>
	 */
	function getBinds(): array
	{
		return Utils::selectArgumentsSignature(self::$functionMap, self::OP_MAP[$this->op]);
	}



	/**
	 * Pass the required arguments and compute the result. Arguments must already
	 * be final values.
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value
	{
		$func = 'apply' . ucfirst(self::OP_MAP[$this->op]);
		if (method_exists(self::class, $func)) {
			TypeValidator::assertArguments(self::Name . '.' . $this->op, $this->getBinds(), $args);
			return call_user_func_array([self::class, $func], $args);
		}
		throw SymbolNotFound::UnsupportedFunc(self::Name, $this->op);
	}



	/**
	 * Addition
	 * @signature "a: Num, b: Num -> Num"
	 */
	private static function applyPlus(FinalValue $a, FinalValue $b): FinalValue
	{
		$sum = $a->unpack() + $b->unpack();
		return new FinalValue($sum, is_int($sum) ? 'Int' : 'Real');
	}



	/**
	 * Subtraction
	 * @signature "a: Num, b: Num -> Num"
	 */
	private static function applyMinus(FinalValue $a, FinalValue $b): FinalValue
	{
		$diff = $a->unpack() - $b->unpack();
		return new FinalValue($diff, is_int($diff) ? 'Int' : 'Real');
	}



	/**
	 * Multiplication
	 * @signature "a: Num, b: Num -> Num"
	 */
	private static function applyMultiply(FinalValue $a, FinalValue $b): FinalValue
	{
		$prod = $a->unpack() * $b->unpack();
		return new FinalValue($prod, is_int($prod) ? 'Int' : 'Real');
	}



	/**
	 * Integer division
	 * @signature "a: Num, b: Num -> Num"
	 */
	private static function applyDiv(FinalValue $a, FinalValue $b): FinalValue
	{
		$a = $a->unpack();
		$b = $b->unpack();
		if (is_int($a) && is_int($b)) {
			return new FinalValue(intdiv($a, $b), 'Int');
		}
		return new FinalValue($a / $b, 'Real');
	}



	/**
	 * Remainder of integer division
	 * @signature "a: Int, b: Int -> Int"
	 */
	private static function applyMod(FinalValue $a, FinalValue $b): FinalValue
	{
		return new FinalValue($a->unpack() % $b->unpack(), 'Int');
	}



	/**
	 * Round up
	 * @signature "a: Num -> Int"
	 */
	private static function applyCeil(FinalValue $a): FinalValue
	{
		return new FinalValue((int) ceil($a->unpack()), 'Int');
	}



	/**
	 * Round down
	 * @signature "a: Num -> Int"
	 */
	private static function applyFloor(FinalValue $a): FinalValue
	{
		return new FinalValue((int) floor($a->unpack()), 'Int');
	}



	/**
	 * Round to a number of decimal places
	 * @signature "a: Num, precision: Int -> Real"
	 */
	private static function applyRound(FinalValue $a, FinalValue $precision): FinalValue
	{
		return new FinalValue(round($a->unpack(), $precision->unpack()), 'Real');
	}



	function __toString(): string
	{
		return '<Math.' . $this->op . '>';
	}

}
