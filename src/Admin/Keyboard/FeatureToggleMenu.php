<?php

namespace Faoxima\Admin\Keyboard;

use PDO;

final class FeatureToggleMenu
{
    public static function build(PDO $pdo, string $type, array $setting, array $textbotlang, array $status_cron): array
    {
        $_rxOn  = (string)($textbotlang['Admin']['Status']['statuson']  ?? 'فعال');
        $_rxOff = (string)($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
        $name_status = [
            'botstatuson' => $_rxOn,
            'botstatusoff' => $_rxOff,
        ][$setting['Bot_Status']] ?? $_rxOff;
        $name_status_username = [
            'onnotuser' => $_rxOn,
            'offnotuser' => $_rxOff,
        ][$setting['NotUser']] ?? $_rxOff;
        $name_status_notifnewuser = [
            'onnewuser' => $_rxOn,
            'offnewuser' => $_rxOff,
        ][$setting['statusnewuser']] ?? $_rxOff;
        $name_status_showagent = [
            'onrequestagent' => $_rxOn,
            'offrequestagent' => $_rxOff,
        ][$setting['statusagentrequest']] ?? $_rxOff;
        $name_status_role = [
            'rolleon' => $_rxOn,
            'rolleoff' => $_rxOff,
        ][$setting['roll_Status']] ?? $_rxOff;
        $Authenticationphone = [
            'onAuthenticationphone' => $_rxOn,
            'offAuthenticationphone' => $_rxOff,
        ][$setting['get_number']] ?? $_rxOff;
        $Authenticationiran = [
            'onAuthenticationiran' => $_rxOn,
            'offAuthenticationiran' => $_rxOff,
        ][$setting['iran_number']] ?? $_rxOff;
        $statusinline = [
            'oninline' => $_rxOn,
            'offinline' => $_rxOff,
        ][$setting['inlinebtnmain']] ?? $_rxOff;
        $statusverify = [
            'onverify' => $_rxOn,
            'offverify' => $_rxOff,
        ][$setting['verifystart']] ?? $_rxOff;
        $statuspvsupport = [
            'onpvsupport' => $_rxOn,
            'offpvsupport' => $_rxOff,
        ][$setting['statussupportpv']] ?? $_rxOff;
        $statusnameconfig = [
            'onnamecustom' => $_rxOn,
            'offnamecustom' => $_rxOff,
        ][$setting['statusnamecustom']] ?? $_rxOff;
        $statusnamebulk = [
            'onbulk' => $_rxOn,
            'offbulk' => $_rxOff,
        ][$setting['bulkbuy']] ?? $_rxOff;
        $statusverifybyuser = [
            'onverify' => $_rxOn,
            'offverify' => $_rxOff,
        ][$setting['verifybucodeuser']] ?? $_rxOff;
        $authScopeVal = (string)($setting['auth_scope'] ?? 'all');
        if ($authScopeVal === '') { $authScopeVal = 'all'; }
        $authScopeBtn = ($authScopeVal === 'newonly')
            ? "👥 محدوده احراز هویت: فقط کاربران جدید"
            : "👥 محدوده احراز هویت: همه کاربران";
        $score = [
            '1' => $_rxOn,
            '0' => $_rxOff,
        ][(string)($setting['scorestatus'] ?? '0')] ?? $_rxOff;
        $wheel_luck = [
            '1' => $_rxOn,
            '0' => $_rxOff,
        ][(string)($setting['wheelـluck'] ?? '0')] ?? $_rxOff;
        $refralstatus = [
            'onaffiliates' => $_rxOn,
            'offaffiliates' => $_rxOff,
        ][$setting['affiliatesstatus']] ?? $_rxOff;
        $btnstatuscategory = [
            '1' => $_rxOn,
            '0' => $_rxOff,
        ][(string)($setting['categoryhelp'] ?? '0')] ?? $_rxOff;
        $cronteststatustext        = ($status_cron['test']           ?? false) ? $_rxOn : $_rxOff;
        $crondaystatustext         = ($status_cron['day']            ?? false) ? $_rxOn : $_rxOff;
        $cronvolumestatustext      = ($status_cron['volume']         ?? false) ? $_rxOn : $_rxOff;
        $cronremovestatustext      = ($status_cron['remove']         ?? false) ? $_rxOn : $_rxOff;
        $cronremovevolumestatustext= ($status_cron['remove_volume']  ?? false) ? $_rxOn : $_rxOff;
        $cronuptime_nodestatustext = ($status_cron['uptime_node']    ?? false) ? $_rxOn : $_rxOff;
        $cronuptime_panelstatustext= ($status_cron['uptime_panel']   ?? false) ? $_rxOn : $_rxOff;
        $cronon_holdtext           = ($status_cron['on_hold']        ?? false) ? $_rxOn : $_rxOff;
        $languagestatus = (((string)($setting['languageen'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $languagestatusru = (((string)($setting['languageru'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $wheelagent = (((string)($setting['wheelagent'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $Lotteryagent = (((string)($setting['Lotteryagent'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $statusfirstwheel = (((string)($setting['statusfirstwheel'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $statuslimitchangeloc = (((string)($setting['statuslimitchangeloc'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $statusDebtsettlement = (((string)($setting['Debtsettlement'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $statusDice = (((string)($setting['Dice'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $statusnotef = (((string)($setting['statusnoteforf'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $status_copy_cart = (((string)($setting['statuscopycart'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $keyboard_config_text = (((string)($setting['status_keyboard_config'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $forced_miniapp_status = (((string)($setting['forced_miniapp_mode'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
        $cardVerifyStatusVal = (string)($setting['card_verify_status'] ?? 'offcardverify');
        $statusCardVerify = ($cardVerifyStatusVal === 'oncardverify') ? $_rxOn : $_rxOff;
        $cardVerifyScopeVal = (string)($setting['card_verify_scope'] ?? 'all');
        $cardVerifyScopeBtn = ($cardVerifyScopeVal === 'newonly')
            ? "💳 محدوده احراز کارت: فقط کاربران جدید"
            : "💳 محدوده احراز کارت: همه کاربران";

        $infocardStatusRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "infocard_status", "select");
        $infocardStatusValue = (is_array($infocardStatusRow) && isset($infocardStatusRow['value']))
            ? (string)$infocardStatusRow['value'] : '0';
        $infocardColorRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "infocard_color", "select");
        $infocardColorValue = (is_array($infocardColorRow) && isset($infocardColorRow['value']))
            ? (string)$infocardColorRow['value'] : 'yellow';
        $infocardStatusText = ($infocardStatusValue === '1') ? $_rxOn : $_rxOff;
        $infocardColorEmojiMap = [
            'yellow' => '🟡', 'green' => '🟢', 'red' => '🔴',
            'blue'   => '🔵', 'purple' => '🟣', 'orange' => '🟠'
        ];
        $infocardColorEmoji = $infocardColorEmojiMap[$infocardColorValue] ?? '🟡';

        $randomWalletStatusRow = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "randomwallet_status", "select");
        $randomWalletStatusValue = is_array($randomWalletStatusRow) ? (string)($randomWalletStatusRow['ValuePay'] ?? '0') : '0';
        $randomWalletStatusText = ($randomWalletStatusValue === '1') ? $_rxOn : $_rxOff;

        $premiumEmojiStatusValue = (string)($setting['premium_emoji_status'] ?? '0');
        $premiumEmojiStatusText = ($premiumEmojiStatusValue === '1') ? $_rxOn : $_rxOff;

        $rxFeatTypeCatMap = [

            'statusbot' => 'bot', 'role' => 'bot',
            'get_number' => 'bot', 'Authenticationiran' => 'bot',
            'verifystart' => 'bot', 'verifybyuser' => 'bot', 'authscope' => 'bot',
            'cardverify' => 'bot',
            'inlinebtnmain' => 'bot', 'forced_miniapp' => 'bot',

            'usernamebtn' => 'users', 'notifnew' => 'users', 'showagent' => 'users',
            'statussupportpv' => 'users', 'statusnamecustom' => 'users', 'statusnamecustomf' => 'users',

            'bulkbuy' => 'shop', 'btn_status_category' => 'shop', 'keyconfig' => 'shop',
            'compycart' => 'shop', 'Debtsettlement' => 'shop', 'changeloc' => 'shop',
            'infocard' => 'shop', 'randomwallet' => 'shop',

            'wheelagent' => 'lottery', 'wheelagentfirst' => 'lottery', 'wheel_luck' => 'lottery',
            'Lotteryagent' => 'lottery', 'score' => 'lottery', 'affiliatesstatus' => 'lottery',
            'Dice' => 'lottery',

            'crontest' => 'crons', 'uptime_node' => 'crons', 'uptime_panel' => 'crons',
            'cronday' => 'crons', 'on_hold' => 'crons', 'cronvolume' => 'crons',
            'notifremove' => 'crons', 'notifremove_volume' => 'crons',
        ];
        $rxFeatTargetCat = $rxFeatTypeCatMap[$type] ?? 'main';
        $rxPostTglPemCount = 0;
        try {
            $rxPostTglPemStmt = $pdo->query("SELECT COUNT(*) AS c FROM premium_emojis");
            if ($rxPostTglPemStmt) {
                $rxPostTglPemRow = $rxPostTglPemStmt->fetch(PDO::FETCH_ASSOC);
                $rxPostTglPemCount = (int)($rxPostTglPemRow['c'] ?? 0);
            }
        } catch (\Throwable $rxPostTglPemErr) { $rxPostTglPemCount = 0; }
        $rxPostTglPremiumLabel = "🌟 ایموجی پرمیوم" . ($rxPostTglPemCount > 0 ? " ({$rxPostTglPemCount})" : "");


        $titles = [
            'bot' => '🤖 <b>آپشن‌های اصلی ربات</b>',
            'users' => '👥 <b>کاربران و پشتیبانی</b>',
            'shop' => '🛍 <b>فروش و خدمات</b>',
            'lottery' => '🎁 <b>گردونه و قرعه‌کشی</b>',
            'crons' => '⏱ <b>کرون‌ها و زمان‌بندی</b>',
        ];
        if (isset($titles[$rxFeatTargetCat])) {
            $labels = compact(
                'name_status', 'name_status_role', 'Authenticationphone', 'Authenticationiran',
                'statusverify', 'statusverifybyuser', 'authScopeBtn', 'authScopeVal',
                'forced_miniapp_status', 'name_status_username', 'name_status_notifnewuser', 'name_status_showagent',
                'statuspvsupport', 'statusnameconfig', 'statusnotef', 'statusnamebulk',
                'btnstatuscategory', 'keyboard_config_text', 'status_copy_cart', 'statusDebtsettlement',
                'statuslimitchangeloc', 'infocardColorEmoji', 'infocardStatusText', 'infocardStatusValue',
                'randomWalletStatusText', 'randomWalletStatusValue', 'wheel_luck', 'statusfirstwheel',
                'wheelagent', 'score', 'Lotteryagent', 'refralstatus',
                'statusDice', 'cronteststatustext', 'cronuptime_nodestatustext', 'cronuptime_panelstatustext',
                'crondaystatustext', 'cronon_holdtext', 'cronvolumestatustext', 'cronremovestatustext',
                'cronremovevolumestatustext',
            );
            $rows = FeatureRowsHelpers::rxAdminBuildFeatureRows($rxFeatTargetCat, $textbotlang, $setting, $status_cron, $labels);
            $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'featcat_main']];
            return [$titles[$rxFeatTargetCat], $rows];
        }

        $rxFeatTitle = "📌 <b>وضعیت قابلیت‌ها</b>\n\n✅ تنظیم به‌روزرسانی شد.\n\nاز کدام دسته از قابلیت‌ها می‌خواهید استفاده کنید؟";
        $rxFeatRows = [
            [['text' => "🤖 آپشن‌های اصلی ربات",   'callback_data' => "featcat_bot"]],
            [['text' => "👥 کاربران و پشتیبانی",   'callback_data' => "featcat_users"]],
            [['text' => "🛍 فروش و خدمات",         'callback_data' => "featcat_shop"]],
            [['text' => "🎁 گردونه و قرعه‌کشی",    'callback_data' => "featcat_lottery"]],
            [['text' => "⏱ کرون‌ها و زمان‌بندی",   'callback_data' => "featcat_crons"]],
            [['text' => $rxPostTglPremiumLabel,    'callback_data' => "premium_emoji_settings"]],
            [['text' => "❌ بستن",                 'callback_data' => 'close_stat']],
        ];
        return [$rxFeatTitle, $rxFeatRows];
    }
}
