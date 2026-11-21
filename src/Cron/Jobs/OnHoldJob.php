<?php

namespace Faoxima\Cron\Jobs;

use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Services\OnHoldNotifier;
use ManagePanel;

final class OnHoldJob
{
    public static function run()
    {
        global $ManagePanel, $pdo, $setting, $text, $user, $username;

        require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        CronRuntime::rx_cron_boot('on_hold', 240);

        ini_set('error_log', 'error_log');
        $root = dirname(__DIR__, 3) . '/cronbot/..';
        require_once $root . '/config.php';
        require_once $root . '/botapi.php';
        require_once $root . '/panels.php';
        require_once $root . '/function.php';

        if (!CronRuntime::rx_cron_db_ready('on_hold')) {
            return;
        }

        $ManagePanel = new ManagePanel();
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");

        (new OnHoldNotifier($pdo, $setting))->run();
    }
}
