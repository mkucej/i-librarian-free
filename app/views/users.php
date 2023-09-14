<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Media\Temporal;
use Librarian\Mvc\TextView;

class UsersView extends TextView {

    /**
     * @var Temporal
     */
    private $temporal;

    /**
     * Main.
     *
     * @param array $users
     * @return string
     * @throws Exception
     */
    public function main(array $users): string {

        $this->temporal = $this->di->get('Temporal');

        $this->title($this->lang->t9n('User management'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('User management'));
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Alert $el */
        $el = $this->di->get('Alert');

        $el->context('danger');
        $el->html($this->lang->t9n('Users whose data you change will be logged out, potentially losing their work in progress'));
        $warning = $el->render();

        $el = null;

        /** @var Bootstrap\Table $el */
        $el = $this->di->get('Table');

        $el->addClass("table-hover");

        $el->head([
            [' '],
            [$this->lang->t9n('Username')],
            [$this->lang->t9n('First name')],
            [$this->lang->t9n('Last name')],
            ["{$this->lang->t9n('Email')}<span class=\"text-danger\">*</span>"],
            [$this->lang->t9n('Permissions')],
            [$this->lang->t9n('Status')],
            [' '],
            [' '],
            [$this->lang->t9n('Created')]
        ]);

        /** @var Bootstrap\Input $in */
        $in = $this->di->get('Input');

        $in->groupClass('m-0');
        $in->addClass("username");
        $in->style('min-width: 6rem');
        $in->placeholder($this->lang->t9n('Username'));
        $username = $in->render();

        $in = null;

        /** @var Bootstrap\Input $in */
        $in = $this->di->get('Input');

        $in->style('max-width: 10rem;min-width: 6rem');
        $in->groupClass('m-0');
        $in->addClass("first-name");
        $in->placeholder($this->lang->t9n('First name'));
        $first = $in->render();

        $in = null;

        /** @var Bootstrap\Input $in */
        $in = $this->di->get('Input');

        $in->style('max-width: 10rem;min-width: 6rem');
        $in->groupClass('m-0');
        $in->addClass("last-name");
        $in->placeholder($this->lang->t9n('Last name'));
        $last = $in->render();

        $in = null;

        /** @var Bootstrap\Input $in */
        $in = $this->di->get('Input');

        $in->groupClass('m-0');
        $in->addClass("email");
        $in->style('min-width: 6rem');
        $in->placeholder($this->lang->t9n('Email'));
        $email = $in->render();

        $in = null;

        /** @var Bootstrap\Select $sel */
        $sel = $this->di->get('Select');

        $sel->groupClass('m-0');
        $sel->style('min-width: 6rem');
        $sel->addClass("permissions");

        foreach (['A' => $this->lang->t9n('admin'), 'U' => $this->lang->t9n('user'), 'G' => $this->lang->t9n('guest')] as $short => $long) {

            $selected = $this->app_settings->getGlobal('default_permissions') === $short ? true : false;

            $sel->option($long, $short, $selected);
        }

        $permissions = $sel->render();

        $sel = null;

        /** @var Bootstrap\Select $sel */
        $sel = $this->di->get('Select');

        $sel->groupClass('m-0');
        $sel->style('min-width: 6rem');
        $sel->disabled('disabled');

        foreach (['P' => $this->lang->t9n('pending'), 'A' => $this->lang->t9n('active'), 'S' => $this->lang->t9n('suspended'), 'D' => $this->lang->t9n('deleted')] as $short => $long) {

            $selected = 'A' === $short ? true : false;

            $sel->option($long, $short, $selected);
        }

        $status = $sel->render();

        $sel = null;

        /** @var Bootstrap\Button $btn */
        $btn = $this->di->get('Button');

        $btn->context('danger');
        $btn->id('create-user');
        $btn->html($this->lang->t9n('Save'));
        $save = $btn->render();

        $btn = null;

        $el->bodyRow([
            ['New'],
            [$username],
            [$first],
            [$last],
            [$email],
            [$permissions],
            [$status],
            [$save],
            [''],
            ['']
        ]);

        foreach ($users as $key => $user) {

            /** @var Bootstrap\Icon $el Admin icon. */
            $icon = $this->di->get('Icon');

            $icon->addClass("mdi-18px mr-2");

            if ($user['permissions'] !== 'A') {

                $icon->style("opacity: 0");
            }

            $icon->icon('shield-account');
            $admin_icon = $icon->render();

            $icon = null;

            $username = $this->sanitation->attr($this->sanitation->lmth($user['username']));
            $first_name = $this->sanitation->attr($this->sanitation->lmth($user['first_name']));

            /** @var Bootstrap\Input $in */
            $in = $this->di->get('Input');

            $in->style('max-width: 10rem');
            $in->groupClass('m-0');
            $in->addClass("first-name");
            $in->value($first_name);
            $in->placeholder($this->lang->t9n('First name'));
            $first = $in->render();

            $in = null;

            $last_name = $this->sanitation->attr($this->sanitation->lmth($user['last_name']));

            /** @var Bootstrap\Input $in */
            $in = $this->di->get('Input');

            $in->style('max-width: 10rem');
            $in->groupClass('m-0');
            $in->addClass("last-name");
            $in->value($last_name);
            $in->placeholder($this->lang->t9n('Last name'));
            $last = $in->render();

            $in = null;

            $email = $this->sanitation->attr($this->sanitation->lmth($user['email']));

            /** @var Bootstrap\Input $in */
            $in = $this->di->get('Input');

            $in->groupClass('m-0');
            $in->addClass("email");
            $in->value($email);
            $in->placeholder($this->lang->t9n('Email'));
            $email = $in->render();

            $in = null;

            /** @var Bootstrap\Select $sel */
            $sel = $this->di->get('Select');

            $sel->groupClass('m-0');
            $sel->addClass("permissions");

            foreach (['A' => $this->lang->t9n('admin'), 'U' => $this->lang->t9n('user'), 'G' => $this->lang->t9n('guest')] as $short => $long) {

                $selected = $user['permissions'] === $short ? true : false;

                $sel->option($long, $short, $selected);
            }

            $permissions = $sel->render();

            $sel = null;

            /** @var Bootstrap\Select $sel */
            $sel = $this->di->get('Select');

            $sel->groupClass('m-0');
            $sel->addClass("status");

            foreach (['A' => $this->lang->t9n('active'), 'S' => $this->lang->t9n('suspended'), 'D' => $this->lang->t9n('deleted')] as $short => $long) {

                $selected = $user['status'] === $short ? true : false;

                $sel->option($long, $short, $selected);
            }

            $status = $sel->render();

            $sel = null;

            /** @var Bootstrap\Button $btn */
            $btn = $this->di->get('Button');

            $btn->context('danger');
            $btn->addClass('update-user');
            $btn->html($this->lang->t9n('Save'));

            if ($user['id_hash'] === $this->session->data('user_id')) {

                $btn->disabled('disabled');
            }

            $save = $btn->render();

            $btn = null;

            /** @var Bootstrap\Button $btn */
            $btn = $this->di->get('Button');

            $btn->context('danger');
            $btn->addClass('reset-password');
            $btn->dataTitle($this->lang->t9n('Reset password'));
            $btn->dataBody($this->lang->t9n('Are you sure you want to reset this user\'s password?'));
            $btn->dataButton($this->lang->t9n('Yes-NOUN'));
            $btn->html($this->lang->t9n('Reset password'));

            if ($user['id_hash'] === $this->session->data('user_id')) {

                $btn->disabled('disabled');
            }

            $reset = $btn->render();

            $btn = null;

            $created = $this->temporal->toUserDate($user['added_time']);

            switch ($user['status']) {

                case 'D':
                    $row_class = 'table-danger';
                    break;

                case 'S':
                    $row_class = 'table-warning';
                    break;

                default:
                    $row_class = '';
            }

            $el->bodyRow([
                [$key + 1],
                ["$admin_icon<span class='username'>$username</span>", 'class="pl-0"'],
                [$first],
                [$last],
                [$email],
                [$permissions],
                [$status],
                [$save],
                [$reset],
                [$created, 'style="white-space: nowrap"']
            ], $row_class);
        }

        $table = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column("$warning<div class=\"table-responsive-lg\">$table</div>", 'col');
        $row = $el->render();

        $el = null;

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }

    /**
     * User was created message.
     *
     * @param array $user
     * @return string
     * @throws Exception
     */
    public function userCreated(array $user): string {

        $first_line = sprintf($this->lang->t9n('Your account at %s was created with a username'), IL_BASE_URL);

        $subject = rawurlencode($this->lang->t9n('New I, Librarian account'));

        $body = rawurlencode(<<<MESSAGE
{$this->lang->t9n('Hello')} {$user['username']}:

{$first_line}:

{$user['username']}
 
{$this->lang->t9n('Your temporary password is')}:

{$user['password']}

{$this->lang->t9n('Please login and change your password as soon as possible')}.

{$this->lang->t9n('Best regards')},
{$this->lang->t9n('Administrator')}
MESSAGE
        );

        $msg = <<<MESSAGE
{$this->lang->t9n('New user\'s temporary password is')}:<br>
{$user['password']}<br>
<a href="mailto:{$user['email']}?subject=$subject&body=$body">{$this->lang->t9n('Click here to send an email')}.</a>
MESSAGE;

        $this->append(['info' => $this->lang->t9n('New user was created'), 'password' => $msg]);

        return $this->send();
    }

    /**
     * User password was reset.
     *
     * @param array $user
     * @return string
     * @throws Exception
     */
    public function userReset(array $user): string {

        // Email message.
        $IL_BASE_URL = IL_BASE_URL;

        $first_line = sprintf($this->lang->t9n('Your account password at %s was reset to'), IL_BASE_URL);

        $subject = rawurlencode($this->lang->t9n('I, Librarian password reset'));

        $body = rawurlencode(<<<MESSAGE
{$this->lang->t9n('Hello')} {$user['username']}:

{$first_line}:

{$user['password']}

{$this->lang->t9n('Please login and change your password as soon as possible')}.

{$this->lang->t9n('Best regards')},
{$this->lang->t9n('Administrator')}
MESSAGE
        );

        $msg = <<<MESSAGE
{$this->lang->t9n('User\'s temporary password is')}:<br>
{$user['password']}<br>
<a href="mailto:{$user['email']}?subject=$subject&body=$body">{$this->lang->t9n('Click here to send an email')}.</a>
MESSAGE;

        $this->append(['info' => $this->lang->t9n('Password was reset'), 'password' => $msg]);

        return $this->send();
    }
}
