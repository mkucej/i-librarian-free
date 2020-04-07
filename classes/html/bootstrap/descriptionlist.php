<?php

namespace Librarian\Html\Bootstrap;

final class Descriptionlist extends Component {

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->elementName('dl');
        $this->addClass('row');
    }

    /**
     * Add list term (left side).
     *
     * @param string $term
     * @param string $classes
     */
    public function term(string $term, string $classes = ''): void {

        $this->append(<<<EOT
            <dt class="$classes">$term</dt>
EOT
        );
    }

    /**
     * Add list description (right side).
     *
     * @param string $description
     * @param string $classes
     */
    public function description(string $description, string $classes = ''): void {

        $this->append(<<<EOT
            <dd class="$classes">$description</dd>
EOT
        );
    }
}
