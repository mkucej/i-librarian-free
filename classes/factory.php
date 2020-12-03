<?php

namespace Librarian;

use Librarian\Container\DependencyInjector;

/**
 * Class to encapsulate all class instantiation definitions.
 */
final class Factory {

    /**
     * @var DependencyInjector
     */
    private $di;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     */
    public function __construct(DependencyInjector $di) {

        $this->di = $di;
    }

    /**
     * Class definitions lineup.
     */
    public function assemble(): void {

        // Framework classes.
        $this->framework();

        // Bootstrap HTML.
        $this->bootstrap();

        // SQLite Databases.
        $this->databases();

        // Third-party SDKs.
        $this->libraries();
    }

    /**
     * Framework classes.
     */
    private function framework(): void {

        $this->di->set('Application', function () {

            return new \Librarian\Application($this->di);
        });

        $this->di->set('AppSettings', function () {

            return new \Librarian\AppSettings($this->di);
        });

        $this->di->set('Router', function () {

            $validation = $this->di->getShared('Validation');

            return new \Librarian\Router($this->di, $validation);
        });

        $this->di->set('ServerRequest', function () {

            return \Librarian\Http\Client\Psr7\ServerRequest::fromGlobals();
        });

        $this->di->set('Validation', function () {

            return new \Librarian\Security\Validation();
        });

        $this->di->set('Sanitation', function () {

            return new \Librarian\Security\Sanitation();
        });

        $this->di->set('Response', function () {

            return new \Librarian\Http\Client\Psr7\Response();
        });

        $this->di->set('ResponseStream', function ($resource = null, $options = []) {

            return \Librarian\Http\Client\Psr7\Utils::streamFor($resource, $options);
        });

        $this->di->set('Session', function () {

            $appSettings = $this->di->getShared('AppSettings');
            $encryption  = $this->di->getShared('Encryption');
            $request     = $this->di->getShared('ServerRequest');

            return new \Librarian\Security\Session($appSettings, $encryption, $request);
        });

        $this->di->set('Encryption', function () {

            return new \Librarian\Security\Encryption();
        });

        $this->di->set('Element', function () {

            return new \Librarian\Html\Element();
        });

        $this->di->set('HttpClient', function (array $config = []) {

            return new \Librarian\Http\Client\Client($config);
        });

        $this->di->set('Authorization', function () {

            $session = $this->di->getShared('Session');

            return new \Librarian\Security\Authorization($session);
        });

        $this->di->set('Language', function () {

            return new \Librarian\Media\Language();
        });

        $this->di->set('ErrorView', function () {

            return new \Librarian\ErrorView($this->di);
        });

        $this->di->set('Installation', function () {

            $model = $this->di->getShared('InstallationModel');

            return new \Librarian\Installation($model);
        });

        $this->di->set('InstallationModel', function () {

            return new \Librarian\InstallationModel($this->di);
        });

        $this->di->set('GarbageCollector', function () {

            return new \Librarian\GarbageCollector();
        });

        $this->di->set('ScalarUtils', function () {

            $lang = $this->di->getShared('Language');

            return new \Librarian\Media\ScalarUtils($this->di, $lang);
        });

        $this->di->set('Xml', function () {

            return new \Librarian\Media\Xml();
        });

        $this->di->set('Binary', function () {

            return new \Librarian\Media\Binary($this->di);
        });

        $this->di->set('FileTools', function () {

            return new \Librarian\Media\FileTools();
        });

        $this->di->set('Pdf', function (string $file) {

            return new \Librarian\Media\Pdf($this->di, $file);
        });

        $this->di->set('Url', function () {

            $request = $this->di->getShared('ServerRequest');

            return new \Librarian\Http\Url($request);
        });

        $this->di->set('Ldap', function () {

            $validation = $this->di->getShared('Validation');
            $app_settings = $this->di->getShared('AppSettings');

            return new \Librarian\Security\Ldap($validation, $app_settings);
        });

        $this->di->set('ItemMeta', function () {

            $app_settings = $this->di->get('AppSettings');

            return new \Librarian\ItemMeta($app_settings);
        });

        $this->di->set('Temporal', function () {

            $app_settings = $this->di->get('AppSettings');
            $lang = $this->di->getShared('Language');

            return new \Librarian\Media\Temporal($app_settings, $lang);
        });

        $this->di->set('FileCache', function () {

            return new \Librarian\Cache\FileCache();
        });

        $this->di->set('Queue', function () {

            return new \Librarian\Queue\Queue();
        });

        $this->di->set('Xplore', function ($key) {

            return new \Librarian\External\Xplore($this->di, $key);
        });

        $this->di->set('Arxiv', function () {

            return new \Librarian\External\Arxiv($this->di);
        });

        $this->di->set('Crossref', function ($key) {

            return new \Librarian\External\Crossref($this->di, $key);
        });

        $this->di->set('Nasa', function ($key) {

            return new \Librarian\External\Nasaads($this->di, $key);
        });

        $this->di->set('Patents', function () {

            return new \Librarian\External\Patents($this->di);
        });

        $this->di->set('Pubmed', function ($key = '') {

            return new \Librarian\External\Pubmed($this->di, $key);
        });

        $this->di->set('Pmc', function ($key = '') {

            return new \Librarian\External\Pmc($this->di, $key);
        });

        $this->di->set('Ol', function () {

            return new \Librarian\External\Ol($this->di);
        });

        $this->di->set('Image', function () {

            return new \Librarian\Media\Image();
        });

        $this->di->set('Logger', function () {

            $db_log = $this->di->get('Db_logs');
            return new \Librarian\Logger\Logger($db_log);
        });

        $this->di->set('Reporter', function () {

            $db_log = $this->di->get('Db_logs');
            return new \Librarian\Logger\Reporter($db_log);
        });

        $this->di->set('BibtexImport', function (string $text) {

            $item_meta = $this->di->get('ItemMeta');
            return new \Librarian\Import\Bibtex($item_meta, $text);
        });

        $this->di->set('EndnoteImport', function (string $text) {

            $xml = $this->di->get('Xml');
            $item_meta = $this->di->get('ItemMeta');
            return new \Librarian\Import\Endnote($xml, $item_meta, $text);
        });

        $this->di->set('RisImport', function (string $text) {

            $item_meta = $this->di->get('ItemMeta');
            return new \Librarian\Import\Ris($item_meta, $text);
        });

        $this->di->set('BibtexExport', function () {

            $app_settings = $this->di->get('AppSettings');
            $item_meta = $this->di->get('ItemMeta');
            $sanitation = $this->di->get('Sanitation');
            return new \Librarian\Export\Bibtex($item_meta, $sanitation, $app_settings);
        });

        $this->di->set('EndnoteExport', function () {

            $item_meta = $this->di->get('ItemMeta');
            $sanitation = $this->di->get('Sanitation');
            return new \Librarian\Export\Endnote($item_meta, $sanitation);
        });

        $this->di->set('RisExport', function () {

            $item_meta = $this->di->get('ItemMeta');
            $sanitation = $this->di->get('Sanitation');
            return new \Librarian\Export\Ris($item_meta, $sanitation);
        });

        $this->di->set('TesseractOcr', function () {

            return new \Librarian\Media\TesseractOcr($this->di);
        });
    }

    /**
     * HTML Bootstrap related classes.
     */
    private function bootstrap(): void {

        $this->di->set('Alert', function () {

            return new \Librarian\Html\Bootstrap\Alert();
        });

        $this->di->set('Badge', function () {

            return new \Librarian\Html\Bootstrap\Badge();
        });

        $this->di->set('Breadcrumb', function () {

            return new \Librarian\Html\Bootstrap\Breadcrumb();
        });

        $this->di->set('Button', function () {

            return new \Librarian\Html\Bootstrap\Button();
        });

        $this->di->set('Card', function () {

            return new \Librarian\Html\Bootstrap\Card();
        });

        $this->di->set('Descriptionlist', function () {

            return new \Librarian\Html\Bootstrap\Descriptionlist();
        });

        $this->di->set('Dropdown', function () {

            return new \Librarian\Html\Bootstrap\Dropdown();
        });

        $this->di->set('Form', function () {

            return new \Librarian\Html\Bootstrap\Form();
        });

        $this->di->set('Icon', function () {

            return new \Librarian\Html\Bootstrap\Icon();
        });

        $this->di->set('IconButton', function () {

            return new \Librarian\Html\Bootstrap\IconButton();
        });

        $this->di->set('Input', function () {

            return new \Librarian\Html\Bootstrap\Input();
        });

        $this->di->set('InputButton', function () {

            return new \Librarian\Html\Bootstrap\InputButton();
        });

        $this->di->set('InputGroup', function () {

            return new \Librarian\Html\Bootstrap\Inputgroup();
        });

        $this->di->set('ListGroup', function () {

            return new \Librarian\Html\Bootstrap\ListGroup();
        });

        $this->di->set('Modal', function () {

            $lang = $this->di->getShared('Language');

            return new \Librarian\Html\Bootstrap\Modal($lang);
        });

        $this->di->set('Nav', function () {

            return new \Librarian\Html\Bootstrap\Nav();
        });

        $this->di->set('NavBar', function () {

            return new \Librarian\Html\Bootstrap\Navbar();
        });

        $this->di->set('NavContent', function () {

            return new \Librarian\Html\Bootstrap\NavContent();
        });

        $this->di->set('Pagination', function () {

            return new \Librarian\Html\Bootstrap\Pagination();
        });

        $this->di->set('Pills', function () {

            return new \Librarian\Html\Bootstrap\Pills();
        });

        $this->di->set('ProgressBar', function () {

            return new \Librarian\Html\Bootstrap\ProgressBar();
        });

        $this->di->set('Row', function () {

            return new \Librarian\Html\Bootstrap\Row();
        });

        $this->di->set('Select', function () {

            return new \Librarian\Html\Bootstrap\Select();
        });

        $this->di->set('Sidebar', function () {

            return new \Librarian\Html\Bootstrap\Sidebar();
        });

        $this->di->set('SideMenu', function () {

            return new \Librarian\Html\Bootstrap\SideMenu();
        });

        $this->di->set('Table', function () {

            return new \Librarian\Html\Bootstrap\Table();
        });

        $this->di->set('Textarea', function () {

            return new \Librarian\Html\Bootstrap\Textarea();
        });

        $this->di->set('Typeahead', function () {

            return new \Librarian\Html\Bootstrap\Typeahead();
        });
    }

    /**
     * Databases.
     */
    private function databases(): void {

        // Main database.
        $this->di->set('Db_main', function () {

            return new \Librarian\Storage\Database([
                'dbname'    => IL_DB_PATH . DIRECTORY_SEPARATOR . 'main.db',
                'functions' => [
                    'deaccent'   => [$this->di->getShared('ScalarUtils'), 'deaccent'],
                    'striptags'  => 'strip_tags'
                ],
                'collations' => [
                    'utf8Collation' => [$this->di->getShared('ScalarUtils'), 'utf8Collation']
                ]
            ]);
        });

        // Log database.
        $this->di->set('Db_logs', function () {

            return new \Librarian\Storage\Database([
                'dbname' => IL_DB_PATH . DIRECTORY_SEPARATOR . 'logs.db',
                'collations' => [
                    'utf8Collation' => [$this->di->getShared('ScalarUtils'), 'utf8Collation']
                ]
            ]);
        });

        // Styles.
        $this->di->set('Db_styles', function () {

            return new \Librarian\Storage\Database([
                'dbname'    => IL_DB_PATH . DIRECTORY_SEPARATOR . 'styles.db',
                'functions' => [
                    'deaccent'   => [$this->di->getShared('ScalarUtils'), 'deaccent']
                ],
                'collations' => [
                    'utf8Collation' => [$this->di->getShared('ScalarUtils'), 'utf8Collation']
                ]
            ]);
        });

        // Custom database.
        $this->di->set('Db_custom', function ($options) {

            return new \Librarian\Storage\Database($options);
        });
    }

    /**
     * Third-party SDK libraries.
     */
    private function libraries(): void {

        // HTMLPurifier used to sanitize external HTML input.
        $this->di->set('HtmlPurifier', function () {

            include IL_CLASS_PATH . DIRECTORY_SEPARATOR
                    . 'libraries' . DIRECTORY_SEPARATOR
                    . 'htmlpurifier' . DIRECTORY_SEPARATOR
                    . 'htmlpurifier.php';

            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', IL_TEMP_PATH);
            return new \HTMLPurifier($config);
        });

        // IEEE Xplore SDK.
        $this->di->set('XploreSdk', function ($key) {

            include IL_CLASS_PATH . DIRECTORY_SEPARATOR
                    . 'libraries' . DIRECTORY_SEPARATOR
                    . 'xplore' . DIRECTORY_SEPARATOR
                    . 'xplore-php-sdk.php';

            return new \XPLORE($key);
        });
    }
}
