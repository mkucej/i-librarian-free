<?php

namespace Librarian\Html\Bootstrap;

use Librarian\Mvc\TextView;

/**
 * Bootstrap's autocomplete.
 */
final class Typeahead extends Input {

    protected $groupClass;

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->addClass('form-control');
        $this->type('text');
        $this->autocomplete('off');
    }

    public function source(string $source = null): string {

        // Setter.
        if (isset($source)) {

            $this->attr('data-source', $source);
        }

        // Getter.
        return $this->attr('data-source');
    }

    public function minLength($minLength = null) {

        // Setter.
        if (isset($minLength)) {

            $this->attr('data-min-length', $minLength);
        }

        // Getter.
        return $this->attr('data-min-length');
    }

    public function delay($delay = null) {

        // Setter.
        if (isset($delay)) {

            $this->attr('data-delay', $delay);
        }

        // Getter.
        return $this->attr('data-delay');
    }

    public function groupClass($class): void {

        $this->groupClass = $class;
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // There must be an ID.
        if ($this->id() === null) {

            $this->id($this->name());
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

        // Label is optional.
        $labelTag = empty($this->label) ? "" : "<label for=\"{$this->id()}\" class=\"$labelRequired\">{$this->label}</label>";

        // Hint is optional.
        $hint = empty($this->hint) ? "" : "<small class=\"form-text text-muted\">{$this->hint}</small>";

        // Listbox ARIA attr.
        $this->attr('aria-controls', "{$this->id()}-listbox");

        // Theme.
        $input_theme_classes = TextView::$theme === 'dark' ? ' bg-secondary text-white border-0' : '';
        $this->addClass($input_theme_classes);

        /*
         * Input.
         */

        // Compile HTML.
        $html = <<<EOT
            <div class="form-group dropdown typeahead {$this->groupClass}">
                <div role="combobox" aria-expanded="false" aria-owns="{$this->id()}-listbox" aria-haspopup="true">
                    $labelTag
                    {$this->startTag()}
                    $hint
                </div>
                <div id="{$this->id()}-listbox" role="listbox" tabindex="-1" class="dropdown-menu rounded-0 py-0 {$input_theme_classes}"></div>
            </div>
EOT;

        return $html;
    }

}
