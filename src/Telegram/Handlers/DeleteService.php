<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $ManagePanel, $admin_ids, $connect, $dataget, $datatextbot, $from_id, $keyboard, $message_id, $pdo, $telegramRoute, $text, $textbotlang, $user, $username.
 * Inherited or exported by reference: $day, $id_invoice, $marzban_list_get, $nameloc, $output, $status, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DeleteService
{
    public static function handle(
        $ManagePanel,
        $admin_ids,
        $connect,
        $dataget,
        $datatextbot,
        &$day,
        $from_id,
        &$id_invoice,
        $keyboard,
        &$marzban_list_get,
        $message_id,
        &$nameloc,
        &$output,
        $pdo,
        &$status,
        &$stmt,
        $telegramRoute,
        $text,
        $textbotlang,
        $user,
        $username
    ) {
        switch ($telegramRoute) {
        case 'delete_prompt':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            if (is_array($nameloc) && (string) $nameloc['id_user'] === (string) $from_id) {
                if (method_exists(\Faoxima\Payments\Settlement\PendingInvoices::class, 'deleteInvoiceFromList')) {
                    \Faoxima\Payments\Settlement\PendingInvoices::deleteInvoiceFromList($id_invoice, $from_id);
                } else {
                    $stmtDelete = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :invoice_id AND id_user = :user_id");
                    $stmtDelete->bindParam(':invoice_id', $id_invoice);
                    $stmtDelete->bindParam(':user_id', $from_id);
                    $stmtDelete->execute();
                }
                sendmessage($from_id, $datatextbot['dyn_errors_service_removed'] ?? "📌 سرویس از لیست شما حذف شد", null, 'html');
            } else {
                sendmessage($from_id, $datatextbot['dyn_errors_service_removal_unavailable'] ?? "❌ امکان حذف سرویس وجود ندارد.", null, 'html');
            }
            break;
        case 'delete_start':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler removeserviceuser on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'removeserviceuser',
                    ]);
                }
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "id_invoice", $id_invoice);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $datatextbot['dyn_errors_removal_reason_prompt'] ?? "📌 دلیل حذف سرویس خود را ارسال کنید.", $bakinfos);
            \Faoxima\Telegram\Navigation\Steps::step("getdisdeleteconfig", $from_id);
            break;
        case 'delete_description':
            $userdata = json_decode($user['Processing_value'], true);
            $id_invoice = $userdata['id_invoice'];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "descritionsremove", $text);
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            if ($nameloc['name_product'] == "سرویس تست") {
                sendmessage($from_id, $textbotlang['users']['stateus']['errorusertest'], null, 'html');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $requestcheck = \Faoxima\Core\Database\Db::select("cancel_service", "*", "username", $nameloc['username'], "count");
            if ($requestcheck != 0) {
                sendmessage($from_id, $textbotlang['users']['stateus']['errorexits'], null, 'html');
                return;
            }
            $confirmremove = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_request_delete_confirm'), 'callback_data' => "confirmremoveservices-$id_invoice"],
                    ],
                ]
            ]);
            sendmessage($from_id, $textbotlang['users']['stateus']['descriptions_removeservice'], $confirmremove, "html");
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 'delete_confirm':
            $userdata = json_decode($user['Processing_value'], true);
            $stmt = $pdo->prepare("SELECT * FROM cancel_service WHERE id_user = :from_id AND status = 'waiting'");
            $stmt->execute([
                ':from_id' => $from_id
            ]);
            $checkcancelservicecount = $stmt->rowCount();
            if ($checkcancelservicecount != 0) {
                sendmessage($from_id, $textbotlang['users']['stateus']['exitsrequsts'], null, 'HTML');
                return;
            }
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmremoveservices on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmremoveservices',
                    ]);
                }
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $stmt = $connect->prepare("INSERT IGNORE INTO cancel_service (id_user, username,description,status) VALUES (?, ?, ?, ?)");
            $descriptions = "0";
            $Status = "waiting";
            $stmt->bind_param("ssss", $from_id, $nameloc['username'], $descriptions, $Status);
            $stmt->execute();
            $stmt->close();
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($DataUserOut['status'] == "Unsuccessful") {
                sendmessage($from_id, $textbotlang['users']['stateus']['panelNotConnected'], null, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }

            $lastonline = \Faoxima\Services\Delivery\ServiceDelivery::formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);
            $status = $DataUserOut['status'];
            $bExp4 = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
            $bDl4 = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
            $bUt4 = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
            if ($bExp4 > 0 && $bExp4 <= time()) {
                $status = 'expired';
            } elseif ($bDl4 > 0.0 && $bUt4 >= $bDl4) {
                $status = 'limited';
            }
            $status_map4 = [
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
            $status_var = $status_map4[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

            $expirationDate = $DataUserOut['expire']
                ? jdate('Y/m/d', $DataUserOut['expire'])
                : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

            $LastTraffic = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

            $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
            $RemainingVolume = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($output) : "نامحدود";

            $usedTrafficGb = $DataUserOut['used_traffic'] ? \Faoxima\Core\Support\Formatting::formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

            $timeDiff = $DataUserOut['expire'] - time();
            $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

            $textinfoadmin = "سلام ادمین 👋

📌 یک درخواست حذف سرویس  توسط کاربر برای شما ارسال شده است. لطفا بررسی کرده و در صورت درست بودن و موافقت تایید کنید.

📊 اطلاعات سرویس کاربر :
آیدی عددی کاربر : $from_id
نام کاربری کاربر : @$username
نام کاربری کانفیگ : {$nameloc['username']}
وضعیت سرویس : $status_var
موقعیت سرویس : {$nameloc['Service_location']}
کد سرویس:{$nameloc['id_invoice']}

🟢 اخرین زمان اتصال شما : $lastonline

📥 حجم مصرفی : $usedTrafficGb
♾ حجم سرویس : $LastTraffic
🪫 حجم باقی مانده : $RemainingVolume
📅 فعال تا تاریخ : $expirationDate ($day)

<b>❌ ادمین گرامی توجه داشته باشید دکمه حذف سرویس که میزنید ربات خودکار حساب میکند و احتمال اشتباه وجود دارد پیشنهاد می شود از  حذف دستی  استفاده نمایید</b>

دلیل حذف سرویس : {$userdata['descritionsremove']}";
            $confirmremoveadmin = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "❌حذف دستی", 'callback_data' => "remoceserviceadminmanual-{$nameloc['id_invoice']}"],
                        ['text' => "❌حذف سرویس", 'callback_data' => "remoceserviceadmin-{$nameloc['id_invoice']}"],
                        ['text' => "❌لغو حذف", 'callback_data' => "rejectremoceserviceadmin-{$nameloc['id_invoice']}"],
                    ],
                ]
            ]);
            foreach ($admin_ids as $admin) {
                sendmessage($admin, $textinfoadmin, $confirmremoveadmin, 'html');
                \Faoxima\Telegram\Navigation\Steps::step("home", $admin);
            }
            deletemessage($from_id, $message_id);
            sendmessage($from_id, $textbotlang['users']['stateus']['sendrequestsremove'], $keyboard, 'html');
            break;
        }

        return true;
    }
}
