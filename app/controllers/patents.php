<?php

namespace LibrarianApp;

use Exception;
use Librarian\External\Patents;
use Librarian\Mvc\Controller;

class PatentsController extends Controller {

    /**
     * @var Patents
     */
    private $patents;

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

        // View.
        $view = new PatentsView($this->di);
        return $view->main();
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

        // Patents model. We use Google to fetch, no keys required.
        $this->patents = $this->di->getShared('Patents');

        $items = $this->patents->fetch($this->get['uid']);

        // View.
        $view = new DefaultView($this->di);
        return $view->main($items);
    }
}
