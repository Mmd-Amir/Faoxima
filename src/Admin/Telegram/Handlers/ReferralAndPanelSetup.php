<?php

namespace Faoxima\Admin\Telegram\Handlers;

use Throwable;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $affiliates, $backadmin, $callback_query_id, $dataget, $from_id, $rxPanelCat, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $connectionResult.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReferralAndPanelSetup
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $affiliates,
        $backadmin,
        $callback_query_id,
        &$connectionResult,
        $dataget,
        $from_id,
        $rxPanelCat,
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
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $affiliates, 'HTML');
            break;
        case 2:
            \Faoxima\Services\Panels\PanelSetup::rxAddPanelShowCategory($from_id);
            break;
        case 3:
            \Faoxima\Services\Panels\PanelSetup::rxAddPanelShowCategory($from_id, $rxPanelCat[1]);
            break;
        case 4:
            $typepanel = $dataget[1] === 'pasargard' ? 'pasarguard' : $dataget[1];
            \Faoxima\Services\Panels\PanelSetup::rxAddPanelResetDraft($from_id);
            $rx_addpanel_back_kb = \Faoxima\Services\Panels\PanelSetup::rxAddPanelBackKeyboard($typepanel);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $rx_addpanel_back_kb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("add_name_panel", $from_id);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "type", $typepanel);
            break;
        case 5:
            if (!isset($update['message']) && empty($text)) { return; }
            $namePanelDraft = json_decode((string) ($user['Processing_value'] ?? ''), true);
            $namePanelBackKeyboard = \Faoxima\Services\Panels\PanelSetup::rxAddPanelBackKeyboard(is_array($namePanelDraft) ? ($namePanelDraft['type'] ?? '') : '');
            $panelNameInput = trim((string) $text);
            if ($panelNameInput === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $namePanelBackKeyboard, 'HTML');
                return;
            }
            $existingPanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelNameInput, "select", ['cache' => false]);
            if (is_array($existingPanel) && !empty($existingPanel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $namePanelBackKeyboard, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            if (!is_array($userdata) || !in_array((string) ($userdata['type'] ?? ''), ['marzban', 'rebecca', 'pasarguard', 'remnawave', 'guard', 'x-ui_single', 'jovadpanel', 'Manualsale'], true)) {
                \Faoxima\Services\Panels\PanelSetup::rxAddPanelShowCategory($from_id, is_array($userdata) ? ($userdata['type'] ?? '') : '', "❌ اطلاعات افزودن پنل منقضی شده است. لطفاً نوع پنل را دوباره انتخاب کنید.");
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "namepanel", $panelNameInput);
            if ($userdata['type'] == "Manualsale") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('getlimitedpanel', $from_id);
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", "null");
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, \Faoxima\Services\Panels\PanelSetup::rxPanelUrlPrompt(is_array($userdata) ? ($userdata['type'] ?? '') : ''), $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('add_link_panel', $from_id);
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            $rxPanelUrlInput = \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminPanelUrlInput($text, $user, 'type');
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
            if (!is_array($userdata) || empty($userdata['type']) || empty($userdata['namepanel'])) {
                \Faoxima\Services\Panels\PanelSetup::rxAddPanelShowCategory($from_id, is_array($userdata) ? ($userdata['type'] ?? '') : '', "❌ اطلاعات افزودن پنل ناقص یا منقضی شده است. لطفاً افزودن پنل را از ابتدا انجام دهید.");
                return;
            }
            if ($userdata['type'] == "guard") {
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                \Faoxima\Telegram\Navigation\Steps::rxRenderWizardStep('add_guard_version', $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('add_guard_version', $from_id);
                return;
            } elseif ($userdata['type'] == "WGDashboard") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 توکن را ارسال نمایید", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_password_panel', $from_id);
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "username", "null");
                return;
            } elseif ($userdata['type'] == "remnawave") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 توکن ثابت API رمن‌ویو را ارسال نمایید.
(از مسیر API Tokens در داشبورد ادمین رمن‌ویو بسازید)", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_remna_token_setup', $from_id);
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                return;
            } elseif ($userdata['type'] == "rebecca") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً API Key پنل Rebecca را ارسال کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_rebecca_api_key', $from_id);
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                return;
            } elseif ($userdata['type'] == "jovadpanel") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً API Key فروشندگی پنل JovadPanel را ارسال کنید.
(از بخش Developers در پنل فروشنده ساخته می‌شود و فقط یک بار نمایش داده می‌شود)", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_jovad_api_key', $from_id);
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                return;
            } elseif ($userdata['type'] == "pasarguard") {
                \Faoxima\Telegram\Navigation\Steps::rxRenderWizardStep('add_pasarguard_auth_method', $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('add_pasarguard_auth_method', $from_id);
                return;
            } elseif ($userdata['type'] == "x-ui_single") {
                \Faoxima\Telegram\Navigation\Steps::rxRenderWizardStep('add_xui_api_mode', $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('add_xui_api_mode', $from_id);
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('add_username_panel', $from_id);
            break;
        case 7:
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
            \Faoxima\Telegram\Navigation\Steps::step('add_guard_api_key', $from_id);
            break;
        case 8:
            $xuiMode = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "xui_api_mode", $xuiMode);
            if ($xuiMode === 'token') {
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuiapitoken'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_xui_api_token', $from_id);
            } else {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "xui_api_token", "null");
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_username_panel', $from_id);
            }
            break;
        case 9:
            if (!isset($update['message']) && empty($text)) { return; }
            $xuiApiToken = trim($text);
            if ($xuiApiToken === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidxuiapitoken'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "xui_api_token", $xuiApiToken);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlimitedpanel', $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            $remnaApiToken = trim((string) $text);
            if ($remnaApiToken === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ توکن API نمی‌تواند خالی باشد.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "remna_api_token", $remnaApiToken);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlimitedpanel', $from_id);
            break;
        case 11:
            if (!isset($update['message']) && empty($text)) { return; }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $rebeccaBaseUrl = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaGetBaseUrl(isset($userdata['url_panel']) ? $userdata['url_panel'] : '');
            try {
                $connectionResult = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaTestConnection($rebeccaBaseUrl, $apiKey);
            } catch (Throwable $rebeccaConnectionError) {
                $connectionResult = ['status' => false, 'msg' => $rebeccaConnectionError->getMessage()];
            }
            if (!is_array($connectionResult) || empty($connectionResult['status'])) {
                $errorMessage = is_array($connectionResult) ? ($connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey']) : $textbotlang['Admin']['managepanel']['invalidapikey'];
                $feedback = "❌ اتصال به Rebecca ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_rebecca_api_key', $from_id);
                return;
            }
            $panelConfig = $connectionResult['panel_config'] ?? [
                'status' => true,
                'panel' => [
                    'type' => 'rebecca',
                    'url_panel' => $rebeccaBaseUrl,
                    'api_key' => $apiKey,
                ],
                'api_key' => $apiKey,
            ];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", $apiKey);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", $rebeccaBaseUrl);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "rebecca_service_id", "auto");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ اتصال به Rebecca برقرار شد.\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlimitedpanel', $from_id);
            break;
        case 12:
            if (!isset($update['message']) && empty($text)) { return; }
            $apiKey = trim((string) $text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $jovadBaseUrl = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelHostUrl(isset($userdata['url_panel']) ? $userdata['url_panel'] : '');
            try {
                $connectionResult = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelTestConnection($jovadBaseUrl, $apiKey);
            } catch (Throwable $jovadConnectionError) {
                $connectionResult = ['status' => false, 'msg' => $jovadConnectionError->getMessage()];
            }
            if (!is_array($connectionResult) || empty($connectionResult['status'])) {
                $errorMessage = is_array($connectionResult) ? ($connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey']) : $textbotlang['Admin']['managepanel']['invalidapikey'];
                $feedback = "❌ اتصال به JovadPanel ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_jovad_api_key', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", $apiKey);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", $jovadBaseUrl);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ اتصال به JovadPanel برقرار شد.\n\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlimitedpanel', $from_id);
            break;
        case 13:
            if (!isset($update['message']) && empty($text)) { return; }
            if (trim((string) $text) === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('add_password_panel', $from_id);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "username", trim((string) $text));
            break;
        case 14:
            if (!isset($update['message']) && empty($text)) { return; }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $guardBaseUrl = \Faoxima\Services\Panels\Drivers\Guard::guardGetBaseUrl(isset($userdata['url_panel']) ? $userdata['url_panel'] : '');
            $guardVersionForTest = isset($userdata['guard_version']) && $userdata['guard_version'] === 'v2' ? 'v2' : 'v1';
            try {
                $connectionResult = \Faoxima\Services\Panels\Drivers\Guard::guardTestConnection($guardBaseUrl, $apiKey, $guardVersionForTest);
            } catch (Throwable $guardConnectionError) {
                $connectionResult = ['status' => false, 'msg' => $guardConnectionError->getMessage()];
            }
            if (!is_array($connectionResult) || empty($connectionResult['status'])) {
                $errorMessage = is_array($connectionResult) ? ($connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey']) : $textbotlang['Admin']['managepanel']['invalidapikey'];
                $feedback = "❌ اتصال به گارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_guard_api_key', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", $apiKey);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", $guardBaseUrl);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                $from_id,
                "✅ اتصال به گارد برقرار شد.\n\n⚠️ توجه: سرویس‌های قابل ساخت روی این پنل هنوز تنظیم نشده‌اند. پیش از فروش، از منوی «مدیریت پنل‌ها ← ⚙️ تنظیم سرویس‌ها» آن‌ها را مشخص کنید؛ در غیر این صورت ساخت سرویس روی این پنل با خطا مواجه می‌شود.\n\n"
                    . $textbotlang['Admin']['managepanel']['getlimitedpanel'],
                $backadmin,
                'HTML'
            );
            \Faoxima\Telegram\Navigation\Steps::step('getlimitedpanel', $from_id);
            break;
        }

        return true;
    }
}
