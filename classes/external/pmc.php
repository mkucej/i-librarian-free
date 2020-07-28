<?php

namespace Librarian\External;

use Exception;
use Librarian\Http\Client\Client;
use Librarian\ItemMeta;
use Librarian\Container\DependencyInjector;
use Librarian\Media\Xml;
use SimpleXMLIterator;

final class Pmc extends ExternalDatabase implements ExternalDatabaseInterface {

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string API URLs.
     */
    private $url_fetch;
    private $url_search;

    /**
     * Pubmed constructor.
     * @param DependencyInjector $di
     * @param string $api_key Optional.
     * @throws Exception
     */
    public function __construct(DependencyInjector $di, string $api_key = '') {

        parent::__construct($di);

        // Set queue lane.
        $this->queue->lane('pubmed');

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

        $api_key_url      = empty($api_key) ? '' : "api_key={$api_key}&";
        $this->url_fetch  = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . $api_key_url;
        $this->url_search = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?' . $api_key_url;
    }

    /**
     * Fetch single record based on an ID.
     *
     * @param string $uid
     * @return array
     * @throws Exception
     */
    public function fetch(string $uid): array {

        return $this->fetchMultiple([$uid]);
    }

    /**
     * Fetch multiple records based on an array of IDs.
     *
     * @param array $uids
     * @return array
     * @throws Exception
     */
    public function fetchMultiple($uids): array {

        $params = [
            'db'      => 'pmc',
            'id'      => join(',', $uids),
            'retmode' => 'xml'
        ];

        $response = $this->client->get($this->url_fetch . http_build_query($params));

        $items = $this->formatMetadata($response->getBody()->getContents());

        return $items;
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

        $maximum_rows = 100;

        $allowed_params = [
            'AB'      => 'Abstract',
            'AD'      => 'Affiliation',
            'ALL'     => 'Anywhere',
            'AU'      => 'Author',
            'DOI'     => 'DOI',
            'PG'      => 'Pagination',
            'TA'      => 'Journal Abbreviation',
            'PMID'    => 'PMID',
            'TI'      => 'Title',
            'VI'      => 'Volume',
            'PUBDATE' => 'Year',
            'TW'      => 'Full text',
            'boolean' => ''
        ];

        $params = [
            'db'         => 'pmc',
            'term'       => '',
            'usehistory' => 'n',
            'retstart'   =>  0,
            'retmax'     =>  $maximum_rows,
            'retmode'    => 'json',
            'sort'       => 'relevance'
        ];

        // Add search terms.
        $queries = [];
        $search_name = '';

        foreach ($terms as $term) {

            $name  = key($term);
            $value = current($term);

            if (isset($allowed_params[$name]) === false) {

                continue;
            }

            // Boolean search overwrites everything.
            if ($name === 'boolean' && !empty($value)) {

                $queries = [$value];
                $search_name = "{$value} ";
                break;
            }

            $queries[] = "{$value} [{$name}]";
            $search_name .= "{$allowed_params[$name]}: $value ";
        }

        // Add filters.
        if (!empty($filters)) {

            foreach ($filters as $filter) {

                if (key($filter) === 'last_added') {

                    $days = current($filter);

                    if ($days < 1 || $days > 365) {

                        continue;
                    }

                    $from = date('Y/m/d', time() - $days * 86400);
                    $now = date('Y/m/d', time() - 86400);

                    $queries[] = "{$from}:{$now}[PMCLIVEDATE]";
                    $plural = $days === '1' ? '' : 's';
                    $search_name .= "\u{2022} last {$days} day{$plural} ";
                }
            }
        }

        $params['term'] = join(' AND ', $queries);

        // Add sorting.
        switch ($sort) {

            case 'relevance':
                $params['sort'] = 'relevance';
                $search_name .= "sort: relevance ";
                break;

            case 'pubsolr12':
                $params['sort'] = '';
                $search_name .= "sort: last added ";
                break;

            case 'pub date':
                $params['sort'] = 'pub date';
                $search_name .= "sort: last published ";
                break;
        }

        // Total rows to fetch per search. This does not equal the I, Librarian page size.
        $params['retstart'] = floor($start / $maximum_rows) * $maximum_rows;

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

            // Acquire semaphore.
            $this->queue->wait();

            // Get results.
            $response = $this->client->get($this->url_search . http_build_query($params));
            $contents = $response->getBody()->getContents();

            $json = json_decode($contents, true);

            $items['items'] = [];

            if (!empty($json['esearchresult']['idlist'])) {

                // Acquire another semaphore.
                $this->queue->wait();

                $items = $this->fetchMultiple($json['esearchresult']['idlist']);
            }

            $items['found'] = $json['esearchresult']['count'] ?? 0;

            // Hold in Cache for 24h.
            $this->cache->set($key, $items, 86400);
        }

        // Paging.
        $slice_start = ($start % $rows) - 1 === 0 ? ($start % $maximum_rows) - 1 : 0;
        $items['items'] = array_slice($items['items'], $slice_start, $rows);

        $items['search_name'] = $search_name;

        return $items;
    }

    /**
     * Format metadata so that it is ready to be saved by the item model.
     *
     * @param string $input
     * @return array
     * @throws Exception
     */
    public function formatMetadata($input): array {

        /** @var Xml $xml_obj */
        $xml_obj = $this->di->get('Xml');

        /** @var SimpleXMLIterator $xml_doc */
        $xml_doc = $xml_obj->loadXmlString($input);

        $output = [
            'found' => 0,
            'items' => []
        ];

        // Articles.
        $i = 0;

        foreach ($xml_doc->article as $node) {

            $article = $node->front;

            // Type.
            $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['ARTICLE'];
            $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['ARTICLE'];

            // Title.
            $output['items'][$i][ItemMeta::COLUMN['TITLE']] = (string) $article->{'article-meta'}->{'title-group'}->{'article-title'} ?? null;

            // Authors, editors.
            $contribs = $article->{'article-meta'}->{'contrib-group'}->contrib ?? [];

            foreach ($contribs as $contrib) {

                $attrs = $contrib->attributes();

                if ((string) $attrs->{'contrib-type'} === 'author') {

                    $output['items'][$i][ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = (string) $contrib->name->surname ?? '';
                    $output['items'][$i][ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = (string) $contrib->name->{'given-names'} ?? '';
                }

                if ((string) $attrs->{'contrib-type'} === 'editor') {

                    $output['items'][$i][ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = (string) $contrib->name->surname ?? '';
                    $output['items'][$i][ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = (string) $contrib->name->{'given-names'} ?? '';
                }
            }

            // Abstract.
            $abstract = '';
            $first_abstract = $article->{'article-meta'}->abstract[0] ?? [];

            if (!empty($first_abstract)) {

                $abstract = strip_tags($first_abstract->asXML());
            }

            $output['items'][$i][ItemMeta::COLUMN['ABSTRACT']] = trim(str_replace(["\r\n", "\n", "\r"], " ", $abstract));

            // Id list.
            $id_list = $article->{'article-meta'}->{'article-id'} ?? [];

            foreach ($id_list as $id) {

                $attrs = $id->attributes();

                if ((string) $attrs->{'pub-id-type'} === 'doi') {

                    $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'DOI';
                    $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = (string) $id;
                }

                if ((string) $attrs->{'pub-id-type'} === 'pmid') {

                    $pmid = (string) $id;

                    $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'PMID';
                    $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $pmid;

                    // Links.
                    $output['items'][$i][ItemMeta::COLUMN['URLS']][2] = 'https://www.ncbi.nlm.nih.gov/pubmed/' . $pmid;
                }

                if ((string) $attrs->{'pub-id-type'} === 'pmc') {

                    $pmcid = (string) $id;
                    $pmcid = is_numeric($id) ? "PMC{$pmcid}" : $pmcid;

                    $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'PMCID';
                    $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $pmcid;

                    // Links.
                    $output['items'][$i][ItemMeta::COLUMN['URLS']][0] = 'https://www.ncbi.nlm.nih.gov/pmc/articles/' . $pmcid;
                    $output['items'][$i][ItemMeta::COLUMN['URLS']][1] = 'https://www.ncbi.nlm.nih.gov/pmc/articles/' . $pmcid . '/pdf/';
                }
            }

            // Volume, issue.
            $output['items'][$i][ItemMeta::COLUMN['VOLUME']] = (string) $article->{'article-meta'}->volume ?? '';
            $output['items'][$i][ItemMeta::COLUMN['ISSUE']] = (string) $article->{'article-meta'}->issue ?? '';

            // Pages.
            $pages = '';
            $start_page = (string) $article->{'article-meta'}->fpage ?? '';
            $end_page = (string) $article->{'article-meta'}->lpage ?? '';

            if (!empty($start_page)) {

                $pages = $start_page;
                $pages = $pages . (!empty($end_page) ? "-{$end_page}" : '');
            }

            $output['items'][$i][ItemMeta::COLUMN['PAGES']] = $pages;

            // Pub date.
            $date = '';
            $pub_date = $article->{'article-meta'}->{'pub-date'}[0] ?? [];

            if (!empty($pub_date)) {

                $year = (string) $pub_date->year;
                $month = empty((string) $pub_date->month) ? '1' : (string) $pub_date->month;
                $day = empty((string) $pub_date->day) ? '1' : (string) $pub_date->day;

                $date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            }

            $output['items'][$i][ItemMeta::COLUMN['PUBLICATION_DATE']] = $date;

            // Keywords.
            $keyword_group = $article->{'kwd-group'}->kwd ?? [];

            foreach ($keyword_group as $keyword) {

                $output['items'][$i][ItemMeta::COLUMN['KEYWORDS']][] = (string) $keyword;
            }

            // Publication.
            $journal_ids = $article->{'journal-meta'}->{'journal-id'} ?? [];

            foreach ($journal_ids as $journal_id) {

                $attrs = $journal_id->attributes();

                if ((string) $attrs->{'journal-id-type'} === 'iso-abbrev') {

                    $output['items'][$i][ItemMeta::COLUMN['PRIMARY_TITLE']] = (string) $journal_id;
                }
            }

            $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = (string) $article->{'journal-meta'}->{'journal-title-group'}->{'journal-title'} ?? '';

            // Publisher.
            $output['items'][$i][ItemMeta::COLUMN['PUBLISHER']] = (string) $article->{'journal-meta'}->{'publisher'}->{'publisher-name'} ?? '';
            $output['items'][$i][ItemMeta::COLUMN['PLACE_PUBLISHED']] = (string) $article->{'journal-meta'}->{'publisher'}->{'publisher-loc'} ?? '';

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
