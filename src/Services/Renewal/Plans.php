<?php

namespace Faoxima\Services\Renewal;

final class Plans
{
    public static function renewalPolicies(): array
    {
        return [
            'carry_all' => ['traffic' => 'carry', 'time' => 'append'],
            'reset_all' => ['traffic' => 'reset', 'time' => 'reset'],
            'reset_traffic_append_time' => ['traffic' => 'reset', 'time' => 'append'],
            'carry_traffic_reset_time' => ['traffic' => 'carry', 'time' => 'reset'],
            'traffic_only' => ['traffic' => 'carry', 'time' => 'keep'],
            'time_only' => ['traffic' => 'keep', 'time' => 'append'],
            'reset_traffic_keep_time' => ['traffic' => 'reset', 'time' => 'keep'],
            'keep_traffic_reset_time' => ['traffic' => 'keep', 'time' => 'reset'],
        ];
    }

    public static function renewalAdminBasePolicies(): array
    {
        return ['carry_all', 'reset_all', 'reset_traffic_append_time', 'carry_traffic_reset_time'];
    }

    public static function renewalAdminPolicy(string $basePolicy, bool $keepTraffic, bool $keepTime): ?string
    {
        $policies = self::renewalPolicies();
        if (!in_array($basePolicy, self::renewalAdminBasePolicies(), true) || !isset($policies[$basePolicy]) || ($keepTraffic && $keepTime)) {
            return null;
        }
        $traffic = $keepTraffic ? 'keep' : $policies[$basePolicy]['traffic'];
        $time = $keepTime ? 'keep' : $policies[$basePolicy]['time'];
        foreach ($policies as $key => $modes) {
            if ($modes['traffic'] === $traffic && $modes['time'] === $time) {
                return $key;
            }
        }
        return null;
    }

    public static function renewalPolicyAllowsUsageReset(string $policy): bool
    {
        $policies = self::renewalPolicies();
        return isset($policies[$policy]) && $policies[$policy]['traffic'] === 'carry';
    }

    public static function renewalModes(): array
    {
        return [
            'ریست حجم و زمان' => ['key' => 'reset_all', 'policy' => 'reset_all', 'reset_usage' => true, 'queued' => false],
            'اضافه شدن زمان و حجم به ماه بعد' => ['key' => 'carry_all', 'policy' => 'carry_all', 'reset_usage' => false, 'queued' => false],
            'ریست زمان و اضافه کردن حجم قبلی' => ['key' => 'carry_traffic_reset_time', 'policy' => 'carry_traffic_reset_time', 'reset_usage' => false, 'queued' => false],
            'ریست شدن حجم و اضافه شدن زمان' => ['key' => 'reset_traffic_append_time', 'policy' => 'reset_traffic_append_time', 'reset_usage' => true, 'queued' => false],
            'اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده' => ['key' => 'carry_all_reset_usage', 'policy' => 'carry_all', 'reset_usage' => true, 'queued' => false],
            'رزرو اشتراک' => ['key' => 'queued', 'policy' => null, 'reset_usage' => false, 'queued' => true],
        ];
    }

    public static function renewalDefaultMethod(): string
    {
        return 'ریست حجم و زمان';
    }

    public static function renewalMode($method): ?array
    {
        $method = trim((string) $method);
        if ($method === '') {
            $method = self::renewalDefaultMethod();
        }
        $modes = self::renewalModes();
        if (!isset($modes[$method])) {
            return null;
        }
        return array_merge($modes[$method], ['method' => $method]);
    }

    public static function renewalIsCustomCode($code): bool
    {
        return in_array(trim((string) $code), ['customvolume', 'custom_volume'], true);
    }

    public static function renewalNormalizeProductCode($code): string
    {
        return self::renewalIsCustomCode($code) ? 'custom_volume' : trim((string) $code);
    }

    public static function renewalPendingSupported($panelType): bool
    {
        return in_array((string) $panelType, ['marzban', 'pasarguard', 'rebecca', 'x-ui_single'], true);
    }

    public static function renewalCompute(int $now, string $policy, bool $resetUsageOption, array $before, $purchasedVolumeGb, $purchasedDays, array $ctx = []): array
    {
        $fail = function (string $reason, array $extra = []) use ($policy) {
            return array_merge([
                'ok' => false,
                'reason' => $reason,
                'policy' => $policy,
                'queued' => false,
                'manual_review' => false,
                'destructive' => false,
                'rx_renewal_plan' => 1,
            ], $extra);
        };
        $policies = self::renewalPolicies();
        if (!isset($policies[$policy])) {
            return $fail('unknown_policy');
        }
        if (!is_numeric($purchasedVolumeGb) || !is_numeric($purchasedDays) || (float) $purchasedVolumeGb < 0 || (float) $purchasedDays < 0) {
            return $fail('invalid_purchase');
        }
        foreach (['data_limit', 'expire', 'used_traffic'] as $field) {
            if (!array_key_exists($field, $before) || $before[$field] === null || is_bool($before[$field]) || !is_numeric($before[$field])) {
                return $fail('panel_state_unavailable');
            }
        }
        $trafficMode = $policies[$policy]['traffic'];
        $timeMode = $policies[$policy]['time'];
        $volume = (float) $purchasedVolumeGb;
        $days = (float) $purchasedDays;
        $purchasedBytes = (int) round($volume * RX_PF_GIB);
        $purchasedSeconds = (int) round($days * 86400);
        $oldLimit = max(0, (int) round((float) $before['data_limit']));
        $oldExpire = max(0, (int) round((float) $before['expire']));
        $used = max(0, (int) round((float) $before['used_traffic']));
        $status = strtolower(trim((string) ($before['status'] ?? '')));
        $pending = !empty($before['pending']) || $status === 'on_hold';
        $pendingDuration = isset($before['pending_duration']) && is_numeric($before['pending_duration']) && (int) $before['pending_duration'] > 0 ? (int) $before['pending_duration'] : null;
        $unlimitedTraffic = $oldLimit <= 0;
        $unlimitedTime = !$pending && $oldExpire <= 0;
        $remaining = $unlimitedTraffic ? null : max(0, $oldLimit - $used);
        if ($status === 'disabled' || !empty($ctx['admin_blocked'])) {
            return $fail('admin_blocked');
        }

        $resetUsage = false;
        if ($trafficMode === 'keep') {
            $targetLimit = $oldLimit;
            $targetUsed = $used;
        } elseif ($volume == 0) {
            $resetUsage = $trafficMode === 'reset' || $resetUsageOption;
            $targetLimit = 0;
            $targetUsed = $resetUsage ? 0 : $used;
        } elseif ($trafficMode === 'reset') {
            $resetUsage = true;
            $targetLimit = $purchasedBytes;
            $targetUsed = 0;
        } elseif ($unlimitedTraffic) {
            $resetUsage = $resetUsageOption;
            $targetLimit = 0;
            $targetUsed = $resetUsage ? 0 : $used;
        } elseif ($resetUsageOption) {
            $resetUsage = true;
            $targetLimit = (int) $remaining + $purchasedBytes;
            $targetUsed = 0;
        } else {
            $targetLimit = $used + (int) $remaining + $purchasedBytes;
            $targetUsed = $used;
        }

        $targetPending = false;
        $targetPendingDuration = null;
        if ($timeMode === 'keep') {
            $targetExpire = $oldExpire;
            $targetPending = $pending;
            $targetPendingDuration = $pendingDuration;
        } elseif ($days == 0) {
            $targetExpire = 0;
        } elseif ($pending) {
            if (empty($ctx['pending_supported'])) {
                return $fail('pending_unsupported');
            }
            if ($timeMode === 'append' && $pendingDuration === null) {
                return $fail('pending_unsupported');
            }
            $targetPending = true;
            $targetExpire = 0;
            $targetPendingDuration = $timeMode === 'reset' ? $purchasedSeconds : (int) $pendingDuration + $purchasedSeconds;
        } elseif ($timeMode === 'reset') {
            $targetExpire = $now + $purchasedSeconds;
        } elseif ($unlimitedTime) {
            $targetExpire = 0;
        } else {
            $targetExpire = max($now, $oldExpire) + $purchasedSeconds;
        }

        $trafficUsable = $targetLimit <= 0 || ($targetLimit - $targetUsed) > 0;
        $timeUsable = $targetPending || $targetExpire <= 0 || $targetExpire > $now;
        if (!$trafficUsable || !$timeUsable) {
            return $fail('result_not_usable', ['unusable' => !$trafficUsable ? 'traffic' : 'time']);
        }
        if ($trafficMode === 'carry' && $targetLimit > 0 && ($targetLimit - $targetUsed) < (int) $remaining + $purchasedBytes) {
            return $fail('invariant_traffic_discarded');
        }
        if ($timeMode === 'append' && !$targetPending && $targetExpire > 0 && $oldExpire > $now && $targetExpire < $oldExpire + $purchasedSeconds) {
            return $fail('invariant_time_discarded');
        }
        if (($ctx['panel_type'] ?? '') === 'jovadpanel') {
            if ($status === 'credit_suspended' || ($before['raw']['paused_by'] ?? '') === 'credit') {
                return $fail('panel_credit_suspended');
            }
            if ($days > 0 && floor($days) != $days) {
                return $fail('panel_policy_unsupported');
            }
            if ($targetLimit <= 0 || $oldLimit <= 0) {
                return $fail('panel_policy_unsupported');
            }
            if (in_array($status, ['expired', 'limited'], true)) {
                if ($targetUsed > 0) {
                    return $fail('panel_policy_unsupported');
                }
            } elseif ($targetLimit < $oldLimit) {
                return $fail('panel_policy_unsupported');
            }
        }

        return [
            'ok' => true,
            'reason' => 'ok',
            'rx_renewal_plan' => 1,
            'policy' => $policy,
            'method' => (string) ($ctx['method'] ?? ''),
            'effective_method' => $policy,
            'label' => \Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel($policy, $resetUsage && $trafficMode === 'carry'),
            'traffic_mode' => $trafficMode,
            'time_mode' => $timeMode,
            'reset_usage_option' => $resetUsageOption,
            'queued' => false,
            'reset_usage' => $resetUsage,
            'destructive' => false,
            'manual_review' => false,
            'now' => $now,
            'before_status' => $status,
            'before_expire' => $oldExpire,
            'before_data_limit' => $oldLimit,
            'before_used_traffic' => $used,
            'before_remaining' => $remaining,
            'before_pending' => $pending,
            'before_pending_duration' => $pendingDuration,
            'purchased_days' => $days,
            'purchased_volume_gb' => $volume,
            'purchased_volume_bytes' => $purchasedBytes,
            'unlimited_expire' => $unlimitedTime,
            'unlimited_volume' => $unlimitedTraffic,
            'data_limit' => $targetLimit,
            'target_used_traffic' => $targetUsed,
            'target_remaining' => $targetLimit > 0 ? $targetLimit - $targetUsed : null,
            'expire' => $targetExpire,
            'target_pending' => $targetPending,
            'target_pending_duration' => $targetPendingDuration,
            'traffic_changed' => $targetLimit !== $oldLimit || $resetUsage,
            'time_changed' => $targetExpire !== $oldExpire || $targetPending !== $pending || $targetPendingDuration !== $pendingDuration,
            'enable' => !$targetPending && in_array($status, ['expired', 'limited'], true),
            'expect_active' => true,
        ];
    }

    public static function renewalReplan(array $plan, array $before, int $now, array $ctx = []): array
    {
        return self::renewalCompute(
            $now,
            (string) ($plan['policy'] ?? ''),
            !empty($plan['reset_usage_option']),
            $before,
            $plan['purchased_volume_gb'] ?? (((int) ($plan['purchased_volume_bytes'] ?? 0)) / RX_PF_GIB),
            $plan['purchased_days'] ?? 0,
            array_merge(['method' => (string) ($plan['method'] ?? '')], $ctx)
        );
    }

    public static function buildRenewalTarget(int $now, $method, array $before, $purchasedVolumeGb, $purchasedDays, array $ctx = []): array
    {
        $mode = self::renewalMode($method);
        if ($mode === null) {
            return ['ok' => false, 'reason' => 'unknown_renewal_method', 'method' => (string) $method, 'manual_review' => true, 'queued' => false, 'destructive' => false];
        }
        if (!empty($mode['queued'])) {
            $limit = \Faoxima\Payments\Fulfillment\Claims::int($before['data_limit'] ?? 0);
            return [
                'ok' => true,
                'reason' => 'ok',
                'method' => $mode['method'],
                'effective_method' => 'queued',
                'queued' => true,
                'reset_usage' => false,
                'before_expire' => \Faoxima\Payments\Fulfillment\Claims::int($before['expire'] ?? 0),
                'before_data_limit' => $limit,
                'before_used_traffic' => \Faoxima\Payments\Fulfillment\Claims::int($before['used_traffic'] ?? 0),
                'before_remaining' => $limit > 0 ? max(0, $limit - \Faoxima\Payments\Fulfillment\Claims::int($before['used_traffic'] ?? 0)) : null,
            ];
        }
        return self::renewalCompute($now, (string) $mode['policy'], !empty($mode['reset_usage']), $before, $purchasedVolumeGb, $purchasedDays, array_merge(['method' => $mode['method']], $ctx));
    }

    public static function renewalVerify(array $after, array $plan): array
    {
        $jovadJournal = $after['raw']['jovad_renewal_state'] ?? ($after['jovad_renewal_state'] ?? null);
        $jovadPending = false;
        $jovadMatches = is_array($jovadJournal) && method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadpanelRenewalSignature')
            && ($jovadJournal['signature'] ?? '') === \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelRenewalSignature($plan);
        if ($jovadMatches) {
            foreach (($jovadJournal['steps'] ?? []) as $jovadStep) {
                if (($jovadStep['state'] ?? '') !== 'done') $jovadPending = true;
            }
        }
        if (method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadpanelVerificationPlan')) {
            $plan = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelVerificationPlan($after, $plan);
        }
        $checks = [];
        if ($jovadPending) $checks['jovad_steps'] = false;
        if (empty($after['ok']) && array_key_exists('ok', $after)) {
            return ['ok' => false, 'checks' => ['readable' => false], 'reason' => 'unreadable'];
        }
        if (array_key_exists('exists', $after) && empty($after['exists'])) {
            return ['ok' => false, 'checks' => ['exists' => false], 'reason' => 'missing'];
        }
        $targetLimit = \Faoxima\Payments\Fulfillment\Claims::int($plan['data_limit'] ?? 0);
        $afterLimit = \Faoxima\Payments\Fulfillment\Claims::int($after['data_limit'] ?? 0);
        $checks['traffic'] = $targetLimit <= 0 ? $afterLimit <= 0 : ($afterLimit > 0 && abs($afterLimit - $targetLimit) <= RX_RN_TRAFFIC_TOLERANCE);
        $afterUsed = \Faoxima\Payments\Fulfillment\Claims::int($after['used_traffic'] ?? 0);
        $beforeUsed = \Faoxima\Payments\Fulfillment\Claims::int($plan['before_used_traffic'] ?? 0);
        if (!empty($plan['reset_usage'])) {
            $checks['usage'] = $afterUsed <= RX_RN_USAGE_SLACK || $afterUsed + RX_RN_USAGE_SLACK <= $beforeUsed;
            if ($jovadMatches) {
                if (($jovadJournal['steps']['reset']['state'] ?? '') === 'done' || ($jovadJournal['steps']['revive']['state'] ?? '') === 'done') {
                    $checks['usage'] = true;
                }
            }
        } else {
            $checks['usage'] = $afterUsed + RX_RN_USAGE_SLACK >= $beforeUsed;
        }
        $afterStatus = strtolower(trim((string) ($after['status'] ?? '')));
        $afterPending = !empty($after['pending']) || $afterStatus === 'on_hold';
        if (!empty($plan['target_pending'])) {
            $wantedDuration = \Faoxima\Payments\Fulfillment\Claims::int($plan['target_pending_duration'] ?? 0);
            $afterDuration = isset($after['pending_duration']) ? \Faoxima\Payments\Fulfillment\Claims::int($after['pending_duration']) : null;
            $checks['time'] = $afterPending && $afterDuration !== null && abs($afterDuration - $wantedDuration) <= RX_PF_EXPIRE_TOLERANCE;
        } else {
            $targetExpire = \Faoxima\Payments\Fulfillment\Claims::int($plan['expire'] ?? 0);
            $afterExpire = \Faoxima\Payments\Fulfillment\Claims::int($after['expire'] ?? 0);
            $checks['time'] = $targetExpire <= 0
                ? ($afterExpire <= 0 && !$afterPending)
                : ($afterExpire > 0 && abs($afterExpire - $targetExpire) <= RX_PF_EXPIRE_TOLERANCE);
        }
        if (!empty($plan['expect_active'])) {
            $checks['status'] = $afterStatus === 'active' || (!empty($plan['target_pending']) && $afterPending);
        }
        $ok = !in_array(false, $checks, true);
        $failed = array_keys(array_filter($checks, function ($v) {
            return $v === false;
        }));
        return ['ok' => $ok, 'checks' => $checks, 'reason' => $ok ? 'ok' : 'mismatch_' . implode('_', $failed)];
    }

    public static function renewalClassify(array $snapshot, array $plan): string
    {
        $jovadPlan = $plan;
        if (method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadpanelVerificationPlan')) {
            $plan = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelVerificationPlan($snapshot, $plan);
        }
        if (empty($snapshot['ok'])) {
            return 'unknown';
        }
        if (empty($snapshot['exists'])) {
            return 'ambiguous';
        }
        if (!empty(self::renewalVerify($snapshot, $jovadPlan)['ok'])) {
            return 'target';
        }
        $jovadJournal = $snapshot['raw']['jovad_renewal_state'] ?? null;
        if (is_array($jovadJournal) && method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadpanelRenewalSignature')
            && ($jovadJournal['signature'] ?? '') === \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelRenewalSignature($jovadPlan)) {
            return 'before';
        }
        $limitMatches = function ($value, $wanted) {
            $value = \Faoxima\Payments\Fulfillment\Claims::int($value);
            $wanted = \Faoxima\Payments\Fulfillment\Claims::int($wanted);
            return $wanted <= 0 ? $value <= 0 : ($value > 0 && abs($value - $wanted) <= RX_RN_TRAFFIC_TOLERANCE);
        };
        $expireMatches = function ($value, $wanted) {
            $value = \Faoxima\Payments\Fulfillment\Claims::int($value);
            $wanted = \Faoxima\Payments\Fulfillment\Claims::int($wanted);
            return ($wanted <= 0 || $value <= 0) ? ($wanted <= 0 && $value <= 0) : abs($value - $wanted) <= RX_PF_EXPIRE_TOLERANCE;
        };
        $afterPending = !empty($snapshot['pending']);
        $limitOk = $limitMatches($snapshot['data_limit'] ?? 0, $plan['before_data_limit'] ?? 0) || $limitMatches($snapshot['data_limit'] ?? 0, $plan['data_limit'] ?? 0);
        $expireBefore = !empty($plan['before_pending']) ? $afterPending : $expireMatches($snapshot['expire'] ?? 0, $plan['before_expire'] ?? 0);
        $expireTarget = !empty($plan['target_pending']) ? $afterPending : $expireMatches($snapshot['expire'] ?? 0, $plan['expire'] ?? 0);
        if (!$limitOk || !($expireBefore || $expireTarget)) {
            return 'ambiguous';
        }
        return 'before';
    }
}
