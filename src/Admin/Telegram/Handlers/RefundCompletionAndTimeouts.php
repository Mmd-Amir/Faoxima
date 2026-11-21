<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminAction, $adminExecution, $backadmin, $callback_query_id, $dataget, $from_id, $keyboardadmin, $message_id, $otherreport, $pdo, $setting, $setting_panel, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RefundCompletionAndTimeouts
{
    public static function handle(
        $ManagePanel,
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $dataget,
        $from_id,
        $keyboardadmin,
        $message_id,
        $otherreport,
        $pdo,
        $setting,
        $setting_panel,
        $text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $nmref_inv_id = $dataget[1];
            $nmref_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $nmref_inv_id, "select");
            if (!is_array($nmref_invoice)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور پیدا نشد.', 'show_alert' => true, 'cache_time' => 0]);
                return;
            }
            $nmref_status = (string)($nmref_invoice['Status'] ?? '');
            if (in_array($nmref_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این درخواست قبلاً بررسی شده است.', 'show_alert' => true, 'cache_time' => 0]);
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $nmref_inv_id, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step("nmrefamount", $from_id);
            $nmref_default = number_format((int)($nmref_invoice['price_product'] ?? 0));
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مبلغ بازگشتی برای این فاکتور را به‌صورت عدد (تومان) ارسال کنید.\n\n🆔 فاکتور: <code>$nmref_inv_id</code>\n💵 مبلغ خرید اولیه: $nmref_default تومان\n📛 سرویس: <code>" . htmlspecialchars((string)($nmref_invoice['username'] ?? '')) . "</code>", $backadmin ?? null, 'HTML');
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'مبلغ موردنظر را ارسال کنید', 'show_alert' => false, 'cache_time' => 0]);
            break;
        case 2:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'] ?? '❌ مقدار معتبر نیست. فقط عدد ارسال کنید.', $backadmin ?? null, 'HTML');
                return;
            }
            $nmref_inv_id = (string)$user['Processing_value'];
            $nmref_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $nmref_inv_id, "select");
            if (!is_array($nmref_invoice)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ فاکتور پیدا نشد.", $keyboardadmin ?? null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $nmref_status = (string)($nmref_invoice['Status'] ?? '');
            if (in_array($nmref_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده است.", $keyboardadmin ?? null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $nmref_amount = intval($text);
            $nmref_user_id = (string)($nmref_invoice['id_user'] ?? '');
            $nmref_username_svc = (string)($nmref_invoice['username'] ?? '');
            $nmref_panel_name = (string)($nmref_invoice['Service_location'] ?? '');
            $nmref_refund = \Faoxima\Payments\Settlement\Refunds::invoiceOnce($pdo,
                $nmref_inv_id,
                "UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'",
                [':inv' => $nmref_inv_id],
                $nmref_user_id,
                $nmref_amount,
                'بازگشت وجه انبار شبکه ملی (مبلغ دستی)'
            );
            if ($nmref_refund === 'claimed') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این درخواست قبلاً پردازش شده است.", $keyboardadmin ?? null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            if ($nmref_refund !== 'refunded') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ بازگشت وجه انجام نشد. دوباره مبلغ را ارسال کنید.", $backadmin ?? null, 'HTML');
                return;
            }
            if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser') && $nmref_username_svc !== '') {
                try { @$ManagePanel->RemoveUser($nmref_panel_name, $nmref_username_svc); } catch (\Throwable $_) {}
            }
            if (is_callable([\Faoxima\Services\Fulfillment\ServiceReversal::class, 'refundHardDeleteService'])) {
                \Faoxima\Services\Fulfillment\ServiceReversal::refundHardDeleteService($pdo, $nmref_panel_name, $nmref_username_svc, $nmref_inv_id);
            }
            try {
                $nmref_del_inv2 = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
                $nmref_del_inv2->bindValue(':iid', $nmref_inv_id, PDO::PARAM_STR);
                $nmref_del_inv2->execute();
            } catch (Throwable $e) {
                error_log('nmrefamount invoice delete failed: ' . $e->getMessage());
            }
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockLog')) {
                try { \Faoxima\Services\Fulfillment\Inventory::nmStockLog($pdo, null, $nmref_user_id, $nmref_inv_id, 'refund_approved_custom', ['amount' => $nmref_amount]); } catch (Throwable $e) {}
            }
            sendmessage($nmref_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($nmref_amount) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $nmref_username_svc . "</code> حذف شد.", null, 'HTML');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ " . number_format($nmref_amount) . " تومان به کیف‌پول کاربر اضافه شد و سرویس حذف گردید.", $keyboardadmin ?? null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            if (!empty($setting['Channel_Report'])) {
                $nmref_rep = ['chat_id' => $setting['Channel_Report'], 'text' => "⭕️ بازگشت وجه انبار شبکه ملی (دلخواه)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($nmref_amount) . " تومان\n👤 کاربر: <code>$nmref_user_id</code>\n📛 سرویس: <code>$nmref_username_svc</code>\n🆔 فاکتور: <code>$nmref_inv_id</code>", 'parse_mode' => 'HTML'];
                if (!empty($otherreport)) $nmref_rep['message_thread_id'] = $otherreport;
                telegram('sendmessage', $nmref_rep);
            }
            break;
        case 3:
            $nmref_inv_id = $dataget[1];
            $nmref_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $nmref_inv_id, "select");
            if (!is_array($nmref_invoice)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور پیدا نشد.', 'show_alert' => true, 'cache_time' => 0]);
                return;
            }
            $nmref_status = (string)($nmref_invoice['Status'] ?? '');
            if ($nmref_status !== 'nm_refund_pending') {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این درخواست قبلاً بررسی شده است.', 'show_alert' => true, 'cache_time' => 0]);
                return;
            }
            $nmref_reject = $pdo->prepare("UPDATE invoice SET Status = 'active' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'");
            $nmref_reject->bindValue(':inv', $nmref_inv_id, PDO::PARAM_STR);
            $nmref_reject->execute();
            if ($nmref_reject->rowCount() === 0) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این درخواست قبلاً پردازش شده است.', 'show_alert' => true, 'cache_time' => 0]);
                return;
            }
            $nmref_user_id = (string)($nmref_invoice['id_user'] ?? '');
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockLog')) {
                try { \Faoxima\Services\Fulfillment\Inventory::nmStockLog($pdo, null, $nmref_user_id, $nmref_inv_id, 'refund_rejected', null); } catch (Throwable $e) {}
            }
            sendmessage($nmref_user_id, "❌ درخواست بازگشت وجه شما توسط ادمین رد شد. سرویس شما فعال باقی می‌ماند.", null, 'HTML');
            $nmref_done = "❌ درخواست بازگشت وجه رد شد.\n\n👤 کاربر: <code>$nmref_user_id</code>\n🆔 فاکتور: <code>$nmref_inv_id</code>";
            if (!empty($message_id) && function_exists('Editmessagetext')) {
                Editmessagetext($from_id, $message_id, $nmref_done, null, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $nmref_done, $keyboardadmin ?? null, 'HTML');
            }
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'درخواست رد شد', 'show_alert' => false, 'cache_time' => 0]);
            break;
        case 4:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['setvolumeremove'] . $setting['cronvolumere'] . "روز", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcronvolumere", $from_id);
            break;
        case 5:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("setting", "cronvolumere", $text);
            break;
        case 6:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "در این بخش باید تغیین کنید که اگر کاربر بعد از چند روز به کانفیگ خود وصل نشد و در وضعیت on_hold بود به کاربر پیام دهد" . $setting['on_hold_day'] . "روز", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("on_hold_day", $from_id);
            break;
        case 7:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("setting", "on_hold_day", $text);
            break;
        case 8:
            $curTimeout = method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_panel_timeout') ? \Faoxima\Services\Panels\PanelHttp::faoxima_panel_timeout() : (int)($setting['panel_curl_timeout'] ?? 20);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⏱ <b>تایم‌اوت پنل</b>\n\nحداکثر انتظار برای هر درخواست پنل و دریافت محتوای ساب\n\nمقدار فعلی: <b>{$curTimeout} ثانیه</b>\n\nℹ️ این مقدار برای هر تلاش جداگانه اعمال می‌شود. تلاش مجدد و چند درخواست پشت‌سرهم (ورود به پنل، خواندن کاربر، دریافت ساب) ممکن است کل عملیات را طولانی‌تر از این مقدار کند.\n\nمقدار جدید را (عدد بین 5 تا 120) ارسال کنید:", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("get_panel_timeout", $from_id);
            break;
        case 9:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text) || (int)$text < 5 || (int)$text > 120) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مقدار نامعتبر است. عددی بین 5 تا 120 ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("setting", "panel_curl_timeout", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداکثر انتظار برای هر درخواست پنل و دریافت محتوای ساب به <b>{$text} ثانیه</b> تغییر یافت.", $setting_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        }

        return true;
    }
}
