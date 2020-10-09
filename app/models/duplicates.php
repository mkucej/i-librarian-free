<?php

namespace LibrarianApp;

use Exception;
use PDO;
use \Librarian\Http\Client\Psr7;

/**
 * Class DuplicatesModel.
 *
 * @method array identical()
 * @method void  merge(array $item_ids)
 * @method array pdfs()
 * @method array similar()
 */
class DuplicatesModel extends AppModel {

    /**
     * Compare titles using metaphone.
     *
     * @return array
     * @throws Exception
     */
    protected function _similar(): array {

        $this->db_main->beginTransaction();

        $pdo = $this->db_main->getPDO();
        $pdo->sqliteCreateFunction('metaphone', 'metaphone', 1);

        // Create temp table of titles.
        $sql = <<<SQL
CREATE TEMPORARY TABLE temp_titles (
    id INTEGER PRIMARY KEY,
    title TEXT NOT NULL,
    sound TEXT NOT NULL,
    file_hash TEXT
);
SQL;

        $this->db_main->run($sql);

        $sql = <<<SQL
INSERT INTO temp_titles
    (id, title, sound, file_hash)
    SELECT items.id, items.title, metaphone(deaccent(items.title)), items.file_hash
        FROM items
SQL;

        $this->db_main->run($sql);

        $sql = <<<SQL
WITH cte AS (
    SELECT id, sound, count(*)
        FROM temp_titles
        GROUP BY sound
        HAVING sound != '' AND count(*) > 1
)
SELECT temp_titles.sound, temp_titles.id, temp_titles.title, temp_titles.file_hash
    FROM temp_titles
    INNER JOIN cte ON cte.sound = temp_titles.sound
    ORDER BY temp_titles.sound;
SQL;

        $this->db_main->run($sql);
        $output = $this->db_main->getResultRows(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        $this->db_main->commit();

        return $output;
    }

    /**
     * Find identical titles.
     *
     * @return array
     * @throws Exception
     */
    protected function _identical(): array {

        $this->db_main->beginTransaction();

        $sql = <<<SQL
WITH cte AS (
    SELECT id, title, count(*)
        FROM items
        GROUP BY title
        HAVING count(*) > 1
)
SELECT items.title, items.id, items.file_hash
    FROM items
    INNER JOIN cte ON cte.title = items.title
    ORDER BY items.title;
SQL;

        $this->db_main->run($sql);
        $output = $this->db_main->getResultRows(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        $this->db_main->commit();

        return $output;
    }

    /**
     * Compare PDF hashes.
     *
     * @return array
     * @throws Exception
     */
    protected function _pdfs(): array {

        // Make sure all PDFs have hashes.

        $sql_select_nohash = <<<'SQL'
SELECT id
    FROM items
    WHERE file_hash IS NULL
SQL;

        $sql_update_nohash = <<<'EOT'
UPDATE items
    SET file_hash = ?
    WHERE id = ?
EOT;

        $this->db_main->run($sql_select_nohash);
        $empty_ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        foreach ($empty_ids as $empty_id) {

            // PDF exists?
            if ($this->isPdf($empty_id) === true) {

                $filepath = $this->idToPdfPath($empty_id);

                $pdf_stream = $this->readFile($filepath);
                $pdf_hash = Psr7\hash($pdf_stream, 'md5');

                $columns_update = [
                    $pdf_hash,
                    (integer) $empty_id
                ];

                $this->db_main->run($sql_update_nohash, $columns_update);
            }
        }

        $this->db_main->beginTransaction();

        $sql = <<<SQL
WITH cte AS (
    SELECT id, file_hash, count(*)
        FROM items
        GROUP BY file_hash
        HAVING count(*) > 1
)
SELECT items.file_hash, items.id, items.title
    FROM items
    INNER JOIN cte ON cte.file_hash = items.file_hash
    ORDER BY items.file_hash;
SQL;

        $this->db_main->run($sql);
        $duplicates = $this->db_main->getResultRows(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        $this->db_main->commit();

        return $duplicates;
    }

    /**
     * Attempt to merge duplicates.
     *
     * @param array $item_ids
     * @throws Exception
     */
    protected function _merge(array $item_ids): void {

        // Shift the smallest item id.
        sort($item_ids);
        $first_id = array_shift($item_ids);

        // Merge tags.
        $sql_tag = <<<SQL
UPDATE OR IGNORE items_tags
    SET item_id = ?
    WHERE item_id = ?
SQL;

        // Merge PDF notes.
        $sql_annot = <<<SQL
UPDATE OR IGNORE annotations
    SET item_id = ?
    WHERE item_id = ?
SQL;

        // Merge highlights.
        $sql_marker = <<<SQL
UPDATE OR IGNORE markers
    SET item_id = ?
    WHERE item_id = ?
SQL;

        // Merge discussion.
        $sql_discuss = <<<SQL
UPDATE item_discussions
    SET item_id = ?
    WHERE item_id = ?
SQL;

        $this->db_main->beginTransaction();

        // Item tags, PDF notes, highlights, discussion.
        foreach ($item_ids as $item_id) {

            $columns = [
                $first_id,
                $item_id
            ];

            $this->db_main->run($sql_tag, $columns);
            $this->db_main->run($sql_annot, $columns);
            $this->db_main->run($sql_marker, $columns);
            $this->db_main->run($sql_discuss, $columns);
        }

        // Merge notes.
        $note_placeholders = array_fill(0, count($item_ids), '?');
        $note_placeholder = join(', ', $note_placeholders);

        $sql_note_get = <<<SQL
SELECT item_id, user_id, note
    FROM item_notes
    WHERE item_id IN ({$note_placeholder})
    ORDER BY item_id
SQL;

        $sql_note_exists = <<<SQL
SELECT id
    FROM item_notes
    WHERE item_id = ? AND user_id = ?
SQL;

        $sql_note_insert = <<<SQL
INSERT INTO item_notes
    (user_id, item_id, note, changed_time) 
    VALUES (?, ?, '', CURRENT_TIMESTAMP)
SQL;

        $sql_note_update = <<<SQL
UPDATE item_notes
    SET note = note || ' ' || ?
    WHERE item_id = ? AND user_id = ?
SQL;

        // Get notes to be merged.
        $this->db_main->run($sql_note_get, $item_ids);

        while ($row = $this->db_main->getResultRow()) {

            // Does a note exist for the first id?
            $columns = [
                $first_id,
                $row['user_id']
            ];

            $this->db_main->run($sql_note_exists, $columns);
            $note_id = $this->db_main->getResult();

            if (empty($note_id)) {

                // Create empty note, if does not exist.
                $columns = [
                    $row['user_id'],
                    $first_id
                ];

                $this->db_main->run($sql_note_insert, $columns);

            }

            // Merge notes.
            $columns = [
                $row['note'],
                $first_id,
                $row['user_id']
            ];

            $this->db_main->run($sql_note_update, $columns);
        }

        $this->db_main->commit();

        // Merge supplements.
        foreach ($item_ids as $item_id) {

            // List supplementary files.
            $filepath = $this->idToSupplementPath($item_id);
            $files = glob($filepath . "*");

            // Rename.
            foreach ($files as $file) {

                $basename = substr(basename($file), 9);
                $newfilepath = $this->idToSupplementPath($first_id) . $basename;

                rename($file, $newfilepath);
            }
        }
    }
}
