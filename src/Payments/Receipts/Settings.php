<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use Throwable;
use Faoxima\Api\Runtime\Db;

final class Settings
{
    public static function log(string $event, string $message, array $ctx = []): void
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

    public static function pdo($runtimePdo): ?PDO
    {
        $pdo = $runtimePdo;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        if (class_exists(\Faoxima\Api\Runtime\Db::class)) {
            try {
                $p = \Faoxima\Api\Runtime\Db::pdo();
                if ($p instanceof PDO) {
                    return $p;
                }
            } catch (Throwable $e) {
            }
        }
        return null;
    }

    public static function ddl(): array
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

    public static function ensureSchema($runtimePdo): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        $pdo = self::pdo($runtimePdo);
        if ($pdo === null) {
            return false;
        }
        try {
            foreach (self::ddl() as $table => $ddl) {
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
            self::log('SBV_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }

    public static function hasSchema($runtimePdo): bool
    {
        static $known = null;
        if ($known === true) {
            return true;
        }
        $pdo = self::pdo($runtimePdo);
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

    public static function settingGet($runtimePdo, string $name, ?string $default = null): ?string
    {
        $pdo = self::pdo($runtimePdo);
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

    public static function settingSet($runtimePdo, string $name, string $value): bool
    {
        $pdo = self::pdo($runtimePdo);
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
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                try {
                    \Faoxima\Core\Database\SelectCache::clearSelectCache('PaySetting');
                } catch (Throwable $e) {
                }
            }
            return true;
        } catch (Throwable $e) {
            self::log('SBV_SETTING_WRITE_FAILED', $e->getMessage(), ['name' => $name]);
            return false;
        }
    }

    public static function apiKey($runtimePdo): string
    {
        return trim((string) self::settingGet($runtimePdo, 'sbv_api_key', ''));
    }

    public static function enabled($runtimePdo): bool
    {
        return self::settingGet($runtimePdo, 'sbv_status', 'off') === 'on' && self::apiKey($runtimePdo) !== '';
    }

    public static function lifetimeMinutes($runtimePdo): int
    {
        $raw = self::settingGet($runtimePdo, 'sbv_lifetime_min', (string) RX_SBV_LIFETIME_MIN_MINUTES);
        $min = ctype_digit((string) $raw) ? (int) $raw : RX_SBV_LIFETIME_MIN_MINUTES;
        return max(RX_SBV_LIFETIME_MIN_MINUTES, min(RX_SBV_LIFETIME_MAX_MINUTES, $min));
    }

    public static function setLifetimeMinutes($runtimePdo, int $minutes): bool
    {
        if ($minutes < RX_SBV_LIFETIME_MIN_MINUTES || $minutes > RX_SBV_LIFETIME_MAX_MINUTES) {
            return false;
        }
        return self::settingSet($runtimePdo, 'sbv_lifetime_min', (string) $minutes);
    }

    public static function suggestionRangeToman($runtimePdo): int
    {
        $raw = self::settingGet($runtimePdo, 'sbv_suggestion_range_toman', (string) RX_SBV_SUGGEST_RANGE_DEFAULT_TOMAN);
        $toman = ctype_digit((string) $raw) ? (int) $raw : RX_SBV_SUGGEST_RANGE_DEFAULT_TOMAN;
        return max(RX_SBV_SUGGEST_RANGE_MIN_TOMAN, min(RX_SBV_SUGGEST_RANGE_MAX_TOMAN, $toman));
    }

    public static function setSuggestionRangeToman($runtimePdo, int $toman): bool
    {
        if ($toman < RX_SBV_SUGGEST_RANGE_MIN_TOMAN || $toman > RX_SBV_SUGGEST_RANGE_MAX_TOMAN) {
            return false;
        }
        return self::settingSet($runtimePdo, 'sbv_suggestion_range_toman', (string) $toman);
    }

    public static function generateKey(): string
    {
        return 'fxv_' . bin2hex(random_bytes(32));
    }

    public static function enable($runtimePdo): array
    {
        if (!self::ensureSchema($runtimePdo)) {
            return ['ok' => false, 'reason' => 'schema_unavailable'];
        }
        $generated = false;
        if (self::apiKey($runtimePdo) === '') {
            if (!self::settingSet($runtimePdo, 'sbv_api_key', self::generateKey())) {
                return ['ok' => false, 'reason' => 'db_error'];
            }
            self::settingSet($runtimePdo, 'sbv_key_created_at', (string) time());
            $generated = true;
        }
        if (!self::settingSet($runtimePdo, 'sbv_status', 'on')) {
            return ['ok' => false, 'reason' => 'db_error'];
        }
        return ['ok' => true, 'generated' => $generated];
    }

    public static function disable($runtimePdo): bool
    {
        return self::settingSet($runtimePdo, 'sbv_status', 'off');
    }

    public static function regenerateKey($runtimePdo): bool
    {
        if (!self::settingSet($runtimePdo, 'sbv_api_key', self::generateKey())) {
            return false;
        }
        self::settingSet($runtimePdo, 'sbv_key_created_at', (string) time());
        self::settingSet($runtimePdo, 'sbv_last_auth_at', '');
        return true;
    }

    public static function connectionStatus($runtimePdo): array
    {
        $last = (int) self::settingGet($runtimePdo, 'sbv_last_auth_at', '0');
        if ($last <= 0) {
            return ['code' => 'never', 'label' => 'هنوز هیچ درخواست معتبری دریافت نشده', 'last_at' => 0];
        }
        if (time() - $last <= 86400) {
            return ['code' => 'connected', 'label' => 'متصل', 'last_at' => $last];
        }
        return ['code' => 'stale', 'label' => 'بیش از ۲۴ ساعت بدون درخواست', 'last_at' => $last];
    }
}
