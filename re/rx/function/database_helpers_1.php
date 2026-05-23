<?php


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
        return is_finite($result) ? $result : $fallback;
    }
}

if (!function_exists('clearSelectCache')) {
    function clearSelectCache($table = null)
    {
        if (!isset($GLOBALS['rx_select_cache']) || !is_array($GLOBALS['rx_select_cache'])) {
            $GLOBALS['rx_select_cache'] = [];
            return;
        }
        if ($table === null) {
            $GLOBALS['rx_select_cache'] = [];
            return;
        }
        foreach (array_keys($GLOBALS['rx_select_cache']) as $key) {
            if (strpos($key, (string) $table . '|') === 0) {
                unset($GLOBALS['rx_select_cache'][$key]);
            }
        }
    }
}

if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection()
    {
        global $pdo, $servername, $username, $password, $dbname;
        if (isset($pdo) && $pdo instanceof PDO) {
            return $pdo;
        }
        if (!isset($servername, $username, $password, $dbname)) {
            return null;
        }
        try {
            $pdo = new PDO("mysql:host={$servername};dbname={$dbname};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
            return $pdo;
        } catch (Throwable $e) {
            error_log('getDatabaseConnection: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('determineColumnTypeFromValue')) {
    function determineColumnTypeFromValue($value)
    {
        if (is_bool($value)) return 'TINYINT(1)';
        if (is_int($value)) return 'INT(11)';
        if (is_float($value)) return 'DOUBLE';
        if ($value === null) return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        if (is_string($value)) {
            $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($length <= 191) return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            if ($length <= 500) return 'VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
        return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}

if (!function_exists('normaliseUpdateValue')) {
    function normaliseUpdateValue($value)
    {
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_array($value) || is_object($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($value === null) return null;
        return (string) $value;
    }
}

if (!function_exists('ensureColumnExistsForUpdate')) {
    function ensureColumnExistsForUpdate($tableName, $fieldName, $valueSample = null)
    {
        global $pdo;
        if (!($pdo instanceof PDO) || $tableName === null || $fieldName === null) return;
        static $checkedColumns = [];
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        $fieldName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $fieldName);
        if ($tableName === '' || $fieldName === '') return;
        $cacheKey = $tableName . '.' . $fieldName;
        if (isset($checkedColumns[$cacheKey])) return;
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $stmt->execute([$tableName, $fieldName]);
            if ((int) $stmt->fetchColumn() === 0 && function_exists('addFieldToTable')) {
                $defaultValue = null;
                if (is_bool($valueSample)) $defaultValue = $valueSample ? '1' : '0';
                elseif (is_scalar($valueSample) && $valueSample !== null) $defaultValue = (string) $valueSample;
                addFieldToTable($tableName, $fieldName, $defaultValue, determineColumnTypeFromValue($valueSample));
            }
        } catch (Throwable $e) {
            error_log('ensureColumnExistsForUpdate: ' . $e->getMessage());
        }
        $checkedColumns[$cacheKey] = true;
    }
}

if (!function_exists('ensureTableUtf8mb4')) {
    function ensureTableUtf8mb4($tableName)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return false;
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        if ($tableName === '') return false;
        try {
            $pdo->exec("ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return true;
        } catch (Throwable $e) {
            error_log('ensureTableUtf8mb4: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('ensureCardNumberTableSupportsUnicode')) {
    function ensureCardNumberTableSupportsUnicode()
    {
        return ensureTableUtf8mb4('card_number');
    }
}

if (!function_exists('update')) {
    function update($table, $field, $newValue, $whereField = null, $whereValue = null)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return 0;
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        $field = preg_replace('/[^A-Za-z0-9_]/', '', (string) $field);
        if ($table === '' || $field === '') return 0;
        $valueToStore = normaliseUpdateValue($newValue);
        ensureColumnExistsForUpdate($table, $field, $valueToStore);
        $params = [$valueToStore];
        if ($whereField !== null) {
            $whereField = preg_replace('/[^A-Za-z0-9_]/', '', (string) $whereField);
            ensureColumnExistsForUpdate($table, $whereField, $whereValue);
            $sql = "UPDATE `{$table}` SET `{$field}` = ? WHERE `{$whereField}` = ?";
            $params[] = normaliseUpdateValue($whereValue);
        } else {
            $sql = "UPDATE `{$table}` SET `{$field}` = ?";
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            clearSelectCache($table);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Incorrect string value') !== false && ensureTableUtf8mb4($table)) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                clearSelectCache($table);
                return $stmt->rowCount();
            }
            throw $e;
        }
    }
}

if (!function_exists('select')) {
    function select($table, $field, $whereField = null, $whereValue = null, $type = 'select', $options = [])
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return false;
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        if ($table === '') return false;
        $fieldSql = ($field === '*') ? '*' : '`' . str_replace('`', '', (string) $field) . '`';
        $type = strtolower((string) $type);
        $useCache = !isset($options['cache']) || $options['cache'] !== false;
        $cacheKey = $table . '|' . $fieldSql . '|' . (string) $whereField . '|' . serialize($whereValue) . '|' . $type;
        if ($useCache && isset($GLOBALS['rx_select_cache'][$cacheKey])) return $GLOBALS['rx_select_cache'][$cacheKey];
        $params = [];
        if ($type === 'count') {
            $sql = "SELECT COUNT(*) FROM `{$table}`";
        } else {
            $sql = "SELECT {$fieldSql} FROM `{$table}`";
        }
        if ($whereField !== null) {
            $whereField = preg_replace('/[^A-Za-z0-9_]/', '', (string) $whereField);
            $sql .= " WHERE `{$whereField}` = ?";
            $params[] = normaliseUpdateValue($whereValue);
        }
        if ($type !== 'count') {
            $sql .= ' LIMIT 1';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($type === 'count') {
            $result = (int) $stmt->fetchColumn();
        } else {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) $result = false;
            elseif ($field === '*' || strpos((string) $field, ',') !== false) $result = $row;
            else $result = $row[(string) $field] ?? $row;
        }
        if ($useCache) $GLOBALS['rx_select_cache'][$cacheKey] = $result;
        return $result;
    }
}

if (!function_exists('step')) {
    function step($step, $from_id)
    {
        update('user', 'step', $step, 'id', $from_id);
    }
}

if (!function_exists('generateUUID')) {
    function generateUUID()
    {
        $data = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('generateReferralCode')) {
    function generateReferralCode($length = 12)
    {
        $length = max(1, (int) $length);
        try {
            return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
        } catch (Throwable $e) {
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $code = '';
            for ($i = 0; $i < $length; $i++) $code .= $chars[mt_rand(0, strlen($chars) - 1)];
            return $code;
        }
    }
}

if (!function_exists('ensureUserInvitationCode')) {
    function ensureUserInvitationCode($userId)
    {
        $user = select('user', '*', 'id', $userId, 'select', ['cache' => false]);
        if (is_array($user) && !empty($user['codeInvitation'])) return $user['codeInvitation'];
        $code = generateReferralCode(12);
        update('user', 'codeInvitation', $code, 'id', $userId);
        return $code;
    }
}

if (!function_exists('deleteDirectory')) {
    function deleteDirectory($directory)
    {
        if (!file_exists($directory)) return true;
        if (!is_dir($directory)) return @unlink($directory);
        $items = scandir($directory);
        if ($items === false) return false;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                if (!deleteDirectory($path)) return false;
            } elseif (!@unlink($path)) return false;
        }
        return @rmdir($directory);
    }
}

if (!function_exists('copyDirectoryContents')) {
    function copyDirectoryContents($source, $destination)
    {
        if (!is_dir($source)) return false;
        if (!is_dir($destination) && !@mkdir($destination, 0755, true)) return false;
        $items = scandir($source);
        if ($items === false) return false;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $src = $source . DIRECTORY_SEPARATOR . $item;
            $dst = $destination . DIRECTORY_SEPARATOR . $item;
            if (is_dir($src)) {
                if (!copyDirectoryContents($src, $dst)) return false;
            } elseif (!@copy($src, $dst)) return false;
        }
        return true;
    }
}

if (!function_exists('deleteInvoiceFromList')) {
    function deleteInvoiceFromList($invoiceId, $userId = null)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) return false;
        $sql = 'DELETE FROM invoice WHERE id_invoice = :invoice_id';
        $params = [':invoice_id' => $invoiceId];
        if ($userId !== null) {
            $sql .= ' AND id_user = :user_id';
            $params[':user_id'] = $userId;
        }
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        clearSelectCache('invoice');
        return $result;
    }
}

if (!function_exists('formatPaymentReportNote')) {
    function formatPaymentReportNote($rawNote)
    {
        if ($rawNote === null || $rawNote === '') return '';
        $decoded = is_string($rawNote) ? json_decode($rawNote, true) : null;
        if (is_array($decoded)) {
            $parts = [];
            foreach ($decoded as $key => $value) {
                if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $parts[] = htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ': ' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            return implode("\n", $parts);
        }
        return htmlspecialchars((string) $rawNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}


if (!function_exists('getCronJobDefinitions')) {
    function getCronJobDefinitions(): array
    {
        return [
            'statusday' => ['script' => 'statusday.php', 'admin_label' => 'کرون وضعیت روزانه', 'instruction' => '🕚 بررسی وضعیت روزانه — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'croncard' => ['script' => 'croncard.php', 'admin_label' => 'کرون کارت‌به‌کارت', 'instruction' => '💳 بررسی کارت‌به‌کارت — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'notifications' => ['script' => 'NoticationsService.php', 'admin_label' => 'کرون اعلان‌ها', 'instruction' => '🔔 ارسال اعلان‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'payment_expire' => ['script' => 'payment_expire.php', 'admin_label' => 'کرون انقضای پرداخت', 'instruction' => '⏳ بررسی انقضای پرداخت‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'sendmessage' => ['script' => 'sendmessage.php', 'admin_label' => 'کرون ارسال پیام', 'instruction' => '📨 ارسال پیام زمان‌بندی‌شده — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'plisio' => ['script' => 'plisio.php', 'admin_label' => 'کرون Plisio', 'instruction' => '💰 بررسی پرداخت Plisio — %s', 'default' => ['unit' => 'minute', 'value' => 3]],
            'activeconfig' => ['script' => 'activeconfig.php', 'admin_label' => 'کرون فعال‌سازی تنظیمات', 'instruction' => '✅ فعال‌سازی تنظیمات — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'disableconfig' => ['script' => 'disableconfig.php', 'admin_label' => 'کرون غیرفعال‌سازی تنظیمات', 'instruction' => '⛔ غیرفعال‌سازی تنظیمات — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'iranpay1' => ['script' => 'iranpay1.php', 'admin_label' => 'کرون ایران‌پی', 'instruction' => '🇮🇷 بررسی پرداخت ایران‌پی — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'backupbot' => ['script' => 'backupbot.php', 'admin_label' => 'کرون بکاپ', 'instruction' => '📦 بکاپ‌گیری — %s', 'default' => ['unit' => 'hour', 'value' => 5]],
            'gift' => ['script' => 'gift.php', 'admin_label' => 'کرون هدایا', 'instruction' => '🎁 ارسال هدایا — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'lottery' => ['script' => 'lottery.php', 'admin_label' => 'قرعه‌کشی شبانه', 'instruction' => '🎁 قرعه‌کشی شبانه — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'expireagent' => ['script' => 'expireagent.php', 'admin_label' => 'کرون انقضای نمایندگان', 'instruction' => '👥 بررسی انقضای نمایندگان — %s', 'default' => ['unit' => 'minute', 'value' => 30]],
            'on_hold' => ['script' => 'on_hold.php', 'admin_label' => 'کرون سرویس‌های معلق', 'instruction' => '⏸ بررسی سفارش‌های معلق — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'configtest' => ['script' => 'configtest.php', 'admin_label' => 'کرون تست تنظیمات', 'instruction' => '🧪 تست تنظیمات سیستم — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'uptime_node' => ['script' => 'uptime_node.php', 'admin_label' => 'کرون Uptime نود', 'instruction' => '🌐 بررسی Uptime نودها — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'uptime_panel' => ['script' => 'uptime_panel.php', 'admin_label' => 'کرون Uptime پنل', 'instruction' => '🖥 بررسی Uptime پنل‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'cryptocheck' => ['script' => 'cryptocheck.php', 'admin_label' => 'کرون چک هش کریپتو', 'instruction' => '🪙 بررسی پرداخت‌های کریپتو — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'nowpaymentcheck' => ['script' => 'nowpaymentcheck.php', 'admin_label' => 'کرون پولر NowPayments', 'instruction' => '💎 بررسی پرداخت‌های NowPayments — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
        ];
    }
}

if (!function_exists('getDefaultCronSchedules')) {
    function getDefaultCronSchedules(): array
    {
        $defaults = [];
        foreach (getCronJobDefinitions() as $key => $definition) $defaults[$key] = $definition['default'];
        return $defaults;
    }
}

if (!function_exists('normalizeCronScheduleConfig')) {
    function normalizeCronScheduleConfig(array $config, array $default): array
    {
        $unit = strtolower((string) ($config['unit'] ?? $default['unit'] ?? 'minute'));
        if (!in_array($unit, ['minute', 'hour', 'day', 'disabled'], true)) $unit = $default['unit'] ?? 'minute';
        $value = (int) ($config['value'] ?? $default['value'] ?? 1);
        if ($unit === 'disabled') $value = 1;
        elseif ($value < 1) $value = (int) ($default['value'] ?? 1);
        return ['unit' => $unit, 'value' => max(1, $value)];
    }
}

if (!function_exists('ensureCronRuntimeStateTable')) {
    function ensureCronRuntimeStateTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cron_runtime_state (
            job_key VARCHAR(255) PRIMARY KEY,
            last_run BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            unit VARCHAR(20) NOT NULL DEFAULT 'minute',
            value INT(10) UNSIGNED NOT NULL DEFAULT 1,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
}

if (!function_exists('loadCronRuntimeState')) {
    function loadCronRuntimeState(PDO $pdo): array
    {
        ensureCronRuntimeStateTable($pdo);
        $state = [];
        $stmt = $pdo->query('SELECT job_key, last_run FROM cron_runtime_state');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $state[(string) $row['job_key']] = (int) $row['last_run'];
        return $state;
    }
}

if (!function_exists('setCronJobLastRun')) {
    function setCronJobLastRun(PDO $pdo, string $jobKey, int $timestamp): void
    {
        if (trim($jobKey) === '') return;
        ensureCronRuntimeStateTable($pdo);
        $stmt = $pdo->prepare('INSERT INTO cron_runtime_state (job_key, last_run) VALUES (:job_key, :last_run) ON DUPLICATE KEY UPDATE last_run = VALUES(last_run)');
        $stmt->execute([':job_key' => $jobKey, ':last_run' => $timestamp]);
    }
}

if (!function_exists('loadCronSchedules')) {
    function loadCronSchedules(): array
    {
        $definitions = getCronJobDefinitions();
        $schedules = getDefaultCronSchedules();
        $pdo = getDatabaseConnection();
        if (!($pdo instanceof PDO)) return $schedules;
        try {
            ensureCronRuntimeStateTable($pdo);
            foreach ($definitions as $key => $definition) {
                $default = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
                $stmt = $pdo->prepare('INSERT IGNORE INTO cron_runtime_state (job_key, unit, value, enabled) VALUES (:job_key, :unit, :value, 1)');
                $stmt->execute([':job_key' => $key, ':unit' => $default['unit'], ':value' => (int) $default['value']]);
            }
            $stmt = $pdo->query('SELECT job_key, unit, value, enabled FROM cron_runtime_state');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $jobKey = trim((string) ($row['job_key'] ?? ''));
                if ($jobKey === '' || !isset($definitions[$jobKey])) continue;
                $config = ['unit' => (string) $row['unit'], 'value' => (int) $row['value']];
                if (isset($row['enabled']) && (int) $row['enabled'] === 0) $config['unit'] = 'disabled';
                $schedules[$jobKey] = normalizeCronScheduleConfig($config, $definitions[$jobKey]['default']);
            }
        } catch (Throwable $e) {
            error_log('loadCronSchedules: ' . $e->getMessage());
        }
        return $schedules;
    }
}

if (!function_exists('updateCronSchedule')) {
    function updateCronSchedule(string $jobKey, array $config): bool
    {
        $definitions = getCronJobDefinitions();
        if (!isset($definitions[$jobKey])) return false;
        $pdo = getDatabaseConnection();
        if (!($pdo instanceof PDO)) return false;
        $normalized = normalizeCronScheduleConfig($config, $definitions[$jobKey]['default']);
        $enabled = $normalized['unit'] === 'disabled' ? 0 : 1;
        try {
            ensureCronRuntimeStateTable($pdo);
            $stmt = $pdo->prepare('INSERT INTO cron_runtime_state (job_key, unit, value, enabled) VALUES (:job_key, :unit, :value, :enabled) ON DUPLICATE KEY UPDATE unit = VALUES(unit), value = VALUES(value), enabled = VALUES(enabled)');
            return $stmt->execute([':job_key' => $jobKey, ':unit' => $normalized['unit'], ':value' => $normalized['value'], ':enabled' => $enabled]);
        } catch (Throwable $e) {
            error_log('updateCronSchedule: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('describeCronSchedule')) {
    function describeCronSchedule(array $config): string
    {
        $unit = $config['unit'] ?? 'minute';
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled') return 'غیرفعال';
        $labels = ['minute' => 'دقیقه', 'hour' => 'ساعت', 'day' => 'روز'];
        return sprintf('هر %d %s', $value, $labels[$unit] ?? 'دقیقه');
    }
}

if (!function_exists('shouldRunCronJob')) {


    function shouldRunCronJob(array $config, $minuteOrJobKey = null, ?int $hour = null, ?int $dayOfYear = null): bool
    {
        $unit = $config['unit'] ?? 'minute';
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled') return false;


        if (is_string($minuteOrJobKey) && $minuteOrJobKey !== '') {
            $jobKey = $minuteOrJobKey;
            $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
            if (!($pdo instanceof PDO)) return true;

            try {
                $state = function_exists('loadCronRuntimeState') ? loadCronRuntimeState($pdo) : [];
            } catch (\Throwable $e) {
                $state = [];
            }
            $lastRun = isset($state[$jobKey]) ? (int) $state[$jobKey] : 0;
            $now = time();

            $intervalSeconds = match ($unit) {
                'minute' => $value * 60,
                'hour'   => $value * 3600,
                'day'    => $value * 86400,
                default  => $value * 60,
            };


            return ($now - $lastRun) >= ($intervalSeconds - 5);
        }


        $minute = (int) $minuteOrJobKey;
        $hour = (int) $hour;
        $dayOfYear = (int) $dayOfYear;
        if ($unit === 'minute') return $minute % $value === 0;
        if ($unit === 'hour')   return $minute === 0 && $hour % $value === 0;
        if ($unit === 'day')    return $minute === 0 && $hour === 0 && $dayOfYear % $value === 0;
        return false;
    }
}

if (!function_exists('shouldRunCronJobNow')) {


    function shouldRunCronJobNow(string $jobKey, array $config): bool
    {
        $unit = $config['unit'] ?? 'minute';
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled') return false;

        $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
        if (!($pdo instanceof PDO)) return true;

        try {
            $state = function_exists('loadCronRuntimeState') ? loadCronRuntimeState($pdo) : [];
        } catch (\Throwable $e) {
            $state = [];
        }

        $lastRun = isset($state[$jobKey]) ? (int) $state[$jobKey] : 0;
        $now = time();
        $intervalSeconds = match ($unit) {
            'minute' => $value * 60,
            'hour'   => $value * 3600,
            'day'    => $value * 86400,
            default  => $value * 60,
        };

        if (($now - $lastRun) < ($intervalSeconds - 5)) {
            return false;
        }


        try {
            if (function_exists('setCronJobLastRun')) {
                setCronJobLastRun($pdo, $jobKey, $now);
            }
        } catch (\Throwable $e) {

        }
        return true;
    }
}

if (!function_exists('buildCronScriptUrlByHost')) {
    function buildCronScriptUrlByHost(string $domainHost, string $script): string
    {
        $domainHost = preg_replace('#^https?://#i', '', trim($domainHost));
        return 'https://' . rtrim($domainHost, '/') . '/cronbot/' . ltrim($script, '/');
    }
}

if (!function_exists('buildCronInstructionDetails')) {
    function buildCronInstructionDetails(string $domainHost): string
    {
        $schedules = loadCronSchedules();
        $parts = [];
        foreach (getCronJobDefinitions() as $key => $definition) {
            $description = describeCronSchedule($schedules[$key] ?? $definition['default']);
            $title = sprintf($definition['instruction'], $description);
            $endpoint = buildCronScriptUrlByHost($domainHost, $definition['script']);
            $parts[] = "<b>{$title}</b>\n<code>curl " . htmlspecialchars($endpoint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
        }
        return implode("\n\n", $parts);
    }
}

if (!function_exists('buildCronJobsKeyboard')) {
    function buildCronJobsKeyboard(): string
    {
        $definitions = getCronJobDefinitions();
        $schedules = loadCronSchedules();
        $rows = [];
        foreach ($definitions as $key => $definition) {
            $schedule = $schedules[$key] ?? $definition['default'];
            $rows[] = [
                ['text' => '⚙️ تنظیمات', 'callback_data' => "cronjob_config-{$key}"],
                ['text' => describeCronSchedule($schedule), 'callback_data' => 'cronjob_display'],
                ['text' => $definition['admin_label'], 'callback_data' => 'cronjob_display'],
            ];
        }
        $rows[] = [['text' => '🔙 بازگشت به تنظیمات کرون', 'callback_data' => 'cronjobs_back_settings']];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }
}


if (!function_exists('buildXuiSingleBaseUrl')) {
    function buildXuiSingleBaseUrl($url, $dropLastSegment = false)
    {
        $url = trim((string) $url);
        if ($url === '') return '';
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) return rtrim($url, '/');
        $path = $parts['path'] ?? '';
        if ($dropLastSegment && $path !== '') $path = preg_replace('#/[^/]*$#', '', $path);
        $base = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim($path, '/');
        return rtrim($base, '/');
    }
}

if (!function_exists('normalizeXuiSingleSubscriptionBaseUrl')) {
    function normalizeXuiSingleSubscriptionBaseUrl($url)
    {
        return buildXuiSingleBaseUrl($url, true);
    }
}

if (!function_exists('hasLikelyXuiSubscriptionId')) {
    function hasLikelyXuiSubscriptionId($url)
    {
        return (bool) preg_match('#/(sub|sub/|xui|proxy|link)/?[A-Za-z0-9_-]{8,}#i', (string) $url);
    }
}

if (!function_exists('applyConnectionPlaceholders')) {
    function applyConnectionPlaceholders($template, $subscription = '', $configs = [])
    {
        $links = is_array($configs) ? implode("\n", array_filter(array_map('strval', $configs))) : (string) $configs;
        return str_replace(['{sub}', '{subscription}', '{linksub}', '{links}', '{links2}'], [(string) $subscription, (string) $subscription, (string) $subscription, $links, trim((string) $subscription)], (string) $template);
    }
}
function tronratee(array $requiredKeys = [])
{
    $normalizedKeys = [];
    foreach ($requiredKeys as $key) {
        $normalized = strtoupper(trim((string) $key));
        if ($normalized === '') {
            continue;
        }
        $normalizedKeys[$normalized] = true;
    }

    if (empty($normalizedKeys)) {
        $normalizedKeys = ['TRX' => true, 'TON' => true, 'USD' => true];
    }

    $needsTrx = isset($normalizedKeys['TRX']);
    $needsTon = isset($normalizedKeys['TON']);
    $needsUsd = isset($normalizedKeys['USD']);

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
        ],
    ]);

    $result = [];
    $missingKeys = [];

    if (!$needsTrx && !$needsTon && !$needsUsd) {
        return ['ok' => true, 'result' => $result];
    }

    $endpoint = 'https://swapwallet.app/api/v1/market/prices';
    $response = @file_get_contents($endpoint, false, $context);

    if ($response === false) {
        error_log('Failed to fetch market prices from SwapWallet API');
        if ($needsTrx) $missingKeys[] = 'TRX';
        if ($needsTon) $missingKeys[] = 'Ton';
        if ($needsUsd) $missingKeys[] = 'USD';
        return ['ok' => empty($missingKeys), 'result' => $result];
    }

    $data = json_decode($response, true);
    if (!is_array($data) || ($data['status'] ?? null) !== 'OK' || !isset($data['result']) || !is_array($data['result'])) {
        error_log('Invalid response received from SwapWallet API');
        if ($needsTrx) $missingKeys[] = 'TRX';
        if ($needsTon) $missingKeys[] = 'Ton';
        if ($needsUsd) $missingKeys[] = 'USD';
        return ['ok' => empty($missingKeys), 'result' => $result];
    }

    $prices = $data['result'];

    $getPair = static function (array $prices, string $base, string $quote) {
        $target = strtoupper($base . '/' . $quote);
        foreach ($prices as $k => $v) {
            if (strtoupper((string) $k) !== $target) {
                continue;
            }

            if (is_string($v)) {
                $v = preg_replace('/[^\d\.\-]/u', '', $v);
            }

            if (!is_numeric($v)) {
                return null;
            }

            $num = (float) $v;
            if ($num <= 0.0 || !is_finite($num)) {
                return null;
            }

            return $num;
        }
        return null;
    };

    if ($needsTrx) {
        $trxIrt = $getPair($prices, 'TRX', 'IRT');
        if ($trxIrt === null) {
            error_log('Missing or invalid TRX/IRT price from SwapWallet');
            $missingKeys[] = 'TRX';
        } else {
            $result['TRX'] = round($trxIrt, 2);
        }
    }

    if ($needsTon) {
        $tonIrt = $getPair($prices, 'TON', 'IRT');
        if ($tonIrt === null) {
            $tonIrt = $getPair($prices, 'Ton', 'IRT');
        }

        if ($tonIrt === null) {
            error_log('Missing or invalid TON/IRT price from SwapWallet');
            $missingKeys[] = 'Ton';
        } else {
            $result['Ton'] = round($tonIrt, 2);
        }
    }

    if ($needsUsd) {
        $usdtIrt = $getPair($prices, 'USDT', 'IRT');
        if ($usdtIrt === null) {
            error_log('Missing or invalid USDT/IRT price from SwapWallet');
            $missingKeys[] = 'USD';
        } else {
            $result['USD'] = round($usdtIrt, 2);
        }
    }

    return ['ok' => empty($missingKeys), 'result' => $result];
}

function requireTronRates(array $keys = [])
{
    $normalizedKeys = [];
    foreach ($keys as $key) {
        $upper = strtoupper(trim((string) $key));
        if ($upper === '') {
            continue;
        }
        $normalizedKeys[$upper] = true;
    }

    $requestedKeys = array_keys($normalizedKeys);
    $rates = tronratee($requestedKeys);

    if (!is_array($rates) || !isset($rates['result']) || !is_array($rates['result'])) {
        return null;
    }

    $result = $rates['result'];

    if (isset($result['USD']) && is_numeric($result['USD'])) {
        $result['USD'] = round(abs((float) $result['USD']), 2);
    }

    $validationKeys = [];
    if (empty($requestedKeys)) {
        $validationKeys = ['TRX', 'Ton', 'USD'];
    } else {
        foreach ($requestedKeys as $requestedKey) {
            if ($requestedKey === 'TON') {
                $validationKeys[] = 'Ton';
            } elseif ($requestedKey === 'TRX' || $requestedKey === 'USD') {
                $validationKeys[] = $requestedKey;
            } else {
                $validationKeys[] = $requestedKey;
            }
        }
    }

    foreach ($validationKeys as $key) {
        if (!isset($result[$key]) || (is_numeric($result[$key]) && (float) $result[$key] == 0.0)) {
            return null;
        }
    }

    return $result;
}

function updatePaymentMessageId($response, $orderId)
{
    if (!is_array($response)) {
        error_log("Failed to send payment message for order {$orderId}: unexpected response");
        return false;
    }

    if (empty($response['ok'])) {
        error_log("Failed to send payment message for order {$orderId}: " . json_encode($response));
        return false;
    }

    if (!isset($response['result']['message_id'])) {
        error_log("Missing message_id for order {$orderId}: " . json_encode($response));
        return false;
    }

    update("Payment_report", "message_id", intval($response['result']['message_id']), "id_order", $orderId);
    return true;
}
function nowPayments($payment, $price_amount, $order_id, $order_description)
{
    global $domainhosts;
    $row = select("PaySetting", "ValuePay", "NamePay", "api_nowpayment", "select");
    $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
    if ($apinowpayments === '' || $apinowpayments === '0') {
        $row = select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller", "select");
        $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/' . $payment,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => 7000,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => 1,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments,
            'Content-Type: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'price_amount' => $price_amount,
        'price_currency' => 'usd',
        'order_id' => $order_id,
        'order_description' => $order_description,
        'ipn_callback_url' => "https://" . $domainhosts . "/payment/nowpayment.php"
    ]));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}