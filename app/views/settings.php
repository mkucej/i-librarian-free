<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Mvc\TextView;

class SettingsView extends TextView {

    /**
     * Main.
     *
     * @param array $user_settings
     * @return string
     * @throws Exception
     */
    public function main(array $user_settings): string {

        $this->title('Settings');

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("Settings");
        $bc = $el->render();

        $el = null;

        $radios_l = '<div class="mt-3"><b>Theme</b></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('theme_light');
        $el->name('settings[theme]');
        $el->value('light');
        $el->label('light');

        if ($user_settings['theme'] === 'light') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('theme_dark');
        $el->name('settings[theme]');
        $el->value('dark');
        $el->label('dark');

        if ($user_settings['theme'] === 'dark') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /*
         * Sorting.
         */

        $radios_l .= '<div class="mt-3"><strong>Order items by</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('sorting_id');
        $el->name('settings[sorting]');
        $el->value('id');
        $el->label('addition date');

        if ($user_settings['sorting'] === 'id') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('sorting_pubdate');
        $el->name('settings[sorting]');
        $el->value('pubdate');
        $el->label('publication date');

        if ($user_settings['sorting'] === 'pubdate') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('sorting_title');
        $el->name('settings[sorting]');
        $el->value('title');
        $el->label('title');

        if ($user_settings['sorting'] === 'title') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /*
         * Items per page.
         */

        $radios_l .= '<div class="mt-3"><strong>Items per page</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size5');
        $el->name('settings[page_size]');
        $el->value('5');
        $el->label('5');

        if ($user_settings['page_size'] === '5') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size10');
        $el->name('settings[page_size]');
        $el->value('10');
        $el->label('10');

        if ($user_settings['page_size'] === '10') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size15');
        $el->name('settings[page_size]');
        $el->value('15');
        $el->label('15');

        if ($user_settings['page_size'] === '15') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size20');
        $el->name('settings[page_size]');
        $el->value('20');
        $el->label('20');

        if ($user_settings['page_size'] === '20') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size50');
        $el->name('settings[page_size]');
        $el->value('50');
        $el->label('50');

        if ($user_settings['page_size'] === '50') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size100');
        $el->name('settings[page_size]');
        $el->value('100');
        $el->label('100');

        if ($user_settings['page_size'] === '100') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /*
         * Display type.
         */

        $radios_l .= '<div class="mt-3"><strong>Display items as</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('display_title');
        $el->name('settings[display_type]');
        $el->value('title');
        $el->label('titles');

        if ($user_settings['display_type'] === 'title') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('display_summary');
        $el->name('settings[display_type]');
        $el->value('summary');
        $el->label('summaries');

        if ($user_settings['display_type'] === 'summary') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('display_icons');
        $el->name('settings[display_type]');
        $el->value('icon');
        $el->label('icons');

        if ($user_settings['display_type'] === 'icon') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /*
         * Icons per row.
         */

        $radios_l .= '<div class="mt-3"><strong>Icons per row</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row1');
        $el->name('settings[icons_per_row]');
        $el->value('1');
        $el->label('1');

        if ($user_settings['icons_per_row'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row2');
        $el->name('settings[icons_per_row]');
        $el->value('2');
        $el->label('2');

        if ($user_settings['icons_per_row'] === '2') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row3');
        $el->name('settings[icons_per_row]');
        $el->value('3');
        $el->label('3');

        if ($user_settings['icons_per_row'] === '3') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row4');
        $el->name('settings[icons_per_row]');
        $el->value('4');
        $el->label('4');

        if ($user_settings['icons_per_row'] === '4') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row5');
        $el->name('settings[icons_per_row]');
        $el->value('auto');
        $el->label('auto');

        if ($user_settings['icons_per_row'] === 'auto') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /*
         * PDF viewer.
         */

        $radios_l .= '<div class="mt-3"><strong>PDF viewer</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('pdf_viewer_internal');
        $el->name('settings[pdf_viewer]');
        $el->value('internal');
        $el->label('<i>I, Librarian</i> PDF viewer');

        if ($user_settings['pdf_viewer'] === 'internal') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('pdf_viewer_external');
        $el->name('settings[pdf_viewer]');
        $el->value('external');
        $el->label('web browser PDF plugin');

        if ($user_settings['pdf_viewer'] === 'external') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /*
         * Timezone.
         */

        /** @var Bootstrap\Typeahead $el */
        $el = $this->di->get('Typeahead');

        $el->id('timezones');
        $el->name('settings[timezone]');
        $el->value($user_settings['timezone']);
        $el->groupClass('my-3');
        $el->label('Timezone');
        $el->source(IL_BASE_URL . 'index.php/filter/timezone');
        $radios_l .= $el->render();

        $el = null;

        /*
         * Always use en_US locale.
         */

        $radios_l .= '<div class="mt-3"><strong>Always use English localization</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('use_en_language_yes');
        $el->name('settings[use_en_language]');
        $el->value('1');
        $el->label('yes');

        if ($user_settings['use_en_language'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('use_en_language_no');
        $el->name('settings[use_en_language]');
        $el->value('0');
        $el->label('no');

        if ($user_settings['use_en_language'] === '0') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /*
         * Custom PDF name.
         */

        $radios_l .= '<div class="mt-3 mb-2"><strong>Custom PDF download name</strong></div>';

        $name_columns = ['', 'author', 'id', 'publication', 'title', 'year'];
        $separators = ["", "' '", "-", "_"];

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_filename][0]');
        $el->style('width: 8rem');

        foreach ($name_columns as $value) {

            $el->option($value, $value, $user_settings['custom_filename'][0] === $value);
        }

        $filename1 = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_filename][1]');
        $el->style('width: 3.5rem');

        foreach ($separators as $value) {

            $el->option($value, $value, $user_settings['custom_filename'][1] === $value);
        }

        $filename2 = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_filename][2]');
        $el->style('width: 8rem');

        foreach ($name_columns as $value) {

            $el->option($value, $value, $user_settings['custom_filename'][2] === $value);
        }

        $filename3 = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_filename][3]');
        $el->style('width: 3.5rem');

        foreach ($separators as $value) {

            $el->option($value, $value, $user_settings['custom_filename'][3] === $value);
        }

        $filename4 = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_filename][4]');
        $el->style('width: 8rem');

        foreach ($name_columns as $value) {

            $el->option($value, $value, $user_settings['custom_filename'][4] === $value);
        }

        $filename5 = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($filename1, 'col-xl-auto pr-xl-0');
        $el->column($filename2, 'col-xl-auto px-xl-1');
        $el->column($filename3, 'col-xl-auto pl-xl-0 pr-xl-1');
        $el->column($filename4, 'col-xl-auto pl-xl-0 pr-xl-1');
        $el->column($filename5, 'col-xl-auto p-xl-0');
        $radios_l .= $el->render();

        $el = null;

        /*
         * Customize dashboard.
         */

        $radios_l .= '<div class="mt-3 mb-2"><strong>Hide dashboard panels</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('dashboard-search');
        $el->name('settings[dashboard_remove_search]');
        $el->value('1');
        $el->label('search');
        $el->inline(true);

        if (isset($user_settings['dashboard_remove_search']) && $user_settings['dashboard_remove_search'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('dashboard-stats');
        $el->name('settings[dashboard_remove_stats]');
        $el->value('1');
        $el->label('stats');
        $el->inline(true);

        if (isset($user_settings['dashboard_remove_stats']) && $user_settings['dashboard_remove_stats'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render() . '<br>';

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('dashboard-items');
        $el->name('settings[dashboard_remove_items]');
        $el->value('1');
        $el->label('items');
        $el->inline(true);

        if (isset($user_settings['dashboard_remove_items']) && $user_settings['dashboard_remove_items'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('dashboard-item-notes');
        $el->name('settings[dashboard_remove_item_notes]');
        $el->value('1');
        $el->label('item notes');
        $el->inline(true);

        if (isset($user_settings['dashboard_remove_item_notes']) && $user_settings['dashboard_remove_item_notes'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('dashboard-item-discussions');
        $el->name('settings[dashboard_remove_item_discussions]');
        $el->value('1');
        $el->label('item discussions');
        $el->inline(true);

        if (isset($user_settings['dashboard_remove_item_discussions']) && $user_settings['dashboard_remove_item_discussions'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render() . '<br>';

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('dashboard-projects');
        $el->name('settings[dashboard_remove_projects]');
        $el->value('1');
        $el->label('projects');
        $el->inline(true);

        if (isset($user_settings['dashboard_remove_projects']) && $user_settings['dashboard_remove_projects'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('dashboard-project-notes');
        $el->name('settings[dashboard_remove_project_notes]');
        $el->value('1');
        $el->label('project notes');
        $el->inline(true);

        if (isset($user_settings['dashboard_remove_project_notes']) && $user_settings['dashboard_remove_project_notes'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('dashboard-project-discussions');
        $el->name('settings[dashboard_remove_project_discussions]');
        $el->value('1');
        $el->label('project discussions');
        $el->inline(true);

        if (isset($user_settings['dashboard_remove_project_discussions']) && $user_settings['dashboard_remove_project_discussions'] === '1') {

            $el->checked('checked');
        }

        $radios_l .= $el->render();

        $el = null;

        /*
         * Connect to databases.
         */

        $radios_r = '<div class="mt-3 mb-2"><strong>Connect to external databases</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-arxiv');
        $el->name('settings[connect_arxiv]');
        $el->value('1');
        $el->label('arXiv');
        $el->hint('arXiv is a repository of over 1 million electronic preprints approved for posting after moderation, but not full peer review.');

        if ($user_settings['connect_arxiv'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-crossref');
        $el->name('settings[connect_crossref]');
        $el->value('1');
        $el->label('Crossref');
        $el->hint('Crossref is an official Digital Object Identifier Registration Agency containing over 100 million articles from academic journals.');

        if ($user_settings['connect_crossref'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-xplore');
        $el->name('settings[connect_xplore]');
        $el->value('1');
        $el->label('IEEE Xplore');
        $el->hint('IEEE Xplore is a research database containing over 5 million documents on computer science, electrical engineering and electronics.');

        if ($user_settings['connect_xplore'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-nasa');
        $el->name('settings[connect_nasa]');
        $el->value('1');
        $el->label('NASA ADS');
        $el->hint('NASA ADS is an online database of over 8 million astronomy and physics papers from both peer reviewed and non-peer reviewed sources.');

        if ($user_settings['connect_nasa'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-ol');
        $el->name('settings[connect_ol]');
        $el->value('1');
        $el->label('Open Library');
        $el->hint('Open Library provides online access to over 1 million of public domain and out-of-print books.');

        if ($user_settings['connect_ol'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-patents');
        $el->name('settings[connect_patents]');
        $el->value('1');
        $el->label('Patents (Pro only)');
        $el->hint('Patent database contains data on more than 110 million patent documents from around the world.');

        if ($user_settings['connect_patents'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-pubmed');
        $el->name('settings[connect_pubmed]');
        $el->value('1');
        $el->label('Pubmed');
        $el->hint('PubMed comprises more than 30 million citations for biomedical literature from MEDLINE, life science journals, and online books.');

        if ($user_settings['connect_pubmed'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-pmc');
        $el->name('settings[connect_pmc]');
        $el->value('1');
        $el->label('Pubmed Central');
        $el->hint('PubMed Central is a free digital repository that archives over 5 million publicly accessible full-text biomedical and life sciences articles.');

        if ($user_settings['connect_pmc'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-sciencedirect');
        $el->name('settings[connect_sciencedirect]');
        $el->value('1');
        $el->label('ScienceDirect (Pro only)');
        $el->hint('ScienceDirect is Elsevier\'s database containing over over 12 million pieces of content from 3,500 academic journals and 34,000 e-books.');

        if ($user_settings['connect_sciencedirect'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('connect-scopus');
        $el->name('settings[connect_scopus]');
        $el->value('1');
        $el->label('Scopus (Pro only)');
        $el->hint('Scopus is Elsevier\'s citation database containing over 69 million documents in life, health, physical and social sciences.');

        if ($user_settings['connect_scopus'] === '1') {

            $el->checked('checked');
        }

        $radios_r .= $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($radios_l);
        $el->column($radios_r);
        $radios = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html('Save');
        $submit = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-5');
        $el->body($radios);
        $el->footer($submit);
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('user-settings-form');
        $el->action(IL_BASE_URL . 'index.php/settings/update');
        $el->append($card);
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($form);
        $row = $el->render();

        $el = null;

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }

    /**
     * Display settings only for modal window.
     *
     * @param array $user_settings
     * @return string
     * @throws Exception
     */
    public function displaySettings(array $user_settings): string {

        /*
         * Sorting.
         */

        $output = '<strong>Order items by</strong><br>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('sorting_id');
        $el->name('settings[sorting]');
        $el->value('id');
        $el->label('addition date');

        if ($user_settings['sorting'] === 'id') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('sorting_pubdate');
        $el->name('settings[sorting]');
        $el->value('pubdate');
        $el->label('publication date');

        if ($user_settings['sorting'] === 'pubdate') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('sorting_title');
        $el->name('settings[sorting]');
        $el->value('title');
        $el->label('title');

        if ($user_settings['sorting'] === 'title') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /*
         * Items per page.
         */

        $output .= '<div class="mt-3"><strong>Items per page</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size5');
        $el->name('settings[page_size]');
        $el->value('5');
        $el->label('5');

        if ($user_settings['page_size'] === '5') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size10');
        $el->name('settings[page_size]');
        $el->value('10');
        $el->label('10');

        if ($user_settings['page_size'] === '10') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size15');
        $el->name('settings[page_size]');
        $el->value('15');
        $el->label('15');

        if ($user_settings['page_size'] === '15') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size20');
        $el->name('settings[page_size]');
        $el->value('20');
        $el->label('20');

        if ($user_settings['page_size'] === '20') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size50');
        $el->name('settings[page_size]');
        $el->value('50');
        $el->label('50');

        if ($user_settings['page_size'] === '50') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('page_size100');
        $el->name('settings[page_size]');
        $el->value('100');
        $el->label('100');

        if ($user_settings['page_size'] === '100') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /*
         * Display type.
         */

        $output .= '<div class="mt-3"><strong>Display items as</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('display_title');
        $el->name('settings[display_type]');
        $el->value('title');
        $el->label('titles');

        if ($user_settings['display_type'] === 'title') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('display_summary');
        $el->name('settings[display_type]');
        $el->value('summary');
        $el->label('summaries');

        if ($user_settings['display_type'] === 'summary') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('display_icons');
        $el->name('settings[display_type]');
        $el->value('icon');
        $el->label('icons');

        if ($user_settings['display_type'] === 'icon') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /*
         * Icons per row.
         */

        $output .= '<div class="mt-3"><strong>Icons per row</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row1');
        $el->name('settings[icons_per_row]');
        $el->value('1');
        $el->label('1');

        if ($user_settings['icons_per_row'] === '1') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row2');
        $el->name('settings[icons_per_row]');
        $el->value('2');
        $el->label('2');

        if ($user_settings['icons_per_row'] === '2') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row3');
        $el->name('settings[icons_per_row]');
        $el->value('3');
        $el->label('3');

        if ($user_settings['icons_per_row'] === '3') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row4');
        $el->name('settings[icons_per_row]');
        $el->value('4');
        $el->label('4');

        if ($user_settings['icons_per_row'] === '4') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('icons_per_row5');
        $el->name('settings[icons_per_row]');
        $el->value('auto');
        $el->label('auto');

        if ($user_settings['icons_per_row'] === 'auto') {

            $el->checked('checked');
        }

        $output .= $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('user-settings-form');
        $el->action(IL_BASE_URL . 'index.php/settings/update');
        $el->append($output);
        $form = $el->render();

        $el = null;

        $this->append(['html' => $form]);

        return $this->send();
    }

    /**
     * Global settings.
     *
     * @param array $settings
     * @return string
     * @throws Exception
     */
    public function global(array $settings): string {

        $this->title('Global settings');

        $this->head();

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->addClass('bg-transparent');
        $el->item('IL', '#dashboard');
        $el->item("Global settings");
        $bc = $el->render();

        $el = null;

        /*
         * Allow user registration.
         */

        $options_left = '<div class="pt-3"><b>User self-registration</b></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('registration-allow');
        $el->name('settings[disallow_signup]');
        $el->value('0');
        $el->label('allow');

        if ($settings['disallow_signup'] === '0') {

            $el->checked('checked');
        }

        $options_left .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('registration-disallow');
        $el->name('settings[disallow_signup]');
        $el->value('1');
        $el->label('disallow');

        if ($settings['disallow_signup'] === '1') {

            $el->checked('checked');
        }

        $options_left .= $el->render();

        $el = null;

        /*
         * Default permissions.
         */

        $options_left .= '<div class="mt-2"><strong>Default user permissions</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('default-admin');
        $el->name('settings[default_permissions]');
        $el->value('A');
        $el->label('administrator <span class="text-secondary">(adds, edits, deletes items; manages users)</span>');

        if ($settings['default_permissions'] === 'A') {

            $el->checked('checked');
        }

        $options_left .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('default-user');
        $el->name('settings[default_permissions]');
        $el->value('U');
        $el->label('user <span class="text-secondary">(adds, edits items; cannot delete items)</span>');

        if ($settings['default_permissions'] === 'U') {

            $el->checked('checked');
        }

        $options_left .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('default-guest');
        $el->groupClass('mb-3');
        $el->name('settings[default_permissions]');
        $el->value('G');
        $el->label('guest <span class="text-secondary">(cannot add, edit, or delete items)</span>');

        if ($settings['default_permissions'] === 'G') {

            $el->checked('checked');
        }

        $options_left .= $el->render();

        $el = null;

        for ($i = 1; $i <= 8; $i++) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->id('custom' . $i);
            $el->name('settings[custom' . $i . ']');
            $el->label('Custom ' . $i . ' name');

            if (!empty($settings['custom' . $i])) {

                $el->value($settings['custom' . $i]);
            }

            $options_left .= $el->render();

            $el = null;
        }

        /*
         * Custom Bibtex id.
         */

        $name_columns = ['', 'author', 'id', 'publication', 'title', 'year'];
        $separators = ['', '-', '_'];

        $options_left .= '<div class="mt-2 mb-2"><b>Custom citation/B<span style="font-size: 0.85rem">IB</span>T</b><div class="d-inline-block" style="transform: translateY(2px)"><b>E</b></div><b>X keys</b></div>';

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_bibtex][0]');
        $el->style('width: 8rem');

        foreach ($name_columns as $value) {

            $el->option($value, $value, $settings['custom_bibtex'][0] === $value);
        }

        $filename1 = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_bibtex][1]');
        $el->style('width: 3.5rem');

        foreach ($separators as $value) {

            $el->option($value, $value, $settings['custom_bibtex'][1] === $value);
        }

        $filename2 = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_bibtex][2]');
        $el->style('width: 8rem');

        foreach ($name_columns as $value) {

            $el->option($value, $value, $settings['custom_bibtex'][2] === $value);
        }

        $filename3 = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_bibtex][3]');
        $el->style('width: 3.5rem');

        foreach ($separators as $value) {

            $el->option($value, $value, $settings['custom_bibtex'][3] === $value);
        }

        $filename4 = $el->render();

        $el = null;

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->name('settings[custom_bibtex][4]');
        $el->style('width: 8rem');

        foreach ($name_columns as $value) {

            $el->option($value, $value, $settings['custom_bibtex'][4] === $value);
        }

        $filename5 = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($filename1, 'col-xl-auto pr-xl-0');
        $el->column($filename2, 'col-xl-auto px-xl-1');
        $el->column($filename3, 'col-xl-auto pl-xl-0 pr-xl-1');
        $el->column($filename4, 'col-xl-auto pl-xl-0 pr-xl-1');
        $el->column($filename5, 'col-xl-auto p-xl-0');
        $options_left .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('replace-bibtex-keys');
        $el->name('replace_bibtex_keys');
        $el->value('1');
        $el->label('replace existing keys');
        $options_left .= $el->render();

        $el = null;

        $options_left .= '<div class="mt-3 mb-2"><strong>Math display formatting</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('math-formatting-allow');
        $el->name('settings[math_formatting]');
        $el->value('1');
        $el->label('on');

        if (isset($settings['math_formatting']) && $settings['math_formatting'] === '1') {

            $el->checked('checked');
        }

        $options_left .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->inline(true);
        $el->id('math-formatting-disallow');
        $el->name('settings[math_formatting]');
        $el->value('0');
        $el->label('off');

        if (!isset($settings['math_formatting']) || isset($settings['math_formatting']) && $settings['math_formatting'] === '0') {

            $el->checked('checked');
        }

        $options_left .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('soffice-path');
        $el->groupClass('mt-3');
        $el->name('settings[soffice_path]');
        $el->label('LibreOffice path');
        $el->hint('Provide LibreOffice path, if it is not set in the Windows environment.');

        if (!empty($settings['soffice_path'])) {

            $el->value($settings['soffice_path']);
        }

        $options_right = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('tesseract-path');
        $el->name('settings[tesseract_path]');
        $el->label('Tesseract OCR path');
        $el->hint('Provide Tesseract path, if it is not set in the Windows environment.');

        if (!empty($settings['tesseract_path'])) {

            $el->value($settings['tesseract_path']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('api-ncbi');
        $el->groupClass('mt-3');
        $el->name('settings[api_crossref]');
        $el->label('Crossref API key');
        $el->hint('Crossref API key is your <a href="https://github.com/CrossRef/rest-api-doc">email address</a>. No registration required.');

        if (!empty($settings['api_crossref'])) {

            $el->value($settings['api_crossref']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('api-ncbi');
        $el->name('settings[api_ieee]');
        $el->label('IEEE Xplore API key');
        $el->hint('Get an API key from <a href="https://developer.ieee.org/">IEEE</a>.');

        if (!empty($settings['api_ieee'])) {

            $el->value($settings['api_ieee']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('api-ncbi');
        $el->name('settings[api_nasa]');
        $el->label('NASA ADS API key');
        $el->hint('Get an API key from <a href="https://ui.adsabs.harvard.edu/">NASA ADS</a>.');

        if (!empty($settings['api_nasa'])) {

            $el->value($settings['api_nasa']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('api-ncbi');
        $el->name('settings[api_ncbi]');
        $el->label('NCBI API key');
        $el->hint('Get an API key from <a href="https://www.ncbi.nlm.nih.gov/account/">NCBI</a>.');

        if (!empty($settings['api_ncbi'])) {

            $el->value($settings['api_ncbi']);
        }

        $options_right .= $el->render();

        $el = null;

        $options_right .= '<div class="mt-3 mb-2"><strong>Proxy server for outgoing requests</strong></div>';

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('connection-direct');
        $el->groupClass('mb-4');
        $el->name('settings[connection]');
        $el->value('direct');
        $el->label('direct connection to the Internet');

        if ($settings['connection'] === 'direct') {

            $el->checked('checked');
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('connection-url');
        $el->name('settings[connection]');
        $el->value('url');
        $el->label('proxy auto-config (PAC/WPAD)');

        if ($settings['connection'] === 'url') {

            $el->checked('checked');
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('wpad-url');
        $el->groupClass('mt-2 mb-4');
        $el->name('settings[wpad_url]');
        $el->label('PAC/WPAD URL');
        $el->placeholder('Example: http://wpad.example.com/wpad.dat');

        if (!empty($settings['wpad_url'])) {

            $el->value($settings['wpad_url']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('proxy-pac');
        $el->name('settings[proxy_pac]');
        $el->type('hidden');

        if (!empty($settings['proxy_pac'])) {

            $el->value($settings['proxy_pac']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('radio');
        $el->id('connection-manual');
        $el->name('settings[connection]');
        $el->value('manual');
        $el->label('manual configuration');

        if ($settings['connection'] === 'manual') {

            $el->checked('checked');
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('manual-host');
        $el->name('settings[proxy_name]');
        $el->label('Proxy host name');

        if (!empty($settings['proxy_name'])) {

            $el->value($settings['proxy_name']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('manual-port');
        $el->name('settings[proxy_port]');
        $el->label('Proxy host port');

        if (!empty($settings['proxy_port'])) {

            $el->value($settings['proxy_port']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('manual-username');
        $el->name('settings[proxy_username]');
        $el->label('Proxy username');

        if (!empty($settings['proxy_username'])) {

            $el->value($settings['proxy_username']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('password');
        $el->id('manual-password');
        $el->name('settings[proxy_password]');
        $el->label('Proxy password');
        $el->hint('Warning! Proxy password is stored and sent to the proxy server unencrypted. Only use in local network.');

        if (!empty($settings['proxy_password'])) {

            $el->value($settings['proxy_password']);
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->id('proxy-auth-ntlm');
        $el->name('settings[proxy_auth]');
        $el->value('ntlm');
        $el->label('NTLM authentication');

        if (isset($settings['proxy_auth']) && $settings['proxy_auth'] === 'ntlm') {

            $el->checked('checked');
        }

        $options_right .= $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($options_left, 'col-xl-6');
        $el->column($options_right, 'col-xl-6');
        $card_row = $el->render();

        $el = null;

        /*
         * Submit button.
         */

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html('Save');
        $submit = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('mb-5');
        $el->body($card_row);
        $el->footer($submit);
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('settings-form');
        $el->action(IL_BASE_URL . 'index.php/globalsettings/update');
        $el->append($card);
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($form, 'col');
        $row = $el->render();

        $el = null;

        $this->append(['html' => "$bc $row"]);

        return $this->send();
    }
}
