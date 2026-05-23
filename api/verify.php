<?php


declare(strict_types=1);


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ob_start();

@ini_set('display_errors',         '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors',             '1');
error_reporting(E_ALL);


$GLOBALS['__verify_response_sent'] = false;

function __verify_emit(int $http, array $payload): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $GLOBALS['__verify_response_sent'] = true;
}

register_shutdown_function(static function () {
    if (!empty($GLOBALS['__verify_response_sent'])) {
        return;
    }
    $err = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (is_array($err) && in_array($err['type'], $fatal, true)) {
        __verify_emit(500, [
            'status' => false,
            'msg'    => 'PHP fatal: ' . $err['message'],
            'detail' => basename((string)$err['file']) . ':' . (int)$err['line'],
            'token'  => null,
        ]);
        return;
    }
    __verify_emit(500, [
        'status' => false,
        'msg'    => 'verify.php finished without emitting a response',
        'token'  => null,
    ]);
});

try {
    require_once __DIR__ . '/lib/Bootstrap.php';
    require_once __DIR__ . '/handlers/VerifyHandler.php';

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    ob_start();

    VerifyHandler::run();

    __verify_emit(500, [
        'status' => false,
        'msg'    => 'VerifyHandler::run returned without responding',
        'token'  => null,
    ]);
} catch (Throwable $e) {
    __verify_emit(500, [
        'status' => false,
        'msg'    => 'verify.php exception: ' . $e->getMessage(),
        'detail' => basename($e->getFile()) . ':' . $e->getLine(),
        'token'  => null,
    ]);
}

