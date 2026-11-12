<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('configtest', 180);

if (!rx_cron_require_or_skip('configtest', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('configtest')) {
    return;
}
$ManagePanel = new ManagePanel();
$datatextbotget = select("textbot", "*",null ,null ,"fetchAll");
$datatxtbot = array();
$setting = select("setting","*",null,null);
$status_cron = json_decode($setting['cron_status'],true);
if(!$status_cron['test'])return;
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
$datatextbot = array(
    'crontest' => '',
);
foreach ($datatxtbot as $item) {
    if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
        $rxLimit = rx_cron_batch_size(15);
        $rxInvoices = rx_cron_bulk_invoices($pdo, 'configtest', "status != 'disabled' AND name_product = ?", ['سرویس تست'], $rxLimit);
        foreach ($rxInvoices as $result) {
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
        try {
        $resultt  = trim($result['username']);
        $marzban_list_get = select("marzban_panel","*","name_panel",$result['Service_location'],"select");
        if($marzban_list_get == false)continue;
        $user = select("user","*","id",$result['id_user'],"select");
        $get_username_Check = $ManagePanel->DataUser($result['Service_location'],$result['username']);
        if (($get_username_Check['paused_by'] ?? '') === 'credit') continue;
        if (!is_array($get_username_Check) || empty($get_username_Check['status'])) continue;
    if (!in_array($get_username_Check['status'],['active','on_hold',"Unsuccessful","disabled"])) {
        try {
            $rxReservationHeld = $pdo->prepare("SELECT id FROM queued_renewal WHERE username = :username AND status IN ('pending', 'processing', 'retry', 'reconciling') LIMIT 1");
            $rxReservationHeld->execute([':username' => $resultt]);
            if ($rxReservationHeld->fetch(PDO::FETCH_ASSOC) !== false) {
                continue;
            }
        } catch (Throwable $e) {
            continue;
        }
        if (rx_cron_time_up()) continue;
        $removeRes = $ManagePanel->RemoveUser($result['Service_location'],$resultt);
        if (!empty($removeRes['status']) && $removeRes['status'] === 'successful') {
            update("invoice","status","disabled","id_invoice",$result['id_invoice']);
            if(intval($user['status_cron']) != 0){
                $Response = json_encode([
                    'inline_keyboard' => [
                        [
                            rx_cron_btn('cron_buy_service', ['text' => "🛍 خرید سرویس", 'callback_data' => 'buy']),
                        ],
                    ]
                ]);
                $textexpire = str_replace('{username}', $resultt, $datatextbot['crontest']);
                sendmessage($result['id_user'], $textexpire, $Response, 'HTML');
            }
        }
    }
        } catch (Throwable $e) {
            error_log('[cron:configtest] invoice ' . $result['id_invoice'] . ': ' . $e->getMessage());
        } finally {
            rx_cron_invoice_cursor('configtest', (string) $result['id_invoice']);
        }
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('configtest_finished');
}
