<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use Throwable;
use RuntimeException;

final class Rejection
{
    public static function rejectionAuditRow($runtimePdo, string $orderId, ?int $depositId): ?array
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_audit WHERE id_order = ? AND deposit_id <=> ? AND action IN ('admin_reject', 'admin_reject_block') ORDER BY id DESC LIMIT 1");
            $stmt->execute([$orderId, $depositId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function rejectionAuditFind($runtimePdo, string $orderId, ?int $preferredDepositId = null): ?array
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_audit WHERE id_order = ? AND action IN ('admin_reject', 'admin_reject_block') ORDER BY id DESC LIMIT 10");
            $stmt->execute([$orderId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        $fallback = null;
        foreach ($rows as $row) {
            $depositId = $row['deposit_id'] !== null ? (int) $row['deposit_id'] : null;
            if ($preferredDepositId !== null) {
                if ($depositId === $preferredDepositId) {
                    return $row;
                }
                continue;
            }
            if ($depositId !== null) {
                return $row;
            }
            if ($fallback === null) {
                $fallback = $row;
            }
        }
        return $preferredDepositId === null ? $fallback : null;
    }

    public static function adminReject($runtimePdo, string $orderId, string $actor, string $reason, bool $block, ?int $expectedDepositId = null): array
    {
        if (trim($reason) === '') {
            return ['ok' => false, 'reason' => 'reason_required'];
        }
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        $retryPreferred = $expectedDepositId;
        if ($retryPreferred === null) {
            $cspHead = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
            if (is_array($cspHead) && $cspHead['deposit_id'] !== null) {
                $retryPreferred = (int) $cspHead['deposit_id'];
            }
        }
        $existingAudit = self::rejectionAuditFind($runtimePdo, $orderId, $retryPreferred);
        if ($existingAudit !== null) {
            $auditDepositId = $existingAudit['deposit_id'] !== null ? (int) $existingAudit['deposit_id'] : null;
            $retryDepositId = $auditDepositId !== null ? $auditDepositId : $retryPreferred;
            $existingReport = \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
            if (is_array($existingReport) && (string) ($existingReport['payment_Status'] ?? '') === 'reject') {
                $storedReason = trim((string) ($existingAudit['reason'] ?? ''));
                $storedActor = trim((string) ($existingAudit['actor'] ?? ''));
                $decisionAt = (int) ($existingAudit['created_at'] ?? time());
                if ($retryDepositId !== null) {
                    try {
                        $pdo->prepare("UPDATE card_sms_deposit SET state = 'reviewed_rejected', id_order = ?, note = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND state IN ('unmatched', 'ambiguous', 'late', 'linked', 'matched') AND (id_order IS NULL OR id_order = '' OR id_order = ?)")
                            ->execute([$orderId, $storedReason !== '' ? $storedReason : $reason, $storedActor !== '' ? $storedActor : $actor, $decisionAt, time(), $retryDepositId, $orderId]);
                        $auditReceived = $existingAudit['received_rial'] !== null ? (int) $existingAudit['received_rial'] : null;
                        $pdo->prepare("UPDATE card_sms_payment SET deposit_id = ?, received_rial = COALESCE(received_rial, ?), updated_at = ? WHERE id_order = ? AND (deposit_id IS NULL OR deposit_id = ?)")
                            ->execute([$retryDepositId, $auditReceived, time(), $orderId, $retryDepositId]);
                    } catch (Throwable $e) {
                        \Faoxima\Payments\Receipts\Settings::log('SBV_REJECT_DEPOSIT_RECONCILE_FAILED', $e->getMessage(), ['id_order' => $orderId, 'deposit_id' => $retryDepositId]);
                    }
                }
                $delivery = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_send_rejection_report($orderId, $retryDepositId, $storedActor !== '' ? $storedActor : $actor, $storedReason !== '' ? $storedReason : $reason, $decisionAt);
                return [
                    'ok' => true,
                    'already_rejected' => true,
                    'blocked' => false,
                    'notified' => false,
                    'deposit_id' => $retryDepositId,
                    'report_state' => (string) ($delivery['state'] ?? 'failed'),
                    'report' => $delivery,
                ];
            }
        }
        $guard = \Faoxima\Payments\Receipts\ReviewQueries::paymentRejectGuard($runtimePdo, $orderId);
        if ($guard !== null) {
            return ['ok' => false, 'reason' => $guard];
        }
        $depositHint = $expectedDepositId;
        if ($depositHint === null) {
            $hintCsp = \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
            if (is_array($hintCsp) && $hintCsp['deposit_id'] !== null) {
                $depositHint = (int) $hintCsp['deposit_id'];
            }
        }
        $now = time();
        $blocked = false;
        try {
            $out = \Faoxima\Payments\Receipts\DepositMatching::tx($runtimePdo, function (PDO $pdo) use ($orderId, $actor, $reason, $block, $expectedDepositId, $depositHint, $now, &$blocked) {
                $fail = function (string $reason) {
                    throw new RuntimeException('sbv_reject:' . $reason);
                };
                $deposit = null;
                $depositId = $expectedDepositId !== null ? $expectedDepositId : $depositHint;
                $depositLoaded = false;
                $loadDeposit = function () use ($pdo, &$deposit, &$depositId, &$depositLoaded, $orderId, $fail) {
                    if ($depositId === null) {
                        return;
                    }
                    if (!$depositLoaded) {
                        $d = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1 FOR UPDATE");
                        $d->execute([$depositId]);
                        $deposit = $d->fetch(PDO::FETCH_ASSOC);
                        $depositLoaded = true;
                        if ($deposit === false) {
                            $fail('deposit_not_found');
                        }
                    }
                    $depositState = (string) $deposit['state'];
                    if ((int) $deposit['is_test'] === 1 || (int) $deposit['is_deposit'] !== 1 || $deposit['amount_rial'] === null) {
                        $fail('deposit_not_eligible');
                    }
                    if (!in_array($depositState, ['unmatched', 'ambiguous', 'late', 'linked', 'matched'], true)) {
                        $fail('deposit_state_' . $depositState);
                    }
                    $depositOrder = trim((string) ($deposit['id_order'] ?? ''));
                    if ($depositOrder !== '' && $depositOrder !== $orderId) {
                        $fail('deposit_reassigned');
                    }
                    $other = $pdo->prepare("SELECT id_order FROM card_sms_payment WHERE deposit_id = ? AND id_order <> ? LIMIT 1");
                    $other->execute([$depositId, $orderId]);
                    if ($other->fetchColumn() !== false) {
                        $fail('deposit_reassigned');
                    }
                };
                if ($depositId !== null) {
                    $loadDeposit();
                }
                $p = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $p->execute([$orderId]);
                $pr = $p->fetch(PDO::FETCH_ASSOC);
                if ($pr === false) {
                    $fail('not_found');
                }
                $payStatus = (string) ($pr['payment_Status'] ?? '');
                if ($payStatus === 'paid' || (int) ($pr['direct_payment_done'] ?? 0) === 1) {
                    $fail('already_paid');
                }
                if (in_array($payStatus, ['processing', 'reconciling'], true)) {
                    $fail('processing');
                }
                if (in_array($payStatus, ['reject', 'cancelled'], true)) {
                    $fail('already_final');
                }
                if (!in_array($payStatus, ['Unpaid', 'waiting', 'expire'], true)) {
                    $fail('not_eligible');
                }
                $c = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $c->execute([$orderId]);
                $csp = $c->fetch(PDO::FETCH_ASSOC);
                if ($csp === false) {
                    $fail('not_verify');
                }
                $expired = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $pr);
                $cspState = (string) $csp['state'];
                if (!in_array($cspState, ['open', 'matched', 'review'], true) && !($cspState === 'closed' && $expired)) {
                    $fail('not_eligible');
                }
                $cspDepositId = $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null;
                if ($expectedDepositId !== null) {
                    if ($cspDepositId !== null && $cspDepositId !== $expectedDepositId) {
                        $fail('order_has_deposit');
                    }
                    $depositId = $expectedDepositId;
                } else {
                    if ($cspDepositId !== $depositHint) {
                        $fail('deposit_state_changed');
                    }
                    $depositId = $cspDepositId;
                }
                try {
                    $f = $pdo->prepare("SELECT state, lease_expires_at FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                    $f->execute([$orderId]);
                    $fulfillment = $f->fetch(PDO::FETCH_ASSOC);
                    if (is_array($fulfillment)) {
                        $fulfillmentState = (string) ($fulfillment['state'] ?? '');
                        if ($fulfillmentState === 'completed') {
                            $fail('already_paid');
                        }
                        if (in_array($fulfillmentState, ['claimed', 'applying', 'reconciling'], true) && (int) ($fulfillment['lease_expires_at'] ?? 0) > $now) {
                            $fail('processing');
                        }
                    }
                } catch (RuntimeException $e) {
                    throw $e;
                } catch (Throwable $e) {
                }
                $stmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject', dec_not_confirmed = ? WHERE id_order = ? AND payment_Status IN ('Unpaid', 'waiting', 'expire') AND COALESCE(direct_payment_done, 0) = 0");
                $stmt->execute([$reason, $orderId]);
                if ($stmt->rowCount() !== 1) {
                    $fail('race_lost');
                }
                $resolution = $block ? 'rejected_blocked' : 'rejected';
                $depositReceivedRial = $csp['received_rial'] !== null ? (int) $csp['received_rial'] : ($depositId !== null && is_array($deposit) && $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null);
                if ($depositId !== null) {
                    $cspUpdate = $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = ?, resolved_by = ?, resolved_at = ?, resolution_note = ?, deposit_id = ?, received_rial = ?, updated_at = ? WHERE id = ?");
                    $cspUpdate->execute([$resolution, $actor, $now, $reason, $depositId, $depositReceivedRial, $now, (int) $csp['id']]);
                } else {
                    $cspUpdate = $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = ?, resolved_by = ?, resolved_at = ?, resolution_note = ?, deposit_id = NULL, updated_at = ? WHERE id = ?");
                    $cspUpdate->execute([$resolution, $actor, $now, $reason, $now, (int) $csp['id']]);
                }
                if ($cspUpdate->rowCount() !== 1) {
                    $fail('payment_state_changed');
                }
                if ($depositId !== null) {
                    $dst = $pdo->prepare("UPDATE card_sms_deposit SET state = 'reviewed_rejected', id_order = ?, note = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND state IN ('unmatched', 'ambiguous', 'late', 'linked', 'matched') AND (id_order IS NULL OR id_order = '' OR id_order = ?)");
                    $dst->execute([$orderId, $reason, $actor, $now, $now, $depositId, $orderId]);
                    if ($dst->rowCount() !== 1) {
                        $fail('deposit_state_changed');
                    }
                }
                \Faoxima\Payments\Receipts\ReceiptOrders::releaseAmount($pdo, $csp, false);
                if ($block) {
                    try {
                        $pdo->prepare("UPDATE user SET User_Status = 'block', description_blocking = ? WHERE id = ?")->execute([$reason, (string) $pr['id_user']]);
                        $blocked = true;
                    } catch (Throwable $e) {
                        $blocked = false;
                        \Faoxima\Payments\Receipts\Settings::log('SBV_BLOCK_FAILED', $e->getMessage(), ['id_order' => $orderId]);
                    }
                }
                $receivedRial = $depositId !== null ? $depositReceivedRial : ($csp['received_rial'] !== null ? (int) $csp['received_rial'] : (is_array($deposit) && $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null));
                try {
                    $pdo->prepare("INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([
                            $orderId,
                            $depositId,
                            $actor,
                            $block ? 'admin_reject_block' : 'admin_reject',
                            (int) $csp['principal_toman'],
                            (int) $csp['fee_toman'],
                            (int) $csp['payable_rial'],
                            $receivedRial,
                            null,
                            $block ? ($blocked ? 'rejected_blocked' : 'rejected_block_failed') : 'rejected',
                            $reason,
                            $now,
                        ]);
                } catch (Throwable $e) {
                    $fail('audit_failed');
                }
                $pr['payment_Status'] = 'reject';
                $pr['dec_not_confirmed'] = $reason;
                return ['ok' => true, 'report' => $pr, 'csp' => $csp, 'deposit_id' => $depositId, 'decision_at' => $now];
            });
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (strpos($message, 'sbv_reject:') === 0) {
                return ['ok' => false, 'reason' => substr($message, 11)];
            }
            \Faoxima\Payments\Receipts\Settings::log('SBV_REJECT_FAILED', $message, ['id_order' => $orderId, 'deposit_id' => $expectedDepositId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        if (empty($out['ok']) || !is_array($out['report'] ?? null)) {
            return ['ok' => false, 'reason' => (string) ($out['reason'] ?? 'error')];
        }
        $report = $out['report'];
        $depositId = isset($out['deposit_id']) ? (int) $out['deposit_id'] : null;
        $decisionAt = (int) ($out['decision_at'] ?? $now);
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            if ($blocked) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('user');
            }
        }
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
            \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, $report['id_user']));
        }
        $notified = false;
        if (method_exists(\Faoxima\Payments\Receipts\ReceiptActions::class, 'rxReceiptRejectSideEffects')) {
            try {
                $side = \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptRejectSideEffects($report, $orderId, $reason, $actor, false, true);
                $notified = !empty($side['notified']);
            } catch (Throwable $e) {
                \Faoxima\Payments\Receipts\Settings::log('SBV_REJECT_NOTIFY_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            }
        }
        $delivery = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_send_rejection_report($orderId, $depositId, $actor, $reason, $decisionAt);
        return [
            'ok' => true,
            'blocked' => $blocked,
            'notified' => $notified,
            'deposit_id' => $depositId,
            'report_state' => (string) ($delivery['state'] ?? 'failed'),
            'report' => $delivery,
        ];
    }
}
