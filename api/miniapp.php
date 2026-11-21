<?php

declare(strict_types=1);

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

if (function_exists('opcache_invalidate') && !ini_get('opcache.validate_timestamps')) {
    foreach ([__DIR__, dirname(__DIR__) . '/src/Api/Handlers', dirname(__DIR__) . '/src/Api/Runtime'] as $__dir) {
        foreach (glob($__dir . '/*.php') ?: [] as $__file) {
            @opcache_invalidate($__file, true);
        }
    }
    $__root = dirname(__DIR__);
    @opcache_invalidate($__root . '/src/bootstrap.php', false);
    unset($__dir, $__file, $__root);
}

ob_start();

@ini_set('display_errors',         '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors',             '1');
error_reporting(E_ALL);

$GLOBALS['__miniapp_response_sent'] = false;



register_shutdown_function(static function () {
    if (!empty($GLOBALS['__miniapp_response_sent'])) {
        return;
    }
    $err = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (is_array($err) && in_array($err['type'], $fatal, true)) {
        @error_log('[miniapp] fatal: ' . $err['message'] . ' @ ' . (string)$err['file'] . ':' . (int)$err['line']);
        \Faoxima\Api\Runtime\MiniApp::__miniapp_emit(500, [
            'status' => false,
            'msg'    => 'Internal server error',
            'obj'    => [],
        ]);
        return;
    }
    \Faoxima\Api\Runtime\MiniApp::__miniapp_emit(500, [
        'status' => false,
        'msg'    => 'miniapp.php finished without emitting a response',
        'obj'    => [],
    ]);
});







try {
    require_once __DIR__ . '/../src/Api/Runtime/bootstrap.php';

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    ob_start();

    \Faoxima\Api\Runtime\MiniApp::dispatch(
        \Faoxima\Api\Runtime\Input::payload(),
        [\Faoxima\Api\Runtime\MiniApp::class, 'rx_miniapp_user'],
    );
} catch (Throwable $e) {
    if (class_exists(\Faoxima\Api\Runtime\Logger::class)) {
        try {
            \Faoxima\Api\Runtime\Logger::exception($e, 'miniapp.php top-level exception');
        } catch (Throwable $_) {  }
    }
    @error_log('[miniapp] exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    \Faoxima\Api\Runtime\MiniApp::__miniapp_emit(500, [
        'status' => false,
        'msg'    => 'Internal server error',
        'obj'    => [],
    ]);
}
