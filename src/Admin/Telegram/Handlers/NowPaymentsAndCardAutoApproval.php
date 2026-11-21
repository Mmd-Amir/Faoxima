<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $CartManage, $Exception_auto_cart_keyboard, $Trust_auto_cart_keyboard, $adminAction, $adminExecution, $autoconfirm_advanced_keyboard, $backadmin, $dataget, $from_id, $message_id, $nowpayment_setting_keyboard, $pdo, $setting, $text, $textbotlang, $user.
 * Inherited or exported by reference: $Response, $code_product, $panel, $product, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class NowPaymentsAndCardAutoApproval
{
    public static function handle(
        $CartManage,
        $Exception_auto_cart_keyboard,
        &$Response,
        $Trust_auto_cart_keyboard,
        $adminAction,
        $adminExecution,
        $autoconfirm_advanced_keyboard,
        $backadmin,
        &$code_product,
        $dataget,
        $from_id,
        $message_id,
        $nowpayment_setting_keyboard,
        &$panel,
        $pdo,
        &$product,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $nowpayment_setting_keyboard, 'HTML');
            break;
        case 2:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید که قابلیت تایید خودکار بدون بررسی  بعد از چند دقیقه رسید را تایید کند.
زمان خود را بر حسب دقیقه ارسال کنید
زمان فعلی : {$setting['timeauto_not_verify']}", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("gettimeauto", $from_id);
            break;
        case 3:
            if (!is_numeric($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("setting", "timeauto_not_verify", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ زمان با موفقیت ثبت گردید.", $CartManage, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 4:
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :name_product  AND agent = :agent AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') LIMIT 1");
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $status_name = [
                '0' => "خاموش",
                '1' => "روشن"
            ][$product['one_buy_status']];
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 از طریق این قابلیت می توانید تعیین کنید این محصول برای خرید اول باشد یا خیر", $Response, 'HTML');
            break;
        case 5:
            $code_product = $dataget[1];
            $status_now = $dataget[2];
            if ($status_now == '0') {
                $status_now = '1';
            } else {
                $status_now = '0';
            }
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("UPDATE product SET one_buy_status = :one_buy_status WHERE code_product = :code_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':one_buy_status', $status_now);
            $stmt->bindParam(':code_product', $code_product);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product  AND agent = :agent AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') LIMIT 1");
            $stmt->bindParam(':code_product', $code_product);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $status_name = [
                '0' => "خاموش",
                '1' => "روشن"
            ][$product['one_buy_status']];
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "📌 از طریق این قابلیت می توانید تعیین کنید این محصول برای خرید اول باشد یا خیر", $Response);
            break;
        case 6:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 تنظیمات پیشرفته تایید خودکار را انتخاب کنید
⚠️ استثناء: کاربران این لیست هرگز تایید خودکار نمی‌شوند
✅ معتمدین: کاربران این لیست بدون تاخیر تایید خودکار می‌شوند", $autoconfirm_advanced_keyboard, 'HTML');
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 تنظیمات پیشرفته تایید خودکار را انتخاب کنید
⚠️ استثناء: کاربران این لیست هرگز تایید خودکار نمی‌شوند
✅ معتمدین: کاربران این لیست بدون تاخیر تایید خودکار می‌شوند", $autoconfirm_advanced_keyboard, 'HTML');
            break;
        case 8:
            $list_Exceptions = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
            $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
            $count = count((array)$list_Exceptions);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 کاربران استثناء از تایید خودکار
تعداد کاربران در لیست: <b>$count</b>", $Exception_auto_cart_keyboard, 'HTML');
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 آیدی عددی کاربر را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getidExceptio", $from_id);
            break;
        case 10:
            if (!\Faoxima\Core\Database\Db::userExists($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
                return;
            }
            $list_Exceptions = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
            $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
            if (in_array((string)$text, array_map('strval', (array)$list_Exceptions))) {
                sendmessage($from_id, "❌ کاربر در لیست استثناء وجود دارد", $Exception_auto_cart_keyboard, 'HTML');
                return;
            }
            $list_Exceptions[] = (string)$text;
            $list_Exceptions = array_values($list_Exceptions);
            sendmessage($from_id, "✅ کاربر با موفقیت به لیست استثناء اضافه شد.", $Exception_auto_cart_keyboard, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 11:
            sendmessage($from_id, "📌 آیدی عددی کاربر را جهت حذف از لیست استثناء ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getidExceptioremove", $from_id);
            break;
        case 12:
            if (!\Faoxima\Core\Database\Db::userExists($text)) {
                sendmessage($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
                return;
            }
            $list_Exceptions = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
            $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
            if (!in_array((string)$text, array_map('strval', (array)$list_Exceptions))) {
                sendmessage($from_id, "❌ کاربر در لیست استثناء وجود ندارد", $Exception_auto_cart_keyboard, 'HTML');
                return;
            }
            $list_Exceptions = array_values(array_filter($list_Exceptions, fn($v) => (string)$v !== (string)$text));
            sendmessage($from_id, "✅ کاربر با موفقیت از لیست استثناء حذف شد.", $Exception_auto_cart_keyboard, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 13:
            $list_Exceptions = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
            $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
            if (empty($list_Exceptions)) {
                sendmessage($from_id, "❌ کاربری در لیست استثناء وجود ندارد", $Exception_auto_cart_keyboard, 'HTML');
                return;
            }
            sendmessage($from_id, "🚫 لیست استثناء از تایید خودکار (" . count($list_Exceptions) . " نفر):\n\n" . implode("\n", $list_Exceptions), $Exception_auto_cart_keyboard, 'HTML');
            break;
        case 14:
            $trustModeVal = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "trust_mode_active", "select")['ValuePay'];
            $isTrustOn = ($trustModeVal == "on");
            $statusLine = $isTrustOn
                ? "⚡️ حالت معتمدین: <b>فعال</b> — فقط معتمدین خودکار، بقیه دستی"
                : "💤 حالت معتمدین: <b>غیرفعال</b> — سیستم تایید خودکار معمولی";
            $toggleBtn = $isTrustOn ? "🔴 غیرفعال کردن حالت معتمدین" : "🟢 فعال کردن حالت معتمدین";
            $list_Trusted = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Trust_auto_cart", "select")['ValuePay'];
            $list_Trusted = is_string($list_Trusted) ? json_decode($list_Trusted, true) : [];
            $count = count((array)$list_Trusted);
            $dynTrustKb = json_encode(['keyboard' => [
                [['text' => $toggleBtn]],
                [['text' => "➕ افزودن به معتمدین"],['text' => "❌ حذف از معتمدین"]],
                [['text' => "👁 نمایش لیست معتمدین"]],
                [['text' => "◀️ بازگشت به تنظیمات پیشرفته"]]
            ], 'resize_keyboard' => true]);
            if (method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_finalizeInlineAdminKb')) {
                $dynTrustKb = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb($dynTrustKb, true);
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 کاربران معتمد برای تایید خودکار فوری
$statusLine
تعداد کاربران در لیست: <b>$count</b>", $dynTrustKb, 'HTML');
            break;
        case 15:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 آیدی عددی کاربر مورد اعتماد را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getidTrustAdd", $from_id);
            break;
        case 16:
            if (!\Faoxima\Core\Database\Db::userExists($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
                return;
            }
            $list_Trusted = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Trust_auto_cart", "select")['ValuePay'];
            $list_Trusted = is_string($list_Trusted) ? json_decode($list_Trusted, true) : [];
            if (in_array((string)$text, array_map('strval', (array)$list_Trusted))) {
                sendmessage($from_id, "❌ کاربر در لیست معتمدین وجود دارد", $Trust_auto_cart_keyboard, 'HTML');
                return;
            }
            $list_Trusted[] = (string)$text;
            $list_Trusted = array_values($list_Trusted);
            sendmessage($from_id, "✅ کاربر با موفقیت به لیست معتمدین اضافه شد.", $Trust_auto_cart_keyboard, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", json_encode($list_Trusted), "NamePay", "Trust_auto_cart");
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 17:
            sendmessage($from_id, "📌 آیدی عددی کاربر را جهت حذف از لیست معتمدین ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getidTrustRemove", $from_id);
            break;
        case 18:
            if (!\Faoxima\Core\Database\Db::userExists($text)) {
                sendmessage($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
                return;
            }
            $list_Trusted = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Trust_auto_cart", "select")['ValuePay'];
            $list_Trusted = is_string($list_Trusted) ? json_decode($list_Trusted, true) : [];
            if (!in_array((string)$text, array_map('strval', (array)$list_Trusted))) {
                sendmessage($from_id, "❌ کاربر در لیست معتمدین وجود ندارد", $Trust_auto_cart_keyboard, 'HTML');
                return;
            }
            $list_Trusted = array_values(array_filter($list_Trusted, fn($v) => (string)$v !== (string)$text));
            sendmessage($from_id, "✅ کاربر با موفقیت از لیست معتمدین حذف شد.", $Trust_auto_cart_keyboard, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", json_encode($list_Trusted), "NamePay", "Trust_auto_cart");
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 19:
            $list_Trusted = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Trust_auto_cart", "select")['ValuePay'];
            $list_Trusted = is_string($list_Trusted) ? json_decode($list_Trusted, true) : [];
            if (empty($list_Trusted)) {
                sendmessage($from_id, "❌ کاربری در لیست معتمدین وجود ندارد", $Trust_auto_cart_keyboard, 'HTML');
                return;
            }
            sendmessage($from_id, "✅ لیست کاربران معتمد (" . count($list_Trusted) . " نفر):\n\n" . implode("\n", $list_Trusted), $Trust_auto_cart_keyboard, 'HTML');
            break;
        case 20:
            $newMode = ($text == "🟢 فعال کردن حالت معتمدین") ? "on" : "off";
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $newMode, "NamePay", "trust_mode_active");
            $statusLine = ($newMode == "on")
                ? "⚡️ حالت معتمدین: <b>فعال</b> — فقط معتمدین خودکار، بقیه دستی"
                : "💤 حالت معتمدین: <b>غیرفعال</b> — سیستم تایید خودکار معمولی";
            $list_Trusted = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "Trust_auto_cart", "select")['ValuePay'];
            $list_Trusted = is_string($list_Trusted) ? json_decode($list_Trusted, true) : [];
            $count = count((array)$list_Trusted);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ تغییر ذخیره شد.
$statusLine
تعداد کاربران در لیست: <b>$count</b>", $Trust_auto_cart_keyboard, 'HTML');
            break;
        }

        return true;
    }
}
