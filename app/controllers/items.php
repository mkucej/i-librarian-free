<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

class ItemsController extends Controller {

    /**
     * ItemsController constructor.
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

        if ($this->validation->id($this->get['page']) === false) {

            throw new Exception("page parameter {$this->validation->error}", 422);
        }

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
                'library',
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
                'library',
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
                $zip = $model->exportZip($items['items']);

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
            $items['tags'] = $tag_model->getTags('library');
            $tag_model = null;

            $view = new ItemsView($this->di);

            if (isset($this->get['save_search'])) {

                // Save search.
                $url_get = $this->get;
                unset($url_get['page']);
                unset($url_get['save_search']);
                $search_url = '#' . IL_PATH_URL . '?' . http_build_query($url_get);

                // Model.
                $model = new SearchModel($this->di);
                $model->save('internal', 'Library - ' . $view->searchName($this->get), $search_url);
            }

            return $view->page('library', $this->get, $items);
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

        if ($this->validation->id($this->get['page']) === false) {

            throw new Exception("page parameter {$this->validation->error}", 422);
        }

        // Limit from.
        $from = ($this->get['page'] - 1) * $this->app_settings->getUser('page_size');
        $from = min($from, $this->app_settings->getGlobal('max_items') - $this->app_settings->getUser('page_size'));

        // Display type export|page|omnitool.
        $display_actions = isset($this->get['export']) ? 'export' : $this->app_settings->getUser('display_type');
        $display_actions = isset($this->post['omnitool']) ? $this->post['omnitool'] : $display_actions;

        $model = new ItemsModel($this->di);

        if (empty($this->get['filter'])) {

            $items = $model->read(
                'library',
                $this->app_settings->getUser('sorting'),
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions
            );

        } else {

            $items = $model->readFiltered(
                $this->get['filter'],
                'library',
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
                $zip = $model->exportZip($items['items']);

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
            $items['tags'] = $tag_model->getTags('library');
            $tag_model = null;

            $view = new ItemsView($this->di);
            return $view->filteredPage('library', $this->get, $items);
        }
    }

    /*
     * Below are actions for the filter.
     */

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

            $authors = $model->searchAuthors('library', $this->get['q']);

        } else {

            $authors = $model->getAuthors('library');
        }

        $view = new FilterView($this->di);
        return $view->linkList('library', 'author', $authors);
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

            $editors = $model->searchEditors('library', $this->get['q']);

        } else {

            $editors = $model->getEditors('library');
        }

        $view = new FilterView($this->di);
        return $view->linkList('library', 'editor', $editors);
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
        $tags = $model->getTags('library', $this->get['filter']['tag'] ?? []);

        $view = new FilterView($this->di);
        return $view->linkList('library', 'tag', $tags);
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

            $items = $model->search('library', $this->get['q']);

        } else {

            $items = $model->get('library');
        }

        $view = new FilterView($this->di);
        return $view->linkList('library', 'keyword', $items);
    }

    /**
     * Primary title.
     *
     * @return string
     * @throws Exception
     */
    public function primarytitlesAction(): string {

        return $this->publicationTitle('primary_title');
    }

    /**
     * Secondary title.
     *
     * @return string
     * @throws Exception
     */
    public function secondarytitlesAction(): string {

        return $this->publicationTitle('secondary_title');
    }

    /**
     * Tertiary title.
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

            $items = $model->search('library', $type, $this->get['q']);

        } else {

            $items = $model->get('library', $type);
        }

        $view = new FilterView($this->di);
        return $view->linkList('library', $type, $items);
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
     * @param $type
     * @return string
     * @throws Exception
     */
    private function itemColumn($type): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ItemcolumnsModel($this->di);

        if (!empty($this->get['q'])) {

            $items = $model->search('library', $type, $this->get['q']);

        } else {

            $items = $model->get('library', $type);
        }

        $view = new FilterView($this->di);
        return $view->linkList('library', $type, $items);
    }

    /**
     * Misc.
     *
     * @return string
     * @throws Exception
     */
    public function miscAction(): string {

        $choices = [
            'nopdf'       => 'No PDF file',
            'myitems'     => 'Added by me',
            'othersitems' => 'Added by others'
        ];

        $view = new FilterView($this->di);
        return $view->linkList('library', 'misc', $choices);
    }

    /**
     * Catalog.
     *
     * @return string
     * @throws Exception
     */
    public function catalogAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (isset($this->get['from_id'])) {

            // List.

            // From.
            $this->get['page'] = isset($this->get['page']) ? $this->get['page'] : 1;

            if ($this->validation->id($this->get['page']) === false) {

                throw new Exception("page parameter {$this->validation->error}", 422);
            }

            if ($this->validation->id($this->get['from_id']) === false) {

                throw new Exception("id parameter {$this->validation->error}", 422);
            }

            $from_id = $this->get['from_id'] % $this->app_settings->getGlobal('max_items') === 1 ? $this->get['from_id'] : 1;

            // Limit from.
            $from = ($this->get['page'] - 1) * $this->app_settings->getUser('page_size');
            $from = min($from, $this->app_settings->getGlobal('max_items') - $this->app_settings->getUser('page_size'));

            // Display type export|page|omnitool.
            $display_actions = isset($this->get['export']) ? 'export' : $this->app_settings->getUser('display_type');
            $display_actions = isset($this->post['omnitool']) ? $this->post['omnitool'] : $display_actions;

            $model = new ItemsModel($this->di);

            $items = $model->readFiltered(
                ['catalog' => $from_id],
                'library',
                $this->app_settings->getUser('sorting'),
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions
            );

            $items['from_id'] = $from_id;

            // Render view.
            if ($display_actions === 'export') {

                if ($this->get['export'] === 'zip') {

                    // Export to a ZIP file.
                    $zip = $model->exportZip($items['items']);

                    $view = new FileView($this->di, $zip);
                    return $view->main('attachment');

                } else {

                    // Other exports.
                    $style = '';

                    if (!empty($this->get['style'])) {

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

                $view = new ItemsView($this->di);
                return $view->page('catalog', $this->get, $items);
            }

        } else {

            // Initial catalog cards.

            $model = new ItemsModel($this->di);
            $count = $model->maxId();

            $view = new ItemsView($this->di);
            return $view->catalog($count);
        }
    }

    /**
     * Export modal form.
     *
     * @return string
     * @throws Exception
     */
    public function exportformAction(): string {

        $this->authorization->signedId(true);
        $this->authorization->permissions('G');

        $view = new ItemsView($this->di);
        return $view->exportForm();
    }

    /**
     * Omnitool modal form.
     *
     * @return string
     * @throws Exception
     */
    public function omnitoolformAction(): string {

        $this->authorization->signedId(true);

        $model = new ProjectModel($this->di);
        $projects = $model->list();
        $model = null;

        $model = new TagsModel($this->di);
        $tags = $model->getTags('library');

        $view = new ItemsView($this->di);
        return $view->omnitoolForm($projects['active_projects'], $tags);
    }

    /**
     * RSS. Disabled until authentication is resolved.
     *
     * @return string
     * @throws Exception
     */
//    public function rssAction(): string {
//
//        $model = new ItemsModel($this->di);
//        $items = $model->read('library', 'id', 20, 0, 'rss');
//        $model = null;
//
//        $view = new RSSView($this->di);
//        return $view->main($items['items']);
//    }
}
