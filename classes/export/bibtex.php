<?php

namespace Librarian\Export;

use Exception;
use Librarian\AppSettings;
use Librarian\ItemMeta;
use Librarian\Security\Sanitation;

class Bibtex {

    /**
     * @var AppSettings
     */
    private $app_settings;

    /**
     * @var ItemMeta
     */
    private $item_meta;

    /**
     * @var Sanitation
     */
    private $sanitation;

    public function __construct(ItemMeta $item_meta, Sanitation $sanitation, AppSettings $app_settings) {

        $this->app_settings = $app_settings;
        $this->item_meta = $item_meta;
        $this->sanitation = $sanitation;
    }

    /**
     * @param array $items
     * @param bool $abstracts Include abstracts?
     * @return string
     * @throws Exception
     */
    public function format(array $items, bool $abstracts = false): string {

        $output = '';

        foreach ($items as $item) {

            // Type.
            $bibtex_type = empty($item[ItemMeta::COLUMN['BIBTEX_TYPE']]) ? 'article' : $item[ItemMeta::COLUMN['BIBTEX_TYPE']];
            $output .= '@' . $bibtex_type . '{';

            // Key.
            $output .= ($item[ItemMeta::COLUMN['BIBTEX_ID']] ?? '?') . ',' . PHP_EOL;

            // Title.
            $output .= $this->prettyTag('title') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['TITLE']] ?? 'No title') . '},' . PHP_EOL;

            // Abstract
            if ($abstracts && !empty($item[ItemMeta::COLUMN['ABSTRACT']])) {

                $output .= $this->prettyTag('abstract') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['ABSTRACT']]) . '},' . PHP_EOL;
            }

            // Authors.
            if (!empty($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']])) {

                $authors = [];

                for ($i = 0; $i < count($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']]); $i++) {

                    $authors[$i] = $item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][$i];
                    $authors[$i] .= !empty($item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$i]) ? ', ' . $item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$i] : '';

                    // Is this an institution?
                    if (empty($item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$i])) {

                        $authors[$i] = '{' . $authors[$i] . '}';
                    }
                }

                $authors_str = join(' and ', $authors);

                $output .= $this->prettyTag('author') . '{' . $this->prettyValue($authors_str) . '},' . PHP_EOL;
            }

            // Editors.
            if (!empty($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']])) {

                $editors = [];

                for ($i = 0; $i < count($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']]); $i++) {

                    $editors[$i] = $item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][$i];
                    $editors[$i] .= !empty($item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$i]) ? ', ' . $item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$i] : '';

                    // Is this an institution?
                    if (empty($item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$i])) {

                        $editors[$i] = '{' . $editors[$i] . '}';
                    }
                }

                $editors_str = join(' and ', $editors);

                $output .= $this->prettyTag('editor') . '{' . $this->prettyValue($editors_str) . '},' . PHP_EOL;
            }

            // Publication titles.
            switch ($item[ItemMeta::COLUMN['BIBTEX_TYPE']]) {

                case 'article':
                    $pub_title = empty($item[ItemMeta::COLUMN['PRIMARY_TITLE']]) ?
                        $item[ItemMeta::COLUMN['SECONDARY_TITLE']] : $item[ItemMeta::COLUMN['PRIMARY_TITLE']];
                    $output .= $this->prettyTag('journal') . '{' . $this->prettyValue($pub_title) . '},' . PHP_EOL;
                    break;

                case 'conference':
                case 'inproceedings':
                case 'incollection':
                    $output .= $this->prettyTag('booktitle') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['SECONDARY_TITLE']] ?? '') . '},' . PHP_EOL;
                    $output .= $this->prettyTag('series') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['TERTIARY_TITLE']] ?? '') . '},' . PHP_EOL;
                    break;

                case 'book':
                    $series = $item[ItemMeta::COLUMN['TERTIARY_TITLE']] ?? $item[ItemMeta::COLUMN['SECONDARY_TITLE']];
                    $output .= $this->prettyTag('series') . '{' . $this->prettyValue($series ?? '') . '},' . PHP_EOL;
                    break;

                case 'techreport':
                    $output .= $this->prettyTag('institution') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['SECONDARY_TITLE']] ?? '') . '},' . PHP_EOL;
                    break;

                case 'mastersthesis':
                case 'phdthesis':
                    $output .= $this->prettyTag('school') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['SECONDARY_TITLE']] ?? '') . '},' . PHP_EOL;
                    break;

                case 'patent':
                    $output .= $this->prettyTag('source') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['AFFILIATION']] ?? '') . '},' . PHP_EOL;
                    $output .= $this->prettyTag('holder') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['AFFILIATION']] ?? '') . '},' . PHP_EOL;
                    break;
            }

            // Volume, issue, pages.
            if (!empty($item[ItemMeta::COLUMN['VOLUME']])) {

                $output .= $this->prettyTag('volume') . '{' . $item[ItemMeta::COLUMN['VOLUME']] . '},' . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['ISSUE']])) {

                $output .= $this->prettyTag('number') . '{' . $item[ItemMeta::COLUMN['ISSUE']] . '},' . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['PAGES']])) {

                $output .= $this->prettyTag('pages') . '{' . str_replace('-', '--', $item[ItemMeta::COLUMN['PAGES']]) . '},' . PHP_EOL;
            }

            // Year, month.
            if (!empty($item[ItemMeta::COLUMN['PUBLICATION_DATE']])) {

                $year = substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']], 0, 4);
                $output .= $this->prettyTag('year') . '{' . $year . '},' . PHP_EOL;

                $month = (int) substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']], 5, 2);

                if ($month > 0 && $month <= 12) {

                    $output .= $this->prettyTag('month') . '{' . $month . '},' . PHP_EOL;
                }
            }

            // Publisher.
            if (!empty($item[ItemMeta::COLUMN['PUBLISHER']])) {

                $output .= $this->prettyTag('publisher') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['PUBLISHER']]) . '},' . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['PLACE_PUBLISHED']])) {

                $output .= $this->prettyTag('address') . '{' . $this->prettyValue($item[ItemMeta::COLUMN['PLACE_PUBLISHED']]) . '},' . PHP_EOL;
            }

            // URLs.
            if (!empty($item[ItemMeta::COLUMN['URLS']])) {

                foreach ($item[ItemMeta::COLUMN['URLS']] as $url) {

                    if (empty($url)) {

                        continue;
                    }

                    $output .= $this->prettyTag('url') . '{' . trim($url) . '},' . PHP_EOL;
                }
            }

            // UIDs.
            if (!empty($item[ItemMeta::COLUMN['UIDS']])) {

                for ($i = 0; $i < count($item[ItemMeta::COLUMN['UIDS']]); $i++) {

                    $output .= $this->prettyTag(strtolower($item[ItemMeta::COLUMN['UID_TYPES']][$i])) . '{' . $item[ItemMeta::COLUMN['UIDS']][$i] . '},' . PHP_EOL;
                }
            }

            // Custom.
            for ($i = 1; $i <= 8; $i++) {

                if (!empty($item[ItemMeta::COLUMN['CUSTOM' . $i]])) {

                    $tag = strtolower(str_replace(' ', '-', $this->app_settings->getGlobal('custom' . $i)));

                    $output .= $this->prettyTag($tag) . '{' . $item[ItemMeta::COLUMN['CUSTOM' . $i]] . '},' . PHP_EOL;
                }
            }

            // Keywords.
            if (!empty($item[ItemMeta::COLUMN['KEYWORDS']])) {

                $output .= $this->prettyTag('keywords') . '{' . $this->prettyValue(join(', ', $item[ItemMeta::COLUMN['KEYWORDS']])) . '},' . PHP_EOL;
            }

            // PDF link.
            if (!empty($item['file'])) {

                $output .= $this->prettyTag('file') . '{FULLTEXT:' . $item['file'] . ':PDF}' . PHP_EOL;
            }

            // Close.
            $output .= '}' . PHP_EOL . PHP_EOL;
        }

        return $output;
    }

    private function prettyTag(string $tag): string {

        return str_repeat(' ', 2) . str_pad($tag, 10, ' ', STR_PAD_RIGHT) . '= ';
    }

    private function prettyValue(string $value): string {

        $value = $this->sanitation->lmth($value);
        $escaped_value = $this->escapeValue($value);

        return trim(wordwrap($escaped_value, 75, PHP_EOL . str_repeat(' ', 15)));
    }

    private function escapeValue(string $value): string {

        $value = str_replace('&', '\&', $value);
        $value = str_replace('%', '\%', $value);

        return preg_replace('/(\p{Lu}+)/u', '{$1}', $value);
    }
}
