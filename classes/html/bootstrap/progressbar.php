<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's progress bar.
 */
final class ProgressBar extends Component {

    protected $label = '';
    protected int    $value = 0;

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
     * Default (value%), or string for custom label.
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
     * @param int|null $value
     * @return integer
     */
    public function value(int $value = null): int {

        if (isset($value)) {

            $this->value = $value;
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
        $context = empty($this->context) ? "" : " bg-$this->context";

        // Label.
        if ($this->label === true) {

            $label = "$this->value%";

        } else {

            $label = $this->label;
        }

        // Inner HTML.
        $html = <<<EOT
            <div class="progress-bar$context" role="progressbar" style="width: $this->value%" aria-valuenow="$this->value" aria-valuemin="0" aria-valuemax="100">$label</div>
EOT;

        $this->html($html);

        return parent::render();
    }
}
