<?php


ignore_user_abort(true);
@set_time_limit(60);
@ini_set('memory_limit', '128M');

date_default_timezone_set('Asia/Tehran');
@putenv('TZ=Asia/Tehran');


$lockFile = __DIR__ . '/cron.lock';
if (is_file($lockFile)) {
    $lockAge = time() - (int) @filemtime($lockFile);
    if ($lockAge >= 0 && $lockAge < 15) {
        echo "BUSY\n";
        exit;
    }
    @unlink($lockFile);
}
@file_put_contents($lockFile, getmypid() . '|' . date('Y-m-d H:i:s'));
register_shutdown_function(static function () use ($lockFile) { @unlink($lockFile); });


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
        @unlink($lockFile);
        echo "SKIP (bootstrap unavailable)\n";
        exit;
    }
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
    $path   = $basePath === '' ? '' : $basePath . '/';
    return $scheme . '://' . $hostOnly . $path . 'cronbot/' . $script;
};

if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', dirname(__DIR__));
}

$pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
if (!($pdo instanceof PDO)) {
    @unlink($lockFile);
    echo "SKIP (db unavailable)\n";
    exit;
}

if (function_exists('ensureCronRuntimeStateTable')) {
    try { ensureCronRuntimeStateTable($pdo); } catch (Throwable $e) {}
}
$runtimeState = function_exists('loadCronRuntimeState') ? loadCronRuntimeState($pdo) : [];


$now       = time();
$minute    = (int) date('i', $now);
$hour      = (int) date('G', $now);
$dayOfYear = (int) date('z', $now);


$shouldRun = static function (string $jobKey, array $schedule, int $minute, int $hour, int $dayOfYear, int $now, array $runtimeState): bool {
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
        $aligned = ($minute === 0 && $hour === 0 && $dayOfYear % $value === 0);
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


$dispatchAsync = static function (array $urls): void {
    if (empty($urls)) return;
    $multi = curl_multi_init();
    if ($multi === false) return;
    $handles = [];
    foreach ($urls as $url) {
        $bustedUrl = $url . (strpos($url, '?') === false ? '?' : '&') . '_t=' . microtime(true);
        $ch = curl_init($bustedUrl);
        if ($ch === false) continue;
        curl_setopt_array($ch, [
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
                'Connection: close',
            ],
            CURLOPT_USERAGENT       => 'CronOrchestrator/2.0 (+internal)',
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[] = $ch;
    }
    if (empty($handles)) {
        curl_multi_close($multi);
        return;
    }


    $deadline = microtime(true) + 5.0;
    do {
        $status = curl_multi_exec($multi, $running);
        if ($status === CURLM_OK && $running > 0) {
            curl_multi_select($multi, 0.2);
        }
    } while ($running > 0 && microtime(true) < $deadline);

    foreach ($handles as $ch) {
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);
};


$dueUrls = [];

if ($bootstrapLoaded && function_exists('getCronJobDefinitions')) {
    $definitions = getCronJobDefinitions();
    $schedules   = function_exists('loadCronSchedules') ? loadCronSchedules() : [];

    foreach ($definitions as $key => $definition) {
        if (empty($definition['script'])) {
            continue;
        }
        $defaultConfig = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
        $schedule      = $schedules[$key] ?? $defaultConfig;

        if (!$shouldRun($key, $schedule, $minute, $hour, $dayOfYear, $now, $runtimeState)) {
            continue;
        }

        $dueUrls[] = $buildCronUrl($definition['script']);


        if (function_exists('setCronJobLastRun')) {
            try { setCronJobLastRun($pdo, $key, $now); } catch (Throwable $e) {}
        }
        $runtimeState[$key] = $now;
    }


    $extraScripts = ['index.php'];
    $definedScripts = [];
    foreach ($definitions as $definition) {
        if (isset($definition['script']) && is_string($definition['script'])) {
            $definedScripts[] = ltrim($definition['script'], '/');
        }
    }
    foreach ($extraScripts as $extraScript) {
        if (!in_array($extraScript, $definedScripts, true)) {
            $dueUrls[] = $buildCronUrl($extraScript);
        }
    }
}

if (!empty($dueUrls)) {
    $dispatchAsync($dueUrls);
}

@unlink($lockFile);
echo "OK " . date('Y-m-d H:i:s') . " (Asia/Tehran) | dispatched=" . count($dueUrls) . "\n";

