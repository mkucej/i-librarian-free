<?php

namespace Librarian\Html\Bootstrap;

use Librarian\Media\Language;
use Librarian\Mvc\TextView;

/**
 * Bootstrap's modal dialog.
 */
final class Modal extends Component {

    /**
     * @var Language
     */
    private Language $lang;

    private array  $body = [];
    private array  $buttons = [];
    private string $header = '';

    /**
     * Constructor.
     *
     * @param Language $lang
     */
    public function __construct(Language $lang) {

        parent::__construct();

        $this->lang = $lang;

        $this->addClass('modal');
        $this->attr('tabindex', '-1');
        $this->attr('role', 'dialog');
    }

    /**
     * Header.
     *
     * @param string $header
     */
    public function header(string $header): void {

        $this->header = $header;
    }

    /**
     * Body.
     *
     * @param string $body
     * @param string $classes
     */
    public function body(string $body, string $classes = ''): void {

        $this->body['html'] = $body;
        $this->body['classes'] = $classes;
    }

    /**
     * Button.
     *
     * @param string $button
     */
    public function button(string $button): void {

        $this->buttons[] = $button;
    }

    public function render(): string {

        // Size.
        $size_class = '';

        if (!empty($this->size)) {

            $size_class = $this->size === 'small' ? ' modal-sm' : ' modal-lg';
        }

        // Theme.
        $theme_classes = TextView::$theme === 'dark' ? 'bg-dark' : '';
        $theme_btn_classes = TextView::$theme === 'dark' ? 'btn-dark' : '';

        $html =
<<<HTML
<div class="modal-dialog modal-dialog-scrollable $size_class" role="document">
    <div class="modal-content rounded-0 $theme_classes">
HTML;

        // Header.
        if (!empty($this->header)) {

            $html .=
<<<I18N
        <div class="modal-header rounded-0 border-0">
            <h5 class="modal-title d-flex flex-nowrap">$this->header</h5>
            <button type="button" class="btn btn-sm $theme_btn_classes" data-dismiss="modal" aria-label="{$this->lang->t9n('Close')}">
                <span class="mdi mdi-18px mdi-close" aria-hidden="true"></span>
            </button>
        </div>
I18N;
        }

        // Body.
        $html .=
<<<HTML
        <div class="modal-body {$this->body['classes']}">
            {$this->body['html']}
        </div>
HTML;

        // Footer.
        $buttons = join(' ', $this->buttons);
        $html .=
<<<I18N
        <div class="modal-footer border-0">
            $buttons
            <button type="button" class="btn btn-secondary" data-dismiss="modal">{$this->lang->t9n('Close')}</button>
        </div>
I18N;

        $html .=
<<<HTML
    </div>
</div>
HTML;

        $this->html($html);

        return parent::render();
    }
}
