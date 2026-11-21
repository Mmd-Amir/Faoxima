<?php

namespace Faoxima\Admin\Telegram\Handlers;

use Exception;
use PDO;

/**
 * Handler stage. Inherited by value: $NowPaymentsManage, $adminAction, $adminExecution, $backadmin, $callback_query_id, $datain, $from_id, $json_list_marzban_panel, $keyboardadmin, $keyboardzarinpal, $pdo, $text, $textbotlang, $user.
 * Inherited or exported by reference: $row, $stmt, $textcart.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CardEditingAndPanelOptions
{
    public static function handle(
        $NowPaymentsManage,
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
        $json_list_marzban_panel,
        $keyboardadmin,
        $keyboardzarinpal,
        $pdo,
        &$row,
        &$stmt,
        $text,
        $textbotlang,
        &$textcart,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $card_id = (int)$user['Processing_value'];
            $new_number = preg_replace('/\D/', '', $text);

            $cancel_kb = json_encode(['inline_keyboard' => [
                [['text' => "❌ لغو", 'callback_data' => 'card_select_' . $card_id]]
            ]], JSON_UNESCAPED_UNICODE);

            if (strlen($new_number) < 16 || strlen($new_number) > 20) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ شماره کارت باید 16 تا 20 رقم باشد", $cancel_kb, 'HTML');
                return;
            }

            $stmt = $pdo->prepare("SELECT id FROM card_number WHERE cardnumber = ? AND id != ?");
            $stmt->execute([$new_number, $card_id]);
            if ($stmt->fetch()) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این شماره کارت قبلاً ثبت شده است", $cancel_kb, 'HTML');
                return;
            }

            \Faoxima\Core\Database\Db::update("user", "Processing_edit_value", $new_number, "id", $from_id);
            $cancel_kb2 = json_encode(['inline_keyboard' => [
                [['text' => "❌ لغو", 'callback_data' => 'card_select_' . $card_id]]
            ]], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام کارت جدید را وارد کنید:", $cancel_kb2, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('card_edit_name', $from_id);
            break;
        case 2:
            $card_id = (int)$user['Processing_value'];
            $new_number = (string)$user['Processing_edit_value'];
            $new_name = trim($text);

            $cancel_kb = json_encode(['inline_keyboard' => [
                [['text' => "❌ لغو", 'callback_data' => 'card_select_' . $card_id]]
            ]], JSON_UNESCAPED_UNICODE);

            if (strlen($new_name) < 1 || strlen($new_name) > 100) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام کارت باید بین 1 تا 100 کاراکتر باشد", $cancel_kb, 'HTML');
                return;
            }

            try {
                $stmt = $pdo->prepare("UPDATE card_number SET cardnumber = ?, namecard = ? WHERE id = ?");
                $stmt->execute([$new_number, $new_name, $card_id]);

                $back_card_kb = json_encode(['inline_keyboard' => [
                    [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
                ]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ کارت با موفقیت به‌روزرسانی شد\n\n🏦 نام: $new_name\n🔢 شماره: <code>$new_number</code>", $back_card_kb, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            } catch (Exception $e) {
                error_log('[card_edit] ' . $e->getMessage());
                $back_card_kb = json_encode(['inline_keyboard' => [
                    [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
                ]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در به‌روزرسانی کارت", $back_card_kb, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            }
            break;
        case 3:
            $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $mode = $row ? ($row['ValuePay'] ?? 'random') : 'random';
            $r_mark = $mode == 'random' ? '✅ ' : '';
            $d_mark = $mode == 'direct' ? '✅ ' : '';
            $mode_kb = json_encode(['inline_keyboard' => [
                [
                    ['text' => "{$r_mark}🎲 رندوم", 'callback_data' => 'card_mode_set_random'],
                    ['text' => "{$d_mark}🔗 مستقیم", 'callback_data' => 'card_mode_set_direct']
                ],
                [['text' => "🔙 بازگشت", 'callback_data' => 'cart_manage_inline']]
            ]], JSON_UNESCAPED_UNICODE);
            $current_label = $mode == 'random' ? '🎲 رندوم' : '🔗 مستقیم (2 کارت)';
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚙️ روش نمایش کارت به کاربر\n\nحالت فعلی: <b>{$current_label}</b>", $mode_kb, 'HTML');
            break;
        case 4:
            $new_mode = $datain == "card_mode_set_random" ? 'random' : 'direct';
            $stmt = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = 'card_display_mode'");
            $stmt->execute([$new_mode]);
            if ($stmt->rowCount() === 0) {
                $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('card_display_mode', ?)")->execute([$new_mode]);
            }
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => ($new_mode == 'random' ? '🎲 رندوم' : '🔗 مستقیم') . ' فعال شد', 'show_alert' => false]);
            $r_mark = $new_mode == 'random' ? '✅ ' : '';
            $d_mark = $new_mode == 'direct' ? '✅ ' : '';
            $mode_kb = json_encode(['inline_keyboard' => [
                [
                    ['text' => "{$r_mark}🎲 رندوم", 'callback_data' => 'card_mode_set_random'],
                    ['text' => "{$d_mark}🔗 مستقیم", 'callback_data' => 'card_mode_set_direct']
                ],
                [['text' => "🔙 بازگشت", 'callback_data' => 'cart_manage_inline']]
            ]], JSON_UNESCAPED_UNICODE);
            $current_label = $new_mode == 'random' ? '🎲 رندوم' : '🔗 مستقیم (2 کارت)';
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚙️ روش نمایش کارت به کاربر\n\nحالت فعلی: <b>{$current_label}</b>", $mode_kb, 'HTML');
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $NowPaymentsManage, 'HTML');
            break;
        case 6:
            $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "api_plisio");
            $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
            if ($PaySetting === '' || $PaySetting === '0') {
                $rowLegacy = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apinowpayment");
                $PaySetting = is_array($rowLegacy) ? (string)($rowLegacy['ValuePay'] ?? '') : '';
            }
            $textcart = "⚙️ api سایت plisio.net.io را ارسال نمایید

        api plisio :$PaySetting";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('api_plisio', $from_id);
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $NowPaymentsManage, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "api_plisio");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 8:
            $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "api_nowpayment");
            $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
            if ($PaySetting === '' || $PaySetting === '0') {
                $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller");
                $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
            }
            $safeKey = htmlspecialchars($PaySetting, ENT_QUOTES, 'UTF-8');
            $displayKey = ($PaySetting !== '' && $PaySetting !== '0') ? "<code>$safeKey</code>" : '— تنظیم نشده';
            $texttronseller = "💳 API NowPayments خود را از داشبورد nowpayments.io دریافت و در این قسمت وارد کنید.\n\n"
                            . "🔑 کلید فعلی:\n$displayKey\n\n"
                            . "ℹ️ طول کلید فعلی: " . strlen($PaySetting) . " کاراکتر";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('marchent_tronseller', $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');

            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "marchent_tronseller");
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "api_nowpayment");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 10:
            $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "nowpayment_ipn_secret");
            $currentSecret = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
            $masked = $currentSecret !== '' ? substr($currentSecret, 0, 4) . str_repeat('*', max(0, strlen($currentSecret) - 8)) . substr($currentSecret, -4) : '— تنظیم نشده';
            $textIpn = "🔐 IPN Secret درگاه NowPayments را از داشبورد NowPayments → Store Settings → IPN Secret دریافت و در این قسمت وارد کنید\n\n"
                     . "🔑 مقدار فعلی : <code>$masked</code>\n\n"
                     . "⚠️ این مقدار برای اعتبارسنجی امضای IPN استفاده می‌شود و باید با مقدار داخل داشبورد NowPayments دقیقاً یکی باشد.";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textIpn, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('nowpayment_ipn_secret', $from_id);
            break;
        case 11:
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", trim($text), "NamePay", "nowpayment_ipn_secret");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ IPN Secret با موفقیت تنظیم گردید.", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 12:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب کنید", $keyboardzarinpal, 'HTML');
            break;
        case 13:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal")['ValuePay'];
            $textaqayepardakht = "💳 مرچنت کد خود را از زرین پال دریافت و در این قسمت وارد کنید

مرچنت کد فعلی شما : $PaySetting";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textaqayepardakht, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('merchant_zarinpal', $from_id);
            break;
        case 14:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardzarinpal, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "merchant_zarinpal");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 15:
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getloc'], $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetLocationEdit', $from_id);
            break;
        }

        return true;
    }
}
