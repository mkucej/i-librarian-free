<?php

namespace LibrarianApp;

use Exception;
use Librarian\Security\Encryption;

/**
 * Class UsersController
 *
 * Admin's user management.
 */
class UsersController extends AppController {

    /**
     * Main action provides settings form with current settings.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('A');

        $model = new UsersModel($this->di);
        $users = $model->list();

        // Settings view.
        $view = new UsersView($this->di);
        return $view->main($users);
    }

    /**
     * Create user - admin version.
     *
     * @return string
     * @throws Exception
     */
    public function createAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('A');

        $this->post['user'] = $this->sanitation->length($this->post['user'], 1024);

        // Verify email.
        if (empty($this->post['user']['email']) === true) {

            throw new Exception("email is required", 400);
        }

        $this->validation->email($this->post['user']['email']);

        // Username.
        if (empty($this->post['user']['username']) === true) {

            $this->post['user']['username'] = strstr($this->post['user']['email'], '@', true);
        }

        // Create password.
        /** @var Encryption $enc */
        $enc = $this->di->get('Encryption');
        $this->post['user']['password'] = $enc->getRandomKey(12);

        $model = new UsersModel($this->di);
        $model->adminCreateUser($this->post['user']);

        // Send the view.
        $view = new UsersView($this->di);
        return $view->userCreated($this->post['user']);
    }

    /**
     * Update user - admin version.
     *
     * @return string
     * @throws Exception
     */
    public function updateAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('A');

        $this->post['user'] = $this->sanitation->length($this->post['user'], 1024);

        $model = new UsersModel($this->di);
        $sessions = $model->adminUpdateUser($this->post['user']);

        // Log out the user.
        $this->session->deleteSessionFiles($sessions);

        // Send the view.
        $view = new DefaultView($this->di);
        return $view->main(['info' => 'user was updated']);
    }

    /**
     * Reset user's password.
     *
     * @return string
     * @throws Exception
     */
    public function resetAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('A');

        // Create password.
        /** @var Encryption $enc */
        $enc = $this->di->get('Encryption');
        $this->post['user']['password'] = $enc->getRandomKey(12);

        $model = new UsersModel($this->di);
        $sessions = $model->adminUpdateUser($this->post['user']);

        // Log out the user.
        $this->session->deleteSessionFiles($sessions);

        // Send the view.
        $view = new UsersView($this->di);
        return $view->userReset($this->post['user']);
    }
}
