<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
use Librarian\Mvc\TextView;

class PdfViewerView extends TextView {

    /**
     * Main.
     *
     * @param int $item_id
     * @param array $info
     * @return string
     * @throws Exception
     */
    public function main(int $item_id, array $info): string {

        $this->title('PDF - ' . $info['title']);

        // No PDF.
        if (array_key_exists('page_count', $info['pdf_info']) === false) {

            /** @var Bootstrap\Alert $el */
            $el = $this->di->get('Alert');

            $el->style('margin: 5rem 25%');
            $el->context('primary');
            $el->html($this->lang->t9n('There is no PDF'));
            $alert = $el->render();

            $el = null;

            if ($this->contentType() === 'html') {

                $this->styleLink('css/plugins.css');
                $this->head();
                $this->append($alert);
                $this->scriptLink('js/plugins.min.js');
                $this->end();

            } elseif ($this->contentType() === 'json') {

                $this->head();
                $this->append(['html' => $alert]);
            }

            return $this->send();
        }

        // Left panel toggle.

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('dark');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-left-btn');
        $el->icon('page-layout-sidebar-left');
        $el->title($this->lang->t9n('Sidebar'));
        $left_toggle_btn = $el->render();

        $el = null;

        // Download PDF.

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->inline(true);
        $el->name('annotations');
        $el->value('1');
        $el->label($this->lang->t9n('add annotations'));
        $add_annotations = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('checkbox');
        $el->inline(true);
        $el->name('supplements');
        $el->value('1');
        $el->label($this->lang->t9n('add supplements'));
        $add_supplements = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('id');
        $el->value($item_id);
        $hidden = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('disposition');
        $el->value('attachment');
        $hidden .= $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('primary');
        $el->addClass('d-block mt-3');
        $el->type('submit');
        $el->html($this->lang->t9n('Download'));
        $download_btn = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->addClass('px-4 py-2');
        $el->style('min-width: 14rem');
        $el->method('GET');
        $el->action(IL_BASE_URL . 'index.php/pdf/file');
        $el->append($add_annotations);
        $el->append($add_supplements);
        $el->append($hidden);
        $el->append($download_btn);
        $form = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-18px');
        $el->icon('content-save');
        $download_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Dropdown $el */
        $el = $this->di->get('Dropdown');

        $el->context('dark');
        $el->addClass('d-inline');
        $el->label($download_icon);
        $el->span("<b>{$this->lang->t9n('Download')}</b>");
        $el->form($form);
        $save_btn = $el->render();

        $el = null;

        // PDF search.

        $search_input =
<<<HTML
<input
    type="text"
    id="pdfviewer-search-input"
    class="form-control bg-light border-0 d-inline rounded-0 px-2 py-0"
    style="transform: translateY(2px);height: 2.1rem;width: 15rem"
    placeholder="{$this->lang->t9n('Search-VERB')}">
HTML;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('dark');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-result-down');
        $el->icon('chevron-down');
        $el->title($this->lang->t9n('Next search result'));
        $down_btn = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('dark');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-result-up');
        $el->icon('chevron-up');
        $el->title($this->lang->t9n('Previous search result'));
        $up_btn = $el->render();

        $el = null;

        // Annotations.

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-18px');
        $el->icon('message-draw');
        $annot_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->id('pdfviewer-underlined');
        $el->type('checkbox');
        $el->inline(true);
        $el->name('underline');
        $el->value('1');
        $el->label($this->lang->t9n('underlined highlights'));
        $underlined = $el->render();

        $el = null;

        /** @var Bootstrap\Dropdown $el */
        $el = $this->di->get('Dropdown');

        $el->id('pdfviewer-annot-menu');
        $el->context('dark');
        $el->addClass('d-inline');
        $el->label($annot_icon);
        $el->button($this->lang->t9n('Show annotations'), 'annot-show');
        $el->button($this->lang->t9n('Hide annotations'), 'annot-hide');
        $el->divider();
        $el->form("<form class='pl-4 pr-3 text-nowrap'>{$underlined}</form>");

        $annot_btn = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('dark');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-new-note-btn');
        $el->icon('message-plus');
        $el->title($this->lang->t9n('New annotation'));
        $pin_btn = $el->render();

        $el = null;

        /** @var Bootstrap\Icon $el */
        $el = $this->di->get('Icon');

        $el->addClass('mdi-18px text-warning');
        $el->icon('marker');
        $marker_icon = $el->render();

        $el = null;

        /** @var Bootstrap\Dropdown $el */
        $el = $this->di->get('Dropdown');

        $el->id('pdfviewer-highlight-menu');
        $el->context('dark');
        $el->addClass('d-inline');
        $el->label($marker_icon);
        $el->span("<div class=\"highlight-color highlight-blue px-3 cursor-pointer\">{$this->lang->t9n('Blue')}</div>");
        $el->span("<div class=\"highlight-color highlight-yellow px-3 cursor-pointer\">{$this->lang->t9n('Yellow')}</div>");
        $el->span("<div class=\"highlight-color highlight-green px-3 cursor-pointer\">{$this->lang->t9n('Green')}</div>");
        $el->span("<div class=\"highlight-color highlight-red px-3 cursor-pointer\">{$this->lang->t9n('Red')}</div>");
        $el->span("<div class=\"highlight-eraser px-3 cursor-pointer\">{$this->lang->t9n('Eraser')}</div>");
        $el->span("<div class=\"highlight-cancel px-3 cursor-pointer\">{$this->lang->t9n('Cancel')}</div>");

        $marker_btn = $el->render();

        $el = null;

        // Page zoom.

        /** @var Bootstrap\Select $el */
        $el = $this->di->get('Select');

        $el->id("pdfviewer-zoom");
        $el->addClass('border-0 custom-select py-0 px-2');
        $el->groupClass('d-inline');
        $el->style("width: 5.5rem;height: 32px;background-color:white !important;color: rgb(73, 80, 87) !important");
        $el->option($this->lang->t9n('auto-NOUN'), 'auto');
        $el->option('50%', '50');
        $el->option('75%', '75');
        $el->option('100%', '100');
        $el->option('125%', '125');
        $el->option('150%', '150');
        $el->option('200%', '200');
        $el->option('250%', '250');
        $el->option('300%', '300');

        $zoom = $el->render();

        $el = null;

        // Copy image.

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('dark');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-image');
        $el->icon('image');
        $el->title($this->lang->t9n('Copy image'));
        $img_btn = $el->render();

        $el = null;

        // Copy text.

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('dark');
        $el->id('pdfviewer-text-btn');
        $el->style('width: 3rem');
        $el->icon('format-color-text');
        $el->title($this->lang->t9n('Copy text'));
        $text_btn = $el->render();

        $el = null;

        // Night mode.

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('dark');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-night-btn');
        $el->icon('weather-night');
        $el->title($this->lang->t9n('Night mode'));
        $night_btn = $el->render();

        $el = null;

        // Page navigation.

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('dark');
        $el->addClass('border-0 px-2 navigation-left');
        $el->style('width: 3rem');
        $el->id('pdfviewer-prev');
        $el->icon('chevron-left');
        $el->title($this->lang->t9n('Previous page'));
        $el->dataValue('prev');
        $prev_btn = $el->render();

        $el = null;

        $page_count = $info['pdf_info']['page_count'];

        $page_num = <<<EOT
            <input
                type="text"
                value="{$info['last_read']}"
                id="pdfviewer-page-input"
                maxlength="6"
                class="form-control bg-light border-0 d-inline rounded-0 px-2 py-0"
                style="transform: translateY(2px);height: 2.1rem;width: 3.4rem">
EOT;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('dark');
        $el->addClass('border-0 px-2 navigation-right');
        $el->style('width: 3rem');
        $el->id('pdfviewer-next');
        $el->icon('chevron-right');
        $el->title($this->lang->t9n('Next page'));
        $el->dataValue('next');
        $next_btn = $el->render();

        $el = null;

        // Top menu row.

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->id('pdfviewer-menu');
        $el->addClass('bg-dark text-white border-darker-bottom');
        $el->column("$left_toggle_btn $save_btn", 'col-sm-auto p-0 py-1 pl-1');
        $el->column("$search_input $up_btn $down_btn", 'col-sm-auto p-0 py-1 pl-1');
        $el->column("$annot_btn $pin_btn $marker_btn $zoom", 'col-sm-auto p-0 py-1 pl-1');
        $el->column("$img_btn $text_btn $night_btn $prev_btn $page_num $next_btn", 'col-sm-auto p-0 py-1 pl-1');
        $row = $el->render();

        $el = null;

        // Left panel submenu.

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('secondary');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-previews-btn');
        $el->icon('file');
        $el->title($this->lang->t9n('Pages'));
        $preview_btn = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('secondary');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-bookmarks-btn');
        $el->icon('bookmark');
        $el->title($this->lang->t9n('Bookmarks'));
        $bookmark_btn = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('secondary');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-notes-btn');
        $el->icon('message-text');
        $el->title($this->lang->t9n('PDF notes'));
        $notes_btn = $el->render();

        $el = null;

        /** @var Bootstrap\IconButton $el */
        $el = $this->di->get('IconButton');

        $el->context('secondary');
        $el->addClass('border-0 px-2');
        $el->style('width: 3rem');
        $el->id('pdfviewer-results-btn');
        $el->icon('file-search');
        $el->title($this->lang->t9n('Search results'));
        $results_btn = $el->render();

        $el = null;

        // Pages.

        // First page is not lazy loaded.
        $w = $info['pdf_info']['page_sizes'][1]['width'];
        $h = $info['pdf_info']['page_sizes'][1]['height'];

        $IL_BASE_URL = IL_BASE_URL;

        $images = <<<EOT
            <div class="d-inline-block pdfviewer-page my-3 img-light-mode md-box-shadow-1" data-page="1">
                <img
                    src="{$IL_BASE_URL}index.php/page/empty"
                    width="{$w}"
                    height="{$h}"
                    alt="{$this->lang->t9n('Page')}"
                    data-src="{$IL_BASE_URL}index.php/page/main?id={$item_id}&number=1&zoom=200"
                    class="lazy">
            </div>
EOT;

        // First thumb.
        $tw = 0.4 * $w;
        $th = 0.4 * $h;

        $thumbs = <<<EOT
            <div class="pdfviewer-thumb img-light-mode position-relative my-3 md-box-shadow-1" data-page="1">
                <img
                    alt="{$this->lang->t9n('Page')}"
                    class="lazy"
                    src="{$IL_BASE_URL}index.php/page/empty"
                    data-src="{$IL_BASE_URL}index.php/page/preview?id={$item_id}&number=1"
                    width="{$tw}"
                    height="{$th}">
                    <div class="bg-secondary">1</div>
            </div>
EOT;

        // Lazy loaded pages.
        for ($i = 2; $i <= $page_count; $i++) {

            $number = $i;
            $w = $info['pdf_info']['page_sizes'][$i]['width'];
            $h = $info['pdf_info']['page_sizes'][$i]['height'];
            $tw = 0.4 * $w;
            $th = 0.4 * $h;

            $images .= <<<EOT
                <div class="d-inline-block pdfviewer-page mb-3 img-light-mode md-box-shadow-1" data-page="{$number}">
                    <img
                        src="{$IL_BASE_URL}index.php/page/empty"
                        width="{$w}"
                        height="{$h}"
                        alt="{$this->lang->t9n('Page')}"
                        data-src="{$IL_BASE_URL}index.php/page/main?id={$item_id}&number={$number}&zoom=200"
                        class="lazy">
                </div>
EOT;

            $thumbs .= <<<EOT
                <div class="pdfviewer-thumb img-light-mode position-relative md-box-shadow-1" data-page="{$number}">
                    <img
                        alt="{$this->lang->t9n('Page')}"
                        src="{$IL_BASE_URL}index.php/page/empty"
                        data-src="{$IL_BASE_URL}index.php/page/preview?id={$item_id}&number={$number}"
                        class="lazy"
                        width="{$tw}"
                        height="{$th}">
                    <div class="bg-secondary">{$number}</div>
                </div>
EOT;
        }

        // Assemble bottom section.

        /** @var Bootstrap\ProgressBar $el */
        $el = $this->di->get('ProgressBar');

        $el->context('primary');
        $el->id('pdfviewer-search-progress');
        $el->addClass('d-none');
        $el->style('height: 2px');
        $el->value(1);
        $search_progress = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->id('pdfviewer-pages');
        $el->addClass('bg-secondary text-white text-center');
        $el->style('overflow: hidden');
        $el->column(
<<<HTML
<div class="border-darker-bottom py-1" style="width: 270px">
    {$preview_btn}
    {$bookmark_btn}
    {$notes_btn}
    {$results_btn}
</div>
<div id="pdfviewer-thumbs" class="d-none" style="width: 270px">
    {$thumbs}
</div>
<div id="pdfviewer-bookmarks" class="d-none" style="width: 270px">
    {$this->lang->t9n('No bookmarks')}
</div>
<div id="pdfviewer-notes" class="d-none" style="width: 270px"></div>
<div id="pdfviewer-results" class="d-none" style="width: 270px">
    {$search_progress}
    <div class="pdfviewer-no-results-container pt-3">
        {$this->lang->t9n('No search results')}
    </div>
    <div class="pdfviewer-results-container"></div>
</div>
HTML
            , 'd-none col-auto p-0 pdfviewer-left overflow-scroll border-darker-right');
        $el->column($images, 'col pdfviewer-right');
        $row .= $el->render();

        $el = null;

        // SVG image sharpening filter for Webkit.
        $filter = <<<HTML
<svg class="position-fixed">
    <filter id="sharpen">
        <feConvolveMatrix order="3 3" preserveAlpha="true" kernelMatrix="-1 0 0 0 10 0 0 0 -1"/>
    </filter>
</svg>
HTML;

        // Image cropping buttons.

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('primary');
        $el->id('copy-image-btn-temp');
        $el->addClass('d-none mr-1');
        $el->html($this->lang->t9n('Download'));
        $crop_btn = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('danger');
        $el->id('save-image-btn-temp');
        $el->addClass('d-none');
        $el->html($this->lang->t9n('Save'));
        $crop_btn .= $el->render();

        $el = null;

        if ($this->contentType() === 'html') {

            $this->styleLink('css/plugins.css');

            $this->head();

            $this->append("<div class=\"container-fluid\">{$row}</div>{$filter}{$crop_btn}");

            $this->scriptLink('js/plugins.min.js');

            $this->script = <<<EOT
                $(function(){
                    $('body').data('itemId', {$item_id});
                    window.pdfmainview = new PdfMainView();
                    window.pdfmainview.htmlRender();
                });
EOT;

            $this->end();

        } elseif ($this->contentType() === 'json') {

            $this->head();

            $this->append(['html' => $row . $filter . $crop_btn]);
        }

        return $this->send();
    }

    /**
     * Bookmarks.
     *
     * @param array $bookmarks
     * @return string
     * @throws Exception
     */
    public function bookmarks(array $bookmarks): string {

        $html = "<div class=\"pt-3 text-center\">{$this->lang->t9n('No bookmarks')}</div>";

        if (!empty($bookmarks)) {

            /** @var Bootstrap\Input $el */
            $el = $this->di->get('Input');

            $el->addClass('mt-3');
            $el->groupClass('mx-3');
            $el->style("background-color: white !important;color: rgb(73, 80, 87) !important");
            $el->placeholder($this->lang->t9n('Search bookmarks'));
            $el->ariaLabel($this->lang->t9n('Search bookmarks'));
            $html = $el->render();

            $el = null;
        }

        foreach ($bookmarks as $bookmark) {

            $padding = 15 * ($bookmark['level'] - 0);
            $weight = $bookmark['level'] === '1' ? 'bold' : 'normal';
            $page = $this->sanitation->html($bookmark['page']);
            $title = $this->sanitation->html($bookmark['title']);

            $html .= <<<EOT
<a href="#" data-page="{$page}" class="mb-3 pr-2 font-weight-{$weight}" style="margin-left:{$padding}px">{$title}</a>
EOT;
        }

        $this->append(['html' => $html]);

        return $this->send();
    }

    /**
     * Word boxes.
     *
     * @param array $boxes
     * @return string
     * @throws Exception
     */
    public function textLayer(array $boxes): string {

        $output = [
            'boxes' => []
        ];

        foreach ($boxes as $page => $words) {

            $html = '';

            foreach ($words as $word) {

                $t = $word['top'] / 10;
                $l = $word['left'] / 10;
                $w = $word['width'] / 10;
                $h = $word['height'] / 10;
                $position = $word['position'];
                $text = $this->sanitation->attr($word['text']);

                /** @var Element $el */
                $el = $this->di->get('Element');

                $el->style("top:{$t}%;left:{$l}%;width:{$w}%;height:{$h}%;");
                $el->attr('data-text', $text);
                $el->attr('data-position', $position);
                $html .= $el->render();

                $el = null;
            }

            $output['boxes'][$page] = "<div class=\"pdfviewer-text\">{$html}</div>";
        }

        $this->append($output);

        return $this->send();
    }

    /**
     * Highlight layer.
     *
     * @param array $highlights
     * @return string
     * @throws Exception
     */
    public function highlightLayer(array $highlights): string {

        $output = [
            'highlights' => []
        ];

        foreach ($highlights as $page => $words) {

            $html = '';

            if (empty($words)) {

                $output['highlights'][$page] = '<div class="pdfviewer-highlights"></div>';
                continue;
            }

            foreach ($words as $key => $word) {

                $t = $word['marker_top'] / 10;
                $l = $word['marker_left'] / 10;
                $w = $word['marker_width'] / 10;
                $h = $word['marker_height'] / 10;
                $position = $word['marker_position'];
                $text = $this->sanitation->attr($word['marker_text']);

                // Adjust width to not have seams.
                if (isset($words[($key + 1)]) &&
                    (int) $words[($key + 1)]['marker_position'] === ((int) $position + 1) &&
                    $words[($key + 1)]['marker_top'] === $word['marker_top']) {

                    $w = ($words[($key + 1)]['marker_left'] - $word['marker_left']) / 10;
                }

                switch ($word['marker_color']) {

                    case 'blue':
                    case'B':
                        $color = 'blue';
                        break;

                    case 'yellow':
                    case'Y':
                        $color = 'yellow';
                        break;

                    case 'green':
                    case'G':
                        $color = 'green';
                        break;

                    case 'red':
                    case'R':
                        $color = 'red';
                        break;

                    default:
                        $color = 'blue';
                }

                /** @var Element $el */
                $el = $this->di->get('Element');

                $el->addClass($color);
                $el->style("top:{$t}%;left:{$l}%;width:{$w}%;height:{$h}%;");
                $el->attr('data-text', $text);
                $el->attr('data-position', $position);
                $html .= $el->render();

                $el = null;
            }

            $output['highlights'][$page] = "<div class=\"pdfviewer-highlights\">{$html}</div>";
        }

        $this->append($output);

        return $this->send();
    }

    /**
     * List of notes for the left-column list.
     *
     * @param array $input
     * @return string
     * @throws Exception
     */
    public function noteList(array $input): string {

        $has_notes = false;
        $list = '';
        $pages = [];

        // New note form.

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('pg');
        $hidden_page = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('top');
        $hidden_top = $el->render();

        $el = null;

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->type('hidden');
        $el->name('left');
        $hidden_left = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->type('submit');
        $el->context('danger');
        $el->html('Save');
        $el->componentSize('small');
        $new_save = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->id('new-note-form');
        $el->addClass('text-left d-none');
        $el->action(IL_BASE_URL . 'index.php/pdf/savenote');
        $el->html(<<<FORM
            <textarea name="note" class="note-ta w-100 px-3 py-2 mb-2"></textarea>
            <div class="text-left pl-3 pb-2 border-darker-bottom">
                $new_save
            </div>
            $hidden_page
            $hidden_top
            $hidden_left
FORM
        );
        $list .= $el->render();

        $el = null;

        foreach ($input as $page => $boxes) {

            $notes = '';

            foreach ($boxes as $box) {

                $has_notes = true;

                $top = $box['annotation_top'] / 10;
                $left = $box['annotation_left'] / 10;

                // Button link.

                $user = $this->session->data('user_id') === $box['id_hash'] ? '': $box['username'] . ' : ';

                /** @var Bootstrap\Button $el */
                $el = $this->di->get('Button');

                $el->context('secondary');
                $el->addClass('note-btn d-block w-100 text-left rounded-0');
                $el->html($user . $box['annotation']);
                $el->attr('data-id', $box['id']);
                $el->attr('data-page', $page);
                $note_text = $el->render();

                $el = null;

                if ($user === '') {

                    /** @var Bootstrap\Button $el */
                    $el = $this->di->get('Button');

                    $el->context('light');
                    $el->componentSize('small');
                    $el->addClass('note-edit-btn ml-3 mt-2 mb-3');
                    $el->html($this->lang->t9n('Edit'));
                    $note_edit = $el->render();

                    $el = null;

                    // Edit form.

                    /** @var Bootstrap\Input $el */
                    $el = $this->di->get('Input');

                    $el->type('hidden');
                    $el->name('annotation_id');
                    $el->value($box['id']);
                    $hidden_annotation_id = $el->render();

                    $el = null;

                    /** @var Bootstrap\Button $el */
                    $el = $this->di->get('Button');

                    $el->type('submit');
                    $el->context('danger');
                    $el->html($this->lang->t9n('Save'));
                    $el->componentSize('small');
                    $note_save = $el->render();

                    $el = null;

                    /** @var Bootstrap\Form $el */
                    $el = $this->di->get('Form');

                    $el->addClass('d-none note-form');
                    $el->action(IL_BASE_URL . 'index.php/pdf/savenote');
                    $el->html(<<<FORM
                    <textarea name="note" class="note-ta w-100 px-3 py-2 mb-2">{$box['annotation']}</textarea>
                    <div class="text-left pl-3 py-1">
                        $note_save
                    </div>
                    $hidden_annotation_id
FORM
                    );
                    $form = $el->render();

                    $el = null;

                } else {

                    $note_edit = '';
                    $form = '';
                }

                // Note list in the left panel.
                $list .= <<<LIST
                    <div class="note-group text-left border-darker-bottom" data-id="{$box['id']}">
                        <div class="note-btn-div">
                            $note_text
                            $note_edit
                        </div>
                        $form
                    </div>
LIST;

                // Boxes on the page.
                $notes .= <<<EOT
                    <div
                        data-id="{$box['id']}"
                        data-toggle="tooltip"
                        title="{$user}{$box['annotation']}"
                        id="pdfnote-{$box['id']}"
                        class="pdfnote"
                        style="left:{$left}%;top:{$top}%;">
                    </div>
EOT;
            }

            $pages[$page] = "<div class=\"pdfviewer-notes\">$notes</div>";
        }

        // No notes.
        if ($has_notes === false) {

            $list = "<div class=\"py-3\">{$this->lang->t9n('No notes')}</div>" . $list;
        }

        $this->append([
            'list' => $list,
            'pages' => $pages
        ]);
        return $this->send();
    }
}
