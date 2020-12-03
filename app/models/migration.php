<?php

namespace LibrarianApp;

use Exception;
use Librarian\Http\Client\Psr7;
use Librarian\Media\ScalarUtils;
use Librarian\Security\Encryption;
use PDO;

/**
 * Class MigrationModel.
 *
 * @method array legacyupgrade(string $legacy_dir)
 */
class MigrationModel extends AppModel {

    private $db_library;

    private $db_users;

    /**
     * @var Encryption
     */
    private $encryption;

    private $legacy_dir;

    private $setting_translation = [
        'disallow_signup'     => 'disallow_signup',
        'default_permissions' => 'default_permissions',
        'custom1'             => 'custom1',
        'custom2'             => 'custom2',
        'custom3'             => 'custom3',
        'custom4'             => 'custom4',
        'connection'          => 'connection',
        'wpad_url'            => 'wpad_url',
        'proxy_name'          => 'proxy_name',
        'proxy_port'          => 'proxy_port',
        'proxy_username'      => 'proxy_username',
        'proxy_password'      => 'proxy_password',
        'soffice_path'        => 'soffice_path'
    ];

    /**
     * @var ScalarUtils
     */
    private $scalar_utils;

    /**
     * @param string $legacy_dir
     * @return array
     * @throws Exception
     */
    protected function _legacyupgrade(string $legacy_dir): array {

        $this->legacy_dir = $legacy_dir;

        // Is there a database?
        if (!is_readable($this->legacy_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'library.sq3')) {

            throw new Exception('database not found');
        }

        // Parent folder must be writable.
        if (!is_writable($this->legacy_dir . DIRECTORY_SEPARATOR . 'database')) {

            throw new Exception('the directory containing the sq3 files must be writable');
        }

        // Verify legacy version.
        $this->db_library = $this->di->get('Db_custom', [
            [
                'engine' => 'sqlite',
                'dbname' => $this->legacy_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'library.sq3'
            ]
        ]);

        $this->db_library->connect();

        $this->db_library->run('PRAGMA user_version');
        $version = (integer) $this->db_library->getResult();

        $this->db_library->close();

        // Check version.
        if ($version < 36) {

            throw new Exception('your library version is too old, please upgrade it to version 4.10 first');
        }

        // OK, we can upgrade.
        $this->_moveFiles();
        $this->_legacyUsers();
        $this->_legacyLibrary();
        // Do not migrate full_text, instruct users to reindex after upgrade.
//        $this->_legacyFulltext();
        $this->_legacyDiscussions();

        $this->db_main->run('ANALYZE');

        return [];
    }

    /**
     * @throws Exception
     */
    private function _legacyUsers() {

        $this->encryption = $this->di->getShared('Encryption');

        $sql_users_insert = <<<'EOT'
INSERT OR IGNORE INTO users
    (id, id_hash, username, password, permissions, status)
    VALUES(?, ?, ?, ?, ?, ?)
EOT;

        // Old users.
        $this->db_users = $this->di->get('Db_custom', [
            [
                'engine' => 'sqlite',
                'dbname' => $this->legacy_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'users.sq3'
            ]
        ]);

        $this->db_users->connect();

//        $this->db_users->run('PRAGMA journal_mode = DELETE');

        $this->db_users->run('SELECT * FROM users');
        $users = $this->db_users->getResultRows();

        // Write new users.
        $this->db_main->beginTransaction();

        foreach ($users as $user) {

            $columns = [
                $user['userID'],
                $this->encryption->getRandomKey(32),
                $user['username'],
                $user['password'],
                $user['permissions'],
                'A'
            ];

            $this->db_main->run($sql_users_insert, $columns);
        }

        $this->db_main->commit();

        // Migrate global settings. We do not migrate user settings.
        $sql_setting_insert = <<<'EOT'
INSERT OR IGNORE INTO settings
    (setting_name, setting_value)
    VALUES(?, ?)
EOT;

        $this->db_users->run("SELECT setting_name, setting_value FROM settings WHERE userID = ''");
        $settings = $this->db_users->getResultRows();
        $this->db_users->close();

        $this->db_main->beginTransaction();

        foreach ($settings as $setting) {

            if (isset($this->setting_translation[$setting['setting_name']]) === false) {

                continue;
            }

            $columns = [
                $this->setting_translation[$setting['setting_name']],
                $setting['setting_value']
            ];

            $this->db_main->run($sql_setting_insert, $columns);
        }

        $this->db_main->commit();
    }

    private function _legacyLibrary() {

        set_time_limit(3600);

        $dbh = $this->db_main->getPDO();

        $dbh->sqliteCreateFunction('html_entity_decode', 'html_entity_decode', 1);

        // Attach old library to the new db.
        $sql_attach = <<<'EOT'
ATTACH DATABASE ? AS library
EOT;

        // Import primary titles.
        $sql_insert_primary = <<<'EOT'
INSERT OR IGNORE INTO primary_titles
    (primary_title)
    SELECT DISTINCT trim(journal)
        FROM library.library
        WHERE journal != ''
EOT;

        // Import secondary titles.
        $sql_insert_secondary = <<<'EOT'
INSERT OR IGNORE INTO secondary_titles
    (secondary_title)
    SELECT DISTINCT trim(secondary_title)
        FROM library.library
        WHERE secondary_title != ''
EOT;

        // Import tertiary titles.
        $sql_insert_tertiary = <<<'EOT'
INSERT OR IGNORE INTO tertiary_titles
    (tertiary_title)
    SELECT DISTINCT trim(tertiary_title)
        FROM library.library
        WHERE tertiary_title != ''
EOT;

        // Import library table.
        $sql_insert_items = <<<'EOT'
INSERT INTO items
    (
        id,
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
        bibtex_id,
        bibtex_type,
        urls,
        custom1,
        custom2,
        custom3,
        custom4,
        private,
        file_hash,
        added_by,
        changed_by,
        added_time,
        changed_time
    )
    SELECT
        id,
        title,
        (SELECT id FROM primary_titles WHERE primary_title=library.library.journal),
        (SELECT id FROM secondary_titles WHERE secondary_title=library.library.secondary_title),
        (SELECT id FROM tertiary_titles WHERE tertiary_title=library.library.tertiary_title),
        CASE year WHEN '' THEN NULL ELSE year END,
        CASE volume WHEN '' THEN NULL ELSE volume END,
        CASE issue WHEN '' THEN NULL ELSE issue END,
        CASE pages WHEN '' THEN NULL ELSE pages END,
        CASE abstract WHEN '' THEN NULL ELSE abstract END,
        CASE affiliation WHEN '' THEN NULL ELSE affiliation END,
        CASE publisher WHEN '' THEN NULL ELSE publisher END,
        CASE place_published WHEN '' THEN NULL ELSE place_published END,
        CASE reference_type WHEN '' THEN 'article' ELSE reference_type END,
        CASE bibtex WHEN '' THEN NULL ELSE bibtex END,
        CASE bibtex_type WHEN '' THEN NULL ELSE bibtex_type END,
        CASE WHEN url = '' THEN NULL WHEN url = 'Array' THEN NULL ELSE url END,
        CASE custom1 WHEN '' THEN NULL ELSE custom1 END,
        CASE custom2 WHEN '' THEN NULL ELSE custom2 END,
        CASE custom3 WHEN '' THEN NULL ELSE custom3 END,
        CASE custom4 WHEN '' THEN NULL ELSE custom4 END,
        'N',
        CASE filehash WHEN '' THEN NULL ELSE filehash END,
        (SELECT min(id) FROM users),
        (SELECT min(id) FROM users),
        CASE addition_date WHEN '' THEN datetime('now') ELSE datetime(addition_date) END,
        CASE modified_date WHEN '' THEN datetime('now') ELSE datetime(modified_date) END
        FROM library.library
EOT;

        // Select UIDs.
        $sql_select_uids = <<<'EOT'
SELECT id, uid, doi
    FROM library.library
EOT;

        // Import UIDs.
        $sql_insert_uids = <<<'EOT'
INSERT OR IGNORE INTO uids
    (uid_type, uid, item_id)
    VALUES(?, ?, ?)
EOT;

        // Select keywords.
        $sql_select_keywords = <<<'EOT'
SELECT id, trim(keywords) AS keywords
    FROM library.library
    WHERE keywords != ''
EOT;

        // Import keywords.
        $sql_insert_keywords = <<<'EOT'
INSERT OR IGNORE INTO keywords
    (keyword)
    VALUES(?)
EOT;

        // Import item-keyword relations.
        $sql_update_keywords = <<<'EOT'
INSERT OR IGNORE INTO items_keywords
    (item_id, keyword_id)
    VALUES(
        ?,
        (SELECT id FROM keywords WHERE keyword=?)
    )
EOT;

        // Select authors.
        $sql_select_authors = <<<'EOT'
SELECT id, authors
    FROM library.library
    WHERE authors != ''
EOT;

        // Import authors.
        $sql_insert_authors = <<<'EOT'
INSERT OR IGNORE INTO authors
    (first_name, last_name)
    VALUES(?, ?)
EOT;

        // Import item-author relations.
        $sql_update_authors = <<<'EOT'
INSERT OR IGNORE INTO items_authors
    (item_id, author_id, position)
    VALUES(
        ?,
        (SELECT id FROM authors WHERE last_name=? AND first_name=?),
        ?
    )
EOT;

        // Select editors.
        $sql_select_editors = <<<'EOT'
SELECT id, editor
    FROM library.library
    WHERE editor != ''
EOT;

        // Import editors.
        $sql_insert_editors = <<<'EOT'
INSERT OR IGNORE INTO editors
    (first_name, last_name)
    VALUES(?, ?)
EOT;

        // Import item-editor relations.
        $sql_update_editors = <<<'EOT'
INSERT OR IGNORE INTO items_editors
    (item_id, editor_id, position)
    VALUES(
        ?,
        (SELECT id FROM editors WHERE last_name=? AND first_name=?),
        ?
    )
EOT;

        // Tags.
        $sql_insert_tags = <<<'EOT'
INSERT OR IGNORE INTO tags
    (id, tag)
    SELECT categoryID, trim(category)
        FROM library.categories
        WHERE category != ''
EOT;

        // Items-tags.
        $sql_insert_items_tags = <<<'EOT'
INSERT OR IGNORE INTO items_tags
    (item_id, tag_id)
    SELECT fileID, categoryID
        FROM library.filescategories
        WHERE fileID IN (SELECT id FROM library.library)
EOT;

        // Projects.
        $sql_insert_projects = <<<'EOT'
INSERT OR IGNORE INTO projects
    (id, user_id, project, is_active, is_restricted)
    SELECT projectID, userID, project, CASE active WHEN 1 THEN 'Y' ELSE 'N' END, 'Y'
    FROM library.projects WHERE userID IN (SELECT id FROM users)
EOT;

        // Projects items
        $sql_insert_projects_items = <<<'EOT'
INSERT OR IGNORE INTO projects_items
    (project_id, item_id)
    SELECT projectID, fileID
        FROM library.projectsfiles
        WHERE projectID IN (SELECT projectID FROM library.projects) AND fileID IN (SELECT id FROM library.library)
EOT;

        // Projects users
        $sql_insert_projects_users = <<<'EOT'
INSERT OR IGNORE INTO projects_users
    (project_id, user_id)
    SELECT projectID, userID FROM library.projectsusers WHERE userID IN (SELECT id FROM users)
EOT;

        $sql_insert_projects_users_owner = <<<'EOT'
INSERT OR IGNORE INTO projects_users
    (project_id, user_id)
    SELECT projectID, userID FROM library.projects WHERE userID IN (SELECT id FROM users)
EOT;

        // Convert shelf to a project with name `Shelf`.
        $sql_select_distinct_shelves = <<<'EOT'
SELECT DISTINCT userID
    FROM library.shelves WHERE userID IN (SELECT id FROM users)
EOT;

        // Create shelf projects.
        $sql_create_shelf = <<<'EOT'
INSERT OR IGNORE INTO projects
    (user_id, project, is_restricted)
    VALUES(?, ?, 'N')
EOT;

        $sql_select_shelf_rows = <<<'EOT'
SELECT fileID, userID
    FROM library.shelves WHERE userID IN (SELECT id FROM users)
EOT;

        // Populate shelf project.
        $sql_insert_shelf = <<<'EOT'
INSERT OR IGNORE INTO projects_items
    (project_id, item_id)
    VALUES(
        (SELECT id FROM projects WHERE user_id=? AND project LIKE 'Shelf%'),
        ?
    )
EOT;

        $sql_insert_shelf_users_owner = <<<'EOT'
INSERT OR IGNORE INTO projects_users
    (project_id, user_id)
    VALUES(
        (SELECT id FROM projects WHERE user_id=? AND project LIKE 'Shelf%'),
        ?
    )
EOT;

        // Item notes.
        $sql_insert_item_notes = <<<'EOT'
INSERT OR IGNORE INTO item_notes
    (id, user_id, item_id, note)
    SELECT notesID, userID, fileID, html_entity_decode(notes)
        FROM library.notes
        WHERE trim(notes) != '' AND fileID IN (SELECT id FROM library.library) AND userID IN (SELECT id FROM users)
EOT;

        // Markers.
        $sql_insert_markers = <<<'EOT'
INSERT OR IGNORE INTO markers
    (
        user_id,
        item_id,
        page,
        marker_position,
        marker_top,
        marker_left,
        marker_width,
        marker_height,
        marker_color,
        marker_text
    )
    SELECT
        userID,
        CAST(filename AS INTEGER) AS fileID,
        page,
        0,
        CAST(10 * top AS INTEGER),
        CAST(10 * `left` AS INTEGER),
        CAST(10 * width AS INTEGER),
        12,
        'blue',
        NULL
        FROM library.yellowmarkers
        WHERE fileID IN (SELECT id FROM library.library) AND userID IN (SELECT id FROM users)
EOT;

        // PDF notes.
        $sql_insert_annotations = <<<'EOT'
INSERT OR IGNORE INTO annotations
    (
        user_id,
        item_id,
        page,
        annotation_top,
        annotation_left,
        annotation
    )
    SELECT
        userID,
        CAST(filename AS INTEGER) as fileID,
        page,
        CAST(10 * top AS INTEGER),
        CAST(10 * `left` AS INTEGER),
        annotation
        FROM library.annotations
        WHERE fileID IN (SELECT id FROM library.library) AND userID IN (SELECT id FROM users)
EOT;

        // Detach old library.
        $sql_detach = <<<'EOT'
DETACH DATABASE library
EOT;

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

        $columns = [
            $this->legacy_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'library.sq3'
        ];

        $this->db_main->run($sql_attach, $columns);

        $this->db_main->beginTransaction();

        $this->db_main->run($sql_insert_primary);
        $this->db_main->run($sql_insert_secondary);
        $this->db_main->run($sql_insert_tertiary);
        $this->db_main->run($sql_insert_items);

        // UIDs.
        $this->db_main->run($sql_select_uids);
        $uid_rows = $this->db_main->getResultRows();

        foreach ($uid_rows as $row) {

            $uids = explode('|', $row['uid']);

            foreach ($uids as $uid) {

                if (empty($uid) || $uid === 'Array') {

                    continue;
                }

                $parts = explode(':', $uid);
                $type = isset($parts[1]) ? $parts[0] : 'OTHER';
                $value = isset($parts[1]) ? $parts[1] : $parts[0];

                $this->db_main->run($sql_insert_uids, [$type, $value, $row['id']]);
            }

            if (!empty($row['doi'])) {

                $this->db_main->run($sql_insert_uids, ['DOI', $row['doi'], $row['id']]);
            }
        }

        // Keywords.
        $this->db_main->run($sql_select_keywords);
        $keyword_rows = $this->db_main->getResultRows();

        foreach ($keyword_rows as $row) {

            $keywords = explode(' / ', $row['keywords']);

            foreach ($keywords as $keyword) {

                $this->db_main->run($sql_insert_keywords, [htmlspecialchars_decode(trim($keyword))]);
            }
        }

        foreach ($keyword_rows as $row) {

            $item_id = $row['id'];
            $keywords = explode(' / ', $row['keywords']);

            foreach ($keywords as $keyword) {

                $this->db_main->run($sql_update_keywords, [$item_id, htmlspecialchars_decode(trim($keyword))]);
            }
        }

        // Authors.
        $this->db_main->run($sql_select_authors);
        $author_rows = $this->db_main->getResultRows();

        foreach ($author_rows as $row) {

            $authors = explode(';', $row['authors']);

            foreach ($authors as $author) {

                $author_arr = explode(',', $author);

                $last_name = substr($author_arr[0], 3, -1);

                // Skip empty.
                if (empty(trim($last_name))) {

                    continue;
                }

                $first_name = isset($author_arr[1]) ? substr($author_arr[1], 3, -1) : '';

                $this->db_main->run($sql_insert_authors, [$first_name, $last_name]);
            }
        }

        foreach ($author_rows as $row) {

            $item_id = $row['id'];
            $authors = explode(';', $row['authors']);
            $count = count($authors);

            for ($position = 1; $position <= $count; $position++) {

                $author_arr = explode(',', $authors[($position - 1)]);

                $last_name = substr($author_arr[0], 3, -1);

                // Skip empty.
                if (empty(trim($last_name))) {

                    continue;
                }

                $first_name = isset($author_arr[1]) ? substr($author_arr[1], 3, -1) : '';

                $this->db_main->run($sql_update_authors, [$item_id, $last_name, $first_name, $position]);
            }
        }

        // Editors.
        $this->db_main->run($sql_select_editors);
        $editor_rows = $this->db_main->getResultRows();

        foreach ($editor_rows as $row) {

            $editors = explode(';', $row['editor']);

            foreach ($editors as $editor) {

                $editor_arr = explode(',', $editor);

                $last_name = substr($editor_arr[0], 3, -1);

                // Skip empty.
                if (empty(trim($last_name))) {

                    continue;
                }

                $first_name = isset($editor_arr[1]) ? substr($editor_arr[1], 3, -1) : '';

                $this->db_main->run($sql_insert_editors, [$first_name, $last_name]);
            }
        }

        foreach ($editor_rows as $row) {

            $item_id = $row['id'];
            $editors = explode(';', $row['editor']);
            $count = count($editors);

            for ($position = 1; $position <= $count; $position++) {

                $editor_arr = explode(',', $editors[($position - 1)]);

                $last_name = substr($editor_arr[0], 3, -1);

                // Skip empty.
                if (empty(trim($last_name))) {

                    continue;
                }

                $first_name = isset($editor_arr[1]) ? substr($editor_arr[1], 3, -1) : '';

                $this->db_main->run($sql_update_editors, [$item_id, $last_name, $first_name, $position]);
            }
        }

        // Tags.
        $this->db_main->run($sql_insert_tags);
        $this->db_main->run($sql_insert_items_tags);

        // Projects.
        $this->db_main->run($sql_insert_projects);
        $this->db_main->run($sql_insert_projects_items);
        $this->db_main->run($sql_insert_projects_users);
        $this->db_main->run($sql_insert_projects_users_owner);

        // Shelves.
        $this->db_main->run($sql_select_distinct_shelves);
        $shelf_user_id_rows = $this->db_main->getResultRows();

        foreach ($shelf_user_id_rows as $row) {

            // Bugged IDs.
            if (intval($row['userID']) === 0) {

                continue;
            }

            $this->db_main->run($sql_create_shelf, [$row['userID'], 'Shelf ' . $row['userID']]);
            $this->db_main->run($sql_insert_shelf_users_owner, [$row['userID'], $row['userID']]);
        }

        $this->db_main->run($sql_select_shelf_rows);
        $shelf_rows = $this->db_main->getResultRows();

        foreach ($shelf_rows as $row) {

            // Bugged IDs.
            if (intval($row['userID']) === 0) {

                continue;
            }

            $this->db_main->run($sql_insert_shelf, [$row['userID'], $row['fileID']]);
        }

        // Item notes.
        $this->db_main->run($sql_insert_item_notes);

        // Markers.
        $this->db_main->run($sql_insert_markers);

        // Annotations.
        $this->db_main->run($sql_insert_annotations);

        $this->db_main->commit();
        $this->db_main->run($sql_detach);

        // Fix no hashes.
        $this->db_main->run($sql_select_nohash);
        $empty_ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        foreach ($empty_ids as $empty_id) {

            // PDF exists?
            if ($this->isPdf($empty_id) === true) {

                $filepath = $this->idToPdfPath($empty_id);

                $pdf_stream = $this->readFile($filepath);
                $pdf_hash = Psr7\Utils::hash($pdf_stream, 'md5');

                $columns_update = [
                    $pdf_hash,
                    (integer) $empty_id
                ];

                $this->db_main->run($sql_update_nohash, $columns_update);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function _legacyFulltext() {

        set_time_limit(3600);

        $this->scalar_utils = $this->di->getShared('ScalarUtils');

        // Attach old db to the new db.
        $sql_attach = <<<'EOT'
ATTACH DATABASE ? AS full_text
EOT;

        // Select full text row.
        $sql_select_fts = <<<'EOT'
SELECT fileID, full_text
    FROM full_text.full_text
    WHERE full_text.full_text != ''
EOT;

        // Populate full text.
        $sql_insert_fts = <<<'EOT'
UPDATE items
    SET full_text_index = deaccent(?)
    WHERE id = ?
EOT;

        // Detach old library.
        $sql_detach = <<<'EOT'
DETACH DATABASE full_text
EOT;

        $database = $this->legacy_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'fulltext.sq3';

        $this->db_main->run($sql_attach, [$database]);

        $this->db_main->beginTransaction();

        $this->db_main->run($sql_select_fts);

        $rows = $this->db_main->getResultRows();

        $i = 1;

        foreach ($rows as $row) {

            $columns = [
                $row['full_text'],
                $row['fileID']
            ];

            $this->db_main->run($sql_insert_fts, $columns);

            // Transactions are 100-rows large.
            if ($i % 100 === 0) {

                $this->db_main->commit();
                $this->db_main->beginTransaction();
            }

            $i++;
        }

        $this->db_main->commit();

        $this->db_main->run($sql_detach);
    }

    private function _legacyDiscussions() {

        // Attach old library to the new db.
        $sql_attach = <<<'EOT'
ATTACH DATABASE ? AS discussions
EOT;

        // Select all item_discussions messages.
        $sql_select_item_discussions = <<<'EOT'
SELECT id, user, fileID, message, timestamp
    FROM discussions.filediscussion
EOT;

        // Populate item_discussions table.
        $sql_insert_item_discussions = <<<'EOT'
INSERT OR IGNORE INTO item_discussions
        (id, user_id, item_id, message, added_time)
        VALUES (
            ?,
            (SELECT id FROM users WHERE username=?),
            ?,
            ?,
            datetime(?)
        )
EOT;

        // Select all project_discussions messages.
        $sql_select_project_discussions = <<<'EOT'
SELECT id, user, projectID, message, timestamp
    FROM discussions.projectdiscussion
EOT;

        // Populate item_discussions table.
        $sql_insert_project_discussions = <<<'EOT'
INSERT OR IGNORE INTO project_discussions
        (id, user_id, project_id, message, added_time)
        VALUES (
            ?,
            (SELECT id FROM users WHERE username=?),
            ?,
            ?,
            datetime(?)
        )
EOT;

        // Detach old library.
        $sql_detach = <<<'EOT'
DETACH DATABASE discussions
EOT;

        $database = $this->legacy_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'discussions.sq3';
        $this->db_main->run($sql_attach, [$database]);

        $this->db_main->beginTransaction();

        $this->db_main->run($sql_select_item_discussions);
        $discussions = $this->db_main->getResultRows();

        foreach ($discussions as $discussion) {

            $columns = [
                $discussion['id'],
                $discussion['user'],
                $discussion['fileID'],
                $discussion['message'],
                $discussion['timestamp']
            ];

            $this->db_main->run($sql_insert_item_discussions, $columns);
        }

        $this->db_main->run($sql_select_project_discussions);
        $discussions = $this->db_main->getResultRows();

        foreach ($discussions as $discussion) {

            $columns = [
                $discussion['id'],
                $discussion['user'],
                $discussion['projectID'],
                $discussion['message'],
                $discussion['timestamp']
            ];

            $this->db_main->run($sql_insert_project_discussions, $columns);
        }

        $this->db_main->commit();
        $this->db_main->run($sql_detach);
    }

    /**
     * @throws Exception
     */
    private function _moveFiles() {

        // Can be memory intensive, if large number of files.
        ini_set('memory_limit', '512M');
        setlocale(LC_ALL,'en_US.UTF-8');

        // Pre 4.4.
        if (is_dir($this->legacy_dir . DIRECTORY_SEPARATOR . 'pdfs' . DIRECTORY_SEPARATOR . '01')) {

            $files = glob($this->legacy_dir . DIRECTORY_SEPARATOR . 'pdfs' . DIRECTORY_SEPARATOR . '01' . DIRECTORY_SEPARATOR . '*.pdf', GLOB_NOSORT);

            foreach ($files as $file) {

                set_time_limit(30);

                $id = (integer) substr(pathinfo($file, PATHINFO_FILENAME), 0, 5);
                $dir = IL_PDF_PATH . DIRECTORY_SEPARATOR . $this->getSubPath($id);

                $this->makeDir($dir);

                $fp_from     = Psr7\Utils::tryFopen($file, 'r');
                $stream_from = Psr7\Utils::streamFor($fp_from);
                $fp_to       = Psr7\Utils::tryFopen($this->idToPdfPath($id), 'w');
                $stream_to   = Psr7\Utils::streamFor($fp_to);

                Psr7\Utils::copyToStream($stream_from, $stream_to);

                $fp_from     = null;
                $stream_from = null;
                $fp_to       = null;
                $stream_to   = null;
            }
        }

        if (is_dir($this->legacy_dir . DIRECTORY_SEPARATOR . 'supplement' . DIRECTORY_SEPARATOR . '01')) {

            $files = glob($this->legacy_dir . DIRECTORY_SEPARATOR . 'supplement' . DIRECTORY_SEPARATOR . '01' . DIRECTORY_SEPARATOR . '*', GLOB_NOSORT);

            foreach ($files as $file) {

                set_time_limit(30);

                $id = (integer) substr(pathinfo($file, PATHINFO_FILENAME), 0, 5);
                $dir = IL_SUPPLEMENT_PATH . DIRECTORY_SEPARATOR . $this->getSubPath($id);

                $this->makeDir($dir);

                /*
                 * Shorten the filename. Filenames are stored encoded in RFC 3986. Some
                 * UTF-8 filenames can be longer than allowed in this format.
                 */
                $sup_name = substr(pathinfo($file, PATHINFO_BASENAME), 5);

                while (strlen(rawurlencode($sup_name)) > 240) {

                    $sup_name = trim(mb_substr($sup_name, 0, -1, 'UTF-8'));
                }

                $fp_from     = Psr7\Utils::tryFopen($file, 'r');
                $stream_from = Psr7\Utils::streamFor($fp_from);
                $fp_to       = Psr7\Utils::tryFopen($this->idToSupplementPath($id) . rawurlencode($sup_name), 'w');
                $stream_to   = Psr7\Utils::streamFor($fp_to);

                Psr7\Utils::copyToStream($stream_from, $stream_to);

                $fp_from     = null;
                $stream_from = null;
                $fp_to       = null;
                $stream_to   = null;
            }
        }

        // Post 4.4.
        if (is_dir($this->legacy_dir . DIRECTORY_SEPARATOR . 'pdfs' . DIRECTORY_SEPARATOR . '0' . DIRECTORY_SEPARATOR . '0')) {

            $files = glob($this->legacy_dir . DIRECTORY_SEPARATOR . 'pdfs' . DIRECTORY_SEPARATOR . '[0-9]' . DIRECTORY_SEPARATOR . '[0-9]' . DIRECTORY_SEPARATOR . '*.pdf', GLOB_NOSORT);

            foreach ($files as $file) {

                set_time_limit(30);

                $id = (integer) substr(pathinfo($file, PATHINFO_FILENAME), 0, 5);
                $dir = IL_PDF_PATH . DIRECTORY_SEPARATOR . $this->getSubPath($id);

                $this->makeDir($dir);

                $fp_from     = Psr7\Utils::tryFopen($file, 'r');
                $stream_from = Psr7\Utils::streamFor($fp_from);
                $fp_to       = Psr7\Utils::tryFopen($this->idToPdfPath($id), 'w');
                $stream_to   = Psr7\Utils::streamFor($fp_to);

                Psr7\Utils::copyToStream($stream_from, $stream_to);

                $fp_from     = null;
                $stream_from = null;
                $fp_to       = null;
                $stream_to   = null;
            }
        }

        if (is_dir($this->legacy_dir . DIRECTORY_SEPARATOR . 'supplement' . DIRECTORY_SEPARATOR . '0' . DIRECTORY_SEPARATOR . '0')) {

            $files = glob($this->legacy_dir . DIRECTORY_SEPARATOR . 'supplement' . DIRECTORY_SEPARATOR . '[0-9]' . DIRECTORY_SEPARATOR . '[0-9]' . DIRECTORY_SEPARATOR . '*', GLOB_NOSORT);

            foreach ($files as $file) {

                set_time_limit(30);

                $id = (integer) substr(pathinfo($file, PATHINFO_FILENAME), 0, 5);
                $dir = IL_SUPPLEMENT_PATH . DIRECTORY_SEPARATOR . $this->getSubPath($id);

                $this->makeDir($dir);

                /*
                 * Shorten the filename. Filenames are stored encoded in RFC 3986. Some
                 * UTF-8 filenames can be longer than allowed in this format.
                 */
                $sup_name = substr(pathinfo($file, PATHINFO_BASENAME), 5);

                while (strlen(rawurlencode($sup_name)) > 240) {

                    $sup_name = trim(mb_substr($sup_name, 0, -1, 'UTF-8'));
                }

                $fp_from     = Psr7\Utils::tryFopen($file, 'r');
                $stream_from = Psr7\Utils::streamFor($fp_from);
                $fp_to       = Psr7\Utils::tryFopen($this->idToSupplementPath($id) . rawurlencode($sup_name), 'w');
                $stream_to   = Psr7\Utils::streamFor($fp_to);

                Psr7\Utils::copyToStream($stream_from, $stream_to);

                $fp_from     = null;
                $stream_from = null;
                $fp_to       = null;
                $stream_to   = null;
            }
        }
    }
}
