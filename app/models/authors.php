<?php

namespace LibrarianApp;

use Exception;
use Librarian\Media\ScalarUtils;

/**
 * @method array getAuthors(string $collection, int $project_id = null) Get a list of authors.
 * @method array searchAuthors(string $collection, string $query, int $project_id = null) Search authors.
 */
class AuthorsModel extends AppModel {

    /**
     * Get a list of authors. Used by filter initial view.
     *
     * @param string $collection
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _getAuthors($collection = 'library', int $project_id = null): array {

        $output = [];

        switch ($collection) {

            case 'library':

                $sql = <<<'EOT'
SELECT id, last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END AS author_name
    FROM authors
    ORDER BY last_name COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns = [];

                break;

            case 'clipboard':

                $sql = <<<'EOT'
SELECT
    authors.id,
    authors.last_name || CASE authors.first_name WHEN '' THEN '' ELSE ', ' || authors.first_name END AS author_name
    FROM authors
    INNER JOIN items_authors ON authors.id=items_authors.author_id
    INNER JOIN clipboard ON items_authors.item_id=clipboard.item_id
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
    authors.id,
    authors.last_name || CASE authors.first_name WHEN '' THEN '' ELSE ', ' || authors.first_name END AS author_name
    FROM authors
    INNER JOIN items_authors ON authors.id=items_authors.author_id
    INNER JOIN projects_items on items_authors.item_id = projects_items.item_id
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

            $output[$row['id']] = $row['author_name'];
        }

        return $output;
    }

    /**
     * Search authors. Used for author filter and autocomplete.
     *
     * @param string $collection
     * @param string $query
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _searchAuthors($collection, $query, int $project_id = null): array {

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
SELECT authors.id, authors.last_name || CASE authors.first_name WHEN '' THEN '' ELSE ', ' || authors.first_name END AS author_name
    FROM authors INNER JOIN ind_authors ON ind_authors.id=authors.id
    WHERE ind_authors.author LIKE ?
    ORDER BY authors.last_name COLLATE utf8Collation
    LIMIT 100
EOT;

                break;

            case 'clipboard':

                $sql = <<<'EOT'
SELECT authors.id, authors.last_name || CASE authors.first_name WHEN '' THEN '' ELSE ', ' || authors.first_name END AS author_name
    FROM authors
    INNER JOIN ind_authors ON ind_authors.id=authors.id
    INNER JOIN items_authors ON authors.id=items_authors.author_id
    INNER JOIN clipboard ON items_authors.item_id=clipboard.item_id
    WHERE ind_authors.author LIKE ? AND clipboard.user_id = ?
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
SELECT authors.id, authors.last_name || CASE authors.first_name WHEN '' THEN '' ELSE ', ' || authors.first_name END AS author_name
    FROM authors
    INNER JOIN ind_authors ON ind_authors.id=authors.id
    INNER JOIN items_authors ON authors.id=items_authors.author_id
    INNER JOIN projects_items on items_authors.item_id = projects_items.item_id
    WHERE ind_authors.author LIKE ? AND projects_items.project_id = ?
    ORDER BY last_name COLLATE utf8Collation
    LIMIT 100
EOT;

                $columns[] = $project_id;

                break;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            $output[$row['id']] = $row['author_name'];
        }

        return $output;
    }
}
