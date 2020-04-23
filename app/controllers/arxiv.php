<?php

namespace LibrarianApp;

use Exception;
use Librarian\External\Arxiv;
use Librarian\Mvc\Controller;

class ArxivController extends Controller {

    /**
     * @var Arxiv
     */
    private $arxiv;

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
        $searches = $model->list('arxiv');

        // View.
        $view = new ArxivView($this->di);
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

        // Model.
        $this->arxiv = $this->di->getShared('Arxiv');

        // Add search terms.
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
        $items = $this->arxiv->search($terms, $from, 10, $filters, $this->get['sort']);

        if (isset($this->get['save_search'])) {

            // Search URL to save.
            unset($this->get['from']);
            unset($this->get['save_search']);
            $search_url = '#' . IL_PATH_URL . '?' . http_build_query($this->get);

            // Model.
            $model = new SearchModel($this->di);
            $model->save('arxiv', $items['search_name'], $search_url);
        }

        // Find out if UIDs exist.
        if ($items['found'] > 0) {

            $model = new ItemModel($this->di);
            $items['items'] = $model->uidsExist($items['items']);
        }

        // View.
        $view = new ExternalView($this->di);
        return $view->results("arXiv", $items, $from, $items['search_name']);
    }
}
