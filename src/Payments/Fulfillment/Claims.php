<?php

namespace Faoxima\Payments\Fulfillment;

use PDO;
use Throwable;
use RuntimeException;

final class Claims
{
    public static function json($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $json === false ? null : $json;
    }

    public static function decode($raw): ?array
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function int($value): int
    {
        if ($value === null || $value === '' || is_bool($value) || !is_numeric($value)) {
            return 0;
        }
        return (int) round((float) $value);
    }

    public static function operationType($idInvoice): string
    {
        $head = explode('|', (string) $idInvoice)[0];
        return in_array($head, ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'], true) ? $head : 'wallet';
    }

    public static function result(string $status, string $reason = '', array $extra = []): array
    {
        return array_merge([
            'success' => \Faoxima\Services\Fulfillment\Delivery::deliveryCompleted($status),
            'completed' => $status === 'completed',
            'already_completed' => $status === 'already_completed',
            'retryable' => in_array($status, ['retryable', 'already_processing'], true),
            'requires_reconciliation' => $status === 'requires_reconciliation',
            'manual_review' => $status === 'manual_review',
            'status' => $status,
            'reason' => $reason,
            'panel_result' => null,
        ], $extra);
    }

    public static function writeClaim(PDO $pdo, ?array $record, string $orderId, array $payment, string $state, string $token, int $now, bool $resetStates): void
    {
        $lease = $now + RX_PF_LEASE_SECONDS;
        $operation = self::operationType($payment['id_invoice'] ?? '');
        $paymentId = isset($payment['id']) && is_numeric($payment['id']) ? (int) $payment['id'] : null;
        if ($record === null) {
            $stmt = $pdo->prepare(
                "INSERT INTO payment_fulfillment (id_order, payment_report_id, operation_type, state, processing_token, lease_expires_at, attempt_count, created_at, updated_at) "
                . "VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)"
            );
            $stmt->execute([$orderId, $paymentId, $operation, $state, $token, $lease, $now, $now]);
            return;
        }
        if ($resetStates) {
            $stmt = $pdo->prepare(
                "UPDATE payment_fulfillment SET payment_report_id = ?, operation_type = ?, state = ?, processing_token = ?, lease_expires_at = ?, "
                . "attempt_count = attempt_count + 1, before_state = NULL, target_state = NULL, result_state = NULL, last_error = NULL, updated_at = ? WHERE id_order = ?"
            );
            $stmt->execute([$paymentId, $operation, $state, $token, $lease, $now, $orderId]);
            return;
        }
        $stmt = $pdo->prepare(
            "UPDATE payment_fulfillment SET state = ?, processing_token = ?, lease_expires_at = ?, attempt_count = attempt_count + 1, updated_at = ? WHERE id_order = ?"
        );
        $stmt->execute([$state, $token, $lease, $now, $orderId]);
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function validOrderId(string $orderId): bool
    {
        return $orderId !== '' && strlen($orderId) <= 191;
    }

    public static function claim($runtimePdo, string $orderId, array $opts = []): array
    {
        $from = array_values(array_map('strval', (array) ($opts['from'] ?? ['waiting'])));
        $to = (string) ($opts['to'] ?? 'processing');
        $decNote = array_key_exists('dec_not_confirmed', $opts) ? (string) $opts['dec_not_confirmed'] : null;
        $minAge = isset($opts['min_age']) ? (int) $opts['min_age'] : null;
        $maxAge = isset($opts['max_age']) ? (int) $opts['max_age'] : null;
        if (!self::validOrderId($orderId)) {
            return ['status' => 'error', 'reason' => 'invalid_order_id'];
        }
        if (!\Faoxima\Payments\Fulfillment\Schema::ensureSchema($runtimePdo)) {
            return ['status' => 'error', 'reason' => 'schema_unavailable'];
        }
        try {
            $out = \Faoxima\Payments\Fulfillment\Store::tx($runtimePdo, function (PDO $pdo) use ($orderId, $from, $to, $decNote, $minAge, $maxAge) {
                $now = time();
                $payment = \Faoxima\Payments\Fulfillment\Store::lockPayment($pdo, $orderId);
                if ($payment === null) {
                    return ['status' => 'not_found'];
                }
                if (!\Faoxima\Payments\Fulfillment\PaymentPolicy::cronPaymentAllowed($payment, $now)) {
                    return ['status' => 'not_eligible', 'reason' => 'cron_window_elapsed'];
                }
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    return ['status' => 'already_completed', 'report' => $payment];
                }
                $record = \Faoxima\Payments\Fulfillment\Store::lockRecord($pdo, $orderId);
                if ($record !== null) {
                    $state = (string) $record['state'];
                    $leaseActive = (int) ($record['lease_expires_at'] ?? 0) > $now;
                    if ($state === 'completed') {
                        return ['status' => 'already_completed', 'report' => $payment];
                    }
                    if ($state === 'manual_review') {
                        return ['status' => 'manual_review', 'report' => $payment];
                    }
                    if ($state === 'applying' || $state === 'reconciling') {
                        return ['status' => $leaseActive ? 'busy' : 'requires_reconciliation', 'report' => $payment];
                    }
                    if ($state === 'claimed' && $leaseActive) {
                        return ['status' => 'busy', 'report' => $payment];
                    }
                }
                if (!in_array((string) ($payment['payment_Status'] ?? ''), $from, true)) {
                    return ['status' => 'not_eligible', 'reason' => 'status', 'report' => $payment];
                }
                if ($minAge !== null || $maxAge !== null) {
                    $atUpdated = (string) ($payment['at_updated'] ?? '');
                    $ts = $atUpdated === '' ? false : strtotime($atUpdated);
                    if ($ts === false) {
                        return ['status' => 'not_eligible', 'reason' => 'at_updated_invalid', 'report' => $payment];
                    }
                    $age = $now - $ts;
                    if ($minAge !== null && $age <= $minAge) {
                        return ['status' => 'not_eligible', 'reason' => 'delay_not_elapsed', 'report' => $payment];
                    }
                    if ($maxAge !== null && $age >= $maxAge) {
                        return ['status' => 'not_eligible', 'reason' => 'window_elapsed', 'report' => $payment];
                    }
                }
                $token = self::newToken();
                self::writeClaim($pdo, $record, $orderId, $payment, 'claimed', $token, $now, true);
                $sql = "UPDATE Payment_report SET payment_Status = ?, at_updated = ?" . ($decNote !== null ? ", dec_not_confirmed = ?" : "")
                    . " WHERE id_order = ? AND payment_Status = ? AND COALESCE(direct_payment_done, 0) = 0";
                $params = [$to, date('Y/m/d H:i:s', $now)];
                if ($decNote !== null) {
                    $params[] = $decNote;
                }
                $params[] = $orderId;
                $params[] = (string) $payment['payment_Status'];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                if ($stmt->rowCount() < 1) {
                    throw new RuntimeException('claim_status_update_failed');
                }
                $payment['payment_Status'] = $to;
                return ['status' => 'claimed', 'token' => $token, 'report' => $payment];
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_CLAIM_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['status' => 'error', 'reason' => 'db_error', 'error' => $e->getMessage()];
        }
        \Faoxima\Payments\Fulfillment\Store::clearCache();
        return $out;
    }

    public static function begin($runtimePdo, string $orderId, ?string $token): array
    {
        $deny = function (array $result): array {
            return ['proceed' => false, 'result' => $result];
        };
        if (!self::validOrderId($orderId)) {
            return $deny(self::result('failed', 'invalid_order_id'));
        }
        if (!\Faoxima\Payments\Fulfillment\Schema::ensureSchema($runtimePdo)) {
            return $deny(self::result('retryable', 'fulfillment_schema_unavailable'));
        }
        try {
            $out = \Faoxima\Payments\Fulfillment\Store::tx($runtimePdo, function (PDO $pdo) use ($orderId, $token, $deny) {
                $now = time();
                $payment = \Faoxima\Payments\Fulfillment\Store::lockPayment($pdo, $orderId);
                if ($payment === null) {
                    return $deny(self::result('failed', 'payment_not_found'));
                }
                if (!\Faoxima\Payments\Fulfillment\PaymentPolicy::cronPaymentAllowed($payment, $now)) {
                    return $deny(self::result('manual_review', 'cron_window_elapsed'));
                }
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    return $deny(self::result('already_completed', 'direct_payment_done'));
                }
                $record = \Faoxima\Payments\Fulfillment\Store::lockRecord($pdo, $orderId);
                if ($record === null && defined('RX_CRON_INIT_LOADED') && strtolower((string) ($payment['payment_Status'] ?? '')) === 'paid') {
                    return $deny(self::result('manual_review', 'legacy_payment_requires_review'));
                }
                $state = $record !== null ? (string) $record['state'] : '';
                $leaseActive = $record !== null && (int) ($record['lease_expires_at'] ?? 0) > $now;
                if ($state === 'completed') {
                    return $deny(self::result('already_completed', 'fulfillment_completed'));
                }
                if ($state === 'manual_review') {
                    return $deny(self::result('manual_review', 'fulfillment_manual_review'));
                }
                if ($token !== null && $token !== '') {
                    if ($record === null || !hash_equals((string) ($record['processing_token'] ?? ''), $token)) {
                        return $deny(self::result('already_processing', 'not_owner'));
                    }
                    if ($state !== 'claimed' && $state !== 'reconciling') {
                        return $deny(self::result('already_processing', 'state_' . $state));
                    }
                    $stmt = $pdo->prepare("UPDATE payment_fulfillment SET lease_expires_at = ?, updated_at = ? WHERE id_order = ?");
                    $stmt->execute([$now + RX_PF_LEASE_SECONDS, $now, $orderId]);
                    return ['proceed' => true, 'phase' => $state === 'claimed' ? 'fresh' : 'reconcile', 'token' => $token, 'record' => $record];
                }
                if ($leaseActive && in_array($state, ['claimed', 'applying', 'reconciling'], true)) {
                    return $deny(self::result('already_processing', 'lease_active'));
                }
                $newToken = self::newToken();
                if ($state === 'applying' || $state === 'reconciling') {
                    if ((int) ($record['attempt_count'] ?? 0) >= RX_PF_MAX_ATTEMPTS) {
                        $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'manual_review', last_error = ?, lease_expires_at = NULL, updated_at = ? WHERE id_order = ?");
                        $stmt->execute(['max_attempts_exceeded', $now, $orderId]);
                        return $deny(self::result('manual_review', 'max_attempts_exceeded'));
                    }
                    self::writeClaim($pdo, $record, $orderId, $payment, 'reconciling', $newToken, $now, false);
                    $record['state'] = 'reconciling';
                    $record['processing_token'] = $newToken;
                    return ['proceed' => true, 'phase' => 'reconcile', 'token' => $newToken, 'record' => $record];
                }
                self::writeClaim($pdo, $record, $orderId, $payment, 'claimed', $newToken, $now, true);
                return ['proceed' => true, 'phase' => 'fresh', 'token' => $newToken, 'record' => ['state' => 'claimed', 'processing_token' => $newToken]];
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_BEGIN_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return $deny(self::result('retryable', 'fulfillment_claim_failed'));
        }
        return $out;
    }
}
