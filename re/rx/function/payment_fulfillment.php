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
            'success' => in_array($status, ['completed', 'already_completed'], true),
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
        try {
            $column = $pdo->query("SHOW COLUMNS FROM wallet_ledger LIKE 'fulfillment_key'");
            if ($column !== false && $column->fetch(PDO::FETCH_ASSOC) === false) {
                $pdo->exec("ALTER TABLE wallet_ledger ADD COLUMN fulfillment_key VARCHAR(191) NULL, ADD UNIQUE KEY uniq_wl_fulfillment_key (fulfillment_key)");
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_WALLET_SCHEMA_FAILED', $e->getMessage());
            return false;
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
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    return $deny(rx_pf_result('already_completed', 'direct_payment_done'));
                }
                $record = rx_pf_lock_record($pdo, $orderId);
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
        rx_pf_transition($orderId, $token, ['claimed'], $retryable ? 'released' : 'failed', $reason, null, false);
        return rx_pf_result($retryable ? 'retryable' : 'failed', $reason, $extra);
    }
}

if (!function_exists('rx_pf_fail')) {
    function rx_pf_fail(string $orderId, string $token, string $reason, ?string $refundOutcome = null): array
    {
        rx_pf_transition($orderId, $token, ['claimed', 'applying', 'reconciling'], 'failed', $reason, null, false);
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
                $now = time();
                $payment = rx_pf_lock_payment($pdo, $orderId);
                if ($payment === null) {
                    return ['status' => 'skip'];
                }
                $status = (string) ($payment['payment_Status'] ?? '');
                if (!in_array($status, ['processing', 'reconciling'], true)) {
                    return ['status' => 'skip'];
                }
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    rx_pf_mark_paid_done($pdo, $orderId);
                    return ['status' => 'finalized', 'report' => $payment];
                }
                $record = rx_pf_lock_record($pdo, $orderId);
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
                    rx_pf_set_payment_status($pdo, $orderId, 'waiting', ['processing', 'reconciling']);
                    return ['status' => 'released'];
                }
                if ($state === 'failed') {
                    $reason = (string) ($record['last_error'] ?? '');
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

if (!function_exists('rx_pf_run_claimed')) {
    function rx_pf_run_claimed(string $orderId, string $token, string $image, string $failedTo): array
    {
        $result = null;
        try {
            $result = DirectPayment($orderId, $image, $token);
        } catch (Throwable $e) {
            rx_pf_log('PAYMENT_FULFILLMENT_DIRECT_PAYMENT_THROWABLE', $e->getMessage(), [
                'id_order' => $orderId,
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
        if (!is_array($result)) {
            $result = rx_pf_result('requires_reconciliation', 'direct_payment_invalid_result');
        }
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
        return in_array((string) ($panel['type'] ?? ''), ['marzban', 'pasarguard', 'x-ui_single', 'guard', 'remnawave', 'rebecca'], true);
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

if (!function_exists('rx_pf_snapshot_from_datauser')) {
    function rx_pf_snapshot_from_datauser($data): array
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
        return [
            'ok' => true,
            'exists' => true,
            'username' => (string) $data['username'],
            'data_limit' => rx_pf_int($data['data_limit'] ?? 0),
            'expire' => rx_pf_int($data['expire'] ?? 0),
            'used_traffic' => rx_pf_int($data['used_traffic'] ?? 0),
            'raw' => $data,
        ];
    }
}

if (!function_exists('rx_pf_panel_snapshot')) {
    function rx_pf_panel_snapshot($managePanel, string $namePanel, string $username): array
    {
        if (!is_object($managePanel)) {
            return ['ok' => false, 'error' => 'manage_panel_unavailable'];
        }
        try {
            $data = $managePanel->DataUser($namePanel, $username);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return rx_pf_snapshot_from_datauser($data);
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

if (!function_exists('rx_pf_extend_target')) {
    function rx_pf_extend_target(string $method, array $before, $newLimitGb, $timeDay, int $now): ?array
    {
        if ($method === 'رزرو اشتراک') {
            return null;
        }
        $volume = (float) $newLimitGb;
        $days = (float) $timeDay;
        $dataOld = rx_pf_int($before['data_limit'] ?? 0);
        $timeOld = rx_pf_int($before['expire'] ?? 0);
        $timeOld = ($now - $timeOld > 0) ? $now : $timeOld;
        $volumeBytes = (int) round($volume * RX_PF_GIB);
        $daySeconds = (int) round($days * 86400);
        $dataNew = $volume == 0 ? 0 : $volumeBytes;
        $dataNewAdd = $volume == 0 ? 0 : $dataOld + $volumeBytes;
        $timeNew = $days == 0 ? 0 : $now + $daySeconds;
        $timeNewAdd = $days == 0 ? 0 : $timeOld + $daySeconds;
        if ($method === 'اضافه شدن زمان و حجم به ماه بعد') {
            return ['data_limit' => $dataNewAdd, 'expire' => $timeNewAdd];
        }
        if ($method === 'ریست زمان و اضافه کردن حجم قبلی') {
            return ['data_limit' => $dataNewAdd, 'expire' => $timeNew];
        }
        if ($method === 'ریست شدن حجم و اضافه شدن زمان') {
            return ['data_limit' => $dataNew, 'expire' => $timeNewAdd];
        }
        if ($method === 'اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده') {
            $remaining = $dataOld - rx_pf_int($before['used_traffic'] ?? 0);
            return ['data_limit' => $dataNew + max(0, $remaining), 'expire' => $timeNewAdd];
        }
        return ['data_limit' => $dataNew, 'expire' => $timeNew];
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
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    $stmt = $pdo->prepare("UPDATE payment_fulfillment SET state = 'completed', lease_expires_at = NULL, completed_at = ?, updated_at = ? WHERE id_order = ?");
                    $stmt->execute([$now, $now, $orderId]);
                    return 'already_completed';
                }
                $credit = $pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE id = ?");
                $credit->execute([$amount, (string) $userId]);
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
