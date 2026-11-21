<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Services\RemnawaveUsageSync;
use ManagePanel;

final class RemnawaveUsageJob
{
    public static function run()
    {
        global $ManagePanel, $pdo;

        require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        CronRuntime::rx_cron_boot('remnawave_usage', 900);

        ini_set('error_log', 'error_log');
        $root = dirname(__DIR__, 3) . '/cronbot/..';
        if (!CronRuntime::rx_cron_require_or_skip('remnawave_usage', [
            $root . '/config.php',
            $root . '/botapi.php',
            $root . '/panels.php',
            $root . '/function.php',
        ])) {
            return;
        }
        if (!CronRuntime::rx_cron_db_ready('remnawave_usage')) {
            return;
        }
        if (is_file($root . '/vendor/autoload.php')) {
            require $root . '/vendor/autoload.php';
        }

        $ManagePanel = new ManagePanel();

        (new RemnawaveUsageSync($pdo))->run();
    }
}
