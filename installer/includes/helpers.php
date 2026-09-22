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

        if ($trigger === 'installer_shutdown') {
            $phpBin = PHP_BINARY && is_executable(PHP_BINARY) ? PHP_BINARY : 'php';
            $cleanCode = 'sleep(1); @rmdir(' . var_export($installerDir, true) . ');';
            $cmdStr = escapeshellarg($phpBin) . ' -r ' . escapeshellarg($cleanCode);
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                @pclose(@popen('start /B ' . $cmdStr, 'r'));
            } else {
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

function rx_telegram_request(string $token, string $method, array $parameters = []): array
{
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'description' => 'امکان آغاز ارتباط با تلگرام وجود ندارد.'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($parameters),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false) {
        return ['ok' => false, 'description' => $curlError !== '' ? $curlError : 'پاسخی از تلگرام دریافت نشد.'];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'description' => 'پاسخ تلگرام معتبر نبود.', 'status' => $status];
    }
    return $decoded;
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

function rx_acquire_installation_lock(string $rootDirectory, string $owner, int $staleAfter = 1800): bool
{
    $logsDirectory = rtrim($rootDirectory, '/\\') . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsDirectory) && !@mkdir($logsDirectory, 0775, true) && !is_dir($logsDirectory)) {
        return false;
    }
    $lockPath = rx_installation_lock_path($rootDirectory);
    clearstatcache(true, $lockPath);
    if (is_file($lockPath)) {
        $current = json_decode((string) @file_get_contents($lockPath), true);
        $currentOwner = is_array($current) ? (string) ($current['owner'] ?? '') : '';
        $modifiedAt = (int) @filemtime($lockPath);
        if ($currentOwner !== '' && hash_equals($currentOwner, $owner)) {
            return @file_put_contents($lockPath, json_encode(['owner' => $owner, 'started_at' => time()]), LOCK_EX) !== false;
        }
        if ($modifiedAt > 0 && (time() - $modifiedAt) < $staleAfter) {
            return false;
        }
        @unlink($lockPath);
    }
    $handle = @fopen($lockPath, 'x');
    if (!is_resource($handle)) {
        return false;
    }
    $written = fwrite($handle, json_encode(['owner' => $owner, 'started_at' => time()]));
    fclose($handle);
    @chmod($lockPath, 0600);
    return $written !== false;
}

function rx_release_installation_lock(string $rootDirectory, string $owner): void
{
    $lockPath = rx_installation_lock_path($rootDirectory);
    if (!is_file($lockPath)) {
        return;
    }
    $current = json_decode((string) @file_get_contents($lockPath), true);
    $currentOwner = is_array($current) ? (string) ($current['owner'] ?? '') : '';
    if ($currentOwner !== '' && hash_equals($currentOwner, $owner)) {
        @unlink($lockPath);
    }
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

function rx_find_php_binary(): ?string
{
    $suffix = DIRECTORY_SEPARATOR === '\\' ? '.exe' : '';
    $candidates = [
        PHP_BINARY,
        rtrim(PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . 'php' . $suffix,
        '/usr/local/bin/php',
        '/usr/bin/php',
    ];
    foreach (array_unique($candidates) as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    return null;
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

function rx_table_migrations_verify_ready(array $dbInfo, int $retries = 5, int $delaySeconds = 2, ?array &$diagnostics = null): bool
{
    $diagnostics = ['missing_tables' => [], 'missing_columns' => [], 'charset_columns' => [], 'database_error' => ''];
    if (!class_exists('mysqli')) {
        $diagnostics['database_error'] = 'mysqli extension is unavailable';
        return false;
    }
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
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        try {
            $connect = @new mysqli($dbInfo['host'], $dbInfo['username'], $dbInfo['password'], $dbInfo['name']);
        } catch (Throwable $error) {
            $diagnostics['database_error'] = $error->getMessage();
            $connect = null;
        }
        if ($connect instanceof mysqli && !$connect->connect_error) {
            $connect->set_charset('utf8mb4');
            $tableNames = implode(',', array_map(static function ($tableName) use ($connect) {
                return "'" . $connect->real_escape_string($tableName) . "'";
            }, $requiredTables));
            $tableResult = $connect->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME IN ({$tableNames})");
            $foundTables = [];
            if ($tableResult) {
                while ($row = $tableResult->fetch_assoc()) {
                    $foundTables[] = (string) $row['TABLE_NAME'];
                }
            }
            $diagnostics['missing_tables'] = array_values(array_diff($requiredTables, $foundTables));
            $columnConditions = array_map(static function ($column) use ($connect) {
                $tableName = $connect->real_escape_string($column[0]);
                $columnName = $connect->real_escape_string($column[1]);
                return "(TABLE_NAME = '{$tableName}' AND COLUMN_NAME = '{$columnName}')";
            }, $requiredColumns);
            $columnResult = $connect->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND (" . implode(' OR ', $columnConditions) . ')');
            $foundColumns = [];
            if ($columnResult) {
                while ($row = $columnResult->fetch_assoc()) {
                    $foundColumns[] = (string) $row['TABLE_NAME'] . '.' . (string) $row['COLUMN_NAME'];
                }
            }
            $expectedColumns = array_map(static function ($column) {
                return $column[0] . '.' . $column[1];
            }, $requiredColumns);
            $diagnostics['missing_columns'] = array_values(array_diff($expectedColumns, $foundColumns));
            $charsetResult = $connect->query("SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$tableNames}) AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME <> 'utf8mb4'");
            $diagnostics['charset_columns'] = [];
            if ($charsetResult) {
                while ($row = $charsetResult->fetch_assoc()) {
                    $diagnostics['charset_columns'][] = (string) $row['TABLE_NAME'] . '.' . (string) $row['COLUMN_NAME'] . ':' . (string) $row['CHARACTER_SET_NAME'];
                }
            }
            if (!$tableResult || !$columnResult || !$charsetResult) {
                $diagnostics['database_error'] = $connect->error;
            } else {
                $diagnostics['database_error'] = '';
            }
            $ready = empty($diagnostics['missing_tables']) && empty($diagnostics['missing_columns']) && empty($diagnostics['charset_columns']) && $diagnostics['database_error'] === '';
            $connect->close();
            if ($ready) {
                return true;
            }
        } elseif ($connect instanceof mysqli) {
            $diagnostics['database_error'] = $connect->connect_error;
        }
        if ($attempt < $retries) {
            sleep($delaySeconds);
        }
    }
    return false;
}

function rx_run_table_migrations(string $rootDirectory, array $dbInfo): array
{
    $tableFile = $rootDirectory . 'table.php';
    if (!file_exists($tableFile)) {
        return ['ok' => false, 'message' => 'فایل table.php یافت نشد.'];
    }
    if (!function_exists('proc_open')) {
        return ['ok' => false, 'message' => 'تابع proc_open برای اجرای ایزوله migration در دسترس نیست.'];
    }
    $runner = __DIR__ . DIRECTORY_SEPARATOR . 'migration_runner.php';
    if (!is_file($runner)) {
        return ['ok' => false, 'message' => 'اجراکننده ایزوله migration یافت نشد.'];
    }
    $phpBinary = rx_find_php_binary();
    if ($phpBinary === null) {
        return ['ok' => false, 'message' => 'فایل اجرایی PHP برای اجرای migration یافت نشد.'];
    }
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $runnerKey = bin2hex(random_bytes(24));
    $environment = array_merge(is_array(getenv()) ? getenv() : [], [
        'RX_INSTALL_ROOT' => rtrim($rootDirectory, '/\\'),
        'RX_INSTALL_RUNNER_KEY' => $runnerKey,
    ]);
    $process = @proc_open([$phpBinary, $runner], $descriptors, $pipes, rtrim($rootDirectory, '/\\'), $environment);
    if (!is_resource($process)) {
        return ['ok' => false, 'message' => 'فرآیند ایزوله migration آغاز نشد.'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $combined = (string) $stdout . "\n" . (string) $stderr;
    $result = null;
    if (preg_match_all('/RX_INSTALL_RESULT:([^\r\n]+)/', $combined, $matches) && !empty($matches[1])) {
        $encoded = end($matches[1]);
        $decoded = json_decode(base64_decode($encoded, true) ?: '', true);
        if (is_array($decoded)) {
            $result = $decoded;
        }
    }
    if ($exitCode !== 0 || !is_array($result) || empty($result['ok'])) {
        $message = is_array($result) ? (string) ($result['message'] ?? '') : '';
        if ($message === '') {
            $diagnosticOutput = trim(preg_replace('/\s+/', ' ', rx_safe_error_message($combined, [$runnerKey])));
            $diagnosticOutput = mb_substr($diagnosticOutput, -1200, null, 'UTF-8');
            $binaryName = basename($phpBinary);
            $message = 'اجرای ایزوله table.php ناموفق بود. exit=' . $exitCode . ' binary=' . $binaryName;
            if ($diagnosticOutput !== '') {
                $message .= ' output=' . $diagnosticOutput;
            }
        }
        return ['ok' => false, 'message' => $message];
    }
    $verification = [];
    if (!rx_table_migrations_verify_ready($dbInfo, 3, 1, $verification)) {
        $details = [];
        foreach (['missing_tables', 'missing_columns', 'charset_columns'] as $key) {
            if (!empty($verification[$key])) {
                $details[] = $key . '=' . implode(',', $verification[$key]);
            }
        }
        if (!empty($verification['database_error'])) {
            $details[] = 'database_error=' . $verification['database_error'];
        }
        $suffix = empty($details) ? '' : ' ' . implode(' ', $details);
        return ['ok' => false, 'message' => 'migration اجرا شد اما جداول، ستون‌ها یا charset مورد انتظار تأیید نشد.' . $suffix];
    }
    return ['ok' => true, 'message' => ''];
}

function rx_ensure_admin_record(array $dbInfo, string $adminNumber): bool
{
    if (!class_exists('mysqli')) {
        return false;
    }
    try {
        $connect = @new mysqli($dbInfo['host'], $dbInfo['username'], $dbInfo['password'], $dbInfo['name']);
        if ($connect->connect_error) {
            return false;
        }
        $connect->set_charset('utf8mb4');
        $defaultPasswordHash = password_hash('14e9eab674', PASSWORD_DEFAULT);
        $tableCheck = $connect->query("SHOW TABLES LIKE 'admin'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $connect->query('SELECT COUNT(*) as cnt FROM admin');
            $countRow = $result ? $result->fetch_assoc() : ['cnt' => 0];
            $count = (int) ($countRow['cnt'] ?? 0);
            if ($count === 0) {
                $stmt = $connect->prepare("INSERT INTO `admin` (`id_admin`, `username`, `password`, `password_hash`, `rule`) VALUES (?, 'admin', '14e9eab674', ?, 'administrator')");
                if ($stmt) {
                    $stmt->bind_param('ss', $adminNumber, $defaultPasswordHash);
                    if (!$stmt->execute()) {
                        $stmt->close();
                        $connect->close();
                        return false;
                    }
                    $stmt->close();
                } else {
                    $connect->close();
                    return false;
                }
            } else {
                $adminNumberEscaped = $connect->real_escape_string($adminNumber);
                $defaultPasswordHashEscaped = $connect->real_escape_string($defaultPasswordHash);
                if (!$connect->query("UPDATE `admin` SET `id_admin` = '{$adminNumberEscaped}', `username` = 'admin', `password` = '14e9eab674', `password_hash` = '{$defaultPasswordHashEscaped}', `rule` = 'administrator' LIMIT 1")) {
                    $connect->close();
                    return false;
                }
            }
        } else {
            if (!$connect->query("CREATE TABLE `admin` (
              `id_admin` varchar(500) NOT NULL,
              `username` varchar(1000) NOT NULL,
              `password` varchar(1000) NOT NULL,
              `password_hash` varchar(255) NULL,
              `rule` varchar(500) NOT NULL,
              PRIMARY KEY (`id_admin`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")) {
                $connect->close();
                return false;
            }
            $stmt = $connect->prepare("INSERT INTO `admin` (`id_admin`, `username`, `password`, `password_hash`, `rule`) VALUES (?, 'admin', '14e9eab674', ?, 'administrator')");
            if ($stmt) {
                $stmt->bind_param('ss', $adminNumber, $defaultPasswordHash);
                if (!$stmt->execute()) {
                    $stmt->close();
                    $connect->close();
                    return false;
                }
                $stmt->close();
            } else {
                $connect->close();
                return false;
            }
        }
        $verify = $connect->prepare('SELECT COUNT(*) FROM `admin` WHERE `id_admin` = ?');
        if (!$verify) {
            $connect->close();
            return false;
        }
        $verify->bind_param('s', $adminNumber);
        if (!$verify->execute()) {
            $verify->close();
            $connect->close();
            return false;
        }
        $verify->bind_result($verifiedCount);
        $verify->fetch();
        $verify->close();
        $connect->close();
        return (int) $verifiedCount > 0;
    } catch (\Throwable $e) {
        return false;
    }
}
