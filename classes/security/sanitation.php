<?php

namespace Librarian\Security;

use Exception;

/**
 * Sanitation.
 *
 * All methods can sanitize both scalars and multidimensional arrays.
 *
 * @method array|string attr(array|string $input) Sanitize string for use in HTML element attributes.
 * @method array|string emptyToNull(array|string $input) Convert empty strings to nulls for db storage.
 * @method array|string html(array|string $input) Sanitize string for use in HTML.
 * @method array|string length(array|string $input, $length = null) Cap the length of a string to max value.
 * @method array|string lmth(array|string $input) Decode HTML back to the original string.
 * @method array|string trim(array|string $input) Trim white spaces.
 * @method array|string stripLow(array|string $input) Strip low ASCII chars.
 * @method array|string urlquery(array|string $input) Sanitize string for use in URL queries.
 * @method array|string xml(array|string $input) Sanitize string for use in XML documents.
 * @method array|string queryLike(array|string $input) Sanitize string for use in LIKE search.
 */
final class Sanitation {

    /**
     * Options for individual sanitizers.
     */
    private $options;

    /**
     * Adapter to call sanitation methods on both scalars and multidimensional arrays.
     *
     * @param  string $method
     * @param  array  $args
     * @return array|string
     * @throws Exception
     */
    public function __call(string $method, array $args) {

        $input = isset($args[0]) ? $args[0] : null;
        $this->options = isset($args[1]) ? $args[1] : null;

        if (method_exists($this, "_$method") === false) {

            throw new Exception("unknown sanitizer <kbd>$method</kbd>", 500);
        }

        if (is_array($input)) {

            // Recursive walk.
            array_walk_recursive($input, [$this, "_$method"]);

        } else {

            // Scalar.
            $this->{"_$method"}($input);
        }

        return $input;
    }

    /**
     * Sanitize string for use in HTML element attributes.
     *
     * @param string $value
     */
    protected function _attr(&$value) {

        $value = htmlspecialchars($value, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Convert empty strings to nulls for db storage purpose.
     *
     * @param string $value
     */
    protected function _emptyToNull(&$value) {

        $value = trim($value) === '' ? null : $value;
    }

    /**
     * Sanitize string for use in HTML.
     *
     * @param string $value
     */
    protected function _html(&$value) {

        $value = htmlspecialchars($value, ENT_HTML5, 'UTF-8');

        // Allowed tags. No attributes allowed.
        foreach (['b', 'i', 'sup', 'sub', 'u'] as $tag) {

            $value = str_ireplace(["&lt;{$tag}&gt;", "&lt;/{$tag}&gt;"], ["<{$tag}>", "</{$tag}>"], $value);
        }
    }

    /**
     * Cap the length of a string to max value.
     *
     * @param string $value
     * @throws Exception
     */
    protected function _length(&$value) {

        if (extension_loaded('mbstring') === false) {

            throw new Exception("missing PHP extension mbstring", 500);
        }

        $length = isset($this->options) && is_int($this->options) ? $this->options : 10 * 1024 * 1024;

        // UTF-8-safe substring.
        $value = mb_substr($value, 0, $length, 'UTF-8');
    }

    /**
     * Decode HTML back to the original string.
     *
     * Data from models is automatically encoded, which must be
     * reversed for data like JSON, images, etc.
     *
     * @param string $value
     */
    protected function _lmth(&$value) {

        $value = htmlspecialchars_decode($value, ENT_HTML5 | ENT_QUOTES);
    }

    /**
     * Strip low ASCII chars, except new line (A) and new page (C).
     *
     * @param string $value
     */
    protected function _stripLow(&$value) {

        $chars = [
            "\u{0000}", "\u{0001}", "\u{0002}", "\u{0003}",
            "\u{0004}", "\u{0005}", "\u{0006}", "\u{0007}",
            "\u{0008}", "\u{0009}", "\u{000B}", "\u{000E}",
            "\u{000F}", "\u{0010}", "\u{0011}", "\u{0012}",
            "\u{0013}", "\u{0014}", "\u{0015}", "\u{0016}",
            "\u{0017}", "\u{0018}", "\u{0019}", "\u{001A}",
            "\u{001B}", "\u{001C}", "\u{001D}", "\u{001E}",
            "\u{001F}", "\u{000D}",
        ];

        $value = str_replace($chars, '', $value);
    }

    /**
     * Trim white spaces.
     *
     * @param string $value
     */
    protected function _trim(&$value) {

        $value = trim($value);
    }

    /**
     * Sanitize string for use in URL queries.
     *
     * @param string $value
     */
    protected function _urlquery(&$value) {

        $value = rawurlencode($value);
    }

    /**
     * Sanitize string for use in XML documents.
     *
     * @param string $value
     */
    protected function _xml(&$value) {

        $value = htmlspecialchars($value, ENT_XML1 | ENT_NOQUOTES | ENT_DISALLOWED, 'UTF-8');
    }

    /**
     * Sanitize string for use in LIKE search.
     *
     * @param string $value
     */
    protected function _queryLike(&$value) {

        $esc = str_replace(["\\", "%", "_"], ["\\\\", "\%", "\_"], $value);
        $value = str_replace("*", "%", $esc);
    }
}
