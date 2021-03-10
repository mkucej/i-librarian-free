<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Element;
use Librarian\Html\Bootstrap;
use Librarian\Http\Client\Utils;
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
     * @param array $projects
     * @param array $tags
     * @param array $terms
     * @return string
     * @throws Exception
     */
    public function results(
        string $database,
        array $items,
        int $from,
        string $search_name,
        array $projects,
        array $tags,
        $terms = []): string {

        $get = $this->request->getQueryParams();
        $trimmed_get = $this->sanitation->trim($get);
        $sanitized_get = $this->sanitation->html($trimmed_get);

        $this->title("$database {$this->lang->t9n('search-NOUN')}");

        $this->head();

        // Breadcrumb.
        $search_name = empty($search_name) ? '' : "&ndash; $search_name";

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("{$database} {$this->lang->t9n('search-NOUN')} {$search_name}");
        $bc = $el->render();

        $el = null;

        // Item count, paging.
        $page_from = $this->scalar_utils->formatNumber($from);
        $page_to = $this->scalar_utils->formatNumber($from + count($items['items']) - 1);
        $found = $this->scalar_utils->formatNumber($items['found'] ?? 0);

        if ($found === '0') {

            $item_count = "<div class=\"text-muted w-100 pb-3 pb-lg-0\">{$this->lang->t9n('No results found')}.</div>";

        } else {

            $item_count = "<div class=\"text-muted w-100 pb-3 pb-xl-0\">{$this->lang->t9n('Results')} $page_from - $page_to of $found</div>";
        }

        // Shared HTML components.
        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->addClass('my-2');
        $el->context('danger');
        $el->html($this->lang->t9n('Save'));
        $save = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $el->addClass('mdi-18px');
        $chevron = $el->render();

        $el = null;

        /** @var Bootstrap\Badge $el */
        $el = $this->di->get('Badge');

        $el->context('warning');
        $el->addClass('mb-2');
        $el->html("<span class=\"text-uppercase\">{$this->lang->t9n('in library')}</span>");
        $exists_badge = $el->render();

        $el = null;

        // Highlighting.
        $patterns = [];

        foreach ($terms as $term) {

            $value = current($term);
            $parts = array_filter(explode(' ', $value));

            foreach ($parts as $part) {

                $part = trim($part);

                // Ignore search tags.
                if (mb_strpos($part, '[') === 0) {

                    continue;
                }

                // Ignore NASA tags.
                $part = strpos($part, ':') !== false ? strstr($part, ':') : $part;

                // Ignore booleans.
                if (in_array($part, ['AND', 'OR', 'NOT', 'ANDNOT', 'BUTNOT', 'and', 'or', 'not', 'andnot', 'butnot',])) {

                    continue;
                }

                // Remove punctuations.
                $part = preg_replace('/[^\p{L}\p{N}*]/ui', '', $part);

                $boundary = mb_strrpos($part, '*') === mb_strlen($part) - 1 ? '' : '\b';
                $part = str_replace('*', '', $part);

                // Skip if nothing left.
                if (empty($part)) {

                    continue;
                }

                $part = preg_quote($part);
                $patterns[] = "/(\b{$part}{$boundary})/ui";
            }
        }

        $patterns = array_unique($patterns);

        // Items.
        $titles = '';
        $i = 1;

        // Found items.
        foreach ($items['items'] as $article) {

            // Exists in library badge.
            $exists = isset($article['exists']) && $article['exists'] === 'Y' ? $exists_badge: '';

            // Title with search term highlighting.
            $title = $this->sanitation->html($article[ItemMeta::COLUMN['TITLE']] ?? '');

            // Compact authors - first and last.
            $author = $this->sanitation->html($article[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][0] ?? '');

            if (!empty($author)) {

                // First name.
                $first_name = $article[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][0] ?? '';
                $author .= $this->sanitation->html(empty($first_name) ? '' : ", {$first_name}");

                // Last author.
                $author_count = count($article[ItemMeta::COLUMN['AUTHOR_LAST_NAME']]);
                $last_last_name = $article[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][($author_count - 1)] ?? '';

                if ($last_last_name !== $article[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][0]) {

                    $author .= ' &hellip;' . $this->sanitation->html($last_last_name);

                    // First name.
                    $last_first_name = $article[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][($author_count - 1)] ?? '';
                    $author .= $this->sanitation->html(empty($last_first_name) ? '' : ", {$last_first_name}");
                }
            }

            $author = empty($author) ? $this->lang->t9n('No authors') : $author;

            // Year.
            $year = $this->sanitation->html($article[ItemMeta::COLUMN['PUBLICATION_DATE']] ?? null);
            $year = empty($year) ? $this->lang->t9n('No date') : substr($year, 0, 4);

            // Publication name.
            $publication = !empty($article[ItemMeta::COLUMN['TERTIARY_TITLE']]) ? $article[ItemMeta::COLUMN['TERTIARY_TITLE']] : '';
            $publication = !empty($article[ItemMeta::COLUMN['SECONDARY_TITLE']]) ? $article[ItemMeta::COLUMN['SECONDARY_TITLE']] : $publication;
            $publication = !empty($article[ItemMeta::COLUMN['PRIMARY_TITLE']]) ? $article[ItemMeta::COLUMN['PRIMARY_TITLE']] : $publication;

            $publication = empty($publication) ? $this->lang->t9n('No publication title') : $publication;

            // Abstract with search term highlighting.
            $abstract = preg_replace($patterns, '<mark>$1</mark>', $this->sanitation->html($article[ItemMeta::COLUMN['ABSTRACT']] ?? ''));

            // Links.
            $link     = $article[ItemMeta::COLUMN['URLS']][0] ?? null;
            $pdf_link = $article[ItemMeta::COLUMN['URLS']][1] ?? null;

            $metadata = $this->sanitation->attr($this->sanitation->lmth(Utils::jsonEncode($article)));

            // Upload form.

            // Metadata.
            /** @var Bootstrap\Input $el Metadata. */
            $el = $this->di->get('Input');

            $el->type('hidden');
            $el->name('metadata');
            $el->value($metadata);
            $hidden = $el->render();

            $el = null;

            // Clipboard.
            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id("clipboard-checkbox-{$i}");
            $el->groupClass('mb-3');
            $el->type('checkbox');
            $el->inline(true);
            $el->label($this->lang->t9n('Clipboard'));
            $el->name('clipboard');
            $el->value('1');
            $clipboard_check = $el->render();

            $el = null;

            // Projects.
            $project_checks = '';

            foreach ($projects['active_projects'] as $project) {

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->id("project-checkbox-{$project['id']}-{$i}");
                $el->groupClass('mb-3');
                $el->type('checkbox');
                $el->inline(true);
                $el->label($project['project']);
                $el->name('projects[]');
                $el->value($project['id']);
                $project_checks .= $el->render();

                $el = null;
            }

            // New tags.
            /** @var Bootstrap\Textarea $el */
            $el = $this->di->get('Textarea');

            $el->id("tags-new-{$i}");
            $el->label("{$this->lang->t9n('New tags')} ({$this->lang->t9n('one per line')})");
            $el->name('new_tags');
            $tags_ta = $el->render();

            $el = null;

            // Filter input.
            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id("tag-filter-{$i}");
            $el->addClass('tag-filter');
            $el->name('tag_filter');
            $el->placeholder($this->lang->t9n('Filter-VERB'));
            $el->label($this->lang->t9n('Tags'));
            $el->attr('data-targets', "#tags-{$i} .label-text");
            $tag_filter = $el->render();

            $el = null;

            // Tags.
            $first_letter = '';
            $j = 0;

            $tag_checkboxes =
<<<HTML
<table class="tag-table">
    <tr>
        <td style="width:2.25rem">
        </td>
        <td>
HTML;

            foreach ($tags as $tag_id => $tag) {

                $first_letter2 = mb_strtoupper($this->scalar_utils->deaccent($tag[0] === '' ? '' : mb_substr($tag, 0, 1, 'UTF-8')), 'UTF-8');

                // New letter.
                if ($first_letter2 !== $first_letter) {

                    /** @var Bootstrap\Badge $el */
                    $el = $this->di->get('Badge');

                    $el->context('warning');
                    $el->addClass('d-inline-block mr-2 mb-2');
                    $el->style('width: 1.33rem');
                    $el->html($first_letter2);
                    $letter_badge = $el->render();

                    $el = null;

                    $tag_checkboxes .=
<<<HTML
        </td>
    </tr>
    <tr>
        <td style="width:2.25rem">
            $letter_badge
        </td>
        <td>
HTML;

                    $first_letter = $first_letter2;
                }

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->id("tag-checkbox-$i-$j");
                $el->type('checkbox');
                $el->name("tags[]");
                $el->value($tag_id);
                $el->label($tag);
                $el->inline(true);

                $tag_checkboxes .= $el->render();

                $el = null;

                $j++;
            }

            $tag_checkboxes .=
<<<HTML
        </td>
    </tr>
</table>
HTML;

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
            $el->append(
<<<HTML
<div class="add-pdf-btn d-inline-block cursor-pointer mb-2" data-toggle="collapse" data-target="#pdf-form-{$i}">
    {$chevron}{$this->lang->t9n('Add PDF')}
</div><br>
<div id="pdf-form-{$i}" class="collapse" style="width: 290px">
    {$this->sharedFileInput(false, $pdf_link)}
</div>
<div class="d-inline-block cursor-pointer mb-2" data-toggle="collapse" data-target="#collections-{$i}">
    {$chevron}{$this->lang->t9n('Add to')}
</div><br>
<div class="collapse" id="collections-{$i}">
    $clipboard_check $project_checks
</div>
<div class="d-inline-block cursor-pointer mb-3" data-toggle="collapse" data-target="#tags-{$i}">
    {$chevron}{$this->lang->t9n('Tag with')}
</div><br>
<div class="collapse" id="tags-{$i}">
    $tags_ta
    $tag_filter
    $tag_checkboxes
</div>
$hidden
$csrf_input
$save
HTML
            );
            $form = $el->render();

            $el = null;

            $title =
<<<HTML
$exists
<h5><a href="{$link}">{$title}</a></h5>
<p>$author <span class="ml-1">({$year})</span> <i class="ml-1">$publication</i></p>
<p style="text-align:justify;columns: 2 300px;column-gap: 30px;">{$abstract}</p>
<div>$form</div>
HTML;

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

        // Toolbar.
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
