<?php

namespace Librarian\Media;

use Collator;
use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Html\Element;
use Librarian\ItemMeta;
use Normalizer;
use NumberFormatter;

final class ScalarUtils {

    private $di;

    public function __construct(DependencyInjector $di) {

        $this->di = $di;
    }

    public function versionToInteger(string $version) {

        $version_parts = explode('.', $version);

        return $version_parts[0] . sprintf('%02d', $version_parts[1]) . sprintf('%02d', $version_parts[2]);
    }

    /**
     * Convert array to HTML table code. Used in ErrorView.
     *
     * @param array $input
     * @return mixed
     * @throws Exception
     */
    public function arrayToTable(array $input = []): string {

        $key = key($input);

        /** @var Element $el */
        $el = $this->di->get('Element');

        $el->elementName('table');
        $el->addClass('text-left mx-auto my-3 w-100');
        $el->append(<<<THEAD
            <thead class="bg-darker-3">
                <tr>
                    <td class="p-3 border-darker" colspan="2">
                        <b>$key</b>
                    </td>
                </tr>
            </thead>
THEAD
);

        if (empty(current($input))) {

            $el->append(<<<TBODY
                <tbody>
                    <tr>
                        <td class="p-3 border-darker" colspan="2">
                        Empty
                        </td>
                    </tr>
                </tbody>
TBODY
            );

            $output = $el->render();

            $el = null;

            return $output;
        }

        $sanitation = $this->di->getShared('Sanitation');

        $rows = '';

        foreach (current($input) as $key => $value) {

            $key = $sanitation->html($key);
            $value = $sanitation->html($value);

            if (is_array($key)) {

                $key = var_export($key, true);
            }

            if (is_array($value)) {

                $value = var_export($value, true);
            }

            $value = "<pre style=\"color:inherit;white-space: pre-wrap;word-break: break-all;margin:0\">$value</pre>";

            $rows .= <<<ROW
                <tr>
                    <td class="p-3 border-darker">$key</td>
                    <td class="p-3 border-darker">$value</td>
                </tr>
ROW;
        }

        $el->append(<<<TBODY
            <tbody>
                $rows
            </tbody>
TBODY
        );

        $output = $el->render();

        return $output;
    }

    public function formatBytes($bytes, int $precision = 1): string {

        $units = ['B', 'kB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return $this->formatNumber($bytes, $precision) . ' ' . $units[$pow];
    }

    public function unformatBytes(string $bytes) {

        switch (substr($bytes, -1)) {
            case 'k':
            case 'K':
                return (integer) trim(substr($bytes, 0, -1)) * 1024;
            case 'm':
            case 'M':
                return (integer) trim(substr($bytes, 0, -1)) * 1024 * 1024;
            case 'g':
            case 'G':
                return (integer) trim(substr($bytes, 0, -1)) * 1024 * 1024 * 1024;
        }

        switch (substr($bytes, -2)) {
            case 'kb':
            case 'Kb':
            case 'kB':
            case 'KB':
                return (integer) trim(substr($bytes, 0, -2)) * 1024;
            case 'mb':
            case 'Mb':
            case 'mB':
            case 'MB':
                return (integer) trim(substr($bytes, 0, -2)) * 1024 * 1024;
            case 'gb':
            case 'Gb':
            case 'gB':
            case 'GB':
                return (integer) trim(substr($bytes, 0, -2)) * 1024 * 1024 * 1024;
        }

        // Fallback. Return input.
        return $bytes;
    }

    /**
     * Locale-aware number formatting.
     *
     * @param  integer|float $number
     * @param  integer       $precision
     * @return string
     */
    public function formatNumber($number, int $precision = 0): string {

        if (extension_loaded('intl') === false) {

            return number_format($number, $precision, '.', ',');
        }

        $fmt = new NumberFormatter(
            IL_LANGUAGE,
            NumberFormatter::DECIMAL
        );

        $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $precision);

        return $fmt->format($number);
    }

    /**
     * Generate custom PDF filename for downloads.
     *
     * @param array $format
     * @param array $item
     * @return string
     */
    public function customFilename(array $format, array $item): string {

        $output = '';

        foreach ($format as $column) {

            switch ($column) {

                case 'author':

                    if (!empty($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][0])) {

                        $output .= $item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][0];

                    } elseif (!empty($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][0])) {

                        $output .= $item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][0];

                    } else {

                        $output .= 'unknown';
                    }

                    break;

                case 'id':

                    $output .= str_pad($item['id'], 9, '0', STR_PAD_LEFT);

                    break;

                case 'publication':

                    if (!empty($item[ItemMeta::COLUMN['PRIMARY_TITLE']])) {

                        $output .= $item[ItemMeta::COLUMN['PRIMARY_TITLE']];

                    } elseif (!empty($item[ItemMeta::COLUMN['SECONDARY_TITLE']])) {

                        $output .= $item[ItemMeta::COLUMN['SECONDARY_TITLE']];

                    } elseif (!empty($item[ItemMeta::COLUMN['TERTIARY_TITLE']])) {

                        $output .= $item[ItemMeta::COLUMN['TERTIARY_TITLE']];

                    } else {

                        $output .= 'unknown';
                    }

                    break;

                case 'title':

                    $output .= !empty($item[ItemMeta::COLUMN['TITLE']]) ?
                        mb_substr($item[ItemMeta::COLUMN['TITLE']], 0, 30) :
                        'unknown';

                    break;

                case 'year':

                    $output .= !empty($item[ItemMeta::COLUMN['PUBLICATION_DATE']]) ?
                        substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']], 0, 4) :
                        'unknown';

                    break;

                case '':
                case '-':
                case '_':

                    $output .= $column;

                break;

                case "' '":

                    $output .= ' ';

                    break;
            }
        }

        $output = empty($output) ? str_pad($item['id'], 9, '0', STR_PAD_LEFT) : $output;

        return $output . '.pdf';
    }

    /**
     * @param array $format
     * @param array $item
     * @return string
     * @throws Exception
     */
    public function customBibtexId(array $format, array $item): string {

        $output = '';

        foreach ($format as $column) {

            switch ($column) {

                case 'author':

                    if (!empty($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][0])) {

                        $output .= $this->deaccent($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][0]);

                    } elseif (!empty($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][0])) {

                        $output .= $this->deaccent($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][0]);

                    } else {

                        $output .= 'unknown';
                    }

                    break;

                case 'id':

                    $output .= 'ID' . $item['id'];

                    break;

                case 'publication':

                    if (!empty($item[ItemMeta::COLUMN['PRIMARY_TITLE']])) {

                        $output .= mb_substr($item[ItemMeta::COLUMN['PRIMARY_TITLE']], 0, 20);

                    } elseif (!empty($item[ItemMeta::COLUMN['SECONDARY_TITLE']])) {

                        $output .= mb_substr($item[ItemMeta::COLUMN['SECONDARY_TITLE']], 0, 20);

                    } elseif (!empty($item[ItemMeta::COLUMN['TERTIARY_TITLE']])) {

                        $output .= mb_substr($item[ItemMeta::COLUMN['TERTIARY_TITLE']], 0, 20);

                    } else {

                        $output .= 'unknown';
                    }

                    break;

                case 'title':

                    $output .= !empty($item[ItemMeta::COLUMN['TITLE']]) ?
                        $this->deaccent(mb_substr($item[ItemMeta::COLUMN['TITLE']], 0, 20)) :
                        'unknown';

                    break;

                case 'year':

                    $output .= !empty($item[ItemMeta::COLUMN['PUBLICATION_DATE']]) ?
                        substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']], 0, 4) :
                        'unknown';

                    break;

                case '':
                case '-':
                case '_':

                    $output .= $column;

                    break;
            }
        }

        $output = str_replace(' ', '', $output);

        return $output;
    }

    /**
     * De-accent Latin characters.
     *
     * @param string|null $input
     * @param bool $preserve_case
     * @return string|null
     * @throws Exception
     */
    public function deaccent(string $input = null, bool $preserve_case = true) {

        if (isset($input) === false) {

            return null;
        }

        // Pre-convert some characters that SQLite does not normalize.
        $chars = [
            "\u{00D0}" => 'D',
            "\u{00D8}" => 'O',
            "\u{00DE}" => 'T',
            "\u{00DF}" => 's',
            "\u{00F0}" => 'd',
            "\u{00F8}" => 'o',
            "\u{00FE}" => 't',
            "\u{0110}" => 'D',
            "\u{0111}" => 'd',
            "\u{0126}" => 'H',
            "\u{0127}" => 'h',
            "\u{0131}" => 'i',
            "\u{0138}" => 'k',
            "\u{013F}" => 'L',
            "\u{0140}" => 'l',
            "\u{0141}" => 'L',
            "\u{0142}" => 'l',
            "\u{0149}" => 'n',
            "\u{014A}" => 'n',
            "\u{014B}" => 'n',
            "\u{0166}" => 'T',
            "\u{0167}" => 't',
            "\u{017F}" => 's'
        ];

        // There can be some huge texts coming in. Process it in chunks.
        $output = '';
        $size = 100000;
        $length = mb_strlen($input, 'UTF-8');

        for($i = 0; $i < $length; $i = $i + $size) {

            $chunk = mb_substr($input, $i, $size, 'UTF-8');

            // Pre-convert some characters that SQLite does not normalize.
            $chunk = str_replace(array_keys($chars), array_values($chars), $chunk);

            // Decompose and remove marks.
            $chunk = Normalizer::normalize($chunk, Normalizer::FORM_KD);
            $chunk = preg_replace('/\pM/u', '', $chunk);

            if ($preserve_case === false) {

                $chunk = mb_strtolower($chunk);

                // Greek final sigma rule.
                $chunk = preg_replace("/\u{03C3}\b/u", "\u{03C2}", $chunk);
            }

            $output .= $chunk;
        }

        return $output;
    }

    /**
     * Normalize UTF-8 using Compatibility decomposition and composition.
     *
     * @param string $input
     * @return string
     * @throws Exception
     */
    public function normalizeUtf8(string $input): string {

        return Normalizer::normalize($input, Normalizer::FORM_C);
    }

    /**
     * Custom UTF-8 collation for SQLite.
     *
     * @param string $string_a
     * @param string $string_b
     * @return int
     * @throws Exception
     */
    public function utf8Collation(string $string_a, string $string_b): int {

        $collator = new Collator(IL_LANGUAGE);

        return $collator->compare($string_a, $string_b);
    }

    /**
     * Test if string is DOI.
     *
     * @param string $doi
     * @return bool
     */
    public function isDoi(string $doi): bool {

        $match = preg_match('/10\.\d{4,5}\.?\d*\/\S+/ui', $doi);

        return $match === 1 ? true : false;
    }
}
