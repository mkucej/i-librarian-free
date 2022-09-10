<?php

namespace LibrarianApp;

use Exception;

class NotFoundController extends AppController {

    /**
     * Main. Not found error view.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction() {

        $this->session->close();

        try {

            throw new Exception('page not found', 404);

        } catch (Exception $exc) {

            $view = new ErrorView($this->di);
            return $view->main($exc);
        }
    }
}
