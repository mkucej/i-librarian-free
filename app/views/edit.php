<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap as Bootstrap;
use Librarian\ItemMeta;
use Librarian\Mvc\TextView;

class EditView extends TextView {

    /**
     * @var ItemMeta
     */
    private $item_meta;

    /**
     * @param $item
     * @return string
     * @throws Exception
     */
    public function main($item) {

        // We need ItemMeta().
        $this->item_meta = $this->di->get('ItemMeta');

        $this->title("{$this->lang->t9n('Edit item')} - {$item['title']}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item("{$item['title']}", '#summary?id=' . $item['id']);
        $el->item($this->lang->t9n('Edit item'));
        $bc = $el->render();

        $el = null;

        // Item labels for this ref type.
        $item_labels = $this->item_meta->getLabels($this->lang, $item['reference_type']);

        // Authors.
        $author_inputs = '';
        $i = 1;

        if (isset($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']])) {

            foreach ($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']] as $key => $value) {

                /** @var Bootstrap\Typeahead $el */
                $el = $this->di->get('Typeahead');

                $el->id('author-last-' . $i);
                $el->addClass("input-typeahead");
                $el->groupClass('col');
                $el->name('author_last_name[]');
                $el->value($value);
                $el->label($this->lang->t9n('Last name'));
                $el->source(IL_BASE_URL . "index.php/filter/author");
                $last_name = $el->render();

                $el = null;

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->id('author-first-' . $i);
                $el->groupClass('col');
                $el->name('author_first_name[]');
                $el->value($item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$key]);
                $el->label($this->lang->t9n('First name'));
                $first_name = $el->render();

                $el = null;

                $author_inputs .= <<<EOT
                <div class="form-row">
                    $last_name
                    $first_name
                </div>
EOT;

                $i++;
            }
        }

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
        $el->addClass('btn-round');
        $el->context('primary');
        $el->icon('plus');
        $clone_authors = $el->render();

        $el = null;

        $author_inputs .= <<<EOT
            <div id="new-author-container" class="form-row">
                $new_last_name
                $new_first_name
            </div>
            $clone_authors
EOT;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mt-2 mb-3');
        $el->style('max-height: 50vh;overflow: auto');
        $el->body($author_inputs, null, 'pt-3');
        $author_card = "<b>{$this->lang->t9n($item_labels['authors'])}</b><br>" . $el->render();

        $el = null;

        // Editors.
        $editor_inputs = '';
        $i = 1;

        if (isset($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']])) {

            foreach ($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']] as $key => $value) {

                /** @var Bootstrap\Typeahead $el */
                $el = $this->di->get('Typeahead');

                $el->id('editor-last-' . $i);
                $el->addClass("input-typeahead");
                $el->groupClass('col');
                $el->name('editor_last_name[]');
                $el->value($value);
                $el->label($this->lang->t9n('Last name'));
                $el->source(IL_BASE_URL . "index.php/filter/editor");
                $last_name = $el->render();

                $el = null;

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->id('editor-first-' . $i);
                $el->groupClass('col');
                $el->name('editor_first_name[]');
                $el->value($item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$key]);
                $el->label($this->lang->t9n('First name'));
                $first_name = $el->render();

                $el = null;

                $editor_inputs .= <<<EOT
                <div class="form-row">
                    $last_name
                    $first_name
                </div>
EOT;

                $i++;
            }
        }

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
        $el->addClass('btn-round');
        $el->context('primary');
        $el->icon('plus');
        $clone_editors = $el->render();

        $el = null;

        $editor_inputs .= <<<EOT
            <div id="new-editor-container" class="form-row">
                $new_last_name
                $new_first_name
            </div>
            $clone_editors
EOT;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mt-2 mb-3');
        $el->style('max-height: 50vh;overflow: auto');
        $el->body($editor_inputs, null, 'pt-3');
        $editor_card = "<b>{$this->lang->t9n($item_labels['editors'])}</b><br>" . $el->render();

        $el = null;

        // UIDs.
        $item[ItemMeta::COLUMN['UIDS']] = isset($item[ItemMeta::COLUMN['UIDS']]) ? $item[ItemMeta::COLUMN['UIDS']] : [];

        foreach ($item[ItemMeta::COLUMN['UIDS']] as $key => $uid) {

            if (empty($uid)) {

                continue;
            }

            /** @var Bootstrap\Select $el */
            $el = $this->di->get('Select');

            $el->addClass("custom-select");
            $el->groupClass('col-sm-3');
            $el->name('uid_types[]');
            $el->id('uid-type-' . $key);
            $el->label("UID {$this->lang->t9n('type-NOUN')}");
            $el->option('', '');

            foreach (ItemMeta::UID_TYPE as $option => $label) {

                $selected = $option === $item[ItemMeta::COLUMN['UID_TYPES']][$key] ? true : false;
                $el->option($label, $option, $selected);
            }

            $uid_html = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('uid-' . $key);
            $el->groupClass('col-sm-9');
            $el->name('uids[]');
            $el->label('UID');
            $el->value($uid);
            $uid_html .= $el->render();

            $uid_rows[] = <<<EOT
                <div class="form-row">
                    $uid_html
                </div>
EOT;
        }

        // New UID.
        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->groupClass('col-sm-3');
        $el->name('uid_types[]');
        $el->id('new-uid-type');
        $el->label("UID {$this->lang->t9n('type-NOUN')}");
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

        $uid_rows[] = <<<EOT
            <div id="uid-row" class="form-row">
                $uid_html
            </div>
            $clone_uid
EOT;

        $uid_html = join(' ', $uid_rows);

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
            $el->value($item[$name]);
            $el->label($item_labels[$name]);
            $inputs[$name] = $el->render();

            $el = null;
        }

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
            $el->value($item[$name]);
            $el->label($item_labels[$name]);
            $el->source(IL_BASE_URL . "index.php/filter/{$source}");
            $typeaheads[$name] = $el->render();

            $el = null;
        }

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
                    $options = ItemMeta::TYPE;
            }

            foreach ($options as $value => $option) {

                $selected = $option === $item[$name] ? true : false;
                $el->option($option, $option, $selected);
            }

            $selects[$name] = $el->render();

            $el = null;
        }

        // Textareas.
        $tas = [];
        $ta_names = [
            'title',
            'abstract',
            'affiliation',
            'urls',
            'keywords'
        ];

        foreach ($ta_names as $name) {

            // Bigger tas.
            $height = in_array($name, ['abstract', 'keywords']) === true ? '8.5' : '4';
            $value = in_array($name, ['urls', 'keywords']) === true ? join(PHP_EOL, $item[$name]) : $item[$name];

            /** @var Bootstrap\Textarea $el */
            $el = $this->di->get('Textarea');

            $el->id(str_replace('_', '-', $name));
            $el->style("height: {$height}rem");
            $el->name($name);
            $el->html($value);
            $el->label($item_labels[$name]);
            $tas[$name] = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->id('item-id-hidden');
        $el->name('id');
        $el->value($item['id']);
        $hidden = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html($this->lang->t9n('Save'));
        $submit = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('edit-form');
        $el->autocomplete('off');
        $el->action(IL_BASE_URL . 'index.php/edit/save');
        $el->html("<a href=\"#pdf/manage?id={$item['id']}\" class=\"d-inline-block mb-2\">Add/replace PDF</a>"
            . $selects['reference_type']
            . $tas['title']
            . $tas['abstract']
            . $author_card
            . $editor_card
            . $tas['affiliation']
            . $typeaheads['primary_title']
            . $typeaheads['secondary_title']
            . $typeaheads['tertiary_title']
            . $inputs['publication_date']
            . $inputs['volume']
            . $inputs['issue']
            . $inputs['pages']
            . $inputs['publisher']
            . $inputs['place_published']
            . $tas['urls']
            . $uid_html
            . $inputs['bibtex_id']
            . $selects['bibtex_type']
            . $tas['keywords']
            . $typeaheads['custom1']
            . $typeaheads['custom2']
            . $typeaheads['custom3']
            . $typeaheads['custom4']
            . $typeaheads['custom5']
            . $typeaheads['custom6']
            . $typeaheads['custom7']
            . $typeaheads['custom8']
            . $hidden
            . $submit
        );
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->body($form, null, 'pt-3');
        $form_card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($bc, 'col-12');
        $el->column($form_card, 'col-xl-8 offset-xl-2 mb-5');
        $row = $el->render();

        $el = null;

        $this->append(['html' => $row]);

        return $this->send();
    }
}
