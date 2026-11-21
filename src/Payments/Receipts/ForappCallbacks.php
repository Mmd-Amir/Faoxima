<?php

namespace Faoxima\Payments\Receipts;

final class ForappCallbacks
{
    public static function forapp_respond(int $code, array $body): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($code === 503) {
        header('Retry-After: 30');
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
}
