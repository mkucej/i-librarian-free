<?php

namespace Librarian\Import;

use Exception;
use Librarian\ItemMeta;
use Librarian\Media\Xml;
use SimpleXMLIterator;

class Endnote {

    /**
     * @var string Imported file contents.
     */
    private $input;

    /**
     * @var ItemMeta
     */
    private $item_meta;

    /**
     * @var SimpleXMLIterator
     */
    private $iterator;

    /**
     * Endnote constructor.
     *
     * @param Xml $xml
     * @param ItemMeta $item_meta
     * @param string $input
     * @throws Exception
     */
    public function __construct(Xml $xml, ItemMeta $item_meta, string $input) {

        $this->input = $input;
        $this->item_meta = $item_meta;

        // Extract xml tag content and feed it to iterator.
        $xml_obj = $xml->loadXmlString($input);
        $this->iterator = $xml->iterateXml($xml_obj->records->asXML());
    }

    /**
     * Entry iterator.
     *
     * @return array|null
     * @throws Exception
     */
    public function getEntry() {

        $record = $this->iterator->current();

        // Go to next record.
        $this->iterator->next();

        // There are no entries to return.
        if ($record === null) {

            return null;
        }

        // Reference type.
        foreach ($record->{'ref-type'}->attributes() as $name => $value) {

            if ($name === 'name') {

                $entry[ItemMeta::COLUMN['REFERENCE_TYPE']] = $this->item_meta->convert($value, 'endnote', 'il');
                break;
            }
        }

        // Authors.
        $authors = $record->contributors->authors->author ?? [];

        foreach ($authors as $author) {

            $value = strip_tags($author->asXML());

            $parts = explode(",", $value);
            $entry[ItemMeta::COLUMN['AUTHOR_LAST_NAME']][] = trim($parts[0]);
            $entry[ItemMeta::COLUMN['AUTHOR_FIRST_NAME']][] = isset($parts[1]) ? trim($parts[1]) : '';
        }

        // Editors.
        $editors = $record->contributors->{'secondary-authors'}->author ?? [];

        foreach ($editors as $editor) {

            $value = strip_tags($editor->asXML());

            $parts = explode(",", $value);
            $entry[ItemMeta::COLUMN['EDITOR_LAST_NAME']][] = trim($parts[0]);
            $entry[ItemMeta::COLUMN['EDITOR_FIRST_NAME']][] = isset($parts[1]) ? trim($parts[1]) : '';
        }

        // Title.
        $title = $record->titles->title ?? null;

        if ($title === null) {

            return null;
        }

        $entry[ItemMeta::COLUMN['TITLE']] = strip_tags($title->asXML());

        // Abstract.
        $abstract = $record->abstract ?? null;

        if ($abstract !== null) {

            $entry[ItemMeta::COLUMN['ABSTRACT']] = strip_tags($abstract->asXML());
        }

        // Affiliation.
        $affiliation = $record->{'auth-address'} ?? null;

        if ($affiliation !== null) {

            $entry[ItemMeta::COLUMN['AFFILIATION']] = strip_tags($affiliation->asXML());
        }

        // Publication titles.
        if ($entry[ItemMeta::COLUMN['REFERENCE_TYPE']] === 'article') {

            $primary = $record->periodical->{'abbr-1'} ?? null;
            $secondary = $record->periodical->{'full-title'} ?? null;

        } else {

            $primary = null;
            $secondary = $record->periodical->{'full-title'} ?? null;
        }

        $tertiary = $record->titles->{'tertiary-title'} ?? null;

        if ($primary !== null) {

            $entry[ItemMeta::COLUMN['PRIMARY_TITLE']] = strip_tags($primary->asXML());
        }

        if ($secondary !== null) {

            $entry[ItemMeta::COLUMN['SECONDARY_TITLE']] = strip_tags($secondary->asXML());
        }

        if ($tertiary !== null) {

            $entry[ItemMeta::COLUMN['TERTIARY_TITLE']] = strip_tags($tertiary->asXML());
        }

        // Volume.
        $volume = $record->volume ?? null;

        if ($volume !== null) {

            $entry[ItemMeta::COLUMN['VOLUME']] = strip_tags($volume->asXML());
        }

        // Issue.
        $issue = $record->number ?? null;

        if ($issue !== null) {

            $entry[ItemMeta::COLUMN['ISSUE']] = strip_tags($issue->asXML());
        }

        // Pages.
        $pages = $record->pages ?? null;

        if ($pages !== null) {

            $entry[ItemMeta::COLUMN['PAGES']] = strip_tags($pages->asXML());
        }

        // Publisher.
        $publisher = $record->publisher ?? null;

        if ($publisher !== null) {

            $entry[ItemMeta::COLUMN['PUBLISHER']] = strip_tags($publisher->asXML());
        }

        // Place published.
        $place_published = $record->{'pub-location'} ?? null;

        if ($place_published !== null) {

            $entry[ItemMeta::COLUMN['PLACE_PUBLISHED']] = strip_tags($place_published->asXML());
        }

        // Keywords.
        $keywords = $record->keywords->keyword ?? [];

        foreach ($keywords as $keyword) {

            $entry[ItemMeta::COLUMN['KEYWORDS']][] = strip_tags($keyword->asXML());
        }

        // DOI.
        $accession_number = $record->{'accession-num'} ?? null;

        if ($accession_number !== null) {

            $stripped = strip_tags($accession_number->asXML());
            preg_match('/10\.\d{4}\/\S+/ui', $stripped, $match);

            if (!empty($match[0])) {

                $entry[ItemMeta::COLUMN['UID_TYPES']][] = 'DOI';
                $entry[ItemMeta::COLUMN['UIDS']][] = $match[0];
            }
        }

        // Year.
        $year = $record->dates->year ?? null;

        if ($year !== null) {

            $year = strip_tags($year->asXML());

            if (is_numeric($year) && $year > 0 && $year < date('Y')) {

                $entry[ItemMeta::COLUMN['PUBLICATION_DATE']] = $year . '-01-01';
            }
        }

        // Custom.
        for ($i = 1;  $i <= 7; $i++) {

            $custom = $record->{"custom{$i}"} ?? null;

            if ($custom !== null) {

                $entry[ItemMeta::COLUMN['CUSTOM' . $i]] = strip_tags($custom);
            }
        }

        // Urls.
        $urls = $record->urls->{'related-urls'}->url ?? [];

        foreach ($urls as $url) {

            $entry[ItemMeta::COLUMN['URLS']][] = strip_tags($url->asXML());
        }

        // Pdf.
        $pdf_path = $record->urls->{'pdf-urls'}->url ?? null;

        if ($pdf_path !== null) {

            $pdf_path = strip_tags($pdf_path->asXML());

            if (strpos($pdf_path, "internal-pdf") === 0) {

                $entry['pdf_path'] = realpath(IL_DATA_PATH . DIRECTORY_SEPARATOR .
                    'import' . DIRECTORY_SEPARATOR .
                    'PDF' . DIRECTORY_SEPARATOR . substr($pdf_path, 15));

            } elseif (strpos($pdf_path, "file:") === 0) {

                $entry['pdf_path'] = realpath(IL_DATA_PATH . DIRECTORY_SEPARATOR .
                    'import' . DIRECTORY_SEPARATOR .
                    'PDF' . DIRECTORY_SEPARATOR . substr($pdf_path, strpos($pdf_path, 'PDF')));
            }

            // Check for path attack.
            if (strpos($entry['pdf_path'], IL_DATA_PATH . DIRECTORY_SEPARATOR . 'import') !== 0) {

                unset($entry['pdf_path']);
            }
        }

        return $entry;
    }
}
