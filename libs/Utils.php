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
		$parts = explode('->', $raw, 2);
		$inputPart = trim($parts[0]);
		$returnType = trim($parts[1] ?? '');

		$binds = [];
		if ($inputPart !== '') {
			foreach (explode(',', $inputPart) as $token) {
				$token = trim($token);
				list($name, $type) = explode(':', $token, 2);
				$binds[] = new BindValue(trim($name), trim($type));
			}
		}

		return [$binds, $returnType];
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
