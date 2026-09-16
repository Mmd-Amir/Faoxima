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


$lockFile = __DIR__ . '/cron.lock';
$lockHandle = @fopen($lockFile, 'c');
if ($lockHandle === false || !@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    if (is_resource($lockHandle)) {
        @fclose($lockHandle);
    }
    echo "BUSY\n";
    exit;
}
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
register_shutdown_function(static function () use ($rxInternalAuthFile): void {
    @unlink($rxInternalAuthFile);
});
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
        if (is_file($rxCronBootstrapMarker)) {
            @unlink($rxCronBootstrapMarker);
        }
    } catch (Throwable $e) {
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
    echo "SKIP (db unavailable)\n";
    exit;
}

$runtimeState = [];
if (function_exists('loadCronRuntimeState')) {
    try {
        $runtimeState = loadCronRuntimeState($pdo);
    } catch (Throwable $e) {
        $runtimeState = [];
    }
}


$rxCronbotDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cronbot';
if (is_dir($rxCronbotDir)) {
    foreach ((array) @glob($rxCronbotDir . '/*.lock') as $rxStale) {
        if ((time() - (int) @filemtime($rxStale)) > 120) { @unlink($rxStale); }
    }
    foreach (['_db_unavailable.flag', '_db_unavailable.log', '_missing_files.log'] as $rxLegacy) {
        $rxLegacyPath = $rxCronbotDir . DIRECTORY_SEPARATOR . $rxLegacy;
        if (is_file($rxLegacyPath) && (time() - (int) @filemtime($rxLegacyPath)) > 120) { @unlink($rxLegacyPath); }
    }
    if (is_dir($rxCronbotDir . DIRECTORY_SEPARATOR . '.dbslots')) {
        foreach ((array) @glob($rxCronbotDir . '/.dbslots/*') as $rxSlot) { @unlink($rxSlot); }
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
$jobWorkerCounts = [
    'sendmessage'   => $rxBroadcastWorkers,
    'notifications' => $rxBroadcastWorkers,
    'plisio'        => $rxPaymentWorkers,
    'croncard'      => $rxPaymentWorkers,
];


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


$dispatchAsync = static function (array $urls, bool $useLoopback) use ($rxInternalAuthToken): array {
    if (empty($urls)) return [];
    $multi = curl_multi_init();
    if ($multi === false) return $urls;
    $handles    = [];
    $handleUrls = [];
    foreach ($urls as $url) {
        $bustedUrl = $url . (strpos($url, '?') === false ? '?' : '&') . '_t=' . microtime(true);
        $ch = curl_init($bustedUrl);
        if ($ch === false) continue;
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
        $handles[]             = $ch;
        $handleUrls[(int) $ch] = $url;
    }
    if (empty($handles)) {
        curl_multi_close($multi);
        return [];
    }


    $deadline = microtime(true) + 5.0;
    do {
        $status = curl_multi_exec($multi, $running);
        if ($status === CURLM_OK && $running > 0) {
            curl_multi_select($multi, 0.2);
        }
    } while ($running > 0 && microtime(true) < $deadline);

    $failed = [];
    foreach ($handles as $ch) {
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        $url = $handleUrls[(int) $ch] ?? null;
        $body = (string) curl_multi_getcontent($ch);
        $isFail = ($err !== '' || $code < 200 || $code >= 400);
        if ($isFail) {
            if ($url !== null) {
                $failed[] = $url;
            }
        }
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);
    return $failed;
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

    $candidates = [];

    if (php_sapi_name() === 'cli' && defined('PHP_BINARY') && PHP_BINARY) {
        $candidates[] = PHP_BINARY;
    }

    $candidates = array_merge($candidates, [
        '/usr/local/bin/php',
        '/usr/bin/php',
        '/opt/cpanel/ea-php85/root/usr/bin/php',
        '/opt/cpanel/ea-php84/root/usr/bin/php',
        '/opt/cpanel/ea-php83/root/usr/bin/php',
        '/opt/cpanel/ea-php82/root/usr/bin/php',
        '/opt/cpanel/ea-php81/root/usr/bin/php',
        'php',
    ]);

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
        @exec(escapeshellarg($candidate) . ' -r ' . escapeshellarg('echo PHP_SAPI;') . ' 2>/dev/null', $output, $code);

        if ($code === 0 && trim(implode("
", $output)) === 'cli') {
            $cliPhp = $candidate;
            return $cliPhp;
        }
    }

    return null;
};

$rxDispatchCli = static function (string $script, int $worker, int $workers, bool $background) use ($rxResolveCliPhp, $rxCanExec): bool {
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

    if (DIRECTORY_SEPARATOR === '\\') {
        $cmd = 'set "BROADCAST_WORKER_ID=' . $worker . '" && set "BROADCAST_WORKERS=' . $workers . '" && '
             . escapeshellarg($phpBin) . ' ' . escapeshellarg($file);
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

    $cmd = 'BROADCAST_WORKER_ID=' . $worker . ' BROADCAST_WORKERS=' . $workers . ' '
        . escapeshellarg($phpBin) . ' ' . escapeshellarg($file);

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

    $urls = [];
    $taskByUrl = [];

    foreach ($tasks as $task) {
        $url = $task['url'];
        if ($useLoopback) {
            $loopback = $rxDetectLoopback();
            if (!is_array($loopback)) {
                return ['success' => [], 'failed' => $tasks];
            }

            $parts = parse_url($url);
            $path = $parts['path'] ?? '/';
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            $url = $loopback['scheme'] . '://127.0.0.1:' . $loopback['port'] . $path . $query;
        }

        $urls[] = $url;
        $taskByUrl[$url][] = $task;
    }

    $failedUrls = $dispatchAsync($urls, $useLoopback);
    $failedLookup = [];
    foreach ($failedUrls as $failedUrl) {
        $failedLookup[$failedUrl] = true;
    }

    $success = [];
    $failed = [];

    foreach ($urls as $url) {
        $bucket = $taskByUrl[$url] ?? [];
        foreach ($bucket as $task) {
            if (isset($failedLookup[$url])) {
                $failed[] = $task;
            } else {
                $success[] = $task;
            }
        }
    }

    return ['success' => $success, 'failed' => $failed];
};

if ($bootstrapLoaded && function_exists('getCronJobDefinitions')) {
    $definitions = getCronJobDefinitions();
    $schedules   = function_exists('loadCronSchedules') ? loadCronSchedules() : [];

    foreach ($definitions as $key => $definition) {
        if (empty($definition['script'])) {
            continue;
        }

        $defaultConfig = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
        $schedule      = $schedules[$key] ?? $defaultConfig;

        $rxDue = $shouldRun($key, $schedule, $minute, $hour, $dayOfYear, $now, $runtimeState, $jobHours[$key] ?? 0);
        if (!$rxDue) {
            continue;
        }

        $rxN = max(1, (int) ($jobWorkerCounts[$key] ?? 1));
        $jobDispatched = false;

        if ($rxIsCli) {
            for ($rxI = 0; $rxI < $rxN; $rxI++) {
                $cliOk = $rxDispatchCli(
                    $definition['script'],
                    $rxI,
                    $rxN,
                    !$rxSharedProfile
                );

                if ($cliOk) {
                    $rxCliDispatched++;
                    $rxSuccessfulDispatches++;
                    $jobDispatched = true;
                    continue;
                }

                $rxBase = $buildCronUrl($definition['script']);
                $rxSep  = (strpos($rxBase, '?') === false) ? '?' : '&';
                $fallbackUrl = $rxN > 1
                    ? $rxBase . $rxSep . 'worker=' . $rxI . '&workers=' . $rxN
                    : $rxBase;

                $fallbackResult = $rxDispatchHttpTasks([[
                    'key' => $key,
                    'script' => $definition['script'],
                    'worker' => $rxI,
                    'workers' => $rxN,
                    'url' => $fallbackUrl,
                ]], false);

                if (!empty($fallbackResult['failed'])) {
                    $fallbackResult = $rxDispatchHttpTasks($fallbackResult['failed'], true);
                }

                if (!empty($fallbackResult['success'])) {
                    $rxSuccessfulDispatches += count($fallbackResult['success']);
                    $jobDispatched = true;
                }
            }

            if ($jobDispatched) {
                $rxMarkJobRun($pdo, $key, $now, $runtimeState);
            } else {
            }
            continue;
        }

        if ($key === 'backupbot') {
            $cliOk = $rxDispatchCli($definition['script'], 0, 1, true);
            if ($cliOk) {
                $rxCliDispatched++;
                $rxSuccessfulDispatches++;
                $jobDispatched = true;
            } else {
                $rxBase = $buildCronUrl($definition['script']);
                $backupTask = [[
                    'key' => $key,
                    'script' => $definition['script'],
                    'worker' => 0,
                    'workers' => 1,
                    'url' => $rxBase,
                ]];

                $fallbackResult = $rxDispatchHttpTasks($backupTask, false);

                if (!empty($fallbackResult['failed'])) {
                    $fallbackResult = $rxDispatchHttpTasks($fallbackResult['failed'], true);
                }

                if (!empty($fallbackResult['success'])) {
                    $rxSuccessfulDispatches += count($fallbackResult['success']);
                    $jobDispatched = true;
                }
            }

            if ($jobDispatched) {
                $rxMarkJobRun($pdo, $key, $now, $runtimeState);
            } else {
            }
            continue;
        }

        $rxBase = $buildCronUrl($definition['script']);
        $rxSep  = (strpos($rxBase, '?') === false) ? '?' : '&';

        for ($rxI = 0; $rxI < $rxN; $rxI++) {
            $dueTasks[] = [
                'key' => $key,
                'script' => $definition['script'],
                'worker' => $rxI,
                'workers' => $rxN,
                'url' => $rxN > 1
                    ? $rxBase . $rxSep . 'worker=' . $rxI . '&workers=' . $rxN
                    : $rxBase,
            ];
        }
    }

    $definedScripts = [];
    foreach ($definitions as $definition) {
        if (isset($definition['script']) && is_string($definition['script'])) {
            $definedScripts[] = ltrim($definition['script'], '/');
        }
    }

    if (!in_array('index.php', $definedScripts, true)) {
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

if (!$rxIsCli && !empty($dueTasks)) {
    $rxHttpBatchSize = $rxSharedProfile ? 2 : 8;
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

$rxDispatchedTotal = $rxSuccessfulDispatches;
echo "OK " . date('Y-m-d H:i:s') . " (Asia/Tehran) | dispatched=" . $rxDispatchedTotal . "\n";

