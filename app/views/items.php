<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Export\Bibtex;
use Librarian\Export\Endnote;
use Librarian\Export\Ris;
use Librarian\Html\Bootstrap;
use Librarian\Html\Bootstrap\Icon;
use Librarian\Html\Element;
use GuzzleHttp\Utils;
use Librarian\ItemMeta;
use Librarian\Media\Temporal;
use Librarian\Mvc\TextView;

class ItemsView extends TextView {

    use SharedHtmlView;

    /**
     * @var Temporal
     */
    private $temporal_obj;

    /**
     * @var ItemMeta
     */
    private $item_meta;

    /**
     * @var array Search type to human readable.
     */
    private $type_to_readable;

    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->type_to_readable = [
            'anywhere'  => "{$this->lang->t9n('Metadata')} {$this->lang->t9n('and')} {$this->lang->t9n('PDF')}",
            'metadata'  => $this->lang->t9n('Metadata'),
            'FT'        => $this->lang->t9n('PDF'),
            'pdfnotes'  => $this->lang->t9n('PDF notes'),
            'itemnotes' => $this->lang->t9n('Notes'),
            'itemid'    => $this->lang->t9n('Item') . ' ID',
            'TI'        => $this->lang->t9n('Title'),
            'AB'        => "{$this->lang->t9n('Title')} {$this->lang->t9n('or')} {$this->lang->t9n('Abstract')}",
            'AU'        => "{$this->lang->t9n('Author')} {$this->lang->t9n('or')} {$this->lang->t9n('Editor')}",
            'AF'        => $this->lang->t9n('Affiliation'),
            'T1'        => $this->lang->t9n('Primary title'),
            'T2'        => $this->lang->t9n('Secondary title'),
            'T3'        => $this->lang->t9n('Tertiary title'),
            'KW'        => $this->lang->t9n('Keyword'),
            'YR'        => $this->lang->t9n('Year')
        ];
    }

    /**
     * Assemble item browsing page and send it.
     *
     * @param string $collection Specify library, clipboard.
     * @param array $get GET super global array.
     * @param array $input [array items, integer total_count, array tags]
     * @param bool $filter_menu
     * @return string
     * @throws Exception
     */
    public function page(string $collection, array $get, array $input, bool $filter_menu = false): string {

        // Page.
        $page = isset($get['page']) ? $get['page'] : 1;

        // Page title.
        switch ($collection) {

            case 'clipboard':
                $title = "{$this->lang->t9n('Page')} {$page} - {$this->lang->t9n('Clipboard')}";
                break;

            case 'project':
                $title = "{$this->lang->t9n('Page')} {$page} - {$this->lang->t9n('Project')} - {$input['project']}";
                break;

            case 'catalog':
                $title = "{$this->lang->t9n('Page')} {$page} - {$this->lang->t9n('Catalog')}";
                break;

            default:
                $title = "{$this->lang->t9n('Page')} {$page} - {$this->lang->t9n('Library')}";
                break;
        }

        $this->title($title);

        $this->head();

        // Page top.
        $page_top = $this->pageTop($collection, $get, $input);

        // Item list.
        switch ($this->app_settings->getUser('display_type')) {

            case 'title':
                $items = $this->titleList($input['items']);
                break;

            case 'icon':
                $items = $this->iconList($input['items']);
                break;

            case 'summary':
                $items = $this->summaryList($input['items']);
                break;

            case 'table':
                $items = $this->tableList($input['items']);
                break;

            default:
                $items = $this->titleList($input['items']);
        }

        // Top row contains the breadcrumb and the item list. It is scrollable.

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->id('top-row');
        $el->style('overflow: auto');
        $el->addClass("d-flex align-content-start");
        $el->column($page_top, 'col-12');
        $el->append($items);
        $top_row = $el->render();

        $el = null;

        // Toolbar.
        $page_bottom = $this->pageBottom($collection, $get, $input);

        $page_rows = "$top_row $page_bottom";

        // Add filter menu.
        if ($filter_menu === true) {

            $filter = $this->filterMenu($collection, $get);

            $left_class = self::$theme === 'dark' ? 'bg-dark' : 'bg-white';

            /** @var Bootstrap\Row $el */
            $el = $this->di->get('Row');

            $el->column($filter, 'col-lg-auto p-0 ' . $left_class);
            $el->column($page_rows, 'col-lg items-container');
            $page_rows = $el->render();

            $el = null;
        }

        // Modals.

        switch ($collection) {

            case 'clipboard':
                $action = '#clipboard/main';
                break;

            case 'project':
                $action = '#project/browse?id=' . $get['id'];
                break;

            default:
                $action = '#items/main';
        }

        $modal_header = $collection === 'catalog' ? 'library' : $collection;

        // Quick search modal.

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('link');
        $el->addClass('pr-0');
        $el->attr('data-dismiss', 'modal');
        $el->attr('data-toggle', 'modal');
        $el->dataTarget('#modal-searches');
        $el->html($this->lang->t9n('Previous searches'));
        $searches_button = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('link');
        $el->attr('data-dismiss', 'modal');
        $el->attr('data-toggle', 'modal');
        $el->dataTarget('#modal-advanced-search');
        $el->html($this->lang->t9n('Advanced search'));
        $advanced_button = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-quick-search');
        $el->header($this->lang->t9n('Search ' . $modal_header));
        $el->button($searches_button);
        $el->button($advanced_button);
        $el->body($this->sharedQuickSearch($action), 'bg-darker-5');
        $quick_search = $el->render();

        $el = null;

        // Advanced search.

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('link');
        $el->attr('data-dismiss', 'modal');
        $el->attr('data-toggle', 'modal');
        $el->dataTarget('#modal-quick-search');
        $el->html($this->lang->t9n('Quick search'));
        $button = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->addClass('search-submit');
        $el->context('primary');
        $el->html($this->lang->t9n('Search-VERB'));
        $search_button = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-advanced-search');
        $el->header('Search ' . $modal_header);
        $el->button($searches_button);
        $el->button($button);
        $el->button($search_button);
        $el->body($this->sharedAdvancedSearch($input['tags'] ?? [], $action), 'bg-darker-5');
        $el->componentSize('large');
        $advanced_search = $el->render();

        $el = null;

        $html = "$page_rows $quick_search $advanced_search";

        // ID list.
        $id_list = [];

        foreach ($input['items'] as $item) {

            $id_list[] = [
                'id'    => $item['id'],
                'title' => trim(mb_substr($item['title'], 0, 75)) . (mb_strlen($item['title']) > 75 ? '&hellip;' : '')
            ];
        }

        $this->append([
            'html'    => $html,
            'id_list' => $id_list
        ]);

        return $this->send();
    }

    /**
     * Assemble item page containing filter column and send it.
     *
     * @param string $collection Specify library, clipboard.
     * @param array $get
     * @param array $input [array $filters, array items, integer total_count]
     * @return string
     * @throws Exception
     */
    public function filteredPage(string $collection, array $get, array $input): string {

        return $this->page($collection, $get, $input, true);
    }

    /**
     * Top row for all pages.
     *
     * @param string $collection
     * @param array $get
     * @param array $input
     * @return string
     * @throws Exception
     */
    private function pageTop(string $collection, array $get, array $input): string {

        // Breadcrumbs.

        switch ($collection) {

            case 'clipboard':
                $bc_title = $this->lang->t9n('Clipboard');
                break;

            case 'project':
                $bc_title = $input['project'];
                break;

            case 'catalog':
                $bc_title = "{$this->lang->t9n('Items')} {$get['from_id']} - " .
                    ($this->scalar_utils->formatNumber(-1 + $get['from_id'] + $this->app_settings->getGlobal('max_items')));
                break;

            default:
                $bc_title = $this->lang->t9n('Library');
        }

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');

        if ($collection === 'project') {

            $el->item($this->lang->t9n('Projects'), IL_BASE_URL . 'index.php/#projects/main');
        }

        if ($collection === 'catalog') {

            $el->item($this->lang->t9n('Catalog'), IL_BASE_URL . 'index.php/#items/catalog');
        }

        $el->item("{$bc_title}");
        $bc = $el->render();

        $el = null;

        // Item count.

        if (empty($input['total_count'])) {

            $item_count = "<div class=\"text-muted w-100 pb-3 pb-lg-0\">{$this->lang->t9n('No items')}</div>";

        } else {

            /*
             * "From" item number = page number * page size + 1. We need to subtract 1 from the page number.
             *  E.g., page 2 -> Item (10 + 1).
             */
            $from = ($get['page'] - 1) * $this->app_settings->getUser('page_size') + 1;
            $from = $this->scalar_utils->formatNumber((int) $from);

            // "To" item number = page number * page size. Max. is the total item number.
            $last = min($get['page'] * $this->app_settings->getUser('page_size'), $input['total_count']);
            $last = $this->scalar_utils->formatNumber((int) $last);

            // Total count.
            $total_count = $this->scalar_utils->formatNumber((int) $input['total_count']);

            // Put together the item counter.
            $item_count = '<div class="text-muted w-100 pb-3 pb-lg-0">' .
                sprintf($this->lang->t9n('Items %s of %s'), $from . ' - ' . $last, $total_count) . '</div>';
        }

        $tags_html = '';

        // Search tags.
        if (isset($get['search_type'])) {

            for ($i = 1; $i <= 8; $i++) {

                $this->type_to_readable["C{$i}"] = $this->app_settings->getGlobal("custom{$i}");
            }

            foreach ($get['search_type'] as $i => $type) {

                if ($get['search_query'][$i] === '') {

                    continue;
                }

                $get_search_query = $this->sanitation->html($get['search_query'][$i]);

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context("dark");
                $el->componentSize("small");
                $el->addClass("d-inline-block mr-1 mb-2 rounded-0");
                $el->html("<b>{$this->type_to_readable[$type]}</b> &mdash; {$get_search_query}");
                $tags_html .= $el->render();

                $el = null;
            }
        }

        // Filter tags.
        $facet_fields = [
            'added_time'       => $this->lang->t9n('Added date'),
            'author'           => $this->lang->t9n('Author'),
            'custom1'          => $this->lang->t9n($this->app_settings->getGlobal('custom1')),
            'custom2'          => $this->lang->t9n($this->app_settings->getGlobal('custom2')),
            'custom3'          => $this->lang->t9n($this->app_settings->getGlobal('custom3')),
            'custom4'          => $this->lang->t9n($this->app_settings->getGlobal('custom4')),
            'custom5'          => $this->lang->t9n($this->app_settings->getGlobal('custom5')),
            'custom6'          => $this->lang->t9n($this->app_settings->getGlobal('custom6')),
            'custom7'          => $this->lang->t9n($this->app_settings->getGlobal('custom7')),
            'custom8'          => $this->lang->t9n($this->app_settings->getGlobal('custom8')),
            'editor'           => $this->lang->t9n('Editor'),
            'keyword'          => $this->lang->t9n('Keyword'),
            'misc'             => $this->lang->t9n('Miscellaneous'),
            'primary_title'    => $this->lang->t9n('Primary title'),
            'reference_type'   => $this->lang->t9n('Publication type'),
            'secondary_title'  => $this->lang->t9n('Secondary title'),
            'tag'              => $this->lang->t9n('Tag'),
            'tertiary_title'   => $this->lang->t9n('Tertiary title')
        ];

        if (!empty($input['filters'])) {

            /** @var Bootstrap\Icon $el Close icon. */
            $el = $this->di->get('Icon');

            $el->icon("close");
            $close = $el->render();

            $el = null;

            foreach ($input['filters'] as $filter) {

                $key = key($filter);
                $values = current($filter);

                if (empty($values)) {

                    continue;
                }

                // Format dates to local format.
                if ($key === 'added_time') {

                    $this->temporal_obj = $this->di->get('Temporal');
                    $values[1] = $this->temporal_obj->toUserDate($values[1]);
                }

                // Construct a URL for the Remove filter button.
                $removed_get = $get;

                // Always remove page, because the link generates a new item list.
                unset ($removed_get['page']);

                // Remove this tag URL.
                $remove_key = array_search($values[0], $removed_get['filter'][$key]);
                unset($removed_get['filter'][$key][$remove_key]);

                // Build query.
                $get_query = http_build_query($removed_get);
                $get_query = empty($get_query) ? '' : '?'. $get_query;

                $close_url = '#' . IL_PATH_URL . $get_query;

                $name = $facet_fields[$key] ?? $this->lang->t9n('Filter-NOUN');

                $t9n_value = $values[1] === 'untagged' ? $this->lang->t9n($values[1]) : $values[1];

                $tags_html .= <<<EOT
                    <div class="btn-group mr-1 mb-2" role="group" aria-label="Filter button">
                        <button type="button" class="btn btn-sm btn-dark rounded-0">
                            <b>{$name}</b> &mdash; {$t9n_value}
                        </button>
                        <a href="{$close_url}" class="btn btn-sm btn-secondary">{$close}</a>
                    </div>
EOT;
            }
        }

        // Put it together in a row.

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($bc, 'col-lg-8');
        $el->column($item_count, 'col-lg-4 text-center text-lg-right d-flex align-items-center');

        if ($tags_html !== '') {

            $el->column($tags_html, 'col-12');
        }

        $row = $el->render();

        $el = null;

        return $row;
    }

    /**
     * @param string $collection
     * @param array $get
     * @param array $input
     * @return string
     * @throws Exception
     */
    private function pageBottom(string $collection, array $get, array $input): string {

        // We'll use some _GET in URLs.
        $sanitized_get = $this->sanitation->urlquery($get);

        $btn_class_a = self::$theme === 'dark' ? 'warning' : 'warning';
        $btn_class_b = self::$theme === 'dark' ? 'secondary' : 'outline-dark';

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('d-xl-none');
        $el->icon('magnify');
        $search_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('search-toggle');
        $el->context($btn_class_a);
        $el->dataToggle('modal');
        $el->dataTarget('#modal-quick-search');
        $el->addClass('border-0 shadow-none');
        $el->html("<span class=\"d-none d-xl-inline-block\" style='min-width:3.5rem'>{$this->lang->t9n('Search-NOUN')}</span>$search_icon");
        $search_toggle = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('d-xl-none');
        $el->icon('filter-outline');
        $filter_icon = $el->render();

        $el = null;

        $filter_url = '#' . str_replace(['/main', '/browse'], '/filter', IL_PATH_URL);

        if ($collection === 'project') {

            $filter_url = '#project/filter?id=' . $get['id'];
        }

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->id('filter-toggle');
        $el->context($btn_class_a);
        $el->addClass('border-0 shadow-none');
        $el->href($filter_url);
        $el->html("<span class=\"d-none d-xl-inline-block\" style='min-width:3.5rem'>{$this->lang->t9n('Filter-NOUN')}</span>$filter_icon");
        $filter_toggle = $el->render();

        $el = null;

        // Catalog is not searchable and filterable.
        if (isset($get['from_id'])) {

            $search_toggle = '';
            $filter_toggle = '';
        }

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('d-xl-none');
        $el->icon('briefcase-download-outline');
        $export_icon = $el->render();

        $el = null;

        // First page URL.
        unset($sanitized_get['page']);
        $first_page_q = empty($sanitized_get) ? '' : '?'. http_build_query($sanitized_get);

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('open-export');
        $el->context($btn_class_b);
        $el->addClass('border-0');
        $el->dataToggle('modal');
        $el->dataTarget('#modal-export');
        $el->attr('data-export-url', IL_BASE_URL . 'index.php/' . IL_PATH_URL . $first_page_q);
        $el->html("<span class=\"d-none d-xl-inline\">{$this->lang->t9n('Export-NOUN')}</span>$export_icon");
        $export_button = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('d-xl-none');
        $el->icon('wrench-outline');
        $omnitool_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('open-omnitool');
        $el->context($btn_class_b);
        $el->addClass('border-0');
        $el->dataToggle('modal');
        $el->dataTarget('#modal-omnitool');
        $el->attr('data-omnitool-url', IL_BASE_URL . 'index.php/' . IL_PATH_URL . $first_page_q);
        $el->html("<span class=\"d-none d-xl-inline\">{$this->lang->t9n('Omnitool')}</span>$omnitool_icon");
        $omnitool_button = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('d-xl-none');
        $el->icon('monitor');
        $sort_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('open-settings');
        $el->context($btn_class_b);
        $el->addClass('border-0');
        $el->dataToggle('modal');
        $el->dataTarget('#modal-settings');
        $el->html("<span class=\"d-none d-xl-inline\">{$this->lang->t9n('Display')}</span>$sort_icon");
        $display_button = $el->render();

        $el = null;

        // First page.
        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->elementName('a');
        $el->href('#' . IL_PATH_URL . $first_page_q);
        $el->context($btn_class_b);
        $el->addClass('border-0');
        $el->icon('chevron-double-left');
        $el->html('Go to first page');

        // Disabling.
        if ($get['page'] === 1) {

            $el->elementName('button');
            $el->removeAttr('href');
            $el->disabled('disabled');
        }

        $first_button = $el->render();

        $el = null;

        // Previous page.
        $prev_disabled = true;
        $prev_page = $get['page'] - 1;

        if ($prev_page >= 1) {

            $prev_disabled = false;
            $sanitized_get['page'] = $prev_page;
        }

        $prev_page_q = empty($sanitized_get) ? '' : '?'. http_build_query($sanitized_get);

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->elementName('a');
        $el->href('#' . IL_PATH_URL . $prev_page_q);
        $el->context($btn_class_b);
        $el->addClass('border-0 navigation-left');
        $el->icon('chevron-left');
        $el->html('Go to previous page');

        // Disabling.
        if ($prev_disabled) {

            $el->elementName('button');
            $el->removeAttr('href');
            $el->disabled('disabled');
        }

        $prev_button = $el->render();

        $el = null;

        unset($sanitized_get['page']);

        // Next page.
        $next_disabled = true;
        $next_page = $get['page'] + 1;

        // Enforce max_items.
        if ($next_page <= ceil($input['total_count'] / $this->app_settings->getUser('page_size'))
            && $next_page <= ceil($this->app_settings->getGlobal('max_items') / $this->app_settings->getUser('page_size'))) {

            $next_disabled = false;
            $sanitized_get['page'] = $next_page;
        }

        $next_page_q = empty($sanitized_get) ? '' : '?'. http_build_query($sanitized_get);

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->elementName('a');
        $el->href('#' . IL_PATH_URL . $next_page_q);
        $el->context($btn_class_b);
        $el->addClass('border-0 navigation-right');
        $el->icon('chevron-right');
        $el->html('Go to next page');

        // Disabling.
        if ($next_disabled) {

            $el->elementName('button');
            $el->removeAttr('href');
            $el->disabled('disabled');
        }

        $next_button = $el->render();

        $el = null;

        unset($sanitized_get['page']);

        // Last page.
        $max_items = min($input['total_count'], $this->app_settings->getGlobal('max_items'));
        $page_modulus = $max_items % $this->app_settings->getUser('page_size');

        $last_page =  $page_modulus === 0 ?
            $max_items / $this->app_settings->getUser('page_size') :
            ceil($max_items / $this->app_settings->getUser('page_size'));

        $sanitized_get['page'] = $last_page;

        $last_page_q = empty($sanitized_get) ? '' : '?'. http_build_query($sanitized_get);

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->elementName('a');
        $el->href('#' . IL_PATH_URL . $last_page_q);
        $el->context($btn_class_b);
        $el->addClass('border-0 ');
        $el->icon('chevron-double-right');
        $el->html('Go to last page');

        // Disabling.
        if ((integer) $last_page === (integer) $get['page'] || (integer) $last_page === 0) {

            $el->elementName('button');
            $el->removeAttr('href');
            $el->disabled('disabled');
        }

        $last_button = $el->render();

        $el = null;

        unset($sanitized_get['page']);

        // Toolbar row.
        $toolbar_class = self::$theme === 'dark' ? 'bg-secondary' : 'bg-darker-5';

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->id('bottom-row');
        $el->role('toolbar');
        $el->addClass('px-3 ' . $toolbar_class);
        $el->column("$search_toggle $filter_toggle $export_button $omnitool_button $display_button", 'col-sm-auto text-center text-sm-left p-0 my-2');
        $el->column("$first_button $prev_button $next_button $last_button", 'col-sm text-center text-sm-right p-0 my-2');
        $bottom_row = $el->render();

        $el = null;

        return $bottom_row;
    }

    /**
     * Assemble HTML for title item list.
     *
     * @param array $items
     * @return string
     * @throws Exception
     */
    private function titleList(array $items): string {

        // Theme.
        $theme_classes = self::$theme === 'light' ? 'bg-white' : 'bg-dark text-white';

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $el->addClass('mdi-18px text-muted');
        $chevron = $el->render();

        $el = null;

        $titles = '';

        foreach ($items as $item) {

            $IL_BASE_URL = IL_BASE_URL;

            if ((int) $item['has_pdf'] === 1) {

                // Active search, convert to PDF search.
                $get = $this->request->getQueryParams();
                $search = '';

                if (isset($get['search_query'])) {

                    $search = '&search=' . rawurlencode($this->toPdfSearch($get));
                }

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->elementName('a');
                $el->href("{$IL_BASE_URL}index.php/pdf?id={$item['id']}" . $search);
                $el->addClass('px-2 py-2 border-0');
                $el->context('primary');
                $el->html('PDF');
                $pdf = $el->render();

                $el = null;

                /** @var Bootstrap\IconButton $el */
                $el = $this->di->get('IconButton');

                $el->elementName('a');
                $el->href("{$IL_BASE_URL}index.php/pdf/file?disposition=attachment&id={$item['id']}");
                $el->addClass('px-2 py-1 border-0');
                $el->context('secondary');
                $el->icon('download');
                $download = $el->render();

                $el = null;

                $pdf_link = <<<EOT
                    <div class="btn-group-vertical">
                        $pdf
                        $download
                    </div>
EOT;

            } else {

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->addClass('px-2 py-2 bg-darker-5 border-0');
                $el->html('PDF');
                $el->disabled('disabled');
                $pdf = $el->render();

                $el = null;

                /** @var Bootstrap\IconButton $el */
                $el = $this->di->get('IconButton');

                $el->addClass('px-2 py-1 bg-darker-5 border-0');
                $el->icon('download');
                $el->disabled('disabled');
                $download = $el->render();

                $el = null;

                $pdf_link = <<<EOT
                    <div class="btn-group-vertical">
                        $pdf
                        $download
                    </div>
EOT;
            }

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->type('checkbox');
            $el->inline(true);
            $el->label($this->lang->t9n('Clipboard'));
            $el->name('clipboard');
            $el->addClass('clipboard mb-3');
            $el->id('clipboard-' . $item['id']);

            if ($item['in_clipboard'] === '1') {

                $el->attr('checked', 'checked');
            }

            $check = $el->render();

            $el = null;

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->elementName('button');
            $el->addClass('px-0 py-0 pr-1 mb-3 mr-3 projects-button');
            $el->dataToggle('collapse');
            $el->dataTarget("#projects-{$item['id']}");
            $el->html("{$chevron}{$this->lang->t9n('Projects')}");
            $button = $el->render();

            $el = null;

            $project_html = empty($item['projects']) ? "<span class=\"text-secondary\">{$this->lang->t9n('No projects')}</span>" : '';

            if (!empty($item['projects'])) {

                foreach ($item['projects'] as $project) {

                    /** @var Bootstrap\Input $el */
                    $el = $this->di->get('Input');

                    $el->type('checkbox');
                    $el->inline(true);
                    $el->label($project['project']);
                    $el->name('project');
                    $el->value($project['project_id']);
                    $el->addClass('project');
                    $el->id("project-{$item['id']}-{$project['project_id']}");

                    if ($project['in_project'] === 'Y') {

                        $el->attr('checked', 'checked');
                    }

                    $project_html .= $el->render();

                    $el = null;
                }
            }

            // Authors.
            $authors = $item['authors'] === [] ? $this->lang->t9n('No authors') : join('<span class="ml-0"> &hellip;</span>', $item['authors']);

            // Publication.
            $publication = !empty($item['publication_title']) ? "<i>{$item['publication_title']}</i>" : $item['reference_type'];
            $date = !empty($item['publication_date']) ? "({$item['publication_date']})" : '';

            // Top HTML structure.

            $titles .= <<<EOT
                <table data-id="{$item['id']}" class="{$theme_classes} item-container mb-2" style="border: 1px solid rgba(0,0,0,0.08);table-layout: fixed;width:100%">
                    <tbody>
                        <tr>
                            <td class="py-3 pl-3" rowspan="2" style="vertical-align: top;width: 4.25em">
                                $pdf_link
                            </td>
                            <td class="pl-1 pt-3 pr-5" style="height: 3.5rem">
                                <h5><a href="{$IL_BASE_URL}index.php/item#summary?id={$item['id']}">{$item['title']}</a></h5>
                                <span class="mr-1">{$authors}</span> <span class="mr-1">{$date}</span> {$publication}
                            </td>
                        </tr>
                        <tr>
                            <td class="pr-5" style="vertical-align: top">
                                $button $check
                                <div class="collapse pl-1 mb-2" id="projects-{$item['id']}">$project_html</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
EOT;
        }

        return "<div class=\"col\">$titles</div>";
    }

    /**
     * Display items as a table.
     *
     * @param array $items
     * @return string
     * @throws Exception
     */
    private function tableList(array $items): string {

        $titles = <<<EOT
            <table class="item-container" style="table-layout: fixed;width: 100%;box-shadow: none">
                <thead>
                    <tr>
                        <th class="px-3 py-2" style="width: 3rem">&nbsp;</th>
                        <th class="px-3 py-2 d-none d-md-table-cell" style="width: 12rem">Author</th>
                        <th class="px-3 py-2">Title</th>
                        <th class="px-3 py-2 d-none d-xl-table-cell" style="width: 5rem">Year</th>
                        <th class="px-3 py-2 d-none d-xl-table-cell" style="width: 20%">Publication</th>
                    </tr>
                </thead>
EOT;

        foreach ($items as $key => $item) {

            $IL_BASE_URL = IL_BASE_URL;

            if ((int) $item['has_pdf'] === 1) {

                // Active search, convert to PDF search.
                $get = $this->request->getQueryParams();
                $search = '';

                if (isset($get['search_query'])) {

                    $search = '&search=' . rawurlencode($this->toPdfSearch($get));
                }

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Badge');

                $el->elementName('a');
                $el->href("{$IL_BASE_URL}index.php/pdf?id={$item['id']}" . $search);
                $el->addClass('border border-primary mr-3');
                $el->context('primary');
                $el->html('PDF');
                $pdf_link = $el->render();

                $el = null;

            } else {

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Badge');

                $el->context('secondary');
                $el->addClass('border border-secondary mr-3');
                $el->style('opacity: 0.66');
                $el->html('PDF');
                $pdf_link = $el->render();

                $el = null;
            }

            // Authors.
            $authors = $item['authors'] === [] ? "<span class=\"text-muted\">{$this->lang->t9n('No authors')}</span>" : $item['authors'][0];

            // Publication.
            $publication = !empty($item['publication_title']) ? $item['publication_title'] : $item['reference_type'];
            $date = !empty($item['publication_date']) ? $item['publication_date'] : '';

            // Top HTML structure.

            $row_class = '';

            if ($key % 2 === 0) {

                $row_class = self::$theme === 'dark' ? 'bg-dark' : 'bg-white';
            }

            $titles .= <<<EOT
                <tbody class="{$row_class}">
                    <tr>
                        <td class="px-3 py-3">{$pdf_link}</td>
                        <td class="px-3 py-3 text-truncate d-none d-md-table-cell">{$authors}</td>
                        <td class="px-3 py-3 text-truncate">
                            <a href="{$IL_BASE_URL}index.php/item#summary?id={$item['id']}">{$item['title']}</a>
                        </td>
                        <td class="px-3 py-3 text-truncate d-none d-xl-table-cell">{$date}</td>
                        <td class="px-3 py-3 text-truncate d-none d-xl-table-cell">{$publication}</td>
                    </tr>
                </tbody>
EOT;
        }

        $titles .= <<<EOT
            </table>
EOT;

        return "<div class=\"col-12\">$titles</div>";
    }

    /**
     * Assemble HTML for icon item list.
     *
     * @param array $items
     * @return string
     * @throws Exception
     */
    private function iconList(array $items): string {

        // Card size.
        if ($this->app_settings->getUser('icons_per_row') === 'auto') {

            $server = $this->request->getServerParams();
            $screen = $server['HTTP_X_CLIENT_WIDTH'];

            if ($screen >= 1600) {

                $card_size = '3';

            } elseif ($screen >= 1366 && $screen < 1600) {

                $card_size = '4';

            } elseif ($screen >= 768 && $screen < 1366) {

                $card_size = '6';

            } else {

                $card_size = '12';
            }

        } else {

            switch ($this->app_settings->getUser('icons_per_row')) {

                case 1:
                    $card_size = '12';
                    break;
                case 2:
                    $card_size = '6';
                    break;
                case 3:
                    $card_size = '4';
                    break;
                case 4:
                    $card_size = '3';
                    break;
                default:
                    $card_size = '3';
            }
        }

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $el->addClass('text-muted');
        $chevron = $el->render();

        $el = null;

        $output = '';
        $IL_BASE_URL = IL_BASE_URL;

        foreach ($items as $item) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->type('checkbox');
            $el->inline(true);
            $el->addClass('clipboard');
            $el->label($this->lang->t9n('Clipboard'));
            $el->name('clipboard');
            $el->id('clipboard-' . $item['id']);

            if ($item['in_clipboard'] === '1') {

                $el->attr('checked', 'checked');
            }

            $check = $el->render();

            $el = null;

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->elementName('button');
            $el->addClass('px-0 py-0 pr-1 mr-3 projects-button');
            $el->dataToggle('collapse');
            $el->dataTarget("#projects-{$item['id']}");
            $el->html("{$chevron}{$this->lang->t9n('Projects')}");
            $button = $el->render();

            $el = null;

            $project_html = empty($item['projects']) ? "<span class=\"text-secondary\">{$this->lang->t9n('No projects')}</span>" : '';

            if (!empty($item['projects'])) {

                foreach ($item['projects'] as $project) {

                    /** @var Bootstrap\Input $el */
                    $el = $this->di->get('Input');

                    $el->type('checkbox');
                    $el->inline(true);
                    $el->label($project['project']);
                    $el->name('project');
                    $el->value($project['project_id']);
                    $el->addClass('project');
                    $el->id("project-{$item['id']}-{$project['project_id']}");

                    if ($project['in_project'] === 'Y') {

                        $el->attr('checked', 'checked');
                    }

                    $project_html .= $el->render();

                    $el = null;
                }
            }

            // Active search, convert to PDF search.
            $get = $this->request->getQueryParams();
            $search = '';

            if (isset($get['search_query'])) {

                $search = '&search=' . rawurlencode($this->toPdfSearch($get));
            }

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass("item-container h-100");
            $el->dataId($item['id']);
            $el->body(<<<EOT
                <div style="position:relative;padding-top: 62.5%;overflow: hidden">
                    <a href="{$IL_BASE_URL}index.php/pdf?id={$item['id']}{$search}" style="position:absolute;top:0;left:0;width:100%">
                        <img
                            class="img-fluid w-100"
                            src="{$IL_BASE_URL}index.php/icon?id={$item['id']}"
                            alt="PDF preview">
                    </a>
                </div>
                <div class="px-3 pt-2 pb-1 border-darker-top">
                    <div class="d-table" style="table-layout: fixed;width: 100%;">
                        <div class="d-table-cell align-middle text-truncate">
                            <a href="{$IL_BASE_URL}index.php/item#summary?id={$item['id']}">{$item['title']}</a>
                        </div>
                    </div>
                    $button $check
                    <div class="collapse" id="projects-{$item['id']}">$project_html</div>
                </div>
                
EOT
            , null, 'p-0');
            $card = $el->render();

            $el = null;

            $output .= "<div class=\"col-sm-{$card_size} pb-3\">$card</div>";
        }

        return $output;
    }

    /**
     * Assemble HTML for summaries item list.
     *
     * @param array $items
     * @return string
     * @throws Exception
     */
    private function summaryList(array $items): string {

        $this->temporal_obj = $this->di->get('Temporal');

        // Theme.
        $theme_classes = self::$theme === 'light' ? 'bg-white' : 'bg-dark text-white';

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $el->addClass('mdi-18px text-muted');
        $chevron = $el->render();

        $el = null;

        $titles = '';

        foreach ($items as $item) {

            $IL_BASE_URL = IL_BASE_URL;

            if ((int) $item['has_pdf'] === 1) {

                // Active search, convert to PDF search.
                $get = $this->request->getQueryParams();
                $search = '';

                if (isset($get['search_query'])) {

                    $search = '&search=' . rawurlencode($this->toPdfSearch($get));
                }

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->elementName('a');
                $el->href("{$IL_BASE_URL}index.php/pdf?id={$item['id']}" . $search);
                $el->addClass('px-2 py-2 border-0');
                $el->context('primary');
                $el->html('PDF');
                $pdf = $el->render();

                $el = null;

                /** @var Bootstrap\IconButton $el */
                $el = $this->di->get('IconButton');

                $el->elementName('a');
                $el->href("{$IL_BASE_URL}index.php/pdf/file?disposition=attachment&id={$item['id']}");
                $el->addClass('px-2 py-1 border-0');
                $el->context('secondary');
                $el->icon('download');
                $download = $el->render();

                $el = null;

                $pdf_link = <<<EOT
                    <div class="btn-group-vertical">
                        $pdf
                        $download
                    </div>
EOT;

            } else {

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->addClass('px-2 py-2 bg-darker-5 border-0');
                $el->html('PDF');
                $el->disabled('disabled');
                $pdf = $el->render();

                $el = null;

                /** @var Bootstrap\IconButton $el */
                $el = $this->di->get('IconButton');

                $el->addClass('px-2 py-1 bg-darker-5 border-0');
                $el->icon('download');
                $el->disabled('disabled');
                $download = $el->render();

                $el = null;

                $pdf_link = <<<EOT
                    <div class="btn-group-vertical">
                        $pdf
                        $download
                    </div>
EOT;
            }

            $links = $this->sharedLinkList($item);

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->type('checkbox');
            $el->inline(true);
            $el->label($this->lang->t9n('Clipboard'));
            $el->name('clipboard');
            $el->addClass('clipboard');
            $el->id('clipboard-' . $item['id']);

            if ($item['in_clipboard'] === '1') {

                $el->attr('checked', 'checked');
            }

            $check = $el->render();

            $el = null;

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->elementName('button');
            $el->addClass('px-0 py-0 pr-1 mr-3 projects-button');
            $el->dataToggle('collapse');
            $el->dataTarget("#projects-{$item['id']}");
            $el->html("{$chevron}{$this->lang->t9n('Projects')}");
            $button = $el->render();

            $el = null;

            $project_html = empty($item['projects']) ? "<span class=\"text-secondary\">{$this->lang->t9n('No projects')}</span>" : '';

            if (!empty($item['projects'])) {

                foreach ($item['projects'] as $project) {

                    /** @var Bootstrap\Input $el */
                    $el = $this->di->get('Input');

                    $el->type('checkbox');
                    $el->inline(true);
                    $el->label($project['project']);
                    $el->name('project');
                    $el->value($project['project_id']);
                    $el->addClass('project');
                    $el->id("project-{$item['id']}-{$project['project_id']}");

                    if ($project['in_project'] === 'Y') {

                        $el->attr('checked', 'checked');
                    }

                    $project_html .= $el->render();

                    $el = null;
                }
            }

            // Abstract.
            $abstract = empty($item['abstract']) ? $this->lang->t9n('No abstract') : $item['abstract'];

            // Rich-text notes.
            $notes_arr = [];
            $notes_arr[] = $this->sanitation->lmth($item['notes']);
            $notes_arr[] = join('<br>', $this->sanitation->lmth($item['other_notes']));
            $notes_arr = array_filter($notes_arr);
            $notes = empty($notes_arr) ? $this->lang->t9n('No notes') : join('<hr>', $notes_arr);

            // PDF annotations.
            $pdf_notes_arr = [];
            $pdf_notes_arr[] = join('<br><br>', $item['pdf_notes']);
            $pdf_notes_arr[] = join('<br><br>', $item['other_pdf_notes']);
            $pdf_notes_arr = array_filter($pdf_notes_arr);
            $pdf_notes = empty($pdf_notes_arr) ? $this->lang->t9n('No notes') : join('<hr>', $pdf_notes_arr);

            // Authors.
            $authors = $item['authors'] === [] ? $this->lang->t9n('No authors') : join('<span class="ml-0"> &hellip;</span>', $item['authors']);

            // Publication.
            $publication = !empty($item['publication_title']) ? "<i>{$item['publication_title']}</i>" : $item['reference_type'];
            $date = !empty($item['publication_date']) ? "({$item['publication_date']})" : '';

            // Tags.
            $tags = '';

            foreach ($item['tags'] as $tag_id => $tag) {

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Button');

                $el->elementName('a');
                $el->href("#items/filter?filter[tag][0]={$tag_id}");
                $el->context('outline-primary');
                $el->componentSize('small');
                $el->addClass('mr-1 mb-1');
                $el->html($tag);

                $tags .= $el->render();

                $el = null;
            }

            $titles .= <<<EOT
                <table data-id="{$item['id']}" class="{$theme_classes} item-container mb-2" style="border: 1px solid rgba(0,0,0,0.08);table-layout: fixed;width:100%">
                    <tbody>
                        <tr>
                            <td class="px-3 pt-3" style="width:4.5em;vertical-align: top" rowspan="5">
                                {$pdf_link}
                            </td>
                            <td class="pt-3 pr-3">
                                <h5><a href="{$IL_BASE_URL}index.php/item#summary?id={$item['id']}">{$item['title']}</a></h5>
                                <span class="mr-1">{$authors}</span> <span class="mr-1">{$date}</span> {$publication}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                {$button} {$check}
                                <div class="collapse" id="projects-{$item['id']}">$project_html</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="pt-0 pr-5">
                                <p style="text-align:justify;columns: 2 300px;column-gap: 30px;">{$abstract}</p>
                                <p>{$tags}</p>
                                <p>{$links}</p>
                            </td>
                        </tr>
                        <tr>
                            <td class="row pb-4 pr-5">
                                <div class="col-md-6">
                                    <p><span class="badge badge-secondary rounded-0">{$this->lang->t9n('Notes')}</span></p>
                                    {$notes}
                                </div>
                                <div class="col-md-6">
                                    <p><span class="badge badge-secondary rounded-0">{$this->lang->t9n('PDF notes')}</span></p>
                                    {$pdf_notes}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
EOT;
        }

        return "<div class=\"col\">$titles</div>";
    }

    /**
     * Assemble HTML for the filter menu.
     *
     * @param string $collection
     * @param array $get
     * @return string
     * @throws Exception
     */
    private function filterMenu(string $collection, array $get): string {

        $sanitized_get = $this->sanitation->urlquery($get);
        $sanitized_get = $this->sanitation->html($sanitized_get);

        switch ($collection) {

            case 'library':
                $controller = 'items';
                $browse_url = 'items/main';
                break;

            case 'clipboard':
                $controller = 'clipboard';
                $browse_url = 'clipboard/main';
                break;

            case 'project':
                $controller = 'project';
                $browse_url = 'project/browse';
                break;

            default:
                $controller = 'items';
                $browse_url = 'items/main';
        }

        // We are resetting to page #1.
        unset($sanitized_get['page']);

        // Add Header. Close filter button.
        $close_params = isset($sanitized_get['id']) ? '?id=' . $sanitized_get['id'] : '';

        $context = self::$theme === 'dark' ? 'dark' : 'light';

        /** @var Bootstrap\IconButton $el Close button. */
        $el = $this->di->get('IconButton');

        $el->elementName('a');
        $el->href("#{$browse_url}{$close_params}");
        $el->context($context);
        $el->componentSize('small');
        $el->addClass('px-1 py-0');
        $el->icon("close");
        $close = $el->render();

        $el = null;

        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->id('filter-list');
        $el->addClass('list-group-flush');

        $el->div("<b>Filter</b> {$close}", 'border-0 d-flex justify-content-between');

        // Query params for src attribute.
        $get_query = empty($sanitized_get) ? '' : '?'. http_build_query($sanitized_get);

        // Add filter buttons.
        $action_to_name = [
            'tags' => $this->lang->t9n('Tag'),
            'authors' => $this->lang->t9n('Author'),
            'editors' => $this->lang->t9n('Editor'),
            'addedtime' => $this->lang->t9n('Added date'),
            'primarytitles' => $this->lang->t9n('Primary title'),
            'secondarytitles' => $this->lang->t9n('Secondary title'),
            'tertiarytitles' => $this->lang->t9n('Tertiary title'),
            'publicationtype' => $this->lang->t9n('Publication type'),
            'keywords' => $this->lang->t9n('Keyword'),
            'custom1' =>  $this->lang->t9n($this->app_settings->getGlobal('custom1')),
            'custom2' =>  $this->lang->t9n($this->app_settings->getGlobal('custom2')),
            'custom3' =>  $this->lang->t9n($this->app_settings->getGlobal('custom3')),
            'custom4' =>  $this->lang->t9n($this->app_settings->getGlobal('custom4')),
            'custom5' =>  $this->lang->t9n($this->app_settings->getGlobal('custom5')),
            'custom6' =>  $this->lang->t9n($this->app_settings->getGlobal('custom6')),
            'custom7' =>  $this->lang->t9n($this->app_settings->getGlobal('custom7')),
            'custom8' =>  $this->lang->t9n($this->app_settings->getGlobal('custom8'))
        ];

        // Limit to 3 tags.
        if (isset($get['filter']['tag']) && count($get['filter']['tag']) >= 3) {

            unset($action_to_name['tags']);
        }

        $IL_BASE_URL = IL_BASE_URL;
        $classes = 'list-group-item-action border-0';

        foreach ($action_to_name as $action => $name) {

            $filter_class = in_array($action, ['tags', 'addedtime', 'publicationtype']) === true  ? ' open-filter-local' : ' open-filter-remote';

            $el->button($name, $classes . $filter_class, "data-title=\"$name\" data-src=\"{$IL_BASE_URL}index.php/{$controller}/{$action}{$get_query}\"");
        }

        if ($collection === 'library') {

            $name = $this->lang->t9n('Miscellaneous');

            $el->button($name, $classes . ' open-filter-local', "data-title=\"{$name}\" data-src=\"{$IL_BASE_URL}index.php/items/misc{$get_query}\"");
        }

        $list = $el->render();

        $el = null;

        return $list;
    }

    /**
     * Export to Bibtex, Citations, Endnote, RIS.
     *
     * @param array $items
     * @param string $format
     * @param string $disposition
     * @param string|null $style
     * @return string
     * @throws Exception
     */
    public function export(array $items, string $format, string $disposition = 'inline', string $style = ''): string {

        switch ($format) {

            case 'bibtex':
                /** @var Bibtex $formatter */
                $formatter = $this->di->get('BibtexExport');
                $output = $formatter->format($items['items']);
                $this->append($output);
                $filename = 'export.bib';
                $this->contentType('text');
                $this->setDisposition($disposition, $filename);
                break;

            case 'bibtexabs':
                /** @var Bibtex $formatter */
                $formatter = $this->di->get('BibtexExport');
                $output = $formatter->format($items['items'], true);
                $this->append($output);
                $filename = 'export.bib';
                $this->contentType('text');
                $this->setDisposition($disposition, $filename);
                break;

            case 'citation':
                $this->citationList($items['items'], $style);
                $filename = 'export.html';
                $this->contentType('html');
                $this->setDisposition('inline', $filename);
                break;

            case 'endnote':
                /** @var Endnote $formatter */
                $formatter = $this->di->get('EndnoteExport');
                $output = $formatter->format($items['items']);
                $this->append($output);
                $filename = 'export.txt';
                $this->contentType('text');
                $this->setDisposition($disposition, $filename);
                break;

            case 'ris':
                /** @var Ris $formatter */
                $formatter = $this->di->get('RisExport');
                $output = $formatter->format($items['items']);
                $this->append($output);
                $filename = 'export.ris';
                $this->contentType('text');
                $this->setDisposition($disposition, $filename);
                break;

            default:
                throw new Exception('export format not recognized');
        }

        return $this->send();
    }

    /**
     * Citation list for export.
     *
     * @param array $items
     * @param string $style
     * @throws Exception
     */
    private function citationList(array $items, string $style): void {

        $this->item_meta = $this->di->getShared('ItemMeta');

        $items = $this->sanitation->stripLow($items);
        $style = $this->sanitation->lmth($style);

        $csl_items = [];

        foreach ($items as $item) {

            $authors = [];
            $editors = [];

            if (isset($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']])) {

                foreach ($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']] as $key => $last_name) {

                    $authors[$key]['family'] = $last_name;

                    if (!empty($item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$key])) {

                        $authors[$key]['given'] = $item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$key];
                    }
                }
            }

            if (isset($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']])) {

                foreach ($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']] as $key => $last_name) {

                    $editors[$key]['family'] = $last_name;

                    if (!empty($item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$key])) {

                        $editors[$key]['given'] = $item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$key];
                    }
                }
            }

            $doi = null;
            $doi_key = array_search('DOI', $item[ItemMeta::COLUMN['UID_TYPES']]);

            if ($doi_key !== false) {

                $doi = $item[ItemMeta::COLUMN['UIDS']][$doi_key];
            }

            $csl_items[] = [
                "id" => $item[ItemMeta::COLUMN['BIBTEX_ID']],
                "type" => $this->item_meta->convert($item[ItemMeta::COLUMN['REFERENCE_TYPE']], 'il', 'csl'),
                "title" => $item[ItemMeta::COLUMN['TITLE']],
                "container-title" => $item[ItemMeta::COLUMN['SECONDARY_TITLE']],
                "collection-title" => $item[ItemMeta::COLUMN['TERTIARY_TITLE']],
                "page" => $item[ItemMeta::COLUMN['PAGES']],
                "volume" => $item[ItemMeta::COLUMN['VOLUME']],
                "issue" => $item[ItemMeta::COLUMN['ISSUE']],
                "DOI" => $doi,
                "journalAbbreviation" => $item[ItemMeta::COLUMN['PRIMARY_TITLE']],
                "author" => $authors,
                "editor" => $editors,
                "issued" => [
                    'date-parts' => [
                        [
                            (int) substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']] ?? '', 0, 4),
                            (int) substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']] ?? '', 5, 2),
                            (int) substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']] ?? '', 8, 2),
                        ]
                    ]
                ],
                "publisher" => $item[ItemMeta::COLUMN['PUBLISHER']],
                "publisher-place" => $item[ItemMeta::COLUMN['PLACE_PUBLISHED']]
            ];
        }

        $items_json = Utils::jsonEncode($csl_items, JSON_INVALID_UTF8_SUBSTITUTE);
        $items_json = str_replace(['\r', '\n', '\r\n'], ' ', $items_json);
        $items_json = str_replace('\\', '\\\\', $items_json);
        $items_json = str_replace('"', '\\"', $items_json);
        $style = str_replace(["\r", "\n", "\r\n"], ' ', $style);
        $style = str_replace('"', '\\"', $style);

        $this->style = <<<'STYLE'
table {
    margin:0.75in 1in;
    border-spacing:0;
}
td {
    font-family: serif;
    font-size: 12pt;
    padding:0;
    padding-bottom:1em;
    vertical-align: top;
    line-height: 1.8em
}
.td-csl-left {
    width: 5em
}
STYLE;

        $this->styleLink('css/plugins.css');

        $this->head();

        $this->append('<table><tbody></tbody></table>');

        $this->scriptLink('js/plugins.min.js');
        $this->scriptLink('js/citeproc/citeproc.js');

        $this->script = <<<SCRIPT
// List of citations in JSON format.
let items = "{$items_json}";
let citations = JSON.parse(items);
// Extract all citation keys.
let itemIDs = _.map(citations, 'id');
// User-selected style.
let style = "{$style}";
// Citeproc init options.
citeprocSys = {
    retrieveLocale: function (lang) {
        let xhr = new XMLHttpRequest();
        xhr.open('GET', window.IL_BASE_URL + 'js/citeproc/locales/locales-' + lang + '.xml', false);
        xhr.send(null);
        return xhr.responseText;
    },
    retrieveItem: function (id) {
        return _.filter(citations, _.matches({ 'id': id }))[0];
    }
};
// Render citations.
let citeproc = new CSL.Engine(citeprocSys, style);
citeproc.updateItems(itemIDs);
let bibResult = citeproc.makeBibliography();
if (typeof bibResult === 'object') {
    // Load citations into the table.
    $.each(bibResult[1], function (key, val) {
        // Two columns vs one column layout.
        if ($(val).children("div").length === 2) {
            $('tbody').append('<tr><td class="td-csl-left" valign="top" width="80">'
                + $(val).children("div").eq(0).html() + '</td><td valign="top">'
                + $(val).children("div").eq(1).html() + '</td></tr>');
        } else if ($(val).children("div").length === 0) {
            $('tbody').append('<tr><td valign="top">' + $(val).html() + '</td></tr>');
        }
    });
    // Final CSS formatting.
    $('td').css('line-height', 1.2 * bibResult[0]['linespacing'] + 'em')
        .css('padding-bottom', bibResult[0]['entryspacing'] + 'em');
    $('.td-csl-left').css('width', bibResult[0]['maxoffset'] + 'em')
        .attr('width', 16 * bibResult[0]['maxoffset']);
}
SCRIPT;

        $this->end();
    }

    /**
     * Catalog cards.
     *
     * @param $count
     * @return string
     * @throws Exception
     */
    public function catalog($count) {

        $this->title($this->lang->t9n('Catalog'));
        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item($this->lang->t9n('Catalog'));
        $bc = $el->render();

        $el = null;

        if ((int) $count['max_id'] === 0) {

            $this->append(['html' => "$bc"]);

            return $this->send();
        }

        $range  = $this->app_settings->getGlobal('max_items');
        $starts = $range >= $count['max_id'] ? [0 => 1] : range(1, $count['max_id'], $range);

        // Higher to lower.
        rsort($starts);

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        foreach ($starts as $value) {

            $end = min($value + $range - 1, $count['max_id']);
            $val_formatted = $this->scalar_utils->formatNumber((int) $value);
            $end_formatted = $this->scalar_utils->formatNumber((int) $end);
            $link = '#items/catalog?from_id=';

            /** @var Bootstrap\Card $el */
            $crd = $this->di->get('Card');

            $crd->body("<a href=\"$link{$value}\">{$this->lang->t9n('Items')}<br> {$val_formatted} - {$end_formatted}</a>", null, 'py-4');
            $el->column($crd->render(), 'col-xl-3 col-lg-4 col-md-6 mb-3 text-center');

            $crd = null;
        }

        $row = $el->render();

        $el = null;

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }

    /**
     * Export window form.
     *
     * @param bool $single
     * @return string
     * @throws Exception
     */
    public function exportForm($single = false): string {

        $inputs = "<div class=\"mb-2\"><b>{$this->lang->t9n('Format-NOUN')}</b></div>";

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio3');
        $el->type('radio');
        $el->name('export');
        $el->value('bibtex');
        $el->checked('checked');
        $el->label('B<span style="font-size: 0.85rem">IB</span>T<div class="d-inline-block" style="transform: translateY(2px)">E</div>X');
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio3-2');
        $el->type('radio');
        $el->name('export');
        $el->value('bibtexabs');
        $el->checked('checked');
        $el->label('B<span style="font-size: 0.85rem">IB</span>T<div class="d-inline-block" style="transform: translateY(2px)">E</div>X + ' . $this->lang->t9n('abstracts'));
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio1');
        $el->type('radio');
        $el->name('export');
        $el->value('endnote');
        $el->label('Endnote');
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio2');
        $el->type('radio');
        $el->name('export');
        $el->value('ris');
        $el->label('RIS');
        $inputs .= $el->render();

        $el = null;

        if ($single === false) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('export-radio4');
            $el->type('radio');
            $el->name('export');
            $el->value('zip');
            $el->label("{$this->lang->t9n('offline app')} <span class=\"text-secondary\">({$this->lang->t9n('up to 500 MB')})</span>");
            $inputs .= $el->render();

            $el = null;
        }

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio5');
        $el->type('radio');
        $el->name('export');
        $el->value('citation');
        $el->label($this->lang->t9n('citation style'));
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Typeahead $el */
        $el = $this->di->get('Typeahead');

        $el->id('export-styles');
        $el->name('style');
        $el->attr('data-source', IL_BASE_URL . 'index.php/filter/citation');
        $el->attr('data-minLength', '1');
        $el->placeholder($this->lang->t9n('Search styles'));
        $inputs .= $el->render();

        $el = null;

        $inputs .= "<div class=\"mt-3 mb-2\"><b>{$this->lang->t9n('Output options')}</b></div>";

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio6');
        $el->type('radio');
        $el->name('disposition');
        $el->value('inline');
        $el->checked('checked');
        $el->label($this->lang->t9n('display in browser'));
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio7');
        $el->type('radio');
        $el->name('disposition');
        $el->value('attachment');
        $el->label($this->lang->t9n('download file'));
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('export-form');
        $el->method('GET');
        $el->target('_blank');
        $el->action(IL_BASE_URL . 'index.php/items/export');
        $el->html($inputs);
        $form = $el->render();

        $el = null;

        $this->append(['html' => $form]);

        return $this->send();
    }

    /**
     * Omnitool form.
     *
     * @param array $projects
     * @param array $tags
     * @return string
     * @throws Exception
     */
    public function omnitoolForm(array $projects, array $tags): string {

        /** @var Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $arrow = $el->render();

        $el = null;

        $inputs = "<div class=\"mb-2\"><b>{$this->lang->t9n('Clipboard')}</b></div>";

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio1');
        $el->type('radio');
        $el->name('omnitool[clipboard]');
        $el->value('add');
        $el->label($this->lang->t9n('add'));
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio2');
        $el->type('radio');
        $el->name('omnitool[clipboard]');
        $el->value('remove');
        $el->label($this->lang->t9n('remove'));
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio3');
        $el->type('radio');
        $el->name('omnitool[clipboard]');
        $el->value('');
        $el->checked('checked');
        $el->label($this->lang->t9n('no action'));
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->id('omnitool-select-project');
        $el->groupClass('mt-3');
        $el->name('omnitool[project_id]');

        foreach ($projects as $project) {

            $el->option($project['project'], $project['id']);
        }

        $el->label($this->lang->t9n('Project'));
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio4');
        $el->type('radio');
        $el->name('omnitool[project]');
        $el->value('add');
        $el->label($this->lang->t9n('add'));
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio5');
        $el->type('radio');
        $el->name('omnitool[project]');
        $el->value('remove');
        $el->label($this->lang->t9n('remove'));
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio6');
        $el->type('radio');
        $el->name('omnitool[project]');
        $el->value('');
        $el->checked('checked');
        $el->label($this->lang->t9n('no action'));
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        $inputs .= "<div class=\"mt-3 mb-2 cursor-pointer\" data-toggle=\"collapse\" data-target=\"#omnitool-tags\">$arrow<b>{$this->lang->t9n('Tags')}</b></div>";

        // Filter input.
        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('tag-filter-omnitool');
        $el->name('tag_filter');
        $el->placeholder($this->lang->t9n('Filter-VERB'));
        $el->ariaLabel($this->lang->t9n('Filter-VERB'));
        $el->attr('data-targets', '#omnitool-tags .label-text');
        $tag_checkboxes = $el->render();

        $el = null;

        // Tags.
        $first_letter = '';
        $i = 0;

        $tag_checkboxes .= '<table class="tag-table"><tr><td style="width:2.25rem"></td><td>';

        foreach ($tags as $tag_id => $tag) {

            $first_letter2 = mb_strtoupper($this->scalar_utils->deaccent($tag[0] === '' ? '' : mb_substr($tag, 0, 1, 'UTF-8')), 'UTF-8');

            if ($first_letter2 !== $first_letter) {

                $tag_checkboxes .= '</td></tr><tr>';

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Badge');

                $el->context('warning');
                $el->addClass('d-inline-block mr-2 mb-2');
                $el->style('width: 1.33rem');
                $el->html($first_letter2);
                $tag_checkboxes .= '<td>' . $el->render() . '</td><td>';

                $el = null;

                $first_letter = $first_letter2;
            }

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('omnitool-checkbox-tags-' . $i);
            $el->type('checkbox');
            $el->name("omnitool[tags][$i]");
            $el->value($tag_id);
            $el->label($tag);
            $el->inline(true);

            $tag_checkboxes .= $el->render();

            $el = null;

            $i++;
        }

        $tag_checkboxes .= '</td></tr></table>';

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Element');

        $el->id('omnitool-tags');
        $el->addClass('mb-3 collapse');
        $el->html($tag_checkboxes);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio7');
        $el->type('radio');
        $el->name('omnitool[tag]');
        $el->value('add');
        $el->label($this->lang->t9n('add'));
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio8');
        $el->type('radio');
        $el->name('omnitool[tag]');
        $el->value('remove');
        $el->label($this->lang->t9n('remove'));
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio9');
        $el->type('radio');
        $el->name('omnitool[tag]');
        $el->value('');
        $el->checked('checked');
        $el->label($this->lang->t9n('no action'));
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        // Only admins, and users can delete.
        if ($this->session->data('permissions') === 'A' || $this->session->data('permissions') === 'U') {

            $inputs .= "<div class=\"mt-3 mb-2 cursor-pointer text-danger\" data-toggle=\"collapse\" data-target=\"#delete-all\">$arrow<b>{$this->lang->t9n('Delete all')}</b></div>";

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('omnitool-checkbox1');
            $el->type('checkbox');
            $el->name('omnitool[delete]');
            $el->value('1');
            $el->label($this->lang->t9n('permanently delete all items'));
            $inputs .= '<div class="collapse" id="delete-all">' . $el->render() . '</div>';

            $el = null;

        }

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->method('GET');
        $el->target('_blank');
        $el->action(IL_BASE_URL . 'index.php/items/main');
        $el->html($inputs);
        $form = $el->render();

        $el = null;

        $this->append(['html' => $form]);

        return $this->send();
    }

    /**
     * Generate internal search name.
     *
     * @param array $get
     * @return string
     * @throws Exception
     */
    public function searchName(array $get): string {

        $search_name = '';

        // Search tags.
        if (isset($get['search_type'])) {

            // Custom customN names.
            for ($i = 1; $i <= 8; $i++) {

                $this->type_to_readable["C{$i}"] = $this->lang->t9n($this->app_settings->getGlobal("custom{$i}"));
            }

            foreach ($get['search_type'] as $i => $type) {

                if ($get['search_query'][$i] === '') {

                    continue;
                }

                $search_name .= " {$this->type_to_readable[$type]}: {$get['search_query'][$i]}";
            }
        }

        return $search_name;
    }

    /**
     * Convert search array parameters to a PDF string search.
     *
     * @param array $get
     * @return string
     */
    private function toPdfSearch(array $get): string {

        $search = '';

        // Get the first valid search query.
        foreach ($get['search_query'] as $key => $query) {

            if (empty($query) || $get['search_type'][$key] === 'itemid') {

                continue;
            }

            // Remove wildcards.
            $search = trim(str_replace('*', '', $query));

            // Get only first token, if query is not a phrase.
            if ($get['search_boolean'][$key] !== 'PHRASE') {

                $query_tokens = explode(' ', $query);
                $search = $query_tokens[0] ?? '';
            }

            if (empty($search)) {

                continue;
            }

            break;
        }

        return $search;
    }
}
