<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's input styled as a button.
 */
class InputButton extends Component {

    protected $group_class;
    protected $inline;
    protected $label;

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'input';
        $this->attr('autocomplete', 'off');
    }

    /**
     * Label.
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
     * Set class for the outer div.
     *
     * @param string $class
     */
    public function groupClass(string $class): void {

        $this->group_class = $class;
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // There must be an ID.
        if ($this->id() === '') {

            $this->id($this->attr('name'));
        }

        // Default type is checkbox.
        if ($this->attr('type') === '') {

            $this->attr('type', 'checkbox');
        }

        // Default value is an empty string.
        if ($this->attr('value') === '') {

            $this->attr('value', '');
        }

        // Size.
        $size_class = '';

        if (!empty($this->size)) {

            $size_class = $this->size === 'small' ? 'btn-sm' : 'btn-lg';
        }

        // Disabled class.
        $disabledClass = $this->attr('disabled') === 'disabled' ? 'disabled' : '';

        // Compile HTML.
        $html = <<<EOT
            <div class="d-inline btn-group-toggle {$this->group_class} {$disabledClass}" data-toggle="buttons">
                <label class="btn btn-{$this->context} {$size_class} btn-icon border-0">
                    {$this->startTag()}
                    {$this->label}
                </label>
            </div>
EOT;

        return $html;
    }
}
