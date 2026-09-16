<?php


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


    if (function_exists('fastcgi_finish_request') && PHP_SAPI !== 'cli') {
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
        return microtime(true) >= rx_cron_deadline();
    }
}


if (!function_exists('rx_cron_db_slot')) {


    function rx_cron_db_slot(?int $budget = null): bool
    {
        try {
            if ($budget === null) {
                $p = function_exists('rx_cron_read_profile') ? rx_cron_read_profile() : [];
                $budget = (int) ($p['cron_db_budget'] ?? 6);
            }
            $budget = max(1, min(64, (int) $budget));

            $dir = rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . 'dbslots';
            if (!@is_dir($dir) && !@mkdir($dir, 0775, true) && !@is_dir($dir)) {
                return true;
            }

            static $held = [];
            for ($i = 0; $i < $budget; $i++) {
                $slotFile = $dir . DIRECTORY_SEPARATOR . 'slot_' . $i . '.lock';
                $fh = @fopen($slotFile, 'c');
                if ($fh === false) {
                    continue;
                }
                if (@flock($fh, LOCK_EX | LOCK_NB)) {
                    @ftruncate($fh, 0);
                    @fwrite($fh, getmypid() . '|' . date('Y-m-d H:i:s'));
                    @fflush($fh);
                    $held[] = $fh;
                    register_shutdown_function(static function () use ($fh) {
                        @flock($fh, LOCK_UN);
                        @fclose($fh);
                    });
                    return true;
                }
                @fclose($fh);
            }
            return false;
        } catch (\Throwable $e) {
            return true;
        }
    }
}


if (!function_exists('rx_cron_boot')) {


    function rx_cron_boot(string $jobName, int $maxAgeSeconds = 120, bool $useDbSlot = true): void
    {
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

        if ($useDbSlot && function_exists('rx_cron_db_slot') && !rx_cron_db_slot()) {
            exit;
        }

        if (function_exists('rx_redis_is_active') && rx_redis_is_active()) {
            $rxRedisLockKey = 'faoxima:cronlock:' . $rxLockName;
            $rxRedisLockToken = uniqid('', true);
            $rxRedisLockResult = rx_redis_set_nx($rxRedisLockKey, $rxRedisLockToken, $maxAgeSeconds * 1000);
            if ($rxRedisLockResult === false) {
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
            return false;
        }

        if (!rx_cron_tables_ready($jobName)) {
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
            'profile'           => 'shared',
            'cron_db_budget'    => 6,
            'cron_time_budget'  => 22,
            'broadcast_workers' => 1,
            'payment_workers'   => 1,
        ];
        $rxOptCfg = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'optimization_config.php';
        if (@is_file($rxOptCfg)) {
            $rxOptData = @include $rxOptCfg;
            if (is_array($rxOptData) && !empty($rxOptData)) {
                $rxAllowed = ['cron_db_budget', 'cron_time_budget', 'broadcast_workers', 'payment_workers'];
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


if (!function_exists('rx_cron_load_payment_context')) {


    function rx_cron_load_payment_context(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

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
            $cached = [
                'setting'        => [],
                'paymentreports' => null,
                'datatextbot'    => [],
                'managePanel'    => null,
                'db_ready'       => false,
            ];
            return $cached;
        }
        if (is_file($root . '/vendor/autoload.php')) {
            require_once $root . '/vendor/autoload.php';
        }


        $hasPdo = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO;
        $hasConn = isset($GLOBALS['connect']) && $GLOBALS['connect'] !== null;
        if (!$hasPdo && !$hasConn) {
            $cached = [
                'setting'        => [],
                'paymentreports' => null,
                'datatextbot'    => [],
                'managePanel'    => null,
                'db_ready'       => false,
            ];
            return $cached;
        }
        if (function_exists('rx_cron_tables_ready') && !rx_cron_tables_ready('payment_context')) {
            $cached = [
                'setting'        => [],
                'paymentreports' => null,
                'datatextbot'    => [],
                'managePanel'    => null,
                'db_ready'       => false,
            ];
            return $cached;
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
                if (isset($datatextbot[$key])) {
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

