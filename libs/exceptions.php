<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;
use Exception;


/**
 * Thrown when a script references a symbol, built-in function, or library function
 * that is not available in the current runtime (not registered, not imported, or misspelled).
 */
class SymbolNotFound extends LogicException
{

	static function UnsupportedFunc(string $modul, string $func): self
	{
		return new self("Unsupported {$modul} function: '{$func}'.");
	}



	static function MissingSymbols(string $missing): self
	{
		return new self("Unable to find symbols: {$missing}.");
	}



	static function InvalidArgumentWrapper(string $key, string $type): self
	{
		return new self("Argument '{$key}' must be package into FinalValue or ParametricValue; {$type} given.");
	}

}



/**
 * Thrown when a compiled script is called with wrong parameters — wrong names,
 * wrong count, or an unsupported value type.
 */
class ArgumentsException extends InvalidArgumentException
{

	/**
	 * A compiled script was applied with wrong argument names.
	 * @param list<string> $expected
	 * @param list<string> $passed
	 */
	static function InvalidArguments(array $expected, array $passed): self
	{
		$fmt = static function(string $x): string { return "'{$x}'"; };
		$expected = implode(', ', array_map($fmt, $expected)) ?: 'empty';
		$passed = implode(', ', array_map($fmt, $passed)) ?: 'empty';
		return new self("Invalid arguments. Expected {$expected}; given {$passed}.");
	}



	/**
	 * A compiled script was applied with the wrong number of arguments.
	 * @param list<string> $expected
	 * @param list<string> $passed
	 */
	static function InvalidCountOfArguments(string $fnname, array $expected, array $passed): self
	{
		$fmt = static function(string $x): string { return "'{$x}'"; };
		$expected = implode(', ', array_map($fmt, $expected)) ?: 'empty';
		$passed = implode(', ', array_map($fmt, $passed)) ?: 'empty';
		return new self("Invalid count of arguments func '{$fnname}'. Expected {$expected}; given {$passed}.");
	}



	/**
	 * @param mixed $src
	 */
	static function InvalidValueType($src): self
	{
		return new self("Invalid type of value: '" . print_r($src, True) . "'.");
	}

}



/**
 * Code could not be compiled. Syntax error.
 */
class CompileException extends LogicException
{

	static function InvalidSourceCode(): self
	{
		return new self("Invalid source code.");
	}



	/**
	 * @param mixed $term
	 */
	static function UnsupportedException(string $label, $term): self
	{
		return new self("Unsupported {$label} (" . (is_object($term)
					? get_class($term)
					: gettype($term)) . "): '{$term}'.");
	}



	static function UnresolvedExpression(Expr $term): self
	{
		return new self("Unresolved expression '{$term}'.");
	}



	static function HayoParser(HayoParserException $e): self
	{
		return new self($e->getMessage(), 0, $e);
	}



	static function Unexpected(): self
	{
		return new self("This situation should not occur.");
	}



	static function InvalidLambdaArguments(): self
	{
		return new self("Lambda arguments must be simple names, not expressions. Use `(a b -> ...)` instead of `((a b) -> ...)`.");
	}



	static function UnsupportedZeroArgLambda(): self
	{
		return new self("Zero-argument lambdas are not supported. Use a local variable instead: `val = 42`.");
	}



	static function UnsupportedCurriedLambda(): self
	{
		return new self("Curried lambdas (x -> y -> ...) are not supported. Use a multi-argument lambda instead: `x y -> ...`.");
	}



	/**
	 * @param mixed $expr
	 */
	static function UnexpectedResult($expr): self
	{
		return new self("Unexpected compiled result: {$expr}");
	}



	static function EvaluationError(Throwable $e): self
	{
		return new self($e->getMessage(), 0, $e);
	}



	static function CannotUnify(Type_ $t1, Type_ $t2): self
	{
		return new self("Cannot unify '{$t1}' with '{$t2}'.");
	}



	static function OccursCheck(string $var, Type_ $type): self
	{
		return new self("Occurs check: '{$var}' occurs in '{$type}'.");
	}



	/**
	 * @param list<string> $missing
	 */
	static function NonExhaustiveMatch(string $typeName, array $missing): self
	{
		$list = implode(', ', $missing);
		return new self("Non-exhaustive match on type '{$typeName}': missing variant(s) {$list}.");
	}

}



/**
 * Thrown when a value of the wrong type is encountered.
 *
 * Surfaced at compile time as CompileException::EvaluationError,
 * and at runtime as ScriptRuntimeException.
 */
class ScriptTypeException extends Exception
{

	static function expectedStr(string $got): self
	{
		return new self("Expected string, got {$got}");
	}



	static function expectedInt(string $got): self
	{
		return new self("Expected int, got {$got}");
	}



	static function expectedFloat(string $got): self
	{
		return new self("Expected float, got {$got}");
	}



	static function expectedList(string $got): self
	{
		return new self("Expected array, got {$got}");
	}



	static function expectedStrElement(int $index, string $got): self
	{
		return new self("Expected string at index {$index}, got '{$got}'");
	}



	static function expectedBool(string $got): self
	{
		return new self("Expected bool, got {$got}");
	}

}



/**
 * Thrown when a compiled script fails during evaluation (the apply phase).
 *
 * Wraps any error that occurs while a ParametricValue is applied to concrete
 * arguments — for example: division by zero, type mismatches in built-in
 * function arguments, or invalid argument counts / names.
 *
 * Compile-time errors (syntax errors, unknown symbols) are NOT wrapped here;
 * they surface as CompileException or SymbolNotFound instead.
 *
 * The original cause is always available via getPrevious().
 */
class ScriptRuntimeException extends RuntimeException
{

	static function From(Throwable $e): self
	{
		return new self($e->getMessage(), 0, $e);
	}

}
