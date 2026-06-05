<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use ReflectionClass;
use ReflectionMethod;
use LogicException;


final class Utils
{

	/**
	 * Returns a list of all functions defined as static methods
	 * starting with the "apply" prefix for the given class.
	 * @param class-string $klassname
	 * @return list<string>
	 */
	static function getFunctionsFrom(string $klassname): array
	{
		$result = [];
		$ref = new ReflectionClass($klassname);
		foreach ($ref->getMethods(ReflectionMethod::IS_STATIC) as $method) {
			if (strpos($method->getName(), 'apply') === 0) {
				$result[] = lcfirst((string) substr($method->getName(), 5));
			}
		}
		return $result;
	}



	/**
	 * Returns a list of all static methods starting with the "apply" prefix for the given class.
	 * The array key is the method name (e.g. "applyFromDate"), the value is the textual signature.
	 * @param class-string $klassname
	 * @return array<string, array{0: list<BindValue>, 1: string}>
	 */
	static function getApplyMethodFrom(string $klassname): array
	{
		$result = [];
		$ref = new ReflectionClass($klassname);
		foreach ($ref->getMethods(ReflectionMethod::IS_STATIC) as $method) {
			if (strpos($method->getName(), 'apply') === 0) {
				$result[$method->getName()] = self::getSignatureFrom($method);
			}
		}
		return $result;
	}



	/**
	 * Parses the signature from the doc-comment of the given method.
	 * Signature format: `@signature "year: Int, month: Int -> DateTime"`.
	 * @return array{0: list<BindValue>, 1: string}
	 */
	static function getSignatureFrom(ReflectionMethod $src): array
	{
		$raw = self::extractSignatureText($src);

		// Split inputs from output on the rightmost top-level `->`, so that
		// nested function types like `(a -> b) -> List<b>` parse correctly.
		$arrowParts = self::splitTopLevel($raw, '->');
		$returnType = trim((string) array_pop($arrowParts));
		$inputPart = trim(implode('->', $arrowParts));

		$binds = [];
		if ($inputPart !== '') {
			foreach (self::splitTopLevel($inputPart, ',') as $token) {
				$token = trim($token);
				list($name, $type) = explode(':', $token, 2);
				$binds[] = new BindValue(trim($name), trim($type));
			}
		}

		return [$binds, $returnType];
	}



	/**
	 * Splits $s on $delim while respecting `()` and `<>` nesting.
	 *
	 *   splitTopLevel('a, b, c', ',') → ['a', ' b', ' c']
	 *   splitTopLevel('List<a, b>, c', ',') → ['List<a, b>', ' c']
	 *   splitTopLevel('cb: (a -> b) -> List<b>', '->') → ['cb: (a -> b) ', ' List<b>']
	 *
	 * @return list<string>
	 */
	static function splitTopLevel(string $s, string $delim): array
	{
		$parts = [];
		$current = '';
		$depthRound = 0;
		$depthAngle = 0;
		$delimLen = strlen($delim);
		$len = strlen($s);

		for ($i = 0; $i < $len; $i++) {
			// Detect a top-level delimiter before touching depth counters,
			// so that delimiters that share characters with brackets
			// (e.g. `->` shares `>` with closing angle brackets) are not
			// miscounted.
			if ($depthRound === 0 && $depthAngle === 0 && (string) substr($s, $i, $delimLen) === $delim) {
				$parts[] = $current;
				$current = '';
				$i += $delimLen - 1;
				continue;
			}

			$c = $s[$i];
			if ($c === '(') {
				$depthRound++;
			}
			elseif ($c === ')') {
				$depthRound--;
			}
			elseif ($c === '<') {
				$depthAngle++;
			}
			elseif ($c === '>') {
				// Skip `>` that is the tail of an arrow `->`
				if (!($i > 0 && $s[$i - 1] === '-')) {
					$depthAngle--;
				}
			}

			$current .= $c;
		}

		$parts[] = $current;
		return $parts;
	}



	/**
	 * "fromTimestamp" => "applyFromTimestamp"
	 */
	static function formatApplyFunc(string $name): string
	{
		return "apply" . ucfirst($name);
	}



	/**
	 * @param array<string, array{0: list<BindValue>, 1: string}> $map
	 * @return list<BindValue>
	 */
	static function selectArgumentsSignature(array $map, string $name): array
	{
		$func = self::formatApplyFunc($name);
		if (!isset($map[$func])) {
			throw new LogicException("Unknown signature for function: '{$name}'.");
		}
		list($args, ) = $map[$func];
		return $args;
	}



	/**
	 * Returns the return type value for the requested function.
	 * @param array<string, array{0: list<BindValue>, 1: string}> $map
	 */
	static function selectReturnType(array $map, string $name): string
	{
		$func = self::formatApplyFunc($name);
		if (!isset($map[$func])) {
			throw new LogicException("Unknown signature for function: '{$name}'.");
		}
		list(, $returnType) = $map[$func];
		return $returnType;
	}



	/**
	 * Returns the raw signature text from the doc-comment.
	 */
	private static function extractSignatureText(ReflectionMethod $src): string
	{
		$doc = $src->getDocComment();
		if ($doc === False) {
			throw new LogicException("Method {$src->getName()} has no doc comment.");
		}
		if ( ! preg_match('/@signature\s+"([^"]+)"/', $doc, $matches)) {
			throw new LogicException("Method {$src->getName()} has no @signature annotation.");
		}
		return $matches[1];
	}

}
