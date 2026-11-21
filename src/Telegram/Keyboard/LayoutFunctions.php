<?php

namespace Faoxima\Telegram\Keyboard;

final class LayoutFunctions
{
    public static function rx_inlineButtonMapFile() {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
        return rtrim($base, '/').'/rx_inline_button_map.json';
    }

    public static function rx_storeInlineButtonText($text) {
        $code = 'rxb_' . substr(hash('sha256', (string)$text), 0, 24);
        return rx_callbackMapStore(self::rx_inlineButtonMapFile(), $code, (string)$text) ? $code : 'start';
    }

    public static function rx_resolveInlineButtonText($code) {
        if (!is_string($code) || strpos($code, 'rxb_') !== 0) return null;
        $map = rx_callbackMapRead(self::rx_inlineButtonMapFile());
        return is_array($map) && isset($map[$code]) ? (string)$map[$code] : null;
    }

    public static function rx_safeCallbackData($callbackData, $buttonText = '') {
        $callbackData = (string)$callbackData;
        if ($callbackData === '') $callbackData = (string)$buttonText;
        return strlen($callbackData) <= 64 ? $callbackData : self::rx_storeInlineButtonText($buttonText !== '' ? $buttonText : $callbackData);
    }

    public static function rx_keyboardToInline($keyboardArray) {
        if (!is_array($keyboardArray)) return json_encode($keyboardArray);
        if (isset($keyboardArray['inline_keyboard'])) return json_encode($keyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!isset($keyboardArray['keyboard']) || !is_array($keyboardArray['keyboard'])) return json_encode($keyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($keyboardArray['keyboard'] as $rxCheckRow) {
            if (!is_array($rxCheckRow)) continue;
            foreach ($rxCheckRow as $rxCheckBtn) {
                if (is_array($rxCheckBtn) && (
                    isset($rxCheckBtn['request_contact']) || isset($rxCheckBtn['request_location']) ||
                    isset($rxCheckBtn['request_poll']) || isset($rxCheckBtn['request_users']) ||
                    isset($rxCheckBtn['request_chat'])
                )) {
                    return json_encode($keyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        $inline = ['inline_keyboard' => []];
        foreach ($keyboardArray['keyboard'] as $row) {
            if (!is_array($row)) continue;
            $newRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn) || !isset($btn['text'])) continue;
                $buttonText = (string) $btn['text'];
                $hasAction = (isset($btn['callback_data']) && (string)$btn['callback_data'] !== '')
                    || isset($btn['url']) || isset($btn['web_app'])
                    || isset($btn['login_url']) || isset($btn['switch_inline_query'])
                    || isset($btn['switch_inline_query_current_chat']) || isset($btn['callback_game'])
                    || isset($btn['pay']);
                if (!$hasAction) {
                    $callbackData = $buttonText;
                    if ($buttonText === ($GLOBALS['textbotlang']['users']['backbtn'] ?? '')) {
                        $callbackData = 'backuser';
                    } elseif ($buttonText === ($GLOBALS['textbotlang']['Admin']['backadmin'] ?? '')) {
                        $callbackData = 'admin';
                    } elseif ($buttonText === ($GLOBALS['textbotlang']['Admin']['backmenu'] ?? '')) {
                        $callbackData = 'backmenu';
                    }
                    $btn['callback_data'] = self::rx_safeCallbackData($callbackData, $buttonText);
                }
                $newRow[] = $btn;
            }
            if (!empty($newRow)) $inline['inline_keyboard'][] = $newRow;
        }
        return json_encode($inline, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function rx_kb_style(array $btn, string $key, ?array $styles = []): array {
        $styles = is_array($styles) ? $styles : [];
        if (!empty($styles['__rxplain__' ])) { $btn['_rxap'] = 1; return $btn; }
        if (!empty($styles['__rxkeep__'])) { $btn['_rxkeep'] = 1; }
        if (!empty($styles[$key]) && $styles[$key] !== 'default') {
            $btn['style'] = $styles[$key];
            return $btn;
        }

        if (!isset($btn['style']) && function_exists('rx_kb_guess_style_from_text') && isset($btn['text'])
            && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $rxGuessed = rx_kb_guess_style_from_text((string)$btn['text']);
            if ($rxGuessed !== null) {
                $btn['style'] = $rxGuessed;
            } else {
                $btn['style'] = 'primary';
            }
        }
        return $btn;
    }

    public static function rx_kb_encode(array $rows, $forceInline = null): string {
        global $setting, $textbotlang;
        $useInline = ($forceInline !== null)
            ? (bool)$forceInline
            : (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline');
        if ($forceInline === null && !$useInline) {
            foreach ($rows as $rxScanRow) {
                if (!is_array($rxScanRow)) continue;
                foreach ($rxScanRow as $rxScanBtn) {
                    if (is_array($rxScanBtn) && !empty($rxScanBtn['_rxap'])) {
                        $useInline = true;
                        break 2;
                    }
                }
            }
        }
        if ($useInline) {
            $sanitized = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $newRow = [];
                foreach ($row as $btn) {
                    if (is_string($btn)) {
                        $btn = ['text' => $btn];
                    }
                    if (!is_array($btn) || !isset($btn['text'])) {
                        continue;
                    }
                    $bText = (string)$btn['text'];
                    $hasAction = isset($btn['callback_data']) || isset($btn['url']) || isset($btn['web_app'])
                        || isset($btn['login_url']) || isset($btn['switch_inline_query'])
                        || isset($btn['switch_inline_query_current_chat']) || isset($btn['callback_game'])
                        || isset($btn['pay']);
                    if (!$hasAction) {
                        $cb = $bText;
                        if (isset($textbotlang['Admin']['backadmin']) && $bText === $textbotlang['Admin']['backadmin']) {
                            $cb = 'adm_hub_main';
                        } elseif (isset($textbotlang['Admin']['backmenu']) && $bText === $textbotlang['Admin']['backmenu']) {
                            $cb = 'adm_backmenu';
                        } elseif (isset($textbotlang['users']['backbtn']) && $bText === $textbotlang['users']['backbtn']) {
                            $cb = 'backuser';
                        }
                        $btn['callback_data'] = method_exists(\Faoxima\Telegram\Keyboard\LayoutFunctions::class, 'rx_safeCallbackData')
                            ? self::rx_safeCallbackData($cb, $bText)
                            : (strlen($cb) <= 64 ? $cb : substr($cb, 0, 64));
                    }
                    $newRow[] = $btn;
                }
                if (!empty($newRow)) {
                    $sanitized[] = $newRow;
                }
            }
            return json_encode(['inline_keyboard' => $sanitized], JSON_UNESCAPED_UNICODE);
        }

        $replyRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $newRow = [];
            foreach ($row as $btn) {
                if (is_string($btn)) {
                    $newRow[] = ['text' => $btn];
                } elseif (is_array($btn) && isset($btn['text'])) {
                    $newRow[] = ['text' => (string)$btn['text']];
                }
            }
            if (!empty($newRow)) {
                $replyRows[] = $newRow;
            }
        }
        return json_encode(['keyboard' => $replyRows, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
    }
}
