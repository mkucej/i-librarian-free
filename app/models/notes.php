<?php

namespace LibrarianApp;

use Exception;

/**
 * Class NotesModel.
 *
 * @method array readAll(int $item_id)
 * @method array readUser(int $item_id)
 * @method void  save(int $item_id, string $note)
 */
class NotesModel extends AppModel {

    /**
     * Read all.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _readAll(int $item_id): array {

        $output = [
            'others' => [],
            'user' => []
        ];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Select title.
        $sql = <<<'EOT'
SELECT title
    FROM items
    WHERE id = ?
EOT;

        $columns = [
            $item_id
        ];

        $this->db_main->run($sql, $columns);
        $output['title'] = $this->db_main->getResult();

        // Other notes
        $sql = <<<'EOT'
SELECT ifnull(trim(first_name || ' ' || last_name), username) as name, item_notes.note, item_notes.changed_time
    FROM item_notes
    INNER JOIN users ON users.id=item_notes.user_id
    WHERE item_notes.item_id = ? AND users.id != ?
EOT;

        $columns = [
            $item_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
        $output['others'] = $this->db_main->getResultRows();

        $sql = <<<'EOT'
SELECT item_notes.note, item_notes.changed_time
    FROM item_notes
    INNER JOIN users ON users.id=item_notes.user_id
    WHERE item_notes.item_id = ? AND users.id = ?
EOT;

        $columns = [
            $item_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
        $row = $this->db_main->getResultRow();

        if (!empty($row)) {

            $output['user'] = $row;
        }

        $this->db_main->commit();

        return $output;
    }

    /**
     * Read user notes.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _readUser(int $item_id): array {

        $output = [
            'user' => [
                'note'         => null,
                'changed_time' => null
            ]
        ];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Select title.
        $sql = <<<'EOT'
SELECT title
    FROM items
    WHERE id = ?
EOT;

        $columns = [
            $item_id
        ];

        $this->db_main->run($sql, $columns);
        $output['title'] = $this->db_main->getResult();

        $sql = <<<'EOT'
SELECT item_notes.note, item_notes.changed_time
    FROM item_notes
    INNER JOIN users ON users.id=item_notes.user_id
    WHERE item_notes.item_id = ? AND users.id = ?
EOT;

        $columns = [
            $item_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
        $row = $this->db_main->getResultRow();

        if (!empty($row)) {

            $output['user'] = $row;
        }

        $this->db_main->commit();

        return $output;
    }

    /**
     * Save.
     *
     * @param int $item_id
     * @param string $note
     * @throws Exception
     */
    protected function _save(int $item_id, string $note): void {

        $purifier = $this->di->getShared('HtmlPurifier');
        $note_sanitized = $purifier->purify($note);
        $note_sanitized = $this->sanitation->emptyToNull($note_sanitized);

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $sql = <<<'EOT'
SELECT id
    FROM item_notes
    WHERE item_id = ? AND user_id = ?
EOT;

        $columns = [
            $item_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
        $note_id = $this->db_main->getResult();

        if ($note_sanitized === null) {

            $sql = <<<'EOT'
DELETE FROM item_notes
    WHERE item_id = ? AND user_id = ?
EOT;

            $columns = [
                $item_id,
                $this->user_id
            ];

        } elseif (empty($note_id)) {

            $sql = <<<'EOT'
INSERT INTO item_notes
    (item_id, user_id, note, changed_time)
    VALUES(?, ?, ?, CURRENT_TIMESTAMP)
EOT;

            $columns = [
                $item_id,
                $this->user_id,
                $note_sanitized
            ];

        } else {

            $sql = <<<'EOT'
UPDATE item_notes
    SET note = ?, changed_time = CURRENT_TIMESTAMP
    WHERE id = ?
EOT;

            $columns = [
                $note_sanitized,
                $note_id
            ];
        }

        $this->db_main->run($sql, $columns);

        $this->db_main->commit();
    }
}
