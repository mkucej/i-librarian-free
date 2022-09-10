<?php

namespace Librarian\External;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Librarian\ItemMeta;
use Librarian\Container\DependencyInjector;
use Librarian\Media\Xml;
use SimpleXMLIterator;

final class Pubmed extends ExternalDatabase implements ExternalDatabaseInterface {

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string API URLs.
     */
    private string $url_fetch;
    private string $url_search;

    /**
     * Pubmed constructor.
     * @param DependencyInjector $di
     * @param string $api_key Optional.
     * @throws Exception
     */
    public function __construct(DependencyInjector $di, string $api_key = '') {

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

        $api_key_url      = empty($api_key) ? '' : "api_key=$api_key&";
        $this->url_fetch  = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . $api_key_url;
        $this->url_search = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?' . $api_key_url;
    }

    /**
     * Fetch single record based on an ID.
     *
     * @param string $uid
     * @return array
     * @throws Exception
     * @throws GuzzleException
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
     * @throws GuzzleException
     */
    public function fetchMultiple(array $uids): array {

        $params = [
            'db'      => 'pubmed',
            'id'      => join(',', $uids),
            'retmode' => 'xml'
        ];

        // Acquire another semaphore.
        $this->queue->wait('pubmed');

        $response = $this->client->get($this->url_fetch . http_build_query($params));

        $this->queue->release('pubmed');

        return $this->formatMetadata($response->getBody()->getContents());
    }

    /**
     * Search database and return an array of records.
     *
     * @param array $terms Search terms [name => term].
     * @param int $start Starting record for this page.
     * @param int $rows Optional number of records per I, Librarian page.
     * @param array|null $filters Optional array of filters [name => value].
     * @param string|null $sort Optional sorting string.
     * @return array
     * @throws Exception
     * @throws GuzzleException
     */
    public function search(
        array  $terms,
        int    $start,
        int    $rows = 10,
        array  $filters = null,
        string $sort = null
    ): array {

        // Max rows to fetch (100) can be overridden with $rows.
        $maximum_rows = max(100, $rows);

        $allowed_params = [
            'TIAB'   => 'Abstract',
            'AD'     => 'Affiliation',
            'ALL'    => 'Anywhere',
            'AU'     => 'Author',
            'AID'    => 'DOI',
            '1AU'    => 'First Author',
            'PG'     => 'First Page',
            'TA'     => 'Journal Abbreviation',
            'LASTAU' => 'Last Author',
            'PMID'   => 'PMID',
            'TI'     => 'Title',
            'VI'     => 'Volume',
            'DP'     => 'Year',
            'boolean' => ''
        ];

        $params = [
            'db'         => 'pubmed',
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
                $search_name = "$value ";
                break;
            }

            $queries[] = "$value [$name]";
            $search_name .= "$allowed_params[$name]: $value ";
        }

        // Add filters.
        if (!empty($filters)) {

            foreach ($filters as $filter) {

                switch (key($filter)) {

                    case 'language':
                        $queries[] = "eng [LA]";
                        $search_name .= "filter: in English ";
                        break;

                    case 'pubtype':
                        $queries[] = "review [PT]";
                        $search_name .= "filter: review ";
                        break;

                    case 'links':
                        $queries[] = "free full text [SB]";
                        $search_name .= "filter: free full text ";
                        break;

                    case 'last_added':
                        $days = current($filter);

                        if ($days < 1 || $days > 365) {

                            break;
                        }

                        $from = date('Y/m/d', time() - $days * 86400);
                        $now = date('Y/m/d', time() - 86400);

                        $queries[] = "$from:$now[CRDT]";
                        $plural = $days === '1' ? '' : 's';
                        $search_name .= "\u{2022} last $days day$plural ";
                        break;
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
            $this->queue->wait('pubmed');

            // Get results.
            $response = $this->client->get($this->url_search . http_build_query($params));

            $this->queue->release('pubmed');

            $contents = $response->getBody()->getContents();
            $json = json_decode($contents, true);

            $items['items'] = [];

            if (!empty($json['esearchresult']['idlist'])) {

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
     * @param string|array $input
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

        foreach ($xml_doc as $article) {

            $abstract = [];
            $authors  = [];
            $editors  = [];
            $id_list  = [];
            $pub_date = [];

            if (isset($article->MedlineCitation)) {

                // Article.
                $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['ARTICLE'];
                $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['ARTICLE'];
                $output['items'][$i][ItemMeta::COLUMN['TITLE']] = (string) $article->MedlineCitation->Article->ArticleTitle;
                $output['items'][$i][ItemMeta::COLUMN['PRIMARY_TITLE']] = (string) $article->MedlineCitation->Article->Journal->ISOAbbreviation ?? '';
                $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = (string) $article->MedlineCitation->Article->Journal->Title ?? '';

                // Authors.
                $authors = $article->MedlineCitation->Article->AuthorList->Author ?? [];

                // Abstract.
                $abstract = $article->MedlineCitation->Article->Abstract->AbstractText ?? [];

                // PMID.
                $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'PMID';
                $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = (string) $article->MedlineCitation->PMID;

                // Links.
                $output['items'][$i][ItemMeta::COLUMN['URLS']][0] = 'https://www.ncbi.nlm.nih.gov/pubmed/' . (string) $article->MedlineCitation->PMID;

                // Volume, issue.
                $output['items'][$i][ItemMeta::COLUMN['VOLUME']] = (string) $article->MedlineCitation->Article->Journal->JournalIssue->Volume ?? '';
                $output['items'][$i][ItemMeta::COLUMN['ISSUE']] = (string) $article->MedlineCitation->Article->Journal->JournalIssue->Issue ?? '';

                // Pages.
                $pages = '';
                $start_page = (string) $article->MedlineCitation->Article->Pagination->StartPage ?? '';
                $end_page = (string) $article->MedlineCitation->Article->Pagination->StartPage ?? '';
                $pagination = (string) $article->MedlineCitation->Article->Pagination->MedlinePgn ?? '';

                if (!empty($start_page)) {

                    $pages = $start_page;
                    $pages = $pages . (!empty($end_page) ? "-$end_page" : '');

                } elseif (!empty($pagination)) {

                    $pages = $pagination;
                }

                $output['items'][$i][ItemMeta::COLUMN['PAGES']] = $pages;

                // Keywords.
                $mesh_headings = $article->MedlineCitation->MeshHeadingList->MeshHeading ?? [];

                foreach ($mesh_headings as $category) {

                    $output['items'][$i][ItemMeta::COLUMN['KEYWORDS']][] = (string) $category->DescriptorName;
                }

                // Id list.
                $id_list = $article->PubmedData->ArticleIdList->ArticleId ?? [];

                // Pub date.
                $pub_date = $article->MedlineCitation->Article->Journal->JournalIssue->PubDate ?? [];

            } elseif (isset($article->BookDocument) && isset($article->BookDocument->ArticleTitle)) {

                // Chapter.
                $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['CHAPTER'];
                $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['INCOLLECTION'];
                $output['items'][$i][ItemMeta::COLUMN['TITLE']] = (string) $article->BookDocument->ArticleTitle;
                $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = (string) $article->BookDocument->Book->BookTitle;
                $output['items'][$i][ItemMeta::COLUMN['TERTIARY_TITLE']] = (string) $article->BookDocument->Book->CollectionTitle ?? '';

                // Authors.
                $authors = $article->BookDocument->AuthorList->Author ?? [];

                // Editors.
                $editors = $article->BookDocument->Book->AuthorList->Author ?? [];

                // Abstract.
                $abstract = $article->BookDocument->Abstract->AbstractText ?? [];

                // PMID.
                $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'PMID';
                $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = (string) $article->BookDocument->PMID;

                // Links.
                $output['items'][$i][ItemMeta::COLUMN['URLS']][0] = 'https://www.ncbi.nlm.nih.gov/pubmed/' . (string) $article->BookDocument->PMID;

                // Pages.
                $pages = '';
                $start_page = (string) $article->BookDocument->Pagination->StartPage ?? '';
                $end_page = (string) $article->BookDocument->Pagination->StartPage ?? '';
                $pagination = (string) $article->BookDocument->Pagination->MedlinePgn ?? '';

                if (!empty($start_page)) {

                    $pages = $start_page;
                    $pages = $pages . (!empty($end_page) ? "-$end_page" : '');

                } elseif (!empty($pagination)) {

                    $pages = $pagination;
                }

                $output['items'][$i][ItemMeta::COLUMN['PAGES']] = $pages;

                // Id list.
                $id_list = $article->BookDocument->ArticleIdList->ArticleId ?? [];

                // Pub date.
                $pub_date = $article->BookDocument->Book->PubDate ?? [];

            } elseif (isset($article->BookDocument)) {

                // Book.
                $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['BOOK'];
                $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['BOOK'];
                $output['items'][$i][ItemMeta::COLUMN['TITLE']] = (string) $article->BookDocument->Book->BookTitle;
                $output['items'][$i][ItemMeta::COLUMN['SECONDARY_TITLE']] = (string) $article->BookDocument->Book->CollectionTitle ?? '';

                // Authors.
                $authors = $article->BookDocument->AuthorList->Author ?? [];

                // Editors.
                $editors = $article->BookDocument->Book->AuthorList->Author ?? [];

                // Abstract.
                $abstract = $article->BookDocument->Abstract->AbstractText ?? [];

                // PMID.
                $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'PMID';
                $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = (string) $article->BookDocument->PMID;

                // Links.
                $output['items'][$i][ItemMeta::COLUMN['URLS']][0] = 'https://www.ncbi.nlm.nih.gov/pubmed/' . (string) $article->BookDocument->PMID;

                // Id list.
                $id_list = $article->BookDocument->ArticleIdList->ArticleId ?? [];

                // Pub date.
                $pub_date = $article->BookDocument->Book->PubDate ?? [];
            }

            foreach ($authors as $author) {

                $output['items'][$i][ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = (string) $author->LastName ?? $author->CollectiveName;
                $output['items'][$i][ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = (string) $author->ForeName ?? '';
                $output['items'][$i][ItemMeta::COLUMN['AFFILIATION']] = (string) $author->AffiliationInfo->Affiliation ?? '';
            }

            foreach ($editors as $editor) {

                $output['items'][$i][ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = (string) $editor->LastName ?? $editor->CollectiveName;
                $output['items'][$i][ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = (string) $editor->ForeName ?? '';
            }

            $abstract_parts = [];

            foreach ($abstract as $part) {

                $attrs = $part->attributes();

                if (isset($attrs->Label)) {

                    $abstract_parts[] = "$attrs->Label: $part";

                } else {

                    $abstract_parts[] = (string) $part;
                }

                $processed_abstract = join(' ', $abstract_parts);
                $output['items'][$i][ItemMeta::COLUMN['ABSTRACT']] = trim(str_replace(["\r\n", "\n", "\r"], " ", $processed_abstract));
            }

            // Ids.
            foreach ($id_list as $id) {

                $attrs = $id->attributes();

                if ((string) $attrs->IdType === 'doi') {

                    $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'DOI';
                    $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = (string) $id;
                }

                if ((string) $attrs->IdType === 'pmc') {

                    $pmcid = (string) $id;

                    $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'PMCID';
                    $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $pmcid;

                    // Links.
                    $output['items'][$i][ItemMeta::COLUMN['URLS']][1] = 'https://www.ncbi.nlm.nih.gov/pmc/articles/' . $pmcid . '/pdf/';

                }
            }

            // Pub date.
            $date = '';

            if (isset($pub_date->Year)) {

                $year = (string) $pub_date->Year;

                if (strlen($year) === 4) {

                    $date = $year;
                }

                if (!empty($date) && isset($pub_date->Month)) {

                    $month = (string) $pub_date->Month;

                    if (is_numeric($month) && strlen($month) === 1 || strlen($month) === 2) {

                        $date .= '-' . str_pad($month, 2, '0', STR_PAD_LEFT);

                    } elseif (is_numeric($month) === false) {

                        $parsed = date_parse($month);

                        if (is_int($parsed['month'])) {

                            $date .= '-' . str_pad($parsed['month'], 2, '0', STR_PAD_LEFT);
                        }

                    } else {

                        $date .= '-01';
                    }

                } else {

                    $date .= '-01';
                }

                if (!empty($date) && isset($pub_date->Day)) {

                    $day = (string) $pub_date->Day;

                    if (is_numeric($day) && strlen($day) === 1 || strlen($day) === 2) {

                        $date .= '-' . str_pad($day, 2, '0', STR_PAD_LEFT);

                    } else {

                        $date .= '-01';
                    }

                } else {

                    $date .= '-01';
                }

            } elseif (isset($pub_date->MedlineDate)) {

                $date = substr((string) $pub_date->MedlineDate, 0 , 4) . '-01-01';
            }

            $output['items'][$i][ItemMeta::COLUMN['PUBLICATION_DATE']] = $date;

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
