<?php

namespace Faoxima\Orders\Checkout;

use PDO;
use Throwable;
use RuntimeException;

final class Settlement
{
    public static function findForReport($runtimePdo, array $report): ?array
    {
        if (!($runtimePdo instanceof PDO) || !self::isServicePayment($report) || !Schema::ensureSchema($runtimePdo)) {
            return null;
        }
        try {
            return self::lookupForReport($runtimePdo, $report);
        } catch (Throwable $e) {
            Checkout::log('CHECKOUT_FIND_FAILED', $e->getMessage(), ['id_order' => $report['id_order'] ?? '']);
            return null;
        }
    }

    private static function isServicePayment(array $report): bool
    {
        [$operation] = Checkout::keyParts((string) ($report['id_invoice'] ?? ''));
        return in_array($operation, Checkout::operations(), true);
    }

    private static function lookupForReport(PDO $pdo, array $report): ?array
    {
        $orderId = (string) ($report['id_order'] ?? '');
        $userId = (string) ($report['id_user'] ?? '');
        if ($orderId !== '') {
            $stmt = $pdo->prepare("SELECT * FROM checkout_order WHERE payment_order_id = ? AND id_user = ? ORDER BY id DESC LIMIT 1");
            if (!$stmt->execute([$orderId, $userId])) {
                throw new RuntimeException('checkout_payment_lookup_failed');
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                return $row;
            }
        }
        $stmt = $pdo->prepare("SELECT * FROM checkout_order WHERE id_user = ? AND op_key = ? AND status IN ('awaiting_payment', 'confirmed', 'expired', 'cancelled', 'completed', 'failed', 'resolved_credit', 'awaiting_choice') ORDER BY (status = 'awaiting_payment') DESC, id DESC LIMIT 1");
        if (!$stmt->execute([$userId, (string) ($report['id_invoice'] ?? '')])) {
            throw new RuntimeException('checkout_operation_lookup_failed');
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function resolveWalletShortfall(PDO $pdo, array $ck, string $orderId, string $userId, int $paid, int $now): array
    {
        \Faoxima\Wallet\Balance\Balances::incrementLocked($pdo, $userId, $paid);
        $after = $pdo->prepare("SELECT Balance FROM user WHERE id = ?");
        $after->execute([$userId]);
        $balanceAfter = (int) $after->fetchColumn();
        $pdo->prepare(
            "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id, fulfillment_key) VALUES (?, 'credit', ?, ?, 'checkout_credit', ?, ?, 'checkout_order', ?, ?)"
        )->execute([$userId, $paid, $balanceAfter, 'اعتبار پرداخت تأییدشده - موجودی کیف پول برای تکمیل خرید کافی نبود', $orderId, (string) $ck['checkout_id'], 'ckcredit:' . $orderId]);
        $pdo->prepare(
            "UPDATE checkout_order SET status = 'resolved_credit', resolution = 'wallet_credit', wallet_state = 'none', settle_order_id = ?, payment_order_id = ?, fulfillment_state = 'credited', updated_at = ? WHERE checkout_id = ?"
        )->execute([$orderId, $orderId, $now, $ck['checkout_id']]);
        $pdo->prepare(
            "UPDATE payment_fulfillment SET state = 'completed', result_state = ?, lease_expires_at = NULL, completed_at = ?, updated_at = ? WHERE id_order = ?"
        )->execute([json_encode(['resolved' => 'wallet_credit', 'credited' => $paid, 'balance_after' => $balanceAfter]), $now, $now, $orderId]);
        $paidRow = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid', direct_payment_done = 1 WHERE id_order = ? AND COALESCE(direct_payment_done, 0) = 0");
        $paidRow->execute([$orderId]);
        if ($paidRow->rowCount() < 1) {
            throw new RuntimeException('checkout_credit_finalize_failed');
        }
        $ck['status'] = 'resolved_credit';
        return ['action' => 'resolved_credit', 'checkout' => $ck, 'code' => 'wallet_insufficient', 'credited' => $paid];
    }

    public static function gateFulfillment($runtimePdo, array $report, string $token): array
    {
        $pdo = $runtimePdo;
        if (!self::isServicePayment($report)) {
            return ['action' => 'legacy', 'checkout' => null, 'code' => 'legacy'];
        }
        if (!($pdo instanceof PDO) || !Schema::ensureSchema($pdo)) {
            return ['action' => 'error', 'checkout' => null, 'code' => 'checkout_schema_unavailable'];
        }
        $orderId = (string) ($report['id_order'] ?? '');
        $userId = (string) ($report['id_user'] ?? '');
        if ($orderId === '' || $userId === '') {
            return ['action' => 'error', 'checkout' => null, 'code' => 'checkout_identity_unavailable'];
        }
        $ck0 = null;
        try {
            $ck0 = self::lookupForReport($pdo, $report);
            if ($ck0 === null) {
                return ['action' => 'legacy', 'checkout' => null, 'code' => 'legacy'];
            }
            if (!$pdo->inTransaction() && !\Faoxima\Payments\Fulfillment\Schema::ensureSchema($pdo)) {
                return ['action' => 'error', 'checkout' => $ck0, 'code' => 'fulfillment_schema_unavailable'];
            }
            $out = \Faoxima\Orders\Checkout\Checkout::tx($runtimePdo, function (PDO $pdo) use ($ck0, $orderId, $userId, $token) {
                $now = time();
                $payment = \Faoxima\Payments\Fulfillment\Store::lockPayment($pdo, $orderId);
                if ($payment === null) {
                    return ['action' => 'error', 'checkout' => $ck0, 'code' => 'payment_missing'];
                }
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    return ['action' => 'deny', 'checkout' => $ck0, 'code' => 'already_done', 'terminal' => true];
                }
                $record = \Faoxima\Payments\Fulfillment\Store::lockRecord($pdo, $orderId);
                if ($record === null || !hash_equals((string) ($record['processing_token'] ?? ''), $token)
                    || !in_array((string) $record['state'], ['claimed', 'applying', 'reconciling'], true)) {
                    return ['action' => 'error', 'checkout' => $ck0, 'code' => 'not_owner'];
                }
                $ck = Checkout::lock($pdo, (string) $ck0['checkout_id'], $userId);
                if ($ck === null) {
                    return ['action' => 'error', 'checkout' => $ck0, 'code' => 'checkout_missing'];
                }
                $paid = (int) round((float) ($payment['price'] ?? 0));
                $wallet = (int) $ck['wallet_amount'];
                $status = (string) $ck['status'];
                $owner = (string) ($ck['settle_order_id'] ?? '');
                $deny = static function (string $code) use ($ck) {
                    return ['action' => 'deny', 'checkout' => $ck, 'code' => $code];
                };
                if ($owner === $orderId && in_array($status, ['awaiting_payment', 'confirmed', 'expired'], true)) {
                    return ['action' => 'proceed', 'checkout' => $ck, 'code' => 'resumed'];
                }
                if (in_array($status, ['completed', 'resolved_credit'], true) || $owner !== '') {
                    return $deny('already_settled');
                }
                if (in_array($status, ['cancelled', 'failed'], true)) {
                    return $deny($status);
                }
                if ($paid + 1 < (int) $ck['external_amount']) {
                    return $deny('amount_mismatch');
                }
                if ($wallet > 0) {
                    $lockStmt = $pdo->prepare("SELECT Balance FROM user WHERE id = ? FOR UPDATE");
                    $lockStmt->execute([$userId]);
                    $balance = $lockStmt->fetchColumn();
                    if ($balance === false) {
                        return ['action' => 'error', 'checkout' => $ck, 'code' => 'user_missing'];
                    }
                    if ((int) $balance < $wallet) {
                        if ($paid <= 0) {
                            return $deny('amount_mismatch');
                        }
                        return self::resolveWalletShortfall($pdo, $ck, $orderId, $userId, $paid, $now);
                    }
                    $pdo->prepare("UPDATE user SET Balance = Balance - ? WHERE id = ? AND Balance >= ?")->execute([$wallet, $userId, $wallet]);
                    $after = $pdo->prepare("SELECT Balance FROM user WHERE id = ?");
                    $after->execute([$userId]);
                    $balanceAfter = (int) $after->fetchColumn();
                    $pdo->prepare(
                        "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id, fulfillment_key) VALUES (?, 'debit', ?, ?, 'checkout', ?, ?, 'checkout_order', ?, ?)"
                    )->execute([$userId, $wallet, $balanceAfter, 'سهم کیف پول از پرداخت ترکیبی سرویس', $orderId, (string) $ck['checkout_id'], 'ckwallet:' . (string) $ck['checkout_id']]);
                }
                $pdo->prepare(
                    "UPDATE checkout_order SET status = 'awaiting_payment', wallet_state = ?, settle_order_id = ?, payment_order_id = ?, fulfillment_state = 'processing', updated_at = ? WHERE checkout_id = ?"
                )->execute([$wallet > 0 ? 'debited' : 'none', $orderId, $orderId, $now, $ck['checkout_id']]);
                return ['action' => 'proceed', 'checkout' => $ck, 'code' => 'settled'];
            });
        } catch (Throwable $e) {
            \Faoxima\Orders\Checkout\Checkout::log('CHECKOUT_GATE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['action' => 'error', 'checkout' => $ck0, 'code' => 'error'];
        }
        \Faoxima\Orders\Checkout\Checkout::clearUserCache($userId);
        \Faoxima\Payments\Fulfillment\Store::clearCache(['Payment_report', 'user']);
        return $out;
    }

    public static function settlementStatements(?array $ck, string $orderId, $userId, int $legacyAmount = 0, string $description = 'پرداخت ترکیبی سرویس'): array
    {
        $userId = (string) $userId;
        if ($ck === null) {
            if ($legacyAmount === -1) {
                return [["UPDATE user SET Balance = 0 WHERE id = ?", [$userId]]];
            }
            if ($legacyAmount <= 0) {
                return [];
            }
            return [["UPDATE user SET Balance = GREATEST(Balance - ?, 0) WHERE id = ?", [$legacyAmount, $userId]]];
        }
        return [[
            "UPDATE checkout_order SET status = 'completed', resolution = 'service', fulfillment_state = 'completed', payment_order_id = ?, updated_at = ? WHERE checkout_id = ? AND settle_order_id = ?",
            [$orderId, time(), (string) $ck['checkout_id'], $orderId],
        ]];
    }

    public static function releaseForReport($runtimePdo, $report, string $newStatus = 'cancelled', string $reason = 'payment_closed', bool $force = false): string
    {
        $pdo = $runtimePdo;
        if (!is_array($report) || !($pdo instanceof PDO)) {
            return 'noop';
        }
        $ck = self::findForReport($runtimePdo, $report);
        if ($ck === null) {
            return 'noop';
        }
        try {
            return \Faoxima\Orders\Checkout\Checkout::tx($runtimePdo, function (PDO $pdo) use ($ck, $report, $newStatus, $reason) {
                $ck = \Faoxima\Orders\Checkout\Checkout::lock($pdo, (string) $ck['checkout_id'], null);
                if ($ck === null || in_array((string) $ck['status'], ['completed', 'cancelled', 'expired', 'failed', 'resolved_credit'], true) || (string) ($ck['settle_order_id'] ?? '') !== '') {
                    return 'noop';
                }
                $orderId = (string) ($report['id_order'] ?? '');
                $live = "'" . implode("','", \Faoxima\Orders\Checkout\Checkout::livePaymentStatuses()) . "'";
                $others = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE id_user = ? AND id_invoice = ? AND id_order <> ? AND payment_Status IN ($live)");
                $others->execute([(string) $ck['id_user'], (string) $ck['op_key'], $orderId]);
                if ((int) $others->fetchColumn() > 0) {
                    return 'still_live';
                }
                $own = $pdo->prepare("SELECT payment_Status FROM Payment_report WHERE id_order = ? LIMIT 1");
                $own->execute([$orderId]);
                if (in_array((string) $own->fetchColumn(), array_merge(\Faoxima\Orders\Checkout\Checkout::fulfillingPaymentStatuses(), ['paid']), true)) {
                    return 'in_fulfillment';
                }
                $pdo->prepare("UPDATE checkout_order SET status = ?, fulfillment_state = ?, updated_at = ? WHERE checkout_id = ?")
                    ->execute([$newStatus, $reason, time(), $ck['checkout_id']]);
                return 'released';
            });
        } catch (Throwable $e) {
            \Faoxima\Orders\Checkout\Checkout::log('CHECKOUT_RELEASE_FAILED', $e->getMessage(), ['id_order' => $report['id_order'] ?? '']);
            return 'error';
        }
    }

    public static function releaseForOrder($runtimePdo, string $orderId, string $newStatus = 'cancelled', string $reason = 'payment_closed', bool $force = false): string
    {
        $pdo = $runtimePdo;
        if ($orderId === '' || !($pdo instanceof PDO)) {
            return 'noop';
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return 'error';
        }
        return is_array($report) ? self::releaseForReport($runtimePdo, $report, $newStatus, $reason) : 'noop';
    }

    public static function releaseFailedFulfillment($runtimePdo, string $orderId, string $reason): string
    {
        $pdo = $runtimePdo;
        if ($orderId === '' || !($pdo instanceof PDO) || !\Faoxima\Orders\Checkout\Schema::ensureSchema($runtimePdo)) {
            return 'noop';
        }
        try {
            $out = \Faoxima\Orders\Checkout\Checkout::tx($runtimePdo, function (PDO $pdo) use ($orderId, $reason) {
                $stmt = $pdo->prepare("SELECT * FROM checkout_order WHERE settle_order_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
                $stmt->execute([$orderId]);
                $ck = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($ck === false || in_array((string) $ck['status'], ['completed', 'resolved_credit', 'failed'], true)) {
                    return 'noop';
                }
                $wallet = (int) $ck['wallet_amount'];
                $restored = false;
                if ((string) $ck['wallet_state'] === 'debited' && $wallet > 0) {
                    $userId = (string) $ck['id_user'];
                    $pdo->prepare("SELECT Balance FROM user WHERE id = ? FOR UPDATE")->execute([$userId]);
                    \Faoxima\Wallet\Balance\Balances::incrementLocked($pdo, $userId, $wallet);
                    $after = $pdo->prepare("SELECT Balance FROM user WHERE id = ?");
                    $after->execute([$userId]);
                    $pdo->prepare(
                        "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id, fulfillment_key) VALUES (?, 'credit', ?, ?, 'refund', ?, ?, 'checkout_order', ?, ?)"
                    )->execute([$userId, $wallet, (int) $after->fetchColumn(), 'بازگشت سهم کیف پول - خرید تکمیل نشد', $orderId, (string) $ck['checkout_id'], 'ckwalletrefund:' . (string) $ck['checkout_id']]);
                    $restored = true;
                }
                $pdo->prepare("UPDATE checkout_order SET status = 'failed', wallet_state = ?, fulfillment_state = ?, updated_at = ? WHERE checkout_id = ?")
                    ->execute([$restored ? 'refunded' : (string) $ck['wallet_state'], substr($reason, 0, 20), time(), $ck['checkout_id']]);
                return $restored ? 'wallet_restored' : 'failed';
            });
        } catch (Throwable $e) {
            \Faoxima\Orders\Checkout\Checkout::log('CHECKOUT_FAILURE_RELEASE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return 'error';
        }
        if (is_callable([\Faoxima\Payments\Fulfillment\Store::class, 'clearCache'])) {
            \Faoxima\Payments\Fulfillment\Store::clearCache(['user']);
        }
        return $out;
    }

    public static function refundUnsecured($runtimePdo, array $report, string $description): string
    {
        if (!is_callable([\Faoxima\Payments\Settlement\Refunds::class, 'paymentOnce'])) {
            return 'failed';
        }
        return \Faoxima\Payments\Settlement\Refunds::paymentOnce($runtimePdo, (string) $report['id_order'], (string) $report['id_user'], $report['price'], $description, null);
    }
}
