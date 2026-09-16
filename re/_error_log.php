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

if (!function_exists('rx_perf_enabled')) {
    function rx_perf_enabled()
    {
        $value = getenv('FAOXIMA_PERF_LOG');
        return $value === false || !in_array(strtolower(trim((string) $value)), ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('rx_perf_request_id')) {
    function rx_perf_request_id()
    {
        static $id = null;
        if ($id !== null) {
            return $id;
        }
        $candidate = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? (getenv('FAOXIMA_REQUEST_ID') ?: ''));
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._-]{8,80}$/', $candidate)) {
            $id = $candidate;
        } else {
            try {
                $id = bin2hex(random_bytes(8));
            } catch (Throwable $e) {
                $id = substr(hash('sha256', uniqid('', true) . getmypid()), 0, 16);
            }
        }
        return $id;
    }
}

if (!function_exists('rx_perf_safe_url')) {
    function rx_perf_safe_url($url)
    {
        $parts = @parse_url((string) $url);
        if (!is_array($parts)) {
            return '';
        }
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) . '://' : '';
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $segments = explode('/', $path);
        $redactNext = false;
        foreach ($segments as $index => $segment) {
            if ($redactNext || preg_match('/^\d{4,}$|^[0-9a-f]{8}-[0-9a-f-]{20,}$/i', $segment)) {
                $segments[$index] = '[redacted]';
                $redactNext = false;
                continue;
            }
            $redactNext = in_array(strtolower($segment), ['user', 'users', 'client', 'clients', 'order', 'orders', 'invoice', 'payment'], true);
        }
        $path = implode('/', $segments);
        return $scheme . $host . $port . $path;
    }
}

if (!function_exists('rx_perf_timestamp')) {
    function rx_perf_timestamp()
    {
        $now = microtime(true);
        $seconds = (int) floor($now);
        $micros = (int) round(($now - $seconds) * 1000000);
        if ($micros >= 1000000) {
            $seconds++;
            $micros = 0;
        }
        return gmdate('Y-m-d\TH:i:s', $seconds) . sprintf('.%06dZ', $micros);
    }
}

if (!function_exists('rx_perf_clean')) {
    function rx_perf_clean($value, $key = '')
    {
        $sensitive = preg_match('/token|secret|password|passwd|authorization|cookie|api.?key|payload|body|text|message|username|chat.?id|user.?id|card|wallet|hash/i', (string) $key);
        if ($sensitive) {
            return '[redacted]';
        }
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            $value = preg_replace('/bot\d+:[A-Za-z0-9_-]+/i', 'bot[redacted]', $value);
            if (preg_match('/error/i', (string) $key)) {
                $value = preg_replace('~https?://\S+~i', '[url]', $value);
            }
            $value = preg_replace('/[\r\n\t]+/', ' ', $value);
            return substr($value, 0, 500);
        }
        if (is_array($value)) {
            $clean = [];
            foreach (array_slice($value, 0, 40, true) as $childKey => $childValue) {
                $clean[$childKey] = rx_perf_clean($childValue, (string) $childKey);
            }
            return $clean;
        }
        return gettype($value);
    }
}

if (!function_exists('rx_perf_rotate')) {
    function rx_perf_rotate($path)
    {
        static $checked = [];
        if (isset($checked[$path])) {
            return;
        }
        $checked[$path] = true;
        $limit = (int) (getenv('FAOXIMA_PERF_LOG_MAX_BYTES') ?: 20971520);
        if ($limit < 1048576 || !is_file($path) || (int) @filesize($path) < $limit) {
            return;
        }
        @unlink($path . '.3');
        if (is_file($path . '.2')) @rename($path . '.2', $path . '.3');
        if (is_file($path . '.1')) @rename($path . '.1', $path . '.2');
        @rename($path, $path . '.1');
    }
}

if (!function_exists('rx_perf_log')) {
    function rx_perf_log($channel, $event, array $context = [])
    {
        if (!rx_perf_enabled()) {
            return false;
        }
        $allowed = ['performance', 'http', 'database', 'cron', 'slow-requests', 'server'];
        if (!in_array($channel, $allowed, true)) {
            $channel = 'performance';
        }
        $path = REFACTORED_LOG_DIR . DIRECTORY_SEPARATOR . $channel . '.log';
        rx_perf_rotate($path);
        $started = (float) ($GLOBALS['rx_perf_started_at'] ?? microtime(true));
        $record = [
            'ts' => rx_perf_timestamp(),
            'request_id' => rx_perf_request_id(),
            'event' => (string) $event,
            'elapsed_ms' => round((microtime(true) - $started) * 1000, 3),
            'pid' => getmypid(),
            'host' => gethostname() ?: '',
            'sapi' => PHP_SAPI,
            'memory_bytes' => memory_get_usage(true),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'context' => rx_perf_clean($context),
        ];
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (!isset($GLOBALS['rx_perf_buffers']) || !is_array($GLOBALS['rx_perf_buffers'])) {
            $GLOBALS['rx_perf_buffers'] = [];
        }
        if (!isset($GLOBALS['rx_perf_buffers'][$path])) {
            $GLOBALS['rx_perf_buffers'][$path] = [];
        }
        $GLOBALS['rx_perf_buffers'][$path][] = $line;
        if (count($GLOBALS['rx_perf_buffers'][$path]) >= 32) {
            return rx_perf_flush($path);
        }
        return true;
    }
}

if (!function_exists('rx_perf_flush')) {
    function rx_perf_flush($onlyPath = null)
    {
        if (empty($GLOBALS['rx_perf_buffers']) || !is_array($GLOBALS['rx_perf_buffers'])) {
            return true;
        }
        $paths = $onlyPath !== null ? [$onlyPath] : array_keys($GLOBALS['rx_perf_buffers']);
        $result = true;
        foreach ($paths as $path) {
            if (empty($GLOBALS['rx_perf_buffers'][$path])) {
                continue;
            }
            $line = implode('', $GLOBALS['rx_perf_buffers'][$path]);
            rx_perf_rotate($path);
            $handle = @fopen($path, 'ab');
            if (!is_resource($handle)) {
                $result = false;
                continue;
            }
            $locked = @flock($handle, LOCK_EX | LOCK_NB);
            if ($locked) {
                @fwrite($handle, $line);
                @flock($handle, LOCK_UN);
                unset($GLOBALS['rx_perf_buffers'][$path]);
            } else {
                $result = false;
            }
            @fclose($handle);
        }
        return $result;
    }
}

if (!function_exists('rx_perf_span_start')) {
    function rx_perf_span_start()
    {
        return hrtime(true);
    }
}

if (!function_exists('rx_perf_span_end')) {
    function rx_perf_span_end($channel, $event, $started, array $context = [])
    {
        $context['duration_ms'] = round((hrtime(true) - (int) $started) / 1000000, 3);
        rx_perf_log($channel, $event, $context);
        return $context['duration_ms'];
    }
}

$GLOBALS['rx_perf_started_at'] = $GLOBALS['rx_perf_started_at'] ?? microtime(true);
if (rx_perf_enabled()) {
    rx_perf_log('performance', 'request.start', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'uri' => rx_perf_safe_url($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'CLI')),
        'script' => basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')),
    ]);
}

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
    if (function_exists('rx_perf_log')) {
        rx_perf_log('server', 'exception.uncaught', [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stack' => $e->getTraceAsString(),
        ]);
    }
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
    if (function_exists('rx_perf_log') && rx_perf_enabled()) {
        $duration = (microtime(true) - (float) ($GLOBALS['rx_perf_started_at'] ?? microtime(true))) * 1000;
        $context = [
            'duration_ms' => round($duration, 3),
            'status' => (int) $status,
            'action' => (string) ($GLOBALS['rx_perf_action'] ?? ''),
            'update_type' => (string) ($GLOBALS['rx_perf_update_type'] ?? ''),
            'fatal' => isset($err) && is_array($err) && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true),
        ];
        $context['result'] = $context['fatal'] ? 'fatal' : ((int) $status >= 400 ? 'error' : 'completed');
        rx_perf_log('performance', 'request.end', $context);
        if ($duration >= 1000) {
            $context['threshold'] = $duration >= 30000 ? '30s' : ($duration >= 10000 ? '10s' : ($duration >= 5000 ? '5s' : ($duration >= 3000 ? '3s' : '1s')));
            rx_perf_log('slow-requests', 'request.slow', $context);
        }
    }
});

register_shutdown_function(function() {
    if (function_exists('rx_perf_flush')) {
        rx_perf_flush();
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
