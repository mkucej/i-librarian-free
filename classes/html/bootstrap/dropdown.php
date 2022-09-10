<?php

namespace Librarian\Html\Bootstrap;

use Exception;

/**
 * Bootstrap's dropdown.
 */
final class Dropdown extends Component {

    private string $label = '';
    private array  $menu_items = [];
    private array  $types;

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->types = [
            'link',
            'divider',
            'span',
            'button',
            'form'
        ];

        $this->addClass('dropdown');
    }

    /**
     * Set/get button text.
     *
     * @param string|null $label
     * @return string
     */
    public function label(string $label = null): string {

        if (isset($label)) {

            $this->label = $label;
        }

        return $this->label;
    }

    /**
     * Add menu item.
     *
     * @param string $type
     * @param string|null $text
     * @param string|null $extras URL, class.
     * @throws Exception
     */
    public function item(string $type, string $text = null, string $extras = null): void {

        if (in_array($type, $this->types) === false) {

            throw new Exception('unknown menu type');
        }

        $this->menu_items[] = [
            $type,
            $text,
            $extras
        ];
    }

    /**
     * Add a menu divider.
     *
     * @throws Exception
     */
    public function divider(): void {

        $this->item('divider');
    }

    /**
     * Add a menu button.
     *
     * @param string $text
     * @throws Exception
     */
    public function span(string $text): void {

        $this->item('span', $text);
    }

    /**
     * Add a menu link.
     *
     * @param string $text
     * @param string $url
     * @throws Exception
     */
    public function link(string $text, string $url): void {

        $this->item('link', $text, $url);
    }

    /**
     * Add a button.
     *
     * @param string $text
     * @param string|null $class
     * @throws Exception
     */
    public function button(string $text, string $class = null): void {

        $this->item('button', $text, $class);
    }

    /**
     * Add a form to the menu.
     *
     * @param string $html
     * @throws Exception
     */
    public function form(string $html): void {

        $this->item('form', $html);
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        $html = '';
        $subId = uniqid('dropdown-button-');

        /*
         * Button.
         */

        // Context.
        $contextClass = '';

        if (!empty($this->context)) {

            $contextClass = "btn-$this->context";
        }

        // Size.
        $sizeClass = '';

        if (!empty($this->size)) {

            $sizeClass = $this->size === 'small' ? ' btn-sm' : ' btn-lg';
        }

        $html .= <<<EOT
            <button class="btn $contextClass dropdown-toggle$sizeClass" type="button" id="$subId" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                $this->label
            </button>
EOT;

        /*
         * Menu.
         */

        $html .= <<<EOT
            <div class="dropdown-menu rounded-0" aria-labelledby="$subId">
EOT;

        foreach ($this->menu_items as $item) {

            switch ($item[0]) {

                case 'link':
                    $html .= <<<EOT
                    <a class="dropdown-item" href="$item[2]">$item[1]</a>
EOT;
                    break;

                case 'span':
                    $html .= <<<EOT
                    <span class="dropdown-item-text">$item[1]</span>
EOT;
                    break;

                case 'button':
                    $class = $item[2] ?? '';
                    $html .= <<<EOT
                    <button class="dropdown-item $class" type="button">$item[1]</button>
EOT;
                    break;

                case 'divider':
                    $html .= <<<'EOT'
                    <div class="dropdown-divider"></div>
EOT;
                    break;

                case 'form':
                    $html .= <<<EOT
                $item[1]
EOT;
                    break;
            }
        }

        $html .= '</div>';

        /*
         * Render.
         */

        $this->html($html);

        return parent::render();
    }
}
