<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $from_id, $keyboardadmin, $optionManualsale, $pdo, $textbotlang, $update, $user.
 * Inherited or exported by reference: $button, $json_list_product_list_admin, $panel, $product, $row, $stmt, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class NetworkAndServiceSettings
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        &$button,
        $from_id,
        &$json_list_product_list_admin,
        $keyboardadmin,
        $optionManualsale,
        &$panel,
        $pdo,
        &$product,
        &$row,
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
            $panel = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelFromUserState']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelFromUserState($user) : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (!$panel) { \Faoxima\Telegram\Support\Replies::nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML'); \Faoxima\Telegram\Navigation\Steps::step('home', $from_id); return; }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "namepanel", $panel['name_panel']);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "code_panel", $panel['code_panel']);
            $newStatus = (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel)) ? 'off_national_net' : 'on_national_net';
            \Faoxima\Core\Database\Db::update("marzban_panel", "national_net_status", $newStatus, "code_panel", $panel['code_panel']);
            if ($newStatus === 'on_national_net' && empty($panel['stock_source_panel'])) {
                \Faoxima\Core\Database\Db::update("marzban_panel", "stock_source_panel", $panel['code_panel'], "code_panel", $panel['code_panel']);
            }
            if ($newStatus === 'on_national_net') {
                \Faoxima\Telegram\Support\Replies::nm_replyOrEdit($from_id, "✅ وضعیت نت ملی برای این پنل روشن شد.\n\n📦 حالا کانفیگ‌های انبار و بقیه تنظیمات (افزودن پلن و ...) را از «پنل وب» انجام دهید.\n\nℹ️ از این پس خرید کاربر از این پنل، از انبار تحویل داده می‌شود.", $keyboardadmin, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_replyOrEdit($from_id, "❌ وضعیت نت ملی برای این پنل خاموش شد. خرید دوباره مستقیم روی پنل انجام می‌شود.", $keyboardadmin, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 2:
            $panel = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelFromUserState']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelFromUserState($user) : null;
            if (!$panel) {
                \Faoxima\Telegram\Support\Replies::nm_replyOrEdit($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "code_panel", $panel['code_panel']);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "old_type", $panel['type']);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "name_panel", $panel['name_panel']);
            \Faoxima\Telegram\Support\Replies::nm_replyOrEdit($from_id, \Faoxima\Services\Panels\PanelSetup::rxAddPanelCategoryMessage('root'), \Faoxima\Services\Panels\PanelSetup::rxSwitchTypeKeyboard('root'), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("switchtype_pick", $from_id);
            break;
        case 3:
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 برای اضافه کردن کانفیگ ابتدا یک نام ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getnameconfigm', $from_id);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            $exitsname = \Faoxima\Core\Database\Db::select("manualsell", "*", "namerecord", $text, "count");
            if (intval($exitsname) != 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "این نام وجود دارد", null, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $product = [];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "namerecord", $text);
            $stmt = $pdo->prepare("SELECT * FROM product WHERE FIND_IN_SET(:text, Location) > 0 or Location = '/all' ");
            $stmt->bindParam(':text', $userdata['namepanel'], PDO::PARAM_STR);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $product[] = [$row['name_product']];
            }
            $list_product = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            $list_product['keyboard'][] = [
                ['text' => "🏠 بازگشت به منوی مدیریت"],
            ];
            foreach ($product as $button) {
                $list_product['keyboard'][] = [
                    ['text' => $button[0]]
                ];
            }
            $json_list_product_list_admin = json_encode($list_product);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام محصول خود را ارسال نمایید در صورتی که میخواهید  برای اکانت تست تنظیم کنید متن تست را ارسال کنید.", $json_list_product_list_admin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getnameproduct', $from_id);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "namerecord", $text);
            break;
        case 5:
            if (!isset($update['message']) && empty($text)) { return; }
            if ($text != "تست") {
                $product = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $text, "select");
                if ($product == false) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "محصول در ربات وجود ندارد", $backadmin, 'HTML');
                    return;
                }
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "codeproduct", $product['code_product']);
            } else {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "codeproduct", "usertest");
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 کانفیگ یا متن دیگر خود را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getconfigtext', $from_id);
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ کانفیگ با موفقیت ذخیره گردید.", $optionManualsale, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $userdata = json_decode($user['Processing_value'], true);
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
            \Faoxima\Admin\Telegram\Support\SettingsFunctionsHelpers::rxAdminManualConfigInsert($pdo, $panel, $userdata, $text);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
            break;
        case 7:
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $listconfig = \Faoxima\Admin\Telegram\Support\SettingsFunctionsHelpers::rxAdminManualConfigNames($pdo, $panel);
            $json_list_manualconfig_list = \Faoxima\Admin\Telegram\Support\SettingsFunctionsHelpers::rxAdminManualConfigKeyboard($listconfig);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام کانفیگی که میخواهید حذف نمایید را ارسال کنید ", $json_list_manualconfig_list, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getnameremove", $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ کانفیگ با موفقیت حذف گردید.", $optionManualsale, 'HTML');
            $userdata = json_decode($user['Processing_value'], true);
            $panelName = is_array($userdata) && isset($userdata['namepanel']) ? $userdata['namepanel'] : $user['Processing_value'];
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select");
            if ($panel) {
                $stmt = $pdo->prepare("DELETE FROM manualsell WHERE namerecord = ? AND codepanel = ?");
                $stmt->bindParam(1, $text);
                $stmt->bindParam(2, $panel['code_panel']);
                $stmt->execute();
            }
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 قیمت تغییر لوکیشن از سایر پنل‌ها به این پنل را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('setpricechangelocation', $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (!is_array($typepanel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب‌شده پیدا نشد. لطفاً دوباره پنل را انتخاب کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], "📌قیمت تغییر لوکیشن با موفقیت تغییر کرد");
            \Faoxima\Core\Database\Db::update("marzban_panel", "priceChangeloc", $text, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 11:
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 قیمت حجم اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetPriceExtra', $from_id);
            break;
        case 12:
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
            \Faoxima\Telegram\Navigation\Steps::step('gettypeextra', $from_id);
            break;
        case 13:
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
            $eextraprice = json_decode($typepanel['priceextravolume'] ?? '{}', true);
            if (!is_array($eextraprice)) $eextraprice = [];
            if ($text == 'all') {
                $eextraprice["f"] = $userdata['price'];
                $eextraprice["n"] = $userdata['price'];
                $eextraprice["n2"] = $userdata['price'];
            } else {
                $eextraprice[$text] = $userdata['price'];
            }
            $eextraprice = json_encode($eextraprice);
            \Faoxima\Core\Database\Db::update("marzban_panel", "priceextravolume", $eextraprice, "name_panel", $userdata['namepanel']);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 14:
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 قیمت حجم اضافه دلخواه این پنل را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetPricecustomvo', $from_id);
            break;
        case 15:
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
            \Faoxima\Telegram\Navigation\Steps::step('gettypeextracustom', $from_id);
            break;
        case 16:
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
            $eextraprice = json_decode($typepanel['pricecustomvolume'] ?? '{}', true);
            if (!is_array($eextraprice)) $eextraprice = [];
            if ($text == 'all') {
                $eextraprice["f"] = $userdata['price'];
                $eextraprice["n"] = $userdata['price'];
                $eextraprice["n2"] = $userdata['price'];
            } else {
                $eextraprice[$text] = $userdata['price'];
            }
            $eextraprice = json_encode($eextraprice);
            \Faoxima\Core\Database\Db::update("marzban_panel", "pricecustomvolume", $eextraprice, "name_panel", $userdata['namepanel']);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        }

        return true;
    }
}
