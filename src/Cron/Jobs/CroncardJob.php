<?php

namespace Faoxima\Cron\Jobs;

use ManagePanel;
use PDO;
use Throwable;

final class CroncardJob
{
    public static function run()
    {
        global $ManagePanel, $datatextbot, $pdo, $setting, $textbotlang;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
\Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('croncard', 180);

ini_set('error_log', 'error_log');
if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('croncard', [
    (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../panels.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../keyboard.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../jdf.php',
])) {
    return;
}
if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('croncard')) {
    return;
}
if (is_file((dirname(__DIR__, 3) . '/cronbot') . '/../vendor/autoload.php')) {
    require (dirname(__DIR__, 3) . '/cronbot') . '/../vendor/autoload.php';
}
$ManagePanel = new ManagePanel();
$setting = \Faoxima\Core\Database\Db::select("setting", "*");
$paymentreports = \Faoxima\Core\Database\Db::select("topicid","idreport","report","paymentreport","select")['idreport'];
$datatextbotget = \Faoxima\Core\Database\Db::select("textbot", "*",null ,null ,"fetchAll");
if (!is_callable([\Faoxima\Payments\Fulfillment\Schema::class, 'ensureSchema']) || !\Faoxima\Payments\Fulfillment\Schema::ensureSchema($pdo)) {
    error_log('[croncard] payment_fulfillment is unavailable; skipping run without touching payments');
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('payment_fulfillment_unavailable', 180);
    return;
}
$sweep = new \Faoxima\Cron\Services\CardReceiptSweep($pdo, $setting, $paymentreports);
$sweep->configureTrust();
$datatextbot = \Faoxima\Cron\Services\CardReceiptSweep::buttonTexts($datatextbotget);
$sweep->collectQueue();
$sweep->processQueue();
$sweep->finish();
    }
}
