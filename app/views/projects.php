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

        $this->title('Projects');

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("Projects");
        $bc = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('name');
        $el->label('Project name');
        $el->required('required');
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

        $el->checked('checked');
        $el->id('project-access-1');
        $el->type('radio');
        $el->inline(true);
        $el->name('access');
        $el->value('open');
        $el->label("open access");
        $el->html("&nbsp;$help");
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
        $el->html('Create');
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
        $el->header('<b>CREATE NEW PROJECT</b>');
        $el->body($form);
        $card_form = $el->render();

        $el = null;

        // Active projects.

        if (empty($projects['active_projects'])) {

            $list = '<small class="text-secondary text-uppercase">No active projects</small>';

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
                $el->style('width: 6rem');
                $el->html('Inactivate');

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
                $el->style('width: 6rem');
                $el->componentSize('small');
                $el->html('Leave');

                if ($this->session->data('user_id') === $project['id_hash'] || $project['is_restricted'] === 'Y') {

                    $el->context('outline-secondary');
                    $el->disabled('disabled');
                }

                $leave = $el->render();

                $el = null;

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('delete my-2');
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $el->attr('data-body', 'Delete this project?');
                $el->style('width: 6rem');
                $el->componentSize('small');
                $el->html('Delete');

                if ($this->session->data('user_id') !== $project['id_hash']) {

                    $el->context('outline-secondary');
                    $el->disabled('disabled');
                }

                $delete = $el->render();

                $el = null;

                $list .= <<<ACTIVE
                    <div class="mb-2 active-project-container">
                        <h5><a class="active-project" href="{$IL_BASE_URL}index.php/project#project/browse?id={$project['id']}">{$project['project']}</a></h5>
                        <b>Owner:</b> {$project['name']} &middot; <b>Created:</b> $date <br>
                        $inactivate $leave $delete
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
            $el->placeholder('Filter');
            $filter_active = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header('<b>ACTIVE PROJECTS</b>');
        $el->body($filter_active . $list);
        $card_ative = $el->render();

        $el = null;

        // Open projects.

        if (empty($projects['open_projects'])) {

            $list = '<small class="text-secondary text-uppercase">No open projects</small>';

        } else {

            $list = '';

            foreach ($projects['open_projects'] as $project) {

                $date = $this->temporal->toUserTime($project['added_time']);

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('join my-2');
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $el->style('width: 6rem');
                $el->componentSize('small');
                $el->html('Join');
                $join = $el->render();

                $el = null;

                $list .= <<<OPEN
                    <div class="mb-2 open-project-container">
                        <h5 class="open-project">{$project['project']}</h5>
                        <b>Owner:</b> {$project['name']} &middot; <b>Created:</b> $date <br>
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
            $el->placeholder('Filter');
            $filter_open = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header('<b>OPEN-ACCESS PROJECTS</b>');
        $el->body($filter_open . $list);
        $card_open = $el->render();

        $el = null;

        // Inactive projects.

        if (empty($projects['inactive_projects'])) {

            $list = '<small class="text-secondary text-uppercase">No inactive projects</small>';

        } else {

            $list = '';

            foreach ($projects['inactive_projects'] as $project) {

                $date = $this->temporal->toUserTime($project['added_time']);

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('activate my-2');
                $el->style('width: 6rem');
                $el->componentSize('small');
                $el->html('Activate');
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $reactivate = $el->render();

                $el = null;

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('outline-danger');
                $el->addClass('delete my-2');
                $el->attr('data-project-id', $this->sanitation->attr($project['id']));
                $el->attr('data-body', 'Delete this project?');
                $el->style('width: 6rem');
                $el->componentSize('small');
                $el->html('Delete');
                $delete = $el->render();

                $el = null;

                $list .= <<<INACTIVE
                    <div class="mb-2 inactive-project-container">
                        <h5><a class="inactive-project" href="{$IL_BASE_URL}index.php/project#project/browse?id={$project['id']}">{$project['project']}</a></h5>
                        <b>Owner:</b> {$project['name']} &middot; <b>Created:</b> $date <br>
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
            $el->placeholder('Filter');
            $filter_inactive = $el->render();

            $el = null;
        }

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-3');
        $el->header('<b>INACTIVE PROJECTS</b>');
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
