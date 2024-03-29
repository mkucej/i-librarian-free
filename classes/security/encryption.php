<?php

namespace Librarian\Security;

use Exception;

/**
 * Encryption.
 */
final class Encryption {

    /**
     * Hashing presets.
     *
     * @var array
     */
    private array $algorithms = [
        '2y' => [
            'cost' => 11
        ],
        'argon2i' => [
            'memory_cost' => 10000,
            'threads'     => 1,
            'time_cost'   => 5
        ],
        'argon2id' => [
            'memory_cost' => 10000,
            'threads'     => 1,
            'time_cost'   => 5
        ]
    ];

    /**
     * Generate cryptographically-sound random hexadecimal/raw key, 8 to 512 chars long.
     *
     * @param  integer $length String length of the resulting key.
     * @param  boolean $raw
     * @return string
     * @throws Exception
     */
    public function getRandomKey(int $length, bool $raw = false): string {

        if ($length < 8 || $length > 512) {

            throw new Exception('invalid key byte length', 500);
        }

        $key = random_bytes($length / 2);

        // Return raw string, or hexadecimal representation.
        return $raw === true ? $key : bin2hex($key);
    }

    /**
     * Hash password.
     *
     * @param  string $password
     * @return string
     * @throws Exception
     */
    public function hashPassword(string $password): string {

        $algo = $this->getAlgorithm();

        return password_hash($password, key($algo), current($algo));
    }

    /**
     * Verify password.
     *
     * @param  string $password (user-supplied)
     * @param  string $storedPassword (from database)
     * @return boolean
     */
    public function verifyPassword(string $password, string $storedPassword): bool {

        return password_verify($password, $storedPassword);
    }

    /**
     * Rehash password if necessary.
     *
     * @param  string $password
     * @param  string $storedPassword
     * @return string|boolean
     * @throws Exception
     */
    public function rehashPassword(string $password, string $storedPassword) {

        $algo = $this->getAlgorithm();

        if (password_needs_rehash($storedPassword, key($algo), current($algo)) === true) {

            return $this->hashPassword($password);
        }

        return false;
    }

    /**
     * Encrypt string using AES with a 256-bit key.
     *
     * @param  string $string
     * @param  string $key
     * @return string
     * @throws Exception
     */
    public function encrypt(string $string, string $key): string {

        if (!function_exists('openssl_encrypt')) {

            throw new Exception('OpenSSL extension required', 500);
        }

        if (strlen($key) != 64) {

            // Must use a 256 bit/32 Byte key.
            throw new Exception('invalid encryption key', 500);
        }

        // Create random initialization vector, must be 128 bit/16 Byte raw string.
        $iv = $this->getRandomKey(32, true);

        $encrypted_str = openssl_encrypt($string, 'aes-256-cbc', $key, 0, $iv);

        // Concatenate encrypted string with IV for database storage.
        return $encrypted_str . '|' . bin2hex($iv);
    }

    /**
     * Decrypt AES-256.
     *
     * @param  string $string
     * @param  string $key
     * @return string
     * @throws Exception
     */
    public function decrypt(string $string, string $key): string {

        if (!function_exists('openssl_decrypt')) {

            throw new Exception('OpenSSL extension required', 500);
        }

        if (strlen($key) != 64) {

            // Must use a 256 bit/32 B key.
            throw new Exception('invalid encryption key', 500);
        }

        list($encrypted_str, $iv) = explode('|', $string);

        if (empty($encrypted_str) || empty($iv)) {

            // Encrypted string must have an IV attached.
            throw new Exception('invalid encrypted string', 500);
        }

        return openssl_decrypt($encrypted_str, 'aes-256-cbc', $key, 0, hex2bin($iv));
    }

    /**
     * Select the strongest available hashing algorithm.
     *
     * @return array
     */
    private function getAlgorithm(): array {

        $algorithms = password_algos();
        $best_algorithm = array_pop($algorithms);

        // Pluck the preset from the array.
        return array_intersect_key($this->algorithms, [$best_algorithm => '']);
    }
}
