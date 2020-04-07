<?php

namespace LibrarianApp;

use Exception;
use \Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class IEEEView extends TextView {

    use SharedHtmlView;

    /**
     * Main.
     *
     * @param array $searches
     * @return string
     * @throws Exception
     */
    public function main(array $searches) {

        $this->title("IEEE Xplore\u{00ae} search");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("IEEE Xplore\u{00ae} search");
        $bc = $el->render();

        $el = null;

        $parameters = [
            'abstract'          => 'Abstract',
            'affiliation'       => 'Affiliation',
            'meta_data'         => 'Anywhere',
            'author'            => 'Author',
            'querytext'         => 'Boolean search',
            'doi'               => 'DOI',
            'article_number'    => 'IEEE ID',
            'thesaurus_terms'   => 'IEEE Terms',
            'isbn'              => 'ISBN',
            'issn'              => 'ISSN',
            'is_number'         => 'Issue',
            'index_terms'       => 'Keywords',
            'publication_title' => 'Publication title',
            'article_title'     => 'Title',
            'publication_year'  => 'Year'
        ];

        $preselections = [
            1 => 'meta_data',
            2 => 'article_title',
            3 => 'abstract',
        ];

        // Add three rows of search terms.
        $uid_rows = '';

        for ($row_number = 1; $row_number <= 3; $row_number++) {

            /** @var Bootstrap\Select $el */
            $el = $this->di->get('Select');

            // Select.
            $el->groupClass('col-sm-3');
            $el->name('search_type[' . ($row_number - 1) . ']');
            $el->label('Field');

            foreach ($parameters as $parameter => $description) {

                $selected = $parameter === $preselections[$row_number] ? true : false;
                $el->option($description, $parameter, $selected);
            }


            $el->id('parameter-' . $row_number);
            $select = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('value-' . $row_number);
            $el->groupClass('col-sm-9');
            $el->name('search_query[' . ($row_number - 1) . ']');
            $el->label('Terms');
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

        /** @var Bootstrap\Input $el Content type. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('content-type-7');
        $el->name('search_filter[0][content_type]');
        $el->inline(true);
        $el->checked('checked');
        $el->label('any');
        $content_type = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Content type. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('content-type-1');
        $el->name('search_filter[0][content_type]');
        $el->inline(true);
        $el->value($this->sanitation->attr('Journals .AND. Magazines'));
        $el->label('Journals');
        $content_type .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Content type. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('content-type-2');
        $el->name('search_filter[0][content_type]');
        $el->inline(true);
        $el->value('Conferences');
        $el->label('Conferences');
        $content_type .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Content type. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('content-type-3');
        $el->name('search_filter[0][content_type]');
        $el->inline(true);
        $el->value($this->sanitation->attr('Early Access Articles'));
        $el->label('Early Access');
        $content_type .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Content type. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('content-type-4');
        $el->name('search_filter[0][content_type]');
        $el->inline(true);
        $el->value('Standards');
        $el->label('Standards');
        $content_type .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Content type. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('content-type-5');
        $el->name('search_filter[0][content_type]');
        $el->inline(true);
        $el->value('Books');
        $el->label('Books');
        $content_type .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Content type. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('content-type-6');
        $el->name('search_filter[0][content_type]');
        $el->inline(true);
        $el->value('Courses');
        $el->label('Courses');
        $content_type .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Content type. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('content-type-8');
        $el->name('search_filter[0][content_type]');
        $el->inline(true);
        $el->value('Magazines');
        $el->label('Magazines');
        $content_type .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-13');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->checked('checked');
        $el->label('any');
        $publishers = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-1');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('Alcatel-Lucent');
        $el->label('Alcatel-Lucent');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-2');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('AGU');
        $el->label('AGU');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-3');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('BIAI');
        $el->label('BIAI');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-4');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('CSEE');
        $el->label('CSEE');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-5');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('IBM');
        $el->label('IBM');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-6');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('IEEE');
        $el->label('IEEE');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-7');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('IET');
        $el->label('IET');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-8');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('MITP');
        $el->label('MITP');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-9');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('Morgan .AND. Claypool');
        $el->label('Morgan & Claypool');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-10');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('SMPTE');
        $el->label('SMPTE');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-11');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('TUP');
        $el->label('TUP');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Publishers. */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('publisher-12');
        $el->name('search_filter[0][publisher]');
        $el->inline(true);
        $el->value('VDE');
        $el->label('VDE');
        $publishers .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el Open access. Not working in API. */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('open-access');
        $el->name('search_filter[0][open_access]');
        $el->inline(true);
        $el->value('True');
        $el->label('Open Access');
        $open_access = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('start-year');
        $el->groupClass('col-sm-3 col-xl-2');
        $el->maxLength(4);
        $el->name('search_filter[0][start_year]');
        $el->inline(true);
        $el->label('Year from');
        $start_year = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('end-year');
        $el->groupClass('col-sm-3 col-xl-2');
        $el->maxLength(4);
        $el->name('search_filter[0][end_year]');
        $el->inline(true);
        $el->label('Year to');
        $end_year = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->addClass('my-2 mr-3');
        $el->context('primary');
        $el->html('Search');
        $search = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->inline(true);
        $el->id('save-search');
        $el->name('save_search');
        $el->value('1');
        $el->label('save this search for later');
        $save_search = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->method('GET');
        $el->id('ieee-search-form');
        $el->addClass('search-form');
        $el->action('#ieee/search');
        $el->html(<<<EOT
            $uid_rows
            $clone
            $clone_remove
            <div class="mb-3">
                <b>Content type</b><br>
                $content_type
            </div>
            <div class="mb-3">
                <b>Publishers</b><br>
                $publishers
            </div>
            <div class="mb-3">
                <b>License</b><br>
                $open_access
            </div>
            <div class="form-row mb-3">
                $start_year
                $end_year
            </div>
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
