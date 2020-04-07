<?php

namespace LibrarianApp;

use Exception;

/**
 * Class ItemdiscussionModel.
 *
 * @method array load(int $item_id)
 * @method void  save(array $message)
 */
class ItemdiscussionModel extends AppModel {

    /**
     * Load.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _load(int $item_id): array {

        $output = [];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Title.
        $sql = <<<EOT
SELECT
    title
    FROM items
    WHERE items.id = ?
EOT;

        $this->db_main->run($sql, [$item_id]);
        $output['title'] = $this->db_main->getResult();

        // Messages
        $sql = <<<EOT
SELECT
    ifnull(trim(first_name || ' ' || last_name), username) as name,
    item_discussions.message,
    item_discussions.added_time
    FROM item_discussions
    INNER JOIN users ON item_discussions.user_id=users.id
    WHERE item_discussions.item_id = ?
    ORDER BY item_discussions.added_time DESC
EOT;

        $this->db_main->run($sql, [$item_id]);
        $output['messages'] = $this->db_main->getResultRows();

        $this->db_main->commit();

        return $output;
    }

    /**
     * Save.
     *
     * @param array $message
     * @throws Exception
     */
    protected function _save(array $message): void {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($message['id']) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Item columns.
        $sql = <<<EOT
INSERT INTO item_discussions
    (item_id, user_id, message, added_time)
    VALUES(?, ?, ?, CURRENT_TIMESTAMP)
EOT;

        $columns = [
            $message['id'],
            $this->user_id,
            $message['message']
        ];

        $this->db_main->run($sql, $columns);

        $this->db_main->commit();
    }
}
