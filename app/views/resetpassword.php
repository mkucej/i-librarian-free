<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
use Librarian\Mvc\TextView;

class ResetpasswordView extends TextView {

    /**
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function main(): string {

        /*
         * Head.
         */

        $this->title($this->lang->t9n('Reset password'));

        $this->styleLink('css/plugins.css');

        $this->head();

        /*
         * Body.
         */

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->item('IL', IL_BASE_URL);
        $el->item($this->lang->t9n('Reset password'));
        $bc = $el->render();

        $el = null;

        if ($this->app_settings->getIni('reset_password','reset_password') === '0') {

            /** @var Bootstrap\Alert $el */
            $el = $this->di->get('Alert');

            $el->addClass('mt-5');
            $el->context('danger');
            $el->html($this->lang->t9n('Password reset must be enabled in the configuration file'));
            $alert = $el->render();

            $el = null;

            /** @var Bootstrap\Row $el */
            $el = $this->di->get('Row');

            $el->column($alert, 'col-lg-4 offset-lg-4');
            $outer_row = $el->render();

            $el = null;

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->addClass('container-fluid');
            $el->html($bc . $outer_row);
            $container = $el->render();

            $el = null;

            $this->append($container);

        } else {

            /*
             * Form.
             */

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->name('username');
            $el->type('test');
            $el->label("{$this->lang->t9n('Username')} {$this->lang->t9n('or')} {$this->lang->t9n('email')}");
            $el->maxlength('256');
            $el->required('required');
            $username = $el->render();

            $el = null;

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->context('primary');
            $el->type('submit');
            $el->html($this->lang->t9n('Reset password'));
            $button = $el->render();

            $el = null;

            /** @var Bootstrap\Form $el */
            $el = $this->di->get('Form');

            $el->action(IL_BASE_URL . 'index.php/resetpassword/send');
            $el->autocomplete('off');
            $el->html("$username $button");
            $form = $el->render();

            $el = null;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->addClass('bg-white mt-4 mb-3');
            $el->body($form, null, 'pt-3');
            $card = $el->render();

            $el = null;

            /** @var Bootstrap\Alert $el */
            $el = $this->di->get('Alert');

            $el->addClass('mt-5');
            $el->context('danger');
            $el->html($this->lang->t9n('Disable password reset in the configuration file immediately after you are done'));
            $alert = $el->render();

            $el = null;

            // Top hierarchy elements.

            /** @var Bootstrap\Row $el */
            $el = $this->di->get('Row');

            $el->column($alert . $card, 'col-lg-4 offset-lg-4');
            $outer_row = $el->render();

            $el = null;

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->addClass('container-fluid');
            $el->html($bc . $outer_row);
            $container = $el->render();

            $el = null;

            $this->append($container);
        }

        /*
         * End.
         */

        $this->scriptLink('js/plugins.js');

        $this->script(<<<EOT
            $(function(){
                new ResetpasswordView();
            });
EOT
        );

        $this->end();

        return $this->send();
    }

    /**
     * @param string $password
     * @return string
     * @throws Exception
     */
    public function result(string $password): string {

        $this->title($this->lang->t9n('Reset password'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->item('IL', IL_BASE_URL);
        $el->item($this->lang->t9n('Reset password'));
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Alert $el */
        $el = $this->di->get('Alert');

        $el->addClass('mt-5');
        $el->context('danger');
        $el->html($this->lang->t9n('Disable password reset in the configuration file immediately after you are done'));
        $alert = $el->render();

        $el = null;

        $IL_BASE_URL = IL_BASE_URL;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('bg-white mt-4 mb-3');
        $el->body(
<<<HTML
<p>{$this->lang->t9n('Your temporary password is')}:</p>
<p><kbd id="new-password">{$password}</kbd></p>
1. {$this->lang->t9n('Copy the password to clipboard')}.<br>
2. <a href="{$IL_BASE_URL}">{$this->lang->t9n('Sign in')}.</a><br>
3. {$this->lang->t9n('Immediately change your password in user profile')}.
HTML
        , null, 'pt-3');
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($alert . $card, 'col-lg-4 offset-lg-4');
        $outer_row = $el->render();

        $el = null;

        $this->append(['html' => $bc . $outer_row]);

        return $this->send();
    }
}
