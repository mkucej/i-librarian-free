<?php

namespace Librarian\Html;

/**
 * Class for HTML elements.
 *
 * Some common attributes:
 * @method Element|string accept(string $value = null)
 * @method Element|string action(string $value = null)
 * @method Element|string alt(string $value = null)
 * @method Element|string ariaHidden(string $value = null)
 * @method Element|string ariaLabel(string $value = null)
 * @method Element|string async(string $value = null)
 * @method Element|string autocomplete(string $value = null)
 * @method Element|string autofocus(string $value = null)
 * @method Element|string checked(string $value = null)
 * @method Element|string contenteditable(string $value = null)
 * @method Element|string dataBody(string $value = null)
 * @method Element|string dataBox(string $value = null)
 * @method Element|string dataButton(string $value = null)
 * @method Element|string dataId(string $value = null)
 * @method Element|string dataItemId(string $value = null)
 * @method Element|string dataPage(string $value = null)
 * @method Element|string dataTarget(string $value = null)
 * @method Element|string dataTitle(string $value = null)
 * @method Element|string dataToggle(string $value = null)
 * @method Element|string dataValue(string $value = null)
 * @method Element|string disabled(string $value = null)
 * @method Element|string href(string $value = null)
 * @method Element|string id(string $value = null)
 * @method Element|string max(string $value = null)
 * @method Element|string maxlength(string $value = null)
 * @method Element|string method(string $value = null)
 * @method Element|string min(string $value = null)
 * @method Element|string multiple(string $value = null)
 * @method Element|string name(string $value = null)
 * @method Element|string pattern(string $value = null)
 * @method Element|string placeholder(string $value = null)
 * @method Element|string readonly(string $value = null)
 * @method Element|string required(string $value = null)
 * @method Element|string role(string $value = null)
 * @method Element|string rows(string $value = null)
 * @method Element|string selected(string $value = null)
 * @method Element|string size(string $value = null)
 * @method Element|string spellcheck(string $value = null)
 * @method Element|string src(string $value = null)
 * @method Element|string style(string $value = null)
 * @method Element|string target(string $value = null)
 * @method Element|string title(string $value = null)
 * @method Element|string type(string $value = null)
 * @method Element|string value(string $value = null)
 */
class Element {

    protected array $attrs = [];

    protected array $classes = [];

    protected string $element_name = '';

    protected string $inner_html = '';

    protected array $uses_name = [
        'button',
        'form',
        'fieldset',
        'iframe',
        'input',
        'keygen',
        'object',
        'output',
        'select',
        'textarea',
        'map',
        'meta',
        'param'
    ];

    protected array $void_elements = [
        'area',
        'base',
        'br',
        'col',
        'command',
        'embed',
        'hr',
        'img',
        'input',
        'keygen',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr'
    ];

    /**
     * Constructor.
     *
     * @param string $element_name
     */
    public function __construct(string $element_name = 'div') {

        $this->element_name = $element_name;
    }

    /**
     * Method overloading calls $this->attr().
     *
     * @param string $method
     * @param array|null $args
     * @return string
     */
    public function __call(string $method, array $args = null): string {

        // Convert data|aria attribute from camel case to dashed: dataFoo -> data-foo.
        if (strpos($method, 'data') === 0 || strpos($method, 'aria') === 0) {

            $method = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $method));
        }

        $value = $args[0] ?? null;

        return $this->attr($method, $value);
    }

    /**
     * Get or set the element name for this component.
     *
     * @param string|null $element_name
     * @return string
     */
    public function elementName(string $element_name = null): string {

        // Setter.
        if (isset($element_name)) {

            $this->element_name = $element_name;
        }

        // Getter.
        return $this->element_name;
    }

    /**
     * Add a class to this element.
     *
     * @param  string $class
     * @return string
     */
    public function addClass(string $class): string {

        $this->classes = array_merge($this->classes, array_filter(explode(' ', $class)));

        return $this->attr('class', implode(' ', $this->classes));
    }

    /**
     * Remove a class from this element.
     *
     * @param  string $class
     * @return string
     */
    public function removeClass(string $class): string {

        $key = array_search($class, $this->classes);

        if (is_integer($key)) {

            unset($this->classes[$key]);
        }

        $classAttr = count($this->classes) === 0 ? '' : implode(' ', $this->classes);

        return $this->attr('class', $classAttr);
    }

    /**
     * Test if element has class.
     *
     * @param  string $class
     * @return boolean
     */
    public function hasClass(string $class): bool {

        return in_array($class, $this->classes);
    }

    /**
     * Style attr alternative.
     *
     * @param string|null $css
     * @return string
     */
    public function css(string $css = null): string {

        return $this->attr('style', $css);
    }

    /**
     * Get or set an HTML element attribute.
     *
     * @param string $name
     * @param string|null $value
     * @return string
     */
    public function attr(string $name, string $value = null): string {

        // Setter.
        if (isset($value)) {

            // Name attribute has a limited use.
            if ($name === 'name' && in_array($this->element_name, $this->uses_name) === false) {

                return '';
            }

            $this->attrs[$name] = $value;
        }

        // Getter.
        return $this->attrs[$name] ?? '';
    }

    /**
     * Remove an HTML element attribute.
     *
     * @param string $name
     * @return void
     */
    public function removeAttr(string $name): void {

        unset($this->attrs[$name]);
    }

    /**
     * Prepend to this element inner HTML.
     *
     * @param string $html
     * @return string
     */
    public function prepend(string $html): string {

        $this->inner_html = $html . $this->inner_html;

        return $this->inner_html;
    }

    /**
     * Append to this element inner HTML.
     *
     * @param string $html
     * @return string
     */
    public function append(string $html): string {

        $this->inner_html .= $html;

        return $this->inner_html;
    }

    /**
     * Get or set the inner HTML for this element.
     *
     * @param string|null $html
     * @return string
     */
    public function html(string $html = null): string {

        // Setter.
        if (isset($html)) {

            $this->inner_html = $html;
        }

        // Getter.
        return $this->inner_html;
    }

    /**
     * Build a starting HTML element tag.
     *
     * @return string
     */
    protected function startTag(): string {

        $startTag = "<$this->element_name";

        $attrs = $this->attrs;

        // Id, class and style first.
        if (isset($attrs['id'])) {

            $startTag .= " id=\"{$attrs['id']}\"";

            unset($attrs['id']);
        }

        if (isset($attrs['class'])) {

            $startTag .= " class=\"{$attrs['class']}\"";

            unset($attrs['class']);
        }

        if (isset($attrs['style'])) {

            $startTag .= " style=\"{$attrs['style']}\"";

            unset($attrs['style']);
        }

        // Then everything else.
        foreach ($attrs as $name => $value) {

            $startTag .= " $name=\"$value\"";
        }

        $startTag .= ">";

        return $startTag;
    }

    /**
     * Build an ending HTML element tag.
     *
     * @return string
     */
    protected function endTag(): string {

        return "</$this->element_name>";
    }

    /**
     * Render this element and its content.
     *
     * @return string
     */
    public function render(): string {

        // If the element uses a void tag...
        if (in_array($this->element_name, $this->void_elements) === true) {

            return $this->startTag();
        }

        return "{$this->startTag()}{$this->html()}{$this->endTag()}";
    }
}
