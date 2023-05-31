<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Media\Temporal;
use Librarian\Mvc\TextView;

class DashboardView extends TextView {

    use SharedHtmlView;

    /**
     * Dashboard HTML.
     *
     * @param $data
     * @return string
     * @throws Exception
     */
    public function main($data) {

        $this->title($this->lang->t9n('Dashboard'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Dashboard'));
        $bc = $el->render();

        $el = null;

        // Cards array.
        $cards = [];

        /*
         * Quick search.
         */

        if ($this->app_settings->getUser('dashboard_remove_search') === '0') {

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->context('link');
            $el->addClass('p-0 ml-3');
            $el->style('transform: translateY(-2px)');
            $el->attr('data-dismiss', 'modal');
            $el->attr('data-toggle', 'modal');
            $el->dataTarget('#modal-advanced-search');
            $el->html($this->lang->t9n('Advanced'));
            $advanced_button = $el->render();

            $el = null;

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->context('link');
            $el->addClass('p-0 ml-3');
            $el->style('transform: translateY(-2px)');
            $el->attr('data-dismiss', 'modal');
            $el->attr('data-toggle', 'modal');
            $el->dataTarget('#modal-searches');
            $el->html($this->lang->t9n('Previous'));
            $searches_button = $el->render();

            $el = null;

            $search_html = $this->sharedQuickSearch();

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('h-100');
            $el->header(
<<<HTML
<div>
    <b class="text-uppercase">{$this->lang->t9n('Search-NOUN')}</b> $advanced_button $searches_button
</div>
HTML
            , 'px-4 pt-3');
            $el->body($search_html, null, 'px-4 pb-4');
            $cards[] = $el->render();

            $el = null;
        }

        /*
         * Import panel.
         */

        if ($this->app_settings->getUser('dashboard_remove_import') === '0') {

            $button_class = $this::$theme === 'dark' ? 'secondary' : 'light';
            $help_class = $this::$theme === 'dark' ? 'dark' : 'secondary';

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->elementName('a');
            $el->href('#import/uid');
            $el->context($button_class);
            $el->addClass('btn-block mb-4 py-3 rounded-0');
            $el->html(
<<<EOT
<h5>DOI, Pubmed ID&hellip;</h5>
<span class="text-{$help_class}">10.2234/26548.225</span>
EOT
                );
            $card_uid = $el->render();

            $el = null;

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->elementName('a');
            $el->href('#import/file');
            $el->context($button_class);
            $el->addClass('btn-block mb-4 py-3 rounded-0');
            $el->html(
<<<EOT
<h5>{$this->lang->t9n('Published PDFs')}</h5>
<span class="mdi mdi-24px mdi-file-pdf-box text-{$help_class}"></span>
<span class="text-{$help_class}">+ 10.2234/2654.225</span>
EOT
            );
            $card_file = $el->render();

            $el = null;

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->elementName('a');
            $el->href('#import/text');
            $el->context($button_class);
            $el->addClass('btn-block mb-4 py-3 rounded-0');
            $el->html(
<<<EOT
<h5>RIS, BibTex, Endnote</h5>
<span class="text-{$help_class}">@article{Smith2011</span>
EOT
            );
            $card_text = $el->render();

            $el = null;

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->elementName('a');
            $el->href('#import/manual');
            $el->context($button_class);
            $el->addClass('btn-block mb-4 py-3 rounded-0');
            $el->html(
<<<EOT
<h5>{$this->lang->t9n('Unpublished file')}</h5>
<span class="mdi mdi-24px mdi-file-word-box text-{$help_class}"></span>
<span class="mdi mdi-24px mdi-image text-{$help_class}"></span>
<span class="mdi mdi-24px mdi-file-pdf-box text-{$help_class}"></span>
EOT
            );
            $card_manual = $el->render();

            $el = null;

            /** @var Bootstrap\Row $el */
            $el = $this->di->get('Row');

            $el->column($card_uid, 'col-sm-6');
            $el->column($card_file, 'col-sm-6');
            $el->column($card_manual, 'col-sm-6');
            $el->column($card_text, 'col-sm-6');
            $button_row = $el->render();

            $el = null;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('h-100');
            $el->header(
                <<<HTML
<span>
    <b class="text-uppercase">{$this->lang->t9n('Import')}</b>
</span>
HTML
                , 'px-4 pt-3');
            $el->body($button_row, null, 'px-4 pb-2');
            $cards[] = $el->render();

            $el = null;
        }

        /*
         * Last items.
         */

        if ($this->app_settings->getUser('dashboard_remove_items') === '0') {

            $last5_html =
                <<<HTML
<table style="table-layout: fixed;width:100%;line-height: 2rem">
    <tbody>
HTML;

            if (count($data['last_items']) === 0) {

                $last5_html .=
                    <<<HTML
        <tr>
            <td style="height: 10rem" class="text-center text-secondary text-uppercase pb-4 align-middle">
                {$this->lang->t9n('No items')}
            </td>
        </tr>
HTML;
            }

            foreach ($data['last_items'] as $item) {

                $item_url = IL_BASE_URL . 'index.php/item#summary?id=' . $item['id'];

                if ((int) $item['has_pdf'] === 1) {

                    /** @var Bootstrap\Badge $el */
                    $el = $this->di->get('Badge');

                    $el->context('warning');
                    $el->addClass('mr-2 border border-warning');
                    $el->html('PDF');
                    $pdf_badge = $el->render();

                    $el = null;

                    $pdf_url = IL_BASE_URL . 'index.php/pdf?id=' . $item['id'];
                    $pdf_button = "<a href=\"{$pdf_url}\">{$pdf_badge}</a>";

                } else {

                    /** @var Bootstrap\Badge $el */
                    $el = $this->di->get('Badge');

                    $el->context('default');
                    $el->addClass('mr-2 border border-secondary text-secondary');
                    $el->html('PDF');
                    $pdf_button = $el->render();

                    $el = null;
                }

                $last5_html .=
                    <<<HTML
        <tr>
            <td class="text-truncate">
                $pdf_button
                <a href="{$item_url}">{$item['title']}</a>
            </td>
        </tr>
HTML;
            }

            $last5_html .=
                <<<HTML
    </tbody>
</table>
HTML;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('h-100');
            $el->header(
                <<<HTML
<span>
    <b class="text-uppercase">{$this->lang->t9n('Items')}</b>
    <a class="mx-3" href="#items/main">{$this->lang->t9n('List-NOUN')}</a>
    <a href="#items/filter">{$this->lang->t9n('Filter-NOUN')}</a>
</span>
HTML
                , 'px-4 pt-3 pb-3');
            $el->body($last5_html, null, 'px-4 pt-1 pb-4');
            $cards[] = $el->render();

            $el = null;
        }

        /*
         * Last projects.
         */

        if ($this->app_settings->getUser('dashboard_remove_projects') === '0') {

            $project_html =
                <<<HTML
<table style="table-layout: fixed;width:100%;line-height: 2rem">
    <tbody>
HTML;

            if (count($data['last_projects']) === 0) {

                $project_html .=
                    <<<HTML
        <tr>
            <td style="height: 10rem" class="align-middle text-center text-uppercase text-secondary pb-4">
                {$this->lang->t9n('No projects')}
            </td>
        </tr>
HTML;
            }

            foreach ($data['last_projects'] as $project) {

                $item_url = IL_BASE_URL . 'index.php/project#project/browse?id=' . $project['id'];

                $project_html .=
                    <<<HTML
        <tr>
            <td class="text-truncate">
                <a href="{$item_url}">{$project['project']}</a>
            </td>
        </tr>
HTML;
            }

            $project_html .=
                <<<HTML
    </tbody>
</table>
HTML;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('h-100');
            $el->header(
                <<<HTML
<span>
    <b class="text-uppercase">{$this->lang->t9n('Projects')}</b>
    <a class="mx-3" href="#projects/main">{$this->lang->t9n('List-NOUN')}</a>
</span>
HTML
                , 'px-4 pt-3');
            $el->body($project_html, null, 'px-4 pb-4');
            $cards[] = $el->render();

            $el = null;
        }

        /*
         * Last item notes.
         */

        if ($this->app_settings->getUser('dashboard_remove_item_notes') === '0') {

            $notes_html =
<<<HTML
<table style="table-layout: fixed;width:100%;line-height: 2rem">
    <tbody>
HTML;

            if (count($data['last_notes']) === 0) {

                $notes_html .=
<<<HTML
        <tr>
            <td style="height: 10rem" class="text-center text-secondary text-uppercase align-middle pb-4">
                {$this->lang->t9n('No notes')}
            </td>
        </tr>
HTML;
            }

            foreach ($data['last_notes'] as $item) {

                $item_url = IL_BASE_URL . 'index.php/item#notes?id=' . $item['id'];

                $note = $this->sanitation->lmth($item['note']);

                $notes_html .=
<<<HTML
        <tr>
            <td class="text-truncate" style="direction: rtl">
                <a href="{$item_url}">{$note}&lrm;</a>
            </td>
        </tr>
HTML;
            }

            $notes_html .=
<<<HTML
    </tbody>
</table>
HTML;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('h-100');
            $el->header(
<<<HTML
<b class="text-uppercase">{$this->lang->t9n('Item notes')}</b>
HTML
                , 'px-4 pt-3');
            $el->body($notes_html, null, 'px-4 pb-4');
            $cards[] = $el->render();

            $el = null;
        }

        /*
         * Project notes.
         */

        if ($this->app_settings->getUser('dashboard_remove_project_notes') === '0') {

            $project_notes =
                <<<HTML
<table style="table-layout: fixed;width:100%;line-height: 2rem">
    <tbody>
HTML;

            if (count($data['last_project_notes']) === 0) {

                $project_notes .=
                    <<<HTML
        <tr>
            <td style="height: 10rem" class="text-center text-secondary text-uppercase align-middle pb-4">
                {$this->lang->t9n('No notes')}
            </td>
        </tr>
HTML;
            }

            foreach ($data['last_project_notes'] as $project) {

                $item_url = IL_BASE_URL . 'index.php/project#project/notes?id=' . $project['id'];

                $project_notes .=
                    <<<HTML
        <tr>
            <td class="text-truncate">
                <a href="{$item_url}">{$project['note']}</a>
            </td>
        </tr>
HTML;
            }

            $project_notes .=
                <<<HTML
    </tbody>
</table>
HTML;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('h-100');
            $el->header(
                <<<HTML
<b class="text-uppercase">{$this->lang->t9n('Project notes')}</b>
HTML
                , 'px-4 pt-3');
            $el->body($project_notes, null, 'px-4 pb-4');
            $cards[] = $el->render();

            $el = null;
        }

        /*
         * Last items discussed.
         */

        if ($this->app_settings->getUser('dashboard_remove_item_discussions') === '0') {

            $discussed_html =
<<<HTML
<table style="table-layout: fixed;width:100%;line-height: 2rem">
    <tbody>
HTML;

            if (count($data['last_discussed']) === 0) {

                $discussed_html .=
<<<HTML
        <tr>
            <td style="height: 10rem" class="text-center text-secondary text-uppercase pb-4 align-middle">
                {$this->lang->t9n('No posts')}
            </td>
        </tr>
HTML;
            }

            foreach ($data['last_discussed'] as $item) {

                $item_url = IL_BASE_URL . 'index.php/item#itemdiscussion?id=' . $item['id'];

                $discussed_html .=
<<<HTML
        <tr>
            <td class="text-truncate">
                <a href="{$item_url}">{$item['message']}</a>
            </td>
        </tr>
HTML;
            }

            $discussed_html .=
<<<HTML
    </tbody>
</table>
HTML;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('h-100');
            $el->header(
<<<HTML
<b class="text-uppercase">{$this->lang->t9n('Item discussions')}</b>
HTML
                , 'px-4 pt-3');
            $el->body($discussed_html, null, 'px-4 pb-4');
            $cards[] = $el->render();

            $el = null;
        }

        /*
         * Last projects discussed.
         */

        if ($this->app_settings->getUser('dashboard_remove_project_discussions') === '0') {

            $discussed_html =
                <<<HTML
<table style="table-layout: fixed;width:100%;line-height: 2rem">
    <tbody>
HTML;

            if (count($data['last_discussed_projects']) === 0) {

                $discussed_html .=
                    <<<HTML
        <tr>
            <td style="height: 10rem" class="text-center text-secondary text-uppercase pb-4 align-middle">
                {$this->lang->t9n('No posts')}
            </td>
        </tr>
HTML;
            }

            foreach ($data['last_discussed_projects'] as $project) {

                $item_url = IL_BASE_URL . 'index.php/project#project/discussion?id=' . $project['project_id'];

                $discussed_html .=
                    <<<HTML
        <tr>
            <td class="text-truncate">
                <a href="{$item_url}">{$project['message']}</a>
            </td>
        </tr>
HTML;
            }

            $discussed_html .=
                <<<HTML
    </tbody>
</table>
HTML;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('h-100');
            $el->header(
                <<<HTML
<b class="text-uppercase">{$this->lang->t9n('Project discussions')}</b>
HTML
                , 'px-4 pt-3');
            $el->body($discussed_html, null, 'px-4 pb-4');
            $cards[] = $el->render();

            $el = null;
        }

        /*
         * Active sessions.
         */

        /** @var Temporal $temporal */
        $temporal = $this->di->get('Temporal');

        $active_sessions = $this->session->readSessionFiles($data['sessions']);

        $sessions_card =
<<<HTML
<div class="pl-4 text-uppercase">{$this->lang->t9n('Logged devices')}</div>
HTML;

        foreach ($active_sessions as $active_session) {

            $created = $temporal->toUserTime($active_session['created']);
            $updated = $temporal->toUserTime($active_session['last_accessed']);
            $user_agent = $this->sanitation->html($active_session['user_agent']);

            $sessions_card .=
<<<HTML
<table class="ml-4 mb-2 text-muted">
    <tbody>
        <tr>
            <td class="align-top pr-3 text-primary">
                <small><b class="text-uppercase">{$this->lang->t9n('Client IP')}</b></small>
            </td>
            <td>
                {$active_session['remote_ip']}
            </td>
        </tr>
        <tr>
            <td class="align-top pr-3">
                <small><b class="text-uppercase">{$this->lang->t9n('Software')}</b></small>
            </td>
            <td>
                <small>{$user_agent}</small>
            </td>
        </tr>
        <tr>
            <td class="align-top pr-3">
                <small><b class="text-uppercase">{$this->lang->t9n('Started')}</b></small>
            </td>
            <td>
                $created
            </td>
        </tr>
        <tr>
            <td class="align-top pr-3">
                <small><b class="text-uppercase">{$this->lang->t9n('Last access')}</b></small>
            </td>
            <td>
                $updated
            </td>
        </tr>
    </tbody>
</table>
HTML;
        }

        /*
         * Row.
         */

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->addClass('d-flex align-content-start no-gutters');
        $el->column($bc, 'col-12');
        $el->column($cards[0] ?? '', 'col-xl-6 mb-3 pr-xl-2');
        $el->column($cards[1] ?? '', 'col-xl-6 mb-3 pl-xl-2');
        $el->column($cards[2] ?? '', 'col-xl-6 mb-3 pr-xl-2');
        $el->column($cards[3] ?? '', 'col-xl-6 mb-3 pl-xl-2');
        $el->column($cards[4] ?? '', 'col-xl-6 mb-3 pr-xl-2');
        $el->column($cards[5] ?? '', 'col-xl-6 mb-3 pl-xl-2');
        $el->column($cards[6] ?? '', 'col-xl-6 mb-3 pr-xl-2');
        $el->column($cards[7] ?? '', 'col-xl-6 mb-3 pl-xl-2');
        $el->column($sessions_card, 'col-12 mb-3');

        $content = $el->render();

        /*
         * Advanced search modal.
         */

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->addClass('search-submit');
        $el->context('primary');
        $el->html($this->lang->t9n('Search-VERB'));
        $search_button = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-advanced-search');
        $el->header($this->lang->t9n('Search library'));
        $el->button($search_button);
        $el->body($this->sharedAdvancedSearch($data['tags']), 'bg-darker-5');
        $el->componentSize('large');
        $content .= $el->render();

        $el = null;

        $this->append([
            'html' => $content
        ]);

        return $this->send();
    }
}
