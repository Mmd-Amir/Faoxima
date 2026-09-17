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

if (!function_exists('rx_perf_enabled')) {
    function rx_perf_enabled(): bool
    {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }
        $flag = REFACTORED_LOG_DIR . DIRECTORY_SEPARATOR . 'perf_disabled.flag';
        $enabled = !is_file($flag);
        return $enabled;
    }
}

if (!function_exists('rx_perf_start')) {
    function rx_perf_start(string $kind): void
    {
        if (!rx_perf_enabled()) {
            return;
        }
        $GLOBALS['__rx_perf'] = [
            'id'     => substr(bin2hex(random_bytes(4)), 0, 8),
            'kind'   => $kind,
            'start'  => microtime(true),
            'marks'  => [],
            'queries_slow' => 0,
            'queries_total' => 0,
        ];
        register_shutdown_function('rx_perf_flush');
    }
}

if (!function_exists('rx_perf_mark')) {
    function rx_perf_mark(string $label): void
    {
        if (!rx_perf_enabled() || !isset($GLOBALS['__rx_perf'])) {
            return;
        }
        $now = microtime(true);
        $GLOBALS['__rx_perf']['marks'][] = [
            'label' => $label,
            't'     => round(($now - $GLOBALS['__rx_perf']['start']) * 1000, 1),
        ];
    }
}

if (!function_exists('rx_perf_note_query')) {
    function rx_perf_note_query(float $durationMs, string $sqlSnippet): void
    {
        if (!rx_perf_enabled() || !isset($GLOBALS['__rx_perf'])) {
            return;
        }
        $GLOBALS['__rx_perf']['queries_total']++;
        if ($durationMs >= 300.0) {
            $GLOBALS['__rx_perf']['queries_slow']++;
            $line = '[' . date('Y-m-d H:i:s') . '] [' . $GLOBALS['__rx_perf']['id'] . '] SLOW_QUERY '
                . round($durationMs, 1) . 'ms | ' . substr(preg_replace('/\s+/', ' ', $sqlSnippet), 0, 300) . PHP_EOL;
            @file_put_contents(REFACTORED_LOG_DIR . DIRECTORY_SEPARATOR . 'perf_slow_queries.log', $line, FILE_APPEND | LOCK_EX);
        }
    }
}

if (!function_exists('rx_perf_flush')) {
    function rx_perf_flush(): void
    {
        if (!rx_perf_enabled() || !isset($GLOBALS['__rx_perf'])) {
            return;
        }
        $p = $GLOBALS['__rx_perf'];
        $totalMs = round((microtime(true) - $p['start']) * 1000, 1);

        $update = $GLOBALS['update'] ?? null;
        $fromId = 0;
        $kindDetail = '';
        if (is_array($update)) {
            $fromId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? 0;
            if (isset($update['callback_query']['data'])) {
                $kindDetail = 'cb:' . substr((string) $update['callback_query']['data'], 0, 40);
            } elseif (isset($update['message']['text'])) {
                $kindDetail = 'msg:' . substr((string) $update['message']['text'], 0, 40);
            }
        }

        $marksStr = '';
        $prevT = 0.0;
        foreach ($p['marks'] as $m) {
            $step = round($m['t'] - $prevT, 1);
            $marksStr .= $m['label'] . '=+' . $step . 'ms(' . $m['t'] . 'ms) ';
            $prevT = $m['t'];
        }

        $line = '[' . date('Y-m-d H:i:s') . '] [' . $p['id'] . '] '
            . 'kind=' . $p['kind']
            . ' total=' . $totalMs . 'ms'
            . ' from_id=' . $fromId
            . ' detail=' . $kindDetail
            . ' queries=' . $p['queries_total']
            . ' slow_queries=' . $p['queries_slow']
            . ' mem=' . round(memory_get_peak_usage(true) / 1048576, 1) . 'MB'
            . ' | ' . trim($marksStr)
            . PHP_EOL;

        @file_put_contents(REFACTORED_LOG_DIR . DIRECTORY_SEPARATOR . 'perf.log', $line, FILE_APPEND | LOCK_EX);

        if ($totalMs >= 2000.0) {
            @file_put_contents(REFACTORED_LOG_DIR . DIRECTORY_SEPARATOR . 'perf_slow_requests.log', $line, FILE_APPEND | LOCK_EX);
        }

        unset($GLOBALS['__rx_perf']);
    }
}

if (!class_exists('RxPerfPDOStatement')) {
    class RxPerfPDOStatement extends PDOStatement
    {
        protected function __construct()
        {
        }

        public function execute($params = null): bool
        {
            $start = microtime(true);
            $result = parent::execute($params);
            $durationMs = (microtime(true) - $start) * 1000;
            if (function_exists('rx_perf_note_query')) {
                rx_perf_note_query($durationMs, $this->queryString);
            }
            return $result;
        }
    }
}

if (!function_exists('rx_perf_wrap_pdo')) {
    function rx_perf_wrap_pdo(PDO $pdo): void
    {
        if (!rx_perf_enabled()) {
            return;
        }
        try {
            $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['RxPerfPDOStatement', []]);
        } catch (\Throwable $e) {
        }
    }
}
