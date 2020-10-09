<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

class NormalizeController extends Controller {

    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');
    }

    /**
     * Main. Initial view - form, links.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // View.
        $view = new NormalizeView($this->di);
        return $view->main();
    }

    /**
     * List author duplicates.
     *
     * @return string
     * @throws Exception
     */
    public function authorsAction(): string {

        $model = new NormalizeModel($this->di);
        $data = $model->similarAuthors();

        // View.
        $view = new NormalizeView($this->di);
        return $view->results('authors', $data);
    }

    /**
     * List editor duplicates.
     *
     * @return string
     * @throws Exception
     */
    public function editorsAction(): string {

        $model = new NormalizeModel($this->di);
        $data = $model->similarEditors();

        // View.
        $view = new NormalizeView($this->di);
        return $view->results('editors', $data);
    }

    /**
     * List primary title duplicates.
     *
     * @return string
     * @throws Exception
     */
    public function primaryAction(): string {

        $model = new NormalizeModel($this->di);
        $data = $model->similarTitles('primary');

        // View.
        $view = new NormalizeView($this->di);
        return $view->results('primary', $data);
    }

    /**
     * List secondary title duplicates.
     *
     * @return string
     * @throws Exception
     */
    public function secondaryAction(): string {

        $model = new NormalizeModel($this->di);
        $data = $model->similarTitles('secondary');

        // View.
        $view = new NormalizeView($this->di);
        return $view->results('secondary', $data);
    }

    /**
     * List tertiary title duplicates.
     *
     * @return string
     * @throws Exception
     */
    public function tertiaryAction(): string {

        $model = new NormalizeModel($this->di);
        $data = $model->similarTitles('tertiary');

        // View.
        $view = new NormalizeView($this->di);
        return $view->results('tertiary', $data);
    }

    /**
     * Search duplicate authors.
     *
     * @return string
     * @throws Exception
     */
    public function searchauthorsAction(): string {

        $model = new AuthorsModel($this->di);

        $data = [];

        if (!empty($this->get['q'])) {

            $data = $model->searchAuthors('library', $this->get['q']);
            $data = array_slice($data, 0, 20, true);
        }

        // View.
        $view = new NormalizeView($this->di);
        return $view->filtered('authors', $data);
    }

    /**
     * Search duplicate editors.
     *
     * @return string
     * @throws Exception
     */
    public function searcheditorsAction(): string {

        $model = new EditorsModel($this->di);

        $data = [];

        if (!empty($this->get['q'])) {

            $data = $model->searchEditors('library', $this->get['q']);
            $data = array_slice($data, 0, 20, true);
        }

        // View.
        $view = new NormalizeView($this->di);
        return $view->filtered('editors', $data);
    }

    /**
     * Search duplicate primary titles.
     *
     * @return string
     * @throws Exception
     */
    public function searchprimarytitlesAction(): string {

        $model = new PublicationtitlesModel($this->di);

        $data = [];

        if (!empty($this->get['q'])) {

            $data = $model->search('library', 'primary_title', $this->get['q']);
            $data = array_slice($data, 0, 20, true);
        }

        // View.
        $view = new NormalizeView($this->di);
        return $view->filtered('primary', $data);
    }

    /**
     * Search duplicate secondary titles.
     *
     * @return string
     * @throws Exception
     */
    public function searchsecondarytitlesAction(): string {

        $model = new PublicationtitlesModel($this->di);

        $data = [];

        if (!empty($this->get['q'])) {

            $data = $model->search('library', 'secondary_title', $this->get['q']);
            $data = array_slice($data, 0, 20, true);
        }

        // View.
        $view = new NormalizeView($this->di);
        return $view->filtered('secondary', $data);
    }

    /**
     * Search duplicate tertiary titles.
     *
     * @return string
     * @throws Exception
     */
    public function searchtertiarytitlesAction(): string {

        $model = new PublicationtitlesModel($this->di);

        $data = [];

        if (!empty($this->get['q'])) {

            $data = $model->search('library', 'tertiary_title', $this->get['q']);
            $data = array_slice($data, 0, 20, true);
        }

        // View.
        $view = new NormalizeView($this->di);
        return $view->filtered('tertiary', $data);
    }

    /**
     * Edit displayed metadata.
     *
     * @return string
     * @throws Exception
     */
    public function editAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        $model = new NormalizeModel($this->di);
        $model->edit($this->post);

        // View.
        $view = new DefaultView($this->di);
        return $view->main(['info' => 'new data were saved']);
    }
}
