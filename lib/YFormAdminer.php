<?php

/**
 * Hilfsklasse mit allen Funktionen zum Einbau der Adminer-Button.
 */

namespace FriendsOfRedaxo\YFormAdminer;

use rex;
use rex_be_controller;
use rex_extension;
use rex_extension_point;
use rex_i18n;
use rex_path;
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

    /** @var array<string,rex_yform_manager_query<rex_yform_manager_dataset>> */
    protected static array $query = [];

    /**
     * Initialisiert den Einbau der Adminer-Button via EP.
     * @api
     */
    public static function init(): void
    {
        // Vorbereitung: Query ermitteln
        rex_extension::register('YFORM_DATA_LIST_QUERY', self::YFORM_DATA_LIST_QUERY(...), rex_extension::LATE);

        // Im Tabellen-Header: Tabelle im Adminer anzeigen
        // STAN: Parameter #2 $extension of static method rex_extension::register() expects callable(rex_extension_point<array<string, array>>): mixed, array{'FriendsOfRedaxo\\YFormAdminer\\YFormAdminer', 'YFORM_DATA_LIST…'} given.
        // Was auch immer hier gewünscht ist ....
        rex_extension::register('YFORM_DATA_LIST_LINKS', self::YFORM_DATA_LIST_LINKS(...), rex_extension::LATE);

        // In den Action-Buttons: Datensatz im Adminer (Edit) anzeigen
        // STAN: Parameter #2 $extension of static method rex_extension::register() expects callable(rex_extension_point<array<string, mixed>>): mixed, array{'FriendsOfRedaxo\\YFormAdminer\\YFormAdminer', 'YFORM_DATA_LIST…'} given.
        // Was auch immer hier gewünscht ist ....
        rex_extension::register('YFORM_DATA_LIST_ACTION_BUTTONS', self::YFORM_DATA_LIST_ACTION_BUTTONS(...), rex_extension::LATE);

        // Nur im Field-Editor: Links auf Felddefinition
        if ('yform/manager/table_field' === rex_be_controller::getCurrentPage()) {
            rex_extension::register('REX_LIST_GET', self::TF_REX_LIST_GET(...));
        }

        // Nur im Tableeditor: Links auf Tabellendefinition
        if ('yform/manager/table_edit' === rex_be_controller::getCurrentPage()) {
            rex_extension::register('REX_LIST_GET', self::TE_REX_LIST_GET(...));
        }
    }

    /**
     * EP-Callback zur Ermittlung der Query.
     * Die Query wird für die spätere Nutzung durch andere EPs abgelegt.
     * @api
     * @param rex_extension_point<rex_yform_manager_query<rex_yform_manager_dataset>> $ep
     */
    public static function YFORM_DATA_LIST_QUERY(rex_extension_point $ep): void
    {
        $query = $ep->getSubject();
        $label = md5(__CLASS__ . $query->getTableName());
        self::$query[$label] = $query;
    }

    /**
     * EP-Callback zur Konfiguration der Titelzeile über der Datentabelle.
     * @api
     * @param rex_extension_point<array<string,array<mixed>>> $ep
     */
    public static function YFORM_DATA_LIST_LINKS(rex_extension_point $ep): void
    {
        if (true === $ep->getParam('popup')) {
            return;
        }
        $links = $ep->getSubject();
        if (is_array($links['dataset_links'])) {
            /** @var rex_yform_manager_table $table */
            $table = $ep->getParam('table');
            $label = md5(__CLASS__ . $table->getTableName());
            if (isset(self::$query[$label])) {
                $query = clone self::$query[$label];
                $query->resetLimit();
                $stmt = self::preparedQuery($query->getQuery(), $query->getParams());
                $item = [
                    'label' => '&thinsp;' . self::icon(self::ICO_QRY) . '&thinsp;', // ohne thinsp stimmt die Höhe nicht
                    'url' => self::dbSql($stmt),
                    'attributes' => [
                        'class' => ['btn-default', self::iconClass(self::ICO_QRY)],
                        'target' => ['_blank'],
                        'title' => rex_i18n::msg('yform_adminer_ydll_sql_title'),
                    ],
                ];
                array_unshift($links['dataset_links'], $item);
            }

            $item = [
                'label' => '&thinsp;' . self::icon(self::ICO_DB) . '&thinsp;', // ohne thinsp stimmt die Höhe nicht
                'url' => self::dbTable($ep->getParams()['table']->getTableName()),
                'attributes' => [
                    'class' => ['btn-default', self::iconClass(self::ICO_DB)],
                    'target' => ['_blank'],
                    'title' => rex_i18n::msg('yform_adminer_ydll_data_title'),
                ],
            ];
            array_unshift($links['dataset_links'], $item);
        }
        if (is_array($links['table_links'])) {
            $item = [
                'label' => '&thinsp;' . self::icon(self::ICO_YF) . '&thinsp;', // ohne thinsp stimmt die Höhe nicht
                'url' => self::YformTableTable($ep->getParams()['table']->getTableName()),
                'attributes' => [
                    'class' => ['btn-default', self::iconClass(self::ICO_YF)],
                    'target' => ['_blank'],
                    'title' => rex_i18n::msg('yform_adminer_action_entry', 'yform_table'),
                ],
            ];
            array_unshift($links['table_links'], $item);
        }
        if (is_array($links['field_links'])) {
            $item = [
                'label' => '&thinsp;' . self::icon(self::ICO_YF) . '&thinsp;', // ohne thinsp stimmt die Höhe nicht
                'url' => self::yformFieldTable($ep->getParams()['table']->getTableName()),
                'attributes' => [
                    'class' => ['btn-default', self::iconClass(self::ICO_YF)],
                    'target' => ['_blank'],
                    'title' => rex_i18n::msg('yform_adminer_action_entries', 'yform_field'),
                ],
            ];
            array_unshift($links['field_links'], $item);
        }
        $ep->setSubject($links);
    }

    /**
     * EP-Callback zum Einfügen zusätzlicher Button in das Action-Menü
     * Da für die Anzeige der Daten auf Basis der tatsächlichen Query (ggf. mit Joins etc.)
     * das Query-Object hier nicht zur Verfügung steht, wird nur ein Dummy-Eintrag eingebaut
     * und später in YFORM_DATA_LIST ersetzt.
     * @api
     * @param rex_extension_point<array<string,mixed>> $ep
     */
    public static function YFORM_DATA_LIST_ACTION_BUTTONS(rex_extension_point $ep): void
    {
        /** @var rex_yform_manager_table $table */
        $table = $ep->getParam('table');
        $buttons = $ep->getSubject();

        /**
         * bis YForm 4.0.4 waren die Action-Buttons einfach HTML-Strings.
         * Post-4.0.4. sind es Arrays, die in einem List-Fragment verwertet werden.
         * Hier die beiden Fälle unterscheiden.
         * Note:
         * Stand 07.03.2023 gibt es nur das GH-Repo und keine neue Versionsnummer.
         * Daher auf das neue Fragment als Unterscheidungsmerkmal setzen.
         */
        $isPostYform404 = is_file(rex_path::plugin('yform', 'manager', 'fragments/yform/manager/page/list.php'));

        // Adminer-Button für den Datensatz
        $url = self::dbEdit($table->getTableName(), '___id___');
        $title = rex_i18n::msg('yform_adminer_ydl_ds_title');
        if ($isPostYform404) {
            $buttons['adminer'] = [
                'url' => $url,
                'content' => self::icon(self::ICO_DB) . ' Adminer',
                'attributes' => [
                    'class' => self::iconClass(self::ICO_DB),
                    'target' => '_blank',
                    'title' => $title,
                ],
            ];
        } else {
            $buttons['adminer'] = self::link($url, self::ICO_DB, $title, 'Adminer');
        }

        // Adminer-Button für die SQL-Query
        $label = md5(__CLASS__ . $table->getTableName());
        if (isset(self::$query[$label])) {
            // Aus der Query das SQL-Statement erzeugen
            $query = clone self::$query[$label];
            $query->resetLimit();
            $query->whereRaw(sprintf('`%s`.`id`=###id###', $query->getTableAlias()));
            $stmt = self::preparedQuery($query->getQuery(), $query->getParams());
            $url = self::dbSql($stmt);

            $title = rex_i18n::msg('yform_adminer_ydll_sql_title');
            if ($isPostYform404) {
                $buttons['adminer-sql'] = [
                    'url' => $url,
                    'content' => self::icon(self::ICO_QRY) . ' ' . rex_i18n::msg('yform_adminer_ydl_sql_label'),
                    'attributes' => [
                        'class' => self::iconClass(self::ICO_QRY),
                        'target' => '_blank',
                        'title' => $title,
                    ],
                ];
            } else {
                $buttons['adminer-sql'] = self::link($url, self::ICO_QRY, $title, rex_i18n::msg('yform_adminer_ydl_sql_label'));
            }
        }
        $ep->setSubject($buttons);
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
            $columnName = md5('Adminer' . $table_name);

            // Spalte für den Aufruf der Tabellen im Adminer
            $tableUrl = self::yformFieldTable($table_name);
            $fieldUrl = self::yformFieldItem('###id###');
            $base = rex::getTable('yform_field');

            $list->addColumn($columnName . 'a', '');
            $list->setColumnLayout($columnName . 'a', [
                '<th class="rex-table-icon">' . self::link($tableUrl, self::ICO_YF, rex_i18n::msg('yform_adminer_action_title', $base, $table_name)) . '</th>',
                '<td class="rex-table-icon">' . self::link($fieldUrl, self::ICO_YF, rex_i18n::msg('yform_adminer_action_title', $base, '###name###')) . '</td>',
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
            $columnName = md5('Adminer' . $base);

            $adminerPur = self::baseUrl();
            $datatable = self::dbTable('###table_name###');
            $yformField = self::YformField();
            $yformFieldTable = self::yformFieldTable('###table_name###');

            $list->addColumn($columnName, '');
            $list->setColumnLayout($columnName, [
                '<th class="rex-table-icon">'
                    . self::link($yformField, self::ICO_YF, rex_i18n::msg('yform_adminer_datatable_title', $base))
                    . self::link($adminerPur, self::ICO_ADM, 'Adminer') .
                '</th>',
                '<td class="rex-table-icon">'
                    . self::link($yformFieldTable, self::ICO_YF, rex_i18n::msg('yform_adminer_action_title', $base, '###table_name###'))
                    . self::link($datatable, self::ICO_DB, rex_i18n::msg('yform_adminer_datatable_title', '###table_name###')) .
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
