<?php

namespace LibrarianApp;

use Exception;
use Librarian\External\Xplore;
use Librarian\Mvc\Controller;

class IEEEController extends Controller {

    /**
     * @var Xplore
     */
    private $xplore;

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
        $this->authorization->permissions('U');

        // Model.
        $model = new SearchModel($this->di);
        $searches = $model->list('ieee');

        // View.
        $view = new IEEEView($this->di);
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
        $this->authorization->permissions('U');

        // Page size 10. Allowed values: 1, 11, 21...
        $from = isset($this->get['from']) && $this->get['from'] % 10 === 1 ? $this->get['from'] : 1;

        // Max 10,000 results.
        $from = min($from, 9991);

        // Xplore model.
        $api_key = $this->app_settings->apiKey('ieee', $this->server);
        $this->xplore = $this->di->getShared('Xplore', $api_key);

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
        $items = $this->xplore->search($terms, $from, 10, $filters);

        // Search URL to save.
        $get = $this->get;
        unset($get['from']);
        unset($get['save_search']);
        $search_url = '#' . IL_PATH_URL . '?' . http_build_query($get);

        // Model.
        $model = new SearchModel($this->di);

        if (isset($this->get['save_search'])) {

            $model->save('ieee', $items['search_name'], $search_url);

        } else {

            // Update search, if exists.
            $model->update('ieee', $items['search_name'], $search_url);
        }

        // Find out if UIDs exist.
        if ($items['found'] > 0) {

            $model = new ItemModel($this->di);
            $items['items'] = $model->uidsExist($items['items']);
        }

        // View.
        $view = new ExternalView($this->di);
        return $view->results("IEEE Xplore\u{00ae}", $items, $from, $items['search_name'], $terms);
    }

    /**
     * Fetch UID metadata. Used by the UID import wizard.
     *
     * @return string
     * @throws Exception
     */
    public function fetchAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        // Xplore model.
        $api_key = $this->app_settings->apiKey('ieee', $this->server);
        $this->xplore = $this->di->getShared('Xplore', $api_key);

        $items = $this->xplore->fetch($this->get['uid']);

        // View.
        $view = new DefaultView($this->di);
        return $view->main($items);
    }
}
