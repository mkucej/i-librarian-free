<?php

namespace LibrarianApp;

use Exception;
use Librarian\External\Nasaads;
use Librarian\Mvc\Controller;

class NasaController extends Controller {

    /**
     * @var Nasaads
     */
    private $nasa;

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
        $searches = $model->list('nasa');

        // View.
        $view = new NasaView($this->di);
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

        // Page size 10. Allowed values: 1, 11, 21...
        $from = isset($this->get['from']) && $this->get['from'] % 10 === 1 ? $this->get['from'] : 1;

        // Max 10,000 results.
        $from = min($from, 9991);

        // Model.
        $api_key = $this->app_settings->apiKey('nasa', $this->server);
        $this->nasa = $this->di->getShared('Nasa', $api_key);

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
        $items = $this->nasa->search($terms, $from, 10, $filters, $this->get['sort']);

        if (isset($this->get['save_search'])) {

            // Search URL to save.
            unset($this->get['from']);
            unset($this->get['save_search']);
            $search_url = '#' . IL_PATH_URL . '?' . http_build_query($this->get);

            if (!empty($items['search_name'])) {

                // Model.
                $model = new SearchModel($this->di);
                $model->save('nasa', $items['search_name'], $search_url);
            }
        }

        // Find out if UIDs exist.
        if ($items['found'] > 0) {

            $model = new ItemModel($this->di);
            $items['items'] = $model->uidsExist($items['items']);
        }

        // View.
        $view = new ExternalView($this->di);
        return $view->results("NASA ADS", $items, $from, $items['search_name'] ?? '', $terms);
    }

    /**
     * Fetch bibcode metadata. Used by the UID import wizard.
     *
     * @return string
     * @throws Exception
     */
    public function fetchAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // Model.
        $api_key = $this->app_settings->apiKey('nasa', $this->server);
        $this->nasa = $this->di->getShared('Nasa', $api_key);

        // Get 10 results from $from.
        $items = $this->nasa->fetch($this->get['uid']);

        // View.
        $view = new DefaultView($this->di);
        return $view->main($items ?? []);
    }
}
