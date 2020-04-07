<?php

namespace Librarian;

use ErrorException;
use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Http\Client\Psr7\ServerRequest;

/**
 * Class Application.
 *
 * Bootstraps application level tasks and then dispatches the controller.
 */
final class Application {

    /**
     * @var DependencyInjector
     */
    private $di;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     */
    function __construct(DependencyInjector $di) {

        $this->di = $di;
    }

    /**
     * Application handler.
     *
     * @return string
     * @throws Exception
     */
    public function handle(): string {

        // Convert notices and warnings to errors.
        set_error_handler([$this, 'errorHandler']);

        // IMPORTANT! Check that the data and config folders are not in the server's document root.

        /** @var ServerRequest $request */
        $request = $this->di->getShared('ServerRequest');
        $server  = $request->getServerParams();
        $request = null;

        if (strpos(IL_DATA_PATH, $server['DOCUMENT_ROOT']) === 0 ||
            isset($server['CONTEXT_DOCUMENT_ROOT']) && strpos(IL_DATA_PATH, $server['CONTEXT_DOCUMENT_ROOT']) === 0) {

            throw new Exception('<b>INSECURE</b> installation -- the data folder is in the server\'s document root');
        }

        if (strpos(IL_CONFIG_PATH, $server['DOCUMENT_ROOT']) === 0 ||
            isset($server['CONTEXT_DOCUMENT_ROOT']) && strpos(IL_CONFIG_PATH, $server['CONTEXT_DOCUMENT_ROOT']) === 0) {

            throw new Exception('<b>INSECURE</b> installation -- the config folder is in the server\'s document root');
        }

        $server = null;

        // Load ini settings.

        /** @var AppSettings $app_settings */
        $app_settings = $this->di->getShared('AppSettings');
        $app_settings->loadIni();
        $app_settings = null;

        // Garbage collection; 1 out of 100 requests.

        if (rand(1, 100) === 50) {

            /** @var GarbageCollector $gc */
            $gc = $this->di->getShared('GarbageCollector');
            $gc->cleanGarbage();
            $gc = null;
        }

        // Convert URL request to route parts.

        /** @var Router $router Route parser. */
        $router = $this->di->getShared('Router');
        $router->parse();

        // Special tasks INSTALL and UPGRADE, before the MainController::mainAction() is dispatched.
        if ($router->route['controller'] === 'main' && $router->route['action'] === 'main') {

            /** @var Installation $installation */
            $installation = $this->di->getShared('Installation');

            // Installation. Create the DB and data folders.
            $installation->install();

            // Upgrade, if required.
            $installation->upgrade();

            $installation = null;
        }

        // Dispatch the controller.
        return $router->dispatch();
    }

    /**
     * Convert notices and warnings to error exceptions.
     *
     * @param integer $e_no
     * @param string  $e_str
     * @param string  $e_file
     * @param integer $e_line
     * @throws ErrorException
     */
    public function errorHandler($e_no, $e_str, $e_file, $e_line) {

        throw new ErrorException($e_str, 500, $e_no, $e_file, $e_line);
    }
}
