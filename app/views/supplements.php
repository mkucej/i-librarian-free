<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
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

        $this->title('Supplements - ' . $files['title']);

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item("{$files['title']}", '#summary?id=' . $item_id);
        $el->item('Supplements');
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
        $el->label('graphical abstract');
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
        $el->html('Upload');
        $upload_button = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('upload-form');
        $el->method('POST');
        $el->action(IL_BASE_URL . 'index.php/supplements/save');
        $el->append("$file_input $graphical_abstract $id_input $csrf_input $upload_button");
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->id('upload-card');
        $el->header('<b>UPLOAD FILES</b>', 'px-4 pt-3');
        $el->body($form, null, 'px-4 pb-4');
        $upload_card = $el->render();

        $el = null;

        // File table.
        if (count($files['files']) === 0) {

            /** @var Bootstrap\ListGroup $el */
            $el = $this->di->get('ListGroup');

            $el->div('NO FILES FOUND', 'text-muted text-center p-4');
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

            $table .= $this->fileList($item_id, $images, 'images');
        }

        if (!empty($pdfs)) {

            $table .= $this->fileList($item_id, $pdfs, 'PDFs');
        }

        if (!empty($videos)) {

            $table .= $this->fileList($item_id, $videos, 'videos');
        }

        if (!empty($audios)) {

            $table .= $this->fileList($item_id, $audios, 'audio');
        }

        if (!empty($misc)) {

            $table .= $this->fileList($item_id, $misc, 'miscellaneous');
        }

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($bc, 'col-12');
        $el->column($upload_card, 'col-md-4 mb-3');
        $el->column($table, 'col-md-8 mb-3');
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
        $li->addClass('mb-3');

        foreach ($files as $file) {

            $encoded_filename = $this->sanitation->lmth($file);
            $encoded_filename = $this->sanitation->urlquery($encoded_filename);

            // Buttons.

            /** @var Bootstrap\IconButton $el */
            $el = $this->di->get('IconButton');

            $el->elementName('a');
            $el->addClass('btn-round btn-secondary mr-2 download');
            $el->href(IL_BASE_URL . "index.php/supplements/download?id={$item_id}&disposition=attachment&filename={$encoded_filename}");
            $el->icon('download');
            $download_button = $el->render();

            $el = null;

            $rename_button = '';

            if (in_array($this->session->data('permissions'), ['A', 'U']) === true) {

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->addClass('btn-sm btn-outline-primary rename-file mt-3');
                $el->name('rename');
                $el->html('Rename');
                $rename_button = $el->render();

                $el = null;
            }

            $delete_button = '';

            if ($this->session->data('permissions') === 'A') {

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->addClass('btn-sm btn-outline-danger delete-file mt-3');
                $el->name('delete');
                $el->dataBody('Do you want to delete this file?');
                $el->dataButton('Delete');
                $el->html('Delete');
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

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->elementName('input');
            $el->type('text');
            $el->addClass('d-none form-control rounded-0 w-75 p-0');
            $el->name('filename');
            $filename_input = $el->render();

            $el = null;

            $li->div(<<<EOT
                <div class="text-truncate" style="width:95%">
                    $download_button $link $filename_input
                </div>
                $rename_button
                $delete_button
EOT
            );


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
