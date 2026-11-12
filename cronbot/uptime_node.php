<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('uptime_node', 180);

ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../PasarGuard.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

if (!rx_cron_db_ready('uptime_node')) {
    return;
}

$errorreport = select("topicid","idreport","report","errorreport","select")['idreport'];
$setting = select("setting", "*");
if (!is_array($setting)) {
    error_log('uptime_node: setting row is not available.');
    return;
}

if (!array_key_exists('cron_status', $setting)) {
    error_log('uptime_node: cron_status is missing from setting.');
    return;
}

$status_cron = json_decode($setting['cron_status'], true);
if (!is_array($status_cron)) {
    error_log('uptime_node: invalid cron_status configuration.');
    return;
}

if (empty($status_cron['uptime_node'])) {
    return;
}
function rxUptimeCheckPanels($panels, $errorreport, $setting, $getNodesFn)
{
    foreach ($panels as $location) {
        if (!is_array($location)) {
            continue;
        }
        $nodesResponse = $getNodesFn($location['name_panel']);
        if (!is_array($nodesResponse)) {
            continue;
        }
        if (!empty($nodesResponse['error'])) {
            error_log("uptime_node: " . $nodesResponse['error']);
            continue;
        }
        if (isset($nodesResponse['status']) && $nodesResponse['status'] != 200) {
            error_log("uptime_node: unexpected status code {$nodesResponse['status']} for {$location['name_panel']}");
            continue;
        }
        if (empty($nodesResponse['body'])) {
            continue;
        }
        $decodedNodes = json_decode($nodesResponse['body'], true);
        if (!is_array($decodedNodes)) {
            error_log("uptime_node: invalid nodes payload for {$location['name_panel']}");
            continue;
        }
        if (count($decodedNodes) == 0) {
            continue;
        }
        foreach ($decodedNodes as $data) {
            if (!is_array($data)) {
                continue;
            }
            $status = $data['status'] ?? null;
            if (!in_array($status, ["connected", "disabled"], true)) {
                $nodeName = $data['name'] ?? 'نامشخص';
                $message = $data['message'] ?? 'بدون پیام';
                $textnode = "🚨 ادمین عزیز نود با اسم {$nodeName} متصل نیست.
<blockquote>وضعیت نود : {$status}</blockquote>
<blockquote>✍️ دلیل خطا : <code> {$message}</code></blockquote>";
                if (strlen($setting['Channel_Report']) > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $errorreport,
                        'text' => $textnode,
                        'parse_mode' => "HTML"
                    ]);
                }
            }
        }
    }
}

$marzbanlist = select("marzban_panel", "*","type" ,"marzban" ,"fetchAll");
$marzbanlist = is_array($marzbanlist) ? $marzbanlist : [];
rxUptimeCheckPanels($marzbanlist, $errorreport, $setting, 'Get_Nodes');

$pasarguardlist = select("marzban_panel", "*","type" ,"pasarguard" ,"fetchAll");
$pasarguardlist = is_array($pasarguardlist) ? $pasarguardlist : [];
if (function_exists('pasarguardGetNodes')) {
    rxUptimeCheckPanels($pasarguardlist, $errorreport, $setting, 'pasarguardGetNodes');
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('uptime_node_finished');
}

