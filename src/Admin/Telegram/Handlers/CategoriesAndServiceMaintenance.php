<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $connect, $dataget, $domainhosts, $from_id, $json_list_marzban_panel, $keyboard_Category_manage, $keyboard_shop_manage, $message_id, $pdo, $shopkeyboard, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $id_user, $next_page, $page, $result, $row.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CategoriesAndServiceMaintenance
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $connect,
        $dataget,
        $domainhosts,
        $from_id,
        &$id_user,
        $json_list_marzban_panel,
        $keyboard_Category_manage,
        $keyboard_shop_manage,
        $message_id,
        &$next_page,
        &$page,
        $pdo,
        &$result,
        &$row,
        $shopkeyboard,
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
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            if (!\Faoxima\Orders\Catalog\NameCascades::rxCategoryDeleteGuarded($pdo, (string) $text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['error'], $shopkeyboard, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ دسته بندی با موفقیت حذف گردید.", $shopkeyboard, 'HTML');
            break;
        case 2:
            if ($user['Processing_value_one'] != "/all") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 این قابلیت فقط زمانی کاربرد دارد که شما لوکیشن محصول را /all تعریف کرده باشید.", null, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در صورتی که لوکیشن پنل را /all انتخاب کرده باشید اما نیاز داشته باشید که یک پنل را نشان ندهید از این قابلیت می توانید استفاده نمایید

جهت مخفی کردن پنل  از لیست زیر پنل های خود را اتنخاب کنید سپس دستور /end_hide را ارسال نمایید.", $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlistpanel', $from_id);
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ ذخیره پنل ها با موفقیت انجام و پنل ها برای محصول انتخابی مخفی شد.", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            $panelRow = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : null;
            $panelNameSave = is_array($panelRow) && !empty($panelRow['name_panel']) ? $panelRow['name_panel'] : $text;
            $list_panel = json_decode(\Faoxima\Core\Database\Db::select("product", "hide_panel", "id", $user['Processing_value'], "select")['hide_panel'], true);
            if (!is_array($list_panel)) { $list_panel = []; }
            if (in_array($panelNameSave, $list_panel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل از قبل اضافه شده است", null, 'HTML');
                return;
            }
            $list_panel[] = $panelNameSave;
            \Faoxima\Core\Database\Db::update("product", "hide_panel", json_encode($list_panel), "id", $user['Processing_value']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /end_hide را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
            break;
        case 5:
            \Faoxima\Core\Database\Db::update("product", "hide_panel", "{}", "name_product", $user['Processing_value']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ تمامی پنل های مخفی حذف شدند", null, 'HTML');
            break;
        case 6:
            $bots_agent = \Faoxima\Core\Database\Db::select("botsaz", "*", null, null, "fetchAll");
            if (count($bots_agent) == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ رباتی وجود ندارد", null, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در انجام وبهوک ...", null, 'HTML');
            foreach ($bots_agent as $bot) {
                telegram('setWebhook', ['url' => "https://$domainhosts/vpnbot/{$bot['id_user']}{$bot['username']}/index.php"], $bot['bot_token']);
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ وبهوک با موفقیت انجام شد.", null, 'HTML');
            break;
        case 7:
            $id_user = $dataget[1];
            $user_status = \Faoxima\Core\Database\Db::select("user", "*", "id", $id_user);
            if (intval($user_status['status_cron']) == 0) {
                \Faoxima\Core\Database\Db::update("user", "status_cron", "1", "id", $id_user);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ اطلاعیه های کرون برای کاربر فعال گردید.", null, 'HTML');
            } else {
                \Faoxima\Core\Database\Db::update("user", "status_cron", "0", "id", $id_user);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ اطلاعیه های کرون برای کاربر غیرفعال گردید.", null, 'HTML');
            }
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_Category_manage, 'HTML');
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_shop_manage, 'HTML');
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 دسته بندی خود را جهت ویرایش انتخاب کنید", \Faoxima\Telegram\Keyboard\AdminCategoryFunctions::KeyboardCategoryadmin(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("editcategory_name", $from_id);
            break;
        case 12:
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "category", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌  نام جدید دسته بندی را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("get_name_new_category", $from_id);
            break;
        case 13:
            $userdata = json_decode($user['Processing_value'], true);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            if (!\Faoxima\Orders\Catalog\NameCascades::rxCategoryRenameCascade($pdo, (string) ($userdata['category'] ?? ''), (string) $text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['error'], $keyboard_Category_manage, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ نام دسته بندی با موفقیت تغییر کرد.", $keyboard_Category_manage, 'HTML');
            break;
        case 14:
            \Faoxima\Core\Database\Db::update("user", "pagenumber", "1", "id", $from_id);
            $page = 1;
            $items_per_page = 10;
            $start_index = ($page - 1) * $items_per_page;
            $_s = (int)$start_index; $_p = (int)$items_per_page;
            $_stmt = $connect->prepare("SELECT * FROM user WHERE Balance < 0 LIMIT ?, ?");
            $_stmt->bind_param("ii", $_s, $_p);
            $_stmt->execute();
            $result = $_stmt->get_result();
            $_stmt->close();
            $keyboardlists = [
                'inline_keyboard' => [],
            ];
            $keyboardlists['inline_keyboard'][] = [
                ['text' => "عملیات", 'callback_data' => "action"],
                ['text' => "نام کاربری", 'callback_data' => "username"],
                ['text' => "شناسه", 'callback_data' => "iduser"]
            ];
            while ($row = mysqli_fetch_assoc($result)) {
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                        'callback_data' => "manageuser_" . $row['id']
                    ],
                    [
                        'text' => $row['username'],
                        'callback_data' => "username"
                    ],
                    [
                        'text' => $row['id'],
                        'callback_data' => $row['id']
                    ],
                ];
            }
            $pagination_buttons = [
                [
                    'text' => $textbotlang['users']['page']['next'],
                    'callback_data' => 'next_pageuserzero'
                ]
            ];
            $backbtn = [
                [
                    'text' => "بازگشت به منوی قبل",
                    'callback_data' => 'backlistuser'
                ]
            ];
            $keyboardlists['inline_keyboard'][] = $pagination_buttons;
            $keyboardlists['inline_keyboard'][] = $backbtn;
            $keyboard_json = json_encode($keyboardlists);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 15:
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
            $_s = (int)$start_index; $_p = (int)$items_per_page;
            $_stmt = $connect->prepare("SELECT * FROM user WHERE Balance < 0 LIMIT ?, ?");
            $_stmt->bind_param("ii", $_s, $_p);
            $_stmt->execute();
            $result = $_stmt->get_result();
            $_stmt->close();
            $keyboardlists = [
                'inline_keyboard' => [],
            ];
            $keyboardlists['inline_keyboard'][] = [
                ['text' => "عملیات", 'callback_data' => "action"],
                ['text' => "نام کاربری", 'callback_data' => "username"],
                ['text' => "شناسه", 'callback_data' => "iduser"]
            ];
            while ($row = mysqli_fetch_assoc($result)) {
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                        'callback_data' => "manageuser_" . $row['id']
                    ],
                    [
                        'text' => $row['username'],
                        'callback_data' => "username"
                    ],
                    [
                        'text' => $row['id'],
                        'callback_data' => $row['id']
                    ],
                ];
            }
            $pagination_buttons = [
                [
                    'text' => $textbotlang['users']['page']['next'],
                    'callback_data' => 'next_pageuserzero'
                ],
                [
                    'text' => $textbotlang['users']['page']['previous'],
                    'callback_data' => 'previous_pageuserzero'
                ]
            ];
            $backbtn = [
                [
                    'text' => "بازگشت به منوی قبل",
                    'callback_data' => 'backlistuser'
                ]
            ];
            $keyboardlists['inline_keyboard'][] = $pagination_buttons;
            $keyboardlists['inline_keyboard'][] = $backbtn;
            $keyboard_json = json_encode($keyboardlists);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        case 16:
            $page = $user['pagenumber'];
            $items_per_page = 10;
            if ($user['pagenumber'] <= 1) {
                $next_page = 1;
            } else {
                $next_page = $page - 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $_s = (int)$start_index; $_p = (int)$items_per_page;
            $_stmt = $connect->prepare("SELECT * FROM user WHERE Balance < 0 LIMIT ?, ?");
            $_stmt->bind_param("ii", $_s, $_p);
            $_stmt->execute();
            $result = $_stmt->get_result();
            $_stmt->close();
            $keyboardlists = [
                'inline_keyboard' => [],
            ];
            $keyboardlists['inline_keyboard'][] = [
                ['text' => "عملیات", 'callback_data' => "action"],
                ['text' => "نام کاربری", 'callback_data' => "username"],
                ['text' => "شناسه", 'callback_data' => "iduser"]
            ];
            while ($row = mysqli_fetch_assoc($result)) {
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                        'callback_data' => "manageuser_" . $row['id']
                    ],
                    [
                        'text' => $row['username'],
                        'callback_data' => "username"
                    ],
                    [
                        'text' => $row['id'],
                        'callback_data' => $row['id']
                    ],
                ];
            }
            $pagination_buttons = [
                [
                    'text' => $textbotlang['users']['page']['next'],
                    'callback_data' => 'next_pageuserzero'
                ],
                [
                    'text' => $textbotlang['users']['page']['previous'],
                    'callback_data' => 'previous_pageuserzero'
                ]
            ];
            $backbtn = [
                [
                    'text' => "بازگشت به منوی قبل",
                    'callback_data' => 'backlistuser'
                ]
            ];
            $keyboardlists['inline_keyboard'][] = $pagination_buttons;
            $keyboardlists['inline_keyboard'][] = $backbtn;
            $keyboard_json = json_encode($keyboardlists);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
            break;
        }

        return true;
    }
}
