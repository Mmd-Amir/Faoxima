<?php

namespace Faoxima\Payments\Fulfillment;

use PDO;
use Throwable;

final class Schema
{
    public static function tableDdl(): string
    {
        return "CREATE TABLE IF NOT EXISTS payment_fulfillment (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_order VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            payment_report_id INT UNSIGNED NULL,
            operation_type VARCHAR(40) NOT NULL,
            state VARCHAR(20) NOT NULL,
            processing_token CHAR(32) NULL,
            lease_expires_at INT UNSIGNED NULL,
            attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
            before_state MEDIUMTEXT NULL,
            target_state MEDIUMTEXT NULL,
            result_state MEDIUMTEXT NULL,
            last_error TEXT NULL,
            created_at INT UNSIGNED NOT NULL,
            updated_at INT UNSIGNED NOT NULL,
            completed_at INT UNSIGNED NULL,
            UNIQUE KEY uniq_pf_id_order (id_order),
            KEY idx_pf_state_lease (state, lease_expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public static function ensureSchema($runtimePdo): bool
    {
        static $ready = false;
        $pdo = $runtimePdo;
        if ($ready) {
            return true;
        }
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $pdo->exec(self::tableDdl());
            $index = $pdo->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_id_order'");
            if ($index !== false && $index->fetch(PDO::FETCH_ASSOC) === false) {
                $pdo->exec("ALTER TABLE Payment_report ADD INDEX idx_pr_id_order (id_order(191))");
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('PAYMENT_FULFILLMENT_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }
}
