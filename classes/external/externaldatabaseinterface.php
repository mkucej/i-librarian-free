<?php

namespace Librarian\External;

interface ExternalDatabaseInterface {

    /**
     * Fetch single record based on an ID.
     *
     * @param  string $uid
     * @return array
     */
    public function fetch(string $uid): array;

    /**
     * Fetch multiple records based on an array of IDs.
     *
     * @param  array $uids
     * @return array
     */
    public function fetchMultiple(array $uids): array;

    /**
     * Search database and return an array of records.
     *
     * @param array $terms Search terms [name => term].
     * @param int $start Starting record for this page.
     * @param int $rows Optional number of records per page.
     * @param array|null $filters Optional array of filters [name => value].
     * @param string|null $sort Optional sorting string.
     * @return array
     */
    public function search(
        array  $terms,
        int    $start,
        int    $rows    = 10,
        array  $filters = null,
        string $sort    = null
    ): array;

    /**
     * Convert API response to standard metadata.
     *
     * @param array|string $input
     * @return array
     */
    public function formatMetadata($input): array;
}
