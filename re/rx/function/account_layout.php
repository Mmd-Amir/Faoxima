<?php

if (!function_exists('rx_account_button_registry')) {
    function rx_account_button_registry(): array
    {
        return [
            'tpc_list' => [
                'label' => '⏳ تراکنش‌های در انتظار',
                'textbot' => null,
                'condition' => 'pending_payments',
                'note' => 'فقط زمانی نمایش داده می‌شود که پرداخت درون‌ربات فعال باشد یا کاربر تراکنش در انتظار داشته باشد.',
            ],
            'my_miniapp_pending' => [
                'label' => '⏳ رسید تایید نشده از مینی‌اپ',
                'textbot' => null,
                'condition' => 'miniapp_pending',
                'note' => 'فقط زمانی نمایش داده می‌شود که کاربر رسید تایید نشده‌ای از مینی‌اپ داشته باشد.',
            ],
            'Discount' => [
                'label' => '🎁 کد هدیه',
                'textbot' => 'text_Discount',
                'condition' => null,
                'note' => '',
            ],
            'Add_Balance' => [
                'label' => '💰 افزایش موجودی',
                'textbot' => 'text_Add_Balance',
                'condition' => null,
                'note' => '',
            ],
            'TransferBalance' => [
                'label' => '🔄 انتقال موجودی',
                'textbot' => null,
                'condition' => null,
                'note' => '',
            ],
            'MyTransactions' => [
                'label' => '📑 تراکنش‌های من',
                'textbot' => null,
                'condition' => null,
                'note' => '',
            ],
            'supporttickets' => [
                'label' => '🎫 تیکت‌های پشتیبانی',
                'textbot' => null,
                'condition' => null,
                'note' => '',
            ],
            'recheckcrypto' => [
                'label' => '🔁 بررسی مجدد هش کریپتو',
                'textbot' => null,
                'condition' => 'crypto_wallet',
                'note' => 'فقط زمانی نمایش داده می‌شود که حداقل یک کیف پول کریپتو فعال باشد.',
            ],
            'backuser' => [
                'label' => '🏠 بازگشت به منوی اصلی',
                'textbot' => null,
                'condition' => null,
                'note' => '',
                'required' => true,
            ],
        ];
    }
}

if (!function_exists('rx_account_default_layout')) {
    function rx_account_default_layout(): array
    {
        return [
            ['tpc_list'],
            ['my_miniapp_pending'],
            ['Discount', 'Add_Balance'],
            ['TransferBalance', 'MyTransactions'],
            ['supporttickets'],
            ['recheckcrypto'],
            ['backuser'],
        ];
    }
}

if (!function_exists('rx_account_layout_normalize')) {
    function rx_account_layout_normalize($raw): array
    {
        $registry = rx_account_button_registry();
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
            return rx_account_default_layout();
        }
        $seen = [];
        $rows = [];
        foreach ($decoded['rows'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cleanRow = [];
            foreach ($row as $id) {
                if (!is_string($id) || !isset($registry[$id]) || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $cleanRow[] = $id;
            }
            if ($cleanRow) {
                $rows[] = $cleanRow;
            }
        }
        if (!isset($seen['backuser'])) {
            $rows[] = ['backuser'];
        }
        return $rows;
    }
}

if (!function_exists('rx_account_layout_encode')) {
    function rx_account_layout_encode(array $rows): string
    {
        return json_encode(['version' => 1, 'rows' => array_values($rows)], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rx_account_layout_load')) {
    function rx_account_layout_load(): array
    {
        global $setting;
        $raw = (is_array($setting ?? null) && isset($setting['account_keyboard_layout'])) ? trim((string) $setting['account_keyboard_layout']) : '';
        if ($raw === '') {
            return rx_account_default_layout();
        }
        return rx_account_layout_normalize($raw);
    }
}

if (!function_exists('rx_account_condition_met')) {
    function rx_account_condition_met(?string $condition, $userId): bool
    {
        if ($condition === null) {
            return true;
        }
        if ($condition === 'crypto_wallet') {
            return function_exists('crypto_active_wallets') && !empty(crypto_active_wallets());
        }
        if ($condition === 'miniapp_pending') {
            global $pdo;
            if (!($pdo instanceof PDO)) {
                return false;
            }
            $stmt = $pdo->prepare(
                "SELECT id_order, price FROM Payment_report
                  WHERE id_user = :uid
                    AND payment_Status = 'waiting'
                    AND Payment_Method = 'cart to cart'
                    AND source = 'miniapp'
                    AND dec_not_confirmed = 'receipt-submitted'
                  LIMIT 1"
            );
            $stmt->execute([':uid' => $userId]);
            return $stmt->rowCount() > 0;
        }
        if ($condition === 'pending_payments') {
            return (function_exists('tonpayCustomUserPending') && (tonpayPaymentMode() === 'custom' || !empty(tonpayCustomUserPending($userId, 1))))
                || (function_exists('atlaspayCustomUserPending') && (atlaspayPaymentMode() === 'custom' || !empty(atlaspayCustomUserPending($userId, 1))))
                || (function_exists('rialitUserHasPending') && rialitUserHasPending($userId));
        }
        return false;
    }
}

if (!function_exists('rx_account_button_label')) {
    function rx_account_button_label(string $id): string
    {
        global $datatextbot, $textbotlang;
        $registry = rx_account_button_registry();
        if ($id === 'backuser' && !empty($textbotlang['users']['backbtn'])) {
            return (string) $textbotlang['users']['backbtn'];
        }
        $key = $registry[$id]['textbot'] ?? null;
        if ($key !== null && isset($datatextbot[$key]) && trim((string) $datatextbot[$key]) !== '') {
            return (string) $datatextbot[$key];
        }
        return (string) ($registry[$id]['label'] ?? $id);
    }
}

if (!function_exists('rx_account_keyboard_rows')) {
    function rx_account_keyboard_rows($userId): array
    {
        $registry = rx_account_button_registry();
        $styles = (isset($GLOBALS['_rx_acc_styles']) && is_array($GLOBALS['_rx_acc_styles'])) ? $GLOBALS['_rx_acc_styles'] : [];
        $rows = [];
        foreach (rx_account_layout_load() as $layoutRow) {
            $row = [];
            foreach ($layoutRow as $id) {
                if (!isset($registry[$id])) {
                    continue;
                }
                try {
                    if (!rx_account_condition_met($registry[$id]['condition'], $userId)) {
                        continue;
                    }
                } catch (Throwable $e) {
                    continue;
                }
                $row[] = rx_kb_style(['text' => rx_account_button_label($id), 'callback_data' => $id], $id, $styles);
            }
            if ($row) {
                $rows[] = $row;
            }
        }
        $hasBack = false;
        foreach ($rows as $row) {
            foreach ($row as $btn) {
                if (($btn['callback_data'] ?? '') === 'backuser') {
                    $hasBack = true;
                }
            }
        }
        if (!$hasBack) {
            $rows[] = [rx_kb_style(['text' => rx_account_button_label('backuser'), 'callback_data' => 'backuser'], 'backuser', $styles)];
        }
        return $rows;
    }
}

if (!function_exists('rx_account_keyboard_json')) {
    function rx_account_keyboard_json($userId): string
    {
        return json_encode([
            'inline_keyboard' => rx_account_keyboard_rows($userId),
            'resize_keyboard' => true,
        ], JSON_UNESCAPED_UNICODE);
    }
}
