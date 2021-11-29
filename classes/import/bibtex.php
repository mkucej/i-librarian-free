<?php

namespace Librarian\Import;

use Exception;
use Librarian\ItemMeta;

class Bibtex {

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
        preg_match_all("/(^|\n)(@[a-z]+{)/ui", $input, $matches, PREG_OFFSET_CAPTURE);
        $this->offsets = empty($matches[2]) ? [] : array_column($matches[2], 1);
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

        // Bibtex type.
        $bibtex_type = strtolower(substr(strstr($str, '{', true), 1));

        if (in_array($bibtex_type, ItemMeta::BIBTEX_TYPE) === true) {

            $entry[ItemMeta::COLUMN['BIBTEX_TYPE']] = $bibtex_type;

        } else {

            $entry[ItemMeta::COLUMN['BIBTEX_TYPE']] = 'misc';
        }

        // IL type.
        $entry[ItemMeta::COLUMN['REFERENCE_TYPE']] = $this->item_meta->convert($entry[ItemMeta::COLUMN['BIBTEX_TYPE']], 'bibtex', 'il');

        // Get content in curly brackets.
        $start = strpos($str, '{') + 1;
        $length = strrpos($str, '}') - $start;
        $inner = trim(substr($str, $start, $length));

        // Bibtex key.
        $bibtex_key = substr($inner, 0, strpos($inner, ','));
        $inner = trim(substr($inner, strpos($inner, ',') + 1));
        $entry[ItemMeta::COLUMN['BIBTEX_ID']] = strrpos($bibtex_key, ',') === false ? $bibtex_key : substr($bibtex_key, 0, -1);

        // Remove line breaks from pretty-printed input.
        // Some entries can have a space after the comma and before the line break.
        $inner = str_replace(", \n", ",\n", $inner);
        $inner = preg_replace('/(?<![}"\d],)\n/u', ' ', $inner);
        $inner = preg_replace('/ {2,}/u', ' ', $inner);

        // Separate into tags.
        $parts = explode("\n", $inner);

        // All inner entry tags.
        foreach ($parts as $part) {

            $final_parts = explode("=", $part);

            if (empty($final_parts[0]) || empty($final_parts[1])) {

                continue;
            }

            $left = strtolower(trim($final_parts[0]));
            $right = trim($final_parts[1]);

            // Get first char in tag content.
            $char = $right[0];

            // Get tag content in optional brackets.
            switch ($char) {

                case '{':
                    $bracket_start = strpos($right, '{');
                    $content_start = $bracket_start === false ? 0 : $bracket_start + 1;
                    $content_end = strrpos($right, '}');
                    $content_end = $content_end === false ? strlen($right) : $content_end;
                    break;

                case '"':
                    $bracket_start = strpos($right, '"');
                    $content_start = $bracket_start === false ? 0 : $bracket_start + 1;
                    $content_end = strrpos($right, '"');
                    $content_end = $content_end === false ? strlen($right) : $content_end;
                    break;

                default:
                    $content_start = 0;
                    $content_end = strrpos($right, ',') === false ? strlen($right) : strlen($right) - 1;
            }

            $right = substr($right, $content_start, $content_end - $content_start);
            $right = trim(str_replace(['{', '}'], '', $right));

            switch ($left) {

                case 'title':
                    $entry[ItemMeta::COLUMN['TITLE']] = $right;
                    break;

                case 'journal':
                case 'booktitle':
                case 'school':
                case 'institution':
                    $entry[ItemMeta::COLUMN['SECONDARY_TITLE']] = $right;
                    break;

                case 'series':
                    $entry[ItemMeta::COLUMN['TERTIARY_TITLE']] = $right;
                    break;

                case 'holder':
                case 'source':
                    $entry[ItemMeta::COLUMN['AFFILIATION']] = $right;
                    break;

                case 'year':
                    $entry[ItemMeta::COLUMN['PUBLICATION_DATE']] = substr($right, 0, 4) . '-01-01';
                    break;

                case 'month':
                    if (!empty($entry[ItemMeta::COLUMN['PUBLICATION_DATE']]) && is_numeric($right)) {

                        $month = str_pad($right, 2, '0', STR_PAD_LEFT);
                        $entry[ItemMeta::COLUMN['PUBLICATION_DATE']] = str_replace('-01-', "-{$month}-", $entry[ItemMeta::COLUMN['PUBLICATION_DATE']]);
                    }
                    break;

                case 'volume':
                    $entry[ItemMeta::COLUMN['VOLUME']] = $right;
                    break;

                case 'number':
                    $entry[ItemMeta::COLUMN['ISSUE']] = $right;
                    break;

                case 'pages':
                    $entry[ItemMeta::COLUMN['PAGES']] = $right;
                    break;

                case 'publisher':
                    $entry[ItemMeta::COLUMN['PUBLISHER']] = $right;
                    break;

                case 'address':
                    $entry[ItemMeta::COLUMN['PLACE_PUBLISHED']] = $right;
                    break;

                case 'abstract':
                    $entry[ItemMeta::COLUMN['ABSTRACT']] = $right;
                    break;

                case 'doi':
                    $entry[ItemMeta::COLUMN['UID_TYPES']][] = 'DOI';
                    $entry[ItemMeta::COLUMN['UIDS']][] = $right;
                    break;

                case 'pmid':
                    $entry[ItemMeta::COLUMN['UID_TYPES']][] = 'PMID';
                    $entry[ItemMeta::COLUMN['UIDS']][] = $right;
                    break;

                case 'url':
                    $entry[ItemMeta::COLUMN['URLS']][] = $right;
                    break;

                case 'author':
                    $authors = explode(' and ', $right);

                    foreach ($authors as $author) {

                        if (strpos($author, ',') === false) {

                            $last_space = strrpos(trim($author), " ");

                            if ($last_space === false) {

                                $entry[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = $author;
                                $entry[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = '';

                            } else {

                                $entry[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = substr(trim($author), $last_space + 1);
                                $entry[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = substr(trim($author), 0, $last_space);
                            }

                        } else {

                            $author_arr = explode(",", $author);
                            $entry[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = trim($author_arr[0]);
                            $entry[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = isset($author_arr[1]) ? trim($author_arr[1]) : '';
                        }
                    }
                    break;

                case 'editor':
                    $editors = explode(' and ', $right);

                    foreach ($editors as $editor) {

                        if (strpos($editor, ',') === false) {

                            $last_space = strrpos(trim($editor), " ");

                            if ($last_space === false) {

                                $entry[ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = $editor;
                                $entry[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = '';

                            } else {

                                $entry[ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = substr(trim($editor), $last_space + 1);
                                $entry[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = substr(trim($editor), 0, $last_space);
                            }

                        } else {

                            $editor_arr = explode(",", $editor);
                            $entry[ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = trim($editor_arr[0]);
                            $entry[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = isset($editor_arr[1]) ? trim($editor_arr[1]) : '';
                        }
                    }
                    break;

                case 'file':
                    if (strpos($right, 'FULLTEXT:') === 0 && strpos(strrev($right), 'FDP:') === 0) {

                        $entry['PDF'] = mb_substr($right, 9, mb_strlen($right) - 13);
                    }
                    break;
            }
        }

        return $entry;
    }
}
