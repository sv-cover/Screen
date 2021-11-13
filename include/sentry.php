<?php

function sentry_get_client() {
    static $client = null;

    if ($client === null && defined('SENTRY_URL'))
        $client = SENTRY_URL ? new Raven_Client(SENTRY_URL) : false;

    return $client ? $client : null;
}

function sentry_report_exception($e, $attributes = []) {
    $client = sentry_get_client();

    if ($client === null)
        return null;

    return $client->captureException($e, $attributes);
}

function init_sentry() {
    $client = sentry_get_client();

    if (!$client)
        return;

    $error_handler = new Raven_ErrorHandler($client);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();
}
