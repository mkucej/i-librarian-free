<?php

namespace Librarian\Html\Bootstrap;

use Librarian\Mvc\TextView;

/**
 * Bootstrap's input.
 */
class Input extends Component {

    protected string $groupClass = '';
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
     * @param  string|null $label
     * @return string
     */
    public function label(string $label = null): string {

        if (isset($label)) {

            $this->label = $label;
        }

        return $this->label;
    }

    /**
     * Hint.
     *
     * @param string|null $hint
     * @return string
     */
    public function hint(string $hint = null): string {

        if (isset($hint)) {

            $this->hint = $hint;
        }

        return $this->hint;
    }

    /**
     * Set/get inline style.
     *
     * @param bool $inline
     * @return bool
     */
    public function inline(bool $inline = true): bool {

        if (isset($inline)) {

            $this->inline = $inline;
        }

        return $this->inline;
    }

    /**
     * Add an icon into the input.
     *
     * @param string $icon
     */
    public function inputIcon(string $icon): void {

        $this->inputIcon = $icon;
    }

    /**
     * Set the class for the top div.
     *
     * @param string $class
     */
    public function groupClass(string $class): void {

        $this->groupClass = $class;
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Default type is text.
        if ($this->attr('type') === '') {

            $this->attr('type', 'text');
        }

        // Default value is an empty string.
        if ($this->attr('value') === '' && $this->attr('type') !== 'file') {

            $this->attr('value', '');
        }

        // There must be an ID, except in hidden type.
        if ($this->id() === '' && $this->attr('type') !== 'hidden') {

            $this->id($this->attr('name'));
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

        switch ($this->attr('type')) {

            case 'hidden':

                $this->removeClass('rounded-0');

                // Compile HTML. No container or label.
                return parent::render();

            case 'checkbox':
            case 'radio':

                // Input class.
                $this->addClass('form-check-input sr-only');

                // Disabled class (form beautification css).
                $disabledClass = $this->attr('disabled') === 'disabled' ? 'disabled' : '';

                // Inline.
                $inlineClass = $this->inline === true ? ' form-check-inline' : '';

                // Hint  is optional.
                $hint = empty($this->hint) ? "" : "<small class=\"form-text text-muted\" style='transform: translateX(4px)'>$this->hint</small>";

                // Compile HTML.
                $html = <<<EOT
                    <div class="form-check$inlineClass mb-2 $this->groupClass">
                        {$this->startTag()}
                        <label
                            for="{$this->attr('id')}"
                            class="form-check-label $disabledClass $labelRequired">
                            <span class="mdi mdi-18px" aria-hidden="true"></span>
                            <span class="label-text">$this->label</span>
                        </label>
                        $hint
                        $this->inner_html
                    </div>
EOT;
                break;

            default:

                // Theme.
                if (TextView::$theme === 'dark') {

                    $this->addClass('bg-secondary border-0 text-white');
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
                $labelTag = empty($this->label) ? "" : "<label for=\"{$this->id()}\" class=\"$labelRequired\">$this->label</label>";

                // Hint  is optional.
                $hint = empty($this->hint) ? "" : "<small class=\"form-text text-muted\">$this->hint</small>";

                // Compile HTML.
                $html = <<<EOT
                    <div class="form-group $this->groupClass" style="position: relative">
                        $labelTag
                        {$this->startTag()}
                        $hint
                        $inputIcon
                        $this->inner_html
                    </div>
EOT;
        }

        return $html;
    }
}
