<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

final class MigrationController extends Controller {

    /**
     * MigrationController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();

        // Must be signed out.
        $this->authorization->signedId(false);
    }

    /**
     * Main. Migration form.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $model = new MainModel($this->di);
        $num_users = (integer) $model->numUsers();

        if ($num_users > 0) {

            throw new Exception('cannot upgrade, because the database already contains data');
        }

        $view = new MigrationView($this->di);

        return $view->main();
    }

    /**
     * Upgrade.
     *
     * @return string
     * @throws Exception
     */
    public function legacyupgradeAction(): string {

        $model = new MainModel($this->di);
        $num_users = (integer) $model->numUsers();

        if ($num_users > 0) {

            throw new Exception('cannot upgrade, because the database already contains data');
        }

        // Requires library location.
        if (empty($this->get['directory'])) {

            throw new Exception('missing library location', 400);
        }

        // Normalize the dirname.
        if (substr($this->get['directory'], -1) === DIRECTORY_SEPARATOR) {

            $this->get['directory'] = substr($this->get['directory'], 0, -1);
        }

        // Upgrade.
        $model = new MigrationModel($this->di);
        $response = $model->legacyupgrade($this->get['directory']);

        $view = new DefaultView($this->di);
        return $view->main($response);
    }
}
