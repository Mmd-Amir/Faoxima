<?php

namespace Faoxima\Admin\Keyboard;

final class ProviderMenuRenderingHelpers
{
    public static function rx_adminPanelCallbackMapFile(): string {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
        return rtrim($base, '/') . '/rx_admin_panel_callback_map.json';
    }

    public static function rx_makeAdminPanelCallback(string $text): string {

        $short = 'apn:' . $text;
        if (strlen($short) <= 64) return $short;


        $code = 'apnh:' . substr(hash('sha256', $text), 0, 32);
        return rx_callbackMapStore(self::rx_adminPanelCallbackMapFile(), $code, $text) ? $code : 'admin_back';
    }

    public static function rx_resolveAdminPanelCallback(string $callbackData): ?string {
        if (strpos($callbackData, 'apn:') === 0) {
            return substr($callbackData, 4);
        }
        if (strpos($callbackData, 'apnh:') === 0) {
            $map = rx_callbackMapRead(self::rx_adminPanelCallbackMapFile());
            return (is_array($map) && isset($map[$callbackData])) ? (string)$map[$callbackData] : null;
        }
        return null;
    }

    public static function rx_keyboardIsAdminInterface($keyboard): bool {
        $kb = is_array($keyboard) ? $keyboard : (is_string($keyboard) ? json_decode($keyboard, true) : null);
        if (!is_array($kb)) return false;
        $rows = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $rows = $kb['keyboard'];
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $rows = $kb['inline_keyboard'];
        }
        if ($rows === null) return false;

        $adminLabels = [
            '🏠 بازگشت به منوی مدیریت' => true,
            'بازگشت به منوی مدیریت' => true,
            '▶️ بازگشت به منوی قبل' => true,
            'بازگشت به منوی قبل' => true,
        ];
        $lang = isset($GLOBALS['textbotlang']) && is_array($GLOBALS['textbotlang']) ? $GLOBALS['textbotlang'] : null;
        if ($lang !== null && isset($lang['Admin']) && is_array($lang['Admin'])) {
            foreach (['backadmin', 'backmenu'] as $labelKey) {
                if (!empty($lang['Admin'][$labelKey]) && is_string($lang['Admin'][$labelKey])) {
                    $adminLabels[trim($lang['Admin'][$labelKey])] = true;
                }
            }
        }

        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            foreach ($row as $btn) {
                if (!is_array($btn)) continue;
                if (!empty($btn['_rxap']) || !empty($btn['_rxadm'])) return true;
                if (isset($btn['callback_data']) && is_string($btn['callback_data'])) {
                    $cb = $btn['callback_data'];
                    if (strpos($cb, 'apn:') === 0 || strpos($cb, 'apnh:') === 0
                        || $cb === 'adm_hub_main' || $cb === 'adm_backmenu') {
                        return true;
                    }
                }
                if (isset($btn['text']) && is_scalar($btn['text'])) {
                    $label = trim((string)$btn['text']);
                    if ($label !== '' && isset($adminLabels[$label])) return true;
                }
            }
        }
        return false;
    }

    public static function rx_finalizeInlineAdminKb(string $json, bool $rxMarkPlain = true): string {
        $kb = json_decode($json, true);
        if (!is_array($kb)) return $json;

        $rows = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $rows = $kb['keyboard'];
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $rows = $kb['inline_keyboard'];
        }
        if ($rows === null) return $json;

        $hasContactRequest = false;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            foreach ($row as $btn) {
                if (is_array($btn) && (isset($btn['request_contact']) || isset($btn['request_location']) || isset($btn['request_poll']) || isset($btn['request_users']) || isset($btn['request_chat']))) {
                    $hasContactRequest = true;
                    break 2;
                }
            }
        }
        if ($hasContactRequest) {
            return $json;
        }

        $rxAdminKb = $rxMarkPlain;
        if (!$rxAdminKb && method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_keyboardIsAdminInterface')) {
            $rxAdminKb = self::rx_keyboardIsAdminInterface($kb);
        }
        $rxGlassMode = isset($GLOBALS['setting']['inlinebtnmain']) && $GLOBALS['setting']['inlinebtnmain'] === 'oninline';
        if (!$rxGlassMode && !$rxAdminKb && isset($kb['keyboard'])) {
            foreach ($kb['keyboard'] as &$rxFRow) {
                if (!is_array($rxFRow)) continue;
                foreach ($rxFRow as &$rxFBtn) {
                    if (!is_array($rxFBtn)) continue;
                    $rxFBtn['_rxkeep'] = 1;
                }
                unset($rxFBtn);
            }
            unset($rxFRow);
            return json_encode($kb, JSON_UNESCAPED_UNICODE);
        }

        $newRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $newRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn) || !isset($btn['text'])) continue;

                if (!isset($btn['callback_data']) && !isset($btn['url'])
                    && !isset($btn['login_url']) && !isset($btn['switch_inline_query'])
                    && !isset($btn['switch_inline_query_current_chat']) && !isset($btn['web_app'])) {
                    $btn['callback_data'] = self::rx_makeAdminPanelCallback((string)$btn['text']);
                }

                unset($btn['request_contact'], $btn['request_location'], $btn['request_poll'], $btn['request_users'], $btn['request_chat']);
                if ($rxMarkPlain) { $btn['_rxap'] = 1; } else { $btn['_rxkeep'] = 1; }
                $newRow[] = $btn;
            }
            if (!empty($newRow)) $newRows[] = $newRow;
        }
        return json_encode(['inline_keyboard' => $newRows], JSON_UNESCAPED_UNICODE);
    }
}
