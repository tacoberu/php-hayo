php-hayo
========

Lehký skriptovací runtime napsaný v PHP, který umožňuje interpretovat skripty přímo v uživatelském prostoru.
Hodí se všude tam, kde potřebujete uživatelsky definované podmínky, transformace, rutiny, validátory a podobně.

Nejde o sandbox — jde o plnohodnotný runtime jazyka. Předáte skript jako řetězec, necháte z něj sestavit
funkci a tu pak voláte s konkrétními daty.

Proč vznikl vlastní runtime místo existujícího řešení, vysvětluje sekce [Porovnání](#porovnání-s-konkurencí).

**Jazyk je čistě funkcionální.** Můžete přiřazovat do proměnných, volat funkce, přesouvat data — ale
výsledek celého výpočtu dostanete ven pouze tak, že ho na závěr vrátíte. Žádné side-efekty nejsou možné.
Nemůžete v průběhu výpočtu nic logovat. (Průběh si ale můžete zapisovat do proměnné a tento log pak vrátit
jako součást výsledku — jiná cesta neexistuje.)

**Jazyk je staticky typovaný.** 
V části compile se provádí kontrola typů, například aby se nesčítali čísla s textem. Cílem není zajistit typovou neprůstřelnost, jako spíše vyhnout se trapným chybám. Dělení nulou stále padne při běhu.



## Rychlý start
```bash
composer require tacoberu/hayo
```
```php
require __DIR__ . '/vendor/autoload.php';

use Taco\Hayo;

// Prostý výraz
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("1 + 1"); // 2

// Výraz s proměnnou
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("1 + a")
    ->apply(["a" => 1]); // 2
```



## Ukázky použití

**Cachování bytecode**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->setCache(new CacheImpl)
    ->evaluate("1 + a")
    ->apply(["a" => 1]); // 2
```

**Vlastní knihovny funkcí**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->registerLibrary("MyStrings", new MyOwnImplementationOfStringsProvider())
    ->evaluate('MyStrings.format("calculate: {0}", [1 + a])')
    ->apply(["a" => 1]); // "calculate: 2"
```

**Lokální proměnné**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("
vat = 1.23
price * vat
    ")
    ->apply(["price" => 100]); // 123
```

**Lokální funkce a lambdy**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("
inc = x -> x + 1
inc counter
    ")
    ->apply(["counter" => 41]); // 42
```

**Funkce vyššího řádu — map, fold a další**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("
List.map xs (x -> x * x)
    ")
    ->apply(["xs" => [1, 2, 3]]); // [1, 4, 9]
```

**Pipe chains operátor**
```php
Hayo\HayoEngine::WithDefaultLibraries()
    ->evaluate("
xs
	|> List.map (x -> x * x)
	|> List.fold 0 (prev curr -> prev + curr)
    ")
    ->apply(["xs" => [1, 2, 3, 4]]); // 30
```



## Charakteristiky

- Čistě funkcionální jazyk
- Type inference
- Lokální proměnné ve skriptu
- Lokální funkce a lambdy definované přímo ve skriptu
- Výraz if-then-else
- Funkce vyššího řádu: map, fold, filter a další
- Možnost cachování sestaveného bytecode
- Registrace vlastních knihoven funkcí
- Volitelný vlastní parser (při zachování stávajícího AST)



## Vestavěné funkce

### Aritmetika
Základní operátory: `+`, `-`, `*`, `div`, `mod`

Zaokrouhlování:

- `Math.ceil` — zaokrouhlení nahoru
- `Math.floor` — zaokrouhlení dolů
- `Math.round` — matematické zaokrouhlení, na zadanou přesnost


### Logické operátory
`and`, `or`, `not`


### Porovnávací operátory
Základní: `==`, `!=`, `<`, `>`, `<=`, `>=`

Množinové operátory:

- `IN` — hodnota vlevo se nachází v množině napravo
- `HAS` — množina nalevo obsahuje hodnotu napravo
- `SUPERSET` — levá množina obsahuje všechny prvky pravé
- `SUBSET` — pravá množina obsahuje všechny prvky levé
- `INTERSECTS` — obě množiny mají alespoň jeden společný prvek


### Řetězce
- `Str.len` — délka řetězce
- `Str.split` — rozdělení podle separátoru
- `Str.concat` — spojení dvou řetězců
- `Str.format` — formátování podle masky (`{0}`, `{1}`, …)
- `Str.indexOf` — pozice podřetězce, nebo -1
- `Str.contains` — zda řetězec obsahuje podřetězec
- `Str.startsWith` — zda řetězec začíná zadaným fragmentem
- `Str.endsWith` — zda řetězec končí zadaným fragmentem
- `Str.sub` — podřetězec od `index` o délce `len`
- `Str.toUpper` — převod na velká písmena
- `Str.toLower` — převod na malá písmena


### Seznamy (List)
- `List.len` — počet prvků
- `List.first` — první prvek
- `List.at` — prvek na zadaném indexu, nebo výchozí hodnota
- `List.exist` — zda na daném indexu prvek existuje
- `List.concat` — spojení dvou seznamů
- `List.push` — přidání prvku na konec
- `List.indexOf` — index hledané hodnoty (od volitelného offsetu), nebo -1
- `List.slice` — podseznamu od `start` o maximální délce `length`
- `List.split` — rozdělení seznamu podle predikátu na nejvýše `limit` dílů
- `List.map` — aplikace funkce na každý prvek
- `List.fold` — redukce seznamu na jednu hodnotu
- `List.filter` — filtrování prvků podle predikátu
- `List.sort` — řazení: `List.Asc`, `List.Desc`


### Slovníky (Dict)
- `Dict.has` — zda existuje klíč
- `Dict.get` — hodnota podle klíče
- `Dict.merge` — sloučení dvou slovníků
- `Dict.keys` — seznam klíčů
- `Dict.values` — seznam hodnot


### Datum a čas (DateTime)
- `DateTime.toTimestamp` — převod na Unix timestamp
- `DateTime.fromTimestamp` — sestavení hodnoty z timestampu
- `DateTime.fromDate` — sestavení z částí data: `(DateTime.fromDate 2025 1 5)`
- `DateTime.fromDateTime` — sestavení z data i času: `(DateTime.fromDateTime 2025 1 5 12 24 55)`
- `DateTime.format` — formátování podle masky: `DateTime.format "%y. %j. %d" src`



## Výjimky

Runtime rozlišuje tři fáze, ve kterých může dojít k chybě, a pro každou používá jiný typ výjimky.

### Chyby při kompilaci

Nastávají při volání `evaluate()` nebo `compile()`.

| Výjimka | Kdy |
|---|---|
| `CompileException` | Syntaktická chyba ve skriptu — neplatný zdrojový kód, nerozpoznaný token, neúplný výraz, nevyřešený výraz. |
| `SymbolNotFound` | Skript odkazuje na symbol, vestavěnou nebo knihovní funkci, která v runtime není dostupná (není zaregistrovaná nebo je překlep v názvu). |

### Chyby při předání parametrů

Nastávají při volání zkompilovaného skriptu s konkrétními daty.

| Výjimka | Kdy |
|---|---|
| `ArgumentsException` | Skript byl zavolán se špatnými parametry — špatné jméno, špatný počet, nebo špatný typ hodnoty. |

### Chyby při vyhodnocení

Nastávají za běhu, typicky v průběhu volání vestavěných funkcí.

| Výjimka | Kdy |
|---|---|
| `ScriptRuntimeException` | Jakákoliv chyba za běhu — špatný typ argumentu vestavěné funkce, dělení nulou (`div`, `mod`) a podobně. Obaluje všechny `Throwable`, které nastaly při vyhodnocení. Původní příčina je dostupná přes `getPrevious()`. |



## Architektura

Runtime se skládá ze tří balíčků:

- `hayo-ast` — definice AST uzlů
- `hayo-parser` — výchozí parser; pokud vám syntaxe nevyhovuje, napište vlastní parser vracející stejné AST
- `hayo` — samotný runtime pro PHP



## Porovnání s konkurencí

Nejčastějším kandidátem na vestavěný skriptovací jazyk v PHP bývá
[Symfony Expression Language](https://packagist.org/packages/symfony/expression-language).
Níže je přehled, kde se obě řešení liší:

| Vlastnost | Hayo | Symfony Expression Language |
|---|---|---|
| Vlastní funkce (import) | ✅ | ✅ |
| Lokální proměnné v kódu | ✅ | ⚠️ pouze předáním zvenčí, ne definicí uvnitř skriptu |
| Lokální funkce a lambdy v kódu | ✅ | ❌ pouze předáním zvenčí, ne definicí uvnitř skriptu |
| Map / fold / filter | ✅ | ⚠️ jen přes vlastní registrované funkce, ne nativně |
| if-then-elseif-then-else | ✅ | ⚠️ pouze ternární operátor `?:` |
| Vlastní syntaxe / parser | ✅ | ❌ |

Symfony Expression Language je kvalitní a dobře otestovaná knihovna. Pokud vám ale chybí lokální
proměnné, lambdy nebo větvení s více podmínkami, Hayo tyto mezery vyplňuje.
