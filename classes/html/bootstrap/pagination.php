<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's pagination.
 */
final class Pagination extends Component {

    private array $pages = [];

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'ul';
        $this->addClass('pagination');
    }

    public function pages(array $pages): void {

        $this->pages = $pages;
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Size.
        if (!empty($this->size)) {

            $sizeClass = $this->size === 'small' ? 'pagination-sm' : 'pagination-lg';
            $this->addClass($sizeClass);
        }

        // Html.
        $html = <<<EOT
            <li class="page-item">
                <a class="page-link rounded-0 border-0" href="#first" aria-label="First">
                    <span class="text-center mdi mdi-chevron-double-left" aria-hidden="true"></span>
                    <span class="sr-only">First</span>
                </a>
            </li>
            <li class="page-item">
                <a class="page-link rounded-0 border-0" href="#previous" aria-label="Previous">
                    <span class="text-center mdi mdi-chevron-left" aria-hidden="true"></span>
                    <span class="sr-only">Previous</span>
                </a>
            </li>
EOT;

        foreach ($this->pages as $page) {

            $html .= <<<EOT
                <li class="page-item"><a class="page-link" href="#">$page</a></li>
EOT;
        }

        $html .= <<<EOT
            <li class="page-item">
                <a class="page-link rounded-0 border-0" href="#next" aria-label="Next">
                    <span class="text-center mdi mdi-chevron-right" aria-hidden="true"></span>
                    <span class="sr-only">Next</span>
                </a>
            </li>
            <li class="page-item">
                <a class="page-link rounded-0 border-0" href="#last" aria-label="Last">
                    <span class="text-center mdi mdi-chevron-double-right" aria-hidden="true"></span>
                    <span class="sr-only">Last</span>
                </a>
            </li>
EOT;

        $this->html($html);

        return "<nav aria-label=\"Page navigation\">{$this->startTag()}{$this->html()}{$this->endTag()}</nav>";
    }
}
