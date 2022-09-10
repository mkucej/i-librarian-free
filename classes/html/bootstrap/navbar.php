<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's navbar.
 */
final class Navbar extends Component {

    private array  $brand = [];
    private array  $leftLinks = [];
    private array  $rightLinks = [];

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'nav';
        $this->addClass('navbar');
    }

    public function brand(string $brand, string $link): void {

        $this->brand = [$brand, $link];
    }

    public function dropdown(string $name, array $dropdown, string $position = 'left', bool $active = false): void {

        $activeClass = $active === true ? ' active' : '';

        if ($position === 'left') {

            $this->leftLinks[] = [$name, $dropdown, $activeClass];

        } elseif ($position === 'right') {

            $this->rightLinks[] = [$name, $dropdown, $activeClass];
        }
    }

    public function link(string $name, string $link, string $position = 'left', bool $active = false): void {

        $activeClass = $active === true ? ' active' : '';

        if ($position === 'left') {

            $this->leftLinks[] = [$name, $link, $activeClass];

        } elseif ($position === 'right') {

            $this->rightLinks[] = [$name, $link, $activeClass];
        }
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Brand.
        $this->append(<<<EOT
            <a class="navbar-brand" href="{$this->brand[1]}">{$this->brand[0]}</a>
EOT
        );

        // Toggler.
        $this->append(<<<EOT
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar-nav" aria-controls="navbar-nav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbar-nav">
EOT
        );

        // Left links.
        if (!empty($this->leftLinks)) {

            $this->append('<ul class="navbar-nav">');

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

        // Right links.
        if (!empty($this->rightLinks)) {

            $this->append('<ul class="navbar-nav ml-auto">');

            foreach ($this->rightLinks as $key => $link) {

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
                            <div class="rounded-0 dropdown-menu dropdown-menu-right" aria-labelledby="dropdown-link-$key">
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
