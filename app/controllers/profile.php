<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

class ProfileController extends Controller {

    /**
     * ProfileController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();
    }

    /**
     * Display user's profile.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // Authorization.
        $this->authorization->signedId(true);

        // Get user profile.
        $model = new AccountModel($this->di);

        $user_profile = $model->readProfile();
        $model = null;

        $view = new ProfileView($this->di);

        return $view->main($user_profile);
    }

    /**
     * Update user's profile.
     *
     * @return string
     * @throws Exception
     */
    public function updateAction(): string {

        $view = new DefaultView($this->di);

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);

        // Get POST vars.
        $this->post['profile'] = $this->sanitation->length($this->post['profile'], 1024);

        if (isset($this->post['profile']['username']) === false) {

            throw new Exception('username is required', 400);
        }

        if (isset($this->post['profile']['email']) === false) {

            throw new Exception('email is required', 400);
        }

        $this->validation->email($this->post['profile']['email']);

        // Save user profile.
        $model = new AccountModel($this->di);
        $model->updateProfile($this->post['profile']);
        $model = null;

        return $view->main(['info' => 'user profile was saved']);
    }

    /**
     * Change user's password.
     *
     * @return string
     * @throws Exception
     */
    public function changepasswordAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);

        $view = new DefaultView($this->di);

        // Get POST vars.
        $this->post['profile'] = $this->sanitation->length($this->post['profile'], 1024);

        if (isset($this->post['profile']['old_password']) === false) {

            throw new Exception('current password is required', 400);
        }

        if (isset($this->post['profile']['new_password']) === false) {

            throw new Exception('new password is required', 400);
        }

        if (isset($this->post['profile']['new_password2']) === false) {

            throw new Exception('new password is required', 400);
        }

        // Check for typos.
        if ($this->post['profile']['new_password'] !== $this->post['profile']['new_password2']) {

            throw new Exception('new password was mistyped', 400);
        }

        // Validate password.
        $this->validation->password($this->post['profile']['new_password']);

        // Save user profile.
        $model = new AccountModel($this->di);

        $model->updatePassword($this->post['profile']);
        $model = null;

        return $view->main(['info' => 'new password was saved']);
    }
}
