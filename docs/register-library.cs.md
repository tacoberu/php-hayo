# Hayo — Registrace vlastních funkcí a typů

Viz také: **[Syntaxe jazyka](syntax.cs.md)** · **[Vestavěné funkce](libs.cs.md)**

Hayo lze ze strany PHP rozšířit o vlastní funkce i vlastní datové typy, a to bez
zásahu do enginu. Knihovna se zaregistruje metodou `registerLibrary()`:

```php
use Taco\Hayo\HayoEngine;

$engine = HayoEngine::WithDefaultLibraries()
    ->registerLibrary(new WalletLibrary());

$engine->evaluate('Wallet.fromAmount 9900 "CZK"');
```

`registerLibrary(LibraryProvider $lib)` zaregistruje knihovnu pod jmenným prostorem,
který si **knihovna sama deklaruje** metodou `getNamespace()` (např. `Wallet` →
`Wallet.fromAmount`, typ `Wallet.Money`). Jedna knihovna může poskytovat **funkce
i typy zároveň** a **více typů**.




## Přehled rozhraní

| Rozhraní / třída | Role |
|---|---|
| `LibraryProvider` | společný základ — `getNamespace()` (jmenný prostor knihovny) |
| `FuncProvider` | knihovna funkcí — `lookupFunc($symbol)` vrací `BuildinFunc`, nebo `null` |
| `BuildinFunc` | jedna funkce (signatura + výpočet) |
| `BindValue` | popis jednoho argumentu funkce (jméno + typ) |
| `FinalValue` | obálka konkrétní hodnoty (hodnota + název typu) |
| `TypeProvider` | knihovna typů — `lookupType($name)` + `getProvidedTypeNames()`; umí **víc typů** |
| `TypeDef` | typ jako objekt — prázdný marker; strukturu dodá pod-rozhraní |
| `ProductTypeDef` | `TypeDef` produktového typu — pole (`getFieldTypes()`) |
| `SumTypeDef` | `TypeDef` výčtového (sum) typu — jméno + varianty, kontrola úplnosti `match` |
| `ShortSymbolProvider` | volitelně: symboly použitelné bez prefixu (jako `+`, `mod`) |

`FuncProvider` i `TypeProvider` rozšiřují `LibraryProvider`, takže každá knihovna má
`getNamespace()`. Vše je v namespace `Taco\Hayo`.



## 1. Registrace vlastních funkcí

Funkce se sdružují do knihovny — třídy implementující `FuncProvider`. Ta musí umět
vrátit svůj namespace a podle názvu symbolu příslušnou funkci (nebo `null`):

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

`lookupFunc()` dostává **jen holé jméno funkce bez namespace**. Pro `Wallet.fromAmount`
přijde `lookupFunc('fromAmount')`, pro short-symbol `+` (viz `ShortSymbolProvider`)
přijde `lookupFunc('+')`. Engine předpokládá v symbolu právě jednu tečku; namespace
se nezanořuje.

Samotnou funkci představuje `BuildinFunc`:

```php
interface BuildinFunc extends Applicable
{
    // Návratový typ (z rozhraní Value) — pro typovou inferenci.
    function type(): string;

    // Jaké argumenty funkce vyžaduje (pro typovou kontrolu i inferenci).
    // @return list<BindValue>
    function getBinds(): array;

    // Přijme argumenty (už jako FinalValue) a spočítá výsledek.
    // @param array<string, FinalValue> $args
    function apply(array $args): Value;

    // Krátká reprezentace do chybových hlášek (z rozhraní Value).
    function __toString(): string;
}
```

> Funkce **nemá** žádné „qualified name" — jméno do chybových hlášek bere engine
> z místa volání ve skriptu.

### Argumenty: `BindValue`

`new BindValue(string $name, string $type)` popisuje jeden parametr — jeho jméno
a název Hayo typu. Built-in typy se uvádějí jak jsou (`'Int'`, `'Str'`, `'Bool'`,
`'Real'`, `'List'`, `'Dict'`, `'DateTime'`); **vlastní typ se uvádí plně
kvalifikovaný** (`'Wallet.Money'`) — knihovna svůj namespace zná, takže si ho
poskládá sama (typicky z `getNamespace()`). Totéž platí pro typ z **jiné** knihovny.

### Hodnoty: `FinalValue`

Hodnoty proudí enginem zabalené ve `FinalValue`:

- `new FinalValue($value, string $type)` — hodnota se jménem typu (skalár/pole/objekt).
- `$v->getValue()` — surová PHP hodnota.
- `$v->unpack()` — PHP hodnota „rozbalená" do skalárů/polí (pro výstup ven).
- `$v->type()` — název Hayo typu.
- `FinalValue::composite(array $fields, string $type)` — vyrobí **kompozitní
  (produktovou) hodnotu**: payload je list polí (každé `FinalValue`). Viz typy níže.
- `$v->fields()` — přečte pole kompozitní hodnoty (list `FinalValue`).

V `apply()` argumenty přijdou jako `FinalValue` a výsledek vracíte také jako
`FinalValue` (které je `Value`).

### Minimální příklad knihovny

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
        return new FinalValue("Ahoj, {$name}!", 'Str');
    }

    function __toString(): string { return '<Greet.hello>'; }
}
```

```php
$engine = HayoEngine::WithDefaultLibraries()
    ->registerLibrary(new GreetProvider());

$engine->evaluate('Greet.hello "světe"'); // "Ahoj, světe!"
```

### Symboly bez prefixu — `ShortSymbolProvider`

Chcete-li, aby šly některé symboly použít bez jmenného prostoru (jak to dělá
`Math` s `+`, `-`, `mod`), nechte provider navíc implementovat
`ShortSymbolProvider` a vraťte seznam těchto symbolů:

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



## 2. Registrace vlastních typů

Typy poskytuje knihovna stejně jako funkce — jako objekty. Implementujte
`TypeProvider`:

```php
interface TypeProvider extends LibraryProvider
{
    // Vrátí typ jako objekt podle lokálního jména (bez namespace), nebo null.
    function lookupType(string $name): ?TypeDef;

    // Lokální jména všech typů, které knihovna poskytuje (pro typovou inferenci).
    // @return list<string>
    function getProvidedTypeNames(): array;
}
```

`TypeDef` je jen marker — jméno typu zná volající z `lookupType($name)`, který si
vyžádal. Strukturu dodá jedno z pod-rozhraní podle druhu typu; pro produktový typ
(např. `Money`) je to `ProductTypeDef` s poli:

```php
interface TypeDef
{
    // prázdný marker — Product nebo Sum
}

interface ProductTypeDef extends TypeDef
{
    /** @return list<string> */
    function getFieldTypes(): array;   // ["Int", "Str"]
}
```

`lookupType()` vrací `TypeDef`; `value()` z něj staví hodnotu — produktovou
(`ProductTypeDef`), nebo variantu sum typu (`SumTypeDef`), viz dále a sekce 5.

**Typ je prefixován namespacem.** Navenek — v `Introspect.of`, v argumentu `value()`
i v runtime typu hodnoty — je typ plně kvalifikovaný (`Wallet.Money`):

- hodnotám vyrobeným z PHP přes `value()` doplní plné jméno engine (volající ho
  předá jako argument),
- hodnotám vyrobeným funkcemi ve skriptu doplní plné jméno **funkce samy** (znají
  svůj namespace z `getNamespace()` — viz `MoneyFunc` níže).

### Hodnoty typu

Typ nemá konstruktory — hodnoty produktového typu se tvoří **funkcemi** knihovny
(viz `fromAmount` níže) nebo z PHP přes `value()`. Reprezentace je
`FinalValue::composite($fields, $type)`; pole se čtou `$v->fields()`. Žádná
zvláštní hodnotová třída není potřeba.

### Tvorba hodnoty z PHP — `value()`

```php
function value(array $values, string $type, ?string $variant = null): FinalValue
```

`value()` bere plně kvalifikované jméno typu a pole hodnot; ověří počet i typy polí.
Pro **produktový** typ se `$variant` neuvádí:

```php
$money = $engine->value([9900, 'CZK'], 'Wallet.Money');
$engine->evaluate('Wallet.format src', ['src' => $money]); // "9900 CZK"
```

Pro **sum** typ se zvolí varianta třetím argumentem (její argumenty se zvalidují);
výsledkem je hodnota nesoucí variantu (`SumTypeValue`):

```php
// type Shape = Circle Real | Rectangle Real Real | Point  (poskytnuté knihovnou Geo)
$circle = $engine->value([3.14], 'Geo.Shape', 'Circle');
$engine->evaluate('Introspect.of src', ['src' => $circle]); // "Geo.Shape"
```

Protože `value()` zná plné jméno typu, je hodnota plnohodnotná i v `match`
(rozlišení variant, binding payloadu, kontrola úplnosti):

```hayo
match src
	case Geo.Shape.Circle r then r * r * 3.14159
	case Geo.Shape.Rectangle w h then w * h
	case Geo.Shape.Point then 0.0
```

`$variant` u produktového typu — nebo jeho vynechání u sum typu — je chyba.
Předání objektu neznámého typu (engine ho nerozpozná) skončí výjimkou
`ArgumentsException` (`Invalid type of value`).



## 3. Kompletní příklad — typ `Money` v knihovně `Wallet`

Plně funkční vzor (viz `tests/CustomTypeMoneyTest.php`). Knihovna `Wallet`
poskytuje typ `Money` a tři funkce: `fromAmount`, `add`, `format`.

### Knihovna (funkce + typy)

```php
use Taco\Hayo\{FuncProvider, TypeProvider, TypeDef, ProductTypeDef, BuildinFunc};

class WalletLibrary implements FuncProvider, TypeProvider
{
    const Ns   = 'Wallet';
    const Type = 'Money';

    function getNamespace(): string { return self::Ns; }

    // --- funkce: dostanou namespace, ať si prefixují vlastní typ ---
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

    // --- typy ---
    function lookupType(string $name): ?TypeDef
    {
        return $name === self::Type ? new MoneyTypeDef() : null;
    }

    /** @return list<string> */
    function getProvidedTypeNames(): array { return [self::Type]; }
}
```

### Typ

```php
class MoneyTypeDef implements ProductTypeDef
{
    /** @return list<string> */
    function getFieldTypes(): array { return ['Int', 'Str']; }
}
```

### Funkce

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

    // Plně kvalifikované jméno vlastního typu — knihovna zná svůj namespace.
    private function money(): string { return $this->ns . '.' . WalletLibrary::Type; }

    function type(): string
    {
        switch ($this->name) {
            case 'fromAmount':
            case 'add':
                return $this->money();   // "Wallet.Money"
            case 'format':
                return 'Str';
            default:
                throw new InvalidArgumentException($this->name);
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

    // Hodnota typu Money = FinalValue::composite (tvar drží FinalValue, ne knihovna).
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

### Použití

```php
$engine = HayoEngine::WithDefaultLibraries()
    ->registerLibrary(new WalletLibrary());

// Vytvoření a formátování uvnitř skriptu
$engine->evaluate('Wallet.format (Wallet.fromAmount 9900 "CZK")'); // "9900 CZK"

// Lokální proměnné
$engine->evaluate(
'price = Wallet.fromAmount 9900 "CZK"
tax   = Wallet.fromAmount 2079 "CZK"
Wallet.format (Wallet.add price tax)'); // "11979 CZK"

// Předání hodnoty z PHP — value() bere plně kvalifikované jméno typu
$base = $engine->value([9900, 'CZK'], 'Wallet.Money');
$engine->evaluate('Wallet.format src', ['src' => $base]); // "9900 CZK"

// Vyšší funkce
$engine->evaluate('List.map prices (p -> Wallet.format p)', [
    'prices' => [
        $engine->value([100, 'CZK'], 'Wallet.Money'),
        $engine->value([200, 'CZK'], 'Wallet.Money'),
    ],
]); // ["100 CZK", "200 CZK"]

// Introspekce — typ je plně kvalifikovaný
$engine->evaluate('Introspect.of src', ['src' => $base]);            // "Wallet.Money"
$engine->evaluate('Introspect.is src "Wallet.Money"', ['src' => $base]); // true
```

> **Sdílení typů mezi knihovnami:** protože je namespace fixní, smí funkce v jiné
> knihovně (např. `Report`) přijmout `Wallet.Money` — stačí v `getBinds()` uvést
> plné jméno `'Wallet.Money'`.



## 4. Dvě varianty deklarace signatury

Signaturu (typy argumentů a návratový typ) lze popsat dvěma způsoby:

### a) Explicitně přes `getBinds()` + `type()`

Tak, jak je vidět výše u `MoneyFunc` — ručně vracíte seznam `BindValue` a název
návratového typu. Přímočaré, vhodné pro vlastní rozšíření.

### b) Anotací `@signature` (jako vestavěné funkce)

Vestavěné knihovny (`MathsProvider` aj.) odvozují signaturu z PHP metody a anotace
`@signature`, kterou zpracují pomocné funkce z `Utils`:

```php
/**
 * Sečtení
 * @signature "a: Num, b: Num -> Num"
 */
private static function applyPlus(FinalValue $a, FinalValue $b): FinalValue
{
    $sum = $a->unpack() + $b->unpack();
    return new FinalValue($sum, is_int($sum) ? 'Int' : 'Real');
}
```

`getBinds()` a `type()` pak delegují na `Utils::selectArgumentsSignature()`
a `Utils::selectReturnType()`. Tento přístup je vhodný hlavně uvnitř enginu;
pro běžná rozšíření postačí varianta (a).



## 5. Výčtové (sum) typy

Druhé pod-rozhraní `TypeDef` je `SumTypeDef` — typ jako diskriminovaná unie
variant. Překladač pak kontroluje úplnost výrazů `match` (musí být pokryté všechny
varianty, nebo přítomný zástupný vzor `_`).

```php
interface SumTypeDef extends TypeDef
{
    function getTypeName(): string;                      // "Color"
    /** @return list<string> */
    function getVariantNames(): array;                   // ["Red", "Green", "Blue"]
    /** @return list<string> */
    function getTypeParams(): array;                     // [] u monomorfního, ["a"] u Maybe<a>
    /** @return list<string> */
    function getVariantArgTypes(string $variant): array; // argumenty jedné varianty
}
```

Výčtové typy zavedené **přímo ve skriptu** (`type Color = Red | Green | Blue`) jsou
interně registrovány jako instance `SumTypeProvider` a mají vlastní konstruktory
variant (`Color.Red`). Tyto typy a built-in `Bool` se adresují pod jménem rovným
namespace (`Color`, `Bool`) — na rozdíl od typů z `TypeProvider`, které jsou
prefixované namespacem knihovny. (`getTypeName()` slouží právě tady jako registrační
jméno; u typu z `lookupType()` se nečte.)

`TypeProvider::lookupType()` smí `SumTypeDef` vrátit také — překladač ho posbírá
pro inferenci a kontrolu `match` stejně jako script typy. Hodnoty takového typu se
tvoří buď z PHP přes `value($fields, $type, $variant)` (viz sekce 2), nebo přímo
**ve skriptu konstruktorem** plně kvalifikovaným jménem `Ns.Type.Variant`:

```hayo
shape = Geo.Shape.Rectangle 10.0 5.0
match shape
	case Geo.Shape.Circle r then r * r * 3.14159
	case Geo.Shape.Rectangle w h then w * h
	case Geo.Shape.Point then 0.0
```

V obou případech nese výsledná hodnota plně kvalifikované jméno typu (`Geo.Shape`)
— prefix namespace knihovny — takže je v `match` i `Introspect.of` plnohodnotná.


Podrobnosti k syntaxi `match` viz **[Syntaxe jazyka](syntax.cs.md)**.
