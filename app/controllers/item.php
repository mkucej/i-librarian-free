<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\External\Arxiv;
use Librarian\External\Crossref;
use Librarian\External\Nasaads;
use Librarian\External\Pmc;
use Librarian\External\Pubmed;
use Librarian\External\Xplore;
use Librarian\ItemMeta;
use Librarian\Mvc\Controller;

class ItemController extends Controller {

    /**
     * ItemController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();
    }

    /**
     * Main. HTML base item view.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new MainModel($this->di);
        $first_name = $model->getFirstName();

        // Render view.
        $view = new ItemView($this->di);
        return $view->main(['first_name' => $first_name]);
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

        if (isset($this->post['id']) === false) {

            throw new Exception("id parameter is required", 400);
        }

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('A');

        $model = new ItemModel($this->di);
        $model->delete($this->post['id']);

        // Render view.
        $view = new DefaultView($this->di);
        return $view->main(['info' => 'Item was deleted.']);
    }

    /**
     * Autoupdate.
     *
     * @return string
     * @throws Exception
     */
    public function autoupdateAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        if (isset($this->post['id']) === false) {

            throw new Exception("id parameter is required", 400);
        }

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        if (isset($this->post['type']) === false) {

            throw new Exception("type parameter is required", 400);
        }

        if (isset($this->post['uid']) === false) {

            throw new Exception("uid parameter is required", 400);
        }

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        // Fetch data from Internet.
        switch ($this->post['type']) {

            case 'DOI':
                $api_key = $this->app_settings->apiKey('crossref', $this->server, true);
                /** @var Crossref $crossref */
                $crossref = $this->di->getShared('Crossref', $api_key);
                $result = $crossref->fetch($this->post['uid']);
                break;

            case 'PMID':
                /** @var Pubmed $model */
                $api_key = $this->app_settings->apiKey('ncbi', $this->server, true);
                $model = $this->di->get('Pubmed', $api_key);
                $result = $model->fetch($this->post['uid']);
                break;

            case 'PMCID':
                /** @var Pmc $model */
                $api_key = $this->app_settings->apiKey('ncbi', $this->server, true);
                $model = $this->di->get('Pmc', $api_key);
                $result = $model->fetch($this->post['uid']);
                break;

            case 'NASAADS':
                /** @var Nasaads $model */
                $api_key = $this->app_settings->apiKey('nasa', $this->server);
                $model = $this->di->get('Nasa', $api_key);
                $result = $model->fetch($this->post['uid']);
                break;

            case 'IEEE':
                $api_key = $this->app_settings->apiKey('ieee', $this->server);
                /** @var Xplore $model */
                $model = $this->di->getShared('Xplore', $api_key);
                $result = $model->fetch($this->post['uid']);
                break;

            case 'ARXIV':
                /** @var Arxiv $model */
                $model = $this->di->get('Arxiv');
                $result = $model->fetch($this->post['uid']);
                break;
        }

        $item_update = $result['items'][0] ?? [];

        // Remove data we do not want to update.
        unset($item_update[ItemMeta::COLUMN['URLS']]);
        unset($item_update[ItemMeta::COLUMN['UIDS']]);
        unset($item_update[ItemMeta::COLUMN['UID_TYPES']]);

        // Get data from database.
        $model = new ItemModel($this->di);
        $item = $model->get($this->post['id']);
        $item = $this->sanitation->lmth($item);

        // Merge new data and save.
        $new_item = array_merge($item, $item_update);
        $model->update($new_item);

        // Render view.
        $view = new DefaultView($this->di);
        return $view->main(['info' => 'Item was updated.']);
    }
}
