<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * Common base for libraries — declares the namespace the library lives under.
 * The namespace is fixed in the library code (it is PHP, so this is reliable),
 * which lets functions and types speak in fully-qualified names and lets other
 * libraries reference each other's types stably.
 */
interface LibraryProvider
{

	function getNamespace(): string;

}



interface FuncProvider extends LibraryProvider
{

	function lookupFunc(string $symbol): ?BuildinFunc;

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
 * Marker for a type definition returned by TypeProvider::lookupType(). It carries
 * no name — the caller knows the name from the lookupType($name) it asked for.
 * Structure comes from the sub-interfaces: ProductTypeDef (fields) and SumTypeDef
 * (variants).
 */
interface TypeDef
{

}



/**
 * A product type: a fixed tuple of positionally-typed fields (e.g. Money = Int Str).
 * HayoEngine::value() reads the field types to validate and build a value.
 */
interface ProductTypeDef extends TypeDef
{

	/**
	 * Field type names in positional order, e.g. ['Int', 'Str'].
	 * @return list<string>
	 */
	function getFieldTypes(): array;

}



/**
 * A library that provides one or more types, returned as TypeDef objects —
 * the type-side counterpart of FuncProvider for functions.
 *
 * lookupType() resolves a local type name (the engine splits "Ns.Type" and asks
 * $libs[Ns]->lookupType("Type"), just like function lookup). getProvidedTypeNames()
 * enumerates the local names so the engine/compiler can pre-register types for the
 * TypeInferrer and namespace-prefix the values built by the library's functions.
 */
interface TypeProvider extends LibraryProvider
{

	function lookupType(string $name): ?TypeDef;



	/**
	 * Local names of all types this provider declares.
	 * @return list<string>
	 */
	function getProvidedTypeNames(): array;

}



/**
 * A sum type: a discriminated union of variants. The compiler uses the variants
 * for exhaustiveness checking on `match` and for instantiating polymorphic types.
 *
 * BoolProvider and user-declared types (`type X = A | B | …`, via SumTypeProvider)
 * implement this. A TypeProvider library may also return a SumTypeDef from
 * lookupType() — the compiler collects it for the TypeInferrer like any sum type.
 */
interface SumTypeDef extends TypeDef
{

	/**
	 * Type name. For a SumTypeProvider registered directly in $libs (Bool, script
	 * `type X`) this is also its registration key; for a sum type returned from
	 * lookupType() it is unused (the caller already knows the name).
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
