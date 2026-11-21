<?php

namespace Faoxima\Cron\Jobs;

use Throwable;

final class NotificationExpireJob
{
    public static function run()
    {
        global $pdo;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('notification_expire', 120);

        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('notification_expire', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('notification_expire')) {
            return;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM notification WHERE expires_at <= :now');
            $stmt->execute([':now' => time()]);
        } catch (Throwable $e) {
            error_log('[notification_expire] ' . $e->getMessage());
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('notification_expire_failed', 120);
            }
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('notification_expire_finished');
        }
    }
}
