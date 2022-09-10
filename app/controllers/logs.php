<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;

class LogsController extends AppController {

    /**
     * LogsController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('A');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $view = new LogsView($this->di);
        return $view->main();
    }
}
