<?php

namespace Librarian\Html\Bootstrap;

use Exception;

/**
 * Class for Bootstrap's range.
 */
class Range extends Component {

    protected string $groupClass = '';
    protected string $hint = '';
    protected bool   $inline = false;
    protected string $label = '';

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'input';
        $this->attr('type', 'range');
        $this->attr('min', '0');
        $this->attr('max', '100');
        $this->attr('step', '10');
        $this->attr('value', '0');
        $this->addClass('custom-range');
    }

    /**
     * Label.
     *
     * @param  string $label
     */
    public function label(string $label): void {

        $this->label = $label;
    }

    /**
     * Hint.
     *
     * @param string $hint
     */
    public function hint(string $hint): void {

        $this->hint = $hint;
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
     * Render component HTML.
     *
     * @return string
     * @throws Exception
     */
    public function render(): string {

        // There must be an ID.
        if ($this->id() === '') {

            throw new Exception('missing id for range element');
        }

        // Required inputs have special label class.
        $labelRequired = '';

        if ($this->attr('required') === 'required') {

            $labelRequired = 'label-required';
        }

        $hint = empty($this->hint) ? "" : "<small class=\"form-text text-muted\">$this->hint</small>";

        // Compile HTML.
        return
<<<HTML
    <div class="form-group mb-2 $this->groupClass">
        <label for="{$this->attr('id')}" class="$labelRequired">
            <span class="label-text">$this->label</span>
        </label>
        {$this->startTag()}
        <div class="range-divider">&nbsp;</div>
        $hint
    </div>
HTML;
    }
}
