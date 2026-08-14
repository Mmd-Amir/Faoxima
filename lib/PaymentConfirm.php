<?php

/**
 * Shared payment finalization for the web-only build.
 *
 * Payment callbacks update the database and wallet only. User/admin delivery
 * through Telegram or the removed mini-app is intentionally not performed.
 */

if (!function_exists('payment_confirm_paid')) {
    function payment_confirm_paid(string $orderId, string $cashbackKey, array $reportData = []): array
    {
        global $pdo;

        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo unavailable'];
        }

        $atomic = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'paid' "
            . "WHERE id_order = :id AND payment_Status <> 'paid'"
        );
        $atomic->execute([':id' => $orderId]);
        if ($atomic->rowCount() < 1) {
            return ['ok' => false, 'reason' => 'already paid or missing'];
        }

        $report = select('Payment_report', '*', 'id_order', $orderId, 'select');
        if (!is_array($report)) {
            return ['ok' => false, 'reason' => 'report missing after update'];
        }

        if (function_exists('crypto_record_verified_hash')) {
            $method = strtolower((string) ($report['Payment_Method'] ?? ''));
            if (in_array($method, ['plisio', 'nowpayment', 'digitaltron', 'arze digital offline'], true)) {
                $source = [
                    'plisio' => 'plisio_ipn',
                    'nowpayment' => 'nowpayment_ipn',
                    'digitaltron' => 'nowpayment_ipn',
                    'arze digital offline' => 'manual_admin',
                ][$method] ?? 'gateway_ipn';
                crypto_record_verified_hash($orderId, $source);
            }
        }

        if (function_exists('DirectPayment')) {
            $imagePath = __DIR__ . '/../images.jpeg';
            if (!is_file($imagePath)) {
                $imagePath = __DIR__ . '/../images.jpg';
            }
            DirectPayment($report['id_order'], $imagePath);
        }

        $userId = (string) ($report['id_user'] ?? '');
        $balanceUser = select('user', '*', 'id', $userId, 'select');
        if (!is_array($balanceUser)) {
            $balanceUser = ['id' => $userId, 'username' => '—', 'Balance' => 0];
        }

        $cashbackPercent = '0';
        if ($cashbackKey !== '') {
            $cashbackRow = select('PaySetting', 'ValuePay', 'NamePay', $cashbackKey, 'select');
            if (is_array($cashbackRow)) {
                $cashbackPercent = (string) ($cashbackRow['ValuePay'] ?? '0');
            }
        }

        $cashbackAmount = 0;
        if ($cashbackPercent !== '' && $cashbackPercent !== '0') {
            $cashbackAmount = ((int) ($report['price'] ?? 0) * (int) $cashbackPercent) / 100;
            update('user', 'Balance', (int) ($balanceUser['Balance'] ?? 0) + $cashbackAmount, 'id', $balanceUser['id']);
        }

        return [
            'ok' => true,
            'order_id' => $report['id_order'],
            'user_id' => $balanceUser['id'],
            'amount' => (int) ($report['price'] ?? 0),
            'cashback_amount' => $cashbackAmount,
        ];
    }
}

if (!function_exists('payment_notify_user_failed')) {
    function payment_notify_user_failed(string $orderId, string $reason = ''): bool
    {
        $report = select('Payment_report', '*', 'id_order', $orderId, 'select');
        if (!is_array($report) || strtolower((string) ($report['payment_Status'] ?? '')) === 'paid') {
            return false;
        }
        update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
        return true;
    }
}

if (!function_exists('payment_mark_expired')) {
    function payment_mark_expired(string $orderId, ?string $textExpire = null): bool
    {
        $report = select('Payment_report', '*', 'id_order', $orderId, 'select');
        if (!is_array($report) || strtolower((string) ($report['payment_Status'] ?? '')) === 'paid') {
            return false;
        }
        update('Payment_report', 'payment_Status', 'expire', 'id_order', $orderId);
        return true;
    }
}
