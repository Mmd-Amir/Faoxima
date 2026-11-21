<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\CronBatches;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Cron\Services\PlisioPoller;
use mysqli;

final class PlisioJob
{
    public static function run()
    {
        global $ManagePanel, $connect, $pdo, $setting;

        require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        CronRuntime::rx_cron_boot('plisio', 180);
        ini_set('error_log', 'error_log');

        $ctx = CronBatches::rx_cron_require_payment_context();
        if ($ctx === null) {
            return;
        }

        $connect = function_exists('getMysqliConnection') ? getMysqliConnection() : null;
        if (!($connect instanceof mysqli)) {
            TaskState::rx_cron_task_defer('mysqli_unavailable', 120);
            return;
        }
        $setting = $ctx['setting'];
        $ManagePanel = $ctx['managePanel'];

        (new PlisioPoller($connect, $pdo, $ctx['paymentreports']))->run();
    }
}
