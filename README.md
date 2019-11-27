php-hayo
========

Motivace je vytvoření knihovny, která poskytuje runtime na Hayo. Vložíme
zdrojový kod, a on ho přeloží na "funkci". Funkce je objekt, který má všechny
požadované metody API. Objekt je přeložený a rovnou použitelný. Objekt je serializovatelný
do souboru a tím přilinkovatelný.

- reflexe pro informaci o požadovaných závislostech
- fáze pre runtime check
- run
- serializace


## Status
rozpracováno


## TODO
1. Mám k dispozici několik matematických a textových funkcí a jsme schopen vracet základní hodnot, struktury a výrazy. Zádné funkce a žádné typy zatím neřeším.
2. Jsem schopen validovat proti buildin funkcím zda dostávají správně typy.
3. Jsem schopen vynucovat správné typy argumentů.
4. Lokální proměnné.
5. Lokální typy a jejich funkce.
