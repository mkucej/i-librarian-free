<?php

namespace LibrarianApp;

use DateTimeZone;
use Exception;
use Librarian\Mvc\Controller;

/**
 * Class SettingsController
 *
 * User settings.
 */
class SettingsController extends Controller {

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

        // Get locally saved user settings.
        $user_settings = $this->app_settings->getUser();

        // Settings view.
        $view = new SettingsView($this->di);
        return $view->main($user_settings);
    }

    /**
     * Display settings for modal window.
     *
     * @return string
     * @throws Exception
     */
    public function displayAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // Get locally saved user settings.
        $user_settings = $this->app_settings->getUser();

        // Settings view.
        $view = new SettingsView($this->di);
        return $view->displaySettings($user_settings);
    }

    /**
     * Update user settings.
     *
     * @return string
     * @throws Exception
     */
    public function updateAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->signedId(true);

        $this->post['settings'] = $this->sanitation->length($this->post['settings'], 1024);

        // Restrict page_size values (it is a numeric value that could be attacked).
        if (!in_array($this->post['settings']['page_size'], [5, 10, 15, 20, 50, 100])) {

            throw new Exception('invalid value <kbd>Items per page</kbd>', 422);
        }

        // Restrict icons_per_row values (it is a numeric value that could be attacked).
        if (!in_array($this->post['settings']['icons_per_row'], [1, 2, 3, 4, 'auto'])) {

            throw new Exception('invalid value <kbd>Icons per row</kbd>', 422);
        }

        // Remove non-existing settings.
        foreach (array_keys($this->post['settings']) as $key) {

            if (array_key_exists($key, $this->app_settings->getUser()) === false) {

                unset($this->post['settings'][$key]);
            }
        }

        // Default timezone.
        if (isset($this->post['settings']['timezone']) === true) {

            // Verify timezone.
            $timezones = DateTimeZone::listIdentifiers();

            $this->post['settings']['timezone'] = in_array($this->post['settings']['timezone'], $timezones) === false ?
                'UTC' :
                $this->post['settings']['timezone'];
        }

        // Get locally saved user settings and merge new settings.
        $user_settings = $this->app_settings->getUser();
        $new_settings = $this->post['settings'] + $user_settings;

        // Save new settings permanently.
        $model = new SettingsModel($this->di);
        $model->saveUser($new_settings);
        $model = null;

        // Save new settings locally.
        $this->app_settings->setUser($new_settings);

        // Send the view.
        $view = new DefaultView($this->di);

        return $view->main(['info' => 'new settings were saved']);
    }
}
