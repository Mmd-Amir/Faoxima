<?php

if (!defined('RX_SBV_FEE_MIN')) {
    define('RX_SBV_FEE_MIN', 1);
}
if (!defined('RX_SBV_FEE_MAX')) {
    define('RX_SBV_FEE_MAX', 999);
}
if (!defined('RX_SBV_RECEIPT_DELAY')) {
    define('RX_SBV_RECEIPT_DELAY', 600);
}
if (!defined('RX_SBV_LIFETIME_MIN_MINUTES')) {
    define('RX_SBV_LIFETIME_MIN_MINUTES', 30);
}
if (!defined('RX_SBV_LIFETIME_MAX_MINUTES')) {
    define('RX_SBV_LIFETIME_MAX_MINUTES', 1440);
}
if (!defined('RX_SBV_REUSE_COOLDOWN')) {
    define('RX_SBV_REUSE_COOLDOWN', 43200);
}
if (!defined('RX_SBV_EXPIRE_GRACE')) {
    define('RX_SBV_EXPIRE_GRACE', 180);
}
if (!defined('RX_SBV_DEVICE_SKEW')) {
    define('RX_SBV_DEVICE_SKEW', 600);
}
if (!defined('RX_SBV_SCOPE')) {
    define('RX_SBV_SCOPE', 'global');
}
if (!defined('RX_SBV_MAX_BODY')) {
    define('RX_SBV_MAX_BODY', 65536);
}
if (!defined('RX_SBV_SUGGEST_RANGE_DEFAULT_TOMAN')) {
    define('RX_SBV_SUGGEST_RANGE_DEFAULT_TOMAN', 1000);
}
if (!defined('RX_SBV_SUGGEST_RANGE_MIN_TOMAN')) {
    define('RX_SBV_SUGGEST_RANGE_MIN_TOMAN', 0);
}
if (!defined('RX_SBV_SUGGEST_RANGE_MAX_TOMAN')) {
    define('RX_SBV_SUGGEST_RANGE_MAX_TOMAN', 10000000);
}

if (!function_exists('rx_sbv_log')) {
    function rx_sbv_log(string $event, string $message, array $ctx = []): void
    {
        if (function_exists('rx_log_event')) {
            try {
                rx_log_event($event, $message, $ctx);
                return;
            } catch (Throwable $e) {
            }
        }
        @error_log('[' . $event . '] ' . $message . ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE));
    }
}

if (!function_exists('rx_sbv_pdo')) {
    function rx_sbv_pdo(): ?PDO
    {
        global $pdo;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        if (class_exists('FaoximaDb')) {
            try {
                $p = FaoximaDb::pdo();
                if ($p instanceof PDO) {
                    return $p;
                }
            } catch (Throwable $e) {
            }
        }
        return null;
    }
}

if (!function_exists('rx_sbv_ddl')) {
    function rx_sbv_ddl(): array
    {
        return [
            'card_sms_payment' => "CREATE TABLE IF NOT EXISTS card_sms_payment (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                id_order VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                id_user VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                scope VARCHAR(32) NOT NULL DEFAULT 'global',
                principal_toman BIGINT UNSIGNED NOT NULL,
                fee_toman INT UNSIGNED NOT NULL,
                payable_toman BIGINT UNSIGNED NOT NULL,
                payable_rial BIGINT UNSIGNED NOT NULL,
                lifetime_sec INT UNSIGNED NOT NULL,
                receipt_delay_sec INT UNSIGNED NOT NULL,
                created_at INT UNSIGNED NOT NULL,
                receipt_after INT UNSIGNED NOT NULL,
                expires_at INT UNSIGNED NOT NULL,
                state VARCHAR(20) NOT NULL DEFAULT 'open',
                deposit_id BIGINT UNSIGNED NULL,
                received_rial BIGINT UNSIGNED NULL,
                resolution VARCHAR(30) NULL,
                resolved_by VARCHAR(64) NULL,
                resolved_at INT UNSIGNED NULL,
                resolution_note TEXT NULL,
                updated_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uniq_csp_order (id_order),
                UNIQUE KEY uniq_csp_deposit (deposit_id),
                KEY idx_csp_payable (payable_rial, state),
                KEY idx_csp_state_exp (state, expires_at),
                KEY idx_csp_user (id_user, state)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'card_sms_amount' => "CREATE TABLE IF NOT EXISTS card_sms_amount (
                scope VARCHAR(32) NOT NULL,
                payable_rial BIGINT UNSIGNED NOT NULL,
                id_order VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                reserved_at INT UNSIGNED NOT NULL,
                reusable_after INT UNSIGNED NULL,
                PRIMARY KEY (scope, payable_rial),
                KEY idx_csa_order (id_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'card_sms_deposit' => "CREATE TABLE IF NOT EXISTS card_sms_deposit (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                fingerprint CHAR(64) NULL,
                is_test TINYINT(1) NOT NULL DEFAULT 0,
                is_deposit TINYINT(1) NOT NULL DEFAULT 0,
                amount_raw VARCHAR(64) NULL,
                unit VARCHAR(16) NULL,
                amount_rial BIGINT UNSIGNED NULL,
                sender VARCHAR(191) NULL,
                bank VARCHAR(191) NULL,
                code VARCHAR(191) NULL,
                body TEXT NULL,
                device VARCHAR(191) NULL,
                attempt INT UNSIGNED NULL,
                received_at_raw VARCHAR(64) NULL,
                received_at_ms BIGINT UNSIGNED NULL,
                stored_at INT UNSIGNED NOT NULL,
                state VARCHAR(20) NOT NULL,
                id_order VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
                note TEXT NULL,
                reviewed_by VARCHAR(64) NULL,
                reviewed_at INT UNSIGNED NULL,
                updated_at INT UNSIGNED NOT NULL,
                payload MEDIUMTEXT NULL,
                UNIQUE KEY uniq_csd_event (event_id),
                UNIQUE KEY uniq_csd_fp (fingerprint),
                KEY idx_csd_state (state, stored_at),
                KEY idx_csd_amount (amount_rial, state),
                KEY idx_csd_order (id_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'card_sms_audit' => "CREATE TABLE IF NOT EXISTS card_sms_audit (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                id_order VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
                deposit_id BIGINT UNSIGNED NULL,
                actor VARCHAR(64) NULL,
                action VARCHAR(40) NOT NULL,
                principal_toman BIGINT UNSIGNED NULL,
                fee_toman INT UNSIGNED NULL,
                expected_rial BIGINT UNSIGNED NULL,
                received_rial BIGINT UNSIGNED NULL,
                credit_toman BIGINT UNSIGNED NULL,
                outcome VARCHAR(40) NULL,
                reason TEXT NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_csa_order (id_order),
                KEY idx_csa_deposit (deposit_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }
}

if (!function_exists('rx_sbv_ensure_schema')) {
    function rx_sbv_ensure_schema(): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return false;
        }
        try {
            foreach (rx_sbv_ddl() as $table => $ddl) {
                $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();
                if ($exists === false) {
                    if ($pdo->inTransaction()) {
                        return false;
                    }
                    $pdo->exec($ddl);
                }
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            rx_sbv_log('SBV_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('rx_sbv_has_schema')) {
    function rx_sbv_has_schema(): bool
    {
        static $known = null;
        if ($known === true) {
            return true;
        }
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return false;
        }
        try {
            $known = $pdo->query("SHOW TABLES LIKE 'card_sms_payment'")->fetchColumn() !== false;
        } catch (Throwable $e) {
            $known = false;
        }
        return $known;
    }
}

if (!function_exists('rx_sbv_setting_get')) {
    function rx_sbv_setting_get(string $name, ?string $default = null): ?string
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return $default;
        }
        try {
            $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = ? LIMIT 1");
            $stmt->execute([$name]);
            $value = $stmt->fetchColumn();
            return $value === false || $value === null ? $default : (string) $value;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('rx_sbv_setting_set')) {
    function rx_sbv_setting_set(string $name, string $value): bool
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return false;
        }
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?");
            $chk->execute([$name]);
            if ((int) $chk->fetchColumn() > 0) {
                $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?")->execute([$value, $name]);
            } else {
                $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)")->execute([$name, $value]);
            }
            if (function_exists('clearSelectCache')) {
                try {
                    clearSelectCache('PaySetting');
                } catch (Throwable $e) {
                }
            }
            return true;
        } catch (Throwable $e) {
            rx_sbv_log('SBV_SETTING_WRITE_FAILED', $e->getMessage(), ['name' => $name]);
            return false;
        }
    }
}

if (!function_exists('rx_sbv_api_key')) {
    function rx_sbv_api_key(): string
    {
        return trim((string) rx_sbv_setting_get('sbv_api_key', ''));
    }
}

if (!function_exists('rx_sbv_enabled')) {
    function rx_sbv_enabled(): bool
    {
        return rx_sbv_setting_get('sbv_status', 'off') === 'on' && rx_sbv_api_key() !== '';
    }
}

if (!function_exists('rx_sbv_lifetime_minutes')) {
    function rx_sbv_lifetime_minutes(): int
    {
        $raw = rx_sbv_setting_get('sbv_lifetime_min', (string) RX_SBV_LIFETIME_MIN_MINUTES);
        $min = ctype_digit((string) $raw) ? (int) $raw : RX_SBV_LIFETIME_MIN_MINUTES;
        return max(RX_SBV_LIFETIME_MIN_MINUTES, min(RX_SBV_LIFETIME_MAX_MINUTES, $min));
    }
}

if (!function_exists('rx_sbv_set_lifetime_minutes')) {
    function rx_sbv_set_lifetime_minutes(int $minutes): bool
    {
        if ($minutes < RX_SBV_LIFETIME_MIN_MINUTES || $minutes > RX_SBV_LIFETIME_MAX_MINUTES) {
            return false;
        }
        return rx_sbv_setting_set('sbv_lifetime_min', (string) $minutes);
    }
}

if (!function_exists('rx_sbv_suggestion_range_toman')) {
    function rx_sbv_suggestion_range_toman(): int
    {
        $raw = rx_sbv_setting_get('sbv_suggestion_range_toman', (string) RX_SBV_SUGGEST_RANGE_DEFAULT_TOMAN);
        $toman = ctype_digit((string) $raw) ? (int) $raw : RX_SBV_SUGGEST_RANGE_DEFAULT_TOMAN;
        return max(RX_SBV_SUGGEST_RANGE_MIN_TOMAN, min(RX_SBV_SUGGEST_RANGE_MAX_TOMAN, $toman));
    }
}

if (!function_exists('rx_sbv_set_suggestion_range_toman')) {
    function rx_sbv_set_suggestion_range_toman(int $toman): bool
    {
        if ($toman < RX_SBV_SUGGEST_RANGE_MIN_TOMAN || $toman > RX_SBV_SUGGEST_RANGE_MAX_TOMAN) {
            return false;
        }
        return rx_sbv_setting_set('sbv_suggestion_range_toman', (string) $toman);
    }
}

if (!function_exists('rx_sbv_generate_key')) {
    function rx_sbv_generate_key(): string
    {
        return 'fxv_' . bin2hex(random_bytes(32));
    }
}

if (!function_exists('rx_sbv_enable')) {
    function rx_sbv_enable(): array
    {
        if (!rx_sbv_ensure_schema()) {
            return ['ok' => false, 'reason' => 'schema_unavailable'];
        }
        $generated = false;
        if (rx_sbv_api_key() === '') {
            if (!rx_sbv_setting_set('sbv_api_key', rx_sbv_generate_key())) {
                return ['ok' => false, 'reason' => 'db_error'];
            }
            rx_sbv_setting_set('sbv_key_created_at', (string) time());
            $generated = true;
        }
        if (!rx_sbv_setting_set('sbv_status', 'on')) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        return ['ok' => true, 'generated' => $generated];
    }
}

if (!function_exists('rx_sbv_disable')) {
    function rx_sbv_disable(): bool
    {
        return rx_sbv_setting_set('sbv_status', 'off');
    }
}

if (!function_exists('rx_sbv_regenerate_key')) {
    function rx_sbv_regenerate_key(): bool
    {
        if (!rx_sbv_setting_set('sbv_api_key', rx_sbv_generate_key())) {
            return false;
        }
        rx_sbv_setting_set('sbv_key_created_at', (string) time());
        rx_sbv_setting_set('sbv_last_auth_at', '');
        return true;
    }
}

if (!function_exists('rx_sbv_webhook_url')) {
    function rx_sbv_webhook_url(): string
    {
        $host = isset($GLOBALS['domainhosts']) ? trim((string) $GLOBALS['domainhosts']) : '';
        $host = rtrim((string) preg_replace('#^https?://#i', '', $host), '/');
        return $host === '' ? '' : 'https://' . $host . '/payment/forapp.php';
    }
}

if (!function_exists('rx_sbv_clock')) {
    function rx_sbv_clock(int $ts, bool $withDate = false): string
    {
        try {
            $dt = new DateTime('@' . $ts);
            $dt->setTimezone(new DateTimeZone('Asia/Tehran'));
            if ($withDate && function_exists('jdate')) {
                return jdate('Y/m/d H:i', $ts);
            }
            return $dt->format($withDate ? 'Y/m/d H:i' : 'H:i');
        } catch (Throwable $e) {
            return date($withDate ? 'Y/m/d H:i' : 'H:i', $ts);
        }
    }
}

if (!function_exists('rx_sbv_money')) {
    function rx_sbv_money($value): string
    {
        return number_format((int) $value);
    }
}

if (!function_exists('rx_sbv_connection_status')) {
    function rx_sbv_connection_status(): array
    {
        $last = (int) rx_sbv_setting_get('sbv_last_auth_at', '0');
        if ($last <= 0) {
            return ['code' => 'never', 'label' => 'هنوز هیچ درخواست معتبری دریافت نشده', 'last_at' => 0];
        }
        if (time() - $last <= 86400) {
            return ['code' => 'connected', 'label' => 'متصل', 'last_at' => $last];
        }
        return ['code' => 'stale', 'label' => 'بیش از ۲۴ ساعت بدون درخواست', 'last_at' => $last];
    }
}

if (!function_exists('rx_sbv_audit')) {
    function rx_sbv_audit(?string $orderId, ?int $depositId, ?string $actor, string $action, array $data = []): void
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return;
        }
        try {
            $pdo->prepare(
                "INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $orderId,
                $depositId,
                $actor,
                $action,
                isset($data['principal_toman']) ? (int) $data['principal_toman'] : null,
                isset($data['fee_toman']) ? (int) $data['fee_toman'] : null,
                isset($data['expected_rial']) ? (int) $data['expected_rial'] : null,
                isset($data['received_rial']) ? (int) $data['received_rial'] : null,
                isset($data['credit_toman']) ? (int) $data['credit_toman'] : null,
                isset($data['outcome']) ? (string) $data['outcome'] : null,
                isset($data['reason']) ? (string) $data['reason'] : null,
                time(),
            ]);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_AUDIT_FAILED', $e->getMessage(), ['id_order' => $orderId, 'action' => $action]);
        }
    }
}

if (!function_exists('rx_sbv_get')) {
    function rx_sbv_get(string $orderId, bool $lock = false): ?array
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null || $orderId === '' || !rx_sbv_has_schema()) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1" . ($lock ? " FOR UPDATE" : ""));
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('rx_sbv_is_verify_order')) {
    function rx_sbv_is_verify_order(string $orderId): bool
    {
        return rx_sbv_get($orderId) !== null;
    }
}

if (!function_exists('rx_sbv_deposit')) {
    function rx_sbv_deposit(int $depositId, bool $lock = false): ?array
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null || $depositId <= 0 || !rx_sbv_has_schema()) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1" . ($lock ? " FOR UPDATE" : ""));
            $stmt->execute([$depositId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('rx_sbv_payment_report')) {
    function rx_sbv_payment_report(string $orderId): ?array
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('rx_sbv_parse_principal')) {
    function rx_sbv_parse_principal($value): int
    {
        if (is_int($value)) {
            return $value;
        }
        $s = trim((string) $value);
        if ($s === '' || !ctype_digit($s) || strlen($s) > 15) {
            return 0;
        }
        return (int) $s;
    }
}

if (!function_exists('rx_sbv_allocate')) {
    function rx_sbv_allocate(PDO $pdo, string $orderId, int $principal, int $now): ?array
    {
        $scope = RX_SBV_SCOPE;
        $lo = ($principal + RX_SBV_FEE_MIN) * 10;
        $hi = ($principal + RX_SBV_FEE_MAX) * 10;
        $taken = [];
        $stmt = $pdo->prepare("SELECT payable_rial FROM card_sms_amount WHERE scope = ? AND payable_rial BETWEEN ? AND ? AND (reusable_after IS NULL OR reusable_after > ?)");
        $stmt->execute([$scope, $lo, $hi, $now]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rial) {
            $taken[(string) $rial] = true;
        }
        $fees = [];
        for ($fee = RX_SBV_FEE_MIN; $fee <= RX_SBV_FEE_MAX; $fee++) {
            if (!isset($taken[(string) (($principal + $fee) * 10)])) {
                $fees[] = $fee;
            }
        }
        for ($i = count($fees) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $fees[$i];
            $fees[$i] = $fees[$j];
            $fees[$j] = $tmp;
        }
        $insert = $pdo->prepare("INSERT INTO card_sms_amount (scope, payable_rial, id_order, reserved_at, reusable_after) VALUES (?, ?, ?, ?, NULL)");
        $reclaim = $pdo->prepare("UPDATE card_sms_amount SET id_order = ?, reserved_at = ?, reusable_after = NULL WHERE scope = ? AND payable_rial = ? AND reusable_after IS NOT NULL AND reusable_after <= ?");
        foreach ($fees as $fee) {
            $rial = ($principal + $fee) * 10;
            try {
                $insert->execute([$scope, $rial, $orderId, $now]);
                if ($insert->rowCount() === 1) {
                    return ['fee' => $fee, 'payable_rial' => $rial, 'scope' => $scope];
                }
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
            $reclaim->execute([$orderId, $now, $scope, $rial, $now]);
            if ($reclaim->rowCount() === 1) {
                return ['fee' => $fee, 'payable_rial' => $rial, 'scope' => $scope];
            }
        }
        return null;
    }
}

if (!function_exists('rx_sbv_release_amount')) {
    function rx_sbv_release_amount(PDO $pdo, array $csp, bool $immediate = false): void
    {
        $now = time();
        $after = $immediate ? $now : max($now, (int) $csp['expires_at']) + RX_SBV_REUSE_COOLDOWN;
        $pdo->prepare("UPDATE card_sms_amount SET reusable_after = ? WHERE scope = ? AND payable_rial = ? AND id_order = ? AND (reusable_after IS NULL OR reusable_after < ?)")
            ->execute([$after, (string) $csp['scope'], (int) $csp['payable_rial'], (string) $csp['id_order'], $after]);
    }
}

if (!function_exists('rx_sbv_prepare')) {
    function rx_sbv_prepare(string $orderId, $userId, $principalRaw): array
    {
        $principal = rx_sbv_parse_principal($principalRaw);
        $userId = trim((string) $userId);
        if ($orderId === '' || strlen($orderId) > 191 || $userId === '' || $principal <= 0) {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        if (!rx_sbv_ensure_schema()) {
            return ['ok' => false, 'reason' => 'schema_unavailable'];
        }
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        rx_sbv_sweep();
        if (!rx_sbv_user_lock($userId)) {
            return ['ok' => false, 'reason' => 'busy'];
        }
        try {
            return rx_sbv_prepare_locked($pdo, $orderId, $userId, $principal);
        } finally {
            rx_sbv_user_unlock($userId);
        }
    }
}

if (!function_exists('rx_sbv_prepare_locked')) {
    function rx_sbv_prepare_locked(PDO $pdo, string $orderId, string $userId, int $principal): array
    {
        $active = rx_sbv_active_payment($userId);
        if ($active !== null) {
            return ['ok' => false, 'reason' => 'active_exists', 'active' => $active];
        }
        $now = time();
        try {
            $alloc = rx_sbv_allocate($pdo, $orderId, $principal, $now);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_ALLOCATE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        if ($alloc === null) {
            rx_sbv_log('SBV_AMOUNT_RANGE_EXHAUSTED', 'No free identification fee for principal', ['principal' => $principal]);
            return ['ok' => false, 'reason' => 'exhausted'];
        }
        $lifetime = rx_sbv_lifetime_minutes() * 60;
        $fee = (int) $alloc['fee'];
        $row = [
            'id_order' => $orderId,
            'id_user' => $userId,
            'scope' => (string) $alloc['scope'],
            'principal_toman' => $principal,
            'fee_toman' => $fee,
            'payable_toman' => $principal + $fee,
            'payable_rial' => (int) $alloc['payable_rial'],
            'lifetime_sec' => $lifetime,
            'receipt_delay_sec' => RX_SBV_RECEIPT_DELAY,
            'created_at' => $now,
            'receipt_after' => $now + RX_SBV_RECEIPT_DELAY,
            'expires_at' => $now + $lifetime,
            'state' => 'open',
            'updated_at' => $now,
        ];
        try {
            $pdo->prepare(
                "INSERT INTO card_sms_payment (id_order, id_user, scope, principal_toman, fee_toman, payable_toman, payable_rial, lifetime_sec, receipt_delay_sec, created_at, receipt_after, expires_at, state, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute(array_values($row));
        } catch (Throwable $e) {
            rx_sbv_log('SBV_PREPARE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            try {
                $pdo->prepare("UPDATE card_sms_amount SET reusable_after = ? WHERE scope = ? AND payable_rial = ? AND id_order = ?")
                    ->execute([$now, (string) $alloc['scope'], (int) $alloc['payable_rial'], $orderId]);
            } catch (Throwable $e2) {
            }
            return ['ok' => false, 'reason' => 'db_error'];
        }
        return ['ok' => true, 'payment' => $row];
    }
}

if (!function_exists('rx_sbv_abandon')) {
    function rx_sbv_abandon(string $orderId): void
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null || $orderId === '' || !rx_sbv_has_schema()) {
            return;
        }
        try {
            $csp = rx_sbv_get($orderId);
            if ($csp === null) {
                return;
            }
            $pdo->prepare("UPDATE card_sms_payment SET state = 'void', resolution = 'not_created', updated_at = ? WHERE id_order = ? AND state = 'open' AND deposit_id IS NULL")
                ->execute([time(), $orderId]);
            rx_sbv_release_amount($pdo, $csp, true);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_ABANDON_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
    }
}

if (!function_exists('rx_sbv_find_reusable')) {
    function rx_sbv_find_reusable($userId, string $invoiceKey, $principalRaw): ?array
    {
        $principal = rx_sbv_parse_principal($principalRaw);
        $pdo = rx_sbv_pdo();
        if ($pdo === null || $principal <= 0 || !rx_sbv_has_schema()) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT csp.*, pr.source AS pr_source FROM card_sms_payment csp
                   JOIN Payment_report pr ON pr.id_order = csp.id_order
                  WHERE csp.id_user = ? AND csp.state = 'open' AND csp.deposit_id IS NULL AND csp.expires_at > ?
                    AND csp.principal_toman = ? AND pr.id_invoice = ? AND pr.payment_Status = 'Unpaid'
                    AND (pr.dec_not_confirmed IS NULL OR pr.dec_not_confirmed = '')
                    AND COALESCE(pr.direct_payment_done, 0) = 0
                    AND pr.Payment_Method = 'cart to cart'
                  ORDER BY csp.id DESC LIMIT 1"
            );
            $stmt->execute([trim((string) $userId), time() + 60, $principal, $invoiceKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            rx_sbv_log('SBV_REUSE_LOOKUP_FAILED', $e->getMessage(), ['id_user' => (string) $userId]);
            return null;
        }
    }
}

if (!function_exists('rx_sbv_user_lock')) {
    function rx_sbv_user_lock(string $userId, int $timeout = 5): bool
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT GET_LOCK(CONCAT('sbvu_', SHA1(CONCAT(DATABASE(), '|', ?))), ?)");
            $stmt->execute([$userId, $timeout]);
            return (int) $stmt->fetchColumn() === 1;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('rx_sbv_user_unlock')) {
    function rx_sbv_user_unlock(string $userId): void
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return;
        }
        try {
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(CONCAT('sbvu_', SHA1(CONCAT(DATABASE(), '|', ?))))");
            $stmt->execute([$userId]);
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('rx_sbv_active_payment')) {
    function rx_sbv_active_payment($userId): ?array
    {
        $pdo = rx_sbv_pdo();
        $userId = trim((string) $userId);
        if ($pdo === null || $userId === '' || !rx_sbv_has_schema()) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT csp.*, pr.id AS pr_id, pr.payment_Status AS pr_status, pr.dec_not_confirmed AS pr_dec, pr.source AS pr_source,
                        pr.id_invoice AS pr_invoice, pr.report_message_id AS pr_report_msg, pr.private_receipt_targets AS pr_private,
                        pr.direct_payment_done AS pr_done
                   FROM card_sms_payment csp
                   LEFT JOIN Payment_report pr ON pr.id_order = csp.id_order
                  WHERE csp.id_user = ? AND csp.state IN ('open', 'matched', 'review')
                  ORDER BY csp.id DESC LIMIT 20"
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_ACTIVE_LOOKUP_FAILED', $e->getMessage(), ['id_user' => $userId]);
            return null;
        }
        $now = time();
        foreach ($rows as $row) {
            if ($row['pr_id'] === null) {
                if ((int) $row['created_at'] > $now - 120) {
                    $row['cancellable'] = false;
                    $row['resumable'] = false;
                    return $row;
                }
                continue;
            }
            $status = (string) $row['pr_status'];
            if ((int) ($row['pr_done'] ?? 0) === 1 || in_array($status, ['paid', 'reject', 'expire', 'cancelled'], true)) {
                continue;
            }
            $state = (string) $row['state'];
            $isActive = false;
            if ($state === 'matched' || $state === 'review') {
                $isActive = true;
            } elseif ($status === 'Unpaid') {
                $isActive = $now < (int) $row['expires_at'];
            } elseif (in_array($status, ['pending', 'waiting', 'processing', 'reconciling'], true)) {
                $isActive = true;
            }
            if (!$isActive) {
                continue;
            }
            $delivered = (int) ($row['pr_report_msg'] ?? 0) > 0 || trim((string) ($row['pr_private'] ?? '')) !== '';
            $row['resumable'] = $state === 'open' && $status === 'Unpaid' && $now < (int) $row['expires_at'];
            $row['cancellable'] = $state === 'open' && $row['deposit_id'] === null && $status === 'Unpaid'
                && trim((string) ($row['pr_dec'] ?? '')) === '' && !$delivered;
            return $row;
        }
        return null;
    }
}

if (!function_exists('rx_sbv_pending_summary')) {
    function rx_sbv_pending_summary(array $active): array
    {
        $status = (string) ($active['pr_status'] ?? '');
        $state = (string) ($active['state'] ?? '');
        if ($state === 'matched' || $state === 'review' || in_array($status, ['processing', 'reconciling'], true)) {
            $label = 'واریز شناسایی شده و در حال تأیید است';
        } elseif ($status === 'waiting' || $status === 'pending') {
            $label = 'رسید ارسال شده و در انتظار بررسی است';
        } else {
            $label = 'در انتظار واریز';
        }
        return [
            'order_id' => (string) $active['id_order'],
            'principal_toman' => (int) $active['principal_toman'],
            'fee_toman' => (int) $active['fee_toman'],
            'payable_toman' => (int) $active['payable_toman'],
            'payable_rial' => (int) $active['payable_rial'],
            'expires_at' => (int) $active['expires_at'],
            'receipt_after' => (int) $active['receipt_after'],
            'server_now' => time(),
            'status' => $status,
            'status_label' => $label,
            'resumable' => !empty($active['resumable']),
            'cancellable' => !empty($active['cancellable']),
        ];
    }
}

if (!function_exists('rx_sbv_pending_message')) {
    function rx_sbv_pending_message(): string
    {
        return 'شما یک پرداخت ناتمام دارید. برای ایجاد پرداخت جدید، ابتدا پرداخت قبلی را تکمیل یا لغو کنید.';
    }
}

if (!function_exists('rx_sbv_has_live_payment')) {
    function rx_sbv_has_live_payment($userId): bool
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null || !rx_sbv_has_schema()) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM card_sms_payment WHERE id_user = ? AND state = 'open' AND deposit_id IS NULL AND expires_at > ?");
            $stmt->execute([trim((string) $userId), time()]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('rx_sbv_cancel_guard')) {
    function rx_sbv_cancel_guard(PDO $pdo, string $orderId, string $reason, string $targetStatus): ?string
    {
        if (!rx_sbv_has_schema()) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$orderId]);
        $csp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($csp === false) {
            return null;
        }
        $now = time();
        if ((string) $csp['state'] !== 'open' || $csp['deposit_id'] !== null) {
            return 'verify_locked';
        }
        if (!in_array($reason, ['user_reset', 'user_cancel'], true) && $now < (int) $csp['expires_at'] + ($reason === 'timeout' ? RX_SBV_EXPIRE_GRACE : 0)) {
            return 'verify_window_active';
        }
        $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = ?, resolved_at = ?, updated_at = ? WHERE id = ? AND state = 'open'")
            ->execute([$targetStatus === 'expire' ? 'expired' : 'cancelled', $now, $now, (int) $csp['id']]);
        rx_sbv_release_amount($pdo, $csp, false);
        return null;
    }
}

if (!function_exists('rx_sbv_public_view')) {
    function rx_sbv_public_view(string $orderId, ?array $csp = null): ?array
    {
        $csp = $csp ?? rx_sbv_get($orderId);
        if ($csp === null) {
            return null;
        }
        $now = time();
        $created = (int) $csp['created_at'];
        $receiptAfter = (int) $csp['receipt_after'];
        $expires = (int) $csp['expires_at'];
        $state = (string) $csp['state'];
        $live = in_array($state, ['open', 'matched'], true);
        $receiptOpen = $state === 'open' && $now >= $receiptAfter && $now < $expires;
        if ($state === 'matched' || $state === 'review') {
            $verifyStatus = 'detected';
        } elseif (in_array($state, ['approved', 'credited'], true)) {
            $verifyStatus = 'approved';
        } elseif (!$live) {
            $verifyStatus = 'closed';
        } elseif ($now >= $expires) {
            $verifyStatus = 'expired';
        } else {
            $verifyStatus = 'waiting';
        }
        return [
            'principal_toman' => (int) $csp['principal_toman'],
            'fee_toman' => (int) $csp['fee_toman'],
            'payable_toman' => (int) $csp['payable_toman'],
            'payable_rial' => (int) $csp['payable_rial'],
            'created_at' => $created,
            'receipt_after' => $receiptAfter,
            'expires_at' => $expires,
            'server_now' => $now,
            'remaining_sec' => max(0, $expires - $now),
            'receipt_wait_sec' => max(0, $receiptAfter - $now),
            'receipt_available' => $receiptOpen,
            'state' => $state,
            'verify_status' => $verifyStatus,
            'receipt_after_clock' => rx_sbv_clock($receiptAfter),
            'expires_at_clock' => rx_sbv_clock($expires),
        ];
    }
}

if (!function_exists('rx_sbv_expired_from_values')) {
    function rx_sbv_expired_from_values(string $state, string $status, string $resolution, int $expiresAt, int $directDone = 0): bool
    {
        if ($status === 'paid' || $directDone === 1 || in_array($state, ['approved', 'credited'], true)) {
            return false;
        }
        if (in_array($status, ['reject', 'cancelled'], true)) {
            return false;
        }
        if ($state === 'closed' && in_array($resolution, ['rejected', 'rejected_blocked', 'cancelled', 'void', 'admin_credit', 'refunded'], true)) {
            return false;
        }
        if ($status === 'expire') {
            return true;
        }
        if ($state === 'closed') {
            return $resolution === 'expired';
        }
        if (in_array($state, ['open', 'review', 'matched'], true) && $expiresAt > 0 && $expiresAt < time()) {
            return in_array($status, ['', 'Unpaid', 'waiting', 'pending'], true);
        }
        return false;
    }
}

if (!function_exists('rx_sbv_payment_is_expired')) {
    function rx_sbv_payment_is_expired(array $csp, ?array $report = null): bool
    {
        $status = is_array($report) ? (string) ($report['payment_Status'] ?? '') : (string) ($csp['payment_Status'] ?? '');
        $directDone = (int) ($csp['direct_payment_done'] ?? (is_array($report) ? ($report['direct_payment_done'] ?? 0) : 0));
        return rx_sbv_expired_from_values(
            (string) ($csp['state'] ?? ''),
            $status,
            (string) ($csp['resolution'] ?? ''),
            (int) ($csp['expires_at'] ?? 0),
            $directDone
        );
    }
}

if (!function_exists('rx_sbv_receipt_gate')) {
    function rx_sbv_receipt_gate(string $orderId): array
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null) {
            return ['ok' => true, 'verify' => false];
        }
        $now = time();
        $state = (string) $csp['state'];
        if (in_array($state, ['approved', 'credited'], true)) {
            return ['ok' => false, 'verify' => true, 'code' => 'already_paid', 'message' => '✅ این پرداخت قبلاً تأیید شده است.'];
        }
        if ($state === 'matched' || $state === 'review') {
            return ['ok' => false, 'verify' => true, 'code' => 'verifying', 'message' => '🔎 واریز شما شناسایی شده و در حال تأیید است؛ نیازی به ارسال رسید نیست.'];
        }
        if ($state !== 'open') {
            return ['ok' => false, 'verify' => true, 'code' => 'closed', 'message' => '❌ این پرداخت دیگر فعال نیست و امکان ارسال رسید برای آن وجود ندارد.'];
        }
        if ($now >= (int) $csp['expires_at']) {
            return ['ok' => false, 'verify' => true, 'code' => 'expired', 'message' => '⏳ مهلت این پرداخت به پایان رسیده است و امکان ارسال رسید وجود ندارد.'];
        }
        if ($now < (int) $csp['receipt_after']) {
            $wait = (int) $csp['receipt_after'] - $now;
            return [
                'ok' => false,
                'verify' => true,
                'code' => 'too_early',
                'available_at' => (int) $csp['receipt_after'],
                'wait_sec' => $wait,
                'message' => '⏳ تأیید خودکار واریز در حال انجام است. ارسال رسید از ساعت ' . rx_sbv_clock((int) $csp['receipt_after']) . ' (حدود ' . (int) ceil($wait / 60) . ' دقیقه دیگر) امکان‌پذیر است.',
            ];
        }
        return ['ok' => true, 'verify' => true];
    }
}

if (!function_exists('rx_sbv_payment_block')) {
    function rx_sbv_payment_block(array $view): string
    {
        return "\n\n🔐 <b>تأیید خودکار واریز فعال است</b>\n"
            . "<blockquote>💰 مبلغ سفارش/شارژ: " . rx_sbv_money($view['principal_toman']) . " تومان\n"
            . "🏷 کارمزد شناسایی: " . rx_sbv_money($view['fee_toman']) . " تومان\n"
            . "✅ مبلغ دقیق قابل واریز: <b>" . rx_sbv_money($view['payable_toman']) . " تومان</b>\n"
            . "🔢 معادل ریالی: <code>" . (int) $view['payable_rial'] . "</code> ریال</blockquote>\n"
            . "⚠️ دقیقاً همین مبلغ را یکجا واریز کنید؛ مبلغ کمتر، بیشتر یا چند واریز جداگانه به‌صورت خودکار تأیید نمی‌شود.\n"
            . "ℹ️ کارمزد شناسایی جزو شارژ کیف پول یا مبلغ سفارش نیست و بازگردانده نمی‌شود.\n"
            . "⏳ مهلت پرداخت تا ساعت <b>" . $view['expires_at_clock'] . "</b>\n"
            . "🤖 پس از واریز، پرداخت به‌صورت خودکار بررسی و تأیید می‌شود.\n"
            . "📤 اگر تا ساعت " . $view['receipt_after_clock'] . " تأیید نشد، می‌توانید رسید را ارسال کنید.";
    }
}

if (!function_exists('rx_sbv_bot_payment_view')) {
    function rx_sbv_bot_payment_view(string $orderId): ?array
    {
        $view = rx_sbv_public_view($orderId);
        if ($view === null) {
            return null;
        }
        return [
            'view' => $view,
            'valueprice' => rx_sbv_money($view['payable_toman']),
            'price_copy' => (string) $view['payable_rial'],
            'block' => rx_sbv_payment_block($view),
            'receipt_label' => '📤 ارسال رسید (از ساعت ' . $view['receipt_after_clock'] . ')',
            'copy_label' => 'کپی مبلغ به ریال',
        ];
    }
}

if (!function_exists('rx_sbv_bool')) {
    function rx_sbv_bool($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['true', '1', 'yes'], true)) {
                return true;
            }
            if (in_array($v, ['false', '0', 'no'], true)) {
                return false;
            }
        }
        return null;
    }
}

if (!function_exists('rx_sbv_int_amount')) {
    function rx_sbv_int_amount($value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (is_float($value)) {
            if (!is_finite($value) || $value < 0 || $value > 9.0E15 || floor($value) !== $value) {
                return null;
            }
            return (int) $value;
        }
        if (is_string($value)) {
            $s = trim($value);
            if ($s === '' || strlen($s) > 15 || !ctype_digit($s)) {
                return null;
            }
            return (int) $s;
        }
        return null;
    }
}

if (!function_exists('rx_sbv_unit_factor')) {
    function rx_sbv_unit_factor($unit): ?int
    {
        if (!is_string($unit)) {
            return null;
        }
        $u = mb_strtolower(trim($unit), 'UTF-8');
        if (in_array($u, ['rial', 'rials', 'irr', 'ریال'], true)) {
            return 1;
        }
        if (in_array($u, ['toman', 'tomans', 'irt', 'تومان'], true)) {
            return 10;
        }
        return null;
    }
}

if (!function_exists('rx_sbv_str')) {
    function rx_sbv_str($value, int $max): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
    }
}

if (!function_exists('rx_sbv_header')) {
    function rx_sbv_header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$key])) {
            return trim((string) $_SERVER[$key]);
        }
        if (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $k => $v) {
                if (strcasecmp((string) $k, $name) === 0) {
                    return trim((string) $v);
                }
            }
        }
        return '';
    }
}

if (!function_exists('rx_sbv_ingest')) {
    function rx_sbv_ingest(string $providedKey, string $idempotencyKey, string $raw): array
    {
        $storedKey = rx_sbv_api_key();
        if ($storedKey === '') {
            return [503, ['ok' => false, 'error' => 'not_configured']];
        }
        if ($providedKey === '' || !hash_equals($storedKey, $providedKey)) {
            return [401, ['ok' => false, 'error' => 'unauthorized']];
        }
        $now = time();
        rx_sbv_setting_set('sbv_last_auth_at', (string) $now);
        if (strlen($raw) > RX_SBV_MAX_BODY) {
            return [413, ['ok' => false, 'error' => 'payload_too_large']];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || array_values($data) === $data) {
            return [400, ['ok' => false, 'error' => 'invalid_json']];
        }
        $eventId = rx_sbv_str($data['id'] ?? null, 191);
        if ($eventId === null || strlen($eventId) > 191) {
            return [422, ['ok' => false, 'error' => 'invalid_id']];
        }
        if ($idempotencyKey !== '' && !hash_equals($eventId, $idempotencyKey)) {
            return [422, ['ok' => false, 'error' => 'idempotency_key_mismatch']];
        }
        $isTest = rx_sbv_bool($data['test'] ?? null);
        $isDeposit = rx_sbv_bool($data['is_deposit'] ?? null);
        if ($isTest === null) {
            return [422, ['ok' => false, 'error' => 'invalid_test_flag']];
        }
        if ($isDeposit === null) {
            return [422, ['ok' => false, 'error' => 'invalid_is_deposit']];
        }
        $amountRaw = $data['amount'] ?? null;
        $amount = rx_sbv_int_amount($amountRaw);
        $factor = rx_sbv_unit_factor($data['unit'] ?? null);
        $amountRial = ($amount !== null && $factor !== null) ? $amount * $factor : null;
        if ($isDeposit && !$isTest) {
            if ($factor === null) {
                return [422, ['ok' => false, 'error' => 'unsupported_unit']];
            }
            if ($amount === null || $amount <= 0) {
                return [422, ['ok' => false, 'error' => 'invalid_amount']];
            }
        }
        if (!rx_sbv_ensure_schema()) {
            return [503, ['ok' => false, 'error' => 'storage_unavailable']];
        }
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return [503, ['ok' => false, 'error' => 'storage_unavailable']];
        }
        $msRaw = $data['received_at_ms'] ?? null;
        $receivedMs = rx_sbv_int_amount($msRaw);
        $body = rx_sbv_str($data['body'] ?? null, 4000);
        $sender = rx_sbv_str($data['sender'] ?? null, 191);
        $receivedAtRaw = rx_sbv_str($data['received_at'] ?? null, 64);
        $fingerprint = null;
        if ($body !== null && ($receivedMs !== null || $receivedAtRaw !== null)) {
            $fingerprint = hash('sha256', json_encode([$isTest ? 1 : 0, (string) $sender, $body, $receivedMs !== null ? (string) $receivedMs : (string) $receivedAtRaw], JSON_UNESCAPED_UNICODE));
        }
        try {
            $identityLocks = rx_sbv_identity_lock($eventId, $fingerprint);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_IDENTITY_LOCK_FAILED', $e->getMessage());
            return [503, ['ok' => false, 'error' => 'storage_unavailable']];
        }
        try {
        if (rx_sbv_deleted_identity_exists($eventId, $fingerprint)) {
            return [200, ['ok' => true, 'status' => 'deleted']];
        }
        if ($isTest) {
            $state = 'test';
            $note = 'test_event';
        } elseif (!$isDeposit) {
            $state = 'ignored';
            $note = 'not_deposit';
        } else {
            $state = 'pending';
            $note = null;
        }
        $attempt = rx_sbv_int_amount($data['attempt'] ?? null);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        try {
            $pdo->prepare(
                "INSERT INTO card_sms_deposit (event_id, fingerprint, is_test, is_deposit, amount_raw, unit, amount_rial, sender, bank, code, body, device, attempt, received_at_raw, received_at_ms, stored_at, state, note, updated_at, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $eventId,
                $fingerprint,
                $isTest ? 1 : 0,
                $isDeposit ? 1 : 0,
                rx_sbv_str($amountRaw, 64),
                rx_sbv_str($data['unit'] ?? null, 16),
                $amountRial,
                $sender,
                rx_sbv_str($data['bank'] ?? null, 191),
                rx_sbv_str($data['code'] ?? null, 191),
                $body,
                rx_sbv_str($data['device'] ?? null, 191),
                $attempt,
                $receivedAtRaw,
                $receivedMs,
                $now,
                $state,
                $note,
                $now,
                $payload === false ? null : $payload,
            ]);
            $id = (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                $dup = $pdo->prepare("SELECT id FROM card_sms_deposit WHERE event_id = ? LIMIT 1");
                $dup->execute([$eventId]);
                $dupId = $dup->fetchColumn();
                if ($dupId !== false) {
                    return [200, ['ok' => true, 'status' => 'duplicate', 'id' => (int) $dupId]];
                }
                if ($fingerprint !== null) {
                    $dup = $pdo->prepare("SELECT id FROM card_sms_deposit WHERE fingerprint = ? LIMIT 1");
                    $dup->execute([$fingerprint]);
                    $dupId = $dup->fetchColumn();
                    if ($dupId !== false) {
                        rx_sbv_log('SBV_DUPLICATE_SMS', 'Same SMS received under a different event id', ['deposit_id' => (int) $dupId]);
                        return [200, ['ok' => true, 'status' => 'duplicate', 'id' => (int) $dupId]];
                    }
                }
            }
            rx_sbv_log('SBV_INGEST_FAILED', $e->getMessage());
            return [500, ['ok' => false, 'error' => 'storage_failed']];
        } catch (Throwable $e) {
            rx_sbv_log('SBV_INGEST_FAILED', $e->getMessage());
            return [500, ['ok' => false, 'error' => 'storage_failed']];
        }
        return [200, ['ok' => true, 'status' => 'stored', 'id' => $id, 'state' => $state]];
        } catch (Throwable $e) {
            rx_sbv_log('SBV_IDENTITY_CHECK_FAILED', $e->getMessage());
            return [503, ['ok' => false, 'error' => 'storage_unavailable']];
        } finally {
            rx_sbv_identity_unlock($identityLocks);
        }
    }
}

if (!function_exists('rx_sbv_tx')) {
    function rx_sbv_tx(callable $fn)
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            throw new RuntimeException('pdo_unavailable');
        }
        if ($pdo->inTransaction()) {
            throw new RuntimeException('nested_transaction');
        }
        $pdo->beginTransaction();
        try {
            $out = $fn($pdo);
            $pdo->commit();
            return $out;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Throwable $re) {
                }
            }
            throw $e;
        }
    }
}

if (!function_exists('rx_sbv_match_deposit')) {
    function rx_sbv_match_deposit(int $depositId): string
    {
        try {
            $result = rx_sbv_tx(function (PDO $pdo) use ($depositId) {
                $now = time();
                $stmt = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1 FOR UPDATE");
                $stmt->execute([$depositId]);
                $dep = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($dep === false || (string) $dep['state'] !== 'pending') {
                    return 'skip';
                }
                $mark = function (string $state, ?string $note, ?string $hint = null) use ($pdo, $depositId, $now) {
                    $pdo->prepare("UPDATE card_sms_deposit SET state = ?, note = ?, id_order = ?, updated_at = ? WHERE id = ?")
                        ->execute([$state, $note, $hint, $now, $depositId]);
                    return $state;
                };
                if ((int) $dep['is_test'] === 1 || (int) $dep['is_deposit'] !== 1) {
                    return $mark('ignored', 'not_eligible', null);
                }
                $amount = $dep['amount_rial'] === null ? 0 : (int) $dep['amount_rial'];
                if ($amount <= 0) {
                    return $mark('unmatched', 'invalid_amount', null);
                }
                $storedAt = (int) $dep['stored_at'];
                $cand = $pdo->prepare(
                    "SELECT * FROM card_sms_payment WHERE payable_rial = ? AND state = 'open' AND deposit_id IS NULL AND created_at <= ? AND expires_at >= ? FOR UPDATE"
                );
                $cand->execute([$amount, $storedAt, $storedAt]);
                $rows = $cand->fetchAll(PDO::FETCH_ASSOC);
                if (count($rows) === 0) {
                    $late = $pdo->prepare("SELECT id_order FROM card_sms_payment WHERE payable_rial = ? AND created_at <= ? AND expires_at >= ? ORDER BY id DESC LIMIT 1");
                    $late->execute([$amount, $storedAt, $storedAt - RX_SBV_REUSE_COOLDOWN]);
                    $lateOrder = $late->fetchColumn();
                    if ($lateOrder !== false) {
                        return $mark('late', 'exact_amount_inactive_payment:' . (string) $lateOrder, null);
                    }
                    return $mark('unmatched', 'no_matching_payment', null);
                }
                if (count($rows) > 1) {
                    return $mark('ambiguous', 'multiple_candidates', null);
                }
                $csp = $rows[0];
                $ms = $dep['received_at_ms'] === null ? null : (int) $dep['received_at_ms'];
                if ($ms !== null && $ms > 0 && intdiv($ms, 1000) < (int) $csp['created_at'] - RX_SBV_DEVICE_SKEW) {
                    return $mark('unmatched', 'sms_predates_payment', null);
                }
                $pr = $pdo->prepare("SELECT payment_Status, direct_payment_done, source FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1");
                $pr->execute([(string) $csp['id_order']]);
                $prRow = $pr->fetch(PDO::FETCH_ASSOC);
                if ($prRow === false || (int) ($prRow['direct_payment_done'] ?? 0) === 1 || !in_array((string) $prRow['payment_Status'], ['Unpaid', 'waiting', 'pending'], true)) {
                    return $mark('unmatched', 'order_not_payable', null);
                }
                $upd = $pdo->prepare("UPDATE card_sms_payment SET state = 'matched', deposit_id = ?, received_rial = ?, updated_at = ? WHERE id = ? AND state = 'open' AND deposit_id IS NULL");
                $upd->execute([$depositId, $amount, $now, (int) $csp['id']]);
                if ($upd->rowCount() !== 1) {
                    return $mark('unmatched', 'candidate_changed', null);
                }
                $mark('matched', null, (string) $csp['id_order']);
                return 'matched';
            });
            return $result;
        } catch (Throwable $e) {
            rx_sbv_log('SBV_MATCH_FAILED', $e->getMessage(), ['deposit_id' => $depositId]);
            return 'error';
        }
    }
}

if (!function_exists('rx_sbv_cashback')) {
    function rx_sbv_cashback(array $report): int
    {
        if (!function_exists('rx_cashback_credit_once') || !function_exists('select')) {
            return 0;
        }
        try {
            $user = select('user', '*', 'id', $report['id_user'], 'select', ['cache' => false]);
            if (!is_array($user)) {
                return 0;
            }
            $row = select('PaySetting', 'ValuePay', 'NamePay', 'chashbackcart', 'select');
            $pct = is_array($row) ? (string) ($row['ValuePay'] ?? '0') : '0';
            if ($pct === '0' || $pct === '' || !is_numeric($pct)) {
                return 0;
            }
            $eligible = !function_exists('rx_cashbackEligibleForKey')
                || rx_cashbackEligibleForKey('chashbackcart', $user['register'] ?? null, $report['id_invoice'] ?? null, $user['id'] ?? null, $report['id_order'] ?? null);
            if (!$eligible) {
                return 0;
            }
            $amount = (int) floor(((int) $report['price'] * (int) $pct) / 100);
            if ($amount <= 0) {
                return 0;
            }
            if (rx_cashback_credit_once($report['id_order'], $user['id'], $amount, 'chashbackcart', 'هدیه بازگشت وجه کارت به کارت (تأیید با بررسی)') !== 'credited') {
                return 0;
            }
            if (function_exists('sendmessage')) {
                sendmessage($user['id'], "🎁 کاربر عزیز مبلغ " . (function_exists('rxFormatToman') ? rxFormatToman($amount) : number_format($amount)) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.", null, 'HTML');
            }
            return $amount;
        } catch (Throwable $e) {
            rx_sbv_log('SBV_CASHBACK_FAILED', $e->getMessage(), ['id_order' => $report['id_order'] ?? null]);
            return 0;
        }
    }
}

if (!function_exists('rx_sbv_report')) {
    function rx_sbv_report(string $text, $keyboard = null, array $options = []): array
    {
        if (!function_exists('select') || !function_exists('telegram')) {
            return ['state' => 'failed', 'ok' => false, 'error' => 'telegram_unavailable'];
        }
        try {
            $setting = select('setting', '*');
            $channel = is_array($setting) ? trim((string) ($setting['Channel_Report'] ?? '')) : '';
            if ($channel === '') {
                return ['state' => 'failed', 'ok' => false, 'error' => 'report_channel_missing'];
            }
            $topic = select('topicid', 'idreport', 'report', 'paymentreport', 'select');
            $payload = [
                'chat_id' => $channel,
                'message_thread_id' => is_array($topic) ? ($topic['idreport'] ?? null) : null,
                'text' => $text,
                'parse_mode' => 'HTML',
            ];
            if (!empty($options['no_network_retry'])) {
                $payload['_rx_no_network_retry'] = true;
            }
            if ($keyboard !== null && $keyboard !== '') {
                $payload['reply_markup'] = $keyboard;
            }
        } catch (Throwable $e) {
            rx_sbv_log('SBV_REPORT_FAILED', $e->getMessage());
            return ['state' => 'failed', 'ok' => false, 'error' => $e->getMessage()];
        }
        try {
            $response = telegram('sendmessage', $payload);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_REPORT_FAILED', $e->getMessage());
            return ['state' => 'uncertain', 'ok' => false, 'error' => $e->getMessage(), 'uncertain' => true];
        }
        if (!is_array($response)) {
            return ['state' => 'uncertain', 'ok' => false, 'error' => 'invalid_telegram_response', 'uncertain' => true];
        }
        if (($response['ok'] ?? null) === true) {
            $result = is_array($response['result'] ?? null) ? $response['result'] : [];
            $chatId = $result['chat']['id'] ?? $channel;
            $messageId = isset($result['message_id']) ? (int) $result['message_id'] : 0;
            if ($messageId <= 0) {
                return ['state' => 'uncertain', 'ok' => false, 'error' => 'missing_telegram_message_id', 'uncertain' => true];
            }
            return [
                'state' => 'sent',
                'ok' => true,
                'chat_id' => (string) $chatId,
                'message_id' => $messageId,
                'response' => $response,
            ];
        }
        $errorCode = (int) ($response['error_code'] ?? 0);
        $error = (string) ($response['description'] ?? 'telegram_send_failed');
        $definitiveFailure = ($response['ok'] ?? null) === false
            && empty($response['_rx_invalid_response'])
            && $errorCode >= 400 && $errorCode < 500;
        $state = $definitiveFailure ? 'failed' : 'uncertain';
        return [
            'state' => $state,
            'ok' => false,
            'error' => $error,
            'error_code' => $errorCode,
            'uncertain' => $state === 'uncertain',
            'response' => $response,
        ];
    }
}

if (!function_exists('rx_sbv_report_marker_fetch')) {
    function rx_sbv_report_marker_fetch(PDO $pdo, string $action, ?int $depositId, ?string $orderId): ?array
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_audit WHERE action = ? AND deposit_id <=> ? AND id_order <=> ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$action, $depositId, $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            rx_sbv_log('SBV_REPORT_MARKER_READ_FAILED', $e->getMessage(), ['action' => $action, 'deposit_id' => $depositId]);
            throw $e;
        }
    }
}

if (!function_exists('rx_sbv_report_marker_save')) {
    function rx_sbv_report_marker_save(PDO $pdo, ?int $markerId, string $action, ?int $depositId, ?string $orderId, ?string $actor, array $data): ?int
    {
        $state = substr((string) ($data['state'] ?? 'failed'), 0, 40);
        $reason = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($reason === false) {
            $reason = json_encode(['state' => $state], JSON_UNESCAPED_SLASHES);
        }
        $receivedRial = isset($data['received_rial']) && $data['received_rial'] !== null ? (int) $data['received_rial'] : null;
        try {
            if ($markerId !== null) {
                $stmt = $pdo->prepare("UPDATE card_sms_audit SET outcome = ?, reason = ?, received_rial = COALESCE(?, received_rial) WHERE id = ?");
                $stmt->execute([$state, $reason, $receivedRial, $markerId]);
                return $markerId;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $orderId,
                $depositId,
                $actor,
                $action,
                isset($data['principal_toman']) ? (int) $data['principal_toman'] : null,
                isset($data['fee_toman']) ? (int) $data['fee_toman'] : null,
                isset($data['expected_rial']) ? (int) $data['expected_rial'] : null,
                $receivedRial,
                isset($data['credit_toman']) ? (int) $data['credit_toman'] : null,
                $state,
                $reason,
                time(),
            ]);
            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            rx_sbv_log('SBV_REPORT_MARKER_WRITE_FAILED', $e->getMessage(), ['action' => $action, 'deposit_id' => $depositId]);
            return null;
        }
    }
}

if (!function_exists('rx_sbv_deliver_tracked_report')) {
    function rx_sbv_deliver_tracked_report(string $action, ?int $depositId, ?string $orderId, ?string $actor, string $text, $keyboard = null, array $meta = []): array
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return ['state' => 'failed', 'ok' => false, 'error' => 'db_unavailable'];
        }
        $lockName = 'rx_sbv_rpt_' . md5($action . '|' . ($depositId ?? '') . '|' . ($orderId ?? ''));
        $locked = false;
        try {
            $locked = (int) $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 0)")->fetchColumn() === 1;
        } catch (Throwable $e) {
            $locked = false;
        }
        if (!$locked) {
            return ['state' => 'busy', 'ok' => false, 'error' => 'report_lock_busy'];
        }
        $markerId = null;
        $attempts = 1;
        $data = [];
        try {
            $marker = rx_sbv_report_marker_fetch($pdo, $action, $depositId, $orderId);
            if (is_array($marker)) {
                $decoded = json_decode((string) ($marker['reason'] ?? ''), true);
                $existing = is_array($decoded) ? $decoded : [];
                $markerState = (string) ($marker['outcome'] ?? ($existing['state'] ?? ''));
                if ($markerState === 'sent') {
                    return [
                        'state' => 'sent',
                        'ok' => true,
                        'already_sent' => true,
                        'chat_id' => (string) ($existing['chat_id'] ?? ''),
                        'message_id' => (int) ($existing['message_id'] ?? 0),
                        'marker_id' => (int) $marker['id'],
                    ];
                }
                if ($markerState === 'sending' || $markerState === 'uncertain') {
                    return [
                        'state' => 'uncertain',
                        'ok' => false,
                        'uncertain' => true,
                        'error' => (string) ($existing['error'] ?? 'delivery_uncertain'),
                        'marker_id' => (int) $marker['id'],
                    ];
                }
                if (!in_array($markerState, ['failed'], true)) {
                    return [
                        'state' => 'uncertain',
                        'ok' => false,
                        'uncertain' => true,
                        'error' => 'legacy_delivery_unknown',
                        'marker_id' => (int) $marker['id'],
                    ];
                }
                $markerId = (int) $marker['id'];
                $attempts = max(1, (int) ($existing['attempts'] ?? 0) + 1);
            }
            $data = array_merge($meta, [
                'state' => 'sending',
                'attempts' => $attempts,
                'started_at' => time(),
                'updated_at' => time(),
                'action' => $action,
                'deposit_id' => $depositId,
                'order_id' => $orderId,
                'text_hash' => hash('sha256', $text),
            ]);
            $markerId = rx_sbv_report_marker_save($pdo, $markerId, $action, $depositId, $orderId, $actor, $data);
            if ($markerId === null) {
                return ['state' => 'failed', 'ok' => false, 'error' => 'report_tracking_failed'];
            }
        } catch (Throwable $e) {
            rx_sbv_log('SBV_REPORT_TRACKING_FAILED', $e->getMessage(), ['action' => $action, 'deposit_id' => $depositId]);
            return ['state' => 'failed', 'ok' => false, 'error' => 'report_tracking_failed'];
        } finally {
            try {
                $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
            } catch (Throwable $e) {
            }
        }
        $delivery = rx_sbv_report($text, $keyboard, ['no_network_retry' => true]);
        $final = array_merge($data, [
            'state' => (string) ($delivery['state'] ?? 'uncertain'),
            'updated_at' => time(),
            'chat_id' => $delivery['chat_id'] ?? null,
            'message_id' => $delivery['message_id'] ?? null,
            'error' => $delivery['error'] ?? null,
            'error_code' => $delivery['error_code'] ?? null,
        ]);
        rx_sbv_report_marker_save($pdo, $markerId, $action, $depositId, $orderId, $actor, $final);
        return array_merge($delivery, ['marker_id' => $markerId, 'attempts' => $attempts]);
    }
}

if (!function_exists('rx_sbv_finalize')) {
    function rx_sbv_finalize(string $orderId, string $state, string $resolution, ?string $actor, ?string $note): void
    {
        try {
            rx_sbv_tx(function (PDO $pdo) use ($orderId, $state, $resolution, $actor, $note) {
                $now = time();
                $stmt = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $stmt->execute([$orderId]);
                $csp = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($csp === false) {
                    return;
                }
                if (in_array((string) $csp['state'], ['approved', 'credited'], true)) {
                    return;
                }
                $pdo->prepare("UPDATE card_sms_payment SET state = ?, resolution = ?, resolved_by = ?, resolved_at = ?, resolution_note = ?, updated_at = ? WHERE id = ?")
                    ->execute([$state, $resolution, $actor, $now, $note, $now, (int) $csp['id']]);
                if ($csp['deposit_id'] !== null) {
                    $pdo->prepare("UPDATE card_sms_deposit SET state = 'applied', id_order = ?, updated_at = ? WHERE id = ? AND state IN ('matched', 'linked')")
                        ->execute([$orderId, $now, (int) $csp['deposit_id']]);
                }
                rx_sbv_release_amount($pdo, $csp, false);
            });
        } catch (Throwable $e) {
            rx_sbv_log('SBV_FINALIZE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
        if (function_exists('rx_redis_del')) {
            $pr = rx_sbv_payment_report($orderId);
            if (is_array($pr)) {
                rx_redis_del(rx_payment_status_cache_key($orderId, $pr['id_user']));
            }
        }
    }
}

if (!function_exists('rx_sbv_run_claimed')) {
    function rx_sbv_run_claimed(string $orderId, string $token, string $image, string $releaseTo): array
    {
        $result = rx_pf_attempt_claimed_delivery($orderId, $token, $image, static function (Throwable $e) use ($orderId) {
            rx_sbv_log('SBV_DIRECT_PAYMENT_THROWABLE', $e->getMessage(), ['id_order' => $orderId, 'file' => $e->getFile(), 'line' => $e->getLine()]);
        });
        $settle = rx_pf_settle($orderId, $token, $result, $releaseTo, 'waiting');
        return [
            'result' => $result,
            'settle' => $settle,
            'finalized' => !empty($result['completed']) || $settle === 'finalized' || !empty($result['already_completed']) || $settle === 'paid',
        ];
    }
}

if (!function_exists('rx_sbv_release_status')) {
    function rx_sbv_release_status(array $report, string $original): string
    {
        if (trim((string) ($report['dec_not_confirmed'] ?? '')) === 'receipt-submitted' || $original === 'waiting') {
            return 'waiting';
        }
        return in_array($original, ['Unpaid', 'expire', 'cancelled', 'reject'], true) ? $original : 'waiting';
    }
}

if (!function_exists('rx_sbv_apply_matched')) {
    function rx_sbv_apply_matched(string $orderId, string $image): string
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null || (string) $csp['state'] !== 'matched') {
            return 'skip';
        }
        $before = rx_sbv_payment_report($orderId);
        if ($before === null) {
            return 'skip';
        }
        $status = (string) $before['payment_Status'];
        if ((int) ($before['direct_payment_done'] ?? 0) === 1 || $status === 'paid') {
            rx_sbv_finalize($orderId, 'approved', 'auto', 'system', null);
            return 'finalized';
        }
        if (!in_array($status, ['Unpaid', 'waiting'], true)) {
            return 'skip';
        }
        $claim = rx_pf_claim($orderId, ['from' => ['Unpaid', 'waiting'], 'to' => 'processing']);
        $claimStatus = (string) ($claim['status'] ?? 'error');
        if ($claimStatus === 'already_completed') {
            rx_sbv_finalize($orderId, 'approved', 'auto', 'system', null);
            return 'finalized';
        }
        if ($claimStatus !== 'claimed') {
            return 'claim_' . $claimStatus;
        }
        $report = is_array($claim['report'] ?? null) ? $claim['report'] : $before;
        $releaseTo = rx_sbv_release_status($report, $status);
        $run = rx_sbv_run_claimed($orderId, (string) $claim['token'], $image, $releaseTo);
        if (empty($run['finalized'])) {
            rx_sbv_log('SBV_AUTO_APPLY_NOT_FINAL', 'Matched deposit claim did not finalize', [
                'id_order' => $orderId,
                'settle' => (string) $run['settle'],
                'result' => (string) ($run['result']['status'] ?? ''),
                'reason' => (string) ($run['result']['reason'] ?? ''),
            ]);
            rx_sbv_hold_if_terminal($orderId, $run);
            return 'pending_' . (string) $run['settle'];
        }
        rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
        return 'finalized';
    }
}

if (!function_exists('rx_sbv_hold_if_terminal')) {
    function rx_sbv_hold_if_terminal(string $orderId, array $run): void
    {
        $result = (array) ($run['result'] ?? []);
        $settle = (string) ($run['settle'] ?? '');
        $retryable = !empty($result['retryable']) || !empty($result['requires_reconciliation']) || in_array($settle, ['reconciling', 'error', 'not_owner', 'missing'], true);
        if ($retryable) {
            return;
        }
        try {
            rx_sbv_tx(function (PDO $pdo) use ($orderId, $result, $settle) {
                $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $s->execute([$orderId]);
                $csp = $s->fetch(PDO::FETCH_ASSOC);
                if ($csp === false || (string) $csp['state'] !== 'matched') {
                    return;
                }
                $now = time();
                $pdo->prepare("UPDATE card_sms_payment SET state = 'review', resolution_note = ?, updated_at = ? WHERE id = ?")
                    ->execute([substr('auto_apply_failed:' . (string) ($result['status'] ?? '') . ':' . (string) ($result['reason'] ?? '') . ':' . $settle, 0, 500), $now, (int) $csp['id']]);
                if ($csp['deposit_id'] !== null) {
                    $pdo->prepare("UPDATE card_sms_deposit SET state = 'linked', note = 'auto_apply_failed', updated_at = ? WHERE id = ? AND state = 'matched'")
                        ->execute([$now, (int) $csp['deposit_id']]);
                }
            });
            rx_sbv_report("⚠️ <b>واریز شناسایی شد اما تکمیل خودکار سفارش ناموفق بود</b>\n\n<blockquote>کد پیگیری: <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code></blockquote>\nواریز به این پرداخت متصل ماند و برای تصمیم ادمین نگه داشته شد. از دکمه «🔎 بررسی تطبیق» روی رسید یا بخش «🔐 تأیید رسید با بررسی» اقدام کنید.");
        } catch (Throwable $e) {
            rx_sbv_log('SBV_HOLD_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
    }
}

if (!function_exists('rx_sbv_after_approval')) {
    function rx_sbv_after_approval(string $orderId, string $resolution, string $actor, ?string $reason, bool $withCashback): void
    {
        $csp = rx_sbv_get($orderId);
        $outcome = rx_sbv_fulfillment_outcome($orderId);
        $special = is_array($outcome) && in_array($outcome['outcome'], ['refunded', 'wallet_credit'], true) ? (string) $outcome['outcome'] : null;
        $isAuto = $resolution === 'auto';
        if ($special !== null) {
            $resolution .= $special === 'refunded' ? '_refund' : '_wallet_credit';
            $withCashback = false;
        }
        rx_sbv_finalize($orderId, $special !== null ? 'credited' : 'approved', $resolution, $actor, $reason);
        $report = rx_sbv_payment_report($orderId);
        if (!is_array($report) || $csp === null) {
            return;
        }
        $cashback = $withCashback ? rx_sbv_cashback($report) : 0;
        rx_sbv_audit($orderId, $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null, $actor, $isAuto ? 'auto_approve' : 'admin_approve', [
            'principal_toman' => (int) $csp['principal_toman'],
            'fee_toman' => (int) $csp['fee_toman'],
            'expected_rial' => (int) $csp['payable_rial'],
            'received_rial' => $csp['received_rial'] !== null ? (int) $csp['received_rial'] : null,
            'credit_toman' => $special !== null ? (int) ($outcome['amount'] ?? 0) : (int) $csp['principal_toman'],
            'outcome' => $resolution,
            'reason' => $reason,
        ]);
        $user = function_exists('select') ? select('user', '*', 'id', $report['id_user'], 'select', ['cache' => false]) : null;
        $uname = is_array($user) ? (string) ($user['username'] ?? '') : '';
        $kind = $isAuto ? 'تأیید خودکار با بررسی پیامک بانکی' : 'تأیید ادمین (پرداخت با بررسی)';
        if ($special === 'refunded') {
            $kind .= ' — سفارش تکمیل نشد و اصل مبلغ به کیف پول بازگشت';
        } elseif ($special === 'wallet_credit') {
            $kind .= ' — سفارش تکمیل نشد و اصل مبلغ به کیف پول واریز شد';
        }
        $text = "📣 پرداخت کارت‌به‌کارت تأیید شد.\n\n"
            . "<blockquote>🤖 نوع تایید : {$kind}</blockquote>\n"
            . "<blockquote>👤 آیدی عددی کاربر : <code>" . htmlspecialchars((string) $report['id_user'], ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n"
            . "<blockquote>👤 نام کاربری کاربر : @" . htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
            . "<blockquote>💰 مبلغ اصل سفارش/شارژ : " . rx_sbv_money($csp['principal_toman']) . " تومان</blockquote>\n"
            . "<blockquote>🏷 کارمزد شناسایی : " . rx_sbv_money($csp['fee_toman']) . " تومان</blockquote>\n"
            . "<blockquote>✅ مبلغ قابل پرداخت : " . rx_sbv_money($csp['payable_toman']) . " تومان</blockquote>\n"
            . "<blockquote>📥 مبلغ دریافتی : " . ($csp['received_rial'] !== null ? rx_sbv_money(intdiv((int) $csp['received_rial'], 10)) . ' تومان' : 'ثبت نشده') . "</blockquote>\n"
            . "<blockquote>کد پیگیری پرداخت : <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code></blockquote>"
            . ($cashback > 0 ? "\n<blockquote>🎁 کش‌بک : " . rx_sbv_money($cashback) . " تومان</blockquote>" : '')
            . ($reason !== null && $reason !== '' ? "\n<blockquote>✍️ دلیل : " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</blockquote>" : '')
            . ($actor !== 'system' ? "\n<blockquote>👤 ادمین : <code>" . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . "</code></blockquote>" : '');
        $delivered = (int) ($report['report_message_id'] ?? 0) > 0 || trim((string) ($report['private_receipt_targets'] ?? '')) !== '';
        if ($delivered && function_exists('rxReceiptSyncTelegram')) {
            $kb = json_encode(['inline_keyboard' => [[['text' => '⚙️ مدیریت کاربر', 'callback_data' => 'manageuser_' . $report['id_user']]]]], JSON_UNESCAPED_UNICODE);
            rxReceiptSyncTelegram($report, $text, $kb);
        } else {
            rx_sbv_report($text);
        }
    }
}

if (!function_exists('rx_sbv_fulfillment_outcome')) {
    function rx_sbv_fulfillment_outcome(string $orderId): ?array
    {
        $csp = rx_sbv_get($orderId);
        $pdo = rx_sbv_pdo();
        if ($csp === null || $pdo === null) {
            return null;
        }
        $pr = rx_sbv_payment_report($orderId);
        $status = is_array($pr) ? (string) $pr['payment_Status'] : '';
        $done = is_array($pr) && (int) ($pr['direct_payment_done'] ?? 0) === 1;
        try {
            $st = $pdo->prepare("SELECT amount FROM wallet_ledger WHERE id_order = ? AND category = 'refund' AND direction = 'credit' ORDER BY id DESC LIMIT 1");
            $st->execute([$orderId]);
            $refund = $st->fetchColumn();
            if ($refund !== false) {
                return ['outcome' => 'refunded', 'amount' => (int) $refund];
            }
        } catch (Throwable $e) {
        }
        try {
            $st = $pdo->prepare("SELECT resolution FROM checkout_order WHERE (settle_order_id = ? OR payment_order_id = ?) AND resolution = 'wallet_credit' LIMIT 1");
            $st->execute([$orderId, $orderId]);
            if ($st->fetchColumn() !== false) {
                $c = $pdo->prepare("SELECT amount FROM wallet_ledger WHERE fulfillment_key = ? LIMIT 1");
                $c->execute(['ckcredit:' . $orderId]);
                $amt = $c->fetchColumn();
                return ['outcome' => 'wallet_credit', 'amount' => $amt === false ? (int) ($pr['price'] ?? 0) : (int) $amt];
            }
        } catch (Throwable $e) {
        }
        if ((string) $csp['state'] === 'credited' && in_array((string) $csp['resolution'], ['admin_credit'], true)) {
            $a = $pdo->prepare("SELECT credit_toman FROM card_sms_audit WHERE id_order = ? AND action = 'admin_credit' ORDER BY id DESC LIMIT 1");
            $a->execute([$orderId]);
            $amt = $a->fetchColumn();
            return ['outcome' => 'wallet_credit', 'amount' => $amt === false ? 0 : (int) $amt];
        }
        $pfState = '';
        try {
            $st = $pdo->prepare("SELECT state FROM payment_fulfillment WHERE id_order = ? LIMIT 1");
            $st->execute([$orderId]);
            $pfState = (string) $st->fetchColumn();
        } catch (Throwable $e) {
        }
        if ($status === 'paid' || $done) {
            return ['outcome' => 'completed'];
        }
        if (in_array($status, ['processing', 'reconciling'], true) || in_array($pfState, ['claimed', 'applying', 'reconciling'], true)) {
            return ['outcome' => 'processing'];
        }
        if ($status === 'manual_review' || $pfState === 'manual_review' || $pfState === 'failed' || (string) $csp['state'] === 'review') {
            return ['outcome' => 'failed'];
        }
        if ((string) $csp['state'] === 'matched') {
            return ['outcome' => 'processing'];
        }
        if (in_array($status, ['reject', 'expire', 'cancelled'], true)) {
            return ['outcome' => 'closed'];
        }
        return ['outcome' => 'pending'];
    }
}

if (!function_exists('rx_sbv_recover_order')) {
    function rx_sbv_recover_order(string $orderId, string $image): array
    {
        $out = ['status' => 'skip', 'order_id' => $orderId];
        if ($orderId === '' || !function_exists('rx_pf_recover')) {
            return $out;
        }
        try {
            $recovered = rx_pf_recover($orderId);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_RECOVER_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            $out['status'] = 'error';
            return $out;
        }
        $status = (string) ($recovered['status'] ?? 'skip');
        $out['recovery'] = $status;
        if ($status === 'finalized') {
            $csp = rx_sbv_get($orderId);
            if ($csp !== null && (string) $csp['state'] === 'matched') {
                rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
            }
            $out['status'] = 'completed';
            return $out;
        }
        if ($status !== 'claimed' && $status !== 'reconcile') {
            if ($status === 'rejected' || $status === 'manual_review') {
                $out['status'] = 'failed';
            } elseif ($status === 'error') {
                $out['status'] = 'error';
            }
            return $out;
        }
        $report = is_array($recovered['report'] ?? null) ? $recovered['report'] : (rx_sbv_payment_report($orderId) ?? []);
        $run = rx_sbv_run_claimed($orderId, (string) ($recovered['token'] ?? ''), $image, rx_sbv_release_status($report, 'waiting'));
        $out['settle'] = (string) ($run['settle'] ?? '');
        if (!empty($run['finalized'])) {
            $csp = rx_sbv_get($orderId);
            if ($csp !== null && (string) $csp['state'] === 'matched') {
                rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
            }
            $out['status'] = 'completed';
            return $out;
        }
        rx_sbv_hold_if_terminal($orderId, $run);
        $out['status'] = 'processing';
        return $out;
    }
}

if (!function_exists('rx_sbv_process_deposit')) {
    function rx_sbv_process_deposit(int $depositId, string $image): array
    {
        $out = ['status' => 'skipped', 'deposit_id' => $depositId, 'deposit_state' => null, 'order_id' => null];
        if ($depositId <= 0) {
            $out['status'] = 'missing';
            return $out;
        }
        $deposit = rx_sbv_deposit($depositId);
        if (!is_array($deposit)) {
            $out['status'] = 'missing';
            return $out;
        }
        $state = (string) ($deposit['state'] ?? '');
        if ($state === 'pending') {
            $match = rx_sbv_match_deposit($depositId);
            $out['match'] = $match;
            $deposit = rx_sbv_deposit($depositId);
            if (!is_array($deposit)) {
                $out['status'] = 'error';
                return $out;
            }
            $state = (string) ($deposit['state'] ?? '');
        }
        $out['deposit_state'] = $state;
        $orderId = trim((string) ($deposit['id_order'] ?? ''));
        if ($orderId !== '') {
            $out['order_id'] = $orderId;
        }
        if ($state === 'pending') {
            $out['status'] = ($out['match'] ?? '') === 'error' ? 'error' : 'skipped';
            return $out;
        }
        if (in_array($state, ['test', 'ignored'], true)) {
            $out['status'] = 'ignored';
            return $out;
        }
        if (in_array($state, ['unmatched', 'ambiguous', 'late'], true)) {
            $out['status'] = $state;
            try {
                rx_sbv_report_unmatched_deposit($depositId);
            } catch (Throwable $e) {
                rx_sbv_log('SBV_REVIEW_REPORT_FAILED', $e->getMessage(), ['deposit_id' => $depositId]);
            }
            return $out;
        }
        if ($state === 'applied' || $state === 'reviewed_rejected' || $state === 'dismissed') {
            $out['status'] = 'already_processed';
            return $out;
        }
        if ($state !== 'matched') {
            $out['status'] = 'skipped';
            return $out;
        }
        if ($orderId === '' || !rx_sbv_is_verify_order($orderId)) {
            $out['status'] = 'skipped';
            return $out;
        }
        try {
            $applied = rx_sbv_apply_matched($orderId, $image);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_AUTO_APPLY_FAILED', $e->getMessage(), ['id_order' => $orderId, 'deposit_id' => $depositId]);
            $applied = 'error';
        }
        $out['fulfillment'] = $applied;
        $outcome = rx_sbv_fulfillment_outcome($orderId);
        $kind = is_array($outcome) ? (string) ($outcome['outcome'] ?? '') : '';
        if ($applied === 'finalized' || $kind === 'completed') {
            $out['status'] = 'completed';
            return $out;
        }
        if ($kind === 'failed') {
            $out['status'] = 'failed';
            return $out;
        }
        if ($kind === 'closed') {
            $out['status'] = 'closed';
            return $out;
        }
        if ($kind === 'refunded' || $kind === 'wallet_credit') {
            $out['status'] = 'resolved';
            return $out;
        }
        if (in_array($applied, ['claim_busy', 'claim_manual_review', 'claim_not_eligible'], true) && $kind !== 'processing') {
            $out['status'] = $kind === '' ? 'error' : 'skipped';
            return $out;
        }
        if ($kind === 'processing' || $applied === 'error' || strpos($applied, 'pending_') === 0 || $applied === 'claim_requires_reconciliation') {
            try {
                $recovery = rx_sbv_recover_order($orderId, $image);
            } catch (Throwable $e) {
                rx_sbv_log('SBV_RECOVER_FAILED', $e->getMessage(), ['id_order' => $orderId]);
                $recovery = ['status' => 'error'];
            }
            $out['recovery'] = $recovery;
            if (($recovery['status'] ?? '') === 'completed') {
                $out['status'] = 'completed';
                return $out;
            }
            if (($recovery['status'] ?? '') === 'failed') {
                $out['status'] = 'failed';
                return $out;
            }
            $out['status'] = ($recovery['status'] ?? '') === 'error' ? 'error' : 'processing';
            return $out;
        }
        $out['status'] = $kind === 'pending' ? 'pending' : 'skipped';
        return $out;
    }
}

if (!function_exists('rx_sbv_sweep')) {
    function rx_sbv_sweep(int $limit = 200): int
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return 0;
        }
        $count = 0;
        try {
            $stmt = $pdo->prepare(
                "SELECT csp.id_order, csp.state, csp.created_at, pr.payment_Status, pr.direct_payment_done, pr.id AS pr_id
                   FROM card_sms_payment csp
                   LEFT JOIN Payment_report pr ON pr.id_order = csp.id_order
                  WHERE csp.state IN ('open', 'matched', 'review')
                    AND (pr.id IS NULL OR pr.payment_Status IN ('paid', 'reject', 'expire', 'cancelled') OR COALESCE(pr.direct_payment_done, 0) = 1)
                  ORDER BY csp.id ASC LIMIT " . max(1, $limit)
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_SWEEP_FAILED', $e->getMessage());
            return 0;
        }
        foreach ($rows as $row) {
            $orderId = (string) $row['id_order'];
            $status = (string) ($row['payment_Status'] ?? '');
            if ($row['pr_id'] === null) {
                if ((int) $row['created_at'] < time() - 600) {
                    try {
                        rx_sbv_tx(function (PDO $pdo) use ($orderId) {
                            $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                            $s->execute([$orderId]);
                            $csp = $s->fetch(PDO::FETCH_ASSOC);
                            if ($csp === false || !in_array((string) $csp['state'], ['open', 'matched', 'review'], true)) {
                                return;
                            }
                            $now = time();
                            if ($csp['deposit_id'] !== null) {
                                $pdo->prepare("UPDATE card_sms_deposit SET state = 'unmatched', note = 'order_missing', id_order = NULL, updated_at = ? WHERE id = ? AND state IN ('matched', 'linked')")
                                    ->execute([$now, (int) $csp['deposit_id']]);
                            }
                            $pdo->prepare("UPDATE card_sms_payment SET state = 'void', resolution = 'order_missing', deposit_id = NULL, updated_at = ? WHERE id = ?")
                                ->execute([$now, (int) $csp['id']]);
                            rx_sbv_release_amount($pdo, $csp, $csp['deposit_id'] === null);
                        });
                        $count++;
                    } catch (Throwable $e) {
                        rx_sbv_log('SBV_SWEEP_FAILED', $e->getMessage(), ['id_order' => $orderId]);
                    }
                }
                continue;
            }
            if ($status === 'paid' || (int) ($row['direct_payment_done'] ?? 0) === 1) {
                rx_sbv_finalize($orderId, 'approved', 'external', null, null);
                $count++;
                continue;
            }
            try {
                rx_sbv_tx(function (PDO $pdo) use ($orderId, $status) {
                    $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                    $s->execute([$orderId]);
                    $csp = $s->fetch(PDO::FETCH_ASSOC);
                    if ($csp === false || !in_array((string) $csp['state'], ['open', 'matched', 'review'], true)) {
                        return;
                    }
                    $now = time();
                    $keepDeposit = $csp['deposit_id'];
                    if ($csp['deposit_id'] !== null) {
                        $d = $pdo->prepare("UPDATE card_sms_deposit SET state = 'unmatched', note = ?, id_order = NULL, updated_at = ? WHERE id = ? AND state = 'matched'");
                        $d->execute(['order_closed:' . $status . ':' . $orderId, $now, (int) $csp['deposit_id']]);
                        if ($d->rowCount() === 1) {
                            $keepDeposit = null;
                        }
                    }
                    $resolution = $status === 'expire' ? 'expired' : ($status === 'reject' ? 'rejected' : 'cancelled');
                    $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = COALESCE(resolution, ?), resolved_at = ?, deposit_id = ?, updated_at = ? WHERE id = ?")
                        ->execute([$resolution, $now, $keepDeposit, $now, (int) $csp['id']]);
                    rx_sbv_release_amount($pdo, $csp, false);
                });
                $count++;
            } catch (Throwable $e) {
                rx_sbv_log('SBV_SWEEP_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            }
        }
        return $count;
    }
}

if (!function_exists('rx_sbv_recover')) {
    function rx_sbv_recover(string $image, int $limit = 20): void
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT pr.id_order FROM Payment_report pr JOIN card_sms_payment csp ON csp.id_order = pr.id_order
                  WHERE pr.payment_Status IN ('processing', 'reconciling') AND pr.Payment_Method = 'cart to cart'
                  ORDER BY pr.id ASC LIMIT " . max(1, $limit)
            );
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_RECOVER_SCAN_FAILED', $e->getMessage());
            return;
        }
        foreach ($orders as $orderId) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
                break;
            }
            $orderId = (string) $orderId;
            $recovered = rx_pf_recover($orderId);
            $status = (string) ($recovered['status'] ?? '');
            if ($status === 'finalized') {
                $csp = rx_sbv_get($orderId);
                if ($csp !== null && (string) $csp['state'] === 'matched') {
                    rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
                }
                continue;
            }
            if ($status !== 'claimed' && $status !== 'reconcile') {
                continue;
            }
            $report = is_array($recovered['report'] ?? null) ? $recovered['report'] : (rx_sbv_payment_report($orderId) ?? []);
            $run = rx_sbv_run_claimed($orderId, (string) $recovered['token'], $image, rx_sbv_release_status($report, 'waiting'));
            if (!empty($run['finalized'])) {
                $csp = rx_sbv_get($orderId);
                if ($csp !== null && (string) $csp['state'] === 'matched') {
                    rx_sbv_after_approval($orderId, 'auto', 'system', null, true);
                }
            } else {
                rx_sbv_hold_if_terminal($orderId, $run);
            }
        }
    }
}

if (!function_exists('rx_sbv_cron_run')) {
    function rx_sbv_cron_run(string $image = '../images.jpg'): array
    {
        $out = ['matched' => 0, 'unmatched' => 0, 'applied' => 0, 'swept' => 0];
        $pdo = rx_sbv_pdo();
        if ($pdo === null || !rx_sbv_has_schema() || !function_exists('rx_pf_claim')) {
            return $out;
        }
        try {
            $lock = $pdo->query("SELECT GET_LOCK('rx_sbv_cron', 0)")->fetchColumn();
        } catch (Throwable $e) {
            $lock = 0;
        }
        if ((int) $lock !== 1) {
            return $out;
        }
        try {
            rx_sbv_recover($image);
            $pending = $pdo->query("SELECT id FROM card_sms_deposit WHERE state = 'pending' ORDER BY id ASC LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($pending as $depositId) {
                if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
                    break;
                }
                $r = rx_sbv_match_deposit((int) $depositId);
                if ($r === 'matched') {
                    $out['matched']++;
                } elseif ($r !== 'skip' && $r !== 'error') {
                    $out['unmatched']++;
                }
            }
            $matched = $pdo->query("SELECT id_order FROM card_sms_payment WHERE state = 'matched' ORDER BY updated_at ASC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($matched as $orderId) {
                if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
                    break;
                }
                if (rx_sbv_apply_matched((string) $orderId, $image) === 'finalized') {
                    $out['applied']++;
                }
            }
            $out['swept'] = rx_sbv_sweep();
            if ($out['unmatched'] > 0) {
                $total = (int) $pdo->query("SELECT COUNT(*) FROM card_sms_deposit WHERE state IN ('unmatched', 'ambiguous', 'late')")->fetchColumn();
                rx_sbv_report("⚠️ <b>واریز تطبیق‌نیافته</b>\n\n" . (int) $out['unmatched'] . " واریز جدید با هیچ پرداخت فعالی تطبیق داده نشد و برای بررسی ادمین نگه داشته شد.\n📥 تعداد کل در صف بررسی: " . $total . "\n\nمسیر: مالی و گزارشات ← کارت به کارت ← 🔐 تأیید رسید با بررسی ← واریزهای تطبیق‌نیافته");
            }
        } catch (Throwable $e) {
            rx_sbv_log('SBV_CRON_FAILED', $e->getMessage());
        } finally {
            try {
                $pdo->query("SELECT RELEASE_LOCK('rx_sbv_cron')");
            } catch (Throwable $e) {
            }
        }
        return $out;
    }
}

if (!function_exists('rx_sbv_confirm_guard')) {
    function rx_sbv_confirm_guard(string $orderId, string $reason): ?string
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null) {
            return null;
        }
        $report = rx_sbv_payment_report($orderId);
        if (rx_sbv_payment_is_expired($csp, $report)) {
            return trim($reason) !== '' ? null : 'verify_reason_required';
        }
        if (trim($reason) !== '') {
            return null;
        }
        if ($csp['deposit_id'] !== null && $csp['received_rial'] !== null && (int) $csp['received_rial'] === (int) $csp['payable_rial']) {
            return null;
        }
        return 'verify_reason_required';
    }
}

if (!function_exists('rx_sbv_payment_reject_guard')) {
    function rx_sbv_payment_reject_guard(string $orderId): ?string
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null) {
            return 'not_verify';
        }
        $report = rx_sbv_payment_report($orderId);
        if (!is_array($report)) {
            return 'not_found';
        }
        $status = (string) ($report['payment_Status'] ?? '');
        if ($status === 'paid' || (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return 'already_paid';
        }
        if (in_array($status, ['processing', 'reconciling'], true)) {
            return 'processing';
        }
        if (in_array($status, ['reject', 'cancelled'], true)) {
            return 'already_final';
        }
        if (!in_array($status, ['Unpaid', 'waiting', 'expire'], true)) {
            return 'not_eligible';
        }
        $state = (string) ($csp['state'] ?? '');
        if (in_array($state, ['approved', 'credited'], true)) {
            return 'already_paid';
        }
        $expired = rx_sbv_payment_is_expired($csp, $report);
        if (!in_array($state, ['open', 'matched', 'review'], true) && !($state === 'closed' && $expired)) {
            return 'not_eligible';
        }
        if (function_exists('rx_pf_fetch')) {
            try {
                $record = rx_pf_fetch($orderId);
                if (is_array($record)) {
                    $recordState = (string) ($record['state'] ?? '');
                    if ($recordState === 'completed') {
                        return 'already_paid';
                    }
                    if (in_array($recordState, ['claimed', 'applying', 'reconciling'], true) && (int) ($record['lease_expires_at'] ?? 0) > time()) {
                        return 'processing';
                    }
                }
            } catch (Throwable $e) {
            }
        }
        return null;
    }
}

if (!function_exists('rx_sbv_payment_link_guard')) {
    function rx_sbv_payment_link_guard(string $orderId, int $depositId = 0): ?string
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null) {
            return 'order_not_verify';
        }
        $report = rx_sbv_payment_report($orderId);
        if (!is_array($report)) {
            return 'not_found';
        }
        $status = (string) ($report['payment_Status'] ?? '');
        if ($status === 'paid' || (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return 'already_paid';
        }
        if (in_array($status, ['processing', 'reconciling'], true)) {
            return 'processing';
        }
        $state = (string) ($csp['state'] ?? '');
        $expired = rx_sbv_payment_is_expired($csp, $report);
        if ($state !== 'open' && !($expired && in_array($state, ['review', 'closed'], true))) {
            return 'order_state_' . $state;
        }
        if ($state === 'closed' && !$expired) {
            return 'order_state_closed';
        }
        if (!$expired && (int) ($csp['expires_at'] ?? 0) < time()) {
            return 'order_state_expired';
        }
        if ($csp['deposit_id'] !== null) {
            return 'order_has_deposit';
        }
        $allowed = $expired ? ['Unpaid', 'waiting', 'pending', 'expire'] : ['Unpaid', 'waiting', 'pending'];
        if (!in_array($status, $allowed, true)) {
            return 'not_eligible';
        }
        return null;
    }
}

if (!function_exists('rx_sbv_match_failure_label')) {
    function rx_sbv_match_failure_label(string $state, string $note): string
    {
        $raw = trim($note);
        $code = strpos($raw, ':') !== false ? substr($raw, 0, strpos($raw, ':')) : $raw;
        $map = [
            'no_matching_payment' => 'هیچ پرداخت فعالی با مبلغ دقیق این واریز پیدا نشد.',
            'multiple_candidates' => 'چند پرداخت فعال با مبلغ دقیق این واریز وجود داشت؛ تطبیق خودکار برای جلوگیری از انتخاب اشتباه متوقف شد.',
            'exact_amount_inactive_payment' => 'مبلغ دقیق با یک پرداخت غیرفعال یا خارج از مهلت برابر بود.',
            'sms_predates_payment' => 'زمان پیامک بانکی پیش از ایجاد پرداخت متناظر است.',
            'order_not_payable' => 'پرداخت متناظر دیگر در وضعیت قابل پرداخت نیست.',
            'candidate_changed' => 'وضعیت پرداخت کاندید هنگام تطبیق هم‌زمان تغییر کرد.',
            'invalid_amount' => 'مبلغ واریز نامعتبر یا صفر است.',
            'not_eligible' => 'واریز برای تطبیق خودکار واجد شرایط نیست.',
        ];
        if (isset($map[$code])) {
            return $map[$code];
        }
        if ($state === 'ambiguous') {
            return 'چند پرداخت هم‌مبلغ فعال بود و انتخاب خودکار امن نیست.';
        }
        if ($state === 'late') {
            return 'مبلغ واریز به یک پرداخت غیرفعال یا منقضی نزدیک است.';
        }
        return 'مبلغ یا زمان واریز با هیچ پرداخت فعالی تطبیق خودکار پیدا نکرد.';
    }
}

if (!function_exists('rx_sbv_report_candidates')) {
    function rx_sbv_report_candidates(int $depositId, int $limit = 8, int $page = 0, ?string $orderId = null): array
    {
        $deposit = rx_sbv_deposit($depositId);
        $pdo = rx_sbv_pdo();
        if (!is_array($deposit) || $pdo === null || $deposit['amount_rial'] === null) {
            return ['ok' => false, 'rows' => [], 'active' => [], 'expired' => [], 'has_more' => false];
        }
        $orderId = $orderId !== null ? trim($orderId) : '';
        $amountRial = (int) $deposit['amount_rial'];
        if ($amountRial <= 0) {
            return ['ok' => true, 'rows' => [], 'active' => [], 'expired' => [], 'has_more' => false];
        }
        $amountToman = intdiv($amountRial, 10);
        $storedAt = (int) $deposit['stored_at'];
        $eventAt = $storedAt;
        $receivedMs = $deposit['received_at_ms'] !== null ? (int) $deposit['received_at_ms'] : 0;
        if ($receivedMs > 0) {
            $eventSec = intdiv($receivedMs, 1000);
            if ($eventSec > 0 && $eventSec <= $storedAt + RX_SBV_DEVICE_SKEW && $eventSec >= $storedAt - 604800) {
                $eventAt = $eventSec;
            }
        }
        $rangeToman = rx_sbv_suggestion_range_toman();
        $rangeRial = $rangeToman * 10;
        $principalLow = max(0, $amountToman - $rangeToman);
        $principalHigh = $amountToman + $rangeToman;
        $payableLow = max(0, $amountRial - $rangeRial);
        $payableHigh = $amountRial + $rangeRial;
        $from = $eventAt - 86400;
        $to = $eventAt;
        $limit = max(1, min(30, $limit));
        $page = max(0, $page);
        $now = time();
        try {
            $base = " FROM card_sms_payment csp
                   JOIN Payment_report pr ON pr.id_order = csp.id_order
                   LEFT JOIN user u ON u.id = csp.id_user
                  WHERE csp.deposit_id IS NULL
                    AND csp.state IN ('open', 'review', 'closed')
                    AND (csp.state <> 'closed' OR csp.resolution IS NULL OR csp.resolution = '' OR csp.resolution = 'expired')
                    AND pr.payment_Status IN ('Unpaid', 'waiting', 'pending', 'expire')
                    AND COALESCE(pr.direct_payment_done, 0) = 0
                    AND csp.created_at BETWEEN ? AND ?
                    AND (csp.principal_toman BETWEEN ? AND ? OR csp.payable_rial BETWEEN ? AND ?)
                    AND NOT EXISTS (SELECT 1 FROM payment_fulfillment pf WHERE pf.id_order = csp.id_order AND (pf.state = 'completed' OR (pf.state IN ('claimed', 'applying', 'reconciling') AND COALESCE(pf.lease_expires_at, 0) > ?)))";
            if ($orderId !== '') {
                $base .= " AND csp.id_order = ?";
            }
            $params = [$from, $to, $principalLow, $principalHigh, $payableLow, $payableHigh, $now];
            if ($orderId !== '') {
                $params[] = $orderId;
            }
            $count = $pdo->prepare('SELECT COUNT(*)' . $base);
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            $pages = max(1, (int) ceil($total / $limit));
            $page = min($page, $pages - 1);
            $expiredRank = "CASE WHEN pr.payment_Status = 'expire' OR (csp.state = 'closed' AND csp.resolution = 'expired') OR (csp.state IN ('open', 'review', 'matched') AND csp.expires_at > 0 AND csp.expires_at < " . $now . ") THEN 1 ELSE 0 END";
            $stmt = $pdo->prepare(
                "SELECT csp.*, pr.payment_Status AS report_status, pr.id_invoice AS report_invoice, pr.time AS report_time, pr.source AS report_source, COALESCE(pr.direct_payment_done, 0) AS report_direct_done, u.username, u.namecustom"
                . $base . " ORDER BY " . $expiredRank . " ASC, CASE WHEN csp.principal_toman = ? THEN 0 WHEN csp.payable_rial = ? THEN 1 ELSE 2 END, LEAST(ABS(CAST(csp.principal_toman AS SIGNED) - CAST(? AS SIGNED)), ABS(CAST(csp.payable_rial AS SIGNED) - CAST(? AS SIGNED)) / 10) ASC, ABS(CAST(csp.created_at AS SIGNED) - CAST(? AS SIGNED)) ASC, csp.id DESC LIMIT " . $limit . " OFFSET " . ($page * $limit)
            );
            $stmt->execute(array_merge($params, [$amountToman, $amountRial, $amountToman, $amountRial, $eventAt]));
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasMore = $page + 1 < $pages;
            $active = [];
            $expired = [];
            foreach ($all as $row) {
                $rowExpired = rx_sbv_expired_from_values(
                    (string) ($row['state'] ?? ''),
                    (string) ($row['report_status'] ?? ''),
                    (string) ($row['resolution'] ?? ''),
                    (int) ($row['expires_at'] ?? 0),
                    (int) ($row['report_direct_done'] ?? 0)
                );
                $row['candidate_expired'] = $rowExpired ? 1 : 0;
                if ($rowExpired) {
                    $expired[] = $row;
                } else {
                    $active[] = $row;
                }
            }
            return ['ok' => true, 'rows' => array_merge($active, $expired), 'active' => $active, 'expired' => $expired, 'has_more' => $hasMore, 'total' => $total, 'page' => $page, 'pages' => $pages];
        } catch (Throwable $e) {
            rx_sbv_log('SBV_CANDIDATE_SCAN_FAILED', $e->getMessage(), ['deposit_id' => $depositId]);
            return ['ok' => false, 'rows' => [], 'active' => [], 'expired' => [], 'has_more' => false];
        }
    }
}

if (!function_exists('rx_sbv_order_deposit_candidates')) {
    function rx_sbv_order_deposit_candidates(string $orderId, int $limit = 5, int $page = 0, ?int $depositId = null): array
    {
        $orderId = trim($orderId);
        $per = max(1, min(5, $limit));
        $empty = ['ok' => false, 'rows' => [], 'has_more' => false, 'total' => 0, 'page' => 0, 'pages' => 1, 'per' => $per];
        if ($orderId === '') {
            return $empty;
        }
        $summary = rx_sbv_order_summary($orderId);
        $pdo = rx_sbv_pdo();
        if ($summary === null || $pdo === null) {
            return $empty;
        }
        $guard = rx_sbv_payment_link_guard($orderId);
        if ($guard !== null) {
            $empty['ok'] = true;
            $empty['blocked'] = $guard;
            return $empty;
        }
        $csp = $summary['csp'];
        $rangeToman = rx_sbv_suggestion_range_toman();
        $rangeRial = $rangeToman * 10;
        $payable = (int) $csp['payable_rial'];
        $principal = (int) $csp['principal_toman'];
        $createdAt = (int) $csp['created_at'];
        $eventExpr = "CASE WHEN d.received_at_ms IS NOT NULL AND d.received_at_ms > 0 AND (d.received_at_ms / 1000) <= d.stored_at + " . RX_SBV_DEVICE_SKEW . " AND (d.received_at_ms / 1000) >= d.stored_at - 604800 THEN (d.received_at_ms / 1000) ELSE d.stored_at END";
        $base = " FROM card_sms_deposit d
                  WHERE d.state IN ('unmatched', 'ambiguous', 'late')
                    AND d.is_test = 0
                    AND d.is_deposit = 1
                    AND d.amount_rial IS NOT NULL
                    AND d.amount_rial > 0
                    AND NOT EXISTS (SELECT 1 FROM card_sms_payment csp2 WHERE csp2.deposit_id = d.id)
                    AND (d.amount_rial BETWEEN ? AND ? OR d.amount_rial BETWEEN ? AND ?)
                    AND " . $eventExpr . " BETWEEN ? AND ?";
        if ($depositId !== null) {
            $base .= " AND d.id = ?";
        }
        $params = [
            max(0, $payable - $rangeRial),
            $payable + $rangeRial,
            max(0, ($principal - $rangeToman) * 10),
            ($principal + $rangeToman) * 10,
            $createdAt,
            $createdAt + 86400,
        ];
        if ($depositId !== null) {
            $params[] = $depositId;
        }
        $page = max(0, $page);
        try {
            $count = $pdo->prepare('SELECT COUNT(*)' . $base);
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            $pages = max(1, (int) ceil($total / $per));
            $page = min($page, $pages - 1);
            $stmt = $pdo->prepare(
                "SELECT d.*" . $base . " ORDER BY CASE WHEN d.amount_rial = ? THEN 0 ELSE 1 END, ABS(CAST(d.amount_rial AS SIGNED) - CAST(? AS SIGNED)) ASC, " . $eventExpr . " ASC, d.id DESC LIMIT " . $per . " OFFSET " . ($page * $per)
            );
            $stmt->execute(array_merge($params, [$payable, $payable]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['ok' => true, 'rows' => $rows, 'has_more' => $page + 1 < $pages, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per];
        } catch (Throwable $e) {
            rx_sbv_log('SBV_ORDER_DEPOSIT_SCAN_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return $empty;
        }
    }
}

if (!function_exists('rx_sbv_candidate_line')) {
    function rx_sbv_candidate_line(array $candidate, int $number, ?int $amountRial): string
    {
        $expired = !empty($candidate['candidate_expired']);
        $status = $expired ? 'منقضی‌شده — احتمال ضعیف‌تر؛ فقط بررسی دستی' : rx_sbv_status_label((string) ($candidate['report_status'] ?? ''));
        $name = trim((string) ($candidate['namecustom'] ?? ''));
        if ($name === 'none') {
            $name = '';
        }
        $username = trim((string) ($candidate['username'] ?? ''));
        $display = $name !== '' ? $name : ($username !== '' ? '@' . $username : 'بدون نام');
        $source = (string) ($candidate['report_source'] ?? '') === 'miniapp' ? 'مینی‌اپ' : 'ربات';
        $diff = $amountRial === null ? null : $amountRial - (int) $candidate['payable_rial'];
        $diffLabel = $diff === null ? '—' : (rx_sbv_money(intdiv(abs($diff), 10)) . ' تومان ' . ($diff < 0 ? 'کمتر' : ($diff > 0 ? 'بیشتر' : 'بدون اختلاف')));
        $amountToman = $amountRial === null ? 0 : intdiv($amountRial, 10);
        $explain = '';
        if ($amountRial !== null && (int) $candidate['principal_toman'] === $amountToman && (int) $candidate['payable_rial'] > $amountRial) {
            $explain = 'پرداخت احتمالی با اصل مبلغ برابر است؛ کارمزد شناسایی در واریز لحاظ نشده است.';
        } elseif ($diff !== null && $diff < 0) {
            $explain = 'مبلغ واریز از مبلغ مورد انتظار کمتر است.';
        } elseif ($diff !== null && $diff > 0) {
            $explain = 'مبلغ واریز از مبلغ مورد انتظار بیشتر است.';
        } else {
            $explain = 'مبلغ واریز با مبلغ مورد انتظار برابر است.';
        }
        return "<b>" . $number . ") سفارش <code>" . rx_sbv_h($candidate['id_order']) . "</code></b>\n"
            . "👤 <code>" . rx_sbv_h($candidate['id_user']) . "</code> | " . rx_sbv_h($display) . "\n"
            . "📦 " . rx_sbv_h(rx_sbv_invoice_label((string) ($candidate['report_invoice'] ?? ''))) . " | منبع: " . rx_sbv_h($source) . "\n"
            . "💰 اصل: " . rx_sbv_money($candidate['principal_toman']) . " تومان | کارمزد: " . rx_sbv_money($candidate['fee_toman']) . " تومان\n"
            . "✅ مبلغ مورد انتظار: " . rx_sbv_money(intdiv((int) $candidate['payable_rial'], 10)) . " تومان\n"
            . "📥 مبلغ واریز: " . ($amountRial !== null ? rx_sbv_money($amountToman) . ' تومان' : '—') . "\n"
            . "➖ اختلاف: " . rx_sbv_h($diffLabel) . "\n"
            . "📅 ایجاد: " . rx_sbv_h(rx_sbv_clock((int) $candidate['created_at'], true)) . " | مهلت: " . rx_sbv_h(rx_sbv_clock((int) $candidate['expires_at'], true)) . "\n"
            . "📌 وضعیت: " . rx_sbv_h($status) . "\n"
            . "💡 " . rx_sbv_h($explain);
    }
}

if (!function_exists('rx_sbv_report_unmatched_deposit')) {
    function rx_sbv_report_unmatched_deposit(int $depositId): bool
    {
        $deposit = rx_sbv_deposit($depositId);
        $pdo = rx_sbv_pdo();
        if (!is_array($deposit) || $pdo === null || !in_array((string) $deposit['state'], ['unmatched', 'ambiguous', 'late'], true)) {
            return false;
        }
        try {
            $marker = rx_sbv_report_marker_fetch($pdo, 'deposit_review_report', $depositId, null);
        } catch (Throwable $e) {
            return false;
        }
        if (is_array($marker)) {
            $markerData = json_decode((string) ($marker['reason'] ?? ''), true);
            $markerData = is_array($markerData) ? $markerData : [];
            $markerState = (string) ($marker['outcome'] ?? ($markerData['state'] ?? ''));
            if ($markerState === 'sent') {
                return true;
            }
            if ($markerState !== 'failed') {
                return false;
            }
        }
        $lockName = 'rx_sbv_report_' . $depositId;
        $locked = false;
        $text = null;
        $keyboard = null;
        try {
            $locked = (int) $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 0)")->fetchColumn() === 1;
        } catch (Throwable $e) {
            $locked = false;
        }
        if (!$locked) {
            return false;
        }
        try {
            $freshDeposit = rx_sbv_deposit($depositId);
            if (!is_array($freshDeposit) || !in_array((string) $freshDeposit['state'], ['unmatched', 'ambiguous', 'late'], true)) {
                return false;
            }
            $deposit = $freshDeposit;
            $candidateResult = rx_sbv_report_candidates($depositId, 30);
            $activeCandidates = (array) ($candidateResult['active'] ?? []);
            $expiredCandidates = (array) ($candidateResult['expired'] ?? []);
            $candidateSearchOk = !empty($candidateResult['ok']);
            $candidateHasMore = !empty($candidateResult['has_more']) || count($activeCandidates) > 3 || count($expiredCandidates) > 2;
            $amountRial = $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null;
            $text = "⚠️ <b>واریز نیازمند بررسی ادمین</b>\n\n"
                . "<blockquote>🆔 شناسه واریز: <code>" . (int) $deposit['id'] . "</code>\n"
                . "💵 مبلغ دریافتی: " . rx_sbv_h(rx_sbv_toman_from_rial($amountRial)) . "\n"
                . "🏦 بانک: " . rx_sbv_h($deposit['bank'] ?? '—') . "\n"
                . "🕒 زمان دریافت: " . rx_sbv_h((string) ($deposit['received_at_raw'] ?? '—')) . "\n"
                . "🕒 ثبت در سرور: " . rx_sbv_h(rx_sbv_clock((int) $deposit['stored_at'], true)) . "\n"
                . "👤 صاحب واریز: هنوز مشخص نیست\n"
                . "📌 وضعیت: منتظر بررسی ادمین\n"
                . "🧩 دلیل عدم تطبیق خودکار: " . rx_sbv_h(rx_sbv_match_failure_label((string) $deposit['state'], (string) ($deposit['note'] ?? ''))) . "</blockquote>\n";
            if (!$candidateSearchOk) {
                $text .= "\n🔍 جست‌وجوی پیشنهاد پرداخت با خطا مواجه شد. این واریز به هیچ کاربری نسبت داده نمی‌شود و بررسی دستی لازم است.";
            } elseif ($activeCandidates || $expiredCandidates) {
                $text .= "\n🔍 <b>پرداخت‌های احتمالی — صاحب واریز هنوز تأیید نشده است</b>\n";
                $shown = 0;
                if ($activeCandidates) {
                    $text .= "\n<b>پرداخت‌های فعال / در انتظار</b>\n";
                    foreach (array_slice($activeCandidates, 0, 3) as $candidate) {
                        $shown++;
                        $text .= "\n" . rx_sbv_candidate_line($candidate, $shown, $amountRial) . "\n";
                    }
                }
                if ($expiredCandidates) {
                    $text .= "\n<b>پرداخت‌های منقضی‌شده — احتمال ضعیف‌تر؛ نیازمند بررسی ادمین</b>\n";
                    foreach (array_slice($expiredCandidates, 0, 2) as $candidate) {
                        $shown++;
                        $text .= "\n" . rx_sbv_candidate_line($candidate, $shown, $amountRial) . "\n";
                    }
                }
                if ($candidateHasMore) {
                    $text .= "\nℹ️ تعداد بیشتری پرداخت احتمالی وجود دارد. از «جزئیات واریز ← پرداخت‌های احتمالی» همه موارد را در پنل خصوصی بررسی کنید.";
                }
                $text .= "\n⚠️ این موارد فقط پیشنهاد هستند و هیچ پرداخت یا کاربری به‌صورت خودکار متصل، تأیید یا رد نمی‌شود.";
            } else {
                $text .= "\n🔍 جست‌وجوی پیشنهاد انجام شد اما هیچ پرداخت فعال یا منقضی‌شده‌ای با مبلغ و زمان نزدیک پیدا نشد.";
            }
            $text .= "\n\n📌 تصمیم نهایی فقط با بررسی ادمین ثبت می‌شود.";
            $keyboard = json_encode(['inline_keyboard' => [
                [['text' => '🔗 اتصال به پرداخت', 'callback_data' => 'sbv_dl_' . (int) $deposit['id']]],
                [['text' => '🔍 پرداخت‌های احتمالی', 'callback_data' => 'sbv_ds_' . (int) $deposit['id']]],
                [['text' => '🗑 حذف واریز', 'callback_data' => 'sbv_dd_' . (int) $deposit['id']]],
                [['text' => '🔎 جزئیات واریز', 'callback_data' => 'sbv_d_' . (int) $deposit['id']]],
                [['text' => '📥 صف بررسی', 'callback_data' => 'sbv_q_0']],
            ]], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_REVIEW_REPORT_FAILED', $e->getMessage(), ['deposit_id' => $depositId]);
            $text = null;
            $keyboard = null;
        } finally {
            try {
                $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
            } catch (Throwable $e) {
            }
        }
        if ($text === null) {
            return false;
        }
        $delivery = rx_sbv_deliver_tracked_report('deposit_review_report', $depositId, null, 'system', $text, $keyboard, [
            'received_rial' => $amountRial,
            'deposit_state' => (string) $deposit['state'],
            'reason' => (string) ($deposit['note'] ?? ''),
        ]);
        return (string) ($delivery['state'] ?? '') === 'sent';
    }
}

if (!function_exists('rx_sbv_after_external_confirm')) {
    function rx_sbv_after_external_confirm(string $orderId, string $actor, string $reason): void
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null) {
            return;
        }
        $resolution = 'admin_approved';
        if ($csp['received_rial'] === null) {
            $resolution = 'admin_unverified';
        } elseif ((int) $csp['received_rial'] < (int) $csp['payable_rial']) {
            $resolution = 'admin_shortfall';
        }
        $outcome = rx_sbv_fulfillment_outcome($orderId);
        $special = is_array($outcome) && in_array($outcome['outcome'], ['refunded', 'wallet_credit'], true) ? (string) $outcome['outcome'] : null;
        if ($special !== null) {
            $resolution .= $special === 'refunded' ? '_refund' : '_wallet_credit';
        }
        rx_sbv_finalize($orderId, $special !== null ? 'credited' : 'approved', $resolution, $actor !== '' ? $actor : null, $reason !== '' ? $reason : null);
        rx_sbv_audit($orderId, $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null, $actor !== '' ? $actor : null, 'admin_approve', [
            'principal_toman' => (int) $csp['principal_toman'],
            'fee_toman' => (int) $csp['fee_toman'],
            'expected_rial' => (int) $csp['payable_rial'],
            'received_rial' => $csp['received_rial'] !== null ? (int) $csp['received_rial'] : null,
            'credit_toman' => $special !== null ? (int) ($outcome['amount'] ?? 0) : (int) $csp['principal_toman'],
            'outcome' => $resolution,
            'reason' => $reason !== '' ? $reason : null,
        ]);
    }
}

if (!function_exists('rx_sbv_order_summary')) {
    function rx_sbv_order_summary(string $orderId): ?array
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null) {
            return null;
        }
        $pr = rx_sbv_payment_report($orderId);
        $dep = $csp['deposit_id'] !== null ? rx_sbv_deposit((int) $csp['deposit_id']) : null;
        $received = $csp['received_rial'] !== null ? (int) $csp['received_rial'] : ($dep !== null && $dep['amount_rial'] !== null ? (int) $dep['amount_rial'] : null);
        return [
            'csp' => $csp,
            'report' => $pr,
            'deposit' => $dep,
            'received_rial' => $received,
            'expected_rial' => (int) $csp['payable_rial'],
            'diff_rial' => $received === null ? null : $received - (int) $csp['payable_rial'],
        ];
    }
}

if (!function_exists('rx_sbv_admin_approve')) {
    function rx_sbv_admin_approve(string $orderId, string $actor, string $reason, string $image = 'images.jpg'): array
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null) {
            return ['ok' => false, 'reason' => 'not_verify'];
        }
        if (in_array((string) $csp['state'], ['approved', 'credited'], true)) {
            return ['ok' => false, 'reason' => 'already_paid'];
        }
        $guard = rx_sbv_confirm_guard($orderId, $reason);
        if ($guard !== null) {
            return ['ok' => false, 'reason' => $guard];
        }
        $before = rx_sbv_payment_report($orderId);
        if ($before === null) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        $expiredPayment = rx_sbv_payment_is_expired($csp, $before);
        if ((string) $csp['state'] === 'closed' && !$expiredPayment) {
            return ['ok' => false, 'reason' => 'not_eligible'];
        }
        $status = (string) $before['payment_Status'];
        if ($status === 'waiting' && function_exists('rxReceiptConfirm')) {
            $res = rxReceiptConfirm($orderId, ['actor_id' => $actor, 'actor_label' => $actor, 'override_reason' => $reason]);
            if (empty($res['ok'])) {
                return ['ok' => false, 'reason' => (string) ($res['reason'] ?? 'error')];
            }
            $outcome = rx_sbv_fulfillment_outcome($orderId);
            return ['ok' => true, 'outcome' => is_array($outcome) ? (string) ($outcome['outcome'] ?? '') : ''];
        }
        if (!in_array($status, ['Unpaid', 'expire'], true)) {
            return ['ok' => false, 'reason' => $status === 'paid' ? 'already_paid' : 'not_eligible'];
        }
        $claim = rx_pf_claim($orderId, ['from' => ['Unpaid', 'expire'], 'to' => 'processing']);
        $claimStatus = (string) ($claim['status'] ?? 'error');
        if ($claimStatus === 'already_completed') {
            return ['ok' => false, 'reason' => 'already_paid'];
        }
        if ($claimStatus !== 'claimed') {
            return ['ok' => false, 'reason' => 'claim_' . $claimStatus];
        }
        $run = rx_sbv_run_claimed($orderId, (string) $claim['token'], $image, rx_sbv_release_status($claim['report'] ?? $before, $status));
        if (empty($run['finalized'])) {
            return ['ok' => false, 'reason' => 'fulfillment_' . (string) $run['settle']];
        }
        $resolution = $csp['received_rial'] === null ? 'admin_unverified' : ((int) $csp['received_rial'] < (int) $csp['payable_rial'] ? 'admin_shortfall' : 'admin_approved');
        rx_sbv_after_approval($orderId, $resolution, $actor, $reason !== '' ? $reason : null, true);
        $outcome = rx_sbv_fulfillment_outcome($orderId);
        return ['ok' => true, 'outcome' => is_array($outcome) ? (string) ($outcome['outcome'] ?? '') : ''];
    }
}

if (!function_exists('rx_sbv_admin_set_received')) {
    function rx_sbv_admin_set_received(string $orderId, string $actor, int $receivedToman, string $reason = ''): array
    {
        if ($receivedToman < 0 || $receivedToman > 100000000000) {
            return ['ok' => false, 'reason' => 'invalid_amount'];
        }
        if (trim($reason) === '') {
            return ['ok' => false, 'reason' => 'reason_required'];
        }
        try {
            $out = rx_sbv_tx(function (PDO $pdo) use ($orderId, $actor, $receivedToman, $reason) {
                $now = time();
                $c = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $c->execute([$orderId]);
                $csp = $c->fetch(PDO::FETCH_ASSOC);
                if ($csp === false) {
                    return ['ok' => false, 'reason' => 'not_verify'];
                }
                $p = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $p->execute([$orderId]);
                $report = $p->fetch(PDO::FETCH_ASSOC);
                if ($report === false) {
                    return ['ok' => false, 'reason' => 'not_found'];
                }
                $status = (string) ($report['payment_Status'] ?? '');
                if ($status === 'paid' || (int) ($report['direct_payment_done'] ?? 0) === 1 || in_array((string) $csp['state'], ['approved', 'credited'], true)) {
                    return ['ok' => false, 'reason' => 'already_paid'];
                }
                if (in_array($status, ['processing', 'reconciling'], true)) {
                    return ['ok' => false, 'reason' => 'processing'];
                }
                if (!in_array($status, ['Unpaid', 'waiting', 'expire'], true)) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                if (in_array((string) $csp['state'], ['matched', 'void'], true)) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                $upd = $pdo->prepare("UPDATE card_sms_payment SET received_rial = ?, updated_at = ? WHERE id = ? AND state NOT IN ('approved', 'credited', 'matched', 'void')");
                $upd->execute([$receivedToman * 10, $now, (int) $csp['id']]);
                if ($upd->rowCount() !== 1) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                $pdo->prepare("INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, 'manual_received_correction', ?, ?, ?, ?, NULL, 'corrected', ?, ?)")
                    ->execute([
                        $orderId,
                        $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null,
                        $actor,
                        (int) $csp['principal_toman'],
                        (int) $csp['fee_toman'],
                        (int) $csp['payable_rial'],
                        $receivedToman * 10,
                        $reason,
                        $now,
                    ]);
                return ['ok' => true, 'previous_rial' => $csp['received_rial'] !== null ? (int) $csp['received_rial'] : null, 'received_rial' => $receivedToman * 10];
            });
        } catch (Throwable $e) {
            rx_sbv_log('SBV_RECEIVED_CORRECTION_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        return $out;
    }
}

if (!function_exists('rx_sbv_admin_credit')) {
    function rx_sbv_admin_credit(string $orderId, string $actor, string $reason): array
    {
        if (trim($reason) === '') {
            return ['ok' => false, 'reason' => 'reason_required'];
        }
        $summary = rx_sbv_order_summary($orderId);
        if ($summary === null) {
            return ['ok' => false, 'reason' => 'not_verify'];
        }
        $csp = $summary['csp'];
        if (in_array((string) $csp['state'], ['approved', 'credited'], true)) {
            return ['ok' => false, 'reason' => 'already_paid'];
        }
        if ($summary['received_rial'] === null) {
            return ['ok' => false, 'reason' => 'received_required'];
        }
        $receivedToman = intdiv((int) $summary['received_rial'], 10);
        $credit = $receivedToman;
        if ($credit <= 0) {
            return ['ok' => false, 'reason' => 'nothing_to_credit'];
        }
        $report = $summary['report'];
        if (!is_array($report)) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        $status = (string) $report['payment_Status'];
        $from = ['Unpaid', 'waiting', 'expire', 'cancelled', 'reject'];
        if (!in_array($status, $from, true)) {
            return ['ok' => false, 'reason' => $status === 'paid' ? 'already_paid' : 'not_eligible'];
        }
        $claim = rx_pf_claim($orderId, ['from' => $from, 'to' => 'processing']);
        $claimStatus = (string) ($claim['status'] ?? 'error');
        if ($claimStatus === 'already_completed') {
            return ['ok' => false, 'reason' => 'already_paid'];
        }
        if ($claimStatus !== 'claimed') {
            return ['ok' => false, 'reason' => 'claim_' . $claimStatus];
        }
        $token = (string) $claim['token'];
        $outcome = rx_pf_wallet_credit($orderId, $token, (string) $report['id_user'], $credit, 'topup_card', 'تأیید دستی کارت‌به‌کارت با بررسی (' . $orderId . ')');
        if ($outcome !== 'credited') {
            rx_pf_settle($orderId, $token, rx_pf_result('retryable', 'sbv_credit_' . $outcome), rx_sbv_release_status($report, $status), rx_sbv_release_status($report, $status));
            return ['ok' => false, 'reason' => 'credit_' . $outcome];
        }
        $pdo = rx_sbv_pdo();
        try {
            if ($pdo->query("SHOW TABLES LIKE 'checkout_order'")->fetchColumn() !== false) {
                $pdo->prepare("UPDATE checkout_order SET status = 'cancelled', fulfillment_state = 'sbv_credit', updated_at = ? WHERE payment_order_id = ? AND status IN ('awaiting_payment', 'confirmed', 'awaiting_choice') AND (settle_order_id IS NULL OR settle_order_id = '')")
                    ->execute([time(), $orderId]);
            }
        } catch (Throwable $e) {
            rx_sbv_log('SBV_CHECKOUT_RELEASE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
        rx_sbv_finalize($orderId, 'credited', 'admin_credit', $actor, $reason);
        rx_sbv_audit($orderId, $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null, $actor, 'admin_credit', [
            'principal_toman' => (int) $csp['principal_toman'],
            'fee_toman' => (int) $csp['fee_toman'],
            'expected_rial' => (int) $csp['payable_rial'],
            'received_rial' => (int) $summary['received_rial'],
            'credit_toman' => $credit,
            'outcome' => 'wallet_credit',
            'reason' => $reason,
        ]);
        if (function_exists('sendmessage')) {
            sendmessage($report['id_user'], "💎 کاربر عزیز، پرداخت کارت‌به‌کارت شما با کد پیگیری <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code> بررسی شد و کل مبلغ دریافتی یعنی " . rx_sbv_money($credit) . " تومان به کیف پول شما اضافه گردید.\nℹ️ این یک تصمیم دستی ادمین است و کارمزد شناسایی از مبلغ واریز کسر نشده است.", null, 'HTML');
        }
        $text = "✅ پرداخت با بررسی به‌صورت شارژ کیف پول تسویه شد.\n\n"
            . "<blockquote>کد پیگیری : <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n"
            . "<blockquote>👤 کاربر : <code>" . htmlspecialchars((string) $report['id_user'], ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n"
            . "<blockquote>📥 مبلغ دریافتی : " . rx_sbv_money($receivedToman) . " تومان</blockquote>\n"
            . "<blockquote>🏷 کارمزد شناسایی : " . rx_sbv_money($csp['fee_toman']) . " تومان (در این تصمیم دستی کسر نشد)</blockquote>\n"
            . "<blockquote>💼 واریز کامل به کیف پول : " . rx_sbv_money($credit) . " تومان</blockquote>\n"
            . "<blockquote>✍️ دلیل : " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
            . "<blockquote>👤 ادمین : <code>" . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . "</code></blockquote>";
        $fresh = rx_sbv_payment_report($orderId);
        $delivered = is_array($fresh) && ((int) ($fresh['report_message_id'] ?? 0) > 0 || trim((string) ($fresh['private_receipt_targets'] ?? '')) !== '');
        if ($delivered && function_exists('rxReceiptSyncTelegram')) {
            rxReceiptSyncTelegram($fresh, $text, json_encode(['inline_keyboard' => [[['text' => '⚙️ مدیریت کاربر', 'callback_data' => 'manageuser_' . $report['id_user']]]]], JSON_UNESCAPED_UNICODE));
        } else {
            rx_sbv_report($text);
        }
        return ['ok' => true, 'credit' => $credit];
    }
}

if (!function_exists('rx_sbv_unlink_deposit')) {
    function rx_sbv_unlink_deposit(string $orderId, string $note): void
    {
        try {
            rx_sbv_tx(function (PDO $pdo) use ($orderId, $note) {
                $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $s->execute([$orderId]);
                $csp = $s->fetch(PDO::FETCH_ASSOC);
                if ($csp === false || $csp['deposit_id'] === null || in_array((string) $csp['state'], ['approved', 'credited'], true)) {
                    return;
                }
                $now = time();
                $pdo->prepare("UPDATE card_sms_deposit SET state = 'unmatched', note = ?, id_order = NULL, updated_at = ? WHERE id = ? AND state IN ('matched', 'linked')")
                    ->execute([$note, $now, (int) $csp['deposit_id']]);
                $pdo->prepare("UPDATE card_sms_payment SET deposit_id = NULL, state = IF(state = 'matched', 'open', state), updated_at = ? WHERE id = ?")
                    ->execute([$now, (int) $csp['id']]);
            });
        } catch (Throwable $e) {
            rx_sbv_log('SBV_UNLINK_FAILED', $e->getMessage(), ['id_order' => $orderId]);
        }
    }
}

if (!function_exists('rx_sbv_identity_lock')) {
    function rx_sbv_identity_lock(string $eventId, ?string $fingerprint): array
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null || $eventId === '') {
            throw new RuntimeException('identity_storage_unavailable');
        }
        $names = ['rx_sbv_id_' . substr(hash('sha256', 'event:' . $eventId), 0, 48)];
        if ($fingerprint !== null && $fingerprint !== '') {
            $names[] = 'rx_sbv_id_' . substr(hash('sha256', 'sms:' . $fingerprint), 0, 48);
        }
        sort($names, SORT_STRING);
        $locked = [];
        try {
            foreach ($names as $name) {
                $stmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
                $stmt->execute([$name]);
                if ((int) $stmt->fetchColumn() !== 1) {
                    throw new RuntimeException('identity_lock_busy');
                }
                $locked[] = $name;
            }
        } catch (Throwable $e) {
            rx_sbv_identity_unlock($locked);
            throw $e;
        }
        return $locked;
    }
}

if (!function_exists('rx_sbv_identity_unlock')) {
    function rx_sbv_identity_unlock(array $names): void
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return;
        }
        foreach (array_reverse($names) as $name) {
            try {
                $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->execute([$name]);
            } catch (Throwable $e) {
                rx_sbv_log('SBV_IDENTITY_UNLOCK_FAILED', $e->getMessage());
            }
        }
    }
}

if (!function_exists('rx_sbv_deleted_identity_exists')) {
    function rx_sbv_deleted_identity_exists(string $eventId, ?string $fingerprint): bool
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null || $eventId === '') {
            throw new RuntimeException('identity_storage_unavailable');
        }
        try {
            if ($fingerprint !== null && $fingerprint !== '') {
                $stmt = $pdo->prepare("SELECT id FROM card_sms_audit WHERE action = 'deposit_deleted' AND (id_order = ? OR reason LIKE ?) LIMIT 1");
                $stmt->execute([$eventId, '%"fingerprint":"' . $fingerprint . '"%']);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM card_sms_audit WHERE action = 'deposit_deleted' AND id_order = ? LIMIT 1");
                $stmt->execute([$eventId]);
            }
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            rx_sbv_log('SBV_DELETED_IDENTITY_READ_FAILED', $e->getMessage());
            throw $e;
        }
    }
}

if (!function_exists('rx_sbv_delete_deposit')) {
    function rx_sbv_delete_deposit(int $depositId, string $actor): array
    {
        if ($depositId <= 0) {
            return ['ok' => false, 'reason' => 'deposit_not_found'];
        }
        $identityLocks = [];
        try {
            $identityDeposit = rx_sbv_deposit($depositId);
            if ($identityDeposit === null) {
                return ['ok' => false, 'reason' => 'deposit_not_found'];
            }
            $identityLocks = rx_sbv_identity_lock((string) $identityDeposit['event_id'], $identityDeposit['fingerprint'] !== null ? (string) $identityDeposit['fingerprint'] : null);
            $out = rx_sbv_tx(function (PDO $pdo) use ($depositId, $actor) {
                $now = time();
                $d = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1 FOR UPDATE");
                $d->execute([$depositId]);
                $deposit = $d->fetch(PDO::FETCH_ASSOC);
                if ($deposit === false) {
                    return ['ok' => false, 'reason' => 'deposit_not_found'];
                }
                if ((int) $deposit['is_test'] === 1 || (int) $deposit['is_deposit'] !== 1) {
                    return ['ok' => false, 'reason' => 'deposit_not_eligible'];
                }
                if (!in_array((string) $deposit['state'], ['unmatched', 'ambiguous', 'late'], true)) {
                    return ['ok' => false, 'reason' => 'deposit_state_' . (string) $deposit['state']];
                }
                $linkedOrder = trim((string) ($deposit['id_order'] ?? ''));
                if ($linkedOrder !== '') {
                    return ['ok' => false, 'reason' => 'deposit_linked'];
                }
                $c = $pdo->prepare("SELECT id_order FROM card_sms_payment WHERE deposit_id = ? LIMIT 1 FOR UPDATE");
                $c->execute([$depositId]);
                if ($c->fetchColumn() !== false) {
                    return ['ok' => false, 'reason' => 'deposit_linked'];
                }
                $eventId = (string) ($deposit['event_id'] ?? '');
                $fingerprint = $deposit['fingerprint'] !== null ? (string) $deposit['fingerprint'] : null;
                $reason = json_encode([
                    'event_id' => $eventId,
                    'fingerprint' => $fingerprint,
                    'deleted_at' => $now,
                    'admin_id' => $actor,
                    'amount_rial' => $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null,
                    'bank' => (string) ($deposit['bank'] ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $ins = $pdo->prepare("INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, 'deposit_deleted', NULL, NULL, NULL, ?, NULL, 'deleted', ?, ?)");
                $ins->execute([
                    $eventId,
                    $depositId,
                    $actor,
                    $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null,
                    is_string($reason) ? $reason : null,
                    $now,
                ]);
                $del = $pdo->prepare("DELETE FROM card_sms_deposit WHERE id = ? AND state IN ('unmatched', 'ambiguous', 'late') AND (id_order IS NULL OR id_order = '')");
                $del->execute([$depositId]);
                if ($del->rowCount() !== 1) {
                    throw new RuntimeException('sbv_delete:state_changed');
                }
                return ['ok' => true, 'deposit_id' => $depositId];
            });
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (strpos($message, 'sbv_delete:') === 0) {
                return ['ok' => false, 'reason' => substr($message, 11)];
            }
            rx_sbv_log('SBV_DEPOSIT_DELETE_FAILED', $message, ['deposit_id' => $depositId]);
            return ['ok' => false, 'reason' => 'db_error'];
        } finally {
            rx_sbv_identity_unlock($identityLocks);
        }
        return $out;
    }
}

if (!function_exists('rx_sbv_expired_payment_delete_guard')) {
    function rx_sbv_expired_payment_delete_guard(string $orderId, ?array $csp = null, ?array $report = null, ?int $depositId = null): ?string
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return 'invalid_order';
        }
        $csp = $csp ?? rx_sbv_get($orderId);
        if (!is_array($csp)) {
            return 'not_found';
        }
        $report = $report ?? rx_sbv_payment_report($orderId);
        if (!is_array($report)) {
            return 'not_found';
        }
        $status = (string) ($report['payment_Status'] ?? '');
        if ($status === 'paid' || (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return 'already_paid';
        }
        if (in_array($status, ['reject', 'cancelled'], true)) {
            return 'already_final';
        }
        if (!in_array($status, ['Unpaid', 'waiting', 'expire'], true)) {
            return 'not_eligible';
        }
        $state = (string) ($csp['state'] ?? '');
        if (in_array($state, ['approved', 'credited'], true)) {
            return 'already_paid';
        }
        if ($csp['deposit_id'] !== null) {
            return 'order_has_deposit';
        }
        if (!rx_sbv_payment_is_expired($csp, $report)) {
            return 'not_expired';
        }
        if ($depositId !== null) {
            if ($depositId <= 0) {
                return 'deposit_not_found';
            }
            $candidateResult = rx_sbv_report_candidates($depositId, 1, 0, $orderId);
            if (empty($candidateResult['ok'])) {
                return 'candidate_check_failed';
            }
            $candidateFound = false;
            foreach ((array) ($candidateResult['rows'] ?? []) as $candidateRow) {
                if ((string) ($candidateRow['id_order'] ?? '') === $orderId) {
                    $candidateFound = true;
                    break;
                }
            }
            if (!$candidateFound) {
                return 'order_not_candidate';
            }
        }
        if (function_exists('rx_pf_fetch')) {
            try {
                $record = rx_pf_fetch($orderId);
                if (is_array($record)) {
                    $recordState = (string) ($record['state'] ?? '');
                    if ($recordState === 'completed') {
                        return 'already_paid';
                    }
                    if (in_array($recordState, ['claimed', 'applying', 'reconciling'], true) && (int) ($record['lease_expires_at'] ?? 0) > time()) {
                        return 'processing';
                    }
                }
            } catch (Throwable $e) {
            }
        }
        return null;
    }
}

if (!function_exists('rx_sbv_admin_delete_expired_payment')) {
    function rx_sbv_admin_delete_expired_payment(string $orderId, string $actor, ?int $depositId = null): array
    {
        $orderId = trim($orderId);
        $actor = trim($actor);
        if ($orderId === '') {
            return ['ok' => false, 'reason' => 'invalid_order'];
        }
        if ($actor === '') {
            return ['ok' => false, 'reason' => 'not_authorized'];
        }
        try {
            $out = rx_sbv_tx(function (PDO $pdo) use ($orderId, $actor, $depositId) {
                $now = time();
                $fail = function (string $reason) {
                    throw new RuntimeException('sbv_expdel:' . $reason);
                };
                $s = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $s->execute([$orderId]);
                $csp = $s->fetch(PDO::FETCH_ASSOC);
                if (!is_array($csp)) {
                    return ['ok' => false, 'reason' => 'not_found'];
                }
                $r = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $r->execute([$orderId]);
                $report = $r->fetch(PDO::FETCH_ASSOC);
                if (!is_array($report)) {
                    return ['ok' => false, 'reason' => 'not_found'];
                }
                $guard = rx_sbv_expired_payment_delete_guard($orderId, $csp, $report, $depositId);
                if ($guard !== null) {
                    if ($guard === 'already_final' && (string) ($report['payment_Status'] ?? '') === 'cancelled') {
                        return ['ok' => true, 'already_removed' => true, 'order_id' => $orderId, 'user_id' => (string) ($report['id_user'] ?? '')];
                    }
                    return ['ok' => false, 'reason' => $guard];
                }
                try {
                    $f = $pdo->prepare("SELECT state, lease_expires_at FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                    $f->execute([$orderId]);
                    $fulfillment = $f->fetch(PDO::FETCH_ASSOC);
                    if (is_array($fulfillment)) {
                        $fState = (string) ($fulfillment['state'] ?? '');
                        if ($fState === 'completed') {
                            return ['ok' => false, 'reason' => 'already_paid'];
                        }
                        if (in_array($fState, ['claimed', 'applying', 'reconciling'], true) && (int) ($fulfillment['lease_expires_at'] ?? 0) > $now) {
                            return ['ok' => false, 'reason' => 'processing'];
                        }
                    }
                } catch (Throwable $e) {
                    $fail('fulfillment_check_failed');
                }
                $marker = substr('expired_deleted:' . $actor, 0, 190);
                $upd = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'cancelled', dec_not_confirmed = ?, at_updated = ? WHERE id_order = ? AND payment_Status IN ('Unpaid', 'waiting', 'expire') AND COALESCE(direct_payment_done, 0) = 0");
                $upd->execute([$marker, date('Y/m/d H:i:s', $now), $orderId]);
                if ($upd->rowCount() < 1) {
                    $fail('race_lost');
                }
                $cspUpdate = $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = 'cancelled', resolved_by = ?, resolved_at = ?, resolution_note = ?, updated_at = ? WHERE id = ? AND deposit_id IS NULL");
                $cspUpdate->execute([$actor, $now, 'expired_payment_deleted', $now, (int) $csp['id']]);
                if ($cspUpdate->rowCount() !== 1) {
                    $fail('race_lost');
                }
                rx_sbv_release_amount($pdo, $csp, false);
                try {
                    $pdo->prepare("INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, 'admin_delete_expired', ?, ?, ?, ?, NULL, 'cancelled', ?, ?)")
                        ->execute([
                            $orderId,
                            $depositId,
                            $actor,
                            (int) $csp['principal_toman'],
                            (int) $csp['fee_toman'],
                            (int) $csp['payable_rial'],
                            $csp['received_rial'] !== null ? (int) $csp['received_rial'] : null,
                            'expired_payment_deleted',
                            $now,
                        ]);
                } catch (Throwable $e) {
                    $fail('audit_failed');
                }
                return ['ok' => true, 'order_id' => $orderId, 'user_id' => (string) ($report['id_user'] ?? ''), 'deposit_id' => $depositId];
            });
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (strpos($message, 'sbv_expdel:') === 0) {
                return ['ok' => false, 'reason' => substr($message, 11)];
            }
            rx_sbv_log('SBV_EXPIRED_PAYMENT_DELETE_FAILED', $message, ['id_order' => $orderId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        if (empty($out['ok'])) {
            return $out;
        }
        if (function_exists('clearSelectCache')) {
            clearSelectCache('Payment_report');
        }
        if (function_exists('rx_redis_del')) {
            rx_redis_del(rx_payment_status_cache_key($orderId, ($out['user_id'] ?? '')));
        }
        if (function_exists('rx_ck_release_for_order')) {
            try {
                rx_ck_release_for_order($orderId, 'cancelled', 'exp_deleted');
            } catch (Throwable $e) {
            }
        }
        return $out;
    }
}

if (!function_exists('rx_sbv_rejection_audit_row')) {
    function rx_sbv_rejection_audit_row(string $orderId, ?int $depositId): ?array
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_audit WHERE id_order = ? AND deposit_id <=> ? AND action IN ('admin_reject', 'admin_reject_block') ORDER BY id DESC LIMIT 1");
            $stmt->execute([$orderId, $depositId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('rx_sbv_rejection_audit_find')) {
    function rx_sbv_rejection_audit_find(string $orderId, ?int $preferredDepositId = null): ?array
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_sms_audit WHERE id_order = ? AND action IN ('admin_reject', 'admin_reject_block') ORDER BY id DESC LIMIT 10");
            $stmt->execute([$orderId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        $fallback = null;
        foreach ($rows as $row) {
            $depositId = $row['deposit_id'] !== null ? (int) $row['deposit_id'] : null;
            if ($preferredDepositId !== null) {
                if ($depositId === $preferredDepositId) {
                    return $row;
                }
                continue;
            }
            if ($depositId !== null) {
                return $row;
            }
            if ($fallback === null) {
                $fallback = $row;
            }
        }
        return $preferredDepositId === null ? $fallback : null;
    }
}

if (!function_exists('rx_sbv_rejection_report_payload')) {
    function rx_sbv_rejection_report_payload(string $orderId, ?int $depositId, string $actor, string $reason, int $decisionAt): ?array
    {
        $csp = rx_sbv_get($orderId);
        $report = rx_sbv_payment_report($orderId);
        if ($csp === null || !is_array($report)) {
            return null;
        }
        $deposit = $depositId !== null ? rx_sbv_deposit($depositId) : null;
        $receivedRial = $csp['received_rial'] !== null ? (int) $csp['received_rial'] : (is_array($deposit) && $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null);
        $user = function_exists('select') ? select('user', '*', 'id', $report['id_user'], 'select', ['cache' => false]) : null;
        $name = is_array($user) ? trim((string) ($user['first_name'] ?? '')) : '';
        $username = is_array($user) ? trim((string) ($user['username'] ?? '')) : '';
        $text = "❌ <b>رد درخواست پرداخت کارت‌به‌کارت</b>\n\n"
            . "<blockquote>🛒 کد پیگیری: <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code>\n"
            . "👤 کاربر: " . htmlspecialchars($name !== '' ? $name : ($username !== '' ? '@' . $username : 'بدون نام'), ENT_QUOTES, 'UTF-8') . " (<code>" . htmlspecialchars((string) $report['id_user'], ENT_QUOTES, 'UTF-8') . "</code>)\n"
            . "👤 ادمین: <code>" . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . "</code>\n"
            . "🆔 شناسه واریز: " . ($depositId !== null ? '<code>' . $depositId . '</code>' : '—') . "\n"
            . "💰 اصل مبلغ: " . rx_sbv_money($csp['principal_toman']) . " تومان\n"
            . "🏷 کارمزد شناسایی: " . rx_sbv_money($csp['fee_toman']) . " تومان\n"
            . "✅ مبلغ مورد انتظار: " . rx_sbv_money(intdiv((int) $csp['payable_rial'], 10)) . " تومان\n"
            . "📥 مبلغ دریافتی: " . ($receivedRial !== null ? rx_sbv_money(intdiv($receivedRial, 10)) . ' تومان' : 'ثبت نشده') . "\n"
            . "✍️ دلیل: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "\n"
            . "🕒 زمان تصمیم: " . rx_sbv_clock($decisionAt, true) . "\n"
            . "📌 تصمیم: رد درخواست پرداخت؛ رکورد واریز بانکی حفظ شد و وجهی بازگشت یا به کیف پول اضافه نشد.</blockquote>";
        $kbRows = [[['text' => '🔎 مشاهده سفارش', 'callback_data' => 'sbv_oo_' . $orderId]]];
        if ($depositId !== null) {
            $kbRows[] = [['text' => '📨 جزئیات واریز', 'callback_data' => 'sbv_d_' . $depositId]];
        }
        $keyboard = json_encode(['inline_keyboard' => $kbRows], JSON_UNESCAPED_UNICODE);
        return [
            'text' => $text,
            'keyboard' => is_string($keyboard) ? $keyboard : null,
            'meta' => [
                'principal_toman' => (int) $csp['principal_toman'],
                'fee_toman' => (int) $csp['fee_toman'],
                'expected_rial' => (int) $csp['payable_rial'],
                'received_rial' => $receivedRial,
                'credit_toman' => null,
                'customer_id' => (string) $report['id_user'],
                'order_id' => $orderId,
                'deposit_id' => $depositId,
                'admin_id' => $actor,
                'reason' => $reason,
                'decision_at' => $decisionAt,
            ],
        ];
    }
}

if (!function_exists('rx_sbv_send_rejection_report')) {
    function rx_sbv_send_rejection_report(string $orderId, ?int $depositId, string $actor, string $reason, int $decisionAt): array
    {
        try {
            $payload = rx_sbv_rejection_report_payload($orderId, $depositId, $actor, $reason, $decisionAt);
        } catch (Throwable $e) {
            rx_sbv_log('SBV_REJECTION_REPORT_BUILD_FAILED', $e->getMessage(), ['id_order' => $orderId, 'deposit_id' => $depositId]);
            return ['state' => 'failed', 'ok' => false, 'error' => 'rejection_report_build_failed'];
        }
        if ($payload === null) {
            return ['state' => 'failed', 'ok' => false, 'error' => 'rejection_report_data_unavailable'];
        }
        try {
            return rx_sbv_deliver_tracked_report('admin_reject_report', $depositId, $orderId, $actor, (string) $payload['text'], $payload['keyboard'] ?? null, (array) ($payload['meta'] ?? []));
        } catch (Throwable $e) {
            rx_sbv_log('SBV_REJECTION_REPORT_DELIVERY_FAILED', $e->getMessage(), ['id_order' => $orderId, 'deposit_id' => $depositId]);
            return ['state' => 'failed', 'ok' => false, 'error' => 'rejection_report_delivery_failed'];
        }
    }
}

if (!function_exists('rx_sbv_admin_reject')) {
    function rx_sbv_admin_reject(string $orderId, string $actor, string $reason, bool $block, ?int $expectedDepositId = null): array
    {
        if (trim($reason) === '') {
            return ['ok' => false, 'reason' => 'reason_required'];
        }
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        $retryPreferred = $expectedDepositId;
        if ($retryPreferred === null) {
            $cspHead = rx_sbv_get($orderId);
            if (is_array($cspHead) && $cspHead['deposit_id'] !== null) {
                $retryPreferred = (int) $cspHead['deposit_id'];
            }
        }
        $existingAudit = rx_sbv_rejection_audit_find($orderId, $retryPreferred);
        if ($existingAudit !== null) {
            $auditDepositId = $existingAudit['deposit_id'] !== null ? (int) $existingAudit['deposit_id'] : null;
            $retryDepositId = $auditDepositId !== null ? $auditDepositId : $retryPreferred;
            $existingReport = rx_sbv_payment_report($orderId);
            if (is_array($existingReport) && (string) ($existingReport['payment_Status'] ?? '') === 'reject') {
                $storedReason = trim((string) ($existingAudit['reason'] ?? ''));
                $storedActor = trim((string) ($existingAudit['actor'] ?? ''));
                $decisionAt = (int) ($existingAudit['created_at'] ?? time());
                if ($retryDepositId !== null) {
                    try {
                        $pdo->prepare("UPDATE card_sms_deposit SET state = 'reviewed_rejected', id_order = ?, note = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND state IN ('unmatched', 'ambiguous', 'late', 'linked', 'matched') AND (id_order IS NULL OR id_order = '' OR id_order = ?)")
                            ->execute([$orderId, $storedReason !== '' ? $storedReason : $reason, $storedActor !== '' ? $storedActor : $actor, $decisionAt, time(), $retryDepositId, $orderId]);
                        $auditReceived = $existingAudit['received_rial'] !== null ? (int) $existingAudit['received_rial'] : null;
                        $pdo->prepare("UPDATE card_sms_payment SET deposit_id = ?, received_rial = COALESCE(received_rial, ?), updated_at = ? WHERE id_order = ? AND (deposit_id IS NULL OR deposit_id = ?)")
                            ->execute([$retryDepositId, $auditReceived, time(), $orderId, $retryDepositId]);
                    } catch (Throwable $e) {
                        rx_sbv_log('SBV_REJECT_DEPOSIT_RECONCILE_FAILED', $e->getMessage(), ['id_order' => $orderId, 'deposit_id' => $retryDepositId]);
                    }
                }
                $delivery = rx_sbv_send_rejection_report($orderId, $retryDepositId, $storedActor !== '' ? $storedActor : $actor, $storedReason !== '' ? $storedReason : $reason, $decisionAt);
                return [
                    'ok' => true,
                    'already_rejected' => true,
                    'blocked' => false,
                    'notified' => false,
                    'deposit_id' => $retryDepositId,
                    'report_state' => (string) ($delivery['state'] ?? 'failed'),
                    'report' => $delivery,
                ];
            }
        }
        $guard = rx_sbv_payment_reject_guard($orderId);
        if ($guard !== null) {
            return ['ok' => false, 'reason' => $guard];
        }
        $depositHint = $expectedDepositId;
        if ($depositHint === null) {
            $hintCsp = rx_sbv_get($orderId);
            if (is_array($hintCsp) && $hintCsp['deposit_id'] !== null) {
                $depositHint = (int) $hintCsp['deposit_id'];
            }
        }
        $now = time();
        $blocked = false;
        try {
            $out = rx_sbv_tx(function (PDO $pdo) use ($orderId, $actor, $reason, $block, $expectedDepositId, $depositHint, $now, &$blocked) {
                $fail = function (string $reason) {
                    throw new RuntimeException('sbv_reject:' . $reason);
                };
                $deposit = null;
                $depositId = $expectedDepositId !== null ? $expectedDepositId : $depositHint;
                $depositLoaded = false;
                $loadDeposit = function () use ($pdo, &$deposit, &$depositId, &$depositLoaded, $orderId, $fail) {
                    if ($depositId === null) {
                        return;
                    }
                    if (!$depositLoaded) {
                        $d = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1 FOR UPDATE");
                        $d->execute([$depositId]);
                        $deposit = $d->fetch(PDO::FETCH_ASSOC);
                        $depositLoaded = true;
                        if ($deposit === false) {
                            $fail('deposit_not_found');
                        }
                    }
                    $depositState = (string) $deposit['state'];
                    if ((int) $deposit['is_test'] === 1 || (int) $deposit['is_deposit'] !== 1 || $deposit['amount_rial'] === null) {
                        $fail('deposit_not_eligible');
                    }
                    if (!in_array($depositState, ['unmatched', 'ambiguous', 'late', 'linked', 'matched'], true)) {
                        $fail('deposit_state_' . $depositState);
                    }
                    $depositOrder = trim((string) ($deposit['id_order'] ?? ''));
                    if ($depositOrder !== '' && $depositOrder !== $orderId) {
                        $fail('deposit_reassigned');
                    }
                    $other = $pdo->prepare("SELECT id_order FROM card_sms_payment WHERE deposit_id = ? AND id_order <> ? LIMIT 1");
                    $other->execute([$depositId, $orderId]);
                    if ($other->fetchColumn() !== false) {
                        $fail('deposit_reassigned');
                    }
                };
                if ($depositId !== null) {
                    $loadDeposit();
                }
                $p = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $p->execute([$orderId]);
                $pr = $p->fetch(PDO::FETCH_ASSOC);
                if ($pr === false) {
                    $fail('not_found');
                }
                $payStatus = (string) ($pr['payment_Status'] ?? '');
                if ($payStatus === 'paid' || (int) ($pr['direct_payment_done'] ?? 0) === 1) {
                    $fail('already_paid');
                }
                if (in_array($payStatus, ['processing', 'reconciling'], true)) {
                    $fail('processing');
                }
                if (in_array($payStatus, ['reject', 'cancelled'], true)) {
                    $fail('already_final');
                }
                if (!in_array($payStatus, ['Unpaid', 'waiting', 'expire'], true)) {
                    $fail('not_eligible');
                }
                $c = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $c->execute([$orderId]);
                $csp = $c->fetch(PDO::FETCH_ASSOC);
                if ($csp === false) {
                    $fail('not_verify');
                }
                $expired = rx_sbv_payment_is_expired($csp, $pr);
                $cspState = (string) $csp['state'];
                if (!in_array($cspState, ['open', 'matched', 'review'], true) && !($cspState === 'closed' && $expired)) {
                    $fail('not_eligible');
                }
                $cspDepositId = $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null;
                if ($expectedDepositId !== null) {
                    if ($cspDepositId !== null && $cspDepositId !== $expectedDepositId) {
                        $fail('order_has_deposit');
                    }
                    $depositId = $expectedDepositId;
                } else {
                    if ($cspDepositId !== $depositHint) {
                        $fail('deposit_state_changed');
                    }
                    $depositId = $cspDepositId;
                }
                try {
                    $f = $pdo->prepare("SELECT state, lease_expires_at FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                    $f->execute([$orderId]);
                    $fulfillment = $f->fetch(PDO::FETCH_ASSOC);
                    if (is_array($fulfillment)) {
                        $fulfillmentState = (string) ($fulfillment['state'] ?? '');
                        if ($fulfillmentState === 'completed') {
                            $fail('already_paid');
                        }
                        if (in_array($fulfillmentState, ['claimed', 'applying', 'reconciling'], true) && (int) ($fulfillment['lease_expires_at'] ?? 0) > $now) {
                            $fail('processing');
                        }
                    }
                } catch (RuntimeException $e) {
                    throw $e;
                } catch (Throwable $e) {
                }
                $stmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject', dec_not_confirmed = ? WHERE id_order = ? AND payment_Status IN ('Unpaid', 'waiting', 'expire') AND COALESCE(direct_payment_done, 0) = 0");
                $stmt->execute([$reason, $orderId]);
                if ($stmt->rowCount() !== 1) {
                    $fail('race_lost');
                }
                $resolution = $block ? 'rejected_blocked' : 'rejected';
                $depositReceivedRial = $csp['received_rial'] !== null ? (int) $csp['received_rial'] : ($depositId !== null && is_array($deposit) && $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null);
                if ($depositId !== null) {
                    $cspUpdate = $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = ?, resolved_by = ?, resolved_at = ?, resolution_note = ?, deposit_id = ?, received_rial = ?, updated_at = ? WHERE id = ?");
                    $cspUpdate->execute([$resolution, $actor, $now, $reason, $depositId, $depositReceivedRial, $now, (int) $csp['id']]);
                } else {
                    $cspUpdate = $pdo->prepare("UPDATE card_sms_payment SET state = 'closed', resolution = ?, resolved_by = ?, resolved_at = ?, resolution_note = ?, deposit_id = NULL, updated_at = ? WHERE id = ?");
                    $cspUpdate->execute([$resolution, $actor, $now, $reason, $now, (int) $csp['id']]);
                }
                if ($cspUpdate->rowCount() !== 1) {
                    $fail('payment_state_changed');
                }
                if ($depositId !== null) {
                    $dst = $pdo->prepare("UPDATE card_sms_deposit SET state = 'reviewed_rejected', id_order = ?, note = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND state IN ('unmatched', 'ambiguous', 'late', 'linked', 'matched') AND (id_order IS NULL OR id_order = '' OR id_order = ?)");
                    $dst->execute([$orderId, $reason, $actor, $now, $now, $depositId, $orderId]);
                    if ($dst->rowCount() !== 1) {
                        $fail('deposit_state_changed');
                    }
                }
                rx_sbv_release_amount($pdo, $csp, false);
                if ($block) {
                    try {
                        $pdo->prepare("UPDATE user SET User_Status = 'block', description_blocking = ? WHERE id = ?")->execute([$reason, (string) $pr['id_user']]);
                        $blocked = true;
                    } catch (Throwable $e) {
                        $blocked = false;
                        rx_sbv_log('SBV_BLOCK_FAILED', $e->getMessage(), ['id_order' => $orderId]);
                    }
                }
                $receivedRial = $depositId !== null ? $depositReceivedRial : ($csp['received_rial'] !== null ? (int) $csp['received_rial'] : (is_array($deposit) && $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null));
                try {
                    $pdo->prepare("INSERT INTO card_sms_audit (id_order, deposit_id, actor, action, principal_toman, fee_toman, expected_rial, received_rial, credit_toman, outcome, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([
                            $orderId,
                            $depositId,
                            $actor,
                            $block ? 'admin_reject_block' : 'admin_reject',
                            (int) $csp['principal_toman'],
                            (int) $csp['fee_toman'],
                            (int) $csp['payable_rial'],
                            $receivedRial,
                            null,
                            $block ? ($blocked ? 'rejected_blocked' : 'rejected_block_failed') : 'rejected',
                            $reason,
                            $now,
                        ]);
                } catch (Throwable $e) {
                    $fail('audit_failed');
                }
                $pr['payment_Status'] = 'reject';
                $pr['dec_not_confirmed'] = $reason;
                return ['ok' => true, 'report' => $pr, 'csp' => $csp, 'deposit_id' => $depositId, 'decision_at' => $now];
            });
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (strpos($message, 'sbv_reject:') === 0) {
                return ['ok' => false, 'reason' => substr($message, 11)];
            }
            rx_sbv_log('SBV_REJECT_FAILED', $message, ['id_order' => $orderId, 'deposit_id' => $expectedDepositId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        if (empty($out['ok']) || !is_array($out['report'] ?? null)) {
            return ['ok' => false, 'reason' => (string) ($out['reason'] ?? 'error')];
        }
        $report = $out['report'];
        $depositId = isset($out['deposit_id']) ? (int) $out['deposit_id'] : null;
        $decisionAt = (int) ($out['decision_at'] ?? $now);
        if (function_exists('clearSelectCache')) {
            clearSelectCache('Payment_report');
            if ($blocked) {
                clearSelectCache('user');
            }
        }
        if (function_exists('rx_redis_del')) {
            rx_redis_del(rx_payment_status_cache_key($orderId, $report['id_user']));
        }
        $notified = false;
        if (function_exists('rxReceiptRejectSideEffects')) {
            try {
                $side = rxReceiptRejectSideEffects($report, $orderId, $reason, $actor, false, true);
                $notified = !empty($side['notified']);
            } catch (Throwable $e) {
                rx_sbv_log('SBV_REJECT_NOTIFY_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            }
        }
        $delivery = rx_sbv_send_rejection_report($orderId, $depositId, $actor, $reason, $decisionAt);
        return [
            'ok' => true,
            'blocked' => $blocked,
            'notified' => $notified,
            'deposit_id' => $depositId,
            'report_state' => (string) ($delivery['state'] ?? 'failed'),
            'report' => $delivery,
        ];
    }
}

if (!function_exists('rx_sbv_link')) {
    function rx_sbv_link(int $depositId, string $orderId, string $actor): array
    {
        try {
            $out = rx_sbv_tx(function (PDO $pdo) use ($depositId, $orderId) {
                $now = time();
                $d = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE id = ? LIMIT 1 FOR UPDATE");
                $d->execute([$depositId]);
                $dep = $d->fetch(PDO::FETCH_ASSOC);
                if ($dep === false) {
                    return ['ok' => false, 'reason' => 'deposit_not_found'];
                }
                if (!in_array((string) $dep['state'], ['unmatched', 'ambiguous', 'late'], true)) {
                    return ['ok' => false, 'reason' => 'deposit_state_' . (string) $dep['state']];
                }
                if ((int) $dep['is_test'] === 1 || (int) $dep['is_deposit'] !== 1 || $dep['amount_rial'] === null) {
                    return ['ok' => false, 'reason' => 'deposit_not_eligible'];
                }
                $p = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $p->execute([$orderId]);
                $pr = $p->fetch(PDO::FETCH_ASSOC);
                if ($pr === false) {
                    return ['ok' => false, 'reason' => 'not_found'];
                }
                $payStatus = (string) ($pr['payment_Status'] ?? '');
                if ($payStatus === 'paid' || (int) ($pr['direct_payment_done'] ?? 0) === 1) {
                    return ['ok' => false, 'reason' => 'already_paid'];
                }
                if (in_array($payStatus, ['processing', 'reconciling'], true)) {
                    return ['ok' => false, 'reason' => 'processing'];
                }
                $c = $pdo->prepare("SELECT * FROM card_sms_payment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                $c->execute([$orderId]);
                $csp = $c->fetch(PDO::FETCH_ASSOC);
                if ($csp === false) {
                    return ['ok' => false, 'reason' => 'order_not_verify'];
                }
                $expired = rx_sbv_payment_is_expired($csp, $pr);
                $cspState = (string) $csp['state'];
                if ($cspState !== 'open' && !($expired && in_array($cspState, ['review', 'closed'], true))) {
                    return ['ok' => false, 'reason' => 'order_state_' . $cspState];
                }
                if ($cspState === 'closed' && !$expired) {
                    return ['ok' => false, 'reason' => 'order_state_closed'];
                }
                if (!$expired && (int) ($csp['expires_at'] ?? 0) < time()) {
                    return ['ok' => false, 'reason' => 'order_state_expired'];
                }
                $allowedStatuses = $expired ? ['Unpaid', 'waiting', 'pending', 'expire'] : ['Unpaid', 'waiting', 'pending'];
                if (!in_array($payStatus, $allowedStatuses, true)) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                if ($csp['deposit_id'] !== null) {
                    return ['ok' => false, 'reason' => 'order_has_deposit'];
                }
                if ((string) $csp['id_user'] !== (string) ($pr['id_user'] ?? '')) {
                    return ['ok' => false, 'reason' => 'not_eligible'];
                }
                try {
                    $f = $pdo->prepare("SELECT state, lease_expires_at FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE");
                    $f->execute([$orderId]);
                    $fulfillment = $f->fetch(PDO::FETCH_ASSOC);
                    if (is_array($fulfillment)) {
                        $fulfillmentState = (string) ($fulfillment['state'] ?? '');
                        if ($fulfillmentState === 'completed') {
                            return ['ok' => false, 'reason' => 'already_paid'];
                        }
                        if (in_array($fulfillmentState, ['claimed', 'applying', 'reconciling'], true) && (int) ($fulfillment['lease_expires_at'] ?? 0) > time()) {
                            return ['ok' => false, 'reason' => 'processing'];
                        }
                    }
                } catch (Throwable $e) {
                }
                $pdo->prepare("UPDATE card_sms_payment SET deposit_id = ?, received_rial = ?, updated_at = ? WHERE id = ?")
                    ->execute([$depositId, (int) $dep['amount_rial'], $now, (int) $csp['id']]);
                $pdo->prepare("UPDATE card_sms_deposit SET state = 'linked', id_order = ?, updated_at = ? WHERE id = ?")
                    ->execute([$orderId, $now, $depositId]);
                return ['ok' => true, 'csp' => $csp, 'deposit' => $dep];
            });
        } catch (Throwable $e) {
            rx_sbv_log('SBV_LINK_FAILED', $e->getMessage(), ['deposit_id' => $depositId, 'id_order' => $orderId]);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        if (!empty($out['ok'])) {
            rx_sbv_audit($orderId, $depositId, $actor, 'admin_link', [
                'principal_toman' => (int) $out['csp']['principal_toman'],
                'fee_toman' => (int) $out['csp']['fee_toman'],
                'expected_rial' => (int) $out['csp']['payable_rial'],
                'received_rial' => (int) $out['deposit']['amount_rial'],
                'outcome' => 'linked',
            ]);
            if (function_exists('rx_redis_del')) {
                rx_redis_del(rx_payment_status_cache_key($orderId, $out['csp']['id_user']));
            }
        }
        return $out;
    }
}

if (!function_exists('rx_sbv_dismiss')) {
    function rx_sbv_dismiss(int $depositId, string $actor, string $reason): array
    {
        if (trim($reason) === '') {
            return ['ok' => false, 'reason' => 'reason_required'];
        }
        $pdo = rx_sbv_pdo();
        if ($pdo === null) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        try {
            $stmt = $pdo->prepare("UPDATE card_sms_deposit SET state = 'dismissed', note = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND state IN ('unmatched', 'ambiguous', 'late')");
            $stmt->execute([$reason, $actor, time(), time(), $depositId]);
            if ($stmt->rowCount() !== 1) {
                return ['ok' => false, 'reason' => 'state_changed'];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        rx_sbv_audit(null, $depositId, $actor, 'deposit_dismiss', ['outcome' => 'dismissed', 'reason' => $reason]);
        return ['ok' => true];
    }
}

if (!function_exists('rx_sbv_review_queue')) {
    function rx_sbv_review_queue(int $offset, int $limit): array
    {
        $pdo = rx_sbv_pdo();
        if ($pdo === null || !rx_sbv_has_schema()) {
            return ['rows' => [], 'total' => 0];
        }
        try {
            $total = (int) $pdo->query("SELECT COUNT(*) FROM card_sms_deposit WHERE state IN ('unmatched', 'ambiguous', 'late', 'linked')")->fetchColumn();
            $stmt = $pdo->prepare("SELECT * FROM card_sms_deposit WHERE state IN ('unmatched', 'ambiguous', 'late', 'linked') ORDER BY id DESC LIMIT " . max(1, $limit) . " OFFSET " . max(0, $offset));
            $stmt->execute();
            return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
        } catch (Throwable $e) {
            return ['rows' => [], 'total' => 0];
        }
    }
}

if (!function_exists('rx_sbv_receipt_admin_note')) {
    function rx_sbv_receipt_admin_note(string $orderId): string
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null) {
            return '';
        }
        $state = (string) $csp['state'];
        $match = $state === 'matched' ? 'واریز تطبیق داده شده، در حال تأیید خودکار' : ($csp['deposit_id'] !== null ? 'واریز متصل شده (نیازمند تصمیم ادمین)' : 'هنوز واریز تطبیق‌یافته‌ای دریافت نشده');
        return "\n\n🔐 <b>پرداخت با بررسی</b>\n"
            . "<blockquote>💰 اصل مبلغ: " . rx_sbv_money($csp['principal_toman']) . " تومان\n"
            . "🏷 کارمزد شناسایی: " . rx_sbv_money($csp['fee_toman']) . " تومان\n"
            . "✅ مبلغ قابل پرداخت: " . rx_sbv_money($csp['payable_toman']) . " تومان (" . (int) $csp['payable_rial'] . " ریال)\n"
            . "⏳ مهلت: " . rx_sbv_clock((int) $csp['expires_at'], true) . "\n"
            . "🔎 وضعیت تطبیق: " . $match . "</blockquote>";
    }
}

if (!function_exists('rx_sbv_receipt_keyboard')) {
    function rx_sbv_receipt_keyboard($keyboardJson, string $orderId)
    {
        if (rx_sbv_get($orderId) === null) {
            return $keyboardJson;
        }
        $kb = is_string($keyboardJson) ? json_decode($keyboardJson, true) : $keyboardJson;
        if (!is_array($kb) || !isset($kb['inline_keyboard']) || !is_array($kb['inline_keyboard'])) {
            $kb = ['inline_keyboard' => []];
        }
        $kb['inline_keyboard'][] = [['text' => '🔎 بررسی تطبیق و اقدامات مالی', 'callback_data' => 'sbv_o_' . $orderId]];
        return json_encode($kb, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rx_sbv_cancel_row')) {
    function rx_sbv_cancel_row(string $orderId): ?array
    {
        $csp = rx_sbv_get($orderId);
        if ($csp === null || (string) $csp['state'] !== 'open' || $csp['deposit_id'] !== null) {
            return null;
        }
        return [['text' => '❌ لغو پرداخت', 'callback_data' => 'sbvcancel_' . $orderId, '_rxsk' => 'pay_cancel']];
    }
}

if (!function_exists('rx_sbv_cancel_keyboard')) {
    function rx_sbv_cancel_keyboard(string $orderId): ?string
    {
        $row = rx_sbv_cancel_row($orderId);
        return $row === null ? null : json_encode(['inline_keyboard' => [$row]], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rx_sbv_bot_mark_cancelled')) {
    function rx_sbv_bot_mark_cancelled($chatId, $messageId, string $text): void
    {
        $kb = json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'backuser']]]], JSON_UNESCAPED_UNICODE);
        if ((int) $messageId <= 0) {
            sendmessage($chatId, $text, $kb, 'HTML');
            return;
        }
        $r = function_exists('Editmessagetext') ? Editmessagetext($chatId, $messageId, $text, $kb, 'HTML') : null;
        if (is_array($r) && !empty($r['ok'])) {
            return;
        }
        $r = telegram('editMessageCaption', ['chat_id' => $chatId, 'message_id' => (int) $messageId, 'caption' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
        if (is_array($r) && !empty($r['ok'])) {
            return;
        }
        telegram('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => (int) $messageId, 'reply_markup' => json_encode(['inline_keyboard' => []])]);
        sendmessage($chatId, $text, $kb, 'HTML');
    }
}

if (!function_exists('rx_sbv_bot_pending_notice')) {
    function rx_sbv_bot_pending_notice($chatId, $messageId, array $active): void
    {
        $s = rx_sbv_pending_summary($active);
        $text = "⚠️ " . rx_sbv_pending_message() . "\n\n"
            . "<blockquote>🛒 کد پیگیری: <code>" . htmlspecialchars($s['order_id'], ENT_QUOTES, 'UTF-8') . "</code>\n"
            . "💰 مبلغ سفارش/شارژ: " . rx_sbv_money($s['principal_toman']) . " تومان\n"
            . "✅ مبلغ دقیق قابل واریز: " . rx_sbv_money($s['payable_toman']) . " تومان\n"
            . "⏳ مهلت پرداخت: تا ساعت " . rx_sbv_clock($s['expires_at']) . "\n"
            . "📌 وضعیت: " . $s['status_label'] . "</blockquote>";
        $rows = [];
        if ($s['resumable']) {
            $rows[] = [['text' => '▶️ ادامه پرداخت قبلی', 'callback_data' => 'sbvresume_' . $s['order_id'], '_rxsk' => 'pay_resume']];
        }
        if ($s['cancellable']) {
            $rows[] = [['text' => '❌ لغو پرداخت قبلی', 'callback_data' => 'sbvcancel_' . $s['order_id'], '_rxsk' => 'pay_cancel']];
        }
        $rows[] = [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'backuser']];
        $kb = json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
        $sent = null;
        if ((int) $messageId > 0 && function_exists('Editmessagetext')) {
            $sent = Editmessagetext($chatId, $messageId, $text, $kb, 'HTML');
        }
        if (!is_array($sent) || empty($sent['ok'])) {
            sendmessage($chatId, $text, $kb, 'HTML');
        }
    }
}

if (!function_exists('rx_sbv_cancel_active')) {
    function rx_sbv_cancel_active($userId, string $orderId): array
    {
        $userId = trim((string) $userId);
        $active = rx_sbv_active_payment($userId);
        if ($active === null || (string) $active['id_order'] !== $orderId) {
            return ['ok' => false, 'code' => 'not_active', 'message' => 'این پرداخت دیگر فعال نیست. اکنون می‌توانید پرداخت جدید ایجاد کنید.'];
        }
        if (empty($active['cancellable'])) {
            return ['ok' => false, 'code' => 'not_cancellable', 'message' => 'این پرداخت قابل لغو نیست؛ واریز یا رسید آن در حال بررسی است.'];
        }
        if (!function_exists('rxCancelAbandonedCardPayment')) {
            return ['ok' => false, 'code' => 'unavailable', 'message' => 'لغو پرداخت در حال حاضر ممکن نیست.'];
        }
        $res = rxCancelAbandonedCardPayment($orderId, $userId, 'user_cancel');
        if (empty($res['ok'])) {
            return ['ok' => false, 'code' => (string) ($res['reason'] ?? 'error'), 'message' => 'لغو پرداخت انجام نشد؛ ممکن است واریز یا رسید آن در حال بررسی باشد.'];
        }
        if (function_exists('rx_ck_release_for_order')) {
            rx_ck_release_for_order($orderId, 'cancelled', 'payment_reset');
        }
        return ['ok' => true, 'code' => 'cancelled', 'message' => '✅ پرداخت قبلی لغو شد. اکنون می‌توانید پرداخت جدید ایجاد کنید.'];
    }
}

if (!function_exists('rx_sbv_bot_resume')) {
    function rx_sbv_bot_resume($chatId, string $orderId, array $user): bool
    {
        global $setting, $datatextbot;
        $uid = (string) $chatId;
        $active = rx_sbv_active_payment($uid);
        if ($active === null || (string) $active['id_order'] !== $orderId || empty($active['resumable'])) {
            return false;
        }
        $pdo = rx_sbv_pdo();
        $pr = rx_sbv_payment_report($orderId);
        $view = rx_sbv_bot_payment_view($orderId);
        if ($pdo === null || $pr === null || $view === null) {
            return false;
        }
        $cfg = is_array($setting ?? null) ? $setting : (function_exists('select') ? (array) select('setting', '*') : []);
        $texts = is_array($datatextbot ?? null) ? $datatextbot : [];
        $adminIds = function_exists('select') ? (array) select('admin', 'id_admin', null, null, 'FETCH_COLUMN') : [];
        $cvActive = !in_array($uid, array_map('strval', $adminIds), true)
            && ($cfg['card_verify_status'] ?? 'offcardverify') === 'oncardverify'
            && (int) ($user['card_verify_bypass'] ?? 0) !== 1;
        $cvMin = (int) ($cfg['card_verify_min_amount'] ?? 0);
        if ($cvActive && $cvMin > 0 && (int) $pr['price'] < $cvMin) {
            $cvActive = false;
        }
        if ($cvActive && trim((string) ($pr['card_last4'] ?? '')) === '') {
            update('user', 'Processing_value', $orderId, 'id', $uid);
            $stmt = $pdo->prepare("SELECT last4 FROM verified_cards WHERE user_id = ? ORDER BY id DESC LIMIT 5");
            $stmt->execute([$uid]);
            $verified = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($verified)) {
                $rows = [];
                foreach ($verified as $l4) {
                    $rows[] = [['text' => "💳 **** **** **** {$l4}", 'callback_data' => "cv_use_{$l4}"]];
                }
                $rows[] = [['text' => "📷 پرداخت با کارت جدید", 'callback_data' => "cv_new"]];
                $cancelRow = rx_sbv_cancel_row($orderId);
                if ($cancelRow !== null) {
                    $rows[] = $cancelRow;
                }
                step('card_select_step', $uid);
                sendmessage($uid, $texts['dyn_wallet_select_verified_card'] ?? "💳 کارت‌های تاییدشده شما:\n\nیکی از کارت‌های زیر را انتخاب کنید یا با کارت جدید پرداخت کنید:", json_encode(['inline_keyboard' => $rows]), 'HTML');
            } else {
                step('card_photo_step', $uid);
                sendmessage($uid, $texts['dyn_wallet_card_auth_active_notice'] ?? "⚠️ احراز هویت کارت به کارت فعال است\n\n📸 لطفا تصویر کارت فیزیکی خود را که قصد واریز با آن را دارید، ارسال کنید.", rx_sbv_cancel_keyboard($orderId), 'HTML');
            }
            return true;
        }
        $access = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?))) AND NOT EXISTS (SELECT 1 FROM user_card_block ucb WHERE ucb.card_id = cn.id AND ucb.user_id = ?)";
        $mode = (string) rx_sbv_setting_get('card_display_mode', 'random');
        $orderSql = $mode === 'direct' ? 'cn.created_at ASC LIMIT 2' : 'RAND() LIMIT 1';
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_number cn WHERE {$access} ORDER BY {$orderSql}");
            $stmt->execute([$uid, $uid, $uid]);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $cards = [];
        }
        $card = $cards[0] ?? null;
        $card2 = $mode === 'direct' ? ($cards[1] ?? null) : null;
        if (!is_array($card) || empty($card['cardnumber'])) {
            sendmessage($uid, $texts['dyn_wallet_no_active_card'] ?? "❌ کارت بانکی فعالی برای این روش پرداخت یافت نشد. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.", null, 'HTML');
            return true;
        }
        if (is_array($card2)) {
            $text = "💳 <b>کارت اول</b>\n🔢 <code>{$card['cardnumber']}</code>\n👤 {$card['namecard']}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$card2['cardnumber']}</code>\n👤 {$card2['namecard']}\n\n💰 مبلغ: {$view['valueprice']} تومان";
        } else {
            $text = strtr((string) ($texts['text_cart'] ?? "💳 مبلغ {price} تومان را به کارت {card_number} به نام {name_card} واریز کنید."), ['{price}' => $view['valueprice'], '{card_number}' => $card['cardnumber'], '{name_card}' => $card['namecard']]);
        }
        $text .= $view['block'];
        $copyAllowed = (string) ($cfg['statuscopycart'] ?? '') === '1';
        $copyRow = [];
        if ($copyAllowed) {
            $copyRow[] = ['text' => "کپی کارت اول", 'copy_text' => ['text' => (string) $card['cardnumber']], '_rxck' => 'card_num'];
        }
        $copyRow[] = ['text' => $view['copy_label'], 'copy_text' => ['text' => $view['price_copy']], '_rxck' => 'card_amount'];
        $rows = [$copyRow];
        if ($copyAllowed && is_array($card2) && !empty($card2['cardnumber'])) {
            $rows[] = [['text' => "کپی کارت دوم", 'copy_text' => ['text' => (string) $card2['cardnumber']], '_rxck' => 'card_num_2']];
        }
        $rows[] = [['text' => $view['receipt_label'], 'callback_data' => 'sendresidcart-' . $orderId]];
        $cancelRow = rx_sbv_cancel_row($orderId);
        if ($cancelRow !== null) {
            $rows[] = $cancelRow;
        }
        $kb = json_encode(['inline_keyboard' => $rows]);
        $res = function_exists('rx_send_banner_message')
            ? rx_send_banner_message($uid, 'cart', $text, $kb, 'html')
            : sendmessage($uid, $text, $kb, 'HTML');
        if (function_exists('updatePaymentMessageId')) {
            updatePaymentMessageId($res, $orderId);
        }
        return true;
    }
}
