<?php

namespace Librarian\External;

use Exception;
use Librarian\Container\DependencyInjector;
use GuzzleHttp\Client;
use Librarian\ItemMeta;
use Librarian\Media\ScalarUtils;

final class Xplore extends ExternalDatabase implements ExternalDatabaseInterface {

    /**
     * @var Client
     */
    private $client;

    /**
     * @var ScalarUtils
     */
    private $scalar_utils;

    /**
     * @var \XPLORE
     */
    private $xplore;

    public function __construct(DependencyInjector $di, string $api_key) {

        parent::__construct($di);

        $this->xplore = $di->getShared('XploreSdk', $api_key);

        $this->client = $this->di->get('HttpClient', [
            [
                'timeout' => 30,
                'curl'    => [
                    CURLOPT_PROXY        => $this->app_settings->proxyUrl(),
                    CURLOPT_PROXYUSERPWD => $this->app_settings->proxyUserPwd(),
                    CURLOPT_PROXYAUTH    => $this->app_settings->proxyAuthType()
                ]
            ]
        ]);
    }

    /**
     * Fetch single record based on an ID.
     *
     * @param string $uid
     * @return array
     * @throws Exception
     */
    public function fetch(string $uid): array {

        // DOI vs IEEE ID.
        $this->scalar_utils = $this->di->getShared('ScalarUtils');

        if ($this->scalar_utils->isDoi($uid) === true) {

            return $this->search([
                ['doi' => $uid]
            ], 1);

        } else {

            return $this->search([
                ['article_number' => $uid]
            ], 1);
        }
    }

    /**
     * Fetch multiple records based on an array of IDs. Not supported by Xplore API.
     *
     * @param array $uids
     * @return array
     * @throws Exception
     * @deprecated
     */
    public function fetchMultiple(array $uids): array {

        return [];
    }

    /**
     * Search database and return an array of records.
     *
     * @param array $terms Search terms [name => term].
     * @param int $start Starting record for this page.
     * @param int $rows Optional number of records per I, Librarian page.
     * @param array $filters Optional array of filters [name => value].
     * @param string $sort Optional sorting string.
     * @return array
     * @throws Exception
     */
    public function search(
        array  $terms,
        int    $start,
        int    $rows = 10,
        array  $filters = null,
        string $sort = null
    ): array {

        $search_name = '';

        // Add search terms.
        foreach ($terms as $term) {

            $name  = key($term);
            $value = current($term);

            $this->xplore->searchField($name, $value);

            $search_name .= "{$name}: {$value} ";
        }

        // Add filters.
        if (!empty($filters)) {

            foreach ($filters as $filter) {

                $name  = key($filter);
                $value = current($filter);

                $this->xplore->resultsFilter($name, $value);

                $search_name .= "{$name}: {$value} ";
            }
        }

        // Total rows to fetch per search. This does not equal the I, Librarian page size.
        $maximum_rows = 100;
        $sdk_start = floor($start / $maximum_rows) * $maximum_rows;

        $this->xplore->startingResult($sdk_start);

        // Fetch 100 records per search.
        $this->xplore->maximumResults($maximum_rows);

        // Available sorting is useless. :-(
        $this->xplore->resultsSorting('article_title', 'asc');

        // Get results as JSON.
        $this->xplore->dataType('json');

        // Return results as array.
        $this->xplore->dataFormat('array');

        // Try to get records from Cache.
        $this->cache->context('searches');
//        $this->cache->clear();

        $key = $this->cache->key(
            __METHOD__
            . serialize($terms)
            . serialize($sdk_start)
            . serialize($filters)
            . serialize($sort)
        );

        // Get items from cache.
        $items = $this->cache->get($key);

        if (empty($items)) {

            // Get results from Xplore.
            $result = $this->xplore->callAPI($this->client);

            if (is_array($result) === false) {

                throw new Exception('Xplore API search did not work. Please try again later.');
            }

            $items = $this->formatMetadata($result);

            // Hold in Cache for 24h.
            $this->cache->set($key, $items, 86400);
        }

        // Paging.
        $slice_start = ($start % $rows) - 1 === 0 ? ($start % $maximum_rows) - 1 : 0;
        $items['items'] = array_slice($items['items'], $slice_start, $rows);

        // Add search name.
        $items['search_name'] = $search_name;

        return $items;
    }

    /**
     * Format metadata so that it is ready to be saved by the item model.
     *
     * @param  array|string $input
     * @return array
     */
    public function formatMetadata($input): array {

        $output = [
            'found' => 0,
            'items' => []
        ];

        // Found.
        $output['found'] = $input['total_records'] ?? 0;

        // Articles.
        $articles = $input['articles'] ?? [];
        $i = 0;

        foreach ($articles as $article) {

            $output['items'][$i][ItemMeta::COLUMN['TITLE']] = $article['title'] ?? null;
            $output['items'][$i][ItemMeta::COLUMN['PUBLISHER']] = $article['publisher'] ?? null;
            $output['items'][$i][ItemMeta::COLUMN['ABSTRACT']] = $article['abstract'] ?? null;
            $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'IEEE';
            $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $article['article_number'];
            $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = $article['publication_title'] ?? null;
            $output['items'][$i][ItemMeta::COLUMN['ISSUE']] = $article['is_number'] ?? null;
            $output['items'][$i][ItemMeta::COLUMN['URLS']][] = $article['abstract_url'] ?? null;
            // PDF can't be downloaded.
//            $output['items'][$i][ItemMeta::COLUMN['URLS']][] = $article['pdf_url'] ?? null;

            if (!empty($article['doi'])) {

                $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'DOI';
                $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $article['doi'];
            }

            // Authors & affiliations.
            $authors = $article['authors']['authors'] ?? [];
            $affiliations = [];

            foreach ($authors as $author) {

                $parts = explode(' ', $author['full_name']);
                $output['items'][$i][ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = array_pop($parts);
                $output['items'][$i][ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = join(' ', $parts);

                $affiliations[] = $author['affiliation'] ?? null;
            }

            $output['items'][$i][ItemMeta::COLUMN['AFFILIATION']] = join(', ', array_unique($affiliations));

            // Reference type.
            switch ($article['content_type']) {

                case 'Conferences':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['CONFERENCE'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['INPROCEEDINGS'];
                    break;

                case 'Early Access':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['ELECTRONIC'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['ELECTRONIC'];
                    break;

                case 'Standards':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['STANDARD'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['STANDARD'];
                    break;

                case 'Books':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['CHAPTER'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['INCOLLECTION'];
                    break;

                case 'Courses':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['GENERIC'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['MISC'];
                    break;

                default:
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['ARTICLE'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['ARTICLE'];
                    break;
            }

            // Pub date.
            $output['items'][$i][ItemMeta::COLUMN['PUBLICATION_DATE']] = !empty($article['publication_year']) ?
                    $article['publication_year'] . '-01-01' : null;

            // Pages.
            $output['items'][$i][ItemMeta::COLUMN['PAGES']] = $article['start_page'] ?? null;

            if (!empty($article['start_page']) && !empty($article['end_page'])) {

                $output['items'][$i][ItemMeta::COLUMN['PAGES']] .= '-' . $article['end_page'];
            }

            // Keywords.
            $keywords = $article['index_terms']['ieee_terms']['terms'] ?? [];
            $output['items'][$i][ItemMeta::COLUMN['KEYWORDS']] = $keywords;

            // No title, skip.
            if (empty($output['items'][$i][ItemMeta::COLUMN['TITLE']])) {

                unset($output['items'][$i]);
                continue;
            }

            $i++;
        }

        return $output;
    }
}
