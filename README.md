php-hayo
========

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-8892bf.svg)](https://php.net)

Hayo is a lightweight, purely functional scripting language / runtime implemented in PHP. It is designed for interpreting user-defined logic (conditions, transformations, business rules) from scripts that you may want to store (for example) in a database and keep user-editable. You pass a script as a string, compile it into a function, and then call that function with concrete data.

Read the **[language syntax reference](docs/syntax.md)** and the **[built-in function reference](docs/libs.md)**.

---

## 💡 Why Hayo?

- **🛡️ Security by Isolation:** Purely functional, no side-effects. Scripts have zero access to the filesystem, network, or global PHP state. It is a safe way to run user-provided logic.
- **💾 Persistence & Caching:** Compiled, optimized bytecode can be transparently cached. The result is already a fast function.
- **🧠 Expressiveness:** The language supports local variables, lambdas, and pattern matching.
- **🔍 Static Analysis:** Includes type inference and compile-time validation to catch most embarrassing errors.

---

## 🚀 Quick Start

```bash
composer require tacoberu/hayo
```

```php
use Taco\Hayo\HayoEngine;

$engine = HayoEngine::WithDefaultLibraries();

// Simple evaluation
$engine->evaluate("1 + 1"); // 2

// With arguments
$engine->evaluate("a + b", ["a" => 1, "b" => 2]); // 3
```

---

## 💡 Real-world Example: Business Logic

Hayo elegantly handles complex branching and data transformations:

```hayo
-- Calculate total from an array of dictionaries
totalPrice = order.items
    |> List.map (i -> i.price * i.quantity)
    |> List.fold 0 (acc curr -> acc + curr)

-- Condition with branching and pattern matching
if totalPrice > 1000 or order.customer.isVip then
    totalPrice * 0.9 -- 10% discount
else
    totalPrice
```

---

## ⚖ Comparison with Alternatives

The most common alternative for PHP is [Symfony Expression Language](https://packagist.org/packages/symfony/expression-language). Below is how they compare:

| Feature | Hayo | Symfony Expression Language |
|---|---|---|
| Custom functions | ✅ | ✅ |
| Local variables in script | ✅ | ⚠️ injection only |
| Lambdas / Closures | ✅ | ❌ |
| Map / fold / filter | ✅ | ⚠️ via extensions |
| Branching (if-else) | ✅ | ⚠️ ternary only |
| Pattern Matching (match) | ✅ | ❌ |
| Type Inference | ✅ | ❌ |

---

## 🏗 Usage & Integration

### Bytecode caching

For repeated execution, use a cache adapter to avoid re-compiling the script:
```php
$engine->setCache(new MyCacheAdapter())
    ->evaluate("1 + a", ["a" => 1]);
```

### Custom function libraries
You can extend Hayo with your own PHP-defined functions.
```php
$engine->registerLibrary("MyStrings", new MyStringsProvider())
    ->evaluate('MyStrings.format("result: ${0}", [1 + a])', ["a" => 1]);
```

### Local functions and lambdas
You can define functions within your script:
```php
HayoEngine::WithDefaultLibraries()
    ->evaluate("
inc = x -> x + 1
inc counter
    ", ["counter" => 41]); // 42
```

### Higher-order functions — map, fold, and more
```php
HayoEngine::WithDefaultLibraries()
    ->evaluate("List.map xs (x -> x * x)", ["xs" => [1, 2, 3]]); // [1, 4, 9]
```

### Pipe chains operator
```php
HayoEngine::WithDefaultLibraries()
    ->evaluate("
xs
    |> List.map (x -> x * x)
    |> List.fold 0 (prev curr -> prev + curr)
    ", ["xs" => [1, 2, 3, 4]]); // 30
```

---

## ⚠️ Exceptions

The engine distinguishes between three phases where an error can occur:

### Compilation Errors
Occur during `evaluate()` or `compile()`.

- `CompileException`: Syntax error in the script — invalid source code, unrecognized token, incomplete expression, or unresolved expression.
- `SymbolNotFound`: Script refers to a symbol, built-in, or library function that is not available in the runtime (not registered or a typo in the name).

### Argument Errors
Occur when calling a compiled script with concrete data.

- `ArgumentsException`: Script was called with wrong parameters — wrong name, count, or value type.

### Runtime Errors
Occur during script execution.

- `ScriptRuntimeException`: Any error during execution — wrong argument type for a built-in function, division by zero (div, mod), etc. Wraps all Throwables that occurred during evaluation. The original cause is available via getPrevious().

---

## 📐 Built-in Functions

Read the **[complete built-in function reference](docs/libs.md)**.

- **Math:** `+`, `-`, `*`, `div`, `mod`, `Math.ceil`, `Math.floor`, `Math.round`.
- **Strings (Str):** `len`, `split`, `concat`, `format`, `indexOf`, `contains`, `startsWith`, `endsWith`, `sub`, `toUpper`, `toLower`, `trim`.
- **Lists (List):** `len`, `first`, `at`, `exist`, `concat`, `push`, `indexOf`, `slice`, `split`, `map`, `fold`, `filter`, `sort`.
- **Dictionaries (Dict):** `has`, `get`, `merge`, `keys`, `values`.
- **DateTime:** `fromDate`, `fromDateTime`, `fromTimestamp`, `toTimestamp`, `format`.
- **Introspect:** `of`, `is`.

---

## Architecture

- `hayo-ast`: AST node definitions.
- `hayo-parser`: Default recursive descent parser.
- `hayo`: Runtime and compiler for PHP.
