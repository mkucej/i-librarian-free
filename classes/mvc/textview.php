<?php

namespace Librarian\Mvc;

use Exception;
use Librarian\AppSettings;
use Librarian\Container\DependencyInjector;
use Librarian\Html\Bootstrap\Badge;
use Librarian\Html\Element;
use Librarian\Http\Client\Psr7;
use Librarian\Http\Client\Psr7\Stream;
use Librarian\Media\ScalarUtils;
use Librarian\Security\Sanitation;
use Librarian\Security\Session;
use Librarian\Security\Validation;

/**
 * Base View class.
 */
abstract class TextView extends View {

    /**
     * @var AppSettings
     */
    protected $app_settings;

    /**
     * @var Sanitation
     */
    protected $sanitation;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Stream
     */
    protected $stream;

    /**
     * @var ScalarUtils
     */
    protected $scalar_utils;

    /**
     * @var string Permission level A|U|G
     */
    public $permissions;

    /**
     * @var Validation
     */
    protected $validation;

    protected $content_type;
    protected $head_links;
    protected $script;
    protected $script_links;
    protected $style;
    protected $title;

    /**
     * @var string Dark or light theme.
     */
    public static $theme;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->app_settings = $this->di->getShared('AppSettings');
        $this->sanitation   = $this->di->getShared('Sanitation');
        $this->session      = $this->di->getShared('Session');
        $this->stream       = $this->di->getShared('ResponseStream');
        $this->scalar_utils = $this->di->getShared('ScalarUtils');
        $this->validation   = $this->di->getShared('Validation');

        // Set default content type.
        $this->defaultType();

        // Theme. Get setting, if logged in.
        if ($this->session->data('user_id') !== null) {

            self::$theme = $this->app_settings->getUser('theme');
        }

        // Title.
        $this->title = 'I, Librarian';
    }

    /**
     * Set/get response content type.
     *
     * @param string $type
     * @return string
     */
    public function contentType(string $type = null): string {

        if (isset($type)) {

            $this->content_type = $type;

            switch ($this->content_type) {

                case 'html':
                    $this->response = $this->response->withHeader('Content-Type', 'text/html');
                    break;

                case 'json':
                    $this->response = $this->response->withHeader('Content-Type', 'application/json');
                    break;

                case 'xml':
                    $this->response = $this->response->withHeader('Content-Type', 'text/xml');
                    break;

                case 'text':
                    $this->response = $this->response->withHeader('Content-Type', 'text/plain');
                    break;

                case 'event-stream':
                    $this->response = $this->response->withHeader('Content-Type', 'text/event-stream');
                    break;

                default:
                    $this->response = $this->response->withHeader('Content-Type', $type);
                    break;
            }
        }

        return $this->content_type;
    }

    /**
     * Add a head link.
     *
     * @param string $link
     * @param array $attributes
     * @return void
     * @throws Exception
     */
    protected function headLink(string $link, array $attributes = []): void {

        // Link may be absolute or relative.
        $add_link = parse_url($link, PHP_URL_SCHEME) === null ? IL_BASE_URL . $link . '?v=' . IL_VERSION : $link;

        /** @var Element $el */
        $el = $this->di->get('Element');

        $el->elementName('link');
        $el->href($add_link);

        foreach ($attributes as $name => $value) {

            $el->attr($name, $value);
        }

        $this->head_links .= $el->render() . PHP_EOL;

        $el = null;
    }

    /**
     * Add a style head link.
     *
     * @param string $link
     * @param array $attributes
     * @return void
     * @throws Exception
     */
    protected function styleLink(string $link, array $attributes = []): void {

        $attr = array_merge($attributes, ['rel' => 'stylesheet']);

        $this->headLink($link, $attr);
    }

    /**
     * Add head to the response stream.
     *
     * @return void
     * @throws Exception
     */
    protected function head(): void {

        if ($this->contentType() === 'json' && !empty($this->title)) {

            // JSON. Only add the document title, if applicable.
            $this->append(['title' => $this->title]);

        } else {

            $theme_class = self::$theme === 'dark' ? 'content-dark text-white' : 'content-light';

            // HTML. Construct <head> and open <body>.
            $IL_BASE_URL = IL_BASE_URL;
            $IL_VERSION = IL_VERSION;

            $this->append(<<<EOT
                <!DOCTYPE HTML>
                <html lang="en">
                    <head>
                        <title>{$this->title}</title>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta http-equiv="X-UA-Compatible" content="IE=edge">
                        <link rel="apple-touch-icon" sizes="180x180" href="{$IL_BASE_URL}img/apple-touch-icon.png?v=$IL_VERSION">
                        <link rel="icon" type="image/png" sizes="32x32" href="{$IL_BASE_URL}img/favicon-32x32.png?v=$IL_VERSION">
                        <link rel="icon" type="image/png" sizes="16x16" href="{$IL_BASE_URL}img/favicon-16x16.png?v=$IL_VERSION">
                        <link rel="manifest" href="{$IL_BASE_URL}img/manifest.json?v=$IL_VERSION">
                        <link rel="mask-icon" href="{$IL_BASE_URL}img/safari-pinned-tab.svg?v=$IL_VERSION" color="#2f8ded">
                        <link rel="shortcut icon" href="{$IL_BASE_URL}img/favicon.ico?v=$IL_VERSION">
                        <meta name="msapplication-config" content="{$IL_BASE_URL}img/browserconfig.xml?v=$IL_VERSION">
                        <meta name="theme-color" content="#333333">
                        {$this->head_links}
                        <link href="{$IL_BASE_URL}css/style.css?v=$IL_VERSION" rel="stylesheet">
                        <style>{$this->style}</style>
                    </head>
                    <body class="$theme_class">
EOT
            );

            // Debug alert.
            $debug_level = $this->app_settings->getIni('error_messages', 'level');

            if ($debug_level === 'debug') {

                /** @var Badge $el */
                $el = $this->di->get('Badge');

                $el->context('danger');
                $el->addClass('position-fixed m-3');
                $el->style('z-index: 1000;left: calc(50% - 3.5rem);top: calc(50% - 1.5rem);opacity: 0.33');
                $el->html('DEBUG ON!');
                $badge = $el->render();

                $el = null;

                $this->append($badge);
            }
        }
    }

    /**
     * Append to the response stream.
     *
     * @param string|array $input
     * @return void
     * @throws Exception
     */
    protected function append($input): void {

        // If response stream is empty, call $this->write() and return.
        if ($this->stream->getSize() === 0) {

            $this->write($input);
            return;
        }

        if ($this->contentType() === 'json') {

            // JSON must be formatted: array -> JSON.

            if (empty($input)) {

                $input = [];

            } elseif (!is_array($input)) {

                throw new Exception('array required for JSON response', 500);
            }

            // Get the whole stream contents and decode to array.
            $this->stream->rewind();

            $stream_arr = \Librarian\Http\Client\json_decode($this->stream, true);

            if (is_array($stream_arr) === false) {

                throw new Exception('could not convert JSON stream to array', 500);
            }

            // Merge the existing array with new input array.
            $stream_arr = array_merge($stream_arr, $input);

            $input = \Librarian\Http\Client\json_encode($stream_arr);

            // JSON overwrites the whole stream.
            $this->stream->rewind();

        } else {

            // Go to the end of stream.
            $this->stream->seek(0, SEEK_END);
        }

        $this->stream->write($input);
    }

    /**
     * Overwrite the whole response stream.
     *
     * @param string $input
     * @return void
     * @throws Exception
     */
    protected function write($input): void {

        // JSON must be formatted array->JSON.
        if ($this->contentType() === 'json') {

            if (empty($input)) {

                $input = [];

            } elseif (!is_array($input)) {

                throw new Exception('array required for JSON response', 500);
            }

            $input = \Librarian\Http\Client\json_encode($input);
        }

        $this->stream->rewind();
        $this->stream->write($input);
    }

    /**
     * Add a script link.
     *
     * @param string $link
     * @param array $attributes
     * @return void
     * @throws Exception
     */
    protected function scriptLink(string $link, array $attributes = []): void {

        // Link may be absolute or relative.
        $add_link = parse_url($link, PHP_URL_SCHEME) === null ? IL_BASE_URL . $link . '?v=' . IL_VERSION : $link;

        /** @var Element $el */
        $el = $this->di->get('Element');

        $el->elementName('script');
        $el->src($add_link);

        foreach ($attributes as $name => $value) {

            $el->attr($name, $value);
        }

        $this->script_links .= $el->render() . PHP_EOL;
    }

    /**
     * Append inline script.
     *
     * @param string $script
     * @return void
     */
    protected function script(string $script): void {

        $this->script .= PHP_EOL . $script;
    }

    /**
     * Add the end of HTML to the response stream.
     *
     * @return void
     * @throws Exception
     */
    protected function end(): void {

        // CSRF. Put the token in <script>.
        if ($this->session->data('token') !== null) {

            $IL_BASE_URL = IL_BASE_URL;
            $MAX_UPLOAD  = $this->scalar_utils->unformatBytes(ini_get('upload_max_filesize'));
            $MAX_POST    = $this->scalar_utils->unformatBytes(ini_get('post_max_size'));

            $csrf_script = <<<EOT
                var IL_BASE_URL = '{$IL_BASE_URL}',
                    MAX_UPLOAD = {$MAX_UPLOAD},
                    MAX_POST = {$MAX_POST},
                    csrfToken='{$this->session->data('token')}';
                $.ajaxPrefilter(function (s, o, x) {
                    if (s.type.toLowerCase() === 'post' && typeof csrfToken === 'string') {
                        if (typeof s.data === 'undefined') {
                            s.data = 'csrfToken=' + csrfToken;
                        } else if (typeof s.data === 'string' && !/csrfToken\=/.test(s.data)) {
                            s.data += '&csrfToken=' + csrfToken;
                        }
                    }
                });
                window.MathJax = {
                    tex: {
                        inlineMath: [['$', '$']]
                    }
                };
EOT;
            $this->script = $csrf_script . $this->script;
        }

        $IL_BASE_URL = IL_BASE_URL;
        $IL_VERSION = IL_VERSION;

        // Add external and internal scripts, close <body> and <html>.
        if ($this->app_settings->getGlobal('math_formatting') === '1') {

            $this->scriptLink('https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js', ['defer' => 'defer']);
        }

        $this->stream->write(<<<EOT
                    {$this->script_links}
                    <script src="{$IL_BASE_URL}js/script.min.js?v=$IL_VERSION"></script>
                    <script>
                        {$this->script}
                    </script>
                </body>
            </html>
EOT
        );
    }

    /**
     * Set a default content type based on the client request.
     *
     * @return void
     */
    private function defaultType(): void {

        // Fallback type.
        $this->contentType('html');

        /*
         * XHR request override.
         */

        $server_params = $this->request->getServerParams();

        if (!empty($server_params['HTTP_X_REQUESTED_WITH'])
            && strtolower($server_params['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

            // Set content type based on the client Accept header.
            $accept_header = empty($server_params['HTTP_ACCEPT']) ? '*/*' : $server_params['HTTP_ACCEPT'];
            $accept_header_arr = explode(',', $accept_header);
            $accept = $accept_header_arr[0];

            switch ($accept) {

                case 'text/html':
                    $this->contentType('html');
                    break;

                case 'application/json':
                    $this->contentType('json');
                    break;

                case 'text/plain':
                    $this->contentType('text');
                    break;
            }
        }

        /*
         * SSE.
         */
        $header = $this->request->getHeader('Accept');

        if (isset($header[0]) && strpos($header[0], 'text/event-stream') !== false) {

            $this->contentType('event-stream');
        }

        /*
         * Non-XHR requests can have an 'as' query parameter to override.
         */

        $getParams = $this->request->getQueryParams();

        if (isset($getParams['as'])) {

            $as = empty($getParams['as']) ? 'html' : $getParams['as'];

            switch ($as) {

                case 'html':
                    $this->contentType('html');
                    break;

                case 'json':
                    $this->contentType('json');
                    break;

                case 'text':
                    $this->contentType('text');
                    break;
            }
        }
    }

    /**
     * Set HTML document title.
     *
     * @param string $title
     */
    protected function title($title): void {

        $this->title = $title . ' - ' . $this->title;
    }

    /**
     * Add a MD5-based Etag header.
     *
     * Only used for text based views. File view uses a time stamp.
     *
     * @return void
     */
    protected function setEtag(): void {

        // No-store does not use Etag.
        if ($this->cache_settings['no-store'] === false) {

            $etag = Psr7\hash($this->stream, 'md5');
            $this->response = $this->response->withHeader('ETag', $etag);
        }
    }

    /**
     * Set content disposition. Default is inline.
     *
     * @param string $disposition
     * @param string|null $filename
     * @return void
     */
    protected function setDisposition(string $disposition = 'inline', string $filename = null): void {

        $disposition_header = $disposition === 'attachment' ? 'attachment' : 'inline';

        // Get filename.
        $filename = isset($filename) ? rawurlencode($filename) : rawurlencode('file.txt');
        $this->response = $this->response->withHeader('Content-Disposition', "$disposition_header; filename*=UTF-8''$filename");
    }

    /**
     * Send headers and return the response stream.
     *
     * @return string
     * @throws Exception
     */
    protected function send(): string {

        // Add headers.
        $this->setCacheControl();
        $this->setEtag();

        // Send headers.
        $this->sendHeaders();

        // Is response body modified?
        $server_params = $this->request->getServerParams();

        // Send 304, if Etags match.
        if (!empty($server_params['HTTP_IF_NONE_MATCH']) && isset($this->response->getHeader('Etag')[0])
            && strtolower($server_params['HTTP_IF_NONE_MATCH']) === $this->response->getHeader('Etag')[0]) {

            http_response_code(304);
            return '';
        }

        http_response_code($this->response->getStatusCode());
        return $this->stream;
    }

    /**
     * Send stream line. Used for SSE.
     *
     * @param int $size
     * @throws Exception
     */
    protected function sendChunk(int $size) {

        // Send headers. if not sent before.
        if (headers_sent($file, $line) === false) {

            // Add headers.
            $this->setCacheControl();
            $this->setEtag();

            // Send headers.
            $this->sendHeaders();
            http_response_code($this->response->getStatusCode());
        }

        if (ob_get_level() > 0) {

            ob_end_clean();
        }

        $this->stream->seek(-$size, SEEK_END);

        echo $this->stream->read($size);
        flush();
    }
}
