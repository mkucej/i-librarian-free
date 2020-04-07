<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class NormalizeView extends TextView {

    /**
     * Initial view.
     *
     * @return string
     * @throws Exception
     */
    public function main(): string {

        $this->title("Normalize data");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("Normalize data");
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header('<b>LIST POSSIBLE DUPLICATES IN:</b>');
        $el->body(<<<LINKS
            <p><a href="#normalize/authors">Authors</a></p>
            <p><a href="#normalize/editors">Editors</a></p>
            <p><a href="#normalize/primary">Primary titles</a></p>
            <p><a href="#normalize/secondary">Secondary titles</a></p>
            <p><a href="#normalize/tertiary">Tertiary titles</a></p>
LINKS
        );
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->id('select-metadata');
        $el->label('Column type');
        $el->option('Authors', 'authors');
        $el->option('Editors', 'editors');
        $el->option('Primary titles', 'primarytitles');
        $el->option('Secondary titles', 'secondarytitles');
        $el->option('Tertiary titles', 'tertiarytitles');
        $select = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('search-metadata');
        $el->label('Search data to edit');
        $el->name('filter');
        $el->attr('data-source', IL_BASE_URL . 'index.php/normalize/searchauthors');
        $el->attr('data-container', '#results');
        $input = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header('<b>SEARCH METADATA</b>');
        $el->body($select . $input . '<div id="results"></div>');
        $card2 = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($card2);
        $el->column($card);
        $row = $el->render();

        $el = null;

        $this->append(['html' => $bc . $row]);

        return $this->send();
    }

    /**
     * List of duplicates - links.
     *
     * @param string $type
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function results(string $type, array $data): string {

        switch ($type) {

            case 'authors';
                $title = 'Authors';
                $column = 'author';
                $param = 'filter[author][]';
                break;

            case 'editors';
                $title = 'Editors';
                $column = 'editor';
                $param = 'filter[editor][]';
                break;

            case 'primary';
                $title = 'Primary titles';
                $column = 'primary_title';
                $param = 'filter[primary_title][]';
                break;

            case 'secondary';
                $title = 'Secondary titles';
                $column = 'secondary_title';
                $param = 'filter[secondary_title][]';
                break;

            case 'tertiary';
                $title = 'Tertiary titles';
                $column = 'tertiary_title';
                $param = 'filter[tertiary_title][]';
                break;
        }

        $this->title("{$title} - Normalize data");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item('Normalize data', '#normalize/main');
        $el->item($title);
        $bc = $el->render();

        $el = null;

        $count = count($data) === 0 ? 'no' : count($data);
        $header = "<h4>Found $count cases of possible duplication.</h4>";

        /** @var Bootstrap\IconButton $el */
        $btn = $this->di->get('IconButton');

        $btn->type('submit');
        $btn->context('danger');
        $btn->style('border:0');
        $btn->icon('content-save-outline');
        $save = $btn->render();

        $btn = null;

        $i = 1;
        $cards = '';
        $IL_BASE_URL = IL_BASE_URL;

        foreach ($data as $duplicates) {

            $forms = '';

            foreach ($duplicates as $item) {

                if ($type === 'authors' || $type === 'editors') {

                    /** @var Bootstrap\Inputgroup $el2 */
                    $el2 = $this->di->get('Input');

                    $el2->id('input-' . $item[0]);
                    $el2->name("{$column}[{$item[0]}][last_name]");
                    $el2->value($item[2]);
                    $el2->label('Last name');
                    $input1 = $el2->render();

                    $el2 = null;

                    /** @var Bootstrap\Inputgroup $el2 */
                    $el2 = $this->di->get('InputGroup');

                    $el2->id('input-' . $item[0]);
                    $el2->name("{$column}[{$item[0]}][first_name]");
                    $el2->value($item[1]);
                    $el2->label('First name');
                    $el2->appendButton($save);
                    $input2 = $el2->render();

                    $el2 = null;

                    /** @var Bootstrap\Row $el */
                    $r = $this->di->get('Row');

                    $r->column($input1, 'col-sm-6');
                    $r->column($input2, 'col-sm-6');
                    $input = $r->render();

                    $r = null;

                } else {

                    /** @var Bootstrap\Inputgroup $el2 */
                    $el2 = $this->di->get('InputGroup');

                    $el2->id('input-' . $item[0]);
                    $el2->name("{$column}[{$item[0]}]");
                    $el2->value($item[1]);
                    $el2->appendButton($save);

                    $input = $el2->render();

                    $el2 = null;
                }

                $link = <<<LINK
<div style="transform: translateY(-6px)">
    <a href='{$IL_BASE_URL}index.php/#items/filter?{$param}={$item[0]}'>Items with this metadata</a>
</div>
LINK;

                /** @var Bootstrap\Form $el */
                $f = $this->di->get('Form');

                $f->addClass('edit-form');
                $f->action(IL_BASE_URL . "index.php/normalize/edit");
                $f->html($input);
                $forms .= $f->render() . $link;

                $f = null;
            }

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->header("<h5>$i</h5>", 'pb-0');
            $el->addClass('mb-3');
            $el->body($forms);
            $cards .= $el->render();

            $el = null;

            $i++;
        }

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->style('margin-bottom: 30vh');
        $el->column($cards);
        $row = $el->render();

        $el = null;

        $this->append(['html' => $bc . $header . $row]);

        return $this->send();
    }

    /**
     * List of duplicates after typeahead search.
     *
     * @param string $type
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function filtered(string $type, array $data): string {

        switch ($type) {

            case 'authors';
                $column = 'author';
                $param = 'filter[author][]';
                break;

            case 'editors';
                $column = 'editor';
                $param = 'filter[editor][]';
                break;

            case 'primary';
                $column = 'primary_title';
                $param = 'filter[primary_title][]';
                break;

            case 'secondary';
                $column = 'secondary_title';
                $param = 'filter[secondary_title][]';
                break;

            case 'tertiary';
                $column = 'tertiary_title';
                $param = 'filter[tertiary_title][]';
                break;
        }

        /** @var Bootstrap\IconButton $el */
        $btn = $this->di->get('IconButton');

        $btn->type('submit');
        $btn->context('danger');
        $btn->style('border:0');
        $btn->icon('content-save-outline');
        $save = $btn->render();

        $btn = null;

        $IL_BASE_URL = IL_BASE_URL;
        $forms = '';

        foreach ($data as $id => $item) {

            if ($type === 'authors' || $type === 'editors') {

                $parts = explode(', ', $item);
                $last_name = $parts[0];
                $first_name = isset($parts[1]) ? $parts[1] : '';

                /** @var Bootstrap\Inputgroup $el2 */
                $el2 = $this->di->get('Input');

                $el2->id('input-' . $id);
                $el2->name("{$column}[{$id}][last_name]");
                $el2->value($last_name);
                $el2->label('Last name');
                $input1 = $el2->render();

                $el2 = null;

                /** @var Bootstrap\Inputgroup $el2 */
                $el2 = $this->di->get('InputGroup');

                $el2->id('input-' . $id);
                $el2->name("{$column}[{$id}][first_name]");
                $el2->value($first_name);
                $el2->label('First name');
                $el2->appendButton($save);
                $input2 = $el2->render();

                $el2 = null;

                /** @var Bootstrap\Row $el */
                $r = $this->di->get('Row');

                $r->column($input1, 'col-sm-6');
                $r->column($input2, 'col-sm-6');
                $input = $r->render();

                $r = null;

            } else {

                /** @var Bootstrap\Inputgroup $el2 */
                $el2 = $this->di->get('InputGroup');

                $el2->id('input-' . $id);
                $el2->name("{$column}[{$id}]");
                $el2->value($item);
                $el2->appendButton($save);

                $input = $el2->render();

                $el2 = null;
            }

            $link = <<<LINK
<div style="transform: translateY(-6px)">
<a href='{$IL_BASE_URL}index.php/#items/filter?{$param}={$id}'>Items with this metadata</a>
</div>
LINK;

            /** @var Bootstrap\Form $el */
            $f = $this->di->get('Form');

            $f->addClass('edit-form');
            $f->action(IL_BASE_URL . "index.php/normalize/edit");
            $f->html($input);
            $forms .= $f->render() . $link;

            $f = null;
        }

        $this->append(['html' => "<div id='results'>$forms</div>"]);

        return $this->send();
    }
}
