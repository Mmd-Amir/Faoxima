<?php

namespace Faoxima\Cron\Jobs;

use Throwable;

final class XuiInboundSyncJob
{
    public static function run()
    {
require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('xui_inbound_sync', 180);

        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('xui_inbound_sync', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../panels.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('xui_inbound_sync')) {
            return;
        }
        if (!method_exists(\Faoxima\Services\Panels\XuiInboundSync::class, 'xsync_run_all')) {
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('xsync_functions_unavailable', 300);
            }
            return;
        }

        try {
            \Faoxima\Services\Panels\XuiInboundSync::xsync_run_all();
        } catch (Throwable $e) {
            error_log('[xui_inbound_sync] ' . $e->getMessage());
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('xui_inbound_sync_failed', 120);
            }
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('xui_inbound_sync_finished');
        }
    }
}
