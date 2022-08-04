<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class SupplementsView extends TextView {

    use SharedHtmlView;

    /**
     * Main.
     *
     * @param int $item_id
     * @param array $files
     * @return string
     * @throws Exception
     */
    public function main(int $item_id, array $files): string {

        $this->title("{$this->lang->t9n('Supplements')} - {$files['title']}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item("{$files['title']}", '#summary?id=' . $item_id);
        $el->item($this->lang->t9n('Supplements'));
        $bc = $el->render();

        $el = null;

        // Upload form.
        $file_input = $this->sharedFileInput(true);

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->id('id');
        $el->name('id');
        $el->value($item_id);
        $id_input = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('graphical-abstract');
        $el->type('checkbox');
        $el->name('graphical_abstract');
        $el->value('1');
        $el->label($this->lang->t9n('graphical abstract'));
        $graphical_abstract = $el->render();

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

        $el->addClass('mt-3');
        $el->type('submit');
        $el->context('danger');
        $el->html($this->lang->t9n('Save'));
        $upload_button = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->id('upload-card');
        $el->header("<b>{$this->lang->t9n('Upload files')}</b>", 'px-4 pt-3 text-uppercase');
        $el->body("$file_input $graphical_abstract $id_input $csrf_input $upload_button", null, 'px-4 pb-4');
        $upload_card = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('upload-form');
        $el->method('POST');
        $el->action(IL_BASE_URL . 'index.php/supplements/save');
        $el->append($upload_card);
        $upload_card = $el->render();

        $el = null;

        // File table.
        if (count($files['files']) === 0) {

            /** @var Bootstrap\ListGroup $el */
            $el = $this->di->get('ListGroup');

            $el->div($this->lang->t9n('No files'), 'text-muted text-center text-uppercase p-4');
            $table = $el->render();

            $el = null;

        } else {

            $table = '';
            $images = [];
            $audios = [];
            $videos = [];
            $pdfs = [];
            $misc = [];

            foreach ($files['files'] as $file) {

                // Determine the file type.
                switch ($file['mime']) {

                    case 'image/gif':
                    case 'image/jpeg':
                    case 'image/png':
                        $images[] = $file['name'];
                        break;

                    case 'video/webm':
                    case 'video/ogg':
                    case 'video/mp4':
                        $videos[] = $file['name'];
                        break;

                    case 'audio/mpeg':
                    case 'audio/ogg':
                    case 'audio/mp4':
                    case 'audio/webm':
                    case 'audio/wav':
                        $audios[] = $file['name'];
                        break;

                    case 'application/pdf':
                        $pdfs[] = $file['name'];
                        break;

                    default:
                        $misc[] = $file['name'];
                        break;
                }
            }
        }

        if (!empty($images)) {

            $table .= $this->fileList($item_id, $images, $this->lang->t9n('images'));
        }

        if (!empty($pdfs)) {

            $table .= $this->fileList($item_id, $pdfs, 'PDFs');
        }

        if (!empty($videos)) {

            $table .= $this->fileList($item_id, $videos, $this->lang->t9n('videos'));
        }

        if (!empty($audios)) {

            $table .= $this->fileList($item_id, $audios, $this->lang->t9n('audio'));
        }

        if (!empty($misc)) {

            $table .= $this->fileList($item_id, $misc, $this->lang->t9n('miscellaneous'));
        }

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($bc, 'col-12');
        $el->column($upload_card, 'col-xl-4 mb-3');
        $el->column($table, 'col-xl-8 mb-3');
        $row = $el->render();

        $el = null;

        $this->append(['html' => $row]);

        return $this->send();
    }

    /**
     * Generate a file list.
     *
     * @param int $item_id
     * @param array $files
     * @param string $type
     * @return string
     * @throws Exception
     */
    private function fileList(int $item_id, array $files, string $type): string {

        $title = "<b class=\"text-uppercase\">$type</b>";

        $IL_BASE_URL = IL_BASE_URL;

        /** @var Bootstrap\ListGroup $el */
        $li = $this->di->get('ListGroup');

        foreach ($files as $file) {

            $encoded_filename = $this->sanitation->lmth($file);
            $encoded_filename = $this->sanitation->urlquery($encoded_filename);

            // Buttons.

            /** @var Bootstrap\IconButton $el */
            $el = $this->di->get('IconButton');

            $el->elementName('a');
            $el->addClass('btn-round btn-secondary mr-2 my-2 download');
            $el->href(IL_BASE_URL . "index.php/supplements/download?id={$item_id}&disposition=attachment&filename={$encoded_filename}");
            $el->icon('download');
            $download_button = $el->render();

            $el = null;

            $rename_button = '';

            if (in_array($this->session->data('permissions'), ['A', 'U']) === true) {

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->addClass('btn-sm btn-outline-primary rename-file my-2');
                $el->style('min-width: 5rem');
                $el->name('rename');
                $el->html($this->lang->t9n('Rename'));
                $rename_button = $el->render();

                $el = null;
            }

            $delete_button = '';

            if (in_array($this->session->data('permissions'), ['A', 'U']) === true) {

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->addClass('btn-sm btn-outline-danger delete-file my-2');
                $el->style('min-width: 5rem');
                $el->name('delete');
                $el->dataBody($this->lang->t9n('Do you want to delete this file?'));
                $el->dataButton($this->lang->t9n('Delete'));
                $el->dataTitle($this->lang->t9n('Delete'));
                $el->html($this->lang->t9n('Delete'));
                $delete_button = $el->render();

                $el = null;
            }

            // File link row.
            $link =  <<<EOT
                <a href="{$IL_BASE_URL}index.php/supplements/download?id={$item_id}&filename={$encoded_filename}"
                    target="_blank"
                    class="filename-link">
                    {$file}
                </a>
EOT;
            $filename_input =  <<<EOT
<input type="text" name="filename" value="" class="d-none form-control rounded-0 w-75 p-0">
EOT;

            /** @var Bootstrap\Row $el */
            $r = $this->di->get('Row');

            $r->column("$download_button $link $filename_input", 'col text-truncate');
            $r->column("$rename_button $delete_button", 'col-sm-auto');
            $row = $r->render();

            $el = null;

            $li->li($row);
        }

        $list = $li->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header($title, 'px-4 pt-3');
        $el->listGroup($list);
        $card = $el->render();

        $el = null;

        return $card;
    }
}
