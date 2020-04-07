<?php

namespace LibrarianApp;

use Exception;
use PDO;

/**
 * Class TagsModel.
 *
 * @method void  createTag(array $tag_names, int $item_id = null)
 * @method void  deleteItemTag(int $item_id, int $tag_id)
 * @method void  deleteTag(int $tag_id))
 * @method array getItemTags(int $item_id)
 * @method array getTagCounts()
 * @method array getTags(string $collection, array $tag_ids = [], int $project_id = null)
 * @method void  renameTag(int $tag_id, string $tag_name)
 * @method void  saveItemTags(int $item_id, array $tag_ids)
 * @method array searchTags(string $query)
 */
class TagsModel extends AppModel {

    /**
     * Get tags for filter modal window.
     *
     * @param string $collection
     * @param array $tag_ids
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _getTags(string $collection, array $tag_ids = [], int $project_id = null): array {

        $tags = [];
        $tag_ids = array_slice($tag_ids, 0, 3);

        switch ($collection) {

            case 'library':

            // Select all.
            $sql0 = <<<'EOT'
SELECT id, tag
    FROM tags
    ORDER BY tag
EOT;

            // Filter with 1 tag_id.
            $sql1 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    WHERE item_id IN (
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
    ) AND tag_id != ?
    ORDER BY tag
EOT;

            // Filter with 2 tag_ids.
            $sql2 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    WHERE item_id IN (
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
        INTERSECT
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
    ) AND tag_id != ? AND tag_id != ?
    ORDER BY tag
EOT;

            // Filter with 3 tag_ids.
            $sql3 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    WHERE item_id IN (
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
        INTERSECT
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
        INTERSECT
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
    ) AND tag_id != ?  AND tag_id != ?  AND tag_id != ?
    ORDER BY tag
EOT;

                break;

            case 'clipboard':

                // Select all.
            $sql0 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    WHERE items_tags.item_id IN (
        SELECT item_id FROM clipboard WHERE user_id = ?
    )
    ORDER BY tag
EOT;

            // Filter with 1 tag_id.
            $sql1 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    WHERE item_id IN (
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
    ) AND tag_id != ? AND
    items_tags.item_id IN (
        SELECT item_id FROM clipboard WHERE user_id = ?
    )
    ORDER BY tag
EOT;

            // Filter with 2 tag_ids.
            $sql2 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    WHERE item_id IN (
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
        INTERSECT
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
    ) AND tag_id != ? AND tag_id != ? AND
    items_tags.item_id IN (
        SELECT item_id FROM clipboard WHERE user_id = ?
    )
    ORDER BY tag
EOT;

            // Filter with 3 tag_ids.
            $sql3 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    WHERE item_id IN (
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
        INTERSECT
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
        INTERSECT
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
    ) AND tag_id != ?  AND tag_id != ?  AND tag_id != ? AND
    items_tags.item_id IN (
        SELECT item_id FROM clipboard WHERE user_id = ?
    )
    ORDER BY tag
EOT;

                break;

            case 'project':

                if($this->verifyProject($project_id) === false) {

                    throw new Exception('you are not authorized to view this project', 403);
                }

                // Select all.
                $sql0 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    INNER JOIN projects_items on items_tags.item_id = projects_items.item_id
    WHERE projects_items.project_id = ?
    ORDER BY tag
EOT;

                // Filter with 1 tag_id.
                $sql1 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    INNER JOIN projects_items on items_tags.item_id = projects_items.item_id
    WHERE projects_items.item_id IN (
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
    ) AND tag_id != ? AND projects_items.project_id = ?
    ORDER BY tag
EOT;

                // Filter with 2 tag_ids.
                $sql2 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    INNER JOIN projects_items on items_tags.item_id = projects_items.item_id
    WHERE projects_items.item_id IN (
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
        INTERSECT
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
    ) AND tag_id != ? AND tag_id != ? AND projects_items.project_id = ?
    ORDER BY tag
EOT;

                // Filter with 3 tag_ids.
                $sql3 = <<<'EOT'
SELECT id, tag
    FROM tags
    INNER JOIN items_tags ON tags.id=items_tags.tag_id
    INNER JOIN projects_items on items_tags.item_id = projects_items.item_id
    WHERE projects_items.item_id IN (
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
        INTERSECT
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
        INTERSECT
        SELECT item_id
            FROM items_tags
            WHERE tag_id = ?
    ) AND tag_id != ?  AND tag_id != ?  AND tag_id != ? AND projects_items.project_id = ?
    ORDER BY tag
EOT;

                break;
        }

        switch (count($tag_ids)) {

            case 0:
                $sql = $sql0;
                $columns = [];
                break;

            case 1:
                $sql = $sql1;
                $columns = [$tag_ids[0], $tag_ids[0]];
                break;

            case 2:
                $sql = $sql2;
                $columns = [$tag_ids[0], $tag_ids[1], $tag_ids[0], $tag_ids[1]];
                break;

            case 3:
                $sql = $sql3;
                $columns = [$tag_ids[0], $tag_ids[1], $tag_ids[2], $tag_ids[0], $tag_ids[1], $tag_ids[2]];
                break;
        }

        // Add parameter for clipboard SQLs.
        if ($collection === 'clipboard') {

            $columns[] = $this->user_id;
        }

        if ($collection === 'project') {

            $columns[] = $project_id;
        }

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            $tags[$row['id']] = $row['tag'];
        }

        return $tags;
    }

    /**
     * Get tags for an item.
     *
     * @param integer $item_id
     * @return array
     * @throws Exception
     */
    protected function _getItemTags(int $item_id): array {

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
    title, abstract
    FROM items
    WHERE items.id = ?
EOT;

        $this->db_main->run($sql, [$item_id]);
        $row = $this->db_main->getResultRow();
        $output['title'] = $row['title'];
        $abstract = $row['abstract'];
        $row = null;

        // List of all tags.
        $sql = <<<'EOT'
SELECT id, tag
    FROM tags
    ORDER BY tag
EOT;

        $this->db_main->run($sql);
        $output['tags'] = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

        // List of item tags.
        $sql = <<<'EOT'
SELECT id, tag
    FROM tags
    LEFT JOIN items_tags ON tags.id=items_tags.tag_id
    WHERE items_tags.item_id = ?
    ORDER BY tag
EOT;

        $this->db_main->run($sql, [$item_id]);
        $output['item_tags'] = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

        // List of recommended tags.
        $columns = str_word_count($abstract, 1);
        $placeholder_arr = array_fill(0, count($columns), '?');
        $placeholders = join(',', $placeholder_arr);

        $sql = <<<EOT
SELECT id, tag
    FROM tags
    WHERE tag IN ($placeholders)
    ORDER BY tag
EOT;

        $this->db_main->run($sql, $columns);
        $output['recommended_tags'] = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

        $this->db_main->commit();

        return $output;
    }

    protected function _searchTags(string $query): array {

        $this->db_main->beginTransaction();

        $sql = <<<EOT
SELECT id, tag
    FROM tags
    WHERE tag LIKE ?
    ORDER BY tag COLLATE utf8Collation
EOT;

        $this->db_main->run($sql, ["{$query}%"]);
        $output = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

        $this->db_main->commit();

        return $output;
    }

    /**
     * Save tag for an item.
     *
     * @param int $item_id
     * @param array $tag_ids
     * @throws Exception
     */
    protected function _saveItemTags(int $item_id, array $tag_ids): void {

        $sql = <<<'EOT'
INSERT OR IGNORE INTO items_tags (item_id, tag_id) VALUES(?, ?)
EOT;

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        foreach ($tag_ids as $tag_id) {

            $this->db_main->run($sql, [$item_id, $tag_id]);
        }

        $this->db_main->commit();
    }

    /**
     * Delete item tag relation.
     *
     * @param int $item_id
     * @param int $tag_id
     * @throws Exception
     */
    protected function _deleteItemTag(int $item_id, int $tag_id): void {

        $sql = <<<'EOT'
DELETE FROM items_tags WHERE item_id = ? AND tag_id = ?
EOT;

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->run($sql, [$item_id, $tag_id]);

        $this->db_main->commit();
    }

    /**
     * Delete tag.
     *
     * @param int $tag_id
     * @throws Exception
     */
    protected function _deleteTag(int $tag_id): void {

        $sql = <<<'EOT'
DELETE FROM tags WHERE id = ?
EOT;

        $this->db_main->run($sql, [$tag_id]);
    }

    /**
     * Rename tag.
     *
     * @param int $tag_id
     * @param string $tag_name
     * @throws Exception
     */
    protected function _renameTag(int $tag_id, string $tag_name): void {

        // Find out if a tag with the new name already exists.
        $sql = <<<SQL
SELECT id
    FROM tags
    WHERE tag = ?
SQL;

        $this->db_main->run($sql, [$tag_name]);
        $existing_id = (int) $this->db_main->getResult();

        if ($existing_id > 0 && $existing_id !== $tag_id) {

            // It exists. Merge old tag into this existing one.
            $sql = <<<SQL
UPDATE
    items_tags
    SET tag_id = ?
    WHERE tag_id = ?
SQL;

            $columns = [
                $existing_id,
                $tag_id
            ];

            $this->db_main->run($sql, $columns);

            // Delete the old tag.
            $sql = <<<SQL
DELETE
    FROM tags
    WHERE id = ?
SQL;

            $this->db_main->run($sql, [$tag_id]);

        } else {

            // It does not exist. Just rename the title.
            $sql = <<<SQL
UPDATE tags
    SET tag = ?
    WHERE id = ?
SQL;
            $columns = [
                $tag_name,
                $tag_id
            ];

            $this->db_main->run($sql, $columns);
        }
    }

    /**
     * Create new tag, optionally add it to item.
     *
     * @param array $tag_names
     * @param int|null $item_id
     * @throws Exception
     */
    protected function _createTag(array $tag_names, int $item_id = null): void {

        $this->db_main->beginTransaction();

        // Insert new tag. Ignore if duplicate.
        $sql1 = <<<'EOT'
INSERT OR IGNORE INTO tags (tag) VALUES(?)
EOT;

        foreach ($tag_names as $tag_name) {

            if (empty($tag_name)) {

                continue;
            }

            $this->db_main->run($sql1, [$tag_name]);
            $tag_id = $this->db_main->lastInsertId();

            // Add new tag to an item.
            if (isset($item_id) && !empty($tag_id)) {

                $sql2 = <<<'EOT'
INSERT OR IGNORE INTO items_tags (item_id, tag_id) VALUES(?, ?)
EOT;

                $this->db_main->run($sql2, [$item_id, $tag_id]);
            }
        }

        $this->db_main->commit();
    }

    /**
     * Get all tags and item counts.
     *
     * @return array
     * @throws Exception
     */
    protected function _getTagCounts(): array {

        $sql = <<<'EOT'
SELECT tags.id, tags.tag, items_tags.item_id, count(*) as count
    FROM tags
    LEFT OUTER JOIN items_tags ON tags.id = items_tags.tag_id
    GROUP BY tags.tag
    ORDER BY tags.tag
    COLLATE utf8Collation
EOT;

        $this->db_main->run($sql);
        $tags = $this->db_main->getResultRows();

        return $tags;
    }
}
