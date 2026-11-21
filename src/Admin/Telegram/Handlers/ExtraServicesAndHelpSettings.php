<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $backuser, $departemanslist, $from_id, $keyboardadmin, $pdo, $setting, $supportcenter, $textbotlang, $user.
 * Inherited or exported by reference: $stmt, $text, $textcart.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ExtraServicesAndHelpSettings
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $backuser,
        $departemanslist,
        $from_id,
        $keyboardadmin,
        $pdo,
        $setting,
        &$stmt,
        $supportcenter,
        &$text,
        $textbotlang,
        &$textcart,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $agentst = ["n", "n2", "f"];
            $text = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agentst);
            if ($text === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'] ?? '{}', true);
            if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('mainvalume', $userdata)) {
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
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
            $eextraprice = json_decode($typepanel['mainvolume'], true);
            $eextraprice[$text] = $userdata['mainvalume'];
            $eextraprice = json_encode($eextraprice);
            \Faoxima\Core\Database\Db::update("marzban_panel", "mainvolume", $eextraprice, "name_panel", $userdata['namepanel']);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 2:
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداکثر حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetmaxeExtra', $from_id);
            break;
        case 3:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
                return;
            }
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "maxvolume", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('gettypeextramax', $from_id);
            break;
        case 4:
            $agentst = ["n", "n2", "f"];
            $text = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agentst);
            if ($text === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'] ?? '{}', true);
            if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('maxvolume', $userdata)) {
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
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
            $eextraprice = json_decode($typepanel['maxvolume'], true);
            $eextraprice[$text] = $userdata['maxvolume'];
            $eextraprice = json_encode($eextraprice);
            \Faoxima\Core\Database\Db::update("marzban_panel", "maxvolume", $eextraprice, "name_panel", $userdata['namepanel']);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 5:
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداقل زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('Getmaintime', $from_id);
            break;
        case 6:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
                return;
            }
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "maintime", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('gettypeextramaintime', $from_id);
            break;
        case 7:
            $agentst = ["n", "n2", "f"];
            $text = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agentst);
            if ($text === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'] ?? '{}', true);
            if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('maintime', $userdata)) {
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
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
            $eextraprice = json_decode($typepanel['maintime'], true);
            $eextraprice[$text] = $userdata['maintime'];
            $eextraprice = json_encode($eextraprice);
            \Faoxima\Core\Database\Db::update("marzban_panel", "maintime", $eextraprice, "name_panel", $userdata['namepanel']);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 8:
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداکثر زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('Getmaxtime', $from_id);
            break;
        case 9:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
                return;
            }
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "maxtime", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('gettypeextramaxtime', $from_id);
            break;
        case 10:
            $agentst = ["n", "n2", "f"];
            $text = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agentst);
            if ($text === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'] ?? '{}', true);
            if (!is_array($userdata) || empty($userdata['namepanel']) || !array_key_exists('maxtime', $userdata)) {
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
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
            $eextraprice = json_decode($typepanel['maxtime'], true);
            $eextraprice[$text] = $userdata['maxtime'];
            $eextraprice = json_encode($eextraprice);
            \Faoxima\Core\Database\Db::update("marzban_panel", "maxtime", $eextraprice, "name_panel", $userdata['namepanel']);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 ایدی عددی ادمینی که میخواهید پیام ها به آن ادمین ارسال شود را بفرستید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getidadmindep", $from_id);
            break;
        case 12:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata('clear', 'idadmin', $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام دپارتمان را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getdeparteman", $from_id);
            break;
        case 13:
            $userdata = json_decode($user['Processing_value'], true);
            $stmt = $pdo->prepare("INSERT IGNORE INTO departman (idsupport,name_departman) VALUES (:idsupport,:name_departman)");
            $stmt->bindParam(':idsupport', $userdata['idadmin']);
            $stmt->bindParam(':name_departman', $text);
            $stmt->execute();
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 دپارتمان با موفقیت اضافه گردید.", $supportcenter, 'HTML');
            break;
        case 14:
            $countdeparteman = \Faoxima\Core\Database\Db::select("departman", "*", null, null, "count");
            if ($countdeparteman == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ دپارتمانی برای حذف وجود ندارد.", $departemanslist, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نوع دپارتمان را برای حذف ارسال کنید.", $departemanslist, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getremovedep", $from_id);
            break;
        case 15:
            $stmt = $pdo->prepare("DELETE FROM departman WHERE name_departman = ?");
            $stmt->bindParam(1, $text);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 بخش مورد نظر حذف گردید.", $supportcenter, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 16:
        case 17:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این تنظیم مربوط به پنل Marzneshin است و دیگر در دسترس نیست.", $backadmin ?? null, 'HTML');
            return;
        case 18:
            $textcart = "📌 نام کاربری خود را بدون @ برای پشتیبانی  ارسال کنید\n\n{$setting['id_support']}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('idsupportset', $from_id);
            break;
        }

        return true;
    }
}
