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
use Throwable;
use DivisionByZeroError;
use InvalidArgumentException;


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
	 * @param class-string<\Throwable>|null $cause
	 */
	#[DataProvider('dataArgumentErrors')]
	#[DataProvider('dataRuntimeErrors')]
	function testApplyError(string $code, array $args, string $exception, string $messageFragment, ?string $cause = null): void
	{
		$compiled = $this->compile($code);
		try {
			$compiled->apply($args);
			$this->fail("Expected {$exception}");
		}
		catch (Throwable $e) {
			$this->assertInstanceOf($exception, $e);
			$this->assertMatchesRegularExpression('/' . preg_quote($messageFragment, '/') . '/i', $e->getMessage());
			if ($cause !== null) {
				$this->assertInstanceOf($cause, $e->getPrevious());
			}
		}
	}



	/**
	 * convenience: errors surfaced through HayoEngine::evaluate
	 * @param array<mixed> $args
	 * @param class-string<\Throwable> $exception
	 * @param class-string<\Throwable>|null $cause
	 */
	#[DataProvider('dataArgumentErrors')]
	#[DataProvider('dataRuntimeErrors')]
	function testEvaluateError(string $code, array $args, string $exception, string $messageFragment, ?string $cause = null): void
	{
		try {
			HayoEngine::WithDefaultLibraries()->evaluate($code, $args);
			$this->fail("Expected {$exception}");
		}
		catch (Throwable $e) {
			$this->assertInstanceOf($exception, $e);
			$this->assertMatchesRegularExpression('/' . preg_quote($messageFragment, '/') . '/i', $e->getMessage());
			if ($cause !== null) {
				$this->assertInstanceOf($cause, $e->getPrevious());
			}
		}
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
				ScriptRuntimeException::class, 'zero',
				DivisionByZeroError::class,
				],

			'a mod 0 — int' => [
				'a mod 0',
				['a' => new FinalValue(10, 'Int')],
				ScriptRuntimeException::class, 'zero',
				DivisionByZeroError::class,
				],

			// Division by zero when the divisor itself is a parameter.
			'10 div b — b=0' => [
				'10 div b',
				['b' => new FinalValue(0, 'Int')],
				ScriptRuntimeException::class, 'zero',
				DivisionByZeroError::class,
				],

			'a div b — b=0' => [
				'a div b',
				['a' => new FinalValue(10, 'Int'), 'b' => new FinalValue(0, 'Int')],
				ScriptRuntimeException::class, 'zero',
				DivisionByZeroError::class,
				],

			// Type mismatch: built-in function receives a value of the wrong type.
			'Str.len on integer param' => [
				'Str.len x',
				['x' => new FinalValue(42, 'Int')],
				ScriptRuntimeException::class, 'Expected string',
				InvalidArgumentException::class,
				],

			'Str.len on boolean param' => [
				'Str.len x',
				['x' => new FinalValue(True, 'Bool')],
				ScriptRuntimeException::class, 'Expected string',
				InvalidArgumentException::class,
				],

		];
	}



	/**
	 * @return array<string, array<mixed>>
	 */
	static function dataArgumentErrors(): array
	{
		return [
			// Wrong number of arguments supplied to a compiled ParametricValue.
			'too few arguments' => [
				'a + b',
				['a' => new FinalValue(1, 'Int')],
				ArgumentsException::class, 'Invalid count of arguments',
				],

			'too many arguments' => [
				'a + 1',
				['a' => new FinalValue(1, 'Int'), 'b' => new FinalValue(2, 'Int')],
				ArgumentsException::class, 'Invalid count of arguments',
				],

			'wrong argument name' => [
				'a + 1',
				['z' => new FinalValue(1, 'Int')],
				ArgumentsException::class, 'Invalid arguments',
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
