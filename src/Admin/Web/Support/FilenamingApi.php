<?php

namespace Faoxima\Admin\Web\Support;

final class FilenamingApi
{
    public static function fn_json(array $data, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }
    $out = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    echo $out === false ? '{"ok":false,"error":"encode"}' : $out;
    exit;
}

    public static function fn_fail(string $error, int $status = 400, array $extra = []): void
{
    self::fn_json(array_merge(['ok' => false, 'error' => $error], $extra), $status);
}

    public static function fn_post_string($key, $default = '')
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}
}
