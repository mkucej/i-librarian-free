<?php

namespace Librarian\Queue;

use Exception;

/**
 * Server-wide queue and throttling.
 *
 * In case of multi-server setup, integrate beanstalkd.
 */
final class Queue {

    private $delays     = [];
    private $sem_keys   = [];
    private $lane;
    private $semaphores = [];
    private $shmvars    = [];

    public function __construct() {

        if (function_exists('sem_get') === false) {

            return;
        }

        // Delays. Interval in microsecs. E.g. 5 req/sec = 200,000 microsecs.
        $this->delays = [
            'pubmed' => 350000,
            'nasa'   => 100000
        ];

        // Create semaphores keys.
        $this->sem_keys = [
            'binary'     => crc32('IL_LOCK_BINARY'),
            'pubmed'     => crc32('IL_LOCK_PUBMED'),
            'nasa'       => crc32('IL_LOCK_NASA'),
            'pdfextract' => crc32('IL_LOCK_PDFEXTRACT')
        ];

        // Create shared counts.
        $IL_SHM_BINARY     = crc32('IL_SHM_BINARY');
        $IL_SHM_PUBMED     = crc32('IL_SHM_PUBMED');
        $IL_SHM_NASA       = crc32('IL_SHM_NASA');
        $IL_SHM_PDFEXTRACT = crc32('IL_SHM_PDFEXTRACT');

        $this->shmvars['binary']     = shm_attach($IL_SHM_BINARY);
        $this->shmvars['pubmed']     = shm_attach($IL_SHM_PUBMED);
        $this->shmvars['nasa']       = shm_attach($IL_SHM_NASA);
        $this->shmvars['pdfextract'] = shm_attach($IL_SHM_PDFEXTRACT);
    }

    /**
     * Choose a queue lane. Sets the correct semaphore context.
     *
     * @param string $lane
     * @return void
     * @throws Exception
     */
    public function lane(string $lane): void {

        switch ($lane) {

            case "binary":
                $this->lane = 'binary';
                break;

            case "pubmed":
                $this->lane = 'pubmed';
                break;

            case "nasa":
                $this->lane = 'nasa';
                break;

            case "pdfextract":
                $this->lane = 'pdfextract';
                break;

            default:
                throw new Exception('unknown queue lane selected');
        }
    }

    /**
     * Acquires semaphore for a script.
     *
     * @return bool
     */
    public function wait(): bool {

        if (function_exists('sem_get') === false) {

            return true;
        }

        $this->semaphores[$this->lane] = sem_get($this->sem_keys[$this->lane]);

        // Some scripts need to be delayed. Delays are very undesirable, but
        // there are APIs that could ban the server, if RPS is too high.
        $this->delay();

        // Acquire semaphore.
        $acquire = sem_acquire($this->semaphores[$this->lane]);

        // Last access is the time of acquire + delay.
        $this->setLastAccess();

        return $acquire;
    }

    /**
     * Lock is an alias for `wait`.
     *
     * @return bool
     */
    public function lock(): bool {

        return $this->wait();
    }

    /**
     * Manually release semaphore lock. Semaphore lock is released when a script
     * ends, but can be manually released earlier, if warranted.
     *
     * @return void
     */
    public function release(): void {

        if (function_exists('sem_get') === false) {

            return;
        }

        // Release the semaphore lock.
        sem_release($this->semaphores[$this->lane]);
        $this->semaphores[$this->lane] = null;

        return;
    }

    /**
     * Delay script for required time. Works with microseconds.
     *
     * @return void
     */
    private function delay(): void {

        // If delay not set.
        if (isset($this->delays[$this->lane]) === false) {

            return;
        }

        $delay = max(0, $this->delays[$this->lane] - 1000000 * (microtime(true) - $this->getLastAccess()));

        usleep($delay);
    }

    /**
     * Get or set a script request count.
     *
     * @param  int|null $count
     * @return int|bool
     */
    public function count(int $count = null) {

        if (function_exists('sem_get') === false) {

            return 0;
        }

        // Setter.
        if (isset($count)) {

            return shm_put_var($this->shmvars[$this->lane], 2, $count);
        }

        // Getter.
        if (shm_has_var($this->shmvars[$this->lane], 2) === false) {

            return 0;

        } else {

            return shm_get_var($this->shmvars[$this->lane], 2);
        }
    }

    /**
     * Get or set a script request count maximum.
     *
     * @param  int|null $max_count
     * @return int|bool
     */
    public function maxCount(int $max_count = null) {

        if (function_exists('sem_get') === false) {

            return 0;
        }

        // Setter.
        if (isset($max_count)) {

            return shm_put_var($this->shmvars[$this->lane], 3, $max_count);
        }

        // Getter.
        if (shm_has_var($this->shmvars[$this->lane], 3) === false) {

            return null;

        } else {

            return shm_get_var($this->shmvars[$this->lane], 3);
        }
    }

    /**
     * Get the last time the script was released as UNIX timestamp float.
     *
     * @return float
     */
    private function getLastAccess(): float {

        if (function_exists('sem_get') === false) {

            return microtime(true) - 1;
        }

        if (shm_has_var($this->shmvars[$this->lane], 1) === false) {

            $accessed = microtime(true) - 1;

        } else {

            $accessed = shm_get_var($this->shmvars[$this->lane], 1);
        }

        return $accessed;
    }

    /**
     * Set the last time the script was released as UNIX timestamp float.
     *
     * @return bool|null
     */
    private function setLastAccess() {

        if (function_exists('sem_get') === false) {

            return null;
        }

        return shm_put_var($this->shmvars[$this->lane], 1, microtime(true));
    }

    /**
     * Delete semaphores and SHM segments. Only use for debugging.
     *
     * @return void
     */
    public function delete(): void {

        if (function_exists('sem_get')) {

            foreach ($this->semaphores as $semaphore) {

                sem_remove($semaphore);
            }

            foreach ($this->shmvars as $count) {

                shm_remove($count);
            }
        }
    }

    public function raiseCount() {

        if (function_exists('sem_get') === false) {

            return null;
        }

        if (shm_has_var($this->shmvars[$this->lane], 2) === false) {

            $count = 1;

        } else {

            $count = shm_get_var($this->shmvars[$this->lane], 2);
            $count++;
        }

        return shm_put_var($this->shmvars[$this->lane], 2, $count);
    }

    public function resetCount(): bool {

        if (function_exists('sem_get')) {

            return shm_put_var($this->shmvars[$this->lane], 2, 0);
        }

        return false;
    }
}
