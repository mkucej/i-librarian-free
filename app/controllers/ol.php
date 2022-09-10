<?php

namespace LibrarianApp;

use Exception;
use Librarian\External\Ol;

/**
 * Class OlController.
 *
 * Open Library.
 */
class OlController extends AppController {

    /**
     * @var Ol
     */
    private $ol;

    /**
     * Main. Noop.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // View.
        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Convert JSON input to item metadata array. UID importer.
     *
     * @return string
     * @throws Exception
     */
    public function convertAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // Model.
        $this->ol = $this->di->getShared('Ol');

        // Get 10 results from $from.
        $items = $this->ol->formatMetadata($this->post['json']);

        // View.
        $view = new DefaultView($this->di);
        return $view->main($items);
    }
}
