<?php
if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', dirname(__DIR__));
}
if (!defined('REFACTORED_LOG_DIR')) {
    define('REFACTORED_LOG_DIR', REFACTORED_LEGACY_ROOT . DIRECTORY_SEPARATOR . 'logs');
}
if (!is_dir(REFACTORED_LOG_DIR)) {
    @mkdir(REFACTORED_LOG_DIR, 0755, true);
}
ini_set('log_errors', '1');
ini_set('display_errors', '0');
ini_set('error_log', REFACTORED_LOG_DIR . DIRECTORY_SEPARATOR . 'php-error.log');


error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);

if (!function_exists('hamoix_dedup_error_log')) {
    function hamoix_dedup_error_log($key, $message, $ttl = 21600) {
        $cacheDir = sys_get_temp_dir() . '/hamoix_log_dedup';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0700, true);
        }
        $cacheFile = $cacheDir . '/' . md5($key);
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            return false;
        }
        @touch($cacheFile);
        error_log($message);
        return true;
    }
}

if (!function_exists('rx_log_event')) {
    function rx_log_event($type, $message, $context = []) {
        $rxStatus = isset($context['status']) ? (string)$context['status'] : '';
        $rxUriRaw = $_SERVER['REQUEST_URI'] ?? '';
        $rxUriTpl = preg_replace('/(username=|order_id=|hash=|user_id=)[^&]+/', '$1*', $rxUriRaw);
        $rxBodyRaw = isset($context['body']) ? (string)$context['body'] : '';
        $rxBodyClean = preg_replace('/"(username|order_id|hash|user_id|custom_username)"\s*:\s*"[^"]*"/', '"$1":"*"', $rxBodyRaw);
        $rxKey = 'rx|' . $type . '|' . $rxStatus . '|' . $rxUriTpl . '|' . md5(substr($rxBodyClean, 0, 200));
        $rxCacheDir = sys_get_temp_dir() . '/hamoix_log_dedup';
        if (!is_dir($rxCacheDir)) {
            @mkdir($rxCacheDir, 0700, true);
        }
        $rxCacheFile = $rxCacheDir . '/' . md5($rxKey);
        if (is_file($rxCacheFile) && (time() - filemtime($rxCacheFile)) < 21600) {
            return;
        }
        @touch($rxCacheFile);

        $line = '[' . date('Y-m-d H:i:s') . '] [' . $type . '] ' . $message;
        $line .= ' | method=' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI');
        $line .= ' | uri=' . ($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'CLI'));
        $line .= ' | script=' . ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown');
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $line .= ' | ' . $key . '=' . str_replace(["\r", "\n"], ' ', (string)$value);
            }
        }
        @file_put_contents(REFACTORED_LOG_DIR . DIRECTORY_SEPARATOR . 'runtime.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

set_error_handler(function($severity, $message, $file, $line) {


    static $rx_suppressed_severities = null;
    if ($rx_suppressed_severities === null) {
        $rx_suppressed_severities = [
            E_WARNING, E_USER_WARNING,
            E_NOTICE, E_USER_NOTICE,
            E_DEPRECATED, E_USER_DEPRECATED,
            E_STRICT,
        ];
    }
    if (in_array($severity, $rx_suppressed_severities, true)) {
        return true;
    }
    if (!(error_reporting() & $severity)) {
        return false;
    }
    rx_log_event('PHP_ERROR', $message, ['severity' => $severity, 'file' => $file, 'line' => $line]);
    return false;
});

set_exception_handler(function($e) {
    rx_log_event('UNCAUGHT_THROWABLE', $e->getMessage(), [
        'class' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Internal error. Check logs/runtime.log\n";
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        rx_log_event('FATAL_SHUTDOWN', $err['message'], [
            'severity' => $err['type'],
            'file' => $err['file'],
            'line' => $err['line'],
        ]);
        if (!headers_sent()) {
            http_response_code(500);
        }
    }
    $status = function_exists('http_response_code') ? http_response_code() : null;
    if ((int)$status >= 500) {
        rx_log_event('HTTP_5XX', 'Request finished with server error status', ['status' => $status]);
    }
});


if (!defined('RX_DEBUG_TRACE_ACTIVE')) {
    define('RX_DEBUG_TRACE_ACTIVE', true);

    $GLOBALS['__rx_debug_raw_body'] = @file_get_contents('php://input');

    register_shutdown_function(function () {
        $status = function_exists('http_response_code') ? (int) http_response_code() : 0;
        $fatal  = error_get_last();
        $hasFatal = is_array($fatal) && in_array(
            $fatal['type'] ?? 0,
            [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR],
            true
        );

        if ($status < 400 && !$hasFatal) {
            return;
        }

        if ($status === 409 || $status === 422) {
            return;
        }

        $body = $GLOBALS['__rx_debug_raw_body'] ?? '';
        $bodyTrunc = is_string($body) ? substr($body, 0, 2000) : '';
        rx_log_event('REQUEST_FAILED', 'request did not return 2xx/3xx', [
            'status'    => $status,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '-',
            'tg_secret' => isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) ? 'present' : '-',
            'body_len'  => is_string($body) ? strlen($body) : 0,
            'body'      => str_replace(["\r", "\n"], ' ', $bodyTrunc),
        ]);
    });
}

