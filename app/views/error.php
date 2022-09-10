<?php

namespace LibrarianApp;

use ErrorException;
use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
use Librarian\Media\ScalarUtils;
use Librarian\Mvc\TextView;
use PDOException;
use Throwable;

/**
 * A view to generate error report to the client.
 */
final class ErrorView extends TextView {

    /**
     * @var array Error severity constant translation array.
     */
    private $severity = [
        1     => 'E_ERROR',
        2     => 'E_WARNING',
        4     => 'E_PARSE',
        8     => 'E_NOTICE',
        16    => 'E_CORE_ERROR',
        32    => 'E_CORE_WARNING',
        64    => 'E_COMPILE_ERROR',
        128   => 'E_COMPILE_WARNING',
        256   => 'E_USER_ERROR',
        512   => 'E_USER_WARNING',
        1024  => 'E_USER_NOTICE',
        2048  => 'E_STRICT',
        4096  => 'E_RECOVERABLE_ERROR',
        8192  => 'E_DEPRECATED',
        16384 => 'E_USER_DEPRECATED',
        30719 => 'E_ALL'
    ];

    /**
     * Generate error report in HTML, or JSON.
     *
     * @param  Throwable|ErrorException|PDOException $exc
     * @return string
     * @throws Exception
     */
    public function main(Throwable $exc): string {

        // No store!
        $this->cacheSettings(['no-store' => true]);

        // Reporting level.
        $level = $this->app_settings->getIni('error_messages', 'level');

        /*
         * Status code.
         */
        $code = (int) $exc->getCode() === 0 ? 500 : $exc->getCode();
        $code = $code < 400 ? 500 : $code;

        $this->response = $this->response->withStatus($code);

        /*
         * Message.
         */

        // I18n.
        $message = $this->lang->t9n($exc->getMessage());

        // Remove Guzzle backticks from error messages.
        $message = str_replace("`", "'", $message);

        if (get_class($exc) === 'PDOException') {

            $message = 'Database error: ' . $message;

        } elseif  (get_class($exc) === 'ErrorException') {

            $severity = $this->severity[$exc->getSeverity()] ?? 'ERROR';
            $message = "{$severity}: $message";

        } else {

            $message = ucfirst($message);
        }

        // Add details.
        if ($level === 'debug') {

            $message .= " in {$exc->getFile()} on line {$exc->getLine()}";
        }

        // Check, if the request is AJAX.
        if ($this->contentType() === 'json') {

            // HTML unauthenticated.
            if ($this->response->getStatusCode() === 401) {

                $this->session->start();
                $this->session->destroy();
            }

            $this->append([
                'error' => $message
            ]);

            // Debug level.
            if ($level === 'debug') {

                $this->session->start();

                $this->append([
                    'server'  => $this->request->getServerParams(),
                    'files'   => $this->request->getUploadedFiles(),
                    'get'     => $this->request->getQueryParams(),
                    'post'    => $this->request->getParsedBody(),
                    'session' => $this->session->data(),
                    'cookie'  => $this->request->getCookieParams(),
                    'php'     => ini_get_all(null, false)
                ]);
            }

        } elseif ($this->contentType() === 'html') {

            if ($level === 'debug') {

                $this->session->start();

                /** @var ScalarUtils $scalar_utils */
                $scalar_utils = $this->di->getShared('ScalarUtils');

                $message .= $scalar_utils->arrayToTable(['GET'     => $this->request->getQueryParams()]);
                $message .= $scalar_utils->arrayToTable(['POST'    => $this->request->getParsedBody()]);
                $message .= $scalar_utils->arrayToTable(['FILE'    => $this->request->getUploadedFiles()]);
                $message .= $scalar_utils->arrayToTable(['SESSION' => $this->session->data()]);
                $message .= $scalar_utils->arrayToTable(['COOKIE'  => $this->request->getCookieParams()]);
                $message .= $scalar_utils->arrayToTable(['SERVER'  => $this->request->getServerParams()]);
                $message .= $scalar_utils->arrayToTable(['PHP'     => ini_get_all(null, false)]);
            }

            $this->title("Error {$code}");

            $this->styleLink('css/plugins.css');

            $this->head();

            /** @var Bootstrap\Icon $el */
            $el = $this->di->get('Icon');

            $el->icon('alert-octagram');
            $el->addClass('text-danger');
            $alert_icon = $el->render();

            $el = null;

            // Different CSS alignment depending on debug level.
            $center_class = $level === 'debug' ? '' : 'align-items-center';

            /** @var Bootstrap\Row $el */
            $el = $this->di->get('Row');

            $el->addClass("h-100 text-center $center_class pt-2");
            $el->column(
<<<I18N
<h1>{$alert_icon}{$this->lang->t9n('Oops')}!</h1>$message
I18N
            , 'col col-xl-8 offset-xl-2 pb-5');
            $row = $el->render();

            $el = null;

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->addClass('container-fluid h-100');
            $el->html($row);
            $container = $el->render();

            $el = null;

            $this->append($container);

            /*
             * End.
             */

            $this->scriptLink('js/plugins.min.js');

            // HTML unauthenticated.
            if ($this->response->getStatusCode() === 401) {

                $this->session->start();
                $this->session->destroy();

                $IL_BASE_URL = IL_BASE_URL;

                $this->script(<<<SCRIPT
location.replace('{$IL_BASE_URL}?ref=' + window.btoa(location.href));
SCRIPT
                );
            }

            $this->end();
        }

        return $this->send();
    }
}
