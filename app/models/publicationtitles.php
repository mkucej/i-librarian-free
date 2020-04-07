<?php

namespace LibrarianApp;

use Exception;
use Librarian\Media\ScalarUtils;

/**
 * @method array get(string $collection, string $type, int $project_id = null) Get a list of publication titles.
 * @method array search(string $collection, string $type, string $query, int $project_id = null) Search publication titles.
 */
class PublicationtitlesModel extends AppModel {

    /**
     * Get a list of publication titles. Used by filter initial view.
     *
     * @param string $collection
     * @param string $type
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _get(string $collection, string $type, int $project_id = null): array {

        $types = [
            'primary_title',
            'secondary_title',
            'tertiary_title',
        ];

        if (in_array($type, $types) === false) {

            throw new Exception('unknown model type', 500);
        }

        $output = [];

        switch ($collection) {

            case 'library':

                $sql = <<<EOT
SELECT id, {$type}
    FROM {$type}s
    ORDER BY {$type} COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns = [];

                break;

            case 'clipboard':

                $sql = <<<EOT
SELECT {$type}s.id, {$type}
    FROM {$type}s
    INNER JOIN items ON {$type}s.id=items.{$type}_id
    WHERE items.id IN (
        SELECT item_id FROM clipboard WHERE user_id = ?
    )
    ORDER BY {$type} COLLATE utf8Collation
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
SELECT {$type}s.id, {$type}
    FROM {$type}s
    INNER JOIN items ON {$type}s.id = items.{$type}_id
    INNER JOIN projects_items ON items.id = projects_items.item_id
    WHERE project_id = ?
    ORDER BY {$type} COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns = [
                    $project_id
                ];

                break;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            $output[$row['id']] = $row[$type];
        }

        return $output;
    }

    /**
     * Search publication titles. Used for filter and autocomplete.
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
            'primary_title',
            'secondary_title',
            'tertiary_title',
        ];

        if (in_array($type, $types) === false) {

            throw new Exception('unknown model type', 500);
        }

        $output = [];

        /** @var ScalarUtils $scalar_utils */
        $scalar_utils = $this->di->getShared('ScalarUtils');
        $query = $scalar_utils->deaccent($query, false);

        $columns = [
            "{$query}%"
        ];

        switch ($collection) {

            case 'library':

                $sql = <<<EOT
SELECT ind_{$type}s.id AS id, {$type}s.{$type}
    FROM {$type}s
    INNER JOIN ind_{$type}s ON {$type}s.id=ind_{$type}s.id
    WHERE ind_{$type}s.{$type} LIKE ?
    ORDER BY {$type}s.{$type} COLLATE utf8Collation
    LIMIT 100
EOT;

                break;

            case 'clipboard':

                $sql = <<<EOT
SELECT ind_{$type}s.id AS id, {$type}s.{$type}
    FROM ind_{$type}s
    INNER JOIN {$type}s ON {$type}s.id=ind_{$type}s.id
    INNER JOIN items ON ind_{$type}s.id=items.{$type}_id
    WHERE ind_{$type}s.{$type} LIKE ?
    AND items.id IN (
        SELECT item_id FROM clipboard WHERE user_id = ?
    )
    ORDER BY {$type}s.{$type} COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns[] = $this->user_id;
                break;

            case 'project':

                if($this->verifyProject($project_id) === false) {

                    throw new Exception('you are not authorized to view this project', 403);
                }

                $sql = <<<EOT
SELECT ind_{$type}s.id AS id, {$type}s.{$type}
    FROM ind_{$type}s
    INNER JOIN {$type}s ON {$type}s.id=ind_{$type}s.id
    INNER JOIN items ON {$type}s.id = items.{$type}_id
    INNER JOIN projects_items ON items.id = projects_items.item_id
    WHERE ind_{$type}s.{$type} LIKE ? AND projects_items.project_id = ?
    ORDER BY {$type}s.{$type} COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns[] = $project_id;

                break;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            $output[$row['id']] = $row[$type];
        }

        return $output;
    }
}
