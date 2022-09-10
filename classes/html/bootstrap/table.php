<?php

namespace Librarian\Html\Bootstrap;

use Librarian\Mvc\TextView;

/**
 * Bootstrap's table.
 */
final class Table extends Component {

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'table';
        $this->addClass('table');
    }

    /**
     * Table head.
     *
     * @param  array  $cells      [[innerHTML, attrs],[innerHTML, attrs]]
     * @param  string $classes
     * @return string
     */
    public function head(array $cells = [], string $classes = ''): string {

        $innerHead = '';

        foreach ($cells as $cell) {

            $attr = isset($cell[1]) ? " $cell[1]" : "";
            $innerHead .= "<th$attr>$cell[0]</th>";
        }

        $head_classes = empty($classes) ? "" : " class=\"$classes\"";

        return $this->append("<thead$head_classes><tr>$innerHead</tr></thead>");
    }

    public function bodyRow(array $cells = [], string $classes = ''): string {

        $innerHead = '';

        foreach ($cells as $cell) {

            $attr = isset($cell[1]) ? " $cell[1]" : "";
            $innerHead .= "<td$attr>$cell[0]</td>";
        }

        $row_classes = empty($classes) ? "" : " class=\"$classes\"";

        return $this->append("<tr$row_classes>$innerHead</tr>");
    }

    public function foot(array $cells = [], string $classes = ''): string {

        $innerHead = '';

        foreach ($cells as $cell) {

            $attr = isset($cell[1]) ? " $cell[1]" : "";
            $innerHead .= "<td$attr>$cell[0]</td>";
        }

        $foot_classes = empty($classes) ? "" : " class=\"$classes\"";

        return $this->append("<tfoot$foot_classes><tr>$innerHead</tr></tfoot>");
    }

    public function render(): string {

        $theme_class_table = TextView::$theme === 'dark' ? 'table-dark' : '';
        $this->addClass($theme_class_table);

        // Size.
        if (!empty($this->size)) {

            $sizeClass = $this->size === 'small' ? ' table-sm' : '';
            $this->addClass($sizeClass);
        }

        return parent::render();
    }
}
