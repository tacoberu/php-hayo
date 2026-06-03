php-hayo
========

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-8892bf.svg)](https://php.net)

Hayo je odlehčený, čistě funkcionální skriptovací jazyk / runtime implementovaný v PHP. Je určen pro interpretaci uživatelské logiky (podmínky, transformace, byznys pravidla) ze skriptů, které chcete mít uložené (například) v databázi a uživatelsky editovatelné. Předáte skript jako řetězec, necháte z něj sestavit funkci a tu pak voláte s konkrétními daty.

Přečtěte si **[popis syntaxe jazyka](docs/syntax.cs.md)** a **[popis vestavěných funkcí](docs/libs.cs.md)**.

---

## 💡 Proč Hayo?

- **🛡️ Bezpečnost izolací:** Čistě funkcionální, bez side-efektů. Skripty nemají přístup k souborovému systému, síti ani globálnímu stavu PHP. Bezpečný způsob spouštění uživatelské logiky.
- **💾 Persistence a cachování:** Zkompilovaný, optimalizovaný bytecode lze transparentně cachovat. Výsledek je už rychlá funkce.
- **🧠 Expresivita:** Jazyk podporuje lokální proměnné, lambdy a pattern matching.
- **🔍 Statická analýza:** Zahrnuje odvozování typů a validaci v době kompilace, což odchytí většinu trapných chyb.

---

## 🚀 Rychlý start

```bash
composer require tacoberu/hayo
```

```php
use Taco\Hayo\HayoEngine;

$engine = HayoEngine::WithDefaultLibraries();

// Jednoduché vyhodnocení
$engine->evaluate("1 + 1"); // 2

// S parametry
$engine->evaluate("a + b", ["a" => 1, "b" => 2]); // 3
```

---

## 💡 Příklad z praxe: Byznys logika

Hayo elegantně zvládá komplexní větvení a transformace dat:

```hayo
-- Výpočet sumy z pole slovníků
totalPrice = order.items
    |> List.map (i -> i.price * i.quantity)
    |> List.fold 0 (acc curr -> acc + curr)

-- Podmínka s větvením a pattern matchingem
if totalPrice > 1000 or order.customer.isVip then
    totalPrice * 0.9 -- 10% sleva
else
    totalPrice
```

---

## ⚖ Porovnání s alternativami

Nejčastější alternativou v PHP je [Symfony Expression Language](https://packagist.org/packages/symfony/expression-language). Níže je srovnání jejich možností:

| Vlastnost | Hayo | Symfony Expression Language |
|---|---|---|
| Vlastní funkce | ✅ | ✅ |
| Lokální proměnné v kódu | ✅ | ⚠️ jen injektáž |
| Lambdy / Uzávěry | ✅ | ❌ |
| Map / fold / filter | ✅ | ⚠️ přes rozšíření |
| Větvení (if-else) | ✅ | ⚠️ jen ternární op. |
| Pattern Matching (match) | ✅ | ❌ |
| Type Inference | ✅ | ❌ |

---

## 🏗 Použití a integrace

### Cachování bytecode

Pro opakované spouštění použijte cache adaptér, nebude třeba znova kompilovat skript:
```php
$engine->setCache(new MyCacheAdapter())
    ->evaluate("1 + a", ["a" => 1]);
```

### Vlastní knihovny funkcí
Hayo můžete rozšířit o vlastní funkce definované v PHP.
```php
$engine->registerLibrary("MyStrings", new MyStringsProvider())
    ->evaluate('MyStrings.format("výsledek: ${0}", [1 + a])', ["a" => 1]);
```

### Lokální funkce a lambdy
Funkce můžete definovat ve vlastním skriptu:
```php
HayoEngine::WithDefaultLibraries()
    ->evaluate("
inc = x -> x + 1
inc counter
    ", ["counter" => 41]); // 42
```

### Funkce vyššího řádu — map, fold a další
```php
HayoEngine::WithDefaultLibraries()
    ->evaluate("List.map xs (x -> x * x)", ["xs" => [1, 2, 3]]); // [1, 4, 9]
```

### Pipe chains operátor
```php
HayoEngine::WithDefaultLibraries()
    ->evaluate("
xs
    |> List.map (x -> x * x)
    |> List.fold 0 (prev curr -> prev + curr)
    ", ["xs" => [1, 2, 3, 4]]); // 30
```

---

## ⚠️ Výjimky

Engine rozlišuje tři fáze, ve kterých může dojít k chybě:

### Chyby při kompilaci
Nastávají při volání `evaluate()` nebo `compile()`.

- `CompileException`: Syntaktická chyba ve skriptu — neplatný zdrojový kód, nerozpoznaný token, neúplný výraz, nevyřešený výraz.
- `SymbolNotFound`: Skript odkazuje na symbol, vestavěnou nebo knihovní funkci, která v runtime není dostupná (není zaregistrovaná nebo je překlep v názvu).

### Chyby při předání parametrů
Nastávají při volání zkompilovaného skriptu s konkrétními daty.

- `ArgumentsException`: Skript byl zavolán se špatnými parametry — špatné jméno, špatný počet, nebo špatný typ hodnoty.

### Chyby při vyhodnocení
Nastávají za běhu skriptu.

- `ScriptRuntimeException`: Jakákoliv chyba za běhu — špatný typ argumentu vestavěné funkce, dělení nulou (div, mod) a podobně. Obaluje všechny Throwable, které nastaly při vyhodnocení. Původní příčina je dostupná přes getPrevious().

---

## 📐 Vestavěné funkce

Přečtěte si **[kompletní popis vestavěných funkcí](docs/libs.cs.md)**.

- **Matematika:** `+`, `-`, `*`, `div`, `mod`, `Math.ceil`, `Math.floor`, `Math.round`.
- **Řetězce (Str):** `len`, `split`, `concat`, `format`, `indexOf`, `contains`, `startsWith`, `endsWith`, `sub`, `toUpper`, `toLower`, `trim`.
- **Seznamy (List):** `len`, `first`, `at`, `exist`, `concat`, `push`, `indexOf`, `slice`, `split`, `map`, `fold`, `filter`, `sort`.
- **Slovníky (Dict):** `has`, `get`, `merge`, `keys`, `values`.
- **DateTime:** `fromDate`, `fromDateTime`, `fromTimestamp`, `toTimestamp`, `format`.
- **Introspekce:** `of`, `is`.

---

## Architektura

- `hayo-ast`: definice AST uzlů.
- `hayo-parser`: výchozí recursive descent parser.
- `hayo`: runtime a kompilátor pro PHP.
