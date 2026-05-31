# Hayo — popis syntaxe jazyka

Hayo je čistě funkcionální skriptovací jazyk. Skript se předá jako řetězec, zkompiluje se
do funkce a ta se volá s konkrétními daty. Výsledkem je vždy poslední vyhodnocený výraz.
Žádné side-efekty nejsou možné.



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

Formátovací maska ve funkci `Str.format` používá `${jméno}`:

```
Str.format "Lorem ${a} doler ist." {a: "ipsum"}
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
[1, 2, 3, 4]
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
xs = (Str.split src ",")
{
    street: (List.first xs "")
    city:   (List.at xs 1 "") |> Str.trim
    country:(List.at xs 2 "") |> Str.trim
}
```



## Vestavěné funkce

### Matematika (Math / krátká forma bez prefixu)

| Zápis | Popis | Typy |
|---|---|---|
| `a + b` | součet | Int/Real |
| `a - b` | rozdíl | Int/Real |
| `a * b` | součin | Int/Real |
| `a div b` | celočíselné dělení | Int/Real |
| `a mod b` | zbytek po dělení | Int |
| `Math.ceil a` | zaokrouhlení nahoru | Real → Int |
| `Math.floor a` | zaokrouhlení dolů | Real → Int |
| `Math.round a precision` | matematické zaokrouhlení | Real Int → Real |


### Řetězce (Str)

| Funkce | Argumenty | Výsledek | Popis |
|---|---|---|---|
| `Str.len src` | Str | Int | délka řetězce |
| `Str.split src sep` | Str Str | List\<Str\> | rozdělení řetězce |
| `Str.concat list sep` | List\<Str\> Str | Str | spojení seznamu řetězců |
| `Str.format src dict` | Str Dict\<Str\> | Str | formátování (`${jméno}`) |
| `Str.indexOf src fragment` | Str Str | Int | pozice podřetězce, nebo -1 |
| `Str.contains src fragment` | Str Str | Bool | zda obsahuje podřetězec |
| `Str.startsWith src fragment` | Str Str | Bool | začíná daným fragmentem |
| `Str.endsWith src fragment` | Str Str | Bool | končí daným fragmentem |
| `Str.sub src start len` | Str Int Int | Str | podřetězec (Unicode) |
| `Str.toUpper src` | Str | Str | převod na velká písmena |
| `Str.toLower src` | Str | Str | převod na malá písmena |
| `Str.trim src` | Str | Str | oříznutí bílých znaků |


### Seznamy (List)

| Funkce | Argumenty | Výsledek | Popis |
|---|---|---|---|
| `List.len src` | List\<a\> | Int | počet prvků |
| `List.first src default` | List\<a\> a | a | první prvek, nebo výchozí |
| `List.at src index default` | List\<a\> Int a | a | prvek na indexu (0-based), nebo výchozí |
| `List.exist src index` | List\<a\> Int | Bool | zda existuje prvek na indexu |
| `List.push xs x` | List\<a\> a | List\<a\> | přidání prvku na konec |
| `List.concat xs ys` | List\<a\> List\<a\> | List\<a\> | spojení dvou seznamů |
| `List.slice src start length` | List\<a\> Int Int | List\<a\> | výřez |
| `List.indexOf src fn offset` | List\<a\> Callable Int | Int | index prvního prvku splňujícího predikát, nebo -1 |
| `List.map src fn` | List\<a\> Callable | List\<b\> | transformace každého prvku |
| `List.filter src fn` | List\<a\> Callable | List\<a\> | filtrování dle predikátu |
| `List.fold src init fn` | List\<a\> b Callable | b | redukce na jednu hodnotu |
| `List.split src fn limit` | List\<a\> Callable Int | List\<List\<a\>\> | rozdělení dle predikátu |
| `List.sort src fn` | List\<a\> Callable | List\<a\> | řazení komparátorem |
| `List.sort src "List.Asc"` | List\<a\> | List\<a\> | vzestupné řazení |
| `List.sort src "List.Desc"` | List\<a\> | List\<a\> | sestupné řazení |


### Slovníky (Dict)

| Funkce | Argumenty | Výsledek | Popis |
|---|---|---|---|
| `Dict.has xs key` | Dict Str | Bool | zda klíč existuje |
| `Dict.get xs key default` | Dict Str a | a | hodnota dle klíče, nebo výchozí |
| `Dict.merge xs ys` | Dict Dict | Dict | sloučení dvou slovníků |
| `Dict.keys xs` | Dict | List\<Str\> | seznam klíčů |
| `Dict.values xs` | Dict | List\<?\> | seznam hodnot |


### Introspekce (Introspect)

| Funkce | Argumenty | Výsledek | Popis |
|---|---|---|---|
| `Introspect.of src` | ? | Str | název typu hodnoty jako řetězec |
| `Introspect.is src type` | ? Str | Bool | vrátí True, pokud je hodnota daného typu |

Vrácené hodnoty `Introspect.of`: `"Int"`, `"Real"`, `"Str"`, `"Bool"`, `"Null"`, `"List"`, `"Dict"`, `"Tuple"`, `"DateTime"`, nebo název vlastního typu (např. `"Money"`).

```
Introspect.of 42              -- "Int"
Introspect.of "hello"         -- "Str"
Introspect.of src             -- "Money"  (pro vlastní typ)

Introspect.is src "Money"     -- True / False

if (Introspect.of src) == "Money" then "je to peníze" else "jiný typ"
if Introspect.is src "Int" then "číslo" else "jiný typ"
```


### Datum a čas (DateTime)

| Funkce | Argumenty | Výsledek | Popis |
|---|---|---|---|
| `DateTime.fromDate year month day` | Int Int Int | DateTime | sestavení z data |
| `DateTime.fromDateTime year month day hour minute sec` | Int×6 | DateTime | sestavení z data a času |
| `DateTime.fromTimestamp src` | Int | DateTime | z Unix timestampu |
| `DateTime.toTimestamp src` | DateTime | Int | na Unix timestamp |
| `DateTime.format mask src` | Str DateTime | Str | formátování (PHP `date()` formát) |


## Rozšíření: vlastní typy a funkce

Hayo lze rozšířit o vlastní typy a funkce na straně PHP bez nutnosti upravovat engine.
Registrace probíhá přes `registerLibrary()` — jeden příkaz pokryje funkce i typ.

***Comming soon...***






## Příklady

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
