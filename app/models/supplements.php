<?php

namespace LibrarianApp;

use Exception;
use Librarian\Http\Psr\Message\StreamInterface;

/**
 * Class SupplementsModel.
 *
 * @method void  delete(int $item_id, string $filename)
 * @method StreamInterface download(int $item_id, string $filename)
 * @method array imagelist(int $item_id)
 * @method array list(int $item_id)
 * @method void  rename(int $item_id, string $oldname, string $newname)
 * @method void  save(int $item_id, StreamInterface $stream, string $client_filename)
 * @method void  saveGraphicalAbstract(int $item_id, StreamInterface $stream)
 */
class SupplementsModel extends AppModel {

    /**
     * Download.
     *
     * @param int $item_id
     * @param string $filename
     * @return StreamInterface
     * @throws Exception
     */
    protected function _download(int $item_id, string $filename) {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->commit();

        return $this->readFile($this->idToSupplementPath($item_id) . rawurlencode($filename));
    }

    /**
     * Save.
     *
     * @param int $item_id
     * @param StreamInterface $stream
     * @param string $client_filename
     * @return void
     * @throws Exception
     */
    protected function _save(int $item_id, StreamInterface $stream, string $client_filename): void {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->commit();

        // Save supplementary file.
        setlocale(LC_ALL,'en_US.UTF-8');
        $filename = pathinfo($client_filename,  PATHINFO_FILENAME);
        $extension = pathinfo($client_filename, PATHINFO_EXTENSION);

        /*
         * Shorten the filename. Filenames are stored encoded in RFC 3986. Some
         * UTF-8 filenames can be longer than allowed in this format.
         */
        while (strlen(rawurlencode($filename)) > 240) {

            $filename = trim(mb_substr($filename, 0, -1, 'UTF-8'));
            $client_filename = $filename . '.' . $extension;
        }

        $filepath = $this->idToSupplementPath($item_id) . rawurlencode($client_filename);
        $this->writeFile($filepath, $stream);
    }

    /**
     * Save file as grapical abstract.
     *
     * @param int $item_id
     * @param StreamInterface $stream
     * @throws Exception
     */
    protected function _saveGraphicalAbstract(int $item_id, StreamInterface $stream): void {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->commit();

        // Save supplementary file.
        $filepath = $this->idToSupplementPath($item_id) . rawurlencode('graphical_abstract');
        $this->writeFile($filepath, $stream);

        // Check if image.
        $mime = $this->file_tools->getMime($filepath);

        if ($mime !== 'image/png' && $mime !== 'image/jpg' && $mime !== 'image/jpeg') {

            $this->deleteFile($filepath);
            throw new Exception('graphical abstract must be a JPG or PNG image', 400);
        }
    }

    /**
     * Rename.
     *
     * @param int $item_id
     * @param string $oldname
     * @param string $newname
     * @throws Exception
     */
    protected function _rename(int $item_id, string $oldname, string $newname): void {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->commit();

        // New filename.
        setlocale(LC_ALL,'en_US.UTF-8');
        $filename = pathinfo($newname,  PATHINFO_FILENAME);
        $extension = pathinfo($newname, PATHINFO_EXTENSION);

        /*
         * Shorten the filename. Filenames are stored encoded in RFC 3986. Some
         * UTF-8 filenames can be longer than allowed in this format.
         */
        while (strlen(rawurlencode($filename)) > 240) {

            $filename = trim(mb_substr($filename, 0, -1, 'UTF-8'));
            $newname = $filename . '.' . $extension;
        }

        $oldfilepath = $this->idToSupplementPath($item_id) . rawurlencode($oldname);
        $newfilepath = $this->idToSupplementPath($item_id) . rawurlencode($newname);

        // Windows fix.
        if (is_writable($newfilepath)) {

            unlink($newfilepath);
        }

        rename($oldfilepath, $newfilepath);
    }

    /**
     * List.
     *
     * @param $item_id
     * @return array
     * @throws Exception
     */
    protected function _list($item_id) {

        $output = [
            'title' => '',
            'files' => []
        ];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Title.
        $sql = <<<EOT
SELECT
    title
    FROM items
    WHERE items.id = ?
EOT;

        $this->db_main->run($sql, [$item_id]);
        $output['title'] = $this->db_main->getResult();

        $this->db_main->commit();

        // List supplementary files.
        $filepath = $this->idToSupplementPath($item_id);
        $files = glob($filepath . "*");

        foreach ($files as $filename) {

            $output['files'][] = [
                'name' => rawurldecode(substr(basename($filename), 9)),
                'mime' => $this->file_tools->getMime($filename)
            ];
        }

        return $output;
    }

    /**
     * Delete.
     *
     * @param $item_id
     * @param $filename
     * @throws Exception
     */
    protected function _delete($item_id, $filename): void {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->commit();

        // Delete supplementary file.
        $filepath = $this->idToSupplementPath($item_id) . rawurlencode($filename);
        $this->deleteFile($filepath);
    }

    /**
     * Get a list of images for TinyMCE.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _imagelist(int $item_id): array {

        $output = [];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->commit();

        // List supplementary files.
        $filepath = $this->idToSupplementPath($item_id);
        $files = glob($filepath . "*");

        foreach ($files as $filename) {

            if (in_array($this->file_tools->getMime($filename), ['image/png', 'image/jpeg', 'image/jpg']) === false) {

                continue;
            }

            $output[] = rawurldecode(substr(basename($filename), 9));
        }

        return $output;
    }
}
