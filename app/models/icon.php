<?php

namespace LibrarianApp;

use Exception;
use Librarian\Cache\FileCache;
use Psr\Http\Message\StreamInterface;
use Librarian\Media\Pdf;

/**
 * Model to handle PDF icons.
 *
 * @method StreamInterface readIcon(integer $id) Read PDF icon.
 */
final class IconModel extends AppModel {

    /**
     * @var FileCache
     */
    private $cache;

    /**
     * @var Pdf
     */
    private $pdf_obj;

    /**
     * Open local icon and return a Stream.
     *
     * @param integer $item_id
     * @return StreamInterface
     * @throws Exception
     */
    protected function _readIcon(int $item_id) {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Get PDF hash.
        $sql = <<<'EOT'
SELECT file_hash
    FROM items
    WHERE id = ?
EOT;

        $this->db_main->run($sql, [$item_id]);
        $hash = $this->db_main->getResult();

        $this->db_main->commit();

        // No PDF, return the SVG placeholder as icon.
        if (empty($hash)) {

            return $this->readFile(IL_APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'nopdf.svg');
        }

        // First, try to get the icon path from the cache.
        $this->cache = $this->di->getShared('FileCache');

        $this->cache->context('icons');
        $key = $this->cache->key($item_id);

        // We must provide the PDF hash to not get a stale icon.
        $icon = $this->cache->get($key, $hash);

        // No icon in cache. Create one.
        if (empty($icon)) {

            $pdf_file = $this->idToPdfPath($item_id);
            $this->pdf_obj = $this->di->get('Pdf', $pdf_file);
            $temp_icon = $this->pdf_obj->icon();

            // Save created icon to the cache.
            $save = $this->cache->set($key, $temp_icon, $hash);

            if ($save === true) {

                $icon = $this->cache->get($key, $hash);
            }
        }

        return $this->readFile($icon);
    }
}
