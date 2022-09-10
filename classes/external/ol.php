<?php

namespace Librarian\External;

use Exception;
use GuzzleHttp\Utils;
use Librarian\ItemMeta;

final class Ol extends ExternalDatabase implements ExternalDatabaseInterface {

    /**
     * Fetch single record based on an ID.
     *
     * @param string $uid
     * @return array
     * @throws Exception
     */
    public function fetch(string $uid): array {

        return [];
    }

    /**
     * Fetch multiple records based on an array of IDs. Not implemented.
     *
     * @param array $uids
     * @return array
     * @throws Exception
     */
    public function fetchMultiple(array $uids): array {

        return [];
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
     */
    public function search(
        array  $terms,
        int    $start,
        int    $rows = 10,
        array  $filters = null,
        string $sort = null
    ): array {

        return [];
    }

    /**
     * Format metadata so that it is ready to be saved by the item model.
     *
     * @param string $json
     * @return array
     * @throws Exception
     */
    public function formatMetadata($json): array {

        $output = [
            'found' => 0,
            'items' => []
        ];

        $array = Utils::jsonDecode($json, JSON_OBJECT_AS_ARRAY);

        $i = 0;
        $article = current($array);

        if (isset($article['title']) === false) {

            return $output;
        }

        // Title.
        $output['items'][$i][ItemMeta::COLUMN['TITLE']] = str_replace(["\r\n", "\n", "\r"], ' ', $article['title']);

        // Get UIDs.
        if (isset($article['identifiers'])) {

            foreach ($article['identifiers'] as $uid) {

                if (isset($uid['isbn_13'][0])) {

                    $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'ISBN';
                    $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $uid['isbn_13'][0];

                } elseif (isset($uid['isbn_10'][0])) {

                    $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'ISBN';
                    $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $uid['isbn_10'][0];
                }

                if (isset($uid['openlibrary'][0])) {

                    $output['items'][$i][ItemMeta::COLUMN['UID_TYPES']][] = 'OL';
                    $output['items'][$i][ItemMeta::COLUMN['UIDS']][] = $uid['openlibrary'][0];
                }
            }
        }

        // Reference type.
        $output['items'][$i][ItemMeta::COLUMN['REFERENCE_TYPE']] = ItemMeta::TYPE['BOOK'];
        $output['items'][$i][ItemMeta::COLUMN['BIBTEX_TYPE']] = ItemMeta::BIBTEX_TYPE['BOOK'];

        // Publisher
        $output['items'][$i][ItemMeta::COLUMN['PUBLISHER']] = $article['publishers'][0]['name'] ?? '';
        $output['items'][$i][ItemMeta::COLUMN['PLACE_PUBLISHED']] = $article['publish_places'][0]['name'] ?? '';

        // Authors.
        if (isset($article['authors'])) {

            foreach ($article['authors'] as $author) {

                $parts = explode(' ', $author['name']);

                $output['items'][$i][ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = array_pop($parts);
                $output['items'][$i][ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = join(' ', $parts);
            }
        }

        // Link.
        $output['items'][$i][ItemMeta::COLUMN['URLS']][] = 'https://openlibrary.org' . $article['key'];

        // No title, skip.
        if (empty($output['items'][$i][ItemMeta::COLUMN['TITLE']])) {

            unset($output['items'][$i]);
        }

        return $output;
    }
}
