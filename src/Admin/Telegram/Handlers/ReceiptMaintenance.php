<?php

namespace Faoxima\Admin\Telegram\Handlers;

use stdClass;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $dataget, $datain, $from_id, $ip_list, $ipdel_match, $iplogin_unlimited, $message_id, $pdo, $shopkeyboard, $text, $textbotlang, $toggle_msg, $update, $user.
 * Inherited or exported by reference: $key, $marzban_list_get, $stmt, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReceiptMaintenance
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $dataget,
        $datain,
        $from_id,
        $ip_list,
        $ipdel_match,
        $iplogin_unlimited,
        &$key,
        &$marzban_list_get,
        $message_id,
        $pdo,
        $shopkeyboard,
        &$stmt,
        $text,
        $textbotlang,
        $toggle_msg,
        $update,
        $user,
        &$value
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            deletemessage($from_id, $message_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅  تمامی رسید ها با موفقیت حذف شدند ", null, 'HTML');
            if (is_callable([\Faoxima\Payments\Receipts\ManualReview::class, 'receiptSoftDeleteAll'])) {
                \Faoxima\Payments\Receipts\ManualReview::receiptSoftDeleteAll($pdo);
            } else {
                $sql = "UPDATE Payment_report SET payment_Status = 'reject',dec_not_confirmed = 'remove_all' WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
            }
            break;
        case 2:
            $idorder = $dataget[1];
            $paymentUser = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $idorder, "select");
            if ($paymentUser == false) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => "تراکنش حذف شده است",
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            $text_order = "🛒 شماره پرداخت  :  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ شناسه کاربر : <code>{$paymentUser['id_user']}</code>
💰 مبلغ پرداختی : " . \Faoxima\Core\Support\Formatting::rxFormatToman($paymentUser['price']) . " تومان
⚜️ وضعیت پرداخت : {$paymentUser['payment_Status']}
⭕️ روش پرداخت : {$paymentUser['Payment_Method']}
📆 تاریخ خرید :  {$paymentUser['time']}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_order, null, 'HTML');
            break;
        case 3:
            $jovadProductPanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
            if (($jovadProductPanel['type'] ?? '') === 'jovadpanel') {
                $jovadProtocolKeyboard = json_encode(['keyboard' => [
                    [['text' => 'xray'], ['text' => 'wireguard'], ['text' => 'openvpn']],
                    [['text' => 'dns'], ['text' => 'xray,wireguard'], ['text' => 'xray,openvpn']],
                    [['text' => 'wireguard,openvpn'], ['text' => 'xray,wireguard,openvpn'], ['text' => 'xray,dns']],
                    [['text' => 'xray,wireguard,dns'], ['text' => 'openvpn,dns'], ['text' => 'xray,wireguard,openvpn,dns']],
                    [['text' => $textbotlang['Admin']['backadmin']]],
                ], 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "کانال‌های این محصول را انتخاب کنید. WireGuard و OpenVPN هنگام دریافت کانفیگ به‌صورت فایل ارسال می‌شوند؛ DNS به‌صورت اطلاعات/پروفایل جداگانه در منوی کانفیگ نمایش داده می‌شود.", $jovadProtocolKeyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("getdatainboundproduct", $from_id);
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در صورتی که پنل مرزبان  یا مرزنشین هستید یک نام کاربری کانفیگ از پنل کپی و ارسال نمایید در غیراینصورت برای پنل های ثنایی و علیرضا شناسه اینباند را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getdatainboundproduct", $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
            $datainbound = "";
            if ($marzban_list_get['type'] == "marzban") {
                $DataUserOut = \Faoxima\Services\Panels\Drivers\Marzban::getuser($text, $marzban_list_get['name_panel']);
                if (!empty($DataUserOut['error'])) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $DataUserOut['error'], null, 'HTML');
                    return;
                }
                if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                    return;
                }
                $DataUserOut = json_decode($DataUserOut['body'], true);
                if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                    return;
                }
                foreach ($DataUserOut['proxies'] as $key => &$value) {
                    if ($key == "shadowsocks") {
                        unset($DataUserOut['proxies'][$key]['password']);
                    } elseif ($key == "trojan") {
                        unset($DataUserOut['proxies'][$key]['password']);
                    } else {
                        unset($DataUserOut['proxies'][$key]['id']);
                    }
                    if (count($DataUserOut['proxies'][$key]) == 0) {
                        $DataUserOut['proxies'][$key] = new stdClass();
                    }
                }
                $stmt = $pdo->prepare("UPDATE product SET proxies = :proxies WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
                $proxies_json = json_encode($DataUserOut['proxies']);
                $stmt->bindParam(':proxies', $proxies_json);
                $stmt->bindParam(':name_product', $user['Processing_value']);
                $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
                $stmt->bindParam(':agent', $user['Processing_value_tow']);
                $stmt->execute();
                $datainbound = json_encode($DataUserOut['inbounds']);
            } elseif ($marzban_list_get['type'] == "jovadpanel") {
                $jovadRequestedChannels = preg_split('/[,\s]+/', strtolower(trim((string) $text)), -1, PREG_SPLIT_NO_EMPTY);
                if (empty($jovadRequestedChannels) || array_diff($jovadRequestedChannels, ['xray', 'wireguard', 'openvpn', 'dns'])) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "پروتکل معتبر انتخاب کنید: xray، wireguard یا openvpn؛ چند پروتکل را با کامای انگلیسی جدا کنید.", null, 'HTML');
                    return;
                }
                $datainbound = json_encode(array_values(array_unique($jovadRequestedChannels)));
            } elseif ($marzban_list_get['type'] == "x-ui_single") {
                $datainbound = $text;
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ برای این پنل قابلیت تعریف اینباند وجود ندارد", $shopkeyboard, 'HTML');
                return;
            }
            $stmt = $pdo->prepare("UPDATE product SET inbounds = :inbounds WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':inbounds', $datainbound);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅محصول بروزرسانی شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            [$ip_list, $iplogin_unlimited] = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::getIpLoginState();

            $msg = "🛡 <b>تنظیم آیپی ورود</b>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            if ($iplogin_unlimited) {
                $msg .= "♾️ <b>حالت نامحدود فعال است.</b>\n";
                $msg .= "ورود به پنل وب از هر آیپی‌ای آزاد است.\n";
            } elseif (empty($ip_list)) {
                $msg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n";
                $msg .= "در این حالت <b>ورود به پنل وب برای همه مسدود است.</b>\n";
            } else {
                $msg .= "📋 آیپی‌های مجاز ورود:\n";
                foreach ($ip_list as $i => $ip) {
                    $msg .= ($i + 1) . ". <code>" . htmlspecialchars($ip) . "</code>\n";
                }
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "➕ برای افزودن آیپی جدید، دکمه <b>افزودن آیپی</b> را بزنید.\n";
            $msg .= "♾️ برای دسترسی نامحدود، دکمه <b>حالت نامحدود</b> را بزنید.";

            $ip_keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildIpLoginKeyboard($ip_list, $iplogin_unlimited);

            if ($message_id) {
                Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
            }
            break;
        case 6:
            if ($datain == "iploginunlim_on") {
                \Faoxima\Core\Database\Db::update("setting", "iplogin", "*", null, null);
                $toggle_msg = "♾️ <b>حالت نامحدود فعال شد.</b>\nورود به پنل از هر آیپی‌ای ممکن است.";
                $ip_list = [];
                $iplogin_unlimited = true;
            } else {
                \Faoxima\Core\Database\Db::update("setting", "iplogin", json_encode([]), null, null);
                $toggle_msg = "🔒 <b>حالت نامحدود غیرفعال شد.</b>\nبرای ورود، آیپی مجاز را تنظیم کنید.";
                $ip_list = [];
                $iplogin_unlimited = false;
            }
            $msg = $toggle_msg . "\n━━━━━━━━━━━━━━━━━━━━\n";
            if ($iplogin_unlimited) {
                $msg .= "♾️ <b>حالت نامحدود فعال است.</b>\n";
            } elseif (empty($ip_list)) {
                $msg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n";
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━";
            $ip_keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildIpLoginKeyboard($ip_list, $iplogin_unlimited);
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
            }
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 آیپی جدید خود را ارسال کنید.\n<i>مثال: 1.2.3.4</i>", null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getiplogin", $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            $new_ip = trim($text);
            if (!filter_var($new_ip, FILTER_VALIDATE_IP)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آیپی وارد شده معتبر نیست. لطفاً یک آیپی صحیح ارسال کنید.\nمثال: <code>1.2.3.4</code>", null, 'HTML');
                return;
            }
            $setting_row = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select");
            $raw_ip = $setting_row['iplogin'] ?? '';
            $ip_list = [];
            if (!empty($raw_ip) && $raw_ip !== '0') {
                $decoded = json_decode($raw_ip, true);
                if (is_array($decoded)) {
                    $ip_list = $decoded;
                } elseif (filter_var($raw_ip, FILTER_VALIDATE_IP)) {
                    $ip_list = [$raw_ip];
                }
            }
            if (in_array($new_ip, $ip_list)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ این آیپی قبلاً در لیست وجود دارد.", null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $ip_list[] = $new_ip;
            \Faoxima\Core\Database\Db::update("setting", "iplogin", json_encode(array_values($ip_list)), null, null);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آیپی <code>" . htmlspecialchars($new_ip) . "</code> با موفقیت اضافه شد.", $shopkeyboard, 'HTML');
            break;
        case 9:
            $del_index = (int)$ipdel_match[1];
            $setting_row = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select");
            $raw_ip = $setting_row['iplogin'] ?? '';
            $ip_list = [];
            if (!empty($raw_ip) && $raw_ip !== '0') {
                $decoded = json_decode($raw_ip, true);
                if (is_array($decoded)) {
                    $ip_list = $decoded;
                } elseif (filter_var($raw_ip, FILTER_VALIDATE_IP)) {
                    $ip_list = [$raw_ip];
                }
            }
            if (!isset($ip_list[$del_index])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آیپی مورد نظر یافت نشد.", $shopkeyboard, 'HTML');
                return;
            }
            $deleted_ip = $ip_list[$del_index];
            array_splice($ip_list, $del_index, 1);
            \Faoxima\Core\Database\Db::update("setting", "iplogin", json_encode(array_values($ip_list)), null, null);

            $msg = "🛡 <b>تنظیم آیپی ورود</b>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "✅ آیپی <code>" . htmlspecialchars($deleted_ip) . "</code> حذف شد.\n\n";
            if (empty($ip_list)) {
                $msg .= "⚠️ هیچ آیپی‌ای تنظیم نشده است.\n";
                $msg .= "در این حالت <b>ورود به پنل وب برای همه مسدود است.</b>\n";
            } else {
                $msg .= "📋 آیپی‌های مجاز ورود:\n";
                foreach ($ip_list as $i => $ip) {
                    $msg .= ($i + 1) . ". <code>" . htmlspecialchars($ip) . "</code>\n";
                }
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━";

            $ip_keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildIpLoginKeyboard($ip_list, false);

            if ($message_id) {
                Editmessagetext($from_id, $message_id, $msg, $ip_keyboard_json);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $msg, $ip_keyboard_json, 'HTML');
            }
            break;
        }

        return true;
    }
}
