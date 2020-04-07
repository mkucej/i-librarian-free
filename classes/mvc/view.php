<?php

namespace Librarian\Mvc;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Http\Client\Psr7\Response;
use Librarian\Http\Client\Psr7\ServerRequest;

/**
 * Base View class.
 */
abstract class View {

    /**
     * @var DependencyInjector
     */
    protected $di;

    /**
     * @var ServerRequest
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var array
     */
    protected $cache_settings;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    function __construct(DependencyInjector $di) {

        $this->di       = $di;
        $this->request  = $this->di->getShared('ServerRequest');
        $this->response = $this->di->getShared('Response');

        // Default cache settings.
        $this->cache_settings = [
            'max-age'  => 3600,
            'no-cache' => true,
            'no-store' => false
        ];
    }

    /**
     * Fetch headers from the response object and send them to the client.
     *
     * @throws Exception
     */
    protected function sendHeaders(): void {

        $file = '';
        $line = '';

        // Check that no headers were sent before.
        if (headers_sent($file, $line) === true) {

            throw new Exception("Headers were already sent in $file on line $line", 500);
        }

        foreach ($this->response->getHeaders() as $name => $header_lines) {

            foreach ($header_lines as $header) {

                header("$name: $header");
            }
        }
    }

    /**
     * Add Cache-Control header settings.
     *
     * @param array $options Default is ['max-age' => 3600, 'no-cache' => true, 'no-store' => false]
     */
    protected function cacheSettings(array $options): void {

        $this->cache_settings = array_merge($this->cache_settings, $options);
    }

    /**
     * Add Cache-Control header to the response.
     *
     * @return void
     */
    protected function setCacheControl(): void {

        if ($this->cache_settings['no-store'] === true) {

            // Storing in cache is completely disabled.
            $cache_control = 'no-store';

        } elseif ($this->cache_settings['no-cache'] === true) {

            // Storing in cache is allowed, but client must always re-validate before using cache.
            $cache_control = 'no-cache, private';

        } else {

            // Using cache is allowed, client should re-validate after max-age seconds.
            $cache_control = 'private, max-age=' . intval($this->cache_settings['max-age']);
        }

        $this->response = $this->response->withHeader('Cache-Control', $cache_control);
    }
}
