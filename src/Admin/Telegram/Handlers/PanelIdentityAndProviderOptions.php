<?php

namespace Faoxima\Admin\Telegram\Handlers;

use Faoxima\Services\Panels\Remnawave\RemnawaveManager;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $from_id, $marzban_list, $message_id, $pdo, $remnaSquadMatch, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $data, $list.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PanelIdentityAndProviderOptions
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        &$data,
        $from_id,
        &$list,
        $marzban_list,
        $message_id,
        $pdo,
        $remnaSquadMatch,
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
            break;
        case 2:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['GetNameNew'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetNameNew', $from_id);
            break;
        case 3:
            if (!isset($update['message']) && empty($text)) { return; }
            $marzban_list = is_array($marzban_list) ? $marzban_list : [];
            if (in_array($text, $marzban_list, true)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
                return;
            }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (!\Faoxima\Orders\Catalog\NameCascades::rxPanelNameCascade($pdo, (string) $user['Processing_value'], (string) $text)) {
                \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['users']['stateus']['error']);
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedNmaePanel']);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $text, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 4:
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
                return;
            }
            $mgr = new RemnawaveManager($typepanel);
            $testRes = $mgr->testConnection($pdo);
            $configuredSquads = array_values(array_filter(array_map('trim', explode(',', (string) ($typepanel['remna_squad'] ?? '')))));
            $squadOk = count($configuredSquads) > 0 && count(array_filter($configuredSquads, [\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_is_uuid'])) === count($configuredSquads);
            if (!empty($testRes['ok'])) {
                $squadLine = $squadOk
                    ? "🛡 Squad: ✅ " . count($configuredSquads) . " مورد انتخاب شده"
                    : "🛡 Squad: ⚠️ تنظیم نشده یا نامعتبر";
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("remnawave", "✅ اتصال به پنل رمن‌ویو موفق بود.
" . $squadLine . "

" . \Faoxima\Services\Panels\PanelCapability::rx_cap_render(\Faoxima\Services\Panels\PanelCapability::rx_panel_capability_report($typepanel)));
            } else {
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("remnawave", "❌ اتصال ناموفق: " . ($testRes['message'] ?? '') . "

جزئیات خطا در لاگ سرور با هدر [REMNAMWAVE-PANEL-TEST-FAIL] ثبت شد.");
            }
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 توکن ثابت API پنل رمن‌ویو را ارسال کنید.
(از مسیر API Tokens در داشبورد ادمین رمن‌ویو بسازید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_remna_token', $from_id);
            break;
        case 6:
            \Faoxima\Core\Database\Db::update("marzban_panel", "remna_api_token", trim($text), "name_panel", $user['Processing_value']);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel("remnawave", "✅ توکن API با موفقیت ثبت شد.");
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 7:
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ اطلاعات پنل در دسترس نیست.", $backadmin, 'HTML');
                return;
            }
            $mgr = new RemnawaveManager($typepanel);
            $squadsRes = $mgr->getInternalSquads($pdo);
            $squadsList = (!empty($squadsRes['ok']) && is_array($squadsRes['data']['internalSquads'] ?? null)) ? $squadsRes['data']['internalSquads'] : [];
            if (empty($squadsList)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ دریافت لیست Squad از پنل ناموفق بود یا هیچ Squadی تعریف نشده است.", $backadmin, 'HTML');
                return;
            }
            $currentSquads = array_filter(array_map('trim', explode(',', (string) ($typepanel['remna_squad'] ?? ''))));
            $squadKeyboard = json_encode(['inline_keyboard' => \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_squad_keyboard_rows($squadsList, $currentSquads)], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🛡 Squad های پیش‌فرض این پنل را انتخاب کنید (می‌توانید چند Squad انتخاب کنید).", $squadKeyboard, 'HTML');
            break;
        case 8:
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'اطلاعات پنل در دسترس نیست.',
                    'show_alert' => true,
                    'cache_time' => 3,
                ]);
                return;
            }
            $toggledSquad = $remnaSquadMatch[1];
            $currentSquads = array_filter(array_map('trim', explode(',', (string) ($typepanel['remna_squad'] ?? ''))));
            if (in_array($toggledSquad, $currentSquads, true)) {
                $currentSquads = array_values(array_diff($currentSquads, [$toggledSquad]));
            } else {
                $currentSquads[] = $toggledSquad;
                $currentSquads = array_values($currentSquads);
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "remna_squad", implode(',', $currentSquads), "name_panel", $typepanel['name_panel']);
            $mgr = new RemnawaveManager($typepanel);
            $squadsRes = $mgr->getInternalSquads($pdo);
            $squadsList = (!empty($squadsRes['ok']) && is_array($squadsRes['data']['internalSquads'] ?? null)) ? $squadsRes['data']['internalSquads'] : [];
            $squadKeyboard = json_encode(['inline_keyboard' => \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_squad_keyboard_rows($squadsList, $currentSquads)], JSON_UNESCAPED_UNICODE);
            if (!empty($message_id)) {
                Editmessagetext($from_id, $message_id, "🛡 Squad های پیش‌فرض این پنل را انتخاب کنید (می‌توانید چند Squad انتخاب کنید).", $squadKeyboard, 'HTML');
            }
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => count($currentSquads) > 0 ? 'Squad های پیش‌فرض به‌روزرسانی شد.' : 'هیچ Squadی انتخاب نشده — کاربران جدید ساخته نخواهند شد تا حداقل یک Squad انتخاب کنید.',
                'show_alert' => false,
                'cache_time' => 0,
            ]);
            break;
        case 9:
            $panelRow = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $newRevoke = (is_array($panelRow) && ($panelRow['remna_revoke'] ?? 'off') === 'on') ? 'off' : 'on';
            \Faoxima\Core\Database\Db::update("marzban_panel", "remna_revoke", $newRevoke, "name_panel", $user['Processing_value']);
            $stateTxt = $newRevoke === 'on' ? '✅ فعال شد' : '❌ غیرفعال شد';
            \Faoxima\Services\Panels\PanelSetup::outtypepanel("remnawave", "🔄 دکمهٔ «تغییر لینک» برای کاربران این پنل $stateTxt.
با لغو اشتراک کامل، لینک اشتراک و رمزهای اتصال هر دو بازتولید می‌شوند.");
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام کاربری سرویس رمن‌ویو را ارسال کنید تا فقط رمزهای اتصال آن بازتولید شود (لینک اشتراک تغییر نمی‌کند).", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_remna_resetcreds', $from_id);
            break;
        case 11:
            try {
                $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
                if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("ریست رمز عبور کاربر", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
                    return;
                }
                $targetUser = trim($text);
                if ($targetUser === '') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام کاربری نامعتبر است.", $backadmin, 'HTML');
                    return;
                }
                $resetRes = method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_reset_credentials') ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_reset_credentials($typepanel['name_panel'], $targetUser) : ['status' => 'Unsuccessful'];
                if (($resetRes['status'] ?? '') === 'successful') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ رمزهای اتصال کاربر <code>" . htmlspecialchars($targetUser) . "</code> بازتولید شد. لینک اشتراک بدون تغییر است.", $backadmin, 'HTML');
                } else {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("ریست رمز عبور کاربر", $from_id, "reset_credentials", ($resetRes['msg'] ?? 'unsuccessful') . " | user=$targetUser");
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل: " . ($resetRes['msg'] ?? 'خطا'), $backadmin, 'HTML');
                }
            } catch (\Throwable $e) {
                \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("ریست رمز عبور کاربر", $from_id, "exception", $e->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('remna_panel_back', $from_id);
            break;
        case 12:
            try {
                $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
                if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("داشبورد نودها", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
                    return;
                }
                $nodesRes = method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_get_nodes') ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_get_nodes($typepanel['name_panel']) : ['status' => 'Unsuccessful', 'nodes' => []];
                if (($nodesRes['status'] ?? '') !== 'successful' || empty($nodesRes['nodes'])) {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("داشبورد نودها", $from_id, "get_nodes", "empty or unsuccessful response");
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل (دریافت لیست نودها ناموفق بود).", $backadmin, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $txt = "🖥 داشبورد نودهای رمن‌ویو:\n";
                foreach ($nodesRes['nodes'] as $nd) {
                    $light = !empty($nd['isDisabled']) ? '⛔️' : (!empty($nd['isConnected']) ? '🟢' : '🔴');
                    $cc = $nd['countryCode'] !== '' ? " ({$nd['countryCode']})" : '';
                    $txt .= "\n$light <b>" . htmlspecialchars($nd['name']) . "</b>$cc\n   👥 آنلاین: {$nd['usersOnline']} | ⚙️ Load: {$nd['loadAvg']} | 💻 RAM: {$nd['memPct']}%\n";
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $txt, $backadmin, 'HTML');
            } catch (\Throwable $e) {
                \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("داشبورد نودها", $from_id, "exception", $e->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('remna_panel_back', $from_id);
            break;
        case 13:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 آیدی عددی تلگرام کاربر را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_remna_tglookup', $from_id);
            break;
        case 14:
            try {
                $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
                if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("جستجو با آیدی تلگرام", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
                    return;
                }
                $tgId = trim($text);
                $look = method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_user_by_tg') ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_user_by_tg($typepanel['name_panel'], $tgId) : ['status' => 'Unsuccessful', 'data' => null];
                if (($look['status'] ?? '') !== 'successful' || empty($look['data'])) {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("جستجو با آیدی تلگرام", $from_id, "by_telegram_id", "no user for tgId=$tgId");
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کاربری با این آیدی تلگرام یافت نشد.", $backadmin, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $data = $look['data'];
                $list = [];
                if (isset($data['users']) && is_array($data['users'])) {
                    $list = $data['users'];
                } elseif (isset($data[0]) && is_array($data[0])) {
                    $list = $data;
                } elseif (is_array($data) && !empty($data['uuid'])) {
                    $list = [$data];
                }
                $txt = "🔎 نتایج برای تلگرام <code>" . htmlspecialchars($tgId) . "</code>:\n";
                foreach ($list as $u) {
                    if (!is_array($u)) {
                        continue;
                    }
                    $txt .= "\n👤 <code>" . htmlspecialchars((string)($u['username'] ?? '')) . "</code>\n   وضعیت: " . htmlspecialchars((string)($u['status'] ?? '')) . "\n   UUID: <code>" . htmlspecialchars((string)($u['uuid'] ?? '')) . "</code>\n";
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $txt, $backadmin, 'HTML');
            } catch (\Throwable $e) {
                \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("جستجو با آیدی تلگرام", $from_id, "exception", $e->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('remna_panel_back', $from_id);
            break;
        case 15:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 با فرمت زیر بفرستید:\n<code>username,squad-uuid[,squad-uuid2]</code>", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_remna_setsquad', $from_id);
            break;
        case 16:
            try {
                $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
                if (!is_array($typepanel) || ($typepanel['type'] ?? '') !== "remnawave") {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("انتساب Squad کاربر", $from_id, "panel_bind", "panel not resolved | pv=" . ($user['Processing_value'] ?? ''));
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل remnawave پیدا نشد — دوباره از «مدیریت پنل» انتخاب کنید.", $backadmin, 'HTML');
                    return;
                }
                $parts = array_map('trim', explode(',', $text));
                $uname = array_shift($parts);
                $squads = array_values(array_filter($parts, function ($v) {
                    return $v !== '';
                }));
                if ($uname === '' || count($squads) === 0) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ فرمت نادرست. نمونه: username,squad-uuid", $backadmin, 'HTML');
                    return;
                }
                $sres = method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_set_squads') ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_set_squads($typepanel['name_panel'], $uname, $squads) : ['status' => 'Unsuccessful'];
                if (($sres['status'] ?? '') !== 'successful') {
                    \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("انتساب Squad کاربر", $from_id, "set_squads", ($sres['msg'] ?? 'unsuccessful') . " | user=$uname");
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, ($sres['status'] ?? '') === 'successful' ? "✅ Squad کاربر بروزرسانی شد." : "❌ خطا در ارتباط با پنل: " . ($sres['msg'] ?? 'خطا'), $backadmin, 'HTML');
            } catch (\Throwable $e) {
                \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_admin_log("انتساب Squad کاربر", $from_id, "exception", $e->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در ارتباط با پنل. جزئیات در لاگ [REMNAWAVE-ADMIN-ERROR].", $backadmin, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('remna_panel_back', $from_id);
            break;
        }

        return true;
    }
}
