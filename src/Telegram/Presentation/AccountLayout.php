<?php

namespace Faoxima\Telegram\Presentation;

use PDO;

final class AccountLayout
{
    public static function rx_account_button_registry(): array
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

    public static function rx_account_default_layout(): array
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

    public static function rx_account_layout_normalize($raw): array
    {
        $registry = self::rx_account_button_registry();
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
            return self::rx_account_default_layout();
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

    public static function rx_account_layout_encode(array $rows): string
    {
        return json_encode(['version' => 1, 'rows' => array_values($rows)], JSON_UNESCAPED_UNICODE);
    }

    public static function rx_account_layout_load(): array
    {
        global $setting;
        $raw = (is_array($setting ?? null) && isset($setting['account_keyboard_layout'])) ? trim((string) $setting['account_keyboard_layout']) : '';
        if ($raw === '') {
            return self::rx_account_default_layout();
        }
        return self::rx_account_layout_normalize($raw);
    }

    public static function rx_account_condition_met(?string $condition, $userId): bool
    {
        if ($condition === null) {
            return true;
        }
        if ($condition === 'crypto_wallet') {
            return method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallets') && !empty(\Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallets());
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
            return (method_exists(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::class, 'tonpayCustomUserPending') && (\Faoxima\Payments\Gateways\Tonpay\Client::paymentMode() === 'custom' || !empty(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomUserPending($userId, 1))))
                || (method_exists(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::class, 'atlaspayCustomUserPending') && (\Faoxima\Payments\Gateways\Atlaspay\CustomClient::paymentMode() === 'custom' || !empty(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomUserPending($userId, 1))))
                || (method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitUserHasPending') && \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitUserHasPending($userId));
        }
        return false;
    }

    public static function rx_account_button_label(string $id): string
    {
        global $datatextbot, $textbotlang;
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        return \Faoxima\Telegram\Keyboard\AccountKeyboard::label($id, self::rx_account_button_registry(), $datatextbot, $textbotlang);
    }

    public static function rx_account_keyboard_rows($userId): array
    {
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        $registry = self::rx_account_button_registry();
        $styles = (isset($GLOBALS['_rx_acc_styles']) && is_array($GLOBALS['_rx_acc_styles'])) ? $GLOBALS['_rx_acc_styles'] : [];
        return \Faoxima\Telegram\Keyboard\AccountKeyboard::rows(
            $userId,
            $registry,
            self::rx_account_layout_load(),
            $styles,
            [\Faoxima\Telegram\Presentation\AccountLayout::class, 'rx_account_condition_met'],
            [\Faoxima\Telegram\Presentation\AccountLayout::class, 'rx_account_button_label'],
            [\Faoxima\Telegram\Keyboard\LayoutFunctions::class, 'rx_kb_style'],
        );
    }

    public static function rx_account_keyboard_json($userId): string
    {
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        $rows = self::rx_account_keyboard_rows($userId);
        return \Faoxima\Telegram\Keyboard\AccountKeyboard::json($rows);
    }
}
