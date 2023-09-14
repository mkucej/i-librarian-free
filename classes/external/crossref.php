<?php

namespace Librarian\External;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Librarian\ItemMeta;
use Librarian\Container\DependencyInjector;

final class Crossref extends ExternalDatabase implements ExternalDatabaseInterface {

    /**
     * @var string API key is mail address.
     */
    private string $api_key;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var string API URL.
     */
    private string $url;

    private int $tries = 0;

    /**
     * Arxiv constructor.
     *
     * @param DependencyInjector $di
     * @param string $api_key
     * @throws Exception
     */
    public function __construct(DependencyInjector $di, string $api_key) {

        parent::__construct($di);

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

        $this->url = 'https://api.crossref.org/works';
        $this->api_key = $api_key;
    }

    /**
     * Fetch single record based on an ID.
     *
     * @param string $doi
     * @return array
     * @throws Exception|GuzzleException
     */
    public function fetch(string $doi): array {

        return $this->fetchMultiple([$doi]);
    }

    /**
     * Fetch multiple records based on an array of IDs. Not implemented.
     *
     * @param array $dois
     * @return array
     * @throws Exception|GuzzleException
     */
    public function fetchMultiple(array $dois): array {

        foreach ($dois as $key => $doi) {

            $dois[$key] = "doi:$doi";
        }

        $doi_filter = join(',', $dois);

        try {

            $this->queue->wait('crossref');
            $response = $this->client->get($this->url . '?filter=' . $this->sanitation->urlquery($doi_filter) . '&mailto=' . $this->sanitation->urlquery($this->api_key));
            $this->queue->release('crossref');

            $json = $response->getBody()->getContents();

            return $this->formatMetadata($json);

        } catch (BadResponseException $e) {

            if ($e->getCode() === 404) {

                return [
                    'found' => 0,
                    'items' => []
                ];

            } elseif ($e->getCode() === 429) {

                if ($this->tries < 3) {

                    sleep(1);
                    $this->tries++;
                    return $this->fetchMultiple($dois);

                } else {

                    $this->queue->release('crossref');
                    throw new Exception('Crossref server is busy, try again later');
                }

            } else {

                $this->queue->release('crossref');
                throw new Exception('Crossref server error');
            }
        }
    }

    /**
     * Search database and return an array of records.
     *
     * @param array $terms Search terms [[name => term]].
     * @param int $start Starting record for this page.
     * @param int $rows Optional number of records per I, Librarian page.
     * @param array|null $filters Optional array of filters [[name => value]].
     * @param string|null $sort Optional sorting string.
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    public function search(
        array  $terms,
        int    $start,
        int    $rows = 10,
        array  $filters = null,
        string $sort = null
    ): array {

        $maximum_rows = 100;

        $params = [
            'offset' => 0,
            'rows'   => $maximum_rows,
            'sort'   => 'relevance',
            'order'  => 'desc'
        ];

        // Add search terms.
        $search_name = '';

        foreach ($terms as $term) {

            $name  = key($term);
            $value = current($term);

            $params['query.' . $name] = $value;
            $search_name .= "$name: $value ";

            // DOI search is special.
            if ($name === 'doi') {

                return $this->fetch($value);
            }
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

                    $params['filter'] = "from-created-date:$from";
                    $plural = $days === '1' ? '' : 's';
                    $search_name .= "\u{2022} last $days day$plural ";
                }
            }
        }

        // Add sorting.
        switch ($sort) {

            case 'relevance':
                $params['sort'] = 'relevance';
                break;

            case 'published':
                $params['sort'] = 'published';
                break;

            case 'updated':
                $params['sort'] = 'updated';
                break;

            case 'is-referenced-by-count':
                $params['sort'] = 'is-referenced-by-count';
                break;
        }

        // Total rows to fetch per search. This does not equal the I, Librarian page size.
        $params['offset'] = floor($start / $maximum_rows) * $maximum_rows;

        // Try to get records from Cache.
        $this->cache->context('searches');

        $key = $this->cache->key(
            __METHOD__
            . serialize($params)
        );

        // Get items from cache.
        $items = $this->cache->get($key);

        if (empty($items)) {

            try {

                $this->queue->wait('crossref');
                $response = $this->client->get($this->url . '?mailto=' . $this->sanitation->urlquery($this->api_key) . '&' . http_build_query($params));
                $this->queue->release('crossref');

                $json = $response->getBody()->getContents();

                $items = $this->formatMetadata($json);

                // Hold in Cache for 24h.
                $this->cache->set($key, $items, 86400);

            } catch (BadResponseException $e) {

                if ($e->getCode() === 429) {

                    if ($this->tries < 3) {

                        sleep(1);
                        $this->tries++;
                        return $this->search($terms, $start, $rows, $filters, $sort);

                    } else {

                        $this->queue->release('crossref');
                        throw new Exception('Crossref server is busy, try again later');
                    }

                } else {

                    $this->queue->release('crossref');
                    throw new Exception('Crossref server error');
                }
            }
        }

        // Paging.
        $slice_start = ($start % $rows) - 1 === 0 ? ($start % $maximum_rows) - 1 : 0;
        $items['items'] = array_slice($items['items'], $slice_start, $rows);

        // Add search name.
        $items['search_name'] = $search_name . " • sort: $sort";

        return $items;
    }

    /**
     * Format metadata so that it is ready to be saved by the item model.
     *
     * @param $json
     * @return array
     * @throws Exception
     */
    public function formatMetadata($json): array {

        $output = [
            'found' => 0,
            'items' => []
        ];

        $items = [];

        $array = Utils::jsonDecode($json, JSON_OBJECT_AS_ARRAY);

        if ($array['message-type'] === 'work') {

            $items[] = $array['message'];
            $output['found'] = 1;

        } elseif ($array['message-type'] === 'work-list') {

            $items = $array['message']['items'];
            $output['found'] = $array['message']['total-results'];
        }

        // Articles.
        $i = 0;

        foreach ($items as $article) {

            // Title.
            $output['items'][$i][ItemMeta::COLUMN['TITLE']] = str_replace(["\r\n", "\n", "\r"], ' ', $article['title'][0] ?? '');

            // Get UIDs.
            if (isset($article['alternative-id'])) {

                foreach ($article['alternative-id'] as $uid) {

                    if (preg_match('/^S\d+/u', $uid) > 0) {

                        $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'DIRECT';
                        $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $uid;
                    }
                }
            }

            // DOI.
            if (!empty($article['DOI'])) {

                $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'DOI';
                $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $article['DOI'];
            }

            // Reference type.
            if (isset($article['type'])) {

                switch ($article['type']) {

                    case 'proceedings':
                    case 'proceedings-series':
                    case 'proceedings-article':
                        $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['CONFERENCE'];
                        $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['CONFERENCE'];
                        // Publication.
                        $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = $article['event']['name'] ?? '';
                        break;

                    case 'book':
                        $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['BOOK'];
                        $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['BOOK'];
                        break;

                    case 'book-chapter':
                        $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['CHAPTER'];
                        $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['INCOLLECTION'];
                        // Publication.
                        $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = $article['container-title'][0] ?? '';
                        break;

                    default:
                        $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['ARTICLE'];
                        $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['ARTICLE'];
                        // Publication.
                        $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = $article['container-title'][0] ?? '';
                        break;
                }

            } else {

                $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['ARTICLE'];
                $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['ARTICLE'];
                // Publication.
                $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = $article['container-title'][0] ?? '';
            }

            // Publisher
            $output['items'][$i][ItemMeta::COLUMN['PUBLISHER']] = $article['publisher'] ?? '';
            $output['items'][$i][ItemMeta::COLUMN['PLACE_PUBLISHED']] = $article['publisher-location'] ?? '';

            $output['items'][$i][ItemMeta::COLUMN['VOLUME']] = $article['volume'] ?? '';
            $output['items'][$i][ItemMeta::COLUMN['ISSUE']] = $article['issue'] ?? '';
            $output['items'][$i][ItemMeta::COLUMN['PAGES']] = $article['page'] ?? '';

            // Authors.
            if (isset($article['author'])) {

                foreach ($article['author'] as $author) {

                    $output['items'][$i][ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = $author['family'] ?? '';
                    $output['items'][$i][ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = $author['given'] ?? '';
                }
            }

            // Editors.
            if (isset($article['editor'])) {

                foreach ($article['editor'] as $editor) {

                    $output['items'][$i][ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = $editor['family'] ?? '';
                    $output['items'][$i][ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = $editor['given'] ?? '';
                }
            }

            // Pub date.
            $date = '';
            $date_arr = $article['published-print']['date-parts'][0] ?? $article['published-online']['date-parts'][0] ?? [];
            $year = $date_arr[0] ?? '';

            if (!empty($year)) {

                $month = isset($date_arr[1]) ? str_pad($date_arr[1], 2, '0', STR_PAD_LEFT) : '01';
                $day = isset($date_arr[2]) ? str_pad($date_arr[2], 2, '0', STR_PAD_LEFT) : '01';
                $date = "$year-$month-$day";
            }

            $output['items'][$i][ItemMeta::COLUMN['PUBLICATION_DATE']] = $date;

            // Link.
            $output['items'][$i][ItemMeta::COLUMN['URLS']][] = 'https://doi.org/' . $this->sanitation->urlquery($article['DOI']);

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
