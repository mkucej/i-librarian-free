<?php

// Debug. Set to 1 to see fatal errors.
ini_set('display_errors', 1);

/*
 * I, Librarian version.
 */
define('IL_VERSION', '5.0.2');
define('IL_DB_VERSION', '50002');

/*
 * Define paths.
 */

// PUBLIC. This file's path.
define('IL_PUBLIC_PATH', __DIR__);

// PRIVATE. Can be the parent folder.
if (is_dir(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app')) {

    define('IL_PRIVATE_PATH', dirname(__DIR__));

} else {

    // Can be defined in paths.php, e.g. /usr/share/i-librarian.
    include_once IL_PUBLIC_PATH . DIRECTORY_SEPARATOR . 'paths.php';
    define('IL_PRIVATE_PATH', $IL_PRIVATE_PATH);
}

// We must have the private path at this point.
if (!is_dir(IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'app')) {

    echo file_get_contents(IL_PUBLIC_PATH . DIRECTORY_SEPARATOR . 'error.html');
    exit;
}

define('IL_APP_PATH', IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'app');
define('IL_CLASS_PATH', IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'classes');

// CONFIG. Can be the sibling folder config.
if (is_dir(IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'config')) {

    define('IL_CONFIG_PATH', IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'config');

} else {

    // Can be defined in paths.php, e.g. /etc/i-librarian.
    include_once IL_PUBLIC_PATH . DIRECTORY_SEPARATOR . 'paths.php';
    define('IL_CONFIG_PATH', $IL_CONFIG_PATH);
}

// We must have the config path at this point.
if (!is_dir(IL_CONFIG_PATH)) {

    echo file_get_contents(IL_PUBLIC_PATH . DIRECTORY_SEPARATOR . 'error.html');
    exit;
}

// DATA. Can be the sibling folder data. Must not be in the server's document root!
if (is_dir(IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'data')) {

    define('IL_DATA_PATH', IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'data');

} else {

    // Can be defined in paths.php, e.g. /var/lib/i-librarian.
    include_once IL_PUBLIC_PATH . DIRECTORY_SEPARATOR . 'paths.php';
    define('IL_DATA_PATH', $IL_DATA_PATH . DIRECTORY_SEPARATOR . 'data');
}

// We must have the data path at this point.
if (!is_dir(IL_DATA_PATH)) {

    echo file_get_contents(IL_PUBLIC_PATH . DIRECTORY_SEPARATOR . 'error.html');
    exit;
}

define('IL_CACHE_PATH', IL_DATA_PATH . DIRECTORY_SEPARATOR . 'cache');
define('IL_DB_PATH', IL_DATA_PATH . DIRECTORY_SEPARATOR . 'database');
define('IL_PDF_PATH', IL_DATA_PATH . DIRECTORY_SEPARATOR . 'pdfs');
define('IL_SUPPLEMENT_PATH', IL_DATA_PATH . DIRECTORY_SEPARATOR . 'supplements');
define('IL_TEMP_PATH', IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'temp');

/*----------------------------------------------------------------------------*/

/*
 * Bootstrap.
 */

use Librarian\Application;
use Librarian\ErrorView;
use Librarian\Http\Url;
use Librarian\Loader;
use Librarian\Container\DependencyInjector;
use Librarian\Factory;

/*
 * Autoloader.
 */
require IL_CLASS_PATH . DIRECTORY_SEPARATOR . 'loader.php';

$loader = new Loader();
$loader->register();
$loader = null;

/*
 * Dependency injector.
 */
$di = new DependencyInjector();

/*
 * Factory. Contains all framework class definitions.
 */
$factory = new Factory($di);
$factory->assemble();
$factory = null;

/*
 * Define this request's base URL.
 */
try {

    /** @var Url $url */
    $url = $di->getShared('Url');

} catch (Exception $e) {

    echo ' Error: ' . strip_tags($e->getMessage()) . '.';
    exit;
}

define('IL_BASE_URL', $url->base());
define('IL_PATH_URL', $url->path());
$url = null;

/*
 * Application.
 */
try {

    /** @var Application $application */
    $application = $di->getShared('Application');
    echo $application->handle();

} catch (Throwable $exc) {

    try {

        /** @var ErrorView $error */
        $error = $di->getShared('ErrorView');
        echo $error->main($exc);

    } catch (Exception $ex) {

        // If ErrorView fails, echo the error as a simple text.
        echo 'Error: ' . strip_tags($exc->getMessage()) . '.';
        echo ' Error: ' . strip_tags($ex->getMessage()) . '.';
    }
}
