<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Media\Temporal;
use Librarian\Mvc\TextView;

class ItemdiscussionView extends TextView {

    /**
     * @var Temporal
     */
    private $temporal_obj;

    /**
     * Main.
     *
     * @param $item_id
     * @param array $messages
     * @return string
     * @throws Exception
     */
    public function main($item_id, array $messages) {

        $this->title("{$this->lang->t9n('Discussion')} - {$messages['title']}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item("{$messages['title']}", '#summary?id=' . $item_id);
        $el->item($this->lang->t9n('Discussion'));
        $bc = $el->render();

        $el = null;

        // New message form.

        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->name('message');
        $el->label($this->lang->t9n('New post'));
        $ta = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('id');
        $el->value($item_id);
        $hidden_id = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html($this->lang->t9n('Send'));
        $submit = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('message-form');
        $el->action(IL_BASE_URL . 'index.php/itemdiscussion/save');
        $el->html("$ta $hidden_id $submit");
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->body($form, null, 'pt-2');
        $card = $el->render();

        $el = null;

        // Messages.

        $list = $this->_formatMessages($messages);

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($card, 'mb-3 col-lg-4');
        $el->column($list, 'mb-3 col-lg-8');

        $content = $el->render();

        $this->append(['html' => "$bc $content"]);

        return $this->send();
    }

    /**
     * List of messages.
     *
     * @param array $messages
     * @return string
     * @throws Exception
     */
    public function messages(array $messages) {

        $list = $this->_formatMessages($messages);

        $this->append(['html' => $list]);

        return $this->send();
    }

    /**
     * Format message array to a HTML list.
     *
     * @param array $messages
     * @return string
     * @throws Exception
     */
    private function _formatMessages(array $messages): string {

        $this->temporal_obj = $this->di->getShared('Temporal');

        // Messages.

        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->id('message-list');

        if (empty($messages['messages'])) {

            $el->div($this->lang->t9n('No posts'), 'text-center text-secondary text-uppercase py-4');
        }

        foreach ($messages['messages'] as $message) {

            $added_time = $this->temporal_obj->toUserTime($message['added_time']);

            $message_breaks = nl2br($message['message']);

            $el->div(<<<EOT
                <div class="d-flex w-100 justify-content-between mb-2">
                    <b>{$message['name']}</b> <small>{$added_time}</small>
                </div>
                {$message_breaks}
EOT
            );
        }

        $list = $el->render();

        $el = null;

        return $list;
    }
}
