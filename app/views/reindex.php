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

        $this->utils = $this->di->get('ScalarUtils');
        $this->temporal = $this->di->get('Temporal');

        $this->title($this->lang->t9n('Databases and indexes'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Databases and indexes'));
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('check-db');
        $el->context('primary');
        $el->style('min-width: 13rem');
        $el->html($this->lang->t9n('Check integrity'));
        $check_db = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('defragment');
        $el->context('danger');
        $el->style('min-width: 13rem');
        $el->html($this->lang->t9n('Defragment'));
        $defragment = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('reindex');
        $el->context('danger');
        $el->style('min-width: 13rem');
        $el->html($this->lang->t9n('Rebuild indexes'));
        $reindex = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->id('reextract');
        $el->context('danger');
        $el->style('min-width: 13rem');
        $el->html($this->lang->t9n('Re-extract all PDFs'));
        $reextract = $el->render();

        $el = null;

        $size = $this->utils->formatBytes($info['size']);
        $modified = $this->temporal->toUserTime($info['modified']);

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header('<h3>main.db</h3>');
        $el->body(<<<BODY
            <b class='d-inline-block w-25'>{$this->lang->t9n('Size')}:</b> {$size}<br>
            <b class='d-inline-block w-25'>{$this->lang->t9n('Modified')}:</b> {$modified}<br>
BODY
        );
        $el->footer(<<<FOOTER
            <p class="mt-3 text-muted">$check_db</p>
            <p class="text-muted">$defragment</p>
            <p class="text-danger">$reindex {$this->lang->t9n('Do not use, unless instructed after upgrade')}.</p>
            <p class="text-danger">$reextract {$this->lang->t9n('Do not use, unless instructed after upgrade')}.</p>
FOOTER
        );
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($card, 'col-xl-6');
        $row = $el->render();

        $el = null;

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }
}