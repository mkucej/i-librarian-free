<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

/**
 * Class NotesController
 *
 * Item notes.
 */
class NotesController extends Controller {

    /**
     * NotesController constructor.
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
     * Main. Show all item notes, user + others.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        // Get file.
        $model = new NotesModel($this->di);
        $notes = $model->readAll($this->get['id']);

        // View. Item notes view.
        $view = new NotesView($this->di);
        return $view->main($this->get['id'], $notes);
    }

    /**
     * Gte user notes for TinyMCE note editor.
     *
     * @return string
     * @throws Exception
     */
    public function userAction(): string {

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter id is required", 400);
        }

        if ($this->validation->id($this->get['id']) === false) {

            throw new Exception("the parameter id {$this->validation->error}", 422);
        }

        // Get file.
        $model = new NotesModel($this->di);
        $notes = $model->readUser($this->get['id']);

        // Add item id to response.
        $notes['item_id'] = $this->get['id'];

        // Notes are in HTML format.
        $note = isset($notes['user']['note']) ? $this->sanitation->lmth($notes['user']['note']) : '';

        $notes['user']['note'] = $note;

        // View. Send JSON to client note editor.
        $view = new DefaultView($this->di);
        return $view->main($notes);
    }

    /**
     * Save user note.
     *
     * @return string
     * @throws Exception
     */
    public function saveAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        if ($this->validation->id($this->post['id']) === false) {

            throw new Exception("the parameter <kbd>item_id</kbd> {$this->validation->error}", 422);
        }

        // Save new settings permanently.
        $model = new NotesModel($this->di);
        $model->save($this->post['id'], $this->post['note']);

        // View. Send empty JSON to client note editor.
        $view = new DefaultView($this->di);
        return $view->main();
    }
}
