<?php

namespace Faoxima\Services\Renewal;

use PDO;
use Throwable;
use Exception;

final class ReservationExecution
{
    public static function reservationClaim($runtimePdo, int $id, string $fromStatus, string $token, int $now): bool
    {
        $pdo = $runtimePdo;
        try {
            $stmt = $pdo->prepare(
                "UPDATE queued_renewal SET status = 'processing', processing_token = ?, lease_expires_at = ?, attempt_count = attempt_count + 1, updated_at = ? "
                . "WHERE id = ? AND status = ? AND (next_attempt_at IS NULL OR next_attempt_at <= ?)"
            );
            $stmt->execute([$token, $now + RX_RN_LOCK_SECONDS, $now, $id, $fromStatus, $now]);
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_CLAIM_FAILED', $e->getMessage(), ['reservation_id' => $id]);
            return false;
        }
    }

    public static function reservationTransition($runtimePdo, int $id, string $token, string $status, array $fields = []): bool
    {
        $pdo = $runtimePdo;
        $allowed = ['stage', 'before_state', 'target_state', 'result_state', 'last_error', 'next_attempt_at', 'applied_at', 'output', 'active_key_clear', 'attempt_reset', 'lease'];
        $sets = ['status = ?', 'updated_at = ?'];
        $params = [$status, time()];
        foreach ($fields as $name => $value) {
            if (!in_array($name, $allowed, true)) {
                continue;
            }
            if ($name === 'active_key_clear') {
                if ($value) {
                    $sets[] = 'active_key = NULL';
                }
                continue;
            }
            if ($name === 'attempt_reset') {
                if ($value) {
                    $sets[] = 'attempt_count = 0';
                }
                continue;
            }
            if ($name === 'lease') {
                $sets[] = 'lease_expires_at = ?';
                $params[] = $value;
                continue;
            }
            if (in_array($name, ['before_state', 'target_state', 'result_state'], true)) {
                $value = \Faoxima\Payments\Fulfillment\Claims::json($value);
            } elseif (in_array($name, ['last_error', 'output'], true) && $value !== null) {
                $value = \Faoxima\Services\Renewal\ReservationStore::trimError($value);
            }
            $sets[] = $name . ' = ?';
            $params[] = $value;
        }
        if (!in_array($status, ['processing'], true) && !array_key_exists('lease', $fields)) {
            $sets[] = 'lease_expires_at = NULL';
            $sets[] = 'processing_token = NULL';
        }
        $params[] = $id;
        $params[] = $token;
        try {
            $stmt = $pdo->prepare("UPDATE queued_renewal SET " . implode(', ', $sets) . " WHERE id = ? AND processing_token = ? AND status = 'processing'");
            $stmt->execute($params);
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_TRANSITION_FAILED', $e->getMessage(), ['reservation_id' => $id, 'to' => $status]);
            return false;
        }
    }

    public static function reservationRecoverStale($runtimePdo, int $now): int
    {
        $pdo = $runtimePdo;
        try {
            $stmt = $pdo->prepare(
                "UPDATE queued_renewal SET status = CASE WHEN stage IN ('applying', 'reset_sent', 'modify_sent') THEN 'reconciling' ELSE 'retry' END, "
                . "processing_token = NULL, lease_expires_at = NULL, next_attempt_at = ?, last_error = CONCAT('lease_expired_at_stage:', COALESCE(stage, 'claimed')), updated_at = ? "
                . "WHERE status = 'processing' AND lease_expires_at IS NOT NULL AND lease_expires_at < ?"
            );
            $stmt->execute([$now, $now, $now]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_RECOVER_FAILED', $e->getMessage());
            return 0;
        }
    }

    public static function reservationDueRows($runtimePdo, int $now, int $limit = 30, int $shardWorker = 0, int $shardCount = 1): array
    {
        $pdo = $runtimePdo;
        $shard = $shardCount > 1 ? " AND MOD(id, " . (int) $shardCount . ") = " . (int) $shardWorker . " " : "";
        try {
            $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE status IN ('pending', 'retry', 'reconciling') AND (next_attempt_at IS NULL OR next_attempt_at <= ?) $shard ORDER BY COALESCE(next_attempt_at, 0) ASC, id ASC LIMIT " . (int) $limit);
            $stmt->execute([$now]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_FETCH_FAILED', $e->getMessage());
            return [];
        }
    }

    public static function reservationDefer($runtimePdo, int $id, string $fromStatus, int $nextAt, ?string $error = null): void
    {
        $pdo = $runtimePdo;
        try {
            $stmt = $pdo->prepare("UPDATE queued_renewal SET next_attempt_at = ?, last_error = COALESCE(?, last_error), updated_at = ? WHERE id = ? AND status = ?");
            $stmt->execute([$nextAt, $error !== null ? \Faoxima\Services\Renewal\ReservationStore::trimError($error) : null, time(), $id, $fromStatus]);
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_DEFER_FAILED', $e->getMessage(), ['reservation_id' => $id]);
        }
    }

    public static function reservationFail($runtimePdo, array $row, string $token, string $reason, bool $permanent, int $now, array $extra = []): array
    {
        $attempts = (int) ($row['attempt_count'] ?? 0) + 1;
        if (!$permanent && $attempts >= RX_RN_MAX_ATTEMPTS) {
            $permanent = true;
            $reason = 'max_attempts_exceeded: ' . $reason;
        }
        $status = $permanent ? 'failed' : (string) ($extra['status'] ?? 'retry');
        $fields = array_merge([
            'last_error' => $reason,
            'next_attempt_at' => $permanent ? null : $now + (int) ($extra['delay'] ?? \Faoxima\Services\Renewal\ReservationOrders::reservationBackoff($attempts)),
        ], array_intersect_key($extra, array_flip(['before_state', 'target_state', 'stage'])));
        self::reservationTransition($runtimePdo, (int) $row['id'], $token, $status, $fields);
        \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('reservation_' . ($permanent ? 'failed' : 'retry'), $reason, [
            'panel_name' => (string) $row['name_panel'],
            'username' => (string) $row['username'],
            'invoice_id' => (string) ($row['id_invoice'] ?? ''),
            'method' => 'reservation',
            'policy' => (string) ($row['policy'] ?? ''),
            'volume_gb' => $row['new_limit_gb'] ?? null,
            'days' => $row['time_day'] ?? null,
            'before' => \Faoxima\Payments\Fulfillment\Claims::decode($row['before_state'] ?? null),
            'ref' => 'reservation:' . (int) $row['id'],
            'operation_id' => $token,
            'mutation' => ($extra['status'] ?? '') === 'reconciling' ? 'uncertain' : 'not_determined',
            'detail' => 'attempt=' . $attempts . ' status=' . $status . ' service_key=' . (string) ($row['service_key'] ?? ''),
        ]);
        return ['outcome' => $status, 'reason' => $reason, 'row' => $row];
    }

    public static function reservationProcessRow($runtimePdo, $managePanel, array $row, int $now): array
    {
        $id = (int) $row['id'];
        $from = (string) $row['status'];
        if (!in_array($from, ['pending', 'retry', 'reconciling'], true)) {
            return ['outcome' => 'skipped', 'reason' => 'not_due_state_' . $from, 'row' => $row];
        }
        $panel = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', (string) $row['name_panel'], 'select') : null;
        if (!is_array($panel)) {
            $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'code_panel', (string) $row['code_panel'], 'select');
        }
        $panelType = is_array($panel) ? (string) ($panel['type'] ?? '') : '';
        if ($from === 'pending' && is_array($panel)) {
            $snapshot = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($managePanel, (string) $panel['name_panel'], (string) $row['username'], $panelType);
            if (empty($snapshot['ok'])) {
                self::reservationDefer($runtimePdo, $id, $from, $now + 300, 'panel_read_failed: ' . (string) ($snapshot['error'] ?? ''));
                return ['outcome' => 'waiting', 'reason' => 'panel_read_failed', 'row' => $row];
            }
            if (!empty($snapshot['exists']) && !\Faoxima\Services\Renewal\ReservationCriteria::reservationTriggered($row, $snapshot, $now)) {
                self::reservationDefer($runtimePdo, $id, $from, $now + 240);
                return ['outcome' => 'waiting', 'reason' => 'not_triggered', 'row' => $row];
            }
        }
        $token = \Faoxima\Payments\Fulfillment\Claims::newToken();
        if (!self::reservationClaim($runtimePdo, $id, $from, $token, $now)) {
            return ['outcome' => 'skipped', 'reason' => 'claim_lost', 'row' => $row];
        }
        $row['processing_token'] = $token;
        $serviceKey = (string) ($row['service_key'] ?? '');
        if ($serviceKey === '') {
            $serviceKey = \Faoxima\Services\Renewal\ReservationStore::serviceKey($row['id_invoice'] ?? '', $row['code_panel'] ?? '', $row['username'] ?? '');
        }
        if (!\Faoxima\Services\Renewal\ReservationStore::lockAcquire($runtimePdo, $serviceKey, $token)) {
            self::reservationTransition($runtimePdo, $id, $token, $from, ['next_attempt_at' => $now + 60, 'last_error' => 'service_busy']);
            return ['outcome' => 'busy', 'reason' => 'service_busy', 'row' => $row];
        }
        try {
            return self::reservationExecute($runtimePdo, $managePanel, $row, $panel, $from, $token, $serviceKey, $now);
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_EXCEPTION', $e->getMessage(), ['reservation_id' => $id, 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return self::reservationFail($runtimePdo, $row, $token, 'exception: ' . $e->getMessage(), false, $now, ['status' => 'reconciling']);
        } finally {
            \Faoxima\Services\Renewal\ReservationStore::lockRelease($runtimePdo, $serviceKey, $token);
        }
    }

    public static function reservationExecuteManual($runtimePdo, $managePanel, array $row, array $panel, string $username, string $from, string $token, string $serviceKey, int $now): array
    {
        $id = (int) $row['id'];
        if ($from === 'reconciling') {
            return self::reservationFail($runtimePdo, $row, $token, 'manual_delivery_result_unverifiable', true, $now);
        }
        self::reservationTransition($runtimePdo, $id, $token, 'processing', ['stage' => 'applying', 'lease' => $now + RX_RN_LOCK_SECONDS]);
        $res = $managePanel->extend('ریست حجم و زمان', $row['new_limit_gb'], $row['time_day'], $username, (string) $row['code_product'], (string) $panel['code_panel'], null, ['lock_owner' => $token, 'service_key' => $serviceKey, 'source' => 'reservation', 'ref' => 'reservation:' . $id]);
        if (is_array($res) && !empty($res['status'])) {
            self::reservationTransition($runtimePdo, $id, $token, 'applied', ['stage' => 'done', 'applied_at' => date('Y/m/d H:i:s', $now), 'output' => json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), 'active_key_clear' => true]);
            return ['outcome' => 'applied', 'reason' => 'ok', 'row' => $row, 'result' => $res];
        }
        return self::reservationFail($runtimePdo, $row, $token, 'manual_delivery_failed: ' . (is_array($res) ? (string) ($res['code'] ?? ($res['msg'] ?? '')) : ''), false, $now);
    }

    public static function reservationSettleExecution($runtimePdo, array $row, string $from, string $token, int $now, $res): array
    {
        $id = (int) $row['id'];
        if (is_array($res) && !empty($res['status'])) {
            $after = $res['rx_renewal']['after'] ?? null;
            self::reservationTransition($runtimePdo, $id, $token, 'applied', [
                'stage' => 'done',
                'applied_at' => date('Y/m/d H:i:s', $now),
                'result_state' => $after,
                'output' => json_encode(['status' => true, 'operation_id' => $token, 'after' => $after], JSON_UNESCAPED_UNICODE),
                'active_key_clear' => true,
            ]);
            return ['outcome' => 'applied', 'reason' => 'ok', 'row' => $row, 'before' => $res['rx_renewal']['before'] ?? null, 'after' => $after];
        }
        if (\Faoxima\Services\Renewal\ProviderRenewal::renewalIsPending($res)) {
            return self::reservationFail($runtimePdo, $row, $token, 'result_unverified: ' . (string) ($res['msg'] ?? ''), false, $now, ['status' => 'reconciling']);
        }
        $code = is_array($res) ? (string) ($res['code'] ?? '') : '';
        $msg = is_array($res) ? (is_scalar($res['msg'] ?? null) ? (string) $res['msg'] : json_encode($res['msg'] ?? '')) : (string) $res;
        if ($code === 'admin_blocked') {
            return self::reservationFail($runtimePdo, $row, $token, 'admin_blocked', false, $now, ['status' => 'retry', 'delay' => RX_RN_BLOCKED_RECHECK]);
        }
        if (in_array($code, ['pending_unsupported', 'result_not_usable', 'invalid_purchase', 'unknown_policy'], true)) {
            return self::reservationFail($runtimePdo, $row, $token, 'plan_refused: ' . $code, true, $now);
        }
        return self::reservationFail($runtimePdo, $row, $token, 'apply_failed: ' . ($code !== '' ? $code . ' ' : '') . $msg, false, $now, ['status' => $from === 'reconciling' ? 'reconciling' : 'retry']);
    }

    public static function reservationExecute($runtimePdo, $managePanel, array $row, $panel, string $from, string $token, string $serviceKey, int $now): array
    {
        $id = (int) $row['id'];
        if (!is_array($panel)) {
            return self::reservationFail($runtimePdo, $row, $token, 'panel_not_found', false, $now, ['status' => $from === 'reconciling' ? 'reconciling' : 'retry']);
        }
        $panelType = (string) ($panel['type'] ?? '');
        $username = (string) $row['username'];
        $fulfillment = \Faoxima\Payments\Fulfillment\Claims::decode($row['fulfillment'] ?? null) ?? [];
        if ($panelType === 'Manualsale') {
            return self::reservationExecuteManual($runtimePdo, $managePanel, $row, $panel, $username, $from, $token, $serviceKey, $now);
        }
        $snapshot = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($managePanel, (string) $panel['name_panel'], $username, $panelType);
        if (empty($snapshot['ok'])) {
            return self::reservationFail($runtimePdo, $row, $token, 'panel_read_failed: ' . (string) ($snapshot['error'] ?? ''), false, $now, ['status' => $from === 'reconciling' ? 'reconciling' : 'retry']);
        }
        if (empty($snapshot['exists'])) {
            return self::reservationFail($runtimePdo, $row, $token, 'service_missing_on_panel', true, $now);
        }
        $storedTarget = \Faoxima\Payments\Fulfillment\Claims::decode($row['target_state'] ?? null);
        $plan = null;
        if ($from === 'reconciling' && is_array($storedTarget) && !empty($storedTarget['rx_renewal_plan'])) {
            $class = \Faoxima\Services\Renewal\Plans::renewalClassify($snapshot, $storedTarget);
            if ($class === 'target') {
                self::reservationTransition($runtimePdo, $id, $token, 'applied', ['stage' => 'done', 'applied_at' => date('Y/m/d H:i:s', $now), 'result_state' => \Faoxima\Services\Fulfillment\ProviderState::limits($snapshot), 'output' => 'reconciled', 'active_key_clear' => true]);
                if (method_exists($managePanel, 'rxRenewalLocalSuccess')) {
                    $managePanel->rxRenewalLocalSuccess($panel, $username, (string) ($row['id_invoice'] ?? ''));
                }
                return ['outcome' => 'applied', 'reason' => 'reconciled', 'row' => $row, 'before' => \Faoxima\Payments\Fulfillment\Claims::decode($row['before_state'] ?? null), 'after' => \Faoxima\Services\Fulfillment\ProviderState::limits($snapshot)];
            }
            if ($class === 'ambiguous') {
                return self::reservationFail($runtimePdo, $row, $token, 'panel_state_ambiguous_after_interrupted_activation', true, $now);
            }
            $plan = $storedTarget;
        }
        if ($plan === null) {
            if (!\Faoxima\Services\Renewal\ReservationCriteria::reservationTriggered($row, $snapshot, $now)) {
                self::reservationTransition($runtimePdo, $id, $token, 'pending', ['next_attempt_at' => $now + 240, 'stage' => null, 'attempt_reset' => true]);
                return ['outcome' => 'waiting', 'reason' => 'not_triggered', 'row' => $row];
            }
            $policy = (string) ($row['policy'] ?? 'reset_all');
            $plan = \Faoxima\Services\Renewal\ProviderRenewal::renewalPlanForSnapshot($runtimePdo, $snapshot, $panel, $username, $policy, !empty($row['reset_usage']), $row['new_limit_gb'], $row['time_day'], 'reservation', $now);
            if (empty($plan['ok'])) {
                $reason = (string) ($plan['reason'] ?? 'plan_refused');
                if ($reason === 'admin_blocked') {
                    return self::reservationFail($runtimePdo, $row, $token, 'admin_blocked', false, $now, ['status' => 'retry', 'delay' => RX_RN_BLOCKED_RECHECK]);
                }
                if ($reason === 'panel_state_unavailable') {
                    return self::reservationFail($runtimePdo, $row, $token, $reason, false, $now);
                }
                return self::reservationFail($runtimePdo, $row, $token, 'plan_refused: ' . $reason, true, $now);
            }
            if (!self::reservationTransition($runtimePdo, $id, $token, 'processing', ['stage' => 'applying', 'before_state' => \Faoxima\Services\Fulfillment\ProviderState::limits($snapshot), 'target_state' => $plan, 'lease' => $now + RX_RN_LOCK_SECONDS])) {
                return ['outcome' => 'skipped', 'reason' => 'ownership_lost', 'row' => $row];
            }
        }
        $res = $managePanel->applyStoredRenewalPlan((string) $panel['name_panel'], $username, $plan, [
            'lock_owner' => $token,
            'service_key' => $serviceKey,
            'op_id' => $token,
            'source' => 'reservation',
            'ref' => 'reservation:' . $id,
            'method' => 'reservation:' . (string) ($row['policy'] ?? ''),
            'id_invoice' => (string) ($row['id_invoice'] ?? ''),
            'code_product' => (string) ($row['code_product'] ?? ''),
            'fulfillment' => $fulfillment,
        ]);
        return self::reservationSettleExecution($runtimePdo, $row, $from, $token, $now, $res);
    }

    public static function reservationMarkNotified($runtimePdo, int $id, string $notifiedState): void
    {
        $pdo = $runtimePdo;
        try {
            $stmt = $pdo->prepare("UPDATE queued_renewal SET notified_state = ? WHERE id = ?");
            $stmt->execute([substr($notifiedState, 0, 80), $id]);
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_NOTIFY_MARK_FAILED', $e->getMessage(), ['reservation_id' => $id]);
        }
    }
}
