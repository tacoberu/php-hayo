<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

interface SymbolProvider
{

	function lookup(string $symbol): ?BuildinFunc;

}



/**
 * Provider implementing this interface will use a symbol without a namespace.
 */
interface ShortSymbolProvider
{

	/**
	 * @return list<string>
	 */
	function getShortSymbolTable(): array;

}



interface BuildinFunc extends Applicable
{

	/**
	 * Fully-qualified name used in error messages, e.g. "Math.+" or "Str.len".
	 */
	function getQualifiedName(): string;



	/**
	 * Which arguments are required.
	 * @return list<BindValue>
	 */
	function getBinds(): array;



	/**
	 * Pass the required arguments and compute the result. Arguments must already
	 * be final values.
	 * @param array<string, FinalValue> $args
	 */
	function apply(array $args): Value;

}



interface Cache
{

	/**
	 * @param callable $cb
	 * @return FinalValue | ParametricValue
	 */
	function load(string $key, $cb);

}



/**
 * Implemented by PHP value classes that can live inside FinalValue and be
 * passed into Hayo scripts as typed values.
 *
 * The value itself knows its Hayo type name — no external recogniser needed.
 *
 *   class Money implements HayoValue {
 *       function getHayoType(): string { return 'Money'; }
 *       ...
 *   }
 */
interface HayoValue
{

	/**
	 * The Hayo type name of this value, as seen by scripts and @signature annotations.
	 */
	function getHayoType(): string;

}



/**
 * Optional interface for a SymbolProvider that also introduces a new type
 * into the Hayo runtime.
 *
 * When registerLibrary() receives a provider implementing this interface,
 * it automatically registers the type so that the type name is available
 * in @signature annotations and TypeValidator.
 *
 * Value recognition in gauseType() does NOT go through this interface —
 * it is handled by HayoValue::getHayoType() on the value itself.
 *
 * A provider may implement both SymbolProvider and TypeDescriptor (one
 * registration covers functions and the type), or they can be separate.
 */
interface TypeDescriptor
{

	/**
	 * Type name as seen by Hayo scripts and @signature annotations.
	 * Must match the string returned by HayoValue::getHayoType() for
	 * values of this type.
	 * Example: "Money", "Resource", "Color".
	 */
	function getTypeName(): string;

}



/**
 * Optional interface for providers that declare a sum type (discriminated union).
 *
 * When a provider implements this, the compiler can perform exhaustiveness
 * checking on `match` expressions whose subject has this type: every variant
 * must be covered by some pattern, or a wildcard `_` must be present.
 *
 * SumTypeProvider and BoolProvider implement this. User-declared types via
 * `type X = A | B | …` are registered as SumTypeProvider instances.
 */
interface SumTypeDescriptor
{

	/**
	 * Type name as it appears in Hayo (e.g. "Color", "Shape", "Bool").
	 */
	function getTypeName(): string;



	/**
	 * Names of all variants declared by this type.
	 * @return list<string>
	 */
	function getVariantNames(): array;



	/**
	 * Names of type parameters in declaration order — empty for monomorphic types.
	 * Example: `Result<a, b>` returns ['a', 'b']; `Color` returns [].
	 * @return list<string>
	 */
	function getTypeParams(): array;



	/**
	 * Argument-type names of one variant, in positional order.
	 * Type names may reference parameters from getTypeParams().
	 * Example: `Result<a, b> = Ok a | Err b` → getVariantArgTypes('Ok') = ['a']
	 * @return list<string>
	 */
	function getVariantArgTypes(string $variant): array;

}
