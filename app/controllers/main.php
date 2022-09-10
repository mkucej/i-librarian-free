<?php

namespace LibrarianApp;

use Exception;

class MainController extends AppController {

    /**
     * Main. HTML base view.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction() {

        $this->session->close();

        $model = new MainModel($this->di);
        $view  = new MainView($this->di);

        // Authenticated view.
        if ($this->session->data('user_id') !== null) {

            $first_name = $model->getFirstName();

            return $view->getAuthenticated(['first_name' => $first_name]);
        }

        // Defaults to the registration view, if 0 accounts and not LDAP authentication.
        if ($this->app_settings->getIni('ldap', 'ldap_active') === "0") {

            $num_users = (integer) $model->numUsers();

            // Database is empty. Send registration form.
            if ($num_users === 0) {

                $view = new RegistrationView($this->di);
                return $view->main($num_users);
            }
        }

        $model = null;

        // Non-authenticated view.
        return $view->getNonAuthenticated();
    }
}
