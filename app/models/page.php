<?php

namespace LibrarianApp;

use Exception;
use Librarian\Cache\FileCache;
use Psr\Http\Message\StreamInterface;
use Librarian\Media\Pdf;

/**
 * Model to handle PDF pages.
 *
 * @method StreamInterface getCroppedPage(int $item_id, int $number, int $x, int $y, int $width, int $height, int $zoom)
 * @method StreamInterface getPage(int $item_id, int $number, int $zoom = 300) Read PDF page.
 * @method StreamInterface getPreview(int $item_id, int $number) PDF page small preview.
 */
final class PageModel extends AppModel {

    /**
     * @var FileCache
     */
    private $cache;

    /**
     * @var Pdf
     */
    private $pdf_obj;

    /**
     * Open local page and return a Stream.
     *
     * @param int $item_id
     * @param int $number
     * @param int $zoom
     * @return StreamInterface
     * @throws Exception
     */
    protected function _getPage(int $item_id, int $number, int $zoom = 300): StreamInterface {

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

        // No PDF!!!
        if (empty($hash)) {

            throw new Exception('page not found', 404);
        }

        // First, try to get the page path from the cache.
        $this->cache = $this->di->getShared('FileCache');

        $this->cache->context('pages');
        $key = $this->cache->key([$item_id, $number, $zoom]);

        // We must provide the PDF hash to not get a stale page.
        $page = $this->cache->get($key, $hash);

        // No page in cache. Create one.
        if (empty($page)) {

            $pdf_file = $this->idToPdfPath($item_id);
            $this->pdf_obj = $this->di->get('Pdf', $pdf_file);
            $temp_page = $this->pdf_obj->pageToImage($number, 'jpg', $zoom);

            // Save created page to the cache.
            $save = $this->cache->set($key, $temp_page, $hash);

            if ($save === true) {

                $page = $this->cache->get($key, $hash);
            }
        }

        return $this->readFile($page);
    }

    /**
     * Open local page and return a Stream.
     *
     * @param  int $item_id
     * @param  int $number
     * @return StreamInterface
     * @throws Exception
     */
    protected function _getPreview(int $item_id, int $number): StreamInterface {

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

        // No PDF!!!
        if (empty($hash)) {

            throw new Exception('page not found', 404);
        }

        // First, try to get the page path from the cache.
        $this->cache = $this->di->getShared('FileCache');

        $this->cache->context('pages');
        $key = $this->cache->key(['preview2', $item_id, $number]);

        // We must provide the PDF hash to not get a stale page.
        $page = $this->cache->get($key, $hash);

        // No page in cache. Create one.
        if (empty($page)) {

            $pdf_file = $this->idToPdfPath($item_id);
            $this->pdf_obj = $this->di->get('Pdf', $pdf_file);
            $temp_page = $this->pdf_obj->pagePreview($number);

            // Save created page to the cache.
            $save = $this->cache->set($key, $temp_page, $hash);

            if ($save === true) {

                $page = $this->cache->get($key, $hash);
            }
        }

        return $this->readFile($page);
    }

    /**
     * Get page crop as an image.
     *
     * @param int $item_id
     * @param int $number
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @param int $zoom
     * @return StreamInterface
     * @throws Exception
     */
    protected function _getCroppedPage(int $item_id, int $number, int $x, int $y, int $width, int $height, int $zoom): StreamInterface {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->commit();

        $pdf_file = $this->idToPdfPath($item_id);
        $this->pdf_obj = $this->di->get('Pdf', $pdf_file);
        $cropped = $this->pdf_obj->cropPageToImage($number, $x, $y, $width, $height, $zoom);

        return $this->readFile($cropped);
    }
}
