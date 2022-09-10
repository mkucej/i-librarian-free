<?php

namespace Librarian\Html\Bootstrap;

/**
 * Sidebar.
 */
final class Sidebar extends Component {

    private string $menu = '';

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'nav';
        $this->addClass('navbar p-0');
    }

    public function menu(string $menu): void {

        $this->menu = $menu;
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Toggler.
        $this->append(<<<EOT
            <button class="navbar-toggler border-0 my-2" type="button" data-toggle="collapse" data-target="#sidebar-nav" aria-controls="navbar-nav" aria-expanded="false" aria-label="Toggle navigation" style="overflow: hidden">
                <span class="mdi mdi-menu" aria-hidden="true"></span>
            </button>
            <div class="collapse navbar-collapse" id="sidebar-nav">
EOT
        );

        // Menu.
        $this->append($this->menu);

        // Links.
        if (!empty($this->leftLinks)) {

            $this->append('<ul class="navbar-nav flex-column">');

            foreach ($this->leftLinks as $key => $link) {

                if (is_array($link[1])) {

                    $dropdownLinks = '';

                    // Dropdown links.
                    foreach ($link[1] as $dropdownLink) {

                        if ($dropdownLink[0] === 'divider') {

                            $dropdownLinks .= '<div class="dropdown-divider"></div>';

                        } else {

                        $dropdownLinks .= <<<EOT
                            <a class="dropdown-item" href="$dropdownLink[1]">$dropdownLink[0]</a>
EOT;
                        }
                    }

                    // Dropdown.
                    $this->append(<<<EOT
                        <li class="dropdown nav-item$link[2]">
                            <a id="dropdown-link-$key" class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">$link[0]</a>
                            <div class="rounded-0 dropdown-menu" aria-labelledby="dropdown-link-$key">
                                $dropdownLinks
                            </div>
                        </li>
EOT
                    );

                } else {

                    // Link.
                    $this->append(<<<EOT
                        <li class="nav-item$link[2]">
                            <a class="nav-link" href="$link[1]">$link[0]</a>
                        </li>
EOT
                    );
                }
            }

            $this->append('</ul>');
        }

        // Wrap up.
        $this->append('</div>');

        return parent::render();
    }

}
