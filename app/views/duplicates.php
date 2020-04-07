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

        $this->title('Duplicates');

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item('Duplicates', '#duplicates');
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->context('primary');
        $el->href('#duplicates/find?mode=similar');
        $el->html('Find duplicates');
        $link1 = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->context('primary');
        $el->href('#duplicates/find?mode=identical');
        $el->html('Find duplicates');
        $link2 = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->elementName('a');
        $el->context('primary');
        $el->href('#duplicates/find?mode=files');
        $el->html('Find duplicates');
        $link3 = $el->render();

        $el = null;

        $animation1 = <<<EOT
            <div class="py-2" style="height: 4rem;font-family: monospace">
                <div class="div1" style="position: relative;height: 1.75rem"><span class="align-middle">Lorem Ipsum Dolor?</span></div>
                <div class="div2" style="position: relative;height: 1.75rem"><span class="align-middle">Lorem ipsum dolor!</span></div>
            </div>
EOT;

        $animation2 = <<<EOT
            <div class="py-2" style="height: 4rem;font-family: monospace">
                <div class="div1" style="position: relative;height: 1.75rem"><span class="align-middle">Lorem ipsum dolor</span></div>
                <div class="div2" style="position: relative;height: 1.75rem"><span class="align-middle">Lorem ipsum dolor</span></div>
            </div>
EOT;

        $animation3 = <<<EOT
            <div class="py-2" style="height: 4rem;font-family: monospace">
                <div class="div1" style="position: relative;height: 1.75rem"><span class="mdi mdi-file-pdf-box align-middle"> f16a15f5dad16df5</span></div>
                <div class="div2" style="position: relative;height: 1.75rem"><span class="mdi mdi-file-pdf-box align-middle"> f16a15f5dad16df5</span></div>
            </div>
EOT;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->id('duplicate-mode1');
        $el->addClass('duplicate-mode mb-3 text-center');
        $el->header('<div class="w-100 text-center"><b>SIMILAR TITLES</b></div>');
        $el->body("<p><span class=\"text-secondary\">slow</span><br><span class=\"text-success\">high detection rate</span><br><span class=\"text-secondary\">high false positives</span></p>$link1");
        $el->footer($animation1);
        $card1 = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->id('duplicate-mode2');
        $el->addClass('duplicate-mode mb-3 text-center');
        $el->header('<div class="w-100 text-center"><b>IDENTICAL TITLES</b></div>');
        $el->body("<p><span class=\"text-success\">fast</span><br><span class=\"text-secondary\">low detection rate</span><br><span class=\"text-success\">low false positives</span></p>$link2");
        $el->footer($animation2);
        $card2 = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->id('duplicate-mode3');
        $el->addClass('duplicate-mode mb-3 text-center');
        $el->header('<div class="w-100 text-center"><b>IDENTICAL PDFS</b></div>');
        $el->body("<p><span class=\"text-success\">fast</span><br><span class=\"text-success\">high detection rate</span><br><span class=\"text-success\">no false positives</span></p>$link3");
        $el->footer($animation3);
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

        $this->title('Similar titles - Duplicates');

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item('Duplicates', '#duplicates/main');
        $el->item('Similar titles');
        $bc = $el->render();

        $el = null;

        $cards = '';
        $IL_BASE_URL = IL_BASE_URL;

        foreach ($duplicates as $items) {

            $body = '';
            $hidden = '';
            $warning = '';

            $pdf_count = count(array_unique(array_column($items, 'file_hash')));

            if ($pdf_count > 1) {

                /** @var Bootstrap\Icon $el */
                $el = $this->di->get('Icon');

                $el->icon('alert');
                $alert = $el->render();

                $el = null;

                $warning = <<<WARNING
<span class="text-danger ml-2">$alert Warning! These items do not appear to have identical PDFs. To prevent data loss,
they should not be merged here. Verify that the PDF are identical before merging.</span>
WARNING;
            }

            foreach ($items as $item) {

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Badge');

                $el->context('secondary');
                $el->addClass('mr-2');
                $el->style('width: 5rem');
                $el->html($item['id']);
                $badge = $el->render();

                $el = null;

                $body .= "<h5>$badge <a href='{$IL_BASE_URL}index.php/item/#summary?id={$item['id']}'>{$item['title']}</a></h5>";

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
            $el->html('&#8593;Merge');
            $merge = $el->render();

            $el = null;

            /** @var Bootstrap\Form $el */
            $el = $this->di->get('Form');

            $el->addClass('merge-form');
            $el->action($IL_BASE_URL . 'index.php/duplicates/merge');
            $el->append($hidden);
            $el->append($merge);
            $el->append($warning);
            $form = $el->render();

            $el = null;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('mb-3');
            $el->body($body, null, 'pt-3 pb-0');
            $el->footer($form);
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

        $this->title('Identical titles - Duplicates');

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item('Duplicates', '#duplicates/main');
        $el->item('Identical titles');
        $bc = $el->render();

        $el = null;

        $cards = '';
        $IL_BASE_URL = IL_BASE_URL;

        foreach ($duplicates as $title => $items) {

            $body = '';
            $hidden = '';
            $warning = '';

            $pdf_count = count(array_unique(array_column($items, 'file_hash')));

            if ($pdf_count > 1) {

                /** @var Bootstrap\Icon $el */
                $el = $this->di->get('Icon');

                $el->icon('alert');
                $alert = $el->render();

                $el = null;

                $warning = <<<WARNING
<span class="text-danger ml-2">$alert Warning! These items do not appear to have identical PDFs. To prevent data loss,
they should not be merged here. Verify that the PDF are identical before merging.</span>
WARNING;
            }

            foreach ($items as $ids) {

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Badge');

                $el->context('secondary');
                $el->addClass('mr-2');
                $el->style('width: 5rem');
                $el->html($ids['id']);
                $badge = $el->render();

                $el = null;

                $body .= "<h5>$badge <a href='{$IL_BASE_URL}index.php/item/#summary?id={$ids['id']}'>{$title}</a></h5>";

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
            $el->html('&#8593;Merge');
            $merge = $el->render();

            $el = null;

            /** @var Bootstrap\Form $el */
            $el = $this->di->get('Form');

            $el->addClass('merge-form');
            $el->action($IL_BASE_URL . 'index.php/duplicates/merge');
            $el->append($hidden);
            $el->append($merge);
            $el->append($warning);
            $form = $el->render();

            $el = null;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('mb-3');
            $el->body($body, null, 'pt-3 pb-0');
            $el->footer($form);
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

        $this->title('Identical PDFs - Duplicates');

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item('Duplicates', '#duplicates/main');
        $el->item('Duplicate PDFs');
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('alert');
        $alert = $el->render();

        $el = null;

        $warning = <<<WARNING
<p class="text-danger">$alert Be warned that duplicate PDFs can be assigned to unrelated items by mistake. Such items should not be merged here.
A correct PDF should be uploaded instead.</p>
WARNING;

        $cards = '';
        $IL_BASE_URL = IL_BASE_URL;

        foreach ($duplicates as $items) {

            $body = '';
            $hidden = '';

            foreach ($items as $item) {

                /** @var Bootstrap\Badge $el */
                $el = $this->di->get('Badge');

                $el->context('secondary');
                $el->addClass('mr-2');
                $el->style('width: 5rem');
                $el->html($item['id']);
                $badge = $el->render();

                $el = null;

                $body .= "<h5>$badge <a href='{$IL_BASE_URL}index.php/item/#pdf/main?id={$item['id']}'>{$item['title']}</a></h5>";

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
            $el->html('&#8593;Merge');
            $merge = $el->render();

            $el = null;

            /** @var Bootstrap\Form $el */
            $el = $this->di->get('Form');

            $el->addClass('merge-form');
            $el->action($IL_BASE_URL . 'index.php/duplicates/merge');
            $el->append($hidden);
            $el->append($merge);
            $form = $el->render();

            $el = null;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('mb-3');
            $el->body($body, null, 'pt-3 pb-0');
            $el->footer($form);
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
