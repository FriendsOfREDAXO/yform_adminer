# Adminer in YForm verlinken

Berechtigte Benutzer erhalten im YForm-Tablemanager kontext-bezogene Buttons, mit denen man direkt mittels Adminer Tabellen oder gefilterte Daten angezeigt bekommt. So kann man schneller mit nur einem Klick in der Datenbank nach dem Rechten sehen, Ergebnisse kontrollieren oder sonstwie eingreifen.

Dazu muss neben dem Addon [YForm](https://github.com/yakamara/redaxo_yform) auch das FOR-Addon [Adminer](https://github.com/FriendsOfREDAXO/adminer) installiert und aktiviert sein.

Das Addon hängt ausschließlich über Extension-Points zusätzliche Button in Kopfzeilen und Spalten.

##  Datensätze in der Listen-Ansicht einer YForm-Tabelle

![](https://raw.githubusercontent.com/FriendsOfREDAXO/yform_adminer/main/internal_support/data_list.png)

1. Die Daten der Tabelle
2. Die SQL-Query aus der zugehörigen YOrm-Query der Tabelle
3. Die Tabellen-Konfiguration in `rex_yform_table` (eine Zeile)
4. Die Felddefinitionen der Tabelle in `rex_yform_field` (Auszug aus `rex_yform_field`) &rarr; 9./11.
5. Der Datensatz zu dieser Zeile
6. Die SQL-Query aus der zugehörigen YOrm-Query, auf diesen Satz beschränkt (id=...)

> Zu 2. und 6.: Je nach Komplexität der Query (z.B. durch Joins erweitert) kann es auch nicht funktionieren.

## Tabellendefinition im Table-Manager 

![](https://raw.githubusercontent.com/FriendsOfREDAXO/yform_adminer/main/internal_support/table_edit.png)

7. Tabelle `rex_yform_field` insgesamt
8. Adminer-Gesamtansicht
9. Die Felddefinitionen der Tabelle in `rex_yform_field` (Auszug aus `rex_yform_field`) &rarr; 4./11.
10. Die Daten der Tabelle

## Felddefinitionen im Table-Manager

![](https://raw.githubusercontent.com/FriendsOfREDAXO/yform_adminer/main/internal_support/table_field.png)

11. Die Felddefinitionen der **Tabelle** in `rex_yform_field` (Auszug aus `rex_yform_field`) &rarr; 4./9.
12. Die Felddefinitionen des **Feldes** in `rex_yform_field` (Auszug aus `rex_yform_field`)

## Berechtigungen

Das Addon wird nur aktiv, wenn der Benutzer Administrator ist oder über die Benutzerverwaltung die Berechtigung `yform_adminer[]`
erhält.

## Konfiguration

Die Farbe der Buttons kann mit individuellem CSS z.B. via Themes- oder Project-Addon geändert werden.
Hier das CSS der obigen Bildbeispiele:

```css
/* Allgemeine Farbe für Adminer */
.for-yfa-color { 
    color: black;
}

/* Farbe für Datenbank-Tabellen */
.for-yfa-table-color {
    color:lightseagreen;
}

/* Farbe für YForm-Systemtabellen (rex_yform_table, rex_yform_field) */
.for-yfa-yform-color {
    color:darkorange;
}
```

## Nutzung in eigenen Addons

Einige Methoden zum Aufbau einer Adminer-URL stehen zur Nutzung in eigenen Addons zur Verfügung.
Bitte dabei beachten, dass YForm_Adminer wie Adminer nur für Admins und nicht im LiveMode verfügbar ist.

```php
// Tabelle anzeigen
$url1 = YFormAdminer::dbTable(
    rex::getTable('config'),
);

// Gefilterte Tabelle anzeigen
$url2 = YFormAdminer::dbTable(
    rex::getTable('config'),
    [
        [
            'col' => 'namespace',
            'op' => '=',
            'val' => 'core',
        ],
    ],
);

// SQL-Query im Fenster "SQL-Kommando" zur Ausführung bereitstellen
$url3 = YFormAdminer::dbSql('SELECT namespace, key FROM rex_config');

// Seite editieren für Tabelle X mit Datensatz mit id=... 
$url4 = YFormAdminer::dbEdit(
    rex::getTable('clang'),
    1,
);

?>
<ul>
<li><a href="<?= $url1 ?>" target="_blank">Tabelle "rex_config"</a></li>
<li><a href="<?= $url2 ?>" target="_blank">Tabelle "rex_config" gefiltert (where)</a></li>
<li><a href="<?= $url3 ?>" target="_blank">SQL-Kommando</a></li>
<li><a href="<?= $url4 ?>" target="_blank">Datensatz editieren (rex_clang Satz 1)</a></li>
</ul>

```

## Fehler, Ideen, Fragen, Support 

Schreibt doch bitte auftretende Fehler, Anmerkungen und Wünsche als Issue auf [Github](https://github.com/FriendsOfREDAXO/yform_adminer/issues).
Oder macht direkt einen Vorschlag als Pull-Request.

Unterstützung für dieses und alle anderen Projekte zu REDAXO und FOR ist stets willkommen.

---
## ChangeLog

Das Changelog findet sich hier: [CHANGELOG.md](CHANGELOG.md)

## Lizenz

[The MIT License (MIT)](LICENSE.md)
