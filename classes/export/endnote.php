<?php

namespace Librarian\Export;

use Exception;
use Librarian\ItemMeta;
use Librarian\Security\Sanitation;

class Endnote {

    /**
     * @var ItemMeta
     */
    private $item_meta;

    /**
     * @var Sanitation
     */
    private $sanitation;

    public function __construct(ItemMeta $item_meta, Sanitation $sanitation) {

        $this->item_meta = $item_meta;
        $this->sanitation = $sanitation;
    }

    /**
     * @param array $items
     * @return string
     * @throws Exception
     */
    public function format(array $items): string {

        $output = '';

        $items = $this->sanitation->lmth($items);

        foreach ($items as $item) {

            // Type.
            $output .= '%0 ' . $this->item_meta->convert($item[ItemMeta::COLUMN['REFERENCE_TYPE']], 'il', 'endnote') . PHP_EOL;

            // Id.
            $output .= '%F ' . $item[ItemMeta::COLUMN['BIBTEX_ID']] . PHP_EOL;

            // Title.
            $output .= '%T ' . ($item[ItemMeta::COLUMN['TITLE']] ?? 'No title') . PHP_EOL;

            // Abstract
            if (!empty($item[ItemMeta::COLUMN['ABSTRACT']])) {

                $output .= '%X ' . $item[ItemMeta::COLUMN['ABSTRACT']] . PHP_EOL;
            }

            // Authors.
            if (!empty($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']])) {

                for ($i = 0; $i < count($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']]); $i++) {

                    $author = $item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][$i];
                    $author .= !empty($item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$i]) ? ', ' . $item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$i] : '';
                    $output .= '%A ' . $author . PHP_EOL;
                }
            }

            // Editors.
            if (!empty($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']])) {

                for ($i = 0; $i < count($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']]); $i++) {

                    $author = $item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][$i];
                    $author .= !empty($item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$i]) ? ', ' . $item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$i] : '';
                    $output .= '%E ' . $author . PHP_EOL;
                }
            }

            // Affiliation.
            if (!empty($item[ItemMeta::COLUMN['AFFILIATION']])) {

                $output .= '%+ ' . $item[ItemMeta::COLUMN['AFFILIATION']] . PHP_EOL;
            }

            // Publication titles.
            if (!empty($item[ItemMeta::COLUMN['PRIMARY_TITLE']])) {

                $output .= '%J ' . $item[ItemMeta::COLUMN['PRIMARY_TITLE']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['SECONDARY_TITLE']])) {

                $output .= '%B ' . $item[ItemMeta::COLUMN['SECONDARY_TITLE']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['TERTIARY_TITLE']])) {

                $output .= '%S ' . $item[ItemMeta::COLUMN['TERTIARY_TITLE']] . PHP_EOL;
            }

            // Year.
            if (!empty($item[ItemMeta::COLUMN['PUBLICATION_DATE']])) {

                $output .= '%D ' . substr($item[ItemMeta::COLUMN['PUBLICATION_DATE']], 0, 4) . PHP_EOL;
            }

            // Volume, issue, pages.
            if (!empty($item[ItemMeta::COLUMN['VOLUME']])) {

                $output .= '%V ' . $item[ItemMeta::COLUMN['VOLUME']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['ISSUE']])) {

                $output .= '%N ' . $item[ItemMeta::COLUMN['ISSUE']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['PAGES']])) {

                $output .= '%P ' . $item[ItemMeta::COLUMN['PAGES']] . PHP_EOL;
            }

            // Publisher.
            if (!empty($item[ItemMeta::COLUMN['PUBLISHER']])) {

                $output .= '%I ' . $item[ItemMeta::COLUMN['PUBLISHER']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['PLACE_PUBLISHED']])) {

                $output .= '%C ' . $item[ItemMeta::COLUMN['PLACE_PUBLISHED']] . PHP_EOL;
            }

            // DOI.
            $doi_key = array_search('DOI', $item[ItemMeta::COLUMN['UID_TYPES']]);

            if ($doi_key !== false) {

                $output .= '%M ' . $item[ItemMeta::COLUMN['UIDS']][$doi_key] . PHP_EOL;
            }

            // URLs.
            if (!empty($item[ItemMeta::COLUMN['URLS']])) {

                foreach ($item[ItemMeta::COLUMN['URLS']] as $url) {

                    if (empty($url)) {

                        continue;
                    }

                    $output .= '%U ' . $url . PHP_EOL;
                }
            }

            // Keywords.
            if (!empty($item[ItemMeta::COLUMN['KEYWORDS']])) {

                $output .= '%K ' . join(', ', $item[ItemMeta::COLUMN['KEYWORDS']]) . PHP_EOL;
            }

            // Custom.
            for ($i = 1; $i <= 4; $i++) {

                if (!empty($item[ItemMeta::COLUMN['CUSTOM' . $i]])) {

                    $output .= "%{$i} " . $item[ItemMeta::COLUMN['CUSTOM' . $i]] . PHP_EOL;
                }
            }

            // Close.
            $output .= PHP_EOL . PHP_EOL;

        }

        return $output;
    }
}
