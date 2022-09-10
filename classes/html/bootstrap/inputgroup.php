<?php

namespace Librarian\Html\Bootstrap;

use Librarian\Mvc\TextView;

/**
 * Bootstrap's input group.
 */
class Inputgroup extends Component {

    protected string $appendButton = '';
    protected string $hint = '';
    protected bool   $inline = false;
    protected string $inputIcon = '';
    protected string $label = '';

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'input';
        $this->addClass('rounded-0');
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
     * Place an icon into the input.
     *
     * @param string $icon
     */
    public function inputIcon(string $icon): void {

        $this->inputIcon = $icon;
    }

    /**
     * Append button to the input.
     *
     * @param string $button
     */
    public function appendButton(string $button): void {

        $this->appendButton = <<<EOT
            <div class="input-group-append">$button</div>
EOT;
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Theme.
        if (TextView::$theme === 'dark') {

            $this->addClass('bg-secondary border-0 text-white');
        }

        // There must be an ID.
        if ($this->id() === null) {

            $this->id($this->attr('name'));
        }

        // Default type is text.
        if ($this->attr('type') === null) {

            $this->attr('type', 'text');
        }

        // Default value is an empty string.
        if ($this->attr('value') === null && $this->attr('type') !== 'file') {

            $this->attr('value', '');
        }

        // Size.
        if (!empty($this->size)) {

            $size_class = $this->size === 'small' ? 'form-control-sm' : 'form-control-lg';
            $this->addClass($size_class);
        }

        // Required inputs have special label class.
        $labelRequired = '';

        if ($this->attr('required') === 'required') {

            $labelRequired = 'label-required';
        }

        // Input icon.
        $inputIcon = '';

        if (!empty($this->inputIcon)) {

            $inputIcon = "<span class=\"mdi mdi-18px mdi-$this->inputIcon opacity-50\" style=\"position: absolute;top:10px;right:5px\"></span>";

            $this->attr('style', $this->attr('style') . ';padding-right: 25px');
        }

        // Input class.
        $inputClass = $this->attr('type') === 'file' ? 'form-control-file' : 'form-control';
        $this->addClass($inputClass);

        // Label is optional.
        $labelTag = empty($this->label) ? "" : "<label for=\"{$this->id()}\" class=\"$labelRequired\"><b>$this->label</b></label>";

        // Compile HTML.
        return <<<EOT
            $labelTag
            <div class="input-group mb-2" style="position: relative">
                {$this->startTag()}
                $this->appendButton
                $inputIcon
            </div>
EOT;
    }
}
