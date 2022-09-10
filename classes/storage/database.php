<?php

namespace Librarian\Storage;

use Exception;
use PDO;
use PDOException;
use PDOStatement;

class Database {

    /**
     * @var PDO
     */
    private PDO $dbh;

    /**
     * Current PDOStatement.
     *
     * @var PDOStatement
     */
    private PDOStatement $stmt;

    private string $engine     = 'sqlite';       // Database engine (mysql, pgsql, sqlite)
    private string $host       = '127.0.0.1';    // Database server host.
    private string $port       = '3306';         // Database server port.
    private string $username   = '';             // Database username.
    private string $password   = '';             // Database password.
    private string $dbname     = '';             // Database name (SQLite file name).
    private array  $options    = [];             // Connection options.
    private array  $functions  = [];             // Custom functions.
    private array  $collations = [];             // Custom collations.

    /**
     * Inject db settings.
     *
     * @param array $args
     */
    public function __construct(array $args) {

        // Inject database connection settings.
        $this->engine     = empty($args['engine'])     ? $this->engine     : $args['engine'];
        $this->host       = empty($args['host'])       ? $this->host       : $args['host'];
        $this->port       = empty($args['port'])       ? $this->port       : $args['port'];
        $this->username   = empty($args['username'])   ? $this->username   : $args['username'];
        $this->password   = empty($args['password'])   ? $this->password   : $args['password'];
        $this->dbname     = empty($args['dbname'])     ? $this->dbname     : $args['dbname'];
        $this->options    = empty($args['options'])    ? $this->options    : $args['options'];
        $this->functions  = empty($args['functions'])  ? $this->functions  : $args['functions'];
        $this->collations = empty($args['collations']) ? $this->collations : $args['collations'];
    }

    /**
     * Connect to database.
     *
     * @throws Exception
     */
    public function connect(): void {

        switch ($this->engine) {

            case 'sqlite':
                $this->dbh = new PDO("sqlite:$this->dbname", null, null, $this->options);

                // Default PRAGMAs.
                $this->dbh->exec('PRAGMA secure_delete = OFF');
                $this->dbh->exec('PRAGMA foreign_keys  = ON');
                $this->dbh->exec('PRAGMA synchronous   = NORMAL');

                // Register functions.
                foreach ($this->functions as $name => $function) {

                    $this->dbh->sqliteCreateFunction($name, $function);
                }

                // Register custom collations.
                foreach ($this->collations as $name => $function) {

                    $this->dbh->sqliteCreateCollation($name, $function);
                }

                break;

            case 'mysql':
                $dsn = "mysql:host=$this->host;port=$this->port;dbname=$this->dbname;charset=utf8";
                $this->dbh = new PDO($dsn, $this->username, $this->password, $this->options);
                break;

            case 'pgsql':
                $this->dbh = new PDO(
                    "pgsql:host=$this->host;" .
                    "port=$this->port;" .
                    "dbname=$this->dbname;" .
                    "user=$this->username;" .
                    "password=$this->password"
                );
                break;

            default:
                throw new Exception("unknown database driver <kbd>$this->engine</kbd>", 500);
        }

        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Begin transaction.
     */
    public function beginTransaction(): void {

        // MySQL may require closing the cursor.
        if (isset($this->stmt)) {

            $this->stmt->closeCursor();
        }

        $this->dbh->beginTransaction();
    }

    /**
     * Roll back transaction.
     */
    public function rollBack(): void {

        if ($this->dbh->inTransaction() === true) {

            $this->dbh->rollBack();
        }
    }

    /**
     * Commit transaction.
     */
    public function commit(): void {

        if ($this->dbh->inTransaction() === true) {

            $this->dbh->commit();
        }
    }

    /**
     * Close database connection.
     */
    public function close(): void {

        unset($this->stmt);
        unset($this->dbh);
    }

    /**
     * Export PDO object to use PDO functions not implemented in this class.
     *
     * @return PDO
     */
    public function getPDO(): PDO {

        return $this->dbh;
    }

    /**
     * Export the current PDOStatement object to use PDO functions not implemented in this class.
     *
     * @return PDOStatement
     */
    public function getPDOStatement(): PDOStatement {

        return $this->stmt;
    }

    /**
     * Bind statement parameters according to their types.
     *
     * @param  array $columns
     */
    private function bind(array $columns): void {

        // Sanitize keys.
        $columns = array_values($columns);

        foreach ($columns as $i => $column) {

            // Default parameter type is string.
            $PARAM = PDO::PARAM_STR;

            if (is_bool($column)) {

                // Boolean type.
                $PARAM = PDO::PARAM_BOOL;

            } elseif (is_null($column)) {

                // NULL type.
                $PARAM = PDO::PARAM_NULL;

            } elseif (is_int($column)) {

                // Integer type.
                $PARAM = PDO::PARAM_INT;

            } elseif (is_resource($column)) {

                // Stream.
                $PARAM = PDO::PARAM_LOB;

            } elseif (bin2hex(mb_substr($column, 0, 3)) === '1f8b08') {

                // GZ encoded bytes.
                $PARAM = PDO::PARAM_LOB;
            }

            $bound = $this->stmt->bindValue($i + 1, $column, $PARAM);

            // On fail.
            if ($bound === false) {

                $this->rollBack();
                $this->close();
            }
        }
    }

    /**
     * Method for simple queries. Complex queries can be done with PDO methods.
     *
     * @param string $sql
     * @param array|null $columns
     * @return bool
     */
    public function run(string $sql, array $columns = null): bool {

        try {

            $this->stmt = $this->dbh->prepare($sql);

            // Bind parameters.
            if (isset($columns)) {

                $this->bind($columns);
            }

            // Execute statement.
            $execute = $this->stmt->execute();

        } catch (PDOException $ex) {

            throw new PDOException("{$ex->getMessage()} {$ex->getTraceAsString()}", 500);
        }

        // Execute failed.
        if ($execute === false) {

            $this->rollBack();
            $this->close();
            return false;
        }

        return true;
    }

    /**
     * Get last inserted ID. May not work in PostgreSQL.
     *
     * @return int
     */
    public function lastInsertId(): int {

        return (int) $this->dbh->lastInsertId();
    }

    /**
     * One row, one column result.
     *
     * @return string|false
     */
    public function getResult() {

        return $this->stmt->fetchColumn();
    }

    /**
     * One row from results.
     *
     * @param  int $FETCH_STYLE
     * @return array|false
     */
    public function getResultRow(int $FETCH_STYLE = PDO::FETCH_ASSOC) {

        return $this->stmt->fetch($FETCH_STYLE);
    }

    /**
     * All rows from results.
     *
     * @param int $FETCH_STYLE
     * @param $FETCH_ARG
     * @return array|false
     */
    public function getResultRows(int $FETCH_STYLE = PDO::FETCH_ASSOC, $FETCH_ARG = null) {

        if (isset($FETCH_ARG)) {

            return $this->stmt->fetchAll($FETCH_STYLE, $FETCH_ARG);
        }

        return $this->stmt->fetchAll($FETCH_STYLE);
    }
}
