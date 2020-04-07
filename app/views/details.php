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

        $this->title("Software details");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("Software details");
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
        $el->div("<b>SOFWTARE VERSION</b> <i>Installed version</i>", 'd-flex justify-content-between py-2');
        $el->div("I, Librarian <span>{$details['I, Librarian']['present']}</span>", 'd-flex justify-content-between py-2');
        $el->div("PHP ({$details['PHP']['required']} required) $php_required", 'd-flex justify-content-between py-2');
        $el->div("SQLite ({$details['SQLite']['required']} required) $sqlite_required", 'd-flex justify-content-between py-2');
        $versions = $el->render();

        $el = null;

        // PHP extensions.
        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->addClass('mb-3');
        $el->div("<b>PHP EXTENSIONS</b> <i>Installed/missing</i>", 'd-flex justify-content-between py-2');

        foreach ($details['loaded_extensions'] as $ext => $bool) {

            $installed = $bool === true ?
                '<span class="text-success">installed</span>' :
                '<span class="text-danger">missing</span>';

            $el->div("{$ext} {$installed}", 'd-flex justify-content-between py-2');
        }

        $extensions = $el->render();

        $el = null;

        // Ini.
        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->addClass('mb-3');
        $el->div("<b>PHP.INI SETTINGS</b> <i>Current setting</i>", 'd-flex justify-content-between py-2');

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
        $el->div("<b>BINARY EXECUTABLES</b> <i>Installed/missing</i>", 'd-flex justify-content-between py-2');

        foreach ($details['binaries'] as $key => $val) {

            $comp = $val === 'installed' ?
                "<span class=\"text-success\">installed</span>" :
                "<span class=\"text-danger\">missing</span>";

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
