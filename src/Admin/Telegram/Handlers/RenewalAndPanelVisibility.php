<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $adminnumber, $backadmin, $dataget, $from_id, $hideuserid, $keyboardadmin, $lottery, $message_id, $pdo, $setting, $text, $textbotlang, $update, $user, $wheelkeyboard.
 * Inherited or exported by reference: $data, $key, $payment, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RenewalAndPanelVisibility
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $adminnumber,
        $backadmin,
        &$data,
        $dataget,
        $from_id,
        $hideuserid,
        &$key,
        $keyboardadmin,
        $lottery,
        $message_id,
        &$payment,
        $pdo,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        $wheelkeyboard
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $marzbanstatus = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $keyboardstatus = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
                    ],
                    [
                        ['text' => "🔙 بازگشت به منوی پنل", 'callback_data' => 'panelmenu_back'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['activepanel'], $keyboardstatus, 'HTML');
            break;
        case 2:
            \Faoxima\Core\Database\Db::update("marzban_panel", "status_extend", "off_extend", "name_panel", $user['Processing_value']);
            $marzbanstatus = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $keyboardstatus = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
                    ],
                    [
                        ['text' => "🔙 بازگشت به منوی پنل", 'callback_data' => 'panelmenu_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepanelStatusOff'], $keyboardstatus);
            break;
        case 3:
            \Faoxima\Core\Database\Db::update("marzban_panel", "status_extend", "on_extend", "name_panel", $user['Processing_value']);
            $marzbanstatus = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $keyboardstatus = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
                    ],
                    [
                        ['text' => "🔙 بازگشت به منوی پنل", 'callback_data' => 'panelmenu_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepaneltatuson'], $keyboardstatus);
            break;
        case 4:
            $iduser = $dataget[1];
            $userdata = \Faoxima\Core\Database\Db::select("user", "*", "id", $iduser, "select");
            if ($userdata['joinchannel'] == "active") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✍️ کاربر از قبل تایید شده است", null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "joinchannel", "active", "id", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 کاربر از این پس بدون عضویت در کانال می تواند در ربات فعالیت داشته باشد", $keyboardadmin, 'HTML');
            break;
        case 5:
            $iduser = $dataget[1];
            $userdata = \Faoxima\Core\Database\Db::select("user", "*", "id", $iduser, "select");
            \Faoxima\Core\Database\Db::update("user", "Balance", "0", "id", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "موجودی کاربر به مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($userdata['Balance']) . " صفر گردید", $keyboardadmin, 'HTML');
            break;
        case 6:
            $idadmin = trim($dataget[1]);
            $mainAdminId = trim((string) $adminnumber);
            if ($idadmin === $mainAdminId) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ امکان حذف ادمین اصلی وجود ندارد", null, 'HTML');
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM admin WHERE TRIM(id_admin) = :id_admin");
            $stmt->bindParam(':id_admin', $idadmin, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ ادمینی با این شناسه یافت نشد.", null, 'HTML');
                return;
            }
            $removeAdminBackKb = json_encode(['inline_keyboard' => [[['text' => "◀️ بازگشت به لیست ادمین‌ها", 'callback_data' => "admin_mgr"]]]]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ ادمین با موفقیت حذف گردید", $removeAdminBackKb, 'HTML');
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آیدی عددی کاربر را برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getuserhide', $from_id);
            break;
        case 8:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], "✅ پنل با موفقیت برای کاربر مخفی گردید");
            if ($typepanel['hide_user'] == null) {
                $hideuserid = [];
            } else {
                $hideuserid = json_decode($typepanel['hide_user'], true);
            }
            $hideuserid[] = $text;
            $hideuserid = json_encode($hideuserid);
            \Faoxima\Core\Database\Db::update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آیدی عددی کاربر را برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getuserhideforremove', $from_id);
            break;
        case 10:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            if ($typepanel['hide_user'] == null) {
                \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], "❌ هیچ کاربری در لیست مخفی شدگان وجود ندارد");
                return;
            }
            $hideuserid = json_decode($typepanel['hide_user'], true);
            if (count($hideuserid) == 0) {
                \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], "❌  کاربر در لیست وجود ندارد");
                return;
            }
            if (!in_array($text, $hideuserid)) {
                \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], "❌ کاربر در لیست وجود ندارد.");
                return;
            }
            $key = array_search($text, $hideuserid);
            if ($key !== false) {
                unset($hideuserid[$key]);
                $hideuserid = array_values($hideuserid);
            }
            $hideuserid = json_encode($hideuserid);
            \Faoxima\Core\Database\Db::update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], "✅  کاربر با موفقیت از لیست حذف گردید.");
            break;
        case 11:
            \Faoxima\Telegram\Navigation\Steps::step('featnav_lottery', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $lottery, 'HTML');
            break;
        case 12:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getonelotary", $from_id);
            break;
        case 13:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $data = json_decode($setting['Lottery_prize'], true);
            $data['one'] = $text;
            $data = json_encode($data, true);
            \Faoxima\Core\Database\Db::update("setting", "Lottery_prize", $data, null, null);
            break;
        case 14:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getonelotary2", $from_id);
            break;
        case 15:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $data = json_decode($setting['Lottery_prize'], true);
            $data['tow'] = $text;
            $data = json_encode($data, true);
            \Faoxima\Core\Database\Db::update("setting", "Lottery_prize", $data, null, null);
            break;
        case 16:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getonelotary3", $from_id);
            break;
        case 17:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $data = json_decode($setting['Lottery_prize'], true);
            $data['theree'] = $text;
            $data = json_encode($data, true);
            \Faoxima\Core\Database\Db::update("setting", "Lottery_prize", $data, null, null);
            break;
        case 18:
            \Faoxima\Telegram\Navigation\Steps::step('featnav_wheel', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $wheelkeyboard, 'HTML');
            break;
        case 19:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricewheel", $from_id);
            break;
        case 20:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $wheelkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("setting", "wheelـluck_price", $text, null, null);
            break;
        case 21:
            $sql = "SELECT * FROM Payment_report WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $list_payment = $stmt->fetchAll();
            $list_payment_count = $stmt->rowCount();
            if ($list_payment_count == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ هیچ پرداخت تایید نشده ای ندارید.", null, 'HTML');
                return;
            }
            $list_pay = ['inline_keyboard' => []];
            foreach ($list_payment as $payment) {
                $list_pay['inline_keyboard'][] = [
                    ['text' => $payment['id_user'], 'callback_data' => "checkpay"]
                ];
                $list_pay['inline_keyboard'][] = [
                    ['text' => "✅", 'callback_data' => "Confirm_pay_{$payment['id_order']}"],
                    ['text' => "❌", 'callback_data' => "reject_pay_{$payment['id_order']}"],
                    ['text' => "📝", 'callback_data' => "showinfopay_{$payment['id_order']}"],
                    ['text' => "🗑", 'callback_data' => "removeresid_{$payment['id_order']}"],
                ];
                $list_pay['inline_keyboard'][] = [
                    ['text' => "💸💸💸💸💸💸💸💸💸", 'callback_data' => "checkpay"]
                ];
            }
            $list_pay['inline_keyboard'][] = [
                ['text' => "❌ حذف همه رسید ها", 'callback_data' => "removeresid"]
            ];
            $list_pay_json = json_encode($list_pay, JSON_UNESCAPED_UNICODE);
            if ($list_pay_json === false) {
                error_log('Failed to encode pending receipts keyboard: ' . json_last_error_msg());
                $list_pay_json = json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE);
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 پرداخت های تایید نشده کارت به کارت
در این بخش میتوانید پرداخت های تایید نشده مشاهده و تایید یا رد نمایید.
❌ : رد کردن پرداخت
✅ : تایید پرداخت
📝 مشخصات پرداخت
🗑 : حذف رسید بدون اطلاع کاربر", $list_pay_json, 'HTML');
            break;
        }

        return true;
    }
}
