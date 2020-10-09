<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Security\Encryption;

/**
 * @method array  createUser(array $data)
 * @method array  readProfile()
 * @method string resetPassword(string $email)
 * @method void   updateProfile(array $profile)
 * @method void   updatePassword(array $profile)
 */
class AccountModel extends AppModel {

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
     * Create user account.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    protected function _createUser(array $data): array {

        $id_hash = $this->encryption->getRandomKey(32);

        $email = !empty($data['email']) ? $data['email'] : null;
        $first_name = !empty($data['first_name']) ? $data['first_name'] : null;
        $last_name = !empty($data['last_name']) ? $data['last_name'] : null;
        $encrypted_password = $this->encryption->hashPassword($data['password']);
        $permissions = $this->app_settings->getGlobal('default_permissions');
        $status = 'A';

        $this->db_main->beginTransaction();

        // First account is always Admin.
        $sql_count = <<<SQL
SELECT count(*) FROM users
SQL;

        $this->db_main->run($sql_count);
        $user_count = (int) $this->db_main->getResult();
        $permissions = $user_count === 0 ? 'A' : $permissions;

        $sql = <<<'EOT'
INSERT OR IGNORE INTO users
    (id_hash, username, password, email, first_name, last_name, permissions, status, added_time, changed_time)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
EOT;

        $columns = [
            $id_hash,
            $data['username'],
            $encrypted_password,
            $email,
            $first_name,
            $last_name,
            $permissions,
            $status
        ];

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
                return $this->_createUser($data);
            }

            // Check uniqueness of username.
            $sql = <<<'EOT'
SELECT id
    FROM users
    WHERE username=?
EOT;

            $columns = [
                $data['username']
            ];

            $this->db_main->run($sql, $columns);
            $id = (integer) $this->db_main->getResult();

            // Username already exists.
            if ($id > 0) {

                $this->db_main->rollBack();
                throw new Exception('username already exists', 403);
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
                throw new Exception('email already exists', 403);
            }
        }

        $this->db_main->commit();

        return ['id' => $id];
    }

    /**
     * Update user profile.
     *
     * @param array $profile
     * @throws Exception
     */
    protected function _updateProfile(array $profile): void {

        $placeholder_arr = [];
        $columns = [];
        $allowed_columns = [
            'username',
            'email',
            'first_name',
            'last_name'
        ];

        foreach ($profile as $key => $value) {

            if (in_array($key, $allowed_columns) === false) {

                continue;
            }

            $placeholder_arr[] = "{$key}=?";
            $columns[] = $value;
        }

        // WHERE
        $columns[] = $this->user_id;

        $placeholders = join(', ', $placeholder_arr);

        $sql = <<<EOT
UPDATE users
    SET {$placeholders}, changed_time = CURRENT_TIMESTAMP
    WHERE id = ?
EOT;

        $this->db_main->run($sql, $columns);
    }

    /**
     * Get user profile.
     *
     * @return array
     * @throws Exception
     */
    protected function _readProfile(): array {

        $sql = <<<EOT
SELECT username, first_name, last_name, email
    FROM users
    WHERE id = ?
EOT;

        $this->db_main->run($sql, [$this->user_id]);
        $profile = $this->db_main->getResultRow();

        return $profile;
    }

    /**
     * Change user password.
     *
     * @param array $profile
     * @throws Exception
     */
    protected function _updatePassword(array $profile): void {

        $sql = <<<'EOT'
SELECT password
    FROM users
    WHERE id = ?
EOT;

        $this->db_main->beginTransaction();

        $this->db_main->run($sql, [$this->user_id]);
        $stored_password = $this->db_main->getResult();

        if($this->encryption->verifyPassword($profile['old_password'], $stored_password) === false) {

            $this->db_main->rollBack();
            throw new Exception('wrong password', 400);
        }

        $sql = <<<EOT
UPDATE users
    SET password = ?, changed_time = CURRENT_TIMESTAMP
    WHERE id = ?
EOT;

        $columns = [
            $this->encryption->hashPassword($profile['new_password']),
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);

        $this->db_main->commit();
    }

    /**
     * Password reset by user.
     *
     * @param string $email
     * @return string
     * @throws Exception
     */
    protected function _resetPassword(string $email): string {

        // Generate password.
        $password = $this->encryption->getRandomKey(12);

        $sql = <<<EOT
UPDATE users
    SET password = ?, changed_time = CURRENT_TIMESTAMP
    WHERE email = ? OR username = ?
EOT;

        $columns = [
            $this->encryption->hashPassword($password),
            $email,
            $email
        ];

        $this->db_main->run($sql, $columns);

        // Was query successful?
        $count = (int) $this->db_main->getPDOStatement()->rowCount();

        return $count === 1 ? $password : '';
    }
}
