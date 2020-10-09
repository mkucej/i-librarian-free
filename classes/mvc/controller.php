<?php

namespace Librarian\Mvc;

use Exception;
use Librarian\AppSettings;
use Librarian\Container\DependencyInjector;
use Librarian\Http\Client\Psr7\ServerRequest;
use Librarian\Http\Client\Psr7\UploadedFile;
use Librarian\Media\Language;
use Librarian\Security\Authorization;
use Librarian\Security\Sanitation;
use Librarian\Security\Session;
use Librarian\Security\Validation;
use LibrarianApp\SettingsModel;

abstract class Controller {

    /**
     * @var DependencyInjector
     */
    protected $di;

    /**
     * @var AppSettings
     */
    protected $app_settings;

    /**
     * @var Authorization
     */
    protected $authorization;

    /**
     * @var Language
     */
    protected $lang;

    /**
     * @var ServerRequest
     */
    protected $request;

    /**
     * @var Sanitation
     */
    protected $sanitation;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Validation
     */
    protected $validation;

    /**
     * GET globals.
     * @var array
     */
    protected $get;

    /**
     * POST globals.
     * @var array
     */
    protected $post;

    /**
     * FILES globals.
     * @var array
     */
    protected $files;

    /**
     * SERVER globals.
     * @var array
     */
    protected $server;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        $this->di            = $di;
        $this->app_settings  = $this->di->getShared('AppSettings');
        $this->authorization = $this->di->getShared('Authorization');
        $this->lang          = $this->di->getShared('Language');
        $this->request       = $this->di->getShared('ServerRequest');
        $this->sanitation    = $this->di->getShared('Sanitation');
        $this->session       = $this->di->getShared('Session');
        $this->validation    = $this->di->getShared('Validation');

        $this->loadGlobals();
        $this->globalSettings();

        // Session is required for all requests.
        $this->session->start();

        // Set locale. Must have php-intl, the client header, and user must allow custom locales.
        $language = 'en_US';

        if (extension_loaded('intl') === true && isset($this->server['HTTP_ACCEPT_LANGUAGE']) === true) {

            $language = locale_accept_from_http($this->server['HTTP_ACCEPT_LANGUAGE']);
        }

        if ($this->session->data('user_id') !== null && $this->app_settings->getUser('use_en_language') === '1') {

            $language = 'en_US';
        }

        // Debug.
//        $language = 'de';

        $this->lang->setLanguage($language);

        // All POST requests must contain CSRF token.
        if ($this->request->getMethod() === 'POST') {

            if (empty($this->post['csrfToken'])) {

                throw new Exception('missing CSRF token in POST request', 400);
            }

            if ($this->session->data('token') !== $this->post['csrfToken']) {

                throw new Exception('session has expired, please reload', 401);
            }
        }
    }

    /**
     * Load globals into their arrays.
     *
     * @throws Exception
     */
    private function loadGlobals(): void {

        $get = $this->request->getQueryParams();
        $get_strip = $this->sanitation->stripLow($get);
        $get_trim = $this->sanitation->trim($get_strip);
        $this->get = $this->sanitation->length($get_trim);

        $post = $this->request->getParsedBody();
        $post_strip = $this->sanitation->stripLow($post);
        $post_trim = $this->sanitation->trim($post_strip);
        $this->post = $this->sanitation->length($post_trim);

        $this->files = $this->request->getUploadedFiles();

        $this->server = $this->request->getServerParams();
    }

    /**
     * Load global settings from the model, if required.
     *
     * @throws Exception
     */
    private function globalSettings(): void {

        if (is_readable(IL_TEMP_PATH . DIRECTORY_SEPARATOR . 'settings.json') === false) {

            // Load global settings.
            $settings_model = new SettingsModel($this->di);
            $global_settings = $settings_model->loadGlobal();

            $this->app_settings->setGlobal($global_settings);
        }
    }

    /**
     * Get uploaded file object. Check errors.
     *
     * @param string $name
     * @return UploadedFile|null
     * @throws Exception
     */
    protected function getUploadedFile(string $name) {

        if (isset($this->files[$name]) === false || $this->files[$name]->getSize() === 0) {

            return null;
        }

        $escaped_name = $this->sanitation->html($this->files[$name]->getClientFilename());

        // Errors.
        switch ($this->files[$name]->getError()) {

            case 1:
                $limit = ini_get('upload_max_filesize');
                throw new Exception("file $escaped_name exceeds the upload_max_filesize setting of {$limit}B", 400);

            case 3:
                throw new Exception("file $escaped_name was only partially uploaded", 500);

            case 4:
                throw new Exception("file $escaped_name was not uploaded", 500);

            case 6:
                throw new Exception("missing the server temporary folder", 500);

            case 7:
                throw new Exception("failed to write file $escaped_name to disk", 500);
        }

        return $this->files[$name];
    }

    /**
     * Every controller must implement mainAction() method.
     */
    abstract function  mainAction();
}
