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

        $this->title('User list');

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("User list");
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Table $el */
        $el = $this->di->get('Table');

        $el->addClass("table-hover");

        $el->head([
            [' '],
            ['Username'],
            ['First&nbsp;name'],
            ['Last&nbsp;name'],
            ['Email<span class="text-danger">*</span>'],
            ['Permissions'],
            ['Status'],
            [' '],
            [' '],
            ['Created']
        ]);

        /** @var Bootstrap\Input $in */
        $in = $this->di->get('Input');

        $in->groupClass('m-0');
        $in->addClass("username");
        $in->style('min-width: 6rem');
        $in->placeholder('Username');
        $username = $in->render();

        $in = null;

        /** @var Bootstrap\Input $in */
        $in = $this->di->get('Input');

        $in->groupClass('m-0');
        $in->addClass("first-name");
        $in->style('min-width: 6rem');
        $in->placeholder('First name');
        $first = $in->render();

        $in = null;

        /** @var Bootstrap\Input $in */
        $in = $this->di->get('Input');

        $in->groupClass('m-0');
        $in->addClass("last-name");
        $in->style('min-width: 6rem');
        $in->placeholder('Last name');
        $last = $in->render();

        $in = null;

        /** @var Bootstrap\Input $in */
        $in = $this->di->get('Input');

        $in->groupClass('m-0');
        $in->addClass("email");
        $in->style('min-width: 6rem');
        $in->placeholder('Email');
        $email = $in->render();

        $in = null;

        /** @var Bootstrap\Select $sel */
        $sel = $this->di->get('Select');

        $sel->groupClass('m-0');
        $sel->style('min-width: 6rem');
        $sel->addClass("permissions");

        foreach (['A' => 'admin', 'U' => 'user', 'G' => 'guest'] as $short => $long) {

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

        foreach (['P' => 'pending', 'A' => 'active', 'S' => 'suspended', 'D' => 'deleted'] as $short => $long) {

            $selected = 'A' === $short ? true : false;

            $sel->option($long, $short, $selected);
        }

        $status = $sel->render();

        $sel = null;

        /** @var Bootstrap\Button $btn */
        $btn = $this->di->get('Button');

        $btn->context('danger');
        $btn->id('create-user');
        $btn->html('Save');
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

            $in->groupClass('m-0');
            $in->addClass("first-name");
            $in->value($first_name);
            $in->placeholder('First name');
            $first = $in->render();

            $in = null;

            $last_name = $this->sanitation->attr($this->sanitation->lmth($user['last_name']));

            /** @var Bootstrap\Input $in */
            $in = $this->di->get('Input');

            $in->groupClass('m-0');
            $in->addClass("last-name");
            $in->value($last_name);
            $in->placeholder('Last name');
            $last = $in->render();

            $in = null;

            $email = $this->sanitation->attr($this->sanitation->lmth($user['email']));

            /** @var Bootstrap\Input $in */
            $in = $this->di->get('Input');

            $in->groupClass('m-0');
            $in->addClass("email");
            $in->value($email);
            $in->placeholder('Email');
            $email = $in->render();

            $in = null;

            /** @var Bootstrap\Select $sel */
            $sel = $this->di->get('Select');

            $sel->groupClass('m-0');
            $sel->addClass("permissions");

            foreach (['A' => 'admin', 'U' => 'user', 'G' => 'guest'] as $short => $long) {

                $selected = $user['permissions'] === $short ? true : false;

                $sel->option($long, $short, $selected);
            }

            $permissions = $sel->render();

            $sel = null;

            /** @var Bootstrap\Select $sel */
            $sel = $this->di->get('Select');

            $sel->groupClass('m-0');
            $sel->addClass("status");

            foreach (['A' => 'active', 'S' => 'suspended', 'D' => 'deleted'] as $short => $long) {

                $selected = $user['status'] === $short ? true : false;

                $sel->option($long, $short, $selected);
            }

            $status = $sel->render();

            $sel = null;

            /** @var Bootstrap\Button $btn */
            $btn = $this->di->get('Button');

            $btn->context('danger');
            $btn->addClass('update-user');
            $btn->html('Save');

            if ($user['id_hash'] === $this->session->data('user_id')) {

                $btn->disabled('disabled');
            }

            $save = $btn->render();

            $btn = null;

            /** @var Bootstrap\Button $btn */
            $btn = $this->di->get('Button');

            $btn->context('danger');
            $btn->addClass('reset-password');
            $btn->dataTitle('Reset password?');
            $btn->dataBody('Are you sure you want to reset this user\'s password?');
            $btn->html('Reset&nbsp;password');

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

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->icon('alert');
        $icon = $el->render();

        $el = null;

        $warning = "<p class=\"text-danger\">$icon Users, whose data you change, will be logged out, potentially loosing their work in progress.</p>";

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

        // Email message.
        $IL_BASE_URL = IL_BASE_URL;

        $subject = rawurlencode("New account at $IL_BASE_URL");

        $body = rawurlencode(<<<MESSAGE
Hello {$user['username']}:

Your account at $IL_BASE_URL was created with a username:

{$user['username']}
 
Your temporary password is:

{$user['password']}

Please login and change your password as soon as possible.

Best regards,
Administrator
MESSAGE
        );

        $msg = <<<MESSAGE
New user's temporary password is:<br>
{$user['password']}<br>
<a href="mailto:{$user['email']}?subject=$subject&body=$body">Click here to send an email.</a>
MESSAGE;

        $this->append(['info' => 'New user was created.', 'password' => $msg]);

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

        $subject = rawurlencode("Password reset at $IL_BASE_URL");

        $body = rawurlencode(<<<MESSAGE
Hello {$user['username']}:

Your account password at $IL_BASE_URL was reset to:

{$user['password']}

Please login and change your password as soon as possible.

Best regards,
Administrator
MESSAGE
        );

        $msg = <<<MESSAGE
User's new temporary password is:<br>
{$user['password']}<br>
<a href="mailto:{$user['email']}?subject=$subject&body=$body">Click here to send an email.</a>
MESSAGE;

        $this->append(['info' => 'New user was created.', 'password' => $msg]);

        return $this->send();
    }
}
