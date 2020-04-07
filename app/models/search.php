<?php

namespace LibrarianApp;

/**
 * Class SearchModel.
 *
 * This class does not perform searches, instead manages saved searches.
 *
 * @method void  delete(int $id)
 * @method array list(string $type)
 * @method void  save(string $type, string $name, string $url)
 */
final class SearchModel extends AppModel {

    /**
     * List searches of the type.
     *
     * @param string $type
     * @return array
     */
    protected function _list(string $type): array {

        $sql_select = <<<'SQL'
SELECT id, search_name, search_url, changed_time
    FROM searches
    WHERE user_id = ? AND search_type = ?
    ORDER BY changed_time DESC
SQL;

        $this->db_main->run($sql_select, [$this->user_id, $type]);
        $output = $this->db_main->getResultRows();

        return $output;
    }

    /**
     * Save new search.
     *
     * @param string $type
     * @param string $name
     * @param string $url
     */
    protected function _save(string $type, string $name, string $url): void {

        $this->db_main->beginTransaction();

        $sql_select = <<<'SQL'
SELECT id
    FROM searches
    WHERE user_id = ? AND search_url = ?
SQL;

        $this->db_main->run($sql_select, [$this->user_id, $url]);
        $id = (int) $this->db_main->getResult();

        if ($id > 0) {

            $sql_update = <<<'SQL'
UPDATE searches
    SET search_type = ?, search_name = ?, changed_time = CURRENT_TIMESTAMP
    WHERE id = ?
SQL;

            $this->db_main->run($sql_update, [$type, $name, $id]);

        } else {

            $sql_insert = <<<'SQL'
INSERT INTO searches
    (user_id, search_type, search_name, search_url)
    VALUES(?, ?, ?, ?)
SQL;

            $this->db_main->run($sql_insert, [$this->user_id, $type, $name, $url]);
        }

        $this->db_main->commit();
    }

    /**
     * Delete search.
     *
     * @param int $id
     */
    protected function _delete(int $id): void {

        $sql_delete = <<<'SQL'
DELETE
    FROM searches
    WHERE id = ? AND user_id = ?
SQL;

        $this->db_main->run($sql_delete, [$id, $this->user_id]);
    }
}
