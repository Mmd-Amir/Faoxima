<?php

declare(strict_types=1);

namespace Faoxima\Api\Runtime;

use Throwable;

final class ErrorHandler
{

    private static $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;


        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler([self::class, 'onError']);
        set_exception_handler([self::class, 'onException']);
        register_shutdown_function([self::class, 'onShutdown']);
    }

    public static function onError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {

        if (!(error_reporting() & $errno)) {
            return false;
        }

        $level = \Faoxima\Api\Runtime\Logger::WARN;
        if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            $level = \Faoxima\Api\Runtime\Logger::ERROR;
        }

        \Faoxima\Api\Runtime\Logger::log($level, '[php] ' . $errstr, [
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline,
        ]);


        return false;
    }

    public static function onException(Throwable $e): void
    {
        \Faoxima\Api\Runtime\Logger::exception($e, 'uncaught exception');

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'status' => false,
            'msg' => 'Internal server error',
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function onShutdown(): void
    {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $fatal = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR];
        if (!in_array($err['type'], $fatal, true)) {
            return;
        }

        \Faoxima\Api\Runtime\Logger::log(\Faoxima\Api\Runtime\Logger::CRITICAL, '[fatal] ' . $err['message'], [
            'file' => $err['file'],
            'line' => $err['line'],
            'type' => $err['type'],
        ]);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => false,
                'msg' => 'Internal server error',
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}

\Faoxima\Api\Runtime\ErrorHandler::register();

