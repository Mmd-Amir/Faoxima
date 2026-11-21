<?php

namespace Faoxima\Admin\Telegram\Conversation;

use PDO;

/**
 * Handler stage. Inherited by value: $CartManage, $Feature_status, $NowPaymentsManage, $Startelegram, $abangateway, $adminChannelMenu, $adminExecution, $adminPanelsMenu, $adminUsersMenu, $admin_ids, $atlaspay, $backadmin, $blupal, $callback_query_id, $channelkeyboard, $cubepay, $datain, $from_id, $iranpaykeyboard, $keyboard_Category_manage, $keyboard_shop_manage, $keyboardadmin, $keyboardhelpadmin, $keyboardzarinpal, $nowpayment_setting_keyboard, $pdo, $rialit, $rx_hardcoded_nav, $rx_kb_json, $rx_kb_label, $rx_kb_value, $rx_kb_var, $rx_nav_key, $rx_stripped_label, $setting_panel, $shopkeyboard, $supportcenter, $textbotlang, $tonpay, $trnado, $tronnowpayments, $variza.
 * Inherited or exported by reference: $rx_pem_entry_step, $text, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Navigation
{
    public static function handle(
        $CartManage,
        $Feature_status,
        $NowPaymentsManage,
        $Startelegram,
        $abangateway,
        $adminChannelMenu,
        $adminExecution,
        $adminPanelsMenu,
        $adminUsersMenu,
        $admin_ids,
        $atlaspay,
        $backadmin,
        $blupal,
        $callback_query_id,
        $channelkeyboard,
        $cubepay,
        $datain,
        $from_id,
        $iranpaykeyboard,
        $keyboard_Category_manage,
        $keyboard_shop_manage,
        $keyboardadmin,
        $keyboardhelpadmin,
        $keyboardzarinpal,
        $nowpayment_setting_keyboard,
        $pdo,
        $rialit,
        $rx_hardcoded_nav,
        $rx_kb_json,
        $rx_kb_label,
        $rx_kb_value,
        $rx_kb_var,
        $rx_nav_key,
        &$rx_pem_entry_step,
        $rx_stripped_label,
        $setting_panel,
        $shopkeyboard,
        $supportcenter,
        &$text,
        $textbotlang,
        $tonpay,
        $trnado,
        $tronnowpayments,
        &$user,
        $variza
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        if (isset($datain) && (string)$datain === 'backmenu' && (!isset($text) || (string)$text === '')) {
            $text = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';
        }
        if (isset($datain) && in_array((string)$datain, ['backadmin', 'admin'], true) && (!isset($text) || (string)$text === '')) {
            $text = $textbotlang['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
        }

        $rx_pem_entry_step = (isset($user) && is_array($user) && isset($user['step'])) ? (string) $user['step'] : '';

        $rx_nav_is_backmenu = false;

        if (\Faoxima\Admin\Telegram\Support\NavigationFunctionsHelpers::rxAdminHasNavigationInput($from_id ?? null, $admin_ids ?? null, $text ?? null)) {
            if (empty($callback_query_id) && empty($datain) && method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_normalizeAdminButtonText')) {
                $text = \Faoxima\Telegram\Support\AdminInput::rx_normalizeAdminButtonText($text);
            }
            [$text, $rx_nav_is_backmenu] = \Faoxima\Admin\Telegram\Support\NavigationFunctionsHelpers::rxAdminCanonicalNavigation($text, $textbotlang['Admin'] ?? []);
        }


        if (
            \Faoxima\Admin\Telegram\Support\NavigationFunctionsHelpers::rxAdminHasNavigationInput($from_id ?? null, $admin_ids ?? null, $text ?? null)
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

            if (function_exists('rx_collectKeyboardLabels')) {
                $rx_kb_vars = [
                    'keyboardadmin' => $keyboardadmin ?? null,
                    'setting_panel' => $setting_panel ?? null,
                    'shopkeyboard' => $shopkeyboard ?? null,
                    'keyboardhelpadmin' => $keyboardhelpadmin ?? null,
                    'Feature_status' => $Feature_status ?? null,
                    'channelkeyboard' => $channelkeyboard ?? null,
                    'keyboard_Category_manage' => $keyboard_Category_manage ?? null,
                    'keyboard_shop_manage' => $keyboard_shop_manage ?? null,
                    'CartManage' => $CartManage ?? null,
                    'trnado' => $trnado ?? null,
                    'tonpay' => $tonpay ?? null,
                    'rialit' => $rialit ?? null,
                    'cubepay' => $cubepay ?? null,
                    'blupal' => $blupal ?? null,
                    'variza' => $variza ?? null,
                    'abangateway' => $abangateway ?? null,
                    'atlaspay' => $atlaspay ?? null,
                    'keyboardzarinpal' => $keyboardzarinpal ?? null,
                    'NowPaymentsManage' => $NowPaymentsManage ?? null,
                    'nowpayment_setting_keyboard' => $nowpayment_setting_keyboard ?? null,
                    'tronnowpayments' => $tronnowpayments ?? null,
                    'Startelegram' => $Startelegram ?? null,
                    'iranpaykeyboard' => $iranpaykeyboard ?? null,
                    'supportcenter' => $supportcenter ?? null,
                    'backadmin' => $backadmin ?? null,
                    'adminPanelsMenu' => $adminPanelsMenu ?? null,
                    'adminChannelMenu' => $adminChannelMenu ?? null,
                    'adminUsersMenu' => $adminUsersMenu ?? null,
                ];
                $rx_kb_labels = [];
                foreach ($rx_kb_vars as $rx_kb_var => $rx_kb_value) {
                    $rx_kb_json = null;
                    if (isset($rx_kb_value) && is_string($rx_kb_value)) {
                        $rx_kb_json = $rx_kb_value;
                    } elseif (isset($GLOBALS[$rx_kb_var]) && is_string($GLOBALS[$rx_kb_var])) {
                        $rx_kb_json = $GLOBALS[$rx_kb_var];
                    }
                    if ($rx_kb_json !== null && $rx_kb_json !== '') {
                        rx_collectKeyboardLabels($rx_kb_json, $rx_kb_labels);
                    }
                }
                foreach ($rx_kb_labels as $rx_kb_label) {
                    $rx_nav_targets[] = $rx_kb_label;
                    if (function_exists('stripReplyStyleEmoji')) {
                        $rx_stripped_label = stripReplyStyleEmoji($rx_kb_label);
                        if ($rx_stripped_label !== '' && $rx_stripped_label !== $rx_kb_label) {
                            $rx_nav_targets[] = $rx_stripped_label;
                        }
                    }
                }
                unset($rx_kb_vars, $rx_kb_var, $rx_kb_value, $rx_kb_json, $rx_kb_labels, $rx_kb_label, $rx_stripped_label);
            }

            $rx_nav_targets = array_values(array_unique($rx_nav_targets));

            $rx_nav_is_dynamic_selection = false;
            if (in_array((string) $user['step'], ['channel', 'channel_manage'], true)
                && method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_find_channel_from_input')
                && isset($pdo)
                && ($pdo instanceof PDO)) {
                $rx_nav_dynamic_match = \Faoxima\Admin\Telegram\ChannelNavigation::rx_find_channel_from_input((string) $text, isset($datain) ? (string) $datain : '', $pdo);
                if (is_array($rx_nav_dynamic_match) && !empty($rx_nav_dynamic_match['id'])) {
                    $rx_nav_is_dynamic_selection = true;
                }
                unset($rx_nav_dynamic_match);
            }

            if (!empty($rx_nav_targets) && in_array((string) $text, $rx_nav_targets, true) && empty($rx_nav_is_backmenu) && !$rx_nav_is_dynamic_selection) {
                \Faoxima\Admin\Telegram\Support\NavigationFunctionsHelpers::rxAdminResetStepForNavigation($from_id, $text, $user);
            }

            unset($rx_nav_targets, $rx_nav_key, $rx_hardcoded_nav, $rx_nav_is_dynamic_selection);
        }

        unset($rx_nav_is_backmenu);

        return true;
    }
}
