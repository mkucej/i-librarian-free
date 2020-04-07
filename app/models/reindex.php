<?php

namespace LibrarianApp;

use Exception;
use Librarian\Media\Pdf;
use Librarian\Media\ScalarUtils;
use PDO;

/**
 * Class ReindexModel.
 *
 * @method array checkDb()
 * @method array info()
 * @method void  reextract()
 * @method void  reindex()
 * @method void  vacuum()
 */
final class ReindexModel extends AppModel {

    /**
     * @return array
     * @throws Exception
     */
    protected function _info(): array {

        $db_file = IL_DB_PATH . DIRECTORY_SEPARATOR . 'main.db';

        return [
            'size' => filesize($db_file),
            'modified' => filemtime($db_file),
            'writable' => is_writable($db_file) & is_writable(dirname($db_file))
        ];
    }

    /**
     * Vacuum the database.
     *
     * @throws Exception
     */
    protected function _vacuum(): void {

        // This can take a long time.
        set_time_limit(3600);

        $this->db_main->run('PRAGMA optimize(0x02)');
        $this->db_main->run('VACUUM');
    }

    /**
     * Database integrity and foreign key check.
     *
     * @return array
     * @throws Exception
     */
    protected function _checkDb(): array {

        // This can take a long time.
        set_time_limit(3600);

        $sql = <<<SQL
PRAGMA integrity_check;
SQL;

        $this->db_main->run($sql);
        $db_errors = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        if ($db_errors[0] === 'ok') {

            $db_errors = [];
        }

        $sql = <<<SQL
PRAGMA foreign_key_check;
SQL;

        $this->db_main->run($sql);

        while ($row = $this->db_main->getResultRow()) {

            $db_errors[] = "Invalid reference from table {$row['table']}, row {$row['rowid']} to table {$row['parent']}";
        }

        return $db_errors;
    }

    /**
     * @throws Exception
     */
    protected function _reindex(): void {

        // This can take a long time.
        set_time_limit(86400);

        /** @var ScalarUtils $scalar_utils */
        $scalar_utils = $this->di->getShared('ScalarUtils');

        $transaction_size = 1000;

        // Items.
        $sql_max = <<<SQL
SELECT max(id)
    FROM items
SQL;

        $sql_select = <<<SQL
SELECT id,
       abstract,
       affiliation,
       custom1,
       custom2,
       custom3,
       custom4,
       custom5,
       custom6,
       custom7,
       custom8,
       primary_title_id,
       secondary_title_id,
       tertiary_title_id,
       title
    FROM items
    WHERE id >= ? AND id < ?
SQL;

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
            WHERE items_keywords.item_id=?) || '     ',
    primary_title_index   = '     ' || (
        SELECT deaccent(primary_title, 0) FROM primary_titles WHERE id = ?) || '     ',
    secondary_title_index = '     ' || (
        SELECT deaccent(secondary_title, 0) FROM secondary_titles WHERE id = ?) || '     ',
    tertiary_title_index  = '     ' || (
        SELECT deaccent(tertiary_title, 0) FROM tertiary_titles WHERE id = ?) || '     ',
    title_index           = '     ' || ? || '     '
    WHERE id = ?
SQL;

        $this->db_main->beginTransaction();

        $this->db_main->run($sql_max);
        $max = $this->db_main->getResult();

        for ($i = 1; $i <= $max; $i = $i + $transaction_size) {

            $columns = [
                $i,
                $i + $transaction_size
            ];

            $this->db_main->run($sql_select, $columns);
            $rows = $this->db_main->getResultRows();

            foreach ($rows as $row) {

                $columns = [
                    $scalar_utils->deaccent($row['abstract'], false),
                    $scalar_utils->deaccent($row['affiliation'], false),
                    $row['id'],
                    $scalar_utils->deaccent($row['custom1'], false),
                    $scalar_utils->deaccent($row['custom2'], false),
                    $scalar_utils->deaccent($row['custom3'], false),
                    $scalar_utils->deaccent($row['custom4'], false),
                    $scalar_utils->deaccent($row['custom5'], false),
                    $scalar_utils->deaccent($row['custom6'], false),
                    $scalar_utils->deaccent($row['custom7'], false),
                    $scalar_utils->deaccent($row['custom8'], false),
                    $row['id'],
                    $row['id'],
                    $row['primary_title_id'],
                    $row['secondary_title_id'],
                    $row['tertiary_title_id'],
                    $scalar_utils->deaccent($row['title'], false),
                    $row['id']
                ];

                $this->db_main->run($sql_update, $columns);
            }

            $this->db_main->commit();
            $this->db_main->beginTransaction();
        }

        $this->db_main->commit();

        // Authors.

        $sql_max = <<<SQL
SELECT max(id)
    FROM authors
SQL;

        $sql_select = <<<SQL
SELECT id, first_name, last_name
    FROM authors
    WHERE id >= ? AND id < ?
SQL;

        $sql_update = <<<SQL
UPDATE ind_authors
    SET author = ?
    WHERE id = ?
SQL;

        $this->db_main->beginTransaction();

        $this->db_main->run($sql_max);
        $max = $this->db_main->getResult();

        for ($i = 1; $i <= $max; $i = $i + $transaction_size) {

            $columns = [
                $i,
                $i + $transaction_size
            ];

            $this->db_main->run($sql_select, $columns);
            $rows = $this->db_main->getResultRows();

            foreach ($rows as $row) {

                $author = $row['last_name'];
                $author .= empty($row['first_name']) ? '' : ', ' . $row['first_name'];

                $columns = [
                    $scalar_utils->deaccent($author, false),
                    $row['id']
                ];

                $this->db_main->run($sql_update, $columns);
            }

            $this->db_main->commit();
            $this->db_main->beginTransaction();
        }

        $this->db_main->commit();

        // Editors.

        $sql_max = <<<SQL
SELECT max(id)
    FROM editors
SQL;

        $sql_select = <<<SQL
SELECT id, first_name, last_name
    FROM editors
    WHERE id >= ? AND id < ?
SQL;

        $sql_update = <<<SQL
UPDATE ind_editors
    SET editor = ?
    WHERE id = ?
SQL;

        $this->db_main->beginTransaction();

        $this->db_main->run($sql_max);
        $max = $this->db_main->getResult();

        for ($i = 1; $i <= $max; $i = $i + $transaction_size) {

            $columns = [
                $i,
                $i + $transaction_size
            ];

            $this->db_main->run($sql_select, $columns);
            $rows = $this->db_main->getResultRows();

            foreach ($rows as $row) {

                $editor = $row['last_name'];
                $editor .= empty($row['first_name']) ? '' : ', ' . $row['first_name'];

                $columns = [
                    $scalar_utils->deaccent($editor, false),
                    $row['id']
                ];

                $this->db_main->run($sql_update, $columns);
            }

            $this->db_main->commit();
            $this->db_main->beginTransaction();
        }

        $this->db_main->commit();

        // Keywords.

        $sql_max = <<<SQL
SELECT max(id)
    FROM ind_keywords
SQL;

        $sql_select = <<<SQL
SELECT id, keyword
    FROM keywords
    WHERE id >= ? AND id < ?
SQL;

        $sql_update = <<<SQL
UPDATE ind_keywords
    SET keyword = ?
    WHERE id = ?
SQL;

        $this->db_main->beginTransaction();

        $this->db_main->run($sql_max);
        $max = $this->db_main->getResult();

        for ($i = 1; $i <= $max; $i = $i + $transaction_size) {

            $columns = [
                $i,
                $i + $transaction_size
            ];

            $this->db_main->run($sql_select, $columns);
            $rows = $this->db_main->getResultRows();

            foreach ($rows as $row) {

                $columns = [
                    '     ' . $scalar_utils->deaccent($row['keyword'], false) . '     ',
                    $row['id']
                ];

                $this->db_main->run($sql_update, $columns);
            }

            $this->db_main->commit();
            $this->db_main->beginTransaction();
        }

        $this->db_main->commit();

        // Primary, secondary and tertiary titles.

        foreach (['primary', 'secondary', 'tertiary'] as $type) {

            $sql_max = <<<SQL
SELECT max(id)
    FROM {$type}_titles
SQL;

            $sql_select = <<<SQL
SELECT id, {$type}_title
    FROM {$type}_titles
    WHERE id >= ? AND id < ?
SQL;

            $sql_update = <<<SQL
UPDATE ind_{$type}_titles
    SET {$type}_title = ?
    WHERE rowid = ?
SQL;

            $this->db_main->beginTransaction();

            $this->db_main->run($sql_max);
            $max = $this->db_main->getResult();

            for ($i = 1; $i <= $max; $i = $i + $transaction_size) {

                $columns = [
                    $i,
                    $i + $transaction_size
                ];

                $this->db_main->run($sql_select, $columns);
                $rows = $this->db_main->getResultRows();

                foreach ($rows as $row) {

                    $columns = [
                        $scalar_utils->deaccent($row["{$type}_title"], false),
                        $row['id']
                    ];

                    $this->db_main->run($sql_update, $columns);
                }

                $this->db_main->commit();
                $this->db_main->beginTransaction();
            }

            $this->db_main->commit();
        }

        $this->_vacuum();
    }

    /**
     * Re-extract PDFs and save to database.
     *
     * @throws Exception
     */
    protected function _reextract(): void {

        /** @var ScalarUtils $scalar_utils */
        $scalar_utils = $this->di->getShared('ScalarUtils');

        $sql = <<<SQL
SELECT id
    FROM items
SQL;

        // Insert new text.
        $sql_ins = <<<'EOT'
UPDATE ind_items
    SET full_text = ?, full_text_index = ?
    WHERE id = ?
EOT;

        $this->db_main->run($sql);
        $ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        foreach ($ids as $id) {

            set_time_limit(600);

            // Extract PDF text.

            if ($this->isPdf($id) === false) {

                continue;
            }

            /** @var Pdf $pdf_object */
            $pdf_object = $this->di->get('Pdf', $this->idToPdfPath($id));

            $text_file = $pdf_object->text();
            $text = file_get_contents($text_file);

            if(empty($text)) {

                continue;
            }

            $columns_ins = [
                gzencode($text, 1),
                '     ' . $scalar_utils->deaccent($text, false) . '     ',
                (integer) $id
            ];

            $this->db_main->run($sql_ins, $columns_ins);

            unlink($text_file);
        }

        $this->_vacuum();
    }
}
