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

        $this->title($this->lang->t9n('Registration'));

        $this->styleLink('css/plugins.css');

        $this->head();

        /*
         * Body.
         */

        if (isset($num_users) && $num_users === 0) {

            // Welcome.

            $IL_BASE_URL = IL_BASE_URL;
            $info1 = sprintf($this->lang->t9n('If you used %s before, you can migrate your library to the current version'), '<i>I, Librarian</i>');

            /** @var Bootstrap\Alert $el */
            $el = $this->di->get('Alert');

            $el->addClass('my-4');
            $el->context('success');
            $el->html(
<<<HTML
<h4>{$this->lang->t9n('Welcome to')} <i>I,&nbsp;Librarian</i>!</h4>
<div class="text-justify">
    {$info1}.
    {$this->lang->t9n('Otherwise, create the first user account below')}.
    <a href="{$IL_BASE_URL}index.php/migration">{$this->lang->t9n('Migrate now')}</a>
</div>
HTML
            );
            $welcome = $el->render();

            $el = null;

            $bc = '';

        } else {

            $welcome = '';

            /** @var Bootstrap\Breadcrumb $el */
            $el = $this->di->get('Breadcrumb');

            $el->style('margin: 0 -15px');
            $el->item('IL', IL_BASE_URL);
            $el->item($this->lang->t9n('Create account'));
            $bc = $el->render();

            $el = null;
        }

        /*
         * Sign up form.
         */

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('username');
        $el->label($this->lang->t9n('Username'));
        $el->required('required');
        $el->maxlength('256');
        $username = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('password');
        $el->name('password');
        $el->label($this->lang->t9n('Password'));
        $el->required('required');
        $el->tooltip($this->lang->t9n('Password must be at least 8 characters long'));
        $el->maxlength('256');
        $password = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('password');
        $el->name('password2');
        $el->label($this->lang->t9n('Re-type password'));
        $el->maxlength('256');
        $el->required('required');
        $password2 = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('email');
        $el->type('email');
        $el->label($this->lang->t9n('Email'));
        $el->maxlength('256');
        $el->required('required');
        $email = $el->render();

        $el = null;

        $optional = "<h5>{$this->lang->t9n('Optional')}</h5>";

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('first_name');
        $el->label($this->lang->t9n('First name'));
        $el->maxlength('256');
        $first_name = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('last_name');
        $el->label($this->lang->t9n('Last name'));
        $el->maxlength('256');
        $last_name = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('primary');
        $el->type('submit');
        $el->html($this->lang->t9n('Create account'));
        $sign_button = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->addClass('ml-1');
        $el->context('secondary');
        $el->type('reset');
        $el->html($this->lang->t9n('Clear'));
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
                . $optional
                . $first_name
                . $last_name
                . $sign_button
                . $reset_button);
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('bg-white mt-4 mb-3');
        $el->header("<b class=\"text-uppercase\">{$this->lang->t9n('Create account')}</b>");
        $el->body($form);
        $card = $el->render();

        $el = null;

        // Top hierarchy elements.

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($welcome . $card, 'col-lg-4 offset-lg-4');
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

        $this->scriptLink('js/plugins.min.js');

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
