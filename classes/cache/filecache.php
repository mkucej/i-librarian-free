<?php

namespace Librarian\Cache;

use Exception;

/**
 * Caching using the file storage.
 *
 * User needs to set context for each Cache object. Unique key can be generated
 * for any serializable input. The PSR interface is not used, because it does
 * not have required features. This class enables comparing hashes and last
 * modified integers to find a match in the cache.
 *
 * File storage is the faster option compared with SQLite. Lookup benchmarks:
 *     SQlite .................. 0.50 ms
 *     File glob (500 files) ... 0.25 ms
 *     File is_writable ........ 0.05 ms
 */
final class FileCache {

    /**
     * @var string Key hashing algorithm.
     */
    private $algo;

    /**
     * @var string Cache context.
     */
    private $context;

    /**
     * @var array Allowed cache contexts.
     */
    private $contexts;

    /**
     * @var string File prefix.
     */
    private $prefix;

    /**
     * @var int Default time to live.
     */
    private $ttl;

    /**
     * Constructor. Install database, if empty. Set algorithm, TTL.
     */
    public function __construct() {

        $this->algo     = 'fnv1a32';
        $this->ttl      =  300;
        $this->contexts = [
            'default',
            'searches',
            'repositories',
            'icons',
            'pages',
            'bookmarks'
        ];
    }

    /**
     * Fetches a value from the cache.
     *
     * @param  string $key   The unique key of this item in the cache.
     * @param  mixed  $match PDF hash, last modified integer, or null.
     * @return mixed         The value from the cache, or null in case of miss.
     * @throws Exception    Thrown if the $key string is not a legal value.
     */
    public function get(string $key, $match = null) {

        if ($this->isValidKey($key) === false) {

            throw new Exception('invalid cache key format');
        }

        if (isset($this->prefix) === false) {

            throw new Exception('valid context must be set for this cache object');
        }

        // Tag type.
        if (isset($match) === false) {

            // Expires.

            $files = glob("{$this->prefix}{$key}_*", GLOB_NOSORT);

            if (isset($files[0]) && is_writable($files[0])) {

                $expires = substr(strrchr($files[0], '_'), 1);

                if ((integer) $expires > time()) {

                    return $this->loadContent($files[0]);

                } else {

                    unlink($files[0]);
                }
            }

        } elseif (strlen($match) === 32 && ctype_xdigit($match) === true) {

            // Hash.

            if (is_writable("{$this->prefix}{$key}_{$match}")) {

                return $this->loadContent("{$this->prefix}{$key}_{$match}");
            }

        } elseif (is_numeric($match) === true && (integer) $match > 1500000000) {

            // Modified.

            if (is_writable("{$this->prefix}{$key}_{$match}")) {

                return $this->loadContent("{$this->prefix}{$key}_{$match}");
            }

        } else {

            throw new Exception("cache tag not recognized");
        }

        return null;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key.
     *
     * @param string $key   The key of the item to store.
     * @param mixed  $value The value of the item to store. Must be serializable.
     * @param mixed  $match Optional. The match tag value of this item. PDF hash,
     *                      last modified integer, optional TTL. If no value is
     *                      sent, then the library will set a default value.
     * @return bool         True on success and false on failure.
     * @throws Exception   Thrown if the $key string is not a legal value.
     */
    public function set(string $key, $value, $match = null) {

        if ($this->isValidKey($key) === false) {

            throw new Exception('invalid cache key format');
        }

        if (isset($this->prefix) === false) {

            throw new Exception('valid context must be set for this cache object');
        }

        // Tag type.
        if (isset($match) === false || (is_numeric($match) === true && (integer) $match < 1500000000)) {

            // Expires.

            $expires = isset($match) ? time() + $match : time() + $this->ttl;
            $filename = "{$this->prefix}{$key}_{$expires}";

            return $this->saveContent($filename, $value);

        } elseif (strlen($match) === 32 && ctype_xdigit($match) === true) {

            // Hash.

            $filename = "{$this->prefix}{$key}_{$match}";

            return $this->saveContent($filename, $value);

        } elseif (is_numeric($match) === true && (integer) $match > 1500000000) {

            // Modified.

            $filename = "{$this->prefix}{$key}_{$match}";

            return $this->saveContent($filename, $value);

        } else {

            throw new Exception('cache tag not recognized');
        }
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param  string $key The unique cache key of the item to delete.
     * @return bool        True if the item was removed, false otherwise.
     * @throws Exception  Thrown if the $key string is not a legal value.
     */
    public function delete(string $key) {

        if ($this->isValidKey($key) === false) {

            throw new Exception('invalid cache key format');
        }

        if (isset($this->prefix) === false) {

            throw new Exception('valid context must be set for this cache object');
        }

        $files = glob("{$this->prefix}{$key}_*", GLOB_NOSORT);

        if (isset($files[0]) && is_writable($files[0])) {

            return unlink($files[0]);
        }

        return false;
    }

    /**
     * Wipes clean the entire cache's keys. Use only for debugging purposes.
     *
     * @return bool True on success, false on failure.
     * @throws Exception
     */
    public function clear() {

        if (isset($this->prefix) === false) {

            throw new Exception('valid context must be set for this cache object');
        }

        $files = glob("{$this->prefix}*", GLOB_NOSORT);

        foreach ($files as $file) {

            unlink($file);
        }

        return true;
    }

    /**
     * Create key.
     *
     * @param  string|array $input
     * @return string
     */
    public function key($input) {

        return hash($this->algo, serialize($input));
    }

    /**
     * Check if the key has valid format. User might have forgotten to get
     * the unique key using $this->key().
     *
     * @param  string $key
     * @return boolean
     */
    private function isValidKey(string $key) {

        if (strlen(hash($this->algo, 'foo')) === strlen($key) && ctype_xdigit($key) === true) {

            return true;
        }

        return false;
    }

    /**
     * Get/set cache context. Set storage file prefix.
     *
     * @param  string $context [default, searches, repositories]
     * @return string
     */
    public function context(string $context = null) {

        if (isset($context) === true && in_array($context, $this->contexts) === true) {

            $this->context = $context;

            // Set storage file prefix.
            switch ($context) {

                case 'icons':
                    $this->prefix = IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR;
                    break;

                case 'pages':
                    $this->prefix = IL_CACHE_PATH . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR;
                    break;

                default:
                    $this->prefix = IL_TEMP_PATH . DIRECTORY_SEPARATOR . "ilcache_{$context}_";
                    break;
            }
        }

        return $this->context;
    }

    /**
     * Saving a value to the cache file varies for different contexts.
     *
     * @param string $filename
     * @param mixed $value
     * @return boolean
     * @throws Exception
     */
    private function saveContent(string $filename, $value) {

        if (isset($this->context) === false) {

            throw new Exception('valid context must be set for this cache object');
        }

        switch ($this->context) {

            case 'icons':
            case 'pages':
                // Value is a file pathname. Move file to cache location.
                return rename($value, $filename);

            default:
                // Value is a scalar, or array. Serialize value and write to file.
                $bytes = file_put_contents($filename, serialize($value), LOCK_EX);
                return $bytes === false ? false : true;
        }
    }

    /**
     * Loading a value from the cache file varies for different contexts.
     *
     * @param string $filename
     * @return mixed
     * @throws Exception
     */
    private function loadContent(string $filename) {

        if (isset($this->context) === false) {

            throw new Exception('valid context must be set for this cache object');
        }

        switch ($this->context) {

            case 'icons':
            case 'pages':
                // Nothing to do. Value is a cached file pathname.
                return $filename;

            default:
                // Value is a scalar, or array. Unserialize value and return.
                $value = file_get_contents($filename);
                return $value === false ? null : unserialize($value);
        }
    }
}
