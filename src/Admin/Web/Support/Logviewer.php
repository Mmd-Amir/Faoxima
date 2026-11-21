<?php

namespace Faoxima\Admin\Web\Support;

use Faoxima\Admin\Security\WebAccess;
use PDO;

final class Logviewer
{
    public static function pageCsrf(PDO $pdo, ?string $username, callable $csrf, callable $ipAllowed): ?string
    {
        $admin = $username === null ? false : WebAccess::findByUsername($pdo, $username);
        if (!$admin || !$ipAllowed($admin['iplogin'] ?? null)) {
            return null;
        }
        return $csrf();
    }

    public static function lv_root(): string
{
    static $root = null;
    if ($root === null) {
        $real = realpath((dirname(__DIR__, 4) . '/panel/lib') . '/../..');
        $root = $real === false ? '' : str_replace('\\', '/', $real);
    }
    return $root;
}

    public static function lv_php_names(): array
{
    return ['error_log', 'error.log', 'php-error.log', 'php_error.log', 'php_errors.log'];
}

    public static function lv_path_eq(string $a, string $b): bool
{
    return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($a, $b) === 0 : $a === $b;
}

    public static function lv_log_name_ok(string $base): bool
{
    if ($base === '' || $base[0] === '.') {
        return false;
    }
    if (in_array(strtolower($base), self::lv_php_names(), true)) {
        return true;
    }
    return (bool)preg_match('/^.+\.log(\.\d{1,3})?$/i', $base);
}

    public static function lv_blocked_dir(string $seg): bool
{
    $l = strtolower($seg);
    return $seg === '' || $seg[0] === '.' || in_array($l, ['tmp', 'temp', 'upload', 'uploads', 'cache', 'sessions'], true);
}

    public static function lv_classify(string $rel): ?array
{
    if ($rel === '' || strlen($rel) > 300 || strpos($rel, "\0") !== false || strpos($rel, '\\') !== false || $rel[0] === '/') {
        return null;
    }
    $parts = explode('/', $rel);
    foreach ($parts as $p) {
        if ($p === '' || $p === '.' || $p === '..') {
            return null;
        }
    }
    $base = end($parts);
    if ($parts[0] === 'logs') {
        if (count($parts) < 2 || count($parts) > 6) {
            return null;
        }
        $kind = 'log';
        foreach (array_slice($parts, 1, -1) as $seg) {
            if (self::lv_blocked_dir($seg)) {
                $kind = 'support';
            }
        }
        if ($kind === 'log' && !self::lv_log_name_ok($base)) {
            $kind = 'support';
        }
        return ['source' => 'logs', 'kind' => $kind, 'deletable' => $kind === 'log'];
    }
    $skip = ['vendor', 'node_modules', 'graphify-out', 'logs'];
    if (count($parts) <= 4 && in_array(strtolower($base), self::lv_php_names(), true)) {
        foreach (array_slice($parts, 0, -1) as $seg) {
            if (self::lv_blocked_dir($seg) || in_array(strtolower($seg), $skip, true)) {
                return null;
            }
        }
        return ['source' => 'php', 'kind' => 'log', 'deletable' => true];
    }
    return null;
}

    public static function lv_validate(string $rel): array
{
    $cls = self::lv_classify($rel);
    if ($cls === null) {
        return ['ok' => false, 'code' => 'invalid'];
    }
    $root = self::lv_root();
    if ($root === '') {
        return ['ok' => false, 'code' => 'invalid'];
    }
    $abs = $root . '/' . $rel;
    clearstatcache(true, $abs);
    if (is_link($abs)) {
        return ['ok' => false, 'code' => 'unsafe'];
    }
    if (!file_exists($abs)) {
        return ['ok' => false, 'code' => 'missing'];
    }
    if (!is_file($abs)) {
        return ['ok' => false, 'code' => 'invalid'];
    }
    $real = realpath($abs);
    if ($real === false) {
        return ['ok' => false, 'code' => 'missing'];
    }
    $real = str_replace('\\', '/', $real);
    if (!self::lv_path_eq($real, $root . '/' . $rel)) {
        return ['ok' => false, 'code' => 'unsafe'];
    }
    return ['ok' => true, 'rel' => $rel, 'abs' => $abs, 'cls' => $cls];
}

    public static function lv_fmt_of(string $base): string
{
    $l = strtolower($base);
    if (in_array($l, self::lv_php_names(), true)) {
        return 'php';
    }
    if (strpos($l, 'api-') === 0) {
        return 'api';
    }
    if (strpos($l, 'client-') === 0) {
        return 'client';
    }
    if (strpos($l, 'ui-actions-') === 0) {
        return 'ui';
    }
    if (strpos($l, 'backup') === 0) {
        return 'backup';
    }
    if (strpos($l, 'nowpayment') === 0) {
        return 'payment';
    }
    if (preg_match('/^[a-z0-9_]+-\d{4}-\d{2}-\d{2}\.log$/', $l)) {
        return 'diag';
    }
    return 'text';
}

    public static function lv_is_binary(string $abs): bool
{
    $fh = @fopen($abs, 'rb');
    if (!$fh) {
        return false;
    }
    $buf = (string)fread($fh, 4096);
    fclose($fh);
    return $buf !== '' && strpos($buf, "\0") !== false;
}

    public static function lv_entry(string $rel, string $abs, array $cls, bool $probe): ?array
{
    $st = @lstat($abs);
    if ($st === false || ($st['mode'] & 0170000) !== 0100000) {
        return null;
    }
    $base = basename($rel);
    $dir = dirname($rel);
    $e = [
        'id' => $rel,
        'name' => $base,
        'dir' => $dir === '.' ? '' : $dir,
        'source' => $cls['source'],
        'kind' => $cls['kind'],
        'fmt' => $cls['kind'] === 'log' ? self::lv_fmt_of($base) : 'support',
        'size' => (int)$st['size'],
        'mtime' => (int)$st['mtime'],
        'ino' => (int)$st['ino'],
        'mtime_s' => date('Y-m-d H:i:s', (int)$st['mtime']),
        'deletable' => $cls['deletable'] && $base !== '.htaccess',
        'readable' => @is_readable($abs),
        'binary' => false,
    ];
    if ($probe && $cls['kind'] === 'log' && $e['readable'] && $e['size'] > 0) {
        $e['binary'] = self::lv_is_binary($abs);
    }
    return $e;
}

    public static function lv_walk_logs(string $absDir, string $rel, int $depth, array &$out, int &$count): void
{
    if ($depth > 5 || $count >= LV_MAX_FILES) {
        return;
    }
    $items = @scandir($absDir);
    if ($items === false) {
        return;
    }
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if ($count >= LV_MAX_FILES) {
            return;
        }
        $abs = $absDir . '/' . $name;
        $r = $rel . '/' . $name;
        if (is_link($abs)) {
            continue;
        }
        if (is_dir($abs)) {
            self::lv_walk_logs($abs, $r, $depth + 1, $out, $count);
            continue;
        }
        $cls = self::lv_classify($r);
        if ($cls === null) {
            continue;
        }
        $e = self::lv_entry($r, $abs, $cls, true);
        if ($e !== null) {
            $out[] = $e;
            $count++;
        }
    }
}

    public static function lv_walk_php(string $absDir, string $rel, int $depth, array &$out, int &$count, int &$dirs): void
{
    if ($depth > 3 || $dirs > 800 || $count >= LV_MAX_FILES) {
        return;
    }
    $dirs++;
    $items = @scandir($absDir);
    if ($items === false) {
        return;
    }
    $skip = ['vendor', 'node_modules', 'graphify-out', 'logs'];
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $abs = $absDir . '/' . $name;
        $r = $rel === '' ? $name : $rel . '/' . $name;
        if (is_link($abs)) {
            continue;
        }
        if (is_dir($abs)) {
            if (self::lv_blocked_dir($name) || in_array(strtolower($name), $skip, true)) {
                continue;
            }
            self::lv_walk_php($abs, $r, $depth + 1, $out, $count, $dirs);
            continue;
        }
        if (!in_array(strtolower($name), self::lv_php_names(), true)) {
            continue;
        }
        $cls = self::lv_classify($r);
        if ($cls === null || $cls['source'] !== 'php') {
            continue;
        }
        $e = self::lv_entry($r, $abs, $cls, true);
        if ($e !== null) {
            $out[] = $e;
            $count++;
        }
    }
}

    public static function lv_discover(): array
{
    $root = self::lv_root();
    $out = [];
    if ($root === '') {
        return $out;
    }
    $count = 0;
    $logs = $root . '/logs';
    if (is_dir($logs) && !is_link($logs)) {
        self::lv_walk_logs($logs, 'logs', 0, $out, $count);
    }
    $dirs = 0;
    self::lv_walk_php($root, '', 0, $out, $count, $dirs);
    usort($out, function ($a, $b) {
        if ($a['kind'] !== $b['kind']) {
            return $a['kind'] === 'log' ? -1 : 1;
        }
        if ($a['mtime'] === $b['mtime']) {
            return strcmp($a['id'], $b['id']);
        }
        return $b['mtime'] <=> $a['mtime'];
    });
    return $out;
}

    public static function lv_redact_string(string $s): string
{
    if ($s === '') {
        return $s;
    }
    $s = preg_replace('/\bbot\d{6,12}:[A-Za-z0-9_-]{30,}/', 'bot***', $s);
    $s = preg_replace('/\b\d{6,12}:[A-Za-z0-9_-]{30,}\b/', '***:***', $s);
    $s = preg_replace('/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{4,}/', '***jwt***', $s);
    $s = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]{6,}/i', '$1 ***', $s);
    $s = preg_replace('/\b((?:Proxy-)?Authorization|Cookie|Set-Cookie|X-Api-Key|X-Auth-Token)(\s*[:=]\s*)[^\r\n"\',}]*/i', '$1$2***', $s);
    $keys = 'password|passwd|pwd|secret|token|api[_-]?key|apikey|access[_-]?token|refresh[_-]?token|private[_-]?key|client[_-]?secret|auth[_-]?key|signature';
    $s = preg_replace('/(["\']?\b(?:[A-Za-z0-9_-]*[_-])?(?:' . $keys . ')\b["\']?\s*[:=]\s*)"(?:[^"\\\\]|\\\\.)*"/i', '$1"***"', $s);
    $s = preg_replace('/(["\']?\b(?:[A-Za-z0-9_-]*[_-])?(?:' . $keys . ')\b["\']?\s*[:=]\s*)\'[^\']*\'/i', '$1\'***\'', $s);
    $s = preg_replace('/(\b(?:[A-Za-z0-9_-]*[_-])?(?:' . $keys . ')\b\s*[:=]\s*)(?!\*\*\*|"|\')[^\s,;&}\)\]"\']+/i', '$1***', $s);
    $s = preg_replace('/(:\/\/[^\s:\/@]+:)[^\s@\/]+@/', '$1***@', $s);
    $root = self::lv_root();
    if ($root !== '') {
        $s = str_ireplace([$root, str_replace('/', '\\', $root), str_replace('/', '\\\\', $root)], '{app}', $s);
    }
    return $s === null ? '' : $s;
}

    public static function lv_sensitive_key(string $k): bool
{
    return (bool)preg_match('/(password|passwd|(^|[_-])pass($|[_-])|pwd|secret|token|api[_-]?key|authorization|cookie|private[_-]?key|signature|credential)/i', $k);
}

    public static function lv_redact_value($v, int $depth = 0)
{
    if (is_array($v)) {
        if ($depth > 8) {
            return '…';
        }
        $out = [];
        $n = 0;
        foreach ($v as $k => $item) {
            if (++$n > 200) {
                $out['…'] = 'truncated';
                break;
            }
            if (is_string($k) && self::lv_sensitive_key($k) && !is_array($item)) {
                $out[$k] = '***';
            } else {
                $out[$k] = self::lv_redact_value($item, $depth + 1);
            }
        }
        return $out;
    }
    if (is_string($v)) {
        if (strlen($v) > LV_MSG_CAP) {
            $v = substr($v, 0, LV_MSG_CAP) . '…';
        }
        return self::lv_redact_string(self::lv_utf8($v));
    }
    return $v;
}

    public static function lv_utf8(string $s): string
{
    if ($s === '' || preg_match('//u', $s)) {
        return $s;
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $c = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
    return $c === false ? '' : $c;
}

    public static function lv_norm_level($v): ?string
{
    if (!is_string($v) && !is_int($v)) {
        return null;
    }
    $l = strtolower(trim((string)$v));
    $map = [
        'debug' => 'debug', 'trace' => 'debug',
        'info' => 'info', 'information' => 'info', 'ok' => 'info', 'success' => 'info',
        'notice' => 'notice', 'deprecated' => 'notice', 'strict' => 'notice',
        'warn' => 'warning', 'warning' => 'warning',
        'error' => 'error', 'err' => 'error',
        'critical' => 'critical', 'crit' => 'critical', 'fatal' => 'critical', 'alert' => 'critical', 'emergency' => 'critical', 'emerg' => 'critical',
    ];
    return $map[$l] ?? null;
}

    public static function lv_line_kind(string $t, &$json): string
{
    $json = null;
    if ($t === '') {
        return 'blank';
    }
    if ($t[0] === '{' && strlen($t) < 400000) {
        $d = json_decode($t, true);
        if (is_array($d)) {
            $json = $d;
            return 'json';
        }
    }
    if (preg_match('/^\[(?:\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:[.,]\d+)?|\d{1,2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}(?: [A-Za-z0-9\/_+\-]+)?)\]/', $t)) {
        return 'text';
    }
    if (preg_match('/^(?:PHP )?(?:Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards|Catchable fatal error):/', $t)) {
        return 'text';
    }
    return 'cont';
}

    public static function lv_probe_structured($fh): bool
{
    fseek($fh, 0);
    $buf = (string)fread($fh, 16384);
    if (strlen($buf) >= 16384) {
        $p = strrpos($buf, "
");
        if ($p !== false) {
            $buf = substr($buf, 0, $p);
        }
    }
    foreach (explode("
", $buf) as $l) {
        $j = null;
        $k = self::lv_line_kind(rtrim($l, "
"), $j);
        if ($k === 'json' || $k === 'text') {
            return true;
        }
    }
    return false;
}

    public static function lv_parse_chunk(string $buf, int $baseOffset, bool $dropLeading, bool $forceStructured = false): array
{
    $len = strlen($buf);
    $lines = [];
    $pos = 0;
    while ($pos < $len) {
        $nl = strpos($buf, "\n", $pos);
        if ($nl === false) {
            $end = $len;
            $next = $len;
        } else {
            $end = $nl;
            $next = $nl + 1;
        }
        $t = substr($buf, $pos, $end - $pos);
        if ($t !== '' && substr($t, -1) === "\r") {
            $t = substr($t, 0, -1);
        }
        $lines[] = [$baseOffset + $pos, $t, $baseOffset + $next];
        $pos = $next;
    }
    $kinds = [];
    $jsons = [];
    $structured = $forceStructured;
    foreach ($lines as $i => $ln) {
        $j = null;
        $k = self::lv_line_kind($ln[1], $j);
        $kinds[$i] = $k;
        $jsons[$i] = $j;
        if ($k === 'json' || $k === 'text') {
            $structured = true;
        }
    }
    $events = [];
    $cur = null;
    $flush = function () use (&$events, &$cur) {
        if ($cur !== null) {
            $events[] = $cur;
            $cur = null;
        }
    };
    foreach ($lines as $i => $ln) {
        $k = $kinds[$i];
        if ($k === 'blank') {
            continue;
        }
        if ($k === 'json') {
            $flush();
            $events[] = ['o' => $ln[0], 'end' => $ln[2], 'lines' => [$ln[1]], 'json' => $jsons[$i], 'bytes' => strlen($ln[1]), 'cut' => false];
            continue;
        }
        if ($k === 'text') {
            $flush();
            $cur = ['o' => $ln[0], 'end' => $ln[2], 'lines' => [$ln[1]], 'json' => null, 'bytes' => strlen($ln[1]), 'cut' => false];
            continue;
        }
        if (!$structured) {
            $events[] = ['o' => $ln[0], 'end' => $ln[2], 'lines' => [$ln[1]], 'json' => null, 'bytes' => strlen($ln[1]), 'cut' => false];
            continue;
        }
        if ($cur !== null) {
            if (count($cur['lines']) < LV_EVENT_LINES && $cur['bytes'] < LV_EVENT_BYTES) {
                $cur['lines'][] = $ln[1];
                $cur['bytes'] += strlen($ln[1]);
            } else {
                $cur['cut'] = true;
            }
            $cur['end'] = $ln[2];
            continue;
        }
        if ($dropLeading && !$events) {
            continue;
        }
        $events[] = ['o' => $ln[0], 'end' => $ln[2], 'lines' => [$ln[1]], 'json' => null, 'bytes' => strlen($ln[1]), 'cut' => false];
    }
    $flush();
    return ['events' => $events, 'structured' => $structured];
}

    public static function lv_split_trace(string $s): array
{
    $out = [];
    foreach (preg_split('/\r?\n/', $s) as $l) {
        $l = trim($l);
        if ($l !== '') {
            $out[] = $l;
        }
    }
    return $out;
}

    public static function lv_php_severity_level(int $s): string
{
    if (in_array($s, [1, 4, 16, 64], true)) {
        return 'critical';
    }
    if (in_array($s, [2, 32, 128, 512], true)) {
        return 'warning';
    }
    if (in_array($s, [8, 1024, 2048, 8192, 16384], true)) {
        return 'notice';
    }
    return 'error';
}

    public static function lv_php_severity_name(int $s): string
{
    $map = [1 => 'E_ERROR', 2 => 'E_WARNING', 4 => 'E_PARSE', 8 => 'E_NOTICE', 16 => 'E_CORE_ERROR', 32 => 'E_CORE_WARNING', 64 => 'E_COMPILE_ERROR', 128 => 'E_COMPILE_WARNING', 256 => 'E_USER_ERROR', 512 => 'E_USER_WARNING', 1024 => 'E_USER_NOTICE', 2048 => 'E_STRICT', 4096 => 'E_RECOVERABLE_ERROR', 8192 => 'E_DEPRECATED', 16384 => 'E_USER_DEPRECATED'];
    return $map[$s] ?? (string)$s;
}

    public static function lv_runtime_parse(string $text): ?array
{
    if (!preg_match('/^\[[^\]]+\]\s*\[(PHP_ERROR|UNCAUGHT_THROWABLE|FATAL_SHUTDOWN|HTTP_5XX)\]\s?(.*)$/s', $text, $m)) {
        return null;
    }
    $type = $m[1];
    $parts = preg_split('/ \| (?=[A-Za-z_][A-Za-z0-9_]*=)/', $m[2]);
    $msg = (string)array_shift($parts);
    $meta = [];
    foreach ($parts as $piece) {
        $eq = strpos($piece, '=');
        if ($eq === false) {
            continue;
        }
        $meta[substr($piece, 0, $eq)] = trim(substr($piece, $eq + 1));
    }
    if ($type === 'FATAL_SHUTDOWN') {
        $lv = 'critical';
    } elseif ($type === 'PHP_ERROR' && isset($meta['severity']) && ctype_digit($meta['severity'])) {
        $lv = self::lv_php_severity_level((int)$meta['severity']);
    } else {
        $lv = 'error';
    }
    return ['type' => $type, 'msg' => trim($msg), 'meta' => $meta, 'lv' => $lv];
}

    public static function lv_light(array $ev, string $fmt): array
{
    $ts = null;
    $lv = null;
    if ($ev['json'] !== null) {
        $j = $ev['json'];
        $ts = isset($j['ts']) && is_scalar($j['ts']) ? (string)$j['ts'] : (isset($j['time']) && is_scalar($j['time']) ? (string)$j['time'] : null);
        $lv = self::lv_norm_level($j['level'] ?? ($j['severity'] ?? null));
    } else {
        $head = $ev['lines'][0];
        if (preg_match('/^\[([^\]]+)\]/', $head, $m)) {
            $ts = $m[1];
        }
        $rt = preg_match('/^\[[^\]]+\]\s*\[(?:PHP_ERROR|UNCAUGHT_THROWABLE|FATAL_SHUTDOWN|HTTP_5XX)\]/', $head) ? self::lv_runtime_parse(implode("\n", $ev['lines'])) : null;
        if ($rt !== null) {
            $lv = $rt['lv'];
        } elseif (preg_match('/^\[[^\]]+\]\s*\[(DEBUG|INFO|NOTICE|WARN|WARNING|ERROR|CRITICAL|FATAL|ALERT|EMERGENCY)\]/i', $head, $m)) {
            $lv = self::lv_norm_level($m[1]);
        } elseif (preg_match('/(?:^|\]\s*)(?:PHP\s+)?(Fatal error|Parse error|Catchable fatal error|Warning|Notice|Deprecated|Strict Standards):/', $head, $m)) {
            $t = strtolower($m[1]);
            if (strpos($t, 'fatal') !== false || strpos($t, 'parse') !== false) {
                $lv = 'critical';
            } elseif ($t === 'warning') {
                $lv = 'warning';
            } else {
                $lv = 'notice';
            }
        }
    }
    $tse = null;
    if ($ts !== null && $ts !== '') {
        $t = strtotime($ts);
        $tse = $t === false ? null : $t;
    }
    return ['ts' => $ts, 'tse' => $tse, 'lv' => $lv ?? 'unknown'];
}

    public static function lv_build_event(array $ev, array $light, string $fileId, string $fmt): array
{
    $raw = implode("\n", $ev['lines']);
    if ($ev['cut']) {
        $raw .= "\n…";
    }
    $raw = self::lv_utf8($raw);
    $msg = '';
    $ctx = null;
    $file = null;
    $line = null;
    $trace = [];
    $channel = null;
    if ($ev['json'] !== null) {
        $j = $ev['json'];
        if (isset($j['msg']) && is_scalar($j['msg'])) {
            $msg = (string)$j['msg'];
        } elseif (isset($j['message']) && is_scalar($j['message'])) {
            $msg = (string)$j['message'];
        } elseif (isset($j['channel'])) {
            $msg = (string)$j['channel'] . (isset($j['step']) && is_scalar($j['step']) ? ' › ' . (string)$j['step'] : '');
        } elseif (isset($j['action']) && is_scalar($j['action'])) {
            $msg = (string)$j['action'];
        } else {
            $msg = '';
        }
        if (isset($j['channel']) && is_scalar($j['channel'])) {
            $channel = (string)$j['channel'];
        }
        $rest = $j;
        foreach (['ts', 'level', 'msg', 'message'] as $drop) {
            unset($rest[$drop]);
        }
        $c = [];
        if (isset($rest['ctx']) && is_array($rest['ctx'])) {
            $c = $rest['ctx'];
            unset($rest['ctx']);
            $rest = $rest + $c;
        }
        if (isset($rest['stack']) && is_string($rest['stack'])) {
            $trace = self::lv_split_trace($rest['stack']);
            unset($rest['stack']);
        }
        if (isset($c['trace']) && is_string($c['trace'])) {
            $trace = array_map('trim', explode(' | ', $c['trace']));
            unset($rest['trace']);
        }
        if (isset($c['file']) && is_string($c['file'])) {
            $file = $c['file'];
            $line = isset($c['line']) ? (int)$c['line'] : null;
        } elseif (isset($j['file']) && is_string($j['file'])) {
            $file = $j['file'];
            $line = isset($j['line']) ? (int)$j['line'] : null;
        } elseif (isset($j['where']) && is_string($j['where']) && $j['where'] !== '') {
            if (preg_match('/^(.*?):(\d+)(?::\d+)?$/', $j['where'], $m)) {
                $file = $m[1];
                $line = (int)$m[2];
            } else {
                $file = $j['where'];
            }
        }
        $ctx = $rest ? $rest : null;
    } elseif (($rt = self::lv_runtime_parse(implode("\n", $ev['lines']))) !== null) {
        $msg = $rt['msg'];
        $meta = $rt['meta'];
        if (isset($meta['file']) && $meta['file'] !== '') {
            $file = $meta['file'];
            $line = isset($meta['line']) && ctype_digit($meta['line']) ? (int)$meta['line'] : null;
        }
        unset($meta['file'], $meta['line']);
        $ctx = ['type' => $rt['type']];
        foreach ($meta as $mk => $mv) {
            $ctx[$mk] = $mv;
        }
        if (isset($ctx['severity']) && ctype_digit((string)$ctx['severity'])) {
            $ctx['severity'] = self::lv_php_severity_name((int)$ctx['severity']) . ' (' . $ctx['severity'] . ')';
        }
    } else {
        $first = $ev['lines'][0];
        $body = $first;
        $body = preg_replace('/^\[[^\]]+\]\s*/', '', $body, 1);
        if (preg_match('/^\[(?:DEBUG|INFO|NOTICE|WARN|WARNING|ERROR|CRITICAL|FATAL|ALERT|EMERGENCY)\]\s*/i', $body, $m)) {
            $body = substr($body, strlen($m[0]));
        }
        if (preg_match('/^\[([A-Za-z0-9_.\-]+):(\d+)\]\s*/', $body, $m)) {
            $file = $m[1];
            $line = (int)$m[2];
            $body = substr($body, strlen($m[0]));
        } elseif (preg_match('/^\[([A-Za-z0-9_.\-]+)\]\s*/', $body, $m)) {
            $channel = $m[1];
            $body = substr($body, strlen($m[0]));
        }
        $tail = [];
        foreach (array_slice($ev['lines'], 1) as $l) {
            if (preg_match('/^\s*#\d+\s/', $l) || preg_match('/^\s*Stack trace:\s*$/', $l)) {
                if (!preg_match('/^\s*Stack trace:\s*$/', $l)) {
                    $trace[] = trim($l);
                }
            } else {
                $tail[] = $l;
            }
        }
        $full = $body . ($tail ? "\n" . implode("\n", $tail) : '');
        $cpos = strpos($full, ' | Context: ');
        if ($cpos !== false) {
            $decoded = json_decode(trim(substr($full, $cpos + 12)), true);
            if (is_array($decoded)) {
                $ctx = $decoded;
                $full = substr($full, 0, $cpos);
            }
        } elseif ($tail === [] && preg_match('/\s(\{.*\})\s*$/', $body, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                $ctx = $decoded;
                $full = trim(substr($full, 0, strlen($full) - strlen($m[0])));
            }
        }
        $msg = $full;
        if ($file === null && preg_match('/\b(?:in|at)\s+(\S+?\.php)(?::|\s+on line\s+)(\d+)/', $full, $m)) {
            $file = $m[1];
            $line = (int)$m[2];
        }
        if ($channel !== null) {
            $ctx = ($ctx ?: []);
            $ctx = array_merge(['channel' => $channel], $ctx);
        }
    }
    if (strlen($msg) > LV_MSG_CAP) {
        $msg = substr($msg, 0, LV_MSG_CAP) . '…';
    }
    if (strlen($raw) > LV_RAW_CAP) {
        $raw = substr($raw, 0, LV_RAW_CAP) . "\n…";
    }
    $msg = self::lv_redact_string(self::lv_utf8(trim($msg)));
    $raw = self::lv_redact_string($raw);
    $trace = array_slice(array_map(function ($l) {
        return self::lv_redact_string(self::lv_utf8((string)$l));
    }, $trace), 0, 120);
    if ($file !== null) {
        $file = self::lv_redact_string(self::lv_utf8($file));
        if (strlen($file) > 400) {
            $file = substr($file, 0, 400) . '…';
        }
    }
    return [
        'k' => $fileId . '|' . $ev['o'] . '|' . dechex(crc32(substr($raw, 0, 200))),
        'f' => $fileId,
        'o' => $ev['o'],
        'ts' => $light['ts'] !== null ? self::lv_redact_string(self::lv_utf8($light['ts'])) : null,
        'tse' => $light['tse'],
        'lv' => $light['lv'],
        'msg' => $msg,
        'file' => $file,
        'line' => $line,
        'trace' => $trace,
        'ctx' => $ctx === null ? null : self::lv_redact_value($ctx),
        'raw' => $raw,
        'json' => $ev['json'] !== null,
        'cut' => $ev['cut'],
    ];
}

    public static function lv_parse_filters(array $src): array
{
    $allowed = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'unknown'];
    $levels = [];
    if (isset($src['levels']) && is_string($src['levels']) && $src['levels'] !== '') {
        foreach (explode(',', $src['levels']) as $l) {
            $l = strtolower(trim($l));
            if (in_array($l, $allowed, true)) {
                $levels[$l] = true;
            }
        }
    }
    $view = isset($src['view']) && $src['view'] === 'errors' ? 'errors' : 'all';
    if ($view === 'errors') {
        $levels = ['error' => true, 'critical' => true];
    }
    $q = isset($src['q']) && is_string($src['q']) ? trim($src['q']) : '';
    if (strlen($q) > 200) {
        $q = substr($q, 0, 200);
    }
    return ['levels' => $levels, 'q' => $q, 'view' => $view];
}

    public static function lv_has_filters(array $f): bool
{
    return $f['q'] !== '' || $f['levels'];
}

    public static function lv_match_light(array $light, array $f): bool
{
    if ($f['levels'] && !isset($f['levels'][$light['lv']])) {
        return false;
    }
    return true;
}

    public static function lv_match_text(string $text, string $q): bool
{
    if ($q === '') {
        return true;
    }
    return function_exists('mb_stripos') ? mb_stripos($text, $q, 0, 'UTF-8') !== false : stripos($text, $q) !== false;
}

    public static function lv_read_page(array $f, ?int $before, int $limit, array $filt, int $budget): array
{
    $abs = $f['abs'];
    clearstatcache(true, $abs);
    $size = (int)@filesize($abs);
    $fh = @fopen($abs, 'rb');
    if (!$fh) {
        return ['error' => 'permission'];
    }
    $reset = false;
    if ($before === null || $before > $size) {
        $reset = $before !== null;
        $before = $size;
    }
    $fmt = self::lv_fmt_of(basename($f['rel']));
    $forced = self::lv_probe_structured($fh);
    $found = [];
    $scanned = 0;
    $chunk = LV_CHUNK;
    $scannedFrom = $before;
    $next = $before > 0 ? $before : null;
    $t0 = microtime(true);
    $hasQ = $filt['q'] !== '';
    while ($before > 0) {
        if ($scanned >= $budget || (microtime(true) - $t0) > LV_TIME_BUDGET) {
            $next = $before;
            break;
        }
        $len = min($chunk, $before);
        $start = $before - $len;
        $readFrom = $start > 0 ? $start - 1 : 0;
        fseek($fh, $readFrom);
        $buf = (string)fread($fh, $before - $readFrom);
        $scanned += strlen($buf);
        $scannedFrom = $start;
        $off = $readFrom;
        if ($start > 0) {
            if ($buf !== '' && $buf[0] === "\n") {
                $buf = substr($buf, 1);
                $off = $readFrom + 1;
            } else {
                $p = strpos($buf, "\n");
                if ($p === false) {
                    if ($chunk < LV_MAX_CHUNK) {
                        $chunk = min($chunk * 2, LV_MAX_CHUNK);
                        continue;
                    }
                    $buf = '';
                    $off = $before;
                } else {
                    $buf = substr($buf, $p + 1);
                    $off = $readFrom + $p + 1;
                }
            }
        }
        $parsed = self::lv_parse_chunk($buf, $off, $start > 0, $forced);
        $evs = $parsed['events'];
        if ($start > 0 && !$evs) {
            if ($chunk < LV_MAX_CHUNK) {
                $chunk = min($chunk * 2, LV_MAX_CHUNK);
                continue;
            }
            $before = $start;
            $next = $start > 0 ? $start : null;
            continue;
        }
        $chunk = LV_CHUNK;
        $stopAt = null;
        for ($i = count($evs) - 1; $i >= 0; $i--) {
            $ev = $evs[$i];
            $light = self::lv_light($ev, $fmt);
            if (!self::lv_match_light($light, $filt)) {
                continue;
            }
            $built = null;
            if ($hasQ) {
                $probe = self::lv_redact_string(self::lv_utf8(implode("\n", $ev['lines'])));
                if (!self::lv_match_text($probe, $filt['q'])) {
                    continue;
                }
            }
            $built = self::lv_build_event($ev, $light, $f['rel'], $fmt);
            $found[] = $built;
            if (count($found) >= $limit) {
                $stopAt = $ev['o'];
                break;
            }
        }
        if ($stopAt !== null) {
            $next = $stopAt > 0 ? $stopAt : null;
            $before = 0;
            fclose($fh);
            return self::lv_page_result($found, $next, $size, $scanned, $scannedFrom, $reset);
        }
        if ($start === 0) {
            $before = 0;
            $next = null;
            break;
        }
        $first = $evs ? $evs[0]['o'] : $start;
        $before = $first;
        $next = $first > 0 ? $first : null;
        if ($first === 0) {
            $next = null;
            break;
        }
    }
    fclose($fh);
    if ($before <= 0) {
        $next = null;
    }
    return self::lv_page_result($found, $next, $size, $scanned, $scannedFrom, $reset);
}

    public static function lv_page_result(array $events, ?int $next, int $size, int $scanned, int $scannedFrom, bool $reset): array
{
    return [
        'events' => $events,
        'next' => $next,
        'size' => $size,
        'scanned' => $scanned,
        'scanned_from' => $scannedFrom,
        'reset' => $reset,
    ];
}

    public static function lv_readable_logs(array $all): array
{
    $out = [];
    foreach ($all as $e) {
        if ($e['kind'] === 'log' && $e['readable'] && !$e['binary'] && $e['size'] > 0) {
            $out[] = $e;
        }
    }
    return $out;
}

    public static function lv_cursor_key(string $id): string
{
    return dechex(crc32($id));
}

    public static function lv_read_merge(array $cursors, int $limit, array $filt, ?string $source): array
{
    $files = self::lv_readable_logs(self::lv_discover());
    if ($source === 'logs' || $source === 'php') {
        $files = array_values(array_filter($files, function ($e) use ($source) {
            return $e['source'] === $source;
        }));
    }
    $total = count($files);
    $active = [];
    $next = [];
    foreach ($files as $e) {
        $key = self::lv_cursor_key($e['id']);
        $cur = array_key_exists($key, $cursors) ? $cursors[$key] : null;
        if ($cur !== null) {
            $next[$key] = $cur;
        }
        if ($cur !== null && $cur <= 0) {
            continue;
        }
        if (count($active) < LV_MERGE_MAX_FILES) {
            $e['key'] = $key;
            $e['cur'] = $cur;
            $active[] = $e;
        }
    }
    $all = [];
    $fetched = [];
    $t0 = microtime(true);
    foreach ($active as $e) {
        $key = $e['key'];
        $cur = $e['cur'];
        if ((microtime(true) - $t0) > LV_TIME_BUDGET) {
            $next[$key] = $cur === null ? $e['size'] : $cur;
            continue;
        }
        $v = self::lv_validate($e['id']);
        if (!$v['ok']) {
            $next[$key] = 0;
            continue;
        }
        $res = self::lv_read_page($v, $cur, $limit, $filt, LV_MERGE_FILE_BUDGET);
        if (isset($res['error'])) {
            $next[$key] = 0;
            continue;
        }
        $fetched[$key] = $res;
        $next[$key] = $res['next'] === null ? 0 : $res['next'];
        foreach ($res['events'] as $ev) {
            $ev['mt'] = $e['mtime'];
            $ev['ck'] = $key;
            $all[] = $ev;
        }
    }
    usort($all, function ($a, $b) {
        $ka = $a['tse'] !== null ? $a['tse'] : $a['mt'];
        $kb = $b['tse'] !== null ? $b['tse'] : $b['mt'];
        if ($ka === $kb) {
            return $b['o'] <=> $a['o'];
        }
        return $kb <=> $ka;
    });
    $slice = array_slice($all, 0, $limit);
    $returnedMin = [];
    $returnedCount = [];
    foreach ($slice as $ev) {
        $key = $ev['ck'];
        $returnedCount[$key] = ($returnedCount[$key] ?? 0) + 1;
        if (!isset($returnedMin[$key]) || $ev['o'] < $returnedMin[$key]) {
            $returnedMin[$key] = $ev['o'];
        }
    }
    foreach ($fetched as $key => $res) {
        if ($res['next'] === null && ($returnedCount[$key] ?? 0) === count($res['events'])) {
            $next[$key] = 0;
        } elseif (isset($returnedMin[$key])) {
            $next[$key] = $returnedMin[$key];
        } elseif ($res['events']) {
            $next[$key] = array_key_exists($key, $cursors) && $cursors[$key] !== null ? $cursors[$key] : $res['size'];
        }
    }
    $done = 0;
    foreach ($files as $e) {
        $key = self::lv_cursor_key($e['id']);
        if (isset($next[$key]) && $next[$key] <= 0) {
            $done++;
        }
    }
    foreach ($slice as &$ev) {
        unset($ev['mt'], $ev['ck']);
    }
    unset($ev);
    return [
        'events' => $slice,
        'cursors' => (object)$next,
        'more' => $done < $total,
        'files_total' => $total,
        'files_done' => $done,
        'files_used' => count($active),
    ];
}

    public static function lv_stats(): array
{
    $files = self::lv_readable_logs(self::lv_discover());
    $eligible = count($files);
    $eligibleBytes = 0;
    foreach ($files as $e) {
        $eligibleBytes += $e['size'];
    }
    $considered = array_slice($files, 0, LV_STATS_MAX_FILES);
    $per = [];
    $sum = ['events' => 0, 'error' => 0, 'critical' => 0, 'warning' => 0, 'notice' => 0, 'info' => 0, 'debug' => 0, 'unknown' => 0];
    $scannedBytes = 0;
    $scannedFileBytes = 0;
    $skipLimit = $eligible - count($considered);
    $skipTime = 0;
    $skipError = 0;
    $partial = 0;
    $t0 = microtime(true);
    foreach ($considered as $e) {
        if ((microtime(true) - $t0) > LV_TIME_BUDGET) {
            $skipTime++;
            continue;
        }
        $abs = self::lv_root() . '/' . $e['id'];
        $fh = @fopen($abs, 'rb');
        if (!$fh) {
            $skipError++;
            continue;
        }
        $size = (int)@filesize($abs);
        $forced = self::lv_probe_structured($fh);
        $start = max(0, $size - LV_STATS_BYTES);
        $readFrom = $start > 0 ? $start - 1 : 0;
        fseek($fh, $readFrom);
        $buf = (string)fread($fh, $size - $readFrom);
        fclose($fh);
        $off = $readFrom;
        if ($start > 0) {
            if ($buf !== '' && $buf[0] === "\n") {
                $buf = substr($buf, 1);
                $off = $readFrom + 1;
            } else {
                $p = strpos($buf, "\n");
                if ($p === false) {
                    $skipError++;
                    continue;
                }
                $buf = substr($buf, $p + 1);
                $off = $readFrom + $p + 1;
            }
        }
        $parsed = self::lv_parse_chunk($buf, $off, $start > 0, $forced);
        $fmt = self::lv_fmt_of(basename($e['id']));
        $cnt = ['events' => 0, 'error' => 0, 'critical' => 0, 'warning' => 0, 'notice' => 0, 'info' => 0, 'debug' => 0, 'unknown' => 0];
        foreach ($parsed['events'] as $ev) {
            $l = self::lv_light($ev, $fmt);
            $cnt['events']++;
            $cnt[$l['lv']]++;
        }
        $scanned = $size - $start;
        $per[$e['id']] = $cnt + ['scanned' => $scanned, 'size' => $size, 'partial' => $start > 0];
        foreach ($cnt as $k => $v) {
            $sum[$k] += $v;
        }
        if ($start > 0) {
            $partial++;
        }
        $scannedBytes += $scanned;
        $scannedFileBytes += $size;
    }
    $skipped = $skipLimit + $skipTime + $skipError;
    return [
        'files' => (object)$per,
        'sum' => $sum,
        'scope' => [
            'files_eligible' => $eligible,
            'files_scanned' => count($per),
            'files_partial' => $partial,
            'files_skipped_limit' => $skipLimit,
            'files_skipped_time' => $skipTime,
            'files_skipped_error' => $skipError,
            'bytes_eligible' => $eligibleBytes,
            'bytes_scanned' => $scannedBytes,
            'bytes_scanned_files' => $scannedFileBytes,
            'per_file_bytes' => LV_STATS_BYTES,
            'complete' => $skipped === 0 && $partial === 0,
        ],
    ];
}

    public static function lv_snapshot_token(string $scope, array $candidates, string $secret): string
{
    $ids = array_column($candidates, 'id');
    sort($ids, SORT_STRING);
    return hash_hmac('sha256', $scope . "\n" . implode("\n", $ids), $secret);
}

    public static function lv_delete_one(string $rel): array
{
    $v = self::lv_validate($rel);
    if (!$v['ok']) {
        $code = $v['code'];
        if ($code === 'missing') {
            return ['id' => $rel, 'status' => 'missing'];
        }
        return ['id' => $rel, 'status' => $code === 'unsafe' ? 'unsafe' : 'protected'];
    }
    if (!$v['cls']['deletable'] || $v['cls']['kind'] !== 'log' || basename($rel) === '.htaccess') {
        return ['id' => $rel, 'status' => 'protected'];
    }
    $abs = $v['abs'];
    $ok = @unlink($abs);
    clearstatcache(true, $abs);
    if ($ok && !file_exists($abs)) {
        return ['id' => $rel, 'status' => 'deleted'];
    }
    if (!file_exists($abs) && !is_link($abs)) {
        return ['id' => $rel, 'status' => 'missing'];
    }
    $dir = dirname($abs);
    if (!@is_writable($dir) || !@is_writable($abs)) {
        return ['id' => $rel, 'status' => 'permission'];
    }
    return ['id' => $rel, 'status' => 'failed'];
}

    public static function lv_delete_candidates(string $scope): array
{
    $out = [];
    foreach (self::lv_discover() as $e) {
        if ($e['kind'] !== 'log' || !$e['deletable']) {
            continue;
        }
        if ($scope === 'all' || $scope === $e['source']) {
            $out[] = $e;
        }
    }
    return $out;
}

    public static function lv_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

    public static function lv_admin_allowed(PDO $pdo, string $username): bool
{
    $q = $pdo->prepare('SELECT * FROM admin WHERE username = :u LIMIT 1');
    $q->execute([':u' => $username]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $raw = $row['iplogin'] ?? null;
    if ($raw === null || $raw === '') {
        return true;
    }
    $dec = json_decode((string)$raw, true);
    if (is_array($dec)) {
        if (in_array('*', $dec, true) || in_array('all', $dec, true) || in_array('unlimited', $dec, true)) {
            return true;
        }
        return in_array(self::lv_client_ip(), $dec, true);
    }
    if ($raw === '*' || $raw === 'all' || $raw === 'unlimited') {
        return true;
    }
    if (filter_var($raw, FILTER_VALIDATE_IP)) {
        return self::lv_client_ip() === $raw;
    }
    return false;
}
}
