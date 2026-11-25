<?php

namespace Faoxima\Services\Renewal;

use PDO;
use Throwable;

final class ProviderRenewal
{
    public static function apply($provider, array $panel, array $product, string $username, ?array $target, array $options)
    {
        return $provider->extend(
            $panel['Methodextend'] ?? '', $product['Volume_constraint'], $product['Service_time'],
            $username, $product['code_product'], $panel['code_panel'], $target, $options
        );
    }

    public static function walletRenewalOutcome(mixed $result): string
    {
        if (self::renewalIsPending($result)) {
            return 'pending';
        }
        if (is_array($result) && !empty($result['status'])) {
            return 'applied';
        }
        return is_array($result) && ($result['no_mutation'] ?? false) === true ? 'refundable' : 'uncertain';
    }

    public static function renewalFailureKnown($extend): bool
    {
        $code = is_array($extend) ? (string) ($extend['code'] ?? '') : '';
        if (in_array($code, ['panel_policy_unsupported', 'panel_credit_suspended'], true)) return true;
        return in_array($code, ['admin_blocked', 'pending_unsupported', 'result_not_usable', 'invalid_purchase', 'unknown_renewal_method', 'unsupported_renewal_method', 'unknown_policy', 'panel_state_unavailable', 'panel_read_failed', 'user_missing', 'renewal_in_progress', 'renewal_lock_unavailable', 'renewal_schema_unavailable', 'queued_renewal_exists', 'trigger_unavailable', 'trigger_date_required', 'trigger_date_invalid', 'trigger_date_expired', 'policy_invalid', 'reservation_unsupported'], true);
    }

    public static function renewalIsPending($extend): bool
    {
        return is_array($extend) && !empty($extend['pending_verification']);
    }

    public static function renewalContext($runtimePdo, $panel, string $username): array
    {
        $type = is_array($panel) ? (string) ($panel['type'] ?? '') : '';
        $adminBlocked = false;
        $invoiceId = null;
        $invoiceStatus = null;
        $pdo = $runtimePdo;
        if ($pdo instanceof PDO && is_array($panel) && $username !== '') {
            try {
                $stmt = $pdo->prepare("SELECT id_invoice, Status FROM invoice WHERE username = ? AND Service_location = ? ORDER BY time_sell DESC LIMIT 1");
                $stmt->execute([$username, (string) ($panel['name_panel'] ?? '')]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $invoiceId = (string) $row['id_invoice'];
                    $invoiceStatus = $row['Status'] === null ? null : (string) $row['Status'];
                }
                $adminBlocked = $invoiceStatus === 'disablebyadmin';
            } catch (Throwable $e) {
                $adminBlocked = false;
            }
        }
        return ['pending_supported' => \Faoxima\Services\Renewal\Plans::renewalPendingSupported($type), 'admin_blocked' => $adminBlocked, 'panel_type' => $type, 'invoice_id' => $invoiceId, 'invoice_status' => $invoiceStatus];
    }

    public static function renewalPlanForSnapshot($runtimePdo, array $snapshot, $panel, string $username, string $policy, bool $resetUsage, $volumeGb, $days, string $method = '', ?int $now = null, array $logCtx = []): array
    {
        $ctx = self::renewalContext($runtimePdo, $panel, $username);
        $ctx['method'] = $method;
        $plan = \Faoxima\Services\Renewal\Plans::renewalCompute($now ?? time(), $policy, $resetUsage, $snapshot, $volumeGb, $days, $ctx);
        if (empty($plan['ok']) && empty($logCtx['quiet'])) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log((string) ($logCtx['stage'] ?? 'plan'), (string) ($plan['reason'] ?? 'plan_refused'), array_merge($logCtx, [
                'panel' => $panel,
                'username' => $username,
                'invoice_id' => ($logCtx['invoice_id'] ?? '') !== '' ? $logCtx['invoice_id'] : ($ctx['invoice_id'] ?? null),
                'invoice_status' => $ctx['invoice_status'] ?? null,
                'admin_blocked' => !empty($ctx['admin_blocked']),
                'method' => $method,
                'policy' => $policy,
                'volume_gb' => $volumeGb,
                'days' => $days,
                'snapshot' => $snapshot,
                'plan' => $plan,
                'mutation' => 'none',
                'detail' => isset($plan['unusable']) ? 'unusable=' . $plan['unusable'] : null,
            ]));
        }
        return $plan;
    }

    public static function renewalPreviewForService($runtimePdo, $managePanel, $panel, string $username, $purchasedVolumeGb, $purchasedDays, array $opts = []): array
    {
        if (!is_array($panel) || ($panel['type'] ?? '') === 'Manualsale' || (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel))) {
            return ['ok' => true, 'blocked' => false, 'plan' => null, 'text' => '', 'mode' => 'delivery'];
        }
        $logCtx = [
            'stage' => (string) ($opts['stage'] ?? 'preview'),
            'invoice_id' => (string) ($opts['id_invoice'] ?? ''),
            'ref' => (string) ($opts['ref'] ?? ''),
            'panel' => $panel,
            'username' => $username,
            'method' => (string) ($panel['Methodextend'] ?? ''),
            'volume_gb' => $purchasedVolumeGb,
            'days' => $purchasedDays,
        ];
        $mode = \Faoxima\Services\Renewal\Plans::renewalMode($panel['Methodextend'] ?? '');
        if ($mode === null) {
            $plan = ['ok' => false, 'reason' => 'unknown_renewal_method'];
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log($logCtx['stage'], 'unknown_renewal_method', $logCtx);
            return ['ok' => false, 'blocked' => true, 'plan' => $plan, 'reason' => 'unknown_renewal_method', 'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText('unknown_renewal_method'), 'mode' => 'immediate'];
        }
        $snapshot = is_array($opts['snapshot'] ?? null) ? $opts['snapshot'] : \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($managePanel, (string) ($panel['name_panel'] ?? ''), $username, (string) ($panel['type'] ?? ''));
        if (!empty($snapshot['ok']) && empty($snapshot['exists'])) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log($logCtx['stage'], 'user_missing', $logCtx + ['snapshot_error' => $snapshot['error'] ?? null]);
            return ['ok' => false, 'blocked' => true, 'plan' => null, 'reason' => 'user_missing', 'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText('user_missing'), 'mode' => !empty($mode['queued']) ? 'reservation' : 'immediate'];
        }
        $snapshotOk = !empty($snapshot['ok']);
        if (!empty($mode['queued'])) {
            return \Faoxima\Services\Renewal\ReservationOrders::reservationPreview($runtimePdo, $panel, $username, $snapshotOk ? $snapshot : null, $purchasedVolumeGb, $purchasedDays, $opts);
        }
        if (!$snapshotOk) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log($logCtx['stage'], 'panel_unreachable', $logCtx + ['snapshot' => $snapshot]);
            return ['ok' => false, 'blocked' => false, 'plan' => null, 'reason' => 'panel_unreachable', 'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reason_panel_unreachable'), 'mode' => 'immediate'];
        }
        $plan = self::renewalPlanForSnapshot($runtimePdo, $snapshot, $panel, $username, (string) $mode['policy'], !empty($mode['reset_usage']), $purchasedVolumeGb, $purchasedDays, (string) $mode['method'], null, $logCtx);
        return [
            'ok' => !empty($plan['ok']),
            'blocked' => empty($plan['ok']),
            'plan' => $plan,
            'reason' => (string) ($plan['reason'] ?? ''),
            'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalPreviewText($plan),
            'mode' => 'immediate',
        ];
    }

    public static function renewalIsAppliedVerified($extend, $afterState = null): bool
    {
        if (!is_array($extend) || empty($extend['status']) || self::renewalIsPending($extend)) {
            return false;
        }
        if (!empty($extend['queued']) || !empty($extend['stock']) || !empty($extend['subscription_url']) || !empty($extend['file_ext'])) {
            return false;
        }
        if (is_array($extend['rx_renewal'] ?? null)) {
            $renewal = $extend['rx_renewal'];
            return !empty($renewal['verified']) && is_array($renewal['before'] ?? null) && is_array($renewal['after'] ?? null);
        }
        return !empty($extend['reconciled']) && is_array($afterState);
    }

    public static function renewalSameLinkSupported($panelType): bool
    {
        return in_array((string) $panelType, ['marzban', 'pasarguard'], true);
    }

    public static function renewalSyncInvoiceName($idInvoice, $productName): bool
    {
        $idInvoice = trim((string) $idInvoice);
        $productName = trim((string) $productName);
        if ($idInvoice === '' || $productName === '' || !\Faoxima\Core\Database\Db::runtimeReady() || !\Faoxima\Core\Database\Db::runtimeReady()) {
            return false;
        }
        try {
            $row = \Faoxima\Core\Database\Db::select('invoice', 'name_product', 'id_invoice', $idInvoice, 'select');
            if (!is_array($row) || (string) ($row['name_product'] ?? '') === $productName) {
                return false;
            }
            \Faoxima\Core\Database\Db::update('invoice', 'name_product', $productName, 'id_invoice', $idInvoice);
            return true;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RENEWAL_INVOICE_NAME_SYNC_FAILED', $e->getMessage(), ['id_invoice' => $idInvoice]);
            return false;
        }
    }
}
