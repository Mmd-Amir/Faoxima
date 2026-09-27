<?php

if (function_exists('fx_admin_button_label') && $text == fx_admin_button_label() && $adminrulecheck['rule'] == "administrator") {
    $rxFxPanelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    $rxFxPanel = $rxFxPanelName !== '' ? select("marzban_panel", "*", "name_panel", $rxFxPanelName, "select", ['cache' => false]) : false;
    if (!is_array($rxFxPanel) || empty($rxFxPanel['code_panel'])) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
        return;
    }
    step('PanelMenu', $from_id);
    update("user", "Processing_value_tow", "", "id", $from_id);
    $rxFxMenu = fx_admin_menu_render($rxFxPanel);
    nm_adminInstantReply($from_id, $rxFxMenu['text'], $rxFxMenu['keyboard'], 'HTML');
} elseif (is_string($datain) && strpos($datain, 'fxp_') === 0 && function_exists('fx_admin_menu_render') && $adminrulecheck['rule'] == "administrator") {
    if (!preg_match('/^fxp_(menu|back|on|onok|off|rebase|base|markup|round|stale|jump|tp|tcv|tct|tev|tet|tp0|tp1|tcv0|tcv1|tct0|tct1|tev0|tev1|tet0|tet1|manual|unmanual|test)_([A-Za-z0-9_-]{1,100})$/', $datain, $rxFxCb)) {
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "❌ درخواست نامعتبر است.", 'show_alert' => false]);
        }
        return;
    }
    $rxFxPanel = fx_admin_load_panel($rxFxCb[2]);
    $rxFxCurrentName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    if ($rxFxPanel === null || (string)$rxFxPanel['name_panel'] !== (string)$rxFxCurrentName) {
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "⚠️ این منو منقضی شده است؛ دوباره از مدیریت پنل وارد شوید.", 'show_alert' => true]);
        }
        return;
    }
    $rxFxAction = $rxFxCb[1];
    $rxFxCode = (string)$rxFxPanel['code_panel'];
    $rxFxConfig = fx_panel_config($rxFxPanel);
    $rxFxEffective = fx_rate_get_effective($rxFxConfig['pair'], $rxFxConfig);
    $rxFxRender = static function (string $prefix, string $code) use ($from_id, $backadmin, $textbotlang) {
        $panel = fx_admin_load_panel($code);
        if ($panel === null) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
            return;
        }
        $menu = fx_admin_menu_render($panel);
        nm_adminInstantReply($from_id, $prefix . $menu['text'], $menu['keyboard'], 'HTML');
    };
    if ($rxFxAction === 'back') {
        step('PanelMenu', $from_id);
        update("user", "Processing_value_tow", "", "id", $from_id);
        outtypepanel($rxFxPanel['type'], $textbotlang['Admin']['Back-menu']);
        return;
    }
    if ($rxFxAction === 'menu') {
        step('PanelMenu', $from_id);
        update("user", "Processing_value_tow", "", "id", $from_id);
        $rxFxRender('', $rxFxCode);
        return;
    }
    if ($rxFxAction === 'on') {
        step('PanelMenu', $from_id);
        $rxFxEnable = fx_admin_enable_render($rxFxPanel);
        nm_adminInstantReply($from_id, $rxFxEnable['text'], $rxFxEnable['keyboard'], 'HTML');
        return;
    }
    if ($rxFxAction === 'onok' || $rxFxAction === 'rebase') {
        step('PanelMenu', $from_id);
        if (!$rxFxEffective['ok'] || $rxFxEffective['rate'] === null) {
            $rxFxRender("❌ نرخ معتبر USDT/IRT در دسترس نیست؛ تغییری ذخیره نشد.\n\n", $rxFxCode);
            return;
        }
        $rxFxConfig['base_rate'] = $rxFxEffective['rate'];
        if ($rxFxAction === 'onok') {
            $rxFxConfig['enabled'] = '1';
            $rxFxConfig['enabled_at'] = time();
        }
        $rxFxSaved = fx_panel_save_config($rxFxPanel, $rxFxConfig);
        $rxFxPrefix = $rxFxSaved
            ? ($rxFxAction === 'onok'
                ? "✅ قیمت‌گذاری دلاری فعال شد. نرخ پایه: " . fx_format_toman($rxFxEffective['rate']) . "\n\n"
                : "✅ نرخ فعلی (" . fx_format_toman($rxFxEffective['rate']) . ") به‌عنوان نرخ پایه ذخیره شد.\n\n")
            : "❌ ذخیره تنظیمات انجام نشد.\n\n";
        $rxFxRender($rxFxPrefix, $rxFxCode);
        return;
    }
    if ($rxFxAction === 'off') {
        step('PanelMenu', $from_id);
        $rxFxConfig['enabled'] = '0';
        $rxFxSaved = fx_panel_save_config($rxFxPanel, $rxFxConfig);
        $rxFxRender($rxFxSaved ? "✅ قیمت‌گذاری دلاری غیرفعال شد؛ قیمت‌های پایه تومانی اعمال می‌شوند.\n\n" : "❌ ذخیره تنظیمات انجام نشد.\n\n", $rxFxCode);
        return;
    }
    $rxFxToggleMap = [
        'tp'  => 'apply_products',
        'tcv' => 'apply_custom_volume',
        'tct' => 'apply_custom_time',
        'tev' => 'apply_extra_volume',
        'tet' => 'apply_extra_time',
    ];
    if (isset($rxFxToggleMap[$rxFxAction])) {
        step('PanelMenu', $from_id);
        $rxFxRender("🔄 این منو بروزرسانی شد؛ لطفاً دوباره گزینه را انتخاب کنید.\n\n", $rxFxCode);
        return;
    }
    $rxFxToggleBase = substr($rxFxAction, 0, -1);
    $rxFxTargetValue = substr($rxFxAction, -1);
    if (isset($rxFxToggleMap[$rxFxToggleBase]) && ($rxFxTargetValue === '0' || $rxFxTargetValue === '1')) {
        step('PanelMenu', $from_id);
        $rxFxKey = $rxFxToggleMap[$rxFxToggleBase];
        $rxFxConfig[$rxFxKey] = $rxFxTargetValue;
        $rxFxSaved = fx_panel_save_config($rxFxPanel, $rxFxConfig);
        if ($rxFxSaved) {
            $rxFxReloaded = fx_admin_load_panel($rxFxCode);
            $rxFxSaved = $rxFxReloaded !== null && fx_panel_config($rxFxReloaded)[$rxFxKey] === $rxFxTargetValue;
        }
        $rxFxRender($rxFxSaved ? "✅ ذخیره شد.\n\n" : "❌ ذخیره تنظیمات انجام نشد.\n\n", $rxFxCode);
        return;
    }
    if ($rxFxAction === 'unmanual') {
        step('PanelMenu', $from_id);
        $rxFxConfig['manual_rate'] = null;
        $rxFxSaved = fx_panel_save_config($rxFxPanel, $rxFxConfig);
        $rxFxRender($rxFxSaved ? "✅ نرخ اضطراری دستی حذف شد؛ قیمت‌ها دوباره از نرخ ذخیره‌شده API محاسبه می‌شوند.\n\n" : "❌ ذخیره تنظیمات انجام نشد.\n\n", $rxFxCode);
        return;
    }
    $rxFxPrompts = [
        'base'   => "✍️ نرخ پایه USDT/IRT (تومان) را ارسال کنید.\n⚠️ قیمت‌های ثبت‌شده محصولات این پنل معادل همین نرخ در نظر گرفته می‌شوند.",
        'markup' => "📈 درصد افزایش (Markup) را ارسال کنید.\nمثال: 5 یا 2.5 — برای حذف، 0 ارسال کنید. (بین -90 تا 1000)",
        'round'  => "🔢 گام گرد کردن قیمت نهایی (تومان) را ارسال کنید.\nمثال: 1000 — قیمت نهایی به مضرب بعدی این عدد گرد می‌شود.",
        'stale'  => "⏱ آستانه قدیمی‌شدن نرخ (دقیقه) را ارسال کنید. (بین 5 تا 10080)",
        'jump'   => "📊 حداکثر درصد جهش مجاز نرخ را ارسال کنید. (بین 1 تا 100)",
        'manual' => "🚨 نرخ اضطراری دستی USDT/IRT (تومان) را ارسال کنید.\nتا زمان حذف دستی، این نرخ به جای نرخ API استفاده می‌شود.",
        'test'   => "🧮 یک قیمت پایه نمونه (تومان) ارسال کنید تا قیمت نهایی محاسبه شود.",
    ];
    if (isset($rxFxPrompts[$rxFxAction])) {
        update("user", "Processing_value_tow", "fxp|" . $rxFxAction . "|" . $rxFxCode, "id", $from_id);
        step('fxp_input', $from_id);
        $rxFxPromptKb = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'fxp_menu_' . $rxFxCode]]]], JSON_UNESCAPED_UNICODE);
        nm_adminInstantReply($from_id, $rxFxPrompts[$rxFxAction], $rxFxPromptKb, 'HTML');
        return;
    }
} elseif ($user['step'] == 'fxp_input' && empty($datain) && isset($update['message']) && function_exists('fx_admin_menu_render') && $adminrulecheck['rule'] == "administrator") {
    $rxFxState = explode('|', (string)($user['Processing_value_tow'] ?? ''));
    $rxFxPanel = (count($rxFxState) === 3 && $rxFxState[0] === 'fxp') ? fx_admin_load_panel($rxFxState[2]) : null;
    $rxFxCurrentName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
    if ($rxFxPanel === null || (string)$rxFxPanel['name_panel'] !== (string)$rxFxCurrentName) {
        step('PanelMenu', $from_id);
        update("user", "Processing_value_tow", "", "id", $from_id);
        nm_adminInstantReply($from_id, "⚠️ این مرحله منقضی شده است؛ دوباره از مدیریت پنل وارد شوید.", $backadmin, 'HTML');
        return;
    }
    $rxFxAction = $rxFxState[1];
    $rxFxCode = (string)$rxFxPanel['code_panel'];
    $rxFxConfig = fx_panel_config($rxFxPanel);
    $rxFxPromptKb = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'fxp_menu_' . $rxFxCode]]]], JSON_UNESCAPED_UNICODE);
    $rxFxValue = fx_parse_admin_number((string)$text, $rxFxAction === 'markup');
    $rxFxRules = [
        'base'   => [0.0001, 1000000000.0, false],
        'markup' => [-90.0, 1000.0, true],
        'round'  => [1.0, 1000000.0, false],
        'stale'  => [5.0, 10080.0, false],
        'jump'   => [1.0, 100.0, true],
        'manual' => [0.0001, 1000000000.0, false],
        'test'   => [0.0001, 100000000000.0, false],
    ];
    if (!isset($rxFxRules[$rxFxAction])) {
        step('PanelMenu', $from_id);
        update("user", "Processing_value_tow", "", "id", $from_id);
        return;
    }
    [$rxFxMin, $rxFxMax, $rxFxAllowFraction] = $rxFxRules[$rxFxAction];
    if ($rxFxValue === null || $rxFxValue < $rxFxMin || $rxFxValue > $rxFxMax || (!$rxFxAllowFraction && in_array($rxFxAction, ['round', 'stale'], true) && floor($rxFxValue) != $rxFxValue)) {
        nm_adminInstantReply($from_id, "❌ مقدار نامعتبر است. یک عدد معتبر در بازه مجاز ارسال کنید.", $rxFxPromptKb, 'HTML');
        return;
    }
    if ($rxFxAction === 'test') {
        $rxFxEffective = fx_rate_get_effective($rxFxConfig['pair'], $rxFxConfig);
        if (!$rxFxEffective['ok']) {
            nm_adminInstantReply($from_id, "❌ نرخ معتبر USDT/IRT در دسترس نیست؛ محاسبه ممکن نیست.", $rxFxPromptKb, 'HTML');
            return;
        }
        $rxFxTestConfig = $rxFxConfig;
        $rxFxTestConfig['enabled'] = '1';
        $rxFxTestConfig['apply_products'] = '1';
        if ($rxFxTestConfig['base_rate'] === null) {
            $rxFxTestConfig['base_rate'] = $rxFxEffective['rate'];
        }
        $rxFxTestPanel = $rxFxPanel;
        $rxFxTestPanel['fx_pricing_config'] = json_encode(fx_sanitize_config($rxFxTestConfig));
        $rxFxTestPrice = fx_adjust_base_toman($rxFxValue, $rxFxTestPanel, 'product');
        $rxFxMultiplier = ($rxFxEffective['rate'] / $rxFxTestConfig['base_rate']) * (1 + $rxFxTestConfig['markup_percent'] / 100);
        $rxFxTestText = "🧮 <b>نتیجه تست محاسبه</b>\n\n"
            . "💰 قیمت پایه: " . fx_format_toman($rxFxValue) . "\n"
            . "💱 نرخ فعلی: " . fx_format_toman($rxFxEffective['rate']) . ($rxFxEffective['manual'] ? ' (دستی)' : '') . "\n"
            . "🏁 نرخ پایه: " . fx_format_toman($rxFxTestConfig['base_rate']) . "\n"
            . "📈 درصد افزایش: " . $rxFxTestConfig['markup_percent'] . "%\n"
            . "✖️ ضریب: " . number_format($rxFxMultiplier, 4) . "\n"
            . "🔢 گام گرد کردن: " . number_format($rxFxTestConfig['round_step']) . "\n\n"
            . "✅ قیمت نهایی: <b>" . fx_format_toman($rxFxTestPrice) . "</b>"
            . ($rxFxConfig['enabled'] === '1' ? '' : "\n\nℹ️ قیمت‌گذاری دلاری این پنل فعلاً غیرفعال است؛ این فقط یک محاسبه نمونه است.");
        step('PanelMenu', $from_id);
        update("user", "Processing_value_tow", "", "id", $from_id);
        nm_adminInstantReply($from_id, $rxFxTestText, $rxFxPromptKb, 'HTML');
        return;
    }
    $rxFxFieldMap = [
        'base'   => 'base_rate',
        'markup' => 'markup_percent',
        'round'  => 'round_step',
        'stale'  => 'stale_after_minutes',
        'jump'   => 'max_jump_percent',
        'manual' => 'manual_rate',
    ];
    $rxFxConfig[$rxFxFieldMap[$rxFxAction]] = $rxFxValue;
    $rxFxSaved = fx_panel_save_config($rxFxPanel, $rxFxConfig);
    step('PanelMenu', $from_id);
    update("user", "Processing_value_tow", "", "id", $from_id);
    $rxFxPanel = fx_admin_load_panel($rxFxCode);
    if ($rxFxPanel === null) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
        return;
    }
    $rxFxMenu = fx_admin_menu_render($rxFxPanel);
    nm_adminInstantReply($from_id, ($rxFxSaved ? "✅ ذخیره شد.\n\n" : "❌ ذخیره تنظیمات انجام نشد.\n\n") . $rxFxMenu['text'], $rxFxMenu['keyboard'], 'HTML');
}
