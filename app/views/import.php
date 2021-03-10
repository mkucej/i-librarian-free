<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\ItemMeta;
use Librarian\Mvc\TextView;

class ImportView extends TextView {

    use SharedHtmlView;

    /**
     * @var ItemMeta
     */
    private $item_meta;

    /**
     * Wizard page.
     *
     * @return string
     * @throws Exception
     */
    public function wizard() {

        $this->title($this->lang->t9n('Import wizard'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Import wizard'));
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el Submit btn. */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->context('primary');
        $el->href('#import/uid');
        $el->html($this->lang->t9n('Import-VERB'));
        $link_uid = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('h-100');
        $el->body(<<<EOT
            <h4>DOI, Pubmed ID, NASA bibcode&hellip;</h4>
            <span class="text-secondary">10.2234/265489.225</span>
EOT
            , null, 'text-center py-5');
        $el->footer($link_uid, 'text-center');
        $card_uid = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el Submit btn. */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->context('primary');
        $el->href('#import/file');
        $el->html($this->lang->t9n('Import-VERB'));
        $link_file = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('h-100');
        $el->body(<<<EOT
            <h4>{$this->lang->t9n('Published PDFs')}</h4>
            <span class="mdi mdi-24px mdi-file-pdf-box text-secondary"></span>
            <span class="mdi mdi-24px mdi-file-pdf-box text-secondary"></span>
            <span class="text-secondary">+ 10.2234/265489.225</span>
EOT
            , null, 'text-center py-5');
        $el->footer($link_file, 'text-center');
        $card_file = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el Submit btn. */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->context('primary');
        $el->href('#import/text');
        $el->html($this->lang->t9n('Import-VERB'));
        $link_text = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('h-100');
        $el->body(<<<EOT
            <h4>RIS, BibTex, {$this->lang->t9n('or')} Endnote XML</h4>
            <span class="text-secondary">@article{Smith2011Foobar</span>
EOT
            , null, 'text-center py-5');
        $el->footer($link_text, 'text-center');
        $card_text = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->addClass('d-flex align-content-start');
        $el->column($bc, 'col-12');
        $el->column($card_uid, 'col-xl-4 mb-3');
        $el->column($card_file, 'col-xl-4 mb-3');
        $el->column($card_text, 'col-xl-4 mb-3');
        $el->column('<a href="#import/manual">' . $this->lang->t9n('None of the above') . '</a>', 'col-12 my-5 text-center');
        $content = $el->render();

        $el = null;

        $this->append(['html' => $content]);

        return $this->send();
    }

    /**
     * Import using a UID.
     *
     * @param array $projects
     * @param array $tags
     * @return string
     * @throws Exception
     */
    public function uid(array $projects, array $tags) {

        $this->title($this->lang->t9n('Import UID'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Import wizard'), '#import/wizard');
        $el->item($this->lang->t9n('Import UID'));
        $bc = $el->render();

        $el = null;

        // File uploader.
        $file_input = $this->sharedFileInput(false);

        /** @var Bootstrap\Input $el Import an UID. */
        $el = $this->di->get('Input');

        $el->name('uid');
        $el->label('DOI, Pubmed ID, NASA bibcode...');
        $el->hint($this->lang->t9n('Example') . ': PMID: 20133854');
        $uid_input = $el->render();

        $el = null;

        // We put CSRF key here, because the JS fileupload plugin has its own AJAX methods.

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('csrfToken');
        $el->value($this->session->data('token'));
        $csrf_input = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Metadata. */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->id('metadata');
        $el->name('metadata');
        $metadata_input = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('fetch-record');
        $el->context('primary');
        $el->html($this->lang->t9n('Fetch record'));
        $fetch_button = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->addClass('d-none');
        $el->html($this->lang->t9n('Save'));
        $upload_button = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('uid_types[]');
        $el->id('new-uid-type');
        $el->label($this->lang->t9n('UID type'));
        $el->option('', '');

        if ($this->app_settings->getUser('connect_arxiv') === '1') {

            $el->option(ItemMeta::UID_TYPE['ARXIV'], 'ARXIV');
        }

        $el->option('DOI', 'DOI');

        if ($this->app_settings->getUser('connect_ol') === '1') {

            $el->option(ItemMeta::UID_TYPE['ISBN'], 'ISBN');
        }

        if ($this->app_settings->getUser('connect_xplore') === '1') {

            $el->option(ItemMeta::UID_TYPE['IEEE'], 'IEEE');
        }

        if ($this->app_settings->getUser('connect_nasa') === '1') {

            $el->option(ItemMeta::UID_TYPE['NASAADS'], 'NASAADS');
        }

        if ($this->app_settings->getUser('connect_ol') === '1') {

            $el->option(ItemMeta::UID_TYPE['OL'], 'OL');
        }

        if ($this->app_settings->getUser('connect_patents') === '1') {

            $el->option(ItemMeta::UID_TYPE['PAT'], 'PAT');
        }

        if ($this->app_settings->getUser('connect_pubmed') === '1') {

            $el->option(ItemMeta::UID_TYPE['PMID'], 'PMID');
        }

        if ($this->app_settings->getUser('connect_pmc') === '1') {

            $el->option(ItemMeta::UID_TYPE['PMCID'], 'PMCID');
        }

        $uid_html = $el->render();

        // Clipboard.
        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $el->addClass('mdi-18px');
        $chevron = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('clipboard-checkbox');
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

            $el->id('project-checkbox-' . $project['id']);
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

        $el->id('tags-new');
        $el->label("{$this->lang->t9n('New tags')} ({$this->lang->t9n('one per line')})");
        $el->name('new_tags');
        $tags_ta = $el->render();

        $el = null;

        // Filter input.
        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('tag-filter');
        $el->name('tag_filter');
        $el->placeholder($this->lang->t9n('Filter-VERB'));
        $el->label($this->lang->t9n('Tags'));
        $el->ariaLabel($this->lang->t9n('Filter-VERB'));
        $el->attr('data-targets', '.label-text');
        $tag_filter = $el->render();

        $el = null;

        // Tags.
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

            $el->id('tag-checkbox-' . $i);
            $el->type('checkbox');
            $el->name("tags[{$i}]");
            $el->value($tag_id);
            $el->label($tag);
            $el->inline(true);

            $tag_checkboxes .= $el->render();

            $el = null;

            $i++;
        }

        $tag_checkboxes .= '</td></tr></table>';

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->body(<<<BODY
            <div class="import-wizard-uid">
                $uid_input
                <div id="uid-message" class="d-none mb-3"></div>
                <div class="collapse" id="select-type">
                    $uid_html
                </div>
            </div>
            <div id="phase-2" class="d-none mt-3">
                <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#more-metadata">
                    {$chevron}{$this->lang->t9n('Add PDF')}
                </div>
                <div class="collapse ml-3" id="more-metadata">
                    $file_input
                </div>
                <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#collections">
                    {$chevron}{$this->lang->t9n('Add to')}
                </div>
                <div class="collapse ml-3" id="collections">
                    $clipboard_check $project_checks
                </div>
                <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#tags">
                    {$chevron}{$this->lang->t9n('Tag with')}
                </div>
                <div class="collapse ml-3" id="tags">
                    $tags_ta
                    $tag_filter
                    $tag_checkboxes
                </div>
            </div>
BODY
        , null, 'pt-3');
        $el->footer("$fetch_button $upload_button");
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('upload-form');
        $el->method('POST');
        $el->action(IL_BASE_URL . 'index.php/import/manual');
        $el->append("$card $csrf_input $metadata_input");
        $upload_form = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->addClass('d-flex align-content-start');
        $el->column($bc, 'col-12');
        $el->column($upload_form, 'col-md-6 offset-md-3 mb-3');
        $content = $el->render();

        $el = null;

        $this->append(['html' => $content]);

        return $this->send();
    }

    /**
     * Manual upload.
     *
     * @param array $projects
     * @param array $tags
     * @return string
     * @throws Exception
     */
    public function manual(array $projects, array $tags) {

        // We need ItemMeta().
        $this->item_meta = $this->di->getShared('ItemMeta');

        // Item labels for this ref type.
        $item_labels = $this->item_meta->getLabels($this->lang, 'unpublished');

        $this->title($this->lang->t9n('Manual import'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Manual import'));
        $bc = $el->render();

        $el = null;

        // File uploader.
        $file_input = $this->sharedFileInput(false);

        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->id('title');
        $el->name('title');
        $el->required('required');
        $el->style('height: 4rem');
        $el->label($this->lang->t9n('Title'));
        $title = $el->render();

        $el = null;

        // Authors.

        /** @var Bootstrap\Typeahead $el */
        $el = $this->di->get('Typeahead');

        $el->id('new-author-last');
        $el->addClass("input-typeahead");
        $el->groupClass('col');
        $el->name('author_last_name[]');
        $el->label($this->lang->t9n('Last name'));
        $el->source(IL_BASE_URL . "index.php/filter/author");
        $new_last_name = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('new-author-first');
        $el->groupClass('col');
        $el->name('author_first_name[]');
        $el->label($this->lang->t9n('First name'));
        $new_first_name = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->id('clone-authors');
        $el->addClass('btn-round mb-3');
        $el->context('primary');
        $el->icon('plus');
        $clone_authors = $el->render();

        $el = null;

        $author_row = <<<EOT
            <p><b>{$this->lang->t9n('Authors')}</b></p>
            <div id="new-author-container" class="form-row">
                $new_last_name
                $new_first_name
            </div>
            $clone_authors
EOT;

        // Editors.

        /** @var Bootstrap\Typeahead $el */
        $el = $this->di->get('Typeahead');

        $el->id('new-editor-last');
        $el->addClass("input-typeahead");
        $el->groupClass('col');
        $el->name('editor_last_name[]');
        $el->label($this->lang->t9n('Last name'));
        $el->source(IL_BASE_URL . "index.php/filter/editor");
        $new_last_name = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('new-editor-first');
        $el->groupClass('col');
        $el->name('editor_first_name[]');
        $el->label($this->lang->t9n('First name'));
        $new_first_name = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->id('clone-editors');
        $el->addClass('btn-round mb-3');
        $el->context('primary');
        $el->icon('plus');
        $clone_editors = $el->render();

        $el = null;

        $editor_row = <<<EOT
            <p><b>{$this->lang->t9n('Editors')}</b></p>
            <div id="new-editor-container" class="form-row">
                $new_last_name
                $new_first_name
            </div>
            $clone_editors
EOT;

        // New UID.

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->groupClass('col-sm-3');
        $el->name('uid_types[]');
        $el->id('new-uid-type');
        $el->label($this->lang->t9n('UID type'));
        $el->option('', '');

        foreach (ItemMeta::UID_TYPE as $option => $label) {

            $el->option($label, $option);
        }

        $uid_html = $el->render();

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('new-uid');
        $el->groupClass('col-sm-9');
        $el->name('uids[]');
        $el->label('UID');
        $uid_html .= $el->render();

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->id('clone-uid');
        $el->addClass('btn-round mb-3');
        $el->context('primary');
        $el->icon('plus');
        $clone_uid = $el->render();

        $el = null;

        $uid_row = <<<EOT
            <div id="uid-row" class="form-row">
                $uid_html
            </div>
            $clone_uid
EOT;

        // Selects.
        $selects = [];
        $select_names = [
            'reference_type',
            'bibtex_type'
        ];

        foreach ($select_names as $name) {

            /** @var Bootstrap\Select $el */
            $el = $this->di->get('Select');

            $el->name($name);
            $el->id(str_replace('_', '-', $name));
            $el->label($item_labels[$name]);

            switch ($name) {

                case 'reference_type':
                    $options = ItemMeta::TYPE;
                    break;

                case 'bibtex_type':
                    $options = ItemMeta::BIBTEX_TYPE;
                    break;

                default:
                    $options = [];
            }

            foreach ($options as $option) {

                $selected = $option === 'unpublished' ? true : false;
                $el->option($option, $option, $selected);
            }

            $selects[$name] = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('keyword_separator');
        $el->id('keyword-separator');
        $el->label('Keyword separator');
        $el->option('new line', '');
        $el->option(',', ',');
        $el->option(';', ';');
        $el->option('/', '/');
        $selects['keyword_separator'] = $el->render();

        $el = null;

        // Typeahead inputs.
        $typeaheads = [];
        $typeahead_names = [
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'custom5',
            'custom6',
            'custom7',
            'custom8',
            'primary_title',
            'secondary_title',
            'tertiary_title'
        ];

        foreach ($typeahead_names as $name) {

            $source = str_replace('_', '', $name);

            /** @var Bootstrap\Typeahead $el */
            $el = $this->di->get('Typeahead');

            $el->id(str_replace('_', '-', $name));
            $el->addClass("input-typeahead");
            $el->name($name);
            $el->label($item_labels[$name]);
            $el->source(IL_BASE_URL . "index.php/filter/{$source}");
            $typeaheads[$name] = $el->render();

            $el = null;
        }

        // Text inputs.
        $inputs = [];
        $input_names = [
            'publication_date',
            'volume',
            'issue',
            'pages',
            'publisher',
            'place_published',
            'bibtex_id'
        ];

        foreach ($input_names as $name) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id(str_replace('_', '-', $name));
            $el->name($name);
            $el->label($item_labels[$name]);
            $inputs[$name] = $el->render();

            $el = null;
        }

        // Text areas.
        $tas = [];
        $ta_names = [
            'title',
            'abstract',
            'affiliation',
            'urls',
            'keywords'
        ];

        foreach ($ta_names as $name) {

            // Bigger TAs.
            $height = in_array($name, ['abstract', 'keywords']) === true ? '6' : '4';

            /** @var Bootstrap\Textarea $el */
            $el = $this->di->get('Textarea');

            $el->id(str_replace('_', '-', $name));
            $el->style("height: {$height}rem");
            $el->name($name);
            $el->label($item_labels[$name]);
            $tas[$name] = $el->render();

            $el = null;
        }

        $other_metadata = <<<EOT
            {$selects['reference_type']}
            {$tas['abstract']}
            $author_row
            $editor_row
            {$tas['affiliation']}
            {$selects['bibtex_type']}
            {$inputs['bibtex_id']}
            $uid_row
            {$typeaheads['primary_title']}
            {$typeaheads['secondary_title']}
            {$typeaheads['tertiary_title']}
            {$inputs['publication_date']}
            {$inputs['volume']}
            {$inputs['issue']}
            {$inputs['pages']}
            {$inputs['publisher']}
            {$inputs['place_published']}
            {$tas['urls']}
            {$tas['keywords']}
            {$selects['keyword_separator']}
            {$typeaheads['custom1']}
            {$typeaheads['custom2']}
            {$typeaheads['custom3']}
            {$typeaheads['custom4']}
            {$typeaheads['custom5']}
            {$typeaheads['custom6']}
            {$typeaheads['custom7']}
            {$typeaheads['custom8']}
EOT;

        // We put CSRF key here, because the JS fileupload plugin has its own AJAX methods.

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('csrfToken');
        $el->value($this->session->data('token'));
        $csrf_input = $el->render();

        $el = null;

        /** @var Bootstrap\Button Submit. $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html($this->lang->t9n('Save'));
        $upload_button = $el->render();

        $el = null;

        // Clipboard.
        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $el->addClass('mdi-18px');
        $chevron = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('clipboard-checkbox');
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

            $el->id('project-checkbox-' . $project['id']);
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

        $el->id('tags-new');
        $el->label("{$this->lang->t9n('New tags')} ({$this->lang->t9n('one per line')})");
        $el->name('new_tags');
        $tags_ta = $el->render();

        $el = null;

        // Filter input.
        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('tag-filter');
        $el->name('tag_filter');
        $el->placeholder($this->lang->t9n('Filter-VERB'));
        $el->label($this->lang->t9n('Tags'));
        $el->ariaLabel($this->lang->t9n('Filter-VERB'));
        $el->attr('data-targets', '.label-text');
        $tag_filter = $el->render();

        $el = null;

        // Tags.
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

            $el->id('tag-checkbox-' . $i);
            $el->type('checkbox');
            $el->name("tags[]");
            $el->value($tag_id);
            $el->label($tag);
            $el->inline(true);

            $tag_checkboxes .= $el->render();

            $el = null;

            $i++;
        }

        $tag_checkboxes .= '</td></tr></table>';

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('h-100');
        $el->body(<<<EOT
            <b>Add PDF, office, image file</b><br>
            $file_input
            $title
            <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#more-metadata">
                {$chevron}{$this->lang->t9n('More metadata')}
            </div>
            <div class="collapse" id="more-metadata">$other_metadata</div>
            <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#collections">
                {$chevron}{$this->lang->t9n('Add to')}
            </div>
            <div class="collapse" id="collections">
                $clipboard_check $project_checks
            </div>
            <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#tags">
                {$chevron}{$this->lang->t9n('Tag with')}
            </div>
            <div class="collapse" id="tags">
                $tags_ta
                $tag_filter
                $tag_checkboxes
            </div>
EOT
        , null, 'pt-3');
        $el->footer($upload_button, 'bg-darker-5');
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('upload-form');
        $el->method('POST');
        $el->action(IL_BASE_URL . 'index.php/import/manual');
        $el->append("$card $csrf_input");
        $upload_form = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->addClass('d-flex align-content-start');
        $el->column($bc, 'col-12');
        $el->column($upload_form, 'col-xl-6 offset-xl-3 mb-3');
        $content = $el->render();

        $el = null;

        $this->append(['html' => $content]);

        return $this->send();
    }

    /**
     * @param array $projects
     * @param array $tags
     * @return string
     * @throws Exception
     */
    public function file(array $projects, array $tags) {

        $this->title($this->lang->t9n('Import PDFs'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Import wizard'), '#import/wizard');
        $el->item($this->lang->t9n('Import PDFs'));
        $bc = $el->render();

        $el = null;

        // File uploader.
        $file_input = $this->sharedFileInput(true);

        // We put CSRF key here, because the JS fileupload plugin has its own AJAX methods.

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('csrfToken');
        $el->value($this->session->data('token'));
        $csrf_input = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html($this->lang->t9n('Save'));
        $upload_button = $el->render();

        $el = null;

        // Repositories.
        $repositories = "<div class=\"mb-1\"><b>{$this->lang->t9n('Fetch metadata from')}:</b></div>";

        if ($this->app_settings->getUser('connect_xplore') === '1') {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('repository-xplore');
            $el->type('radio');
            $el->label('IEEE Xplore');
            $el->name('repository');
            $el->value('xplore');
            $repositories .= $el->render();

            $el = null;
        }

        if ($this->app_settings->getUser('connect_nasa') === '1') {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('repository-nasa');
            $el->type('radio');
            $el->label('NASA ADS');
            $el->name('repository');
            $el->value('nasa');
            $repositories .= $el->render();

            $el = null;
        }

        if ($this->app_settings->getUser('connect_pubmed') === '1') {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('repository-pubmed');
            $el->type('radio');
            $el->label('Pubmed');
            $el->name('repository');
            $el->value('pubmed');
            $repositories .= $el->render();

            $el = null;
        }

        if ($this->app_settings->getUser('connect_pmc') === '1') {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('repository-pmc');
            $el->type('radio');
            $el->label('Pubmed Central');
            $el->name('repository');
            $el->value('pmc');
            $repositories .= $el->render();

            $el = null;
        }

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('repository-crossref');
        $el->type('radio');
        $el->readonly('readonly');
        $el->checked('checked');
        $el->label('Crossref');
        $repositories .= $el->render();

        $el = null;

        // Clipboard.
        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $el->addClass('mdi-18px');
        $chevron = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('clipboard-checkbox');
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

            $el->id('project-checkbox-' . $project['id']);
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

        $el->id('tags-new');
        $el->label("{$this->lang->t9n('New tags')} ({$this->lang->t9n('one per line')})");
        $el->name('new_tags');
        $tags_ta = $el->render();

        $el = null;

        // Filter input.
        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('tag-filter');
        $el->name('tag_filter');
        $el->placeholder($this->lang->t9n('Filter-VERB'));
        $el->label($this->lang->t9n('Tags'));
        $el->ariaLabel($this->lang->t9n('Filter-VERB'));
        $el->attr('data-targets', '.label-text');
        $tag_filter = $el->render();

        $el = null;

        // Tags.
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

            $el->id('tag-checkbox-' . $i);
            $el->type('checkbox');
            $el->name("tags[{$i}]");
            $el->value($tag_id);
            $el->label($tag);
            $el->inline(true);

            $tag_checkboxes .= $el->render();

            $el = null;

            $i++;
        }

        $tag_checkboxes .= '</td></tr></table>';

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->body(<<<BODY
            $file_input
            $repositories
            <div id="phase-2" class="mt-3">
                <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#collections">
                    {$chevron}{$this->lang->t9n('Add to')}
                </div>
                <div class="collapse ml-3" id="collections">
                    $clipboard_check $project_checks
                </div>
                <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#tags">
                    {$chevron}{$this->lang->t9n('Tag with')}
                </div>
                <div class="collapse ml-3" id="tags">
                    $tags_ta
                    $tag_filter
                    $tag_checkboxes
                </div>
            </div>
BODY
            , null, 'pt-3');
        $el->footer($upload_button);
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('upload-form');
        $el->method('POST');
        $el->action(IL_BASE_URL . 'index.php/import/batch');
        $el->append("$card $csrf_input");
        $upload_form = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->addClass('d-flex align-content-start');
        $el->column($bc, 'col-12');
        $el->column($upload_form, 'col-md-6 offset-md-3 mb-3');
        $content = $el->render();

        $el = null;

        $this->append(['html' => $content]);

        return $this->send();
    }

    /**
     * @param array $projects
     * @param array $tags
     * @return string
     * @throws Exception
     */
    public function text(array $projects, array $tags) {

        $this->title($this->lang->t9n('Import metadata'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Import wizard'), '#import/wizard');
        $el->item($this->lang->t9n('Import metadata'));
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->id('text');
        $el->name('text');
        $el->style('height: 10rem');
        $el->label("RIS, BibTex, {$this->lang->t9n('or')} Endnote XML");
        $el->placeholder($this->lang->t9n('Paste here'));
        $ta = $el->render();

        $el = null;

        // File uploader.
        $file_input = $this->sharedFileInput(false);

        // We put CSRF key here, because the JS fileupload plugin has its own AJAX methods.

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('csrfToken');
        $el->value($this->session->data('token'));
        $csrf_input = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->addClass('mb-4');
        $el->html($this->lang->t9n('Save'));
        $upload_button = $el->render();

        $el = null;

        // Clipboard.
        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('chevron-down');
        $el->addClass('mdi-18px');
        $chevron = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('clipboard-checkbox');
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

            $el->id('project-checkbox-' . $project['id']);
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

        $el->id('tags-new');
        $el->label("{$this->lang->t9n('New tags')} ({$this->lang->t9n('one per line')})");
        $el->name('new_tags');
        $tags_ta = $el->render();

        $el = null;

        // Filter input.
        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('tag-filter');
        $el->name('tag_filter');
        $el->placeholder($this->lang->t9n('Filter-VERB'));
        $el->label($this->lang->t9n('Tags'));
        $el->ariaLabel($this->lang->t9n('Filter-VERB'));
        $el->attr('data-targets', '.label-text');
        $tag_filter = $el->render();

        $el = null;

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

            $el->id('tag-checkbox-' . $i);
            $el->type('checkbox');
            $el->name("tags[{$i}]");
            $el->value($tag_id);
            $el->label($tag);
            $el->inline(true);

            $tag_checkboxes .= $el->render();

            $el = null;

            $i++;
        }

        $tag_checkboxes .= '</td></tr></table>';

        $help = <<<HELP
            <div data-toggle="collapse" data-target="#pdf-hint" class="cursor-pointer">{$chevron} PDF {$this->lang->t9n('hint')}</div>
            <div class="collapse" id="pdf-hint"><br>
                {$this->lang->t9n('PDFs can be imported with RIS and bib files, if they are found in the data import directory')}.
                {$this->lang->t9n('A relative path in the metadata will be used to find these files, if specified as follows')}:<br><br>
                RIS<br>
                <code>L1  - relative/path/in/import/file.pdf</code><br>
                Bibtex<br>
                <code>file = {FULLTEXT:relative/path/file.pdf:PDF}</code>
            </div>

HELP;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->body(<<<BODY
            $ta
            <p>&mdash;{$this->lang->t9n('OR')}&mdash;</p>
            $file_input
            <div class="mt-3">
                <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#collections">
                    {$chevron}{$this->lang->t9n('Add to')}
                </div>
                <div class="collapse ml-3" id="collections">
                    $clipboard_check $project_checks
                </div>
                <div class="mb-3 cursor-pointer" data-toggle="collapse" data-target="#tags">
                    {$chevron}{$this->lang->t9n('Tag with')}
                </div>
                <div class="collapse ml-3" id="tags">
                    $tags_ta
                    $tag_filter
                    $tag_checkboxes
                </div>
            </div>
BODY
            , null, 'pt-3');
        $el->footer($upload_button . $help);
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('upload-form');
        $el->method('POST');
        $el->action(IL_BASE_URL . 'index.php/import/text');
        $el->append("$card $csrf_input");
        $upload_form = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->addClass('d-flex align-content-start');
        $el->column($bc, 'col-12');
        $el->column($upload_form, 'col-md-6 offset-md-3 mb-3');
        $content = $el->render();

        $el = null;

        $this->append(['html' => $content]);

        return $this->send();
    }
}
