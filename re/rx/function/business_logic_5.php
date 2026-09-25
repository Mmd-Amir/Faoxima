<?php



function nm_sendInfoCardsForServiceList($from_id, array $services)
{
    if (!function_exists('nm_renderInfoCardForInvoice') || !function_exists('telegram')) {
        return;
    }
    if (empty($services)) {
        return;
    }


    $cap = defined('INFOCARD_LIST_MAX') ? (int) INFOCARD_LIST_MAX : 8;
    $sent = 0;
    foreach ($services as $row) {
        if ($sent >= $cap) {
            break;
        }
        if (!is_array($row) || empty($row['username']) || empty($row['id_invoice'])) {
            continue;
        }
        $panel = select("marzban_panel", "*", "name_panel", $row['Service_location'] ?? '', "select");
        if (!is_array($panel)) {
            continue;
        }
        $cardPath = nm_renderInfoCardForInvoice($panel, $row['username'], $row['id_invoice'], $from_id);
        if ($cardPath === null) {
            if (function_exists('nm_sendServiceQrFallback')) {
                global $ManagePanel;
                $subLink = '';
                if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'DataUser')) {
                    $data = @$ManagePanel->DataUser($row['Service_location'] ?? '', $row['username']);
                    if (is_array($data)) {
                        $subLink = (string)($data['subscription_url'] ?? '');
                    }
                }
                if ($subLink !== '') {
                    nm_sendServiceQrFallback($from_id, $subLink);
                }
            }
            continue;
        }
        $note = isset($row['note']) && $row['note'] !== '' ? ' | ' . $row['note'] : '';
        $caption = '✨ <b>' . htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') . '</b>'
            . htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
        $__kbButtons = [['text' => '🔧 مدیریت سرویس', 'callback_data' => 'product_' . $row['id_invoice']]];
        if (!function_exists('isQrDisabled') || !isQrDisabled()) {
            $__kbButtons[] = ['text' => faoxima_textbot_get('dyn_purchase_qr_code_btn', '📷 دریافت QR Code'), 'callback_data' => 'infocard_qr_' . $row['id_invoice']];
        }
        $kb = json_encode(['inline_keyboard' => [$__kbButtons]], JSON_UNESCAPED_UNICODE);
        try {
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo' => new CURLFile($cardPath),
                'reply_markup' => $kb,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);
            $sent++;
        } catch (\Throwable $e) {
            error_log('nm_sendInfoCardsForServiceList send failed: ' . $e->getMessage());
        }
        @unlink($cardPath);
    }
}


function rxRenderFeatureStatus($rxFeatView, $from_id) {
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
        update("setting", "Bot_Status", "botstatuson");
    } elseif ($setting['Bot_Status'] == "❌ ربات خاموش است") {
        update("setting", "Bot_Status", "botstatusoff");
    }
    if ($setting['roll_Status'] == "✅ تایید قانون روشن است") {
        update("setting", "roll_Status", "rolleon");
    } elseif ($setting['roll_Status'] == "❌ تایید قوانین خاموش است") {
        update("setting", "roll_Status", "rolleoff");
    }
    if ($setting['get_number'] == "✅ تایید شماره موبایل روشن است") {
        update("setting", "get_number", "onAuthenticationphone");
    } elseif ($setting['get_number'] == "❌ احرازهویت شماره تماس غیرفعال است") {
        update("setting", "get_number", "offAuthenticationphone");
    }
    if ($setting['iran_number'] == "✅ احرازشماره ایرانی روشن است") {
        update("setting", "iran_number", "onAuthenticationiran");
    } elseif ($setting['iran_number'] == "❌ بررسی شماره ایرانی غیرفعال است") {
        update("setting", "iran_number", "offAuthenticationiran");
    }
    $status_cron = normalizeCronStatus($setting['cron_status'] ?? null, true);
    $setting = select("setting", "*", null, null, "select");
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

    $randomWalletStatusRow = select("PaySetting", "ValuePay", "NamePay", "randomwallet_status", "select");
    $randomWalletStatusValue = is_array($randomWalletStatusRow) ? (string)($randomWalletStatusRow['ValuePay'] ?? '0') : '0';
    $randomWalletStatusText = $randomWalletStatusValue === '1'
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];

    $infocardStatusRow = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
    $infocardStatusValue = (is_array($infocardStatusRow) && isset($infocardStatusRow['value']))
        ? (string)$infocardStatusRow['value'] : '0';
    $infocardColorRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
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
        $rxFeatKb = rx_featCategoryRows('bot');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "🤖 <b>آپشن‌های اصلی ربات</b>\n\nقابلیت‌های اصلی ربات را در اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'users') {
        $rxFeatKb = rx_featCategoryRows('users');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "👥 <b>کاربران و پشتیبانی</b>\n\nقابلیت‌های مربوط به کاربران، اعلان‌ها و پشتیبانی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'shop') {
        $rxFeatKb = rx_featCategoryRows('shop');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "🛍 <b>فروش و خدمات</b>\n\nقابلیت‌های فروشگاه، کانفیگ و خدمات جانبی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'lottery') {
        $rxFeatKb = rx_featCategoryRows('lottery');
        $rxFeatKb[] = $rxBackRow;
        $rxFeatTitle = "🎁 <b>گردونه و قرعه‌کشی</b>\n\nقابلیت‌های گردونه شانس، قرعه‌کشی و زیرمجموعه را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'crons') {
        $rxFeatKb = rx_featCategoryRows('crons');
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
        $rxRedisExtOk = function_exists('rx_redis_extension_available') && rx_redis_extension_available();
        $rxRedisAdminOn = (string)($setting['redis_enabled'] ?? '0') === '1';
        $rxRedisReachable = $rxRedisExtOk && function_exists('rx_redis_is_active') && rx_redis_is_active();
        $rxRedisCooldown = function_exists('rx_redis_cooldown_remaining') ? rx_redis_cooldown_remaining() : 0;

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

        $rxFeatKb = [
            [['text' => $rxGlassOn ? $textbotlang['Admin']['Status']['statuson'] : $textbotlang['Admin']['Status']['statusoff'],
              'callback_data' => "editstsuts-inlinebtnmain-" . ($setting['inlinebtnmain'] ?? 'offinline')],
             ['text' => "🛡 شیشه‌ای بودن دکمه ربات", 'callback_data' => "none"]],
            [['text' => $rxAutoRemoveOn ? $textbotlang['Admin']['Status']['statuson'] : $textbotlang['Admin']['Status']['statusoff'],
              'callback_data' => "editstsuts-auto_remove_kb-" . ($rxAutoRemoveOn ? 'on' : 'off')],
             ['text' => "🧹 حذف خودکار Reply Keyboard", 'callback_data' => "none"]],
            $rxBackRow,
        ];

        $rxFeatTitle = "🛡 <b>تنظیمات دکمه شیشه‌ای</b>\n\n"
            . "📌 <b>وضعیت فعلی:</b>\n"
            . "• شیشه‌ای بودن دکمه ربات: " . ($rxGlassOn ? "✅ روشن" : "❌ خاموش") . "\n"
            . "• حذف خودکار Reply Keyboard: " . ($rxAutoRemoveOn ? "✅ روشن" : "❌ خاموش") . "\n\n"
            . "💡 اگر «شیشه‌ای بودن» خاموش باشد، منوها به‌صورت Reply Keyboard عادی نمایش داده می‌شوند.\n"
            . "اگر «شیشه‌ای بودن» روشن باشد، منوها Inline نمایش داده می‌شوند و «حذف خودکار Reply Keyboard» مشخص می‌کند که کیبورد قبلی به‌طور خودکار پاک شود یا نه.";
    } else {
        return false;
    }
    $Bot_Status = json_encode(['inline_keyboard' => $rxFeatKb]);
    nm_adminInstantReply($from_id, $rxFeatTitle, $Bot_Status, 'HTML');
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'featcat_' . $rxFeatView);
    }
    return true;
}

function rxRenderPremiumEmojiPanel($from_id, $page = 1) {
    global $pdo, $setting;
    $pageSize = 10;
    $page = max(1, (int)$page);

    $rows = [];
    try {
        $stmt = $pdo->query("SELECT id, emoji, custom_emoji_id FROM premium_emojis ORDER BY id ASC");
        if ($stmt) {
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $rows[] = $r; }
        }
    } catch (Throwable $e) { $rows = []; }

    $total = count($rows);
    $totalPages = max(1, (int)ceil($total / $pageSize));
    if ($page > $totalPages) { $page = $totalPages; }
    $sliceStart = ($page - 1) * $pageSize;
    $pageItems = array_slice($rows, $sliceStart, $pageSize);

    $statusVal = (string)($setting['premium_emoji_status'] ?? '0');
    $statusText = ($statusVal === '1') ? "☑️ فعال" : "🚫 غیرفعال";

    $msg  = "🌟 <b>تنظیمات ایموجی پرمیوم</b>\n\n";
    $msg .= "وضعیت کلی: <b>{$statusText}</b>\n";
    $msg .= "تعداد ثبت‌شده: <b>{$total}</b>";
    if ($totalPages > 1) {
        $msg .= "  ·  📄 صفحه <b>{$page}</b> از <b>{$totalPages}</b>";
    }
    $msg .= "\n\n";
    if (empty($rows)) {
        $msg .= "📭 هنوز هیچ ایموجی‌ای ثبت نشده است.\n\n";
        $msg .= "برای افزودن، روی دکمه «➕ افزودن ایموجی جدید» بزنید.";
    } else {
        $msg .= "برای ویرایش/حذف، از دکمه‌های زیر استفاده کنید.";
    }

    $kb = ['inline_keyboard' => []];
    foreach ($pageItems as $it) {
        $em = (string)$it['emoji'];
        $id = (int)$it['id'];
        $isValid = function_exists('isValidPremiumEmojiSource') ? isValidPremiumEmojiSource($em) : true;
        $btnLabel = $isValid ? $em : "⚠️ نامعتبر: " . mb_substr($em, 0, 15, 'UTF-8');

        $kb['inline_keyboard'][] = [
            ['text' => $btnLabel, 'callback_data' => "premium_emoji_noop"],
            ['text' => "✏️ ویرایش", 'callback_data' => "premium_emoji_edit_{$id}"],
            ['text' => "🗑 حذف", 'callback_data' => "premium_emoji_del_{$id}"],
        ];
    }
    if ($totalPages > 1) {
        $prevCb = ($page > 1) ? "premium_emoji_settings_" . ($page - 1) : "premium_emoji_noop";
        $prevTxt = ($page > 1) ? "« قبل" : "▫️";
        $nextCb = ($page < $totalPages) ? "premium_emoji_settings_" . ($page + 1) : "premium_emoji_noop";
        $nextTxt = ($page < $totalPages) ? "بعد »" : "▫️";
        $kb['inline_keyboard'][] = [
            ['text' => $prevTxt, 'callback_data' => $prevCb],
            ['text' => "📄 {$page} / {$totalPages}", 'callback_data' => "premium_emoji_noop"],
            ['text' => $nextTxt, 'callback_data' => $nextCb],
        ];
    }
    $kb['inline_keyboard'][] = [
        ['text' => "➕ افزودن ایموجی جدید", 'callback_data' => "premium_emoji_add"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => "🔍 شناسایی خودکار ایموجی‌های ساده", 'callback_data' => "premium_emoji_scan"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => "🗑 حذف همگانی همه ایموجی‌ها", 'callback_data' => "premium_emoji_del_all"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => ($statusVal === '1' ? "🚫 خاموش کردن قابلیت" : "☑️ روشن کردن قابلیت"),
         'callback_data' => "editstsuts-premiumemoji-{$statusVal}"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => "🔙 بازگشت", 'callback_data' => "featcat_main"],
        ['text' => "🔄 رفرش", 'callback_data' => "premium_emoji_settings_{$page}"],
        ['text' => "❌ بستن", 'callback_data' => "close_stat"],
    ];


    global $callback_query_id, $message_id;
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'premium_emoji');
    }
    $kbJson = json_encode($kb, JSON_UNESCAPED_UNICODE);
    $isCallback = !empty($callback_query_id) && !empty($message_id);
    $delivered = false;

    if ($isCallback && function_exists('Editmessagetext')) {
        try {
            $editResult = Editmessagetext($from_id, $message_id, $msg, $kbJson, 'HTML');
            if (is_array($editResult) && !empty($editResult['ok'])) {
                $delivered = true;
                if (function_exists('telegram')) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time'        => 0,
                        ]);
                    } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {
            error_log('rxRenderPremiumEmojiPanel Editmessagetext failed: ' . $e->getMessage());
        }
    }


    if (!$delivered && function_exists('telegram')) {
        if ($isCallback) {
            try {
                $rawEdit = telegram('editmessagetext', [
                    'chat_id'      => $from_id,
                    'message_id'   => $message_id,
                    'text'         => $msg,
                    'reply_markup' => $kbJson,
                    'parse_mode'   => 'HTML',
                ]);
                if (is_array($rawEdit) && !empty($rawEdit['ok'])) {
                    $delivered = true;
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time'        => 0,
                        ]);
                    } catch (Throwable $e) {}
                }
            } catch (Throwable $e) {}
        }
        if (!$delivered) {
            if ($isCallback && function_exists('deletemessage')) {
                try { @deletemessage($from_id, $message_id); } catch (Throwable $e) {}
            }
            try {
                telegram('sendmessage', [
                    'chat_id'      => $from_id,
                    'text'         => $msg,
                    'reply_markup' => $kbJson,
                    'parse_mode'   => 'HTML',
                ]);
            } catch (Throwable $e) {
                error_log('rxRenderPremiumEmojiPanel send failed: ' . $e->getMessage());
            }
        }
    }
}
if (!function_exists('crypto_supported_currencies')) {
    function crypto_supported_currencies(): array
    {
        return [
            'TRX'        => ['network' => 'TRON', 'decimals' => 6, 'label' => 'ترون (TRX)',           'fa_short' => 'ترون'],
            'TON'        => ['network' => 'TON',  'decimals' => 9, 'label' => 'تون (TON)',            'fa_short' => 'تون'],
            'USDT_TRC20' => ['network' => 'TRON', 'decimals' => 6, 'label' => 'تتر روی شبکه ترون',     'fa_short' => 'تتر-ترون'],
            'USDT_TON'   => ['network' => 'TON',  'decimals' => 6, 'label' => 'تتر روی شبکه تون',      'fa_short' => 'تتر-تون'],
        ];
    }
}

if (!function_exists('crypto_pay_setting')) {
    function crypto_pay_setting(string $name, string $default = ''): string
    {
        $row = function_exists('select') ? select('PaySetting', 'ValuePay', 'NamePay', $name, 'select') : null;
        if (is_array($row) && isset($row['ValuePay'])) {
            $v = trim((string) $row['ValuePay']);
            if ($v !== '') return $v;
        }
        return $default;
    }
}

if (!function_exists('crypto_extract_hash')) {


    function crypto_extract_hash($input): ?string
    {
        if (!is_string($input)) return null;
        $input = trim($input);
        if ($input === '') return null;

        $input = preg_replace('/\s+/u', ' ', $input);
        $input = str_replace(["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], '', (string) $input);
        $input = trim((string) $input);


        if (preg_match('~[a-z0-9.-]+\.[a-z]{2,}[^\s]*?/(?:tx|transaction|transactions|events)/(0x[0-9a-fA-F]{64}|[0-9a-fA-F]{64})~i', $input, $m)) {
            return strtolower($m[1]);
        }


        if (preg_match('/\b0x[0-9a-fA-F]{64}\b/', $input, $m)) {
            return strtolower($m[0]);
        }


        if (preg_match('/\b[0-9a-fA-F]{64}\b/', $input, $m)) {
            return strtolower($m[0]);
        }


        if (preg_match('~[a-z0-9.-]+\.[a-z]{2,}[^\s]*?/(?:tx|transaction|transactions|events)/([A-Za-z0-9_\-+/=]{40,44}=?)~i', $input, $m)) {
            return $m[1];
        }
        if (preg_match('~/(?:tx|transaction|transactions|events)/([A-Za-z0-9_\-+/=]{40,44}=?)(?:[/?#]|$)~', $input, $m)) {
            return $m[1];
        }


        if (preg_match('/^[A-Za-z0-9_\-+\/]{40,44}=?$/', $input) && !preg_match('/^0x[0-9a-fA-F]{40}$/i', $input)) {
            return $input;
        }

        return null;
    }
}

if (!function_exists('crypto_active_wallet')) {
    function crypto_active_wallet(string $currency): ?array
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return null;
        try {
            $stmt = $pdo->prepare("SELECT * FROM crypto_wallets WHERE currency = :c AND enabled = 1 LIMIT 1");
            $stmt->execute([':c' => $currency]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) return null;
            $row['wallet_address'] = trim((string) ($row['wallet_address'] ?? ''));
            if ($row['wallet_address'] === '') return null;
            return $row;
        } catch (Throwable $e) {
            error_log('[crypto] active_wallet: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('crypto_active_wallets')) {
    function crypto_active_wallets(): array
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return [];
        try {
            $stmt = $pdo->query("SELECT currency, network, wallet_address, label
                                   FROM crypto_wallets
                                  WHERE enabled = 1 AND wallet_address <> ''
                                  ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('crypto_validate_address')) {
    function crypto_validate_address(string $address, string $network): bool
    {
        $address = trim($address);
        if ($network === 'TRON') {
            // Checksum-verified: a mistyped TRC20 address passes a shape regex but can never receive funds.
            return function_exists('tronadoIsValidTronAddress') && tronadoIsValidTronAddress($address);
        }
        if ($network === 'TON') {


            if (preg_match('/^(?:EQ|UQ|kQ|Ef|Uf|0Q)[A-Za-z0-9_\-]{46}$/', $address)) return true;
            if (preg_match('/^-?\d+:[0-9a-fA-F]{64}$/', $address)) return true;
            return false;
        }
        return mb_strlen($address) >= 20 && mb_strlen($address) <= 200;
    }
}

if (!function_exists('crypto_save_wallet')) {
    function crypto_save_wallet(string $currency, string $address): bool
    {
        $supported = crypto_supported_currencies();
        if (!isset($supported[$currency])) return false;
        $network = $supported[$currency]['network'];
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("INSERT INTO crypto_wallets (currency, network, wallet_address, label, enabled)
                                  VALUES (:c, :n, :a, :l, 1)
                                  ON DUPLICATE KEY UPDATE wallet_address = VALUES(wallet_address),
                                                          network        = VALUES(network),
                                                          enabled        = 1");
            $up->execute([
                ':c' => $currency,
                ':n' => $network,
                ':a' => $address,
                ':l' => $supported[$currency]['label'],
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] save_wallet: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('crypto_manual_currencies')) {
    function crypto_manual_currencies(): array
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return [];
        try {
            $stmt = $pdo->query("SELECT currency, network, label
                                    FROM crypto_wallets
                                   WHERE verification_mode = 'manual' AND enabled = 1
                                   ORDER BY id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $out = [];
            foreach ($rows as $row) {
                $out[$row['currency']] = [
                    'network'   => $row['network'],
                    'decimals'  => 6,
                    'label'     => $row['label'] ?: $row['currency'],
                    'fa_short'  => $row['label'] ?: $row['currency'],
                ];
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('crypto_create_manual_wallet')) {
    function crypto_create_manual_wallet(string $currency, string $network, string $label, string $address): bool
    {
        $currency = strtoupper(trim($currency));
        $network  = trim($network);
        $label    = trim($label);
        if ($currency === '' || $network === '') return false;
        if (strpos($currency, 'USDT_') !== 0) return false;
        if (isset(crypto_supported_currencies()[$currency])) return false;
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("INSERT INTO crypto_wallets (currency, network, wallet_address, label, enabled, verification_mode)
                                  VALUES (:c, :n, :a, :l, 1, 'manual')
                                  ON DUPLICATE KEY UPDATE wallet_address = VALUES(wallet_address),
                                                          network        = VALUES(network),
                                                          label          = VALUES(label),
                                                          enabled        = 1,
                                                          verification_mode = 'manual'");
            $up->execute([
                ':c' => $currency,
                ':n' => $network,
                ':a' => $address,
                ':l' => $label !== '' ? $label : $currency,
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] create_manual_wallet: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('crypto_delete_wallet')) {

    function crypto_delete_wallet(string $currency): bool
    {
        $supported = crypto_supported_currencies();
        $manual = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
        if (!isset($supported[$currency]) && !isset($manual[$currency])) return false;
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("UPDATE crypto_wallets
                                    SET wallet_address = '', wallet_memo = '', enabled = 0
                                  WHERE currency = :c");
            $up->execute([':c' => $currency]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] delete_wallet: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('crypto_invoice_button_style')) {
    function crypto_invoice_button_style(string $key): ?string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
            if ($pdo instanceof PDO) {
                try {
                    $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                        $all = json_decode((string) $row['keyboard_styles_all'], true);
                        if (is_array($all) && !empty($all['invoice_copy_buttons']) && is_array($all['invoice_copy_buttons'])) {
                            $cache = $all['invoice_copy_buttons'];
                        }
                    }
                } catch (Throwable $e) {  }
            }
        }
        $style = $cache[$key] ?? null;
        if ($style === null || $style === '' || $style === 'default') return null;
        return (string) $style;
    }
}

if (!function_exists('cm_apply_payment')) {
    function cm_apply_payment(string $orderId, int $finalIrr, array $payment, $adminId, $adminUsername, array $setting, $paymentreports, $messageId, $callbackQueryId, string $textInline = ''): void
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return;
        $atomic = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'paid' "
            . "WHERE id_order = :id AND payment_Status <> 'paid'"
        );
        $atomic->bindValue(':id', $orderId, PDO::PARAM_STR);
        $atomic->execute();
        if ($atomic->rowCount() < 1) {
            return;
        }
        if (function_exists('crypto_record_verified_hash')) {
            crypto_record_verified_hash($orderId, 'manual_admin');
        }

        $idInvoiceRaw = (string) ($payment['id_invoice'] ?? '');
        $pendingActionTags = ['getconfigafterpay', 'getextenduser', 'getextratimeuser', 'getextravolumeuser'];
        $pendingActionTag = '';
        if ($idInvoiceRaw !== '' && strpos($idInvoiceRaw, '|') !== false) {
            $tag = explode('|', $idInvoiceRaw, 2)[0];
            if (in_array($tag, $pendingActionTags, true)) {
                $pendingActionTag = $tag;
            }
        }

        if ($pendingActionTag !== '') {
            // پرداخت برای تکمیل یک عملیات معلق (خرید/تمدید/افزودن حجم-زمان) است — مثل مسیر خودکار
            // cronbot/cryptocheck.php، مبلغ به کیف پول اضافه نمی‌شود؛ DirectPayment() خودش عملیات را انجام می‌دهد.
            if (function_exists('DirectPayment')) {
                DirectPayment($orderId);
            }
            if (function_exists('update')) {
                update('Payment_report', 'at_updated', date('Y/m/d H:i:s'), 'id_order', $orderId);
            }
            if ($callbackQueryId && function_exists('telegram')) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callbackQueryId,
                    'text' => '✅ تایید شد.',
                    'cache_time' => 0,
                ]);
            }
            if ($messageId && function_exists('Editmessagetext')) {
                $doneNote = "\n\n━━━━━━━━━━━━\n✅ <b>تایید شده</b> (مبلغ: " . number_format($finalIrr) . " تومان)\n👨‍💼 توسط ادمین: <code>{$adminId}</code>\n⏰ " . date('Y/m/d H:i:s');
                @Editmessagetext($adminId, $messageId, $textInline . $doneNote, null);
            }
            if (!empty($setting['Channel_Report']) && function_exists('telegram')) {
                $payload = [
                    'chat_id' => $setting['Channel_Report'],
                    'text'    => "✅ بررسی دستی پرداخت کریپتو تایید شد\n\n"
                        . "🛒 کد پیگیری: <code>{$orderId}</code>\n"
                        . "👤 کاربر: <code>{$payment['id_user']}</code>\n"
                        . "💎 ارز: " . htmlspecialchars((string) ($payment['crypto_currency'] ?? '')) . "\n"
                        . "💵 مبلغ: " . number_format($finalIrr) . " تومان\n"
                        . "👨‍💼 ادمین تاییدکننده: <code>{$adminId}</code> (@{$adminUsername})",
                    'parse_mode' => 'HTML',
                ];
                if (!empty($paymentreports)) {
                    $payload['message_thread_id'] = $paymentreports;
                }
                telegram('sendmessage', $payload);
            }
            return;
        }

        $userRow = function_exists('select') ? select('user', '*', 'id', $payment['id_user'], 'select', ['cache' => false]) : null;
        if (!function_exists('balance_atomic_credit') || !balance_atomic_credit($payment['id_user'], $finalIrr)) {
            try {
                $release = $pdo->prepare("UPDATE Payment_report SET payment_Status = :s WHERE id_order = :id AND payment_Status = 'paid'");
                $release->execute([':s' => (string) ($payment['payment_Status'] ?? 'waiting'), ':id' => $orderId]);
            } catch (Throwable $e) {
                error_log('[crypto] cm_apply_payment release failed: ' . $e->getMessage());
            }
            if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
            if (function_exists('rx_log_event')) {
                rx_log_event('WALLET_CREDIT_FAILED', 'cm_apply_payment wallet credit failed; claim released', [
                    'id_order' => $orderId,
                    'id_user' => $payment['id_user'] ?? null,
                    'amount' => $finalIrr,
                ]);
            }
            if ($callbackQueryId && function_exists('telegram')) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callbackQueryId,
                    'text' => '❌ شارژ کیف پول انجام نشد. دوباره تلاش کنید.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
            }
            return;
        }
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($payment['id_user'], 'credit', $finalIrr, 'topup_crypto', (string) ($payment['Payment_Method'] ?? 'crypto'), $orderId);
        }
        $balanceRow = function_exists('select') ? select('user', 'Balance', 'id', $payment['id_user'], 'select', ['cache' => false]) : null;
        $newBalance = is_array($balanceRow) ? (int) ($balanceRow['Balance'] ?? 0) : ((is_array($userRow) ? (int) ($userRow['Balance'] ?? 0) : 0) + $finalIrr);
        if (function_exists('update')) {
            update('Payment_report', 'at_updated', date('Y/m/d H:i:s'), 'id_order', $orderId);
        }

        $__cmDiscountCode = trim((string) ($payment['discount_code'] ?? ''));
        $__cmDiscountAlreadyConsumed = (string) ($payment['discount_consumed'] ?? '') === '1';
        if ($__cmDiscountCode !== '' && !$__cmDiscountAlreadyConsumed && class_exists('MiniDiscount') && is_array($userRow)) {
            update('Payment_report', 'discount_consumed', '1', 'id_order', $orderId);
            MiniDiscount::markSellUsed($__cmDiscountCode, $userRow);
            MiniDiscount::logOrderDiscount([
                'id_user' => $payment['id_user'],
                'code' => $__cmDiscountCode,
                'kind' => 'sell',
                'value_type' => null,
                'value_raw' => null,
                'price_before' => (float) ($payment['price_before_discount'] ?? 0),
                'discount_amount' => (float) ($payment['discount_amount'] ?? 0),
                'price_after' => $finalIrr,
                'section' => 'charge',
            ]);
        }
        $coinAmt = rtrim(rtrim(number_format((float) ($payment['crypto_amount'] ?? 0), 9, '.', ''), '0'), '.');
        $explorerUrl = function_exists('crypto_explorer_url')
            ? crypto_explorer_url((string) ($payment['crypto_currency'] ?? ''), (string) ($payment['crypto_tx_hash'] ?? ''))
            : '';
        $explorerLine = $explorerUrl !== '' ? "🔗 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده تراکنش</a>" : '';
        if (function_exists('sendmessage')) {
            sendmessage(
                (string) $payment['id_user'],
                "✅ <b>درخواست بررسی دستی شما تایید شد و کیف پول‌تان شارژ گردید.</b>\n\n"
                . "🛒 کد پیگیری: <code>{$orderId}</code>\n"
                . "💎 ارز: <b>" . htmlspecialchars((string) ($payment['crypto_currency'] ?? '')) . "</b>\n"
                . "🪙 مقدار: <code>{$coinAmt}</code>\n"
                . "💵 مبلغ شارژ شده: " . number_format($finalIrr) . " تومان\n"
                . "💰 موجودی جدید: " . number_format($newBalance) . " تومان"
                . ($explorerLine !== '' ? "\n{$explorerLine}" : ''),
                null,
                'HTML'
            );
        }
        if ($callbackQueryId && function_exists('telegram')) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text' => '✅ تایید شد و کیف پول شارژ شد.',
                'cache_time' => 0,
            ]);
        }
        if ($messageId && function_exists('Editmessagetext')) {
            $doneNote = "\n\n━━━━━━━━━━━━\n✅ <b>تایید شده</b> (مبلغ: " . number_format($finalIrr) . " تومان)\n👨‍💼 توسط ادمین: <code>{$adminId}</code>\n⏰ " . date('Y/m/d H:i:s');
            @Editmessagetext($adminId, $messageId, $textInline . $doneNote, null);
        }
        if (!empty($setting['Channel_Report']) && function_exists('telegram')) {
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'text'    => "✅ بررسی دستی پرداخت کریپتو تایید شد\n\n"
                    . "🛒 کد پیگیری: <code>{$orderId}</code>\n"
                    . "👤 کاربر: <code>{$payment['id_user']}</code>\n"
                    . "💎 ارز: " . htmlspecialchars((string) ($payment['crypto_currency'] ?? '')) . "\n"
                    . "🪙 مقدار: <code>{$coinAmt}</code>\n"
                    . "💵 مبلغ شارژ: " . number_format($finalIrr) . " تومان\n"
                    . "👨‍💼 ادمین تاییدکننده: <code>{$adminId}</code> (@{$adminUsername})",
                'parse_mode' => 'HTML',
            ];
            if (!empty($paymentreports)) {
                $payload['message_thread_id'] = $paymentreports;
            }
            telegram('sendmessage', $payload);
        }
    }
}

if (!function_exists('crypto_save_wallet_memo')) {
    function crypto_save_wallet_memo(string $currency, string $memo): bool
    {
        $supported = crypto_supported_currencies();
        $manual = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
        if (!isset($supported[$currency]) && !isset($manual[$currency])) return false;
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $up = $pdo->prepare("UPDATE crypto_wallets SET wallet_memo = :m WHERE currency = :c");
            $up->execute([':m' => $memo, ':c' => $currency]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] save_wallet_memo: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('crypto_get_irt_rates')) {


    function crypto_get_irt_rates(array $wantKeys = []): array
    {
        $providerSet = [];
        foreach ($wantKeys ?: ['TRX', 'TON', 'USDT'] as $k) {
            $u = strtoupper(trim((string) $k));
            if ($u === '') continue;
            if ($u === 'USDT')      $providerSet['USD'] = true;
            else                    $providerSet[$u] = true;
        }
        $providerKeys = array_keys($providerSet);

        $rates = [];
        if (!empty($providerKeys) && function_exists('requireTronRates')) {


            foreach ($providerKeys as $pk) {
                $r = requireTronRates([$pk]);
                if (!is_array($r)) continue;
                if ($pk === 'TRX' && isset($r['TRX']) && is_numeric($r['TRX']) && (float) $r['TRX'] > 0) {
                    $rates['TRX'] = (float) $r['TRX'];
                } elseif ($pk === 'TON' && isset($r['Ton']) && is_numeric($r['Ton']) && (float) $r['Ton'] > 0) {
                    $rates['TON'] = (float) $r['Ton'];
                } elseif ($pk === 'USD' && isset($r['USD']) && is_numeric($r['USD']) && (float) $r['USD'] > 0) {
                    $rates['USDT'] = (float) $r['USD'];
                }
            }
        }


        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->query("SELECT currency, rate_irt_override FROM crypto_wallets WHERE rate_irt_override IS NOT NULL AND rate_irt_override > 0");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cur = strtoupper((string) $row['currency']);
                    $key = ($cur === 'USDT_TRC20' || $cur === 'USDT_TON') ? 'USDT' : $cur;
                    if (!isset($rates[$key])) {
                        $rates[$key] = (float) $row['rate_irt_override'];
                    }
                }
            } catch (Throwable $e) {  }
        }
        return $rates;
    }
}

if (!function_exists('crypto_irt_rate_for')) {
    function crypto_irt_rate_for(string $currency): ?float
    {


        if ($currency === 'TRX') {
            $rates = crypto_get_irt_rates(['TRX']);
            return $rates['TRX'] ?? null;
        }
        if ($currency === 'TON') {
            $rates = crypto_get_irt_rates(['TON']);
            return $rates['TON'] ?? null;
        }
        if ($currency === 'USDT_TRC20' || $currency === 'USDT_TON' || strpos($currency, 'USDT_') === 0) {
            $rates = crypto_get_irt_rates(['USDT']);
            return $rates['USDT'] ?? null;
        }
        return null;
    }
}

if (!function_exists('crypto_display_decimals')) {

    function crypto_display_decimals(string $currency): int
    {
        $defaults = [
            'TRX'        => 2,
            'USDT_TRC20' => 2,
            'USDT_TON'   => 2,
            'TON'        => 2,
        ];
        $cfg = crypto_pay_setting('cryptocheck_display_decimals_' . $currency, '');
        if ($cfg !== '' && ctype_digit($cfg)) {
            return max(2, min(6, (int) $cfg));
        }
        return $defaults[$currency] ?? 2;
    }
}

if (!function_exists('crypto_unique_amount')) {


    function crypto_unique_amount(float $baseCoinAmount, string $currency, ?int $extraDecimalsOverride = null, bool $iranianMode = false): float
    {
        $displayDecimals = crypto_display_decimals($currency);
        $scale = (int) pow(10, $displayDecimals);
        $maxNoiseCfg = crypto_pay_setting('cryptocheck_max_cent_noise', '');
        $maxNoise = ($maxNoiseCfg !== '' && ctype_digit($maxNoiseCfg)) ? (int) $maxNoiseCfg : 24;
        $maxNoise = max(1, min($maxNoise, $scale - 1));

        $baseUnits = (int) ceil($baseCoinAmount * $scale - 1e-9);
        if ($baseUnits < 1) $baseUnits = 1;

        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        $existing = [];
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT crypto_amount FROM Payment_report
                      WHERE crypto_currency = :c
                        AND payment_Status IN ('Unpaid','AwaitingHash')
                        AND crypto_amount IS NOT NULL"
                );
                $stmt->execute([':c' => $currency]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $units = (int) round((float) $row['crypto_amount'] * $scale);
                    $existing[$units] = true;
                }
            } catch (Throwable $e) {  }
        }

        for ($attempt = 0; $attempt < 64; $attempt++) {
            try { $rand = random_int(1, $maxNoise); }
            catch (Throwable $e) { $rand = mt_rand(1, $maxNoise); }
            $candidate = $baseUnits + $rand;
            if (!isset($existing[$candidate])) {
                return $candidate / $scale;
            }
        }

        $bump = $maxNoise + ((int) (microtime(true) * 1000) % max(1, $maxNoise)) + 1;
        return ($baseUnits + $bump) / $scale;
    }
}

if (!function_exists('crypto_create_invoice')) {
    function crypto_create_invoice($userId, int $amountIrt, string $currency, string $invoiceMeta = '', bool $iranianMode = false, ?string $source = null): array
    {
        $currencies = crypto_supported_currencies() + (function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : []);
        if (!isset($currencies[$currency])) {
            return ['ok' => false, 'error' => 'currency-not-supported'];
        }
        $wallet = crypto_active_wallet($currency);
        if (!$wallet) {
            return ['ok' => false, 'error' => 'wallet-not-configured'];
        }
        $minIrt = (int) ($wallet['min_irt'] ?? 0);
        $maxIrt = (int) ($wallet['max_irt'] ?? 0);
        if ($minIrt > 0 && $amountIrt < $minIrt) {
            return ['ok' => false, 'error' => 'below-min', 'min' => $minIrt];
        }
        if ($maxIrt > 0 && $amountIrt > $maxIrt) {
            return ['ok' => false, 'error' => 'above-max', 'max' => $maxIrt];
        }

        $rate = crypto_irt_rate_for($currency);
        if ($rate === null || $rate <= 0) {
            return ['ok' => false, 'error' => 'rate-unavailable'];
        }
        $rawCoin = $amountIrt / $rate;
        $finalCoin = crypto_unique_amount($rawCoin, $currency, null, $iranianMode);
        $finalAmountIrt = (int) round($finalCoin * $rate);

        global $connect;
        $orderId = bin2hex(random_bytes(6));
        $now = date('Y/m/d H:i:s');
        $statusUnpaid = 'Unpaid';


        $methodLabel = 'arze digital offline';
        $network = $currencies[$currency]['network'];

        try {
            $stmt = $connect->prepare(
                "INSERT INTO Payment_report
                    (id_user, id_order, time, price, payment_Status, Payment_Method,
                     id_invoice, crypto_currency, crypto_network, crypto_amount, crypto_wallet_to, crypto_iranian_mode, crypto_rate_irt, source)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $userIdStr = (string) $userId;
            $amountIrtStr = (string) $amountIrt;
            $coinStr = number_format($finalCoin, $currencies[$currency]['decimals'], '.', '');
            $iranianModeStr = '0';
            $rateStr = (string) $rate;
            $sourceStr = ($source !== null && $source !== '') ? $source : null;
            $stmt->bind_param(
                'ssssssssssssss',
                $userIdStr, $orderId, $now, $amountIrtStr, $statusUnpaid, $methodLabel,
                $invoiceMeta, $currency, $network, $coinStr, $wallet['wallet_address'], $iranianModeStr, $rateStr, $sourceStr
            );
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[crypto] create_invoice: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'db-write-failed'];
        }

        $ttl = 1800;
        return [
            'ok'                => true,
            'order_id'          => $orderId,
            'amount_coin'       => $finalCoin,
            'base_amount_coin'  => $rawCoin,
            'base_amount_irt'   => $amountIrt,
            'final_amount_irt'  => $finalAmountIrt,
            'wallet'            => $wallet['wallet_address'],
            'wallet_memo'       => trim((string) ($wallet['wallet_memo'] ?? '')),
            'currency'          => $currency,
            'network'           => $network,
            'rate'              => $rate,
            'expires_at'        => time() + $ttl,
        ];
    }
}

if (!function_exists('crypto_check_tx_timestamp_after_invoice')) {
    function crypto_check_tx_timestamp_after_invoice(int $txTimestamp, int $invoiceCreatedAt, int $toleranceSec = 120): bool
    {
        if ($txTimestamp <= 0 || $invoiceCreatedAt <= 0) return true;
        return $txTimestamp >= ($invoiceCreatedAt - $toleranceSec);
    }
}

if (!function_exists('crypto_check_sender_lock')) {
    function crypto_check_sender_lock(string $senderAddress, string $currency, string $telegramUserId): array
    {
        $senderAddress = trim($senderAddress);
        $currency = trim($currency);
        $telegramUserId = trim($telegramUserId);
        if ($senderAddress === '' || $currency === '' || $telegramUserId === '') {
            return ['ok' => true, 'first_use' => false, 'locked_to' => null];
        }
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => true, 'first_use' => false, 'locked_to' => null];
        }
        try {
            $q = $pdo->prepare(
                "SELECT telegram_user_id FROM crypto_sender_locks
                  WHERE sender_address = :s AND currency = :c
                  LIMIT 1"
            );
            $q->execute([':s' => $senderAddress, ':c' => $currency]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return ['ok' => true, 'first_use' => true, 'locked_to' => null];
            }
            $lockedTo = (string) ($row['telegram_user_id'] ?? '');
            if ($lockedTo === $telegramUserId) {
                return ['ok' => true, 'first_use' => false, 'locked_to' => $lockedTo];
            }
            return ['ok' => false, 'first_use' => false, 'locked_to' => $lockedTo];
        } catch (Throwable $e) {
            error_log('[crypto] check_sender_lock: ' . $e->getMessage());
            return ['ok' => true, 'first_use' => false, 'locked_to' => null];
        }
    }
}

if (!function_exists('crypto_record_sender_lock')) {
    function crypto_record_sender_lock(string $senderAddress, string $currency, string $telegramUserId): bool
    {
        $senderAddress = trim($senderAddress);
        $currency = trim($currency);
        $telegramUserId = trim($telegramUserId);
        if ($senderAddress === '' || $currency === '' || $telegramUserId === '') return false;
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $ins = $pdo->prepare(
                "INSERT INTO crypto_sender_locks (sender_address, currency, telegram_user_id, last_used_at, use_count)
                 VALUES (:s, :c, :u, CURRENT_TIMESTAMP, 1)
                 ON DUPLICATE KEY UPDATE
                    last_used_at = CURRENT_TIMESTAMP,
                    use_count = use_count + 1"
            );
            $ins->execute([':s' => $senderAddress, ':c' => $currency, ':u' => $telegramUserId]);
            return true;
        } catch (Throwable $e) {
            error_log('[crypto] record_sender_lock: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('crypto_record_verified_hash')) {
    function crypto_record_verified_hash(string $orderId, string $source = 'auto_cron'): bool
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return false;
        try {
            $row = function_exists('select') ? select('Payment_report', '*', 'id_order', $orderId, 'select') : null;
            if (!is_array($row)) return false;
            $hash = trim((string)($row['crypto_tx_hash'] ?? ''));
            if ($hash === '') return false;
            $ins = $pdo->prepare(
                "INSERT IGNORE INTO crypto_verified_hashes
                 (tx_hash, currency, network, wallet_to, sender_address, amount_coin, amount_irr, order_id, user_id, verification_source)
                 VALUES (:h, :c, :n, :w, :s, :ac, :ai, :o, :u, :src)"
            );
            $walletTo = (string)($row['crypto_wallet_to'] ?? '');
            $senderAddr = (string)($row['crypto_sender_address'] ?? '');
            $userIdStr = (string)($row['id_user'] ?? '');
            $ins->execute([
                ':h' => $hash,
                ':c' => (string)($row['crypto_currency'] ?? ''),
                ':n' => (string)($row['crypto_network'] ?? ''),
                ':w' => $walletTo !== '' ? $walletTo : null,
                ':s' => $senderAddr !== '' ? $senderAddr : null,
                ':ac' => $row['crypto_amount'] ?? null,
                ':ai' => (int)($row['price'] ?? 0),
                ':o' => $orderId,
                ':u' => $userIdStr !== '' ? $userIdStr : null,
                ':src' => $source,
            ]);
            return $ins->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[crypto] record_verified_hash: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('crypto_lookup_verified_hash')) {
    function crypto_lookup_verified_hash(string $hash): ?array
    {
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO) || trim($hash) === '') return null;
        try {
            $q = $pdo->prepare("SELECT * FROM crypto_verified_hashes WHERE tx_hash = :h LIMIT 1");
            $q->execute([':h' => trim($hash)]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('crypto_attach_hash')) {
    function crypto_attach_hash(string $orderId, string $hashOrUrl): array
    {
        $hash = crypto_extract_hash($hashOrUrl);
        if ($hash === null) {
            return ['ok' => false, 'error' => 'invalid-hash'];
        }
        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'error' => 'no-db'];
        }
        try {
            $dup = $pdo->prepare("SELECT id_order FROM Payment_report WHERE crypto_tx_hash = :h AND id_order <> :o LIMIT 1");
            $dup->execute([':h' => $hash, ':o' => $orderId]);
            if ($dup->fetch()) {
                return ['ok' => false, 'error' => 'hash-already-used'];
            }
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                 SET crypto_tx_hash = :h, crypto_hash_at = :t, payment_Status = 'AwaitingHash'
                 WHERE id_order = :o AND payment_Status IN ('Unpaid','AwaitingHash')"
            );
            $stmt->execute([':h' => $hash, ':t' => time(), ':o' => $orderId]);
            if ($stmt->rowCount() < 1) {
                return ['ok' => false, 'error' => 'order-not-pending'];
            }
            return ['ok' => true, 'hash' => $hash];
        } catch (Throwable $e) {
            error_log('[crypto] attach_hash: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'db-update-failed'];
        }
    }
}

if (!function_exists('crypto_http_get_json')) {
    function crypto_http_get_json(string $url, array $headers = [], int $timeoutMs = 7000): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => 4000,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: application/json',
                'User-Agent: CryptoHashChecker/1.0',
            ], $headers),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            error_log("[crypto] HTTP {$code} for {$url}: {$err}");
            return null;
        }
        $json = json_decode((string) $body, true);
        if (!is_array($json)) return null;
        return $json;
    }
}

if (!function_exists('crypto_amount_within_tolerance')) {


    function crypto_amount_within_tolerance(float $expected, float $observed, bool $iranianMode = false): bool
    {
        if ($expected <= 0) return false;

        $shortPct = (float) crypto_pay_setting('cryptocheck_amount_tolerance', '0');
        $shortPct = max(0.0, min($shortPct, 5.0));

        $overPct = (float) crypto_pay_setting('cryptocheck_overpay_tolerance', '0');
        $overPct = max(0.0, min($overPct, 100.0));

        $shortTol = $expected * ($shortPct / 100.0);
        $overTol  = $expected * ($overPct / 100.0);


        $eps = max(abs($expected), abs($observed)) * 1e-9;

        $diff = $observed - $expected;
        if ($diff >= -($shortTol + $eps) && $diff <= ($overTol + $eps)) {
            return true;
        }
        return false;
    }
}


if (!function_exists('crypto_check_tron_tx')) {
    function crypto_check_tron_tx(string $hash, string $expectedTo, float $expectedAmount, ?string $tokenContract = null, bool $iranianMode = false): array
    {
        $hash = strtolower(trim($hash));
        if (!preg_match('/^[0-9a-f]{64}$/', $hash)) {
            return ['ok' => false, 'reason' => 'bad-hash-format'];
        }
        $expectedTo = trim($expectedTo);
        if ($expectedTo === '') {
            return ['ok' => false, 'reason' => 'no-expected-recipient'];
        }

        $url = 'https://apilist.tronscanapi.com/api/transaction-info?hash=' . urlencode($hash);
        $key = crypto_pay_setting('cryptocheck_trongrid_key', '');
        $headers = [];
        if ($key !== '') $headers[] = 'TRON-PRO-API-KEY: ' . $key;

        $tx = crypto_http_get_json($url, $headers);
        if ($tx === null || empty($tx)) {
            return ['ok' => false, 'reason' => 'api-unreachable'];
        }
        if (empty($tx['hash'])) {
            return ['ok' => false, 'reason' => 'tx-not-found'];
        }
        $confirmed = (int) ($tx['confirmed'] ?? 0) === 1;
        if (!$confirmed && empty($tx['confirmations'])) {
            return ['ok' => false, 'reason' => 'tx-not-confirmed'];
        }
        if (isset($tx['contractRet']) && $tx['contractRet'] !== 'SUCCESS') {
            return ['ok' => false, 'reason' => 'tx-failed', 'detail' => $tx];
        }

        $txTimestampMs = (int) ($tx['timestamp'] ?? 0);
        $txTimestampSec = $txTimestampMs > 0 ? (int) floor($txTimestampMs / 1000) : 0;

        if ($tokenContract === null) {
            $contractType = (int) ($tx['contractType'] ?? 1);
            if ($contractType !== 1) {
                return ['ok' => false, 'reason' => 'not-trx-transfer'];
            }
            $to = trim((string) ($tx['toAddress'] ?? ''));
            if (strcasecmp($to, $expectedTo) !== 0) {
                return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['to' => $to, 'want' => $expectedTo]];
            }
            $amountSun = (float) ($tx['contractData']['amount'] ?? 0);
            $amountTrx = $amountSun / 1000000.0;
            if (!crypto_amount_within_tolerance($expectedAmount, $amountTrx, $iranianMode)) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $amountTrx, 'want' => $expectedAmount]];
            }
            $sender = trim((string) ($tx['ownerAddress'] ?? $tx['contractData']['owner_address'] ?? ''));
            return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amountTrx, 'to' => $to, 'sender' => $sender, 'tx_timestamp' => $txTimestampSec]];
        }


        $transfers = $tx['tokenTransferInfo'] ?? null;
        if (!is_array($transfers)) {
            $transfers = $tx['trc20TransferInfo'] ?? [];
            if (!is_array($transfers)) $transfers = [];
            if (isset($transfers['contract_address'])) {
                $transfers = [$transfers];
            }
        } else {
            $transfers = [$transfers];
        }
        foreach ($transfers as $t) {
            if (!is_array($t)) continue;
            $contract = trim((string) ($t['contract_address'] ?? $t['contractAddress'] ?? ''));
            if ($contract === '' || strcasecmp($contract, $tokenContract) !== 0) continue;
            $to = trim((string) ($t['to_address'] ?? $t['toAddress'] ?? ''));
            if (strcasecmp($to, $expectedTo) !== 0) {
                return ['ok' => false, 'reason' => 'wrong-recipient', 'detail' => ['to' => $to, 'want' => $expectedTo]];
            }
            $decimals = (int) ($t['decimals'] ?? 6);
            $raw = (string) ($t['amount_str'] ?? $t['amount'] ?? '0');
            $amount = (float) $raw / pow(10, $decimals);
            if (!crypto_amount_within_tolerance($expectedAmount, $amount, $iranianMode)) {
                return ['ok' => false, 'reason' => 'amount-mismatch', 'detail' => ['observed' => $amount, 'want' => $expectedAmount]];
            }
            $sender = trim((string) ($t['from_address'] ?? $t['fromAddress'] ?? $tx['ownerAddress'] ?? ''));
            return ['ok' => true, 'reason' => 'verified', 'detail' => ['amount' => $amount, 'to' => $to, 'sender' => $sender, 'tx_timestamp' => $txTimestampSec]];
        }
        return ['ok' => false, 'reason' => 'no-matching-trc20-transfer'];
    }
}