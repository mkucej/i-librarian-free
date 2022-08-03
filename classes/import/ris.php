<?php

namespace Librarian\Import;

use Exception;
use Librarian\ItemMeta;

class Ris {

    /**
     * @var string Imported file contents.
     */
    private $input;

    /**
     * @var ItemMeta
     */
    private $item_meta;

    /**
     * @var array Entry offsets.
     */
    private $offsets;

    public function __construct(ItemMeta $item_meta, string $input) {

        $this->input = $input;
        $this->item_meta = $item_meta;

        // Read entry offsets.
        preg_match_all("/TY {2}- /u", $input, $matches, PREG_OFFSET_CAPTURE);
        $this->offsets = empty($matches[0]) ? [] : array_column($matches[0], 1);
    }

    /**
     * Entry iterator.
     *
     * @return array|null
     * @throws Exception
     */
    public function getEntry() {

        // There are no entries to return.
        if (empty($this->offsets)) {

            return null;
        }

        $entry = [];
        $key = key($this->offsets);
        $offset = current($this->offsets);
        $length = isset($this->offsets[$key + 1]) ? $this->offsets[$key + 1] - $offset : strlen($this->input) - $offset;

        // Get item string.
        $str = substr($this->input, $offset, $length);

        // Remove current offset from offsets.
        array_shift($this->offsets);

        // Remove line breaks from pretty-printed input.
        $inner = preg_replace('/\r/u', '', trim($str));
        $inner = preg_replace('/\n(?![A-Z0-9]{2})/u', ' ', $inner);

        // Separate into tags.
        $parts = explode("\n", $inner);

        // All inner entry tags.
        foreach ($parts as $part) {

            $left = substr($part, 0, 2);
            $right = substr($part, 6);

            if (empty($left) || empty($right)) {

                continue;
            }

            switch ($left) {

                case 'TY':
                    // IL type.
                    $entry[ItemMeta::COLUMN['REFERENCE_TYPE']] = $this->item_meta->convert($right, 'ris', 'il');
                    break;

                case 'TI':
                case 'T1':
                    $entry[ItemMeta::COLUMN['TITLE']] = $right;
                    break;

                case 'JA':
                case 'J2':
                    $entry[ItemMeta::COLUMN['PRIMARY_TITLE']] = $right;
                    break;

                case 'JF':
                case 'JO':
                case 'BT':
                case 'T2':
                    $entry[ItemMeta::COLUMN['SECONDARY_TITLE']] = $right;
                    break;

                case 'T3':
                    $entry[ItemMeta::COLUMN['TERTIARY_TITLE']] = $right;
                    break;

                case 'PY':
                case 'Y1':
                case 'DA':
                    $year = (int) substr($right, 0, 4);

                    if ($year > 0 && $year <= date('Y')) {

                        $entry[ItemMeta::COLUMN['PUBLICATION_DATE']] = $year;
                    }

                    $parts = explode('/', $right);

                    $month = !empty($parts[1]) && is_numeric($parts[1]) ? str_pad($parts[1], 2, '0', STR_PAD_LEFT) : '01';
                    $entry[ItemMeta::COLUMN['PUBLICATION_DATE']] .= '-' . $month;

                    $day = !empty($parts[2]) && is_numeric($parts[2]) ? str_pad($parts[2], 2, '0', STR_PAD_LEFT) : '01';
                    $entry[ItemMeta::COLUMN['PUBLICATION_DATE']] .= '-' . $day;
                    break;

                case 'VL':
                    $entry[ItemMeta::COLUMN['VOLUME']] = $right;
                    break;

                case 'IS':
                    $entry[ItemMeta::COLUMN['ISSUE']] = $right;
                    break;

                case 'SP':
                    $entry[ItemMeta::COLUMN['PAGES']] = $right;
                    break;

                case 'EP':
                    $entry[ItemMeta::COLUMN['PAGES']] .= '-' . $right;
                    break;

                case 'PB':
                    $entry[ItemMeta::COLUMN['PUBLISHER']] = $right;
                    break;

                case 'CY':
                    $entry[ItemMeta::COLUMN['PLACE_PUBLISHED']] = $right;
                    break;

                case 'AB':
                case 'N2':
                    $entry[ItemMeta::COLUMN['ABSTRACT']] = $right;
                    break;

                case 'N1':
                case 'M3':
                    preg_match('/10\.\d{4}\/\S+/u', $right, $match);
                    if (!empty($match[0])) {

                        $entry[ItemMeta::COLUMN['UID_TYPES']][] = 'DOI';
                        $entry[ItemMeta::COLUMN['UIDS']][] = $match[0];
                    }
                    break;

                case 'DO':
                    $entry[ItemMeta::COLUMN['UID_TYPES']][] = 'DOI';
                    $entry[ItemMeta::COLUMN['UIDS']][] = $right;
                    break;

                case 'UR':
                    $entry[ItemMeta::COLUMN['URLS']][] = $right;
                    break;

                case 'KW':
                    $entry[ItemMeta::COLUMN['KEYWORDS']][] = $right;
                    break;

                case 'AD':
                    $entry[ItemMeta::COLUMN['AFFILIATION']] = $right;
                    break;

                case 'M2':
                    preg_match('/(pmid:\s?)(\d+)/ui', $right, $match);

                    if (!empty($match[2])) {

                        $entry[ItemMeta::COLUMN['UID_TYPES']][] = 'PMID';
                        $entry[ItemMeta::COLUMN['UIDS']][] = $match[2];
                    }
                    break;

                case 'AU':
                case 'A1':
                    $parts = explode(',', $right);

                    if (!empty(trim($parts[0])))  {

                        $entry[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = trim($parts[0]);
                    }

                    if (isset($parts[1]) && !empty(trim($parts[1])))  {

                        $entry[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = trim($parts[1]);
                    }
                    break;

                case 'ED':
                case 'A2':
                    $parts = explode(',', $right);

                    if (!empty(trim($parts[0])))  {

                        $entry[ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = trim($parts[0]);
                    }

                    if (isset($parts[1]) && !empty(trim($parts[1])))  {

                        $entry[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = trim($parts[1]);
                    }
                    break;

                case 'L1':
                    $entry['PDF'] = $right;
                    break;
            }
        }

        return $entry;
    }
}
