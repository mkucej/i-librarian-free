<?php

namespace LibrarianApp;

use Exception;
use Librarian\AppSettings;
use Librarian\Container\DependencyInjector;
use \Librarian\Html\Bootstrap;
use Librarian\Media\Temporal;
use Librarian\Security\Validation;

/**
 * Trait with reusable static HTML elements.
 */
trait SharedHtmlView {

    /**
     * @var DependencyInjector
     */
    protected $di;

    /**
     * @var Validation
     */
    protected $validation;

    /**
     * A static HTML element: quick search form.
     *
     * @param string|null $action
     * @return string
     * @throws Exception
     */
    public function sharedQuickSearch(string $action = null): string {

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->type('submit');
        $el->context('primary');
        $el->style('border: 0;padding: 0 10px');
        $el->icon('magnify');
        $search_button = $el->render();

        $el = null;

        /** @var Bootstrap\Inputgroup $el */
        $el = $this->di->get('InputGroup');

        $el->name('search_query[]');
        $el->ariaLabel('Search query');
        $el->placeholder($this->lang->t9n('Search query'));
        $el->required('required');
        $el->appendButton($search_button);
        $search_input = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('search_boolean_and');
        $el->name('search_boolean[]');
        $el->value('AND');
        $el->checked('checked');
        $el->label(
<<<HTML
<span class="text-uppercase">{$this->lang->t9n('and')}</span>
HTML
        );
        $search_boolean = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('search_boolean_or');
        $el->name('search_boolean[]');
        $el->value('OR');
        $el->label(
<<<HTML
<span class="text-uppercase">{$this->lang->t9n('or')}</span>
HTML
        );
        $search_boolean .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('search_boolean_phrase');
        $el->name('search_boolean[]');
        $el->value('PHRASE');
        $el->label(
<<<HTML
<span class="text-uppercase">{$this->lang->t9n('phrase')}</span>
HTML
        );
        $search_boolean .= $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->addClass('mt-2');
        $el->name('search_type[]');
        $el->ariaLabel('Search in');
        $el->option("{$this->lang->t9n('Metadata')} + PDFs", 'anywhere');
        $el->option($this->lang->t9n('Metadata'), 'metadata');
        $el->option('PDFs', 'FT');
        $el->option($this->lang->t9n('PDF notes'), 'pdfnotes');
        $el->option($this->lang->t9n('Notes'), 'itemnotes');
        $el->option("{$this->lang->t9n('Item')} ID", 'itemid');
        $search_type = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->inline(true);
        $el->id('save-search-quick');
        $el->name('save_search');
        $el->value('1');
        $el->label($this->lang->t9n('save this search for later'));
        $save_search = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('quick-search-form');
        $el->method('GET');
        $el->action(isset($action) ? $action : '#items/main');
        $el->append("$search_input $search_boolean $search_type $save_search");
        $quick_search_form = $el->render();

        $el = null;

        return $quick_search_form;
    }

    /**
     * @param array $tags
     * @param string|null $action
     * @return string
     * @throws Exception
     */
    public function sharedAdvancedSearch(array $tags, string $action = null): string {

        $rows = '';

        for ($i = 0; $i < 2; $i++) {

            /** @var Bootstrap\Select $el */
            $el = $this->di->get('Select');

            $el->id('input-type-' . $i);
            $el->groupClass('my-2');
            $el->addClass('fields');
            $el->name("search_type[{$i}]");
            $el->ariaLabel('Search in');
            $el->option($this->lang->t9n('Title'), 'TI');
            $el->option("{$this->lang->t9n('Title')} {$this->lang->t9n('or')} {$this->lang->t9n('abstract')}", 'AB', $i === 0);
            $el->option("PDF {$this->lang->t9n('fulltext')}", 'FT');
            $el->option("{$this->lang->t9n('Author')} {$this->lang->t9n('or')} {$this->lang->t9n('editor')}", 'AU', $i === 1);
            $el->option($this->lang->t9n('Affiliation'), 'AF');
            $el->option($this->lang->t9n('Primary title'), 'T1');
            $el->option($this->lang->t9n('Secondary title'), 'T2');
            $el->option($this->lang->t9n('Tertiary title'), 'T3');
            $el->option($this->lang->t9n('Keyword'), 'KW');
            $el->option($this->lang->t9n('Year'), 'YR');

            /** @var AppSettings $app_settings */
            $app_settings = $this->app_settings;

            for ($j = 1; $j <= 8; $j++) {

                $el->option($app_settings->getGlobal('custom' . $j), "C{$j}");
            }

            $search_type = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('input-query-' . $i);
            $el->groupClass('my-2');
            $el->name("search_query[{$i}]");
            $el->ariaLabel('Search query');
            $el->placeholder($this->lang->t9n('Search query'));
            $search_input = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('input-and-' . $i);
            $el->type('radio');
            $el->inline(true);
            $el->name("search_boolean[$i]");
            $el->value('AND');
            $el->checked('checked');
            $el->label($this->lang->t9n('AND'));
            $search_boolean = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('input-or-' . $i);
            $el->type('radio');
            $el->inline(true);
            $el->name("search_boolean[$i]");
            $el->value('OR');
            $el->label($this->lang->t9n('OR'));
            $search_boolean .= $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('input-phrase-' . $i);
            $el->type('radio');
            $el->inline(true);
            $el->name("search_boolean[$i]");
            $el->value('PHRASE');
            $el->label($this->lang->t9n('PHRASE'));
            $search_boolean .= $el->render();

            $el = null;

            /** @var Bootstrap\Select $el */
            $el = $this->di->get('Select');

            $el->id('input-glue-' . $i);
            $el->groupClass('my-2');
            $el->style('width: 5rem');
            $el->name("search_glue[{$i}]");
            $el->ariaLabel('AND/OR/NOT');
            $el->option($this->lang->t9n('AND'), 'AND');
            $el->option($this->lang->t9n('OR'), 'OR');
            $el->option($this->lang->t9n('NOT'), 'NOT');
            $glue = $el->render();

            $el = null;

            if ($i === 0) {

                $glue = '<div style="width: 5rem"></div>';
            }

            /** @var Bootstrap\Row $el */
            $el = $this->di->get('Row');

            if ($i === 1) {

                $el->id('clone-target');
            }

            $el->addClass('no-gutters');
            $el->column($glue, 'col-lg-auto pr-1');
            $el->column($search_type, 'col-lg-3 pr-1');
            $el->column($search_input . $search_boolean, 'col-lg pr-1');

            $rows .= $el->render();

            $el = null;
        }

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->addClass('clone-button btn-round mb-3');
        $el->context('primary');
        $el->icon('plus');
        $clone_rows = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->addClass('remove-clone-button btn-round ml-2 mb-3');
        $el->context('secondary');
        $el->icon('minus');
        $clone_rows .= $el->render();

        $el = null;

        // Tags.

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $arrow = $el->render();

        $el = null;

        $tag_html = "<div class=\"my-3 cursor-pointer\" data-toggle=\"collapse\" data-target=\"#search-tags\">$arrow<b>{$this->lang->t9n('Tags')}</b></div>";

        // Filter input.
        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('tag-filter-search');
        $el->placeholder($this->lang->t9n('Filter-NOUN'));
        $el->ariaLabel($this->lang->t9n('Filter-NOUN'));
        $el->attr('data-targets', '#search-tags .label-text');
        $tag_filter = $el->render();

        $el = null;

        // First letter.
        $first_letter = '';
        $i = 0;

        $tag_checkboxes = '<table class="tag-table"><tr><td style="width:2.25rem"></td><td>';

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

            $el->groupClass('tag-divs');
            $el->addClass('tag-inputs');
            $el->id('search-checkbox-tags-' . $i);
            $el->type('checkbox');
            $el->name("search_filter[tag][{$i}]");
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

        $el->id('search-tags');
        $el->addClass('mb-3 collapse');
        $el->html($tag_filter . $tag_checkboxes);
        $tag_html .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('save-search-advanced');
        $el->name('save_search');
        $el->value('1');
        $el->label($this->lang->t9n('save this search for later'));
        $save_search = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('primary');
        $el->style('position: fixed; top: 0;left: -500px');
        $el->html('Submit');
        $submit = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('advanced-search-form');
        $el->method('GET');
        $el->action(isset($action) ? $action : '#items/main');
        $el->html("$rows $clone_rows $tag_html $save_search $submit");
        $quick_search_form = $el->render();

        $el = null;

        return $quick_search_form;
    }

    /**
     * File upload.
     *
     * @param bool $multiple
     * @param string|null $web_link
     * @return string
     * @throws Exception
     */
    public function sharedFileInput(bool $multiple = false, string $web_link = null): string {

        // File select button.
        $affix = $multiple === true ? 's' : '';

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->addClass('uploadable-select mt-2');
        $el->context('secondary');
        $el->html(
<<<HTML
+ {$this->lang->t9n('Select local file' . $affix)}
HTML
        );
        $file_button = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->addClass('uploadable-clear mt-2 d-none');
        $el->context('danger');
        $el->html($this->lang->t9n('Clear'));
        $clear_button = $el->render();

        $el = null;

        /** @var Bootstrap\Inputgroup $el File input. */
        $el = $this->di->get('Input');

        $el->id('file-' . uniqid());
        $el->type('file');
        $el->addClass('uploadable-file d-none');
        $el->name('file');

        if ($multiple === true) {

            $el->attr('multiple', 'multiple');
        }

        $file_input  = $el->render();

        $el = null;

        /** @var Bootstrap\ProgressBar $el */
        $el = $this->di->get('ProgressBar');

        $el->addClass('uploadable-progress bg-darker-5');
        $el->style('height: 4px');
        $el->value(1);
        $progress = $el->render();

        $el = null;

        /** @var Bootstrap\ListGroup $el File list container. */
        $el = $this->di->get('ListGroup');

        $el->addClass('uploadable-list d-none mb-3');
        $el->div($progress, 'p-0');
        $el->div('<div class="p-3" style="max-height: 33vh;overflow: auto"></div>', 'p-0');
        $list = $el->render();

        $el = null;

        // Remove invalid link.
        if (!empty($web_link)) {

            try {

                $this->validation->ssrfLink($web_link);

            } catch (Exception $exc) {

                $web_link = '';
            }
        }

        /** @var Bootstrap\Input $el Remote URL input. */
        $el = $this->di->get('Input');

        $el->id('uploadable-url-' . uniqid());
        $el->name('remote_url');
        $el->value($web_link);
        $el->addClass('uploadable-url');
        $el->label($this->lang->t9n('Upload file from a URL'));
        $url_input = $el->render();

        return "$file_button $clear_button $file_input $list $url_input";
    }

    /**
     * List of saved searches.
     *
     * @param array $searches
     * @param bool $external
     * @return string
     * @throws Exception
     */
    public function sharedSearchList(array $searches, bool $external = false): string {

        /** @var Bootstrap\ListGroup $el List group of saved searches. */
        $el = $this->di->get('ListGroup');

        $el->id('search-list');

        if (empty($searches)) {

            $el->div(
<<<HTML
<small>{$this->lang->t9n('No saved searches')}</small>
HTML
            , 'py-3 text-secondary text-uppercase');
        }

        // Saved searches.
        foreach ($searches as $search) {

            $edit = '';

            if ($external === true) {

                /** @var Bootstrap\Button $btn Delete saved search btn.*/
                $btn = $this->di->get('Button');

                $btn->addClass('edit-search my-1');
                $btn->context('outline-primary');
                $btn->componentSize('small');
                $btn->style('min-width: 4rem');
                $btn->html($this->lang->t9n('Edit'));
                $edit = $btn->render();

                $btn = null;
            }

            /** @var Bootstrap\Button $btn Delete saved search btn.*/
            $btn = $this->di->get('Button');

            $btn->addClass('delete-search my-1');
            $btn->context('outline-danger');
            $btn->componentSize('small');
            $btn->html($this->lang->t9n('Delete'));
            $btn->attr('data-id', $search['id']);
            $btn->attr('data-url', IL_BASE_URL . 'index.php/search/delete');
            $delete = $btn->render();

            $btn = null;

            /** @var Temporal $temporal */
            $temporal = $this->di->getShared('Temporal');
            $last_search = $temporal->toUserTime($search['changed_time']);
            $diff = $temporal->diff($search['changed_time']);

            $searches_html =
<<<HTML
<div class="mr-3">
    <a href="{$search['search_url']}">{$search['search_name']}</a><br>
    {$this->lang->t9n('Last search')}: {$last_search} <span class="text-muted">&mdash; {$diff}</span>
</div>
<div style="white-space: nowrap">
    $edit
    $delete
</div>
HTML;

            $el->div($searches_html, 'd-flex justify-content-between align-items-center border-0');
        }

        $list = $el->render();

        $el = null;

        return $list;
    }
}
