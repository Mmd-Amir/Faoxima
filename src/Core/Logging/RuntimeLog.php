<?php

declare(strict_types=1);

namespace Faoxima\Core\Logging;

/**
 * Runtime error logging and PHP error/exception/shutdown handling.
 *
 * `src/Core/Logging/functions.php` is the single entry point that registers this at the start of every public entry
 * (index.php, admin.php, keyboard.php, function.php). The log destinations (`logs/php-error.log`, `logs/runtime.log`), the line
 * format, the severity filtering and the 6-hour de-duplication window are unchanged.
 */
final class RuntimeLog
{
    private const DEDUP_TTL = 21600;

    /** Suppressed PHP severities: warnings, notices and deprecations are not logged. */
    private const SUPPRESSED = [E_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT];

    private const FATAL = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    private static bool $registered = false;

    /** Defines the log constants, applies the PHP error settings and installs the three handlers (once per request). */
    public static function register(): void
    {
        if (!defined('REFACTORED_LEGACY_ROOT')) {
            define('REFACTORED_LEGACY_ROOT', dirname(__DIR__, 3));
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

        if (self::$registered) {
            return;
        }
        self::$registered = true;
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /** Writes one de-duplicated line to logs/runtime.log. */
    public static function event(string $type, string $message, array $context = []): void
    {
        $logDir = defined('REFACTORED_LOG_DIR') ? REFACTORED_LOG_DIR : sys_get_temp_dir();
        $status = isset($context['status']) ? (string) $context['status'] : '';
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $uriTemplate = preg_replace('/(username=|order_id=|hash=|user_id=)[^&]+/', '$1*', $uri);
        $body = isset($context['body']) ? (string) $context['body'] : '';
        $bodyClean = preg_replace('/"(username|order_id|hash|user_id|custom_username)"\s*:\s*"[^"]*"/', '"$1":"*"', $body);
        $scope = '';
        foreach (['username', 'panel', 'service_key', 'id_invoice', 'id_order', 'reservation_id', 'op_id', 'operation_id', 'reason'] as $field) {
            if (isset($context[$field]) && is_scalar($context[$field])) {
                $scope .= '|' . $field . '=' . $context[$field];
            }
        }
        $key = 'rx|' . $logDir . '|' . $type . '|' . $status . '|' . $uriTemplate . '|' . md5(substr((string) $bodyClean, 0, 200)) . $scope;
        if (!self::claimWindow($key)) {
            return;
        }

        $line = '[' . date('Y-m-d H:i:s') . '] [' . $type . '] ' . $message;
        $line .= ' | method=' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI');
        $line .= ' | uri=' . ($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'CLI'));
        $line .= ' | script=' . ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown');
        foreach ($context as $name => $value) {
            if (is_scalar($value) || $value === null) {
                $line .= ' | ' . $name . '=' . str_replace(["\r", "\n"], ' ', (string) $value);
            }
        }
        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'runtime.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /** Logs a throwable from a loader with its real file and line (no fragment or source mapping), then the caller rethrows. */
    public static function throwable(string $module, \Throwable $error): void
    {
        self::event('RUNTIME_LOAD_THROWABLE', $error->getMessage(), [
            'module' => $module,
            'class' => get_class($error),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (in_array($severity, self::SUPPRESSED, true)) {
            return true;
        }
        if (!(error_reporting() & $severity)) {
            return false;
        }
        self::event('PHP_ERROR', $message, ['severity' => $severity, 'file' => $file, 'line' => $line]);
        return false;
    }

    public static function handleException(\Throwable $error): void
    {
        self::event('UNCAUGHT_THROWABLE', $error->getMessage(), [
            'class' => get_class($error),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "Internal error. Check logs/runtime.log\n";
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], self::FATAL, true)) {
            self::event('FATAL_SHUTDOWN', $error['message'], [
                'severity' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);
            if (!headers_sent()) {
                http_response_code(500);
            }
        }
        $status = http_response_code();
        if ((int) $status >= 500) {
            self::event('HTTP_5XX', 'Request finished with server error status', ['status' => $status]);
        }
    }

    /** True when this key has not been logged during the last six hours (and records it). */
    private static function claimWindow(string $key): bool
    {
        $directory = sys_get_temp_dir() . '/faoxima_log_dedup';
        if (!is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }
        $marker = $directory . '/' . md5($key);
        if (is_file($marker) && (time() - (int) filemtime($marker)) < self::DEDUP_TTL) {
            return false;
        }
        @touch($marker);
        return true;
    }
}
