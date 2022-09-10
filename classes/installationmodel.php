<?php

namespace Librarian;

use Exception;
use Librarian\Mvc\Model;
use PDO;

/**
 * Class InstallationModel
 *
 * @method void createReferenceTypeIndex()
 * @method void createTables(bool $force = false)
 */
class InstallationModel extends Model {

    /**
     * Create all data folders.
     *
     * @throws Exception
     */
    public function createFolders(): void {

        // Check if IL_DATA_PATH exists and is writable.
        if (is_writable(IL_DATA_PATH) === false) {

            throw new Exception("directory <kbd>" . IL_DATA_PATH. "</kbd> must be writable by the server");
        }

        // Folders to create.
        $folders = [
            IL_DATA_PATH . DIRECTORY_SEPARATOR . 'import',
            IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'icons',
            IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'pages',
            IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'temp',
            IL_DB_PATH,
            IL_PDF_PATH,
            IL_SUPPLEMENT_PATH,
            IL_DATA_PATH . DIRECTORY_SEPARATOR . 'sessions'
        ];

        foreach ($folders as $folder) {

            if (is_dir($folder) === false) {

                $mkdir = mkdir($folder, 0755, true);

                if ($mkdir === false) {

                    throw new Exception("directory <kbd>$folder</kbd> could not be created", 500);
                }
            }
        }
    }

    /**
     * Create database.
     *
     * @param  boolean $force Set to true to force install, even if the database exists.
     * @return void
     * @throws Exception
     */
    protected function _createTables(bool $force = false): void {

        /*
         * Main db.
         */

        $this->db_main = $this->di->get('Db_main');
        $this->db_main->connect();

        /** @var PDO $pdo */
        $pdo = $this->db_main->getPDO();

        // Core tables.

        // Skip, if tables were already created. Better performance.
        if ($force === false) {

            $this->db_main->run('PRAGMA table_info(items)');
            $row = $this->db_main->getResultRow();
        }

        if (isset($row['cid']) === false) {

            // Create DB tables script.
            $create_db_file = IL_APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'main.sql';

            if (is_readable($create_db_file) === false) {

                throw new Exception("installation failed: could not read the <kbd>$create_db_file</kbd> file");
            }

            $sql = file_get_contents($create_db_file);

            $pdo->exec($sql);
        }

        // Index tables.

        // Skip, if tables were already created. Better performance.
        if ($force === false) {

            $this->db_main->run('PRAGMA table_info(ind_items)');
            $row = $this->db_main->getResultRow();
        }

        if (isset($row['cid']) === false) {

            // Create DB tables script.
            $create_db_file = IL_APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'fts.sql';

            if (is_readable($create_db_file) === false) {

                throw new Exception("installation failed: could not read the <kbd>$create_db_file</kbd> file");
            }

            $sql = file_get_contents($create_db_file);

            $pdo->exec($sql);
        }

        $this->db_main->close();
        $row = null;

        /*
         * Logs db.
         */

        $this->db_logs = $this->di->get('Db_logs');
        $this->db_logs->connect();

        // Skip, if tables were already created. Better performance.
        if ($force === false) {

            $this->db_logs->run('PRAGMA table_info(last_pages)');
            $row = $this->db_logs->getResultRow();
        }

        if (isset($row['cid']) === false) {

            // Create DB tables script.
            $create_db_file = IL_APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'logs.sql';

            if (is_readable($create_db_file) === false) {

                throw new Exception("installation failed: could not read the <kbd>$create_db_file</kbd> file");
            }

            $sql = file_get_contents($create_db_file);

            /** @var PDO $pdo */
            $pdo = $this->db_logs->getPDO();
            $pdo->exec($sql);
        }

        $this->db_logs->close();
        $row = null;

        /*
         * CSL styles db.
         */
        $from = IL_APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'styles.db';
        $to = IL_DB_PATH . DIRECTORY_SEPARATOR . 'styles.db';

        if (is_readable($from) === true && file_exists($to) === false) {

            copy($from, $to);
        }
    }

    /**
     * Add index for reference type filtering. This update is backwards compatible.
     *
     * @throws Exception
     */
    protected function _createReferenceTypeIndex(): void {

        $this->db_main = $this->di->get('Db_main');
        $this->db_main->connect();

        $this->db_main->run('PRAGMA index_info(ix_items_reference_type)');
        $row = $this->db_main->getResultRow();

        if (isset($row['cid']) === false) {

            // Create DB tables script.
            $sql_file = IL_APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'main_update_1.sql';

            if (is_readable($sql_file) === false) {

                throw new Exception("update failed: could not read the <kbd>$sql_file</kbd> file");
            }

            $sql = file_get_contents($sql_file);

            /** @var PDO $pdo */
            $pdo = $this->db_main->getPDO();
            $pdo->exec($sql);
        }

        $this->db_main->close();
    }
}
