<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;
use RuntimeException;
use Throwable;


class SymbolNotFound extends LogicException
{

	static function UnsupportedFunc(string $modul, string $func): self
	{
		return new self("Unsupported {$modul} function: '{$func}'.");
	}



	static function InvalidTypeOfArguments(string $arg, string $expected, string $gone): self
	{
		return new self("Invalid type of argument {$arg}: {$expected}, gone: {$gone}.");
	}



	static function InvalidArguments(string $arg, string $cause): self
	{
		return new self("Invalid argument {$arg}: {$cause}.");
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



	static function EvaluationError(Throwable $e): self
	{
		return new self($e->getMessage(), 0, $e);
	}

}



/**
 * Invalid types. For example, adding strings.
 * This error can be thrown by both the parser and the evaluator due to invalid arguments.
 */
class ValidationException extends LogicException
{

	/**
	 * @param list<string> $errors
	 */
	static function InvalidArguments(string $fn, array $errors): self
	{
		$errors = implode(', ', $errors);
		return new self("Invalid arguments of $fn: $errors");
	}

}



class InvalidArgumentException extends ValidationException
{

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

	static function wrap(Throwable $e): self
	{
		return new self($e->getMessage(), 0, $e);
	}

}
