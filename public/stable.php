<?php

/*
 * Stable link redirects to the new URL.
 */

$uri = str_replace('stable.php', 'index.php/item#summary', $_SERVER['REQUEST_URI']);

http_response_code(307);
header("Location: $_SERVER[REQUEST_SCHEME]://$_SERVER[HTTP_HOST]$uri");
