<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('activeconfig', 120);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('activeconfig', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('activeconfig')) {
    return;
}
$ManagePanel = new ManagePanel();


$rxLimit = rx_cron_batch_size(30);
$rxEligible = "Status = 'disablebyadmin' AND Service_location NOT IN (SELECT name_panel FROM marzban_panel WHERE type = 'remnawave')";
$rxFinished = $pdo->prepare("SELECT id FROM user WHERE checkstatus = '1' AND NOT EXISTS (SELECT 1 FROM invoice WHERE id_user = user.id AND ($rxEligible)) ORDER BY id LIMIT " . (int) $rxLimit);
$rxFinished->execute();
$rxFinishedUsers = $rxFinished->fetchAll(PDO::FETCH_COLUMN);
$rxClear = $pdo->prepare("UPDATE user SET checkstatus = '0' WHERE id = ? AND checkstatus = '1' AND NOT EXISTS (SELECT 1 FROM invoice WHERE id_user = user.id AND ($rxEligible))");
foreach ($rxFinishedUsers as $rxUserId) {
    if (rx_cron_time_up()) break;
    $rxClear->execute([$rxUserId]);
}
if (rx_cron_time_up()) return;
$rxInvoices = rx_cron_bulk_invoices($pdo, 'activeconfig', $rxEligible . " AND id_user IN (SELECT id FROM user WHERE checkstatus = '1')", [], $rxLimit);
foreach ($rxInvoices as $invoice) {
    if (rx_cron_time_up()) break;
    try {
        $rxRequested = $pdo->prepare("SELECT checkstatus FROM user WHERE id = ?");
        $rxRequested->execute([$invoice['id_user']]);
        if ((string) $rxRequested->fetchColumn() !== '1') continue;
        $rxCurrent = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if (($rxCurrent['paused_by'] ?? '') === 'credit') continue;
        $rxStatus = is_array($rxCurrent) ? (string) ($rxCurrent['status'] ?? '') : '';
        if ($rxStatus !== 'active') {
            if ($rxStatus !== 'disabled' || rx_cron_time_up()) continue;
            $ManagePanel->Change_status($invoice['username'], $invoice['Service_location']);
            $rxVerify = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
            if (!is_array($rxVerify) || ($rxVerify['status'] ?? '') !== 'active') continue;
        }
        update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
    } catch (Throwable $e) {
        error_log('[cron:activeconfig] invoice ' . $invoice['id_invoice'] . ': ' . $e->getMessage());
    } finally {
        rx_cron_invoice_cursor('activeconfig', (string) $invoice['id_invoice']);
    }
}
