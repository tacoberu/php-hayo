# Hayo — Language Syntax Reference

Hayo is a purely functional scripting language. A script is passed as a string, compiled
into a function, and called with concrete data. The result is always the last evaluated expression.
No side effects are possible.



## Comments

```
-- this is a line comment
```



## Data Types and Literals

### Integers (Int)

```
42
0
-1
```

### Floating-point numbers (Real)

```
3.1415
-3.1415
0.0
```

### Strings (Str)

```
"Hello world"
"Sinead O'Connor"       -- apostrophe inside double quotes
'Sinead O\'Connor'      -- inside single quotes, apostrophe must be escaped
"""Sinead O'Connor"""   -- triple quotes, apostrophe needs no escaping
```

The format mask in `Str.format` uses `${name}`:

```
Str.format "Lorem ${a} doler ist." {a: "ipsum"}
```

### Booleans and Null (Symbol)

```
True
False
Null
```

### Empty tuple (Tuple)

```
()
```



## Variables

Assignments are written as `name = expression` on their own line.
Variables are immutable — once assigned, a value cannot change.

```
vat = 1.23
price * vat
```

A variable can reference another variable:

```
b = a
b + b
```

Chaining is also supported:

```
b = c
c = a
b + b
```



## Expressions and Operators

### Arithmetic

Operators are written infix (between operands):

```
40 + 2
40 + (1 + 1)
2 * 10
10 div 3     -- integer division
10 mod 3     -- remainder
```

Precedence is controlled with parentheses `( )`.

### Comparison

```
a == b
a != b
a < b
a > b
a <= b
a >= b
```

Set operators (case-insensitive keywords):

```
value IN list          -- value is an element of the list
list HAS value         -- list contains the value
listA SUPERSET listB   -- listA contains all elements of listB
listA SUBSET listB     -- listB contains all elements of listA
listA INTERSECTS listB -- both sets share at least one element
```

### Logic

```
a AND b    -- or &&
a OR b     -- or ||
NOT a
```

`AND` and `&&` are aliases; `OR` and `||` are aliases.



## Grouping with Parentheses

Parentheses `( )` change precedence or group function call arguments:

```
(10 + a) + (a + 1)
(Str.len src) + b
```



## Compound Data Structures

### List

Elements separated by commas or newlines, enclosed in `[ ]`:

```
[]
[1, 2, 3]
[1, 2, 3, 4]
["one", "two", "three"]
[
    "one"
    "two"
    "three"
]
```

### Dictionary (Dict)

Key-value pairs `key: value` separated by commas or newlines, enclosed in `{ }`:

```
{}
{a: 42}
{a: 42, b: 555}
{
    a: 42
    b: 555
}
```

Keys are identifiers (no quotes).

### Tuple

Elements in parentheses separated by commas or newlines:

```
()
(1,)
(1, 2, 3)
("one", "two", "three")
(
    "one"
    "two"
    "three"
)
```

A single-element tuple must have a trailing comma — otherwise parentheses are treated as grouping: `(x)` is an expression, `(x,)` is a tuple.



## Dictionary Field Access

Dot notation, chainable to any depth. A non-existent path returns `Null`:

```
x.foo
x.foo.doo
x.foo.nothing    -- returns Null
```



## Function Calls

Functions are called with prefix notation: function name followed by arguments separated by spaces.
Namespaces are indicated with a dot:

```
Str.len src
Str.split src ","
List.map xs (x -> x * x)
List.fold xs 0 (prev curr -> prev + curr)
```

An entire call can be wrapped in parentheses (required when the call result is itself an argument):

```
(Str.len "hi")
(List.first xs "")
(List.at xs 1 "")
```

Operators `+`, `-`, `*`, `div`, `mod` can be used without the `Math.` prefix.



## Lambdas (Anonymous Functions)

Syntax: `arguments -> body`

Single-argument:

```
x -> x + 1
x -> x * x
```

Multi-argument:

```
prev curr -> prev + curr
a b -> a + b
```

Lambda as a variable value (local function):

```
inc = x -> x + 1
inc counter
```

Multi-line body (indented; each line is an assignment or the final expression):

```
inc = x ->
    y = 2
    x + y
inc x
```

Lines in the body can alternatively be separated by a semicolon `;`:

```
inc = x -> y = 1; x + y
```

Nested lambdas:

```
inc = x ->
    y = 2
    sqr = x ->
        x * x
    (sqr x) + (sqr y)
inc x
```

A lambda passed directly as a function argument is enclosed in parentheses:

```
List.map xs (x -> x * x)
List.filter xs (x -> x > 2)
List.fold xs 0 (prev curr -> prev + curr)
List.sort xs (a b -> if a < b then -1 else 1)
```

Lambda arguments must be plain identifiers — `(a b -> ...)` is correct,
`((a b) -> ...)` is an error.



## Conditionals (if-then-elif-then-else)

```
if condition then exprA else exprB

if condition then exprA
elif condition2 then exprB
elif condition3 then exprC
else exprD
```

Branches can be written on a single line or each on its own line:

```
if (List.len xs) < 2 then "A"
elif (List.len xs) < 4 then "B"
elif (List.len xs) < 8 then "C"
else "D"
```



## Pipe Operator `|>`

The value on the left is passed as the first argument to the function on the right:

```
xs
    |> List.map (x -> x * x)
    |> List.fold 0 (prev curr -> prev + curr)
```

Can also be used inline inside an expression:

```
(List.at xs 1 "") |> Str.trim
```



## Script Structure

A script consists of lines. Each line is either:

- an assignment: `name = expression`
- or an expression

The last expression is returned as the script result.

```
vat = 1.23
price * vat
```

```
xs = (Str.split src ",")
{
    street: (List.first xs "")
    city:   (List.at xs 1 "") |> Str.trim
    country:(List.at xs 2 "") |> Str.trim
}
```



## Built-in Functions

### Math (Math / short form without prefix)

| Syntax | Description | Types |
|---|---|---|
| `a + b` | addition | Int/Real |
| `a - b` | subtraction | Int/Real |
| `a * b` | multiplication | Int/Real |
| `a div b` | integer division | Int/Real |
| `a mod b` | remainder | Int |
| `Math.ceil a` | round up | Real → Int |
| `Math.floor a` | round down | Real → Int |
| `Math.round a precision` | mathematical rounding | Real Int → Real |


### Strings (Str)

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


### Lists (List)

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


### Dictionaries (Dict)

| Function | Arguments | Result | Description |
|---|---|---|---|
| `Dict.has xs key` | Dict Str | Bool | whether key exists |
| `Dict.get xs key default` | Dict Str a | a | value by key, or default |
| `Dict.merge xs ys` | Dict Dict | Dict | merge two dictionaries |
| `Dict.keys xs` | Dict | List\<Str\> | list of keys |
| `Dict.values xs` | Dict | List\<?\> | list of values |


### Date and Time (DateTime)

| Function | Arguments | Result | Description |
|---|---|---|---|
| `DateTime.fromDate year month day` | Int Int Int | DateTime | construct from date |
| `DateTime.fromDateTime year month day hour minute sec` | Int×6 | DateTime | construct from date and time |
| `DateTime.fromTimestamp src` | Int | DateTime | from Unix timestamp |
| `DateTime.toTimestamp src` | DateTime | Int | to Unix timestamp |
| `DateTime.format mask src` | Str DateTime | Str | formatting (PHP `date()` format) |



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
inc = x -> x + 1
inc counter
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
if score < 50 then "F"
elif score < 70 then "C"
elif score < 90 then "B"
else "A"
```

```
xs = (Str.split src ",")
{
    street:  (List.first xs "")
    city:    (List.at xs 1 "") |> Str.trim
    country: (List.at xs 2 "") |> Str.trim
}
```
