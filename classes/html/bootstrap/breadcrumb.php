<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's breadcrumbs.
 */
final class Breadcrumb extends Component {

    private array $breadcrumbs = [];

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'ol';
        $this->addClass('breadcrumb d-flex flex-lg-nowrap rounded-0');
    }

    /**
     * Add breadcrumb item.
     *
     * @param string $name
     * @param string|null $url
     */
    public function item(string $name, string $url = null): void {

        $this->breadcrumbs[] = [
            $name,
            $url ?? ''
        ];
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Compile breadcrumbs.
        $html = '';
        $last_breadcrumb = array_pop($this->breadcrumbs);

        foreach ($this->breadcrumbs as $breadcrumb) {

            $html .= <<<EOT
            <li class="breadcrumb-item d-inline-block text-truncate" style="max-width: 50vw">
                <a href="$breadcrumb[1]">$breadcrumb[0]</a>
            </li>
EOT;
        }

        $html .= <<<EOT
            <li class="breadcrumb-item d-inline-block text-truncate active" style="max-width: 50vw" aria-current="page">
                $last_breadcrumb[0]
            </li>
EOT;

        $this->html($html);

        return "<nav aria-label=\"breadcrumb\">{$this->startTag()}{$this->html()}{$this->endTag()}</nav>";
    }
}
