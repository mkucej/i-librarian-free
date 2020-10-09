<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class PmcView extends TextView {

    use SharedHtmlView;

    /**
     * Main.
     *
     * @param array $searches
     * @return string
     * @throws Exception
     */
    public function main(array $searches): string {

        $this->title("Pubmed Central {$this->lang->t9n('Search-NOUN')}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("Pubmed Central {$this->lang->t9n('Search-NOUN')}");
        $bc = $el->render();

        $el = null;

        $parameters = [
            'AB'      => $this->lang->t9n('Abstract'),
            'AD'      => $this->lang->t9n('Affiliation'),
            'ALL'     => $this->lang->t9n('Anywhere'),
            'AU'      => $this->lang->t9n('Author'),
            'DOI'     => 'DOI',
            'TW'      => $this->lang->t9n('Full text'),
            'PG'      => $this->lang->t9n('Pagination'),
            'TA'      => $this->lang->t9n('Journal Abbreviation'),
            'PMID'    => 'PMID',
            'TI'      => $this->lang->t9n('Title'),
            'VI'      => $this->lang->t9n('Volume'),
            'PUBDATE' => $this->lang->t9n('Year')
        ];

        $preselections = [
            1 => 'TW',
            2 => 'AB',
            3 => 'AU'
        ];

        // Add three rows of search terms.
        $uid_rows = '';

        for ($row_number = 1; $row_number <= 3; $row_number++) {

            /** @var Bootstrap\Select $el */
            $el = $this->di->get('Select');

            $el->groupClass('col-sm-3');
            $el->name('search_type[' . ($row_number - 1) . ']');
            $el->label($this->lang->t9n('Field'));
            $el->id('parameter-' . $row_number);

            foreach ($parameters as $parameter => $description) {

                $selected = $parameter === $preselections[$row_number] ? true : false;
                $el->option($description, $parameter, $selected);
            }

            $select = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('value-' . $row_number);
            $el->groupClass('col-sm-9');
            $el->name('search_query[' . ($row_number - 1) . ']');
            $el->label($this->lang->t9n('Terms'));
            $input = $el->render();

            $el = null;

            // Add ID to last row for cloning purposes.
            $id_str = $row_number === 3 ? 'id="search-row"' : '';

            $uid_rows .= <<<EOT
                <div class="form-row" {$id_str}>
                    $select
                    $input
                </div>
EOT;
        }

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->addClass('clone-button btn-round mb-3');
        $el->context('primary');
        $el->icon('plus');
        $clone = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->addClass('remove-clone-button btn-round ml-2 mb-3');
        $el->context('secondary');
        $el->icon('minus');
        $clone_remove = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('search_type[boolean]');
        $el->value('boolean');
        $hidden = $el->render();

        $el = null;

        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->id('boolean-ta');
        $el->name('search_query[boolean]');
        $el->label("{$this->lang->t9n('Boolean search')} <a target=\"_blank\" href=\"https://www.ncbi.nlm.nih.gov/books/NBK3825/#pmchelp.Search_Queries\">?</a>");
        $boolean = $el->render();

        $el = null;

        // Sorting

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('sorting-1');
        $el->name('sort');
        $el->inline(true);
        $el->value($this->lang->t9n('relevance'));
        $el->label('relevance');
        $el->checked('checked');
        $sorting = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('sorting-2');
        $el->name('sort');
        $el->inline(true);
        $el->value('pubsolr12');
        $el->label($this->lang->t9n('last added'));
        $sorting .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('sorting-3');
        $el->name('sort');
        $el->inline(true);
        $el->value('pub date');
        $el->label($this->lang->t9n('last published'));
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
        $el->id('pmc-search-form');
        $el->addClass('search-form');
        $el->action('#pmc/search');
        $el->html(<<<EOT
            $uid_rows
            $clone
            $clone_remove
            $hidden
            $boolean
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
