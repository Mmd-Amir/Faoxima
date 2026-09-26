<?php

function rx_config_is_already_installed(string $configDirectory): bool
{
    if (!is_file($configDirectory)) {
        return false;
    }
    $contents = @file_get_contents($configDirectory);
    if ($contents === false || $contents === '') {
        return false;
    }
    if (strpos($contents, '{API_KEY}') !== false || strpos($contents, '{database_name}') !== false) {
        return false;
    }
    if (!preg_match('/\$APIKEY\s*=\s*[\'"]([^\'"]*)[\'"]/', $contents, $matches)) {
        return false;
    }
    return trim($matches[1]) !== '';
}

if (!function_exists('rx_cleanup_installer')) {
    function rx_cleanup_installer(string $installerDir, string $trigger = 'installer'): bool
    {
        $rootDir = dirname($installerDir);
        $logsDir = $rootDir . DIRECTORY_SEPARATOR . 'logs';
        $flagFile = $logsDir . DIRECTORY_SEPARATOR . '.cleanup_failed';

        if ($trigger === 'config_bootstrap' && is_file($flagFile)) {
            $mtime = (int) @filemtime($flagFile);
            if ((time() - $mtime) < 300) {
                return false;
            }
        }

        if (!is_dir($installerDir)) {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            return true;
        }

        $indexFile = $installerDir . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($indexFile)) {
            @chmod($indexFile, 0666);
            @file_put_contents($indexFile, "<?php http_response_code(404); exit;\n");
        }

        @chmod($rootDir, 0777);
        @chmod($installerDir, 0777);

        $deleteRecursive = static function (string $dir) use (&$deleteRecursive): bool {
            if (!is_dir($dir)) {
                return true;
            }
            @chmod($dir, 0777);
            $items = @scandir($dir);
            if ($items === false) {
                return false;
            }

            $success = true;
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                clearstatcache(true, $path);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($path, true);
                }
                @chmod($path, 0777);

                if (is_dir($path) && !is_link($path)) {
                    if (!$deleteRecursive($path)) {
                        $success = false;
                    }
                    if (!@rmdir($path)) {
                        $success = false;
                    }
                } else {
                    if (!@unlink($path)) {
                        @chmod($path, 0666);
                        if (!@unlink($path)) {
                            $success = false;
                        }
                    }
                }
            }
            return $success;
        };

        $deleteRecursive($installerDir);
        clearstatcache(true, $installerDir);

        if (!is_dir($installerDir)) {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            return true;
        }

        if (@rmdir($installerDir)) {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            return true;
        }

        $escapedDir = escapeshellarg($installerDir);
        $shellCmd = strncasecmp(PHP_OS, 'WIN', 3) === 0
            ? "rmdir /s /q {$escapedDir} 2>&1"
            : "chmod -R 777 {$escapedDir} 2>&1; rm -rf {$escapedDir} 2>&1";

        if (function_exists('exec')) {
            @exec($shellCmd);
        } elseif (function_exists('shell_exec')) {
            @shell_exec($shellCmd);
        } elseif (function_exists('system')) {
            ob_start();
            @system($shellCmd);
            ob_end_clean();
        } elseif (function_exists('passthru')) {
            ob_start();
            @passthru($shellCmd);
            ob_end_clean();
        }

        clearstatcache(true, $installerDir);
        if (!is_dir($installerDir)) {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            return true;
        }

        @touch($flagFile);

        $phpBin = PHP_BINARY && is_executable(PHP_BINARY) ? PHP_BINARY : '';
        $isCliBinary = $phpBin !== '' && preg_match('/^php(\d+(\.\d+)*)?(\.exe)?$/i', basename($phpBin));
        if ($trigger === 'installer_shutdown' && $isCliBinary) {
            $cleanCode = 'sleep(1); @rmdir(' . var_export($installerDir, true) . ');';
            $cmdStr = escapeshellarg($phpBin) . ' -r ' . escapeshellarg($cleanCode);
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                if (function_exists('popen') && function_exists('pclose')) {
                    $backgroundProcess = @popen('start /B ' . $cmdStr, 'r');
                    if (is_resource($backgroundProcess)) {
                        @pclose($backgroundProcess);
                    }
                }
            } elseif (function_exists('exec')) {
                @exec($cmdStr . ' > /dev/null 2>&1 &');
            }
        }

        return !is_dir($installerDir);
    }
}

function rx_is_https(): bool
{
    return (
        ($_SERVER['REQUEST_SCHEME'] ?? 'http') === 'https' ||
        ($_SERVER['HTTPS'] ?? 'off') === 'on' ||
        ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    );
}

function rx_get_contents(string $url)
{
    $context = stream_context_create([
        'http' => ['timeout' => 30],
        'https' => ['timeout' => 30],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['ok' => false];
    }
    $decoded = json_decode($response, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return ['ok' => false];
    }
    return $decoded;
}

function rx_telegram_request(string $token, string $method, array $parameters = [], int $timeout = 15): array
{
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $timeout = max(3, min(20, $timeout));

    for ($attempt = 0; $attempt <= 1; $attempt++) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'transport_error' => true, 'description' => 'امکان آغاز ارتباط با تلگرام وجود ندارد.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($parameters),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            $timedOut = $curlErrno === 28;
            return [
                'ok' => false,
                'transport_error' => true,
                'timed_out' => $timedOut,
                'description' => $timedOut
                    ? 'مهلت پاسخ سرور تلگرام به پایان رسید.'
                    : ($curlError !== '' ? $curlError : 'پاسخی از تلگرام دریافت نشد.'),
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'transport_error' => true, 'description' => 'پاسخ تلگرام معتبر نبود. HTTP ' . $status, 'status' => $status];
        }

        $isRateLimited = ((int) ($decoded['error_code'] ?? 0) === 429) || $status === 429;
        $retryAfter = (int) ($decoded['parameters']['retry_after'] ?? 1);
        if (!$isRateLimited || $attempt >= 1 || $retryAfter > 3) {
            return $decoded;
        }

        sleep(max(1, $retryAfter));
    }

    return ['ok' => false, 'description' => 'محدودیت موقت تلگرام برطرف نشد.'];
}

function rx_telegram_error_description(array $response, array $secrets = []): string
{
    $description = trim((string) ($response['description'] ?? ''));
    if ($description === '') {
        $description = 'پاسخ ناموفق بدون توضیح از تلگرام دریافت شد.';
    }
    $errorCode = (int) ($response['error_code'] ?? 0);
    if ($errorCode > 0) {
        $description .= ' (کد ' . $errorCode . ')';
    }
    return mb_substr(rx_safe_error_message($description, $secrets), 0, 300, 'UTF-8');
}

function rx_telegram_webhook_secret(string $rootDirectory, string $botToken): ?string
{
    $authFile = rtrim($rootDirectory, '/\\') . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'WebhookAuth.php';
    if (!class_exists('FaoximaWebhookAuth', false)) {
        if (!is_file($authFile)) {
            return null;
        }
        require_once $authFile;
    }
    if (!class_exists('FaoximaWebhookAuth') || !method_exists('FaoximaWebhookAuth', 'secret')) {
        return null;
    }
    $secret = (string) FaoximaWebhookAuth::secret($botToken);
    if ($secret === '' || strlen($secret) > 256 || !preg_match('/^[A-Za-z0-9_-]+$/', $secret)) {
        return null;
    }
    return $secret;
}

function rx_safe_error_message($message, array $secrets = []): string
{
    $safe = (string) $message;
    foreach ($secrets as $secret) {
        if ((string) $secret !== '') {
            $safe = str_replace((string) $secret, '[redacted]', $safe);
        }
    }
    $safe = preg_replace('#bot\d{6,12}:[A-Za-z0-9_-]{35}#', 'bot[redacted]', $safe);
    return trim((string) $safe);
}

function rx_installer_log(string $rootDirectory, string $stage, $error, array $secrets = []): string
{
    $errorId = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
    $logsDirectory = rtrim($rootDirectory, '/\\') . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsDirectory)) {
        @mkdir($logsDirectory, 0775, true);
    }
    $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;
    $line = sprintf("[%s] installer=%s stage=%s message=%s\n", date('c'), $errorId, $stage, rx_safe_error_message($message, $secrets));
    @file_put_contents($logsDirectory . DIRECTORY_SEPARATOR . 'installer.log', $line, FILE_APPEND | LOCK_EX);
    return $errorId;
}

function rx_installation_lock_path(string $rootDirectory): string
{
    return rtrim($rootDirectory, '/\\') . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . '.installer_running';
}

function rx_acquire_installation_lock(string $rootDirectory): array
{
    $logsDirectory = rtrim($rootDirectory, '/\\') . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsDirectory) && !@mkdir($logsDirectory, 0775, true) && !is_dir($logsDirectory)) {
        return ['acquired' => false, 'busy' => false, 'handle' => null];
    }
    $handle = @fopen(rx_installation_lock_path($rootDirectory), 'c');
    if (!is_resource($handle)) {
        return ['acquired' => false, 'busy' => false, 'handle' => null];
    }
    $wouldBlock = 0;
    if (!@flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
        fclose($handle);
        return ['acquired' => false, 'busy' => (bool) $wouldBlock, 'handle' => null];
    }
    @ftruncate($handle, 0);
    @fwrite($handle, (string) time());
    @fflush($handle);
    return ['acquired' => true, 'busy' => false, 'handle' => $handle];
}

function rx_release_installation_lock($handle): void
{
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function rx_installation_lock_is_busy(string $rootDirectory): bool
{
    $lockPath = rx_installation_lock_path($rootDirectory);
    if (!is_file($lockPath)) {
        return false;
    }
    $handle = @fopen($lockPath, 'r');
    if (!is_resource($handle)) {
        return false;
    }
    $wouldBlock = 0;
    $locked = @flock($handle, LOCK_SH | LOCK_NB, $wouldBlock);
    if ($locked) {
        @flock($handle, LOCK_UN);
    }
    fclose($handle);
    return !$locked && (bool) $wouldBlock;
}

function rx_defer_installer_cleanup(string $rootDirectory): bool
{
    $logsDirectory = rtrim($rootDirectory, '/\\') . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsDirectory) && !@mkdir($logsDirectory, 0775, true) && !is_dir($logsDirectory)) {
        return false;
    }
    return @touch($logsDirectory . DIRECTORY_SEPARATOR . '.cleanup_failed');
}

function rx_cancel_installer_cleanup_deferral(string $rootDirectory): void
{
    $flagPath = rtrim($rootDirectory, '/\\') . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . '.cleanup_failed';
    if (is_file($flagPath)) {
        @unlink($flagPath);
    }
}

function rx_is_valid_telegram_token(string $token): bool
{
    return (bool) preg_match('/^\d{6,12}:[A-Za-z0-9_-]{35}$/', $token);
}

function rx_is_valid_telegram_id(string $id): bool
{
    return (bool) preg_match('/^\d{6,12}$/', $id);
}

function rx_sanitize_input($input, array $options = [])
{
    $defaultOptions = [
        'allow_html' => false,
        'allowed_tags' => '',
        'remove_spaces' => false,
        'max_length' => 0,
        'encoding' => 'UTF-8',
    ];
    $options = array_merge($defaultOptions, $options);
    if (is_array($input)) {
        return array_map(static function ($item) use ($options) {
            return rx_sanitize_input($item, $options);
        }, $input);
    }
    if ($input === null || $input === false) {
        return '';
    }
    $input = trim((string) $input);
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);
    if ($options['max_length'] > 0) {
        $input = mb_substr($input, 0, $options['max_length'], $options['encoding']);
    }
    if (!$options['allow_html']) {
        $input = strip_tags($input);
    } elseif (!empty($options['allowed_tags'])) {
        $input = strip_tags($input, $options['allowed_tags']);
    }
    if ($options['remove_spaces']) {
        $input = preg_replace('/\s+/', ' ', trim($input));
    }
    return $input;
}

function rx_normalize_domain_address(string $url): ?array
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $parsedUrl = parse_url($url);
    if (empty($parsedUrl['host']) || isset($parsedUrl['user']) || isset($parsedUrl['pass']) || isset($parsedUrl['query']) || isset($parsedUrl['fragment'])) {
        return null;
    }
    $path = $parsedUrl['path'] ?? '';
    $pathBaseName = basename($path);
    if (str_contains($pathBaseName, '.') && strcasecmp($pathBaseName, 'index.php') !== 0) {
        return null;
    }
    $path = preg_replace('#/index\.php$#i', '', $path);
    $path = preg_replace('#/installer/?$#', '', $path);
    $path = rtrim($path, '/');
    $path = ltrim($path, '/');
    $address = $parsedUrl['host'];
    if (isset($parsedUrl['port'])) {
        $address .= ':' . (int) $parsedUrl['port'];
    }
    if ($path !== '') {
        $address .= '/' . $path;
    }
    return ['address' => $address];
}

function rx_update_config_values(string $configContents, array $placeholderValues, int &$replacementCount = 0): string
{
    $replacementCount = 0;
    $configData = str_replace(array_keys($placeholderValues), array_values($placeholderValues), $configContents, $placeholderReplacementCount);
    if ($placeholderReplacementCount > 0) {
        $replacementCount += $placeholderReplacementCount;
    }
    $variableMap = [
        'dbname' => $placeholderValues['{database_name}'] ?? '',
        'usernamedb' => $placeholderValues['{username_db}'] ?? '',
        'passworddb' => $placeholderValues['{password_db}'] ?? '',
        'dbhost' => $placeholderValues['{db_host}'] ?? '',
        'APIKEY' => $placeholderValues['{API_KEY}'] ?? '',
        'adminnumber' => $placeholderValues['{admin_number}'] ?? '',
        'domainhosts' => $placeholderValues['{domain_name}'] ?? '',
        'usernamebot' => $placeholderValues['{username_bot}'] ?? '',
    ];
    $updatedConfig = $configData;
    foreach ($variableMap as $variable => $value) {
        $pattern = '/(\$' . preg_quote($variable, '/') . '\s*=\s*)((?:\'(?:\\\\.|[^\'\\\\])*\')|(?:"(?:\\\\.|[^"\\\\])*"))(\s*;)([^\n]*)(\n?)/u';
        $updatedConfig = preg_replace_callback(
            $pattern,
            static function ($matches) use ($value, &$replacementCount) {
                $replacementCount++;
                $quoteChar = $matches[2][0];
                $formattedValue = rx_format_config_value($value, $quoteChar);
                return $matches[1] . $formattedValue . $matches[3] . $matches[4] . $matches[5];
            },
            $updatedConfig,
            1
        );
    }
    return $updatedConfig;
}

function rx_validate_config_source(string $configSource, array $expectedValues): array
{
    try {
        token_get_all($configSource, TOKEN_PARSE);
    } catch (ParseError $e) {
        return ['ok' => false, 'message' => 'ساختار PHP فایل تنظیمات معتبر نیست.'];
    }
    $requiredVariables = ['dbname', 'usernamedb', 'passworddb', 'dbhost', 'APIKEY', 'adminnumber', 'domainhosts', 'usernamebot'];
    foreach ($requiredVariables as $variable) {
        $expected = (string) ($expectedValues[$variable] ?? '');
        $singleQuoted = preg_quote(rx_format_config_value($expected, "'"), '/');
        $doubleQuoted = preg_quote(rx_format_config_value($expected, '"'), '/');
        $pattern = '/\$' . preg_quote($variable, '/') . '\s*=\s*(?:' . $singleQuoted . '|' . $doubleQuoted . ')\s*;/u';
        if (!preg_match($pattern, $configSource)) {
            return ['ok' => false, 'message' => 'اعتبارسنجی مقدار تنظیمات ناموفق بود: ' . $variable];
        }
    }
    return ['ok' => true, 'message' => ''];
}

function rx_write_config_atomically(string $configPath, string $configSource): array
{
    $directory = dirname($configPath);
    $temporaryPath = @tempnam($directory, '.rx-config-');
    if ($temporaryPath === false) {
        return ['ok' => false, 'message' => 'فایل موقت تنظیمات ساخته نشد.'];
    }
    $written = @file_put_contents($temporaryPath, $configSource, LOCK_EX);
    if ($written === false || $written !== strlen($configSource)) {
        @unlink($temporaryPath);
        return ['ok' => false, 'message' => 'نوشتن کامل فایل تنظیمات ممکن نبود.'];
    }
    @chmod($temporaryPath, 0640);
    $previousPath = null;
    if (DIRECTORY_SEPARATOR === '\\' && is_file($configPath)) {
        $previousPath = $configPath . '.previous-' . bin2hex(random_bytes(4));
        if (!@rename($configPath, $previousPath)) {
            @unlink($temporaryPath);
            return ['ok' => false, 'message' => 'آماده‌سازی جایگزینی فایل تنظیمات ممکن نبود.'];
        }
    }
    if (!@rename($temporaryPath, $configPath)) {
        if ($previousPath !== null && is_file($previousPath)) {
            @rename($previousPath, $configPath);
        }
        @unlink($temporaryPath);
        return ['ok' => false, 'message' => 'نهایی‌سازی فایل تنظیمات ممکن نبود.'];
    }
    if ($previousPath !== null && is_file($previousPath)) {
        @unlink($previousPath);
    }
    clearstatcache(true, $configPath);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($configPath, true);
    }
    return ['ok' => true, 'message' => ''];
}

function rx_format_config_value($value, string $quoteChar = "'"): string
{
    if ($value === null) {
        return 'null';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if ($quoteChar !== "'" && $quoteChar !== '"') {
        $quoteChar = "'";
    }
    $stringValue = (string) $value;
    $characters = $quoteChar === '"' ? "\\\"$" : "\\'";
    $escapedValue = addcslashes($stringValue, $characters);
    return $quoteChar . $escapedValue . $quoteChar;
}

function rx_escape_html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}


function rx_database_connect(array $dbInfo): PDO
{
    return new PDO(
        'mysql:host=' . $dbInfo['host'] . ';dbname=' . $dbInfo['name'] . ';charset=utf8mb4',
        $dbInfo['username'],
        $dbInfo['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
}

function rx_database_error_code(Throwable $error): int
{
    if (preg_match('/SQLSTATE\[[^\]]*\]\s*\[(\d+)\]/', $error->getMessage(), $matches)) {
        return (int) $matches[1];
    }
    if ($error instanceof PDOException && is_array($error->errorInfo) && isset($error->errorInfo[1])) {
        return (int) $error->errorInfo[1];
    }
    return is_numeric($error->getCode()) ? (int) $error->getCode() : 0;
}

function rx_database_error_message(Throwable $error): string
{
    $code = rx_database_error_code($error);
    switch ($code) {
        case 1045:
            $message = 'نام کاربری یا رمز عبور دیتابیس اشتباه است. در cPanel نام کاربری کامل را همراه با پیشوند حساب (مانند cpuser_dbuser) وارد کنید.';
            break;
        case 1044:
            $message = 'کاربر دیتابیس به این دیتابیس دسترسی ندارد. نام کامل دیتابیس (همراه با پیشوند حساب) را بررسی کنید و در بخش MySQL Databases هاست، کاربر را با ALL PRIVILEGES به دیتابیس اختصاص دهید.';
            break;
        case 1049:
            $message = 'دیتابیسی با این نام یافت نشد. دیتابیس را ابتدا در cPanel بسازید و نام کامل آن را همراه با پیشوند حساب وارد کنید.';
            break;
        case 1040:
        case 1203:
            $message = 'تعداد اتصال‌های همزمان دیتابیس به حداکثر رسیده است. چند لحظه بعد دوباره تلاش کنید.';
            break;
        case 1142:
        case 1227:
        case 1370:
            $message = 'کاربر دیتابیس سطح دسترسی کافی ندارد. در cPanel برای این کاربر ALL PRIVILEGES را فعال کنید.';
            break;
        case 2002:
        case 2003:
        case 2005:
        case 2006:
        case 2013:
            $message = 'اتصال به میزبان دیتابیس برقرار نشد یا مهلت آن به پایان رسید. در هاست cPanel مقدار میزبان معمولاً localhost است.';
            break;
        default:
            $message = 'اتصال به دیتابیس برقرار نشد. اطلاعات ورود و سطح دسترسی کاربر دیتابیس را بررسی کنید.';
    }
    return $code > 0 ? $message . ' (کد خطا: ' . $code . ')' : $message;
}

function rx_table_migrations_verify_ready(array $dbInfo, ?array &$diagnostics = null): bool
{
    $diagnostics = ['missing_tables' => [], 'missing_columns' => [], 'charset_columns' => [], 'database_error' => ''];
    $requiredTables = [
        'user', 'setting', 'channels', 'marzban_panel', 'product', 'invoice',
        'Payment_report', 'textbot', 'shopSetting', 'support_message', 'crypto_wallets',
        'processed_updates', 'cron_runtime_state',
    ];
    $requiredColumns = [
        ['user', 'nav_state'],
        ['user', 'card_verify_bypass'],
        ['setting', 'redis_enabled'],
        ['setting', 'banner_start_status'],
        ['setting', 'auto_remove_reply_keyboard'],
        ['invoice', 'invalidated_at'],
        ['Payment_report', 'atlaspay_order_id'],
        ['marzban_panel', 'xui_api_mode'],
        ['marzban_panel', 'ip_limit_guard'],
        ['product', 'ip_limit'],
        ['support_message', 'seen_by_admin'],
        ['crypto_wallets', 'verification_mode'],
    ];
    try {
        $pdo = rx_database_connect($dbInfo);
        $requiredTablesLower = array_map('strtolower', $requiredTables);
        $tablePlaceholders = implode(',', array_fill(0, count($requiredTables), '?'));
        $tableQuery = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND LOWER(TABLE_NAME) IN ({$tablePlaceholders})");
        $tableQuery->execute($requiredTablesLower);
        $foundTables = array_map('strtolower', array_map('strval', $tableQuery->fetchAll(PDO::FETCH_COLUMN)));
        $diagnostics['missing_tables'] = array_values(array_filter($requiredTables, static function ($tableName) use ($foundTables) {
            return !in_array(strtolower($tableName), $foundTables, true);
        }));

        $columnConditions = [];
        $columnParameters = [];
        foreach ($requiredColumns as $column) {
            $columnConditions[] = '(LOWER(TABLE_NAME) = ? AND LOWER(COLUMN_NAME) = ?)';
            $columnParameters[] = strtolower($column[0]);
            $columnParameters[] = strtolower($column[1]);
        }
        $columnQuery = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND (' . implode(' OR ', $columnConditions) . ')');
        $columnQuery->execute($columnParameters);
        $foundColumns = [];
        foreach ($columnQuery->fetchAll() as $row) {
            $foundColumns[] = strtolower((string) $row['TABLE_NAME'] . '.' . (string) $row['COLUMN_NAME']);
        }
        $expectedColumns = array_map(static function ($column) {
            return $column[0] . '.' . $column[1];
        }, $requiredColumns);
        $diagnostics['missing_columns'] = array_values(array_filter($expectedColumns, static function ($columnName) use ($foundColumns) {
            return !in_array(strtolower($columnName), $foundColumns, true);
        }));

        $charsetQuery = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND LOWER(TABLE_NAME) IN ({$tablePlaceholders}) AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME <> 'utf8mb4'");
        $charsetQuery->execute($requiredTablesLower);
        foreach ($charsetQuery->fetchAll() as $row) {
            $diagnostics['charset_columns'][] = (string) $row['TABLE_NAME'] . '.' . (string) $row['COLUMN_NAME'] . ':' . (string) $row['CHARACTER_SET_NAME'];
        }
    } catch (Throwable $error) {
        $diagnostics['database_error'] = $error->getMessage();
        return false;
    }
    return empty($diagnostics['missing_tables']) && empty($diagnostics['missing_columns']) && empty($diagnostics['charset_columns']);
}

function rx_execute_table_file(string $installerTableRunFile): array
{
    global $dbname, $usernamedb, $passworddb, $dbhost, $redis_host, $redis_port, $redis_password, $redis_database;
    global $connect, $pdo, $dsn, $options, $APIKEY, $adminnumber, $domainhosts, $usernamebot;
    global $telegramCurlTimeout, $telegramStrictIpValidation;

    $installerTableRunDirectory = getcwd();
    $installerTableRunReporting = error_reporting();
    $installerTableRunErrorHandler = set_error_handler(static function (): bool {
        return false;
    });
    restore_error_handler();
    $installerTableRunExceptionHandler = set_exception_handler(null);
    set_exception_handler($installerTableRunExceptionHandler);
    $installerTableRunBufferLevel = ob_get_level();
    $installerTableRunResult = ['ok' => true, 'message' => ''];
    ob_start();
    try {
        include $installerTableRunFile;
    } catch (Throwable $installerTableRunError) {
        $installerTableRunResult = ['ok' => false, 'message' => $installerTableRunError->getMessage()];
    } finally {
        while (ob_get_level() > $installerTableRunBufferLevel) {
            ob_end_clean();
        }
        set_error_handler($installerTableRunErrorHandler);
        set_exception_handler($installerTableRunExceptionHandler);
        error_reporting($installerTableRunReporting);
        if ($installerTableRunDirectory !== false) {
            @chdir($installerTableRunDirectory);
        }
    }
    return $installerTableRunResult;
}

function rx_run_table_migrations(string $rootDirectory, array $dbInfo): array
{
    $tableFile = rtrim($rootDirectory, '/\\') . DIRECTORY_SEPARATOR . 'table.php';
    if (!is_file($tableFile)) {
        return ['ok' => false, 'message' => 'فایل table.php یافت نشد.'];
    }
    $executionError = '';
    $diagnostics = [];
    for ($pass = 1; $pass <= 2; $pass++) {
        $execution = rx_execute_table_file($tableFile);
        if (!$execution['ok']) {
            $executionError = (string) $execution['message'];
        }
        if (rx_table_migrations_verify_ready($dbInfo, $diagnostics)) {
            return ['ok' => true, 'message' => ''];
        }
    }
    $details = [];
    if ($executionError !== '') {
        $details[] = 'خطای table.php: ' . $executionError;
    }
    $labels = [
        'missing_tables' => 'جداول ایجادنشده',
        'missing_columns' => 'ستون‌های ایجادنشده',
        'charset_columns' => 'ستون‌های غیر utf8mb4',
    ];
    foreach ($labels as $key => $label) {
        if (!empty($diagnostics[$key])) {
            $details[] = $label . ': ' . implode(', ', array_slice($diagnostics[$key], 0, 8));
        }
    }
    if (!empty($diagnostics['database_error'])) {
        $details[] = 'خطای دیتابیس: ' . $diagnostics['database_error'];
    }
    $message = 'ساختار دیتابیس پس از اجرای table.php کامل نشد. سطح دسترسی کاربر دیتابیس (ALL PRIVILEGES) را بررسی کنید.';
    if (!empty($details)) {
        $message .= ' ' . implode(' | ', $details);
    }
    return ['ok' => false, 'message' => $message];
}

function rx_ensure_admin_record(array $dbInfo, string $adminNumber): array
{
    try {
        $pdo = rx_database_connect($dbInfo);
        $tableExists = (bool) $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin'")->fetchColumn();
        if (!$tableExists) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `admin` (
              `id_admin` varchar(500) NOT NULL,
              `username` varchar(1000) NOT NULL,
              `password` varchar(1000) NOT NULL,
              `password_hash` varchar(255) NULL,
              `iplogin` varchar(1000) NULL,
              `rule` varchar(500) NOT NULL,
              `last_ticket_seen` INT(11) NULL DEFAULT 0,
              PRIMARY KEY (`id_admin`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        $select = $pdo->prepare('SELECT `id_admin`, `rule` FROM `admin` WHERE `id_admin` = ? LIMIT 1');
        $select->execute([$adminNumber]);
        $existing = $select->fetch();
        $created = false;

        if (is_array($existing)) {
            if ((string) ($existing['rule'] ?? '') !== 'administrator') {
                $promote = $pdo->prepare("UPDATE `admin` SET `rule` = 'administrator' WHERE `id_admin` = ?");
                $promote->execute([$adminNumber]);
            }
        } else {
            $usernameCheck = $pdo->prepare('SELECT COUNT(*) FROM `admin` WHERE `username` = ?');
            $usernameCheck->execute(['admin']);
            $username = (int) $usernameCheck->fetchColumn() > 0 ? $adminNumber : 'admin';
            $insert = $pdo->prepare("INSERT INTO `admin` (`id_admin`, `username`, `password`, `password_hash`, `rule`) VALUES (?, ?, '', ?, 'administrator')");
            $insert->execute([$adminNumber, $username, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
            $created = true;
        }

        $verify = $pdo->prepare("SELECT COUNT(*) FROM `admin` WHERE `id_admin` = ? AND `rule` = 'administrator'");
        $verify->execute([$adminNumber]);
        if ((int) $verify->fetchColumn() < 1) {
            return ['ok' => false, 'created' => $created, 'message' => 'Admin record was not found after write.'];
        }
        return ['ok' => true, 'created' => $created, 'message' => ''];
    } catch (Throwable $error) {
        return ['ok' => false, 'created' => false, 'message' => $error->getMessage()];
    }
}

function rx_render_failure_page(string $stage, string $errorId, string $message): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html dir="rtl" lang="fa"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>خطای نصب</title><body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#07060b;color:#f5f3f8;font-family:Tahoma,sans-serif"><main style="width:min(520px,calc(100% - 32px));padding:32px;border:1px solid rgba(167,112,255,.2);border-radius:20px;background:#120e1b;text-align:center"><h1 style="font-size:22px">نصب تکمیل نشد</h1><p style="color:#aaa4b5;line-height:1.9">' . rx_escape_html($message) . '</p><small style="color:#b77aff">مرحله: ' . rx_escape_html($stage) . ' · شناسه خطا: ' . rx_escape_html($errorId) . '</small><p style="margin:22px 0 0"><a href="./" style="display:inline-block;padding:10px 18px;border-radius:11px;background:#7c3aed;color:#fff;text-decoration:none;font-weight:700">بازگشت به اینستالر و تلاش مجدد</a></p></main></body></html>';
}
