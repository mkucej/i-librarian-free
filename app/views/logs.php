<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class LogsView extends TextView {

    /**
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function main(): string {

        $this->title($this->lang->t9n('Logs'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Logs'));
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Alert $el */
        $el = $this->di->get('Alert');

        $el->context('danger');
        $el->html(<<<ALERT
            User activity logs are available in <i>I, Librarian Pro</i> only. <a href="https://i-librarian.net">Upgrade now</a>
ALERT
        );
        $alert = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($alert, 'col-xl-6 offset-xl-3');
        $row = $el->render();

        $el = null;

        $this->append([
            'html' => $bc . $row
        ]);

        return $this->send();
    }
}
