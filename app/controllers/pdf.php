<?php

namespace LibrarianApp;

use Exception;
use GuzzleHttp\Psr7\UploadedFile;
use Librarian\Container\DependencyInjector;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
use Librarian\Http\ResponseDecorator;
use Librarian\Media\FileTools;
use Librarian\Media\ScalarUtils;
use Librarian\Media\TesseractOcr;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Class PdfController
 *
 * PDF related tasks.
 */
class PdfController extends AppController {

    /**
     * @var ScalarUtils
     */
    private $scalar_utils;

    /**
     * PdfController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();
    }

    /**
     * Main. Show PDF.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        $model = new PdfModel($this->di);
        $info = $model->info($this->get['id']);

        // Corrupt PDFs with 0 page count throw error.
        if (isset($info['pdf_info']['page_count']) && $info['pdf_info']['page_count'] === 0) {

            throw new Exception('invalid PDF file');
        }

        // PDF viewer setting.
        $viewer = $this->app_settings->getUser('pdf_viewer');

        // View.
        if ($viewer === 'external') {

            $view = new PdfView($this->di);
            return $view->external($this->get['id'], $info);
        }

        $view = new PdfViewerView($this->di);
        return $view->main($this->get['id'], $info);
    }

    /**
     * File. Send PDF as a file stream.
     *
     * @return string
     * @throws Exception
     */
    public function fileAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        // Custom filename.
        $model = new ItemModel($this->di);
        $item = $model->get($this->get['id']);
        $model = null;

        $this->scalar_utils = $this->di->getShared('ScalarUtils');
        $filename = $this->scalar_utils->customFilename($this->app_settings->getUser('custom_filename'), $item);

        // Log download.
        $pdf_model = new PdfModel($this->di);
        $pdf_model->pdfDownloaded($this->get['id']);

        if (isset($this->get['supplements']) === false && isset($this->get['annotations']) === false) {

            // Get PDF file.
            $file_model = new FileModel($this->di);
            $stream = $file_model->readPdf($this->get['id']);
            $file_model = null;

        } else {

            $annotations = isset($this->get['annotations']) ? true : false;
            $supplements = isset($this->get['supplements']) ? true : false;

            $stream = $pdf_model->modifiedPdf($this->get['id'], $annotations, $supplements);
            $pdf_model = null;
        }

        // Send disposition to the View.
        $disposition = isset($this->get['disposition']) ? $this->get['disposition'] : 'inline';

        // View.
        $view = new FileView($this->di, $stream);

        // Change extension (could be ZIP).
        $filename = pathinfo($filename, PATHINFO_FILENAME) . '.' . pathinfo($view->filenameFromStream(), PATHINFO_EXTENSION);
        $view->filename = $filename;
        return $view->main($disposition);
    }

    /**
     * Manage. Manage view, upload form, etc...
     *
     * @return string
     * @throws Exception
     */
    public function manageAction(): string {

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        // Model.
        $model = new PdfModel($this->di);
        $pdf = $model->manage($this->get['id']);

        // View.
        $view = new PdfView($this->di);
        return $view->manage($this->get['id'], $pdf);
    }

    /**
     * Save PDF file.
     *
     * @return string
     * @throws Exception
     */
    public function saveAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        // Check id.
        if (empty($this->post['id'])) {

            throw new Exception("Id required", 400);
        }

        $this->validation->id($this->post['id']);

        $stream = null;
        $client_name = null;

        // Uploaded file.
        /** @var UploadedFile $uploaded_file */
        $uploaded_file = $this->getUploadedFile('file');

        // Remote URL?
        $temp_save = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('remote_pdf_');

        // Form input PDF has precedence over the remote link.
        if(isset($uploaded_file)) {

            $stream = $uploaded_file->getStream();
            $client_name = $uploaded_file->getClientFilename();

        } elseif (!empty($this->post['remote_url'])) {

            // Safe link?
            $this->validation->ssrfLink($this->post['remote_url']);

            $client = $this->di->get('HttpClient', [
                [
                    'timeout' => 30,
                    'curl'    => [
                        CURLOPT_PROXY        => $this->app_settings->proxyUrl(),
                        CURLOPT_PROXYUSERPWD => $this->app_settings->proxyUserPwd(),
                        CURLOPT_PROXYAUTH    => $this->app_settings->proxyAuthType()
                    ]
                ]
            ]);

            /** @var Response $response */
            $jar = new CookieJar();

            /** @var Response $response */
            $response = $client->get($this->post['remote_url'], ['sink' => $temp_save, 'cookies' => $jar]);

            // Client filename.
            $decorator = new ResponseDecorator($response);
            $client_name = $decorator->getFilename();

            // Open stream.
            $fp = \GuzzleHttp\Psr7\Utils::tryFopen($temp_save, 'r');
            $stream = \GuzzleHttp\Psr7\Utils::streamFor($fp);
        }

        if (is_object($stream)) {

            // Save the PDF to model.
            $this->save($this->post['id'], $stream, $client_name);

            if (is_writable($temp_save)) {

                unlink($temp_save);
            }

            $view = new DefaultView($this->di);
            return $view->main(['info' => 'new PDF was saved']);

        } else {

            $view = new DefaultView($this->di);
            return $view->main([]);
        }
    }

    /**
     * This is a method that takes an imported file stream, and sends it to the model.
     *
     * @param int $item_id
     * @param StreamInterface $stream
     * @param string $client_name
     * @return void
     * @throws Exception
     */
    public function save(int $item_id, StreamInterface $stream, string $client_name): void {

        /*
         * Shorten the client filename. Filenames are stored encoded in RFC 3986. Some
         * UTF-8 filenames can be longer than allowed in this format.
         */
        while (strlen(rawurlencode($client_name)) > 240) {

            $client_filename  = pathinfo($client_name, PATHINFO_FILENAME);
            $client_extension = strtolower(pathinfo($client_name, PATHINFO_EXTENSION));

            $client_filename = trim(mb_substr($client_filename, 0, -1, 'UTF-8'));
            $client_name = $client_filename . '.' . $client_extension;
        }

        // File not a PDF?
        setlocale(LC_ALL,'en_US.UTF-8');

        /** @var FileTools $file_tools */
        $file_tools = $this->di->get('FileTools');
        $mime = $file_tools->getMime($stream);

        if ($mime === 'application/pdf') {

            /*
             * PDF files.
             */

            // Just send it to the PDF model.
            $model = new PdfModel($this->di);
            $model->save($item_id, $stream, $client_name);

        } else {

            /*
             * Non-PDF files.
             */

            // Only some MIME types allowed.
            if (in_array($mime, $this->app_settings->extra_mime_types) === true) {

                // Save stream to temp file.
                $temp_path = IL_TEMP_PATH . DIRECTORY_SEPARATOR . $client_name;
                $file_tools->writeFile($temp_path, $stream);

                // Try to convert the file to PDF. Get converted file path.
                $converted_path = $this->convertToPdf($temp_path);

                if ($converted_path !== '') {

                    // Success! Save PDF to model.
                    $fp = \GuzzleHttp\Psr7\Utils::tryFopen($converted_path, 'r');
                    $pdf_stream = \GuzzleHttp\Psr7\Utils::streamFor($fp);

                    $model = new PdfModel($this->di);
                    $model->save($item_id, $pdf_stream, $client_name);
                }

                // Save the original file as a supplement.
                $stream->rewind();
                $supplement_model = new SupplementsModel($this->di);
                $supplement_model->save($item_id, $stream, $client_name);

                // Delete files when done.
                if (is_writable($temp_path)) {

                    unlink($temp_path);
                }

                if (is_writable($converted_path)) {

                    unlink($converted_path);
                }

            } else {

                throw new Exception('uploaded file is not a PDF or a supported type', 400);
            }
        }
    }

    /**
     * Delete.
     *
     * @return string
     * @throws Exception
     */
    public function deleteAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        // Check id.
        // Validate id.
        if (empty($this->post['id'])) {

            throw new Exception("Id required", 400);
        }

        $this->validation->id($this->post['id']);

        // Delete the file.
        $model = new PdfModel($this->di);
        $deleted = $model->delete($this->post['id']);

        if ($deleted === false) {

            throw new Exception('file was not deleted', 500);
        }

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'file was deleted']);
    }

    /**
     * Extract text from PDF.
     *
     * @return string
     * @throws Exception
     */
    public function extractAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        // Validate id.
        if (empty($this->post['id'])) {

            throw new Exception("Id required", 400);
        }

        // Check id.
        $this->validation->id($this->post['id']);

        // Re-extract text from the PDF.
        $model = new PdfModel($this->di);
        $model->extract($this->post['id'], true);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'text was extracted']);
    }

    /**
     * OCR.
     *
     * Get PDF page count from the model.
     * Page by page, get image and OCR it.
     * Collect text and send it to the model.
     *
     * @return string
     * @throws Exception
     * @throws Throwable
     */
    public function ocrAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        // Validate id.
        if (empty($this->post['id'])) {

            throw new Exception("Id required", 400);
        }

        $this->validation->id($this->post['id']);

        // Get page count.
        $pdf_model = new PdfModel($this->di);
        $info = $pdf_model->info($this->post['id']);
        $page_count = $info['pdf_info']['page_count'] ?? 0;

        if ($page_count === 0) {

            $view = new DefaultView($this->di);
            return $view->main(['info' => 'invalid PDF file']);
        }

        /** @var TesseractOcr $ocr_obj */
        $ocr_obj = $this->di->get('TesseractOcr');

        $language = !empty($this->post['custom_language']) ? $this->post['custom_language'] : $this->post['language'];

        // File to save text and boxes.
        $text_file = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('ocrtext_');

        for ($i = 1; $i <= $page_count; $i++) {

            // Get image filename.
            $image_key = $pdf_model->pageImage($this->post['id'], $i);

            // Get OCR response.
            $array = $ocr_obj->ocr(IL_TEMP_PATH . DIRECTORY_SEPARATOR . $image_key . '.jpg', $language);

            // Save data to local files page by page.
            file_put_contents($text_file, "\n\f" . $array['text'], FILE_APPEND);

            // Save box data to PDF db.
            unset($array['text']);
            $array['page'] = $i;
            $json = Utils::jsonEncode($array);
            $pdf_model->saveOcrBoxes($this->post['id'], $json);
        }

        $pdf_model->saveOcrText($this->post['id'], file_get_contents($text_file));

        unlink($text_file);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'OCR has finished']);
    }

    /**
     * Bookmarks.
     *
     * @return string
     * @throws Exception
     */
    public function bookmarksAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        $model = new PdfModel($this->di);
        $bookmarks = $model->bookmarks($this->get['id']);

        $view = new PdfViewerView($this->di);
        return $view->bookmarks($bookmarks['bookmarks']);
    }

    /**
     * Word boxes.
     *
     * @return string
     * @throws Exception
     */
    public function boxesAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        if (!isset($this->get['page'])) {

            throw new Exception("the parameter page is required", 400);
        }

        $this->validation->intRange($this->get['page'], 1, 100000);

        $model = new PdfModel($this->di);
        $boxes = $model->boxes($this->get['id'], $this->get['page']);

        $view = new PdfViewerView($this->di);
        return $view->textLayer($boxes['boxes']);
    }

    /**
     * List of PDF notes.
     *
     * @return string
     * @throws Exception
     */
    public function notelistAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        $model = new PdfModel($this->di);
        $boxes = $model->annotations($this->get['id']);

        $view = new PdfViewerView($this->di);
        return $view->noteList($boxes['notes']);
    }

    /**
     * Save a PDF note.
     *
     * @return string
     * @throws Exception
     */
    public function savenoteAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->post['annotation_id']) && !isset($this->post['left'])) {

            throw new Exception("annotation id or position is required", 400);
        }

        if (!isset($this->post['id'])) {

            throw new Exception("item id is required", 400);
        }

        $this->validation->id($this->post['id']);

        if (isset($this->post['annotation_id']) === true) {

            $this->validation->id($this->post['annotation_id']);
        }

        $note_pg = isset($this->post['pg']) ? $this->post['pg'] : null;
        $note_t = isset($this->post['top']) ? $this->post['top'] : null;
        $note_l = isset($this->post['left']) ? $this->post['left'] : null;
        $note_id = isset($this->post['annotation_id']) ? $this->post['annotation_id'] : null;

        $model = new PdfModel($this->di);
        $model->saveNote($this->post['id'], $this->post['note'], $note_pg, $note_t, $note_l, $note_id);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'PDF note was saved']);
    }

    /**
     * Get PDF highlights.
     *
     * @return string
     * @throws Exception
     */
    public function highlightsAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        $model = new PdfModel($this->di);
        $boxes = $model->annotations($this->get['id']);

        $view = new PdfViewerView($this->di);
        return $view->highlightLayer($boxes['highlights']);
    }

    /**
     * Save PDF highlights.
     *
     * @return string
     * @throws Exception
     */
    public function savehighlightsAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->post['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->post['id']);

        // Boxes come as JSON.
        $boxes = Utils::jsonDecode($this->post['boxes'], JSON_OBJECT_AS_ARRAY);

        $model = new PdfModel($this->di);
        $model->saveHighlights($this->post['id'], $this->post['color'], $boxes);

        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Delete PDF highlights.
     *
     * @return string
     * @throws Exception
     */
    public function deletehighlightsAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->post['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->post['id']);

        // Boxes come as JSON.
        $boxes = Utils::jsonDecode($this->post['boxes'], JSON_OBJECT_AS_ARRAY);

        $model = new PdfModel($this->di);
        $model->deleteHighlights($this->post['id'], $boxes);

        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Search PDF for text.
     *
     * @return string
     * @throws Exception
     */
    public function searchAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        if (!isset($this->get['query'])) {

            throw new Exception("the parameter query is required", 400);
        }

        $view = new DefaultView($this->di);

        // Empty query.
        if (empty($this->get['query']) || mb_strlen($this->get['query']) < 3) {

            $view->sseLine("data: CLOSE\n\n");
            return '';
        }

        $model = new PdfModel($this->di);
        $info = $model->info($this->get['id']);

        $chunk = 50;

        for ($i = 1; $i <= $info['pdf_info']['page_count']; $i = $i + $chunk) {

            $divs = [
                'boxes'     => [],
                'last_page' => min($i + $chunk - 1, $info['pdf_info']['page_count']),
                'snippets'  => []
            ];

            $boxes = $model->search($this->get['id'], $this->get['query'], $i);

            foreach ($boxes['boxes'] as $page => $words) {

                $html = '';

                foreach ($words as $word) {

                    $t = $word['top'] / 10;
                    $l = $word['left'] / 10;
                    $w = $word['width'] / 10;
                    $h = $word['height'] / 10;

                    $html .=
<<<HTML
<div class="result" id="box-{$page}-{$word['position']}" style="left:calc({$l}% - 2px);top:{$t}%;width:calc({$w}% + 4px);height:{$h}%"></div>
HTML;
                }

                $divs['boxes'][$page] = "<div class=\"pdfviewer-result-boxes unselectable\">{$html}</div>";
            }

            foreach ($boxes['snippets'] as $snippet) {

                $page = $this->sanitation->attr($snippet['page']);
                $position = $this->sanitation->attr($snippet['position']);
                $text = $this->sanitation->html($snippet['text']);

                $html =
<<<HTML
<a id="snippet-{$page}-{$position}" class="snippet text-white px-3 py-2" href="#" data-page="{$page}" data-box="box-{$page}-{$position}">{$this->sanitation->lmth($text)}</a>
HTML;

                $divs['snippets'][] = $html;
            }

            $sse = Utils::jsonEncode($divs);
            $view->sseLine('data: ' . $sse . "\n\n");
        }

        $view->sseLine("data: CLOSE\n\n");

        return '';
    }

    /**
     * Log opened PDF page.
     *
     * @return string
     * @throws Exception
     */
    public function logPageAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (isset($this->get['id']) === false) {

            throw new Exception("id parameter is required", 400);
        }

        $this->validation->id($this->get['id']);

        if (isset($this->get['page']) === false) {

            throw new Exception("page parameter is required", 400);
        }

        $this->validation->intRange($this->get['page'], 1, 10000);

        // Log model.
        $model = new PdfModel($this->di);
        $model->logPage($this->get['id'], $this->get['page']);

        // Render view.
        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Links. SSE.
     *
     * @return string
     * @throws Exception
     */
    public function linksAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        $model = new PdfModel($this->di);
        $info = $model->info($this->get['id']);

        $view = new DefaultView($this->di);
        $chunk = 100;

        for ($i = 1; $i <= $info['pdf_info']['page_count']; $i = $i + $chunk) {

            $divs = [];
            $links = $model->links($this->get['id'], $i, $chunk);

            foreach ($links['links'] as $page => $hrefs) {

                if (empty($hrefs)) {

                    continue;
                }

                $href_divs = '';

                foreach ($hrefs as $href) {

                    $top = $href['top'] / 10;
                    $left = $href['left'] / 10;
                    $width = $href['width'] / 10;
                    $height = $href['height'] / 10;

                    $href_divs .= <<<HREF
<a class="pdflink d-block" href="#" data-href="{$href['link']}" style="left:{$left}%;top:{$top}%;width:{$width}%;height:{$height}%"></a>
HREF;
                }

                // Make a DIV HTML.
                $divs[$page] = "<div class=\"pdfviewer-links\">{$href_divs}</div>";
            }

            if (empty($divs)) {

                continue;
            }

            $sse = Utils::jsonEncode($divs);
            $view->sseLine('data: ' . $sse . "\n\n");
        }

        $view->sseLine("data: CLOSE\n\n");

        return '';
    }

    /**
     * Rescan PDF text for DOI and save it.
     *
     * @return string
     * @throws Exception
     */
    public function scandoiandsaveAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        $this->validation->id($this->get['id']);

        $model = new PdfModel($this->di);
        $doi = $model->scanDOIAndSave($this->get['id']);

        $view = new DefaultView($this->di);
        return $view->main([
            'doi'  => $doi['doi'],
            'info' => empty($doi['doi']) ? 'No DOI found' : 'DOI found'
        ]);
    }
}
