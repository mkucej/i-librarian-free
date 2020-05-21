<?php

namespace LibrarianApp;

use Exception;
use Librarian\External\Pmc;
use Librarian\Mvc\Controller;

class PmcController extends Controller {

    /**
     * @var Pmc
     */
    private $model;

    /**
     * Main. Search form and list of searches.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // Model.
        $model = new SearchModel($this->di);
        $searches = $model->list('pmc');

        // View.
        $view = new PmcView($this->di);
        return $view->main($searches);
    }

    /**
     * Search and list results.
     *
     * @return string
     * @throws Exception
     */
    public function searchAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // Model.
        $api_key = $this->app_settings->apiKey('ncbi', $this->server, true);
        $this->model = $this->di->get('Pmc', $api_key);

        // Page size 10. Allowed values: 1, 11, 21...
        $from = isset($this->get['from']) && $this->get['from'] % 10 === 1 ? $this->get['from'] : 1;

        // Max 10,000 results.
        $from = min($from, 9991);

        // Add terms.
        $terms = [];

        foreach ($this->get['search_query'] as $key => $query) {

            if (empty($query)) {

                continue;
            }

            $terms[] = [
                $this->get['search_type'][$key] => $query
            ];
        }

        // No terms.
        if ($terms === []) {

            $view = new DefaultView($this->di);
            return $view->main(['info' => 'No search terms provided.']);
        }

        // Add filters.
        $filters = [];
        $filters_arr = $this->get['search_filter'] ?? [];

        foreach ($filters_arr as $filter) {

            foreach ($filter as $key => $value) {

                if (empty($value)) {

                    continue;
                }

                $filters[][$key] = $value;
            }
        }

        // Get 10 results from $from.
        $items = $this->model->search($terms, $from, 10, $filters, $this->get['sort']);

        if (isset($this->get['save_search'])) {

            // Search URL to save.
            unset($this->get['from']);
            unset($this->get['save_search']);
            $search_url = '#' . IL_PATH_URL . '?' . http_build_query($this->get);

            // Model.
            $model = new SearchModel($this->di);
            $model->save('pmc', $items['search_name'], $search_url);
        }

        // Find out if UIDs exist.
        if ($items['found'] > 0) {

            $model = new ItemModel($this->di);
            $items['items'] = $model->uidsExist($items['items']);
        }

        // View.
        $view = new ExternalView($this->di);
        return $view->results("Pubmed Central", $items, $from, $items['search_name'], $terms);
    }

    /**
     * Convert Pubmed XML to metadata. Used by the UID import wizard.
     *
     * @return string
     * @throws Exception
     */
    public function formatAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // Model.
        $api_key = $this->app_settings->apiKey('ncbi', $this->server, true);
        $this->model = $this->di->get('Pmc', $api_key);
        $metadata = $this->model->formatMetadata($this->post['xml']);

        // View.
        $view = new DefaultView($this->di);
        return $view->main($metadata);
    }
}
