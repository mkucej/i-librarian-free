<?php

namespace LibrarianApp;

use Exception;
use Librarian\Media\ScalarUtils;

/**
 * @method array getEditors(string $collection, int $project_id = null)
 * @method array searchEditors(string $collection, string $query, int $project_id = null)
 */
class EditorsModel extends AppModel {

    /**
     * Get a list of editors. Used by filter initial view.
     *
     * @param string $collection
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _getEditors(string $collection, int $project_id = null): array {

        $output = [];

        switch ($collection) {

            case 'library':

                $sql = <<<'EOT'
SELECT id, last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END AS editor_name
    FROM editors
    ORDER BY last_name COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns = [];

                break;

            case 'clipboard':

                $sql = <<<'EOT'
SELECT
    editors.id,
    editors.last_name || CASE editors.first_name WHEN '' THEN '' ELSE ', ' || editors.first_name END AS editor_name
    FROM editors
    INNER JOIN items_editors ON editors.id=items_editors.editor_id
    INNER JOIN clipboard ON items_editors.item_id=clipboard.item_id
    WHERE clipboard.user_id = ?
    ORDER BY last_name COLLATE utf8Collation
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

                $sql = <<<'EOT'
SELECT
    editors.id,
    editors.last_name || CASE editors.first_name WHEN '' THEN '' ELSE ', ' || editors.first_name END AS editor_name
    FROM editors
    INNER JOIN items_editors ON editors.id=items_editors.editor_id
    INNER JOIN projects_items on items_editors.item_id = projects_items.item_id
    WHERE projects_items.project_id = ?
    ORDER BY last_name COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns = [
                    $project_id
                ];

                break;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            $output[$row['id']] = $row['editor_name'];
        }

        return $output;
    }

    /**
     * Search editors. Used for author filter and autocomplete.
     *
     * @param string $collection
     * @param string $query
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _searchEditors(string $collection, string $query, int $project_id = null): array {

        $output = [];

        /** @var ScalarUtils $scalar_utils */
        $scalar_utils = $this->di->getShared('ScalarUtils');
        $query = $scalar_utils->deaccent($query, false);

        $names = explode(',', $query);
        $last_name = trim($names[0]);
        $first_name = isset($names[1]) && !empty(trim($names[1])) ? trim($names[1]) : null;

        $columns = [
            "{$last_name}%"
        ];

        // Add first name.
        if (isset($first_name)) {

            $columns = [
                "{$last_name}, {$first_name}%"
            ];
        }

        switch ($collection) {

            case 'library':

                $sql = <<<'EOT'
SELECT editors.id, editors.last_name || CASE editors.first_name WHEN '' THEN '' ELSE ', ' || editors.first_name END AS editor_name
    FROM editors INNER JOIN ind_editors ON ind_editors.id=editors.id
    WHERE ind_editors.editor LIKE ?
    ORDER BY editors.last_name COLLATE utf8Collation
    LIMIT 100
EOT;

                break;

            case 'clipboard':

                $sql = <<<'EOT'
SELECT editors.id, editors.last_name || CASE editors.first_name WHEN '' THEN '' ELSE ', ' || editors.first_name END AS editor_name
    FROM editors
    INNER JOIN ind_editors ON ind_editors.id=editors.id
    INNER JOIN items_editors ON editors.id=items_editors.editor_id
    INNER JOIN clipboard ON items_editors.item_id=clipboard.item_id
    WHERE ind_editors.editor LIKE ? AND clipboard.user_id = ?
    ORDER BY last_name COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns[] = $this->user_id;

                break;

            case 'project':

                if($this->verifyProject($project_id) === false) {

                    throw new Exception('you are not authorized to view this project', 403);
                }

                $sql = <<<'EOT'
SELECT editors.id, editors.last_name || CASE editors.first_name WHEN '' THEN '' ELSE ', ' || editors.first_name END AS editor_name
    FROM editors
    INNER JOIN ind_editors ON ind_editors.id=editors.id
    INNER JOIN items_editors ON editors.id=items_editors.editor_id
    INNER JOIN projects_items on items_editors.item_id = projects_items.item_id
    WHERE ind_editors.editor LIKE ? AND projects_items.project_id = ?
    ORDER BY last_name COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns[] = $project_id;

                break;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            $output[$row['id']] = $row['editor_name'];
        }

        return $output;
    }
}
