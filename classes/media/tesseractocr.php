<?php

namespace Librarian\Media;

use Exception;
use Librarian\Container\DependencyInjector;

final class TesseractOcr {

    /**
     * @var Binary
     */
    private $binary;

    /**
     * @var array Supported languages.
     */
    public static $languages = [
        'afr' => 'Afrikaans',
        'amh' => 'Amharic',
        'ara' => 'Arabic',
        'asm' => 'Assamese',
        'aze' => 'Azerbaijani',
        'aze_cyrl' => 'Azerbaijani - Cyrilic',
        'bel' => 'Belarusian',
        'ben' => 'Bengali',
        'bod' => 'Tibetan',
        'bos' => 'Bosnian',
        'bre' => 'Breton',
        'bul' => 'Bulgarian',
        'cat' => 'Catalan; Valencian',
        'ceb' => 'Cebuano',
        'ces' => 'Czech',
        'chi_sim' => 'Chinese simplified',
        'chi_tra' => 'Chinese traditional',
        'chr' => 'Cherokee',
        'cym' => 'Welsh',
        'dan' => 'Danish',
        'deu' => 'German',
        'dzo' => 'Dzongkha',
        'ell' => 'Greek, Modern, 1453-',
        'eng' => 'English',
        'enm' => 'English, Middle, 1100-1500',
        'epo' => 'Esperanto',
        'equ' => 'Math / equation detection module',
        'est' => 'Estonian',
        'eus' => 'Basque',
        'fas' => 'Persian',
        'fin' => 'Finnish',
        'fra' => 'French',
        'frk' => 'Frankish',
        'frm' => 'French, Middle, ca.1400-1600',
        'gle' => 'Irish',
        'glg' => 'Galician',
        'grc' => 'Greek, Ancient, to 1453',
        'guj' => 'Gujarati',
        'hat' => 'Haitian; Haitian Creole',
        'heb' => 'Hebrew',
        'hin' => 'Hindi',
        'hrv' => 'Croatian',
        'hun' => 'Hungarian',
        'iku' => 'Inuktitut',
        'ind' => 'Indonesian',
        'isl' => 'Icelandic',
        'ita' => 'Italian',
        'ita_old' => 'Italian - Old',
        'jav' => 'Javanese',
        'jpn' => 'Japanese',
        'kan' => 'Kannada',
        'kat' => 'Georgian',
        'kat_old' => 'Georgian - Old',
        'kaz' => 'Kazakh',
        'khm' => 'Central Khmer',
        'kir' => 'Kirghiz; Kyrgyz',
        'kmr' => 'Kurdish Kurmanji',
        'kor' => 'Korean',
        'kor_vert' => 'Korean vertical',
        'kur' => 'Kurdish',
        'lao' => 'Lao',
        'lat' => 'Latin',
        'lav' => 'Latvian',
        'lit' => 'Lithuanian',
        'ltz' => 'Luxembourgish',
        'mal' => 'Malayalam',
        'mar' => 'Marathi',
        'mkd' => 'Macedonian',
        'mlt' => 'Maltese',
        'mon' => 'Mongolian',
        'mri' => 'Maori',
        'msa' => 'Malay',
        'mya' => 'Burmese',
        'nep' => 'Nepali',
        'nld' => 'Dutch; Flemish',
        'nor' => 'Norwegian',
        'oci' => 'Occitan post 1500',
        'ori' => 'Oriya',
        'osd' => 'Orientation and script detection module',
        'pan' => 'Panjabi; Punjabi',
        'pol' => 'Polish',
        'por' => 'Portuguese',
        'pus' => 'Pushto; Pashto',
        'que' => 'Quechua',
        'ron' => 'Romanian; Moldavian; Moldovan',
        'rus' => 'Russian',
        'san' => 'Sanskrit',
        'sin' => 'Sinhala; Sinhalese',
        'slk' => 'Slovak',
        'slv' => 'Slovenian',
        'snd' => 'Sindhi',
        'spa' => 'Spanish; Castilian',
        'spa_old' => 'Spanish; Castilian - Old',
        'sqi' => 'Albanian',
        'srp' => 'Serbian',
        'srp_latn' => 'Serbian - Latin',
        'sun' => 'Sundanese',
        'swa' => 'Swahili',
        'swe' => 'Swedish',
        'syr' => 'Syriac',
        'tam' => 'Tamil',
        'tat' => 'Tatar',
        'tel' => 'Telugu',
        'tgk' => 'Tajik',
        'tgl' => 'Tagalog',
        'tha' => 'Thai',
        'tir' => 'Tigrinya',
        'ton' => 'Tonga',
        'tur' => 'Turkish',
        'uig' => 'Uighur; Uyghur',
        'ukr' => 'Ukrainian',
        'urd' => 'Urdu',
        'uzb' => 'Uzbek',
        'uzb_cyrl' => 'Uzbek - Cyrilic',
        'vie' => 'Vietnamese',
        'yid' => 'Yiddish',
        'yor' => 'Yoruba',
        // Scripts.
        'Arabic' => 'Arabic script',
        'Armenian' => 'Armenian script',
        'Bengali' => 'Bengali script',
        'Canadian_Aboriginal' => 'Canadian Aboriginal script',
        'Cherokee' => 'Cherokee script',
        'Cyrillic' => 'Cyrillic script',
        'Devanagari' => 'Devanagari script',
        'Ethiopic' => 'Ethiopic script',
        'Fraktur' => 'Fraktur script',
        'Georgian' => 'Georgian script',
        'Greek' => 'Greek script',
        'Gujarati' => 'Gujarati script',
        'Gurmukhi' => 'Gurmukhi script',
        'HanS' => 'Han simplified script',
        'HanS_vert' => 'Han simplified script vertical',
        'HanT' => 'Han traditional script',
        'HanT_vert' => 'Han traditional script vertical',
        'Hangul' => 'Hangul script',
        'Hangul_vert' => 'Hangul script vertical',
        'Hebrew' => 'Hebrew script',
        'Japanese' => 'Japanese script',
        'Japanese_vert' => 'Japanese script vertical',
        'Kannada' => 'Kannada script',
        'Khmer' => 'Khmer script',
        'Lao' => 'Lao script',
        'Latin' => 'Latin script',
        'Malayalam' => 'Malayalam script',
        'Myanmar' => 'Myanmar script',
        'Oriya' => 'Oriya script',
        'Sinhala' => 'Sinhala script',
        'Syriac' => 'Syriac script',
        'Tamil' => 'Tamil script',
        'Telugu' => 'Telugu script',
        'Thaana' => 'Thaana script',
        'Thai' => 'Thai script',
        'Tibetan' => 'Tibetan script',
        'Vietnamese' => 'Vietnamese script'
    ];

    /**
     * TesseractOcr constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        $this->binary = $di->getShared('Binary');
    }

    /**
     * Extract text and binding boxes from image.
     *
     * @param string $image Image path.
     * @param string $language
     * @return array
     * @throws Exception
     */
    public function ocr(string $image, string $language): array {

        set_time_limit(600);

        $output = [
            'boxes' => [],
            'text'  => ''
        ];

        exec($this->binary->tesseract() . ' ' . escapeshellarg($image) . ' -l ' . escapeshellarg($language) . ' stdout tsv', $lines);

        // Get image dimensions.
        array_shift($lines);
        $first_line = array_shift($lines);
        $first_line_array = explode("\t", $first_line);
        $image_width = $first_line_array[8];
        $image_height = $first_line_array[9];

        // Scan TSV line by line.
        foreach ($lines as $line) {

            $line_array = explode("\t", $line);
            $word = trim($line_array[11] ?? '');

            if ($word === '') {

                continue;
            }

            $output['boxes'][] = [
                'text' => $word,
                't'    => 1000 * round($line_array[7] / $image_height, 3),
                'l'    => 1000 * round($line_array[6] / $image_width, 3),
                'w'    => 1000 * round($line_array[8] / $image_width, 3),
                'h'    => 1000 * round($line_array[9] / $image_height, 3)
            ];

            $output['text'] .= ' ' . $word;
        }

        $output['text'] = trim($output['text']);

        return $output;
    }

    /**
     * Get a list of installed languages.
     *
     * @return array
     * @throws Exception
     */
    public function getInstalledLanguages(): array {

        exec($this->binary->tesseract() . " --list-langs", $languages);
        array_shift($languages);

        $languages = array_intersect_key(self::$languages, array_flip($languages));
        asort($languages);

        return $languages;
    }
}
