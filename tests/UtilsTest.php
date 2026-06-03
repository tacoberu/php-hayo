<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use LogicException;


class UtilsTest extends TestCase
{

	// ---- getApplyMethodFrom(string) ----------------------------------------

	function testGetApplyMethodFromReturnsOnlyApplyMethods()
	{
		$methods = Utils::getApplyMethodFrom(DateTimeFunc::class);
		foreach (array_keys($methods) as $name) {
			$this->assertStringStartsWith('apply', $name);
		}
	}



	function testGetApplyMethodFromReturnsAllApplyMethods()
	{
		$methods = Utils::getApplyMethodFrom(DateTimeFunc::class);
		$this->assertArrayHasKey('applyFromDateTime', $methods);
		$this->assertArrayHasKey('applyFromDate', $methods);
		$this->assertArrayHasKey('applyToTimestamp', $methods);
		$this->assertArrayHasKey('applyFromTimestamp', $methods);
		$this->assertArrayHasKey('applyFormat', $methods);
	}



	function testGetApplyMethodFromReturnsParsedSignatures()
	{
		$methods = Utils::getApplyMethodFrom(DateTimeFunc::class);
		foreach ($methods as [$binds, $returnType]) {
			$this->assertIsArray($binds);
			$this->assertIsString($returnType);
			foreach ($binds as $bind) {
				$this->assertInstanceOf(BindValue::class, $bind);
			}
		}
	}



	function testGetApplyMethodFromReturnsCorrectSignatures()
	{
		$methods = Utils::getApplyMethodFrom(DateTimeFunc::class);

		[$binds, $returnType] = $methods['applyFromDate'];
		$this->assertEquals([
			new BindValue('year', 'Int'),
			new BindValue('month', 'Int'),
			new BindValue('day', 'Int'),
		], $binds);
		$this->assertSame('DateTime', $returnType);

		[$binds, $returnType] = $methods['applyToTimestamp'];
		$this->assertEquals([new BindValue('src', 'DateTime')], $binds);
		$this->assertSame('Int', $returnType);

		[$binds, $returnType] = $methods['applyFormat'];
		$this->assertEquals([
			new BindValue('format', 'Str'),
			new BindValue('src', 'DateTime'),
		], $binds);
		$this->assertSame('Str', $returnType);
	}



	function testGetApplyMethodFromDictsProvider()
	{
		$methods = Utils::getApplyMethodFrom(DictFunc::class);
		$this->assertArrayHasKey('applyKeys', $methods);
		$this->assertArrayHasKey('applyValues', $methods);
		$this->assertArrayHasKey('applyHas', $methods);
		$this->assertArrayHasKey('applyGet', $methods);
		$this->assertArrayHasKey('applyMerge', $methods);
	}



	function testGetApplyMethodFromReturnsEmptyForClassWithNoApplyMethods()
	{
		$methods = Utils::getApplyMethodFrom(BindValue::class);
		$this->assertSame([], $methods);
	}



	// ---- getSignatureFrom(ReflectionMethod) --------------------------------

	function testGetSignatureFromSingleArg()
	{
		// @signature "src: DateTime -> Int"
		$method = new ReflectionMethod(DateTimeFunc::class, 'applyToTimestamp');
		[$binds, $returnType] = Utils::getSignatureFrom($method);
		$this->assertEquals([new BindValue('src', 'DateTime')], $binds);
		$this->assertSame('Int', $returnType);
	}



	function testGetSignatureFromThreeArgs()
	{
		// @signature "year: Int, month: Int, day: Int -> DateTime"
		$method = new ReflectionMethod(DateTimeFunc::class, 'applyFromDate');
		[$binds, $returnType] = Utils::getSignatureFrom($method);
		$this->assertEquals([
			new BindValue('year', 'Int'),
			new BindValue('month', 'Int'),
			new BindValue('day', 'Int'),
		], $binds);
		$this->assertSame('DateTime', $returnType);
	}



	function testGetSignatureFromSixArgs()
	{
		// @signature "year: Int, month: Int, day: Int, hour: Int, minute: Int, sec: Int -> DateTime"
		$method = new ReflectionMethod(DateTimeFunc::class, 'applyFromDateTime');
		[$binds, $returnType] = Utils::getSignatureFrom($method);
		$this->assertEquals([
			new BindValue('year', 'Int'),
			new BindValue('month', 'Int'),
			new BindValue('day', 'Int'),
			new BindValue('hour', 'Int'),
			new BindValue('minute', 'Int'),
			new BindValue('sec', 'Int'),
		], $binds);
		$this->assertSame('DateTime', $returnType);
	}



	function testGetSignatureFromTwoArgs()
	{
		// @signature "format: Str, src: DateTime -> Str"
		$method = new ReflectionMethod(DateTimeFunc::class, 'applyFormat');
		[$binds, $returnType] = Utils::getSignatureFrom($method);
		$this->assertEquals([
			new BindValue('format', 'Str'),
			new BindValue('src', 'DateTime'),
		], $binds);
		$this->assertSame('Str', $returnType);
	}



	function testGetSignatureFromDictSingleArg()
	{
		// @signature "xs: Dict -> List<Str>"
		$method = new ReflectionMethod(DictFunc::class, 'applyKeys');
		[$binds, $returnType] = Utils::getSignatureFrom($method);
		$this->assertEquals([new BindValue('xs', 'Dict')], $binds);
		$this->assertSame('List<Str>', $returnType);
	}



	function testGetSignatureFromDictTwoArgs()
	{
		// @signature "xs: Dict, key: Str -> Bool"
		$method = new ReflectionMethod(DictFunc::class, 'applyHas');
		[$binds, $returnType] = Utils::getSignatureFrom($method);
		$this->assertEquals([
			new BindValue('xs', 'Dict'),
			new BindValue('key', 'Str'),
		], $binds);
		$this->assertSame('Bool', $returnType);
	}



	function testGetSignatureFromWithGenericType()
	{
		// @signature "xs: Dict, key: Str, default: a -> a"
		$method = new ReflectionMethod(DictFunc::class, 'applyGet');
		[$binds, $returnType] = Utils::getSignatureFrom($method);
		$this->assertEquals([
			new BindValue('xs', 'Dict'),
			new BindValue('key', 'Str'),
			new BindValue('default', 'a'),
		], $binds);
		$this->assertSame('a', $returnType);
	}



	function testGetSignatureFromThrowsWhenNoDocComment()
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessageMatches('/no doc comment/');
		$method = new ReflectionMethod(BindValue::class, '__construct');
		Utils::getSignatureFrom($method);
	}



	function testGetSignatureFromThrowsWhenNoSignatureAnnotation()
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessageMatches('/no @signature/');
		$method = new ReflectionMethod(DateTimeFunc::class, 'apply');
		Utils::getSignatureFrom($method);
	}

}
