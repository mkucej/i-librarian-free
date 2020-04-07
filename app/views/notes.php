<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class NotesView extends TextView {

    /**
     * Main.
     *
     * @param int $item_id Item id.
     * @param array $notes
     * @return string
     * @throws Exception
     */
    public function main(int $item_id, array $notes): string {

        $this->title("Notes - {$notes['title']} - Library");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item("{$notes['title']}", '#summary?id=' . $item_id);
        $el->item('Notes');
        $bc = $el->render();

        $el = null;

        // User's notes.

        $user_note = isset($notes['user']['note']) ?
            $this->sanitation->lmth($notes['user']['note']) :
            '<span class="text-muted mt-4">No notes found.</span>';

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('link');
        $el->addClass('open-notes px-1 py-0 border-0');
        $el->dataId($item_id);
        $el->html('Edit');
        $note_button = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>YOUR NOTES</b> $note_button");
        $el->body("<div id=\"user-note\">$user_note</div>");
        $user_card = $el->render();

        $el = null;

        // Add second column - others' notes.

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('text-secondary mdi-24px');
        $el->icon('account');
        $user_icon = $el->render();

        $el = null;

        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->addClass('list-group-flush');

        if (empty($notes['others'])) {

            $el->li('<span class="text-muted">No notes found.</span>', 'pt-0 pb-4');
        }

        foreach ($notes['others'] as $others_note) {

            $note = $this->sanitation->lmth($others_note['note']);

            $note_html = <<<EOT
                $user_icon {$others_note['name']}<br>
                $note
EOT;

            $el->li($note_html);
        }

        $notes_list = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>OTHERS' NOTES</b>");
        $el->listGroup($notes_list);
        $others_card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($bc, 'col-12');
        $el->column($user_card, 'col-md-6 mb-3');
        $el->column($others_card, 'col-md-6 mb-3');
        $row = $el->render();

        $el = null;

        $this->append(['html' => $row]);

        return $this->send();
    }
}
