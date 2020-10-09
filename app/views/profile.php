<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class ProfileView extends TextView {

    /**
     * Main.
     *
     * @param array $user_profile
     * @return string
     * @throws Exception
     */
    public function main(array $user_profile): string {

        $this->title($this->lang->t9n('User profile'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('User profile'));
        $bc = $el->render();

        $el = null;

        /*
         * SSO view.
         */

        if ($this->app_settings->getIni('ldap', 'ldap_active') === '1') {

            $username = !empty($user_profile['username']) ? $user_profile['username'] : '&nbsp;';
            $first_name = !empty($user_profile['first_name']) ? $user_profile['first_name'] : '&nbsp;';
            $last_name = !empty($user_profile['last_name']) ? $user_profile['last_name'] : '&nbsp;';
            $email = !empty($user_profile['email']) ? $user_profile['email'] : '&nbsp;';

            /** @var Bootstrap\Descriptionlist $el */
            $el = $this->di->get('Descriptionlist');

            $el->term("<b>{$this->lang->t9n('Username')}</b>", 'col-sm-3');
            $el->description($username, 'col-sm-9');
            $el->term("<b>{$this->lang->t9n('First name')}</b>", 'col-sm-3');
            $el->description($first_name, 'col-sm-9');
            $el->term("<b>{$this->lang->t9n('Last name')}</b>", 'col-sm-3');
            $el->description($last_name, 'col-sm-9');
            $el->term("<b>{$this->lang->t9n('Email')}</b>", 'col-sm-3');
            $el->description($email, 'col-sm-9');
            $profile = $el->render();

            $el = null;

            /** @var Bootstrap\Card $el */
            $el = $this->di->get('Card');

            $el->header("<b>{$this->lang->t9n('User profile')}</b>", 'text-uppercase');
            $el->body($profile);
            $card = $el->render();

            $el = null;

            /** @var Bootstrap\Row $el */
            $el = $this->di->get('Row');

            $el->column("$card", 'col-lg-6 offset-lg-3 mb-3');
            $row = $el->render();

            $el = null;

            $this->append(['html' => "$bc $row"]);

            return $this->send();
        }

        /*
         * Non-LDAP view. Profile form.
         */

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('username');
        $el->name('profile[username]');
        $el->value($user_profile['username']);
        $el->required('required');
        $el->maxlength('256');
        $el->label($this->lang->t9n('Username'));
        $inputs = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('email');
        $el->name('profile[email]');
        $el->required('required');
        $el->value($user_profile['email']);
        $el->maxlength('256');
        $el->label($this->lang->t9n('Email'));
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('first_name');
        $el->name('profile[first_name]');
        $el->value($user_profile['first_name']);
        $el->maxlength('256');
        $el->label($this->lang->t9n('First name'));
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('last_name');
        $el->name('profile[last_name]');
        $el->value($user_profile['last_name']);
        $el->maxlength('256');
        $el->label($this->lang->t9n('Last name'));
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html($this->lang->t9n('Save'));
        $submit = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->header("<b>{$this->lang->t9n('Update profile')}</b>", 'text-uppercase');
        $el->body($inputs);
        $el->footer($submit);
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('user-profile-form');
        $el->action(IL_BASE_URL . 'index.php/profile/update');
        $el->html($card);
        $form = $el->render();

        $el = null;

        /*
         * Update password form.
         */

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('old-password');
        $el->name('profile[old_password]');
        $el->type('password');
        $el->label($this->lang->t9n('Current password'));
        $el->required('required');
        $el->maxlength('256');
        $inputs = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('new-password1');
        $el->name('profile[new_password]');
        $el->type('password');
        $el->label($this->lang->t9n('New password'));
        $el->tooltip($this->lang->t9n('Password must be at least 8 characters long'));
        $el->required('required');
        $el->maxlength('256');
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('new-password2');
        $el->name('profile[new_password2]');
        $el->type('password');
        $el->label($this->lang->t9n('Re-type new password'));
        $el->required('required');
        $el->maxlength('256');
        $inputs .= $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('reset');
        $el->addClass('ml-2');
        $el->context('secondary');
        $el->html($this->lang->t9n('Clear'));
        $reset = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('h-100');
        $el->header("<b>{$this->lang->t9n('Update password')}</b>", 'text-uppercase');
        $el->body($inputs);
        $el->footer($submit . $reset);
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->addClass('h-100');
        $el->id('user-profile-change-password');
        $el->action(IL_BASE_URL . 'index.php/profile/changepassword');
        $el->html($card);
        $form2 = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column("$form", 'col-lg-5 offset-lg-1 mb-3');
        $el->column("$form2", 'col-lg-5 mb-3');
        $row = $el->render();

        $el = null;

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }
}
