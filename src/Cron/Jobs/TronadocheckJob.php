<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\PaymentJob;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Payments\Gateways\Tronado\Settlement;

final class TronadocheckJob
{
    public static function run()
    {
        global $ManagePanel, $pdo, $setting;

        require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        $ctx = PaymentJob::begin('tronadocheck', 60);
        if ($ctx === null) {
            return;
        }
        $ManagePanel = $ctx['managePanel'];
        $setting = $ctx['setting'];

        if (!PaymentJob::requireGateway('tronadocheck', 'tronado v2', [Settlement::class, 'runCron'], 'tronado_functions_unavailable')) {
            return;
        }

        Settlement::runCron($pdo, $setting);
        TaskState::rx_cron_task_complete('tronado_poll_finished');
    }
}
