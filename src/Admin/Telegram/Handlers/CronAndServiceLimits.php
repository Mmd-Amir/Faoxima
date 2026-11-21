<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminAction, $adminExecution, $adminnumber, $backadmin, $buyreport, $dataget, $datain, $errorreport, $from_id, $json_list_marzban_panel, $keyboardlistsnode, $message_id, $optionGuard, $optionJovad, $optionManualsale, $optionPasarGuard, $optionRebecca, $option_remnawave, $optionathmarzban, $optionathx_ui, $pdo, $setting, $setting_panel, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $dataoutput, $datatextbot, $expire, $panel, $randomString, $result, $sumvolume, $text, $usernameconfig.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CronAndServiceLimits
{
    public static function handle(
        $ManagePanel,
        $adminAction,
        $adminExecution,
        $adminnumber,
        $backadmin,
        $buyreport,
        $dataget,
        $datain,
        &$dataoutput,
        &$datatextbot,
        $errorreport,
        &$expire,
        $from_id,
        $json_list_marzban_panel,
        $keyboardlistsnode,
        $message_id,
        $optionGuard,
        $optionJovad,
        $optionManualsale,
        $optionPasarGuard,
        $optionRebecca,
        $option_remnawave,
        $optionathmarzban,
        $optionathx_ui,
        &$panel,
        $pdo,
        &$randomString,
        &$result,
        $setting,
        $setting_panel,
        &$sumvolume,
        &$text,
        $textbotlang,
        $update,
        $user,
        $username,
        &$usernameconfig
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تنظیم کنید که اگر حجم کاربر به x رسید پیام اخطار ارسال شود. حجم را براساس گیگ ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getvolumewarn", $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
                return;
            }
            \Faoxima\Core\Database\Db::update("setting", "volumewarn", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ تغییرات با موفقیت ذخیره شد", $setting_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 3:
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "idpanel", $user['Processing_value']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌در این بخش میتوانید یک سفارش را بطور دستی ایجاد و دریافت کنید
⚠️ در صورتی که می خواهید  کانفیگ به حساب کاربر اضافه شود و کاربر مدیریت کند باید از گزینه افزودن سفارش  استفاده نمایید.
- برای اضافه کردن کانفیگ ابتدا نام کاربری را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getusernameconfigcr', $from_id);
            break;
        case 4:
            $text = str_replace('_', '-', $text);
            if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['invalidusername'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $text, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('getcountcreate', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 تعداد کانفیگی که میخواهید ساخته شود را ارسال کنید حداکثر ۱۰ تا می توانید ارسال کنید", $backadmin, 'HTML');
            break;
        case 5:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            if (intval($text) > 10 or intval($text) < 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ حداقل ۱ عدد و حداکثر می توانید ۱۰ عدد ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "count", $text);
            \Faoxima\Telegram\Navigation\Steps::step('getvolumesconfig', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حجم مصرفی اکانت را ارسال نمایید . حجم براساس گیگابایت است.", $backadmin, 'HTML');
            break;
        case 6:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", $text, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 زمان سرویس را ارسال نمایید زمان براساس روز است.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("gettimeaccount", $from_id);
            break;
        case 7:
            $userdata = json_decode($user['Processing_value'], true);
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مقدار نامعتبر", null, 'html');
                return;
            }
            if (intval($text) == 0) {
                $expire = 0;
            } else {
                $datetimestep = strtotime("+" . $text . "days");
                $expire = strtotime(date("Y-m-d H:i:s", $datetimestep));
            }
            $datac = array(
                'expire' => $expire,
                'data_limit' => $user['Processing_value_tow'] * pow(1024, 3),
                'from_id' => $from_id,
                'username' => "$username",
                'type' => "new by admin $from_id"
            );
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdata['idpanel'], "select");
            for ($i = 0; $i < $userdata['count']; $i++) {
                $usernameconfig = str_replace('_', '-', $user['Processing_value_one']) . "-" . $i;
                $dataoutput = $ManagePanel->createUser($userdata['idpanel'], "usertest", $usernameconfig, $datac, true);
                if ($dataoutput['username'] == null) {
                    $dataoutput['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['sell']['ErrorConfig'], null, 'HTML');
                    $texterros = "
⭕️ یک کاربر قصد دریافت اکانت داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
<blockquote>✍️ دلیل خطا : {$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$panel['name_panel']}</blockquote>";
                    if (strlen($setting['Channel_Report']) > 0) {
                        telegram('sendmessage', [
                            'chat_id' => $setting['Channel_Report'],
                            'message_thread_id' => $errorreport,
                            'text' => $texterros,
                            'parse_mode' => "HTML"
                        ]);
                        \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                    }
                    return;
                }
                $randomString = bin2hex(random_bytes(5));
                $output_config_link = $panel['sublink'] == "onsublink" ? \Faoxima\Services\Delivery\ServiceDelivery::rxResolveConnectionLink($panel, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
                $config = "";
                if ($panel['config'] == "onconfig" && is_array($dataoutput['configs'])) {
                    $rxAdminTextConfigs = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'rxDeliveryTextConfigs') ? \Faoxima\Services\Panels\Drivers\JovadPanel::rxDeliveryTextConfigs($dataoutput['configs']) : $dataoutput['configs'];
                    foreach ($rxAdminTextConfigs as $link) {
                        $config .= "\n" . $link;
                    }
                }
                $datatextbot['textafterpay'] = $panel['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
                $datatextbot['textafterpay'] = $panel['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
                if (intval($text) == 0)
                    $text = $textbotlang['users']['stateus']['Unlimited'];
                $textcreatuser = str_replace('{username}', "<code>" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($dataoutput['username'], $panel) . "</code>", $datatextbot['textafterpay']);
                $textcreatuser = str_replace('{name_service}', "پلن دلخواه", $textcreatuser);
                $textcreatuser = str_replace('{location}', $panel['name_panel'], $textcreatuser);
                $textcreatuser = str_replace('{day}', $text, $textcreatuser);
                $textcreatuser = str_replace('{volume}', $user['Processing_value_tow'], $textcreatuser);
                $textcreatuser = \Faoxima\Core\Database\Db::applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
                if ($panel['type'] == "Manualsale") {
                    $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
                    \Faoxima\Core\Database\Db::update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
                }
                \Faoxima\Services\Delivery\ServiceDelivery::sendMessageService($panel, $dataoutput['configs'], $output_config_link, $dataoutput['username'], null, $textcreatuser, $randomString);
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
            $text_report = "";
            if (strlen($setting['Channel_Report']) > 0) {
                $text_report = " 🛍 ساخت کانفیگ توسط ادمین

<blockquote>نام کاربری کانفیگ : {$user['Processing_value_one']}</blockquote>
<blockquote>حجم کانفیگ  : {$user['Processing_value_tow']} گیگ</blockquote>
<blockquote>زمان کانفیگ : $text روز</blockquote>
<blockquote>آیدی عددی ادمین : $from_id</blockquote>
<blockquote>نام کاربری ادمین : $username</blockquote>
<blockquote>تعداد ساخت : {$userdata['count']}</blockquote>";
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $buyreport,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $userdata['idpanel'], "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🪚 برای استفاده از این قابلیت یکی از پنل های زیر را انتخاب نمایید", $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlocoption', $from_id);
            break;
        case 9:
            $panelRow = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $text, "select");
            if (is_array($panelRow) && !empty($panelRow)) {
                $canonicalName = $panelRow['name_panel'];
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $canonicalName, "id", $from_id);
                $typepanel = $panelRow['type'] ?? '';
                if ($typepanel == "marzban") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
                } elseif ($typepanel == "x-ui_single") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
                } elseif ($typepanel == "WGDashboard") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
                } elseif ($typepanel == "remnawave") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $option_remnawave, 'HTML');
                } elseif ($typepanel == "rebecca") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionRebecca, 'HTML');
                } elseif ($typepanel == "guard") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionGuard, 'HTML');
                } elseif ($typepanel == "pasarguard") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionPasarGuard, 'HTML');
                } elseif ($typepanel == "jovadpanel") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionJovad, 'HTML');
                } elseif ($typepanel == "Manualsale") {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionManualsale, 'HTML');
                }
            }
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 10:
            if ($adminnumber != $from_id) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این بخش فقط در دسترس ادمین اصلی است", null, 'HTML');
                return;
            }
            $nodes = \Faoxima\Services\Panels\Drivers\Marzban::Get_Nodes($user['Processing_value']);
            if (!empty($nodes['error'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $nodes['error'], null, 'HTML');
                return;
            }
            if (!empty($nodes['status']) && $nodes['status'] != 200) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$nodes['status']}", null, 'HTML');
                return;
            }
            $nodes = json_decode($nodes['body'], true);
            if (count($nodes) == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌  امکان مشاهده تنظیمات نود ها وجود ندارد", null, 'HTML');
                return;
            }
            $keyboardlistsnode['inline_keyboard'][] = [
                ['text' => "عملیات", 'callback_data' => "actionnode"],
                ['text' => "نام", 'callback_data' => "namenode"]
            ];
            foreach ($nodes as $result) {
                if (!isset($result['id']))
                    continue;
                $keyboardlistsnode['inline_keyboard'][] = [
                    ['text' => "مدیریت", 'callback_data' => "node_{$result['id']}"],
                    ['text' => $result['name'], 'callback_data' => "node_{$result['id']}"],
                ];
            }
            $keyboardlistsnode = json_encode($keyboardlistsnode);
            if ($datain == "bakcnode") {
                Editmessagetext($from_id, $message_id, "📌 در این بخش می توانید نود های پنل مرزبان مدیریت کنید.", $keyboardlistsnode);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید نود های پنل مرزبان مدیریت کنید.", $keyboardlistsnode, 'HTML');
            }
            break;
        case 11:
            $nodeid = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $nodeid, "id", $from_id);
            $node = \Faoxima\Services\Panels\Drivers\Marzban::Get_Node($user['Processing_value'], $nodeid);
            if (!empty($node['error'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $node['error'], null, 'HTML');
                return;
            }
            if (!empty($node['status']) && $node['status'] != 200) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$node['status']}", null, 'HTML');
                return;
            }
            $nodeusage = \Faoxima\Services\Panels\Drivers\Marzban::Get_usage_Nodes($user['Processing_value']);
            if (!empty($nodeusage['error'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $nodeusage['error'], null, 'HTML');
                return;
            }
            if (!empty($nodeusage['status']) && $nodeusage['status'] != 200) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$nodeusage['status']}", null, 'HTML');
                return;
            }
            $node = json_decode($node['body'], true);
            $nodeusage = json_decode($nodeusage['body'], true);
            foreach ($nodeusage['usages'] as $nodeusages) {
                if ($nodeusages['node_id'] == $nodeid) {
                    $nodeusage = $nodeusages;
                    break;
                }
            }
            $sumvolume = \Faoxima\Core\Support\Formatting::formatBytes($nodeusage['downlink'] + $nodeusage['uplink']);
            $textnode = "📌 اطلاعات نود

🖥 نام نود :  {$node['name']}
🌍 آیپی نود : {$node['address']}
🔻 پورت نود : {$node['port']}
🔺 پورت api نود : {$node['api_port']}
🔋جمع مصرف نود  : $sumvolume
🔄 ضریب مصرف نود : {$node['usage_coefficient']}
🔵 نسخه xray نود : {$node['xray_version']}
🟢 وضعیت نود : {$node['status']}
    ";
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🗂 نام نود", 'callback_data' => "changenamenode"],
                        ['text' => "🔄 ضریب مصرف نود", 'callback_data' => "changecoefficient"],
                    ],
                    [
                        ['text' => "🌍 آدرس IP نود", 'callback_data' => "changeipnode"],
                        ['text' => "♻️ اتصال مجدد", 'callback_data' => "reconnectnode"],
                    ],
                    [
                        ['text' => "❌ حذف نود", 'callback_data' => "removenode"],
                    ],
                    [
                        ['text' => "🔙 بازگشت به لیست نود ها", 'callback_data' => "bakcnode"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
            break;
        }

        return true;
    }
}
