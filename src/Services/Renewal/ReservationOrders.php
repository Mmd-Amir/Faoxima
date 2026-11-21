<?php

namespace Faoxima\Services\Renewal;

use PDO;
use PDOException;
use Throwable;

final class ReservationOrders
{
    public static function reservationOutstanding($runtimePdo, string $serviceKey, string $username = '', string $namePanel = ''): ?array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || !\Faoxima\Services\Renewal\ReservationStore::ensureSchema($runtimePdo)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE (active_key = ? OR service_key = ?) AND status IN (" . \Faoxima\Services\Renewal\ReservationStore::unresolvedSql() . ") ORDER BY id ASC LIMIT 1");
            $stmt->execute([$serviceKey, $serviceKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false && $username !== '' && $namePanel !== '') {
                $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE username = ? AND name_panel = ? AND status IN (" . \Faoxima\Services\Renewal\ReservationStore::unresolvedSql() . ") ORDER BY id ASC LIMIT 1");
                $stmt->execute([$username, $namePanel]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_LOOKUP_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
            return null;
        }
    }

    public static function reservationByPaymentRef($runtimePdo, string $paymentRef): ?array
    {
        $pdo = $runtimePdo;
        if ($paymentRef === '' || !($pdo instanceof PDO) || !\Faoxima\Services\Renewal\ReservationStore::ensureSchema($runtimePdo)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE payment_ref = ? LIMIT 1");
            $stmt->execute([$paymentRef]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function reservationSummaryForService($runtimePdo, string $username, string $namePanel = '', string $idInvoice = ''): ?array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            if ($idInvoice !== '' && \Faoxima\Services\Renewal\ReservationStore::ensureSchema($runtimePdo)) {
                $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE (service_key = ? OR (username = ? AND name_panel = ?)) AND status IN (" . \Faoxima\Services\Renewal\ReservationStore::unresolvedSql() . ") ORDER BY id ASC LIMIT 1");
                $stmt->execute(['inv:' . $idInvoice, $username, $namePanel]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE username = ? AND status IN (" . \Faoxima\Services\Renewal\ReservationStore::unresolvedSql() . ") ORDER BY id ASC LIMIT 1");
                $stmt->execute([$username]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        if ($row === false) {
            return null;
        }
        $status = (string) ($row['status'] ?? 'pending');
        $trigger = (string) ($row['trigger_type'] ?? 'either');
        $policy = (string) ($row['policy'] ?? 'reset_all');
        $resetUsage = !empty($row['reset_usage']);
        $conditions = \Faoxima\Services\Renewal\ReservationCriteria::reservationRowConditions($row);
        return [
            'id' => (int) $row['id'],
            'status' => $status,
            'status_label' => \Faoxima\Services\Renewal\Reservations::rxReservationStatusLabel($status),
            'trigger' => $trigger,
            'conditions' => $conditions,
            'trigger_at' => isset($row['trigger_at']) && $row['trigger_at'] !== null ? (int) $row['trigger_at'] : null,
            'trigger_label' => \Faoxima\Services\Renewal\Reservations::rxReservationTriggerLabel($trigger, $row['trigger_at'] ?? null, $conditions),
            'policy' => $policy,
            'policy_label' => isset(\Faoxima\Services\Renewal\Plans::renewalPolicies()[$policy]) ? \Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel($policy, $resetUsage) : $policy,
            'reset_usage' => $resetUsage,
            'volume_gb' => (float) ($row['new_limit_gb'] ?? 0),
            'time_days' => (float) ($row['time_day'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_notice', [
                'status' => \Faoxima\Services\Renewal\Reservations::rxReservationStatusLabel($status),
                'trigger' => \Faoxima\Services\Renewal\Reservations::rxReservationTriggerLabel($trigger, $row['trigger_at'] ?? null, $conditions),
                'policy' => isset(\Faoxima\Services\Renewal\Plans::renewalPolicies()[$policy]) ? \Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel($policy, $resetUsage) : $policy,
            ]),
        ];
    }

    public static function reservationRegister($runtimePdo, array $a): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || !\Faoxima\Services\Renewal\ReservationStore::ensureSchema($runtimePdo)) {
            return ['ok' => false, 'code' => 'schema_unavailable'];
        }
        $serviceKey = (string) ($a['service_key'] ?? '');
        $paymentRef = trim((string) ($a['payment_ref'] ?? ''));
        if ($serviceKey === '') {
            return ['ok' => false, 'code' => 'service_identity_missing'];
        }
        try {
            if ($paymentRef !== '') {
                $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE payment_ref = ? LIMIT 1");
                $stmt->execute([$paymentRef]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing !== false) {
                    return ['ok' => true, 'existing' => true, 'id' => (int) $existing['id'], 'row' => $existing];
                }
            }
            $outstanding = self::reservationOutstanding($runtimePdo, $serviceKey, (string) ($a['username'] ?? ''), (string) ($a['name_panel'] ?? ''));
            if ($outstanding !== null) {
                return ['ok' => false, 'code' => 'queued_renewal_exists', 'id' => (int) $outstanding['id']];
            }
            $now = time();
            $stmt = $pdo->prepare(
                "INSERT INTO queued_renewal (id_user, username, name_panel, code_panel, code_product, new_limit_gb, time_day, price, id_invoice, status, bottype, created_at, "
                . "service_key, active_key, trigger_type, conditions, trigger_at, policy, reset_usage, payment_ref, fulfillment, attempt_count, legacy, updated_at) "
                . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)"
            );
            $stmt->execute([
                (string) ($a['id_user'] ?? ''),
                (string) $a['username'],
                (string) $a['name_panel'],
                (string) $a['code_panel'],
                (string) ($a['code_product'] ?? ''),
                (string) ($a['new_limit_gb'] ?? '0'),
                (string) ($a['time_day'] ?? '0'),
                (string) ($a['price'] ?? '0'),
                isset($a['id_invoice']) ? (string) $a['id_invoice'] : null,
                isset($a['bottype']) ? $a['bottype'] : null,
                date('Y/m/d H:i:s', $now),
                $serviceKey,
                $serviceKey,
                (string) $a['trigger'],
                implode(',', \Faoxima\Services\Renewal\ReservationCriteria::reservationNormalizeConditions($a['conditions'] ?? \Faoxima\Services\Renewal\ReservationCriteria::reservationConditionsFromTrigger((string) $a['trigger']))),
                $a['trigger_at'] ?? null,
                (string) $a['policy'],
                !empty($a['reset_usage']) ? 1 : 0,
                $paymentRef !== '' ? substr($paymentRef, 0, 191) : null,
                \Faoxima\Payments\Fulfillment\Claims::json($a['fulfillment'] ?? null),
                $now,
            ]);
            $id = (int) $pdo->lastInsertId();
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_REGISTERED', 'Reservation registered', [
                'reservation_id' => $id,
                'service_key' => $serviceKey,
                'username' => (string) $a['username'],
                'panel' => (string) $a['name_panel'],
                'trigger' => (string) $a['trigger'],
                'conditions' => \Faoxima\Services\Renewal\ReservationCriteria::reservationNormalizeConditions($a['conditions'] ?? []),
                'trigger_at' => $a['trigger_at'] ?? null,
                'policy' => (string) $a['policy'],
                'payment_ref' => $paymentRef,
            ]);
            return ['ok' => true, 'existing' => false, 'id' => $id];
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return ['ok' => false, 'code' => 'queued_renewal_exists'];
            }
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_REGISTER_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
            return ['ok' => false, 'code' => 'queued_renewal_insert_failed', 'msg' => $e->getMessage()];
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RESERVATION_REGISTER_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
            return ['ok' => false, 'code' => 'queued_renewal_insert_failed', 'msg' => $e->getMessage()];
        }
    }

    public static function reservationPreview($runtimePdo, $panel, string $username, ?array $snapshot, $volumeGb, $days, array $opts = []): array
    {
        $now = time();
        $selection = \Faoxima\Services\Renewal\ReservationCriteria::reservationPanelSelection($panel);
        $base = [
            'mode' => 'reservation',
            'selection' => $selection,
            'plan' => null,
        ];
        $ctx = \Faoxima\Services\Renewal\ProviderRenewal::renewalContext($runtimePdo, $panel, $username);
        $stage = (string) ($opts['stage'] ?? 'preview') . '_reservation';
        $logCtx = [
            'panel' => $panel,
            'username' => $username,
            'invoice_id' => (string) (($opts['id_invoice'] ?? '') !== '' ? $opts['id_invoice'] : ($ctx['invoice_id'] ?? '')),
            'invoice_status' => $ctx['invoice_status'] ?? null,
            'method' => (string) ($panel['Methodextend'] ?? ''),
            'policy' => (string) ($selection['policy'] ?? ''),
            'volume_gb' => $volumeGb,
            'days' => $days,
            'snapshot' => is_array($snapshot) ? $snapshot : [],
            'mutation' => 'none',
        ];
        if ($ctx['admin_blocked'] || (is_array($snapshot) && ($snapshot['status'] ?? '') === 'disabled')) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log($stage, 'admin_blocked', $logCtx + ['admin_blocked' => !empty($ctx['admin_blocked'])]);
            return array_merge($base, ['ok' => false, 'blocked' => true, 'reason' => 'admin_blocked', 'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText('admin_blocked')]);
        }
        $serviceKey = \Faoxima\Services\Renewal\ReservationStore::serviceKey((string) ($opts['id_invoice'] ?? self::renewalInvoiceId($runtimePdo, $username, (string) ($panel['name_panel'] ?? ''))), (string) ($panel['code_panel'] ?? ''), $username);
        if (self::reservationOutstanding($runtimePdo, $serviceKey, $username, (string) ($panel['name_panel'] ?? '')) !== null) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log($stage, 'queued_renewal_exists', $logCtx + ['detail' => 'service_key=' . $serviceKey]);
            return array_merge($base, ['ok' => false, 'blocked' => true, 'reason' => 'queued_renewal_exists', 'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText('queued_renewal_exists')]);
        }
        $check = \Faoxima\Services\Renewal\ReservationCriteria::reservationNormalizeSelection($selection, $snapshot, $now);
        if (empty($check['ok'])) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log($stage, (string) $check['reason'], $logCtx + ['detail' => $check['detail'] ?? null]);
            return array_merge($base, ['ok' => false, 'blocked' => true, 'reason' => (string) $check['reason'], 'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText((string) $check['reason'], $check)]);
        }
        $policy = (string) $check['policy'];
        $plan = $snapshot !== null ? \Faoxima\Services\Renewal\ProviderRenewal::renewalPlanForSnapshot($runtimePdo, $snapshot, $panel, $username, $policy, false, $volumeGb, $days, 'رزرو اشتراک', $now, ['quiet' => true]) : null;
        if (is_array($plan) && in_array((string) ($plan['reason'] ?? ''), ['pending_unsupported', 'invalid_purchase', 'panel_policy_unsupported'], true)) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log($stage, (string) $plan['reason'], ['policy' => $policy, 'plan' => $plan, 'admin_blocked' => !empty($ctx['admin_blocked'])] + $logCtx);
            return array_merge($base, ['ok' => false, 'blocked' => true, 'plan' => null, 'reason' => (string) $plan['reason'], 'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText((string) $plan['reason'], $plan)]);
        }
        $lines = [
            \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_title'),
            \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_trigger', ['label' => \Faoxima\Services\Renewal\Reservations::rxReservationActivationText($check['conditions'], $check['trigger_at'])]),
            \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_policy', ['label' => \Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel($policy)]),
            \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_note'),
        ];
        if (is_array($plan) && !empty($plan['ok'])) {
            $lines[] = '';
            $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_estimate');
            $lines = array_merge($lines, \Faoxima\Services\Renewal\RenewalText::rxRenewalPlanLines($plan));
        } else {
            $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_estimate_unavailable');
        }
        return array_merge($base, [
            'ok' => true,
            'blocked' => false,
            'selection' => $check,
            'plan' => is_array($plan) && !empty($plan['ok']) ? $plan : null,
            'reason' => '',
            'text' => implode("\n", $lines),
        ]);
    }

    public static function renewalInvoiceId($runtimePdo, string $username, string $namePanel): string
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || $username === '') {
            return '';
        }
        try {
            $stmt = $pdo->prepare("SELECT id_invoice FROM invoice WHERE username = ? AND Service_location = ? ORDER BY time_sell DESC LIMIT 1");
            $stmt->execute([$username, $namePanel]);
            $id = $stmt->fetchColumn();
            return $id === false ? '' : (string) $id;
        } catch (Throwable $e) {
            return '';
        }
    }

    public static function reservationBackoff(int $attempt): int
    {
        return (int) min(3600, 60 * pow(2, max(0, $attempt - 1)));
    }
}
