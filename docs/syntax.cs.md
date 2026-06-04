# Hayo — popis syntaxe jazyka

Hayo je čistě funkcionální skriptovací jazyk. Skript se předá jako řetězec, zkompiluje se
do funkce a ta se volá s konkrétními daty. Výsledkem je vždy poslední vyhodnocený výraz.
Žádné side-efekty nejsou možné.

Viz také: **[Vestavěné funkce](libs.cs.md)**



## Komentáře

```
-- toto je komentář do konce řádku
```



## Datové typy a literály

### Celá čísla (Int)

```
42
0
-1
```

### Desetinná čísla (Real)

```
3.1415
-3.1415
0.0
```

### Řetězce (Str)

```
"Ahoj světe"
"Sinead O'Connor"       -- apostrof uvnitř dvojitých uvozovek
'Sinead O\'Connor'      -- uvnitř jednoduchých uvozovek je třeba escapovat
"""Sinead O'Connor"""   -- trojité uvozovky, apostrof uvnitř bez escapování
```

### Logické hodnoty a Null

```
True
False
Null
```

`True` a `False` jsou varianty vestavěného sum typu `Bool`.
`Null` je speciální hodnota prázdného výsledku.

### Prázdná n-tice (Tuple)

```
()
```



## Proměnné

Přiřazení se píše jako `jméno = výraz` na samostatném řádku.
Proměnné jsou neměnné — jednou přiřazená hodnota se nemění.

```
vat = 1.23
price * vat
```

Proměnná může odkazovat na jinou proměnnou:

```
b = a
b + b
```

I zřetězeně:

```
b = c
c = a
b + b
```



## Výrazy a operátory

### Aritmetika

Operátory se píší infixově (mezi operandy):

```
40 + 2
40 + (1 + 1)
2 * 10
10 div 3     -- celočíselné dělení
10 mod 3     -- zbytek po dělení
```

Priorita se ovlivňuje závorkami `( )`.

### Porovnání

```
a == b
a != b
a < b
a > b
a <= b
a >= b
```

Množinové operátory (case-insensitive klíčová slova):

```
value IN list          -- hodnota je prvkem seznamu
list HAS value         -- seznam obsahuje hodnotu
listA SUPERSET listB   -- listA obsahuje všechny prvky listB
listA SUBSET listB     -- listB obsahuje všechny prvky listA
listA INTERSECTS listB -- obě množiny sdílejí alespoň jeden prvek
```

### Logika

```
a AND b    -- nebo &&
a OR b     -- nebo ||
NOT a
```

`AND` a `&&` jsou aliasy; `OR` a `||` jsou aliasy.



## Seskupování závorkami

Závorky `( )` mění prioritu nebo sdružují argumenty volání funkce:

```
(10 + a) + (a + 1)
(Str.len src) + b
```



## Složené datové struktury

### Seznam (List)

Prvky oddělené čárkami nebo novými řádky, uzavřené do `[ ]`:

```
[]
[1, 2, 3]
["une", "deux", "trois"]
[
    "une"
    "deux"
    "trois"
]
```

### Slovník (Dict)

Dvojice `klíč: hodnota` oddělené čárkami nebo novými řádky, uzavřené do `{ }`:

```
{}
{a: 42}
{a: 42, b: 555}
{
    a: 42
    b: 555
}
```

Klíče jsou identifikátory (bez uvozovek).

### N-tice (Tuple)

Prvky v kulatých závorkách oddělené čárkami nebo novými řádky:

```
()
(1,)
(1, 2, 3)
("une", "deux", "trois")
(
    "une"
    "deux"
    "trois"
)
```

Jednoprvková tuple musí mít čárku za prvkem — jinak se závorka považuje za grupovací: `(x)` je výraz, `(x,)` je tuple.



## Přístup k polím slovníku

Tečková notace, lze zřetězit libovolně hluboko. Neexistující cesta vrací `Null`:

```
x.foo
x.foo.doo
x.foo.nothing    -- vrátí Null
```



## Volání funkcí

Funkce se volá prefixovou notací: jméno funkce, za ním argumenty oddělené mezerami.
Namespace se uvádí tečkou:

```
Str.len src
Str.split src ","
List.map xs (x -> x * x)
List.fold xs 0 (prev curr -> prev + curr)
```

Celé volání lze uzavřít do závorek (nutné tehdy, kdy je výsledek volání sám argumentem):

```
(Str.len "hi")
(List.first xs "")
(List.at xs 1 "")
```

Operátory `+`, `-`, `*`, `div`, `mod` lze používat bez prefixu `Math.`.



## Lambdy (anonymní funkce)

Syntaxe: `argumenty -> tělo`

Jednoargumentová:

```
x -> x + 1
x -> x * x
```

Víceargumentová:

```
prev curr -> prev + curr
a b -> a + b
```

Lambda jako hodnota proměnné (lokální funkce):

```
inc = x -> x + 1
inc counter
```

Víceřádkové tělo (odsazení, každý řádek je přiřazení nebo závěrečný výraz):

```
inc = x ->
    y = 2
    x + y
inc x
```

Alternativně lze řádky v těle oddělit středníkem `;`:

```
inc = x -> y = 1; x + y
```

Vnořené lambdy:

```
inc = x ->
    y = 2
    sqr = x ->
        x * x
    (sqr x) + (sqr y)
inc x
```

Lambda jako přímý argument funkce se uzavírá do závorek:

```
List.map xs (x -> x * x)
List.filter xs (x -> x > 2)
List.fold xs 0 (prev curr -> prev + curr)
List.sort xs (a b -> if a < b then -1 else 1)
```

Argumenty lambdy musí být prosté identifikátory — `(a b -> ...)` je správně,
`((a b) -> ...)` je chyba.



## Součtové typy (Sum Types)

Součtový typ je typ s pevnou sadou pojmenovaných variant. Každá varianta může volitelně nést payload:

```
type Color = Red | Green | Blue
type Shape = Circle Real | Rectangle Real Real | Point
type Expr  = Add Int Int | Neg Int | Lit Int
```

Varianty se konstruují jako `NázevTypu.NázevVarianty` s hodnotami payloadu:

```
Color.Green
Shape.Circle 5.0
Shape.Rectangle 3.0 4.0
Expr.Add 3 4
```



## Pattern Matching (match)

`match` větví podle hodnoty součtového typu nebo skaláru. Každá `case` uvádí vzor a výraz, který se vyhodnotí, pokud vzor odpovídá.

### Základní syntaxe

```
match výraz
	case Vzor1 then výraz1
	case Vzor2 then výraz2
```

### Varianty součtového typu

```
type Color = Red | Green | Blue
c = Color.Green

match c
	case Color.Red   then "red"
	case Color.Green then "green"
	case Color.Blue  then "blue"
```

### Vazba polí payloadu

Pole payloadu se svážou s lokálními jmény přímo ve vzoru `case`:

```
type Shape = Circle Real | Rectangle Real Real | Point
s = Shape.Rectangle 3.0 4.0

match s
	case Shape.Circle r      then r * r * 3.0
	case Shape.Rectangle w h then w * h
	case Shape.Point         then 0.0
```

### Výchozí rameno (`else`)

Sekce `else` zachytí všechny varianty, které nepokrývá žádné explicitní `case`:

```
match c
	case Color.Red then "red"
	else           "other"
```


### Matching skalárních hodnot

`match` funguje i na prostých skalárních hodnotách:

```
match n
	case 1 then "one"
	case 2 then "two"
	else   "other"
```

### Více vzorů najednou (`case P1 | P2 then`)

Více vzorů může sdílet jedno `case` pomocí `|`:

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

### Inline syntaxe

Vše lze zapsat na jeden řádek:

```
match c case Color.Red then 1 case Color.Green then 2 case Color.Blue then 3
```

### match v přiřazení

Výsledek `match` lze přiřadit do proměnné:

```
result = match c
    case Color.Red   then 1
    case Color.Green then 2
    case Color.Blue  then 3
```

### match uvnitř lambdy

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

### Úplnost vzorů

`match` na součtovém typu musí pokrýt všechny varianty (nebo obsahovat sekci `else`).
Neúplný `match` je zachycen při kompilaci.



## Podmínky (if-then-elif-then-else)

```
if podmínka then výrazA else výrazB

if podmínka then výrazA
elif podmínka2 then výrazB
elif podmínka3 then výrazC
else výrazD
```

Větve lze psát na jednom řádku i každou na samostatném:

```
if (List.len xs) < 2 then "A"
elif (List.len xs) < 4 then "B"
elif (List.len xs) < 8 then "C"
else "D"
```



## Pipe operátor `|>`

Hodnota vlevo se předá jako první argument funkce vpravo:

```
xs
    |> List.map (x -> x * x)
    |> List.fold 0 (prev curr -> prev + curr)
```

Lze použít i inline uvnitř výrazu:

```
(List.at xs 1 "") |> Str.trim
```



## Struktura skriptu

Skript se skládá z řádků. Každý řádek je buď:

- přiřazení: `jméno = výraz`
- nebo výraz

Poslední výraz je vrácen jako výsledek skriptu.

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
