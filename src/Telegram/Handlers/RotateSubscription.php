<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $ManagePanel, $dataget, $datatextbot, $first_name, $from_id, $message_id, $otherservice, $setting, $telegramRoute, $textbotlang, $user, $username.
 * Inherited or exported by reference: $id_invoice, $marzban_list_get, $nameloc.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RotateSubscription
{
    public static function handle(
        $ManagePanel,
        $dataget,
        $datatextbot,
        $first_name,
        $from_id,
        &$id_invoice,
        &$marzban_list_get,
        $message_id,
        &$nameloc,
        $otherservice,
        $setting,
        $telegramRoute,
        $textbotlang,
        $user,
        $username
    ) {
        switch ($telegramRoute) {
        case 'rotate_link':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changelink on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changelink',
                    ]);
                }
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            if ($DataUserOut['status'] == "disabled" || $DataUserOut['status'] == "on_hold") {
                sendmessage($from_id, $datatextbot['dyn_errors_link_change_service_disabled'] ?? "❌ سرویس غیرفعال است و امکان تعویض لینک برای سرویس وجود ندارد.", null, 'html');
                return;
            }
            $keyboardextend = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['changelink']['confirm'], 'callback_data' => "confirmchange_" . $nameloc['id_invoice']],
                    ],
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['changelink']['warnchange'], $keyboardextend);
            break;
        case 'rotate_confirm':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmchange on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmchange',
                    ]);
                }
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $DataUserOut = $ManagePanel->Revoke_sub($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_link_change_failed'), null, 'HTML');
                return;
            }
            $textconfig = "✅ کانفیگ شما با موفقیت بروزرسانی گردید.";
            if ($marzban_list_get['sublink'] == "onsublink") {
                $output_config_link = $DataUserOut['subscription_url'];
                $textconfig .= "اشتراک شما : <code>$output_config_link</code>";
            }
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            if ($marzban_list_get['config'] == "onconfig") {
                Editmessagetext($from_id, $message_id, $textconfig, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboard_config($DataUserOut['configs'], $nameloc['id_invoice'], true));
            } else {
                Editmessagetext($from_id, $message_id, $textconfig, $bakinfos);
            }
            $timejalali = jdate('Y/m/d H:i:s');
            $text_report = "📣 جزئیات تغییر لینک در ربات شما ثبت شد .
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :{$nameloc['username']}</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️زمان تغییر لینک : $timejalali</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $text_report,
                    'parse_mode' => "HTML",
                ]);
            }
            break;
        }

        return true;
    }
}
