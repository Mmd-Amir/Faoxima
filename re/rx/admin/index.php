<?php
require_once dirname(__DIR__, 2) . '/_error_log.php';
if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', dirname(__DIR__, 3));
}
@chdir(REFACTORED_LEGACY_ROOT);

$__rx_parts = require __DIR__ . '/manifest.php';
$__rx_code = '';
foreach ($__rx_parts as $__rx_part) {
    $__rx_path = __DIR__ . DIRECTORY_SEPARATOR . $__rx_part;
    if (!is_file($__rx_path)) {
        rx_log_event('RX_MISSING_PART', $__rx_path, ['module' => basename(__DIR__)]);
        throw new RuntimeException('Missing refactored part: ' . $__rx_path);
    }
    $__rx_raw = file_get_contents($__rx_path);
    $__rx_raw = preg_replace('/^<\?php\n/', '', $__rx_raw, 1);
    $__rx_code .= $__rx_raw;
}
unset($__rx_parts, $__rx_part, $__rx_path, $__rx_raw);

if (isset($datain) && (string)$datain === 'backmenu' && (!isset($text) || (string)$text === '')) {
    $text = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';
}
if (isset($datain) && in_array((string)$datain, ['backadmin', 'admin'], true) && (!isset($text) || (string)$text === '')) {
    $text = $textbotlang['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
}


if (
    isset($from_id)
    && isset($admin_ids)
    && is_array($admin_ids)
    && in_array($from_id, $admin_ids, true)
    && isset($text)
    && is_string($text)
    && $text !== ''
) {
    $rx_back_admin_canonical = $textbotlang['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
    $rx_back_menu_canonical  = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';

    $rx_text_trim = trim((string) $text);


    $rx_emoji_pattern = '/^(?:[\s\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2500}-\x{25FF}\x{2900}-\x{2BFF}\x{1F100}-\x{1F1FF}\x{1FA00}-\x{1FAFF}\x{FE0E}\x{FE0F}\x{200D}])+/u';
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
            'پنل مدیریت',
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
    && in_array($from_id, $admin_ids, true)
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
    foreach ([
        '⏳ تنظیم سریع قیمت زمان',
        '🔋 تنظیم سریع قیمت حجم',
        '🏬 تنظیمات فروشگاه',
        '💎 مالی',
        '🤙 بخش پشتیبانی',
        '📚 بخش آموزش',
        '🛠 قابلیت های پنل',
        '⚙️ تنظیمات عمومی',
        '💵 رسید های تایید نشده',
    ] as $rx_hardcoded_nav) {
        $rx_nav_targets[] = $rx_hardcoded_nav;
    }

    if (!empty($rx_nav_targets) && in_array((string) $text, $rx_nav_targets, true)) {
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

    unset($rx_nav_targets, $rx_nav_key, $rx_hardcoded_nav, $rx_step_err);
}

try {
    eval($__rx_code);
} catch (Throwable $__rx_throwable) {
    rx_log_event('RX_EVAL_THROWABLE', $__rx_throwable->getMessage(), [
        'class' => get_class($__rx_throwable),
        'file'  => $__rx_throwable->getFile(),
        'line'  => $__rx_throwable->getLine(),
    ]);
    throw $__rx_throwable;
}
unset($__rx_code);
