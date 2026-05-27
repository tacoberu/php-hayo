<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Demonstrates error scenarios ("panics") — cases where the runtime throws
 * instead of producing a result.
 *
 * Two categories are distinguished:
 *
 *   - Compile-time errors: thrown by compile() / Compiler::compile().
 *     Constant expressions are partially evaluated during compilation, so
 *     e.g. `10 div 0` already panics before any data is supplied.
 *
 *   - Runtime errors: thrown by apply() / HayoEngine::evaluate().
 *     These arise when parameter-dependent expressions are evaluated against
 *     concrete input, e.g. `a div 0` applied with a = 10.
 */
class RuntimeErrorTest extends TestCase
{

	/**
	 * runtime errors (fire during apply)
	 * @param array<string, mixed> $args
	 * @param class-string<\Throwable> $exception
	 */
	#[DataProvider('dataRuntimeErrors')]
	function testApplyError(string $code, array $args, string $exception, string $messageFragment): void
	{
		$compiled = $this->compile($code);
		$this->expectException($exception);
		$this->expectExceptionMessageMatches('/' . preg_quote($messageFragment, '/') . '/i');
		$compiled->apply($args);
	}



	/**
	 * convenience: errors surfaced through HayoEngine::evaluate
	 * @param array<mixed> $args
	 * @param class-string<\Throwable> $exception
	 */
	#[DataProvider('dataRuntimeErrors')]
	function testEvaluateError(string $code, array $args, string $exception, string $messageFragment): void
	{
		$this->expectException($exception);
		$this->expectExceptionMessageMatches('/' . preg_quote($messageFragment, '/') . '/i');
		HayoEngine::WithDefaultLibraries()->evaluate($code, $args);
	}



	/**
	 * @return array<string, array<mixed>>
	 */
	static function dataRuntimeErrors(): array
	{
		return [
			// Division by zero when denominator comes from a local constant (0),
			// but the numerator is a parameter — expression is not fully reduced at compile time.
			'a div 0 — int' => [
				'a div 0',
				['a' => new FinalValue(10, 'Int')],
				ScriptRuntimeException::class,
				'zero',
				],

			'a mod 0 — int' => [
				'a mod 0',
				['a' => new FinalValue(10, 'Int')],
				ScriptRuntimeException::class,
				'zero',
				],

			// Division by zero when the divisor itself is a parameter.
			'10 div b — b=0' => [
				'10 div b',
				['b' => new FinalValue(0, 'Int')],
				ScriptRuntimeException::class,
				'zero',
				],

			'a div b — b=0' => [
				'a div b',
				['a' => new FinalValue(10, 'Int'), 'b' => new FinalValue(0, 'Int')],
				ScriptRuntimeException::class,
				'zero',
				],

			// Type mismatch: built-in function receives a value of the wrong type.
			'Str.len on integer param' => [ // @TODO except SymbolNotFound::InvalidTypeOfArguments
				'Str.len x',
				['x' => new FinalValue(42, 'Int')],
				ScriptRuntimeException::class,
				'Expected string',
				],

			'Str.len on boolean param' => [ // @TODO except SymbolNotFound::InvalidTypeOfArguments
				'Str.len x',
				['x' => new FinalValue(True, 'Bool')],
				ScriptRuntimeException::class,
				'Expected string',
				],

			'Math.ceil on integer (expects Real)' => [ // @TODO except SymbolNotFound::InvalidTypeOfArguments
				'Math.ceil x',
				['x' => new FinalValue(5, 'Int')],
				ScriptRuntimeException::class,
				'Expected float',
				],

			// Wrong number of arguments supplied to a compiled ParametricValue.
			'too few arguments' => [ // @TODO except SymbolNotFound::InvalidArguments
				'a + b',
				['a' => new FinalValue(1, 'Int')],
				ScriptRuntimeException::class, 'Invalid count of arguments',
				],

			'too many arguments' => [// @TODO except SymbolNotFound::InvalidArguments
				'a + 1',
				['a' => new FinalValue(1, 'Int'), 'b' => new FinalValue(2, 'Int')],
				ScriptRuntimeException::class, 'Invalid count of arguments',
				],

			'wrong argument name' => [ // @TODO except SymbolNotFound::InvalidArguments
				'a + 1',
				['z' => new FinalValue(1, 'Int')],
				ScriptRuntimeException::class, 'Invalid arguments',
				],

		];
	}



	/**
	 * @return FinalValue|ParametricValue
	 */
	private function compile(string $code)
	{
		return (new Compiler([
			'predicate' => new PredicatesProvider(),
			'Math' => new MathsProvider(),
			'Str' => new StringsProvider(),
			'List' => new ListsProvider(),
			'Dict' => new DictsProvider(),
			]))
			->compile($code);
	}

}
