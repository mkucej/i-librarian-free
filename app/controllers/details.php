<?php

namespace LibrarianApp;

use Exception;
use Librarian\Media\Binary;

class DetailsController extends AppController {

    /**
     * @var Binary
     */
    private $binary;

    /**
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
        $this->authorization->permissions('A');

        $details = [];

        // Versions.

        $details['I, Librarian'] = [
            'present'  => IL_VERSION,
            'required' => ''
        ];

        $details['PHP'] = [
            'present'  => PHP_VERSION,
            'required' => '7.2.0'
        ];

        $model = new DetailsModel($this->di);
        $sqlite = $model->read();

        $details['SQLite'] = [
            'present'  => $sqlite['sqlite'],
            'required' => '3.22.0'
        ];

        // Extensions.

        $extensions = [
            'libxml',
            'openssl',
            'pcre',
            'zlib',
            'session',
            'pdo',
            'xml',
            'ctype',
            'curl',
            'dom',
            'mbstring',
            'fileinfo',
            'json',
            'ldap',
            'pdo_sqlite',
            'simplexml',
            'sysvsem',
            'sysvshm',
            'xmlreader',
            'xmlwriter',
            'zip',
            'intl',
            'fileinfo',
            'zlib'
        ];

        sort($extensions);

        $loaded_extensions = [];

        foreach ($extensions as $ext) {

            $loaded_extensions[$ext] = extension_loaded($ext);
        }

        $details['loaded_extensions'] = $loaded_extensions;

        // PHP ini.

        $details['ini'] = [
            'file_uploads' => [
                'present'  => ini_get('file_uploads'),
                'required' => '1'
            ],
            'upload_max_filesize' => [
                'present'  => ini_get('upload_max_filesize'),
                'required' => '200M'
            ],
            'post_max_size' => [
                'present'  => ini_get('post_max_size'),
                'required' => '800M'
            ],
            'max_input_vars' => [
                'present'  => ini_get('max_input_vars'),
                'required' => '10000'
            ],
            'open_basedir' => [
                'present'  => empty(ini_get('open_basedir')) ? 'empty' : ini_get('open_basedir'),
                'required' => 'empty'
            ],
            'allow_url_fopen' => [
                'present'  => ini_get('allow_url_fopen'),
                'required' => '1'
            ]
        ];

        // Binaries.
        $this->binary = $this->di->get('Binary');

        $binaries = [
            'pdftotext',
            'pdfinfo',
            'pdftohtml',
            'pdftoppm',
            'gs',
            'tesseract',
            'soffice'
        ];

        foreach ($binaries as $binary) {

            try {

                ${$binary} = $this->binary->isInstalled($binary) === true ? 'installed' : 'missing';

            } catch (Exception $exception) {

                ${$binary} = 'missing';
            }
        }

        /** @var string $pdftotext */
        /** @var string $pdfinfo */
        /** @var string $pdftohtml */
        /** @var string $pdftoppm */
        /** @var string $gs */
        /** @var string $tesseract */
        /** @var string $soffice */

        $details['binaries'] = [
            'Poppler pdftotext' => $pdftotext,
            'Poppler pdfinfo'   => $pdfinfo,
            'Poppler pdftohtml' => $pdftohtml,
            'Poppler pdftoppm'  => $pdftoppm,
            'Ghostscript'       => $gs,
            'Tesseract OCR (optional)' => $tesseract,
            'LibreOffice (optional)'   => $soffice
        ];

        $view = new DetailsView($this->di);
        return $view->main($details);
    }
}
