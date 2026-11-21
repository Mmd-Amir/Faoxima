<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $Response, $dataget, $from_id, $keyboard, $message_id, $otherreport, $setting, $telegramRoute, $textbotlang, $update, $user.
 * Inherited or exported by reference: $id_invoice, $invoice, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceNotes
{
    public static function handle(
        $Response,
        $dataget,
        $from_id,
        &$id_invoice,
        &$invoice,
        $keyboard,
        $message_id,
        $otherreport,
        $setting,
        $telegramRoute,
        &$text,
        $textbotlang,
        $update,
        $user
    ) {
        switch ($telegramRoute) {
        case 'note_start':
            $id_invoice = $dataget[1];
            $rxNoteCheck = \Faoxima\Core\Database\Db::select("invoice", "id_user", "id_invoice", $id_invoice, "select");
            if (!is_array($rxNoteCheck) || (string)($rxNoteCheck['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changenote on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changenote',
                    ]);
                }
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $id_invoice, "id", $from_id);
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['note']['SendNote'], $backinfoss);
            \Faoxima\Telegram\Navigation\Steps::step("getnotedit", $from_id);
            break;
        case 'note_save':
            if (!isset($update['message']) && empty($text)) { return; }
            $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
            if (strlen($text) > 150) {
                sendmessage($from_id, $textbotlang['users']['note']['ErrorLongNote'], $keyboard, "html");
                return;
            }
            $text = \Faoxima\Core\Support\Formatting::sanitizeUserName($text);
            $id_invoice = $user['Processing_value'];
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
                    ]
                ]
            ]);
            \Faoxima\Core\Database\Db::update("invoice", "note", $text, "id_invoice", $id_invoice);
            sendmessage($from_id, $textbotlang['users']['note']['changednote'], $backinfoss, "html");
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $timejalali = jdate('Y/m/d H:i:s');
            $textreport = "📌  یک کاربر یادداشت سرویس خود را تغییر داد.

<blockquote>▫️ نام کاربری سرویس : {$invoice['username']}</blockquote>
<blockquote>▫️ یاداشت قبلی :‌ {$invoice['note']}</blockquote>
<blockquote>▫️ یاداشت جدید :‌  $text</blockquote>

<blockquote>زمان تغییر یادداشت : $timejalali </blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $textreport,
                    'reply_markup' => $Response,
                    'parse_mode' => "HTML"
                ]);
            }
            break;
        }

        return true;
    }
}
