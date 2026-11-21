<?php

namespace Faoxima\Cron\Jobs;

final class NoticationsServiceJob
{
    public static function run()
    {
require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('notifications', 600);

        ini_set('error_log', 'error_log');

        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('notifications', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../panels.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('notifications')) {
            return;
        }




        $volumeMonitor = new \Faoxima\Cron\Services\ServiceMonitor();
        $volumeMonitor->RunNotifactions();
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('notifications_finished');
        }
    }
}
