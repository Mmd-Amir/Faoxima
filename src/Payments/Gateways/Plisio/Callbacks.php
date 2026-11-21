<?php

namespace Faoxima\Payments\Gateways\Plisio;

final class Callbacks
{
    public static function rxPlisio_log($event, $msg, $ctx = []) {
    if (function_exists('rx_log_event')) {
        rx_log_event($event, $msg, $ctx);
    } else {
        error_log('[plisio-cb] ' . $event . ': ' . $msg . ' ' . json_encode($ctx));
    }
}

    public static function rxPlisio_readBody(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    if (!empty($_POST)) return $_POST;
    if (!empty($_GET))  return $_GET;
    return [];
}
}
