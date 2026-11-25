<?php

namespace Faoxima\Core\Database;

use PDO;
use PDOException;
use Throwable;
use mysqli;
use mysqli_result;

final class Db
{
    /**
     * Whether the application runtime (config + function layer) has been initialised.
     *
     * Before the migration the DB helpers were global functions defined only after `function.php` had loaded, so
     * `function_exists('select')` doubled as "the runtime is up". These methods are always declared (autoloaded),
     * so declaration alone must not be used for that check.
     */
    public static function runtimeReady(): bool
    {
        return defined('RX_APP_RUNTIME_LOADED') || function_exists('select') || function_exists('getDatabaseConnection');
    }

    public static function getDatabaseConnection()
    {
        static $cachedPdo = null;

        if ($cachedPdo instanceof PDO) {
            return $cachedPdo;
        }

        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $cachedPdo = $GLOBALS['pdo'];
            return $cachedPdo;
        }

        $dsn = $GLOBALS['dsn'] ?? null;
        $username = $GLOBALS['usernamedb'] ?? null;
        $password = $GLOBALS['passworddb'] ?? null;
        $options = $GLOBALS['options'] ?? [];

        if (!is_string($dsn) || trim($dsn) === '') {
            // config.php already logs once-per-hour about empty credentials when the
            // installer is gone; logging here too just duplicates that signal. We
            // suppress this branch unless DB creds are filled (meaning the DSN
            // string itself was somehow corrupted at runtime — a real anomaly).
            $rxDbname = $GLOBALS['dbname'] ?? '';
            $rxUser   = $GLOBALS['usernamedb'] ?? '';
            if (is_string($rxDbname) && $rxDbname !== '' && is_string($rxUser) && $rxUser !== '') {
                $rxDsnMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_dsn_missing.flag';
                if (!is_file($rxDsnMarker) || (time() - (int) @filemtime($rxDsnMarker)) > 3600) {
                    error_log('getDatabaseConnection: DSN is not configured (creds present — check config.php).');
                    @touch($rxDsnMarker);
                }
            }
            return null;
        }

        try {
            $username = (string) $username;
            $password = (string) $password;
            $options = is_array($options) ? $options : [];

            $newPdo = function_exists('rx_connect_pdo')
                ? rx_connect_pdo($dsn, $username, $password, $options)
                : new PDO($dsn, $username, $password, $options);
            $GLOBALS['pdo'] = $newPdo;
            $cachedPdo = $newPdo;
            return $cachedPdo;
        } catch (PDOException $e) {
            error_log('getDatabaseConnection: Unable to create PDO instance. ' . $e->getMessage());
            return null;
        }
    }

    public static function applyConnectionPlaceholders($template, $subscriptionLink, $configList)
    {
        $trimmedSubscription = trim((string) $subscriptionLink);
        $trimmedConfigList = trim((string) $configList);

        $connectionSections = [];
        $configSection = '';
        $linksSection = '';

        if ($trimmedSubscription !== '') {
            $configSection = "🔗 لینک اتصال:\n\n<code>" . htmlspecialchars($trimmedSubscription, ENT_QUOTES, 'UTF-8') . "</code>";
            $connectionSections['config'] = $configSection;
        }

        $rxConfigListIsMultiLine = strpos($trimmedConfigList, "\n") !== false;
        if ($trimmedConfigList !== '') {
            $linksSection = $rxConfigListIsMultiLine ? "🔐 کانفیگ اشتراک :" : "🔐 کانفیگ اشتراک :\n\n<code>" . htmlspecialchars($trimmedConfigList, ENT_QUOTES, 'UTF-8') . "</code>";
            $connectionSections['links'] = $linksSection;
        }

        $connectionLinksBlock = implode("\n\n", array_values($connectionSections));
        if ($connectionLinksBlock !== '') {
            $connectionLinksBlock .= "\n";
        }

        $hasConnectionLinksPlaceholder = strpos($template, '{connection_links}') !== false;
        $hasConfigPlaceholder = strpos($template, '{config}') !== false;
        $hasLinksPlaceholder = strpos($template, '{links}') !== false;

        $placeholderLabels = [
            '{config}' => [
                '🔗 لینک اتصال:',
                '🔗 لینک اتصال :',
                'لینک اتصال:',
                'لینک اتصال :',
            ],
            '{links}' => [
                '🔐 کانفیگ اشتراک:',
                '🔐 کانفیگ اشتراک :',
                'لینک اشتراک:',
                'لینک اشتراک :',
            ],
        ];

        $replacePlaceholder = function ($templateValue, $placeholder, $replacement) use ($placeholderLabels) {
            $wrappedPlaceholder = "<code>{$placeholder}</code>";
            $labels = $placeholderLabels[$placeholder] ?? [];
            $placeholderPattern = '(?:' . preg_quote($placeholder, '/') . '|' . preg_quote($wrappedPlaceholder, '/') . ')';

            foreach ($labels as $label) {
                $labelPattern = preg_quote($label, '/');
                $pattern = '/(^|\R)[^\S\r\n]*' . $labelPattern . '[^\S\r\n]*(?:\r?\n)?[^\S\r\n]*' . $placeholderPattern . '/u';
                $updatedTemplate = preg_replace($pattern, '$1' . $replacement, $templateValue, 1, $count);
                if ($count > 0) {
                    return $updatedTemplate;
                }
            }

            if (strpos($templateValue, $wrappedPlaceholder) !== false) {
                return str_replace($wrappedPlaceholder, $replacement, $templateValue);
            }

            return str_replace($placeholder, $replacement, $templateValue);
        };

        if ($hasConnectionLinksPlaceholder) {
            if ($connectionLinksBlock === '') {
                $orphanLabels = [
                    'اطلاعات سرویس :',
                    'اطلاعات سرویس:',
                    '📌 اطلاعات سرویس :',
                    '📌 اطلاعات سرویس:',
                ];
                foreach ($orphanLabels as $orphanLabel) {
                    $orphanPattern = '/(^|\R)[^\S\r\n]*' . preg_quote($orphanLabel, '/') . '[^\S\r\n]*(?:\r?\n)?[^\S\r\n]*\{connection_links\}/u';
                    $template = preg_replace($orphanPattern, '$1{connection_links}', $template, 1, $orphanCount);
                    if ($orphanCount > 0) {
                        break;
                    }
                }
            }
            $template = str_replace('{connection_links}', $connectionLinksBlock, $template);

            if ($hasConfigPlaceholder) {
                $configReplacement = $configSection;
                if ($configReplacement !== '' && $linksSection !== '') {
                    $configReplacement .= "\n\n";
                }
                $template = $replacePlaceholder($template, '{config}', $configReplacement);
            }

            if ($hasLinksPlaceholder) {
                $template = $replacePlaceholder($template, '{links}', $linksSection);
            }
        } elseif ($hasConfigPlaceholder || $hasLinksPlaceholder) {
            if ($hasConfigPlaceholder && $hasLinksPlaceholder) {
                $configReplacement = $configSection;
                if ($configReplacement !== '' && $linksSection !== '') {
                    $configReplacement .= "\n\n";
                }

                $template = $replacePlaceholder($template, '{config}', $configReplacement);
                $template = $replacePlaceholder($template, '{links}', $linksSection);
            } elseif ($hasConfigPlaceholder) {
                $template = $replacePlaceholder($template, '{config}', $connectionLinksBlock);
            } else {
                $template = $replacePlaceholder($template, '{links}', $connectionLinksBlock);
            }
        }

        if (strpos($template, '{links2}') !== false) {
            $template = str_replace('{links2}', $trimmedSubscription, $template);
        }

        $template = preg_replace("/\n{3,}/u", "\n\n", $template);

        return $template;
    }

    public static function ensureTableUtf8mb4($table)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            $currentCollation = $stmt->fetchColumn();

            if ($currentCollation === false) {
                error_log("Failed to detect current collation for table {$table}");
                return false;
            }

            if (stripos((string) $currentCollation, 'utf8mb4') === 0) {
                return true;
            }

            $pdo->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return true;
        } catch (PDOException $e) {
            error_log('Failed to convert table to utf8mb4: ' . $e->getMessage());
            return false;
        }
    }

    public static function ensureCardNumberTableSupportsUnicode()
    {
        global $connect;

        if (!isset($connect) || !($connect instanceof mysqli)) {
            return;
        }

        try {
            if (method_exists($connect, 'character_set_name') && $connect->character_set_name() !== 'utf8mb4') {
                if (!$connect->set_charset('utf8mb4')) {
                    error_log('Failed to enforce utf8mb4 charset on mysqli connection: ' . $connect->error);
                }
            }

            if (!$connect->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'")) {
                error_log('Failed to execute SET NAMES utf8mb4 for card_number table: ' . $connect->error);
            }

            $createQuery = "CREATE TABLE IF NOT EXISTS card_number (" .
                "cardnumber varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY," .
                "namecard varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (!$connect->query($createQuery)) {
                error_log('Failed to create card_number table with utf8mb4 charset: ' . $connect->error);
            }

            self::ensureTableUtf8mb4('card_number');

            $columnInfo = $connect->query("SHOW FULL COLUMNS FROM card_number WHERE Field IN ('cardnumber', 'namecard')");
            if ($columnInfo instanceof mysqli_result) {
                while ($column = $columnInfo->fetch_assoc()) {
                    $collation = $column['Collation'] ?? '';
                    if (!is_string($collation) || stripos($collation, 'utf8mb4') === false) {
                        $field = $column['Field'];
                        $type = $field === 'cardnumber' ? 'varchar(500)' : 'varchar(1000)';
                        $alter = sprintf(
                            "ALTER TABLE card_number MODIFY %s %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci%s",
                            $field,
                            $type,
                            $field === 'cardnumber' ? ' PRIMARY KEY' : ' NOT NULL'
                        );
                        if (!$connect->query($alter)) {
                            error_log('Failed to update card_number column collation: ' . $connect->error);
                        }
                    }
                }
                $columnInfo->free();
            } else {
                error_log('Unable to inspect card_number column collations: ' . $connect->error);
            }
        } catch (\Throwable $e) {
            error_log('Unexpected error while ensuring card_number utf8mb4 compatibility: ' . $e->getMessage());
        }
    }

    public static function normaliseUpdateValue($value)
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    public static function determineColumnTypeFromValue($value)
    {
        if (is_bool($value)) {
            return 'TINYINT(1)';
        }

        if (is_int($value)) {
            return 'INT(11)';
        }

        if (is_float($value)) {
            return 'DOUBLE';
        }

        if ($value === null) {
            return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        if (is_string($value)) {
            if (function_exists('mb_strlen')) {
                $length = mb_strlen($value, 'UTF-8');
            } else {
                $length = strlen($value);
            }

            if ($length <= 191) {
                return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            }

            if ($length <= 500) {
                return 'VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            }

            return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public static function ensureColumnExistsForUpdate($tableName, $fieldName, $valueSample = null)
    {
        global $pdo;

        static $checkedColumns = [];

        $cacheKey = $tableName . '.' . $fieldName;
        if (isset($checkedColumns[$cacheKey])) {
            return;
        }

        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $stmt->execute([$tableName, $fieldName]);
            if ((int) $stmt->fetchColumn() > 0) {
                $checkedColumns[$cacheKey] = true;
                return;
            }

            $datatype = self::determineColumnTypeFromValue($valueSample);

            $defaultValue = null;
            if (is_bool($valueSample)) {
                $defaultValue = $valueSample ? '1' : '0';
            } elseif (is_scalar($valueSample) && $valueSample !== null) {
                $defaultValue = (string) $valueSample;
            }

            self::addFieldToTable($tableName, $fieldName, $defaultValue, $datatype);
            $checkedColumns[$cacheKey] = true;
        } catch (PDOException $e) {
            error_log('Failed to ensure column exists: ' . $e->getMessage());
            $checkedColumns[$cacheKey] = true;
        }
    }

    public static function rx_update_missing_row_insert($table, $field, $valueToStore, $whereField, $whereValueToStore): array
    {
        $columns = [$field];
        $values = [$valueToStore];
        if ($field !== $whereField) {
            $columns[] = $whereField;
            $values[] = $whereValueToStore;
        }
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', array_map(function ($column) {
            return "`$column`";
        }, $columns));

        return ["INSERT INTO $table ($columnList) VALUES ($placeholders)", $values];
    }

    public static function update($table, $field, $newValue, $whereField = null, $whereValue = null)
    {
        global $pdo;

        $valueToStore = self::normaliseUpdateValue($newValue);
        $whereValueToStore = $whereField !== null ? self::normaliseUpdateValue($whereValue) : null;

        $executeUpdate = function ($value) use ($pdo, $table, $field, $whereField, $whereValueToStore) {
            if ($whereField !== null) {
                $stmt = $pdo->prepare("UPDATE $table SET $field = ? WHERE $whereField = ?");
                $stmt->execute([$value, $whereValueToStore]);
            } else {
                $stmt = $pdo->prepare("UPDATE $table SET $field = ?");
                $stmt->execute([$value]);
            }

            return isset($stmt) ? $stmt->rowCount() : 0;
        };

        $affectedRows = 0;

        try {
            $affectedRows = $executeUpdate($valueToStore);
        } catch (PDOException $e) {
            $rxMissingColumn = (string) $e->getCode() === '42S22'
                || (int) ($e->errorInfo[1] ?? 0) === 1054
                || stripos($e->getMessage(), 'Unknown column') !== false;
            if ($rxMissingColumn) {
                self::ensureColumnExistsForUpdate($table, $field, $valueToStore);
                if ($whereField !== null) {
                    self::ensureColumnExistsForUpdate($table, $whereField, $whereValueToStore);
                }
                $affectedRows = $executeUpdate($valueToStore);
            } elseif (strpos($e->getMessage(), 'Incorrect string value') !== false) {
                $tableConverted = self::ensureTableUtf8mb4($table);
                if ($tableConverted) {
                    try {
                        $affectedRows = $executeUpdate($valueToStore);
                    } catch (PDOException $retryException) {
                        error_log('Retry after charset conversion failed: ' . $retryException->getMessage());
                        throw $retryException;
                    }
                } else {
                    $fallbackValue = is_string($valueToStore) ? @iconv('UTF-8', 'UTF-8//IGNORE', $valueToStore) : $valueToStore;
                    if ($fallbackValue === false) {
                        $fallbackValue = '';
                    }
                    $affectedRows = $executeUpdate($fallbackValue);
                }
            } else {
                throw $e;
            }
        }

        if ($whereField !== null && $affectedRows === 0) {
            if ($whereValueToStore === null) {
                $existsStmt = $pdo->prepare("SELECT 1 FROM $table WHERE $whereField IS NULL LIMIT 1");
                $existsStmt->execute();
            } else {
                $existsStmt = $pdo->prepare("SELECT 1 FROM $table WHERE $whereField = ? LIMIT 1");
                $existsStmt->execute([$whereValueToStore]);
            }
            if ($existsStmt->fetchColumn() === false) {
                [$insertSql, $values] = self::rx_update_missing_row_insert($table, $field, $valueToStore, $whereField, $whereValueToStore);
                try {
                    $insertStmt = $pdo->prepare($insertSql);
                    $insertStmt->execute($values);
                } catch (PDOException $insertException) {
                    error_log('Failed to insert missing row during update fallback: ' . $insertException->getMessage());
                }
            }
        }


        if ($table === 'invoice' && strtolower($field) === 'status' && $valueToStore === 'active'
            && $whereField !== null && strtolower($field) !== strtolower($whereField)) {
            $clearStmt = $pdo->prepare("UPDATE invoice SET invalidated_at = NULL WHERE $whereField = ?");
            $clearStmt->execute([$whereValueToStore]);
        }

        if (defined('RX_UPDATE_LOG_ENABLED') && RX_UPDATE_LOG_ENABLED === true) {
            static $rx_update_log_skip = [
                'message_count'     => true,
                'last_message_time' => true,
                'step'              => true,
                'pagenumber'        => true,
                'Processing_value'  => true,
            ];
            if (!isset($rx_update_log_skip[$field])) {
                global $user;
                $date = date("Y-m-d H:i:s");
                if (!isset($user['step'])) {
                    $user['step'] = '';
                }
                $logValue = is_scalar($valueToStore)
                    ? $valueToStore
                    : json_encode($valueToStore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $logLine = "\n{$table}_{$field}_{$logValue}_{$whereField}_{$whereValue}_{$user['step']}_$date";
                $logPath = defined('RX_UPDATE_LOG_PATH') ? RX_UPDATE_LOG_PATH : 'log.txt';
                @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
            }
        }


        if ($table === 'marzban_panel' || $table === 'textbot') {
            \Faoxima\Core\Database\SelectCache::clearSelectCache($table);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'faoxima_bust_bot_selectcache')) {
                \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache($table);
            }
        } elseif ($whereField !== null && $whereValueToStore !== null
            && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCacheRow($table, $whereField, $whereValueToStore);
        } else {
            \Faoxima\Core\Database\SelectCache::clearSelectCache($table);
        }
    }

    public static function select($table, $field, $whereField = null, $whereValue = null, $type = "select", $options = [])
    {
        $pdo = self::getDatabaseConnection();

        if (!($pdo instanceof PDO)) {
            $rxDbMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_db_unavailable.flag';
            if (!is_file($rxDbMarker) || (time() - (int) @filemtime($rxDbMarker)) > 3600) {
                error_log('select: Database connection is unavailable.');
                @touch($rxDbMarker);
            }

            return \Faoxima\Core\Database\SelectCache::rx_select_failure_result($type);
        }

        if ($table === 'textbot') {
            try {
                TextbotTemporary::enforceExpiry($pdo);
            } catch (\Throwable $e) {
            }
        }

        $useCache = true;
        if (is_array($options) && array_key_exists('cache', $options)) {
            $useCache = (bool) $options['cache'];
        }

        $cacheKey = null;
        if ($useCache) {
            $cacheKey = hash('sha256', json_encode([
                $table,
                $field,
                $whereField,
                $whereValue,
                $type,
            ], JSON_UNESCAPED_UNICODE));

            $store =& \Faoxima\Core\Database\SelectCache::getSelectCacheStore();
            if (isset($store['results'][$cacheKey])) {
                return $store['results'][$cacheKey];
            }

            if (!isset(\Faoxima\Core\Database\SelectCache::rx_select_cache_hot_tables()[$table]) && method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_is_active') && \Faoxima\Core\Cache\RedisCache::rx_redis_is_active()) {
                $rxRedisCached = \Faoxima\Core\Cache\RedisCache::rx_redis_get('faoxima:selectcache:' . $cacheKey);
                if ($rxRedisCached !== null) {
                    $rxRedisDecoded = json_decode($rxRedisCached, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        \Faoxima\Core\Database\SelectCache::rx_select_cache_store_result($store, $table, $cacheKey, $whereField, $whereValue, $rxRedisDecoded);
                        return $rxRedisDecoded;
                    }
                }
            }
        }

        $query = "SELECT $field FROM $table";

        if ($whereField !== null) {
            $query .= " WHERE $whereField = :whereValue";
        }

        try {
            $stmt = $pdo->prepare($query);
            if ($whereField !== null) {
                $stmt->bindParam(':whereValue', $whereValue, PDO::PARAM_STR);
            }

            $stmt->execute();
            if ($type == "count") {
                $result = $stmt->rowCount();
            } elseif ($type == "FETCH_COLUMN") {
                $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if ($table === 'admin' && $field === 'id_admin') {
                    global $adminnumber;
                    if (!is_array($results)) {
                        $results = [];
                    }

                    $results = array_values(array_unique(array_filter($results, function ($value) {
                        return $value !== null && $value !== '';
                    })));

                    if (empty($results) && isset($adminnumber) && $adminnumber !== '') {
                        $results[] = (string) $adminnumber;
                    }
                }
                $result = $results;
            } elseif ($type == "fetchAll") {
                $result = $stmt->fetchAll();
            } else {
                $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
                $result = $fetched === false ? null : $fetched;
            }
        } catch (PDOException $e) {


            error_log('select() PDOException on table=' . $table . ': ' . $e->getMessage());
            return \Faoxima\Core\Database\SelectCache::rx_select_failure_result($type);
        }

        if ($useCache && $cacheKey !== null) {

            $skipCache = false;
            if (isset(\Faoxima\Core\Database\SelectCache::rx_select_cache_hot_tables()[$table])) {
                $skipCache = true;
            }
            if (!$skipCache && is_array($result) && count($result) > 1000) {
                $skipCache = true;
            }
            if (!$skipCache) {
                $store =& \Faoxima\Core\Database\SelectCache::getSelectCacheStore();


                $rxCacheMaxEntries = 500;
                if (count($store['results']) >= $rxCacheMaxEntries) {
                    $oldestKey = array_key_first($store['results']);
                    if ($oldestKey !== null) {
                        unset($store['results'][$oldestKey]);
                        foreach ($store['tableIndex'] as $tIdx => &$keys) {
                            if (isset($keys[$oldestKey])) {
                                unset($keys[$oldestKey]);
                            }
                        }
                        unset($keys);
                    }
                }
                $rxRowIdx = \Faoxima\Core\Database\SelectCache::rx_select_cache_store_result($store, $table, $cacheKey, $whereField, $whereValue, $result);

                if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_is_active') && \Faoxima\Core\Cache\RedisCache::rx_redis_is_active()) {
                    $rxRedisEncoded = json_encode($result, JSON_UNESCAPED_UNICODE);
                    if ($rxRedisEncoded !== false) {
                        $rxSelectCacheTtl = \Faoxima\Core\Database\SelectCache::rx_select_cache_redis_ttl();
                        \Faoxima\Core\Cache\RedisCache::rx_redis_set('faoxima:selectcache:' . $cacheKey, $rxRedisEncoded, $rxSelectCacheTtl);
                        \Faoxima\Core\Cache\RedisCache::rx_redis_sadd_index('faoxima:selectcache:tableindex:' . $table, 'faoxima:selectcache:' . $cacheKey, $rxSelectCacheTtl);
                        \Faoxima\Core\Cache\RedisCache::rx_redis_sadd_index('faoxima:selectcache:rowindex:' . $rxRowIdx, 'faoxima:selectcache:' . $cacheKey, $rxSelectCacheTtl);
                    }
                }
            }
        }

        return $result;
    }

    public static function getPaySettingValue($name, $default = null)
    {
        $result = self::select("PaySetting", "ValuePay", "NamePay", $name, "select");
        if (!is_array($result) || !array_key_exists('ValuePay', $result)) {
            return $default;
        }

        return $result['ValuePay'];
    }

    public static function userExists($userId)
    {
        static $existenceCache = [];

        if (is_array($userId) || is_object($userId)) {
            return false;
        }

        $normalizedId = trim((string) $userId);
        if ($normalizedId === '' || !ctype_digit($normalizedId)) {
            return false;
        }

        if (array_key_exists($normalizedId, $existenceCache)) {
            return $existenceCache[$normalizedId];
        }

        $pdo = self::getDatabaseConnection();
        if (!($pdo instanceof PDO)) {
            $rxDbMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_db_unavailable.flag';
            if (!is_file($rxDbMarker) || (time() - (int) @filemtime($rxDbMarker)) > 3600) {
                error_log('userExists: Database connection is unavailable.');
                @touch($rxDbMarker);
            }
            return false;
        }

        try {
            $stmt = $pdo->prepare('SELECT 1 FROM user WHERE id = :user_id LIMIT 1');
            $stmt->bindParam(':user_id', $normalizedId, PDO::PARAM_STR);
            $stmt->execute();
            $exists = $stmt->fetchColumn() !== false;
        } catch (PDOException $exception) {
            error_log('userExists: ' . $exception->getMessage());
            $exists = false;
        }

        $existenceCache[$normalizedId] = $exists;

        return $exists;
    }

    public static function rxTableValueExists($table, $field, $value)
    {
        static $allowed = [
            'invoice'      => ['id_invoice' => true, 'username' => true],
            'Discount'     => ['code' => true],
            'DiscountSell' => ['codeDiscount' => true],
        ];
        if (!isset($allowed[$table][$field]) || is_array($value) || is_object($value)) {
            return false;
        }
        $pdo = self::getDatabaseConnection();
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE `{$field}` = BINARY :value LIMIT 1");
            $stmt->execute([':value' => (string) $value]);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            error_log('rxTableValueExists: ' . $e->getMessage());
            return false;
        }
    }

    public static function addFieldToTable($tableName, $fieldName, $defaultValue = null, $datatype = "VARCHAR(500)")
    {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName"
        );
        $stmt->bindParam(':tableName', $tableName);
        $stmt->execute();
        $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tableExists['count'] == 0)
            return;
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$pdo->query("SELECT DATABASE()")->fetchColumn(), $tableName, $fieldName]);
        $filedExists = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($filedExists['count'] != 0)
            return;
        $query = "ALTER TABLE $tableName ADD $fieldName $datatype";
        $statement = $pdo->prepare($query);
        $statement->execute();
        if ($defaultValue != null) {
            $stmt = $pdo->prepare("UPDATE $tableName SET $fieldName= ?");
            $stmt->bindParam(1, $defaultValue);
            $stmt->execute();
        }
        echo "The $fieldName field was added ✅";
    }

    public static function rxTableColumnPresent(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
            $stmt->execute([$table, $column]);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            error_log('[rxTableColumnPresent] ' . $e->getMessage());
            return false;
        }
    }

    public static function nmTableHasColumn($table, $column)
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
            $stmt->execute([':t' => $table, ':c' => $column]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) { return false; }
    }
}
