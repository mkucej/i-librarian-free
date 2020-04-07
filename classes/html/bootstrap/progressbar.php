<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's progress bar.
 */
final class ProgressBar extends Component {

    protected $label;
    protected $value;

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->addClass('progress rounded-0');
    }

    /**
     * Set label.
     *
     * True for default (value%), or string for custom label.
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
     * Set value.
     *
     * @param  integer $value
     * @return integer
     */
    public function value(int $value = null): int {

        if (isset($value)) {

            $this->value = (integer) $value;
        }

        return $this->value;
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Context.
        $context = empty($this->context) ? "" : " bg-{$this->context}";

        // Label.
        $label = "";

        if ($this->label === true) {

            $label = "{$this->value}%";

        } elseif (!empty($this->label) && is_string($this->label)) {

            $label = $this->label;
        }

        // Inner HTML.
        $html = <<<EOT
            <div class="progress-bar{$context}" role="progressbar" style="width: {$this->value}%" aria-valuenow="{$this->value}" aria-valuemin="0" aria-valuemax="100">{$label}</div>
EOT;

        $this->html($html);

        return parent::render();
    }

}
