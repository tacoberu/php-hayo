<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


/**
 * End-to-end tests for static typing of List<a> and Dict literals through
 * the compile pipeline (decoder → partialEvaluate → TypeInferrer → runtime).
 */
class ListDictTypeTest extends TestCase
{

	function testHomogeneousIntList(): void
	{
		$this->assertSame([1, 2, 3],
			$this->engine()->evaluate('[1, 2, 3]'));
	}



	function testHomogeneousStrList(): void
	{
		$this->assertSame(['a', 'b', 'c'],
			$this->engine()->evaluate('["a", "b", "c"]'));
	}



	function testHomogeneousBoolList(): void
	{
		$this->assertSame([true, false, true],
			$this->engine()->evaluate('[True, False, True]'));
	}



	function testEmptyList(): void
	{
		$this->assertSame([], $this->engine()->evaluate('[]'));
	}



	function testHeterogeneousListIntStrRejected(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Cannot unify 'Int' with 'Str'.");

		$this->engine()->evaluate('[1, "x"]');
	}



	function testHeterogeneousListBoolIntRejected(): void
	{
		$this->expectException(CompileException::class);

		$this->engine()->evaluate('[True, 1]');
	}



	function testNestedHomogeneousList(): void
	{
		$this->assertSame([[1, 2], [3, 4]],
			$this->engine()->evaluate('[[1, 2], [3, 4]]'));
	}



	function testNestedHeterogeneousInnerListRejected(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Cannot unify 'Int' with 'Str'.");

		$this->engine()->evaluate('[[1], ["x"]]');
	}



	/**
	 * List.* — type-aware builtins via signatures
	 */
	function testListLenOnIntList(): void
	{
		$this->assertSame(4, $this->engine()->evaluate('List.len [1, 2, 3, 4]'));
	}



	function testListLenOnEmptyList(): void
	{
		$this->assertSame(0, $this->engine()->evaluate('List.len []'));
	}



	function testStrSplitProducesStrList(): void
	{
		$this->assertSame(['a', 'b', 'c'],
			$this->engine()->evaluate('Str.split "a,b,c" ","'));
	}



	function testStrConcatTakesStrListAndReturnsStr(): void
	{
		$this->assertSame('a-b-c',
			$this->engine()->evaluate('Str.concat ["a", "b", "c"] "-"'));
	}



	function testStrConcatRejectsIntList(): void
	{
		// Str.concat src: List<Str>, sep: Str — passing List<Int> should fail
		$this->expectException(CompileException::class);

		$this->engine()->evaluate('Str.concat [1, 2, 3] "-"');
	}



	/**
	 * Dict — heterogeneous values are allowed (Dict is structural)
	 */
	function testEmptyDict(): void
	{
		$this->assertEquals((object) [],
			$this->engine()->evaluate('{}'));
	}



	function testIntValuedDict(): void
	{
		$this->assertEquals((object) ['a' => 1, 'b' => 2],
			$this->engine()->evaluate('{a: 1, b: 2}'));
	}



	function testHeterogeneousDictAllowed(): void
	{
		// Unlike List<a>, Dict is intentionally heterogeneous — each field has
		// its own type. The compiler does not enforce a single value type.
		$this->assertEquals(
			(object) ['name' => 'Alice', 'age' => 30, 'admin' => true],
			$this->engine()->evaluate('{name: "Alice", age: 30, admin: True}')
		);
	}



	function testDictHasReturnsBool(): void
	{
		$this->assertTrue($this->engine()->evaluate('Dict.has {a: 1, b: 2} "a"'));
		$this->assertFalse($this->engine()->evaluate('Dict.has {a: 1, b: 2} "c"'));
	}



	function testDictHasRejectsNonStrKey(): void
	{
		// Dict.has xs: Dict, key: Str — passing Int as key must fail
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Cannot unify 'Str' with 'Int'.");

		$this->engine()->evaluate('Dict.has {a: 1} 42');
	}



	function testDictGetWithDefault(): void
	{
		$this->assertSame(1,
			$this->engine()->evaluate('Dict.get {a: 1, b: 2} "a" 0'));
		$this->assertSame(99,
			$this->engine()->evaluate('Dict.get {a: 1} "missing" 99'));
	}



	function testDictKeysReturnsStrList(): void
	{
		$this->assertSame(['a', 'b'],
			$this->engine()->evaluate('Dict.keys {a: 1, b: 2}'));
	}



	function testDictMergeReturnsDict(): void
	{
		$this->assertEquals(
			(object) ['a' => 1, 'b' => 22, 'c' => 3],
			$this->engine()->evaluate('Dict.merge {a: 1, b: 2} {b: 22, c: 3}')
		);
	}



	/**
	 * List<a> propagates through external arguments via unification
	 */
	function testExternalListInfluencesElementType(): void
	{
		// xs comes from outside; the literal 99 in `List.push xs 99` constrains
		// xs to List<Int> at compile time. Passing a List<Int> at runtime works.
		$result = $this->engine()->evaluate(
			'List.push xs 99',
			['xs' => [1, 2, 3]]
		);
		$this->assertSame([1, 2, 3, 99], $result);
	}



	function testExternalListConcat(): void
	{
		$result = $this->engine()->evaluate(
			'List.concat xs ys',
			['xs' => [1, 2], 'ys' => [3, 4]]
		);
		$this->assertSame([1, 2, 3, 4], $result);
	}



	/**
	 * Constant-only mismatches are now caught at compile time too (via the
	 * pre-fold type check inside partialEvaluateExpr).
	 */
	function testConstantOnlyMismatchCaught(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Cannot unify 'Int' with 'Str'.");

		$this->engine()->compile('List.concat [1, 2] ["a", "b"]');
	}



	/**
	 * Lambda body type errors are now caught at compile time too — builtin
	 * signatures use explicit function types like `(a -> b)` instead of
	 * the opaque `Callable` placeholder.
	 */
	function testLambdaBodyTypeErrorInListMap(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Cannot unify 'Int' with 'Str'.");

		// Str.toUpper expects Str, but the list contains Int — the (a -> b)
		// signature on List.map unifies a=Int and rejects the misuse.
		$this->engine()->compile('List.map [1, 2, 3] (x -> Str.toUpper x)');
	}



	function testLambdaBodyTypeErrorInListFilter(): void
	{
		// filter's lambda must return Bool — returning Int fails the unification
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Cannot unify 'Bool' with 'Int'.");

		$this->engine()->compile('List.filter [1, 2, 3] (x -> x + 1)');
	}



	function testLambdaBodyTypeErrorInListFold(): void
	{
		// fold's init is Str but list elements are Int — acc and element types clash
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Cannot unify");

		$this->engine()->compile('List.fold [1, 2, 3] "init" (acc x -> acc + x)');
	}



	private function engine(): HayoEngine
	{
		return HayoEngine::WithDefaultLibraries();
	}

}
