<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
use Librarian\Media\Temporal;
use Librarian\Mvc\TextView;

class ProjectView extends TextView {

    use SharedHtmlView;

    /**
     * @var Temporal
     */
    private $temporal_obj;

    /**
     * Main.
     *
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function main(array $data): string {

        $first_name = isset($data['first_name']) ? $data['first_name'] : 'Who are you?';

        $this->title("Project - Library");

        $this->styleLink('css/plugins.css');

        $this->head();

        /*
         * Side menu.
         */

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('account');
        $account = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('view-dashboard');
        $dashboard_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('flask-empty-outline');
        $projects_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('format-list-checkbox');
        $browse_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('settings');
        $edit_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('pen');
        $notes_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('note-text');
        $comp_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('forum');
        $discuss_icon = $el->render();

        $el = null;

//        /** @var Bootstrap\Icon $el */
//        $el = $this->di->get('Icon');
//
//        $el->addClass('mdi-24px mr-2');
//        $el->icon('rss');
//        $rss_icon = $el->render();
//
//        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('keyboard-variant');
        $keyboard = $el->render();

        $el = null;

        $menu_arr = [
            [
                'label'   => "{$account}<span id=\"menu-first-name\" class=\"d-inline-block text-truncate\" style=\"width: 140px\">{$first_name}</span>",
                'link'    => '#',
                'submenu' => [
                    [
                        'label' => 'User profile',
                        'link'  => '#profile/main'
                    ],
                    [
                        'label' => 'User settings',
                        'link'  => '#settings/main'
                    ],
                    [
                        'label' => '<div id="sign-out">Sign out</div>',
                        'link'  => '#'
                    ]
                ]
            ],
            [
                'label' => "{$dashboard_icon}Dashboard",
                'link'  => IL_BASE_URL . 'index.php#dashboard/main'
            ],
            [
                'label' => "{$projects_icon}Projects",
                'link'  => IL_BASE_URL . 'index.php#projects/main'
            ],
            [
                'label' => "{$browse_icon}Project items",
                'link'  => '#project/browse?id=',
                'attrs' => 'class="add-id-link"'
            ],
            [
                'label' => "{$notes_icon}Project notes",
                'link'  => '#project/notes?id=',
                'attrs' => 'class="add-id-link"'
            ],
            [
                'label' => "{$comp_icon}Note compilation",
                'link'  => '#project/compilenotes?id=',
                'attrs' => 'class="add-id-link"'
            ],
            [
                'label' => "{$discuss_icon}Discussion",
                'link'  => '#project/discussion?id=',
                'attrs' => 'class="add-id-link"'
            ],
            [
                'label' => "{$edit_icon}Edit",
                'link'  => '#project/edit?id=',
                'attrs' => 'class="add-id-link"'
            ],
//            [
//                'label' => "{$rss_icon}RSS feed",
//                'link'  => IL_BASE_URL . 'index.php/project/rss?id=',
//                'attrs' => 'class="add-id-link"'
//            ],
            [
                'label'   => "{$keyboard}Extended keyboard",
                'link'    => '#',
                'attrs'   => 'id="keyboard-toggle" class="d-none d-lg-block"'
            ]
        ];

        /** @var Bootstrap\SideMenu $el */
        $el = $this->di->get('SideMenu');

        $el->id('side-menu');
        $el->menu($menu_arr);
        $menu = $el->render();

        $el = null;

        /** @var Bootstrap\Sidebar $el */
        $el = $this->di->get('Sidebar');

        $el->addClass('navbar-dark navbar-expand-lg');
        $el->menu($menu);
        $sidebar = $el->render();

        $el = null;

        // Notes form.

        /** @var Element $el Vanilla textarea. */
        $el = $this->di->get('Element');

        $el->elementName('textarea');
        $el->id('notes-ta');
        $el->name('note');
        $ta = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->id('id-hidden');
        $el->name('id');
        $hidden = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('note-form');
        $el->action(IL_BASE_URL . 'index.php/project/savenotes');
        $el->html($ta . $hidden);
        $form = $el->render();

        $el = null;

        // Notes floating window.

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->id('notes-window');
        $el->addClass('d-none');
        $el->header(<<<'EOT'
            Notes
            <button type="button" class="close" aria-label="Close">
                <span aria-hidden="true" class="mdi mdi-close"></span>
            </button>
EOT
        );
        $el->body($form);
        $notes_window = $el->render();

        $el = null;

        // Confirm modal window.

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('primary');
        $el->html('Yes');
        $button = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-confirm');
        $el->header('Confirmation');
        $el->body('Confirm?');
        $el->button($button);
        $confirm = $el->render();

        $el = null;

        // Filters.

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('filter');
        $el->placeholder('Search');
        $filter = $el->render();

        $el = null;

        /** @var Element $el */
        $el = $this->di->get('Element');

        $el->addClass('px-3 pt-3 pb-1 bg-darker-5');
        $el->html($filter);
        $filter_cont = $el->render();

        $el = null;

        /** @var Element $el */
        $el = $this->di->get('Element');

        $el->addClass('container-fluid');
        $el->html('');
        $result_cont = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->componentSize('large');
        $el->id('modal-filters');
        $el->header("Filter");
        $el->body("$filter_cont $result_cont", 'p-0');
        $modal_filters = $el->render();

        $el = null;

        // Previous searches.

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-searches');
        $el->componentSize('large');
        $el->header('Previous searches');
        $el->body('', 'p-0 border-top border-bottom border-darker-5');
        $searches = $el->render();

        $el = null;

        // Export modal.

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('primary');
        $el->html('Export');
        $button = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-export');
        $el->header('Export');
        $el->body('', 'bg-darker-5');
        $el->button($button);
        $export = $el->render();

        $el = null;

        // Omnitool modal.

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('primary');
        $el->html('Submit');
        $button = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-omnitool');
        $el->header('Omnitool - apply actions to all displayed items.');
        $el->body('', 'bg-darker-5');
        $el->button($button);
        $omnitool = $el->render();

        $el = null;

        // Display settings modal.

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('primary');
        $el->html('Save');
        $button = $el->render();

        $el = null;

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-settings');
        $el->header('Display settings');
        $el->body('', 'bg-darker-5');
        $el->button($button);
        $settings = $el->render();

        $el = null;

        // Top HTML structure.

        $this->append(<<<EOT
            <div class="container-fluid h-100">
                <div class="row">
                    <div class="left-container col-lg-auto p-0">
                        $sidebar
                    </div>
                    <div class="col" id="content-col"></div>
                </div>
            </div>
            $notes_window
            $confirm
            $modal_filters
            $export
            $omnitool
            $settings
            $searches
EOT
        );

        $this->scriptLink('js/plugins.js');
        $this->scriptLink('js/tinymce/tinymce.min.js');

        $this->script(<<<EOT
            $(function(){
                new ProjectView();
            });

EOT
        );

        $this->end();

        return $this->send();
    }

    /**
     * Edit project form.
     *
     * @param array $project
     * @return string
     * @throws Exception
     */
    public function edit(array $project): string {

        if (empty($project)) {

            $this->title("Editing not authorized - Project");

            $this->head();

            /** @var Bootstrap\Breadcrumb $el */
            $el = $this->di->get('Breadcrumb');

            $el->style('margin: 0 -15px');
            $el->addClass('bg-transparent');
            $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
            $el->item('Projects', IL_BASE_URL . 'index.php/#projects/main');
            $el->item("Edit project");
            $bc = $el->render();

            $el = null;

            /** @var Bootstrap\Alert $el */
            $el = $this->di->get('Alert');

            $el->context('danger');
            $el->html('You are not authorized to edit this project.');
            $alert = $el->render();

            $el = null;

            /** @var Bootstrap\Row $el */
            $el = $this->di->get('Row');

            $el->column($alert, 'col-xl-6 offset-xl-3');
            $row = $el->render();

            $el = null;

            $this->append(['html' => "$bc $row"]);

            return $this->send();
        }

        $this->title("Edit - Project - {$project['project']}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item('Projects', IL_BASE_URL . 'index.php/#projects/main');
        $el->item($project['project'], '#project/browse?id=' . $project['id']);
        $el->item("Edit project");
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('name');
        $el->label('Project name');
        $el->required('required');
        $el->value($this->sanitation->attr($this->sanitation->lmth($project['project'])));
        $proj_name = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->context('info');
        $el->icon('help-circle');
        $el->tooltip('Users can join this project freely.');
        $help = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('project-access-1');
        $el->type('radio');
        $el->inline(true);
        $el->name('access');
        $el->value('open');
        $el->label("open access");
        $el->html("&nbsp;$help");

        if ($project['is_restricted'] === 'N') {

            $el->checked('checked');
        }

        $proj_access = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->context('info');
        $el->icon('help-circle');
        $el->tooltip('You select users joining this project.');
        $help = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('project-access-2');
        $el->type('radio');
        $el->inline(true);
        $el->name('access');
        $el->value('restricted');
        $el->label('restricted access');
        $el->html("&nbsp;$help");

        if ($project['is_restricted'] === 'Y') {

            $el->checked('checked');
        }

        $proj_access .= $el->render();

        $el = null;

        $user_list = '';
        $users_card = '';

        if (isset($project['users'])) {

            foreach ($project['users'] as $user) {

                $checked = $user['in_project'] === 'Y' ? true : false;

                /** @var Bootstrap\Input $el */
                $el = $this->di->get('Input');

                $el->id("project-users-{$user['id_hash']}");
                $el->name('users[]');
                $el->type('checkbox');
                $el->value($user['id_hash']);
                $el->label($user['name']);

                if ($checked === true) {

                    $el->checked('checked');
                }

                $user_list .= $el->render();

                $el = null;
            }

            $users_card = '<div class="my-2"><b>Users with access</b></div>';

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->addClass('mb-2 bg-darker-3 px-3 py-2');
            $el->style('max-height: 25vh;overflow: auto');
            $el->html($user_list);
            $users_card .=  $el->render();

            $el = null;
        }

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->addClass('mt-3');
        $el->type('submit');
        $el->context('danger');
        $el->html('Update');
        $update = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('project_id');
        $el->type('hidden');
        $el->value($project['id']);

        $project_id = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('project-form');
        $el->action(IL_BASE_URL . 'index.php/project/update');
        $el->html("$proj_name $proj_access <br> $users_card $project_id $update");
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header('<b>EDIT PROJECT SETTINGS</b>');
        $el->body($form);
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($card, 'col-xl-6 offset-xl-3');
        $row = $el->render();

        $el = null;

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }

    /**
     * Display discussion.
     *
     * @param $project_id
     * @param array $messages
     * @return string
     * @throws Exception
     */
    public function discussion($project_id, array $messages): string {

        $this->title("Discussion - Project - {$messages['title']}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item('Projects', IL_BASE_URL . 'index.php/#projects/main');
        $el->item($messages['title'], '#project/browse?id=' . $project_id);
        $el->item('Discussion');
        $bc = $el->render();

        $el = null;

        // New message form.

        /** @var Bootstrap\Textarea $el */
        $el = $this->di->get('Textarea');

        $el->name('message');
        $el->label('New message');
        $ta = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('project_id');
        $el->value($project_id);
        $hidden_id = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html('Send');
        $submit = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('message-form');
        $el->action(IL_BASE_URL . 'index.php/project/savemessage');
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
     * Fromat discussion messages.
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

        if (empty($messages['messages'])) {

            $el->div('No messages', 'text-center text-muted text-uppercase py-4');
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

    /**
     * Notes.
     *
     * @param int $project_id
     * @param array $notes
     * @return string
     * @throws Exception
     */
    public function notes(int $project_id, array $notes): string {

        $this->title("Project notes - Project - {$notes['title']}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item('Projects', IL_BASE_URL . 'index.php/#projects/main');
        $el->item($notes['title'], '#project/browse?id=' . $project_id);
        $el->item('Project notes');
        $bc = $el->render();

        $el = null;

        // User's notes.

        $user_note = isset($notes['user']['note']) ?
            $this->sanitation->lmth($notes['user']['note']) :
            '<span class="text-secondary">No notes found.</span>';

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('link');
        $el->addClass('open-notes px-1 py-0 border-0');
        $el->dataId($project_id);
        $el->html('Edit');
        $note_button = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('h-100');
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

        if (empty($notes['others'] )) {

            $el->li('No notes found.', 'pt-0 pb-4 text-secondary');
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

    /**
     * @param int $project_id
     * @param array $notes
     * @return string
     * @throws Exception
     */
    public function compilation(int $project_id, array $notes): string {

        $this->title("Item note compilation - Project - {$notes['title']}");

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', IL_BASE_URL . 'index.php/#dashboard/main');
        $el->item('Projects', IL_BASE_URL . 'index.php/#projects/main');
        $el->item($notes['title'], '#project/browse?id=' . $project_id);
        $el->item('Item note compilation');
        $bc = $el->render();

        $el = null;

        // User's notes.
        $user_htmls = [];
        $user_html = '<span class="text-muted">No notes found.</span>';

        foreach ($notes['user'] as $item_id => $item) {

            $note_htmls = [];

            foreach ($item['notes'] as $note) {

                $note_htmls[] = <<<NOTE
                {$this->sanitation->lmth($note['note'])}
NOTE;
            }

            $note_html = join('<br>', $note_htmls);
            $item_url = IL_BASE_URL . "index.php/item#summary?id={$item_id}";

            $user_htmls[] = <<<USER
                <h5><a href="$item_url">{$item['title']}</a></h5>
                $note_html
USER;

            $user_html = join('<br>', $user_htmls);
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('h-100');
        $el->header("<b>YOUR NOTES</b>");
        $el->body("<div id=\"user-note\">$user_html</div>");
        $user_card = $el->render();

        $el = null;

        // Add second column - others' notes.

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('text-secondary mdi-24px');
        $el->icon('account');
        $user_icon = $el->render();

        $el = null;

        $other_htmls = [];
        $other_html = '<span class="text-muted">No notes found.</span>';

        if (!empty($notes['other'])) {

            foreach ($notes['other'] as $item_id => $item) {

                $note_htmls = [];

                foreach ($item['notes'] as $note) {

                    $note_htmls[] = <<<NOTE
                    $user_icon {$note['name']}<br>
                    {$this->sanitation->lmth($note['note'])}
NOTE;
                }

                $note_html = join('<br>', $note_htmls);
                $item_url = IL_BASE_URL . "index.php/item#summary?id={$item_id}";

                $other_htmls[] = <<<USER
                <h5><a href="$item_url">{$item['title']}</a></h5>
                $note_html
USER;

                $other_html = join('<br>', $other_htmls);
            }
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>OTHERS' NOTES</b>");
        $el->body($other_html);
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
