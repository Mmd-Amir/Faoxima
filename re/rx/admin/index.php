<?php
require_once dirname(__DIR__, 2) . '/_error_log.php';
if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', dirname(__DIR__, 3));
}
@chdir(REFACTORED_LEGACY_ROOT);

$__rx_section = rx_compile_section(__DIR__);
$__rx_code = $__rx_section['body'] !== null
    ? $__rx_section['body']
    : ($__rx_section['path'] !== null ? (string) @file_get_contents($__rx_section['path']) : '');

if (isset($datain) && (string)$datain === 'backmenu' && (!isset($text) || (string)$text === '')) {
    $text = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';
}
if (isset($datain) && in_array((string)$datain, ['backadmin', 'admin'], true) && (!isset($text) || (string)$text === '')) {
    $text = $textbotlang['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
}

$rx_nav_is_backmenu = false;

if (
    isset($from_id)
    && isset($admin_ids)
    && is_array($admin_ids)
    && in_array((string) $from_id, array_map('strval', $admin_ids), true)
    && isset($text)
    && is_string($text)
    && $text !== ''
) {
    if (empty($callback_query_id) && empty($datain) && function_exists('rx_normalizeAdminButtonText')) {
        $text = rx_normalizeAdminButtonText($text);
    }
    $rx_back_admin_canonical = $textbotlang['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
    $rx_back_menu_canonical  = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';

    $rx_text_trim = trim((string) $text);


    $rx_emoji_pattern = '/[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2500}-\x{25FF}\x{2900}-\x{2BFF}\x{1F100}-\x{1F1FF}\x{1FA00}-\x{1FAFF}\x{FE0E}\x{FE0F}\x{200D}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200C}]/u';
    $rx_text_stripped = preg_replace($rx_emoji_pattern, '', $rx_text_trim);
    $rx_text_stripped = is_string($rx_text_stripped) ? trim($rx_text_stripped) : '';

    $rx_is_back_admin = ($rx_text_trim === $rx_back_admin_canonical);
    $rx_is_back_menu  = ($rx_text_trim === $rx_back_menu_canonical);


    if (!$rx_is_back_admin && !$rx_is_back_menu && $rx_text_stripped !== '') {
        $rx_back_admin_targets = [
            'بازگشت به منوی مدیریت',
            'بازگشت به منوی اصلی',
            'بازگشت به منو اصلی',
            'بازگشت به خانه',
            'بازگشت به ادمین',
            'بازگشت به پنل مدیریت',
            'بازگشت به پنل ادمین',
            'منوی مدیریت',
            'منوی اصلی',
            'خانه',
        ];
        $rx_back_menu_targets = [
            'بازگشت به منوی قبل',
            'بازگشت به منو قبل',
            'بازگشت به قبل',
            'منوی قبل',
            'بازگشت',
        ];

        if (in_array($rx_text_stripped, $rx_back_admin_targets, true)) {
            $rx_is_back_admin = true;
        } elseif (in_array($rx_text_stripped, $rx_back_menu_targets, true)) {
            $rx_is_back_menu = true;
        }
    }

    if ($rx_is_back_admin || $rx_is_back_menu) {
        $text = $rx_is_back_admin ? $rx_back_admin_canonical : $rx_back_menu_canonical;
    }

    $rx_nav_is_backmenu = $rx_is_back_menu;

    unset(
        $rx_back_admin_canonical, $rx_back_menu_canonical,
        $rx_text_trim, $rx_text_stripped, $rx_emoji_pattern,
        $rx_is_back_admin, $rx_is_back_menu,
        $rx_back_admin_targets, $rx_back_menu_targets
    );
}


if (
    isset($from_id)
    && isset($admin_ids)
    && is_array($admin_ids)
    && in_array((string) $from_id, array_map('strval', $admin_ids), true)
    && isset($text)
    && is_string($text)
    && $text !== ''
    && isset($user)
    && is_array($user)
    && isset($user['step'])
    && (string) $user['step'] !== ''
    && (string) $user['step'] !== 'home'
    && empty($callback_query_id)
) {
    $rx_nav_targets = [];
    if (isset($textbotlang['Admin']['btnkeyboardadmin']) && is_array($textbotlang['Admin']['btnkeyboardadmin'])) {
        foreach (['managementpanel', 'addpanel', 'managruser'] as $rx_nav_key) {
            if (isset($textbotlang['Admin']['btnkeyboardadmin'][$rx_nav_key])
                && is_string($textbotlang['Admin']['btnkeyboardadmin'][$rx_nav_key])) {
                $rx_nav_targets[] = (string) $textbotlang['Admin']['btnkeyboardadmin'][$rx_nav_key];
            }
        }
    }
    if (isset($textbotlang['Admin']['Status']['btn']) && is_string($textbotlang['Admin']['Status']['btn'])) {
        $rx_nav_targets[] = (string) $textbotlang['Admin']['Status']['btn'];
    }
    if (isset($textbotlang['Admin']['textpaneladmin']) && is_string($textbotlang['Admin']['textpaneladmin'])) {
        $rx_nav_targets[] = (string) $textbotlang['Admin']['textpaneladmin'];
    }
    $rx_nav_targets[] = 'panel';
    $rx_nav_targets[] = '/panel';
    foreach ([
        '⏳ قیمت سریع زمان',
        '🔋 قیمت سریع حجم',
        '🏬 تنظیمات فروشگاه',
        '💎 مالی و گزارشات',
        '🤙 بخش پشتیبانی',
        '📚 بخش آموزش',
        '🛠 قابلیت های پنل',
        '⚙️ تنظیمات فنی و ربات',
        '📁 مدیریت پنل‌ها و سرورها',
        '📢 کانال و اطلاع‌رسانی',
        '👥 مدیریت کاربران',
        '💵 رسید های تایید نشده',
    ] as $rx_hardcoded_nav) {
        $rx_nav_targets[] = $rx_hardcoded_nav;
    }

    if (is_string($__rx_code) && $__rx_code !== ''
        && preg_match_all('/elseif\s*\(\s*\$text\s*==\s*"([^"]+)"\s*&&\s*\$adminrulecheck\[\'rule\'\]\s*==\s*"administrator"\s*\)/', $__rx_code, $rx_entry_matches)
        && !empty($rx_entry_matches[1])) {
        foreach ($rx_entry_matches[1] as $rx_entry_label) {
            $rx_nav_targets[] = (string) $rx_entry_label;
        }
    }

    // Enumerate every label from the admin keyboards that were actually rendered
    // this request, so pressing ANY menu button (reply/non-glassy) while mid-step
    // resets the step instead of the press being swallowed as step input. This is
    // far more reliable than the regex above (which only catches handlers ending
    // in `&& administrator`, missing many sub-menu reply buttons).
    if (function_exists('rx_collectKeyboardLabels')) {
        $rx_kb_vars = [
            'keyboardadmin', 'setting_panel', 'shopkeyboard', 'keyboardhelpadmin',
            'Feature_status', 'channelkeyboard', 'keyboard_Category_manage', 'keyboard_shop_manage',
            'CartManage', 'trnado', 'tonpay', 'cubepay', 'blupal', 'atlaspay', 'tetrapay', 'keyboardzarinpal',
            'NowPaymentsManage', 'nowpayment_setting_keyboard', 'tronnowpayments', 'Startelegram',
            'iranpaykeyboard', 'supportcenter', 'departemanslist', 'backadmin',
            'adminPanelsMenu', 'adminChannelMenu', 'adminUsersMenu',
        ];
        $rx_kb_labels = [];
        foreach ($rx_kb_vars as $rx_kb_var) {
            $rx_kb_json = null;
            if (isset($$rx_kb_var) && is_string($$rx_kb_var)) {
                $rx_kb_json = $$rx_kb_var;
            } elseif (isset($GLOBALS[$rx_kb_var]) && is_string($GLOBALS[$rx_kb_var])) {
                $rx_kb_json = $GLOBALS[$rx_kb_var];
            }
            if ($rx_kb_json !== null && $rx_kb_json !== '') {
                rx_collectKeyboardLabels($rx_kb_json, $rx_kb_labels);
            }
        }
        foreach ($rx_kb_labels as $rx_kb_label) {
            $rx_nav_targets[] = $rx_kb_label;
            // Upstream strips the reply-style emoji prefix from $text before this
            // guard runs, so add the stripped form too to guarantee a match.
            if (function_exists('stripReplyStyleEmoji')) {
                $rx_stripped_label = stripReplyStyleEmoji($rx_kb_label);
                if ($rx_stripped_label !== '' && $rx_stripped_label !== $rx_kb_label) {
                    $rx_nav_targets[] = $rx_stripped_label;
                }
            }
        }
        unset($rx_kb_vars, $rx_kb_var, $rx_kb_json, $rx_kb_labels, $rx_kb_label, $rx_stripped_label);
    }

    if (isset($datatextbot) && is_array($datatextbot)) {
        foreach ($datatextbot as $rx_dtb_value) {
            if (!is_string($rx_dtb_value) || $rx_dtb_value === '') {
                continue;
            }
            $rx_nav_targets[] = $rx_dtb_value;
            if (function_exists('stripReplyStyleEmoji')) {
                $rx_dtb_stripped = stripReplyStyleEmoji($rx_dtb_value);
                if ($rx_dtb_stripped !== '' && $rx_dtb_stripped !== $rx_dtb_value) {
                    $rx_nav_targets[] = $rx_dtb_stripped;
                }
            }
        }
        unset($rx_dtb_value, $rx_dtb_stripped);
    }

    $rx_nav_targets = array_values(array_unique($rx_nav_targets));

    $rx_nav_is_dynamic_selection = false;
    if (in_array((string) $user['step'], ['channel', 'channel_manage'], true)
        && function_exists('rx_find_channel_from_input')
        && isset($pdo)
        && ($pdo instanceof PDO)) {
        $rx_nav_dynamic_match = rx_find_channel_from_input((string) $text, isset($datain) ? (string) $datain : '', $pdo);
        if (is_array($rx_nav_dynamic_match) && !empty($rx_nav_dynamic_match['id'])) {
            $rx_nav_is_dynamic_selection = true;
        }
        unset($rx_nav_dynamic_match);
    }

    if (!empty($rx_nav_targets) && in_array((string) $text, $rx_nav_targets, true) && empty($rx_nav_is_backmenu) && !$rx_nav_is_dynamic_selection) {
        if (function_exists('step')) {
            try {
                step('home', $from_id);
            } catch (Throwable $rx_step_err) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('NAV_GUARD_STEP_RESET_FAILED', $rx_step_err->getMessage(), [
                        'from_id' => $from_id,
                        'text'    => $text,
                    ]);
                }
            }
        }
        $user['step'] = 'home';
    }

    unset($rx_nav_targets, $rx_nav_key, $rx_hardcoded_nav, $rx_step_err, $rx_entry_matches, $rx_entry_label, $rx_nav_is_dynamic_selection);
}

unset($rx_nav_is_backmenu);

try {
    if ($__rx_section['path'] !== null) {
        require $__rx_section['path'];
    } else {
        eval((string) $__rx_section['body']);
    }
} catch (Throwable $__rx_throwable) {
    $__rx_ctx = [
        'module' => basename(__DIR__),
        'class'  => get_class($__rx_throwable),
        'file'   => $__rx_throwable->getFile(),
        'line'   => $__rx_throwable->getLine(),
    ];
    if ($__rx_section['path'] !== null && $__rx_throwable->getFile() === $__rx_section['path']) {
        $__rx_src = rx_map_compiled_line(__DIR__, $__rx_throwable->getLine());
        if (is_array($__rx_src)) {
            $__rx_ctx['part'] = $__rx_src['file'];
            $__rx_ctx['part_line'] = $__rx_src['line'];
        }
    }
    rx_log_event('RX_EVAL_THROWABLE', $__rx_throwable->getMessage(), $__rx_ctx);
    throw $__rx_throwable;
}
unset($__rx_section, $__rx_throwable, $__rx_ctx, $__rx_src, $__rx_code);
