<?php

namespace LibrarianApp;

use Exception;
use PDO;

/**
 * Class DashboardModel.
 *
 * @method array get()
 */
class DashboardModel extends AppModel {

    /**
     * Get.
     *
     * @return array
     * @throws Exception
     */
    protected function _get(): array {

        $this->reporter = $this->di->get('Reporter');

        $output = [
            'last_items'     => [],
            'last_projects'  => [],
            'last_discussed' => [],
            'last_discussed_projects' => [],
            'last_notes'     => [],
            'last_project_notes' => []
        ];

        $this->db_main->beginTransaction();

        /*
         * Count.
         */
        $sql = <<<EOT
SELECT total_count
    FROM stats
    WHERE table_name = 'items';
EOT;

        $this->db_main->run($sql);
        $output['count'] = $this->db_main->getResult();

        /*
         * Last added.
         */

        // Get IDs, remove restricted.
        $sql_items = <<<EOT
SELECT id
    FROM items
    ORDER BY id DESC
    LIMIT 5
EOT;

        // Add item data.
        $sql_item_data = <<<EOT
SELECT title, CASE WHEN file_hash IS NULL THEN 0 ELSE 1 END AS has_pdf
    FROM items
    WHERE id = ?
EOT;

        $this->db_main->run($sql_items);
        $last_items = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        // Add data.
        foreach ($last_items as $item_id) {

            $this->db_main->run($sql_item_data, [$item_id]);
            $row = $this->db_main->getResultRow();

            $output['last_items'][] = [
                'id'      => $item_id,
                'title'   => $row['title'],
                'has_pdf' => $row['has_pdf']
            ];
        }

        /*
         * Last projects.
         */

        // Get user's projects.
        $sql_projects = <<<EOT
SELECT projects.id, projects.project
    FROM projects
    LEFT JOIN projects_users on projects.id = projects_users.project_id
    WHERE (projects.user_id = ? OR projects_users.user_id = ?) AND projects.is_active = 'Y'
    GROUP BY projects.id, projects.added_time
    ORDER BY projects.added_time DESC
    LIMIT 5
EOT;

        $columns_projects = [
            $this->user_id,
            $this->user_id
        ];

        $this->db_main->run($sql_projects, $columns_projects);

        $output['last_projects'] = $this->db_main->getResultRows();

        /*
         * Last discussed.
         */

        $sql_discussed = <<<EOT
SELECT DISTINCT item_id
    FROM item_discussions
    ORDER BY id DESC
    LIMIT 5
EOT;

        // Add item data.
        $sql_discussed_data = <<<EOT
SELECT substr(message, 1, 255) AS message
    FROM item_discussions
    WHERE item_id = ?
    ORDER BY id DESC
    LIMIT 1
EOT;

        $this->db_main->run($sql_discussed);
        $disc_ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        // Add data.
        foreach ($disc_ids as $item_id) {

            $this->db_main->run($sql_discussed_data, [$item_id]);
            $message = $this->db_main->getResult();

            $output['last_discussed'][] = [
                'id'      => $item_id,
                'message' => $message
            ];
        }

        /*
         * Last item notes.
         */

        $sql_notes = <<<EOT
SELECT DISTINCT item_id
    FROM item_notes
    ORDER BY id DESC
    LIMIT 5
EOT;

        $sql_notes_data = <<<EOT
SELECT substr(striptags(note), -255)
    FROM item_notes
    WHERE item_id = ?
    ORDER BY id DESC
    LIMIT 1
EOT;

        $this->db_main->run($sql_notes);
        $note_ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        // Add data.
        foreach ($note_ids as $item_id) {

            $this->db_main->run($sql_notes_data, [$item_id]);
            $note = $this->db_main->getResult();

            $output['last_notes'][] = [
                'id'   => $item_id,
                'note' => $note
            ];
        }

        /*
         * Last project notes.
         */

        $sql_project_notes = <<<EOT
SELECT DISTINCT project_notes.project_id
    FROM project_notes
    INNER JOIN projects ON project_notes.project_id = projects.id
    LEFT JOIN projects_users ON projects.id = projects_users.project_id
    WHERE (projects.user_id = ? OR projects_users.user_id = ?) AND projects.is_active = 'Y'
    ORDER BY project_notes.changed_time DESC
    LIMIT 5
EOT;

        $columns_projects = [
            $this->user_id,
            $this->user_id
        ];

        $sql_project_notes_data = <<<EOT
SELECT substr(striptags(note), -255)
    FROM project_notes
    WHERE project_id = ?
    ORDER BY id DESC
    LIMIT 1
EOT;

        $this->db_main->run($sql_project_notes, $columns_projects);
        $note_ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        // Add data.
        foreach ($note_ids as $project_id) {

            $this->db_main->run($sql_project_notes_data, [$project_id]);
            $note = $this->db_main->getResult();

            $output['last_project_notes'][] = [
                'id'   => $project_id,
                'note' => $note
            ];
        }

        /*
         * Last projects discussed.
         */

        $sql_discussed = <<<EOT
SELECT DISTINCT project_discussions.project_id
    FROM project_discussions
    INNER JOIN projects on project_discussions.project_id = projects.id
    LEFT OUTER JOIN projects_users ON projects.id = projects_users.project_id
    WHERE
        (projects.user_id = ? OR projects_users.user_id = ?) AND projects.is_active = 'Y'
    ORDER BY project_discussions.id DESC
    LIMIT 5
EOT;

        $columns = [
            $this->user_id,
            $this->user_id
        ];

        // Add project data.
        $sql_discussed_data = <<<EOT
SELECT substr(message, 1, 255) AS message
    FROM project_discussions
    WHERE project_id = ?
    ORDER BY id DESC
    LIMIT 1
EOT;

        $this->db_main->run($sql_discussed, $columns);
        $disc_ids = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        // Add data.
        foreach ($disc_ids as $project_id) {

            $this->db_main->run($sql_discussed_data, [$project_id]);
            $message = $this->db_main->getResult();

            $output['last_discussed_projects'][] = [
                'project_id' => $project_id,
                'message'    => $message
            ];
        }

        /*
         * User sessions.
         */
        $sql_sessions = <<<EOT
SELECT session_id
    FROM sessions
    WHERE user_id = ?
    ORDER BY added_time DESC
EOT;

        $this->db_main->run($sql_sessions, [$this->user_id]);
        $output['sessions'] = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        /*
         * Tags for search.
         */

        $sql_tags = <<<'EOT'
SELECT id, tag
    FROM tags
    ORDER BY tag COLLATE utf8Collation
EOT;

        $this->db_main->run($sql_tags);
        $output['tags'] = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

        $this->db_main->commit();

        return $output;
    }
}
