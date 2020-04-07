<?php

namespace LibrarianApp;

use Exception;

/**
 * @method string getFirstName() Get the string to represent user in the side menu.
 * @method int numUsers() Get the number of existing users.
 */
class MainModel extends AppModel {

    /**
     * Get the number of existing users.
     *
     * @return integer
     * @throws Exception
     */
    protected function _numUsers(): int {

        $this->db_main->run("SELECT count(*) FROM users");
        $num_users = $this->db_main->getResult();

        return $num_users;
    }

    /**
     * Get the string to represent user in the side menu.
     *
     * @return string
     * @throws Exception
     */
    protected function _getFirstName(): string {

        $this->db_main->run("SELECT username, first_name FROM users WHERE id = ?", [$this->user_id]);
        $row = $this->db_main->getResultRow();

        return !empty($row['first_name']) ? $row['first_name']: $row['username'];
    }
}
