<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $datain, $from_id, $textbotlang, $update, $user.
 * Inherited or exported by reference: $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BulkRenewalAndProviderCapacity
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
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
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 تعداد روز برای تمدید همهٔ کاربران فعال این پنل را ارسال کنید (به هر کاربر این تعداد روز اضافه می‌شود).", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_remna_bulkextend', $from_id);
            break;
        case 2:
            try {
                $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
                if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("تمدید گروهی", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
                    return;
                }
                $days = (int) trim($text);
                if ($days < 1) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ عدد روز نامعتبر است.", $backadmin, 'HTML');
                    return;
                }
                $bres = method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_bulk_extend') ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_bulk_extend($typepanel['name_panel'], $days) : ['status' => 'Unsuccessful', 'count' => 0];
                if (($bres['status'] ?? '') !== 'successful') {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("تمدید گروهی", $from_id, "bulk_extend", ($bres['msg'] ?? 'unsuccessful') . " | days=$days");
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, ($bres['status'] ?? '') === 'successful' ? "✅ {$bres['count']} کاربر $days روز تمدید شدند." : "❌ خطا در ارتباط با پنل: " . ($bres['msg'] ?? 'خطا'), $backadmin, 'HTML');
            } catch (\Throwable $e) {
                \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("تمدید گروهی", $from_id, "exception", $e->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('remna_panel_back', $from_id);
            break;
        case 3:
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, \Faoxima\Services\Panels\PanelSetup::rxPanelUrlPrompt(is_array($typepanel) ? ($typepanel['type'] ?? '') : '', true), $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GeturlNew', $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!filter_var($text, FILTER_VALIDATE_URL)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
                return;
            }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $rxPanelUrlWarning = \Faoxima\Services\Panels\PanelSetup::rxPanelUrlPathWarning($text, is_array($typepanel) ? ($typepanel['type'] ?? '') : '');
            if ($rxPanelUrlWarning !== '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxPanelUrlWarning, $backadmin, 'HTML');
                return;
            }
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "url_panel", $text, "name_panel", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نوع کاربری را ارسال کنید
گروه های کاربری : f,n,n2
❌ در صورتی که می خواهید پنل برای تمام گروه کاربری ها نمایش داده شود متن all را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getagentpanel', $from_id);
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], "📌گروه کاربری با موفقیت تغییر کرد");
            \Faoxima\Core\Database\Db::update("marzban_panel", "agent", $text, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 اگر پنل ثنایی هستید یک لینک ساب کاربر را از پنل کپی کرده سپس در این بخش ارسال کنید .بقیه پنل ها باید طبق ساختارش ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GeturlNewx', $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            $inputLink = trim($text);
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if ($typepanel['type'] !== "x-ui_single" && !filter_var($inputLink, FILTER_VALIDATE_URL)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
                return;
            }
            if ($typepanel['type'] === "x-ui_single") {
                $text = \Faoxima\Services\Panels\XuiUrls::normalizeXuiSingleSubscriptionBaseUrl($inputLink);
            } else {
                $text = $inputLink;
            }
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "linksubx", $text, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 uuid ادمین را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getuuidadmin', $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], "✅ uuid ادمین ذخیره گردید");
            \Faoxima\Core\Database\Db::update("marzban_panel", "secret_code", $text, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 11:
            $rxLimitPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            $rxLimitMenu = method_exists(\Faoxima\Services\Panels\PanelLimits::class, 'panel_limit_menu_render') ? \Faoxima\Services\Panels\PanelLimits::panel_limit_menu_render($rxLimitPanelName) : null;
            if ($rxLimitMenu === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
            break;
        case 12:
            $rxLimitPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            $rxLimitMenu = method_exists(\Faoxima\Services\Panels\PanelLimits::class, 'panel_limit_menu_render') ? \Faoxima\Services\Panels\PanelLimits::panel_limit_menu_render($rxLimitPanelName) : null;
            if ($rxLimitMenu === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
            break;
        case 13:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['setlimit'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('panellimit_getnew', $from_id);
            break;
        case 14:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit((string)$text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['limitmenu_invalid'], $backadmin, 'HTML');
                return;
            }
            $rxLimitPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select");
            \Faoxima\Services\Panels\PanelLimits::panel_limit_set($typepanel, $text);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['changedlimit']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
            $rxLimitMenu = \Faoxima\Services\Panels\PanelLimits::panel_limit_menu_render($typepanel);
            if ($rxLimitMenu !== null) {
                sendmessage($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
            }
            break;
        case 15:
            $rxLimitPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
            \Faoxima\Services\Panels\PanelLimits::panel_limit_reset($typepanel);
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
            $rxLimitMenu = \Faoxima\Services\Panels\PanelLimits::panel_limit_menu_render($typepanel);
            if ($rxLimitMenu !== null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
            }
            break;
        case 16:
            $rxLimitPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
            \Faoxima\Services\Panels\PanelLimits::panel_limit_set_unlimited($typepanel);
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select", ['cache' => false]);
            $rxLimitMenu = \Faoxima\Services\Panels\PanelLimits::panel_limit_menu_render($typepanel);
            if ($rxLimitMenu !== null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxLimitMenu['text'], $rxLimitMenu['keyboard'], 'HTML');
            }
            break;
        case 17:
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            $rxLimitPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxLimitPanelName, "select");
            if (is_array($typepanel) && !empty($typepanel)) {
                \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['Back-menu']);
            }
            break;
        case 18:
            $rxTestPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            $rxTestPanel = $rxTestPanelName !== '' ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxTestPanelName, "select", ['cache' => false]) : false;
            if (!is_array($rxTestPanel) || empty($rxTestPanel['code_panel'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
                return;
            }
            $rxTestMenu = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_menu_render($rxTestPanel);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxTestMenu['text'], $rxTestMenu['keyboard'], 'HTML');
            break;
        case 19:
            if (!preg_match('/^tset_(menu|back|limit|ulimit|time|utime|vol|uvol|anew|aall)_([A-Za-z0-9_-]{1,100})$/', $datain, $rxTestCb)) {
                if (!empty($callback_query_id)) {
                    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "❌ درخواست نامعتبر است.", 'show_alert' => false]);
                }
                return;
            }
            $rxTestPanel = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_load_panel($rxTestCb[2]);
            $rxTestCurrentName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            if ($rxTestPanel === null || (string)$rxTestPanel['name_panel'] !== (string)$rxTestCurrentName) {
                if (!empty($callback_query_id)) {
                    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "⚠️ این منو منقضی شده است؛ دوباره از مدیریت پنل وارد شوید.", 'show_alert' => true]);
                }
                return;
            }
            $rxTestAction = $rxTestCb[1];
            $rxTestCode = (string)$rxTestPanel['code_panel'];
            if ($rxTestAction === 'back') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Services\Panels\PanelSetup::outtypepanel($rxTestPanel['type'], $textbotlang['Admin']['Back-menu']);
                return;
            }
            if ($rxTestAction === 'menu') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                $rxTestMenu = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_menu_render($rxTestPanel);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxTestMenu['text'], $rxTestMenu['keyboard'], 'HTML');
                return;
            }
            $rxTestUnlimitedMap = ['ulimit' => 'limit', 'utime' => 'time', 'uvol' => 'volume'];
            if (isset($rxTestUnlimitedMap[$rxTestAction])) {
                $rxTestSaved = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_save_settings($rxTestPanel, $rxTestUnlimitedMap[$rxTestAction], 'unlimited');
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                $rxTestPanel = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_load_panel($rxTestCode);
                if ($rxTestPanel === null) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
                    return;
                }
                $rxTestMenu = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_menu_render($rxTestPanel);
                $rxTestPrefix = $rxTestSaved ? "✅ ذخیره شد.\n\n" : "❌ ذخیره تنظیمات انجام نشد.\n\n";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxTestPrefix . $rxTestMenu['text'], $rxTestMenu['keyboard'], 'HTML');
                return;
            }
            if ($rxTestAction === 'limit') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                $rxTestAudienceMenu = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_audience_menu_render($rxTestPanel);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxTestAudienceMenu['text'], $rxTestAudienceMenu['keyboard'], 'HTML');
                return;
            }
            if ($rxTestAction === 'anew' || $rxTestAction === 'aall') {
                $rxTestAudience = $rxTestAction === 'anew' ? RX_TEST_AUDIENCE_NEW : RX_TEST_AUDIENCE_ALL;
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_flow_state($rxTestCode, $rxTestAudience), "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('tset_limit', $from_id);
                $rxTestPromptKb = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'tset_limit_' . $rxTestCode]]]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "👥 اعمال روی: " . \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_audience_label($rxTestAudience) . "\n\n👤 حداکثر تعداد اکانت تست هر کاربر برای این پنل را ارسال کنید.\n⚠️ فقط عدد صحیح بزرگ‌تر از صفر؛ برای نامحدود از دکمه «♾ سقف نامحدود» استفاده کنید.", $rxTestPromptKb, 'HTML');
                return;
            }
            $rxTestPrompts = [
                'time'  => ['updatetime', "🕰 مدت زمان سرویس تست را ارسال کنید.\n⚠️ زمان بر حسب ساعت و عدد صحیح بزرگ‌تر از صفر است؛ برای نامحدود از دکمه «♾ زمان نامحدود» استفاده کنید."],
                'vol'   => ['val_usertest', "💾 حجم سرویس تست را ارسال کنید.\n⚠️ حجم بر حسب مگابایت و عدد صحیح بزرگ‌تر از صفر است؛ برای نامحدود از دکمه «♾ حجم نامحدود» استفاده کنید."],
            ];
            $rxTestPromptKb = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت به تنظیمات تست", 'callback_data' => 'tset_menu_' . $rxTestCode]]]], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Navigation\Steps::step($rxTestPrompts[$rxTestAction][0], $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxTestPrompts[$rxTestAction][1], $rxTestPromptKb, 'HTML');
            break;
        case 20:
            $rxTestPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            $rxTestPanel = $rxTestPanelName !== '' ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxTestPanelName, "select", ['cache' => false]) : false;
            if (!is_array($rxTestPanel) || empty($rxTestPanel['code_panel'])) {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
                return;
            }
            $rxTestFieldMap = ['tset_limit' => 'limit', 'updatetime' => 'time', 'val_usertest' => 'volume'];
            $rxTestField = $rxTestFieldMap[$user['step']];
            $rxTestValue = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_normalize_digits($text);
            $rxTestAudience = null;
            if ($rxTestField === 'limit') {
                $rxTestAudience = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_parse_flow_state((string)($user['Processing_value_tow'] ?? ''), (string)$rxTestPanel['code_panel']);
                if ($rxTestAudience === null) {
                    \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                    $rxTestAudienceMenu = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_audience_menu_render($rxTestPanel);
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ مرحله قبلی منقضی شده است؛ لطفاً دوباره نوع کاربران را انتخاب کنید.\n\n" . $rxTestAudienceMenu['text'], $rxTestAudienceMenu['keyboard'], 'HTML');
                    return;
                }
            }
            $rxTestPromptKb = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => ($rxTestField === 'limit' ? 'tset_limit_' : 'tset_menu_') . $rxTestPanel['code_panel']]]]], JSON_UNESCAPED_UNICODE);
            if ($rxTestValue === '' || !ctype_digit($rxTestValue) || !\Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_save_settings($rxTestPanel, $rxTestField, $rxTestValue, $rxTestAudience)) {
                $rxTestInvalid = [
                    'limit'  => "❌ مقدار نامعتبر است. یک عدد صحیح بین 1 تا " . RX_TEST_MAX_LIMIT . " ارسال کنید.",
                    'time'   => "❌ زمان نامعتبر است. یک عدد صحیح بین 1 تا " . RX_TEST_MAX_HOURS . " ساعت ارسال کنید.",
                    'volume' => "❌ حجم نامعتبر است. یک عدد صحیح بین 1 تا " . RX_TEST_MAX_MB . " مگابایت ارسال کنید.",
                ];
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxTestInvalid[$rxTestField], $rxTestPromptKb, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            if ($rxTestField === 'limit') {
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
            }
            $rxTestPanel = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_load_panel((string)$rxTestPanel['code_panel']);
            if ($rxTestPanel === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
                return;
            }
            $rxTestMenu = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_admin_menu_render($rxTestPanel);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ ذخیره شد.\n\n" . $rxTestMenu['text'], $rxTestMenu['keyboard'], 'HTML');
            break;
        }

        return true;
    }
}
