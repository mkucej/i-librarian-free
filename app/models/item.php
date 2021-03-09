<?php

namespace LibrarianApp;

use Exception;
use Librarian\Cache\FileCache;
use Librarian\Http\Client;
use Librarian\Import\Bibtex;
use Librarian\Import\Endnote;
use Librarian\Import\Ris;
use Librarian\ItemMeta;
use Librarian\Media\Pdf;
use Librarian\Media\ScalarUtils;
use PDO;

/**
 * Class ItemModel.
 *
 * @method void  delete(int|array $item_id)
 * @method array get(int $item_id)
 * @method array importText(array $data)
 * @method array save(array $item)
 * @method array uidsExist(array $items)
 * @method void  update(array $item)
 */
class ItemModel extends AppModel {

    /**
     * @var FileCache
     */
    private $cache;

    /**
     * @var Bibtex|Endnote|Ris
     */
    private $parser;

    /**
     * @var Pdf
     */
    private $pdf_obj;

    /**
     * @var ScalarUtils
     */
    private $scalar_utils;

    /**
     * Get.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _get(int $item_id): array {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Item columns.
        $sql = <<<EOT
SELECT
    items.id,
    items.title,
    items.abstract,
    items.publication_date,
    items.reference_type,
    items.affiliation,
    items.urls,
    items.volume,
    items.issue,
    items.pages,
    items.publisher,
    items.place_published,
    items.custom1,
    items.custom2,
    items.custom3,
    items.custom4,
    items.custom5,
    items.custom6,
    items.custom7,
    items.custom8,
    items.bibtex_id,
    items.bibtex_type,
    primary_titles.primary_title,
    secondary_titles.secondary_title,
    tertiary_titles.tertiary_title
    FROM items
    LEFT JOIN primary_titles ON primary_titles.id=items.primary_title_id
    LEFT JOIN secondary_titles ON secondary_titles.id=items.secondary_title_id
    LEFT JOIN tertiary_titles ON tertiary_titles.id=items.tertiary_title_id
    WHERE items.id=?
EOT;

        $this->db_main->run($sql, [$item_id]);
        $output = $this->db_main->getResultRow();

        // Edit URLs.
        $output['urls'] = explode('|', $output['urls']);

        // UIDs.
        $sql_uids = <<<EOT
SELECT
    uid_type, uid
    FROM uids
    WHERE item_id = ?
EOT;

        $this->db_main->run($sql_uids, [$item_id]);

        while ($uids = $this->db_main->getResultRow()) {

            $output[ItemMeta::COLUMN['UID_TYPES']][] = $uids['uid_type'];
            $output[ItemMeta::COLUMN['UIDS']][] = $uids['uid'];
        }

        // Authors.
        $sql = <<<EOT
SELECT
    last_name, first_name
    FROM authors
    INNER JOIN items_authors ON items_authors.author_id=authors.id
    WHERE items_authors.item_id=?
    ORDER by items_authors.position
EOT;

        $this->db_main->run($sql, [$item_id]);

        while ($row = $this->db_main->getResultRow()) {

            $output[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = $row['last_name'];
            $output[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = $row['first_name'];
        }

        // Editors.
        $sql = <<<EOT
SELECT
    last_name, first_name
    FROM editors
    INNER JOIN items_editors ON items_editors.editor_id=editors.id
    WHERE items_editors.item_id=?
    ORDER by items_editors.position
EOT;

        $this->db_main->run($sql, [$item_id]);

        while ($row = $this->db_main->getResultRow()) {

            $output[ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = $row['last_name'];
            $output[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = $row['first_name'];
        }

        // Keywords.
        $sql = <<<EOT
SELECT
    keyword
    FROM keywords
    INNER JOIN items_keywords ON items_keywords.keyword_id=keywords.id
    WHERE items_keywords.item_id=?
    ORDER BY keyword COLLATE utf8Collation
EOT;

        $this->db_main->run($sql, [$item_id]);
        $output['keywords'] = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        $this->db_main->commit();

        return $output;
    }

    /**
     * Save item to database. PDF is saved in a separate model.
     *
     * @param  array $item
     * @return array Item id as $output['item_id'].
     * @throws Exception
     */
    protected function _save(array $item): array {

        $output = [];

        if (isset($item[ItemMeta::COLUMN['TITLE']]) === false) {

            throw new Exception('item title is required', 400);
        }

        // Publication titles.
        $sql_select_primary = <<<EOT
SELECT id
    FROM primary_titles
    WHERE primary_title = ?
EOT;

        $sql_insert_primary = <<<EOT
INSERT INTO primary_titles
    (primary_title)
    VALUES(?)
EOT;

        $sql_select_secondary = <<<EOT
SELECT id
    FROM secondary_titles
    WHERE secondary_title = ?
EOT;

        $sql_insert_secondary = <<<EOT
INSERT INTO secondary_titles
    (secondary_title)
    VALUES(?)
EOT;

        $sql_select_tertiary = <<<EOT
SELECT id
    FROM tertiary_titles
    WHERE tertiary_title = ?
EOT;

        $sql_insert_tertiary = <<<EOT
INSERT INTO tertiary_titles
    (tertiary_title)
    VALUES(?)
EOT;

        // Insert item.
        $sql_item = <<<EOT
INSERT INTO items
    (
        title,
        primary_title_id,
        secondary_title_id,
        tertiary_title_id,
        publication_date,
        volume,
        issue,
        pages,
        abstract,
        affiliation,
        publisher,
        place_published,
        reference_type,
        bibtex_type,
        urls,
        custom1,
        custom2,
        custom3,
        custom4,
        custom5,
        custom6,
        custom7,
        custom8,
        private,
        added_by,
        changed_by,
        added_time,
        changed_time
    )
    VALUES(
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP
    )
EOT;

        // Convert empty strings to nulls. Prevents unique indexes from throwing errors.
        $item = $this->sanitation->emptyToNull($item);

        $this->db_main->beginTransaction();

        // Publication titles.
        $primary_title_id = null;

        if (!empty($item[ItemMeta::COLUMN['PRIMARY_TITLE']])) {

            $this->db_main->run($sql_select_primary, [$item[ItemMeta::COLUMN['PRIMARY_TITLE']]]);
            $primary_title_id = $this->db_main->getResult();

            if(empty($primary_title_id)) {

                $this->db_main->run($sql_insert_primary, [$item[ItemMeta::COLUMN['PRIMARY_TITLE']]]);
                $primary_title_id = $this->db_main->lastInsertId();
            }
        }

        $secondary_title_id = null;

        if (!empty($item[ItemMeta::COLUMN['SECONDARY_TITLE']])) {

            $this->db_main->run($sql_select_secondary, [$item[ItemMeta::COLUMN['SECONDARY_TITLE']]]);
            $secondary_title_id = $this->db_main->getResult();

            if(empty($secondary_title_id)) {

                $this->db_main->run($sql_insert_secondary, [$item[ItemMeta::COLUMN['SECONDARY_TITLE']]]);
                $secondary_title_id = $this->db_main->lastInsertId();
            }
        }

        $tertiary_title_id = null;

        if (!empty($item[ItemMeta::COLUMN['TERTIARY_TITLE']])) {

            $this->db_main->run($sql_select_tertiary, [$item[ItemMeta::COLUMN['TERTIARY_TITLE']]]);
            $tertiary_title_id = $this->db_main->getResult();

            if(empty($tertiary_title_id)) {

                $this->db_main->run($sql_insert_tertiary, [$item[ItemMeta::COLUMN['TERTIARY_TITLE']]]);
                $tertiary_title_id = $this->db_main->lastInsertId();
            }
        }

        // Publication date.
        $publication_date = null;

        if (!empty($item[ItemMeta::COLUMN['PUBLICATION_DATE']])) {

            if (preg_match('/^\d{4}$/', $item[ItemMeta::COLUMN['PUBLICATION_DATE']]) === 1) {

                $publication_date = $item[ItemMeta::COLUMN['PUBLICATION_DATE']] . '-01-01';

            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $item[ItemMeta::COLUMN['PUBLICATION_DATE']]) === 1) {

                $publication_date = $item[ItemMeta::COLUMN['PUBLICATION_DATE']];
            }
        }

        // Reference types.
        $reference_type = ItemMeta::TYPE['ARTICLE'];

        if (isset($item[ItemMeta::COLUMN['REFERENCE_TYPE']])
                && in_array($item[ItemMeta::COLUMN['REFERENCE_TYPE']], ItemMeta::TYPE) === true) {

            $reference_type = $item[ItemMeta::COLUMN['REFERENCE_TYPE']];
        }

        $bibtex_type = ItemMeta::TYPE['ARTICLE'];

        if (isset($item[ItemMeta::COLUMN['BIBTEX_TYPE']])
                && in_array($item[ItemMeta::COLUMN['BIBTEX_TYPE']], ItemMeta::BIBTEX_TYPE) === true) {

            $bibtex_type = $item[ItemMeta::COLUMN['BIBTEX_TYPE']];
        }

        $columns[] = $item[ItemMeta::COLUMN['TITLE']];
        $columns[] = $primary_title_id;
        $columns[] = $secondary_title_id;
        $columns[] = $tertiary_title_id;
        $columns[] = $publication_date;
        $columns[] = $item[ItemMeta::COLUMN['VOLUME']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['ISSUE']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['PAGES']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['ABSTRACT']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['AFFILIATION']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['PUBLISHER']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['PLACE_PUBLISHED']] ?? null;
        $columns[] = $reference_type;
        $columns[] = $bibtex_type;

        // URLs.
        if (isset($item[ItemMeta::COLUMN['URLS']]) && is_string($item[ItemMeta::COLUMN['URLS']])) {

            $columns[] = str_replace("\n", '|', $item[ItemMeta::COLUMN['URLS']]);

        } elseif (isset($item[ItemMeta::COLUMN['URLS']]) && is_array($item[ItemMeta::COLUMN['URLS']])) {

            $columns[] = join('|', $item[ItemMeta::COLUMN['URLS']]);

        } else {

            $columns[] = null;
        }

        $columns[] = $item[ItemMeta::COLUMN['CUSTOM1']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM2']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM3']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM4']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM5']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM6']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM7']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM8']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['PRIVATE']] ?? 'N';
        $columns[] = $this->user_id;
        $columns[] = $this->user_id;

        // Insert item.
        $this->db_main->run($sql_item, $columns);
        $last_id = $this->db_main->lastInsertId();
        $output['item_id'] = $last_id;
        $item['id'] = $last_id;

        // UIDs.
        $sql_uid = <<<SQL
INSERT INTO uids
    (uid_type, uid, item_id)
    VALUES (?, ?, ?)
SQL;

        if (isset($item[ItemMeta::COLUMN['UIDS']])) {

            foreach ($item[ItemMeta::COLUMN['UIDS']] as $i => $uid) {

                // Ignore if no UID type set.
                if (empty($item[ItemMeta::COLUMN['UID_TYPES']][$i])) {

                    continue;
                }

                if (empty($uid)) {

                    continue;
                }

                $columns_uid = [
                    $item[ItemMeta::COLUMN['UID_TYPES']][$i],
                    $uid,
                    $last_id
                ];

                $this->db_main->run($sql_uid, $columns_uid);
            }
        }

        $bibtex_id = $item[ItemMeta::COLUMN['BIBTEX_ID']] ?? null;

        if (empty($bibtex_id)) {

            // Bibtex ID.
            $sql_bibtex_fromat = <<<SQL
SELECT setting_value
    FROM settings
    WHERE setting_name = 'custom_bibtex'
SQL;

            $this->db_main->run($sql_bibtex_fromat);
            $format_json = $this->db_main->getResult();

            if (empty($format_json)) {

                $format = $this->app_settings->default_global_settings['custom_bibtex'];

            } else {

                $format = Client\Utils::jsonDecode($format_json, true);
            }

            $this->scalar_utils = $this->di->getShared('ScalarUtils');
            $bibtex_id = $this->scalar_utils->customBibtexId($format, $item);
        }

        $sql_bibtex_id = <<<EOT
UPDATE items
    SET bibtex_id = ?
    WHERE id = ?
EOT;

        $this->db_main->run($sql_bibtex_id, [$bibtex_id, $last_id]);

        // Authors.
        $author_select = <<<EOT
SELECT id
    FROM authors
    WHERE last_name = ? AND first_name = ?
EOT;

        $author_insert = <<<EOT
INSERT INTO authors
    (last_name, first_name)
    VALUES(?, ?)
EOT;

        $author_relation_insert = <<<EOT
INSERT INTO items_authors
    (item_id, author_id, position)
    VALUES(?, ?, ?)
EOT;

        if (isset($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']]) === true) {

            $author_count = count($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']]);

            for ($i = 0, $position = 1; $i < $author_count; $i++, $position++) {

                if (empty($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][$i])) {

                    $position--;
                    continue;
                }

                // Get author id.
                $columns = [
                    $item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][$i],
                    $item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$i] ?? ''
                ];

                $this->db_main->run($author_select, $columns);
                $id = $this->db_main->getResult();

                if (empty($id)) {

                    $this->db_main->run($author_insert, $columns);
                    $id = $this->db_main->lastInsertId();
                }

                $columns = [
                    $last_id,
                    $id,
                    $position
                ];

                // Update items_authors.
                $this->db_main->run($author_relation_insert, $columns);
            }
        }

        // Editors.
        $editor_select = <<<EOT
SELECT id
    FROM editors
    WHERE last_name = ? AND first_name = ?
EOT;

        $editor_insert = <<<EOT
INSERT INTO editors
    (last_name, first_name)
    VALUES(?, ?)
EOT;

        $editor_relation_insert = <<<EOT
INSERT INTO items_editors
    (item_id, editor_id, position)
    VALUES(?, ?, ?)
EOT;

        if (isset($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']]) === true) {

            $editor_count = count($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']]);

            for ($i = 0, $position = 1; $i < $editor_count; $i++, $position++) {

                if (empty($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][$i])) {

                    $position--;
                    continue;
                }

                // Get editor id.
                $columns = [
                    $item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][$i],
                    $item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$i] ?? ''
                ];

                $this->db_main->run($editor_select, $columns);
                $id = $this->db_main->getResult();

                if (empty($id)) {

                    $this->db_main->run($editor_insert, $columns);
                    $id = $this->db_main->lastInsertId();
                }

                $columns = [
                    $last_id,
                    $id,
                    $position
                ];

                // Update items_editors.
                $this->db_main->run($editor_relation_insert, $columns);
            }
        }

        // Keywords.
        $keywords = [];

        if (isset($item[ItemMeta::COLUMN['KEYWORDS']]) && is_string($item[ItemMeta::COLUMN['KEYWORDS']])) {

            $keywords = array_unique(array_filter(explode("\n", $item[ItemMeta::COLUMN['KEYWORDS']])));

        } elseif (isset($item[ItemMeta::COLUMN['KEYWORDS']]) && is_array($item[ItemMeta::COLUMN['KEYWORDS']])) {

            $keywords = array_unique(array_filter($item[ItemMeta::COLUMN['KEYWORDS']]));
        }

        $keyword_select = <<<EOT
SELECT id
    FROM keywords
    WHERE keyword = ?
EOT;

        $keyword_insert = <<<EOT
INSERT INTO keywords
    (keyword)
    VALUES(?)
EOT;

        $keyword_relation_insert = <<<EOT
INSERT INTO items_keywords
    (item_id, keyword_id)
    VALUES(?, ?)
EOT;

        foreach ($keywords as $keyword) {

            // Get editor id.
            $columns = [
                $keyword
            ];

            $this->db_main->run($keyword_select, $columns);
            $id = $this->db_main->getResult();

            if (empty($id)) {

                $this->db_main->run($keyword_insert, $columns);
                $id = $this->db_main->lastInsertId();
            }

            $columns = [
                $last_id,
                $id
            ];

            $this->db_main->run($keyword_relation_insert, $columns);
        }

        // Save to clipboard.
        if (isset($item['clipboard'])) {

            $clipboard_sql = <<<EOT
INSERT INTO clipboard
    (user_id, item_id)
    VALUES(?, ?)
EOT;

            $columns = [
                $this->user_id,
                $last_id
            ];

            $this->db_main->run($clipboard_sql, $columns);
        }

        // Save to projects.
        if (!empty(isset($item['projects']))) {

            $project_sql = <<<EOT
INSERT INTO projects_items
    (project_id, item_id)
    VALUES(?, ?)
EOT;

            foreach ($item['projects'] as $id) {

                // Authorize project.
                if ($this->verifyProject($id) === false) {

                    $this->db_main->rollBack();
                    throw new Exception('you are not authorized to access this project', 403);
                }

                $columns = [
                    $id,
                    $last_id
                ];

                $this->db_main->run($project_sql, $columns);
            }
        }

        // Add new tags.
        if (!empty($item['new_tags'])) {

            $tag_array = explode("\n", $item['new_tags']);

            $tag_sql = <<<EOT
INSERT OR IGNORE INTO tags (tag) VALUES(?)
EOT;

            foreach ($tag_array as $tag) {

                $columns = [
                    $tag
                ];

                $this->db_main->run($tag_sql, $columns);

                // Add new tag id to the item.
                $new_tag_id = $this->db_main->lastInsertId();

                if ($new_tag_id > 0) {

                    $item['tags'][] = $new_tag_id;
                }
            }
        }

        // Add existing tags.
        if (!empty($item['tags'])) {

            $tag_sql = <<<EOT
INSERT INTO items_tags
    (item_id, tag_id)
    VALUES(?, ?)
EOT;

            foreach ($item['tags'] as $id) {

                $columns = [
                    $last_id,
                    $id
                ];

                $this->db_main->run($tag_sql, $columns);
            }
        }

        $this->db_main->commit();

        return $output;
    }

    /**
     * Update an item in the database.
     *
     * @param  array $item
     * @throws Exception
     */
    protected function _update(array $item): void {

        if (isset($item[ItemMeta::COLUMN['TITLE']]) === false) {

            throw new Exception('item title is required', 400);
        }

        $this->scalar_utils = $this->di->getShared('ScalarUtils');

        // Publication titles.
        $sql_select_primary = <<<EOT
SELECT id
    FROM primary_titles
    WHERE primary_title = ?
EOT;

        $sql_insert_primary = <<<EOT
INSERT INTO primary_titles
    (primary_title)
    VALUES(?)
EOT;

        $sql_select_secondary = <<<EOT
SELECT id
    FROM secondary_titles
    WHERE secondary_title = ?
EOT;

        $sql_insert_secondary = <<<EOT
INSERT INTO secondary_titles
    (secondary_title)
    VALUES(?)
EOT;

        $sql_select_tertiary = <<<EOT
SELECT id
    FROM tertiary_titles
    WHERE tertiary_title = ?
EOT;

        $sql_insert_tertiary = <<<EOT
INSERT INTO tertiary_titles
    (tertiary_title)
    VALUES(?)
EOT;

        // Update item.
        $sql_item = <<<EOT
UPDATE items
    SET title = ?,
        primary_title_id = ?,
        secondary_title_id = ?,
        tertiary_title_id = ?,
        publication_date = ?,
        volume = ?,
        issue = ?,
        pages = ?,
        abstract = ?,
        affiliation = ?,
        publisher = ?,
        place_published = ?,
        reference_type = ?,
        bibtex_type = ?,
        urls = ?,
        custom1 = ?,
        custom2 = ?,
        custom3 = ?,
        custom4 = ?,
        custom5 = ?,
        custom6 = ?,
        custom7 = ?,
        custom8 = ?,
        bibtex_id = ?,
        changed_by = ?,
        changed_time = CURRENT_TIMESTAMP
    WHERE id=?
EOT;

        // Convert empty strings to nulls. Prevents unique indexes from throwing errors.
        $item = $this->sanitation->emptyToNull($item);

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item['id']) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Publication titles.
        $primary_title_id = null;

        if (!empty($item[ItemMeta::COLUMN['PRIMARY_TITLE']])) {

            $this->db_main->run($sql_select_primary, [$item[ItemMeta::COLUMN['PRIMARY_TITLE']]]);
            $primary_title_id = $this->db_main->getResult();

            if(empty($primary_title_id)) {

                $this->db_main->run($sql_insert_primary, [$item[ItemMeta::COLUMN['PRIMARY_TITLE']]]);
                $primary_title_id = $this->db_main->lastInsertId();
            }
        }

        $secondary_title_id = null;

        if (!empty($item[ItemMeta::COLUMN['SECONDARY_TITLE']])) {

            $this->db_main->run($sql_select_secondary, [$item[ItemMeta::COLUMN['SECONDARY_TITLE']]]);
            $secondary_title_id = $this->db_main->getResult();

            if(empty($secondary_title_id)) {

                $this->db_main->run($sql_insert_secondary, [$item[ItemMeta::COLUMN['SECONDARY_TITLE']]]);
                $secondary_title_id = $this->db_main->lastInsertId();
            }
        }

        $tertiary_title_id = null;

        if (!empty($item[ItemMeta::COLUMN['TERTIARY_TITLE']])) {

            $this->db_main->run($sql_select_tertiary, [$item[ItemMeta::COLUMN['TERTIARY_TITLE']]]);
            $tertiary_title_id = $this->db_main->getResult();

            if(empty($tertiary_title_id)) {

                $this->db_main->run($sql_insert_tertiary, [$item[ItemMeta::COLUMN['TERTIARY_TITLE']]]);
                $tertiary_title_id = $this->db_main->lastInsertId();
            }
        }

        // Publication date.
        $publication_date = null;

        if (!empty($item[ItemMeta::COLUMN['PUBLICATION_DATE']])) {

            if (preg_match('/^\d{4}$/', $item[ItemMeta::COLUMN['PUBLICATION_DATE']]) === 1) {

                $publication_date = $item[ItemMeta::COLUMN['PUBLICATION_DATE']] . '-01-01';

            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $item[ItemMeta::COLUMN['PUBLICATION_DATE']]) === 1) {

                $publication_date = $item[ItemMeta::COLUMN['PUBLICATION_DATE']];
            }
        }

        // Reference types.
        $reference_type = ItemMeta::TYPE['ARTICLE'];

        if (isset($item[ItemMeta::COLUMN['REFERENCE_TYPE']])
                && in_array($item[ItemMeta::COLUMN['REFERENCE_TYPE']], ItemMeta::TYPE) === true) {

            $reference_type = $item[ItemMeta::COLUMN['REFERENCE_TYPE']];
        }

        $bibtex_type = ItemMeta::TYPE['ARTICLE'];

        if (isset($item[ItemMeta::COLUMN['BIBTEX_TYPE']])
                && in_array($item[ItemMeta::COLUMN['BIBTEX_TYPE']], ItemMeta::BIBTEX_TYPE) === true) {

            $bibtex_type = $item[ItemMeta::COLUMN['BIBTEX_TYPE']];
        }

        // Bibtex ID.
        $bibtex_id = $item[ItemMeta::COLUMN['BIBTEX_ID']] ?? null;

        if (empty($bibtex_id)) {

            // Bibtex ID.
            $sql_bibtex_fromat = <<<SQL
SELECT setting_value
    FROM settings
    WHERE setting_name = 'custom_bibtex'
SQL;

            $this->db_main->run($sql_bibtex_fromat);
            $format_json = $this->db_main->getResult();

            if (empty($format_json)) {

                $format = $this->app_settings->default_global_settings['custom_bibtex'];

            } else {

                $format = Client\Utils::jsonDecode($format_json, true);
            }

            $bibtex_id = $this->scalar_utils->customBibtexId($format, $item);
        }

        $columns[] = $item[ItemMeta::COLUMN['TITLE']];
        $columns[] = $primary_title_id;
        $columns[] = $secondary_title_id;
        $columns[] = $tertiary_title_id;
        $columns[] = $publication_date;
        $columns[] = $item[ItemMeta::COLUMN['VOLUME']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['ISSUE']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['PAGES']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['ABSTRACT']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['AFFILIATION']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['PUBLISHER']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['PLACE_PUBLISHED']] ?? null;
        $columns[] = $reference_type;
        $columns[] = $bibtex_type;

        // URLs.
        if (isset($item[ItemMeta::COLUMN['URLS']]) && is_string($item[ItemMeta::COLUMN['URLS']])) {

            $columns[] = str_replace("\n", '|', $item[ItemMeta::COLUMN['URLS']]);

        } elseif (isset($item[ItemMeta::COLUMN['URLS']]) && is_array($item[ItemMeta::COLUMN['URLS']])) {

            $columns[] = join('|', $item[ItemMeta::COLUMN['URLS']]);

        } else {

            $columns[] = null;
        }

        $columns[] = $item[ItemMeta::COLUMN['CUSTOM1']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM2']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM3']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM4']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM5']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM6']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM7']] ?? null;
        $columns[] = $item[ItemMeta::COLUMN['CUSTOM8']] ?? null;
        $columns[] = $bibtex_id;
        $columns[] = $this->user_id;
        $columns[] = $item['id'];

        // Insert item.
        $this->db_main->run($sql_item, $columns);

        // UIDs.
        $sql_uid_delete = <<<SQL
DELETE
    FROM uids
    WHERE item_id = ? AND uid_type = ?
SQL;

        $sql_uid_find = <<<SQL
SELECT id
    FROM uids
    WHERE item_id = ? AND uid_type = ? AND uid = ?
SQL;

        $sql_uid_find2 = <<<SQL
SELECT id
    FROM uids
    WHERE item_id = ? AND uid_type = ?
SQL;

        $sql_uid_update = <<<SQL
UPDATE
    uids
    SET uid = ?
    WHERE item_id = ? AND uid_type = ?
SQL;

        $sql_uid_insert = <<<SQL
INSERT INTO uids
    (uid_type, uid, item_id)
    VALUES (?, ?, ?)
SQL;

        if (isset($item[ItemMeta::COLUMN['UIDS']])) {

            foreach ($item[ItemMeta::COLUMN['UIDS']] as $i => $uid) {

                // Ignore if no UID type set.
                if (empty($item[ItemMeta::COLUMN['UID_TYPES']][$i])) {

                    continue;
                }

                // If empty, delete.
                if (empty($uid)) {

                    $columns_uid = [
                        $item['id'],
                        $item[ItemMeta::COLUMN['UID_TYPES']][$i]
                    ];

                    $this->db_main->run($sql_uid_delete, $columns_uid);

                    continue;
                }

                // If exact match exists, ignore.
                $columns_uid = [
                    $item['id'],
                    $item[ItemMeta::COLUMN['UID_TYPES']][$i],
                    $uid
                ];

                $this->db_main->run($sql_uid_find, $columns_uid);
                $exists = $this->db_main->getResult();

                if (!empty($exists)) {

                    continue;
                }

                // Change existing UID type.
                $columns_uid = [
                    $item['id'],
                    $item[ItemMeta::COLUMN['UID_TYPES']][$i]
                ];

                $this->db_main->run($sql_uid_find2, $columns_uid);
                $exists = $this->db_main->getResult();

                if (!empty($exists)) {

                    $columns_uid = [
                        $uid,
                        $item['id'],
                        $item[ItemMeta::COLUMN['UID_TYPES']][$i]
                    ];

                    $this->db_main->run($sql_uid_update, $columns_uid);

                    continue;
                }

                // Finally, add new UID.
                $columns_uid = [
                    $item[ItemMeta::COLUMN['UID_TYPES']][$i],
                    $uid,
                    $item['id']
                ];

                $this->db_main->run($sql_uid_insert, $columns_uid);
            }
        }

        // Authors.
        $author_delete = <<<EOT
DELETE
    FROM items_authors
    WHERE item_id = ?
EOT;

        $author_select = <<<EOT
SELECT id
    FROM authors
    WHERE last_name = ? AND first_name = ?
EOT;

        $author_insert = <<<EOT
INSERT INTO authors
    (last_name, first_name)
    VALUES(?, ?)
EOT;

        $author_relation_insert = <<<EOT
INSERT INTO items_authors
    (item_id, author_id, position)
    VALUES(?, ?, ?)
EOT;

        // Delete items-authors rows.
        $this->db_main->run($author_delete, [$item['id']]);

        if (isset($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']]) === true) {

            $author_count = count($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']]);

            for ($i = 0, $position = 1; $i < $author_count; $i++, $position++) {

                if (empty($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][$i])) {

                    $position--;
                    continue;
                }

                // Get author id.
                $columns = [
                    $item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][$i],
                    $item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$i] ?? ''
                ];

                $this->db_main->run($author_select, $columns);
                $id = $this->db_main->getResult();

                if (empty($id)) {

                    $this->db_main->run($author_insert, $columns);
                    $id = $this->db_main->lastInsertId();
                }

                $columns = [
                    $item['id'],
                    $id,
                    $position
                ];

                // Update items_authors.
                $this->db_main->run($author_relation_insert, $columns);
            }
        }

        // Editors.
        $editor_delete = <<<EOT
DELETE
    FROM items_editors
    WHERE item_id = ?
EOT;

        $editor_select = <<<EOT
SELECT id
    FROM editors
    WHERE last_name = ? AND first_name = ?
EOT;

        $editor_insert = <<<EOT
INSERT INTO editors
    (last_name, first_name)
    VALUES(?, ?)
EOT;

        $editor_relation_insert = <<<EOT
INSERT INTO items_editors
    (item_id, editor_id, position)
    VALUES(?, ?, ?)
EOT;

        // Delete items-editors rows.
        $this->db_main->run($editor_delete, [$item['id']]);

        if (isset($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']]) === true) {

            $editor_count = count($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']]);

            for ($i = 0, $position = 1; $i < $editor_count; $i++, $position++) {

                if (empty($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][$i])) {

                    $position--;
                    continue;
                }

                // Get editor id.
                $columns = [
                    $item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][$i],
                    $item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$i] ?? ''
                ];

                $this->db_main->run($editor_select, $columns);
                $id = $this->db_main->getResult();

                if (empty($id)) {

                    $this->db_main->run($editor_insert, $columns);
                    $id = $this->db_main->lastInsertId();
                }

                $columns = [
                    $item['id'],
                    $id,
                    $position
                ];

                // Update items_editors.
                $this->db_main->run($editor_relation_insert, $columns);
            }
        }

        // Keywords.
        $keywords = [];

        if (isset($item[ItemMeta::COLUMN['KEYWORDS']]) && is_string($item[ItemMeta::COLUMN['KEYWORDS']])) {

            $keywords = array_unique(array_filter(explode("\n", $item[ItemMeta::COLUMN['KEYWORDS']])));

        } elseif (isset($item[ItemMeta::COLUMN['KEYWORDS']]) && is_array($item[ItemMeta::COLUMN['KEYWORDS']])) {

            $keywords = array_unique(array_filter($item[ItemMeta::COLUMN['KEYWORDS']]));
        }

        $keyword_delete = <<<EOT
DELETE
    FROM items_keywords
    WHERE item_id = ?
EOT;

        $keyword_select = <<<EOT
SELECT id
    FROM keywords
    WHERE keyword = ?
EOT;

        $keyword_insert = <<<EOT
INSERT INTO keywords
    (keyword)
    VALUES(?)
EOT;

        $keyword_relation_insert = <<<EOT
INSERT INTO items_keywords
    (item_id, keyword_id)
    VALUES(?, ?)
EOT;

        // Delete items-keywords rows.
        $this->db_main->run($keyword_delete, [$item['id']]);

        foreach ($keywords as $keyword) {

            // Get editor id.
            $columns = [
                trim($keyword)
            ];

            $this->db_main->run($keyword_select, $columns);
            $id = $this->db_main->getResult();

            if (empty($id)) {

                $this->db_main->run($keyword_insert, $columns);
                $id = $this->db_main->lastInsertId();
            }

            $columns = [
                $item['id'],
                $id
            ];

            // Update items_editors.
            $this->db_main->run($keyword_relation_insert, $columns);
        }

        $this->db_main->commit();

        // Update index.
        $sql_update = <<<SQL
UPDATE ind_items
    SET
    abstract_index        = '     ' || ? || '     ',
    affiliation_index     = '     ' || ? || '     ',
    authors_index         = '     ' || (
        SELECT deaccent(group_concat(last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END, '     '), 0)
            FROM authors INNER JOIN items_authors ON authors.id = items_authors.author_id
            WHERE items_authors.item_id = ?) || '     ',
    custom1_index         = '     ' || ? || '     ',
    custom2_index         = '     ' || ? || '     ',
    custom3_index         = '     ' || ? || '     ',
    custom4_index         = '     ' || ? || '     ',
    custom5_index         = '     ' || ? || '     ',
    custom6_index         = '     ' || ? || '     ',
    custom7_index         = '     ' || ? || '     ',
    custom8_index         = '     ' || ? || '     ',
    editors_index         = '     ' || (
        SELECT deaccent(group_concat(last_name || CASE first_name WHEN '' THEN '' ELSE ', ' || first_name END, '     '), 0)
            FROM editors INNER JOIN items_editors ON editors.id = items_editors.editor_id
            WHERE items_editors.item_id = ?) || '     ',
    keywords_index        = '     ' || (
        SELECT deaccent(group_concat(keyword, '     '), 0)
            FROM keywords INNER JOIN items_keywords ON keywords.id=items_keywords.keyword_id
            WHERE items_keywords.item_id = ?) || '     ',
    primary_title_index   = '     ' || ? || '     ',
    secondary_title_index = '     ' || ? || '     ',
    tertiary_title_index  = '     ' || ? || '     ',
    title_index           = '     ' || ? || '     '
    WHERE id = ?
SQL;

        $columns = [
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['ABSTRACT']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['AFFILIATION']] ?? null), false),
            $item['id'],
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['CUSTOM1']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['CUSTOM2']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['CUSTOM3']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['CUSTOM4']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['CUSTOM5']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['CUSTOM6']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['CUSTOM7']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['CUSTOM8']] ?? null), false),
            $item['id'],
            $item['id'],
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['PRIMARY_TITLE']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['SECONDARY_TITLE']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['TERTIARY_TITLE']] ?? null), false),
            $this->scalar_utils->deaccent(($item[ItemMeta::COLUMN['TITLE']]), false),
            $item['id']
        ];

        $this->db_main->run($sql_update, $columns);
    }

    /**
     * Delete item. Also delete the PDF.
     *
     * @param integer|array $item_ids
     * @throws Exception
     */
    protected function _delete($item_ids): void {

        // Only admin can do this.
        if ($this->permissions !== 'A') {

            return;
        }

        // Cached assets.
        $this->cache = $this->di->getShared('FileCache');

        if (is_array($item_ids) === false) {

            $item_ids = [$item_ids];
        }

        $sql_delete = <<<EOT
DELETE
    FROM items
    WHERE id = ?
EOT;

        $this->db_main->beginTransaction();

        foreach ($item_ids as $item_id) {

            // Check if ID exists.
            if ($this->idExists($item_id) === false) {

                $this->db_main->rollBack();
                throw new Exception("item $item_id does not exist", 404);
            }

            $deleted = $this->db_main->run($sql_delete, [$item_id]);

            if ($deleted === true) {

                if ($this->isPdf($item_id) === true) {

                    $filepath_pdf = $this->idToPdfPath($item_id);

                    // PDF icon.
                    $this->cache->context('icons');
                    $key = $this->cache->key($item_id);
                    $this->cache->delete($key);

                    // Pdf object.
                    $this->pdf_obj = $this->di->get('Pdf', [$filepath_pdf]);

                    // Page images.
                    $page_count = $this->pdf_obj->pageCount();
                    $this->pdf_obj = null;

                    $this->cache->context('pages');

                    for($i = 1; $i <= $page_count; $i++) {

                        $key = $this->cache->key([(int) $item_id, (int) $i]);
                        $this->cache->delete($key);
                    }

                    // Bookmarks.
                    $this->cache->context('bookmarks');
                    $key = $this->cache->key((int) $item_id);
                    $this->cache->delete($key);

                    // PDF db.
                    $this->deleteFile($filepath_pdf . '.db');

                    // Delete PDF.
                    $this->deleteFile($filepath_pdf);
                }

                // Searches.
                $this->cache->context('searches');
                $this->cache->clear();

                // Delete supplements.
                $filepath_supp = $this->idToSupplementPath($item_id);
                $files = glob($filepath_supp . '*', GLOB_NOSORT);

                foreach ($files as $file) {

                    $this->deleteFile($file);
                }
            }
        }

        $this->db_main->commit();
    }

    /**
     * Import items from a metadata string.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    protected function _importText(array $data): array {

        $count = 0;

        // Detect type and instantiate correct parser.
        $trimmed_text = trim($data['text']);

        switch ($trimmed_text[0]) {

            case '@':
                $this->parser = $this->di->get('BibtexImport', $data['text']);
                break;

            case '<':
                $this->parser = $this->di->get('EndnoteImport', $data['text']);
                break;

            case 'T':
                $this->parser = $this->di->get('RisImport', $data['text']);
                break;

            default:
                throw new Exception('metadata format not recognized', 400);
        }

        unset($data['text']);

        $pdf_object = new PdfModel($this->di);

        while ($entry = $this->parser->getEntry()) {

            $item = $this->_save($entry + $data);

            // Save PDF
            if (!empty($entry['PDF']) && !empty($item['item_id'])) {

                $pdf_file = IL_DATA_PATH . DIRECTORY_SEPARATOR . 'import' . DIRECTORY_SEPARATOR . $entry['PDF'];

                if (is_readable($pdf_file)) {

                    $fp = fopen($pdf_file, 'rb');
                    $stream = Client\Psr7\Utils::streamFor($fp);

                    $pdf_object->save($item['item_id'], $stream);

                    $stream = null;

                    // Delete PDF when done.
                    if (is_writable($pdf_file)) {

                        unlink($pdf_file);
                    }
                }
            }

            $count++;
        }

        $pdf_object = null;

        return ['count' => $count];
    }

    /**
     * Find out if item UIDs are already in database.
     *
     * @param array $items
     * @return array
     */
    protected function _uidsExist(array $items): array {

        $output = [];

        $sql = <<<SQL
SELECT count(*)
    FROM uids
    WHERE uid_type = ? AND uid = ?
    LIMIT 1
SQL;

        foreach ($items as $item) {

            $item = (array) $item;

            // Default id no.
            $item['exists'] = 'N';

            if (empty($item[ItemMeta::COLUMN['UID_TYPES']]) || empty($item[ItemMeta::COLUMN['UIDS']])) {

                $output[] = $item;

            } else {

                foreach ($item[ItemMeta::COLUMN['UID_TYPES']] as $key => $type) {

                    $this->db_main->run($sql, [$type, $item[ItemMeta::COLUMN['UIDS']][$key]]);
                    $exists = (int) $this->db_main->getResult();

                    if ($exists > 0) {

                        $item['exists'] = 'Y';
                        break;
                    }
                }
            }

            $output[] = $item;
        }

        return $output;
    }
}
