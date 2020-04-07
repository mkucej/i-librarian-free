<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

class RegistrationController extends Controller {

    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        if ($this->app_settings->getGlobal('disallow_signup') === '1') {

            throw new Exception('creating new accounts is not authorized', 403);
        }

        // Authorization.
        $this->authorization->signedId(false);
    }

    /**
     * Main action is to send the registration HTML view.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $view = new RegistrationView($this->di);
        return $view->main();
    }

    /**
     * Create account action.
     *
     * @return string JSON response view.
     * @throws Exception
     */
    public function createaccountAction(): string {

        // Requires POST.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception('request method must be POST', 405);
        }

        $sanitized_post = $this->sanitation->length($this->post, 1024);

        $view = new DefaultView($this->di);

        // Password typo.
        if ($sanitized_post['password'] !== $sanitized_post['password2']) {

            return $view->main(['info' => 'Password was mistyped.']);
        }

        // Validate password.
        if ($this->validation->password($sanitized_post['password']) === false) {

            return $view->main(['info' => "Password {$this->validation->error}."]);
        }

        // Validate email.
        if (!empty($sanitized_post['email']) && $this->validation->email($sanitized_post['email']) === false) {

            return $view->main(['info' => "Email {$this->validation->error}."]);
        }

        // Create account.
        $model = new AccountModel($this->di);

        $user = $model->createUser([
            'username'   => $sanitized_post['username'],
            'password'   => $sanitized_post['password'],
            'first_name' => $sanitized_post['first_name'],
            'last_name'  => $sanitized_post['last_name'],
            'email'      => $sanitized_post['email']
        ]);

        // User successfully registered, let's sign them in.
        if (isset($user['id'])) {

            $authentication = new AuthenticationController($this->di);

            return $authentication->signinAction();
        }

        return $view->main($user);
    }
}
