<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's badge.
 */
final class Badge extends Component {

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'span';
        $this->addClass('badge');
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Remove rounded corners.
        if ($this->hasClass('badge-pill') === false) {

            $this->addClass('rounded-0');
        }

        // Context.
        if (!empty($this->context)) {

            $this->addClass("badge-$this->context");
        }

        return parent::render();
    }
}
