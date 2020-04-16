<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Http\Client\Cookie\CookieJar;
use Librarian\Http\Client\Psr7\Response;
use Librarian\Http\ResponseDecorator;
use Librarian\Media\ScalarUtils;
use Librarian\Media\TesseractOcr;
use Librarian\Mvc\Controller;
use Throwable;

/**
 * Class PdfController
 *
 * PDF related tasks.
 */
class PdfController extends Controller {

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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        $model = new PdfModel($this->di);
        $info = $model->info($this->get['id']);

        // PDF viewer setting.
        $viewer = $this->app_settings->getUser('pdf_viewer');

        // View.
        if ($viewer === 'external') {

            $view = new PdfView($this->di);
            return $view->external($this->get['id'], ['title' => $info['title']]);
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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

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

            // Change extension (could be ZIP).
            $meta = $stream->getMetadata();
            $filename = pathinfo($filename, PATHINFO_FILENAME) . '.' . pathinfo($meta['uri'], PATHINFO_EXTENSION);
        }

        // Send disposition to the View.
        $disposition = isset($this->get['disposition']) ? $this->get['disposition'] : 'inline';

        // View.
        $view = new FileView($this->di, $stream);
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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

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

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        $stream = null;
        $client_name = null;

        // Uploaded file.
        $uploaded_file = $this->getUploadedFile('file');

        // Remote URL?
        if (!empty($this->post['remote_url'])) {

            // Safe link?
            if ($this->validation->ssrfLink($this->post['remote_url']) === false) {

                throw new Exception("this link is invalid", 422);
            }

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
            $response = $client->request('GET', $this->post['remote_url'], ['stream' => true, 'cookies' => $jar]);
            $stream = $response->getBody();

            // Client filename.
            $decorator = new ResponseDecorator($response);
            $client_name = $decorator->getFilename();

        } elseif(isset($uploaded_file)) {

            $stream = $uploaded_file->getStream();
            $client_name = $uploaded_file->getClientFilename();
        }

        $view = new DefaultView($this->di);

        if ($stream === null) {

            return $view->main(['info' => 'No PDF provided.']);
        }

        // Save the file.
        $model = new PdfModel($this->di);
        $model->save($this->post['id'], $stream, $client_name);

        return $view->main(['info' => 'New PDF saved.']);
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
        $this->authorization->permissions('A');

        // Check id.
        // Validate id.
        if (empty($this->post['id'])) {

            throw new Exception("Id required", 400);
        }

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        // Delete the file.
        $model = new PdfModel($this->di);
        $deleted = $model->delete($this->post['id']);

        if ($deleted === false) {

            throw new Exception("file was not deleted", 500);
        }

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'File was deleted.']);
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
        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        // Re-extract text from the PDF.
        $model = new PdfModel($this->di);
        $model->extract($this->post['id'], true);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'Text was extracted.']);
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

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("Id {$this->validation->error}", 422);
        }

        // Get page count.
        $pdf_model = new PdfModel($this->di);
        $info = $pdf_model->info($this->post['id']);
        $page_count = $info['info']['page_count'] ?? 0;

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
            $json = \Librarian\Http\Client\json_encode($array);
            $pdf_model->saveOcrBoxes($this->post['id'], $json);
        }

        $pdf_model->saveOcrText($this->post['id'], file_get_contents($text_file));

        unlink($text_file);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'PDF OCR has finished.']);
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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        if (!isset($this->get['page'])) {

            throw new Exception("the parameter page is required", 400);
        }

        if ($this->validation->id($this->get['page']) === false) {

            throw new Exception("the parameter page {$this->validation->error}", 422);
        }

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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

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

        if (isset($this->post['id']) && $this->validation->id($this->post['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        if (isset($this->post['annotation_id']) && $this->validation->id($this->post['annotation_id']) === false) {

            throw new Exception("the parameter annotation id {$this->validation->error}", 422);
        }

        $note_pg = isset($this->post['pg']) ? $this->post['pg'] : null;
        $note_t = isset($this->post['top']) ? $this->post['top'] : null;
        $note_l = isset($this->post['left']) ? $this->post['left'] : null;
        $note_id = isset($this->post['annotation_id']) ? $this->post['annotation_id'] : null;

        $model = new PdfModel($this->di);
        $model->saveNote($this->post['id'], $this->post['note'], $note_pg, $note_t, $note_l, $note_id);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'PDF note was saved.']);
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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

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

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        // Boxes come as JSON.
        $boxes = \Librarian\Http\Client\json_decode($this->post['boxes'], JSON_OBJECT_AS_ARRAY);

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

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        // Boxes come as JSON.
        $boxes = \Librarian\Http\Client\json_decode($this->post['boxes'], JSON_OBJECT_AS_ARRAY);

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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        if (!isset($this->get['query'])) {

            throw new Exception("the parameter query is required", 400);
        }

        $view = new DefaultView($this->di);

        // Empty query.
        if (empty($this->get['query'])) {

            $view->sseLine("data: CLOSE\n\n");
            return '';
        }

        $model = new PdfModel($this->di);
        $info = $model->info($this->get['id']);

        $chunk = 50;

        for ($i = 1; $i <= $info['info']['page_count']; $i = $i + $chunk) {

            $divs = [
                'boxes'    => [],
                'snippets' => []
            ];

            $boxes = $model->search($this->get['id'], $this->get['query'], $i);

            foreach ($boxes['boxes'] as $page => $words) {

                $html = '';

                foreach ($words as $word) {

                    $t = $word['top'] / 10;
                    $l = $word['left'] / 10;
                    $w = $word['width'] / 10;
                    $h = $word['height'] / 10;

                    $html .= <<<HTML
<div class="result" id="box-{$page}-{$word['position']}" style="left:{$l}%;top:{$t}%;width:{$w}%;height:{$h}%"></div>
HTML;
                }

                $divs['boxes'][$page] = "<div class=\"pdfviewer-result-boxes unselectable\">{$html}</div>";
            }

            foreach ($boxes['snippets'] as $snippet) {

                $page = $this->sanitation->attr($snippet['page']);
                $position = $this->sanitation->attr($snippet['position']);
                $text = $this->sanitation->html($snippet['text']);

                $html = <<<HTML
<a class="snippet d-block text-left text-white px-3 py-2 text-truncate"
 id="snippet-{$page}-{$position}" href="#" data-page="{$page}" data-box="box-{$page}-{$position}">$text</a>
HTML;

                $divs['snippets'][] = $html;
            }

            if (!empty( $divs['boxes']) || !empty( $divs['snippets'])) {

                $sse = \Librarian\Http\Client\json_encode($divs);
                $view->sseLine('data: ' . $sse . "\n\n");
            }
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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        if (isset($this->get['page']) === false) {

            throw new Exception("page parameter is required", 400);
        }

        if ($this->validation->intRange($this->get['page'], 1, 10000) === false) {

            throw new Exception("page parameter {$this->validation->error}", 422);
        }

        // Log model.
        $model = new PdfModel($this->di);
        $model->logPage($this->get['id'], $this->get['page']);

        // Render view.
        $view = new DefaultView($this->di);
        return $view->main([]);
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

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        $model = new PdfModel($this->di);
        $info = $model->info($this->get['id']);

        $view = new DefaultView($this->di);
        $chunk = 100;

        for ($i = 1; $i <= $info['info']['page_count']; $i = $i + $chunk) {

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

            $sse = \Librarian\Http\Client\json_encode($divs);
            $view->sseLine('data: ' . $sse . "\n\n");
        }

        $view->sseLine("data: CLOSE\n\n");

        return '';
    }
}
