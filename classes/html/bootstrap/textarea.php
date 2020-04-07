<?php

namespace Librarian\Html\Bootstrap;

use Librarian\Mvc\TextView;

/**
 * Bootstrap's input.
 */
final class Textarea extends Component {

    private $hint;
    private $label;

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'textarea';
        $this->addClass('form-control rounded-0');
    }

    public function label(string $label = null): string {

        if (isset($label)) {

            $this->label = $label;
        }

        return $this->label;
    }

    public function hint(string $hint = null): string {

        if (isset($hint)) {

            $this->hint = $hint;
        }

        return $this->hint;
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

        // Required inputs have special label class.
        $labelRequired = '';

        if ($this->attr('required') === 'required') {

            $labelRequired = 'label-required';
        }

        // Label is optional.
        $labelTag = empty($this->label) ? "" : "<label for=\"{$this->id()}\" class=\"$labelRequired\">{$this->label}</label>";

        // Hint  is optional.
        $hint = empty($this->hint) ? "" : "<small class=\"form-text text-muted\">{$this->hint}</small>";

        // Compile HTML.
        $html = <<<EOT
            <div class="form-group">
                $labelTag
                {$this->startTag()}{$this->html()}{$this->endTag()}
                $hint
            </div>
EOT;

        return $html;
    }

}
