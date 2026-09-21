<?php


@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
date_default_timezone_set('Asia/Tehran');
if (function_exists('putenv') && !preg_match('/(^|,)\s*putenv\s*(,|$)/', strtolower((string) ini_get('disable_functions')))) {
    @putenv('TZ=Asia/Tehran');
}

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
@chdir(__DIR__);

require_once 'function.php';
require_once 'config.php';
require_once 'botapi.php';
global $connect, $pdo;

$rxDbHost = isset($dbhost) && $dbhost !== '' ? (string) $dbhost : '';
$rxDbName = isset($dbname) && $dbname !== '' ? (string) $dbname : '';
$rxDbUser = isset($usernamedb) && $usernamedb !== '' ? (string) $usernamedb : '';
$rxDbPass = isset($passworddb) ? (string) $passworddb : '';

if ($rxDbHost === '') {
    $rxEnvHost = getenv('DB_HOST');
    $rxDbHost = ($rxEnvHost !== false && $rxEnvHost !== '') ? $rxEnvHost : 'db';
}
if ($rxDbName === '') {
    $rxEnvName = getenv('DB_NAME');
    if ($rxEnvName === false || $rxEnvName === '') {
        $rxEnvName = getenv('MYSQL_DATABASE');
    }
    $rxDbName = ($rxEnvName !== false) ? (string) $rxEnvName : '';
}
if ($rxDbUser === '') {
    $rxEnvUser = getenv('DB_USER');
    if ($rxEnvUser === false || $rxEnvUser === '') {
        $rxEnvUser = getenv('MYSQL_USER');
    }
    $rxDbUser = ($rxEnvUser !== false) ? (string) $rxEnvUser : '';
}
if ($rxDbPass === '') {
    $rxEnvPass = getenv('DB_PASS');
    if ($rxEnvPass === false || $rxEnvPass === '') {
        $rxEnvPass = getenv('MYSQL_PASSWORD');
    }
    $rxDbPass = ($rxEnvPass !== false) ? (string) $rxEnvPass : '';
}

if (!(isset($connect) && $connect instanceof mysqli)) {
    if ($rxDbName !== '' && $rxDbUser !== '') {
        try {
            $rxMysqli = @new mysqli($rxDbHost, $rxDbUser, $rxDbPass, $rxDbName);
            if ($rxMysqli->connect_errno === 0) {
                $rxMysqli->set_charset('utf8mb4');
                $connect = $rxMysqli;
            } else {
                $rxMysqli->close();
            }
        } catch (Throwable $e) {
            error_log('[table.php] MySQLi fallback connection failed');
        }
    }
}

if (!(isset($pdo) && $pdo instanceof PDO)) {
    if ($rxDbName !== '' && $rxDbUser !== '') {
        try {
            $pdo = new PDO(
                "mysql:host={$rxDbHost};dbname={$rxDbName};charset=utf8mb4",
                $rxDbUser,
                $rxDbPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (Throwable $e) {
            error_log('[table.php] PDO fallback connection failed');
        }
    }
}

if (!(isset($connect) && $connect instanceof mysqli) || !(isset($pdo) && $pdo instanceof PDO)) {
    throw new RuntimeException('Database connection is unavailable for table migrations.');
}

if (!function_exists('rxEnsureUtf8mb4Schema')) {
    function rxEnsureUtf8mb4Schema($connection, $pdoConnection = null)
    {
        if (!is_object($connection) || !method_exists($connection, 'query')) {
            return;
        }
        try {
            if (method_exists($connection, 'set_charset')) {
                $connection->set_charset('utf8mb4');
            }
            $connection->query("SET collation_connection = 'utf8mb4_unicode_ci'");
            if (is_object($pdoConnection) && method_exists($pdoConnection, 'exec')) {
                try {
                    $pdoConnection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (Throwable $e) {
                    error_log('[table.php] PDO utf8mb4 connection: ' . $e->getMessage());
                }
            }
            $databaseResult = $connection->query('SELECT DATABASE() AS database_name');
            $databaseRow = ($databaseResult && isset($databaseResult->num_rows) && $databaseResult->num_rows > 0) ? $databaseResult->fetch_assoc() : null;
            $databaseName = is_array($databaseRow) ? (string) ($databaseRow['database_name'] ?? '') : '';
            if ($databaseName === '') {
                return;
            }
            $schemaResult = $connection->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()");
            $schemaRow = ($schemaResult && isset($schemaResult->num_rows) && $schemaResult->num_rows > 0) ? $schemaResult->fetch_assoc() : null;
            if (is_array($schemaRow) && (
                strtolower((string) ($schemaRow['DEFAULT_CHARACTER_SET_NAME'] ?? '')) !== 'utf8mb4'
                || strtolower((string) ($schemaRow['DEFAULT_COLLATION_NAME'] ?? '')) !== 'utf8mb4_unicode_ci'
            )) {
                $safeDatabase = str_replace('`', '``', $databaseName);
                try {
                    $connection->query("ALTER DATABASE `{$safeDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (Throwable $e) {
                    error_log('[table.php] utf8mb4 database default: ' . $e->getMessage());
                }
            }
            $tablesResult = $connection->query("SELECT DISTINCT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME <> 'utf8mb4' UNION SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_COLLATION IS NOT NULL AND TABLE_COLLATION NOT LIKE 'utf8mb4%'");
            if (!$tablesResult) {
                return;
            }
            while ($tableRow = $tablesResult->fetch_assoc()) {
                $tableName = (string) ($tableRow['TABLE_NAME'] ?? '');
                if ($tableName === '') {
                    continue;
                }
                $safeTable = str_replace('`', '``', $tableName);
                $targetCollation = in_array($tableName, ['category', 'reagent_report'], true) ? 'utf8mb4_bin' : 'utf8mb4_unicode_ci';
                try {
                    $connection->query("ALTER TABLE `{$safeTable}` CONVERT TO CHARACTER SET utf8mb4 COLLATE {$targetCollation}");
                } catch (Throwable $e) {
                    error_log("[table.php] utf8mb4 conversion {$tableName}: " . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            error_log('[table.php] utf8mb4 schema migration: ' . $e->getMessage());
        }
    }
}

rxEnsureUtf8mb4Schema($connect, $pdo ?? null);


if (!function_exists('rxTableColumnExists')) {
    function rxTableColumnExists($connection, $tableName, $columnName)
    {
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        $columnName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);
        if ($tableName === '' || $columnName === '') {
            return false;
        }
        try {
            $safeColumn = method_exists($connection, 'real_escape_string') ? $connection->real_escape_string($columnName) : $columnName;
            $result = $connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$safeColumn}'");
            if ($result && isset($result->num_rows)) {
                return $result->num_rows > 0;
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }
}

if (!function_exists('rxSafeAddColumn')) {
    function rxSafeAddColumn($connection, $tableName, $columnName, $definition)
    {
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        $columnName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);
        if ($tableName === '' || $columnName === '' || rxTableColumnExists($connection, $tableName, $columnName)) {
            return;
        }
        try {
            $connection->query("ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` {$definition}");
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false && stripos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }
}

if (!function_exists('rxSafeModifyColumn')) {
    function rxSafeModifyColumn($connection, $tableName, $columnName, $definition, $expectedType, $expectedNullable, $expectedDefault, $expectedCollation = null)
    {
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        $columnName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);
        if ($tableName === '' || $columnName === '' || !rxTableColumnExists($connection, $tableName, $columnName)) {
            return;
        }
        try {
            $safeColumn = method_exists($connection, 'real_escape_string') ? $connection->real_escape_string($columnName) : $columnName;
            $result = $connection->query("SHOW FULL COLUMNS FROM `{$tableName}` LIKE '{$safeColumn}'");
            $row = ($result && isset($result->num_rows) && $result->num_rows > 0) ? $result->fetch_assoc() : null;
            if (!is_array($row)) {
                return;
            }
            $typeMatches = strtolower((string) ($row['Type'] ?? '')) === strtolower((string) $expectedType);
            $nullableMatches = strtoupper((string) ($row['Null'] ?? '')) === ($expectedNullable ? 'YES' : 'NO');
            $actualDefault = array_key_exists('Default', $row) ? $row['Default'] : null;
            $defaultMatches = $expectedDefault === null
                ? $actualDefault === null
                : $actualDefault !== null && (string) $actualDefault === (string) $expectedDefault;
            $collationMatches = $expectedCollation === null
                || strtolower((string) ($row['Collation'] ?? '')) === strtolower((string) $expectedCollation);
            if ($typeMatches && $nullableMatches && $defaultMatches && $collationMatches) {
                return;
            }
            $connection->query("ALTER TABLE `{$tableName}` MODIFY COLUMN `{$columnName}` {$definition}");
        } catch (Throwable $e) {
            error_log("[table.php] rxSafeModifyColumn {$tableName}.{$columnName}: " . $e->getMessage());
        }
    }
}

if (!function_exists('rxSafeAddIndex')) {
    function rxSafeAddIndex($connection, $tableName, $indexName, $columnName, $prefix = null)
    {
        $tableName  = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        $indexName  = preg_replace('/[^A-Za-z0-9_]/', '', (string) $indexName);
        $columnName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);
        if ($tableName === '' || $indexName === '' || $columnName === '') {
            return;
        }
        if (!rxTableColumnExists($connection, $tableName, $columnName)) {
            return;
        }
        try {
            $safeIndex = method_exists($connection, 'real_escape_string') ? $connection->real_escape_string($indexName) : $indexName;
            $existing = $connection->query("SHOW INDEX FROM `{$tableName}` WHERE Key_name = '{$safeIndex}'");
            if ($existing && isset($existing->num_rows) && $existing->num_rows > 0) {
                return;
            }
            $prefix = ($prefix !== null) ? (int) $prefix : 0;
            $colSpec = $prefix > 0 ? "`{$columnName}`({$prefix})" : "`{$columnName}`";
            $connection->query("ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` ({$colSpec})");
        } catch (Throwable $e) {
            error_log("[table.php] rxSafeAddIndex {$tableName}.{$indexName}: " . $e->getMessage());
        }
    }
}

if (!function_exists('rxSafeAddUniqueIndex')) {
    function rxSafeAddUniqueIndex($connection, $tableName, $indexName, array $columnNames, array $columnPrefixes = [])
    {
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        $indexName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $indexName);
        $columnNames = array_map(function ($columnName) {
            return preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);
        }, $columnNames);
        if ($tableName === '' || $indexName === '' || empty($columnNames)) {
            return;
        }
        foreach ($columnNames as $columnName) {
            if ($columnName === '' || !rxTableColumnExists($connection, $tableName, $columnName)) {
                return;
            }
        }
        try {
            $safeIndex = method_exists($connection, 'real_escape_string') ? $connection->real_escape_string($indexName) : $indexName;
            $existing = $connection->query("SHOW INDEX FROM `{$tableName}` WHERE Key_name = '{$safeIndex}'");
            if ($existing && isset($existing->num_rows) && $existing->num_rows > 0) {
                return;
            }
            $colSpec = implode(', ', array_map(function ($columnName) use ($columnPrefixes) {
                $prefix = isset($columnPrefixes[$columnName]) ? (int) $columnPrefixes[$columnName] : 0;
                return $prefix > 0 ? "`{$columnName}`({$prefix})" : "`{$columnName}`";
            }, $columnNames));
            $connection->query("ALTER TABLE `{$tableName}` ADD UNIQUE `{$indexName}` ({$colSpec})");
        } catch (Throwable $e) {
            error_log("[table.php] rxSafeAddUniqueIndex {$tableName}.{$indexName}: " . $e->getMessage());
        }
    }
}

if (!function_exists('rxShrinkMarzbanPanelRow')) {
    function rxShrinkMarzbanPanelRow($connection)
    {
        $columns = [
            'remna_api_token', 'xui_api_token',
            'priceextravolume', 'priceextratime', 'pricecustomvolume', 'pricecustomtime',
            'mainvolume', 'maxvolume', 'maintime', 'maxtime',
        ];
        try {
            $modifications = [];
            foreach ($columns as $column) {
                $safeColumn = method_exists($connection, 'real_escape_string') ? $connection->real_escape_string($column) : $column;
                $result = $connection->query("SHOW COLUMNS FROM `marzban_panel` LIKE '{$safeColumn}'");
                if ($result && isset($result->num_rows) && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $type = strtolower((string) ($row['Type'] ?? ''));
                    if ($type !== '' && strpos($type, 'text') === false) {
                        $modifications[] = "MODIFY `{$column}` TEXT NULL";
                    }
                }
            }
            if (!empty($modifications)) {
                $connection->query("ALTER TABLE `marzban_panel` " . implode(", ", $modifications));
            }
        } catch (Throwable $e) {
            error_log('[table.php] shrink marzban_panel row: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensureMarzbanGuardFieldsMigrated')) {
    function ensureMarzbanGuardFieldsMigrated()
    {
        static $guardFieldsChecked = false;
        if ($guardFieldsChecked === true) {
            return;
        }
        $guardFieldsChecked = true;

        $guardColumns = [
            ['api_key', null, "VARCHAR(500)"],
            ['guard_service_ids', null, "TEXT"],
            ['guard_note', null, "TEXT"],
            ['guard_auto_delete_days', 0, "INT(11)"],
            ['guard_auto_renewals', null, "TEXT"],
            ['hide_user', null, "TEXT"],
            ['guard_version', 'v1', "VARCHAR(10)"],
        ];

        foreach ($guardColumns as [$field, $default, $datatype]) {
            addFieldToTable("marzban_panel", $field, $default, $datatype);
        }
    }
}

if (!function_exists('ensureRebeccaPanelFieldsMigrated')) {
    function ensureRebeccaPanelFieldsMigrated()
    {
        static $rebeccaFieldsChecked = false;
        if ($rebeccaFieldsChecked === true) {
            return;
        }
        $rebeccaFieldsChecked = true;

        $rebeccaColumns = [
            ['api_key', null, "VARCHAR(500)"],
            ['rebecca_service_id', null, "VARCHAR(100)"],
        ];

        foreach ($rebeccaColumns as [$field, $default, $datatype]) {
            addFieldToTable("marzban_panel", $field, $default, $datatype);
        }
    }
}

try {

    $tableName = 'user';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id VARCHAR(500) PRIMARY KEY,
            limit_usertest INT NOT NULL DEFAULT 1,
            roll_Status BOOL NOT NULL DEFAULT 0,
            username VARCHAR(500) NOT NULL DEFAULT '',
            Processing_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            Processing_value_one TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            Processing_value_tow TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            Processing_value_four TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            step VARCHAR(500) NOT NULL DEFAULT 'home',
            description_blocking TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            number VARCHAR(300) NOT NULL DEFAULT 'none',
            Balance INT NOT NULL DEFAULT 0,
            User_Status VARCHAR(500) NOT NULL DEFAULT 'Active',
            pagenumber INT NOT NULL DEFAULT 0,
            message_count VARCHAR(100) NOT NULL DEFAULT '0',
            last_message_time VARCHAR(100) NOT NULL DEFAULT '0',
            agent VARCHAR(100) NOT NULL DEFAULT 'f',
            affiliatescount VARCHAR(100) NOT NULL DEFAULT '0',
            affiliates VARCHAR(100) NOT NULL DEFAULT '0',
            namecustom VARCHAR(300) NOT NULL DEFAULT 'none',
            number_username VARCHAR(300) NOT NULL DEFAULT '100',
            register VARCHAR(100) NOT NULL DEFAULT 'none',
            verify VARCHAR(100) NOT NULL DEFAULT '1',
            cardpayment VARCHAR(100) NOT NULL DEFAULT '1',
            codeInvitation VARCHAR(100) NULL,
            pricediscount VARCHAR(100) NULL   DEFAULT '0',
            hide_mini_app_instruction VARCHAR(20) NULL   DEFAULT '0',
            maxbuyagent VARCHAR(100) NULL   DEFAULT '0',
            joinchannel VARCHAR(100) NULL   DEFAULT '0',
            checkstatus VARCHAR(50) NULL   DEFAULT '0',
            bottype TEXT NULL ,
            score INT NULL DEFAULT '0',
            limitchangeloc VARCHAR(50) NULL   DEFAULT '0',
            status_cron VARCHAR(20)  NULL DEFAULT '1',
            expire VARCHAR(100) NULL ,
            token VARCHAR(100) NULL,
            auth_exempt VARCHAR(20) NULL DEFAULT '0',
            antispam_window_start VARCHAR(20) NULL DEFAULT '0',
            antispam_window_count VARCHAR(20) NULL DEFAULT '0',
            antispam_muted_until VARCHAR(20) NULL DEFAULT '0',
            step_stack TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    }
    {
        addFieldToTable($tableName, 'token', null, "VARCHAR(100)");
        addFieldToTable($tableName, 'status_cron', "1", "VARCHAR(20)");
        addFieldToTable($tableName, 'expire', NULL, "VARCHAR(100)");
        addFieldToTable($tableName, 'limitchangeloc', '0', "VARCHAR(50)");
        addFieldToTable($tableName, 'bottype', '0', "TEXT");
        addFieldToTable($tableName, 'score', '0', "INT");
        addFieldToTable($tableName, 'checkstatus', '0', "VARCHAR(50)");
        addFieldToTable($tableName, 'joinchannel', '0', "VARCHAR(100)");
        addFieldToTable($tableName, 'maxbuyagent', '0');
        addFieldToTable($tableName, 'agent', 'f');
        addFieldToTable($tableName, 'verify', '1');
        addFieldToTable($tableName, 'register', 'none');
        addFieldToTable($tableName, 'auth_exempt', '0', "varchar(20)");
        addFieldToTable($tableName, 'namecustom', 'none');
        addFieldToTable($tableName, 'number_username', '100');
        addFieldToTable($tableName, 'cardpayment', '1');
        addFieldToTable($tableName, 'card_verify_bypass', '0', 'TINYINT(1)');
        addFieldToTable($tableName, 'affiliatescount', '0');
        addFieldToTable($tableName, 'affiliates', '0');
        addFieldToTable($tableName, 'message_count', '0');
        addFieldToTable($tableName, 'last_message_time', '0');
        addFieldToTable($tableName, 'Processing_value_four', '');
        addFieldToTable($tableName, 'username', 'none');
        addFieldToTable($tableName, 'Processing_value', 'none');
        addFieldToTable($tableName, 'number', 'none');
        addFieldToTable($tableName, 'pagenumber', '');
        addFieldToTable($tableName, 'codeInvitation', null);
        addFieldToTable($tableName, 'pricediscount', "0");
        addFieldToTable($tableName, 'hide_mini_app_instruction', '0', "VARCHAR(20)");
        addFieldToTable($tableName, 'Processing_value_price', null, "VARCHAR(100)");
        addFieldToTable($tableName, 'last_seen_notification_id', null, "INT");
        rxSafeModifyColumn($connect, $tableName, 'limit_usertest', 'INT NOT NULL DEFAULT 1', 'int', false, '1');
        rxSafeModifyColumn($connect, $tableName, 'roll_Status', 'BOOL NOT NULL DEFAULT 0', 'tinyint(1)', false, '0');
        rxSafeModifyColumn($connect, $tableName, 'username', "VARCHAR(500) NOT NULL DEFAULT ''", 'varchar(500)', false, '', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'Processing_value', 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL', 'text', true, null, 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'Processing_value_one', 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL', 'text', true, null, 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'Processing_value_tow', 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL', 'text', true, null, 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'Processing_value_four', 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL', 'text', true, null, 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'step', "VARCHAR(500) NOT NULL DEFAULT 'home'", 'varchar(500)', false, 'home', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'number', "VARCHAR(300) NOT NULL DEFAULT 'none'", 'varchar(300)', false, 'none', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'Balance', 'INT NOT NULL DEFAULT 0', 'int', false, '0');
        rxSafeModifyColumn($connect, $tableName, 'User_Status', "VARCHAR(500) NOT NULL DEFAULT 'Active'", 'varchar(500)', false, 'Active', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'pagenumber', 'INT NOT NULL DEFAULT 0', 'int', false, '0');
        rxSafeModifyColumn($connect, $tableName, 'message_count', "VARCHAR(100) NOT NULL DEFAULT '0'", 'varchar(100)', false, '0', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'last_message_time', "VARCHAR(100) NOT NULL DEFAULT '0'", 'varchar(100)', false, '0', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'agent', "VARCHAR(100) NOT NULL DEFAULT 'f'", 'varchar(100)', false, 'f', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'affiliatescount', "VARCHAR(100) NOT NULL DEFAULT '0'", 'varchar(100)', false, '0', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'affiliates', "VARCHAR(100) NOT NULL DEFAULT '0'", 'varchar(100)', false, '0', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'namecustom', "VARCHAR(300) NOT NULL DEFAULT 'none'", 'varchar(300)', false, 'none', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'number_username', "VARCHAR(300) NOT NULL DEFAULT '100'", 'varchar(300)', false, '100', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'register', "VARCHAR(100) NOT NULL DEFAULT 'none'", 'varchar(100)', false, 'none', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'verify', "VARCHAR(100) NOT NULL DEFAULT '1'", 'varchar(100)', false, '1', 'utf8mb4_unicode_ci');
        rxSafeModifyColumn($connect, $tableName, 'cardpayment', "VARCHAR(100) NOT NULL DEFAULT '1'", 'varchar(100)', false, '1', 'utf8mb4_unicode_ci');


        addFieldToTable($tableName, 'antispam_window_start', '0', "VARCHAR(20)");
        addFieldToTable($tableName, 'antispam_window_count', '0', "VARCHAR(20)");
        addFieldToTable($tableName, 'antispam_muted_until', '0', "VARCHAR(20)");
        addFieldToTable($tableName, 'step_stack', '[]', "TEXT");
        addFieldToTable($tableName, 'nav_state', 'home', "VARCHAR(64)");
        addFieldToTable($tableName, 'reply_kb_cleared', '0', "VARCHAR(1)");
        addFieldToTable($tableName, 'reply_kb_cleanup_msg_id', '0', "VARCHAR(20)");
    }
} catch (PDOException $e) {
    error_log('[panels] ' . $e->getMessage());
}


try {

    $tableName = 'help';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name_os varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        Media_os varchar(5000) NOT NULL,
        type_Media_os varchar(500) NOT NULL,
        category TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        Description_os TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        app_title varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        app_link TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    } else {
        addFieldToTable("help", "category", null, "TEXT");
        addFieldToTable("help", "app_title", null, "VARCHAR(255)");
        addFieldToTable("help", "app_link", null, "TEXT");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $tableName = 'setting';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $DATAAWARD = json_encode(array(
        'one' => "0",
        "tow" => "0",
        "theree" => "0"
    ));
    $limitlist = json_encode(array(
        'free' => 100,
        'all' => 100,
    ));
    $status_cron = json_encode(array(
        'day' => true,
        'volume' => true,
        'remove' => false,
        'remove_volume' => false,
        'test' => false,
        'on_hold' => false,
        'uptime_node' => false,
        'uptime_panel' => false,
    ));
    $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
        Bot_Status varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        roll_Status varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        get_number varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        iran_number varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        NotUser varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        Channel_Report varchar(600)  NULL,
        PublicLog_Channel varchar(600)  NULL,
        PublicLog_Status varchar(20)  NULL,
        PublicLog_NewSub varchar(20)  NULL,
        PublicLog_Renewal varchar(20)  NULL,
        PublicLog_VolumeTopup varchar(20)  NULL,
        PublicLog_TimeExtra varchar(20)  NULL,
        PublicLog_WalletDeposit varchar(20)  NULL,
        limit_usertest_all varchar(600)  NULL,
        affiliatesstatus varchar(600)  NULL,
        affiliatespercentage varchar(600)  NULL,
        removedayc varchar(600)  NULL,
        showcard varchar(200)  NULL,
        numbercount varchar(600)  NULL,
        statusnewuser varchar(600)  NULL,
        statusagentrequest varchar(600)  NULL,
        statuscategory varchar(200)  NULL,
        statusterffh varchar(200)  NULL,
        volumewarn varchar(200)  NULL,
        inlinebtnmain varchar(200)  NULL,
        verifystart varchar(200)  NULL,
        id_support varchar(200)  NULL,
        statusnamecustom varchar(100)  NULL,
        statuscategorygenral varchar(100)  NULL,
        statussupportpv varchar(100)  NULL,
        agentreqprice varchar(100)  NULL,
        bulkbuy varchar(100)  NULL,
        on_hold_day varchar(100)  NULL,
        cronvolumere varchar(100)  NULL,
        verifybucodeuser varchar(100)  NULL,
        scorestatus varchar(100)  NULL,
        Lottery_prize TEXT  NULL,
        wheelـluck varchar(45)  NULL,
        wheelـluck_price varchar(45)  NULL,
        btn_status_extned varchar(45)  NULL,
        daywarn varchar(45)  NULL,
        categoryhelp varchar(45)  NULL,
        linkappstatus varchar(45)  NULL,
        iplogin TEXT  NULL,
        wheelagent varchar(45)  NULL,
        Lotteryagent varchar(45)  NULL,
        languageen varchar(45)  NULL,
        languageru varchar(45)  NULL,
        statusfirstwheel varchar(45)  NULL,
        statuslimitchangeloc varchar(45)  NULL,
        Debtsettlement varchar(45)  NULL,
        Dice varchar(45) NULL,
        keyboardmain TEXT NOT NULL,
        statusnoteforf varchar(45) NOT NULL,
        statuscopycart varchar(45) NOT NULL,
        timeauto_not_verify varchar(20) NOT NULL,
        status_keyboard_config varchar(20)  NULL,
        cron_status TEXT NOT NULL,
        limitnumber varchar(200)  NULL,
        auth_scope varchar(20) NULL DEFAULT 'all',
        antispam_status varchar(20) NULL DEFAULT '0',
        antispam_msg_count varchar(20) NULL DEFAULT '5',
        antispam_seconds varchar(20) NULL DEFAULT '3',
        antispam_mute_seconds varchar(20) NULL DEFAULT '5',
        proxy_telegram_status varchar(20) NULL DEFAULT '0',
        proxy_telegram_url varchar(500) NULL DEFAULT '',
        proxy_panel_status varchar(20) NULL DEFAULT '0',
        proxy_panel_url varchar(500) NULL DEFAULT '',
        premium_emoji_status varchar(20) NULL DEFAULT '0',
        keyboard_styles_all TEXT NULL,
        webhook_secret_token varchar(64) NULL DEFAULT '',
        forced_miniapp_mode varchar(20) NULL DEFAULT '0',
        miniapp_ticket_mode varchar(20) NULL DEFAULT '0',
        card_verify_status varchar(20) NULL DEFAULT 'offcardverify',
        card_verify_scope varchar(20) NULL DEFAULT 'all',
        card_verify_min_amount varchar(20) NULL DEFAULT '0',
        receipt_topic_reporting varchar(20) NULL DEFAULT '1',
        subscription_link_button varchar(20) NULL DEFAULT '1',
        redis_enabled varchar(20) NULL DEFAULT '0',
        banner_start_status varchar(20) NULL DEFAULT '0',
        banner_start_file_id varchar(255) NULL DEFAULT '',
        banner_cart_status varchar(20) NULL DEFAULT '0',
        banner_cart_file_id varchar(255) NULL DEFAULT '',
        banner_buy_status varchar(20) NULL DEFAULT '0',
        banner_buy_file_id varchar(255) NULL DEFAULT '')
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
        $stmt = $pdo->prepare("INSERT INTO setting (Bot_Status,roll_Status,get_number,limit_usertest_all,iran_number,NotUser,affiliatesstatus,affiliatespercentage,removedayc,showcard,statuscategory,numbercount,statusnewuser,statusagentrequest,volumewarn,inlinebtnmain,verifystart,statussupportpv,statusnamecustom,statuscategorygenral,agentreqprice,cronvolumere,bulkbuy,on_hold_day,verifybucodeuser,scorestatus,Lottery_prize,wheelـluck,wheelـluck_price,iplogin,daywarn,categoryhelp,linkappstatus,languageen,languageru,wheelagent,Lotteryagent,statusfirstwheel,statuslimitchangeloc,limitnumber,Debtsettlement,Dice,keyboardmain,statusnoteforf,statuscopycart,timeauto_not_verify,status_keyboard_config,cron_status) VALUES ('botstatuson','rolleon','offAuthenticationphone','1','offAuthenticationiran','offnotuser','offaffiliates','0','0','1','offcategory','0','onnewuser','onrequestagent','2','offinline','offverify','offpvsupport','offnamecustom','offcategorys','0','5','onbulk','4','offverify','0','$DATAAWARD','0','0','0','2','0','0','0','0','1','1','0','0','$limitlist','1','0','$keyboardmain','1','0','4','1','$status_cron')");
        $stmt->execute();
    } else {
        addFieldToTable("setting", "cron_status", $status_cron, "TEXT");
        addFieldToTable("setting", "status_keyboard_config", "1", "varchar(20)");
        addFieldToTable("setting", "statusnoteforf", "1", "varchar(20)");
        addFieldToTable("setting", "timeauto_not_verify", "4", "varchar(20)");
        addFieldToTable("setting", "statuscopycart", "0", "varchar(20)");
        addFieldToTable("setting", "keyboardmain", $keyboardmain, "TEXT");
        addFieldToTable("setting", "Dice", '0', "varchar(45)");
        addFieldToTable("setting", "Debtsettlement", '1', "varchar(45)");
        addFieldToTable("setting", "limitnumber", $limitlist, "varchar(200)");
        addFieldToTable("setting", "statuslimitchangeloc", "0", "varchar(45)");
        addFieldToTable("setting", "statusfirstwheel", "0", "varchar(45)");
        addFieldToTable("setting", "Lotteryagent", "1", "varchar(45)");
        addFieldToTable("setting", "wheelagent", "1", "varchar(45)");
        addFieldToTable("setting", "languageru", "0", "varchar(45)");
        addFieldToTable("setting", "languageen", "0", "varchar(45)");
        addFieldToTable("setting", "linkappstatus", "0", "varchar(45)");
        addFieldToTable("setting", "categoryhelp", "0", "varchar(45)");
        addFieldToTable("setting", "daywarn", "2", "varchar(45)");
        addFieldToTable("setting", "btn_status_extned", "0", "varchar(45)");
        addFieldToTable("setting", "auth_scope", "all", "varchar(20)");
        addFieldToTable("setting", "iplogin", "[]", "TEXT");
        $stmt_ip = $pdo->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'setting' AND COLUMN_NAME = 'iplogin'");
        $stmt_ip->execute();
        $ip_col_type = $stmt_ip->fetchColumn();
        if ($ip_col_type && strtolower($ip_col_type) !== 'text' && strtolower($ip_col_type) !== 'longtext' && strtolower($ip_col_type) !== 'mediumtext') {
            $pdo->exec("ALTER TABLE setting MODIFY COLUMN iplogin TEXT NULL");
            $stmt_migrate = $pdo->query("SELECT iplogin FROM setting LIMIT 1");
            $cur_val = $stmt_migrate ? $stmt_migrate->fetchColumn() : null;
            if ($cur_val !== null && $cur_val !== '' && $cur_val !== '0' && $cur_val !== '[]') {
                $decoded_check = json_decode($cur_val, true);
                if (!is_array($decoded_check) && filter_var($cur_val, FILTER_VALIDATE_IP)) {
                    $new_val = json_encode([$cur_val]);
                    $pdo->prepare("UPDATE setting SET iplogin = ?")->execute([$new_val]);
                }
            }
            echo "iplogin column migrated to TEXT ✅<br>";
        }
        addFieldToTable("setting", "wheelـluck_price", "0", "varchar(45)");
        addFieldToTable("setting", "wheelـluck", "0", "varchar(45)");
        addFieldToTable("setting", "Lottery_prize", $DATAAWARD, "TEXT");
        addFieldToTable("setting", "scorestatus", "0", "VARCHAR(100)");
        addFieldToTable("setting", "verifybucodeuser", "offverify", "VARCHAR(100)");
        addFieldToTable("setting", "on_hold_day", "4", "VARCHAR(100)");
        addFieldToTable("setting", "bulkbuy", "onbulk", "VARCHAR(100)");
        addFieldToTable("setting", "statuscategorygenral", "offcategorys", "VARCHAR(100)");
        addFieldToTable("setting", "cronvolumere", "5", "VARCHAR(100)");
        addFieldToTable("setting", "agentreqprice", "0", "VARCHAR(100)");
        addFieldToTable("setting", "statusnamecustom", "offnamecustom", "VARCHAR(100)");
        addFieldToTable("setting", "id_support", "0", "VARCHAR(100)");
        addFieldToTable("setting", "statussupportpv", "offpvsupport", "VARCHAR(100)");
        addFieldToTable("setting", "affiliatespercentage", "0", "VARCHAR(600)");
        addFieldToTable("setting", "inlinebtnmain", "offinline", "VARCHAR(200)");
        addFieldToTable("setting", "auto_remove_reply_keyboard", "on", "VARCHAR(20)");
        addFieldToTable("setting", "volumewarn", "2", "VARCHAR(200)");
        addFieldToTable("setting", "statusagentrequest", "onrequestagent", "VARCHAR(600)");
        addFieldToTable("setting", "statusnewuser", "onnewuser", "VARCHAR(600)");
        addFieldToTable("setting", "numbercount", "0", "VARCHAR(600)");
        addFieldToTable("setting", "statuscategory", "offcategory", "VARCHAR(600)");
        addFieldToTable("setting", "showcard", "1", "VARCHAR(200)");
        addFieldToTable("setting", "removedayc", "1", "VARCHAR(200)");
        addFieldToTable("setting", "affiliatesstatus", "offaffiliates", "VARCHAR(600)");
        addFieldToTable("setting", "NotUser", "offnotuser", "VARCHAR(200)");
        addFieldToTable("setting", "iran_number", "offAuthenticationiran", "VARCHAR(200)");
        addFieldToTable("setting", "get_number", "onAuthenticationphone", "VARCHAR(200)");
        addFieldToTable("setting", "limit_usertest_all", "1", "VARCHAR(200)");
        addFieldToTable("setting", "Channel_Report", "0", "VARCHAR(200)");
        addFieldToTable("setting", "Bot_Status", "botstatuson", "VARCHAR(200)");
        addFieldToTable("setting", "roll_Status", "rolleon", "VARCHAR(200)");
        addFieldToTable("setting", "verifystart", "offverify", "VARCHAR(200)");

        addFieldToTable("setting", "premium_emoji_status", "0", "VARCHAR(20)");
        addFieldToTable("setting", "keyboard_styles_all", "{}", "TEXT");


        addFieldToTable("setting", "antispam_status", "0", "VARCHAR(20)");
        addFieldToTable("setting", "antispam_msg_count", "5", "VARCHAR(20)");
        addFieldToTable("setting", "antispam_seconds", "3", "VARCHAR(20)");
        addFieldToTable("setting", "antispam_mute_seconds", "5", "VARCHAR(20)");

        addFieldToTable("setting", "proxy_telegram_status", "0", "VARCHAR(20)");
        addFieldToTable("setting", "proxy_telegram_url", "", "VARCHAR(500)");
        addFieldToTable("setting", "proxy_panel_status", "0", "VARCHAR(20)");
        addFieldToTable("setting", "proxy_panel_url", "", "VARCHAR(500)");
        addFieldToTable("setting", "card_verify_status", "offcardverify", "VARCHAR(20)");
        addFieldToTable("setting", "card_verify_scope", "all", "VARCHAR(20)");
        addFieldToTable("setting", "card_verify_min_amount", "0", "VARCHAR(20)");
        addFieldToTable("setting", "receipt_topic_reporting", "1", "VARCHAR(20)");
        addFieldToTable("setting", "subscription_link_button", "1", "VARCHAR(20)");
        addFieldToTable("setting", "PublicLog_Channel", "", "VARCHAR(600)");
        addFieldToTable("setting", "PublicLog_Status", "0", "VARCHAR(20)");
        addFieldToTable("setting", "PublicLog_NewSub", "1", "VARCHAR(20)");
        addFieldToTable("setting", "PublicLog_Renewal", "1", "VARCHAR(20)");
        addFieldToTable("setting", "PublicLog_VolumeTopup", "1", "VARCHAR(20)");
        addFieldToTable("setting", "PublicLog_TimeExtra", "1", "VARCHAR(20)");
        addFieldToTable("setting", "PublicLog_WalletDeposit", "0", "VARCHAR(20)");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}


try {
    $tableName = 'admin';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
        id_admin varchar(500) PRIMARY KEY NOT NULL,
        username varchar(1000) NOT NULL,
        password varchar(1000) NOT NULL,
        password_hash varchar(255) NULL,
        iplogin varchar(1000) NULL,
        rule varchar(500) NOT NULL,
        last_ticket_seen INT(11) NULL DEFAULT 0)");
        $stmt->execute();
        $randomString = bin2hex(random_bytes(5));


        $stmt = $pdo->prepare("INSERT INTO admin (id_admin, rule, username, password, password_hash) VALUES (:id, :rule, :username, :password, :password_hash)");
        $stmt->execute([
            ':id'            => (string) $adminnumber,
            ':rule'          => 'administrator',
            ':username'      => 'admin',
            ':password'      => $randomString,
            ':password_hash' => password_hash($randomString, PASSWORD_DEFAULT),
        ]);
    } else {
        addFieldToTable("admin", "rule", "administrator", "VARCHAR(200)");
        addFieldToTable("admin", "username", null, "VARCHAR(200)");
        addFieldToTable("admin", "password", null, "VARCHAR(200)");
        addFieldToTable("admin", "password_hash", null, "VARCHAR(255)");
        addFieldToTable("admin", "iplogin", null, "VARCHAR(1000)");
        addFieldToTable("admin", "last_ticket_seen", "0", "INT(11)");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $tableName = 'channels';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            remark varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            linkjoin varchar(200) NOT NULL,
            link varchar(200) NOT NULL)
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    } else {
        if (!rxTableColumnExists($connect, "channels", "id")) {
            try {
                $connect->query("ALTER TABLE `channels` ADD `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
            } catch (Throwable $e) {
                try {
                    $pdo->exec("ALTER TABLE channels ADD id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
                } catch (Throwable $e2) {
                    error_log('[table.php] add id to channels: ' . $e2->getMessage());
                }
            }
        }
        addFieldToTable("channels", "remark", null, "VARCHAR(200)");
        addFieldToTable("channels", "linkjoin", null, "VARCHAR(200)");
        addFieldToTable("channels", "link", null, "VARCHAR(200)");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'marzban_panel'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE marzban_panel (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code_panel varchar(200) NULL,
        name_panel varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        status varchar(500) NULL,
        url_panel varchar(2000) NULL,
        username_panel varchar(200) NULL,
        password_panel varchar(200) NULL,
        api_key varchar(500) NULL,
        pasarguard_auth_mode varchar(20) NOT NULL DEFAULT 'api_key',
        xui_api_token TEXT NULL,
        xui_api_mode varchar(20) NOT NULL DEFAULT 'legacy',
        xui_monitor_state TEXT NULL,
        ip_limit_guard varchar(20) NULL DEFAULT 'offipguard',
        agent varchar(200) NULL,
        sublink varchar(500) NULL,
        config varchar(500) NULL,
        MethodUsername varchar(700) NULL,
        TestAccount varchar(100) NULL,
        limit_panel varchar(100) NULL,
        namecustom varchar(100) NULL,
        Methodextend varchar(100) NULL,
        conecton varchar(100) NULL,
        linksubx varchar(1000) NULL,
        inboundid varchar(100) NULL,
        type varchar(100) NULL,
        inboundstatus varchar(100) NULL,
        hosts  JSON  NULL,
        inbound_deactive varchar(100) NULL,
        time_usertest varchar(100) NULL,
        val_usertest varchar(100)  NULL,
        secret_code varchar(200) NULL,
        priceChangeloc varchar(200) NULL,
        priceextravolume TEXT NULL,
        pricecustomvolume TEXT NULL,
        pricecustomtime TEXT NULL,
        priceextratime TEXT NULL,
        mainvolume TEXT NULL,
        maxvolume TEXT NULL,
        maintime TEXT NULL,
        maxtime TEXT NULL,
        status_extend varchar(100) NULL,
        datelogin TEXT NULL,
        proxies TEXT NULL,
        inbounds TEXT NULL,
        subvip varchar(60) NULL,
        changeloc varchar(60) NULL,
        on_hold_test varchar(60) NOT NULL,
        version_panel varchar(60) NOT NULL DEFAULT '0',
        customvolume TEXT NULL,
        hide_user TEXT NULL,
        guard_service_ids TEXT NULL,
        guard_note TEXT NULL,
        guard_auto_delete_days INT(11) NULL,
        guard_auto_renewals TEXT NULL,
        remna_api_token TEXT NULL,
        remna_squad varchar(200) NULL,
        shop_features TEXT NULL,
        remna_revoke varchar(20) NULL DEFAULT 'off',
        remna_server_picker varchar(20) NULL DEFAULT 'off',
        remna_usage_btn varchar(20) NULL DEFAULT 'on',
        rebecca_service_id varchar(100) NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table marzban_panel: " . mysqli_error($connect));
        }
    } else {
        rxShrinkMarzbanPanelRow($connect);
        rxSafeAddColumn($connect, "marzban_panel", "shop_features", "TEXT NULL");
        $VALUE = json_encode(array(
            'f' => '0',
            'n' => '0',
            'n2' => '0'
        ));
        $valueprice = json_encode(array(
            'f' => "4000",
            'n' => "4000",
            'n2' => "4000"
        ));
        $valuemain = json_encode(array(
            'f' => "1",
            'n' => "1",
            'n2' => "1"
        ));
        $valuemax = json_encode(array(
            'f' => "1000",
            'n' => "1000",
            'n2' => "1000"
        ));
        $valuemax_time = json_encode(array(
            'f' => "365",
            'n' => "365",
            'n2' => "365"
        ));
        addFieldToTable("marzban_panel", "on_hold_test", "1", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "version_panel", "0", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "proxies", null, "TEXT");
        addFieldToTable("marzban_panel", "inbounds", null, "TEXT");
        addFieldToTable("marzban_panel", "api_key", null, "VARCHAR(500)");
        $pasarguardAuthModeColumnExisted = rxTableColumnExists($connect, "marzban_panel", "pasarguard_auth_mode");
        addFieldToTable("marzban_panel", "pasarguard_auth_mode", "api_key", "VARCHAR(20)");
        if (!$pasarguardAuthModeColumnExisted) {
            $connect->query("UPDATE marzban_panel SET pasarguard_auth_mode = 'api_key' WHERE type = 'pasarguard'");
        }
        addFieldToTable("marzban_panel", "xui_api_token", null, "VARCHAR(1000)");
        $xuiApiModeColumnExisted = rxTableColumnExists($connect, "marzban_panel", "xui_api_mode");
        addFieldToTable("marzban_panel", "xui_api_mode", "legacy", "VARCHAR(20)");
        if (!$xuiApiModeColumnExisted) {
            $connect->query("UPDATE marzban_panel SET xui_api_mode = 'token' WHERE type = 'x-ui_single' AND xui_api_token IS NOT NULL AND xui_api_token <> '' AND xui_api_mode = 'legacy'");
        }
        addFieldToTable("marzban_panel", "xui_monitor_state", null, "TEXT");
        addFieldToTable("marzban_panel", "ip_limit_guard", "offipguard", "VARCHAR(20)");
        rxSafeModifyColumn($connect, "marzban_panel", "ip_limit_guard", "VARCHAR(20) NULL DEFAULT 'offipguard'", "varchar(20)", true, "offipguard", "utf8mb4_unicode_ci");
        addFieldToTable("marzban_panel", "customvolume", $VALUE, "TEXT");
        addFieldToTable("marzban_panel", "subvip", "offsubvip", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "changeloc", "offchangeloc", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "hide_user", null, "TEXT");
        addFieldToTable("marzban_panel", "guard_service_ids", null, "TEXT");
        addFieldToTable("marzban_panel", "guard_note", null, "TEXT");
        addFieldToTable("marzban_panel", "guard_auto_delete_days", 0, "INT(11)");
        addFieldToTable("marzban_panel", "guard_auto_renewals", null, "TEXT");
        addFieldToTable("marzban_panel", "guard_version", 'v1', "VARCHAR(10)");
        addFieldToTable("marzban_panel", "status_extend", "on_extend", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "code_panel", null, "VARCHAR(50)");
        addFieldToTable("marzban_panel", "priceextravolume", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "pricecustomvolume", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "pricecustomtime", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "priceextratime", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "priceChangeloc", "0", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "mainvolume", $valuemain, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "maxvolume", $valuemax, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "maintime", $valuemain, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "maxtime", $valuemax_time, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "MethodUsername", "آیدی عددی + حروف و عدد رندوم", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "datelogin", null, "TEXT");
        addFieldToTable("marzban_panel", "val_usertest", "100", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "time_usertest", "1", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "secret_code", null, "VARCHAR(200)");
        addFieldToTable("marzban_panel", "inboundstatus", "offinbounddisable", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "inbound_deactive", "0", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "agent", "all", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "inboundid", "1", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "linksubx", null, "VARCHAR(200)");
        addFieldToTable("marzban_panel", "conecton", "offconecton", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "type", "marzban", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "Methodextend", "ریست حجم و زمان", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "namecustom", "vpn", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "limit_panel", "unlimted", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "TestAccount", "ONTestAccount", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "status", "active", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "sublink", "onsublink", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "config", "offconfig", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "national_net_status", "off_national_net", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "stock_source_panel", null, "VARCHAR(191)");
        addFieldToTable("marzban_panel", "remna_api_token", null, "VARCHAR(2000)");
        addFieldToTable("marzban_panel", "remna_squad", null, "VARCHAR(200)");
        addFieldToTable("marzban_panel", "remna_revoke", "off", "VARCHAR(20)");
        addFieldToTable("marzban_panel", "remna_server_picker", "off", "VARCHAR(20)");
        addFieldToTable("marzban_panel", "remna_usage_btn", "on", "VARCHAR(20)");
        addFieldToTable("marzban_panel", "rebecca_service_id", null, "VARCHAR(100)");
    }
    ensureMarzbanGuardFieldsMigrated();
    ensureRebeccaPanelFieldsMigrated();
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'remnawave_users'");
    $table_exists = ($result && $result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE remnawave_users (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(200) NULL,
        id_order varchar(200) NULL,
        username varchar(500) NULL,
        name_panel varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        panel_uuid varchar(200) NULL,
        short_uuid varchar(200) NULL,
        subscription_url varchar(2000) NULL,
        id_product varchar(200) NULL,
        status varchar(100) NULL DEFAULT 'active',
        created_at varchar(20) NULL,
        remna_tier varchar(40) NULL DEFAULT '',
        panel_user_id varchar(40) NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table remnawave_users: " . mysqli_error($connect));
        }
    } else {
        addFieldToTable("remnawave_users", "panel_uuid", null, "VARCHAR(200)");
        addFieldToTable("remnawave_users", "short_uuid", null, "VARCHAR(200)");
        addFieldToTable("remnawave_users", "subscription_url", null, "VARCHAR(2000)");
        addFieldToTable("remnawave_users", "name_panel", null, "VARCHAR(2000)");
        addFieldToTable("remnawave_users", "status", "active", "VARCHAR(100)");
        addFieldToTable("remnawave_users", "created_at", null, "VARCHAR(20)");
        addFieldToTable("remnawave_users", "remna_tier", "", "VARCHAR(40)");
        addFieldToTable("remnawave_users", "panel_user_id", null, "VARCHAR(40)");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'remnawave_nodes_cache'");
    $table_exists = ($result && $result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE remnawave_nodes_cache (
        name_panel varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY,
        cache_json LONGTEXT NULL,
        ts INT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table remnawave_nodes_cache: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'pinned_messages'");
    $table_exists = ($result && $result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE pinned_messages (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        chat_id varchar(200) NOT NULL,
        message_id varchar(100) NOT NULL,
        unpin_at INT(11) NULL,
        created_at INT(11) NOT NULL,
        INDEX idx_unpin_at (unpin_at))
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table pinned_messages: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'notification'");
    $table_exists = ($result && $result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE notification (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        message TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        duration_hours TINYINT UNSIGNED NOT NULL,
        created_at INT(11) NOT NULL,
        expires_at INT(11) NOT NULL,
        INDEX idx_expires_at (expires_at))
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table notification: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'product'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE product (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code_product varchar(200)  NULL,
        name_product varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price_product varchar(2000) NULL,
        Volume_constraint varchar(2000) NULL,
        Location varchar(500) NULL,
        Service_time varchar(200) NULL,
        agent varchar(100) NULL,
        note TEXT NULL,
        data_limit_reset varchar(200) NULL,
        one_buy_status varchar(20) NOT NULL,
        inbounds TEXT NULL,
        proxies TEXT NULL,
        category varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        position INT(11) NOT NULL DEFAULT 0,
        hide_panel TEXT  NOT NULL,
        ip_limit varchar(20) NOT NULL DEFAULT '0',
        hwid_limit varchar(20) NOT NULL DEFAULT '0',
        symbolic_limit_enabled varchar(20) NOT NULL DEFAULT '0',
        symbolic_limit_users varchar(20) NOT NULL DEFAULT '0')
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table product: " . mysqli_error($connect));
        }
    } else {
        addFieldToTable("product", "one_buy_status", "0", "VARCHAR(20)");
        addFieldToTable("product", "Location", null, "VARCHAR(200)");
        addFieldToTable("product", "inbounds", null, "TEXT");
        addFieldToTable("product", "proxies", null, "TEXT");
        addFieldToTable("product", "category", null, "varchar(200)");
        addFieldToTable("product", "note", '', "TEXT");
        addFieldToTable("product", "hide_panel", '{}', "TEXT");
        addFieldToTable("product", "data_limit_reset", "no_reset", "varchar(100)");
        addFieldToTable("product", "agent", "f", "varchar(50)");
        addFieldToTable("product", "code_product", null, "varchar(50)");
        addFieldToTable("product", "position", "0", "INT(11)");
        addFieldToTable("product", "ip_limit", "0", "VARCHAR(20)");
        addFieldToTable("product", "hwid_limit", "0", "VARCHAR(20)");
        addFieldToTable("product", "symbolic_limit_enabled", "0", "VARCHAR(20)");
        addFieldToTable("product", "symbolic_limit_users", "0", "VARCHAR(20)");
        $__prodLocCol = $connect->query("SHOW FULL COLUMNS FROM product LIKE 'Location'");
        $__prodLocRow = $__prodLocCol ? $__prodLocCol->fetch_assoc() : null;
        if ($__prodLocRow && strpos((string)($__prodLocRow['Type'] ?? ''), '500') === false) {
            $connect->query("ALTER TABLE product MODIFY Location VARCHAR(500) NULL");
        }
        $__prodCatCol = $connect->query("SHOW FULL COLUMNS FROM product LIKE 'category'");
        $__prodCatRow = $__prodCatCol ? $__prodCatCol->fetch_assoc() : null;
        if ($__prodCatRow && strpos((string)($__prodCatRow['Type'] ?? ''), '500') === false) {
            $connect->query("ALTER TABLE product MODIFY category VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'invoice'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE invoice (
        id_invoice varchar(200) PRIMARY KEY,
        id_user varchar(200) NULL,
        username varchar(300) NULL,
        Service_location varchar(300) NULL,
        time_sell VARCHAR(200) NULL,
        name_product varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price_product varchar(200) NULL,
        Volume varchar(200) NULL,
        Volume_unit varchar(10) NOT NULL DEFAULT 'GB',
        Service_time varchar(200) NULL,
        uuid TEXT NULL,
        note varchar(500) NULL,
        user_info TEXT NULL,
        bottype varchar(200) NULL,
        refral varchar(100) NULL,
        time_cron varchar(100) NULL,
        notifctions TEXT NOT NULL,
        Status varchar(200) NULL,
        ip_limit varchar(20) NOT NULL DEFAULT '0',
        hwid_limit varchar(20) NOT NULL DEFAULT '0',
        ip_last_seen TEXT NULL DEFAULT NULL,
        symbolic_limit_enabled varchar(20) NOT NULL DEFAULT '0',
        symbolic_limit_users varchar(20) NOT NULL DEFAULT '0',
        invalidated_at INT UNSIGNED NULL DEFAULT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table invoice: " . mysqli_error($connect));
        }
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'Volume_unit'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD Volume_unit VARCHAR(10) NOT NULL DEFAULT 'GB' AFTER Volume");
        }
        $connect->query("UPDATE invoice SET Volume_unit = 'MB' WHERE name_product = 'سرویس تست' AND (Volume_unit IS NULL OR Volume_unit <> 'MB')");
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'ip_limit'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD ip_limit VARCHAR(20) NOT NULL DEFAULT '0'");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'hwid_limit'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD hwid_limit VARCHAR(20) NOT NULL DEFAULT '0'");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'ip_last_seen'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD ip_last_seen TEXT NULL DEFAULT NULL");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'symbolic_limit_enabled'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD symbolic_limit_enabled VARCHAR(20) NOT NULL DEFAULT '0'");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'symbolic_limit_users'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD symbolic_limit_users VARCHAR(20) NOT NULL DEFAULT '0'");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'note'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD note VARCHAR(700)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'notifctions'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $data = json_encode(array(
                'volume' => false,
                'time' => false,
            ));
            $result = $connect->query("ALTER TABLE invoice ADD notifctions TEXT NOT NULL");
            $connect->query("UPDATE invoice SET notifctions = '$data'");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'time_cron'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD time_cron VARCHAR(100)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'refral'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD refral VARCHAR(100)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'bottype'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD bottype VARCHAR(200)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'user_info'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD user_info TEXT");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'time_sell'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD time_sell VARCHAR(200)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'uuid'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD uuid TEXT");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'Status'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD Status VARCHAR(100)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'invalidated_at'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD invalidated_at INT UNSIGNED NULL DEFAULT NULL");
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'Payment_report'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE Payment_report (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        id_order varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        time varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        at_updated varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        dec_not_confirmed TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        Payment_Method varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        payment_Status varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        bottype varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        message_id INT NULL,
        id_invoice varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        card_photo_file_id varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        card_last4 varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        report_chat_id varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        report_message_id INT NULL,
        report_thread_id INT NULL,
        private_receipt_targets TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        direct_payment_done TINYINT(1) NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table Payment_report: " . mysqli_error($connect));
        }
    } else {
        ensureTableUtf8mb4('Payment_report');
        addFieldToTable("Payment_report", "message_id", null, "INT");
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'Payment_Method'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD Payment_Method VARCHAR(200)");
            echo "The Payment_Method field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'bottype'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD bottype VARCHAR(300)");
            echo "The bottype field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'at_updated'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD at_updated VARCHAR(200)");
            echo "The at_updated field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'id_invoice'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD id_invoice VARCHAR(400)");
            $connect->query("UPDATE Payment_report SET id_invoice = 'none'");
            echo "The id_invoice field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'source'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD source VARCHAR(20) NULL");
            $connect->query("ALTER TABLE Payment_report ADD INDEX idx_paymentreport_source (source)");
            echo "The source field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'card_photo_file_id'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD card_photo_file_id VARCHAR(500) NULL");
            echo "The card_photo_file_id field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'card_last4'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD card_last4 VARCHAR(4) NULL");
            echo "The card_last4 field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'report_chat_id'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD report_chat_id VARCHAR(50) NULL");
            echo "The report_chat_id field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'report_message_id'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD report_message_id INT NULL");
            echo "The report_message_id field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'report_thread_id'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD report_thread_id INT NULL");
            echo "The report_thread_id field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'private_receipt_targets'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD private_receipt_targets TEXT NULL");
            echo "The private_receipt_targets field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'direct_payment_done'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD direct_payment_done TINYINT(1) NULL");
            echo "The direct_payment_done field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'Discount'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE Discount (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code varchar(2000) NULL,
        price varchar(200) NULL,
        limituse varchar(200) NULL,
        limitused varchar(200) NULL)
        ");
        if (!$result) {
            error_log("[table.php] table Discount: " . mysqli_error($connect));
        }
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM Discount LIKE 'limituse'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Discount ADD limituse VARCHAR(200)");
            echo "The limituse field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Discount LIKE 'limitused'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Discount ADD limitused VARCHAR(200)");
            echo "The limitused field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'Giftcodeconsumed'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE  Giftcodeconsumed (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code varchar(2000) NULL,
        id_user varchar(200) NULL)");
        if (!$result) {
            error_log("[table.php] table Giftcodeconsumed: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'textbot'");
    $table_exists = ($result->num_rows > 0);
    if ($table_exists) {
        try {
            $rxTbPkRes = $connect->query("SHOW KEYS FROM textbot WHERE Key_name = 'PRIMARY'");
            $rxTbHasPk = ($rxTbPkRes && $rxTbPkRes->num_rows > 0);
            if (!$rxTbHasPk) {
                $connect->query("DELETE t1 FROM textbot t1 JOIN textbot t2 ON t1.id_text = t2.id_text AND t1.text != t2.text WHERE (t1.text LIKE '💸 درگاه%' OR t1.text IS NULL OR t1.text = '') AND NOT (t2.text LIKE '💸 درگاه%' OR t2.text IS NULL OR t2.text = '')");
                $connect->query("ALTER TABLE textbot ADD COLUMN __rid INT AUTO_INCREMENT PRIMARY KEY");
                $connect->query("DELETE t1 FROM textbot t1 JOIN textbot t2 ON t1.id_text = t2.id_text AND t1.__rid > t2.__rid");
                $connect->query("ALTER TABLE textbot DROP COLUMN __rid");
                $connect->query("ALTER TABLE textbot ADD PRIMARY KEY (id_text)");
                echo "textbot primary key restored ✅";
            }
        } catch (Exception $rxTbPkErr) {
            error_log('[table.php textbot pk] ' . $rxTbPkErr->getMessage());
        }
    }
    $text_roll = "
♨️ قوانین استفاده از خدمات ما

1- به اطلاعیه هایی که داخل کانال گذاشته می شود حتما توجه کنید.
2- در صورتی که اطلاعیه ای در مورد قطعی در کانال گذاشته نشده به اکانت پشتیبانی پیام دهید
3- سرویس ها را از طریق پیامک ارسال نکنید برای ارسال پیامک می توانید از طریق ایمیل ارسال کنید.
    ";
    $text_dec_fq = "
 💡 سوالات متداول ⁉️

1️⃣ فیلترشکن شما آیپی ثابته؟ میتونم برای صرافی های ارز دیجیتال استفاده کنم؟

✅ به دلیل وضعیت نت و محدودیت های کشور سرویس ما مناسب ترید نیست و فقط لوکیشن‌ ثابته.

2️⃣ اگه قبل از منقضی شدن اکانت، تمدیدش کنم روزهای باقی مانده می سوزد؟

✅ خیر، روزهای باقیمونده اکانت موقع تمدید حساب میشن و اگه مثلا 5 روز قبل از منقضی شدن اکانت 1 ماهه خودتون اون رو تمدید کنید 5 روز باقیمونده + 30 روز تمدید میشه.

3️⃣ اگه به یک اکانت بیشتر از حد مجاز متصل شیم چه اتفاقی میافته؟

✅ در این صورت حجم سرویس شما زود تمام خواهد شد.

4️⃣ فیلترشکن شما از چه نوعیه؟

✅ فیلترشکن های ما v2ray است و پروتکل‌های مختلفی رو ساپورت میکنیم تا حتی تو دورانی که اینترنت اختلال داره بدون مشکل و افت سرعت بتونید از سرویستون استفاده کنید.

5️⃣ فیلترشکن از کدوم کشور است؟

✅ سرور فیلترشکن ما از کشور  آلمان است

6️⃣ چطور باید از این فیلترشکن استفاده کنم؟

✅ برای آموزش استفاده از برنامه، روی دکمه «📚 آموزش» بزنید.

7️⃣ فیلترشکن وصل نمیشه، چیکار کنم؟

✅ به همراه یک عکس از پیغام خطایی که میگیرید به پشتیبانی مراجعه کنید.

8️⃣ فیلترشکن شما تضمینی هست که همیشه مواقع متصل بشه؟

✅ به دلیل قابل پیش‌بینی نبودن وضعیت نت کشور، امکان دادن تضمین نیست فقط می‌تونیم تضمین کنیم که تمام تلاشمون رو برای ارائه سرویس هر چه بهتر انجام بدیم.

9️⃣ امکان بازگشت وجه دارید؟

✅ امکان بازگشت وجه در صورت حل نشدن مشکل از سمت ما وجود دارد.

💡 در صورتی که جواب سوالتون رو نگرفتید میتونید به «پشتیبانی» مراجعه کنید.";
    $text_channel = "
        ⚠️ کاربر گرامی؛ شما عضو چنل ما نیستید
از طریق دکمه زیر وارد کانال شده و عضو شوید
پس از عضویت دکمه بررسی عضویت را کلیک کنید";
    $text_invoice = "📇 پیش فاکتور شما:
👤 نام کاربری:  {username}
🔐 نام سرویس: {name_product}
📆 مدت اعتبار: {Service_time} روز
💶 قیمت:  {price} تومان
👥 حجم اکانت: {Volume} گیگ
🗒 یادداشت محصول : {note}
💵 موجودی کیف پول شما : {userBalance}

💰 سفارش شما آماده پرداخت است";
    $text_account_info = "
🗂 اطلاعات حساب کاربری شما :

🪪 شناسه کاربری: <code>{shanase}</code>
👤 نام: <code>{name}</code>
👨‍👩‍👦 کد معرف شما : <code>{referral_code}</code>
📱 شماره تماس :{phone}
⌚️زمان ثبت نام : {register_date}
💰 موجودی: {balance} تومان
🛒 تعداد سرویس های خریداری شده : {order_count} عدد
📑 تعداد فاکتور های پرداخت شده :  : {payment_count} عدد
🤝 تعداد زیر مجموعه های شما : {affiliate_count} نفر
🔖 گروه کاربری : {group}

📆 {date_now} → ⏰ {time_now}
                    ";
    $text_service_detail = "📊وضعیت سرویس : {status}
👤 نام سرویس : <code>{username}</code>
🌍 موقعیت سرویس :{location}
🗂 نام محصول :{product}

🔋 ترافیک : {traffic}
📥 حجم مصرفی : {used}
💢 حجم باقی مانده : {remaining} ({percent}%)

📅 تاریخ اتمام : {expire_date} ({days_left})";
    $textafterpay = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

{connection_links}
🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $text_wgdashboard = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textmanual = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}

 اطلاعات سرویس :
{connection_links}
🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textaftertext = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  ساعت
🗜 حجم سرویس:  {volume} مگابایت

{connection_links}
🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textconfigtest = "با سلام خدمت شما کاربر گرامی
سرویس تست شما با نام کاربری {username} به پایان رسیده است
امیدواریم تجربه‌ی خوبی از آسودگی و سرعت سرویستون داشته باشین. در صورتی که از سرویس‌ تست خودتون راضی بودین، میتونید سرویس اختصاصی خودتون رو تهیه کنید و از داشتن اینترنت آزاد با نهایت کیفیت لذت ببرید😉🔥
🛍 برای تهیه سرویس با کیفیت می توانید از دکمه زیر استفاده نمایید";
    $textcart = "برای افزایش موجودی، مبلغ <code>{price}</code>  تومان  را به شماره‌ی حساب زیر واریز کنید 👇🏻

        ====================
        <code>{card_number}</code>
        {name_card}
        ====================

❌ این تراکنش به مدت ۳۰ دقیقه (نیم ساعت) اعتبار دارد و پس از آن امکان پرداخت این تراکنش وجود نخواهد داشت.
‼مبلغ باید همان مبلغی که در بالا ذکر شده واریز نمایید.
‼️امکان برداشت وجه از کیف پول نیست.
‼️مسئولیت واریز اشتباهی با شماست.
🔝بعد از پرداخت  دکمه پرداخت کردم را زده سپس تصویر رسید را ارسال نمایید
💵بعد از تایید پرداختتون توسط ادمین کیف پول شما شارژ خواهد شد و در صورتی که سفارشی داشته باشین انجام خواهد شد";
    $textcartauto = "برای تایید فوری لطفا دقیقاً مبلغ زیر واریز شود. در غیر این صورت تایید پرداخت شما ممکن است با تاخیر مواجه شود.⚠️
            برای افزایش موجودی، مبلغ <code>{price}</code>  ریال  را به شماره‌ی حساب زیر واریز کنید 👇🏻

        ====================
        <code>{card_number}</code>
        {name_card}
        ====================

💰دقیقا مبلغی را که در بالا ذکر شده واریز نمایید تا بصورت آنی تایید شود.
‼️امکان برداشت وجه از کیف پول نیست.
🔝لزومی به ارسال رسید نیست، اما در صورتی که بعد از گذشت مدتی واریز شما تایید نشد، عکس رسید خود را ارسال کنید.";
    $insertQueries = [
        ['text_start', 'سلام خوش آمدید'],
        ['text_usertest', '🔑 اکانت تست'],
        ['text_Purchased_services', '🛍 سرویس های من'],
        ['text_support', '☎️ پشتیبانی'],
        ['text_help', '📚 آموزش'],
        ['text_bot_off', '❌ ربات خاموش است، لطفا دقایقی دیگر مراجعه کنید'],
        ['text_roll', $text_roll],
        ['text_fq', '❓ سوالات متداول'],
        ['text_dec_fq', $text_dec_fq],
        ['text_sell', '🔐 خرید اشتراک'],
        ['text_Add_Balance', '💰 افزایش موجودی'],
        ['text_channel', $text_channel],
        ['text_Discount', '🎁 کد هدیه'],
        ['text_Tariff_list', '💵 تعرفه اشتراک ها'],
        ['text_dec_Tariff_list', 'تنظیم نشده است'],
        ['text_Account_op', '🎛 حساب کاربری'],
        ['text_affiliates', '👥 زیر مجموعه گیری'],
        ['text_pishinvoice', $text_invoice],
        ['text_account_info', $text_account_info],
        ['text_service_detail', $text_service_detail],
        ['accountwallet', '🏦 کیف پول + شارژ'],
        ['carttocart', '💳 کارت به کارت'],
        ['textnowpayment', '💵 پرداخت ارزی 1'],
        ['textnowpaymenttron', '💵 واریز رمزارز ترون'],
        ['textsnowpayment', '💸 پرداخت با ارز دیجیتال'],
        ['iranpay1', 'ریالی سوم'],
        ['iranpay2', 'ترونادو'],
        ['iranpay3', 'ترونادو'],
        ['mowpayment', '💸 پرداخت با ارز دیجیتال'],
        ['zarinpal', '🟡 زرین پال'],
        ['tonpay', '💠 تون‌پی'],
        ['cubepay', '🟦 کیوب‌پی'],
        ['blupal', '💙 بلوپال'],
        ['variza', '💳 واریزا'],
        ['abangateway', '💳 آبان گیت وی'],
        ['atlaspay', '🌐 اطلس‌پی'],
        ['tetrapay', '🔷 تتراپی'],
        ['textafterpay', $textafterpay],
        ['dyn_purchase_subscription_link_line', '🔗 لینک اتصال: {link}'],
        ['textaftertext', $textaftertext],
        ['textmanual', $textmanual],
        ['textselectlocation', '📌 موقعیت سرویس را انتخاب نمایید.'],
        ['crontest', $textconfigtest],
        ['textpaymentnotverify', 'درگاه ریالی'],
        ['textrequestagent', '👨‍💻 درخواست نمایندگی'],
        ['textpanelagent', '👨‍💻 پنل نمایندگی'],
        ['text_wheel_luck', '🎲 گردونه شانس'],
        ['text_cart', $textcart],
        ['text_cart_auto', $textcartauto],
        ['text_star_telegram', "💫 Star Telegram"],
        ['text_request_agent_dec', '📌 توضیحات خود را برای ثبت درخواست نمایندگی ارسال نمایید.'],
        ['text_extend', '♻️ تمدید سرویس'],
        ['text_wgdashboard', $text_wgdashboard],
        ['miniapp_suggest_1', "کاربر گرامی، برای خرید، تمدید و مدیریت سرویس‌های خود لطفاً از مینی‌اپ اختصاصی ربات استفاده کنید.\n\n✨ مزایای استفاده از مینی‌اپ:\n• رابط کاربری مدرن و بسیار ساده\n• سرعت بسیار بالا در خرید و تحویل سرویس\n• مدیریت کامل و لحظه‌ای اکانت‌ها\n• پرداخت امن و آسان\n\n👇 جهت ورود روی دکمه زیر کلیک کنید:"],
        ['dyn_errors_verification_failed', '❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید'],
        ['dyn_errors_restart_process', '❌ مراحل خرید را مجددا از اول انجام دهید'],
        ['dyn_errors_concurrency_insufficient_stock', '❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.'],
        ['dyn_errors_panel_connection_error', '❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.'],
        ['dyn_errors_button_disabled', '❌ این دکمه غیرفعال می باشد'],
        ['dyn_errors_feature_unavailable', '❌ این قابلیت درحال حاضر در دسترس نیست.'],
        ['dyn_errors_feature_unavailable_short', '❌ این قابلیت در دسترس نیست.'],
        ['dyn_errors_feature_currently_unavailable_nodot', '❌ این قابلیت درحال حاضر در دسترس نیست'],
        ['dyn_errors_renewal_failed_restart', '❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.'],
        ['dyn_errors_renewal_support_error', '❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید'],
        ['dyn_errors_renewal_stock_empty', '❌ موجودی انبار برای این محصول تمام شده است. مبلغی از حساب شما کسر نشد.'],
        ['dyn_errors_purchase_info_incomplete', '❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.'],
        ['dyn_errors_panel_not_found', '❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.'],
        ['dyn_errors_panel_unavailable_choose_other', '❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.'],
        ['dyn_errors_extra_volume_purchase_error', '❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید'],
        ['dyn_errors_location_change_limit_reached', '❌ محدودیت تغییر لوکیشن شما به پایان رسیده است'],
        ['dyn_errors_min_max_deposit_amount', '❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد'],
        ['dyn_errors_stock_depleted_no_charge', '❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.'],
        ['dyn_errors_stock_extend_success', '✅ تمدید انباری انجام شد و موجودی انبار یک عدد کم شد.'],
        ['dyn_errors_invalid_subscription_link', '❌ لینک اشتراک نامعتبر است'],
        ['dyn_errors_service_found_count', '🛍 %s عدد سرویس یافت برای مشاهده و مدیریت سرویس روی یکی از سرویس ها کلیک کنید'],
        ['dyn_errors_service_not_found', '❌ سرویس مورد نظر پیدا نشد.'],
        ['dyn_errors_view_account_unavailable', '❌ امکان مشاهده اطلاعات اکانت درحال حاضر وجود ندارد'],
        ['dyn_errors_qr_unavailable', '❌ امکان دریافت QR Code در حال حاضر وجود ندارد.'],
        ['dyn_errors_service_deleted', '📌 سرویس با موفقیت حذف شد'],
        ['dyn_errors_config_read_error', '❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.'],
        ['dyn_errors_panel_unavailable_dot', '❌ پنل در دسترس نیست.'],
        ['dyn_errors_config_read_from_panel_error', '❌ خطا در خواندن کانفیگ از پنل.'],
        ['dyn_errors_usage_stats_failed', '❌ دریافت آمار مصرف ناموفق بود.'],
        ['dyn_errors_server_list_failed', '❌ دریافت لیست سرورها ناموفق بود.'],
        ['dyn_errors_not_connected_change_status', '❌ هنوز به کانفیگ متصل نشده اید و امکان تغییر وضعیت سرویس وجود ندارد. بعد از متصل شدن به کانفیگ می توانید از این قابلیت استفاده نمایید.'],
        ['dyn_errors_extend_unavailable_panel', '❌ امکان تمدید در این پنل وجود ندارد'],
        ['dyn_errors_not_connected_extend', '❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید'],
        ['dyn_errors_extend_current_plan_unavailable', '❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.'],
        ['dyn_errors_extend_restart_process', '❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.'],
        ['dyn_errors_exclusive_discount_conflict', '❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.'],
        ['dyn_errors_discount_expired', '❌ زمان کد تخفیف به پایان رسیده است.'],
        ['dyn_errors_discount_applied', '🤩 کد تخفیف شما درست بود و تخفیف %s روی فاکتور شما اعمال شد.'],
        ['dyn_errors_link_change_service_disabled', '❌ سرویس غیرفعال است و امکان تعویض لینک برای سرویس وجود ندارد.'],
        ['dyn_errors_extra_volume_unavailable_panel', '❌ امکان خرید حجم اضافه در این پنل وجود ندارد'],
        ['dyn_errors_extra_volume_restart_process', '❌ مراحل خرید حجم اضافه را مجددا انجام دهید'],
        ['dyn_errors_purchase_failed_restart', '❌ خرید با خطا مواجه گردید مراحل را مجدد انجام دهید.'],
        ['dyn_errors_location_change_unavailable', '❌ این قابلیت درحال حاضر دردسترس نیست.'],
        ['dyn_errors_generic_restart_process', '❌ خطایی رخ داده است لطفا مراحل مجددا انجام دهید'],
        ['dyn_errors_restart_buy_process_short', '❌ لطفا مراحل خرید را مجددا انجام دهید'],
        ['dyn_errors_transfer_panel_unavailable', '❌ امکان انتقال به پنل وجود ندارد.'],
        ['dyn_errors_transfer_unused_config_only', '❌ کانفیگ شما در وضعیت استفاده نشده است و امکان انتقال موقعیت سرویس وجود ندارد.'],
        ['dyn_errors_removal_request_thanks', '✅ با تشکر از ثبت درخواست ،درخواست شما  ارسال شده و درحال بررسی توسط پشتیبانی می باشد.'],
        ['dyn_errors_extra_time_unavailable_panel', '❌ امکان خرید زمان اضافه در این پنل وجود ندارد'],
        ['dyn_errors_extra_time_restart_process', '❌ مراحل خرید زمان اضافه را مجددا انجام دهید'],
        ['dyn_errors_service_removed', '📌 سرویس از لیست شما حذف شد'],
        ['dyn_errors_service_removal_unavailable', '❌ امکان حذف سرویس وجود ندارد.'],
        ['dyn_errors_removal_reason_prompt', '📌 دلیل حذف سرویس خود را ارسال کنید.'],
        ['dyn_errors_test_service_unavailable', '📌 سرویس تست در حال حاضر در دسترس نیست .'],
        ['dyn_errors_min_bulk_purchase_balance', '❌ برای خرید انبوه باید حداقل %s تومان موجودی داشته باشید.'],
        ['dyn_errors_section_disabled', '❌ این بخش در حال غیرفعال می باشد'],
        ['dyn_errors_discount_code_not_applicable', '❌ امکان خرید با این کد کد تخفیف وجود ندارد'],
        ['dyn_errors_stock_low_purchase_blocked', '❌ موجودی این سرویس به پایان رسیده لطفا سرویسی دیگر را خریداری کنید.'],
        ['dyn_referral_welcome_alert', "<b>🎉 خوش آمدی!</b>\n\nشما با دعوت <b>@%s</b> وارد ربات شدی و به عنوان زیرمجموعه ثبت شدی ✅\n\nبرای دریافت هدیه عضویت:\n🔘 به منوی <b>زیرمجموعه‌گیری</b> برو\n🔘 دکمه <b>🎁 هدیه عضویت</b> را بزن\n\nبا این کار، هم خودت و هم معرفت هدیه می‌گیرید! 💰"],
        ['dyn_referral_inviter_reward_alert', "<b>🎉 یک زیرمجموعه جدید!</b>\n\nکاربر <b>@%s</b> با لینک دعوت شما وارد ربات شد ✅\n\nبا خریدهای این کاربر، <b>سهم هدیه شما</b> به حسابت واریز می‌شه 🔥"],
        ['dyn_referral_commission_credited', '🎁 مبلغ %s به موجودی شما از طرف زیر مجموعه با شناسه کاربری %s اضافه گردید.'],
        ['dyn_rewards_points_earned', '📌شما %s امتیاز جدید کسب کردید.'],
        ['dyn_rewards_cashback_gift', "تبریک 🎉\n📌 به عنوان هدیه تمدید مبلغ %s تومان حساب شما شارژ گردید"],
        ['dyn_tickets_select_category', '📌 یک دسته را انتخاب نمایید'],
        ['dyn_tickets_select_department', '📌 بخش پشتیبانی که میخواهید پیام دهید را انتخاب نمایید.'],
        ['dyn_tickets_ask_subject', '📝 موضوع تیکت را وارد نمایید (مثلاً: مشکل در اتصال):'],
        ['dyn_tickets_operation_canceled', '❌ عملیات لغو شد.'],
        ['dyn_tickets_invalid_subject_text', '❌ لطفاً موضوع تیکت را به‌صورت متن وارد کنید.'],
        ['dyn_tickets_ask_media_new', '📎 می‌خواهید همراه تیکت عکس/ویدیو هم بفرستید؟'],
        ['dyn_tickets_ask_media_reply', '📎 می‌خواهید همراه پاسخ عکس/ویدیو هم بفرستید؟'],
        ['dyn_tickets_media_yes', '🖼 بله'],
        ['dyn_tickets_media_no', '✏️ فقط متن'],
        ['dyn_tickets_cancel', '🔙 انصراف'],
        ['dyn_tickets_ask_media_count', '🔢 چند رسانه می‌خواهید بفرستید؟ (۱ تا ۵)'],
        ['dyn_tickets_ask_media_upload', '📤 رسانهٔ ۱ از %s را بفرستید (فقط عکس یا ویدیو).'],
        ['dyn_tickets_invalid_media_choice', '❌ لطفاً از دکمه‌های بالا انتخاب کنید یا «انصراف» بزنید.'],
        ['dyn_tickets_invalid_media_type', '❌ فقط عکس یا ویدیوی معمولی مجاز است (گیف/استیکر/فوروارد/فایل/ویس رد می‌شود). دوباره بفرستید یا «انصراف» بزنید.'],
        ['dyn_tickets_media_received', '✅ دریافت شد (%s از %s). رسانهٔ بعدی را بفرستید.'],
        ['dyn_tickets_ask_body_text', '✍️ حالا متن پیام خود را بنویسید:'],
        ['dyn_tickets_ask_body_text_short', '✍️ متن پیام خود را بنویسید:'],
        ['dyn_tickets_not_available', '❌ این تیکت در دسترس نیست.'],
        ['dyn_tickets_reply_recorded', '✅ پیام شما ثبت شد و پس از بررسی پاسخ داده می‌شود.'],
        ['dyn_tickets_invalid_body_text', '❌ لطفاً متن پیام را وارد کنید یا «انصراف» بزنید.'],
        ['dyn_tickets_created', '✅ تیکت شما با کد پیگیری <code>%s</code> ثبت شد و پس از بررسی پاسخ داده می‌شود.'],
        ['dyn_tickets_closed_locked', '🔒 این تیکت بسته است؛ تا زمانی که پشتیبانی پاسخ ندهد یا آن را باز نکند نمی‌توانید پیام ارسال کنید.'],
        ['dyn_tickets_no_media', '🖼 رسانه‌ای در این تیکت نیست.'],
        ['dyn_tickets_closed_confirm', '🔒 تیکت <code>%s</code> بسته شد. برای ارسال پیام جدید باید پشتیبانی پاسخ دهد یا آن را باز کند.'],
        ['dyn_wallet_select_amount', '💰 مبلغ مورد نظر را برای شارژ کیف پول انتخاب کنید:'],
        ['dyn_wallet_recheck_canceled', '❌ درخواست بررسی مجدد لغو شد.'],
        ['dyn_wallet_amount_not_numeric', '❌ مقدار وارد شده عددی نیست. لطفاً عددی مثل <code>0.2</code> ارسال کنید.'],
        ['dyn_wallet_rate_unavailable', '❌ نرخ لحظه‌ای ارز در دسترس نیست. لطفاً دقایقی دیگر تلاش کنید.'],
        ['dyn_wallet_calculated_amount_invalid', '❌ مبلغ محاسبه‌شده معتبر نیست. مقدار ارز را بررسی کنید.'],
        ['dyn_wallet_hash_not_found', '❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش خام یا لینک تراکنش را بفرستید.'],
        ['dyn_wallet_ask_receipt_photo', "📸 <b>عکس رسید/اسکرین‌شات تراکنش</b> را ارسال کنید (اختیاری).\n\nاگر عکسی ندارید، روی دکمه «ارسال بدون عکس» بزنید."],
        ['dyn_wallet_incomplete_info', '❌ اطلاعات ناقص است. لطفاً از ابتدا تلاش کنید.'],
        ['dyn_wallet_hash_already_used', '❌ این هش قبلاً برای فاکتور دیگری ثبت شده است...'],
        ['dyn_wallet_internal_error_retry', '❌ خطای داخلی در ثبت درخواست. لطفاً دوباره تلاش کنید.'],
        ['dyn_wallet_invalid_charge_amount', '❌ مبلغ شارژ نامعتبر است. مجددا تلاش کنید.'],
        ['dyn_wallet_discount_not_applicable_zero', '❌ این کد برای شارژ قابل استفاده نیست (مبلغ پرداختی صفر می‌شود).'],
        ['dyn_wallet_no_active_card', '❌ کارت بانکی فعالی برای این روش پرداخت یافت نشد. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.'],
        ['dyn_wallet_select_verified_card', "💳 کارت‌های تاییدشده شما:\n\nیکی از کارت‌های زیر را انتخاب کنید..."],
        ['dyn_wallet_card_auth_active_notice', "⚠️ احراز هویت کارت به کارت فعال است\n\n📸 لطفا تصویر کارت فیزیکی خود را که قصد واریز با آن را دارید، ارسال کنید.\n\n🔘 نکته مهم: برای تایید تراکنش، الزامی است که ۴ رقم آخر شماره کارت و نام شما در تصویر کاملاً واضح و خوانا باشد. (سایر اطلاعات را می‌توانید بپوشانید)."],
        ['dyn_wallet_queue_too_busy', "تعداد افراد در صف درخواست درگاه پرداخت بشدت زیاد است 📊\n\n‼️درحال حاظر از روش پرداخت دیگری استفاده کنید"],
        ['dyn_wallet_hashchecker_not_loaded', '❌ ماژول هش‌چکر بارگذاری نشده است. لطفاً مدتی دیگر تلاش کنید.'],
        ['dyn_wallet_no_wallet_address_configured', '❌ آدرس کیف پولی برای هیچ شبکه‌ای توسط ادمین ثبت نشده است...'],
        ['dyn_wallet_charge_amount_undetectable', '❌ مبلغ شارژ قابل تشخیص نیست. لطفاً مجدداً از منوی شارژ کیف پول اقدام کنید.'],
        ['dyn_wallet_invoice_not_found', '❌ فاکتور یافت نشد.'],
        ['dyn_wallet_invoice_not_pending', '❌ این فاکتور دیگر در حالت انتظار پرداخت نیست.'],
        ['dyn_affiliates_extra_section_disabled', '📛 این بخش درحال حاضر غیرفعال می باشد'],
        ['dyn_affiliates_extra_not_affiliate_of_anyone', '📛 شما زیرمجموعه هیچ کاربری نیستید.'],
        ['dyn_affiliates_extra_gift_already_received', "<b>⛔ شما قبلاً هدیه عضویت را دریافت کرده‌اید.</b>\nاین هدیه فقط <b>یک‌بار</b> قابل فعال‌سازی است."],
        ['dyn_affiliates_extra_gift_credited_inviter', '🎉 یک نفر با معرفی شما وارد شد! هدیه به حساب شما واریز شد.'],
        ['dyn_affiliates_extra_gift_activated', '🎉 هدیه عضویت برای شما فعال شد!'],
        ['dyn_wheel_button_disabled_you', '❌ این دکمه برای شما غیرفعال می باشد'],
        ['dyn_wheel_no_purchase_users_only', '❌ متاسفانه این آپشن فقط برای کاربرانی فعال است که از ربات خریدی نداشته باشند.'],
        ['dyn_wheel_price_unavailable', '❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.'],
        ['dyn_wallet_prompt_amount_prompt', "💸 مبلغ را  به تومان وارد کنید:\n✅  حداقل مبلغ %s حداکثر مبلغ %s تومان می باشد"],
        ['dyn_wallet_prompt_amount_out_of_range', "❌ خطا\n💬 مبلغ باید حداقل %s تومان و حداکثر %s تومان باشد"],
        ['dyn_wallet_prompt_debt_must_pay_first', "❌ شما بدهی دارید، باید حداقل %s تومان پرداخت کنید.\n         میبغ خود را مجددا ارسال نمایید"],
        ['dyn_wallet_prompt_has_discount_yes', '✅ بله، کد تخفیف دارم'],
        ['dyn_wallet_prompt_has_discount_no', '➡️ خیر، ادامه به پرداخت'],
        ['dyn_wallet_prompt_ask_has_discount', '🎁 آیا برای شارژ کیف پول کد تخفیف دارید؟'],
        ['dyn_wallet_prompt_auth_success', 'حساب کاربری شما با موفقیت احرازهویت گردید'],
        ['dyn_group_setup_topic_not_active', "⚠️ تاپیک گروه فعال نیست\n\nلطفاً ابتدا از بخش تنظیمات گروه، گزینه تاپیک را فعال کنید؛ سپس مجدداً عبارت «آیدی» یا «ایدی» را داخل گروه ارسال نمایید."],
        ['dyn_group_setup_insufficient_access', "⚠️ دسترسی ربات کافی نیست\n\nلطفاً ابتدا ربات را در این گروه ادمین کنید و دسترسی‌های موردنیاز را در اختیار آن قرار دهید؛ سپس دوباره عبارت «آیدی» یا «ایدی» را ارسال نمایید."],
        ['dyn_group_setup_chat_id_received', "✅ آیدی عددی گروه با موفقیت دریافت شد\n\nآیدی عددی این گروه:\n%s\n\nلطفاً این شناسه را کپی کرده و در بخش تنظیم گروه گزارشات ربات ارسال نمایید. 📋"],
        ['dyn_receipt_already_confirmed', '✅ این پرداخت قبلاً تأیید شده است.'],
        ['dyn_receipt_already_pending_review', '⏳ رسید این پرداخت قبلاً ارسال شده و در انتظار بررسی ادمین است.'],
        ['dyn_receipt_no_admin_configured', '❌ هیچ ادمینی روی سرور تنظیم نشده است.'],
        ['dyn_receipt_bot_token_missing', '❌ توکن ربات روی سرور تنظیم نشده است.'],
        ['dyn_receipt_admin_delivery_failed', '❌ ارسال رسید به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.'],
        ['dyn_receipt_status_update_failed', '❌ رسید برای ادمین ارسال شد اما ثبت وضعیت آن ناموفق بود. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.'],
        ['dyn_receipt_submitted_success', '✅ رسید شما برای ادمین ارسال شد. پس از تأیید، حساب شما شارژ می‌شود.'],
        ['dyn_receipt_admin_caption_tpl', "💳 رسید پرداخت کارت‌به‌کارت\n\n<blockquote>👤 کد پیگیری: <code>{order_id}</code></blockquote>\n<blockquote>💰 مبلغ: {amount} تومان</blockquote>\n<blockquote>👤 کاربر: {user_link}</blockquote>\n<blockquote>👤 شناسه عددی: <code>{user_id}</code></blockquote>\n<blockquote>💎 موجودی فعلی: {balance} تومان</blockquote>\n<blockquote>📌 روش: {method}</blockquote>"],
        ['dyn_receipt_view_user_btn', '👁 مشاهده کاربر'],
        ['dyn_receipt_user_card_caption', '🪪 کارت بانکی کاربر'],
        ['dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'],
        ['dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'],
        ['dyn_errors_single_photo_only', '❌  فقط مجاز به ارسال یک تصویر هستید'],
        ['dyn_admin_notify_new_purchase_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید سرویس جدید\n\n<blockquote>نام کاربری سرویس : {service_username}</blockquote>\n<blockquote>نام محصول : {product_name}</blockquote>\n<blockquote>حجم محصول : {volume} گیگ</blockquote>\n<blockquote>زمان محصول : {service_time} روز</blockquote>\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💵 تعداد کل پرداختی های کاربر : {payment_count} عدد</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_admin_notify_extend_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nتمدید\n<blockquote>نام کاربری سرویس : {service_username}</blockquote>\n<blockquote>نام محصول : {product_name}</blockquote>\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💵 تعداد کل پرداختی های کاربر : {payment_count} عدد</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_admin_notify_extra_volume_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید حجم اضافه\n<blockquote>نام کاربری سرویس : {service_username}</blockquote>\n<blockquote>حجم خریداری شده  : {volumes}</blockquote>\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💵 تعداد کل پرداختی های کاربر : {payment_count} عدد</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_admin_notify_extra_time_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید زمان اضافه\n<blockquote>نام کاربری سرویس : {service_username}</blockquote>\n<blockquote>تعداد روز خریداری شده  : {days}</blockquote>\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💵 تعداد کل پرداختی های کاربر : {payment_count} عدد</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_admin_notify_wallet_topup_tpl', "⭕️ یک پرداخت جدید انجام شده است .\nافزایش موجودی\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💵 تعداد کل پرداختی های کاربر : {payment_count} عدد</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_admin_notify_cart_new_purchase_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید سرویس جدید\n<blockquote>نام کاربری سرویس  : {service_username}</blockquote>\n<blockquote>نام محصول : {product_name}</blockquote>\n<blockquote>حجم محصول : {volume} گیگ</blockquote>\n<blockquote>زمان محصول : {service_time} روز</blockquote>\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_admin_notify_cart_extend_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nتمدید\n<blockquote>نام کاربری سرویس : {service_username}</blockquote>\n<blockquote>نام محصول : {product_name}</blockquote>\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_admin_notify_cart_extra_volume_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید حجم اضافه\n<blockquote>نام کاربری سرویس : {service_username}</blockquote>\n<blockquote>حجم خریداری شده  : {volumes}</blockquote>\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_admin_notify_cart_extra_time_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید زمان اضافه\n<blockquote>نام کاربری سرویس : {service_username}</blockquote>\n<blockquote>تعداد روز خریداری شده  : {days}</blockquote>\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_admin_notify_cart_wallet_topup_tpl', "⭕️ یک پرداخت جدید انجام شده است .\nافزایش موجودی\n<blockquote>👤 نام اکانت کاربر : {first_name}</blockquote>\n<blockquote>👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a></blockquote>\n<blockquote>💸 موجودی فعلی کاربر : {format_balance} تومان</blockquote>\n<blockquote>🛒 کد پیگیری پرداخت: {order_id}</blockquote>\n<blockquote>⚜️ نام کاربری: @{username}</blockquote>\n<blockquote>💸 مبلغ پرداختی: {format_price_cart} تومان</blockquote>\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."],
        ['dyn_cart_receipt_extend_sent', '🚀 رسید شما ارسال و پس از بررسی سرویس شما تمدید خواهد شد'],
        ['dyn_cart_receipt_extra_volume_sent', '🚀 رسید شما ارسال و پس از بررسی  به سرویس شما حجم اضافه خواهد شد.'],
        ['dyn_cart_receipt_extra_time_sent', '🚀 رسید شما ارسال و پس از بررسی به سرویس شما زمان اضافه خواهد شد'],
        ['dyn_receipt_payment_receipt_caption', '🧾 رسید پرداخت'],
        ['dyn_purchase_panel_not_found', 'پنل انتخابی موجود نیست.'],
        ['dyn_purchase_panel_disabled', 'پنل انتخابی درحال حاضر فعال نیست'],
        ['dyn_purchase_product_not_found', 'محصول انتخابی پیدا نشد'],
        ['dyn_purchase_product_not_allowed_for_agent', 'این محصول برای نوع کاربری شما فعال نیست'],
        ['dyn_purchase_invalid_discount_code', '❌ کد تخفیف نامعتبر است.'],
        ['dyn_purchase_custom_username_required', 'برای این پنل، انتخاب نام کاربری دلخواه ضروری است.'],
        ['dyn_purchase_custom_username_invalid', 'نام کاربری معتبر نیست. فقط حروف انگلیسی، عدد، _ . - (۳ تا ۴۰ کاراکتر).'],
        ['dyn_purchase_balance_below_price', 'موجودی کمتر از قیمت محصول است'],
        ['dyn_purchase_username_already_taken', 'این نام کاربری قبلاً ثبت شده است، لطفاً دوباره تلاش کنید.'],
        ['dyn_purchase_invoice_save_failed', 'خطا در ذخیره فاکتور'],
        ['dyn_purchase_insufficient_balance_pay_shortfall', 'موجودی کافی نیست — لطفاً مبلغ کسری را پرداخت کنید'],
        ['dyn_purchase_concurrent_balance_conflict', 'موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.'],
        ['dyn_purchase_stock_depleted', '❌ موجودی انبار برای این محصول تمام شده است؛ مبلغی کسر نشد.'],
        ['dyn_purchase_score_earned_1', '📌شما 1 امتیاز جدید کسب کردید.'],
        ['dyn_purchase_create_user_failed_report_tpl', "⭕️ خطای ساخت اشتراک \n✍️ دلیل خطا : \n{reason}\nآیدی کابر : {user_id}\nنام کاربری کاربر : @{username}\nنام پنل : {panel_name}"],
        ['dyn_purchase_create_user_failed_customer', 'خطایی در ساخت اشتراک رخ داده است با پشتیبانی در ارتباط باشید'],
        ['dyn_purchase_view_tutorial_btn', '📚 مشاهده آموزش استفاده '],
        ['dyn_purchase_qr_code_btn', '📷 دریافت QR Code'],
        ['dyn_purchase_config_subscription_header_btn', '🔐 کانفیگ اشتراک'],
        ['dyn_purchase_invalid_volume_restart', 'حجم نامعتبر است خرید را از اول انجام دهید'],
        ['dyn_purchase_invalid_time_restart', 'زمان نامعتبر است خرید را از اول انجام دهید'],
        ['dyn_purchase_score_earned_2', '📌شما 2 امتیاز جدید کسب کردید.'],
        ['dyn_purchase_affiliate_commission_ledger_note', 'پورسانت خرید زیرمجموعه'],
        ['dyn_purchase_affiliate_commission_user_tpl', "🎁  پرداخت پورسانت\n\nمبلغ {amount} تومان به حساب شما از طرف زیر مجموعه تان به کیف پول شما واریز گردید"],
        ['dyn_purchase_affiliate_commission_report_tpl', "\nمبلغ {amount} به کاربر {affiliate_id} برای پورسانت از کاربر {user_id} واریز گردید\nتایم : {when}"],
        ['dyn_purchase_first_buy_flag', '📌 خرید اول کاربر'],
        ['dyn_purchase_report_channel_tpl', "📣 جزئیات ساخت اکانت در مینی اپ ثبت شد .\n\n{first_buy}\n▫️آیدی عددی کاربر : <code>{user_id}</code>\n▫️نام کاربری کاربر :@{username}\n▫️نام کاربری کانفیگ :{config_username}\n▫️موقعیت سرویس : {panel_name}\n▫️نام محصول :{product_name}\n▫️زمان خریداری شده :{service_time} روز\n▫️حجم خریداری شده : {volume} GB\n▫️موجودی قبل خرید : {balance_before} تومان\n▫️موجودی بعد خرید : {balance_after} تومان\n▫️کد پیگیری: {order_id}\n▫️نوع کاربر : {agent}\n▫️شماره تلفن کاربر : {phone}\n▫️قیمت محصول : {price} تومان\n▫️زمان خرید : {when}"],
        ['dyn_paymentinit_amount_out_of_range', '❌ مبلغ باید بین {min} و {max} تومان باشد'],
        ['dyn_paymentinit_pending_request_exists', '⏳ یک درخواست پرداخت در انتظار بررسی دارید. لطفاً ابتدا آن را تکمیل یا لغو کنید.'],
        ['dyn_paymentinit_renew_service_not_found', '❌ سرویسی برای تمدید با این نام کاربری پیدا نشد.'],
        ['dyn_paymentinit_renew_steps_incomplete', '❌ مراحل تمدید کامل نشده است. لطفاً تمدید را از ابتدا انجام دهید.'],
        ['dyn_paymentinit_unpaid_invoice_not_found', '❌ فاکتور خرید ناتمامی برای این نام کاربری پیدا نشد.'],
        ['dyn_paymentinit_discount_zeroes_charge', '❌ این کد برای شارژ قابل استفاده نیست (مبلغ پرداختی صفر می‌شود).'],
        ['dyn_paymentinit_click_link_to_pay', '🌸 برای تکمیل پرداخت روی لینک زیر کلیک کنید.'],
        ['dyn_paymentinit_digitaltron_use_hashchecker', '⚠️ این روش ارزی در مینی‌اپ از فلوی هش‌چکر استفاده می‌کند. لطفاً صفحه را بازنشانی کنید و مجدداً انتخاب کنید.'],
        ['dyn_paymentinit_manual_request_registered', '✅ درخواست شما ثبت شد. ادمین پس از بررسی، حساب شما را شارژ می‌کند.'],
        ['dyn_paymentinit_stars_complete_in_bot', '⭐ پرداخت با Telegram Stars از داخل ربات قابل تکمیل است.'],
        ['dyn_paymentinit_no_active_card', '❌ کارت بانکی فعالی برای کارت‌به‌کارت تنظیم نشده است.'],
        ['dyn_paymentinit_card_verify_active', '🔒 احراز هویت کارت‌به‌کارت فعال است.'],
        ['dyn_paymentinit_deposit_and_upload_receipt', '💳 مبلغ را به کارت زیر واریز کنید و سپس رسید را آپلود نمایید.'],
        ['dyn_paymentinit_gateway_function_missing', '❌ تابع گیت‌وی در سرور موجود نیست: {method}'],
        ['dyn_paymentinit_gateway_connection_error', '❌ خطا در ارتباط با درگاه {method}'],
        ['dyn_paymentinit_payment_link_creation_failed', '❌ ساخت لینک پرداخت ناموفق بود. لطفاً دوباره تلاش کنید.'],
        ['dyn_paymentinit_invoice_created_click_link', '✅ فاکتور ساخته شد. برای پرداخت روی لینک کلیک کنید.'],
        ['dyn_paymentinit_min_amount', '❌ حداقل مبلغ پرداخت {min} تومان است.'],
        ['dyn_paymentinit_max_amount', '❌ حداکثر مبلغ پرداخت {max} تومان است.'],
        ['dyn_paymentinit_gateway_generic_error', '❌ خطا در ارتباط با درگاه پرداخت.'],
        ['dyn_paymentinit_payment_link_creation_failed_short', '❌ ساخت لینک پرداخت ناموفق بود.'],
        ['dyn_paymentinit_plisio_function_missing', '❌ تابع Plisio روی این سرور موجود نیست.'],
        ['dyn_paymentinit_plisio_min_amount', '❌ حداقل مبلغ پرداخت Plisio {min} تومان است.'],
        ['dyn_paymentinit_plisio_max_amount', '❌ حداکثر مبلغ پرداخت Plisio {max} تومان است.'],
        ['dyn_paymentinit_rate_module_not_loaded', '❌ ماژول نرخ ارز روی سرور بارگذاری نشده.'],
        ['dyn_paymentinit_rate_fetch_failed', '❌ دریافت نرخ ارز ناموفق بود. لطفاً چند دقیقه دیگر تلاش کنید.'],
        ['dyn_paymentinit_rate_invalid', '❌ نرخ ارز نامعتبر است. لطفاً مدتی دیگر تلاش کنید.'],
        ['dyn_paymentinit_amount_too_small_usd', '❌ مبلغ پرداخت بسیار کم است (کمتر از 1 دلار).'],
        ['dyn_paymentinit_plisio_connection_error', '❌ خطا در ارتباط با Plisio. لطفاً دوباره تلاش کنید.'],
        ['dyn_paymentinit_plisio_link_creation_failed', '❌ ساخت لینک پرداخت Plisio ناموفق بود.'],
        ['dyn_paymentinit_plisio_invoice_created', 'فاکتور ارزی ساخته شد. روی لینک کلیک کنید تا به Plisio منتقل شوید.'],
        ['dyn_paymentinit_nowpayment_function_missing', '❌ تابع NowPayments روی این سرور موجود نیست.'],
        ['dyn_paymentinit_nowpayment_min_amount', '❌ حداقل مبلغ پرداخت NowPayments {min} تومان است.'],
        ['dyn_paymentinit_nowpayment_max_amount', '❌ حداکثر مبلغ پرداخت NowPayments {max} تومان است.'],
        ['dyn_paymentinit_nowpayment_rate_invalid', '❌ نرخ ارز نامعتبر است.'],
        ['dyn_paymentinit_amount_too_small', '❌ مبلغ پرداخت بسیار کم است.'],
        ['dyn_paymentinit_nowpayment_connection_error', '❌ خطا در ارتباط با NowPayments.'],
        ['dyn_paymentinit_nowpayment_link_creation_failed', '❌ ساخت لینک پرداخت NowPayments ناموفق بود.'],
        ['dyn_paymentinit_nowpayment_invoice_created', 'فاکتور ارزی NowPayments ساخته شد. روی لینک کلیک کنید.'],
        ['dyn_paymentinit_bot_username_not_configured', '❌ نام کاربری ربات روی سرور ثبت نشده است.'],
        ['dyn_serviceaction_label_changelink', '🔄 درخواست تغییر لینک'],
        ['dyn_serviceaction_label_refund', '💎 درخواست بازگشت وجه'],
        ['dyn_serviceaction_label_transfer', '↪️ درخواست انتقال به کاربر دیگر'],
        ['dyn_serviceaction_label_change_location', '📍 درخواست تغییر موقعیت'],
        ['dyn_serviceaction_transfer_target_required', 'شناسه کاربر مقصد را وارد کنید.'],
        ['dyn_serviceaction_transfer_target_must_be_numeric', 'شناسه کاربر باید عدد باشد.'],
        ['dyn_serviceaction_transfer_cannot_self', 'نمی‌توانید سرویس را به خودتان انتقال دهید.'],
        ['dyn_serviceaction_transfer_target_block_tpl', '👤 کاربر مقصد: <code>{target}</code>'],
        ['dyn_serviceaction_refund_reason_block_tpl', '📝 توضیح کاربر: {reason}'],
        ['dyn_serviceaction_miniapp_outdated', '⚠️ نسخهٔ مینی‌اپ شما قدیمی است. لطفاً صفحه را بازنشانی کنید (Pull-to-refresh یا بستن و باز کردن مینی‌اپ).'],
        ['dyn_serviceaction_select_new_location', '❌ موقعیت جدید را انتخاب کنید.'],
        ['dyn_serviceaction_validation_error_tpl', '❌ {err}'],
        ['dyn_serviceaction_from_miniapp_suffix', ' (از مینی‌اپ)'],
        ['dyn_serviceaction_user_line_tpl', '👤 کاربر: <a href="tg://user?id={user_id}">{user_full}</a>{username_part}'],
        ['dyn_serviceaction_user_id_line_tpl', '🪪 شناسه عددی: <code>{user_id}</code>'],
        ['dyn_serviceaction_product_line_tpl', '🛍 محصول: {product}'],
        ['dyn_serviceaction_service_username_line_tpl', '👤 نام کاربری سرویس: <code>{username}</code>'],
        ['dyn_serviceaction_location_line_tpl', '🌍 موقعیت سرویس: {location}'],
        ['dyn_serviceaction_invoice_id_line_tpl', '🆔 کد فاکتور: <code>{invoice_id}</code>'],
        ['dyn_serviceaction_time_line_tpl', '🕒 زمان: {when}'],
        ['dyn_serviceaction_manage_user_in_bot_btn', '⚙️ مدیریت کاربر در ربات'],
        ['dyn_serviceaction_refund_auto_btn', '🔄 بازگشت خودکار (مبلغ خرید)'],
        ['dyn_serviceaction_refund_manual_btn', '✋ بازگشت دستی (تعیین مبلغ)'],
        ['dyn_serviceaction_admin_delivery_failed', '❌ ارسال درخواست به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.'],
        ['dyn_serviceaction_request_sent_success', '✅ درخواست شما برای ادمین ارسال شد. ادمین پس از بررسی، با شما تماس می‌گیرد.'],
        ['dyn_serviceaction_incomplete_service_info', '❌ اطلاعات سرویس ناقص است.'],
        ['dyn_serviceaction_service_panel_not_found', '❌ پنل سرویس پیدا نشد.'],
        ['dyn_serviceaction_changelink_unavailable', '❌ امکان تغییر لینک روی این سرور فعال نیست.'],
        ['dyn_serviceaction_changelink_error_retry', '❌ خطایی در تغییر لینک رخ داد. لطفاً دوباره تلاش کنید.'],
        ['dyn_serviceaction_changelink_error_short', '❌ خطایی در تغییر لینک رخ داد.'],
        ['dyn_serviceaction_changelink_report_tpl', "📣 جزئیات تغییر لینک از مینی‌اپ ثبت شد .\n▫️آیدی عددی کاربر : <code>{user_id}</code>\n▫️نام کاربری کاربر :@{username}\n▫️نام کاربری کانفیگ :{config_username}\n▫️نام کاربر : {first_name}\n▫️موقعیت سرویس : {panel_name}\n▫️نوع کاربر : {agent}\n▫️زمان تغییر لینک : {when}"],
        ['dyn_serviceaction_changelink_success', '✅ لینک سرویس شما با موفقیت تغییر کرد.'],
        ['dyn_serviceaction_current_panel_not_found', '❌ پنل فعلی سرویس پیدا نشد.'],
        ['dyn_serviceaction_new_location_not_found', '❌ موقعیت جدید پیدا نشد.'],
        ['dyn_serviceaction_changeloc_unavailable_for_target', '❌ این قابلیت برای موقعیت مقصد در دسترس نیست.'],
        ['dyn_serviceaction_no_other_location', '❌ موقعیت دیگری برای انتقال وجود ندارد.'],
        ['dyn_serviceaction_target_same_as_current', '❌ موقعیت مقصد نمی‌تواند همان موقعیت فعلی باشد.'],
        ['dyn_serviceaction_changeloc_limit_reached', '❌ محدودیت تغییر لوکیشن شما به پایان رسیده است.'],
        ['dyn_serviceaction_transfer_to_panel_unavailable', '❌ امکان انتقال به این پنل وجود ندارد.'],
        ['dyn_serviceaction_config_on_hold_no_transfer', '❌ کانفیگ شما در وضعیت استفاده‌نشده است و امکان انتقال موقعیت وجود ندارد.'],
        ['dyn_serviceaction_status_forbids_transfer', '❌ وضعیت سرویس فعلی اجازه انتقال نمی‌دهد.'],
        ['dyn_serviceaction_purchase_limit_exhausted', '❌ مبلغ مجاز خرید شما به اتمام رسیده است.'],
        ['dyn_serviceaction_insufficient_balance_changeloc', '❌ موجودی کیف پول شما برای انتقال موقعیت کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.'],
        ['dyn_serviceaction_changeloc_ledger_debit_note', 'تغییر موقعیت سرویس'],
        ['dyn_serviceaction_changeloc_refund_note', 'بازگشت وجه به دلیل خطای تغییر موقعیت'],
        ['dyn_serviceaction_changeloc_generic_error', '❌ خطایی هنگام تغییر موقعیت رخ داد. با پشتیبانی در ارتباط باشید.'],
        ['dyn_serviceaction_changeloc_report_tpl', "📍 تغییر موقعیت سرویس (از مینی‌اپ)\n\n▫️آیدی عددی کاربر : <code>{user_id}</code>\n▫️نام کاربری کاربر : @{username}\n▫️نام کاربری کانفیگ : {config_username}\n▫️پنل قدیم : {old_panel}\n▫️پنل جدید : {new_panel}\n▫️موجودی کاربر : {balance} تومان\n▫️زمان : {when}"],
        ['dyn_serviceaction_changeloc_success_tpl', '✅ موقعیت سرویس شما با موفقیت به «{panel_name}» تغییر کرد.'],
        ['dyn_renewconfirm_status_invalid_restart', '❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.'],
        ['dyn_renewconfirm_extend_unavailable_panel', '❌ امکان تمدید در این پنل وجود ندارد'],
        ['dyn_renewconfirm_error_restart', '❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.'],
        ['dyn_renewconfirm_insufficient_balance', '❌ موجودی کیف پول شما کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.'],
        ['dyn_renewconfirm_request_save_failed', '❌ خطا در ثبت درخواست تمدید. لطفاً دوباره تلاش کنید.'],
        ['dyn_renewconfirm_insufficient_balance_pay_shortfall', 'موجودی کیف پول کافی نیست. لطفاً مبلغ کسری را پرداخت کنید.'],
        ['dyn_renewconfirm_ledger_debit_note', 'تمدید سرویس'],
        ['dyn_renewconfirm_stock_refund_note', 'بازگشت وجه به دلیل خطای انبار'],
        ['dyn_renewconfirm_stock_depleted', '❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.'],
        ['dyn_renewconfirm_national_stock_report_tpl', "✅ <b>تمدید سرویس (انبار شبکه‌ملی)</b>\n▫️آیدی کاربر : {user_id}\n▫️نام کاربری سرویس : {username}\n▫️محصول : {product_name}\n▫️حجم : {volume} گیگ\n▫️زمان : {service_time} روز\n▫️مبلغ : {price} تومان\n▫️پنل : {panel_name}"],
        ['dyn_renewconfirm_national_stock_success', '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد.'],
        ['dyn_renewconfirm_extend_refund_note', 'بازگشت وجه به دلیل خطای تمدید سرویس'],
        ['dyn_renewconfirm_extend_error', '❌ خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید'],
        ['dyn_renewconfirm_extend_failed_report_tpl', "خطای تمدید سرویس\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>دلیل خطا : {reason}</blockquote>"],
        ['dyn_renewconfirm_manual_stock_empty', '❌ موجودی انبار برای این محصول تمام شده است. مبلغی از حساب شما کسر نشد.'],
        ['dyn_renewconfirm_invalid_volume_range_tpl', '❌ حجم نامعتبر است (بین {min} و {max} گیگابایت)'],
        ['dyn_renewconfirm_invalid_time_range_tpl', '❌ زمان نامعتبر است (بین {min} و {max} روز)'],
        ['dyn_renewconfirm_custom_service_name', '⚙️ سرویس دلخواه'],
        ['dyn_renewconfirm_report_tpl', "✅ <b>تمدید سرویس</b>\n▫️آیدی کاربر : {user_id}\n▫️نام کاربری سرویس : {username}\n▫️محصول : {product_name}\n▫️حجم : {volume} گیگ\n▫️زمان : {service_time} روز\n▫️مبلغ : {price} تومان\n▫️پنل : {panel_name}"],
        ['dyn_renewconfirm_success', '✅ سرویس شما با موفقیت تمدید شد.'],
        ['dyn_renewconfirm_manage_user_btn', '👤 مدیریت کاربر'],
        ['dyn_testaccount_service_unavailable', '📌 سرویس تست در حال حاضر در دسترس نیست.'],
        ['dyn_testaccount_limit_reached', '❌ سقف دریافت اکانت تست شما به پایان رسیده است.'],
        ['dyn_testaccount_location_unavailable', '❌ سرویس تست برای این موقعیت در دسترس نیست.'],
        ['dyn_testaccount_username_required', 'لطفاً یک نام کاربری وارد کنید.'],
        ['dyn_testaccount_username_invalid', 'نام کاربری معتبر نیست.'],
        ['dyn_testaccount_manualsale_stock_depleted', '❌ موجودی این سرویس به پایان رسیده.'],
        ['dyn_testaccount_create_failed_report_tpl', "⭕️ یک کاربر قصد دریافت اکانت تست از مینی‌اپ داشت که ساخت کانفیگ با خطا مواجه شده\n<blockquote>✍️ دلیل خطا :</blockquote>\n<blockquote>{reason}</blockquote>\n<blockquote>آیدی کابر : {user_id}</blockquote>\n<blockquote>نام کاربری کاربر : @{username}</blockquote>\n<blockquote>نام پنل : {panel_name}</blockquote>"],
        ['dyn_testaccount_create_failed_customer', '❌ خطایی در ساخت اکانت تست رخ داد. با پشتیبانی تماس بگیرید.'],
        ['dyn_testaccount_default_template', "✅ اکانت تست شما ساخته شد\n\n👤 نام کاربری : {username}\n🌿 نام سرویس : {name_service}\n🇺🇳 لوکیشن : {location}\n⏳ مدت زمان: {day}\n🗜 حجم بسته: {volume}"],
        ['dyn_testaccount_product_name', 'سرویس تست'],
        ['dyn_testaccount_minimal_template', "✅ اکانت تست شما ساخته شد\n\n👤 نام کاربری : <code>{username}</code>"],
        ['dyn_testaccount_delivery_failed', '❌ اکانت تست ساخته شد اما ارسال آن به تلگرام ناموفق بود. لطفاً ربات را استارت کنید و دوباره تلاش کنید.'],
        ['dyn_testaccount_report_tpl', "🧪 دریافت اکانت تست از مینی‌اپ\n\n▫️آیدی عددی کاربر : <code>{user_id}</code>\n▫️نام کاربری کاربر :@{username}\n▫️نام کاربری کانفیگ :{config_username}\n▫️موقعیت سرویس : {panel_name}"],
        ['dyn_serviceextra_unit_day', 'روز'],
        ['dyn_serviceextra_unit_gigabyte', 'گیگابایت'],
        ['dyn_serviceextra_amount_min_tpl', '❌ مقدار باید حداقل {min} باشد'],
        ['dyn_serviceextra_amount_max_tpl', '❌ مقدار باید حداکثر {max} باشد'],
        ['dyn_serviceextra_ledger_debit_time', 'خرید زمان اضافه'],
        ['dyn_serviceextra_ledger_debit_volume', 'خرید حجم اضافه'],
        ['dyn_serviceextra_refund_note', 'بازگشت وجه به دلیل خطا در سرویس اضافه'],
        ['dyn_serviceextra_error_title_time', 'خطای خرید زمان اضافه'],
        ['dyn_serviceextra_error_title_volume', 'خطای خرید حجم اضافه'],
        ['dyn_serviceextra_error_report_tpl', "{error_title}\nنام پنل : {panel_name}\nنام کاربری سرویس : {username}\nدلیل خطا : {reason}"],
        ['dyn_serviceextra_generic_error_time', '❌ خطایی در خرید زمان اضافه رخ داده با پشتیبانی در ارتباط باشید'],
        ['dyn_serviceextra_generic_error_volume', '❌ خطایی در خرید حجم اضافه رخ داده با پشتیبانی در ارتباط باشید'],
        ['dyn_serviceextra_success_time_tpl', '✅ {amount} روز به سرویس شما اضافه شد.'],
        ['dyn_serviceextra_success_volume_tpl', '✅ {amount} گیگابایت به سرویس شما اضافه شد.'],
        ['dyn_serviceextra_status_invalid_restart', '❌ خرید با خطا مواجه گردید مراحل را مجدد انجام دهید.'],
        ['dyn_serviceextra_time_unavailable_panel', '❌ امکان خرید زمان اضافه در این پنل وجود ندارد'],
        ['dyn_serviceextra_volume_unavailable_panel', '❌ امکان خرید حجم اضافه در این پنل وجود ندارد'],
        ['dyn_serviceextra_feature_unavailable', '❌ این قابلیت درحال حاضر در دسترس نیست'],
        ['dyn_serviceextra_test_service_unavailable', '❌ این قابلیت برای سرویس تست در دسترس نیست'],
        ['dyn_serviceextra_tariff_not_configured', '❌ تعرفه خرید اضافه روی این پنل تنظیم نشده است.'],
        ['dyn_gift_all_done', '📌 عملیات برای تمامی سرویس های درخواستی انجام شد.'],
        ['dyn_gift_error_report_tpl', "خطای اضافه شدن هدیه حجم\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>دلیل خطا : {reason}</blockquote>"],
        ['dyn_gift_failed_users_suffix_tpl', "\n⚠️ کاربران با خطای اعمال هدیه:\n{list}"],
        ['dyn_uptime_api_access_error_tpl', "⚠️ خطای دسترسی API\n\nتوکن API پنل «{panel_name}» معتبر نیست یا دسترسی آن غیرفعال شده است.\nلطفاً اطلاعات اتصال پنل را بررسی و توکن جدید ثبت کنید."],
        ['dyn_uptime_api_access_restored_tpl', '✅ دسترسی API پنل «{panel_name}» دوباره برقرار شد.'],
        ['dyn_uptime_xray_stopped_tpl', '🚨 Xray روی پنل «{panel_name}» متوقف شده است؛ کاربران این پنل قطع هستند.'],
        ['dyn_uptime_xray_restored_tpl', '✅ Xray روی پنل «{panel_name}» دوباره فعال شد.'],
        ['dyn_uptime_panel_not_connected_tpl', '🚨 ادمین عزیز پنل با اسم <code>{panel_name}</code> متصل نیست.'],
        ['dyn_panel_tickets_continue_in_miniapp_btn', '🚀 ادامه گفتگو در مینی‌اپ'],
        ['dyn_panel_tickets_miniapp_only_note', "\n\n⚠️ ادامه گفتگو فقط از طریق مینی‌اپ امکان‌پذیر است."],
        ['dyn_panel_tickets_reply_btn', '💬 پاسخ'],
        ['dyn_panel_tickets_support_replied_tpl', '📩 پشتیبانی به تیکت شما (کد <code>{tracking}</code>) پاسخ داد.{miniapp_note}'],
        ['dyn_panel_tickets_support_reply_prefix', '📩 پاسخ پشتیبانی:'],
        ['dyn_panel_tickets_closed_by_support_tpl', '🔒 تیکت شما (کد <code>{tracking}</code>) توسط پشتیبانی بسته شد.'],
        ['dyn_panel_tickets_reopened_miniapp_tpl', "🔓 تیکت شما (کد <code>{tracking}</code>) دوباره باز شد.\n\n⚠️ ادامه گفتگو فقط از طریق مینی‌اپ امکان‌پذیر است."],
        ['dyn_panel_tickets_reopened_tpl', '🔓 تیکت شما (کد <code>{tracking}</code>) دوباره باز شد. می‌توانید پیام ارسال کنید.'],
        ['dyn_panel_tickets_my_tickets_btn', '🎫 تیکت‌های من'],
        ['dyn_panel_tickets_new_ticket_created_tpl', '🎫 یک تیکت جدید توسط پشتیبانی برای شما ثبت شد (کد <code>{tracking}</code>).'],
        ['dyn_panel_user_unblocked_notice', "✳️ حساب کاربری شما از مسدودی خارج شد ✳️\nاکنون میتوانید از ربات استفاده کنید "],
        ['dyn_panel_user_balance_added_tpl', '💎 کاربر عزیز مبلغ {amount} T به موجودی کیف پول تان اضافه گردید.'],
        ['dyn_panel_user_admin_message_tpl', "📥 یک پیام از مدیریت برای شما ارسال شد.\n\nمتن پیام : {message}"],
        ['dyn_cron_volume_warning_tpl', "با سلام خدمت شما کاربر گرامی 👋\n🚨 از حجم سرویس {username} تنها {volume} باقی مانده است. لطفاً در صورت تمایل برای خرید حجم اضافه و یا تمدید سرویستون از طریق بخش «{purchased_services}» اقدام بفرمایین"],
        ['dyn_cron_volume_warning_report_tpl', "📌 اطلاعیه کرون حجم\n\nنام کاربری سرویس :‌ <code>{username}</code>\nوضعیت سرویس : {status}\nحجم باقی مانده : {volume}"],
        ['dyn_cron_time_warning_tpl', "با سلام خدمت شما کاربر گرامی 👋\n📌 از مهلت زمانی استفاده از سرویس {username} فقط {days} روز باقی مانده است. لطفاً در صورت تمایل برای تمدید این سرویس، از طریق بخش «{purchased_services}» اقدام بفرمایین. با تشکر از همراهی شما"],
        ['dyn_cron_time_warning_report_tpl', "📌 اطلاعیه کرون زمان\n\nنام کاربری سرویس :‌ <code>{username}</code>\nوضعیت سرویس : {status}\nتعداد روز باقی مانده ‌:‌{days}"],
        ['dyn_cron_removal_notice_tpl', "📌 کاربر گرامی بدلیل عدم تمدید، سرویس {username} از لیست سرویس های شما حذف گردید\n\n🌟 جهت تهیه سرویس جدید از بخش خرید سرویس اقدام فرمایید"],
        ['dyn_cron_removal_report_tpl', "📌 اطلاعیه کرون حذف\n\nنام کاربری سرویس :‌ <code>{username}</code>\nوضعیت سرویس : {status}\nتعداد روز باقی مانده ‌:‌{days}\nحجم باقی مانده : {volume}"],
        ['dyn_cron_removal_volume_notice_tpl', "📌 کاربر گرامی بدلیل عدم تمدید، سرویس {username} از لیست سرویس های شما حذف گردید\n\n🌟 جهت تهیه سرویس جدید از بخش خرید سرویس اقدام فرمایید"],
        ['dyn_cron_removal_volume_report_tpl', "📌  اطلاعیه کرون حذف حجم \nنام کاربری سرویس : {username} \n وضعیت سرویس : {status} \nتعداد روز باقی مانده :{days} \n حجم باقی مانده : {volume}\nآخرین اتصال کاربر : {last_online}"],
        ['dyn_cron_extend_service_btn', '💊 تمدید سرویس'],
        ['dyn_public_log_btn_label', '🛒 خرید خدمات از ربات'],
        ['dyn_public_broadcast_new_sub_tpl', "✅ گزارش خرید #سفارش_جدید\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>🖥️ پنل : {panel_name}</blockquote>\n<blockquote>🏷️ دسته‌بندی : {category}</blockquote>\n<blockquote>📦 سرویس : {amount}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}"],
        ['dyn_public_broadcast_renewal_tpl', "✅ گزارش تمدید #تمدید_سرویس\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>🖥️ پنل : {panel_name}</blockquote>\n<blockquote>🏷️ دسته‌بندی : {category}</blockquote>\n<blockquote>📦 سرویس : {amount}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}"],
        ['dyn_public_broadcast_volume_topup_tpl', "✅ گزارش خرید #خرید_حجم\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>📦 مقدار : {amount} گیگابایت</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}"],
        ['dyn_public_broadcast_time_extra_tpl', "✅ گزارش خرید #خرید_زمان\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>📦 مقدار : {amount} روز</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}"],
        ['dyn_public_broadcast_wallet_deposit_tpl', "✅ گزارش تراکنش #شارژ_کیف_پول\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>📦 مقدار : {amount} تومان</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}"],
        ['dyn_renewconfirm_queued_success', "✅ سرویس خریداری شده رزرو شد و به محض پایان سرویس فعلی فعال می‌گردد."],
        ['dyn_queued_renewal_activated_tpl', "با سلام خدمت شما کاربر گرامی 👋\n✅ رزرو اشتراک سرویس {username} فعال شد و حجم و زمان سرویس شما تمدید گردید."],
        ['dyn_errors_queued_renewal_exists', "❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد."]
    ];
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE textbot (
        id_text varchar(600) PRIMARY KEY NOT NULL,
        text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table textbot: " . mysqli_error($connect));
        }

        foreach ($insertQueries as $query) {
            $idEsc   = $connect->real_escape_string($query[0]);
            $textEsc = $connect->real_escape_string($query[1]);
            $connect->query("INSERT INTO textbot (id_text, text) VALUES ('$idEsc', '$textEsc')");
        }
    } else {
        foreach ($insertQueries as $query) {
            $idEsc   = $connect->real_escape_string($query[0]);
            $textEsc = $connect->real_escape_string($query[1]);
            // Restore fully-missing rows...
            $connect->query("INSERT IGNORE INTO textbot (id_text, text) VALUES ('$idEsc', '$textEsc')");
            // ...and heal rows that exist but are blank/NULL (broken DB) without
            // clobbering an admin's customised, non-empty text.
            $connect->query("UPDATE textbot SET text = '$textEsc' WHERE id_text = '$idEsc' AND (text IS NULL OR text = '')");
        }
        $rxRialDefaults = [
            'iranpay1' => ['ریالی سوم', ['💸 درگاه  پرداخت ریالی', '💸 درگاه پرداخت ریالی', '💸 درگاه  پرداخت ریالی سوم', '💸 درگاه پرداخت ریالی سوم']],
            'iranpay2' => ['ترونادو', ['💸 درگاه  پرداخت ریالی دوم', '💸 درگاه پرداخت ریالی دوم', '💸 درگاه  پرداخت ریالی']],
            'iranpay3' => ['ترونادو', ['💸 درگاه  پرداخت ریالی سوم', '💸 درگاه پرداخت ریالی سوم', '💸 درگاه  پرداخت ریالی دوم']],
        ];
        foreach ($rxRialDefaults as $rxKey => $rxPair) {
            $rxKeyEsc = $connect->real_escape_string($rxKey);
            $rxNewEsc = $connect->real_escape_string($rxPair[0]);
            $rxOldList = [];
            foreach ($rxPair[1] as $rxOld) {
                $rxOldList[] = "'" . $connect->real_escape_string($rxOld) . "'";
            }
            $rxIn = implode(',', $rxOldList);
            $connect->query("UPDATE textbot SET text = '$rxNewEsc' WHERE id_text = '$rxKeyEsc' AND (text IS NULL OR text = '' OR text IN ($rxIn))");
        }

        $rxPublicLogDefaults = [
            'dyn_public_broadcast_new_sub_tpl' => [
                "✅ گزارش خرید #سفارش_جدید\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>🖥️ پنل : {panel_name}</blockquote>\n<blockquote>🏷️ دسته‌بندی : {category}</blockquote>\n<blockquote>📦 سرویس : {amount}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}",
                ["📣 خرید اشتراک جدید\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>🖥️ پنل : {panel_name}</blockquote>\n<blockquote>🏷️ دسته‌بندی : {category}</blockquote>\n<blockquote>📦 سرویس : {amount}</blockquote>\n<blockquote>⏰ زمان : {when}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>"],
            ],
            'dyn_public_broadcast_renewal_tpl' => [
                "✅ گزارش تمدید #تمدید_سرویس\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>🖥️ پنل : {panel_name}</blockquote>\n<blockquote>🏷️ دسته‌بندی : {category}</blockquote>\n<blockquote>📦 سرویس : {amount}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}",
                ["🔄 تمدید سرویس\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>🖥️ پنل : {panel_name}</blockquote>\n<blockquote>🏷️ دسته‌بندی : {category}</blockquote>\n<blockquote>📦 سرویس : {amount}</blockquote>\n<blockquote>⏰ زمان : {when}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>"],
            ],
            'dyn_public_broadcast_volume_topup_tpl' => [
                "✅ گزارش خرید #خرید_حجم\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>📦 مقدار : {amount} گیگابایت</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}",
                ["📶 افزایش حجم\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>📦 مقدار : {amount} گیگابایت</blockquote>\n<blockquote>⏰ زمان : {when}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>"],
            ],
            'dyn_public_broadcast_time_extra_tpl' => [
                "✅ گزارش خرید #خرید_زمان\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>📦 مقدار : {amount} روز</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}",
                ["⏳ افزایش زمان\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>📦 مقدار : {amount} روز</blockquote>\n<blockquote>⏰ زمان : {when}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>"],
            ],
            'dyn_public_broadcast_wallet_deposit_tpl' => [
                "✅ گزارش تراکنش #شارژ_کیف_پول\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>📦 مقدار : {amount} تومان</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>\n\n📅 : {date} - ⏰ : {time}",
                ["💳 شارژ کیف پول\n\n<blockquote>👤 آیدی کاربر : <code>{user_id}</code></blockquote>\n<blockquote>📦 مقدار : {amount} تومان</blockquote>\n<blockquote>⏰ زمان : {when}</blockquote>\n<blockquote>💰 مبلغ پرداختی : {price} تومان</blockquote>"],
            ],
        ];
        foreach ($rxPublicLogDefaults as $rxKey => $rxPair) {
            $rxKeyEsc = $connect->real_escape_string($rxKey);
            $rxNewEsc = $connect->real_escape_string($rxPair[0]);
            $rxOldList = [];
            foreach ($rxPair[1] as $rxOld) {
                $rxOldList[] = "'" . $connect->real_escape_string($rxOld) . "'";
            }
            $rxIn = implode(',', $rxOldList);
            $connect->query("UPDATE textbot SET text = '$rxNewEsc' WHERE id_text = '$rxKeyEsc' AND (text IS NULL OR text = '' OR text IN ($rxIn))");
        }
    }

    $jsonTextSeedFile = __DIR__ . DIRECTORY_SEPARATOR . 'text.json';
    if (is_file($jsonTextSeedFile)) {
        $jsonTextData = json_decode(file_get_contents($jsonTextSeedFile), true);
        $jsonTextFa = is_array($jsonTextData['fa'] ?? null) ? $jsonTextData['fa'] : [];
        $jsonTextInsertQueries = [];
        $jsonTextFlatten = static function (array $arr, string $prefix) use (&$jsonTextFlatten, &$jsonTextInsertQueries) {
            foreach ($arr as $k => $v) {
                $path = $prefix === '' ? (string) $k : $prefix . '.' . $k;
                if (is_array($v)) {
                    $jsonTextFlatten($v, $path);
                } else {
                    $jsonTextInsertQueries[] = ['jsontext.' . $path, (string) $v];
                }
            }
        };
        $jsonTextFlatten($jsonTextFa, '');
        foreach ($jsonTextInsertQueries as $query) {
            if (strlen($query[0]) > 600) {
                continue;
            }
            $idEsc   = $connect->real_escape_string($query[0]);
            $textEsc = $connect->real_escape_string($query[1]);
            $connect->query("INSERT IGNORE INTO textbot (id_text, text) VALUES ('$idEsc', '$textEsc')");
            $connect->query("UPDATE textbot SET text = '$textEsc' WHERE id_text = '$idEsc' AND (text IS NULL OR text = '')");
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'PaySetting'");
    $table_exists = ($result->num_rows > 0);
    $main = 20000;
    $max = 1000000;
    $settings = [
        ['Cartstatus', 'oncard'],
        ['CartDirect', '@cart'],
        ['cardnumber', '603700000000'],
        ['namecard', 'تنظیم نشده'],
        ['Cartstatuspv', 'offcardpv'],
        ['apinowpayment', '0'],
        ['api_plisio', '0'],
        ['api_nowpayment', '0'],
        ['nowpaymentstatus', 'offnowpayment'],
        ['digistatus', 'offdigi'],
        ['minbalance', '20000'],
        ['maxbalance', '1000000'],
        ['marchent_tronseller', '0'],
        ['walletaddress', ''],
        ['statuscardautoconfirm', 'offautoconfirm'],
        ['statustarnado', 'offternado'],
        ['apiternado', '0'],
        ['ipnsigningkeytronado', ''],
        ['wageFromBusinessPercentageTronado', '0'],
        ['chashbackcart', '0'],
        ['chashbackstar', '0'],
        ['chashbackperfect', '0'],
        ['chashbackiranpay2', '0'],
        ['chashbackplisio', '0'],
        ['chashbackzarinpal', '0'],
        ['checkpaycartfirst', 'offpayverify'],
        ['zarinpalstatus', 'offzarinpal'],
        ['merchant_zarinpal', '0'],
        ['minbalancecart', $main],
        ['maxbalancecart', $max],
        ['minbalancestar', $main],
        ['maxbalancestar', $max],
        ['minbalanceplisio', $main],
        ['maxbalanceplisio', $max],
        ['minbalancedigitaltron', $main],
        ['maxbalancedigitaltron', $max],
        ['minbalanceiranpay2', $main],
        ['maxbalanceiranpay2', $max],
        ['minbalancepaynotverify', $main],
        ['maxbalancepaynotverify', $max],
        ['minbalanceperfect', $main],
        ['maxbalanceperfect', $max],
        ['minbalancezarinpal', $main],
        ['maxbalancezarinpal', $max],
        ['minbalanceiranpay', $main],
        ['maxbalanceiranpay', $max],
        ['minbalancenowpayment', $main],
        ['maxbalancenowpayment', $max],
        ['apiiranpay', '0'],
        ['helpcart', '2'],
        ['helpstar', '2'],
        ['helpplisio', '2'],
        ['helpiranpay2', '2'],
        ['helpperfectmony', '2'],
        ['helpzarinpal', '2'],
        ['helpnowpayment', '2'],
        ['helpofflinearze', '2'],
        ['autoconfirmcart', 'offauto'],
        ['cashbacknowpayment', '0'],
        ['statusstar', '0'],
        ['statusnowpayment', '0'],
        ['Exception_auto_cart', '{}'],
        ['nowpayment_ipn_secret', ''],
        ['randomwallet_status', '0'],
        ['randomwallet_amounts', '[50000,75000,100000,150000,200000,250000,500000,1000000]'],
        ['statustonpay', 'offtonpay'],
        ['apitonpay', ''],
        ['chashbacktonpay', '0'],
        ['minbalancetonpay', $main],
        ['maxbalancetonpay', $max],
        ['helptonpay', '2'],
        ['statuscubepay', 'offcubepay'],
        ['apicubepay', ''],
        ['chashbackcubepay', '0'],
        ['feecubepay', '0'],
        ['minbalancecubepay', $main],
        ['maxbalancecubepay', $max],
        ['helpcubepay', '2'],
        ['statusblupal', 'offblupal'],
        ['apiblupal', ''],
        ['chashbackblupal', '0'],
        ['minbalanceblupal', $main],
        ['maxbalanceblupal', $max],
        ['helpblupal', '2'],
        ['statusvariza', 'offvariza'],
        ['apivariza', ''],
        ['variza_webhook_secret', ''],
        ['chashbackvariza', '0'],
        ['minbalancevariza', $main],
        ['maxbalancevariza', $max],
        ['helpvariza', '2'],
        ['statusabangateway', 'offabangateway'],
        ['urlabangateway', ''],
        ['apiabangateway', ''],
        ['chashbackabangateway', '0'],
        ['minbalanceabangateway', $main],
        ['maxbalanceabangateway', $max],
        ['helpabangateway', '2'],
        ['statusatlaspay', 'offatlaspay'],
        ['apiatlaspay', ''],
        ['chashbackatlaspay', '0'],
        ['minbalanceatlaspay', 50000],
        ['maxbalanceatlaspay', 2000000],
        ['helpatlaspay', '2'],
        ['chashbackcart_target', 'all'],
        ['chashbackiranpay2_target', 'all'],
        ['chashbacktonpay_target', 'all'],
        ['chashbackcubepay_target', 'all'],
        ['chashbackblupal_target', 'all'],
        ['chashbackvariza_target', 'all'],
        ['chashbackabangateway_target', 'all'],
        ['chashbackatlaspay_target', 'all'],
        ['chashbackzarinpal_target', 'all'],
        ['chashbackplisio_target', 'all'],
        ['cashbacknowpayment_target', 'all'],
        ['chashbackstar_target', 'all'],
    ];
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE PaySetting (
        NamePay varchar(500) PRIMARY KEY NOT NULL,
        ValuePay TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table PaySetting: " . mysqli_error($connect));
        }

        foreach ($settings as $setting) {
            $connect->query("INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('{$setting[0]}', '{$setting[1]}')");
        }
    } else {
        foreach ($settings as $setting) {
            $connect->query("INSERT IGNORE INTO PaySetting (NamePay, ValuePay) VALUES ('{$setting[0]}', '{$setting[1]}')");
        }


    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'DiscountSell'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE DiscountSell (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codeDiscount varchar(1000)  NOT NULL,
        price varchar(200)  NOT NULL,
        limitDiscount varchar(500)  NOT NULL,
        agent varchar(500)  NOT NULL,
        usefirst varchar(100)  NOT NULL,
        useuser varchar(100)  NOT NULL,
        code_product varchar(100)  NOT NULL,
        code_panel varchar(100)  NOT NULL,
        time varchar(100)  NOT NULL,
        type varchar(100)  NOT NULL,
        usedDiscount varchar(500) NOT NULL)");
        if (!$result) {
            error_log("[table.php] table DiscountSell: " . mysqli_error($connect));
        }
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'agent'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD agent VARCHAR(100)");
            echo "The agent discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'type'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD type VARCHAR(100)");
            echo "The agent type field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'time'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD time VARCHAR(100)");
            echo "The agent time field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'code_panel'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD code_panel VARCHAR(100)");
            echo "The code_panel discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'code_product'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD code_product VARCHAR(100)");
            echo "The code_product discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'useuser'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD useuser VARCHAR(100)");
            echo "The useuser discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'usefirst'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD usefirst VARCHAR(100)");
            echo "The usefirst discount field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $rx_addcol = function ($table, $col, $ddl) use ($connect) {
        $chk = $connect->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($chk && mysqli_num_rows($chk) != 1) {
            $connect->query("ALTER TABLE `$table` ADD $col $ddl");
        }
    };
    $rx_tableexists = function ($table) use ($connect) {
        $r = $connect->query("SHOW TABLES LIKE '$table'");
        return $r && $r->num_rows > 0;
    };

    if ($rx_tableexists('DiscountSell')) {
        $rx_addcol('DiscountSell', 'section', "VARCHAR(20) NULL");
        $rx_addcol('DiscountSell', 'value_type', "VARCHAR(10) NULL");
        $rx_addcol('DiscountSell', 'target_user', "VARCHAR(64) NULL");
        $rx_addcol('DiscountSell', 'status', "VARCHAR(12) NULL");
        $rx_addcol('DiscountSell', 'targeting_mode', "VARCHAR(12) NULL");
        $rx_addcol('DiscountSell', 'code_category', "VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL");
        $__ccCol = $connect->query("SHOW FULL COLUMNS FROM DiscountSell LIKE 'code_category'");
        $__ccRow = $__ccCol ? $__ccCol->fetch_assoc() : null;
        if ($__ccRow && strtolower((string)($__ccRow['Collation'] ?? '')) !== 'utf8mb4_bin') {
            $connect->query("ALTER TABLE DiscountSell MODIFY code_category VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL");
        }
        $__cpCol = $connect->query("SHOW FULL COLUMNS FROM DiscountSell LIKE 'code_panel'");
        $__cpRow = $__cpCol ? $__cpCol->fetch_assoc() : null;
        if ($__cpRow && (strtolower((string)($__cpRow['Collation'] ?? '')) !== 'utf8mb4_bin' || strpos((string)($__cpRow['Type'] ?? ''), '500') === false)) {
            $connect->query("ALTER TABLE DiscountSell MODIFY code_panel VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL");
        }
        $__secCol = $connect->query("SHOW FULL COLUMNS FROM DiscountSell LIKE 'section'");
        $__secRow = $__secCol ? $__secCol->fetch_assoc() : null;
        if ($__secRow && (strtolower((string)($__secRow['Collation'] ?? '')) !== 'utf8mb4_bin' || strpos((string)($__secRow['Type'] ?? ''), '200') === false)) {
            $connect->query("ALTER TABLE DiscountSell MODIFY section VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL");
        }
        $__agCol = $connect->query("SHOW FULL COLUMNS FROM DiscountSell LIKE 'agent'");
        $__agRow = $__agCol ? $__agCol->fetch_assoc() : null;
        if ($__agRow && strtolower((string)($__agRow['Collation'] ?? '')) !== 'utf8mb4_bin') {
            $connect->query("ALTER TABLE DiscountSell MODIFY agent VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
        }
        $connect->query("UPDATE DiscountSell SET section = type WHERE (section IS NULL OR section = '') AND type IN ('all','buy','extend')");
        $connect->query("UPDATE DiscountSell SET section = 'all' WHERE section IS NULL OR section = ''");
        $connect->query("UPDATE DiscountSell SET value_type = 'percent' WHERE value_type IS NULL OR value_type = '' OR value_type NOT IN ('percent','amount','free')");
        $connect->query("UPDATE DiscountSell SET agent = 'allusers' WHERE agent = 'all'");
        $connect->query("UPDATE DiscountSell SET status = 'active' WHERE status IS NULL OR status = ''");
        $connect->query("UPDATE DiscountSell SET targeting_mode = 'none' WHERE targeting_mode IS NULL OR targeting_mode = '' OR targeting_mode NOT IN ('none','category','panel')");
        $connect->query("UPDATE DiscountSell SET code_category = 'all' WHERE code_category IS NULL OR code_category = ''");
    }

    if ($rx_tableexists('Discount')) {
        $rx_addcol('Discount', 'target_user', "VARCHAR(64) NULL");
        $rx_addcol('Discount', 'expire_at', "VARCHAR(20) NULL");
        $rx_addcol('Discount', 'status', "VARCHAR(12) NULL");
        $connect->query("UPDATE Discount SET status = 'active' WHERE status IS NULL OR status = ''");
    }

    if ($rx_tableexists('Giftcodeconsumed')) {
        $rx_addcol('Giftcodeconsumed', 'kind', "VARCHAR(8) NULL");
        $rx_addcol('Giftcodeconsumed', 'consumed_at', "VARCHAR(20) NULL");
        $rx_addcol('Giftcodeconsumed', 'released', "TINYINT(1) NOT NULL DEFAULT 0");
        $rx_addcol('Giftcodeconsumed', 'related_order', "VARCHAR(200) NULL");
    }

    if ($rx_tableexists('cancel_service')) {
        $rx_addcol('cancel_service', 'resolved_at', "VARCHAR(20) NULL");
    }

    if ($rx_tableexists('Payment_report')) {
        $rx_addcol('Payment_report', 'charge_bonus', "VARCHAR(20) NULL");
        $rx_addcol('Payment_report', 'discount_code', "VARCHAR(200) NULL");
        $rx_addcol('Payment_report', 'discount_amount', "VARCHAR(200) NULL");
        $rx_addcol('Payment_report', 'price_before_discount', "VARCHAR(200) NULL");
        $rx_addcol('Payment_report', 'discount_consumed', "VARCHAR(1) NULL");
    }
    if ($rx_tableexists('invoice')) {
        $rx_addcol('invoice', 'discount_code', "VARCHAR(200) NULL");
        $rx_addcol('invoice', 'discount_amount', "VARCHAR(200) NOT NULL DEFAULT '0'");
        $rx_addcol('invoice', 'price_before_discount', "VARCHAR(200) NULL");
        $connect->query("UPDATE invoice SET discount_amount = '0' WHERE discount_amount IS NULL OR discount_amount = ''");
        $chkIdx = $connect->query("SHOW INDEX FROM `invoice` WHERE Key_name = 'idx_invoice_discount_code'");
        if ($chkIdx && mysqli_num_rows($chkIdx) == 0) {
            $connect->query("ALTER TABLE `invoice` ADD INDEX idx_invoice_discount_code (discount_code)");
        }
    }
    $connect->query("CREATE TABLE IF NOT EXISTS order_discount_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user VARCHAR(200) NOT NULL,
        id_invoice VARCHAR(200) NULL,
        code VARCHAR(200) NOT NULL,
        kind VARCHAR(20) NOT NULL DEFAULT 'sell',
        value_type VARCHAR(10) NOT NULL DEFAULT 'percent',
        value_raw VARCHAR(100) NULL,
        price_before BIGINT NOT NULL DEFAULT 0,
        discount_amount BIGINT NOT NULL DEFAULT 0,
        price_after BIGINT NOT NULL DEFAULT 0,
        section VARCHAR(20) NULL,
        created_at VARCHAR(20) NULL,
        INDEX idx_odl_user (id_user),
        INDEX idx_odl_invoice (id_invoice),
        INDEX idx_odl_code (code),
        INDEX idx_odl_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log('[discount-migrate] ' . $e->getMessage());
}

try {
    $connect->query("CREATE TABLE IF NOT EXISTS sale_ledger (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user VARCHAR(200) NOT NULL,
        id_invoice VARCHAR(200) NULL,
        Service_location VARCHAR(300) NOT NULL,
        kind VARCHAR(20) NOT NULL,
        name_product VARCHAR(200) NULL,
        amount BIGINT NOT NULL DEFAULT 0,
        source VARCHAR(10) NOT NULL DEFAULT 'bot',
        created_at VARCHAR(20) NOT NULL,
        INDEX idx_sl_panel_kind_time (Service_location, kind, created_at),
        INDEX idx_sl_user (id_user),
        INDEX idx_sl_invoice (id_invoice)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log('[sale-ledger-migrate] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'affiliates'");
    $table_exists = ($result && $result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE affiliates (
        description TEXT  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        status_commission varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        Discount varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        price_Discount varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        porsant_one_buy varchar(100),
        id_media varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        banner_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        require_phone_verified VARCHAR(50) NULL,
        min_account_age_hours INT DEFAULT 0,
        daily_referral_cap INT DEFAULT 0,
        monthly_referral_cap INT DEFAULT 0,
        menu_keyboard_mode VARCHAR(50) NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table affiliates: " . mysqli_error($connect));
        }
        $connect->query("INSERT INTO affiliates (description,id_media,status_commission,Discount,porsant_one_buy,banner_text,require_phone_verified,min_account_age_hours,daily_referral_cap,monthly_referral_cap,menu_keyboard_mode) VALUES ('none','none','oncommission','onDiscountaffiliates','off_buy_porsant',NULL,'off_require_phone',0,0,0,'reply_menu')");
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'porsant_one_buy'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD porsant_one_buy VARCHAR(100)");
            $connect->query("UPDATE affiliates SET porsant_one_buy = 'off_buy_porsant'");
            echo "The Discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'Discount'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD Discount VARCHAR(100)");
            $connect->query("UPDATE affiliates SET Discount = 'onDiscountaffiliates'");
            echo "The Discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'price_Discount'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD price_Discount VARCHAR(100)");
            echo "The price_Discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'status_commission'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD status_commission VARCHAR(100)");
            $connect->query("UPDATE affiliates SET status_commission = 'oncommission'");
            echo "The commission field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'banner_text'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD banner_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
            echo "The banner_text field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'require_phone_verified'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD require_phone_verified VARCHAR(50)");
            $connect->query("UPDATE affiliates SET require_phone_verified = 'off_require_phone'");
            echo "The require_phone_verified field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'min_account_age_hours'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD min_account_age_hours INT DEFAULT 0");
            echo "The min_account_age_hours field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'daily_referral_cap'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD daily_referral_cap INT DEFAULT 0");
            echo "The daily_referral_cap field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'monthly_referral_cap'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD monthly_referral_cap INT DEFAULT 0");
            echo "The monthly_referral_cap field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'menu_keyboard_mode'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD menu_keyboard_mode VARCHAR(50)");
            $connect->query("UPDATE affiliates SET menu_keyboard_mode = 'reply_menu'");
            echo "The menu_keyboard_mode field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'affiliate_referral_claims'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE affiliate_referral_claims (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        reagent VARCHAR(30) NOT NULL,
        invitee_id VARCHAR(30) NOT NULL UNIQUE,
        claim_type VARCHAR(50) NOT NULL,
        claim_date DATE NOT NULL,
        time VARCHAR(50) NOT NULL,
        INDEX idx_arc_reagent_date (reagent, claim_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table affiliate_referral_claims: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'shopSetting'");
    $table_exists = ($result->num_rows > 0);
    if ($table_exists) {
        try {
            $rxPkRes = $connect->query("SHOW KEYS FROM shopSetting WHERE Key_name = 'PRIMARY'");
            $rxHasPk = ($rxPkRes && $rxPkRes->num_rows > 0);
            if (!$rxHasPk) {
                $connect->query("DELETE s1 FROM shopSetting s1 JOIN shopSetting s2 ON s1.Namevalue = s2.Namevalue WHERE s1.value = '#7c5cff' AND s2.value <> '#7c5cff'");
                $connect->query("ALTER TABLE shopSetting ADD COLUMN __rid INT AUTO_INCREMENT PRIMARY KEY");
                $connect->query("DELETE s1 FROM shopSetting s1 JOIN shopSetting s2 ON s1.Namevalue = s2.Namevalue AND s1.__rid > s2.__rid");
                $connect->query("ALTER TABLE shopSetting DROP COLUMN __rid");
                $connect->query("ALTER TABLE shopSetting ADD PRIMARY KEY (Namevalue)");
                echo "shopSetting primary key restored ✅";
            }
        } catch (Exception $rxShopPkErr) {
            error_log('[table.php shopSetting pk] ' . $rxShopPkErr->getMessage());
        }
    }
    $agent_cashback = json_encode(array(
        'n' => 0,
        'n2' => 0
    ));
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE shopSetting (
        Namevalue varchar(500) PRIMARY KEY NOT NULL,
        value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table shopSetting: " . mysqli_error($connect));
        }
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customvolmef','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customvolmen','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customvolmen2','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusextra','offextra')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customtimepricef','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customtimepricen','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customtimepricen2','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusdirectpabuy','ondirectbuy')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('minbalancebuybulk','0')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statustimeextra','ontimeextraa')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusdisorder','offdisorder')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statuschangeservice','onstatus')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusshowprice','offshowprice')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('configshow','onconfig')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('backserviecstatus','on')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('chashbackextend','0')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('chashbackextend_agent','$agent_cashback')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('chashbackextend_target','all')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('brand_accent','#7c5cff')");
    } else {
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customvolmef','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customvolmen','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customvolmen2','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusextra','offextra')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusdirectpabuy','ondirectbuy')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('minbalancebuybulk','0')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statustimeextra','ontimeextraa')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customtimepricef','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customtimepricen','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customtimepricen2','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusdisorder','offdisorder')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statuschangeservice','onstatus')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusshowprice','offshowprice')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('configshow','onconfig')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('backserviecstatus','on')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('chashbackextend','0')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('chashbackextend_agent','$agent_cashback')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('chashbackextend_target','all')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('miniapp_short_name','')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('brand_name','')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('brand_mark','')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('brand_logo','')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('brand_accent','#7c5cff')");


    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    if (rxTableColumnExists($connect, 'marzban_panel', 'shop_features') && function_exists('panel_features_snapshot_json')) {
        $rxShopFeaturesSnapshot = panel_features_snapshot_json();
        if (is_string($rxShopFeaturesSnapshot) && $rxShopFeaturesSnapshot !== '') {
            $rxSnapshotEscaped = $connect->real_escape_string($rxShopFeaturesSnapshot);
            $rxPanelsResult = $connect->query("SELECT code_panel, shop_features FROM marzban_panel");
            if ($rxPanelsResult) {
                while ($rxPanelRow = $rxPanelsResult->fetch_assoc()) {
                    $rxExistingFeatures = trim((string) ($rxPanelRow['shop_features'] ?? ''));
                    $rxDecodedFeatures = $rxExistingFeatures !== '' ? json_decode($rxExistingFeatures, true) : null;
                    if (is_array($rxDecodedFeatures)) {
                        continue;
                    }
                    $rxPanelCode = $connect->real_escape_string((string) $rxPanelRow['code_panel']);
                    $connect->query("UPDATE marzban_panel SET shop_features = '{$rxSnapshotEscaped}' WHERE code_panel = '{$rxPanelCode}'");
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('[table.php] shop_features backfill: ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'cancel_service'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE cancel_service (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(500)  NOT NULL,
        username varchar(1000)  NOT NULL,
        description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
        status varchar(1000)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table cancel_service: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'service_other'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE service_other (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(500)  NOT NULL,
        username varchar(1000)  NOT NULL,
        value varchar(1000)  NOT NULL,
        time varchar(200)  NOT NULL,
        price varchar(200)  NOT NULL,
        type varchar(1000)  NOT NULL,
        status varchar(200)  NOT NULL,
        output TEXT  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table service_other: " . mysqli_error($connect));
        }
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM service_other LIKE 'price'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE service_other ADD price VARCHAR(200)");
            echo "The price field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM service_other LIKE 'status'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE service_other ADD status VARCHAR(200)");
            echo "The status field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM service_other LIKE 'output'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE service_other ADD output TEXT");
            echo "The output field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'queued_renewal'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE queued_renewal (
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
        INDEX idx_queued_renewal_username_status (username(191), status(50)))
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table queued_renewal: " . mysqli_error($connect));
        }
    } else {
        rxSafeAddIndex($connect, "queued_renewal", "idx_queued_renewal_username_status", "username", 191);
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'card_number'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE card_number (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cardnumber varchar(500) NOT NULL UNIQUE,
        namecard varchar(1000) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        created_at INT NOT NULL DEFAULT 0,
        INDEX idx_card_active (is_active),
        INDEX idx_card_visible (is_visible))
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table card_number: " . mysqli_error($connect));
        }
    } else {
        rxSafeAddColumn($connect, "card_number", "id", "INT AUTO_INCREMENT UNIQUE");
        rxSafeAddColumn($connect, "card_number", "is_active", "TINYINT(1) NOT NULL DEFAULT 1");
        rxSafeAddColumn($connect, "card_number", "is_visible", "TINYINT(1) NOT NULL DEFAULT 1");
        rxSafeAddColumn($connect, "card_number", "created_at", "INT NOT NULL DEFAULT 0");
        rxSafeAddIndex($connect, "card_number", "idx_card_active", "is_active");
        rxSafeAddIndex($connect, "card_number", "idx_card_visible", "is_visible");
    }

    $columnInfo = $connect->query("SHOW FULL COLUMNS FROM card_number LIKE 'namecard'");
    if ($columnInfo) {
        $column = $columnInfo->fetch_assoc();
        $currentCollation = $column['Collation'] ?? '';
        if (empty($currentCollation) || stripos($currentCollation, 'utf8mb4') === false) {
            $connect->query("ALTER TABLE card_number CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $columnInfo->free();
    }
} catch (Exception $e) {
    error_log('[card_number table] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'card_whitelist'");
    $table_exists = ($result && $result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE card_whitelist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT NOT NULL,
        user_id VARCHAR(64) NOT NULL,
        created_at INT NOT NULL DEFAULT 0,
        UNIQUE KEY unique_card_user (card_id, user_id),
        INDEX idx_user_id (user_id),
        FOREIGN KEY (card_id) REFERENCES card_number(id) ON DELETE CASCADE)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table card_whitelist: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[card_whitelist table] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'user_card_block'");
    $table_exists = ($result && $result->num_rows > 0);
    if (!$table_exists) {
        $connect->query("CREATE TABLE user_card_block (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        card_id INT NOT NULL,
        UNIQUE KEY unique_user_card (user_id, card_id),
        INDEX idx_ucb_user (user_id),
        INDEX idx_ucb_card (card_id))
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
} catch (Exception $e) {
    error_log('[user_card_block table] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'verified_cards'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $connect->query("CREATE TABLE verified_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        last4 VARCHAR(4) NOT NULL,
        created_at INT NOT NULL DEFAULT 0,
        INDEX idx_vc_user (user_id))
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'Requestagent'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE Requestagent (
        id varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY,
        username  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        time  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        Description  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        status  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        type  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table Requestagent: " . mysqli_error($connect));
        }
    } else {
        ensureTableUtf8mb4('Requestagent');
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'topicid'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE topicid (
        report varchar(500) PRIMARY KEY NOT NULL,
        idreport TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table Requestagent: " . mysqli_error($connect));
        }
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','buyreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','otherservice')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','paymentreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','otherreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','reporttest')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','errorreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','porsantreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','reportnight')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','reportcron')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','backupfile')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','receiptreport')");
    } else {
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','buyreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','otherservice')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','paymentreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','otherreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','reporttest')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','errorreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','porsantreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','reportnight')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','reportcron')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','backupfile')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','receiptreport')");
        $connect->query("UPDATE topicid SET idreport = '0' WHERE idreport = '-1' AND report IN ('porsantreport','reportnight','reportcron','backupfile','receiptreport')");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'manualsell'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE manualsell (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codepanel  varchar(100)  NOT NULL,
        codeproduct  varchar(100)  NOT NULL,
        namerecord  varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
        username  varchar(500)  NULL,
        contentrecord  LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
        status  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table manualsell: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $tableName = 'departman';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            idsupport VARCHAR(200) NOT NULL,
            name_departman VARCHAR(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
        $connect->query("INSERT INTO departman (idsupport,name_departman) VALUES ('$adminnumber','☎️ بخش عمومی')");
    }
} catch (PDOException $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {

    $tableName = 'support_message';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Tracking VARCHAR(100) NOT NULL,
            idsupport VARCHAR(100) NOT NULL,
            iduser VARCHAR(100) NOT NULL,
            name_departman VARCHAR(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            result TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            time VARCHAR(200) NOT NULL,
            status ENUM('Answered','Pending','Unseen','Customerresponse','close') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    } else {
        addFieldToTable("support_message", "result", "0", "TEXT");
    }
} catch (PDOException $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    rxSafeAddColumn($connect, "support_message", "seen_by_user", "TINYINT(1) NOT NULL DEFAULT 0");
    rxSafeAddColumn($connect, "support_message", "seen_by_admin", "TINYINT(1) NOT NULL DEFAULT 0");
    rxSafeAddColumn($connect, "support_message", "reply_to_id", "INT(6) UNSIGNED NULL DEFAULT NULL");

    $tableName = 'support_message_reaction';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $connect->query("CREATE TABLE support_message_reaction (
            id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id INT(6) UNSIGNED NOT NULL,
            actor VARCHAR(20) NOT NULL,
            actor_id VARCHAR(100) NOT NULL,
            emoji VARCHAR(20) NOT NULL,
            time VARCHAR(200) NOT NULL,
            UNIQUE KEY uniq_smr_msg_actor (message_id, actor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    rxSafeAddIndex($connect, "support_message_reaction", "idx_smr_message", "message_id");
} catch (Throwable $e) {
    error_log('[table.php support_message extensions] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'wheel_list'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE wheel_list (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user  varchar(200)  NOT NULL,
        time  varchar(200)  NOT NULL,
        first_name  varchar(200)  NOT NULL,
        wheel_code  varchar(200)  NOT NULL,
        price  varchar(200)  NOT NULL,
        wheel_day  DATE  NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table wheel_list: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'botsaz'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE botsaz (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user  varchar(200)  NOT NULL,
        bot_token  varchar(200)  NOT NULL,
        admin_ids  TEXT  NOT NULL,
        username  varchar(200)  NOT NULL,
        setting  TEXT  NULL,
        hide_panel  JSON  NOT NULL,
        time  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table botsaz: " . mysqli_error($connect));
        }
    } else {
        addFieldToTable("botsaz", "hide_panel", "{}", "JSON");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'app'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE app (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name  varchar(200)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        link  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table app: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}


try {
    $result = $connect->query("SHOW TABLES LIKE 'logs_api'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE logs_api (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        header JSON  NULL,
        data JSON  NULL,
        ip  varchar(200)  NOT NULL,
        time  varchar(200)  NOT NULL,
        actions  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table logs_api: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'category'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE category (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        remark varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin");
        if (!$result) {
            error_log("[table.php] table category: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'reagent_report'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE reagent_report (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNIQUE  NOT NULL,
        get_gift BOOL   NOT NULL,
        time varchar(50)  NOT NULL,
        reagent varchar(30)  NOT NULL
        )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin");
        if (!$result) {
            error_log("[table.php] table affiliates: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}


try {
    $paySettingRow = select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select");
    $paySettingValue = is_array($paySettingRow) && isset($paySettingRow['ValuePay']) ? $paySettingRow['ValuePay'] : null;
    $balancemain = is_string($paySettingValue) ? json_decode($paySettingValue, true) : null;
    if (!is_array($balancemain) || !isset($balancemain['f'])) {
        $value = json_encode(array(
            "f" => "1000000",
            "n" => "1000000",
            "n2" => "1000000",
        ));
        $valuemain = json_encode(array(
            "f" => "20000",
            "n" => "20000",
            "n2" => "20000",
        ));
        update("PaySetting", "ValuePay", $value, "NamePay", "maxbalance");
        update("PaySetting", "ValuePay", $valuemain, "NamePay", "minbalance");
    }
    $connect->query("ALTER TABLE `invoice` CHANGE `Volume` `Volume` VARCHAR(200)");
    $connect->query("ALTER TABLE `invoice` CHANGE `price_product` `price_product` VARCHAR(200)");
    $connect->query("ALTER TABLE `invoice` CHANGE `name_product` `name_product` VARCHAR(200)");
    $connect->query("ALTER TABLE `invoice` CHANGE `username` `username` VARCHAR(200)");
    $connect->query("ALTER TABLE `invoice` CHANGE `Service_location` `Service_location` VARCHAR(200)");
    $connect->query("ALTER TABLE `invoice` CHANGE `time_sell` `time_sell` VARCHAR(200)");
    $connect->query("ALTER TABLE marzban_panel MODIFY name_panel VARCHAR(255) COLLATE utf8mb4_bin");
    $connect->query("ALTER TABLE product MODIFY name_product VARCHAR(255) COLLATE utf8mb4_bin");
    $connect->query("ALTER TABLE help MODIFY name_os VARCHAR(500) COLLATE utf8mb4_bin");
} catch (\Throwable $e) {
    error_log('[table.php balance/invoice] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'channels'");
    if ($result && $result->num_rows > 0) {
        $connect->query("ALTER TABLE channels CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $connect->query("ALTER TABLE channels MODIFY remark VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
        $connect->query("ALTER TABLE channels MODIFY linkjoin VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
        $connect->query("ALTER TABLE channels MODIFY link VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
    }
} catch (Exception $e) {
    error_log('[table.php channels charset] ' . $e->getMessage());
}



$hookParams = [
    'url' => "https://$domainhosts/index.php",
];
$secretTok = function_exists('getTelegramExpectedSecretToken') ? getTelegramExpectedSecretToken() : '';
if ($secretTok !== '') {
    $hookParams['secret_token'] = $secretTok;
}
$rxSetHookResp = telegram('setwebhook', $hookParams);
if (!is_array($rxSetHookResp) || empty($rxSetHookResp['ok'])) {
    error_log('setwebhook FAILED: ' . json_encode($rxSetHookResp));
}


try {
    $connect->query("CREATE TABLE IF NOT EXISTS nm_config_stock (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        shelf_id BIGINT UNSIGNED NULL,
        codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        codeproduct VARCHAR(191) NOT NULL DEFAULT 'auto',
        tier VARCHAR(32) NOT NULL DEFAULT 'auto',
        format VARCHAR(32) NOT NULL DEFAULT 'link',
        content MEDIUMTEXT NOT NULL,
        sub_link MEDIUMTEXT NULL,
        status ENUM('active','reserved','delivered','disabled') NOT NULL DEFAULT 'active',
        assigned_user VARCHAR(64) NULL,
        assigned_invoice VARCHAR(64) NULL,
        assigned_mode VARCHAR(64) NULL,
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        reserved_at INT UNSIGNED NULL,
        delivered_at INT UNSIGNED NULL,
        UNIQUE KEY uq_nm_config_stock_content (content(191)),
        KEY idx_nm_stock_lookup (status, codepanel, codeproduct, tier),
        KEY idx_nm_stock_shelf (status, shelf_id),
        KEY idx_nm_stock_invoice (assigned_invoice)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connect->query("CREATE TABLE IF NOT EXISTS nm_stock_shelves (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        source_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        stock_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        category_id VARCHAR(64) NULL,
        category_name VARCHAR(191) NULL,
        codeproduct VARCHAR(191) NOT NULL DEFAULT 'auto',
        product_name VARCHAR(191) NULL,
        volume_gb DECIMAL(10,2) NOT NULL DEFAULT 0,
        service_days INT NOT NULL DEFAULT 0,
        price BIGINT NOT NULL DEFAULT 0,
        status ENUM('active','disabled') NOT NULL DEFAULT 'active',
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at INT UNSIGNED NULL,
        UNIQUE KEY uq_nm_stock_shelf_name_panel (name, source_codepanel),
        KEY idx_nm_stock_shelf_lookup (status, source_codepanel, codeproduct)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connect->query("CREATE TABLE IF NOT EXISTS nm_config_stock_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        stock_id BIGINT UNSIGNED NULL,
        id_user VARCHAR(64) NULL,
        id_invoice VARCHAR(64) NULL,
        action VARCHAR(64) NOT NULL,
        payload MEDIUMTEXT NULL,
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        KEY idx_nm_stock_log_invoice (id_invoice),
        KEY idx_nm_stock_log_user (id_user)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connect->query("CREATE TABLE IF NOT EXISTS nm_stock_product_map (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        source_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        stock_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        codeproduct VARCHAR(191) NOT NULL DEFAULT 'auto',
        category VARCHAR(191) NULL,
        category_id VARCHAR(64) NULL,
        category_name VARCHAR(191) NULL,
        volume_gb DECIMAL(10,2) NOT NULL DEFAULT 0,
        service_days INT NOT NULL DEFAULT 0,
        price BIGINT NOT NULL DEFAULT 0,
        status ENUM('active','disabled') NOT NULL DEFAULT 'active',
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at INT UNSIGNED NULL,
        UNIQUE KEY uq_nm_stock_product_map (source_codepanel, stock_codepanel, codeproduct),
        KEY idx_nm_stock_product_lookup (status, source_codepanel, codeproduct),
        KEY idx_nm_stock_product_category (status, source_codepanel, category)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    rxSafeAddColumn($connect, "nm_config_stock", "shelf_id", "BIGINT UNSIGNED NULL");
    rxSafeAddColumn($connect, "nm_config_stock", "sub_link", "MEDIUMTEXT NULL");
    rxSafeAddColumn($connect, "manualsell", "file_ext", "VARCHAR(20) NULL");
    rxSafeAddColumn($connect, "manualsell", "sub_link", "MEDIUMTEXT NULL");
    rxSafeAddColumn($connect, "manualsell", "group_id", "VARCHAR(40) NULL");
    rxSafeAddColumn($connect, "manualsell", "group_size", "INT NULL");
    rxSafeAddIndex($connect, "manualsell", "idx_manualsell_group", "group_id");
    @$connect->query("ALTER TABLE manualsell MODIFY contentrecord LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    rxSafeAddColumn($connect, "nm_stock_product_map", "category_id", "VARCHAR(64) NULL");
    rxSafeAddColumn($connect, "nm_stock_product_map", "category_name", "VARCHAR(191) NULL");
    rxSafeAddColumn($connect, "invoice", "source_panel_code", "VARCHAR(191) NULL");
    rxSafeAddColumn($connect, "marzban_panel", "national_net_status", "VARCHAR(50) NOT NULL DEFAULT 'off_national_net'");
    rxSafeAddColumn($connect, "marzban_panel", "stock_source_panel", "VARCHAR(191) NULL");
    rxSafeAddColumn($connect, "marzban_panel", "version_panel", "VARCHAR(60) NOT NULL DEFAULT '0'");
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage(), FILE_APPEND);
}


try {

    $result = $connect->query("SHOW TABLES LIKE 'crypto_wallets'");
    $exists = ($result && $result->num_rows > 0);
    if (!$exists) {
        $created = $connect->query("CREATE TABLE crypto_wallets (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            currency VARCHAR(20) NOT NULL,
            network VARCHAR(20) NOT NULL,
            wallet_address VARCHAR(255) NOT NULL,
            label VARCHAR(255) DEFAULT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            min_irt BIGINT NOT NULL DEFAULT 20000,
            max_irt BIGINT NOT NULL DEFAULT 100000000,
            cashback_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
            rate_irt_override DECIMAL(20,4) DEFAULT NULL,
            wallet_memo VARCHAR(255) NULL,
            verification_mode ENUM('automated','manual') NOT NULL DEFAULT 'automated',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_currency (currency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$created) {
            error_log("[table.php] crypto_wallets: " . mysqli_error($connect));
        }
        $seedCurrencies = [
            ['TRX',         'TRON', '', 'ترون (TRX)'],
            ['TON',         'TON',  '', 'تون (TON)'],
            ['USDT_TRC20',  'TRON', '', 'تتر روی شبکه ترون (USDT-TRC20)'],
            ['USDT_TON',    'TON',  '', 'تتر روی شبکه تون (USDT-TON)'],
        ];
        $seedStmt = $connect->prepare(
            "INSERT IGNORE INTO crypto_wallets (currency, network, wallet_address, label, enabled) VALUES (?,?,?,?,0)"
        );
        if ($seedStmt) {
            foreach ($seedCurrencies as $row) {
                $seedStmt->bind_param('ssss', $row[0], $row[1], $row[2], $row[3]);
                $seedStmt->execute();
            }
            $seedStmt->close();
        }
    } else {
        rxSafeAddColumn($connect, "crypto_wallets", "network",            "VARCHAR(20) NOT NULL DEFAULT 'TRON'");
        rxSafeAddColumn($connect, "crypto_wallets", "label",              "VARCHAR(255) DEFAULT NULL");
        rxSafeAddColumn($connect, "crypto_wallets", "enabled",            "TINYINT(1) NOT NULL DEFAULT 1");
        rxSafeAddColumn($connect, "crypto_wallets", "min_irt",            "BIGINT NOT NULL DEFAULT 20000");
        rxSafeAddColumn($connect, "crypto_wallets", "max_irt",            "BIGINT NOT NULL DEFAULT 100000000");
        rxSafeAddColumn($connect, "crypto_wallets", "cashback_percent",   "DECIMAL(6,2) NOT NULL DEFAULT 0");
        rxSafeAddColumn($connect, "crypto_wallets", "rate_irt_override",  "DECIMAL(20,4) DEFAULT NULL");
        rxSafeAddColumn($connect, "crypto_wallets", "created_at",         "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        rxSafeAddColumn($connect, "crypto_wallets", "updated_at",         "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        rxSafeAddColumn($connect, "crypto_wallets", "wallet_memo",        "VARCHAR(255) NULL");
        rxSafeAddColumn($connect, "crypto_wallets", "verification_mode",  "ENUM('automated','manual') NOT NULL DEFAULT 'automated'");
    }


    rxSafeAddColumn($connect, "Payment_report", "crypto_currency",        "VARCHAR(20) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_network",         "VARCHAR(20) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_amount",          "DECIMAL(30,9) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_wallet_to",       "VARCHAR(255) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_tx_hash",         "VARCHAR(255) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_hash_at",         "BIGINT NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_check_count",     "INT NOT NULL DEFAULT 0");
    rxSafeAddColumn($connect, "Payment_report", "crypto_last_error",      "TEXT NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_sender_address",  "VARCHAR(255) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_iranian_mode",    "TINYINT(1) NOT NULL DEFAULT 0");
    rxSafeAddColumn($connect, "Payment_report", "crypto_rate_irt",        "DECIMAL(20,4) NULL");
    rxSafeAddColumn($connect, "Payment_report", "tracking_code",           "VARCHAR(100) NULL");
    rxSafeAddColumn($connect, "Payment_report", "source",                 "VARCHAR(20) NULL");
    rxSafeAddColumn($connect, "Payment_report", "tronado_payment_url",    "VARCHAR(500) NULL");
    rxSafeAddColumn($connect, "Payment_report", "tonpay_invoice_id",      "VARCHAR(64) NULL");
    rxSafeAddColumn($connect, "Payment_report", "tonpay_invoice_url",     "VARCHAR(500) NULL");
    rxSafeAddColumn($connect, "Payment_report", "cubepay_authority",      "VARCHAR(64) NULL");
    rxSafeAddColumn($connect, "Payment_report", "cubepay_payment_link",   "VARCHAR(500) NULL");
    rxSafeAddColumn($connect, "Payment_report", "cubepay_method",         "VARCHAR(20) NULL");
    rxSafeAddColumn($connect, "Payment_report", "blupal_invoice_id",      "VARCHAR(64) NULL");
    rxSafeAddColumn($connect, "Payment_report", "blupal_payment_link",    "VARCHAR(500) NULL");
    rxSafeAddColumn($connect, "Payment_report", "atlaspay_order_id",      "VARCHAR(64) NULL");
    rxSafeAddColumn($connect, "Payment_report", "atlaspay_tracking_code", "VARCHAR(64) NULL");
    rxSafeAddColumn($connect, "Payment_report", "atlaspay_payment_url",   "VARCHAR(500) NULL");

    try {
        $haveBlupalIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_blupal_invoice'");
        if ($haveBlupalIdx && $haveBlupalIdx->num_rows === 0) {
            @$connect->query("ALTER TABLE Payment_report ADD INDEX idx_pr_blupal_invoice (blupal_invoice_id)");
        }
    } catch (Throwable $e) {  }

    try {
        $haveAtlasIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_atlaspay_order'");
        if ($haveAtlasIdx && $haveAtlasIdx->num_rows === 0) {
            @$connect->query("ALTER TABLE Payment_report ADD INDEX idx_pr_atlaspay_order (atlaspay_order_id)");
        }
    } catch (Throwable $e) {  }

    try {
        $haveSrcIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_source_user'");
        if ($haveSrcIdx && $haveSrcIdx->num_rows === 0) {
            @$connect->query("ALTER TABLE Payment_report ADD INDEX idx_pr_source_user (source, id_user)");
        }
    } catch (Throwable $e) {  }

    try {
        $haveCubeIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_cubepay_authority'");
        if ($haveCubeIdx && $haveCubeIdx->num_rows === 0) {
            @$connect->query("ALTER TABLE Payment_report ADD INDEX idx_pr_cubepay_authority (cubepay_authority)");
        }
    } catch (Throwable $e) {  }

    try {
        $haveIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_crypto_pending'");
        if ($haveIdx && $haveIdx->num_rows === 0) {
            $connect->query("ALTER TABLE Payment_report ADD INDEX idx_pr_crypto_pending (payment_Status, crypto_currency)");
        }
    } catch (Throwable $e) {  }

    try {
        $haveHashIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'uniq_pr_crypto_hash'");
        if ($haveHashIdx && $haveHashIdx->num_rows === 0) {
            @$connect->query("ALTER TABLE Payment_report ADD UNIQUE KEY uniq_pr_crypto_hash (crypto_tx_hash)");
        }
    } catch (Throwable $e) {
        error_log('[table.php] could not add unique key on crypto_tx_hash: ' . $e->getMessage());
    }

    try {
        $haveSenderIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_crypto_sender'");
        if ($haveSenderIdx && $haveSenderIdx->num_rows === 0) {
            $connect->query("ALTER TABLE Payment_report ADD INDEX idx_pr_crypto_sender (crypto_currency, crypto_sender_address)");
        }
    } catch (Throwable $e) {  }

    try {
        $connect->query("CREATE TABLE IF NOT EXISTS crypto_verified_hashes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tx_hash VARCHAR(255) NOT NULL,
            currency VARCHAR(20) NOT NULL,
            network VARCHAR(20) NOT NULL,
            wallet_to VARCHAR(255) NULL,
            sender_address VARCHAR(255) NULL,
            amount_coin DECIMAL(30,9) NULL,
            amount_irr BIGINT NULL,
            order_id VARCHAR(64) NOT NULL,
            user_id VARCHAR(64) NULL,
            verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verification_source VARCHAR(40) NOT NULL DEFAULT 'auto_cron',
            UNIQUE KEY uniq_cvh_hash (tx_hash),
            INDEX idx_cvh_order (order_id),
            INDEX idx_cvh_user (user_id),
            INDEX idx_cvh_currency (currency),
            INDEX idx_cvh_verified_at (verified_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('[table.php] crypto_verified_hashes create: ' . $e->getMessage());
    }

    try {
        $connect->query("CREATE TABLE IF NOT EXISTS wallet_ledger (
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
            INDEX idx_wl_user (id_user, created_at),
            INDEX idx_wl_order (id_order),
            INDEX idx_wl_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('[table.php] wallet_ledger create: ' . $e->getMessage());
    }

    try {
        $connect->query("CREATE TABLE IF NOT EXISTS PublicLog_Queue (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            chat_id VARCHAR(600) NOT NULL,
            text TEXT NOT NULL,
            reply_markup TEXT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL,
            INDEX idx_plq_pending (sent_at, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('[table.php] PublicLog_Queue create: ' . $e->getMessage());
    }

    try {
        $connect->query("CREATE TABLE IF NOT EXISTS crypto_sender_locks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_address VARCHAR(255) NOT NULL,
            currency VARCHAR(20) NOT NULL,
            telegram_user_id VARCHAR(64) NOT NULL,
            first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NULL DEFAULT NULL,
            use_count INT UNSIGNED NOT NULL DEFAULT 1,
            UNIQUE KEY uniq_sender_currency (sender_address, currency),
            INDEX idx_sender_user (telegram_user_id),
            INDEX idx_sender_first_seen (first_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('[table.php] crypto_sender_locks create: ' . $e->getMessage());
    }


    $cryptoSettings = [
        ['cryptocheck_cashback_TRX',      '0'],
        ['cryptocheck_cashback_TON',      '0'],
        ['cryptocheck_cashback_USDT_TRC20','0'],
        ['cryptocheck_cashback_USDT_TON', '0'],
        ['cryptocheck_tonapi_key',        ''],
        ['cryptocheck_trongrid_key',      ''],


        ['cryptocheck_amount_tolerance',  '0'],
        ['cryptocheck_overpay_tolerance', '0'],
        ['cryptocheck_max_retries',       '10'],
        ['cryptocheck_invoice_ttl',       '1800'],
        ['cryptocheck_invoice_ttl_iranian','1800'],
        ['cryptocheck_iranian_tolerance', '2'],
        ['cryptocheck_iranian_threshold', '1'],
    ];
    foreach ($cryptoSettings as $cs) {
        $ins = $connect->prepare("INSERT IGNORE INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)");
        if ($ins) {
            $ins->bind_param('ss', $cs[0], $cs[1]);
            $ins->execute();
            $ins->close();
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', '[table.php cryptocheck] ' . $e->getMessage() . "\n", FILE_APPEND);
}


try {
    $result = $connect->query("SHOW TABLES LIKE 'premium_emojis'");
    $exists = ($result && $result->num_rows > 0);
    if (!$exists) {
        $created = $connect->query("CREATE TABLE premium_emojis (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            emoji VARCHAR(50) NOT NULL,
            custom_emoji_id VARCHAR(100) NOT NULL,
            label VARCHAR(255) NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_emoji_cid (emoji, custom_emoji_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$created) {
            error_log("[table.php] premium_emojis: " . mysqli_error($connect));
        }
    } else {
        rxSafeAddColumn($connect, "premium_emojis", "emoji",           "VARCHAR(50) NOT NULL");
        rxSafeAddColumn($connect, "premium_emojis", "custom_emoji_id", "VARCHAR(100) NOT NULL");
        rxSafeAddColumn($connect, "premium_emojis", "label",           "VARCHAR(255) NULL");
        rxSafeAddColumn($connect, "premium_emojis", "created_at",      "INT UNSIGNED NOT NULL DEFAULT 0");
        rxSafeAddColumn($connect, "premium_emojis", "updated_at",      "INT UNSIGNED NOT NULL DEFAULT 0");
    }


    try {
        $oldKey = $connect->query("SHOW INDEX FROM premium_emojis WHERE Key_name='uniq_emoji'");
        if ($oldKey && $oldKey->num_rows > 0) {
            $connect->query("ALTER TABLE premium_emojis DROP INDEX uniq_emoji");
        }
        $newKey = $connect->query("SHOW INDEX FROM premium_emojis WHERE Key_name='uniq_emoji_cid'");
        if (!$newKey || $newKey->num_rows === 0) {
            $connect->query("ALTER TABLE premium_emojis ADD UNIQUE KEY uniq_emoji_cid (emoji, custom_emoji_id)");
        }
    } catch (Exception $idxErr) {
        error_log("[table.php] premium_emojis index migration: " . $idxErr->getMessage());
    }

    try {
        $connect->query("ALTER TABLE premium_emojis MODIFY emoji VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
        $connect->query("ALTER TABLE premium_emojis MODIFY custom_emoji_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
        $connect->query("ALTER TABLE premium_emojis MODIFY label VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
        $connect->query("DELETE FROM premium_emojis WHERE emoji = '' OR emoji IS NULL");
    } catch (Exception $rxPemUtf8Err) {
        error_log("[table.php] premium_emojis utf8mb4 migration: " . $rxPemUtf8Err->getMessage());
    }
} catch (Exception $e) {
    file_put_contents('error_log', '[table.php premium_emojis] ' . $e->getMessage() . "\n", FILE_APPEND);
}


try {
    addFieldToTable("setting", "premium_emoji_status", "0", "VARCHAR(20)");
    addFieldToTable("setting", "keyboard_styles_all", "{}", "TEXT");
    addFieldToTable("setting", "forced_miniapp_mode", "0", "VARCHAR(20)");
    addFieldToTable("setting", "miniapp_ticket_mode", "0", "VARCHAR(20)");
} catch (Exception $e) {
    file_put_contents('error_log', '[table.php setting kb cols] ' . $e->getMessage() . "\n", FILE_APPEND);
}

try {
    addFieldToTable("setting", "redis_enabled", "0", "VARCHAR(20)");
} catch (Exception $e) {
    file_put_contents('error_log', '[table.php setting redis_enabled] ' . $e->getMessage() . "\n", FILE_APPEND);
}

try {
    addFieldToTable("setting", "banner_start_status", "0", "VARCHAR(20)");
    addFieldToTable("setting", "banner_start_file_id", "", "VARCHAR(255)");
    addFieldToTable("setting", "banner_cart_status", "0", "VARCHAR(20)");
    addFieldToTable("setting", "banner_cart_file_id", "", "VARCHAR(255)");
    addFieldToTable("setting", "banner_buy_status", "0", "VARCHAR(20)");
    addFieldToTable("setting", "banner_buy_file_id", "", "VARCHAR(255)");
} catch (Exception $e) {
    file_put_contents('error_log', '[table.php setting banner cols] ' . $e->getMessage() . "\n", FILE_APPEND);
}

try {
    $connect->query("DELETE FROM textbot WHERE id_text IN ('miniapp_suggest_2','miniapp_suggest_3','miniapp_suggest_4')");
} catch (Exception $e) {}



try {
    rxSafeAddIndex($connect, "invoice",         "idx_invoice_id_user",  "id_user",          191);
    rxSafeAddIndex($connect, "invoice",         "idx_invoice_username", "username",         191);
    rxSafeAddIndex($connect, "invoice",         "idx_invoice_location", "Service_location", 191);
    rxSafeAddIndex($connect, "invoice",         "idx_invoice_status",   "Status",            60);
    rxSafeAddUniqueIndex($connect, "invoice", "uniq_invoice_username", ["username"], ["username" => 191]);
    rxSafeAddIndex($connect, "user",            "idx_user_checkstatus", "checkstatus");
    rxSafeAddIndex($connect, "support_message", "idx_sm_tracking",      "Tracking");
    rxSafeAddIndex($connect, "support_message", "idx_sm_iduser",        "iduser");
    rxSafeAddIndex($connect, "remnawave_users", "idx_rw_id_user",       "id_user",          191);
    rxSafeAddIndex($connect, "service_other",   "idx_so_username",      "username",         191);
    rxSafeAddIndex($connect, "wheel_list",      "idx_wl_id_user",       "id_user",          191);

    rxSafeAddColumn($connect, "wheel_list", "wheel_day", "DATE NULL");
    rxSafeAddUniqueIndex($connect, "wheel_list", "uniq_wl_user_day", ["id_user", "wheel_day"]);

    rxSafeAddIndex($connect, "DiscountSell",     "idx_ds_code",        "codeDiscount",     191);
    rxSafeAddIndex($connect, "Discount",         "idx_disc_code",      "code",             191);
    rxSafeAddIndex($connect, "Giftcodeconsumed", "idx_gcc_id_user",    "id_user",          191);
    rxSafeAddIndex($connect, "botsaz",           "idx_botsaz_token",   "bot_token",        191);
    rxSafeAddIndex($connect, "manualsell",       "idx_ms_codepanel",   "codepanel");
    rxSafeAddIndex($connect, "manualsell",       "idx_ms_username",    "username",         191);
    rxSafeAddIndex($connect, "remnawave_users",  "idx_rw_username",    "username",         191);
    rxSafeAddIndex($connect, "remnawave_users",  "idx_rw_panel",       "name_panel",       191);
    rxSafeAddIndex($connect, "service_other",    "idx_so_id_user",     "id_user",          191);
    rxSafeAddIndex($connect, "cancel_service",   "idx_cs_username",    "username",         191);
} catch (Throwable $e) {
    error_log('[table.php perf indexes] ' . $e->getMessage());
}


try {
    $connect->query("CREATE TABLE IF NOT EXISTS processed_updates (
        update_id BIGINT UNSIGNED PRIMARY KEY,
        processed_at INT UNSIGNED NOT NULL,
        KEY idx_processed_updates_time (processed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    rxSafeAddIndex($connect, 'processed_updates', 'idx_processed_updates_time', 'processed_at');

    $connect->query("CREATE TABLE IF NOT EXISTS cron_runtime_state (
        job_key VARCHAR(255) PRIMARY KEY,
        last_run BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        unit VARCHAR(20) NOT NULL DEFAULT 'minute',
        value INT(10) UNSIGNED NOT NULL DEFAULT 1,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $rxOwnTables = [
        'user','help','setting','admin','channels','marzban_panel','remnawave_users','remnawave_nodes_cache',
        'product','invoice','Payment_report','Discount','Giftcodeconsumed','textbot','PaySetting','DiscountSell',
        'affiliates','shopSetting','cancel_service','service_other','card_number','Requestagent','topicid',
        'manualsell','departman','support_message','wheel_list','botsaz','app','logs_api','category',
        'reagent_report','crypto_wallets','crypto_verified_hashes','crypto_sender_locks','premium_emojis',
        'nm_config_stock','nm_stock_shelves','nm_config_stock_log','nm_stock_product_map','cron_runtime_state','processed_updates',
    ];
    $rxEngRes = $connect->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE IS NOT NULL AND ENGINE <> 'InnoDB'");
    if ($rxEngRes) {
        while ($rxEngRow = $rxEngRes->fetch_assoc()) {
            $rxEngTable = (string) ($rxEngRow['TABLE_NAME'] ?? '');
            if ($rxEngTable === '' || !in_array($rxEngTable, $rxOwnTables, true)) {
                continue;
            }
            $rxEngSafe = preg_replace('/[^A-Za-z0-9_]/', '', $rxEngTable);
            try {
                $connect->query("ALTER TABLE `{$rxEngSafe}` ENGINE=InnoDB");
            } catch (Throwable $e) {
                error_log("[table.php] engine->InnoDB {$rxEngSafe}: " . $e->getMessage());
            }
        }
    }
} catch (Throwable $e) {
    error_log('[table.php engine guard] ' . $e->getMessage());
}







try {
    $rxSeedRes = $connect->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
    $rxSeedRow = ($rxSeedRes && $rxSeedRes->num_rows > 0) ? $rxSeedRes->fetch_assoc() : null;
    $rxSeedRaw = is_array($rxSeedRow) ? (string)($rxSeedRow['keyboard_styles_all'] ?? '') : '';
    $rxSeedTrim = trim($rxSeedRaw);

    if ($rxSeedTrim === '' || $rxSeedTrim === '[]' || $rxSeedTrim === 'null') {
        $rxSeedUpd = $connect->prepare("UPDATE setting SET keyboard_styles_all = '{}'");
        if ($rxSeedUpd) {
            $rxSeedUpd->execute();
            $rxSeedUpd->close();
        }
    } elseif ($rxSeedTrim !== '{}' && function_exists('rx_getKeyboardDefaultStyles')) {
        $rxSeedExisting = json_decode($rxSeedRaw, true);
        $rxSeedDefaults = rx_getKeyboardDefaultStyles();
        if (is_array($rxSeedExisting) && is_array($rxSeedDefaults)) {
            $rxSeedClean = [];
            if (array_key_exists('_use_defaults', $rxSeedExisting)) {
                $rxSeedClean['_use_defaults'] = $rxSeedExisting['_use_defaults'];
            }
            foreach ($rxSeedExisting as $rxSecKey => $rxSecPairs) {
                if ($rxSecKey === '_use_defaults') { continue; }
                if (!is_array($rxSecPairs)) {
                    $rxSeedClean[$rxSecKey] = $rxSecPairs;
                    continue;
                }
                $rxKeptPairs = [];
                foreach ($rxSecPairs as $rxBtnKey => $rxBtnVal) {
                    $rxFactory = $rxSeedDefaults[$rxSecKey][$rxBtnKey] ?? null;
                    if ($rxFactory === null || (string)$rxFactory !== (string)$rxBtnVal) {
                        $rxKeptPairs[$rxBtnKey] = $rxBtnVal;
                    }
                }
                if (!empty($rxKeptPairs)) {
                    $rxSeedClean[$rxSecKey] = $rxKeptPairs;
                }
            }
            $rxSeedCleanJson = json_encode($rxSeedClean, JSON_UNESCAPED_UNICODE);
            if ($rxSeedCleanJson !== false && $rxSeedCleanJson !== $rxSeedRaw) {
                $rxSeedUpd = $connect->prepare("UPDATE setting SET keyboard_styles_all = ?");
                if ($rxSeedUpd) {
                    $rxSeedUpd->bind_param('s', $rxSeedCleanJson);
                    $rxSeedUpd->execute();
                    $rxSeedUpd->close();
                }
            }
        }
    }
} catch (Exception $rxSeedErr) {
    file_put_contents('error_log', '[table.php kb defaults cleanup] ' . $rxSeedErr->getMessage() . "\n", FILE_APPEND);
}

