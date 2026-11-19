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


$rxEligible = "Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold') AND Service_location NOT IN (SELECT name_panel FROM marzban_panel WHERE type = 'remnawave')";
if (!rx_cron_bulk_service_status($pdo, $ManagePanel, 'disableconfig', $rxEligible, '2', 'active', 'disabled', 'disablebyadmin')) {
    return;
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('disableconfig_finished');
}
