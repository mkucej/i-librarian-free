<?php

namespace LibrarianApp;

use Exception;
use Librarian\ItemMeta;
use Librarian\Logger\Logger;
use PDO;

/**
 * Class SummaryModel.
 *
 * @method array item(int $item_id, string|null $display_format)
 */
final class SummaryModel extends AppModel {

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param int $item_id
     * @param string|null $display_format
     * @return array
     * @throws Exception
     */
    protected function _item(int $item_id, string $display_format = null): array {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        if ($display_format === 'export') {

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
    items.file_hash,
    primary_titles.primary_title,
    secondary_titles.secondary_title,
    tertiary_titles.tertiary_title
    FROM items
    LEFT JOIN primary_titles ON primary_titles.id=items.primary_title_id
    LEFT JOIN secondary_titles ON secondary_titles.id=items.secondary_title_id
    LEFT JOIN tertiary_titles ON tertiary_titles.id=items.tertiary_title_id
    WHERE items.id = ?
EOT;

            $this->db_main->run($sql, [$item_id]);
            $output = $this->db_main->getResultRow();

            $sql_uids = <<<EOT
SELECT
    uid_type, uid
    FROM uids
    WHERE item_id = ?
EOT;

            $sql_authors = <<<EOT
SELECT last_name, first_name
    FROM authors
    INNER JOIN items_authors ON authors.id = items_authors.author_id
    WHERE items_authors.item_id = ?
    ORDER BY items_authors.position
EOT;

            $sql_editors = <<<EOT
SELECT last_name, first_name
    FROM editors
    INNER JOIN items_editors ON editors.id = items_editors.editor_id
    WHERE items_editors.item_id = ?
    ORDER BY items_editors.position
EOT;

            $sql_keywords = <<<EOT
SELECT keyword
    FROM keywords
    INNER JOIN items_keywords ON keywords.id = items_keywords.keyword_id
    WHERE items_keywords.item_id = ?
EOT;

            // UIDs.
            $this->db_main->run($sql_uids, [$item_id]);

            $output[ItemMeta::COLUMN['UID_TYPES']] = [];
            $output[ItemMeta::COLUMN['UIDS']] = [];

            while ($uids = $this->db_main->getResultRow()) {

                $output[ItemMeta::COLUMN['UID_TYPES']][] = $uids['uid_type'];
                $output[ItemMeta::COLUMN['UIDS']][] = $uids['uid'];
            }

            // Authors.
            $this->db_main->run($sql_authors, [$item_id]);

            while($row = $this->db_main->getResultRow()) {

                $output[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = $row['last_name'];
                $output[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = $row['first_name'];
            }

            // Editors.
            $this->db_main->run($sql_editors, [$item_id]);

            while($row = $this->db_main->getResultRow()) {

                $output[ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = $row['last_name'];
                $output[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = $row['first_name'];
            }

            // Keywords.
            $this->db_main->run($sql_keywords, [$item_id]);
            $output[ItemMeta::COLUMN['KEYWORDS']] = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

            // Urls.
            $urls = explode('|', $output[ItemMeta::COLUMN['URLS']]);
            $output[ItemMeta::COLUMN['URLS']] = $urls;

            if (!empty($output['file_hash'])) {

                $output['file'] = str_replace(IL_DATA_PATH . DIRECTORY_SEPARATOR, '', $this->idToPdfPath($item_id));
            }

            return $output;

        } else {

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
    items.bibtex_id,
    items.custom1,
    items.custom2,
    items.custom3,
    items.custom4,
    items.custom5,
    items.custom6,
    items.custom7,
    items.custom8,
    items.added_time,
    primary_titles.primary_title,
    secondary_titles.secondary_title,
    tertiary_titles.tertiary_title,
    items.file_hash,
    ifnull(trim(first_name || ' ' || last_name), username) as name
    FROM items
    LEFT JOIN primary_titles ON primary_titles.id=items.primary_title_id
    LEFT JOIN secondary_titles ON secondary_titles.id=items.secondary_title_id
    LEFT JOIN tertiary_titles ON tertiary_titles.id=items.tertiary_title_id
    INNER JOIN users ON users.id=items.added_by
    WHERE items.id=?
EOT;

            $this->db_main->run($sql, [$item_id]);
            $output = $this->db_main->getResultRow();

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

            // Clipboard flag.
            $sql = <<<EOT
SELECT
    count(*)
    FROM clipboard
    WHERE item_id = ? AND user_id = ?
EOT;

            $this->db_main->run($sql, [$item_id, $this->user_id]);
            $output['in_clipboard'] = $this->db_main->getResult() === '1' ? 'Y' : 'N';

            // Projects.
            $sql = <<<EOT
SELECT projects.id, projects.project
    FROM projects
    LEFT OUTER JOIN projects_users ON projects_users.project_id=projects.id
    WHERE
        (projects.user_id = ? OR projects_users.user_id = ?) AND projects.is_active = 'Y'
        ORDER BY projects.project COLLATE utf8Collation
EOT;

            $columns = [
                $this->user_id,
                $this->user_id
            ];

            $this->db_main->run($sql, $columns);
            $projects = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

            // Add project flags.
            $sql = <<<EOT
SELECT count(*)
    FROM projects_items
    WHERE project_id = ? AND item_id = ?
EOT;

            foreach ($projects as $project_id => $project_name) {

                $columns = [
                    $project_id,
                    $item_id
                ];

                $this->db_main->run($sql, $columns);
                $in_project = $this->db_main->getResult() === '1' ? 'Y' : 'N';

                $output['projects'][] = [
                    'project_id' => $project_id,
                    'project' => $project_name,
                    'in_project' => $in_project
                ];
            }

            // Tags.
            $sql = <<<EOT
SELECT
    tags.id, tag
    FROM tags
    INNER JOIN items_tags ON items_tags.tag_id=tags.id
    WHERE items_tags.item_id=?
    ORDER BY tag COLLATE utf8Collation
EOT;

            $this->db_main->run($sql, [$item_id]);
            $output['tags'] = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

            // Keywords.
            $sql = <<<EOT
SELECT
    keywords.id, keyword
    FROM keywords
    INNER JOIN items_keywords ON items_keywords.keyword_id=keywords.id
    WHERE items_keywords.item_id=?
    ORDER BY keyword COLLATE utf8Collation
EOT;

            $this->db_main->run($sql, [$item_id]);
            $output['keywords'] = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

            // Authors.
            $sql = <<<EOT
SELECT
    authors.id, CASE WHEN first_name is NULL THEN last_name ELSE last_name || ', ' || first_name END AS author
    FROM authors
    INNER JOIN items_authors ON items_authors.author_id=authors.id
    WHERE items_authors.item_id=?
    ORDER by items_authors.position
EOT;

            $this->db_main->run($sql, [$item_id]);
            $output['authors'] = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

            // Editors.
            $sql = <<<EOT
SELECT
    editors.id, CASE WHEN first_name is NULL THEN last_name ELSE last_name || ', ' || first_name END AS editor
    FROM editors
    INNER JOIN items_editors ON items_editors.editor_id=editors.id
    WHERE items_editors.item_id=?
    ORDER by items_editors.position
EOT;

            $this->db_main->run($sql, [$item_id]);
            $output['editors'] = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

            // Notes.
            $sql = <<<EOT
SELECT
    id_hash, ifnull(trim(first_name || ' ' || last_name), username) as name, note
    FROM item_notes
    INNER JOIN users ON users.id=item_notes.user_id
    WHERE item_notes.item_id=?
EOT;

            $this->db_main->run($sql, [$item_id]);
            $output['notes'] = $this->db_main->getResultRows();

            // PDF notes.
            $sql = <<<EOT
SELECT
    id_hash, ifnull(trim(first_name || ' ' || last_name), username) as name, annotation
    FROM annotations
    INNER JOIN users ON users.id=annotations.user_id
    WHERE annotations.item_id=?
EOT;

            $this->db_main->run($sql, [$item_id]);
            $output['pdfnotes'] = $this->db_main->getResultRows();

            $this->db_main->commit();

            // Supplementary files.
            $output['files'] = [];

            $filepath = $this->idToSupplementPath($item_id);
            $files = glob($filepath . "*");

            foreach ($files as $filename) {

                $output['files'][] = [
                    'name' => rawurldecode(substr(basename($filename), 9)),
                    'mime' => mime_content_type($filename)
                ];
            }

            // Log item open.
            $this->logger = $this->di->getShared('Logger');
            $this->logger->itemOpened($this->user_id, $item_id);

            return $output;
        }
    }
}
