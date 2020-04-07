<?php

namespace LibrarianApp;

use Exception;
use Librarian\Mvc\Controller;

/**
 * Class GlobalSettingsController
 *
 * Admin settings.
 */
class GlobalSettingsController extends Controller {

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

        // Get global settings.
        $settings = $this->app_settings->getGlobal();

        // Settings view.
        $view = new SettingsView($this->di);

        return $view->global($settings);
    }

    /**
     * Update global settings.
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
        $this->authorization->permissions('A');

        $this->post['settings'] = $this->sanitation->length($this->post['settings'], 1024);

        // Remove non-existing settings.
        foreach (array_keys($this->post['settings']) as $key) {

            if (array_key_exists($key, $this->app_settings->getGlobal()) === false) {

                unset($this->post['settings'][$key]);
            }
        }

        // Save new settings permanently.
        $model = new SettingsModel($this->di);
        $model->saveGlobal($this->post['settings']);

        // Save new settings locally.
        $this->app_settings->setGlobal($this->post['settings']);

        // Reset Bibtex Ids for all items.
        if (isset($this->post['replace_bibtex_keys']) && $this->post['replace_bibtex_keys'] === '1') {

            $model = new ItemsModel($this->di);
            $model->resetBibtexIds();
            $model = null;
        }

        // Send the view.
        $view = new DefaultView($this->di);

        return $view->main(['info' => 'New settings were saved.']);
    }
}
