<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
use Librarian\Mvc\TextView;

class RegistrationView extends TextView {

    /**
     * Main.
     *
     * @param int|null $num_users
     * @return string
     * @throws Exception
     */
    public function main(int $num_users = null): string {

        /*
         * Head.
         */

        $this->title('Registration');

        $this->styleLink('css/plugins.css');

        $this->head();

        /*
         * Body.
         */

        if (isset($num_users) && $num_users === 0) {

            // Welcome.

            $IL_BASE_URL = IL_BASE_URL;

            /** @var Bootstrap\Alert $el */
            $el = $this->di->get('Alert');

            $el->addClass('my-4');
            $el->context('success');
            $el->html(<<<EOT
                <h4>Welcome to <i>I,&nbsp;Librarian</i>!</h4>
                <div class="text-justify">
                    If you used <i>I,&nbsp;Librarian</i> before, you can
                    <a href="{$IL_BASE_URL}index.php/migration">upgrade</a>
                    your library to the current version. Otherwise, create
                    the first user account below.
                </div>
EOT
            );
            $welcome = $el->render();

            $el = null;

            $argon = '';

            if (defined('PASSWORD_ARGON2I') === false) {

                /** @var Bootstrap\Alert $el */
                $el = $this->di->get('Alert');

                $el->addClass('my-4');
                $el->context('danger');
                $el->html(<<<EOT
                A required PHP encryption extension Sodium is missing. You won't be able to create user accounts.
EOT
                );
                $argon = $el->render();

            }

            $el = null;

            $bc = '';

        } else {

            $welcome = '';
            $argon = '';

            /** @var Bootstrap\Breadcrumb $el */
            $el = $this->di->get('Breadcrumb');

            $el->style('margin: 0 -15px');
            $el->item('IL', IL_BASE_URL);
            $el->item("Create account");
            $bc = $el->render();

            $el = null;
        }

        /*
         * Sign up form.
         */

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('username');
        $el->label('Username');
        $el->required('required');
        $el->maxlength('256');
        $username = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('password');
        $el->name('password');
        $el->label('Password');
        $el->required('required');
        $el->tooltip('Password must be at least 8 characters long.');
        $el->maxlength('256');
        $password = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('password');
        $el->name('password2');
        $el->label('Re-type password');
        $el->maxlength('256');
        $el->required('required');
        $password2 = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('email');
        $el->type('email');
        $el->label('Email');
        $el->maxlength('256');
        $el->required('required');
        $email = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('first_name');
        $el->label('First name');
        $el->maxlength('256');
        $first_name = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('last_name');
        $el->label('Last name');
        $el->maxlength('256');
        $last_name = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('primary');
        $el->type('submit');
        $el->html('Create account');
        $sign_button = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->addClass('ml-1');
        $el->context('secondary');
        $el->type('reset');
        $el->html('Reset');
        $reset_button = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->action(IL_BASE_URL . 'index.php/registration/createaccount');
        $el->id('signup-form');
        $el->autocomplete('off');
        $el->html($username
                . $password
                . $password2
                . $email
                . '<h5>Optional</h5>'
                . $first_name
                . $last_name
                . $sign_button
                . $reset_button);
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('bg-white mt-4 mb-3');
        $el->header('<b>CREATE ACCOUNT</b>');
        $el->body($form);
        $card = $el->render();

        $el = null;

        // Top hierarchy elements.

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($welcome . $argon . $card, 'col-lg-4 offset-lg-4');
        $outer_row = $el->render();

        $el = null;

        /** @var Element $el */
        $el = $this->di->get('Element');

        $el->addClass('container-fluid');
        $el->html($bc . $outer_row);
        $container = $el->render();

        $el = null;

        $this->append($container);

        /*
         * End.
         */

        $this->scriptLink('js/plugins.js');

        $this->script(<<<EOT
            $(function(){
                new RegistrationView();
                $('[data-toggle="tooltip"]').tooltip();
            });
EOT
        );

        $this->end();

        return $this->send();
    }
}
