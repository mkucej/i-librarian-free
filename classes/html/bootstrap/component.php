<?php

namespace Librarian\Html\Bootstrap;

use Librarian\Html\Element;

/**
 * Class for common methods for Bootstrap components.
 */
class Component extends Element {

    protected string $context = 'default';
    protected string $size = '';

    /**
     * Bootstrap's context.
     *
     * @param string|null $context default, primary, secondary, success, danger, warning, info, light, dark
     * @return string
     */
    public function context(string $context = null): string {

        // Setter.
        if (isset($context)) {

            $this->context = $context;
        }

        // Getter.
        return $this->context;
    }

    /**
     * Bootstrap's component size.
     *
     * @param string|null $size large, small
     * @return string
     */
    public function componentSize(string $size = null): string {

        // Setter.
        if (isset($size)) {

            $this->size = $size;
        }

        // Getter.
        return $this->size;
    }

    /**
     * Bootstrap's tooltip.
     *
     * @param string $html
     * @param string $placement
     */
    public function tooltip(string $html, string $placement = 'top'): void {

        $this->attr('data-toggle', 'tooltip');
        $this->attr('data-html', 'true');
        $this->attr('title', $html);
        $this->attr('data-placement', $placement);
        $this->attr('data-animation', 'false');
        $this->attr('data-trigger', 'hover');
    }

    /**
     * Bootstrap's popover.
     *
     * @param string $content
     * @param string|null $title
     * @param string $placement
     * @param string $container
     */
    public function popover(string $content, string $title = null, string $placement = 'top', string $container = 'body'): void {

        $this->attr('data-toggle', 'popover');
        $this->attr('data-html', 'true');
        $this->attr('title', $title);
        $this->attr('data-content', $content);
        $this->attr('data-placement', $placement);
        $this->attr('data-container', $container);
        $this->attr('data-animation', 'false');
    }
}
