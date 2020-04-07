<?php

namespace Librarian\Security;

use Exception;

/**
 * Validation.
 *
 * All methods return `true` on pass, and `false` on fail. Upon failed
 * validation, an error message is written to the public property
 * Validation::error.
 */
final class Validation {

    public $error;

    /**
     * URL.
     *
     * @param string $value
     * @return bool
     */
    public function url(string $value): bool {

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {

            $this->error = "is not a valid URL address";
            return false;
        }

        return true;
    }

    /**
     * Http(s) URL.
     *
     * @param string $value
     * @return bool
     */
    public function link(string $value): bool {

        // First check URL.
        if ($this->url($value) === false) {

            return false;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if ($scheme !== 'http' && $scheme !== 'https') {

            $this->error = "is not a valid web link";
            return false;
        }

        return true;
    }

    /**
     * Https URL.
     *
     * @param string $value
     * @return bool
     */
    public function tlsLink(string $value): bool {

        // First check URL.
        if ($this->url($value) === false) {

            return false;
        }

        if (parse_url($value, PHP_URL_SCHEME) !== 'https') {

            $this->error = "is not a secure web link";
            return false;
        }

        return true;
    }

    /**
     * SSRF-safe URL.
     *
     * @param string $value
     * @return bool
     */
    public function ssrfLink(string $value): bool {

        // First check URL.
        if ($this->url($value) === false) {

            return false;
        }

        $host = parse_url($value, PHP_URL_HOST);

        // Prevent IP based addresses and localhost.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)  || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {

            $this->error = "can't be an IP-host URL";
            return false;
        }

        // Deal with wildcard DNS.
        $ip = gethostbyname($host);
        $ip = ip2long($ip);

        if ($ip === false) {

            $this->error = "is not a valid URL address";
            return false;
        }

        $is_inner_ipaddress =
            ip2long('127.0.0.0')   >> 24 === $ip >> 24 or
            ip2long('10.0.0.0')    >> 24 === $ip >> 24 or
            ip2long('172.16.0.0')  >> 20 === $ip >> 20 or
            ip2long('169.254.0.0') >> 16 === $ip >> 16 or
            ip2long('192.168.0.0') >> 16 === $ip >> 16;

        if ($is_inner_ipaddress) {

            $this->error = "can't be an internal network address";
            return false;
        }

        return true;
    }

    /**
     * Email.
     *
     * @param string $value
     * @return bool
     */
    public function email(string $value): bool {

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {

            $this->error = "is not a valid email address";
            return false;

        }

        return true;
    }

    /**
     * Latin letters.
     *
     * @param string $value
     * @return bool
     */
    public function alpha(string $value): bool {

        if (preg_match('/^[[:alpha:]]*$/', $value) === 0) {

            $this->error = "must contain letters only";
            return false;

        }

        return true;
    }

    /**
     * Latin letters and numbers.
     *
     * @param $value
     * @return bool
     */
    public function alphanum($value): bool {

        if (preg_match('/^[[:alnum:]]*$/', $value) === 0) {

            $this->error = "must contain alphanumeric characters only";
            return false;
        }

        return true;
    }

    /**
     * 0-9.
     *
     * @param $value
     * @return bool
     */
    public function num($value): bool {

        if (preg_match('/^[[:digit:]]*$/', $value) === 0) {

            $this->error = "must contain numeric characters only";
            return false;
        }

        return true;
    }

    /**
     * Byte length.
     *
     * @param $value
     * @param int $length
     * @return bool
     */
    public function length($value, int $length): bool {

        if (strlen($value) > $length) {

            $this->error = "exceeded the required length of $length";
            return false;
        }

        return true;
    }

    /**
     * Multibyte length.
     *
     * @param $value
     * @param int $length
     * @return bool
     * @throws Exception
     */
    public function mbLength($value, int $length): bool {

        if (extension_loaded('mbstring') === false) {

            throw new Exception("missing PHP extension mbstring", 500);
        }

        if (mb_strlen($value, 'UTF-8') > $length) {

            $this->error = "exceeded the required length of $length";
            return false;
        }

        return true;
    }

    /**
     * Password.
     *
     * @param string $value
     * @return bool
     * @throws Exception
     */
    public function password(string $value): bool {

        if (extension_loaded('mbstring') === false) {

            throw new Exception("missing PHP extension mbstring", 500);
        }

        $min_length = 8;
        $max_length = 1024;

        if (mb_strlen($value, 'UTF-8') < $min_length) {

            $this->error = "must be at least $min_length characters";
            return false;
        }

        if ($this->mbLength($value, $max_length) === false) {

            return false;
        }

        if ($this->num($value) === true) {

            $this->error = "must not be a number";
            return false;
        }

        return true;
    }

    /**
     * Directory name - no double dots allowed.
     *
     * @param string $value
     * @return bool
     */
    public function dirname(string $value): bool {

        // No double dots allowed.
        if (strpos($value, '..') !== false) {

            $this->error = "is invalid directory name";
            return false;
        }

        return true;
    }

    /**
     * LDAP string.
     *
     * @param string $value
     * @return bool
     */
    public function ldap(string $value): bool {

        // Not allowed.
        $chars = [',', '\\', '#', '+', '<', '>', ';', '"', '=', ' '];

        foreach ($chars as $char) {

            if (strpos($value, $char) !== false) {

                $this->error = "is invalid LDAP string";
                return false;
            }
        }

        return true;
    }

    /**
     * ID must be integer in a range.
     *
     * @param int $value
     * @return bool
     */
    public function id($value): bool {

        if ($this->num($value) === false) {

            return false;
        }

        if ($this->intRange($value, 1, pow(2, 32)) === false) {

            return false;
        }

        return true;
    }

    /**
     * Integer is in range.
     *
     * @param  int $value
     * @param  int $min
     * @param  int $max
     * @return bool
     */
    public function intRange($value, int $min, int $max): bool {

        $result = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => $min,
                    'max_range' => $max
                ]
            ]
        );

        if ($result === false) {

            $this->error = "is out of range";
            return false;
        }

        return true;
    }
}
