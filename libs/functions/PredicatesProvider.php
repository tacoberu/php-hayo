<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

class PredicatesProvider implements SymbolProvider, ShortSymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc
	{
		$symbol = strtolower($symbol);
		if (! in_array($symbol, ['==', '!=', '<', '>', '<=', '>=', 'and', 'or', '&&', '||', 'not', 'in', 'has', 'superset', 'subset', 'intersects',], True)) {
			return Null;
		}

		return new PredicateFunction($symbol);
	}



	/**
	 * @return list<string>
	 */
	function getShortSymbolTable(): array
	{
		return ['==', '!=', '<', '>', '<=', '>=',
			'and', 'or', '&&', '||', 'not',
			'in', 'has', 'superset', 'subset', 'intersects',
			];
	}

}



/**
 * `Pred.== a: ?, b: ? :: Bool` - Equal.
 * `Pred.!= a: ?, b: ? :: Bool` - Not equal.
 * `Pred.< a: ?, b: ? :: Bool` - Less than.
 * `Pred.> a: ?, b: ? :: Bool` - Greater than.
 * `Pred.<= a: ?, b: ? :: Bool` - Less than or equal.
 * `Pred.>= a: ?, b: ? :: Bool` - Greater than or equal.
 * `Pred.and a: ?, b: ? :: Bool` - Logical conjunction (also &&).
 * `Pred.or a: ?, b: ? :: Bool` - Logical disjunction (also ||).
 * `Pred.not a: ? :: Bool` - Negation.
 * `Pred.in a: a, b: List<a> :: Bool` - Element left is in the right set.
 * `Pred.has a: List<a>, b: a :: Bool` - The left set contains the right element.
 * `Pred.superset a: List<a>, b: List<a> :: Bool` - All elements of the right set are in the left set.
 * `Pred.subset a: List<a>, b: List<a> :: Bool` - All elements of the left set are in the right set.
 * `Pred.intersects a: List<a>, b: List<a> :: Bool` - The sets share at least one common element.
 */
class PredicateFunction implements BuildinFunc
{

	const Name = "Predicate";

	/** Mapping of symbol to PHP method name */
	private const OP_MAP = [
		'==' => 'eq',
		'!=' => 'neq',
		'<' => 'lt',
		'>' => 'gt',
		'<=' => 'lte',
		'>=' => 'gte',
		'and' => 'and',
		'or' => 'or',
		'&&' => 'and',
		'||' => 'or',
		'not' => 'not',
		'in' => 'in',
		'has' => 'has',
		'superset' => 'superset',
		'subset' => 'subset',
		'intersects' => 'intersects',
	];

	private string $name;

	/**
	 * @var array<string, array{0: list<BindValue>, 1: string}>
	 */
	private static array $functionMap = [];

	function __construct(string $name)
	{
		$this->name = strtolower($name);
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
		return Utils::selectReturnType(self::$functionMap, self::OP_MAP[$this->name]);
	}



	/**
	 * Which arguments are required.
	 * @return list<BindValue>
	 */
	function getBinds(): array
	{
		return Utils::selectArgumentsSignature(self::$functionMap, self::OP_MAP[$this->name]);
	}



	/**
	 * Pass the required arguments and compute the result. Arguments must already
	 * be final values.
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value
	{
		$func = 'apply' . ucfirst(self::OP_MAP[$this->name]);
		if (method_exists(self::class, $func)) {
			TypeValidator::assertArguments(self::Name . '.' . $this->name, $this->getBinds(), $args);
			return call_user_func_array([self::class, $func], $args);
		}
		throw SymbolNotFound::UnsupportedFunc(self::Name, $this->name);
	}



	/**
	 * @signature "a: a, b: a -> Bool"
	 */
	private static function applyEq(FinalValue $a, FinalValue $b): FinalValue
	{
		return new FinalValue($a->unpack() === $b->unpack(), 'Bool');
	}



	/**
	 * @signature "a: a, b: a -> Bool"
	 */
	private static function applyNeq(FinalValue $a, FinalValue $b): FinalValue
	{
		return new FinalValue($a->unpack() !== $b->unpack(), 'Bool');
	}



	/**
	 * @signature "a: a, b: a -> Bool"
	 */
	private static function applyLt(FinalValue $a, FinalValue $b): FinalValue
	{
		return new FinalValue($a->unpack() < $b->unpack(), 'Bool');
	}



	/**
	 * @signature "a: a, b: a -> Bool"
	 */
	private static function applyGt(FinalValue $a, FinalValue $b): FinalValue
	{
		return new FinalValue($a->unpack() > $b->unpack(), 'Bool');
	}



	/**
	 * @signature "a: a, b: a -> Bool"
	 */
	private static function applyLte(FinalValue $a, FinalValue $b): FinalValue
	{
		return new FinalValue($a->unpack() <= $b->unpack(), 'Bool');
	}



	/**
	 * @signature "a: a, b: a -> Bool"
	 */
	private static function applyGte(FinalValue $a, FinalValue $b): FinalValue
	{
		return new FinalValue($a->unpack() >= $b->unpack(), 'Bool');
	}



	/**
	 * @signature "a: Bool, b: Bool -> Bool"
	 */
	private static function applyAnd(FinalValue $a, FinalValue $b): FinalValue
	{
		if (!is_bool($a->unpack())) {
			throw ScriptTypeException::expectedBool(gettype($a->unpack()));
		}
		if (!is_bool($b->unpack())) {
			throw ScriptTypeException::expectedBool(gettype($b->unpack()));
		}
		return new FinalValue($a->unpack() && $b->unpack(), 'Bool');
	}



	/**
	 * @signature "a: Bool, b: Bool -> Bool"
	 */
	private static function applyOr(FinalValue $a, FinalValue $b): FinalValue
	{
		if (!is_bool($a->unpack())) {
			throw ScriptTypeException::expectedBool(gettype($a->unpack()));
		}
		if (!is_bool($b->unpack())) {
			throw ScriptTypeException::expectedBool(gettype($b->unpack()));
		}
		return new FinalValue($a->unpack() || $b->unpack(), 'Bool');
	}



	/**
	 * @signature "a: Bool -> Bool"
	 */
	private static function applyNot(FinalValue $a): FinalValue
	{
		if (!is_bool($a->unpack())) {
			throw ScriptTypeException::expectedBool(gettype($a->unpack()));
		}
		return new FinalValue( ! $a->unpack(), 'Bool');
	}



	/**
	 * Element left is in the right set.
	 * @signature "a: a, b: List<a> -> Bool"
	 */
	private static function applyIn(FinalValue $a, FinalValue $b): FinalValue
	{
		return new FinalValue(in_array($a->unpack(), $b->unpack(), True), 'Bool');
	}



	/**
	 * The left set contains the right element.
	 * @signature "a: List<a>, b: a -> Bool"
	 */
	private static function applyHas(FinalValue $a, FinalValue $b): FinalValue
	{
		return new FinalValue(in_array($b->unpack(), $a->unpack(), True), 'Bool');
	}



	/**
	 * All elements of the right set are in the left set.
	 * @signature "a: List<a>, b: List<a> -> Bool"
	 */
	private static function applySuperset(FinalValue $a, FinalValue $b): FinalValue
	{
		$a = $a->unpack();
		foreach ($b->unpack() as $item) {
			if (!in_array($item, $a, True)) {
				return new FinalValue(False, 'Bool');
			}
		}
		return new FinalValue(True, 'Bool');
	}



	/**
	 * All elements of the left set are in the right set.
	 * @signature "a: List<a>, b: List<a> -> Bool"
	 */
	private static function applySubset(FinalValue $a, FinalValue $b): FinalValue
	{
		$b = $b->unpack();
		foreach ($a->unpack() as $item) {
			if (!in_array($item, $b, True)) {
				return new FinalValue(False, 'Bool');
			}
		}
		return new FinalValue(True, 'Bool');
	}



	/**
	 * The left and right sets share at least one common element.
	 * @signature "a: List<a>, b: List<a> -> Bool"
	 */
	private static function applyIntersects(FinalValue $a, FinalValue $b): FinalValue
	{
		$b = $b->unpack();
		foreach ($a->unpack() as $item) {
			if (in_array($item, $b, True)) {
				return new FinalValue(True, 'Bool');
			}
		}
		return new FinalValue(False, 'Bool');
	}



	function __toString(): string
	{
		return '<' . self::Name . '.' . $this->name . '>';
	}

}
