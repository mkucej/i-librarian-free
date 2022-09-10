<?php

namespace Librarian\Mvc;

use Exception;
use Librarian\AppSettings;
use Librarian\Container\DependencyInjector;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use Librarian\Media\Language;
use Librarian\Security\Sanitation;
use Librarian\Security\Validation;
use LibrarianApp\SettingsModel;

abstract class Controller {

    /**
     * @var DependencyInjector
     */
    protected DependencyInjector $di;

    /**
     * @var AppSettings
     */
    protected AppSettings $app_settings;

    /**
     * @var Language
     */
    protected Language $lang;

    /**
     * @var ServerRequest
     */
    protected ServerRequest $request;

    /**
     * @var Sanitation
     */
    protected Sanitation $sanitation;

    /**
     * @var Validation
     */
    protected Validation $validation;

    /**
     * GET globals.
     * @var array
     */
    protected array $get;

    /**
     * POST globals.
     * @var array
     */
    protected array $post;

    /**
     * FILES globals.
     * @var array
     */
    protected array $files;

    /**
     * SERVER globals.
     * @var array
     */
    protected array $server;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        $this->di            = $di;
        $this->app_settings  = $this->di->getShared('AppSettings');
        $this->lang          = $this->di->getShared('Language');
        $this->request       = $this->di->getShared('ServerRequest');
        $this->sanitation    = $this->di->getShared('Sanitation');
        $this->validation    = $this->di->getShared('Validation');

        $this->loadGlobals();
        $this->globalSettings();
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
