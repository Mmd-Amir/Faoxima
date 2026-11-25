<?php

namespace Faoxima\Services\Renewal;

use PDO;
use RuntimeException;
use Throwable;

final class ReservationStore
{
    public static function unresolvedStatuses(): array
    {
        return ['pending', 'processing', 'retry', 'reconciling'];
    }

    public static function unresolvedSql(): string
    {
        return "'" . implode("','", self::unresolvedStatuses()) . "'";
    }

    public static function queuedColumns(): array
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

    public static function queuedTableDdl(): string
    {
        $extra = [];
        foreach (self::queuedColumns() as $name => $definition) {
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

    public static function identifierColumns(): array
    {
        return [
            'renewal_lock' => 'owner',
            'renewal_operation' => 'op_id',
        ];
    }

    public static function identifierColumnWidth(array $column): ?int
    {
        $type = strtolower(trim((string) ($column['Type'] ?? '')));
        if (!preg_match('/^(?:var)?char\((\d+)\)$/', $type, $matches)) {
            return null;
        }
        return (int) $matches[1];
    }

    public static function identifierColumnDefinition(array $column): string
    {
        $collation = (string) ($column['Collation'] ?? '');
        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $collation) !== 1) {
            throw new RuntimeException('column collation is unavailable or invalid');
        }
        return 'VARCHAR(' . max(128, self::identifierColumnWidth($column) ?? 128) . ') COLLATE ' . $collation . ' NOT NULL';
    }

    public static function identifierColumnProblem(string $table, string $column, array $row): ?string
    {
        $key = $table . '.' . $column;
        if ($row === []) {
            return $key . ' is missing';
        }
        $type = strtolower(trim((string) ($row['Type'] ?? '')));
        $width = self::identifierColumnWidth($row);
        if ($width === null) {
            return $key . ' has an unsupported type';
        }
        if (strpos($type, 'varchar(') !== 0) {
            return $key . ' must be VARCHAR';
        }
        if ($width < 128) {
            return $key . ' must allow at least 128 characters';
        }
        if (strtoupper((string) ($row['Null'] ?? '')) !== 'NO') {
            return $key . ' must be NOT NULL';
        }
        return null;
    }

    public static function identifierSchemaProblems($runtimePdo): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return ['database' => 'database connection is unavailable'];
        }
        $problems = [];
        foreach (self::identifierColumns() as $table => $column) {
            $key = $table . '.' . $column;
            try {
                $tableStmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($tableStmt === false || $tableStmt->fetch(PDO::FETCH_NUM) === false) {
                    $problems[$key] = $key . ': table is missing';
                    continue;
                }
                $stmt = $pdo->query("SHOW FULL COLUMNS FROM `{$table}` WHERE `Field` = '{$column}'");
                $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                $problem = is_array($row) ? self::identifierColumnProblem($table, $column, $row) : $key . ' is missing';
                if ($problem !== null) {
                    $problems[$key] = $problem;
                }
            } catch (Throwable $e) {
                $problems[$key] = $key . ': ' . $e->getMessage();
            }
        }
        return $problems;
    }

    public static function ensureIdentifierColumns($runtimePdo): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return ['database' => 'database connection is unavailable'];
        }
        $problems = [];
        foreach (self::identifierColumns() as $table => $column) {
            $key = $table . '.' . $column;
            try {
                $tableStmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($tableStmt === false || $tableStmt->fetch(PDO::FETCH_NUM) === false) {
                    continue;
                }
                $stmt = $pdo->query("SHOW FULL COLUMNS FROM `{$table}` WHERE `Field` = '{$column}'");
                $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                if (!is_array($row)) {
                    $problems[$key] = $key . ' is missing';
                    continue;
                }
                if (self::identifierColumnProblem($table, $column, $row) === null) {
                    continue;
                }
                if (self::identifierColumnWidth($row) === null) {
                    $problems[$key] = $key . ' has an unsupported type';
                    continue;
                }
                $collation = $row['Collation'] ?? null;
                if ($pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` " . self::identifierColumnDefinition($row)) === false) {
                    $problems[$key] = $key . ': could not widen identifier column';
                    continue;
                }
                $stmt = $pdo->query("SHOW FULL COLUMNS FROM `{$table}` WHERE `Field` = '{$column}'");
                $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                $problem = is_array($row) ? self::identifierColumnProblem($table, $column, $row) : $key . ' is missing after upgrade';
                if ($problem === null && ($row['Collation'] ?? null) !== $collation) {
                    $problem = $key . ' collation changed during upgrade';
                }
                if ($problem !== null) {
                    $problems[$key] = $problem;
                }
            } catch (Throwable $e) {
                $problems[$key] = $key . ': ' . $e->getMessage();
            }
        }
        return $problems;
    }

    public static function ensureSchema($runtimePdo): bool
    {
        static $ready = false;
        $pdo = $runtimePdo;
        if ($ready) {
            return true;
        }
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS renewal_lock (
                service_key VARCHAR(191) NOT NULL PRIMARY KEY,
                owner VARCHAR(128) NOT NULL,
                lease_expires_at INT UNSIGNED NOT NULL,
                updated_at INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS renewal_operation (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                op_id VARCHAR(128) NOT NULL,
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
            $identifierProblems = self::identifierSchemaProblems($pdo);
            if ($identifierProblems !== []) {
                \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RENEWAL_SCHEMA_FAILED', implode('; ', $identifierProblems), ['details' => array_keys($identifierProblems)]);
                return false;
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
                        $migrateSettings->execute([\Faoxima\Services\Renewal\ReservationCriteria::reservationSettingsJson(\Faoxima\Services\Renewal\ReservationCriteria::reservationSettingsFromRaw(json_decode((string) $legacyRow['reservation_settings'], true))), (string) $legacyRow['code_panel']]);
                    }
                }
            }
            $exists = $pdo->query("SHOW TABLES LIKE 'queued_renewal'");
            if ($exists === false || $exists->fetch(PDO::FETCH_NUM) === false) {
                $pdo->exec(self::queuedTableDdl());
                $ready = true;
                return true;
            }
            $columns = [];
            foreach ($pdo->query("SHOW COLUMNS FROM queued_renewal")->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[(string) $col['Field']] = true;
            }
            $legacyMigration = !isset($columns['policy']);
            foreach (self::queuedColumns() as $name => $definition) {
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
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RENEWAL_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }

    public static function serviceKey($idInvoice, $codePanel, $username): string
    {
        $idInvoice = trim((string) $idInvoice);
        $key = $idInvoice !== '' ? 'inv:' . $idInvoice : 'pu:' . (string) $codePanel . '|' . (string) $username;
        return substr($key, 0, 191);
    }

    public static function lockAcquireResult($runtimePdo, string $serviceKey, string $owner, int $ttl = RX_RN_LOCK_SECONDS): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'database_unavailable', 'contended' => false];
        }
        if (!self::ensureSchema($runtimePdo)) {
            return ['ok' => false, 'reason' => 'schema_unavailable', 'contended' => false];
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
            $check = $pdo->prepare("SELECT owner, lease_expires_at FROM renewal_lock WHERE service_key = ? LIMIT 1");
            $check->execute([$serviceKey]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && hash_equals((string) ($row['owner'] ?? ''), $owner)) {
                return ['ok' => true, 'reason' => 'acquired', 'contended' => false];
            }
            $contended = is_array($row) && (int) ($row['lease_expires_at'] ?? 0) >= $now;
            return [
                'ok' => false,
                'reason' => $contended ? 'lock_contended' : 'lock_unavailable',
                'contended' => $contended,
            ];
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RENEWAL_LOCK_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
            return ['ok' => false, 'reason' => 'lock_error', 'contended' => false];
        }
    }

    public static function lockAcquire($runtimePdo, string $serviceKey, string $owner, int $ttl = RX_RN_LOCK_SECONDS): bool
    {
        return !empty(self::lockAcquireResult($runtimePdo, $serviceKey, $owner, $ttl)['ok']);
    }

    public static function lockRelease($runtimePdo, string $serviceKey, string $owner): void
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM renewal_lock WHERE service_key = ? AND owner = ?");
            $stmt->execute([$serviceKey, $owner]);
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RENEWAL_LOCK_RELEASE_FAILED', $e->getMessage(), ['service_key' => $serviceKey]);
        }
    }

    public static function walletRenewalBlocked(PDO $pdo, string $serviceKey): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM renewal_operation WHERE service_key = ?
             AND source IN ('wallet', 'miniapp')
             AND state IN ('applying', 'uncertain', 'reconciling', 'manual_review') LIMIT 1"
        );
        if ($statement === false || !$statement->execute([$serviceKey])) {
            throw new \RuntimeException('renewal_recovery_lookup_failed');
        }
        return $statement->fetchColumn() !== false;
    }

    public static function trimError($error): string
    {
        $error = is_scalar($error) ? (string) $error : (string) json_encode($error, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return function_exists('mb_substr') ? mb_substr($error, 0, 2000, 'UTF-8') : substr($error, 0, 2000);
    }

    public static function opStart($runtimePdo, array $op): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || !self::ensureSchema($runtimePdo)) {
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
                \Faoxima\Payments\Fulfillment\Claims::json($op['before'] ?? null),
                \Faoxima\Payments\Fulfillment\Claims::json($op['target'] ?? null),
                is_array($op['finalize'] ?? null) ? \Faoxima\Payments\Fulfillment\Claims::json($op['finalize']) : null,
                is_array($op['finalize'] ?? null) ? 'pending' : null,
                $now,
                $now,
            ]);
            return true;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RENEWAL_OPERATION_START_FAILED', $e->getMessage(), ['op_id' => $op['op_id'] ?? null]);
            return false;
        }
    }

    public static function opUpdate($runtimePdo, string $opId, array $fields): void
    {
        $pdo = $runtimePdo;
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
                $value = \Faoxima\Payments\Fulfillment\Claims::json($value);
            } elseif ($name === 'last_error' && $value !== null) {
                $value = self::trimError($value);
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
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('RENEWAL_OPERATION_UPDATE_FAILED', $e->getMessage(), ['op_id' => $opId]);
        }
    }
}
