<?php

namespace Librarian\Media;

use Locale;

/**
 * Class Language.
 *
 * Parses provided IETF language code into an ICU locale code.
 * Provides locale code to other classes.
 * Translates tokens based on the locale.
 */
final class Language {

    /**
     * @var string Default ICU language code.
     */
    private $language = 'en_US';

    /**
     * @var array Translations.
     */
    private $tokens;

    /**
     * Controller provides IETF language code, which is converted into an ICU locale.
     * Corresponding token translations are loaded. Only the primary language and region are supported.
     *
     * @param string $language IETF language code.
     */
    public function setLanguage(string $language): void {

        $locale_arr = Locale::parseLocale($language);
        $this->language = Locale::composeLocale(array_intersect_key($locale_arr, ['language' => '', 'region' => '']));
        $this->loadTokens();
    }

    /**
     * Get language property.
     *
     * @return string ICU locale code.
     */
    public function getLanguage(): string {

        return $this->language;
    }

    /**
     * Load tokens into an array.
     *
     * Checks for the presence of a regional-specific file, then tries the primary language file.
     */
    private function loadTokens() {

        $this->tokens = [];

        if ($this->language === 'en_US') {

            return;
        }

        $location = IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'l10n';
        $l10n_file_regional = $location . DIRECTORY_SEPARATOR . $this->language . '.ini';
        $l10n_file_primary = $location . DIRECTORY_SEPARATOR . Locale::getPrimaryLanguage($this->language) . '.ini';

        if (is_readable($l10n_file_regional) === true) {

            $this->tokens = parse_ini_file($l10n_file_regional, INI_SCANNER_RAW);

        } elseif (is_readable($l10n_file_primary) === true) {

            $this->tokens = parse_ini_file($l10n_file_primary, INI_SCANNER_RAW);
        }

        // Ini parsing silent fail.
        if ($this->tokens === false) {

            $this->tokens = [];
        }
    }

    /**
     * Translating a token. Used in PHP files.
     *
     * @param string $token
     * @return string Translated token.
     */
    public function t9n(string $token): string {

        if ($this->language === 'en_US') {

            return $this->toEnglish($token);
        }

        return $this->tokens[$token] ?? $this->toEnglish($token);
    }

    /**
     * Translating a text with {T% token %T} placeholders. Used for non-PHP templates.
     *
     * @param string $input
     * @return string Translated input.
     * @todo
     */
    public function t9nText(string $input): string {

        if ($this->language === 'en') {

            return $input;
        }

        $tokens = $this->tokens;

        return preg_replace_callback(
            '\{T% (.*) %T\}/u',
            function ($matches) use ($tokens) {
                return $tokens[$matches[1]] ?? $this->toEnglish($matches[1]);
            },
            $input
        );
    }

    /**
     * Remove metadata from token key.
     *
     * @param string $token
     * @return string English token.
     */
    private function toEnglish(string $token): string {

        if (mb_strpos($token, '-VERB') !== false || mb_strpos($token, '-NOUN') !== false) {

            return mb_substr($token, 0, -5);
        }

        return $token;
    }
}
