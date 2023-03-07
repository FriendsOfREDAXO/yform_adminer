<?php

/**
 * Hilfsklasse mit allen Funktionen zum Einbau der Adminer-Button.
 *
 * STAN: sämtliche "is never used"-Meldungen sind falsch.
 */

namespace FriendsOfRedaxo\YFormAdminer;

use rex;
use rex_be_controller;
use rex_extension;
use rex_extension_point;
use rex_i18n;
use rex_sql;
use rex_url;
use rex_yform_list;
use rex_yform_manager_dataset;
use rex_yform_manager_query;

use rex_yform_manager_table;

use function array_key_exists;
use function count;
use function is_array;

class YFormAdminer
{
    protected const ICO_ADM = 1;
    protected const ICO_DB = 2;
    protected const ICO_QRY = 3;
    protected const ICO_YF = 4;

    /**
     * @api
     * @var array<string,rex_yform_manager_query<rex_yform_manager_dataset>>
     */
    protected static array $query = [];

    /**
     * Initialisiert den Einbau der Adminer-Button via EP.
     * @api
     */
    public static function init(): void
    {
        rex_extension::register('YFORM_DATA_LIST', [self::class, 'YFORM_DATA_LIST'], rex_extension::LATE);

        // Im Tabellen-Header: Tabelle im Adminer anzeigen
        rex_extension::register('YFORM_DATA_LIST_LINKS', [self::class, 'YFORM_DATA_LIST_LINKS'], rex_extension::LATE);

        // In den Action-Buttons: Datensatz im Adminer (Edit) anzeigen
        rex_extension::register('YFORM_DATA_LIST_ACTION_BUTTONS', [self::class, 'YFORM_DATA_LIST_ACTION_BUTTONS']);

        // Nur im Field-Editor: Links auf Felddefinition
        if ('yform/manager/table_field' === rex_be_controller::getCurrentPage()) {
            rex_extension::register('REX_LIST_GET', [self::class, 'TF_REX_LIST_GET']);
        }

        // Nur im Tableeditor: Links auf Tabellendefinition
        if ('yform/manager/table_edit' === rex_be_controller::getCurrentPage()) {
            rex_extension::register('REX_LIST_GET', [self::class, 'TE_REX_LIST_GET']);
        }
    }

    /**
     * EP-Callback zur Konfiguration der  Datentabelle
     * Damit wird der im EP YFORM_DATA_LIST_ACTION_BUTTONS hinzugefügte Dummy-Button mit
     * konkretem Inhalt (der Abfrage) gefüllt.
     * @api
     * @param rex_extension_point<rex_yform_list> $ep
     */
    public static function YFORM_DATA_LIST(rex_extension_point $ep): void
    {
        /** @var rex_yform_list $list */
        $list = $ep->getSubject();

        /** @var rex_yform_manager_table $table */
        $table = $ep->getParam('table');
        $label = md5(__CLASS__.$table->getTableName());

        self::$query[$label] = $list->getQuery();

        // Action-Buttons umbauen

        $query = clone self::$query[$label];
        $query->resetLimit();
        $query->whereRaw(sprintf('`%s`.`id`=###id###', $query->getTableAlias()));

        $columnName = rex_i18n::msg('yform_function').' ';

        // Falls es schon einen Callback auf der Funktion-Spalte gibt ... berücksichtigen
        $predecessor = $list->getColumnFormat($columnName);
        $predecessorCallback = null;
        if (null !== $predecessor && 'custom' === $predecessor[0]) {
            $predecessorCallback = $predecessor[1];
        }

        $list->setColumnFormat($columnName, 'custom', static function ($params) use ($label, $query, $predecessorCallback) {
            // ggf. vorherige Callback-Funktion ablaufen lassen
            if (null !== $predecessorCallback) {
                $params['value'] = $predecessorCallback($params);
            }
            // Eigene Änderungen anhängen
            $query = clone $query;
            $query->resetLimit();
            $query->whereRaw(sprintf('`%s`.`id`=###id###', $query->getTableAlias()));
            $stmt = self::preparedQuery($query->getQuery(), $query->getParams());
            $url = self::dbSql($stmt);
            $link = self::link($url, self::ICO_QRY, 'Adminer: Datensatz-Abfrage', 'Datensatz-Abfrage');
            return str_replace($label, $link, $params['value']);
        });
    }

    /**
     * EP-Callback zur Konfiguration der Titelzeile über der Datentabelle.
     * @api
     * @param rex_extension_point<array> $ep
     *
     * STAN: Method FriendsOfRedaxo\YFormAdminer\YFormAdminer::YFORM_DATA_LIST_LINKS() has parameter $ep with no value type specified in iterable type array.
     * STAN: was auch immer da rein soll ... na ja, dann ist das halt so.
     * @phpstan-ignore-next-line
     */
    public static function YFORM_DATA_LIST_LINKS(rex_extension_point $ep): void
    {
        if (false === $ep->getParam('popup')) {
            $links = $ep->getSubject();
            if (is_array($links['dataset_links'])) {
                /** @var rex_yform_manager_table $table */
                $table = $ep->getParam('table');
                $label = md5(__CLASS__.$table->getTableName());
                if (isset(self::$query[$label])) {
                    $query = clone self::$query[$label];
                    $query->resetLimit();
                    $stmt = self::preparedQuery($query->getQuery(), $query->getParams());
                    $item = [
                        'label' => '&thinsp;'.self::icon(self::ICO_QRY).'&thinsp;', // ohne thinsp stimmt die Höhe nicht
                        'url' => self::dbSql($stmt),
                        'attributes' => [
                            'class' => ['btn-default', self::iconClass(self::ICO_QRY)],
                            'target' => ['_blank'],
                            'title' => 'Adminer: Abfrage-Daten anzeigen',
                        ],
                    ];
                    array_unshift($links['dataset_links'], $item);
                }

                $item = [
                    'label' => '&thinsp;'.self::icon(self::ICO_DB).'&thinsp;', // ohne thinsp stimmt die Höhe nicht
                    'url' => self::dbTable($ep->getParams()['table']->getTableName()),
                    'attributes' => [
                        'class' => ['btn-default', self::iconClass(self::ICO_DB)],
                        'target' => ['_blank'],
                        'title' => 'Adminer: Daten anzeigen',
                    ],
                ];
                array_unshift($links['dataset_links'], $item);
            }
            if (is_array($links['table_links'])) {
                $item = [
                    'label' => '&thinsp;'.self::icon(self::ICO_YF).'&thinsp;', // ohne thinsp stimmt die Höhe nicht
                    'url' => self::YformTableTable($ep->getParams()['table']->getTableName()),
                    'attributes' => [
                        'class' => ['btn-default', self::iconClass(self::ICO_YF)],
                        'target' => ['_blank'],
                        'title' => 'Adminer: yform_table-Eintrag',
                    ],
                ];
                array_unshift($links['table_links'], $item);
            }
            if (is_array($links['field_links'])) {
                $item = [
                    'label' => '&thinsp;'.self::icon(self::ICO_YF).'&thinsp;', // ohne thinsp stimmt die Höhe nicht
                    'url' => self::yformFieldTable($ep->getParams()['table']->getTableName()),
                    'attributes' => [
                        'class' => ['btn-default', self::iconClass(self::ICO_YF)],
                        'target' => ['_blank'],
                        'title' => 'Adminer: yform_field-Einträge',
                    ],
                ];
                array_unshift($links['field_links'], $item);
            }
            $ep->setSubject($links);
        }
    }

    /**
     * EP-Callback zu, Einfügen zusätzlicher Button in das Action-Menü
     * Da für die Anzeige der Daten auf BAsis der tatsächlichen Query (ggf. mit Joins etc.)
     * das Query-Object hier nicht zur Verfügung steht, wird nur ein Dummy-Eintrag eingebaut
     * und später in YFORM_DATA_LIST ersetzt.
     * @api
     * @param rex_extension_point<array<string,string>> $ep
     * @return array<string,string>
     */
    public static function YFORM_DATA_LIST_ACTION_BUTTONS(rex_extension_point $ep): array
    {
        /** @var rex_yform_manager_table $table */
        $table = $ep->getParam('table');
        /** @var array<string,string> $buttons */
        $buttons = $ep->getSubject();
        $url = self::dbEdit($table->getTableName(), '___id___');
        $buttons['adminer'] = self::link($url, self::ICO_DB, 'Adminer: Datensatz in der DB anzeigen', 'Adminer');

        // Anzeige des Abfrage-Datensatzes (SQL) => Dummy einbauen
        $label = md5(__CLASS__.$table->getTableName());
        $buttons[$label] = $label;
        return $buttons;
    }

    /**
     * EP-Callback zur Konfiguration der Liste der Tabellenfelder im
     * YForm-Table-Manager.
     * @api
     * @param rex_extension_point<rex_yform_list> $ep
     */
    public static function TF_REX_LIST_GET(rex_extension_point $ep): void
    {
        /** @var rex_yform_list $list */
        $list = $ep->getSubject();
        $page = $list->getParams()['page'] ?? '';
        if ('yform/manager/table_field' === $page) { // sicher ist sicher
            /** @var string $table_name */
            $table_name = $list->getParams()['table_name'];
            $columnName = md5('Adminer'.$table_name);

            // Spalte für den Aufruf der Tabellen im Adminer
            $tableUrl = self::yformFieldTable($table_name);
            $fieldUrl = self::yformFieldItem('###id###');
            $base = rex::getTable('yform_field');

            $list->addColumn($columnName.'a', '');
            $list->setColumnLayout($columnName.'a', [
                '<th class="rex-table-icon">'.self::link($tableUrl, self::ICO_YF, 'Adminer: '.$base.'&rarr;'.$table_name.'').'</th>',
                '<td class="rex-table-icon">'.self::link($fieldUrl, self::ICO_YF, 'Adminer: '.$base.'&rarr;###name###').'</td>',
            ]);
        }
    }

    /**
     * EP-Callback zur Konfiguration der Liste "Tabellenübersicht" im
     * YForm-Table-Manager.
     * @api
     * @param rex_extension_point<rex_yform_list> $ep
     */
    public static function TE_REX_LIST_GET(rex_extension_point $ep): void
    {
        /** @var rex_yform_list $list */
        $list = $ep->getSubject();
        $page = $list->getParams()['page'] ?? '';
        if ('yform/manager/table_edit' === $page) { // sicher ist sicher
            $base = rex::getTable('yform_field');
            $columnName = md5('Adminer'.$base);

            $adminerPur = self::baseUrl();
            $datatable = self::dbTable('###table_name###');
            $yformField = self::YformField();
            $yformFieldTable = self::yformFieldTable('###table_name###');

            $list->addColumn($columnName, '');
            $list->setColumnLayout($columnName, [
                '<th class="rex-table-icon">'
                    .self::link($yformField, self::ICO_YF, 'Adminer: '.$base)
                    .self::link($adminerPur, self::ICO_ADM, 'Adminer').
                '</th>',
                '<td class="rex-table-icon">'
                    .self::link($yformFieldTable, self::ICO_YF, 'Adminer: '.$base.'&rarr;###table_name###')
                    .self::link($datatable, self::ICO_DB, 'Adminer: ###table_name###').
                '</td>',
            ]);
        }
    }

    /**
     * Adminer: Aufruf-Basis
     * ein eventueller Platzhalter ###xx### wird von rex_url::backendPage
     * escaped, was wieder zurckgedreht wird, um die Url in Listen nutzen zukönnen.
     * @ api
     * @param array<string|scalar> $params
     */
    protected static function baseUrl(array $params = []): string
    {
        return str_replace('%23%23%23', '###', rex_url::backendPage(
            'adminer',
            array_merge(
                [
                    'db' => rex::getDbConfig(1)->name,
                    'username' => '',
                ],
                $params,
            ),
            false));
    }

    /**
     * Adminer: zeigt die angegebene Tabelle im Adminer.
     * @ api
     */
    protected static function dbTable(string $tablename): string
    {
        return self::baseUrl(
            [
                'select' => $tablename,
            ],
        );
    }

    /**
     * Adminer: ruft die Adminer-Seite "SQL-Kommando" auf.
     * @ api
     */
    protected static function dbSql(string $query): string
    {
        return self::baseUrl(
            [
                'sql' => $query,
            ],
        );
    }

    /**
     * Adminer: ruft rex_yform_field allgemein auf.
     * @ api
     */
    protected static function YformField(): string
    {
        return self::dbTable(rex::getTable('yform_field'));
    }

    /**
     * Adminer: Ruft die edit-Maske für den angegebenen Datensatz der Tabelle im Adminer auf.
     * @ api
     */
    protected static function dbEdit(string $tablename, int|string $id): string
    {
        return self::baseUrl(
            [
                'edit' => $tablename,
                'where[id]' => $id,
            ],
        );
    }

    /**
     * ruft Adminer: rex_yform_table für eine angegebene YForm-Tabelle aus.
     * @ api
     */
    protected static function YformTableTable(string $tablename): string
    {
        return self::baseUrl(
            [
                'select' => rex::getTable('yform_table'),
                'where[0][col]' => 'table_name',
                'where[0][op]' => '=',
                'where[0][val]' => $tablename,
            ],
        );
    }

    /**
     * Adminer: ruft rex_yform_field für eine bestimmte Tabelle auf.
     * @ api
     */
    protected static function yformFieldTable(string $tablename): string
    {
        return self::baseUrl(
            [
                'select' => rex::getTable('yform_field'),
                'where[0][col]' => 'table_name',
                'where[0][op]' => '=',
                'where[0][val]' => $tablename,
            ],
        );
    }

    /**
     * Adminer: ruft rex_yform_field für einen bestimmten Eintrag auf.
     * @ api
     */
    protected static function yformFieldItem(int|string $id): string
    {
        return self::baseUrl(
            [
                'select' => rex::getTable('yform_field'),
                'where[0][col]' => 'id',
                'where[0][op]' => '=',
                'where[0][val]' => $id,
            ],
        );
    }

    /**
     * liefert zu einer Icon-Nummer das Icon-HTML.
     * @ api
     */
    protected static function icon(int $icon): string
    {
        switch ($icon) {
            case self::ICO_DB: return '<i class="rex-icon rex-icon-database for-yfa-table-color"></i>';
            case self::ICO_QRY: return '<i class="rex-icon rex-icon-search for-yfa-table-color"></i>';
            case self::ICO_YF: return '<i class="rex-icon fa-wpforms for-yfa-yform-color"></i>';
        }
        return '<i class="rex-icon rex-icon-database xxfor-yfa-color"></i>';
    }

    /**
     * liefert zu einer Icon-Nummer die Klasse zur Darstellungs-Anpassung.
     * @ api
     */
    protected static function iconClass(int $icon): string
    {
        switch ($icon) {
            case self::ICO_DB: return 'for-yfa-table-color';
            case self::ICO_QRY: return 'for-yfa-table-color';
            case self::ICO_YF: return 'for-yfa-yform-color';
        }
        return 'for-yfa-color';
    }

    /**
     * erzeugt einen a-Tag für den Link
     * Mindestangabe sind Link ind Icon.
     * @ api
     */
    protected static function link(string $url, int $icon, string $title = '', string $label = ''): string
    {
        if ('' !== $label) {
            $label = ' ' . $label;
        }
        return sprintf('<a href="%s" class="%s" target="_blank" title="%s">%s%s</a>', $url, self::iconClass($icon), $title, self::icon($icon), $label);
    }

    /**
     * @param array<string, int|string> $params
     * @ api
     */
    protected static function preparedQuery(string $query, array $params = []): string
    {
        if (0 === count($params)) {
            return $query;
        }
        $sql = rex_sql::factory();
        $i = 0;
        $pregResult = preg_replace_callback(
            '/\?|((?<!:):[a-z0-9_]+)/i',
            static function ($matches) use ($params, &$i, $sql) {
                if ('?' === $matches[0]) {
                    $keys = [$i];
                } else {
                    $keys = [$matches[0], substr($matches[0], 1)];
                }

                foreach ($keys as $key) {
                    if (array_key_exists($key, $params)) {
                        ++$i;
                        return $sql->escape((string) $params[$key]);
                    }
                }

                return $matches[0];
            },
            $query,
        );
        return null === $pregResult ? $query : $pregResult;
    }
}
