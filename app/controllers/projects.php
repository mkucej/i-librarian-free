<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;

class ProjectsController extends AppController {

    /**
     * ProjectsController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();
    }

    /**
     * Main. List projects.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        $model = new ProjectModel($this->di);
        $projects = $model->list();
        $model = null;

        $view = new ProjectsView($this->di);

        return $view->main($projects);
    }

    /**
     * Create new project.
     *
     * @return string
     * @throws Exception
     */
    public function createAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('U');

        $model = new ProjectModel($this->di);
        $model->create($this->post);
        $projects = $model->list();
        $model = null;

        $view = new ProjectsView($this->di);

        return $view->main($projects);
    }
}
