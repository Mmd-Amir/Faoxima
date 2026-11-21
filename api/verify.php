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
        @error_log('[verify] fatal: ' . $err['message'] . ' @ ' . (string)$err['file'] . ':' . (int)$err['line']);
        \Faoxima\Api\Runtime\EndpointSupport::__verify_emit(500, [
            'status' => false,
            'msg'    => 'Internal server error',
            'token'  => null,
        ]);
        return;
    }
    \Faoxima\Api\Runtime\EndpointSupport::__verify_emit(500, [
        'status' => false,
        'msg'    => 'verify.php finished without emitting a response',
        'token'  => null,
    ]);
});

try {
    require_once __DIR__ . '/../src/Api/Runtime/bootstrap.php';

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    ob_start();

    \Faoxima\Api\Handlers\VerifyHandler::run();

    \Faoxima\Api\Runtime\EndpointSupport::__verify_emit(500, [
        'status' => false,
        'msg'    => 'VerifyHandler::run returned without responding',
        'token'  => null,
    ]);
} catch (Throwable $e) {
    @error_log('[verify] exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    \Faoxima\Api\Runtime\EndpointSupport::__verify_emit(500, [
        'status' => false,
        'msg'    => 'Internal server error',
        'token'  => null,
    ]);
}
