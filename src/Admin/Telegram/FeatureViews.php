<?php

namespace Faoxima\Admin\Telegram;

use PDO;

final class FeatureViews
{
    public static function rxRenderCronWorkerSettings($from_id, string $group = '', ?array $profile = null): void
    {
        $profile = $profile ?? \Faoxima\Core\Runtime\HostProfile::rx_host_profile();
        $settings = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('setting', '*') : [];
        $counts = \Faoxima\Core\Runtime\HostProfile::rx_cron_worker_counts($profile, is_array($settings) ? $settings : []);
        [$text, $rows] = self::rxCronWorkerSettingsView($counts, !empty($profile['manual_workers']), $group);
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text, json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE), 'HTML');
    }

    public static function rxCronWorkerSettingsView(array $counts, bool $manual, string $group): array
    {
        $broadcast = $counts['broadcast_workers'];
        $payment = $counts['payment_workers'];
        $text = "⚙️ <b>تنظیم ورکرهای کرون</b>\n\n"
            . "ابتدا «بهینه‌سازی هاست» را بزنید تا بر اساس بار قابل‌اندازه‌گیری CPU، حافظه و دیتابیس، تنظیم خودکار انجام شود.\n"
            . "برای سرعت بیشتر ارسال همگانی می‌توانید ورکر پخش را بین <b>۱ تا ۸</b> انتخاب کنید.\n"
            . "⚠️ اگر منابع هاست یا سرور کافی نباشد، ورکر بیشتر ممکن است ربات را کندتر کند.\n\n"
            . "📨 پخش و اعلان‌ها: <b>{$broadcast}</b>\n"
            . "💳 بررسی کارت و Plisio: <b>{$payment}</b>\n"
            . "حالت: <b>" . ($manual ? 'دارای تنظیم دستی' : 'خودکار') . "</b>\n\n"
            . "تغییر فوراً ذخیره می‌شود و پس از پایان نوبت در جریان، در اجرای بعدی آن کار اعمال می‌شود. سقف هم‌زمانی صف تغییر نمی‌کند.\n"
            . "اجرای دوبارهٔ بهینه‌ساز، تنظیم دستی را به حالت خودکار برمی‌گرداند.";
        $rows = [];
        if (in_array($group, ['broadcast', 'payment'], true)) {
            $current = $group === 'broadcast' ? $broadcast : $payment;
            $text .= "\n\n" . ($group === 'broadcast' ? 'تعداد ورکر پخش و اعلان‌ها را انتخاب کنید:' : 'تعداد ورکر بررسی کارت و Plisio را انتخاب کنید:');
            for ($start = 1; $start <= 8; $start += 4) {
                $row = [];
                for ($value = $start; $value < $start + 4; $value++) {
                    $row[] = ['text' => ($current === $value ? '✅ ' : '') . $value, 'callback_data' => "cronworkers_set_{$group}_{$value}"];
                }
                $rows[] = $row;
            }
            $rows[] = [['text' => '🔙 تنظیمات ورکر', 'callback_data' => 'cronworkers_settings']];
        } else {
            $rows[] = [['text' => "📨 ورکر پخش: {$broadcast}", 'callback_data' => 'cronworkers_pick_broadcast']];
            $rows[] = [['text' => "💳 ورکر پرداخت: {$payment}", 'callback_data' => 'cronworkers_pick_payment']];
        }
        $rows[] = [['text' => '🖥 بهینه‌سازی هاست / خودکار', 'callback_data' => 'run_host_optimizer']];
        $rows[] = [['text' => '🔙 کرون‌ها و زمان‌بندی', 'callback_data' => 'featcat_crons']];
        return [$text, $rows];
    }

    public static function rxRenderFeatureStatus($rxFeatView, $from_id) {
    global $textbotlang, $setting, $pdo, $status_cron,
        $name_status, $name_status_role, $Authenticationphone, $Authenticationiran,
        $statusverify, $statusverifybyuser, $authScopeBtn, $authScopeVal, $statusinline,
        $name_status_username, $name_status_notifnewuser, $name_status_showagent,
        $statuspvsupport, $statusnameconfig, $statusnotef,
        $statusnamebulk, $btnstatuscategory, $keyboard_config_text, $status_copy_cart,
        $statusDebtsettlement, $statuslimitchangeloc, $infocardColorEmoji, $infocardStatusText,
        $infocardStatusValue, $forced_miniapp_status,
        $wheel_luck, $statusfirstwheel, $wheelagent, $score, $Lotteryagent, $refralstatus, $statusDice,
        $cronteststatustext, $cronuptime_nodestatustext, $cronuptime_panelstatustext,
        $crondaystatustext, $cronon_holdtext, $cronvolumestatustext,
        $cronremovestatustext, $cronremovevolumestatustext,
        $randomWalletStatusText, $randomWalletStatusValue;

    if ($setting['Bot_Status'] == "✅  ربات روشن است") {
        \Faoxima\Core\Database\Db::update("setting", "Bot_Status", "botstatuson");
    } elseif ($setting['Bot_Status'] == "❌ ربات خاموش است") {
        \Faoxima\Core\Database\Db::update("setting", "Bot_Status", "botstatusoff");
    }
    if ($setting['roll_Status'] == "✅ تایید قانون روشن است") {
        \Faoxima\Core\Database\Db::update("setting", "roll_Status", "rolleon");
    } elseif ($setting['roll_Status'] == "❌ تایید قوانین خاموش است") {
        \Faoxima\Core\Database\Db::update("setting", "roll_Status", "rolleoff");
    }
    if ($setting['get_number'] == "✅ تایید شماره موبایل روشن است") {
        \Faoxima\Core\Database\Db::update("setting", "get_number", "onAuthenticationphone");
    } elseif ($setting['get_number'] == "❌ احرازهویت شماره تماس غیرفعال است") {
        \Faoxima\Core\Database\Db::update("setting", "get_number", "offAuthenticationphone");
    }
    if ($setting['iran_number'] == "✅ احرازشماره ایرانی روشن است") {
        \Faoxima\Core\Database\Db::update("setting", "iran_number", "onAuthenticationiran");
    } elseif ($setting['iran_number'] == "❌ بررسی شماره ایرانی غیرفعال است") {
        \Faoxima\Core\Database\Db::update("setting", "iran_number", "offAuthenticationiran");
    }
    $status_cron = \Faoxima\Core\Cron\Crontab::normalizeCronStatus($setting['cron_status'] ?? null, true);
    $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select");
    $name_status = [
        'botstatuson' => $textbotlang['Admin']['Status']['statuson'],
        'botstatusoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Bot_Status']];
    $name_status_username = [
        'onnotuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnotuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['NotUser']];
    $name_status_notifnewuser = [
        'onnewuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnewuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnewuser']];
    $name_status_showagent = [
        'onrequestagent' => $textbotlang['Admin']['Status']['statuson'],
        'offrequestagent' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusagentrequest']];
    $name_status_role = [
        'rolleon' => $textbotlang['Admin']['Status']['statuson'],
        'rolleoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['roll_Status']];
    $Authenticationphone = [
        'onAuthenticationphone' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationphone' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['get_number']];
    $Authenticationiran = [
        'onAuthenticationiran' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationiran' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['iran_number']];
    $statusinline = [
        'oninline' => $textbotlang['Admin']['Status']['statuson'],
        'offinline' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['inlinebtnmain']];
    $statusverify = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifystart']];
    $statuspvsupport = [
        'onpvsupport' => $textbotlang['Admin']['Status']['statuson'],
        'offpvsupport' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statussupportpv']];
    $statusnameconfig = [
        'onnamecustom' => $textbotlang['Admin']['Status']['statuson'],
        'offnamecustom' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnamecustom']];
    $statusnamebulk = [
        'onbulk' => $textbotlang['Admin']['Status']['statuson'],
        'offbulk' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['bulkbuy']];
    $statusverifybyuser = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifybucodeuser']];
    $authScopeVal = (string)($setting['auth_scope'] ?? 'all');
    if ($authScopeVal === '') { $authScopeVal = 'all'; }
    $authScopeBtn = ($authScopeVal === 'newonly')
        ? "👥 محدوده احراز هویت: فقط کاربران جدید"
        : "👥 محدوده احراز هویت: همه کاربران";
    $score = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['scorestatus']];
    $wheel_luck = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelـluck']];
    $refralstatus = [
        'onaffiliates' => $textbotlang['Admin']['Status']['statuson'],
        'offaffiliates' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['affiliatesstatus']];
    $btnstatuscategory = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['categoryhelp']];
    $cronteststatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['test']];
    $crondaystatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['day']];
    $cronvolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['volume']];
    $cronremovestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove']];
    $cronremovevolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove_volume']];
    $cronuptime_nodestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_node']];
    $cronuptime_panelstatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_panel']];
    $cronon_holdtext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['on_hold']];
    $wheelagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelagent']];
    $Lotteryagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Lotteryagent']];
    $statusfirstwheel = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusfirstwheel']];
    $statuslimitchangeloc = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuslimitchangeloc']];
    $statusDebtsettlement = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Debtsettlement']];
    $statusDice = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Dice']];
    $statusnotef = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnoteforf']];
    $status_copy_cart = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscopycart']];
    $keyboard_config_text = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['status_keyboard_config']];
    $forced_miniapp_status = (((string)($setting['forced_miniapp_mode'] ?? '0')) === '1')
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];

    $randomWalletStatusRow = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "randomwallet_status", "select");
    $randomWalletStatusValue = is_array($randomWalletStatusRow) ? (string)($randomWalletStatusRow['ValuePay'] ?? '0') : '0';
    $randomWalletStatusText = $randomWalletStatusValue === '1'
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];

    $infocardStatusRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "infocard_status", "select");
    $infocardStatusValue = (is_array($infocardStatusRow) && isset($infocardStatusRow['value']))
        ? (string)$infocardStatusRow['value'] : '0';
    $infocardColorRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $infocardColorValue = (is_array($infocardColorRow) && isset($infocardColorRow['value']))
        ? (string)$infocardColorRow['value'] : 'yellow';
    $infocardStatusText = $infocardStatusValue === '1'
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $infocardColorEmojiMap = [
        'yellow' => '🟡', 'green' => '🟢', 'red' => '🔴',
        'blue'   => '🔵', 'purple' => '🟣', 'orange' => '🟠'
    ];
    $infocardColorEmoji = $infocardColorEmojiMap[$infocardColorValue] ?? '🟡';

    $premiumEmojiCount = 0;
    try {
        $rxPemRow = $pdo->query("SELECT COUNT(*) AS c FROM premium_emojis WHERE custom_emoji_id IS NOT NULL AND custom_emoji_id <> ''")->fetch(PDO::FETCH_ASSOC);
        $premiumEmojiCount = (int)($rxPemRow['c'] ?? 0);
    } catch (\Throwable $rxPemErr) { $premiumEmojiCount = 0; }

    $rxAsStatusValue = (string)($setting['antispam_status'] ?? '0');
    $rxAsStatusText  = ($rxAsStatusValue === '1')
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $rxAsMsgCountVal = (int)($setting['antispam_msg_count'] ?? 5);
    if ($rxAsMsgCountVal < 1)    { $rxAsMsgCountVal = 1; }
    if ($rxAsMsgCountVal > 1000) { $rxAsMsgCountVal = 1000; }
    $rxAsSecondsVal = (int)($setting['antispam_seconds'] ?? 3);
    if ($rxAsSecondsVal < 1)    { $rxAsSecondsVal = 1; }
    if ($rxAsSecondsVal > 3600) { $rxAsSecondsVal = 3600; }
    $rxAsMuteSecondsVal = (int)($setting['antispam_mute_seconds'] ?? 5);
    if ($rxAsMuteSecondsVal < 1)     { $rxAsMuteSecondsVal = 1; }
    if ($rxAsMuteSecondsVal > 86400) { $rxAsMuteSecondsVal = 86400; }

    $rxBackRow = [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main']];

    if ($rxFeatView === 'main') {
        $rxFeatKb = [
            [['text' => "🤖 آپشن‌های اصلی ربات",       'callback_data' => 'featcat_bot']],
            [['text' => "👥 کاربران و پشتیبانی",        'callback_data' => 'featcat_users']],
            [['text' => "🛍 فروش و خدمات",              'callback_data' => 'featcat_shop']],
            [['text' => "🎁 گردونه و قرعه‌کشی",          'callback_data' => 'featcat_lottery']],
            [['text' => "⏱ کرون‌ها و زمان‌بندی",          'callback_data' => 'featcat_crons']],
            [['text' => "🌟 ایموجی پرمیوم ({$premiumEmojiCount})", 'callback_data' => 'premium_emoji_settings']],
            [['text' => "🛡 آنتی اسپم", 'callback_data' => 'featcat_antispam']],
            [['text' => "🖥 بهینه‌ساز هاست اشتراکی", 'callback_data' => 'run_host_optimizer']],
            [['text' => "🧠 وضعیت Redis", 'callback_data' => 'run_redis_status']],
            [['text' => "🔙 بازگشت", 'callback_data' => 'admin_settings'],
             ['text' => "❌ بستن", 'callback_data' => 'close_stat']],
        ];
        $rxFeatTitle = "📌 <b>وضعیت قابلیت‌ها</b>\n\nاز کدام دسته از قابلیت‌ها می‌خواهید استفاده کنید؟\n\n💡 برای تنظیمات هر بخش، روی دکمه دسته بزنید.";
    } elseif ($rxFeatView === 'bot') {
        $rxFeatKb = \Faoxima\Admin\Keyboard\FeatureRowsHelpers::rx_featCategoryRows('bot');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "🤖 <b>آپشن‌های اصلی ربات</b>\n\nقابلیت‌های اصلی ربات را در اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'users') {
        $rxFeatKb = \Faoxima\Admin\Keyboard\FeatureRowsHelpers::rx_featCategoryRows('users');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "👥 <b>کاربران و پشتیبانی</b>\n\nقابلیت‌های مربوط به کاربران، اعلان‌ها و پشتیبانی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'shop') {
        $rxFeatKb = \Faoxima\Admin\Keyboard\FeatureRowsHelpers::rx_featCategoryRows('shop');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "🛍 <b>فروش و خدمات</b>\n\nقابلیت‌های فروشگاه، کانفیگ و خدمات جانبی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'lottery') {
        $rxFeatKb = \Faoxima\Admin\Keyboard\FeatureRowsHelpers::rx_featCategoryRows('lottery');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "🎁 <b>گردونه و قرعه‌کشی</b>\n\nقابلیت‌های گردونه شانس، قرعه‌کشی و زیرمجموعه را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'crons') {
        $rxFeatKb = \Faoxima\Admin\Keyboard\FeatureRowsHelpers::rx_featCategoryRows('crons');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "⏱ <b>کرون‌ها و زمان‌بندی</b>\n\nقابلیت‌های مربوط به کرون‌ها (cronjobs) و زمان‌بندی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'antispam') {
        $rxFeatKb = [
            [['text' => $rxAsStatusText, 'callback_data' => "antispam_toggle"],
             ['text' => "🛡 وضعیت آنتی اسپم", 'callback_data' => "antispam_noop"]],
            [['text' => (string)$rxAsMsgCountVal, 'callback_data' => "antispam_set_count"],
             ['text' => "✉️ تعداد پیام مجاز", 'callback_data' => "antispam_noop"]],
            [['text' => (string)$rxAsSecondsVal, 'callback_data' => "antispam_set_seconds"],
             ['text' => "⏱ بازه (ثانیه)", 'callback_data' => "antispam_noop"]],
            [['text' => (string)$rxAsMuteSecondsVal, 'callback_data' => "antispam_set_mute"],
             ['text' => "🔇 آف بودن (ثانیه)", 'callback_data' => "antispam_noop"]],
            $rxBackRow,
        ];
        $rxFeatTitle = "🛡 <b>آنتی اسپم</b>\n\n"
            . "محدودیت ارسال پیام برای جلوگیری از اسپم.\n\n"
            . "📌 <b>تنظیمات فعلی:</b>\n"
            . "• وضعیت: {$rxAsStatusText}\n"
            . "• تعداد پیام مجاز: <b>{$rxAsMsgCountVal}</b> پیام\n"
            . "• بازه زمانی: هر <b>{$rxAsSecondsVal}</b> ثانیه\n"
            . "• مدت آف بودن پس از تخلف: <b>{$rxAsMuteSecondsVal}</b> ثانیه\n\n"
            . "💡 اگر کاربری بیشتر از {$rxAsMsgCountVal} پیام در {$rxAsSecondsVal} ثانیه ارسال کند، ربات برای {$rxAsMuteSecondsVal} ثانیه به او پاسخ نمی‌دهد.\n"
            . "پس از پایان این مدت، با ارسال مجدد /start کاربر مجدداً پاسخ می‌گیرد.\n\n"
            . "ℹ️ <b>توجه:</b> این محدودیت فقط برای کاربران معمولی اعمال می‌شود. ادمین‌ها هرگز محدود نمی‌شوند.";
    } elseif ($rxFeatView === 'redis') {
        $rxRedisExtOk = method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_extension_available') && \Faoxima\Core\Cache\RedisCache::rx_redis_extension_available();
        $rxRedisAdminOn = (string)($setting['redis_enabled'] ?? '0') === '1';
        $rxRedisReachable = $rxRedisExtOk && method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_is_active') && \Faoxima\Core\Cache\RedisCache::rx_redis_is_active();
        $rxRedisCooldown = method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_cooldown_remaining') ? \Faoxima\Core\Cache\RedisCache::rx_redis_cooldown_remaining() : 0;

        $rxRedisExtText = $rxRedisExtOk ? "✅ فعال" : "❌ غیرفعال";
        $rxRedisReachText = $rxRedisReachable ? "✅ متصل" : "❌ متصل نیست";
        $rxRedisAdminText = $rxRedisAdminOn ? "✅ فعال" : "❌ غیرفعال";
        $rxRedisToggleLabel = $rxRedisAdminOn ? "✅ فعال" : "❌ غیرفعال";

        $rxFeatKb = [
            [['text' => $rxRedisToggleLabel, 'callback_data' => "editstsuts-redis_enabled-" . ($rxRedisAdminOn ? '1' : '0')],
             ['text' => "🧠 فعال‌سازی Redis", 'callback_data' => "none"]],
            [['text' => "🧪 تست خواندن/نوشتن", 'callback_data' => "run_redis_test"]],
            $rxBackRow,
        ];

        $rxFeatTitle = "🧠 <b>وضعیت Redis</b>\n\n"
            . "📌 <b>وضعیت فعلی:</b>\n"
            . "• افزونه PHP: {$rxRedisExtText}\n"
            . "• اتصال زنده: {$rxRedisReachText}\n"
            . "• فعال‌سازی توسط ادمین: {$rxRedisAdminText}\n";
        if ($rxRedisCooldown > 0) {
            $rxFeatTitle .= "• ⏳ در حالت استراحت پس از خطای اتصال — {$rxRedisCooldown} ثانیه دیگر دوباره تلاش می‌شود.\n";
        }
        $rxFeatTitle .= "\n💡 Redis فقط برای کش، Session موقت، Rate Limit و صف استفاده می‌شود؛ جایگزین دیتابیس اصلی نیست.";
    } elseif ($rxFeatView === 'glass_mode') {
        $rxGlassOn = (string)($setting['inlinebtnmain'] ?? 'offinline') === 'oninline';
        $rxAutoRemoveOn = (string)($setting['auto_remove_reply_keyboard'] ?? 'on') !== 'off';
        $rxRichOn = (string)($setting['user_rich_buttons_enabled'] ?? 'off') === 'on';

        $rxFeatKb = [
            [['text' => $rxGlassOn ? $textbotlang['Admin']['Status']['statuson'] : $textbotlang['Admin']['Status']['statusoff'],
              'callback_data' => "editstsuts-inlinebtnmain-" . ($setting['inlinebtnmain'] ?? 'offinline')],
             ['text' => "🛡 حالت شیشه‌ای بخش کاربر", 'callback_data' => "none"]],
            [['text' => $rxAutoRemoveOn ? $textbotlang['Admin']['Status']['statuson'] : $textbotlang['Admin']['Status']['statusoff'],
              'callback_data' => "editstsuts-auto_remove_kb-" . ($rxAutoRemoveOn ? 'on' : 'off')],
             ['text' => "🧹 حذف خودکار Reply Keyboard", 'callback_data' => "none"]],
            [['text' => $rxRichOn ? $textbotlang['Admin']['Status']['statuson'] : $textbotlang['Admin']['Status']['statusoff'],
              'callback_data' => "editstsuts-user_rich_buttons-" . ($rxRichOn ? 'on' : 'off')],
             ['text' => "دکمه داخل متن پیام", 'callback_data' => "none"]],
            [['text' => "تنظیمات داخلی متن", 'callback_data' => "richsec_menu"]],
            $rxBackRow,
        ];

        $rxFeatTitle = "🛡 <b>تنظیمات حالت شیشه‌ای بخش کاربر</b>\n\n"
            . "📌 <b>وضعیت فعلی:</b>\n"
            . "• حالت شیشه‌ای بخش کاربر: " . ($rxGlassOn ? "✅ روشن" : "❌ خاموش") . "\n"
            . "• حذف خودکار Reply Keyboard: " . ($rxAutoRemoveOn ? "✅ روشن" : "❌ خاموش") . "\n"
            . "• دکمه داخل متن پیام: " . ($rxRichOn ? "✅ روشن" : "❌ خاموش") . "\n"
            . "\n"
            . "💡 این تنظیم فقط روی بخش‌های کاربری (خرید، تمدید، کیف پول، سرویس‌ها و منوهای مشابه) اثر دارد.\n"
            . "اگر «حالت شیشه‌ای بخش کاربر» خاموش باشد، منوهای کاربری به‌صورت Reply Keyboard عادی نمایش داده می‌شوند.\n"
            . "اگر «حالت شیشه‌ای بخش کاربر» روشن باشد، منوهای کاربری Inline نمایش داده می‌شوند و «حذف خودکار Reply Keyboard» مشخص می‌کند که کیبورد قبلی به‌طور خودکار پاک شود یا نه.\n"
            . "پنل مدیریت و منوهای ادمین همیشه به‌صورت Inline نمایش داده می‌شوند و از این تنظیم پیروی نمی‌کنند.\n"
            . "اگر «دکمه داخل متن پیام» روشن باشد، دکمه‌های کاربران داخل خود پیام نمایش داده می‌شوند. این حالت می‌تواند هم‌زمان با «حالت شیشه‌ای بخش کاربر» روشن باشد؛ بخش‌هایی که در «تنظیمات داخلی متن» انتخاب شده‌اند با دکمه داخل متن و بقیه با حالت عادی نمایش داده می‌شوند.";
    } else {
        return false;
    }
    $Bot_Status = json_encode(['inline_keyboard' => $rxFeatKb]);
    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxFeatTitle, $Bot_Status, 'HTML');
    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'featcat_' . $rxFeatView);
    }
    return true;
}
}
