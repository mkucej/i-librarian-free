<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's badge.
 */
final class Icon extends Component {

    private $icon_type;

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'span';
        $this->addClass('mdi');
        $this->ariaHidden('true');
    }

    /**
     * Set/get icon type.
     *
     * @param  string $icon_type
     * @return string
     */
    public function icon(string $icon_type = null): string {

        if (isset($icon_type)) {

            $this->icon_type = $icon_type;

            // Set icon type.
            $this->addClass("mdi-{$icon_type}");
        }

        return $this->icon_type;
    }

    public function render(): string {

        if ($this->context() !== 'default') {

            $this->addClass('text-' . $this->context());
        }

        return parent::render();
    }
}
