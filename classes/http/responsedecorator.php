<?php

namespace Librarian\Http;

use Librarian\Http\Client\Psr7\Response;

final class ResponseDecorator {

    private $response;

    public static $mimetypes = [
        '7z' => 'application/x-7z-compressed',
        'aac' => 'audio/x-aac',
        'ai' => 'application/postscript',
        'aif' => 'audio/x-aiff',
        'asc' => 'text/plain',
        'asf' => 'video/x-ms-asf',
        'atom' => 'application/atom+xml',
        'avi' => 'video/x-msvideo',
        'bin' => 'application/octet-stream',
        'bmp' => 'image/bmp',
        'bz2' => 'application/x-bzip2',
        'cer' => 'application/pkix-cert',
        'crl' => 'application/pkix-crl',
        'crt' => 'application/x-x509-ca-cert',
        'css' => 'text/css',
        'csv' => 'text/csv',
        'cu' => 'application/cu-seeme',
        'deb' => 'application/x-debian-package',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'dvi' => 'application/x-dvi',
        'eot' => 'application/vnd.ms-fontobject',
        'eps' => 'application/postscript',
        'epub' => 'application/epub+zip',
        'etx' => 'text/x-setext',
        'flac' => 'audio/flac',
        'flv' => 'video/x-flv',
        'gif' => 'image/gif',
        'gz' => 'application/gzip',
        'htm' => 'text/html',
        'html' => 'text/html',
        'ico' => 'image/x-icon',
        'ics' => 'text/calendar',
        'ini' => 'text/plain',
        'iso' => 'application/x-iso9660-image',
        'jar' => 'application/java-archive',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'latex' => 'application/x-latex',
        'log' => 'text/plain',
        'm4a' => 'audio/mp4',
        'm4v' => 'video/mp4',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',
        'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'mp4a' => 'audio/mp4',
        'mp4v' => 'video/mp4',
        'mpe' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpg4' => 'video/mp4',
        'oga' => 'audio/ogg',
        'ogg' => 'audio/ogg',
        'ogv' => 'video/ogg',
        'ogx' => 'application/ogg',
        'pbm' => 'image/x-portable-bitmap',
        'pdf' => 'application/pdf',
        'pgm' => 'image/x-portable-graymap',
        'png' => 'image/png',
        'pnm' => 'image/x-portable-anymap',
        'ppm' => 'image/x-portable-pixmap',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ps' => 'application/postscript',
        'qt' => 'video/quicktime',
        'rar' => 'application/x-rar-compressed',
        'ras' => 'image/x-cmu-raster',
        'rss' => 'application/rss+xml',
        'rtf' => 'application/rtf',
        'sgm' => 'text/sgml',
        'sgml' => 'text/sgml',
        'svg' => 'image/svg+xml',
        'swf' => 'application/x-shockwave-flash',
        'tar' => 'application/x-tar',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'torrent' => 'application/x-bittorrent',
        'ttf' => 'application/x-font-ttf',
        'txt' => 'text/plain',
        'wav' => 'audio/x-wav',
        'webm' => 'video/webm',
        'wma' => 'audio/x-ms-wma',
        'wmv' => 'video/x-ms-wmv',
        'woff' => 'application/x-font-woff',
        'wsdl' => 'application/wsdl+xml',
        'xbm' => 'image/x-xbitmap',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xml' => 'application/xml',
        'xpm' => 'image/x-xpixmap',
        'xwd' => 'image/x-xwindowdump',
        'yaml' => 'text/yaml',
        'yml' => 'text/yaml',
        'zip' => 'application/zip',
    ];

    public function __construct(Response $response) {

        $this->response = $response;
    }

    /**
     * Get well-formed file name from the response header.
     *
     * @return string
     */
    public function getFilename(): string {

        setlocale(LC_ALL,'en_US.UTF-8');

        $header = $this->response->getHeader('Content-Disposition');

        $disposition = $header[0] ?? '';
        $dispositions = explode(';', $disposition);

        $filename = empty($dispositions[1]) ? '' : $dispositions[1];

        if ($filename === '') {

            $uri = $this->response->getBody()->getMetadata('uri');
            $filename = pathinfo(parse_url($uri, PHP_URL_PATH), PATHINFO_BASENAME);

        } elseif (strpos($filename, "filename*=UTF-8''") !== false) {

            $filename = rawurldecode(substr(trim($filename), 17));

        } elseif (strpos($filename, 'filename=') !== false) {

            $filename = substr(trim($filename), 9);
        }

        if (empty(pathinfo($filename, PATHINFO_FILENAME))) {

            $filename = 'file';
        }

        if (empty(pathinfo($filename, PATHINFO_EXTENSION))) {

            $mime = $this->getMimeType();
            $filename = $filename . '.' . $this->extensionFromMimeType($mime);
        }

        return $filename;
    }

    /**
     * Get MIME type from the response header.
     *
     * @return string
     */
    public function getMimeType(): string {

        $header = $this->response->getHeader('Content-Type');
        $type = $header[0] ?? '';
        $types = explode(';', $type);

        return empty($types[0]) ? 'application/octet-stream' : $types[0];
    }

    public function extensionFromMimeType(string $mime_type): string {

        $extensions = array_keys(self::$mimetypes, $mime_type);

        return !empty($extensions[0]) ? $extensions[0] : '';
    }
}
