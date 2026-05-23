<?php


if (!function_exists('faoxima_schema_table_exists')) {


function faoxima_schema_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1"
        );
        $stmt->execute([':t' => $table]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        error_log('[schema] table_exists ' . $table . ': ' . $e->getMessage());
        return false;
    }
}


function faoxima_schema_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :t
               AND COLUMN_NAME  = :c LIMIT 1"
        );
        $stmt->execute([':t' => $table, ':c' => $column]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        error_log('[schema] column_exists ' . $table . '.' . $column . ': ' . $e->getMessage());
        return false;
    }
}


function faoxima_schema_ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (!faoxima_schema_table_exists($pdo, $table)) {

        return;
    }
    if (faoxima_schema_column_exists($pdo, $table, $column)) {
        return;
    }
    try {
        $sanT = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $sanC = preg_replace('/[^A-Za-z0-9_]/', '', $column);
        $pdo->exec("ALTER TABLE `{$sanT}` ADD COLUMN `{$sanC}` {$definition}");
    } catch (\PDOException $e) {

        if (!empty($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1060) return;
        error_log('[schema] ensure_column ' . $table . '.' . $column . ': ' . $e->getMessage());
    } catch (\Throwable $e) {
        error_log('[schema] ensure_column ' . $table . '.' . $column . ': ' . $e->getMessage());
    }
}


function faoxima_schema_ready(PDO $pdo): void {
    if (!empty($_SESSION['__faoxima_schema_ok'])) {
        return;
    }


    faoxima_schema_ensure_column(
        $pdo, 'marzban_panel', 'emergency_panel_status',
        "VARCHAR(50) NOT NULL DEFAULT 'off_emergency_panel'"
    );
    faoxima_schema_ensure_column(
        $pdo, 'marzban_panel', 'national_net_status',
        "VARCHAR(50) NOT NULL DEFAULT 'off_national_net'"
    );
    faoxima_schema_ensure_column(
        $pdo, 'marzban_panel', 'emergency_source_panel',
        "VARCHAR(191) NULL"
    );
    faoxima_schema_ensure_column(
        $pdo, 'marzban_panel', 'stock_source_panel',
        "VARCHAR(191) NULL"
    );


    if (!faoxima_schema_table_exists($pdo, 'crypto_wallets')) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS crypto_wallets (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                currency VARCHAR(20) NOT NULL,
                network VARCHAR(20) NOT NULL,
                wallet_address VARCHAR(255) NOT NULL DEFAULT '',
                label VARCHAR(255) DEFAULT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                min_irt BIGINT NOT NULL DEFAULT 20000,
                max_irt BIGINT NOT NULL DEFAULT 100000000,
                cashback_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
                rate_irt_override DECIMAL(20,4) DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_currency (currency)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $seed = $pdo->prepare(
                "INSERT IGNORE INTO crypto_wallets (currency, network, wallet_address, label, enabled)
                 VALUES (?,?,?,?,0)"
            );
            foreach ([
                ['TRX',         'TRON', '', 'ترون (TRX)'],
                ['TON',         'TON',  '', 'تون (TON)'],
                ['USDT_TRC20',  'TRON', '', 'تتر روی شبکه ترون (USDT-TRC20)'],
                ['USDT_TON',    'TON',  '', 'تتر روی شبکه تون (USDT-TON)'],
            ] as $r) $seed->execute($r);
        } catch (\Throwable $e) {
            error_log('[schema] crypto_wallets: ' . $e->getMessage());
        }
    }


    if (!faoxima_schema_table_exists($pdo, 'app')) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS app (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                link VARCHAR(500) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            error_log('[schema] app: ' . $e->getMessage());
        }
    }


    if (!faoxima_schema_table_exists($pdo, 'shopSetting')) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS shopSetting (
                Namevalue VARCHAR(500) PRIMARY KEY NOT NULL,
                value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");

            $seed = $pdo->prepare(
                "INSERT IGNORE INTO shopSetting (Namevalue, value) VALUES (?, ?)"
            );
            foreach ([
                ['statusextra',         'offextra'],
                ['statusdirectpabuy',   'ondirectbuy'],
                ['statustimeextra',     'ontimeextraa'],
                ['statusdisorder',      'offdisorder'],
                ['statuschangeservice', 'onstatus'],
                ['statusshowprice',     'offshowprice'],
                ['configshow',          'onconfig'],
                ['backserviecstatus',   'on'],
                ['minbalancebuybulk',   '0'],
                ['customvolmef',        '4000'],
                ['customvolmen',        '4000'],
                ['customvolmen2',       '4000'],
                ['customtimepricef',    '4000'],
                ['customtimepricen',    '4000'],
                ['customtimepricen2',   '4000'],
            ] as $r) $seed->execute($r);
        } catch (\Throwable $e) {
            error_log('[schema] shopSetting: ' . $e->getMessage());
        }
    }


    $_SESSION['__faoxima_schema_ok'] = true;
}

}

