<?php

namespace Faoxima\Services\Renewal;

use PDO;

final class AdministrativePlans
{
    public static function arProductEligible($row, string $panelName): bool
    {
        if (!is_array($row) || !isset($row['Location'])) {
            return false;
        }
        if (!is_numeric($row['Volume_constraint'] ?? null) || !is_numeric($row['Service_time'] ?? null) || (float) $row['Volume_constraint'] < 0 || (float) $row['Service_time'] < 0) {
            return false;
        }
        $location = trim((string) $row['Location']);
        if ($location === '/all') {
            return true;
        }
        foreach (explode(',', $location) as $item) {
            if (trim($item) === $panelName) {
                return true;
            }
        }
        return false;
    }

    public static function arEligibleProducts($runtimePdo, string $panelName): array
    {
        $pdo = $runtimePdo;
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(?, Location) > 0 OR Location = '/all') ORDER BY id ASC LIMIT 300");
        $stmt->execute([$panelName]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (self::arProductEligible($row, $panelName) && (string) ($row['name_product'] ?? '') !== 'سرویس تست') {
                $out[] = $row;
            }
        }
        return $out;
    }

    public static function arProduct(array $d): ?array
    {
        if (($d['mode'] ?? '') !== 'product' || (int) ($d['pid'] ?? 0) <= 0) {
            return null;
        }
        $row = \Faoxima\Core\Database\Db::select("product", "*", "id", (int) $d['pid'], "select");
        return self::arProductEligible($row, (string) $d['pn']) ? $row : null;
    }

    public static function arQuantities(array $d): array
    {
        if (($d['mode'] ?? '') === 'product') {
            $product = self::arProduct($d);
            if ($product === null) {
                return ['ok' => false, 'reason' => 'product_missing'];
            }
            return ['ok' => true, 'gb' => (float) $product['Volume_constraint'], 'days' => (float) $product['Service_time'], 'code' => (string) $product['code_product'], 'keep_traffic' => false, 'keep_time' => false, 'product' => $product];
        }
        if (($d['mode'] ?? '') !== 'custom' || ($d['vt'] ?? '') === '' || ($d['dt'] ?? '') === '') {
            return ['ok' => false, 'reason' => 'incomplete'];
        }
        $keepTraffic = $d['vt'] === 'unchanged';
        $keepTime = $d['dt'] === 'unchanged';
        if ($keepTraffic && $keepTime) {
            return ['ok' => false, 'reason' => 'both_unchanged'];
        }
        return [
            'ok' => true,
            'gb' => $d['vt'] === 'finite' ? (float) $d['vg'] : 0.0,
            'days' => $d['dt'] === 'finite' ? (float) $d['dg'] : 0.0,
            'code' => 'custom_volume',
            'keep_traffic' => $keepTraffic,
            'keep_time' => $keepTime,
            'product' => null,
        ];
    }

    public static function arBuild($runtimePdo, $managePanel, array $panel, array $d, ?array $snapshot = null): array
    {
        $now = time();
        $q = self::arQuantities($d);
        if (empty($q['ok'])) {
            return ['ok' => false, 'reason' => (string) $q['reason'], 'text' => \Faoxima\Services\Renewal\AdminRenewalFlow::rxArReasonText((string) $q['reason'])];
        }
        $policy = \Faoxima\Services\Renewal\Plans::renewalAdminPolicy((string) ($d['m'] ?? ''), $q['keep_traffic'], $q['keep_time']);
        $label = \Faoxima\Services\Renewal\RenewalText::rxRenewalAdminMethodLabel((string) ($d['m'] ?? ''));
        if ($policy === null || $label === null) {
            return ['ok' => false, 'reason' => 'policy_invalid', 'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText('policy_invalid')];
        }
        $username = (string) $d['u'];
        if ($snapshot === null) {
            $snapshot = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($managePanel, (string) $panel['name_panel'], $username, (string) $panel['type']);
        }
        if (empty($snapshot['ok'])) {
            return ['ok' => false, 'reason' => 'panel_unreachable', 'text' => \Faoxima\Services\Renewal\AdminRenewalFlow::rxArReasonText('panel_unreachable'), 'snapshot' => $snapshot];
        }
        if (empty($snapshot['exists'])) {
            return ['ok' => false, 'reason' => 'user_missing', 'text' => \Faoxima\Services\Renewal\AdminRenewalFlow::rxArReasonText('user_missing'), 'snapshot' => $snapshot];
        }
        $logCtx = ['stage' => 'admin_preview', 'invoice_id' => (string) $d['inv'], 'ref' => \Faoxima\Services\Renewal\AdminRenewalFlow::rxArRef($d)];
        $base = ['q' => $q, 'policy' => $policy, 'method_label' => $label, 'snapshot' => $snapshot, 'now' => $now];
        $plan = \Faoxima\Services\Renewal\ProviderRenewal::renewalPlanForSnapshot($runtimePdo, $snapshot, $panel, $username, $policy, false, $q['gb'], $q['days'], $label, $now, $logCtx);
        if (empty($plan['ok'])) {
            return $base + ['ok' => false, 'reason' => (string) ($plan['reason'] ?? ''), 'text' => \Faoxima\Services\Renewal\AdminRenewalFlow::rxArReasonText((string) ($plan['reason'] ?? ''), $plan), 'plan' => $plan, 'mode' => 'immediate'];
        }
        return $base + ['ok' => true, 'reason' => '', 'text' => '', 'plan' => $plan, 'mode' => 'immediate'];
    }

    public static function arPlanSignature(array $built): array
    {
        $plan = is_array($built['plan'] ?? null) ? $built['plan'] : [];
        $signature = [
            'mode' => (string) ($built['mode'] ?? ''),
            'policy' => (string) ($built['policy'] ?? ''),
            'limit' => (int) ($plan['data_limit'] ?? 0),
            'reset' => !empty($plan['reset_usage']) ? 1 : 0,
            'pending' => !empty($plan['target_pending']) ? 1 : 0,
            'pduration' => (int) ($plan['target_pending_duration'] ?? 0),
            'expire' => ($plan['time_mode'] ?? '') === 'reset' ? 0 : (int) ($plan['expire'] ?? 0),
        ];
        return $signature;
    }
}
