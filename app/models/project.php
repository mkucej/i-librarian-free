<?php

namespace LibrarianApp;

use Exception;
use PDO;

/**
 * @method void  activate(int $project_id)
 * @method void  create(array $project)
 * @method array compileNotes(int $project_id)
 * @method void  delete(int $project_id)
 * @method array get(int $project_id)
 * @method void  inactivate(int $project_id)
 * @method void  join(int $project_id)
 * @method void  leave(int $project_id)
 * @method array list()
 * @method array loadDiscussion(int $project_id)
 * @method array readNotes(int $project_id)
 * @method array readUserNotes(int $project_id)
 * @method void  saveMessage(array $message)
 * @method void  saveNotes(int $project_id, string $note)
 * @method void  update(int $project_id, array $project)
 */
class ProjectModel extends AppModel {

    /**
     * Get project data.
     *
     * @param int $project_id
     * @return array
     * @throws Exception
     */
    protected function _get(int $project_id): array {

        $this->db_main->beginTransaction();

        // Project data.
        $sql = <<<EOT
SELECT id, project, is_active, is_restricted
    FROM projects
    WHERE
        id = ? AND user_id = ?
EOT;

        $columns = [
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
        $output = $this->db_main->getResultRow();

        // No project found.
        if (empty($output)) {

            $this->db_main->commit();

            return [];
        }

        // Project found, continue.

        // Project users.
        $sql = <<<EOT
SELECT id_hash
    FROM projects_users
    INNER JOIN users ON projects_users.user_id = users.id
    WHERE project_id = ?
EOT;

        $columns = [
            $project_id
        ];

        $this->db_main->run($sql, $columns);
        $project_users = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        // All users.
        $sql = <<<EOT
SELECT ifnull(trim(first_name || ' ' || last_name), username) as name, id_hash
    FROM users
    WHERE id != ?
    ORDER BY name COLLATE utf8Collation
EOT;

        $columns = [
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            $row['in_project'] = 'N';

            if (in_array($row['id_hash'], $project_users) === true) {

                $row['in_project'] = 'Y';
            }

            $output['users'][] = $row;
        }

        $this->db_main->commit();

        return $output;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function _list(): array {

        $output = [
            'active_projects'   => [],
            'inactive_projects' => [],
            'open_projects'     => [],
            'users'             => [],
        ];

        // Get a list of all users for create project form.
        $sql = <<<EOT
SELECT ifnull(trim(first_name || ' ' || last_name), username) as name, id_hash
    FROM users
    WHERE id != ? AND status = 'A'
    ORDER BY name COLLATE utf8Collation
EOT;

        $columns = [
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);

        $output['users'] = $this->db_main->getResultRows();

        // Get user's projects.
        $sql = <<<EOT
SELECT
    projects.id,
    users.id_hash,
    ifnull(trim(users.first_name || ' ' || users.last_name), users.username) as name,
    projects.project,
    projects.is_active,
    projects.is_restricted,
    projects.added_time
    FROM projects
    INNER JOIN users ON projects.user_id = users.id
    INNER JOIN projects_users on projects.id = projects_users.project_id
    WHERE projects_users.user_id = ?
    GROUP BY projects.id, projects.project
    ORDER BY projects.project COLLATE utf8Collation
EOT;

        $columns = [
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);

        while ($row = $this->db_main->getResultRow()) {

            if ($row['is_active'] === 'Y') {

                $output['active_projects'][] = $row;

            } else {

                $output['inactive_projects'][] = $row;
            }
        }

        // Get others' open projects. User has not joined these projects.
        $sql = <<<EOT
SELECT 
    projects.id,
    ifnull(trim(users.first_name || ' ' || users.last_name), users.username) as name,
    projects.project,
    projects.added_time
    FROM projects
    INNER JOIN users ON projects.user_id = users.id
    WHERE
        projects.is_restricted = 'N' AND
        projects.is_active = 'Y' AND
        projects.user_id != ?
EXCEPT
SELECT
    projects.id,
    ifnull(trim(users.first_name || ' ' || users.last_name), users.username) as name,
    projects.project,
    projects.added_time
    FROM projects
    INNER JOIN users ON projects.user_id = users.id
    INNER JOIN projects_users on projects.id = projects_users.project_id
    WHERE projects_users.user_id = ?
EOT;

        $columns = [
            $this->user_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);

        $output['open_projects'] = $this->db_main->getResultRows();

        return $output;
    }

    /**
     * @param array $project
     * @throws Exception
     */
    protected function _create(array $project): void {

        $sql = <<<EOT
INSERT INTO projects
    (user_id, project, is_active, is_restricted, added_time)
    VALUES(
       ?,
       ?,
       'Y',
       ?,
       CURRENT_TIMESTAMP
   )
EOT;

        $columns = [
            $this->user_id,
            $project['name'],
            $project['access'] === 'restricted' ? 'Y' : 'N',
        ];

        $this->db_main->beginTransaction();

        $this->db_main->run($sql, $columns);

        $project_id = $this->db_main->lastInsertId();

        // Add the project owner to the project users.
        if (isset($project['users']) === false) {

            $project['users'] = [$this->id_hash];

        } else {

            $project['users'][] = $this->id_hash;
        }

        // Add users.
        $sql = <<<EOT
INSERT INTO projects_users
    (project_id, user_id)
    VALUES(?, (SELECT id FROM users WHERE id_hash = ?))
EOT;

        foreach ($project['users'] as $id_hash) {

            $columns = [
                $project_id,
                $id_hash
            ];

            $this->db_main->run($sql, $columns);
        }

        $this->db_main->commit();
    }

    /**
     * Update a project.
     *
     * @param int $project_id
     * @param array $project
     * @throws Exception
     */
    protected function _update(int $project_id, array $project): void {

        $this->db_main->beginTransaction();

        $sql = <<<EOT
UPDATE projects
    SET project = ?, is_restricted = ?
    WHERE id = ? AND user_id = ?
EOT;

        $columns = [
            $project['name'],
            $project['access'] === 'restricted' ? 'Y' : 'N',
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);

        $updated = (int) $this->db_main->getPDOStatement()->rowCount();

        if ($updated === 0) {

            $this->db_main->rollBack();
            throw new Exception('project does not exist, or you are not authorized to edit it', 403);
        }

        // Update users.
        $sql = <<<EOT
DELETE
FROM projects_users
    WHERE project_id = ?
EOT;

        $columns = [
            $project_id
        ];

        $this->db_main->run($sql, $columns);

        // Add the project owner to the project users.
        if (isset($project['users']) === false) {

            $project['users'] = [$this->id_hash];

        } else {

            $project['users'][] = $this->id_hash;
        }

        $sql = <<<EOT
INSERT INTO projects_users
    (project_id, user_id)
    VALUES(?, (SELECT id FROM users WHERE id_hash = ?))
EOT;

        foreach ($project['users'] as $id_hash) {

            $columns = [
                $project_id,
                $id_hash
            ];

            $this->db_main->run($sql, $columns);
        }

        $this->db_main->commit();
    }

    /**
     * Join an open access project.
     *
     * @param int $project_id
     * @throws Exception
     */
    protected function _join(int $project_id): void {

        $this->db_main->beginTransaction();

        // Check if project is active and open.
        $sql = <<<EOT
SELECT count(*)
    FROM projects
    WHERE id = ? AND is_restricted = 'N' AND is_active = 'Y'
EOT;

        $columns = [
            $project_id
        ];

        $this->db_main->run($sql, $columns);
        $count =  (int) $this->db_main->getResult();

        if ($count === 0) {

            $this->db_main->rollBack();
            throw new Exception('this project is not open access', 403);
        }

        // Add a user.
        $sql = <<<EOT
INSERT OR IGNORE
    INTO projects_users
    (project_id, user_id)
    VALUES (?, ?)
EOT;

        $columns = [
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);

        $this->db_main->commit();
    }

    /**
     * Leave an open access project.
     *
     * @param int $project_id
     * @throws Exception
     */
    protected function _leave(int $project_id): void {

        $this->db_main->beginTransaction();

        // Check if project is active and open.
        $sql = <<<EOT
SELECT count(*)
    FROM projects
    WHERE id = ? AND is_restricted = 'N' AND is_active = 'Y'
EOT;

        $columns = [
            $project_id
        ];

        $this->db_main->run($sql, $columns);
        $count = (int) $this->db_main->getResult();

        if ($count === 0) {

            $this->db_main->rollBack();
            throw new Exception('this project is not open access', 403);
        }

        // Delete a user.
        $sql = <<<EOT
DELETE
    FROM projects_users
    WHERE project_id = ? AND user_id = ?
EOT;

        $columns = [
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);

        $this->db_main->commit();
    }

    /**
     * Inactivate user's project.
     *
     * @param int $project_id
     * @throws Exception
     */
    protected function _inactivate(int $project_id): void {

        $sql = <<<EOT
UPDATE projects
    SET is_active = 'N'
    WHERE id = ? AND user_id = ?
EOT;

        $columns = [
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
    }

    /**
     * Activate user's project.
     *
     * @param int $project_id
     * @throws Exception
     */
    protected function _activate(int $project_id): void {

        $sql = <<<EOT
UPDATE projects
    SET is_active = 'Y'
    WHERE id = ? AND user_id = ?
EOT;

        $columns = [
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
    }

    /**
     * Delete user's project.
     *
     * @param int $project_id
     * @throws Exception
     */
    protected function _delete(int $project_id): void {

        // Authorize project.
        if ($this->verifyProject($project_id) === false) {

            throw new Exception('you are not authorized to access this project', 403);
        }

        $sql = <<<EOT
DELETE FROM projects
    WHERE id = ? AND user_id = ?
EOT;

        $columns = [
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
    }

    /**
     * Load discussion for a project.
     *
     * @param int $project_id
     * @return array
     * @throws Exception
     */
    protected function _loadDiscussion(int $project_id): array {

        $output = [];

        $this->db_main->beginTransaction();

        // Authorize project.
        if ($this->verifyProject($project_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to access this project', 403);
        }

        // Title.
        $sql = <<<EOT
SELECT
    project
    FROM projects
    WHERE id = ?
EOT;

        $this->db_main->run($sql, [$project_id]);
        $output['title'] = $this->db_main->getResult();

        // Messages.
        $sql = <<<EOT
SELECT
    ifnull(trim(users.first_name || ' ' || users.last_name), users.username) as name,
    project_discussions.message,
    project_discussions.added_time
    FROM project_discussions
    INNER JOIN users ON project_discussions.user_id=users.id
    WHERE project_discussions.project_id = ?
    ORDER BY project_discussions.added_time DESC
EOT;

        $this->db_main->run($sql, [$project_id]);
        $output['messages'] = $this->db_main->getResultRows();

        $this->db_main->commit();

        return $output;
    }

    /**
     * Save discussion message.
     *
     * @param array $message
     * @throws Exception
     */
    protected function _saveMessage(array $message): void {

        $output = [
            'saved' => false
        ];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->verifyProject($message['project_id']) === false) {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to access this project', 403);
        }

        // Item columns.
        $sql = <<<EOT
INSERT INTO project_discussions
    (project_id, user_id, message, added_time)
    VALUES(?, ?, ?, CURRENT_TIMESTAMP)
EOT;

        $columns = [
            $message['project_id'],
            $this->user_id,
            $message['message']
        ];

        $output['saved'] = $this->db_main->run($sql, $columns);

        $this->db_main->commit();
    }

    /**
     * Compile item notes for project.
     *
     * @param int $project_id
     * @return array
     * @throws Exception
     */
    protected function _readNotes(int $project_id): array {

        $output = [
            'others' => [],
            'user' => []
        ];

        $this->db_main->beginTransaction();

        // Authorize project.
        if ($this->verifyProject($project_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to access this project', 403);
        }

        // Title.
        $sql = <<<EOT
SELECT
    project
    FROM projects
    WHERE id = ?
EOT;

        $this->db_main->run($sql, [$project_id]);
        $output['title'] = $this->db_main->getResult();

        // Notes.
        $sql = <<<EOT
SELECT
    ifnull(trim(users.first_name || ' ' || users.last_name), users.username) as name,
    project_notes.note,
    project_notes.changed_time
    FROM project_notes
    INNER JOIN users ON project_notes.user_id=users.id
    WHERE project_notes.project_id = ? AND users.id != ?
    ORDER BY project_notes.changed_time DESC
EOT;

        $columns = [
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
        $output['others'] = $this->db_main->getResultRows();

        $sql = <<<'EOT'
SELECT project_notes.note, project_notes.changed_time
    FROM project_notes
    INNER JOIN users ON users.id=project_notes.user_id
    WHERE project_notes.project_id = ? AND users.id = ?
EOT;

        $columns = [
            $project_id,
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
     * Get user notes for TinyMCE.
     *
     * @param int $project_id
     * @return array
     * @throws Exception
     */
    protected function _readUserNotes(int $project_id): array {

        $output = [
            'user' => [
                'note'         => null,
                'changed_time' => null
            ]
        ];

        $this->db_main->beginTransaction();

        // Authorize project.
        if ($this->verifyProject($project_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to access this project', 403);
        }

        // Select title.
        $sql = <<<'EOT'
SELECT title
    FROM items
    WHERE id = ?
EOT;

        $columns = [
            $project_id
        ];

        $this->db_main->run($sql, $columns);
        $output['title'] = $this->db_main->getResult();

        $sql = <<<'EOT'
SELECT project_notes.note, project_notes.changed_time
    FROM project_notes
    INNER JOIN users ON users.id=project_notes.user_id
    WHERE project_notes.project_id = ? AND users.id = ?
EOT;

        $columns = [
            $project_id,
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
     * Save project note.
     *
     * @param int $project_id
     * @param string $note
     * @throws Exception
     */
    protected function _saveNotes(int $project_id, string $note): void {

        $purifier = $this->di->getShared('HtmlPurifier');
        $note_sanitized = $purifier->purify($note);
        $note_sanitized = $this->sanitation->emptyToNull($note_sanitized);

        $this->db_main->beginTransaction();

        // Authorize project.
        if ($this->verifyProject($project_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to access this project', 403);
        }

        $sql = <<<'EOT'
SELECT id
    FROM project_notes
    WHERE project_id = ? AND user_id = ?
EOT;

        $columns = [
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
        $note_id = $this->db_main->getResult();

        if ($note_sanitized === null) {

            $sql = <<<'EOT'
DELETE FROM project_notes
    WHERE project_id = ? AND user_id = (
        SELECT id FROM users WHERE id = ?
    )
EOT;

            $columns = [
                $project_id,
                $this->user_id
            ];

        } elseif (empty($note_id)) {

            $sql = <<<'EOT'
INSERT INTO project_notes
    (project_id, user_id, note, changed_time)
    VALUES(?, ?, ?, CURRENT_TIMESTAMP)
EOT;

            $columns = [
                $project_id,
                $this->user_id,
                $note_sanitized
            ];

        } else {

            $sql = <<<'EOT'
UPDATE project_notes
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

    /**
     * Compile project's item notes.
     *
     * @param int $project_id
     * @return array
     * @throws Exception
     */
    protected function _compileNotes(int $project_id): array {

        $output = [
            'others' => [],
            'user' => []
        ];

        $this->db_main->beginTransaction();

        // Authorize project.
        if ($this->verifyProject($project_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to access this project', 403);
        }

        // Title.
        $sql = <<<EOT
SELECT
    project
    FROM projects
    WHERE id = ?
EOT;

        $this->db_main->run($sql, [$project_id]);
        $output['title'] = $this->db_main->getResult();

        // Notes.
        $sql = <<<EOT
SELECT
    ifnull(trim(users.first_name || ' ' || users.last_name), users.username) as name,
    users.id,
    item_notes.item_id,
    item_notes.note,
    item_notes.changed_time
    FROM item_notes
    INNER JOIN users ON item_notes.user_id=users.id
    INNER JOIN projects_items ON projects_items.item_id=item_notes.item_id
    WHERE projects_items.project_id = ?
    ORDER BY item_notes.changed_time DESC
EOT;

        $columns = [
            $project_id
        ];

        $this->db_main->run($sql, $columns);
        $rows = $this->db_main->getResultRows();

        $sql = <<<EOT
SELECT
    title
    FROM items
    WHERE id = ?
EOT;

        $output['others'] = [];

        foreach ($rows as $row) {

            $this->db_main->run($sql, [$row['item_id']]);
            $title = $this->db_main->getResult();

            if ($row['id'] === $this->user_id) {

                $output['user'][$row['item_id']]['title'] = $title;
                $output['user'][$row['item_id']]['notes'][] = $row;

            } else {

                $output['other'][$row['item_id']]['title'] = $title;
                $output['other'][$row['item_id']]['notes'][] = $row;
            }
        }

        $this->db_main->commit();

        return $output;
    }
}
