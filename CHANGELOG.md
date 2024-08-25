# Changelog

## **25-08-2024 1.4.1**

- Fehler in _package.yml_ behoben: `requires` für Redaxo-Packages korrigiert.
- _README.md_: Fehlende Hinweise zur Nutzung von Funktionen, die URLs in den Adminer hinein erzeugen, nachgetragen

## **11.03.2023 1.4.0**

- interne Funktonen für Direkt-Aufrufe in YForm freigegeben ('public' statt 'protected')
  - `YFormAdminer::dbTable(string $tablename, array $where = [])`  
    zeigt die angegebene Tabelle im Adminer. Über den Parameter `where` kann
    die Tabelle gefiltert werden `[['col'=>'spalte','op'=>'operator','val'=>'vergleichswert'],...]`
  - `YFormAdminer::dbSql(string $query)`  
    ruft die Adminer-Seite "SQL-Kommando" mit dem angegebenen SQL-Query-String auf.
    Das Kommendo wird nicht ausgeführt, nur angezeigt
  - `YFormAdminer::dbEdit($table_name,$data_id)`
    Ruft die edit-Maske für den angegebenen Datensatz der Tabelle im Adminer auf.
- im Live-Mode deaktiviert

## **09.03.2023 1.3.0**

- Callback umgestellt auf die "First Class Callable Syntax" bzw. "Callback-Funktionen als Objekte erster Klasse", also statt `[self::class, 'methode']` nun `self::methode(...)`. Damit wird die statische Code-Analyse verbessert (IDE, RexStan). (@christophboecker #18)
- Notwendige Anhebung der Vorrausetzungen auf PHP ^8.1 und REDAXO ^5.15.

## **09.03.2023 1.2.0**

- Anpassungen an neue YForm-Versionen nach 4.0.4.; Die Änderungen sind schon jetzt im Github-Reoo zu finden.
  Ohne die Änderung würde das Addon zu einem Whoops führen, da die Action-buttons in der Funktion-Spalte
  der YForm-Tabellen geändert wurde (Array statt String). (@christophboecker #12,#13)
- Code insgesamt noch einal überarbeitet (@christophboecker #13)
- rex_i18n verbessert.(@christophboecker #13)

## **07.03.2023 1.1.0

- Button-Texte und Label auf i18n-Verfahen umgestellt (de_de.lang) (@christophboecker #8)
- Verschiedene Korrekturen in der README.md durch Alexander Walther @alxndr-w (#7)
- `require adminer` in `package.yml` eingefügt von Alexander Walther @alxndr-w (#6)
- Die Callback-Funktion (Custom-Format für der Spalte "Funktion") berücksichtigt eine evtl schon vorher gesetzte Funktion (Aufruf-Kaskadierung) (@christophboecker #9)
- RexStan-Überarbeitung: Level 9, REDAXO SuperGlobals|Bleeding-Edge|Strict-Mode|Deprecation Warnings|phpstan-dba|cognitive complexity|report mixed|dead code, PHP 8.1|8.2 (@christophboecker #10)

## **20.01.2023 Version 1.0.2**

- Bugfix: BlueScreen nach Logout behoben (#5)

## **20.01.2023 Version 1.0.1**

- Bugfix: in SQL-PreparedStatements die Platzhalter durch ihre Werte ersetzen (#4). Unterstützer: Thomas Blum (@tbaddade)

## **20.01.2023 Version 1.0.0**

- Initiale Version
