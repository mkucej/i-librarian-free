<?php

namespace Librarian\Html\Bootstrap;

use Exception;
use Librarian\Mvc\TextView;

/**
 * Bootstrap's card.
 */
final class Card extends Component {

    private $body_classes;
    private $footer;
    private $footer_classes;
    private $header;
    private $header_classes;
    private $items = [];

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->addClass('card rounded-0');
    }

    /**
     * Card body.
     *
     * @param string $body_text
     * @param string|null $body_title
     * @param string|null $body_classes
     */
    public function body(string $body_text, string $body_title = null, string $body_classes = null): void {

        $this->items[] = [
            'type'  => 'body',
            'title' => $body_title,
            'text'  => $body_text
        ];

        $this->body_classes = $body_classes;
    }

    /**
     * Card image.
     *
     * @param string $src
     * @param string $alt
     */
    public function image(string $src, string $alt): void {

        $this->items[] = [
            'type'     => 'image',
            'src'      => $src,
            'alt'      => $alt,
        ];
    }

    /**
     * Add a list group to the card.
     *
     * @param string $list
     */
    public function listGroup(string $list): void {

        $this->items[] = [
            'type' => 'list',
            'html' => $list
        ];
    }

    /**
     * Add card header.
     *
     * @param string $header
     * @param string|null $header_classes
     */
    public function header(string $header, string $header_classes = null): void {

        $this->header = $header;
        $this->header_classes = $header_classes;
    }

    /**
     * Add card footer.
     *
     * @param string $footer
     * @param string|null $footer_classes
     */
    public function footer(string $footer, string $footer_classes = null): void {

        $this->footer = $footer;
        $this->footer_classes = $footer_classes;
    }

    /**
     * Render the component.
     *
     * @return string
     * @throws Exception
     */
    public function render(): string {

        // Theme.
        if (TextView::$theme === 'dark') {

            $this->addClass('bg-dark');
        }

        $html = $this->inner_html;

        // Header.
        if (!empty($this->header)) {

            $html .= <<<EOT
                <div
                    class="card-header d-flex justify-content-between align-items-center border-0 bg-transparent {$this->header_classes}">
                    {$this->header}
                </div>
EOT;
        }

        // Card items.
        foreach ($this->items as $key => $item) {

            switch ($item['type']) {

                case 'image':
                    $html .= <<<EOT
                        <img class="card-img-top rounded-0 border-darker-bottom" src="{$item['src']}" alt="{$item['alt']}">
EOT;
                    break;

                case 'body':
                    $title = empty($item['title']) ? "" : "<h5 class=\"card-title\">{$item['title']}</h5>";
                    $html .= <<<EOT
                        <div class="card-body pt-0 {$this->body_classes}">
                            $title
                            <div class="card-text">{$item['text']}</div>
                        </div>
EOT;
                    break;

                case 'list':
                    $html .= $item['html'];
                    break;

                default:
                    throw new Exception('unknown card item', 500);
            }
        }

        // Footer.
        if (!empty($this->footer)) {

            $html .= <<<EOT
                <div class="card-footer border-0 bg-transparent pb-4 {$this->footer_classes}">{$this->footer}</div>
EOT;
        }

        $this->html($html);

        return parent::render();
    }
}
