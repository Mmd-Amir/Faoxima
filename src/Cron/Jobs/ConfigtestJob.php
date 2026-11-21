<?php

namespace Faoxima\Cron\Jobs;

use ManagePanel;
use PDO;
use Throwable;

final class ConfigtestJob
{
    public static function run()
    {
        global $ManagePanel, $datatextbot, $pdo, $setting, $status_cron, $user;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('configtest', 180);
        
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('configtest', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../panels.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('configtest')) {
            return;
        }
        $ManagePanel = new ManagePanel();
        $datatextbotget = \Faoxima\Core\Database\Db::select("textbot", "*",null ,null ,"fetchAll");
        $setting = \Faoxima\Core\Database\Db::select("setting","*",null,null);
        $status_cron = json_decode($setting['cron_status'],true);
        if(!$status_cron['test'])return;
        $datatextbot = \Faoxima\Cron\Services\TestServiceCleaner::buttonTexts($datatextbotget);

        (new \Faoxima\Cron\Services\TestServiceCleaner($pdo, $ManagePanel, $datatextbot))->run();
    }
}
