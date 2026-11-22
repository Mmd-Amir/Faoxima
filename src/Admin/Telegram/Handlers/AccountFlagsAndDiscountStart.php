<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $ManagePanel, $_toggle_notice, $adminAction, $adminExecution, $backadmin, $connect, $dataget, $from_id, $keyboard, $keyboardadmin, $message_id, $otherservice, $setting, $shopkeyboard, $text_inline, $textbotlang.
 * Inherited or exported by reference: $Response, $info_product, $text, $username.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AccountFlagsAndDiscountStart
{
    public static function handle(
        $ManagePanel,
        &$Response,
        $_toggle_notice,
        $adminAction,
        $adminExecution,
        $backadmin,
        $connect,
        $dataget,
        $from_id,
        &$info_product,
        $keyboard,
        $keyboardadmin,
        $message_id,
        $otherservice,
        $setting,
        $shopkeyboard,
        &$text,
        $text_inline,
        $textbotlang,
        &$username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $cardId = (int)$dataget[1];
            $iduser = (int)$dataget[2];
            $_tb_res = mysqli_query($connect, "SELECT id FROM user_card_block WHERE user_id = {$iduser} AND card_id = {$cardId} LIMIT 1");
            if ($_tb_res && mysqli_num_rows($_tb_res) > 0) {
                mysqli_query($connect, "DELETE FROM user_card_block WHERE user_id = {$iduser} AND card_id = {$cardId}");
                $_toggle_notice = "✅ کارت نمایان شد";
            } else {
                mysqli_query($connect, "INSERT IGNORE INTO user_card_block (user_id, card_id) VALUES ({$iduser}, {$cardId})");
                $_toggle_notice = "❌ کارت پنهان شد";
            }
            $allCards2 = [];
            $_ac2_res = mysqli_query($connect, "SELECT id, cardnumber, namecard FROM card_number WHERE is_active = 1 ORDER BY created_at ASC LIMIT 50");
            if ($_ac2_res) {
                while ($_ac2_row = mysqli_fetch_assoc($_ac2_res)) {
                    $allCards2[] = $_ac2_row;
                }
            }
            $blockedIds2 = [];
            $_bl2_res = mysqli_query($connect, "SELECT card_id FROM user_card_block WHERE user_id = {$iduser}");
            if ($_bl2_res) {
                while ($_bl2_row = mysqli_fetch_assoc($_bl2_res)) {
                    $blockedIds2[] = (int)$_bl2_row['card_id'];
                }
            }
            $_uck2_rows = [];
            foreach ($allCards2 as $_uc2_card) {
                $_uc2_cid = (int)$_uc2_card['id'];
                $_uc2_last4 = substr((string)$_uc2_card['cardnumber'], -4);
                $_uc2_blocked = in_array($_uc2_cid, $blockedIds2, true);
                $_uc2_status = $_uc2_blocked ? "❌ پنهان" : "✅ نمایان";
                $_uck2_rows[] = [[
                    'text' => "{$_uc2_status} | *{$_uc2_last4} — {$_uc2_card['namecard']}",
                    'callback_data' => "toggleusercard_{$_uc2_cid}_{$iduser}",
                ]];
            }
            $_uck2_rows[] = [['text' => "🔙 بازگشت", 'callback_data' => "manageuser_{$iduser}"]];
            $ucMenu2 = json_encode(['inline_keyboard' => $_uck2_rows], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "💳 مدیریت کارت‌های کاربر #{$iduser} — {$_toggle_notice}\n\nروی هر کارت کلیک کنید تا وضعیت نمایش آن برای این کاربر تغییر کند:", $ucMenu2, 'HTML');
            break;
        case 2:
            $iduser = $dataget[1];
            $userdata = \Faoxima\Core\Database\Db::select("user", "*", "id", $iduser, "select");
            if ($userdata['User_Status'] == "Active") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['UserNotBlock'], null, 'HTML');
                return;
            }
            $textblok = "کاربر با آیدی عددی
$iduser  در ربات  رفع مسدود گردید
<blockquote>ادمین مسدود کننده : $from_id</blockquote>";
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
                    ],
                ]
            ]);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $textblok,
                    'parse_mode' => "HTML",
                    'reply_markup' => $Response
                ]);
            }
            \Faoxima\Admin\Users\UserStatus::change($iduser, 'Active', 'update');
            \Faoxima\Core\Database\Db::update("user", "description_blocking", " ", "id", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['UserUnblocked'], $keyboardadmin, 'HTML');
            sendmessage($iduser, "✳️ حساب کاربری شما از مسدودی خارج شد ✳️
اکنون میتوانید از ربات استفاده کنید ✔️", $keyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 3:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "number", "confrim number by admin", "id", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['phone']['active'], $keyboardadmin, 'HTML');
            break;
        case 4:
            $iduser = $dataget[1];
            $_stmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_user = ?");
            $_stmt->bind_param("s", $iduser);
            $_stmt->execute();
            $PaymentUsers = $_stmt->get_result();
            $_stmt->close();
            foreach ($PaymentUsers as $paymentUser) {
                $text_order = "🛒 شماره پرداخت  :  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ شناسه کاربر : <code>{$paymentUser['id_user']}</code>
💰 مبلغ پرداختی : " . \Faoxima\Core\Support\Formatting::rxFormatToman($paymentUser['price']) . " تومان
⚜️ وضعیت پرداخت : {$paymentUser['payment_Status']}
⭕️ روش پرداخت : {$paymentUser['Payment_Method']}
📆 تاریخ خرید :  {$paymentUser['time']}";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_order, null, 'HTML');
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['sendpayemntlist'], $keyboardadmin, 'HTML');
            break;
        case 5:
            $iduser = $dataget[1];
            $affiliatesUsers = \Faoxima\Core\Database\Db::select("user", "*", "affiliates", $iduser, "count");
            if ($affiliatesUsers == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کاربر دارای زیرمجموعه نمی باشد.", null, 'HTML');
                return;
            }
            $affiliatesUsers = \Faoxima\Core\Database\Db::select("user", "*", "affiliates", $iduser, "fetchAll");
            $count = 0;
            $text_affiliates = "";
            foreach ($affiliatesUsers as $affiliatesUser) {
                $text_affiliates .= "<code>{$affiliatesUser['id']}</code>\n\r";
                $count++;
                if ($count == 10) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_affiliates, null, 'HTML');
                    $count = 0;
                    $text_affiliates = "";
                }
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_affiliates, null, 'HTML');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 شناسه مربوط به زیرمجموعه های کاربر ارسال گردید.", $keyboardadmin, 'HTML');
            break;
        case 6:
            $iduser = $dataget[1];
            $user2 = \Faoxima\Core\Database\Db::select("user", "*", "id", $iduser, "select");
            $user2 = \Faoxima\Core\Database\Db::select("user", "*", "id", $user2['affiliates'], "select");
            $affiliatescount = intval($user2['affiliatescount']) - 1;
            \Faoxima\Core\Database\Db::update("user", "affiliatescount", $affiliatescount, "id", $user2['id']);
            \Faoxima\Core\Database\Db::update("user", "affiliates", "0", "id", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 کاربر از زیرمجموعه خارج شد.", $keyboardadmin, 'HTML');
            break;
        case 7:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "affiliatescount", "0", "id", $iduser);
            \Faoxima\Core\Database\Db::update("user", "affiliates", "0", "affiliates", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 زیرمجموعه های کاربر حذف شد.", $keyboardadmin, 'HTML');
            break;
        case 8:
            $username = $dataget[1];
            $info_product = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $username, "select");
            $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
            $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
            \Faoxima\Core\Database\Db::update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
            rxRemoveMessageKeyboard($from_id, $message_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 9:
            $username = $dataget[1];
            $info_product = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $username, "select");
            if ($info_product['Status'] == "removebyadmin") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ سرویس از قبل حذف شده است", $keyboardadmin, 'HTML');
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
            if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
            } else {
                if ($DataUserOut['status'] == "Unsuccessful") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, 'خطایی رخ داده است', $keyboardadmin, 'HTML');
                }
            }
            $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
            \Faoxima\Core\Database\Db::update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
            $Balance_user = \Faoxima\Core\Database\Db::select("user", "*", "id", $info_product['id_user'], "select");
            $Balance_add_user = $Balance_user['Balance'] + $info_product['price_product'];
            \Faoxima\Core\Database\Db::update("user", "Balance", $Balance_add_user, "id", $info_product['id_user']);
            $textadd = "💎 کاربر عزیز مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($info_product['price_product']) . " تومان به موجودی کیف پول تان اضافه گردید.";
            sendmessage($info_product['id_user'], $textadd, null, 'HTML');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
            rxRemoveMessageKeyboard($from_id, $message_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 11:
            if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['PriceCodesell'], null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_price_codesell', $from_id);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "code", strtolower($text));
            break;
        case 12:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "price", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discountsell']['getlimit'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlimitcode', $from_id);
            break;
        case 13:
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "limitDiscount", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['agentcode'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(true), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('gettypecodeagent', $from_id);
            break;
        case 14:
            $agentst = ["n", "n2", "f", "allusers"];
            $text = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agentst);
            if ($text === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(true), 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "agent", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 کد تخفیف برای چند ساعت فعال باشد . در صورتی که میخواهید نامحدود باشد عدد 0 را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('gettimediscount', $from_id);
            break;
        case 15:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            if (intval($text) == 0) {
                $text = "0";
            } else {
                $text = time() + (intval($text) * 3600);
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "time", $text);
            $keyboarddiscount = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "تمامی خرید ها", 'callback_data' => "discountlimitbuy_0"],
                        ['text' => "خرید اول", 'callback_data' => "discountlimitbuy_1"],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['firstdiscount'], $keyboarddiscount, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getfirstdiscount', $from_id);
            break;
        case 16:
            $discountbuylimit = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "usefirst", $discountbuylimit);
            if (intval($discountbuylimit) == 1) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌محدودیت استفاده برای یک کاربر را ارسال نمایید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('getuseuser', $from_id);
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "typediscount", "all");
            } else {
                $keyboarddiscount = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => "خرید", 'callback_data' => "discounttype_buy"],
                            ['text' => "تمدید", 'callback_data' => "discounttype_extend"],
                        ],
                        [
                            ['text' => "هردو", 'callback_data' => "discounttype_all"]
                        ]
                    ]
                ]);
                Editmessagetext($from_id, $message_id, "📌 کد تخفیف برای کدوم بخش باشد", $keyboarddiscount);
            }
            break;
        }

        return true;
    }
}
