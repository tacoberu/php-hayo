# Hayo — Language Syntax Reference

Hayo is a purely functional scripting language. A script is passed as a string, compiled
into a function, and called with concrete data. The result is always the last evaluated expression.
No side effects are possible.

See also: **[Built-in Functions](libs.md)**



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

### Booleans and Null

```
True
False
Null
```

`True` and `False` are variants of the built-in sum type `Bool`.
`Null` is a special value representing an absent result.

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



## Sum Types

A sum type is a type with a fixed set of named variants. Each variant may optionally carry payload fields:

```
type Color = Red | Green | Blue
type Shape = Circle Real | Rectangle Real Real | Point
type Expr  = Add Int Int | Neg Int | Lit Int
```

Variants are constructed with `TypeName.VariantName` followed by the payload values:

```
Color.Green
Shape.Circle 5.0
Shape.Rectangle 3.0 4.0
Expr.Add 3 4
```



## Pattern Matching (match)

`match` dispatches on the value of a sum type or a scalar. Each `case` specifies a pattern and the expression to evaluate when that pattern matches.

### Basic syntax

```
match subject
	case Pattern1 then expr1
	case Pattern2 then expr2
```

### Sum type variants

```
type Color = Red | Green | Blue
c = Color.Green

match c
	case Color.Red   then "red"
	case Color.Green then "green"
	case Color.Blue  then "blue"
```

### Binding payload fields

Payload fields are bound to local names directly in the `case` pattern:

```
type Shape = Circle Real | Rectangle Real Real | Point
s = Shape.Rectangle 3.0 4.0

match s
	case Shape.Circle r      then r * r * 3.0
	case Shape.Rectangle w h then w * h
	case Shape.Point         then 0.0
```

### Default section (`else`)

The `else` section catches any variant not handled by an explicit `case`:

```
match c
	case Color.Red then "red"
	else           "other"
```

### Scalar matching

`match` works on plain scalar values too:

```
match n
	case 1 then "one"
	case 2 then "two"
	else   "other"
```

### Multi-pattern (`case P1 | P2 then`)

Multiple patterns can share a single `case` using `|`:

```
match c
	case Color.Red | Color.Green then "warm"
	case Color.Blue              then "cool"
```

```
match n
	case 1 | 2 then "low"
	case 3 | 4 then "high"
	else        "other"
```

### Inline syntax

All cases can be written on one line:

```
match c case Color.Red then 1 case Color.Green then 2 case Color.Blue then 3
```

### match in a let binding

The result of `match` can be assigned to a variable:

```
result = match c
    case Color.Red   then 1
    case Color.Green then 2
    case Color.Blue  then 3
```

### match inside a lambda

```
toInt = c ->  match c
    case Color.Red   then 1
    case Color.Green then 2
    case Color.Blue  then 3

List.map colors (c -> match c
    case Color.Red   then 1
    case Color.Green then 2
    case Color.Blue  then 3)
```

### Exhaustiveness

`match` on a sum type must cover all variants (or include an `else` section).
A non-exhaustive `match` is caught at compile time.



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
inc = x -> x + 1
inc counter
```

```
if score < 50 then "F"
elif score < 70 then "C"
elif score < 90 then "B"
else "A"
```
