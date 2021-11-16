<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class DuplicatesView extends TextView {

    /**
     * @return string
     * @throws Exception
     */
    public function main(): string {

        $this->title($this->lang->t9n('Duplicates'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Duplicates'), '#duplicates');
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->context('primary');
        $el->href('#duplicates/find?mode=similar');
        $el->html($this->lang->t9n('Find duplicates'));
        $link1 = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->context('primary');
        $el->href('#duplicates/find?mode=identical');
        $el->html($this->lang->t9n('Find duplicates'));
        $link2 = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->context('primary');
        $el->href('#duplicates/find?mode=files');
        $el->html($this->lang->t9n('Find duplicates'));
        $link3 = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->id('duplicate-mode1');
        $el->addClass('duplicate-mode mb-3 text-center');
        $el->header(
<<<HTML
<div class="w-100 text-center text-uppercase"><b>{$this->lang->t9n('similar titles')}</b></div>
HTML
        );
        $el->body(
<<<HTML
<p>
    <span class="text-success">{$this->lang->t9n('high detection rate')}</span><br>
    <span class="text-secondary">{$this->lang->t9n('high false positives')}</span>
</p>
$link1
HTML
        );
        $el->footer(
<<<HTML
<div class="py-2 text-monospace text-center">
    Lorem Ipsum Dolor?<br>
    Lorem ipsum dolor!
</div>
HTML
);
        $card1 = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->id('duplicate-mode2');
        $el->addClass('duplicate-mode mb-3 text-center');
        $el->header(
<<<HTML
<div class="w-100 text-center text-uppercase"><b>{$this->lang->t9n('identical titles')}</b></div>
HTML
        );
        $el->body(
<<<HTML
<p>
    <span class="text-secondary">{$this->lang->t9n('low detection rate')}</span><br>
    <span class="text-success">{$this->lang->t9n('low false positives')}</span>
</p>
$link2
HTML
        );
        $el->footer(
<<<HTML
<div class="py-2 text-monospace text-center">
    Lorem Ipsum Dolor<br>
    Lorem ipsum dolor
</div>
HTML
        );
        $card2 = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('file-pdf-box');
        $pdf_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->id('duplicate-mode3');
        $el->addClass('duplicate-mode mb-3 text-center');
        $el->header(
<<<HTML
<div class="w-100 text-center text-uppercase"><b>{$this->lang->t9n('identical PDFs')}</b></div>
HTML
        );
        $el->body(
<<<HTML
<p>
    <span class="text-success">{$this->lang->t9n('high detection rate')}</span><br>
    <span class="text-success">{$this->lang->t9n('no false positives')}</span>
</p>
$link3
HTML
        );
        $el->footer(
<<<HTML
<div class="py-2 text-monospace text-center">
    {$pdf_icon} f16a15f5dad16df5<br>
    {$pdf_icon} f16a15f5dad16df5
</div>
HTML
        );
        $card3 = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($card1, 'col-lg');
        $el->column($card2, 'col-lg');
        $el->column($card3, 'col-lg');
        $row = $el->render();

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }

    /**
     * @param array $duplicates
     * @return string
     * @throws Exception
     */
    public function similar(array $duplicates): string {

        $this->title("{$this->lang->t9n('Similar titles')} - {$this->lang->t9n('Duplicates')}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Duplicates'), '#duplicates/main');
        $el->item($this->lang->t9n('Similar titles'));
        $bc = $el->render();

        $el = null;

        // No duplicates.
        if (count($duplicates) === 0) {

            $this->append(['html' => "$bc"]);

            return $this->send();
        }

        $cards = '';
        $IL_BASE_URL = IL_BASE_URL;

        foreach ($duplicates as $items) {

            $body = '';
            $hidden = '';
            $warning = '';

            $pdf_count = count(array_unique(array_column($items, 'file_hash')));

            if ($pdf_count > 1) {

                /** @var Bootstrap\Alert $el */
                $el = $this->di->get('Alert');

                $el->context('danger');
                $el->html(
<<<HTML
{$this->lang->t9n('These items do not have identical PDFs')}.
{$this->lang->t9n('To prevent data loss, verify whether the PDFs can be merged safely')}.
HTML
                );
                $warning = $el->render();

                $el = null;
            }

            foreach ($items as $key => $item) {

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Badge');

                $el->context('secondary');
                $el->addClass('mr-2');
                $el->style('width: 5rem');
                $el->html($item['id']);
                $badge = $el->render();

                $el = null;

                $title = "<h5>$badge <a href=\"{$IL_BASE_URL}index.php/item/#summary?id={$item['id']}\" target=\"_blank\">{$item['title']}</a></h5>";

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->type('radio');
                $el->id("id-to-keep-{$item['id']}");
                $el->name('id_to_keep');
                $el->style('width: 5rem');
                $el->label($title);
                $el->value($item['id']);
                $el->checked(($key === 0 ? 'checked' : null));
                $body .= $el->render();

                $el = null;

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->type('hidden');
                $el->name('ids[]');
                $el->value($item['id']);
                $hidden .= $el->render();

                $el = null;
            }

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->type('submit');
            $el->context('danger');
            $el->html($this->lang->t9n('Merge'));
            $merge = $el->render();

            $el = null;

            /** @var Bootstrap\Form $el */
            $el = $this->di->get('Form');

            $el->addClass('merge-form');
            $el->action($IL_BASE_URL . 'index.php/duplicates/merge');
            $el->append($body);
            $el->append($hidden);
            $el->append($merge);
            $form = $el->render();

            $el = null;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('mb-3');
            $el->header("<b>{$this->lang->t9n('Select an item to keep')}</b>", 'text-uppercase');
            $el->body($warning . $form);
            $cards .= $el->render();

            $el = null;
        }

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->style('margin-bottom: 30vh');
        $el->column($cards);
        $row = $el->render();

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }

    /**
     * @param array $duplicates
     * @return string
     * @throws Exception
     */
    public function identical(array $duplicates): string {

        $this->title("{$this->lang->t9n('Identical titles')} - {$this->lang->t9n('Duplicates')}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Duplicates'), '#duplicates/main');
        $el->item($this->lang->t9n('Identical titles'));
        $bc = $el->render();

        $el = null;

        // No duplicates.
        if (count($duplicates) === 0) {

            $this->append(['html' => "$bc"]);

            return $this->send();
        }

        $cards = '';
        $IL_BASE_URL = IL_BASE_URL;

        foreach ($duplicates as $title => $items) {

            $body = '';
            $hidden = '';
            $warning = '';

            $pdf_count = count(array_unique(array_column($items, 'file_hash')));

            if ($pdf_count > 1) {

                /** @var Bootstrap\Alert $el */
                $el = $this->di->get('Alert');

                $el->context('danger');
                $el->html(
                    <<<HTML
{$this->lang->t9n('These items do not have identical PDFs')}.
{$this->lang->t9n('To prevent data loss, verify whether the PDFs can be merged safely')}.
HTML
                );
                $warning = $el->render();

                $el = null;
            }

            foreach ($items as $key => $ids) {

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Badge');

                $el->context('secondary');
                $el->addClass('mr-2');
                $el->style('width: 5rem');
                $el->html($ids['id']);
                $badge = $el->render();

                $el = null;

                $line = "<h5>$badge <a href=\"{$IL_BASE_URL}index.php/item/#summary?id={$ids['id']}\" target=\"_blank\">{$title}</a></h5>";

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->type('radio');
                $el->id("id-to-keep-{$ids['id']}");
                $el->name('id_to_keep');
                $el->style('width: 5rem');
                $el->label($line);
                $el->value($ids['id']);
                $el->checked(($key === 0 ? 'checked' : null));
                $body .= $el->render();

                $el = null;

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->type('hidden');
                $el->name('ids[]');
                $el->value($ids['id']);
                $hidden .= $el->render();

                $el = null;
            }

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->type('submit');
            $el->context('danger');
            $el->html($this->lang->t9n('Merge'));
            $merge = $el->render();

            $el = null;

            /** @var Bootstrap\Form $el */
            $el = $this->di->get('Form');

            $el->addClass('merge-form');
            $el->action($IL_BASE_URL . 'index.php/duplicates/merge');
            $el->append($body);
            $el->append($hidden);
            $el->append($merge);
            $form = $el->render();

            $el = null;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('mb-3');
            $el->header("<b>{$this->lang->t9n('Select an item to keep')}</b>", 'text-uppercase');
            $el->body($warning . $form);
            $cards .= $el->render();

            $el = null;
        }

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->style('margin-bottom: 30vh');
        $el->column($cards);
        $row = $el->render();

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }

    /**
     * @param array $duplicates
     * @return string
     * @throws Exception
     */
    public function pdfs(array $duplicates): string {

        $this->title("{$this->lang->t9n('Identical PDFs')} - {$this->lang->t9n('Duplicates')}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Duplicates'), '#duplicates/main');
        $el->item($this->lang->t9n('Identical PDFs'));
        $bc = $el->render();

        $el = null;

        // No duplicates.
        if (count($duplicates) === 0) {

            $this->append(['html' => "$bc"]);

            return $this->send();
        }

        /** @var Bootstrap\Alert $el */
        $el = $this->di->get('Alert');

        $el->context('danger');
        $el->html(
            <<<HTML
{$this->lang->t9n('Duplicate PDFs can be assigned to unrelated items by mistake')}.
{$this->lang->t9n('Do not merge such items, instead, upload the correct PDFs')}.
HTML
        );
        $warning = $el->render();

        $el = null;

        $cards = '';
        $IL_BASE_URL = IL_BASE_URL;

        foreach ($duplicates as $items) {

            $body = '';
            $hidden = '';

            foreach ($items as $key => $item) {

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Badge');

                $el->context('secondary');
                $el->addClass('mr-2');
                $el->style('width: 5rem');
                $el->html($item['id']);
                $badge = $el->render();

                $el = null;

                $line = "<h5>$badge <a href='{$IL_BASE_URL}index.php/item/#pdf/main?id={$item['id']}'>{$item['title']}</a></h5>";

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->type('radio');
                $el->id("id-to-keep-{$item['id']}");
                $el->name('id_to_keep');
                $el->style('width: 5rem');
                $el->label($line);
                $el->value($item['id']);
                $el->checked(($key === 0 ? 'checked' : null));
                $body .= $el->render();

                $el = null;

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->type('hidden');
                $el->name('ids[]');
                $el->value($item['id']);
                $hidden .= $el->render();

                $el = null;
            }

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->type('submit');
            $el->context('danger');
            $el->html("{$this->lang->t9n('Merge')}");
            $merge = $el->render();

            $el = null;

            /** @var Bootstrap\Form $el */
            $el = $this->di->get('Form');

            $el->addClass('merge-form');
            $el->action($IL_BASE_URL . 'index.php/duplicates/merge');
            $el->append($body);
            $el->append($hidden);
            $el->append($merge);
            $form = $el->render();

            $el = null;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('mb-3');
            $el->header("<b>{$this->lang->t9n('Select an item to keep')}</b>", 'text-uppercase');
            $el->body($form);
            $cards .= $el->render();

            $el = null;
        }

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->style('margin-bottom: 30vh');
        $el->column($cards);
        $row = $el->render();

        $this->append(['html' => "$bc $warning $row"]);

        return $this->send();
    }
}
