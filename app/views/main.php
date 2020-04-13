<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
use Librarian\Mvc\TextView;

class MainView extends TextView {

    use SharedHtmlView;

    /**
     * Non-authenticated HTML view.
     *
     * @return string
     * @throws Exception
     */
    public function getNonAuthenticated() {

        /*
         * Head.
         */

        $this->styleLink('css/plugins.css');

        // Override content-light when not authenticated.
        $this->style = <<<STYLE
body.content-light {
    background-color: #111111;
    background-repeat: no-repeat;
    background-size: cover;
    background-position: center;
    background-image: url("img/bg-v1x.jpg");
}

@media screen and (max-resolution: 143dpi) {
    body.content-light {
        background-image: url("img/bg-v1x.jpg");
    }
}

@media screen and (max-resolution: 143dpi) and (min-width: 1200px) {
    body.content-light {
        background-image: url("img/bg-h1x.jpg");
    }
}

@media screen and (min-device-pixel-ratio: 2) and (min-resolution: 144dpi) {
    body.content-light {
        background-image: url("img/bg-v2x.jpg");
    }
}

@media screen and (min-device-pixel-ratio: 2) and (min-resolution: 144dpi) and (min-width: 1200px) {
    body.content-light {
        background-image: url("img/bg-h2x.jpg");
    }
}

.card {
    background-color: rgba(245,250,255,0.5);
    width: 100%;
    max-width: 400px;
}

#version-badge {
    position: absolute;
    bottom: 1rem;
    right: 1rem;
    background-color: rgba(245,250,255,0.5);
    padding: 0 0.5rem
}

@supports (backdrop-filter: blur(20px)) or (-webkit-backdrop-filter: blur(20px)) {
    .card, #version-badge {
        -webkit-backdrop-filter: blur(10px);
        backdrop-filter: blur(10px);
        background-color: rgba(245,250,255,0.25);
    }
}
STYLE;
        $this->head();

        /*
         * Body.
         */

        /*
         * Sign in form.
         */

        $title = <<<TITLE
            <div
                class="bg-primary text-white text-center py-2 w-100"
                style="font-family: monospace;max-width: 400px;margin:auto;font-size: 20px;">
                I, Librarian Free
             </div>
TITLE;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('username');
        $el->autofocus('autofocus');
        $el->label('Username or email');
        $username = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('password');
        $el->name('password');
        $el->label('Password');
        $el->hint('<a style="font-size:1rem" class="text-dark" href="' . IL_BASE_URL . 'index.php/resetpassword"><b>Forgot password?</b></a>');
        $password = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('primary');
        $el->type('submit');
        $el->html('Sign in');
        $sign_btn = $el->render();

        $el = null;

        $register_btn = '';

        if ($this->app_settings->getGlobal('disallow_signup') === '0') {

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->elementName('a');
            $el->href(IL_BASE_URL . 'index.php/registration');
            $el->context('secondary');
            $el->addClass('float-right');
            $el->html('Create account');
            $register_btn = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->action(IL_BASE_URL . 'index.php/authentication');
        $el->id('signin-form');
        $el->html("$username $password $sign_btn $register_btn");
        $form = $el->render();

        $el = null;

        /*
         * Card.
         */

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->context('primary');
        $el->addClass('d-inline-block text-left border-0 mb-3');
        $el->body($form, null, 'pt-3');
        $card = $el->render();

        $el = null;

        /*
         * Row and container.
         */

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->addClass('align-items-center text-center');
        $el->style('height: 100vh');
        $el->column("$title $card");
        $row = $el->render();

        $el = null;

        $version_badge = '<div id="version-badge">' . IL_VERSION . '</div>';

        /** @var Element $el */
        $el = $this->di->get('Element');

        $el->addClass('container-fluid');
        $el->html($row . $version_badge);
        $container = $el->render();

        $el = null;

        $this->append($container);

        /*
         * End.
         */

        $this->scriptLink('js/plugins.js');

        $this->script(<<<EOT
            $(function(){
                $('[data-toggle="tooltip"]').tooltip();
                new MainView();
            });
EOT
        );

        $this->end();

        return $this->send();
    }

    /**
     * Authenticated main HTML view.
     *
     * @param $data
     * @return string
     * @throws Exception
     */
    public function getAuthenticated($data) {

        // There will be a single call to get all data for the menu.
        $first_name = isset($data['first_name']) ? $data['first_name'] : 'Who are you?';

        /*
         * Head.
         */

        $this->styleLink('css/plugins.css');

        $this->head();

        /*
         * Body.
         */

        /*
         * Side menu.
         */

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('view-dashboard');
        $dasboard = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('account');
        $account = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2 text-warning');
        $el->icon('cloud-upload');
        $record = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('library-books');
        $library = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('format-list-checkbox');
        $catalog = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('flask-empty-outline');
        $project = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('clipboard-outline');
        $clipboard = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('wrench');
        $wrench = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('keyboard-variant');
        $keyboard = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-24px mr-2');
        $el->icon('shield-account');
        $admin = $el->render();

        $el = null;

        $menu_arr = [];

        /*
         * Account.
         */
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
                    'link'  => ''
                ]
            ]
        ];

        /*
         * Dashboard.
         */
        $menu_arr[] = [
            'label' => "{$dasboard}Dashboard",
            'link'  => '#dashboard/main'
        ];

        /*
         * Import.
         */

        if ($this->session->data('permissions') === 'A' || $this->session->data('permissions') === 'U') {

            $external = [];

            if ($this->app_settings->getUser('connect_arxiv') === '1') {

                $external[] = [
                    'label' => 'arXiv',
                    'link' => '#arxiv/main'
                ];
            }

            if ($this->app_settings->getUser('connect_crossref') === '1') {

                $external[] = [
                    'label' => 'Crossref',
                    'link' => '#crossref/main'
                ];
            }

            if ($this->app_settings->getUser('connect_xplore') === '1') {

                $external[] = [
                    'label' => 'IEEE Xplore',
                    'link' => '#ieee/main'
                ];
            }

            if ($this->app_settings->getUser('connect_nasa') === '1') {

                $external[] = [
                    'label' => 'NASA ADS',
                    'link' => '#nasa/main'
                ];
            }

            if ($this->app_settings->getUser('connect_patents') === '1') {

                $external[] = [
                    'label' => 'Patents',
                    'link' => '#patents/main'
                ];
            }

            if ($this->app_settings->getUser('connect_pubmed') === '1') {

                $external[] = [
                    'label' => 'Pubmed',
                    'link' => '#pubmed/main'
                ];
            }

            if ($this->app_settings->getUser('connect_pmc') === '1') {

                $external[] = [
                    'label' => 'Pubmed Central',
                    'link' => '#pmc/main'
                ];
            }

            if ($this->app_settings->getUser('connect_scopus') === '1') {

                $external[] = [
                    'label' => 'Scopus',
                    'link' => '#scopus/main'
                ];
            }

            $menu_arr[] = [
                'label' => "{$record}Import",
                'link' => '#',
                'submenu' => [
                    [
                        'label' => 'Import wizard',
                        'link' => '#import/wizard'
                    ],
                    [
                        'label' => 'Manual import',
                        'link' => '#import/manual'
                    ],
                    [
                        'label' => 'Internet search',
                        'link' => '#',
                        'submenu' => $external
                    ]
                ]
            ];
        }

        /*
         * Library.
         */
        $menu_arr[] = [
            'label'   => "{$library}Library",
            'link'    => '#items/main'
        ];

        /*
         * Clipboard.
         */
        $menu_arr[] = [
            'label'   => "{$clipboard}Clipboard",
            'link'    => '#clipboard/main'
        ];

        /*
         * Projects.
         */
        $menu_arr[] = [
            'label'   => "{$project}Projects",
            'link'    => '#projects/main'
        ];

        /*
         * Catalog
         */
        $menu_arr[] = [
            'label'   => "{$catalog}Catalog",
            'link'    => '#items/catalog'
        ];

        /*
         * Tools.
         */
        if ($this->session->data('permissions') === 'A' || $this->session->data('permissions') === 'U') {

            $menu_arr[] = [
                'label' => "{$wrench}Tools",
                'link' => '#',
                'submenu' => [
                    [
                        'label' => 'Citation styles',
                        'link' => '#citation/main'
                    ],
                    [
                        'label' => 'Manage tags',
                        'link' => '#tags/manage'
                    ],
                    [
                        'label' => 'Normalize data',
                        'link' => '#normalize/main'
                    ]
                ]
            ];
        }

        /*
         * Admin.
         */
        if ($this->session->data('permissions') === 'A') {

            $menu_arr[] = [
                'label'   => "{$admin}Administrator",
                'link'    => '#',
                'submenu' => [
                    [
                        'label' => 'Logs',
                        'link'  => '#logs/main'
                    ],
                    [
                        'label' => 'Global settings',
                        'link'  => '#globalsettings/main'
                    ],
                    [
                        'label' => 'User management',
                        'link'  => '#users/main'
                    ],
                    [
                        'label' => 'Find duplicates',
                        'link'  => '#duplicates/main'
                    ],
                    [
                        'label' => 'Software details',
                        'link'  => '#details/main'
                    ],
                    [
                        'label' => 'Databases & indexes',
                        'link'  => '#reindex/main'
                    ]
                ]
            ];
        }

        /*
         * Keyboard.
         */
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

        // Previous searches.

        /** @var Bootstrap\Modal $el */
        $el = $this->di->get('Modal');

        $el->id('modal-searches');
        $el->componentSize('large');
        $el->header('Previous searches');
        $el->body('', 'p-0');
        $searches = $el->render();

        $el = null;

        // Top containers.
        $this->append(<<<EOT
            <div class="container-fluid h-100">
                <div class="row">
                    <div class="left-container col-lg-auto p-0">
                        $sidebar
                    </div>
                    <div class="col" id="content-col"></div>
                </div>
            </div>
            $modal_filters
            $confirm
            $export
            $omnitool
            $settings
            $searches
EOT
        );

        /*
         * End.
         */

        $this->scriptLink('js/plugins.js');

        $this->script(<<<EOT
            $(function(){
                $('[data-toggle="tooltip"]').tooltip();
                $('#side-menu').metisMenu();
                new MainView();
            });
EOT
        );

        $this->end();

        return $this->send();
    }
}
