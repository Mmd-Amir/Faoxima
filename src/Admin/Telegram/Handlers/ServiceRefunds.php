<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminAction, $adminExecution, $backadmin, $callback_query_id, $dataget, $from_id, $keyboardadmin, $message_id, $otherreport, $pdo, $setting, $text, $textbotlang, $user.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceRefunds
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
        $text,
        $textbotlang,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:

            $mafu_inv_id = $dataget[1];
            $mafu_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
            if (!is_array($mafu_invoice)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'فاکتور پیدا نشد.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }
            $mafu_status = (string)($mafu_invoice['Status'] ?? '');
            if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'این درخواست قبلاً بررسی شده است.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }

            $mafu_price = (int)($mafu_invoice['price_product'] ?? 0);
            $mafu_user_id = (string)($mafu_invoice['id_user'] ?? '');
            $mafu_username_svc = (string)($mafu_invoice['username'] ?? '');
            $mafu_panel_name = (string)($mafu_invoice['Service_location'] ?? '');

            if ($mafu_price <= 0 || $mafu_user_id === '' || $mafu_username_svc === '') {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'اطلاعات فاکتور ناقص است.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }


            $mafu_refund = \Faoxima\Payments\Settlement\Refunds::invoiceOnce($pdo,
                $mafu_inv_id,
                "UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status NOT IN ('removedbyadmin','removebyuser','refunded')",
                [':inv' => $mafu_inv_id],
                $mafu_user_id,
                $mafu_price,
                'بازگشت وجه خودکار مینی‌اپ (تایید ادمین)'
            );
            if ($mafu_refund !== 'refunded') {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $mafu_refund === 'claimed' ? 'این درخواست قبلاً پردازش شده است.' : '❌ بازگشت وجه انجام نشد. دوباره تلاش کنید.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }


            if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser')) {
                try { @$ManagePanel->RemoveUser($mafu_panel_name, $mafu_username_svc); } catch (\Throwable $_) {}
            }

            if (is_callable([\Faoxima\Services\Fulfillment\ServiceReversal::class, 'refundHardDeleteService'])) {
                \Faoxima\Services\Fulfillment\ServiceReversal::refundHardDeleteService($pdo, $mafu_panel_name, $mafu_username_svc, $mafu_inv_id);
            }
            try {
                $mafu_del_inv = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
                $mafu_del_inv->bindValue(':iid', $mafu_inv_id, PDO::PARAM_STR);
                $mafu_del_inv->execute();
            } catch (Throwable $e) {
                error_log('mafurefauto invoice delete failed: ' . $e->getMessage());
            }

            sendmessage($mafu_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($mafu_price) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $mafu_username_svc . "</code> حذف شد.", null, 'HTML');

            $mafu_done = "✅ بازگشت وجه خودکار انجام شد.\n\n💰 مبلغ: " . number_format($mafu_price) . " تومان\n👤 کاربر: <code>$mafu_user_id</code>\n📛 سرویس: <code>$mafu_username_svc</code>\n🆔 فاکتور: <code>$mafu_inv_id</code>";
            if (!empty($message_id) && function_exists('Editmessagetext')) {
                Editmessagetext($from_id, $message_id, $mafu_done, null, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $mafu_done, $keyboardadmin ?? null, 'HTML');
            }
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'بازگشت وجه انجام شد',
                'show_alert' => false,
                'cache_time' => 0,
            ]);

            if (!empty($setting['Channel_Report'])) {
                $mafu_rep = [
                    'chat_id'    => $setting['Channel_Report'],
                    'text'       => "⭕️ بازگشت وجه خودکار (مینی‌اپ)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($mafu_price) . " تومان\n👤 کاربر: <code>$mafu_user_id</code>\n📛 سرویس: <code>$mafu_username_svc</code>\n🆔 فاکتور: <code>$mafu_inv_id</code>",
                    'parse_mode' => 'HTML',
                ];
                if (!empty($otherreport)) $mafu_rep['message_thread_id'] = $otherreport;
                telegram('sendmessage', $mafu_rep);
            }
            break;
        case 2:

            $mafu_inv_id = $dataget[1];
            $mafu_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
            if (!is_array($mafu_invoice)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'فاکتور پیدا نشد.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }
            $mafu_status = (string)($mafu_invoice['Status'] ?? '');
            if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'این درخواست قبلاً بررسی شده است.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }

            \Faoxima\Core\Database\Db::update("user", "Processing_value", $mafu_inv_id, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step("mafurefamount", $from_id);

            $mafu_default = number_format((int)($mafu_invoice['price_product'] ?? 0));
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                $from_id,
                "📌 مبلغ بازگشتی برای این فاکتور را به‌صورت عدد (تومان) ارسال کنید.\n\n🆔 فاکتور: <code>$mafu_inv_id</code>\n💵 مبلغ خرید اولیه: $mafu_default تومان\n📛 سرویس: <code>" . htmlspecialchars((string)($mafu_invoice['username'] ?? '')) . "</code>",
                $backadmin ?? null,
                'HTML'
            );
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'مبلغ موردنظر را ارسال کنید',
                'show_alert' => false,
                'cache_time' => 0,
            ]);
            break;
        case 3:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'] ?? '❌ مقدار معتبر نیست. فقط عدد ارسال کنید.', $backadmin ?? null, 'HTML');
                return;
            }

            $mafu_inv_id = (string)$user['Processing_value'];
            $mafu_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $mafu_inv_id, "select");
            if (!is_array($mafu_invoice)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ فاکتور پیدا نشد.", $keyboardadmin ?? null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $mafu_status = (string)($mafu_invoice['Status'] ?? '');
            if (in_array($mafu_status, ['removedbyadmin', 'removebyuser', 'refunded'], true)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده است.", $keyboardadmin ?? null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }

            $mafu_amount = intval($text);
            $mafu_user_id = (string)($mafu_invoice['id_user'] ?? '');
            $mafu_username_svc = (string)($mafu_invoice['username'] ?? '');
            $mafu_panel_name = (string)($mafu_invoice['Service_location'] ?? '');


            $mafu_refund = \Faoxima\Payments\Settlement\Refunds::invoiceOnce($pdo,
                $mafu_inv_id,
                "UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status NOT IN ('removedbyadmin','removebyuser','refunded')",
                [':inv' => $mafu_inv_id],
                $mafu_user_id,
                $mafu_amount,
                'بازگشت وجه دستی مینی‌اپ (تایید ادمین)'
            );
            if ($mafu_refund === 'claimed') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این درخواست قبلاً پردازش شده است.", $keyboardadmin ?? null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            if ($mafu_refund !== 'refunded') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ بازگشت وجه انجام نشد. دوباره مبلغ را ارسال کنید.", $backadmin ?? null, 'HTML');
                return;
            }


            if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser') && $mafu_username_svc !== '') {
                try { @$ManagePanel->RemoveUser($mafu_panel_name, $mafu_username_svc); } catch (\Throwable $_) {}
            }


            if (is_callable([\Faoxima\Services\Fulfillment\ServiceReversal::class, 'refundHardDeleteService'])) {
                \Faoxima\Services\Fulfillment\ServiceReversal::refundHardDeleteService($pdo, $mafu_panel_name, $mafu_username_svc, $mafu_inv_id);
            }
            try {
                $mafu_del_inv2 = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
                $mafu_del_inv2->bindValue(':iid', $mafu_inv_id, PDO::PARAM_STR);
                $mafu_del_inv2->execute();
            } catch (Throwable $e) {
                error_log('mafurefamount invoice delete failed: ' . $e->getMessage());
            }

            sendmessage($mafu_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($mafu_amount) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $mafu_username_svc . "</code> حذف شد.", null, 'HTML');

            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ " . number_format($mafu_amount) . " تومان به کیف‌پول کاربر اضافه شد و سرویس حذف گردید.", $keyboardadmin ?? null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);

            if (!empty($setting['Channel_Report'])) {
                $mafu_rep = [
                    'chat_id'    => $setting['Channel_Report'],
                    'text'       => "⭕️ بازگشت وجه دستی (مینی‌اپ)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($mafu_amount) . " تومان\n👤 کاربر: <code>$mafu_user_id</code>\n📛 سرویس: <code>$mafu_username_svc</code>\n🆔 فاکتور: <code>$mafu_inv_id</code>",
                    'parse_mode' => 'HTML',
                ];
                if (!empty($otherreport)) $mafu_rep['message_thread_id'] = $otherreport;
                telegram('sendmessage', $mafu_rep);
            }
            break;
        case 4:
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
            $nmref_amount = (int)($nmref_invoice['price_product'] ?? 0);
            $nmref_user_id = (string)($nmref_invoice['id_user'] ?? '');
            $nmref_username_svc = (string)($nmref_invoice['username'] ?? '');
            $nmref_panel_name = (string)($nmref_invoice['Service_location'] ?? '');
            $nmref_refund = \Faoxima\Payments\Settlement\Refunds::invoiceOnce($pdo,
                $nmref_inv_id,
                "UPDATE invoice SET Status = 'removedbyadmin' WHERE id_invoice = :inv AND Status = 'nm_refund_pending'",
                [':inv' => $nmref_inv_id],
                $nmref_user_id,
                $nmref_amount,
                'بازگشت وجه انبار شبکه ملی (پیش‌فرض)'
            );
            if ($nmref_refund !== 'refunded') {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $nmref_refund === 'claimed' ? 'این درخواست قبلاً پردازش شده است.' : '❌ بازگشت وجه انجام نشد. دوباره تلاش کنید.', 'show_alert' => true, 'cache_time' => 0]);
                return;
            }
            if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'RemoveUser') && $nmref_username_svc !== '') {
                try { @$ManagePanel->RemoveUser($nmref_panel_name, $nmref_username_svc); } catch (\Throwable $_) {}
            }
            if (is_callable([\Faoxima\Services\Fulfillment\ServiceReversal::class, 'refundHardDeleteService'])) {
                \Faoxima\Services\Fulfillment\ServiceReversal::refundHardDeleteService($pdo, $nmref_panel_name, $nmref_username_svc, $nmref_inv_id);
            }
            try {
                $nmref_del_inv = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :iid");
                $nmref_del_inv->bindValue(':iid', $nmref_inv_id, PDO::PARAM_STR);
                $nmref_del_inv->execute();
            } catch (Throwable $e) {
                error_log('nmrefokdef invoice delete failed: ' . $e->getMessage());
            }
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockLog')) {
                try { \Faoxima\Services\Fulfillment\Inventory::nmStockLog($pdo, null, $nmref_user_id, $nmref_inv_id, 'refund_approved_default', ['amount' => $nmref_amount]); } catch (Throwable $e) {}
            }
            sendmessage($nmref_user_id, "✅ درخواست بازگشت وجه شما توسط ادمین تایید شد.\n\n💰 مبلغ " . number_format($nmref_amount) . " تومان به کیف‌پول شما اضافه گردید.\n📛 سرویس <code>" . $nmref_username_svc . "</code> حذف شد.", null, 'HTML');
            $nmref_done = "✅ بازگشت وجه انبار شبکه ملی انجام شد.\n\n💰 مبلغ: " . number_format($nmref_amount) . " تومان\n👤 کاربر: <code>$nmref_user_id</code>\n📛 سرویس: <code>$nmref_username_svc</code>\n🆔 فاکتور: <code>$nmref_inv_id</code>";
            if (!empty($message_id) && function_exists('Editmessagetext')) {
                Editmessagetext($from_id, $message_id, $nmref_done, null, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $nmref_done, $keyboardadmin ?? null, 'HTML');
            }
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'بازگشت وجه انجام شد', 'show_alert' => false, 'cache_time' => 0]);
            if (!empty($setting['Channel_Report'])) {
                $nmref_rep = ['chat_id' => $setting['Channel_Report'], 'text' => "⭕️ بازگشت وجه انبار شبکه ملی (پیش‌فرض)\n\n🪪 ادمین: <code>$from_id</code>\n💰 مبلغ: " . number_format($nmref_amount) . " تومان\n👤 کاربر: <code>$nmref_user_id</code>\n📛 سرویس: <code>$nmref_username_svc</code>\n🆔 فاکتور: <code>$nmref_inv_id</code>", 'parse_mode' => 'HTML'];
                if (!empty($otherreport)) $nmref_rep['message_thread_id'] = $otherreport;
                telegram('sendmessage', $nmref_rep);
            }
            break;
        }

        return true;
    }
}
