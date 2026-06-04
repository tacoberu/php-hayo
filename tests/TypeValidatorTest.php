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
use DateTime;
use stdClass;


class TypeValidatorTest extends TestCase
{

	/**
	 * @param list<BindValue> $signature
	 * @param array<mixed> $args
	 * @dataProvider dataCorrect
	 */
	#[DataProvider('dataCorrect')]
	function testCorrect(array $signature, array $args): void
	{
		$this->assertSame([], TypeValidator::checkArguments($signature, $args));
	}



	/**
	 * @param list<BindValue> $signature
	 * @param array<mixed> $args
	 * @param list<string> $expectedErrors
	 * @dataProvider dataErrors
	 */
	#[DataProvider('dataErrors')]
	function testErrors(array $signature, array $args, array $expectedErrors): void
	{
		$this->assertSame($expectedErrors, TypeValidator::checkArguments($signature, $args));
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataCorrect(): array
	{
		return [
			// Empty signature and no args
			[
[], [],
				],
			// Int
			[
[new BindValue('n', 'Int')], [new FinalValue(42, 'Int')],
				],
			// Str
			[
[new BindValue('s', 'Str')], [new FinalValue('hello', 'Str')],
				],
			// Bool
			[
[new BindValue('b', 'Bool')], [new FinalValue(True, 'Bool')],
				],
			// Real
			[
[new BindValue('r', 'Real')], [new FinalValue(3.14, 'Real')],
				],
			// List
			[
[new BindValue('xs', 'List')], [new FinalValue([1, 2, 3], 'List')],
				],
			// List<a>
			[
[new BindValue('xs', 'List<a>')], [new FinalValue([1, 2], 'List<a>')],
				],
			// Dict
			[
[new BindValue('d', 'Dict')], [new FinalValue(new stdClass(), 'Dict')],
				],
			// DateTime
			[
[new BindValue('dt', 'DateTime')], [new FinalValue(new DateTime('2024-01-01'), 'DateTime')],
				],
			// ? (any) accepts anything
			[
[new BindValue('x', '?')], [new FinalValue('whatever', '?')],
				],
			// a (generic) accepts anything
			[
[new BindValue('x', 'a')], [new FinalValue(99, 'a')],
				],
			// Multiple arguments
			[
				[new BindValue('src', 'List'), new BindValue('n', 'Int')],
				[new FinalValue([1, 2], 'List'), new FinalValue(1, 'Int')],
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataErrors(): array
	{
		return [
			// Too few arguments
			[
[new BindValue('src', 'List'), new BindValue('n', 'Int')], [new FinalValue([1, 2], 'List')],
				['Expected 2 argument(s), got 1.']],
			// Too many arguments
			[
[new BindValue('n', 'Int')], [new FinalValue(1, 'Int'), new FinalValue(2, 'Int')],
				['Expected 1 argument(s), got 2.']],
			// Wrong type: Int expected, string given
			[
[new BindValue('n', 'Int')], [new FinalValue('hello', '?')],
				['Expected int, got string']],
			// Wrong type: Str expected, int given
			[
[new BindValue('s', 'Str')], [new FinalValue(42, '?')],
				['Expected string, got integer']],
			// Wrong type: Bool expected, int given
			[
[new BindValue('b', 'Bool')], [new FinalValue(1, '?')],
				['Expected bool, got integer']],
			// Wrong type: Real expected, int given
			[
[new BindValue('r', 'Real')], [new FinalValue(42, '?')],
				['Expected float, got integer']],
			// Wrong type: List expected, string given
			[
[new BindValue('xs', 'List')], [new FinalValue('not-a-list', '?')],
				['Expected array, got string']],
			// Wrong type: List<a> expected, int given
			[
[new BindValue('xs', 'List<a>')], [new FinalValue(42, '?')],
				['Expected array, got integer']],
			// Wrong type: Dict expected, array given
			[
[new BindValue('d', 'Dict')], [new FinalValue([], '?')],
				['Expected Dict (stdClass), got array']],
			// Wrong type: DateTime expected, string given
			[
[new BindValue('dt', 'DateTime')], [new FinalValue('2024-01-01', '?')],
				['Expected DateTime, got string']],
			// Multiple type errors accumulated
			[
				[new BindValue('s', 'Str'), new BindValue('n', 'Int')],
				[new FinalValue(42, '?'), new FinalValue('hello', '?')],
				['Expected string, got integer', 'Expected int, got string']],
		];
	}

}
