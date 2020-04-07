<?php

namespace LibrarianApp;

use Exception;
use Librarian\Media\ScalarUtils;

/**
 * @method array get(string $collection, int $project_id = null)
 * @method array search(string $collection, string $query, int $project_id = null)
 */
class KeywordsModel extends AppModel {

    /**
     * Get a list of keywords. Used by filter initial view.
     *
     * @param string $collection
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _get(string $collection, int $project_id = null): array {

        $output = [];

        switch ($collection) {

            case 'library':

                $sql = <<<EOT
SELECT id, keyword
    FROM keywords
    ORDER BY keyword COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns = [];

                break;

            case 'clipboard':

                $sql = <<<EOT
SELECT id, keyword
    FROM keywords
    INNER JOIN items_keywords ON keywords.id=items_keywords.keyword_id
    INNER JOIN clipboard ON clipboard.item_id=items_keywords.item_id
    WHERE user_id = ?
    ORDER BY keyword COLLATE utf8Collation
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
SELECT id, keyword
    FROM keywords
    INNER JOIN items_keywords ON keywords.id=items_keywords.keyword_id
    INNER JOIN projects_items ON items_keywords.item_id = projects_items.item_id
    WHERE projects_items.project_id = ?
    ORDER BY keyword COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns = [
                    $project_id
                ];

                break;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            $output[$row['id']] = $row['keyword'];
        }

        return $output;
    }

    /**
     * Search keywords. Used for filter and autocomplete.
     *
     * @param string $collection
     * @param string $query
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _search($collection, $query, int $project_id = null): array {

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
SELECT keywords.id, keywords.keyword
    FROM keywords
    INNER JOIN ind_keywords ON keywords.id = ind_keywords.id
    WHERE ind_keywords.keyword LIKE ?
    ORDER BY keywords.keyword COLLATE utf8Collation
    LIMIT 100
EOT;

                break;

            case 'clipboard':

                $sql = <<<EOT
SELECT keywords.id, keywords.keyword
    FROM keywords
    INNER JOIN ind_keywords ON keywords.id = ind_keywords.id
    INNER JOIN items_keywords ON keywords.id=items_keywords.keyword_id
    INNER JOIN clipboard ON clipboard.item_id=items_keywords.item_id
    WHERE ind_keywords.keyword LIKE ? AND clipboard.user_id = ?
    ORDER BY keywords.keyword COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns[] = $this->user_id;
                break;

            case 'project':

                if($this->verifyProject($project_id) === false) {

                    throw new Exception('you are not authorized to view this project', 403);
                }

                $sql = <<<EOT
SELECT keywords.id, keywords.keyword
    FROM keywords
    INNER JOIN ind_keywords ON keywords.id = ind_keywords.id
    INNER JOIN items_keywords ON keywords.id=items_keywords.keyword_id
    INNER JOIN projects_items ON items_keywords.item_id = projects_items.item_id
    WHERE ind_keywords.keyword LIKE ? AND projects_items.project_id = ?
    ORDER BY keywords.keyword COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns[] = $project_id;
                break;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            $output[$row['id']] = $row['keyword'];
        }

        return $output;
    }
}
