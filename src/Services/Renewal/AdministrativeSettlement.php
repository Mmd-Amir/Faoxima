<?php

namespace Faoxima\Services\Renewal;

use PDO;
use Throwable;
use Exception;

final class AdministrativeSettlement
{
    public static function arFinalizeContext(array $d, array $built, array $invoice, array $panel): array
    {
        $q = $built['q'];
        $product = null;
        if (is_array($q['product'])) {
            $product = [
                'id' => (int) $q['product']['id'],
                'code' => (string) $q['product']['code_product'],
                'name' => (string) $q['product']['name_product'],
                'volume' => (string) $q['product']['Volume_constraint'],
                'time' => (string) $q['product']['Service_time'],
            ];
        }
        return [
            'v' => 1,
            'inv' => (string) $invoice['id_invoice'],
            'adm' => (string) $d['adm'],
            'u' => (string) $d['u'],
            'pn' => (string) $panel['name_panel'],
            'uid' => (string) ($invoice['id_user'] ?? ''),
            'bottype' => isset($invoice['bottype']) ? (string) $invoice['bottype'] : null,
            'method' => (string) $built['method_label'],
            'base' => (string) $d['m'],
            'policy' => (string) $built['policy'],
            'mode' => (string) $d['mode'],
            'vt' => (string) ($d['vt'] ?? ''),
            'dt' => (string) ($d['dt'] ?? ''),
            'gb' => $q['gb'],
            'days' => $q['days'],
            'keep_traffic' => $q['keep_traffic'] ? 1 : 0,
            'keep_time' => $q['keep_time'] ? 1 : 0,
            'product' => $product,
            'notify' => !empty($d['nf']) ? 1 : 0,
            'ref' => \Faoxima\Services\Renewal\AdminRenewalFlow::rxArRef($d),
        ];
    }

    public static function arFinalizeOperation($runtimePdo, string $opId): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || !\Faoxima\Services\Renewal\ReservationStore::ensureSchema($runtimePdo)) {
            return ['status' => 'pending', 'notify' => 'none'];
        }
        if ($pdo->inTransaction()) {
            return ['status' => 'pending', 'notify' => 'none'];
        }
        $status = 'pending';
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM renewal_operation WHERE op_id = ? FOR UPDATE");
            $stmt->execute([$opId]);
            $op = $stmt->fetch(PDO::FETCH_ASSOC);
            $ctx = is_array($op) ? \Faoxima\Payments\Fulfillment\Claims::decode($op['finalize_context'] ?? null) : null;
            if (!is_array($op) || (string) $op['source'] !== 'admin' || !is_array($ctx) || $op['finalize_state'] === null) {
                $pdo->rollBack();
                return ['status' => 'skipped', 'notify' => 'none'];
            }
            if ((string) $op['finalize_state'] !== 'pending') {
                $pdo->rollBack();
                $status = (string) $op['finalize_state'];
            } elseif ((string) $op['state'] !== 'verified') {
                $pdo->rollBack();
                return ['status' => 'not_verified', 'notify' => 'none'];
            } else {
                $invStmt = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = ? FOR UPDATE");
                $invStmt->execute([(string) $ctx['inv']]);
                $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($invoice) || (string) $invoice['username'] !== (string) $op['username'] || (string) $invoice['Service_location'] !== (string) $op['name_panel'] || (string) $ctx['inv'] !== (string) $op['id_invoice']) {
                    $mark = $pdo->prepare("UPDATE renewal_operation SET finalize_state = 'invalid', notify_state = 'none', finalize_error = ?, updated_at = ? WHERE op_id = ?");
                    $mark->execute(['invoice_mismatch', time(), $opId]);
                    $pdo->commit();
                    \Faoxima\Payments\Fulfillment\PaymentPolicy::log('ADMIN_RENEWAL_FINALIZE_INVALID', 'invoice does not match operation', ['op_id' => $opId, 'invoice' => $ctx['inv'] ?? null]);
                    return ['status' => 'invalid', 'notify' => 'none'];
                }
                $newer = $pdo->prepare("SELECT COUNT(*) FROM renewal_operation WHERE service_key = ? AND id > ? AND state = 'verified'");
                $newer->execute([(string) $op['service_key'], (int) $op['id']]);
                $superseded = (int) $newer->fetchColumn() > 0;
                if (!$superseded) {
                    $sets = [];
                    $params = [];
                    if (is_array($ctx['product'] ?? null)) {
                        $sets = ['name_product = ?', 'Volume = ?', 'Service_time = ?'];
                        $params = [(string) $ctx['product']['name'], (string) $ctx['product']['volume'], (string) $ctx['product']['time']];
                    } else {
                        if (empty($ctx['keep_traffic'])) {
                            $sets[] = 'Volume = ?';
                            $params[] = \Faoxima\Services\Renewal\AdminRenewalFlow::rxArFormatNumber($ctx['gb']);
                        }
                        if (empty($ctx['keep_time'])) {
                            $sets[] = 'Service_time = ?';
                            $params[] = \Faoxima\Services\Renewal\AdminRenewalFlow::rxArFormatNumber($ctx['days']);
                        }
                    }
                    $sets[] = 'price_product = ?';
                    $params[] = '0';
                    $params[] = (string) $ctx['inv'];
                    $upd = $pdo->prepare("UPDATE invoice SET " . implode(', ', $sets) . " WHERE id_invoice = ?");
                    $upd->execute($params);
                }
                $history = $pdo->prepare("INSERT INTO service_other (id_user, username, value, type, time, price, output, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $history->execute([
                    (string) $ctx['adm'],
                    (string) $op['username'],
                    (!empty($ctx['keep_traffic']) ? 'unchanged' : \Faoxima\Services\Renewal\AdminRenewalFlow::rxArFormatNumber($ctx['gb'])) . '_' . (!empty($ctx['keep_time']) ? 'unchanged' : \Faoxima\Services\Renewal\AdminRenewalFlow::rxArFormatNumber($ctx['days'])),
                    'extend_user_by_admin',
                    date('Y/m/d H:i:s'),
                    '0',
                    (string) json_encode(['op_id' => $opId, 'ref' => $ctx['ref'] ?? null, 'method' => $ctx['method'] ?? null, 'policy' => $ctx['policy'] ?? null, 'invoice_updated' => !$superseded]),
                    'paid',
                ]);
                $done = $pdo->prepare("UPDATE renewal_operation SET finalize_state = 'done', finalized_at = ?, notify_state = ?, finalize_error = NULL, updated_at = ? WHERE op_id = ?");
                $done->execute([time(), !empty($ctx['notify']) && (string) ($ctx['uid'] ?? '') !== '' ? 'pending' : 'none', time(), $opId]);
                $pdo->commit();
                $status = 'done';
            }
        } catch (Throwable $e) {
            $attempts = 1;
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $retry = $pdo->prepare("UPDATE renewal_operation SET finalize_attempts = finalize_attempts + 1, finalize_next = ?, finalize_error = ?, updated_at = ? WHERE op_id = ? AND finalize_state = 'pending'");
                $reason = \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log_clean($e->getMessage(), 200);
                $row = $pdo->prepare("SELECT * FROM renewal_operation WHERE op_id = ? LIMIT 1");
                $row->execute([$opId]);
                $failedOp = $row->fetch(PDO::FETCH_ASSOC);
                $attempts = is_array($failedOp) ? (int) $failedOp['finalize_attempts'] + 1 : 1;
                $retry->execute([time() + (int) min(1800, 120 * pow(2, max(0, $attempts - 1))), $reason, time(), $opId]);
                if (is_array($failedOp)) {
                    \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('admin_finalize', 'finalize_failed', [
                        'panel_name' => (string) $failedOp['name_panel'],
                        'username' => (string) $failedOp['username'],
                        'invoice_id' => (string) $failedOp['id_invoice'],
                        'operation_id' => $opId,
                        'method' => (string) ($failedOp['method'] ?? ''),
                        'policy' => (string) ($failedOp['policy'] ?? ''),
                        'mutation' => 'none',
                        'explanation' => 'attempt=' . $attempts . ' local_finalization_only',
                        'error' => $reason,
                    ]);
                }
            } catch (Throwable $inner) {
                \Faoxima\Payments\Fulfillment\PaymentPolicy::log('ADMIN_RENEWAL_FINALIZE_FAILED', \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log_clean($e->getMessage(), 200), ['op_id' => $opId]);
            }
            return ['status' => 'pending', 'notify' => 'none'];
        }
        $notify = \Faoxima\Services\Renewal\AdminRenewalFlow::rxArNotifyStateOf($opId);
        if ($status === 'done' && in_array($notify, ['pending', 'failed', 'sending'], true)) {
            $notify = \Faoxima\Services\Renewal\AdminRenewalFlow::rxArNotifyOperation($opId);
        }
        return ['status' => $status, 'notify' => $notify];
    }

    public static function arFinalizePending($runtimePdo, int $now, int $limit = 10): array
    {
        $pdo = $runtimePdo;
        $results = [];
        if (!($pdo instanceof PDO) || !\Faoxima\Services\Renewal\ReservationStore::ensureSchema($runtimePdo)) {
            return $results;
        }
        try {
            $exhausted = $pdo->prepare("UPDATE renewal_operation SET notify_state = 'dead', notify_error = 'attempts_exhausted_delivery_unknown', notify_token = NULL, updated_at = ? WHERE source = 'admin' AND finalize_state = 'done' AND notify_state = 'sending' AND notify_claimed_at <= ? AND notify_attempts >= ?");
            $exhausted->execute([$now, $now - RX_AR_NOTIFY_STALE_SECONDS, RX_AR_NOTIFY_MAX_ATTEMPTS]);
            $stmt = $pdo->prepare(
                "SELECT op_id FROM renewal_operation WHERE source = 'admin' AND state = 'verified' AND finalize_state IS NOT NULL AND ("
                . "(finalize_state = 'pending' AND (finalize_next IS NULL OR finalize_next <= ?)) "
                . "OR (finalize_state = 'done' AND notify_attempts < ? AND ("
                . "notify_state = 'pending' "
                . "OR (notify_state = 'failed' AND (notify_next IS NULL OR notify_next <= ?)) "
                . "OR (notify_state = 'sending' AND notify_claimed_at <= ?)))) "
                . "ORDER BY id ASC LIMIT " . (int) $limit
            );
            $stmt->execute([$now, RX_AR_NOTIFY_MAX_ATTEMPTS, $now, $now - RX_AR_NOTIFY_STALE_SECONDS]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $opId) {
                $results[(string) $opId] = self::arFinalizeOperation($runtimePdo, (string) $opId);
            }
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('ADMIN_RENEWAL_FINALIZE_SWEEP_FAILED', \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log_clean($e->getMessage(), 200));
        }
        return $results;
    }

    public static function arRun($managePanel, array $panel, array $invoice, array $d, array $built): array
    {
        $q = $built['q'];
        $options = [
            'source' => 'admin',
            'ref' => \Faoxima\Services\Renewal\AdminRenewalFlow::rxArRef($d),
            'id_user' => $invoice['id_user'],
            'price' => 0,
            'policy' => $built['policy'],
            'finalize' => self::arFinalizeContext($d, $built, $invoice, $panel),
        ];
        try {
            $extend = $managePanel->extend($built['method_label'], $q['gb'], $q['days'], (string) $d['u'], $q['code'], $panel['code_panel'], null, $options);
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('ADMIN_RENEWAL_EXCEPTION', $e->getMessage(), ['username' => (string) $d['u'], 'ref' => $options['ref']]);
            return ['state' => 'failed', 'extend' => ['status' => false, 'code' => 'exception', 'msg' => $e->getMessage()], 'revisable' => false];
        }
        if (!is_array($extend)) {
            return ['state' => 'failed', 'extend' => ['status' => false, 'code' => 'invalid_response', 'msg' => ''], 'revisable' => false];
        }
        if (\Faoxima\Services\Renewal\ProviderRenewal::renewalIsPending($extend)) {
            return ['state' => 'pending', 'extend' => $extend, 'revisable' => false];
        }
        if (empty($extend['status'])) {
            return ['state' => 'failed', 'extend' => $extend, 'revisable' => !empty($extend['no_mutation'])];
        }
        if (empty($extend['rx_renewal']['verified'])) {
            return ['state' => 'pending', 'extend' => $extend, 'revisable' => false];
        }
        return ['state' => 'verified', 'extend' => $extend, 'revisable' => false];
    }
}
