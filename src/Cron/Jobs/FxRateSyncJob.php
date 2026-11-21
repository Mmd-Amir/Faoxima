<?php

namespace Faoxima\Cron\Jobs;

use Throwable;

final class FxRateSyncJob
{
    public static function run()
    {
require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('fx_rate_sync', 120);

        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('fx_rate_sync', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('fx_rate_sync')) {
            return;
        }
        if (!is_callable([\Faoxima\Orders\Pricing\ExchangeRates::class, 'fxRateSync'])) {
            return;
        }

        try {
            $fxSyncResult = \Faoxima\Orders\Pricing\ExchangeRates::fxRateSync(FX_DEFAULT_PAIR);
            if (empty($fxSyncResult['ok'])) {
                if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('fx_rate_sync_failed', 120);
                }
                error_log('[fx_rate_sync] ' . (string) ($fxSyncResult['action'] ?? 'failed') . ' ' . (string) ($fxSyncResult['error'] ?? ''));
            }
        } catch (Throwable $e) {
            error_log('[fx_rate_sync] ' . $e->getMessage());
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('fx_rate_sync_failed', 120);
            }
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('fx_rate_sync_finished');
        }
    }
}
