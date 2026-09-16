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

if (!function_exists('faoxima_dedup_error_log')) {
    function faoxima_dedup_error_log($key, $message, $ttl = 21600) {
        $cacheDir = sys_get_temp_dir() . '/faoxima_log_dedup';
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
        $rxCacheDir = sys_get_temp_dir() . '/faoxima_log_dedup';
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

if (!function_exists('rx_strip_php_open')) {
    function rx_strip_php_open($raw)
    {
        $raw = (string) $raw;
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }
        $raw = ltrim($raw);
        if (strncasecmp($raw, '<?php', 5) === 0) {
            $raw = substr($raw, 5);
            $raw = preg_replace('/^[ \t]*\r?\n/', '', $raw, 1);
        } elseif (strncmp($raw, '<?=', 3) !== 0 && strncmp($raw, '<?', 2) === 0) {
            $raw = substr($raw, 2);
            $raw = preg_replace('/^[ \t]*\r?\n/', '', $raw, 1);
        }
        return $raw;
    }
}

if (!function_exists('rx_compile_section')) {
    function rx_compile_section($dir)
    {
        $manifestPath = $dir . DIRECTORY_SEPARATOR . 'manifest.php';
        $parts = require $manifestPath;
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid manifest: ' . $manifestPath);
        }

        $compiled = $dir . DIRECTORY_SEPARATOR . '.compiled.php';
        $mapPath  = $dir . DIRECTORY_SEPARATOR . '.compiled.map';

        $partPaths = [];
        $newest = (int) @filemtime($manifestPath);
        $manifestStat = @stat($manifestPath);
        $sourceMeta = ['manifest' => [$newest, (int) @filesize($manifestPath), (int) @filectime($manifestPath), (int) ($manifestStat['ino'] ?? 0)], 'parts' => []];
        foreach ($parts as $part) {
            $partPath = $dir . DIRECTORY_SEPARATOR . $part;
            if (!is_file($partPath)) {
                rx_log_event('RX_MISSING_PART', $partPath, ['module' => basename($dir)]);
                throw new RuntimeException('Missing refactored part: ' . $partPath);
            }
            $partPaths[$part] = $partPath;
            $mtime = (int) @filemtime($partPath);
            if ($mtime > $newest) {
                $newest = $mtime;
            }
            $partStat = @stat($partPath);
            $sourceMeta['parts'][$part] = [$mtime, (int) @filesize($partPath), (int) @filectime($partPath), (int) ($partStat['ino'] ?? 0)];
        }
        $sourceMetaHash = md5((string) json_encode($sourceMeta));

        $cachedMap = null;
        if (is_file($mapPath)) {
            $decodedMap = json_decode((string) @file_get_contents($mapPath), true);
            if (is_array($decodedMap)) {
                $cachedMap = $decodedMap;
            }
        }
        $cachedMetaHash = is_array($cachedMap) && isset($cachedMap['_source_meta_hash']) ? (string) $cachedMap['_source_meta_hash'] : null;

        if ($cachedMetaHash === $sourceMetaHash && is_file($compiled)
            && ($newest <= 0 || (int) @filemtime($compiled) >= $newest)) {
            return ['path' => $compiled, 'body' => null];
        }

        $body = '';
        $map = ['_source_meta_hash' => $sourceMetaHash, '_source_meta' => $sourceMeta, '_entries' => []];
        $lineAt = 2;
        foreach ($partPaths as $part => $partPath) {
            $stripped = rx_strip_php_open((string) file_get_contents($partPath));
            $map['_entries'][] = ['start' => $lineAt, 'file' => $part];
            $body .= $stripped;
            $lineAt += substr_count($stripped, "\n");
        }
        $code = "<?php\n" . $body;

        $written = false;
        $tmp = $compiled . '.' . getmypid() . '.' . substr(md5($code), 0, 8) . '.tmp';
        if (@file_put_contents($tmp, $code, LOCK_EX) !== false) {
            if (@rename($tmp, $compiled)) {
                @chmod($compiled, 0644);
                @file_put_contents($mapPath, json_encode($map), LOCK_EX);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($compiled, true);
                }
                $written = true;
            } else {
                @unlink($tmp);
            }
        }

        if ($written) {
            return ['path' => $compiled, 'body' => $body];
        }
        return ['path' => null, 'body' => $body];
    }
}

if (!function_exists('rx_map_compiled_line')) {
    function rx_map_compiled_line($dir, $line)
    {
        $mapPath = $dir . DIRECTORY_SEPARATOR . '.compiled.map';
        if (!is_file($mapPath)) {
            return null;
        }
        $map = json_decode((string) @file_get_contents($mapPath), true);
        if (!is_array($map)) {
            return null;
        }
        $entries = isset($map['_entries']) && is_array($map['_entries']) ? $map['_entries'] : $map;
        $found = null;
        foreach ($entries as $entry) {
            if (!isset($entry['start'])) {
                continue;
            }
            if ((int) $entry['start'] <= (int) $line) {
                $found = $entry;
            } else {
                break;
            }
        }
        if ($found === null) {
            return null;
        }
        return ['file' => $found['file'], 'line' => ((int) $line - (int) $found['start']) + 2];
    }
}
