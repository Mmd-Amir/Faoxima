<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('disableconfig', 120);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('disableconfig', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('disableconfig')) {
    return;
}
$ManagePanel = new ManagePanel();


$rxLimit = rx_cron_batch_size(30);
$rxEligible = "Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold') AND Service_location NOT IN (SELECT name_panel FROM marzban_panel WHERE type = 'remnawave')";
$rxFinished = $pdo->prepare("SELECT id FROM user WHERE checkstatus = '2' AND NOT EXISTS (SELECT 1 FROM invoice WHERE id_user = user.id AND ($rxEligible)) ORDER BY id LIMIT " . (int) $rxLimit);
$rxFinished->execute();
$rxFinishedUsers = $rxFinished->fetchAll(PDO::FETCH_COLUMN);
$rxClear = $pdo->prepare("UPDATE user SET checkstatus = '0' WHERE id = ? AND checkstatus = '2' AND NOT EXISTS (SELECT 1 FROM invoice WHERE id_user = user.id AND ($rxEligible))");
foreach ($rxFinishedUsers as $rxUserId) {
    if (rx_cron_time_up()) break;
    $rxClear->execute([$rxUserId]);
}
if (rx_cron_time_up()) {
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('time_budget', 60);
    }
    return;
}
$rxInvoices = rx_cron_bulk_invoices($pdo, 'disableconfig', $rxEligible . " AND id_user IN (SELECT id FROM user WHERE checkstatus = '2')", [], $rxLimit);
foreach ($rxInvoices as $invoice) {
    if (rx_cron_time_up()) {
        if (function_exists('rx_cron_task_defer')) {
            rx_cron_task_defer('time_budget', 60);
        }
        break;
    }
    try {
        $rxRequested = $pdo->prepare("SELECT checkstatus FROM user WHERE id = ?");
        $rxRequested->execute([$invoice['id_user']]);
        if ((string) $rxRequested->fetchColumn() !== '2') continue;
        $rxCurrent = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if (($rxCurrent['paused_by'] ?? '') === 'credit') continue;
        $rxStatus = is_array($rxCurrent) ? (string) ($rxCurrent['status'] ?? '') : '';
        if ($rxStatus !== 'disabled') {
            if ($rxStatus !== 'active' || rx_cron_time_up()) continue;
            $ManagePanel->Change_status($invoice['username'], $invoice['Service_location']);
            $rxVerify = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
            if (!is_array($rxVerify) || ($rxVerify['status'] ?? '') !== 'disabled') continue;
        }
        update('invoice', 'Status', 'disablebyadmin', 'id_invoice', $invoice['id_invoice']);
    } catch (Throwable $e) {
        error_log('[cron:disableconfig] invoice ' . $invoice['id_invoice'] . ': ' . $e->getMessage());
    } finally {
        rx_cron_invoice_cursor('disableconfig', (string) $invoice['id_invoice']);
    }
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('disableconfig_finished');
}
