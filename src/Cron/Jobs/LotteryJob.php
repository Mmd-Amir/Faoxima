<?php

namespace Faoxima\Cron\Jobs;

use PDO;
use Throwable;
use RuntimeException;

final class LotteryJob
{
    public static function run()
    {
        global $otherreport, $pdo, $setting;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('lottery', 120);

        ini_set('error_log', 'error_log');

        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('lottery', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../jdf.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('lottery')) {
            return;
        }
        if (is_file((dirname(__DIR__, 3) . '/cronbot') . '/../vendor/autoload.php')) {
            require (dirname(__DIR__, 3) . '/cronbot') . '/../vendor/autoload.php';
        }

        $setting = \Faoxima\Core\Database\Db::select("setting", "*");

        $draw = \Faoxima\Cron\Services\LotteryDraw::begin($setting, $pdo, dirname(__DIR__, 3));
        if ($draw === null) {
            return;
        }
        $draw->execute();
    }
}
