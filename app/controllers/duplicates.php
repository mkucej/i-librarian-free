<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

class DuplicatesController extends Controller {

    /**
     * DuplicatesController constructor.
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
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $view = new DuplicatesView($this->di);
        return $view->main();
    }

    /**
     * Find duplicates using different modes.
     *
     * @return string
     * @throws Exception
     */
    public function findAction(): string {

        $model = new DuplicatesModel($this->di);
        $view = new DuplicatesView($this->di);

        switch ($this->get['mode']) {

            case 'similar':
                $result = $model->similar();
                return $view->similar($result);

            case 'identical':
                $result = $model->identical();
                return $view->identical($result);

            case 'files':
                $result = $model->pdfs();
                return $view->pdfs($result);

            default:
                $view = new DefaultView($this->di);
                return $view->main();
        }
    }

    /**
     * Merge duplicate records into one.
     *
     * @return string
     * @throws Exception
     */
    public function mergeAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Validate.
        foreach ($this->post['ids'] as $id) {

            if ($this->validation->id($id) === false) {

                throw new Exception('parameter ids ' . $this->validation->error, 400);
            }
        }

        // Merge duplicates.
        $model = new DuplicatesModel($this->di);
        $model->merge($this->post['ids']);
        $model = null;

        // Remove the smallest item id, so it is not deleted.
        $ids = $this->post['ids'];
        sort($ids);
        $first_id = array_shift($ids);

        // Delete duplicate ids.
        $model = new ItemModel($this->di);
        $model->delete($ids);
        $model = null;

        $view = new DefaultView($this->di);
        return $view->main(['info' => "Items were merged into ID $first_id."]);
    }
}
