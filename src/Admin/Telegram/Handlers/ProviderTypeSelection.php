<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $adminPanelsMenu, $backadmin, $callback_query_id, $dataget, $from_id, $rxSwitchPanelCat, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $connectionResult.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderTypeSelection
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $adminPanelsMenu,
        $backadmin,
        $callback_query_id,
        &$connectionResult,
        $dataget,
        $from_id,
        $rxSwitchPanelCat,
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
            $rxSwitchCategory = $rxSwitchPanelCat[1];
            if ($rxSwitchCategory === 'menu') {
                $rxSwitchDraft = json_decode((string) ($user['Processing_value'] ?? ''), true);
                $rxSwitchPanelName = is_array($rxSwitchDraft) ? (string) ($rxSwitchDraft['name_panel'] ?? '') : '';
                $rxSwitchPanel = $rxSwitchPanelName !== '' ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxSwitchPanelName, "select") : false;
                if (is_array($rxSwitchPanel) && !empty($rxSwitchPanel)) {
                    \Faoxima\Core\Database\Db::update("user", "Processing_value", $rxSwitchPanel['name_panel'], "id", $from_id);
                    \Faoxima\Services\Panels\PanelSetup::outtypepanel($rxSwitchPanel['type'], $textbotlang['Admin']['Back-menu']);
                } else {
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
                }
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, \Faoxima\Services\Panels\PanelSetup::rxAddPanelCategoryMessage($rxSwitchCategory), \Faoxima\Services\Panels\PanelSetup::rxSwitchTypeKeyboard($rxSwitchCategory), 'HTML');
            }
            break;
        case 2:
            $newType = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "new_type", $newType);
            if ($newType == "Manualsale") {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", "null");
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_confirm', $from_id);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, \Faoxima\Services\Panels\PanelSetup::rxPanelUrlPrompt($newType), $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_link_panel', $from_id);
            }
            break;
        case 3:
            if (!isset($update['message']) && empty($text)) { return; }
            $rxPanelUrlInput = \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminPanelUrlInput($text, $user, 'new_type');
            if ($rxPanelUrlInput['status'] === 'invalid_url') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
                return;
            }
            if ($rxPanelUrlInput['status'] === 'invalid_path') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxPanelUrlInput['warning'], $backadmin, 'HTML');
                return;
            }
            $normalizedPanelUrl = $rxPanelUrlInput['url'];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", $normalizedPanelUrl);
            $userdata = json_decode($user['Processing_value'], true);
            if ($userdata['new_type'] == "guard") {
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                $guardVersionKb = json_encode([
                    'inline_keyboard' => [
                        [['text' => 'v1', 'callback_data' => 'switchguardversion#v1']],
                        [['text' => 'v2', 'callback_data' => 'switchguardversion#v2']],
                    ],
                ]);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['guard']['getversion'] ?? '🛡 نسخه پنل Guard را انتخاب کنید:', $guardVersionKb, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_guard_version', $from_id);
            } elseif ($userdata['new_type'] == "WGDashboard") {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "username", "null");
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 توکن را ارسال نمایید", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_password_panel', $from_id);
            } elseif ($userdata['new_type'] == "remnawave") {
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 توکن ثابت API رمن‌ویو را ارسال نمایید.
(از مسیر API Tokens در داشبورد ادمین رمن‌ویو بسازید)", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_remna_token', $from_id);
            } elseif ($userdata['new_type'] == "rebecca") {
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً API Key پنل Rebecca را ارسال کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_rebecca_api_key', $from_id);
            } elseif ($userdata['new_type'] == "jovadpanel") {
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً API Key فروشندگی پنل JovadPanel را ارسال کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_jovad_api_key', $from_id);
            } elseif ($userdata['new_type'] == "pasarguard" || $userdata['new_type'] == "pasargard") {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "new_type", "pasarguard");
                $switchPasarguardAuthKb = json_encode([
                    'inline_keyboard' => [
                        [['text' => '👤 نام کاربری / رمز عبور', 'callback_data' => 'switchpasarguardauth#password']],
                        [['text' => '🔑 API Key', 'callback_data' => 'switchpasarguardauth#api_key']],
                    ],
                ]);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔐 روش احراز هویت پنل PasarGuard را انتخاب کنید:", $switchPasarguardAuthKb, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_pasarguard_auth_method', $from_id);
            } elseif ($userdata['new_type'] == "x-ui_single") {
                $xuiModeKb = json_encode([
                    'inline_keyboard' => [
                        [['text' => $textbotlang['Admin']['managepanel']['xuimodelegacy'], 'callback_data' => 'switchxuimode#legacy']],
                        [['text' => $textbotlang['Admin']['managepanel']['xuimodetoken'], 'callback_data' => 'switchxuimode#token']],
                    ],
                ]);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuimode'], $xuiModeKb, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_xui_api_mode', $from_id);
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_username_panel', $from_id);
            }
            break;
        case 4:
            $xuiMode = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "xui_api_mode", $xuiMode);
            if ($xuiMode === 'token') {
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuiapitoken'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_xui_token', $from_id);
            } else {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "xui_api_token", "null");
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_username_panel', $from_id);
            }
            break;
        case 5:
            if (!isset($update['message']) && empty($text)) { return; }
            $xuiApiToken = trim($text);
            if ($xuiApiToken === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidxuiapitoken'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "xui_api_token", $xuiApiToken);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_confirm', $from_id);
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "remna_api_token", trim($text));
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_confirm', $from_id);
            break;
        case 7:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "username", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_password_panel', $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "password", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_confirm', $from_id);
            break;
        case 9:
            $guardVersion = $dataget[1];
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => "نسخه {$guardVersion} انتخاب شد.",
                    'show_alert' => false,
                    'cache_time' => 3,
                ]);
            }
            if (function_exists('rx_releaseWebhookConnection')) {
                rx_releaseWebhookConnection();
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "guard_version", $guardVersion);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_guard_api_key', $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", $apiKey);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_confirm', $from_id);
            break;
        case 11:
            if (!isset($update['message']) && empty($text)) { return; }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", $apiKey);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "rebecca_service_id", "auto");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_confirm', $from_id);
            break;
        case 12:
            if (!isset($update['message']) && empty($text)) { return; }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", $apiKey);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_confirm', $from_id);
            break;
        case 13:
            $pasarguardAuthMethod = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "pasarguard_auth_mode", $pasarguardAuthMethod);
            if ($pasarguardAuthMethod === 'password') {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", "null");
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_pasarguard_username', $from_id);
            } else {
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً API Key پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_pasarguard_api_key', $from_id);
            }
            break;
        case 14:
            if (!isset($update['message']) && empty($text)) { return; }
            $pasarguardUsername = trim((string) $text);
            if ($pasarguardUsername === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "username", $pasarguardUsername);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_pasarguard_password', $from_id);
            break;
        case 15:
            if (!isset($update['message']) && empty($text)) { return; }
            $pasarguardPassword = trim((string) $text);
            if ($pasarguardPassword === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $pasarguardBaseUrl = rtrim((string) ($userdata['url_panel'] ?? ''), '/');
            $pasarguardUsernameForTest = (string) ($userdata['username'] ?? '');
            $connectionResult = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardTestConnectionUserPass($pasarguardBaseUrl, $pasarguardUsernameForTest, $pasarguardPassword);
            if (empty($connectionResult['status'])) {
                $errorMessage = $connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['getpassword'];
                $feedback = "❌ اتصال به پاسارگارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً نام کاربری و رمز عبور را بررسی کرده و رمز عبور را مجدداً ارسال کنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_pasarguard_password', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "password", $pasarguardPassword);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", $pasarguardBaseUrl);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ اتصال به پاسارگارد برقرار شد.\n\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_confirm', $from_id);
            break;
        case 16:
            if (!isset($update['message']) && empty($text)) { return; }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $pasarguardBaseUrl = rtrim((string) ($userdata['url_panel'] ?? ''), '/');
            $connectionResult = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardTestConnection($pasarguardBaseUrl, $apiKey);
            if ($connectionResult['status'] === false) {
                $errorMessage = $connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey'];
                $feedback = "❌ اتصال به پاسارگارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('switchtype_pasarguard_api_key', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", $apiKey);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", $pasarguardBaseUrl);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ اتصال به پاسارگارد برقرار شد.\n\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('switchtype_confirm', $from_id);
            break;
        }

        return true;
    }
}
