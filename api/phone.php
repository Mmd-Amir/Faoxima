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



register_shutdown_function(static function () {
    if (!empty($GLOBALS['__verify_response_sent'])) {
        return;
    }
    $err = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (is_array($err) && in_array($err['type'], $fatal, true)) {
        \Faoxima\Api\Runtime\EndpointSupport::__verify_emit(500, [
            'status' => false,
            'msg'    => 'PHP fatal: ' . $err['message'],
            'token'  => null,
        ]);
        return;
    }
    \Faoxima\Api\Runtime\EndpointSupport::__verify_emit(500, [
        'status' => false,
        'msg'    => 'phone.php finished without emitting a response',
        'token'  => null,
    ]);
});

try {
    require_once __DIR__ . '/../src/Api/Runtime/bootstrap.php';

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    ob_start();

    \Faoxima\Api\Handlers\PhoneVerifyHandler::run();

    \Faoxima\Api\Runtime\EndpointSupport::__verify_emit(500, [
        'status' => false,
        'msg'    => 'PhoneVerifyHandler::run returned without responding',
        'token'  => null,
    ]);
} catch (Throwable $e) {
    \Faoxima\Api\Runtime\EndpointSupport::__verify_emit(500, [
        'status' => false,
        'msg'    => 'phone.php exception: ' . $e->getMessage(),
        'detail' => basename($e->getFile()) . ':' . $e->getLine(),
        'token'  => null,
    ]);
}
