<?php

namespace Faoxima\Cron\Jobs;

use PDO;

final class TetraminatorcheckJob
{
    public static function run()
    {
        global $pdo, $setting;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        if ((int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0) > 0) {
            return;
        }
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('tetraminatorcheck', 180);

        ini_set('error_log', 'error_log');

        $ctx = \Faoxima\Cron\Runtime\CronBatches::rx_cron_require_payment_context();
        if ($ctx === null) {
            return;
        }
        require_once (dirname(__DIR__, 3) . '/cronbot') . '/../src/bootstrap.php';

        global $pdo, $setting;
        $setting = $ctx['setting'];

        if (!($pdo instanceof PDO)) {
            error_log('[tetraminatorcheck] no PDO connection');
            return;
        }

        \Faoxima\Payments\Gateways\Tetraminator\Recovery::processPendingOrders($pdo, $setting, 20);
        \Faoxima\Payments\Gateways\Tetraminator\Recovery::recoverVerifiedOrders($pdo, $setting, 10);
        \Faoxima\Payments\Gateways\Tetraminator\Recovery::closeStale($pdo);
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('tetraminator_poll_finished');
        }
    }
}
