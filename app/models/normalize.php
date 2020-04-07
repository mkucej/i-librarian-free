<?php

namespace LibrarianApp;

use Exception;
use PDO;

/**
 * Class NormalizeModel.
 *
 * @method void  edit(array $data)
 * @method array similarAuthors()
 * @method array similarEditors()
 * @method array similarTitles(string $type)
 */
class NormalizeModel extends AppModel {

    /**
     * @return array
     * @throws Exception
     */
    protected function _similarAuthors(): array {

        $pdo = $this->db_main->getPDO();
        $pdo->sqliteCreateFunction('metaphone', 'metaphone', 1);

        // Create temp table.
        $sql = <<<SQL
CREATE TEMPORARY TABLE temp_authors (
    id INTEGER PRIMARY KEY,
    first_name TEXT,
    last_name TEXT NOT NULL,
    sound TEXT NOT NULL
);
SQL;

        $this->db_main->run($sql);

        $sql = <<<SQL
INSERT INTO temp_authors
    (id, first_name, last_name, sound)
    SELECT ind_authors.id, authors.first_name, authors.last_name, metaphone(ind_authors.author)
        FROM ind_authors
        INNER JOIN authors ON ind_authors.rowid=authors.id;
SQL;

        $this->db_main->run($sql);

        $sql = <<<SQL
WITH cte AS (
    SELECT temp_authors.id, sound, count(*)
        FROM temp_authors
        GROUP BY sound
        HAVING sound != '' AND count(*) > 1
)
SELECT temp_authors.sound, temp_authors.id, temp_authors.first_name, temp_authors.last_name
    FROM temp_authors
    INNER JOIN cte ON cte.sound = temp_authors.sound
    ORDER BY temp_authors.sound;
SQL;

        $this->db_main->run($sql);
        $output = $this->db_main->getResultRows(PDO::FETCH_GROUP|PDO::FETCH_NUM);

        return $output;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function _similarEditors(): array {

        $pdo = $this->db_main->getPDO();
        $pdo->sqliteCreateFunction('metaphone', 'metaphone', 1);

        // Create temp table.
        $sql = <<<SQL
CREATE TEMPORARY TABLE temp_editors (
    id INTEGER PRIMARY KEY,
    first_name TEXT,
    last_name TEXT NOT NULL,
    sound TEXT NOT NULL
);
SQL;

        $this->db_main->run($sql);

        $sql = <<<SQL
INSERT INTO temp_editors
    (id, first_name, last_name, sound)
    SELECT ind_editors.rowid, editors.first_name, editors.last_name, metaphone(ind_editors.editor)
        FROM ind_editors
        INNER JOIN editors ON ind_editors.rowid=editors.id;
SQL;

        $this->db_main->run($sql);

        $sql = <<<SQL
WITH cte AS (
    SELECT temp_editors.id, sound, count(*)
        FROM temp_editors
        GROUP BY sound
        HAVING sound != '' AND count(*) > 1
)
SELECT temp_editors.sound, temp_editors.id, temp_editors.first_name, temp_editors.last_name
    FROM temp_editors
    INNER JOIN cte ON cte.sound = temp_editors.sound
    ORDER BY temp_editors.sound;
SQL;

        $this->db_main->run($sql);
        $output = $this->db_main->getResultRows(PDO::FETCH_GROUP|PDO::FETCH_NUM);

        return $output;
    }

    /**
     * @param string $type
     * @return array
     * @throws Exception
     */
    protected function _similarTitles(string $type): array {

        if (in_array($type, ['primary', 'secondary', 'tertiary']) === false) {

            throw new Exception('unknown publication type', 422);
        }

        $pdo = $this->db_main->getPDO();
        $pdo->sqliteCreateFunction('metaphone', 'metaphone', 1);

        // Create temp table.
        $sql = <<<SQL
CREATE TEMPORARY TABLE temp_{$type}_titles (
    id INTEGER PRIMARY KEY,
    {$type}_title TEXT NOT NULL,
    sound TEXT NOT NULL
);
SQL;

        $this->db_main->run($sql);

        $sql = <<<SQL
INSERT INTO temp_{$type}_titles
    (id, {$type}_title, sound)
    SELECT ind_{$type}_titles.rowid, {$type}_titles.{$type}_title, metaphone(ind_{$type}_titles.{$type}_title)
        FROM ind_{$type}_titles
        INNER JOIN {$type}_titles ON ind_{$type}_titles.rowid={$type}_titles.id;
SQL;

        $this->db_main->run($sql);

        $sql = <<<SQL
WITH cte AS (
    SELECT temp_{$type}_titles.id, sound, count(*)
        FROM temp_{$type}_titles
        GROUP BY sound
        HAVING sound != '' AND count(*) > 1
)
SELECT temp_{$type}_titles.sound, temp_{$type}_titles.id, temp_{$type}_titles.{$type}_title
    FROM temp_{$type}_titles
    INNER JOIN cte ON cte.sound = temp_{$type}_titles.sound
    ORDER BY temp_{$type}_titles.sound;
SQL;

        $this->db_main->run($sql);
        $output = $this->db_main->getResultRows(PDO::FETCH_GROUP|PDO::FETCH_NUM);

        return $output;
    }

    /**
     * Edit metadata, normalizer.
     *
     * @param array $data POST data.
     * @throws Exception
     */
    protected function _edit(array $data): void {

        switch (key($data)) {

            case 'author':

                $author_id = key($data['author']);
                $author = $data['author'][$author_id];

                // Select item ids where this author exists to rebuild index later.
                $sql = <<<SQL
SELECT item_id
    FROM items_authors
    WHERE author_id = ?
SQL;

                $this->db_main->run($sql, [$author_id]);
                $item_ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

                // Find out if the new name already exists.
                $sql = <<<SQL
SELECT id
    FROM authors
    WHERE last_name = ? AND first_name = ?
SQL;

                $columns = [
                    $author['last_name'],
                    $author['first_name']
                ];

                $this->db_main->run($sql, $columns);
                $existing_id = (int) $this->db_main->getResult();

                if ($existing_id > 0 && $existing_id !== $author_id) {

                    // Merge this author into existing one.
                    $sql = <<<SQL
UPDATE
    items_authors
    SET author_id = ?
    WHERE author_id = ?
SQL;

                    $columns = [
                        $existing_id,
                        $author_id
                    ];

                    $this->db_main->run($sql, $columns);

                    // Delete the old author.
                    $sql = <<<SQL
DELETE
    FROM authors
    WHERE id = ?
SQL;

                    $this->db_main->run($sql, [$author_id]);

                } else {

                    // It does not exist. Just rename the author.
                    $sql = <<<SQL
UPDATE authors
    SET last_name = ?, first_name = ?
    WHERE id = ?
SQL;
                    $columns = [
                        $author['last_name'],
                        $author['first_name'],
                        $author_id
                    ];

                    $this->db_main->run($sql, $columns);
                }

                $this->rebuildAuthorFts($item_ids);

                break;

            case 'editor':

                $editor_id = key($data['editor']);
                $editor = $data['editor'][$editor_id];

                // Select item ids where this editor exists to rebuild index later.
                $sql = <<<SQL
SELECT item_id
    FROM items_editors
    WHERE editor_id = ?
SQL;

                $this->db_main->run($sql, [$editor_id]);
                $item_ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

                // Find out if the new name already exists.
                $sql = <<<SQL
SELECT id
    FROM editors
    WHERE last_name = ? AND first_name = ?
SQL;

                $columns = [
                    $editor['last_name'],
                    $editor['first_name']
                ];

                $this->db_main->run($sql, $columns);
                $existing_id = (int) $this->db_main->getResult();

                if ($existing_id > 0 && $existing_id !== $editor_id) {

                    // Merge this editor into existing one.
                    $sql = <<<SQL
UPDATE
    items_editors
    SET editor_id = ?
    WHERE editor_id = ?
SQL;

                    $columns = [
                        $existing_id,
                        $editor_id
                    ];

                    $this->db_main->run($sql, $columns);

                    // Delete the old editor.
                    $sql = <<<SQL
DELETE
    FROM editors
    WHERE id = ?
SQL;

                    $this->db_main->run($sql, [$editor_id]);

                } else {

                    // It does not exist. Just rename the editor.
                    $sql = <<<SQL
UPDATE editors
    SET last_name = ?, first_name = ?
    WHERE id = ?
SQL;
                    $columns = [
                        $editor['last_name'],
                        $editor['first_name'],
                        $editor_id
                    ];

                    $this->db_main->run($sql, $columns);
                }

                $this->rebuildEditorFts($item_ids);

                break;

            case 'primary_title':
            case 'secondary_title':
            case 'tertiary_title':

                // Title type.
                switch (key($data)) {
                    case 'primary_title':
                        $type = 'primary';
                        break;
                    case 'secondary_title':
                        $type = 'secondary';
                        break;
                    case 'tertiary_title':
                        $type = 'tertiary';
                        break;
                }

                $title_id = key($data[$type . '_title']);
                $title = $data[$type . '_title'][$title_id];

                // Select item ids where this editor exists to rebuild index later.
                $sql = <<<SQL
SELECT id
    FROM items
    WHERE {$type}_title_id = ?
SQL;

                $this->db_main->run($sql, [$title_id]);
                $item_ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

                // Find out if a title with the new name already exists.
                $sql = <<<SQL
SELECT id
    FROM {$type}_titles
    WHERE {$type}_title = ?
SQL;

                $this->db_main->run($sql, [$title]);
                $existing_id = (int) $this->db_main->getResult();

                if ($existing_id > 0 && $existing_id !== $title_id) {

                    // It exists. Merge old title into this new one.
                    $sql = <<<SQL
UPDATE
    items
    SET {$type}_title_id = ?
    WHERE {$type}_title_id = ?
SQL;

                    $columns = [
                        $existing_id,
                        $title_id
                    ];

                    $this->db_main->run($sql, $columns);

                    // Delete the old title.
                    $sql = <<<SQL
DELETE
    FROM {$type}_titles
    WHERE id = ?
SQL;

                    $this->db_main->run($sql, [$title_id]);

                } else {

                    // It does not exist. Just rename the title.
                    $sql = <<<SQL
UPDATE {$type}_titles
    SET {$type}_title = ?
    WHERE id = ?
SQL;
                    $columns = [
                        $title,
                        $title_id
                    ];

                    $this->db_main->run($sql, $columns);
                }

                $this->rebuildPublicationTitleFts($type, $item_ids);

                break;

            default:
                throw new Exception('cannot edit this data type here.', 422);
        }
    }

    /**
     * @param array $item_ids
     * @throws Exception
     */
    private function rebuildAuthorFts(array $item_ids): void {

        $sql = <<<SQL
UPDATE ind_items
    SET authors_index = '     ' || (
        SELECT deaccent(group_concat(last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END, '     '), 0)
            FROM authors INNER JOIN items_authors ON authors.id = items_authors.author_id
            WHERE items_authors.item_id = ?
    ) || '     '
    WHERE id = ?
SQL;

        foreach ($item_ids as $item_id) {

            $columns = [
                $item_id,
                $item_id
            ];

            $this->db_main->run($sql, $columns);
        }
    }

    /**
     * @param array $item_ids
     * @throws Exception
     */
    private function rebuildEditorFts(array $item_ids): void {

        $sql = <<<SQL
UPDATE ind_items
    SET editors_index = '     ' || (
        SELECT deaccent(group_concat(last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END, '     '), 0)
            FROM editors INNER JOIN items_editors ON editors.id = items_editors.editor_id
            WHERE items_editors.item_id = ?
    ) || '     '
    WHERE id = ?
SQL;

        foreach ($item_ids as $item_id) {

            $columns = [
                $item_id,
                $item_id
            ];

            $this->db_main->run($sql, $columns);
        }
    }

    /**
     * @param string $type
     * @param array $item_ids
     * @throws Exception
     */
    private function rebuildPublicationTitleFts(string $type, array $item_ids): void {

        $sql = <<<SQL
UPDATE ind_items
    SET {$type}_title_index = '     ' || (
        SELECT deaccent({$type}_title, 0)
            FROM {$type}_titles
            INNER JOIN items ON items.{$type}_title_id = {$type}_titles.id
            WHERE items.id = ?
    ) || '     '
    WHERE id = ?
SQL;

        foreach ($item_ids as $item_id) {

            $columns = [
                $item_id,
                $item_id
            ];

            $this->db_main->run($sql, $columns);
        }
    }
}
