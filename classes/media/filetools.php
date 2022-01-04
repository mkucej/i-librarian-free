<?php

namespace Librarian\Media;

use Exception;
use finfo;
use Librarian\Http\Psr\Message\StreamInterface;
use SplFileObject;

final class FileTools {

    /**
     * @var SplFileObject
     */
    private $file;

    /**
     * @var StreamInterface
     */
    private $stream;

    private $wrong_mime_types = [
        'webma' => 'audio/webm'
    ];

    /**
     * Add file to object.
     *
     * @param StreamInterface|string $file
     */
    private function addFile($file): void {

        if (is_string($file)) {

            // Pathname.
            $this->file = new SplFileObject($file);
            $this->stream = null;

        } elseif (is_object($file)) {

            // Stream.
            $path = $file->getMetadata('uri');
            $this->file = new SplFileObject($path);
            $this->stream = $file;
        }
    }

    /**
     * Get MIME type.
     *
     * @param StreamInterface|string $file
     * @return string
     * @throws Exception
     */
    public function getMime($file): string {

        $mime = '';
        $this->addFile($file);

        // MIME types not correctly recognized.
        if (isset($this->wrong_mime_types[$this->file->getExtension()]) === true) {

             return $this->wrong_mime_types[$this->file->getExtension()];
        }

        $info = new finfo(FILEINFO_MIME_TYPE);

        if (is_object($this->stream) === true) {

            /*
             * Get MIME from a chunk of a seekable stream.
             */

            if ($this->stream->isSeekable() === false) {

                throw new Exception('cannot get MIME type of a non-seekable stream', 500);
            }

            // Rewind and read the top.
            $this->stream->rewind();
            $chunk = $this->stream->read(1024);
            $this->stream->rewind();

            $mime = $info->buffer($chunk);

        } elseif (is_object($this->file) === true) {

            /*
             * Get MIME from a filename.
             */

            if ($this->file->isReadable() === false) {

                throw new Exception('cannot get MIME type, file is not readable', 500);
            }

            $mime = $info->file($this->file->getPathname());

        } else {

            throw new Exception('cold not load filename or stream', 500);
        }

        /**
         * Compensate for PHP bug #77784
         * @see https://bugs.php.net/bug.php?id=77784
         */
        $mime_1 = substr($mime, 0, strlen($mime) / 2);
        $mime_2 = substr($mime, -1 * strlen($mime) / 2);

        return $mime_1 === $mime_2 ? $mime_1 : $mime;
    }
}
