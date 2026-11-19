<?php

if (!defined('RX_PF_LEASE_SECONDS')) {
    define('RX_PF_LEASE_SECONDS', 300);
}
if (!defined('RX_PF_EXPIRE_TOLERANCE')) {
    define('RX_PF_EXPIRE_TOLERANCE', 120);
}
if (!defined('RX_PF_MAX_ATTEMPTS')) {
    define('RX_PF_MAX_ATTEMPTS', 12);
}
if (!defined('RX_PF_GIB')) {
    define('RX_PF_GIB', 1073741824);
}

if (!function_exists('rx_pf_is_receipt_payment')) {
    function rx_pf_is_receipt_payment(array $payment): bool
    {
        return in_array((string) ($payment['Payment_Method'] ?? ''), ['cart to cart', 'arze digital offline'], true);
    }
}

if (!function_exists('rx_pf_cron_payment_allowed')) {
    function rx_pf_cron_payment_allowed(array $payment, ?int $now = null): bool
    {
        if (!defined('RX_CRON_INIT_LOADED')) {
            return true;
        }
        $now = $now ?? time();
        $raw = trim((string) ($payment['time'] ?? ''));
        if (preg_match('/^[0-9]{10}$/D', $raw)) {
            $created = (int) $raw;
        } else {
            $normalized = str_replace('/', '-', $raw);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $normalized, new DateTimeZone('Asia/Tehran'));
            if ($date === false || $date->format('Y-m-d H:i:s') !== $normalized) {
                return false;
            }
            $created = $date->getTimestamp();
        }
        if (rx_pf_is_receipt_payment($payment)
            && in_array((string) ($payment['payment_Status'] ?? ''), ['Unpaid', 'waiting', 'pending', 'processing', 'reconciling', 'paid'], true)) {
            return $created > 0 && $created <= $now;
        }
        if ((string) ($payment['Payment_Method'] ?? '') === 'tonpay'
            && (string) ($payment['tonpay_gateway_mode'] ?? '') === 'custom') {
            return $created > 0 && $created <= $now;
        }
        return $created > 0 && $created <= $now && $created >= $now - (function_exists('rx_cron_catchup_seconds') ? rx_cron_catchup_seconds() : 3600);
    }
}

if (!function_exists('rx_pf_cron_window_sql')) {
    function rx_pf_cron_window_sql(string $alias = 'Payment_report'): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $alias)) {
            throw new InvalidArgumentException('Invalid payment table alias');
        }
        $now = time();
        $zone = new DateTimeZone('Asia/Tehran');
        $until = (new DateTimeImmutable('@' . $now))->setTimezone($zone)->format('Y-m-d H:i:s');
        $since = (new DateTimeImmutable('@' . ($now - (function_exists('rx_cron_catchup_seconds') ? rx_cron_catchup_seconds() : 3600))))->setTimezone($zone)->format('Y-m-d H:i:s');
        $column = $alias . '.`time`';
        return "(CASE WHEN $column REGEXP '^[0-9]{10}$' THEN CAST($column AS UNSIGNED) BETWEEN " . ($now - (function_exists('rx_cron_catchup_seconds') ? rx_cron_catchup_seconds() : 3600)) . " AND $now ELSE REPLACE($column, '/', '-') BETWEEN '$since' AND '$until' END)";
    }
}

if (!function_exists('rx_pf_cron_receipt_window_sql')) {
    function rx_pf_cron_receipt_window_sql(string $alias = 'Payment_report'): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $alias)) {
            throw new InvalidArgumentException('Invalid payment table alias');
        }
        return '((' . rx_pf_cron_window_sql($alias) . ') OR ' . $alias . ".payment_Status IN ('Unpaid', 'waiting', 'pending', 'processing', 'reconciling', 'paid'))";
    }
}

if (!function_exists('rx_pf_log')) {
    function rx_pf_log(string $event, string $message, array $ctx = []): void
    {
        if (function_exists('rx_log_event')) {
            try {
                rx_log_event($event, $message, $ctx);
                return;
            } catch (Throwable $e) {
            }
        }
        error_log('[' . $event . '] ' . $message . ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
}

if (!defined('RX_RENEWAL_LOG_DEDUP_SECONDS')) {
    define('RX_RENEWAL_LOG_DEDUP_SECONDS', 60);
}
if (!defined('RX_RENEWAL_LOG_MAX_BYTES')) {
    define('RX_RENEWAL_LOG_MAX_BYTES', 5242880);
}

if (!function_exists('rx_renewal_log_dir')) {
    function rx_renewal_log_dir(): string
    {
        if (defined('REFACTORED_LOG_DIR')) {
            return (string) REFACTORED_LOG_DIR;
        }
        $root = defined('REFACTORED_LEGACY_ROOT') ? (string) REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
        return $root . DIRECTORY_SEPARATOR . 'logs';
    }
}

if (!function_exists('rx_renewal_log_clean')) {
    function rx_renewal_log_clean($value, int $max = 300): string
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
}

if (!function_exists('rx_renewal_log_value')) {
    function rx_renewal_log_value($value): string
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
        $clean = rx_renewal_log_clean($value);
        return $clean === '' ? 'empty' : $clean;
    }
}

if (!function_exists('rx_renewal_log_state')) {
    function rx_renewal_log_state(array $state, bool $fromPlan = false): array
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
}

if (!function_exists('rx_renewal_log_explain')) {
    function rx_renewal_log_explain(string $reason): string
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
}

if (!function_exists('rx_renewal_log_dedup')) {
    function rx_renewal_log_dedup(string $dir, string $key): bool
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
}

if (!function_exists('rx_renewal_log')) {
    function rx_renewal_log(string $stage, string $reason, array $ctx = []): void
    {
        static $seen = [];
        try {
            $panelRow = is_array($ctx['panel'] ?? null) ? $ctx['panel'] : [];
            $panelName = (string) ($ctx['panel_name'] ?? ($panelRow['name_panel'] ?? (is_string($ctx['panel'] ?? null) ? $ctx['panel'] : '')));
            $panelType = (string) ($ctx['panel_type'] ?? ($panelRow['type'] ?? ''));
            $username = (string) ($ctx['username'] ?? '');
            $dir = rx_renewal_log_dir();
            $reasonKey = (string) preg_replace('~[:\s].*$~s', '', $reason);
            $requestKey = $panelName . '|' . $username . '|' . $reasonKey;
            if (isset($seen[$requestKey])) {
                return;
            }
            $seen[$requestKey] = true;
            $installKey = sha1((string) (@realpath($dir) ?: $dir));
            if (!rx_renewal_log_dedup($dir, $installKey . '|' . $panelName . '|' . $username . '|' . $stage . '|' . $reasonKey)) {
                return;
            }
            $plan = is_array($ctx['plan'] ?? null) ? $ctx['plan'] : [];
            $snapshot = is_array($ctx['snapshot'] ?? null) ? $ctx['snapshot'] : [];
            $panelState = null;
            $stateSource = 'unavailable';
            if ($snapshot !== [] && array_key_exists('data_limit', $snapshot) && ($snapshot['ok'] ?? true)) {
                $panelState = rx_renewal_log_state($snapshot);
                $stateSource = 'panel_snapshot';
            } elseif ($plan !== [] && array_key_exists('before_data_limit', $plan)) {
                $panelState = rx_renewal_log_state($plan, true);
                $stateSource = 'plan_before';
            } elseif (is_array($ctx['before'] ?? null)) {
                $panelState = rx_renewal_log_state($ctx['before']);
                $stateSource = 'operation_before';
            }
            $fields = [
                'stage' => $stage,
                'reason' => $reasonKey,
                'explanation' => (string) ($ctx['explanation'] ?? rx_renewal_log_explain($reasonKey)),
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
                $fields['target_expire'] = isset($plan['expire']) ? rx_renewal_log_state(['expire' => $plan['expire']])['expire'] : null;
                $fields['target_pending'] = $plan['target_pending'] ?? null;
                $fields['target_pending_duration'] = $plan['target_pending_duration'] ?? null;
                $fields['reset_usage'] = $plan['reset_usage'] ?? null;
            }
            if (is_array($ctx['after'] ?? null)) {
                $afterState = rx_renewal_log_state($ctx['after']);
                $fields['after'] = 'status=' . rx_renewal_log_value($afterState['status']) . ' limit=' . rx_renewal_log_value($afterState['limit']) . ' used=' . rx_renewal_log_value($afterState['used']) . ' expire=' . rx_renewal_log_value($afterState['expire']) . ' pending_duration=' . rx_renewal_log_value($afterState['pending_duration']);
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
            if (function_exists('rxRenewalReasonText') && function_exists('rxRenewalText') && !in_array($reasonKey, ['panel_unreachable', 'pending_verification', 'unverified_result'], true)) {
                $fields['customer_message'] = rxRenewalReasonText($reasonKey, $plan) === rxRenewalText('reason_generic') ? 'generic' : 'specific';
            }
            $parts = [];
            foreach ($fields as $name => $value) {
                $parts[] = $name . '=' . rx_renewal_log_value($value);
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

if (!function_exists('rx_pf_json')) {
    function rx_pf_json($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $json === false ? null : $json;
    }
}

if (!function_exists('rx_pf_decode')) {
    function rx_pf_decode($raw): ?array
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('rx_pf_int')) {
    function rx_pf_int($value): int
    {
        if ($value === null || $value === '' || is_bool($value) || !is_numeric($value)) {
            return 0;
        }
        return (int) round((float) $value);
    }
}

if (!function_exists('rx_pf_operation_type')) {
    function rx_pf_operation_type($idInvoice): string
    {
        $head = explode('|', (string) $idInvoice)[0];
        return in_array($head, ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'], true) ? $head : 'wallet';
    }
}

if (!function_exists('rx_pf_result')) {
    function rx_pf_result(string $status, string $reason = '', array $extra = []): array
    {
        return array_merge([
            'success' => rx_pf_delivery_completed($status),
            'completed' => $status === 'completed',
            'already_completed' => $status === 'already_completed',
            'retryable' => in_array($status, ['retryable', 'already_processing'], true),
            'requires_reconciliation' => $status === 'requires_reconciliation',
            'manual_review' => $status === 'manual_review',
            'status' => $status,
            'reason' => $reason,
            'panel_result' => null,
        ], $extra);
    }
}

if (!function_exists('rx_pf_table_ddl')) {
    function rx_pf_table_ddl(): string
    {
        return "CREATE TABLE IF NOT EXISTS payment_fulfillment (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_order VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            payment_report_id INT UNSIGNED NULL,
            operation_type VARCHAR(40) NOT NULL,
            state VARCHAR(20) NOT NULL,
            processing_token CHAR(32) NULL,
            lease_expires_at INT UNSIGNED NULL,
            attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
            before_state MEDIUMTEXT NULL,
            target_state MEDIUMTEXT NULL,
            result_state MEDIUMTEXT NULL,
            last_error TEXT NULL,
            created_at INT UNSIGNED NOT NULL,
            updated_at INT UNSIGNED NOT NULL,
            completed_at INT UNSIGNED NULL,
            UNIQUE KEY uniq_pf_id_order (id_order),
            KEY idx_pf_state_lease (state, lease_expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}

if (!function_exists('rx_pf_ensure_schema')) {
    function rx_pf_ensure_schema(): bool
    {
        static $ready = false;
        global $pdo;
        if ($ready) {
            return true;
        }
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $pdo->exec(rx_pf_table_ddl());
            $index = $pdo->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_id_order'");
            if ($index !== false && $index->fetch(PDO::FETCH_ASSOC) === false) {
                $pdo->exec("ALTER TABLE Payment_report ADD INDEX idx_pr_id_order (id_order(191))");
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('rx_pf_wallet_definition')) {
    function rx_pf_wallet_definition(): array
    {
        return [
            'table' => "CREATE TABLE IF NOT EXISTS wallet_ledger (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                id_user VARCHAR(200) NOT NULL,
                direction ENUM('credit','debit') NOT NULL,
                amount BIGINT NOT NULL,
                balance_after BIGINT NULL,
                category VARCHAR(50) NOT NULL,
                description VARCHAR(500) NULL,
                id_order VARCHAR(64) NULL,
                ref_table VARCHAR(50) NULL,
                ref_id VARCHAR(100) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fulfillment_key VARCHAR(191) NULL,
                UNIQUE KEY uniq_wl_fulfillment_key (fulfillment_key),
                INDEX idx_wl_user (id_user, created_at),
                INDEX idx_wl_order (id_order),
                INDEX idx_wl_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => [
                'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
                'id_user' => 'VARCHAR(200) NOT NULL',
                'direction' => "ENUM('credit','debit') NOT NULL",
                'amount' => 'BIGINT NOT NULL',
                'balance_after' => 'BIGINT NULL',
                'category' => 'VARCHAR(50) NOT NULL',
                'description' => 'VARCHAR(500) NULL',
                'id_order' => 'VARCHAR(64) NULL',
                'ref_table' => 'VARCHAR(50) NULL',
                'ref_id' => 'VARCHAR(100) NULL',
                'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'fulfillment_key' => 'VARCHAR(191) NULL',
            ],
            'indexes' => [
                'idx_wl_user' => 'ADD INDEX idx_wl_user (id_user, created_at)',
                'idx_wl_order' => 'ADD INDEX idx_wl_order (id_order)',
                'idx_wl_category' => 'ADD INDEX idx_wl_category (category)',
            ],
        ];
    }
}

if (!function_exists('rx_pf_wallet_type_base')) {
    function rx_pf_wallet_type_base(string $type): string
    {
        return (string) preg_replace('/[^a-z].*$/s', '', strtolower(trim($type)));
    }
}

if (!function_exists('rx_pf_wallet_column_problem')) {
    function rx_pf_wallet_column_problem(string $name, array $column): ?string
    {
        $type = rx_pf_wallet_type_base((string) ($column['Type'] ?? ''));
        $rawType = strtolower((string) ($column['Type'] ?? ''));
        $nullable = strtoupper((string) ($column['Null'] ?? '')) === 'YES';
        $optional = ['balance_after', 'description', 'id_order', 'ref_table', 'ref_id', 'fulfillment_key'];
        if (in_array($name, $optional, true) && !$nullable) {
            return $name . ' must allow NULL';
        }
        if ($name === 'id') {
            if ($type !== 'bigint' && !(in_array($type, ['int', 'integer'], true) && strpos($rawType, 'unsigned') !== false)) {
                return 'id must support at least an unsigned INT';
            }
            if (strtoupper((string) ($column['Key'] ?? '')) !== 'PRI') {
                return 'id must be the primary key';
            }
            if (stripos((string) ($column['Extra'] ?? ''), 'auto_increment') === false) {
                return 'id must be auto increment';
            }
            return null;
        }
        if ($name === 'id_user') {
            if ($type === 'bigint') {
                return null;
            }
            if (in_array($type, ['char', 'varchar'], true) && preg_match('/\((\d+)\)/', $rawType, $length) && (int) $length[1] >= 20) {
                return null;
            }
            return in_array($type, ['tinytext', 'text', 'mediumtext', 'longtext'], true) ? null : 'id_user cannot hold Telegram user IDs';
        }
        if ($name === 'direction') {
            if ($type === 'enum') {
                return strpos($rawType, "'credit'") !== false && strpos($rawType, "'debit'") !== false ? null : 'direction must accept credit and debit';
            }
            if (in_array($type, ['char', 'varchar'], true) && preg_match('/\((\d+)\)/', $rawType, $length) && (int) $length[1] >= 6) {
                return null;
            }
            return in_array($type, ['tinytext', 'text', 'mediumtext', 'longtext'], true) ? null : 'direction must accept credit and debit';
        }
        if ($name === 'amount' || $name === 'balance_after') {
            if ($type === 'bigint' && strpos($rawType, 'unsigned') === false) {
                return null;
            }
            if (in_array($type, ['decimal', 'numeric'], true) && preg_match('/\((\d+),\s*(\d+)\)/', $rawType, $precision)
                && (int) $precision[1] >= 19 && (int) $precision[2] === 0 && strpos($rawType, 'unsigned') === false) {
                return null;
            }
            return $name . ' must support signed BIGINT amounts without rounding';
        }
        if ($name === 'created_at') {
            return in_array($type, ['timestamp', 'datetime'], true)
                && preg_match('/^current_timestamp(?:\(\d*\))?$/i', (string) ($column['Default'] ?? ''))
                ? null : 'created_at must default to CURRENT_TIMESTAMP';
        }
        $minimum = ['category' => 50, 'description' => 500, 'id_order' => 64, 'ref_table' => 50, 'ref_id' => 100, 'fulfillment_key' => 191];
        if (in_array($type, ['text', 'mediumtext', 'longtext'], true)) {
            return null;
        }
        if (in_array($type, ['char', 'varchar'], true) && preg_match('/\((\d+)\)/', $rawType, $length)
            && (int) $length[1] >= ($minimum[$name] ?? 1)) {
            return null;
        }
        return $name . ' has insufficient string capacity';
    }
}

if (!function_exists('rx_pf_wallet_indexes')) {
    function rx_pf_wallet_indexes(PDO $pdo): array
    {
        $indexes = [];
        $stmt = $pdo->query('SHOW INDEX FROM wallet_ledger');
        if ($stmt === false) {
            return $indexes;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ($row['Key_name'] ?? '');
            if ($key === '') {
                continue;
            }
            if (!isset($indexes[$key])) {
                $indexes[$key] = ['unique' => ((int) ($row['Non_unique'] ?? 1) === 0), 'columns' => []];
            }
            $indexes[$key]['columns'][(int) ($row['Seq_in_index'] ?? 1)] = (string) ($row['Column_name'] ?? '');
            if ($row['Sub_part'] !== null) {
                $indexes[$key]['prefix'] = true;
            }
        }
        foreach ($indexes as &$meta) {
            ksort($meta['columns']);
            $meta['columns'] = array_values($meta['columns']);
        }
        unset($meta);
        return $indexes;
    }
}

if (!function_exists('rx_pf_wallet_schema_verify')) {
    function rx_pf_wallet_schema_verify(PDO $pdo): array
    {
        $definition = rx_pf_wallet_definition();
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM wallet_ledger');
        if ($stmt === false) {
            return ['ok' => false, 'reason' => 'wallet_ledger is not readable', 'details' => []];
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[strtolower((string) ($row['Field'] ?? ''))] = $row;
        }
        foreach (array_keys($definition['columns']) as $name) {
            if (!isset($columns[$name])) {
                return ['ok' => false, 'reason' => 'wallet_ledger is missing column ' . $name, 'details' => [$name]];
            }
            $problem = rx_pf_wallet_column_problem($name, $columns[$name]);
            if ($problem !== null) {
                return ['ok' => false, 'reason' => 'wallet_ledger.' . $name . ': ' . $problem, 'details' => [$name]];
            }
        }
        $engineRow = $pdo->query("SHOW TABLE STATUS LIKE 'wallet_ledger'")->fetch(PDO::FETCH_ASSOC);
        if (!is_array($engineRow) || strcasecmp((string) ($engineRow['Engine'] ?? ''), 'InnoDB') !== 0) {
            return ['ok' => false, 'reason' => 'wallet_ledger must use the InnoDB engine', 'details' => ['engine']];
        }
        $indexes = rx_pf_wallet_indexes($pdo);
        $expectedIndexes = ['idx_wl_user' => ['id_user', 'created_at'], 'idx_wl_order' => ['id_order'], 'idx_wl_category' => ['category']];
        foreach ($expectedIndexes as $keyName => $expectedColumns) {
            if (!isset($indexes[$keyName]) || $indexes[$keyName]['columns'] !== $expectedColumns) {
                return ['ok' => false, 'reason' => 'wallet_ledger is missing index ' . $keyName, 'details' => [$keyName]];
            }
        }
        foreach ($indexes as $meta) {
            if (!empty($meta['unique']) && empty($meta['prefix']) && ($meta['columns'] ?? []) === ['fulfillment_key']) {
                $invalidDirection = $pdo->query("SELECT 1 FROM wallet_ledger WHERE direction IS NULL OR direction NOT IN ('credit','debit') LIMIT 1")->fetchColumn();
                if ($invalidDirection !== false) {
                    return ['ok' => false, 'reason' => 'wallet_ledger.direction contains unsupported values; manual reconciliation required', 'details' => ['direction']];
                }
                return ['ok' => true, 'reason' => '', 'details' => []];
            }
        }
        return ['ok' => false, 'reason' => 'wallet_ledger is missing a unique index on fulfillment_key', 'details' => ['uniq_wl_fulfillment_key']];
    }
}

if (!function_exists('rx_pf_wallet_schema_reconcile')) {
    function rx_pf_wallet_schema_reconcile(PDO $pdo): array
    {
        $definition = rx_pf_wallet_definition();
        $tableExists = $pdo->query("SHOW TABLES LIKE 'wallet_ledger'")->fetchColumn();
        if ($tableExists === false) {
            $pdo->exec($definition['table']);
        }
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM wallet_ledger');
        if ($stmt === false) {
            return ['ok' => false, 'reason' => 'wallet_ledger is not readable', 'details' => []];
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[strtolower((string) ($row['Field'] ?? ''))] = $row;
        }
        if (empty($columns)) {
            return ['ok' => false, 'reason' => 'wallet_ledger has no readable columns', 'details' => []];
        }
        $hasRows = $pdo->query('SELECT 1 FROM wallet_ledger LIMIT 1')->fetchColumn() !== false;
        foreach (['id', 'id_user', 'direction', 'amount', 'category', 'created_at'] as $name) {
            if (!isset($columns[$name])) {
                if ($hasRows) {
                    return ['ok' => false, 'reason' => 'wallet_ledger is missing required column ' . $name . '; manual reconciliation required', 'details' => [$name]];
                }
                $addDefinition = $definition['columns'][$name];
                if ($name === 'id') {
                    $addDefinition .= ' PRIMARY KEY';
                }
                $pdo->exec('ALTER TABLE wallet_ledger ADD COLUMN `' . $name . '` ' . $addDefinition);
                continue;
            }
            $problem = rx_pf_wallet_column_problem($name, $columns[$name]);
            if ($problem !== null) {
                return ['ok' => false, 'reason' => 'wallet_ledger.' . $name . ': ' . $problem . '; manual reconciliation required', 'details' => [$name]];
            }
        }
        foreach (['balance_after', 'description', 'id_order', 'ref_table', 'ref_id', 'fulfillment_key'] as $name) {
            if (!isset($columns[$name])) {
                $pdo->exec('ALTER TABLE wallet_ledger ADD COLUMN `' . $name . '` ' . $definition['columns'][$name]);
                continue;
            }
            $problem = rx_pf_wallet_column_problem($name, $columns[$name]);
            if ($problem !== null) {
                return ['ok' => false, 'reason' => 'wallet_ledger.' . $name . ': ' . $problem . '; manual reconciliation required', 'details' => [$name]];
            }
        }
        $invalidDirection = $pdo->query("SELECT direction FROM wallet_ledger WHERE direction IS NOT NULL AND direction NOT IN ('credit','debit') LIMIT 1")->fetchColumn();
        if ($invalidDirection !== false) {
            return ['ok' => false, 'reason' => 'wallet_ledger.direction contains unsupported values; manual reconciliation required', 'details' => ['direction']];
        }
        $engineRow = $pdo->query("SHOW TABLE STATUS LIKE 'wallet_ledger'")->fetch(PDO::FETCH_ASSOC);
        if (is_array($engineRow) && strcasecmp((string) ($engineRow['Engine'] ?? ''), 'InnoDB') !== 0) {
            $pdo->exec('ALTER TABLE wallet_ledger ENGINE=InnoDB');
        }
        $indexes = rx_pf_wallet_indexes($pdo);
        foreach ($definition['indexes'] as $keyName => $sql) {
            if (!isset($indexes[$keyName])) {
                $pdo->exec('ALTER TABLE wallet_ledger ' . $sql);
            }
        }
        $hasUniqueFulfillment = false;
        foreach ($indexes as $meta) {
            if (!empty($meta['unique']) && empty($meta['prefix']) && ($meta['columns'] ?? []) === ['fulfillment_key']) {
                $hasUniqueFulfillment = true;
                break;
            }
        }
        if (!$hasUniqueFulfillment) {
            $duplicate = $pdo->query("SELECT fulfillment_key FROM wallet_ledger WHERE fulfillment_key IS NOT NULL GROUP BY fulfillment_key HAVING COUNT(*) > 1 LIMIT 1")->fetchColumn();
            if ($duplicate !== false) {
                return ['ok' => false, 'reason' => 'wallet_ledger has duplicate fulfillment_key values; manual reconciliation required', 'details' => ['fulfillment_key']];
            }
            if (isset($indexes['uniq_wl_fulfillment_key'])) {
                $pdo->exec('ALTER TABLE wallet_ledger DROP INDEX uniq_wl_fulfillment_key');
            }
            $pdo->exec('ALTER TABLE wallet_ledger ADD UNIQUE KEY uniq_wl_fulfillment_key (fulfillment_key)');
        }
        return rx_pf_wallet_schema_verify($pdo);
    }
}

if (!function_exists('rx_pf_wallet_ready')) {
    function rx_pf_wallet_ready(): bool
    {
        static $ready = false;
        global $pdo;
        if ($ready) {
            return true;
        }
        if (!($pdo instanceof PDO)) {
            return false;
        }
        if ($pdo->inTransaction()) {
            rx_pf_log('PAYMENT_FULFILLMENT_WALLET_SCHEMA_FAILED', 'wallet_ledger upgrade cannot run inside a transaction');
            return false;
        }
        $lockName = 'faoxima_wallet_ledger_' . substr(md5(defined('_FX_SHARD') ? (string) _FX_SHARD : 'default'), 0, 8);
        $locked = false;
        try {
            $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
            $lockStmt->execute([$lockName]);
            $locked = ((string) $lockStmt->fetchColumn() === '1');
        } catch (Throwable $e) {
            $locked = false;
        }
        if (!$locked) {
            rx_pf_log('PAYMENT_FULFILLMENT_WALLET_SCHEMA_FAILED', 'could not acquire wallet_ledger schema lock', ['lock' => $lockName]);
            return false;
        }
        try {
            $result = rx_pf_wallet_schema_reconcile($pdo);
            if (empty($result['ok'])) {
                rx_pf_log('PAYMENT_FULFILLMENT_WALLET_SCHEMA_FAILED', (string) ($result['reason'] ?? 'wallet_ledger schema upgrade failed'), ['details' => $result['details'] ?? []]);
                return false;
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_WALLET_SCHEMA_FAILED', $e->getMessage());
            return false;
        } finally {
            try {
                $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $releaseStmt->execute([$lockName]);
            } catch (Throwable $e) {
            }
        }
    }
}

if (!function_exists('rx_pf_clear_cache')) {
    function rx_pf_clear_cache(array $tables = ['Payment_report']): void
    {
        if (!function_exists('clearSelectCache')) {
            return;
        }
        foreach ($tables as $table) {
            try {
                clearSelectCache($table);
            } catch (Throwable $e) {
            }
        }
    }
}

if (!function_exists('rx_pf_tx')) {
    function rx_pf_tx(callable $fn)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
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
                } catch (Throwable $rollbackError) {
                }
            }
            throw $e;
        }
    }
}

if (!function_exists('rx_pf_lock_payment')) {
    function rx_pf_lock_payment(PDO $pdo, string $orderId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}

if (!function_exists('rx_pf_lock_record')) {
    function rx_pf_lock_record(PDO $pdo, string $orderId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}

if (!function_exists('rx_pf_fetch')) {
    function rx_pf_fetch(string $orderId): ?array
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT * FROM payment_fulfillment WHERE id_order = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}

if (!function_exists('rx_pf_write_claim')) {
    function rx_pf_write_claim(PDO $pdo, ?array $record, string $orderId, array $payment, string $state, string $token, int $now, bool $resetStates): void
    {
        $lease = $now + RX_PF_LEASE_SECONDS;
        $operation = rx_pf_operation_type($payment['id_invoice'] ?? '');
        $paymentId = isset($payment['id']) && is_numeric($payment['id']) ? (int) $payment['id'] : null;
        if ($record === null) {
            $stmt = $pdo->prepare(
                "INSERT INTO payment_fulfillment (id_order, payment_report_id, operation_type, state, processing_token, lease_expires_at, attempt_count, created_at, updated_at) "
                . "VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)"
            );
            $stmt->execute([$orderId, $paymentId, $operation, $state, $token, $lease, $now, $now]);
            return;
        }
        if ($resetStates) {
            $stmt = $pdo->prepare(
                "UPDATE payment_fulfillment SET payment_report_id = ?, operation_type = ?, state = ?, processing_token = ?, lease_expires_at = ?, "
                . "attempt_count = attempt_count + 1, before_state = NULL, target_state = NULL, result_state = NULL, last_error = NULL, updated_at = ? WHERE id_order = ?"
            );
            $stmt->execute([$paymentId, $operation, $state, $token, $lease, $now, $orderId]);
            return;
        }
        $stmt = $pdo->prepare(
            "UPDATE payment_fulfillment SET state = ?, processing_token = ?, lease_expires_at = ?, attempt_count = attempt_count + 1, updated_at = ? WHERE id_order = ?"
        );
        $stmt->execute([$state, $token, $lease, $now, $orderId]);
    }
}

if (!function_exists('rx_pf_new_token')) {
    function rx_pf_new_token(): string
    {
        return bin2hex(random_bytes(16));
    }
}

if (!function_exists('rx_pf_valid_order_id')) {
    function rx_pf_valid_order_id(string $orderId): bool
    {
        return $orderId !== '' && strlen($orderId) <= 191;
    }
}

if (!function_exists('rx_pf_set_payment_status')) {
    function rx_pf_set_payment_status(PDO $pdo, string $orderId, string $status, array $fromStatuses, ?string $decNote = null): int
    {
        $placeholders = implode(', ', array_fill(0, count($fromStatuses), '?'));
        $sql = "UPDATE Payment_report SET payment_Status = ?" . ($decNote !== null ? ", dec_not_confirmed = ?" : "")
            . " WHERE id_order = ? AND payment_Status IN ($placeholders) AND COALESCE(direct_payment_done, 0) = 0";
        $params = [$status];
        if ($decNote !== null) {
            $params[] = $decNote;
        }
        $params[] = $orderId;
        foreach ($fromStatuses as $from) {
            $params[] = $from;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

if (!function_exists('rx_pf_mark_paid_done')) {
    function rx_pf_mark_paid_done(PDO $pdo, string $orderId): int
    {
        $stmt = $pdo->prepare(
            "UPDATE Payment_report SET direct_payment_done = 1, payment_Status = CASE WHEN payment_Status IN ('processing', 'reconciling') THEN 'paid' ELSE payment_Status END "
            . "WHERE id_order = ? AND (COALESCE(direct_payment_done, 0) = 0 OR payment_Status IN ('processing', 'reconciling'))"
        );
        $stmt->execute([$orderId]);
        return $stmt->rowCount();
    }
}

if (!function_exists('rx_pf_claim')) {
    function rx_pf_claim(string $orderId, array $opts = []): array
    {
        $from = array_values(array_map('strval', (array) ($opts['from'] ?? ['waiting'])));
        $to = (string) ($opts['to'] ?? 'processing');
        $decNote = array_key_exists('dec_not_confirmed', $opts) ? (string) $opts['dec_not_confirmed'] : null;
        $minAge = isset($opts['min_age']) ? (int) $opts['min_age'] : null;
        $maxAge = isset($opts['max_age']) ? (int) $opts['max_age'] : null;
        if (!rx_pf_valid_order_id($orderId)) {
            return ['status' => 'error', 'reason' => 'invalid_order_id'];
        }
        if (!rx_pf_ensure_schema()) {
            return ['status' => 'error', 'reason' => 'schema_unavailable'];
        }
        try {
            $out = rx_pf_tx(function (PDO $pdo) use ($orderId, $from, $to, $decNote, $minAge, $maxAge) {
                $now = time();
                $payment = rx_pf_lock_payment($pdo, $orderId);
                if ($payment === null) {
                    return ['status' => 'not_found'];
                }
                if (!rx_pf_cron_payment_allowed($payment, $now)) {
                    return ['status' => 'not_eligible', 'reason' => 'cron_window_elapsed'];
                }
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    return ['status' => 'already_completed', 'report' => $payment];
                }
                $record = rx_pf_lock_record($pdo, $orderId);
                if ($record !== null) {
                    $state = (string) $record['state'];
                    $leaseActive = (int) ($record['lease_expires_at'] ?? 0) > $now;
                    if ($state === 'completed') {
                        return ['status' => 'already_completed', 'report' => $payment];
                    }
                    if ($state === 'manual_review') {
                        return ['status' => 'manual_review', 'report' => $payment];
                    }
                    if ($state === 'applying' || $state === 'reconciling') {
                        return ['status' => $leaseActive ? 'busy' : 'requires_reconciliation', 'report' => $payment];
                    }
                    if ($state === 'claimed' && $leaseActive) {
                        return ['status' => 'busy', 'report' => $payment];
                    }
                }
                if (!in_array((string) ($payment['payment_Status'] ?? ''), $from, true)) {
                    return ['status' => 'not_eligible', 'reason' => 'status', 'report' => $payment];
                }
                if ($minAge !== null || $maxAge !== null) {
                    $atUpdated = (string) ($payment['at_updated'] ?? '');
                    $ts = $atUpdated === '' ? false : strtotime($atUpdated);
                    if ($ts === false) {
                        return ['status' => 'not_eligible', 'reason' => 'at_updated_invalid', 'report' => $payment];
                    }
                    $age = $now - $ts;
                    if ($minAge !== null && $age <= $minAge) {
                        return ['status' => 'not_eligible', 'reason' => 'delay_not_elapsed', 'report' => $payment];
                    }
                    if ($maxAge !== null && $age >= $maxAge) {
                        return ['status' => 'not_eligible', 'reason' => 'window_elapsed', 'report' => $payment];
                    }
                }
                $token = rx_pf_new_token();
                rx_pf_write_claim($pdo, $record, $orderId, $payment, 'claimed', $token, $now, true);
                $sql = "UPDATE Payment_report SET payment_Status = ?, at_updated = ?" . ($decNote !== null ? ", dec_not_confirmed = ?" : "")
                    . " WHERE id_order = ? AND payment_Status = ? AND COALESCE(direct_payment_done, 0) = 0";
                $params = [$to, date('Y/m/d H:i:s', $now)];
                if ($decNote !== null) {
                    $params[] = $decNote;
                }
                $params[] = $orderId;
                $params[] = (string) $payment['payment_Status'];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                if ($stmt->rowCount() < 1) {
                    throw new RuntimeException('claim_status_update_failed');
                }
                $payment['payment_Status'] = $to;
                return ['status' => 'claimed', 'token' => $token, 'report' => $payment];
            });
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_CLAIM_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['status' => 'error', 'reason' => 'db_error', 'error' => $e->getMessage()];
        }
        rx_pf_clear_cache();
        return $out;
    }
}

if (!function_exists('rx_pf_begin')) {
    function rx_pf_begin(string $orderId, ?string $token): array
    {
        $deny = function (array $result): array {
            return ['proceed' => false, 'result' => $result];
        };
        if (!rx_pf_valid_order_id($orderId)) {
            return $deny(rx_pf_result('failed', 'invalid_order_id'));
        }
        if (!rx_pf_ensure_schema()) {
            return $deny(rx_pf_result('retryable', 'fulfillment_schema_unavailable'));
        }
        try {
            $out = rx_pf_tx(function (PDO $pdo) use ($orderId, $token, $deny) {
                $now = time();
                $payment = rx_pf_lock_payment($pdo, $orderId);
                if ($payment === null) {
                    return $deny(rx_pf_result('failed', 'payment_not_found'));
                }
                if (!rx_pf_cron_payment_allowed($payment, $now)) {
                    return $deny(rx_pf_result('manual_review', 'cron_window_elapsed'));
                }
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    return $deny(rx_pf_result('already_completed', 'direct_payment_done'));
                }
                $record = rx_pf_lock_record($pdo, $orderId);
                if ($record === null && defined('RX_CRON_INIT_LOADED') && strtolower((string) ($payment['payment_Status'] ?? '')) === 'paid') {
                    return $deny(rx_pf_result('manual_review', 'legacy_payment_requires_review'));
                }
                $state = $record !== null ? (string) $record['state'] : '';
                $leaseActive = $record !== null && (int) ($record['lease_expires_at'] ?? 0) > $now;
                if ($state === 'completed') {
                    return $deny(rx_pf_result('already_completed', 'fulfillment_completed'));
                }
                if ($state === 'manual_review') {
                    return $deny(rx_pf_result('manual_review', 'fulfillment_manual_review'));
                }
                if ($token !== null && $token !== '') {
                    if ($record === null || !hash_equals((string) ($record['processing_token'] ?? ''), $token)) {
                        return $deny(rx_pf_result('already_processing', 'not_owner'));
                    }
                    if ($state !== 'claimed' && $state !== 'reconciling') {
                        return $deny(rx_pf_result('already_processing', 'state_' . $state));
                    }
                    $stmt = $pdo->prepare("UPDATE payment_fulfillment SET lease_expires_at = ?, updated_at = ? WHERE id_order = ?");
                    $stmt->execute([$now + RX_PF_LEASE_SECONDS, $now, $orderId]);
                    return ['proceed' => true, 'phase' => $state === 'claimed' ? 'fresh' : 'reconcile', 'token' => $token, 'record' => $record];
                }
                if ($leaseActive && in_array($state, ['claimed', 'applying', 'reconciling'], true)) {
                    return $deny(rx_pf_result('already_processing', 'lease_active'));
                }
                $newToken = rx_pf_new_token();
                if ($state === 'applying' || $state === 'reconciling') {
                    if ((int) ($record['attempt_count'] ?? 0) >= RX_PF_MAX_ATTEMPTS) {
                        $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'manual_review', last_error = ?, lease_expires_at = NULL, updated_at = ? WHERE id_order = ?");
                        $stmt->execute(['max_attempts_exceeded', $now, $orderId]);
                        return $deny(rx_pf_result('manual_review', 'max_attempts_exceeded'));
                    }
                    rx_pf_write_claim($pdo, $record, $orderId, $payment, 'reconciling', $newToken, $now, false);
                    $record['state'] = 'reconciling';
                    $record['processing_token'] = $newToken;
                    return ['proceed' => true, 'phase' => 'reconcile', 'token' => $newToken, 'record' => $record];
                }
                rx_pf_write_claim($pdo, $record, $orderId, $payment, 'claimed', $newToken, $now, true);
                return ['proceed' => true, 'phase' => 'fresh', 'token' => $newToken, 'record' => ['state' => 'claimed', 'processing_token' => $newToken]];
            });
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_BEGIN_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return $deny(rx_pf_result('retryable', 'fulfillment_claim_failed'));
        }
        return $out;
    }
}

if (!function_exists('rx_pf_prepare')) {
    function rx_pf_prepare(string $orderId, string $token, ?array $before, ?array $target): bool
    {
        global $pdo;
        try {
            $now = time();
            $stmt = $pdo->prepare(
                "UPDATE payment_fulfillment SET state = 'applying', before_state = ?, target_state = ?, lease_expires_at = ?, updated_at = ? "
                . "WHERE id_order = ? AND processing_token = ? AND state = 'claimed'"
            );
            $stmt->execute([rx_pf_json($before), rx_pf_json($target), $now + RX_PF_LEASE_SECONDS, $now, $orderId, $token]);
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_PREPARE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return false;
        }
    }
}

if (!function_exists('rx_pf_mark_applying')) {
    function rx_pf_mark_applying(string $orderId, string $token): bool
    {
        global $pdo;
        try {
            $now = time();
            $stmt = $pdo->prepare(
                "UPDATE payment_fulfillment SET state = 'applying', lease_expires_at = ?, updated_at = ? WHERE id_order = ? AND processing_token = ? AND state = 'reconciling'"
            );
            $stmt->execute([$now + RX_PF_LEASE_SECONDS, $now, $orderId, $token]);
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_APPLY_MARK_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return false;
        }
    }
}

if (!function_exists('rx_pf_complete')) {
    function rx_pf_complete(string $orderId, string $token, $resultState = null, array $statements = []): string
    {
        try {
            $status = rx_pf_tx(function (PDO $pdo) use ($orderId, $token, $resultState, $statements) {
                $now = time();
                $record = rx_pf_lock_record($pdo, $orderId);
                if ($record === null) {
                    return 'missing';
                }
                if ((string) $record['state'] === 'completed') {
                    return 'already_completed';
                }
                if (!hash_equals((string) ($record['processing_token'] ?? ''), $token)) {
                    return 'not_owner';
                }
                if (!in_array((string) $record['state'], ['claimed', 'applying', 'reconciling'], true)) {
                    return 'invalid_state';
                }
                $payment = rx_pf_lock_payment($pdo, $orderId);
                if ($payment === null) {
                    return 'missing';
                }
                $alreadyDone = (int) ($payment['direct_payment_done'] ?? 0) === 1;
                if (!$alreadyDone) {
                    foreach ($statements as $statement) {
                        $stmt = $pdo->prepare((string) $statement[0]);
                        $stmt->execute(array_values((array) ($statement[1] ?? [])));
                    }
                }
                $stmt = $pdo->prepare(
                    "UPDATE payment_fulfillment SET state = 'completed', result_state = ?, lease_expires_at = NULL, completed_at = ?, updated_at = ? WHERE id_order = ?"
                );
                $stmt->execute([rx_pf_json($resultState), $now, $now, $orderId]);
                rx_pf_mark_paid_done($pdo, $orderId);
                return $alreadyDone ? 'already_completed' : 'completed';
            });
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_COMPLETE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return 'error';
        }
        rx_pf_clear_cache(['Payment_report', 'user', 'invoice']);
        return $status;
    }
}

if (!function_exists('rx_pf_transition')) {
    function rx_pf_transition(string $orderId, string $token, array $fromStates, string $toState, string $reason, ?string $paymentStatus, bool $renewLease, bool $countAttempt = false): string
    {
        try {
            $out = rx_pf_tx(function (PDO $pdo) use ($orderId, $token, $fromStates, $toState, $reason, $paymentStatus, $renewLease, $countAttempt) {
                $now = time();
                $record = rx_pf_lock_record($pdo, $orderId);
                if ($record === null) {
                    return 'missing';
                }
                if (!hash_equals((string) ($record['processing_token'] ?? ''), $token)) {
                    return 'not_owner';
                }
                if (!in_array((string) $record['state'], $fromStates, true)) {
                    return 'state_' . (string) $record['state'];
                }
                $targetState = $toState;
                if ($countAttempt && (int) ($record['attempt_count'] ?? 0) >= RX_PF_MAX_ATTEMPTS) {
                    $targetState = 'manual_review';
                    $paymentStatus = 'manual_review';
                    $reason = 'max_attempts_exceeded: ' . $reason;
                }
                $stmt = $pdo->prepare(
                    "UPDATE payment_fulfillment SET state = ?, last_error = ?, lease_expires_at = ?, attempt_count = attempt_count + ?, updated_at = ? WHERE id_order = ?"
                );
                $stmt->execute([
                    $targetState,
                    function_exists('mb_substr') ? mb_substr($reason, 0, 2000, 'UTF-8') : substr($reason, 0, 2000),
                    $renewLease && $targetState !== 'manual_review' ? $now + RX_PF_LEASE_SECONDS : null,
                    $countAttempt ? 1 : 0,
                    $now,
                    $orderId,
                ]);
                if ($paymentStatus !== null) {
                    rx_pf_set_payment_status($pdo, $orderId, $paymentStatus, ['processing', 'reconciling']);
                }
                return $targetState;
            });
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_TRANSITION_FAILED', $e->getMessage(), ['id_order' => $orderId, 'to' => $toState]);
            return 'error';
        }
        rx_pf_clear_cache();
        return $out;
    }
}

if (!function_exists('rx_pf_alert_admin')) {
    function rx_pf_alert_admin(string $orderId, string $reason, array $ctx = []): void
    {
        try {
            if (!function_exists('select') || !function_exists('telegram')) {
                return;
            }
            $setting = select('setting', '*');
            $channel = is_array($setting) ? trim((string) ($setting['Channel_Report'] ?? '')) : '';
            if ($channel === '') {
                return;
            }
            $topic = select('topicid', 'idreport', 'report', 'errorreport', 'select');
            $thread = is_array($topic) ? ($topic['idreport'] ?? null) : null;
            $text = "⚠️ <b>پرداخت نیازمند بررسی دستی است</b>\n"
                . "<blockquote>🛒 کد پیگیری پرداخت: <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n"
                . "<blockquote>✍️ دلیل: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</blockquote>\n";
            if (!empty($ctx['username'])) {
                $text .= "<blockquote>🪪 نام کاربری سرویس: <code>" . htmlspecialchars((string) $ctx['username'], ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n";
            }
            $text .= "ℹ️ وضعیت پنل قابل تشخیص قطعی نبود؛ عملیات پنل به‌صورت خودکار تکرار نمی‌شود.";
            telegram('sendmessage', [
                'chat_id' => $channel,
                'message_thread_id' => $thread,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_ALERT_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
    }
}

if (!function_exists('rx_pf_manual_review')) {
    function rx_pf_manual_review(string $orderId, string $token, string $reason, array $ctx = []): array
    {
        $state = rx_pf_transition($orderId, $token, ['claimed', 'applying', 'reconciling'], 'manual_review', $reason, 'manual_review', false);
        rx_pf_log('PAYMENT_FULFILLMENT_MANUAL_REVIEW', $reason, array_merge(['id_order' => $orderId, 'transition' => $state], $ctx));
        if ($state === 'manual_review') {
            rx_pf_alert_admin($orderId, $reason, $ctx);
            return rx_pf_result('manual_review', $reason);
        }
        return rx_pf_result('requires_reconciliation', $reason . ' (' . $state . ')');
    }
}

if (!function_exists('rx_pf_reconcile_later')) {
    function rx_pf_reconcile_later(string $orderId, string $token, string $reason, bool $countAttempt = false): array
    {
        $state = rx_pf_transition($orderId, $token, ['claimed', 'applying', 'reconciling'], 'reconciling', $reason, 'reconciling', true, $countAttempt);
        rx_pf_log('PAYMENT_FULFILLMENT_RECONCILE_LATER', $reason, ['id_order' => $orderId, 'transition' => $state]);
        if ($state === 'manual_review') {
            rx_pf_alert_admin($orderId, $reason);
            return rx_pf_result('manual_review', $reason);
        }
        return rx_pf_result('requires_reconciliation', $reason);
    }
}

if (!function_exists('rx_pf_defer')) {
    function rx_pf_defer(string $orderId, string $token, string $reason): array
    {
        return rx_pf_reconcile_later($orderId, $token, $reason, true);
    }
}

if (!function_exists('rx_pf_abort')) {
    function rx_pf_abort(string $orderId, string $token, string $reason, bool $retryable, array $extra = []): array
    {
        $record = null;
        try {
            $record = rx_pf_fetch($orderId);
        } catch (Throwable $e) {
            $record = null;
        }
        if (is_array($record) && (string) $record['state'] === 'reconciling') {
            return $retryable ? rx_pf_defer($orderId, $token, $reason) : rx_pf_manual_review($orderId, $token, $reason);
        }
        $abortState = rx_pf_transition($orderId, $token, ['claimed'], $retryable ? 'released' : 'failed', $reason, null, false);
        if (!$retryable && $abortState === 'failed' && function_exists('rx_ck_release_failed_fulfillment')) {
            rx_ck_release_failed_fulfillment($orderId, 'abort:' . $reason);
        }
        return rx_pf_result($retryable ? 'retryable' : 'failed', $reason, $extra);
    }
}

if (!function_exists('rx_pf_fail')) {
    function rx_pf_fail(string $orderId, string $token, string $reason, ?string $refundOutcome = null): array
    {
        rx_pf_transition($orderId, $token, ['claimed', 'applying', 'reconciling'], 'failed', $reason, null, false);
        if (function_exists('rx_ck_release_failed_fulfillment')) {
            rx_ck_release_failed_fulfillment($orderId, 'fail:' . $reason);
        }
        return rx_pf_result($refundOutcome === 'refunded' ? 'refunded' : 'failed', $reason, ['refund' => $refundOutcome]);
    }
}

if (!function_exists('rx_pf_completion_outcome')) {
    function rx_pf_completion_outcome(string $orderId, string $token, string $completion): array
    {
        if ($completion === 'already_completed') {
            return rx_pf_result('already_completed', 'finalized_elsewhere');
        }
        if ($completion === 'not_owner' || $completion === 'invalid_state' || $completion === 'missing') {
            return rx_pf_result('already_processing', 'completion_' . $completion);
        }
        return rx_pf_reconcile_later($orderId, $token, 'finalize_failed_after_panel_success');
    }
}

if (!function_exists('rx_pf_settle')) {
    function rx_pf_settle(string $orderId, ?string $token, array $result, string $releaseTo = 'waiting', string $failedTo = 'waiting'): string
    {
        try {
            $out = rx_pf_tx(function (PDO $pdo) use ($orderId, $token, $releaseTo, $failedTo) {
                $now = time();
                $payment = rx_pf_lock_payment($pdo, $orderId);
                if ($payment === null) {
                    return 'missing';
                }
                $status = (string) ($payment['payment_Status'] ?? '');
                $record = rx_pf_lock_record($pdo, $orderId);
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    if (in_array($status, ['processing', 'reconciling'], true)) {
                        rx_pf_mark_paid_done($pdo, $orderId);
                        return 'finalized';
                    }
                    return 'paid';
                }
                if ($record === null) {
                    return 'missing';
                }
                $state = (string) $record['state'];
                if ($state === 'completed') {
                    rx_pf_mark_paid_done($pdo, $orderId);
                    return 'finalized';
                }
                if ($token !== null && !hash_equals((string) ($record['processing_token'] ?? ''), $token)) {
                    return 'not_owner';
                }
                if ($state === 'claimed' || $state === 'released') {
                    if ($state === 'claimed') {
                        $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'released', lease_expires_at = NULL, updated_at = ? WHERE id_order = ?");
                        $stmt->execute([$now, $orderId]);
                    }
                    rx_pf_set_payment_status($pdo, $orderId, $releaseTo, ['processing', 'reconciling']);
                    return 'released';
                }
                if ($state === 'failed') {
                    $reason = (string) ($record['last_error'] ?? '');
                    rx_pf_set_payment_status($pdo, $orderId, $failedTo, ['processing', 'reconciling'], $failedTo === 'reject' ? ($reason !== '' ? $reason : 'fulfillment_failed') : null);
                    return $failedTo === 'reject' ? 'rejected' : 'released';
                }
                if ($state === 'applying') {
                    $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'reconciling', lease_expires_at = ?, updated_at = ? WHERE id_order = ?");
                    $stmt->execute([$now + RX_PF_LEASE_SECONDS, $now, $orderId]);
                    rx_pf_set_payment_status($pdo, $orderId, 'reconciling', ['processing']);
                    return 'reconciling';
                }
                if ($state === 'reconciling') {
                    rx_pf_set_payment_status($pdo, $orderId, 'reconciling', ['processing']);
                    return 'reconciling';
                }
                if ($state === 'manual_review') {
                    rx_pf_set_payment_status($pdo, $orderId, 'manual_review', ['processing', 'reconciling']);
                    return 'manual_review';
                }
                return 'state_' . $state;
            });
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_SETTLE_FAILED', $e->getMessage(), ['id_order' => $orderId, 'result' => $result['status'] ?? null]);
            return 'error';
        }
        rx_pf_clear_cache();
        return $out;
    }
}

if (!function_exists('rx_pf_recover_locked')) {
    function rx_pf_recover_locked(PDO $pdo, string $orderId, ?string &$alert): array
    {
        $now = time();
        $payment = rx_pf_lock_payment($pdo, $orderId);
        if ($payment === null) {
            return ['status' => 'skip'];
        }
        if (!rx_pf_cron_payment_allowed($payment, $now)) {
            return ['status' => 'not_eligible', 'reason' => 'cron_window_elapsed'];
        }
        $status = (string) ($payment['payment_Status'] ?? '');
        $paidUndelivered = $status === 'paid' && (int) ($payment['direct_payment_done'] ?? 0) === 0;
        if (!in_array($status, ['processing', 'reconciling'], true) && !$paidUndelivered) {
            return ['status' => 'skip'];
        }
        if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
            rx_pf_mark_paid_done($pdo, $orderId);
            return ['status' => 'finalized', 'report' => $payment];
        }
        $record = rx_pf_lock_record($pdo, $orderId);
        if ($record === null && $paidUndelivered && defined('RX_CRON_INIT_LOADED')) {
            return ['status' => 'manual_review', 'reason' => 'legacy_payment_requires_review'];
        }
        if ($record === null && $paidUndelivered) {
            $token = rx_pf_new_token();
            $stmt = $pdo->prepare(
                "INSERT INTO payment_fulfillment (id_order, payment_report_id, operation_type, state, processing_token, lease_expires_at, attempt_count, created_at, updated_at) VALUES (?, ?, ?, 'claimed', ?, ?, 1, ?, ?)"
            );
            $stmt->execute([
                $orderId,
                isset($payment['id']) && is_numeric($payment['id']) ? (int) $payment['id'] : null,
                rx_pf_operation_type($payment['id_invoice'] ?? ''),
                $token,
                $now + RX_PF_LEASE_SECONDS,
                $now,
                $now,
            ]);
            return ['status' => 'claimed', 'token' => $token, 'report' => $payment];
        }
        if ($record === null) {
            $stmt = $pdo->prepare(
                "INSERT INTO payment_fulfillment (id_order, payment_report_id, operation_type, state, attempt_count, last_error, created_at, updated_at) VALUES (?, ?, ?, 'manual_review', 0, ?, ?, ?)"
            );
            $stmt->execute([
                $orderId,
                isset($payment['id']) && is_numeric($payment['id']) ? (int) $payment['id'] : null,
                rx_pf_operation_type($payment['id_invoice'] ?? ''),
                'processing_without_fulfillment_record',
                $now,
                $now,
            ]);
            rx_pf_set_payment_status($pdo, $orderId, 'manual_review', ['processing', 'reconciling']);
            $alert = 'processing_without_fulfillment_record';
            return ['status' => 'manual_review'];
        }
        $state = (string) $record['state'];
        if ($state === 'completed') {
            rx_pf_mark_paid_done($pdo, $orderId);
            return ['status' => 'finalized', 'report' => $payment];
        }
        if ($state === 'manual_review') {
            rx_pf_set_payment_status($pdo, $orderId, 'manual_review', ['processing', 'reconciling']);
            return ['status' => 'manual_review'];
        }
        if ((int) ($record['lease_expires_at'] ?? 0) > $now && in_array($state, ['claimed', 'applying', 'reconciling'], true)) {
            return ['status' => 'busy'];
        }
        if ($state === 'released') {
            if ($paidUndelivered) {
                $token = rx_pf_new_token();
                rx_pf_write_claim($pdo, $record, $orderId, $payment, 'claimed', $token, $now, true);
                return ['status' => 'claimed', 'token' => $token, 'report' => $payment];
            }
            rx_pf_set_payment_status($pdo, $orderId, 'waiting', ['processing', 'reconciling']);
            return ['status' => 'released'];
        }
        if ($state === 'failed') {
            $reason = (string) ($record['last_error'] ?? '');
            if ($paidUndelivered) {
                $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'manual_review', lease_expires_at = NULL, updated_at = ? WHERE id_order = ?");
                $stmt->execute([$now, $orderId]);
                $alert = 'paid_fulfillment_failed';
                return ['status' => 'manual_review'];
            }
            rx_pf_set_payment_status($pdo, $orderId, 'reject', ['processing', 'reconciling'], $reason !== '' ? $reason : 'fulfillment_failed');
            return ['status' => 'rejected'];
        }
        $token = rx_pf_new_token();
        if ($state === 'claimed') {
            rx_pf_write_claim($pdo, $record, $orderId, $payment, 'claimed', $token, $now, true);
            return ['status' => 'claimed', 'token' => $token, 'report' => $payment];
        }
        if ((int) ($record['attempt_count'] ?? 0) >= RX_PF_MAX_ATTEMPTS) {
            $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'manual_review', last_error = ?, lease_expires_at = NULL, updated_at = ? WHERE id_order = ?");
            $stmt->execute(['max_attempts_exceeded', $now, $orderId]);
            rx_pf_set_payment_status($pdo, $orderId, 'manual_review', ['processing', 'reconciling']);
            $alert = 'max_attempts_exceeded';
            return ['status' => 'manual_review'];
        }
        rx_pf_write_claim($pdo, $record, $orderId, $payment, 'reconciling', $token, $now, false);
        rx_pf_set_payment_status($pdo, $orderId, 'reconciling', ['processing']);
        return ['status' => 'reconcile', 'token' => $token, 'report' => $payment];
    }
}

if (!function_exists('rx_pf_recover')) {
    function rx_pf_recover(string $orderId): array
    {
        if (!rx_pf_valid_order_id($orderId)) {
            return ['status' => 'error', 'reason' => 'invalid_order_id'];
        }
        if (!rx_pf_ensure_schema()) {
            return ['status' => 'error', 'reason' => 'schema_unavailable'];
        }
        $alert = null;
        try {
            $out = rx_pf_tx(function (PDO $pdo) use ($orderId, &$alert) {
                return rx_pf_recover_locked($pdo, $orderId, $alert);
            });
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_RECOVER_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['status' => 'error', 'reason' => 'db_error'];
        }
        rx_pf_clear_cache();
        if ($alert !== null) {
            rx_pf_log('PAYMENT_FULFILLMENT_MANUAL_REVIEW', $alert, ['id_order' => $orderId]);
            rx_pf_alert_admin($orderId, $alert);
        }
        return $out;
    }
}

if (!class_exists('RxLegacyPaymentConfirmation', false)) {
    final class RxLegacyPaymentConfirmation
    {
        public static function claimCard($connect, $order_id)
        {
            $_claim = $connect->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND (payment_Status = 'Unpaid' OR payment_Status = 'waiting')");
            $_claim->bind_param("s", $order_id);
            $_claim->execute();
            $_claimed = $_claim->affected_rows;
            $_claim->close();
            return $_claimed;
        }

        public static function claimZarinpal($pdo, $invoice_id)
        {
            $atomic = $pdo->prepare(
                "UPDATE Payment_report SET payment_Status = 'paid' "
                . "WHERE id_order = :id AND payment_Status NOT IN ('paid', 'cancelled')"
            );
            $atomic->bindValue(':id', $invoice_id, PDO::PARAM_STR);
            $atomic->execute();
            return $atomic->rowCount();
        }

        public static function claimOnline($connect, $orderId)
        {
            $atomic = $connect->prepare(
                "UPDATE Payment_report SET payment_Status = ? WHERE id_order = ? AND payment_Status NOT IN ('paid', 'cancelled')"
            );
            $statusPaid = 'paid';
            $atomic->bind_param('ss', $statusPaid, $orderId);
            $atomic->execute();
            $affected = $atomic->affected_rows;
            $atomic->close();
            return $affected;
        }

        public static function claimTronado($connect, $paymentId, $dedupeKey)
        {
            $atomic = $connect->prepare(
                "UPDATE Payment_report SET payment_Status = ?, dec_not_confirmed = ? "
                . "WHERE id_order = ? AND payment_Status <> 'paid'"
            );
            $statusPaid = 'paid';
            $atomic->bind_param('sss', $statusPaid, $dedupeKey, $paymentId);
            $atomic->execute();
            $affected = $atomic->affected_rows;
            $atomic->close();
            return $affected;
        }

        public static function creditCashback($report, $user, string $key, $percentage, string $description): ?int
        {
            $eligible = !function_exists('rx_cashbackEligibleForKey')
                || rx_cashbackEligibleForKey($key, $user['register'] ?? null, $report['id_invoice'] ?? null, $user['id'] ?? null, $report['id_order'] ?? null);
            if (!$eligible || $percentage == "0") {
                return null;
            }
            $amount = (int) floor(($report['price'] * $percentage) / 100);
            return rx_cashback_credit_once($report['id_order'], $user['id'], $amount, $key, $description) === 'credited'
                ? $amount
                : null;
        }

        public static function canResumeOnline(array $report): bool
        {
            $alreadyDone = (int)($report['direct_payment_done'] ?? 0) === 1;
            $wasCancelled = strtolower((string)($report['payment_Status'] ?? '')) === 'cancelled';
            return !$alreadyDone && !$wasCancelled;
        }
    }
}

if (!function_exists('rx_pf_attempt_claimed_delivery')) {
    function rx_pf_attempt_claimed_delivery(string $orderId, string $token, string $image, callable $onError): array
    {
        $result = null;
        try {
            $result = DirectPayment($orderId, $image, $token);
        } catch (Throwable $e) {
            $onError($e);
        }
        return is_array($result) ? $result : rx_pf_result('requires_reconciliation', 'direct_payment_invalid_result');
    }
}

if (!function_exists('rx_pf_delivery_completed')) {
    function rx_pf_delivery_completed(string $status): bool
    {
        return $status === 'completed' || $status === 'already_completed';
    }
}

if (!function_exists('rx_pf_run_claimed')) {
    function rx_pf_run_claimed(string $orderId, string $token, string $image, string $failedTo): array
    {
        $result = rx_pf_attempt_claimed_delivery($orderId, $token, $image, static function (Throwable $e) use ($orderId) {
            rx_pf_log('PAYMENT_FULFILLMENT_DIRECT_PAYMENT_THROWABLE', $e->getMessage(), [
                'id_order' => $orderId,
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        });
        $settle = rx_pf_settle($orderId, $token, $result, 'waiting', $failedTo);
        return [
            'result' => $result,
            'settle' => $settle,
            'finalized' => !empty($result['completed']) || $settle === 'finalized',
        ];
    }
}

if (!function_exists('rx_pf_panel_reconcilable')) {
    function rx_pf_panel_reconcilable($panel): bool
    {
        if (!is_array($panel)) {
            return false;
        }
        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel)) {
            return false;
        }
        return in_array((string) ($panel['type'] ?? ''), ['marzban', 'pasarguard', 'x-ui_single', 'guard', 'remnawave', 'rebecca', 'jovadpanel'], true);
    }
}

if (!function_exists('rx_pf_user_missing_message')) {
    function rx_pf_user_missing_message(string $message): bool
    {
        if ($message === '' || stripos($message, 'panel not found') !== false) {
            return false;
        }
        return (bool) preg_match('/user\s*(not\s*found|does\s*not\s*exist)|not\s*found|404/i', $message);
    }
}

if (!defined('RX_RN_USAGE_SLACK')) {
    define('RX_RN_USAGE_SLACK', 536870912);
}
if (!defined('RX_RN_TRAFFIC_TOLERANCE')) {
    define('RX_RN_TRAFFIC_TOLERANCE', 1048576);
}
if (!defined('RX_RN_FAR_FUTURE')) {
    define('RX_RN_FAR_FUTURE', 284014800);
}

if (!function_exists('rxRenewalTextDefaults')) {
    function rxRenewalTextDefaults(): array
    {
        return [
            'policy_carry_all' => 'حفظ حجم و زمان باقی‌مانده + افزودن حجم و زمان خریداری‌شده',
            'policy_reset_all' => 'ریست کامل حجم و زمان؛ فقط حجم خریداری‌شده و زمان خریداری‌شده از لحظه اجرا',
            'policy_reset_traffic_append_time' => 'ریست حجم (فقط حجم خریداری‌شده) + حفظ زمان باقی‌مانده و افزودن زمان خریداری‌شده',
            'policy_carry_traffic_reset_time' => 'حفظ حجم باقی‌مانده + افزودن حجم خریداری‌شده؛ زمان خریداری‌شده از لحظه اجرا شروع می‌شود',
            'policy_traffic_only' => 'فقط حجم: حجم باقی‌مانده + حجم خریداری‌شده؛ تاریخ انقضا تغییر نمی‌کند',
            'policy_time_only' => 'فقط زمان: زمان باقی‌مانده + زمان خریداری‌شده؛ حجم و مصرف تغییر نمی‌کند',
            'policy_reset_traffic_keep_time' => 'ریست حجم (فقط حجم خریداری‌شده)؛ تاریخ انقضا تغییر نمی‌کند',
            'policy_keep_traffic_reset_time' => 'حجم و مصرف بدون تغییر؛ زمان خریداری‌شده از لحظه اجرا شروع می‌شود',
            'policy_short_reset_traffic_keep_time' => 'ریست حجم، انقضا بدون تغییر',
            'policy_short_keep_traffic_reset_time' => 'حجم بدون تغییر، ریست زمان',
            'policy_short_carry_all' => 'حفظ حجم و زمان',
            'policy_short_reset_all' => 'ریست کامل حجم و زمان',
            'policy_short_reset_traffic_append_time' => 'ریست حجم، حفظ زمان',
            'policy_short_carry_traffic_reset_time' => 'حفظ حجم، ریست زمان',
            'policy_short_traffic_only' => 'فقط افزایش حجم',
            'policy_short_time_only' => 'فقط افزایش زمان',
            'policy_usage_reset_suffix' => ' (شمارنده مصرف صفر می‌شود)',
            'trigger_either' => 'اتمام حجم یا انقضای زمان (هرکدام زودتر)',
            'trigger_traffic' => 'فقط اتمام حجم',
            'trigger_expire' => 'فقط انقضای زمان',
            'trigger_date' => 'در تاریخ مشخص',
            'trigger_date_at' => 'در تاریخ {date}',
            'trigger_immediate' => 'اجرای فوری (بدون رزرو)',
            'trigger_unavailable_traffic' => 'حجم این سرویس نامحدود است و هرگز تمام نمی‌شود.',
            'trigger_unavailable_expire' => 'زمان این سرویس نامحدود است و هرگز منقضی نمی‌شود.',
            'trigger_unavailable_either' => 'حجم و زمان این سرویس هر دو نامحدود است؛ فقط فعال‌سازی در تاریخ مشخص ممکن است.',
            'reason_admin_blocked' => '⛔️ این سرویس توسط مدیریت غیرفعال شده است و تمدید، این محدودیت را برطرف نمی‌کند. برای رفع محدودیت با پشتیبانی در ارتباط باشید؛ مبلغی کسر نشد.',
            'reason_pending_unsupported' => '⚠️ این سرویس هنوز در انتظار اولین اتصال است و این پنل امکان اعمال روش تمدید انتخاب‌شده روی مدت انتظار را ندارد. لطفاً ابتدا به سرویس متصل شوید و سپس تمدید کنید؛ مبلغی کسر نشد.',
            'reason_result_not_usable' => '⚠️ با این روش، سرویس پس از اجرا همچنان قابل استفاده نخواهد بود ({detail}). روش یا محصول دیگری انتخاب کنید؛ مبلغی کسر نشد.',
            'reason_result_detail_traffic' => 'حجم قابل استفاده باقی نمی‌ماند',
            'reason_result_detail_time' => 'سرویس منقضی باقی می‌ماند',
            'reason_invalid_purchase' => '❌ مقدار حجم یا زمان خریداری‌شده نامعتبر است.',
            'reason_unknown_method' => '❌ روش تمدید تنظیم‌شده برای این پنل نامعتبر است؛ لطفاً به مدیریت اطلاع دهید. مبلغی کسر نشد.',
            'reason_panel_unreachable' => '⚠️ اطلاعات فعلی سرویس از پنل دریافت نشد؛ پیش‌نمایش در دسترس نیست. هنگام تأیید، وضعیت سرویس دوباره از پنل خوانده می‌شود.',
            'reason_panel_state_unavailable' => '⚠️ وضعیت فعلی سرویس از پنل قابل خواندن نیست؛ لطفاً چند دقیقه بعد دوباره تلاش کنید. مبلغی کسر نشد.',
            'reason_user_missing' => '❌ این سرویس در پنل یافت نشد؛ لطفاً با پشتیبانی در ارتباط باشید.',
            'reason_renewal_in_progress' => '⏳ عملیات دیگری روی این سرویس در حال انجام است؛ چند لحظه بعد دوباره تلاش کنید. مبلغی کسر نشد.',
            'reason_queued_exists' => '❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد.',
            'reason_trigger_unavailable' => '❌ زمان فعال‌سازی انتخاب‌شده برای این سرویس در دسترس نیست: {detail}',
            'reason_trigger_date_required' => '❌ برای فعال‌سازی در تاریخ مشخص، ابتدا تاریخ فعال‌سازی را تعیین کنید.',
            'reason_trigger_date_invalid' => '❌ تاریخ فعال‌سازی نامعتبر است؛ تاریخ باید در آینده و حداکثر تا یک سال بعد باشد.',
            'reason_policy_invalid' => '❌ سیاست اجرای انتخاب‌شده نامعتبر است.',
            'reason_panel_policy_unsupported' => '⚠️ این روش تمدید با محدودیت‌های پنل JovadPanel برای وضعیت فعلی این سرویس هم‌خوان نیست (مثلاً کاهش حجم سرویس فعال یا حفظ مصرف یک سرویس تمام‌شده). روش یا محصول دیگری انتخاب کنید؛ مبلغی کسر نشد.',
            'reason_reservation_unsupported' => '❌ رزرو اشتراک برای این نوع سرویس پشتیبانی نمی‌شود.',
            'reason_generic' => '❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید',
            'pending_verification' => '⏳ درخواست تمدید به پنل ارسال شد اما نتیجه نهایی هنوز تأیید نشده است. وضعیت به‌صورت خودکار بررسی می‌شود و نیازی به پرداخت مجدد نیست؛ در صورت بروز مشکل، پشتیبانی پیگیری می‌کند.',
            'preview_title' => '🔎 پیش‌نمایش تمدید',
            'preview_policy' => '⚙️ روش تمدید : {label}',
            'preview_traffic_kept' => '🔋 حجم باقی‌مانده فعلی حفظ می‌شود',
            'preview_traffic_discarded' => '🗑 حجم باقی‌مانده فعلی حذف می‌شود و فقط حجم خریداری‌شده باقی می‌ماند',
            'preview_traffic_unchanged' => '📦 حجم کل و مصرف بدون تغییر می‌ماند',
            'preview_traffic_unlimited' => '♾ حجم سرویس نامحدود می‌ماند',
            'preview_time_kept' => '⏳ زمان باقی‌مانده فعلی حفظ می‌شود',
            'preview_time_discarded' => '🗑 زمان باقی‌مانده فعلی حذف می‌شود و زمان خریداری‌شده از لحظه اجرا شروع می‌شود',
            'preview_time_unchanged' => '📅 تاریخ انقضا بدون تغییر می‌ماند',
            'preview_time_unlimited' => '♾ زمان سرویس نامحدود می‌ماند',
            'preview_usage_reset' => '♻️ شمارنده مصرف : صفر می‌شود',
            'preview_usage_kept' => '♻️ شمارنده مصرف : حفظ می‌شود',
            'preview_usage_note' => 'ℹ️ صفر شدن شمارنده مصرف به معنی حذف حجم باقی‌مانده نیست؛ حجم باقی‌مانده به حجم کل جدید منتقل می‌شود.',
            'preview_current_expire' => '⏳ انقضای فعلی : {value}',
            'preview_new_expire' => '⏳ انقضای جدید : {value}',
            'preview_current_limit' => '📦 حجم کل فعلی : {value}',
            'preview_used' => '📉 حجم مصرف‌شده : {value}',
            'preview_current_remaining' => '🔋 حجم باقی‌مانده فعلی : {value}',
            'preview_purchased_time' => '➕ زمان خریداری‌شده : {value}',
            'preview_purchased_volume' => '➕ حجم خریداری‌شده : {value}',
            'preview_new_limit' => '📦 حجم کل جدید : {value}',
            'preview_new_remaining' => '🔋 حجم قابل استفاده جدید : {value}',
            'preview_pending' => 'ℹ️ سرویس در انتظار اولین اتصال است؛ مدت سرویس پس از اولین اتصال شروع می‌شود ({value}).',
            'preview_enable' => '✅ سرویس منقضی/تمام‌شده پس از تمدید دوباره فعال می‌شود',
            'reservation_title' => '📌 این خرید به‌صورت رزرو ثبت می‌شود و سرویس فعلی تا زمان فعال‌سازی تغییر نمی‌کند.',
            'reservation_trigger' => '⏱ زمان فعال‌سازی : {label}',
            'reservation_policy' => '⚙️ سیاست اجرا : {label}',
            'reservation_note' => 'ℹ️ حجم و زمان باقی‌مانده در لحظه فعال‌سازی محاسبه می‌شود؛ مصرف شما تا آن لحظه از بسته رزرو کم نمی‌شود.',
            'reservation_estimate' => '🔎 برآورد در صورت فعال‌سازی در همین لحظه:',
            'reservation_estimate_unavailable' => 'ℹ️ برآورد لحظه‌ای در دسترس نیست؛ مقادیر در زمان فعال‌سازی محاسبه می‌شود.',
            'mark_on' => '✅',
            'mark_off' => '⬜️',
            'button_outdated' => '⚠️ این دکمه منقضی شده است؛ لطفاً مراحل تمدید را دوباره شروع کنید.',
            'admin_title' => '🗓 تنظیمات رزرو اشتراک پنل {panel}',
            'admin_section_policy' => '⚙️ سیاست تمدید (یک گزینه)',
            'admin_section_conditions' => '⏱ شرایط فعال‌سازی (چند گزینه؛ هرکدام زودتر)',
            'admin_btn_date' => '📅 تغییر تاریخ',
            'admin_summary' => '📌 تنظیم فعلی: {text}',
            'admin_timezone_note' => '🕒 تاریخ‌ها به وقت {tz} ثبت و نمایش داده می‌شوند.',
            'admin_conditions_empty' => '❌ حداقل یک شرط فعال‌سازی را انتخاب کنید.',
            'admin_date_required' => '❌ برای شرط «تاریخ مشخص» ابتدا تاریخ را تعیین کنید.',
            'admin_date_past' => '❌ تاریخ انتخاب‌شده گذشته است؛ یک تاریخ آینده تعیین کنید.',
            'admin_date_invalid' => '❌ تاریخ نامعتبر است. تاریخ و ساعت را به شکل 1405/08/01 12:00 (شمسی) یا 2026-10-23 12:00 و حداکثر تا یک سال آینده ارسال کنید.',
            'admin_date_prompt' => '📅 تاریخ و ساعت فعال‌سازی را به وقت {tz} ارسال کنید؛ مثال: 1405/08/01 12:00 (شمسی) یا 2026-10-23 12:00. ساعت اختیاری است (پیش‌فرض 00:00).',
            'admin_date_saved' => '✅ تاریخ فعال‌سازی تعیین شد: {date}',
            'cond_traffic' => 'اتمام حجم',
            'cond_expire' => 'پایان زمان',
            'cond_date' => 'تاریخ مشخص',
            'cond_date_at' => 'تاریخ مشخص ({date})',
            'activation_phrase_traffic' => 'پایان حجم',
            'activation_phrase_expire' => 'پایان زمان',
            'activation_phrase_traffic_expire' => 'پایان حجم یا زمان سرویس فعلی',
            'activation_phrase_date' => 'رسیدن تاریخ {date}',
            'activation_single_traffic' => 'پایان حجم سرویس فعلی',
            'activation_single_expire' => 'پایان زمان سرویس فعلی',
            'activation_single' => 'پس از {phrase} فعال می‌شود',
            'activation_multi' => 'پس از {phrase}، هرکدام زودتر باشد، فعال می‌شود',
            'activation_joiner' => ' یا ',
            'reservation_confirmed' => '✅ اشتراک شما رزرو شد',
            'reason_trigger_date_expired' => '❌ تاریخ فعال‌سازی رزرو که مدیریت تعیین کرده گذشته است و رزرو جدید با این تاریخ ثبت نمی‌شود؛ لطفاً با پشتیبانی در ارتباط باشید. مبلغی کسر نشد.',
            'admin_hint' => 'یک سیاست تمدید و یک یا چند شرط فعال‌سازی انتخاب کنید؛ رزرو با برقراری هرکدام از شرط‌ها (هرکدام زودتر) فعال می‌شود. سپس «ذخیره تنظیمات» را بزنید. این تنظیمات برای همه رزروهای جدید این پنل اعمال می‌شود و مشتری آن را تغییر نمی‌دهد.',
            'admin_btn_save' => '💾 ذخیره تنظیمات',
            'admin_btn_back' => '🔙 بازگشت',
            'admin_saved' => '✅ روش تمدید «رزرو اشتراک» و تنظیمات آن ذخیره شد.',
            'admin_cancelled' => 'تنظیمات رزرو ذخیره نشد.',
            'admin_pending_unsupported' => 'ℹ️ این نوع پنل تغییر مدت سرویس‌های «در انتظار اولین اتصال» را پشتیبانی نمی‌کند؛ رزرو چنین سرویسی با سیاست‌های دارای زمان تا اولین اتصال قابل اجرا نیست و به پشتیبانی ارجاع می‌شود.',
            'admin_outdated' => '⚠️ این دکمه منقضی شده است؛ لطفاً دوباره از منوی پنل وارد شوید.',
            'reservation_date_unset' => 'تعیین نشده',
            'status_pending' => 'در انتظار فعال‌سازی',
            'status_processing' => 'در حال اجرا',
            'status_retry' => 'خطای موقت؛ تلاش مجدد خودکار',
            'status_reconciling' => 'در حال بررسی نتیجه',
            'status_applied' => 'فعال شد',
            'status_failed' => 'نیازمند بررسی پشتیبانی',
            'status_cancelled' => 'لغو شده',
            'reservation_notice' => '📌 رزرو اشتراک: {status}\n⏱ زمان فعال‌سازی: {trigger}\n⚙️ سیاست اجرا: {policy}',
            'reservation_failed_user_tpl' => "⚠️ فعال‌سازی رزرو اشتراک سرویس {username} با مشکل مواجه شد و برای بررسی به پشتیبانی ارسال شد. بسته رزرو شما محفوظ است.",
            'reconciled_user_tpl' => '✅ تمدید سرویس {username} در پنل تأیید و اعمال شد.',
            'success_same_link' => "🔗 لینک اشتراک شما همان لینک قبلی است و نیازی به دریافت کد یا کانفیگ جدید ندارید.\nبرای دریافت اطلاعات و کانفیگ‌های به‌روز، در برنامهٔ اتصال خود یک بار «به‌روزرسانی اشتراک» را بزنید.",
            'result_line_expire' => '⏳ انقضا : {before} ← {after}',
            'result_line_limit' => '📦 حجم کل : {before} ← {after}',
            'result_line_remaining' => '🔋 حجم باقی‌مانده : {before} ← {after}',
            'adm_title' => '🔄 تمدید سرویس توسط مدیر',
            'adm_service_lines' => "👤 نام کاربری : <code>{username}</code>\n📍 پنل : {panel}",
            'adm_state_unchanged' => 'بدون تغییر',
            'adm_state_unlimited' => 'نامحدود (صفر)',
            'adm_state_unset' => 'انتخاب نشده',
            'adm_package_product' => '«{name}» ({volume} / {days})',
            'adm_package_custom' => 'حجم : {volume} | زمان : {days}',
            'adm_btn_back' => '◀️ مرحله قبل',
            'adm_btn_service' => '🧾 بازگشت به مدیریت سرویس',
            'adm_btn_admin' => '🏠 منوی مدیریت',
            'adm_btn_cancel' => '❌ لغو تمدید',
            'adm_btn_product' => '🛍 انتخاب محصول',
            'adm_btn_custom' => '✍️ حجم و زمان دلخواه',
            'adm_btn_keep_traffic' => '📦 حجم بدون تغییر',
            'adm_btn_keep_time' => '📅 انقضا بدون تغییر',
            'adm_btn_continue' => '➡️ ادامه',
            'adm_btn_notify_on' => 'اطلاع‌رسانی به مشتری',
            'adm_btn_notify_off' => 'بدون اطلاع‌رسانی',
            'adm_btn_preview' => '📋 پیش‌نمایش',
            'adm_btn_refresh' => '♻️ بروزرسانی پیش‌نمایش',
            'adm_btn_confirm' => '✅ تایید و اجرا',
            'adm_src_prompt' => 'بسته تمدید را چگونه تعیین می‌کنید؟',
            'adm_prod_prompt' => '🛍 یکی از محصولات مجاز این پنل را انتخاب کنید. حجم و زمان محصول اعمال می‌شود و مبلغی از مشتری کسر نمی‌شود.',
            'adm_prod_empty' => '⚠️ محصول مجازی برای این پنل یافت نشد؛ از حجم و زمان دلخواه استفاده کنید.',
            'adm_vol_prompt' => "📦 حجم تمدید را به گیگابایت و به‌صورت عدد ارسال کنید.\n\n0 = حجم نامحدود (ارسال صفر به معنی «بدون تغییر» نیست)\n«حجم بدون تغییر» = حجم کل و مصرف فعلی دست‌نخورده می‌ماند و حجمی اضافه یا ریست نمی‌شود",
            'adm_days_prompt' => "📅 زمان تمدید را به روز و به‌صورت عدد ارسال کنید.\n\n0 = زمان نامحدود (ارسال صفر به معنی «بدون تغییر» نیست)\n«انقضا بدون تغییر» = تاریخ انقضا یا مدت انتظار اولین اتصال دست‌نخورده می‌ماند و روزی اضافه یا شروع مجدد نمی‌شود",
            'adm_current_choice' => 'انتخاب فعلی : {value}',
            'adm_invalid_number' => '❌ فقط یک عدد صحیح (صفر یا بیشتر) ارسال کنید.',
            'adm_both_unchanged' => '⚠️ حجم و انقضا نمی‌توانند هم‌زمان «بدون تغییر» باشند؛ حداقل یکی باید تغییر کند.',
            'adm_product_missing' => '⚠️ محصول انتخاب‌شده دیگر برای این پنل در دسترس نیست.',
            'adm_incomplete' => '⚠️ انتخاب‌های تمدید کامل نیست.',
            'adm_method_prompt' => '⚙️ روش تمدید را برای همین عملیات انتخاب کنید (تنظیم روش تمدید پنل تغییر نمی‌کند):',
            'adm_method_override_note' => 'ℹ️ گزینه «بدون تغییر» همان بُعد (حجم یا زمان) را در روش انتخابی بازنویسی می‌کند و مقدار فعلی آن بدون تغییر می‌ماند.',
            'adm_method_required' => 'ابتدا یک روش تمدید انتخاب کنید.',
            'adm_opt_prompt' => '🔔 مشخص کنید مشتری از تمدید مطلع شود یا نه (تمدید همیشه فوری اجرا می‌شود):',
            'adm_preview_title' => '🔎 پیش‌نمایش تمدید توسط مدیر',
            'adm_preview_package' => '🛍 بسته : {value}',
            'adm_preview_method' => '⚙️ روش تمدید : ✅ {label}',
            'adm_preview_effective' => '↳ روش اعمال‌شده با احتساب «بدون تغییر» : {label}',
            'adm_preview_exec_now' => '🚦 اجرا : فوری',
            'adm_preview_free' => '💰 هزینه : تمدید رایگان توسط مدیر؛ مبلغی از کیف پول یا حساب مشتری کسر نمی‌شود',
            'adm_preview_notify_on' => '🔔 اطلاع‌رسانی به مشتری : بله',
            'adm_preview_notify_off' => '🔕 اطلاع‌رسانی به مشتری : خیر',
            'adm_preview_current' => '📍 وضعیت فعلی سرویس',
            'adm_preview_pending_current' => '⏳ مدت پس از اولین اتصال : {value}',
            'adm_preview_expected' => '📍 وضعیت پس از اجرا',
            'adm_preview_new_used' => '📉 مصرف پس از اجرا : {value}',
            'adm_preview_unchanged' => '🔒 بدون تغییر : {value}',
            'adm_unchanged_traffic' => 'حجم کل و مصرف',
            'adm_unchanged_time' => 'تاریخ انقضا',
            'adm_none' => 'هیچ‌کدام',
            'adm_revise_hint' => 'یک مرحله به عقب بازگردید و انتخاب‌ها را اصلاح کنید؛ چیزی اعمال نشده است.',
            'adm_state_changed' => '⚠️ وضعیت سرویس از زمان پیش‌نمایش تغییر کرده است؛ پیش‌نمایش جدید را بررسی و دوباره تایید کنید. چیزی اعمال نشد.',
            'adm_already_running' => 'این عملیات قبلاً تایید شده و در حال اجرا یا انجام‌شده است.',
            'adm_cancelled' => '❌ تمدید لغو شد و هیچ تغییری اعمال نشد.',
            'adm_error_start' => '❌ تمدید با خطا مواجه گردید؛ مراحل تمدید را مجددا انجام دهید.',
            'adm_error_manual_panel' => '⚠️ این پنل از نوع فروش دستی یا انبار ملی است و تمدید حجم/زمان با روش‌های این فرایند برای آن پشتیبانی نمی‌شود.',
            'adm_error_status' => '⚠️ وضعیت فعلی سفارش ({status}) اجازه تمدید نمی‌دهد.',
            'adm_failed_generic' => '❌ خطایی در تمدید سرویس رخ داد و چیزی اعمال‌شده تایید نشد؛ لاگ‌ها را بررسی کنید.',
            'adm_failed_stock' => '❌ موجودی انبار فروش دستی برای این محصول تمام شده است.',
            'adm_admin_done' => '✅ تمدید سرویس انجام و در پنل تایید شد.',
            'adm_notified_yes' => '🔔 پیام اطلاع‌رسانی برای مشتری ارسال شد.',
            'adm_notified_no' => '🔕 به انتخاب شما مشتری مطلع نشد.',
            'adm_finalize_pending' => '⏳ ثبت نهایی در فاکتور و تاریخچه به‌صورت خودکار تکمیل می‌شود؛ تمدید در پنل دوباره ارسال نخواهد شد.',
            'adm_notified_later' => '🔔 پیام اطلاع‌رسانی پس از ثبت نهایی برای مشتری ارسال می‌شود.',
            'adm_notified_failed' => '⚠️ ارسال پیام به مشتری ممکن نشد (مثلاً ربات را مسدود کرده است) و دیگر تلاش نمی‌شود؛ تمدید و ثبت فاکتور تکمیل شده است.',
            'adm_notified_retrying' => '⏳ ارسال پیام به مشتری فعلاً انجام نشد و به‌صورت خودکار و محدود دوباره تلاش می‌شود؛ تمدید و ثبت فاکتور تکمیل شده است. ممکن است در موارد نادر پیام تکراری برسد.',
            'adm_customer_done' => '✅ سرویس {username} توسط مدیریت تمدید شد.',
            'adm_customer_activated' => '✅ رزرو تمدید سرویس {username} فعال شد و سرویس شما طبق رزرو تمدید گردید.',
            'adm_report_done' => '⭕️ ادمین سرویس کاربر را تمدید کرد.',
            'unlimited' => 'نامحدود',
            'days_unit' => 'روز',
            'gb_unit' => 'گیگ',
        ];
    }
}

if (!function_exists('rxRenewalText')) {
    function rxRenewalText(string $key, array $vars = []): string
    {
        $defaults = rxRenewalTextDefaults();
        $default = (string) ($defaults[$key] ?? $key);
        $text = $default;
        if (function_exists('faoxima_textbot_get')) {
            try {
                $text = (string) faoxima_textbot_get('dyn_renewal_' . $key, $default);
            } catch (Throwable $e) {
                $text = $default;
            }
        }
        $text = str_replace('\n', "\n", $text);
        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }
}

if (!function_exists('rx_rn_panel_number')) {
    function rx_rn_panel_number(array $data, string $key, bool $nullIsZero)
    {
        if (!array_key_exists($key, $data)) {
            return false;
        }
        $value = $data[$key];
        if ($value === null) {
            return $nullIsZero ? 0 : false;
        }
        if (is_bool($value) || !is_numeric($value)) {
            return false;
        }
        return (int) round((float) $value);
    }
}

if (!function_exists('rx_rn_panel_type')) {
    function rx_rn_panel_type(string $namePanel): string
    {
        if ($namePanel === '' || !function_exists('select')) {
            return '';
        }
        try {
            $row = select('marzban_panel', 'type', 'name_panel', $namePanel, 'select');
        } catch (Throwable $e) {
            return '';
        }
        return is_array($row) ? (string) ($row['type'] ?? '') : '';
    }
}

if (!function_exists('rx_pf_snapshot_from_datauser')) {
    function rx_pf_snapshot_from_datauser($data, string $panelType = ''): array
    {
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'invalid_panel_response'];
        }
        if (($data['status'] ?? '') === 'Unsuccessful') {
            $msg = $data['msg'] ?? '';
            $msg = is_scalar($msg) ? (string) $msg : (string) json_encode($msg, JSON_UNESCAPED_UNICODE);
            if (rx_pf_user_missing_message($msg)) {
                return ['ok' => true, 'exists' => false, 'error' => $msg];
            }
            return ['ok' => false, 'error' => $msg !== '' ? $msg : 'panel_unsuccessful'];
        }
        if (!isset($data['username']) || (string) $data['username'] === '') {
            return ['ok' => false, 'error' => 'panel_response_without_username'];
        }
        $limit = rx_rn_panel_number($data, 'data_limit', true);
        $used = rx_rn_panel_number($data, 'used_traffic', false);
        $expire = rx_rn_panel_number($data, 'expire', true);
        if ($limit === false || $used === false || $expire === false || $limit < 0 || $expire < 0) {
            return ['ok' => false, 'error' => 'malformed_panel_values'];
        }
        if (in_array($panelType, ['remnawave', 'guard'], true) && $expire > time() + RX_RN_FAR_FUTURE) {
            $expire = 0;
        }
        $status = strtolower(trim((string) ($data['status'] ?? '')));
        $duration = null;
        if (isset($data['on_hold_expire_duration']) && is_numeric($data['on_hold_expire_duration']) && (int) $data['on_hold_expire_duration'] > 0) {
            $duration = (int) $data['on_hold_expire_duration'];
        }
        return [
            'ok' => true,
            'exists' => true,
            'username' => (string) $data['username'],
            'data_limit' => $limit,
            'expire' => $expire,
            'used_traffic' => max(0, $used),
            'status' => $status,
            'pending' => $status === 'on_hold',
            'pending_duration' => $duration,
            'raw' => $data,
        ];
    }
}

if (!function_exists('rx_pf_panel_snapshot')) {
    function rx_pf_panel_snapshot($managePanel, string $namePanel, string $username, string $panelType = ''): array
    {
        if (!is_object($managePanel)) {
            return ['ok' => false, 'error' => 'manage_panel_unavailable'];
        }
        try {
            $data = $managePanel->DataUser($namePanel, $username);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return rx_pf_snapshot_from_datauser($data, $panelType !== '' ? $panelType : rx_rn_panel_type($namePanel));
    }
}

if (!function_exists('rx_pf_limits')) {
    function rx_pf_limits(array $snapshot): array
    {
        return [
            'data_limit' => rx_pf_int($snapshot['data_limit'] ?? 0),
            'expire' => rx_pf_int($snapshot['expire'] ?? 0),
            'used_traffic' => rx_pf_int($snapshot['used_traffic'] ?? 0),
        ];
    }
}

if (!function_exists('rx_pf_state_matches')) {
    function rx_pf_state_matches(array $snapshot, array $expected): bool
    {
        if (!empty($expected['rx_renewal_plan'])) {
            return !empty(rxRenewalVerify($snapshot, $expected)['ok']);
        }
        if (array_key_exists('data_limit', $expected) && rx_pf_int($snapshot['data_limit'] ?? 0) !== rx_pf_int($expected['data_limit'])) {
            return false;
        }
        if (array_key_exists('expire', $expected)) {
            $current = rx_pf_int($snapshot['expire'] ?? 0);
            $wanted = rx_pf_int($expected['expire']);
            if ($wanted <= 0 || $current <= 0) {
                return $wanted <= 0 && $current <= 0;
            }
            if (abs($current - $wanted) > RX_PF_EXPIRE_TOLERANCE) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('rx_pf_classify')) {
    function rx_pf_classify(array $snapshot, ?array $before, ?array $target): string
    {
        if (is_array($target) && !empty($target['rx_renewal_plan'])) {
            return rxRenewalClassify($snapshot, $target);
        }
        if (empty($snapshot['ok'])) {
            return 'unknown';
        }
        if (empty($snapshot['exists']) || !is_array($before) || !is_array($target)) {
            return 'ambiguous';
        }
        $dims = array_values(array_intersect(['data_limit', 'expire'], array_keys($target)));
        if ($dims === []) {
            return 'ambiguous';
        }
        $wanted = array_intersect_key($target, array_flip($dims));
        $original = array_intersect_key($before, array_flip($dims));
        if (count($original) !== count($dims)) {
            return 'ambiguous';
        }
        $isTarget = rx_pf_state_matches($snapshot, $wanted);
        $isBefore = rx_pf_state_matches($snapshot, $original);
        if ($isTarget && !$isBefore) {
            return 'target';
        }
        if ($isBefore && !$isTarget) {
            return 'before';
        }
        return 'ambiguous';
    }
}

if (!function_exists('rxRenewalPolicies')) {
    function rxRenewalPolicies(): array
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
}

if (!function_exists('rxRenewalAdminBasePolicies')) {
    function rxRenewalAdminBasePolicies(): array
    {
        return ['carry_all', 'reset_all', 'reset_traffic_append_time', 'carry_traffic_reset_time'];
    }
}

if (!function_exists('rxRenewalAdminPolicy')) {
    function rxRenewalAdminPolicy(string $basePolicy, bool $keepTraffic, bool $keepTime): ?string
    {
        $policies = rxRenewalPolicies();
        if (!in_array($basePolicy, rxRenewalAdminBasePolicies(), true) || !isset($policies[$basePolicy]) || ($keepTraffic && $keepTime)) {
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
}

if (!function_exists('rxRenewalAdminMethodLabel')) {
    function rxRenewalAdminMethodLabel(string $basePolicy): ?string
    {
        foreach (rxRenewalModes() as $label => $mode) {
            if (empty($mode['queued']) && ($mode['key'] ?? '') === $basePolicy) {
                return (string) $label;
            }
        }
        return null;
    }
}

if (!function_exists('rxRenewalPolicyAllowsUsageReset')) {
    function rxRenewalPolicyAllowsUsageReset(string $policy): bool
    {
        $policies = rxRenewalPolicies();
        return isset($policies[$policy]) && $policies[$policy]['traffic'] === 'carry';
    }
}

if (!function_exists('rxRenewalModes')) {
    function rxRenewalModes(): array
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
}

if (!function_exists('rxRenewalDefaultMethod')) {
    function rxRenewalDefaultMethod(): string
    {
        return 'ریست حجم و زمان';
    }
}

if (!function_exists('rxRenewalMode')) {
    function rxRenewalMode($method): ?array
    {
        $method = trim((string) $method);
        if ($method === '') {
            $method = rxRenewalDefaultMethod();
        }
        $modes = rxRenewalModes();
        if (!isset($modes[$method])) {
            return null;
        }
        return array_merge($modes[$method], ['method' => $method]);
    }
}

if (!function_exists('rxRenewalPolicyLabel')) {
    function rxRenewalPolicyLabel(string $policy, bool $resetUsage = false, bool $short = false): string
    {
        $label = rxRenewalText(($short ? 'policy_short_' : 'policy_') . $policy);
        if ($resetUsage && rxRenewalPolicyAllowsUsageReset($policy)) {
            $label .= rxRenewalText('policy_usage_reset_suffix');
        }
        return $label;
    }
}

if (!function_exists('rxRenewalModeLabel')) {
    function rxRenewalModeLabel(string $key): string
    {
        foreach (rxRenewalModes() as $mode) {
            if ($mode['key'] === $key && $mode['policy'] !== null) {
                return rxRenewalPolicyLabel($mode['policy'], !empty($mode['reset_usage']));
            }
        }
        return isset(rxRenewalPolicies()[$key]) ? rxRenewalPolicyLabel($key) : $key;
    }
}

if (!function_exists('rxRenewalIsCustomCode')) {
    function rxRenewalIsCustomCode($code): bool
    {
        return in_array(trim((string) $code), ['customvolume', 'custom_volume'], true);
    }
}

if (!function_exists('rxRenewalNormalizeProductCode')) {
    function rxRenewalNormalizeProductCode($code): string
    {
        return rxRenewalIsCustomCode($code) ? 'custom_volume' : trim((string) $code);
    }
}

if (!function_exists('rxRenewalPendingSupported')) {
    function rxRenewalPendingSupported($panelType): bool
    {
        return in_array((string) $panelType, ['marzban', 'pasarguard', 'rebecca', 'x-ui_single'], true);
    }
}

if (!function_exists('rxRenewalCompute')) {
    function rxRenewalCompute(int $now, string $policy, bool $resetUsageOption, array $before, $purchasedVolumeGb, $purchasedDays, array $ctx = []): array
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
        $policies = rxRenewalPolicies();
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
            'label' => rxRenewalPolicyLabel($policy, $resetUsage && $trafficMode === 'carry'),
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
}

if (!function_exists('rxRenewalReplan')) {
    function rxRenewalReplan(array $plan, array $before, int $now, array $ctx = []): array
    {
        return rxRenewalCompute(
            $now,
            (string) ($plan['policy'] ?? ''),
            !empty($plan['reset_usage_option']),
            $before,
            $plan['purchased_volume_gb'] ?? (((int) ($plan['purchased_volume_bytes'] ?? 0)) / RX_PF_GIB),
            $plan['purchased_days'] ?? 0,
            array_merge(['method' => (string) ($plan['method'] ?? '')], $ctx)
        );
    }
}

if (!function_exists('rxBuildRenewalTarget')) {
    function rxBuildRenewalTarget(int $now, $method, array $before, $purchasedVolumeGb, $purchasedDays, array $ctx = []): array
    {
        $mode = rxRenewalMode($method);
        if ($mode === null) {
            return ['ok' => false, 'reason' => 'unknown_renewal_method', 'method' => (string) $method, 'manual_review' => true, 'queued' => false, 'destructive' => false];
        }
        if (!empty($mode['queued'])) {
            $limit = rx_pf_int($before['data_limit'] ?? 0);
            return [
                'ok' => true,
                'reason' => 'ok',
                'method' => $mode['method'],
                'effective_method' => 'queued',
                'queued' => true,
                'reset_usage' => false,
                'before_expire' => rx_pf_int($before['expire'] ?? 0),
                'before_data_limit' => $limit,
                'before_used_traffic' => rx_pf_int($before['used_traffic'] ?? 0),
                'before_remaining' => $limit > 0 ? max(0, $limit - rx_pf_int($before['used_traffic'] ?? 0)) : null,
            ];
        }
        return rxRenewalCompute($now, (string) $mode['policy'], !empty($mode['reset_usage']), $before, $purchasedVolumeGb, $purchasedDays, array_merge(['method' => $mode['method']], $ctx));
    }
}

if (!function_exists('rxRenewalVerify')) {
    function rxRenewalVerify(array $after, array $plan): array
    {
        $jovadJournal = $after['raw']['jovad_renewal_state'] ?? ($after['jovad_renewal_state'] ?? null);
        $jovadPending = false;
        $jovadMatches = is_array($jovadJournal) && function_exists('jovadpanelRenewalSignature')
            && ($jovadJournal['signature'] ?? '') === jovadpanelRenewalSignature($plan);
        if ($jovadMatches) {
            foreach (($jovadJournal['steps'] ?? []) as $jovadStep) {
                if (($jovadStep['state'] ?? '') !== 'done') $jovadPending = true;
            }
        }
        if (function_exists('jovadpanelVerificationPlan')) {
            $plan = jovadpanelVerificationPlan($after, $plan);
        }
        $checks = [];
        if ($jovadPending) $checks['jovad_steps'] = false;
        if (empty($after['ok']) && array_key_exists('ok', $after)) {
            return ['ok' => false, 'checks' => ['readable' => false], 'reason' => 'unreadable'];
        }
        if (array_key_exists('exists', $after) && empty($after['exists'])) {
            return ['ok' => false, 'checks' => ['exists' => false], 'reason' => 'missing'];
        }
        $targetLimit = rx_pf_int($plan['data_limit'] ?? 0);
        $afterLimit = rx_pf_int($after['data_limit'] ?? 0);
        $checks['traffic'] = $targetLimit <= 0 ? $afterLimit <= 0 : ($afterLimit > 0 && abs($afterLimit - $targetLimit) <= RX_RN_TRAFFIC_TOLERANCE);
        $afterUsed = rx_pf_int($after['used_traffic'] ?? 0);
        $beforeUsed = rx_pf_int($plan['before_used_traffic'] ?? 0);
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
            $wantedDuration = rx_pf_int($plan['target_pending_duration'] ?? 0);
            $afterDuration = isset($after['pending_duration']) ? rx_pf_int($after['pending_duration']) : null;
            $checks['time'] = $afterPending && $afterDuration !== null && abs($afterDuration - $wantedDuration) <= RX_PF_EXPIRE_TOLERANCE;
        } else {
            $targetExpire = rx_pf_int($plan['expire'] ?? 0);
            $afterExpire = rx_pf_int($after['expire'] ?? 0);
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
}

if (!function_exists('rxRenewalClassify')) {
    function rxRenewalClassify(array $snapshot, array $plan): string
    {
        $jovadPlan = $plan;
        if (function_exists('jovadpanelVerificationPlan')) {
            $plan = jovadpanelVerificationPlan($snapshot, $plan);
        }
        if (empty($snapshot['ok'])) {
            return 'unknown';
        }
        if (empty($snapshot['exists'])) {
            return 'ambiguous';
        }
        if (!empty(rxRenewalVerify($snapshot, $jovadPlan)['ok'])) {
            return 'target';
        }
        $jovadJournal = $snapshot['raw']['jovad_renewal_state'] ?? null;
        if (is_array($jovadJournal) && function_exists('jovadpanelRenewalSignature')
            && ($jovadJournal['signature'] ?? '') === jovadpanelRenewalSignature($jovadPlan)) {
            return 'before';
        }
        $limitMatches = function ($value, $wanted) {
            $value = rx_pf_int($value);
            $wanted = rx_pf_int($wanted);
            return $wanted <= 0 ? $value <= 0 : ($value > 0 && abs($value - $wanted) <= RX_RN_TRAFFIC_TOLERANCE);
        };
        $expireMatches = function ($value, $wanted) {
            $value = rx_pf_int($value);
            $wanted = rx_pf_int($wanted);
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

if (!function_exists('rxRenewalFormatExpire')) {
    function rxRenewalFormatExpire($timestamp): string
    {
        $timestamp = rx_pf_int($timestamp);
        if ($timestamp <= 0) {
            return rxRenewalText('unlimited');
        }
        return function_exists('jdate') ? (string) jdate('Y/m/d H:i', $timestamp) : date('Y/m/d H:i', $timestamp);
    }
}

if (!function_exists('rxRenewalFormatBytes')) {
    function rxRenewalFormatBytes($bytes, bool $zeroIsUnlimited = true): string
    {
        if ($bytes === null) {
            return rxRenewalText('unlimited');
        }
        $bytes = rx_pf_int($bytes);
        if ($bytes <= 0 && $zeroIsUnlimited) {
            return rxRenewalText('unlimited');
        }
        $gb = max(0, $bytes) / RX_PF_GIB;
        return rtrim(rtrim(number_format($gb, 2, '.', ''), '0'), '.') . ' ' . rxRenewalText('gb_unit');
    }
}

if (!function_exists('rxRenewalFormatDays')) {
    function rxRenewalFormatDays($days): string
    {
        $days = (float) $days;
        if ($days <= 0) {
            return rxRenewalText('unlimited');
        }
        return rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.') . ' ' . rxRenewalText('days_unit');
    }
}

if (!function_exists('rxRenewalReasonText')) {
    function rxRenewalReasonText(string $reason, array $plan = []): string
    {
        switch ($reason) {
            case 'admin_blocked':
                return rxRenewalText('reason_admin_blocked');
            case 'pending_unsupported':
                return rxRenewalText('reason_pending_unsupported');
            case 'result_not_usable':
                return rxRenewalText('reason_result_not_usable', ['detail' => rxRenewalText(($plan['unusable'] ?? '') === 'traffic' ? 'reason_result_detail_traffic' : 'reason_result_detail_time')]);
            case 'invalid_purchase':
                return rxRenewalText('reason_invalid_purchase');
            case 'unknown_renewal_method':
            case 'unsupported_renewal_method':
            case 'unknown_policy':
                return rxRenewalText('reason_unknown_method');
            case 'panel_state_unavailable':
            case 'panel_read_failed':
                return rxRenewalText('reason_panel_state_unavailable');
            case 'user_missing':
                return rxRenewalText('reason_user_missing');
            case 'renewal_in_progress':
                return rxRenewalText('reason_renewal_in_progress');
            case 'queued_renewal_exists':
                return rxRenewalText('reason_queued_exists');
            case 'trigger_unavailable':
                return rxRenewalText('reason_trigger_unavailable', ['detail' => (string) ($plan['detail'] ?? '')]);
            case 'trigger_date_required':
                return rxRenewalText('reason_trigger_date_required');
            case 'trigger_date_invalid':
                return rxRenewalText('reason_trigger_date_invalid');
            case 'trigger_date_expired':
                return rxRenewalText('reason_trigger_date_expired');
            case 'policy_invalid':
                return rxRenewalText('reason_policy_invalid');
            case 'panel_policy_unsupported':
                return rxRenewalText('reason_panel_policy_unsupported');
            case 'panel_credit_suspended':
                return '⚠️ سرویس به‌دلیل اعتبار فروشنده متوقف است. پس از رفع مشکل اعتبار دوباره تلاش کنید؛ مبلغی کسر نشد.';
            case 'reservation_unsupported':
                return rxRenewalText('reason_reservation_unsupported');
        }
        return rxRenewalText('reason_generic');
    }
}

if (!function_exists('rxRenewalFailureKnown')) {
    function rxRenewalFailureKnown($extend): bool
    {
        $code = is_array($extend) ? (string) ($extend['code'] ?? '') : '';
        if (in_array($code, ['panel_policy_unsupported', 'panel_credit_suspended'], true)) return true;
        return in_array($code, ['admin_blocked', 'pending_unsupported', 'result_not_usable', 'invalid_purchase', 'unknown_renewal_method', 'unsupported_renewal_method', 'unknown_policy', 'panel_state_unavailable', 'panel_read_failed', 'user_missing', 'renewal_in_progress', 'queued_renewal_exists', 'trigger_unavailable', 'trigger_date_required', 'trigger_date_invalid', 'trigger_date_expired', 'policy_invalid', 'reservation_unsupported'], true);
    }
}

if (!function_exists('rxRenewalFailureText')) {
    function rxRenewalFailureText($extend, string $default = ''): string
    {
        $code = is_array($extend) ? (string) ($extend['code'] ?? '') : '';
        if (rxRenewalFailureKnown($extend)) {
            return rxRenewalReasonText($code, is_array($extend['plan'] ?? null) ? $extend['plan'] : (array) $extend);
        }
        return $default !== '' ? $default : rxRenewalText('reason_generic');
    }
}

if (!function_exists('rxRenewalIsPending')) {
    function rxRenewalIsPending($extend): bool
    {
        return is_array($extend) && !empty($extend['pending_verification']);
    }
}

if (!function_exists('rxRenewalPlanLines')) {
    function rxRenewalPlanLines(array $plan): array
    {
        $lines = [];
        $lines[] = rxRenewalText('preview_policy', ['label' => (string) ($plan['label'] ?? '')]);
        $trafficMode = (string) ($plan['traffic_mode'] ?? '');
        $timeMode = (string) ($plan['time_mode'] ?? '');
        if ($trafficMode === 'keep') {
            $lines[] = rxRenewalText('preview_traffic_unchanged');
        } elseif (rx_pf_int($plan['data_limit'] ?? 0) <= 0) {
            $lines[] = rxRenewalText('preview_traffic_unlimited');
        } else {
            $lines[] = rxRenewalText($trafficMode === 'reset' ? 'preview_traffic_discarded' : 'preview_traffic_kept');
        }
        if ($timeMode === 'keep') {
            $lines[] = rxRenewalText('preview_time_unchanged');
        } elseif (empty($plan['target_pending']) && rx_pf_int($plan['expire'] ?? 0) <= 0) {
            $lines[] = rxRenewalText('preview_time_unlimited');
        } else {
            $lines[] = rxRenewalText($timeMode === 'reset' ? 'preview_time_discarded' : 'preview_time_kept');
        }
        $lines[] = rxRenewalText(!empty($plan['reset_usage']) ? 'preview_usage_reset' : 'preview_usage_kept');
        if (!empty($plan['reset_usage']) && $trafficMode === 'carry') {
            $lines[] = rxRenewalText('preview_usage_note');
        }
        $lines[] = rxRenewalText('preview_current_expire', ['value' => !empty($plan['before_pending']) ? rxRenewalFormatDays(((int) ($plan['before_pending_duration'] ?? 0)) / 86400) : rxRenewalFormatExpire($plan['before_expire'] ?? 0)]);
        if (!empty($plan['target_pending'])) {
            $lines[] = rxRenewalText('preview_pending', ['value' => rxRenewalFormatDays(((int) ($plan['target_pending_duration'] ?? 0)) / 86400)]);
        } else {
            $lines[] = rxRenewalText('preview_new_expire', ['value' => rxRenewalFormatExpire($plan['expire'] ?? 0)]);
        }
        $lines[] = rxRenewalText('preview_current_limit', ['value' => rxRenewalFormatBytes($plan['before_data_limit'] ?? 0)]);
        $lines[] = rxRenewalText('preview_used', ['value' => rxRenewalFormatBytes($plan['before_used_traffic'] ?? 0, false)]);
        $lines[] = rxRenewalText('preview_current_remaining', ['value' => rxRenewalFormatBytes($plan['before_remaining'] ?? null, false)]);
        $lines[] = rxRenewalText('preview_purchased_time', ['value' => rxRenewalFormatDays($plan['purchased_days'] ?? 0)]);
        $lines[] = rxRenewalText('preview_purchased_volume', ['value' => rxRenewalFormatBytes($plan['purchased_volume_bytes'] ?? 0)]);
        $lines[] = rxRenewalText('preview_new_limit', ['value' => rxRenewalFormatBytes($plan['data_limit'] ?? 0)]);
        $lines[] = rxRenewalText('preview_new_remaining', ['value' => rxRenewalFormatBytes($plan['target_remaining'] ?? null)]);
        if (!empty($plan['enable'])) {
            $lines[] = rxRenewalText('preview_enable');
        }
        return $lines;
    }
}

if (!function_exists('rxRenewalPreviewText')) {
    function rxRenewalPreviewText(array $plan): string
    {
        if (empty($plan['ok'])) {
            return rxRenewalReasonText((string) ($plan['reason'] ?? ''), $plan);
        }
        if (!empty($plan['queued'])) {
            return rxRenewalText('reservation_title') . "\n"
                . rxRenewalText('preview_current_expire', ['value' => rxRenewalFormatExpire($plan['before_expire'] ?? 0)]) . "\n"
                . rxRenewalText('preview_current_remaining', ['value' => rxRenewalFormatBytes($plan['before_remaining'] ?? null, false)]);
        }
        return rxRenewalText('preview_title') . "\n" . implode("\n", rxRenewalPlanLines($plan));
    }
}

if (!function_exists('rxRenewalContext')) {
    function rxRenewalContext($panel, string $username): array
    {
        $type = is_array($panel) ? (string) ($panel['type'] ?? '') : '';
        $adminBlocked = false;
        $invoiceId = null;
        $invoiceStatus = null;
        global $pdo;
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
        return ['pending_supported' => rxRenewalPendingSupported($type), 'admin_blocked' => $adminBlocked, 'panel_type' => $type, 'invoice_id' => $invoiceId, 'invoice_status' => $invoiceStatus];
    }
}

if (!function_exists('rxRenewalPlanForSnapshot')) {
    function rxRenewalPlanForSnapshot(array $snapshot, $panel, string $username, string $policy, bool $resetUsage, $volumeGb, $days, string $method = '', ?int $now = null, array $logCtx = []): array
    {
        $ctx = rxRenewalContext($panel, $username);
        $ctx['method'] = $method;
        $plan = rxRenewalCompute($now ?? time(), $policy, $resetUsage, $snapshot, $volumeGb, $days, $ctx);
        if (empty($plan['ok']) && empty($logCtx['quiet'])) {
            rx_renewal_log((string) ($logCtx['stage'] ?? 'plan'), (string) ($plan['reason'] ?? 'plan_refused'), array_merge($logCtx, [
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
}

if (!function_exists('rxRenewalPreviewForService')) {
    function rxRenewalPreviewForService($managePanel, $panel, string $username, $purchasedVolumeGb, $purchasedDays, array $opts = []): array
    {
        if (!is_array($panel) || ($panel['type'] ?? '') === 'Manualsale' || (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel))) {
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
        $mode = rxRenewalMode($panel['Methodextend'] ?? '');
        if ($mode === null) {
            $plan = ['ok' => false, 'reason' => 'unknown_renewal_method'];
            rx_renewal_log($logCtx['stage'], 'unknown_renewal_method', $logCtx);
            return ['ok' => false, 'blocked' => true, 'plan' => $plan, 'reason' => 'unknown_renewal_method', 'text' => rxRenewalReasonText('unknown_renewal_method'), 'mode' => 'immediate'];
        }
        $snapshot = is_array($opts['snapshot'] ?? null) ? $opts['snapshot'] : rx_pf_panel_snapshot($managePanel, (string) ($panel['name_panel'] ?? ''), $username, (string) ($panel['type'] ?? ''));
        if (!empty($snapshot['ok']) && empty($snapshot['exists'])) {
            rx_renewal_log($logCtx['stage'], 'user_missing', $logCtx + ['snapshot_error' => $snapshot['error'] ?? null]);
            return ['ok' => false, 'blocked' => true, 'plan' => null, 'reason' => 'user_missing', 'text' => rxRenewalReasonText('user_missing'), 'mode' => !empty($mode['queued']) ? 'reservation' : 'immediate'];
        }
        $snapshotOk = !empty($snapshot['ok']);
        if (!empty($mode['queued'])) {
            return rxReservationPreview($panel, $username, $snapshotOk ? $snapshot : null, $purchasedVolumeGb, $purchasedDays, $opts);
        }
        if (!$snapshotOk) {
            rx_renewal_log($logCtx['stage'], 'panel_unreachable', $logCtx + ['snapshot' => $snapshot]);
            return ['ok' => false, 'blocked' => false, 'plan' => null, 'reason' => 'panel_unreachable', 'text' => rxRenewalText('reason_panel_unreachable'), 'mode' => 'immediate'];
        }
        $plan = rxRenewalPlanForSnapshot($snapshot, $panel, $username, (string) $mode['policy'], !empty($mode['reset_usage']), $purchasedVolumeGb, $purchasedDays, (string) $mode['method'], null, $logCtx);
        return [
            'ok' => !empty($plan['ok']),
            'blocked' => empty($plan['ok']),
            'plan' => $plan,
            'reason' => (string) ($plan['reason'] ?? ''),
            'text' => rxRenewalPreviewText($plan),
            'mode' => 'immediate',
        ];
    }
}

if (!function_exists('rxRenewalResultText')) {
    function rxRenewalResultText(array $before, array $after): string
    {
        $beforeLimit = rx_pf_int($before['data_limit'] ?? 0);
        $afterLimit = rx_pf_int($after['data_limit'] ?? 0);
        $beforeRemaining = $beforeLimit > 0 ? max(0, $beforeLimit - rx_pf_int($before['used_traffic'] ?? 0)) : null;
        $afterRemaining = $afterLimit > 0 ? max(0, $afterLimit - rx_pf_int($after['used_traffic'] ?? 0)) : null;
        return rxRenewalText('result_line_expire', ['before' => rxRenewalFormatExpire($before['expire'] ?? 0), 'after' => rxRenewalFormatExpire($after['expire'] ?? 0)]) . "\n"
            . rxRenewalText('result_line_limit', ['before' => rxRenewalFormatBytes($beforeLimit), 'after' => rxRenewalFormatBytes($afterLimit)]) . "\n"
            . rxRenewalText('result_line_remaining', ['before' => rxRenewalFormatBytes($beforeRemaining, false), 'after' => rxRenewalFormatBytes($afterRemaining, false)]);
    }
}

if (!function_exists('rxRenewalOutcomeText')) {
    function rxRenewalOutcomeText($extend): string
    {
        if (!is_array($extend) || !is_array($extend['rx_renewal'] ?? null)) {
            return rxRenewalIsPending($extend) ? rxRenewalText('pending_verification') : '';
        }
        $renewal = $extend['rx_renewal'];
        if (!empty($renewal['verified']) && is_array($renewal['before'] ?? null) && is_array($renewal['after'] ?? null)) {
            return rxRenewalResultText($renewal['before'], $renewal['after']);
        }
        return rxRenewalText('pending_verification');
    }
}

if (!function_exists('rxRenewalIsAppliedVerified')) {
    function rxRenewalIsAppliedVerified($extend, $afterState = null): bool
    {
        if (!is_array($extend) || empty($extend['status']) || rxRenewalIsPending($extend)) {
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
}

if (!function_exists('rxRenewalSameLinkSupported')) {
    function rxRenewalSameLinkSupported($panelType): bool
    {
        return in_array((string) $panelType, ['marzban', 'pasarguard'], true);
    }
}

if (!function_exists('rxRenewalSameLinkNote')) {
    function rxRenewalSameLinkNote($extend, $panelType, $afterState = null): string
    {
        if (!rxRenewalSameLinkSupported($panelType) || !rxRenewalIsAppliedVerified($extend, $afterState)) {
            return '';
        }
        return trim(rxRenewalText('success_same_link'));
    }
}

if (!function_exists('rxRenewalSyncInvoiceName')) {
    function rxRenewalSyncInvoiceName($idInvoice, $productName): bool
    {
        $idInvoice = trim((string) $idInvoice);
        $productName = trim((string) $productName);
        if ($idInvoice === '' || $productName === '' || !function_exists('select') || !function_exists('update')) {
            return false;
        }
        try {
            $row = select('invoice', 'name_product', 'id_invoice', $idInvoice, 'select');
            if (!is_array($row) || (string) ($row['name_product'] ?? '') === $productName) {
                return false;
            }
            update('invoice', 'name_product', $productName, 'id_invoice', $idInvoice);
            return true;
        } catch (Throwable $e) {
            rx_pf_log('RENEWAL_INVOICE_NAME_SYNC_FAILED', $e->getMessage(), ['id_invoice' => $idInvoice]);
            return false;
        }
    }
}

if (!function_exists('rxRenewalDecorateSuccess')) {
    function rxRenewalDecorateSuccess(string $successText, $extend, string $username, string $panelName): string
    {
        if (rxRenewalIsPending($extend)) {
            return rxRenewalText('pending_verification') . "\n\n▫️نام سرویس : " . $username;
        }
        if (!is_array($extend) || !is_array($extend['rx_renewal'] ?? null)) {
            return $successText;
        }
        $renewal = $extend['rx_renewal'];
        if (!empty($renewal['verified']) && is_array($renewal['before'] ?? null) && is_array($renewal['after'] ?? null)) {
            $decorated = rtrim($successText) . "\n\n" . rxRenewalResultText($renewal['before'], $renewal['after']);
            $sameLink = rxRenewalSameLinkNote($extend, rx_rn_panel_type($panelName));
            return $sameLink !== '' ? $decorated . "\n\n" . $sameLink : $decorated;
        }
        rx_renewal_log('result_decoration', 'unverified_result', [
            'username' => $username,
            'panel_name' => $panelName,
            'plan' => is_array($renewal['plan'] ?? null) ? $renewal['plan'] : [],
            'before' => is_array($renewal['before'] ?? null) ? $renewal['before'] : null,
            'after' => is_array($renewal['after'] ?? null) ? $renewal['after'] : null,
            'operation_id' => $renewal['operation_id'] ?? null,
            'mutation' => 'uncertain',
        ]);
        return rxRenewalText('pending_verification') . "\n\n▫️نام سرویس : " . $username;
    }
}

if (!function_exists('rx_pf_extra_volume_target')) {
    function rx_pf_extra_volume_target(array $before, $volumeGb): array
    {
        $volume = (float) $volumeGb;
        return ['data_limit' => $volume == 0 ? 0 : rx_pf_int($before['data_limit'] ?? 0) + (int) round($volume * RX_PF_GIB)];
    }
}

if (!function_exists('rx_pf_extra_time_target')) {
    function rx_pf_extra_time_target(array $before, $days, int $now): array
    {
        $timeOld = rx_pf_int($before['expire'] ?? 0);
        $timeOld = ($now - $timeOld > 0) ? $now : $timeOld;
        $days = (float) $days;
        return ['expire' => $days == 0 ? 0 : $timeOld + (int) round($days * 86400)];
    }
}

if (!function_exists('rx_pf_adopt_created')) {
    function rx_pf_adopt_created(array $raw): array
    {
        $output = $raw;
        $output['status'] = 'successful';
        if (empty($output['configs']) && !empty($output['links'])) {
            $output['configs'] = is_array($output['links']) ? $output['links'] : explode("\n", (string) $output['links']);
        }
        return $output;
    }
}

if (!function_exists('rx_pf_wallet_credit')) {
    function rx_pf_wallet_credit(string $orderId, string $token, $userId, int $amount, string $category, string $description): string
    {
        if ($amount <= 0 || trim((string) $userId) === '') {
            return 'invalid';
        }
        if (!rx_pf_wallet_ready()) {
            return 'error';
        }
        try {
            $status = rx_pf_tx(function (PDO $pdo) use ($orderId, $token, $userId, $amount, $category, $description) {
                $now = time();
                $record = rx_pf_lock_record($pdo, $orderId);
                if ($record === null) {
                    return 'missing';
                }
                if ((string) $record['state'] === 'completed') {
                    return 'already_completed';
                }
                if (!hash_equals((string) ($record['processing_token'] ?? ''), $token)) {
                    return 'not_owner';
                }
                if (!in_array((string) $record['state'], ['claimed', 'reconciling'], true)) {
                    return 'invalid_state';
                }
                $payment = rx_pf_lock_payment($pdo, $orderId);
                if ($payment === null) {
                    return 'missing';
                }
                if (!rx_pf_cron_payment_allowed($payment, $now)) {
                    return 'cron_window_elapsed';
                }
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'completed', lease_expires_at = NULL, completed_at = ?, updated_at = ? WHERE id_order = ?");
                    $stmt->execute([$now, $now, $orderId]);
                    return 'already_completed';
                }
                $credit = rx_wallet_increment_locked($pdo, (string) $userId, $amount);
                if ($credit->rowCount() < 1) {
                    throw new RuntimeException('wallet_user_not_found');
                }
                $balanceStmt = $pdo->prepare("SELECT Balance FROM user WHERE id = ? LIMIT 1");
                $balanceStmt->execute([(string) $userId]);
                $balanceAfter = $balanceStmt->fetchColumn();
                $ledger = $pdo->prepare(
                    "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, fulfillment_key) VALUES (?, 'credit', ?, ?, ?, ?, ?, ?)"
                );
                $ledger->execute([(string) $userId, $amount, $balanceAfter === false ? null : (int) $balanceAfter, $category, $description, $orderId, $orderId]);
                $paid = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid', direct_payment_done = 1 WHERE id_order = ? AND COALESCE(direct_payment_done, 0) = 0");
                $paid->execute([$orderId]);
                if ($paid->rowCount() < 1) {
                    throw new RuntimeException('wallet_payment_finalize_failed');
                }
                $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'completed', result_state = ?, lease_expires_at = NULL, completed_at = ?, updated_at = ? WHERE id_order = ?");
                $stmt->execute([rx_pf_json(['credited' => $amount, 'balance_after' => $balanceAfter === false ? null : (int) $balanceAfter]), $now, $now, $orderId]);
                return 'credited';
            });
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_WALLET_CREDIT_FAILED', $e->getMessage(), ['id_order' => $orderId, 'id_user' => (string) $userId, 'amount' => $amount]);
            return 'error';
        }
        rx_pf_clear_cache(['Payment_report', 'user']);
        if (function_exists('clearSelectCacheRow')) {
            try {
                clearSelectCacheRow('user', 'id', $userId);
            } catch (Throwable $e) {
            }
        }
        return $status;
    }
}
