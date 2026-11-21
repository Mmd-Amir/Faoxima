<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use PDOException;
use Throwable;
use RuntimeException;

final class DepositMatching
{
    public static function bool($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['true', '1', 'yes'], true)) {
                return true;
            }
            if (in_array($v, ['false', '0', 'no'], true)) {
                return false;
            }
        }
        return null;
    }

    public static function intAmount($value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (is_float($value)) {
            if (!is_finite($value) || $value < 0 || $value > 9.0E15 || floor($value) !== $value) {
                return null;
            }
            return (int) $value;
        }
        if (is_string($value)) {
            $s = trim($value);
            if ($s === '' || strlen($s) > 15 || !ctype_digit($s)) {
                return null;
            }
            return (int) $s;
        }
        return null;
    }

    public static function unitFactor($unit): ?int
    {
        if (!is_string($unit)) {
            return null;
        }
        $u = mb_strtolower(trim($unit), 'UTF-8');
        if (in_array($u, ['rial', 'rials', 'irr', 'ریال'], true)) {
            return 1;
        }
        if (in_array($u, ['toman', 'tomans', 'irt', 'تومان'], true)) {
            return 10;
        }
        return null;
    }

    public static function str($value, int $max): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
    }

    public static function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$key])) {
            return trim((string) $_SERVER[$key]);
        }
        if (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $k => $v) {
                if (strcasecmp((string) $k, $name) === 0) {
                    return trim((string) $v);
                }
            }
        }
        return '';
    }

    public static function ingest($runtimePdo, string $providedKey, string $idempotencyKey, string $raw): array
    {
        $storedKey = \Faoxima\Payments\Receipts\Settings::apiKey($runtimePdo);
        if ($storedKey === '') {
            return [503, ['ok' => false, 'error' => 'not_configured']];
        }
        if ($providedKey === '' || !hash_equals($storedKey, $providedKey)) {
            return [401, ['ok' => false, 'error' => 'unauthorized']];
        }
        $now = time();
        \Faoxima\Payments\Receipts\Settings::settingSet($runtimePdo, 'sbv_last_auth_at', (string) $now);
        if (strlen($raw) > RX_SBV_MAX_BODY) {
            return [413, ['ok' => false, 'error' => 'payload_too_large']];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || array_values($data) === $data) {
            return [400, ['ok' => false, 'error' => 'invalid_json']];
        }
        $eventId = self::str($data['id'] ?? null, 191);
        if ($eventId === null || strlen($eventId) > 191) {
            return [422, ['ok' => false, 'error' => 'invalid_id']];
        }
        if ($idempotencyKey !== '' && !hash_equals($eventId, $idempotencyKey)) {
            return [422, ['ok' => false, 'error' => 'idempotency_key_mismatch']];
        }
        $isTest = self::bool($data['test'] ?? null);
        $isDeposit = self::bool($data['is_deposit'] ?? null);
        if ($isTest === null) {
            return [422, ['ok' => false, 'error' => 'invalid_test_flag']];
        }
        if ($isDeposit === null) {
            return [422, ['ok' => false, 'error' => 'invalid_is_deposit']];
        }
        $amountRaw = $data['amount'] ?? null;
        $amount = self::intAmount($amountRaw);
        $factor = self::unitFactor($data['unit'] ?? null);
        $amountRial = ($amount !== null && $factor !== null) ? $amount * $factor : null;
        if ($isDeposit && !$isTest) {
            if ($factor === null) {
                return [422, ['ok' => false, 'error' => 'unsupported_unit']];
            }
            if ($amount === null || $amount <= 0) {
                return [422, ['ok' => false, 'error' => 'invalid_amount']];
            }
        }
        if (!\Faoxima\Payments\Receipts\Settings::ensureSchema($runtimePdo)) {
            return [503, ['ok' => false, 'error' => 'storage_unavailable']];
        }
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return [503, ['ok' => false, 'error' => 'storage_unavailable']];
        }
        $msRaw = $data['received_at_ms'] ?? null;
        $receivedMs = self::intAmount($msRaw);
        $body = self::str($data['body'] ?? null, 4000);
        $sender = self::str($data['sender'] ?? null, 191);
        $receivedAtRaw = self::str($data['received_at'] ?? null, 64);
        $fingerprint = null;
        if ($body !== null && ($receivedMs !== null || $receivedAtRaw !== null)) {
            $fingerprint = hash('sha256', json_encode([$isTest ? 1 : 0, (string) $sender, $body, $receivedMs !== null ? (string) $receivedMs : (string) $receivedAtRaw], JSON_UNESCAPED_UNICODE));
        }
        try {
            $identityLocks = \Faoxima\Payments\Receipts\ReceiptRetention::identityLock($runtimePdo, $eventId, $fingerprint);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_IDENTITY_LOCK_FAILED', $e->getMessage());
            return [503, ['ok' => false, 'error' => 'storage_unavailable']];
        }
        try {
        if (\Faoxima\Payments\Receipts\ReceiptRetention::deletedIdentityExists($runtimePdo, $eventId, $fingerprint)) {
            return [200, ['ok' => true, 'status' => 'deleted']];
        }
        if ($isTest) {
            $state = 'test';
            $note = 'test_event';
        } elseif (!$isDeposit) {
            $state = 'ignored';
            $note = 'not_deposit';
        } else {
            $state = 'pending';
            $note = null;
        }
        $attempt = self::intAmount($data['attempt'] ?? null);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        try {
            $pdo->prepare(
                "INSERT INTO card_sms_deposit (event_id, fingerprint, is_test, is_deposit, amount_raw, unit, amount_rial, sender, bank, code, body, device, attempt, received_at_raw, received_at_ms, stored_at, state, note, updated_at, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $eventId,
                $fingerprint,
                $isTest ? 1 : 0,
                $isDeposit ? 1 : 0,
                self::str($amountRaw, 64),
                self::str($data['unit'] ?? null, 16),
                $amountRial,
                $sender,
                self::str($data['bank'] ?? null, 191),
                self::str($data['code'] ?? null, 191),
                $body,
                self::str($data['device'] ?? null, 191),
                $attempt,
                $receivedAtRaw,
                $receivedMs,
                $now,
                $state,
                $note,
                $now,
                $payload === false ? null : $payload,
            ]);
            $id = (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                $dup = $pdo->prepare("SELECT id FROM card_sms_deposit WHERE event_id = ? LIMIT 1");
                $dup->execute([$eventId]);
                $dupId = $dup->fetchColumn();
                if ($dupId !== false) {
                    return [200, ['ok' => true, 'status' => 'duplicate', 'id' => (int) $dupId]];
                }
                if ($fingerprint !== null) {
                    $dup = $pdo->prepare("SELECT id FROM card_sms_deposit WHERE fingerprint = ? LIMIT 1");
                    $dup->execute([$fingerprint]);
                    $dupId = $dup->fetchColumn();
                    if ($dupId !== false) {
                        \Faoxima\Payments\Receipts\Settings::log('SBV_DUPLICATE_SMS', 'Same SMS received under a different event id', ['deposit_id' => (int) $dupId]);
                        return [200, ['ok' => true, 'status' => 'duplicate', 'id' => (int) $dupId]];
                    }
                }
            }
            \Faoxima\Payments\Receipts\Settings::log('SBV_INGEST_FAILED', $e->getMessage());
            return [500, ['ok' => false, 'error' => 'storage_failed']];
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_INGEST_FAILED', $e->getMessage());
            return [500, ['ok' => false, 'error' => 'storage_failed']];
        }
        return [200, ['ok' => true, 'status' => 'stored', 'id' => $id, 'state' => $state]];
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_IDENTITY_CHECK_FAILED', $e->getMessage());
            return [503, ['ok' => false, 'error' => 'storage_unavailable']];
        } finally {
            \Faoxima\Payments\Receipts\ReceiptRetention::identityUnlock($runtimePdo, $identityLocks);
        }
    }

    public static function tx($runtimePdo, callable $fn)
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            throw new RuntimeException('pdo_unavailable');
        }
        if ($pdo->inTransaction()) {
            throw new RuntimeException('nested_transaction');
        }
        $pdo->beginTransaction();
        try {
            $out = $fn($pdo);
            $pdo->commit();
            return $out;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Throwable $re) {
                }
            }
            throw $e;
        }
    }

    public static function matchDeposit($runtimePdo, int $depositId): string
    {
        try {
            $result = self::tx($runtimePdo, function (PDO $pdo) use ($depositId) {
                $now = time();
                $stmt = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1 FOR UPDATE");
                $stmt->execute([$depositId]);
                $dep = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($dep === false || (string) $dep['state'] !== 'pending') {
                    return 'skip';
                }
                $mark = function (string $state, ?string $note, ?string $hint = null) use ($pdo, $depositId, $now) {
                    $pdo->prepare("UPDATE card_sms_deposit SET state = ?, note = ?, id_order = ?, updated_at = ? WHERE id = ?")
                        ->execute([$state, $note, $hint, $now, $depositId]);
                    return $state;
                };
                if ((int) $dep['is_test'] === 1 || (int) $dep['is_deposit'] !== 1) {
                    return $mark('ignored', 'not_eligible', null);
                }
                $amount = $dep['amount_rial'] === null ? 0 : (int) $dep['amount_rial'];
                if ($amount <= 0) {
                    return $mark('unmatched', 'invalid_amount', null);
                }
                $storedAt = (int) $dep['stored_at'];
                $cand = $pdo->prepare(
                    "SELECT * FROM card_sms_payment WHERE payable_rial = ? AND state = 'open' AND deposit_id IS NULL AND created_at <= ? AND expires_at >= ? FOR UPDATE"
                );
                $cand->execute([$amount, $storedAt, $storedAt]);
                $rows = $cand->fetchAll(PDO::FETCH_ASSOC);
                if (count($rows) === 0) {
                    $late = $pdo->prepare("SELECT id_order FROM card_sms_payment WHERE payable_rial = ? AND created_at <= ? AND expires_at >= ? ORDER BY id DESC LIMIT 1");
                    $late->execute([$amount, $storedAt, $storedAt - RX_SBV_REUSE_COOLDOWN]);
                    $lateOrder = $late->fetchColumn();
                    if ($lateOrder !== false) {
                        return $mark('late', 'exact_amount_inactive_payment:' . (string) $lateOrder, null);
                    }
                    return $mark('unmatched', 'no_matching_payment', null);
                }
                if (count($rows) > 1) {
                    return $mark('ambiguous', 'multiple_candidates', null);
                }
                $csp = $rows[0];
                $ms = $dep['received_at_ms'] === null ? null : (int) $dep['received_at_ms'];
                if ($ms !== null && $ms > 0 && intdiv($ms, 1000) < (int) $csp['created_at'] - RX_SBV_DEVICE_SKEW) {
                    return $mark('unmatched', 'sms_predates_payment', null);
                }
                $pr = $pdo->prepare("SELECT payment_Status, direct_payment_done, source FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1");
                $pr->execute([(string) $csp['id_order']]);
                $prRow = $pr->fetch(PDO::FETCH_ASSOC);
                if ($prRow === false || (int) ($prRow['direct_payment_done'] ?? 0) === 1 || !in_array((string) $prRow['payment_Status'], ['Unpaid', 'waiting', 'pending'], true)) {
                    return $mark('unmatched', 'order_not_payable', null);
                }
                $upd = $pdo->prepare("UPDATE card_sms_payment SET state = 'matched', deposit_id = ?, received_rial = ?, updated_at = ? WHERE id = ? AND state = 'open' AND deposit_id IS NULL");
                $upd->execute([$depositId, $amount, $now, (int) $csp['id']]);
                if ($upd->rowCount() !== 1) {
                    return $mark('unmatched', 'candidate_changed', null);
                }
                $mark('matched', null, (string) $csp['id_order']);
                return 'matched';
            });
            return $result;
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_MATCH_FAILED', $e->getMessage(), ['deposit_id' => $depositId]);
            return 'error';
        }
    }

    public static function link($runtimePdo, int $depositId, string $orderId, string $actor): array
    {
        try {
            $out = self::tx($runtimePdo, function (PDO $pdo) use ($depositId, $orderId) {
                $now = time();
                $d = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1 FOR UPDATE");
                $d->execute([$depositId]);
                $dep = $d->fetch(PDO::FETCH_ASSOC);
                if ($dep === false) {
                    return ['ok' => false, 'reason' => 'deposit_not_found'];
                }
                if (!in_array((string) $dep['state'], ['unmatched', 'ambiguous', 'late'], true)) {
                    return ['ok' => false, 'reason' => 'deposit_state_' . (string) $dep['state']];
                }
                if ((int) $dep['is_test'] === 1 || (int) $dep['is_deposit'] !== 1 || $dep['amount_rial'] === null) {
                    return ['ok' => false, 'reason' => 'deposit_not_eligible'];
                }
                $p = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $p->execute([$orderId]);
                $pr = $p->fetch(PDO::FETCH_ASSOC);
                if ($pr === false) {
                    return ['ok' => false, 'reason' => 'not_found'];
                }
                $payStatus = (string) ($pr['payment_Status'] ?? '');
                if ($payStatus === 'paid' || (int) ($pr['direct_payment_done'] ?? 0) === 1) {
                    return ['ok' => false, 'reason' => 'already_paid'];
                }
                if (in_array($payStatus, ['processing', 'reconciling'], true)) {
                    return ['ok' => false, 'reason' => 'processing'];
                }
                $c = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $c->execute([$orderId]);
                $csp = $c->fetch(PDO::FETCH_ASSOC);
                if ($csp === false) {
                    return ['ok' => false, 'reason' => 'order_not_verify'];
                }
                $expired = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $pr);
                $cspState = (string) $csp['state'];
                if ($cspState !== 'open' && !($expired && in_array($cspState, ['review', 'closed'], true))) {
                    return ['ok' => false, 'reason' => 'order_state_' . $cspState];
                }
                if ($cspState === 'closed' && !$expired) {
                    return ['ok' => false, 'reason' => 'order_state_closed'];
                }
                if (!$expired && (int) ($csp['expires_at'] ?? 0) < time()) {
                    return ['ok' => false, 'reason' => 'order_state_expired'];
                }
                $allowedStatuses = $expired ? ['Unpaid', 'waiting', 'pending', 'expire'] : ['Unpaid', 'waiting', 'pending'];
                if (!in_array($payStatus, $allowedStatuses, true)) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                if ($csp['deposit_id'] !== null) {
                    return ['ok' => false, 'reason' => 'order_has_deposit'];
                }
                if ((string) $csp['id_user'] !== (string) ($pr['id_user'] ?? '')) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                try {
                    $f = $pdo->prepare("SELECT state, lease_expires_at FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                    $f->execute([$orderId]);
                    $fulfillment = $f->fetch(PDO::FETCH_ASSOC);
                    if (is_array($fulfillment)) {
                        $fulfillmentState = (string) ($fulfillment['state'] ?? '');
                        if ($fulfillmentState === 'completed') {
                            return ['ok' => false, 'reason' => 'already_paid'];
                        }
                        if (in_array($fulfillmentState, ['claimed', 'applying', 'reconciling'], true) && (int) ($fulfillment['lease_expires_at'] ?? 0) > time()) {
                            return ['ok' => false, 'reason' => 'processing'];
                        }
                    }
                } catch (Throwable $e) {
                }
                $pdo->prepare("UPDATE card_sms_payment SET deposit_id = ?, received_rial = ?, updated_at = ? WHERE id = ?")
                    ->execute([$depositId, (int) $dep['amount_rial'], $now, (int) $csp['id']]);
                $pdo->prepare("UPDATE card_sms_deposit SET state = 'linked', id_order = ?, updated_at = ? WHERE id = ?")
                    ->execute([$orderId, $now, $depositId]);
                return ['ok' => true, 'csp' => $csp, 'deposit' => $dep];
            });
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_LINK_FAILED', $e->getMessage(), ['deposit_id' => $depositId, 'id_order' => $orderId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        if (!empty($out['ok'])) {
            \Faoxima\Payments\Receipts\ReceiptOrders::audit($runtimePdo, $orderId, $depositId, $actor, 'admin_link', [
                'principal_toman' => (int) $out['csp']['principal_toman'],
                'fee_toman' => (int) $out['csp']['fee_toman'],
                'expected_rial' => (int) $out['csp']['payable_rial'],
                'received_rial' => (int) $out['deposit']['amount_rial'],
                'outcome' => 'linked',
            ]);
            if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
                \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, $out['csp']['id_user']));
            }
        }
        return $out;
    }

    public static function dismiss($runtimePdo, int $depositId, string $actor, string $reason): array
    {
        if (trim($reason) === '') {
            return ['ok' => false, 'reason' => 'reason_required'];
        }
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        try {
            $stmt = $pdo->prepare("UPDATE card_sms_deposit SET state = 'dismissed', note = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND state IN ('unmatched', 'ambiguous', 'late')");
            $stmt->execute([$reason, $actor, time(), time(), $depositId]);
            if ($stmt->rowCount() !== 1) {
                return ['ok' => false, 'reason' => 'state_changed'];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        \Faoxima\Payments\Receipts\ReceiptOrders::audit($runtimePdo, null, $depositId, $actor, 'deposit_dismiss', ['outcome' => 'dismissed', 'reason' => $reason]);
        return ['ok' => true];
    }

    public static function reviewQueue($runtimePdo, int $offset, int $limit): array
    {
        $pdo = \Faoxima\Payments\Receipts\Settings::pdo($runtimePdo);
        if ($pdo === null || !\Faoxima\Payments\Receipts\Settings::hasSchema($runtimePdo)) {
            return ['rows' => [], 'total' => 0];
        }
        try {
            $total = (int) $pdo->query("SELECT COUNT(*) FROM card_sms_deposit WHERE state IN ('unmatched', 'ambiguous', 'late', 'linked')")->fetchColumn();
            $stmt = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE state IN ('unmatched', 'ambiguous', 'late', 'linked') ORDER BY id DESC LIMIT " . max(1, $limit) . " OFFSET " . max(0, $offset));
            $stmt->execute();
            return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
        } catch (Throwable $e) {
            return ['rows' => [], 'total' => 0];
        }
    }
}
