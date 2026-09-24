<?php

if (!function_exists('rx_featCategoryRows')) {
    function rx_featCategoryRows($cat)
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

        if ($cat === 'bot') {
            return [
                [['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
                 ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"]],
                [['text' => $name_status, 'callback_data' => "editstsuts-statusbot-{$setting['Bot_Status']}"],
                 ['text' => $textbotlang['Admin']['Status']['stautsbot'], 'callback_data' => "statusbot"]],
                [['text' => $name_status_role, 'callback_data' => "editstsuts-role-{$setting['roll_Status']}"],
                 ['text' => $textbotlang['Admin']['Status']['stautsrolee'], 'callback_data' => "stautsrolee"]],
                [['text' => $Authenticationphone, 'callback_data' => "editstsuts-get_number-{$setting['get_number']}"],
                 ['text' => $textbotlang['Admin']['Status']['Authenticationphone'], 'callback_data' => "Authenticationphone"]],
                [['text' => $Authenticationiran, 'callback_data' => "editstsuts-Authenticationiran-{$setting['iran_number']}"],
                 ['text' => $textbotlang['Admin']['Status']['Authenticationiran'], 'callback_data' => "Authenticationiran"]],
                [['text' => $statusverify, 'callback_data' => "editstsuts-verifystart-{$setting['verifystart']}"],
                 ['text' => "🔒 احراز هویت", 'callback_data' => "verify"]],
                [['text' => $statusverifybyuser, 'callback_data' => "editstsuts-verifybyuser-{$setting['verifybucodeuser']}"],
                 ['text' => "🔑 احراز با لینک", 'callback_data' => "verifybyuser"]],
                [['text' => $authScopeBtn, 'callback_data' => "editstsuts-authscope-{$authScopeVal}"]],
                [['text' => "🛡 تنظیمات دکمه شیشه‌ای", 'callback_data' => "glass_mode_settings"]],
                [['text' => $forced_miniapp_status, 'callback_data' => "editstsuts-forced_miniapp-{$setting['forced_miniapp_mode']}"],
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
            ];
        } elseif ($cat === 'users') {
            return [
                [['text' => $name_status_username, 'callback_data' => "editstsuts-usernamebtn-{$setting['NotUser']}"],
                 ['text' => $textbotlang['Admin']['Status']['statususernamebtn'], 'callback_data' => "usernamebtn"]],
                [['text' => $name_status_notifnewuser, 'callback_data' => "editstsuts-notifnew-{$setting['statusnewuser']}"],
                 ['text' => $textbotlang['Admin']['Status']['statusnotifnewuser'], 'callback_data' => "statusnewuser"]],
                [['text' => $name_status_showagent, 'callback_data' => "editstsuts-showagent-{$setting['statusagentrequest']}"],
                 ['text' => $textbotlang['Admin']['Status']['statusshowagent'], 'callback_data' => "statusnewuser"]],
                [['text' => $statuspvsupport, 'callback_data' => "editstsuts-statussupportpv-{$setting['statussupportpv']}"],
                 ['text' => "👤 پشتیبانی پیوی", 'callback_data' => "statussupportpv"]],
                [['text' => $statusnameconfig, 'callback_data' => "editstsuts-statusnamecustom-{$setting['statusnamecustom']}"],
                 ['text' => "📨 یادداشت کانفیگ", 'callback_data' => "statusnamecustom"]],
                [['text' => $statusnotef, 'callback_data' => "editstsuts-statusnamecustomf-{$setting['statusnoteforf']}"],
                 ['text' => "📨 یادداشت کاربر", 'callback_data' => "statusnamecustomf"]],
            ];
        } elseif ($cat === 'shop') {
            return [
                [['text' => $statusnamebulk, 'callback_data' => "editstsuts-bulkbuy-{$setting['bulkbuy']}"],
                 ['text' => "🛍 خرید عمده", 'callback_data' => "bulkbuy"]],
                [['text' => $btnstatuscategory, 'callback_data' => "editstsuts-btn_status_category-{$setting['categoryhelp']}"],
                 ['text' => "📗 دسته آموزش", 'callback_data' => "btn_status_category"]],
                [['text' => $keyboard_config_text, 'callback_data' => "editstsuts-keyconfig-{$setting['status_keyboard_config']}"],
                 ['text' => "🔗 کیبورد کانفیگی", 'callback_data' => "keyconfig"]],
                [['text' => $status_copy_cart, 'callback_data' => "editstsuts-compycart-{$setting['statuscopycart']}"],
                 ['text' => "💳 کپی شماره کارت", 'callback_data' => "copycart"]],
                [['text' => $statusDebtsettlement, 'callback_data' => "editstsuts-Debtsettlement-{$setting['Debtsettlement']}"],
                 ['text' => "💎 تسویه بدهی", 'callback_data' => "Debtsettlement"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "changeloclimit"],
                 ['text' => $statuslimitchangeloc, 'callback_data' => "editstsuts-changeloc-{$setting['statuslimitchangeloc']}"],
                 ['text' => "🌍 قفل مکان", 'callback_data' => "changeloc"]],
                [['text' => "{$infocardColorEmoji} رنگ کارت", 'callback_data' => "infocard_color_menu"],
                 ['text' => $infocardStatusText, 'callback_data' => "editstsuts-infocard-{$infocardStatusValue}"],
                 ['text' => "📊 کارت سرویس", 'callback_data' => "infocard_status"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "randomwalletlimit"],
                 ['text' => $randomWalletStatusText, 'callback_data' => "editstsuts-randomwallet-{$randomWalletStatusValue}"],
                 ['text' => "🎯 رندوم کیف پول", 'callback_data' => "randomwallet_status"]],
            ];
        } elseif ($cat === 'lottery') {
            return [
                [['text' => "⚙️ تنظیمات", 'callback_data' => "gradonhshans"],
                 ['text' => $wheel_luck, 'callback_data' => "editstsuts-wheel_luck-{$setting['wheelـluck']}"],
                 ['text' => "🎲 گردونه شانس", 'callback_data' => "none"]],
                [['text' => $statusfirstwheel, 'callback_data' => "editstsuts-wheelagentfirst-{$setting['statusfirstwheel']}"],
                 ['text' => "🎲 گردونه خرید اول", 'callback_data' => "wheelagentfirst"]],
                [['text' => $wheelagent, 'callback_data' => "editstsuts-wheelagent-{$setting['wheelagent']}"],
                 ['text' => "🎲 گردونه نماینده", 'callback_data' => "wheelagent"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "scoresetting"],
                 ['text' => $score, 'callback_data' => "editstsuts-score-{$setting['scorestatus']}"],
                 ['text' => "🎁 قرعه شبانه", 'callback_data' => "score"]],
                [['text' => $Lotteryagent, 'callback_data' => "editstsuts-Lotteryagent-{$setting['Lotteryagent']}"],
                 ['text' => "🎁 قرعه نماینده", 'callback_data' => "Lotteryagent"]],
                [['text' => "⚙️ تنظیمات", 'callback_data' => "settingaffiliatesf"],
                 ['text' => $refralstatus, 'callback_data' => "editstsuts-affiliatesstatus-{$setting['affiliatesstatus']}"],
                 ['text' => "🎁 زیرمجموعه", 'callback_data' => "affiliatesstatus"]],
                [['text' => $statusDice, 'callback_data' => "editstsuts-Dice-{$setting['Dice']}"],
                 ['text' => "🎰 نمایش تاس", 'callback_data' => "Dice"]],
            ];
        } elseif ($cat === 'crons') {
            return [
                [['text' => $cronteststatustext, 'callback_data' => "editstsuts-crontest-{$status_cron['test']}"],
                 ['text' => "🔓 کرون تست", 'callback_data' => "none"]],
                [['text' => $cronuptime_nodestatustext, 'callback_data' => "editstsuts-uptime_node-{$status_cron['uptime_node']}"],
                 ['text' => "🎛 آپتایم نود", 'callback_data' => "none"]],
                [['text' => $cronuptime_panelstatustext, 'callback_data' => "editstsuts-uptime_panel-{$status_cron['uptime_panel']}"],
                 ['text' => "🎛 آپتایم پنل", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان هشدار", 'callback_data' => "settimecornday"],
                 ['text' => $crondaystatustext, 'callback_data' => "editstsuts-cronday-{$status_cron['day']}"],
                 ['text' => "🕚 کرون زمان", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان اتصال", 'callback_data' => "setting_on_holdcron"],
                 ['text' => $cronon_holdtext, 'callback_data' => "editstsuts-on_hold-{$status_cron['on_hold']}"],
                 ['text' => "🕚 کرون اتصال", 'callback_data' => "none"]],
                [['text' => "⚙️ حجم هشدار", 'callback_data' => "settimecornvolume"],
                 ['text' => $cronvolumestatustext, 'callback_data' => "editstsuts-cronvolume-{$status_cron['volume']}"],
                 ['text' => "🔋 کرون حجم", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremove"],
                 ['text' => $cronremovestatustext, 'callback_data' => "editstsuts-notifremove-{$status_cron['remove']}"],
                 ['text' => "❌ کرون حذف", 'callback_data' => "none"]],
                [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremovevolume"],
                 ['text' => $cronremovevolumestatustext, 'callback_data' => "editstsuts-notifremove_volume-{$status_cron['remove_volume']}"],
                 ['text' => "❌ کرون حذف‌حجم", 'callback_data' => "none"]],
                [['text' => "⚙️ تنظیم", 'callback_data' => "set_panel_timeout"],
                 ['text' => (int)($setting['panel_curl_timeout'] ?? 20) . " ثانیه", 'callback_data' => "none"],
                 ['text' => "⏱ تایم پنل", 'callback_data' => "none"]],
                [['text' => "⚙️ مدیریت", 'callback_data' => "cronjobs_settings"],
                 ['text' => "⏱ نمایش لیست", 'callback_data' => "cronjobs_settings"],
                 ['text' => "زمان‌بندی", 'callback_data' => "none"]],
            ];
        }
        return [];
    }
}

if (in_array($text, $textadmin) || $datain == "admin") {
    if ($datain == "admin")
        deletemessage($from_id, $message_id);
    if (rxAdminPanelEntryRequiresChannelReport($setting, $adminrulecheck)) {
        rxAdminPanelEntryForceChannelReportSetup($from_id, $setting, $textbotlang, $backadmin);
        if (isset($user) && is_array($user)) { $user['step'] = 'addchannelid'; }
        return;
    }
    step('home', $from_id);
    if (isset($user) && is_array($user)) { $user['step'] = 'home'; }
    if (function_exists('removeReplyKeyboardOnStartIfNeeded')) {
        removeReplyKeyboardOnStartIfNeeded($from_id);
    }
    $version_mini_app = file_get_contents('app/version');
    $rxCronAutoStatus = function_exists('activecronStatus') ? activecronStatus() : ['status' => 'error', 'user' => null];
    if (function_exists('rxBuildMiniAppInstructionText')) {
        $miniAppInstructionText = rxBuildMiniAppInstructionText($domainhostsEscaped, $rxCronAutoStatus);
    }
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    nm_adminInstantReply($from_id, $text_admin, $keyboardadmin, 'HTML');
    $miniAppInstructionHidden = isset($user['hide_mini_app_instruction']) ? (string) $user['hide_mini_app_instruction'] : '0';
    if ($miniAppInstructionHidden !== '1') {
        $miniAppInstructionKeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'دیگر نمایش نده ⛓️‍💥', 'callback_data' => 'hide_mini_app_instruction'],
                ],
            ],
        ]);
        nm_adminInstantReply($from_id, $miniAppInstructionText, $miniAppInstructionKeyboard, 'HTML');
    }
    return;
} elseif ($text == $textbotlang['Admin']['backadmin'] || $text == "🏠 منوی مدیریت" || $text == "منوی مدیریت 🏠" || $text == "منوی مدیریت" || $text == "بازگشت به منوی مدیریت 🏠" || $text == "بازگشت به منوی مدیریت" || $datain == "adm_hub_main") {
    if (rxAdminPanelEntryRequiresChannelReport($setting, $adminrulecheck)) {
        rxAdminPanelEntryForceChannelReportSetup($from_id, $setting, $textbotlang, $backadmin);
        if (isset($user) && is_array($user)) { $user['step'] = 'addchannelid'; }
        return;
    }
    if (function_exists('nmResolvePanelNameForUser')) {
        $rawProcessing = (string)($user['Processing_value'] ?? '');
        if ($rawProcessing !== '' && ($rawProcessing[0] === '{' || $rawProcessing[0] === '[')) {
            $resolvedName = nmResolvePanelNameForUser($user);
            if ($resolvedName !== '') {
                update("user", "Processing_value", $resolvedName, "id", $from_id);
            }
        }
        unset($rawProcessing, $resolvedName);
    }
    $version_mini_app = file_get_contents('app/version');
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    nm_adminInstantReply($from_id, $text_admin, $keyboardadmin, 'HTML');
    step('home', $from_id);
    return;
} elseif ($datain == "hide_mini_app_instruction") {
    if (!in_array($from_id, $admin_ids))
        return;
    if (($user['hide_mini_app_instruction'] ?? '0') !== '1') {
        update("user", "hide_mini_app_instruction", "1", "id", $from_id);
        $user['hide_mini_app_instruction'] = '1';
    }
    $confirmationKeyboard = json_encode(['inline_keyboard' => []]);
    if (function_exists('rxBuildMiniAppInstructionText')) {
        $rxHideCronStatus = function_exists('activecronStatus') ? activecronStatus() : ['status' => 'error', 'user' => null];
        $miniAppInstructionText = rxBuildMiniAppInstructionText($domainhostsEscaped, $rxHideCronStatus);
    }
    $confirmationText = $miniAppInstructionText . "\n\n✅ این پیام دیگر برای شما نمایش داده نخواهد شد.";
    Editmessagetext($from_id, $message_id, $confirmationText, $confirmationKeyboard, 'HTML');
    return;
} elseif ($text == "🔙 بازگشت به منوی پنل" && in_array($from_id, $admin_ids)) {
    step('PanelMenu', $from_id);
    $panelNameBack = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    if ($panelNameBack !== '' && $panelNameBack !== '0') {
        $typepanel = select("marzban_panel", "*", "name_panel", $panelNameBack, "select");
        if (is_array($typepanel) && !empty($typepanel)) {
            outtypepanel($typepanel['type'], $textbotlang['Admin']['Back-menu']);
            return;
        }
    }
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
    return;
} elseif (($text == $textbotlang['Admin']['backmenu']) || ((isset($datain) ? (string)$datain : '') === 'backmenu')) {
    $currentStep = isset($user['step']) ? (string) $user['step'] : '';

    if (function_exists('rxWizardStepFlow') && rxWizardStepFlow($currentStep) !== null) {
        $rx_prev_step = function_exists('stepBack') ? stepBack($from_id) : null;
        if ($rx_prev_step !== null && function_exists('rxRenderWizardStep') && rxRenderWizardStep($rx_prev_step, $from_id)) {
            return;
        }
        if ($rx_prev_step === null && function_exists('rxWizardExitFirstStep') && rxWizardExitFirstStep($currentStep, $from_id)) {
            return;
        }
    }

    if (($currentStep === 'get_time_start' || $currentStep === 'get_time_end') && isset($keyboard_stat)) {
        step('home', $from_id);
        nm_adminInstantReply($from_id, "📊 منوی آمار ربات", $keyboard_stat, 'HTML');
        return;
    }
    if ($currentStep === 'gettextday' || $currentStep === 'gettextSystemMessage') {
        step('home', $from_id);
        $rx_broadcastMenu = json_encode(['inline_keyboard' => [
            [['text' => "ارسال همگانی", 'callback_data' => 'typeservice-sendmessage']],
            [['text' => "فوروارد همگانی", 'callback_data' => 'typeservice-forwardmessage']],
            [['text' => "تعداد روزی که استفاده نکردند", 'callback_data' => 'typeservice-xdaynotmessage']],
            [['text' => "لغو پیام های پین شده", 'callback_data' => 'typeservice-unpinmessage']],
            [['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser']],
        ]], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $rx_broadcastMenu, 'HTML');
        return;
    }

    if (function_exists('rxNavBack') && rxNavBack($from_id, isset($rx_back_origin) ? $rx_back_origin : null, $currentStep)) {
        return;
    }

    step('home', $from_id);

    $adminPanelFlowSteps = [
        'GetLocationEdit', 'PanelMenu',
        'add_link_panel', 'add_username_panel', 'add_password_panel',
        'add_remna_token_setup', 'getlimitedpanel', 'add_guard_version', 'add_guard_api_key',
        'guard_svc_edit', 'guard_edit_api_key',
        'add_rebecca_api_key', 'rebecca_edit_api_key',
        'add_pasarguard_api_key', 'pasarguard_edit_api_key',
        'add_pasarguard_auth_method', 'add_pasarguard_username', 'add_pasarguard_password',
        'pasarguard_edit_username', 'pasarguard_edit_password',
        'confirmremovepanel', 'add_link_panel_edit', 'getlocoption',
        'switchtype_pick', 'switchtype_link_panel', 'switchtype_username_panel',
        'switchtype_password_panel', 'switchtype_guard_version', 'switchtype_guard_api_key', 'switchtype_remna_token',
        'switchtype_rebecca_api_key', 'switchtype_pasarguard_api_key',
        'switchtype_pasarguard_auth_method', 'switchtype_pasarguard_username', 'switchtype_pasarguard_password',
        'switchtype_xui_api_mode', 'switchtype_xui_token', 'switchtype_confirm',
    ];
    if (in_array($currentStep, $adminPanelFlowSteps, true)) {
        update("user", "Processing_value", "0", "id", $from_id);
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
        return;
    }

    if ($currentStep === 'remna_panel_back') {
        $panelNameBack = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
        if ($panelNameBack !== '' && $panelNameBack !== '0') {
            $typepanelBack = select("marzban_panel", "*", "name_panel", $panelNameBack, "select");
            if (is_array($typepanelBack) && !empty($typepanelBack) && function_exists('outtypepanel')) {
                outtypepanel($typepanelBack['type'], $textbotlang['Admin']['Back-menu']);
                return;
            }
        }
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
        return;
    }

    $adminProductFlowSteps = [
        'get_limit', 'get_agent', 'get_location', 'getcategory', 'get_time',
        'get_price', 'gettimereset', 'getnote', 'endstep', 'selectloc',
        'remove-product',
    ];
    if (in_array($currentStep, $adminProductFlowSteps, true) && isset($keyboard_shop_manage)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_shop_manage, 'HTML');
        return;
    }

    $adminCategoryFlowSteps = [
        'getremarkcategory', 'removecategory', 'editcategory_name', 'get_name_new_category',
    ];
    if (in_array($currentStep, $adminCategoryFlowSteps, true) && isset($keyboard_Category_manage)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_Category_manage, 'HTML');
        return;
    }

    $adminQuickPriceFlowSteps = [
        'getpricef', 'getpricnn', 'getpricnn2',
        'getpriceftime', 'getpricnntime', 'getpricnn2time',
    ];
    if (in_array($currentStep, $adminQuickPriceFlowSteps, true) && isset($adminPanelsMenu)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
        return;
    }

    if (in_array($currentStep, ['premium_emoji_get_char', 'premium_emoji_get_id', 'premium_emoji_edit_id'], true)) {
        $rxPemNavCbs = ['featcat_main','close_stat','admin_settings','premium_emoji_settings','premium_emoji_noop','premium_emoji_add','premium_emoji_add_single','premium_emoji_add_batch','premium_emoji_batch_continue','premium_emoji_batch_end','premium_emoji_del_all','premium_emoji_del_all_confirm','premium_emoji_scan','run_host_optimizer'];
        $rxPemIsNav  = !empty($datain) && (in_array((string)$datain, $rxPemNavCbs, true) || strpos((string)$datain, 'premium_emoji_settings_') === 0);
        if (!$rxPemIsNav) {
            if (function_exists('rxRenderPremiumEmojiPanel')) {
                rxRenderPremiumEmojiPanel($from_id, 1);
            }
            return;
        }
    }
    if (strpos($currentStep, 'get_remna_') === 0 || in_array($currentStep, ["updatetime", "val_usertest", "getlimitnew", "panellimit_getnew", "GetusernameNew", "GeturlNew", "protocolset", "updatemethodusername", "GetNameNew", "getprotocol", "getprotocolremove", "GetpaawordNew", "updateextendmethod", "setpricechangelocation"])) {
        $panelNameBack = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
        if ($panelNameBack !== '') {
            update("user", "Processing_value", $panelNameBack, "id", $from_id);
        }
        $typepanel = select("marzban_panel", "*", "name_panel", $panelNameBack !== '' ? $panelNameBack : $user['Processing_value'], "select");
        outtypepanel(is_array($typepanel) ? $typepanel['type'] : '', $textbotlang['Admin']['Back-menu']);
    } else {
        $financialStepKeyboardMap = [
            'helpiranpay2'        => $trnado,
            'helpofflinearze'     => $tronnowpayments,
            'helpzarinpal'        => $keyboardzarinpal,
            'minbalanceiranpay'   => $iranpaykeyboard,
            'maxbalanceiranpay'   => $iranpaykeyboard,
            'api_plisio'          => $NowPaymentsManage,
            'gettimeauto'         => $CartManage,
            'getidExceptio'       => $CartManage,
            'getidExceptioremove' => $CartManage,
            'getidTrustAdd'       => $CartManage,
            'getidTrustRemove'    => $CartManage,
            'apiternado' => $trnado,
            'ipnsigningkeytronado' => $trnado,
            'wageFromBusinessPercentageTronado' => $trnado,
            'apitonpay' => $tonpay,
            'getcashtonpay' => $tonpay,
            'getmaintonpay' => $tonpay,
            'getmaxtonpay' => $tonpay,
            'helptonpay' => $tonpay,
            'apicubepay' => $cubepay,
            'getcashcubepay' => $cubepay,
            'getfeecubepay' => $cubepay,
            'getmaincubepay' => $cubepay,
            'getmaxcubepay' => $cubepay,
            'helpcubepay' => $cubepay,
            'apiblupal' => $blupal,
            'getcashblupal' => $blupal,
            'getmainblupal' => $blupal,
            'getmaxblupal' => $blupal,
            'helpblupal' => $blupal,
            'apivariza' => $variza,
            'variza_webhook_secret' => $variza,
            'getcashvariza' => $variza,
            'getmainvariza' => $variza,
            'getmaxvariza' => $variza,
            'helpvariza' => $variza,
            'urlabangateway' => $abangateway,
            'apiabangateway' => $abangateway,
            'getcashabangateway' => $abangateway,
            'getmainabangateway' => $abangateway,
            'getmaxabangateway' => $abangateway,
            'helpabangateway' => $abangateway,
            'apiatlaspay' => $atlaspay,
            'getcashatlaspay' => $atlaspay,
            'getmainatlaspay' => $atlaspay,
            'getmaxatlaspay' => $atlaspay,
            'helpatlaspay' => $atlaspay,
            'changecard' => $CartManage,
            'getnamecard' => $CartManage,
            'getcardremove' => $CartManage,
            'getnamecarttocart' => $CartManage,
            'getnamenowpayment' => $nowpayment_setting_keyboard,
            'getnamecarttopaynotverify' => $CartManage,
            'gettextnowpayment' => $NowPaymentsManage,
            'gettextnowpaymentTRON' => $tronnowpayments,
            'gettextstartelegram' => $Startelegram,
            'gettextiranpay3' => $trnado,
            'gettexttonpay' => $tonpay,
            'gettextcubepay' => $cubepay,
            'gettextblupal' => $blupal,
            'gettextvariza' => $variza,
            'gettextabangateway' => $abangateway,
            'gettextatlaspay' => $atlaspay,
            'gettextiranpay1' => $iranpaykeyboard,
            'gettextzarinpal' => $keyboardzarinpal,
            'merchant_zarinpal' => $keyboardzarinpal,
            'apinowpayment' => $NowPaymentsManage,
            'nowpayment_ipn_secret' => $nowpayment_setting_keyboard,
            'marchent_tronseller' => $nowpayment_setting_keyboard,
            'getcashcart' => $CartManage,
            'getcashiranpay2' => $trnado,
            'getcashplisio' => $CartManage,
            'getcashnowpayment' => $nowpayment_setting_keyboard,
            'getcashzarinpal' => $keyboardzarinpal,
            'getmaincart' => $CartManage,
            'getmaxcart' => $CartManage,
            'getmainplisio' => $NowPaymentsManage,
            'getmaxplisio' => $NowPaymentsManage,
            'getmaindigitaltron' => $tronnowpayments,
            'getmaxdigitaltron' => $tronnowpayments,
            'getmainiranpay2' => $trnado,
            'getmaaxiranpay2' => $trnado,
            'getmainaqzarinpal' => $keyboardzarinpal,
            'getmaaxzarinpal' => $keyboardzarinpal,
            'gethelpcart' => $CartManage,
            'gethelpnowpayment' => $nowpayment_setting_keyboard,
            'gethelpperfect' => $CartManage,
            'gethelpplisio' => $CartManage,
            'getmainaqstar' => $Startelegram,
            'maxbalancestar' => $Startelegram,
            'getmainaqnowpayment' => $nowpayment_setting_keyboard,
            'maxbalancenowpayment' => $nowpayment_setting_keyboard,
            'gethelpstar' => $Startelegram,
            'chashbackstar' => $Startelegram,
        ];

        $productStepKeyboardMap = [
            'change_price' => $change_product,
            'change_note' => $change_product,
            'change_categroy' => $change_product,
            'change_name' => $change_product,
            'change_type_agent' => $change_product,
            'change_reset_data' => $change_product,
            'change_loc_data' => $change_product,
            'getlistpanel' => $change_product,
            'change_val' => $change_product,
            'change_time' => $change_product,
        ];

        if (in_array($currentStep, ['admin_nav_cart_settings'], true)) {
            if (function_exists('sendAdminFinanceMenu')) {
                sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            }
            return;
        }

        if (in_array($currentStep, ['admin_nav_cron_settings', 'admin_nav_cron_jobs'], true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
            step('admin_nav_cron_settings', $from_id);
            return;
        }

        if ($currentStep === 'cronjob_set_value') {
            if (function_exists('buildCronJobsKeyboard')) {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], buildCronJobsKeyboard(), 'HTML');
                step('admin_nav_cron_jobs', $from_id);
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
                step('admin_nav_cron_settings', $from_id);
            }
            return;
        }

        if ($currentStep === 'walletaddresssiranpay') {
            $processingData = [];
            if (isset($user['Processing_value'])) {
                $decodedProcessing = json_decode($user['Processing_value'], true);
                if (is_array($decodedProcessing)) {
                    $processingData = $decodedProcessing;
                }
            }
            $walletOrigin = $processingData['walletaddress_origin'] ?? 'general';
            if ($walletOrigin === 'trnado') {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $trnado, 'HTML');
            } elseif (function_exists('sendAdminFinanceMenu')) {
                sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            }
            return;
        }

        if (isset($financialStepKeyboardMap[$currentStep])) {
            $targetKeyboard = $financialStepKeyboardMap[$currentStep];
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
            return;
        }

        if (isset($productStepKeyboardMap[$currentStep])) {
            $targetKeyboard = $productStepKeyboardMap[$currentStep];
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
            return;
        }

        $financeSteps = [
            'marchent_tronseller', 'cryptowallet_set',
            'admin_nav_finance', 'maxbalance', 'minbalance', 'CartDirect', 'showcardallusers',
            'apiiranpay', 'getnameconfigm',
        ];
        if (in_array($currentStep, $financeSteps, true)) {
            step('home', $from_id);
            if (function_exists('sendAdminFinanceMenu')) {
                sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            }
            return;
        }

        $financeSpecificMap = [
            'getmainiranpay3' => $trnado,    'getmaaxiranpay3' => $trnado,
            'gethelpiranpay3' => $trnado,    'getcashiranpay3' => $trnado,
            'getcashiranpay2'  => $trnado,
            'getmainiranpay2'  => $trnado,   'getmaaxiranpay2'  => $trnado,
            'getmainiranpay1'  => $iranpaykeyboard ?? $keyboardadmin,
            'getmaaxiranpay1'  => $iranpaykeyboard ?? $keyboardadmin,
            'gethelpiranpay2'  => $trnado,
            'getagentbalancemax' => $shopkeyboard,
            'getagentbalancemin' => $shopkeyboard,
            'getmaindigitaltron2' => $tronnowpayments,
            'getmaxdigitaltron2'  => $tronnowpayments,
        ];
        if (isset($financeSpecificMap[$currentStep])) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $financeSpecificMap[$currentStep], 'HTML');
            return;
        }

        $discountSteps = [
            'getdiscont', 'getfirstdiscount', 'getlimitcode', 'getlimitcodedis',
            'getlocdiscount', 'getproductdiscount', 'gettimediscount', 'gettypeagentoflist',
            'gettypecodeagent', 'getuseuser', 'getmaxbuyagent', 'getpercentuser',
            'setpercentage', 'remove-Discount', 'remove-Discountsell', 'get_price_code',
            'get_price_codesell', 'get_price_Negative', 'Negative_Balance',
            'getagent', 'getpricecashback', 'stependforaddorder',
            'getnameproduct', 'add_Balance_all', 'setbanner', 'show_info', 'reject-dec',
            'get_number_limit',
        ];
        if (in_array($currentStep, $discountSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
            return;
        }

        $textEditSteps = [
            'text_Add_Balance', 'text_Discount', 'text_Tariff_list', 'text_affiliates',
            'text_afterpaytext', 'text_aftertesttext',
            'text_cart', 'text_channel', 'text_crontest',
            'text_dec_Tariff_list', 'text_dec_fq', 'text_extend', 'text_fq',
            'text_help', 'text_pishinvoice', 'text_request_agent_dec', 'text_roll',
            'text_sell', 'text_support', 'text_textmanual', 'text_wgdashboard',
            'text_wheel_luck', 'textpanelagent', 'textrequestagent', 'textselectlocation',
            'changetextinfo', 'changetextstart', 'changetextusertest',
        ];
        if (in_array($currentStep, $textEditSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $textbot, 'HTML');
            return;
        }

        $helpSteps = [
            'changecategoryhelp', 'changedeshelp', 'changemedia', 'changenamehelp',
            'add_name_help', 'getcatgoryhelp', 'remove_help', 'getconfigtext',
            'getservceid',
        ];
        if (in_array($currentStep, $helpSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardhelpadmin, 'HTML');
            return;
        }

        if ($currentStep === 'limit_usertest_allusers') {
            $usersHubKb = isset($adminUsersMenu) ? $adminUsersMenu : $keyboardadmin;
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $usersHubKb, 'HTML');
            return;
        }

        $settingsSteps = [
            'getnamepanelconfig', 'getusernameconfig',
            'idsupportset', 'get_codesell',
        ];
        if (in_array($currentStep, $settingsSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
            return;
        }

        $userSteps = [
            'accountwallet', 'add_dec', 'addbalancemanual', 'addbalanceuser',
            'addbalanceusercurrent', 'adddecriptionblock', 'getuserhide',
            'getuserhideforremove', 'addadmin', 'getrule', 'GetusernameconfigAndOrdedrs',
            'antispam_get_count', 'antispam_get_mute', 'antispam_get_seconds',
            'getbtnresponseforward', 'getmessageAsAdmin', 'getmessageforward',
            'sendmessagetext', 'sendmessagetid', 'getcountcreate', 'getagentpanel',
            'getinboundiid', 'getuuidadmin', 'getprotocoldisable', 'getprotocolx_ui',
            'getvolumesconfig', 'GeturlNewx',
            'getInbounddisable', 'getusernameconfigcr', 'removeprotocol',
            'GetPriceExtratime', 'GetPricecustomvo', 'GetPricetimeextra',
            'GetmaineExtra', 'Getmaintime', 'GetmaxeExtra', 'Getmaxtime',
        ];
        if (in_array($currentStep, $userSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            return;
        }

        $shopSteps = [
            "selectlocedite",
            "GetPriceExtra",
            "GetPriceexstratime",
            "GetPricecustomtime",
            "GetPricecustomvolume",
            "get_code",
            "get_codesell",
            "minbalancebulk",
            "gettypeextra",
            "gettypeextracustom",
            "gettypeextratime",
            "gettypeextratimecustom",
            "gettypeextramain",
            "gettypeextramax",
            "gettypeextramaintime",
            "gettypeextramaxtime",
        ];

        if (in_array($currentStep, $shopSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
            return;
        } elseif (in_array($currentStep, ["ch_edit_remark", "ch_edit_linkjoin", "ch_edit_link"], true)) {
            $chanId = (int)($user['Processing_value'] ?? 0);
            if ($chanId > 0 && function_exists('rx_render_channel_manage')) {
                rx_render_channel_manage($chanId, $from_id);
                return;
            }
            $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
            step('channel', $from_id);
            if (function_exists('rxNavSetState')) {
                rxNavSetState($from_id, 'channel');
            }
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
            return;
        } elseif ($currentStep === "channel_manage") {
            $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
            step('channel', $from_id);
            if (function_exists('rxNavSetState')) {
                rxNavSetState($from_id, 'channel');
            }
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
            return;
        } elseif ($currentStep === "channel") {
            if (function_exists('rxRenderMenuState')) {
                rxRenderMenuState('channelhub', $from_id);
                return;
            }
            step('channelhub', $from_id);
            if (isset($user) && is_array($user)) {
                $user['step'] = 'channelhub';
            }
            if (function_exists('rxNavSetState')) {
                rxNavSetState($from_id, 'channelhub');
            }
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $adminChannelMenu ?? $keyboardadmin, 'HTML');
            return;
        } elseif ($currentStep === "channelhub") {
            if (function_exists('rxRenderMenuState')) {
                rxRenderMenuState('home', $from_id);
                return;
            }
            step('home', $from_id);
            if (isset($user) && is_array($user)) {
                $user['step'] = 'home';
            }
            if (function_exists('rxNavSetState')) {
                rxNavSetState($from_id, 'home');
            }
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-Admin'], $keyboardadmin, 'HTML');
            return;
        } elseif ($currentStep === "addchannelid") {
            if (function_exists('rxRenderMenuState')) {
                rxRenderMenuState('channelhub', $from_id);
                return;
            }
            step('channelhub', $from_id);
            if (isset($user) && is_array($user)) {
                $user['step'] = 'channelhub';
            }
            if (function_exists('rxNavSetState')) {
                rxNavSetState($from_id, 'channelhub');
            }
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $adminChannelMenu ?? $keyboardadmin, 'HTML');
            return;
        } elseif (in_array($currentStep, ["addchannel", "removechannel", "getremark", "getlinkjoin"], true)) {
            $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
            step('channel', $from_id);
            if (function_exists('rxNavSetState')) {
                rxNavSetState($from_id, 'channel');
            }
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
            return;
        } else {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-Admin'], $keyboardadmin, 'HTML');
        }
    }
    return;
} elseif (($text == $textbotlang['Admin']['channel']['title'] || $text == "➕ افزودن کانال" || (isset($datain) && $datain == 'ch_add')) && $adminrulecheck['rule'] == "administrator") {
    $wizKb = function_exists('rx_get_channel_wizard_back_keyboard') ? rx_get_channel_wizard_back_keyboard() : $backadmin;
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['changechannel'], $wizKb, 'HTML');
    step('addchannel', $from_id);
} elseif ($user['step'] == "addchannel") {
    if (!isset($update['message']) && empty($text)) { return; }
    $rxChannelChatId = function_exists('rx_normalize_channel_chat_id') ? rx_normalize_channel_chat_id($text) : trim((string) $text);
    if ($rxChannelChatId === '') {
        $wizKb = function_exists('rx_get_channel_wizard_back_keyboard') ? rx_get_channel_wizard_back_keyboard() : $backadmin;
        nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['changechannel'], $wizKb, 'HTML');
        return;
    }
    savedata("clear", "link", $rxChannelChatId);
    $wizKb = function_exists('rx_get_channel_wizard_back_keyboard') ? rx_get_channel_wizard_back_keyboard() : $backadmin;
    nm_adminInstantReply($from_id, "📌 یک نام برای دکمه عضویت چنل انتخاب نمایید.", $wizKb, 'HTML');
    step('getremark', $from_id);
} elseif ($user['step'] == "getremark") {
    if (!isset($update['message']) && empty($text)) { return; }
    savedata("save", "remark", $text);
    $wizKb = function_exists('rx_get_channel_wizard_back_keyboard') ? rx_get_channel_wizard_back_keyboard() : $backadmin;
    nm_adminInstantReply($from_id, "📌 لینک عضویت را ارسال کنید", $wizKb, 'HTML');
    step('getlinkjoin', $from_id);
} elseif ($user['step'] == "getlinkjoin") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        $wizKb = function_exists('rx_get_channel_wizard_back_keyboard') ? rx_get_channel_wizard_back_keyboard() : $backadmin;
        nm_adminInstantReply($from_id, "آدرس عضویت صحیح نمی باشد", $wizKb, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata)) {
        $userdata = [];
    }

    $remark = isset($userdata['remark']) ? (string) $userdata['remark'] : '';
    $link = isset($userdata['link']) ? (string) $userdata['link'] : '';

    $insertChannel = function ($remarkValue) use ($pdo, $link, $text) {
        $stmt = $pdo->prepare("INSERT INTO channels (link, remark, linkjoin) VALUES (:link, :remark, :linkjoin)");
        $stmt->bindValue(':remark', $remarkValue, PDO::PARAM_STR);
        $stmt->bindValue(':link', $link, PDO::PARAM_STR);
        $stmt->bindValue(':linkjoin', $text, PDO::PARAM_STR);
        $stmt->execute();
    };

    try {
        $insertChannel($remark);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
            ensureTableUtf8mb4('channels');
            try {
                $insertChannel($remark);
            } catch (PDOException $retryException) {
                if (strpos($retryException->getMessage(), 'Incorrect string value') === false) {
                    throw $retryException;
                }

                $sanitisedRemark = is_string($remark) ? @iconv('UTF-8', 'UTF-8//IGNORE', $remark) : '';
                if ($sanitisedRemark === false) {
                    $sanitisedRemark = '';
                }
                $insertChannel($sanitisedRemark);
            }
        } else {
            throw $e;
        }
    }
    $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
    step('channel', $from_id);
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'channel');
    }
    nm_adminInstantReply($from_id, "✅ کانال جوین اجباری با موفقیت ثبت گردید.", $channelkeyboard, 'HTML');
} elseif ($adminrulecheck['rule'] == "administrator" && (
    (isset($datain) && preg_match('/^ch_manage_(\d+)$/', (string)$datain)) ||
    (
        !empty($text)
        && isset($user['step'])
        && $user['step'] === "channel"
        && $text !== "📢 کانال و اطلاع‌رسانی"
        && $text !== "📣 گزارشات ربات"
        && $text !== "📯 تنظیمات کانال"
        && $text !== "🔙 بازگشت به لیست کانال‌ها"
        && ($pdo instanceof PDO)
        && function_exists('rx_find_channel_from_input')
        && ($rxPreMatched = rx_find_channel_from_input($text, $datain ?? '', $pdo))
    ) ||
    (
        isset($user['step'])
        && $user['step'] === "channel"
        && !empty($text)
        && !in_array($text, [
            "➕ افزودن کانال",
            "➕ اضافه کردن کانال",
            $textbotlang['Admin']['channel']['title'] ?? '',
            $textbotlang['Admin']['backadmin'] ?? '',
            $textbotlang['Admin']['backmenu'] ?? '',
            "🏠 بازگشت به منوی مدیریت",
            "بازگشت به منوی مدیریت 🏠",
            "بازگشت به منوی مدیریت",
            "منوی مدیریت 🏠",
            "🏠 منوی مدیریت",
            "منوی مدیریت",
            "▶️ بازگشت به منوی قبل",
            "بازگشت به منوی قبل ▶️",
            "🔙 بازگشت به منوی قبل",
            "بازگشت به منوی قبل ⬅️",
            "بازگشت به منوی قبل",
            "🔙 بازگشت به لیست کانال‌ها",
            "بازگشت به لیست کانال‌ها",
            "📯 تنظیمات کانال",
            "📣 گزارشات ربات",
            "📢 کانال و اطلاع‌رسانی"
        ], true)
    )
)) {
    $matchedChan = isset($rxPreMatched) && is_array($rxPreMatched)
        ? $rxPreMatched
        : (function_exists('rx_find_channel_from_input') && ($pdo instanceof PDO)
            ? rx_find_channel_from_input($text, $datain ?? '', $pdo)
            : null);

    if ($matchedChan && !empty($matchedChan['id'])) {
        $chanId = (int)$matchedChan['id'];
        if (function_exists('rx_render_channel_manage')) {
            rx_render_channel_manage($chanId, $from_id);
        } else {
            update("user", "Processing_value", (string)$chanId, "id", $from_id);
            step('channel_manage', $from_id);
            if (isset($user) && is_array($user)) {
                $user['step'] = 'channel_manage';
                $user['Processing_value'] = (string)$chanId;
            }
            if (function_exists('rxNavSetState')) {
                rxNavSetState($from_id, 'channel_manage');
            }
            $msg = "📋 <b>مدیریت کانال:</b> " . htmlspecialchars($matchedChan['remark'] ?? '') . "\n\n"
                . "🏷 <b>عنوان دکمه:</b> <code>" . htmlspecialchars($matchedChan['remark'] ?? '') . "</code>\n"
                . "🔗 <b>لینک عضویت:</b> " . htmlspecialchars($matchedChan['linkjoin'] ?? '') . "\n"
                . "🆔 <b>آیدی کانال:</b> <code>" . htmlspecialchars($matchedChan['link'] ?? '') . "</code>\n\n"
                . "یک گزینه را انتخاب کنید:";
            $kb = function_exists('rx_get_channel_manage_keyboard')
                ? rx_get_channel_manage_keyboard($chanId, true)
                : $channelkeyboard;
            nm_adminInstantReply($from_id, $msg, $kb, 'HTML');
        }
    } else {
        $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
        step('channel', $from_id);
        if (isset($user) && is_array($user)) {
            $user['step'] = 'channel';
        }
        if (function_exists('rxNavSetState')) {
            rxNavSetState($from_id, 'channel');
        }
        nm_adminInstantReply($from_id, "❌ کانال مورد نظر یافت نشد. لطفاً از لیست زیر انتخاب نمایید.", $channelkeyboard, 'HTML');
    }
} elseif ($adminrulecheck['rule'] == "administrator" && (
    (isset($datain) && preg_match('/^ch_del_(\d+)$/', (string)$datain, $delMatches)) ||
    ($text == "❌ حذف کانال" && !empty($user['Processing_value']) && is_numeric($user['Processing_value']))
)) {
    $chanId = isset($delMatches[1]) ? (int)$delMatches[1] : (int)$user['Processing_value'];
    $stmt = $pdo->prepare("DELETE FROM channels WHERE id = :id");
    $stmt->execute([':id' => $chanId]);
    update("user", "Processing_value", "none", "id", $from_id);
    step('channel', $from_id);
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'channel');
    }
    $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removedchannel'], $channelkeyboard, 'HTML');
} elseif ($adminrulecheck['rule'] == "administrator" && (
    (isset($datain) && preg_match('/^ch_editremark_(\d+)$/', (string)$datain, $editRemMatches)) ||
    ($text == "✏️ ویرایش عنوان دکمه" && !empty($user['Processing_value']) && is_numeric($user['Processing_value']))
)) {
    $chanId = isset($editRemMatches[1]) ? (int)$editRemMatches[1] : (int)$user['Processing_value'];
    update("user", "Processing_value", $chanId, "id", $from_id);
    step('ch_edit_remark', $from_id);
    $editBackKb = function_exists('rx_get_channel_edit_back_keyboard') ? rx_get_channel_edit_back_keyboard($chanId) : $backadmin;
    nm_adminInstantReply($from_id, "📌 عنوان جدید دکمه را ارسال کنید:", $editBackKb, 'HTML');
} elseif ($user['step'] == "ch_edit_remark" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    $chanId = (int)($user['Processing_value'] ?? 0);
    if ($chanId > 0) {
        $stmt = $pdo->prepare("UPDATE channels SET remark = :rem WHERE id = :id");
        $stmt->execute([':rem' => trim($text), ':id' => $chanId]);
    }
    if ($chanId > 0 && function_exists('rx_render_channel_manage')) {
        rx_render_channel_manage($chanId, $from_id, "✅ عنوان دکمه با موفقیت ویرایش شد.");
    } else {
        $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
        step('channel', $from_id);
        if (function_exists('rxNavSetState')) {
            rxNavSetState($from_id, 'channel');
        }
        nm_adminInstantReply($from_id, "✅ عنوان دکمه با موفقیت ویرایش شد.", $channelkeyboard, 'HTML');
    }
} elseif ($adminrulecheck['rule'] == "administrator" && (
    (isset($datain) && preg_match('/^ch_editjoin_(\d+)$/', (string)$datain, $editJoinMatches)) ||
    ($text == "🔗 ویرایش لینک عضویت" && !empty($user['Processing_value']) && is_numeric($user['Processing_value']))
)) {
    $chanId = isset($editJoinMatches[1]) ? (int)$editJoinMatches[1] : (int)$user['Processing_value'];
    update("user", "Processing_value", $chanId, "id", $from_id);
    step('ch_edit_linkjoin', $from_id);
    $editBackKb = function_exists('rx_get_channel_edit_back_keyboard') ? rx_get_channel_edit_back_keyboard($chanId) : $backadmin;
    nm_adminInstantReply($from_id, "📌 لینک جدید عضویت را ارسال کنید (مثال: https://t.me/...):", $editBackKb, 'HTML');
} elseif ($user['step'] == "ch_edit_linkjoin" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    $chanId = (int)($user['Processing_value'] ?? 0);
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        $editBackKb = function_exists('rx_get_channel_edit_back_keyboard') ? rx_get_channel_edit_back_keyboard($chanId) : $backadmin;
        nm_adminInstantReply($from_id, "❌ آدرس عضویت صحیح نمی باشد. لطفاً یک لینک معتبر ارسال کنید.", $editBackKb, 'HTML');
        return;
    }
    if ($chanId > 0) {
        $stmt = $pdo->prepare("UPDATE channels SET linkjoin = :lj WHERE id = :id");
        $stmt->execute([':lj' => trim($text), ':id' => $chanId]);
    }
    if ($chanId > 0 && function_exists('rx_render_channel_manage')) {
        rx_render_channel_manage($chanId, $from_id, "✅ لینک عضویت با موفقیت ویرایش شد.");
    } else {
        $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
        step('channel', $from_id);
        if (function_exists('rxNavSetState')) {
            rxNavSetState($from_id, 'channel');
        }
        nm_adminInstantReply($from_id, "✅ لینک عضویت با موفقیت ویرایش شد.", $channelkeyboard, 'HTML');
    }
} elseif ($adminrulecheck['rule'] == "administrator" && (
    (isset($datain) && preg_match('/^ch_editlink_(\d+)$/', (string)$datain, $editLinkMatches)) ||
    ($text == "🆔 ویرایش آیدی کانال" && !empty($user['Processing_value']) && is_numeric($user['Processing_value']))
)) {
    $chanId = isset($editLinkMatches[1]) ? (int)$editLinkMatches[1] : (int)$user['Processing_value'];
    update("user", "Processing_value", $chanId, "id", $from_id);
    step('ch_edit_link', $from_id);
    $editBackKb = function_exists('rx_get_channel_edit_back_keyboard') ? rx_get_channel_edit_back_keyboard($chanId) : $backadmin;
    nm_adminInstantReply($from_id, "📌 یوزرنیم کانال (با @) یا آیدی عددی کانال (که با -100 شروع می‌شود) را ارسال کنید:", $editBackKb, 'HTML');
} elseif ($user['step'] == "ch_edit_link" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    $chanId = (int)($user['Processing_value'] ?? 0);
    if ($chanId > 0) {
        $stmt = $pdo->prepare("UPDATE channels SET link = :lnk WHERE id = :id");
        $stmt->execute([':lnk' => trim($text), ':id' => $chanId]);
    }
    if ($chanId > 0 && function_exists('rx_render_channel_manage')) {
        rx_render_channel_manage($chanId, $from_id, "✅ آیدی کانال با موفقیت ویرایش شد.");
    } else {
        $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
        step('channel', $from_id);
        if (function_exists('rxNavSetState')) {
            rxNavSetState($from_id, 'channel');
        }
        nm_adminInstantReply($from_id, "✅ آیدی کانال با موفقیت ویرایش شد.", $channelkeyboard, 'HTML');
    }
} elseif (($text == $textbotlang['Admin']['channel']['removechannelbtn'] || $text == "❌ حذف کانال" || (isset($datain) && $datain == 'ch_del')) && $adminrulecheck['rule'] == "administrator") {
    $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
    step('channel', $from_id);
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'channel');
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removechannel'], $channelkeyboard, 'HTML');
} elseif ($user['step'] == "removechannel") {
    if (!isset($update['message']) && empty($text)) { return; }
    $stmt = $pdo->prepare("DELETE FROM channels WHERE link = :link OR id = :id");
    $stmt->bindValue(':link', $text, PDO::PARAM_STR);
    $stmt->bindValue(':id', is_numeric($text) ? (int)$text : 0, PDO::PARAM_INT);
    $stmt->execute();
    $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
    step('channel', $from_id);
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'channel');
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removedchannel'], $channelkeyboard, 'HTML');
} elseif ($datain == "addnewadmin" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
    step('addadmin', $from_id);
} elseif ($user['step'] == "addadmin") {
    if (!isset($update['message']) && empty($text)) { return; }
    $adminId = trim($text);
    if ($adminId === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $adminId, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['setrule'], $adminrule, 'HTML');
    step('getrule', $from_id);
} elseif ($user['step'] == "getrule") {
    if (!isset($update['message']) && empty($text)) { return; }
    $rule = ['administrator', 'Seller', 'support'];
    if (!in_array($text, $rule)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['invalidrule'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['addadminset'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $textbotlang['Admin']['manageadmin']['adminedsenduser'], null, 'HTML');
    step('home', $from_id);
    $usernamepanel = "root";
    $randomString = bin2hex(random_bytes(5));
    $randomStringHash = password_hash($randomString, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admin (id_admin, username, password, password_hash, rule) VALUES (:id_admin, :username, :password, :password_hash, :rule)");
    $stmt->bindParam(':id_admin', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
    $stmt->bindParam(':password', $randomString, PDO::PARAM_STR);
    $stmt->bindParam(':password_hash', $randomStringHash, PDO::PARAM_STR);
    $stmt->bindParam(':rule', $text, PDO::PARAM_STR);
    $stmt->execute();
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['adminadded'], $username, $from_id, $text, $user['Processing_value']);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/limitusertest_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['getid'], $backadmin, 'HTML');
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('get_number_limit', $from_id);
} elseif ($user['step'] == "get_number_limit") {
    if (!isset($update['message']) && empty($text)) { return; }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['setlimit'], $keyboardadmin, 'HTML');
    $id_user_set = $text;
    step('home', $from_id);
    update("user", "limit_usertest", $text, "id", $user['Processing_value']);
} elseif ($text == $textbotlang['Admin']['getlimitusertest']['setlimitbtn'] && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['limitall'], $backadmin, 'HTML');
    step('limit_usertest_allusers', $from_id);
} elseif ($user['step'] == "limit_usertest_allusers") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['setlimitall'], $keyboardadmin, 'HTML');
    step('home', $from_id);
    update("user", "limit_usertest", $text);
    update("setting", "limit_usertest_all", $text);
} elseif (($text == "📯 تنظیمات کانال" || $text == "🔙 بازگشت به لیست کانال‌ها" || (isset($datain) && in_array($datain, ['set_channel', 'ch_list'], true))) && $adminrulecheck['rule'] == "administrator") {
    $channelkeyboard = function_exists('rx_get_channel_keyboard') ? rx_get_channel_keyboard() : $channelkeyboard;
    step('channel', $from_id);
    if (isset($user) && is_array($user)) {
        $user['step'] = 'channel';
    }
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'channel');
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['description'], $channelkeyboard, 'HTML');
} elseif ($text == $textbotlang['Admin']['Status']['btn'] || $datain == "stat_all_bot") {
    $Balanceall = select("user", "SUM(Balance)", null, null, "select")['SUM(Balance)'];
    $statistics = rx_stats_total_users($pdo);
    $sumpanel = select("marzban_panel", "*", null, null, "count");
    $sql1 = "SELECT COUNT(id) AS count FROM user WHERE agent != 'f'";
    $stmt1 = $pdo->query($sql1);
    $agentsum = $stmt1->fetch(PDO::FETCH_ASSOC)['count'];
    $agentsumn = select("user", "COUNT(id)", "agent", "n", "select")['COUNT(id)'];
    $agentsumn2 = select("user", "COUNT(id)", "agent", "n2", "select")['COUNT(id)'];
    $sql1 = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE " . rx_stats_operational_service_predicate();
    $stmt1 = $pdo->query($sql1);
    $invoiceactive = $stmt1->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $invoice = rx_stats_total_sales_count($pdo);
    $sql2 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE " . rx_stats_operational_service_predicate();
    $stmt2 = $pdo->query($sql2);
    $invoicesum = $stmt2->fetch(PDO::FETCH_ASSOC)['total_price'];
    $invoiceTotal = rx_stats_total_sales_amount($pdo);
    $invoicesumall = number_format($invoiceTotal, 0);
    $extendsum = rx_stats_extend_paid_sum($pdo);
    $count_usertest = select("invoice", "*", "name_product", "سرویس تست", "count");
    $timeacc = jdate('H:i:s', time());
    $statisticsorder = rx_stats_paying_users_count($pdo);
    $statispay = rx_stats_real_payment_totals($pdo);
    $date = date("Y-m-d");
    $timeacc = jdate('H:i:s', time());
    $start_time = date('d.m.Y', strtotime("-1 days")) . " 00:00:00";
    $end_time = date('d.m.Y', strtotime("-1 days")) . " 23:59:59";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT SUM(price_product) FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . rx_stats_operational_service_predicate();
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $suminvoiceday = $stmt->fetch(PDO::FETCH_ASSOC)['SUM(price_product)'];
    $invoicesum = (float) ($invoicesum ?? 0);
    $extendsum = (float) ($extendsum ?? 0);
    $suminvoiceday = (float) ($suminvoiceday ?? 0);
    $statistics = (int) ($statistics ?? 0);
    $statisticsorder = (int) ($statisticsorder ?? 0);
    $paycount = "";
    $ratecustomer = round(safe_divide($statisticsorder * 100, $statistics, 0), 2);
    $averagePurchase = safe_divide($invoiceTotal, $statisticsorder, 0);
    $avgbuy_customer = $averagePurchase > 0 ? number_format($averagePurchase) : '0';
    $monthe_buy = number_format($suminvoiceday * 30);
    $percent_of_extend = round(safe_divide($extendsum * 100, $invoicesum, 0), 2);
    $percent_of_extend = $percent_of_extend > 100 ? 100 : $percent_of_extend;
    $extendsum = number_format($extendsum, 0);
    if (!empty($statispay)) {
        $statusLabels = [
            'cart to cart' => $datatextbot['carttocart'] ?? 'cart to cart',
            'aqayepardakht' => $datatextbot['aqayepardakht'] ?? 'aqayepardakht',
            'zarinpal' => $datatextbot['zarinpal'] ?? 'zarinpal',
            'zarinpey' => $datatextbot['zarinpey'] ?? 'zarinpey',
            'zarinpay' => $datatextbot['zarinpey'] ?? ($datatextbot['zarinpal'] ?? 'zarinpay'),
            'plisio' => $datatextbot['textnowpayment'] ?? 'plisio',
            'arze digital offline' => $datatextbot['textnowpaymenttron'] ?? 'arze digital offline',
            'Currency Rial 1' => $datatextbot['iranpay2'] ?? 'Currency Rial 1',
            'Currency Rial 2' => $datatextbot['iranpay3'] ?? 'Currency Rial 2',
            'Currency Rial 3' => $datatextbot['iranpay1'] ?? 'Currency Rial 3',
            'tonpay' => $datatextbot['tonpay'] ?? 'tonpay',
            'cubepay' => $datatextbot['cubepay'] ?? 'cubepay',
            'blupal' => $datatextbot['blupal'] ?? 'blupal',
            'abangateway' => $datatextbot['abangateway'] ?? 'abangateway',
            'atlaspay' => $datatextbot['atlaspay'] ?? 'atlaspay',
            'tetrapay' => $datatextbot['tetrapay'] ?? 'tetrapay',
            'paymentnotverify' => $datatextbot['textpaymentnotverify'] ?? 'paymentnotverify',
            'Star Telegram' => $datatextbot['text_star_telegram'] ?? 'Star Telegram',
        ];

        foreach ($statispay as $tracepay) {
            $paymentMethod = $tracepay['Payment_Method'] ?? '';
            $status_var = $statusLabels[$paymentMethod] ?? $paymentMethod;
            $paycount .= "
📌 نام درگاه : <code>$status_var</code>
 - تعداد پرداخت موفق : <code>{$tracepay['countpay']}</code>
 - جمع پرداختی ها : <code>{$tracepay['sumpay']}</code>\n";
        }
    }
    $bot_ping = 'نامشخص';
    $ping_start_time = microtime(true);
    $ping_response = telegram('getMe');
    $ping_duration = (microtime(true) - $ping_start_time) * 1000;
    if (is_array($ping_response) && !empty($ping_response['ok'])) {
        $bot_ping = number_format(max($ping_duration, 0), 0) . ' میلی‌ثانیه';
    }

    $statisticsall = "📊 <b>آمار کلی ربات</b>
━━━━━━━━━━━━━━━━━━
👥 <b>تعداد کل کاربران:</b> <code>$statistics</code> نفر
💳 <b>کاربران دارای خرید:</b> <code>$statisticsorder</code> نفر
🧪 <b>اکانت‌های تست:</b> <code>$count_usertest</code> نفر
💰 <b>موجودی کل کاربران:</b> <code>$Balanceall</code> تومان

🧾 <b>تعداد کل فروش:</b> <code>$invoice</code> عدد
🧾 <b>تعداد کل فروش سرویس های فعال:</b> <code>$invoiceactive</code> عدد
💵 <b>جمع کل فروش :</b> <code>$invoicesumall</code> تومان
💵 <b>جمع کل فروش سرویس های فعال:</b> <code>$invoicesum</code> تومان
🔄 <b>جمع کل تمدید:</b> <code>$extendsum</code> تومان
📈 <b>نرخ تبدیل به مشتری:</b> <code>$ratecustomer</code>٪
💳 <b>میانگین خرید هر مشتری:</b> <code>$avgbuy_customer</code> تومان
📅 <b>درآمد پیش‌بینی‌شده ماهانه:</b> <code>$monthe_buy</code> تومان
📊 <b>درصد تمدید از فروش:</b> <code>$percent_of_extend</code>٪

👨‍💼 <b>تعداد کل نمایندگان:</b> <code>$agentsum</code> نفر
🔹 <b>نمایندگان نوع N:</b> <code>$agentsumn</code> نفر
🔸 <b>نمایندگان نوع N2:</b> <code>$agentsumn2</code> نفر
🧩 <b>تعداد پنل‌ها:</b> <code>$sumpanel</code> عدد
📡 <b>پینگ ربات:</b> $bot_ping
$paycount
";
    if ($datain == "stat_all_bot") {
        Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $statisticsall, $keyboard_stat, 'HTML');
    }
} elseif ($datain == "close_stat") {
    deletemessage($from_id, $message_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($datain == "hoursago_stat") {
    $desired_date_time_start = time() - 3600;
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . rx_stats_historical_sale_predicate();
    $stmt = $pdo->prepare($sql);
    $time_current = time();
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extend_user' AND status = 'paid'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $extra_time_stat['count'];
    $sum_change_location = number_format($extra_time_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $countextendday = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ۱ ساعت گذشته</b>

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countextendday نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "yesterday_stat") {
    $start_time = date('Y/m/d', strtotime("-1 days")) . " 00:00:00";
    $end_time = date('Y/m/d', strtotime("-1 days")) . " 23:59:59";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . rx_stats_historical_sale_predicate();
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status = 'paid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار روز گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "today_stat") {
    $start_time = date('Y/m/d') . " 00:00:00";
    $end_time = date('Y/m/d H:i:s');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . rx_stats_historical_sale_predicate();
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status = 'paid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار روز فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "month_old_stat") {
    $firstDayLastMonth = new DateTime('first day of last month');
    $lastDayLastMonth = new DateTime('last day of last month');
    $start_time = $firstDayLastMonth->format('Y/m/d');
    $end_time = $lastDayLastMonth->format('Y/m/d');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . rx_stats_historical_sale_predicate();
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status = 'paid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ماه گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "month_current_stat") {
    $firstDayLastMonth = new DateTime('first day of this month');
    $lastDayLastMonth = new DateTime('last day of this month');
    $start_time = $firstDayLastMonth->format('Y/m/d');
    $end_time = $lastDayLastMonth->format('Y/m/d');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . rx_stats_historical_sale_predicate();
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status = 'paid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ماه فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "view_stat_time") {
    nm_adminInstantReply($from_id, sprintf($textbotlang['Admin']['getstats'], date('Y/m/d')), $backadmin, 'HTML');
    step("get_time_start", $from_id);
} elseif ($user['step'] == "get_time_start") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!isValidDate($text)) {
        nm_adminInstantReply($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
        return;
    }
    savedata("clear", "start_time", $text);
    nm_adminInstantReply($from_id, "تاریخ پایان را ارسال کنید بطور مثال :  \n<code>2025/09/08</code>", $backadmin, 'HTML');
    step("get_time_end", $from_id);
} elseif ($user['step'] == "get_time_end") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!isValidDate($text)) {
        nm_adminInstantReply($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $start_time = $userdata['start_time'] . "00:00:00";
    $end_time = $text . "23:59:00";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . rx_stats_historical_sale_predicate();
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status = 'paid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار تاریخ انتخابی</b>

⏳ بازه تایم  : $start_time تا $end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    step('home', $from_id);
    nm_adminInstantReply($from_id, $statisticsall, $keyboardadmin, 'HTML');
} elseif ($datain == "settingaffiliatesf" || $text == "🎁 زیرمجموعه") {
    step('featnav_affiliates', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $affiliates, 'HTML');
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['addpanel'] && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['gettypepanel'], $keyboardtypepanel, 'HTML');
} elseif (preg_match('/^typepanel#(marzban|rebecca|pasarguard|pasargard|remnawave|guard|x-ui_single|Manualsale)$/', (string) $datain, $dataget)) {
    $typepanel = $dataget[1] === 'pasargard' ? 'pasarguard' : $dataget[1];
    $rx_inline_mode = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline');
    if ($rx_inline_mode) {
        $rx_addpanel_back_kb = json_encode([
            'inline_keyboard' => [
                [['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'admin']]
            ],
        ]);
    } else {
        $rx_addpanel_back_kb = $backadmin;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $rx_addpanel_back_kb, 'HTML');
    step("add_name_panel", $from_id);
    savedata("clear", "type", $typepanel);
} elseif ($user['step'] == "add_name_panel") {
    if (!isset($update['message']) && empty($text)) { return; }
    $panelNameInput = trim((string) $text);
    if ($panelNameInput === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $backadmin, 'HTML');
        return;
    }
    $existingPanel = select("marzban_panel", "*", "name_panel", $panelNameInput, "select", ['cache' => false]);
    if (is_array($existingPanel) && !empty($existingPanel)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || !in_array((string) ($userdata['type'] ?? ''), ['marzban', 'rebecca', 'pasarguard', 'remnawave', 'guard', 'x-ui_single', 'Manualsale'], true)) {
        nm_adminInstantReply($from_id, "❌ اطلاعات افزودن پنل منقضی شده است. لطفاً نوع پنل را دوباره انتخاب کنید.", $keyboardtypepanel, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("save", "namepanel", $panelNameInput);
    if ($userdata['type'] == "Manualsale") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('getlimitedpanel', $from_id);
        savedata("save", "url_panel", "null");
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelurl'], $backadmin, 'HTML');
    step('add_link_panel', $from_id);
} elseif ($user['step'] == "add_link_panel") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $normalizedPanelUrl = rtrim($text, '/');
    savedata("save", "url_panel", $normalizedPanelUrl);
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || empty($userdata['type']) || empty($userdata['namepanel'])) {
        nm_adminInstantReply($from_id, "❌ اطلاعات افزودن پنل ناقص یا منقضی شده است. لطفاً افزودن پنل را از ابتدا انجام دهید.", $keyboardtypepanel, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($userdata['type'] == "guard") {
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        $guardVersionKb = json_encode([
            'inline_keyboard' => [
                [['text' => 'v1', 'callback_data' => 'guardversion#v1']],
                [['text' => 'v2', 'callback_data' => 'guardversion#v2']],
            ],
        ]);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['guard']['getversion'] ?? '🛡 نسخه پنل Guard را انتخاب کنید:', $guardVersionKb, 'HTML');
        step('add_guard_version', $from_id);
        return;
    } elseif ($userdata['type'] == "WGDashboard") {
        nm_adminInstantReply($from_id, "📌 توکن را ارسال نمایید", $backadmin, 'HTML');
        step('add_password_panel', $from_id);
        savedata("save", "username", "null");
        return;
    } elseif ($userdata['type'] == "remnawave") {
        nm_adminInstantReply($from_id, "📌 توکن ثابت API رمن‌ویو را ارسال نمایید.
(از مسیر API Tokens در داشبورد ادمین رمن‌ویو بسازید)", $backadmin, 'HTML');
        step('add_remna_token_setup', $from_id);
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    } elseif ($userdata['type'] == "rebecca") {
        nm_adminInstantReply($from_id, "🔑 لطفاً API Key پنل Rebecca را ارسال کنید.", $backadmin, 'HTML');
        step('add_rebecca_api_key', $from_id);
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    } elseif ($userdata['type'] == "pasarguard") {
        $pasarguardAuthKb = json_encode([
            'inline_keyboard' => [
                [['text' => '👤 نام کاربری / رمز عبور', 'callback_data' => 'pasarguardauth#password']],
                [['text' => '🔑 API Key', 'callback_data' => 'pasarguardauth#api_key']],
            ],
        ]);
        nm_adminInstantReply($from_id, "🔐 روش احراز هویت پنل PasarGuard را انتخاب کنید:", $pasarguardAuthKb, 'HTML');
        step('add_pasarguard_auth_method', $from_id);
        return;
    } elseif ($userdata['type'] == "x-ui_single") {
        $xuiModeKb = json_encode([
            'inline_keyboard' => [
                [['text' => $textbotlang['Admin']['managepanel']['xuimodelegacy'], 'callback_data' => 'xuimode#legacy']],
                [['text' => $textbotlang['Admin']['managepanel']['xuimodetoken'], 'callback_data' => 'xuimode#token']],
            ],
        ]);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuimode'], $xuiModeKb, 'HTML');
        step('add_xui_api_mode', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
    step('add_username_panel', $from_id);
} elseif ($user['step'] == "add_guard_version" && preg_match('/guardversion#(v1|v2)/', $datain, $dataget)) {
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
    savedata("save", "guard_version", $guardVersion);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
    step('add_guard_api_key', $from_id);
} elseif ($user['step'] == "add_xui_api_mode" && preg_match('/xuimode#(legacy|token)/', $datain, $dataget)) {
    $xuiMode = $dataget[1];
    savedata("save", "xui_api_mode", $xuiMode);
    if ($xuiMode === 'token') {
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuiapitoken'], $backadmin, 'HTML');
        step('add_xui_api_token', $from_id);
    } else {
        savedata("save", "xui_api_token", "null");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
        step('add_username_panel', $from_id);
    }
} elseif ($user['step'] == "add_xui_api_token") {
    if (!isset($update['message']) && empty($text)) { return; }
    $xuiApiToken = trim($text);
    if ($xuiApiToken === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidxuiapitoken'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "xui_api_token", $xuiApiToken);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
} elseif ($user['step'] == "add_remna_token_setup") {
    if (!isset($update['message']) && empty($text)) { return; }
    $remnaApiToken = trim((string) $text);
    if ($remnaApiToken === '') {
        nm_adminInstantReply($from_id, "❌ توکن API نمی‌تواند خالی باشد.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "remna_api_token", $remnaApiToken);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
} elseif ($user['step'] == "add_rebecca_api_key") {
    if (!isset($update['message']) && empty($text)) { return; }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $rebeccaBaseUrl = rebeccaGetBaseUrl(isset($userdata['url_panel']) ? $userdata['url_panel'] : '');
    try {
        $connectionResult = rebeccaTestConnection($rebeccaBaseUrl, $apiKey);
    } catch (Throwable $rebeccaConnectionError) {
        $connectionResult = ['status' => false, 'msg' => $rebeccaConnectionError->getMessage()];
    }
    if (!is_array($connectionResult) || empty($connectionResult['status'])) {
        $errorMessage = is_array($connectionResult) ? ($connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey']) : $textbotlang['Admin']['managepanel']['invalidapikey'];
        $feedback = "❌ اتصال به Rebecca ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
        nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
        step('add_rebecca_api_key', $from_id);
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
    savedata("save", "api_key", $apiKey);
    savedata("save", "url_panel", $rebeccaBaseUrl);
    savedata("save", "rebecca_service_id", "auto");
    nm_adminInstantReply($from_id, "✅ اتصال به Rebecca برقرار شد.\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
} elseif ($user['step'] == "add_username_panel") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (trim((string) $text) === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
    step('add_password_panel', $from_id);
    savedata("save", "username", trim((string) $text));
} elseif ($user['step'] == "add_guard_api_key") {
    if (!isset($update['message']) && empty($text)) { return; }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $guardBaseUrl = guardGetBaseUrl(isset($userdata['url_panel']) ? $userdata['url_panel'] : '');
    $guardVersionForTest = isset($userdata['guard_version']) && $userdata['guard_version'] === 'v2' ? 'v2' : 'v1';
    try {
        $connectionResult = guardTestConnection($guardBaseUrl, $apiKey, $guardVersionForTest);
    } catch (Throwable $guardConnectionError) {
        $connectionResult = ['status' => false, 'msg' => $guardConnectionError->getMessage()];
    }
    if (!is_array($connectionResult) || empty($connectionResult['status'])) {
        $errorMessage = is_array($connectionResult) ? ($connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey']) : $textbotlang['Admin']['managepanel']['invalidapikey'];
        $feedback = "❌ اتصال به گارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
        nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    savedata("save", "api_key", $apiKey);
    savedata("save", "url_panel", $guardBaseUrl);
    nm_adminInstantReply(
        $from_id,
        "✅ اتصال به گارد برقرار شد.\n\n⚠️ توجه: سرویس‌های قابل ساخت روی این پنل هنوز تنظیم نشده‌اند. پیش از فروش، از منوی «مدیریت پنل‌ها ← ⚙️ تنظیم سرویس‌ها» آن‌ها را مشخص کنید؛ در غیر این صورت ساخت سرویس روی این پنل با خطا مواجه می‌شود.\n\n"
            . $textbotlang['Admin']['managepanel']['getlimitedpanel'],
        $backadmin,
        'HTML'
    );
    step('getlimitedpanel', $from_id);
} elseif ($user['step'] == "add_pasarguard_auth_method" && preg_match('/pasarguardauth#(api_key|password)/', $datain, $dataget)) {
    $pasarguardAuthMethod = $dataget[1];
    savedata("save", "pasarguard_auth_mode", $pasarguardAuthMethod);
    if ($pasarguardAuthMethod === 'password') {
        savedata("save", "api_key", "null");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
        step('add_pasarguard_username', $from_id);
    } else {
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        nm_adminInstantReply($from_id, "🔑 لطفاً API Key پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
        step('add_pasarguard_api_key', $from_id);
    }
} elseif ($user['step'] == "add_pasarguard_username") {
    if (!isset($update['message']) && empty($text)) { return; }
    $pasarguardUsername = trim((string) $text);
    if ($pasarguardUsername === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "username", $pasarguardUsername);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
    step('add_pasarguard_password', $from_id);
} elseif ($user['step'] == "add_pasarguard_password") {
    if (!isset($update['message']) && empty($text)) { return; }
    $pasarguardPassword = trim((string) $text);
    if ($pasarguardPassword === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pasarguardBaseUrl = rtrim((string) ($userdata['url_panel'] ?? ''), '/');
    $pasarguardUsernameForTest = (string) ($userdata['username'] ?? '');
    try {
        $connectionResult = pasarguardTestConnectionUserPass($pasarguardBaseUrl, $pasarguardUsernameForTest, $pasarguardPassword);
    } catch (Throwable $pasarguardConnectionError) {
        $connectionResult = ['status' => false, 'msg' => $pasarguardConnectionError->getMessage()];
    }
    if (!is_array($connectionResult) || empty($connectionResult['status'])) {
        $errorMessage = is_array($connectionResult) ? ($connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['getpassword']) : $textbotlang['Admin']['managepanel']['getpassword'];
        $feedback = "❌ اتصال به پاسارگارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً نام کاربری و رمز عبور را بررسی کرده و رمز عبور را مجدداً ارسال کنید.";
        nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
        step('add_pasarguard_password', $from_id);
        return;
    }
    savedata("save", "password", $pasarguardPassword);
    savedata("save", "url_panel", $pasarguardBaseUrl);
    nm_adminInstantReply($from_id, "✅ اتصال به پاسارگارد برقرار شد.\n\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
} elseif ($user['step'] == "add_pasarguard_api_key") {
    if (!isset($update['message']) && empty($text)) { return; }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pasarguardBaseUrl = rtrim((string) ($userdata['url_panel'] ?? ''), '/');
    try {
        $connectionResult = pasarguardTestConnection($pasarguardBaseUrl, $apiKey);
    } catch (Throwable $pasarguardConnectionError) {
        $connectionResult = ['status' => false, 'msg' => $pasarguardConnectionError->getMessage()];
    }
    if (!is_array($connectionResult) || empty($connectionResult['status'])) {
        $errorMessage = is_array($connectionResult) ? ($connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey']) : $textbotlang['Admin']['managepanel']['invalidapikey'];
        $feedback = "❌ اتصال به پاسارگارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
        nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
        step('add_pasarguard_api_key', $from_id);
        return;
    }
    savedata("save", "api_key", $apiKey);
    savedata("save", "url_panel", $pasarguardBaseUrl);
    nm_adminInstantReply($from_id, "✅ اتصال به پاسارگارد برقرار شد.\n\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
} elseif ($user['step'] == "guard_svc_edit" && preg_match('/^guardsvc:(t):(\d+)$|^guardsvc:(a|n|s|x)$/', $datain, $matches)) {
    $guardSvcLock = rx_callback_lock_acquire($from_id, 'guardsvc', 2.5);
    if ($guardSvcLock === false) {
        rx_callback_busy_reply($callback_query_id);
        return;
    }

    try {
        $guardSvcFresh = select("user", "*", "id", $from_id, "select", ['cache' => false]);
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
        $availableIds = guardExtractServiceIdsFromList($services);
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
            update("user", "Processing_value", $state['panel'], "id", $from_id);
            step('PanelMenu', $from_id);
            rx_callback_lock_release($guardSvcLock);
            $guardSvcLock = false;
            rx_releaseWebhookConnection();
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '', 'show_alert' => false, 'cache_time' => 0]);
            deletemessage($from_id, $message_id);
            outtypepanel('guard', '⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید');
            return;
        } elseif (($matches[3] ?? '') === 's') {
            if (empty($selected)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '⚠️ حداقل یک سرویس را انتخاب کنید', 'show_alert' => true, 'cache_time' => 0]);
                return;
            }
            $storageValue = (count($selected) === count($availableIds)) ? '0' : json_encode(array_values($selected));
            update("marzban_panel", "guard_service_ids", $storageValue, "name_panel", $state['panel']);
            $state['selected'] = $selected;
            savedata("save", "guard_svc", $state);
            rx_callback_lock_release($guardSvcLock);
            $guardSvcLock = false;
            rx_releaseWebhookConnection();
            rx_answer_and_edit($callback_query_id, 'ذخیره شد.', $from_id, $message_id, guardSvcRenderText($services, $selected) . "\n\n✅ ذخیره شد.", guardSvcRenderKeyboard($services, $selected));
            return;
        }

        $state['selected'] = $selected;
        savedata("save", "guard_svc", $state);

        rx_callback_lock_release($guardSvcLock);
        $guardSvcLock = false;
        rx_releaseWebhookConnection();

        rx_answer_and_edit($callback_query_id, '', $from_id, $message_id, guardSvcRenderText($services, $selected), guardSvcRenderKeyboard($services, $selected));
    } finally {
        rx_callback_lock_release($guardSvcLock);
    }
} elseif (strpos((string) $datain, 'guardsvc:') === 0) {
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'این صفحه منقضی شده. دوباره از منوی مدیریت پنل وارد شوید.',
        'show_alert' => true,
        'cache_time' => 0,
    ]);
    return;
} elseif ($user['step'] == "add_password_panel") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (trim((string) $text) === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
    savedata("save", "password", trim((string) $text));
} elseif ($user['step'] == "getlimitedpanel") {
    if (!isset($update['message']) && empty($text)) { return; }
    $userdata = json_decode($user['Processing_value'], true);
    $supportedPanelTypes = ['marzban', 'rebecca', 'pasarguard', 'remnawave', 'guard', 'x-ui_single', 'Manualsale'];
    $panelDraftValid = is_array($userdata)
        && in_array((string) ($userdata['type'] ?? ''), $supportedPanelTypes, true)
        && trim((string) ($userdata['namepanel'] ?? '')) !== ''
        && trim((string) ($userdata['url_panel'] ?? '')) !== ''
        && array_key_exists('username', $userdata)
        && array_key_exists('password', $userdata);
    if ($panelDraftValid && in_array($userdata['type'], ['rebecca', 'guard'], true)) {
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
        nm_adminInstantReply($from_id, "❌ اطلاعات افزودن پنل ناقص یا منقضی شده است. لطفاً افزودن پنل را از ابتدا انجام دهید.", $keyboardtypepanel, 'HTML');
        step('home', $from_id);
        return;
    }
    savedata("save", "limitpanel", $text);
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
        $guardColumnsCheck = ensureGuardPanelColumnsReady($pdo);
        if ($guardColumnsCheck['status'] === false) {
            $missingList = implode(', ', $guardColumnsCheck['missing']);
            $warningMessage = "❌ امکان ثبت پنل Guard وجود ندارد. ستون‌های موردنیاز یافت نشدند: {$missingList}\nلطفاً یکبار دیگر صفحه را اجرا کنید تا مهاجرت خودکار انجام شود و سپس مجدداً تلاش نمایید.";
            nm_adminInstantReply($from_id, $warningMessage, $backadmin, 'HTML');
            step('add_guard_api_key', $from_id);
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
    $shopFeaturesDefault = panel_features_all_off_json();
    $stmt = $pdo->prepare("INSERT INTO marzban_panel (code_panel,name_panel,sublink,config,MethodUsername,TestAccount,status,limit_panel,namecustom,Methodextend,type,conecton,inboundid,agent,inbound_deactive,inboundstatus,url_panel,username_panel,password_panel,api_key,time_usertest,val_usertest,linksubx,priceextravolume,priceextratime,pricecustomvolume,pricecustomtime,mainvolume,maxvolume,maintime,maxtime,status_extend,subvip,changeloc,customvolume,on_hold_test,version_panel,guard_service_ids,guard_note,guard_auto_delete_days,guard_auto_renewals,guard_version,shop_features,rebecca_service_id) VALUES (:code_panel,:name_panel,:sublink,:config,:MethodUsername,:TestAccount,:status,:limit_panel,:namecustom,:Methodextend,:type,:conecton,:inboundid,:agent,:inbound_deactive,:inboundstatus,:url_panel,:username_panel,:password_panel,:api_key,:val_usertest,:time_usertest,:linksubx,:priceextravolume,:priceextratime,:pricecustomvolume,:pricecustomtime,:mainvolume,:maxvolume,:maintime,:maxtime,:status_extend,:subvip,:changeloc,:customvolume,:on_hold_test,:version_panel,:guard_service_ids,:guard_note,:guard_auto_delete_days,:guard_auto_renewals,:guard_version,:shop_features,:rebecca_service_id)");
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
        nm_adminInstantReply($from_id, "❌ ثبت پنل انجام نشد. نام پنل و اطلاعات اتصال را بررسی کنید و دوباره تلاش نمایید.", $backadmin, 'HTML');
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
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addedpanel'], $keyboardadmin, 'HTML');
    nm_adminInstantReply($from_id, "🥳", $keyboardadmin, 'HTML');
    step("home", $from_id);
    if ($userdata['type'] == "x-ui_single") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم شناسه اینباند و دامنه لینک ساب را حتما تنظیم نمایید در غیراینصورت کانفیگ ساخته نخواهد شد", null, 'HTML');
    } elseif ($userdata['type'] == "marzban") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم پروتکل و اینباند را تنظیم نمایید تا ربات کانفیگ دهد در غیراینصورت کانفیگ به  کاربر داده نمی شود", null, 'HTML');
    } elseif ($userdata['type'] == "WGDashboard") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
منوی تنظیم شناسه اینباند رفته و نام کانفیگ را تنظیم نمایید در غیراینصورت ربات هیچ کانفیگی نمیسازد", null, 'HTML');
    } elseif ($userdata['type'] == "remnawave") {
        nm_adminInstantReply($from_id, "✅ پنل رمن‌ویو با موفقیت ثبت شد. لطفاً شناسه Squad خود را حتماً تنظیم کرده و سپس دکمه تست اتصال را بزنید.", null, 'HTML');
    } elseif ($userdata['type'] == "rebecca") {
        nm_adminInstantReply($from_id, "✅ پنل Rebecca با موفقیت ثبت شد. در صورت نیاز سرویس پیش‌فرض را از منوی مدیریت پنل تغییر دهید.", null, 'HTML');
    } elseif ($userdata['type'] == "pasarguard") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم پروتکل و اینباند را تنظیم نمایید تا ربات کانفیگ دهد در غیراینصورت کانفیگ به  کاربر داده نمی شود", null, 'HTML');
    }
}

elseif ($user['step'] == "switchtype_pick" && preg_match('/switchtype#(.*)/', $datain, $dataget)) {
    $newType = $dataget[1];
    savedata("save", "new_type", $newType);
    if ($newType == "Manualsale") {
        savedata("save", "url_panel", "null");
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('switchtype_confirm', $from_id);
    } else {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelurl'], $backadmin, 'HTML');
        step('switchtype_link_panel', $from_id);
    }
} elseif ($user['step'] == "switchtype_link_panel") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $normalizedPanelUrl = rtrim($text, '/');
    savedata("save", "url_panel", $normalizedPanelUrl);
    $userdata = json_decode($user['Processing_value'], true);
    if ($userdata['new_type'] == "guard") {
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        $guardVersionKb = json_encode([
            'inline_keyboard' => [
                [['text' => 'v1', 'callback_data' => 'switchguardversion#v1']],
                [['text' => 'v2', 'callback_data' => 'switchguardversion#v2']],
            ],
        ]);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['guard']['getversion'] ?? '🛡 نسخه پنل Guard را انتخاب کنید:', $guardVersionKb, 'HTML');
        step('switchtype_guard_version', $from_id);
    } elseif ($userdata['new_type'] == "WGDashboard") {
        savedata("save", "username", "null");
        nm_adminInstantReply($from_id, "📌 توکن را ارسال نمایید", $backadmin, 'HTML');
        step('switchtype_password_panel', $from_id);
    } elseif ($userdata['new_type'] == "remnawave") {
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        nm_adminInstantReply($from_id, "📌 توکن ثابت API رمن‌ویو را ارسال نمایید.
(از مسیر API Tokens در داشبورد ادمین رمن‌ویو بسازید)", $backadmin, 'HTML');
        step('switchtype_remna_token', $from_id);
    } elseif ($userdata['new_type'] == "rebecca") {
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        nm_adminInstantReply($from_id, "🔑 لطفاً API Key پنل Rebecca را ارسال کنید.", $backadmin, 'HTML');
        step('switchtype_rebecca_api_key', $from_id);
    } elseif ($userdata['new_type'] == "pasarguard" || $userdata['new_type'] == "pasargard") {
        savedata("save", "new_type", "pasarguard");
        $switchPasarguardAuthKb = json_encode([
            'inline_keyboard' => [
                [['text' => '👤 نام کاربری / رمز عبور', 'callback_data' => 'switchpasarguardauth#password']],
                [['text' => '🔑 API Key', 'callback_data' => 'switchpasarguardauth#api_key']],
            ],
        ]);
        nm_adminInstantReply($from_id, "🔐 روش احراز هویت پنل PasarGuard را انتخاب کنید:", $switchPasarguardAuthKb, 'HTML');
        step('switchtype_pasarguard_auth_method', $from_id);
    } elseif ($userdata['new_type'] == "x-ui_single") {
        $xuiModeKb = json_encode([
            'inline_keyboard' => [
                [['text' => $textbotlang['Admin']['managepanel']['xuimodelegacy'], 'callback_data' => 'switchxuimode#legacy']],
                [['text' => $textbotlang['Admin']['managepanel']['xuimodetoken'], 'callback_data' => 'switchxuimode#token']],
            ],
        ]);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuimode'], $xuiModeKb, 'HTML');
        step('switchtype_xui_api_mode', $from_id);
    } else {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
        step('switchtype_username_panel', $from_id);
    }
} elseif ($user['step'] == "switchtype_xui_api_mode" && preg_match('/switchxuimode#(legacy|token)/', $datain, $dataget)) {
    $xuiMode = $dataget[1];
    savedata("save", "xui_api_mode", $xuiMode);
    if ($xuiMode === 'token') {
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getxuiapitoken'], $backadmin, 'HTML');
        step('switchtype_xui_token', $from_id);
    } else {
        savedata("save", "xui_api_token", "null");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
        step('switchtype_username_panel', $from_id);
    }
} elseif ($user['step'] == "switchtype_xui_token") {
    if (!isset($update['message']) && empty($text)) { return; }
    $xuiApiToken = trim($text);
    if ($xuiApiToken === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidxuiapitoken'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "xui_api_token", $xuiApiToken);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('switchtype_confirm', $from_id);
} elseif ($user['step'] == "switchtype_remna_token") {
    if (!isset($update['message']) && empty($text)) { return; }
    savedata("save", "remna_api_token", trim($text));
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('switchtype_confirm', $from_id);
} elseif ($user['step'] == "switchtype_username_panel") {
    if (!isset($update['message']) && empty($text)) { return; }
    savedata("save", "username", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
    step('switchtype_password_panel', $from_id);
} elseif ($user['step'] == "switchtype_password_panel") {
    if (!isset($update['message']) && empty($text)) { return; }
    savedata("save", "password", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('switchtype_confirm', $from_id);
} elseif ($user['step'] == "switchtype_guard_version" && preg_match('/switchguardversion#(v1|v2)/', $datain, $dataget)) {
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
    savedata("save", "guard_version", $guardVersion);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
    step('switchtype_guard_api_key', $from_id);
} elseif ($user['step'] == "switchtype_guard_api_key") {
    if (!isset($update['message']) && empty($text)) { return; }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "api_key", $apiKey);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('switchtype_confirm', $from_id);
} elseif ($user['step'] == "switchtype_rebecca_api_key") {
    if (!isset($update['message']) && empty($text)) { return; }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "api_key", $apiKey);
    savedata("save", "rebecca_service_id", "auto");
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('switchtype_confirm', $from_id);
} elseif ($user['step'] == "switchtype_pasarguard_auth_method" && preg_match('/switchpasarguardauth#(api_key|password)/', $datain, $dataget)) {
    $pasarguardAuthMethod = $dataget[1];
    savedata("save", "pasarguard_auth_mode", $pasarguardAuthMethod);
    if ($pasarguardAuthMethod === 'password') {
        savedata("save", "api_key", "null");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
        step('switchtype_pasarguard_username', $from_id);
    } else {
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        nm_adminInstantReply($from_id, "🔑 لطفاً API Key پنل PasarGuard را ارسال کنید.", $backadmin, 'HTML');
        step('switchtype_pasarguard_api_key', $from_id);
    }
} elseif ($user['step'] == "switchtype_pasarguard_username") {
    if (!isset($update['message']) && empty($text)) { return; }
    $pasarguardUsername = trim((string) $text);
    if ($pasarguardUsername === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "username", $pasarguardUsername);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
    step('switchtype_pasarguard_password', $from_id);
} elseif ($user['step'] == "switchtype_pasarguard_password") {
    if (!isset($update['message']) && empty($text)) { return; }
    $pasarguardPassword = trim((string) $text);
    if ($pasarguardPassword === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pasarguardBaseUrl = rtrim((string) ($userdata['url_panel'] ?? ''), '/');
    $pasarguardUsernameForTest = (string) ($userdata['username'] ?? '');
    $connectionResult = pasarguardTestConnectionUserPass($pasarguardBaseUrl, $pasarguardUsernameForTest, $pasarguardPassword);
    if (empty($connectionResult['status'])) {
        $errorMessage = $connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['getpassword'];
        $feedback = "❌ اتصال به پاسارگارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً نام کاربری و رمز عبور را بررسی کرده و رمز عبور را مجدداً ارسال کنید.";
        nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
        step('switchtype_pasarguard_password', $from_id);
        return;
    }
    savedata("save", "password", $pasarguardPassword);
    savedata("save", "url_panel", $pasarguardBaseUrl);
    nm_adminInstantReply($from_id, "✅ اتصال به پاسارگارد برقرار شد.\n\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('switchtype_confirm', $from_id);
} elseif ($user['step'] == "switchtype_pasarguard_api_key") {
    if (!isset($update['message']) && empty($text)) { return; }
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pasarguardBaseUrl = rtrim((string) ($userdata['url_panel'] ?? ''), '/');
    $connectionResult = pasarguardTestConnection($pasarguardBaseUrl, $apiKey);
    if ($connectionResult['status'] === false) {
        $errorMessage = $connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey'];
        $feedback = "❌ اتصال به پاسارگارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
        nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
        step('switchtype_pasarguard_api_key', $from_id);
        return;
    }
    savedata("save", "api_key", $apiKey);
    savedata("save", "url_panel", $pasarguardBaseUrl);
    nm_adminInstantReply($from_id, "✅ اتصال به پاسارگارد برقرار شد.\n\n" . $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('switchtype_confirm', $from_id);
} elseif ($user['step'] == "switchtype_confirm") {
    $userdata = json_decode($user['Processing_value'], true);
    $codePanel = $userdata['code_panel'];
    $namePanel = $userdata['name_panel'];
    $finalType = $userdata['new_type'];
    $versionPanel = '0';
    $urlPanel = isset($userdata['url_panel']) ? $userdata['url_panel'] : 'null';
    $usernamePanel = isset($userdata['username']) ? $userdata['username'] : 'null';
    $passwordPanel = isset($userdata['password']) ? $userdata['password'] : 'null';
    $apiKey = isset($userdata['api_key']) ? $userdata['api_key'] : null;
    $xuiApiMode = isset($userdata['xui_api_mode']) ? $userdata['xui_api_mode'] : 'legacy';
    $xuiApiToken = isset($userdata['xui_api_token']) ? $userdata['xui_api_token'] : null;
    $remnaApiToken = isset($userdata['remna_api_token']) ? $userdata['remna_api_token'] : null;
    $pasarguardAuthMode = (isset($userdata['pasarguard_auth_mode']) && $userdata['pasarguard_auth_mode'] === 'password') ? 'password' : 'api_key';
    $sqlSwitchType = "UPDATE marzban_panel SET type = :type, version_panel = :version_panel, url_panel = :url_panel, username_panel = :username_panel, password_panel = :password_panel, api_key = :api_key, xui_api_mode = :xui_api_mode, xui_api_token = :xui_api_token, remna_api_token = :remna_api_token";
    if ($finalType == "guard") {
        $sqlSwitchType .= ", guard_service_ids = :guard_service_ids, guard_version = :guard_version";
    }
    if ($finalType == "rebecca") {
        $sqlSwitchType .= ", rebecca_service_id = :rebecca_service_id";
    }
    if ($finalType == "pasarguard") {
        $sqlSwitchType .= ", pasarguard_auth_mode = :pasarguard_auth_mode";
    }
    $sqlSwitchType .= " WHERE code_panel = :code_panel";
    $stmt = $pdo->prepare($sqlSwitchType);
    $stmt->bindParam(':type', $finalType);
    $stmt->bindParam(':version_panel', $versionPanel);
    $stmt->bindParam(':url_panel', $urlPanel);
    $stmt->bindParam(':username_panel', $usernamePanel);
    $stmt->bindParam(':password_panel', $passwordPanel);
    $stmt->bindParam(':api_key', $apiKey);
    $stmt->bindParam(':xui_api_mode', $xuiApiMode);
    $stmt->bindParam(':xui_api_token', $xuiApiToken);
    $stmt->bindParam(':remna_api_token', $remnaApiToken);
    if ($finalType == "pasarguard") {
        $stmt->bindParam(':pasarguard_auth_mode', $pasarguardAuthMode);
    }
    if ($finalType == "guard") {
        $guardServiceIds = null;
        $guardVersionSwitch = isset($userdata['guard_version']) && $userdata['guard_version'] === 'v2' ? 'v2' : 'v1';
        $stmt->bindParam(':guard_service_ids', $guardServiceIds);
        $stmt->bindParam(':guard_version', $guardVersionSwitch);
    }
    if ($finalType == "rebecca") {
        $rebeccaServiceIdSwitch = isset($userdata['rebecca_service_id']) ? $userdata['rebecca_service_id'] : 'auto';
        $stmt->bindParam(':rebecca_service_id', $rebeccaServiceIdSwitch);
    }
    $stmt->bindParam(':code_panel', $codePanel);
    $stmt->execute();
    update("user", "Processing_value", $namePanel, "id", $from_id);
    outtypepanel($finalType, "✅ نوع پنل با موفقیت تغییر کرد.");
}

elseif ($datain == "systemsms") {

    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus !== null) {
        Editmessagetext(
            $from_id,
            $message_id,
            nm_buildBroadcastStatusText($broadcastStatus),
            nm_buildBroadcastStatusKeyboard($broadcastStatus),
            'HTML'
        );
        return;
    }

    if (!is_file('cronbot/users.json')) {
        @file_put_contents('cronbot/users.json', json_encode([]));
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "ارسال همگانی", 'callback_data' => 'typeservice-sendmessage'],
            ],
            [
                ['text' => "فوروارد همگانی", 'callback_data' => 'typeservice-forwardmessage'],
            ],
            [
                ['text' => "تعداد روزی که استفاده نکردند", 'callback_data' => 'typeservice-xdaynotmessage'],
            ],
            [
                ['text' => "لغو پیام های پین شده", 'callback_data' => 'typeservice-unpinmessage'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $listbtn);
} elseif ($datain == "broadcast_status_refresh") {

    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus === null) {
        Editmessagetext(
            $from_id,
            $message_id,
            "✅ عملیات ارسال پیامی در حال انجام نیست.\n\nبرای شروع یک ارسال جدید از منوی اصلی وارد بخش پیام‌رسانی شوید.",
            json_encode([
                'inline_keyboard' => [
                    [['text' => "📨 منوی پیام‌رسانی",  'callback_data' => 'systemsms']],
                    [['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser']],
                ]
            ]),
            'HTML'
        );
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => 'عملیاتی در حال انجام نیست.',
                'show_alert'        => false,
                'cache_time'        => 0,
            ]);
        }
        return;
    }
    Editmessagetext(
        $from_id,
        $message_id,
        nm_buildBroadcastStatusText($broadcastStatus),
        nm_buildBroadcastStatusKeyboard($broadcastStatus),
        'HTML'
    );
    if (!empty($callback_query_id)) {
        $toast = '🚀 ارسال‌شده: ' . number_format((int) $broadcastStatus['sent'])
               . ' | 📊 باقی‌مانده: ' . number_format((int) $broadcastStatus['remaining']);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text'              => $toast,
            'show_alert'        => false,
            'cache_time'        => 0,
        ]);
    }
    return;
} elseif (preg_match('/^broadcast_resume_([0-9a-f]{12})$/', $datain, $rxResumeMatch)) {
    $rxResumeResult = 'not_found';
    if (function_exists('rx_broadcast_info_update')) {
        rx_broadcast_info_update('cronbot/info', $rxResumeMatch[1], static function (array &$c) use (&$rxResumeResult) {
            if (!rx_broadcast_resume_now($c, true)) {
                $rxResumeResult = 'not_paused';
                return false;
            }
            $rxResumeResult = 'resumed';
        });
    }
    $rxResumeToast = [
        'resumed'    => '▶️ عملیات از همان نقطه ادامه می‌یابد (حداکثر تا یک دقیقه دیگر).',
        'not_paused' => 'این عملیات در حالت توقف نیست.',
        'not_found'  => 'این عملیات دیگر وجود ندارد یا لغو شده است.',
    ][$rxResumeResult];
    if (!empty($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text'              => $rxResumeToast,
            'show_alert'        => $rxResumeResult !== 'resumed',
            'cache_time'        => 0,
        ]);
    }
    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus !== null) {
        Editmessagetext(
            $from_id,
            $message_id,
            nm_buildBroadcastStatusText($broadcastStatus),
            nm_buildBroadcastStatusKeyboard($broadcastStatus),
            'HTML'
        );
    } else {
        Editmessagetext($from_id, $message_id, "❌ عملیات ارسال پیامی برای ادامه وجود ندارد.", null, 'HTML');
    }
    return;
} elseif (preg_match('/^typeservice-(\w+)/', $datain, $dataget)) {

    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus !== null) {
        Editmessagetext(
            $from_id,
            $message_id,
            nm_buildBroadcastStatusText($broadcastStatus),
            nm_buildBroadcastStatusKeyboard($broadcastStatus),
            'HTML'
        );
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => '⏳ یک عملیات ارسال در حال انجام است.',
                'show_alert'        => true,
                'cache_time'        => 0,
            ]);
        }
        return;
    }
    $type = $dataget[1];
    savedata("clear", "typeservice", $type);
    if ($type == "unpinmessage") {
        deletemessage($from_id, $message_id);
        $typesend = [
            "unpinmessage" => "لغو پیام پین شده"
        ][$type];
        $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend";
        $startaction = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "تایید و شروع عملیات", 'callback_data' => 'startaction'],
                ],
            ]
        ]);
        nm_adminInstantReply($from_id, $textconfirm, $startaction, 'HTML');
        nm_adminInstantReply($from_id, "با تایید گزینه بالا فرآیند ارسال شروع خواهد شد", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typeusermessage-all'],
            ],
            [
                ['text' => "مشتریانی که خرید داشتند", 'callback_data' => 'typeusermessage-customer'],
            ],
            [
                ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typeusermessage-nonecustomer'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'systemsms'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 سرویس برای کدام گروه کاربری اعمال شود؟", $listbtn);
} elseif (preg_match('/^typeusermessage-(\w+)/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typeusermessage", $dataget[1]);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typeagent-all'],
            ],
            [
                ['text' => "کاربران گروه f", 'callback_data' => 'typeagent-f'],
            ],
            [
                ['text' => "کاربران گروه n", 'callback_data' => 'typeagent-n'],
            ],
            [
                ['text' => "کاربران گروه n2", 'callback_data' => 'typeagent-n2'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeservice-' . $userdata['typeservice']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 سرویس برای چه دسته از کاربران اعمال شود؟", $listbtn);
} elseif (preg_match('/^typeagent-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $type);
    if ($userdata['typeusermessage'] == "customer") {
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE agent = :agent OR agent = 'all'");
        $stmt->bindParam(':agent', $type);
        $stmt->execute();
        $list_panel = ['inline_keyboard' => []];
        $list_panel['inline_keyboard'][] = [['text' => "تمامی پنل ها", 'callback_data' => 'locationmessage_all']];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $list_panel['inline_keyboard'][] = [
                ['text' => $result['name_panel'], 'callback_data' => "locationmessage_{$result['code_panel']}"]
            ];
        }
        $list_panel['inline_keyboard'][] = [['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],];
        Editmessagetext($from_id, $message_id, "📌 پیام برای کدام کاربران موجود در پنل های زیر ارسال شود.", json_encode($list_panel));
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif (preg_match('/^pinduration-(\w+)/', $datain, $dataget)) {
    $duration = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "pinduration", $duration);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "دکمه استارت", 'callback_data' => 'btntypemessage-start'],
                ['text' => "دکمه آموزش", 'callback_data' => 'btntypemessage-helpbtn'],
            ],
            [
                ['text' => "دکمه خرید", 'callback_data' => 'btntypemessage-buy'],
                ['text' => "دکمه اکانت تست", 'callback_data' => 'btntypemessage-usertestbtn'],
            ],
            [
                ['text' => "دکمه زیرمجموعه گیری ", 'callback_data' => 'btntypemessage-affiliatesbtn'],
                ['text' => "شارژ حساب کاربری", 'callback_data' => 'btntypemessage-addbalance'],
            ],
            [
                ['text' => "ارسال بدون دکمه", 'callback_data' => 'btntypemessage-none'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 اگر می خواهید زیر پیام دکمه ای نمایش داده شود از لیست زیر گزینه ای را انتخاب کنید در غیر اینصورت دکمه  ارسال بدون دکمه را بزنید", $listbtn);
} elseif (preg_match('/^locationmessage_(\w+)/', $datain, $dataget)) {
    $typeoanel = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "selectpanel", $typeoanel);
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif (preg_match('/^typepinmessage-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typepinmessage", $type);
    if ($type == "yes") {
        $listpinduration = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "پین ۲۴ ساعته", 'callback_data' => 'pinduration-24'],
                    ['text' => "پین ۴۸ ساعته", 'callback_data' => 'pinduration-48'],
                ],
                [
                    ['text' => "بدون تایم (نامحدود)", 'callback_data' => 'pinduration-none'],
                ],
                [
                    ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 مدت زمان پین ماندن پیام را انتخاب کنید. پس از این مدت پیام به‌صورت خودکار از پین خارج می‌شود.", $listpinduration);
        return;
    }
    savedata("save", "pinduration", "none");
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "دکمه استارت", 'callback_data' => 'btntypemessage-start'],
                ['text' => "دکمه آموزش", 'callback_data' => 'btntypemessage-helpbtn'],
            ],
            [
                ['text' => "دکمه خرید", 'callback_data' => 'btntypemessage-buy'],
                ['text' => "دکمه اکانت تست", 'callback_data' => 'btntypemessage-usertestbtn'],
            ],
            [
                ['text' => "دکمه زیرمجموعه گیری ", 'callback_data' => 'btntypemessage-affiliatesbtn'],
                ['text' => "شارژ حساب کاربری", 'callback_data' => 'btntypemessage-addbalance'],
            ],
            [
                ['text' => "ارسال بدون دکمه", 'callback_data' => 'btntypemessage-none'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
            ],
        ]
    ]);
    if ($userdata['typeservice'] == "forwardmessage") {
        step("gettextSystemMessage", $from_id);
        nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    Editmessagetext($from_id, $message_id, "📌 اگر می خواهید زیر پیام دکمه ای نمایش داده شود از لیست زیر گزینه ای را انتخاب کنید در غیر اینصورت دکمه  ارسال بدون دکمه را بزنید", $listbtn);
} elseif (preg_match('/^btntypemessage-(\w+)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $type = $dataget[1];
    savedata("save", "btntypemessage", $type);
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif ($user['step'] == "gettextday") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "daynoyuse", $text);
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif ($user['step'] == "gettextSystemMessage") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "forwardmessage") {
        savedata("save", "message", $message_id);
    } elseif ($userdata['typeservice'] == "xdaynotmessage") {
        if ($text) {
            savedata("save", "message", $text);
        } else {
            nm_adminInstantReply($from_id, "📌  در بخش کاربرانی که به تعداد روز تعیین شده استفاده نکردند فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
            return;
        }
    } elseif ($userdata['typeservice'] == "sendmessage") {
        if ($text) {
            savedata("save", "message", $text);
        } else {
            nm_adminInstantReply($from_id, "📌  در بخش ارسال همگانی فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
            return;
        }
    }
    $typesend = [
        "xdaynotmessage" => "کاربرانی که به تعداد روز تعیین شده استفاده نکردند",
        "sendmessage" => "ارسال همگانی",
        "forwardmessage" => "فوروارد همگانی",
        "unpinmessage" => "لغو پیام پین شده"
    ][$userdata['typeservice']];
    $typeservice = [
        "all" => "ارسال به همه کاربران",
        "customer" => "مشتریان",
        "nonecustomer" => "کسانی که خرید نداشتند",
    ][$userdata['typeusermessage']];
    if ($userdata['typeservice'] == "xdaynotmessage") {
        $textday = "تعداد روزی که کاربر پیام نداده است : {$userdata['daynoyuse']}";
    } else {
        $textday = "";
    }
    $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend
🎛 نوع سرویس : $typeservice
🗂 نوع کاربری : {$userdata['agent']}
$textday
";
    $startaction = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و شروع عملیات", 'callback_data' => 'startaction'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textconfirm, $startaction, 'HTML');
    nm_adminInstantReply($from_id, "با تایید گزینه بالا فرآیند ارسال شروع خواهد شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "startaction") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    $agent = $userdata['agent'];
    $typeservice = $userdata['typeservice'];
    $typeusermessage = $userdata['typeusermessage'];
    $text = $userdata['message'];
    $cancelmessage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
            ],
        ]
    ]);

    @ini_set('memory_limit', '1G');
    @set_time_limit(300);

    if (!function_exists('nm_writeBroadcastQueueFromJson')) {
        function nm_writeBroadcastQueueFromJson($jsonStr) {
            $arr = is_string($jsonStr) ? json_decode($jsonStr) : $jsonStr;
            if (!is_array($arr)) {
                return 0;
            }
            $tmp = "cronbot/users.txt.new";
            $fh  = @fopen($tmp, 'w');
            if (!$fh) {
                return 0;
            }
            $count = 0;
            foreach ($arr as $row) {
                $id = null;
                if (is_object($row) && isset($row->id))     $id = $row->id;
                elseif (is_array($row) && isset($row['id']))$id = $row['id'];
                elseif (is_scalar($row))                    $id = $row;
                if ($id !== null && $id !== '' && is_numeric($id)) {
                    fwrite($fh, ((string) $id) . "\n");
                    $count++;
                }
            }
            fclose($fh);
            @unlink('cronbot/users.json');
            @unlink('cronbot/users.txt');
            @rename($tmp, 'cronbot/users.txt');

            $arr = null;
            unset($arr);
            return $count;
        }
    }

    if ($typeservice == "unpinmessage") {
        $userlist = json_encode(select("user", "id", null, null, "fetchAll"));
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $dataunpin = json_encode(array(
            "id_admin" => $from_id,
            'type' => "unpinmessage",
            "id_message" => $message_id['result']['message_id']
        ));
        nm_writeBroadcastQueueFromJson($userlist); $userlist = null;
        file_put_contents('cronbot/info', $dataunpin);
    } elseif ($typeservice == "sendmessage") {
        if ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE  u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "pinduration" => $userdata['pinduration'] ?? 'none',
            "btnmessage" => $userdata['btntypemessage']
        ));
        $rxBuilt = nm_writeBroadcastQueueFromJson($userslist); $userslist = null;        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "forwardmessage") {
        if ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "forwardmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "pinduration" => $userdata['pinduration'] ?? 'none',
        ));
        $rxBuilt = nm_writeBroadcastQueueFromJson($userslist); $userslist = null;        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "xdaynotmessage") {
        $timedaystamp = intval($userdata['daynoyuse']) * 86400;
        $timenouser = time() - $timedaystamp;
        if ($agent == "all") {
            $stmt = $pdo->prepare("SELECT id FROM user  WHERE last_message_time < $timenouser");
            $stmt->execute();
            $userslist = json_encode($stmt->fetchAll());
        } else {
            if ($typeusermessage == "all") {
                if ($typeusermessage == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "customer") {
                    if ($userdata['selectpanel'] == "all") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    } else {
                        $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                    }
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "nonecustomer") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                }
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "xdaynotmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "pinduration" => $userdata['pinduration'] ?? 'none',
            "btnmessage" => $userdata['btntypemessage']
        ));
        $rxBuilt = nm_writeBroadcastQueueFromJson($userslist); $userslist = null;        file_put_contents('cronbot/info', $data);
    }
} elseif ($datain == "cancel_sendmessage") {
    @file_put_contents('users.json', json_encode(array()));
    if (function_exists('rx_broadcast_cancel')) {
        rx_broadcast_cancel('cronbot');
    } else {
        @unlink('cronbot/users.json');
        @unlink('cronbot/users.txt');
        @unlink('cronbot/users.txt.new');
        @unlink('cronbot/users.txt.tail.tmp');
        @unlink('cronbot/info');
    }
    deletemessage($from_id, $message_id);
    nm_adminInstantReply($from_id, "❌ عملیات ارسال پیام لغو شد و قابل ادامه نیست.", null, 'HTML');
}

elseif ($text == "📝 تنظیم متن ربات" && $adminrulecheck['rule'] == "administrator") {
    step('home', $from_id);
    nm_adminInstantReply($from_id, "📝 این بخش به پنل تحت وب منتقل شده است.\n\nبرای ویرایش متن‌های ربات به پنل مدیریت مراجعه کنید.", $keyboardadmin, 'HTML');
} elseif ($text == "📱 متن پیشنهاد مینی‌اپ" && $adminrulecheck['rule'] == "administrator") {
    $_ms_cur = htmlspecialchars((string)($datatextbot['miniapp_suggest_1'] ?? ''), ENT_QUOTES);
    nm_adminInstantReply($from_id, "📝 <b>متن پیشنهاد مینی‌اپ</b>\n\nمتن فعلی:\n<code>{$_ms_cur}</code>\n\n📌 متن جدید را ارسال کنید:", $backadmin, 'HTML');
    step('edit_miniapp_suggest_text', $from_id);
} elseif ($user['step'] == 'edit_miniapp_suggest_text' && $adminrulecheck['rule'] == "administrator") {
    if ($text !== '') {
        update("textbot", "text", $text, "id_text", "miniapp_suggest_1");
        nm_adminInstantReply($from_id, "✅ متن با موفقیت ذخیره شد.", $textbot, 'HTML');
    } else {
        nm_adminInstantReply($from_id, "❌ متن نمی‌تواند خالی باشد.", $textbot, 'HTML');
    }
    step('home', $from_id);
} elseif ($user['step'] == "premium_emoji_get_char" && $adminrulecheck['rule'] == "administrator") {
    try {
        $rxPemRawText = is_string($text) ? trim($text) : '';
        $rxPemSticker = $update['message']['sticker'] ?? null;

        $rxPemBase = '';
        if ($rxPemRawText !== '') {
            $rxPemBase = $rxPemRawText;
        } elseif (is_array($rxPemSticker) && !empty($rxPemSticker['emoji'])) {
            $rxPemBase = (string)$rxPemSticker['emoji'];
        }

        if ($rxPemBase === '' || mb_strlen($rxPemBase, 'UTF-8') > 50) {
            nm_adminInstantReply($from_id, "❌ ایموجی نامعتبر است.\n\nلطفاً یک <b>ایموجی عادی</b> ارسال کنید (مثل ✅، ❌، 🔥، 💎).", json_encode([
                'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            return;
        }

        $rxPemPrevVal = (string)($user['Processing_value'] ?? '');
        $rxPemMode = (strpos($rxPemPrevVal, 'batch:') === 0) ? 'batch' : 'single';
        update("user", "Processing_value", $rxPemMode . ':' . $rxPemBase, "id", $from_id);

        nm_adminInstantReply($from_id, "✅ <b>ایموجی پایه ذخیره شد:</b> {$rxPemBase}\n\n📌 حالا <b>ایموجی پرمیوم متناظر</b> را ارسال کنید.\n\nربات خودکار آیدی آن را تشخیص می‌دهد و این دو را به هم متصل می‌کند ✨\n\n💡 می‌توانید ایموجی پرمیوم را به هر شکلی بفرستید: متن، استیکر، Forward یا Reply.", json_encode([
            'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('premium_emoji_get_id', $from_id);
    } catch (\Throwable $rxPemErr) {
        @error_log('[premium_emoji_get_char] EXCEPTION: ' . $rxPemErr->getMessage() . ' @ ' . $rxPemErr->getFile() . ':' . $rxPemErr->getLine());
        $rxPemErrMsg = htmlspecialchars($rxPemErr->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی</b>\n\n<code>{$rxPemErrMsg}</code>", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
} elseif ($user['step'] == "premium_emoji_get_id" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    try {
        $rxPemRaw = (string)($user['Processing_value'] ?? '');
        if (strpos($rxPemRaw, 'batch:') === 0) {
            $rxPemMode = 'batch';
            $rxPemBase = substr($rxPemRaw, 6);
        } elseif (strpos($rxPemRaw, 'single:') === 0) {
            $rxPemMode = 'single';
            $rxPemBase = substr($rxPemRaw, 7);
        } else {
            $rxPemMode = 'single';
            $rxPemBase = $rxPemRaw;
        }
        if ($rxPemBase === '') {
            nm_adminInstantReply($from_id, "❌ ایموجی پایه یافت نشد. دوباره از ابتدا شروع کنید.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            step('home', $from_id);
            return;
        }

        $rxPemTableOk = true;
        try {
            $rxPemCheck = $pdo->query("SHOW TABLES LIKE 'premium_emojis'");
            $rxPemTableOk = ($rxPemCheck && $rxPemCheck->fetchColumn() !== false);
        } catch (\Throwable $rxPemTblErr) { $rxPemTableOk = false; }
        if (!$rxPemTableOk) {
            nm_adminInstantReply($from_id, "❌ <b>جدول دیتابیس آماده نیست</b>\n\nقبل از این، باید <code>table.php</code> را در مرورگر اجرا کنید.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            step('home', $from_id);
            return;
        }

        $rxPemCid = '';

        $rxPemEntities = $update['message']['entities'] ?? $update['message']['caption_entities'] ?? [];
        if (is_array($rxPemEntities)) {
            foreach ($rxPemEntities as $rxPemEnt) {
                if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                    $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                    break;
                }
            }
        }

        if ($rxPemCid === '') {
            $rxPemSticker = $update['message']['sticker'] ?? null;
            if (is_array($rxPemSticker) && ($rxPemSticker['type'] ?? '') === 'custom_emoji'
                && !empty($rxPemSticker['custom_emoji_id'])) {
                $rxPemCid = (string)$rxPemSticker['custom_emoji_id'];
            }
        }

        if ($rxPemCid === '') {
            $rxPemReply = $update['message']['reply_to_message'] ?? null;
            if (is_array($rxPemReply)) {
                $rxPemReplyEntities = $rxPemReply['entities'] ?? $rxPemReply['caption_entities'] ?? [];
                if (is_array($rxPemReplyEntities)) {
                    foreach ($rxPemReplyEntities as $rxPemEnt) {
                        if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                            $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                            break;
                        }
                    }
                }
                if ($rxPemCid === '') {
                    $rxPemReplySticker = $rxPemReply['sticker'] ?? null;
                    if (is_array($rxPemReplySticker)
                        && ($rxPemReplySticker['type'] ?? '') === 'custom_emoji'
                        && !empty($rxPemReplySticker['custom_emoji_id'])) {
                        $rxPemCid = (string)$rxPemReplySticker['custom_emoji_id'];
                    }
                }
            }
        }

        if ($rxPemCid === '' && is_string($text)) {
            $rxPemCandidate = trim($text);
            if (ctype_digit($rxPemCandidate) && strlen($rxPemCandidate) >= 8 && strlen($rxPemCandidate) <= 30) {
                $rxPemCid = $rxPemCandidate;
            }
        }

        if ($rxPemCid === '') {
            $rxPemDiag = '';
            $rxPemSentText = is_string($text) ? trim($text) : '';
            $rxPemStkType = is_array($update['message']['sticker'] ?? null) ? ($update['message']['sticker']['type'] ?? '') : '';
            if ($rxPemStkType !== '' && $rxPemStkType !== 'custom_emoji') {
                $rxPemDiag = "🔎 شما یک <b>استیکر معمولی</b> فرستادید (نوع آن custom_emoji نیست).";
            } elseif ($rxPemSentText !== '' && mb_strlen($rxPemSentText, 'UTF-8') <= 4) {
                $rxPemDiag = "🔎 شما یک <b>ایموجی عادی</b> فرستادید: <b>{$rxPemSentText}</b>\nاین فاقد متادیتای پرمیوم است.";
            } elseif (ctype_digit($rxPemSentText)) {
                $rxPemDiag = "🔎 آیدی عددی نامعتبر است (طول باید بین ۸ تا ۳۰ رقم باشد).";
            }
            if ($rxPemDiag !== '') { $rxPemDiag .= "\n\n"; }
            nm_adminInstantReply($from_id, "❌ ایموجی پرمیوم یافت نشد.\n\n{$rxPemDiag}📌 لطفاً <b>ایموجی پرمیوم</b> را برای ایموجی پایه «{$rxPemBase}» ارسال کنید.\n\n💡 یا اگر آیدی عددی پرمیوم را دارید، آن را پیست کنید.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            return;
        }

        $rxPemNow = time();
        $rxPemAlreadyExists = false;
        try {
            $rxPemIns = $pdo->prepare("INSERT INTO premium_emojis (emoji, custom_emoji_id, created_at, updated_at) VALUES (:e, :c, :t1, :t2)");
            $rxPemIns->execute([':e' => $rxPemBase, ':c' => $rxPemCid, ':t1' => $rxPemNow, ':t2' => $rxPemNow]);
        } catch (\Throwable $rxPemInsErr) {

            if (stripos($rxPemInsErr->getMessage(), 'Duplicate') !== false || stripos($rxPemInsErr->getMessage(), '1062') !== false) {
                $rxPemAlreadyExists = true;
                try {
                    $rxPemTouch = $pdo->prepare("UPDATE premium_emojis SET updated_at = :t WHERE emoji = :e AND custom_emoji_id = :c");
                    $rxPemTouch->execute([':t' => $rxPemNow, ':e' => $rxPemBase, ':c' => $rxPemCid]);
                } catch (\Throwable $rxPemTouchErr) {  }
            } else {
                throw $rxPemInsErr;
            }
        }
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }

        if ($rxPemAlreadyExists) {
            $rxPemSuccessMsg = "ℹ️ <b>این ایموجی پرمیوم با همین آیدی قبلاً ثبت شده بود</b>\n\n"
                . "• ایموجی پایه: {$rxPemBase}\n"
                . "• 🆔 آیدی پرمیوم: <code>{$rxPemCid}</code>\n\n"
                . "هیچ ردیف تکراری اضافه نشد.";
        } else {
            $rxPemSuccessMsg = "✅ <b>ایموجی پرمیوم جدید با موفقیت اضافه شد!</b>\n\n"
                . "• ایموجی پایه: {$rxPemBase}\n"
                . "• 🆔 آیدی پرمیوم: <code>{$rxPemCid}</code>\n\n"
                . "✨ از این لحظه، در پیام‌های ربات «{$rxPemBase}» به نسخه پرمیوم تبدیل می‌شود.\n\n"
                . "💡 می‌توانید برای همین «{$rxPemBase}» آیدی‌های پرمیوم بیشتری هم اضافه کنید — جدیدترین آیدی به‌صورت فعال استفاده می‌شود و قبلی‌ها در لیست باقی می‌مانند.";
        }
        if ($rxPemMode === 'batch') {
            $rxPemButtons = [
                [['text' => "➕ ادامه افزودن", 'callback_data' => "premium_emoji_batch_continue"]],
                [['text' => "✅ تایید و پایان", 'callback_data' => "premium_emoji_batch_end"]],
            ];
        } else {
            $rxPemButtons = [
                [['text' => "➕ افزودن ایموجی دیگر", 'callback_data' => "premium_emoji_add"]],
                [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
            ];
        }
        nm_adminInstantReply($from_id, $rxPemSuccessMsg, json_encode(['inline_keyboard' => $rxPemButtons]), 'HTML');
        step('home', $from_id);
    } catch (\Throwable $rxPemErr) {
        @error_log('[premium_emoji_get_id] EXCEPTION: ' . $rxPemErr->getMessage() . ' @ ' . $rxPemErr->getFile() . ':' . $rxPemErr->getLine());
        $rxPemErrMsg = htmlspecialchars($rxPemErr->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی هنگام ذخیره ایموجی پرمیوم</b>\n\n<code>{$rxPemErrMsg}</code>", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
} elseif ($user['step'] == "premium_emoji_edit_id" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    try {
    $rxPemRowId = (int)($user['Processing_value'] ?? 0);
    if ($rxPemRowId <= 0) {
        nm_adminInstantReply($from_id, "❌ خطا: ردیف ایموجی یافت نشد.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    $rxPemCid = '';
    $rxPemEntities = $update['message']['entities'] ?? $update['message']['caption_entities'] ?? [];
    if (is_array($rxPemEntities)) {
        foreach ($rxPemEntities as $rxPemEnt) {
            if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                break;
            }
        }
    }

    $rxPemSticker = $update['message']['sticker'] ?? null;
    if ($rxPemCid === '' && is_array($rxPemSticker)) {
        if (($rxPemSticker['type'] ?? '') === 'custom_emoji' && !empty($rxPemSticker['custom_emoji_id'])) {
            $rxPemCid = (string)$rxPemSticker['custom_emoji_id'];
        }
    }

    $rxPemReply = $update['message']['reply_to_message'] ?? null;
    if ($rxPemCid === '' && is_array($rxPemReply)) {
        $rxPemReplyEntities = $rxPemReply['entities'] ?? $rxPemReply['caption_entities'] ?? [];
        if (is_array($rxPemReplyEntities)) {
            foreach ($rxPemReplyEntities as $rxPemEnt) {
                if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                    $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                    break;
                }
            }
        }
        $rxPemReplySticker = $rxPemReply['sticker'] ?? null;
        if ($rxPemCid === '' && is_array($rxPemReplySticker)
            && ($rxPemReplySticker['type'] ?? '') === 'custom_emoji'
            && !empty($rxPemReplySticker['custom_emoji_id'])) {
            $rxPemCid = (string)$rxPemReplySticker['custom_emoji_id'];
        }
    }
    if ($rxPemCid === '' && is_string($text)) {
        $rxPemCandidate = trim($text);
        if (ctype_digit($rxPemCandidate) && strlen($rxPemCandidate) >= 8 && strlen($rxPemCandidate) <= 30) {
            $rxPemCid = $rxPemCandidate;
        }
    }
    if ($rxPemCid === '') {
        nm_adminInstantReply($from_id, "❌ آیدی ایموجی پرمیوم یافت نشد.\n\n📌 لطفاً <b>ایموجی پرمیوم</b> را ارسال کنید یا آیدی عددی را وارد نمایید.", null, 'HTML');
        return;
    }
    $rxPemNow = time();
    $rxPemStmt = $pdo->prepare("UPDATE premium_emojis SET custom_emoji_id = :c, updated_at = :t WHERE id = :id");
    $rxPemStmt->execute([':id' => $rxPemRowId, ':c' => $rxPemCid, ':t' => $rxPemNow]);
    if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
    nm_adminInstantReply($from_id, "✅ ایموجی پرمیوم به‌روزرسانی شد.\n\n🆔 <code>{$rxPemCid}</code>", json_encode([
        'inline_keyboard' => [
            [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
        ]
    ]), 'HTML');
    step('home', $from_id);
    } catch (\Throwable $rxPemEditErr) {
        @error_log('[premium_emoji_edit_id] EXCEPTION: ' . $rxPemEditErr->getMessage() . ' @ ' . $rxPemEditErr->getFile() . ':' . $rxPemEditErr->getLine());
        $rxPemErrMsg = htmlspecialchars($rxPemEditErr->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی هنگام ویرایش ایموجی پرمیوم</b>\n\n<code>{$rxPemErrMsg}</code>", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
} elseif (preg_match('/sendmessageuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    savedata("clear", "iduser", $iduser);
    nm_adminInstantReply($from_id, "📌 متن یا تصویر خود را ارسال نمایید", $backadmin, 'HTML');
    step('sendmessagetext', $from_id);
} elseif ($user['step'] == "sendmessagetext") {
    if ($photo) {
        savedata("save", "type", "photo");
        savedata("save", "photoid", $photoid);
        savedata("save", "text", $caption);
    } else {
        savedata("save", "text", $text);
        savedata("save", "type", "text");
    }
    $textb = "📌 کاربر بتواند پاسخ دهد یاخیر ؟
1 - بله  پاسخ دهد
2 - خیر پاسخ ندهد
پاسخ را به عدد ارسال کنید";
    nm_adminInstantReply($from_id, $textb, $backadmin, 'HTML');
    step('sendmessagetid', $from_id);
} elseif ($user['step'] == "sendmessagetid") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $textsendadmin = "
👤 یک پیام از طرف ادمین ارسال شده است
متن پیام:

{$userdata['text']}";
    if (intval($text) == "1") {
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
                ],
            ]
        ]);
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'reply_markup' => $Response,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, $Response, 'HTML');
        }
    } else {
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, null, 'HTML');
        }
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📤 فوروارد پیام برای یک کاربر") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetText'], $backadmin, 'HTML');
    step('getmessageforward', $from_id);
} elseif ($user['step'] == "getmessageforward") {
    savedata("clear", "messageid", $message_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetIDMessage'], $backadmin, 'HTML');
    step('getbtnresponseforward', $from_id);
} elseif ($user['step'] == "getbtnresponseforward") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    forwardMessage($from_id, $userdata['messageid'], $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📚 بخش آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardhelpadmin, 'HTML');
} elseif (($text == "📥 دریافت پیش‌فرض‌ها" || $datain == "help_load_default") && $adminrulecheck['rule'] == "administrator") {
    // Issue #27: load the bundled default bot-usage tutorials into the help table.
    // Idempotent: entries whose name_os already exist are left untouched, so an
    // admin's customized tutorials are never overwritten.
    $rxHelpRoot = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
    $rxHelpDefaultsFile = $rxHelpRoot . '/default_help.json';
    if (!is_file($rxHelpDefaultsFile)) {
        nm_adminInstantReply($from_id, "❌ فایل default_help.json در سورس ربات یافت نشد.", $keyboardhelpadmin, 'HTML');
        return;
    }
    $rxHelpDefaults = json_decode((string) file_get_contents($rxHelpDefaultsFile), true);
    if (!is_array($rxHelpDefaults) || empty($rxHelpDefaults)) {
        nm_adminInstantReply($from_id, "❌ فایل default_help.json معتبر نیست.", $keyboardhelpadmin, 'HTML');
        return;
    }
    $rxHelpAdded = 0;
    $rxHelpSkipped = 0;
    $rxHelpInsert = $pdo->prepare("INSERT INTO help (name_os,Media_os,type_Media_os,category,Description_os,app_title,app_link) VALUES (?,?,?,?,?,?,?)");
    foreach ($rxHelpDefaults as $rxHelpItem) {
        $rxHelpName = trim((string) ($rxHelpItem['name_os'] ?? ''));
        if ($rxHelpName === '') { $rxHelpSkipped++; continue; }
        if (select("help", "id", "name_os", $rxHelpName, "select") !== false) { $rxHelpSkipped++; continue; }
        $rxHelpInsert->execute([
            $rxHelpName,
            '',
            'text',
            (string) ($rxHelpItem['category'] ?? ''),
            (string) ($rxHelpItem['description'] ?? ''),
            (string) ($rxHelpItem['app_title'] ?? ''),
            (string) ($rxHelpItem['app_link'] ?? ''),
        ]);
        $rxHelpAdded++;
    }
    nm_adminInstantReply($from_id, "📥 آموزش‌های پیش‌فرض بارگذاری شد.\n\n✅ اضافه‌شده: {$rxHelpAdded}\n↩️ از قبل موجود (دست‌نخورده): {$rxHelpSkipped}", $keyboardhelpadmin, 'HTML');
    return;
} elseif ($text == "📚 افزودن آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddNameHelp'], $backadmin, 'HTML');
    step('add_name_help', $from_id);
} elseif ($user['step'] == "add_name_help") {
    if (strlen($text) >= 150) {
        nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    $helpexits = select("help", "*", "name_os", $text, "count");
    if ($helpexits != 0) {
        nm_adminInstantReply($from_id, "❌ نام آموزش وجود دارد از نام دیگری استفاده نمایید.", null, 'HTML');
        return;
    }
    $stmt = $connect->prepare("INSERT IGNORE INTO help (name_os) VALUES (?)");
    $stmt->bind_param("s", $text);
    $stmt->execute();
    update("user", "Processing_value", $text, "id", $from_id);
    if ($setting['categoryhelp'] == "0") {
        update("help", "category", "0", "name_os", $user['Processing_value']);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
        step('add_dec', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, "📌 نام دسته بندی برای آموزش را ارسال نمایید", $backadmin, 'HTML');
    step('getcatgoryhelp', $from_id);
} elseif ($user['step'] == "getcatgoryhelp") {
    update("help", "category", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
    step('add_dec', $from_id);
} elseif ($user['step'] == "add_dec") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($text) {
        update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    } elseif ($document) {
        if (isset($fileid))
            update("help", "Media_os", $fileid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "document", "name_os", $user['Processing_value']);
    }
    $skipAppLinkKb = json_encode([
        'inline_keyboard' => [
            [['text' => '⏭ رد شدن (بدون لینک برنامه)', 'callback_data' => 'help_skip_app_link']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "📌 در صورت تمایل متن دکمه لینک دانلود برنامه را برای این آموزش ارسال کنید، یا رد شدن را انتخاب کنید.", $skipAppLinkKb, 'HTML');
    step('add_app_title_help', $from_id);
} elseif ($user['step'] == "add_app_title_help" && $datain == "help_skip_app_link") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SaveHelp'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "add_app_title_help") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (strlen($text) > 200) {
        nm_adminInstantReply($from_id, "📌 نام باید کمتر از ۲۰۰ کاراکتر باشد.", $backadmin, 'HTML');
        return;
    }
    update("help", "app_title", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, "📌 لینک دانلود اپ را ارسال نمایید", $backadmin, 'HTML');
    step('add_app_link_help', $from_id);
} elseif ($user['step'] == "add_app_link_help") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    update("help", "app_link", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SaveHelp'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ حذف آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
    step('remove_help', $from_id);
} elseif ($user['step'] == "remove_help") {
    $stmt = $pdo->prepare("DELETE FROM help WHERE name_os = :name_os");
    $stmt->bindParam(':name_os', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['RemoveHelp'], $keyboardhelpadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/Response_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "support")) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('getmessageAsAdmin', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backadmin, 'HTML');
} elseif ($user['step'] == "getmessageAsAdmin") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SendMessageuser'], null, 'HTML');
    $Respuseronse = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
            ],
        ]
    ]);
    if ($text) {
        $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$text";
        sendmessage($user['Processing_value'], $textSendAdminToUser, $Respuseronse, 'HTML');
    }
    if ($photo) {
        $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$caption";
        telegram('sendphoto', [
            'chat_id' => $user['Processing_value'],
            'photo' => $photoid,
            'reply_markup' => $Respuseronse,
            'caption' => $textSendAdminToUser,
            'parse_mode' => "HTML",
        ]);
    }
    step('home', $from_id);
} elseif (preg_match('/^ticketadminreply_(\w+)$/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "support")) {
    if ((string)($setting['miniapp_ticket_mode'] ?? '0') === '1') {
        nm_adminInstantReply($from_id, "❌ پاسخ به تیکت‌ها فقط از طریق پنل وب مدیریت امکان‌پذیر است.", $backadmin, 'HTML');
        return;
    }
    $ttk = select("support_message", "*", "Tracking", $dataget[1]);
    if (!is_array($ttk)) {
        nm_adminInstantReply($from_id, "❌ این تیکت یافت نشد.", $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $dataget[1], "id", $from_id);
    step('ticketadminreplyWait', $from_id);
    nm_adminInstantReply($from_id, "📝 پاسخ خود را برای این تیکت بفرستید (متن، عکس یا ویدیو):", $backadmin, 'HTML');
} elseif ($user['step'] == "ticketadminreplyWait" && ($text !== '' || $photo || $video)) {
    if ((string)($setting['miniapp_ticket_mode'] ?? '0') === '1') {
        nm_adminInstantReply($from_id, "❌ پاسخ به تیکت‌ها فقط از طریق پنل وب مدیریت امکان‌پذیر است.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    $T = (string)$user['Processing_value'];
    $ttk = select("support_message", "*", "Tracking", $T);
    if (!is_array($ttk)) {
        nm_adminInstantReply($from_id, "❌ این تیکت یافت نشد.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    $time = date('Y/m/d H:i:s');
    $mediaToken = $photo ? "\n[[photo:$photoid]]" : ($video ? "\n[[video:$videoid]]" : "");
    $bodytext = trim(((string)$text !== '' ? (string)$text : (string)$caption) . $mediaToken);
    $userKb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ', 'callback_data' => 'ticketreply_' . $T]]]], JSON_UNESCAPED_UNICODE);
    $userMsgText = (string)$text !== '' ? (string)$text : (string)$caption;
    $toUser = "📩 پاسخ پشتیبانی به تیکت <code>$T</code> :" . ($userMsgText !== '' ? "\n\n" . $userMsgText : ($photo ? "\n\n(تصویر پیوست ارسال شد)" : ($video ? "\n\n(ویدیو پیوست ارسال شد)" : "")));
    $sendRes = sendmessage($ttk['iduser'], $toUser, $userKb, 'HTML');
    if (empty($sendRes['ok'])) {
        nm_adminInstantReply($from_id, "❌ ارسال پاسخ به کاربر ناموفق بود (ممکن است کاربر ربات را بلاک کرده باشد یا خطای تلگرام رخ داده باشد).\nتیکت همچنان در صف پاسخگویی باقی ماند.", null, 'HTML');
        return;
    }
    if ($photo) { sendphoto($ttk['iduser'], $photoid, null); }
    if ($video) { sendvideo($ttk['iduser'], $videoid, null); }
    $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$T, $ttk['idsupport'], $ttk['iduser'], $ttk['name_departman'], $bodytext, 'admin', $time, 'Answered']);
    update("support_message", "status", "Answered", "Tracking", $T);
    nm_adminInstantReply($from_id, "✅ پاسخ شما ثبت و برای کاربر ارسال شد.", null, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "ticketadminreplyWait") {
    nm_adminInstantReply($from_id, "❌ فقط متن، عکس یا ویدیو پشتیبانی می‌شود.", null, 'HTML');
    return;
} elseif (
    ($text == "⚙️ وضعیت قابلیت ها" && $adminrulecheck['rule'] == "administrator")
    || (in_array((string)($datain ?? ''), ['featcat_main','featcat_bot','featcat_users','featcat_shop','featcat_lottery','featcat_crons','featcat_antispam','feat_info','feat_test','feat_help'], true) && $adminrulecheck['rule'] == "administrator")
) {
    $rxFeatView = 'main';
    $rxDatainStr = (string)($datain ?? '');
    if ($rxDatainStr === 'featcat_bot')       $rxFeatView = 'bot';
    elseif ($rxDatainStr === 'featcat_users') $rxFeatView = 'users';
    elseif ($rxDatainStr === 'featcat_shop')  $rxFeatView = 'shop';
    elseif ($rxDatainStr === 'featcat_lottery') $rxFeatView = 'lottery';
    elseif ($rxDatainStr === 'featcat_crons') $rxFeatView = 'crons';
    elseif ($rxDatainStr === 'featcat_antispam') $rxFeatView = 'antispam';
    rxRenderFeatureStatus($rxFeatView, $from_id);
    return;
} elseif ($datain === 'run_host_optimizer' && $adminrulecheck['rule'] === 'administrator') {

    $rxOptRoot           = defined('APP_ROOT_PATH') ? APP_ROOT_PATH : dirname(__DIR__, 3);
    $rxOptCfgFile        = $rxOptRoot . '/optimization_config.php';
    $rxOptCooldownSecs   = 60;
    $rxOptLastRun        = 0;
    if (@is_file($rxOptCfgFile)) {
        $rxOptExisting = @include $rxOptCfgFile;
        if (is_array($rxOptExisting) && isset($rxOptExisting['generated_at'])) {
            $rxOptLastRun = (int) $rxOptExisting['generated_at'];
        }
    }
    $rxOptSecondsAgo = time() - $rxOptLastRun;

    if ($rxOptLastRun > 0 && $rxOptSecondsAgo < $rxOptCooldownSecs) {
        $rxOptWait = $rxOptCooldownSecs - $rxOptSecondsAgo;
        if (!empty($callback_query_id)) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text'              => "⏳ لطفاً {$rxOptWait} ثانیه دیگر مجدداً امتحان کنید.",
                    'show_alert'        => true,
                    'cache_time'        => 0,
                ]);
            } catch (\Throwable $rxOptCbErr) {}
        }
        return;
    }

    if (!empty($callback_query_id)) {
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => 'در حال آنالیز هاست...',
                'show_alert'        => false,
                'cache_time'        => 2,
            ]);
        } catch (\Throwable $rxOptCbErr) {}
    }
    $rxOptAnalyzerFile   = $rxOptRoot . '/re/rx/function/EnvAnalyzer.php';
    $rxOptControllerFile = $rxOptRoot . '/re/rx/admin/OptimizerController.php';

    if (!class_exists('RxEnvAnalyzer') && @is_file($rxOptAnalyzerFile)) {
        require_once $rxOptAnalyzerFile;
    }
    if (!class_exists('RxOptimizerController') && @is_file($rxOptControllerFile)) {
        require_once $rxOptControllerFile;
    }

    $rxOptResultText = '❌ فایل‌های بهینه‌ساز یافت نشد.';
    if (class_exists('RxOptimizerController') && isset($pdo) && $pdo instanceof PDO) {
        try {
            $rxOptCtrl       = new RxOptimizerController($pdo, $rxOptRoot);
            $rxOptResultText = $rxOptCtrl->run();
        } catch (\Throwable $rxOptErr) {
            $rxOptResultText = '❌ خطا در اجرای بهینه‌ساز: ' . htmlspecialchars($rxOptErr->getMessage());
        }
    }

    $rxOptKb = json_encode(['inline_keyboard' => [
        [['text' => "🔙 بازگشت به وضعیت قابلیت‌ها", 'callback_data' => 'featcat_main']],
    ]]);
    nm_adminInstantReply($from_id, $rxOptResultText, $rxOptKb, 'HTML');
    return;
} elseif ($datain === 'glass_mode_settings' && $adminrulecheck['rule'] === 'administrator') {

    rxRenderFeatureStatus('glass_mode', $from_id);
    return;
} elseif ($datain === 'run_redis_status' && $adminrulecheck['rule'] === 'administrator') {

    rxRenderFeatureStatus('redis', $from_id);
    return;
} elseif ($datain === 'run_redis_test' && $adminrulecheck['rule'] === 'administrator') {

    if (!empty($callback_query_id)) {
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => 'در حال تست Redis...',
                'show_alert'        => false,
                'cache_time'        => 0,
            ]);
        } catch (\Throwable $rxRedisCbErr) {}
    }

    if (!function_exists('rx_redis_extension_available') || !rx_redis_extension_available()) {
        $rxRedisTestText = function_exists('rx_redis_extension_guidance')
            ? rx_redis_extension_guidance()
            : '⚠️ افزونه Redis در PHP فعال نیست.';
    } else {
        $rxRedisTestKey = 'faoxima:admintest:' . $from_id;
        $rxRedisTestValue = (string) time();
        $rxRedisWriteOk = function_exists('rx_redis_set') && rx_redis_set($rxRedisTestKey, $rxRedisTestValue, 10);
        $rxRedisReadBack = $rxRedisWriteOk && function_exists('rx_redis_get') ? rx_redis_get($rxRedisTestKey) : null;
        if ($rxRedisWriteOk && $rxRedisReadBack === $rxRedisTestValue) {
            $rxRedisTestText = "✅ تست خواندن/نوشتن Redis موفق بود.";
        } else {
            $rxRedisTestText = "❌ تست Redis ناموفق بود — اتصال برقرار نیست یا Redis غیرفعال است.";
        }
    }

    $rxRedisTestKb = json_encode(['inline_keyboard' => [
        [['text' => "🔙 بازگشت", 'callback_data' => 'run_redis_status']],
    ]]);
    nm_adminInstantReply($from_id, $rxRedisTestText, $rxRedisTestKb, 'HTML');
    return;
} elseif ($datain == "antispam_noop" && $adminrulecheck['rule'] == "administrator") {

    if (!empty($callback_query_id)) {
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'cache_time' => 1,
            ]);
        } catch (\Throwable $rxAsNoopErr) {  }
    }
} elseif ($datain == "antispam_toggle" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCurrent = (string)($setting['antispam_status'] ?? '0');
    $rxAsNew = ($rxAsCurrent === '1') ? '0' : '1';
    update("setting", "antispam_status", $rxAsNew);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    if (!empty($callback_query_id)) {
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => ($rxAsNew === '1') ? '✅ آنتی اسپم فعال شد' : '⛔️ آنتی اسپم غیرفعال شد',
                'show_alert' => false,
                'cache_time' => 0,
            ]);
        } catch (\Throwable $rxAsTogErr) {  }
    }

    $setting = select("setting", "*", null, null, "select", ['cache' => false]);
    $rxAsRefreshedStatusValue = (string)($setting['antispam_status'] ?? '0');
    $rxAsRefreshedStatusText  = ($rxAsRefreshedStatusValue === '1')
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $rxAsRefreshedMsgCount = (int)($setting['antispam_msg_count'] ?? 5);
    $rxAsRefreshedSeconds  = (int)($setting['antispam_seconds'] ?? 3);
    $rxAsRefreshedMute     = (int)($setting['antispam_mute_seconds'] ?? 5);
    $rxAsRefreshedKb = [
        [['text' => $rxAsRefreshedStatusText, 'callback_data' => "antispam_toggle"],
         ['text' => "🛡 وضعیت آنتی اسپم", 'callback_data' => "antispam_noop"]],
        [['text' => (string)$rxAsRefreshedMsgCount, 'callback_data' => "antispam_set_count"],
         ['text' => "✉️ تعداد پیام مجاز", 'callback_data' => "antispam_noop"]],
        [['text' => (string)$rxAsRefreshedSeconds, 'callback_data' => "antispam_set_seconds"],
         ['text' => "⏱ بازه (ثانیه)", 'callback_data' => "antispam_noop"]],
        [['text' => (string)$rxAsRefreshedMute, 'callback_data' => "antispam_set_mute"],
         ['text' => "🔇 آف بودن (ثانیه)", 'callback_data' => "antispam_noop"]],
        [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main']],
    ];
    $rxAsRefreshedTitle = "🛡 <b>آنتی اسپم</b>\n\n"
        . "📌 <b>تنظیمات فعلی:</b>\n"
        . "• وضعیت: {$rxAsRefreshedStatusText}\n"
        . "• تعداد پیام مجاز: <b>{$rxAsRefreshedMsgCount}</b> پیام\n"
        . "• بازه زمانی: هر <b>{$rxAsRefreshedSeconds}</b> ثانیه\n"
        . "• مدت آف بودن: <b>{$rxAsRefreshedMute}</b> ثانیه";
    if (isset($message_id)) {
        Editmessagetext($from_id, $message_id, $rxAsRefreshedTitle, json_encode(['inline_keyboard' => $rxAsRefreshedKb]), 'HTML');
    } else {
        nm_adminInstantReply($from_id, $rxAsRefreshedTitle, json_encode(['inline_keyboard' => $rxAsRefreshedKb]), 'HTML');
    }
} elseif ($datain == "antispam_set_count" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCur = (int)($setting['antispam_msg_count'] ?? 5);
    nm_adminInstantReply(
        $from_id,
        "📌 <b>تنظیم تعداد پیام مجاز آنتی اسپم</b>\n\n"
        . "مقدار فعلی: <b>{$rxAsCur}</b>\n\n"
        . "لطفاً یک عدد بین <b>1</b> تا <b>1000</b> ارسال کنید.\n"
        . "این عدد بیشترین تعداد پیامی است که کاربر می‌تواند در بازه زمانی تعیین‌شده ارسال کند.",
        $backadmin,
        'HTML'
    );
    step('antispam_get_count', $from_id);
} elseif ($datain == "antispam_set_seconds" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCur = (int)($setting['antispam_seconds'] ?? 3);
    nm_adminInstantReply(
        $from_id,
        "📌 <b>تنظیم بازه زمانی آنتی اسپم</b>\n\n"
        . "مقدار فعلی: <b>{$rxAsCur}</b> ثانیه\n\n"
        . "لطفاً یک عدد بین <b>1</b> تا <b>3600</b> (یک ساعت) ارسال کنید.\n"
        . "این عدد طول بازه زمانی برای شمارش پیام‌ها است.",
        $backadmin,
        'HTML'
    );
    step('antispam_get_seconds', $from_id);
} elseif ($datain == "antispam_set_mute" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCur = (int)($setting['antispam_mute_seconds'] ?? 5);
    nm_adminInstantReply(
        $from_id,
        "📌 <b>تنظیم مدت آف بودن ربات</b>\n\n"
        . "مقدار فعلی: <b>{$rxAsCur}</b> ثانیه\n\n"
        . "لطفاً یک عدد بین <b>1</b> تا <b>86400</b> (یک روز) ارسال کنید.\n"
        . "این عدد مدت زمانی است که ربات پس از تخلف کاربر، به او پاسخ نمی‌دهد.\n\n"
        . "ℹ️ این محدودیت فقط برای کاربران اعمال می‌شود؛ ادمین‌ها هرگز محدود نمی‌شوند.",
        $backadmin,
        'HTML'
    );
    step('antispam_get_mute', $from_id);
} elseif ($user['step'] == "antispam_get_count" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    $rxAsTrim = is_string($text) ? trim($text) : '';
    if (!ctype_digit($rxAsTrim)) {
        nm_adminInstantReply(
            $from_id,
            "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    $rxAsNum = (int)$rxAsTrim;
    if ($rxAsNum < 1 || $rxAsNum > 1000) {
        nm_adminInstantReply(
            $from_id,
            "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>1000</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    update("setting", "antispam_msg_count", (string)$rxAsNum);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    nm_adminInstantReply(
        $from_id,
        "✅ تعداد پیام مجاز آنتی اسپم روی <b>{$rxAsNum}</b> تنظیم شد.",
        $keyboardadmin,
        'HTML'
    );
    step('home', $from_id);
} elseif ($user['step'] == "antispam_get_seconds" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    $rxAsTrim = is_string($text) ? trim($text) : '';
    if (!ctype_digit($rxAsTrim)) {
        nm_adminInstantReply(
            $from_id,
            "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    $rxAsNum = (int)$rxAsTrim;
    if ($rxAsNum < 1 || $rxAsNum > 3600) {
        nm_adminInstantReply(
            $from_id,
            "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>3600</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    update("setting", "antispam_seconds", (string)$rxAsNum);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    nm_adminInstantReply(
        $from_id,
        "✅ بازه زمانی آنتی اسپم روی <b>{$rxAsNum}</b> ثانیه تنظیم شد.",
        $keyboardadmin,
        'HTML'
    );
    step('home', $from_id);
} elseif ($user['step'] == "antispam_get_mute" && $adminrulecheck['rule'] == "administrator") {
    if (!isset($update['message']) && empty($text)) { return; }
    $rxAsTrim = is_string($text) ? trim($text) : '';
    if (!ctype_digit($rxAsTrim)) {
        nm_adminInstantReply(
            $from_id,
            "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    $rxAsNum = (int)$rxAsTrim;
    if ($rxAsNum < 1 || $rxAsNum > 86400) {
        nm_adminInstantReply(
            $from_id,
            "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>86400</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    update("setting", "antispam_mute_seconds", (string)$rxAsNum);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    nm_adminInstantReply(
        $from_id,
        "✅ مدت آف بودن ربات روی <b>{$rxAsNum}</b> ثانیه تنظیم شد.",
        $keyboardadmin,
        'HTML'
    );
    step('home', $from_id);
} elseif (preg_match('/^editstsuts-(.*)-(.*)/', $datain, $dataget)) {
    $status_cron = normalizeCronStatus($setting['cron_status'] ?? null, true);
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "statusbot") {
        if ($value == "botstatuson") {
            $valuenew = "botstatusoff";
        } else {
            $valuenew = "botstatuson";
        }
        update("setting", "Bot_Status", $valuenew);
    } elseif ($type == "usernamebtn") {
        if ($value == "onnotuser") {
            $valuenew = "offnotuser";
        } else {
            $valuenew = "onnotuser";
        }
        update("setting", "NotUser", $valuenew);
    } elseif ($type == "notifnew") {
        if ($value == "onnewuser") {
            $valuenew = "offnewuser";
        } else {
            $valuenew = "onnewuser";
        }
        update("setting", "statusnewuser", $valuenew);
    } elseif ($type == "showagent") {
        if ($value == "onrequestagent") {
            $valuenew = "offrequestagent";
        } else {
            $valuenew = "onrequestagent";
        }
        update("setting", "statusagentrequest", $valuenew);
    } elseif ($type == "role") {
        if ($value == "rolleon") {
            $valuenew = "rolleoff";
        } else {
            $valuenew = "rolleon";
        }
        update("setting", "roll_Status", $valuenew);
    } elseif ($type == "get_number") {
        $current = $setting['get_number'] ?? 'offAuthenticationphone';
        $valuenew = ($current === "onAuthenticationphone") ? "offAuthenticationphone" : "onAuthenticationphone";
        update("setting", "get_number", $valuenew);
        $setting['get_number'] = $valuenew;
    } elseif ($type == "Authenticationiran") {
        $current = $setting['iran_number'] ?? 'offAuthenticationiran';
        $valuenew = ($current === "onAuthenticationiran") ? "offAuthenticationiran" : "onAuthenticationiran";
        update("setting", "iran_number", $valuenew);
        $setting['iran_number'] = $valuenew;
    } elseif ($type == "inlinebtnmain") {
        if ($value == "oninline") {
            $valuenew = "offinline";
        } else {
            $valuenew = "oninline";
        }
        update("setting", "inlinebtnmain", $valuenew);
        $setting['inlinebtnmain'] = $valuenew;
        rxRenderFeatureStatus('glass_mode', $from_id);
        return;
    } elseif ($type == "auto_remove_kb") {
        $valuenew = ($value === "on") ? "off" : "on";
        update("setting", "auto_remove_reply_keyboard", $valuenew);
        $setting['auto_remove_reply_keyboard'] = $valuenew;
        rxRenderFeatureStatus('glass_mode', $from_id);
        return;
    } elseif ($type == "verifystart") {
        $current = $setting['verifystart'] ?? 'offverify';
        $valuenew = ($current === "onverify") ? "offverify" : "onverify";
        update("setting", "verifystart", $valuenew);
        $setting['verifystart'] = $valuenew;
    } elseif ($type == "statussupportpv") {
        if ($value == "onpvsupport") {
            $valuenew = "offpvsupport";
        } else {
            $valuenew = "onpvsupport";
        }
        update("setting", "statussupportpv", $valuenew);
    } elseif ($type == "statusnamecustom") {
        if ($value == "onnamecustom") {
            $valuenew = "offnamecustom";
        } else {
            $valuenew = "onnamecustom";
        }
        update("setting", "statusnamecustom", $valuenew);
    } elseif ($type == "bulkbuy") {
        if ($value == "onbulk") {
            $valuenew = "offbulk";
        } else {
            $valuenew = "onbulk";
        }
        update("setting", "bulkbuy", $valuenew);
    } elseif ($type == "verifybyuser") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifybucodeuser", $valuenew);
    } elseif ($type == "authscope") {
        if ($value == "newonly") {
            $valuenew = "all";
        } else {
            $valuenew = "newonly";
        }
        update("setting", "auth_scope", $valuenew);
    } elseif ($type == "cardverify") {
        $current = $setting['card_verify_status'] ?? 'offcardverify';
        $valuenew = ($current === 'oncardverify') ? 'offcardverify' : 'oncardverify';
        update("setting", "card_verify_status", $valuenew);
        $setting['card_verify_status'] = $valuenew;
    } elseif ($type == "forced_miniapp") {
        $valuenew = ($value === '1') ? '0' : '1';
        update("setting", "forced_miniapp_mode", $valuenew);
        $setting['forced_miniapp_mode'] = $valuenew;
    } elseif ($type == "miniappticket") {
        $valuenew = ($value === '1') ? '0' : '1';
        update("setting", "miniapp_ticket_mode", $valuenew);
        $setting['miniapp_ticket_mode'] = $valuenew;
    } elseif ($type == "wheelagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelagent", $valuenew);
    } elseif ($type == "keyconfig") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "status_keyboard_config", $valuenew);
    } elseif ($type == "Lotteryagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Lotteryagent", $valuenew);
    } elseif ($type == "compycart") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuscopycart", $valuenew);
    } elseif ($type == "premiumemoji") {

        $rxPemCurrent = (string)($setting['premium_emoji_status'] ?? '0');
        $valuenew = ($rxPemCurrent === '1') ? '0' : '1';
        update("setting", "premium_emoji_status", $valuenew);

        $setting['premium_emoji_status'] = $valuenew;
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }

        if (function_exists('rxRenderPremiumEmojiPanel')) {
            rxRenderPremiumEmojiPanel($from_id, 1);
        }
        return;
    } elseif ($type == "score") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "scorestatus", $valuenew);
    } elseif ($type == "redis_enabled") {
        if ($value === "1") {
            $valuenew = "0";
        } else {
            if (!function_exists('rx_redis_extension_available') || !rx_redis_extension_available()) {
                if (!empty($callback_query_id)) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'text'              => function_exists('rx_redis_extension_guidance') ? rx_redis_extension_guidance() : '⚠️ افزونه Redis در PHP فعال نیست.',
                            'show_alert'        => true,
                        ]);
                    } catch (\Throwable $rxRedisToggleCbErr) {}
                }
                rxRenderFeatureStatus('redis', $from_id);
                return;
            }
            $valuenew = "1";
        }
        update("setting", "redis_enabled", $valuenew);
        if (function_exists('rx_redis_reset_resolution')) {
            rx_redis_reset_resolution();
        }
        if (function_exists('clearSelectCache')) {
            clearSelectCache('setting');
        }
        $setting['redis_enabled'] = $valuenew;
        rxRenderFeatureStatus('redis', $from_id);
        return;
    } elseif ($type == "wheel_luck") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelـluck", $valuenew);
    } elseif ($type == "affiliatesstatus") {
        if ($value == "onaffiliates") {
            $valuenew = "offaffiliates";
        } else {
            $valuenew = "onaffiliates";
        }
        update("setting", "affiliatesstatus", $valuenew);
    } elseif ($type == "btn_status_category") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "categoryhelp", $valuenew);
    } elseif ($type == "randomwallet") {
        $valuenew = ($value === '1') ? '0' : '1';
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "randomwallet_status");
    } elseif ($type == "btnstautslanguage") {
        if ($setting['languageru'] == "1") {
            nm_adminInstantReply($from_id, "زبان روسیه ای روشن است و نمی توانید زبان انگلیسی را تغییر وضعیت دهید", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageen", $valuenew);
    } elseif ($type == "btnstautslanguageru") {
        if ($setting['languageen'] == "1") {
            nm_adminInstantReply($from_id, "زبان انگلیسی روشن است و نمی توانید زبان روسیه ای را تغییر وضعیت دهید", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageru", $valuenew);
    } elseif ($type == "wheelagentfirst") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusfirstwheel", $valuenew);
    } elseif ($type == "changeloc") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuslimitchangeloc", $valuenew);
    } elseif ($type == "Debtsettlement") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Debtsettlement", $valuenew);
    } elseif ($type == "Dice") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Dice", $valuenew);
    } elseif ($type == "statusnamecustomf") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusnoteforf", $valuenew);
    } elseif ($type == "crontest") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['test'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronday") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['day'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronvolume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['remove'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove_volume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['remove_volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_node") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['uptime_node'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_panel") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['uptime_panel'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "on_hold") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['on_hold'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "infocard") {

        $valuenew = ($value === '1') ? '0' : '1';
        $existing = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
        if (is_array($existing) && isset($existing['Namevalue'])) {
            update("shopSetting", "value", $valuenew, "Namevalue", "infocard_status");
        } else {

            try {
                $stmt = $pdo->prepare("INSERT INTO shopSetting (Namevalue, value) VALUES (:n, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)");
                $stmt->execute([':n' => 'infocard_status', ':v' => $valuenew]);
                if (function_exists('clearSelectCache')) clearSelectCache('shopSetting');
            } catch (\Throwable $e) {
                error_log('infocard_status insert failed: ' . $e->getMessage());
            }
        }
    } elseif ($type == "premiumemoji") {

        $valuenew = ($value === '1') ? '0' : '1';
        update("setting", "premium_emoji_status", $valuenew);
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
    }
    $setting = select("setting", "*");
    $status_cron = normalizeCronStatus($setting['cron_status'] ?? null, true);
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

    $infocardStatusRow = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
    $infocardStatusValue = (is_array($infocardStatusRow) && isset($infocardStatusRow['value']))
        ? (string)$infocardStatusRow['value'] : '0';
    $infocardColorRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $infocardColorValue = (is_array($infocardColorRow) && isset($infocardColorRow['value']))
        ? (string)$infocardColorRow['value'] : 'yellow';
    $infocardStatusText = ($infocardStatusValue === '1') ? $_rxOn : $_rxOff;
    $infocardColorEmojiMap = [
        'yellow' => '🟡', 'green' => '🟢', 'red' => '🔴',
        'blue'   => '🔵', 'purple' => '🟣', 'orange' => '🟠'
    ];
    $infocardColorEmoji = $infocardColorEmojiMap[$infocardColorValue] ?? '🟡';

    $randomWalletStatusRow = select("PaySetting", "ValuePay", "NamePay", "randomwallet_status", "select");
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

    $rxFeatTitle = "📋 <b>وضعیت قابلیت‌ها</b>";
    $rxFeatBackRow = [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main']];

    if ($rxFeatTargetCat === 'bot') {
        $rxFeatTitle = "🤖 <b>آپشن‌های اصلی ربات</b>";
        $rxFeatRows = rx_featCategoryRows('bot');
        $rxFeatRows[] = $rxFeatBackRow;
    } elseif ($rxFeatTargetCat === 'users') {
        $rxFeatTitle = "👥 <b>کاربران و پشتیبانی</b>";
        $rxFeatRows = rx_featCategoryRows('users');
        $rxFeatRows[] = $rxFeatBackRow;
    } elseif ($rxFeatTargetCat === 'shop') {
        $rxFeatTitle = "🛍 <b>فروش و خدمات</b>";
        $rxFeatRows = rx_featCategoryRows('shop');
        $rxFeatRows[] = $rxFeatBackRow;
    } elseif ($rxFeatTargetCat === 'lottery') {
        $rxFeatTitle = "🎁 <b>گردونه و قرعه‌کشی</b>";
        $rxFeatRows = rx_featCategoryRows('lottery');
        $rxFeatRows[] = $rxFeatBackRow;
    } elseif ($rxFeatTargetCat === 'crons') {
        $rxFeatTitle = "⏱ <b>کرون‌ها و زمان‌بندی</b>";
        $rxFeatRows = rx_featCategoryRows('crons');
        $rxFeatRows[] = $rxFeatBackRow;
    } else {

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
    }
    $Bot_Status = json_encode(['inline_keyboard' => $rxFeatRows]);
    Editmessagetext($from_id, $message_id, $rxFeatTitle, $Bot_Status);
} elseif (($text == "📣 گزارشات ربات" || (isset($datain) && $datain === 'set_reports')) && $adminrulecheck['rule'] == "administrator") {
    $textreports = "📣 تنظیم گروه گزارشات ربات

در این بخش می‌توانید آیدی عددی گروه موردنظر را برای ارسال اعلان‌ها و گزارشات ربات ثبت نمایید.

آموزش تنظیم گروه:
1 ـ ابتدا یک گروه جدید ایجاد کنید یا گروه موردنظر خود را انتخاب نمایید.
2 ـ از تنظیمات گروه، قابلیت تاپیک را فعال کنید.
3 ـ ربات خود را به گروه اضافه کرده و دسترسی مدیریت موردنیاز را به آن بدهید.
4 ـ سپس داخل همان گروه، یکی از عبارت‌های «آیدی» یا «ایدی» را ارسال کنید.
5 ـ ربات، آیدی عددی گروه را برای شما نمایش می‌دهد. آیدی نمایش‌داده‌شده را کپی کرده و در این بخش برای ربات ارسال نمایید.

⚠️ توجه داشته باشید که قبل از درخواست آیدی، تاپیک گروه باید فعال شده باشد.

آیدی عددی فعلی شما: {$setting['Channel_Report']}";
    step('addchannelid', $from_id);
    if (isset($user) && is_array($user)) {
        $user['step'] = 'addchannelid';
    }
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'addchannelid');
    }
    nm_adminInstantReply($from_id, $textreports, $backadmin, 'HTML');
} elseif ($user['step'] == "addchannelid") {
    $text = trim((string) $text);
    if (!preg_match('/^-\d+$/', $text)) {
        nm_adminInstantReply($from_id, "❌ آیدی گروه معتبر نیست\n\nلطفاً آیدی عددی نمایش‌داده‌شده توسط ربات در گروه را بدون تغییر ارسال نمایید.", null, 'HTML');
        return;
    }
    $outputcheck = sendmessage($text, $textbotlang['Admin']['Channel']['TestChannel'], null, 'HTML');
    if (empty($outputcheck['ok'])) {
        $errorDescription = 'نامشخص';
        if (is_array($outputcheck) && isset($outputcheck['description'])) {
            $errorDescription = $outputcheck['description'];
        } elseif (is_string($outputcheck) && $outputcheck !== '') {
            $errorDescription = $outputcheck;
        }
        $texterror = "❌ اتصال به گروه با موفقیت انجام نشد

خطای دریافتی :  {$errorDescription}";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($outputcheck['result']['chat']['is_forum'] == false) {
        $texterror = "❌ گروه انتخاب شده درحالت انجمن نیست ابتدا قابلیت تاپیک گروه را روشن کرده سپس آیدی عددی گروه را مجددا تنظیم نمایید";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    @ignore_user_abort(true);
    @set_time_limit(120);
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    nm_adminInstantReply($from_id, "⏳ در حال ساخت تاپیک‌های گزارش... این کار تا حدود یک دقیقه ممکن است طول بکشد، لطفاً صبر کنید.", null, 'HTML');
    $reportTopics = [
        'buyreport'     => "🛍 گزارش های خرید",
        'otherservice'  => "📌 گزارش خرید خدمات",
        'reporttest'    => "🔑 گزارش اکانت تست",
        'otherreport'   => "⚙️ سایر گزارشات",
        'errorreport'   => "❌ گزارش خطا ها",
        'paymentreport' => "💰 گزارش مالی",
        'receiptreport' => "🧾 گزارش رسید",
    ];

    $extraReportKeys = ['porsantreport', 'reportnight', 'reportcron', 'backupfile'];

    foreach ($reportTopics as $reportKey => $reportName) {
        update("topicid", "idreport", "0", "report", $reportKey);
    }
    foreach ($extraReportKeys as $extraKey) {
        update("topicid", "idreport", "0", "report", $extraKey);
    }
    update("setting", "Channel_Report", $text);

    $createdCount = 0;
    foreach ($reportTopics as $reportKey => $reportName) {
        $topicResult = null;
        for ($try = 1; $try <= 3; $try++) {
            $topicResult = telegram('createForumTopic', [
                'chat_id' => $text,
                'name' => $reportName
            ]);
            if (!empty($topicResult['ok'])) {
                break;
            }
            $retryAfter = isset($topicResult['parameters']['retry_after']) ? (int)$topicResult['parameters']['retry_after'] : 2;
            if ($retryAfter < 1) $retryAfter = 1;
            if ($retryAfter > 10) $retryAfter = 10;
            sleep($retryAfter);
        }
        if (!empty($topicResult['ok']) && isset($topicResult['result']['message_thread_id'])) {
            update("topicid", "idreport", $topicResult['result']['message_thread_id'], "report", $reportKey);
            $createdCount++;
            usleep(400000);
        }
    }

    $totalTopics = count($reportTopics);
    if ($createdCount >= $totalTopics) {
        if (function_exists('rxEnsureCronRegistered')) {
            rxEnsureCronRegistered('report_group_success');
        }
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Channel']['SetChannelReport'], $adminChannelMenu ?? $keyboardadmin, 'HTML');
        step('channelhub', $from_id);
        if (isset($user) && is_array($user)) {
            $user['step'] = 'channelhub';
        }
        if (function_exists('rxNavSetState')) {
            rxNavSetState($from_id, 'channelhub');
        }
    } elseif ($createdCount == 0) {
        nm_adminInstantReply($from_id, "❌ هیچ تاپیکی ساخته نشد. مطمئن شوید ربات در گروه ادمین است و دسترسی «مدیریت تاپیک‌ها» (Manage Topics) دارد، سپس دوباره تلاش کنید.", null, 'HTML');
    } else {
        nm_adminInstantReply($from_id, "⚠️ ساخت تاپیک‌ها کامل نشد ({$createdCount} از {$totalTopics} ساخته شد). علت معمولاً کندی یا محدودیت موقت تلگرام است. چند لحظه صبر کنید و دوباره همین گزینه («📣 گزارشات ربات») را بزنید.", null, 'HTML');
    }
} elseif ($text == "🏬 تنظیمات فروشگاه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
} elseif ($text == "🛍 افزودن محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "⚠️ افزودن محصول از طریق ربات غیرفعال است.\n\nبرای ساخت محصول جدید و دسترسی به تمام تنظیمات محصول، لطفاً از پنل مدیریت وب استفاده کنید.", $keyboard_shop_manage, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "get_limit") {
    if (strlen($text) > 150) {
        nm_adminInstantReply($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $name_product)) {
        nm_adminInstantReply($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "name_product", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['setagentproduct'], rx_agentGroupKeyboard(false), 'HTML');
    step('get_agent', $from_id);
} elseif ($user['step'] == "get_agent") {
    $agent = ["n", "f", "n2"];
    $text = rx_resolveAgentGroupFromReplyButton($text, $agent);
    if ($text === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], rx_agentGroupKeyboard(false), 'HTML');
        return;
    }
    savedata("save", "agent", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Service_location'], $json_list_marzban_panel, 'HTML');
    step('get_location', $from_id);
} elseif ($user['step'] == "get_location") {
    $panel = ($text === '/all') ? null : (function_exists('rx_resolvePanelFromInput') ? rx_resolvePanelFromInput($text, $pdo) : select("marzban_panel", "*", "name_panel", $text, "select"));
    if ($text !== '/all' && (!is_array($panel) || empty($panel))) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی اشتباه است", null, 'HTML');
        return;
    }
    $locCanonical = ($text === '/all') ? '/all' : $panel['name_panel'];
    savedata("save", "Location", $locCanonical);
    if ($text !== '/all' && panel_feature_enabled($locCanonical, 'categorygeneral')) {
        nm_adminInstantReply($from_id, "📌 نام دسته بندی خود را ارسال نمایید.", KeyboardCategoryadmin(), 'HTML');
        step("getcategory", $from_id);
        return;
    }
    if ($text !== '/all' && !is_array($panel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
        nm_adminInstantReply($from_id, "❌ ساخت محصول فروش دستی از ربات غیرفعال است. لطفاً از بخش مدیریت وب اقدام کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "getcategory") {
    $category = select("category", "*", "remark", $text, "count");
    if ($category == 0) {
        nm_adminInstantReply($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", KeyboardCategoryadmin(), 'HTML');
        return;
    }
    savedata("save", "category", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = $userdata['Location'] === '/all' ? null : select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($userdata['Location'] !== '/all' && !is_array($panel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
        nm_adminInstantReply($from_id, "❌ ساخت محصول فروش دستی از ربات غیرفعال است. لطفاً از بخش مدیریت وب اقدام کنید.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "get_time") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "Volume_constraint", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GettIime'], $backadmin, 'HTML');
    step('get_price', $from_id);
} elseif ($user['step'] == "get_price") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidtime'] ?? '❌ زمان نامعتبر است', $backadmin, 'HTML');
        return;
    }
    savedata("save", "Service_time", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
    step('gettimereset', $from_id);
} elseif ($user['step'] == "gettimereset") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "price_product", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = $userdata['Location'] === '/all' ? null : select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($userdata['Location'] !== '/all' && !is_array($panel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $panelType = is_array($panel) ? ($panel['type'] ?? '') : '';
    if ($panelType == "marzban") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['gettimereset'], $keyboardtimereset, 'HTML');
        step('getnote', $from_id);
        return;
    }
    savedata("save", "data_limit_reset", "no_reset");
    nm_adminInstantReply($from_id, " 🗒 یادداشت را برای محصول ارسال کنید. این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "getnote") {
    savedata("save", "data_limit_reset", $text);
    nm_adminInstantReply($from_id, " 🗒 یادداشت را برای محصول ارسال کنید.این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "endstep") {
    $userdata = json_decode($user['Processing_value'], true);
    $randomString = bin2hex(random_bytes(2));
    $varhide_panel = "{}";
    if (!isset($userdata['category']))
        $userdata['category'] = null;
    $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status) VALUES (:name_product,:code_product,:price_product,:Volume_constraint,:Service_time,:Location,:agent,:data_limit_reset,:note,:category,:hide_panel,'0')");
    $stmt->bindParam(':name_product', $userdata['name_product']);
    $stmt->bindParam(':code_product', $randomString);
    $stmt->bindParam(':price_product', $userdata['price_product']);
    $stmt->bindParam(':Volume_constraint', $userdata['Volume_constraint']);
    $stmt->bindParam(':Service_time', $userdata['Service_time']);
    $stmt->bindParam(':Location', $userdata['Location']);
    $stmt->bindParam(':agent', $userdata['agent']);
    $stmt->bindParam(':data_limit_reset', $userdata['data_limit_reset']);
    $stmt->bindParam(':category', $userdata['category'], PDO::PARAM_STR);
    $stmt->bindParam(':note', $text, PDO::PARAM_STR);
    $stmt->bindParam(':hide_panel', $varhide_panel, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['SaveProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif (($text == "👨‍🔧 بخش ادمین" || $datain == "admin_mgr") && $adminrulecheck['rule'] == "administrator") {
    $list_admin = select("admin", "*", null, null, "fetchAll");
    $keyboardadmin = ['inline_keyboard' => []];
    foreach ($list_admin as $admin) {
        $adminId = isset($admin['id_admin']) ? trim($admin['id_admin']) : '';
        if ($adminId === '') {
            continue;
        }
        $keyboardadmin['inline_keyboard'][] = [
            ['text' => "❌", 'callback_data' => "removeadmin_" . $adminId],
            ['text' => $adminId, 'callback_data' => "adminlist"],
        ];
    }
    $keyboardadmin['inline_keyboard'][] = [
        ['text' => "👨‍💻 اضافه کردن ادمین", 'callback_data' => "addnewadmin"],
    ];
    $keyboardadmin['inline_keyboard'][] = [
        ['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => "admin_usershub"],
        ['text' => "🏠 منوی مدیریت", 'callback_data' => "adm_hub_main"],
    ];
    $keyboardadmin = json_encode($keyboardadmin);
    nm_adminInstantReply($from_id, "📌 در بخش زیر می توانید لیست ادمین ها را مشاهده کنید همچنین با زدن دکمه ضربدر می توانید یک ادمین را حذف کنید", $keyboardadmin, 'HTML');
} elseif ($text == "📁 مدیریت پنل‌ها و سرورها" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
} elseif (($text == "📢 کانال و اطلاع‌رسانی" || (isset($datain) && $datain === 'admin_channelhub')) && $adminrulecheck['rule'] == "administrator") {
    step('channelhub', $from_id);
    if (isset($user) && is_array($user)) {
        $user['step'] = 'channelhub';
    }
    if (function_exists('rxNavSetState')) {
        rxNavSetState($from_id, 'channelhub');
    }
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminChannelMenu, 'HTML');
} elseif ($text == "👥 مدیریت کاربران" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminUsersMenu, 'HTML');
} elseif ($text == "⚙️ تنظیمات فنی و ربات" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($text == "🤙 بخش پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $supportcenter, 'HTML');
} elseif (preg_match('/Confirm_pay_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $order_id = $dataget[1];
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $_receipt_chat_id = !empty($Payment_report['report_chat_id']) ? $Payment_report['report_chat_id'] : ($update['callback_query']['message']['chat']['id'] ?? $from_id);
    $_receipt_msg_id = !empty($Payment_report['report_message_id']) ? (int) $Payment_report['report_message_id'] : (int) $message_id;
    $_receipt_thread_id = !empty($Payment_report['report_thread_id']) ? (int) $Payment_report['report_thread_id'] : (int) ($update['callback_query']['message']['message_thread_id'] ?? 0);
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید شده", 'callback_data' => "confirmpaid"],
            ],
            [
                ['text' => "⚙️ مدیریت کاربر", 'callback_data' => "manageuser_" . $Payment_report['id_user']],
            ]
        ]
    ]);
    $sql = "SELECT * FROM Payment_report WHERE id_user = '{$Payment_report['id_user']}' AND payment_Status != 'paid' AND payment_Status != 'Unpaid' AND payment_Status != 'expire' AND payment_Status != 'reject' AND payment_Status != 'cancelled' AND  (id_invoice  LIKE CONCAT('%','getconfigafterpay', '%') OR id_invoice  LIKE CONCAT('%','getextenduser', '%') OR id_invoice  LIKE CONCAT('%','getextravolumeuser', '%') OR id_invoice  LIKE CONCAT('%','getextratimeuser', '%'))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $countpay = $stmt->rowCount();
    $typepay = explode('|', $Payment_report['id_invoice']);
    if ($countpay > 0 and !in_array($typepay[0], ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'])) {
        nm_adminInstantReply($from_id, "⚠️ برای تأیید درخواست‌های کاربر، ابتدا رسیدهای خرید یا تمدید اشتراک را بررسی و تأیید کنید. سپس رسید شارژ کیف پول را تأیید کنید. ", null, 'HTML');
        return;
    }
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 0,
        ));
        $textconfrom = "✅. پرداخت توسط ادمین دیگری تایید شده
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی بعد از تایید : " . rxFormatToman($Balance_id['Balance']) . "
💸 مبلغ پرداختی: $format_price_cart تومان
";
        Editmessagetext($_receipt_chat_id, $_receipt_msg_id, $textconfrom, $Confirm_pay, 'HTML', $_receipt_thread_id > 0 ? $_receipt_thread_id : null);
        return;
    }

    $rxConfirmResult = rxReceiptConfirm($order_id, ['actor_id' => $from_id, 'actor_label' => (string) $from_id]);

    if (!$rxConfirmResult['ok'] && in_array($rxConfirmResult['reason'] ?? '', ['race_lost', 'already_paid', 'already_final', 'not_waiting'], true)) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 0,
        ));
        return;
    }

    if (!$rxConfirmResult['ok']) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "❌ تکمیل عملیات با خطا مواجه شد، دوباره تلاش کنید",
            'show_alert' => true,
            'cache_time' => 0,
        ));
        return;
    }
} elseif (preg_match('/reject_pay_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $_receipt_chat_id = !empty($Payment_report['report_chat_id']) ? $Payment_report['report_chat_id'] : ($update['callback_query']['message']['chat']['id'] ?? $from_id);
    $_receipt_msg_id = !empty($Payment_report['report_message_id']) ? (int) $Payment_report['report_message_id'] : (int) $message_id;
    $_receipt_thread_id = !empty($Payment_report['report_thread_id']) ? (int) $Payment_report['report_thread_id'] : (int) ($update['callback_query']['message']['message_thread_id'] ?? 0);
    if ($Payment_report['payment_Status'] == "reject" || $Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 0,
        ));
        return;
    }
    $rejectStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject' WHERE id_order = :id_order AND payment_Status = 'waiting'");
    $rejectStmt->execute([':id_order' => $id_order]);
    if ($rejectStmt->rowCount() !== 1) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 0,
        ));
        return;
    }
    update("user", "Processing_value", $Payment_report['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $id_order, "id", $from_id);

    $_reject_reason_kb = json_encode([
        'inline_keyboard' => [
            [['text' => "❌ لغو", 'callback_data' => "reject_pay_cancel_" . $id_order]],
        ],
    ], JSON_UNESCAPED_UNICODE);
    $_reject_prompt_result = Editmessagetext($_receipt_chat_id, $_receipt_msg_id, $textbotlang['Admin']['Payment']['Reasonrejecting'], $_reject_reason_kb, 'HTML', $_receipt_thread_id > 0 ? $_receipt_thread_id : null);
    $_reject_prompt_msg_id = is_array($_reject_prompt_result) ? (int) ($_reject_prompt_result['result']['message_id'] ?? 0) : 0;
    if ($_reject_prompt_msg_id > 0) {
        $_receipt_msg_id = $_reject_prompt_msg_id;
    }
    if (!empty($callback_query_id)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
    }
    update("user", "Processing_value_four", $_receipt_chat_id . ':' . $_receipt_msg_id . ':' . $_receipt_thread_id, "id", $from_id);
    step('reject-dec', $from_id);
} elseif (preg_match('/reject_pay_cancel_(\w+)/', $datain, $datagetc) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetc[1];
    $_receipt_chat_id = $update['callback_query']['message']['chat']['id'] ?? $from_id;
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    step('home', $from_id);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "لغو شد",
        'show_alert' => false,
        'cache_time' => 0,
    ));
    if ($Payment_report === false) {
        Editmessagetext($_receipt_chat_id, $message_id, "❌ تراکنش حذف شده است", null);
        return;
    }
    update("Payment_report", "payment_Status", "waiting", "id_order", $id_order);
    $_reject_cancel_kb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$id_order}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$id_order}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$id_order}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$Payment_report['id_user']}"],
            ],
        ],
    ]);
    $_reject_cancel_text = "🔁 رد رسید لغو شد. رسید دوباره در انتظار بررسی است.

🛒 کد پیگیری پرداخت: {$id_order}
💰 مبلغ پرداخت : " . rxFormatToman($Payment_report['price']) . "
👤 ایدی عددی کاربر: {$Payment_report['id_user']}";
    Editmessagetext($_receipt_chat_id, $message_id, $_reject_cancel_text, $_reject_cancel_kb);
} elseif ($user['step'] == "reject-dec") {
    if (!isset($update['message']) && empty($text)) { return; }
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value_one'], "select");
    update("Payment_report", "dec_not_confirmed", $text, "id_order", $user['Processing_value_one']);
    $text_reject = "❌ کاربر گرامی پرداخت شما به دلیل زیر رد گردید.
✍️ $text
🛒 کد پیگیری پرداخت: {$user['Processing_value_one']}
                ";
    $_reject_origin = explode(':', (string) ($user['Processing_value_four'] ?? ''), 3);
    $_reject_chat_id = $_reject_origin[0] ?? '';
    $_reject_message_id = $_reject_origin[1] ?? '';
    $_reject_thread_id = (int) ($_reject_origin[2] ?? 0);
    if ($_reject_chat_id === '' || $_reject_message_id === '') {
        $_reject_chat_id = (string) ($Payment_report['report_chat_id'] ?? '');
        $_reject_message_id = (string) ($Payment_report['report_message_id'] ?? '');
        $_reject_thread_id = (int) ($Payment_report['report_thread_id'] ?? 0);
    }
    $_reject_confirmed_text = "❌ رسید پرداخت رد شد.

اطلاعات :
<blockquote>💸 روش پرداخت : {$Payment_report['Payment_Method']}</blockquote>
<blockquote>👤آیدی عددی  ادمین رد کننده : $from_id</blockquote>
<blockquote>نام کاربری ادمین رد کننده : @$username</blockquote>
<blockquote>💰 مبلغ پرداخت : " . rxFormatToman($Payment_report['price']) . "</blockquote>
<blockquote>دلیل رد کردن : $text</blockquote>
<blockquote>👤 ایدی عددی کاربر: {$Payment_report['id_user']}</blockquote>";
    if ($_reject_chat_id !== '' && $_reject_message_id !== '') {
        Editmessagetext($_reject_chat_id, $_reject_message_id, $_reject_confirmed_text, null, 'HTML', $_reject_thread_id > 0 ? $_reject_thread_id : null);
        update("user", "Processing_value_four", "", "id", $from_id);
    }
    $_reject_admin_msg_chat = $update['message']['chat']['id'] ?? null;
    $_reject_admin_msg_id = $update['message']['message_id'] ?? null;
    if ($_reject_admin_msg_chat && $_reject_admin_msg_id) {
        $_reject_del_result = deletemessage($_reject_admin_msg_chat, $_reject_admin_msg_id);
        if (function_exists('rx_log_event') && (!is_array($_reject_del_result) || empty($_reject_del_result['ok']))) {
            rx_log_event('REJECT_DEC_DELETE_FAILED', 'Failed to delete admin reason message', [
                'chat_id' => $_reject_admin_msg_chat,
                'message_id' => $_reject_admin_msg_id,
                'result' => $_reject_del_result,
            ]);
        }
    } else {
        if (function_exists('rx_log_event')) {
            rx_log_event('REJECT_DEC_DELETE_SKIPPED', 'No message context to delete', [
                'update_message_isset' => isset($update['message']),
            ]);
        }
    }
    $_target_user_id = $Payment_report['id_user'] ?? null;
    if ($_target_user_id) { sendmessage($_target_user_id, $text_reject, null, 'HTML'); }
    step('home', $from_id);
} elseif (preg_match('/^confirmcryptomanual_(\w+)$/', (string) $datain, $cmConf) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmConf[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'تراکنش یافت نشد',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    if ($cmPayment['payment_Status'] == 'paid') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این فاکتور قبلاً تایید شده است.',
            'show_alert' => true,
            'cache_time' => 0,
        ]);
        return;
    }
    $cmAutoIrr = (int) ($cmPayment['price'] ?? 0);
    $cmCoinAmtPick = (string) ($cmPayment['crypto_amount'] ?? '');
    $cmCoinAmtPick = rtrim(rtrim(number_format((float) $cmCoinAmtPick, 9, '.', ''), '0'), '.');
    $cmPickMsg = "🟢 <b>تایید درخواست بررسی دستی</b>\n\n"
               . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
               . "👤 کاربر: <code>{$cmPayment['id_user']}</code>\n"
               . "💎 ارز: " . htmlspecialchars((string) $cmPayment['crypto_currency']) . "\n"
               . "🪙 مقدار: <code>{$cmCoinAmtPick}</code>\n"
               . "💵 معادل خودکار: <b>" . number_format($cmAutoIrr) . " تومان</b>\n\n"
               . "👇 روش تایید را انتخاب کنید:";
    $cmPickKb = json_encode([
        'inline_keyboard' => [
            [['text' => '⚡ تایید با همان مبلغ خودکار', 'callback_data' => 'cmauto_' . $cmOrderId]],
            [['text' => '✏️ ویرایش مبلغ و تایید',       'callback_data' => 'cmmanual_' . $cmOrderId]],
            [['text' => '🔙 بازگشت',                     'callback_data' => 'cmback_' . $cmOrderId]],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $cmPickMsg, $cmPickKb);
    } else {
        sendmessage($from_id, $cmPickMsg, $cmPickKb, 'HTML');
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
} elseif (preg_match('/^cmauto_(\w+)$/', (string) $datain, $cmAuto) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmAuto[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور یافت نشد', 'show_alert' => true]);
        return;
    }
    if ($cmPayment['payment_Status'] === 'paid') {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این فاکتور قبلاً تایید شده', 'show_alert' => true]);
        return;
    }
    $cmFinalIrr = (int) ($cmPayment['price'] ?? 0);
    cm_apply_payment($cmOrderId, $cmFinalIrr, $cmPayment, $from_id, $username, $setting, $paymentreports, $message_id, $callback_query_id, $text_inline ?? '');
} elseif (preg_match('/^cmmanual_(\w+)$/', (string) $datain, $cmMan) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmMan[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'فاکتور یافت نشد', 'show_alert' => true]);
        return;
    }
    if ($cmPayment['payment_Status'] === 'paid') {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این فاکتور قبلاً تایید شده', 'show_alert' => true]);
        return;
    }
    update("user", "Processing_value_one", $cmOrderId, "id", $from_id);
    update("user", "Processing_value_tow", (string) ($message_id ?? 0), "id", $from_id);
    $cmCoinAmt = (string) ($cmPayment['crypto_amount'] ?? '');
    $cmCoinAmt = rtrim(rtrim(number_format((float) $cmCoinAmt, 9, '.', ''), '0'), '.');
    $cmAutoIrrShow = (int) ($cmPayment['price'] ?? 0);
    nm_adminInstantReply(
        $from_id,
        "✏️ <b>وارد کردن مبلغ تومانی دستی</b>\n\n"
        . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
        . "💎 ارز: " . htmlspecialchars((string) $cmPayment['crypto_currency']) . "\n"
        . "🪙 مقدار: <code>{$cmCoinAmt}</code>\n"
        . "💵 مبلغ خودکار: <b>" . number_format($cmAutoIrrShow) . " تومان</b>\n\n"
        . "💰 مبلغ تومانی نهایی را وارد کنید (فقط عدد):",
        $backadmin,
        'HTML'
    );
    step('cm_manual_irr_input', $from_id);
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
} elseif ($user['step'] == "cm_manual_irr_input" && empty($datain)) {
    $cmOrderId = (string) ($user['Processing_value_one'] ?? '');
    if ($cmOrderId === '') {
        nm_adminInstantReply($from_id, "❌ خطای داخلی. دوباره تلاش کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $cmIrrRaw = trim(str_replace([',', '،'], ['', ''], (string) $text));
    if (!ctype_digit($cmIrrRaw) || (int) $cmIrrRaw <= 0) {
        nm_adminInstantReply($from_id, "❌ مبلغ باید عدد صحیح مثبت باشد. مثال: <code>50000</code>", null, 'HTML');
        return;
    }
    $cmFinalIrr = (int) $cmIrrRaw;
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        nm_adminInstantReply($from_id, "❌ تراکنش یافت نشد.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($cmPayment['payment_Status'] === 'paid') {
        nm_adminInstantReply($from_id, "❌ این فاکتور قبلاً تایید شده است.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    update("Payment_report", "price", (string) $cmFinalIrr, "id_order", $cmOrderId);
    $cmPayment['price'] = (string) $cmFinalIrr;
    $cmPrevMsgId = (int) ($user['Processing_value_tow'] ?? 0);
    cm_apply_payment($cmOrderId, $cmFinalIrr, $cmPayment, $from_id, $username, $setting, $paymentreports, $cmPrevMsgId, null, '');
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ تایید شد با مبلغ " . number_format($cmFinalIrr) . " تومان.", $keyboardadmin, 'HTML');
} elseif (preg_match('/^cmback_(\w+)$/', (string) $datain, $cmBack) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmBack[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'تراکنش یافت نشد', 'show_alert' => true]);
        return;
    }
    $cmKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید و شارژ', 'callback_data' => 'confirmcryptomanual_' . $cmOrderId],
                ['text' => '❌ رد درخواست',           'callback_data' => 'rejectcryptomanual_' . $cmOrderId],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if (!empty($message_id)) {
        telegram('editMessageReplyMarkup', [
            'chat_id'      => $from_id,
            'message_id'   => $message_id,
            'reply_markup' => $cmKb,
        ]);
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
} elseif (preg_match('/^rejectcryptomanual_(\w+)$/', (string) $datain, $cmRej) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmRej[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'تراکنش یافت نشد',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    if ($cmPayment['payment_Status'] === 'paid') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این فاکتور قبلاً تایید شده است.',
            'show_alert' => true,
            'cache_time' => 0,
        ]);
        return;
    }
    update("user", "Processing_value", $cmPayment['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $cmOrderId, "id", $from_id);
    update("user", "Processing_value_tow", (string) ($message_id ?? 0), "id", $from_id);
    nm_adminInstantReply($from_id, "✍️ دلیل رد کردن این درخواست را وارد کنید:", $backadmin, 'HTML');
    step('reject_crypto_manual_reason', $from_id);
} elseif ($user['step'] == "reject_crypto_manual_reason" && empty($datain)) {
    $cmOrderId = (string) ($user['Processing_value_one'] ?? '');
    $cmUserId = (string) ($user['Processing_value'] ?? '');
    if ($cmOrderId === '' || $cmUserId === '') {
        nm_adminInstantReply($from_id, "❌ خطای داخلی. مجدداً تلاش کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $cmReason = trim((string) $text);
    if ($cmReason === '' || mb_strlen($cmReason) > 500) {
        nm_adminInstantReply($from_id, "❌ دلیل معتبر نیست. حداکثر ۵۰۰ کاراکتر.", null, 'HTML');
        return;
    }
    update("Payment_report", "payment_Status", "reject", "id_order", $cmOrderId);
    update("Payment_report", "dec_not_confirmed", $cmReason, "id_order", $cmOrderId);
    update("Payment_report", "at_updated", date('Y/m/d H:i:s'), "id_order", $cmOrderId);

    sendmessage(
        $cmUserId,
        "❌ <b>درخواست بررسی دستی پرداخت کریپتوی شما رد شد.</b>\n\n"
        . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
        . "📝 دلیل: " . htmlspecialchars($cmReason) . "\n\n"
        . "در صورت اعتراض با پشتیبانی در ارتباط باشید.",
        null,
        'HTML'
    );
    nm_adminInstantReply($from_id, "✅ درخواست با موفقیت رد شد.", $keyboardadmin, 'HTML');
    step('home', $from_id);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => "❌ بررسی دستی پرداخت کریپتو رد شد\n\n"
                . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
                . "👤 کاربر: <code>{$cmUserId}</code>\n"
                . "📝 دلیل: " . htmlspecialchars($cmReason) . "\n"
                . "👨‍💼 ادمین: <code>{$from_id}</code> (@{$username})",
            'parse_mode' => 'HTML',
        ]);
    }
} elseif (preg_match('/^cmdelete_(\w+)$/', (string) $datain, $cmDel) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmdOrderId = $cmDel[1];
    $cmdRow = select("Payment_report", "*", "id_order", $cmdOrderId, "select");
    if (!$cmdRow) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'تراکنش یافت نشد یا قبلاً حذف شده',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    if (($cmdRow['payment_Status'] ?? '') === 'paid') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این تراکنش قبلاً تایید شده — قابل حذف نیست.',
            'show_alert' => true,
            'cache_time' => 0,
        ]);
        return;
    }
    $cmdUserId = (string) ($cmdRow['id_user'] ?? '');
    try {
        $cmdDel = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :o");
        $cmdDel->execute([':o' => $cmdOrderId]);
    } catch (Throwable $e) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'خطا در حذف. لاگ را بررسی کنید.',
            'show_alert' => true,
            'cache_time' => 0,
        ]);
        error_log('[cmdelete] failed: ' . $e->getMessage());
        return;
    }
    if ($cmdUserId !== '' && function_exists('sendmessage')) {
        @sendmessage(
            $cmdUserId,
            "🗑️ <b>درخواست بررسی دستی شما لغو و حذف شد</b>\n\n"
            . "🛒 کد فاکتور: <code>" . htmlspecialchars($cmdOrderId) . "</code>\n\n"
            . "اگر سوال دارید، با پشتیبانی در ارتباط باشید.",
            null,
            'HTML'
        );
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => '✅ حذف شد',
        'cache_time' => 0,
    ]);
    if (!empty($message_id) && function_exists('Editmessagetext')) {
        $cmdOriginal = (string) ($update['callback_query']['message']['text'] ?? '');
        $cmdDoneNote = "\n\n━━━━━━━━━━━━\n🗑️ <b>لغو و حذف شده</b>\n"
            . "👨‍💼 توسط ادمین: <code>" . htmlspecialchars((string) $from_id) . "</code>\n"
            . "⏰ " . date('Y/m/d H:i:s');
        @Editmessagetext($from_id, $message_id, $cmdOriginal . $cmdDoneNote, null);
    }
    if (strlen($setting['Channel_Report'] ?? '') > 0 && (string) $setting['Channel_Report'] !== (string) $from_id) {
        @telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => "🗑️ <b>بررسی دستی پرداخت کریپتو لغو و حذف شد</b>\n\n"
                . "🛒 کد پیگیری: <code>" . htmlspecialchars($cmdOrderId) . "</code>\n"
                . "👤 کاربر: <code>" . htmlspecialchars($cmdUserId) . "</code>\n"
                . "👨‍💼 ادمین: <code>{$from_id}</code> (@{$username})",
            'parse_mode' => 'HTML',
        ]);
    }
} elseif ($text == "❌ حذف محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $json_list_marzban_panel, 'HTML');
    step('selectloc', $from_id);
} elseif ($user['step'] == "selectloc") {
    $panelResolved = function_exists('rx_resolvePanelFromInput') ? rx_resolvePanelFromInput($text, $pdo) : null;
    $locValue = is_array($panelResolved) && !empty($panelResolved['name_panel']) ? $panelResolved['name_panel'] : $text;
    update("user", "Processing_value", $locValue, "id", $from_id);
    step('remove-product', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['selectRemoveProduct'], $json_list_product_list_admin, 'HTML');
} elseif ($user['step'] == "remove-product") {
    if (!in_array($text, $name_product)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['sell']['error-product'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM product WHERE name_product =:name_product AND (FIND_IN_SET(:Location, Location) > 0 or Location= '/all')");
    $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
    $stmt->bindParam(':Location', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['RemoveedProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "✏️ ویرایش محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $list_marzban_panel_edit_product, 'HTML');
} elseif (preg_match('/locationedit_(\w+)/', $datain, $dataget)) {
    $location = $dataget[1];
    $location = $location == "all" ? "/all" : $location;
    update("user", "Processing_value_one", $location, "id", $from_id);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "کاربر عادی", 'callback_data' => 'typeagenteditproduct_f'],
            ],
            [
                ['text' => "نماینده پیشرفته", 'callback_data' => 'typeagenteditproduct_n2'],
                ['text' => "نماینده عادی", 'callback_data' => 'typeagenteditproduct_n'],
            ],
            [
                ['text' => "بازگشت", 'callback_data' => "admin"]
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 نوع کاربری را انتخاب کنید", $Response);
} elseif (preg_match('/^typeagenteditproduct_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    update("user", "Processing_value_tow", $typeagent, "id", $from_id);
    $product = [];
    $escapedText = mysqli_real_escape_string($connect, $user['Processing_value_one']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $_loc = $panel['name_panel'];
    $_stmt = $connect->prepare("SELECT * FROM product WHERE (FIND_IN_SET(?, Location) > 0 OR Location = '/all') AND agent = ?");
    $_stmt->bind_param("ss", $_loc, $typeagent);
    $_stmt->execute();
    $getdataproduct = $_stmt->get_result();
    $_stmt->close();
    $list_product = [
        'inline_keyboard' => [],
    ];
    if (isset($getdataproduct)) {
        while ($row = mysqli_fetch_assoc($getdataproduct)) {
            $list_product['inline_keyboard'][] = [
                ['text' => $row['name_product'], 'callback_data' => "productedit_" . $row['id']]
            ];
        }
        $list_product['inline_keyboard'][] = [
            ['text' => "🏠 بازگشت به منوی قبل", 'callback_data' => "locationedit_" . $user['Processing_value_one']],
        ];

        $json_list_product_list_admin = json_encode($list_product);
    }
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Product']['selectEditProduct'], $json_list_product_list_admin);
} elseif (preg_match('/^productedit_(\w+)/', $datain, $dataget)) {
    $id_product = $dataget[1];
    deletemessage($from_id, $message_id);
    update("user", "Processing_value", $id_product, "id", $from_id);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $_loc2 = $panel['name_panel']; $_pv2 = $user['Processing_value_tow'];
    $_stmt = $connect->prepare("SELECT * FROM product WHERE id = ? AND agent = ? AND (FIND_IN_SET(?, Location) > 0 OR Location = '/all') LIMIT 1");
    $_stmt->bind_param("sss", $id_product, $_pv2, $_loc2);
    $_stmt->execute();
    $info_product = $_stmt->get_result()->fetch_assoc();
    $_stmt->close();
    $count_invoice = select("invoice", "*", "name_product", $info_product['name_product'], "count");
    $infoproduct = "
📌 اطلاعات محصول در حال ویرایش:
نام محصول :  {$info_product['name_product']}
قیمت محصول : " . rxFormatToman($info_product['price_product']) . "
حجم محصول : {$info_product['Volume_constraint']}
موقعیت محصول : {$info_product['Location']}
زمان محصول : {$info_product['Service_time']}
نوع کاربری محصول : {$info_product['agent']}
ریست دوره ای حجم محصول : {$info_product['data_limit_reset']}
یادداشت محصول : {$info_product['note']}
دسته بندی محصول : {$info_product['category']}
تعداد محصول فروخته شده : $count_invoice عدد
    ";
    nm_adminInstantReply($from_id, $infoproduct, $change_product, 'HTML');
    step('product_editor', $from_id);
} elseif ($text == "قیمت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "قیمت جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_price', $from_id);
} elseif ($user['step'] == "change_price") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET price_product = :price_product WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':price_product', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ قیمت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "یادداشت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "یادداشت جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_note', $from_id);
} elseif ($user['step'] == "change_note") {
    if (!isset($update['message']) && empty($text)) { return; }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET note = :notes WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':notes', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ یادداشت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "دسته بندی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نام دسته بندی جدید را انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step('change_categroy', $from_id);
} elseif ($user['step'] == "change_categroy") {
    if (!isset($update['message']) && empty($text)) { return; }
    $category = select("category", "*", "remark", $text, "count");
    if ($category == 0) {
        nm_adminInstantReply($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی ُ دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", KeyboardCategoryadmin(), 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET category = :categroy WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':categroy', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ دسته بندی محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "نام محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نام جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_name', $from_id);
} elseif ($user['step'] == "change_name") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (strlen($text) > 150) {
        nm_adminInstantReply($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $name_product)) {
        nm_adminInstantReply($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET name_product = :name_products WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':name_products', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "نوع کاربری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نوع کاربری جدید را ارسال کنید :
نوع کاربری ها :f , n , n2", $backadmin, 'HTML');
    step('change_type_agent', $from_id);
} elseif ($user['step'] == "change_type_agent") {
    if (!isset($update['message']) && empty($text)) { return; }
    $grp = function_exists('rx_resolveAgentGroupFromReplyButton') ? rx_resolveAgentGroupFromReplyButton($text, ['f', 'n', 'n2']) : (in_array($text, ['f', 'n', 'n2'], true) ? $text : null);
    if ($grp === null) {
        nm_adminInstantReply($from_id, "❌ گروه کاربری نامعتبر می باشد", null, 'HTML');
        return;
    }
    $text = $grp;
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET agent = :agents WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':agents', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "نوع ریست حجم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نوع ریست حجم را ارسال کنید", $keyboardtimereset, 'HTML');
    step('change_reset_data', $from_id);
} elseif ($user['step'] == "change_reset_data") {
    if (!isset($update['message']) && empty($text)) { return; }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET data_limit_reset = :data_limit_reset WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':data_limit_reset', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "موقعیت محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 موقعیت جدید محصول را انتخاب کنید", $json_list_marzban_panel, 'HTML');
    step('change_loc_data', $from_id);
} elseif ($user['step'] == "change_loc_data") {
    if (!isset($update['message']) && empty($text)) { return; }
    if ($text == "/all") {
        nm_adminInstantReply($from_id, "❌ نمی توانید محصول تعریف شده را به نام موقعیت /all تغییر دهید.", $shopkeyboard, 'HTML');
        return;
    }
    $panelResolved = function_exists('rx_resolvePanelFromInput') ? rx_resolvePanelFromInput($text, $pdo) : null;
    $targetLocation = is_array($panelResolved) && !empty($panelResolved['name_panel']) ? $panelResolved['name_panel'] : $text;
    $product = select("product", "*", "name_product", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Location = :Location2 WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Location2', $targetLocation);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $stmt = $pdo->prepare("UPDATE invoice SET Service_location = :Service_location WHERE name_product = :name_product AND Service_location = :Location ");
    $stmt->bindParam(':Service_location', $targetLocation);
    $stmt->bindParam(':name_product', $product['name_product']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅موقعیت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "حجم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "حجم جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_val', $from_id);
} elseif ($user['step'] == "change_val") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $product = select("product", "*", "id", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
    $stmt = $pdo->prepare("UPDATE product SET Volume_constraint = :Volume_constraint WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Volume_constraint', $text);
    $stmt->bindParam(':name_product', $product['id']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['volumeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "زمان" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['NewTime'], $backadmin, 'HTML');
    step('change_time', $from_id);
} elseif ($user['step'] == "change_time") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidtime'] ?? '❌ زمان نامعتبر است', $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Service_time = :Service_time WHERE id = :id_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Service_time', $text);
    $stmt->bindParam(':id_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['TimeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "balanceaddall") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['addallbalance'], $backadmin, 'HTML');
    step('add_Balance_all', $from_id);
} elseif ($user['step'] == "add_Balance_all") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    savedata("clear", "price", $text);
    $keyboardagent = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typebalanceall_all'],
            ],
            [
                ['text' => "کاربران گروه f", 'callback_data' => 'typebalanceall_f'],
                ['text' => "کاربران گروه n", 'callback_data' => 'typebalanceall_nl'],
                ['text' => "کاربران گروه n2", 'callback_data' => 'typebalanceall_n2'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 شارژ برای کدام یک از گروه کاربری زیر واریز شود.", $keyboardagent, 'HTML');
} elseif (preg_match('/typebalanceall_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    savedata("save", "agent", $typeagent);
    $keyboardtypeuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typecustomer_all'],
            ],
            [
                ['text' => "کاربرانی که خرید داشتند", 'callback_data' => 'typecustomer_customer'],
            ],
            [
                ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typecustomer_notcustomer'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 چه کاربر شارژ همگانی ارسال شود", $keyboardtypeuser);
} elseif (preg_match('/typecustomer_(\w+)/', $datain, $dataget)) {
    $typecustomer = $dataget[1];
    savedata("save", "typecustomer", $typecustomer);
    nm_adminInstantReply($from_id, "📌 برای کاربران پیام ارسال شارژ ارسال شود یا خیر؟
بله : 1
خیر : 0", $backadmin, 'HTML');
    step("getmeesagestatus", $from_id);
} elseif ($user['step'] == "getmeesagestatus") {
    if (!isset($update['message']) && empty($text)) { return; }
    $userdata = json_decode($user['Processing_value'], true);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['AddBalanceUsers'], $keyboardadmin, 'HTML');
    $query_where = "";
    if ($userdata['agent'] == "all") {
        if ($userdata['typecustomer'] == "all") {
            $query_where = "";
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = "WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = "WHERE  NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    } else {
        if ($userdata['typecustomer'] == "all") {
            $query_where = null;
            ;
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    }
    $stmt = $pdo->prepare("SELECT u.id FROM user u " . $query_where);
    $stmt->execute();
    $Balance_user = $stmt->fetchAll();
    $stmt = $pdo->prepare("UPDATE user as u SET  Balance = Balance + {$userdata['price']} " . $query_where);
    $stmt->execute();
    step('home', $from_id);
    if ($text == "1") {
        $cancelmessage = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
                ],
            ]
        ]);
        $textgift = "🎁 کاربر  عزیز مبلغ " . rxFormatToman($userdata['price']) . " تومان از طرف مدیریت به عنوان هدیه به کیف پول شما واریز گردید.";
        $message_id = sendmessage($from_id, "✅ عملیات ارسال پیام آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage, "html");
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $textgift,
            "pingmessage" => "no",
            "btnmessage" => "start"
        ));
        file_put_contents("cronbot/users.json", json_encode($Balance_user));
        file_put_contents('cronbot/info', $data);
    }
} elseif ($text == "⬇️ کم کردن موجودی") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['NegativeBalance'], $backadmin, 'HTML');
    step('Negative_Balance', $from_id);
} elseif ($user['step'] == "Negative_Balance") {
    if (!userExists($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['PriceBalancek'], $backadmin, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step('get_price_Negative', $from_id);
} elseif ($user['step'] == "get_price_Negative") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) >= 100000000) {
        nm_adminInstantReply($from_id, "📌 حداکثر مقدار 100 میلیون ریال است.", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['NegativeBalanceUser'], $keyboardadmin, 'HTML');

    $stmtAtomic = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid");
    $stmtAtomic->bindValue(':delta', (int) $text, PDO::PARAM_INT);
    $stmtAtomic->bindValue(':uid', $user['Processing_value'], PDO::PARAM_STR);
    $stmtAtomic->execute();
    $balances1 = number_format($text, 0);
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    $textkam = "❌ کاربر عزیز مبلغ $balances1 تومان از  موجودی کیف پول تان کسر گردید.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را کم کرده است :

🪪 اطلاعات ادمین کم کننده موجودی :
<blockquote>نام کاربری :@$username</blockquote>
<blockquote>آیدی عددی : $from_id</blockquote>
👤 اطلاعات کاربر  :
<blockquote>آیدی عددی کاربر  : {$user['Processing_value']}</blockquote>
<blockquote>مبلغ موجودی : $text</blockquote>
<blockquote>موجودی کاربر پس از کم کردن : $Balance_user_afters</blockquote>";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "searchuser" || $datain == "support_search" || $text == "👁‍🗨 جستجو کاربر") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetIdUserunblock'], $backadmin, 'HTML');
    step('show_info', $from_id);
} elseif ($user['step'] == "show_info" || preg_match('/manageuser_(\w+)/', $datain, $dataget) || preg_match('/updateinfouser_(\w+)/', $datain, $dataget) || (is_string($datain) && preg_match('/^toggle_user_cardpayment_(\d+)$/', $datain)) || strpos($text, "/user ") !== false || strpos($text, "/id ") !== false) {
    $rxCardpayToggle = is_string($datain) && preg_match('/^toggle_user_cardpayment_(\d+)$/', $datain, $rxCardpayMatch);
    $rxCardpayNotice = null;
    if ($rxCardpayToggle) {
        $id_user = $rxCardpayMatch[1];
    } elseif ($user['step'] == "show_info") {
        if (!isset($update['message']) && empty($text)) { return; }
        $id_user = $text;
    } elseif (explode(" ", $text)[0] == "/user") {
        $id_user = explode(" ", $text)[1];
    } elseif (explode(" ", $text)[0] == "/id") {
        $id_user = explode(" ", $text)[1];
    } else {
        $id_user = $dataget[1];
    }
    if (!userExists($id_user)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
        return;
    }
    if ($rxCardpayToggle) {
        $rxCardpayOk = false;
        $rxCardpayNew = null;
        try {
            $rxCardpayStmt = $pdo->prepare("SELECT cardpayment FROM user WHERE id = ? LIMIT 1");
            $rxCardpayStmt->execute([$id_user]);
            $rxCardpayCurrent = $rxCardpayStmt->fetchColumn();
            $rxCardpayStmt->closeCursor();
            if ($rxCardpayCurrent !== false) {
                $rxCardpayNew = intval($rxCardpayCurrent) === 1 ? "0" : "1";
                update("user", "cardpayment", $rxCardpayNew, "id", $id_user);
                $rxCardpayStmt->execute([$id_user]);
                $rxCardpayAfter = $rxCardpayStmt->fetchColumn();
                $rxCardpayStmt->closeCursor();
                $rxCardpayOk = $rxCardpayAfter !== false && (string) intval($rxCardpayAfter) === $rxCardpayNew;
            }
        } catch (Throwable $rxCardpayErr) {
            error_log('[toggle_user_cardpayment] ' . $rxCardpayErr->getMessage());
            $rxCardpayOk = false;
        }
        if (!$rxCardpayOk) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => "❌ تغییر وضعیت نمایش شماره کارت انجام نشد. دوباره تلاش کنید.",
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            return;
        }
        $rxCardpayNotice = $rxCardpayNew === "1"
            ? "✅ نمایش شماره کارت برای این کاربر فعال شد"
            : "✅ نمایش شماره کارت برای این کاربر غیرفعال شد";
    }
    $date = date("Y-m-d");
    $_stmt = $connect->prepare("SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ?");
    $_stmt->bind_param("s", $id_user); $_stmt->execute();
    $dayListSell = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $_stmt = $connect->prepare("SELECT SUM(price) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = ? AND Payment_Method != 'low balance by admin'");
    $_stmt->bind_param("s", $id_user); $_stmt->execute();
    $balanceall = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $_stmt = $connect->prepare("SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ?");
    $_stmt->bind_param("s", $id_user); $_stmt->execute();
    $subbuyuser = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $invoicecount = select("invoice", '*', "id_user", $id_user, "count");
    if ($invoicecount == 0) {
        $sumvolume['SUM(Volume)'] = 0;
    } else {
        $_stmt = $connect->prepare("SELECT SUM(Volume) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ? AND name_product != 'سرویس تست'");
        $_stmt->bind_param("s", $id_user); $_stmt->execute();
        $sumvolume = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    }
    $user = select("user", "*", "id", $id_user, "select");
    $roll_Status = [
        '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'],
        '0' => $textbotlang['Admin']['ManageUser']['Failedphone'],
    ][$user['roll_Status']];
    if ($subbuyuser['SUM(price_product)'] == null)
        $subbuyuser['SUM(price_product)'] = 0;
    $keyboardmanage = [
        'inline_keyboard' => [
            [['text' => "♻️  بروزرسانی اطلاعات", 'callback_data' => "updateinfouser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['addbalanceuser'], 'callback_data' => "addbalanceuser_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['lowbalanceuser'], 'callback_data' => "lowbalanceuser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['banuserlist'], 'callback_data' => "banuserlist_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['unbanuserlist'], 'callback_data' => "unbanuserr_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['addagent'], 'callback_data' => "addagent_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['removeagent'], 'callback_data' => "removeagent_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['confirmnumber'], 'callback_data' => "confirmnumber_" . $id_user]],
            [['text' => "🎁 درصد تخفیف", 'callback_data' => "Percentlow_" . $id_user], ['text' => "✍️ ارسال پیام", 'callback_data' => "sendmessageuser_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['vieworderuser'], 'callback_data' => "vieworderuser_" . $id_user]],
            [['text' => "👥 زیرمجموعه‌ها", 'callback_data' => "affiliates-" . $id_user]],
            [['text' => "🔄 خروج از زیرمجموعه", 'callback_data' => "removeaffiliate-" . $id_user], ['text' => "🔄 حذف زیرمجموعه‌ها", 'callback_data' => "removeaffiliateuser-" . $id_user]],
            [['text' => "احراز هویت کاربر", 'callback_data' => "verify_" . $id_user], ['text' => "عدم احراز کاربر", 'callback_data' => "unverify-" . $id_user]],
            [['text' => "🛒 افزودن سفارش", 'callback_data' => "addordermanualـ" . $id_user], ['text' => "➕ محدودیت تست", 'callback_data' => "limitusertest_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['viewpaymentuser'], 'callback_data' => "viewpaymentuser_" . $id_user], ['text' => "انتقال حساب کاربری ", 'callback_data' => "transferaccount_" . $id_user]],
            [['text' => "💡 خاموش کردن", 'callback_data' => "disableconfig-" . $id_user], ['text' => "💡 روشن کردن", 'callback_data' => "activeconfig-" . $id_user]],
            [['text' => "📑 احراز عضویت", 'callback_data' => "confirmchannel-" . $id_user], ['text' => "0️⃣ صفر کردن موجودی", 'callback_data' => "zerobalance-" . $id_user]],
            [['text' => "🕚 وضعیت ارسال پیام های کرون", 'callback_data' => "statuscronuser-" . $id_user]],
            [[
                'text' => intval($user['cardpayment'] ?? 0) === 1
                    ? "💳 غیرفعال‌سازی نمایش شماره کارت"
                    : "💳 فعال‌سازی نمایش شماره کارت",
                'callback_data' => "toggle_user_cardpayment_" . $id_user,
            ]],
            [['text' => "💳 منوی کارت", 'callback_data' => "usercardmenu_" . $id_user]],
            [[
                'text' => intval($user['card_verify_bypass'] ?? 0) === 1
                    ? "🛡️ فعال‌سازی احراز کارت"
                    : "🛡️ حذف احراز کارت",
                'callback_data' => intval($user['card_verify_bypass'] ?? 0) === 1
                    ? "cardverifyon_" . $id_user
                    : "cardverifyoff_" . $id_user,
            ]],
        ]
    ];
    if ($user['agent'] == "n2")
        $keyboardmanage['inline_keyboard'][] = [['text' => "سقف خرید  نماینده", 'callback_data' => "maxbuyagent_" . $id_user]];
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🤖 فعال‌سازی ربات", 'callback_data' => "createbot_" . $id_user],
            ['text' => "❌ حذف ربات فروش", 'callback_data' => "removebotsell_" . $id_user]
        ];
    }
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🔋 قیمت پایه حجم", 'callback_data' => "setvolumesrc_" . $id_user],
            ['text' => "⏳ قیمت پایه زمان", 'callback_data' => "settimepricesrc_" . $id_user]
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "❌ مخفی کردن یک پنل برای نماینده", 'callback_data' => "hidepanel_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🗑 نمایش پنل های مخفی شده", 'callback_data' => "removehide_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => "expireset_" . $id_user],
        ];
    }
    if (intval($setting['statuslimitchangeloc']) == 1) {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "محدودیت تغییر لوکیشن", 'callback_data' => "changeloclimitbyuser_" . $id_user]
        ];
    }
    $keyboardmanage['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $keyboardmanage = json_encode($keyboardmanage, JSON_UNESCAPED_UNICODE);
    $user['Balance'] = number_format($user['Balance']);
    if ($user['register'] != "none") {
        if ($user['register'] == null)
            return;
        $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    } else {
        $userjoin = "نامشخص";
    }
    $userverify = [
        '0' => "احراز نشده",
        '1' => "احراز شده"
    ][$user['verify']];
    $showcart = [
        '0' => "مخفی",
        '1' => "نمایش داده می شود"
    ][$user['cardpayment']];
    if ($user['last_message_time'] == null) {
        $lastmessage = "";
    } else {
        $lastmessage = jdate('Y/m/d H:i:s', $user['last_message_time']);
    }
    $datefirst = time() - 86400;
    $desired_date_time_start = time() - 3600;
    $month_date_time_start = time() - 2592000;
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $listhours = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $suminvoicehours = $stmt->fetchColumn();
    if ($suminvoicehours == null) {
        $suminvoicehours = "0";
    }
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $listmonth = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $suminvoicemonth = $stmt->fetchColumn();
    if ($suminvoicemonth == null) {
        $suminvoicemonth = "0";
    }
    if ($user['agent'] != "f" && $user['expire'] != null) {
        $text_expie_agent = "⭕️ تاریخ پایان نمایندگی : " . jdate('Y/m/d H:i:s', $user['expire']);
    } else {
        $text_expie_agent = "";
    }
    $rxFmtUserBalance = rxFormatToman($user['Balance']);
    $rxFmtBalanceAllSum = rxFormatToman($balanceall['SUM(price)'] ?? 0);
    $rxFmtSubbuyuserSum = rxFormatToman($subbuyuser['SUM(price_product)'] ?? 0);
    $rxFmtSuminvoicehours = rxFormatToman($suminvoicehours);
    $rxFmtSuminvoicemonth = rxFormatToman($suminvoicemonth);
    $textinfouser = "👀 اطلاعات کاربر:

🔗 اطلاعات کاربری کاربر

⭕️ وضعیت کاربر : {$user['User_Status']}
⭕️ نام کاربری کاربر : @{$user['username']}
⭕️ آیدی عددی کاربر :  <a href = \"tg://user?id=$id_user\">$id_user</a>
⭕️ کد معرف کاربر : {$user['codeInvitation']}
⭕️ زمان عضویت کاربر : $userjoin
⭕️ آخرین زمان  استفاده کاربر از ربات : $lastmessage
⭕️ محدودیت اکانت تست :  {$user['limit_usertest']}
⭕️ وضعیت تایید قانون : $roll_Status
⭕️ شماره موبایل : <code>{$user['number']}</code>
⭕️ نوع کاربری : {$user['agent']}
⭕️ تعداد زیرمجموعه کاربر : {$user['affiliatescount']}
⭕  معرف کاربر : {$user['affiliates']}
⭕  وضعیت احراز هویت: $userverify
⭕  نمایش شماره کارت :‌$showcart
⭕ امتیاز کاربر : {$user['score']}
⭕️  مجموع حجم خریداری شده فعال ( برای آمار دقیق حجم باید کرون روشن باشد): {$sumvolume['SUM(Volume)']}
$text_expie_agent

💎 گزارشات مالی

🔰 موجودی کاربر : {$rxFmtUserBalance}
🔰 تعداد خرید کل کاربر : {$dayListSell['COUNT(*)']}
🔰️ مبلغ کل پرداختی  :  {$rxFmtBalanceAllSum}
🔰 جمع کل خرید : {$rxFmtSubbuyuserSum}
🔰 درصد تخفیف کاربر : {$user['pricediscount']}
🔰 تعداد فروش یک ساعت گذشته : $listhours عدد
🔰 مجموع فروش یک ساعت گذشته : {$rxFmtSuminvoicehours} تومان
🔰 تعداد فروش یک ماه گذشته : $listmonth عدد
🔰 مجموع فروش یک ماه گذشته : {$rxFmtSuminvoicemonth} تومان

";
    if ($rxCardpayNotice !== null) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $rxCardpayNotice,
            'show_alert' => false,
            'cache_time' => 0,
        ]);
        Editmessagetext($from_id, $message_id, $textinfouser, $keyboardmanage);
    } elseif (is_string($datain) && isset($datain[0]) && $datain[0] == "u") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "اطلاعات بروزرسانی گردید",
            'show_alert' => true,
            'cache_time' => 0,
        ));
        Editmessagetext($from_id, $message_id, $textinfouser, $keyboardmanage);
    } elseif (is_string($datain) && strpos($datain, 'manageuser_') === 0) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
        sendmessage($from_id, $textinfouser, $keyboardmanage, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $textinfouser, $keyboardmanage, 'HTML');
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
    }
    step('home', $from_id);
} elseif ($text == "🎁 ساخت کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "get_code") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "get_price_code") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "getlimitcodedis") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ حذف کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "remove-Discount") {
    nm_adminInstantReply($from_id, "🌐 ساخت و مدیریت کد تخفیف و کد هدیه از طریق ربات غیرفعال شده است.\n\nلطفاً برای ساخت یا مدیریت کدهای تخفیف و هدیه به پنل تحت وب مراجعه کنید.", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "🗑 حذف پروتکل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['RemoveProtocol'], $keyboardprotocollist, 'HTML');
    step('removeprotocol', $from_id);
} elseif ($user['step'] == "removeprotocol") {
    if (!in_array($text, $protocoldata)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['invalidProtocol'], null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['RemovedProtocol'], $optionMarzban, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM protocol WHERE NameProtocol = :protocol");
    $stmt->bindParam(':protocol', $text, PDO::PARAM_STR);
    $stmt->execute();
    step('home', $from_id);
} elseif ($text == "💡 ساخت نام کاربری" && $adminrulecheck['rule'] == "administrator") {
    $text_username = "⭕️ ساخت نام کاربری برای اکانت ها را از دکمه زیر انتخاب نمایید.

⚠️ در صورتی که کاربری نام کاربری نداشته باشه کلمه انتخابی توسط شما ثبت خواهد شد جای نام کاربری اعمال خواهد شد.

⚠️ در صورتی که نام کاربری وجود داشته باشه یک عدد رندوم به نام کاربری اضافه خواهد شد";
    $rxMuPanelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string) ($user['Processing_value'] ?? '');
    $rxMuPanelRow = $rxMuPanelName !== '' ? select("marzban_panel", "*", "name_panel", $rxMuPanelName, "select") : false;
    $rxMuKeyboard = (is_array($rxMuPanelRow) && ($rxMuPanelRow['type'] ?? '') === 'remnawave') ? $MethodUsernameRemna : $MethodUsername;
    nm_adminInstantReply($from_id, $text_username, $rxMuKeyboard, 'HTML');
    step('updatemethodusername', $from_id);
} elseif ($user['step'] == "updatemethodusername") {
    if (!isset($update['message']) && empty($text)) { return; }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $allowedMethods = [
        "آیدی عددی + حروف و عدد رندوم",
        "نام کاربری + حروف و عدد رندوم",
        "نام کاربری دلخواه + عدد رندوم",
        "متن دلخواه کاربر + رندوم",
        "متن دلخواه + عدد رندوم",
        "متن دلخواه + عدد ترتیبی",
        "نام کاربری + عدد به ترتیب",
        "آیدی عددی+عدد ترتیبی",
        "متن دلخواه نماینده + عدد ترتیبی",
        "نام کاربری دلخواه",
        "آیدی عددی",
    ];
    if (!in_array($text, $allowedMethods, true)) {
        $rxMuReshowRow = select("marzban_panel", "*", "name_panel", $panelName, "select");
        $rxMuReshowKb = (is_array($rxMuReshowRow) && ($rxMuReshowRow['type'] ?? '') === 'remnawave') ? $MethodUsernameRemna : $MethodUsername;
        nm_adminInstantReply($from_id, "❌ گزینه نامعتبر است. لطفاً یکی از دکمه‌های زیر را انتخاب کنید.", $rxMuReshowKb, 'HTML');
        return;
    }
    update("marzban_panel", "MethodUsername", $text, "name_panel", $panelName);
    update("user", "Processing_value", $panelName, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    if ($text == "متن دلخواه + عدد رندوم" || $text == "متن دلخواه + عدد ترتیبی" || $text == "متن دلخواه نماینده + عدد ترتیبی") {
        step('getnamecustom', $from_id);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['customnamesend'], $getNameCustomKb, 'HTML');
        return;
    }
    if ($text == "نام کاربری + حروف و عدد رندوم" || $text == "نام کاربری + عدد به ترتیب") {
        step('getnamecustom', $from_id);
        nm_adminInstantReply($from_id, "📌 در صورتی که کاربر نام کاربری نداشت چه اسمی ثبت شود؟", $getNameCustomKb, 'HTML');
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['AlgortimeUsername']['SaveData']);
    step('PanelMenu', $from_id);
} elseif ($user['step'] == "getnamecustom") {
    if (!isset($update['message']) && empty($text)) { return; }
    if ($text === '🎲 ساخت رندوم خودکار') {
        $text = strtolower('usr' . bin2hex(random_bytes(2)));
    }
    $text = str_replace('_', '-', $text);
    if (!preg_match('/^[A-Za-z0-9-]{2,32}$/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidname'], $getNameCustomKb, 'html');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    update("marzban_panel", "namecustom", $text, "name_panel", $panelName);
    update("user", "Processing_value", $panelName, "id", $from_id);
    step('home', $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['savedname']);
} elseif (($datain == "cartsetting" || $text == "▶️ بازگشت به منوی تظنیمات کارت") && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_cart_settings', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $CartManage, 'HTML');
} elseif ($datain == "cart_manage_inline" || $text == "💳 مدیریت شماره کارت" || preg_match('/^card_list_page_(\d+)$/', $datain, $_clpg_m)) {
    step('home', $from_id);
    $_cl_page = isset($_clpg_m[1]) ? max(1, (int)$_clpg_m[1]) : 1;
    $_cl_per = 5;
    $_cl_off = ($_cl_page - 1) * $_cl_per;
    $_cl_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM card_number");
    $_cl_count_stmt->execute();
    $_cl_total = (int)$_cl_count_stmt->fetchColumn();
    $_cl_pages = max(1, (int)ceil($_cl_total / $_cl_per));
    $stmt = $pdo->prepare("SELECT id, cardnumber, namecard, is_active, is_visible FROM card_number ORDER BY created_at DESC LIMIT {$_cl_per} OFFSET {$_cl_off}");
    $stmt->execute();
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_cl_total === 0) {
        $_emk = [
            [['text' => "➕ افزودن شماره کارت", 'callback_data' => 'card_add_new']],
        ];
        if ($adminrulecheck['rule'] == "administrator") {
            $_emk[] = [['text' => "⚙️ روش نمایش به کاربر", 'callback_data' => 'card_display_mode_menu']];
        }
        $_emk[] = [['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back']];
        $inline_kb = json_encode(['inline_keyboard' => $_emk], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, "📭 هیچ شماره کارتی موجود نیست\n\n➕ برای افزودن کارت جدید اینجا کلیک کنید:", $inline_kb, 'HTML');
    } else {
        $inline_kb = ['inline_keyboard' => []];
        foreach ($cards as $card) {
            $status = ($card['is_active'] == 1) ? '✅' : '❌';
            $display_num = substr($card['cardnumber'], 0, 4) . "****" . substr($card['cardnumber'], -4);
            $inline_kb['inline_keyboard'][] = [['text' => "$status {$card['namecard']} ($display_num)", 'callback_data' => 'card_select_' . $card['id']]];
        }
        $_cl_pg_row = [];
        if ($_cl_page > 1) {
            $_cl_pg_row[] = ['text' => "◀️ قبل", 'callback_data' => 'card_list_page_' . ($_cl_page - 1)];
        }
        if ($_cl_page < $_cl_pages) {
            $_cl_pg_row[] = ['text' => "▶️ بعد", 'callback_data' => 'card_list_page_' . ($_cl_page + 1)];
        }
        if (!empty($_cl_pg_row)) {
            $inline_kb['inline_keyboard'][] = $_cl_pg_row;
        }
        $inline_kb['inline_keyboard'][] = [['text' => "➕ افزودن شماره کارت جدید", 'callback_data' => 'card_add_new']];
        if ($adminrulecheck['rule'] == "administrator") {
            $inline_kb['inline_keyboard'][] = [['text' => "⚙️ روش نمایش به کاربر", 'callback_data' => 'card_display_mode_menu']];
        }
        $inline_kb['inline_keyboard'][] = [['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back']];
        $_cl_pg_label = $_cl_pages > 1 ? "\n\n📄 صفحه {$_cl_page} از {$_cl_pages}" : "";
        nm_adminInstantReply($from_id, "📋 کارت‌های ثبت‌شده:\n\n(برای مدیریت یک کارت روی آن کلیک کنید){$_cl_pg_label}", json_encode($inline_kb, JSON_UNESCAPED_UNICODE), 'HTML');
    }
} elseif ($datain == "card_add_new") {
    nm_adminInstantReply($from_id, "📌 شماره کارت را وارد کنید (بدون فاصله و علامت):", $backadmin, 'HTML');
    step('card_add_cardnumber', $from_id);
} elseif ($user['step'] == "card_add_cardnumber" && !isset($update['callback_query'])) {
    $card_num = preg_replace('/\D/', '', $text);
    if (strlen($card_num) < 16 || strlen($card_num) > 20) {
        nm_adminInstantReply($from_id, "❌ شماره کارت باید بین 16 تا 20 رقم باشد", $backadmin, 'HTML');
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM card_number WHERE cardnumber = ?");
    $stmt->execute([$card_num]);
    if ($stmt->rowCount() > 0) {
        nm_adminInstantReply($from_id, "❌ این شماره کارت قبلاً ثبت شده است", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }

    update("user", "Processing_value", $card_num, "id", $from_id);
    nm_adminInstantReply($from_id, "📌 نام دارنده کارت یا بانک را وارد کنید:", $backadmin, 'HTML');
    step('card_add_cardname', $from_id);
} elseif ($user['step'] == "card_add_cardname" && !isset($update['callback_query'])) {
    try {
        $card_num = (string)$user['Processing_value'];
        $card_name = trim($text);
        if (empty($card_name) || strlen($card_name) > 1000) {
            nm_adminInstantReply($from_id, "❌ نام کارت نامعتبر است", $backadmin, 'HTML');
            return;
        }

        $stmt = $pdo->prepare("INSERT INTO card_number (cardnumber, namecard, is_active, is_visible, created_at) VALUES (?, ?, 1, 1, ?)");
        $stmt->execute([$card_num, $card_name, time()]);

        nm_adminInstantReply($from_id, "✅ شماره کارت با موفقیت افزوده شد", $CartManage, 'HTML');
        step('home', $from_id);
    } catch (Exception $e) {
        error_log('[card_add] ' . $e->getMessage());
        nm_adminInstantReply($from_id, "❌ خطا در ثبت کارت. لطفاً دوباره تلاش کنید", $CartManage, 'HTML');
        step('home', $from_id);
    }
} elseif (preg_match('/^card_select_(\d+)$/', $datain, $m)) {
    $card_id = (int)$m[1];
    step('home', $from_id);
    $stmt = $pdo->prepare("SELECT id, cardnumber, namecard, is_active, is_visible FROM card_number WHERE id = ?");
    $stmt->execute([$card_id]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ کارت پیدا نشد', 'show_alert' => true]);
        return;
    }

    update("user", "Processing_value", $card_id, "id", $from_id);

    $active_badge = ($card['is_active'] == 1) ? '✅ فعال' : '❌ غیرفعال';

    $card_menu = json_encode(['inline_keyboard' => [
        [['text' => "📝 {$card['namecard']}", 'callback_data' => 'card_show_info']],
        [['text' => $active_badge, 'callback_data' => 'card_toggle_active']],
        [['text' => "📋 مدیریت وایت لیست", 'callback_data' => 'card_manage_whitelist']],
        [['text' => "✏️ ویرایش کارت", 'callback_data' => 'card_edit_start']],
        [['text' => "❌ حذف کارت", 'callback_data' => 'card_delete_confirm']],
        [['text' => "🔙 بازگشت", 'callback_data' => 'cart_manage_inline']]
    ]], JSON_UNESCAPED_UNICODE);

    nm_adminInstantReply($from_id, "💳 مدیریت کارت:\n\n🏦 نام: {$card['namecard']}\n🔢 شماره: <code>{$card['cardnumber']}</code>\n$active_badge", $card_menu, 'HTML');
} elseif ($datain == "card_toggle_active") {
    $card_id = (int)$user['Processing_value'];
    $stmt = $pdo->prepare("SELECT id, cardnumber, namecard, is_active FROM card_number WHERE id = ?");
    $stmt->execute([$card_id]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($card) {
        $new_status = ($card['is_active'] == 1) ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE card_number SET is_active = ? WHERE id = ?");
        $stmt->execute([$new_status, $card_id]);

        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => ($new_status == 1 ? '✅ فعال شد' : '❌ غیرفعال شد'), 'show_alert' => false]);

        $active_badge = ($new_status == 1) ? '✅ فعال' : '❌ غیرفعال';
        $updated_menu = json_encode(['inline_keyboard' => [
            [['text' => "📝 {$card['namecard']}", 'callback_data' => 'card_show_info']],
            [['text' => $active_badge, 'callback_data' => 'card_toggle_active']],
            [['text' => "📋 مدیریت وایت لیست", 'callback_data' => 'card_manage_whitelist']],
            [['text' => "✏️ ویرایش کارت", 'callback_data' => 'card_edit_start']],
            [['text' => "❌ حذف کارت", 'callback_data' => 'card_delete_confirm']],
            [['text' => "🔙 بازگشت", 'callback_data' => 'cart_manage_inline']]
        ]], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, "💳 مدیریت کارت:\n\n🏦 نام: {$card['namecard']}\n🔢 شماره: <code>{$card['cardnumber']}</code>\n$active_badge", $updated_menu, 'HTML');
    }
} elseif ($datain == "card_manage_whitelist" || preg_match('/^card_wl_page_(\d+)$/', $datain, $_wlpg_m)) {
    step('home', $from_id);
    $_wl_page = isset($_wlpg_m[1]) ? max(1, (int)$_wlpg_m[1]) : 1;
    $_wl_per = 5;
    $_wl_off = ($_wl_page - 1) * $_wl_per;
    $card_id = (int)$user['Processing_value'];
    $_wl_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM card_whitelist WHERE card_id = ?");
    $_wl_count_stmt->execute([$card_id]);
    $_wl_total = (int)$_wl_count_stmt->fetchColumn();
    $_wl_pages = max(1, (int)ceil($_wl_total / $_wl_per));
    $stmt = $pdo->prepare("SELECT user_id FROM card_whitelist WHERE card_id = ? ORDER BY created_at DESC LIMIT {$_wl_per} OFFSET {$_wl_off}");
    $stmt->execute([$card_id]);
    $whitelisted = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($_wl_total === 0) {
        $wl_text = "📭 هیچ کاربری وایت لیست نشده";
    } else {
        $wl_text = "📋 کاربران وایت لیست‌شده:\n" . implode("\n", array_map(function($u) { return "• $u"; }, $whitelisted));
        if ($_wl_pages > 1) {
            $wl_text .= "\n\n📄 صفحه {$_wl_page} از {$_wl_pages}";
        }
    }

    $_wl_rows = [];
    $_wl_rows[] = [['text' => "➕ افزودن کاربر", 'callback_data' => 'card_wl_add']];
    $_wl_rows[] = [
        ['text' => "🗑 پاک‌کردن همه", 'callback_data' => 'card_wl_clear'],
        ['text' => "✂️ حذف تکی", 'callback_data' => 'card_wl_remove']
    ];
    $_wl_pg_row = [];
    if ($_wl_page > 1) {
        $_wl_pg_row[] = ['text' => "◀️ قبل", 'callback_data' => 'card_wl_page_' . ($_wl_page - 1)];
    }
    if ($_wl_page < $_wl_pages) {
        $_wl_pg_row[] = ['text' => "▶️ بعد", 'callback_data' => 'card_wl_page_' . ($_wl_page + 1)];
    }
    if (!empty($_wl_pg_row)) {
        $_wl_rows[] = $_wl_pg_row;
    }
    $_wl_rows[] = [['text' => "🔙 بازگشت", 'callback_data' => 'card_select_' . $card_id]];
    $wl_kb = json_encode(['inline_keyboard' => $_wl_rows], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, $wl_text, $wl_kb, 'HTML');
} elseif ($datain == "card_wl_add") {
    $cancel_wl_kb = json_encode(['inline_keyboard' => [
        [['text' => "❌ لغو", 'callback_data' => 'card_manage_whitelist']]
    ]], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "📌 آیدی عددی کاربر را وارد کنید (یا چند آیدی را با ویرگول جدا کنید):", $cancel_wl_kb, 'HTML');
    step('card_wl_add_users', $from_id);
} elseif ($user['step'] == "card_wl_add_users" && !isset($update['callback_query'])) {
    $card_id = (int)$user['Processing_value'];
    $user_ids = array_filter(array_map('trim', explode(',', $text)));
    $added = 0;
    $failed = 0;

    foreach ($user_ids as $uid) {
        if (!ctype_digit($uid)) {
            $failed++;
            continue;
        }
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO card_whitelist (card_id, user_id, created_at) VALUES (?, ?, ?)");
            $stmt->execute([$card_id, $uid, time()]);
            $added += $stmt->rowCount();
        } catch (Exception $e) {
            $failed++;
        }
    }

    $back_wl_kb = json_encode(['inline_keyboard' => [
        [['text' => "🔙 بازگشت به مدیریت وایت لیست", 'callback_data' => 'card_manage_whitelist']],
        [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
    ]], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "✅ $added کاربر افزوده شد\n❌ $failed خطا یا تکراری", $back_wl_kb, 'HTML');
    step('home', $from_id);
} elseif ($datain == "card_wl_clear") {
    $card_id = (int)$user['Processing_value'];
    $stmt = $pdo->prepare("DELETE FROM card_whitelist WHERE card_id = ?");
    $stmt->execute([$card_id]);
    $back_wl_kb = json_encode(['inline_keyboard' => [
        [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
    ]], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "✅ تمام کاربران وایت لیست‌شده حذف شدند", $back_wl_kb, 'HTML');
    step('home', $from_id);
} elseif ($datain == "card_wl_remove") {
    $cancel_rm_kb = json_encode(['inline_keyboard' => [
        [['text' => "❌ لغو", 'callback_data' => 'card_manage_whitelist']]
    ]], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "📌 آیدی عددی کاربر را برای حذف از وایت لیست وارد کنید:", $cancel_rm_kb, 'HTML');
    step('card_wl_remove_uid', $from_id);
} elseif ($user['step'] == "card_wl_remove_uid" && !isset($update['callback_query'])) {
    $card_id = (int)$user['Processing_value'];
    $uid = trim($text ?? '');
    if (!ctype_digit($uid) || intval($uid) <= 0) {
        $cancel_rm_kb2 = json_encode(['inline_keyboard' => [
            [['text' => "❌ لغو", 'callback_data' => 'card_manage_whitelist']]
        ]], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, "❌ آیدی نامعتبر است. فقط عدد مجاز است", $cancel_rm_kb2, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM card_whitelist WHERE card_id = ? AND user_id = ?");
    $stmt->execute([$card_id, $uid]);
    $deleted = $stmt->rowCount();
    $back_rm_kb = json_encode(['inline_keyboard' => [
        [['text' => "🔙 بازگشت به وایت لیست", 'callback_data' => 'card_manage_whitelist']],
        [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
    ]], JSON_UNESCAPED_UNICODE);
    $msg = $deleted > 0 ? "✅ کاربر {$uid} از وایت لیست حذف شد" : "⚠️ کاربر {$uid} در وایت لیست این کارت یافت نشد";
    nm_adminInstantReply($from_id, $msg, $back_rm_kb, 'HTML');
    step('home', $from_id);
} elseif ($datain == "card_delete_confirm") {
    $card_id = (int)$user['Processing_value'];
    $confirm_kb = json_encode(['inline_keyboard' => [
        [['text' => "✅ بله، حذف کن", 'callback_data' => 'card_delete_execute']],
        [['text' => "❌ خیر", 'callback_data' => 'card_select_' . $card_id]]
    ]], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "⚠️ آیا مطمئن هستید؟ این عمل قابل بازگشت نیست", $confirm_kb, 'HTML');
} elseif ($datain == "card_delete_execute") {
    $card_id = (int)$user['Processing_value'];
    try {
        $stmt = $pdo->prepare("DELETE FROM card_whitelist WHERE card_id = ?");
        $stmt->execute([$card_id]);
        $stmt = $pdo->prepare("DELETE FROM card_number WHERE id = ?");
        $stmt->execute([$card_id]);

        nm_adminInstantReply($from_id, "✅ کارت با موفقیت حذف شد", $CartManage, 'HTML');
        step('home', $from_id);
    } catch (Exception $e) {
        error_log('[card_delete] ' . $e->getMessage());
        nm_adminInstantReply($from_id, "❌ خطا در حذف کارت", $CartManage, 'HTML');
        step('home', $from_id);
    }
} elseif ($datain == "card_edit_start") {
    $cancel_kb = json_encode(['inline_keyboard' => [
        [['text' => "❌ لغو", 'callback_data' => 'card_select_' . (int)$user['Processing_value']]]
    ]], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "📌 شماره کارت جدید را وارد کنید (16-20 رقم):", $cancel_kb, 'HTML');
    step('card_edit_number', $from_id);
} elseif ($user['step'] == "card_edit_number" && !isset($update['callback_query'])) {
    $card_id = (int)$user['Processing_value'];
    $new_number = preg_replace('/\D/', '', $text);

    $cancel_kb = json_encode(['inline_keyboard' => [
        [['text' => "❌ لغو", 'callback_data' => 'card_select_' . $card_id]]
    ]], JSON_UNESCAPED_UNICODE);

    if (strlen($new_number) < 16 || strlen($new_number) > 20) {
        nm_adminInstantReply($from_id, "❌ شماره کارت باید 16 تا 20 رقم باشد", $cancel_kb, 'HTML');
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM card_number WHERE cardnumber = ? AND id != ?");
    $stmt->execute([$new_number, $card_id]);
    if ($stmt->fetch()) {
        nm_adminInstantReply($from_id, "❌ این شماره کارت قبلاً ثبت شده است", $cancel_kb, 'HTML');
        return;
    }

    update("user", "Processing_edit_value", $new_number, "id", $from_id);
    $cancel_kb2 = json_encode(['inline_keyboard' => [
        [['text' => "❌ لغو", 'callback_data' => 'card_select_' . $card_id]]
    ]], JSON_UNESCAPED_UNICODE);
    nm_adminInstantReply($from_id, "📌 نام کارت جدید را وارد کنید:", $cancel_kb2, 'HTML');
    step('card_edit_name', $from_id);
} elseif ($user['step'] == "card_edit_name" && !isset($update['callback_query'])) {
    $card_id = (int)$user['Processing_value'];
    $new_number = (string)$user['Processing_edit_value'];
    $new_name = trim($text);

    $cancel_kb = json_encode(['inline_keyboard' => [
        [['text' => "❌ لغو", 'callback_data' => 'card_select_' . $card_id]]
    ]], JSON_UNESCAPED_UNICODE);

    if (strlen($new_name) < 1 || strlen($new_name) > 100) {
        nm_adminInstantReply($from_id, "❌ نام کارت باید بین 1 تا 100 کاراکتر باشد", $cancel_kb, 'HTML');
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE card_number SET cardnumber = ?, namecard = ? WHERE id = ?");
        $stmt->execute([$new_number, $new_name, $card_id]);

        $back_card_kb = json_encode(['inline_keyboard' => [
            [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
        ]], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, "✅ کارت با موفقیت به‌روزرسانی شد\n\n🏦 نام: $new_name\n🔢 شماره: <code>$new_number</code>", $back_card_kb, 'HTML');
        step('home', $from_id);
    } catch (Exception $e) {
        error_log('[card_edit] ' . $e->getMessage());
        $back_card_kb = json_encode(['inline_keyboard' => [
            [['text' => "🔙 بازگشت به کارت", 'callback_data' => 'card_select_' . $card_id]]
        ]], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, "❌ خطا در به‌روزرسانی کارت", $back_card_kb, 'HTML');
        step('home', $from_id);
    }
} elseif ($datain == "card_display_mode_menu" && $adminrulecheck['rule'] == "administrator") {
    $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $mode = $row ? ($row['ValuePay'] ?? 'random') : 'random';
    $r_mark = $mode == 'random' ? '✅ ' : '';
    $d_mark = $mode == 'direct' ? '✅ ' : '';
    $mode_kb = json_encode(['inline_keyboard' => [
        [
            ['text' => "{$r_mark}🎲 رندوم", 'callback_data' => 'card_mode_set_random'],
            ['text' => "{$d_mark}🔗 مستقیم", 'callback_data' => 'card_mode_set_direct']
        ],
        [['text' => "🔙 بازگشت", 'callback_data' => 'cart_manage_inline']]
    ]], JSON_UNESCAPED_UNICODE);
    $current_label = $mode == 'random' ? '🎲 رندوم' : '🔗 مستقیم (2 کارت)';
    nm_adminInstantReply($from_id, "⚙️ روش نمایش کارت به کاربر\n\nحالت فعلی: <b>{$current_label}</b>", $mode_kb, 'HTML');
} elseif (($datain == "card_mode_set_random" || $datain == "card_mode_set_direct") && $adminrulecheck['rule'] == "administrator") {
    $new_mode = $datain == "card_mode_set_random" ? 'random' : 'direct';
    $stmt = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = 'card_display_mode'");
    $stmt->execute([$new_mode]);
    if ($stmt->rowCount() === 0) {
        $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('card_display_mode', ?)")->execute([$new_mode]);
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => ($new_mode == 'random' ? '🎲 رندوم' : '🔗 مستقیم') . ' فعال شد', 'show_alert' => false]);
    $r_mark = $new_mode == 'random' ? '✅ ' : '';
    $d_mark = $new_mode == 'direct' ? '✅ ' : '';
    $mode_kb = json_encode(['inline_keyboard' => [
        [
            ['text' => "{$r_mark}🎲 رندوم", 'callback_data' => 'card_mode_set_random'],
            ['text' => "{$d_mark}🔗 مستقیم", 'callback_data' => 'card_mode_set_direct']
        ],
        [['text' => "🔙 بازگشت", 'callback_data' => 'cart_manage_inline']]
    ]], JSON_UNESCAPED_UNICODE);
    $current_label = $new_mode == 'random' ? '🎲 رندوم' : '🔗 مستقیم (2 کارت)';
    nm_adminInstantReply($from_id, "⚙️ روش نمایش کارت به کاربر\n\nحالت فعلی: <b>{$current_label}</b>", $mode_kb, 'HTML');
} elseif ($datain == "plisiosetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $NowPaymentsManage, 'HTML');
} elseif ($text == "🧩 api plisio" && $adminrulecheck['rule'] == "administrator") {
    $row = select("PaySetting", "ValuePay", "NamePay", "api_plisio");
    $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    if ($PaySetting === '' || $PaySetting === '0') {
        $rowLegacy = select("PaySetting", "ValuePay", "NamePay", "apinowpayment");
        $PaySetting = is_array($rowLegacy) ? (string)($rowLegacy['ValuePay'] ?? '') : '';
    }
    $textcart = "⚙️ api سایت plisio.net.io را ارسال نمایید

        api plisio :$PaySetting";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('api_plisio', $from_id);
} elseif ($user['step'] == "api_plisio" || $user['step'] == "apinowpayment") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $NowPaymentsManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "api_plisio");
    step('home', $from_id);
} elseif ($text == "API NOWPAYMENT") {
    $row = select("PaySetting", "ValuePay", "NamePay", "api_nowpayment");
    $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    if ($PaySetting === '' || $PaySetting === '0') {
        $row = select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller");
        $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    }
    $safeKey = htmlspecialchars($PaySetting, ENT_QUOTES, 'UTF-8');
    $displayKey = ($PaySetting !== '' && $PaySetting !== '0') ? "<code>$safeKey</code>" : '— تنظیم نشده';
    $texttronseller = "💳 API NowPayments خود را از داشبورد nowpayments.io دریافت و در این قسمت وارد کنید.\n\n"
                    . "🔑 کلید فعلی:\n$displayKey\n\n"
                    . "ℹ️ طول کلید فعلی: " . strlen($PaySetting) . " کاراکتر";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('marchent_tronseller', $from_id);
} elseif ($user['step'] == "marchent_tronseller") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');

    update("PaySetting", "ValuePay", $text, "NamePay", "marchent_tronseller");
    update("PaySetting", "ValuePay", $text, "NamePay", "api_nowpayment");
    step('home', $from_id);
} elseif ($text == "🔐 IPN Secret nowpayment" && $adminrulecheck['rule'] == "administrator") {
    $row = select("PaySetting", "ValuePay", "NamePay", "nowpayment_ipn_secret");
    $currentSecret = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    $masked = $currentSecret !== '' ? substr($currentSecret, 0, 4) . str_repeat('*', max(0, strlen($currentSecret) - 8)) . substr($currentSecret, -4) : '— تنظیم نشده';
    $textIpn = "🔐 IPN Secret درگاه NowPayments را از داشبورد NowPayments → Store Settings → IPN Secret دریافت و در این قسمت وارد کنید\n\n"
             . "🔑 مقدار فعلی : <code>$masked</code>\n\n"
             . "⚠️ این مقدار برای اعتبارسنجی امضای IPN استفاده می‌شود و باید با مقدار داخل داشبورد NowPayments دقیقاً یکی باشد.";
    nm_adminInstantReply($from_id, $textIpn, $backadmin, 'HTML');
    step('nowpayment_ipn_secret', $from_id);
} elseif ($user['step'] == "nowpayment_ipn_secret") {
    update("PaySetting", "ValuePay", trim($text), "NamePay", "nowpayment_ipn_secret");
    nm_adminInstantReply($from_id, "✅ IPN Secret با موفقیت تنظیم گردید.", $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($datain == "zarinpalsetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب کنید", $keyboardzarinpal, 'HTML');
} elseif ($text == "مرچنت زرین پال" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal")['ValuePay'];
    $textaqayepardakht = "💳 مرچنت کد خود را از زرین پال دریافت و در این قسمت وارد کنید

مرچنت کد فعلی شما : $PaySetting";
    nm_adminInstantReply($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('merchant_zarinpal', $from_id);
} elseif ($user['step'] == "merchant_zarinpal") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardzarinpal, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "merchant_zarinpal");
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'] && $adminrulecheck['rule'] == "administrator") {
    update("user", "Processing_value", "0", "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getloc'], $json_list_marzban_panel, 'HTML');
    step('GetLocationEdit', $from_id);
} elseif ($user['step'] == "GetLocationEdit") {
    $marzban_list_get = function_exists('rx_resolvePanelFromInput') ? rx_resolvePanelFromInput($text, $pdo) : select("marzban_panel", "*", "name_panel", $text, "select");
    if (!is_array($marzban_list_get) || empty($marzban_list_get)) {
        $notFoundMessage = $textbotlang['Admin']['managepanel']['nullpanel'] ?? "❌ پنل مورد نظر یافت نشد.";
        nm_adminInstantReply($from_id, $notFoundMessage, $json_list_marzban_panel, 'HTML');
        return;
    }
    $panelCanonicalName = $marzban_list_get['name_panel'];
    $text = $panelCanonicalName;
    update("user", "Processing_value", $panelCanonicalName, "id", $from_id);
    step('PanelMenu', $from_id);
    if ($marzban_list_get['type'] == "marzban") {
        $Check_token = token_panel($marzban_list_get['code_panel'], false);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Stats($panelCanonicalName);
            if ((string)($marzban_list_get['version_panel'] ?? '0') === '1') {
                $active_users = $System_Stats['active_users']
                    ?? $System_Stats['users_active']
                    ?? $System_Stats['online_users']
                    ?? 0;
            } else {
                $active_users = $System_Stats['users_active']
                    ?? $System_Stats['active_users']
                    ?? $System_Stats['online_users']
                    ?? 0;
            }
            $total_user = $System_Stats['total_user'];
            $mem_total = formatBytes($System_Stats['mem_total']);
            $mem_used = formatBytes($System_Stats['mem_used']);
            $bandwidth = formatBytes($System_Stats['outgoing_bandwidth'] + $System_Stats['incoming_bandwidth']);
            $rx_sales_metrics = rx_panel_sales_metrics_block($marzban_list_get['name_panel']);

            $Condition_marzban = "";
            $text_marzban = "
آمار پنل شما👇:

🖥 وضعیت اتصال پنل مرزبان: ✅ پنل متصل است

👥  تعداد کل کاربران: $total_user

👤 تعداد کاربران فعال: $active_users

📡 نسخه پنل مرزبان :  {$System_Stats['version']}

💻 رم  کل سرور  : $mem_total

💻 مصرف رم پنل مرزبان  : $mem_used

🌐 ترافیک کل مصرف شده  ( آپلود / دانلود) : $bandwidth

$rx_sales_metrics

⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        } else {
            $errorDetails = json_encode($Check_token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
            if (!empty($errorDetails) && $errorDetails !== 'null') {
                $text_marzban .= PHP_EOL . "علت خطا: {$errorDetails}";
            }
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "guard") {
        $guardConfig = getGuardPanelConfig($marzban_list_get['name_panel']);
        if ($guardConfig['status'] === false) {
            $errorMsg = $guardConfig['msg'] ?? $textbotlang['Admin']['managepanel']['guard']['connection_error'];
            nm_adminInstantReply($from_id, "❌ {$errorMsg}", $optionGuard, 'HTML');
        } else {
            $testResult = guardTestConnection($guardConfig['panel']['url_panel'], $guardConfig['api_key'], $guardConfig['panel']['guard_version'] ?? 'v1');
            $text_marzban = !empty($testResult['status']) ? "✅ اتصال به گارد برقرار است" : guardFormatConnectionResult($testResult);

            $guardAdminsStats = guardGetAdminsStats($guardConfig);
            $guardSubsStats = guardGetSubscriptionsStats($guardConfig);
            if (!empty($guardAdminsStats['status']) || !empty($guardSubsStats['status'])) {
                $text_marzban .= "\n\n‏──────────────\n👥 آمار کاربران و اتصال\n‏──────────────";
                if (!empty($guardSubsStats['status'])) {
                    $s = $guardSubsStats['stats'];
                    if (isset($s['total'])) {
                        $text_marzban .= "\n\n👤 کل کاربران: {$s['total']}";
                    }
                    if (isset($s['online'])) {
                        $text_marzban .= "\n\n🟢 آنلاین: {$s['online']}";
                    }
                    if (isset($s['active'])) {
                        $text_marzban .= "\n\n✅ فعال: {$s['active']}";
                    }
                    if (isset($s['expired'])) {
                        $text_marzban .= "\n\n⏳ منقضی‌شده: {$s['expired']}";
                    }
                    if (isset($s['disabled'])) {
                        $text_marzban .= "\n\n⛔ غیرفعال: {$s['disabled']}";
                    }
                    if (isset($s['limited'])) {
                        $text_marzban .= "\n\n📉 محدودشده: {$s['limited']}";
                    }
                    if (isset($s['on_hold'])) {
                        $text_marzban .= "\n\n⏸ معلق: {$s['on_hold']}";
                    }
                }
                if (!empty($guardAdminsStats['status'])) {
                    $a = $guardAdminsStats['stats'];
                    if (isset($a['total'])) {
                        $text_marzban .= "\n\n🛡 کل ادمین‌ها: {$a['total']}";
                    }
                    if (isset($a['online'])) {
                        $text_marzban .= "\n\n🟢 ادمین آنلاین: {$a['online']}";
                    }
                }
            }

            $rx_sales_metrics = rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
            $text_marzban .= "\n\n{$rx_sales_metrics}"
                . "\n\n⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            nm_adminInstantReply($from_id, $text_marzban, $optionGuard, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "x-ui_single") {
        if (xui_panel_uses_token($marzban_list_get)) {
            $xuiTokenCheck = xui_api_token_request($marzban_list_get, 'GET', '/panel/api/inbounds/list', null, 6, 'panel_menu_check');
            $xuiTokenOk = (($xuiTokenCheck['status'] ?? 0) >= 200 && ($xuiTokenCheck['status'] ?? 0) < 300 && empty($xuiTokenCheck['error']));
            if ($xuiTokenOk) {
                $xuiPanelInfoText = $textbotlang['Admin']['managepanel']['connectx-ui'];
                $xuiStatus = xui_server_status($marzban_list_get);
                if (!empty($xuiStatus['status'])) {
                    $xuiData = $xuiStatus['data'];
                    $xuiCpu = isset($xuiData['cpu']) ? round(floatval($xuiData['cpu']), 1) : null;
                    $xuiMemCurrent = $xuiData['mem']['current'] ?? null;
                    $xuiMemTotal = $xuiData['mem']['total'] ?? null;
                    $xuiDiskCurrent = $xuiData['disk']['current'] ?? null;
                    $xuiDiskTotal = $xuiData['disk']['total'] ?? null;
                    $xuiUptimeDays = isset($xuiData['uptime']) ? round(intval($xuiData['uptime']) / 86400, 1) : null;
                    $xuiXrayState = $xuiData['xray']['state'] ?? null;
                    $xuiXrayVersion = $xuiData['xray']['version'] ?? null;
                    $xuiPanelVersion = $xuiData['panelVersion'] ?? null;
                    $xuiPanelInfoText .= "\n\n📊 وضعیت سرور";
                    if ($xuiPanelVersion !== null && $xuiPanelVersion !== '') {
                        $xuiPanelInfoText .= "\n\n🧩 نسخه پنل: {$xuiPanelVersion}";
                    }
                    if ($xuiCpu !== null) {
                        $xuiPanelInfoText .= "\n\n🖥 پردازنده: {$xuiCpu}%";
                    }
                    if ($xuiMemCurrent !== null && $xuiMemTotal !== null && $xuiMemTotal > 0) {
                        $xuiPanelInfoText .= "\n\n🧠 حافظه: " . round($xuiMemCurrent / 1073741824, 2) . " از " . round($xuiMemTotal / 1073741824, 2) . " گیگابایت";
                    }
                    if ($xuiDiskCurrent !== null && $xuiDiskTotal !== null && $xuiDiskTotal > 0) {
                        $xuiPanelInfoText .= "\n\n💾 فضای دیسک: " . round($xuiDiskCurrent / 1073741824, 2) . " از " . round($xuiDiskTotal / 1073741824, 2) . " گیگابایت";
                    }
                    if ($xuiUptimeDays !== null) {
                        $xuiPanelInfoText .= "\n\n⏱ مدت روشن‌بودن: {$xuiUptimeDays} روز";
                    }
                    if ($xuiXrayState !== null) {
                        $xuiXrayLabel = $xuiXrayState === 'running' ? 'فعال ✅' : 'متوقف ❌';
                        if ($xuiXrayVersion !== null && $xuiXrayVersion !== '') {
                            $xuiXrayLabel .= " (نسخه {$xuiXrayVersion})";
                        }
                        $xuiPanelInfoText .= "\n\n⚙️ وضعیت Xray: {$xuiXrayLabel}";
                    }
                }
                $xuiSummary = xui_clients_summary($marzban_list_get);
                if (!empty($xuiSummary['status'])) {
                    $xuiOnlines = xui_clients_onlines($marzban_list_get);
                    $xuiOnlineCount = !empty($xuiOnlines['status']) ? count($xuiOnlines['emails']) : null;
                    $xuiPanelInfoText .= "\n\n‏──────────────\n👥 وضعیت کاربران پنل\n‏──────────────";
                    if ($xuiOnlineCount !== null) {
                        $xuiPanelInfoText .= "\n\n🟢 آنلاین: {$xuiOnlineCount}";
                    }
                    $xuiPanelInfoText .= "\n\n👤 کل کاربران: {$xuiSummary['total']}";
                    $xuiPanelInfoText .= "\n\n⏳ منقضی‌شده: {$xuiSummary['expired']}";
                    $xuiPanelInfoText .= "\n\n📉 حجم‌تمام‌شده: {$xuiSummary['depleted']}";
                    $xuiPanelInfoText .= "\n\n⛔ غیرفعال: {$xuiSummary['disabled']}";
                }
                $rx_sales_metrics = rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                $xuiPanelInfoText .= "\n\n" . $rx_sales_metrics;
                nm_adminInstantReply($from_id, $xuiPanelInfoText, $optionX_ui_single, 'HTML');
            } else {
                $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
                if (!empty($xuiTokenCheck['error'])) {
                    $text_marzban .= PHP_EOL . "علت خطا: {$xuiTokenCheck['error']}";
                }
                nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
            }
        } else {
            $x_ui_check_connect = login($marzban_list_get['code_panel'], false);
            if ($x_ui_check_connect['success']) {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionX_ui_single, 'HTML');
            } elseif (!empty($x_ui_check_connect['msg']) && $x_ui_check_connect['msg'] == "Invalid username or password.") {
                $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
                nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
            } else {
                $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
                if (!empty($x_ui_check_connect['errror'])) {
                    $text_marzban .= PHP_EOL . "علت خطا: {$x_ui_check_connect['errror']}";
                }
                nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
            }
        }
    } elseif ($marzban_list_get['type'] == "Manualsale") {
        $rx_sales_metrics = rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
        $text_manualsale = "یک گزینه را انتخاب نمایید

{$rx_sales_metrics}";
        nm_adminInstantReply($from_id, $text_manualsale, $optionManualsale, 'HTML');
    } elseif ($marzban_list_get['type'] == "WGDashboard") {
        $rx_sales_metrics = rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
        $text_wg = "آمار پنل شما👇:

{$rx_sales_metrics}

⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
        nm_adminInstantReply($from_id, $text_wg, $optionwg, 'HTML');
    } elseif ($marzban_list_get['type'] == "remnawave") {
        require_once __DIR__ . '/../../../RemnawaveManager.php';
        $mgr = new RemnawaveManager($marzban_list_get);
        $testRes = $mgr->testConnection($pdo);
        if (!empty($testRes['ok'])) {
            $stats = remnawave_system_stats($marzban_list_get['name_panel']);
            $rx_sales_metrics = rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
            $remnaVersion = remnawave_panel_version($marzban_list_get['name_panel']);
            $versionLine = $remnaVersion !== null ? "\n📡 نسخه پنل رمن‌ویو: {$remnaVersion}\n" : '';
            $statsUnavailableNote = '';
            if (!empty($stats['ok'])) {
                $totalUsers = $stats['total_users'];
                $activeUsers = $stats['active_users'];
                $serverStatusBlock = "
‏──────────────
📊 «وضعیت سرور»
‏──────────────

🧮 هسته‌های پردازنده: {$stats['cpu_cores']}

🧠 حافظه: " . remnawave_format_bytes($stats['mem_used']) . " از " . remnawave_format_bytes($stats['mem_total']) . "

🌐 ترافیک کل مصرف‌شده نودها: " . ($stats['total_traffic'] > 0 ? remnawave_format_bytes($stats['total_traffic']) : 'نامشخص') . "

🕐 مدت روشن‌بودن: " . remnawave_format_uptime($stats['uptime_seconds']) . "

‏──────────────
👥 «وضعیت کاربران پنل»
‏──────────────

🟢 آنلاین: {$stats['online_now']}

👤 کل کاربران: {$stats['total_users']}

✅ فعال: {$stats['active_users']}

⏳ منقضی‌شده: {$stats['expired_users']}

📉 محدودشده: {$stats['limited_users']}

⛔️ غیرفعال: {$stats['disabled_users']}
";
            } else {
                $remnaSub = remnawave_user_data_count($marzban_list_get['name_panel']);
                $totalUsers = "نامشخص (تخمین لوکال: {$remnaSub})";
                $activeUsers = "نامشخص (تخمین لوکال: {$remnaSub})";
                $statsUnavailableNote = "\n⚠️ دریافت آمار زنده از پنل ناموفق بود؛ عدد بالا فقط برآورد از دیتابیس محلی است.\n";
                $serverStatusBlock = '';
            }
            $text_remnawave = "
آمار پنل شما 👇 :

🖥 وضعیت اتصال پنل رمن‌ویو: ✅ پنل متصل است
{$versionLine}{$statsUnavailableNote}
👥 تعداد کل کاربران: $totalUsers

👤 تعداد کاربران فعال: $activeUsers
{$serverStatusBlock}
{$rx_sales_metrics}";
            nm_adminInstantReply($from_id, $text_remnawave, $option_remnawave, 'HTML');
        } else {
            nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل رمن‌ویو: ❌ اتصال ناموفق
" . ($testRes['message'] ?? '') . "

لطفاً آدرس پنل یا توکن API را بررسی کنید.", $option_remnawave, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "rebecca") {
        $rebeccaApiKey = trim((string) ($marzban_list_get['api_key'] ?? ''));
        if ($rebeccaApiKey === '') {
            nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل Rebecca: ❌ کلید API تنظیم نشده است", $optionRebecca, 'HTML');
        } else {
            $rebeccaTestRes = rebeccaTestConnection($marzban_list_get['url_panel'] ?? null, $rebeccaApiKey);
            if ($rebeccaTestRes['status'] !== false) {
                $rebeccaStats = rebeccaSystemStats($marzban_list_get['name_panel']);
                $rebeccaStatsData = ($rebeccaStats['status'] !== false && is_array($rebeccaStats['data'])) ? $rebeccaStats['data'] : [];
                $rebeccaVersion = $rebeccaStatsData['version'] ?? 'نامشخص';
                $rebeccaTotalUsers = $rebeccaStatsData['total_user'] ?? 'نامشخص';
                $rebeccaOnlineUsers = $rebeccaStatsData['online_users'] ?? 'نامشخص';
                $rebeccaActiveUsers = $rebeccaStatsData['users_active'] ?? 'نامشخص';
                $rebeccaExpiredUsers = $rebeccaStatsData['users_expired'] ?? 'نامشخص';
                $rebeccaLimitedUsers = $rebeccaStatsData['users_limited'] ?? 'نامشخص';
                $rebeccaDisabledUsers = $rebeccaStatsData['users_disabled'] ?? 'نامشخص';
                $rebeccaCpuUsage = isset($rebeccaStatsData['cpu_usage']) ? round($rebeccaStatsData['cpu_usage'], 1) : 'نامشخص';
                $rebeccaMemUsed = isset($rebeccaStatsData['memory']['current']) ? formatBytes($rebeccaStatsData['memory']['current']) : 'نامشخص';
                $rebeccaMemTotal = isset($rebeccaStatsData['memory']['total']) ? formatBytes($rebeccaStatsData['memory']['total']) : 'نامشخص';
                $rebeccaDiskUsed = isset($rebeccaStatsData['disk']['current']) ? formatBytes($rebeccaStatsData['disk']['current']) : 'نامشخص';
                $rebeccaDiskTotal = isset($rebeccaStatsData['disk']['total']) ? formatBytes($rebeccaStatsData['disk']['total']) : 'نامشخص';
                $rebeccaUptimeDays = isset($rebeccaStatsData['uptime_seconds']) ? floor($rebeccaStatsData['uptime_seconds'] / 86400) : 'نامشخص';
                $rebeccaXrayStatus = !empty($rebeccaStatsData['xray_running']) ? '✅ فعال' : '❌ غیرفعال';
                $rebeccaBandwidth = (isset($rebeccaStatsData['incoming_bandwidth']) && isset($rebeccaStatsData['outgoing_bandwidth']))
                    ? formatBytes($rebeccaStatsData['incoming_bandwidth'] + $rebeccaStatsData['outgoing_bandwidth'])
                    : 'نامشخص';
                $rx_sales_metrics = rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                $rebeccaServiceIdDisplay = trim((string) ($marzban_list_get['rebecca_service_id'] ?? ''));
                if ($rebeccaServiceIdDisplay === '' || $rebeccaServiceIdDisplay === 'auto') {
                    $rebeccaServiceLine = 'خودکار';
                } else {
                    $rebeccaServiceLine = $rebeccaServiceIdDisplay;
                    if (function_exists('rebeccaGetServices') && function_exists('rebeccaServiceLabel')) {
                        $rebeccaServicesForLabel = rebeccaGetServices($marzban_list_get['name_panel']);
                        if (($rebeccaServicesForLabel['status'] ?? false) === true && is_array($rebeccaServicesForLabel['services'] ?? null)) {
                            foreach ($rebeccaServicesForLabel['services'] as $rebeccaServiceRow) {
                                $rebeccaRowId = (string) ($rebeccaServiceRow['id'] ?? ($rebeccaServiceRow['service_id'] ?? ''));
                                if ($rebeccaRowId === $rebeccaServiceIdDisplay) {
                                    $rebeccaServiceLine = rebeccaServiceLabel($rebeccaServiceRow);
                                    break;
                                }
                            }
                        }
                    }
                }
                $text_rebecca = "
پنل متصل است ✅

📡 نسخه پنل Rebecca: {$rebeccaVersion}

‏──────────────
📊 «وضعیت سرور»
‏──────────────

🖥 پردازنده: {$rebeccaCpuUsage}%

🧠 حافظه: {$rebeccaMemUsed} از {$rebeccaMemTotal}

💾 فضای دیسک: {$rebeccaDiskUsed} از {$rebeccaDiskTotal}

⏱ مدت روشن‌بودن: {$rebeccaUptimeDays} روز

⚙️ وضعیت Xray: {$rebeccaXrayStatus}

🌐 ترافیک کل مصرف شده ( آپلود / دانلود ): {$rebeccaBandwidth}

‏──────────────
👥 «وضعیت کاربران پنل»
‏──────────────

🟢 آنلاین: {$rebeccaOnlineUsers}

👤 کل کاربران: {$rebeccaTotalUsers}

✅ فعال: {$rebeccaActiveUsers}

⏳ منقضی‌شده: {$rebeccaExpiredUsers}

📈 حجم‌تمام‌شده: {$rebeccaLimitedUsers}

⛔ غیرفعال: {$rebeccaDisabledUsers}

⚙️ سرویس پیش‌فرض: {$rebeccaServiceLine}

{$rx_sales_metrics}";
                nm_adminInstantReply($from_id, $text_rebecca, $optionRebecca, 'HTML');
            } else {
                nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل Rebecca: ❌ اتصال ناموفق
" . ($rebeccaTestRes['msg'] ?? '') . "

لطفاً آدرس پنل یا کلید API را بررسی کنید.", $optionRebecca, 'HTML');
            }
        }
    } elseif ($marzban_list_get['type'] == "pasarguard") {
        $pasarguardAuthModeStatus = pasarguardAuthMode($marzban_list_get);
        if ($pasarguardAuthModeStatus === 'password') {
            $pasarguardUsernameStatus = trim((string) ($marzban_list_get['username_panel'] ?? ''));
            $pasarguardPasswordStatus = trim((string) ($marzban_list_get['password_panel'] ?? ''));
            $pasarguardCredsMissing = $pasarguardUsernameStatus === '' || $pasarguardPasswordStatus === '';
            $pasarguardTestRes = $pasarguardCredsMissing
                ? ['status' => false, 'msg' => 'نام کاربری یا رمز عبور تنظیم نشده است']
                : pasarguardTestConnectionUserPass($marzban_list_get['url_panel'] ?? null, $pasarguardUsernameStatus, $pasarguardPasswordStatus);
        } else {
            $pasarguardApiKey = trim((string) ($marzban_list_get['api_key'] ?? ''));
            $pasarguardCredsMissing = $pasarguardApiKey === '';
            $pasarguardTestRes = $pasarguardCredsMissing
                ? ['status' => false, 'msg' => 'کلید API تنظیم نشده است']
                : pasarguardTestConnection($marzban_list_get['url_panel'] ?? null, $pasarguardApiKey);
        }
        if ($pasarguardCredsMissing) {
            $missingLabel = $pasarguardAuthModeStatus === 'password' ? 'نام کاربری یا رمز عبور تنظیم نشده است' : 'کلید API تنظیم نشده است';
            nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل PasarGuard: ❌ {$missingLabel}", $optionPasarGuard, 'HTML');
        } else {
            if ($pasarguardTestRes['status'] !== false) {
                $pasarguardStats = pasarguardGetSystemStats($marzban_list_get['name_panel']);
                $pasarguardStatsData = !empty($pasarguardStats['body']) ? json_decode($pasarguardStats['body'], true) : [];
                if (!is_array($pasarguardStatsData)) {
                    $pasarguardStatsData = [];
                }
                $pasarguardVersion = $pasarguardStatsData['version'] ?? 'نامشخص';
                $pasarguardTotalUsers = $pasarguardStatsData['total_user'] ?? 'نامشخص';
                $pasarguardOnlineUsers = $pasarguardStatsData['online_users'] ?? 'نامشخص';
                $pasarguardActiveUsers = $pasarguardStatsData['active_users'] ?? 'نامشخص';
                $pasarguardExpiredUsers = $pasarguardStatsData['expired_users'] ?? 'نامشخص';
                $pasarguardLimitedUsers = $pasarguardStatsData['limited_users'] ?? 'نامشخص';
                $pasarguardDisabledUsers = $pasarguardStatsData['disabled_users'] ?? 'نامشخص';
                $pasarguardCpuUsage = isset($pasarguardStatsData['cpu_usage']) ? round($pasarguardStatsData['cpu_usage'], 1) : 'نامشخص';
                $pasarguardMemUsed = isset($pasarguardStatsData['mem_used']) ? formatBytes($pasarguardStatsData['mem_used']) : 'نامشخص';
                $pasarguardMemTotal = isset($pasarguardStatsData['mem_total']) ? formatBytes($pasarguardStatsData['mem_total']) : 'نامشخص';
                $pasarguardDiskUsed = isset($pasarguardStatsData['disk_used']) ? formatBytes($pasarguardStatsData['disk_used']) : 'نامشخص';
                $pasarguardDiskTotal = isset($pasarguardStatsData['disk_total']) ? formatBytes($pasarguardStatsData['disk_total']) : 'نامشخص';
                $pasarguardUptime = isset($pasarguardStatsData['uptime_seconds']) ? pasarguardFormatUptime($pasarguardStatsData['uptime_seconds']) : 'نامشخص';
                $pasarguardBandwidth = (isset($pasarguardStatsData['incoming_bandwidth']) && isset($pasarguardStatsData['outgoing_bandwidth']))
                    ? formatBytes($pasarguardStatsData['incoming_bandwidth'] + $pasarguardStatsData['outgoing_bandwidth'])
                    : 'نامشخص';
                $rx_sales_metrics = rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                $text_pasarguard = "
پنل متصل است ✅

📡 نسخه پنل PasarGuard: {$pasarguardVersion}

‏──────────────
📊 «وضعیت سرور»
‏──────────────

🖥 پردازنده: {$pasarguardCpuUsage}%

🧠 حافظه: {$pasarguardMemUsed} از {$pasarguardMemTotal}

💾 فضای دیسک: {$pasarguardDiskUsed} از {$pasarguardDiskTotal}

⏱ مدت روشن‌بودن: {$pasarguardUptime}

🌐 ترافیک کل مصرف شده ( آپلود / دانلود ): {$pasarguardBandwidth}

‏──────────────
👥 «وضعیت کاربران پنل»
‏──────────────

🟢 آنلاین: {$pasarguardOnlineUsers}

👤 کل کاربران: {$pasarguardTotalUsers}

✅ فعال: {$pasarguardActiveUsers}

⏳ منقضی‌شده: {$pasarguardExpiredUsers}

📈 محدودشده: {$pasarguardLimitedUsers}

⛔ غیرفعال: {$pasarguardDisabledUsers}

{$rx_sales_metrics}";
                nm_adminInstantReply($from_id, $text_pasarguard, $optionPasarGuard, 'HTML');
            } else {
                $pasarguardFailHint = $pasarguardAuthModeStatus === 'password'
                    ? 'لطفاً آدرس پنل، نام کاربری یا رمز عبور را بررسی کنید.'
                    : 'لطفاً آدرس پنل یا کلید API را بررسی کنید.';
                nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل PasarGuard: ❌ اتصال ناموفق
" . ($pasarguardTestRes['msg'] ?? '') . "

{$pasarguardFailHint}", $optionPasarGuard, 'HTML');
            }
        }
    } else {
        nm_adminInstantReply($from_id, "یک گزینه را انتخاب نمایید", $optionMarzban, 'HTML');
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('PanelMenu', $from_id);
}
