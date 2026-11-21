<?php

namespace Faoxima\Cron\Jobs;

use PDO;

final class RialitcheckJob
{
    public static function run()
    {
        global $pdo, $setting;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        if ((int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0) > 0) {
            return;
        }
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('rialitcheck', 180);

        ini_set('error_log', 'error_log');

        $ctx = \Faoxima\Cron\Runtime\CronBatches::rx_cron_require_payment_context();
        if ($ctx === null) {
            return;
        }
        require_once (dirname(__DIR__, 3) . '/cronbot') . '/../src/bootstrap.php';

        global $pdo, $setting;
        $setting = $ctx['setting'];

        if (!($pdo instanceof PDO)) {
            error_log('[rialitcheck] no PDO connection');
            return;
        }

        if (!method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitVerifyPayment') || !method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitProcessPendingOrders')) {
            error_log('[rialitcheck] rialit functions not available');
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('rialit_functions_unavailable', 300);
            }
            return;
        }

        \Faoxima\Payments\Gateways\Rialit\CronJobs::rialit_cron_webhook_events($pdo);
        \Faoxima\Payments\Gateways\Rialit\CronJobs::rialit_cron_pending_orders($pdo);
        \Faoxima\Payments\Gateways\Rialit\CronJobs::rialit_cron_paid_recovery($pdo);
        if (is_callable([\Faoxima\Payments\Gateways\Rialit\WebhookQueue::class, 'pruneWebhookEvents'])) {
            \Faoxima\Payments\Gateways\Rialit\WebhookQueue::pruneWebhookEvents($pdo);
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('rialit_poll_finished');
        }
    }
}
