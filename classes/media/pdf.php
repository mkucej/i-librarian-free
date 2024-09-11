<?php

namespace Librarian\Media;

use DOMDocument;
use DOMElement;
use DomXpath;
use Exception;
use Librarian\Container\DependencyInjector;
use GuzzleHttp\Utils;
use Librarian\Queue\Queue;
use Librarian\Storage\Database;
use PDO;
use SimpleXMLElement;

final class Pdf {

    private Binary $binary;
    private array $bookmarks = [];
    private DependencyInjector $di;
    private string $file;
    public  int $page_resolution;
    private Queue $queue;

    /**
     * Pdf constructor.
     *
     * @param DependencyInjector $di
     * @param string $file
     * @throws Exception
     */
    public function __construct(DependencyInjector $di, string $file) {

        if (mime_content_type($file) !== 'application/pdf') {

            throw new Exception("this file is not a PDF", 400);
        }

        $this->di = $di;
        $this->queue = $this->di->getShared('Queue');
        $this->binary = $this->di->getShared('Binary');
        $this->file = $file;
        $this->page_resolution = 96;
    }

    /**
     * Create and open PDF database cache. The db file is stored next to the PDF.
     *
     * @return Database
     * @throws Exception
     */
    private function openDb(): Database {

        $db_name = $this->file . '.db';

        // Delete a stale db.
        if (is_writable($db_name) === true && filemtime($this->file) > filemtime($db_name)) {

            unlink($db_name);
        }

        /** @var Database $db */
        $db = $this->di->get('Db_custom', [
            [
                'dbname' => $db_name
            ]
        ]);

        // Create tables if not exist.

        $sql_boxes = <<<'EOT'
CREATE TABLE IF NOT EXISTS boxes (
    page     INTEGER NOT NULL,
    position INTEGER NOT NULL,
    top      INTEGER NOT NULL,
    `left`   INTEGER NOT NULL,
    width    INTEGER NOT NULL,
    height   INTEGER NOT NULL,
    text     TEXT,
    text_ind TEXT,
    PRIMARY KEY(page, position, top, `left`)
);
EOT;

        $sql_links = <<<'EOT'
CREATE TABLE IF NOT EXISTS links (
    page     INTEGER NOT NULL,
    top      INTEGER NOT NULL,
    `left`   INTEGER NOT NULL,
    width    INTEGER NOT NULL,
    height   INTEGER NOT NULL,
    link     TEXT,
    PRIMARY KEY(page, top, `left`)
);
EOT;

        $sql_metadata = <<<'EOT'
CREATE TABLE IF NOT EXISTS metadata (
    id         INETEGER PRIMARY KEY,
    bookmarks  TEXT,
    created    TEXT,
    has_links  TEXT,
    has_text   TEXT,
    page_boxes TEXT,
    page_count INTEGER,
    page_sizes TEXT,
    title      TEXT
);
EOT;

        $sql_insert = <<<SQL
INSERT OR IGNORE INTO metadata 
    (id, bookmarks, created, has_links, has_text, page_boxes, page_count, page_sizes, title)
     VALUES(1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)
SQL;

        $db->connect();
        $db->run($sql_boxes);
        $db->run($sql_links);
        $db->run($sql_metadata);
        $db->run($sql_insert);
        $db->close();

        return $db;
    }

    /**
     * Get info about a PDF.
     *
     * @param bool $boxes Add crop box information.
     * @return array
     * @throws Exception
     */
    public function info(bool $boxes = false): array {

        $pdfinfo = ['title' => '', 'created' => '', 'page_count' => 0, 'page_sizes' => [], 'page_boxes' => []];

        $db = $this->openDb();
        $db->connect();

        if ($boxes === false) {

            $sql_select = <<<SQL
SELECT created, page_count, page_sizes, title FROM metadata
SQL;

        } else {

            $sql_select = <<<SQL
SELECT created, page_boxes, page_count, page_sizes, title FROM metadata
SQL;
        }

        $sql_update = <<<SQL
UPDATE metadata
    SET created = ?, page_boxes = ?, page_count = ?, page_sizes = ?, title = ?
    WHERE id = 1
SQL;

        $db->run($sql_select);
        $metadata = $db->getResultRow();

        if (!empty($metadata['page_sizes'])) {

            // Return metadata.
            $pdfinfo = [
                'title'      => $metadata['title'],
                'created'    => $metadata['created'],
                'page_count' => $metadata['page_count'],
                'page_sizes' => Utils::jsonDecode($metadata['page_sizes'], JSON_OBJECT_AS_ARRAY)
            ];

            if ($boxes === true) {

                $pdfinfo['page_boxes'] = Utils::jsonDecode($metadata['page_boxes'], JSON_OBJECT_AS_ARRAY);
            }

        } else {

            // Run pdfinfo, save, and return metadata.
            $raw = [];

            $this->queue->wait('binary');

            exec($this->binary->pdfinfo() . ' -enc UTF-8 -f 1 -l 10000 -box ' . escapeshellarg($this->file), $raw);

            $this->queue->release('binary');

            // Store landscape pages here.
            $landscape_pages = [];

            foreach ($raw as $k => $line) {

                if (strpos($line, "Title:") === 0) {

                    $pdfinfo['title'] = trim(substr($line, 6));

                } elseif (strpos($line, "CreationDate:") === 0) {

                    $pdfinfo['created'] = date('c', strtotime(trim(substr($line, 13))));

                } elseif (strpos($line, "Page") === 0 && strpos($line, "size:") !== false) {

                    $match = [];

                    preg_match("/(Page\s+)(\d+)(\s+)(size:)(\s+)(\d+\.?\d+)( x )(\d+\.?\d+)(\s+)(pts)/", $line, $match);

                    $page = $match[2] ?? 0;

                    if ($page > 0) {

                        $rot_match = [];

                        // Get rotation angle.
                        preg_match("/(Page\s+)(\d+)(\s+)(rot:)(\s+)(\d+)/", $raw[$k+1], $rot_match);

                        $rotation = $rot_match[6] ?? 0;

                        if ((string) $rotation === "90") {

                            $landscape_pages[] = (string) $page;

                            // Landscape.
                            $w = $match[8];
                            $h = $match[6];

                        } else {

                            // Portrait.
                            $w = $match[6];
                            $h = $match[8];
                        }

                        // Convert pt to px.
                        $pdfinfo['page_sizes'][$page]['width']  = $w * $this->page_resolution / 72;
                        $pdfinfo['page_sizes'][$page]['height'] = $h * $this->page_resolution / 72;
                    }

                } elseif (strpos($line, "Page") === 0) {

                    preg_match("/(Page\s+)(\d+)(\s+)(MediaBox:)(\s+)(-?\d+\.?\d+)(\s+)(-?\d+\.?\d+)(\s+)(-?\d+\.?\d+)(\s+)(-?\d+\.?\d+)/", $line, $match);

                    $page = $match[2] ?? 0;

                    if ($page > 0) {

                        $xmin = (float) $match[6] ?? 0;
                        $ymin = (float) $match[8] ?? 0;
                        $xmax = (float) $match[10] ?? 0;
                        $ymax = (float) $match[12] ?? 0;

                        // Rotate media boxes for landscape pages.
                        if (in_array($page, $landscape_pages) !== false) {

                            $xmax_l = $ymax + $xmin - $ymin;
                            $ymax_l = $xmax + $ymin - $xmin;
                            $xmax = $xmax_l;
                            $ymax = $ymax_l;
                        }

                        $pdfinfo['page_boxes'][$page]['mediabox'] = [
                            'xmin' => $xmin,
                            'ymin' => $ymin,
                            'xmax' => $xmax,
                            'ymax' => $ymax
                        ];

                    } else {

                        preg_match("/(Page\s+)(\d+)(\s+)(CropBox:)(\s+)(-?\d+\.?\d+)(\s+)(-?\d+\.?\d+)(\s+)(-?\d+\.?\d+)(\s+)(-?\d+\.?\d+)/", $line, $match);

                        $page = $match[2] ?? 0;

                        if ($page > 0) {

                            $xmin = (float) $match[6] ?? 0;
                            $ymin = (float) $match[8] ?? 0;
                            $xmax = (float) $match[10] ?? 0;
                            $ymax = (float) $match[12] ?? 0;

                            // Rotate crop boxes for landscape pages.
                            if (in_array($page, $landscape_pages) !== false) {

                                $xmax_l = $ymax + $xmin - $ymin;
                                $ymax_l = $xmax + $ymin - $xmin;
                                $xmax = $xmax_l;
                                $ymax = $ymax_l;
                            }

                            $pdfinfo['page_boxes'][$page]['cropbox'] = [
                                'xmin' => $xmin,
                                'ymin' => $ymin,
                                'xmax' => $xmax,
                                'ymax' => $ymax
                            ];
                        }
                    }
                }
            }

            $pdfinfo['page_count'] = count($pdfinfo['page_sizes']);

            $db->run($sql_update, [
                $pdfinfo['created'],
                Utils::jsonEncode($pdfinfo['page_boxes']),
                $pdfinfo['page_count'],
                Utils::jsonEncode($pdfinfo['page_sizes']),
                $pdfinfo['title']
            ]);

            if ($boxes === false) {

                $pdfinfo['page_boxes'] = [];
            }
        }

        $db->close();

        return $pdfinfo;
    }

    /**
     * Get PDF page count.
     *
     * @return int
     * @throws Exception
     */
    public function pageCount(): int {

        $db = $this->openDb();
        $db->connect();

        $sql_select = <<<SQL
SELECT page_count FROM metadata
SQL;

        $sql_insert = <<<SQL
UPDATE metadata
    SET page_count = ?
    WHERE id = 1
SQL;

        $db->run($sql_select);
        $page_count = (int) $db->getResult();

        if ($page_count === 0) {

            $this->queue->wait('binary');

            exec($this->binary->pdfinfo() . ' -enc "UTF-8" -f 1 -l 1 ' . escapeshellarg($this->file), $raw);

            $this->queue->release('binary');

            foreach ($raw as $line) {

                if (strpos($line, "Pages:") === 0) {

                    preg_match("/(Pages:\s+)(\d+)/", $line, $match);
                    $page_count = (int) $match[2] ?? 0;
                    break;
                }
            }

            $db->run($sql_insert, [$page_count]);
        }

        $db->close();

        return $page_count;
    }

    /**
     * Extract a page as an image. We use pdftoppm, because it can correctly apply cropbox
     * dimensions when extracting image from a PDF page.
     *
     * @param int|string$pageNumber
     * @param string $type
     * @param int|null $resolution
     * @param string $engine pdftoppm or gs
     * @return string
     * @throws Exception
     */
    public function pageToImage($pageNumber, string $type = 'jpg', int $resolution = null, string $engine = 'pdftoppm'): string {

        /*
         * We have to do some trickery first. The goal is to have images with even pixel numbers, because Chrome is buggy,
         * and can't properly resize images with odd pixel sizes. The trick is:
         * 1. Get page sizes from info()
         * 2. Calculate what the resulting image will be for the given zoom.
         * 3. If width and/or height are odd, add 1 pixel.
         * 4. Use pdftoppm scaling ability.
         */
        $info = $this->info();
        $page_size = $info['page_sizes'][$pageNumber];
        $w = (int) round( ($resolution / 100) * $page_size['width'] );
        $h = (int) round( ($resolution / 100) * $page_size['height']);
        $w = $w % 2 === 0 ? $w : $w + 1;
        $h = $h % 2 === 0 ? $h : $h - 1;

        $this->queue->wait('binary');

        $resolution = $resolution ?? $this->page_resolution;
        $imagePath = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid();

        // Create image.
        switch ($type) {

            case 'jpg':
            case 'png':

                if ($engine === 'pdftocairo') {

                    $device = $type === 'jpg' ? 'jpeg -jpegopt quality=85' : 'png';

                    exec($this->binary->pdftocairo()
                        . " -f $pageNumber -l $pageNumber -singlefile -cropbox "
                        . " -r $resolution -$device "
                        . escapeshellarg($this->file) . " " . escapeshellarg($imagePath));

                    $imagePath = "$imagePath.$type";

                } elseif ($engine === 'pdftoppm') {

                    $device = $type === 'jpg' ? 'jpeg -jpegopt quality=85' : 'png';

                    exec($this->binary->pdftoppm()
                        . " -f $pageNumber -l $pageNumber -singlefile -cropbox "
                        . "-scale-dimension-before-rotation -scale-to-x $w -scale-to-y -1 -x 0 -y 0 -W $w -H $h -$device "
                        . escapeshellarg($this->file) . " " . escapeshellarg($imagePath));

                    $imagePath = "$imagePath.$type";

                } else {

                    $device = $type === 'jpg' ? '-sDEVICE=jpeg -dJPEGQ=85' : '-sDEVICE=png16m';

                    exec($this->binary->ghostscript() . " $device -r$resolution"
                        . " -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -dDOINTERPOLATE -dUseCropBox"
                        . " -dFirstPage=$pageNumber -dLastPage=$pageNumber -o "
                        . escapeshellarg($imagePath). " " . escapeshellarg($this->file));
                }

                break;

            case 'svg':

                exec($this->binary->pdftocairo() . " -svg -f $pageNumber -l $pageNumber "
                    . escapeshellarg($this->file) . " " . escapeshellarg($imagePath));

                // Cairo's library has serious bugs, let's try to repair the SVG.
                $this->repairCairoSvg($imagePath);

                break;

            case 'pnggray':

                exec($this->binary->ghostscript() . " -sDEVICE=pnggray -r$resolution"
                    . " -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -dDOINTERPOLATE -dUseCropBox"
                    . " -dFirstPage=$pageNumber -dLastPage=$pageNumber -o "
                    . escapeshellarg($imagePath). " " . escapeshellarg($this->file));

                break;

            default:
                throw new Exception("cannot convert PDF to this image type", 400);
        }

        $this->queue->release('binary');

        // Image must exist -> error.
        if (!is_file($imagePath)) {

            throw new Exception("PDF to image page conversion failed", 500);
        }

        return $imagePath;
    }

    /**
     * Crop PDF page to an image. Used by a JS PDF cropper. We use pdftoppm, because it can correctly apply cropbox
     * dimensions when extracting image from a PDF page.
     *
     * @param int $page
     * @param int $x
     * @param int $y
     * @param int $w
     * @param int $h
     * @param int $zoom
     * @return string
     * @throws Exception
     */
    public function cropPageToImage(int $page, int $x, int $y, int $w, int $h, int $zoom): string {

        // The pages in the viewer are 288 dpi. To make the image 300 dpi, we must multiply the args.
        $resolution = ceil($zoom * 96 / 100);
        $ratio = 300 / $resolution;
        $x = round($ratio * $x);
        $y = round($ratio * $y);
        $w = round($ratio * $w);
        $h = round($ratio * $h);

        $img_path = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid();

        $this->queue->wait('binary');

        // Create image.
        exec($this->binary->pdftoppm()
            . " -singlefile -f $page -l $page -jpeg -jpegopt quality=90"
            . " -r 300 -x $x -y $y -W $w -H $h -cropbox "
            . escapeshellarg($this->file) . " " . escapeshellarg($img_path));

        $this->queue->release('binary');

        // Image must exist -> error.
        if (!is_file($img_path . '.jpg')) {

            throw new Exception("PDF to image page conversion failed", 500);
        }

        return $img_path . '.jpg';
    }

    /**
     * Create icon/thumb. We use pdftoppm, because it can correctly apply cropbox dimensions when extracting image
     * from a PDF page.
     *
     * @param int $page
     * @param int $width
     * @param float $ratio
     * @param string|null $path
     * @return string
     * @throws Exception
     */
    public function icon(int $page = 1, int $width = 600, float $ratio = 1.6, string $path = null): string {

        $height = round($width / $ratio);

        $img_path = $path ?? IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . uniqid();

        $this->queue->wait('binary');

        // Create image.
        exec($this->binary->pdftoppm()
            . " -singlefile -f $page -l $page -jpeg -jpegopt quality=80 -r 96 -cropbox"
            . " -scale-dimension-before-rotation -scale-to-x $width -scale-to-y -1 -x 0 -y 0 -W $width -H $height "
            . escapeshellarg($this->file) . " " . escapeshellarg($img_path));

        $this->queue->release('binary');

        // Image must exist -> error.
        if (!is_file($img_path . '.jpg')) {

            throw new Exception("PDF to image page conversion failed", 500);
        }

        return $img_path . '.jpg';
    }

    /**
     * Create small page preview. We use pdftoppm, because it can correctly apply cropbox dimensions when extracting
     * image from a PDF page.
     *
     * @param int $page
     * @param int $width
     * @return string
     * @throws Exception
     */
    public function pagePreview(int $page = 1, int $width = 400): string {

        $img_path = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('thumb_');

        $this->queue->wait('binary');

        // Create image.
        exec($this->binary->pdftoppm()
            . " -singlefile -f $page -l $page -jpeg -jpegopt quality=80 -cropbox"
            . " -scale-dimension-before-rotation -scale-to-x $width -scale-to-y -1 -x 0 -y 0 -W $width "
            . escapeshellarg($this->file) . " " . escapeshellarg($img_path));

        $this->queue->release('binary');

        // Image must exist -> error.
        if (!is_file($img_path . '.jpg')) {

            throw new Exception("PDF to image page conversion failed", 500);
        }

        return $img_path . '.jpg';
    }

    /**
     * Get bookmarks.
     *
     * @return array
     * @throws Exception
     */
    public function bookmarks(): array {

        $db = $this->openDb();
        $db->connect();

        $sql_select = <<<SQL
SELECT bookmarks FROM metadata
SQL;

        $sql_insert = <<<SQL
UPDATE metadata
    SET bookmarks = ?
    WHERE id = 1
SQL;

        $db->run($sql_select);
        $bookmarks = $db->getResult();

        if (!empty($bookmarks)) {

            $this->bookmarks = Utils::jsonDecode($bookmarks, JSON_OBJECT_AS_ARRAY);

        } else {

            $this->bookmarks = [];

            // Get XML.
            $string = $this->xml(1);

            // Load XML string into object.
            $xml = simplexml_load_string($string);

            // XML is invalid -> quiet exit.
            if ($xml === false) {

                return [];
            }

            // Compile output.
            if (!empty($xml->outline)) {

                $this->traverseXMLOutline($xml->outline);
            }

            // Save to db.
            $db->run($sql_insert, [
                Utils::jsonEncode($this->bookmarks)
            ]);
        }

        $db->close();

        return $this->bookmarks;
    }

    /**
     * Extract text from  PDF.
     *
     * @return string Filename where text is saved.
     * @throws Exception
     */
    public function text(): string {

        $tmpFile = IL_TEMP_PATH . DIRECTORY_SEPARATOR . basename($this->file) . '-temp.txt';
        $txtFile = IL_TEMP_PATH . DIRECTORY_SEPARATOR . basename($this->file) . '.txt';

        $this->queue->wait('pdfextract');

        exec($this->binary->pdftotext() . ' -enc UTF-8 '
            . escapeshellarg($this->file) . ' ' . escapeshellarg($tmpFile));

        $this->queue->release('pdfextract');

        // Binary failed -> quiet exit.
        if (!is_file($tmpFile)) {

            return '';
        }

        $fr = fopen($tmpFile, 'r');

        // Normalize text line by line.
        $ft = fopen($txtFile, 'w');

        while(feof($fr) === false) {

            $line = fgets($fr);
            // Remove zero width line breaks.
            $line = str_replace("\u{200B}\n", "", $line);
            // Remove zero width spaces.
            $line = str_replace("\u{200B}", "", $line);
            $line = preg_replace( "/[^\p{L}\p{N}\p{P}\f]+/u", " ", $line);
            $line = preg_replace('/\s{2,}/u', ' ', $line);

            fwrite($ft, $line);
        }

        fclose($fr);
        fclose($ft);

        unlink($tmpFile);

        return $txtFile;
    }

    /**
     * Get PDF links.
     *
     * @param int $page_from
     * @param int $page_number
     * @return array
     * @throws Exception
     */
    public function getLinks(int $page_from, int $page_number): array {

        $db = $this->openDb();
        $db->connect();

        // Check if links were extracted previously. has_link can be NULL or last page processed.
        $sql_select = <<<SQL
SELECT has_links FROM metadata
SQL;

        $db->run($sql_select);
        $has_links = $db->getResult();

        $db->close();

        if (empty($has_links) || (int) $has_links < $page_from) {

            // Extract links.
            $this->extractLinks($page_from, $page_number);
        }

        $db->connect();

        // Check flag again.
        $db->run($sql_select);
        $has_links = $db->getResult();

        if ($has_links === '0') {

            // PDF has no links.
            $db->close();
            return [];
        }

        // PDF links are in DB.
        $sql_select = <<<EOT
SELECT
    page, top, `left`, height, width, link
    FROM links
    WHERE page >= ? AND page < ?
    ORDER BY page
EOT;

        $columns = [
            $page_from,
            $page_from + $page_number
        ];

        $db->run($sql_select, $columns);
        $output = $db->getResultRows(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

        $db->close();

        return $output;
    }

    /**
     * Extract word boxes.
     *
     * @param array $pages
     * @return array
     * @throws Exception
     */
    public function getBoxes(array $pages): array {

        $db = $this->openDb();
        $db->connect();

        // Check if text was extracted previously.
        $sql_select = <<<SQL
SELECT has_text FROM metadata
SQL;

        $db->run($sql_select);
        $has_text = $db->getResult();

        $db->close();

        $processed_pages = json_decode((string) $has_text, JSON_OBJECT_AS_ARRAY);
        $unprocessed = is_null($processed_pages) ? $pages : array_diff($pages, $processed_pages);

        // Boxes have never been processed.
        if (count($unprocessed) > 0) {

            $bins = [];
            $chunk = 50;

            foreach ($unprocessed as $p) {

                $bins[] = $chunk * floor(($p - 1) / $chunk) + 1;
            }

            $bins = array_unique($bins);

            foreach ($bins as $bin) {

                // Extract text.
                $this->extractBoxes($bin);
            }
        }

        $db->connect();

        // Check flag again. There could have been an error.
        $db->run($sql_select);
        $has_text = $db->getResult();

        $processed_pages = json_decode((string) $has_text, JSON_OBJECT_AS_ARRAY);
        $unprocessed = is_null($processed_pages) ? $pages : array_diff($pages, $processed_pages);

        if (count($unprocessed) > 0) {

            $db->close();
            return [];
        }

        // Get text from DB.
        $placeholder_arr = array_fill(0, count($pages), '?');
        $placeholders = join(', ', $placeholder_arr);

        $sql_select = <<<EOT
SELECT
    page, position, top, `left`, height, width, text
    FROM boxes
    WHERE page IN ($placeholders)
    ORDER BY page
EOT;

        $db->run($sql_select, $pages);
        $output = $db->getResultRows(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

        $db->close();

        return $output;
    }

    /**
     * Search PDF.
     *
     * @param string $query
     * @param int $page_from
     * @return array
     * @throws Exception
     */
    public function search(string $query, int $page_from): array {

        /** @var ScalarUtils $scalar_utils */
        $scalar_utils = $this->di->getShared('ScalarUtils');

        $output = [
            'boxes'    => [],
            'snippets' => []
        ];

        $db = $this->openDb();
        $db->connect();

        // Check if text was extracted previously.
        $sql_select = <<<SQL
SELECT has_text FROM metadata
SQL;

        $db->run($sql_select);
        $has_text = $db->getResult();

        $db->close();

        $processed_pages = json_decode((string) $has_text, JSON_OBJECT_AS_ARRAY);

        $chunk = 50;
        $bin = $chunk * floor(($page_from - 1) / $chunk) + 1;

        if (is_null($processed_pages) || in_array($page_from, $processed_pages) === false) {

            $this->extractBoxes($bin);
        }

        $db->connect();

        // Check flag again. There could have been an error.
        $db->run($sql_select);
        $has_text = $db->getResult();

        $processed_pages = json_decode((string) $has_text, JSON_OBJECT_AS_ARRAY);

        if (is_null($processed_pages) || in_array($page_from, $processed_pages) === false) {

            $db->close();
            return $output;
        }

        // Search.
        $terms = explode(' ', $query);
        $term_count = count($terms);
        $deaccented_query = $scalar_utils->deaccent($query, false);
        $deaccented_terms = explode(' ', $deaccented_query);
        $likes = array_fill(0, count($terms), 'text LIKE ? OR text_ind LIKE ?');
        $like_param = join(' OR ', $likes);

        $columns = [
            $bin,
            $bin + $chunk
        ];

        foreach ($deaccented_terms as $term) {

            $columns[] = "%$term%";
            $columns[] = "%$term%";
        }

        $sql_search = <<<EOT
SELECT
    page, position, top, left, height, width, CASE WHEN text_ind = '' THEN text ELSE text_ind END AS text
    FROM boxes
    WHERE page >= ? AND page < ? AND ($like_param)
    ORDER BY page, position
EOT;

        $sql_snippet = <<<EOT
SELECT
    group_concat(text, ' ') AS snippet
    FROM boxes
    WHERE page = ? AND position >= ? - 2 AND position <= ? + 20
    ORDER BY position
EOT;

        $db->run($sql_search, $columns);
        $output['boxes'] = $db->getResultRows(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

        // We selected all boxes that match any of the query terms, but, in case of a multi-word query,
        // we return only the boxes that match the phrase. This is normal behavior in other PDF viewers.
        foreach ($output['boxes'] as $page => $boxes) {

            $protected_boxes = [];
            $box_count = count($boxes);

            // Iterate over all boxes to find out if they fit the query, which can be multi-word.
            for ($i = 0; $i < $box_count; $i = $i + 1) {

                // Compile a phrase from number of boxes that matches the query term number.
                $sliced_boxes = [];
                $box_position = (int) $boxes[$i]['position'];

                // We iterate on box position, because there must be no position gaps.
                for ($pos = $box_position, $ind = $i; $pos < $box_position + $term_count; $pos++, $ind++) {

                    // If a box with consecutive position exists, add it to array.
                    if (isset($boxes[$ind]) && (int) $boxes[$ind]['position'] === $pos) {

                        $sliced_boxes[$ind] = $boxes[$ind];
                    }
                }

                // Extract just the text column.
                $deaccented_boxes = array_column($sliced_boxes, 'text');

                // Compare the query with the text in boxes.
                if (mb_stripos(trim(join(' ', $deaccented_boxes)), trim($deaccented_query)) !== false) {

                    // If whole query matches the boxes, fetch the snippet.

                    $columns = [
                        $page,
                        $boxes[$i]['position'],
                        $boxes[$i]['position']
                    ];

                    $db->run($sql_snippet, $columns);
                    $snippet = str_replace('- ', '', $db->getResult());

                    $output['snippets'][] = [
                        // Remove word breaks.
                        'text'     => $snippet,
                        'page'     => $page,
                        'position' => $boxes[$i]['position']
                    ];

                    // Protect all boxes in this snippet from deletion.
                    $protected_boxes = array_merge($protected_boxes, array_keys($sliced_boxes));

                } else {

                    // If whole query does not match the boxes, delete the current text box.

                    if (in_array($i, $protected_boxes) === false) {

                        unset($output['boxes'][$page][$i]);
                    }
                }
            }
        }

        $db->close();

        return $output;
    }

    /**
     * Extract word boxes from PDF.
     *
     * @param int $page_from
     * @throws Exception
     */
    public function extractBoxes(int $page_from): void {

        set_time_limit(600);

        /** @var ScalarUtils $scalar_utils */
        $scalar_utils = $this->di->getShared('ScalarUtils');

        $sql_metadata_select = <<<'EOT'
SELECT has_text
    FROM metadata
    WHERE id = 1
EOT;

        $sql_metadata_update = <<<'EOT'
UPDATE metadata
    SET has_text = ?
    WHERE id = 1
EOT;

        $sql_delete = <<<'EOT'
DELETE FROM boxes
    WHERE page >= ? AND page <= ?
EOT;

        $sql_insert = <<<'EOT'
INSERT OR IGNORE INTO boxes
    (page, position, top, `left`, width, height, text, text_ind)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?)
EOT;

        // Get PDF info.
        $info = $this->info(true);

        if ($info['page_count'] === 0) {

            return;
        }

        $chunk = 50;
        $page_end = min($page_from + $chunk - 1, $info['page_count']);

        $db = $this->openDb();
        $db->connect();
        $db->beginTransaction();

        // Delete previous boxes.
        $columns_delete = [
            $page_from,
            $page_end
        ];

        $db->run($sql_delete, $columns_delete);

        // Delete from metadata.
        $db->run($sql_metadata_select);
        $json = $db->getResult();

        $array = empty($json) ? [] : Utils::jsonDecode($json, JSON_OBJECT_AS_ARRAY);
        $new_array = array_diff($array, range($page_from, $page_end));

        $db->run($sql_metadata_update, [Utils::jsonEncode($new_array)]);

        // Get boxes.
        $html_file = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('boxes_') . '.html';

        $this->queue->wait('pdfextract');

        exec($this->binary->pdftotext() . " -bbox -enc \"UTF-8\" -f $page_from -l $page_end "
            . escapeshellarg($this->file) . ' ' . escapeshellarg($html_file));

        $this->queue->release('pdfextract');

        // Binary failed -> quiet exit.
        if (!is_file($html_file)) {

            $db->rollBack();
            $db->close();
            return;
        }

        libxml_use_internal_errors(true);

        if (PHP_MAJOR_VERSION < 8) {

            libxml_disable_entity_loader(false);
        }

        libxml_clear_errors();

        // Get just the body.
        preg_match("/<body>.*<\/body>/uism", file_get_contents($html_file), $matches);

        $dom = new DOMDocument();
        $dom->loadHTML(isset($matches[0]) ? '<?xml encoding="UTF-8">' . $matches[0] : '');
        $pages = $dom->getElementsByTagName('page');

        $page_number = $page_from;
        $processed_pages = [];

        /** @var DOMElement $page */
        foreach ($pages as $page) {

            /*
             * Pdftotext gives coordinates relative to MediaBox, whereas pdftoppm works with CropBox.
             */

            // Get Cropbox size and offsets. Remember, PDF coordinates start at lower left corner.
            $offset_x = $info['page_boxes'][$page_number]['cropbox']['xmin'] - $info['page_boxes'][$page_number]['mediabox']['xmin'];
            $offset_y = $info['page_boxes'][$page_number]['mediabox']['ymax'] - $info['page_boxes'][$page_number]['cropbox']['ymax'];
            $page_w   = $info['page_boxes'][$page_number]['cropbox']['xmax'] - $info['page_boxes'][$page_number]['cropbox']['xmin'];
            $page_h   = $info['page_boxes'][$page_number]['cropbox']['ymax'] - $info['page_boxes'][$page_number]['cropbox']['ymin'];

            /** @var DOMElement $word */
            $words = $page->getElementsByTagName('word');
            $page = null;

            foreach ($words as $position => $word) {

                $word_x1 = (float) $word->getAttribute('xmin') - (float) $offset_x;
                $word_y1 = (float) $word->getAttribute('ymin') - (float) $offset_y;
                $word_x2 = (float) $word->getAttribute('xmax') - (float) $offset_x;
                $word_y2 = (float) $word->getAttribute('ymax') - (float) $offset_y;

                // Deaccent.
                $deaccented = $scalar_utils->deaccent($word->nodeValue, false);
                $deaccented = $deaccented === $word->nodeValue ? '' : $deaccented;

                $db->run($sql_insert, [
                    // Page.
                    $page_number,
                    //Position.
                    $position + 1,
                    // Top.
                    1000 * round($word_y1 / $page_h, 3),
                    // Left.
                    1000 * round($word_x1 / $page_w, 3),
                    // Width.
                    1000 * round(($word_x2 - $word_x1) / $page_w, 3),
                    // Height.
                    1000 * round(($word_y2 - $word_y1) / $page_h, 3),
                    // Text.
                    $word->nodeValue,
                    // De-accented text.
                    $deaccented
                ]);
            }

            $processed_pages[] = $page_number;
            $page_number++;
            $words = null;
        }

        $pages = null;
        $dom = null;

        // Save metadata.
        $db->run($sql_metadata_select);
        $json = $db->getResult();

        $array = empty($json) ? [] : Utils::jsonDecode($json, JSON_OBJECT_AS_ARRAY);
        $new_array = array_unique(array_merge($array, $processed_pages));

        $db->run($sql_metadata_update, [Utils::jsonEncode($new_array)]);

        $db->commit();
        $db->close();

        unlink($html_file);

//        $this->queue->release();
    }

    /**
     * Extract links and save to db.
     *
     * @param int $page_from
     * @param int $chunk
     * @throws Exception
     */
    public function extractLinks(int $page_from, int $chunk): void {

        set_time_limit(600);

        $sql_links = <<<'EOT'
INSERT OR IGNORE INTO links
    (page, top, `left`, width, height, link)
    VALUES(?, ?, ?, ?, ?, ?)
EOT;

        $sql_metadata = <<<'EOT'
UPDATE metadata
    SET has_links = ?
    WHERE id = 1
EOT;

        // Get PDF info.
        $info = $this->info(true);

        if ($info['page_count'] === 0) {

            return;
        }

        $page_end = min($page_from + $chunk - 1, $info['page_count']);

        // Add links.
        $xml_file = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('links_') . '.xml';

        $this->queue->wait('pdfextract');

        exec($this->binary->pdftohtml() . " -nodrm -q -enc \"UTF-8\" -nomerge -i -hidden -xml -f $page_from -l $page_end "
            . escapeshellarg($this->file) . ' ' . escapeshellarg($xml_file));

        $this->queue->release('pdfextract');

        $db = $this->openDb();
        $db->connect();

        // Binary failed -> quiet exit.
        if (!is_file($xml_file)) {

            $db->close();
            return;
        }

        libxml_use_internal_errors(true);

        if (PHP_MAJOR_VERSION < 8) {

            libxml_disable_entity_loader(false);
        }

        libxml_clear_errors();

        $dom = new DOMDocument();
        $dom->loadXML(file_get_contents($xml_file));
        $pages = $dom->getElementsByTagName('page');

        $page_number = $page_from;

        $db->beginTransaction();

        /** @var DOMElement $page */
        foreach ($pages as $page) {

            /*
             * Pdftohtml gives coordinates relative to MediaBox in px, whereas pdftoppm works with CropBox and pts.
             */

            // Get Cropbox size and offsets. Remember, PDF coordinates start at lower left corner.
            $offset_x = ($info['page_boxes'][$page_number]['cropbox']['xmin'] - $info['page_boxes'][$page_number]['mediabox']['xmin']);
            $offset_y = $info['page_boxes'][$page_number]['mediabox']['ymax'] - $info['page_boxes'][$page_number]['cropbox']['ymax'];
            $page_w   = ($info['page_boxes'][$page_number]['cropbox']['xmax'] - $info['page_boxes'][$page_number]['cropbox']['xmin']);
            $page_h   = ($info['page_boxes'][$page_number]['cropbox']['ymax'] - $info['page_boxes'][$page_number]['cropbox']['ymin']);

            /** @var DOMElement $row */
            $rows = $page->getElementsByTagName('text');
            $page = null;

            foreach ($rows as $row) {

                /** @var DOMElement $link */
                $links = $row->getElementsByTagName('a');

                foreach ($links as $link) {

                    $href = $link->getAttribute('href');

                    if (empty($href)) {

                        continue;
                    }

                    if (strpos($href, pathinfo($xml_file,  PATHINFO_FILENAME)) === 0) {

                        $href = substr(strstr($href, '#'), 1);
                    }

                    $db->run($sql_links, [
                        // Page.
                        $page_number,
                        // Top.
                        1000 * round((0.666864557 * $row->getAttribute('top') - $offset_y) / $page_h, 3),
                        // Left.
                        1000 * round((0.666864557 * $row->getAttribute('left') - $offset_x) / $page_w, 3),
                        // Width.
                        1000 * round(0.666864557 * $row->getAttribute('width') / $page_w, 3),
                        // Height.
                        1000 * round(0.666864557 * $row->getAttribute('height') / $page_h, 3),
                        // Href.
                        $href
                    ]);
                }

                $links = null;
                $row = null;
            }

            $page_number++;
            $rows = null;
        }

        // Save metadata.
        $db->run($sql_metadata, [$page_from]);

        $db->commit();

        $pages = null;
        $dom = null;
        unlink($xml_file);

        $db->close();
    }

    /**
     * Get XML for a page.
     *
     * @param int|null $page
     * @return string
     * @throws Exception
     */
    public function xml(int $page = null): string {

        $pages = '';
        $xmlFile = IL_TEMP_PATH . DIRECTORY_SEPARATOR . basename($this->file) . '.xml';

        if (isset($page)) {

            $pages = "-f $page -l $page";
            $xmlFile = IL_TEMP_PATH . DIRECTORY_SEPARATOR . basename($this->file) . "p$page.xml";
        }

        $this->queue->wait('pdfextract');

        exec($this->binary->pdftohtml() . " -nodrm $pages -enc UTF-8 -nomerge -i -hidden -xml "
            . escapeshellarg($this->file) . ' ' . escapeshellarg($xmlFile));

        $this->queue->release('pdfextract');

        // Binary failed -> quiet exit.
        if (!is_file($xmlFile)) {

            return '';
        }

        $content = file_get_contents($xmlFile);
        unlink($xmlFile);

        // Replace line breaks with spaces.
        $string = str_replace(["\r\n", "\n", "\r"], ' ', $content);

        // Repair XML string.
        $xml_obj = $this->di->getShared('Xml');
        return $xml_obj->repair($string);
    }

    /**
     * Convert bookmark XML into array.
     *
     * @param SimpleXMLElement $outline
     * @param int $level
     */
    private function traverseXMLOutline(SimpleXMLElement $outline, int $level = 1) {

        foreach ($outline->children() as $child) {

            if ($child->getName() === 'item' && isset($child['page'])) {

                $this->bookmarks[] = ['title' => (string) $child, 'page' => (integer) $child['page'], 'level' => $level];

            } elseif ($child->getName() === 'outline') {

                $level++;
                $this->traverseXMLOutline($child, $level);
                $level--;
            }
        }
    }

    /**
     * @param array $notes
     * @param array $highlights
     * @return string Filepath to the annotated PDF.
     * @throws Exception
     */
    public function addAnnotations(array $notes, array $highlights): string {

        // No annotations for this PDF.
        if ($notes === [] && $highlights === []) {

            return $this->file;
        }

        $pdfmark_file = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('pdfmark_') . '.ps';
        $output_file = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('annotated_pdf_') . '.pdf';

        // Get page info.
        $info = $this->info(true);

        // Add notes.
        foreach ($notes as $note) {

            $page_w = $info['page_boxes'][$note['page']]['cropbox']['xmax'] - $info['page_boxes'][$note['page']]['cropbox']['xmin'];
            $page_h = $info['page_boxes'][$note['page']]['cropbox']['ymax'] - $info['page_boxes'][$note['page']]['cropbox']['ymin'];
            $x_min = round(($note['annotation_left'] / 1000) * $page_w);
            $x_max = $x_min + 20;
            $y_min = round(((1000 - $note['annotation_top']) / 1000) * $page_h);
            $y_max = $y_min + 20;

            $annotation = strtoupper(bin2hex(mb_convert_encoding($note['annotation'], 'UCS-2BE', 'UTF-8')));
            $rectangle = "$x_min $y_min $x_max $y_max";

            file_put_contents($pdfmark_file, <<<NOTE
                [ /Contents <FEFF$annotation>
                  /Rect [ $rectangle ]
                  /Subtype /Text
                  /Name /Comment
                  /SrcPg {$note['page']}
                  /Open false
                  /Title (Comment by {$note['username']})
                  /Color [ 0.6 0.65 0.9 ]
                  /ANN pdfmark

NOTE
                , FILE_APPEND);
        }

        // Add highlights.
        foreach ($highlights as $note) {

            $page_w = $info['page_boxes'][$note['page']]['cropbox']['xmax'] - $info['page_boxes'][$note['page']]['cropbox']['xmin'];
            $page_h = $info['page_boxes'][$note['page']]['cropbox']['ymax'] - $info['page_boxes'][$note['page']]['cropbox']['ymin'];
            $x_min = round(($note['marker_left'] / 1000) * $page_w);
            $x_max = $x_min + round(($note['marker_width'] / 1000) * $page_w);
            $y_max = round(((1000 - $note['marker_top']) / 1000) * $page_h);
            $y_min = $y_max - round(($note['marker_height'] / 1000) * $page_h);

            $rectangle = "$x_min $y_min $x_max $y_max";
            $quad = "$x_min $y_max $x_max $y_max $x_min $y_min $x_max $y_min";

            switch ($note['marker_color']) {

                case 'Y':
                    $color = '1 0.96 0.5';
                    break;

                case 'B':
                    $color = '0.78 0.88 1';
                    break;

                case 'G':
                    $color = '0.78 1 0.85';
                    break;

                case 'R':
                    $color = '1 0.78 0.82';
                    break;

                default:
                    $color = '0.78 0.88 1';
            }

            file_put_contents($pdfmark_file, <<<NOTE
                [ /Rect [ $rectangle ]
                  /Subtype /Highlight
                  /QuadPoints [ $quad ]
                  /SrcPg {$note['page']}
                  /Color [ $color ]
                  /ANN pdfmark

NOTE
                , FILE_APPEND);
        }

        if (file_exists($pdfmark_file)) {

            $this->queue->wait('binary');

            exec($this->binary->ghostscript() . ' -o ' . escapeshellarg($output_file) .
                ' -dPDFSETTINGS=/prepress -sDEVICE=pdfwrite ' .
                escapeshellarg($pdfmark_file) . ' ' . escapeshellarg($this->file));

            $this->queue->release('binary');
        }

        return $output_file;
    }

    /**
     * Save binding boxes from JSON sent by OCR.
     *
     * @param string $json
     * @throws Exception
     */
    public  function  saveJsonBoxes(string $json): void {

        /** @var ScalarUtils $scalar_utils */
        $scalar_utils = $this->di->getShared('ScalarUtils');

        $array = Utils::jsonDecode($json, JSON_OBJECT_AS_ARRAY);

        $db = $this->openDb();
        $db->connect();

        $sql_delete = <<<'EOT'
DELETE FROM boxes
    WHERE page = ?
EOT;

        $sql_insert = <<<'EOT'
INSERT OR REPLACE INTO boxes
    (page, position, top, `left`, width, height, text, text_ind)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?)
EOT;

        $sql_metadata_select = <<<'EOT'
SELECT has_text
    FROM metadata
    WHERE id = 1
EOT;

        $sql_metadata_update = <<<'EOT'
UPDATE metadata
    SET has_text = ?
    WHERE id = 1
EOT;

        $page = $array['page'];
        $position = 1;

        $db->beginTransaction();

        // Delete from metadata.
        $db->run($sql_metadata_select);
        $json = $db->getResult();

        $metadata_array = empty($json) ? [] : Utils::jsonDecode($json, JSON_OBJECT_AS_ARRAY);
        $new_array = array_diff($metadata_array, [$page]);

        $db->run($sql_metadata_update, [Utils::jsonEncode($new_array)]);

        // Delete boxes.
        $db->run($sql_delete, [$page]);

        foreach ($array['boxes'] as $boxes) {

            // Deaccent.
            $deaccented = $scalar_utils->deaccent($boxes['text'], false);
            $deaccented = $deaccented === $boxes['text'] ? '' : $deaccented;

            $columns = [
                // Page.
                $page,
                //Position.
                $position,
                // Top.
                $boxes['t'],
                // Left.
                $boxes['l'],
                // Width.
                $boxes['w'],
                // Height.
                $boxes['h'],
                // Text.
                $boxes['text'],
                // De-accented text.
                $deaccented
            ];

            $db->run($sql_insert, $columns);

            $position++;
        }

        // Save metadata.
        $db->run($sql_metadata_select);
        $json = $db->getResult();

        $metadata_array = empty($json) ? [] : Utils::jsonDecode($json, JSON_OBJECT_AS_ARRAY);
        $new_array = array_unique(array_merge($metadata_array, [$page]));

        $db->run($sql_metadata_update, [Utils::jsonEncode($new_array)]);

        $db->commit();
        $db->close();
    }

    /**
     * Compensate for Cairo bugs.
     *
     * @param string $filename
     * @see https://gitlab.freedesktop.org/cairo/cairo/-/issues/4 ImageMask disappears or gets wrong transform.
     * @see https://gitlab.freedesktop.org/poppler/poppler/issues/375 PDF highlight annotations don't print correctly.
     */
    private function repairCairoSvg(string $filename): void {

        libxml_use_internal_errors(true);

        if (PHP_MAJOR_VERSION < 8) {

            libxml_disable_entity_loader(false);
        }

        libxml_clear_errors();

        $xml = new DOMDocument();
        $xml->load($filename);

        $xpath = new DomXpath($xml);

        /*
         * Highlight bug fix.
         */

        // Find global surfaces, presumably highlights.
        $surfaces = $xpath->query("//*[name()='defs']/*[name()='g' and starts-with(@id, 'surface')]");

        // Get the page g, and the first node in page g.
        $page_g = $xpath->query("//*[name()='svg']/*[name()='g']")->item(0);
        $first_g = $page_g->firstChild;

        foreach ($surfaces as $surface) {

            $surface_id = $surface->getAttribute('id');

            // Find g where the surface is used.
            $g_use = $xpath->query("//*[name()='use' and starts-with(@xlink:href, '#$surface_id')]")->item(0);

            if (empty($g_use)) {

                continue;
            }

            $g = $g_use->parentNode;

            // Try moving the node up so that it does not cover text.
            try {

                $page_g->insertBefore($g, $first_g);

            } catch (Exception $ex) {

                // This can silently fail if $page_g === $g, or $first_g === $g.
            }
        }

        /*
         * Image mask bug fix.
         */

        // Find global masks.
        $defs = $xml->getElementsByTagName('defs');
        $masks = isset($defs[0]) ? $defs[0]->getElementsByTagName('mask') : [];

        foreach ($masks as $mask) {

            $mask_id = $mask->getAttribute('id');

            if ($mask_id === '') {

                break;
            }

            // Get mask transform.
            $use = $mask->getElementsByTagName('use');
            $mask_transform = isset($use[0]) ? $use[0]->getAttribute('transform') : '';

            if ($mask_transform === '') {

                break;
            }

            $transform = substr($mask_transform, 7, -1);
            $transfrom_matrix = explode(',', $transform);
            $transform_x = (float) $transfrom_matrix[0] ?? 1;
            $transform_y = (float) $transfrom_matrix[3] ?? 1;

            // Masks smaller than 2x2 pts are invalid.
            $mask_is_invalid = abs($transform_x) < 0.0033 || abs($transform_y) < 0.0025;

            if ($mask_is_invalid === false) {

                break;
            }

            // Find clip path for this mask.
            /** @var DOMElement $g_clip */
            $g_mask = $xpath->query("//*[@mask=\"url(#$mask_id)\"]")->item(0);

            if (empty($g_mask)) {

                continue;
            }

            $g_clip = $g_mask->parentNode;
            $clip_id = $g_clip->getAttribute('clip-path');
            $clip_id = substr($clip_id, 5, -1);

            // There is a clip path.
            if (!empty($clip_id)) {

                // Parse clip path starting point, width and height.
                /** @var DOMElement $clip_path */
                $clip_path = $xpath->query("//*[@id=\"$clip_id\"]")->item(0);

                if (empty($clip_path)) {

                    continue;
                }

                $path = $clip_path->getElementsByTagName('path');
                $d = isset($path[0]) ? $path[0]->getAttribute('d') : '';

            } else {

                // No clip path. Is there a path sibling before the mask?
                $clip_path = $g_mask->previousSibling;

                if (trim($clip_path->nodeValue) === '') {

                    $clip_path = $g_mask->previousSibling->previousSibling;
                }

                if (empty($clip_path)) {

                    continue;
                }

                $d = $clip_path->getAttribute('d');
            }

            // No path d found.
            if ($d === '') {

                break;
            }

            preg_match('/^M (\d{1,6}\.?\d{0,6}) (\d{1,6}\.?\d{0,6}) L (\d{1,6}\.?\d{0,6}) (\d{1,6}\.?\d{0,6}) L (\d{1,6}\.?\d{0,6}) (\d{1,6}\.?\d{0,6}) L (\d{1,6}\.?\d{0,6}) (\d{1,6}\.?\d{0,6}) Z/', $d, $matches);

            // Only rectangular shapes are supported.
            if ($matches[1] !== $matches[7] || $matches[2] !== $matches[4] || $matches[3] !== $matches[5] || $matches[6] !== $matches[8]) {

                break;
            }

            // Image can be vertically flipped.
            $vertically_flipped = $matches[6] < $matches[2];

            switch ($vertically_flipped) {

                case true:
                    $tx = (float) $matches[7] ?? 0;
                    $ty = (float) $matches[8] ?? 0;
                    $dw = (float) $matches[3] - $tx;
                    $dh = (float) $matches[2] - $ty;
                    break;

                case false:
                    $tx = (float) $matches[1] ?? 0;
                    $ty = (float) $matches[2] ?? 0;
                    $dw = (float) $matches[3] - $tx;
                    $dh = (float) $matches[6] - $ty;
                    break;
            }

            // Get image id.
            $image_id = isset($use[0]) ? $use[0]->getAttribute('xlink:href') : '';
            $image_id = substr($image_id, 1);

            // Get image original dimensions.
            /** @var DOMElement $image */
            $image = $xpath->query("//*[@id=\"$image_id\"]")->item(0);

            if ($image === null) {

                break;
            }

            $img_w = (float) $image->getAttribute('width');
            $img_h = (float) $image->getAttribute('height');

            // Get transform ratio.
            $a = round($dw / $img_w, 6);
            $c = round($dh / $img_h, 6);

            // Update the transform matrix.
            $use[0]->setAttribute('transform', "matrix($a,0,0,$c,$tx,$ty)");
        }

        $xml->save($filename);
    }
}
