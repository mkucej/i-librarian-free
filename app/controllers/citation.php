<?php

namespace LibrarianApp;

use Exception;
use Librarian\Mvc\Controller;

/**
 * Class CitationController
 *
 * This controller deals with CSL styles.
 */
class CitationController extends Controller {

    /**
     * Get a list of CSL styles. Tools page.
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
        $model = new CitationModel($this->di);
        $data = $model->list();

        // View.
        $view = new CitationView($this->di);
        return $view->main($data);
    }

    /**
     * Edit the CSL style.
     *
     * @return string
     * @throws Exception
     */
    public function editAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        // Model.
        $model = new CitationModel($this->di);
        $model->edit($this->post['csl']);

        // View.
        $view = new DefaultView($this->di);
        return $view->main(['info' => 'new citation style was saved']);
    }

    /**
     * Get a CSL style. Modal window.
     *
     * @return string
     * @throws Exception
     */
    public function getAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        // Model.
        $model = new CitationModel($this->di);
        $csl = $model->get($this->get['id']);

        // View.
        $view = new DefaultView($this->di);
        return $view->main(['csl' => $csl]);
    }

    /**
     * Create db from Github files in import/csl.
     *
     * @return string
     * @throws Exception
     */
    public function populateAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('A');

        // Model.
        $model = new CitationModel($this->di);
        $model->populate();

        // View.
        $view = new DefaultView($this->di);
        return $view->main();
    }
}
