<?php

namespace Librarian\Html\Bootstrap;

/**
 * Side metis menu.
 */
final class SideMenu extends Component {

    private $menu = [];

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'ul';
        $this->addClass('metismenu');
    }

    public function menu(array $menu): void {

        $this->menu = $menu;
    }

    private function link(array $link): string {

        $html = '';

        if (!empty($link['submenu'])) {

            $subMenu = $this->subMenu($link['submenu']);

            $html .= <<<EOT
                <li>
                    <a href="{$link['link']}" class="has-arrow" aria-expanded="false">
                        {$link['label']}
                    </a>
                    {$subMenu}
                </li>
EOT;
        } else {

            $attrs = empty($link['attrs']) ? '' : $link['attrs'];

            if (!isset($link['link'])) {

                $html .= <<<EOT
                <li><div $attrs>{$link['label']}</div></li>
EOT;

            } else {

                $html .= <<<EOT
                <li><a href="{$link['link']}" $attrs>{$link['label']}</a></li>
EOT;
            }
        }

        return $html;
    }

    private function subMenu(array $subMenu): string {

        $html = '<ul aria-expanded="false" class="collapse">';

        foreach ($subMenu as $link) {

            $html .= $this->link($link);
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        $html = '';

        foreach ($this->menu as $link) {

            $html .= $this->link($link);
        }

        $this->html($html);

        return parent::render();
    }

}
