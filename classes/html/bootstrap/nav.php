<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's navigation.
 */
class Nav extends Component {

    private array $items = [];

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->addClass('nav');
        $this->attr('role', 'tablist');
    }

    public function item(string $name, string $id, bool $active = false, string $class = null): void {

        $this->items[] = [
            $name,
            $id,
            $active === true ? 'true' : 'false',
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
            $activeClass = $item[2] === 'true' ? ' active' : '';

            $html .= <<<EOT
                <span
                    id="$item[1]-nav"
                    class="cursor-pointer mr-1 border-0 nav-item nav-link$class$activeClass"
                    data-target="#$item[1]"
                    data-toggle="tab"
                    role="tab"
                    aria-controls="$item[1]"
                    aria-selected="$item[2]">$item[0]</span>
EOT;
        }

        // Render.
        $this->html($html);

        return parent::render();
    }
}
