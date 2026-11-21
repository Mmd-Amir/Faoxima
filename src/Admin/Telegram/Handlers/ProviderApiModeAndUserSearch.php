<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $adminPanelsMenu, $backadmin, $connect, $dataget, $datagetr, $datain, $from_id, $keyboardadmin, $message_id, $pdo, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $next_page, $page, $result, $row, $rows.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderApiModeAndUserSearch
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $adminPanelsMenu,
        $backadmin,
        $connect,
        $dataget,
        $datagetr,
        $datain,
        $from_id,
        $keyboardadmin,
        $message_id,
        &$next_page,
        &$page,
        $pdo,
        &$result,
        &$row,
        &$rows,
        $text,
        $textbotlang,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $xuiEditMode = $dataget[1];
            if ($xuiEditMode === 'token') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuiapitoken'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('edit_xui_api_token', $from_id);
            } else {
                \Faoxima\Core\Database\Db::update("marzban_panel", "xui_api_mode", "legacy", "name_panel", $user['Processing_value']);
                \Faoxima\Services\Panels\PanelSetup::outtypepanel('x-ui_single', $textbotlang['Admin']['managepanel']['saveddata']);
            }
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            $xuiEditToken = trim($text);
            if ($xuiEditToken === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidxuiapitoken'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "xui_api_token", $xuiEditToken, "name_panel", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "xui_api_mode", "token", "name_panel", $user['Processing_value']);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel('x-ui_single', $textbotlang['Admin']['managepanel']['saveddata']);
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpasswordnew'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetpaawordNew', $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedpasswordPanel']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "password_panel", $text, "name_panel", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "در صورت تایید کلمه زیر را ارسال کنید.
<code>تایید</code>", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('confirmremovepanel', $from_id);
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            if ($text == "تایید") {
                $rxPanelsHubKb = isset($adminPanelsMenu) ? $adminPanelsMenu : $keyboardadmin;
                $marzban = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
                if (!\Faoxima\Orders\Catalog\NameCascades::rxPanelNameCascade($pdo, (string) $user['Processing_value'], null)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['error'], $rxPanelsHubKb, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['RemovedPanel'], $rxPanelsHubKb, 'HTML');
                \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 7:
            $keyboardtypelistuser = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "کاربران دارای موجودی", 'callback_data' => "balanceuserlist"],
                    ],
                    [
                        ['text' => "کاربران دارای زیرمجموعه", 'callback_data' => "listrefral"],
                    ],
                    [
                        ['text' => "کاربران شماره کارت فعال", 'callback_data' => "cartuserlist"],
                    ],
                    [
                        ['text' => "کاربران با موجودی منفی", 'callback_data' => "zerobalance"],
                    ],
                    [
                        ['text' => "کاربران دارای اشتراک فعال", 'callback_data' => "activesubuserlist"],
                    ],
                    [
                        ['text' => "لیست نمایندگان", 'callback_data' => "agentlistusers"],
                        ['text' => "لیست کل کاربران", 'callback_data' => "alllistusers"],
                    ],
                    [
                        ['text' => "🛍 جستجو سفارش", 'callback_data' => "searchorder"],
                        ['text' => "👥 شارژ همگانی", 'callback_data' => "balanceaddall"],
                    ],
                    [
                        ['text' => "🔍 جستجو کاربر", 'callback_data' => "searchuser"],
                        ['text' => "📨 بخش ارسال پیام", 'callback_data' => "systemsms"],
                    ],
                    [
                        ['text' => "🔋 حجم یا زمان همگانی", 'callback_data' => "voloume_or_day_all"],
                    ],
                    [
                        ['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => "admin_usershub"],
                        ['text' => "🏠 منوی مدیریت", 'callback_data' => "adm_hub_main"],
                    ]
                ]
            ]);
            $text_list_users = "📌 از لیست زیر یک گزینه را انتخاب نمایید";
            if ($datain == "backlistuser") {
                Editmessagetext($from_id, $message_id, $text_list_users, $keyboardtypelistuser);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_list_users, $keyboardtypelistuser, 'html');
            }
            break;
        case 8:
            \Faoxima\Core\Database\Db::update("user", "pagenumber", "1", "id", $from_id);
            $page = 1;
            $items_per_page = 10;
            $start_index = ($page - 1) * $items_per_page;
            $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuser', 'previous_pageuser', true, true);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 9:
            $numpage = \Faoxima\Core\Database\Db::select("user", "*", null, null, "count");
            $page = $user['pagenumber'];
            $items_per_page = 10;
            $sum = $user['pagenumber'] * $items_per_page;
            if ($sum > $numpage) {
                $next_page = 1;
            } else {
                $next_page = $page + 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuser', 'previous_pageuser', true, true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 10:
            $page = $user['pagenumber'];
            $items_per_page = 10;
            if ($user['pagenumber'] <= 1) {
                $next_page = 1;
            } else {
                $next_page = $page - 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, 'next_pageuser', 'previous_pageuser', true, true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 11:
            deletemessage($from_id, $message_id);
            break;
        case 12:
            $keyboardtypelistuser = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "n", 'callback_data' => "agenttypshowlist_n"],
                        ['text' => "n2", 'callback_data' => "agenttypshowlist_n2"],
                    ],
                    [
                        ['text' => "تمام نمایندگان", 'callback_data' => "agenttypshowlist_all"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, "📌 کدام گروه از نمایندگان می خواهید مشاهده کنید ؟", $keyboardtypelistuser);
            break;
        case 13:
            $typeagent = $datagetr[1];
            \Faoxima\Core\Database\Db::update("user", "pagenumber", "1", "id", $from_id);
            $page = 1;
            $items_per_page = 10;
            $start_index = ($page - 1) * $items_per_page;
            if ($typeagent == "all") {
                $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE agent != 'f'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            } else {
                $_s=(int)$start_index; $_p=(int)$items_per_page;
                $_stmt = $connect->prepare("SELECT * FROM user WHERE agent = ? LIMIT ?, ?");
                $_stmt->bind_param("sii", $typeagent, $_s, $_p);
                $_stmt->execute();
                $result = $_stmt->get_result();
                $_stmt->close();
            }
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, "next_pageuseragent_$typeagent", null, true);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 14:
            $typeagent = $datagetr[1];
            $numpage = \Faoxima\Core\Database\Db::select("user", "*", null, null, "count");
            $page = $user['pagenumber'];
            $items_per_page = 10;
            $sum = $user['pagenumber'] * $items_per_page;
            if ($sum > $numpage) {
                $next_page = 1;
            } else {
                $next_page = $page + 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            if ($typeagent == "all") {
                $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE agent != 'f'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            } else {
                $_s=(int)$start_index; $_p=(int)$items_per_page;
                $_stmt = $connect->prepare("SELECT * FROM user WHERE agent = ? LIMIT ?, ?");
                $_stmt->bind_param("sii", $typeagent, $_s, $_p);
                $_stmt->execute();
                $result = $_stmt->get_result();
                $_stmt->close();
            }
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, "next_pageuseragent_$typeagent", "previous_pageuseragent_$typeagent", true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 15:
            $typeagent = $datagetr[1];
            $page = $user['pagenumber'];
            $items_per_page = 10;
            if ($user['pagenumber'] <= 1) {
                $next_page = 1;
            } else {
                $next_page = $page - 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            if ($typeagent == "all") {
                $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE agent != 'f'  LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
            } else {
                $_s=(int)$start_index; $_p=(int)$items_per_page;
                $_stmt = $connect->prepare("SELECT * FROM user WHERE agent = ? LIMIT ?, ?");
                $_stmt->bind_param("sii", $typeagent, $_s, $_p);
                $_stmt->execute();
                $result = $_stmt->get_result();
                $_stmt->close();
            }
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            $keyboard_json = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildUserListKeyboard($rows, $textbotlang, "next_pageuseragent_$typeagent", "previous_pageuseragent_$typeagent", true);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        }

        return true;
    }
}
