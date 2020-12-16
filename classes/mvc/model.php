<?php

namespace Librarian\Mvc;

use Exception;
use Librarian\AppSettings;
use Librarian\Container\DependencyInjector;
use Librarian\Http\Client\Exception\GuzzleException;
use Librarian\Http\Client\Psr7\Stream;
use Librarian\Http\Client\Psr7\Utils;
use Librarian\Http\Psr\Message\StreamInterface;
use Librarian\Media\Binary;
use Librarian\Media\FileTools;
use Librarian\Queue\Queue;
use Librarian\Security\Sanitation;
use Librarian\Security\Session;
use Librarian\Security\Validation;
use Librarian\Storage\Database;

/**
 * Class Model
 *
 * @method Stream readPdf(int $id)
 */
abstract class Model {

    /**
     * @var DependencyInjector
     */
    protected $di;

    /**
     * @var AppSettings
     */
    protected $app_settings;

    /**
     * @var Binary
     */
    protected $binary;

    /**
     * @var Database main
     */
    protected $db_main;

    /**
     * @var Database logs
     */
    protected $db_logs;

    /**
     * @var FileTools
     */
    protected $file_tools;

    /**
     * @var string User permissions G|U|A.
     */
    protected $permissions;

    /**
     * @var array POST globals.
     */
    protected $post;

    /**
     * @var Sanitation
     */
    protected $sanitation;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Validation
     */
    protected $validation;

    /**
     * @var string A id hash. Most models will require authorization against it.
     */
    protected $id_hash;

    /**
     * @var string A user id.
     */
    protected $user_id;

    /**
     * Constructor.
     *
     * @param  DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        $this->di           = $di;
        $this->app_settings = $this->di->getShared('AppSettings');
        $this->file_tools   = $this->di->get('FileTools');
        $this->sanitation   = $this->di->getShared('Sanitation');
        $this->validation   = $this->di->getShared('Validation');
        $this->session      = $this->di->getShared('Session');
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
    protected function idExists($item_id) {

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
    protected function getSubPath($id) {

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
    protected function isFile(string $filepath) {

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
    protected function idToBasename($id) {

        return str_pad($id, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Locate PDF file and return PSR Stream.
     *
     * @param integer|string $id
     * @return Stream
     * @throws Exception
     */
    protected function _readPdf($id) {

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
    protected function isPdf($id) {

        return $this->isFile($this->idToPdfPath($id));
    }

    /**
     * Open local file and return a PSR Stream.
     *
     * @param string $filepath
     * @return Stream
     * @throws Exception
     */
    protected function readFile(string $filepath) {

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
    protected function deleteFile($filepath) {

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
    protected function idToPdfPath($id) {

        return IL_PDF_PATH . DIRECTORY_SEPARATOR . $this->getSubPath($id) . DIRECTORY_SEPARATOR . $this->idToBasename($id) . '.pdf';
    }

    /**
     * @param $id
     * @return string
     * @throws Exception
     */
    protected function idToSupplementPath($id) {

        return IL_SUPPLEMENT_PATH . DIRECTORY_SEPARATOR . $this->getSubPath($id) . DIRECTORY_SEPARATOR . $this->idToBasename($id);
    }

    /**
     * @param $dir
     * @return bool
     * @throws Exception
     */
    protected function makeDir($dir) {

        $this->validation->dirname($dir);

        if (!is_dir($dir)) {

            $mkdir = mkdir($dir, 0755, true);

            if ($mkdir === false) {

                throw new Exception('could not create the directory', 500);
            }
        }

        return true;
    }

    /**
     * Convert supported file types to PDF and return new file pathname.
     *
     * @param string $filename
     * @return string Temporary PDF file path.
     * @throws Exception
     */
    protected function convertToPdf(string $filename): string {

        $this->binary = $this->di->get("Binary");

        // Attempt conversion.
        if (PHP_OS === 'Linux' || PHP_OS === 'Darwin') {

            putenv('HOME=' . IL_TEMP_PATH);
        }

        /** @var Queue $queue */
        $queue = $this->di->getShared('Queue');

        $queue->wait('binary');

        exec($this->binary->soffice() . ' --invisible --convert-to pdf:writer_pdf_Export --outdir ' .
            escapeshellarg(IL_TEMP_PATH) . ' ' . escapeshellarg($filename)
        );

        $queue->release('binary');

        if (PHP_OS === 'Linux' || PHP_OS === 'Darwin') {

            putenv('HOME=""');
        }

        $new_file = IL_TEMP_PATH . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME) . '.pdf';

        if (is_file($new_file) === false) {

            // Conversion failed, exit.
            return '';
        }

        return  $new_file;
    }
}
