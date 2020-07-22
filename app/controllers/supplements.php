<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Http\Client\Client;
use Librarian\Http\Client\Exception\GuzzleException;
use Librarian\Mvc\Controller;

class SupplementsController extends Controller {

    /**
     * @var Client
     */
    private $client;

    /**
     * SupplementsController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();
        $this->authorization->signedId(true);
    }

    /**
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // Authorization.
        $this->authorization->permissions('U');

        // Check id.
        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        $model = new SupplementsModel($this->di);
        $list = $model->list($this->get['id']);

        $view = new SupplementsView($this->di);
        return $view->main($this->get['id'], $list);
    }

    /**
     * Save.
     *
     * @return string
     * @throws Exception
     * @throws GuzzleException
     */
    public function saveAction(): string {

        // Authorization.
        $this->authorization->permissions('U');

        // Check id.
        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        // Remote URL?
        if (!empty($this->post['remote_url'])) {

            // Safe link?
            if ($this->validation->ssrfLink($this->post['remote_url']) === false) {

                throw new Exception("this link is invalid", 422);
            }

            $this->client = $this->di->get('HttpClient', [
                [
                    'timeout' => 30,
                    'curl'    => [
                        CURLOPT_PROXY        => $this->app_settings->proxyUrl(),
                        CURLOPT_PROXYUSERPWD => $this->app_settings->proxyUserPwd(),
                        CURLOPT_PROXYAUTH    => $this->app_settings->proxyAuthType()
                    ]
                ]
            ]);

            $response = $this->client->request('GET', $this->post['remote_url'], ['stream' => true]);
            $stream = $response->getBody();
            $name = basename($this->post['remote_url']);

        } else {

            // Uploaded file.
            $uploaded_file = $this->getUploadedFile('file');

            $stream = $uploaded_file->getStream();
            $name = $uploaded_file->getClientFilename();
        }

        // Save the file.
        $model = new SupplementsModel($this->di);

        if (isset($this->post['graphical_abstract'])) {

            $model->saveGraphicalAbstract($this->post['id'], $stream);

        } else {

            $model->save($this->post['id'], $stream, $name);
        }

        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Rename.
     *
     * @return string
     * @throws Exception
     */
    public function renameAction(): string {

        // Authorization.
        $this->authorization->permissions('U');

        // Check id.
        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        if ($this->validation->dirname($this->post['filename']) === false) {

            throw new Exception("filename parameter {$this->validation->error}", 400);
        }

        if ($this->validation->dirname($this->post['newname']) === false) {

            throw new Exception("filename parameter {$this->validation->error}", 400);
        }

        // Save the file.
        $model = new SupplementsModel($this->di);
        $model->rename($this->post['id'], $this->post['filename'], $this->post['newname']);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'File was renamed.']);
    }

    /**
     * Delete.
     *
     * @return string
     * @throws Exception
     */
    public function deleteAction(): string {

        // Authorization.
        $this->authorization->permissions('A');

        // Check id.
        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        if ($this->validation->dirname($this->post['filename']) === false) {

            throw new Exception("filename parameter {$this->validation->error}", 400);
        }

        // Delete the file.
        $model = new SupplementsModel($this->di);
        $model->delete($this->post['id'], $this->post['filename']);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'File was deleted.']);
    }

    /**
     * Download.
     *
     * @return string
     * @throws Exception
     */
    public function downloadAction(): string {

        // Check id.
        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        if ($this->validation->dirname($this->get['filename']) === false) {

            throw new Exception("filename parameter {$this->validation->error}", 400);
        }

        // Get file from model.
        $model = new SupplementsModel($this->di);
        $stream = $model->download($this->get['id'], $this->get['filename']);

        $disposition = isset($this->get['disposition']) ? $this->get['disposition'] : 'inline';

        // Stream file to browser.
        $view = new FileView($this->di, $stream);

        // Change filename. Remove the first six numbers.
        $metadata = $stream->getMetadata();
        $view->filename = rawurldecode(substr(basename($metadata['uri']), 9));

        return $view->main($disposition);
    }

    /**
     * List images for TinyMCE.
     *
     * @return string
     * @throws Exception
     */
    public function imagelistAction(): string {

        // Check id.
        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        // Get file from model.
        $model = new SupplementsModel($this->di);
        $images = $model->imagelist($this->get['id']);

        // Stream file to browser.
        $view = new DefaultView($this->di);

        $list = [];

        foreach ($images as $image) {

            $list[] = [
                'title' => $image,
                'value' => IL_BASE_URL . "index.php/supplements/download?id={$this->get['id']}&filename={$image}"
            ];
        }

        return $view->main($list);
    }
}
