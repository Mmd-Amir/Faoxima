<?php

namespace Faoxima\Admin\Telegram;

final class AgentCashbackKeyboards
{
    public static function rx_resolveAgentGroupFromReplyButton($text, array $allowed)
    {
        $t = trim((string) $text);
        $map = self::rx_agentGroupButtonMap(true);

        if (isset($map[$t]) && in_array($map[$t], $allowed, true)) {
            return $map[$t];
        }

        $fallbackMap = [
            'f' => 'f',
            'n' => 'n',
            'n2' => 'n2',
            'all' => 'all',
            'allusers' => 'allusers',
            'کاربر عادی' => 'f',
            'عادی' => 'f',
            'نماینده عادی' => 'n',
            'نماینده پیشرفته' => 'n2',
            'نماینده با قابلیت های بیشتر' => 'n2',
            'همه' => 'all',
            'همه گروه‌ها' => 'all',
            'همه گروه ها' => 'all',
            'همه کاربران' => 'all',
        ];

        if (isset($fallbackMap[$t]) && in_array($fallbackMap[$t], $allowed, true)) {
            return $fallbackMap[$t];
        }

        return null;
    }

    public static function rx_product_allows_agent(array $product, $agent)
    {
        $agent = strtolower(trim((string) $agent));
        if (!in_array($agent, ['f', 'n', 'n2'], true)) {
            return false;
        }

        if (!array_key_exists('agent', $product) || $product['agent'] === null) {
            return false;
        }

        $raw = $product['agent'];
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return false;
            }
            $decoded = json_decode($raw, true);
            $values = is_array($decoded) ? $decoded : preg_split('/[,|]/', $raw);
        }

        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $value = strtolower(trim((string) $value));
            if ($value === $agent || in_array($value, ['all', 'allusers'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function rx_resolveAgentGroupFromCallbackData($callbackData, array $allowed)
    {
        $parts = explode(':', trim((string) $callbackData), 2);

        if (count($parts) === 2 && $parts[0] === 'set_agent') {
            $token = trim($parts[1]);
            if (in_array($token, $allowed, true)) {
                return $token;
            }
        }

        return null;
    }

    public static function rx_resolveAgentGroup($raw, array $allowed)
    {
        $t = trim((string) $raw);
        $stripped = preg_replace('/^(?:[\s\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2500}-\x{25FF}\x{2900}-\x{2BFF}\x{1F100}-\x{1F1FF}\x{1FA00}-\x{1FAFF}\x{FE0E}\x{FE0F}\x{200D}])+/u', '', $t);
        $t = is_string($stripped) ? trim($stripped) : $t;
        $low = strtolower($t);

        $map = [
            'f' => 'f', 'کاربر عادی' => 'f', 'عادی' => 'f',
            'n' => 'n', 'نماینده عادی' => 'n',
            'n2' => 'n2', 'نماینده پیشرفته' => 'n2', 'نماینده با قابلیت های بیشتر' => 'n2',
        ];

        $token = null;
        if (isset($map[$low])) {
            $token = $map[$low];
        } elseif (isset($map[$t])) {
            $token = $map[$t];
        } else {
            $allWords = ['all', 'allusers', 'همه', 'همه گروه‌ها', 'همه گروه ها', 'همه کاربران'];
            if (in_array($low, $allWords, true) || in_array($t, $allWords, true)) {
                if (in_array('all', $allowed, true)) {
                    $token = 'all';
                } elseif (in_array('allusers', $allowed, true)) {
                    $token = 'allusers';
                }
            }
        }

        if ($token !== null && in_array($token, $allowed, true)) {
            return $token;
        }
        return null;
    }

    public static function rx_agentGroupButtonMap($allowAll = false)
    {
        $map = [
            '👤 کاربر عادی' => 'f',
            '🤝 نماینده عادی' => 'n',
            '💎 نماینده پیشرفته' => 'n2',
        ];
        if ($allowAll) {
            $map['📊 همه گروه‌ها'] = 'all';
        }
        return $map;
    }

    public static function rx_agentGroupKeyboard($allowAll = false)
    {
        global $textbotlang;
        $back = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';

        $rows = [
            [['text' => '👤 کاربر عادی'], ['text' => '🤝 نماینده عادی']],
            [['text' => '💎 نماینده پیشرفته']],
        ];
        if ($allowAll) {
            $rows[1][] = ['text' => '📊 همه گروه‌ها'];
        }
        $rows[] = [['text' => $back]];

        return json_encode([
            'keyboard' => $rows,
            'resize_keyboard' => true,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function rx_cashbackTargetOptionMap()
    {
        return [
            '🆕 ۱ روز گذشته' => '1',
            '📅 ۳ روز گذشته' => '3',
            '📅 ۷ روز گذشته' => '7',
            '📅 ۳۰ روز گذشته' => '30',
            '⏳ زمان دلخواه' => 'custom',
            '🎯 فقط اولین خرید' => 'firstpurchase',
            '👥 کل کاربران' => 'all',
        ];
    }

    public static function rx_cashbackTargetKeyboard()
    {
        global $textbotlang;
        $back = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';

        return json_encode([
            'keyboard' => [
                [['text' => '🆕 ۱ روز گذشته'], ['text' => '📅 ۳ روز گذشته']],
                [['text' => '📅 ۷ روز گذشته'], ['text' => '📅 ۳۰ روز گذشته']],
                [['text' => '⏳ زمان دلخواه']],
                [['text' => '🎯 فقط اولین خرید']],
                [['text' => '👥 کل کاربران']],
                [['text' => $back]],
            ],
            'resize_keyboard' => true,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function rx_normalizeCashbackButtonText($text)
    {
        $t = trim((string) $text);
        $t = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $t);
        $t = is_string($t) ? trim($t) : '';
        $stripped = preg_replace('/^(?:[\s\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2500}-\x{25FF}\x{2900}-\x{2BFF}\x{1F100}-\x{1F1FF}\x{1FA00}-\x{1FAFF}\x{FE0E}\x{FE0F}\x{200D}])+/u', '', $t);
        $t = is_string($stripped) ? trim($stripped) : $t;
        $t = strtr($t, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        return $t;
    }

    public static function rx_resolveCashbackTargetFromReplyButton($text)
    {
        $map = self::rx_cashbackTargetOptionMap();
        $raw = trim((string) $text);
        if (isset($map[$raw])) {
            return $map[$raw];
        }

        $normalizedInput = self::rx_normalizeCashbackButtonText($raw);
        foreach ($map as $label => $value) {
            if (self::rx_normalizeCashbackButtonText($label) === $normalizedInput) {
                return $value;
            }
        }

        return null;
    }

    public static function rx_cashbackScopeKeyboardRows($callbackPrefix, array $selectedScopes)
    {
        $rows = [];
        foreach (\Faoxima\Cashback\Eligibility::cashbackScopeOptionMap() as $scopeKey => $label) {
            $isSelected = in_array($scopeKey, $selectedScopes, true);
            $checkIcon = $isSelected ? '✅' : '❌';
            $rows[] = [[
                'text' => "{$label} {$checkIcon}",
                'callback_data' => $callbackPrefix . 'toggle#' . $scopeKey,
            ]];
        }
        $allSelected = count(array_diff(\Faoxima\Cashback\Eligibility::cashbackScopeDefault(), $selectedScopes)) === 0 && count($selectedScopes) === count(\Faoxima\Cashback\Eligibility::cashbackScopeDefault());
        $rows[] = [[
            'text' => $allSelected ? '👥 همه موارد ✅' : '👥 همه موارد',
            'callback_data' => $callbackPrefix . 'all',
        ]];
        $rows[] = [[
            'text' => '✅ تایید و ذخیره',
            'callback_data' => $callbackPrefix . 'confirm',
        ]];
        return $rows;
    }

    public static function rx_cashbackEligibleForKey($cashbackKey, $userRegisterTs, $idInvoice = null, $userId = null, $currentOrderId = null)
    {
        global $pdo;
        return \Faoxima\Cashback\Eligibility::cashbackEligibleForKey($pdo, $cashbackKey, $userRegisterTs, $idInvoice, $userId, $currentOrderId);
    }
}
