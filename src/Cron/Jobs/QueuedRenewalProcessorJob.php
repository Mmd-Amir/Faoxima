<?php

namespace Faoxima\Cron\Jobs;

use ManagePanel;
use RuntimeException;
use Throwable;

final class QueuedRenewalProcessorJob
{
    public static function run()
    {
        global $ManagePanel, $pdo, $setting, $text;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('queued_renewal', 600);

        ini_set('error_log', 'error_log');

        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('queued_renewal', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../panels.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('queued_renewal')) {
            return;
        }
        if (!\Faoxima\Services\Renewal\ReservationStore::ensureSchema($pdo)) {
            error_log('[queued_renewal] renewal schema unavailable; skipping run');
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('renewal_schema_unavailable', 300);
            }
            return;
        }

        $ManagePanel = new ManagePanel();
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        $reportCron = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "reportcron", "select")['idreport'];

        $renewals = new \Faoxima\Cron\Services\QueuedRenewals($pdo, $ManagePanel, $setting, $reportCron, dirname(__DIR__, 3));
        if (!$renewals->loadNotifications()) {
            return;
        }
        $renewals->drainNotifications();
        $renewals->recoverStale();
        $renewals->processDueReservations();
        $renewals->reconcileOperations();
        $renewals->recoverWalletFinancials();
        \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('queued_renewal_finished');
    }
}
