<?php

namespace Librarian\Http;

use GuzzleHttp\Psr7\ServerRequest as Request;

/**
 * URL related methods.
 */
final class Url {

    /**
     * @var Request
     */
    private $request;

    /**
     * @var array Server super globals.
     */
    private  $server;

    public function __construct(Request $request) {

        $this->request = $request;
        $this->server  = $this->request->getServerParams();
    }

    /**
     * Get base URL (where the index.php bootstrap file is).
     *
     * @return string
     */
    public function base(): string {

        // Construct whole URL from globals using Guzzle.
        $url = Request::getUriFromGlobals();

        // Get base path (ends with trailing slash).
        $path = $url->getPath();
        $base_path = strpos($path, 'index.php') === false ? $path : strstr($path, 'index.php', true);

        // Add port.
        $port = $url->getPort();
        $port = $port === '80' || $port === '443' || empty($port) ? '' : ":{$port}";

        // Add scheme. Check for the scheme that client sent to a reverse proxy.
        $scheme = !empty($this->server['HTTP_X_FORWARDED_PROTO']) ? strtolower($this->server['HTTP_X_FORWARDED_PROTO']) : $url->getScheme();

        return "{$scheme}://{$url->getHost()}{$port}{$base_path}";
    }

    /**
     * Get the URL path (after index.php). No leading slash.
     *
     * @return string
     */
    public function path(): string {

        return isset($this->server['PATH_INFO']) ? substr($this->server['PATH_INFO'], 1) : '';
    }
}
