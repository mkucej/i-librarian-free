<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's row.
 */
final class Row extends Component {

    private array $rows = [];

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->addClass('row');
    }

    /**
     * Add column. Default class is col-lg.
     *
     * @param string $html
     * @param string|null $class
     */
    public function column(string $html, string $class = null): void {

        $this->rows[] = [
            $html,
            $class ?? 'col-xl'
        ];
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Inner HTML.
        $html = '';

        foreach ($this->rows as $row) {

            $html .= <<<EOT
            <div class="$row[1]">$row[0]</div>
EOT;
        }

        $html .= $this->inner_html;

        $this->html($html);

        return parent::render();
    }
}
