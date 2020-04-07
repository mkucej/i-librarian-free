<?php

namespace LibrarianApp;

use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Model;

/**
 * Class AppModel
 *
 * Top model class in app.
 */
class AppModel extends Model {

    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        // Main db object.
        $this->db_main = $this->di->get('Db_main');
        $this->db_main->connect();

        // Logs db object.
        $this->db_logs = $this->di->get('Db_logs');
        $this->db_logs->connect();

        // User id and permissions.
        if (is_object($this->session) && $this->session->data('user_id') !== null) {

            // From the application session.
            $id_hash = $this->session->data('user_id');
            $this->getUserIdAndPermissions($id_hash);
        }
    }

    /**
     * Get user's id and permissions.
     *
     * @param string $id_hash
     */
    private function getUserIdAndPermissions(string $id_hash): void {

        $sql = <<<'SQL'
SELECT id, permissions
    FROM users
    WHERE id_hash = ?
SQL;

        $this->db_main->run($sql, [$id_hash]);
        $row = $this->db_main->getResultRow();

        $this->id_hash = $id_hash;
        $this->user_id = $row['id'];
        $this->permissions = $row['permissions'];
    }

    /**
     * Verify if allowed to see project.
     *
     * @param int $project_id
     * @return bool
     */
    protected function verifyProject(int $project_id): bool {

        $sql = <<<EOT
SELECT count(*)
    FROM projects_users
    WHERE project_id = ? AND user_id = ?
    LIMIT 1
EOT;

        $columns = [
            $project_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);

        return $this->db_main->getResult() === '0' ? false : true;
    }

    /**
     * Remove database handles form memory.
     */
    public function __destruct() {

        $this->db_main->close();
        $this->db_logs->close();
    }
}
