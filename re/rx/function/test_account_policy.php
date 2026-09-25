<?php

if (!defined('RX_TEST_PRODUCT_NAME')) {
    define('RX_TEST_PRODUCT_NAME', 'سرویس تست');
}
if (!defined('RX_TEST_DEFAULT_HOURS')) {
    define('RX_TEST_DEFAULT_HOURS', 1);
}
if (!defined('RX_TEST_DEFAULT_MB')) {
    define('RX_TEST_DEFAULT_MB', 100);
}
if (!defined('RX_TEST_MAX_LIMIT')) {
    define('RX_TEST_MAX_LIMIT', 1000000);
}
if (!defined('RX_TEST_MAX_HOURS')) {
    define('RX_TEST_MAX_HOURS', 87600);
}
if (!defined('RX_TEST_MAX_MB')) {
    define('RX_TEST_MAX_MB', 10485760);
}
if (!defined('RX_TEST_AUDIENCE_ALL')) {
    define('RX_TEST_AUDIENCE_ALL', 'all_users');
}
if (!defined('RX_TEST_AUDIENCE_NEW')) {
    define('RX_TEST_AUDIENCE_NEW', 'new_users_24h');
}

if (!function_exists('rx_test_pdo')) {
    function rx_test_pdo()
    {
        global $pdo;
        return ($pdo instanceof PDO) ? $pdo : null;
    }
}

if (!function_exists('rx_test_setting')) {
    function rx_test_setting()
    {
        global $setting;
        if (is_array($setting) && !empty($setting)) {
            return $setting;
        }
        $row = select('setting', '*');
        return is_array($row) ? $row : [];
    }
}

if (!function_exists('rx_test_ensure_schema')) {
    function rx_test_ensure_schema()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $pdo = rx_test_pdo();
        if ($pdo === null) {
            return $ready = false;
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS test_account_usage (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                code_panel VARCHAR(100) NOT NULL,
                used_count INT UNSIGNED NOT NULL DEFAULT 0,
                quota_generation INT UNSIGNED NOT NULL DEFAULT 1,
                created_at INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at INT UNSIGNED NOT NULL DEFAULT 0,
                UNIQUE KEY uq_tau_user_panel (user_id, code_panel)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS test_account_reservation (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                code_panel VARCHAR(100) NOT NULL,
                quota_mode VARCHAR(16) NOT NULL,
                quota_generation INT UNSIGNED NOT NULL DEFAULT 1,
                usage_counted TINYINT(1) NOT NULL DEFAULT 0,
                state VARCHAR(16) NOT NULL,
                id_invoice VARCHAR(64) NULL,
                username VARCHAR(200) NULL,
                source VARCHAR(16) NOT NULL DEFAULT 'bot',
                created_at INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at INT UNSIGNED NOT NULL DEFAULT 0,
                INDEX idx_tar_user_panel (user_id, code_panel),
                INDEX idx_tar_state (state),
                INDEX idx_tar_panel_gen_state (code_panel, quota_generation, state)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS test_account_user_override (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                remaining_limit INT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 0,
                revision INT UNSIGNED NOT NULL DEFAULT 1,
                updated_at INT UNSIGNED NOT NULL DEFAULT 0,
                updated_by VARCHAR(64) NULL,
                UNIQUE KEY uq_tauo_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            rx_test_add_column_if_missing('test_account_usage', 'quota_generation', 'INT UNSIGNED NOT NULL DEFAULT 1');
            rx_test_add_column_if_missing('test_account_reservation', 'quota_generation', 'INT UNSIGNED NOT NULL DEFAULT 1');
            rx_test_add_index_if_missing('test_account_reservation', 'idx_tar_panel_gen_state', '(code_panel, quota_generation, state)');
            $ready = true;
        } catch (Throwable $e) {
            error_log('[test_account_policy] schema: ' . $e->getMessage());
            $ready = false;
        }
        return $ready;
    }
}

if (!function_exists('rx_test_add_column_if_missing')) {
    function rx_test_add_column_if_missing($table, $column, $definition)
    {
        $pdo = rx_test_pdo();
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        $column = preg_replace('/[^A-Za-z0-9_]/', '', (string) $column);
        if ($pdo === null || $table === '' || $column === '') {
            return false;
        }
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
        }
        return true;
    }
}

if (!function_exists('rx_test_add_index_if_missing')) {
    function rx_test_add_index_if_missing($table, $index, $columns)
    {
        $pdo = rx_test_pdo();
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        $index = preg_replace('/[^A-Za-z0-9_]/', '', (string) $index);
        if ($pdo === null || $table === '' || $index === '') {
            return false;
        }
        $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($index));
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` {$columns}");
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate key name') === false) {
                throw $e;
            }
        }
        return true;
    }
}

if (!function_exists('rx_test_normalize_digits')) {
    function rx_test_normalize_digits($value)
    {
        return strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}

if (!function_exists('rx_test_ensure_settings_column')) {
    function rx_test_ensure_settings_column()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $pdo = rx_test_pdo();
        if ($pdo === null) {
            return $ready = false;
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM marzban_panel LIKE 'test_settings'");
            if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
                return $ready = true;
            }
            $pdo->exec("ALTER TABLE marzban_panel ADD COLUMN test_settings TEXT NULL");
            $ready = true;
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') !== false) {
                return $ready = true;
            }
            error_log('[test_account_policy] settings column: ' . $e->getMessage());
            $ready = false;
        }
        return $ready;
    }
}

if (!function_exists('rx_test_valid_code')) {
    function rx_test_valid_code($code)
    {
        return is_string($code) && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $code) === 1;
    }
}

if (!function_exists('rx_test_int_in_range')) {
    function rx_test_int_in_range($value, $min, $max)
    {
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && $value !== '' && ctype_digit($value) && strlen($value) <= 9) {
            $int = (int) $value;
        } else {
            return null;
        }
        return ($int >= $min && $int <= $max) ? $int : null;
    }
}

if (!function_exists('rx_test_legacy_number')) {
    function rx_test_legacy_number($raw, $default, $max)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || !ctype_digit($raw) || strlen($raw) > 9) {
            return ['value' => $default, 'unlimited' => false];
        }
        $int = (int) $raw;
        if ($int === 0) {
            return ['value' => 0, 'unlimited' => true];
        }
        return ['value' => min($int, $max), 'unlimited' => false];
    }
}

if (!function_exists('rx_test_parse_settings_json')) {
    function rx_test_parse_settings_json($raw)
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return false;
        }
        foreach (['limit_unlimited', 'time_unlimited', 'volume_unlimited'] as $flag) {
            if (!array_key_exists($flag, $data) || !is_bool($data[$flag])) {
                return false;
            }
        }
        $limit = $data['limit_unlimited'] ? 0 : rx_test_int_in_range($data['limit'] ?? null, 1, RX_TEST_MAX_LIMIT);
        $time = $data['time_unlimited'] ? 0 : rx_test_int_in_range($data['time_hours'] ?? null, 1, RX_TEST_MAX_HOURS);
        $volume = $data['volume_unlimited'] ? 0 : rx_test_int_in_range($data['volume_mb'] ?? null, 1, RX_TEST_MAX_MB);
        if ($limit === null || $time === null || $volume === null) {
            return false;
        }
        $audience = array_key_exists('audience_mode', $data) ? $data['audience_mode'] : RX_TEST_AUDIENCE_ALL;
        if (!in_array($audience, [RX_TEST_AUDIENCE_ALL, RX_TEST_AUDIENCE_NEW], true)) {
            return false;
        }
        $generation = array_key_exists('quota_generation', $data) ? rx_test_int_in_range($data['quota_generation'], 1, 2000000000) : 1;
        if ($generation === null) {
            return false;
        }
        $changedAt = array_key_exists('quota_changed_at', $data) ? rx_test_int_in_range($data['quota_changed_at'], 0, 4000000000) : 0;
        if ($changedAt === null) {
            return false;
        }
        return [
            'mode'             => 'panel',
            'limit'            => $limit,
            'limit_unlimited'  => $data['limit_unlimited'],
            'time_hours'       => $time,
            'time_unlimited'   => $data['time_unlimited'],
            'volume_mb'        => $volume,
            'volume_unlimited' => $data['volume_unlimited'],
            'audience_mode'    => $audience,
            'quota_generation' => $generation,
            'quota_changed_at' => $changedAt,
        ];
    }
}

if (!function_exists('rx_test_panel_settings')) {
    function rx_test_panel_settings(array $panel)
    {
        $parsed = rx_test_parse_settings_json($panel['test_settings'] ?? null);
        if (is_array($parsed)) {
            return $parsed;
        }
        if ($parsed === false) {
            return ['mode' => 'invalid'];
        }
        $time = rx_test_legacy_number($panel['time_usertest'] ?? '', RX_TEST_DEFAULT_HOURS, RX_TEST_MAX_HOURS);
        $volume = rx_test_legacy_number($panel['val_usertest'] ?? '', RX_TEST_DEFAULT_MB, RX_TEST_MAX_MB);
        return [
            'mode'             => 'legacy',
            'limit'            => 0,
            'limit_unlimited'  => false,
            'time_hours'       => $time['value'],
            'time_unlimited'   => $time['unlimited'],
            'volume_mb'        => $volume['value'],
            'volume_unlimited' => $volume['unlimited'],
            'audience_mode'    => RX_TEST_AUDIENCE_ALL,
            'quota_generation' => 1,
            'quota_changed_at' => 0,
        ];
    }
}

if (!function_exists('rx_test_generation')) {
    function rx_test_generation(array $settings)
    {
        $generation = (int) ($settings['quota_generation'] ?? 1);
        return $generation >= 1 ? $generation : 1;
    }
}

if (!function_exists('rx_test_user_is_new')) {
    function rx_test_user_is_new(array $user, $now = null)
    {
        $raw = $user['register'] ?? null;
        if (is_int($raw)) {
            $registered = $raw;
        } else {
            $raw = trim((string) $raw);
            if ($raw === '' || !ctype_digit($raw) || strlen($raw) > 12) {
                return false;
            }
            $registered = (int) $raw;
        }
        if ($registered <= 0) {
            return false;
        }
        $now = $now === null ? time() : (int) $now;
        return $registered >= $now - 86400;
    }
}

if (!function_exists('rx_test_override_get')) {
    function rx_test_override_get($userId)
    {
        $pdo = rx_test_pdo();
        if ($pdo === null || !rx_test_ensure_schema()) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT remaining_limit, active, revision FROM test_account_user_override WHERE user_id = ? LIMIT 1");
            $stmt->execute([(string) $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[test_account_policy] override get: ' . $e->getMessage());
            return null;
        }
        if (!is_array($row)) {
            return null;
        }
        return [
            'remaining' => max(0, (int) $row['remaining_limit']),
            'active'    => (int) $row['active'] === 1,
            'revision'  => (int) $row['revision'],
        ];
    }
}

if (!function_exists('rx_test_override_set')) {
    function rx_test_override_set($userId, $limit, $updatedBy = null)
    {
        $pdo = rx_test_pdo();
        $userId = trim((string) $userId);
        $limit = rx_test_int_in_range(rx_test_normalize_digits($limit), 0, RX_TEST_MAX_LIMIT);
        if ($pdo === null || $userId === '' || strlen($userId) > 64 || $limit === null || !rx_test_ensure_schema()) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO test_account_user_override (user_id, remaining_limit, active, revision, updated_at, updated_by) VALUES (?, ?, ?, 1, ?, ?) ON DUPLICATE KEY UPDATE remaining_limit = VALUES(remaining_limit), active = VALUES(active), revision = revision + 1, updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)");
            $stmt->execute([$userId, $limit, $limit > 0 ? 1 : 0, time(), $updatedBy === null ? null : substr((string) $updatedBy, 0, 64)]);
        } catch (Throwable $e) {
            error_log('[test_account_policy] override set: ' . $e->getMessage());
            return false;
        }
        return true;
    }
}

if (!function_exists('rx_test_issued_count')) {
    function rx_test_issued_count(array $panel, $settings = null)
    {
        $pdo = rx_test_pdo();
        if ($pdo === null || !rx_test_ensure_schema()) {
            return 0;
        }
        $settings = is_array($settings) ? $settings : rx_test_panel_settings($panel);
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_account_reservation WHERE code_panel = ? AND quota_generation = ? AND state IN ('created', 'delivered', 'undelivered')");
            $stmt->execute([(string) ($panel['code_panel'] ?? ''), rx_test_generation($settings)]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[test_account_policy] issued count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('rx_test_expire_timestamp')) {
    function rx_test_expire_timestamp(array $settings, $now = null)
    {
        if (!empty($settings['time_unlimited'])) {
            return 0;
        }
        $hours = (int) ($settings['time_hours'] ?? 0);
        if ($hours <= 0) {
            $hours = RX_TEST_DEFAULT_HOURS;
        }
        $now = $now === null ? time() : (int) $now;
        return $now + ($hours * 3600);
    }
}

if (!function_exists('rx_test_data_limit_bytes')) {
    function rx_test_data_limit_bytes(array $settings)
    {
        if (!empty($settings['volume_unlimited'])) {
            return 0;
        }
        $mb = (int) ($settings['volume_mb'] ?? 0);
        if ($mb <= 0) {
            $mb = RX_TEST_DEFAULT_MB;
        }
        return $mb * 1048576;
    }
}

if (!function_exists('rx_test_stored_hours')) {
    function rx_test_stored_hours(array $settings)
    {
        return !empty($settings['time_unlimited']) ? 0 : max(1, (int) ($settings['time_hours'] ?? RX_TEST_DEFAULT_HOURS));
    }
}

if (!function_exists('rx_test_stored_mb')) {
    function rx_test_stored_mb(array $settings)
    {
        return !empty($settings['volume_unlimited']) ? 0 : max(1, (int) ($settings['volume_mb'] ?? RX_TEST_DEFAULT_MB));
    }
}

if (!function_exists('rx_test_unlimited_label')) {
    function rx_test_unlimited_label()
    {
        global $textbotlang;
        $label = $textbotlang['users']['stateus']['Unlimited'] ?? '';
        return is_string($label) && $label !== '' ? $label : 'نامحدود';
    }
}

if (!function_exists('rx_test_display_time')) {
    function rx_test_display_time(array $settings)
    {
        return !empty($settings['time_unlimited']) ? rx_test_unlimited_label() : (string) rx_test_stored_hours($settings);
    }
}

if (!function_exists('rx_test_display_volume')) {
    function rx_test_display_volume(array $settings)
    {
        if (!empty($settings['volume_unlimited'])) {
            return rx_test_unlimited_label();
        }
        return formatBytes((float) rx_test_data_limit_bytes($settings));
    }
}

if (!function_exists('rx_test_feature_enabled')) {
    function rx_test_feature_enabled()
    {
        $setting = rx_test_setting();
        return function_exists('check_active_btn') && check_active_btn($setting['keyboardmain'] ?? '', 'text_usertest');
    }
}

if (!function_exists('rx_test_verification_gate')) {
    function rx_test_verification_gate(array $user, $isAdmin)
    {
        $setting = rx_test_setting();
        $skip = function_exists('rx_auth_skip_user') && rx_auth_skip_user($user);
        if ($skip) {
            return null;
        }
        $phonePolicy = (($setting['get_number'] ?? '') === 'onAuthenticationphone') || (($setting['iran_number'] ?? '') === 'onAuthenticationiran');
        if ($phonePolicy && (string) ($user['number'] ?? 'none') === 'none') {
            return 'phone';
        }
        if (!$isAdmin && ($setting['verifystart'] ?? '') === 'onverify' && intval($user['verify'] ?? 0) === 0) {
            return 'verify';
        }
        return null;
    }
}

if (!function_exists('rx_test_user_hidden')) {
    function rx_test_user_hidden(array $panel, $userId)
    {
        $raw = $panel['hide_user'] ?? null;
        if ($raw === null || $raw === '') {
            return false;
        }
        $list = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($list)) {
            return false;
        }
        return in_array((string) $userId, array_map('strval', $list), true);
    }
}

if (!function_exists('rx_test_user_agent')) {
    function rx_test_user_agent(array $user)
    {
        $agent = trim((string) ($user['agent'] ?? ''));
        return $agent === '' ? 'f' : $agent;
    }
}

if (!function_exists('rx_test_panel_usable')) {
    function rx_test_panel_usable(array $panel)
    {
        if (($panel['TestAccount'] ?? '') !== 'ONTestAccount') {
            return false;
        }
        if (!rx_test_valid_code((string) ($panel['code_panel'] ?? '')) || trim((string) ($panel['name_panel'] ?? '')) === '') {
            return false;
        }
        $type = trim((string) ($panel['type'] ?? ''));
        if ($type === '') {
            return false;
        }
        if ($type !== 'Manualsale' && trim((string) ($panel['url_panel'] ?? '')) === '') {
            return false;
        }
        return rx_test_panel_settings($panel)['mode'] !== 'invalid';
    }
}

if (!function_exists('rx_test_panel_allowed_for_user')) {
    function rx_test_panel_allowed_for_user(array $panel, array $user)
    {
        $panelAgent = trim((string) ($panel['agent'] ?? ''));
        if ($panelAgent !== 'all' && $panelAgent !== rx_test_user_agent($user)) {
            return false;
        }
        if (rx_test_user_hidden($panel, $user['id'] ?? '')) {
            return false;
        }
        return rx_test_panel_usable($panel);
    }
}

if (!function_exists('rx_test_eligible_panels')) {
    function rx_test_eligible_panels(array $user)
    {
        $pdo = rx_test_pdo();
        if ($pdo === null || !isset($user['id'])) {
            return [];
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE TestAccount = 'ONTestAccount' AND (agent = ? OR agent = 'all') ORDER BY id ASC");
            $stmt->execute([rx_test_user_agent($user)]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[test_account_policy] eligible panels: ' . $e->getMessage());
            return [];
        }
        $list = [];
        foreach ($rows ?: [] as $row) {
            if (is_array($row) && rx_test_panel_allowed_for_user($row, $user)) {
                $list[] = $row;
            }
        }
        return $list;
    }
}

if (!function_exists('rx_test_resolve_panel')) {
    function rx_test_resolve_panel(array $user, $code = null, $eligible = null)
    {
        $eligible = is_array($eligible) ? $eligible : rx_test_eligible_panels($user);
        $code = $code === null ? '' : trim((string) $code);
        if ($code === '') {
            return count($eligible) === 1 ? $eligible[0] : null;
        }
        if (!rx_test_valid_code($code)) {
            return null;
        }
        foreach ($eligible as $panel) {
            if ((string) $panel['code_panel'] === $code) {
                return $panel;
            }
        }
        return null;
    }
}

if (!function_exists('rx_test_usage_seed')) {
    function rx_test_usage_seed($userId, array $panel, $generation = 1)
    {
        $pdo = rx_test_pdo();
        if ($pdo === null || !rx_test_ensure_schema()) {
            return false;
        }
        $userId = (string) $userId;
        $code = (string) $panel['code_panel'];
        $generation = max(1, (int) $generation);
        $check = $pdo->prepare("SELECT quota_generation FROM test_account_usage WHERE user_id = ? AND code_panel = ? LIMIT 1");
        $check->execute([$userId, $code]);
        $current = $check->fetchColumn();
        $now = time();
        if ($current !== false) {
            if ((int) $current < $generation) {
                $roll = $pdo->prepare("UPDATE test_account_usage SET used_count = 0, quota_generation = ?, updated_at = ? WHERE user_id = ? AND code_panel = ? AND quota_generation < ?");
                $roll->execute([$generation, $now, $userId, $code, $generation]);
            }
            return true;
        }
        $baseline = 0;
        if ($generation <= 1) {
            $count = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = ? AND Service_location = ? AND name_product = ? AND Status <> 'Unsuccessful'");
            $count->execute([$userId, (string) $panel['name_panel'], RX_TEST_PRODUCT_NAME]);
            $baseline = max(0, (int) $count->fetchColumn());
        }
        $insert = $pdo->prepare("INSERT IGNORE INTO test_account_usage (user_id, code_panel, used_count, quota_generation, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->execute([$userId, $code, $baseline, $generation, $now, $now]);
        if ($insert->rowCount() === 0) {
            $roll = $pdo->prepare("UPDATE test_account_usage SET used_count = 0, quota_generation = ?, updated_at = ? WHERE user_id = ? AND code_panel = ? AND quota_generation < ?");
            $roll->execute([$generation, $now, $userId, $code, $generation]);
        }
        return true;
    }
}

if (!function_exists('rx_test_usage_count')) {
    function rx_test_usage_count($userId, array $panel, $generation = 1)
    {
        $pdo = rx_test_pdo();
        if ($pdo === null || !rx_test_usage_seed($userId, $panel, $generation)) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT used_count, quota_generation FROM test_account_usage WHERE user_id = ? AND code_panel = ? LIMIT 1");
        $stmt->execute([(string) $userId, (string) $panel['code_panel']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return (int) $row['quota_generation'] === max(1, (int) $generation) ? (int) $row['used_count'] : 0;
    }
}

if (!function_exists('rx_test_legacy_quota')) {
    function rx_test_legacy_quota($userId)
    {
        $pdo = rx_test_pdo();
        if ($pdo === null) {
            return 0;
        }
        $stmt = $pdo->prepare("SELECT limit_usertest FROM user WHERE id = ? LIMIT 1");
        $stmt->execute([(string) $userId]);
        $value = $stmt->fetchColumn();
        return $value === false ? 0 : max(0, (int) $value);
    }
}

if (!function_exists('rx_test_quota_state')) {
    function rx_test_quota_state(array $user, array $panel, array $settings, $isAdmin, $now = null)
    {
        $state = [
            'mode'                 => $settings['mode'],
            'source'               => $settings['mode'] === 'panel' ? 'panel' : 'legacy',
            'bypass'               => (bool) $isAdmin,
            'limit'                => null,
            'used'                 => null,
            'remaining'            => 0,
            'audience_mode'        => (string) ($settings['audience_mode'] ?? RX_TEST_AUDIENCE_ALL),
            'quota_generation'     => rx_test_generation($settings),
            'manual_override'      => false,
            'reason'               => '',
        ];
        try {
            if ($settings['mode'] === 'legacy') {
                $state['remaining'] = rx_test_legacy_quota($user['id']);
            } elseif ($settings['mode'] === 'panel') {
                $override = $isAdmin ? null : rx_test_override_get($user['id']);
                if (is_array($override) && $override['active'] && $override['remaining'] > 0) {
                    $state['source'] = 'manual_override';
                    $state['manual_override'] = true;
                    $state['remaining'] = $override['remaining'];
                } elseif (!$isAdmin && $state['audience_mode'] === RX_TEST_AUDIENCE_NEW && !rx_test_user_is_new($user, $now)) {
                    $state['remaining'] = 0;
                    $state['reason'] = (is_array($override) && $override['active']) ? 'limit_reached' : 'audience_restricted';
                } elseif (!empty($settings['limit_unlimited'])) {
                    $state['remaining'] = null;
                } else {
                    $used = rx_test_usage_count($user['id'], $panel, $state['quota_generation']);
                    $state['limit'] = (int) $settings['limit'];
                    $state['used'] = $used;
                    $state['remaining'] = $used === null ? 0 : max(0, (int) $settings['limit'] - $used);
                }
            }
        } catch (Throwable $e) {
            error_log('[test_account_policy] quota state: ' . $e->getMessage());
            $state['remaining'] = 0;
        }
        if ($isAdmin) {
            $state['source'] = 'admin';
        }
        $state['can_create'] = $state['bypass'] || $state['remaining'] === null || $state['remaining'] > 0;
        if (!$state['can_create'] && $state['reason'] === '') {
            $state['reason'] = 'limit_reached';
        }
        if ($state['can_create']) {
            $state['reason'] = '';
        }
        return $state;
    }
}

if (!function_exists('rx_test_manual_stock_available')) {
    function rx_test_manual_stock_available(array $panel)
    {
        if (($panel['type'] ?? '') !== 'Manualsale') {
            return true;
        }
        $pdo = rx_test_pdo();
        if ($pdo === null) {
            return false;
        }
        $stmt = $pdo->prepare("SELECT 1 FROM manualsell WHERE codepanel = ? AND codeproduct = 'usertest' AND status = 'active' LIMIT 1");
        $stmt->execute([(string) $panel['code_panel']]);
        return $stmt->fetchColumn() !== false;
    }
}

if (!function_exists('rx_test_check_policy')) {
    function rx_test_check_policy(array $user, $isAdmin, $code = null, $eligible = null)
    {
        if (!rx_test_feature_enabled()) {
            return ['ok' => false, 'eligible' => false, 'error' => 'feature_disabled'];
        }
        $gate = rx_test_verification_gate($user, $isAdmin);
        if ($gate !== null) {
            return ['ok' => false, 'eligible' => false, 'error' => 'verify_' . $gate];
        }
        $eligible = is_array($eligible) ? $eligible : rx_test_eligible_panels($user);
        if (empty($eligible)) {
            return ['ok' => false, 'eligible' => false, 'error' => 'no_panel'];
        }
        $panel = rx_test_resolve_panel($user, $code, $eligible);
        if ($panel === null) {
            return ['ok' => false, 'eligible' => false, 'error' => ($code === null || trim((string) $code) === '') ? 'panel_required' : 'panel_unavailable'];
        }
        $settings = rx_test_panel_settings($panel);
        $quota = rx_test_quota_state($user, $panel, $settings, $isAdmin);
        $result = [
            'ok'               => (bool) $quota['can_create'],
            'eligible'         => (bool) $quota['can_create'],
            'error'            => $quota['can_create'] ? '' : $quota['reason'],
            'panel'            => $panel,
            'settings'         => $settings,
            'quota'            => $quota,
            'quota_source'     => $quota['source'],
            'remaining'        => $quota['remaining'],
            'audience_mode'    => $quota['audience_mode'],
            'quota_generation' => $quota['quota_generation'],
            'manual_override'  => $quota['manual_override'],
        ];
        return $result;
    }
}

if (!function_exists('rx_test_usage_adjust')) {
    function rx_test_usage_adjust($userId, array $panel, $delta, $cap = null, $generation = 1)
    {
        $pdo = rx_test_pdo();
        $generation = max(1, (int) $generation);
        if ($pdo === null) {
            return false;
        }
        $now = time();
        if ($delta > 0) {
            if (!rx_test_usage_seed($userId, $panel, $generation)) {
                return false;
            }
            if ($cap === null) {
                $stmt = $pdo->prepare("UPDATE test_account_usage SET used_count = used_count + 1, updated_at = ? WHERE user_id = ? AND code_panel = ? AND quota_generation = ?");
                $stmt->execute([$now, (string) $userId, (string) $panel['code_panel'], $generation]);
            } else {
                $stmt = $pdo->prepare("UPDATE test_account_usage SET used_count = used_count + 1, updated_at = ? WHERE user_id = ? AND code_panel = ? AND quota_generation = ? AND used_count < ?");
                $stmt->execute([$now, (string) $userId, (string) $panel['code_panel'], $generation, (int) $cap]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE test_account_usage SET used_count = CASE WHEN used_count > 0 THEN used_count - 1 ELSE 0 END, updated_at = ? WHERE user_id = ? AND code_panel = ? AND quota_generation = ?");
            $stmt->execute([$now, (string) $userId, (string) $panel['code_panel'], $generation]);
        }
        return $stmt->rowCount() === 1;
    }
}

if (!function_exists('rx_test_reserve')) {
    function rx_test_reserve(array $user, array $panel, array $settings, $isAdmin, $source = 'bot', &$error = null)
    {
        $error = '';
        $pdo = rx_test_pdo();
        if ($pdo === null || !isset($user['id'])) {
            $error = 'limit_reached';
            return null;
        }
        $userId = (string) $user['id'];
        $schemaReady = rx_test_ensure_schema();
        if ($settings['mode'] === 'panel' && rx_test_valid_code((string) ($panel['code_panel'] ?? ''))) {
            $fresh = rx_test_admin_load_panel((string) $panel['code_panel']);
            if (is_array($fresh)) {
                $freshSettings = rx_test_panel_settings($fresh);
                if ($freshSettings['mode'] !== 'panel') {
                    $error = 'panel_unavailable';
                    return null;
                }
                $panel = $fresh;
                $settings = $freshSettings;
            }
        }
        $generation = rx_test_generation($settings);
        $reservation = [
            'id'               => 0,
            'user_id'          => $userId,
            'panel'            => $panel,
            'quota_mode'       => $isAdmin ? 'admin' : $settings['mode'],
            'quota_generation' => $generation,
            'usage_counted'    => false,
            'override_revision'=> null,
            'state'            => 'reserved',
        ];
        try {
            if ($isAdmin) {
                if ($schemaReady) {
                    $reservation['usage_counted'] = rx_test_usage_adjust($userId, $panel, 1, null, $generation);
                }
            } elseif ($settings['mode'] === 'legacy') {
                $stmt = $pdo->prepare("UPDATE user SET limit_usertest = limit_usertest - 1 WHERE id = ? AND limit_usertest > 0");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() !== 1) {
                    $error = 'limit_reached';
                    return null;
                }
                if (function_exists('clearSelectCache')) {
                    clearSelectCache('user');
                }
                if ($schemaReady) {
                    try {
                        $reservation['usage_counted'] = rx_test_usage_adjust($userId, $panel, 1, null, $generation);
                    } catch (Throwable $e) {
                        error_log('[test_account_policy] legacy usage track: ' . $e->getMessage());
                    }
                }
            } elseif ($settings['mode'] === 'panel') {
                if (!$schemaReady) {
                    $error = 'limit_reached';
                    return null;
                }
                $override = rx_test_override_get($userId);
                $usedOverride = false;
                if (is_array($override) && $override['active'] && $override['remaining'] > 0) {
                    $take = $pdo->prepare("UPDATE test_account_user_override SET remaining_limit = remaining_limit - 1, updated_at = ? WHERE user_id = ? AND active = 1 AND remaining_limit > 0 AND revision = ?");
                    $take->execute([time(), $userId, $override['revision']]);
                    if ($take->rowCount() === 1) {
                        $usedOverride = true;
                        $reservation['quota_mode'] = 'manual_override';
                        $reservation['override_revision'] = $override['revision'];
                    } else {
                        $override = rx_test_override_get($userId);
                    }
                }
                if (!$usedOverride) {
                    if (($settings['audience_mode'] ?? RX_TEST_AUDIENCE_ALL) === RX_TEST_AUDIENCE_NEW && !rx_test_user_is_new($user)) {
                        $error = (is_array($override) && $override['active']) ? 'limit_reached' : 'audience_restricted';
                        return null;
                    }
                    $cap = !empty($settings['limit_unlimited']) ? null : (int) $settings['limit'];
                    if (!rx_test_usage_adjust($userId, $panel, 1, $cap, $generation)) {
                        $error = 'limit_reached';
                        return null;
                    }
                    $reservation['usage_counted'] = true;
                }
            } else {
                $error = 'panel_unavailable';
                return null;
            }
        } catch (Throwable $e) {
            error_log('[test_account_policy] reserve: ' . $e->getMessage());
            $error = 'limit_reached';
            return null;
        }
        if ($schemaReady) {
            try {
                $now = time();
                $stmt = $pdo->prepare("INSERT INTO test_account_reservation (user_id, code_panel, quota_mode, quota_generation, usage_counted, state, source, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'reserved', ?, ?, ?)");
                $stmt->execute([$userId, (string) $panel['code_panel'], $reservation['quota_mode'], $generation, $reservation['usage_counted'] ? 1 : 0, substr((string) $source, 0, 16), $now, $now]);
                $reservation['id'] = (int) $pdo->lastInsertId();
            } catch (Throwable $e) {
                error_log('[test_account_policy] reservation log: ' . $e->getMessage());
            }
        }
        return $reservation;
    }
}

if (!function_exists('rx_test_reservation_update')) {
    function rx_test_reservation_update(array &$reservation, $state, $invoiceId = null, $username = null)
    {
        $pdo = rx_test_pdo();
        if ((int) ($reservation['id'] ?? 0) > 0 && $pdo !== null) {
            try {
                $stmt = $pdo->prepare("UPDATE test_account_reservation SET state = ?, id_invoice = COALESCE(?, id_invoice), username = COALESCE(?, username), updated_at = ? WHERE id = ? AND state NOT IN ('released')");
                $stmt->execute([$state, $invoiceId, $username, time(), (int) $reservation['id']]);
            } catch (Throwable $e) {
                error_log('[test_account_policy] reservation update: ' . $e->getMessage());
            }
        }
        if (($reservation['state'] ?? '') !== 'released') {
            $reservation['state'] = $state;
        }
    }
}

if (!function_exists('rx_test_release')) {
    function rx_test_release(array &$reservation)
    {
        $pdo = rx_test_pdo();
        if ($pdo === null || !in_array($reservation['state'] ?? '', ['reserved', 'failed'], true)) {
            return false;
        }
        if ((int) ($reservation['id'] ?? 0) > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE test_account_reservation SET state = 'released', updated_at = ? WHERE id = ? AND state IN ('reserved', 'failed')");
                $stmt->execute([time(), (int) $reservation['id']]);
                if ($stmt->rowCount() !== 1) {
                    return false;
                }
            } catch (Throwable $e) {
                error_log('[test_account_policy] release mark: ' . $e->getMessage());
            }
        }
        $reservation['state'] = 'released';
        try {
            if (($reservation['quota_mode'] ?? '') === 'legacy') {
                $stmt = $pdo->prepare("UPDATE user SET limit_usertest = limit_usertest + 1 WHERE id = ?");
                $stmt->execute([(string) $reservation['user_id']]);
                if (function_exists('clearSelectCache')) {
                    clearSelectCache('user');
                }
            }
            if (($reservation['quota_mode'] ?? '') === 'manual_override' && $reservation['override_revision'] !== null) {
                $stmt = $pdo->prepare("UPDATE test_account_user_override SET remaining_limit = remaining_limit + 1, updated_at = ? WHERE user_id = ? AND revision = ?");
                $stmt->execute([time(), (string) $reservation['user_id'], (int) $reservation['override_revision']]);
            }
            if (!empty($reservation['usage_counted'])) {
                rx_test_usage_adjust($reservation['user_id'], $reservation['panel'], -1, null, (int) ($reservation['quota_generation'] ?? 1));
            }
        } catch (Throwable $e) {
            error_log('[test_account_policy] release credit: ' . $e->getMessage());
            return false;
        }
        return true;
    }
}

if (!function_exists('rx_test_build_username')) {
    function rx_test_build_username(array $user, array $panel, $customText, $randomString)
    {
        $usernameAc = generateUsername(
            (string) $user['id'],
            (string) ($panel['MethodUsername'] ?? ''),
            (string) ($user['username'] ?? ''),
            (string) $randomString,
            strtolower((string) $customText),
            (string) ($panel['namecustom'] ?? ''),
            (string) ($user['namecustom'] ?? '')
        );
        $usernameAc = strtolower((string) $usernameAc);
        $taken = rxTableValueExists('invoice', 'username', $usernameAc);
        if (!$taken && ($panel['type'] ?? '') !== 'Manualsale') {
            try {
                $managePanel = new ManagePanel();
                $remote = $managePanel->DataUser($panel['name_panel'], $usernameAc);
                $taken = is_array($remote) && isset($remote['username']);
            } catch (Throwable $e) {
                error_log('[test_account_policy] username check: ' . $e->getMessage());
            }
        }
        if ($taken) {
            $usernameAc = rand(1000000, 9999999) . '-' . $usernameAc;
        }
        return $usernameAc;
    }
}

if (!function_exists('rx_test_provision')) {
    function rx_test_provision(array $user, array $panel, array $settings, array &$reservation, $usernameAc, $orderId = null)
    {
        $pdo = rx_test_pdo();
        $orderId = $orderId === null ? bin2hex(random_bytes(4)) : (string) $orderId;
        $expire = rx_test_expire_timestamp($settings);
        $dataLimit = rx_test_data_limit_bytes($settings);
        $result = [
            'ok'         => false,
            'error'      => '',
            'msg'        => '',
            'order_id'   => $orderId,
            'username'   => (string) $usernameAc,
            'expire'     => $expire,
            'data_limit' => $dataLimit,
            'hours'      => rx_test_stored_hours($settings),
            'volume_mb'  => rx_test_stored_mb($settings),
            'output'     => null,
        ];
        if ($pdo === null) {
            rx_test_reservation_update($reservation, 'failed');
            rx_test_release($reservation);
            $result['error'] = 'invoice_failed';
            return $result;
        }
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Volume_unit, Service_time, Status, notifctions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                (string) $user['id'],
                $orderId,
                (string) $usernameAc,
                (string) time(),
                (string) $panel['name_panel'],
                RX_TEST_PRODUCT_NAME,
                '0',
                (string) $result['volume_mb'],
                'MB',
                (string) $result['hours'],
                'active',
                json_encode(['volume' => false, 'time' => false]),
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('test invoice was not inserted');
            }
            if (function_exists('clearSelectCache')) {
                clearSelectCache('invoice');
            }
        } catch (Throwable $e) {
            error_log('[test_account_policy] invoice insert: ' . $e->getMessage());
            rx_test_reservation_update($reservation, 'failed');
            rx_test_release($reservation);
            $result['error'] = 'invoice_failed';
            $result['msg'] = $e->getMessage();
            return $result;
        }
        rx_test_reservation_update($reservation, 'reserved', $orderId, (string) $usernameAc);
        $datac = [
            'expire'     => $expire,
            'data_limit' => $dataLimit,
            'from_id'    => $user['id'],
            'username'   => (string) $usernameAc,
            'type'       => 'usertest',
        ];
        try {
            $managePanel = new ManagePanel();
            $output = $managePanel->createUser($panel['name_panel'], 'usertest', (string) $usernameAc, $datac);
        } catch (Throwable $e) {
            $output = ['status' => 'Unsuccessful', 'msg' => $e->getMessage()];
        }
        $result['output'] = $output;
        if (!is_array($output) || empty($output['username'])) {
            $msg = is_array($output) ? ($output['msg'] ?? $output) : $output;
            $result['msg'] = is_string($msg) ? $msg : (string) json_encode($msg, JSON_UNESCAPED_UNICODE);
            rx_test_reservation_update($reservation, 'failed');
            try {
                $upd = $pdo->prepare("UPDATE invoice SET Status = 'Unsuccessful' WHERE id_invoice = ? AND id_user = ?");
                $upd->execute([$orderId, (string) $user['id']]);
                if (function_exists('clearSelectCache')) {
                    clearSelectCache('invoice');
                }
            } catch (Throwable $e) {
                error_log('[test_account_policy] invoice fail mark: ' . $e->getMessage());
            }
            rx_test_release($reservation);
            $stockEmpty = ($panel['type'] ?? '') === 'Manualsale' && $result['msg'] === 'Manualsale stock not found';
            $result['error'] = $stockEmpty ? 'stock_empty' : 'create_failed';
            return $result;
        }
        rx_test_reservation_update($reservation, 'created');
        $result['ok'] = true;
        $result['username'] = (string) $output['username'];
        return $result;
    }
}

if (!function_exists('rx_test_mark_delivery')) {
    function rx_test_mark_delivery(array &$reservation, $delivered)
    {
        rx_test_reservation_update($reservation, $delivered ? 'delivered' : 'undelivered');
    }
}

if (!function_exists('rx_test_admin_edit_settings')) {
    function rx_test_admin_edit_settings(array $panel)
    {
        $current = rx_test_panel_settings($panel);
        if ($current['mode'] === 'panel') {
            return $current;
        }
        $legacy = $current['mode'] === 'legacy' ? $current : rx_test_panel_settings(array_merge($panel, ['test_settings' => null]));
        $setting = rx_test_setting();
        $limit = rx_test_int_in_range(trim((string) ($setting['limit_usertest_all'] ?? '')), 1, RX_TEST_MAX_LIMIT);
        return [
            'mode'             => 'panel',
            'limit'            => $limit === null ? 1 : $limit,
            'limit_unlimited'  => false,
            'time_hours'       => $legacy['time_unlimited'] ? 0 : $legacy['time_hours'],
            'time_unlimited'   => $legacy['time_unlimited'],
            'volume_mb'        => $legacy['volume_unlimited'] ? 0 : $legacy['volume_mb'],
            'volume_unlimited' => $legacy['volume_unlimited'],
            'audience_mode'    => RX_TEST_AUDIENCE_ALL,
            'quota_generation' => 1,
            'quota_changed_at' => 0,
        ];
    }
}

if (!function_exists('rx_test_admin_save_settings')) {
    function rx_test_admin_save_settings(array $panel, $field, $value, $audience = null)
    {
        $pdo = rx_test_pdo();
        $code = (string) ($panel['code_panel'] ?? '');
        if ($pdo === null || !rx_test_valid_code($code) || !rx_test_ensure_settings_column()) {
            return false;
        }
        if (!in_array($field, ['limit', 'time', 'volume'], true)) {
            return false;
        }
        if ($audience !== null && !in_array($audience, [RX_TEST_AUDIENCE_ALL, RX_TEST_AUDIENCE_NEW], true)) {
            return false;
        }
        if ($value !== 'unlimited') {
            $max = $field === 'limit' ? RX_TEST_MAX_LIMIT : ($field === 'time' ? RX_TEST_MAX_HOURS : RX_TEST_MAX_MB);
            $value = rx_test_int_in_range(rx_test_normalize_digits($value), 1, $max);
            if ($value === null) {
                return false;
            }
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $row = rx_test_admin_load_panel($code);
            if ($row === null) {
                return false;
            }
            $previousRaw = $row['test_settings'] ?? null;
            $settings = rx_test_admin_edit_settings($row);
            $extra = [];
            if (is_string($previousRaw) && trim($previousRaw) !== '') {
                $decoded = json_decode($previousRaw, true);
                if (is_array($decoded) && rx_test_panel_settings($row)['mode'] === 'panel') {
                    $extra = $decoded;
                }
            }
            if ($field === 'limit') {
                $settings['limit_unlimited'] = $value === 'unlimited';
                $settings['limit'] = $value === 'unlimited' ? 0 : $value;
                if ($audience !== null) {
                    $settings['audience_mode'] = $audience;
                }
                $settings['quota_generation'] = rx_test_panel_settings($row)['mode'] === 'panel' ? rx_test_generation($settings) + 1 : 1;
                $settings['quota_changed_at'] = time();
            } elseif ($field === 'time') {
                $settings['time_unlimited'] = $value === 'unlimited';
                $settings['time_hours'] = $value === 'unlimited' ? 0 : $value;
            } else {
                $settings['volume_unlimited'] = $value === 'unlimited';
                $settings['volume_mb'] = $value === 'unlimited' ? 0 : $value;
            }
            $payload = array_merge($extra, [
                'v'                => 1,
                'limit'            => (int) $settings['limit'],
                'limit_unlimited'  => (bool) $settings['limit_unlimited'],
                'time_hours'       => (int) $settings['time_hours'],
                'time_unlimited'   => (bool) $settings['time_unlimited'],
                'volume_mb'        => (int) $settings['volume_mb'],
                'volume_unlimited' => (bool) $settings['volume_unlimited'],
                'audience_mode'    => (string) $settings['audience_mode'],
                'quota_generation' => rx_test_generation($settings),
                'quota_changed_at' => (int) $settings['quota_changed_at'],
            ]);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (!is_array(rx_test_parse_settings_json($json))) {
                return false;
            }
            try {
                if ($previousRaw === null) {
                    $stmt = $pdo->prepare("UPDATE marzban_panel SET test_settings = ?, time_usertest = ?, val_usertest = ? WHERE code_panel = ? AND test_settings IS NULL");
                    $stmt->execute([$json, (string) rx_test_stored_hours($settings), (string) rx_test_stored_mb($settings), $code]);
                } else {
                    $stmt = $pdo->prepare("UPDATE marzban_panel SET test_settings = ?, time_usertest = ?, val_usertest = ? WHERE code_panel = ? AND test_settings = ?");
                    $stmt->execute([$json, (string) rx_test_stored_hours($settings), (string) rx_test_stored_mb($settings), $code, (string) $previousRaw]);
                }
            } catch (Throwable $e) {
                error_log('[test_account_policy] save settings: ' . $e->getMessage());
                return false;
            }
            if ($stmt->rowCount() === 1 || ($previousRaw !== null && (string) $previousRaw === $json)) {
                if (function_exists('clearSelectCache')) {
                    clearSelectCache('marzban_panel');
                }
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('rx_test_admin_load_panel')) {
    function rx_test_admin_load_panel($code)
    {
        $pdo = rx_test_pdo();
        if ($pdo === null || !rx_test_valid_code((string) $code)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE code_panel = ? LIMIT 1");
            $stmt->execute([(string) $code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('rx_test_audience_label')) {
    function rx_test_audience_label($audience)
    {
        return $audience === RX_TEST_AUDIENCE_NEW ? 'کاربران جدید ۲۴ ساعت گذشته' : 'همه کاربران';
    }
}

if (!function_exists('rx_test_admin_menu_render')) {
    function rx_test_admin_menu_render(array $panel)
    {
        $settings = rx_test_panel_settings($panel);
        $code = (string) $panel['code_panel'];
        $unlimited = '♾ نامحدود';
        $lines = ['🧪 <b>تنظیمات اکانت تست</b>', '', '🖥 پنل: ' . htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8')];
        if ($settings['mode'] === 'invalid') {
            $lines[] = '⚠️ تنظیمات ذخیره‌شده نامعتبر است؛ تا اصلاح، تست از این پنل صادر نمی‌شود.';
            $settings = rx_test_admin_edit_settings(array_merge($panel, ['test_settings' => null]));
            $settings['mode'] = 'invalid';
        }
        if ($settings['mode'] === 'legacy') {
            $lines[] = '📌 حالت: قدیمی (سهمیه عمومی هر کاربر)';
        } elseif ($settings['mode'] === 'panel') {
            $lines[] = '📌 حالت: اختصاصی همین پنل';
        }
        $lines[] = '👥 اعمال روی: ' . rx_test_audience_label($settings['audience_mode'] ?? RX_TEST_AUDIENCE_ALL);
        if ($settings['mode'] === 'legacy') {
            $lines[] = '👤 سقف تست هر کاربر: طبق سهمیه عمومی کاربر';
        } else {
            $lines[] = '👤 سقف تست هر کاربر: ' . (!empty($settings['limit_unlimited']) ? $unlimited : (int) $settings['limit']);
        }
        $lines[] = '⏳ مدت زمان: ' . (!empty($settings['time_unlimited']) ? $unlimited : ((int) $settings['time_hours'] . ' ساعت'));
        $lines[] = '💾 حجم: ' . (!empty($settings['volume_unlimited']) ? $unlimited : ((int) $settings['volume_mb'] . ' مگابایت'));
        $lines[] = '📊 تست‌های دریافت‌شده: ' . rx_test_issued_count($panel, $settings['mode'] === 'invalid' ? null : $settings);
        $lines[] = '';
        $lines[] = 'با ثبت اولین تغییر، این پنل به حالت اختصاصی می‌رود و سقف تست برای هر پنل جداگانه شمرده می‌شود. تغییر سقف تست، شمارنده این دوره را صفر می‌کند.';
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '👤 تغییر سقف تست', 'callback_data' => 'tset_limit_' . $code],
                    ['text' => '♾ سقف نامحدود', 'callback_data' => 'tset_ulimit_' . $code],
                ],
                [
                    ['text' => '⏳ تغییر مدت زمان', 'callback_data' => 'tset_time_' . $code],
                    ['text' => '♾ زمان نامحدود', 'callback_data' => 'tset_utime_' . $code],
                ],
                [
                    ['text' => '💾 تغییر حجم', 'callback_data' => 'tset_vol_' . $code],
                    ['text' => '♾ حجم نامحدود', 'callback_data' => 'tset_uvol_' . $code],
                ],
                [
                    ['text' => '🔙 بازگشت به مدیریت پنل', 'callback_data' => 'tset_back_' . $code],
                ],
            ],
        ];
        return [
            'text'     => implode("\n", $lines),
            'keyboard' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
        ];
    }
}

if (!function_exists('rx_test_admin_audience_menu_render')) {
    function rx_test_admin_audience_menu_render(array $panel)
    {
        $code = (string) $panel['code_panel'];
        $settings = rx_test_panel_settings($panel);
        $text = "👤 <b>تغییر سقف تست</b>\n\n🖥 پنل: " . htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8')
            . "\n👥 اعمال فعلی: " . rx_test_audience_label($settings['audience_mode'] ?? RX_TEST_AUDIENCE_ALL)
            . "\n\nسقف جدید روی کدام کاربران اعمال شود؟\n⚠️ با ثبت سقف جدید، شمارنده تست‌های این پنل از صفر شروع می‌شود.";
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🆕 اعمال روی کاربران جدید ۲۴ ساعت گذشته', 'callback_data' => 'tset_anew_' . $code]],
                [['text' => '👥 اعمال روی تمامی کاربران', 'callback_data' => 'tset_aall_' . $code]],
                [['text' => '🔙 بازگشت', 'callback_data' => 'tset_menu_' . $code]],
            ],
        ];
        return [
            'text'     => $text,
            'keyboard' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
        ];
    }
}

if (!function_exists('rx_test_admin_flow_state')) {
    function rx_test_admin_flow_state($code, $audience)
    {
        return 'tset:' . $code . ':' . $audience;
    }
}

if (!function_exists('rx_test_admin_parse_flow_state')) {
    function rx_test_admin_parse_flow_state($raw, $expectedCode)
    {
        if (!is_string($raw) || !preg_match('/^tset:([A-Za-z0-9_-]{1,100}):(all_users|new_users_24h)$/', $raw, $m)) {
            return null;
        }
        return $m[1] === (string) $expectedCode ? $m[2] : null;
    }
}

if (!function_exists('rx_test_audience_denied_text')) {
    function rx_test_audience_denied_text()
    {
        $fallback = '❌ دریافت اکانت تست این پنل فقط برای کاربرانی فعال است که در ۲۴ ساعت گذشته عضو شده‌اند.';
        return function_exists('faoxima_textbot_get') ? faoxima_textbot_get('dyn_testaccount_audience_restricted', $fallback) : $fallback;
    }
}

if (!function_exists('rx_test_overview')) {
    function rx_test_overview(array $user, $isAdmin)
    {
        $overview = ['enabled' => rx_test_feature_enabled(), 'gate' => null, 'panels' => [], 'any_available' => false, 'reason' => ''];
        if (!$overview['enabled']) {
            $overview['reason'] = 'feature_disabled';
            return $overview;
        }
        $overview['gate'] = rx_test_verification_gate($user, $isAdmin);
        $audienceOnly = true;
        foreach (rx_test_eligible_panels($user) as $panel) {
            $settings = rx_test_panel_settings($panel);
            $quota = rx_test_quota_state($user, $panel, $settings, $isAdmin);
            $overview['panels'][] = ['panel' => $panel, 'settings' => $settings, 'quota' => $quota];
            if ($quota['can_create']) {
                $overview['any_available'] = true;
            } elseif ($quota['reason'] !== 'audience_restricted') {
                $audienceOnly = false;
            }
        }
        if ($overview['gate'] !== null) {
            $overview['reason'] = 'verify_' . $overview['gate'];
        } elseif (empty($overview['panels'])) {
            $overview['reason'] = 'no_panel';
        } elseif (!$overview['any_available']) {
            $overview['reason'] = $audienceOnly ? 'audience_restricted' : 'limit_reached';
        }
        return $overview;
    }
}
