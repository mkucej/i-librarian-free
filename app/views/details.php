<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class DetailsView extends TextView {

    /**
     * @param array $details
     * @return string
     * @throws Exception
     */
    public function main(array $details): string {

        $this->title($this->lang->t9n('Software details'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Software details'));
        $bc = $el->render();

        $el = null;

        // PHP version.
        $php_required = version_compare($details['PHP']['present'], $details['PHP']['required']) > -1 ?
            "<span class=\"text-success\">{$details['PHP']['present']}</span>" :
            "<span class=\"text-danger\">{$details['PHP']['present']}</span>";

        // Sqlite.
        $sqlite_required = version_compare($details['SQLite']['present'], $details['SQLite']['required']) > -1 ?
            "<span class=\"text-success\">{$details['SQLite']['present']}</span>" :
            "<span class=\"text-danger\">{$details['SQLite']['present']}</span>";

        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->addClass('mb-3');
        $el->div(
<<<HTML
<b class="text-uppercase">{$this->lang->t9n('software version')}</b>
<i class="text-capitalize">{$this->lang->t9n('installed')}</i>
HTML
        , 'd-flex justify-content-between py-2');
        $el->div("I, Librarian <span>{$details['I, Librarian']['present']}</span>", 'd-flex justify-content-between py-2');
        $el->div("PHP ({$details['PHP']['required']}+) $php_required", 'd-flex justify-content-between py-2');
        $el->div("SQLite ({$details['SQLite']['required']}+) $sqlite_required", 'd-flex justify-content-between py-2');
        $versions = $el->render();

        $el = null;

        // PHP extensions.
        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->addClass('mb-3');
        $el->div(
<<<HTML
<b class="text-uppercase">PHP {$this->lang->t9n('extensions')}</b>
<i class="text-capitalize">{$this->lang->t9n('installed')}/{$this->lang->t9n('missing')}</i>
HTML
        , 'd-flex justify-content-between py-2');

        foreach ($details['loaded_extensions'] as $ext => $bool) {

            $installed = $bool === true ?
                "<span class=\"text-success\">{$this->lang->t9n('installed')}</span>" :
                "<span class=\"text-danger\">{$this->lang->t9n('missing')}</span>";

            $el->div("{$ext} {$installed}", 'd-flex justify-content-between py-2');
        }

        $extensions = $el->render();

        $el = null;

        // Ini.
        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->addClass('mb-3');
        $el->div(
<<<HTML
<b class="text-uppercase">PHP.INI {$this->lang->t9n('settings')}</b>
<i>{$this->lang->t9n('Current setting')}</i>
HTML
        , 'd-flex justify-content-between py-2');

        foreach ($details['ini'] as $key => $val) {

            $comp = $val['present'] === $val['required'] || (int) $val['present'] > (int) $val['required'] ?
                "<span class=\"text-success\">{$val['present']}</span>" :
                "<span class=\"text-danger\">{$val['present']}</span>";

            $el->div("{$key} = {$val['required']} $comp", 'd-flex justify-content-between py-2');
        }

        $ini = $el->render();

        $el = null;

        // Binaries.
        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->addClass('mb-3');
        $el->div(
<<<HTML
<b class="text-uppercase">{$this->lang->t9n('binary executables')}</b>
<i class="text-capitalize">{$this->lang->t9n('installed')}/{$this->lang->t9n('missing')}</i>
HTML
        , 'd-flex justify-content-between py-2');

        foreach ($details['binaries'] as $key => $val) {

            $comp = $val === 'installed' ?
                "<span class=\"text-success\">{$this->lang->t9n('installed')}</span>" :
                "<span class=\"text-danger\">{$this->lang->t9n('missing')}</span>";

            $el->div("{$key} $comp", 'd-flex justify-content-between py-2');
        }

        $binaries = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($versions . $ini . $binaries);
        $el->column($extensions);
        $row = $el->render();

        $el = null;

        $this->append(['html' => $bc . $row]);

        return $this->send();
    }
}
