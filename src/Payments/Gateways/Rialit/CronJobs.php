<?php

namespace Faoxima\Payments\Gateways\Rialit;

use PDO;

final class CronJobs
{
    public static function rialit_cron_webhook_events(PDO $pdo): void
    {
        if (!method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitProcessWebhookEvents')) {
            return;
        }
        $stats = \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitProcessWebhookEvents(20);
        if (($stats['completed'] + $stats['retry'] + $stats['failed']) > 0 && function_exists('rx_log_event')) {
            rx_log_event('RIALIT_WEBHOOK_EVENTS', 'Processed queued Rialit webhook events', $stats);
        }
    }

    public static function rialit_cron_pending_orders(PDO $pdo): void
    {
        $stats = \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitProcessPendingOrders(20);
        if (($stats['checked'] + $stats['paid']) > 0 && function_exists('rx_log_event')) {
            rx_log_event('RIALIT_POLL_RESULT', 'Reconciled pending Rialit payments', $stats);
        }
    }

    public static function rialit_cron_paid_recovery(PDO $pdo): void
    {
        if (!is_callable([\Faoxima\Payments\Gateways\Rialit\WebhookQueue::class, 'recoverPaidOrders'])) {
            return;
        }
        $recovered = \Faoxima\Payments\Gateways\Rialit\WebhookQueue::recoverPaidOrders($pdo, 10);
        if ($recovered > 0 && function_exists('rx_log_event')) {
            rx_log_event('RIALIT_FULFILLMENT_RECOVERY', 'Recovered paid Rialit orders', ['count' => $recovered]);
        }
    }
}
