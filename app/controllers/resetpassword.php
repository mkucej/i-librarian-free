<?php

namespace LibrarianApp;

use Exception;

/**
 * Class ResetpasswordController
 *
 * Reset password page and actions. User.
 */
class ResetpasswordController extends AppController {

    /**
     * Main action is to send the registration HTML view.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        // Authorization.
        $this->authorization->signedId(false);

        $view = new ResetpasswordView($this->di);

        return $view->main();
    }

    /**
     * Reset password and send message.
     *
     * @return string JSON response view.
     * @throws Exception
     */
    public function sendAction(): string {

        // Requires POST.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception('request method must be POST', 405);
        }

        // Authorization.
        $this->authorization->signedId(false);

        // Model.
        $model = new AccountModel($this->di);
        $password = $model->resetPassword($this->post['username']);

        // Send email.
        if (!empty($password)) {

            $view = new ResetpasswordView($this->di);
            return $view->result($password);
        }

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'Password reset failed. Please try again.']);
    }
}
