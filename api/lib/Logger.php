<?php


declare(strict_types=1);

if (class_exists('FaoximaLogger')) {
    return;
}

final class FaoximaLogger
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARN = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';


    private const LEVEL_RANK = [
        'debug'    => 10,
        'info'     => 20,
        'warning'  => 30,
        'error'    => 40,
        'critical' => 50,
    ];


    private static $logDir;


    private static $initialised = false;


    private static $minLevel = 'info';


    public static function init(?string $logDir = null): void
    {
        if (self::$initialised && $logDir === null) {
            return;
        }

        $base = $logDir ?: dirname(__DIR__, 2) . '/logs';
        $base = rtrim($base, "/\\");

        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }

        self::$logDir = $base;

        $envLevel = getenv('FAOXIMA_LOG_LEVEL');
        if (is_string($envLevel) && $envLevel !== '') {
            $envLevel = strtolower(trim($envLevel));
            if (isset(self::LEVEL_RANK[$envLevel])) {
                self::$minLevel = $envLevel;
            }
        } elseif (defined('FAOXIMA_LOG_LEVEL')) {
            $constLevel = strtolower((string) constant('FAOXIMA_LOG_LEVEL'));
            if (isset(self::LEVEL_RANK[$constLevel])) {
                self::$minLevel = $constLevel;
            }
        }

        self::$initialised = true;
    }


    public static function setMinLevel(string $level): void
    {
        $level = strtolower(trim($level));
        if (isset(self::LEVEL_RANK[$level])) {
            self::$minLevel = $level;
        }
    }


    public static function log(string $level, string $message, array $context = []): void
    {
        if (!self::$initialised) {
            self::init();
        }


        $rank = self::LEVEL_RANK[$level] ?? self::LEVEL_RANK['info'];
        $minRank = self::LEVEL_RANK[self::$minLevel] ?? self::LEVEL_RANK['info'];
        if ($rank < $minRank) {
            return;
        }

        $entry = [
            'ts' => date('Y-m-d H:i:s'),
            'level' => $level,
            'msg' => $message,
            'ip' => self::clientIp(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ];

        if (!empty($context)) {

            $entry['ctx'] = self::sanitiseContext($context);
        }

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = '{"ts":"' . date('Y-m-d H:i:s') . '","level":"' . $level . '","msg":"<unencodable log entry>"}';
        }

        $file = self::$logDir . '/api-' . date('Y-m-d') . '.log';
        $written = @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) {

            error_log('[FaoximaLogger fallback] ' . $line);
        }
    }

    public static function debug(string $msg, array $ctx = []): void { self::log(self::DEBUG, $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void { self::log(self::INFO, $msg, $ctx); }
    public static function warn(string $msg, array $ctx = []): void { self::log(self::WARN, $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::log(self::ERROR, $msg, $ctx); }
    public static function critical(string $msg, array $ctx = []): void { self::log(self::CRITICAL, $msg, $ctx); }


    public static function userFacing(string $msg, array $ctx = []): void { self::log(self::DEBUG, $msg, $ctx); }


    public static function exception(Throwable $e, string $note = '', array $ctx = []): void
    {
        $ctx['exception'] = get_class($e);
        $ctx['file'] = $e->getFile();
        $ctx['line'] = $e->getLine();
        $ctx['trace'] = self::shortTrace($e);
        self::log(self::ERROR, $note !== '' ? $note . ': ' . $e->getMessage() : $e->getMessage(), $ctx);
    }

    private static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    private static function shortTrace(Throwable $e): string
    {
        $frames = $e->getTrace();
        $out = [];
        foreach (array_slice($frames, 0, 6) as $i => $frame) {
            $where = ($frame['file'] ?? '?') . ':' . ($frame['line'] ?? '?');
            $what  = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
            $out[] = "#{$i} {$where} {$what}";
        }
        return implode(' | ', $out);
    }

    private static function sanitiseContext(array $ctx): array
    {
        $blocked = ['password', 'passwd', 'token', 'apikey', 'api_key', 'secret', 'authorization'];
        foreach ($ctx as $k => $v) {
            if (in_array(strtolower((string)$k), $blocked, true)) {
                $ctx[$k] = '***';
            } elseif (is_array($v)) {
                $ctx[$k] = self::sanitiseContext($v);
            } elseif (is_string($v) && strlen($v) > 2000) {
                $ctx[$k] = substr($v, 0, 2000) . '…(truncated)';
            }
        }
        return $ctx;
    }
}

FaoximaLogger::init();

