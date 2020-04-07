<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class CitationView extends TextView {

    /**
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function main(array $data): string {

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item('Citation styles');
        $bc = $el->render();

        $el = null;

        // New style form.

        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->name('csl');
        $el->label('Citation style as CSL');
        $el->hint('<a href="https://github.com/citation-style-language/styles" target="_blank">CSL repository</a> (Dependent styles are not supported.)');
        $ta = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html('Save');
        $save = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('form-new-csl');
        $el->action(IL_BASE_URL . 'index.php/citation/edit');
        $el->html($ta . $save);
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>ADD/REPLACE CITATION STYLE</b>");
        $el->body($form);
        $csl_card = $el->render();

        $el = null;

        // CSL table.

        /** @var Bootstrap\Table $el */
        $el = $this->di->get('Table');

        $el->id('table-csl');
        $el->addClass('table-hover w-100');
        $el->head([
            ['Journal'], ['last modified'], ['']
        ]);
        $el->bodyRow([
            ['<span class="text-secondary">NO RECORDS</span>'], [''], ['']
        ]);
        $table = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->action(IL_BASE_URL . 'index.php/citation/new');
        $el->column($csl_card, 'col-xl-4');
        $el->column($table, 'col-xl-8');
        $row = $el->render();

        $el = null;

        // View modal.

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-csl');
        $el->componentSize('large');
        $el->header('Citation style');
        $el->body('<pre style="color: inherit"><code id="csl-xml"></code></pre>', 'bg-darker-5');
        $modal = $el->render();

        $el = null;

        // Replace id column with a button.
        foreach ($data as $key => $item) {

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->context('primary');
            $el->html('View');
            $el->attr('data-toggle', 'modal');
            $el->attr('data-target', '#modal-csl');
            $el->attr('data-id', $item[2]);
            $el->attr('data-name', $item[0]);
            $view = $el->render();

            $el = null;

            $data[$key][2] = $view;
        }

        $this->append([
            'html'   => $bc . $row . $modal,
            'styles' => $data
        ]);

        return $this->send();
    }
}
