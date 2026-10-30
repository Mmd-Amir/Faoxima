<?php


ignore_user_abort(true);
@set_time_limit(60);
@ini_set('memory_limit', '128M');

date_default_timezone_set('Asia/Tehran');
if (!defined('FAOXIMA_LAZY_MYSQLI')) {
    define('FAOXIMA_LAZY_MYSQLI', true);
}
if (function_exists('putenv') && !preg_match('/(^|,)\s*putenv\s*(,|$)/', strtolower((string) ini_get('disable_functions')))) {
    @putenv('TZ=Asia/Tehran');
}


$rxTaskStateLib = dirname(__DIR__) . '/cronbot/cron_task_state.php';
if (is_file($rxTaskStateLib)) {
    try {
        require_once $rxTaskStateLib;
    } catch (Throwable $e) {
    }
}

$rxCronHealthLib = dirname(__DIR__) . '/lib/CronHealth.php';
if (is_file($rxCronHealthLib)) {
    try {
        require_once $rxCronHealthLib;
    } catch (Throwable $e) {
    }
}
if (function_exists('rx_cron_health_orch_begin')) {
    rx_cron_health_orch_begin();
}
$rxHealthStage = static function (string $name, string $state): void {
    if (function_exists('rx_cron_health_orch_stage')) {
        rx_cron_health_orch_stage($name, $state);
    }
};

$lockFile = __DIR__ . '/cron.lock';
$lockHandle = @fopen($lockFile, 'c');
if ($lockHandle === false || !@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $rxHealthStage('lock', $lockHandle === false ? 'open_failed' : 'busy');
    if (is_resource($lockHandle)) {
        @fclose($lockHandle);
    }
    echo "BUSY\n";
    exit;
}
$rxHealthStage('lock', 'acquired');
@ftruncate($lockHandle, 0);
@fwrite($lockHandle, getmypid() . '|' . date('Y-m-d H:i:s'));
@fflush($lockHandle);
$rxInternalAuthFile = __DIR__ . '/.cron_internal_auth';
try {
    $rxInternalAuthToken = bin2hex(random_bytes(32));
} catch (Throwable $e) {
    $rxInternalAuthToken = hash('sha256', uniqid('', true) . microtime(true) . getmypid());
}
$rxInternalAuthPayload = hash('sha256', $rxInternalAuthToken) . '|' . time();
@file_put_contents($rxInternalAuthFile, $rxInternalAuthPayload, LOCK_EX);
@chmod($rxInternalAuthFile, 0600);
register_shutdown_function(static function () use ($lockHandle) {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
});


$functionBootstrap = __DIR__ . '/function.php';
if (!is_readable($functionBootstrap)) {
    $functionBootstrap = __DIR__ . '/../function.php';
}

$bootstrapLoaded = false;
$rxCronBootstrapMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_cron_bootstrap_fail.flag';
if (is_readable($functionBootstrap)) {
    try {
        require_once $functionBootstrap;
        $bootstrapLoaded = true;
        $rxHealthStage('bootstrap', 'ok');
        if (is_file($rxCronBootstrapMarker)) {
            @unlink($rxCronBootstrapMarker);
        }
    } catch (Throwable $e) {
        $rxHealthStage('bootstrap', 'failed');
        $rxLogIt = true;
        if (is_file($rxCronBootstrapMarker) && (time() - (int) @filemtime($rxCronBootstrapMarker)) < 3600) {
            $rxLogIt = false;
        }
        if ($rxLogIt) {
            error_log('[cron.php] bootstrap failed: ' . $e->getMessage());
            @touch($rxCronBootstrapMarker);
        }
        echo "SKIP (bootstrap unavailable)\n";
        exit;
    }
}

if (!$bootstrapLoaded) {
    $rxHealthStage('bootstrap', 'missing');
    echo "SKIP (bootstrap unavailable)\n";
    exit;
}



if (isset($conn) && $conn instanceof mysqli) {
    try { $conn->close(); } catch (Throwable $e) {}
} elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    try { $mysqli->close(); } catch (Throwable $e) {}
} elseif (isset($db) && $db instanceof PDO) {
    $db = null;
}
if (function_exists('mysqli_close') && isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
    try { @mysqli_close($GLOBALS['conn']); } catch (Throwable $e) {}
}


$host = null;
if (isset($domainhosts) && is_string($domainhosts) && trim($domainhosts) !== '') {
    $host = $domainhosts;
}
if ($host === null || trim((string) $host) === '') {
    $host = $_SERVER['HTTP_HOST'] ?? null;
}
if ($host === null || trim((string) $host) === '') {
    $host = 'localhost';
}

$hostConfig = $host;
if (!preg_match('~^https?://~i', $hostConfig)) {
    $hostConfig = 'https://' . ltrim($hostConfig);
}

$parts    = parse_url($hostConfig);
$scheme   = $parts['scheme'] ?? 'https';
$hostOnly = $parts['host']   ?? 'localhost';
$basePath = rtrim($parts['path'] ?? '', '/');

$buildCronUrl = static function (string $script) use ($scheme, $hostOnly, $basePath): string {
    $script = ltrim($script, '/');
    return $scheme . '://' . $hostOnly . $basePath . '/cronbot/' . $script;
};

$rxDetectLoopback = static function (): ?array {
    $cacheFile = __DIR__ . '/loopback_port.cache';
    if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < 300) {
        $cached = @json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) || $cached === null) {
            return is_array($cached) ? $cached : null;
        }
    }
    foreach ([80 => 'http', 443 => 'https', 8080 => 'http'] as $port => $proto) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
        if ($fp !== false) {
            fclose($fp);
            $result = ['port' => $port, 'scheme' => $proto];
            @file_put_contents($cacheFile, json_encode($result));
            return $result;
        }
    }
    @file_put_contents($cacheFile, json_encode(null));
    return null;
};

if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', dirname(__DIR__));
}

$pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
if (!($pdo instanceof PDO)) {
    $rxHealthStage('db', 'failed');
    echo "SKIP (db unavailable)\n";
    exit;
}
$rxHealthStage('db', 'ok');

$runtimeState = [];
if (function_exists('loadCronRuntimeState')) {
    try {
        $runtimeState = loadCronRuntimeState($pdo);
    } catch (Throwable $e) {
        $runtimeState = [];
    }
}

$rxHealthTrace = function_exists('rx_cron_health_orch_attach_db')
    ? rx_cron_health_orch_attach_db($pdo)
    : ['traced' => false, 'claimed' => [], 'active' => false];


$rxCronbotDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cronbot';
if (is_dir($rxCronbotDir)) {
    foreach ((array) @glob($rxCronbotDir . '/*.lock') as $rxStale) {
        if ((time() - (int) @filemtime($rxStale)) <= 120) { continue; }
        $rxStaleHandle = @fopen($rxStale, 'c');
        if ($rxStaleHandle === false) { continue; }
        if (@flock($rxStaleHandle, LOCK_EX | LOCK_NB)) {
            @unlink($rxStale);
            @flock($rxStaleHandle, LOCK_UN);
        }
        @fclose($rxStaleHandle);
    }
    foreach (['_db_unavailable.flag', '_db_unavailable.log', '_missing_files.log'] as $rxLegacy) {
        $rxLegacyPath = $rxCronbotDir . DIRECTORY_SEPARATOR . $rxLegacy;
        if (is_file($rxLegacyPath) && (time() - (int) @filemtime($rxLegacyPath)) > 120) { @unlink($rxLegacyPath); }
    }
    if (is_dir($rxCronbotDir . DIRECTORY_SEPARATOR . '.dbslots')) {
        foreach ((array) @glob($rxCronbotDir . '/.dbslots/*') as $rxSlot) {
            $rxSlotHandle = @fopen($rxSlot, 'c');
            if ($rxSlotHandle === false) { continue; }
            if (@flock($rxSlotHandle, LOCK_EX | LOCK_NB)) {
                @unlink($rxSlot);
                @flock($rxSlotHandle, LOCK_UN);
            }
            @fclose($rxSlotHandle);
        }
        @rmdir($rxCronbotDir . DIRECTORY_SEPARATOR . '.dbslots');
    }
}

$jobHours = [];
$rxHostProfile = [];
if (function_exists('rx_host_profile')) {
    try { $rxHostProfile = rx_host_profile(); } catch (Throwable $e) { $rxHostProfile = []; }
}
$rxBroadcastWorkers = max(1, (int) ($rxHostProfile['broadcast_workers'] ?? 3));
$rxPaymentWorkers   = max(1, (int) ($rxHostProfile['payment_workers'] ?? 2));
$rxSharedProfile    = (($rxHostProfile['profile'] ?? 'shared') === 'shared');
$jobWorkerCounts = [];
try {
    $rxSettingRow = function_exists('select') ? select('setting', '*') : null;
    if (is_array($rxSettingRow)) {
        $jobHours['lottery']   = max(0, min(23, (int) ($rxSettingRow['lottery_hour']   ?? 0)));
        $jobHours['statusday'] = max(0, min(23, (int) ($rxSettingRow['statusday_hour'] ?? 0)));
        if (isset($rxSettingRow['broadcast_workers'])) {
            $rxBroadcastWorkers = max(1, min(8, (int) $rxSettingRow['broadcast_workers']));
        }
        if (isset($rxSettingRow['payment_workers'])) {
            $rxPaymentWorkers = max(1, min(8, (int) $rxSettingRow['payment_workers']));
        }
    }
} catch (Throwable $e) {}
if ($rxSharedProfile) {
    $rxBroadcastWorkers = 1;
    $rxPaymentWorkers = 1;
    @set_time_limit(300);
}
if (!empty($rxHostProfile['manual_workers']) && function_exists('rx_cron_worker_counts')) {
    $rxManualWorkers = rx_cron_worker_counts($rxHostProfile, is_array($rxSettingRow) ? $rxSettingRow : []);
    $rxBroadcastWorkers = $rxManualWorkers['broadcast_workers'];
    $rxPaymentWorkers = $rxManualWorkers['payment_workers'];
}
$jobWorkerCounts = [
    'sendmessage'   => $rxBroadcastWorkers,
    'notifications' => $rxBroadcastWorkers,
    'plisio'        => $rxPaymentWorkers,
    'croncard'      => $rxPaymentWorkers,
];

$rxMaxConcurrency = $rxSharedProfile ? 2 : 3;
if (isset($rxHostProfile['cron_max_concurrency'])) {
    $rxMaxConcurrency = (int) $rxHostProfile['cron_max_concurrency'];
}
try {
    $rxOptConfigFile = dirname(__DIR__) . '/optimization_config.php';
    if (is_file($rxOptConfigFile)) {
        $rxOptConfigData = @include $rxOptConfigFile;
        if (is_array($rxOptConfigData) && isset($rxOptConfigData['cron_max_concurrency'])) {
            $rxMaxConcurrency = (int) $rxOptConfigData['cron_max_concurrency'];
        }
    }
} catch (Throwable $e) {}
if (isset($rxSettingRow['cron_max_concurrency']) && (int) $rxSettingRow['cron_max_concurrency'] > 0) {
    $rxMaxConcurrency = (int) $rxSettingRow['cron_max_concurrency'];
}
$rxMaxConcurrency = max(1, min(8, $rxMaxConcurrency));
$rxTaskMaxAttempts = 3;
$rxTaskDispatchLease = 120;
$rxTaskStateEnabled = false;
if (function_exists('rx_cron_task_ensure_table')) {
    try {
        $rxTaskStateEnabled = rx_cron_task_ensure_table($pdo);
    } catch (Throwable $e) {
        $rxTaskStateEnabled = false;
    }
}

$now       = time();
$minute    = (int) date('i', $now);
$hour      = (int) date('G', $now);
$dayOfYear = (int) date('z', $now);

if ($hour === 3 && $minute === 0) {
    try {
        $pdo->exec("DELETE FROM processed_updates WHERE processed_at < UNIX_TIMESTAMP(NOW() - INTERVAL 1 DAY)");
    } catch (Throwable $e) {}
}


$shouldRun = static function (string $jobKey, array $schedule, int $minute, int $hour, int $dayOfYear, int $now, array $runtimeState, int $targetHour = 0): bool {
    $unit  = strtolower((string) ($schedule['unit'] ?? 'minute'));
    $value = max(1, (int) ($schedule['value'] ?? 1));
    if ($unit === 'disabled') {
        return false;
    }
    $aligned = false;
    if ($unit === 'minute') {
        $aligned = ($minute % $value === 0);
    } elseif ($unit === 'hour') {
        $aligned = ($minute === 0 && $hour % $value === 0);
    } elseif ($unit === 'day') {
        
        $aligned = ($minute === 0 && $hour === $targetHour && $dayOfYear % $value === 0);
    }
    if (!$aligned) {
        return false;
    }
    $lastRun = isset($runtimeState[$jobKey]) ? (int) $runtimeState[$jobKey] : 0;
    if ($lastRun > 0 && ($now - $lastRun) < 25) {
        return false;
    }
    return true;
};


$dispatchAsync = static function (array $tasks, bool $useLoopback) use ($rxInternalAuthToken): array {
    $result = ['success' => [], 'failed' => []];
    if (empty($tasks)) {
        return $result;
    }
    if (!function_exists('curl_multi_init')) {
        $result['failed'] = $tasks;
        return $result;
    }
    $multi = curl_multi_init();
    if ($multi === false) {
        $result['failed'] = $tasks;
        return $result;
    }
    $handles = [];
    foreach ($tasks as $task) {
        $url = (string) ($task['url'] ?? '');
        if ($url === '') {
            continue;
        }
        $bustedUrl = $url . (strpos($url, '?') === false ? '?' : '&') . '_t=' . microtime(true);
        $ch = curl_init($bustedUrl);
        if ($ch === false) {
            continue;
        }
        $rxResolveHost = parse_url($url, PHP_URL_HOST);
        $curlOpts = [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_NOSIGNAL        => true,
            CURLOPT_CONNECTTIMEOUT_MS => 1500,
            CURLOPT_TIMEOUT_MS      => 4000,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_FORBID_REUSE    => true,
            CURLOPT_FRESH_CONNECT   => true,
            CURLOPT_HTTPHEADER      => [
                'Cache-Control: no-cache, no-store, must-revalidate, max-age=0',
                'Pragma: no-cache',
                'Expires: 0',
                'X-Cron-Source: cron-orchestrator',
                'X-Cron-Token: ' . $rxInternalAuthToken,
                'X-Cron-Task: ' . (string) ($task['task_key'] ?? ''),
                'X-Cron-Task-Token: ' . (string) ($task['token'] ?? ''),
                'Connection: close',
            ],
            CURLOPT_USERAGENT       => 'CronOrchestrator/2.0 (+internal)',
        ];
        if ($useLoopback
            && is_string($rxResolveHost) && $rxResolveHost !== '' && $rxResolveHost !== '127.0.0.1' && $rxResolveHost !== 'localhost') {
            $curlOpts[CURLOPT_RESOLVE] = [
                $rxResolveHost . ':443:127.0.0.1',
                $rxResolveHost . ':80:127.0.0.1',
            ];
        }
        curl_setopt_array($ch, $curlOpts);
        curl_multi_add_handle($multi, $ch);
        $handles[(int) $ch] = ['handle' => $ch, 'task' => $task];
    }
    if (empty($handles)) {
        curl_multi_close($multi);
        $result['failed'] = $tasks;
        return $result;
    }

    $deadline = microtime(true) + 5.0;
    do {
        $status = curl_multi_exec($multi, $running);
        if ($status === CURLM_OK && $running > 0) {
            curl_multi_select($multi, 0.2);
        }
    } while ($running > 0 && microtime(true) < $deadline);

    foreach ($handles as $item) {
        $ch = $item['handle'];
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        $isFail = ($err !== '' || $code < 200 || $code >= 400);
        if ($isFail) {
            $result['failed'][] = $item['task'];
        } else {
            $result['success'][] = $item['task'];
        }
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);
    return $result;
};

$rxCanExec = static function (): bool {
    if (!function_exists('exec')) {
        return false;
    }

    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
    return !in_array('exec', $disabled, true);
};

$rxResolveCliPhp = static function () use ($rxCanExec): ?string {
    if (!$rxCanExec()) {
        return null;
    }
    static $resolved = false;
    static $cliPhp = null;

    if ($resolved) {
        return $cliPhp;
    }
    $resolved = true;

    if (php_sapi_name() === 'cli' && defined('PHP_BINARY') && PHP_BINARY && is_file(PHP_BINARY)) {
        $cliPhp = PHP_BINARY;
        return $cliPhp;
    }

    $candidates = [
        '/usr/local/bin/php',
        '/usr/bin/php',
        '/opt/cpanel/ea-php85/root/usr/bin/php',
        '/opt/cpanel/ea-php84/root/usr/bin/php',
        '/opt/cpanel/ea-php83/root/usr/bin/php',
        '/opt/cpanel/ea-php82/root/usr/bin/php',
        '/opt/cpanel/ea-php81/root/usr/bin/php',
        'php',
    ];

    $seen = [];
    foreach ($candidates as $candidate) {
        if (isset($seen[$candidate])) {
            continue;
        }
        $seen[$candidate] = true;

        if (strpos($candidate, '/') !== false && !is_executable($candidate)) {
            continue;
        }

        $output = [];
        $code = 1;
        @exec(escapeshellarg($candidate) . ' -r ' . escapeshellarg('echo PHP_SAPI;') . (DIRECTORY_SEPARATOR === '\\' ? ' 2>NUL' : ' 2>/dev/null'), $output, $code);

        if ($code === 0 && trim(implode("
", $output)) === 'cli') {
            $cliPhp = $candidate;
            return $cliPhp;
        }
    }

    return null;
};

$rxDispatchCli = static function (string $script, int $worker, int $workers, bool $background, string $taskKey = '', string $token = '') use ($rxResolveCliPhp, $rxCanExec): bool {
    if (!$rxCanExec()) {
        return false;
    }
    $file = realpath(__DIR__ . '/../cronbot/' . ltrim($script, '/'));
    if ($file === false || !is_file($file)) {
        return false;
    }

    $phpBin = $rxResolveCliPhp();
    if ($phpBin === null) {
        return false;
    }

    if (!preg_match('/^[A-Za-z0-9_\-]{1,140}:[0-9]{1,3}:[0-9]{1,3}$/', $taskKey)) {
        $taskKey = '';
    }
    if (!preg_match('/^[a-f0-9]{32,64}$/', $token)) {
        $token = '';
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        $envParts = [
            'set "BROADCAST_WORKER_ID=' . $worker . '"',
            'set "BROADCAST_WORKERS=' . $workers . '"',
        ];
        if ($taskKey !== '') {
            $envParts[] = 'set "RX_CRON_TASK=' . $taskKey . '"';
            $envParts[] = 'set "RX_CRON_TASK_TOKEN=' . $token . '"';
        }
        $cmd = implode(' && ', $envParts) . ' && ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($file);
        if ($background) {
            $handle = @popen('start /B cmd /C "' . $cmd . '"', 'r');
            if (is_resource($handle)) {
                @pclose($handle);
                return true;
            }
            return false;
        }

        $exitCode = 1;
        @exec($cmd . ' > NUL 2>&1', $unused, $exitCode);
        return $exitCode === 0;
    }

    $env = 'BROADCAST_WORKER_ID=' . $worker . ' BROADCAST_WORKERS=' . $workers;
    if ($taskKey !== '') {
        $env .= ' RX_CRON_TASK=' . escapeshellarg($taskKey) . ' RX_CRON_TASK_TOKEN=' . escapeshellarg($token);
    }
    $cmd = $env . ' ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($file);

    if ($background) {
        $exitCode = 1;
        @exec($cmd . ' > /dev/null 2>&1 &', $unused, $exitCode);
        return $exitCode === 0;
    }

    $exitCode = 1;
    @exec($cmd . ' > /dev/null 2>&1', $unused, $exitCode);
    return $exitCode === 0;
};

$rxIsCli = (php_sapi_name() === 'cli');
$rxCliDispatched = 0;

$rxLegacyLoopbackFlag = __DIR__ . '/use_loopback.flag';
if (is_file($rxLegacyLoopbackFlag)) {
    @unlink($rxLegacyLoopbackFlag);
}


$dueTasks = [];
$rxSuccessfulDispatches = 0;
$rxSkippedMissingScripts = 0;

$rxCronScriptExists = static function (string $script): bool {
    $script = ltrim($script, '/');
    if ($script === '' || strpos($script, '..') !== false) {
        return false;
    }
    return is_file(__DIR__ . '/../cronbot/' . $script);
};

$rxReportMissingCronScript = static function (string $key, string $script): void {
    $script = ltrim($script, '/');
    $marker = rtrim(sys_get_temp_dir(), '/' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'faoxima_cron_missing_' . md5(__DIR__ . '|' . $key . '|' . $script);
    if (is_file($marker) && (time() - (int) @filemtime($marker)) < 86400) {
        return;
    }
    @touch($marker);
    error_log(sprintf('[cron] skipped job "%s": script cronbot/%s does not exist', $key, $script));
};

$rxMarkJobRun = static function (PDO $pdo, string $key, int $now, array &$runtimeState) : void {
    if (function_exists('setCronJobLastRun')) {
        try {
            setCronJobLastRun($pdo, $key, $now);
        } catch (Throwable $e) {
        }
    }
    $runtimeState[$key] = $now;
};

$rxDispatchHttpTasks = static function (array $tasks, bool $useLoopback) use ($dispatchAsync, $rxDetectLoopback): array {
    if (empty($tasks)) {
        return ['success' => [], 'failed' => []];
    }

    if ($useLoopback) {
        $loopback = $rxDetectLoopback();
        if (!is_array($loopback)) {
            return ['success' => [], 'failed' => $tasks];
        }
        foreach ($tasks as $index => $task) {
            $parts = parse_url((string) ($task['url'] ?? ''));
            if (!is_array($parts)) {
                continue;
            }
            $path = $parts['path'] ?? '/';
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            $tasks[$index]['url'] = $loopback['scheme'] . '://127.0.0.1:' . $loopback['port'] . $path . $query;
        }
    }

    return $dispatchAsync($tasks, $useLoopback);
};

$rxValidTasks = [];
$rxTaskKeyFor = static function (string $jobKey, int $workers, int $worker): string {
    return function_exists('rx_cron_task_key') ? rx_cron_task_key($jobKey, $workers, $worker) : $jobKey . ':' . $workers . ':' . $worker;
};

if ($bootstrapLoaded && function_exists('getCronJobDefinitions')) {
    $definitions = getCronJobDefinitions();
    $schedules   = function_exists('loadCronSchedules') ? loadCronSchedules() : [];
    $rxOccurrence = (int) (floor($now / 60) * 60);
    $rxQueuedWorkerCounts = [];
    $rxQueuedJobKeys = [];
    $rxAvailability = [];
    $rxProbeWork = static function (string $jobKey) use (&$rxAvailability): ?bool {
        if (array_key_exists($jobKey, $rxAvailability)) {
            return $rxAvailability[$jobKey];
        }
        $state = null;
        if (function_exists('rx_cron_job_work_available')) {
            try {
                $state = rx_cron_job_work_available($jobKey);
            } catch (Throwable $e) {
                $state = null;
            }
        }
        $rxAvailability[$jobKey] = $state;
        return $state;
    };
    if ($rxTaskStateEnabled) {
        try {
            $rxWorkerRows = $pdo->query("SELECT job_key, workers FROM cron_task_state WHERE state IN ('pending','claimed','running') ORDER BY scheduled_for ASC, task_key ASC");
            if ($rxWorkerRows === false) {
                throw new RuntimeException('Cannot read queued worker counts');
            }
            foreach ($rxWorkerRows->fetchAll(PDO::FETCH_ASSOC) as $rxWorkerRow) {
                $rxWorkerJob = (string) ($rxWorkerRow['job_key'] ?? '');
                if ($rxWorkerJob !== '') {
                    $rxQueuedJobKeys[$rxWorkerJob] = true;
                }
                if (isset($jobWorkerCounts[$rxWorkerJob]) && !isset($rxQueuedWorkerCounts[$rxWorkerJob])) {
                    $rxQueuedWorkerCounts[$rxWorkerJob] = max(1, min(8, (int) $rxWorkerRow['workers']));
                }
            }
        } catch (Throwable $e) {
            error_log('[cron] queued worker counts unavailable');
            echo "SKIP (task queue unavailable)\n";
            exit;
        }
    }

    foreach ($definitions as $key => $definition) {
        if (empty($definition['script'])) {
            continue;
        }

        if (!$rxCronScriptExists((string) $definition['script'])) {
            $rxSkippedMissingScripts++;
            $rxReportMissingCronScript((string) $key, (string) $definition['script']);
            continue;
        }

        $defaultConfig = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
        $schedule      = $schedules[$key] ?? $defaultConfig;
        if (strtolower((string) ($schedule['unit'] ?? 'minute')) === 'disabled') {
            continue;
        }

        $rxDue = $shouldRun($key, $schedule, $minute, $hour, $dayOfYear, $now, $runtimeState, $jobHours[$key] ?? 0);
        $rxSkipNoWork = false;
        if ($rxDue || isset($rxQueuedJobKeys[$key])) {
            $rxSkipNoWork = ($rxProbeWork((string) $key) === false);
        }
        $rxRequestedN = max(1, (int) ($jobWorkerCounts[$key] ?? 1));
        $rxN = max(1, (int) ($rxQueuedWorkerCounts[$key] ?? $rxRequestedN));
        $rxWorkerTransition = $rxN !== $rxRequestedN;
        $rxBase = $buildCronUrl($definition['script']);
        $rxSep = (strpos($rxBase, '?') === false) ? '?' : '&';

        for ($rxI = 0; $rxI < $rxN; $rxI++) {
            $rxTask = [
                'key' => $key,
                'script' => $definition['script'],
                'worker' => $rxI,
                'workers' => $rxN,
                'url' => $rxN > 1
                    ? $rxBase . $rxSep . 'worker=' . $rxI . '&workers=' . $rxN
                    : $rxBase,
            ];
            $rxValidTasks[$rxTaskKeyFor($key, $rxN, $rxI)] = $rxTask;
            if (!$rxDue || $rxWorkerTransition || $rxSkipNoWork) {
                continue;
            }
            $dueTasks[] = $rxTask;
            if ($rxTaskStateEnabled) {
                try {
                    rx_cron_task_enqueue($pdo, $key, (string) $definition['script'], $rxN, $rxI, $rxOccurrence);
                } catch (Throwable $e) {
                }
            }
        }
    }

    if ($rxTaskStateEnabled) {
        try {
            rx_cron_task_cancel_obsolete($pdo, $rxValidTasks);
        } catch (Throwable $e) {
        }
        try {
            rx_cron_task_prune($pdo, $now);
        } catch (Throwable $e) {
        }
        try {
            rx_cron_task_scan_markers($pdo, $rxTaskDispatchLease, $now);
        } catch (Throwable $e) {
        }
        try {
            rx_cron_task_reconcile($pdo, $rxTaskMaxAttempts, $now);
        } catch (Throwable $e) {
        }
    }

    $definedScripts = [];
    foreach ($definitions as $definition) {
        if (isset($definition['script']) && is_string($definition['script'])) {
            $definedScripts[] = ltrim($definition['script'], '/');
        }
    }

    if (!in_array('index.php', $definedScripts, true) && !$rxCronScriptExists('index.php')) {
        $rxSkippedMissingScripts++;
        $rxReportMissingCronScript('__index__', 'index.php');
    } elseif (!in_array('index.php', $definedScripts, true)) {
        $indexDispatched = false;

        if ($rxIsCli) {
            $indexDispatched = $rxDispatchCli('index.php', 0, 1, true);
            if ($indexDispatched) {
                $rxCliDispatched++;
                $rxSuccessfulDispatches++;
            }
        }

        if (!$indexDispatched) {
            $indexTask = [[
                'key' => '__index__',
                'script' => 'index.php',
                'worker' => 0,
                'workers' => 1,
                'url' => $buildCronUrl('index.php'),
            ]];

            $indexResult = $rxDispatchHttpTasks($indexTask, false);
            if (!empty($indexResult['failed'])) {
                $indexResult = $rxDispatchHttpTasks($indexResult['failed'], true);
            }

            if (!empty($indexResult['success'])) {
                $rxSuccessfulDispatches += count($indexResult['success']);
            }
        }
    }
}

$rxHttpBatchSize = $rxSharedProfile ? 2 : 8;

$rxTaskStarted = static function (string $taskKey) use ($pdo, $rxTaskDispatchLease): bool {
    if ($taskKey === '' || !($pdo instanceof PDO)) {
        return false;
    }
    if (function_exists('rx_cron_task_scan_markers')) {
        try {
            rx_cron_task_scan_markers($pdo, $rxTaskDispatchLease, time());
        } catch (Throwable $e) {
        }
    }
    if (!function_exists('rx_cron_task_state_name')) {
        return false;
    }
    try {
        $state = rx_cron_task_state_name($pdo, $taskKey);
    } catch (Throwable $e) {
        return false;
    }
    return in_array($state, ['running', 'completed', 'failed'], true);
};

$rxDispatchTask = static function (array $task) use ($rxIsCli, $rxSharedProfile, $buildCronUrl, $rxDispatchHttpTasks, $rxDispatchCli, $rxTaskStarted): string {
    $jobKey  = (string) ($task['job_key'] ?? $task['key'] ?? '');
    $script  = (string) ($task['script'] ?? '');
    $worker  = (int) ($task['worker'] ?? 0);
    $workers = max(1, (int) ($task['workers'] ?? 1));
    $taskKey = (string) ($task['task_key'] ?? '');
    $token   = (string) ($task['token'] ?? '');
    $httpAttempted = false;

    if ($rxIsCli) {
        $background = ($jobKey === 'backupbot') ? true : !$rxSharedProfile;
        if ($rxDispatchCli($script, $worker, $workers, $background, $taskKey, $token)) {
            return 'accepted';
        }
        if ($rxTaskStarted($taskKey)) {
            return 'accepted';
        }
    }

    if (function_exists('curl_multi_init')) {
        $httpAttempted = true;
        $rxBase = $buildCronUrl($script);
        $rxSep = (strpos($rxBase, '?') === false) ? '?' : '&';
        $single = [[
            'key' => $jobKey,
            'script' => $script,
            'worker' => $worker,
            'workers' => $workers,
            'url' => $workers > 1 ? $rxBase . $rxSep . 'worker=' . $worker . '&workers=' . $workers : $rxBase,
            'task_key' => $taskKey,
            'token' => $token,
        ]];
        $result = $rxDispatchHttpTasks($single, false);
        if (!empty($result['success'])) {
            return 'accepted';
        }
        if ($rxTaskStarted($taskKey)) {
            return 'accepted';
        }
        $result = $rxDispatchHttpTasks($single, true);
        if (!empty($result['success'])) {
            return 'accepted';
        }
        if ($rxTaskStarted($taskKey)) {
            return 'accepted';
        }
    }

    if (!$rxIsCli && $rxDispatchCli($script, $worker, $workers, true, $taskKey, $token)) {
        return 'accepted';
    }

    return $httpAttempted ? 'accepted' : 'failed';
};

if ($rxTaskStateEnabled) {
    $rxDispatchDeadline = microtime(true) + ($rxIsCli ? ($rxSharedProfile ? 240 : 45) : ($rxSharedProfile ? 8 : 15));
    $rxJobMarked = [];
    $rxOnDispatch = static function (array $claim) use ($pdo, $now, &$runtimeState, &$rxJobMarked, $rxMarkJobRun): void {
        $jobKey = (string) ($claim['job_key'] ?? '');
        if ($jobKey === '' || isset($rxJobMarked[$jobKey])) {
            return;
        }
        $rxJobMarked[$jobKey] = true;
        $rxMarkJobRun($pdo, $jobKey, $now, $runtimeState);
    };
    $rxValidateTask = static function (array $row) use ($rxValidTasks): bool {
        $taskKey = (string) ($row['task_key'] ?? '');
        if ($taskKey === '' || !isset($rxValidTasks[$taskKey])) {
            return false;
        }
        $valid = $rxValidTasks[$taskKey];
        return (int) ($valid['worker'] ?? -1) === (int) ($row['worker'] ?? -2)
            && (int) ($valid['workers'] ?? -1) === (int) ($row['workers'] ?? -2);
    };
    $rxStats = ['dispatched' => 0];
    $rxDeferredJobs = [];
    foreach ((array) ($rxAvailability ?? []) as $rxJobKey => $rxJobState) {
        if ($rxJobState === false) {
            $rxDeferredJobs[] = (string) $rxJobKey;
        }
    }
    if (function_exists('rx_cron_task_run_pending')) {
        try {
            $rxStats = rx_cron_task_run_pending($pdo, [
                'concurrency' => $rxMaxConcurrency,
                'max_attempts' => $rxTaskMaxAttempts,
                'lease_seconds' => $rxTaskDispatchLease,
                'deadline' => $rxDispatchDeadline,
                'poll_micros' => $rxSharedProfile ? 600000 : 400000,
                'deferred_jobs' => $rxDeferredJobs,
            ], $rxDispatchTask, $rxValidateTask, $rxOnDispatch);
        } catch (Throwable $e) {
            $rxStats = ['dispatched' => 0];
        }
    }
    $rxSuccessfulDispatches += (int) ($rxStats['dispatched'] ?? 0);
} elseif ($rxIsCli) {
    $successfulJobs = [];
    foreach ($dueTasks as $task) {
        $jobKey = (string) $task['key'];
        $background = ($jobKey === 'backupbot') ? true : !$rxSharedProfile;
        if ($rxDispatchCli($task['script'], (int) $task['worker'], (int) $task['workers'], $background)) {
            $rxCliDispatched++;
            $rxSuccessfulDispatches++;
            $successfulJobs[$jobKey] = true;
            continue;
        }
        $result = $rxDispatchHttpTasks([$task], false);
        if (!empty($result['failed'])) {
            $result = $rxDispatchHttpTasks($result['failed'], true);
        }
        if (!empty($result['success'])) {
            $rxSuccessfulDispatches += count($result['success']);
            $successfulJobs[$jobKey] = true;
        }
    }
    foreach (array_keys($successfulJobs) as $jobKey) {
        $rxMarkJobRun($pdo, $jobKey, $now, $runtimeState);
    }
} elseif (!empty($dueTasks)) {
    $primarySuccess = [];
    $primaryFailed = [];

    foreach (array_chunk($dueTasks, $rxHttpBatchSize) as $batch) {
        $result = $rxDispatchHttpTasks($batch, false);
        $primarySuccess = array_merge($primarySuccess, $result['success']);
        $primaryFailed = array_merge($primaryFailed, $result['failed']);
    }

    $loopbackSuccess = [];
    $loopbackFailed = [];

    if (!empty($primaryFailed)) {
        foreach (array_chunk($primaryFailed, $rxHttpBatchSize) as $batch) {
            $result = $rxDispatchHttpTasks($batch, true);
            $loopbackSuccess = array_merge($loopbackSuccess, $result['success']);
            $loopbackFailed = array_merge($loopbackFailed, $result['failed']);
        }
    }

    $cliFallbackSuccess = [];
    if (!empty($loopbackFailed)) {
        foreach ($loopbackFailed as $task) {
            $cliOk = $rxDispatchCli(
                $task['script'],
                (int) $task['worker'],
                (int) $task['workers'],
                true
            );
            if ($cliOk) {
                $cliFallbackSuccess[] = $task;
                $rxCliDispatched++;
            }
        }
    }

    $allSuccess = array_merge($primarySuccess, $loopbackSuccess, $cliFallbackSuccess);
    $rxSuccessfulDispatches += count($allSuccess);

    $successfulJobs = [];
    foreach ($allSuccess as $task) {
        $successfulJobs[$task['key']] = true;
    }

    foreach (array_keys($successfulJobs) as $jobKey) {
        $rxMarkJobRun($pdo, $jobKey, $now, $runtimeState);
    }
}

if (!empty($rxHealthTrace['traced']) && function_exists('rx_cron_health_orch_diagnostics')) {
    rx_cron_health_orch_diagnostics($pdo);
}
if (function_exists('rx_cron_health_orch_complete')) {
    try {
        if (!empty($rxHealthTrace['active']) && !function_exists('telegram') && is_readable(APP_ROOT_PATH . '/botapi.php')) {
            require_once APP_ROOT_PATH . '/botapi.php';
        }
        rx_cron_health_orch_complete($pdo, !empty($rxHealthTrace['active']));
    } catch (Throwable $e) {
    }
}

$rxDispatchedTotal = $rxSuccessfulDispatches;
echo "OK " . date('Y-m-d H:i:s') . " (Asia/Tehran) | dispatched=" . $rxDispatchedTotal . ($rxSkippedMissingScripts > 0 ? " | skipped_missing=" . $rxSkippedMissingScripts : '') . "\n";

