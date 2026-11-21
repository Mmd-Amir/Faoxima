<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
@chdir(__DIR__);

require_once 'config.php';
require_once 'botapi.php';
require_once 'panels.php';
require_once 'function.php';


$ManagePanel = new ManagePanel();
$headers = getallheaders();
$webhook_secret = isset($headers['X-Webhook-Secret']) ? $headers['X-Webhook-Secret'] : '';
$reportcron = \Faoxima\Core\Database\Db::select("topicid","idreport","report","reportcron","select")['idreport'];
$textservice = \Faoxima\Core\Database\Db::select("textbot","text","id_text","text_Purchased_services","select")['text'];
$setting = \Faoxima\Core\Database\Db::select("setting", "*");


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
$secret_key = \Faoxima\Core\Database\Db::select("admin", "*", "password", $decodedSecret, "count");
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
    $invoice = \Faoxima\Core\Database\Db::select("invoice","*","username",$line,"select");
    if($invoice == false)return;
    if($invoice['name_product'] == "سرویس تست")return;
    $user = \Faoxima\Core\Database\Db::select("user","*","id",$invoice['id_user'],"select");
    $data = $data['user'];
    $output =  $data['data_limit'] - $data['used_traffic'];
    $RemainingVolume = \Faoxima\Core\Support\Formatting::formatBytes($output);
    $data_limit = \Faoxima\Core\Support\Formatting::formatBytes($data['data_limit']);
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

<blockquote>نام کاربری سرویس :‌ <code>$line</code></blockquote>
<blockquote>آیدی عددی کاربر :‌ <code>{$invoice['id_user']}</code></blockquote>
<blockquote>وضعیت سرویس : {$data['status']}</blockquote>
<blockquote>حجم باقی مانده : $RemainingVolume</blockquote>
<blockquote>حجم کل سرویس : $data_limit</blockquote>";
    if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage',[
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $reportcron,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
    if($invoice['Status'] === "end_of_volume"){
        \Faoxima\Core\Database\Db::update("invoice","Status","sendedwarn", "username",$invoice['username']);
    }else{
        \Faoxima\Core\Database\Db::update("invoice","Status","end_of_volume", "username",$invoice['username']);
    }
}
elseif ($data['action'] == "reached_days_left"){
    $line = $data['username'];
    $invoice = \Faoxima\Core\Database\Db::select("invoice","*","username",$line,"select");
    if($invoice == false)return;
    if($invoice['name_product'] == "سرویس تست")return;
    $user = \Faoxima\Core\Database\Db::select("user","*","id",$invoice['id_user'],"select");
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

<blockquote>نام کاربری سرویس :‌ <code>{$data['username']}</code></blockquote>
<blockquote>آیدی عددی کاربر :‌ <code>{$invoice['id_user']}</code></blockquote>
<blockquote>وضعیت سرویس : {$data['status']}</blockquote>
<blockquote>تعداد روز باقی مانده ‌:‌$day</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage',[
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $reportcron,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
            }
        if($invoice['Status'] === "end_of_volume"){
                \Faoxima\Core\Database\Db::update("invoice","Status","sendedwarn", "username",$invoice['username']);
        }else{
            \Faoxima\Core\Database\Db::update("invoice","Status","end_of_time", "username",$invoice['username']);
                }
}
elseif(in_array($data['action'],["user_expired","user_limited"])){
        $line = $data['username'];
        $invoice = \Faoxima\Core\Database\Db::select("invoice","*","username",$line,"select");
        if($invoice == false)return;
        if($invoice['name_product'] == "سرویس تست")return;
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel","*","name_panel",$invoice['Service_location'],"select");
        $actionName = $data['action'];
        $data = $data['user'];
        if($actionName === "user_expired"){
            \Faoxima\Core\Database\Db::update("invoice","Status","end_of_time","username",$line);
        }elseif($actionName === "user_limited"){
            \Faoxima\Core\Database\Db::update("invoice","Status","end_of_volume","username",$line);
        }
        try {
            \Faoxima\Core\Database\Db::update("invoice","user_info",json_encode($data, JSON_UNESCAPED_UNICODE),"username",$line);
        } catch (Throwable $e) {
        }
        if($panel['inboundstatus'] == "oninbounddisable"){
        if($data['data_limit_reset_strategy'] == "no_reset"){
        $inbound = explode("*", $panel['inbound_deactive']);
        $proxies = [];
        $proxies[$inbound[0]] = new stdClass();;
        $inbounds[$inbound[0]][] = $inbound[1];
        $configs  = array(
            "proxies" => $proxies,
            "inbounds" => $inbounds
            );
        $modifyResult = $ManagePanel->Modifyuser($line,$panel['name_panel'],$configs);
        if (!$modifyResult['status']) {
            if (function_exists('rx_log_event')) {
                rx_log_event('WEBHOOK_MODIFYUSER_FAIL', 'Modifyuser call failed, invoice uuid not updated', [
                    'username' => $line,
                    'name_panel' => $panel['name_panel'],
                    'msg' => $modifyResult['msg'] ?? '',
                ]);
            } else {
                error_log('[webhook] Modifyuser failed for ' . $line . ': ' . ($modifyResult['msg'] ?? ''));
            }
        } else {
            \Faoxima\Core\Database\Db::update("invoice","uuid",json_encode($data['proxies']), "username",$line);
        }
         }
    }

}


