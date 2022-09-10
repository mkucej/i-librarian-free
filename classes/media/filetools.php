<?php

namespace Librarian\Media;

use Exception;
use finfo;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use SplFileObject;

final class FileTools {

    /**
     * @var SplFileObject
     */
    private SplFileObject $file;

    /**
     * @var StreamInterface
     */
    private $stream;

    private array $wrong_mime_types = [
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
        $mime_1 = substr($mime, 0, ceil(strlen($mime) / 2));
        $mime_2 = substr($mime, -1 * ceil(strlen($mime) / 2));

        return $mime_1 === $mime_2 ? $mime_1 : $mime;
    }

    /**
     * Make dir.
     *
     * @param string $dir
     * @return void
     * @throws Exception
     */
    public function makeDir(string $dir): void {

        if (!is_dir($dir)) {

            $mkdir = mkdir($dir, 0755, true);

            if ($mkdir === false) {

                throw new Exception('could not create the directory', 500);
            }
        }
    }

    /**
     * Write file from Stream.
     *
     * @param string $filepath
     * @param StreamInterface $stream
     * @return int
     * @throws Exception
     */
    public function writeFile(string $filepath, StreamInterface $stream): int {

        setlocale(LC_ALL,'en_US.UTF-8');

        // Make sure the dir exists.
        $this->makeDir(pathinfo($filepath, PATHINFO_DIRNAME));

        $written = 0;

        if (strlen(basename($filepath)) > 250) {

            throw new Exception('filename is too long', 400);
        }

        try {

            $fp = Utils::tryFopen($filepath, 'w');

        } catch (Exception $exc) {

            $exc = null;
            throw new Exception('could not write file', 500);
        }

        while (!$stream->eof()) {

            $fwrite = fwrite($fp, $stream->read(4096));

            if ($fwrite === false) {

                throw new Exception('could not write file', 500);
            }

            $written += $fwrite;
        }

        fclose($fp);

        return $written;
    }
}
