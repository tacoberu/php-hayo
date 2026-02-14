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


class ComputeTest extends TestCase
{

	/**
	 * @param array<string, FinalVal> $args
	 */
	#[DataProvider('dataOperations')]
	function testCompute(string $code, array $args, $expected)
	{
		$this->assertEquals($expected, $this->compile($code)->apply($args));
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataOperations(): array
	{
		return [
			['40 + a'
				, [ 'a' => new FinalVal(2, 'Int')]
				, new FinalVal(42, 'Int'),
				],
			['40 + (a + a)'
				, [ 'a' => new FinalVal(45, 'Int')]
				, new FinalVal(130, 'Int'),
				],
			['40 + (1 + a)'
				, [ 'a' => new FinalVal(45, 'Int')]
				, new FinalVal(86, 'Int'),
				],
			['(10 + a) + (a + 1)'
				, [ 'a' => new FinalVal(8, 'Int')]
				, new FinalVal(27, 'Int'),
				],
			// @TODO
		];
	}



	private function compile($src)
	{
		return (new Compiler())->compile($src);
	}

}
