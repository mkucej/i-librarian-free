<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

class IconController extends Controller {

    /**
     * IconController constructor.
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

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter <kbd>id</kbd> is required", 400);
        }

        $this->validation->id($this->get['id']);

        // Get icon.
        $model = new IconModel($this->di);
        $stream = $model->readIcon($this->get['id']);

        // View.
        $view = new FileView($this->di, $stream);
        return $view->main();
    }
}
