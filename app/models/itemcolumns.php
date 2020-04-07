<?php

namespace LibrarianApp;

use Exception;
use Librarian\Media\ScalarUtils;

/**
 * @method array get(string $collection, string $type, $project_id = null)
 * @method array search(string $collection, string $type, string $query, int $project_id = null)
 */
class ItemcolumnsModel extends AppModel {

    /**
     * Get a list of column values. Used by filter initial view.
     *
     * @param string $collection
     * @param string $type
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _get(string $collection, string $type, int $project_id = null): array {

        $types = [
            'added_time',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'custom5',
            'custom6',
            'custom7',
            'custom8'
        ];

        if (in_array($type, $types) === false) {

            throw new Exception('unknown model type', 500);
        }

        $output = [];
        $sort = 'ASC';
        $column_type = $type;

        if ($type === 'added_time') {

            $sort = 'DESC';
            $column_type = "substr(items.added_time, 1, 10) AS at";
            $type = 'at';
        }

        switch ($collection) {

            case 'library':

                $sql = <<<EOT
SELECT DISTINCT {$column_type}
    FROM items
    ORDER BY {$type} COLLATE utf8Collation {$sort}
    LIMIT 100
EOT;

                $columns = [];

                break;

            case 'clipboard':

                $sql = <<<EOT
SELECT DISTINCT {$column_type}
    FROM items
    INNER JOIN clipboard ON items.id=clipboard.item_id
    WHERE user_id = ?
    ORDER BY {$type} COLLATE utf8Collation {$sort}
    LIMIT 100
EOT;

                $columns = [
                    $this->user_id
                ];

                break;

            case 'project':

                if($this->verifyProject($project_id) === false) {

                    throw new Exception('you are not authorized to view this project', 403);
                }

                $sql = <<<EOT
SELECT DISTINCT {$column_type}
    FROM items
    INNER JOIN projects_items ON items.id = projects_items.item_id
    WHERE project_id = ?
    ORDER BY {$type} COLLATE utf8Collation {$sort}
    LIMIT 100
EOT;

                $columns = [
                    $project_id
                ];

                break;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            if (empty($row[$type])) {

                continue;
            }

            // Each value is it's id too, because these columns are not normalized.
            $output[$row[$type]] = $row[$type];
        }

        return $output;
    }

    /**
     * Search column values. Used for filter and autocomplete.
     *
     * @param string $collection
     * @param string $type
     * @param string $query
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _search(string $collection, string $type, string $query, int $project_id = null): array {

        $types = [
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'custom5',
            'custom6',
            'custom7',
            'custom8'
        ];

        if (in_array($type, $types) === false) {

            throw new Exception('unknown model type', 500);
        }

        $output = [];

        /** @var ScalarUtils $scalar_utils */
        $scalar_utils = $this->di->getShared('ScalarUtils');
        $query = $scalar_utils->deaccent($query, false);

        $columns = [
            "% {$query}%"
        ];

        switch ($collection) {

            case 'library':

                $sql = <<<EOT
SELECT DISTINCT {$type}
    FROM items
    INNER JOIN ind_items ON items.id = ind_items.id
    WHERE ind_items.{$type}_index LIKE ?
    ORDER BY items.{$type} COLLATE utf8Collation
    LIMIT 100
EOT;

                break;

            case 'clipboard':

                $sql = <<<EOT
SELECT DISTINCT {$type}
    FROM items
    INNER JOIN ind_items ON items.id = ind_items.id
    INNER JOIN clipboard ON items.id=clipboard.item_id
    WHERE ind_items.{$type}_index LIKE ? AND clipboard.user_id = ?
    ORDER BY items.{$type} COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns[] = $this->user_id;

                break;

            case 'project':

                if($this->verifyProject($project_id) === false) {

                    throw new Exception('you are not authorized to view this project', 403);
                }

                $sql = <<<EOT
SELECT DISTINCT {$type}
    FROM items
    INNER JOIN ind_items ON items.id = ind_items.id
    INNER JOIN projects_items ON items.id=projects_items.item_id
    WHERE ind_items.{$type}_index LIKE ? AND project_id = ?
    ORDER BY items.{$type} COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns[] = $project_id;

                break;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            // Each value is it's id too, because these columns are not normalized.
            $output[$row[$type]] = $row[$type];
        }

        return $output;
    }
}
