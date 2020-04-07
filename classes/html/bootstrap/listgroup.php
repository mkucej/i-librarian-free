<?php

namespace Librarian\Html\Bootstrap;

use Librarian\Mvc\TextView;

/**
 * Bootstrap's list group.
 *
 * @link https://getbootstrap.com/docs/4.0/components/list-group/
 */
final class ListGroup extends Component {

    private $theme_class = '';

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();
        $this->addClass('list-group list-group-flush rounded-0');

        // Theme.
        if (TextView::$theme === 'dark') {

            $this->theme_class = 'bg-dark text-white';
        }
    }

    /**
     * Unordered list. Adds a <li> element.
     *
     * @param string $html
     * @param string $class
     */
    public function li(string $html, string $class = null): void {

        $this->element_name = 'ul';

        $class = isset($class) ? "$class" : "";

        $this->append(<<<EOT
            <li class="list-group-item rounded-0 {$this->theme_class} {$class}">{$html}</li>
EOT
        );
    }

    /**
     * List of buttons. Adds a <button> element.
     *
     * @param string $html
     * @param string $class
     * @param string $attrs
     */
    public function button(string $html, string $class = null, string $attrs = null): void {

        $this->element_name = 'div';

        $class = isset($class) ? " $class" : "";
        $attrs = isset($attrs) ? " $attrs" : "";

        $this->append(<<<EOT
            <button type="button" class="list-group-item rounded-0 {$this->theme_class} {$class}" {$attrs}>{$html}</button>
EOT
        );
    }

    /**
     * List of hyperlinks. Adds an <a> element.
     *
     * @param string $url
     * @param string $html
     * @param string $class
     */
    public function link(string $url, string $html, string $class = null): void {

        $this->element_name = 'div';

        $class = isset($class) ? " $class" : "";

        $this->append(<<<EOT
            <a href="$url" class="list-group-item list-group-item-action rounded-0 {$this->theme_class} {$class}">{$html}</a>
EOT
        );
    }

    /**
     * List of divs. Adds a <div> element.
     *
     * @param string $html
     * @param string $class
     */
    public function div(string $html, $class = null): void {

        $this->element_name = 'div';

        $class = isset($class) ? " $class" : "";

        $this->append(<<<EOT
            <div class="list-group-item rounded-0 {$this->theme_class} {$class}">{$html}</div>
EOT
        );
    }
}
