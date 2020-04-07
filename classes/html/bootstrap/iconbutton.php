<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's button.
 */
final class IconButton extends Component {

    private $icons = [];

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'button';
        $this->addClass('btn btn-icon');
        $this->attr('type', 'button');
    }

    /**
     * Add icon to the button.
     *
     * @param string $iconType
     */
    public function icon(string $iconType): void {

        // Set icon type.
        $this->icons[] = $iconType;
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

        // Use HTML as aria label.
        $this->ariaLabel($this->html());

        // Create the icon, which is the inner HTML.
        $icon = '';

        foreach ($this->icons as $type) {

            $el = new Icon();

            $el->icon($type);
            $icon .= $el->render();

            $el = null;
        }

        $this->html($icon);

        return parent::render();
    }
}
