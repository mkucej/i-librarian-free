<?php

namespace Librarian\Mvc;

use Exception;
use Librarian\AppSettings;
use Librarian\Container\DependencyInjector;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Librarian\Media\Binary;
use Librarian\Media\FileTools;
use Librarian\Security\Sanitation;
use Librarian\Security\Session;
use Librarian\Security\Validation;
use Librarian\Storage\Database;

/**
 * Class Model
 *
 * @method StreamInterface readPdf(int $id)
 */
abstract class Model {

    /**
     * @var DependencyInjector
     */
    protected DependencyInjector $di;

    /**
     * @var AppSettings
     */
    protected AppSettings $app_settings;

    /**
     * @var Binary
     */
    protected Binary $binary;

    /**
     * @var Database main
     */
    protected Database $db_main;

    /**
     * @var Database logs
     */
    protected Database $db_logs;

    /**
     * @var FileTools
     */
    protected FileTools $file_tools;

    /**
     * @var string User permissions G|U|A.
     */
    protected string $permissions;

    /**
     * @var Sanitation
     */
    protected Sanitation $sanitation;

    /**
     * @var Validation
     */
    protected Validation $validation;

    /**
     * @var string A user id.
     */
    protected string $user_id;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        $this->di           = $di;
        $this->app_settings = $this->di->getShared('AppSettings');
        $this->file_tools   = $this->di->get('FileTools');
        $this->sanitation   = $this->di->getShared('Sanitation');
        $this->validation   = $this->di->getShared('Validation');
    }

    /**
     * Magic method to call model methods. It either calls a local method,
     * or connects to a remote model.
     *
     * @param string $method
     * @param array $arguments
     * @return array
     * @throws Exception
     * @throws GuzzleException
     */
    public function __call(string $method, $arguments = []) {

        // Call local method.
        if (method_exists($this, '_' . $method) === false) {

            throw new Exception("model method <kbd>_$method</kbd> does not exist", 500);
        }

        $model_data = call_user_func_array([$this, "_{$method}"], $arguments);

        // Return sanitized data by default.
        if (is_scalar($model_data) || is_array($model_data)) {

            $model_data = $this->sanitation->html($model_data);
        }

        return $model_data;
    }

    /**
     * Check if the item ID exists.
     *
     * @param  integer|string $item_id
     * @return bool
     */
    protected function idExists($item_id): bool {

        $sql = <<<'EOT'
SELECT count(*)
    FROM items
    WHERE id = ?
EOT;

        $columns = [
            $item_id
        ];

        $this->db_main->run($sql, $columns);
        $count = (integer) $this->db_main->getResult();

        return $count === 1;
    }

    /**
     * Convert id to subdirectory path part.
     *
     * @param integer|string $id
     * @return string
     * @throws Exception
     */
    protected function getSubPath($id): string {

        $basename = $this->idToBasename($id);

        return substr($basename, 0, 3) . DIRECTORY_SEPARATOR . substr($basename, 3, 3);
    }

    /**
     * Check if local file exists.
     *
     * @param string $filepath
     * @return boolean
     * @throws Exception
     */
    protected function isFile(string $filepath): bool {

        if (is_readable($filepath) === false) {

            return false;
        }

        return true;
    }

    /**
     * Convert id to 9-digit basename.
     *
     * @param  integer|string $id
     * @return string
     * @throws Exception
     */
    protected function idToBasename($id): string {

        return str_pad($id, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Locate PDF file and return PSR Stream.
     *
     * @param integer|string $id
     * @return StreamInterface
     * @throws Exception
     */
    protected function _readPdf($id): StreamInterface {

        $pdf_file = $this->idToPdfPath($id);

        if ($this->isFile($pdf_file) === false) {

            throw new Exception('file not found', 404);
        }

        return $this->readFile($pdf_file);
    }

    /**
     * Check if PDF file exists for this item id.
     *
     * @param integer|string $id
     * @return boolean
     * @throws Exception
     */
    protected function isPdf($id): bool {

        return $this->isFile($this->idToPdfPath($id));
    }

    /**
     * Open local file and return a PSR Stream.
     *
     * @param string $filepath
     * @return StreamInterface
     * @throws Exception
     */
    protected function readFile(string $filepath): StreamInterface {

        try {

            $fp = Utils::tryFopen($filepath, 'r');

        } catch (Exception $exc) {

            $exc = null;
            throw new Exception('could not read file', 500);
        }

        return Utils::streamFor($fp);
    }

    /**
     * @param $filepath
     * @param StreamInterface $stream
     * @return int
     * @throws Exception
     */
    protected function writeFile($filepath, StreamInterface $stream): int {

        setlocale(LC_ALL,'en_US.UTF-8');

        // Make sure the dir exists.
        $this->makeDir(pathinfo($filepath, PATHINFO_DIRNAME));

        $written = 0;

        if (strlen(basename($filepath)) > 250) {

            throw new Exception('filename is too long', 400);
        }

        try {

            $fp = Utils::tryFopen($filepath, 'w');

        } catch (Exception $exc) {

            $exc = null;
            throw new Exception('could not write file', 500);
        }

        while (!$stream->eof()) {

            $fwrite = fwrite($fp, $stream->read(4096));

            if ($fwrite === false) {

                throw new Exception('could not write file', 500);
            }

            $written += $fwrite;
        }

        fclose($fp);

        return $written;
    }

    /**
     * Rename file.
     *
     * @param string $old_name
     * @param string $new_name
     * @return bool
     * @throws Exception
     */
    protected function renameFile(string $old_name, string $new_name): bool {

        setlocale(LC_ALL,'en_US.UTF-8');

        // Make sure the dir exists.
        $this->makeDir(pathinfo($new_name, PATHINFO_DIRNAME));

        if (is_writable($old_name) === false) {

            throw new Exception('permissions prevent renaming the file', 500);
        }

        // Windows fix.
        if (is_writable($new_name)) {

            unlink($new_name);
        }

        $rename = rename($old_name, $new_name);

        if ($rename === false) {

            throw new Exception('file was not renamed', 500);
        }

        return true;
    }

    /**
     * @param $filepath
     * @return bool
     * @throws Exception
     */
    protected function deleteFile($filepath): bool {

        if (is_file($filepath) === false) {

            return true;
        }

        if (is_writable($filepath) === false) {

            throw new Exception('permissions prevent deleting the file', 500);
        }

        $delete = @unlink($filepath);

        if ($delete === false) {

            throw new Exception('file was not deleted', 500);
        }

        return true;
    }

    /**
     * Get PDF path from id.
     *
     * @param integer|string $id
     * @return string
     * @throws Exception
     */
    protected function idToPdfPath($id): string {

        return IL_PDF_PATH . DIRECTORY_SEPARATOR . $this->getSubPath($id) . DIRECTORY_SEPARATOR . $this->idToBasename($id) . '.pdf';
    }

    /**
     * @param $id
     * @return string
     * @throws Exception
     */
    protected function idToSupplementPath($id): string {

        return IL_SUPPLEMENT_PATH . DIRECTORY_SEPARATOR . $this->getSubPath($id) . DIRECTORY_SEPARATOR . $this->idToBasename($id);
    }

    /**
     * @param $dir
     * @return bool
     * @throws Exception
     */
    protected function makeDir($dir): bool {

        $this->validation->dirname($dir);

        if (!is_dir($dir)) {

            $mkdir = mkdir($dir, 0755, true);

            if ($mkdir === false) {

                throw new Exception('could not create the directory', 500);
            }
        }

        return true;
    }
}
