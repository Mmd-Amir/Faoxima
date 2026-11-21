<?php

namespace Faoxima\Cron;

use Throwable;
use mysqli;
use PDO;
use RuntimeException;

final class Dispatcher
{
    public static function run()
    {
        global $conn, $domainhosts, $pdo, $query, $token;

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';


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


if (method_exists(\Faoxima\Cron\Health\HealthRun::class, 'rx_cron_health_orch_begin')) {
    \Faoxima\Cron\Health\HealthRun::rx_cron_health_orch_begin();
}
$rxHealthStage = static function (string $name, string $state): void {
    if (method_exists(\Faoxima\Cron\Health\HealthRun::class, 'rx_cron_health_orch_stage')) {
        \Faoxima\Cron\Health\HealthRun::rx_cron_health_orch_stage($name, $state);
    }
};

$lockFile = (dirname(__DIR__, 2) . '/cron') . '/cron.lock';
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
$rxInternalAuthFile = (dirname(__DIR__, 2) . '/cron') . '/.cron_internal_auth';
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


$functionBootstrap = (dirname(__DIR__, 2) . '/cron') . '/function.php';
if (!is_readable($functionBootstrap)) {
    $functionBootstrap = (dirname(__DIR__, 2) . '/cron') . '/../function.php';
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


$buildCronUrl = \Faoxima\Cron\Dispatch\Host::resolveBaseUrl($domainhosts);
$root = dirname(__DIR__, 2);

if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', dirname((dirname(__DIR__, 2) . '/cron')));
}

$pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
if (!($pdo instanceof PDO)) {
    $rxHealthStage('db', 'failed');
    echo "SKIP (db unavailable)\n";
    exit;
}
$rxHealthStage('db', 'ok');

$runtimeState = [];
try {
    $runtimeState = \Faoxima\Core\Cron\Schedules::loadCronRuntimeState($pdo);
} catch (Throwable $e) {
    $runtimeState = [];
}

$rxHealthTrace = \Faoxima\Cron\Health\HealthRun::rx_cron_health_orch_attach_db($pdo);

\Faoxima\Cron\Dispatch\Host::sweepStaleRuntime($root);

$plan = \Faoxima\Cron\Dispatch\Host::workerPlan($root);
$rxSharedProfile = $plan['sharedProfile'];
$rxTaskMaxAttempts = 3;
$rxTaskDispatchLease = 120;
$rxTaskStateEnabled = false;
try {
    $rxTaskStateEnabled = \Faoxima\Cron\Runtime\TaskState::rx_cron_task_ensure_table($pdo);
} catch (Throwable $e) {
    $rxTaskStateEnabled = false;
}

$now = time();
\Faoxima\Cron\Dispatch\Planner::runMaintenance($pdo, $now, (int) date('G', $now), $runtimeState);

$rxIsCli = (php_sapi_name() === 'cli');
$rxLegacyLoopbackFlag = (dirname(__DIR__, 2) . '/cron') . '/use_loopback.flag';
if (is_file($rxLegacyLoopbackFlag)) {
    @unlink($rxLegacyLoopbackFlag);
}

$planner = new \Faoxima\Cron\Dispatch\Planner($pdo, $root, $runtimeState, $plan['jobHours'], $plan['jobWorkerCounts'], $rxTaskStateEnabled, $rxTaskMaxAttempts, $rxTaskDispatchLease, $buildCronUrl, $now);
$delivery = new \Faoxima\Cron\Dispatch\Delivery($pdo, $rxInternalAuthToken, $rxIsCli, $rxSharedProfile, $buildCronUrl, $rxTaskDispatchLease, $rxTaskMaxAttempts, $plan['maxConcurrency']);

$planner->plan();
$delivery->dispatchIndexFallback($planner);
$delivery->deliver($planner, $rxTaskStateEnabled, $now);

if (!empty($rxHealthTrace['traced'])) {
    \Faoxima\Cron\Health\HealthRun::rx_cron_health_orch_diagnostics($pdo);
}
try {
    if (!empty($rxHealthTrace['active']) && !function_exists('telegram') && is_readable(APP_ROOT_PATH . '/botapi.php')) {
        require_once APP_ROOT_PATH . '/botapi.php';
    }
    \Faoxima\Cron\Health\HealthRun::rx_cron_health_orch_complete($pdo, !empty($rxHealthTrace['active']));
} catch (Throwable $e) {
}

$rxDispatchedTotal = $delivery->successful;
echo "OK " . date('Y-m-d H:i:s') . " (Asia/Tehran) | dispatched=" . $rxDispatchedTotal . ($planner->skippedMissing > 0 ? " | skipped_missing=" . $planner->skippedMissing : '') . "\n";
    }
}
