<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $configedit, $dataget, $from_id, $json_list_marzban_panel, $keyboardadmin, $message_id, $optionManualsale, $pdo, $shopkeyboard, $textbotlang, $update, $user.
 * Inherited or exported by reference: $grp, $panel, $product, $products, $result, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AdditionalTimeAndGatewayBounds
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $configedit,
        $dataget,
        $from_id,
        &$grp,
        $json_list_marzban_panel,
        $keyboardadmin,
        $message_id,
        $optionManualsale,
        &$panel,
        $pdo,
        &$product,
        &$products,
        &$result,
        $shopkeyboard,
        &$stmt,
        &$text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 قیمت زمان اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetPricetimeextra', $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
                return;
            }
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "price", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(true), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('gettypeextratime', $from_id);
            break;
        case 3:

            if (!isset($update['message']) && empty($text)) {
                return;
            }

            $agentst = ["n", "n2", "f", "all"];
            $text = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agentst);


            if ($text === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(true), 'HTML');
                return;
            }

            $userdata = json_decode($user['Processing_value'], true);


            if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('price', $userdata)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ اطلاعات مرحله قبلی ناقص است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }

            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");


            if (!is_array($typepanel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }

            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
            $eextraprice = json_decode($typepanel['priceextratime'] ?? '{}', true);
            if (!is_array($eextraprice)) $eextraprice = [];


            if ($text == 'all') {
                $eextraprice["f"] = $userdata['price'];
                $eextraprice["n"] = $userdata['price'];
                $eextraprice["n2"] = $userdata['price'];
            } else {
                $eextraprice[$text] = $userdata['price'];
            }
            $eextraprice = json_encode($eextraprice);
            \Faoxima\Core\Database\Db::update("marzban_panel", "priceextratime", $eextraprice, "name_panel", $userdata['namepanel']);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 4:
            $paymentverify = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
            $keyboardverify = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $paymentverify, 'callback_data' => $paymentverify],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 با روشن کردن این قابلیت پس از اولین پرداخت کاربر درگاه کارت به کارت برای کاربر فعال می شود", $keyboardverify, 'HTML');
            break;
        case 5:
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "offpayverify", "NamePay", "checkpaycartfirst");
            $paymentverify = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
            $keyboardverify = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $paymentverify, 'callback_data' => $paymentverify],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "خاموش شد", $keyboardverify);
            break;
        case 6:
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "onpayverify", "NamePay", "checkpaycartfirst");
            $paymentverify = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
            $keyboardverify = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $paymentverify, 'callback_data' => $paymentverify],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "روشن شد", $keyboardverify);
            break;
        case 7:
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $listconfig = \Faoxima\Admin\Telegram\Support\SettingsFunctionsHelpers::rxAdminManualConfigNames($pdo, $panel);
            $json_list_manualconfig_list = \Faoxima\Admin\Telegram\Support\SettingsFunctionsHelpers::rxAdminManualConfigKeyboard($listconfig);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام کانفیگی که میخواهید ویرایش نمایید را ارسال کنید ", $json_list_manualconfig_list, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getnameedit", $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "یکی از گزینه های زیر را انتخاب کنید ", $configedit, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $text, "id", $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "محتوا جدید کانفیگ را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcontentedit", $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ ذخیره گردید.", $optionManualsale, 'HTML');
            \Faoxima\Core\Database\Db::update("manualsell", "contentrecord", $text, "namerecord", $user['Processing_value_one']);
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید افزایش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getaddpricepeoductloc", $from_id);
            break;
        case 12:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود؟
یکی از گزینه‌های زیر را انتخاب یا ارسال کنید:
👤 کاربر عادی (f)
🤝 نماینده عادی (n)
💎 نماینده پیشرفته (n2)", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $text);
            \Faoxima\Telegram\Navigation\Steps::step("getagentaddpriceproduct", $from_id);
            break;
        case 13:
            $grp = method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_resolveAgentGroupFromReplyButton') ? \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, ['f', 'n', 'n2']) : (in_array($text, ['f', 'n', 'n2'], true) ? $text : null);
            if ($grp === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
                return;
            }
            $text = $grp;
            $keyboard_type_price = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "درصدی", 'callback_data' => 'typeaddprice_percent'],
                        ['text' => "ثابت", 'callback_data' => 'typeaddprice_static'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مبلغ به صورت درصدی اضافه شود یا مبلغ ثابت", $keyboard_type_price, 'HTML');
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "agent", $text);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 14:
            $type = $dataget[1];
            deletemessage($from_id, $message_id);
            if ($type == "static") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 درصدی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "type_price", $type);
            \Faoxima\Telegram\Navigation\Steps::step("getaddpricepeoduct", $from_id);
            break;
        case 15:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $stmt = $pdo->prepare("SELECT * FROM product WHERE FIND_IN_SET(:loc, Location) > 0 AND agent = :agent");
            $stmt->execute([':loc' => (string) $userdata['namepanel'], ':agent' => (string) $userdata['agent']]);
            $product = $stmt->fetchAll();
            if ($product == false) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محصولی برای تغییر قیمت یافت نشد", $shopkeyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            if ($userdata['type_price'] == "static") {
                $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + :price WHERE FIND_IN_SET(:loc, Location) > 0 AND agent = :agent");
                $stmt->bindParam(':price', $text, PDO::PARAM_STR);
            } else {
                $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + (price_product * :price / 100)  WHERE FIND_IN_SET(:loc, Location) > 0 AND agent = :agent");
                $stmt->bindParam(':price', $text, PDO::PARAM_STR);
            }
            $rxBulkLoc = (string) $userdata['namepanel'];
            $rxBulkAgent = (string) $userdata['agent'];
            $stmt->bindParam(':loc', $rxBulkLoc, PDO::PARAM_STR);
            $stmt->bindParam(':agent', $rxBulkAgent, PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Core\Database\SelectCache::clearSelectCache('product');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت برای تمامی محصولات اعمال شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 16:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید کاهش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getlowpricepeoductloc", $from_id);
            break;
        case 17:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود؟
یکی از گزینه‌های زیر را انتخاب یا ارسال کنید:
👤 کاربر عادی (f)
🤝 نماینده عادی (n)
💎 نماینده پیشرفته (n2)", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $text);
            \Faoxima\Telegram\Navigation\Steps::step("getkampricepeoductloc", $from_id);
            break;
        case 18:
            $grp = method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_resolveAgentGroupFromReplyButton') ? \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, ['f', 'n', 'n2']) : (in_array($text, ['f', 'n', 'n2'], true) ? $text : null);
            if ($grp === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "agent", $grp);
            \Faoxima\Telegram\Navigation\Steps::step("getkampricepeoduct", $from_id);
            break;
        case 19:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $stmt = $pdo->prepare("SELECT * FROM product WHERE FIND_IN_SET(:loc, Location) > 0 AND agent = :agent");
            $stmt->execute([':loc' => (string) $userdata['namepanel'], ':agent' => (string) $userdata['agent']]);
            $product = $stmt->fetchAll();
            if ($product == false) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محصولی برای تغییر قیمت یافت نشد", $shopkeyboard, 'HTML');
                return;
            }
            foreach ($product as $products) {
                $result = $products['price_product'] - intval($text);
                \Faoxima\Core\Database\Db::update("product", "price_product", round($result), "code_product", $products['code_product']);
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت برای تمامی محصولات اعمال شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        }

        return true;
    }
}
