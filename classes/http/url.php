<?php

namespace Librarian\Http;

use Librarian\Http\Client\Psr7\ServerRequest as Request;

/**
 * URL related methods.
 */
final class Url {

    private $request;

    public function __construct(Request $request) {

        $this->request = $request;
    }

    /**
     * Get base URL (where the index.php bootstrap file is).
     *
     * @return string
     */
    public function base() {

        // Construct whole URL from globals using Guzzle.
        $url = Request::getUriFromGlobals();

        // Get base path (ends with trailing slash).
        $path = $url->getPath();
        $base_path = strpos($path, 'index.php') === false ? $path : strstr($path, 'index.php', true);

        // Add port.
        $port = $url->getPort();
        $port = $port === '80' || $port === '443' || empty($port) ? '' : ":{$port}";

        return "{$url->getScheme()}://{$url->getHost()}{$port}{$base_path}";
    }

    /**
     * Get the URL path (after index.php). No leading slash.
     *
     * @return string
     */
    public function path() {

        $server = $this->request->getServerParams();

        return isset($server['PATH_INFO']) ? substr($server['PATH_INFO'], 1) : '';
    }
}
