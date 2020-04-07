<?php

namespace Librarian;

use FilesystemIterator;

final class GarbageCollector {

    /**
     * @var string Directory to clean.
     */
    private $dir;

    /**
     * @var int Number of files to keep.
     */
    private $keep;

    /**
     * Randomly clean one of the cache folders.
     *
     * @param  boolean $delete_all
     */
    public function cleanGarbage(bool $delete_all = false): void {

        switch (rand(1,3)) {

            case 1:
                $this->cleanIcons($delete_all);
                break;

            case 2:
                $this->cleanPages($delete_all);
                break;

            case 3:
                $this->cleanTemp($delete_all);
                break;
        }
    }

    /**
     * Clean old icon files.
     *
     * @param  boolean $delete_all
     */
    public function cleanIcons(bool $delete_all = false): void {

        $this->keep = 1000;
        $this->dir  = IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'icons';

        $this->cleanFiles($delete_all);
    }

    /**
     * Clean old PDF page images.
     *
     * @param  boolean $delete_all
     */
    public function cleanPages(bool $delete_all = false): void {

        $this->keep = 1000;
        $this->dir  = IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'pages';

        $this->cleanFiles($delete_all);
    }

    /**
     * Clean old files in temp cache. Temp files are only kept for a limited time.
     *
     * @param  boolean $delete_all
     */
    public function cleanTemp(bool $delete_all = false): void {

        // Delete older files than this many seconds.
        $ttl = 86400;

        // Iterate over the folder.
        $it = new FilesystemIterator(IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'temp', FilesystemIterator::SKIP_DOTS);

        /** @var FilesystemIterator $file */
        foreach ($it as $file) {

            // Protect settings.json from deletion.
            if ($file->getFilename() === 'settings.json') {

                continue;
            }

            if ($delete_all === true && $file->isFile() && $file->isWritable()) {

                // If all files are to be deleted.
                unlink($file->getPathname());

            } elseif ($file->isFile() && $file->isWritable() && time() - $file->getMTime() > $ttl) {

                // Delete older than TTL files.
                unlink($file->getPathname());
            }
        }

        $it = null;
    }

    /**
     * Clean old files in a directory.
     *
     * @param  boolean $delete_all
     */
    private function cleanFiles(bool $delete_all = false): void {

        // Iterate over the folder.
        $it = new FilesystemIterator($this->dir, FilesystemIterator::SKIP_DOTS);

        // If all files are to be deleted.
        if ($delete_all === true) {

            /** @var FilesystemIterator $file */
            foreach ($it as $file) {

                if ($file->isFile() && $file->isWritable()) {

                    unlink($file->getPathname());
                }
            }

            return;
        }

        // Leave $this->keep newest files.
        $files = [];

        /** @var FilesystemIterator $file */
        foreach ($it as $file) {

            if ($file->isFile() && $file->isWritable()) {

                $files[$file->getPathname()] = $file->getMTime();
            }
        }

        $it = null;

        // Only continue, if there is more than $this->keep files.
        if (count($files) <= $this->keep) {

            return;
        }

        // Sort by modified time, newest files first.
        arsort($files);

        // Keep the $this->keep newest files, delete the rest.
        $files_slice = array_slice($files, $this->keep);

        $files = null;

        foreach ($files_slice as $pathname => $mtime) {

            unlink($pathname);
        }

        $files_slice = null;
    }
}
