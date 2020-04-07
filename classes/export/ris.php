<?php

namespace Librarian\Export;

use Exception;
use Librarian\ItemMeta;
use Librarian\Security\Sanitation;

class Ris {

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
            $output .= 'TY  - ' . $this->item_meta->convert($item[ItemMeta::COLUMN['REFERENCE_TYPE']], 'il', 'ris') . PHP_EOL;

            // Id.
            $output .= 'ID  - ' . $item[ItemMeta::COLUMN['BIBTEX_ID']] . PHP_EOL;

            // Title.
            $output .= 'TI  - ' . ($item[ItemMeta::COLUMN['TITLE']] ?? 'No title') . PHP_EOL;

            // Abstract
            if (!empty($item[ItemMeta::COLUMN['ABSTRACT']])) {

                $output .= 'AB  - ' . $item[ItemMeta::COLUMN['ABSTRACT']] . PHP_EOL;
            }

            // Authors.
            if (!empty($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']])) {

                for ($i = 0; $i < count($item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']]); $i++) {

                    $author = $item[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][$i];
                    $author .= !empty($item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$i]) ? ', ' . $item[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][$i] : '';
                    $output .= 'AU  - ' . $author . PHP_EOL;
                }
            }

            // Editors.
            if (!empty($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']])) {

                for ($i = 0; $i < count($item[ItemMeta::COLUMN['EDITOR_LAST_NAME']]); $i++) {

                    $author = $item[ItemMeta::COLUMN['EDITOR_LAST_NAME']][$i];
                    $author .= !empty($item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$i]) ? ', ' . $item[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][$i] : '';
                    $output .= 'ED  - ' . $author . PHP_EOL;
                }
            }

            // Affiliation.
            if (!empty($item[ItemMeta::COLUMN['AFFILIATION']])) {

                $output .= 'AD  - ' . $item[ItemMeta::COLUMN['AFFILIATION']] . PHP_EOL;
            }

            // Publication titles.
            if (!empty($item[ItemMeta::COLUMN['PRIMARY_TITLE']])) {

                $output .= 'J2  - ' . $item[ItemMeta::COLUMN['PRIMARY_TITLE']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['SECONDARY_TITLE']])) {

                $output .= 'T2  - ' . $item[ItemMeta::COLUMN['SECONDARY_TITLE']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['TERTIARY_TITLE']])) {

                $output .= 'T3  - ' . $item[ItemMeta::COLUMN['TERTIARY_TITLE']] . PHP_EOL;
            }

            // Year.
            if (!empty($item[ItemMeta::COLUMN['PUBLICATION_DATE']])) {

                $output .= 'DA  - ' . str_replace('-', '/', $item[ItemMeta::COLUMN['PUBLICATION_DATE']]) . '/' . PHP_EOL;
            }

            // Volume, issue, pages.
            if (!empty($item[ItemMeta::COLUMN['VOLUME']])) {

                $output .= 'VL  - ' . $item[ItemMeta::COLUMN['VOLUME']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['ISSUE']])) {

                $output .= 'IS  - ' . $item[ItemMeta::COLUMN['ISSUE']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['PAGES']])) {

                $pages = str_replace('--', '-', $item[ItemMeta::COLUMN['PAGES']]);
                $parts = explode('-', $pages);

                $output .= 'SP  - ' . $parts[0] . PHP_EOL;

                if (!empty($parts[1])) {

                    $output .= 'EP  - ' . $parts[1] . PHP_EOL;
                }
            }

            // Publisher.
            if (!empty($item[ItemMeta::COLUMN['PUBLISHER']])) {

                $output .= 'PB  - ' . $item[ItemMeta::COLUMN['PUBLISHER']] . PHP_EOL;
            }

            if (!empty($item[ItemMeta::COLUMN['PLACE_PUBLISHED']])) {

                $output .= 'CY  - ' . $item[ItemMeta::COLUMN['PLACE_PUBLISHED']] . PHP_EOL;
            }

            // DOI.
            $doi_key = array_search('DOI', $item[ItemMeta::COLUMN['UID_TYPES']]);

            if ($doi_key !== false) {

                $output .= 'DO  - ' . $item[ItemMeta::COLUMN['UIDS']][$doi_key] . PHP_EOL;
            }

            // UIDS.
            if (!empty($item[ItemMeta::COLUMN['UIDS']])) {

                for ($i = 0; $i < count($item[ItemMeta::COLUMN['UIDS']]); $i++) {

                    $output .= 'M2  - ' . $item[ItemMeta::COLUMN['UID_TYPES']][$i] . ': ' . $item[ItemMeta::COLUMN['UIDS']][$i] . PHP_EOL;
                }
            }

            // URLs.
            if (!empty($item[ItemMeta::COLUMN['URLS']])) {

                foreach ($item[ItemMeta::COLUMN['URLS']] as $url) {

                    if (empty($url)) {

                        continue;
                    }

                    $output .= 'UR  - ' . $url . PHP_EOL;
                }
            }

            // Keywords.
            if (!empty($item[ItemMeta::COLUMN['KEYWORDS']])) {

                $output .= 'KW  - ' . join(', ', $item[ItemMeta::COLUMN['KEYWORDS']]) . PHP_EOL;
            }

            // Custom.
            for ($i = 1; $i <= 4; $i++) {

                if (!empty($item[ItemMeta::COLUMN['CUSTOM' . $i]])) {

                    $output .= "C{$i}  - " . $item[ItemMeta::COLUMN['CUSTOM' . $i]] . PHP_EOL;
                }
            }

            // PDF link.
            if (!empty($item['file'])) {

                $output .= 'L1  - ' . $item['file'] . PHP_EOL;
            }

            // Close.
            $output .= 'ER  -' . PHP_EOL . PHP_EOL;

        }

        return $output;
    }
}
