<?php

namespace Faoxima\Payments\Fulfillment;

use PDO;
use Throwable;
use DateTime;

final class WalletSchema
{
    public static function walletDefinition(): array
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

    public static function walletTypeBase(string $type): string
    {
        return (string) preg_replace('/[^a-z].*$/s', '', strtolower(trim($type)));
    }

    public static function walletColumnProblem(string $name, array $column): ?string
    {
        $type = self::walletTypeBase((string) ($column['Type'] ?? ''));
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

    public static function walletIndexes(PDO $pdo): array
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

    public static function walletSchemaVerify(PDO $pdo): array
    {
        $definition = self::walletDefinition();
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
            $problem = self::walletColumnProblem($name, $columns[$name]);
            if ($problem !== null) {
                return ['ok' => false, 'reason' => 'wallet_ledger.' . $name . ': ' . $problem, 'details' => [$name]];
            }
        }
        $engineRow = $pdo->query("SHOW TABLE STATUS LIKE 'wallet_ledger'")->fetch(PDO::FETCH_ASSOC);
        if (!is_array($engineRow) || strcasecmp((string) ($engineRow['Engine'] ?? ''), 'InnoDB') !== 0) {
            return ['ok' => false, 'reason' => 'wallet_ledger must use the InnoDB engine', 'details' => ['engine']];
        }
        $indexes = self::walletIndexes($pdo);
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

    public static function walletSchemaReconcile(PDO $pdo): array
    {
        $definition = self::walletDefinition();
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
            $problem = self::walletColumnProblem($name, $columns[$name]);
            if ($problem !== null) {
                return ['ok' => false, 'reason' => 'wallet_ledger.' . $name . ': ' . $problem . '; manual reconciliation required', 'details' => [$name]];
            }
        }
        foreach (['balance_after', 'description', 'id_order', 'ref_table', 'ref_id', 'fulfillment_key'] as $name) {
            if (!isset($columns[$name])) {
                $pdo->exec('ALTER TABLE wallet_ledger ADD COLUMN `' . $name . '` ' . $definition['columns'][$name]);
                continue;
            }
            $problem = self::walletColumnProblem($name, $columns[$name]);
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
        $indexes = self::walletIndexes($pdo);
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
        return self::walletSchemaVerify($pdo);
    }

    public static function walletReady($runtimePdo): bool
    {
        static $ready = false;
        $pdo = $runtimePdo;
        if ($ready) {
            return true;
        }
        if (!($pdo instanceof PDO)) {
            return false;
        }
        if ($pdo->inTransaction()) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_WALLET_SCHEMA_FAILED', 'wallet_ledger upgrade cannot run inside a transaction');
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
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_WALLET_SCHEMA_FAILED', 'could not acquire wallet_ledger schema lock', ['lock' => $lockName]);
            return false;
        }
        try {
            $result = self::walletSchemaReconcile($pdo);
            if (empty($result['ok'])) {
                \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_WALLET_SCHEMA_FAILED', (string) ($result['reason'] ?? 'wallet_ledger schema upgrade failed'), ['details' => $result['details'] ?? []]);
                return false;
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_WALLET_SCHEMA_FAILED', $e->getMessage());
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
