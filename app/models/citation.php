<?php

namespace LibrarianApp;

use Exception;
use Librarian\Media\ScalarUtils;
use Librarian\Media\Xml;
use Librarian\Storage\Database;
use PDO;

/**
 * Class CitationModel.
 *
 * @method void   edit(string $csl)
 * @method string get(string $id)
 * @method string getFromName(string $name)
 * @method array  list()
 * @method void   populate()
 * @method array  search(string $query)
 */
class CitationModel extends AppModel {

    /**
     * @var Database
     */
    private $db_styles;

    /**
     * @var ScalarUtils
     */
    private $scalar_utils;

    /**
     * @var Xml
     */
    private $xml_obj;

    /**
     * List of CSL styles.
     *
     * @return array
     * @throws Exception
     */
    protected function _list(): array {

        $this->scalar_utils = $this->di->getShared('ScalarUtils');

        $this->db_styles = $this->di->get('Db_styles');
        $this->db_styles->connect();

        $sql = <<<'SQL'
SELECT name, modified, id
    FROM styles
SQL;

        $this->db_styles->run($sql);

        while ($row = $this->db_styles->getResultRow(PDO::FETCH_NUM)) {

            $output[] = [
                $this->scalar_utils->deaccent($row[0]),
                $row[1],
                $row[2]
            ];
        }

        $this->db_styles->close();

        return $output;
    }

    /**
     * Edit a CSL style.
     *
     * @param string $csl
     * @throws Exception
     */
    protected function _edit(string $csl): void {

        $this->xml_obj = $this->di->getShared('Xml');

        $this->db_styles = $this->di->get('Db_styles');
        $this->db_styles->connect();

        $sql = <<<'SQL'
INSERT OR REPLACE INTO styles
    (id, name, style, modified) VALUES (?, ?, ?, datetime(?))
SQL;

        $xml = $this->xml_obj->loadXmlString($csl);

        $id = $xml->info->id;
        $name = $xml->info->title;
        $modified = $xml->info->updated;

        if (!empty($id) && !empty($name) && !empty($modified)) {

            $columns = [
                $id,
                $name,
                gzencode($csl, 6),
                $modified
            ];

            $this->db_styles->run($sql, $columns);
        }

        $this->db_styles->close();
    }

    /**
     * Get a CSl style using an ID.
     *
     * @param string $id
     * @return string
     * @throws Exception
     */
    protected function _get(string $id): string {

        $this->db_styles = $this->di->get('Db_styles');
        $this->db_styles->connect();

        $sql = <<<'SQL'
SELECT style
    FROM styles
    WHERE id = ?
SQL;

        $this->db_styles->run($sql, [$id]);
        $output = gzdecode($this->db_styles->getResult());

        $this->db_styles->close();

        return $output;
    }

    /**
     * Get a CSl style using a name.
     *
     * @param string $name
     * @return string
     * @throws Exception
     */
    protected function _getFromName(string $name): string {

        $this->db_styles = $this->di->get('Db_styles');
        $this->db_styles->connect();

        $sql = <<<'SQL'
SELECT style
    FROM styles
    WHERE name = ?
SQL;

        $this->db_styles->run($sql, [$name]);
        $output = gzdecode($this->db_styles->getResult());

        $this->db_styles->close();

        return $output;
    }

    /**
     * Search CSL names.
     *
     * @param string $query
     * @return array
     * @throws Exception
     */
    protected function _search(string $query): array {

        $this->db_styles = $this->di->get('Db_styles');
        $this->db_styles->connect();

        $sql = <<<'SQL'
SELECT name
    FROM styles
    WHERE deaccent(name) LIKE deaccent(?)
    ORDER BY name COLLATE utf8Collation
SQL;

        $query = str_replace(['%', '_'], '', $query);

        $this->db_styles->run($sql, ["%{$query}%"]);
        $output = $this->db_styles->getResultRows(PDO::FETCH_COLUMN);

        $this->db_styles->close();

        return $output;
    }

    /**
     * Populate database from Github files in import/csl.
     *
     * @throws Exception
     */
    protected function _populate(): void {

        $this->db_styles = $this->di->get('Db_styles');
        $this->db_styles->connect();

        $this->xml_obj = $this->di->getShared('Xml');

        $files = glob(IL_DATA_PATH . DIRECTORY_SEPARATOR . 'import' . DIRECTORY_SEPARATOR . 'csl' . DIRECTORY_SEPARATOR . '*.csl', GLOB_NOSORT);

        $sql_delete = <<<'SQL'
DROP TABLE IF EXISTS styles
SQL;

        $this->db_styles->run($sql_delete);

        $sql_create = <<<'SQL'
CREATE TABLE IF NOT EXISTS styles (
    id TEXT PRIMARY KEY,
    name TEXT,
    style BLOB,
    modified TEXT
)
SQL;

        $this->db_styles->run($sql_create);

        $sql_insert = <<<'SQL'
INSERT INTO styles
(id, name, style, modified) VALUES (?, ?, ?, datetime(?))
SQL;

        $this->db_styles->beginTransaction();

        foreach ($files as $file) {

            $csl = file_get_contents($file);

            $xml = $this->xml_obj->loadXmlString($csl);

            $id = $xml->info->id;
            $name = $xml->info->title;
            $modified = $xml->info->updated;

            if (!empty($name) && !empty($csl) && !empty($modified)) {

                $columns = [
                    $id,
                    $name,
                    gzencode($csl, 6),
                    $modified
                ];

                $this->db_styles->run($sql_insert, $columns);
            }
        }

        $this->db_styles->commit();
        $this->db_styles->close();
    }
}
