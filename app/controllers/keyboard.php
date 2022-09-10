<?php

namespace LibrarianApp;

use Exception;

class KeyboardController extends AppController {

    /**
     * Main. Keyboard popup.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction() {

        $this->session->close();

        // Must be signed in.
        $this->authorization->signedId(true);

        // View.
        $view = new KeyboardView($this->di);
        return $view->main();
    }
}
