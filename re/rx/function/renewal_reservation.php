<?php

if (!defined('RX_RN_LOCK_SECONDS')) {
    define('RX_RN_LOCK_SECONDS', 300);
}
if (!defined('RX_RN_MAX_ATTEMPTS')) {
    define('RX_RN_MAX_ATTEMPTS', 10);
}
if (!defined('RX_RN_BLOCKED_RECHECK')) {
    define('RX_RN_BLOCKED_RECHECK', 21600);
}

if (!function_exists('rx_rn_unresolved_statuses')) {
    function rx_rn_unresolved_statuses(): array
    {
        return ['pending', 'processing', 'retry', 'reconciling'];
    }
}

if (!function_exists('rx_rn_unresolved_sql')) {
    function rx_rn_unresolved_sql(): string
    {
        return "'" . implode("','", rx_rn_unresolved_statuses()) . "'";
    }
}

if (!function_exists('rx_rn_queued_columns')) {
    function rx_rn_queued_columns(): array
    {
        return [
            'service_key' => 'VARCHAR(191) NULL',
            'active_key' => 'VARCHAR(191) NULL',
            'trigger_type' => "VARCHAR(20) NOT NULL DEFAULT 'either'",
            'conditions' => 'VARCHAR(64) NULL',
            'trigger_at' => 'INT UNSIGNED NULL',
            'policy' => "VARCHAR(40) NOT NULL DEFAULT 'reset_all'",
            'reset_usage' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'payment_ref' => 'VARCHAR(191) NULL',
            'fulfillment' => 'MEDIUMTEXT NULL',
            'processing_token' => 'CHAR(32) NULL',
            'lease_expires_at' => 'INT UNSIGNED NULL',
            'attempt_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'next_attempt_at' => 'INT UNSIGNED NULL',
            'last_error' => 'TEXT NULL',
            'stage' => 'VARCHAR(30) NULL',
            'before_state' => 'MEDIUMTEXT NULL',
            'target_state' => 'MEDIUMTEXT NULL',
            'result_state' => 'MEDIUMTEXT NULL',
            'notified_state' => 'VARCHAR(80) NULL',
            'legacy' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'updated_at' => 'INT UNSIGNED NULL',
        ];
    }
}

if (!function_exists('rx_rn_queued_table_ddl')) {
    function rx_rn_queued_table_ddl(): string
    {
        $extra = [];
        foreach (rx_rn_queued_columns() as $name => $definition) {
            $extra[] = $name . ' ' . $definition;
        }
        return "CREATE TABLE IF NOT EXISTS queued_renewal (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(500) NOT NULL,
        username varchar(1000) NOT NULL,
        name_panel varchar(500) NOT NULL,
        code_panel varchar(500) NOT NULL,
        code_product varchar(500) NOT NULL,
        new_limit_gb varchar(200) NOT NULL,
        time_day varchar(200) NOT NULL,
        price varchar(200) NOT NULL,
        id_invoice varchar(500) NULL,
        status varchar(200) NOT NULL DEFAULT 'pending',
        bottype varchar(200) NULL,
        created_at varchar(200) NOT NULL,
        applied_at varchar(200) NULL,
        output TEXT NULL,
        " . implode(",\n        ", $extra) . ",
        INDEX idx_queued_renewal_username_status (username(191), status(50)),
        UNIQUE KEY uniq_qr_active_key (active_key),
        KEY idx_qr_status_next (status(50), next_attempt_at),
        KEY idx_qr_payment_ref (payment_ref))
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci";
    }
}

if (!function_exists('rx_rn_ensure_schema')) {
    function rx_rn_ensure_schema(): bool
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
            $pdo->exec("CREATE TABLE IF NOT EXISTS renewal_lock (
                service_key VARCHAR(191) NOT NULL PRIMARY KEY,
                owner CHAR(32) NOT NULL,
                lease_expires_at INT UNSIGNED NOT NULL,
                updated_at INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS renewal_operation (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                op_id CHAR(32) NOT NULL,
                source VARCHAR(40) NOT NULL,
                ref VARCHAR(191) NULL,
                service_key VARCHAR(191) NOT NULL,
                username VARCHAR(191) NOT NULL,
                name_panel VARCHAR(191) NOT NULL,
                id_invoice VARCHAR(191) NULL,
                method VARCHAR(100) NULL,
                policy VARCHAR(40) NULL,
                state VARCHAR(20) NOT NULL,
                stage VARCHAR(30) NOT NULL,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                next_attempt_at INT UNSIGNED NULL,
                before_state MEDIUMTEXT NULL,
                target_state MEDIUMTEXT NULL,
                result_state MEDIUMTEXT NULL,
                last_error TEXT NULL,
                created_at INT UNSIGNED NOT NULL,
                updated_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uniq_ro_op_id (op_id),
                KEY idx_ro_state (state, next_attempt_at),
                KEY idx_ro_service (service_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $opColumns = [];
            foreach ($pdo->query("SHOW COLUMNS FROM renewal_operation")->fetchAll(PDO::FETCH_ASSOC) as $opCol) {
                $opColumns[(string) $opCol['Field']] = true;
            }
            foreach ([
                'finalize_context' => 'MEDIUMTEXT NULL',
                'finalize_state' => 'VARCHAR(20) NULL',
                'finalize_attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0',
                'finalize_next' => 'INT UNSIGNED NULL',
                'finalize_error' => 'TEXT NULL',
                'finalized_at' => 'INT UNSIGNED NULL',
                'notify_state' => 'VARCHAR(20) NULL',
                'notify_attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0',
                'notify_next' => 'INT UNSIGNED NULL',
                'notify_claimed_at' => 'INT UNSIGNED NULL',
                'notify_token' => 'CHAR(16) NULL',
                'notify_error' => 'VARCHAR(255) NULL',
            ] as $opName => $opDefinition) {
                if (!isset($opColumns[$opName])) {
                    $pdo->exec("ALTER TABLE renewal_operation ADD COLUMN {$opName} {$opDefinition}");
                }
            }
            $panelTable = $pdo->query("SHOW TABLES LIKE 'marzban_panel'");
            if ($panelTable !== false && $panelTable->fetch(PDO::FETCH_NUM) !== false) {
                $settingsColumn = $pdo->query("SHOW COLUMNS FROM marzban_panel LIKE 'reservation_settings'");
                if ($settingsColumn !== false && $settingsColumn->fetch(PDO::FETCH_ASSOC) === false) {
                    $pdo->exec("ALTER TABLE marzban_panel ADD COLUMN reservation_settings TEXT NULL");
                }
                $legacySettings = $pdo->query("SELECT code_panel, reservation_settings FROM marzban_panel WHERE reservation_settings LIKE '%default_policy%' OR reservation_settings LIKE '%default_trigger%'");
                if ($legacySettings !== false) {
                    $migrateSettings = $pdo->prepare("UPDATE marzban_panel SET reservation_settings = ? WHERE code_panel = ?");
                    foreach ($legacySettings->fetchAll(PDO::FETCH_ASSOC) as $legacyRow) {
                        $migrateSettings->execute([rxReservationSettingsJson(rxReservationSettingsFromRaw(json_decode((string) $legacyRow['reservation_settings'], true))), (string) $legacyRow['code_panel']]);
                    }
                }
            }
            $exists = $pdo->query("SHOW TABLES LIKE 'queued_renewal'");
            if ($exists === false || $exists->fetch(PDO::FETCH_NUM) === false) {
                $pdo->exec(rx_rn_queued_table_ddl());
                $ready = true;
                return true;
            }
            $columns = [];
            foreach ($pdo->query("SHOW COLUMNS FROM queued_renewal")->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[(string) $col['Field']] = true;
            }
            $legacyMigration = !isset($columns['policy']);
            foreach (rx_rn_queued_columns() as $name => $definition) {
                if (!isset($columns[$name])) {
                    $pdo->exec("ALTER TABLE queued_renewal ADD COLUMN {$name} {$definition}");
                }
            }
            if ($legacyMigration) {
                $pdo->exec("UPDATE queued_renewal SET legacy = 1, trigger_type = 'either', policy = 'reset_all', reset_usage = 0, attempt_count = 0, "
                    . "service_key = LEFT(CASE WHEN id_invoice IS NOT NULL AND id_invoice <> '' THEN CONCAT('inv:', id_invoice) ELSE CONCAT('pu:', code_panel, '|', username) END, 191), "
                    . "updated_at = UNIX_TIMESTAMP()");
                $pdo->exec("UPDATE queued_renewal q JOIN (SELECT MIN(id) AS id FROM queued_renewal WHERE status = 'pending' GROUP BY service_key) m ON m.id = q.id SET q.active_key = q.service_key");
                $pdo->exec("UPDATE queued_renewal SET last_error = LEFT(COALESCE(output, 'legacy_failed'), 2000), notified_state = 'failed:legacy' WHERE status = 'failed'");
            }
            $indexes = [];
            foreach ($pdo->query("SHOW INDEX FROM queued_renewal")->fetchAll(PDO::FETCH_ASSOC) as $idx) {
                $indexes[(string) $idx['Key_name']] = true;
            }
            if (!isset($indexes['uniq_qr_active_key'])) {
                $pdo->exec("ALTER TABLE queued_renewal ADD UNIQUE KEY uniq_qr_active_key (active_key)");
            }
            if (!isset($indexes['idx_qr_status_next'])) {
                $pdo->exec("ALTER TABLE queued_renewal ADD KEY idx_qr_status_next (status(50), next_attempt_at)");
            }
            if (!isset($indexes['idx_qr_payment_ref'])) {
                $pdo->exec("ALTER TABLE queued_renewal ADD KEY idx_qr_payment_ref (payment_ref)");
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            rx_pf_log('RENEWAL_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('rx_rn_service_key')) {
    function rx_rn_service_key($idInvoice, $codePanel, $username): string
    {
        $idInvoice = trim((string) $idInvoice);
        $key = $idInvoice !== '' ? 'inv:' . $idInvoice : 'pu:' . (string) $codePanel . '|' . (string) $username;
        return substr($key, 0, 191);
    }
}

if (!function_exists('rx_rn_lock_acquire')) {
    function rx_rn_lock_acquire(string $serviceKey, string $owner, int $ttl = RX_RN_LOCK_SECONDS): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !rx_rn_ensure_schema()) {
            return false;
        }
        try {
            $now = time();
            $stmt = $pdo->prepare(
                "INSERT INTO renewal_lock (service_key, owner, lease_expires_at, updated_at) VALUES (?, ?, ?, ?) "
                . "ON DUPLICATE KEY UPDATE owner = IF(lease_expires_at < VALUES(updated_at) OR owner = VALUES(owner), VALUES(owner), owner), "
                . "lease_expires_at = IF(owner = VALUES(owner), VALUES(lease_expires_at), lease_expires_at), "
                . "updated_at = IF(owner = VALUES(owner), VALUES(updated_at), updated_at)"
            );
            $stmt->execute([$serviceKey, $owner, $now + $ttl, $now]);
            $check = $pdo->prepare("SELECT owner FROM renewal_lock WHERE service_key = ? LIMIT 1");
            $check->execute([$serviceKey]);
            return hash_equals((string) $check->fetchColumn(), $owner);
        } catch (Throwable $e) {
            rx_pf_log('RENEWAL_LOCK_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
            return false;
        }
    }
}

if (!function_exists('rx_rn_lock_release')) {
    function rx_rn_lock_release(string $serviceKey, string $owner): void
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM renewal_lock WHERE service_key = ? AND owner = ?");
            $stmt->execute([$serviceKey, $owner]);
        } catch (Throwable $e) {
            rx_pf_log('RENEWAL_LOCK_RELEASE_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
        }
    }
}

if (!function_exists('rx_rn_trim_error')) {
    function rx_rn_trim_error($error): string
    {
        $error = is_scalar($error) ? (string) $error : (string) json_encode($error, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return function_exists('mb_substr') ? mb_substr($error, 0, 2000, 'UTF-8') : substr($error, 0, 2000);
    }
}

if (!function_exists('rx_rn_op_start')) {
    function rx_rn_op_start(array $op): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !rx_rn_ensure_schema()) {
            return false;
        }
        try {
            $now = time();
            $stmt = $pdo->prepare(
                "INSERT INTO renewal_operation (op_id, source, ref, service_key, username, name_panel, id_invoice, method, policy, state, stage, attempt_count, before_state, target_state, finalize_context, finalize_state, created_at, updated_at) "
                . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'applying', 'planned', 1, ?, ?, ?, ?, ?, ?) "
                . "ON DUPLICATE KEY UPDATE state = 'applying', stage = 'planned', attempt_count = attempt_count + 1, target_state = VALUES(target_state), updated_at = VALUES(updated_at)"
            );
            $stmt->execute([
                (string) $op['op_id'],
                (string) ($op['source'] ?? 'wallet'),
                isset($op['ref']) ? substr((string) $op['ref'], 0, 191) : null,
                (string) $op['service_key'],
                substr((string) $op['username'], 0, 191),
                substr((string) $op['name_panel'], 0, 191),
                isset($op['id_invoice']) ? (string) $op['id_invoice'] : null,
                isset($op['method']) ? substr((string) $op['method'], 0, 100) : null,
                isset($op['policy']) ? (string) $op['policy'] : null,
                rx_pf_json($op['before'] ?? null),
                rx_pf_json($op['target'] ?? null),
                is_array($op['finalize'] ?? null) ? rx_pf_json($op['finalize']) : null,
                is_array($op['finalize'] ?? null) ? 'pending' : null,
                $now,
                $now,
            ]);
            return true;
        } catch (Throwable $e) {
            rx_pf_log('RENEWAL_OPERATION_START_FAILED', $e->getMessage(), ['op_id' => $op['op_id'] ?? null]);
            return false;
        }
    }
}

if (!function_exists('rx_rn_op_update')) {
    function rx_rn_op_update(string $opId, array $fields): void
    {
        global $pdo;
        if (!($pdo instanceof PDO) || $fields === []) {
            return;
        }
        $allowed = ['state', 'stage', 'result_state', 'last_error', 'next_attempt_at', 'attempt_count'];
        $sets = [];
        $params = [];
        foreach ($fields as $name => $value) {
            if (!in_array($name, $allowed, true)) {
                continue;
            }
            if ($name === 'result_state') {
                $value = rx_pf_json($value);
            } elseif ($name === 'last_error' && $value !== null) {
                $value = rx_rn_trim_error($value);
            }
            $sets[] = $name . ' = ?';
            $params[] = $value;
        }
        if ($sets === []) {
            return;
        }
        $sets[] = 'updated_at = ?';
        $params[] = time();
        $params[] = $opId;
        try {
            $stmt = $pdo->prepare("UPDATE renewal_operation SET " . implode(', ', $sets) . " WHERE op_id = ?");
            $stmt->execute($params);
        } catch (Throwable $e) {
            rx_pf_log('RENEWAL_OPERATION_UPDATE_FAILED', $e->getMessage(), ['op_id' => $opId]);
        }
    }
}

if (!function_exists('rxReservationTriggers')) {
    function rxReservationTriggers(): array
    {
        return ['either', 'traffic', 'expire', 'date', 'immediate', 'multi'];
    }
}

if (!function_exists('rxReservationConditions')) {
    function rxReservationConditions(): array
    {
        return ['traffic', 'expire', 'date'];
    }
}

if (!function_exists('rxReservationConditionsFromTrigger')) {
    function rxReservationConditionsFromTrigger(string $trigger): array
    {
        if ($trigger === 'traffic' || $trigger === 'expire' || $trigger === 'date') {
            return [$trigger];
        }
        if ($trigger === 'immediate') {
            return [];
        }
        return ['traffic', 'expire'];
    }
}

if (!function_exists('rxReservationNormalizeConditions')) {
    function rxReservationNormalizeConditions($conditions): array
    {
        if (is_string($conditions)) {
            $conditions = explode(',', $conditions);
        }
        $conditions = is_array($conditions) ? array_map('strval', $conditions) : [];
        return array_values(array_intersect(rxReservationConditions(), $conditions));
    }
}

if (!function_exists('rxReservationTriggerForConditions')) {
    function rxReservationTriggerForConditions(array $conditions): string
    {
        $conditions = rxReservationNormalizeConditions($conditions);
        if ($conditions === ['traffic', 'expire']) {
            return 'either';
        }
        return count($conditions) === 1 ? $conditions[0] : 'multi';
    }
}

if (!function_exists('rxReservationRowConditions')) {
    function rxReservationRowConditions(array $row): array
    {
        $stored = rxReservationNormalizeConditions((string) ($row['conditions'] ?? ''));
        if ($stored !== []) {
            return $stored;
        }
        return rxReservationConditionsFromTrigger((string) ($row['trigger_type'] ?? 'either'));
    }
}

if (!function_exists('rxReservationActivationText')) {
    function rxReservationActivationText(array $conditions, $triggerAt = null): string
    {
        $conditions = rxReservationNormalizeConditions($conditions);
        $date = is_numeric($triggerAt) && (int) $triggerAt > 0 ? rxRenewalFormatExpire((int) $triggerAt) : rxRenewalText('reservation_date_unset');
        if (count($conditions) === 1) {
            $phrase = $conditions[0] === 'date'
                ? rxRenewalText('activation_phrase_date', ['date' => $date])
                : rxRenewalText('activation_single_' . $conditions[0]);
            return rxRenewalText('activation_single', ['phrase' => $phrase]);
        }
        $parts = [];
        if (in_array('traffic', $conditions, true) && in_array('expire', $conditions, true)) {
            $parts[] = rxRenewalText('activation_phrase_traffic_expire');
        } else {
            foreach (['traffic', 'expire'] as $key) {
                if (in_array($key, $conditions, true)) {
                    $parts[] = rxRenewalText('activation_phrase_' . $key);
                }
            }
        }
        if (in_array('date', $conditions, true)) {
            $parts[] = rxRenewalText('activation_phrase_date', ['date' => $date]);
        }
        return rxRenewalText('activation_multi', ['phrase' => implode(rxRenewalText('activation_joiner'), $parts)]);
    }
}

if (!function_exists('rxReservationConfirmationText')) {
    function rxReservationConfirmationText(array $conditions, $triggerAt = null): string
    {
        return rxRenewalText('reservation_confirmed') . "\n" . rxReservationActivationText($conditions, $triggerAt);
    }
}

if (!function_exists('rxReservationTriggerLabel')) {
    function rxReservationTriggerLabel(string $trigger, $triggerAt = null, $conditions = null): string
    {
        $list = rxReservationNormalizeConditions($conditions ?? '');
        if ($list === []) {
            $list = rxReservationConditionsFromTrigger($trigger);
        }
        if ($list === []) {
            return rxRenewalText('trigger_immediate');
        }
        return rxReservationActivationText($list, $triggerAt);
    }
}

if (!function_exists('rxReservationStatusLabel')) {
    function rxReservationStatusLabel(string $status): string
    {
        $known = ['pending', 'processing', 'retry', 'reconciling', 'applied', 'failed', 'cancelled'];
        return rxRenewalText('status_' . (in_array($status, $known, true) ? $status : 'pending'));
    }
}

if (!function_exists('rxReservationTriggerAvailability')) {
    function rxReservationTriggerAvailability(?array $snapshot): array
    {
        $out = [];
        foreach (array_merge(rxReservationTriggers(), rxReservationConditions()) as $trigger) {
            $out[$trigger] = ['available' => true, 'reason' => ''];
        }
        if (!is_array($snapshot) || empty($snapshot['ok']) || empty($snapshot['exists'])) {
            return $out;
        }
        $unlimitedTraffic = rx_pf_int($snapshot['data_limit'] ?? 0) <= 0;
        $unlimitedTime = empty($snapshot['pending']) && rx_pf_int($snapshot['expire'] ?? 0) <= 0;
        if ($unlimitedTraffic) {
            $out['traffic'] = ['available' => false, 'reason' => rxRenewalText('trigger_unavailable_traffic')];
        }
        if ($unlimitedTime) {
            $out['expire'] = ['available' => false, 'reason' => rxRenewalText('trigger_unavailable_expire')];
        }
        if ($unlimitedTraffic && $unlimitedTime) {
            $out['either'] = ['available' => false, 'reason' => rxRenewalText('trigger_unavailable_either')];
        }
        return $out;
    }
}

if (!function_exists('rxReservationTriggered')) {
    function rxReservationTriggered(array $row, array $snapshot, int $now): bool
    {
        if (empty($snapshot['ok']) || empty($snapshot['exists'])) {
            return false;
        }
        $conditions = rxReservationRowConditions($row);
        if (in_array('date', $conditions, true)) {
            $at = (int) ($row['trigger_at'] ?? 0);
            if ($at > 0 && $now >= $at) {
                return true;
            }
        }
        $status = strtolower((string) ($snapshot['status'] ?? ''));
        $limit = rx_pf_int($snapshot['data_limit'] ?? 0);
        $used = rx_pf_int($snapshot['used_traffic'] ?? 0);
        $expire = rx_pf_int($snapshot['expire'] ?? 0);
        $pending = !empty($snapshot['pending']);
        if (in_array('traffic', $conditions, true) && $limit > 0 && ($used >= $limit || $status === 'limited')) {
            return true;
        }
        if (in_array('expire', $conditions, true) && !$pending && $expire > 0 && ($expire <= $now || $status === 'expired')) {
            return true;
        }
        return false;
    }
}

if (!function_exists('rxReservationParseDate')) {
    function rxReservationParseDate($input, int $now, int $maxAhead = 31622400): ?int
    {
        $input = trim((string) $input);
        if (function_exists('convertPersianNumbersToEnglish')) {
            $input = (string) convertPersianNumbersToEnglish($input);
        }
        $input = strtr($input, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '：' => ':']);
        $input = preg_replace('/\s+/', ' ', $input);
        if ($input === '') {
            return null;
        }
        if (ctype_digit($input) && (int) $input <= 366) {
            $days = (int) $input;
            if ($days < 1) {
                return null;
            }
            return $now + $days * 86400;
        }
        if (ctype_digit($input) && (int) $input > 1000000000) {
            $ts = (int) $input;
        } elseif (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:[ T](\d{1,2}):(\d{2}))?$/', $input, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            $h = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
            $mi = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 0;
            if ($h > 23 || $mi > 59) {
                return null;
            }
            if ($y < 1700) {
                if (!function_exists('jalali_to_gregorian')) {
                    return null;
                }
                $g = jalali_to_gregorian($y, $mo, $d);
                if (!is_array($g) || count($g) < 3) {
                    return null;
                }
                list($y, $mo, $d) = [(int) $g[0], (int) $g[1], (int) $g[2]];
            }
            if (!checkdate($mo, $d, $y)) {
                return null;
            }
            $ts = mktime($h, $mi, 0, $mo, $d, $y);
        } else {
            return null;
        }
        if ($ts === false || $ts <= $now || $ts > $now + $maxAhead) {
            return null;
        }
        return (int) $ts;
    }
}

if (!function_exists('rxReservationNormalizeSelection')) {
    function rxReservationNormalizeSelection($selection, ?array $snapshot, int $now, bool $allowElapsedDate = false): array
    {
        $selection = is_array($selection) ? $selection : [];
        $policy = (string) ($selection['policy'] ?? 'reset_all');
        if (!isset(rxRenewalPolicies()[$policy])) {
            return ['ok' => false, 'reason' => 'policy_invalid'];
        }
        $conditions = rxReservationNormalizeConditions($selection['conditions'] ?? []);
        if ($conditions === [] && isset($selection['trigger'])) {
            if ((string) $selection['trigger'] === 'immediate') {
                return ['ok' => true, 'trigger' => 'immediate', 'conditions' => [], 'trigger_at' => null, 'policy' => $policy, 'reset_usage' => !empty($selection['reset_usage']) && rxRenewalPolicyAllowsUsageReset($policy)];
            }
            $conditions = rxReservationConditionsFromTrigger((string) $selection['trigger']);
        }
        if ($conditions === []) {
            return ['ok' => false, 'reason' => 'trigger_unavailable', 'detail' => rxRenewalText('admin_conditions_empty')];
        }
        $triggerAt = null;
        if (in_array('date', $conditions, true)) {
            $raw = $selection['trigger_at'] ?? null;
            if ($raw === null || $raw === '' || (int) $raw <= 0) {
                return ['ok' => false, 'reason' => 'trigger_date_required'];
            }
            $triggerAt = is_numeric($raw) ? (int) $raw : rxReservationParseDate($raw, $now);
            if ($triggerAt === null) {
                return ['ok' => false, 'reason' => 'trigger_date_invalid'];
            }
            if ($triggerAt <= $now && !$allowElapsedDate) {
                return ['ok' => false, 'reason' => 'trigger_date_expired', 'trigger_at' => $triggerAt];
            }
        } else {
            $availability = rxReservationTriggerAvailability($snapshot);
            $possible = false;
            $reasons = [];
            foreach ($conditions as $condition) {
                if (!empty($availability[$condition]['available'])) {
                    $possible = true;
                } else {
                    $reasons[] = (string) $availability[$condition]['reason'];
                }
            }
            if (!$possible) {
                return ['ok' => false, 'reason' => 'trigger_unavailable', 'detail' => implode(' ', $reasons)];
            }
        }
        return [
            'ok' => true,
            'trigger' => rxReservationTriggerForConditions($conditions),
            'conditions' => $conditions,
            'trigger_at' => $triggerAt,
            'policy' => $policy,
            'reset_usage' => !empty($selection['reset_usage']) && rxRenewalPolicyAllowsUsageReset($policy),
        ];
    }
}

if (!function_exists('rxReservationOutstanding')) {
    function rxReservationOutstanding(string $serviceKey, string $username = '', string $namePanel = ''): ?array
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !rx_rn_ensure_schema()) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE (active_key = ? OR service_key = ?) AND status IN (" . rx_rn_unresolved_sql() . ") ORDER BY id ASC LIMIT 1");
            $stmt->execute([$serviceKey, $serviceKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false && $username !== '' && $namePanel !== '') {
                $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE username = ? AND name_panel = ? AND status IN (" . rx_rn_unresolved_sql() . ") ORDER BY id ASC LIMIT 1");
                $stmt->execute([$username, $namePanel]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            rx_pf_log('RESERVATION_LOOKUP_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
            return null;
        }
    }
}

if (!function_exists('rxReservationByPaymentRef')) {
    function rxReservationByPaymentRef(string $paymentRef): ?array
    {
        global $pdo;
        if ($paymentRef === '' || !($pdo instanceof PDO) || !rx_rn_ensure_schema()) {
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
}

if (!function_exists('rxReservationSummaryForService')) {
    function rxReservationSummaryForService(string $username, string $namePanel = '', string $idInvoice = ''): ?array
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            if ($idInvoice !== '' && rx_rn_ensure_schema()) {
                $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE (service_key = ? OR (username = ? AND name_panel = ?)) AND status IN (" . rx_rn_unresolved_sql() . ") ORDER BY id ASC LIMIT 1");
                $stmt->execute(['inv:' . $idInvoice, $username, $namePanel]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE username = ? AND status IN (" . rx_rn_unresolved_sql() . ") ORDER BY id ASC LIMIT 1");
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
        $conditions = rxReservationRowConditions($row);
        return [
            'id' => (int) $row['id'],
            'status' => $status,
            'status_label' => rxReservationStatusLabel($status),
            'trigger' => $trigger,
            'conditions' => $conditions,
            'trigger_at' => isset($row['trigger_at']) && $row['trigger_at'] !== null ? (int) $row['trigger_at'] : null,
            'trigger_label' => rxReservationTriggerLabel($trigger, $row['trigger_at'] ?? null, $conditions),
            'policy' => $policy,
            'policy_label' => isset(rxRenewalPolicies()[$policy]) ? rxRenewalPolicyLabel($policy, $resetUsage) : $policy,
            'reset_usage' => $resetUsage,
            'volume_gb' => (float) ($row['new_limit_gb'] ?? 0),
            'time_days' => (float) ($row['time_day'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'text' => rxRenewalText('reservation_notice', [
                'status' => rxReservationStatusLabel($status),
                'trigger' => rxReservationTriggerLabel($trigger, $row['trigger_at'] ?? null, $conditions),
                'policy' => isset(rxRenewalPolicies()[$policy]) ? rxRenewalPolicyLabel($policy, $resetUsage) : $policy,
            ]),
        ];
    }
}

if (!function_exists('rxReservationRegister')) {
    function rxReservationRegister(array $a): array
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !rx_rn_ensure_schema()) {
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
            $outstanding = rxReservationOutstanding($serviceKey, (string) ($a['username'] ?? ''), (string) ($a['name_panel'] ?? ''));
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
                implode(',', rxReservationNormalizeConditions($a['conditions'] ?? rxReservationConditionsFromTrigger((string) $a['trigger']))),
                $a['trigger_at'] ?? null,
                (string) $a['policy'],
                !empty($a['reset_usage']) ? 1 : 0,
                $paymentRef !== '' ? substr($paymentRef, 0, 191) : null,
                rx_pf_json($a['fulfillment'] ?? null),
                $now,
            ]);
            $id = (int) $pdo->lastInsertId();
            rx_pf_log('RESERVATION_REGISTERED', 'Reservation registered', [
                'reservation_id' => $id,
                'service_key' => $serviceKey,
                'username' => (string) $a['username'],
                'panel' => (string) $a['name_panel'],
                'trigger' => (string) $a['trigger'],
                'conditions' => rxReservationNormalizeConditions($a['conditions'] ?? []),
                'trigger_at' => $a['trigger_at'] ?? null,
                'policy' => (string) $a['policy'],
                'payment_ref' => $paymentRef,
            ]);
            return ['ok' => true, 'existing' => false, 'id' => $id];
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return ['ok' => false, 'code' => 'queued_renewal_exists'];
            }
            rx_pf_log('RESERVATION_REGISTER_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
            return ['ok' => false, 'code' => 'queued_renewal_insert_failed', 'msg' => $e->getMessage()];
        } catch (Throwable $e) {
            rx_pf_log('RESERVATION_REGISTER_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
            return ['ok' => false, 'code' => 'queued_renewal_insert_failed', 'msg' => $e->getMessage()];
        }
    }
}

if (!function_exists('rxReservationPreview')) {
    function rxReservationPreview($panel, string $username, ?array $snapshot, $volumeGb, $days, array $opts = []): array
    {
        $now = time();
        $selection = rxReservationPanelSelection($panel);
        $base = [
            'mode' => 'reservation',
            'selection' => $selection,
            'plan' => null,
        ];
        $ctx = rxRenewalContext($panel, $username);
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
            rx_renewal_log($stage, 'admin_blocked', $logCtx + ['admin_blocked' => !empty($ctx['admin_blocked'])]);
            return array_merge($base, ['ok' => false, 'blocked' => true, 'reason' => 'admin_blocked', 'text' => rxRenewalReasonText('admin_blocked')]);
        }
        $serviceKey = rx_rn_service_key((string) ($opts['id_invoice'] ?? rxRenewalInvoiceId($username, (string) ($panel['name_panel'] ?? ''))), (string) ($panel['code_panel'] ?? ''), $username);
        if (rxReservationOutstanding($serviceKey, $username, (string) ($panel['name_panel'] ?? '')) !== null) {
            rx_renewal_log($stage, 'queued_renewal_exists', $logCtx + ['detail' => 'service_key=' . $serviceKey]);
            return array_merge($base, ['ok' => false, 'blocked' => true, 'reason' => 'queued_renewal_exists', 'text' => rxRenewalReasonText('queued_renewal_exists')]);
        }
        $check = rxReservationNormalizeSelection($selection, $snapshot, $now);
        if (empty($check['ok'])) {
            rx_renewal_log($stage, (string) $check['reason'], $logCtx + ['detail' => $check['detail'] ?? null]);
            return array_merge($base, ['ok' => false, 'blocked' => true, 'reason' => (string) $check['reason'], 'text' => rxRenewalReasonText((string) $check['reason'], $check)]);
        }
        $policy = (string) $check['policy'];
        $plan = $snapshot !== null ? rxRenewalPlanForSnapshot($snapshot, $panel, $username, $policy, false, $volumeGb, $days, 'رزرو اشتراک', $now, ['quiet' => true]) : null;
        if (is_array($plan) && in_array((string) ($plan['reason'] ?? ''), ['pending_unsupported', 'invalid_purchase', 'panel_policy_unsupported'], true)) {
            rx_renewal_log($stage, (string) $plan['reason'], ['policy' => $policy, 'plan' => $plan, 'admin_blocked' => !empty($ctx['admin_blocked'])] + $logCtx);
            return array_merge($base, ['ok' => false, 'blocked' => true, 'plan' => null, 'reason' => (string) $plan['reason'], 'text' => rxRenewalReasonText((string) $plan['reason'], $plan)]);
        }
        $lines = [
            rxRenewalText('reservation_title'),
            rxRenewalText('reservation_trigger', ['label' => rxReservationActivationText($check['conditions'], $check['trigger_at'])]),
            rxRenewalText('reservation_policy', ['label' => rxRenewalPolicyLabel($policy)]),
            rxRenewalText('reservation_note'),
        ];
        if (is_array($plan) && !empty($plan['ok'])) {
            $lines[] = '';
            $lines[] = rxRenewalText('reservation_estimate');
            $lines = array_merge($lines, rxRenewalPlanLines($plan));
        } else {
            $lines[] = rxRenewalText('reservation_estimate_unavailable');
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
}

if (!function_exists('rxRenewalInvoiceId')) {
    function rxRenewalInvoiceId(string $username, string $namePanel): string
    {
        global $pdo;
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
}

if (!function_exists('rxReservationBackoff')) {
    function rxReservationBackoff(int $attempt): int
    {
        return (int) min(3600, 60 * pow(2, max(0, $attempt - 1)));
    }
}

if (!function_exists('rxReservationClaim')) {
    function rxReservationClaim(int $id, string $fromStatus, string $token, int $now): bool
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                "UPDATE queued_renewal SET status = 'processing', processing_token = ?, lease_expires_at = ?, attempt_count = attempt_count + 1, updated_at = ? "
                . "WHERE id = ? AND status = ? AND (next_attempt_at IS NULL OR next_attempt_at <= ?)"
            );
            $stmt->execute([$token, $now + RX_RN_LOCK_SECONDS, $now, $id, $fromStatus, $now]);
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            rx_pf_log('RESERVATION_CLAIM_FAILED', $e->getMessage(), ['reservation_id' => $id]);
            return false;
        }
    }
}

if (!function_exists('rxReservationTransition')) {
    function rxReservationTransition(int $id, string $token, string $status, array $fields = []): bool
    {
        global $pdo;
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
                $value = rx_pf_json($value);
            } elseif (in_array($name, ['last_error', 'output'], true) && $value !== null) {
                $value = rx_rn_trim_error($value);
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
            rx_pf_log('RESERVATION_TRANSITION_FAILED', $e->getMessage(), ['reservation_id' => $id, 'to' => $status]);
            return false;
        }
    }
}

if (!function_exists('rxReservationRecoverStale')) {
    function rxReservationRecoverStale(int $now): int
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                "UPDATE queued_renewal SET status = CASE WHEN stage IN ('applying', 'reset_sent', 'modify_sent') THEN 'reconciling' ELSE 'retry' END, "
                . "processing_token = NULL, lease_expires_at = NULL, next_attempt_at = ?, last_error = CONCAT('lease_expired_at_stage:', COALESCE(stage, 'claimed')), updated_at = ? "
                . "WHERE status = 'processing' AND lease_expires_at IS NOT NULL AND lease_expires_at < ?"
            );
            $stmt->execute([$now, $now, $now]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            rx_pf_log('RESERVATION_RECOVER_FAILED', $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('rxReservationDueRows')) {
    function rxReservationDueRows(int $now, int $limit = 30, int $shardWorker = 0, int $shardCount = 1): array
    {
        global $pdo;
        $shard = $shardCount > 1 ? " AND MOD(id, " . (int) $shardCount . ") = " . (int) $shardWorker . " " : "";
        try {
            $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE status IN ('pending', 'retry', 'reconciling') AND (next_attempt_at IS NULL OR next_attempt_at <= ?) $shard ORDER BY COALESCE(next_attempt_at, 0) ASC, id ASC LIMIT " . (int) $limit);
            $stmt->execute([$now]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            rx_pf_log('RESERVATION_FETCH_FAILED', $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('rxReservationDefer')) {
    function rxReservationDefer(int $id, string $fromStatus, int $nextAt, ?string $error = null): void
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("UPDATE queued_renewal SET next_attempt_at = ?, last_error = COALESCE(?, last_error), updated_at = ? WHERE id = ? AND status = ?");
            $stmt->execute([$nextAt, $error !== null ? rx_rn_trim_error($error) : null, time(), $id, $fromStatus]);
        } catch (Throwable $e) {
            rx_pf_log('RESERVATION_DEFER_FAILED', $e->getMessage(), ['reservation_id' => $id]);
        }
    }
}

if (!function_exists('rxReservationFail')) {
    function rxReservationFail(array $row, string $token, string $reason, bool $permanent, int $now, array $extra = []): array
    {
        $attempts = (int) ($row['attempt_count'] ?? 0) + 1;
        if (!$permanent && $attempts >= RX_RN_MAX_ATTEMPTS) {
            $permanent = true;
            $reason = 'max_attempts_exceeded: ' . $reason;
        }
        $status = $permanent ? 'failed' : (string) ($extra['status'] ?? 'retry');
        $fields = array_merge([
            'last_error' => $reason,
            'next_attempt_at' => $permanent ? null : $now + (int) ($extra['delay'] ?? rxReservationBackoff($attempts)),
        ], array_intersect_key($extra, array_flip(['before_state', 'target_state', 'stage'])));
        rxReservationTransition((int) $row['id'], $token, $status, $fields);
        rx_renewal_log('reservation_' . ($permanent ? 'failed' : 'retry'), $reason, [
            'panel_name' => (string) $row['name_panel'],
            'username' => (string) $row['username'],
            'invoice_id' => (string) ($row['id_invoice'] ?? ''),
            'method' => 'reservation',
            'policy' => (string) ($row['policy'] ?? ''),
            'volume_gb' => $row['new_limit_gb'] ?? null,
            'days' => $row['time_day'] ?? null,
            'before' => rx_pf_decode($row['before_state'] ?? null),
            'ref' => 'reservation:' . (int) $row['id'],
            'operation_id' => $token,
            'mutation' => ($extra['status'] ?? '') === 'reconciling' ? 'uncertain' : 'not_determined',
            'detail' => 'attempt=' . $attempts . ' status=' . $status . ' service_key=' . (string) ($row['service_key'] ?? ''),
        ]);
        return ['outcome' => $status, 'reason' => $reason, 'row' => $row];
    }
}

if (!function_exists('rxReservationProcessRow')) {
    function rxReservationProcessRow($managePanel, array $row, int $now): array
    {
        $id = (int) $row['id'];
        $from = (string) $row['status'];
        if (!in_array($from, ['pending', 'retry', 'reconciling'], true)) {
            return ['outcome' => 'skipped', 'reason' => 'not_due_state_' . $from, 'row' => $row];
        }
        $panel = function_exists('select') ? select('marzban_panel', '*', 'name_panel', (string) $row['name_panel'], 'select') : null;
        if (!is_array($panel)) {
            $panel = select('marzban_panel', '*', 'code_panel', (string) $row['code_panel'], 'select');
        }
        $panelType = is_array($panel) ? (string) ($panel['type'] ?? '') : '';
        if ($from === 'pending' && is_array($panel)) {
            $snapshot = rx_pf_panel_snapshot($managePanel, (string) $panel['name_panel'], (string) $row['username'], $panelType);
            if (empty($snapshot['ok'])) {
                rxReservationDefer($id, $from, $now + 300, 'panel_read_failed: ' . (string) ($snapshot['error'] ?? ''));
                return ['outcome' => 'waiting', 'reason' => 'panel_read_failed', 'row' => $row];
            }
            if (!empty($snapshot['exists']) && !rxReservationTriggered($row, $snapshot, $now)) {
                rxReservationDefer($id, $from, $now + 240);
                return ['outcome' => 'waiting', 'reason' => 'not_triggered', 'row' => $row];
            }
        }
        $token = rx_pf_new_token();
        if (!rxReservationClaim($id, $from, $token, $now)) {
            return ['outcome' => 'skipped', 'reason' => 'claim_lost', 'row' => $row];
        }
        $row['processing_token'] = $token;
        $serviceKey = (string) ($row['service_key'] ?? '');
        if ($serviceKey === '') {
            $serviceKey = rx_rn_service_key($row['id_invoice'] ?? '', $row['code_panel'] ?? '', $row['username'] ?? '');
        }
        if (!rx_rn_lock_acquire($serviceKey, $token)) {
            rxReservationTransition($id, $token, $from, ['next_attempt_at' => $now + 60, 'last_error' => 'service_busy']);
            return ['outcome' => 'busy', 'reason' => 'service_busy', 'row' => $row];
        }
        try {
            return rxReservationExecute($managePanel, $row, $panel, $from, $token, $serviceKey, $now);
        } catch (Throwable $e) {
            rx_pf_log('RESERVATION_EXCEPTION', $e->getMessage(), ['reservation_id' => $id, 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return rxReservationFail($row, $token, 'exception: ' . $e->getMessage(), false, $now, ['status' => 'reconciling']);
        } finally {
            rx_rn_lock_release($serviceKey, $token);
        }
    }
}

if (!function_exists('rx_reservation_execute_manual')) {
    function rx_reservation_execute_manual($managePanel, array $row, array $panel, string $username, string $from, string $token, string $serviceKey, int $now): array
    {
        $id = (int) $row['id'];
        if ($from === 'reconciling') {
            return rxReservationFail($row, $token, 'manual_delivery_result_unverifiable', true, $now);
        }
        rxReservationTransition($id, $token, 'processing', ['stage' => 'applying', 'lease' => $now + RX_RN_LOCK_SECONDS]);
        $res = $managePanel->extend('ریست حجم و زمان', $row['new_limit_gb'], $row['time_day'], $username, (string) $row['code_product'], (string) $panel['code_panel'], null, ['lock_owner' => $token, 'service_key' => $serviceKey, 'source' => 'reservation', 'ref' => 'reservation:' . $id]);
        if (is_array($res) && !empty($res['status'])) {
            rxReservationTransition($id, $token, 'applied', ['stage' => 'done', 'applied_at' => date('Y/m/d H:i:s', $now), 'output' => json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), 'active_key_clear' => true]);
            return ['outcome' => 'applied', 'reason' => 'ok', 'row' => $row, 'result' => $res];
        }
        return rxReservationFail($row, $token, 'manual_delivery_failed: ' . (is_array($res) ? (string) ($res['code'] ?? ($res['msg'] ?? '')) : ''), false, $now);
    }
}

if (!function_exists('rx_reservation_settle_execution')) {
    function rx_reservation_settle_execution(array $row, string $from, string $token, int $now, $res): array
    {
        $id = (int) $row['id'];
        if (is_array($res) && !empty($res['status'])) {
            $after = $res['rx_renewal']['after'] ?? null;
            rxReservationTransition($id, $token, 'applied', [
                'stage' => 'done',
                'applied_at' => date('Y/m/d H:i:s', $now),
                'result_state' => $after,
                'output' => json_encode(['status' => true, 'operation_id' => $token, 'after' => $after], JSON_UNESCAPED_UNICODE),
                'active_key_clear' => true,
            ]);
            return ['outcome' => 'applied', 'reason' => 'ok', 'row' => $row, 'before' => $res['rx_renewal']['before'] ?? null, 'after' => $after];
        }
        if (rxRenewalIsPending($res)) {
            return rxReservationFail($row, $token, 'result_unverified: ' . (string) ($res['msg'] ?? ''), false, $now, ['status' => 'reconciling']);
        }
        $code = is_array($res) ? (string) ($res['code'] ?? '') : '';
        $msg = is_array($res) ? (is_scalar($res['msg'] ?? null) ? (string) $res['msg'] : json_encode($res['msg'] ?? '')) : (string) $res;
        if ($code === 'admin_blocked') {
            return rxReservationFail($row, $token, 'admin_blocked', false, $now, ['status' => 'retry', 'delay' => RX_RN_BLOCKED_RECHECK]);
        }
        if (in_array($code, ['pending_unsupported', 'result_not_usable', 'invalid_purchase', 'unknown_policy'], true)) {
            return rxReservationFail($row, $token, 'plan_refused: ' . $code, true, $now);
        }
        return rxReservationFail($row, $token, 'apply_failed: ' . ($code !== '' ? $code . ' ' : '') . $msg, false, $now, ['status' => $from === 'reconciling' ? 'reconciling' : 'retry']);
    }
}

if (!function_exists('rxReservationExecute')) {
    function rxReservationExecute($managePanel, array $row, $panel, string $from, string $token, string $serviceKey, int $now): array
    {
        $id = (int) $row['id'];
        if (!is_array($panel)) {
            return rxReservationFail($row, $token, 'panel_not_found', false, $now, ['status' => $from === 'reconciling' ? 'reconciling' : 'retry']);
        }
        $panelType = (string) ($panel['type'] ?? '');
        $username = (string) $row['username'];
        $fulfillment = rx_pf_decode($row['fulfillment'] ?? null) ?? [];
        if ($panelType === 'Manualsale') {
            return rx_reservation_execute_manual($managePanel, $row, $panel, $username, $from, $token, $serviceKey, $now);
        }
        $snapshot = rx_pf_panel_snapshot($managePanel, (string) $panel['name_panel'], $username, $panelType);
        if (empty($snapshot['ok'])) {
            return rxReservationFail($row, $token, 'panel_read_failed: ' . (string) ($snapshot['error'] ?? ''), false, $now, ['status' => $from === 'reconciling' ? 'reconciling' : 'retry']);
        }
        if (empty($snapshot['exists'])) {
            return rxReservationFail($row, $token, 'service_missing_on_panel', true, $now);
        }
        $storedTarget = rx_pf_decode($row['target_state'] ?? null);
        $plan = null;
        if ($from === 'reconciling' && is_array($storedTarget) && !empty($storedTarget['rx_renewal_plan'])) {
            $class = rxRenewalClassify($snapshot, $storedTarget);
            if ($class === 'target') {
                rxReservationTransition($id, $token, 'applied', ['stage' => 'done', 'applied_at' => date('Y/m/d H:i:s', $now), 'result_state' => rx_pf_limits($snapshot), 'output' => 'reconciled', 'active_key_clear' => true]);
                if (method_exists($managePanel, 'rxRenewalLocalSuccess')) {
                    $managePanel->rxRenewalLocalSuccess($panel, $username, (string) ($row['id_invoice'] ?? ''));
                }
                return ['outcome' => 'applied', 'reason' => 'reconciled', 'row' => $row, 'before' => rx_pf_decode($row['before_state'] ?? null), 'after' => rx_pf_limits($snapshot)];
            }
            if ($class === 'ambiguous') {
                return rxReservationFail($row, $token, 'panel_state_ambiguous_after_interrupted_activation', true, $now);
            }
            $plan = $storedTarget;
        }
        if ($plan === null) {
            if (!rxReservationTriggered($row, $snapshot, $now)) {
                rxReservationTransition($id, $token, 'pending', ['next_attempt_at' => $now + 240, 'stage' => null, 'attempt_reset' => true]);
                return ['outcome' => 'waiting', 'reason' => 'not_triggered', 'row' => $row];
            }
            $policy = (string) ($row['policy'] ?? 'reset_all');
            $plan = rxRenewalPlanForSnapshot($snapshot, $panel, $username, $policy, !empty($row['reset_usage']), $row['new_limit_gb'], $row['time_day'], 'reservation', $now);
            if (empty($plan['ok'])) {
                $reason = (string) ($plan['reason'] ?? 'plan_refused');
                if ($reason === 'admin_blocked') {
                    return rxReservationFail($row, $token, 'admin_blocked', false, $now, ['status' => 'retry', 'delay' => RX_RN_BLOCKED_RECHECK]);
                }
                if ($reason === 'panel_state_unavailable') {
                    return rxReservationFail($row, $token, $reason, false, $now);
                }
                return rxReservationFail($row, $token, 'plan_refused: ' . $reason, true, $now);
            }
            if (!rxReservationTransition($id, $token, 'processing', ['stage' => 'applying', 'before_state' => rx_pf_limits($snapshot), 'target_state' => $plan, 'lease' => $now + RX_RN_LOCK_SECONDS])) {
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
        return rx_reservation_settle_execution($row, $from, $token, $now, $res);
    }
}

if (!function_exists('rxReservationMarkNotified')) {
    function rxReservationMarkNotified(int $id, string $notifiedState): void
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("UPDATE queued_renewal SET notified_state = ? WHERE id = ?");
            $stmt->execute([substr($notifiedState, 0, 80), $id]);
        } catch (Throwable $e) {
            rx_pf_log('RESERVATION_NOTIFY_MARK_FAILED', $e->getMessage(), ['reservation_id' => $id]);
        }
    }
}

if (!function_exists('rxReservationFetch')) {
    function rxReservationFetch(int $id): ?array
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('rxRenewalOpsReconcile')) {
    function rxRenewalOpsReconcile($managePanel, int $now, int $limit = 20): array
    {
        global $pdo;
        $results = [];
        if (!($pdo instanceof PDO) || !rx_rn_ensure_schema()) {
            return $results;
        }
        if (function_exists('rxArFinalizePending')) {
            rxArFinalizePending($now, 10);
        }
        try {
            $pdo->prepare("UPDATE renewal_operation SET state = 'uncertain', stage = CONCAT(stage, ':interrupted'), updated_at = ? WHERE state = 'applying' AND source NOT IN ('payment', 'reservation') AND updated_at < ?")
                ->execute([$now, $now - RX_RN_LOCK_SECONDS]);
            $stmt = $pdo->prepare("SELECT * FROM renewal_operation WHERE state = 'uncertain' AND source NOT IN ('payment', 'reservation') AND (next_attempt_at IS NULL OR next_attempt_at <= ?) ORDER BY id ASC LIMIT " . (int) $limit);
            $stmt->execute([$now]);
            $ops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            rx_pf_log('RENEWAL_OPS_FETCH_FAILED', $e->getMessage());
            return $results;
        }
        foreach ($ops as $op) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
                break;
            }
            $claim = $pdo->prepare("UPDATE renewal_operation SET state = 'reconciling', next_attempt_at = ?, attempt_count = attempt_count + 1, updated_at = ? WHERE id = ? AND state = 'uncertain'");
            $claim->execute([$now + RX_RN_LOCK_SECONDS, $now, (int) $op['id']]);
            if ($claim->rowCount() !== 1) {
                continue;
            }
            $results[] = rxRenewalOpReconcileOne($managePanel, $op, $now);
        }
        return $results;
    }
}

if (!function_exists('rxRenewalOpSyncInvoiceName')) {
    function rxRenewalOpSyncInvoiceName(array $op, $target): bool
    {
        if (!in_array((string) ($op['source'] ?? ''), ['wallet', 'miniapp'], true) || !is_array($target)) {
            return false;
        }
        return rxRenewalSyncInvoiceName($op['id_invoice'] ?? '', $target['rx_product_name'] ?? '');
    }
}

if (!function_exists('rxRenewalOpReconcileOne')) {
    function rxRenewalOpReconcileOne($managePanel, array $op, int $now): array
    {
        $opId = (string) $op['op_id'];
        $attempts = (int) $op['attempt_count'] + 1;
        $target = rx_pf_decode($op['target_state'] ?? null);
        $back = function (string $error) use ($opId, $attempts, $now, $op, $target) {
            rx_renewal_log('reconcile', $error, [
                'panel_name' => (string) ($op['name_panel'] ?? ''),
                'username' => (string) ($op['username'] ?? ''),
                'invoice_id' => (string) ($op['id_invoice'] ?? ''),
                'method' => (string) ($op['method'] ?? ''),
                'policy' => (string) ($op['policy'] ?? ''),
                'plan' => is_array($target) ? $target : [],
                'before' => rx_pf_decode($op['before_state'] ?? null),
                'ref' => (string) ($op['ref'] ?? ''),
                'operation_id' => $opId,
                'mutation' => 'uncertain',
                'detail' => 'attempt=' . $attempts . ($attempts >= RX_RN_MAX_ATTEMPTS ? ' manual_review' : ' retry'),
            ]);
            if ($attempts >= RX_RN_MAX_ATTEMPTS) {
                rx_rn_op_update($opId, ['state' => 'manual_review', 'last_error' => 'max_attempts_exceeded: ' . $error]);
                rx_pf_alert_admin('renewal:' . $opId, 'تمدید نیازمند بررسی دستی است: ' . $error, ['username' => (string) $op['username']]);
                return ['op_id' => $opId, 'outcome' => 'manual_review', 'reason' => $error, 'op' => $op];
            }
            rx_rn_op_update($opId, ['state' => 'uncertain', 'last_error' => $error, 'next_attempt_at' => $now + rxReservationBackoff($attempts)]);
            return ['op_id' => $opId, 'outcome' => 'uncertain', 'reason' => $error, 'op' => $op];
        };
        if (!is_array($target) || empty($target['rx_renewal_plan'])) {
            rx_rn_op_update($opId, ['state' => 'manual_review', 'last_error' => 'target_missing']);
            return ['op_id' => $opId, 'outcome' => 'manual_review', 'reason' => 'target_missing', 'op' => $op];
        }
        $serviceKey = (string) $op['service_key'];
        if (!rx_rn_lock_acquire($serviceKey, $opId)) {
            rx_rn_op_update($opId, ['state' => 'uncertain', 'next_attempt_at' => $now + 60]);
            return ['op_id' => $opId, 'outcome' => 'busy', 'op' => $op];
        }
        try {
            $snapshot = rx_pf_panel_snapshot($managePanel, (string) $op['name_panel'], (string) $op['username']);
            $class = rxRenewalClassify($snapshot, $target);
            if ($class === 'unknown') {
                return $back('panel_read_failed: ' . (string) ($snapshot['error'] ?? ''));
            }
            if ($class === 'ambiguous') {
                rx_rn_op_update($opId, ['state' => 'manual_review', 'last_error' => 'panel_state_ambiguous']);
                rx_pf_alert_admin('renewal:' . $opId, 'وضعیت سرویس پس از تمدید نامشخص است و بررسی دستی لازم است', ['username' => (string) $op['username']]);
                return ['op_id' => $opId, 'outcome' => 'manual_review', 'reason' => 'panel_state_ambiguous', 'op' => $op];
            }
            if ($class === 'target') {
                rx_rn_op_update($opId, ['state' => 'verified', 'stage' => 'reconciled', 'result_state' => rx_pf_limits($snapshot)]);
                if (function_exists('rxArFinalizeOperation')) {
                    rxArFinalizeOperation($opId);
                }
                $panel = select('marzban_panel', '*', 'name_panel', (string) $op['name_panel'], 'select');
                if (is_array($panel) && method_exists($managePanel, 'rxRenewalLocalSuccess')) {
                    $managePanel->rxRenewalLocalSuccess($panel, (string) $op['username'], (string) ($op['id_invoice'] ?? ''));
                }
                rxRenewalOpSyncInvoiceName($op, $target);
                return ['op_id' => $opId, 'outcome' => 'verified', 'op' => $op, 'after' => rx_pf_limits($snapshot)];
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
                rxRenewalOpSyncInvoiceName($op, $target);
                return ['op_id' => $opId, 'outcome' => 'verified', 'op' => $op, 'after' => $res['rx_renewal']['after'] ?? null];
            }
            return $back('reapply_failed: ' . (is_array($res) ? (string) ($res['code'] ?? '') : ''));
        } finally {
            rx_rn_lock_release($serviceKey, $opId);
        }
    }
}

if (!function_exists('rxReservationAdminPolicies')) {
    function rxReservationAdminPolicies(): array
    {
        return ['carry_all', 'reset_all', 'reset_traffic_append_time', 'carry_traffic_reset_time'];
    }
}

if (!function_exists('rxReservationSettingsFromRaw')) {
    function rxReservationSettingsFromRaw($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $configured = isset($raw['policy']) || isset($raw['conditions']) || isset($raw['default_policy']) || isset($raw['default_trigger']);
        $policy = (string) ($raw['policy'] ?? ($raw['default_policy'] ?? ''));
        if (!in_array($policy, rxReservationAdminPolicies(), true)) {
            $policy = 'reset_all';
        }
        if (isset($raw['conditions'])) {
            $conditions = rxReservationNormalizeConditions($raw['conditions']);
        } else {
            $conditions = rxReservationConditionsFromTrigger((string) ($raw['default_trigger'] ?? 'either'));
        }
        $dateAt = isset($raw['date_at']) && is_numeric($raw['date_at']) && (int) $raw['date_at'] > 0 ? (int) $raw['date_at'] : null;
        if ($dateAt === null) {
            $conditions = array_values(array_diff($conditions, ['date']));
        }
        if ($conditions === []) {
            $conditions = ['traffic', 'expire'];
        }
        return ['policy' => $policy, 'conditions' => $conditions, 'date_at' => in_array('date', $conditions, true) ? $dateAt : null, 'configured' => $configured];
    }
}

if (!function_exists('rxReservationPanelSettings')) {
    function rxReservationPanelSettings($panel): array
    {
        $raw = is_array($panel) ? json_decode((string) ($panel['reservation_settings'] ?? ''), true) : null;
        return rxReservationSettingsFromRaw($raw);
    }
}

if (!function_exists('rxReservationSettingsJson')) {
    function rxReservationSettingsJson(array $settings): string
    {
        return (string) json_encode([
            'policy' => (string) $settings['policy'],
            'conditions' => rxReservationNormalizeConditions($settings['conditions']),
            'date_at' => in_array('date', rxReservationNormalizeConditions($settings['conditions']), true) ? (int) $settings['date_at'] : null,
        ], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rxReservationPanelSelection')) {
    function rxReservationPanelSelection($panel): array
    {
        $settings = rxReservationPanelSettings($panel);
        return [
            'policy' => $settings['policy'],
            'conditions' => $settings['conditions'],
            'trigger_at' => $settings['date_at'],
            'reset_usage' => false,
        ];
    }
}

if (!function_exists('rxReservationStoredSelection')) {
    function rxReservationStoredSelection($stored): ?array
    {
        if (!is_array($stored) || !isset($stored['policy'])) {
            return null;
        }
        if (!isset($stored['conditions']) && !isset($stored['trigger'])) {
            return null;
        }
        return $stored;
    }
}

if (!function_exists('rxReservationUiEnabled')) {
    function rxReservationUiEnabled($panel): bool
    {
        if (!is_array($panel) || ($panel['type'] ?? '') === 'Manualsale') {
            return false;
        }
        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel)) {
            return false;
        }
        $mode = rxRenewalMode($panel['Methodextend'] ?? '');
        return is_array($mode) && !empty($mode['queued']);
    }
}

if (!function_exists('rxReservationAdminState')) {
    function rxReservationAdminState(array $settings): array
    {
        $mask = 0;
        foreach (rxReservationConditions() as $index => $key) {
            if (in_array($key, $settings['conditions'], true)) {
                $mask |= (1 << $index);
            }
        }
        $index = array_search($settings['policy'], rxReservationAdminPolicies(), true);
        return ['p' => $index === false ? 1 : (int) $index, 'mask' => $mask, 'at' => (int) ($settings['date_at'] ?? 0)];
    }
}

if (!function_exists('rxReservationAdminSettingsFromState')) {
    function rxReservationAdminSettingsFromState(array $state): array
    {
        $conditions = [];
        foreach (rxReservationConditions() as $index => $key) {
            if (((int) $state['mask'] >> $index) & 1) {
                $conditions[] = $key;
            }
        }
        return [
            'policy' => rxReservationAdminPolicies()[(int) $state['p']] ?? 'reset_all',
            'conditions' => $conditions,
            'date_at' => in_array('date', $conditions, true) && (int) $state['at'] > 0 ? (int) $state['at'] : null,
        ];
    }
}

if (!function_exists('rxReservationAdminCallback')) {
    function rxReservationAdminCallback(string $code, array $state, string $action): string
    {
        return 'rsva:' . $code . ':' . (int) $state['p'] . ':' . ((int) $state['mask'] & 7) . ':' . base_convert((string) max(0, (int) $state['at']), 10, 36) . ':' . (!empty($state['selected']) ? '1' : '0') . ':' . $action;
    }
}

if (!function_exists('rxReservationAdminParse')) {
    function rxReservationAdminParse(string $data): ?array
    {
        if (!preg_match('/^rsva:([A-Za-z0-9_-]{1,40}):([0-3]):([0-7]):([0-9a-z]{1,8}):(?:([01]):)?([a-z])([0-9]?)$/', $data, $m)) {
            return null;
        }
        return [
            'code' => $m[1],
            'state' => ['p' => (int) $m[2], 'mask' => (int) $m[3], 'at' => (int) base_convert($m[4], 36, 10), 'selected' => $m[5] === '' || $m[5] === '1'],
            'action' => $m[6],
            'arg' => $m[7] === '' ? null : (int) $m[7],
        ];
    }
}

if (!function_exists('rxReservationAdminStepEncode')) {
    function rxReservationAdminStepEncode(string $code, array $state): string
    {
        return 'rsvadate|' . $code . '|' . (int) $state['p'] . '|' . ((int) $state['mask'] & 7) . '|' . max(0, (int) $state['at']);
    }
}

if (!function_exists('rxReservationAdminStepDecode')) {
    function rxReservationAdminStepDecode(string $step): ?array
    {
        if (!preg_match('/^rsvadate\|([A-Za-z0-9_-]{1,40})\|([0-3])\|([0-7])\|([0-9]{1,12})$/', $step, $m)) {
            return null;
        }
        return ['code' => $m[1], 'state' => ['p' => (int) $m[2], 'mask' => (int) $m[3], 'at' => (int) $m[4]]];
    }
}

if (!function_exists('rxReservationAdminValidate')) {
    function rxReservationAdminValidate(array $state, int $now): ?string
    {
        if (((int) $state['mask'] & 7) === 0) {
            return rxRenewalText('admin_conditions_empty');
        }
        if ((((int) $state['mask'] >> 2) & 1) === 1) {
            if ((int) $state['at'] <= 0) {
                return rxRenewalText('admin_date_required');
            }
            if ((int) $state['at'] <= $now) {
                return rxRenewalText('admin_date_past');
            }
        }
        return null;
    }
}

if (!function_exists('rxReservationAdminApply')) {
    function rxReservationAdminApply(array $state, string $action, ?int $arg): array
    {
        if ($action === 'p' && $arg !== null && $arg < count(rxReservationAdminPolicies())) {
            $state['p'] = $arg;
        } elseif ($action === 'c' && $arg !== null && $arg < count(rxReservationConditions())) {
            $state['mask'] = ((int) $state['mask'] ^ (1 << $arg)) & 7;
        }
        return $state;
    }
}

if (!function_exists('rxReservationAdminView')) {
    function rxReservationAdminView(array $panel, array $state, string $notice = ''): array
    {
        $code = (string) $panel['code_panel'];
        $policies = rxReservationAdminPolicies();
        $settings = rxReservationAdminSettingsFromState($state);
        $lines = [rxRenewalText('admin_title', ['panel' => htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8')]), ''];
        if ($notice !== '') {
            $lines[] = $notice;
            $lines[] = '';
        }
        $lines[] = rxRenewalText('admin_hint');
        $lines[] = '';
        foreach ($policies as $index => $key) {
            $lines[] = ($index + 1) . '. ' . rxRenewalPolicyLabel($key);
        }
        $lines[] = '';
        if ($settings['conditions'] !== [] && (!in_array('date', $settings['conditions'], true) || (int) $state['at'] > 0)) {
            $lines[] = rxRenewalText('admin_summary', ['text' => rxReservationActivationText($settings['conditions'], $state['at'])]);
        }
        $lines[] = rxRenewalText('admin_timezone_note', ['tz' => date_default_timezone_get()]);
        if (!rxRenewalPendingSupported((string) ($panel['type'] ?? ''))) {
            $lines[] = rxRenewalText('admin_pending_unsupported');
        }
        $rows = [[['text' => rxRenewalText('admin_section_policy'), 'callback_data' => rxReservationAdminCallback($code, $state, 'x')]]];
        foreach ($policies as $index => $key) {
            $rows[] = [['text' => ((int) $state['p'] === $index ? rxRenewalText('mark_on') . ' ' : '') . rxRenewalPolicyLabel($key, false, true), 'callback_data' => rxReservationAdminCallback($code, $state, 'p' . $index)]];
        }
        if (empty($state['selected'])) {
            $rows[] = [['text' => rxRenewalText('admin_btn_back'), 'callback_data' => rxReservationAdminCallback($code, $state, 'b')]];
            return ['text' => implode("\n", $lines), 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
        }
        $rows[] = [['text' => rxRenewalText('admin_section_conditions'), 'callback_data' => rxReservationAdminCallback($code, $state, 'x')]];
        foreach (rxReservationConditions() as $index => $key) {
            $enabled = ((int) $state['mask'] >> $index) & 1;
            $label = rxRenewalText('cond_' . $key);
            if ($key === 'date' && $enabled && (int) $state['at'] > 0) {
                $label = rxRenewalText('cond_date_at', ['date' => rxRenewalFormatExpire((int) $state['at'])]);
            }
            $rows[] = [['text' => rxRenewalText($enabled ? 'mark_on' : 'mark_off') . ' ' . $label, 'callback_data' => rxReservationAdminCallback($code, $state, 'c' . $index)]];
        }
        if ((((int) $state['mask'] >> 2) & 1) === 1) {
            $rows[] = [['text' => rxRenewalText('admin_btn_date'), 'callback_data' => rxReservationAdminCallback($code, $state, 'e')]];
        }
        $rows[] = [
            ['text' => rxRenewalText('admin_btn_save'), 'callback_data' => rxReservationAdminCallback($code, $state, 's')],
            ['text' => rxRenewalText('admin_btn_back'), 'callback_data' => rxReservationAdminCallback($code, $state, 'b')],
        ];
        return ['text' => implode("\n", $lines), 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
    }
}
