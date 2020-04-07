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

        $this->title("Tags - {$tags['title']}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item("{$tags['title']}", '#summary?id=' . $id);
        $el->item('Tags');
        $bc = $el->render();

        $el = null;

        // Form.

        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->name('new_tags');
        $el->label('New tags <span class="text-muted">(one per line)</span>');
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
        $el->html('Save');
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

        $el->header("<b>ADD NEW ITEM TAGS</b>");
        $el->body($form);
        $form_card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        if (empty($tags['tags'])) {

            $el->column('No tags', 'col text-center text-muted text-uppercase py-4');
        }

        foreach ($tags['tags'] as $tag_id => $tag) {

            $el2 = $this->di->get('Input');

            $el2->addClass('tag-inputs');
            $el2->id('tag-' . $tag_id);
            $el2->type('checkbox');
            $el2->name('tag_id[]');
            $el2->value($tag_id);
            $el2->label($tag);

            // Make recommended tags bold.
            if (in_array($tag, $tags['recommended_tags'])) {

                $el2->groupClass('font-weight-bold');
            }

            // Item tags are checked.
            if (isset($tags['item_tags'][$tag_id])) {

                $el2->checked('checked');
            }

            $tag_html = $el2->render();

            $el->column($tag_html, 'col-lg-6 mt-1 mb-1');
        }

        $tag_row = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>ITEM TAGS</b>");
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

    /**
     * Manage tags.
     *
     * @param array $tags
     * @return string
     * @throws Exception
     */
    public function manage(array $tags): string {

        $this->title("Tags");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("Tags");
        $bc = $el->render();

        $el = null;

        // Form.

        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->name('new_tags');
        $el->label('New tags (one per line)');
        $ta = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html('Save');
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

        $el->header("<b>CREATE NEW TAGS</b>");
        $el->body($form);
        $form_card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        if (empty($tags)) {

            $el->column('No tags', 'col text-center text-muted text-uppercase py-4');
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
            $plural = $tag['count'] === '1' ? '' : 's';
            $count = $this->scalar_utils->formatNumber($tag['count']);

            $label = <<<LABEL
<div class="$class" style="transform: translateY(-6px)">{$count} item{$plural} tagged</div>
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

        $el->header("<b>EDIT TAGS</b>");
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
