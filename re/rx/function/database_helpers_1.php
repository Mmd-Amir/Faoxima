<?php


if (!function_exists('rxAdminTicketFind')) {
    function rxAdminTicketFind($tracking)
    {
        return select("support_message", "*", "Tracking", $tracking);
    }

    function rxAdminTicketStatus(string $tracking, string $status): void
    {
        update("support_message", "status", $status, "Tracking", $tracking);
    }
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

if (!function_exists('faoxima_bust_bot_selectcache')) {
    function faoxima_bust_bot_selectcache($table)
    {
        $host = (string) ($GLOBALS['redis_host'] ?? '');
        if ($host === '' || !class_exists('Redis')) return;
        try {
            $client = new Redis();
            if (!$client->connect($host, (int) ($GLOBALS['redis_port'] ?? 6379), 1.0)) return;
            $password = (string) ($GLOBALS['redis_password'] ?? '');
            if ($password !== '') $client->auth($password);
            $client->select((int) ($GLOBALS['redis_database'] ?? 0));
            $rxKey = function_exists('rx_redis_key') ? 'rx_redis_key' : 'strval';
            $indexKey = $rxKey('faoxima:selectcache:tableindex:' . $table);
            $members = $client->sMembers($indexKey);
            if (is_array($members) && !empty($members)) {
                $client->del(array_map($rxKey, $members));
            }
            $client->del($indexKey);
            $client->close();
        } catch (\Throwable $e) {
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
        if (!isset($servername)) {
            $servername = $GLOBALS['dbhost'] ?? null;
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
            else $result = array_key_exists((string) $field, $row) ? $row[(string) $field] : null;
        }
        if ($useCache) $GLOBALS['rx_select_cache'][$cacheKey] = $result;
        return $result;
    }
}

if (!function_exists('step')) {
    function step($step, $from_id)
    {
        $rxNavOldStep = null;
        if (class_exists('logNavigation')) {
            $rxNavPrev = select('user', 'step', 'id', $from_id, 'select', ['cache' => false]);
            if (is_array($rxNavPrev)) {
                $rxNavOldStep = isset($rxNavPrev['step']) ? $rxNavPrev['step'] : null;
            } elseif (is_scalar($rxNavPrev)) {
                $rxNavOldStep = $rxNavPrev;
            }
        }
        update('user', 'step', $step, 'id', $from_id);
        if (class_exists('logNavigation')) {
            logNavigation::transition($from_id, $rxNavOldStep, $step);
        }
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
            'statusday' => ['script' => 'statusday.php', 'admin_label' => 'وضعیت روزانه', 'instruction' => '🕚 بررسی وضعیت روزانه — %s', 'default' => ['unit' => 'day', 'value' => 1]],
            'croncard' => ['script' => 'croncard.php', 'admin_label' => 'کارت‌به‌کارت', 'instruction' => '💳 بررسی کارت‌به‌کارت — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'notifications' => ['script' => 'NoticationsService.php', 'admin_label' => 'اعلان‌ها', 'instruction' => '🔔 ارسال اعلان‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'payment_expire' => ['script' => 'payment_expire.php', 'admin_label' => 'انقضای پرداخت', 'instruction' => '⏳ بررسی انقضای پرداخت‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'sendmessage' => ['script' => 'sendmessage.php', 'admin_label' => 'ارسال پیام', 'instruction' => '📨 ارسال پیام زمان‌بندی‌شده — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'plisio' => ['script' => 'plisio.php', 'admin_label' => 'Plisio', 'instruction' => '💰 بررسی پرداخت Plisio — %s', 'default' => ['unit' => 'minute', 'value' => 3]],
            'activeconfig' => ['script' => 'activeconfig.php', 'admin_label' => 'فعال کانفیگ', 'instruction' => '✅ فعال‌سازی تنظیمات — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'disableconfig' => ['script' => 'disableconfig.php', 'admin_label' => 'غیرفعال‌کانفیگ', 'instruction' => '⛔ غیرفعال‌سازی تنظیمات — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'backupbot' => ['script' => 'backupbot.php', 'admin_label' => 'بکاپ', 'instruction' => '📦 بکاپ‌گیری — %s', 'default' => ['unit' => 'hour', 'value' => 5]],
            'gift' => ['script' => 'gift.php', 'admin_label' => 'هدایا', 'instruction' => '🎁 ارسال هدایا — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'discount_expire' => ['script' => 'discount_expire.php', 'admin_label' => 'انقضای تخفیف', 'instruction' => '🏷 بررسی انقضای تخفیف‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 30]],
            'lottery' => ['script' => 'lottery.php', 'admin_label' => 'قرعه‌کشی شبانه', 'instruction' => '🎁 قرعه‌کشی شبانه — %s', 'default' => ['unit' => 'day', 'value' => 1]],
            'expireagent' => ['script' => 'expireagent.php', 'admin_label' => 'انقضای‌نماینده', 'instruction' => '👥 بررسی انقضای نمایندگان — %s', 'default' => ['unit' => 'minute', 'value' => 30]],
            'on_hold' => ['script' => 'on_hold.php', 'admin_label' => 'سرویس‌های معلق', 'instruction' => '⏸ بررسی سفارش‌های معلق — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'configtest' => ['script' => 'configtest.php', 'admin_label' => 'تست تنظیمات', 'instruction' => '🧪 تست تنظیمات سیستم — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'uptime_node' => ['script' => 'uptime_node.php', 'admin_label' => 'Uptime نود', 'instruction' => '🌐 بررسی Uptime نودها — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'uptime_panel' => ['script' => 'uptime_panel.php', 'admin_label' => 'Uptime پنل', 'instruction' => '🖥 بررسی Uptime پنل‌ها — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'cryptocheck' => ['script' => 'cryptocheck.php', 'admin_label' => 'چک هش کریپتو', 'instruction' => '🪙 بررسی پرداخت‌های کریپتو — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'nowpaymentcheck' => ['script' => 'nowpaymentcheck.php', 'admin_label' => 'پولر NowPayments', 'instruction' => '💎 بررسی پرداخت‌های NowPayments — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'blupalcheck' => ['script' => 'blupalcheck.php', 'admin_label' => 'پولر بلوپال', 'instruction' => '💙 بررسی پرداخت‌های بلوپال — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'atlaspaycheck' => ['script' => 'atlaspaycheck.php', 'admin_label' => 'پولر اطلس‌پی', 'instruction' => '🌐 بررسی پرداخت‌های اطلس‌پی — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'tronadocheck' => ['script' => 'tronadocheck.php', 'admin_label' => 'پولر ترونادو', 'instruction' => '🔷 بازیابی وضعیت پرداخت‌های ترونادو — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'tonpaycheck' => ['script' => 'tonpaycheck.php', 'admin_label' => 'پولر تون‌پی', 'instruction' => '💠 بررسی پرداخت‌های تون‌پی — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'rialitcheck' => ['script' => 'rialitcheck.php', 'admin_label' => 'پولر ریالیت', 'instruction' => '💚 بررسی پرداخت‌های ریالیت — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
            'remnawave_usage' => ['script' => 'remnawave_usage.php', 'admin_label' => 'مصرف رمن‌ویو', 'instruction' => '📊 بررسی مصرف کاربران رمن‌ویو — %s', 'default' => ['unit' => 'minute', 'value' => 15]],
            'logs_cleanup' => ['script' => 'logs_cleanup.php', 'admin_label' => 'پاکسازی لاگ API', 'instruction' => '🧹 پاکسازی لاگ‌های قدیمی API — %s', 'default' => ['unit' => 'day', 'value' => 7]],
            'pin_expire' => ['script' => 'pin_expire.php', 'admin_label' => 'انقضای پین پیام', 'instruction' => '📌 لغو خودکار پیام‌های پین‌شده منقضی — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'notification_expire' => ['script' => 'notification_expire.php', 'admin_label' => 'انقضای اعلان مینی‌اپ', 'instruction' => '🔔 حذف خودکار اعلان منقضی مینی‌اپ — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'ip_block_notify' => ['script' => 'ip_block_notify.php', 'admin_label' => 'اطلاع مسدودشدن IP', 'instruction' => '📶 اطلاع‌رسانی مسدودشدن IP قبلی — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'public_log_drain' => ['script' => 'public_log_drain.php', 'admin_label' => 'گزارش عمومی', 'instruction' => '📣 تخلیه صف گزارش عمومی خرید — %s', 'default' => ['unit' => 'minute', 'value' => 1]],
            'queued_renewal' => ['script' => 'QueuedRenewalProcessor.php', 'admin_label' => 'رزرو اشتراک', 'instruction' => '🔋 بررسی و فعال‌سازی رزروهای اشتراک — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'fx_rate_sync' => ['script' => 'fx_rate_sync.php', 'admin_label' => 'نرخ دلار (USDT)', 'instruction' => '💵 به‌روزرسانی نرخ USDT/IRT — %s', 'default' => ['unit' => 'minute', 'value' => 5]],
            'xui_inbound_sync' => ['script' => 'xui_inbound_sync.php', 'admin_label' => 'همگام‌سازی 3x-ui', 'instruction' => '🔄 همگام‌سازی اینباندهای کلاینت‌ها با کلاینت مرجع — %s', 'default' => ['unit' => 'minute', 'value' => 2]],
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

if (!function_exists('rx_cron_schedule_interval_seconds')) {
    function rx_cron_schedule_interval_seconds(array $config): int
    {
        $unit = strtolower((string) ($config['unit'] ?? 'minute'));
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'day') return $value * 86400;
        if ($unit === 'hour') return $value * 3600;
        return $value * 60;
    }
}

if (!function_exists('rx_cron_schedule_occurrence')) {
    function rx_cron_schedule_occurrence(array $config, int $now, int $targetHour = 0): int
    {
        $unit = strtolower((string) ($config['unit'] ?? 'minute'));
        $value = max(1, (int) ($config['value'] ?? 1));
        if ($unit === 'disabled' || $now <= 0) {
            return 0;
        }
        if ($unit !== 'day') {
            $interval = rx_cron_schedule_interval_seconds($config);
            $start = (int) strtotime(date('Y-m-d', $now) . ' 00:00:00');
            return $start + (int) (floor(($now - $start) / $interval) * $interval);
        }
        $targetHour = max(0, min(23, $targetHour));
        $candidate = strtotime(date('Y-m-d', $now) . ' 00:00:00') + ($targetHour * 3600);
        if ($candidate > $now) {
            $candidate = strtotime('-1 day', $candidate);
        }
        for ($i = 0; $i < 370; $i++) {
            if (((int) date('z', $candidate) % $value) === 0) {
                break;
            }
            $candidate = strtotime('-1 day', $candidate);
        }
        return (int) $candidate;
    }
}

if (!function_exists('rx_cron_job_due')) {
    function rx_cron_job_due(string $jobKey, array $config, int $now, array $runtimeState, int $targetHour = 0): ?int
    {
        $unit = strtolower((string) ($config['unit'] ?? 'minute'));
        if ($unit === 'disabled' || $jobKey === '') {
            return null;
        }
        $lastRun = isset($runtimeState[$jobKey]) ? (int) $runtimeState[$jobKey] : 0;
        if ($lastRun > $now + 300) {
            $lastRun = 0;
        }
        if ($unit === 'day') {
            $occurrence = rx_cron_schedule_occurrence($config, $now, $targetHour);
            if ($occurrence <= 0 || $now - $occurrence > 3600 || $lastRun >= $occurrence) {
                return null;
            }
            return $occurrence;
        }
        $occurrence = rx_cron_schedule_occurrence($config, $now, $targetHour);
        return $occurrence > 0 && $now - $occurrence <= 3600 && $lastRun < $occurrence ? $occurrence : null;
    }
}

if (!function_exists('rx_cron_claim_job_occurrence')) {
    function rx_cron_claim_job_occurrence(PDO $pdo, string $jobKey, int $occurrence): bool
    {
        if (trim($jobKey) === '' || $occurrence <= 0) {
            return false;
        }
        $definition = getCronJobDefinitions()[$jobKey] ?? [];
        $default = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
        $unit = (string) ($default['unit'] ?? 'minute');
        $value = max(1, (int) ($default['value'] ?? 1));
        $enabled = $unit === 'disabled' ? 0 : 1;
        try {
            ensureCronRuntimeStateTable($pdo);
            $stmt = $pdo->prepare('INSERT INTO cron_runtime_state (job_key, last_run, unit, value, enabled) VALUES (:job_key, :last_run, :unit, :value, :enabled) ON DUPLICATE KEY UPDATE last_run = IF(last_run < VALUES(last_run), VALUES(last_run), last_run)');
            $stmt->execute([':job_key' => $jobKey, ':last_run' => $occurrence, ':unit' => $unit, ':value' => $value, ':enabled' => $enabled]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
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
        $definition = getCronJobDefinitions()[$jobKey] ?? [];
        $default = $definition['default'] ?? ['unit' => 'minute', 'value' => 1];
        $unit = (string) ($default['unit'] ?? 'minute');
        $value = max(1, (int) ($default['value'] ?? 1));
        $enabled = $unit === 'disabled' ? 0 : 1;
        $stmt = $pdo->prepare('INSERT INTO cron_runtime_state (job_key, last_run, unit, value, enabled) VALUES (:job_key, :last_run, :unit, :value, :enabled) ON DUPLICATE KEY UPDATE last_run = VALUES(last_run)');
        $stmt->execute([':job_key' => $jobKey, ':last_run' => $timestamp, ':unit' => $unit, ':value' => $value, ':enabled' => $enabled]);
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
            return rx_cron_job_due($jobKey, $config, $now, [$jobKey => $lastRun]) !== null;
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
        $occurrence = rx_cron_job_due($jobKey, $config, $now, [$jobKey => $lastRun]);
        if ($occurrence === null) {
            return false;
        }

        try {
            return rx_cron_claim_job_occurrence($pdo, $jobKey, $occurrence);
        } catch (\Throwable $e) {
            return false;
        }
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
            if ($key === 'backupbot') {
                $command = 'php ' . escapeshellarg(REFACTORED_LEGACY_ROOT . '/cronbot/backupbot.php');
                $parts[] = "<b>{$title}</b>\n<code>" . htmlspecialchars($command, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
                continue;
            }
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
        $rows[] = [['text' => '⚙️ تنظیم ورکرها', 'callback_data' => 'cronworkers_settings']];
        $rows[] = [['text' => '🔙 بازگشت به تنظیمات کرون', 'callback_data' => 'featcat_crons']];
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

if (!function_exists('rx_gateway_payment_method_map')) {
    function rx_gateway_payment_method_map()
    {
        return [
            'zarinpal'    => 'zarinpal',
            'plisio'      => 'plisio',
            'nowpayment'  => 'nowpayment',
            'iranpay2'    => 'Currency Rial 2',
            'tonpay'      => 'tonpay',
            'rialit'      => 'rialit',
            'blupal'      => 'blupal',
            'atlaspay'    => 'atlaspay',
            'cubepay'     => 'cubepay',
            'variza'      => 'variza',
            'abangateway' => 'abangateway',
        ];
    }
}

if (!function_exists('rx_gateway_stale_minutes')) {
    function rx_gateway_stale_minutes($paymentMethod)
    {
        if ($paymentMethod === 'tonpay') {
            return 1440;
        }
        if ($paymentMethod === 'rialit') {
            return 1440;
        }
        return 30;
    }
}

if (!function_exists('rx_purge_stale_gateway_invoices')) {
    function rx_purge_stale_gateway_invoices($userId, $paymentMethod)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        if ($paymentMethod === 'rialit') {
            return;
        }
        try {
            $rows = $pdo->prepare(
                "SELECT id_order, time FROM Payment_report
                  WHERE id_user = :u
                    AND Payment_Method = :m
                    AND payment_Status IN ('Unpaid','pending','waiting')"
            );
            $rows->execute([':u' => (string) $userId, ':m' => $paymentMethod]);
            $result = $rows->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[rx_purge_stale_gateway_invoices] fetch failed: ' . $e->getMessage());
            return;
        }
        if (empty($result)) {
            return;
        }

        $cutoff = time() - (rx_gateway_stale_minutes($paymentMethod) * 60);
        $stale = [];
        foreach ($result as $row) {
            $ts = isValidDate((string) ($row['time'] ?? '')) ? strtotime(str_replace('/', '-', (string) $row['time'])) : false;
            if ($ts === false || $ts <= $cutoff) {
                $stale[] = (string) $row['id_order'];
            }
        }
        if ($paymentMethod === 'tonpay' && function_exists('tonpayCustomPurgeProtected')) {
            $stale = array_values(array_filter($stale, static function ($staleOrderId) {
                return !tonpayCustomPurgeProtected($staleOrderId);
            }));
        }
        if ($paymentMethod === 'atlaspay' && function_exists('atlaspayCustomPurgeProtected')) {
            $stale = array_values(array_filter($stale, static function ($staleOrderId) {
                return !atlaspayCustomPurgeProtected($staleOrderId);
            }));
        }
        if (empty($stale)) {
            return;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($stale), '?'));
            $sql = "UPDATE Payment_report
                       SET payment_Status = 'expire'
                     WHERE id_user = ?
                       AND Payment_Method = ?
                       AND payment_Status IN ('Unpaid','pending','waiting')
                       AND id_order IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([(string) $userId, $paymentMethod], $stale));
        } catch (Throwable $e) {
            error_log('[rx_purge_stale_gateway_invoices] update failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('findPendingGatewayInvoice')) {
    function findPendingGatewayInvoice($userId, $paymentMethod)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }

        rx_purge_stale_gateway_invoices($userId, $paymentMethod);

        try {
            $stmt = $pdo->prepare(
                "SELECT id_order, Payment_Method, payment_Status, price, time
                   FROM Payment_report
                  WHERE id_user = :u
                    AND Payment_Method = :m
                    AND payment_Status IN ('Unpaid','pending','waiting')
                  ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([':u' => (string) $userId, ':m' => $paymentMethod]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[findPendingGatewayInvoice] query failed: ' . $e->getMessage());
            return null;
        }

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('cancelPendingGatewayInvoice')) {
    function cancelPendingGatewayInvoice($userId, $paymentMethod, $orderId)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }

        if ($paymentMethod === 'rialit') {
            return false;
        }

        try {
            $report = $pdo->prepare(
                "SELECT id_order, Payment_Method, payment_Status, atlaspay_order_id
                   FROM Payment_report
                  WHERE id_order = :o
                    AND id_user = :u
                    AND Payment_Method = :m
                    AND payment_Status IN ('Unpaid','pending','waiting')
                  LIMIT 1"
            );
            $report->execute([':o' => $orderId, ':u' => (string) $userId, ':m' => $paymentMethod]);
            $row = $report->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[cancelPendingGatewayInvoice] lookup failed: ' . $e->getMessage());
            return false;
        }
        if (!is_array($row)) {
            return false;
        }

        if ($paymentMethod === 'tonpay' && function_exists('tonpayCustomFetchReport')) {
            $customReport = tonpayCustomFetchReport($orderId);
            if ($customReport !== null) {
                $customResult = tonpayCustomCancel($orderId, (string) $userId);
                return !empty($customResult['ok']);
            }
        }

        if ($paymentMethod === 'atlaspay' && function_exists('atlaspayCustomFetchReport')) {
            $customReport = atlaspayCustomFetchReport($orderId);
            if ($customReport !== null) {
                $customResult = atlaspayCustomCancel($orderId, (string) $userId);
                return !empty($customResult['ok']);
            }
        }

        if ($paymentMethod === 'atlaspay' && function_exists('atlaspayCancelOrder')) {
            $atlaspayOrderId = trim((string) ($row['atlaspay_order_id'] ?? ''));
            if ($atlaspayOrderId !== '') {
                try {
                    atlaspayCancelOrder($atlaspayOrderId);
                } catch (Throwable $e) {
                    error_log('[cancelPendingGatewayInvoice] atlaspayCancelOrder failed: ' . $e->getMessage());
                }
            }
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET payment_Status = 'cancelled'
                  WHERE id_order = :o
                    AND id_user = :u
                    AND Payment_Method = :m
                    AND payment_Status IN ('Unpaid','pending','waiting')"
            );
            $stmt->execute([':o' => $orderId, ':u' => (string) $userId, ':m' => $paymentMethod]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[cancelPendingGatewayInvoice] update failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('rx_gateway_display_name')) {
    function rx_gateway_display_name($gatewayKey)
    {
        $labels = [
            'zarinpal'    => 'زرین‌پال',
            'plisio'      => 'Plisio',
            'nowpayment'  => 'NowPayments',
            'iranpay2'    => 'ایران‌پی',
            'tonpay'      => 'تون‌پی',
            'rialit'      => '💚 ریالیت',
            'blupal'      => 'بلوپال',
            'atlaspay'    => 'اطلس‌پی',
            'cubepay'     => 'کیوب‌پی',
            'variza'      => 'واریزا',
            'abangateway' => 'آبان گیت‌وی',
        ];
        return $labels[$gatewayKey] ?? $gatewayKey;
    }
}

if (!function_exists('rx_send_pending_gateway_invoice_notice')) {
    function rx_send_pending_gateway_invoice_notice($from_id, $message_id, array $pending, $gatewayKey)
    {
        global $datatextbot;

        $orderId = (string) ($pending['id_order'] ?? '');
        $priceFmt = number_format((int) ($pending['price'] ?? 0));
        $text = sprintf(
            $datatextbot['dyn_pending_gateway_invoice_notice'] ?? "⏳ شما یک فاکتور پرداخت‌نشده در همین درگاه دارید.\n\n🛒 کد فاکتور: <code>%s</code>\n💰 مبلغ: %s تومان\n\nمی‌توانید منتظر بررسی همین فاکتور بمانید یا آن را لغو کرده و فاکتور جدید بسازید.",
            htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8'),
            $priceFmt
        );
        $kb = json_encode([
            'inline_keyboard' => [
                [rx_payment_button((string) $gatewayKey, 'pending_cancel', ['text' => '♻️ لغو و دریافت فاکتور جدید', 'callback_data' => 'rxpgw_cancel_' . $gatewayKey . '_' . $orderId])],
                [rx_payment_button((string) $gatewayKey, 'back_main', ['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'backuser'])],
            ],
        ], JSON_UNESCAPED_UNICODE);

        if (intval($message_id) > 0) {
            Editmessagetext($from_id, $message_id, $text, $kb, 'HTML');
        } else {
            sendmessage($from_id, $text, $kb, 'HTML');
        }
    }
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

if (!function_exists('rx_broadcast_token')) {
    function rx_broadcast_token(array $info): string
    {
        return substr(md5(
            ($info['id_admin'] ?? '') . '|' . ($info['id_message'] ?? '') . '|' .
            ($info['type'] ?? '') . '|' . ($info['message'] ?? '')
        ), 0, 12);
    }
}

if (!function_exists('rx_broadcast_is_paused')) {
    function rx_broadcast_is_paused($status): bool
    {
        return is_string($status) && strpos($status, 'paused_') === 0;
    }
}

if (!function_exists('rx_broadcast_classify')) {
    function rx_broadcast_classify($resp): array
    {
        $r = ['class' => 'permanent_user', 'temporary' => false, 'code' => 0, 'retry_after' => 0, 'detail' => '', 'manual' => false];
        if (!is_array($resp)) {
            return ['class' => 'temporary_network', 'temporary' => true, 'detail' => 'no_response'] + $r;
        }
        if (!empty($resp['ok'])) {
            return ['class' => 'success'] + $r;
        }
        $code = (int) ($resp['error_code'] ?? 0);
        $desc = strtolower((string) ($resp['description'] ?? ''));
        $r['code'] = $code;
        if ($code === 429) {
            return ['class' => 'temporary_telegram', 'temporary' => true, 'detail' => 'rate_limit', 'retry_after' => max(1, (int) ($resp['parameters']['retry_after'] ?? 1))] + $r;
        }
        if (preg_match('/message to (forward|copy) not found|message_id_invalid|can\'t parse entities|message is too long|message text is empty|text must be non-empty/', $desc)) {
            return ['class' => 'system_error', 'temporary' => true, 'manual' => true, 'detail' => 'message_invalid'] + $r;
        }
        if ($code >= 500) {
            return ['class' => 'temporary_telegram', 'temporary' => true, 'detail' => 'http_' . $code] + $r;
        }
        if ($code === 401 || $code === 404) {
            return ['class' => 'system_error', 'temporary' => true, 'manual' => true, 'detail' => 'telegram_auth'] + $r;
        }
        if (strpos($desc, 'invalid response') !== false) {
            return ['class' => 'temporary_network', 'temporary' => true, 'detail' => 'invalid_response'] + $r;
        }
        if ($code === 0 && $desc !== '') {
            if (strpos($desc, 'timed out') !== false || strpos($desc, 'timeout') !== false) {
                $detail = 'timeout';
            } elseif (strpos($desc, 'resolve') !== false) {
                $detail = 'dns';
            } elseif (strpos($desc, 'refused') !== false) {
                $detail = 'refused';
            } elseif (strpos($desc, 'connect') !== false) {
                $detail = 'connect';
            } elseif (strpos($desc, 'reset') !== false || strpos($desc, 'recv failure') !== false || strpos($desc, 'send failure') !== false) {
                $detail = 'reset';
            } elseif (strpos($desc, 'ssl') !== false) {
                $detail = 'ssl';
            } else {
                $detail = 'network';
            }
            return ['class' => 'temporary_network', 'temporary' => true, 'detail' => $detail] + $r;
        }
        return $r;
    }
}

if (!function_exists('rx_broadcast_classify_exception')) {
    function rx_broadcast_classify_exception(\Throwable $e): array
    {
        $m = $e->getMessage();
        $r = ['class' => 'system_error', 'temporary' => true, 'code' => 0, 'retry_after' => 0, 'detail' => 'worker_exception', 'manual' => false];
        if ($e instanceof \PDOException || preg_match('/SQLSTATE|mysql|gone away|lost connection|too many connections|max_user_connections|\b(1040|1203|2002|2006|2013)\b/i', $m)) {
            if (preg_match('/too many connections|max_user_connections|\b1040\b|\b1203\b/i', $m)) {
                $detail = 'too_many_connections';
            } elseif (preg_match('/gone away|\b2006\b/i', $m)) {
                $detail = 'db_gone';
            } elseif (preg_match('/lost connection|\b2013\b/i', $m)) {
                $detail = 'db_lost';
            } elseif (preg_match('/refused|\b2002\b/i', $m)) {
                $detail = 'db_refused';
            } else {
                $detail = 'db_error';
            }
            return ['class' => 'temporary_database', 'detail' => $detail] + $r;
        }
        return $r;
    }
}

function rx_broadcast_pause_reason(string $class, int $code, array $classification): array
{
    if ($class === 'temporary_telegram' && $code === 429) {
        return ['paused_rate_limit', 'telegram_429'];
    }
    if ($class === 'temporary_telegram' || $class === 'temporary_network') {
        return ['paused_network', $code >= 500 ? 'telegram_5xx' : 'network'];
    }
    if ($class === 'temporary_database') {
        return ['paused_database', 'database'];
    }
    $detail = $classification['detail'] ?? '';
    return ['paused_system', in_array($detail, ['message_invalid', 'telegram_auth'], true) ? (string) $detail : 'system'];
}

if (!function_exists('rx_broadcast_apply_pause')) {
    function rx_broadcast_apply_pause(array &$c, array $cls, ?int $now = null): void
    {
        $now = $now ?? time();
        $class = (string) ($cls['class'] ?? 'system_error');
        $code = (int) ($cls['code'] ?? 0);
        [$status, $reason] = rx_broadcast_pause_reason($class, $code, $cls);
        $retryAfter = (int) ($cls['retry_after'] ?? 0);
        $current = (string) ($c['status'] ?? '');
        $currentUntil = (int) ($c['pause_until'] ?? 0);
        if (rx_broadcast_is_paused($current) && (!empty($c['manual_resume']) || $currentUntil > $now)) {
            if ($status === 'paused_rate_limit' && $current === 'paused_rate_limit' && empty($c['manual_resume'])) {
                $c['pause_until'] = max($currentUntil, $now + max(1, $retryAfter) + 1);
                $c['retry_after'] = max((int) ($c['retry_after'] ?? 0), $retryAfter);
            }
            return;
        }
        $streak = (int) ($c['temp_streak'] ?? 0) + 1;
        $manual = !empty($cls['manual']);
        if ($status === 'paused_rate_limit') {
            $wait = max(1, $retryAfter) + 1;
        } else {
            $wait = (int) min(300, 30 * (2 ** min(4, $streak - 1)));
            if ($streak >= 6) {
                $manual = true;
            }
        }
        $c['status'] = $status;
        $c['pause_reason'] = $reason;
        $c['pause_detail'] = (string) ($cls['detail'] ?? '');
        $c['pause_until'] = $manual ? 0 : $now + $wait;
        $c['manual_resume'] = $manual;
        $c['last_error_type'] = $class;
        $c['last_error_code'] = $code;
        $c['last_error_at'] = $now;
        $c['retry_after'] = $retryAfter;
        $c['paused_at'] = $now;
        $c['temp_streak'] = $streak;
    }
}

if (!function_exists('rx_broadcast_resume_now')) {
    function rx_broadcast_resume_now(array &$c, bool $manual): bool
    {
        if (!rx_broadcast_is_paused($c['status'] ?? '')) {
            return false;
        }
        $c['status'] = 'running';
        $c['pause_until'] = 0;
        $c['manual_resume'] = false;
        $c['resume_count'] = (int) ($c['resume_count'] ?? 0) + 1;
        $c['resumed_at'] = time();
        if ($manual) {
            $c['temp_streak'] = 0;
        }
        return true;
    }
}

if (!function_exists('rx_broadcast_work_available')) {
    function rx_broadcast_work_available(?string $infoFile = null): ?bool
    {
        if ($infoFile === null || $infoFile === '') {
            $infoFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'cronbot' . DIRECTORY_SEPARATOR . 'info';
        }
        if (!is_file($infoFile)) {
            return false;
        }
        $raw = @file_get_contents($infoFile);
        if ($raw === false) {
            return null;
        }
        $info = json_decode($raw, true);
        if (!is_array($info)) {
            return null;
        }
        $status = (string) ($info['status'] ?? '');
        if ($status === 'cancelled' || $status === 'finished') {
            return false;
        }
        if (rx_broadcast_is_paused($status)) {
            $until = (int) ($info['pause_until'] ?? 0);
            if (!empty($info['manual_resume']) || $until <= 0 || $until > time()) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('rx_cron_table_exists')) {
    function rx_cron_table_exists(PDO $pdo, string $table): ?bool
    {
        static $cache = [];
        $key = strtolower($table);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            $stmt->execute([$table]);
            $exists = $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            $exists = null;
        }
        $cache[$key] = $exists;
        return $exists;
    }
}

if (!function_exists('rx_cron_work_probe')) {
    function rx_cron_work_probe(PDO $pdo, string $sql, array $params = [], ?string $table = null): ?bool
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            if ($table !== null && $table !== '' && rx_cron_table_exists($pdo, $table) === false) {
                return false;
            }
            return null;
        }
    }
}

if (!function_exists('rx_cron_work_combine')) {
    function rx_cron_work_combine(array $results): ?bool
    {
        $unknown = false;
        foreach ($results as $result) {
            if (is_callable($result)) {
                try {
                    $result = $result();
                } catch (Throwable $e) {
                    $result = null;
                }
            }
            if ($result === true) {
                return true;
            }
            if ($result !== false) {
                $unknown = true;
            }
        }
        return $unknown ? null : false;
    }
}

if (!function_exists('rx_cron_job_work_available')) {
    function rx_cron_job_work_available(string $jobKey, ?PDO $pdo = null): ?bool
    {
        $pdo = $pdo instanceof PDO ? $pdo : (function_exists('getDatabaseConnection') ? getDatabaseConnection() : null);
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $now = time();
        $paymentWindow = $jobKey === 'payment_expire' ? '1=1' : (function_exists('rx_pf_cron_window_sql') ? rx_pf_cron_window_sql() : '1=0');
        if ($jobKey === 'sendmessage') {
            return rx_broadcast_work_available();
        }
        if ($jobKey === 'gift') {
            $cronbot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'cronbot';
            if (is_file($cronbot . DIRECTORY_SEPARATOR . 'gift') && is_file($cronbot . DIRECTORY_SEPARATOR . 'username.json')) {
                return true;
            }
            $pendingFile = $cronbot . DIRECTORY_SEPARATOR . 'gift_notify_pending.json';
            if (is_file($pendingFile)) {
                $pending = json_decode((string) @file_get_contents($pendingFile), true);
                if (is_array($pending)) {
                    foreach ($pending as $entry) {
                        if (is_array($entry) && ($entry['state'] ?? '') === 'pending'
                            && (int) ($entry['next_attempt_at'] ?? 0) <= $now
                            && (int) ($entry['created_at'] ?? 0) >= $now - 3600
                            && (int) ($entry['created_at'] ?? 0) <= $now) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }
        switch ($jobKey) {
            case 'activeconfig':
                return rx_cron_work_probe($pdo, "SELECT 1 FROM user WHERE checkstatus = '1' LIMIT 1");
            case 'disableconfig':
                return rx_cron_work_probe($pdo, "SELECT 1 FROM user WHERE checkstatus = '2' LIMIT 1");
            case 'configtest':
                return rx_cron_work_probe($pdo, "SELECT 1 FROM invoice WHERE name_product = ? AND status != 'disabled' LIMIT 1", ['سرویس تست']);
            case 'notifications':
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')"
                    . " AND name_product != ? AND (time_cron <= ? OR time_cron IS NULL) LIMIT 1",
                    ['سرویس تست', (string) ($now - 3600)]
                );
            case 'plisio':
                return rx_cron_work_probe($pdo, "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid' AND Payment_Method = 'plisio' LIMIT 1");
            case 'blupalcheck':
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid' AND Payment_Method = 'blupal'"
                    . " AND blupal_invoice_id IS NOT NULL AND blupal_invoice_id <> '' LIMIT 1"
                );
            case 'nowpaymentcheck':
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid' AND Payment_Method = 'nowpayment'"
                    . " AND dec_not_confirmed IS NOT NULL AND dec_not_confirmed <> '' LIMIT 1"
                );
            case 'tronadocheck':
                if (!defined('TRONADO_V2_WINDOW_SECONDS') || !defined('TRONADO_V2_RECENT_SECONDS')
                    || !defined('TRONADO_V2_POLL_RECENT_SECONDS') || !defined('TRONADO_V2_POLL_OLD_SECONDS')
                    || !defined('RX_PAYMENT_PROCESSING_VERSION')) {
                    return null;
                }
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method IN ('Currency Rial 2','iranpay2')"
                    . " AND payment_processing_version >= " . (int) RX_PAYMENT_PROCESSING_VERSION
                    . " AND payment_Status IN ('Unpaid','pending','waiting','expire','reject')"
                    . " AND (tronado_local_state IS NULL OR tronado_local_state NOT IN ('manual_review','dispute_review','completed'))"
                    . " AND tronado_created_at >= ?"
                    . " AND ("
                    . "   (tronado_created_at >= ? AND (tronado_last_checked_at IS NULL OR tronado_last_checked_at <= ?))"
                    . "   OR (tronado_created_at < ? AND (tronado_last_checked_at IS NULL OR tronado_last_checked_at <= ?))"
                    . " ) LIMIT 1",
                    [
                        (int) ($now - TRONADO_V2_WINDOW_SECONDS),
                        (int) ($now - TRONADO_V2_RECENT_SECONDS),
                        (int) ($now - TRONADO_V2_POLL_RECENT_SECONDS),
                        (int) ($now - TRONADO_V2_RECENT_SECONDS),
                        (int) ($now - TRONADO_V2_POLL_OLD_SECONDS),
                    ]
                );
            case 'tonpaycheck':
                if (!defined('TONPAY_CUSTOM_LATE_RECONCILE_SECONDS') || !defined('TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS')) {
                    return null;
                }
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method = 'tonpay' AND ("
                    . " (tonpay_invoice_id IS NOT NULL AND tonpay_invoice_id <> ''"
                    . " AND (payment_Status = 'Unpaid'"
                    . "   OR (tonpay_gateway_mode = 'custom' AND payment_Status IN ('expire','reject','cancelled')"
                    . "       AND (tonpay_local_state IS NULL OR tonpay_local_state <> 'closed')"
                    . "       AND tonpay_created_at >= ?)))"
                    . " OR (tonpay_gateway_mode = 'custom' AND payment_Status = 'Unpaid' AND ("
                    . "   (tonpay_receipt_submitted_at IS NOT NULL AND tonpay_receipt_submitted_at > 0 AND tonpay_review_deadline_at < ?)"
                    . "   OR ((tonpay_receipt_submitted_at IS NULL OR tonpay_receipt_submitted_at = 0) AND tonpay_created_at < ?)"
                    . " )) ) LIMIT 1",
                    [(int) ($now - TONPAY_CUSTOM_LATE_RECONCILE_SECONDS), (int) $now, (int) ($now - TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS)]
                );
            case 'rialitcheck':
                return rx_cron_work_combine([
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM rialit_webhook_event WHERE state IN ('pending','retry') AND next_attempt_at <= ? AND EXISTS (SELECT 1 FROM Payment_report WHERE Payment_report.id_order = rialit_webhook_event.order_id AND $paymentWindow) LIMIT 1",
                        [$now],
                        'rialit_webhook_event'
                    ),
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method = 'rialit'"
                        . " AND payment_Status IN ('Unpaid','pending','waiting','expire','cancelled')"
                        . " AND rialit_created_at >= ?"
                        . " AND (rialit_last_checked_at IS NULL OR rialit_last_checked_at <= ?) LIMIT 1",
                        [(int) ($now - 172800), (int) ($now - 120)]
                    ),
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method = 'rialit'"
                        . " AND payment_Status = 'paid' AND COALESCE(direct_payment_done, 0) = 0 LIMIT 1"
                    ),
                ]);
            case 'atlaspaycheck':
                if (!defined('ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS') || !defined('ATLASPAY_CUSTOM_CARD_RETENTION_SECONDS')
                    || !defined('RX_PAYMENT_PROCESSING_VERSION')) {
                    return null;
                }
                $rxLate = (int) ($now - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS);
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM Payment_report WHERE $paymentWindow AND Payment_Method = 'atlaspay' AND ("
                    . " (atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> '' AND ("
                    . "   ((atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND payment_Status = 'Unpaid')"
                    . "   OR ((atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND payment_Status = 'expire'"
                    . "       AND payment_processing_version >= " . (int) RX_PAYMENT_PROCESSING_VERSION
                    . "       AND atlaspay_created_at >= ?)"
                    . "   OR (atlaspay_gateway_mode = 'custom' AND payment_Status = 'Unpaid')"
                    . "   OR (atlaspay_gateway_mode = 'custom' AND payment_Status IN ('expire','reject')"
                    . "       AND (atlaspay_local_state IS NULL OR atlaspay_local_state <> 'closed')"
                    . "       AND atlaspay_created_at >= ?)"
                    . " ))"
                    . " OR (atlaspay_gateway_mode = 'custom' AND atlaspay_card_number IS NOT NULL AND atlaspay_created_at < ?)"
                    . " ) LIMIT 1",
                    [$rxLate, $rxLate, (int) ($now - ATLASPAY_CUSTOM_CARD_RETENTION_SECONDS)]
                );
            case 'queued_renewal':
                $pendingFile = REFACTORED_LEGACY_ROOT . '/cronbot/.runtime/renewal_notify_pending.json';
                if (is_file($pendingFile)) {
                    $pending = json_decode((string) @file_get_contents($pendingFile), true);
                    if (is_array($pending)) {
                        foreach ($pending as $entry) {
                            if (is_array($entry) && ($entry['state'] ?? '') === 'pending' && (int) ($entry['next_attempt_at'] ?? 0) <= $now
                                && (int) ($entry['created_at'] ?? 0) >= $now - 3600
                                && (int) ($entry['created_at'] ?? 0) <= $now) {
                                return true;
                            }
                        }
                    }
                }
                return rx_cron_work_combine([
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM queued_renewal WHERE status IN ('pending','retry','reconciling')"
                        . " AND (next_attempt_at IS NULL OR next_attempt_at <= " . (int) $now . ") LIMIT 1"
                    ),
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM queued_renewal WHERE status = 'processing'"
                        . " AND lease_expires_at IS NOT NULL AND lease_expires_at < " . (int) $now . " LIMIT 1"
                    ),
                    static fn() => rx_cron_work_probe($pdo, "SELECT 1 FROM renewal_operation WHERE state IN ('applying','uncertain') LIMIT 1"),
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM renewal_operation WHERE source = 'admin' AND state = 'verified'"
                        . " AND finalize_state IS NOT NULL"
                        . " AND (finalize_state = 'pending' OR notify_state IN ('pending','failed','sending')) LIMIT 1"
                    ),
                ]);
            case 'payment_expire':
                $rxCutoff = date('Y/m/d H:i:s', $now - 1800);
                $rxTonpayCutoff = date('Y/m/d H:i:s', $now - 86400);
                $rxReceiptCutoff = date('Y/m/d H:i:s', $now - 600);
                return rx_cron_work_combine([
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid'"
                        . " AND (crypto_currency IS NULL OR crypto_currency = '')"
                        . " AND Payment_Method != 'tonpay' AND Payment_Method != 'rialit' AND time < ? LIMIT 1",
                        [$rxCutoff]
                    ),
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM card_sms_payment csp INNER JOIN Payment_report pr ON pr.id_order = csp.id_order"
                        . " WHERE csp.state = 'open' AND csp.deposit_id IS NULL AND csp.expires_at <= ?"
                        . " AND pr.payment_Status = 'Unpaid' LIMIT 1",
                        [(int) $now],
                        'card_sms_payment'
                    ),
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'Unpaid' AND Payment_Method = 'tonpay' AND time < ? LIMIT 1",
                        [$rxTonpayCutoff]
                    ),
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM checkout_order WHERE status IN ('awaiting_choice','confirmed')"
                        . " AND expires_at > 0 AND expires_at <= ? LIMIT 1",
                        [(int) $now]
                    ),
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Payment_report WHERE $paymentWindow AND payment_Status = 'pending'"
                        . " AND dec_not_confirmed = 'receipt-uploading'"
                        . " AND COALESCE(NULLIF(at_updated, ''), time) < ? LIMIT 1",
                        [$rxReceiptCutoff]
                    ),
                ]);
            case 'remnawave_usage':
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM remnawave_users WHERE status = 'active'"
                    . " AND panel_user_id IS NOT NULL AND panel_user_id <> '' LIMIT 1",
                    [],
                    'remnawave_users'
                );
            case 'pin_expire':
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM pinned_messages WHERE unpin_at IS NOT NULL AND unpin_at <= ? LIMIT 1",
                    [(int) $now]
                );
            case 'notification_expire':
                return rx_cron_work_probe($pdo, "SELECT 1 FROM notification WHERE expires_at <= ? LIMIT 1", [(int) $now]);
            case 'public_log_drain':
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM PublicLog_Queue WHERE (sent_at IS NULL AND attempts < 5)"
                    . " OR (sent_at IS NOT NULL AND sent_at < (NOW() - INTERVAL 3 DAY)) LIMIT 1"
                );
            case 'xui_inbound_sync':
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM xui_sync_panel WHERE enabled = 1"
                    . " OR (enabled = 0 AND reference_email IS NOT NULL AND reference_email <> '' AND last_check_at < ?) LIMIT 1",
                    [(int) ($now - 600)]
                );
            case 'expireagent':
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM user WHERE expire IS NOT NULL AND expire < ? LIMIT 1",
                    [(int) $now]
                );
            case 'discount_expire':
                return rx_cron_work_combine([
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM DiscountSell WHERE (status IS NULL OR status = 'active')"
                        . " AND time IS NOT NULL AND time <> '' AND time <> '0'"
                        . " AND CAST(time AS UNSIGNED) <= ? LIMIT 1",
                        [(int) $now]
                    ),
                    static fn() => rx_cron_work_probe(
                        $pdo,
                        "SELECT 1 FROM Discount WHERE (status IS NULL OR status = 'active')"
                        . " AND expire_at IS NOT NULL AND expire_at <> '' AND expire_at <> '0'"
                        . " AND CAST(expire_at AS UNSIGNED) <= ? LIMIT 1",
                        [(int) $now]
                    ),
                ]);
            case 'ip_block_notify':
                return rx_cron_work_probe(
                    $pdo,
                    "SELECT 1 FROM invoice INNER JOIN marzban_panel ON marzban_panel.name_panel = invoice.Service_location"
                    . " WHERE marzban_panel.type IN ('x-ui_single','rebecca') AND marzban_panel.ip_limit_guard = 'onipguard'"
                    . " AND invoice.Status = 'active' AND invoice.ip_limit IS NOT NULL AND invoice.ip_limit != '0' LIMIT 1"
                );
        }
        return null;
    }
}

if (!function_exists('rx_broadcast_info_update')) {
    function rx_broadcast_info_update(string $infoFile, ?string $token, callable $mutator): ?array
    {
        $lf = @fopen($infoFile . '.wlock', 'c');
        if ($lf) {
            @flock($lf, LOCK_EX);
        }
        $result = null;
        $cur = is_file($infoFile) ? json_decode((string) @file_get_contents($infoFile), true) : null;
        if (is_array($cur) && ($token === null || rx_broadcast_token($cur) === $token)) {
            if ($mutator($cur) !== false) {
                $tmp = $infoFile . '.tmp';
                if (@file_put_contents($tmp, json_encode($cur, JSON_UNESCAPED_UNICODE)) !== false) {
                    @rename($tmp, $infoFile);
                }
            }
            $result = $cur;
        }
        if ($lf) {
            @flock($lf, LOCK_UN);
            @fclose($lf);
        }
        return $result;
    }
}

if (!function_exists('rx_broadcast_cleanup_token')) {
    function rx_broadcast_cleanup_token(string $cronbotDir, string $token): void
    {
        if (!preg_match('/^[0-9a-f]{12}$/', $token)) {
            return;
        }
        foreach (glob($cronbotDir . '/users.txt.*') ?: [] as $f) {
            if (strpos(basename($f), '.' . $token . '.') !== false) {
                @unlink($f);
            }
        }
    }
}

if (!function_exists('rx_broadcast_cancel')) {
    function rx_broadcast_cancel(string $cronbotDir): ?string
    {
        $infoFile = $cronbotDir . '/info';
        $lf = @fopen($infoFile . '.wlock', 'c');
        if ($lf) {
            @flock($lf, LOCK_EX);
        }
        $cur = is_file($infoFile) ? json_decode((string) @file_get_contents($infoFile), true) : null;
        $token = is_array($cur) ? rx_broadcast_token($cur) : null;
        @unlink($infoFile);
        @unlink($infoFile . '.tmp');
        foreach (['users.txt', 'users.json', 'users.txt.new', 'users.txt.tail.tmp', 'users.txt.recover.tmp'] as $f) {
            @unlink($cronbotDir . '/' . $f);
        }
        if ($token !== null) {
            rx_broadcast_cleanup_token($cronbotDir, $token);
        }
        if ($lf) {
            @flock($lf, LOCK_UN);
            @fclose($lf);
        }
        return $token;
    }
}

if (!function_exists('rx_broadcast_resume_button')) {
    function rx_broadcast_resume_button(string $token): array
    {
        return ['text' => "▶️ ادامه عملیات", 'callback_data' => 'broadcast_resume_' . $token];
    }
}

if (!function_exists('rx_broadcast_pause_text')) {
    function rx_broadcast_pause_text(array $info): string
    {
        $titles = [
            'telegram_429'    => 'محدودیت نرخ ارسال تلگرام',
            'telegram_5xx'    => 'خطای موقت سرور تلگرام',
            'network'         => 'مشکل اتصال شبکه / تلگرام',
            'database'        => 'دیتابیس موقتاً در دسترس نیست',
            'system'          => 'ورکر موقتاً در دسترس نیست',
            'message_invalid' => 'پیام توسط تلگرام پذیرفته نشد',
            'telegram_auth'   => 'توکن ربات توسط تلگرام رد شد',
        ];
        $details = [
            'rate_limit'           => 'Too Many Requests',
            'timeout'              => 'Connection timeout',
            'dns'                  => 'DNS error',
            'refused'              => 'Connection refused',
            'connect'              => 'Connection failed',
            'reset'             => 'Connection reset',
            'ssl'                  => 'SSL error',
            'network'              => 'Network error',
            'no_response'          => 'No response',
            'invalid_response'     => 'Invalid response',
            'too_many_connections' => 'Too many connections',
            'db_refused'           => 'Connection refused',
            'db_lost'              => 'Lost connection',
            'db_gone'              => 'MySQL server has gone away',
            'db_error'             => 'Database connection error',
            'db_unavailable'       => 'Database unavailable',
            'worker_fatal'         => 'Worker stopped unexpectedly',
            'worker_exception'     => 'Worker error',
            'queue_unavailable'    => 'Queue file unavailable',
            'message_invalid'      => 'Message rejected by Telegram',
            'telegram_auth'        => 'Unauthorized',
        ];
        $reason = (string) ($info['pause_reason'] ?? 'system');
        $detailKey = (string) ($info['pause_detail'] ?? '');
        $detail = $details[$detailKey] ?? (preg_match('/^http_(\d{3})$/', $detailKey, $m) ? 'HTTP ' . $m[1] : '');
        $t = "\n⚠️ دلیل توقف : <b>" . ($titles[$reason] ?? $titles['system']) . "</b>\n";
        if ($detail !== '') {
            $t .= "🔎 جزئیات : <code>" . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . "</code>\n";
        }
        $until = (int) ($info['pause_until'] ?? 0);
        if (!empty($info['manual_resume']) || $until <= 0) {
            $t .= "🖐 ادامه خودکار انجام نمی‌شود؛ برای ادامه روی «▶️ ادامه عملیات» بزنید.\n";
        } else {
            $t .= "⏳ ادامه خودکار پس از حدود <b>" . max(0, $until - time()) . "</b> ثانیه (در اجرای بعدی کرون)\n";
        }
        return $t;
    }
}
