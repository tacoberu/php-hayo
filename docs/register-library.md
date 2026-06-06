# Hayo — Registering custom functions and types

See also: **[Language syntax](syntax.md)** · **[Built-in functions](libs.md)**

Hayo can be extended from the PHP side with custom functions and custom data types,
without touching the engine. A library is registered with `registerLibrary()`:

```php
use Taco\Hayo\HayoEngine;

$engine = HayoEngine::WithDefaultLibraries()
    ->registerLibrary(new WalletLibrary());

$engine->evaluate('Wallet.fromAmount 9900 "CZK"');
```

`registerLibrary(LibraryProvider $lib)` registers the library under the namespace the
**library declares itself** via `getNamespace()` (e.g. `Wallet` → `Wallet.fromAmount`,
type `Wallet.Money`). A single library can provide **both functions and types** and
**multiple types**.



## Interface overview

| Interface / class | Role |
|---|---|
| `LibraryProvider` | common base — `getNamespace()` (the library's namespace) |
| `FuncProvider` | function library — `lookupFunc($symbol)` returns `BuildinFunc`, or `null` |
| `BuildinFunc` | a single function (signature + computation) |
| `BindValue` | description of one function argument (name + type) |
| `FinalValue` | wrapper around a concrete value (value + type name) |
| `TypeProvider` | type library — `lookupType($name)` + `getProvidedTypeNames()`; can do **multiple types** |
| `TypeDef` | a type as an object — empty marker; structure comes from a sub-interface |
| `ProductTypeDef` | `TypeDef` of a product type — fields (`getFieldTypes()`) |
| `SumTypeDef` | `TypeDef` of a sum type — name + variants, `match` exhaustiveness |
| `ShortSymbolProvider` | optional: symbols usable without a prefix (like `+`, `mod`) |

`FuncProvider` and `TypeProvider` both extend `LibraryProvider`, so every library has
`getNamespace()`. Everything lives in the `Taco\Hayo` namespace.



## 1. Registering custom functions

Functions are grouped into a library — a class implementing `FuncProvider`. It must be
able to return its namespace and, by symbol name, the matching function (or `null`):

```php
interface LibraryProvider
{
    function getNamespace(): string;
}

interface FuncProvider extends LibraryProvider
{
    function lookupFunc(string $symbol): ?BuildinFunc;
}
```

`lookupFunc()` receives **only the bare function name without a namespace**. For
`Wallet.fromAmount` it gets `lookupFunc('fromAmount')`; for the short symbol `+` (see
`ShortSymbolProvider`) it gets `lookupFunc('+')`. The engine assumes exactly one dot in
the symbol; namespaces do not nest.

The function itself is a `BuildinFunc`:

```php
interface BuildinFunc extends Applicable
{
    // Return type (from the Value interface) — for type inference.
    function type(): string;

    // Which arguments the function requires (for type checking and inference).
    // @return list<BindValue>
    function getBinds(): array;

    // Receive the arguments (already FinalValue) and compute the result.
    // @param array<string, FinalValue> $args
    function apply(array $args): Value;

    // Short representation for error messages (from the Value interface).
    function __toString(): string;
}
```

> A function has **no** "qualified name" — the engine takes the name for error messages
> from the call site in the script.

### Arguments: `BindValue`

`new BindValue(string $name, string $type)` describes one parameter — its name and the
Hayo type name. Built-in types are written as-is (`'Int'`, `'Str'`, `'Bool'`, `'Real'`,
`'List'`, `'Dict'`, `'DateTime'`); a **custom type is written fully qualified**
(`'Wallet.Money'`) — the library knows its namespace, so it assembles the name itself
(typically from `getNamespace()`). The same holds for a type from **another** library.

### Values: `FinalValue`

Values flow through the engine wrapped in `FinalValue`:

- `new FinalValue($value, string $type)` — a value with its type name (scalar/array/object).
- `$v->getValue()` — the raw PHP value.
- `$v->unpack()` — the PHP value "unwrapped" into scalars/arrays (for output).
- `$v->type()` — the Hayo type name.
- `FinalValue::composite(array $fields, string $type)` — builds a **composite (product)
  value**: the payload is a list of fields (each a `FinalValue`). See types below.
- `$v->fields()` — reads the fields of a composite value (list of `FinalValue`).

In `apply()` the arguments arrive as `FinalValue` and you return the result as a
`FinalValue` (which is a `Value`) too.

### Minimal library example

```php
use Taco\Hayo\{FuncProvider, BuildinFunc, BindValue, FinalValue, Value};

class GreetProvider implements FuncProvider
{
    function getNamespace(): string { return 'Greet'; }

    function lookupFunc(string $symbol): ?BuildinFunc
    {
        return $symbol === 'hello' ? new GreetFunc() : null;
    }
}

class GreetFunc implements BuildinFunc
{
    function type(): string { return 'Str'; }

    /** @return list<BindValue> */
    function getBinds(): array
    {
        return [new BindValue('name', 'Str')];
    }

    /** @param array<string, FinalValue> $args */
    function apply(array $args): Value
    {
        $name = array_values($args)[0]->unpack();
        return new FinalValue("Hello, {$name}!", 'Str');
    }

    function __toString(): string { return '<Greet.hello>'; }
}
```

```php
$engine = HayoEngine::WithDefaultLibraries()
    ->registerLibrary(new GreetProvider());

$engine->evaluate('Greet.hello "world"'); // "Hello, world!"
```

### Symbols without a prefix — `ShortSymbolProvider`

If you want some symbols usable without a namespace (as `Math` does with `+`, `-`,
`mod`), have the provider additionally implement `ShortSymbolProvider` and return the
list of those symbols:

```php
class MathsProvider implements FuncProvider, ShortSymbolProvider
{
    function getNamespace(): string { return 'Math'; }

    function lookupFunc(string $symbol): ?BuildinFunc { /* … */ }

    /** @return list<string> */
    function getShortSymbolTable(): array
    {
        return ['+', '-', '*', 'div', 'mod'];
    }
}
```



## 2. Registering custom types

A library provides types like functions — as objects. Implement `TypeProvider`:

```php
interface TypeProvider extends LibraryProvider
{
    // Returns the type as an object by its local name (without namespace), or null.
    function lookupType(string $name): ?TypeDef;

    // Local names of all types the library provides (for type inference).
    // @return list<string>
    function getProvidedTypeNames(): array;
}
```

`TypeDef` is just a marker — the type name is known to the caller from the
`lookupType($name)` it requested. The structure is supplied by one of the
sub-interfaces depending on the kind of type; for a product type (e.g. `Money`) it is
`ProductTypeDef` with fields:

```php
interface TypeDef
{
    // empty marker — Product or Sum
}

interface ProductTypeDef extends TypeDef
{
    /** @return list<string> */
    function getFieldTypes(): array;   // ["Int", "Str"]
}
```

`lookupType()` returns a `TypeDef`; `value()` builds a value from it — a product value
(`ProductTypeDef`) or a sum-type variant (`SumTypeDef`), see below and section 5.

**Types are namespace-prefixed.** Externally — in `Introspect.of`, in the `value()`
argument, and in the runtime type of a value — the type is fully qualified
(`Wallet.Money`):

- for values built from PHP via `value()` the full name is supplied by the engine (the
  caller passes it as the argument),
- for values built by functions in a script the full name is supplied by the
  **functions themselves** (they know their namespace from `getNamespace()` — see
  `MoneyFunc` below).

### Values of a type

A type has no constructors — product-type values are created by the library's
**functions** (see `fromAmount` below) or from PHP via `value()`. The representation is
`FinalValue::composite($fields, $type)`; fields are read with `$v->fields()`. No special
value class is needed.

### Building a value from PHP — `value()`

```php
function value(array $values, string $type, ?string $variant = null): FinalValue
```

`value()` takes a fully-qualified type name and a list of values; it checks both the
count and the field types. For a **product** type `$variant` is omitted:

```php
$money = $engine->value([9900, 'CZK'], 'Wallet.Money');
$engine->evaluate('Wallet.format src', ['src' => $money]); // "9900 CZK"
```

For a **sum** type the variant is chosen with the third argument (its arguments are
validated); the result is a value carrying the variant (`SumTypeValue`):

```php
// type Shape = Circle Real | Rectangle Real Real | Point  (provided by the Geo library)
$circle = $engine->value([3.14], 'Geo.Shape', 'Circle');
$engine->evaluate('Introspect.of src', ['src' => $circle]); // "Geo.Shape"
```

Because `value()` knows the full type name, the value is fully usable in `match` too
(variant discrimination, payload binding, exhaustiveness):

```hayo
match src
case Geo.Shape.Circle r then r * r * 3.14159
case Geo.Shape.Rectangle w h then w * h
case Geo.Shape.Point then 0.0
```

Passing `$variant` for a product type — or omitting it for a sum type — is an error.
Passing an object of an unknown type (the engine cannot recognize it) raises
`ArgumentsException` (`Invalid type of value`).



## 3. Complete example — type `Money` in library `Wallet`

A fully working pattern (see `tests/CustomTypeMoneyTest.php`). The `Wallet` library
provides the type `Money` and three functions: `fromAmount`, `add`, `format`.

### Library (functions + types)

```php
use Taco\Hayo\{FuncProvider, TypeProvider, TypeDef, ProductTypeDef, BuildinFunc};

class WalletLibrary implements FuncProvider, TypeProvider
{
    const Ns   = 'Wallet';
    const Type = 'Money';

    function getNamespace(): string { return self::Ns; }

    // --- functions: receive the namespace so they can prefix their own type ---
    function lookupFunc(string $symbol): ?BuildinFunc
    {
        switch ($symbol) {
            case 'fromAmount':
            case 'add':
            case 'format':
                return new MoneyFunc(self::Ns, $symbol);
            default:
                return null;
        }
    }

    // --- types ---
    function lookupType(string $name): ?TypeDef
    {
        return $name === self::Type ? new MoneyTypeDef() : null;
    }

    /** @return list<string> */
    function getProvidedTypeNames(): array { return [self::Type]; }
}
```

### Type

```php
class MoneyTypeDef implements ProductTypeDef
{
    /** @return list<string> */
    function getFieldTypes(): array { return ['Int', 'Str']; }
}
```

### Functions

```php
use Taco\Hayo\{BuildinFunc, BindValue, FinalValue, Value, TypeValidator};
use InvalidArgumentException;

class MoneyFunc implements BuildinFunc
{
    private string $ns;
    private string $name;

    function __construct(string $ns, string $name)
    {
        $this->ns = $ns;
        $this->name = $name;
    }

    // Fully-qualified name of the custom type — the library knows its namespace.
    private function money(): string { return $this->ns . '.' . WalletLibrary::Type; }

    function type(): string
    {
        switch ($this->name) {
            case 'fromAmount':
            case 'add':    return $this->money();   // "Wallet.Money"
            case 'format': return 'Str';
            default:       throw new InvalidArgumentException($this->name);
        }
    }

    /** @return list<BindValue> */
    function getBinds(): array
    {
        switch ($this->name) {
            case 'fromAmount':
                return [new BindValue('amount', 'Int'), new BindValue('currency', 'Str')];
            case 'add':
                return [new BindValue('a', $this->money()), new BindValue('b', $this->money())];
            case 'format':
                return [new BindValue('src', $this->money())];
            default:
                throw new InvalidArgumentException($this->name);
        }
    }

    /** @param array<string, FinalValue> $args */
    function apply(array $args): Value
    {
        $a = array_values($args);
        switch ($this->name) {
            case 'fromAmount':
                TypeValidator::assertInt($a[0]->unpack());
                TypeValidator::assertStr($a[1]->unpack());
                return $this->make($a[0]->unpack(), $a[1]->unpack());

            case 'add':
                [$amountA, $currency] = self::fields($a[0]);
                [$amountB, ] = self::fields($a[1]);
                return $this->make($amountA + $amountB, $currency);

            case 'format':
                [$amount, $currency] = self::fields($a[0]);
                return new FinalValue("{$amount} {$currency}", 'Str');

            default:
                throw new InvalidArgumentException($this->name);
        }
    }

    // A Money value = FinalValue::composite (the shape lives in FinalValue, not here).
    private function make(int $amount, string $currency): FinalValue
    {
        return FinalValue::composite([
            new FinalValue($amount, 'Int'),
            new FinalValue($currency, 'Str'),
        ], $this->money());
    }

    /** @return array{0: int, 1: string} */
    private static function fields(FinalValue $src): array
    {
        $f = $src->fields();
        return [$f[0]->unpack(), $f[1]->unpack()];
    }

    function __toString(): string { return '<' . $this->ns . '.' . $this->name . '>'; }
}
```

### Usage

```php
$engine = HayoEngine::WithDefaultLibraries()
    ->registerLibrary(new WalletLibrary());

// Creating and formatting inside a script
$engine->evaluate('Wallet.format (Wallet.fromAmount 9900 "CZK")'); // "9900 CZK"

// Local variables
$engine->evaluate(
'price = Wallet.fromAmount 9900 "CZK"
tax   = Wallet.fromAmount 2079 "CZK"
Wallet.format (Wallet.add price tax)'); // "11979 CZK"

// Passing a value from PHP — value() takes the fully-qualified type name
$base = $engine->value([9900, 'CZK'], 'Wallet.Money');
$engine->evaluate('Wallet.format src', ['src' => $base]); // "9900 CZK"

// Higher-order functions
$engine->evaluate('List.map prices (p -> Wallet.format p)', [
    'prices' => [
        $engine->value([100, 'CZK'], 'Wallet.Money'),
        $engine->value([200, 'CZK'], 'Wallet.Money'),
    ],
]); // ["100 CZK", "200 CZK"]

// Introspection — the type is fully qualified
$engine->evaluate('Introspect.of src', ['src' => $base]);            // "Wallet.Money"
$engine->evaluate('Introspect.is src "Wallet.Money"', ['src' => $base]); // true
```

> **Sharing types across libraries:** because the namespace is fixed, a function in
> another library (e.g. `Report`) may accept `Wallet.Money` — just write the full name
> `'Wallet.Money'` in its `getBinds()`.



## 4. Two ways to declare a signature

A signature (argument types and return type) can be described in two ways:

### a) Explicitly via `getBinds()` + `type()`

As shown above in `MoneyFunc` — you return the list of `BindValue`s and the return type
name by hand. Straightforward, suitable for custom extensions.

### b) The `@signature` annotation (like the built-in functions)

The built-in libraries (`MathsProvider` etc.) derive the signature from the PHP method
and the `@signature` annotation, processed by helpers in `Utils`:

```php
/**
 * Addition
 * @signature "a: Num, b: Num -> Num"
 */
private static function applyPlus(FinalValue $a, FinalValue $b): FinalValue
{
    $sum = $a->unpack() + $b->unpack();
    return new FinalValue($sum, is_int($sum) ? 'Int' : 'Real');
}
```

`getBinds()` and `type()` then delegate to `Utils::selectArgumentsSignature()` and
`Utils::selectReturnType()`. This approach is mainly handy inside the engine; for
ordinary extensions option (a) is enough.



## 5. Sum types

The second `TypeDef` sub-interface is `SumTypeDef` — a type as a discriminated union of
variants. The compiler then checks the exhaustiveness of `match` expressions (every
variant must be covered, or a wildcard `_` present).

```php
interface SumTypeDef extends TypeDef
{
    function getTypeName(): string;                      // "Color"
    /** @return list<string> */
    function getVariantNames(): array;                   // ["Red", "Green", "Blue"]
    /** @return list<string> */
    function getTypeParams(): array;                     // [] for monomorphic, ["a"] for Maybe<a>
    /** @return list<string> */
    function getVariantArgTypes(string $variant): array; // arguments of one variant
}
```

Sum types declared **directly in a script** (`type Color = Red | Green | Blue`) are
internally registered as `SumTypeProvider` instances and have their own variant
constructors (`Color.Red`). These types and the built-in `Bool` are addressed under a
name equal to their namespace (`Color`, `Bool`) — unlike types from `TypeProvider`,
which are prefixed by the library namespace. (`getTypeName()` serves here as the
registration name; for a type returned from `lookupType()` it is not read.)

`TypeProvider::lookupType()` may also return a `SumTypeDef` — the compiler collects it
for inference and `match` checking just like script types. Values of such a type are
created from PHP via `value($fields, $type, $variant)` (see section 2) and are fully
usable in `match`. 

 For `match` syntax details see **[Language syntax](syntax.md)**.
