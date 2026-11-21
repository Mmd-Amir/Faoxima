<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Services\IpLimitNotifier;

final class IpBlockNotifyJob
{
    public static function run()
    {
        global $pdo;

        require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        CronRuntime::rx_cron_boot('ip_block_notify', 120);

        ini_set('error_log', 'error_log');
        $root = dirname(__DIR__, 3) . '/cronbot/..';
        if (!CronRuntime::rx_cron_require_or_skip('ip_block_notify', [
            $root . '/config.php',
            $root . '/botapi.php',
            $root . '/panels.php',
            $root . '/function.php',
        ])) {
            return;
        }
        if (!CronRuntime::rx_cron_db_ready('ip_block_notify')) {
            return;
        }

        (new IpLimitNotifier($pdo))->run();
    }
}
