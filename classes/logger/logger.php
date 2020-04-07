<?php

namespace Librarian\Logger;

use Exception;
use Librarian\Storage\Database;

final class Logger {

    /**
     * @var Database
     */
    protected $db_log;

    /**
     * Logger constructor.
     *
     * @param Database $db_log
     * @throws Exception
     */
    public function __construct(Database $db_log) {

        $this->db_log = $db_log;
        $db_log->connect();
    }

    /**
     * @param $user_id
     * @param $item_id
     * @param $page
     */
    public function logPage($user_id, $item_id, $page): void {

        $sql_insert = <<<'EOT'
INSERT OR REPLACE
    INTO pages (item_id, user_id, page)
    VALUES(?, ?, ?)
EOT;

        $columns = [
            $item_id,
            $user_id,
            $page
        ];

        $this->db_log->run($sql_insert, $columns);
    }

    /**
     * @param $user_id
     * @param $item_id
     */
    public function itemOpened($user_id, $item_id): void {}

    /**
     * @param $user_id
     * @param $item_id
     */
    public function pdfDownloaded($user_id, $item_id): void {}

    /**
     * Destructor closes db connection.
     */
    public function __destruct() {

        $this->db_log->close();
    }
}
