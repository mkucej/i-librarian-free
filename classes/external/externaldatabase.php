<?php

namespace Librarian\External;

use Exception;
use Librarian\AppSettings;
use Librarian\Cache\FileCache;
use Librarian\Container\DependencyInjector;
use Librarian\ItemMeta;
use Librarian\Queue\Queue;
use Librarian\Security\Sanitation;

abstract class ExternalDatabase implements ExternalDatabaseInterface {

    /**
     * @var AppSettings
     */
    protected $app_settings;

    /**
     * @var FileCache
     */
    protected $cache;

    /**
     * @var DependencyInjector
     */
    protected $di;

    /**
     * @var ItemMeta
     */
    protected $item_meta;

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var Sanitation
     */
    protected $sanitation;

    /**
     * Constructor. Inject required classes.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        $this->app_settings = $di->getShared('AppSettings');
        $this->cache        = $di->getShared('FileCache');
        $this->di           = $di;
        $this->item_meta    = $di->getShared('ItemMeta');
        $this->queue        = $di->getShared('Queue');
        $this->sanitation   = $di->getShared('Sanitation');
    }

    /**
     * Fetch single record based on an ID.
     *
     * @param  string $uid
     * @return array
     */
    abstract function fetch(string $uid): array;

    /**
     * Fetch multiple records based on an array of IDs.
     *
     * @param  array $uids
     * @return array
     */
    abstract function fetchMultiple(array $uids): array;

    /**
     * Search database and return an array of records.
     *
     * @param  array  $terms   Search terms [name => term].
     * @param  int    $start   Starting record for this page.
     * @param  int    $rows    Optional number of records per page.
     * @param  array  $filters Optional array of filters [name => value].
     * @param  string $sort    Optional sorting string.
     * @return array
     */
    abstract function search(
        array  $terms,
        int    $start,
        int    $rows    = 10,
        array  $filters = null,
        string $sort    = null
    ): array;

    /**
     * Convert API response to standard metadata.
     *
     * @param array|string $input JSON|XML
     * @return array
     */
    abstract function formatMetadata($input): array;
}
