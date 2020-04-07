<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's navigation content divs.
 */
final class NavContent extends Component {

    private $items = [];

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->addClass('tab-content');
    }

    public function tab(string $content, string $id, bool $active = false, string $class = null): void {

        $this->items[] = [
            $content,
            $id,
            $active,
            $class
        ];
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        $html = '';

        foreach ($this->items as $item) {

            $class = empty($item[3]) ? "" : " $item[3]";
            $activeClass = $item[2] === true ? ' show active' : '';

            $html .= <<<EOT
                <div
                    id="{$item[1]}"
                    class="tab-pane{$class}{$activeClass}"
                    role="tabpanel"
                    aria-labelledby="{$item[1]}-nav">{$item[0]}</div>
EOT;
        }

        // Render.
        $this->html($html);

        return parent::render();
    }
}
