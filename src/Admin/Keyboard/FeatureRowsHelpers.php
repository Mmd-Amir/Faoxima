<?php

namespace Faoxima\Admin\Keyboard;

final class FeatureRowsHelpers
{
    public static function rx_featCategoryRows($cat)
    {
        global $textbotlang, $setting, $status_cron,
            $name_status, $name_status_role, $Authenticationphone, $Authenticationiran,
            $statusverify, $statusverifybyuser, $authScopeBtn, $authScopeVal, $statusinline,
            $name_status_username, $name_status_notifnewuser, $name_status_showagent,
            $statuspvsupport, $statusnameconfig, $statusnotef,
            $statusnamebulk, $btnstatuscategory, $keyboard_config_text, $status_copy_cart,
            $statusDebtsettlement, $statuslimitchangeloc, $infocardColorEmoji, $infocardStatusText,
            $infocardStatusValue, $forced_miniapp_status,
            $randomWalletStatusText, $randomWalletStatusValue,
            $wheel_luck, $statusfirstwheel, $wheelagent, $score, $Lotteryagent, $refralstatus, $statusDice,
            $cronteststatustext, $cronuptime_nodestatustext, $cronuptime_panelstatustext,
            $crondaystatustext, $cronon_holdtext, $cronvolumestatustext,
            $cronremovestatustext, $cronremovevolumestatustext;

        return self::rxAdminBuildFeatureRows($cat, $textbotlang, $setting, $status_cron, compact(
            'name_status', 'name_status_role', 'Authenticationphone', 'Authenticationiran', 'statusverify',
            'statusverifybyuser', 'authScopeBtn', 'authScopeVal', 'name_status_username', 'name_status_notifnewuser',
            'name_status_showagent', 'statuspvsupport', 'statusnameconfig', 'statusnotef', 'statusnamebulk',
            'btnstatuscategory', 'keyboard_config_text', 'status_copy_cart', 'statusDebtsettlement', 'statuslimitchangeloc',
            'infocardColorEmoji', 'infocardStatusText', 'infocardStatusValue', 'forced_miniapp_status', 'randomWalletStatusText',
            'randomWalletStatusValue', 'wheel_luck', 'statusfirstwheel', 'wheelagent', 'score',
            'Lotteryagent', 'refralstatus', 'statusDice', 'cronteststatustext', 'cronuptime_nodestatustext',
            'cronuptime_panelstatustext', 'crondaystatustext', 'cronon_holdtext', 'cronvolumestatustext', 'cronremovestatustext',
            'cronremovevolumestatustext',
        ));
    }

    public static function rxAdminBuildFeatureRows($cat, $textbotlang, $setting, $status_cron, array $labels): array
    {

        if ($cat === 'bot') {
            return [
                [['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
                 ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"]],
                [['text' => $labels['name_status'], 'callback_data' => "editstsuts-statusbot-{$setting['Bot_Status']}"],
                 ['text' => $textbotlang['Admin']['Status']['stautsbot'], 'callback_data' => "statusbot"]],
                [['text' => $labels['name_status_role'], 'callback_data' => "editstsuts-role-{$setting['roll_Status']}"],
                 ['text' => $textbotlang['Admin']['Status']['stautsrolee'], 'callback_data' => "stautsrolee"]],
                [['text' => $labels['Authenticationphone'], 'callback_data' => "editstsuts-get_number-{$setting['get_number']}"],
                 ['text' => $textbotlang['Admin']['Status']['Authenticationphone'], 'callback_data' => "Authenticationphone"]],
                [['text' => $labels['Authenticationiran'], 'callback_data' => "editstsuts-Authenticationiran-{$setting['iran_number']}"],
                 ['text' => $textbotlang['Admin']['Status']['Authenticationiran'], 'callback_data' => "Authenticationiran"]],
                [['text' => $labels['statusverify'], 'callback_data' => "editstsuts-verifystart-{$setting['verifystart']}"],
                 ['text' => "🔒 احراز هویت", 'callback_data' => "verify"]],
                [['text' => $labels['statusverifybyuser'], 'callback_data' => "editstsuts-verifybyuser-{$setting['verifybucodeuser']}"],
                 ['text' => "🔑 احراز با لینک", 'callback_data' => "verifybyuser"]],
                [['text' => $labels['authScopeBtn'], 'callback_data' => "editstsuts-authscope-{$labels['authScopeVal']}"]],
                [['text' => "🛡 تنظیمات حالت شیشه‌ای بخش کاربر", 'callback_data' => "glass_mode_settings"]],
                [['text' => $labels['forced_miniapp_status'], 'callback_data' => "editstsuts-forced_miniapp-{$setting['forced_miniapp_mode']}"],
                 ['text' => "📱 حالت اجباری مینی‌اپ", 'callback_data' => "forced_miniapp_info"]],
                [['text' => (((string)($setting['miniapp_ticket_mode'] ?? '0')) === '1')
                        ? (string)($textbotlang['Admin']['Status']['statuson']  ?? '✅ روشن')
                        : (string)($textbotlang['Admin']['Status']['statusoff'] ?? '❌ خاموش'),
                   'callback_data' => 'editstsuts-miniappticket-' . ((string)($setting['miniapp_ticket_mode'] ?? '0'))],
                 ['text' => "🎫 ارسال تیکت مینی‌اپ", 'callback_data' => "miniappticket_info"]],
                [['text' => (($setting['card_verify_status'] ?? 'offcardverify') === 'oncardverify')
                        ? (string)($textbotlang['Admin']['Status']['statuson']  ?? '✅ روشن')
                        : (string)($textbotlang['Admin']['Status']['statusoff'] ?? '❌ خاموش'),
                   'callback_data' => 'editstsuts-cardverify-' . ($setting['card_verify_status'] ?? 'offcardverify')],
                 ['text' => "💳 احراز هویت کارت‌به‌کارت", 'callback_data' => "cardverify_info"]],
                [['text' => "🎭 ارسال استیکر", 'callback_data' => "stmedia_menu"]],
            ];
        } elseif ($cat === 'users') {
            return [
                [['text' => $labels['name_status_username'], 'callback_data' => "editstsuts-usernamebtn-{$setting['NotUser']}"],
                 ['text' => $textbotlang['Admin']['Status']['statususernamebtn'], 'callback_data' => "usernamebtn"]],
                [['text' => $labels['name_status_notifnewuser'], 'callback_data' => "editstsuts-notifnew-{$setting['statusnewuser']}"],
                 ['text' => $textbotlang['Admin']['Status']['statusnotifnewuser'], 'callback_data' => "statusnewuser"]],
                [['text' => $labels['name_status_showagent'], 'callback_data' => "editstsuts-showagent-{$setting['statusagentrequest']}"],
                 ['text' => $textbotlang['Admin']['Status']['statusshowagent'], 'callback_data' => "statusnewuser"]],
                [['text' => $labels['statuspvsupport'], 'callback_data' => "editstsuts-statussupportpv-{$setting['statussupportpv']}"],
                 ['text' => "👤 پشتیبانی پیوی", 'callback_data' => "statussupportpv"]],
                [['text' => $labels['statusnameconfig'], 'callback_data' => "editstsuts-statusnamecustom-{$setting['statusnamecustom']}"],
                 ['text' => "📨 یادداشت کانفیگ", 'callback_data' => "statusnamecustom"]],
                [['text' => $labels['statusnotef'], 'callback_data' => "editstsuts-statusnamecustomf-{$setting['statusnoteforf']}"],
                 ['text' => "📨 یادداشت کاربر", 'callback_data' => "statusnamecustomf"]],
            ];
        } elseif ($cat === 'shop') {
            return [
                [['text' => $labels['statusnamebulk'], 'callback_data' => "editstsuts-bulkbuy-{$setting['bulkbuy']}"],
                 ['text' => "🛍 خرید عمده", 'callback_data' => "bulkbuy"]],
                [['text' => $labels['btnstatuscategory'], 'callback_data' => "editstsuts-btn_status_category-{$setting['categoryhelp']}"],
                 ['text' => "📗 دسته آموزش", 'callback_data' => "btn_status_category"]],
                [['text' => $labels['keyboard_config_text'], 'callback_data' => "editstsuts-keyconfig-{$setting['status_keyboard_config']}"],
                 ['text' => "🔗 کیبورد کانفیگی", 'callback_data' => "keyconfig"]],
                [['text' => $labels['status_copy_cart'], 'callback_data' => "editstsuts-compycart-{$setting['statuscopycart']}"],
                 ['text' => "💳 کپی شماره کارت", 'callback_data' => "copycart"]],
                [['text' => $labels['statusDebtsettlement'], 'callback_data' => "editstsuts-Debtsettlement-{$setting['Debtsettlement']}"],
                 ['text' => "💎 تسویه بدهی", 'callback_data' => "Debtsettlement"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "changeloclimit"],
                 ['text' => $labels['statuslimitchangeloc'], 'callback_data' => "editstsuts-changeloc-{$setting['statuslimitchangeloc']}"],
                 ['text' => "🌍 قفل مکان", 'callback_data' => "changeloc"]],
                [['text' => "{$labels['infocardColorEmoji']} رنگ کارت", 'callback_data' => "infocard_color_menu"],
                 ['text' => $labels['infocardStatusText'], 'callback_data' => "editstsuts-infocard-{$labels['infocardStatusValue']}"],
                 ['text' => "📊 کارت سرویس", 'callback_data' => "infocard_status"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "randomwalletlimit"],
                 ['text' => $labels['randomWalletStatusText'], 'callback_data' => "editstsuts-randomwallet-{$labels['randomWalletStatusValue']}"],
                 ['text' => "🎯 رندوم کیف پول", 'callback_data' => "randomwallet_status"]],
            ];
        } elseif ($cat === 'lottery') {
            return [
                [['text' => "⚙️ تنظیمات", 'callback_data' => "gradonhshans"],
                 ['text' => $labels['wheel_luck'], 'callback_data' => "editstsuts-wheel_luck-{$setting['wheelـluck']}"],
                 ['text' => "🎲 گردونه شانس", 'callback_data' => "none"]],
                [['text' => $labels['statusfirstwheel'], 'callback_data' => "editstsuts-wheelagentfirst-{$setting['statusfirstwheel']}"],
                 ['text' => "🎲 گردونه خرید اول", 'callback_data' => "wheelagentfirst"]],
                [['text' => $labels['wheelagent'], 'callback_data' => "editstsuts-wheelagent-{$setting['wheelagent']}"],
                 ['text' => "🎲 گردونه نماینده", 'callback_data' => "wheelagent"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "scoresetting"],
                 ['text' => $labels['score'], 'callback_data' => "editstsuts-score-{$setting['scorestatus']}"],
                 ['text' => "🎁 قرعه شبانه", 'callback_data' => "score"]],
                [['text' => $labels['Lotteryagent'], 'callback_data' => "editstsuts-Lotteryagent-{$setting['Lotteryagent']}"],
                 ['text' => "🎁 قرعه نماینده", 'callback_data' => "Lotteryagent"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "settingaffiliatesf"],
                 ['text' => $labels['refralstatus'], 'callback_data' => "editstsuts-affiliatesstatus-{$setting['affiliatesstatus']}"],
                 ['text' => "🎁 زیرمجموعه", 'callback_data' => "affiliatesstatus"]],
                [['text' => $labels['statusDice'], 'callback_data' => "editstsuts-Dice-{$setting['Dice']}"],
                 ['text' => "🎰 نمایش تاس", 'callback_data' => "Dice"]],
            ];
        } elseif ($cat === 'crons') {
            return [
                [['text' => $labels['cronteststatustext'], 'callback_data' => "editstsuts-crontest-{$status_cron['test']}"],
                 ['text' => "🔓 کرون تست", 'callback_data' => "none"]],
                [['text' => $labels['cronuptime_nodestatustext'], 'callback_data' => "editstsuts-uptime_node-{$status_cron['uptime_node']}"],
                 ['text' => "🎛 آپتایم نود", 'callback_data' => "none"]],
                [['text' => $labels['cronuptime_panelstatustext'], 'callback_data' => "editstsuts-uptime_panel-{$status_cron['uptime_panel']}"],
                 ['text' => "🎛 آپتایم پنل", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان هشدار", 'callback_data' => "settimecornday"],
                 ['text' => $labels['crondaystatustext'], 'callback_data' => "editstsuts-cronday-{$status_cron['day']}"],
                 ['text' => "🕚 کرون زمان", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان اتصال", 'callback_data' => "setting_on_holdcron"],
                 ['text' => $labels['cronon_holdtext'], 'callback_data' => "editstsuts-on_hold-{$status_cron['on_hold']}"],
                 ['text' => "🕚 کرون اتصال", 'callback_data' => "none"]],
                [['text' => "⚙️ حجم هشدار", 'callback_data' => "settimecornvolume"],
                 ['text' => $labels['cronvolumestatustext'], 'callback_data' => "editstsuts-cronvolume-{$status_cron['volume']}"],
                 ['text' => "🔋 کرون حجم", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremove"],
                 ['text' => $labels['cronremovestatustext'], 'callback_data' => "editstsuts-notifremove-{$status_cron['remove']}"],
                 ['text' => "❌ کرون حذف", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremovevolume"],
                 ['text' => $labels['cronremovevolumestatustext'], 'callback_data' => "editstsuts-notifremove_volume-{$status_cron['remove_volume']}"],
                 ['text' => "❌ کرون حذف‌حجم", 'callback_data' => "none"]],
                [['text' => "⚙️ تنظیم", 'callback_data' => "set_panel_timeout"],
                 ['text' => (method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_panel_timeout') ? \Faoxima\Services\Panels\PanelHttp::faoxima_panel_timeout() : (int)($setting['panel_curl_timeout'] ?? 20)) . " ثانیه", 'callback_data' => "none"],
                 ['text' => "⏱ تایم پنل", 'callback_data' => "none"]],
                [['text' => "⚙️ مدیریت", 'callback_data' => "cronjobs_settings"],
                 ['text' => "⏱ نمایش لیست", 'callback_data' => "cronjobs_settings"],
                 ['text' => "زمان‌بندی", 'callback_data' => "none"]],
                [['text' => "🧪 تست کرون", 'callback_data' => "cronhealth_start"]],
                [['text' => "⚙️ تنظیم ورکرها", 'callback_data' => "cronworkers_settings"]],
            ];
        }
        return [];
    }
}
