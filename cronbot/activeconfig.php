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


$rxEligible = "Status = 'disablebyadmin' AND Service_location NOT IN (SELECT name_panel FROM marzban_panel WHERE type = 'remnawave')";
if (!rx_cron_bulk_service_status($pdo, $ManagePanel, 'activeconfig', $rxEligible, '1', 'disabled', 'active', 'active')) {
    return;
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('activeconfig_finished');
}
