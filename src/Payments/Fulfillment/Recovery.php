<?php

namespace Faoxima\Payments\Fulfillment;

use PDO;
use Throwable;

final class Recovery
{
    public static function recoverLocked(PDO $pdo, string $orderId, ?string &$alert): array
    {
        $now = time();
        $payment = \Faoxima\Payments\Fulfillment\Store::lockPayment($pdo, $orderId);
        if ($payment === null) {
            return ['status' => 'skip'];
        }
        if (!\Faoxima\Payments\Fulfillment\PaymentPolicy::cronPaymentAllowed($payment, $now)) {
            return ['status' => 'not_eligible', 'reason' => 'cron_window_elapsed'];
        }
        $status = (string) ($payment['payment_Status'] ?? '');
        $paidUndelivered = $status === 'paid' && (int) ($payment['direct_payment_done'] ?? 0) === 0;
        if (!in_array($status, ['processing', 'reconciling'], true) && !$paidUndelivered) {
            return ['status' => 'skip'];
        }
        if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
            \Faoxima\Payments\Fulfillment\Store::markPaidDone($pdo, $orderId);
            return ['status' => 'finalized', 'report' => $payment];
        }
        $record = \Faoxima\Payments\Fulfillment\Store::lockRecord($pdo, $orderId);
        if ($record === null && $paidUndelivered && defined('RX_CRON_INIT_LOADED')) {
            if (($payment['Payment_Method'] ?? '') === 'cart to cart') {
                $stmt = $pdo->prepare("INSERT INTO payment_fulfillment
                    (id_order, payment_report_id, operation_type, state, attempt_count, last_error, created_at, updated_at)
                    VALUES (?, ?, ?, 'manual_review', 0, 'legacy_payment_requires_review', ?, ?)");
                $stmt->execute([$orderId, $payment['id'], Claims::operationType($payment['id_invoice'] ?? ''), $now, $now]);
                $alert = 'legacy_payment_requires_review';
            }
            return ['status' => 'manual_review', 'reason' => 'legacy_payment_requires_review'];
        }
        if ($record === null && $paidUndelivered) {
            $token = \Faoxima\Payments\Fulfillment\Claims::newToken();
            $stmt = $pdo->prepare(
                "INSERT INTO payment_fulfillment (id_order, payment_report_id, operation_type, state, processing_token, lease_expires_at, attempt_count, created_at, updated_at) VALUES (?, ?, ?, 'claimed', ?, ?, 1, ?, ?)"
            );
            $stmt->execute([
                $orderId,
                isset($payment['id']) && is_numeric($payment['id']) ? (int) $payment['id'] : null,
                \Faoxima\Payments\Fulfillment\Claims::operationType($payment['id_invoice'] ?? ''),
                $token,
                $now + RX_PF_LEASE_SECONDS,
                $now,
                $now,
            ]);
            return ['status' => 'claimed', 'token' => $token, 'report' => $payment];
        }
        if ($record === null) {
            $stmt = $pdo->prepare(
                "INSERT INTO payment_fulfillment (id_order, payment_report_id, operation_type, state, attempt_count, last_error, created_at, updated_at) VALUES (?, ?, ?, 'manual_review', 0, ?, ?, ?)"
            );
            $stmt->execute([
                $orderId,
                isset($payment['id']) && is_numeric($payment['id']) ? (int) $payment['id'] : null,
                \Faoxima\Payments\Fulfillment\Claims::operationType($payment['id_invoice'] ?? ''),
                'processing_without_fulfillment_record',
                $now,
                $now,
            ]);
            \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, 'manual_review', ['processing', 'reconciling']);
            $alert = 'processing_without_fulfillment_record';
            return ['status' => 'manual_review'];
        }
        $state = (string) $record['state'];
        if ($state === 'completed') {
            \Faoxima\Payments\Fulfillment\Store::markPaidDone($pdo, $orderId);
            return ['status' => 'finalized', 'report' => $payment];
        }
        if ($state === 'manual_review') {
            \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, 'manual_review', ['processing', 'reconciling']);
            return ['status' => 'manual_review'];
        }
        if ((int) ($record['lease_expires_at'] ?? 0) > $now && in_array($state, ['claimed', 'applying', 'reconciling'], true)) {
            return ['status' => 'busy'];
        }
        if ($state === 'released') {
            if ($paidUndelivered) {
                $token = \Faoxima\Payments\Fulfillment\Claims::newToken();
                \Faoxima\Payments\Fulfillment\Claims::writeClaim($pdo, $record, $orderId, $payment, 'claimed', $token, $now, true);
                return ['status' => 'claimed', 'token' => $token, 'report' => $payment];
            }
            \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, 'waiting', ['processing', 'reconciling']);
            return ['status' => 'released'];
        }
        if ($state === 'failed') {
            $reason = (string) ($record['last_error'] ?? '');
            if ($paidUndelivered) {
                $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'manual_review', lease_expires_at = NULL, updated_at = ? WHERE id_order = ?");
                $stmt->execute([$now, $orderId]);
                $alert = 'paid_fulfillment_failed';
                return ['status' => 'manual_review'];
            }
            \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, 'reject', ['processing', 'reconciling'], $reason !== '' ? $reason : 'fulfillment_failed');
            return ['status' => 'rejected'];
        }
        $token = \Faoxima\Payments\Fulfillment\Claims::newToken();
        if ($state === 'claimed') {
            \Faoxima\Payments\Fulfillment\Claims::writeClaim($pdo, $record, $orderId, $payment, 'claimed', $token, $now, true);
            return ['status' => 'claimed', 'token' => $token, 'report' => $payment];
        }
        if ((int) ($record['attempt_count'] ?? 0) >= RX_PF_MAX_ATTEMPTS) {
            $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'manual_review', last_error = ?, lease_expires_at = NULL, updated_at = ? WHERE id_order = ?");
            $stmt->execute(['max_attempts_exceeded', $now, $orderId]);
            \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, 'manual_review', ['processing', 'reconciling']);
            $alert = 'max_attempts_exceeded';
            return ['status' => 'manual_review'];
        }
        \Faoxima\Payments\Fulfillment\Claims::writeClaim($pdo, $record, $orderId, $payment, 'reconciling', $token, $now, false);
        \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, 'reconciling', ['processing']);
        return ['status' => 'reconcile', 'token' => $token, 'report' => $payment];
    }

    public static function recover($runtimePdo, string $orderId): array
    {
        if (!\Faoxima\Payments\Fulfillment\Claims::validOrderId($orderId)) {
            return ['status' => 'error', 'reason' => 'invalid_order_id'];
        }
        if (!\Faoxima\Payments\Fulfillment\Schema::ensureSchema($runtimePdo)) {
            return ['status' => 'error', 'reason' => 'schema_unavailable'];
        }
        $alert = null;
        try {
            $out = \Faoxima\Payments\Fulfillment\Store::tx($runtimePdo, function (PDO $pdo) use ($orderId, &$alert) {
                return self::recoverLocked($pdo, $orderId, $alert);
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_RECOVER_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['status' => 'error', 'reason' => 'db_error'];
        }
        \Faoxima\Payments\Fulfillment\Store::clearCache();
        if ($alert !== null) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_MANUAL_REVIEW', $alert, ['id_order' => $orderId]);
            \Faoxima\Payments\Fulfillment\Settlement::alertAdmin($orderId, $alert);
        }
        return $out;
    }
}
