<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;

class ClipboardController extends AppController {

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);
        
        $this->session->close();
    }

    /**
     * Main. Browse, search, omnitool, export.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        // Page.
        $this->get['page'] = isset($this->get['page']) ? $this->get['page'] : 1;
        $this->validation->intRange($this->get['page'], 1, 10000);

        // Limit from.
        $from = ($this->get['page'] - 1) * $this->app_settings->getUser('page_size');
        $from = min($from, $this->app_settings->getGlobal('max_items') - $this->app_settings->getUser('page_size'));

        // Display type export|page|omnitool.
        $display_actions = isset($this->get['export']) ? 'export' : $this->app_settings->getUser('display_type');
        $display_actions = isset($this->post['omnitool']) ? $this->post['omnitool'] : $display_actions;

        $model = new ItemsModel($this->di);

        // Get search-related _GET keys.
        $search_keys = empty($this->get) ? [] : preg_grep('/search_/', array_keys($this->get));

        if ($search_keys === []) {

            // Browse.
            $items = $model->read(
                'clipboard',
                $this->app_settings->getUser('sorting'),
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions
            );

        } else {

            // Compile search-related _GET keys.
            foreach ($search_keys as $search_key) {

                $search[$search_key] = $this->get[$search_key];
            }

            // Search.
            $items = $model->search(
                $search,
                'clipboard',
                $this->app_settings->getUser('sorting'),
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions
            );
        }

        // Render view.
        if ($display_actions === 'export') {

            if ($this->get['export'] === 'zip') {

                // Export to a ZIP file.
                $zip = $model->exportZip($items['items'], $this->lang->getLanguage());

                $view = new FileView($this->di, $zip);
                return $view->main('attachment');

            } else {

                // Other exports.
                $style = '';

                if ($this->get['export'] === 'citation' && !empty($this->get['style'])) {

                    $citation = new CitationModel($this->di);
                    $style = $citation->getFromName($this->get['style']);
                }

                $view = new ItemsView($this->di);
                return $view->export($items, $this->get['export'], $this->get['disposition'], $style);
            }

        } elseif (isset($this->post['omnitool'])) {

            $view = new DefaultView($this->di);
            return $view->main();

        } else {

            // Get tag list for search.
            $tag_model = new TagsModel($this->di);
            $items['tags'] = $tag_model->getTags('clipboard');
            $tag_model = null;

            $view = new ItemsView($this->di);

            // Search saving.
            if ($search_keys !== []) {

                // Search saving model.
                $model = new SearchModel($this->di);

                // Save search.
                $url_get = $this->get;
                unset($url_get['page']);
                unset($url_get['save_search']);
                $search_url = '#' . IL_PATH_URL . '?' . http_build_query($url_get);

                if (isset($this->get['save_search'])) {

                    // Force save/update search.
                    $model->save('internal', 'Clipboard - ' . $view->searchName($this->get), $search_url);

                } else {

                    // Update search, if exists.
                    $model->update('internal', 'Clipboard - ' . $view->searchName($this->get), $search_url);
                }
            }

            return $view->page('clipboard', $this->get, $items);
        }
    }

    /**
     * Filter.
     *
     * @return string
     * @throws Exception
     */
    public function filterAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        // From.
        $this->get['page'] = isset($this->get['page']) ? $this->get['page'] : 1;
        $this->validation->intRange($this->get['page'], 1, 10000);

        // Limit from.
        $from = ($this->get['page'] - 1) * $this->app_settings->getUser('page_size');
        $from = min($from, $this->app_settings->getGlobal('max_items') - $this->app_settings->getUser('page_size'));

        // Display type export|page|omnitool.
        $display_actions = isset($this->get['export']) ? 'export' : $this->app_settings->getUser('display_type');
        $display_actions = isset($this->post['omnitool']) ? $this->post['omnitool'] : $display_actions;

        $model = new ItemsModel($this->di);

        if (empty($this->get['filter'])) {

            $items = $model->read(
                'clipboard',
                $this->app_settings->getUser('sorting'),
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions
            );

        } else {

            $items = $model->readFiltered(
                $this->get['filter'],
                'clipboard',
                $this->app_settings->getUser('sorting'),
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions
            );
        }

        // Render view.
        if ($display_actions === 'export') {

            if ($this->get['export'] === 'zip') {

                // Export to a ZIP file.
                $zip = $model->exportZip($items['items'], $this->lang->getLanguage());

                $view = new FileView($this->di, $zip);
                return $view->main('attachment');

            } else {

                // Other exports.
                $style = '';

                if ($this->get['export'] === 'citation' && !empty($this->get['style'])) {

                    $citation = new CitationModel($this->di);
                    $style = $citation->getFromName($this->get['style']);
                }

                $view = new ItemsView($this->di);
                return $view->export($items, $this->get['export'], $this->get['disposition'], $style);
            }

        } elseif (isset($this->post['omnitool'])) {

            $view = new DefaultView($this->di);
            return $view->main();

        } else {

            // Get tag list for search.
            $tag_model = new TagsModel($this->di);
            $items['tags'] = $tag_model->getTags('clipboard');
            $tag_model = null;

            $view = new ItemsView($this->di);
            return $view->filteredPage('clipboard', $this->get, $items);
        }
    }

    /**
     * Add to clipboard.
     *
     * @return string
     * @throws Exception
     */
    public function addAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);

        if (isset($this->post['id']) === false) {

            throw new Exception("id parameter is required", 400);
        }

        $this->validation->id($this->post['id']);

        $model = new ItemsModel($this->di);
        $response = $model->clipboardAdd($this->post['id']);

        // Max clipboard size.
        $info = ['max_count' => false];

        if ($response['max_count'] === true) {

            $info = [
                'info' => 'clipboard maximum size was reached',
                'max_count' => true
            ];
        }

        $view = new DefaultView($this->di);
        return $view->main($info);
    }

    /**
     * Delete from clipboard.
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

        if (isset($this->post['id']) === false) {

            throw new Exception("id parameter is required", 400);
        }

        $this->validation->id($this->post['id']);

        $model = new ItemsModel($this->di);
        $model->clipboardDelete($this->post['id']);

        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Create a list of authors for the item filter.
     *
     * @return string
     * @throws Exception
     */
    public function authorsAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new AuthorsModel($this->di);

        if (!empty($this->get['q'])) {

            $authors = $model->searchAuthors('clipboard', $this->get['q']);

        } else {

            $authors = $model->getAuthors('clipboard');
        }

        $view = new FilterView($this->di);
        return $view->linkList('clipboard', 'author', $authors);
    }

    /**
     * Create a list of editors for the item filter.
     *
     * @return string
     * @throws Exception
     */
    public function editorsAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new EditorsModel($this->di);

        if (!empty($this->get['q'])) {

            $editors = $model->searchEditors('clipboard', $this->get['q']);

        } else {

            $editors = $model->getEditors('clipboard');
        }

        $view = new FilterView($this->di);
        return $view->linkList('clipboard', 'editor', $editors);
    }

    /**
     * Create a list of tags for the item filter.
     *
     * @return string
     * @throws Exception
     */
    public function tagsAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (isset($this->get['filter']['tag']) && count($this->get['filter']['tag']) >= 3) {

            throw new Exception('a maximum of three tags can be combined', 400);
        }

        $model = new TagsModel($this->di);
        $tags = $model->getTags('clipboard', $this->get['filter']['tag'] ?? []);

        $view = new FilterView($this->di);
        return $view->linkList('clipboard', 'tag', $tags);
    }

    /**
     * Keywords.
     *
     * @return string
     * @throws Exception
     */
    public function keywordsAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new KeywordsModel($this->di);

        if (!empty($this->get['q'])) {

            $items = $model->search('clipboard', $this->get['q']);

        } else {

            $items = $model->get('clipboard');
        }

        $view = new FilterView($this->di);
        return $view->linkList('clipboard', 'keyword', $items);
    }

    /**
     * Primary publication titles.
     *
     * @return string
     * @throws Exception
     */
    public function primarytitlesAction(): string {

        return $this->publicationTitle('primary_title');
    }

    /**
     * Secondary publication titles.
     *
     * @return string
     * @throws Exception
     */
    public function secondarytitlesAction(): string {

        return $this->publicationTitle('secondary_title');
    }


    /**
     * Tertiary publication titles.
     *
     * @return string
     * @throws Exception
     */
    public function tertiarytitlesAction(): string {

        return $this->publicationTitle('tertiary_title');
    }

    /**
     * Publication title.
     *
     * @param string $type
     * @return string
     * @throws Exception
     */
    private function publicationTitle(string $type): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new PublicationtitlesModel($this->di);

        if (!empty($this->get['q'])) {

            $items = $model->search('clipboard', $type, $this->get['q']);

        } else {

            $items = $model->get('clipboard', $type);
        }

        $view = new FilterView($this->di);
        return $view->linkList('clipboard', $type, $items);
    }

    /**
     * Added time.
     *
     * @return string
     * @throws Exception
     */
    public function addedtimeAction(): string {

        return $this->itemColumn('added_time');
    }

    /**
     * Publication type filter.
     *
     * @return string
     * @throws Exception
     */
    public function publicationtypeAction(): string {

        return $this->itemColumn('reference_type');
    }

    /**
     * Custom 1.
     *
     * @return string
     * @throws Exception
     */
    public function custom1Action(): string {

        return $this->itemColumn('custom1');
    }

    /**
     * Custom 2.
     *
     * @return string
     * @throws Exception
     */
    public function custom2Action(): string {

        return $this->itemColumn('custom2');
    }

    /**
     * Custom 3.
     *
     * @return string
     * @throws Exception
     */
    public function custom3Action(): string {

        return $this->itemColumn('custom3');
    }

    /**
     * Custom 4.
     *
     * @return string
     * @throws Exception
     */
    public function custom4Action(): string {

        return $this->itemColumn('custom4');
    }

    /**
     * Custom 5.
     *
     * @return string
     * @throws Exception
     */
    public function custom5Action(): string {

        return $this->itemColumn('custom5');
    }

    /**
     * Custom 6.
     *
     * @return string
     * @throws Exception
     */
    public function custom6Action(): string {

        return $this->itemColumn('custom6');
    }

    /**
     * Custom 7.
     *
     * @return string
     * @throws Exception
     */
    public function custom7Action(): string {

        return $this->itemColumn('custom7');
    }

    /**
     * Custom 8.
     *
     * @return string
     * @throws Exception
     */
    public function custom8Action(): string {

        return $this->itemColumn('custom8');
    }

    /**
     * Item column.
     *
     * @param string $type
     * @return string
     * @throws Exception
     */
    private function itemColumn(string $type): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ItemcolumnsModel($this->di);

        if (!empty($this->get['q'])) {

            $items = $model->search('clipboard', $type, $this->get['q']);

        } else {

            $items = $model->get('clipboard', $type);
        }

        $view = new FilterView($this->di);
        return $view->linkList('clipboard', $type, $items);
    }
}
