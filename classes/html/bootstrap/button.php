<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's button.
 */
final class Button extends Component {

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'button';
        $this->addClass('btn');
        $this->attr('type', 'button');
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Anchor button can't have type attr.
        if ($this->element_name === 'a') {

            $this->removeAttr('type');
        }

        // Context.
        if (!empty($this->context)) {

            $this->addClass("btn-{$this->context}");
        }

        // Size.
        if (!empty($this->size)) {

            $size_class = $this->size === 'small' ? 'btn-sm' : 'btn-lg';
            $this->addClass($size_class);
        }

        return parent::render();
    }
}
