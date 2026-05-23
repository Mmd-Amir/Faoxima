<?php


declare(strict_types=1);

if (class_exists('FaoximaResponse')) {
    return;
}

final class FaoximaResponse
{
    public static function ok($obj = null, string $msg = 'Successful', ?array $meta = null): void
    {
        self::send(200, true, $msg, $obj, $meta);
    }

    public static function fail(int $statusCode, string $msg, $obj = null): void
    {
        self::send($statusCode, false, $msg, $obj, null);
    }

    public static function badRequest(string $msg = 'Bad request', $obj = null): void
    {
        self::send(400, false, $msg, $obj, null);
    }

    public static function unauthorized(string $msg = 'Authorization header missing'): void
    {
        self::send(401, false, $msg, null, null);
    }

    public static function forbidden(string $msg = 'Forbidden'): void
    {
        self::send(403, false, $msg, null, null);
    }

    public static function notFound(string $msg = 'Not found'): void
    {
        self::send(404, false, $msg, null, null);
    }

    public static function methodNotAllowed(string $expected): void
    {
        self::send(405, false, "Method invalid; must be {$expected}", null, null);
    }

    public static function serverError(string $msg = 'Internal server error'): void
    {
        self::send(500, false, $msg, null, null);
    }

    private static function send(int $statusCode, bool $status, string $msg, $obj, ?array $meta): void
    {
        $payload = [
            'status' => $status,
            'msg'    => $msg,
        ];
        if ($obj !== null) {
            $payload['obj'] = $obj;
        } elseif ($status === false) {
            $payload['obj'] = [];
        }
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        if (function_exists('__miniapp_emit')) {
            __miniapp_emit($statusCode, $payload);
            exit;
        }
        if (function_exists('__verify_emit')) {
            __verify_emit($statusCode, $payload);
            exit;
        }


        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

