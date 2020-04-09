<?php

namespace LibrarianApp;

use Exception;
use Librarian\Cache\FileCache;
use Librarian\Http\Client\Psr7\Stream;
use Librarian\ItemMeta;
use Librarian\Media\ScalarUtils;
use Librarian\Security\Sanitation;
use PDO;
use ZipArchive;
use function Librarian\Http\Client\Psr7\stream_for;

/**
 * @method array clipboardAdd(array $items)
 * @method void  clipboardDelete(array $items)
 * @method Stream exportZip(array $items)
 * @method array maxId()
 * @method array projectAdd(array $item_ids, int $project_id)
 * @method void  projectDelete(array $item_ids, int $project_id)
 * @method array read(string $collection, string $orderby = 'id', int $limit = 10, int $offset = 0, string|array|null $display_action = 'title', int $project_id = null)
 * @method array readFiltered(array $filters, $collection, $orderby = 'id', $limit = 10, $offset = 0, string|array|null $display_action = 'title', int $project_id = null)
 * @method void  resetBibtexIds()
 * @method array search(array $search, $collection = 'library', $limit = 10, $offset = 0, string|array|null $display_action = 'title', int $project_id = null)
 * @method void  tag(int|array $items, int|array $tags)
 * @method void  untag(int|array $items, int|array $tags)
 */
class ItemsModel extends AppModel {

    /**
     * @var ScalarUtils
     */
    private $scalar_utils;

    /**
     * Basic item paging.
     *
     * @param string $collection Library or clipboard.
     * @param string $orderby
     * @param integer $limit
     * @param integer $offset
     * @param string|array|null $display_action
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _read(
        string $collection,
        string $orderby = 'id',
        int $limit = 10,
        int $offset = 0,
        $display_action = 'title',
        int $project_id = null
    ) {

        $output = [
            'items' => [],
            'total_count' => 0
        ];

        // SQL ORDER BY.
        $order = $this->orderByColumn($orderby);

        $this->db_main->beginTransaction();

        // SQL.
        switch ($collection) {

            case 'library':

                $sql = <<<EOT
SELECT id as item_id
    FROM items
    ORDER BY {$order}
    LIMIT ? OFFSET ?
EOT;

                if ($display_action === 'export' || is_array($display_action)) {

                    $columns = [
                        (integer) $this->app_settings->getGlobal('max_items'),
                        0
                    ];

                } else {

                    $columns = [
                        $limit,
                        $offset
                    ];
                }

                break;

            case 'project':

                // Verify project.
                if ($this->verifyProject($project_id) === false) {

                    $this->db_main->rollBack();
                    throw new Exception('you are not authorized to view this project', 403);
                }

                // Get project title.
                $sql = <<<EOT
SELECT project
    FROM projects
    WHERE id = ?
EOT;

                $columns = [
                    $project_id
                ];

                $this->db_main->run($sql, $columns);
                $output['project'] = $this->db_main->getResult();

                $sql = <<<EOT
SELECT items.id as item_id
    FROM items
    INNER JOIN projects_items ON items.id = projects_items.item_id
    INNER JOIN projects ON projects_items.project_id = projects.id
    WHERE projects_items.project_id = ?
    ORDER BY $order
    LIMIT ? OFFSET ?
EOT;

                if ($display_action === 'export' || is_array($display_action)) {

                    $columns = [
                        $project_id,
                        (integer) $this->app_settings->getGlobal('max_items'),
                        0
                    ];

                } else {

                    $columns = [
                        $project_id,
                        $limit,
                        $offset
                    ];
                }

                break;

            case 'clipboard':

                $sql = <<<EOT
SELECT items.id as item_id
    FROM items
    INNER JOIN clipboard ON items.id=clipboard.item_id
    WHERE clipboard.user_id = ?
    ORDER BY $order
    LIMIT ? OFFSET ?
EOT;

                if ($display_action === 'export' || is_array($display_action)) {

                    $columns = [
                        $this->user_id,
                        (integer) $this->app_settings->getGlobal('max_items'),
                        0
                    ];

                } else {

                    $columns = [
                        $this->user_id,
                        $limit,
                        $offset
                    ];
                }

                break;
        }

        // Run the query.
        $this->db_main->run($sql, $columns);
        $rows = $this->db_main->getResultRows();

        if (empty($rows)) {

            return $output;
        }

        // Omnitool.
        if (is_array($display_action)) {

            $this->db_main->commit();

            $item_ids = array_column($rows, 'item_id');

            $this->omnitool($item_ids, $display_action);

            return [];
        }

        // Get item data.
        $output['items'] = $this->itemData($rows, $display_action);

        // Export. Send items, no counting necessary.
        if ($display_action === 'export') {

            $this->db_main->commit();
            return $output;
        }

        // Total count.
        switch ($collection) {

            case 'library':

                $sql = <<<EOT
SELECT total_count
    FROM stats
    WHERE table_name = 'items';
EOT;

                $this->db_main->run($sql);
                break;

            case 'project':

                $sql = <<<EOT
SELECT count(*)
    FROM projects_items
    WHERE project_id = ?
EOT;

                $columns = [
                    $project_id
                ];

                $this->db_main->run($sql, $columns);
                break;

            case 'clipboard':

                $sql = <<<EOT
SELECT count(*)
    FROM clipboard
    WHERE user_id = ?
EOT;

                $columns = [
                    $this->user_id
                ];

                $this->db_main->run($sql, $columns);
                break;
        }

        $output['total_count'] = $this->db_main->getResult();

        $this->db_main->commit();

        return $output;
    }

    /**
     * @param array $filters
     * @param string $collection
     * @param string $orderby
     * @param int $limit
     * @param int $offset
     * @param string|array|null $display_action
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _readFiltered(
        array $filters,
        string $collection,
        string $orderby = 'id',
        int $limit = 10,
        int $offset = 0,
        $display_action = 'title',
        int $project_id = null
    ) {

        $output = [
            'items' => [],
            'total_count' => 0
        ];
        $columns = [];
        $sqls    = [];

        // SQL ORDER BY.
        $order = $this->orderByColumn($orderby);

        // Items filtered by tags.
        if (!empty($filters['tag'])) {

            foreach ($filters['tag'] as $id) {

                $sqls[] = <<<EOT
SELECT items.id as item_id
    FROM items INNER JOIN items_tags ON items.id=items_tags.item_id
    WHERE items_tags.tag_id=?
EOT;

                $columns[] = $id;
            }
        }

        // Items filtered by author.
        if (!empty($filters['author'])) {

            foreach ($filters['author'] as $id) {

                $sqls[] = <<<EOT
SELECT items.id as item_id
    FROM items INNER JOIN items_authors ON items.id=items_authors.item_id
    WHERE items_authors.author_id=?
EOT;

                $columns[] = $id;
            }
        }

        // Items filtered by editor.
        if (!empty($filters['editor'])) {

            foreach ($filters['editor'] as $id) {

                $sqls[] = <<<EOT
SELECT items.id as item_id
    FROM items INNER JOIN items_editors ON items.id=items_editors.item_id
    WHERE items_editors.editor_id=?
EOT;

                $columns[] = $id;
            }
        }

        // Items filtered by keyword.
        if (!empty($filters['keyword'])) {

            foreach ($filters['keyword'] as $id) {

                $sqls[] = <<<EOT
SELECT items.id as item_id
    FROM items INNER JOIN items_keywords ON items.id=items_keywords.item_id
    WHERE items_keywords.keyword_id=?
EOT;

                $columns[] = $id;
            }
        }

        if (!empty($filters['misc']) && in_array('nopdf', $filters['misc'])) {

            $sqls[] = <<<EOT
SELECT id as item_id
    FROM items
    WHERE file_hash IS NULL 
EOT;
        }

        if (!empty($filters['misc']) && in_array('myitems', $filters['misc'])) {

            $sqls[] = <<<EOT
SELECT id as item_id
    FROM items
    WHERE added_by = ?
EOT;

            $columns[] = $this->user_id;
        }

        if (!empty($filters['misc']) && in_array('othersitems', $filters['misc'])) {

            $sqls[] = <<<EOT
SELECT id as item_id
    FROM items
    WHERE added_by > ? OR added_by < ?
EOT;

            $columns[] = $this->user_id;
            $columns[] = $this->user_id;
        }

        // One-to-many filters.
        $otm_filters = [
            'added_time',
            'primary_title',
            'secondary_title',
            'tertiary_title',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'custom5',
            'custom6',
            'custom7',
            'custom8'
        ];

        foreach ($otm_filters as $otm_filter) {

            if (empty($filters[$otm_filter])) {

                continue;
            }

            foreach ($filters[$otm_filter] as $id) {

                switch ($otm_filter) {

                    case 'primary_title':
                        $otm_filter = 'primary_title_id';
                        break;


                    case 'secondary_title':
                        $otm_filter = 'secondary_title_id';
                        break;

                    case 'tertiary_title':
                        $otm_filter = 'tertiary_title_id';
                        break;
                }

                $placeholder = "{$otm_filter}=?";

                // Date has special format.
                if ($otm_filter === 'added_time') {

                    $placeholder = "date(added_time) = ?";
                }

                $sqls[] = <<<EOT
SELECT items.id as item_id
    FROM items
    WHERE {$placeholder}
EOT;

                $columns[] = $id;
            }
        }

        // Catalog cards use filter, but can't be combined with other filters.
        if (!empty($filters['catalog'])) {

            $sqls = [];

            $sqls[] = <<<EOT
SELECT id as item_id
    FROM items
    WHERE id > ? AND id < (? + {$this->app_settings->getGlobal('max_items')})
EOT;

            $columns = [$filters['catalog'], $filters['catalog']];
        }

        if ($collection === 'clipboard') {

            $sqls[] = <<<EOT
SELECT item_id
    FROM clipboard
    WHERE user_id = ?
EOT;

            $columns[] = $this->user_id;
        }

        if ($collection === 'project') {

            if($this->verifyProject($project_id) === false) {

                throw new Exception('you are not authorized to view this project', 403);
            }

            $project_sql = <<<EOT
SELECT project
    FROM projects
    WHERE id = ?
EOT;

            $this->db_main->run($project_sql, [$project_id]);
            $output['project'] = $this->db_main->getResult();

            $sqls[] = <<<EOT
SELECT item_id
    FROM projects_items
    WHERE project_id = ?
EOT;

            $columns[] = $project_id;
        }

        // Compound SQL statements.
        $compound_sql = join(' INTERSECT ', $sqls);

        // Add ordering and offset.
        $final_sql = <<<EOT
$compound_sql
    ORDER BY $order
    LIMIT ? OFFSET ?
EOT;

        if ($display_action === 'export' || is_array($display_action)) {

            $final_columns = array_merge($columns, [
                (integer) $this->app_settings->getGlobal('max_items'),
                0
            ]);

        } else {

            $final_columns = array_merge($columns, [
                $limit,
                $offset
            ]);
        }

        $this->db_main->beginTransaction();

        // Run the query.
        $this->db_main->run($final_sql, $final_columns);
        $rows = $this->db_main->getResultRows();

        if (empty($rows)) {

            $this->db_main->commit();

            // Add filters.
            $output['filters'] = $this->translateFilters($filters);

            return $output;
        }

        // Omnitool.
        if (is_array($display_action)) {

            $this->db_main->commit();

            $item_ids = array_column($rows, 'item_id');

            $this->omnitool($item_ids, $display_action);

            return [];
        }

        // Get item data.
        $output['items'] = $this->itemData($rows, $display_action);

        // Export. Send items, no counting necessary.
        if ($display_action === 'export') {

            $this->db_main->commit();
            return $output;
        }

        // Count.
        $count_total = <<<EOT
SELECT count(*)
    FROM (
        {$compound_sql}
    ) AS t
EOT;

        $this->db_main->run($count_total, $columns);
        $output['total_count'] = $this->db_main->getResult();

        // Add filters.
        $output['filters'] = $this->translateFilters($filters);

        $this->db_main->commit();

        return $output;
    }

    /**
     * Get human readable filters.
     *
     * @param array $filters
     * @return array
     */
    private function translateFilters(array $filters) {

        // Translate filters from ids to strings.
        $output = [];

        // Tags.
        if (!empty($filters['tag'])) {

            $sql = <<<EOT
SELECT tag
    FROM tags
    WHERE id = ?
EOT;

            foreach ($filters['tag'] as $id) {

                $this->db_main->run($sql, [$id]);

                $value = $this->db_main->getResult();

                if (!empty($value)) {

                    $output[] = [
                        'name'  => ['tag', 'tag'],
                        'value' => [$id, $value]
                    ];
                }
            }
        }

        // Authors.
        if (!empty($filters['author'])) {

            $sql = <<<EOT
SELECT last_name || CASE WHEN first_name = '' THEN '' ELSE ', ' || first_name END AS author_name
    FROM authors
    WHERE id = ?
EOT;

            foreach ($filters['author'] as $id) {

                $this->db_main->run($sql, [$id]);

                $value = $this->db_main->getResult();

                if (!empty($value)) {

                    $output[] = [
                        'name'  => ['author', 'author'],
                        'value' => [$id, $value]
                    ];
                }
            }
        }

        // Editors.
        if (!empty($filters['editor'])) {

            $sql = <<<EOT
SELECT last_name || CASE WHEN first_name = '' THEN '' ELSE ', ' || first_name END AS editor_name
    FROM editors
    WHERE id = ?
EOT;

            foreach ($filters['editor'] as $id) {

                $this->db_main->run($sql, [$id]);

                $value = $this->db_main->getResult();

                if (!empty($value)) {

                    $output[] = [
                        'name'  => ['editor', 'editor'],
                        'value' => [$id, $value]
                    ];
                }
            }
        }

        // Keywords.
        if (!empty($filters['keyword'])) {

            $sql = <<<EOT
SELECT keyword
    FROM keywords
    WHERE id = ?
EOT;

            foreach ($filters['keyword'] as $id) {

                $this->db_main->run($sql, [$id]);

                $value = $this->db_main->getResult();

                if (!empty($value)) {

                    $output[] = [
                        'name'  => ['keyword', 'keyword'],
                        'value' => [$id, $value]
                    ];
                }
            }
        }

        // Primary.
        if (!empty($filters['primary_title'])) {

            $sql = <<<EOT
SELECT primary_title
    FROM primary_titles
    WHERE id = ?
EOT;

            foreach ($filters['primary_title'] as $id) {

                $this->db_main->run($sql, [$id]);

                $value = $this->db_main->getResult();

                if (!empty($value)) {

                    $output[] = [
                        'name'  => ['primary_title', 'primary title'],
                        'value' => [$id, $value]
                    ];
                }
            }
        }

        // Secondary.
        if (!empty($filters['secondary_title'])) {

            $sql = <<<EOT
SELECT secondary_title
    FROM secondary_titles
    WHERE id = ?
EOT;

            foreach ($filters['secondary_title'] as $id) {

                $this->db_main->run($sql, [$id]);

                $value = $this->db_main->getResult();

                if (!empty($value)) {

                    $output[] = [
                        'name'  => ['secondary_title', 'secondary title'],
                        'value' => [$id, $value]
                    ];
                }
            }
        }

        // Tertiary.
        if (!empty($filters['tertiary_title'])) {

            $sql = <<<EOT
SELECT tertiary_title
    FROM tertiary_titles
    WHERE id = ?
EOT;

            foreach ($filters['tertiary_title'] as $id) {

                $this->db_main->run($sql, [$id]);

                $value = $this->db_main->getResult();

                if (!empty($value)) {

                    $output[] = [
                        'name'  => ['tertiary_title', 'tertiary title'],
                        'value' => [$id, $value]
                    ];
                }
            }
        }

        // Added date.
        if (!empty($filters['added_time'])) {

            foreach ($filters['added_time'] as $id) {

                $output[] = [
                    'name'  => ['added_time', 'added'],
                    'value' => [$id, strtotime($id)]
                ];
            }
        }

        // Custom.
        for ($i = 1; $i <= 8; $i++) {

            if (!empty($filters['custom' . $i])) {

                foreach ($filters['custom' . $i] as $id) {

                    $output[] = [
                        'name'  => ['custom' . $i, 'custom' . $i],
                        'value' => [$id, $id]
                    ];
                }
            }
        }

        // Misc.
        if (!empty($filters['misc'])) {

            foreach ($filters['misc'] as $id) {

                switch ($id) {

                    case 'nopdf':
                        $value = 'no PDF';
                        break;

                    case 'myitems':
                        $value = 'added by me';
                        break;

                    case 'othersitems':
                        $value = 'added by others';
                        break;

                    default:
                        $value = '';
                }

                $output[] = [
                    'name'  => ['misc', 'miscellaneous'],
                    'value' => [$id, $value]
                ];
            }
        }

        return $output;
    }

    /**
     * Search router.
     *
     * @param array $search
     * @param string $collection
     * @param int $limit
     * @param int $offset
     * @param string|array|null $display_action
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    protected function _search(
        array $search,
        string $collection,
        int $limit = 10,
        int $offset = 0,
        $display_action = 'title',
        int $project_id = null
    ) {

        // Item ID search.
        if ($search['search_type'][0] === 'itemid') {

            $item_ids = explode(' ', $search['search_query'][0]);
            return $this->searchIds($item_ids, $collection, $limit, $offset, $display_action, $project_id);
        }

        // Item note search.
        if ($search['search_type'][0] === 'pdfnotes') {

            return $this->searchPdfnotes($search, $collection, $limit, $offset, $display_action, $project_id);
        }

        // Item note search.
        if ($search['search_type'][0] === 'itemnotes') {

            return $this->searchItemnotes($search, $collection, $limit, $offset, $display_action, $project_id);
        }

        // Quicksearch.
        if ($search['search_type'][0] === 'metadata' || $search['search_type'][0] === 'anywhere') {

            return $this->searchMetadata($search, $collection, $limit, $offset, $display_action, $project_id);
        }

        // Advanced search.
        return $this->searchColumns($search, $collection, $limit, $offset, $display_action, $project_id);
    }

    /**
     * @param array $search
     * @param string $collection
     * @param int $limit
     * @param int $offset
     * @param string|array|null $display_action
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    private function searchMetadata(
        array $search,
        string $collection,
        int $limit = 10,
        int $offset = 0,
        $display_action = 'title',
        int $project_id = null
    ) {

        /** @var FileCache $cache */
        $cache = $this->di->get('FileCache');

        $output = [
            'items' => [],
            'total_count' => 0
        ];

        $fields = [
            'ind_items.abstract_index',
            'ind_items.affiliation_index',
            'ind_items.authors_index',
            'ind_items.editors_index',
            'ind_items.keywords_index',
            'ind_items.primary_title_index',
            'ind_items.secondary_title_index',
            'ind_items.tertiary_title_index',
            'ind_items.title_index',
            'ind_items.custom1_index',
            'ind_items.custom2_index',
            'ind_items.custom3_index',
            'ind_items.custom4_index',
            'ind_items.custom5_index',
            'ind_items.custom6_index',
            'ind_items.custom7_index',
            'ind_items.custom8_index',
            'ind_items.full_text_index'
        ];

        // If metadata, remove PDF search.
        if ($search['search_type'][0] === 'metadata') {

            array_pop($fields);
        }

        /** @var Sanitation $sanitation */
        $sanitation = $this->di->getShared('Sanitation');
        $query = $sanitation->queryLike($search['search_query'][0]);

        $this->scalar_utils = $this->di->getShared('ScalarUtils');
        $query = $this->scalar_utils->deaccent($query, false);

        $placeholder = '';
        $columns = [];
        $join_collection = '';
        $where_collection = '';

        // Modify query based on boolean type.
        switch ($search['search_boolean'][0]) {

            case 'AND':
            case 'OR':

                $placeholders = [];
                $query_parts = array_filter(explode(' ', $query));

                foreach ($query_parts as $term) {

                    $field_placeholders = [];

                    foreach ($fields as $field) {

                        $field_placeholders[] = "{$field} LIKE ? ESCAPE '\'";
                        $columns[] = "% {$term}%";
                    }

                    $placeholders[] = '(' . join(' OR ', $field_placeholders) . ')';
                }

                $placeholder = join(" {$search['search_boolean'][0]} ", $placeholders);
                break;

            case 'PHRASE':

                $field_placeholders = [];

                foreach ($fields as $field) {

                    $field_placeholders[] = "{$field} LIKE ? ESCAPE '\'";
                    $columns[] = "% {$query}%";
                }

                $placeholder = '(' . join(' OR ', $field_placeholders) . ')';
                break;
        }

        $this->db_main->beginTransaction();

        if ($collection === 'clipboard') {

            $join_collection = 'INNER JOIN clipboard ON ind_items.id=clipboard.item_id';
            $where_collection = 'AND clipboard.user_id = ?';

            $columns[] = $this->user_id;

        } elseif ($collection === 'project' and is_int($project_id) === true) {

            if ($this->verifyProject($project_id) === false) {

                $this->db_main->rollBack();
                throw new Exception('you are not authorized to access this project', 403);
            }

            // Get project title.
            $sql = <<<EOT
SELECT project
    FROM projects
    WHERE id = ?
EOT;

            $this->db_main->run($sql, [$project_id]);
            $output['project'] = $this->db_main->getResult();

            $join_collection = 'INNER JOIN projects_items ON ind_items.id=projects_items.item_id';
            $where_collection = 'AND projects_items.project_id = ?';

            $columns[] = $project_id;
        }

        $sql = <<<EOT
SELECT
    id as item_id
    FROM ind_items
    {$join_collection}
    WHERE {$placeholder}
    {$where_collection}
    ORDER BY id DESC
    LIMIT ?
EOT;


        $columns[] = (integer) $this->app_settings->getGlobal('max_items');

        // Try to get records from Cache.
        $cache->context('searches');

        $key = $cache->key(
            __METHOD__
            . serialize([$search, $collection, $project_id])
        );

        // Debug.
//        $cache->delete($key);

        // Last modified.
        $sql_modified = <<<SQL
SELECT changed_time
    FROM stats
    WHERE table_name = 'ind_items' 
SQL;

        $this->db_main->run($sql_modified);
        $modified = strtotime($this->db_main->getResult());

        // Get items from cache.
        $rows = $cache->get($key, $modified);

        // Cache is empty.
        if (empty($rows)) {

            // Execute search.
            $this->db_main->run($sql, $columns);
            $rows = $this->db_main->getResultRows();

            // Save to cache.
            $cache->set($key, $rows, $modified);
        }

        if (empty($rows)) {

            return $output;
        }

        // Omnitool.
        if (is_array($display_action)) {

            $this->db_main->commit();

            $item_ids = array_column($rows, 'item_id');

            $this->omnitool($item_ids, $display_action);

            return [];
        }

        // Count.
        $output['total_count'] = count($rows);

        if ($display_action !== 'export') {

            // Paging.
            $rows = array_slice($rows, $offset, $limit);
        }

        // Get item data.
        $output['items'] = $this->itemData($rows, $display_action);

        $this->db_main->commit();

        $rows = null;

        return $output;
    }

    /**
     * @param array $search
     * @param string $collection
     * @param int $limit
     * @param int $offset
     * @param string|array|null $display_action
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    private function searchPdfnotes(
        array $search,
        string $collection,
        int $limit = 10,
        int $offset = 0,
        $display_action = 'title',
        int $project_id = null
    ) {

        $output = [
            'items' => [],
            'total_count' => 0
        ];

        /** @var Sanitation $sanitation */
        $sanitation = $this->di->getShared('Sanitation');
        $query = $sanitation->queryLike($search['search_query'][0]);

        $placeholder = '';
        $columns = [];
        $columns_count = [];
        $join_collection = '';
        $where_collection = '';

        // Modify query based on boolean type.
        switch ($search['search_boolean'][0]) {

            case 'AND':
            case 'OR':

                $placeholders = [];
                $query_parts = array_filter(explode(' ', $query));

                foreach ($query_parts as $term) {

                    $placeholders[] = "annotations.annotation LIKE ? ESCAPE '\'";
                    $columns[] = "%{$term}%";
                    $columns_count[] = "%{$term}%";
                }

                $placeholder = join(" {$search['search_boolean'][0]} ", $placeholders);
                break;

            case 'PHRASE':

                $placeholder = "annotations.annotation LIKE ? ESCAPE '\'";
                $columns[] = "%{$query}%";
                $columns_count[] = "%{$query}%";
                break;
        }

        $this->db_main->beginTransaction();

        if ($collection === 'clipboard') {

            $join_collection = 'INNER JOIN clipboard ON items.id=clipboard.item_id';
            $where_collection = 'AND clipboard.user_id = ?';

            $columns[] = $this->user_id;

        } elseif ($collection === 'project' and is_int($project_id) === true) {

            if ($this->verifyProject($project_id) === false) {

                $this->db_main->rollBack();
                throw new Exception('you are not authorized to access this project', 403);
            }

            // Get project title.
            $sql = <<<EOT
SELECT project
    FROM projects
    WHERE id = ?
EOT;

            $this->db_main->run($sql, [$project_id]);
            $output['project'] = $this->db_main->getResult();

            $join_collection = 'INNER JOIN projects_items ON items.id=projects_items.item_id';
            $where_collection = 'AND projects_items.project_id = ?';

            $columns[] = $project_id;
        }

        $sql = <<<EOT
SELECT
    DISTINCT item_id
    FROM annotations
    INNER JOIN items ON annotations.item_id = items.id
    {$join_collection}
    WHERE {$placeholder}
    {$where_collection}
    ORDER BY item_id DESC
    LIMIT ? OFFSET ?
EOT;

        if ($display_action === 'export' || is_array($display_action)) {

            $columns[] = (integer) $this->app_settings->getGlobal('max_items');
            $columns[] = 0;

        } else {

            $columns[] = $limit;
            $columns[] = $offset;
        }

        // Run the query.
        $this->db_main->run($sql, $columns);
        $rows = $this->db_main->getResultRows();

        if (empty($rows)) {

            return $output;
        }

        // Omnitool.
        if (is_array($display_action)) {

            $this->db_main->commit();

            $item_ids = array_column($rows, 'item_id');

            $this->omnitool($item_ids, $display_action);

            return [];
        }

        // Get item data.
        $output['items'] = $this->itemData($rows, $display_action);

        // Export. No need to count.
        if ($display_action === 'export') {

            $this->db_main->commit();
            return $output;
        }

        // Count.
        $count_total = <<<EOT
SELECT
    count(DISTINCT item_id)
    FROM annotations
    INNER JOIN items ON items.id=annotations.item_id
    {$join_collection}
    WHERE {$placeholder}
    {$where_collection}
EOT;

        if ($collection === 'clipboard') {

            $columns_count[] = $this->user_id;

        } elseif ($collection === 'project') {

            $columns_count[] = $project_id;
        }

        $this->db_main->run($count_total, $columns_count);
        $output['total_count'] = $this->db_main->getResult();

        $this->db_main->commit();

        return $output;
    }

    /**
     * @param array $search
     * @param string $collection
     * @param int $limit
     * @param int $offset
     * @param string|array|null $display_action
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    private function searchItemnotes(
        array $search,
        string $collection,
        int $limit = 10,
        int $offset = 0,
        $display_action = 'title',
        int $project_id = null
    ) {

        $output = [
            'items' => [],
            'total_count' => 0
        ];

        /** @var Sanitation $sanitation */
        $sanitation = $this->di->getShared('Sanitation');
        $query = $sanitation->queryLike($search['search_query'][0]);

        $placeholder = '';
        $columns = [];
        $columns_count = [];
        $join_collection = '';
        $where_collection = '';

        // Modify query based on boolean type.
        switch ($search['search_boolean'][0]) {

            case 'AND':
            case 'OR':

                $placeholders = [];
                $query_parts = array_filter(explode(' ', $query));

                foreach ($query_parts as $term) {

                    $placeholders[] = "striptags(item_notes.note) LIKE ? ESCAPE '\'";
                    $columns[] = "%{$term}%";
                    $columns_count[] = "%{$term}%";
                }

                $placeholder = join(" {$search['search_boolean'][0]} ", $placeholders);
                break;

            case 'PHRASE':

                $placeholder = "striptags(item_notes.note) LIKE ? ESCAPE '\'";
                $columns[] = "%{$query}%";
                $columns_count[] = "%{$query}%";
                break;
        }

        $this->db_main->beginTransaction();

        if ($collection === 'clipboard') {

            $join_collection = 'INNER JOIN clipboard ON items.id=clipboard.item_id';
            $where_collection = 'AND clipboard.user_id = ?';

            $columns[] = $this->user_id;

        } elseif ($collection === 'project' and is_int($project_id) === true) {

            if ($this->verifyProject($project_id) === false) {

                $this->db_main->rollBack();
                throw new Exception('you are not authorized to access this project', 403);
            }

            // Get project title.
            $sql = <<<EOT
SELECT project
    FROM projects
    WHERE id = ?
EOT;

            $this->db_main->run($sql, [$project_id]);
            $output['project'] = $this->db_main->getResult();

            $join_collection = 'INNER JOIN projects_items ON items.id=projects_items.item_id';
            $where_collection = 'AND projects_items.project_id = ?';

            $columns[] = $project_id;
        }

        $sql = <<<EOT
SELECT
    DISTINCT item_id
    FROM item_notes
    INNER JOIN items ON item_notes.item_id = items.id
    {$join_collection}
    WHERE {$placeholder}
    {$where_collection}
    ORDER BY item_id DESC
    LIMIT ? OFFSET ?
EOT;

        if ($display_action === 'export' || is_array($display_action)) {

            $columns[] = (integer) $this->app_settings->getGlobal('max_items');
            $columns[] = 0;

        } else {

            $columns[] = $limit;
            $columns[] = $offset;
        }

        // Run the query.
        $this->db_main->run($sql, $columns);
        $rows = $this->db_main->getResultRows();

        if (empty($rows)) {

            return $output;
        }

        // Omnitool.
        if (is_array($display_action)) {

            $this->db_main->commit();

            $item_ids = array_column($rows, 'item_id');

            $this->omnitool($item_ids, $display_action);

            return [];
        }

        // Get item data.
        $output['items'] = $this->itemData($rows, $display_action);

        // Export. No need to count.
        if ($display_action === 'export') {

            $this->db_main->commit();
            return $output;
        }

        // Count.
        $count_total = <<<EOT
SELECT
    count(DISTINCT item_id)
    FROM item_notes
    INNER JOIN items ON items.id=item_notes.item_id
    {$join_collection}
    WHERE {$placeholder}
    {$where_collection}
EOT;

        if ($collection === 'clipboard') {

            $columns_count[] = $this->user_id;

        } elseif ($collection === 'project') {

            $columns_count[] = $project_id;
        }

        $this->db_main->run($count_total, $columns_count);
        $output['total_count'] = $this->db_main->getResult();

        $this->db_main->commit();

        return $output;
    }

    /**
     * @param array $search
     * @param string $collection
     * @param int $limit
     * @param int $offset
     * @param string $display_action
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    private function searchColumns(
        array $search,
        string $collection,
        int $limit = 10,
        int $offset = 0,
        $display_action = 'title',
        int $project_id = null
    ) {

        $output = [
            'items' => [],
            'total_count' => 0
        ];

        $fields = [
            'AB' => ['ind_items.title_index','ind_items.abstract_index'],
            'AF' => 'ind_items.affiliation_index',
            'AU' => ['ind_items.authors_index','ind_items.editors_index'],
            'KW' => 'ind_items.keywords_index',
            'JO' => 'ind_items.primary_title_index',
            'T1' => 'ind_items.primary_title_index',
            'T2' => 'ind_items.secondary_title_index',
            'T3' => 'ind_items.tertiary_title_index',
            'TI' => 'ind_items.title_index',
            'C1' => 'ind_items.custom1_index',
            'C2' => 'ind_items.custom2_index',
            'C3' => 'ind_items.custom3_index',
            'C4' => 'ind_items.custom4_index',
            'C5' => 'ind_items.custom5_index',
            'C6' => 'ind_items.custom6_index',
            'C7' => 'ind_items.custom7_index',
            'C8' => 'ind_items.custom8_index',
            'FT' => 'ind_items.full_text_index'
        ];

        /** @var Sanitation $sanitation */
        $sanitation = $this->di->getShared('Sanitation');
        $this->scalar_utils = $this->di->getShared('ScalarUtils');

        /** @var FileCache $cache */
        $cache = $this->di->get('FileCache');

        $placeholder = '';
        $columns = [];
        $join_collection = '';
        $where_collection = '';

        foreach ($search['search_query'] as $key => $search_query) {

            if (empty($search_query)) {

                continue;
            }

            $search_query = $sanitation->queryLike($search_query);
            $search_query = $this->scalar_utils->deaccent($search_query, false);

            // Advanced search glue: AND/OR/NOT.
            if (isset($search['search_glue'][$key]) && in_array($search['search_glue'][$key], ['AND', 'OR', 'NOT']) === false) {

                throw new Exception('invalid search parameters', 422);
            }

            if (isset($search['search_glue'][$key])) {

                $search['search_glue'][$key] =  $search['search_glue'][$key] === 'NOT' ? 'AND NOT' :  $search['search_glue'][$key];
            }

            // Glue.
            $placeholder .= $placeholder !== '' && isset($search['search_glue'][$key]) ? " {$search['search_glue'][$key]} " : '';

            // Modify query based on boolean type.
            switch ($search['search_boolean'][$key]) {

                case 'AND':
                case 'OR':

                    $placeholders = [];
                    $query_parts = array_filter(explode(' ', $search_query));

                    foreach ($query_parts as $term) {

                        // Placeholder.
                        $field = $fields[$search['search_type'][$key]];

                        if (is_array($field)) {

                            $placeholder_parts = [];

                            foreach ($field as $field_part) {

                                $placeholder_parts[] = "{$field_part} LIKE ? ESCAPE '\'";
                                $columns[] = "% {$term}%";
                            }

                            $placeholders[] = '(' . join(' OR ', $placeholder_parts) . ')';

                        } else {

                            $placeholders[] = "{$field} LIKE ? ESCAPE '\'";
                            $columns[] = "% {$term}%";
                        }
                    }

                    $placeholder .= join(" {$search['search_boolean'][$key]} ", $placeholders);

                    break;

                case 'PHRASE':

                    // Placeholder.
                    $field = $fields[$search['search_type'][$key]];

                    if (is_array($field)) {

                        $placeholder_parts = [];

                        foreach ($field as $field_part) {

                            $placeholder_parts[] = "{$field_part} LIKE ? ESCAPE '\'";
                            $columns[] = "% {$search_query}%";
                        }

                        $placeholder .= '(' . join(' OR ', $placeholder_parts) . ')';
                        $columns[] = "% {$search_query}%";

                    } else {

                        $placeholder .= "{$field} LIKE ? ESCAPE '\'";
                        $columns[] = "% {$search_query}%";
                    }

                    break;
            }
        }

        $this->db_main->beginTransaction();

        if ($collection === 'clipboard') {

            $join_collection = 'INNER JOIN clipboard ON ind_items.id=clipboard.item_id';
            $where_collection = 'AND clipboard.user_id = ?';

            $columns[] = $this->user_id;

        } elseif ($collection === 'project' and is_int($project_id) === true) {

            if ($this->verifyProject($project_id) === false) {

                $this->db_main->rollBack();
                throw new Exception('you are not authorized to access this project', 403);
            }

            // Get project title.
            $sql = <<<EOT
SELECT project
    FROM projects
    WHERE id = ?
EOT;

            $this->db_main->run($sql, [$project_id]);
            $output['project'] = $this->db_main->getResult();

            $join_collection = 'INNER JOIN projects_items ON ind_items.id=projects_items.item_id';
            $where_collection = 'AND projects_items.project_id = ?';

            $columns[] = $project_id;
        }

        $sql = <<<EOT
SELECT
    id as item_id
    FROM ind_items
    {$join_collection}
    WHERE {$placeholder}
    {$where_collection}
    ORDER BY id DESC
    LIMIT ?
EOT;

        $columns[] = (integer) $this->app_settings->getGlobal('max_items');

        // Try to get records from Cache.
        $cache->context('searches');

        $key = $cache->key(
            __METHOD__
            . serialize([$search, $collection, $project_id])
        );

        // Debug.
//        $cache->delete($key);

        // Last modified.
        $sql_modified = <<<SQL
SELECT changed_time
    FROM stats
    WHERE table_name = 'ind_items' 
SQL;

        $this->db_main->run($sql_modified);
        $modified = strtotime($this->db_main->getResult());

        // Get items from cache.
        $rows = $cache->get($key, $modified);

        // Cache is empty.
        if (empty($rows)) {

            // Execute search.
            $this->db_main->run($sql, $columns);
            $rows = $this->db_main->getResultRows();

            // Save to cache.
            $cache->set($key, $rows, $modified);
        }

        if (empty($rows)) {

            return $output;
        }

        // Omnitool.
        if (is_array($display_action)) {

            $this->db_main->commit();

            $item_ids = array_column($rows, 'item_id');

            $this->omnitool($item_ids, $display_action);

            return [];
        }

        // Count.
        $output['total_count'] = count($rows);

        if ($display_action !== 'export') {

            // Paging.
            $rows = array_slice($rows, $offset, $limit);
        }

        // Get item data.
        $output['items'] = $this->itemData($rows, $display_action);

        $this->db_main->commit();

        $rows = null;

        return $output;
    }

    /**
     * Search item IDs.
     *
     * @param array $item_ids
     * @param string $collection
     * @param int $limit
     * @param int $offset
     * @param string $display_action
     * @param int|null $project_id
     * @return array
     * @throws Exception
     */
    private function searchIds(
        array $item_ids,
        string $collection,
        int $limit = 10,
        int $offset = 0,
        $display_action = 'title',
        int $project_id = null
    ) {

        $output = [
            'items' => [],
            'total_count' => 0
        ];

        $join_collection = '';
        $where_collection = '';

        // Search columns.
        $columns = $item_ids;

        $placeholder_arr = array_fill(0, count($item_ids), '?');
        $placeholders = join(', ', $placeholder_arr);

        $this->db_main->beginTransaction();

        if ($collection === 'clipboard') {

            $join_collection = 'INNER JOIN clipboard ON items.id=clipboard.item_id';
            $where_collection = 'AND clipboard.user_id = ?';

            $columns[] = $this->user_id;

        } elseif ($collection === 'project' and is_int($project_id) === true) {

            if ($this->verifyProject($project_id) === false) {

                $this->db_main->rollBack();
                throw new Exception('you are not authorized to access this project', 403);
            }

            // Get project title.
            $sql = <<<EOT
SELECT project
    FROM projects
    WHERE id = ?
EOT;

            $this->db_main->run($sql, [$project_id]);
            $output['project'] = $this->db_main->getResult();

            $join_collection = 'INNER JOIN projects_items ON items.id=projects_items.item_id';
            $where_collection = 'AND projects_items.project_id = ?';

            $columns[] = $project_id;
        }

        $sql = <<<EOT
SELECT
    id as item_id
    FROM items
    {$join_collection}
    WHERE id IN ({$placeholders})
    {$where_collection}
    ORDER BY item_id DESC
    LIMIT ? OFFSET ?
EOT;

        // Limit, offset.
        if ($display_action === 'export' || is_array($display_action)) {

            // Export and omnitool have max item limit.
            $columns[] = (integer) $this->app_settings->getGlobal('max_items');
            $columns[] = 0;

        } else {

            // Search is paged.
            $columns[] = $limit;
            $columns[] = $offset;
        }

        // Run the search query.
        $this->db_main->run($sql, $columns);
        $rows = $this->db_main->getResultRows();

        if (empty($rows)) {

            return $output;
        }

        // Omnitool.
        if (is_array($display_action)) {

            // End transaction.
            $this->db_main->commit();

            $item_ids = array_column($rows, 'item_id');

            // Peform omnitool actions.
            $this->omnitool($item_ids, $display_action);

            return [];
        }

        // Add item data for display, or export.
        $output['items'] = $this->itemData($rows, $display_action);

        // Export. No need to count.
        if ($display_action === 'export') {

            // End transaction.
            $this->db_main->commit();
            return $output;
        }

        // Count.

        $sql_count = <<<EOT
SELECT count(*)
    FROM items
    {$join_collection}
    WHERE id IN ({$placeholders})
    {$where_collection}
EOT;

        $columns_count = $item_ids;

        if ($collection === 'clipboard') {

            $columns_count[] = $this->user_id;

        } elseif ($collection === 'project') {

            $columns_count[] = $project_id;
        }

        $this->db_main->run($sql_count, $columns_count);
        $output['total_count'] = $this->db_main->getResult();

        $this->db_main->commit();

        return $output;
    }

    /**
     * @param array|int $items
     * @return array
     * @throws Exception
     */
    protected function _clipboardAdd($items): array {

        $output = [];

        // Cast to array.
        if (is_array($items) === false) {

            $items = (array) $items;
        }

        $this->db_main->beginTransaction();

        // Insert into clipboard.
        $sql = <<<EOT
INSERT OR IGNORE
    INTO clipboard
    (user_id, item_id)
    VALUES(?, ?)
EOT;

        foreach ($items as $item_id) {

            $columns = [
                $this->user_id,
                $item_id
            ];

            $this->db_main->run($sql, $columns);
        }

        // Delete supernumerary items.
        $sql = <<<EOT
DELETE FROM clipboard
    WHERE user_id = ? AND item_id IN (
        SELECT item_id
            FROM clipboard
            WHERE user_id = ? ORDER BY item_id DESC LIMIT -1 OFFSET ?
    )
EOT;

        $columns = [
            $this->user_id,
            $this->user_id,
            $this->app_settings->getGlobal('max_items')
        ];

        $this->db_main->run($sql, $columns);

        $output['max_count'] = $this->db_main->getPDOStatement()->rowCount() > 0;

        $this->db_main->commit();

        return $output;
    }

    /**
     * Delete item from clipboard.
     *
     * @param int|array $items
     */
    protected function _clipboardDelete($items) {

        // Cast to array.
        if (is_array($items) === false) {

            $items = (array) $items;
        }

        $this->db_main->beginTransaction();

        $sql = <<<EOT
DELETE
    FROM clipboard
    WHERE user_id = ? AND item_id = ?
EOT;

        foreach ($items as $item_id) {

            $columns = [
                $this->user_id,
                $item_id
            ];

            $this->db_main->run($sql, $columns);
        }

        $this->db_main->commit();
    }

    /**
     * Add items to a project.
     *
     * @param array $item_ids
     * @param int $project_id
     * @return array
     * @throws Exception
     */
    protected function _projectAdd(array $item_ids, int $project_id): array {

        $output = [];

        $this->db_main->beginTransaction();

        if ($this->verifyProject($project_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to edit this project', 403);
        }

        // Insert into project.
        $sql = <<<EOT
INSERT OR IGNORE
    INTO projects_items
    (project_id, item_id)
    VALUES(?, ?)
EOT;

        foreach ($item_ids as $item_id) {

            $columns = [
                $project_id,
                $item_id
            ];

            $this->db_main->run($sql, $columns);
        }

        // Delete supernumerary items.
        $sql = <<<EOT
DELETE FROM projects_items
    WHERE project_id = ? AND item_id IN (
        SELECT item_id
            FROM projects_items
            WHERE project_id = ? ORDER BY item_id DESC LIMIT -1 OFFSET ?
    )
EOT;

        $columns = [
            $project_id,
            $project_id,
            $this->app_settings->getGlobal('max_items')
        ];

        $this->db_main->run($sql, $columns);

        $output['max_count'] = $this->db_main->getPDOStatement()->rowCount() > 0;

        $this->db_main->commit();

        return $output;
    }

    /**
     * Delete items from a project.
     *
     * @param array $item_ids
     * @param int $project_id
     * @throws Exception
     */
    protected function _projectDelete(array $item_ids, int $project_id): void {

        $this->db_main->beginTransaction();

        if ($this->verifyProject($project_id) === false) {

            $this->db_main->rollBack();
            throw new Exception('you are not authorized to edit this project', 403);
        }

        $sql = <<<EOT
DELETE
    FROM projects_items
    WHERE project_id = ? AND item_id = ?
EOT;

        foreach ($item_ids as $item_id) {

            $columns = [
                $project_id,
                $item_id
            ];

            $this->db_main->run($sql, $columns);
        }

        $this->db_main->commit();
    }

    /**
     * Tag a list of items. Omnitool.
     *
     * @param int|array $items
     * @param int|array $tags
     */
    protected function _tag($items, $tags): void {

        // Cast to array.
        if (is_array($items) === false) {

            $items = (array) $items;
        }

        if (is_array($tags) === false) {

            $tags = (array) $tags;
        }

        $this->db_main->beginTransaction();

        // Insert into clipboard.
        $sql = <<<EOT
INSERT OR IGNORE
    INTO items_tags
    (item_id, tag_id)
    VALUES(?, ?)
EOT;

        foreach ($items as $item_id) {

            foreach ($tags as $tag_id) {

                $columns = [
                    $item_id,
                    $tag_id
                ];

                $this->db_main->run($sql, $columns);
            }
        }

        $this->db_main->commit();
    }

    /**
     * Untag a list of items. Omnitool.
     *
     * @param int|array $items
     * @param int|array $tags
     */
    protected function _untag($items, $tags): void {

        // Cast to array.
        if (is_array($items) === false) {

            $items = (array) $items;
        }

        if (is_array($tags) === false) {

            $tags = (array) $tags;
        }

        $this->db_main->beginTransaction();

        $sql = <<<EOT
DELETE FROM items_tags
    WHERE item_id = ? and  tag_id = ?
EOT;

        foreach ($items as $item_id) {

            foreach ($tags as $tag_id) {

                $columns = [
                    $item_id,
                    $tag_id
                ];

                $this->db_main->run($sql, $columns);
            }
        }

        $this->db_main->commit();
    }

    /**
     * Get SQL for result ordering.
     *
     * @param  string $orderby
     * @return string
     * @throws Exception
     */
    private function orderByColumn(string $orderby) {

        switch ($orderby) {

            case 'id':
                return 'item_id DESC';

            case 'pubdate':
                return 'publication_date DESC';

            case 'title':
                return 'title COLLATE utf8Collation';

            default:
                throw new Exception('unknown sorting type', 500);
        }
    }

    /**
     * Get data required for the required display format.
     *
     * @param array $item_rows Array of item ids and other data.
     * @param string $display_action title|summary|icon|export
     * @return array
     * @throws Exception
     */
    private function itemData(array $item_rows, string $display_action): array {

        if (count($item_rows) === 0) {

            return [];
        }

        $item_ids = array_column($item_rows, 'item_id');

        // Item placeholder.
        $item_placeholders = array_fill(0, count($item_ids), '?');
        $item_placeholder  = join(',', $item_placeholders);

        // Keep item ordering.
        $item_ordering = '';

        foreach ($item_ids as $key => $item_id) {

            $item_id = (int) $item_id;
            $key = (int) $key;

            $item_ordering .= "WHEN {$item_id} THEN {$key}" . PHP_EOL;
        }

        if ($display_action === 'title' || $display_action === 'icon') {

            $sql = <<<EOT
SELECT items.id, items.title, items.file_hash
    FROM items
    WHERE items.id IN ($item_placeholder)
    ORDER BY CASE items.id
        $item_ordering
    END
EOT;

            $this->db_main->run($sql, $item_ids);

            $i = 0;
            $output = [];

            while ($row = $this->db_main->getResultRow()) {

                // Add row to the items array.
                $output[] = [
                    'id'      => $row['id'],
                    'title'   => $row['title'],
                    'has_pdf' => !empty($row['file_hash']),
                    'snippet' => $item_rows[$i]['snippet'] ?? ''
                ];

                $i++;
            }

            if ( $display_action == "title" ) {
                for ($idx = 0; $idx < $i; $idx++) {
                    $item_id = $output[$idx]['id'];
 
                    // Query Authors.
                    $sql = <<<EOT
SELECT
    authors.id, CASE WHEN first_name is NULL THEN last_name ELSE last_name || ', ' || first_name END AS author
    FROM authors
    INNER JOIN items_authors ON items_authors.author_id=authors.id
    WHERE items_authors.item_id=?
    ORDER by items_authors.position
EOT;
        
                    $this->db_main->run($sql, [$item_id]);
                    $authors = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);
                    if (!empty($authors)) {
                        $output[$idx]['authors'] = $authors;
                    }   
                }
            }


            // Add clipboard flags.
            $output = $this->clipboardFlags($output);

            // Add project flags.
            $output = $this->addProjects($output);

            return $output;

        } elseif ($display_action === 'summary') {

            $sql = <<<EOT
SELECT items.id, items.title, items.abstract, items.file_hash
    FROM items
    WHERE items.id IN ($item_placeholder)
    ORDER BY CASE items.id
        $item_ordering
    END
EOT;

            $this->db_main->run($sql, $item_ids);

            $i = 0;
            $output = [];

            while ($row = $this->db_main->getResultRow()) {
                // Add row to the items array.
                $output[] = [
                    'id'       => $row['id'],
                    'title'    => $row['title'],
                    'abstract' => $row['abstract'],
                    'has_pdf'  => !empty($row['file_hash']),
                    'snippet' => $item_rows[$i]['snippet'] ?? ''
                ];

                $i++;
            }
            for ($idx = 0; $idx < $i; $idx++) {
                $item_id = $output[$idx]['id'];
 
                // Query Authors.
                $sql = <<<EOT
SELECT
    authors.id, CASE WHEN first_name is NULL THEN last_name ELSE last_name || ', ' || first_name END AS author
    FROM authors
    INNER JOIN items_authors ON items_authors.author_id=authors.id
    WHERE items_authors.item_id=?
    ORDER by items_authors.position
EOT;

                $this->db_main->run($sql, [$item_id]);
                $authors = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);
                if (!empty($authors)) {
                    $output[$idx]['authors'] = $authors;
                }       
            }

            // Add clipboard flags.
            $output = $this->clipboardFlags($output);

            // Add project flags.
            $output = $this->addProjects($output);

            // Add notes.
            $output = $this->addNotes($output);

            return $output;

        } elseif ($display_action === 'rss') {

            $sql = <<<EOT
SELECT items.id, items.title, items.abstract, items.added_time
    FROM items
    WHERE items.id IN ($item_placeholder)
    ORDER BY CASE items.id
        $item_ordering
    END
EOT;

            $this->db_main->run($sql, $item_ids);

            $i = 0;
            $output = [];

            while ($row = $this->db_main->getResultRow()) {

                // Add row to the items array.
                $output[] = [
                    'id'         => $row['id'],
                    'title'      => $row['title'],
                    'abstract'   => $row['abstract'],
                    'added_time' => $row['added_time']
                ];

                $i++;
            }

            return $output;

        } elseif ($display_action === 'export') {

            $sql = <<<EOT
SELECT
    items.id,
    items.title,
    items.abstract,
    items.publication_date,
    items.reference_type,
    items.affiliation,
    items.urls,
    items.volume,
    items.issue,
    items.pages,
    items.publisher,
    items.place_published,
    items.custom1,
    items.custom2,
    items.custom3,
    items.custom4,
    items.custom5,
    items.custom6,
    items.custom7,
    items.custom8,
    items.bibtex_id,
    items.bibtex_type,
    items.file_hash,
    primary_titles.primary_title,
    secondary_titles.secondary_title,
    tertiary_titles.tertiary_title
    FROM items
    LEFT JOIN primary_titles ON primary_titles.id=items.primary_title_id
    LEFT JOIN secondary_titles ON secondary_titles.id=items.secondary_title_id
    LEFT JOIN tertiary_titles ON tertiary_titles.id=items.tertiary_title_id
    WHERE items.id IN ({$item_placeholder})
    ORDER BY CASE items.id
        {$item_ordering}
    END
EOT;

            $this->db_main->run($sql, $item_ids);
            $output = $this->db_main->getResultRows();

            $count = count($output);

            // UIDs.
            $sql_uids = <<<EOT
SELECT
    uid_type, uid
    FROM uids
    WHERE item_id = ?
EOT;

            $sql_authors = <<<EOT
SELECT last_name, first_name
    FROM authors
    INNER JOIN items_authors ON authors.id = items_authors.author_id
    WHERE items_authors.item_id = ?
    ORDER BY items_authors.position
EOT;

            $sql_editors = <<<EOT
SELECT last_name, first_name
    FROM editors
    INNER JOIN items_editors ON editors.id = items_editors.editor_id
    WHERE items_editors.item_id = ?
    ORDER BY items_editors.position
EOT;

            $sql_keywords = <<<EOT
SELECT keyword
    FROM keywords
    INNER JOIN items_keywords ON keywords.id = items_keywords.keyword_id
    WHERE items_keywords.item_id = ?
EOT;

            for ($i = 0; $i < $count; $i++) {

                // UIDs.
                $this->db_main->run($sql_uids, [$output[$i]['id']]);

                $output[$i][ItemMeta::COLUMN['UID_TYPES']] = [];
                $output[$i][ItemMeta::COLUMN['UIDS']] = [];

                while ($uids = $this->db_main->getResultRow()) {

                    $output[$i][ItemMeta::COLUMN['UID_TYPES']][] = $uids['uid_type'];
                    $output[$i][ItemMeta::COLUMN['UIDS']][] = $uids['uid'];
                }

                // Authors.
                $this->db_main->run($sql_authors, [$output[$i]['id']]);

                while($row = $this->db_main->getResultRow()) {

                    $output[$i][ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = $row['last_name'];
                    $output[$i][ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = $row['first_name'];
                }

                // Editors.
                $this->db_main->run($sql_editors, [$output[$i]['id']]);

                while($row = $this->db_main->getResultRow()) {

                    $output[$i][ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = $row['last_name'];
                    $output[$i][ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = $row['first_name'];
                }

                // Keywords.
                $this->db_main->run($sql_keywords, [$output[$i]['id']]);
                $output[$i][ItemMeta::COLUMN['KEYWORDS']] = $this->db_main->getResultRows(PDO::FETCH_COLUMN);

                // Urls.
                $urls = explode('|', $output[$i][ItemMeta::COLUMN['URLS']]);
                $output[$i][ItemMeta::COLUMN['URLS']] = $urls;

                if (!empty($output[$i]['file_hash'])) {

                    $output[$i]['file'] = str_replace(IL_DATA_PATH . DIRECTORY_SEPARATOR, '', $this->idToPdfPath($output[$i]['id']));
                }
            }

            // Add notes.
            $output = $this->addNotes($output);

            return $output;
        }

        return [];
    }

    /**
     * Find out which items are in clipboard.
     *
     * @param  array $items
     * @return array
     */
    private function clipboardFlags(array $items) {

        // Clipboard flag.
        $sql = <<<EOT
SELECT 1
    FROM clipboard
    WHERE user_id = ? AND item_id = ?
EOT;

        for ($i = 0; $i < count($items); $i++) {

            $columns = [
                $this->user_id,
                $items[$i]['id']
            ];

            $this->db_main->run($sql, $columns);
            $clipboard = $this->db_main->getResult();

            $items[$i]['in_clipboard'] = $clipboard === '1' ? true : false;
        }

        return $items;
    }

    private function addNotes(array $items) {

        // Item notes.
        $sql = <<<EOT
SELECT users.id, item_notes.note
    FROM item_notes
    INNER JOIN users ON users.id=item_notes.user_id
    WHERE item_notes.item_id = ?
EOT;

        for ($i = 0; $i < count($items); $i++) {

            $items[$i]['notes'] = '';
            $items[$i]['other_notes'] = [];

            $columns = [
                $items[$i]['id']
            ];

            $this->db_main->run($sql, $columns);

            while ($row = $this->db_main->getResultRow()) {

                if ($row['id'] === $this->user_id) {

                    $items[$i]['notes'] = $row['note'];

                } else {

                    $items[$i]['other_notes'][] = $row['note'];
                }
            }
        }

        // PDF notes.
        $sql = <<<EOT
SELECT users.id, annotations.annotation
    FROM annotations
    INNER JOIN users ON users.id=annotations.user_id
    WHERE annotations.item_id = ?
EOT;

        for ($i = 0; $i < count($items); $i++) {

            $items[$i]['pdf_notes'] = [];
            $items[$i]['other_pdf_notes'] = [];

            $columns = [
                $items[$i]['id']
            ];

            $this->db_main->run($sql, $columns);

            while ($row = $this->db_main->getResultRow()) {

                if ($row['id'] === $this->user_id) {

                    $items[$i]['pdf_notes'][] = $row['annotation'];

                } else {

                    $items[$i]['other_pdf_notes'][] = $row['annotation'];
                }
            }
        }

        return $items;
    }

    /**
     * Get project flags for items.
     *
     * @param array $items
     * @return array
     */
    private function addProjects(array $items): array {

        // Get the list of projects.
        $sql = <<<EOT
SELECT projects.id, projects.project
    FROM projects
    LEFT OUTER JOIN projects_users ON projects_users.project_id=projects.id
    WHERE
        (projects.user_id = ? OR projects_users.user_id = ?) AND projects.is_active = 'Y'
        ORDER BY projects.project COLLATE utf8Collation
EOT;

        $columns = [
            $this->user_id,
            $this->user_id
        ];

        $this->db_main->run($sql, $columns);
        $projects = $this->db_main->getResultRows(PDO::FETCH_KEY_PAIR);

        // Add project flags.
        $sql = <<<EOT
SELECT count(*)
    FROM projects_items
    WHERE project_id = ? AND item_id = ?
EOT;

        for ($i = 0; $i < count($items); $i++) {

            foreach ($projects as $project_id => $project_name) {

                $columns = [
                    $project_id,
                    $items[$i]['id']
                ];

                $this->db_main->run($sql, $columns);
                $in_project = $this->db_main->getResult() === '1' ? 'Y' : 'N';

                $items[$i]['projects'][] = [
                    'project_id' => $project_id,
                    'project'    => $project_name,
                    'in_project' => $in_project
                ];
            }
        }

        return $items;
    }

    protected function _maxId() {

        $sql = <<<EOT
SELECT id
    FROM items
    ORDER BY id DESC
    LIMIT 1
EOT;

        $this->db_main->run($sql);
        $max_id = $this->db_main->getResult();

        return ['max_id' => $max_id];
    }

    /**
     * Perform omnitool actions.
     *
     * @param array $item_ids
     * @param array $omnitool_actions
     * @throws Exception
     */
    protected function omnitool(array $item_ids, array $omnitool_actions): void {

        foreach ($omnitool_actions as $key => $value) {

            switch ($key) {

                case 'clipboard':

                    if ($value === 'add') {

                        $this->clipboardAdd($item_ids);

                    } elseif ($value === 'remove') {

                        $this->clipboardDelete($item_ids);
                    }

                    break;

                case 'project':

                    if ($value === 'add') {

                        $this->projectAdd($item_ids, $omnitool_actions['project_id']);

                    } elseif ($value === 'remove') {

                        $this->projectDelete($item_ids, $omnitool_actions['project_id']);
                    }

                    break;

                case 'tag':

                    if ($value === 'add') {

                        $this->tag($item_ids, $omnitool_actions['tags']);

                    } elseif ($value === 'remove') {

                        $this->untag($item_ids, $omnitool_actions['tags']);
                    }

                    break;

                case 'delete':

                    // Only admin can delete.
                    if ($this->permissions === 'A') {

                        $model = new ItemModel($this->di);
                        $model->delete($item_ids);
                    }

                    break;
            }
        }
    }

    /**
     * Create an HTML export + PDFs in a ZIP file and send the Stream to the controller.
     *
     * @param $items
     * @return Stream
     * @throws Exception
     */
    protected function _exportZip(array $items) {

        $zip_file = IL_TEMP_PATH . DIRECTORY_SEPARATOR . uniqid('export_') . '.zip';
        $zip = new ZipArchive();

        $open = $zip->open($zip_file, ZipArchive::CREATE);

        if ($open === false) {

            throw new Exception('failed opening a ZIP archive');
        }

        // Add Bootstrap to ZIP.
        $zip->addFile(IL_PUBLIC_PATH . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'bootstrap.min.css', 'css/bootstrap.min.css');
        $zip->setCompressionName('css/bootstrap.min.css', ZipArchive::CM_STORE);
        $zip->addFile(IL_PUBLIC_PATH . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'popper.min.js', 'js/popper.min.js');
        $zip->setCompressionName('css/bootstrap.min.css', ZipArchive::CM_STORE);
        $zip->addFile(IL_PUBLIC_PATH . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'bootstrap.min.js', 'js/bootstrap.min.js');
        $zip->setCompressionName('css/bootstrap.min.css', ZipArchive::CM_STORE);

        $close = $zip->close();

        if ($close === false) {

            throw new Exception('failed creating a ZIP archive');
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en" style="width:100%;height:100%">
    <head>
        <title>I, Librarian</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <style>
            .content-light {
                background-color: #f2f3f6;
            }
            table {
                table-layout: fixed;
                width: calc(100% - 30px);
                background-color: white;
                margin: 15px auto;
            }
            .abstract {
                text-align: justify;
                columns: 2 300px;
                column-gap: 30px;
            }
        </style>
    </head>
    <body class="content-light">
HTML;

        foreach ($items as $item) {

            set_time_limit(30);

            $pdf_link = 'PDF';
            clearstatcache($zip_file);

            if (!empty($item['file']) && filesize($zip_file) <= 250000000) {

                // Add PDF to ZIP.
                $open = $zip->open($zip_file);

                if ($open === false) {

                    throw new Exception('failed opening an existing ZIP archive');
                }

                $zip->addFile(IL_DATA_PATH . DIRECTORY_SEPARATOR . $item['file'], $item['file']);
                $zip->setCompressionName($item['file'], ZipArchive::CM_STORE);
                $close = $zip->close();

                if ($close === false) {

                    throw new Exception('failed modifying a ZIP archive');
                }

                $pdf_link = "<a href=\"{$item['file']}\">PDF</a>";
            }

            // Abstract.
            $abstract = empty($item['abstract']) ? 'No abstract' : $item['abstract'];

            // Rich-text notes.
            $notes_arr = [];
            $notes_arr[] = $this->sanitation->lmth($item['notes']);
            $notes_arr[] = join('<br>', $this->sanitation->lmth($item['other_notes']));
            $notes_arr = array_filter($notes_arr);
            $notes = empty($notes_arr) ? 'No notes' : join('<hr>', $notes_arr);

            // PDF annotations.
            $pdf_notes_arr = [];
            $pdf_notes_arr[] = join('<br><br>', $item['pdf_notes']);
            $pdf_notes_arr[] = join('<br><br>', $item['other_pdf_notes']);
            $pdf_notes_arr = array_filter($pdf_notes_arr);
            $pdf_notes = empty($pdf_notes_arr) ? 'No notes' : join('<hr>', $pdf_notes_arr);

            $html .= <<<EOT
                <table data-id="{$item['id']}">
                    <tbody>
                        <tr>
                            <td class="px-3 pt-3" style="width:4.5em;vertical-align: top" rowspan="3">
                                $pdf_link
                            </td>
                            <td class="pt-3 pr-3">
                                <h5>{$item['title']}</a></h5>
                            </td>
                        </tr>
                        <tr>
                            <td class="pt-0 pb-3 pr-5">
                                <div class="abstract">{$abstract}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="row pt-0 pb-3 pr-5">
                                <div class="col-md-6">
                                    <p><span class="badge badge-secondary rounded-0">Notes</span></p>
                                    {$notes}
                                </div>
                                <div class="col-md-6">
                                    <p><span class="badge badge-secondary rounded-0">PDF Notes</span></p>
                                    {$pdf_notes}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
EOT;
        }

        $html .= <<<HTML
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap.min.js"></script>
    </body>
</html>
HTML;

        // Add HTML.
        $open = $zip->open($zip_file);

        if ($open === false) {

            throw new Exception('failed opening an existing ZIP archive');
        }

        $zip->addFromString('index.html', $html);
        $zip->setCompressionName('index.html', ZipArchive::CM_STORE);
        $close = $zip->close();

        if ($close === false) {

            throw new Exception('failed modifying a ZIP archive');
        }

        $zip = null;

        clearstatcache($zip_file);

        $zp = fopen($zip_file, 'rb');

        return stream_for($zp);
    }

    /**
     * Reformat Bibtex IDs.
     *
     * @throws Exception
     */
    protected function _resetBibtexIds(): void {

        $this->scalar_utils = $this->di->getShared('ScalarUtils');

        // Bibtex ID format.
        $sql_bibtex_fromat = <<<SQL
SELECT setting_value
    FROM settings
    WHERE setting_name = 'custom_bibtex'
SQL;

        $this->db_main->run($sql_bibtex_fromat);
        $format_json = $this->db_main->getResult();

        $format = \Librarian\Http\Client\json_decode($format_json, true);

        $transaction_size = 10;

        // How many records?
        $sql = <<<EOT
SELECT id
    FROM items
    ORDER BY id DESC
    LIMIT 1;
EOT;

        $this->db_main->run($sql);
        $total = (int) $this->db_main->getResult();

        // Item columns.
        $sql = <<<EOT
SELECT
    items.id,
    items.title,
    items.publication_date,
    primary_titles.primary_title,
    secondary_titles.secondary_title,
    tertiary_titles.tertiary_title
    FROM items
    LEFT JOIN primary_titles ON primary_titles.id=items.primary_title_id
    LEFT JOIN secondary_titles ON secondary_titles.id=items.secondary_title_id
    LEFT JOIN tertiary_titles ON tertiary_titles.id=items.tertiary_title_id
    WHERE items.id > ? AND items.id <= ?
EOT;

        // Authors.
        $sql_authors = <<<EOT
SELECT
    last_name, first_name
    FROM authors
    INNER JOIN items_authors ON items_authors.author_id=authors.id
    WHERE items_authors.item_id = ?
    ORDER by items_authors.position
EOT;

        // Editors.
        $sql_editors = <<<EOT
SELECT
    last_name, first_name
    FROM editors
    INNER JOIN items_editors ON items_editors.editor_id=editors.id
    WHERE items_editors.item_id = ?
    ORDER by items_editors.position
EOT;

        // Update item.
        $sql_update = <<<EOT
UPDATE items
    SET bibtex_id = ?
    WHERE id = ?
EOT;

        $this->db_main->beginTransaction();

        for ($i = 0; $i < $total; $i = $i + $transaction_size) {

            $this->db_main->run($sql, [$i, min($total, $i + $transaction_size)]);
            $output = $this->db_main->getResultRows();

            foreach ($output as $key => $item) {

                $this->db_main->run($sql_authors, [$item['id']]);

                while ($row = $this->db_main->getResultRow()) {

                    $item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = $row['last_name'];
                    $item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = $row['first_name'];
                }

                $this->db_main->run($sql_editors, [$item['id']]);

                while ($row = $this->db_main->getResultRow()) {

                    $item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = $row['last_name'];
                    $item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = $row['first_name'];
                }

                $bibtex_id = $this->scalar_utils->customBibtexId($format, $item);
                $this->db_main->run($sql_update, [$bibtex_id, $item['id']]);
            }

            $this->db_main->commit();
            $this->db_main->beginTransaction();
        }

        $this->db_main->commit();
    }
}
