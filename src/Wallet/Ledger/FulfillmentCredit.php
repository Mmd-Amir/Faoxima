<?php

namespace Faoxima\Wallet\Ledger;

use PDO;
use Throwable;
use RuntimeException;

final class FulfillmentCredit
{
    public static function walletCredit($runtimePdo, string $orderId, string $token, $userId, int $amount, string $category, string $description): string
    {
        if ($amount <= 0 || trim((string) $userId) === '') {
            return 'invalid';
        }
        if (!\Faoxima\Payments\Fulfillment\WalletSchema::walletReady($runtimePdo)) {
            return 'error';
        }
        try {
            $status = \Faoxima\Payments\Fulfillment\Store::tx($runtimePdo, function (PDO $pdo) use ($orderId, $token, $userId, $amount, $category, $description) {
                $now = time();
                $record = \Faoxima\Payments\Fulfillment\Store::lockRecord($pdo, $orderId);
                if ($record === null) {
                    return 'missing';
                }
                if ((string) $record['state'] === 'completed') {
                    return 'already_completed';
                }
                if (!hash_equals((string) ($record['processing_token'] ?? ''), $token)) {
                    return 'not_owner';
                }
                if (!in_array((string) $record['state'], ['claimed', 'reconciling'], true)) {
                    return 'invalid_state';
                }
                $payment = \Faoxima\Payments\Fulfillment\Store::lockPayment($pdo, $orderId);
                if ($payment === null) {
                    return 'missing';
                }
                if (!\Faoxima\Payments\Fulfillment\PaymentPolicy::cronPaymentAllowed($payment, $now)) {
                    return 'cron_window_elapsed';
                }
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'completed', lease_expires_at = NULL, completed_at = ?, updated_at = ? WHERE id_order = ?");
                    $stmt->execute([$now, $now, $orderId]);
                    return 'already_completed';
                }
                $credit = \Faoxima\Wallet\Balance\Balances::incrementLocked($pdo, (string) $userId, $amount);
                if ($credit->rowCount() < 1) {
                    throw new RuntimeException('wallet_user_not_found');
                }
                $balanceStmt = $pdo->prepare("SELECT Balance FROM user WHERE id = ? LIMIT 1");
                $balanceStmt->execute([(string) $userId]);
                $balanceAfter = $balanceStmt->fetchColumn();
                $ledger = $pdo->prepare(
                    "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, fulfillment_key) VALUES (?, 'credit', ?, ?, ?, ?, ?, ?)"
                );
                $ledger->execute([(string) $userId, $amount, $balanceAfter === false ? null : (int) $balanceAfter, $category, $description, $orderId, $orderId]);
                $paid = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid', direct_payment_done = 1 WHERE id_order = ? AND COALESCE(direct_payment_done, 0) = 0");
                $paid->execute([$orderId]);
                if ($paid->rowCount() < 1) {
                    throw new RuntimeException('wallet_payment_finalize_failed');
                }
                $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'completed', result_state = ?, lease_expires_at = NULL, completed_at = ?, updated_at = ? WHERE id_order = ?");
                $stmt->execute([\Faoxima\Payments\Fulfillment\Claims::json(['credited' => $amount, 'balance_after' => $balanceAfter === false ? null : (int) $balanceAfter]), $now, $now, $orderId]);
                return 'credited';
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_WALLET_CREDIT_FAILED', $e->getMessage(), ['id_order' => $orderId, 'id_user' => (string) $userId, 'amount' => $amount]);
            return 'error';
        }
        \Faoxima\Payments\Fulfillment\Store::clearCache(['Payment_report', 'user']);
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
            try {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $userId);
            } catch (Throwable $e) {
            }
        }
        return $status;
    }
}
