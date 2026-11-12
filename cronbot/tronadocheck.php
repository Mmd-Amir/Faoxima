<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('tronadocheck', 60);

ini_set('error_log', 'error_log');

$ctx = rx_cron_load_payment_context();
if (empty($ctx['db_ready'])) {
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('payment_context_unavailable', 180);
    }
    return;
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $pdo, $ManagePanel, $setting;
$ManagePanel = $ctx['managePanel'];
$setting = $ctx['setting'];

if (!($pdo instanceof PDO)) {
    error_log('[tronadocheck] no PDO connection');
    return;
}

if (!function_exists('tronadoV2RunCron')) {
    error_log('[tronadocheck] tronado v2 functions not available');
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('tronado_functions_unavailable', 300);
    }
    return;
}

tronadoV2RunCron();
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('tronado_poll_finished');
}
