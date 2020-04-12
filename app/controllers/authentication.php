<?php

namespace LibrarianApp;

use Exception;
use Librarian\Mvc\Controller;
use Librarian\Security\Ldap;

class AuthenticationController extends Controller {

    /**
     * @var Ldap
     */
    private $ldap;

    /**
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction() {

        return $this->signinAction();
    }

    /**
     * Sign in.
     *
     * @return string
     * @throws Exception
     */
    public function signinAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Must be signed out.
        $this->authorization->signedId(false);

        // Regenerate session id to prevent hijacking.
        $this->session->regenerateId();

        $model = new AuthenticationModel($this->di);

        $view  = new DefaultView($this->di);

        /** @var array $ldap_settings */
        $ldap_settings = $this->app_settings->getIni('ldap');

        if ($ldap_settings['ldap_active'] === '1') {

            // LDAP authentication.
            $this->ldap = $this->di->get('Ldap');
            $ldap_response = $this->ldap->authenticate($this->post['username'], $this->post['password']);

            // Feedback info for a user.
            if (isset($ldap_response['info'])) {

                return $view->main($ldap_response);
            }

            // Create/update LDAP user in local database.
            $user_data = $model->syncLdapUser($ldap_response, session_id());

        } else {

            // Native authentication.
            $user_data = $model->authenticate($this->post['username'], $this->post['password'], session_id());
        }

        // Feedback info for a user.
        if (isset($user_data['info'])) {

            return $view->main($user_data);
        }

        // Log out old sessions.
        $this->session->deleteSessionFiles($user_data['old_sessions']);

        // Write user data to the session.
        $this->session->data('user_id', $user_data['user_id']);
        $this->session->data('permissions', $user_data['permissions']);
        $this->session->data('remote_ip', $this->server['REMOTE_ADDR'] ?? null);
        $this->session->data('user_agent', $this->server['HTTP_USER_AGENT'] ?? null);

        // Save settings locally.
        $this->app_settings->setUser($user_data['settings']);
        $this->app_settings->setGlobal($user_data['global_settings']);

        return $view->main([]);
    }

    /**
     * Sign out action destroys the session.
     *
     * @return string
     * @throws Exception
     */
    public function signoutAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Must be signed in.
        $this->authorization->signedId(true);

        $model = new AuthenticationModel($this->di);
        $model->signOut(session_id());

        // Destroy the session.
        $this->session->destroy();

        // Send response.
        $view = new DefaultView($this->di);

        return $view->main();
    }
}
