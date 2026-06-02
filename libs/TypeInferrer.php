<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * Hindley-Milner type inference (algorithm W) over Hayo's partially-evaluated AST.
 *
 * Runs after Compiler::partialEvaluate() and before compileRuntimeValue().
 * At that point the AST contains: FinalValue, Scalar, Expr, Lambda, Form,
 * Composite, BuildinFunc, and plain strings (unresolved variable references).
 *
 * The main entry point is infer(). It returns the inferred type together with
 * the accumulated substitution. Callers that only need error-checking may
 * ignore both return values — the side-effect is throwing CompileException
 * when unification fails.
 */
class TypeInferrer
{

	/**
	 * @var Unifier
	 */
	private $unifier;

	/**
	 * Registry of sum types: typeName => list of variant names.
	 * Used for exhaustiveness checking of match expressions.
	 * @var array<string, list<string>>
	 */
	private $sumTypes;

	/**
	 * Reverse index: bare variant name => owning type name (e.g. 'True' => 'Bool').
	 * Built from $sumTypes; used to resolve unqualified patterns (`case True then …`).
	 * @var array<string, string>
	 */
	private $variantToType;

	/**
	 * @param array<string, list<string>> $sumTypes
	 */
	function __construct(Unifier $unifier, array $sumTypes = [])
	{
		$this->unifier = $unifier;
		$this->sumTypes = $sumTypes;

		$this->variantToType = [];
		foreach ($sumTypes as $typeName => $variants) {
			foreach ($variants as $variant) {
				$this->variantToType[$variant] = $typeName;
			}
		}
	}



	/**
	 * Infers the type of an AST node in the given type environment.
	 *
	 * @param mixed $node post-partialEvaluate AST node
	 * @return array{0: Type_, 1: Substitution}
	 * @throws CompileException on unification failure (type error)
	 */
	function infer(TypeEnv $env, $node): array
	{
		switch (True) {
			case $node instanceof FinalValue:
				return [$this->typeOfFinalValue($node), Substitution::empty_()];

			case $node instanceof Scalar:
				return [$this->typeOfScalar($node), Substitution::empty_()];

			case $node instanceof BuildinFunc:
				return [$this->schemeFor($node)->instantiate($this->unifier), Substitution::empty_()];

			case is_string($node):
				return $this->inferVar($env, $node);

			case $node instanceof Expr:
				return $this->inferExpr($env, $node);

			case $node instanceof Lambda:
				return $this->inferLambda($env, $node);

			case $node instanceof Form:
				return $this->inferForm($env, $node);

			case $node instanceof Composite:
				return $this->inferComposite($env, $node);

			default:
				// Unknown node type — conservative: return fresh var, no error
				return [$this->unifier->fresh(), Substitution::empty_()];
		}
	}



	/**
	 * Literals
	 */
	private function typeOfFinalValue(FinalValue $v): Type_
	{
		return $this->typeFromName($v->type());
	}



	private function typeOfScalar(Scalar $s): Type_
	{
		switch ($s->type()) {
			case 'Int':
			case 'Number':
				return new TCon('Int');

			case 'Real':
				return new TCon('Real');

			case 'Str':
			case 'String':
				return new TCon('Str');

			case 'Symbol':
				switch ($s->getValue()) {
					case 'True':
					case 'False':
						return new TCon('Bool');
					case 'Null':
						return new TCon('Null');
				}
				return $this->unifier->fresh();

			default:
				return $this->unifier->fresh();
		}
	}



	/**
	 * Variable references
	 * @return array{0: Type_, 1: Substitution}
	 */
	private function inferVar(TypeEnv $env, string $name): array
	{
		$scheme = $env->lookup($name);
		if ($scheme !== Null) {
			return [$scheme->instantiate($this->unifier), Substitution::empty_()];
		}
		// External argument: type is unknown at compile time — use fresh var
		return [$this->unifier->fresh(), Substitution::empty_()];
	}



	/**
	 * Expressions
	 * @return array{0: Type_, 1: Substitution}
	 */
	private function inferExpr(TypeEnv $env, Expr $expr): array
	{
		$items = $expr->getItems();

		if ($expr->getNotation() === Expr::NotationInfix) {
			return $this->inferInfix($env, $items[0], $items[1], $items[2]);
		}

		if ($expr->getNotation() === Expr::NotationPrefix) {
			return $this->inferApplication($env, $items[0], array_slice($items, 1));
		}

		return [$this->unifier->fresh(), Substitution::empty_()];
	}



	/**
	 * Infix operator: left op right
	 * Treated as a curried application: op(left)(right).
	 *
	 * @param mixed $left
	 * @param mixed $op
	 * @param mixed $right
	 * @return array{0: Type_, 1: Substitution}
	 */
	private function inferInfix(TypeEnv $env, $left, $op, $right): array
	{
		[$tLeft, $s1] = $this->infer($env, $left);
		[$tOp, $s2] = $this->infer($env->apply($s1), $op);
		[$tRight, $s3] = $this->infer($env->apply($s2->compose($s1)), $right);

		$s = $s3->compose($s2)->compose($s1);
		$result = $this->unifier->fresh();

		// The operator must have type: tLeft -> tRight -> result
		$expected = new TFun($s->apply($tLeft), new TFun($s->apply($tRight), $result));
		$s4 = $this->unifier->unify($s->apply($tOp), $expected);

		return [$s4->apply($result), $s4->compose($s)];
	}



	/**
	 * Prefix (curried) application: fn arg1 arg2 …
	 *
	 * @param mixed $fn
	 * @param list<mixed> $args
	 * @return array{0: Type_, 1: Substitution}
	 */
	private function inferApplication(TypeEnv $env, $fn, array $args): array
	{
		[$tFn, $s] = $this->infer($env, $fn);
		$result = $this->unifier->fresh();
		$argTypes = [];

		$currentEnv = $env->apply($s);
		foreach ($args as $arg) {
			[$tArg, $sArg] = $this->infer($currentEnv, $arg);
			$argTypes[] = $s->apply($tArg);
			$s = $sArg->compose($s);
			$currentEnv = $currentEnv->apply($sArg);
		}

		// Build the expected function type: t1 -> t2 -> … -> result
		$expected = $result;
		foreach (array_reverse($argTypes) as $t) {
			$expected = new TFun($t, $expected);
		}

		$s2 = $this->unifier->unify($s->apply($tFn), $expected);

		return [$s2->apply($result), $s2->compose($s)];
	}



	/**
	 * Lambda: args -> body
	 *
	 * Each argument gets a fresh type variable. The body is inferred in the
	 * extended environment. The result type is the curried function type
	 * t_arg1 -> t_arg2 -> … -> t_body.
	 *
	 * @return array{0: Type_, 1: Substitution}
	 */
	private function inferLambda(TypeEnv $env, Lambda $lambda): array
	{
		$argVars = [];
		$extEnv = $env;

		foreach ($lambda->getArgs() as $arg) {
			$fresh = $this->unifier->fresh();
			$argVars[] = $fresh;
			$extEnv = $extEnv->extend($arg, TScheme::mono($fresh));
		}

		[$bodyType, $s] = $this->infer($extEnv, $lambda->getExpr());

		// Build function type from right to left
		$type = $bodyType;
		foreach (array_reverse($argVars) as $av) {
			$type = new TFun($s->apply($av), $type);
		}

		return [$type, $s];
	}



	/**
	 * if cond then e1 elif … else eN
	 *
	 * Rules:
	 *   - each condition must have type Bool
	 *   - all branches must have the same type
	 *
	 * @return array{0: Type_, 1: Substitution}
	 */
	private function inferForm(TypeEnv $env, Form $form): array
	{
		if ($form->getName() === 'match') {
			return $this->inferMatch($env, $form);
		}

		if ($form->getName() !== 'if-then-else') {
			return [$this->unifier->fresh(), Substitution::empty_()];
		}

		$s = Substitution::empty_();
		$branchType = Null;

		foreach ($form->getItems() as $block) {
			/** @var object{cond: mixed, expr: mixed} $block */
			if ($block->cond !== Null) {
				[$tCond, $sCond] = $this->infer($env->apply($s), $block->cond);
				$sUnify = $this->unifier->unify($s->apply($tCond), new TCon('Bool'));
				$s = $sUnify->compose($sCond)->compose($s);
			}

			[$tExpr, $sExpr] = $this->infer($env->apply($s), $block->expr);
			$s = $sExpr->compose($s);

			if ($branchType === Null) {
				$branchType = $tExpr;
			}
			else {
				$sUnify = $this->unifier->unify($s->apply($branchType), $s->apply($tExpr));
				$s = $sUnify->compose($s);
				$branchType = $sUnify->apply($branchType);
			}
		}

		return [$branchType ?? $this->unifier->fresh(), $s];
	}



	/**
	 * match subject | Pat binds -> e1 | …
	 *
	 * Rules:
	 *   - all arms must produce the same type (the result type of match)
	 *   - if subject type is a known sum type, every variant must be covered
	 *     by some arm (exhaustiveness) — unless a wildcard '_' is present
	 *
	 * @return array{0: Type_, 1: Substitution}
	 */
	private function inferMatch(TypeEnv $env, Form $form): array
	{
		$items = $form->getItems();
		$subject = $items[0];
		$arms = array_slice($items, 1);

		// 1. Infer subject type
		[$tSubject, $s] = $this->infer($env, $subject);

		// 2. Infer arm bodies — all must unify to the same result type.
		//    Each non-wildcard pattern that names a known sum type forces the
		//    subject's type to be that sum type (regular HM unification).
		$resultType = Null;
		$hasWildcard = False;
		$patternNames = [];

		foreach ($arms as $arm) {
			/** @var object{pattern: string, binds: list<string>, expr: mixed} $arm */
			if ($arm->pattern === '_') {
				$hasWildcard = True;
			}
			else {
				$resolved = $this->resolvePatternType($arm->pattern);
				if ($resolved !== Null) {
					[$typeName, $variant] = $resolved;
					$patternNames[] = $variant;

					// Unify the subject with the pattern's declaring type. This is
					// how an external argument c with no prior constraint becomes
					// `Color` once a `case Color.Red …` arm is seen.
					$sUnify = $this->unifier->unify($s->apply($tSubject), new TCon($typeName));
					$s = $sUnify->compose($s);
				}
				else {
					// Pattern is not a registered constructor — fall back to bare
					// last-segment matching (e.g. literal-like or scalar patterns)
					$dotPos = strrpos($arm->pattern, '.');
					$patternNames[] = $dotPos !== False
						? substr($arm->pattern, $dotPos + 1)
						: $arm->pattern;
				}
			}

			// Extend environment with bound payload variables (each a fresh var)
			$armEnv = $env->apply($s);
			foreach ($arm->binds as $bind) {
				$armEnv = $armEnv->extend($bind, TScheme::mono($this->unifier->fresh()));
			}

			[$tArm, $sArm] = $this->infer($armEnv, $arm->expr);
			$s = $sArm->compose($s);

			if ($resultType === Null) {
				$resultType = $tArm;
			}
			else {
				$sUnify = $this->unifier->unify($s->apply($resultType), $s->apply($tArm));
				$s = $sUnify->compose($s);
				$resultType = $sUnify->apply($resultType);
			}
		}

		// 3. Exhaustiveness check (only when no wildcard and subject is a known sum type)
		if (!$hasWildcard) {
			$resolvedSubject = $s->apply($tSubject);
			if ($resolvedSubject instanceof TCon) {
				$typeName = (string) $resolvedSubject;
				if (isset($this->sumTypes[$typeName])) {
					$declared = $this->sumTypes[$typeName];
					$missing = array_values(array_diff($declared, $patternNames));
					if ($missing !== []) {
						throw CompileException::NonExhaustiveMatch($typeName, $missing);
					}
				}
			}
		}

		return [$resultType ?? $this->unifier->fresh(), $s];
	}



	/**
	 * Resolves a constructor pattern to its declaring sum type and variant name.
	 *
	 *   'Color.Red' → ['Color', 'Red'] (qualified)
	 *   'True' → ['Bool', 'True'] (bare — found in reverse index)
	 *   '42' → null (not a registered constructor)
	 *
	 * @return array{0: string, 1: string}|null
	 */
	private function resolvePatternType(string $pattern): ?array
	{
		$dotPos = strrpos($pattern, '.');
		if ($dotPos !== False) {
			$typeName = substr($pattern, 0, $dotPos);
			$variant = substr($pattern, $dotPos + 1);
			if (isset($this->sumTypes[$typeName]) && in_array($variant, $this->sumTypes[$typeName], True)) {
				return [$typeName, $variant];
			}
			return Null;
		}
		if (isset($this->variantToType[$pattern])) {
			return [$this->variantToType[$pattern], $pattern];
		}
		return Null;
	}



	/**
	 * Composite values (List, Dict, Tuple)
	 *
	 * @return array{0: Type_, 1: Substitution}
	 */
	private function inferComposite(TypeEnv $env, Composite $comp): array
	{
		switch ($comp->type()) {
			case Composite::TypeList:
				return $this->inferList($env, (array) $comp->getItems());

			case Composite::TypeDict:
				// Dict values may be heterogeneous — no element inference yet
				return [new TCon('Dict'), Substitution::empty_()];

			case Composite::TypeTuple:
				return [new TCon('Tuple'), Substitution::empty_()];

			default:
				return [$this->unifier->fresh(), Substitution::empty_()];
		}
	}



	/**
	 * List literal: all elements must have the same type.
	 *
	 * @param list<mixed> $items
	 * @return array{0: Type_, 1: Substitution}
	 */
	private function inferList(TypeEnv $env, array $items): array
	{
		$elemVar = $this->unifier->fresh();
		$s = Substitution::empty_();

		foreach ($items as $item) {
			[$tElem, $sElem] = $this->infer($env->apply($s), $item);
			$sUnify = $this->unifier->unify($s->apply($elemVar), $s->apply($tElem));
			$s = $sUnify->compose($sElem)->compose($s);
			$elemVar = $sUnify->apply($elemVar);
		}

		return [new TApp('List', [$elemVar]), $s];
	}



	/**
	 * Builds a TScheme for a built-in function from its getBinds() signature
	 * and type() return type.
	 *
	 * Named type variables (a, b, num, …) are shared across the same signature
	 * so that e.g. List<a> in the argument and a in the return are the same var.
	 */
	private function schemeFor(BuildinFunc $fn): TScheme
	{
		$namedVars = []; // string => TVar — shared within one signature
		$getVar = static function (string $name) use (&$namedVars): TVar {
			if (!isset($namedVars[$name])) {
				$namedVars[$name] = new TVar($name);
			}
			return $namedVars[$name];
		};

		$returnType = $this->typeFromNameWithVars($fn->type(), $getVar);

		// Build curried function type: arg1 -> arg2 -> … -> return
		$type = $returnType;
		foreach (array_reverse($fn->getBinds()) as $bind) {
			$argType = $this->typeFromNameWithVars($bind->getTypeName(), $getVar);
			$type = new TFun($argType, $type);
		}

		return new TScheme($type->freeVars(), $type);
	}



	/**
	 * Type name → Type_ conversion
	 */
	private function typeFromName(string $name): Type_
	{
		return $this->typeFromNameWithVars($name, static function (string $n): TVar {
			return new TVar($n);
		});
	}



	/**
	 * Converts a type-name string (from @signature annotations or FinalValue::type())
	 * to a Type_ object.
	 *
	 * Named single-letter variables (a, b, …) and 'num' are resolved through
	 * $getVar so that multiple occurrences of the same name map to the same TVar.
	 *
	 * @param callable(string): TVar $getVar
	 */
	private function typeFromNameWithVars(string $name, callable $getVar): Type_
	{
		$name = trim($name);

		switch ($name) {
			case 'Int':
			case 'Number':
				return new TCon('Int');

			case 'Real':
			case 'Float':
				return new TCon('Real');

			case 'Str':
			case 'String':
				return new TCon('Str');

			case 'Bool':
				return new TCon('Bool');

			case 'Null':
				return new TCon('Null');

			case 'DateTime':
				return new TCon('DateTime');

			case 'Dict':
				return new TCon('Dict');

			case 'Tuple':
				return new TCon('Tuple');

			case 'Num':
				return $getVar('num'); // numeric type variable (Math operators)

			case 'Callable':
				return $this->unifier->fresh(); // TODO: function type in Phase 3+

			case '':
			case '?':
				return $this->unifier->fresh();
		}

		// Single lowercase letter or 'num' — named type variable
		if (preg_match('/^[a-z]$/', $name)) {
			return $getVar($name);
		}

		// Parameterised: List<a>, Result<a, b>, List<Str>, …
		if (preg_match('/^([A-Za-z]\w*)<(.+)>$/', $name, $m)) {
			$argNames = $this->splitTypeArgs($m[2]);
			$argTypes = array_map(function (string $a) use ($getVar): Type_ {
				return $this->typeFromNameWithVars($a, $getVar);
			}, $argNames);
			return new TApp($m[1], $argTypes);
		}

		// Starts with uppercase — custom or built-in named type (Money, Resource, …)
		if (preg_match('/^[A-Z]/', $name)) {
			return new TCon($name);
		}

		return $this->unifier->fresh();
	}



	/**
	 * Splits a comma-separated type argument list while respecting nested
	 * angle brackets, e.g. "a, Result<b, c>, d" → ["a", "Result<b, c>", "d"].
	 *
	 * @return list<string>
	 */
	private function splitTypeArgs(string $s): array
	{
		$depth = 0;
		$current = '';
		$parts = [];

		for ($i = 0, $len = strlen($s); $i < $len; $i++) {
			$c = $s[$i];
			if ($c === '<') {
				$depth++;
			}
			elseif ($c === '>') {
				$depth--;
			}
			elseif ($c === ',' && $depth === 0) {
				$parts[] = trim($current);
				$current = '';
				continue;
			}
			$current .= $c;
		}

		if ($current !== '') {
			$parts[] = trim($current);
		}

		return $parts;
	}

}
