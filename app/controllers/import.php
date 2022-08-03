<?php

namespace LibrarianApp;

use Exception;
use GuzzleHttp\Psr7\Utils;
use Librarian\Container\DependencyInjector;
use Librarian\External\Crossref;
use Librarian\External\Nasaads;
use Librarian\External\Pmc;
use Librarian\External\Pubmed;
use Librarian\External\Xplore;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Librarian\Http\ResponseDecorator;
use Librarian\Media\Pdf;
use Librarian\Mvc\Controller;

class ImportController extends Controller {

    /**
     * ImportController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');
    }

    /**
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        return $this->manualAction();
    }

    /**
     * Wizard.
     *
     * @return string
     * @throws Exception
     */
    public function wizardAction(): string {

        // View.
        $view = new ImportView($this->di);
        return $view->wizard();
    }

    /**
     * Import from a UID.
     *
     * @return string
     * @throws Exception
     */
    public function uidAction(): string {

        // Get projects for this user.
        $model = new ProjectModel($this->di);
        $projects = $model->list();
        $model = null;

        // Get all tags.
        $model = new TagsModel($this->di);
        $tags = $model->getTags('library');
        $model = null;

        // View.
        $view = new ImportView($this->di);
        return $view->uid($projects, $tags);
    }

    /**
     * Files. Batch file import form.
     *
     * @return string
     * @throws Exception
     */
    public function fileAction(): string {

        // Get projects for this user.
        $model = new ProjectModel($this->di);
        $projects = $model->list();
        $model = null;

        // Get all tags.
        $model = new TagsModel($this->di);
        $tags = $model->getTags('library');
        $model = null;

        // View.
        $view = new ImportView($this->di);
        return $view->file($projects, $tags);
    }

    /**
     * Batch import. POST request.
     *
     * @return string
     * @throws GuzzleException
     * @throws Exception
     */
    public function batchAction(): string {

        // Imported file is saved to temp file.
        $temp_filename = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('batch_' . true);
        $uploaded_file = $this->getUploadedFile('file');

        if (!empty($this->post['remote_url'])) {

            // Safe link?
            $this->validation->ssrfLink($this->post['remote_url']);

            $jar = new CookieJar();

            /** @var Client $client */
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
            $response = $client->request('GET', $this->post['remote_url'], ['sink' => $temp_filename, 'cookies' => $jar]);

            // Client filename.
            $decorator = new ResponseDecorator($response);
            $client_name = $decorator->getFilename();

        } elseif(isset($uploaded_file)) {

            $client_name = $uploaded_file->getClientFilename();
            $uploaded_file->moveTo($temp_filename);
        }

        // Extract DOI from PDF.
        $doi = null;
        $mime_type = mime_content_type($temp_filename);

        if ($mime_type === 'application/pdf') {

            /** @var Pdf $pdf_obj */
            $pdf_obj = new Pdf($this->di, $temp_filename);
            $text_file = $pdf_obj->text();

            if (is_readable($text_file) === true) {

                $ft = fopen($text_file, 'r');

                while (feof($ft) === false) {

                    $line = fgets($ft);

                    preg_match_all('/10\.\d{4,5}\.?\d*\/\s?\S+/ui', $line, $match, PREG_PATTERN_ORDER);

                    if (count($match[0]) > 0) {

                        // First match.
                        $doi = $match[0][0];

                        // Remove punctuation marks from the end.
                        if (in_array(substr($doi, -1), ['.', ',', ';']) === true) {

                            $doi = substr($doi, 0, -1);
                        }

                        // Extract DOI from parentheses.
                        if (substr($doi, -1) === ')' || substr($doi, -1) === ']') {

                            preg_match_all('/(.)(doi:\s?)?(10\.\d{4,5}\.?\d*\/\s?\S+)/ui', $line, $match2, PREG_PATTERN_ORDER);

                            if (substr($doi, -1) === ')' && isset($match2[1][0]) === true && $match2[1][0] === '(') {

                                $doi = substr($doi, 0, -1);
                            }

                            if (substr($doi, -1) === ']' && isset($match2[1][0]) === true && $match2[1][0] === '[') {

                                $doi = substr($doi, 0, -1);
                            }
                        }

                        $doi = str_replace(' ', '', $doi);

                        break;
                    }
                }

                fclose($ft);
                unlink($text_file);
            }

        } elseif (in_array($mime_type, $this->app_settings->extra_mime_types) === false) {

            throw new Exception(sprintf(
                $this->lang->t9n('only these file types can be imported: %s'),
                join(', ', $this->app_settings->extra_file_types) . ', pdf'), 400);
        }

        // Fetch record metadata.
        if (!empty($doi) && !empty($this->post['repository'])) {

            switch ($this->post['repository']) {

                case 'xplore':
                    $api_key = $this->app_settings->apiKey('ieee', $this->server);
                    /** @var Xplore $model */
                    $model = $this->di->getShared('Xplore', $api_key);
                    $result = $model->fetch($doi);
                    break;

                case 'pubmed':
                    /** @var Pubmed $model */
                    $api_key = $this->app_settings->apiKey('ncbi', $this->server, true);
                    $model = $this->di->get('Pubmed', $api_key);
                    $result = $model->search([['AID' => $doi]], 0);
                    break;

                case 'pmc':
                    /** @var Pmc $model */
                    $api_key = $this->app_settings->apiKey('ncbi', $this->server, true);
                    $model = $this->di->get('Pmc', $api_key);
                    $result = $model->search([['DOI' => $doi]], 0);
                    break;

                case 'nasa':
                    /** @var Nasaads $model */
                    $api_key = $this->app_settings->apiKey('nasa', $this->server);
                    $model = $this->di->get('Nasa', $api_key);
                    $result = $model->search([['doi' => $doi]], 0);
                    break;

                default:
                    $result = [];
            }

            $metadata = isset($result['items'][0]) ? $result['items'][0] : [];
            $this->post = $this->post + $metadata;
        }

        if (empty($this->post['title']) && !empty($doi)) {

            // Crossref fallback.
            $api_key = $this->app_settings->apiKey('crossref', $this->server, true);
            /** @var Crossref $crossref */
            $crossref = $this->di->getShared('Crossref', $api_key);
            $result = $crossref->fetch($doi);

            $metadata = isset($result['items'][0]) ? $result['items'][0] : [];
            $this->post = $this->post + $metadata;
        }

        if (empty($this->post['title'])) {

            $this->post['title'] = pathinfo($client_name, PATHINFO_FILENAME);
        }

        $model = new ItemModel($this->di);
        $result = $model->save($this->post);

        if (!empty($result['item_id'])) {

            // Open stream for the temp file.
            $fp = Utils::tryFopen($temp_filename, 'rb');
            $stream = Utils::streamFor($fp);

            // Save the file.
            $model = new PdfModel($this->di);
            $model->save($result['item_id'], $stream, $client_name);
        }

        unlink($temp_filename);

        $view = new DefaultView($this->di);
        return $view->main(['info' => "new item was saved"]);
    }

    /**
     * @return string
     * @throws Exception
     * @throws GuzzleException
     */
    public function textAction(): string {

        // GET. Upload form.
        if ($this->request->getMethod() === 'GET') {

            // Get projects for this user.
            $model = new ProjectModel($this->di);
            $projects = $model->list();
            $model = null;

            // Get all tags.
            $model = new TagsModel($this->di);
            $tags = $model->getTags('library');
            $model = null;

            // View.
            $view = new ImportView($this->di);
            return $view->text($projects, $tags);

        } elseif ($this->request->getMethod() === 'POST') {

            if (!empty($this->post['remote_url'])) {

                // Safe link?
                $this->validation->ssrfLink($this->post['remote_url']);

                $jar = new CookieJar();

                /** @var Client $client */
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
                $response = $client->request('GET', $this->post['remote_url'], ['stream' => true, 'cookies' => $jar]);

                $stream = $response->getBody();
                $this->post['text'] = $stream->getContents();

            } else {

                $file = $this->getUploadedFile('file');

                if ($file !== null) {

                    $stream = $file->getStream();
                    $this->post['text'] = $stream->getContents();
                }
            }

            if (empty($this->post['text'])) {

                throw new Exception("no input found", 400);
            }

            $model = new ItemModel($this->di);
            $model->importText($this->post);

            $view = new DefaultView($this->di);
            return $view->main(['info' => "new items were saved"]);
        }

        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Manual.
     *
     * @return string
     * @throws Exception
     */
    public function manualAction(): string {

        // Save from the wizard UID input.
        if (isset($this->post['metadata'])) {

            $metadata = \GuzzleHttp\Utils::jsonDecode($this->post['metadata'], JSON_OBJECT_AS_ARRAY);
            $this->post = $this->post + $metadata;
        }

        // POST. Saving new file from manual form.
        if (isset($this->post['title'])) {

            $error = '';

            $model = new ItemModel($this->di);
            $result = $model->save($this->post);

            // Save PDF save.
            if (!empty($result['item_id'])) {

                $controller = new PdfController($this->di);
                $controller->post['id'] = $result['item_id'];

                try {

                    $controller->saveAction();

                } catch (Exception $ex) {

                    $error = ", but PDF was not";
                }

            }

            $view = new DefaultView($this->di);
            return $view->main(['info' => "new item was saved{$error}"]);
        }

        // GET. Upload form.
        if ($this->request->getMethod() === 'GET') {

            $model = new ProjectModel($this->di);
            $projects = $model->list();
            $model = null;

            $model = new TagsModel($this->di);
            $tags = $model->getTags('library');

            // View.
            $view = new ImportView($this->di);
            return $view->manual($projects, $tags);
        }

        $view = new DefaultView($this->di);
        return $view->main();
    }
}
