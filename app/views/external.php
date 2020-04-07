<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Element;
use Librarian\Html\Bootstrap;
use Librarian\ItemMeta;
use Librarian\Mvc\TextView;

/**
 * Class ExternalView.
 *
 * Displays results of external repository search.
 */
class ExternalView extends TextView {

    use SharedHtmlView;

    /**
     * @var ItemMeta
     */
    private $item_meta;

    /**
     * @param $database
     * @param $items
     * @param $from
     * @param string $search_name
     * @return string
     * @throws Exception
     */
    public function results($database, $items, $from, $search_name = '') {

        $get = $this->request->getQueryParams();
        $trimmed_get = $this->sanitation->trim($get);
        $sanitized_get = $this->sanitation->html($trimmed_get);

        $this->title("$database search");

        $this->head();

        $this->item_meta = $this->di->getShared('ItemMeta');

        $search_name = empty($search_name) ? '' : "&ndash; $search_name";

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("{$database} search {$search_name}");
        $bc = $el->render();

        $el = null;

        $page_from = $this->scalar_utils->formatNumber($from);
        $page_to = $this->scalar_utils->formatNumber($from + count($items['items']) - 1);
        $found = $this->scalar_utils->formatNumber($items['found'] ?? 0);

        if ($found === '0') {

            $item_count = '<div class="text-muted w-100 pb-3 pb-lg-0">No results found.</div>';

        } else {

            $item_count = "<div class=\"text-muted w-100 pb-3 pb-xl-0\">Results $page_from - $page_to of $found</div>";
        }

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->addClass('my-2');
        $el->context('danger');
        $el->html('Save');
        $save = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $el->addClass('mdi-18px');
        $chevron = $el->render();

        $el = null;

        // Items.
        $titles = '';
        $i = 1;

        /** @var Bootstrap\Badge $el */
        $el = $this->di->get('Badge');

        $el->context('warning');
        $el->addClass('mb-2');
        $el->html('IN LIBRARY');
        $exists_badge = $el->render();

        $el = null;

        foreach ($items['items'] as $article) {

            $exists   = isset($article['exists']) && $article['exists'] === 'Y' ? $exists_badge: '';
            $title    = $this->sanitation->html($article[ItemMeta::COLUMN['TITLE']] ?? '');
            $author   = $this->sanitation->html($article[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][0] ?? '');
            $year     = $this->sanitation->html($article[ItemMeta::COLUMN['PUBLICATION_DATE']] ?? null);
            $year     = empty($year) ? 'No date' : substr($year, 0, 4);
            $publication = !empty($article[ItemMeta::COLUMN['TERTIARY_TITLE']]) ? $article[ItemMeta::COLUMN['TERTIARY_TITLE']] : '';
            $publication = !empty($article[ItemMeta::COLUMN['SECONDARY_TITLE']]) ? $article[ItemMeta::COLUMN['SECONDARY_TITLE']] : $publication;
            $publication = !empty($article[ItemMeta::COLUMN['PRIMARY_TITLE']]) ? $article[ItemMeta::COLUMN['PRIMARY_TITLE']] : $publication;
            $abstract = $this->sanitation->html($article[ItemMeta::COLUMN['ABSTRACT']] ?? '');
            $link     = $article[ItemMeta::COLUMN['URLS']][0] ?? null;
            $pdf_link = $article[ItemMeta::COLUMN['URLS']][1] ?? null;

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->elementName('div');
            $el->addClass('cursor-pointer d-inline-block mb-2 add-pdf-btn');
            $el->dataToggle('collapse');
            $el->dataTarget("#pdf-form-{$i}");
            $el->html("{$chevron}Add PDF");
            $pdf_button = $el->render();

            $el = null;

            $metadata = $this->sanitation->attr(\Librarian\Http\Client\json_encode($article));

            // Upload form.

            /** @var Bootstrap\Input $el Metadata. */
            $el = $this->di->get('Input');

            $el->type('hidden');
            $el->name('metadata');
            $el->value($metadata);
            $hidden = $el->render();

            $el = null;

            // We put CSRF key here, because the JS file upload plugin has its own AJAX methods.

            /** @var Bootstrap\Input $el CSRF key. */
            $el = $this->di->get('Input');

            $el->type('hidden');
            $el->name('csrfToken');
            $el->value($this->session->data('token'));
            $csrf_input = $el->render();

            $el = null;

            /** @var Bootstrap\Form $el */
            $el = $this->di->get('Form');

            $el->addClass('save-form');
            $el->action(IL_BASE_URL . 'index.php/import/manual');
            $el->append(<<<EOT
                <div id="pdf-form-{$i}" class="collapse" style="width: 290px">
                    {$this->sharedFileInput(false, $pdf_link)}
                </div>
EOT
            );
            $el->append("$hidden $csrf_input $save");
            $form = $el->render();

            $el = null;

            $title = <<<EOT
$exists
                <h5><a href="{$link}">{$title}</a></h5>
                <p>$author <span class="ml-1">({$year})</span> <span class="ml-1">$publication</span></p>
                <p style="text-align:justify;columns: 2 300px;column-gap: 30px;">{$abstract}</p>
                $pdf_button
                <div>$form</div>
EOT;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass("mb-3");
            $el->body($title, null, 'pt-4');
            $titles .= $el->render();

            $el = null;

            $i++;

            if ($i === 11) {

                break;
            }
        }

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->id('top-row');
        $el->style('overflow: auto');
        $el->addClass("d-flex align-content-start");
        $el->column($bc, 'col-xl-9');
        $el->column($item_count, 'col-xl-3 text-center text-xl-right d-flex align-items-center');
        $el->column($titles, 'col-xl-12');
        $top_row = $el->render();

        $el = null;

        $btn_class = self::$theme === 'dark' ? 'secondary' : 'outline-dark';
        $bar_class = self::$theme === 'dark' ? 'bg-secondary' : 'list-group-item-secondary';

        // First page.
        unset($sanitized_get['from']);
        $first_page_q = empty($sanitized_get) ? '' : '?'. http_build_query($sanitized_get);

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->elementName('a');
        $el->href('#' . IL_PATH_URL . $first_page_q);
        $el->context($btn_class);
        $el->addClass('border-0 ');
        $el->icon('chevron-double-left');
        $el->html('Go to first page');

        // Disabling.
        if ($from === 1) {

            $el->elementName('button');
            $el->removeAttr('href');
            $el->disabled('disabled');
        }

        $first_button = $el->render();

        $el = null;

        // Previous page.
        $prev_disabled = true;
        $prev_page = $from - 10;

        if ($prev_page >= 1) {

            $prev_disabled = false;
            $sanitized_get['from'] = $prev_page;
        }

        $prev_page_q = empty($sanitized_get) ? '' : '?'. http_build_query($sanitized_get);

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->elementName('a');
        $el->href('#' . IL_PATH_URL . $prev_page_q);
        $el->context($btn_class);
        $el->addClass('border-0 ');
        $el->icon('chevron-left');
        $el->html('Go to previous page');

        // Disabling.
        if ($prev_disabled) {

            $el->elementName('button');
            $el->removeAttr('href');
            $el->disabled('disabled');
        }

        unset($sanitized_get['from']);

        $prev_button = $el->render();

        $el = null;

        // Next page.
        $next_disabled = true;
        $next_page = $from + 10;

        // Enforce max_items.
        if ($next_page <= $this->app_settings->getGlobal('max_items') &&
            $next_page <= $items['found']) {

            $next_disabled = false;
            $sanitized_get['from'] = $next_page;
        }

        $next_page_q = empty($sanitized_get) ? '' : '?'. http_build_query($sanitized_get);

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->elementName('a');
        $el->href('#' . IL_PATH_URL . $next_page_q);
        $el->context($btn_class);
        $el->addClass('border-0 ');
        $el->icon('chevron-right');
        $el->html('Go to next page');

        // Disabling.
        if ($next_disabled) {

            $el->elementName('button');
            $el->removeAttr('href');
            $el->disabled('disabled');
        }

        unset($sanitized_get['from']);

        $next_button = $el->render();

        $el = null;

        // Last page.
        $max_items = min($items['found'], $this->app_settings->getGlobal('max_items'));
        $page_modulus = $max_items % 10;

        $last_page = $page_modulus === 0 ? $max_items - 9 : floor($max_items / 10) * 10 + 1;

        $sanitized_get['from'] = $last_page;

        $last_page_q = empty($sanitized_get) ? '' : '?'. http_build_query($sanitized_get);

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->elementName('a');
        $el->href('#' . IL_PATH_URL . $last_page_q);
        $el->context($btn_class);
        $el->addClass('border-0 ');
        $el->icon('chevron-double-right');
        $el->html('Go to last page');

        // Disabling.
        if ((integer) $last_page === (integer) $from) {

            $el->elementName('button');
            $el->removeAttr('href');
            $el->disabled('disabled');
        }

        $last_button = $el->render();

        $el = null;

        // Toolbar row.

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->id('bottom-row');
        $el->role('toolbar');
        $el->addClass("'px-2 {$bar_class}");
        $el->column("$first_button $prev_button $next_button $last_button", 'col text-right py-2');
        $bottom_row = $el->render();

        $this->append(['html' => $top_row . $bottom_row]);

        return $this->send();
    }
}
