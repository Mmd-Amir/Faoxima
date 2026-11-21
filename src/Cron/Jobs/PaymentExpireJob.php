<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Services\PaymentExpirer;
use ManagePanel;

final class PaymentExpireJob
{
    public static function run()
    {
        global $ManagePanel, $datatextbot, $pdo, $setting;

        require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        CronRuntime::rx_cron_boot('payment_expire', 180);

        ini_set('error_log', 'error_log');
        $root = dirname(__DIR__, 3) . '/cronbot/..';
        if (!CronRuntime::rx_cron_require_or_skip('payment_expire', [
            $root . '/config.php',
            $root . '/botapi.php',
            $root . '/panels.php',
            $root . '/function.php',
        ])) {
            return;
        }
        if (!CronRuntime::rx_cron_db_ready('payment_expire')) {
            return;
        }
        if (is_file($root . '/vendor/autoload.php')) {
            require $root . '/vendor/autoload.php';
        }
        $ManagePanel = new ManagePanel();
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");

        $expirer = new PaymentExpirer($pdo);
        $datatextbot = $expirer->buttonTexts();
        $expirer->run();
    }
}
