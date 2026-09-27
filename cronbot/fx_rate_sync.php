<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('fx_rate_sync', 120);

if (!rx_cron_require_or_skip('fx_rate_sync', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('fx_rate_sync')) {
    return;
}
if (!function_exists('fx_rate_sync')) {
    return;
}

try {
    $fxSyncResult = fx_rate_sync(FX_DEFAULT_PAIR);
    if (empty($fxSyncResult['ok'])) {
        error_log('[fx_rate_sync] ' . (string) ($fxSyncResult['action'] ?? 'failed') . ' ' . (string) ($fxSyncResult['error'] ?? ''));
    }
} catch (Throwable $e) {
    error_log('[fx_rate_sync] ' . $e->getMessage());
}
