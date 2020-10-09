<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Security\Encryption;
use PDO;

/**
 * @method array adminCreateUser(array $data)
 * @method array list()
 * @method array adminUpdateUser(array $profile)
 */
class UsersModel extends AppModel {

    /**
     * @var Encryption
     */
    private $encryption;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->encryption = $this->di->getShared('Encryption');
    }

    /**
     * List of users.
     *
     * @return array
     * @throws Exception
     */
    protected function _list(): array {

        $this->db_main->beginTransaction();

        if ($this->permissions !== 'A') {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to perform this action', 403);
        }

        $sql = <<<'EOT'
SELECT id_hash, username, first_name, last_name, email, permissions, status, added_time, changed_time
    FROM users
    ORDER BY CASE status WHEN 'A' THEN 1 WHEN 'S' THEN 2 WHEN 'D' THEN 3 END, username COLLATE utf8Collation
EOT;

        $this->db_main->run($sql);
        $output = $this->db_main->getResultRows();

        $this->db_main->commit();

        return $output;
    }

    /**
     * Create user account.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    protected function _adminCreateUser(array $data) {

        $id_hash = $this->encryption->getRandomKey(32);

        $first_name = !empty($data['first_name']) ? $data['first_name'] : null;
        $last_name = !empty($data['last_name']) ? $data['last_name'] : null;
        $encrypted_password = $this->encryption->hashPassword($data['password']);
        $permissions = $data['permissions'];
        $status = 'A';

        $sql = <<<'EOT'
INSERT OR IGNORE INTO users
    (id_hash, username, password, email, first_name, last_name, permissions, status, added_time, changed_time)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
EOT;

        $columns = [
            $id_hash,
            $data['username'],
            $encrypted_password,
            $data['email'],
            $first_name,
            $last_name,
            $permissions,
            $status
        ];

        $this->db_main->beginTransaction();

        if ($this->permissions !== 'A') {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to perform this action', 403);
        }

        $this->db_main->run($sql, $columns);
        $id = (integer) $this->db_main->lastInsertId();

        // Failed.
        if ($id === 0) {

            // Check uniqueness of id_hash.
            $sql = <<<'EOT'
SELECT id
    FROM users
    WHERE id_hash = ?
EOT;

            $columns = [
                $id_hash
            ];

            $this->db_main->run($sql, $columns);
            $id = (integer) $this->db_main->getResult();

            // Id hash already exists, try again.
            if ($id > 0) {

                $this->db_main->rollBack();
                return $this->_adminCreateUser($data);
            }

            // Check uniqueness of username.
            $sql = <<<'EOT'
SELECT id
    FROM users
    WHERE username = ?
EOT;

            $columns = [
                $data['username']
            ];

            $this->db_main->run($sql, $columns);
            $id = (integer) $this->db_main->getResult();

            // Username already exists.
            if ($id > 0) {

                $this->db_main->rollBack();
                throw new Exception('username already exists');
            }

            // Check uniqueness of email.
            $sql = <<<'EOT'
SELECT id
    FROM users
    WHERE email = ?
EOT;

            $columns = [
                $data['email']
            ];

            $this->db_main->run($sql, $columns);
            $id = (integer) $this->db_main->getResult();

            // Email already exists.
            if ($id > 0) {

                $this->db_main->rollBack();
                throw new Exception('email already exists');
            }
        }

        $this->db_main->commit();

        return ['id' => $id];
    }

    /**
     * Update user - admin version.
     *
     * @param array $profile
     * @return array
     * @throws Exception
     */
    protected function _adminUpdateUser(array $profile): array {

        $placeholder_arr = [];
        $columns = [];
        $allowed_columns = [
            'email',
            'first_name',
            'last_name',
            'permissions',
            'status',
            'password'
        ];

        foreach ($profile as $key => $value) {

            if (in_array($key, $allowed_columns) === false) {

                continue;
            }

            $value = $this->sanitation->emptyToNull($value);

            // Allowed permissions.
            if ($key === 'permissions' && in_array($value, ['A', 'U', 'G']) === false) {

                continue;
            }

            // Allowed statuses.
            if ($key === 'status' && in_array($value, ['A', 'S', 'D']) === false) {

                continue;
            }

            if ($key === 'password' && !empty($value)) {

                $value = $this->encryption->hashPassword($value);
            }

            $placeholder_arr[] = "{$key}=?";
            $columns[] = $value;
        }

        // WHERE
        $columns[] = $profile['username'];

        $placeholders = join(', ', $placeholder_arr);

        $sql = <<<EOT
UPDATE users
    SET {$placeholders}, changed_time = CURRENT_TIMESTAMP
    WHERE username = ?
EOT;

        $this->db_main->beginTransaction();

        if ($this->permissions !== 'A') {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to perform this action', 403);
        }

        $this->db_main->run($sql, $columns);

        // User must be logged out, send session ids.
        $sql = <<<EOT
SELECT session_id
    FROM sessions
    WHERE user_id = (SELECT id FROM users WHERE username = ?)
EOT;

        $this->db_main->run($sql, [$profile['username']]);
        $sessions = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

        $this->db_main->commit();

        return $sessions;
    }
}
