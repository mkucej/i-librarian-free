<?php

namespace Librarian\Media;

use Exception;
use Librarian\AppSettings;
use Librarian\Container\DependencyInjector;

/**
 * Class to return correct commands for executable programs.
 */
final class Binary {

    /**
     * @var AppSettings
     */
    private $settings;

    /**
     * @var string  WINDOWS|MAC|LINUX
     */
    private string $os;

    /**
     * Binary constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        $this->settings = $di->getShared('AppSettings');

        // Set the OS.
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {

            $this->os = 'WINDOWS';

        } elseif (PHP_OS === 'Darwin') {

            $this->os = 'MAC';

        } elseif (DIRECTORY_SEPARATOR === '/') {

            // Required for CentOS.
            putenv("LD_LIBRARY_PATH=/");

            $this->os = 'LINUX';
        }
    }

    /**
     * @param bool $check
     * @return string
     * @throws Exception
     */
    public function pdfinfo(bool $check = false): string {

        return $this->command('pdfinfo', $check);
    }

    /**
     * @param bool $check
     * @return string
     * @throws Exception
     */
    public function pdftotext(bool $check = false): string {

        return $this->command('pdftotext', $check);
    }

    /**
     * @param bool $check
     * @return string
     * @throws Exception
     */
    public function pdftohtml(bool $check = false): string {

        return $this->command('pdftohtml', $check);
    }

    /**
     * @param bool $check
     * @return string
     * @throws Exception
     */
    public function pdftocairo(bool $check = false): string {

        return $this->command('pdftocairo', $check);
    }

    /**
     * @param bool $check
     * @return string
     * @throws Exception
     */
    public function pdftoppm(bool $check = false): string {

        return $this->command('pdftoppm', $check);
    }

    /**
     * @param bool $check
     * @return string
     * @throws Exception
     */
    public function ghostscript(bool $check = false): string {

        return $this->command('gs', $check);
    }

    /**
     * @param bool $check
     * @return string
     * @throws Exception
     */
    public function tesseract(bool $check = false): string {

        return $this->command('tesseract', $check);
    }

    /**
     * @param bool $check
     * @return string
     * @throws Exception
     */
    public function soffice(bool $check = false): string {

        return $this->command('soffice', $check);
    }

    /**
     * Return the program name. Optionally check whether it is installed.
     *
     * @param string $binary
     * @param boolean $check Check if installed.
     * @return string
     * @throws Exception
     */
    private function command(string $binary, bool $check): string {

        $binary = $this->withPath($binary);

        // Check if installed.
        if ($check === true) {

            $this->isInstalled($binary);
        }

        return "\"$binary\"";
    }

    /**
     * Check if the program is installed.
     *
     * @param string $binary
     * @return bool
     * @throws Exception
     */
    public function isInstalled(string $binary): bool {

        if ($this->os === 'WINDOWS') {

            if (is_executable($this->withPath($binary)) === false) {

                throw new Exception("the program <kbd>$binary</kbd> not found", 500);
            }

        } else {

            if (is_string(shell_exec(sprintf("command -v %s", escapeshellarg($binary)))) === false &&
                is_executable($this->withPath($binary)) === false) {

                throw new Exception("the program <kbd>$binary</kbd> not found", 500);
            }
        }

        return true;
    }

    /**
     * Add full path to the executable name. Windows only.
     *
     * @param  string $binary
     * @return string
     * @throws Exception
     */
    private function withPath(string $binary): string {

        switch ($binary) {

            case 'gs':

                $name = $this->os === 'WINDOWS' ? 'gs.exe' : 'gs';
                $path = IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'gs' . DIRECTORY_SEPARATOR . $name;

                if (is_executable($path) === true) {

                    return $path;

                }

                return $binary;

            case 'pdfinfo':
            case 'pdftotext':
            case 'pdftohtml':
            case 'pdftocairo':
            case 'pdftoppm':

                $name = $this->os === 'WINDOWS' ? "$binary.exe" : $binary;
                $path = IL_PRIVATE_PATH . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'poppler' . DIRECTORY_SEPARATOR . $name;

                if (is_executable($path) === true) {

                    return $path;
                }

                return $binary;

            case 'tesseract':

                $name = $this->os === 'WINDOWS' ? "$binary.exe" : $binary;

                if ($this->settings->getGlobal('tesseract_path') !== '') {

                    return $this->settings->getGlobal('tesseract_path') . DIRECTORY_SEPARATOR . $name;

                } elseif($this->os === 'WINDOWS') {

                    return "%PROGRAMFILES%\\Tesseract-OCR\\tesseract.exe";

                }

                return $binary;

            case 'soffice':

                $name = $this->os === 'WINDOWS' ? "$binary.exe" : $binary;

                if ($this->settings->getGlobal('soffice_path') !== '') {

                    return $this->settings->getGlobal('soffice_path') . DIRECTORY_SEPARATOR . $name;

                } elseif($this->os === 'WINDOWS') {

                    return "%PROGRAMFILES%\\LibreOffice\\program\\soffice.exe";
                }

                return $binary;

            default:
                throw new Exception("unknown executable requested", 400);
        }
    }
}
