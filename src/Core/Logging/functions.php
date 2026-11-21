<?php
// Runtime logging entry point for the public entries (index.php, admin.php, keyboard.php, function.php): registers
// RuntimeLog (log constants, PHP error settings, error/exception/shutdown handlers) and keeps the global rx_log_event() helper
// that application code calls. The implementation is in RuntimeLog.php.
if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', dirname(__DIR__, 3));
}
require_once __DIR__ . '/RuntimeLog.php';
\Faoxima\Core\Logging\RuntimeLog::register();

if (!function_exists('rx_log_event')) {
    function rx_log_event($type, $message, $context = []) {
        \Faoxima\Core\Logging\RuntimeLog::event((string) $type, (string) $message, (array) $context);
    }
}
