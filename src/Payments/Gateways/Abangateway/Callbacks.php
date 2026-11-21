<?php

namespace Faoxima\Payments\Gateways\Abangateway;

final class Callbacks
{
    public static function abangateway_callback_log(string $type, string $message, array $context = []): void
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    error_log('[abangateway] ' . $type . ': ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
}
}
