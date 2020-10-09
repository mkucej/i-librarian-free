<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class CrossrefView extends TextView {

    use SharedHtmlView;

    /**
     * @param array $searches
     * @return string
     * @throws Exception
     */
    public function main(array $searches) {

        $this->title('Crossref ' . $this->lang->t9n('search-NOUN'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item('Crossref ' . $this->lang->t9n('search-NOUN'));
        $bc = $el->render();

        $el = null;

        $parameters = [
            'bibliographic'   => $this->lang->t9n('Title'),
            'contributor'     => $this->lang->t9n('Author'),
            'container-title' => $this->lang->t9n('Publication name'),
            'doi'             => 'DOI'
        ];

        // Add three rows of search terms.
        $uid_rows = '';
        $i = 0;

        foreach ($parameters as $column => $label) {

            /** @var Bootstrap\Select $el */
            $el = $this->di->get('Select');

            $el->id('parameter-' . $column);
            $el->groupClass('col-sm-3');
            $el->name("search_type[{$i}]");
            $el->readonly('readonly');
            $el->label($this->lang->t9n('Field'));
            $el->option($label, $column, true);

            $select = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('value-' . $column);
            $el->groupClass('col-sm-9');
            $el->name("search_query[{$i}]");
            $el->label($this->lang->t9n('Terms'));
            $input = $el->render();

            $el = null;

            $uid_rows .= <<<EOT
                <div class="form-row">{$select} {$input}</div>
EOT;

            $i++;
        }

        /** @var Bootstrap\Input $el Sorting. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('sorting-1');
        $el->name('sort');
        $el->inline(true);
        $el->value('relevance');
        $el->label($this->lang->t9n('relevance'));
        $el->checked('checked');
        $sorting = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Sorting. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('sorting-2');
        $el->name('sort');
        $el->inline(true);
        $el->value('published');
        $el->label($this->lang->t9n('last published'));
        $sorting .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Sorting. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('sorting-3');
        $el->name('sort');
        $el->inline(true);
        $el->value('updated');
        $el->label($this->lang->t9n('last updated'));
        $sorting .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Sorting. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('sorting-4');
        $el->name('sort');
        $el->inline(true);
        $el->value('is-referenced-by-count');
        $el->label($this->lang->t9n('most cited'));
        $sorting .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('last-days');
        $el->style('width: 10rem');
        $el->maxlength('3');
        $el->pattern('\d{1,3}');
        $el->name('search_filter[0][last_added]');
        $el->label(sprintf($this->lang->t9n('Added in last %s days'), '(1-365)'));
        $last_days = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->addClass('my-2 mr-3');
        $el->context('primary');
        $el->html($this->lang->t9n('Search-VERB'));
        $search = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->inline(true);
        $el->id('save-search');
        $el->name('save_search');
        $el->value('1');
        $el->label($this->lang->t9n('save this search for later'));
        $save_search = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->method('GET');
        $el->id('crossref-search-form');
        $el->addClass('search-form');
        $el->action('#crossref/search');
        $el->html(<<<EOT
            $uid_rows
            <div class="mb-3">
                <b>{$this->lang->t9n('Sorting')}</b><br>
                $sorting
            </div>
            $last_days
            $search
            $save_search
EOT
        );
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el Card. */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->body($form, null, 'pt-3');
        $card = $el->render();

        $el = null;

        // Search list.
        $list = $this->sharedSearchList($searches, true);

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->addClass('mb-3');
        $el->column($card);
        $el->column($list);
        $row = $el->render();

        $el = null;

        $this->append(['html' => $bc . $row]);

        return $this->send();
    }
}
