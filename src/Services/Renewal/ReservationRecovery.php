<?php

namespace Faoxima\Services\Renewal;

use PDO;
use Throwable;

final class ReservationRecovery
{
    public static function reservationFetch($runtimePdo, int $id): ?array
    {
        $pdo = $runtimePdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function renewalOpsReconcile($runtimePdo, $managePanel, int $now, int $limit = 20): array
    {
        $pdo = $runtimePdo;
        $results = [];
        if (!($pdo instanceof PDO) || !\Faoxima\Services\Renewal\ReservationStore::ensureSchema($runtimePdo)) {
            return $results;
        }
        if (is_callable([\Faoxima\Services\Renewal\AdministrativeSettlement::class, 'arFinalizePending'])) {
            \Faoxima\Services\Renewal\AdministrativeSettlement::arFinalizePending($runtimePdo, $now, 10);
        }
        try {
            $pdo->prepare("UPDATE renewal_operation SET state = 'uncertain', stage = CONCAT(stage, ':interrupted'), updated_at = ? WHERE state = 'applying' AND source NOT IN ('payment', 'reservation') AND updated_at < ?")
                ->execute([$now, $now - RX_RN_LOCK_SECONDS]);
            $stmt = $pdo->prepare("SELECT * FROM renewal_operation WHERE state = 'uncertain' AND source NOT IN ('payment', 'reservation') AND (next_attempt_at IS NULL OR next_attempt_at <= ?) ORDER BY id ASC LIMIT " . (int) $limit);
            $stmt->execute([$now]);
            $ops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RENEWAL_OPS_FETCH_FAILED', $e->getMessage());
            return $results;
        }
        foreach ($ops as $op) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                break;
            }
            $claim = $pdo->prepare("UPDATE renewal_operation SET state = 'reconciling', next_attempt_at = ?, attempt_count = attempt_count + 1, updated_at = ? WHERE id = ? AND state = 'uncertain'");
            $claim->execute([$now + RX_RN_LOCK_SECONDS, $now, (int) $op['id']]);
            if ($claim->rowCount() !== 1) {
                continue;
            }
            $results[] = self::renewalOpReconcileOne($runtimePdo, $managePanel, $op, $now);
        }
        return $results;
    }

    public static function renewalOpSyncInvoiceName(array $op, $target): bool
    {
        if (!in_array((string) ($op['source'] ?? ''), ['wallet', 'miniapp'], true) || !is_array($target)) {
            return false;
        }
        return \Faoxima\Services\Renewal\ProviderRenewal::renewalSyncInvoiceName($op['id_invoice'] ?? '', $target['rx_product_name'] ?? '');
    }

    public static function renewalOpReconcileOne($runtimePdo, $managePanel, array $op, int $now): array
    {
        $opId = (string) $op['op_id'];
        $attempts = (int) $op['attempt_count'] + 1;
        $target = \Faoxima\Payments\Fulfillment\Claims::decode($op['target_state'] ?? null);
        $back = function (string $error) use ($runtimePdo, $opId, $attempts, $now, $op, $target) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('reconcile', $error, [
                'panel_name' => (string) ($op['name_panel'] ?? ''),
                'username' => (string) ($op['username'] ?? ''),
                'invoice_id' => (string) ($op['id_invoice'] ?? ''),
                'method' => (string) ($op['method'] ?? ''),
                'policy' => (string) ($op['policy'] ?? ''),
                'plan' => is_array($target) ? $target : [],
                'before' => \Faoxima\Payments\Fulfillment\Claims::decode($op['before_state'] ?? null),
                'ref' => (string) ($op['ref'] ?? ''),
                'operation_id' => $opId,
                'mutation' => 'uncertain',
                'detail' => 'attempt=' . $attempts . ($attempts >= RX_RN_MAX_ATTEMPTS ? ' manual_review' : ' retry'),
            ]);
            if ($attempts >= RX_RN_MAX_ATTEMPTS) {
                \Faoxima\Services\Renewal\ReservationStore::opUpdate($runtimePdo, $opId, ['state' => 'manual_review', 'last_error' => 'max_attempts_exceeded: ' . $error]);
                \Faoxima\Payments\Fulfillment\Settlement::alertAdmin('renewal:' . $opId, 'تمدید نیازمند بررسی دستی است: ' . $error, ['username' => (string) $op['username']]);
                return ['op_id' => $opId, 'outcome' => 'manual_review', 'reason' => $error, 'op' => $op];
            }
            \Faoxima\Services\Renewal\ReservationStore::opUpdate($runtimePdo, $opId, ['state' => 'uncertain', 'last_error' => $error, 'next_attempt_at' => $now + \Faoxima\Services\Renewal\ReservationOrders::reservationBackoff($attempts)]);
            return ['op_id' => $opId, 'outcome' => 'uncertain', 'reason' => $error, 'op' => $op];
        };
        if (!is_array($target) || empty($target['rx_renewal_plan'])) {
            \Faoxima\Services\Renewal\ReservationStore::opUpdate($runtimePdo, $opId, ['state' => 'manual_review', 'last_error' => 'target_missing']);
            return ['op_id' => $opId, 'outcome' => 'manual_review', 'reason' => 'target_missing', 'op' => $op];
        }
        $serviceKey = (string) $op['service_key'];
        $rxRecoveryLock = \Faoxima\Services\Renewal\ReservationStore::lockAcquireResult($runtimePdo, $serviceKey, $opId);
        if (empty($rxRecoveryLock['ok'])) {
            $rxRecoveryLockReason = !empty($rxRecoveryLock['contended']) ? 'lock_contended' : 'lock_unavailable';
            \Faoxima\Services\Renewal\ReservationStore::opUpdate($runtimePdo, $opId, ['state' => 'uncertain', 'next_attempt_at' => $now + 60, 'last_error' => $rxRecoveryLockReason]);
            return ['op_id' => $opId, 'outcome' => 'busy', 'reason' => $rxRecoveryLockReason, 'op' => $op];
        }
        try {
            $snapshot = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($managePanel, (string) $op['name_panel'], (string) $op['username']);
            $class = \Faoxima\Services\Renewal\Plans::renewalClassify($snapshot, $target);
            if ($class === 'unknown') {
                return $back('panel_read_failed: ' . (string) ($snapshot['error'] ?? ''));
            }
            if ($class === 'ambiguous') {
                \Faoxima\Services\Renewal\ReservationStore::opUpdate($runtimePdo, $opId, ['state' => 'manual_review', 'last_error' => 'panel_state_ambiguous']);
                \Faoxima\Payments\Fulfillment\Settlement::alertAdmin('renewal:' . $opId, 'وضعیت سرویس پس از تمدید نامشخص است و بررسی دستی لازم است', ['username' => (string) $op['username']]);
                return ['op_id' => $opId, 'outcome' => 'manual_review', 'reason' => 'panel_state_ambiguous', 'op' => $op];
            }
            if ($class === 'target') {
                \Faoxima\Services\Renewal\ReservationStore::opUpdate($runtimePdo, $opId, ['state' => 'verified', 'stage' => 'reconciled', 'result_state' => \Faoxima\Services\Fulfillment\ProviderState::limits($snapshot)]);
                if (is_callable([\Faoxima\Services\Renewal\AdministrativeSettlement::class, 'arFinalizeOperation'])) {
                    \Faoxima\Services\Renewal\AdministrativeSettlement::arFinalizeOperation($runtimePdo, $opId);
                }
                $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', (string) $op['name_panel'], 'select');
                if (is_array($panel) && method_exists($managePanel, 'rxRenewalLocalSuccess')) {
                    $managePanel->rxRenewalLocalSuccess($panel, (string) $op['username'], (string) ($op['id_invoice'] ?? ''));
                }
                self::renewalOpSyncInvoiceName($op, $target);
                return ['op_id' => $opId, 'outcome' => 'verified', 'op' => $op, 'after' => \Faoxima\Services\Fulfillment\ProviderState::limits($snapshot)];
            }
            $res = $managePanel->applyStoredRenewalPlan((string) $op['name_panel'], (string) $op['username'], $target, [
                'lock_owner' => $opId,
                'service_key' => $serviceKey,
                'op_id' => $opId,
                'source' => (string) $op['source'],
                'ref' => (string) ($op['ref'] ?? ''),
                'method' => (string) ($op['method'] ?? ''),
                'id_invoice' => (string) ($op['id_invoice'] ?? ''),
            ]);
            if (is_array($res) && !empty($res['status'])) {
                self::renewalOpSyncInvoiceName($op, $target);
                return ['op_id' => $opId, 'outcome' => 'verified', 'op' => $op, 'after' => $res['rx_renewal']['after'] ?? null];
            }
            return $back('reapply_failed: ' . (is_array($res) ? (string) ($res['code'] ?? '') : ''));
        } finally {
            \Faoxima\Services\Renewal\ReservationStore::lockRelease($runtimePdo, $serviceKey, $opId);
        }
    }
}
