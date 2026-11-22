<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $ManagePanel, $backuser, $connect, $dataget, $from_id, $keyboard, $message_id, $telegramRoute, $text, $textbotlang, $user.
 * Inherited or exported by reference: $id_invoice, $marzban_list_get, $nameloc, $stmt, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TransferService
{
    public static function handle(
        $ManagePanel,
        $backuser,
        $connect,
        $dataget,
        $from_id,
        &$id_invoice,
        $keyboard,
        &$marzban_list_get,
        $message_id,
        &$nameloc,
        &$stmt,
        $telegramRoute,
        $text,
        $textbotlang,
        $user,
        &$value
    ) {
        switch ($telegramRoute) {
        case 'transfer_start':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler transfer on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'transfer',
                    ]);
                }
                return;
            }
            if ($nameloc['name_product'] == "سرویس تست") {
                sendmessage($from_id, $textbotlang['Admin']['transfor']['transfornotvalid'], null, 'html');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['transfor']['discription'], $bakinfos);
            \Faoxima\Telegram\Navigation\Steps::step("getidfortransfer", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $nameloc['username'], "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", $nameloc['id_invoice'], "id", $from_id);
            break;
        case 'transfer_recipient':
            if (!\Faoxima\Core\Database\Db::userExists($text)) {
                sendmessage($from_id, $textbotlang['Admin']['transfor']['notusertrns'], $backuser, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $text, "id", $from_id);
            $confirmtransfer = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_confirm_transfer'), 'callback_data' => "confrimtransfers_{$user['Processing_value_tow']}"],
                    ],
                ]
            ]);
            sendmessage($from_id, $textbotlang['Admin']['transfor']['confirm'], $confirmtransfer, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 'transfer_confirm':
            if ($from_id == $user['Processing_value_one']) {
                sendmessage($from_id, $textbotlang['Admin']['transfor']['notsendserviceyou'], $keyboard, 'HTML');
                return;
            }
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confrimtransfers on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confrimtransfers',
                    ]);
                }
                return;
            }
            \Faoxima\Core\Database\Db::update("invoice", "id_user", $user['Processing_value_one'], "id_invoice", $id_invoice);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "backorder"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['transfor']['confirmed'], $bakinfos);
            $texttransfer = \Faoxima\Telegram\Support\MessageTemplates::get('service_transfer_received_notice', ['username' => $nameloc['username'], 'from_id' => $from_id]);
            sendmessage($user['Processing_value_one'], $texttransfer, $keyboard, 'HTML');
            $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
            $value = $user['Processing_value_one'];
            $dateacc = date('Y/m/d H:i:s');
            $type = "transfertouser";
            $price = "0";
            $stmt->bind_param("ssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $price);
            $stmt->execute();
            $stmt->close();
            break;
        }

        return true;
    }
}
