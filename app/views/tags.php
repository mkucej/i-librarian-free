<?php

namespace LibrarianApp;

use Exception;
use \Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class TagsView extends TextView {

    /**
     * Main.
     *
     * @param int $id
     * @param array $tags
     * @return string
     * @throws Exception
     */
    public function item(int $id, array $tags): string {

        $this->title("{$this->lang->t9n('Tags')} - {$tags['title']}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item("{$tags['title']}", '#summary?id=' . $id);
        $el->item($this->lang->t9n('Tags'));
        $bc = $el->render();

        $el = null;

        // Form.
        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->id('new-tags');
        $el->name('new_tags');
        $el->label("{$this->lang->t9n('New tags')} <span class=\"text-muted\">({$this->lang->t9n('one per line')})</span>");
        $ta = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('id');
        $el->value($id);
        $hidden_id = $el->render();

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

        $el->id('tag-form');
        $el->action(IL_BASE_URL . 'index.php/tags/create');
        $el->html("$ta $hidden_id $submit");
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header("<b>{$this->lang->t9n('Add new item tags')}</b>", 'text-uppercase');
        $el->body($form);
        $form_card = $el->render();

        $el = null;

        // Filter input.
        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('tag-filter');
        $el->name('tag_filter');
        $el->placeholder($this->lang->t9n('Filter-VERB'));
        $el->ariaLabel($this->lang->t9n('Filter-VERB'));
        $el->attr('data-targets', '.label-text');
        $filter = $el->render();

        $el = null;

        $tag_html = '';

        if (empty($tags['tags'])) {

            $tag_html = "<div class=\"text-center text-muted text-uppercase py-4\">{$this->lang->t9n('No tags')}</div>";

        } else {

            // First letter.
            $first_letter = '';

            $tag_html .= '<table class="tag-table"><tr><td style="width:2.25rem"></td><td>';

            foreach ($tags['tags'] as $tag_id => $tag) {

                $first_letter2 = mb_strtoupper($this->scalar_utils->deaccent($tag[0] === '' ? '' : mb_substr($tag, 0, 1, 'UTF-8')), 'UTF-8');

                if ($first_letter2 !== $first_letter) {

                    $tag_html .= '</td></tr><tr>';

                    /** @var Bootstrap\Badge $el */
                    $el = $this->di->get('Badge');

                    $el->context('warning');
                    $el->addClass('d-inline-block mr-2 mb-2');
                    $el->style('width: 1.33rem');
                    $el->html($first_letter2);
                    $tag_html .= '<td>' . $el->render() . '</td><td>';

                    $el = null;

                    $first_letter = $first_letter2;
                }

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->groupClass('tag-divs');
                $el->addClass('tag-inputs');
                $el->id('tag-' . $tag_id);
                $el->type('checkbox');
                $el->name('tag_id[]');
                $el->value($tag_id);
                $el->label($tag);
                $el->inline(true);

                // Make recommended tags bold.
                if (in_array($tag, $tags['recommended_tags'])) {

                    $el->groupClass('font-weight-bold');
                }

                // Item tags are checked.
                if (isset($tags['item_tags'][$tag_id])) {

                    $el->checked('checked');
                }

                $tag_html .= $el->render();

                $el = null;
            }

            $tag_html .= '</td></tr></table>';
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>{$this->lang->t9n('Item tags')}</b>", 'text-uppercase');
        $el->body($filter . $tag_html);
        $tag_card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($form_card, 'mb-3 col-xl-4');
        $el->column($tag_card, 'mb-3 col-xl-8');
        $content = $el->render();

        $el = null;

        $this->append(['html' => "$bc $content"]);

        return $this->send();
    }

    /**
     * Manage tags.
     *
     * @param array $tags
     * @return string
     * @throws Exception
     */
    public function manage(array $tags): string {

        $this->title($this->lang->t9n('Tags'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Tags'));
        $bc = $el->render();

        $el = null;

        // Form.

        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->name('new_tags');
        $el->label("{$this->lang->t9n('New tags')} ({$this->lang->t9n('one per line')})");
        $ta = $el->render();

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

        $el->id('tag-form');
        $el->action(IL_BASE_URL . 'index.php/tags/create');
        $el->html("$ta $submit");
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>{$this->lang->t9n('New tags')}</b>", 'text-uppercase');
        $el->body($form);
        $form_card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        if (empty($tags)) {

            $el->column($this->lang->t9n('No tags'), 'col text-center text-muted text-uppercase py-4');
        }

        /** @var Bootstrap\IconButton $el */
        $btn = $this->di->get('IconButton');

        $btn->type('submit');
        $btn->context('danger');
        $btn->style('border:0');
        $btn->icon('content-save-outline');
        $save = $btn->render();

        $btn = null;

        foreach ($tags as $tag) {

            $tag['count'] = empty($tag['item_id']) ? '0' : $tag['count'];
            $class = $tag['count'] === '0' ? 'text-danger' : 'text-muted';
            $count = $this->scalar_utils->formatNumber($tag['count']);

            $label = <<<LABEL
<div class="$class" style="transform: translateY(-6px)">{$count} {$this->lang->t9n('tagged')}</div>
LABEL;

            /** @var Bootstrap\Inputgroup $el2 */
            $el2 = $this->di->get('InputGroup');

            $el2->addClass('tag-inputs');
            $el2->id('tag-' . $tag['id']);
            $el2->name("tag[{$tag['id']}]");
            $el2->value($tag['tag']);
            $el2->appendButton($save);

            $tag_html = $el2->render();

            $el2 = null;

            /** @var Bootstrap\Form $el */
            $f = $this->di->get('Form');

            $f->addClass('edit-tag-form');
            $f->action(IL_BASE_URL . 'index.php/tags/edit');
            $f->html($tag_html);
            $form2 = $f->render();

            $f = null;

            $el->column($form2 . $label, 'col-sm-6 col-xl-4 mb-1');
        }

        $tag_row = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>{$this->lang->t9n('Edit tags')}</b>", 'text-uppercase');
        $el->body($tag_row);
        $tag_card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($form_card, 'mb-3 col-xl-4');
        $el->column($tag_card, 'mb-3 col-xl-8');
        $content = $el->render();

        $el = null;

        $this->append(['html' => "$bc $content"]);

        return $this->send();
    }
}
