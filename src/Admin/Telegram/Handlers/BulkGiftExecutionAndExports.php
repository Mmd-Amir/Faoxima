<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $CartManage, $ManagePanel, $adminAction, $adminExecution, $backadmin, $dataget, $datagetr, $first_name, $from_id, $keyboardadmin, $otherreport, $pdo, $setting, $shopkeyboard, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $dataoutput, $filename, $id_invoice, $id_user, $list, $listusers, $marzban_list_get, $message_id, $nameloc, $stmt, $userdate, $userslist.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BulkGiftExecutionAndExports
{
    public static function handle(
        $CartManage,
        $ManagePanel,
        $adminAction,
        $adminExecution,
        $backadmin,
        $dataget,
        $datagetr,
        &$dataoutput,
        &$filename,
        $first_name,
        $from_id,
        &$id_invoice,
        &$id_user,
        $keyboardadmin,
        &$list,
        &$listusers,
        &$marzban_list_get,
        &$message_id,
        &$nameloc,
        $otherreport,
        $pdo,
        $setting,
        $shopkeyboard,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        &$userdate,
        &$userslist
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $keyboardstatistics = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "❌ لفو ارسال هدیه", 'callback_data' => 'cancel_gift'],
                    ],
                ]
            ]);
            $userdata = json_decode($user['Processing_value'], true);
            if (!isset($userdata['typegift'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطایی رخ داده است مراحل را از اول طی کنید.", $keyboardadmin, "html");
                return;
            }
            $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات ارسال هدیه با موفقیت آغاز گردید پس از اضافه شدن و اتمام به شما اطلاع داده می شود.", $keyboardstatistics);
            $userdata['id_message'] = $message_id['result']['message_id'];
            $stmt = $pdo->prepare("SELECT username FROM invoice WHERE  (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$userdata['name_panel']}' AND name_product != 'سرویس تست'");
            $stmt->execute();
            $userslist = json_encode($stmt->fetchAll());
            file_put_contents('cronbot/gift', json_encode($userdata));
            file_put_contents('cronbot/username.json', $userslist);
            break;
        case 2:
            if (file_exists('cronbot/username.json')) unlink('cronbot/username.json');
            if (file_exists('cronbot/gift')) unlink('cronbot/gift');
            deletemessage($from_id, $message_id);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 ارسال هدیه لغو گردید.", $keyboardadmin, 'HTML');
            break;
        case 3:
            $id_user = $datagetr[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "id_user", $id_user);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🕘 زمان انقضا نمایندگی را ارسال نمایید. پس از پایان تعداد روز تعیین شده کاربر از حالت نمایندگی خارج شده و گروه کاربر f خواهد شد.
توجه داشته باشید این قابلیت ارتباطی با قابلیت ربات ساز یا ربات فروش نماینده ندارد و فقط مربوط به ربات اصلی شما است

📌 تعداد روز را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("gettime_expire_agent", $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $userdate = json_decode($user['Processing_value'], true);
            $timestamp = time() + (intval(value: $text) * 86400);
            \Faoxima\Core\Database\Db::update("user", "expire", $timestamp, "id", $userdate['id_user']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ تاریخ انقضا تنظیم شد.
📌 پس از پایان زمان گروه کاربری کاربر به f تغییر داده می شود و به کاربر اطلاع داده می شود.", $keyboardadmin, 'HTML');
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 لیست آیدی هایی که  می خواهید شماره کارت برایشان نشان داده شود را ارسال شود
مثال :
1234435423
23423131", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getlistidcart", $from_id);
            break;
        case 6:
            $list = explode("\n", $text);
            foreach ($list as $id_user) {
                if (!\Faoxima\Core\Database\Db::userExists($id_user)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 کاربر با آیدی عددی $id_user در  دیتابیس وجود ندارد", $backadmin, 'HTML');
                    continue;
                }
                \Faoxima\Core\Database\Db::update("user", "cardpayment", "1", "id", $id_user);
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ شماره کارت برای کاربران ارسال شده فعال گردید.", $CartManage, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 7:
            $listusers = \Faoxima\Core\Database\Db::select("user", "id", "cardpayment", "1", "fetchAll");
            if (!$listusers) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 برای کاربری شماره کارت فعال نشده است", $CartManage, 'HTML');
                return;
            }
            $filename = 'cartlist.txt';
            foreach ($listusers as $id_user) {
                file_put_contents($filename, $id_user['id'] . "\n", FILE_APPEND);
            }
            sendDocument($from_id, $filename, "🪪 لیست کاربرانی که شماره کارت برای آنها فعال است");
            unlink($filename);
            break;
        case 8:
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbanporsant_one_buy = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardDiscountaffiliates = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates, 'HTML');
            break;
        case 9:
            \Faoxima\Core\Database\Db::update("affiliates", "porsant_one_buy", "off_buy_porsant");
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbanporsant_one_buy = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardDiscountaffiliates = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates);
            break;
        case 10:
            \Faoxima\Core\Database\Db::update("affiliates", "porsant_one_buy", "on_buy_porsant");
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbanporsant_one_buy = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardDiscountaffiliates = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates);
            break;
        case 11:
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "on_hold") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ هنوز به کانفیگ متصل نشده است کانفیگ و امکان تغییر وضعیت سرویس وجود ندارد. بعد از متصل شدن به کانفیگ می توانید از این قابلیت استفاده نمایید.", null, 'html');
                return;
            }
            if ($DataUserOut['status'] == "Unsuccessful") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            if ($DataUserOut['status'] == "active") {
                $confirmdisableaccount = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ تایید و غیرفعال کانفیگ', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                        ],
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                        ]
                    ]
                ]);
                Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما خاموش و دیگر امکان اتصال به کانفیگ وجود ندارد.
⚠️ در صورتی که میخواهید مجدد کانفیگ فعال شود باید از بخش مدیریت سرویس دکمه <u>💡 روشن کردن اکانت</u> را کلیک کنید", $confirmdisableaccount);
            } else {
                $confirmdisableaccount = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ تایید و فعال کانفیگ', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                        ],
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                        ]
                    ]
                ]);
                Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما روشن خواهد شد. و می توانید به کانفیگ خود متصل شوید
⚠️ در صورتی که میخواهید مجدد کانفیگ غیرفعال شود باید از بخش مدیریت سرویس دکمه <u>❌ خاموش کردن اکانت</u>را کلیک کنید", $confirmdisableaccount);
            }
            break;
        case 12:
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
            if ($dataoutput['status'] == "Unsuccessful") {
                Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "active") {
                \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
                Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
            } else {
                \Faoxima\Core\Database\Db::update("invoice", "Status", "disablebyadmin", "id_invoice", $nameloc['id_invoice']);
                Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
            }
            break;
        case 13:
            $id_invoice = $dataget[1];
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "تایید و حذف ", 'callback_data' => "confirmremovefulls-" . $id_invoice],
                    ],
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $id_invoice],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, "📌 با تایید بر روی گزینه زیر این سرویس بطور کامل از دیتابیس ربات حذف خواهد شد و دیگرجزء آمار حساب نخواهد شد ( این بخش سرویس را از پنل حذف نمی کند و فقط از دیتابیس ربات حذف می کند)", $bakinfos);
            break;
        case 14:
            $id_invoice = $dataget[1];
            $invocie = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            $stmt = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :id_invoice");
            $stmt->bindParam(':id_invoice', $id_invoice, PDO::PARAM_STR);
            $stmt->execute();
            Editmessagetext($from_id, $message_id, "✅ سرویس با موفقیت حذف گردید.", json_encode(['inline_keyboard' => []]));
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => "🔗 یک ادمین یک سرویس را از دیتابیس ربات حذف کرد.

- آیدی عددی ادمین :‌$from_id
- نام ادمین : $first_name
- نام کاربری سرویس :‌ {$invocie['username']}",
                    'parse_mode' => "HTML"
                ]);
            }
            break;
        case 15:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جهت اضافه کردن دسته بندی نام دسته بندی را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getremarkcategory", $from_id);
            break;
        case 16:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ دسته بندی با موفقیت اضافه گردید.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $stmt = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
            $stmt->bindParam(1, $text);
            $stmt->execute();
            break;
        case 17:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 دسته بندی خود را جهت حذف انتخاب کنید", \Faoxima\Telegram\Keyboard\AdminCategoryFunctions::KeyboardCategoryadmin(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("removecategory", $from_id);
            break;
        }

        return true;
    }
}
