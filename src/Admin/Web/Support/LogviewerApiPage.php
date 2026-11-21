<?php

namespace Faoxima\Admin\Web\Support;

final class LogviewerApiPage
{
    public static function lv_json(array $data, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }
    $out = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    echo $out === false ? '{"ok":false,"code":"encode"}' : $out;
    exit;
}

    public static function lv_fail(string $code, int $status): void
{
    self::lv_json(['ok' => false, 'code' => $code], $status);
}
}
