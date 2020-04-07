<?php

namespace Librarian\Html\Bootstrap;

use Librarian\Mvc\TextView;

/**
 * Bootstrap's modal dialog.
 */
final class Modal extends Component {

    private $body;
    private $buttons = [];
    private $header;

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

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

        $html = <<<EOT
            <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered {$size_class}" role="document">
                <div class="modal-content rounded-0 {$theme_classes}">
EOT;

        // Header.
        if (!empty($this->header)) {

            $html .= <<<EOT
                <div class="modal-header rounded-0 border-0">
                    <h5 class="modal-title d-flex flex-nowrap">{$this->header}</h5>
                    <button type="button" class="btn btn-sm {$theme_btn_classes}" data-dismiss="modal" aria-label="Close">
                        <span class="mdi mdi-18px mdi-close" aria-hidden="true"></span>
                    </button>
                </div>
EOT;
        }

        // Body.
        $html .= <<<EOT
            <div class="modal-body {$this->body['classes']}">
                {$this->body['html']}
            </div>
EOT;

        // Footer.
        $buttons = join(' ', $this->buttons);
        $html .= <<<EOT
            <div class="modal-footer border-0">
                {$buttons}
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
EOT;

        $html .= '</div></div>';

        $this->html($html);

        return parent::render();
    }

}
