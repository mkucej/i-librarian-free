<?php

namespace Librarian\Logger;

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;
use Exception;
use Librarian\Storage\Database;

final class Reporter {

    /**
     * @var Database
     */
    protected $db_log;

    /**
     * Reporter constructor.
     *
     * @param Database $db_log
     * @throws Exception
     */
    public function __construct(Database $db_log) {

        $this->db_log = $db_log;
        $this->db_log->connect();
    }

    /**
     * @param $user_id
     * @param $item_id
     * @return int
     * @throws Exception
     */
    public function lastPage($user_id, $item_id): int {

        $sql_select = <<<'SQL'
SELECT page
    FROM last_pages
    WHERE item_id = ? AND user_id = ?
SQL;

        $columns = [
            $item_id,
            $user_id
        ];

        $this->db_log->run($sql_select, $columns);
        $last_page = (int) $this->db_log->getResult();

        return $last_page === 0 ? 1 : $last_page;
    }

    /**
     * Get time period based on time.
     *
     * @param $time
     * @return DatePeriod
     * @throws Exception
     */
    public function timePeriod($time = 30): DatePeriod {

        switch ($time) {

            case 12:

                // Create an array with date range.
                $start = new DateTime('13 months ago', new DateTimeZone('UTC'));
                $start->modify('first day of this month');
                $end = new DateTime('now', new DateTimeZone('UTC'));
                $end->modify('first day of this month');
                $interval = new DateInterval('P1M');
                $period = new DatePeriod($start, $interval, $end);
                break;

            default:

                // Create an array with date range.
                $start = new DateTime('31 days ago', new DateTimeZone('UTC'));
                $end = new DateTime('now', new DateTimeZone('UTC'));
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end);
                break;
        }

        return $period;
    }

    /**
     * Destructor closes db connection.
     */
    public function __destruct() {

        $this->db_log->close();
    }
}
