<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $datagetr, $from_id, $json_list_marzban_panel, $keyboardadmin, $keyboardchangelimit, $message_id, $pdo, $rwSlotMatch, $setting, $text, $textbotlang, $update, $user, $userdate.
 * Inherited or exported by reference: $id_user, $panel, $userslist, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class LocationLimitsAndBulkGifts
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $datagetr,
        $from_id,
        &$id_user,
        $json_list_marzban_panel,
        $keyboardadmin,
        $keyboardchangelimit,
        $message_id,
        &$panel,
        $pdo,
        $rwSlotMatch,
        $setting,
        $text,
        $textbotlang,
        $update,
        $user,
        $userdate,
        &$userslist,
        &$value
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب نمایید.
۱ - محدودیت کلی کاربر در کل چند بار می تواند تغییر لوکیشن انجام دهد.
۲ - محدودیت رایگان  کاربر از محدودیت کلی چند بار می تواند رایگان تغییر لوکیشن دهد.", $keyboardchangelimit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('featnav_changeloc', $from_id);
            break;
        case 2:
            $limitnumber = json_decode($setting['limitnumber'], true);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌  محدودیت کلی که کاربر می تواند تغییر لوکیشن انجام دهد را ارسال کنید توجه داشته باشید این محدودیت برای تمام کانفیگ ها  است
محدودیت فعلی : {$limitnumber['all']}", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("limitchangeall", $from_id);
            break;
        case 3:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ محدودیت با موفقیت تنظیم شد.", $keyboardchangelimit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $value = json_decode($setting['limitnumber'], true);
            $value['all'] = intval($text);
            \Faoxima\Core\Database\Db::update("setting", "limitnumber", json_encode($value), null, null);
            break;
        case 4:
            $limitnumber = json_decode($setting['limitnumber'], true);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌  محدودیت رایگانی که کاربر می تواند تغییر لوکیشن انجام دهد را ارسال کنید توجه داشته باشید این محدودیت برای تمام کانفیگ ها  است
محدودیت فعلی : {$limitnumber['free']}", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("limitfreechangefree", $from_id);
            break;
        case 5:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ محدودیت با موفقیت تنظیم شد.", $keyboardchangelimit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $value = json_decode($setting['limitnumber'], true);
            $value['free'] = intval($text);
            \Faoxima\Core\Database\Db::update("setting", "limitnumber", json_encode($value), null, null);
            break;
        case 6:
            \Faoxima\Telegram\Navigation\Steps::step('featnav_changeloc', $from_id);
            $keyboarddata = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "تایید و صفر شدن", 'callback_data' => 'reasetchangeloc'],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 با تأیید گزینه زیر، تمام تغییر لوکیشن هایی که توسط کاربر انجام شده است صفر خواهد شد. در صورت موافقت، روی گزینه زیر کلیک کنید.", $keyboarddata, 'HTML');
            break;
        case 7:
            \Faoxima\Core\Database\Db::update("user", "limitchangeloc", "0", null, null);
            \Faoxima\Telegram\Navigation\Steps::step('featnav_changeloc', $from_id);
            $keyboarddataDone = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "✅ تمامی محدودیت کاربران صفر شد.", $keyboarddataDone);
            break;
        case 8:
            $rwAmounts = \Faoxima\Wallet\Balance\ChargePrompts::rx_randomWalletAmounts();
            $rwRows = [];
            for ($rwI = 0; $rwI < 8; $rwI += 2) {
                $rwRows[] = [
                    ['text' => number_format((int)$rwAmounts[$rwI]) . " تومان", 'callback_data' => "randomwallet_slot_{$rwI}"],
                    ['text' => number_format((int)$rwAmounts[$rwI + 1]) . " تومان", 'callback_data' => "randomwallet_slot_" . ($rwI + 1)],
                ];
            }
            $rwRows[] = [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_shop']];
            $rwKeyboard = json_encode(['inline_keyboard' => $rwRows], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 روی هر دکمه بزنید تا مبلغ آن را تغییر دهید.", $rwKeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('featnav_randomwallet', $from_id);
            break;
        case 9:
            $rwSlotIndex = (int)$rwSlotMatch[1];
            $rwAmounts = \Faoxima\Wallet\Balance\ChargePrompts::rx_randomWalletAmounts();
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "randomwallet_slot", (string)$rwSlotIndex);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مبلغ جدید دکمه " . ($rwSlotIndex + 1) . " را به تومان ارسال کنید.\nمبلغ فعلی: " . number_format((int)$rwAmounts[$rwSlotIndex]), $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("get_randomwallet_slot", $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text) || (int)$text <= 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            $rwUserData = json_decode($user['Processing_value'], true);
            $rwSlotIndex = (int)($rwUserData['randomwallet_slot'] ?? 0);
            $rwAmounts = \Faoxima\Wallet\Balance\ChargePrompts::rx_randomWalletAmounts();
            $rwAmounts[$rwSlotIndex] = (int)$text;
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", json_encode(array_values($rwAmounts)), "NamePay", "randomwallet_amounts");
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت تنظیم شد.", $keyboardadmin, 'HTML');
            break;
        case 11:
            $id_user = $datagetr[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "id_user", $id_user);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 محدودیت جدیدی که میخواهید برای کاربر تنظیم کنید را ارسال کنید توجه داشته باشید این قابلیت تعداد تعییر لوکیشن انجام شده را تغییر میدهد", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getlimitchangenewbyuser", $from_id);
            break;
        case 12:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("user", "limitchangeloc", $text, "id", $userdate['id_user']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ تعداد استفاده کاربر با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
            break;
        case 13:
            $id_user = $datagetr[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "id_user", $id_user);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل هایی که می خواهید برای این نماینده نشان داده نشود از دکمه  زیر انتخاب نمایید بعد از انتخاب دستور /finish را ارسال کنید تا ذخیره شود.", $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpanelhidebotsaz", $from_id);
            break;
        case 14:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ ذخیره پنل ها با موفقیت انجام و پنل های برای کاربر مخفی شد.", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 15:
            if (!isset($update['message']) && empty($text)) { return; }
            $panelRow = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : null;
            $panelNameSave = is_array($panelRow) && !empty($panelRow['name_panel']) ? $panelRow['name_panel'] : $text;
            $userdata = json_decode($user['Processing_value'], true);
            $list_panel = json_decode(\Faoxima\Core\Database\Db::select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
            if (!is_array($list_panel)) { $list_panel = []; }
            if (in_array($panelNameSave, $list_panel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل از قبل اضافه شده است", null, 'HTML');
                return;
            }
            $list_panel[] = $panelNameSave;
            \Faoxima\Core\Database\Db::update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /finish را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
            break;
        case 16:
            $id_user = $datagetr[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "id_user", $id_user);
            $list_panel = json_decode(\Faoxima\Core\Database\Db::select("botsaz", "hide_panel", "id_user", $id_user, "select")['hide_panel'], true);
            $list_hide_panel = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            foreach ($list_panel as $panelname) {
                $list_hide_panel['keyboard'][] = [
                    ['text' => $panelname]
                ];
            }
            $list_hide_panel['keyboard'][] = [
                ['text' => $textbotlang['Admin']['backadmin']],
            ];
            $list_hide_panel = json_encode($list_hide_panel);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ از لیست زیر پنل هایی که میخواهید مجددا در ربات نماینده نشان داده شود را  انتخاب نمایید بعد از انتخاب تمامی پنل ها  دستور /remove را ارسال کنید تا ذخیره شود.", $list_hide_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getremovehidepanel", $from_id);
            break;
        case 17:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ نمایش پنل ها با موفقیت انجام و پنل های برای کاربر فعال شد.", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 18:
            $userdata = json_decode($user['Processing_value'], true);
            $list_panel = json_decode(\Faoxima\Core\Database\Db::select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
            if (!in_array($text, $list_panel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل در لیست وجود ندارد", null, 'HTML');
                return;
            }
            $count = 0;
            foreach ($list_panel as $panel) {
                if ($panel == $text) {
                    unset($list_panel[$count]);
                    break;
                }
                $count += 1;
            }
            $list_panel = array_values($list_panel);
            \Faoxima\Core\Database\Db::update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /remove را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
            break;
        case 19:
            $userslistData = '[]';
            if (is_file('cronbot/username.json')) {
                $fileContents = file_get_contents('cronbot/username.json');
                if ($fileContents !== false && $fileContents !== '') {
                    $userslistData = $fileContents;
                }
            }
            $userslist = json_decode($userslistData, true);
            if (is_array($userslist) && count($userslist) != 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ سیستم ارسال هدیه درحال انجام عملیات است پس از پایان و اطلاع رسانی  می توانید پیام جدید را ارسال نمایید.", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 برای سرویس های کدام پنل میخواهید حجم یا زمان هدیه دهید؟", $json_list_marzban_panel, "html");
            \Faoxima\Telegram\Navigation\Steps::step("getpanelgift", $from_id);
            break;
        case 20:
            if (!isset($update['message']) && empty($text)) { return; }
            $panelRow = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $text, "select");
            if (!$panelRow || !is_array($panelRow) || empty($panelRow['name_panel'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل وجود ندارد", null, "html");
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "name_panel", $panelRow['name_panel']);
            $keyboardstatistics = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔋 حجم", 'callback_data' => 'typegift_volume'],
                        ['text' => "⏳ زمان", 'callback_data' => 'typegift_day'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 یکی از هدیه های زیر را انتخاب نمایید.", $keyboardstatistics, "html");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 21:
            $typegift = $datagetr[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "typegift", $typegift);
            deletemessage($from_id, $message_id);
            if ($typegift == "volume") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 چند گیگ حجم می خواهید به سرویس های کاربر اضافه شود", $backadmin, "html");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 چند روز می خواهید به سرویس های کاربران اضافه شود", $backadmin, "html");
            }
            \Faoxima\Telegram\Navigation\Steps::step("getvaluegift", $from_id);
            break;
        case 22:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "value", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 متنی که می خواهید برای کاربر ارسال شود را ارسال کنید", $backadmin, "html");
            \Faoxima\Telegram\Navigation\Steps::step("gettextgift", $from_id);
            break;
        case 23:
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "text", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "id_admin", $from_id);
            $keyboardstatistics = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "✅ تایید و شروع فرآیند", 'callback_data' => 'startgift'],
                    ],
                    [
                        ['text' => "❌ لغو", 'callback_data' => 'cancel_gift'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 ادمین عزیز با تایید بر روی گزینه زیر فرآیند اعمال هدیه ها آغاز خواهد شد توجه داشته باشید با توجه به محدودیت ها اعمال هدیه زمان بر خواهد بود.", $keyboardstatistics, "html");
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        }

        return true;
    }
}
