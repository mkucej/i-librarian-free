<?php

namespace LibrarianApp;

use Exception;
use Librarian\Mvc\Controller;

/**
 * Class SearchController
 *
 * Manages saved searches.
 */
class SearchController extends Controller {

    /**
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // View.
        $view = new DefaultView($this->di);
        return $view->main([]);
    }

    /**
     * Delete a saved search.
     *
     * @return string
     * @throws Exception
     */
    public function deleteAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // Model.
        $model = new SearchModel($this->di);
        $model->delete($this->post['id']);

        // View.
        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * List internal searches for a modal list.
     *
     * @return string
     * @throws Exception
     */
    public function listAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // Model.
        $model = new SearchModel($this->di);
        $searches = $model->list('internal');

        // View.
        $view = new ItemsView($this->di);
        $list = $view->sharedSearchList($searches);

        $view = new DefaultView($this->di);
        return $view->main(['html' => $list]);
    }
}
