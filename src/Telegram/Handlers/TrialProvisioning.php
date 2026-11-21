<?php

namespace Faoxima\Telegram\Handlers;

use Throwable;

/**
 * Handler stage. Inherited by value: $admin_ids, $backuser, $callback_query_id, $errorreport, $first_name, $from_id, $keyboard, $keyboard_buy, $message_id, $reporttest, $request_contact, $setting, $telegramRoute, $textbotlang, $user, $username, $usernamePromptKb, $username_ac.
 * Inherited or exported by reference: $Response, $datain, $dataoutput, $datatextbot, $location, $marzban_list_get, $randomString, $text, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TrialProvisioning
{
    public static function handle(
        &$Response,
        $admin_ids,
        $backuser,
        $callback_query_id,
        &$datain,
        &$dataoutput,
        &$datatextbot,
        $errorreport,
        $first_name,
        $from_id,
        $keyboard,
        $keyboard_buy,
        &$location,
        &$marzban_list_get,
        $message_id,
        &$randomString,
        $reporttest,
        $request_contact,
        $setting,
        $telegramRoute,
        &$text,
        $textbotlang,
        $user,
        $username,
        $usernamePromptKb,
        $username_ac,
        &$value
    ) {
        switch ($telegramRoute) {
        case 'trial':
            $rxTestIsAdmin = in_array($from_id, $admin_ids);
            $userlimit = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id, "select", ['cache' => false]);
            if (!is_array($userlimit) || empty($userlimit)) {
                $userlimit = $user;
            }
            if (!\Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_feature_enabled()) {
                sendmessage($from_id, $datatextbot['dyn_errors_test_service_unavailable'] ?? "📌 سرویس تست در حال حاضر در دسترس نیست .", null, 'HTML');
                return;
            }
            $rxTestGate = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_verification_gate($userlimit, $rxTestIsAdmin);
            if ($rxTestGate === 'phone') {
                if ($user['step'] != "get_number") {
                    sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('get_number', $from_id);
                }
                return;
            }
            if ($rxTestGate === 'verify') {
                sendmessage($from_id, $datatextbot['dyn_testaccount_verify_required'] ?? "⚠️ حساب شما هنوز احراز هویت نشده است.", null, 'HTML');
                return;
            }
            $rxTestCode = \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxTrialRequestedPanel($datain, $user);
            $rxTestEligible = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_eligible_panels($userlimit);
            if ($rxTestCode === null && count($rxTestEligible) !== 1) {
                return;
            }
            $marzban_list_get = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_resolve_panel($userlimit, $rxTestCode, $rxTestEligible);
            if ($marzban_list_get === null) {
                if ($user['step'] == "createusertest") {
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                }
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                return;
            }
            $rxTestSettings = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_panel_settings($marzban_list_get);
            $rxTestQuota = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_quota_state($userlimit, $marzban_list_get, $rxTestSettings, $rxTestIsAdmin);
            if (!$rxTestQuota['can_create']) {
                if ($user['step'] == "createusertest") {
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                }
                sendmessage($from_id, $rxTestQuota['reason'] === 'audience_restricted' ? \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_audience_denied_text() : $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
                return;
            }
            if ($user['step'] != "createusertest" && strpos((string) $datain, 'locationtest_') !== 0) {
                \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('test', $from_id, $datain, $message_id, ['usertestbtn']);
            }
            $location = $marzban_list_get['code_panel'];
            $_rx_usernameKb = (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseUsernameUsesRandomSuffix($marzban_list_get['MethodUsername'])) ? $usernamePromptKb : $backuser;
            if (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseNeedsUsername($marzban_list_get['MethodUsername'], $textbotlang['users']['customusername'])) {
                if ($user['step'] != "createusertest") {
                    \Faoxima\Telegram\Navigation\Steps::step('createusertest', $from_id);
                    \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $location, "id", $from_id);
                    sendmessage($from_id, $textbotlang['users']['selectusername'], $_rx_usernameKb, 'html');
                    return;
                }
            }
            if ($user['step'] == "createusertest") {
                if ($datain === 'gen_random_uname') {
                    $text = \Faoxima\Services\Delivery\ServiceDelivery::rx_build_smart_random_username($username ?? '', $from_id);
                    if ($callback_query_id && function_exists('telegram')) {
                        @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
                    }
                }
                if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
                    sendmessage($from_id, $textbotlang['users']['invalidusername'], $_rx_usernameKb, 'HTML');
                    return;
                }
            } else {
                deletemessage($from_id, $message_id);
            }
            if (!\Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_manual_stock_available($marzban_list_get)) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_testaccount_manualsale_stock_depleted', "❌ موجودی این سرویس به پایان رسیده."), null, 'HTML');
                return;
            }
            $rxTestReserveError = '';
            $rxTestReservation = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_reserve($userlimit, $marzban_list_get, $rxTestSettings, $rxTestIsAdmin, 'bot', $rxTestReserveError);
            if ($rxTestReservation === null) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if ($rxTestReserveError === 'panel_unavailable') {
                    sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                    return;
                }
                sendmessage($from_id, $rxTestReserveError === 'audience_restricted' ? \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_audience_denied_text() : $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
                return;
            }
            $randomString = bin2hex(random_bytes(4));
            $text = strtolower((string) $text);
            try {
                $username_ac = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_build_username($userlimit, $marzban_list_get, $text, $randomString);
            } catch (Throwable $rxTestErr) {
                \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_reservation_update($rxTestReservation, 'failed');
                \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_release($rxTestReservation);
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
                return;
            }
            $rxTestProvision = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_provision($userlimit, $marzban_list_get, $rxTestSettings, $rxTestReservation, $username_ac, $randomString);
            if (empty($rxTestProvision['ok'])) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if ($rxTestProvision['error'] === 'stock_empty') {
                    sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_testaccount_manualsale_stock_depleted', "❌ موجودی این سرویس به پایان رسیده."), null, 'HTML');
                    return;
                }
                sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
                $rxTestErrReason = htmlspecialchars((string) $rxTestProvision['msg'], ENT_QUOTES, 'UTF-8');
                $texterros = "
⭕️ یک کاربر قصد دریافت اکانت  تست داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
<blockquote>✍️ دلیل خطا :
{$rxTestErrReason}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $errorreport,
                        'text' => $texterros,
                        'parse_mode' => "HTML"
                    ]);
                }
                return;
            }
            $dataoutput = $rxTestProvision['output'];
            $username_ac = (string) ($rxTestProvision['username'] ?? $username_ac);
            \Faoxima\Services\Panels\PanelMessages::rx_notify_username_renamed($from_id, $dataoutput, $marzban_list_get);
            $output_config_link = "";
            $config = "";
            $output_config_link = \Faoxima\Services\Delivery\ServiceDelivery::rxShouldShowConnectionLink($marzban_list_get, $dataoutput['file_ext'] ?? null) ? \Faoxima\Services\Delivery\ServiceDelivery::rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
            if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
                $rxTestTextConfigs = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'rxDeliveryTextConfigs') ? \Faoxima\Services\Panels\Drivers\JovadPanel::rxDeliveryTextConfigs($dataoutput['configs']) : $dataoutput['configs'];
                for ($i = 0; $i < count($rxTestTextConfigs); ++$i) {
                    $config .= "\n" . $rxTestTextConfigs[$i];
                }
            }

            $usertestinfo = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
                    ]
                ]
            ]);
            if ($marzban_list_get['type'] == "WGDashboard" || trim((string) ($datatextbot['textaftertext'] ?? '')) === "") {
                $datatextbot['textaftertext'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  ساعت
🗜 حجم سرویس:  {volume} مگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
            }
            $usertest_day = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_display_time($rxTestSettings);
            $usertest_volume = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_display_volume($rxTestSettings);
            $textcreatuser = str_replace('{username}', \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($dataoutput['username'], $marzban_list_get), $datatextbot['textaftertext']);
            $textcreatuser = str_replace('{name_service}', "تست", $textcreatuser);
            $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
            $textcreatuser = str_replace('{day}', $usertest_day, $textcreatuser);
            $textcreatuser = preg_replace('/\{volume\}[ \t\x{200c}]*(?:گیگابایت|گیگ|GB|مگابایت|مگ|MB)?/iu', $usertest_volume, $textcreatuser);
            $textcreatuser = \Faoxima\Core\Database\Db::applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
            $rxTestDelivered = true;
            try {
                \Faoxima\Services\Delivery\ServiceDelivery::sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $usertestinfo, $textcreatuser, $randomString);
            } catch (Throwable $rxTestErr) {
                $rxTestDelivered = false;
                error_log('[usertest] delivery failed: ' . $rxTestErr->getMessage());
            }
            \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_mark_delivery($rxTestReservation, $rxTestDelivered);
            if (!$rxTestDelivered) {
                sendmessage($from_id, $datatextbot['dyn_testaccount_delivery_failed_saved'] ?? "⚠️ اکانت تست ساخته شد اما ارسال جزئیات آن ناموفق بود. اطلاعات سرویس در بخش «سرویس‌های من» در دسترس است.", null, 'HTML');
            }
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                $value = intval($user['number_username']) + 1;
                \Faoxima\Core\Database\Db::update("user", "number_username", $value, "id", $from_id);
                if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                    $value = intval($setting['numbercount']) + 1;
                    \Faoxima\Core\Database\Db::update("setting", "numbercount", $value);
                }
            }
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
                    ],
                ]
            ]);
            $timejalali = jdate('Y/m/d H:i:s');
            $rxTestReportTime = !empty($rxTestSettings['time_unlimited']) ? \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_unlimited_label() : \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_stored_hours($rxTestSettings) . " ساعت";
            $rxTestReportVolume = !empty($rxTestSettings['volume_unlimited']) ? \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_unlimited_label() : \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_stored_mb($rxTestSettings) . " MB";
            $text_report = "📣 جزئیات ساخت اکانت تست در ربات شما ثبت شد .
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($username_ac, $marzban_list_get) . "</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>▫️زمان خریداری شده : {$rxTestReportTime}</blockquote>
<blockquote>▫️حجم خریداری شده : {$rxTestReportVolume}</blockquote>
<blockquote>▫️کد پیگیری: $randomString</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$user['number']}</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $reporttest,
                    'text' => $text_report,
                    'parse_mode' => "HTML",
                    'reply_markup' => $Response
                ]);
            }
            break;
        }

        return true;
    }
}
