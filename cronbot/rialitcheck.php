<?php
require_once __DIR__ . '/_init.php';
if ((int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0) > 0) {
    return;
}
rx_cron_boot('rialitcheck', 180);

ini_set('error_log', 'error_log');

$ctx = rx_cron_require_payment_context();
if ($ctx === null) {
    return;
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $pdo, $setting;
$setting = $ctx['setting'];

if (!($pdo instanceof PDO)) {
    error_log('[rialitcheck] no PDO connection');
    return;
}

if (!function_exists('rialitVerifyPayment') || !function_exists('rialitProcessPendingOrders')) {
    error_log('[rialitcheck] rialit functions not available');
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('rialit_functions_unavailable', 300);
    }
    return;
}

rialit_cron_webhook_events($pdo);
rialit_cron_pending_orders($pdo);
rialit_cron_paid_recovery($pdo);
if (function_exists('rialitPruneWebhookEvents')) {
    rialitPruneWebhookEvents();
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('rialit_poll_finished');
}

function rialit_cron_webhook_events(PDO $pdo): void
{
    if (!function_exists('rialitProcessWebhookEvents')) {
        return;
    }
    $stats = rialitProcessWebhookEvents(20);
    if (($stats['completed'] + $stats['retry'] + $stats['failed']) > 0 && function_exists('rx_log_event')) {
        rx_log_event('RIALIT_WEBHOOK_EVENTS', 'Processed queued Rialit webhook events', $stats);
    }
}

function rialit_cron_pending_orders(PDO $pdo): void
{
    $stats = rialitProcessPendingOrders(20);
    if (($stats['checked'] + $stats['paid']) > 0 && function_exists('rx_log_event')) {
        rx_log_event('RIALIT_POLL_RESULT', 'Reconciled pending Rialit payments', $stats);
    }
}

function rialit_cron_paid_recovery(PDO $pdo): void
{
    if (!function_exists('rialitRecoverPaidOrders')) {
        return;
    }
    $recovered = rialitRecoverPaidOrders(10);
    if ($recovered > 0 && function_exists('rx_log_event')) {
        rx_log_event('RIALIT_FULFILLMENT_RECOVERY', 'Recovered paid Rialit orders', ['count' => $recovered]);
    }
}
