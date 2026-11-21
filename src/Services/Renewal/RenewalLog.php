<?php

namespace Faoxima\Services\Renewal;

use Throwable;

final class RenewalLog
{
    public static function rx_renewal_log_dir(): string
    {
        if (defined('REFACTORED_LOG_DIR')) {
            return (string) REFACTORED_LOG_DIR;
        }
        $root = defined('REFACTORED_LEGACY_ROOT') ? (string) REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
        return $root . DIRECTORY_SEPARATOR . 'logs';
    }

    public static function rx_renewal_log_clean($value, int $max = 300): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        $text = is_scalar($value) ? (string) $value : '';
        $text = preg_replace('~[a-z][a-z0-9+.\-]*://[^\s"\'<>|]+~i', '[url]', $text) ?? '';
        $text = preg_replace('~\b(?:bearer|basic)\s+[A-Za-z0-9._\-+/=]{8,}~i', '[redacted]', $text) ?? '';
        $text = preg_replace('~("?(?:password|passwd|pass|pwd|token|access_token|refresh_token|secret|api[_-]?key|apikey|authorization|auth|cookie|session|subscription_url|sub_url|subscription|sub|uuid|private[_-]?key|key)"?\s*[:=]\s*)("[^"]*"|\'[^\']*\'|[^\s,;&|}\]]+)~i', '$1[redacted]', $text) ?? '';
        $text = preg_replace('~\b(?:\d{1,3}\.){3}\d{1,3}(?::\d+)?\b~', '[host]', $text) ?? '';
        $text = preg_replace('~\b[a-z0-9][a-z0-9\-]*(?:\.[a-z0-9\-]+)+\.[a-z]{2,}(?::\d+)?(?:/[^\s"\'<>|]*)?~i', '[host]', $text) ?? '';
        $text = preg_replace('~(?<![A-Za-z0-9])[A-Za-z0-9_\-+/=]{32,}(?![A-Za-z0-9])~', '[redacted]', $text) ?? '';
        $text = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text) ?? '';
        $text = trim(str_replace('|', '/', $text));
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max, 'UTF-8');
        }
        return substr($text, 0, $max);
    }

    public static function rx_renewal_log_value($value): string
    {
        if ($value === null) {
            return 'unavailable';
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return $value;
        }
        $clean = self::rx_renewal_log_clean($value);
        return $clean === '' ? 'empty' : $clean;
    }

    public static function rx_renewal_log_state(array $state, bool $fromPlan = false): array
    {
        $map = $fromPlan
            ? ['status' => 'before_status', 'limit' => 'before_data_limit', 'used' => 'before_used_traffic', 'expire' => 'before_expire', 'pending' => 'before_pending', 'pending_duration' => 'before_pending_duration']
            : ['status' => 'status', 'limit' => 'data_limit', 'used' => 'used_traffic', 'expire' => 'expire', 'pending' => 'pending', 'pending_duration' => 'pending_duration'];
        $out = [];
        foreach ($map as $label => $key) {
            $out[$label] = array_key_exists($key, $state) && $state[$key] !== '' ? $state[$key] : null;
        }
        if (is_numeric($out['expire']) && (int) $out['expire'] > 0) {
            $out['expire'] = (int) $out['expire'] . '(' . date('Y-m-d H:i:s', (int) $out['expire']) . ')';
        } elseif (is_numeric($out['expire'])) {
            $out['expire'] = '0(unlimited)';
        }
        if (is_numeric($out['limit']) && (int) $out['limit'] === 0) {
            $out['limit'] = '0(unlimited)';
        }
        return $out;
    }

    public static function rx_renewal_log_explain(string $reason): string
    {
        $map = [
            'admin_blocked' => 'Service treated as administrator-blocked (latest invoice status disablebyadmin or panel status disabled)',
            'pending_unsupported' => 'Service is awaiting first connection and the panel or method cannot renew it',
            'result_not_usable' => 'Computed result would leave the service without usable traffic or time',
            'invalid_purchase' => 'Purchased volume or days are missing, non-numeric or negative',
            'unknown_renewal_method' => 'Panel Methodextend does not match any renewal mode',
            'unsupported_renewal_method' => 'Panel Methodextend does not match any renewal mode',
            'unknown_policy' => 'Renewal policy is not defined',
            'invariant_traffic_discarded' => 'Carry policy would have discarded remaining traffic',
            'invariant_time_discarded' => 'Append policy would have discarded remaining time',
            'panel_state_unavailable' => 'Panel state lacked a numeric limit, expiry or usage value',
            'panel_read_failed' => 'Panel user data could not be read',
            'panel_unreachable' => 'Panel snapshot could not be obtained during preview',
            'panel_not_found' => 'Panel row could not be loaded',
            'user_missing' => 'Service does not exist on the panel',
            'renewal_in_progress' => 'Another renewal operation holds the service lock',
            'queued_renewal_exists' => 'A queued reservation is already outstanding for this service',
            'reservation_unsupported' => 'Reservation is not supported for this service',
            'policy_invalid' => 'Reservation policy is invalid',
            'trigger_unavailable' => 'Reservation trigger is unavailable for this service',
            'trigger_date_required' => 'Reservation date trigger selected without a date',
            'trigger_date_invalid' => 'Reservation date is invalid',
            'trigger_date_expired' => 'Reservation date is in the past',
            'queued_renewal_insert_failed' => 'Reservation could not be stored',
            'manual_stock_empty' => 'No manual configuration is available in stock',
            'operation_persist_failed' => 'Renewal operation record could not be persisted',
            'reset_failed' => 'Usage reset failed before any change was made',
            'panel_write_failed' => 'Panel rejected the renewal write; service unchanged',
            'renewal_refused' => 'Renewal refused without a specific reason',
            'pending_verification' => 'Panel write sent but the resulting state is not confirmed',
            'unverified_result' => 'Panel state after renewal does not match the target',
        ];
        return $map[$reason] ?? 'No mapped explanation; see error field';
    }

    public static function rx_renewal_log_dedup(string $dir, string $key): bool
    {
        $dedupDir = $dir . DIRECTORY_SEPARATOR . '.renewal-error-dedup';
        if (!is_dir($dedupDir)) {
            @mkdir($dedupDir, 0755, true);
        }
        $file = $dedupDir . DIRECTORY_SEPARATOR . md5($key);
        $mtime = @filemtime($file);
        if ($mtime !== false && (time() - $mtime) < RX_RENEWAL_LOG_DEDUP_SECONDS) {
            return false;
        }
        @touch($file);
        if (mt_rand(1, 50) === 1) {
            foreach ((array) @glob($dedupDir . DIRECTORY_SEPARATOR . '*') as $old) {
                $oldTime = @filemtime($old);
                if ($oldTime !== false && (time() - $oldTime) > 3600) {
                    @unlink($old);
                }
            }
        }
        return true;
    }

    public static function rx_renewal_log(string $stage, string $reason, array $ctx = []): void
    {
        static $seen = [];
        try {
            $panelRow = is_array($ctx['panel'] ?? null) ? $ctx['panel'] : [];
            $panelName = (string) ($ctx['panel_name'] ?? ($panelRow['name_panel'] ?? (is_string($ctx['panel'] ?? null) ? $ctx['panel'] : '')));
            $panelType = (string) ($ctx['panel_type'] ?? ($panelRow['type'] ?? ''));
            $username = (string) ($ctx['username'] ?? '');
            $dir = self::rx_renewal_log_dir();
            $reasonKey = (string) preg_replace('~[:\s].*$~s', '', $reason);
            $requestKey = $panelName . '|' . $username . '|' . $reasonKey;
            if (isset($seen[$requestKey])) {
                return;
            }
            $seen[$requestKey] = true;
            $installKey = sha1((string) (@realpath($dir) ?: $dir));
            if (!self::rx_renewal_log_dedup($dir, $installKey . '|' . $panelName . '|' . $username . '|' . $stage . '|' . $reasonKey)) {
                return;
            }
            $plan = is_array($ctx['plan'] ?? null) ? $ctx['plan'] : [];
            $snapshot = is_array($ctx['snapshot'] ?? null) ? $ctx['snapshot'] : [];
            $panelState = null;
            $stateSource = 'unavailable';
            if ($snapshot !== [] && array_key_exists('data_limit', $snapshot) && ($snapshot['ok'] ?? true)) {
                $panelState = self::rx_renewal_log_state($snapshot);
                $stateSource = 'panel_snapshot';
            } elseif ($plan !== [] && array_key_exists('before_data_limit', $plan)) {
                $panelState = self::rx_renewal_log_state($plan, true);
                $stateSource = 'plan_before';
            } elseif (is_array($ctx['before'] ?? null)) {
                $panelState = self::rx_renewal_log_state($ctx['before']);
                $stateSource = 'operation_before';
            }
            $fields = [
                'stage' => $stage,
                'reason' => $reasonKey,
                'explanation' => (string) ($ctx['explanation'] ?? self::rx_renewal_log_explain($reasonKey)),
                'username' => $username !== '' ? $username : null,
                'invoice_id' => ($ctx['invoice_id'] ?? '') !== '' ? $ctx['invoice_id'] : null,
                'invoice_status' => ($ctx['invoice_status'] ?? '') !== '' ? ($ctx['invoice_status'] ?? null) : null,
                'panel' => $panelName !== '' ? $panelName : null,
                'panel_type' => $panelType !== '' ? $panelType : null,
                'method' => ($ctx['method'] ?? '') !== '' ? ($ctx['method'] ?? null) : null,
                'policy' => ($ctx['policy'] ?? ($plan['policy'] ?? '')) !== '' ? ($ctx['policy'] ?? ($plan['policy'] ?? null)) : null,
                'purchased_volume_gb' => $ctx['volume_gb'] ?? ($plan['purchased_volume_gb'] ?? null),
                'purchased_days' => $ctx['days'] ?? ($plan['purchased_days'] ?? null),
                'state_source' => $stateSource,
                'panel_status' => $panelState['status'] ?? null,
                'panel_limit' => $panelState['limit'] ?? null,
                'panel_used' => $panelState['used'] ?? null,
                'panel_expire' => $panelState['expire'] ?? null,
                'panel_pending' => $panelState['pending'] ?? null,
                'panel_pending_duration' => $panelState['pending_duration'] ?? null,
            ];
            if (!empty($ctx['snapshot_error']) || ($snapshot !== [] && empty($snapshot['ok']) && !empty($snapshot['error']))) {
                $fields['snapshot_error'] = $ctx['snapshot_error'] ?? $snapshot['error'];
            }
            if (array_key_exists('admin_blocked', $ctx) || array_key_exists('admin_blocked_by_status', $ctx)) {
                $statusBlock = ($panelState['status'] ?? '') === 'disabled';
                $fields['admin_block_check'] = !empty($ctx['admin_blocked']) ? 'rejected_by_invoice_status' : ($statusBlock ? 'rejected_by_panel_status' : 'not_rejected');
            }
            if ($plan !== [] && array_key_exists('data_limit', $plan) && !empty($plan['ok'])) {
                $fields['target_limit'] = $plan['data_limit'];
                $fields['target_used'] = $plan['target_used_traffic'] ?? null;
                $fields['target_expire'] = isset($plan['expire']) ? self::rx_renewal_log_state(['expire' => $plan['expire']])['expire'] : null;
                $fields['target_pending'] = $plan['target_pending'] ?? null;
                $fields['target_pending_duration'] = $plan['target_pending_duration'] ?? null;
                $fields['reset_usage'] = $plan['reset_usage'] ?? null;
            }
            if (is_array($ctx['after'] ?? null)) {
                $afterState = self::rx_renewal_log_state($ctx['after']);
                $fields['after'] = 'status=' . self::rx_renewal_log_value($afterState['status']) . ' limit=' . self::rx_renewal_log_value($afterState['limit']) . ' used=' . self::rx_renewal_log_value($afterState['used']) . ' expire=' . self::rx_renewal_log_value($afterState['expire']) . ' pending_duration=' . self::rx_renewal_log_value($afterState['pending_duration']);
            }
            $fields['operation_id'] = ($ctx['operation_id'] ?? '') !== '' ? ($ctx['operation_id'] ?? null) : null;
            $fields['ref'] = ($ctx['ref'] ?? '') !== '' ? ($ctx['ref'] ?? null) : null;
            $fields['mutation'] = (string) ($ctx['mutation'] ?? 'none');
            if (isset($ctx['error']) && $ctx['error'] !== '') {
                $fields['error'] = $ctx['error'];
            } elseif (preg_match('~^[^:]+:\s*(.+)$~s', $reason, $m)) {
                $fields['error'] = $m[1];
            }
            if (isset($ctx['detail']) && $ctx['detail'] !== '') {
                $fields['detail'] = $ctx['detail'];
            }
            if (method_exists(\Faoxima\Services\Renewal\RenewalText::class, 'rxRenewalReasonText') && method_exists(\Faoxima\Services\Renewal\RenewalText::class, 'rxRenewalText') && !in_array($reasonKey, ['panel_unreachable', 'pending_verification', 'unverified_result'], true)) {
                $fields['customer_message'] = \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText($reasonKey, $plan) === \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reason_generic') ? 'generic' : 'specific';
            }
            $parts = [];
            foreach ($fields as $name => $value) {
                $parts[] = $name . '=' . self::rx_renewal_log_value($value);
            }
            $line = '[' . date('Y-m-d H:i:s') . '] [RENEWAL_ERROR] ' . implode(' | ', $parts);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $path = $dir . DIRECTORY_SEPARATOR . 'renewal-error.log';
            $size = @filesize($path);
            if ($size !== false && $size > RX_RENEWAL_LOG_MAX_BYTES) {
                @rename($path, $path . '.1');
            }
            @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
        }
    }
}
