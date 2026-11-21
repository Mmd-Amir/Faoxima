<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $from_id, $keyboardprotocol, $message_id, $rebeccaServiceMatch, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $baseUrl, $connectionResult, $panel.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class GuardProviderManagement
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        &$baseUrl,
        $callback_query_id,
        &$connectionResult,
        $from_id,
        $keyboardprotocol,
        $message_id,
        &$panel,
        $rebeccaServiceMatch,
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
            if (!isset($update['message']) && empty($text)) { return; }
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $typepanel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($typepanel) || ($typepanel['type'] ?? null) != "guard") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $baseUrl = \Faoxima\Services\Panels\Drivers\Guard::guardGetBaseUrl($typepanel['url_panel'] ?? null);
            $currentKey = trim((string) (!empty($typepanel['api_key']) ? $typepanel['api_key'] : ($typepanel['password_panel'] ?? '')));
            $guardVersionForTest = ($typepanel['guard_version'] ?? 'v1') === 'v2' ? 'v2' : 'v1';
            if ($currentKey !== '' && hash_equals($currentKey, $apiKey)) {
                $sameKeyMessage = "ℹ️ کلید جدید با کلید قبلی یکسان است. تغییری انجام نشد.";
                $testResult = \Faoxima\Services\Panels\Drivers\Guard::guardTestConnection($baseUrl, $apiKey, $guardVersionForTest);
                $statusText = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardFormatConnectionResult($testResult);
                if ($statusText !== '') {
                    $sameKeyMessage .= "\n{$statusText}";
                }
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("guard", $sameKeyMessage);
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "api_key", $apiKey, "name_panel", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "password_panel", $apiKey, "name_panel", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "url_panel", $baseUrl, "name_panel", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
            $connectionResult = \Faoxima\Services\Panels\Drivers\Guard::guardTestConnection($baseUrl, $apiKey, $guardVersionForTest);
            $statusText = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardFormatConnectionResult($connectionResult);
            $savedMessage = $textbotlang['Admin']['managepanel']['guard']['api_key_saved'] ?? "🔑 کلید Guard ذخیره شد.";
            \Faoxima\Services\Panels\PanelSetup::outtypepanel("guard", "{$savedMessage}\n{$statusText}");
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 2:
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $typepanel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($typepanel) || empty($typepanel['type'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['errorstateuspanel'], $backadmin, 'HTML');
                return;
            }
            sendmessage($from_id, "⏳ در حال بررسی اتصال و دسترسی‌های پنل؛ ممکن است چند ثانیه طول بکشد...", null, 'HTML');
            $capabilityReport = \Faoxima\Services\Panels\PanelCapability::rx_panel_capability_report($typepanel);
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], \Faoxima\Services\Panels\PanelCapability::rx_cap_render($capabilityReport));
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 3:
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $panel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($panel) || ($panel['type'] ?? null) != "rebecca") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
                return;
            }
            $servicesResponse = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaGetServicePage($panel['name_panel'], 1);
            if ($servicesResponse['status'] === false || empty($servicesResponse['services'])) {
                $errorMsg = $servicesResponse['msg'] ?? 'لیست سرویس‌ها خالی است.';
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("rebecca", "❌ دریافت سرویس‌ها از Rebecca ناموفق بود: {$errorMsg}");
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            $currentServiceId = trim((string) ($panel['rebecca_service_id'] ?? ''));
            $rebeccaServiceView = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaBuildServiceKeyboard($servicesResponse['services'], $currentServiceId, 1, 5, $servicesResponse['has_next']);
            if ($rebeccaServiceView === null) {
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("rebecca", "❌ دریافت سرویس‌ها از Rebecca ناموفق بود: لیست سرویس‌ها خالی است.");
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚙️ سرویس پیش‌فرض فعلی را انتخاب کنید. با هر بار ضربه زدن روی یک سرویس، همان سرویس به‌عنوان پیش‌فرض ذخیره می‌شود.", $rebeccaServiceView['keyboard'], 'HTML');
            break;
        case 4:
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'cache_time' => 3,
            ]);
            break;
        case 5:
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $panel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($panel) || ($panel['type'] ?? null) != "rebecca") {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'اطلاعات پنل در دسترس نیست.',
                    'show_alert' => true,
                    'cache_time' => 3,
                ]);
                return;
            }
            $servicesResponse = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaGetServicePage($panel['name_panel'], (int) $rebeccaServiceMatch[1]);
            $rebeccaServiceView = ($servicesResponse['status'] !== false && is_array($servicesResponse['services'] ?? null))
                ? \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaBuildServiceKeyboard($servicesResponse['services'], $panel['rebecca_service_id'] ?? '', $servicesResponse['page'], 5, $servicesResponse['has_next'])
                : null;
            if ($rebeccaServiceView === null) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'دریافت لیست سرویس‌ها ناموفق بود. دوباره تلاش کنید.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }
            if (!empty($message_id)) {
                Editmessagetext($from_id, $message_id, "⚙️ سرویس پیش‌فرض فعلی را انتخاب کنید. با هر بار ضربه زدن روی یک سرویس، همان سرویس به‌عنوان پیش‌فرض ذخیره می‌شود.", $rebeccaServiceView['keyboard'], 'HTML');
            }
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'cache_time' => 0,
            ]);
            break;
        case 6:
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $panel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($panel) || ($panel['type'] ?? null) != "rebecca") {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'اطلاعات پنل در دسترس نیست.',
                    'show_alert' => true,
                    'cache_time' => 3,
                ]);
                return;
            }
            $chosenServiceId = (string) intval($rebeccaServiceMatch[1]);
            $rebeccaServicePage = isset($rebeccaServiceMatch[2]) ? (int) $rebeccaServiceMatch[2] : 1;
            $servicesResponse = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaGetServicePage($panel['name_panel'], $rebeccaServicePage);
            $rebeccaServiceView = ($servicesResponse['status'] !== false && is_array($servicesResponse['services'] ?? null))
                ? \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaBuildServiceKeyboard($servicesResponse['services'], $chosenServiceId, $servicesResponse['page'], 5, $servicesResponse['has_next'])
                : null;
            if ($rebeccaServiceView === null) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'دریافت لیست سرویس‌ها ناموفق بود؛ سرویس پیش‌فرض تغییر نکرد.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }
            if (!in_array($chosenServiceId, $rebeccaServiceView['ids'], true)) {
                $rebeccaServiceView = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaBuildServiceKeyboard($servicesResponse['services'], $panel['rebecca_service_id'] ?? '', $servicesResponse['page'], 5, $servicesResponse['has_next']);
                if (!empty($message_id)) {
                    Editmessagetext($from_id, $message_id, "⚙️ سرویس پیش‌فرض فعلی را انتخاب کنید. با هر بار ضربه زدن روی یک سرویس، همان سرویس به‌عنوان پیش‌فرض ذخیره می‌شود.", $rebeccaServiceView['keyboard'], 'HTML');
                }
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'این سرویس دیگر وجود ندارد؛ فهرست به‌روزرسانی شد.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }
            \Faoxima\Core\Database\Db::update("marzban_panel", "rebecca_service_id", $chosenServiceId, "name_panel", $panel['name_panel']);
            if (!empty($message_id)) {
                Editmessagetext($from_id, $message_id, "⚙️ سرویس پیش‌فرض فعلی را انتخاب کنید. با هر بار ضربه زدن روی یک سرویس، همان سرویس به‌عنوان پیش‌فرض ذخیره می‌شود.", $rebeccaServiceView['keyboard'], 'HTML');
            }
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'سرویس پیش‌فرض ذخیره شد.',
                'show_alert' => false,
                'cache_time' => 0,
            ]);
            break;
        case 7:
            $panelName = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardResolveUserPanelName($user);
            $panel = $panelName ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
            if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidserviceid'], $backadmin, 'HTML');
                return;
            }
            $servicesResponse = \Faoxima\Services\Panels\Drivers\Guard::guardGetServices($panel['name_panel']);
            if ($servicesResponse['status'] === false || empty($servicesResponse['services'])) {
                $errorMsg = $servicesResponse['msg'] ?? 'لیست سرویس Guard خالی است.';
                $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("guard", sprintf($failTextTemplate, $errorMsg));
                return;
            }
            $services = $servicesResponse['services'];
            $availableIds = \Faoxima\Services\Panels\Drivers\Guard::guardExtractServiceIdsFromList($services);
            if (empty($availableIds)) {
                $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
                \Faoxima\Services\Panels\PanelSetup::outtypepanel("guard", sprintf($failTextTemplate, 'شناسه معتبر برای سرویس‌ها یافت نشد.'));
                return;
            }
            $storedIds = \Faoxima\Services\Panels\Drivers\Guard::guardParseServiceIds($panel['guard_service_ids'] ?? null);
            $isAllStored = in_array('all', $storedIds, true) || in_array(0, $storedIds, true);
            $selected = $isAllStored ? $availableIds : array_values(array_intersect($availableIds, array_map('intval', $storedIds)));
            if (empty($selected)) {
                $selected = $availableIds;
            }
            $state = ['panel' => $panel['name_panel'], 'services' => $services, 'selected' => $selected];
            $sent = sendmessage($from_id, \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardSvcRenderText($services, $selected), \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardSvcRenderKeyboard($services, $selected), 'HTML');
            $state['message_id'] = $sent['result']['message_id'] ?? null;
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "guard_svc", $state);
            \Faoxima\Telegram\Navigation\Steps::step('guard_svc_edit', $from_id);
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getusernamenew'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetusernameNew', $from_id);
            break;
        case 9:
            if (!isset($update['message']) && empty($text)) { return; }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedusernamePanel']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "username_panel", $text, "name_panel", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getprotocolx_ui', $from_id);
            break;
        case 11:
            if (!isset($update['message']) && empty($text)) { return; }
            $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['setprotocol']);
            $marzbanprotocol = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            \Faoxima\Core\Database\Db::update("x_ui", "protocol", $text, "codepanel", $marzbanprotocol['code_panel']);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        case 12:
            $rxXuiModePanelRow = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (!is_array($rxXuiModePanelRow) || ($rxXuiModePanelRow['type'] ?? '') !== 'x-ui_single') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            } else {
                $xuiModeEditKb = json_encode([
                    'inline_keyboard' => [
                        [['text' => $textbotlang['Admin']['managepanel']['xuimodelegacy'], 'callback_data' => 'editxuimode#legacy']],
                        [['text' => $textbotlang['Admin']['managepanel']['xuimodetoken'], 'callback_data' => 'editxuimode#token']],
                    ],
                ]);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuimode'], $xuiModeEditKb, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('edit_xui_api_mode', $from_id);
            }
            break;
        }

        return true;
    }
}
