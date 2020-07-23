<?php

namespace LibrarianApp;

use Exception;
use \Librarian\Html\Bootstrap;
use Librarian\Media\Temporal;
use Librarian\Media\TesseractOcr;
use Librarian\Mvc\TextView;

class PdfView extends TextView {

    use SharedHtmlView;

    /**
     * @var Temporal
     */
    private $temporal_obj;

    public function main($item_id, $item) {}

    /**
     * External PDF viewer - browser plugin in an iframe.
     *
     * @param int $item_id
     * @param array $item
     * @return string
     * @throws Exception
     */
    public function external(int $item_id, array $item): string {

        $this->title("PDF - {$item['title']}");

        $IL_BASE_URL = IL_BASE_URL;

        if ($this->contentType() === 'html') {

            // HTML response.

            $this->styleLink('css/plugins.css');
            $this->head();

            if (array_key_exists('page_count', $item['info']) === false) {

                // No PDF.

                /** @var Bootstrap\Alert $el */
                $el = $this->di->get('Alert');

                $el->style('margin: 5rem 25%');
                $el->context('primary');
                $el->html('There is no PDF.');
                $alert = $el->render();

                $el = null;

                $iframe = '<iframe style="display: none"></iframe>';

                $this->append($alert . $iframe);

            } else {

                $iframe = <<<EOT
                <iframe
                    style="display: block;width: 100%;height: 100%; border: 0"
                    src="{$IL_BASE_URL}index.php/pdf/file?id={$item_id}#zoom=page-width&pagemode=none">
                </iframe>
EOT;

                $this->append($iframe);
            }

            $this->scriptLink('js/plugins.js');
            $this->end();

        } elseif ($this->contentType() === 'json') {

            // JSON response.

            $this->head();

            if (array_key_exists('page_count', $item['info']) === false) {

                /** @var Bootstrap\Alert $el */
                $el = $this->di->get('Alert');

                $el->style('margin: 5rem 25%');
                $el->context('primary');
                $el->html('There is no PDF.');
                $alert = $el->render();

                $el = null;

                $iframe = '<iframe style="display: none"></iframe>';

                // No PDF.
                $this->append(['html' => $alert . $iframe]);

            } else {

                // JSON response.
                $iframe = <<<EOT
                <iframe
                    style="display: block;margin: 0 -15px;width: calc(100% + 30px);height: 100%; border: 0"
                    src="{$IL_BASE_URL}index.php/pdf/file?id={$item_id}#zoom=page-width&pagemode=none">
                </iframe>
EOT;

                $this->append(['html' => $iframe]);
            }
        }

        return $this->send();
    }

    /**
     * Manage the PDF file.
     *
     * @param int $item_id
     * @param array $item
     * @return string
     * @throws Exception
     */
    public function manage(int $item_id, array $item): string {

        $this->temporal_obj = $this->di->getShared('Temporal');

        $this->title("PDF - {$item['title']} - Library");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item("{$item['title']}", '#summary?id=' . $item_id);
        $el->item('Manage PDF');
        $bc = $el->render();

        $el = null;

        // Upload form.
        $file_input = $this->sharedFileInput();

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('id');
        $el->value($item_id);
        $id_input = $el->render();

        $el = null;

        // We put CSRF key here, because the JS file upload plugin has its own AJAX methods.

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('csrfToken');
        $el->value($this->session->data('token'));
        $csrf_input = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html('Upload');
        $upload_button = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('upload-form');
        $el->method('POST');
        $el->action(IL_BASE_URL . 'index.php/pdf/save');
        $el->append("$file_input $id_input $csrf_input $upload_button");
        $form = $el->render();

        $el = null;

        $add_replace = !empty($item['info']['name']) ? 'REPLACE' : 'ADD';

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>{$add_replace} PDF</b>");
        $el->body($form);
        $form_card = $el->render();

        $el = null;

        if (!empty($item['info']['name'])) {

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->context('danger');
            $el->id('delete-pdf');
            $el->style('width: 5.5rem');
            $el->name('delete');
            $el->html('Delete');
            $el->dataBody('Do you want to delete this PDF?');
            $el->dataButton('Delete');
            $del_button = $el->render();

            $el = null;

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->context('danger');
            $el->id('reindex-pdf');
            $el->style('width: 5.5rem');
            $el->name('reindex');
            $el->html('Extract');
            $el->dataBody('Do you want to re-extract text from this PDF? This action will erase the existing index, including all OCRed data.');
            $el->dataButton('Extract');
            $rei_button = $el->render();

            $el = null;

            // OCR form.

            /** @var Bootstrap\Select $el Languages. */
            $el = $this->di->get('Select');

            $el->id('language');
            $el->name('language');
            $el->label('Language');
            $el->option('Mix of English and Greek &mdash; eng+ell', 'eng+ell', true);

            /** @var TesseractOcr $ocr */
            $ocr = $this->di->get('TesseractOcr');
            $languages = $ocr->getInstalledLanguages();

            foreach ($languages as $code => $language) {

                $el->option("{$language} &mdash; {$code}", $code);
            }

            $languages = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('custom-language');
            $el->name('custom_language');
            $el->label('Custom language code');
            $custom_language = $el->render();

            $el = null;

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->type('hidden');
            $el->name('id');
            $el->value($item_id);
            $hidden_id = $el->render();

            $el = null;

            /** @var Bootstrap\Form $el */
            $el = $this->di->get('Form');

            $el->id('ocr-form');
            $el->action(IL_BASE_URL . 'index.php/pdf/ocr');
            $el->html("{$languages} {$custom_language} {$hidden_id}");
            $form = $el->render();

            $el = null;

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->context('danger');
            $el->id('ocr-pdf');
            $el->addClass('ml-1');
            $el->style('width: 5.5rem');
            $el->name('ocr');
            $el->html('OCR');
            $el->dataTitle('Optical character recognition');
            $el->dataBody($this->sanitation->attr($form));
            $el->dataButton('OCR');
            $ocr_button = $el->render();

            $el = null;

            $text = empty(trim($item['info']['text'])) ? '<div class="text-secondary">NO TEXT</div>' : $item['info']['text'];

            /** @var Bootstrap\ListGroup $el */
            $el = $this->di->get('ListGroup');

            $el->addClass('mb-3');
            $el->div("<b>{$item['info']['name']}</b> $del_button", 'd-flex justify-content-between align-items-center');
            $el->div("<b>Indexed text</b> <span>$rei_button $ocr_button</span>", 'd-flex justify-content-between align-items-center');
            $el->div($text);
            $card = $el->render();

            $el = null;

        } else {

            /** @var Bootstrap\ListGroup $el */
            $el = $this->di->get('ListGroup');

            $el->addClass('mb-3');
            $el->div('<div class="text-center text-secondary">NO PDF</div>');
            $card = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($bc, 'col-12');
        $el->column($form_card, 'col-md-4 mb-3');
        $el->column($card, 'col-md-8 mb-3');
        $row = $el->render();

        $el = null;

        $this->append(['html' => $row]);

        return $this->send();
    }
}
