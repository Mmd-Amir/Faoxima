<?php

require_once __DIR__ . '/cron_task_state.php';

if (!defined('RX_CRON_INIT_LOADED')) {
    define('RX_CRON_INIT_LOADED', true);

    @date_default_timezone_set('Asia/Tehran');
    if (function_exists('putenv') && !preg_match('/(^|,)\s*putenv\s*(,|$)/', strtolower((string) ini_get('disable_functions')))) {
        @putenv('TZ=Asia/Tehran');
    }

    @ignore_user_abort(true);
    @set_time_limit(180);
    @ini_set('memory_limit', '256M');
    @ini_set('display_errors', 0);
    @ini_set('display_startup_errors', 0);
    if (!defined('FAOXIMA_LAZY_MYSQLI')) {
        define('FAOXIMA_LAZY_MYSQLI', true);
    }
}

if (!function_exists('rx_cron_release_http')) {
    function rx_cron_release_http(): void
    {
        static $released = false;
        if ($released || PHP_SAPI === 'cli') {
            return;
        }
        $released = true;
        if (function_exists('fastcgi_finish_request')) {
            if (!headers_sent()) {
                @header('Content-Type: text/plain; charset=utf-8');
                @header('Content-Length: 0');
                @header('Connection: close');
            }
            @ob_end_flush();
            @flush();
            @fastcgi_finish_request();
        }
    }
}

if (!function_exists('rx_cron_runtime_dir')) {


    function rx_cron_runtime_dir(): string
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
        if (!@is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return @is_dir($dir) ? $dir : __DIR__;
    }
}


if (!function_exists('rx_cron_deadline')) {


    function rx_cron_deadline(?int $seconds = null): float
    {
        static $deadline = null;
        if ($deadline === null || $seconds !== null) {
            if ($seconds === null) {
                $p = function_exists('rx_cron_read_profile') ? rx_cron_read_profile() : [];
                $seconds = (int) ($p['cron_time_budget'] ?? 22);
            }
            $seconds  = max(5, min(280, (int) $seconds));
            $deadline = microtime(true) + $seconds;
        }
        return $deadline;
    }
}

if (!function_exists('rx_cron_time_up')) {
    function rx_cron_time_up(): bool
    {
        if (function_exists('rx_cron_task_heartbeat')) {
            rx_cron_task_heartbeat();
        }
        return microtime(true) >= rx_cron_deadline();
    }
}


if (!function_exists('rx_cron_db_slot_dir')) {
    function rx_cron_db_slot_dir(): ?string
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime' . DIRECTORY_SEPARATOR . 'dbslots';
        if (@is_dir($dir)) {
            return $dir;
        }
        if (@mkdir($dir, 0775, true) || @is_dir($dir)) {
            return $dir;
        }
        return null;
    }
}

if (!function_exists('rx_cron_db_slot')) {


    function rx_cron_db_slot(?int $budget = null): bool
    {
        static $reported = false;
        try {
            if ($budget === null) {
                $p = function_exists('rx_cron_read_profile') ? rx_cron_read_profile() : [];
                $budget = (int) ($p['cron_db_budget'] ?? 6);
            }
            $budget = max(1, min(64, (int) $budget));

            $dir = rx_cron_db_slot_dir();
            if ($dir === null) {
                if (!$reported) {
                    $reported = true;
                    @error_log('[cron] database slot control unavailable: init_failed');
                }
                return false;
            }

            $busy = false;
            $openFailed = false;
            for ($i = 0; $i < $budget; $i++) {
                $slotFile = $dir . DIRECTORY_SEPARATOR . 'slot_' . $i . '.lock';
                $fh = @fopen($slotFile, 'c');
                if ($fh === false) {
                    $openFailed = true;
                    continue;
                }
                if (@flock($fh, LOCK_EX | LOCK_NB)) {
                    @ftruncate($fh, 0);
                    @fwrite($fh, getmypid() . '|' . date('Y-m-d H:i:s'));
                    @fflush($fh);
                    register_shutdown_function(static function () use ($fh) {
                        @flock($fh, LOCK_UN);
                        @fclose($fh);
                    });
                    return true;
                }
                $busy = true;
                @fclose($fh);
            }

            if ($openFailed && !$busy && !$reported) {
                $reported = true;
                @error_log('[cron] database slot control unavailable: open_failed');
            }
            return false;
        } catch (\Throwable $e) {
            if (!$reported) {
                $reported = true;
                @error_log('[cron] database slot control error: ' . $e->getMessage());
            }
            return false;
        }
    }
}


if (!function_exists('rx_cron_verify_web_access')) {


    function rx_cron_verify_web_access(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $rxCronAuthFile = dirname(__DIR__) . '/cron/.cron_internal_auth';
        $rxCronToken = isset($_SERVER['HTTP_X_CRON_TOKEN']) ? trim((string) $_SERVER['HTTP_X_CRON_TOKEN']) : '';
        $rxCronSource = isset($_SERVER['HTTP_X_CRON_SOURCE']) ? trim((string) $_SERVER['HTTP_X_CRON_SOURCE']) : '';
        $rxCronAuthValid = false;
        if ($rxCronToken !== '' && $rxCronSource === 'cron-orchestrator' && is_readable($rxCronAuthFile)) {
            $rxCronAuthData = trim((string) @file_get_contents($rxCronAuthFile));
            $rxCronAuthParts = explode('|', $rxCronAuthData, 2);
            if (count($rxCronAuthParts) === 2) {
                $rxCronExpectedHash = trim((string) $rxCronAuthParts[0]);
                $rxCronIssuedAt = (int) $rxCronAuthParts[1];
                if ($rxCronExpectedHash !== '' && $rxCronIssuedAt > 0 && abs(time() - $rxCronIssuedAt) <= 120) {
                    $rxCronAuthValid = hash_equals($rxCronExpectedHash, hash('sha256', $rxCronToken));
                }
            }
        }
        if (!$rxCronAuthValid) {
            http_response_code(403);
            exit('Cron request is not authorized');
        }
        rx_cron_release_http();
    }
}

if (!function_exists('rx_cron_boot')) {


    function rx_cron_boot(string $jobName, int $maxAgeSeconds = 120, bool $useDbSlot = true): void
    {
        rx_cron_verify_web_access();
        $jobName = preg_replace('/[^A-Za-z0-9_\-]/', '', $jobName);
        if ($jobName === '') {
            return;
        }
        $rxW = (int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0);
        $rxW = max(0, min(15, $rxW));
        $rxLockName = $jobName . ($rxW > 0 ? "_w{$rxW}" : '');
        $lockFile = rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . $rxLockName . '.lock';

        static $heldHandles = [];

        $fh = @fopen($lockFile, 'c');
        if ($fh === false) {
            if (is_file($lockFile) && (time() - (int) @filemtime($lockFile)) < $maxAgeSeconds) {
                exit;
            }
            @file_put_contents($lockFile, getmypid() . '|' . date('Y-m-d H:i:s'));
            register_shutdown_function(static function () use ($lockFile) {
                @unlink($lockFile);
            });
        } else {
            if (!@flock($fh, LOCK_EX | LOCK_NB)) {
                @fclose($fh);
                exit;
            }

            @ftruncate($fh, 0);
            @fwrite($fh, getmypid() . '|' . date('Y-m-d H:i:s'));
            @fflush($fh);
            $heldHandles[] = $fh;

            register_shutdown_function(static function () use ($fh) {
                @flock($fh, LOCK_UN);
                @fclose($fh);
            });
        }

        if (function_exists('rx_cron_task_attach')) {
            rx_cron_task_attach($jobName, $maxAgeSeconds);
        }

        if ($useDbSlot && function_exists('rx_cron_db_slot') && !rx_cron_db_slot()) {
            if (function_exists('rx_cron_task_defer')) {
                rx_cron_task_defer('db_slot_unavailable', 45);
            } elseif (function_exists('rx_cron_task_defer_request')) {
                rx_cron_task_defer_request('db_slot_unavailable', 45);
            }
            exit;
        }

        if (function_exists('rx_redis_is_active') && rx_redis_is_active()) {
            $rxRedisLockKey = 'faoxima:cronlock:' . $rxLockName;
            $rxRedisLockToken = uniqid('', true);
            $rxRedisLockResult = rx_redis_set_nx($rxRedisLockKey, $rxRedisLockToken, $maxAgeSeconds * 1000);
            if ($rxRedisLockResult === false) {
                if (function_exists('rx_cron_task_defer')) {
                    rx_cron_task_defer('redis_lock_busy', 60);
                } elseif (function_exists('rx_cron_task_defer_request')) {
                    rx_cron_task_defer_request('redis_lock_busy', 60);
                }
                exit;
            }
            if ($rxRedisLockResult === true) {
                register_shutdown_function(static function () use ($rxRedisLockKey, $rxRedisLockToken) {
                    rx_redis_release_lock($rxRedisLockKey, $rxRedisLockToken);
                });
            }
        }

        if (function_exists('rx_cron_deadline')) {
            rx_cron_deadline();
        }
    }
}


if (!function_exists('rx_cron_shard')) {

    function rx_cron_shard(): array
    {
        $n = (int) ($_GET['workers'] ?? $_SERVER['BROADCAST_WORKERS'] ?? 1);
        $n = max(1, min(16, $n));
        $w = (int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0);
        $w = max(0, min($n - 1, $w));
        return [$w, $n];
    }
}


if (!function_exists('rx_cron_require_or_skip')) {


    function rx_cron_require_or_skip(string $jobName, array $files): bool
    {
        foreach ($files as $file) {
            if (!is_file($file)) {
                $msg = '[' . date('Y-m-d H:i:s') . '] [cron:' . $jobName . '] missing required file: ' . $file
                     . ' — skipping this run (upload the file to fix).';
                @error_log($msg);
                @file_put_contents(rx_cron_runtime_dir() . '/_missing_files.log', $msg . PHP_EOL, FILE_APPEND);
                if (function_exists('rx_cron_task_defer')) {
                    rx_cron_task_defer('missing_required_file', 300);
                }
                return false;
            }
        }
        foreach ($files as $file) {
            require_once $file;
        }
        return true;
    }
}


if (!function_exists('rx_cron_tables_ready')) {


    function rx_cron_tables_ready(string $jobName = 'cron'): bool
    {
        global $pdo, $connect;

        static $cachedResult = null;
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $requiredTables = ['admin', 'user', 'invoice', 'setting', 'PaySetting', 'Payment_report', 'marzban_panel'];

        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query('SHOW TABLES');
                $existing = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
            } elseif (isset($connect) && $connect !== null) {
                $existing = [];
                $result = mysqli_query($connect, 'SHOW TABLES');
                if ($result) {
                    while ($row = mysqli_fetch_row($result)) {
                        $existing[] = $row[0];
                    }
                }
            } else {
                return $cachedResult = false;
            }
        } catch (\Throwable $e) {
            return $cachedResult = false;
        }

        $existingLower = array_map('strtolower', $existing);
        $missing = [];
        foreach ($requiredTables as $rxReqTable) {
            if (!in_array(strtolower($rxReqTable), $existingLower, true)) {
                $missing[] = $rxReqTable;
            }
        }
        if (empty($missing)) {
            return $cachedResult = true;
        }

        return $cachedResult = false;
    }
}


if (!function_exists('rx_cron_db_ready')) {


    function rx_cron_db_ready(string $jobName = 'cron'): bool
    {
        global $pdo, $connect;
        $pdoOk     = isset($pdo) && $pdo instanceof PDO;
        $connectOk = isset($connect) && $connect !== null;
        if (!$pdoOk && !$connectOk) {
            $marker = rx_cron_runtime_dir() . '/_db_unavailable.flag';
            $age    = is_file($marker) ? (time() - (int) @filemtime($marker)) : 99999;
            if ($age > 3600) {
                $msg = '[' . date('Y-m-d H:i:s') . '] [cron:' . $jobName . '] DB connection unavailable'
                     . ' — check config.php credentials. Skipping silently for the next hour.';
                @error_log($msg);
                @file_put_contents(rx_cron_runtime_dir() . '/_db_unavailable.log', $msg . PHP_EOL, FILE_APPEND);
                @touch($marker);
            }
            if (function_exists('rx_cron_task_defer')) {
                rx_cron_task_defer('db_unavailable', 120);
            }
            return false;
        }

        if (!rx_cron_tables_ready($jobName)) {
            if (function_exists('rx_cron_task_defer')) {
                rx_cron_task_defer('db_tables_unavailable', 120);
            }
            return false;
        }

        return true;
    }
}


if (!function_exists('rx_cron_read_profile')) {


    function rx_cron_read_profile(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $defaults = [
            'profile'              => 'shared',
            'cron_db_budget'       => 6,
            'cron_time_budget'     => 22,
            'broadcast_workers'    => 1,
            'payment_workers'      => 1,
            'cron_max_concurrency' => 2,
            'batch_size'           => 30,
        ];
        $rxOptCfg = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'optimization_config.php';
        if (@is_file($rxOptCfg)) {
            $rxOptData = @include $rxOptCfg;
            if (is_array($rxOptData) && !empty($rxOptData)) {
                $rxAllowed = ['cron_db_budget', 'cron_time_budget', 'broadcast_workers', 'payment_workers', 'cron_max_concurrency', 'batch_size'];
                $defaults  = array_merge($defaults, array_intersect_key($rxOptData, array_flip($rxAllowed)));
            }
        }
        $path = rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . 'host_profile.json';
        if (@is_file($path)) {
            $raw     = @file_get_contents($path);
            $decoded = ($raw !== false) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                return $cache = array_merge($defaults, $decoded);
            }
        }
        return $cache = $defaults;
    }
}


if (!function_exists('rx_cron_batch_size')) {

    function rx_cron_batch_size(int $cap = 50): int
    {
        $profile = function_exists('rx_cron_read_profile') ? rx_cron_read_profile() : [];
        $size    = isset($profile['batch_size']) ? (int) $profile['batch_size'] : 30;
        $size    = max(1, min($cap, $size));
        list(, $workers) = function_exists('rx_cron_shard') ? rx_cron_shard() : [0, 1];
        if ($workers > 1) {
            $size = max(1, (int) ceil($size / $workers));
        }
        return $size;
    }
}


if (!function_exists('rx_cron_bulk_cursor')) {
    function rx_cron_bulk_cursor(PDO $pdo, string $job, ?int $invoiceId = null): int
    {
        $key = '_bulk_cursor_' . $job;
        if ($invoiceId !== null) {
            $invoiceId = max(0, $invoiceId);
            $stmt = $pdo->prepare("INSERT INTO cron_runtime_state (job_key, last_run, unit, value, enabled) VALUES (?, ?, 'disabled', 1, 0) ON DUPLICATE KEY UPDATE last_run = VALUES(last_run)");
            $stmt->execute([$key, $invoiceId]);
            return $invoiceId;
        }
        $stmt = $pdo->prepare('SELECT last_run FROM cron_runtime_state WHERE job_key = ?');
        $stmt->execute([$key]);
        return max(0, (int) $stmt->fetchColumn());
    }
}

if (!function_exists('rx_cron_invoice_cursor')) {
    function rx_cron_write_invoice_cursor(string $job, string $invoiceId): bool
    {
        $file = rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . 'invoice_cursor_' . hash('sha256', $job) . '.json';
        $encoded = json_encode($invoiceId, JSON_UNESCAPED_UNICODE);
        $tmp = $file . '.tmp';
        if ($encoded === false || @file_put_contents($tmp, $encoded, LOCK_EX) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            error_log('[cron] invoice cursor persist failed: ' . $job);
            return false;
        }
        return true;
    }

    function rx_cron_invoice_cursor(string $job, ?string $invoiceId = null): string
    {
        if ($invoiceId !== null) {
            rx_cron_write_invoice_cursor($job, $invoiceId);
            return $invoiceId;
        }
        $file = rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . 'invoice_cursor_' . hash('sha256', $job) . '.json';
        if (!is_file($file)) return '';
        $raw = @file_get_contents($file, false, null, 0, 4096);
        if ($raw === false) return '';
        $cursor = json_decode($raw, true);
        return is_string($cursor) && strlen($cursor) <= 800 ? $cursor : '';
    }
}

if (!function_exists('rx_cron_bulk_invoices')) {
    function rx_cron_bulk_invoices(PDO $pdo, string $job, string $condition, array $params, int $limit): array
    {
        $cursor = rx_cron_invoice_cursor($job);
        $stmt = $pdo->prepare('SELECT invoice.* FROM invoice WHERE (' . $condition . ') AND id_invoice > ? ORDER BY id_invoice LIMIT ' . max(1, min(50, $limit)));
        $stmt->execute(array_merge($params, [$cursor]));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows && $cursor !== '') {
            $stmt->execute(array_merge($params, ['']));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $rows;
    }
}

if (!function_exists('rx_cron_payment_cursor_page')) {
    function rx_cron_payment_cursor_page(?PDO $pdo, string $job, callable $fetch): array
    {
        $after = rx_cron_invoice_cursor($job);
        $ceilingKey = 'payment_poll_ceiling_' . $job;
        $ceiling = null;
        if ($pdo !== null) {
            try {
                $ceiling = rx_cron_bulk_cursor($pdo, $ceilingKey);
                if ($ceiling === 0) {
                    $ceiling = rx_cron_bulk_cursor($pdo, $ceilingKey, (int) $pdo->query('SELECT MAX(id) FROM Payment_report')->fetchColumn());
                }
            } catch (Throwable $e) {
                error_log('[cron] payment cursor ceiling unavailable: ' . $job . ' ' . get_class($e));
                $ceiling = null;
            }
        }

        $rows = $fetch($after, $ceiling ?? '4294967295');
        if ($rows) {
            return $rows;
        }

        $previousCeiling = $ceiling;
        if ($pdo !== null && $ceiling !== null && ($after === '' || rx_cron_write_invoice_cursor($job, ''))) {
            try {
                $ceiling = (int) $pdo->query('SELECT MAX(id) FROM Payment_report')->fetchColumn();
                if ($ceiling !== $previousCeiling) {
                    rx_cron_bulk_cursor($pdo, $ceilingKey, $ceiling);
                }
            } catch (Throwable $e) {
                error_log('[cron] payment cursor ceiling refresh failed: ' . $job . ' ' . get_class($e));
                $ceiling = null;
            }
        }
        return $after !== '' || $ceiling !== $previousCeiling
            ? $fetch('', $ceiling ?? '4294967295')
            : [];
    }
}


if (!function_exists('rx_cron_load_payment_context')) {


    function rx_cron_load_payment_context(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $unavailable = [
            'setting'        => [],
            'paymentreports' => null,
            'datatextbot'    => [],
            'managePanel'    => null,
            'db_ready'       => false,
        ];

        $root = dirname(__DIR__);
        $required = [
            $root . '/config.php',
            $root . '/botapi.php',
            $root . '/panels.php',
            $root . '/function.php',
            $root . '/keyboard.php',
            $root . '/jdf.php',
        ];
        if (!rx_cron_require_or_skip('payment_context', $required)) {
            return $cached = $unavailable;
        }
        if (is_file($root . '/vendor/autoload.php')) {
            require_once $root . '/vendor/autoload.php';
        }


        $hasPdo = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO;
        $hasConn = isset($GLOBALS['connect']) && $GLOBALS['connect'] !== null;
        if (!$hasPdo && !$hasConn) {
            return $cached = $unavailable;
        }
        if (function_exists('rx_cron_tables_ready') && !rx_cron_tables_ready('payment_context')) {
            return $cached = $unavailable;
        }

        $setting = select('setting', '*');

        $reportRow = select('topicid', 'idreport', 'report', 'paymentreport', 'select');
        $paymentreports = is_array($reportRow) ? ($reportRow['idreport'] ?? null) : null;

        $datatextbotget = select('textbot', '*', null, null, 'fetchAll');
        $datatextbot = [
            'textafterpay'       => '',
            'textaftertext'      => '',
            'textmanual'         => '',
            'textselectlocation' => '',
            'text_wgdashboard'   => '',
        ];
        if (is_array($datatextbotget)) {
            foreach ($datatextbotget as $row) {
                $key = $row['id_text'] ?? '';
                if ($key !== '' && (array_key_exists($key, $datatextbot) || trim((string) ($row['text'] ?? '')) !== '')) {
                    $datatextbot[$key] = $row['text'] ?? '';
                }
            }
        }

        $cached = [
            'setting'        => $setting,
            'paymentreports' => $paymentreports,
            'datatextbot'    => $datatextbot,
            'managePanel'    => new ManagePanel(),
            'db_ready'       => true,
        ];
        return $cached;
    }
}



if (!function_exists('rx_cron_require_payment_context')) {
    function rx_cron_require_payment_context(): ?array
    {
        $context = rx_cron_load_payment_context();
        if (!empty($context['db_ready'])) {
            return $context;
        }
        if (function_exists('rx_cron_task_defer')) {
            rx_cron_task_defer('payment_context_unavailable', 180);
        }
        return null;
    }
}

if (!function_exists('rx_cron_budget_exhausted')) {
    function rx_cron_budget_exhausted(): bool
    {
        if (!rx_cron_time_up()) {
            return false;
        }
        if (function_exists('rx_cron_task_defer')) {
            rx_cron_task_defer('time_budget', 60);
        }
        return true;
    }
}

if (!function_exists('rx_cron_drain_notifications')) {
    function rx_cron_drain_notifications(array &$pending, int $limit, callable $deliver): void
    {
        $attempted = 0;
        foreach (array_keys($pending) as $key) {
            if (rx_cron_time_up() || $attempted >= $limit) {
                break;
            }
            if (($pending[$key]['state'] ?? 'uncertain') === 'pending' && (int) ($pending[$key]['next_attempt_at'] ?? 0) <= time()) {
                $attempted++;
                $deliver($key);
            }
        }
    }

    function rx_cron_deliver_pending_notification(array &$pending, $key, callable $persist, callable $send, bool $checkBudget, bool $recordSentTime): bool
    {
        $entry = $pending[$key] ?? null;
        if (is_array($entry) && ($entry['state'] ?? '') === 'sent') {
            return true;
        }
        if (!is_array($entry) || ($entry['state'] ?? 'uncertain') !== 'pending' || (int) ($entry['next_attempt_at'] ?? 0) > time() || ($checkBudget && rx_cron_time_up())) {
            return false;
        }
        $persistState = static function (string $state) use ($persist): void {
            try {
                $persist();
            } catch (Throwable $e) {
                error_log('[cron:notification] could not persist ' . $state . ': ' . get_class($e));
                throw $e;
            }
        };
        if (!rx_cron_catchup_allowed((int) ($entry['created_at'] ?? 0))) {
            $pending[$key]['state'] = 'expired';
            $pending[$key]['next_attempt_at'] = 0;
            $pending[$key]['last_error'] = 'catchup_window_elapsed';
            $persistState('expired');
            return false;
        }
        $pending[$key]['state'] = 'sending';
        $pending[$key]['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;
        $persistState('sending');
        try {
            $result = $send($entry);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'description' => $e->getMessage()];
        }
        if (is_array($result) && ($result['ok'] ?? false) === true) {
            $pending[$key]['state'] = 'sent';
            if ($recordSentTime) {
                $pending[$key]['sent_at'] = time();
            }
            $persistState('sent');
            return true;
        }
        $code = (int) ($result['error_code'] ?? 0);
        $pending[$key]['state'] = $code === 429 && $pending[$key]['attempts'] < 5 ? 'pending' : ($code >= 400 && $code < 500 && $code !== 408 ? 'failed' : 'uncertain');
        $pending[$key]['next_attempt_at'] = time() + max(60, (int) ($result['parameters']['retry_after'] ?? 300));
        $pending[$key]['last_error'] = substr((string) ($result['description'] ?? 'no_response'), 0, 200);
        $persistState($pending[$key]['state']);
        return false;
    }
}

if (!function_exists('rx_cron_run_poll_batch')) {
    function rx_cron_run_poll_batch(array $rows, callable $poll, callable $onError, callable $advance): void
    {
        foreach ($rows as $row) {
            if (rx_cron_budget_exhausted()) {
                break;
            }
            try {
                $poll($row);
            } catch (Throwable $e) {
                $onError($row, $e);
            } finally {
                $advance($row);
            }
        }
    }
}

if (!function_exists('rx_cron_bulk_service_status')) {
    function rx_cron_bulk_service_status(PDO $pdo, ManagePanel $managePanel, string $job, string $eligible, string $request, string $providerBefore, string $providerAfter, string $invoiceAfter): bool
    {
        $limit = rx_cron_batch_size(30);
        $finished = $pdo->prepare("SELECT id FROM user WHERE checkstatus = ? AND NOT EXISTS (SELECT 1 FROM invoice WHERE id_user = user.id AND ($eligible)) ORDER BY id LIMIT " . (int) $limit);
        $finished->execute([$request]);
        $finishedUsers = $finished->fetchAll(PDO::FETCH_COLUMN);
        $clear = $pdo->prepare("UPDATE user SET checkstatus = '0' WHERE id = ? AND checkstatus = ? AND NOT EXISTS (SELECT 1 FROM invoice WHERE id_user = user.id AND ($eligible))");
        foreach ($finishedUsers as $userId) {
            if (rx_cron_time_up()) {
                break;
            }
            $clear->execute([$userId, $request]);
        }
        if (rx_cron_budget_exhausted()) {
            return false;
        }

        $invoices = rx_cron_bulk_invoices($pdo, $job, $eligible . ' AND id_user IN (SELECT id FROM user WHERE checkstatus = ?)', [$request], $limit);
        rx_cron_run_poll_batch(
            $invoices,
            static function (array $invoice) use ($pdo, $managePanel, $request, $providerBefore, $providerAfter, $invoiceAfter): void {
                $requested = $pdo->prepare('SELECT checkstatus FROM user WHERE id = ?');
                $requested->execute([$invoice['id_user']]);
                if ((string) $requested->fetchColumn() !== $request) {
                    return;
                }
                if (rx_cron_service_target_reached($managePanel, $invoice, $providerBefore, $providerAfter)) {
                    update('invoice', 'Status', $invoiceAfter, 'id_invoice', $invoice['id_invoice']);
                }
            },
            static function (array $invoice, Throwable $e) use ($job): void {
                error_log('[cron:' . $job . '] invoice ' . $invoice['id_invoice'] . ': ' . $e->getMessage());
            },
            static function (array $invoice) use ($job): void {
                rx_cron_invoice_cursor($job, (string) $invoice['id_invoice']);
            }
        );
        return true;
    }
}

if (!function_exists('rx_cron_service_target_reached')) {
    function rx_cron_service_target_reached(ManagePanel $managePanel, array $invoice, string $before, string $after): bool
    {
        $current = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if (($current['paused_by'] ?? '') === 'credit') {
            return false;
        }
        $status = is_array($current) ? (string) ($current['status'] ?? '') : '';
        if ($status === $after) {
            return true;
        }
        if ($status !== $before || rx_cron_time_up()) {
            return false;
        }
        $managePanel->Change_status($invoice['username'], $invoice['Service_location']);
        $verified = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
        return is_array($verified) && ($verified['status'] ?? '') === $after;
    }
}

