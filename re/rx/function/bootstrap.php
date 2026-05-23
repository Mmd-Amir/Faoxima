<?php

if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', REFACTORED_LEGACY_ROOT);
}


$composerAutoload = APP_ROOT_PATH . '/vendor/autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
    unset($composerAutoload);
} else {
    error_log('Composer autoloader not found. Optional dependencies may be unavailable.');
    unset($composerAutoload);
}
require_once APP_ROOT_PATH . '/config.php';

ini_set('error_log', APP_ROOT_PATH . '/error_log');

function getDatabaseConnection()
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
        $newPdo = new PDO($dsn, (string) $username, (string) $password, is_array($options) ? $options : []);
        $GLOBALS['pdo'] = $newPdo;
        $cachedPdo = $newPdo;
        return $cachedPdo;
    } catch (PDOException $e) {
        error_log('getDatabaseConnection: Unable to create PDO instance. ' . $e->getMessage());
        return null;
    }
}

if (!defined('TRONADO_API_CONFIGURATION')) {
    $tronadoApiConfiguration = [
        'base_url' => 'https://bot.tronado.cloud',
        'order_token_path' => '/Order/GetOrderToken',
        'versions' => [
            'api/v1',
            'api/v2',
            'api/v3',
            'api',
            null,
        ],
    ];

    define('TRONADO_API_CONFIGURATION', $tronadoApiConfiguration);
    unset($tronadoApiConfiguration);
}

if (!defined('TRONADO_ORDER_TOKEN_ENDPOINTS')) {
    $tronadoConfig = TRONADO_API_CONFIGURATION;
    $baseUrl = rtrim((string) ($tronadoConfig['base_url'] ?? ''), '/');
    $path = '/' . ltrim((string) ($tronadoConfig['order_token_path'] ?? ''), '/');
    $versions = is_array($tronadoConfig['versions'] ?? null) ? $tronadoConfig['versions'] : [];

    $computedEndpoints = [];
    foreach ($versions as $version) {
        if ($baseUrl === '') {
            continue;
        }

        $versionSegment = $version !== null ? '/' . trim((string) $version, '/') : '';
        $computedEndpoints[] = $baseUrl . $versionSegment . $path;
    }

    if (!in_array(null, $versions, true)) {
        $computedEndpoints[] = $baseUrl . $path;
    }

    $computedEndpoints = array_values(array_unique(array_filter($computedEndpoints)));
    define('TRONADO_ORDER_TOKEN_ENDPOINTS', $computedEndpoints);
    unset($computedEndpoints, $baseUrl, $path, $versions, $tronadoConfig);
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;


function isShellExecAvailable()
{
    static $isAvailable;

    if ($isAvailable !== null) {
        return $isAvailable;
    }

    if (!function_exists('shell_exec')) {
        $isAvailable = false;
        return $isAvailable;
    }

    $disabledFunctions = ini_get('disable_functions');
    if (!empty($disabledFunctions) && stripos($disabledFunctions, 'shell_exec') !== false) {
        $isAvailable = false;
        return $isAvailable;
    }

    $isAvailable = true;
    return $isAvailable;
}

if (!function_exists('safe_divide')) {


    function safe_divide($numerator, $denominator, $fallback = 0)
    {
        if (!is_numeric($numerator) || !is_numeric($denominator)) {
            return $fallback;
        }

        $denominator = (float) $denominator;
        if ($denominator == 0.0) {
            return $fallback;
        }

        $result = (float) $numerator / $denominator;

        if (!is_finite($result)) {
            return $fallback;
        }

        return $result;
    }
}


function generateReferralCode($length = 12)
{
    $length = max(1, (int) $length);
    $bytes = (int) ceil($length / 2);

    if (function_exists('random_bytes')) {
        try {
            $code = bin2hex(random_bytes($bytes));
            return substr($code, 0, $length);
        } catch (Exception $exception) {
            error_log('Falling back to pseudo-random referral code generator: ' . $exception->getMessage());
        } catch (Error $exception) {
            error_log('Falling back to pseudo-random referral code generator: ' . $exception->getMessage());
        }
    }

    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxIndex = strlen($characters) - 1;
    $code = '';

    for ($i = 0; $i < $length; ++$i) {
        if (function_exists('random_int')) {
            try {
                $index = random_int(0, $maxIndex);
            } catch (Exception $exception) {
                error_log('random_int failed, using mt_rand fallback: ' . $exception->getMessage());
                $index = mt_rand(0, $maxIndex);
            } catch (Error $exception) {
                error_log('random_int failed, using mt_rand fallback: ' . $exception->getMessage());
                $index = mt_rand(0, $maxIndex);
            }
        } else {
            $index = mt_rand(0, $maxIndex);
        }

        $code .= $characters[$index];
    }

    return $code;
}


function ensureUserInvitationCode($userId, $currentCode = null, $length = 12)
{
    if (!is_scalar($userId) || (string) $userId === '') {
        return null;
    }

    $currentCode = is_string($currentCode) ? trim($currentCode) : '';
    if ($currentCode !== '') {
        return $currentCode;
    }

    $newCode = generateReferralCode($length);
    update('user', 'codeInvitation', $newCode, 'id', (string) $userId);

    return $newCode;
}

if (!function_exists('applyConnectionPlaceholders')) {


    function applyConnectionPlaceholders($template, $subscriptionLink, $configList)
    {
        $trimmedSubscription = trim((string) $subscriptionLink);
        $trimmedConfigList = trim((string) $configList);

        $connectionSections = [];
        $configSection = '';
        $linksSection = '';

        if ($trimmedSubscription !== '') {
            $configSection = "🔗 لینک اتصال:\n\n<code>{$trimmedSubscription}</code>";
            $connectionSections['config'] = $configSection;
        }

        if ($trimmedConfigList !== '') {
            $linksSection = "🔐 کانفیگ اشتراک :\n\n<code>{$trimmedConfigList}</code>";
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

        return $template;
    }
}

function getCrontabBinary()
{
    static $resolvedPath;

    if ($resolvedPath !== null) {
        return $resolvedPath ?: null;
    }

    $candidateDirectories = [
        '/usr/local/bin',
        '/usr/bin',
        '/bin',
        '/usr/sbin',
        '/sbin',
    ];

    $environmentPath = getenv('PATH');
    if ($environmentPath !== false && $environmentPath !== '') {
        foreach (explode(PATH_SEPARATOR, $environmentPath) as $pathDirectory) {
            $pathDirectory = trim($pathDirectory);
            if ($pathDirectory !== '' && !in_array($pathDirectory, $candidateDirectories, true)) {
                $candidateDirectories[] = $pathDirectory;
            }
        }
    }

    foreach ($candidateDirectories as $directory) {
        $executablePath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crontab';
        if (@is_file($executablePath) && @is_executable($executablePath)) {
            $resolvedPath = $executablePath;
            return $resolvedPath;
        }
    }

    if (isShellExecAvailable()) {
        $whichOutput = @shell_exec('command -v crontab 2>/dev/null');
        if (is_string($whichOutput)) {
            $whichOutput = trim($whichOutput);
            if ($whichOutput !== '' && @is_executable($whichOutput)) {
                $resolvedPath = $whichOutput;
                return $resolvedPath;
            }
        }
    }

    $resolvedPath = '';
    error_log('Unable to locate the crontab executable on this system.');

    return null;
}

function runShellCommand($command)
{
    if (!isShellExecAvailable()) {
        error_log('shell_exec is not available; unable to run command: ' . $command);
        return null;
    }

    if (getenv('PATH') === false || trim((string) getenv('PATH')) === '') {
        putenv('PATH=/usr/local/bin:/usr/bin:/bin');
    }

    return shell_exec($command);
}

function deleteDirectory($directory)
{
    if (!file_exists($directory)) {
        return true;
    }

    if (!is_dir($directory)) {
        return @unlink($directory);
    }

    $items = scandir($directory);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if (!deleteDirectory($path)) {
                return false;
            }
        } else {
            if (!@unlink($path)) {
                return false;
            }
        }
    }

    return @rmdir($directory);
}

function ensureTableUtf8mb4($table)
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

function ensureCardNumberTableSupportsUnicode()
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

        ensureTableUtf8mb4('card_number');

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

function normaliseUpdateValue($value)
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $value;
}

function copyDirectoryContents($source, $destination)
{
    if (!is_dir($source)) {
        return false;
    }

    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        return false;
    }

    $items = scandir($source);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
        $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;

        if (is_dir($sourcePath)) {
            if (!copyDirectoryContents($sourcePath, $destinationPath)) {
                return false;
            }
        } else {
            if (!@copy($sourcePath, $destinationPath)) {
                return false;
            }
        }
    }

    return true;
}


function step($step, $from_id)
{
    global $pdo;
    $stmt = $pdo->prepare('UPDATE user SET step = ? WHERE id = ?');
    $stmt->execute([$step, $from_id]);
    clearSelectCache('user');
}
function determineColumnTypeFromValue($value)
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
function ensureColumnExistsForUpdate($tableName, $fieldName, $valueSample = null)
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

        $datatype = determineColumnTypeFromValue($valueSample);

        $defaultValue = null;
        if (is_bool($valueSample)) {
            $defaultValue = $valueSample ? '1' : '0';
        } elseif (is_scalar($valueSample) && $valueSample !== null) {
            $defaultValue = (string) $valueSample;
        }

        addFieldToTable($tableName, $fieldName, $defaultValue, $datatype);
        $checkedColumns[$cacheKey] = true;
    } catch (PDOException $e) {
        error_log('Failed to ensure column exists: ' . $e->getMessage());
        $checkedColumns[$cacheKey] = true;
    }
}
function update($table, $field, $newValue, $whereField = null, $whereValue = null)
{
    global $pdo, $user;

    $valueToStore = normaliseUpdateValue($newValue);
    $whereValueToStore = $whereField !== null ? normaliseUpdateValue($whereValue) : null;

    ensureColumnExistsForUpdate($table, $field, $valueToStore);
    if ($whereField !== null) {
        ensureColumnExistsForUpdate($table, $whereField, $whereValueToStore);
    }

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
        if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
            $tableConverted = ensureTableUtf8mb4($table);
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

        $rowExists = $existsStmt->fetchColumn();

        if ($rowExists === false) {
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

            try {
                $insertStmt = $pdo->prepare("INSERT INTO $table ($columnList) VALUES ($placeholders)");
                $insertStmt->execute($values);
            } catch (PDOException $insertException) {
                error_log('Failed to insert missing row during update fallback: ' . $insertException->getMessage());
            }
        }
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


    if ($whereField !== null && $whereValueToStore !== null
        && function_exists('clearSelectCacheRow')) {
        clearSelectCacheRow($table, $whereField, $whereValueToStore);
    } else {
        clearSelectCache($table);
    }
}
function &getSelectCacheStore()
{
    static $store = [
        'results' => [],
        'tableIndex' => [],
        'rowIndex' => [],
    ];

    return $store;
}

function clearSelectCache($table = null)
{
    $store =& getSelectCacheStore();

    if ($table === null) {
        $store['results'] = [];
        $store['tableIndex'] = [];
        $store['rowIndex'] = [];
        return;
    }

    if (!isset($store['tableIndex'][$table])) {
        return;
    }

    foreach (array_keys($store['tableIndex'][$table]) as $cacheKey) {
        unset($store['results'][$cacheKey]);
    }

    unset($store['tableIndex'][$table]);


    if (isset($store['rowIndex']) && is_array($store['rowIndex'])) {
        foreach (array_keys($store['rowIndex']) as $rowKey) {
            if (strpos($rowKey, $table . '|') === 0) {
                unset($store['rowIndex'][$rowKey]);
            }
        }
    }
}

function select($table, $field, $whereField = null, $whereValue = null, $type = "select", $options = [])
{
    $pdo = getDatabaseConnection();

    if (!($pdo instanceof PDO)) {
        $rxDbMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_db_unavailable.flag';
        if (!is_file($rxDbMarker) || (time() - (int) @filemtime($rxDbMarker)) > 3600) {
            error_log('select: Database connection is unavailable.');
            @touch($rxDbMarker);
        }

        switch ($type) {
            case 'count':
                return 0;
            case 'FETCH_COLUMN':
            case 'fetchAll':
                return [];
            default:
                return null;
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

        $store =& getSelectCacheStore();
        if (isset($store['results'][$cacheKey])) {
            return $store['results'][$cacheKey];
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
        switch ($type) {
            case 'count':
                return 0;
            case 'FETCH_COLUMN':
            case 'fetchAll':
                return [];
            default:
                return null;
        }
    }

    if ($useCache && $cacheKey !== null) {


        static $rx_select_cache_hot_tables = [
            'user'           => true,
            'invoice'        => true,
            'Payment_report' => true,
            'reagent_report' => true,
            'cron_runtime_state' => true,
        ];
        $skipCache = false;
        if (isset($rx_select_cache_hot_tables[$table])) {
            $skipCache = true;
        }
        if (!$skipCache && is_array($result) && count($result) > 1000) {
            $skipCache = true;
        }
        if (!$skipCache) {
            $store =& getSelectCacheStore();


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
            $store['results'][$cacheKey] = $result;
            if (!isset($store['tableIndex'][$table])) {
                $store['tableIndex'][$table] = [];
            }
            $store['tableIndex'][$table][$cacheKey] = true;

            $rxRowIdx = $table . '|' . (string) $whereField . '|' . (string) $whereValue;
            if (!isset($store['rowIndex'][$rxRowIdx])) {
                $store['rowIndex'][$rxRowIdx] = [];
            }
            $store['rowIndex'][$rxRowIdx][$cacheKey] = true;
        }
    }

    return $result;
}


if (!function_exists('clearSelectCacheRow')) {
    function clearSelectCacheRow($table, $whereField, $whereValue)
    {
        $store =& getSelectCacheStore();
        if (!isset($store['tableIndex'][$table])) {
            return;
        }


        $rowIdx = $table . '|' . (string) $whereField . '|' . (string) $whereValue;
        if (isset($store['rowIndex'][$rowIdx]) && is_array($store['rowIndex'][$rowIdx])) {
            foreach (array_keys($store['rowIndex'][$rowIdx]) as $cacheKey) {
                unset($store['results'][$cacheKey]);
                unset($store['tableIndex'][$table][$cacheKey]);
            }
            unset($store['rowIndex'][$rowIdx]);
        }


        $allRowsIdx = $table . '||';
        if (isset($store['rowIndex'][$allRowsIdx]) && is_array($store['rowIndex'][$allRowsIdx])) {
            foreach (array_keys($store['rowIndex'][$allRowsIdx]) as $cacheKey) {
                unset($store['results'][$cacheKey]);
                unset($store['tableIndex'][$table][$cacheKey]);
            }
            unset($store['rowIndex'][$allRowsIdx]);
        }
    }
}

function getPaySettingValue($name, $default = null)
{
    $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
    if (!is_array($result) || !array_key_exists('ValuePay', $result)) {
        return $default;
    }

    return $result['ValuePay'];
}


function userExists($userId)
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

    $pdo = getDatabaseConnection();
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

function formatPaymentReportNote($rawNote)
{
    if ($rawNote === null) {
        return '';
    }

    if (is_array($rawNote)) {
        return json_encode($rawNote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!is_scalar($rawNote)) {
        return '';
    }

    $rawNote = trim((string) $rawNote);
    if ($rawNote === '') {
        return '';
    }

    $decoded = json_decode($rawNote, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        if (($decoded['gateway'] ?? '') === 'zarinpay') {
            $lines = ['زرین‌پی'];
            $fieldMap = [
                'payment_id' => 'شناسه پرداخت',
                'reference_id' => 'شماره پیگیری',
                'authority' => 'کد اعتبار',
                'order_id' => 'کد سفارش',
                'code' => 'کد تأیید',
            ];

            foreach ($fieldMap as $key => $label) {
                $value = $decoded[$key] ?? null;
                if ($value !== null && $value !== '') {
                    $lines[] = sprintf('%s: %s', $label, $value);
                }
            }

            if (!empty($decoded['amount'])) {
                $lines[] = 'مبلغ تراکنش (ریال): ' . number_format((int) $decoded['amount']);
            }

            if (!empty($decoded['card_pan'])) {
                $lines[] = 'کارت پرداخت‌کننده: ' . $decoded['card_pan'];
            }

            if (!empty($decoded['paid_at'])) {
                $lines[] = 'زمان پرداخت: ' . $decoded['paid_at'];
            }

            return implode("\n", array_filter($lines));
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    return $rawNote;
}
function generateUUID()
{
    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    return $uuid;
}
if (!function_exists('isShellExecAvailable')) {
    function isShellExecAvailable()
    {
        static $isAvailable = null;
        if ($isAvailable !== null) {
            return $isAvailable;
        }
        if (!function_exists('shell_exec')) {
            return $isAvailable = false;
        }
        $disabledFunctions = (string) ini_get('disable_functions');
        if ($disabledFunctions !== '' && preg_match('/(^|,)\s*shell_exec\s*(,|$)/i', $disabledFunctions)) {
            return $isAvailable = false;
        }
        return $isAvailable = true;
    }
}

if (!function_exists('runShellCommand')) {
    function runShellCommand($command)
    {
        if (!isShellExecAvailable()) {
            error_log('shell_exec is not available; unable to run command: ' . $command);
            return null;
        }
        if (getenv('PATH') === false || trim((string) getenv('PATH')) === '') {
            putenv('PATH=/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin');
        }
        return shell_exec($command);
    }
}

if (!function_exists('getCrontabBinary')) {
    function getCrontabBinary()
    {
        static $resolvedPath = null;
        if ($resolvedPath !== null) {
            return $resolvedPath ?: null;
        }
        $candidateDirectories = ['/usr/local/bin', '/usr/bin', '/bin', '/usr/sbin', '/sbin'];
        $environmentPath = getenv('PATH');
        if ($environmentPath !== false && $environmentPath !== '') {
            foreach (explode(PATH_SEPARATOR, $environmentPath) as $pathDirectory) {
                $pathDirectory = trim($pathDirectory);
                if ($pathDirectory !== '' && !in_array($pathDirectory, $candidateDirectories, true)) {
                    $candidateDirectories[] = $pathDirectory;
                }
            }
        }
        foreach ($candidateDirectories as $directory) {
            $executablePath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crontab';
            if (@is_file($executablePath) && @is_executable($executablePath)) {
                return $resolvedPath = $executablePath;
            }
        }
        if (isShellExecAvailable()) {
            $whichOutput = @shell_exec('command -v crontab 2>/dev/null');
            $whichOutput = is_string($whichOutput) ? trim($whichOutput) : '';
            if ($whichOutput !== '' && @is_executable($whichOutput)) {
                return $resolvedPath = $whichOutput;
            }
        }
        $resolvedPath = '';
        error_log('Unable to locate the crontab executable on this system.');
        return null;
    }
}