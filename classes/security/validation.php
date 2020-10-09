<?php

namespace Librarian\Security;

use Exception;

/**
 * Validation.
 *
 * All methods return Exception on fail.
 */
final class Validation {

    /**
     * @var int HTTP status code for all errors.
     */
    private $http_code = 422;

    /**
     * URL.
     *
     * @param string $value
     * @throws Exception
     */
    public function url(string $value): void {

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {

            throw new Exception('invalid URL address provided', $this->http_code);
        }
    }

    /**
     * Http(s) URL.
     *
     * @param string $value
     * @throws Exception
     */
    public function link(string $value): void {

        // First check URL.
        $this->url($value);

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if ($scheme !== 'http' && $scheme !== 'https') {

            throw new Exception('invalid web link provided', $this->http_code);
        }
    }

    /**
     * Https URL.
     *
     * @param string $value
     * @throws Exception
     */
    public function tlsLink(string $value): void {

        // First check URL.
        $this->url($value);

        if (parse_url($value, PHP_URL_SCHEME) !== 'https') {

            throw new Exception('provided web link is insecure', $this->http_code);
        }
    }

    /**
     * SSRF-safe URL.
     *
     * @param string $value
     * @throws Exception
     */
    public function ssrfLink(string $value): void {

        // First check URL.
        $this->url($value);

        $host = parse_url($value, PHP_URL_HOST);

        // Prevent IP based addresses and localhost.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)  || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {

            throw new Exception('invalid link to an IP-host', $this->http_code);
        }

        // Deal with wildcard DNS.
        $ip = gethostbyname($host);
        $ip = ip2long($ip);

        if ($ip === false) {

            throw new Exception('invalid link provided', $this->http_code);
        }

        $is_inner_ipaddress =
            ip2long('127.0.0.0')   >> 24 === $ip >> 24 or
        ip2long('10.0.0.0')    >> 24 === $ip >> 24 or
        ip2long('172.16.0.0')  >> 20 === $ip >> 20 or
        ip2long('169.254.0.0') >> 16 === $ip >> 16 or
        ip2long('192.168.0.0') >> 16 === $ip >> 16;

        if ($is_inner_ipaddress) {

            throw new Exception('invalid link to internal network', $this->http_code);
        }
    }

    /**
     * Email.
     *
     * @param string $value
     * @throws Exception
     */
    public function email(string $value): void {

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {

            throw new Exception('invalid email address provided', $this->http_code);
        }
    }

    /**
     * Latin letters.
     *
     * @param string $value
     * @throws Exception
     */
    public function alpha(string $value): void {

        if (preg_match('/^[a-zA-Z]*$/', $value) === 0) {

            throw new Exception('provided text must contain letters only', $this->http_code);
        }
    }

    /**
     * Latin letters and numbers.
     *
     * @param $value
     * @throws Exception
     */
    public function alphanum($value): void {

        if (preg_match('/^[a-zA-Z0-9]*$/', $value) === 0) {

            throw new Exception('provided text must contain letters and numbers only', $this->http_code);
        }
    }

    /**
     * 0-9.
     *
     * @param $value
     * @throws Exception
     */
    public function num($value): void {

        if (preg_match('/^[0-9]*$/', $value) === 0) {

            throw new Exception('invalid number provided', $this->http_code);
        }
    }

    /**
     * Byte length.
     *
     * @param $value
     * @param int $length
     * @throws Exception
     */
    public function length($value, int $length): void {

        if (strlen($value) > $length) {

            throw new Exception('provided text exceeded maximum length', $this->http_code);
        }
    }

    /**
     * Multibyte length.
     *
     * @param $value
     * @param int $length
     * @throws Exception
     */
    public function mbLength($value, int $length): void {

        if (extension_loaded('mbstring') === false) {

            throw new Exception('missing PHP extension mbstring', 500);
        }

        if (mb_strlen($value, 'UTF-8') > $length) {

            throw new Exception('provided text exceeded maximum length', $this->http_code);
        }
    }

    /**
     * Password.
     *
     * @param string $value
     * @throws Exception
     */
    public function password(string $value): void {

        if (extension_loaded('mbstring') === false) {

            throw new Exception("missing PHP extension mbstring", 500);
        }

        $min_length = 8;
        $max_length = 1024;

        if (mb_strlen($value, 'UTF-8') < $min_length) {

            throw new Exception(sprintf("provided password must have at least %s characters", $min_length), $this->http_code);
        }

        if ($this->mbLength($value, $max_length) === false) {

            throw new Exception(sprintf("provided password may have maximum of %s characters", $max_length), $this->http_code);
        }

        try {

            $this->num($value);
            $is_number = true;

        } catch (Exception $exc) {

            $is_number = false;

        } finally {

            if ($is_number === true) {

                throw new Exception('provided password must not be a number', $this->http_code);
            }
        }
    }

    /**
     * Path name - no double dots allowed.
     *
     * @param string $value
     * @throws Exception
     */
    public function dirname(string $value): void {

        // No double dots allowed.
        if (strpos($value, '..') !== false) {

            throw new Exception('invalid path name provided', $this->http_code);
        }
    }

    /**
     * LDAP string.
     *
     * @param string $value
     * @throws Exception
     */
    public function ldap(string $value): void {

        // Not allowed.
        $chars = [',', '\\', '#', '+', '<', '>', ';', '"', '=', ' '];

        foreach ($chars as $char) {

            if (strpos($value, $char) !== false) {

                throw new Exception('invalid LDAP text provided', $this->http_code);
            }
        }
    }

    /**
     * ID must be integer in a range.
     *
     * @param int|string $value
     * @throws Exception
     */
    public function id($value): void {

        $this->num($value);
        $this->intRange($value, 1, pow(2, 32));
    }

    /**
     * Integer is in range.
     *
     * @param int|string $value
     * @param int $min
     * @param int $max
     * @throws Exception
     */
    public function intRange($value, int $min, int $max): void {

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

            throw new Exception('provided number is out of valid range', $this->http_code);
        }
    }
}
