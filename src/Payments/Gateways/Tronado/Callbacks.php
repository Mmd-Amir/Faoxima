<?php

namespace Faoxima\Payments\Gateways\Tronado;

final class Callbacks
{
    public static function tronado_log_event($type, $message, array $context = [])
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    $line = $type . ': ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log('[tronado] ' . $line);
}
}
