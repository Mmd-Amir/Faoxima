<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('logs_cleanup', 300);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('logs_cleanup', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('logs_cleanup')) {
    return;
}

$retentionDays = 90;
$enabled       = true;
try {
    $rowDays = select("shopSetting", "value", "Namevalue", "logs_api_retention_days", "select");
    if (is_array($rowDays) && isset($rowDays['value']) && (int) $rowDays['value'] > 0) {
        $retentionDays = max(1, min(3650, (int) $rowDays['value']));
    }
    $rowStatus = select("shopSetting", "value", "Namevalue", "logs_api_retention_status", "select");
    if (is_array($rowStatus) && isset($rowStatus['value'])) {
        $val     = strtolower(trim((string) $rowStatus['value']));
        $enabled = ($val !== 'off' && $val !== '0' && $val !== 'false');
    }
} catch (Throwable $e) {
}

if (!$enabled) {
    rx_cron_task_complete('logs_cleanup_disabled');
    return;
}

$cutoff  = date('Y/m/d H:i:s', time() - ($retentionDays * 86400));
$batch   = 2000;
$runCap  = 500000;
$removed = 0;

try {
    $del = $pdo->prepare("DELETE FROM logs_api WHERE time < :cutoff ORDER BY id ASC LIMIT {$batch}");
    while ($removed < $runCap) {
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
            rx_cron_task_defer('time_budget', 60);
            break;
        }
        $del->execute([':cutoff' => $cutoff]);
        $n        = (int) $del->rowCount();
        $removed += $n;
        if ($n < $batch) {
            break;
        }
        usleep(50000);
    }
} catch (Throwable $e) {
    error_log('[logs_cleanup] ' . $e->getMessage());
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('logs_cleanup_failed', 120);
    }
}
if ($removed >= $runCap) {
    rx_cron_task_defer('cleanup_batch_limit', 60);
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('logs_cleanup_finished');
}
