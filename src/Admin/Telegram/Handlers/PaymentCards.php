<?php

namespace Faoxima\Admin\Telegram\Handlers;

use Exception;
use PDO;

/**
 * Handler stage. Inherited by value: $CartManage, $_wlpg_m, $adminAction, $adminExecution, $backadmin, $callback_query_id, $from_id, $m, $pdo, $text, $user, $wl_text.
 * Inherited or exported by reference: $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PaymentCards
{
    public static function handle(
        $CartManage,
        $_wlpg_m,
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $from_id,
        $m,
        $pdo,
        &$stmt,
        $text,
        $user,
        $wl_text
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 شماره کارت را وارد کنید (بدون فاصله و علامت):", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('card_add_cardnumber', $from_id);
            break;
        case 2:
            $card_num = preg_replace('/\D/', '', $text);
            if (strlen($card_num) < 16 || strlen($card_num) > 20) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ شماره کارت باید بین 16 تا 20 رقم باشد", $backadmin, 'HTML');
                return;
            }

            $stmt = $pdo->prepare("SELECT id FROM card_number WHERE cardnumber = ?");
            $stmt->execute([$card_num]);
            if ($stmt->rowCount() > 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این شماره کارت قبلاً ثبت شده است", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }

            \Faoxima\Core\Database\Db::update("user", "Processing_value", $card_num, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام دارنده کارت یا بانک را وارد کنید:", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('card_add_cardname', $from_id);
            break;
        case 3:
            try {
                $card_num = (string)$user['Processing_value'];
                $card_name = trim($text);
                if (empty($card_name) || strlen($card_name) > 1000) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام کارت نامعتبر است", $backadmin, 'HTML');
                    return;
                }

                $stmt = $pdo->prepare("INSERT INTO card_number (cardnumber, namecard, is_active, is_visible, created_at) VALUES (?, ?, 1, 1, ?)");
                $stmt->execute([$card_num, $card_name, time()]);

                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ شماره کارت با موفقیت افزوده شد", $CartManage, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            } catch (Exception $e) {
                error_log('[card_add] ' . $e->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در ثبت کارت. لطفاً دوباره تلاش کنید", $CartManage, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            }
            break;
        case 4:
            $card_id = (int)$m[1];
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $stmt = $pdo->prepare("SELECT id, cardnumber, namecard, is_active, is_visible FROM card_number WHERE id = ?");
            $stmt->execute([$card_id]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ کارت پیدا نشد', 'show_alert' => true]);
                return;
            }

            \Faoxima\Core\Database\Db::update("user", "Processing_value", $card_id, "id", $from_id);

            $active_badge = ($card['is_active'] == 1) ? '✅ فعال' : '❌ غیرفعال';

            $card_menu = json_encode(['inline_keyboard' => [
                [['text' => "📝 {$card['namecard']}", 'callback_data' => 'card_show_info']],
                [['text' => $active_badge, 'callback_data' => 'card_toggle_active']],
                [['text' => "📋 مدیریت وایت لیست", 'callback_data' => 'card_manage_whitelist']],
                [['text' => "✏️ ویرایش کارت", 'callback_data' => 'card_edit_start']],
                [['text' => "❌ حذف کارت", 'callback_data' => 'card_delete_confirm']],
                [['text' => "🔙 بازگشت", 'callback_data' => 'cart_manage_inline']]
            ]], JSON_UNESCAPED_UNICODE);

            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "💳 مدیریت کارت:\n\n🏦 نام: {$card['namecard']}\n🔢 شماره: <code>{$card['cardnumber']}</code>\n$active_badge", $card_menu, 'HTML');
            break;
        case 5:
            $card_id = (int)$user['Processing_value'];
            $stmt = $pdo->prepare("SELECT id, cardnumber, namecard, is_active FROM card_number WHERE id = ?");
            $stmt->execute([$card_id]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($card) {
                $new_status = ($card['is_active'] == 1) ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE card_number SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $card_id]);

                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => ($new_status == 1 ? '✅ فعال شد' : '❌ غیرفعال شد'), 'show_alert' => false]);

                $active_badge = ($new_status == 1) ? '✅ فعال' : '❌ غیرفعال';
                $updated_menu = json_encode(['inline_keyboard' => [
                    [['text' => "📝 {$card['namecard']}", 'callback_data' => 'card_show_info']],
                    [['text' => $active_badge, 'callback_data' => 'card_toggle_active']],
                    [['text' => "📋 مدیریت وایت لیست", 'callback_data' => 'card_manage_whitelist']],
                    [['text' => "✏️ ویرایش کارت", 'callback_data' => 'card_edit_start']],
                    [['text' => "❌ حذف کارت", 'callback_data' => 'card_delete_confirm']],
                    [['text' => "🔙 بازگشت", 'callback_data' => 'cart_manage_inline']]
                ]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "💳 مدیریت کارت:\n\n🏦 نام: {$card['namecard']}\n🔢 شماره: <code>{$card['cardnumber']}</code>\n$active_badge", $updated_menu, 'HTML');
            }
            break;
        case 6:
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $_wl_page = isset($_wlpg_m[1]) ? max(1, (int)$_wlpg_m[1]) : 1;
            $_wl_per = 5;
            $_wl_off = ($_wl_page - 1) * $_wl_per;
            $card_id = (int)$user['Processing_value'];
            $_wl_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM card_whitelist WHERE card_id = ?");
            $_wl_count_stmt->execute([$card_id]);
            $_wl_total = (int)$_wl_count_stmt->fetchColumn();
            $_wl_pages = max(1, (int)ceil($_wl_total / $_wl_per));
            $stmt = $pdo->prepare("SELECT user_id FROM card_whitelist WHERE card_id = ? ORDER BY created_at DESC LIMIT {$_wl_per} OFFSET {$_wl_off}");
            $stmt->execute([$card_id]);
            $whitelisted = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($_wl_total === 0) {
                $wl_text = "📭 هیچ کاربری وایت لیست نشده";
            } else {
                $wl_text = "📋 کاربران وایت لیست‌شده:\n" . implode("\n", array_map(function($u) { return "• $u"; }, $whitelisted));
                if ($_wl_pages > 1) {
                    $wl_text .= "\n\n📄 صفحه {$_wl_page} از {$_wl_pages}";
                }
            }

            $_wl_rows = [];
            $_wl_rows[] = [['text' => "➕ افزودن کاربر", 'callback_data' => 'card_wl_add']];
            $_wl_rows[] = [
                ['text' => "🗑 پاک‌کردن همه", 'callback_data' => 'card_wl_clear'],
                ['text' => "✂️ حذف تکی", 'callback_data' => 'card_wl_remove']
            ];
            $_wl_pg_row = [];
            if ($_wl_page > 1) {
                $_wl_pg_row[] = ['text' => "◀️ قبل", 'callback_data' => 'card_wl_page_' . ($_wl_page - 1)];
            }
            if ($_wl_page < $_wl_pages) {
                $_wl_pg_row[] = ['text' => "▶️ بعد", 'callback_data' => 'card_wl_page_' . ($_wl_page + 1)];
            }
            if (!empty($_wl_pg_row)) {
                $_wl_rows[] = $_wl_pg_row;
            }
            $_wl_rows[] = [['text' => "🔙 بازگشت", 'callback_data' => 'card_select_' . $card_id]];
            $wl_kb = json_encode(['inline_keyboard' => $_wl_rows], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $wl_text, $wl_kb, 'HTML');
            break;
        case 7:
            $cancel_wl_kb = json_encode(['inline_keyboard' => [
                [['text' => "❌ لغو", 'callback_data' => 'card_manage_whitelist']]
            ]], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 آیدی عددی کاربر را وارد کنید (یا چند آیدی را با ویرگول جدا کنید):", $cancel_wl_kb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('card_wl_add_users', $from_id);
            break;
        case 8:
            $card_id = (int)$user['Processing_value'];
            $user_ids = array_filter(array_map('trim', explode(',', $text)));
            $added = 0;
            $failed = 0;

            foreach ($user_ids as $uid) {
                if (!ctype_digit($uid)) {
                    $failed++;
                    continue;
                }
                try {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO card_whitelist (card_id, user_id, created_at) VALUES (?, ?, ?)");
                    $stmt->execute([$card_id, $uid, time()]);
                    $added += $stmt->rowCount();
                } catch (Exception $e) {
                    $failed++;
                }
            }

            $back_wl_kb = json_encode(['inline_keyboard' => [
                [['text' => "🔙 بازگشت به مدیریت وایت لیست", 'callback_data' => 'card_manage_whitelist']],
                [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
            ]], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ $added کاربر افزوده شد\n❌ $failed خطا یا تکراری", $back_wl_kb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 9:
            $card_id = (int)$user['Processing_value'];
            $stmt = $pdo->prepare("DELETE FROM card_whitelist WHERE card_id = ?");
            $stmt->execute([$card_id]);
            $back_wl_kb = json_encode(['inline_keyboard' => [
                [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
            ]], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ تمام کاربران وایت لیست‌شده حذف شدند", $back_wl_kb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 10:
            $cancel_rm_kb = json_encode(['inline_keyboard' => [
                [['text' => "❌ لغو", 'callback_data' => 'card_manage_whitelist']]
            ]], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 آیدی عددی کاربر را برای حذف از وایت لیست وارد کنید:", $cancel_rm_kb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('card_wl_remove_uid', $from_id);
            break;
        case 11:
            $card_id = (int)$user['Processing_value'];
            $uid = trim($text ?? '');
            if (!ctype_digit($uid) || intval($uid) <= 0) {
                $cancel_rm_kb2 = json_encode(['inline_keyboard' => [
                    [['text' => "❌ لغو", 'callback_data' => 'card_manage_whitelist']]
                ]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آیدی نامعتبر است. فقط عدد مجاز است", $cancel_rm_kb2, 'HTML');
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM card_whitelist WHERE card_id = ? AND user_id = ?");
            $stmt->execute([$card_id, $uid]);
            $deleted = $stmt->rowCount();
            $back_rm_kb = json_encode(['inline_keyboard' => [
                [['text' => "🔙 بازگشت به وایت لیست", 'callback_data' => 'card_manage_whitelist']],
                [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
            ]], JSON_UNESCAPED_UNICODE);
            $msg = $deleted > 0 ? "✅ کاربر {$uid} از وایت لیست حذف شد" : "⚠️ کاربر {$uid} در وایت لیست این کارت یافت نشد";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $msg, $back_rm_kb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 12:
            $card_id = (int)$user['Processing_value'];
            $confirm_kb = json_encode(['inline_keyboard' => [
                [['text' => "✅ بله، حذف کن", 'callback_data' => 'card_delete_execute']],
                [['text' => "❌ خیر", 'callback_data' => 'card_select_' . $card_id]]
            ]], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ آیا مطمئن هستید؟ این عمل قابل بازگشت نیست", $confirm_kb, 'HTML');
            break;
        case 13:
            $card_id = (int)$user['Processing_value'];
            try {
                $stmt = $pdo->prepare("DELETE FROM card_whitelist WHERE card_id = ?");
                $stmt->execute([$card_id]);
                $stmt = $pdo->prepare("DELETE FROM card_number WHERE id = ?");
                $stmt->execute([$card_id]);

                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ کارت با موفقیت حذف شد", $CartManage, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            } catch (Exception $e) {
                error_log('[card_delete] ' . $e->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در حذف کارت", $CartManage, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            }
            break;
        case 14:
            $cancel_kb = json_encode(['inline_keyboard' => [
                [['text' => "❌ لغو", 'callback_data' => 'card_select_' . (int)$user['Processing_value']]]
            ]], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 شماره کارت جدید را وارد کنید (16-20 رقم):", $cancel_kb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('card_edit_number', $from_id);
            break;
        }

        return true;
    }
}
