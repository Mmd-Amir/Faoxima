<?php

namespace Faoxima\Payments\Gateways\Variza;

final class Callbacks
{
    public static function variza_webhook_respond(int $code, string $msg): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

    public static function variza_webhook_log(string $type, string $message, array $context = []): void
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    error_log('[variza] ' . $type . ': ' . $message);
}
}
