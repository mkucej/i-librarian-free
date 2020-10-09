<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

class ItemdiscussionController extends Controller {

    /**
     * ItemdiscussionContoller constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
    }

    /**
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        if (isset($this->get['id']) === false) {

            throw new Exception("id parameter required", 400);
        }

        $this->validation->id($this->get['id']);

        $model = new ItemdiscussionModel($this->di);
        $messages = $model->load($this->get['id']);

        $view = new ItemdiscussionView($this->di);
        return $view->main($this->get['id'], $messages);
    }

    /**
     * Save.
     *
     * @return string
     * @throws Exception
     */
    public function saveAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        if (isset($this->post['id']) === false) {

            throw new Exception("id parameter required", 400);
        }

        $this->validation->id($this->post['id']);

        $trimmed_post = $this->sanitation->trim($this->post);

        if ($trimmed_post['message'] !== '') {

            $model = new ItemdiscussionModel($this->di);
            $model->save($trimmed_post);
        }

        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Messages.
     *
     * @return string
     * @throws Exception
     */
    public function messagesAction(): string {

        if (isset($this->get['id']) === false) {

            throw new Exception("id parameter required", 400);
        }

        $this->validation->id($this->get['id']);

        $model = new ItemdiscussionModel($this->di);
        $messages = $model->load($this->get['id']);

        $view = new ItemdiscussionView($this->di);
        return $view->messages($messages);
    }
}
