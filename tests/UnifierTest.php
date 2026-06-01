<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;


class UnifierTest extends TestCase
{

	// -------------------------------------------------------------------------
	// Substitution

	function testSubstitutionEmptyGet(): void
	{
		$s = Substitution::empty_();
		$this->assertNull($s->get('a'));
	}



	function testSubstitutionSingleGet(): void
	{
		$s = Substitution::single('a', new TCon('Int'));
		$this->assertEquals(new TCon('Int'), $s->get('a'));
		$this->assertNull($s->get('b'));
	}



	function testSubstitutionApplyToTVar(): void
	{
		$s = Substitution::single('a', new TCon('Int'));
		$this->assertEquals(new TCon('Int'), $s->apply(new TVar('a')));
	}



	function testSubstitutionApplyToUnboundTVar(): void
	{
		$s = Substitution::single('a', new TCon('Int'));
		$this->assertEquals(new TVar('b'), $s->apply(new TVar('b')));
	}



	function testSubstitutionApplyToTCon(): void
	{
		$s = Substitution::single('a', new TCon('Int'));
		$this->assertEquals(new TCon('Str'), $s->apply(new TCon('Str')));
	}



	function testSubstitutionApplyToTApp(): void
	{
		// {a: Int} applied to List<a>  →  List<Int>
		$s = Substitution::single('a', new TCon('Int'));
		$result = $s->apply(new TApp('List', [new TVar('a')]));
		$this->assertEquals(new TApp('List', [new TCon('Int')]), $result);
	}



	function testSubstitutionApplyToTFun(): void
	{
		// {a: Int} applied to a -> Str  →  Int -> Str
		$s = Substitution::single('a', new TCon('Int'));
		$result = $s->apply(new TFun(new TVar('a'), new TCon('Str')));
		$this->assertEquals(new TFun(new TCon('Int'), new TCon('Str')), $result);
	}



	function testSubstitutionCompose(): void
	{
		// s1 = {a: Int}, s2 = {b: List<a>}
		// s2->compose(s1) = "s1 first, then s2"
		// Result: {a: Int, b: List<Int>}
		$s1 = Substitution::single('a', new TCon('Int'));
		$s2 = Substitution::single('b', new TApp('List', [new TVar('a')]));

		$composed = $s2->compose($s1);

		$this->assertEquals(new TCon('Int'), $composed->apply(new TVar('a')));
		$this->assertEquals(new TApp('List', [new TCon('Int')]), $composed->apply(new TVar('b')));
	}



	function testSubstitutionComposeOverrides(): void
	{
		// s1 = {a: Int}, s2 = {a: Str}
		// s2->compose(s1): s1 is inner → result for 'a' is s2(Int) = Int
		// (s1 takes precedence since it's the inner substitution)
		$s1 = Substitution::single('a', new TCon('Int'));
		$s2 = Substitution::single('a', new TCon('Str'));

		$composed = $s2->compose($s1);
		$this->assertEquals(new TCon('Int'), $composed->apply(new TVar('a')));
	}



	function testApplyToSchemeSkipsBound(): void
	{
		// {a: Int} applied to ∀a. a -> b  →  ∀a. a -> b  (a is bound, not replaced)
		$s = Substitution::single('a', new TCon('Int'));
		$scheme = new TScheme(['a'], new TFun(new TVar('a'), new TVar('b')));

		$result = $s->applyToScheme($scheme);

		$this->assertSame(['a'], $result->getVars());
		$this->assertEquals(new TFun(new TVar('a'), new TVar('b')), $result->getType());
	}



	function testApplyToSchemeFreeVarReplaced(): void
	{
		// {b: Str} applied to ∀a. a -> b  →  ∀a. a -> Str
		$s = Substitution::single('b', new TCon('Str'));
		$scheme = new TScheme(['a'], new TFun(new TVar('a'), new TVar('b')));

		$result = $s->applyToScheme($scheme);

		$this->assertEquals(new TFun(new TVar('a'), new TCon('Str')), $result->getType());
	}



	// -------------------------------------------------------------------------
	// Unifier — fresh variables

	function testFreshGeneratesUniqueNames(): void
	{
		$u = new Unifier();
		$t0 = $u->fresh();
		$t1 = $u->fresh();
		$t2 = $u->fresh();

		$this->assertNotSame($t0->getName(), $t1->getName());
		$this->assertNotSame($t1->getName(), $t2->getName());
	}



	function testFreshWithPrefix(): void
	{
		$u = new Unifier();
		$v = $u->fresh('r');
		$this->assertStringStartsWith('r', $v->getName());
	}



	// -------------------------------------------------------------------------
	// Unifier — unify

	function testUnifyVarWithCon(): void
	{
		// unify(a, Int)  →  {a: Int}
		$u = new Unifier();
		$s = $u->unify(new TVar('a'), new TCon('Int'));

		$this->assertEquals(new TCon('Int'), $s->apply(new TVar('a')));
	}



	function testUnifyConWithVar(): void
	{
		// unify(Int, a)  →  {a: Int}  (symmetric)
		$u = new Unifier();
		$s = $u->unify(new TCon('Int'), new TVar('a'));

		$this->assertEquals(new TCon('Int'), $s->apply(new TVar('a')));
	}



	function testUnifyVarWithVar(): void
	{
		// unify(a, b)  →  {a: b}
		$u = new Unifier();
		$s = $u->unify(new TVar('a'), new TVar('b'));

		$this->assertEquals(new TVar('b'), $s->apply(new TVar('a')));
	}



	function testUnifySameVar(): void
	{
		// unify(a, a)  →  {}
		$u = new Unifier();
		$s = $u->unify(new TVar('a'), new TVar('a'));

		$this->assertEmpty($s->getBindings());
	}



	function testUnifySameCon(): void
	{
		// unify(Int, Int)  →  {}
		$u = new Unifier();
		$s = $u->unify(new TCon('Int'), new TCon('Int'));

		$this->assertEmpty($s->getBindings());
	}



	function testUnifyDifferentConThrows(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Cannot unify 'Int' with 'Str'.");

		(new Unifier())->unify(new TCon('Int'), new TCon('Str'));
	}



	function testUnifyTAppSameConstructor(): void
	{
		// unify(List<a>, List<Int>)  →  {a: Int}
		$u = new Unifier();
		$s = $u->unify(
			new TApp('List', [new TVar('a')]),
			new TApp('List', [new TCon('Int')])
		);

		$this->assertEquals(new TCon('Int'), $s->apply(new TVar('a')));
	}



	function testUnifyTAppTwoArgs(): void
	{
		// unify(Result<a, b>, Result<Int, Str>)  →  {a: Int, b: Str}
		$u = new Unifier();
		$s = $u->unify(
			new TApp('Result', [new TVar('a'), new TVar('b')]),
			new TApp('Result', [new TCon('Int'), new TCon('Str')])
		);

		$this->assertEquals(new TCon('Int'), $s->apply(new TVar('a')));
		$this->assertEquals(new TCon('Str'), $s->apply(new TVar('b')));
	}



	function testUnifyTAppDifferentConstructorThrows(): void
	{
		$this->expectException(CompileException::class);

		(new Unifier())->unify(
			new TApp('List', [new TVar('a')]),
			new TApp('Maybe', [new TVar('a')])
		);
	}



	function testUnifyTAppDifferentArityThrows(): void
	{
		$this->expectException(CompileException::class);

		(new Unifier())->unify(
			new TApp('Result', [new TVar('a'), new TVar('b')]),
			new TApp('Result', [new TVar('a')])
		);
	}



	function testUnifyTFun(): void
	{
		// unify(a -> b, Int -> Str)  →  {a: Int, b: Str}
		$u = new Unifier();
		$s = $u->unify(
			new TFun(new TVar('a'), new TVar('b')),
			new TFun(new TCon('Int'), new TCon('Str'))
		);

		$this->assertEquals(new TCon('Int'), $s->apply(new TVar('a')));
		$this->assertEquals(new TCon('Str'), $s->apply(new TVar('b')));
	}



	function testUnifyTFunPropagatesSubstitution(): void
	{
		// unify(a -> a, Int -> b)  →  {a: Int, b: Int}
		$u = new Unifier();
		$s = $u->unify(
			new TFun(new TVar('a'), new TVar('a')),
			new TFun(new TCon('Int'), new TVar('b'))
		);

		$this->assertEquals(new TCon('Int'), $s->apply(new TVar('a')));
		$this->assertEquals(new TCon('Int'), $s->apply(new TVar('b')));
	}



	function testUnifyMismatchedKindsThrows(): void
	{
		$this->expectException(CompileException::class);

		// unify(Int, Int -> Str)  — TCon vs TFun
		(new Unifier())->unify(
			new TCon('Int'),
			new TFun(new TCon('Int'), new TCon('Str'))
		);
	}



	function testOccursCheckThrows(): void
	{
		$this->expectException(CompileException::class);
		$this->expectExceptionMessage("Occurs check: 'a' occurs in 'List<a>'.");

		// unify(a, List<a>)  →  infinite type — must be rejected
		(new Unifier())->unify(
			new TVar('a'),
			new TApp('List', [new TVar('a')])
		);
	}



	// -------------------------------------------------------------------------
	// TScheme instantiation

	function testSchemeInstantiateMonomorphic(): void
	{
		// ∀. Int  →  Int  (no vars to substitute)
		$u = new Unifier();
		$scheme = TScheme::mono(new TCon('Int'));

		$this->assertEquals(new TCon('Int'), $scheme->instantiate($u));
	}



	function testSchemeInstantiateCreatesFreash(): void
	{
		// ∀a. List<a>  →  List<t0>  (fresh var for a)
		$u = new Unifier();
		$scheme = new TScheme(['a'], new TApp('List', [new TVar('a')]));

		$result = $scheme->instantiate($u);

		$this->assertInstanceOf(TApp::class, $result);
		$this->assertSame('List', $result->getName());
		$this->assertCount(1, $result->getArgs());
		// The argument must be a fresh TVar, not 'a'
		$arg = $result->getArgs()[0];
		$this->assertInstanceOf(TVar::class, $arg);
		$this->assertNotSame('a', $arg->getName());
	}



	function testSchemeInstantiateTwiceGivesDifferentVars(): void
	{
		// Two instantiations of ∀a. a -> a must produce independent variables
		$u = new Unifier();
		$scheme = new TScheme(['a'], new TFun(new TVar('a'), new TVar('a')));

		$r1 = $scheme->instantiate($u);
		$r2 = $scheme->instantiate($u);

		$this->assertInstanceOf(TFun::class, $r1);
		$this->assertInstanceOf(TFun::class, $r2);
		$this->assertNotSame($r1->getFrom()->getName(), $r2->getFrom()->getName());
	}

}
