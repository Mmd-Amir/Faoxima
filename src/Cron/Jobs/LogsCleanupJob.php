<?php

namespace Faoxima\Cron\Jobs;

use Throwable;

final class LogsCleanupJob
{
    public static function run()
    {
        global $pdo;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('logs_cleanup', 300);

        ini_set('error_log', 'error_log');
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('logs_cleanup', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('logs_cleanup')) {
            return;
        }

        $retentionDays = 90;
        $enabled       = true;
        try {
            $rowDays = \Faoxima\Core\Database\Db::select("shopSetting", "value", "Namevalue", "logs_api_retention_days", "select");
            if (is_array($rowDays) && isset($rowDays['value']) && (int) $rowDays['value'] > 0) {
                $retentionDays = max(1, min(3650, (int) $rowDays['value']));
            }
            $rowStatus = \Faoxima\Core\Database\Db::select("shopSetting", "value", "Namevalue", "logs_api_retention_status", "select");
            if (is_array($rowStatus) && isset($rowStatus['value'])) {
                $val     = strtolower(trim((string) $rowStatus['value']));
                $enabled = ($val !== 'off' && $val !== '0' && $val !== 'false');
            }
        } catch (Throwable $e) {
        }

        if (!$enabled) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('logs_cleanup_disabled');
            return;
        }

        $cutoff  = date('Y/m/d H:i:s', time() - ($retentionDays * 86400));
        $batch   = 2000;
        $runCap  = 500000;
        $removed = 0;

        try {
            $del = $pdo->prepare("DELETE FROM logs_api WHERE time < :cutoff ORDER BY id ASC LIMIT {$batch}");
            while ($removed < $runCap) {
                if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('time_budget', 60);
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
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('logs_cleanup_failed', 120);
            }
        }
        if ($removed >= $runCap) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('cleanup_batch_limit', 60);
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('logs_cleanup_finished');
        }
    }
}
