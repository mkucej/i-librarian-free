<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
use Librarian\Mvc\TextView;

class ItemView extends TextView {

    /**
     * Main.
     *
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function main(array $data): string {

        $first_name = isset($data['first_name']) ? $data['first_name'] : 'Who are you?';

        $this->title("Item - Library");

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
        $dasboard = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('arrow-left');
        $back_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('view-list');
        $summary_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2 text-warning');
        $el->icon('file-pdf-box');
        $pdf_icon = $el->render();

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
        $el->icon('tag');
        $tags_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('paperclip');
        $files_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('forum');
        $discuss_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('keyboard-variant');
        $keyboard = $el->render();

        $el = null;

        $menu_arr = [];

        $menu_arr[] = [
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
        ];

        $menu_arr[] = [
            'label' => "{$dasboard}Dashboard",
            'link'  => IL_BASE_URL . 'index.php/#dashboard/main'
        ];

        $menu_arr[] = [
            'label' => "{$back_icon}Back",
            'link'  => IL_BASE_URL,
            'attrs' => 'id="item-back-link"'
        ];

        $menu_arr[] = [
            'label' => "{$summary_icon}Item summary",
            'link'  => '#summary?id=',
            'attrs' => 'class="add-id-link"'
        ];

        if ($this->session->data('permissions') === 'A' || $this->session->data('permissions') === 'U') {

            $menu_arr[] = [
                'label' => "{$pdf_icon}Pdf",
                'link' => '#',
                'submenu' => [
                    [
                        'label' => 'Open',
                        'link' => '#pdf/main?id=',
                        'attrs' => 'class="add-id-link"'

                    ],
                    [
                        'label' => 'Open in new window',
                        'link' => IL_BASE_URL . 'index.php/pdf?id=',
                        'attrs' => 'class="add-id-link" target="_blank"'
                    ],
                    [
                        'label' => "Download",
                        'link' => IL_BASE_URL . 'index.php/pdf/file?disposition=attachment&id=',
                        'attrs' => 'class="add-id-link"'
                    ],
                    [
                        'label' => "Manage PDF",
                        'link'  => '#pdf/manage?id=',
                        'attrs' => 'class="add-id-link"'
                    ]
                ]
            ];

        } else {

            $menu_arr[] = [
                'label' => "{$pdf_icon}Pdf",
                'link' => '#',
                'submenu' => [
                    [
                        'label' => 'Open',
                        'link' => '#pdf/main?id=',
                        'attrs' => 'class="add-id-link"'

                    ],
                    [
                        'label' => 'Open in new window',
                        'link' => IL_BASE_URL . 'index.php/pdf?id=',
                        'attrs' => 'class="add-id-link" target="_blank"'
                    ],
                    [
                        'label' => "Download",
                        'link' => IL_BASE_URL . 'index.php/pdf/file?disposition=attachment&id=',
                        'attrs' => 'class="add-id-link"'
                    ]
                ]
            ];
        }

        $menu_arr[] = [
            'label' => "{$notes_icon}Notes",
            'link'  => '#notes?id=',
            'attrs' => 'class="add-id-link"'
        ];

        $menu_arr[] = [
            'label' => "{$tags_icon}Tags",
            'link'  => '#tags/item?id=',
            'attrs' => 'class="add-id-link"'
        ];

        if ($this->session->data('permissions') === 'A' || $this->session->data('permissions') === 'U') {

            $menu_arr[] = [
                'label' => "{$edit_icon}Edit",
                'link' => '#edit?id=',
                'attrs' => 'class="add-id-link"'
            ];

            $menu_arr[] = [
                'label' => "{$files_icon}Supplements",
                'link' => '#supplements?id=',
                'attrs' => 'class="add-id-link"'
            ];
        }

        $menu_arr[] = [
            'label' => "{$discuss_icon}Discussion",
            'link'  => '#itemdiscussion?id=',
            'attrs' => 'class="add-id-link"'
        ];

        $menu_arr[] = [
            'label'   => "{$keyboard}Extended keyboard",
            'link'    => '#',
            'attrs'   => 'id="keyboard-toggle" class="d-none d-lg-block"'
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
        $el->action(IL_BASE_URL . 'index.php/notes/save');
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
            $export
EOT
        );

        $this->scriptLink('js/plugins.js');
        $this->scriptLink('js/tinymce/tinymce.min.js');

        $this->script(<<<EOT
            $(function(){
                new ItemView();
            });

EOT
        );

        $this->end();

        return $this->send();
    }
}
