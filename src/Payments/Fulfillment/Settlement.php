<?php

namespace Faoxima\Payments\Fulfillment;

use PDO;
use Throwable;

final class Settlement
{
    public static function prepare($runtimePdo, string $orderId, string $token, ?array $before, ?array $target): bool
    {
        $pdo = $runtimePdo;
        try {
            $now = time();
            $stmt = $pdo->prepare(
                "UPDATE payment_fulfillment SET state = 'applying', before_state = ?, target_state = ?, lease_expires_at = ?, updated_at = ? "
                . "WHERE id_order = ? AND processing_token = ? AND state = 'claimed'"
            );
            $stmt->execute([\Faoxima\Payments\Fulfillment\Claims::json($before), \Faoxima\Payments\Fulfillment\Claims::json($target), $now + RX_PF_LEASE_SECONDS, $now, $orderId, $token]);
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_PREPARE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return false;
        }
    }

    public static function markApplying($runtimePdo, string $orderId, string $token): bool
    {
        $pdo = $runtimePdo;
        try {
            $now = time();
            $stmt = $pdo->prepare(
                "UPDATE payment_fulfillment SET state = 'applying', lease_expires_at = ?, updated_at = ? WHERE id_order = ? AND processing_token = ? AND state = 'reconciling'"
            );
            $stmt->execute([$now + RX_PF_LEASE_SECONDS, $now, $orderId, $token]);
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_APPLY_MARK_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return false;
        }
    }

    public static function complete($runtimePdo, string $orderId, string $token, $resultState = null, array $statements = []): string
    {
        try {
            $status = \Faoxima\Payments\Fulfillment\Store::tx($runtimePdo, function (PDO $pdo) use ($orderId, $token, $resultState, $statements) {
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
                if (!in_array((string) $record['state'], ['claimed', 'applying', 'reconciling'], true)) {
                    return 'invalid_state';
                }
                $payment = \Faoxima\Payments\Fulfillment\Store::lockPayment($pdo, $orderId);
                if ($payment === null) {
                    return 'missing';
                }
                $alreadyDone = (int) ($payment['direct_payment_done'] ?? 0) === 1;
                if (!$alreadyDone) {
                    foreach ($statements as $statement) {
                        $stmt = $pdo->prepare((string) $statement[0]);
                        $stmt->execute(array_values((array) ($statement[1] ?? [])));
                    }
                }
                $stmt = $pdo->prepare(
                    "UPDATE payment_fulfillment SET state = 'completed', result_state = ?, lease_expires_at = NULL, completed_at = ?, updated_at = ? WHERE id_order = ?"
                );
                $stmt->execute([\Faoxima\Payments\Fulfillment\Claims::json($resultState), $now, $now, $orderId]);
                \Faoxima\Payments\Fulfillment\Store::markPaidDone($pdo, $orderId);
                return $alreadyDone ? 'already_completed' : 'completed';
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_COMPLETE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return 'error';
        }
        \Faoxima\Payments\Fulfillment\Store::clearCache(['Payment_report', 'user', 'invoice']);
        return $status;
    }

    public static function transition($runtimePdo, string $orderId, string $token, array $fromStates, string $toState, string $reason, ?string $paymentStatus, bool $renewLease, bool $countAttempt = false): string
    {
        try {
            $out = \Faoxima\Payments\Fulfillment\Store::tx($runtimePdo, function (PDO $pdo) use ($orderId, $token, $fromStates, $toState, $reason, $paymentStatus, $renewLease, $countAttempt) {
                $now = time();
                $record = \Faoxima\Payments\Fulfillment\Store::lockRecord($pdo, $orderId);
                if ($record === null) {
                    return 'missing';
                }
                if (!hash_equals((string) ($record['processing_token'] ?? ''), $token)) {
                    return 'not_owner';
                }
                if (!in_array((string) $record['state'], $fromStates, true)) {
                    return 'state_' . (string) $record['state'];
                }
                $targetState = $toState;
                if ($countAttempt && (int) ($record['attempt_count'] ?? 0) >= RX_PF_MAX_ATTEMPTS) {
                    $targetState = 'manual_review';
                    $paymentStatus = 'manual_review';
                    $reason = 'max_attempts_exceeded: ' . $reason;
                }
                $stmt = $pdo->prepare(
                    "UPDATE payment_fulfillment SET state = ?, last_error = ?, lease_expires_at = ?, attempt_count = attempt_count + ?, updated_at = ? WHERE id_order = ?"
                );
                $stmt->execute([
                    $targetState,
                    function_exists('mb_substr') ? mb_substr($reason, 0, 2000, 'UTF-8') : substr($reason, 0, 2000),
                    $renewLease && $targetState !== 'manual_review' ? $now + RX_PF_LEASE_SECONDS : null,
                    $countAttempt ? 1 : 0,
                    $now,
                    $orderId,
                ]);
                if ($paymentStatus !== null) {
                    \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, $paymentStatus, ['processing', 'reconciling']);
                }
                return $targetState;
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_TRANSITION_FAILED', $e->getMessage(), ['id_order' => $orderId, 'to' => $toState]);
            return 'error';
        }
        \Faoxima\Payments\Fulfillment\Store::clearCache();
        return $out;
    }

    public static function alertAdmin(string $orderId, string $reason, array $ctx = []): void
    {
        try {
            if (!\Faoxima\Core\Database\Db::runtimeReady() || !function_exists('telegram')) {
                return;
            }
            $setting = \Faoxima\Core\Database\Db::select('setting', '*');
            $channel = is_array($setting) ? trim((string) ($setting['Channel_Report'] ?? '')) : '';
            if ($channel === '') {
                return;
            }
            $topic = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'errorreport', 'select');
            $thread = is_array($topic) ? ($topic['idreport'] ?? null) : null;
            $text = "⚠️ <b>پرداخت نیازمند بررسی دستی است</b>\n"
                . "<blockquote>🛒 کد پیگیری پرداخت: <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n"
                . "<blockquote>✍️ دلیل: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</blockquote>\n";
            if (!empty($ctx['username'])) {
                $text .= "<blockquote>🪪 نام کاربری سرویس: <code>" . htmlspecialchars((string) $ctx['username'], ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n";
            }
            $text .= "ℹ️ وضعیت پنل قابل تشخیص قطعی نبود؛ عملیات پنل به‌صورت خودکار تکرار نمی‌شود.";
            telegram('sendmessage', [
                'chat_id' => $channel,
                'message_thread_id' => $thread,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_ALERT_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
    }

    public static function manualReview($runtimePdo, string $orderId, string $token, string $reason, array $ctx = []): array
    {
        $state = self::transition($runtimePdo, $orderId, $token, ['claimed', 'applying', 'reconciling'], 'manual_review', $reason, 'manual_review', false);
        \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_MANUAL_REVIEW', $reason, array_merge(['id_order' => $orderId, 'transition' => $state], $ctx));
        if ($state === 'manual_review') {
            self::alertAdmin($orderId, $reason, $ctx);
            return \Faoxima\Payments\Fulfillment\Claims::result('manual_review', $reason);
        }
        return \Faoxima\Payments\Fulfillment\Claims::result('requires_reconciliation', $reason . ' (' . $state . ')');
    }

    public static function reconcileLater($runtimePdo, string $orderId, string $token, string $reason, bool $countAttempt = false): array
    {
        $state = self::transition($runtimePdo, $orderId, $token, ['claimed', 'applying', 'reconciling'], 'reconciling', $reason, 'reconciling', true, $countAttempt);
        \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_RECONCILE_LATER', $reason, ['id_order' => $orderId, 'transition' => $state]);
        if ($state === 'manual_review') {
            self::alertAdmin($orderId, $reason);
            return \Faoxima\Payments\Fulfillment\Claims::result('manual_review', $reason);
        }
        return \Faoxima\Payments\Fulfillment\Claims::result('requires_reconciliation', $reason);
    }

    public static function defer($runtimePdo, string $orderId, string $token, string $reason): array
    {
        return self::reconcileLater($runtimePdo, $orderId, $token, $reason, true);
    }

    public static function abort($runtimePdo, string $orderId, string $token, string $reason, bool $retryable, array $extra = []): array
    {
        $record = null;
        try {
            $record = \Faoxima\Payments\Fulfillment\Store::fetch($runtimePdo, $orderId);
        } catch (Throwable $e) {
            $record = null;
        }
        if (is_array($record) && (string) $record['state'] === 'reconciling') {
            return $retryable ? self::defer($runtimePdo, $orderId, $token, $reason) : self::manualReview($runtimePdo, $orderId, $token, $reason);
        }
        $abortState = self::transition($runtimePdo, $orderId, $token, ['claimed'], $retryable ? 'released' : 'failed', $reason, null, false);
        if (!$retryable && $abortState === 'failed' && is_callable([\Faoxima\Orders\Checkout\Settlement::class, 'releaseFailedFulfillment'])) {
            \Faoxima\Orders\Checkout\Settlement::releaseFailedFulfillment($runtimePdo, $orderId, 'abort:' . $reason);
        }
        return \Faoxima\Payments\Fulfillment\Claims::result($retryable ? 'retryable' : 'failed', $reason, $extra);
    }

    public static function fail($runtimePdo, string $orderId, string $token, string $reason, ?string $refundOutcome = null): array
    {
        self::transition($runtimePdo, $orderId, $token, ['claimed', 'applying', 'reconciling'], 'failed', $reason, null, false);
        if (is_callable([\Faoxima\Orders\Checkout\Settlement::class, 'releaseFailedFulfillment'])) {
            \Faoxima\Orders\Checkout\Settlement::releaseFailedFulfillment($runtimePdo, $orderId, 'fail:' . $reason);
        }
        return \Faoxima\Payments\Fulfillment\Claims::result($refundOutcome === 'refunded' ? 'refunded' : 'failed', $reason, ['refund' => $refundOutcome]);
    }

    public static function completionOutcome($runtimePdo, string $orderId, string $token, string $completion): array
    {
        if ($completion === 'already_completed') {
            return \Faoxima\Payments\Fulfillment\Claims::result('already_completed', 'finalized_elsewhere');
        }
        if ($completion === 'not_owner' || $completion === 'invalid_state' || $completion === 'missing') {
            return \Faoxima\Payments\Fulfillment\Claims::result('already_processing', 'completion_' . $completion);
        }
        return self::reconcileLater($runtimePdo, $orderId, $token, 'finalize_failed_after_panel_success');
    }

    public static function settle($runtimePdo, string $orderId, ?string $token, array $result, string $releaseTo = 'waiting', string $failedTo = 'waiting'): string
    {
        try {
            $out = \Faoxima\Payments\Fulfillment\Store::tx($runtimePdo, function (PDO $pdo) use ($orderId, $token, $releaseTo, $failedTo) {
                $now = time();
                $payment = \Faoxima\Payments\Fulfillment\Store::lockPayment($pdo, $orderId);
                if ($payment === null) {
                    return 'missing';
                }
                $status = (string) ($payment['payment_Status'] ?? '');
                $record = \Faoxima\Payments\Fulfillment\Store::lockRecord($pdo, $orderId);
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    if (in_array($status, ['processing', 'reconciling'], true)) {
                        \Faoxima\Payments\Fulfillment\Store::markPaidDone($pdo, $orderId);
                        return 'finalized';
                    }
                    return 'paid';
                }
                if ($record === null) {
                    return 'missing';
                }
                $state = (string) $record['state'];
                if ($state === 'completed') {
                    \Faoxima\Payments\Fulfillment\Store::markPaidDone($pdo, $orderId);
                    return 'finalized';
                }
                if ($token !== null && !hash_equals((string) ($record['processing_token'] ?? ''), $token)) {
                    return 'not_owner';
                }
                if ($state === 'claimed' || $state === 'released') {
                    if ($state === 'claimed') {
                        $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'released', lease_expires_at = NULL, updated_at = ? WHERE id_order = ?");
                        $stmt->execute([$now, $orderId]);
                    }
                    \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, $releaseTo, ['processing', 'reconciling']);
                    return 'released';
                }
                if ($state === 'failed') {
                    $reason = (string) ($record['last_error'] ?? '');
                    \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, $failedTo, ['processing', 'reconciling'], $failedTo === 'reject' ? ($reason !== '' ? $reason : 'fulfillment_failed') : null);
                    return $failedTo === 'reject' ? 'rejected' : 'released';
                }
                if ($state === 'applying') {
                    $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'reconciling', lease_expires_at = ?, updated_at = ? WHERE id_order = ?");
                    $stmt->execute([$now + RX_PF_LEASE_SECONDS, $now, $orderId]);
                    \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, 'reconciling', ['processing']);
                    return 'reconciling';
                }
                if ($state === 'reconciling') {
                    \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, 'reconciling', ['processing']);
                    return 'reconciling';
                }
                if ($state === 'manual_review') {
                    \Faoxima\Payments\Fulfillment\Store::setPaymentStatus($pdo, $orderId, 'manual_review', ['processing', 'reconciling']);
                    return 'manual_review';
                }
                return 'state_' . $state;
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_SETTLE_FAILED', $e->getMessage(), ['id_order' => $orderId, 'result' => $result['status'] ?? null]);
            return 'error';
        }
        \Faoxima\Payments\Fulfillment\Store::clearCache();
        return $out;
    }
}
