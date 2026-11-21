<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use Throwable;
use RuntimeException;

final class ReceiptRetention
{
    public static function unlinkDeposit($runtimePdo, string $orderId, string $note): void
    {
        try {
            \Faoxima\Payments\Receipts\DepositMatching::tx($runtimePdo, function (PDO $pdo) use ($orderId, $note) {
                $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $s->execute([$orderId]);
                $csp = $s->fetch(PDO::FETCH_ASSOC);
                if ($csp === false || $csp['deposit_id'] === null || in_array((string) $csp['state'], ['approved', 'credited'], true)) {
                    return;
                }
                $now = time();
                $pdo->prepare("UPDATE card_sms_deposit SET state = 'unmatched', note = ?, id_order = NULL, updated_at = ? WHERE id = ? AND state IN ('matched', 'linked')")
                    ->execute([$note, $now, (int) $csp['deposit_id']]);
                $pdo->prepare("UPDATE card_sms_payment SET deposit_id = NULL, state = IF(state = 'matched', 'open', state), updated_at = ? WHERE id = ?")
                    ->execute([$now, (int) $csp['id']]);
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_UNLINK_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
    }

    public static function identityLock($runtimePdo, string $eventId, ?string $fingerprint): array
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null || $eventId === '') {
            throw new RuntimeException('identity_storage_unavailable');
        }
        $names = ['rx_sbv_id_' . substr(hash('sha256', 'event:' . $eventId), 0, 48)];
        if ($fingerprint !== null && $fingerprint !== '') {
            $names[] = 'rx_sbv_id_' . substr(hash('sha256', 'sms:' . $fingerprint), 0, 48);
        }
        sort($names, SORT_STRING);
        $locked = [];
        try {
            foreach ($names as $name) {
                $stmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
                $stmt->execute([$name]);
                if ((int) $stmt->fetchColumn() !== 1) {
                    throw new RuntimeException('identity_lock_busy');
                }
                $locked[] = $name;
            }
        } catch (Throwable $e) {
            self::identityUnlock($runtimePdo, $locked);
            throw $e;
        }
        return $locked;
    }

    public static function identityUnlock($runtimePdo, array $names): void
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return;
        }
        foreach (array_reverse($names) as $name) {
            try {
                $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->execute([$name]);
            } catch (Throwable $e) {
                \Faoxima\Payments\Receipts\Settings::log('SBV_IDENTITY_UNLOCK_FAILED', $e->getMessage());
            }
        }
    }

    public static function deletedIdentityExists($runtimePdo, string $eventId, ?string $fingerprint): bool
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null || $eventId === '') {
            throw new RuntimeException('identity_storage_unavailable');
        }
        try {
            if ($fingerprint !== null && $fingerprint !== '') {
                $stmt = $pdo->prepare("SELECT id FROM card_sms_audit WHERE action = 'deposit_deleted' AND (id_order = ? OR reason LIKE ?) LIMIT 1");
                $stmt->execute([$eventId, '%"fingerprint":"' . $fingerprint . '"%']);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM card_sms_audit WHERE action = 'deposit_deleted' AND id_order = ? LIMIT 1");
                $stmt->execute([$eventId]);
            }
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_DELETED_IDENTITY_READ_FAILED', $e->getMessage());
            throw $e;
        }
    }

    public static function deleteDeposit($runtimePdo, int $depositId, string $actor): array
    {
        if ($depositId <= 0) {
            return ['ok' => false, 'reason' => 'deposit_not_found'];
        }
        $identityLocks = [];
        try {
            $identityDeposit = \Faoxima\Payments\Receipts\ReceiptOrders::deposit($runtimePdo, $depositId);
            if ($identityDeposit === null) {
                return ['ok' => false, 'reason' => 'deposit_not_found'];
            }
            $identityLocks = self::identityLock($runtimePdo, (string) $identityDeposit['event_id'], $identityDeposit['fingerprint'] !== null ? (string) $identityDeposit['fingerprint'] : null);
            $out = \Faoxima\Payments\Receipts\DepositMatching::tx($runtimePdo, function (PDO $pdo) use ($depositId, $actor) {
                $now = time();
                $d = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1 FOR UPDATE");
                $d->execute([$depositId]);
                $deposit = $d->fetch(PDO::FETCH_ASSOC);
                if ($deposit === false) {
                    return ['ok' => false, 'reason' => 'deposit_not_found'];
                }
                if ((int) $deposit['is_test'] === 1 || (int) $deposit['is_deposit'] !== 1) {
                    return ['ok' => false, 'reason' => 'deposit_not_eligible'];
                }
                if (!in_array((string) $deposit['state'], ['unmatched', 'ambiguous', 'late'], true)) {
                    return ['ok' => false, 'reason' => 'deposit_state_' . (string) $deposit['state']];
                }
                $linkedOrder = trim((string) ($deposit['id_order'] ?? ''));
                if ($linkedOrder !== '') {
                    return ['ok' => false, 'reason' => 'deposit_linked'];
                }
                $c = $pdo->prepare("SELECT id_order FROM card_sms_payment WHERE deposit_id = ? LIMIT 1 FOR UPDATE");
                $c->execute([$depositId]);
                if ($c->fetchColumn() !== false) {
                    return ['ok' => false, 'reason' => 'deposit_linked'];
                }
                $eventId = (string) ($deposit['event_id'] ?? '');
                $fingerprint = $deposit['fingerprint'] !== null ? (string) $deposit['fingerprint'] : null;
                $reason = json_encode([
                    'event_id' => $eventId,
                    'fingerprint' => $fingerprint,
                    'deleted_at' => $now,
                    'admin_id' => $actor,
                    'amount_rial' => $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null,
                    'bank' => (string) ($deposit['bank'] ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $ins = $pdo->prepare("INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, 'deposit_deleted', NULL, NULL, NULL, ?, NULL, 'deleted', ?, ?)");
                $ins->execute([
                    $eventId,
                    $depositId,
                    $actor,
                    $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null,
                    is_string($reason) ? $reason : null,
                    $now,
                ]);
                $del = $pdo->prepare("DELETE FROM card_sms_deposit WHERE id = ? AND state IN ('unmatched', 'ambiguous', 'late') AND (id_order IS NULL OR id_order = '')");
                $del->execute([$depositId]);
                if ($del->rowCount() !== 1) {
                    throw new RuntimeException('sbv_delete:state_changed');
                }
                return ['ok' => true, 'deposit_id' => $depositId];
            });
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (strpos($message, 'sbv_delete:') === 0) {
                return ['ok' => false, 'reason' => substr($message, 11)];
            }
            \Faoxima\Payments\Receipts\Settings::log('SBV_DEPOSIT_DELETE_FAILED', $message, ['deposit_id' => $depositId]);
            return ['ok' => false, 'reason' => 'db_error'];
        } finally {
            self::identityUnlock($runtimePdo, $identityLocks);
        }
        return $out;
    }

    public static function expiredPaymentDeleteGuard($runtimePdo, string $orderId, ?array $csp = null, ?array $report = null, ?int $depositId = null): ?string
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return 'invalid_order';
        }
        $csp = $csp ?? \Faoxima\Payments\Receipts\ReceiptOrders::get($runtimePdo, $orderId);
        if (!is_array($csp)) {
            return 'not_found';
        }
        $report = $report ?? \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($runtimePdo, $orderId);
        if (!is_array($report)) {
            return 'not_found';
        }
        $status = (string) ($report['payment_Status'] ?? '');
        if ($status === 'paid' || (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return 'already_paid';
        }
        if (in_array($status, ['reject', 'cancelled'], true)) {
            return 'already_final';
        }
        if (!in_array($status, ['Unpaid', 'waiting', 'expire'], true)) {
            return 'not_eligible';
        }
        $state = (string) ($csp['state'] ?? '');
        if (in_array($state, ['approved', 'credited'], true)) {
            return 'already_paid';
        }
        if ($csp['deposit_id'] !== null) {
            return 'order_has_deposit';
        }
        if (!\Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $report)) {
            return 'not_expired';
        }
        if ($depositId !== null) {
            if ($depositId <= 0) {
                return 'deposit_not_found';
            }
            $candidateResult = \Faoxima\Payments\Receipts\ReviewQueries::reportCandidates($runtimePdo, $depositId, 1, 0, $orderId);
            if (empty($candidateResult['ok'])) {
                return 'candidate_check_failed';
            }
            $candidateFound = false;
            foreach ((array) ($candidateResult['rows'] ?? []) as $candidateRow) {
                if ((string) ($candidateRow['id_order'] ?? '') === $orderId) {
                    $candidateFound = true;
                    break;
                }
            }
            if (!$candidateFound) {
                return 'order_not_candidate';
            }
        }
        if (is_callable([\Faoxima\Payments\Fulfillment\Store::class, 'fetch'])) {
            try {
                $record = \Faoxima\Payments\Fulfillment\Store::fetch($runtimePdo, $orderId);
                if (is_array($record)) {
                    $recordState = (string) ($record['state'] ?? '');
                    if ($recordState === 'completed') {
                        return 'already_paid';
                    }
                    if (in_array($recordState, ['claimed', 'applying', 'reconciling'], true) && (int) ($record['lease_expires_at'] ?? 0) > time()) {
                        return 'processing';
                    }
                }
            } catch (Throwable $e) {
            }
        }
        return null;
    }

    public static function adminDeleteExpiredPayment($runtimePdo, string $orderId, string $actor, ?int $depositId = null): array
    {
        $orderId = trim($orderId);
        $actor = trim($actor);
        if ($orderId === '') {
            return ['ok' => false, 'reason' => 'invalid_order'];
        }
        if ($actor === '') {
            return ['ok' => false, 'reason' => 'not_authorized'];
        }
        try {
            $out = \Faoxima\Payments\Receipts\DepositMatching::tx($runtimePdo, function (PDO $pdo) use ($runtimePdo, $orderId, $actor, $depositId) {
                $now = time();
                $fail = function (string $reason) {
                    throw new RuntimeException('sbv_expdel:' . $reason);
                };
                $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $s->execute([$orderId]);
                $csp = $s->fetch(PDO::FETCH_ASSOC);
                if (!is_array($csp)) {
                    return ['ok' => false, 'reason' => 'not_found'];
                }
                $r = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $r->execute([$orderId]);
                $report = $r->fetch(PDO::FETCH_ASSOC);
                if (!is_array($report)) {
                    return ['ok' => false, 'reason' => 'not_found'];
                }
                $guard = self::expiredPaymentDeleteGuard($runtimePdo, $orderId, $csp, $report, $depositId);
                if ($guard !== null) {
                    if ($guard === 'already_final' && (string) ($report['payment_Status'] ?? '') === 'cancelled') {
                        return ['ok' => true, 'already_removed' => true, 'order_id' => $orderId, 'user_id' => (string) ($report['id_user'] ?? '')];
                    }
                    return ['ok' => false, 'reason' => $guard];
                }
                try {
                    $f = $pdo->prepare("SELECT state, lease_expires_at FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                    $f->execute([$orderId]);
                    $fulfillment = $f->fetch(PDO::FETCH_ASSOC);
                    if (is_array($fulfillment)) {
                        $fState = (string) ($fulfillment['state'] ?? '');
                        if ($fState === 'completed') {
                            return ['ok' => false, 'reason' => 'already_paid'];
                        }
                        if (in_array($fState, ['claimed', 'applying', 'reconciling'], true) && (int) ($fulfillment['lease_expires_at'] ?? 0) > $now) {
                            return ['ok' => false, 'reason' => 'processing'];
                        }
                    }
                } catch (Throwable $e) {
                    $fail('fulfillment_check_failed');
                }
                $marker = substr('expired_deleted:' . $actor, 0, 190);
                $upd = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'cancelled', dec_not_confirmed = ?, at_updated = ? WHERE id_order = ? AND payment_Status IN ('Unpaid', 'waiting', 'expire') AND COALESCE(direct_payment_done, 0) = 0");
                $upd->execute([$marker, date('Y/m/d H:i:s', $now), $orderId]);
                if ($upd->rowCount() < 1) {
                    $fail('race_lost');
                }
                $cspUpdate = $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = 'cancelled', resolved_by = ?, resolved_at = ?, resolution_note = ?, updated_at = ? WHERE id = ? AND deposit_id IS NULL");
                $cspUpdate->execute([$actor, $now, 'expired_payment_deleted', $now, (int) $csp['id']]);
                if ($cspUpdate->rowCount() !== 1) {
                    $fail('race_lost');
                }
                \Faoxima\Payments\Receipts\ReceiptOrders::releaseAmount($pdo, $csp, false);
                try {
                    $pdo->prepare("INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, 'admin_delete_expired', ?, ?, ?, ?, NULL, 'cancelled', ?, ?)")
                        ->execute([
                            $orderId,
                            $depositId,
                            $actor,
                            (int) $csp['principal_toman'],
                            (int) $csp['fee_toman'],
                            (int) $csp['payable_rial'],
                            $csp['received_rial'] !== null ? (int) $csp['received_rial'] : null,
                            'expired_payment_deleted',
                            $now,
                        ]);
                } catch (Throwable $e) {
                    $fail('audit_failed');
                }
                return ['ok' => true, 'order_id' => $orderId, 'user_id' => (string) ($report['id_user'] ?? ''), 'deposit_id' => $depositId];
            });
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (strpos($message, 'sbv_expdel:') === 0) {
                return ['ok' => false, 'reason' => substr($message, 11)];
            }
            \Faoxima\Payments\Receipts\Settings::log('SBV_EXPIRED_PAYMENT_DELETE_FAILED', $message, ['id_order' => $orderId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        if (empty($out['ok'])) {
            return $out;
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
        }
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
            \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, ($out['user_id'] ?? '')));
        }
        if (method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_release_for_order')) {
            try {
                \Faoxima\Orders\Checkout\Settlement::releaseForOrder($runtimePdo, $orderId, 'cancelled', 'exp_deleted');
            } catch (Throwable $e) {
            }
        }
        return $out;
    }
}
