<?php

namespace Librarian\Mvc;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Http\Message\StreamInterface;
use Librarian\Media\FileTools;

/**
 * File View class.
 */
abstract class FileView extends View {

    /**
     * @var string Custom filename.
     */
    public $filename;

    /**
     * @var FileTools
     */
    private $file_tools;

    /**
     * @var StreamInterface
     */
    private $stream;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @param StreamInterface $stream
     * @throws Exception
     */
    function __construct(DependencyInjector $di, StreamInterface $stream) {

        $this->stream = $stream;
        parent::__construct($di);
        $this->file_tools = $di->get('FileTools');
    }

    /**
     * Set file disposition to attachment. Default is inline.
     *
     * @param string $disposition
     * @return void
     */
    protected function setDisposition(string $disposition = 'inline'): void {

        $disposition_header = $disposition === 'attachment' ? 'attachment' : 'inline';

        $metadata = $this->stream->getMetadata();

        // Get filename.
        switch ($metadata['wrapper_type']) {

            case 'plainfile':
                $filename = empty($this->filename) ? basename($metadata['uri']) : $this->filename;
                $filename = rawurlencode($filename);
                $this->response = $this->response->withHeader('Content-Disposition', "$disposition_header; filename*=UTF-8''$filename");
                break;

            case 'http':
                if (!empty($this->filename)) {
                    $filename = rawurlencode($this->filename);
                    $this->response = $this->response->withHeader('Content-Disposition', "$disposition_header; filename*=UTF-8''$filename");
                    break;
                }
                foreach ($metadata['wrapper_data'] as $header) {

                    if (strpos($header, 'Content-Disposition') === 0) {

                        $header_parts = explode(':', $header);
                        $content_disposition = trim($header_parts[1]);
                        $this->response = $this->response->withHeader('Content-Disposition', $content_disposition);
                        break;
                    }
                }
                break;

            default:
                $filename = rawurlencode('file.txt');
                $this->response = $this->response->withHeader('Content-Disposition', "$disposition_header; filename*=UTF-8''$filename");
        }
    }

    /**
     * Set last modified header. Default is now.
     *
     * @return void
     */
    protected function setLastModified(): void {

        $last_modified = gmdate('D, d M Y H:i:s', time()) . ' GMT';

        $metadata = $this->stream->getMetadata();

        switch ($metadata['wrapper_type']) {

            case 'plainfile':
                $last_modified = gmdate('D, d M Y H:i:s', filemtime($metadata['uri'])) . ' GMT';
                break;

            case 'http':
                foreach ($metadata['wrapper_data'] as $header) {

                    if (strpos($header, 'Last-Modified') === 0) {

                        $header_parts = explode(':', $header);
                        $last_modified = trim($header_parts[1]);
                        break;
                    }
                }
        }

        $this->response = $this->response->withHeader('Last-Modified', $last_modified);
    }

    /**
     * Set content type header.
     *
     * @return void
     * @throws Exception
     */
    protected function setMime(): void {

        $content_type = 'application/octet-stream';

        $metadata = $this->stream->getMetadata();

        switch ($metadata['wrapper_type']) {

            case 'plainfile':
                $content_type = $this->file_tools->getMime($metadata['uri']);
                break;

            case 'http':
                foreach ($metadata['wrapper_data'] as $header) {

                    if (strpos($header, 'Content-Type') === 0) {

                        $header_parts = explode(':', $header);
                        $content_type = trim($header_parts[1]);
                        break;
                    }
                }
        }

        $this->response = $this->response->withHeader('Content-Type', $content_type);
    }

    /**
     * Set content length.
     *
     * @return void
     */
    protected function setSize(): void {

        $size = 0;

        $metadata = $this->stream->getMetadata();

        switch ($metadata['wrapper_type']) {

            case 'plainfile':
                $size = filesize($metadata['uri']);
                break;

            case 'http':
                foreach ($metadata['wrapper_data'] as $header) {

                    if (strpos($header, 'Content-Length') === 0) {

                        $header_parts = explode(':', $header);
                        $size = trim($header_parts[1]);
                        break;
                    }
                }
        }

        if ($size > 0) {

            $this->response = $this->response->withHeader('Content-Length', $size);
        }
    }

    /**
     * Echo the response stream in small chunks. Use for files.
     *
     * @param string $disposition
     * @return string
     * @throws Exception
     */
    protected function send(string $disposition = 'inline'): string {

        // Add headers.
        $this->setMime();
        $this->setSize();
        $this->setLastModified();
        $this->setDisposition($disposition);
        $this->setCacheControl();

        // Send all headers.
        $this->sendHeaders();

        $serverParams = $this->request->getServerParams();

        // Cache control: is this file new?
        if (!empty($serverParams['HTTP_IF_MODIFIED_SINCE']) &&
            $serverParams['HTTP_IF_MODIFIED_SINCE'] === $this->response->getHeader('Last-Modified')[0]) {

            // Send 304 Not Modified, if Last-Modified match.
            http_response_code(304);
            return '';
        }

        // Response code header.
        http_response_code($this->response->getStatusCode());

        // Turn off output buffering, if active.
        if (ob_get_level() === 1) {

            ob_end_clean();
        }

        // Stream file in chunks.
        while ($this->stream->eof() === false) {

            echo $this->stream->read(65536);
        }

        return '';
    }
}
