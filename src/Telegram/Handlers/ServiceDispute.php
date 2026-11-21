<?php

namespace Faoxima\Telegram\Handlers;

use DateTime;
use DateTimeZone;

/**
 * Handler stage. Inherited by value: $ManagePanel, $admin_ids, $dataget, $datatextbot, $from_id, $message_id, $telegramRoute, $text, $textbotlang, $user, $username.
 * Inherited or exported by reference: $Percent, $Response, $adminrulecheck, $day, $id_invoice, $lastupdate, $nameloc, $output, $status.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceDispute
{
    public static function handle(
        $ManagePanel,
        &$Percent,
        &$Response,
        $admin_ids,
        &$adminrulecheck,
        $dataget,
        $datatextbot,
        &$day,
        $from_id,
        &$id_invoice,
        &$lastupdate,
        $message_id,
        &$nameloc,
        &$output,
        &$status,
        $telegramRoute,
        $text,
        $textbotlang,
        $user,
        $username
    ) {
        switch ($telegramRoute) {
        case 'dispute_start':
            $id_invoice = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $id_invoice, "id", $from_id);
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler disorder on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'disorder',
                    ]);
                }
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            $textdisorder = "❓ علت اختلال خود را بنویسید

🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )";
            $keyboarddisorder = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textdisorder, $keyboarddisorder);
            \Faoxima\Telegram\Navigation\Steps::step("getdesdisorder", $from_id);
            break;
        case 'dispute_description':
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $text, "id", $from_id);
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            $textdisorder = "❓ آیا از ارسال گزارش اختلال اطمینان دارید

🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )";
            $keyboarddisorder = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "✅ ارسال گزارش اختلال", 'callback_data' => "confirmdisorders-" . $user['Processing_value']],
                    ],
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
                    ]
                ]
            ]);
            sendmessage($from_id, $textdisorder, $keyboarddisorder, 'html');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 'dispute_confirm':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmdisorders on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmdisorders',
                    ]);
                }
                return;
            }
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
                    ],
                ]
            ]);
            $textdisorder = "
    ⚠️ یک کاربر با اطلاعات زیر یک گزارش اختلال در سرویس ثبت کرده است .

- نام کاربری : @$username
- آیدی عددی : $from_id
- نام کاربری کانفیگ : {$nameloc['username']}
- نام پلن تهیه شده : {$nameloc['name_product']}
- موقعیت سرویس : {$nameloc['Service_location']}
- توضیحات اختلال : {$user['Processing_value']}";
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            $lastonline = \Faoxima\Services\Delivery\ServiceDelivery::formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

            $status = $DataUserOut['status'];
            $bExp3 = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
            $bDl3 = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
            $bUt3 = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
            if ($bExp3 > 0 && $bExp3 <= time()) {
                $status = 'expired';
            } elseif ($bDl3 > 0.0 && $bUt3 >= $bDl3) {
                $status = 'limited';
            }
            $status_map3 = [
                'active' => $textbotlang['users']['stateus']['active'],
                'limited' => $textbotlang['users']['stateus']['limited'],
                'end_of_volume' => $textbotlang['users']['stateus']['limited'],
                'disabled' => $textbotlang['users']['stateus']['disabled'],
                'deactivev' => $textbotlang['users']['stateus']['disabled'],
                'expired' => $textbotlang['users']['stateus']['expired'],
                'end_of_time' => $textbotlang['users']['stateus']['expired'],
                'on_hold' => $textbotlang['users']['stateus']['on_hold'],
                'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
                'Unknown' => $textbotlang['users']['stateus']['Unknown']
            ];
            $status_var = $status_map3[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

            $expirationDate = $DataUserOut['expire']
                ? jdate('Y/m/d', $DataUserOut['expire'])
                : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

            $LastTraffic = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

            $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
            $RemainingVolume = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($output) : "نامحدود";

            $usedTrafficGb = $DataUserOut['used_traffic'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

            $timeDiff = $DataUserOut['expire'] - time();
            $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

            if ($DataUserOut['sub_updated_at'] !== null) {
                $sub_updated = $DataUserOut['sub_updated_at'];
                $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
                $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
                $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
            }
            if ($DataUserOut['data_limit'] != null && $DataUserOut['used_traffic'] != null) {
                $Percent = ($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) * 100 / $DataUserOut['data_limit'];
            } else {
                $Percent = "100";
            }
            if ($Percent < 0)
                $Percent = -($Percent);
            $Percent = round($Percent, 2);
            $textdisorder .= "

 وضعیت سرویس : $status_var

🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)

لینک اشتراک کاربر :
<code>{$DataUserOut['subscription_url']}</code>

📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>";
            foreach ($admin_ids as $admin) {
                $adminrulecheck = \Faoxima\Core\Database\Db::select("admin", "*", "id_admin", $admin, "select");
                if ($adminrulecheck['rule'] == "Seller")
                    continue;
                sendmessage($admin, $textdisorder, $Response, 'html');
            }
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $datatextbot['dyn_errors_removal_request_thanks'] ?? "✅ با تشکر از ثبت درخواست ،درخواست شما  ارسال شده و درحال بررسی توسط پشتیبانی می باشد.", $bakinfos, 'html');
            break;
        }

        return true;
    }
}
