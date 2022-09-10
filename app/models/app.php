<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Model;
use Librarian\Security\Session;

/**
 * Class AppModel
 *
 * Top model class in app.
 */
class AppModel extends Model {

    /**
     * @var string An id hash. Most models will require authorization against it.
     */
    protected string $id_hash;

    /**
     * @var Session
     */
    protected Session $session;

    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        // Main db object.
        $this->db_main = $this->di->get('Db_main');
        $this->db_main->connect();

        // Logs db object.
        $this->db_logs = $this->di->get('Db_logs');
        $this->db_logs->connect();

        // User id and permissions.
        $this->session = $this->di->getShared('Session');

        if ($this->session->data('user_id') !== null) {

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

        return (int) $this->db_main->getResult() === 0 ? false : true;
    }

    /**
     * @param string $name
     * @return string|array
     * @throws Exception
     */
    protected function getGlobalSetting(string $name) {

        // Get setting from the db.
        $sql = <<<SQL
SELECT setting_value
    FROM settings
    WHERE setting_name = ?
SQL;

        $this->db_main->run($sql, [$name]);
        $setting_value = $this->db_main->getResult();

        // If not found, get default setting.
        if (empty($setting_value)) {

            $setting_value = $this->app_settings->default_global_settings[$name];
        }

        // Error, we must have a setting value.
        if (empty($setting_value)) {

            throw new Exception("could not find global setting", 500);
        }

        return $setting_value;
    }

    /**
     * Remove database handles form memory.
     */
    public function __destruct() {

        $this->db_main->close();
        $this->db_logs->close();
    }
}
