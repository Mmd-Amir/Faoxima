<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $from_id, $optionX_ui_single, $textbotlang, $update, $user.
 * Inherited or exported by reference: $baseUrl, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class InboundAndProviderAccess
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        &$baseUrl,
        $from_id,
        $optionX_ui_single,
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
            $typepanelInboundPrompt = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (is_array($typepanelInboundPrompt) && ($typepanelInboundPrompt['type'] ?? '') === 'x-ui_single' && \Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($typepanelInboundPrompt)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام کاربری (ایمیل) یک کلاینت نمونه که همین الان روی پنل ساخته شده را ارسال کنید تا ربات شناسه اینباند(های) همان کلاینت را خودکار تشخیص داده و ذخیره کند.", $backadmin, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 شناسه اینباندی که می خواهید کانفیگ ازآن ساخته شود راارسال نمایید.  شناسه اینباند یک عدد چند رقمی است که در پنل  در صفحه اینباند ها ستون id  نوشته شده است

⚠️ در صورتی که پنل wgdashboard هستید باید نام کانفیگ را ارسال نمایید", $backadmin, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('getinboundiid', $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            $inboundInputRaw = trim($text);
            $typepanelInbound = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (is_array($typepanelInbound) && ($typepanelInbound['type'] ?? '') === 'x-ui_single' && \Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($typepanelInbound)) {
                $sampleClient = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_get_full_client($typepanelInbound, $inboundInputRaw);
                if (empty($sampleClient['status']) || empty($sampleClient['inboundIds'])) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کلاینتی با این نام کاربری روی پنل پیدا نشد یا به هیچ اینباندی متصل نیست. نام کاربری صحیح یک کلاینت موجود روی پنل را ارسال کنید.", $backadmin, 'HTML');
                    return;
                }
                $detectedIds = array_map('intval', $sampleClient['inboundIds']);
                $text = implode(',', $detectedIds);
                $xsyncRead = \Faoxima\Services\Panels\XuiInboundSync::xsync_fetch_client($typepanelInbound, $inboundInputRaw);
                if (!empty($xsyncRead['ok']) && !empty($xsyncRead['ids'])) {
                    \Faoxima\Services\Panels\XuiInboundSync::xsync_enable_panel($typepanelInbound['name_panel'], $inboundInputRaw, $xsyncRead['ids'], $typepanelInbound['inboundid'] ?? '');
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ شناسه اینباند(های) [{$text}] از روی کلاینت «{$inboundInputRaw}» تشخیص داده و ذخیره شد", $optionX_ui_single, 'HTML');
                \Faoxima\Core\Database\Db::update("marzban_panel", "inboundid", $text, "name_panel", $user['Processing_value']);
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ شناسه اینباند با موفقیت ذخیره گردید", $optionX_ui_single, 'HTML');
            \Faoxima\Core\Database\Db::update("marzban_panel", "inboundid", $text, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 3:
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $typepanel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (is_array($typepanel) && ($typepanel['type'] ?? null) == "rebecca") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً API Key جدید پنل Rebecca را ارسال کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('rebecca_edit_api_key', $from_id);
                return;
            }
            if (is_array($typepanel) && ($typepanel['type'] ?? null) == "jovadpanel") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً API Key جدید پنل JovadPanel را ارسال کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('jovad_edit_api_key', $from_id);
                return;
            }
            if (is_array($typepanel) && ($typepanel['type'] ?? null) == "pasarguard") {
                if (($typepanel['pasarguard_auth_mode'] ?? 'api_key') === 'password') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "👤 لطفاً نام کاربری جدید پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('pasarguard_edit_username', $from_id);
                    return;
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً API Key جدید پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('pasarguard_edit_api_key', $from_id);
                return;
            }
            if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['guard']['request_api_key'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('guard_edit_api_key', $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $typepanel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "pasarguard") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $baseUrl = rtrim((string) ($typepanel['url_panel'] ?? ''), '/');
            $currentKey = trim((string) ($typepanel['api_key'] ?? ''));
            if ($currentKey !== '' && hash_equals($currentKey, $apiKey)) {
                $sameKeyMessage = "ℹ️ کلید جدید با کلید قبلی یکسان است. تغییری انجام نشد.";
                $testResult = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardTestConnection($baseUrl, $apiKey);
                if ($testResult['status'] === false) {
                    $sameKeyMessage .= "\n❌ " . ($testResult['msg'] ?? 'اتصال ناموفق بود');
                } else {
                    $sameKeyMessage .= "\n✅ اتصال برقرار است";
                }
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("pasarguard", $sameKeyMessage);
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            $testResult = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardTestConnection($baseUrl, $apiKey);
            if ($testResult['status'] === false) {
                $errorMessage = $testResult['msg'] ?? 'اتصال ناموفق بود';
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("pasarguard", "❌ کلید جدید ذخیره نشد، اتصال به PasarGuard ناموفق بود:\n{$errorMessage}");
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "api_key", $apiKey, "name_panel", $typepanel['name_panel']);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel("pasarguard", "🔑 کلید PasarGuard با موفقیت ذخیره شد.\n✅ اتصال برقرار است");
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 5:
            if (!isset($update['message']) && empty($text)) { return; }
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $typepanel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "pasarguard") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $newUsername = trim((string) $text);
            if ($newUsername === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                return;
            }
            $editState = json_encode(['panel' => $panelName, 'pasarguard_edit_username_value' => $newUsername], JSON_UNESCAPED_UNICODE);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $editState, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً رمز عبور جدید پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('pasarguard_edit_password', $from_id);
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $typepanel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "pasarguard") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $newPassword = trim((string) $text);
            if ($newPassword === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً رمز عبور جدید پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            $pendingData = json_decode($user['Processing_value'], true);
            $newUsername = is_array($pendingData) ? (string) ($pendingData['pasarguard_edit_username_value'] ?? '') : '';
            if ($newUsername === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ اطلاعات ویرایش منقضی شده است. لطفاً دوباره تلاش کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            $baseUrl = rtrim((string) ($typepanel['url_panel'] ?? ''), '/');
            $testResult = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardTestConnectionUserPass($baseUrl, $newUsername, $newPassword);
            if (empty($testResult['status'])) {
                $errorMessage = $testResult['msg'] ?? 'اتصال ناموفق بود';
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("pasarguard", "❌ اطلاعات جدید ذخیره نشد، اتصال به PasarGuard ناموفق بود:\n{$errorMessage}");
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            if (method_exists(\Faoxima\Services\Panels\Drivers\PasarGuard::class, 'pasarguardClearToken')) {
                \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardClearToken($baseUrl, (string) ($typepanel['username_panel'] ?? ''));
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "username_panel", $newUsername, "name_panel", $typepanel['name_panel']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "password_panel", $newPassword, "name_panel", $typepanel['name_panel']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "datelogin", null, "name_panel", $typepanel['name_panel']);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel("pasarguard", "🔑 نام کاربری و رمز عبور PasarGuard با موفقیت ذخیره شد.\n✅ اتصال برقرار است");
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 7:
            if (!isset($update['message']) && empty($text)) { return; }
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $typepanel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "rebecca") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $baseUrl = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaGetBaseUrl($typepanel['url_panel'] ?? null);
            $currentKey = trim((string) ($typepanel['api_key'] ?? ''));
            if ($currentKey !== '' && hash_equals($currentKey, $apiKey)) {
                $sameKeyMessage = "ℹ️ کلید جدید با کلید قبلی یکسان است. تغییری انجام نشد.";
                $testResult = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaTestConnection($baseUrl, $apiKey);
                if ($testResult['status'] === false) {
                    $sameKeyMessage .= "\n❌ " . ($testResult['msg'] ?? 'اتصال ناموفق بود');
                } else {
                    $sameKeyMessage .= "\n✅ اتصال برقرار است";
                }
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("rebecca", $sameKeyMessage);
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            $testResult = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaTestConnection($baseUrl, $apiKey);
            if ($testResult['status'] === false) {
                $errorMessage = $testResult['msg'] ?? 'اتصال ناموفق بود';
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("rebecca", "❌ کلید جدید ذخیره نشد، اتصال به Rebecca ناموفق بود:\n{$errorMessage}");
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "api_key", $apiKey, "name_panel", $typepanel['name_panel']);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel("rebecca", "🔑 کلید Rebecca با موفقیت ذخیره شد.\n✅ اتصال برقرار است");
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $typepanel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "jovadpanel") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $baseUrl = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelHostUrl($typepanel['url_panel'] ?? null);
            $currentKey = trim((string) ($typepanel['api_key'] ?? ''));
            if ($currentKey !== '' && hash_equals($currentKey, $apiKey)) {
                $sameKeyMessage = "ℹ️ کلید جدید با کلید قبلی یکسان است. تغییری انجام نشد.";
                $testResult = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelTestConnection($baseUrl, $apiKey);
                if ($testResult['status'] === false) {
                    $sameKeyMessage .= "\n❌ " . ($testResult['msg'] ?? 'اتصال ناموفق بود');
                } else {
                    $sameKeyMessage .= "\n✅ اتصال برقرار است";
                }
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("jovadpanel", $sameKeyMessage);
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            $testResult = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelTestConnection($baseUrl, $apiKey);
            if ($testResult['status'] === false) {
                $errorMessage = $testResult['msg'] ?? 'اتصال ناموفق بود';
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("jovadpanel", "❌ کلید جدید ذخیره نشد، اتصال به JovadPanel ناموفق بود:\n{$errorMessage}");
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "api_key", $apiKey, "name_panel", $typepanel['name_panel']);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel("jovadpanel", "🔑 کلید JovadPanel با موفقیت ذخیره شد.\n✅ اتصال برقرار است");
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        }

        return true;
    }
}
