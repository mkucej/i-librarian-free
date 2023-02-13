<?php

class XPLORE {

    // used for lookups other than Open Access Articles
    protected $endPoint = 'http://ieeexploreapi.ieee.org/api/v1/search/articles';

    // used for Open Access Articles lookup
    protected $oaEndPoint = 'http://ieeexploreapi.ieee.org/api/v1/search/document/';

    // set in __construct
    protected $apiKey;

    // flag that some search criteria has been provided
    protected $queryProvided = false;

    // flag for Open Access, which changes endpoint in use and limits results to just Open Access
    protected $usingOpenAccess = false;

    // flag that article number has been provided, which overrides all other non-Open Access search criteria
    protected $usingArticleNumber = false;

    // flag that a boolean method is in use
    protected $usingBoolean = false;

    // flag that a facet is in use
    protected $usingFacet = false;

    // flag that a facet has been applied, in the event that multiple facets are passed
    protected $facetApplied = false;

    // data type for results; default is JSON
    protected $outputType = 'json';

    // data format for results; default is array but could also be raw (returned string) or object (JSON object or SimpleXML object)
    protected $outputDataFormat = 'array';

    // default of 25 results returned
    protected $resultSetMax = 25;

    // maximum of 200 results returned
    protected $resultSetMaxCap = 200;

    // records returned default to position 1 in result set
    protected $startRecord = 1;

    // default sort order is ascending; could also be 'desc' for descending
    protected $sortOrder = 'asc';

    // field name that is being used for sorting
    protected $sortField = 'article_title';

    // array of permitted search fields for searchField() method
    protected $allowedSearchFields = array('abstract', 'affiliation', 'article_number', 'article_title', 'author', 'boolean_text', 'content_type', 'd-au', 'd-pubtype', 'd-publisher', 'd-year', 'doi', 'end_year', 'facet', 'index_terms', 'isbn', 'issn', 'is_number', 'meta_data', 'open_access', 'publication_number', 'publication_title', 'publication_year', 'publisher', 'querytext', 'start_year', 'thesaurus_terms');

    // array of all search parameters in use and their values
    protected $parameters = array();

    // array of all filters in use and their values
    protected $filters = array();


    /**
     *   @param string $apiKey   API Key
     *   @return void
     */
    public function __construct($apiKey='') {

        $this->apiKey = $apiKey;

    }


    /**
     *   @param string $outputType   Format for the returned result (JSON, XML)
     *   @return void
     */
    public function dataType($outputType='json') {

        $outputType = strtolower(trim($outputType));

        switch ($outputType) {

            case 'json' :
            case 'xml' :

                $this->outputType = $outputType;
                break;

        }

    }


    /**
     *   @param string $outputDataFormat   Data structure for the returned result (raw string, object, array)
     *   @return void
     */
    public function dataFormat($outputDataFormat='array') {

        $outputDataFormat = strtolower(trim($outputDataFormat));

        switch ($outputDataFormat) {

            case 'raw' :
            case 'object' :
            case 'array' :

                $this->outputDataFormat = $outputDataFormat;
                break;

        }

    }


    /**
     *   @param number $start  Start position in the returned data
     *   @return void
     */
    public function startingResult($start=1) {

        $this->startRecord = ($start > 0) ? ceil($start) : 1;

    }


    /**
     *   @param number $maximum   Max number of results to return
     *   @return void
     */
    public function maximumResults($maximum=25) {

        $this->resultSetMax = ($maximum > 0) ? ceil($maximum) : 25;

        if ($this->resultSetMax > $this->resultSetMaxCap) {

            $this->resultSetMax = $this->resultSetMaxCap;

        }

    }


    /**
     *   @param string $filter   Field used for filtering
     *   @param string $value    Text to filter on
     *   @return void
     */
    public function resultsFilter($filter='', $value='') {

        $filter = strtolower(trim($filter));
        $value = trim($value);

        switch ($filter) {

            case 'publication_number' :
            case 'open_access' :
            case 'publisher' :
            case 'content_type' :
            case 'start_year' :
            case 'end_year' :

                if (strlen($value) > 0) {

                    $this->filters[$filter] = $value;
                    $this->queryProvided = true;

                    // Standards do not have article titles, so switch to sorting by article number
                    if ($filter == 'content_type' && $value == 'Standards') {
                        $this->resultsSorting('publication_year', 'asc');
                    }

                    break;

                }

        }

    }


    /**
     *   @param string $field   Data field used for sorting
     *   @param string $order   Sort order for results (ascending or descending)
     *   @return void
     */
    public function resultsSorting($field='author', $order='asc') {

        $field = strtolower(trim($field));
        $order = strtolower(trim($order));

        switch ($field) {

            case 'author' :
            case 'article_title' :
            case 'publication_title' :
            case 'article_number' :
            case 'publication_year' :

                $this->sortField= $field;
                break;

        }

        switch ($order) {

            case 'asc' :
            case 'desc' :

                $this->sortOrder = $order;
                break;

        }

    }


    /**
     *   @param string $field     Field used for searching
     *   @param string $value       Text to query
     *   @return void
     */
    public function searchField($field='', $value='') {

        $field = strtolower(trim($field));

        if (in_array($field, $this->allowedSearchFields)) {

            $this->addParameter($field, $value);

        }

        else {

            echo 'Searches against field ' . $field . ' are not supported';
            exit;

        }

    }


    /**
     *   @param string $value   Abstract text to query
     *   @return void
     */
    public function abstractText($value='') {

        $this->addParameter('abstract', $value);

    }


    /**
     *   @param string $value   Affiliation text to query
     *   @return void
     */
    public function affiliationText($value='') {

        $this->addParameter('affiliation', $value);

    }


    /**
     *   @param string $value   Article number to query
     *   @return void
     */
    public function articleNumber($value='') {

        $this->addParameter('article_number', $value);

    }


    /**
     *   @param string $value   Article title to query
     *   @return void
     */
    public function articleTitle($value='') {

        $this->addParameter('article_title', $value);

    }


    /**
     *   @param string $value   Author to query
     *   @return void
     */
    public function authorText($value='') {

        $this->addParameter('author', $value);

    }


    /**
     *   @param string $value   Author Facet text to query
     *   @return void
     */
    public function authorFacetText($value='') {

        $this->addParameter('d-au', $value);

    }


    /**
     *   @param string $value   Value(s) to use in the boolean query
     *   @return void
     */
    public function booleanText($value='') {

        $this->addParameter('boolean_text', $value);

    }


    /**
     *   @param string $value   Content Type Facet text to query
     *   @return void
     */
    public function contentTypeFacetText($value='') {

        $this->addParameter('d-pubtype', $value);

    }


    /**
     *   @param string $value   DOI (Digital Object Identifier) to query
     *   @return void
     */
    public function doi($value='') {

        $this->addParameter('doi', $value);

    }


    /**
     *   @param string $value   Facet text to query
     *   @return void
     */
    public function facetText($value='') {

        $this->addParameter('facet', $value);

    }


    /**
     *   @param string $value   Author Keywords, IEEE Terms, and Mesh Terms to query
     *   @return void
     */
    public function indexTerms($value='') {

        $this->addParameter('index_terms', $value);

    }


    /**
     *   @param string $value   ISBN (International Standard Book Number) to query
     *   @return void
     */
    public function isbn($value='') {

        $this->addParameter('isbn', $value);

    }


    /**
     *   @param string $value   ISSN (International Standard Serial number) to query
     *   @return void
     */
    public function issn($value='') {

        $this->addParameter('issn', $value);

    }


    /**
     *   @param string $value   Issue number to query
     *   @return void
     */
    public function issueNumber($value='') {

        $this->addParameter('is_number', $value);

    }


    /**
     *   @param string $value   Text to query across metadata fields and the abstract
     *   @return void
     */
    public function metaDataText($value='') {

        $this->addParameter('meta_data', $value);

    }


    /**
     *   @param string $value   Publication Facet text to query
     *   @return void
     */
    public function publicationFacetText($value='') {

        $this->addParameter('d-year', $value);

    }


    /**
     *   @param string $value   Publisher Facet text to query
     *   @return void
     */
    public function publisherFacetText($value='') {

        $this->addParameter('d-publisher', $value);

    }


    /**
     *   @param string $value   Publication title to query
     *   @return void
     */
    public function publicationTitle($value='') {

        $this->addParameter('publication_title', $value);

    }


    /**
     *   @param string|number $value   Publication year to query
     *   @return void
     */
    public function publicationYear($value='') {

        $this->addParameter('publication_year', $value);

    }


    /**
     *   @param string $value   Text to query across metadata fields, abstract and document text
     *   @return void
     */
    public function queryText($value='') {

        $this->addParameter('querytext', $value);

    }


    /**
     *   @param string $value   Thesaurus terms (IEEE Terms) to query
     *   @return void
     */
    public function thesaurusTerms($value='') {

        $this->addParameter('thesaurus_terms', $value);

    }


    /**
     *   @param string $parameter   Data field to query
     *   @param string $value       Text to use in query
     *   @return void
     */
    public function addParameter($parameter, $value) {

        $value = trim($value);

        if (strlen($value) > 0) {

            $this->parameters[$parameter]= $value;

            // viable query criteria provided
            $this->queryProvided = true;

            // set flags based on parameter
            if ($parameter == 'article_number') {

                $this->usingArticleNumber = true;

            }

            if ($parameter == 'boolean_text') {

                $this->usingBoolean = true;

            }

            if ($parameter == 'facet' || $parameter == 'd-au' || $parameter == 'd-year' ||
                $parameter == 'd-pubtype' || $parameter == 'd-publisher') {

                $this->usingFacet = true;

            }

        }

    }


    /**
     *   @param string $article         Article number to query
     *   @return void
     */
    public function openAccess($article=0) {

        $this->usingOpenAccess = true;
        $this->articleNumber($article);

        if ($article > 0) {
            $this->queryProvided = true;
        }

    }


    /**
     * @param \GuzzleHttp\Client $client
     * @param bool $debugModeOff
     * @return mixed $formattedData   Either raw result string, SimpleXML or JSON object, or array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function callAPI(\GuzzleHttp\Client $client, $debugModeOff=true) {

        if ($this->usingOpenAccess) {

            $str = $this->buildOpenAccessQuery();

        }

        else {

            $str = $this->buildQuery();

        }

        if (!$debugModeOff) {

            echo $str;
            exit;

        }

        if (!$this->queryProvided) {

            echo 'No search criteria provided';
            exit;

        }

        $data = $this->queryAPI($str, $client);
        $formattedData = $this->formatData($data);

        return $formattedData;

    }


    /**
     *   @param void
     *   @return string $str  Full URL for querying the API
     */
    protected function buildQuery() {

        $str = $this->endPoint;

        $str .= '?apikey=' . $this->apiKey;
        $str .= '&format=' . $this->outputType;
        $str .= '&max_records=' . $this->resultSetMax;
        $str .= '&start_record=' . $this->startRecord;
        $str .= '&sort_order=' . $this->sortOrder;
        $str .= '&sort_field=' . $this->sortField;

        // add in search criteria
        // article number query takes priority over all others
        if ($this->usingArticleNumber) {

            $str .= '&article_number=' . urlencode($this->parameters['article_number']);

        }

        // boolean query
        elseif ($this->usingBoolean) {

            $str .= '&querytext=(' . urlencode($this->parameters['boolean_text']) . ')';

        }

        else {

            foreach ($this->parameters as $key => $value) {

                if ($this->usingFacet && !$this->facetApplied) {

                    $str .= '&querytext=' . urlencode($value) . '&facet=' . $key;
                    $this->facetApplied = true;

                }

                else {

                    $str .= '&' . $key . '=' . urlencode($value);

                }

            }

        }

        // add in filters
        foreach ($this->filters as $key => $value) {

            $str .= '&' . $key . '=' . urlencode($value);

        }

        // further sanitize string
        $str = filter_var($str, FILTER_SANITIZE_URL);

        if (!$str) {

            echo 'URL generated is invalid';
            exit;

        }

        return $str;

    }


    /**
     *   @param void
     *   @return string $str  Full URL for querying the API
     */
    protected function buildOpenAccessQuery() {

        $str = $this->oaEndPoint;

        $str .= $this->parameters['article_number'] . '/fulltext';
        $str .= '?apikey=' . $this->apiKey;
        $str .= '&format=' . $this->outputType;

        // further sanitize string
        $str = filter_var($str, FILTER_SANITIZE_URL);

        if (!$str) {

            echo 'URL generated is invalid';
            exit;

        }

        return $str;

    }


    /**
     * @param string $str Full URL to pass to API
     * @param \GuzzleHttp\Client $client
     * @return string $data   Result string from API
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function queryAPI($str, \GuzzleHttp\Client $client) {

        $response = $client->get($str);
        $data = $response->getBody()->getContents();

//        $ch = curl_init();
//        $timeout = 300;
//        curl_setopt($ch, CURLOPT_URL, $str);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
//        $data = curl_exec($ch);
//        curl_close($ch);
        return $data;

    }


    /**
     *   @param string $data   Result string from API
     *   @return void
     */
    protected function formatData($data) {

        if ($this->outputType == 'xml' && !extension_loaded('simplexml')) {

            echo 'SimpleXML extension is not loaded';
            exit;

        }

        elseif ($this->outputType == 'json' && !extension_loaded('json')) {

            echo 'JSON extension is not loaded';
            exit;

        }

        switch ($this->outputDataFormat) {

            case 'raw' :

                return $data;

            case 'object' :

                if ($this->outputType == 'xml') {

                    $obj = simplexml_load_string($data);

                }

                else {

                    $obj = json_decode($data);

                }

                return $obj;

            case 'array' :

                if ($this->outputType == 'xml') {

                    $obj = simplexml_load_string($data);
                    $json = json_encode($obj);
                    $arr = json_decode($json, true);

                }

                else {

                    $arr = json_decode($data, true);

                }

                return $arr;

        }


    }


}

?>