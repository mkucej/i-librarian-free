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
        $el->placeholder('Search query');
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
        $el->label('AND');
        $search_boolean = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('search_boolean_or');
        $el->name('search_boolean[]');
        $el->value('OR');
        $el->label('OR');
        $search_boolean .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('search_boolean_phrase');
        $el->name('search_boolean[]');
        $el->value('PHRASE');
        $el->label('PHRASE');
        $search_boolean .= $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->addClass('mt-3');
        $el->name('search_type[]');
        $el->ariaLabel('Search in');
        $el->option('Metadata + PDFs', 'anywhere');
        $el->option('Metadata', 'metadata');
        $el->option('PDFs', 'FT');
        $el->option('PDF notes', 'pdfnotes');
        $el->option('Notes', 'itemnotes');
        $el->option('Item ID', 'itemid');
        $search_type = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->inline(true);
        $el->id('save-search-quick');
        $el->name('save_search');
        $el->value('1');
        $el->label('save this search for later');
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
            $el->name("search_type[{$i}]");
            $el->ariaLabel('Search in');
            $el->option('Title', 'TI');
            $el->option('Title + abstract', 'AB', $i === 0 ? true : false);
            $el->option('PDF fulltext', 'FT');
            $el->option('Authors + editors', 'AU', $i === 1 ? true : false);
            $el->option('Affiliation', 'AF');
            $el->option('Primary title', 'T1');
            $el->option('Secondary title', 'T2');
            $el->option('Tertiary title', 'T3');
            $el->option('Keywords', 'KW');

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
            $el->placeholder('Search query');
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
            $el->label('AND');
            $search_boolean = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('input-or-' . $i);
            $el->type('radio');
            $el->inline(true);
            $el->name("search_boolean[$i]");
            $el->value('OR');
            $el->label('OR');
            $search_boolean .= $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('input-phrase-' . $i);
            $el->type('radio');
            $el->inline(true);
            $el->name("search_boolean[$i]");
            $el->value('PHRASE');
            $el->label('PHRASE');
            $search_boolean .= $el->render();

            $el = null;

            /** @var Bootstrap\Select $el */
            $el = $this->di->get('Select');

            $el->id('input-glue-' . $i);
            $el->groupClass('my-2');
            $el->style('width: 5rem');
            $el->name("search_glue[{$i}]");
            $el->ariaLabel('AND/OR/NOT');
            $el->option('AND', 'AND');
            $el->option('OR', 'OR');
            $el->option('NOT', 'NOT');
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

        $tag_html = "<div class=\"my-3 cursor-pointer\" data-toggle=\"collapse\" data-target=\"#search-tags\">$arrow<b>Tagged by</b></div>";

        // Filter input.
        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('tag-filter-search');
        $el->placeholder('Filter');
        $el->ariaLabel('Filter');
        $el->attr('data-targets', '#search-tags .label-text');
        $tag_filter = $el->render();

        $el = null;

        $i = 0;
        $tag_checkboxes = '';

        foreach ($tags as $id => $tag) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('search-checkbox-tags-' . $id);
            $el->type('checkbox');
            $el->name("search_filter[tag][{$i}]");
            $el->value($id);
            $el->label($tag);
            $el->inline(true);
            $tag_checkboxes .= $el->render();

            $el = null;

            $i++;
        }

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
        $el->label('save this search for later');
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
        $el->html('+ Select local file' . $affix);
        $file_button = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->addClass('uploadable-clear mt-2 d-none');
        $el->context('danger');
        $el->html('Clear');
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

        $sanitized_link = '';

        if (isset($web_link) === true && $this->validation->ssrfLink($web_link) === true) {

            $sanitized_link = $web_link;
        }

        /** @var Bootstrap\Input $el Remote URL input. */
        $el = $this->di->get('Input');

        $el->id('uploadable-url-' . uniqid());
        $el->name('remote_url');
        $el->value($sanitized_link);
        $el->addClass('uploadable-url');
        $el->label('Upload file from a URL');
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

            $el->div('<small>NO SAVED SEARCHES</small>', 'py-3 text-secondary');
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
                $btn->style('width: 4rem');
                $btn->html('Edit');
                $edit = $btn->render();

                $btn = null;
            }

            /** @var Bootstrap\Button $btn Delete saved search btn.*/
            $btn = $this->di->get('Button');

            $btn->addClass('delete-search my-1');
            $btn->context('outline-danger');
            $btn->componentSize('small');
            $btn->html('Delete');
            $btn->attr('data-id', $search['id']);
            $btn->attr('data-url', IL_BASE_URL . 'index.php/search/delete');
            $delete = $btn->render();

            $btn = null;

            /** @var Temporal $temporal */
            $temporal = $this->di->getShared('Temporal');
            $last_search = $temporal->toUserTime($search['changed_time']);
            $diff = $temporal->diff($search['changed_time']);

            $searches_html = <<<SEARCH
                <div class="mr-3">
                    <a href="{$search['search_url']}">{$search['search_name']}</a><br>
                    Last search: {$last_search} <span class="text-muted">&mdash; {$diff}</span>
                </div>
                <div style="white-space: nowrap">
                    $edit
                    $delete
                </div>
SEARCH;

            $el->div($searches_html, 'd-flex justify-content-between align-items-center border-0');
        }

        $list = $el->render();

        $el = null;

        return $list;
    }
}
