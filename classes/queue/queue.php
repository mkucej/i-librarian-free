<?php

namespace Librarian\Queue;

use Exception;

/**
 * Server-wide queue and throttling.
 *
 * In case of multiserver setup, integrate beanstalkd.
 */
final class Queue {

    private string $lane;
    private array $semaphores;

    public function __construct() {

        if (function_exists('sem_get') === false) {

            return;
        }

        // Create semaphores.
        $sem_keys = [
            'binary'     => crc32('IL_LOCK_BINARY'),
            'pdfextract' => crc32('IL_LOCK_PDFEXTRACT'),
            'arxiv'      => crc32('IL_LOCK_ARXIV'),
            'crossref'   => crc32('IL_LOCK_CROSSREF'),
            'google'     => crc32('IL_LOCK_GOOGLE'),
            'ieee'       => crc32('IL_LOCK_IEEE'),
            'nasa'       => crc32('IL_LOCK_NASA'),
            'patents'    => crc32('IL_LOCK_PATENTS'),
            'pubmed'     => crc32('IL_LOCK_PUBMED')
        ];

        foreach ($sem_keys as $name => $key) {

            // Allow extra threads for binaries.
            $threads = $name === 'binary' ? 3 : 1;

            $this->semaphores[$name] = sem_get($key, $threads);
        }
    }

    /**
     * Acquires semaphore for a script.
     *
     * @param string $lane
     * @throws Exception
     */
    public function wait(string $lane): void {

        // Set lane property;
        $this->lane = $lane;

        // Extension not installed.
        if (function_exists('sem_get') === false) {

            return;
        }

        if (isset($this->semaphores[$lane]) === false) {

            throw new Exception('unknown queue lane specified');
        }

        // Acquire semaphore.
        sem_acquire($this->semaphores[$lane]);
    }

    /**
     * Manually release semaphore lock. Semaphore lock is released when a script
     * ends, but should be manually released earlier, if warranted.
     *
     * @param string $lane
     * @throws Exception
     */
    public function release(string $lane): void {

        if (function_exists('sem_get') === false) {

            return;
        }

        if (isset($this->semaphores[$lane]) === false) {

            throw new Exception('unknown queue lane specified');
        }

        if ($this->lane !== $lane) {

            throw new Exception('incorrect queue lane specified');
        }

        // Release the semaphore lock.
        sem_release($this->semaphores[$lane]);
    }
}
