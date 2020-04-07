<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

class ProjectController extends Controller {

    /**
     * ProjectController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();
    }

    /**
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new MainModel($this->di);
        $first_name = $model->getFirstName();

        $view = new ProjectView($this->di);

        return $view->main(['first_name' => $first_name]);
    }

    /**
     * Browse project.
     *
     * @return string
     * @throws Exception
     */
    public function browseAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        // Project id.
        if (isset($this->get['id']) === false) {

            throw new Exception("project id is required", 400);
        }

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("page parameter {$this->validation->error}", 422);
        }

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
        $search_keys = preg_grep('/search_/', array_keys($this->get));

        if ($search_keys === []) {

            // Browse.
            $items = $model->read(
                'project',
                $this->app_settings->getUser('sorting'),
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions,
                $this->get['id']
            );

        } else {

            // Compile search-related _GET keys.
            foreach ($search_keys as $search_key) {

                $search[$search_key] = $this->get[$search_key];
            }

            // Search.
            $items = $model->search(
                $search,
                'project',
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions,
                $this->get['id']
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

            $view = new ItemsView($this->di);

            if (isset($this->get['save_search'])) {

                // Save search.
                $url_get = $this->get;
                unset($url_get['page']);
                unset($url_get['save_search']);
                $search_url = '#' . IL_PATH_URL . '?' . http_build_query($url_get);

                // Model.
                $model = new SearchModel($this->di);
                $model->save('internal', $items['project'] . ' - ' . $view->searchName($this->get), $search_url);
            }

            return $view->page('project', $this->get, $items);
        }
    }

    /**
     * Filter project items.
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

            // Browse.
            $items = $model->read(
                'project',
                $this->app_settings->getUser('sorting'),
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions,
                $this->get['id']
            );

        } else {

            $items = $model->readFiltered(
                $this->get['filter'],
                'project',
                $this->app_settings->getUser('sorting'),
                $this->app_settings->getUser('page_size'),
                $from,
                $display_actions,
                $this->get['id']
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

            $view = new ItemsView($this->di);
            return $view->filteredPage('project', $this->get, $items);
        }
    }

    /**
     * Join an open-access project.
     *
     * @return string
     * @throws Exception
     */
    public function joinAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        if (isset($this->post['project_id']) === false) {

            throw new Exception("project id parameter is required", 400);
        }

        if ($this->validation->id($this->post['project_id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ProjectModel($this->di);
        $model->join($this->post['project_id']);
        $model = null;

        $view = new DefaultView($this->di);

        return $view->main();
    }

    /**
     * Leave an open-access project.
     *
     * @return string
     * @throws Exception
     */
    public function leaveAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        if (isset($this->post['project_id']) === false) {

            throw new Exception("project id parameter is required", 400);
        }

        if ($this->validation->id($this->post['project_id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ProjectModel($this->di);
        $model->leave($this->post['project_id']);
        $model = null;

        $view = new DefaultView($this->di);

        return $view->main();
    }

    /**
     * Activate user's project.
     *
     * @return string
     * @throws Exception
     */
    public function activateAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        if (isset($this->post['project_id']) === false) {

            throw new Exception("project id parameter is required", 400);
        }

        if ($this->validation->id($this->post['project_id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ProjectModel($this->di);
        $model->activate($this->post['project_id']);
        $model = null;

        $view = new DefaultView($this->di);

        return $view->main();
    }

    /**
     * Inactivate user's project.
     *
     * @return string
     * @throws Exception
     */
    public function inactivateAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        if (isset($this->post['project_id']) === false) {

            throw new Exception("project id parameter is required", 400);
        }

        if ($this->validation->id($this->post['project_id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ProjectModel($this->di);
        $model->inactivate($this->post['project_id']);
        $model = null;

        $view = new DefaultView($this->di);

        return $view->main();
    }

    /**
     * Delete user's project.
     *
     * @return string
     * @throws Exception
     */
    public function deleteAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        if (isset($this->post['project_id']) === false) {

            throw new Exception("project id parameter is required", 400);
        }

        if ($this->validation->id($this->post['project_id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ProjectModel($this->di);
        $model->delete($this->post['project_id']);
        $model = null;

        $view = new DefaultView($this->di);

        return $view->main();
    }

    /**
     * Edit form for a project.
     *
     * @return string
     * @throws Exception
     */
    public function editAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ProjectModel($this->di);
        $project = $model->get($this->get['id']);
        $model = null;

        $view = new ProjectView($this->di);

        return $view->edit($project);
    }

    /**
     * Update a project.
     *
     * @return string
     * @throws Exception
     */
    public function updateAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        if (isset($this->post['project_id']) === false) {

            throw new Exception("project id parameter is required", 400);
        }

        if ($this->validation->id($this->post['project_id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ProjectModel($this->di);
        $model->update($this->post['project_id'], $this->post);
        $model = null;

        $view = new DefaultView($this->di);

        return $view->main(['info' => 'Project was updated.']);
    }

    /**
     * Add item to project.
     *
     * @return string
     * @throws Exception
     */
    public function additemAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);

        if (isset($this->post['id']) === false) {

            throw new Exception("id parameter is required", 400);
        }

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        if (isset($this->post['project_id']) === false) {

            throw new Exception("project id parameter is required", 400);
        }

        if ($this->validation->id($this->post['project_id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        $model = new ItemsModel($this->di);
        $response = $model->projectAdd([$this->post['id']], $this->post['project_id']);

        // Max project size.
        $info = ['max_count' => false];

        if ($response['max_count'] === 'true') {

            $info = [
                'info' => 'Project maximum size reached.',
                'max_count' => true
            ];
        }

        $view = new DefaultView($this->di);
        return $view->main($info);
    }

    /**
     * Delete item from a project.
     *
     * @return string
     * @throws Exception
     */
    public function deleteitemAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);

        if (isset($this->post['id']) === false) {

            throw new Exception("id parameter is required", 400);
        }

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("id parameter {$this->validation->error}", 422);
        }

        if (isset($this->post['project_id']) === false) {

            throw new Exception("project id parameter is required", 400);
        }

        if ($this->validation->id($this->post['project_id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        $model = new ItemsModel($this->di);
        $model->projectDelete([$this->post['id']], $this->post['project_id']);

        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Project notes user's plus others.
     *
     * @return string
     * @throws Exception
     */
    public function notesAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        $model = new ProjectModel($this->di);
        $notes = $model->readNotes($this->get['id']);

        $view = new ProjectView($this->di);
        return $view->notes($this->get['id'], $notes);
    }

    /**
     * Save project notes.
     *
     * @return string
     * @throws Exception
     */
    public function savenotesAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        $model = new ProjectModel($this->di);
        $model->saveNotes($this->post['id'], $this->post['note']);

        // View. Send empty JSON to client note editor.
        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Get user's notes for TinyMCE.
     *
     * @return string
     * @throws Exception
     */
    public function usernotesAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        // Get file.
        $model = new ProjectModel($this->di);
        $notes = $model->readUserNotes($this->get['id']);

        // Notes are in HTML format.
        $note = isset($notes['user']['note']) ? $this->sanitation->lmth($notes['user']['note']) : '';

        $notes['user']['note'] = $note;

        // View. Send JSON to client note editor.
        $view = new DefaultView($this->di);
        return $view->main($notes);
    }

    /**
     * Compile all project's item notes.
     *
     * @return string
     * @throws Exception
     */
    public function compilenotesAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        // Get file.
        $model = new ProjectModel($this->di);
        $notes = $model->compileNotes($this->get['id']);

        // View.
        $view = new ProjectView($this->di);
        return $view->compilation($this->get['id'], $notes);
    }

    /**
     * Get project discussion.
     *
     * @return string
     * @throws Exception
     */
    public function discussionAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        $model = new ProjectModel($this->di);
        $messages = $model->loadDiscussion($this->get['id']);

        $view = new ProjectView($this->di);
        return $view->discussion($this->get['id'], $messages);
    }

    /**
     * Save discussion message.
     *
     * @return string
     * @throws Exception
     */
    public function savemessageAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);

        if ($this->validation->id($this->post['project_id']) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        if ($this->post['message'] === '') {

            throw new Exception("message is empty", 400);
        }

        $model = new ProjectModel($this->di);
        $model->saveMessage($this->post);

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

            $authors = $model->searchAuthors('project', $this->get['q'], $this->get['id']);

        } else {

            $authors = $model->getAuthors('project', $this->get['id']);
        }

        $view = new FilterView($this->di);
        return $view->linkList('project', 'author', $authors);
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

            $editors = $model->searchEditors('project', $this->get['q'], $this->get['id']);

        } else {

            $editors = $model->getEditors('project', $this->get['id']);
        }

        $view = new FilterView($this->di);
        return $view->linkList('project', 'editor', $editors);
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
        return $view->linkList('project', 'tag', $tags);
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

            $items = $model->search('project', $this->get['q'], $this->get['id']);

        } else {

            $items = $model->get('project', $this->get['id']);
        }

        $view = new FilterView($this->di);
        return $view->linkList('project', 'keyword', $items);
    }

    /**
     * Primary publication titles.
     *
     * @return string
     * @throws Exception
     */
    public function primarytitlesAction(): string {

        return $this->publicationTitle('primary_title', $this->get['id']);
    }

    /**
     * Secondary publication titles.
     *
     * @return string
     * @throws Exception
     */
    public function secondarytitlesAction(): string {

        return $this->publicationTitle('secondary_title', $this->get['id']);
    }


    /**
     * Tertiary publication titles.
     *
     * @return string
     * @throws Exception
     */
    public function tertiarytitlesAction(): string {

        return $this->publicationTitle('tertiary_title', $this->get['id']);
    }

    /**
     * Publication title.
     *
     * @param string $type
     * @return string
     * @throws Exception
     */
    private function publicationTitle(string $type, int $project_id): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new PublicationtitlesModel($this->di);

        if (!empty($this->get['q'])) {

            $items = $model->search('project', $type, $this->get['q'], $project_id);

        } else {

            $items = $model->get('project', $type, $project_id);
        }

        $view = new FilterView($this->di);
        return $view->linkList('project', $type, $items);
    }

    /**
     * Added time.
     *
     * @return string
     * @throws Exception
     */
    public function addedtimeAction(): string {

        return $this->itemColumn('added_time', $this->get['id']);
    }

    /**
     * Custom 1.
     *
     * @return string
     * @throws Exception
     */
    public function custom1Action(): string {

        return $this->itemColumn('custom1', $this->get['id']);
    }

    /**
     * Custom 2.
     *
     * @return string
     * @throws Exception
     */
    public function custom2Action(): string {

        return $this->itemColumn('custom2', $this->get['id']);
    }

    /**
     * Custom 3.
     *
     * @return string
     * @throws Exception
     */
    public function custom3Action(): string {

        return $this->itemColumn('custom3', $this->get['id']);
    }

    /**
     * Custom 4.
     *
     * @return string
     * @throws Exception
     */
    public function custom4Action(): string {

        return $this->itemColumn('custom4', $this->get['id']);
    }

    /**
     * Custom 5.
     *
     * @return string
     * @throws Exception
     */
    public function custom5Action(): string {

        return $this->itemColumn('custom5', $this->get['id']);
    }

    /**
     * Custom 6.
     *
     * @return string
     * @throws Exception
     */
    public function custom6Action(): string {

        return $this->itemColumn('custom6', $this->get['id']);
    }

    /**
     * Custom 7.
     *
     * @return string
     * @throws Exception
     */
    public function custom7Action(): string {

        return $this->itemColumn('custom7', $this->get['id']);
    }

    /**
     * Custom 8.
     *
     * @return string
     * @throws Exception
     */
    public function custom8Action(): string {

        return $this->itemColumn('custom8', $this->get['id']);
    }

    /**
     * Item column.
     *
     * @param string $type
     * @param int $project_id
     * @return string
     * @throws Exception
     */
    private function itemColumn(string $type, int $project_id): string {

        // Authorization.
        $this->authorization->signedId(true);

        if ($this->validation->id($project_id) === false) {

            throw new Exception("project id parameter {$this->validation->error}", 422);
        }

        $model = new ItemcolumnsModel($this->di);

        if (!empty($this->get['q'])) {

            $items = $model->search('project', $type, $this->get['q'], $project_id);

        } else {

            $items = $model->get('project', $type, $project_id);
        }

        $view = new FilterView($this->di);
        return $view->linkList('project', $type, $items);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function rssAction(): string {

        $model = new ItemsModel($this->di);
        $items = $model->read('project', 'id', 20, 0, 'rss', $this->get['id']);
        $model = null;

        $view = new RSSView($this->di);
        return $view->main($items['items']);
    }
}
