<?php

namespace LibrarianApp;

use Exception;

class ScopusController extends AppController {

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
        $view = new ScopusView($this->di);
        return $view->main();
    }
}
