<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $dataget, $from_id, $keyboardadmin, $matches, $message_id, $pdo, $text, $textbotlang, $update.
 * Inherited or exported by reference: $MethodUsername, $connectionResult, $randomString, $status, $stmt, $user, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderCredentials
{
    public static function handle(
        &$MethodUsername,
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        &$connectionResult,
        $dataget,
        $from_id,
        $keyboardadmin,
        $matches,
        $message_id,
        $pdo,
        &$randomString,
        &$status,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        &$user,
        &$value
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $pasarguardAuthMethod = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "pasarguard_auth_mode", $pasarguardAuthMethod);
            if ($pasarguardAuthMethod === 'password') {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", "null");
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_pasarguard_username', $from_id);
            } else {
                \Faoxima\Admin\Telegram\Support\CoreFunctionsHelpers::rxAdminClearPanelCredentials();
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 لطفاً API Key پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_pasarguard_api_key', $from_id);
            }
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            $pasarguardUsername = trim((string) $text);
            if ($pasarguardUsername === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "username", $pasarguardUsername);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('add_pasarguard_password', $from_id);
            break;
        case 3:
            if (!isset($update['message']) && empty($text)) { return; }
            $pasarguardPassword = trim((string) $text);
            if ($pasarguardPassword === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $pasarguardBaseUrl = rtrim((string) ($userdata['url_panel'] ?? ''), '/');
            $pasarguardUsernameForTest = (string) ($userdata['username'] ?? '');
            try {
                $connectionResult = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardTestConnectionUserPass($pasarguardBaseUrl, $pasarguardUsernameForTest, $pasarguardPassword);
            } catch (Throwable $pasarguardConnectionError) {
                $connectionResult = ['status' => false, 'msg' => $pasarguardConnectionError->getMessage()];
            }
            if (!is_array($connectionResult) || empty($connectionResult['status'])) {
                $errorMessage = is_array($connectionResult) ? ($connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['getpassword']) : $textbotlang['Admin']['managepanel']['getpassword'];
                $feedback = "❌ اتصال به پاسارگارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً نام کاربری و رمز عبور را بررسی کرده و رمز عبور را مجدداً ارسال کنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_pasarguard_password', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "password", $pasarguardPassword);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", $pasarguardBaseUrl);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ اتصال به پاسارگارد برقرار شد.\n\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlimitedpanel', $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            $apiKey = trim($text);
            if ($apiKey === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $pasarguardBaseUrl = rtrim((string) ($userdata['url_panel'] ?? ''), '/');
            try {
                $connectionResult = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardTestConnection($pasarguardBaseUrl, $apiKey);
            } catch (Throwable $pasarguardConnectionError) {
                $connectionResult = ['status' => false, 'msg' => $pasarguardConnectionError->getMessage()];
            }
            if (!is_array($connectionResult) || empty($connectionResult['status'])) {
                $errorMessage = is_array($connectionResult) ? ($connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey']) : $textbotlang['Admin']['managepanel']['invalidapikey'];
                $feedback = "❌ اتصال به پاسارگارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_pasarguard_api_key', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "api_key", $apiKey);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "url_panel", $pasarguardBaseUrl);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ اتصال به پاسارگارد برقرار شد.\n\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlimitedpanel', $from_id);
            break;
        case 5:
            $guardSvcLock = rx_callback_lock_acquire($from_id, 'guardsvc', 2.5);
            if ($guardSvcLock === false) {
                rx_callback_busy_reply($callback_query_id);
                return;
            }

            try {
                $guardSvcFresh = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id, "select", ['cache' => false]);
                $guardSvcRaw = is_array($guardSvcFresh) ? ($guardSvcFresh['Processing_value'] ?? null) : null;
                $userdata = (is_string($guardSvcRaw) && $guardSvcRaw !== '') ? json_decode($guardSvcRaw, true) : null;
                if (!is_array($userdata)) {
                    $userdata = json_decode($user['Processing_value'], true);
                }
                if (is_array($guardSvcFresh)) {
                    $user = $guardSvcFresh;
                }

                $state = isset($userdata['guard_svc']) ? $userdata['guard_svc'] : null;
                if (!is_array($state) || empty($state['services']) || empty($state['panel'])) {
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => 'این صفحه منقضی شده. دوباره از منوی مدیریت پنل وارد شوید.',
                        'show_alert' => true,
                        'cache_time' => 0,
                    ]);
                    return;
                }
                $services = $state['services'];
                $availableIds = \Faoxima\Services\Panels\Drivers\Guard::guardExtractServiceIdsFromList($services);
                $selected = array_values(array_intersect($availableIds, array_map('intval', $state['selected'] ?? [])));

                if (isset($matches[1]) && $matches[1] === 't') {
                    $id = intval($matches[2]);
                    if (!in_array($id, $availableIds, true)) {
                        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'شناسه سرویس نامعتبر است.', 'show_alert' => true, 'cache_time' => 0]);
                        return;
                    }
                    if (in_array($id, $selected, true)) {
                        $selected = array_values(array_diff($selected, [$id]));
                    } else {
                        $selected[] = $id;
                    }
                } elseif (($matches[3] ?? '') === 'a') {
                    $selected = $availableIds;
                } elseif (($matches[3] ?? '') === 'n') {
                    $selected = [];
                } elseif (($matches[3] ?? '') === 'x') {
                    \Faoxima\Core\Database\Db::update("user", "Processing_value", $state['panel'], "id", $from_id);
                    \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                    rx_callback_lock_release($guardSvcLock);
                    $guardSvcLock = false;
                    rx_releaseWebhookConnection();
                    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '', 'show_alert' => false, 'cache_time' => 0]);
                    deletemessage($from_id, $message_id);
                    \Faoxima\Services\Panels\PanelSetup::outtypepanel('guard', '⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید');
                    return;
                } elseif (($matches[3] ?? '') === 's') {
                    if (empty($selected)) {
                        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '⚠️ حداقل یک سرویس را انتخاب کنید', 'show_alert' => true, 'cache_time' => 0]);
                        return;
                    }
                    $storageValue = (count($selected) === count($availableIds)) ? '0' : json_encode(array_values($selected));
                    \Faoxima\Core\Database\Db::update("marzban_panel", "guard_service_ids", $storageValue, "name_panel", $state['panel']);
                    $state['selected'] = $selected;
                    \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "guard_svc", $state);
                    rx_callback_lock_release($guardSvcLock);
                    $guardSvcLock = false;
                    rx_releaseWebhookConnection();
                    rx_answer_and_edit($callback_query_id, 'ذخیره شد.', $from_id, $message_id, \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardSvcRenderText($services, $selected) . "\n\n✅ ذخیره شد.", \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardSvcRenderKeyboard($services, $selected));
                    return;
                }

                $state['selected'] = $selected;
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "guard_svc", $state);

                rx_callback_lock_release($guardSvcLock);
                $guardSvcLock = false;
                rx_releaseWebhookConnection();

                rx_answer_and_edit($callback_query_id, '', $from_id, $message_id, \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardSvcRenderText($services, $selected), \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardSvcRenderKeyboard($services, $selected));
            } finally {
                rx_callback_lock_release($guardSvcLock);
            }
            break;
        case 6:
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'این صفحه منقضی شده. دوباره از منوی مدیریت پنل وارد شوید.',
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            return;
            break;
        case 7:
            if (!isset($update['message']) && empty($text)) { return; }
            if (trim((string) $text) === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getlimitedpanel', $from_id);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "password", trim((string) $text));
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            $userdata = json_decode($user['Processing_value'], true);
            $supportedPanelTypes = ['marzban', 'rebecca', 'pasarguard', 'remnawave', 'guard', 'x-ui_single', 'jovadpanel', 'Manualsale'];
            $panelDraftValid = is_array($userdata)
                && in_array((string) ($userdata['type'] ?? ''), $supportedPanelTypes, true)
                && trim((string) ($userdata['namepanel'] ?? '')) !== ''
                && trim((string) ($userdata['url_panel'] ?? '')) !== ''
                && array_key_exists('username', $userdata)
                && array_key_exists('password', $userdata);
            if ($panelDraftValid && in_array($userdata['type'], ['rebecca', 'guard', 'jovadpanel'], true)) {
                $panelDraftValid = trim((string) ($userdata['api_key'] ?? '')) !== '';
            }
            if ($panelDraftValid && $userdata['type'] === 'pasarguard') {
                if (($userdata['pasarguard_auth_mode'] ?? 'api_key') === 'password') {
                    $panelDraftValid = trim((string) ($userdata['username'] ?? '')) !== ''
                        && trim((string) ($userdata['username'] ?? '')) !== 'null'
                        && trim((string) ($userdata['password'] ?? '')) !== ''
                        && trim((string) ($userdata['password'] ?? '')) !== 'null';
                } else {
                    $panelDraftValid = trim((string) ($userdata['api_key'] ?? '')) !== '';
                }
            }
            if ($panelDraftValid && $userdata['type'] === 'remnawave') {
                $panelDraftValid = trim((string) ($userdata['remna_api_token'] ?? '')) !== '';
            }
            if ($panelDraftValid && $userdata['type'] === 'x-ui_single' && ($userdata['xui_api_mode'] ?? 'legacy') === 'token') {
                $panelDraftValid = trim((string) ($userdata['xui_api_token'] ?? '')) !== '';
            }
            if (!$panelDraftValid) {
                \Faoxima\Services\Panels\PanelSetup::rxAddPanelShowCategory($from_id, is_array($userdata) ? ($userdata['type'] ?? '') : '', "❌ اطلاعات افزودن پنل ناقص یا منقضی شده است. لطفاً افزودن پنل را از ابتدا انجام دهید.");
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "limitpanel", $text);
            $randomString = bin2hex(random_bytes(2));

            $rx_panel_version_flag = '0';
            if ($userdata['type'] == "x-ui_single") {
                $marzbanprotocol = $randomString;
                $protocols = "vmess";
                $settingpanel = json_encode(array(
                    'network' => 'ws',
                    'security' => 'none',
                    'externalProxy' => array(),
                    'wsSettings' => array(
                        'acceptProxyProtocol' => false,
                        'path' => '/',
                        'host' => '',
                        'headers' => array()

                    ),
                ));
            }
            $sublink = "onsublink";
            $configstatus = "offconfig";
            $MethodUsername = "آیدی عددی + حروف و عدد رندوم";
            $status = "active";
            $ONTestAccount = "ONTestAccount";
            $extendtextadd = "ریست حجم و زمان";
            $namecustoms = "none";
            $type = "marzban";
            $conecton = "offconecton";
            $inboundid = 1;
            $agent = "all";
            $time = "1";
            $valume = "100";
            $changeloc = "offchangeloc";
            $apiKey = isset($userdata['api_key']) ? $userdata['api_key'] : null;
            $guardServiceIds = isset($userdata['guard_service_ids']) && $userdata['guard_service_ids'] !== '' ? $userdata['guard_service_ids'] : null;
            $guardVersionSetting = isset($userdata['guard_version']) && $userdata['guard_version'] === 'v2' ? 'v2' : 'v1';
            if ($userdata['type'] != "guard") {
                $guardVersionSetting = null;
            }
            $rebeccaServiceIdSetting = ($userdata['type'] ?? '') === 'rebecca' ? ($userdata['rebecca_service_id'] ?? 'auto') : null;
            if ($userdata['type'] == "guard") {
                $guardColumnsCheck = \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::ensureGuardPanelColumnsReady($pdo);
                if ($guardColumnsCheck['status'] === false) {
                    $missingList = implode(', ', $guardColumnsCheck['missing']);
                    $warningMessage = "❌ امکان ثبت پنل Guard وجود ندارد. ستون‌های موردنیاز یافت نشدند: {$missingList}\nلطفاً یکبار دیگر صفحه را اجرا کنید تا مهاجرت خودکار انجام شود و سپس مجدداً تلاش نمایید.";
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $warningMessage, $backadmin, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('add_guard_api_key', $from_id);
                    return;
                }
            }
            $value = json_encode(array(
                'f' => "4000",
                'n' => "4000",
                'n2' => "4000"
            ));
            $valuemain = json_encode(array(
                'f' => "1",
                'n' => "1",
                'n2' => "1"
            ));
            $valuemax = json_encode(array(
                'f' => "1000",
                'n' => "1000",
                'n2' => "1000"
            ));
            $VALUE = json_encode(array(
                'f' => '0',
                'n' => '0',
                'n2' => '0'
            ));
            $valuestatusin = "offinbounddisable";
            $statusextend = "on_extend";
            $subvip = "offsubvip";
            $stauts_on_holed = "1";
            $shopFeaturesDefault = \Faoxima\Services\Panels\PanelFeatures::panel_features_new_panel_defaults_json();
            $stmt = $pdo->prepare("INSERT INTO marzban_panel (code_panel,name_panel,sublink,config,MethodUsername,TestAccount,status,limit_panel,namecustom,Methodextend,type,conecton,inboundid,agent,inbound_deactive,inboundstatus,url_panel,username_panel,password_panel,api_key,time_usertest,val_usertest,linksubx,priceextravolume,priceextratime,pricecustomvolume,pricecustomtime,mainvolume,maxvolume,maintime,maxtime,status_extend,subvip,changeloc,customvolume,on_hold_test,version_panel,guard_service_ids,guard_note,guard_auto_delete_days,guard_auto_renewals,guard_version,shop_features,rebecca_service_id) VALUES (:code_panel,:name_panel,:sublink,:config,:MethodUsername,:TestAccount,:status,:limit_panel,:namecustom,:Methodextend,:type,:conecton,:inboundid,:agent,:inbound_deactive,:inboundstatus,:url_panel,:username_panel,:password_panel,:api_key,:time_usertest,:val_usertest,:linksubx,:priceextravolume,:priceextratime,:pricecustomvolume,:pricecustomtime,:mainvolume,:maxvolume,:maintime,:maxtime,:status_extend,:subvip,:changeloc,:customvolume,:on_hold_test,:version_panel,:guard_service_ids,:guard_note,:guard_auto_delete_days,:guard_auto_renewals,:guard_version,:shop_features,:rebecca_service_id)");
            $stmt->bindParam(':code_panel', $randomString);
            $stmt->bindParam(':name_panel', $userdata['namepanel'], PDO::PARAM_STR);
            $stmt->bindParam(':sublink', $sublink);
            $stmt->bindParam(':config', $configstatus);
            $stmt->bindParam(':MethodUsername', $MethodUsername);
            $stmt->bindParam(':TestAccount', $ONTestAccount);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':limit_panel', $text);
            $stmt->bindParam(':namecustom', $namecustoms);
            $stmt->bindParam(':Methodextend', $extendtextadd);
            $stmt->bindParam(':type', $userdata['type'], PDO::PARAM_STR);
            $stmt->bindParam(':conecton', $conecton);
            $stmt->bindParam(':inboundid', $inboundid);
            $stmt->bindParam(':agent', $agent);
            $stmt->bindParam(':inbound_deactive', $inboundid);
            $stmt->bindParam(':inboundstatus', $valuestatusin);
            $stmt->bindParam(':url_panel', $userdata['url_panel']);
            $stmt->bindParam(':linksubx', $userdata['url_panel']);
            $stmt->bindParam(':username_panel', $userdata['username']);
            $stmt->bindParam(':password_panel', $userdata['password']);
            $stmt->bindParam(':api_key', $apiKey);
            $stmt->bindParam(':val_usertest', $valume);
            $stmt->bindParam(':time_usertest', $time);
            $stmt->bindParam(':priceextravolume', $value);
            $stmt->bindParam(':priceextratime', $value);
            $stmt->bindParam(':pricecustomtime', $value);
            $stmt->bindParam(':pricecustomvolume', $value);
            $stmt->bindParam(':mainvolume', $valuemain);
            $stmt->bindParam(':maxvolume', $valuemax);
            $stmt->bindParam(':maintime', $valuemain);
            $stmt->bindParam(':maxtime', $valuemax);
            $stmt->bindParam(':status_extend', $statusextend);
            $stmt->bindParam(':subvip', $subvip);
            $stmt->bindParam(':changeloc', $changeloc);
            $stmt->bindParam(':customvolume', $VALUE);
            $stmt->bindParam(':on_hold_test', $stauts_on_holed);
            $stmt->bindParam(':version_panel', $rx_panel_version_flag, PDO::PARAM_STR);
            $stmt->bindParam(':guard_service_ids', $guardServiceIds);
            $guardNoteSettingUnused = null;
            $guardAutoDeleteDaysUnused = 0;
            $guardAutoRenewalsSettingUnused = null;
            $stmt->bindParam(':guard_note', $guardNoteSettingUnused);
            $stmt->bindParam(':guard_auto_delete_days', $guardAutoDeleteDaysUnused);
            $stmt->bindParam(':guard_auto_renewals', $guardAutoRenewalsSettingUnused);
            $stmt->bindParam(':guard_version', $guardVersionSetting);
            $stmt->bindParam(':shop_features', $shopFeaturesDefault);
            $stmt->bindParam(':rebecca_service_id', $rebeccaServiceIdSetting);
            try {
                $stmt->execute();
            } catch (Throwable $panelInsertError) {
                error_log('ADD_PANEL_INSERT_FAILED type=' . (string) $userdata['type'] . ' name=' . (string) $userdata['namepanel'] . ' error=' . $panelInsertError->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ثبت پنل انجام نشد. نام پنل و اطلاعات اتصال را بررسی کنید و دوباره تلاش نمایید.", $backadmin, 'HTML');
                return;
            }
            if (($userdata['type'] ?? '') == "remnawave" && isset($userdata['remna_api_token'])) {
                $stmt_remna = $pdo->prepare("UPDATE marzban_panel SET remna_api_token = :t WHERE name_panel = :n");
                $stmt_remna->execute([':t' => (string) $userdata['remna_api_token'], ':n' => $userdata['namepanel']]);
            }
            if (($userdata['type'] ?? '') == "x-ui_single") {
                $xuiApiMode = (isset($userdata['xui_api_mode']) && $userdata['xui_api_mode'] === 'token') ? 'token' : 'legacy';
                $xuiApiTokenValue = ($xuiApiMode === 'token' && isset($userdata['xui_api_token']) && $userdata['xui_api_token'] !== 'null')
                    ? (string) $userdata['xui_api_token']
                    : '';
                $stmt_xui = $pdo->prepare("UPDATE marzban_panel SET xui_api_mode = :m, xui_api_token = :t WHERE name_panel = :n");
                $stmt_xui->execute([':m' => $xuiApiMode, ':t' => $xuiApiTokenValue, ':n' => $userdata['namepanel']]);
            }
            if (($userdata['type'] ?? '') == "pasarguard") {
                $pasarguardAuthModeValue = (isset($userdata['pasarguard_auth_mode']) && $userdata['pasarguard_auth_mode'] === 'password') ? 'password' : 'api_key';
                $stmt_pasarguard = $pdo->prepare("UPDATE marzban_panel SET pasarguard_auth_mode = :m WHERE name_panel = :n");
                $stmt_pasarguard->execute([':m' => $pasarguardAuthModeValue, ':n' => $userdata['namepanel']]);
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addedpanel'], $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🥳", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            if ($userdata['type'] == "x-ui_single") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم شناسه اینباند و دامنه لینک ساب را حتما تنظیم نمایید در غیراینصورت کانفیگ ساخته نخواهد شد", null, 'HTML');
            } elseif ($userdata['type'] == "marzban") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم پروتکل و اینباند را تنظیم نمایید تا ربات کانفیگ دهد در غیراینصورت کانفیگ به  کاربر داده نمی شود", null, 'HTML');
            } elseif ($userdata['type'] == "WGDashboard") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
منوی تنظیم شناسه اینباند رفته و نام کانفیگ را تنظیم نمایید در غیراینصورت ربات هیچ کانفیگی نمیسازد", null, 'HTML');
            } elseif ($userdata['type'] == "remnawave") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ پنل رمن‌ویو با موفقیت ثبت شد. لطفاً شناسه Squad خود را حتماً تنظیم کرده و سپس دکمه تست اتصال را بزنید.", null, 'HTML');
            } elseif ($userdata['type'] == "rebecca") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ پنل Rebecca با موفقیت ثبت شد. در صورت نیاز سرویس پیش‌فرض را از منوی مدیریت پنل تغییر دهید.", null, 'HTML');
            } elseif ($userdata['type'] == "jovadpanel") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ پنل JovadPanel با موفقیت ثبت شد.
دسترسی مشتریان به کانفیگ و لینک اشتراک از منوی «⚙️ وضعیت قابلیت ها پنل» تنظیم می‌شود و WireGuard/OpenVPN به‌صورت فایل ارسال می‌شود.", null, 'HTML');
            } elseif ($userdata['type'] == "pasarguard") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم پروتکل و اینباند را تنظیم نمایید تا ربات کانفیگ دهد در غیراینصورت کانفیگ به  کاربر داده نمی شود", null, 'HTML');
            }
            break;
        }

        return true;
    }
}
