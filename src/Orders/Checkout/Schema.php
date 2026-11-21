<?php

namespace Faoxima\Orders\Checkout;

use PDO;
use Throwable;

final class Schema
{
    public static function tableDdl(): string
    {
        return "CREATE TABLE IF NOT EXISTS checkout_order (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checkout_id VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            id_user VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            operation VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            op_key VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            target VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            final_price BIGINT NOT NULL DEFAULT 0,
            wallet_choice VARCHAR(10) NOT NULL DEFAULT 'pending',
            wallet_amount BIGINT NOT NULL DEFAULT 0,
            external_amount BIGINT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'awaiting_choice',
            reservation_state VARCHAR(10) NOT NULL DEFAULT 'none',
            reservation_expires_at INT UNSIGNED NULL,
            wallet_state VARCHAR(10) NOT NULL DEFAULT 'none',
            resolution VARCHAR(20) NULL,
            settle_order_id VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            payment_order_id VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            payment_method VARCHAR(60) NULL,
            fulfillment_state VARCHAR(20) NULL,
            source VARCHAR(10) NOT NULL DEFAULT 'bot',
            meta MEDIUMTEXT NULL,
            expires_at INT UNSIGNED NOT NULL DEFAULT 0,
            created_at INT UNSIGNED NOT NULL,
            updated_at INT UNSIGNED NOT NULL,
            UNIQUE KEY uniq_ck_checkout_id (checkout_id),
            KEY idx_ck_user_res (id_user, reservation_state),
            KEY idx_ck_user_key (id_user, op_key(191)),
            KEY idx_ck_payment_order (payment_order_id),
            KEY idx_ck_status_exp (status, expires_at)
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
            $exists = $pdo->query("SHOW TABLES LIKE 'checkout_order'")->fetchColumn();
            if ($exists === false) {
                if ($pdo->inTransaction()) {
                    return false;
                }
                $pdo->exec(self::tableDdl());
            }
            if ($pdo->inTransaction()) {
                return true;
            }
            $columns = [
                'wallet_state' => "VARCHAR(10) NOT NULL DEFAULT 'none'",
                'resolution' => "VARCHAR(20) NULL",
                'settle_order_id' => "VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL",
            ];
            foreach ($columns as $column => $definition) {
                $have = $pdo->query("SHOW COLUMNS FROM checkout_order LIKE '" . $column . "'")->fetchColumn();
                if ($have === false) {
                    $pdo->exec("ALTER TABLE checkout_order ADD COLUMN {$column} {$definition}");
                }
            }
            $pdo->exec("UPDATE checkout_order SET reservation_state = 'released', reservation_expires_at = NULL WHERE reservation_state = 'reserved'");
            if (method_exists(\Faoxima\Payments\Fulfillment\FulfillmentRuntime::class, 'rx_pf_wallet_ready')) {
                \Faoxima\Payments\Fulfillment\WalletSchema::walletReady($runtimePdo);
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            \Faoxima\Orders\Checkout\Checkout::log('CHECKOUT_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }
}
