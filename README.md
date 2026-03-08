php-hayo
========

A lightweight scripting runtime written in PHP that allows you to interpret scripts directly in user space.
It is useful anywhere you need user-defined conditions, transformations, routines, validators, and similar things.

This is not a sandbox — it is a fully featured language runtime. You pass a script as a string, compile it
into a function, and then call that function with concrete data.

The reason for building a custom runtime instead of using an existing solution is explained in the
[Comparison](#comparison-with-alternatives) section.

**The language is purely functional.** You can assign variables, call functions, and move data around — but
the result of the entire computation can only be retrieved by returning it at the end. No side effects are
possible. You cannot log anything during the computation. (You can, however, accumulate a log in a variable
and return it as part of the result — that is the only way.)

**The language is statically typed.** Type checking is performed during the compile phase, for example to
prevent adding numbers to strings. The goal is not to guarantee complete type safety, but rather to avoid
embarrassing errors. Division by zero will still fail at runtime.



## Quick Start
```bash
composer require tacoberu/hayo
```
```php
require __DIR__ . '/vendor/autoload.php';

use Taco\Hayo;

// Simple expression
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("1 + 1"); // 2

// Expression with a variable
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("1 + a")
    ->apply(["a" => 1]); // 2
```



## Usage Examples

**Bytecode caching**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->setCache(new CacheImpl)
    ->evaluate("1 + a")
    ->apply(["a" => 1]); // 2
```

**Custom function libraries**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->registerLibrary("MyStrings", new MyOwnImplementationOfStringsProvider())
    ->evaluate('MyStrings.format("calculate: {0}", [1 + a])')
    ->apply(["a" => 1]); // "calculate: 2"
```

**Local variables**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("
vat = 1.23
price * vat
    ")
    ->apply(["price" => 100]); // 123
```

**Local functions and lambdas**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("
inc = x -> x + 1
inc counter
    ")
    ->apply(["counter" => 41]); // 42
```

**Higher-order functions — map, fold, and more**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("
list.map (x -> x * x) xs
    ")
    ->apply(["xs" => [1, 2, 3]]); // [1, 4, 9]
```

**Pipe chain operator**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("
xs
	|> List.map (x -> x * x)
	|> List.fold 0 (prev curr -> prev + curr)
    ")
    ->apply(["xs" => [1, 2, 3, 4]]); // 30
```



## Features

- Purely functional language
- Type inference
- Local variables in scripts
- Local functions and lambdas defined directly in scripts
- if-then-else expressions
- Higher-order functions: map, fold, filter, and more
- Optional bytecode caching
- Register custom function libraries
- Optional custom parser (while keeping the existing AST)



## Built-in Functions

### Arithmetic
Basic operators: `+`, `-`, `*`, `div`, `mod`

Rounding:

- `Math.ceil` — round up
- `Math.floor` — round down
- `Math.round` — mathematical rounding, to a given precision


### Logical Operators
`and`, `or`, `not`


### Comparison Operators
Basic: `==`, `!=`, `<`, `>`, `<=`, `>=`

Set operators:

- `IN` — the left-hand value is found in the right-hand set
- `HAS` — the left-hand set contains the right-hand value
- `SUPERSET` — the left set contains all elements of the right set
- `SUBSET` — the right set contains all elements of the left set
- `INTERSECTS` — both sets share at least one common element


### Strings
- `Str.len` — string length
- `Str.split` — split by separator
- `Str.concat` — concatenate two strings
- `Str.format` — format using a mask (`{0}`, `{1}`, …)
- `Str.indexOf` — position of a substring, or -1
- `Str.contains` — whether the string contains a substring
- `Str.startsWith` — whether the string starts with a given fragment
- `Str.endsWith` — whether the string ends with a given fragment
- `Str.sub` — substring starting at `index` with length `len`
- `Str.toUpper` — convert to uppercase
- `Str.toLower` — convert to lowercase


### Lists
- `List.len` — number of elements
- `List.first` — first element
- `List.at` — element at a given index, or a default value
- `List.exist` — whether an element exists at the given index
- `List.concat` — concatenate two lists
- `List.push` — append an element to the end
- `List.indexOf` — index of a searched value (from an optional offset), or -1
- `List.slice` — sub-list starting at `start` with maximum length `length`
- `List.split` — split a list by a predicate into at most `limit` parts
- `List.map` — apply a function to each element
- `List.fold` — reduce a list to a single value
- `List.filter` — filter elements by a predicate
- `List.sort` — sorting: `List.Asc`, `List.Desc`


### Dictionaries (Dict)
- `Dict.has` — whether a key exists
- `Dict.get` — value by key
- `Dict.merge` — merge two dictionaries
- `Dict.keys` — list of keys
- `Dict.values` — list of values


### Date and Time (DateTime)
- `DateTime.toTimestamp` — convert to Unix timestamp
- `DateTime.fromTimestamp` — construct a value from a timestamp
- `DateTime.fromDate` — construct from date parts: `(DateTime.fromDate 2025 1 5)`
- `DateTime.fromDateTime` — construct from date and time: `(DateTime.fromDateTime 2025 1 5 12 24 55)`
- `DateTime.format` — format using a mask: `DateTime.format "%y. %j. %d" src`



## Architecture

The runtime consists of three packages:

- `hayo-ast` — AST node definitions
- `hayo-parser` — the default parser; if the syntax does not suit you, write your own parser returning the same AST
- `hayo` — the PHP runtime itself



## Comparison with Alternatives

The most common candidate for an embedded scripting language in PHP is
[Symfony Expression Language](https://packagist.org/packages/symfony/expression-language).
Below is an overview of where the two solutions differ:

| Feature | Hayo | Symfony Expression Language |
|---|---|---|
| Custom functions (import) | ✅ | ✅ |
| Local variables in code | ✅ | ⚠️ only by passing from outside, not by defining inside the script |
| Local functions and lambdas in code | ✅ | ❌ only by passing from outside, not by defining inside the script |
| Map / fold / filter | ✅ | ⚠️ only via custom registered functions, not natively |
| if-then-elseif-then-else | ✅ | ⚠️ only the ternary operator `?:` |
| Custom syntax / parser | ✅ | ❌ |

Symfony Expression Language is a solid and well-tested library. However, if you need local variables,
lambdas, or branching with multiple conditions, Hayo fills those gaps.
