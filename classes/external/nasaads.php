<?php

namespace Librarian\External;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Http\Client\Client;
use Librarian\Http\Client\Exception\GuzzleException;
use Librarian\Http\Client\Utils;
use Librarian\ItemMeta;
use const JSON_OBJECT_AS_ARRAY;

/**
 * NASA ADS API connector.
 */
class Nasaads extends ExternalDatabase implements ExternalDatabaseInterface {

    /**
     * @var Client
     */
    private $client;

    /**
     * @var array Return fields.
     */
    private $fields = [
        'abstract',
        'aff',
        'author',
        'bibcode',
        'doi',
        'doctype',
        'esources',
        'issue',
        'keyword',
        'page_range',
        'pub',
        'pubdate',
        'title',
        'volume'
    ];

    private $url_search = 'https://api.adsabs.harvard.edu/v1/search/query';
//    private $url_fetch  = 'https://api.adsabs.harvard.edu/v1/export/refabsxml';

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @param string $key
     * @throws Exception
     */
    public function __construct(DependencyInjector $di, string $key) {

        parent::__construct($di);

        // Acquire green light.
        $this->queue->lane('nasa');
        $this->queue->wait();

        // Get current request count limit from SHM.
        $count = $this->queue->count();
        $max_count = $this->queue->maxCount();

        // Prevent request, if limit reached.
        if ($count + 1 === $max_count) {

            throw new Exception('maximum number of queries to NASA reached, try again later');
        }

        // Instantiate Client.
        $this->client = $this->di->get('HttpClient', [
            [
                'timeout' => 30,
                'curl'    => [
                    CURLOPT_PROXY        => $this->app_settings->proxyUrl(),
                    CURLOPT_PROXYUSERPWD => $this->app_settings->proxyUserPwd(),
                    CURLOPT_PROXYAUTH    => $this->app_settings->proxyAuthType()
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $key
                ]
            ]
        ]);
    }

    /**
     * Fetch NASA record from a bibcode.
     *
     * @param string $uid
     * @return array
     * @throws Exception|GuzzleException
     */
    public function fetch(string $uid): array {

        $query = [
            'q'    => 'bibcode:' . $uid,
            'fl'   => join(',', $this->fields),
            'rows' => 1
        ];

        // Send request to API endpoint.
        $response = $this->client->get($this->url_search . '?' . http_build_query($query));

        // Get limits and save them to SHM.
        $limit_total = (integer) $response->getHeaderLine('X-RateLimit-Limit');
        $limit_remaining = (integer) $response->getHeaderLine('X-RateLimit-Remaining');
        $limit_reset = (integer) $response->getHeaderLine('X-RateLimit-Reset');

        // Save counts to queue.
        $this->queue->count($limit_total - $limit_remaining);
        $this->queue->maxCount($limit_total);

        // No more requests allowed.
        if ((integer) $limit_remaining === 1) {

            $hours_remaining = ceil(($limit_reset - time()) / 3600);
            throw new Exception("maximum number of queries to NASA reached, try again in {$hours_remaining}h");
        }

        $json_str = $response->getBody()->getContents();

        return $this->formatMetadata($json_str);
    }

    /**
     * Fetch multiple records based on an array of IDs.
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
     * Search NASA API.
     *
     * @param array $terms
     * @param int $start
     * @param int $rows
     * @param array|null $filters
     * @param string|null $sort
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    public function search(
        array  $terms,
        int    $start,
        int    $rows    = 10,
        array  $filters = null,
        string $sort    = null
    ): array {

        $queries = [];
        $search_name = '';
        // Max rows to fetch (100) can be overridden with $rows.
        $maximum_rows = max(100, $rows);

        $allowed_params = [
            'abs'     => 'Title + abstract',
            'author'  => 'Author',
            'bibcode' => 'Bibcode',
            'full'    => 'Full text',
            'doi'     => 'DOI',
            'object'  => 'Object',
            'title'   => 'Title',
            'year'    => 'Year'
        ];

        foreach ($terms as $term) {

            $name  = key($term);
            $value = current($term);

            // Boolean search overwrites everything.
            if ($name === 'boolean' && !empty($value)) {

                $queries = [$value];
                $search_name = "{$value} ";
                break;
            }

            $queries[] = "{$name}: {$value}";
            $search_name .= "{$allowed_params[$name]}: $value ";
        }

        // Add filters. Only last submitted filter.
        if (!empty($filters)) {

            foreach ($filters as $filter) {

                if (key($filter) === 'last_added') {

                    $days = current($filter);

                    if ($days < 1 || $days > 365) {

                        continue;
                    }

                    $from = date('Y-m-d', time() - $days * 86400);
                    $now = date('Y-m-d', time() - 86400);

                    $queries[] = "entdate:[{$from} TO {$now}]";
                    $plural = $days === '1' ? '' : 's';
                    $search_name .= "\u{2022} last {$days} day{$plural} ";
                }
            }
        }

        $query = join(' ', $queries);

        $params = [
            'q'     => $query,
            'start' => $start,
            'fq'    => $filters,
            'rows'  => $maximum_rows,
            'fl'    => join(',', $this->fields),
            'sort'  => $sort
        ];

        // Total rows to fetch per search. This does not equal the I, Librarian page size.
        $params['start'] = floor($start / $maximum_rows) * $maximum_rows;

        // Try to get records from Cache.
        $this->cache->context('searches');

        $key = $this->cache->key(
            __METHOD__
            . serialize($params)
        );

        // Debug.
//        $this->cache->delete($key);

        // Get items from cache.
        $items = $this->cache->get($key);

        if (empty($items)) {

            // Send request to API endpoint.
            $response = $this->client->get($this->url_search . '?' . http_build_query($params));

            // Get limits and save them to SHM.
            $limit_total = (integer) $response->getHeaderLine('X-RateLimit-Limit');
            $limit_remaining = (integer) $response->getHeaderLine('X-RateLimit-Remaining');
            $limit_reset = (integer) $response->getHeaderLine('X-RateLimit-Reset');

            // Save counts to queue.
            $this->queue->count($limit_total - $limit_remaining);
            $this->queue->maxCount($limit_total);

            // No more requests allowed.
            if ((integer) $limit_remaining === 1) {

                $hours_remaining = ceil(($limit_reset - time()) / 3600);
                throw new Exception("maximum number of queries to NASA reached, try again in {$hours_remaining}h");
            }

            $json_str = $response->getBody()->getContents();

            $items = $this->formatMetadata($json_str);

            // Hold in Cache for 24h.
            $this->cache->set($key, $items, 86400);
        }

        // Paging.
        $slice_start = ($start % $rows) - 1 === 0 ? ($start % $maximum_rows) - 1 : 0;
        $items['items'] = array_slice($items['items'], $slice_start, $rows);

        // Add search name.
        $sort = empty($sort) ? 'relevance' : $sort;
        $items['search_name'] = $search_name . " • sort: {$sort}";

        return $items;
    }

    /**
     * Convert NASA XML input to metadata array.
     *
     * @param string $input JSON
     * @return array
     * @throws Exception
     */
    public function formatMetadata($input): array {

        $output = [
            'found' => 0,
            'items' => []
        ];

        // Response is JSON. Convert to array.
        $json = Utils::jsonDecode($input, JSON_OBJECT_AS_ARRAY);
        $output['found'] =  $json['response']['numFound'] ?? 0;
        $docs = $json['response']['docs'] ?? [];

        $i = 0;

        foreach ($docs as $article) {

            // Type.
            switch ($article['doctype']) {

                case 'article':
                case 'eprint':
                case 'bookreview':
                case 'circular':
                case 'erratum':
                case 'newsletter':
                case 'obituary':
                case 'pressrelease':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['ARTICLE'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['ARTICLE'];
                    break;

                case 'inproceedings':
                case 'abstract':
                case 'proceedings':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['CONFERENCE'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['CONFERENCE'];
                    break;

                case 'inbook':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['CHAPTER'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['INCOLLECTION'];
                    break;

                case 'book':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['BOOK'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['BOOK'];
                    break;

                case 'mastersthesis':
                case 'phdthesis':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['THESIS'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['PHDTHESIS'];
                    break;

                case 'techreport':
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['REPORT'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['TECHREPORT'];
                    break;

                default:
                    $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['GENERIC'];
                    $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['MISC'];
            }

            // Title.
            $output['items'][$i][ItemMeta::COLUMN['TITLE']] = $article['title'][0] ?? null;

            // DOI.
            if (!empty($article['doi'][0])) {

                $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'DOI';
                $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $article['doi'][0];
            }

            // Abstract.
            $output['items'][$i][ItemMeta::COLUMN['ABSTRACT']] = $article['abstract'] ?? '';

            // UIDS.
            $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'NASAADS';
            $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $article['bibcode'];

            // URLS.
            $output['items'][$i][ItemMeta::COLUMN['URLS']][0] = 'https://ui.adsabs.harvard.edu/abs/' . $article['bibcode'];

            if (!empty($article['esources'])) {

                $pdf_key = array_keys($article['esources'], 'PUB_PDF');
                $pdf_key2 = array_keys($article['esources'], 'ADS_PDF');
                $eprint_key = array_keys($article['esources'], 'EPRINT_PDF');

                if (!empty($pdf_key)) {

                    $output['items'][$i][ItemMeta::COLUMN['URLS']][1] = "https://ui.adsabs.harvard.edu/link_gateway/{$article['bibcode']}/PUB_PDF";

                } elseif (!empty($pdf_key2)) {

                    $output['items'][$i][ItemMeta::COLUMN['URLS']][1] = "https://ui.adsabs.harvard.edu/link_gateway/{$article['bibcode']}/ADS_PDF";

                } elseif (!empty($eprint_key)) {

                    $output['items'][$i][ItemMeta::COLUMN['URLS']][1] = "https://ui.adsabs.harvard.edu/link_gateway/{$article['bibcode']}/EPRINT_PDF";
                }
            }

            // Publication.
            $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = $article['pub'] ?? '';
            $output['items'][$i][ItemMeta::COLUMN['VOLUME']] = $article['volume'] ?? '';
            $output['items'][$i][ItemMeta::COLUMN['ISSUE']] = $article['issue'] ?? '';
            $output['items'][$i][ItemMeta::COLUMN['PAGES']] = $article['page_range'] ?? '';

            // Date.
            $date = $article['pubdate'] ?? '';
            $output['items'][$i][ItemMeta::COLUMN['PUBLICATION_DATE']] = empty($date) ? '' : str_replace('-00', '-01', $date);

            // Authors.
            if (!empty($article['author'])) {

                foreach ($article['author'] as $author) {

                    $parts = explode(',', $author);
                    $output['items'][$i][ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = $parts[0];
                    $output['items'][$i][ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = $parts[1] ?? '';
                }
            }

            // Affiliations.
            if (!empty($article['aff'])) {

                $affiliations = [];
                $article['aff'] = array_unique($article['aff']);

                foreach ($article['aff'] as $aff) {

                    // Some weird short aff fields must be filtered out.
                    if (strlen($aff) > 3) {

                        $affiliations[] = $aff;
                    }
                }

                $output['items'][$i][ItemMeta::COLUMN['AFFILIATION']] = join('; ', $affiliations);
            }

            // Keywords.
            if (!empty($article['keyword'])) {

                foreach ($article['keyword'] as $kw) {

                    $output['items'][$i][ItemMeta::COLUMN['KEYWORDS']][] = $kw;
                }
            }

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
