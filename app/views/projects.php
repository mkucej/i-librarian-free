<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
use Librarian\Media\Temporal;
use Librarian\Mvc\TextView;

class ProjectsView extends TextView {

    /**
     * @var Temporal
     */
    private $temporal;

    /**
     * Main.
     *
     * @param array $projects
     * @return string
     * @throws Exception
     */
    public function main(array $projects): string {

        $IL_BASE_URL = IL_BASE_URL;

        $this->temporal = $this->di->getShared('Temporal');

        $this->title($this->lang->t9n('Projects'));

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item($this->lang->t9n('Projects'));
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('name');
        $el->label($this->lang->t9n('Project name'));
        $el->required('required');
        $proj_name = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->context('info');
        $el->icon('help-circle');
        $el->tooltip($this->lang->t9n('Users can join this project freely'));
        $help = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->checked('checked');
        $el->id('project-access-1');
        $el->type('radio');
        $el->inline(true);
        $el->name('access');
        $el->value('open');
        $el->label($this->lang->t9n('open access'));
        $el->html("&nbsp;$help");
        $proj_access = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->context('info');
        $el->icon('help-circle');
        $el->tooltip($this->lang->t9n('You select members of this project'));
        $help = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('project-access-2');
        $el->type('radio');
        $el->inline(true);
        $el->name('access');
        $el->value('restricted');
        $el->label($this->lang->t9n('restricted access'));
        $el->html("&nbsp;$help");
        $proj_access .= $el->render();

        $el = null;

        $user_list = '';

        foreach ($projects['users'] as $user) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id("project-users-{$user['id_hash']}");
            $el->name('users[]');
            $el->type('checkbox');
            $el->value($user['id_hash']);
            $el->label($user['name']);
            $user_list .= $el->render();

            $el = null;
        }

        $users_card = '';

        if (count($projects['users']) > 0) {

            $users_card = "<div class=\"my-2\"><b>{$this->lang->t9n('Users with access')}</b></div>";

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
        $el->html($this->lang->t9n('Create'));
        $create = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('project-form');
        $el->action(IL_BASE_URL . 'index.php/projects/create');
        $el->html("$proj_name $proj_access <br> $users_card $create");
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header("<b>{$this->lang->t9n('Create new project')}</b>", 'text-uppercase');
        $el->body($form);
        $card_form = $el->render();

        $el = null;

        // Active projects.

        if (empty($projects['active_projects'])) {

            $list = "<small class=\"text-secondary text-uppercase\">{$this->lang->t9n('No active projects')}</small>";

        } else {

            $list = '';

            foreach ($projects['active_projects'] as $project) {

                $date = $this->temporal->toUserTime($project['added_time']);

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('inactivate my-2');
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $el->componentSize('small');
                $el->style('min-width: 6rem');
                $el->html($this->lang->t9n('Inactivate'));

                if ($this->session->data('user_id') !== $project['id_hash']) {

                    $el->context('outline-secondary');
                    $el->disabled('disabled');
                }

                $inactivate = $el->render();

                $el = null;

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('leave my-2');
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $el->style('min-width: 6rem');
                $el->componentSize('small');
                $el->html($this->lang->t9n('Leave'));

                if ($this->session->data('user_id') === $project['id_hash'] || $project['is_restricted'] === 'Y') {

                    $el->context('outline-secondary');
                    $el->disabled('disabled');
                }

                $leave = $el->render();

                $el = null;

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->addClass('my-2');
                $el->style('min-width: 6rem');
                $el->componentSize('small');
                $el->html($this->lang->t9n('Edit'));

                if ($this->session->data('user_id') !== $project['id_hash']) {

                    $el->context('outline-secondary');
                    $el->disabled('disabled');

                } else {

                    $el->elementName('a');
                    $el->context('outline-danger');
                    $el->href($IL_BASE_URL . "index.php/project#project/edit?id=" . $this->sanitation->attr($project['id']));
                }

                $edit = $el->render();

                $el = null;

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('delete my-2');
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $el->attr('data-body', 'Delete this project?');
                $el->style('min-width: 6rem');
                $el->componentSize('small');
                $el->html($this->lang->t9n('Delete'));

                if ($this->session->data('user_id') !== $project['id_hash']) {

                    $el->context('outline-secondary');
                    $el->disabled('disabled');
                }

                $delete = $el->render();

                $el = null;

                $list .= <<<ACTIVE
                    <div class="mb-2 active-project-container">
                        <h5><a class="active-project" href="{$IL_BASE_URL}index.php/project#project/browse?id={$project['id']}">{$project['project']}</a></h5>
                        <b>{$this->lang->t9n('Owner')}:</b> {$project['name']} &middot;
                        <b>{$this->lang->t9n('Created')}:</b> $date <br>
                        $inactivate $leave $edit $delete
                    </div>
ACTIVE;

            }
        }

        $filter_active = '';

        if (count($projects['active_projects']) > 4) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id("filter-active");
            $el->name('filter');
            $el->placeholder($this->lang->t9n('Filter-VERB'));
            $filter_active = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header("<b>{$this->lang->t9n('Active projects')}</b>", 'text-uppercase');
        $el->body($filter_active . $list);
        $card_ative = $el->render();

        $el = null;

        // Open projects.

        if (empty($projects['open_projects'])) {

            $list = "<small class=\"text-secondary text-uppercase\">{$this->lang->t9n('No open projects')}</small>";

        } else {

            $list = '';

            foreach ($projects['open_projects'] as $project) {

                $date = $this->temporal->toUserTime($project['added_time']);

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('join my-2');
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $el->style('min-width: 6rem');
                $el->componentSize('small');
                $el->html($this->lang->t9n('Join'));
                $join = $el->render();

                $el = null;

                $list .= <<<OPEN
                    <div class="mb-2 open-project-container">
                        <h5 class="open-project">{$project['project']}</h5>
                        <b>{$this->lang->t9n('Owner')}:</b> {$project['name']} &middot;
                        <b>{$this->lang->t9n('Created')}:</b> $date <br>
                        $join
                    </div>
OPEN;

            }
        }

        $filter_open = '';

        if (count($projects['open_projects']) > 4) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id("filter-open");
            $el->name('filter');
            $el->placeholder($this->lang->t9n('Filter-VERB'));
            $filter_open = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header("<b>{$this->lang->t9n('Open-access projects')}</b>", 'text-uppercase');
        $el->body($filter_open . $list);
        $card_open = $el->render();

        $el = null;

        // Inactive projects.

        if (empty($projects['inactive_projects'])) {

            $list = "<small class=\"text-secondary text-uppercase\">{$this->lang->t9n('No inactive projects')}</small>";

        } else {

            $list = '';

            foreach ($projects['inactive_projects'] as $project) {

                $date = $this->temporal->toUserTime($project['added_time']);

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('activate my-2');
                $el->style('min-width: 6rem');
                $el->componentSize('small');
                $el->html($this->lang->t9n('Activate'));
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $reactivate = $el->render();

                $el = null;

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('delete my-2');
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $el->attr('data-body', 'Delete this project?');
                $el->style('min-width: 6rem');
                $el->componentSize('small');
                $el->html($this->lang->t9n('Delete'));
                $delete = $el->render();

                $el = null;

                $list .= <<<INACTIVE
                    <div class="mb-2 inactive-project-container">
                        <h5><a class="inactive-project" href="{$IL_BASE_URL}index.php/project#project/browse?id={$project['id']}">{$project['project']}</a></h5>
                        <b>{$this->lang->t9n('Owner')}:</b> {$project['name']} &middot;
                        <b>{$this->lang->t9n('Created')}:</b> $date <br>
                        $reactivate $delete
                    </div>
INACTIVE;

            }
        }

        $filter_inactive = '';

        if (count($projects['inactive_projects']) > 4) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id("filter-inactive");
            $el->name('filter');
            $el->placeholder($this->lang->t9n('Filter-VERB'));
            $filter_inactive = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header("<b>{$this->lang->t9n('Inactive projects')}</b>", 'text-uppercase');
        $el->body($filter_inactive . $list);
        $card_inactive = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($card_form, 'mb-3 col-xl-4');
        $el->column($card_ative . $card_open . $card_inactive, 'mb-3 col-xl-8');

        $content = $el->render();

        $this->append(['html' => $bc . $content]);

        return $this->send();
    }
}
