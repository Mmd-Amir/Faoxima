<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $cmAuto, $cmBack, $cmConf, $cmMan, $cmRej, $from_id, $keyboardadmin, $message_id, $paymentreports, $setting, $text, $text_inline, $update, $user, $username.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReceiptRejectionAndInventory
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $cmAuto,
        $cmBack,
        $cmConf,
        $cmMan,
        $cmRej,
        $from_id,
        $keyboardadmin,
        $message_id,
        $paymentreports,
        $setting,
        $text,
        $text_inline,
        $update,
        $user,
        $username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            if (!isset($update['message']) && empty($text)) { return; }
            $Payment_report = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $user['Processing_value_one'], "select");
            \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", $text, "id_order", $user['Processing_value_one']);
            $text_reject = "❌ کاربر گرامی پرداخت شما به دلیل زیر رد گردید.
✍️ $text
🛒 کد پیگیری پرداخت: {$user['Processing_value_one']}
                ";
            $_reject_origin = explode(':', (string) ($user['Processing_value_four'] ?? ''), 3);
            $_reject_chat_id = $_reject_origin[0] ?? '';
            $_reject_message_id = $_reject_origin[1] ?? '';
            $_reject_thread_id = (int) ($_reject_origin[2] ?? 0);
            if ($_reject_chat_id === '' || $_reject_message_id === '') {
                $_reject_chat_id = (string) ($Payment_report['report_chat_id'] ?? '');
                $_reject_message_id = (string) ($Payment_report['report_message_id'] ?? '');
                $_reject_thread_id = (int) ($Payment_report['report_thread_id'] ?? 0);
            }
            $_reject_confirmed_text = "❌ رسید پرداخت رد شد.

اطلاعات :
<blockquote>💸 روش پرداخت : {$Payment_report['Payment_Method']}</blockquote>
<blockquote>👤آیدی عددی  ادمین رد کننده : $from_id</blockquote>
<blockquote>نام کاربری ادمین رد کننده : @$username</blockquote>
<blockquote>💰 مبلغ پرداخت : " . \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price']) . "</blockquote>
<blockquote>دلیل رد کردن : $text</blockquote>
<blockquote>👤 ایدی عددی کاربر: {$Payment_report['id_user']}</blockquote>";
            if ($_reject_chat_id !== '' && $_reject_message_id !== '') {
                Editmessagetext($_reject_chat_id, $_reject_message_id, $_reject_confirmed_text, null, 'HTML', $_reject_thread_id > 0 ? $_reject_thread_id : null);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "", "id", $from_id);
            }
            $_reject_admin_msg_chat = $update['message']['chat']['id'] ?? null;
            $_reject_admin_msg_id = $update['message']['message_id'] ?? null;
            if ($_reject_admin_msg_chat && $_reject_admin_msg_id) {
                $_reject_del_result = deletemessage($_reject_admin_msg_chat, $_reject_admin_msg_id);
                if (function_exists('rx_log_event') && (!is_array($_reject_del_result) || empty($_reject_del_result['ok']))) {
                    rx_log_event('REJECT_DEC_DELETE_FAILED', 'Failed to delete admin reason message', [
                        'chat_id' => $_reject_admin_msg_chat,
                        'message_id' => $_reject_admin_msg_id,
                        'result' => $_reject_del_result,
                    ]);
                }
            } else {
                if (function_exists('rx_log_event')) {
                    rx_log_event('REJECT_DEC_DELETE_SKIPPED', 'No message context to delete', [
                        'update_message_isset' => isset($update['message']),
                    ]);
                }
            }
            $_target_user_id = $Payment_report['id_user'] ?? null;
            if ($_target_user_id) { sendmessage($_target_user_id, $text_reject, null, 'HTML'); }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 2:
            $cmOrderId = $cmConf[1];
            $cmPayment = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $cmOrderId, "select");
            if (!$cmPayment) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'تراکنش یافت نشد',
                    'show_alert' => true,
                    'cache_time' => 5,
                ]);
                return;
            }
            if ($cmPayment['payment_Status'] == 'paid') {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'این فاکتور قبلاً تایید شده است.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }
            $cmAutoIrr = (int) ($cmPayment['price'] ?? 0);
            $cmCoinAmtPick = (string) ($cmPayment['crypto_amount'] ?? '');
            $cmCoinAmtPick = rtrim(rtrim(number_format((float) $cmCoinAmtPick, 9, '.', ''), '0'), '.');
            $cmPickMsg = "🟢 <b>تایید درخواست بررسی دستی</b>\n\n"
                       . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
                       . "👤 کاربر: <code>{$cmPayment['id_user']}</code>\n"
                       . "💎 ارز: " . htmlspecialchars((string) $cmPayment['crypto_currency']) . "\n"
                       . "🪙 مقدار: <code>{$cmCoinAmtPick}</code>\n"
                       . "💵 معادل خودکار: <b>" . number_format($cmAutoIrr) . " تومان</b>\n\n"
                       . "👇 روش تایید را انتخاب کنید:";
            $cmPickKb = json_encode([
                'inline_keyboard' => [
                    [['text' => '⚡ تایید با همان مبلغ خودکار', 'callback_data' => 'cmauto_' . $cmOrderId]],
                    [['text' => '✏️ ویرایش مبلغ و تایید',       'callback_data' => 'cmmanual_' . $cmOrderId]],
                    [['text' => '🔙 بازگشت',                     'callback_data' => 'cmback_' . $cmOrderId]],
                ],
            ], JSON_UNESCAPED_UNICODE);
            if (!empty($message_id)) {
                Editmessagetext($from_id, $message_id, $cmPickMsg, $cmPickKb);
            } else {
                sendmessage($from_id, $cmPickMsg, $cmPickKb, 'HTML');
            }
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            break;
        case 3:
            $cmOrderId = $cmAuto[1];
            $cmPayment = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $cmOrderId, "select");
            if (!$cmPayment) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور یافت نشد', 'show_alert' => true]);
                return;
            }
            if ($cmPayment['payment_Status'] === 'paid') {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این فاکتور قبلاً تایید شده', 'show_alert' => true]);
                return;
            }
            $cmFinalIrr = (int) ($cmPayment['price'] ?? 0);
            \Faoxima\Payments\Crypto\ManualCrypto::cm_apply_payment($cmOrderId, $cmFinalIrr, $cmPayment, $from_id, $username, $setting, $paymentreports, $message_id, $callback_query_id, $text_inline ?? '');
            break;
        case 4:
            $cmOrderId = $cmMan[1];
            $cmPayment = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $cmOrderId, "select");
            if (!$cmPayment) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور یافت نشد', 'show_alert' => true]);
                return;
            }
            if ($cmPayment['payment_Status'] === 'paid') {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این فاکتور قبلاً تایید شده', 'show_alert' => true]);
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $cmOrderId, "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", (string) ($message_id ?? 0), "id", $from_id);
            $cmCoinAmt = (string) ($cmPayment['crypto_amount'] ?? '');
            $cmCoinAmt = rtrim(rtrim(number_format((float) $cmCoinAmt, 9, '.', ''), '0'), '.');
            $cmAutoIrrShow = (int) ($cmPayment['price'] ?? 0);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                $from_id,
                "✏️ <b>وارد کردن مبلغ تومانی دستی</b>\n\n"
                . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
                . "💎 ارز: " . htmlspecialchars((string) $cmPayment['crypto_currency']) . "\n"
                . "🪙 مقدار: <code>{$cmCoinAmt}</code>\n"
                . "💵 مبلغ خودکار: <b>" . number_format($cmAutoIrrShow) . " تومان</b>\n\n"
                . "💰 مبلغ تومانی نهایی را وارد کنید (فقط عدد):",
                $backadmin,
                'HTML'
            );
            \Faoxima\Telegram\Navigation\Steps::step('cm_manual_irr_input', $from_id);
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            break;
        case 5:
            $cmOrderId = (string) ($user['Processing_value_one'] ?? '');
            if ($cmOrderId === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطای داخلی. دوباره تلاش کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $cmIrrRaw = trim(str_replace([',', '،'], ['', ''], (string) $text));
            if (!ctype_digit($cmIrrRaw) || (int) $cmIrrRaw <= 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مبلغ باید عدد صحیح مثبت باشد. مثال: <code>50000</code>", null, 'HTML');
                return;
            }
            $cmFinalIrr = (int) $cmIrrRaw;
            $cmPayment = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $cmOrderId, "select");
            if (!$cmPayment) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ تراکنش یافت نشد.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($cmPayment['payment_Status'] === 'paid') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این فاکتور قبلاً تایید شده است.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("Payment_report", "price", (string) $cmFinalIrr, "id_order", $cmOrderId);
            $cmPayment['price'] = (string) $cmFinalIrr;
            $cmPrevMsgId = (int) ($user['Processing_value_tow'] ?? 0);
            \Faoxima\Payments\Crypto\ManualCrypto::cm_apply_payment($cmOrderId, $cmFinalIrr, $cmPayment, $from_id, $username, $setting, $paymentreports, $cmPrevMsgId, null, '');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ تایید شد با مبلغ " . number_format($cmFinalIrr) . " تومان.", $keyboardadmin, 'HTML');
            break;
        case 6:
            $cmOrderId = $cmBack[1];
            $cmPayment = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $cmOrderId, "select");
            if (!$cmPayment) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'تراکنش یافت نشد', 'show_alert' => true]);
                return;
            }
            $cmKb = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تایید و شارژ', 'callback_data' => 'confirmcryptomanual_' . $cmOrderId],
                        ['text' => '❌ رد درخواست',           'callback_data' => 'rejectcryptomanual_' . $cmOrderId],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
            if (!empty($message_id)) {
                telegram('editMessageReplyMarkup', [
                    'chat_id'      => $from_id,
                    'message_id'   => $message_id,
                    'reply_markup' => $cmKb,
                ]);
            }
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            break;
        case 7:
            $cmOrderId = $cmRej[1];
            $cmPayment = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $cmOrderId, "select");
            if (!$cmPayment) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'تراکنش یافت نشد',
                    'show_alert' => true,
                    'cache_time' => 5,
                ]);
                return;
            }
            if ($cmPayment['payment_Status'] === 'paid') {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'این فاکتور قبلاً تایید شده است.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $cmPayment['id_user'], "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $cmOrderId, "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", (string) ($message_id ?? 0), "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✍️ دلیل رد کردن این درخواست را وارد کنید:", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('reject_crypto_manual_reason', $from_id);
            break;
        case 8:
            $cmOrderId = (string) ($user['Processing_value_one'] ?? '');
            $cmUserId = (string) ($user['Processing_value'] ?? '');
            if ($cmOrderId === '' || $cmUserId === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطای داخلی. مجدداً تلاش کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $cmReason = trim((string) $text);
            if ($cmReason === '' || mb_strlen($cmReason) > 500) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ دلیل معتبر نیست. حداکثر ۵۰۰ کاراکتر.", null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("Payment_report", "payment_Status", "reject", "id_order", $cmOrderId);
            \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", $cmReason, "id_order", $cmOrderId);
            \Faoxima\Core\Database\Db::update("Payment_report", "at_updated", date('Y/m/d H:i:s'), "id_order", $cmOrderId);

            sendmessage(
                $cmUserId,
                "❌ <b>درخواست بررسی دستی پرداخت کریپتوی شما رد شد.</b>\n\n"
                . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
                . "📝 دلیل: " . htmlspecialchars($cmReason) . "\n\n"
                . "در صورت اعتراض با پشتیبانی در ارتباط باشید.",
                null,
                'HTML'
            );
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ درخواست با موفقیت رد شد.", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $paymentreports,
                    'text' => "❌ بررسی دستی پرداخت کریپتو رد شد\n\n"
                        . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
                        . "👤 کاربر: <code>{$cmUserId}</code>\n"
                        . "📝 دلیل: " . htmlspecialchars($cmReason) . "\n"
                        . "👨‍💼 ادمین: <code>{$from_id}</code> (@{$username})",
                    'parse_mode' => 'HTML',
                ]);
            }
            break;
        }

        return true;
    }
}
