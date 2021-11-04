<?php

namespace LibrarianApp;

use Exception;
use Librarian\Cache\FileCache;
use \Librarian\Http\Client\Psr7;
use Librarian\Http\Psr\Message\StreamInterface;
use Librarian\Logger\Logger;
use Librarian\Logger\Reporter;
use Librarian\Media\Pdf;
use Librarian\Media\ScalarUtils;
use Librarian\Security\Encryption;
use PDO;
use ZipArchive;

/**
 * Class PdfModel.
 *
 * @method array annotations(int $item_id)
 * @method array bookmarks(int $item_id)
 * @method array boxes(int $item_id, int $page)
 * @method array delete(int $item_id)
 * @method void  deleteHighlights(int $item_id, array $boxes)
 * @method void  extract(int $item_id, bool $boxes = false)
 * @method array info(int $item_id)
 * @method array links(int $item_id, int $page_from, int $page_number)
 * @method void  logPage($item_id, $page)
 * @method array manage(int $item_id)
 * @method Psr7\Stream modifiedPdf(int $item_id, bool $annotations, bool $supplements)
 * @method string pageImage(int $item_id, int $page)
 * @method void  pdfDownloaded($item_id)
 * @method void  save(int $item_id, StreamInterface $file, string $client_filename = null)
 * @method void  saveHighlights(int $item_id, string $color, array $boxes)
 * @method void  saveNote(int $item_id, string $text, int $page = null, int $top = null, int $left = null, int $note_id = null)
 * @method void  saveOcrText(int $item_id, string $text)
 * @method void  saveOcrBoxes(int $item_id, string $boxes)
 * @method array search(int $item_id, string $query, int $page_from)
 */
class PdfModel extends AppModel {

    /**
     * @var FileCache
     */
    private $cache;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Reporter
     */
    private $reporter;

    /**
     * @var Pdf
     */
    private $pdf_object;

    /**
     * Manage.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _manage(int $item_id): array {

        $output = [
            'info' => [
                'name' => '',
                'text' => ''
            ]
        ];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Select title.
        $sql = <<<'EOT'
SELECT title
    FROM items
    WHERE id = ?
EOT;

        $columns = [
            $item_id
        ];

        $this->db_main->run($sql, $columns);
        $output['title'] = $this->db_main->getResult();

        // Does PDF exist?
        if ($this->isPdf($item_id) === false) {

            return $output;
        }

        // Select text.

        $sql = <<<'EOT'
SELECT full_text
    FROM ind_items
    WHERE id = ? AND full_text IS NOT NULL
EOT;

        $columns = [
            (integer) $item_id
        ];

        $this->db_main->run($sql, $columns);
        $compressed = $this->db_main->getResult();
        $text = empty($compressed) ? '' : gzdecode($compressed);

        $output['info']['text'] = mb_strlen($text) > 3000 ? mb_substr($text, 0, 3000) . '...' : $text;

        $this->db_main->commit();

        $pdfpath = $this->idToPdfPath($item_id);
        $output['info']['name'] = basename($pdfpath);

        return $output;
    }

    /**
     * Info.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _info(int $item_id): array {

        $output = [
            'info' => []
        ];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Select title.
        $sql = <<<'EOT'
SELECT title
    FROM items
    WHERE id = ?
EOT;

        $columns = [
            $item_id
        ];

        $this->db_main->run($sql, $columns);
        $output['title'] = $this->db_main->getResult();

        $this->db_main->commit();

        // Does PDF exist?
        if ($this->isPdf($item_id) === false) {

            return $output;
        }

        $pdfpath = $this->idToPdfPath($item_id);

        // PDF info.
        $this->pdf_object = $this->di->get('Pdf', $pdfpath);

        $output['info'] = $this->pdf_object->info();

        // Last page read.
        $this->reporter = $this->di->get('Reporter');
        $output['last_read'] = $this->reporter->lastPage($this->user_id, $item_id);

        return $output;
    }

    /**
     * Save file.
     *
     * @param int $item_id
     * @param StreamInterface $file
     * @param string|null $client_filename
     * @throws Exception
     */
    protected function _save(int $item_id, StreamInterface $file, string $client_filename = null): void {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->commit();

        // Save PDF first.
        $filepath = $this->idToPdfPath($item_id);
        $this->writeFile($filepath, $file);

        // File not a PDF?
        setlocale(LC_ALL,'en_US.UTF-8');

        $mime = $this->file_tools->getMime($filepath);

        if ($mime !== 'application/pdf') {

            // Client filename.
            $client_basename = pathinfo($client_filename, PATHINFO_FILENAME);
            $client_extension = strtolower(pathinfo($client_filename, PATHINFO_EXTENSION));

            // Rename PDF with client extension.
            $parts = pathinfo($filepath);
            $new_path = $parts['dirname'] . DIRECTORY_SEPARATOR . "{$parts['filename']}.{$client_extension}";
            $this->renameFile($filepath, $new_path);

            // Only some MIME types allowed.
            if (in_array($mime, $this->app_settings->extra_mime_types) === true) {

                // Try to convert to PDF. Get converted file path.
                $converted = $this->convertToPdf($new_path);

                if ($converted === '') {

                    // Fail. Delete file.
                    $this->deleteFile($new_path);
                    throw new Exception('error converting to PDF', 400);
                }

                // Successful conversion. Move converted PDF to PDF folder.
                $this->renameFile($converted, $filepath);

                // Save the original file as a supplement.

                /*
                 * Shorten the client filename. Filenames are stored encoded in RFC 3986. Some
                 * UTF-8 filenames can be longer than allowed in this format.
                 */
                while (strlen(rawurlencode($client_basename)) > 240) {

                    $client_basename = trim(mb_substr($client_basename, 0, -1, 'UTF-8'));
                }

                $supp_filepath = $this->idToSupplementPath($item_id) . rawurlencode($client_basename . '.' . $client_extension);
                $this->renameFile($new_path, $supp_filepath);

            } else {

                // This MIME not allowed. Delete file.
                $this->deleteFile($new_path);
                throw new Exception('uploaded file is not a PDF or a supported type', 400);
            }
        }

        // Extract full text.
        $this->pdf_object = $this->di->get('Pdf', $filepath);

        $text_file = $this->pdf_object->text();
        $text = trim(file_get_contents($text_file));

        if (!empty($text)) {

            // Insert new text.

            $sql_ins = <<<'EOT'
UPDATE ind_items
    SET full_text = ?, full_text_index = ?
    WHERE id = ?
EOT;

            /** @var ScalarUtils $scalar_utils */
            $scalar_utils = $this->di->getShared('ScalarUtils');

            $columns_ins = [
                gzencode($text, 1),
                '     ' . $scalar_utils->deaccent($text, false) . '     ',
                (integer) $item_id
            ];

            $this->db_main->run($sql_ins, $columns_ins);
        }

        unlink($text_file);

        // File hash.
        $sql_update = <<<'EOT'
UPDATE items
    SET file_hash = ?
    WHERE id = ?
EOT;

        $pdf_stream = $this->readFile($filepath);
        $pdf_hash = Psr7\Utils::hash($pdf_stream, 'md5');

        $columns_update = [
            $pdf_hash,
            (integer) $item_id
        ];

        $this->db_main->run($sql_update, $columns_update);
    }

    /**
     * Delete.
     *
     * @param int $item_id
     * @throws Exception
     */
    protected function _delete(int $item_id): void {

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            throw new Exception('this item does not exist', 404);
        }

        // Delete PDF.
        $filepath = $this->idToPdfPath($item_id);
        $this->deleteFile($filepath);
        $this->deleteFile($filepath . '.db');

        // Delete file hash in items.
        $sql = <<<SQL
UPDATE items
    SET file_hash = NULL
    WHERE id = ?
SQL;

        $this->db_main->run($sql, [$item_id]);

        // Delete existing PDF text.
        $sql = <<<'SQL'
UPDATE ind_items
    SET full_text = NULL, full_text_index = NULL
    WHERE id = ?
SQL;

        $this->db_main->run($sql, [$item_id]);
    }

    /**
     * Save extracted text.
     *
     * @param int $item_id
     * @param bool $boxes
     * @throws Exception
     */
    protected function _extract(int $item_id, bool $boxes = false): void {

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            throw new Exception('this item does not exist', 404);
        }

        if ($this->isPdf($item_id) === false) {

            return;
        }

        $pdf_file = $this->idToPdfPath($item_id);

        // Not a PDF.
        if ($this->file_tools->getMime($pdf_file) !== 'application/pdf') {

            return;
        }

        // Extract full text.
        $this->pdf_object = $this->di->get('Pdf', $pdf_file);

        $text_file = $this->pdf_object->text();

        // No text file.
        if (is_readable($text_file) === false) {

            return;
        }

        $text = trim(file_get_contents($text_file));
        unlink($text_file);

        if(empty($text)) {

            return;
        }

        // Insert new text.

        $sql_ins = <<<'EOT'
UPDATE ind_items
    SET full_text = ?, full_text_index = ?
    WHERE id = ?
EOT;

        /** @var ScalarUtils $scalar_utils */
        $scalar_utils = $this->di->getShared('ScalarUtils');

        $columns_ins = [
            gzencode($text, 1),
            '     ' . $scalar_utils->deaccent($text, false) . '     ',
            (integer) $item_id
        ];

        $this->db_main->run($sql_ins, $columns_ins);

        // Also re-extract binding boxes.
        if ($boxes === true) {

            $chunk = 50;
            $page_count = $this->pdf_object->pageCount();

            for ($i = 1; $i <= $page_count; $i = $i + $chunk) {

                $this->pdf_object->extractBoxes($i);
            }
        }
    }

    /**
     * Bookmarks.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _bookmarks(int $item_id): array {

        $output = [
            'bookmarks' => []
        ];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Does PDF exist?
        if ($this->isPdf($item_id) === false) {

            $this->db_main->rollBack();
            return $output;
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

        $this->cache = $this->di->getShared('FileCache');

        $this->cache->context('bookmarks');
        $key = $this->cache->key($item_id);

        // We must provide the PDF hash to not get a stale page.
        $bookmarks = $this->cache->get($key, $hash);

        if (empty($bookmarks)) {

            $pdfpath = $this->idToPdfPath($item_id);
            $this->pdf_object = $this->di->get('Pdf', $pdfpath);
            $bookmarks = $this->pdf_object->bookmarks();

            // Save created page to the cache.
            $save = $this->cache->set($key, $bookmarks, $hash);

            if ($save === true) {

                $bookmarks = $this->cache->get($key, $hash);
            }
        }

        $output['bookmarks'] = $bookmarks;

        return $output;
    }

    /**
     * Get word boxes.
     *
     * @param int $item_id
     * @param int $page
     * @return array
     * @throws Exception
     */
    protected function _boxes(int $item_id, int $page): array {

        $output = [
            'boxes' => []
        ];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Does PDF exist?
        if ($this->isPdf($item_id) === false) {

            $this->db_main->rollBack();
            return $output;
        }

        $this->db_main->commit();

        // Page range.
        $min_page = max(1, $page - 2);

        $pdfpath = $this->idToPdfPath($item_id);
        $this->pdf_object = $this->di->get('Pdf', $pdfpath);
        $page_count = $this->pdf_object->pageCount();

        if ($page_count === 0) {

            return $output;
        }

        $output['boxes'] = $this->pdf_object->getBoxes(range($min_page, min($page + 3, $page_count), 1));

        return $output;
    }

    /**
     * Get PDF highlights and PDF notes.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _annotations(int $item_id): array {

        $output = [
            'highlights' => [],
            'notes' => []
        ];

        $pdfpath = $this->idToPdfPath($item_id);
        $this->pdf_object = $this->di->get('Pdf', $pdfpath);
        $page_count = $this->pdf_object->pageCount();

        if ($page_count === 0) {

            return $output;
        }

        // Empty page array.
        $pages = array_fill_keys(range(1, $page_count, 1), []);

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $sql_highlights = <<<EOT
SELECT page, markers.id, id_hash, IFNULL(last_name, username) as username, item_id, marker_position, marker_top, marker_left, marker_width, marker_height, marker_color, marker_text
    FROM markers
    INNER JOIN users ON markers.user_id = users.id
    WHERE item_id = ? AND users.id = ?
    ORDER BY page, marker_position
EOT;

        $sql_notes = <<<EOT
SELECT page, annotations.id, id_hash, IFNULL(last_name, username) as username, item_id, annotation_top, annotation_left, annotation
    FROM annotations
    INNER JOIN users ON annotations.user_id = users.id
    WHERE item_id = ?
    ORDER BY page, annotation_top
EOT;

        $this->db_main->run($sql_highlights, [$item_id, $this->user_id]);
        $output['highlights'] = array_replace($pages, $this->db_main->getResultRows(PDO::FETCH_ASSOC | PDO::FETCH_GROUP));

        $this->db_main->run($sql_notes, [$item_id]);
        $output['notes'] = array_replace($pages, $this->db_main->getResultRows(PDO::FETCH_ASSOC | PDO::FETCH_GROUP));

        $this->db_main->commit();

        return $output;
    }

    /**
     * Get PDF links.
     *
     * @param int $item_id
     * @param int $page_from
     * @param int $page_number
     * @return array
     * @throws Exception
     */
    protected function _links(int $item_id, int $page_from, int $page_number): array {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $this->db_main->commit();

        $pdfpath = $this->idToPdfPath($item_id);
        $this->pdf_object = $this->di->get('Pdf', $pdfpath);
        $output['links'] = $this->pdf_object->getLinks($page_from, $page_number);

        return $output;
    }

    /**
     * Save highlights.
     *
     * @param int $item_id
     * @param string $color
     * @param array $boxes
     * @throws Exception
     */
    protected function _saveHighlights(int $item_id, string $color, array $boxes): void {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        if (in_array($color, ['B', 'G', 'R', 'Y']) === false) {

            $color = 'Y';
        }

        $sql = <<<EOT
INSERT OR REPLACE INTO markers
(user_id, item_id, page, marker_position, marker_top, marker_left, marker_width, marker_height, marker_color, marker_text)
VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;

        foreach ($boxes as $highlight) {

            $columns = [
                $this->user_id,
                $item_id,
                $highlight['page'],
                $highlight['position'],
                $highlight['top'],
                $highlight['left'],
                $highlight['width'],
                $highlight['height'],
                $color,
                $highlight['text']
            ];

            $this->db_main->run($sql, $columns);
        }

        $this->db_main->commit();
    }

    /**
     * Delete highlight boxes.
     *
     * @param int $item_id
     * @param array $boxes
     * @throws Exception
     */
    protected function _deleteHighlights(int $item_id, array $boxes): void {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        $sql = <<<EOT
DELETE FROM markers
    WHERE user_id = ? AND item_id = ? AND page = ? AND marker_position = ?
EOT;

        foreach ($boxes as $highlight) {

            $columns = [
                $this->user_id,
                $item_id,
                $highlight['page'],
                $highlight['position']
            ];

            $this->db_main->run($sql, $columns);
        }

        $this->db_main->commit();
    }

    /**
     * Save PDF note.
     *
     * @param int $item_id
     * @param string $text
     * @param int|null $page
     * @param int|null $top
     * @param int|null $left
     * @param int|null $note_id
     * @throws Exception
     */
    protected function _saveNote(int $item_id, string $text, int $page = null, int $top = null, int $left = null, int $note_id = null): void {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        if (isset($note_id)) {

            if ($text === '') {

                // Empty text deletes the note.

                $sql = <<<EOT
DELETE FROM annotations
    WHERE id = ? AND user_id = ?
EOT;

                $columns = [
                    $note_id,
                    $this->user_id
                ];

            } else {

                // Update existing note.

                $sql = <<<EOT
UPDATE annotations
    SET annotation = ?
    WHERE id = ? AND user_id = ?
EOT;

                $columns = [
                    $text,
                    $note_id,
                    $this->user_id
                ];
            }

        } else {

            // Insert new note.

            $sql = <<<EOT
INSERT INTO annotations
    (user_id, item_id, page, annotation_top, annotation_left, annotation) 
    VALUES(?, ?, ?, ?, ?, ?)
EOT;

            $columns = [
                $this->user_id,
                $item_id,
                $page,
                $top,
                $left,
                $text
            ];
        }

        $this->db_main->run($sql, $columns);

        $this->db_main->commit();
    }

    /**
     * Search.
     *
     * @param int $item_id
     * @param string $query
     * @param int $page_from
     * @return array
     * @throws Exception
     */
    protected function _search(int $item_id, string $query, int $page_from): array {

        $output = [
            'results' => []
        ];

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Does PDF exist?
        if ($this->isPdf($item_id) === false) {

            $this->db_main->rollBack();
            return $output;
        }

        $this->db_main->commit();

        $pdfpath = $this->idToPdfPath($item_id);
        $this->pdf_object = $this->di->get('Pdf', $pdfpath);
        $output = $this->pdf_object->search($query, $page_from);

        return $output;
    }

    /**
     * Log read page.
     *
     * @param $item_id
     * @param $page
     * @throws Exception
     */
    protected function _logPage($item_id, $page): void {

        // Log.
        $this->logger = $this->di->get('Logger');
        $this->logger->logPage($this->user_id, $item_id, $page);
    }

    /**
     * @param $item_id
     * @throws Exception
     */
    protected function _pdfDownloaded($item_id): void {

        // Log.
        $this->logger = $this->di->get('Logger');
        $this->logger->pdfDownloaded($this->user_id, $item_id);
    }

    /**
     * Send PDF with annotations and/or supplements.
     *
     * @param int $item_id
     * @param bool $annotations
     * @param bool $supplements
     * @return Psr7\Stream
     * @throws Exception
     */
    protected function _modifiedPdf(int $item_id, bool $annotations, bool $supplements): Psr7\Stream {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Does PDF exist?
        if ($this->isPdf($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this PDF does not exist', 404);
        }

        $this->db_main->commit();

        $pdf_file = $this->idToPdfPath($item_id);

        // Add annotations.
        if ($annotations === true) {

            $sql_highlights = <<<EOT
SELECT page, markers.id, id_hash, IFNULL(last_name, username) as username, item_id, marker_position, marker_top, marker_left, marker_width, marker_height, marker_color, marker_text
    FROM markers
    INNER JOIN users ON markers.user_id = users.id
    WHERE item_id = ? AND user_id = ?
    ORDER BY page, marker_position
EOT;

            $sql_notes = <<<EOT
SELECT page, annotations.id, id_hash, IFNULL(last_name, username) as username, item_id, annotation_top, annotation_left, annotation
    FROM annotations
    INNER JOIN users ON annotations.user_id = users.id
    WHERE item_id = ?
    ORDER BY page, annotation_top
EOT;

            $this->db_main->run($sql_highlights, [$item_id, $this->user_id]);
            $highlights = $this->db_main->getResultRows(PDO::FETCH_ASSOC);

            $this->db_main->run($sql_notes, [$item_id]);
            $annotations = $this->db_main->getResultRows(PDO::FETCH_ASSOC);

            // Add notes and highlights.
            $pdf_obj = new Pdf($this->di, $pdf_file);
            $pdf_file = $pdf_obj->addAnnotations($annotations, $highlights);
        }

        // Add supplements.
        if ($supplements === true) {

            $zip_file = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('pdf_') . '.zip';

            $zip = new ZipArchive();

            $open = $zip->open($zip_file, ZipArchive::CREATE);

            if ($open === false) {

                throw new Exception('failed creating a ZIP archive');
            }

            // Add PDF.
            $zip->addFile($pdf_file, basename($pdf_file));
            $zip->setCompressionIndex(0, ZipArchive::CM_STORE);

            // Add notes.
            $note_outer = '<!DOCTYPE html><html lang="en" style="width:100%;height:100%"><head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                    <title>I, Librarian - Notes</title></head><body>';

            $sql = <<<'EOT'
SELECT users.username, item_notes.note
    FROM item_notes
    INNER JOIN users ON users.id=item_notes.user_id
    WHERE item_notes.item_id = ?
EOT;

            $columns = [
                $item_id
            ];

            $this->db_main->run($sql, $columns);

            $note_inner = '';

            while ($row = $this->db_main->getResultRow()) {

                $note_inner .= "<p>{$row['username']}:</p>{$row['note']}";
            }

            if (!empty($note_inner)) {

                $note_outer .= "{$note_inner}</body></html>";

                $zip->addFromString('supplements/notes.html', $note_outer);
                $zip->setCompressionName('supplements/notes.html', ZipArchive::CM_STORE);
            }

            $close = $zip->close();

            if ($close === false) {

                throw new Exception('failed closing a ZIP archive');
            }

            // Add supplementary files.
            $filepath = $this->idToSupplementPath($item_id);
            $files = glob($filepath . "*");
            $file_count = count($files);

            for ($i = 0; $i < $file_count; $i++) {

                clearstatcache($zip_file);

                // Max 1 GB.
                if (filesize($zip_file) > 1000000000) {

                    break;
                }

                $open = $zip->open($zip_file);

                if ($open === false) {

                    throw new Exception('failed opening a ZIP archive');
                }

                $name = 'supplements/' . rawurldecode(substr(basename($files[$i]), 9));

                $zip->addFile($files[$i], $name);
                $zip->setCompressionName($name, ZipArchive::CM_STORE);

                $close = $zip->close();

                if ($close === false) {

                    throw new Exception('failed closing a ZIP archive');
                }
            }

            $zip = null;

            clearstatcache($zip_file);

            $zp = fopen($zip_file, 'rb');
            return Psr7\Utils::streamFor($zp);

        } else {

            $fp = fopen($pdf_file, 'r');
            return Psr7\Utils::streamFor($fp);
        }
    }

    /**
     * Make an image for OCR.
     *
     * @param int $item_id
     * @param int $page
     * @return string Image key.
     * @throws Exception
     */
    protected function _pageImage(int $item_id, int $page): string {

        $this->db_main->beginTransaction();

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('this item does not exist', 404);
        }

        // Does PDF exist?
        if ($this->isPdf($item_id) === false) {

            throw new Exception('this PDF does not exist', 404);
        }

        $this->db_main->commit();

        $pdfpath = $this->idToPdfPath($item_id);

        // PDF info.
        $this->pdf_object = $this->di->get('Pdf', $pdfpath);

        $image_path = $this->pdf_object->pageToImage($page, 'jpg', 300);

        /** @var Encryption $security */
        $security = $this->di->getShared('Encryption');
        $key = $security->getRandomKey(32);
        $safe_name = IL_TEMP_PATH . DIRECTORY_SEPARATOR . $key . '.jpg';

        // Windows fix.
        if (is_writable($safe_name)) {

            unlink($safe_name);
        }

        rename($image_path, $safe_name);

        return $key;
    }

    /**
     * Save text coming from OCR controller.
     *
     * @param int $item_id
     * @param string $text
     * @throws Exception
     */
    protected function _saveOcrText(int $item_id, string $text): void {

        // Text.
        $text = trim($text, " \f\t\n\r\0\x0B");

        if (!empty($text)) {

            $sql_ins = <<<'EOT'
UPDATE ind_items
    SET full_text = ?, full_text_index = ?
    WHERE id = ?
EOT;

            /** @var ScalarUtils $scalar_utils */
            $scalar_utils = $this->di->getShared('ScalarUtils');

            $columns_ins = [
                gzencode($text, 1),
                '     ' . $scalar_utils->deaccent($text, false) . '     ',
                (integer) $item_id
            ];

            $this->db_main->run($sql_ins, $columns_ins);
        }
    }

    /**
     * Save binding boxes coming from OCR controller.
     *
     * @param int $item_id
     * @param string $boxes
     * @throws Exception
     */
    protected function _saveOcrBoxes(int $item_id, string $boxes): void {

        $pdfpath = $this->idToPdfPath($item_id);
        $this->pdf_object = $this->di->get('Pdf', $pdfpath);

        $this->pdf_object->saveJsonBoxes($boxes);
    }

    /**
     * Scan PDF text for a DOI and save it to item.
     *
     * @param int $item_id
     * @return array
     * @throws Exception
     */
    protected function _scanDOIAndSave(int $item_id): array {

        $output = [
            'doi' => ''
        ];

        // Check if ID exists.
        if ($this->idExists($item_id) === false) {

            throw new Exception('this item does not exist', 404);
        }

        // Does PDF exist?
        if ($this->isPdf($item_id) === false) {

            return $output;
        }

        // Get PDF text.
        $sql_sel = <<<'EOT'
SELECT full_text
    FROM ind_items
    WHERE id = ?
EOT;

        $columns_ins = [
            (integer) $item_id
        ];

        $this->db_main->run($sql_sel, $columns_ins);
        $compressed = $this->db_main->getResult();
        $pdf_text = empty($compressed) ? '' : gzdecode($compressed);

        if (empty($pdf_text)) {

            return $output;
        }

        preg_match('/10\.\d{4,5}\.?\d*\/\S+/ui', $pdf_text, $match, PREG_OFFSET_CAPTURE);

        if (isset($match[0][0])) {

            // First match.
            $doi = $match[0][0];
            $offset = $match[0][1];

            // Remove punctuation marks from the end.
            if (in_array(substr($doi, -1), ['.', ',', ';']) === true) {

                $doi = substr($doi, 0, -1);
            }

            // Extract DOI from parentheses.
            if ($offset > 0) {

                if (substr($doi, -1) === ')' && $pdf_text[($offset - 1)] === '(') {

                    $doi = substr($doi, 0, -1);
                }

                if (substr($doi, -1) === ']' && $pdf_text[($offset - 1)] === '[') {

                    $doi = substr($doi, 0, -1);
                }
            }

            // Save DOI to item.
            if (!empty($doi)) {

                $sql_uid_find = <<<SQL
SELECT id
    FROM uids
    WHERE item_id = ? AND uid_type = 'DOI'
SQL;

                $sql_uid_update = <<<SQL
UPDATE
    uids
    SET uid = ?
    WHERE item_id = ? AND uid_type = 'DOI'
SQL;

                $sql_uid_insert = <<<SQL
INSERT INTO uids
    (uid_type, uid, item_id)
    VALUES ('DOI', ?, ?)
SQL;

                $this->db_main->beginTransaction();

                $columns_uid = [
                    $item_id
                ];

                // DOI exists?
                $this->db_main->run($sql_uid_find, $columns_uid);
                $exists = $this->db_main->getResult();

                $columns_uid = [
                    $doi,
                    $item_id
                ];

                if (!empty($exists)) {

                    // Update DOI.
                    $this->db_main->run($sql_uid_update, $columns_uid);

                } else {

                    // Add new DOI.
                    $this->db_main->run($sql_uid_insert, $columns_uid);
                }

                $this->db_main->commit();
            }

            $output['doi'] = $doi;
        }

        return $output;
    }
}
