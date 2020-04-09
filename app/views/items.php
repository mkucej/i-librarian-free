<?php

namespace LibrarianApp;

use Exception;
use Librarian\Export\Bibtex;
use Librarian\Export\Endnote;
use Librarian\Export\Ris;
use Librarian\Html\Bootstrap;
use Librarian\Html\Bootstrap\Icon;
use Librarian\Html\Element;
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
    private $type_to_readable = [
        'anywhere'  => 'Metadata & PDFs',
        'metadata'  => 'Metadata',
        'FT'        => 'PDFs',
        'pdfnotes'  => 'PDF notes',
        'itemnotes' => 'Notes',
        'itemid'    => 'Item #',
        'TI'        => 'Titles',
        'AB'        => 'Titles & abstracts',
        'AU'        => 'Authors & editors',
        'AF'        => 'Affiliations',
        'T1'        => 'Primary titles',
        'T2'        => 'Secondary titles',
        'T3'        => 'Tertiary titles',
        'KW'        => 'Keywords',
        'YR'        => 'Years'
    ];

    /**
     * Assemble item browsing page and send it.
     *
     * @param string $collection Specify library, clipboard.
     * @param array $get GET super global array.
     * @param array $input [array items, integer total_count]
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
                $title = "Page {$page} - Clipboard";
                break;

            case 'project':
                $title = "Page {$page} - Project - {$input['project']}";
                break;

            case 'catalog':
                $title = "Page {$page} - Catalog";
                break;

            default:
                $title = "Page {$page} - Library";
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
        $el->html('Previous searches');
        $searches_button = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('link');
        $el->attr('data-dismiss', 'modal');
        $el->attr('data-toggle', 'modal');
        $el->dataTarget('#modal-advanced-search');
        $el->html('Advanced search');
        $advanced_button = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-quick-search');
        $el->header('Search ' . $modal_header);
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
        $el->html('Quick search');
        $button = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->addClass('search-submit');
        $el->context('primary');
        $el->html('Search');
        $search_button = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-advanced-search');
        $el->header('Search ' . $modal_header);
        $el->button($searches_button);
        $el->button($button);
        $el->button($search_button);
        $el->body($this->sharedAdvancedSearch($action), 'bg-darker-5');
        $el->componentSize('large');
        $advanced_search = $el->render();

        $el = null;

        $html = "$page_rows $quick_search $advanced_search";

        $this->append([
            'html'    => $html,
            'id_list' => array_column($input['items'], 'id')
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
                $bc_title = "Clipboard";
                break;

            case 'project':
                $bc_title = $input['project'];
                break;

            case 'catalog':
                $bc_title = "Items {$get['from_id']} - " .
                    ($this->scalar_utils->formatNumber(-1 + $get['from_id'] + $this->app_settings->getGlobal('max_items')));
                break;

            default:
                $bc_title = "Library";
        }

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');

        if ($collection === 'project') {

            $el->item('Projects', IL_BASE_URL . 'index.php/#projects/main');
        }

        if ($collection === 'catalog') {

            $el->item('Catalog', IL_BASE_URL . 'index.php/#items/catalog');
        }

        $el->item("{$bc_title}");
        $bc = $el->render();

        $el = null;

        // Item count.

        if (empty($input['total_count'])) {

            $item_count = "<div class=\"text-muted w-100 pb-3 pb-lg-0\">No items</div>";

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
            $item_count = "<div class=\"text-muted w-100 pb-3 pb-lg-0\">Items $from - $last of {$total_count}</div>";
        }

        $tags_html = '';

        // Search tags.
        if (isset($get['search_type'])) {

            for ($i = 1; $i <= 8; $i++) {

                $this->type_to_readable["[C{$i}]"] = $this->app_settings->getGlobal("custom{$i}");
            }

            foreach ($get['search_type'] as $i => $type) {

                if ($get['search_query'][$i] === '') {

                    continue;
                }

                $tags_html .= <<<EOT
                <span class="bg-dark text-white d-inline-block mr-1 mb-2 px-3 py-1" style="font-size: 0.85rem">
                    <b>{$this->type_to_readable[$type]}</b> &mdash; {$get['search_query'][$i]}
                </span>
EOT;
            }
        }

        // Filter tags.
        if (!empty($input['filters'])) {

            /** @var Bootstrap\Icon $el Close icon. */
            $el = $this->di->get('Icon');

            $el->icon("close");
            $close = $el->render();

            $el = null;

            foreach ($input['filters'] as $filter) {

                if (empty($filter)) {

                    continue;
                }

                // Format dates to local format.
                if ($filter['name'][0] === 'added_time') {

                    $this->temporal_obj = $this->di->get('Temporal');
                    $filter['value'][1] = $this->temporal_obj->toUserDate($filter['value'][1]);
                }

                // Construct a URL for the Remove filter button.
                $removed_get = $get;

                // Always remove page, because the link generates a new item list.
                unset ($removed_get['page']);

                // Remove this tag URL.
                $remove_key = array_search($filter['value'][0], $removed_get['filter'][$filter['name'][0]]);
                unset($removed_get['filter'][$filter['name'][0]][$remove_key]);

                // Build query.
                $get_query = http_build_query($removed_get);
                $get_query = empty($get_query) ? '' : '?'. $get_query;

                $close_url = '#' . IL_PATH_URL . $get_query;

                $tags_html .= <<<EOT
                    <div class="btn-group mr-1 mb-2" role="group" aria-label="Filter button">
                        <button type="button" class="btn btn-sm btn-dark rounded-0">
                            <b>{$filter['name'][1]}</b> &mdash; {$filter['value'][1]}
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
        $el->html("<span class=\"d-none d-xl-inline-block\" style='width:3.5rem'>Search</span>$search_icon");
        $search_toggle = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('d-xl-none');
        $el->icon('filter');
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
        $el->html("<span class=\"d-none d-xl-inline-block\" style='width:3.5rem'>Filter</span>$filter_icon");
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
        $el->icon('briefcase-download');
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
        $el->html("<span class=\"d-none d-xl-inline\">Export</span>$export_icon");
        $export_button = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('d-xl-none');
        $el->icon('wrench');
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
        $el->html("<span class=\"d-none d-xl-inline\">Omnitool</span>$omnitool_icon");
        $omnitool_button = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('d-xl-none');
        $el->icon('sort-alphabetical');
        $sort_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('open-settings');
        $el->context($btn_class_b);
        $el->addClass('border-0');
        $el->dataToggle('modal');
        $el->dataTarget('#modal-settings');
        $el->html("<span class=\"d-none d-xl-inline\">Display</span>$sort_icon");
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
        $el->column("$search_toggle $filter_toggle $export_button $omnitool_button $display_button", 'col-12 col-xl-8 p-0 my-2 text-center text-xl-left');
        $el->column("$first_button $prev_button $next_button $last_button", 'col-12 col-xl-4 p-0 my-2 text-center text-xl-right');
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

            if ($item['has_pdf'] === '1') {

                $get = $this->request->getQueryParams();
                $search = '';

                if (isset($get['search_query']) && $get['search_type'][0] !== 'itemid') {

                    $search = '&search=' . rawurlencode(trim(join(' ', $get['search_query'])));
                }

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->elementName('a');
                $el->href("{$IL_BASE_URL}index.php/pdf?id={$item['id']}" . $search);
                $el->addClass('px-2 py-1 border-0');
                $el->context('primary');
                $el->html('PDF');
                $pdf = $el->render();

                $el = null;

                /** @var Bootstrap\IconButton $el */
                $el = $this->di->get('IconButton');

                $el->elementName('a');
                $el->href("{$IL_BASE_URL}index.php/pdf/file?disposition=attachment&id={$item['id']}");
                $el->addClass('px-2 py-0 border-0');
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

                $el->addClass('px-2 py-1 bg-darker-5 border-0');
                $el->html('PDF');
                $el->disabled('disabled');
                $pdf = $el->render();

                $el = null;

                /** @var Bootstrap\IconButton $el */
                $el = $this->di->get('IconButton');

                $el->addClass('px-2 py-0 bg-darker-5 border-0');
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
            $el->label('Clipboard');
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
            $el->html("{$chevron}Projects");
            $button = $el->render();

            $el = null;

            $project_html = empty($item['projects']) ? '<span class="text-secondary">No projects yet.</span>' : '';

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

            // Authors
            $authors = '';

            if (!empty($item['authors'])) {
                $authors = join('; ', $item['authors']);
                $authors = "<div class=\"truncate mb-1\">{$authors}</div>";
            }  

            // Top HTML structure.

            $titles .= <<<EOT
                <table data-id="{$item['id']}" class="{$theme_classes} item-container mb-2" style="border: 1px solid rgba(0,0,0,0.08);table-layout: fixed;width:100%">
                    <tbody>
                        <tr>
                            <td class="py-3 pl-3 pr-0" rowspan="2" style="vertical-align: top;width: 4.25em">
                                $pdf_link
                            </td>
                            <td class="pl-1 pt-3 pr-4" style="height: 3.5rem">
                                <h5><a href="{$IL_BASE_URL}index.php/item#summary?id={$item['id']}">{$item['title']}</a></h5>
                                {$authors}
                            </td>
                        </tr>
                        <tr>
                            <td class="pr-4" style="vertical-align: top">
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
            $el->label('Clipboard');
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
            $el->html("{$chevron}Projects");
            $button = $el->render();

            $el = null;

            $project_html = empty($item['projects']) ? '<span class="text-secondary">No projects yet.</span>' : '';

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

            $get = $this->request->getQueryParams();
            $search = '';

            if (isset($get['search_query']) && $get['search_type'][0] !== 'itemid') {

                $search = '&search=' . rawurlencode(trim(join(' ', $get['search_query'])));
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
                <div class="px-3 pt-3 pb-2 border-darker-top">
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

            if ($item['has_pdf'] === '1') {

                $get = $this->request->getQueryParams();
                $search = '';

                if (isset($get['search_query']) && $get['search_type'][0] !== 'itemid') {

                    $search = '&search=' . rawurlencode(trim(join(' ', $get['search_query'])));
                }

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->elementName('a');
                $el->href("{$IL_BASE_URL}index.php/pdf?id={$item['id']}" . $search);
                $el->addClass('px-2 py-1 border-0');
                $el->context('primary');
                $el->html('PDF');
                $pdf = $el->render();

                $el = null;

                /** @var Bootstrap\IconButton $el */
                $el = $this->di->get('IconButton');

                $el->elementName('a');
                $el->href("{$IL_BASE_URL}index.php/pdf/file?disposition=attachment&id={$item['id']}");
                $el->addClass('px-2 py-0 border-0');
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

                $el->addClass('px-2 py-1 bg-darker-5 border-0');
                $el->html('PDF');
                $el->disabled('disabled');
                $pdf = $el->render();

                $el = null;

                /** @var Bootstrap\IconButton $el */
                $el = $this->di->get('IconButton');

                $el->addClass('px-2 py-0 bg-darker-5 border-0');
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
            $el->label('Clipboard');
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
            $el->html("{$chevron}Projects");
            $button = $el->render();

            $el = null;

            $project_html = empty($item['projects']) ? '<span class="text-secondary">No projects yet.</span>' : '';

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
            $abstract = empty($item['abstract']) ? 'No abstract' : $item['abstract'];

            // Authors
            $authors = '';

            if (!empty($item['authors'])) {
                $authors = join('; ', $item['authors']);
                $authors = "<div class=\"truncate mb-1\">{$authors}</div>";
            }  

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

            $titles .= <<<EOT
                <table data-id="{$item['id']}" class="{$theme_classes} item-container mb-2" style="table-layout: fixed;width:100%">
                    <tbody>
                        <tr>
                            <td class="px-3 pt-3" style="width:4.5em;vertical-align: top" rowspan="5">
                                $pdf_link
                            </td>
                            <td class="pt-3 pr-3">
                                <h5><a href="{$IL_BASE_URL}index.php/item#summary?id={$item['id']}">{$item['title']}</a></h5>
                                {$authors}
                            </td>
                        </tr>
                        <tr>
                            <td class="pt-0 pb-2">
                                $button $check
                                <div class="collapse" id="projects-{$item['id']}">$project_html</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="pt-0 pb-3 pr-5">
                                <div style="text-align:justify;columns: 2 300px;column-gap: 30px;">{$abstract}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="row pt-0 pb-4 pr-5">
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
            'tags' => 'Tags',
            'authors' => 'Authors',
            'editors' => 'Editors',
            'addedtime' => 'Added dates',
            'primarytitles' => 'Primary titles',
            'secondarytitles' => 'Secondary titles',
            'tertiarytitles' => 'Tertiary titles',
            'keywords' => 'Keywords',
            'custom1' =>  $this->app_settings->getGlobal('custom1'),
            'custom2' =>  $this->app_settings->getGlobal('custom2'),
            'custom3' =>  $this->app_settings->getGlobal('custom3'),
            'custom4' =>  $this->app_settings->getGlobal('custom4'),
            'custom5' =>  $this->app_settings->getGlobal('custom5'),
            'custom6' =>  $this->app_settings->getGlobal('custom6'),
            'custom7' =>  $this->app_settings->getGlobal('custom7'),
            'custom8' =>  $this->app_settings->getGlobal('custom8')
        ];

        // Limit to 3 tags.
        if (isset($get['filter']['tag']) && count($get['filter']['tag']) >= 3) {

            unset($action_to_name['tags']);
        }

        $IL_BASE_URL = IL_BASE_URL;
        $classes = 'list-group-item-action border-0 open-filter';

        foreach ($action_to_name as $action => $name) {

            $el->button($name, $classes, "data-title=\"$name\" data-src=\"{$IL_BASE_URL}index.php/{$controller}/{$action}{$get_query}\"");
        }

        if ($collection === 'library') {

            $el->button('Miscellaneous', $classes, "data-title=\"Miscellaneous\" data-src=\"{$IL_BASE_URL}index.php/items/misc{$get_query}\"");
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
                            (int) substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']], 0, 4),
                            (int) substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']], 5, 2),
                            (int) substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']], 8, 2),
                        ]
                    ]
                ],
                "publisher" => $item[ItemMeta::COLUMN['PUBLISHER']],
                "publisher-place" => $item[ItemMeta::COLUMN['PLACE_PUBLISHED']]
            ];
        }

        $items_json = \Librarian\Http\Client\json_encode($csl_items, JSON_INVALID_UTF8_SUBSTITUTE);
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

        $this->scriptLink('js/plugins.js');
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

        $this->title('Catalog');
        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item("Catalog");
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

            $crd->body("<a href=\"$link{$value}\">Items<br> {$val_formatted} - {$end_formatted}</a>", null, 'py-4');
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

        $inputs = '<div class="mb-2"><b>Format</b></div>';

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
            $el->label('zipped HTML <span class="text-secondary">(incl. 250 MB PDFs)</span>');
            $inputs .= $el->render();

            $el = null;
        }

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio5');
        $el->type('radio');
        $el->name('export');
        $el->value('citation');
        $el->label('citation style');
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Typeahead $el */
        $el = $this->di->get('Typeahead');

        $el->id('export-styles');
        $el->name('style');
        $el->attr('data-source', IL_BASE_URL . 'index.php/filter/citation');
        $el->attr('data-minLength', '1');
        $inputs .= $el->render();

        $el = null;

        $inputs .= '<div class="mt-3 mb-2"><b>Output options</b></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio6');
        $el->type('radio');
        $el->name('disposition');
        $el->value('inline');
        $el->checked('checked');
        $el->label('display in browser');
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('export-radio7');
        $el->type('radio');
        $el->name('disposition');
        $el->value('attachment');
        $el->label('download a file');
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

        $inputs = '<div class="mb-2"><b>Clipboard</b></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio1');
        $el->type('radio');
        $el->name('omnitool[clipboard]');
        $el->value('add');
        $el->label('add');
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio2');
        $el->type('radio');
        $el->name('omnitool[clipboard]');
        $el->value('remove');
        $el->label('remove');
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
        $el->label('no action');
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

        $el->label('Project');
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio4');
        $el->type('radio');
        $el->name('omnitool[project]');
        $el->value('add');
        $el->label('add');
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio5');
        $el->type('radio');
        $el->name('omnitool[project]');
        $el->value('remove');
        $el->label('remove');
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
        $el->label('no action');
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        $inputs .= "<div class=\"mt-3 mb-2 cursor-pointer\" data-toggle=\"collapse\" data-target=\"#omnitool-tags\">$arrow<b>Tags</b></div>";

        $tag_html = '';

        foreach ($tags as $id => $tag) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('omnitool-checkbox-tags-' . $id);
            $el->type('checkbox');
            $el->name('omnitool[tags][]');
            $el->value($id);
            $el->label($tag);
            $el->inline(true);
            $tag_html .= $el->render();

            $el = null;
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Element');

        $el->id('omnitool-tags');
        $el->addClass('mb-3 collapse');
        $el->html($tag_html);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio7');
        $el->type('radio');
        $el->name('omnitool[tag]');
        $el->value('add');
        $el->label('add');
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('omnitool-radio8');
        $el->type('radio');
        $el->name('omnitool[tag]');
        $el->value('remove');
        $el->label('remove');
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
        $el->label('no action');
        $el->inline(true);
        $inputs .= $el->render();

        $el = null;

        // Only admin can delete.
        if ($this->session->data('permissions') === 'A') {

            $inputs .= "<div class=\"mt-3 mb-2 cursor-pointer text-danger\" data-toggle=\"collapse\" data-target=\"#delete-all\">$arrow<b>Delete all</b></div>";

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('omnitool-checkbox1');
            $el->type('checkbox');
            $el->name('omnitool[delete]');
            $el->value('1');
            $el->label('permanently delete all items');
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

                $this->type_to_readable["[C{$i}]"] = $this->app_settings->getGlobal("custom{$i}");
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
}
