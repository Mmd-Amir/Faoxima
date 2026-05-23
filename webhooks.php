<?php

require_once 'config.php';
require_once 'botapi.php';
require_once 'panels.php';
require_once 'function.php';


$ManagePanel = new ManagePanel();
$headers = getallheaders();
$webhook_secret = isset($headers['X-Webhook-Secret']) ? $headers['X-Webhook-Secret'] : '';
$reportcron = select("topicid","idreport","report","reportcron","select")['idreport'];
$textservice = select("textbot","text","id_text","text_Purchased_services","select")['text'];
$setting = select("setting", "*");


$decodedSecret = base64_decode($webhook_secret, true);
if (!is_string($decodedSecret) || strlen($decodedSecret) < 8) {
    if (function_exists('rx_log_event')) {
        rx_log_event('WEBHOOK_REJECT', 'Webhook secret missing or too short', [
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'has_secret' => $webhook_secret !== '' ? 'yes' : 'no',
        ]);
    } else {
        error_log('[webhook] rejected: missing/short secret');
    }
    http_response_code(401);
    return;
}
$secret_key = select("admin", "*", "password", $decodedSecret, "count");
if ($secret_key == 0) {
    if (function_exists('rx_log_event')) {
        rx_log_event('WEBHOOK_AUTH_FAIL', 'Webhook secret did not match any admin row', [
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    } else {
        error_log('[webhook] auth failed');
    }
    http_response_code(401);
    return;
}
$rawWebhookBody = file_get_contents("php://input");
$decodedBody = json_decode($rawWebhookBody, true);
if (!is_array($decodedBody) || !isset($decodedBody[0]) || !is_array($decodedBody[0])) {
    if (function_exists('rx_log_event')) {
        rx_log_event('WEBHOOK_BAD_BODY', 'Webhook body was not the expected [object] shape', [
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }
    http_response_code(400);
    return;
}
$data = $decodedBody[0];
if (!isset($data['action']) || !is_string($data['action'])) {
    http_response_code(400);
    return;
}
if($data['action'] == "reached_usage_percent"){
    $line = $data['username'];
    $invoice = select("invoice","*","username",$line,"select");
    if($invoice == false)return;
    if($invoice['name_product'] == "سرویس تست")return;
    $user = select("user","*","id",$invoice['id_user'],"select");
    $data = $data['user'];
    $output =  $data['data_limit'] - $data['used_traffic'];
    $RemainingVolume = formatBytes($output);
    $data_limit = formatBytes($data['data_limit']);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "💊 تمدید سرویس", 'callback_data' => 'extend_' . $invoice['id_invoice']],
            ],
        ]
    ]);
    $text = "با سلام خدمت شما کاربر گرامی 👋
🚨 از حجم سرویس $line تنها $RemainingVolume باقی مانده است. لطفاً در صورت تمایل برای تمدید سرویستون از طریق بخش «{$textservice}» اقدام بفرمایین";
if(intval($user['status_cron']) != 0){
    sendmessage($invoice['id_user'], $text, $Response, 'HTML');
}
    $text_report = "📌 اطلاعیه کرون حجم

نام کاربری سرویس :‌ <code>$line</code>
آیدی عددی کاربر :‌ <code>{$invoice['id_user']}</code>
وضعیت سرویس : {$data['status']}
حجم باقی مانده : $RemainingVolume
حجم کل سرویس : $data_limit";
    if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage',[
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $reportcron,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
    if($invoice['Status'] === "end_of_volume"){
        update("invoice","Status","sendedwarn", "username",$invoice['username']);
    }else{
        update("invoice","Status","end_of_volume", "username",$invoice['username']);
    }
}
elseif ($data['action'] == "reached_days_left"){
    $line = $data['username'];
    $invoice = select("invoice","*","username",$line,"select");
    if($invoice == false)return;
    if($invoice['name_product'] == "سرویس تست")return;
    $user = select("user","*","id",$invoice['id_user'],"select");
    $data = $data['user'];
    $timeservice = $data['expire'] - time();
    $day = intval($timeservice / 86400);
    if($day <=0){
        $day = intval($timeservice / 3600) . "ساعت";
    }else{
        $day = $day. "روز";
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "💊 تمدید سرویس", 'callback_data' => 'extend_' . $invoice['id_invoice']],
            ],
        ]
    ]);
    $text = "با سلام خدمت شما کاربر گرامی 👋
📌 از مهلت زمانی استفاده از سرویس {$invoice['username']} فقط $day باقی مانده است. لطفاً در صورت تمایل برای تمدید این سرویس، از طریق بخش «{$textservice}» اقدام بفرمایین. با تشکر از همراهی شما";
if(intval($user['status_cron']) != 0){
    sendmessage($invoice['id_user'], $text, $Response, 'HTML');
}
    $text_report = "📌 اطلاعیه کرون زمان

نام کاربری سرویس :‌ <code>{$data['username']}</code>
آیدی عددی کاربر :‌ <code>{$invoice['id_user']}</code>
وضعیت سرویس : {$data['status']}
تعداد روز باقی مانده ‌:‌$day";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage',[
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $reportcron,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
            }
        if($invoice['Status'] === "end_of_volume"){
                update("invoice","Status","sendedwarn", "username",$invoice['username']);
        }else{
            update("invoice","Status","end_of_time", "username",$invoice['username']);
                }
}
elseif(in_array($data['action'],["user_expired","user_limited"])){
        $line = $data['username'];
        $invoice = select("invoice","*","username",$line,"select");
        if($invoice == false)return;
        if($invoice['name_product'] == "سرویس تست")return;
        $panel = select("marzban_panel","*","name_panel",$invoice['Service_location'],"select");
        $data = $data['user'];
        if($panel['inboundstatus'] == "oninbounddisable"){
        if($data['data_limit_reset_strategy'] == "no_reset"){
        $inbound = explode("*", $panel['inbound_deactive']);
        update("invoice","uuid",json_encode($data['proxies']), "username",$line);
        $proxies = [];
        $proxies[$inbound[0]] = new stdClass();;
        $inbounds[$inbound[0]][] = $inbound[1];
        $configs  = array(
            "proxies" => $proxies,
            "inbounds" => $inbounds
            );
        $ManagePanel->Modifyuser($line,$panel['name_panel'],$configs);
         }
    }

}


