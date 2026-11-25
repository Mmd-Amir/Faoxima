<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Services\Delivery\AutoFileQueue;
use ManagePanel;

final class AutoFileDeliveryJob
{
    public static function run(): void
    {
        global $pdo, $ManagePanel, $setting;

        $root = dirname(__DIR__, 3);
        require_once $root . '/cronbot/_init.php';
        CronRuntime::rx_cron_boot('auto_file_delivery', 120);
        if (!CronRuntime::rx_cron_require_or_skip('auto_file_delivery', [
            $root . '/config.php', $root . '/botapi.php', $root . '/function.php', $root . '/panels.php',
        ]) || !CronRuntime::rx_cron_db_ready('auto_file_delivery')) {
            return;
        }

        $setting = \Faoxima\Core\Database\Db::select('setting', '*');
        $ManagePanel = new ManagePanel();
        AutoFileQueue::drain($pdo, $ManagePanel);
        TaskState::rx_cron_task_complete('auto_file_delivery_finished');
    }
}
