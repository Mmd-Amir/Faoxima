<?php

namespace Faoxima\Cron\Jobs;

use ManagePanel;

final class DisableconfigJob
{
    public static function run()
    {
        global $ManagePanel, $pdo;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('disableconfig', 120);
        
        ini_set('error_log', 'error_log');
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('disableconfig', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../panels.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('disableconfig')) {
            return;
        }
        $ManagePanel = new ManagePanel();
        
        
        $rxEligible = "Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold') AND Service_location NOT IN (SELECT name_panel FROM marzban_panel WHERE type = 'remnawave')";
        if (!\Faoxima\Cron\Runtime\CronBatches::rx_cron_bulk_service_status($pdo, $ManagePanel, 'disableconfig', $rxEligible, '2', 'active', 'disabled', 'disablebyadmin')) {
            return;
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('disableconfig_finished');
        }
    }
}
