<?php

namespace Librarian;

use Exception;
use Librarian\Media\Language;

final class ItemMeta {

    const COLUMN = [
        'ABSTRACT'          => 'abstract',
        'AFFILIATION'       => 'affiliation',
        'AUTHOR_FIRST_NAME' => 'author_first_name',
        'AUTHOR_LAST_NAME'  => 'author_last_name',
        'BIBTEX_ID'         => 'bibtex_id',
        'BIBTEX_TYPE'       => 'bibtex_type',
        'CUSTOM1'           => 'custom1',
        'CUSTOM2'           => 'custom2',
        'CUSTOM3'           => 'custom3',
        'CUSTOM4'           => 'custom4',
        'CUSTOM5'           => 'custom5',
        'CUSTOM6'           => 'custom6',
        'CUSTOM7'           => 'custom7',
        'CUSTOM8'           => 'custom8',
        'EDITOR_FIRST_NAME' => 'editor_first_name',
        'EDITOR_LAST_NAME'  => 'editor_last_name',
        'ISSUE'             => 'issue',
        'KEYWORDS'          => 'keywords',
        'PAGES'             => 'pages',
        'PLACE_PUBLISHED'   => 'place_published',
        'PRIMARY_TITLE'     => 'primary_title',
        'PRIVATE'           => 'private',
        'PUBLICATION_DATE'  => 'publication_date',
        'PUBLISHER'         => 'publisher',
        'REFERENCE_TYPE'    => 'reference_type',
        'SECONDARY_TITLE'   => 'secondary_title',
        'TERTIARY_TITLE'    => 'tertiary_title',
        'TITLE'             => 'title',
        'UIDS'              => 'uids',
        'UID_TYPES'         => 'uid_types',
        'URLS'              => 'urls',
        'VOLUME'            => 'volume'
    ];

    /**
     * Item types.
     */
    const TYPE = [
        'ARTICLE'     => 'article',
        'BOOK'        => 'book',
        'CHAPTER'     => 'chapter',
        'CONFERENCE'  => 'conference',
        'DATASET'     => 'dataset',
        'ELECTRONIC'  => 'electronic',
        'GENERIC'     => 'generic',
        'MANUAL'      => 'manual',
        'THESIS'      => 'thesis',
        'PATENT'      => 'patent',
        'REPORT'      => 'report',
        'STANDARD'    => 'standard',
        'UNPUBLISHED' => 'unpublished'
    ];

    /**
     * Bibtex item types.
     */
    const BIBTEX_TYPE = [
        'ARTICLE'       => 'article',
        'BOOK'          => 'book',
        'CONFERENCE'    => 'conference',
        'DATASET'       => 'dataset',
        'ELECTRONIC'    => 'electronic',
        'INCOLLECTION'  => 'incollection',
        'INPROCEEDINGS' => 'inproceedings',
        'MANUAL'        => 'manual',
        'MASTERTHESIS'  => 'mastersthesis',
        'MISC'          => 'misc',
        'ONLINE'        => 'online',
        'PHDTHESIS'     => 'phdthesis',
        'PATENT'        => 'patent',
        'TECHREPORT'    => 'techreport',
        'STANDARD'      => 'standard',
        'UNPUBLISHED'   => 'unpublished'
    ];

    /**
     * Supported external UID types.
     */
    const UID_TYPE = [
        'ARXIV'   => 'arXiv',
        'DOI'     => 'DOI',
        'ISBN'    => 'ISBN',
        'IEEE'    => 'IEEE',
        'NASAADS' => 'NASA ADS',
        'OL'      => 'Open Library',
        'PAT'     => 'Patent',
        'PMID'    => 'Pubmed',
        'PMCID'   => 'Pubmed Central',
        'DIRECT'  => 'ScienceDirect',
        'SCOPUS'  => 'Scopus',
        'OTHER'   => 'Other'
    ];

    /**
     * @var array Input labels for different item types.
     */
    private array $labels = [
        'article'     => [
            'secondary_title'  => 'Journal full name'
        ],
        'book'        => [
            'secondary_title'  => 'Series title'
        ],
        'chapter'     => [
            'secondary_title'  => 'Book title',
            'tertiary_title'   => 'Series title'
        ],
        'conference'  => [
            'secondary_title'  => 'Conference'
        ],
        'dataset'     => [],
        'electronic'  => [],
        'generic'     => [
            'abstract'         => 'Abstract',
            'affiliation'      => 'Affiliation',
            'authors'          => 'Authors',
            'bibtex_id'        => 'Bibtex ID',
            'bibtex_type'      => 'Bibtex type',
            'custom1'          => 'Custom 1',
            'custom2'          => 'Custom 2',
            'custom3'          => 'Custom 3',
            'custom4'          => 'Custom 4',
            'custom5'          => 'Custom 5',
            'custom6'          => 'Custom 6',
            'custom7'          => 'Custom 7',
            'custom8'          => 'Custom 8',
            'doi'              => 'DOI',
            'editors'          => 'Editors',
            'issue'            => 'Issue',
            'keywords'         => 'Keywords',
            'pages'            => 'Pages',
            'place_published'  => 'Place published',
            'primary_title'    => 'Journal abbreviation',
            'publication_date' => 'Published date as YYYY-MM-DD',
            'publisher'        => 'Publisher',
            'reference_type'   => 'Reference type',
            'secondary_title'  => 'Secondary title',
            'tertiary_title'   => 'Tertiary title',
            'title'            => 'Title',
            'uids'             => 'UID',
            'urls'             => 'URLs',
            'volume'           => 'Volume'
        ],
        'manual'      => [],
        'patent'      => [
            'affiliation'      => 'Assignee or applicant',
            'authors'          => 'Inventors'
        ],
        'report'      => [],
        'standard'    => [],
        'thesis'      => [
            'secondary_title'  => 'School'
        ],
        'unpublished' => []
    ];

    /**
     * @var array Item type translation map.
     */
    public array $type_map = [
        [
            'il'      => 'article',
            'bibtex'  => 'article',
            'ris'     => 'JOUR',
            'endnote' => 'Journal Article',
            'csl'     => 'article-journal'
        ],
        [
            'il'      => 'book',
            'bibtex'  => 'book',
            'ris'     => 'BOOK',
            'endnote' => 'Book',
            'csl'     => 'book'
        ],
        [
            'il'      => 'chapter',
            'bibtex'  => 'incollection',
            'ris'     => 'CHAP',
            'endnote' => 'Book Section',
            'csl'     => 'chapter'
        ],
        [
            'il'      => 'conference',
            'bibtex'  => ['inproceedings', 'conference'],
            'ris'     => 'CONF',
            'endnote' => 'Conference Paper',
            'csl'     => 'paper-conference'
        ],
        [
            'il'      => 'dataset',
            'bibtex'  => 'dataset',
            'ris'     => 'DATA',
            'endnote' => 'Online Database',
            'csl'     => 'dataset'
        ],
        [
            'il'      => 'electronic',
            'bibtex'  => ['online', 'electronic'],
            'ris'     => 'ELEC',
            'endnote' => 'Electronic Article',
            'csl'     => 'webpage'
        ],
        [
            'il'      => 'generic',
            'bibtex'  => 'misc',
            'ris'     => 'GEN',
            'endnote' => 'Generic',
            'csl'     => 'entry'
        ],
        [
            'il'      => 'manual',
            'bibtex'  => 'manual',
            'ris'     => 'STAND',
            'endnote' => 'Standard',
            'csl'     => 'article-journal'
        ],
        [
            'il'      => 'patent',
            'bibtex'  => 'patent',
            'ris'     => 'PAT',
            'endnote' => 'Patent',
            'csl'     => 'patent'
        ],
        [
            'il'      => 'report',
            'bibtex'  => 'techreport',
            'ris'     => 'RPRT',
            'endnote' => 'Report',
            'csl'     => 'report'
        ],
        [
            'il'      => 'standard',
            'bibtex'  => 'standard',
            'ris'     => 'STAND',
            'endnote' => 'Standard',
            'csl'     => 'article'
        ],
        [
            'il'      => 'thesis',
            'bibtex'  => ['phdthesis', 'mastersthesis'],
            'ris'     => 'THES',
            'endnote' => 'Thesis',
            'csl'     => 'thesis'
        ],
        [
            'il'      => 'unpublished',
            'bibtex'  => 'unpublished',
            'ris'     => 'UNPB',
            'endnote' => 'Unpublished Work',
            'csl'     => 'manuscript'
        ]
    ];

    /**
     * @var AppSettings
     */
    private AppSettings $app_settings;

    /**
     * Constructor.
     *
     * @param AppSettings $app_settings
     */
    public function __construct(AppSettings $app_settings) {

        $this->app_settings = $app_settings;
    }

    /**
     * Convert reference type using the type_map array.
     *
     * @param  string $type Type string to convert.
     * @param  string $from Format from.
     * @param  string $to Format to.
     * @return string
     * @throws Exception
     */
    public function convert(string $type, string $from, string $to): string {

        $to_out = '';

        foreach ($this->type_map as $map) {

            if (isset($map[$from]) === false) {

                throw new Exception('converting from an unknown publication format');
            }

            if ((is_string($map[$from]) && $map[$from] === $type) ||
                (is_array($map[$from]) && in_array($type, $map[$from]) === true)) {

                $to_out = is_array($map[$to]) ? $map[$to][0] : $map[$to];
            }
        }

        if ($to_out === '') {

            return $this->convert($this->type_map[0][$from], $from, $to);
        }

        return $to_out;
    }

    /**
     * Get input labels for an item type.
     *
     * @param Language $lang
     * @param string $type Item type.
     * @return array
     * @throws Exception
     */
    public function getLabels(Language $lang, string $type): array {

        if (isset($this->labels[$type]) === false) {

            throw new Exception('unknown item type');
        }

        // Add customN labels to generic.
        for ($i = 1; $i <= 8; $i++) {

            $this->labels['generic']['custom' . $i] = $this->app_settings->getGlobal('custom' . $i);
        }

        // Merge labels of the type with generic labels.
        $output = array_merge($this->labels['generic'], $this->labels[$type]);

        // Translate.
        foreach ($output as $key => $value) {

            $output[$key] = $lang->t9n($value);
        }

        return $output;
    }
}
