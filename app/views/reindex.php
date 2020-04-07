<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Media\ScalarUtils;
use Librarian\Media\Temporal;
use Librarian\Mvc\TextView;

class ReindexView extends TextView {

    /**
     * @var Temporal
     */
    private $temporal;

    /**
     * @var ScalarUtils
     */
    private $utils;

    /**
     * @param array $info
     * @return string
     * @throws Exception
     */
    public function main(array $info): string {

        $this->title('Databases & indexes');

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("Databases & indexes");
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('alert');
        $icon = $el->render();

        $el = null;

        $warning = <<<WARN
            <p class="text-danger">
                $icon The database actions below can take a long time, during which the software may become
                unresponsive to other users. Only do these actions during downtime or low user activity.
            </p>
WARN;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('check-db');
        $el->context('primary');
        $el->style('width: 13rem');
        $el->html('Check integrity');
        $check_db = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('defragment');
        $el->context('danger');
        $el->style('width: 13rem');
        $el->html('Defragment');
        $defragment = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('reindex');
        $el->context('danger');
        $el->style('width: 13rem');
        $el->html('Rebuild indexes');
        $reindex = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('reextract');
        $el->context('danger');
        $el->style('width: 13rem');
        $el->html('Re-extract all PDFs');
        $reextract = $el->render();

        $el = null;

        $this->utils = $this->di->get('ScalarUtils');
        $size = $this->utils->formatBytes($info['size']);

        $this->temporal = $this->di->get('Temporal');
        $modified = $this->temporal->toUserTime($info['modified']);

        $writable = $info['writable'] === '1' ? 'yes' : 'no';

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header('<h3>main.db</h3>');
        $el->body(<<<BODY
            <b class='d-inline-block w-25'>Size:</b> {$size}<br>
            <b class='d-inline-block w-25'>Modified:</b> {$modified}<br>
            <b class='d-inline-block w-25'>Writable:</b> {$writable}
BODY
        );
        $el->footer(<<<FOOTER
            <p class="mt-3 text-muted">$check_db <br> Checks database referential integrity and indexes.</p>
            <p class="text-muted">$defragment <br> Defragments database, making it smaller and faster.</p>
            <p class="text-danger">$reindex <br> Recreates metadata indexes. Do not use, unless instructed after upgrade.</p>
            <p class="text-danger">$reextract <br> Re-extracts text from all PDFs. Do not use, unless instructed after upgrade.</p>
FOOTER
        );
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($card, 'col-xl-6');
        $row = $el->render();

        $el = null;

        $this->append(['html' => "$bc $warning $row"]);

        return $this->send();
    }
}