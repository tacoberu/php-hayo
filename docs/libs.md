# Hayo — Built-in Functions

See also: **[Language Syntax](syntax.md)**



## Math (Math / short form without prefix)

| Syntax | Description | Types |
|---|---|---|
| `a + b` | addition | Num |
| `a - b` | subtraction | Num |
| `a * b` | multiplication | Num |
| `a div b` | integer division | Num → Int |
| `a mod b` | remainder | Int |
| `Math.ceil a` | round up | Num → Int |
| `Math.floor a` | round down | Num → Int |
| `Math.round a precision` | mathematical rounding | Num Int → Real |



## Strings (Str)

| Function | Arguments | Result | Description |
|---|---|---|---|
| `Str.len src` | Str | Int | string length |
| `Str.split src sep` | Str Str | List\<Str\> | split string |
| `Str.concat list sep` | List\<Str\> Str | Str | join list of strings |
| `Str.format src dict` | Str Dict\<Str\> | Str | formatting (`${name}`) |
| `Str.indexOf src fragment` | Str Str | Int | position of substring, or -1 |
| `Str.contains src fragment` | Str Str | Bool | whether it contains substring |
| `Str.startsWith src fragment` | Str Str | Bool | starts with the given fragment |
| `Str.endsWith src fragment` | Str Str | Bool | ends with the given fragment |
| `Str.sub src start len` | Str Int Int | Str | substring (Unicode) |
| `Str.toUpper src` | Str | Str | convert to uppercase |
| `Str.toLower src` | Str | Str | convert to lowercase |
| `Str.trim src` | Str | Str | trim whitespace |

The format mask in `Str.format` uses `${name}`:

```
Str.format "Lorem ${a} doler ist." {a: "ipsum"}
```



## Lists (List)

| Function | Arguments | Result | Description |
|---|---|---|---|
| `List.len src` | List\<a\> | Int | number of elements |
| `List.first src default` | List\<a\> a | a | first element, or default |
| `List.at src index default` | List\<a\> Int a | a | element at index (0-based), or default |
| `List.exist src index` | List\<a\> Int | Bool | whether element exists at index |
| `List.push xs x` | List\<a\> a | List\<a\> | append element to end |
| `List.concat xs ys` | List\<a\> List\<a\> | List\<a\> | concatenate two lists |
| `List.slice src start length` | List\<a\> Int Int | List\<a\> | slice |
| `List.indexOf src fn offset` | List\<a\> Callable Int | Int | index of first element matching predicate, or -1 |
| `List.map src fn` | List\<a\> Callable | List\<b\> | transform each element |
| `List.filter src fn` | List\<a\> Callable | List\<a\> | filter by predicate |
| `List.fold src init fn` | List\<a\> b Callable | b | reduce to a single value |
| `List.split src fn limit` | List\<a\> Callable Int | List\<List\<a\>\> | split by predicate |
| `List.sort src fn` | List\<a\> Callable | List\<a\> | sort with comparator |
| `List.sort src "List.Asc"` | List\<a\> | List\<a\> | sort ascending |
| `List.sort src "List.Desc"` | List\<a\> | List\<a\> | sort descending |



## Dictionaries (Dict)

| Function | Arguments | Result | Description |
|---|---|---|---|
| `Dict.has xs key` | Dict Str | Bool | whether key exists |
| `Dict.get xs key default` | Dict Str a | a | value by key, or default |
| `Dict.merge xs ys` | Dict Dict | Dict | merge two dictionaries |
| `Dict.keys xs` | Dict | List\<Str\> | list of keys |
| `Dict.values xs` | Dict | List\<a\> | list of values |



## Introspection (Introspect)

| Function | Arguments | Result | Description |
|---|---|---|---|
| `Introspect.of src` | a | Str | type name of a value as a string |
| `Introspect.is src type` | a Str | Bool | returns True if the value is of the given type |

Returned values of `Introspect.of`: `"Int"`, `"Real"`, `"Str"`, `"Bool"`, `"Null"`, `"List"`, `"Dict"`, `"Tuple"`, `"DateTime"`, or the name of a custom type (e.g. `"Money"`).

```
Introspect.of 42              -- "Int"
Introspect.of "hello"         -- "Str"
Introspect.of src             -- "Money"  (for a custom type)

Introspect.is src "Money"     -- True / False

if (Introspect.of src) == "Money" then "it is money" else "other type"
if Introspect.is src "Int" then "number" else "other type"
```



## Date and Time (DateTime)

| Function | Arguments | Result | Description |
|---|---|---|---|
| `DateTime.fromDate year month day` | Int Int Int | DateTime | construct from date |
| `DateTime.fromDateTime year month day hour minute sec` | Int×6 | DateTime | construct from date and time |
| `DateTime.fromTimestamp src` | Int | DateTime | from Unix timestamp |
| `DateTime.toTimestamp src` | DateTime | Int | to Unix timestamp |
| `DateTime.format mask src` | Str DateTime | Str | formatting (PHP `date()` format) |



## Custom Types and Functions

Hayo can be extended with custom types and functions on the PHP side without modifying
the engine. A library declares its namespace via `getNamespace()` and implements
`FuncProvider` (functions) and/or `TypeProvider` (types). It is registered with
`registerLibrary()`.

```php
class WalletLibrary implements FuncProvider, TypeProvider
{
    function getNamespace(): string { return 'Wallet'; }

    function lookupFunc(string $symbol): ?BuildinFunc
    {
        return match ($symbol) {
            'fromAmount', 'add', 'format' => new MoneyFunc($this->getNamespace(), $symbol),
            default => null,
        };
    }

    function lookupType(string $name): ?TypeDef
    {
        return $name === 'Money' ? new MoneyTypeDef() : null;
    }

    /** @return list<string> */
    function getProvidedTypeNames(): array { return ['Money']; }
}

$engine = HayoEngine::WithDefaultLibraries()
    ->registerLibrary(new WalletLibrary());
```

```
Wallet.fromAmount 9900 "CZK"
Wallet.format (Wallet.add a b)
```

For the full pattern (type, functions, values, sum types) see the
**[library reference](register-library.md)**.



## Examples

```
1 + 1
```

```
price * 1.23
```

```
vat = 1.23
price * vat
```

```
List.map xs (x -> x * x)
```

```
xs
    |> List.map (x -> x * x)
    |> List.fold 0 (prev curr -> prev + curr)
```

```
xs = (Str.split src ",")
{
    street:  (List.first xs "")
    city:    (List.at xs 1 "") |> Str.trim
    country: (List.at xs 2 "") |> Str.trim
}
```
