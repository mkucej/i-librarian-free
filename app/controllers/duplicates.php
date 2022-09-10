<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;

class DuplicatesController extends AppController {

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
        $this->authorization->permissions('U');
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

            $this->validation->id($id);
        }

        $this->validation->id($this->post['id_to_keep']);

        if (in_array($this->post['id_to_keep'], $this->post['ids']) === false) {

            throw new Exception("item id mismatch", 422);
        }

        // Remove id to keep from duplicate items array, so it is not deleted.
        unset($this->post['ids'][array_search($this->post['id_to_keep'], $this->post['ids'])]);
        // Reset array keys.
        $this->post['ids'] = array_values($this->post['ids']);

        // Merge duplicates.
        $model = new DuplicatesModel($this->di);
        $model->merge($this->post['ids'], $this->post['id_to_keep']);
        $model = null;

        // Delete duplicate ids.
        $model = new ItemModel($this->di);
        $model->delete($this->post['ids']);
        $model = null;

        $view = new DefaultView($this->di);
        return $view->main(['info' => "items were merged"]);
    }
}
