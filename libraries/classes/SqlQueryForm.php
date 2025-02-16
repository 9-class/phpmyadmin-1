<?php
/**
 * functions for displaying the sql query form
 *
 * @usedby  /server/sql
 * @usedby  /database/sql
 * @usedby  /table/sql
 * @usedby  /table/structure
 * @usedby  /table/tracking
 */

declare(strict_types=1);

namespace PhpMyAdmin;

use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\Html\MySQLDocumentation;
use PhpMyAdmin\Utils\ForeignKey;

use function __;
use function htmlspecialchars;
use function sprintf;
use function str_contains;

/**
 * PhpMyAdmin\SqlQueryForm class
 */
class SqlQueryForm
{
    public function __construct(private Template $template, private DatabaseInterface $dbi)
    {
    }

    /**
     * return HTML for the sql query boxes
     *
     * @param bool|string $query       query to display in the textarea
     *                                 or true to display last executed
     * @param bool|string $display_tab sql|full|false
     *                                 what part to display
     *                                 false if not inside querywindow
     * @param string      $delimiter   delimiter
     *
     * @usedby  /server/sql
     * @usedby  /database/sql
     * @usedby  /table/sql
     * @usedby  /table/structure
     * @usedby  /table/tracking
     */
    public function getHtml(
        string $db,
        string $table,
        bool|string $query = true,
        bool|string $display_tab = false,
        string $delimiter = ';',
    ): string {
        if (! $display_tab) {
            $display_tab = 'full';
        }

        // query to show
        if ($query === true) {
            $query = $GLOBALS['sql_query'];
            if (empty($query) && (isset($_GET['show_query']) || isset($_POST['show_query']))) {
                $query = $_GET['sql_query'] ?? $_POST['sql_query'] ?? '';
            }
        }

        if ($db === '') {
            // prepare for server related
            $goto = empty($GLOBALS['goto']) ? Url::getFromRoute('/server/sql') : $GLOBALS['goto'];
        } elseif ($table === '') {
            // prepare for db related
            $goto = empty($GLOBALS['goto']) ? Url::getFromRoute('/database/sql') : $GLOBALS['goto'];
        } else {
            $goto = empty($GLOBALS['goto']) ? Url::getFromRoute('/table/sql') : $GLOBALS['goto'];
        }

        if ($display_tab === 'full' || $display_tab === 'sql') {
            [$legend, $query, $columns_list] = $this->init($query);
        }

        $relation = new Relation($this->dbi);
        $bookmarkFeature = $relation->getRelationParameters()->bookmarkFeature;

        $bookmarks = [];
        if ($display_tab === 'full' && $bookmarkFeature !== null) {
            $bookmark_list = Bookmark::getList(
                $bookmarkFeature,
                $this->dbi,
                $GLOBALS['cfg']['Server']['user'],
                $db,
            );

            foreach ($bookmark_list as $bookmarkItem) {
                $bookmarks[] = [
                    'id' => $bookmarkItem->getId(),
                    'variable_count' => $bookmarkItem->getVariableCount(),
                    'label' => $bookmarkItem->getLabel(),
                    'is_shared' => $bookmarkItem->getUser() === '',
                ];
            }
        }

        return $this->template->render('sql/query', [
            'legend' => $legend ?? '',
            'textarea_cols' => $GLOBALS['cfg']['TextareaCols'],
            'textarea_rows' => $GLOBALS['cfg']['TextareaRows'],
            'textarea_auto_select' => $GLOBALS['cfg']['TextareaAutoSelect'],
            'columns_list' => $columns_list ?? [],
            'codemirror_enable' => $GLOBALS['cfg']['CodemirrorEnable'],
            'has_bookmark' => $bookmarkFeature !== null,
            'delimiter' => $delimiter,
            'retain_query_box' => $GLOBALS['cfg']['RetainQueryBox'] !== false,
            'is_upload' => $GLOBALS['config']->get('enable_upload'),
            'db' => $db,
            'table' => $table,
            'goto' => $goto,
            'query' => $query,
            'display_tab' => $display_tab,
            'bookmarks' => $bookmarks,
            'can_convert_kanji' => Encoding::canConvertKanji(),
            'is_foreign_key_check' => ForeignKey::isCheckEnabled(),
        ]);
    }

    /**
     * Get initial values for Sql Query Form Insert
     *
     * @param string $query query to display in the textarea
     *
     * @return array ($legend, $query, $columns_list)
     */
    public function init(string $query): array
    {
        $columns_list = [];
        if ($GLOBALS['db'] === '') {
            // prepare for server related
            $legend = sprintf(
                __('Run SQL query/queries on server “%s”'),
                htmlspecialchars(
                    ! empty($GLOBALS['cfg']['Servers'][$GLOBALS['server']]['verbose'])
                    ? $GLOBALS['cfg']['Servers'][$GLOBALS['server']]['verbose']
                    : $GLOBALS['cfg']['Servers'][$GLOBALS['server']]['host'],
                ),
            );
        } elseif ($GLOBALS['table'] === '') {
            // prepare for db related
            $db = $GLOBALS['db'];
            // if you want navigation:
            $scriptName = Util::getScriptNameForOption($GLOBALS['cfg']['DefaultTabDatabase'], 'database');
            $tmp_db_link = '<a href="' . $scriptName
                . Url::getCommon(['db' => $db], ! str_contains($scriptName, '?') ? '?' : '&')
                . '">';
            $tmp_db_link .= htmlspecialchars($db) . '</a>';
            $legend = sprintf(__('Run SQL query/queries on database %s'), $tmp_db_link);
            if ($query === '') {
                $query = Util::expandUserString($GLOBALS['cfg']['DefaultQueryDatabase'], Util::backquote(...));
            }
        } else {
            $db = $GLOBALS['db'];
            $table = $GLOBALS['table'];
            // Get the list and number of fields
            // we do a try_query here, because we could be in the query window,
            // trying to synchronize and the table has not yet been created
            $columns_list = $this->dbi->getColumns($db, $GLOBALS['table'], true);

            $scriptName = Util::getScriptNameForOption($GLOBALS['cfg']['DefaultTabTable'], 'table');
            $tmp_tbl_link = '<a href="' . $scriptName . Url::getCommon(['db' => $db, 'table' => $table], '&') . '">';
            $tmp_tbl_link .= htmlspecialchars($db) . '.' . htmlspecialchars($table) . '</a>';
            $legend = sprintf(__('Run SQL query/queries on table %s'), $tmp_tbl_link);
            if ($query === '') {
                $query = Util::expandUserString($GLOBALS['cfg']['DefaultQueryTable'], Util::backquote(...));
            }
        }

        $legend .= ': ' . MySQLDocumentation::show('SELECT');

        return [
            $legend,
            $query,
            $columns_list,
        ];
    }
}
