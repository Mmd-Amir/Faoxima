<?php

if (!defined('RX_CK_PAYMENT_TTL')) {
    define('RX_CK_PAYMENT_TTL', 3600);
}
if (!defined('RX_CK_CHOICE_TTL')) {
    define('RX_CK_CHOICE_TTL', 1800);
}

if (!function_exists('rx_ck_log')) {
    function rx_ck_log(string $event, string $message, array $ctx = []): void
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

if (!function_exists('rx_ck_operations')) {
    function rx_ck_operations(): array
    {
        return ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'];
    }
}

if (!function_exists('rx_ck_live_payment_statuses')) {
    function rx_ck_live_payment_statuses(): array
    {
        return ['Unpaid', 'pending', 'waiting', 'AwaitingHash', 'processing', 'reconciling', 'manual_review'];
    }
}

if (!function_exists('rx_ck_fulfilling_payment_statuses')) {
    function rx_ck_fulfilling_payment_statuses(): array
    {
        return ['processing', 'reconciling', 'manual_review'];
    }
}

if (!function_exists('rx_ck_table_ddl')) {
    function rx_ck_table_ddl(): string
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
}

if (!function_exists('rx_ck_ensure_schema')) {
    function rx_ck_ensure_schema(): bool
    {
        static $ready = false;
        global $pdo;
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
                $pdo->exec(rx_ck_table_ddl());
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
            if (function_exists('rx_pf_wallet_ready')) {
                rx_pf_wallet_ready();
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('rx_ck_tx')) {
    function rx_ck_tx(callable $fn)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            throw new RuntimeException('pdo_unavailable');
        }
        if ($pdo->inTransaction()) {
            return $fn($pdo);
        }
        rx_ck_ensure_schema();
        $pdo->beginTransaction();
        try {
            $out = $fn($pdo);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            return $out;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Throwable $rollbackError) {
                }
            }
            throw $e;
        }
    }
}

if (!function_exists('rx_ck_new_id')) {
    function rx_ck_new_id(): string
    {
        return bin2hex(random_bytes(8));
    }
}

if (!function_exists('rx_ck_key_parts')) {
    function rx_ck_key_parts(string $opKey): array
    {
        $parts = explode('|', $opKey, 2);
        return [(string) ($parts[0] ?? ''), (string) ($parts[1] ?? '')];
    }
}

if (!function_exists('rx_ck_clear_user_cache')) {
    function rx_ck_clear_user_cache($userId): void
    {
        if (function_exists('clearSelectCacheRow')) {
            try {
                clearSelectCacheRow('user', 'id', $userId);
            } catch (Throwable $e) {
            }
        }
    }
}

if (!function_exists('rx_ck_wallet_state')) {
    function rx_ck_wallet_state($userId, ?string $excludeCheckoutId = null, bool $lock = false): array
    {
        global $pdo;
        $balance = 0;
        if ($pdo instanceof PDO) {
            $stmt = $pdo->prepare("SELECT Balance FROM user WHERE id = ?" . ($lock ? " FOR UPDATE" : ""));
            $stmt->execute([(string) $userId]);
            $value = $stmt->fetchColumn();
            $balance = $value === false ? 0 : (int) $value;
        }
        return [
            'balance' => $balance,
            'available' => max(0, $balance),
        ];
    }
}

if (!function_exists('rx_wallet_available')) {
    function rx_wallet_available($userId): array
    {
        try {
            return rx_ck_wallet_state($userId);
        } catch (Throwable $e) {
            return ['balance' => 0, 'available' => 0];
        }
    }
}

if (!function_exists('rx_ck_available_for_user')) {
    function rx_ck_available_for_user(array $user): float
    {
        return (float) ($user['Balance'] ?? 0);
    }
}

if (!function_exists('rx_ck_meta')) {
    function rx_ck_meta(?array $ck): array
    {
        if (!is_array($ck) || empty($ck['meta'])) {
            return [];
        }
        $decoded = json_decode((string) $ck['meta'], true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('rx_ck_fetch')) {
    function rx_ck_fetch(string $checkoutId, $userId = null): ?array
    {
        global $pdo;
        if ($checkoutId === '' || !($pdo instanceof PDO) || !rx_ck_ensure_schema()) {
            return null;
        }
        $sql = "SELECT * FROM checkout_order WHERE checkout_id = ?";
        $params = [$checkoutId];
        if ($userId !== null) {
            $sql .= " AND id_user = ?";
            $params[] = (string) $userId;
        }
        $stmt = $pdo->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}

if (!function_exists('rx_ck_lock')) {
    function rx_ck_lock(PDO $pdo, string $checkoutId, $userId = null): ?array
    {
        $sql = "SELECT * FROM checkout_order WHERE checkout_id = ?";
        $params = [$checkoutId];
        if ($userId !== null) {
            $sql .= " AND id_user = ?";
            $params[] = (string) $userId;
        }
        $stmt = $pdo->prepare($sql . " LIMIT 1 FOR UPDATE");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}

if (!function_exists('rx_ck_expire_stale')) {
    function rx_ck_expire_stale(bool $force = false): int
    {
        global $pdo;
        static $lastRun = 0;
        $now = time();
        if (!$force && $now - $lastRun < 30) {
            return 0;
        }
        $lastRun = $now;
        if (!($pdo instanceof PDO) || !rx_ck_ensure_schema()) {
            return 0;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE checkout_order SET status = 'expired', updated_at = ? "
                . "WHERE status IN ('awaiting_choice', 'confirmed') AND expires_at > 0 AND expires_at <= ? "
                . "AND NOT EXISTS (SELECT 1 FROM Payment_report pr WHERE pr.id_user = checkout_order.id_user AND pr.id_invoice = checkout_order.op_key AND pr.payment_Status IN ('Unpaid','pending','waiting','AwaitingHash','processing','reconciling','manual_review'))"
            );
            $stmt->execute([$now, $now]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_EXPIRE_FAILED', $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('rx_ck_open')) {
    function rx_ck_open(array $p): ?array
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !rx_ck_ensure_schema()) {
            return null;
        }
        $userId = (string) ($p['id_user'] ?? '');
        $operation = (string) ($p['operation'] ?? '');
        $opKey = (string) ($p['op_key'] ?? '');
        $finalPrice = (int) round((float) ($p['final_price'] ?? 0));
        if ($userId === '' || !in_array($operation, rx_ck_operations(), true) || $opKey === '' || $finalPrice <= 0) {
            return null;
        }
        rx_ck_expire_stale();
        $now = time();
        $checkoutId = rx_ck_new_id();
        $meta = isset($p['meta']) && is_array($p['meta']) ? json_encode($p['meta'], JSON_UNESCAPED_UNICODE) : null;
        try {
            rx_ck_tx(function (PDO $pdo) use ($userId, $operation, $opKey, $finalPrice, $now, $checkoutId, $meta, $p) {
                $sup = $pdo->prepare(
                    "UPDATE checkout_order SET status = 'cancelled', updated_at = ? "
                    . "WHERE id_user = ? AND op_key = ? AND status IN ('awaiting_choice', 'confirmed')"
                );
                $sup->execute([$now, $userId, $opKey]);
                $ins = $pdo->prepare(
                    "INSERT INTO checkout_order (checkout_id, id_user, operation, op_key, target, final_price, wallet_choice, wallet_amount, external_amount, status, reservation_state, source, meta, expires_at, created_at, updated_at) "
                    . "VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, ?, 'awaiting_choice', 'none', ?, ?, ?, ?, ?)"
                );
                $ins->execute([
                    $checkoutId,
                    $userId,
                    $operation,
                    $opKey,
                    isset($p['target']) ? (string) $p['target'] : null,
                    $finalPrice,
                    $finalPrice,
                    (string) ($p['source'] ?? 'bot'),
                    $meta,
                    $now + RX_CK_CHOICE_TTL,
                    $now,
                    $now,
                ]);
            });
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_OPEN_FAILED', $e->getMessage(), ['id_user' => $userId, 'op_key' => $opKey]);
            return null;
        }
        return rx_ck_fetch($checkoutId, $userId);
    }
}

if (!function_exists('rx_ck_preview')) {
    function rx_ck_preview(array $ck): array
    {
        $final = (int) $ck['final_price'];
        $state = rx_ck_wallet_state($ck['id_user']);
        $walletIfUse = min($state['available'], $final);
        $externalIfUse = $final - $walletIfUse;
        $canUse = $walletIfUse > 0 && $externalIfUse > 1;
        return [
            'checkout_id' => (string) $ck['checkout_id'],
            'final_price' => $final,
            'recorded_balance' => $state['balance'],
            'available_balance' => $state['available'],
            'can_use_wallet' => $canUse,
            'use_wallet' => [
                'wallet_amount' => $canUse ? $walletIfUse : 0,
                'external_amount' => $canUse ? $externalIfUse : $final,
            ],
            'no_wallet' => [
                'wallet_amount' => 0,
                'external_amount' => $final,
            ],
            'status' => (string) $ck['status'],
        ];
    }
}

if (!function_exists('rx_ck_select')) {
    function rx_ck_select(string $checkoutId, $userId, string $choice): array
    {
        if (!in_array($choice, ['use', 'none'], true)) {
            return ['ok' => false, 'code' => 'invalid_choice'];
        }
        try {
            $out = rx_ck_tx(function (PDO $pdo) use ($checkoutId, $userId, $choice) {
                $ck = rx_ck_lock($pdo, $checkoutId, $userId);
                if ($ck === null) {
                    return ['ok' => false, 'code' => 'not_found'];
                }
                if (!in_array((string) $ck['status'], ['awaiting_choice', 'confirmed'], true)) {
                    return ['ok' => false, 'code' => 'wrong_state:' . $ck['status'], 'checkout' => $ck];
                }
                if ((int) $ck['expires_at'] > 0 && (int) $ck['expires_at'] <= time()) {
                    return ['ok' => false, 'code' => 'expired', 'checkout' => $ck];
                }
                $preview = rx_ck_preview($ck);
                if ($choice === 'use' && !$preview['can_use_wallet']) {
                    return ['ok' => false, 'code' => 'wallet_unavailable', 'checkout' => $ck, 'preview' => $preview];
                }
                $amounts = $choice === 'use' ? $preview['use_wallet'] : $preview['no_wallet'];
                $pdo->prepare(
                    "UPDATE checkout_order SET wallet_choice = ?, wallet_amount = ?, external_amount = ?, status = 'awaiting_choice', updated_at = ? "
                    . "WHERE checkout_id = ? AND status IN ('awaiting_choice', 'confirmed')"
                )->execute([$choice, $amounts['wallet_amount'], $amounts['external_amount'], time(), $checkoutId]);
                $ck['wallet_choice'] = $choice;
                $ck['wallet_amount'] = $amounts['wallet_amount'];
                $ck['external_amount'] = $amounts['external_amount'];
                return ['ok' => true, 'code' => 'selected', 'checkout' => $ck, 'preview' => $preview];
            });
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_SELECT_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId]);
            return ['ok' => false, 'code' => 'error'];
        }
        return $out;
    }
}

if (!function_exists('rx_ck_confirm')) {
    function rx_ck_confirm(string $checkoutId, $userId, string $choice, ?int $expectedWallet = null, ?int $expectedExternal = null): array
    {
        if (!in_array($choice, ['use', 'none'], true)) {
            return ['ok' => false, 'code' => 'invalid_choice'];
        }
        try {
            $out = rx_ck_tx(function (PDO $pdo) use ($checkoutId, $userId, $choice, $expectedWallet, $expectedExternal) {
                $now = time();
                $ck = rx_ck_lock($pdo, $checkoutId, $userId);
                if ($ck === null) {
                    return ['ok' => false, 'code' => 'not_found'];
                }
                $status = (string) $ck['status'];
                if (in_array($status, ['cancelled', 'expired', 'failed', 'completed', 'resolved_credit'], true)) {
                    return ['ok' => false, 'code' => $status, 'checkout' => $ck];
                }
                $final = (int) $ck['final_price'];
                if (in_array($status, ['confirmed', 'awaiting_payment'], true) && (string) $ck['wallet_choice'] === $choice) {
                    $sameWallet = $expectedWallet === null || (int) $ck['wallet_amount'] === $expectedWallet;
                    $sameExternal = $expectedExternal === null || (int) $ck['external_amount'] === $expectedExternal;
                    if ($sameWallet && $sameExternal) {
                        return ['ok' => true, 'code' => 'already_confirmed', 'checkout' => $ck];
                    }
                }
                if ($status === 'awaiting_payment') {
                    return ['ok' => false, 'code' => 'wrong_state:awaiting_payment', 'checkout' => $ck];
                }
                if ((int) $ck['expires_at'] > 0 && (int) $ck['expires_at'] <= $now) {
                    $pdo->prepare("UPDATE checkout_order SET status = 'expired', updated_at = ? WHERE checkout_id = ? AND status IN ('awaiting_choice', 'confirmed')")->execute([$now, $checkoutId]);
                    $ck['status'] = 'expired';
                    return ['ok' => false, 'code' => 'expired', 'checkout' => $ck];
                }
                if ($choice === 'none') {
                    if (($expectedWallet !== null && $expectedWallet !== 0) || ($expectedExternal !== null && $expectedExternal !== $final)) {
                        return ['ok' => false, 'code' => 'refresh_required', 'checkout' => $ck, 'preview' => rx_ck_preview($ck)];
                    }
                    $pdo->prepare(
                        "UPDATE checkout_order SET wallet_choice = 'none', wallet_amount = 0, external_amount = ?, status = 'confirmed', expires_at = ?, updated_at = ? WHERE checkout_id = ?"
                    )->execute([$final, $now + RX_CK_PAYMENT_TTL, $now, $checkoutId]);
                    return ['ok' => true, 'code' => 'confirmed', 'checkout' => rx_ck_lock($pdo, $checkoutId, $userId)];
                }
                $state = rx_ck_wallet_state($userId, null, true);
                $wallet = min($state['available'], $final);
                $external = $final - $wallet;
                $stale = $wallet <= 0 || $external <= 1
                    || ($expectedWallet !== null && $expectedWallet !== $wallet)
                    || ($expectedExternal !== null && $expectedExternal !== $external);
                if ($stale) {
                    $pdo->prepare(
                        "UPDATE checkout_order SET wallet_choice = 'pending', wallet_amount = 0, external_amount = ?, status = 'awaiting_choice', updated_at = ? WHERE checkout_id = ?"
                    )->execute([$final, $now, $checkoutId]);
                    $ck = rx_ck_lock($pdo, $checkoutId, $userId);
                    return ['ok' => false, 'code' => 'refresh_required', 'checkout' => $ck, 'preview' => rx_ck_preview($ck)];
                }
                $pdo->prepare(
                    "UPDATE checkout_order SET wallet_choice = 'use', wallet_amount = ?, external_amount = ?, status = 'confirmed', expires_at = ?, updated_at = ? WHERE checkout_id = ?"
                )->execute([$wallet, $external, $now + RX_CK_PAYMENT_TTL, $now, $checkoutId]);
                return ['ok' => true, 'code' => 'confirmed', 'checkout' => rx_ck_lock($pdo, $checkoutId, $userId)];
            });
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_CONFIRM_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId]);
            return ['ok' => false, 'code' => 'error'];
        }
        return $out;
    }
}

if (!function_exists('rx_ck_cancel')) {
    function rx_ck_cancel(string $checkoutId, $userId, string $reason = 'user_cancelled'): array
    {
        try {
            $out = rx_ck_tx(function (PDO $pdo) use ($checkoutId, $userId, $reason) {
                $ck = rx_ck_lock($pdo, $checkoutId, $userId);
                if ($ck === null) {
                    return ['ok' => false, 'code' => 'not_found'];
                }
                $status = (string) $ck['status'];
                if (in_array($status, ['cancelled', 'expired', 'failed'], true)) {
                    return ['ok' => true, 'code' => 'already_' . $status, 'checkout' => $ck];
                }
                if (in_array($status, ['completed', 'resolved_credit'], true)) {
                    return ['ok' => false, 'code' => $status, 'checkout' => $ck];
                }
                if ($status === 'awaiting_payment') {
                    $fulfilling = "'" . implode("','", rx_ck_fulfilling_payment_statuses()) . "'";
                    $busy = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE id_user = ? AND id_invoice = ? AND payment_Status IN ($fulfilling)");
                    $busy->execute([(string) $ck['id_user'], (string) $ck['op_key']]);
                    if ((int) $busy->fetchColumn() > 0 || (string) ($ck['settle_order_id'] ?? '') !== '') {
                        return ['ok' => false, 'code' => 'in_fulfillment', 'checkout' => $ck];
                    }
                }
                $pdo->prepare(
                    "UPDATE checkout_order SET status = 'cancelled', fulfillment_state = ?, updated_at = ? WHERE checkout_id = ? AND status NOT IN ('completed', 'resolved_credit')"
                )->execute([$reason, time(), $checkoutId]);
                $ck['status'] = 'cancelled';
                return ['ok' => true, 'code' => 'cancelled', 'checkout' => $ck];
            });
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_CANCEL_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId]);
            return ['ok' => false, 'code' => 'error'];
        }
        return $out;
    }
}

if (!function_exists('rx_ck_validate_payment')) {
    function rx_ck_validate_payment(string $checkoutId, $userId, ?string $method = null): array
    {
        try {
            $out = rx_ck_tx(function (PDO $pdo) use ($checkoutId, $userId, $method) {
                $now = time();
                $ck = rx_ck_lock($pdo, $checkoutId, $userId);
                if ($ck === null) {
                    return ['ok' => false, 'code' => 'not_found'];
                }
                $status = (string) $ck['status'];
                if (!in_array($status, ['confirmed', 'awaiting_payment'], true)) {
                    return ['ok' => false, 'code' => $status === 'awaiting_choice' ? 'choice_required' : $status, 'checkout' => $ck];
                }
                $wallet = (int) $ck['wallet_amount'];
                if ((string) $ck['wallet_choice'] === 'use' && $wallet > 0 && (string) ($ck['settle_order_id'] ?? '') === '') {
                    $state = rx_ck_wallet_state($userId, null, false);
                    if ($state['balance'] < $wallet) {
                        $pdo->prepare(
                            "UPDATE checkout_order SET wallet_choice = 'pending', wallet_amount = 0, external_amount = final_price, status = 'awaiting_choice', updated_at = ? WHERE checkout_id = ?"
                        )->execute([$now, $checkoutId]);
                        $ck = rx_ck_lock($pdo, $checkoutId, $userId);
                        return ['ok' => false, 'code' => 'refresh_required', 'checkout' => $ck, 'preview' => rx_ck_preview($ck)];
                    }
                }
                $pdo->prepare("UPDATE checkout_order SET status = 'awaiting_payment', payment_method = ?, updated_at = ?, expires_at = ? WHERE checkout_id = ?")
                    ->execute([$method, $now, $now + RX_CK_PAYMENT_TTL, $checkoutId]);
                $ck['status'] = 'awaiting_payment';
                return ['ok' => true, 'code' => 'validated', 'checkout' => $ck];
            });
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_VALIDATE_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId]);
            return ['ok' => false, 'code' => 'error'];
        }
        return $out;
    }
}

if (!function_exists('rx_ck_attach_payment')) {
    function rx_ck_attach_payment(string $checkoutId, string $orderId, ?string $method = null): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO) || $checkoutId === '' || $orderId === '') {
            return false;
        }
        try {
            $stmt = $pdo->prepare("UPDATE checkout_order SET payment_order_id = ?, payment_method = COALESCE(?, payment_method), updated_at = ? WHERE checkout_id = ? AND status IN ('confirmed', 'awaiting_payment')");
            $stmt->execute([$orderId, $method, time(), $checkoutId]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_ATTACH_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId, 'id_order' => $orderId]);
            return false;
        }
    }
}

if (!function_exists('rx_ck_find_for_key')) {
    function rx_ck_find_for_key($userId, string $opKey, array $statuses = ['confirmed', 'awaiting_payment']): ?array
    {
        global $pdo;
        if (!($pdo instanceof PDO) || $opKey === '' || !rx_ck_ensure_schema()) {
            return null;
        }
        $in = "'" . implode("','", array_map(static function ($s) {
            return preg_replace('/[^a-z_]/', '', (string) $s);
        }, $statuses)) . "'";
        $stmt = $pdo->prepare("SELECT * FROM checkout_order WHERE id_user = ? AND op_key = ? AND status IN ($in) ORDER BY id DESC LIMIT 1");
        $stmt->execute([(string) $userId, $opKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}

if (!function_exists('rx_ck_find_for_report')) {
    function rx_ck_find_for_report(array $report): ?array
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !rx_ck_ensure_schema()) {
            return null;
        }
        $opKey = (string) ($report['id_invoice'] ?? '');
        [$operation] = rx_ck_key_parts($opKey);
        if (!in_array($operation, rx_ck_operations(), true)) {
            return null;
        }
        $orderId = (string) ($report['id_order'] ?? '');
        $userId = (string) ($report['id_user'] ?? '');
        try {
            if ($orderId !== '') {
                $stmt = $pdo->prepare("SELECT * FROM checkout_order WHERE payment_order_id = ? AND id_user = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$orderId, $userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row !== false) {
                    return $row;
                }
            }
            $stmt = $pdo->prepare("SELECT * FROM checkout_order WHERE id_user = ? AND op_key = ? AND status IN ('awaiting_payment', 'confirmed', 'expired', 'cancelled', 'completed', 'failed', 'resolved_credit', 'awaiting_choice') ORDER BY (status = 'awaiting_payment') DESC, id DESC LIMIT 1");
            $stmt->execute([$userId, $opKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_FIND_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return null;
        }
    }
}

if (!function_exists('rx_ck_gate_fulfillment')) {
    function rx_ck_gate_fulfillment(array $report, string $token): array
    {
        global $pdo;
        $ck0 = rx_ck_find_for_report($report);
        if ($ck0 === null) {
            return ['action' => 'legacy', 'checkout' => null, 'code' => 'legacy'];
        }
        $orderId = (string) ($report['id_order'] ?? '');
        $userId = (string) ($report['id_user'] ?? '');
        if (function_exists('rx_pf_ensure_schema')) {
            rx_pf_ensure_schema();
        }
        try {
            $out = rx_ck_tx(function (PDO $pdo) use ($ck0, $orderId, $userId, $token) {
                $now = time();
                $payment = rx_pf_lock_payment($pdo, $orderId);
                if ($payment === null) {
                    return ['action' => 'error', 'checkout' => $ck0, 'code' => 'payment_missing'];
                }
                if ((int) ($payment['direct_payment_done'] ?? 0) === 1) {
                    return ['action' => 'deny', 'checkout' => $ck0, 'code' => 'already_done', 'terminal' => true];
                }
                $record = rx_pf_lock_record($pdo, $orderId);
                if ($record === null || !hash_equals((string) ($record['processing_token'] ?? ''), $token)
                    || !in_array((string) $record['state'], ['claimed', 'applying', 'reconciling'], true)) {
                    return ['action' => 'error', 'checkout' => $ck0, 'code' => 'not_owner'];
                }
                $ck = rx_ck_lock($pdo, (string) $ck0['checkout_id'], null);
                if ($ck === null) {
                    return ['action' => 'legacy', 'checkout' => null, 'code' => 'legacy'];
                }
                $paid = (int) round((float) ($payment['price'] ?? 0));
                $wallet = (int) $ck['wallet_amount'];
                $status = (string) $ck['status'];
                $owner = (string) ($ck['settle_order_id'] ?? '');
                $deny = static function (string $code) use ($ck) {
                    return ['action' => 'deny', 'checkout' => $ck, 'code' => $code];
                };
                if ($owner === $orderId && in_array($status, ['awaiting_payment', 'confirmed', 'expired'], true)) {
                    return ['action' => 'proceed', 'checkout' => $ck, 'code' => 'resumed'];
                }
                if (in_array($status, ['completed', 'resolved_credit'], true) || $owner !== '') {
                    return $deny('already_settled');
                }
                if (in_array($status, ['cancelled', 'failed'], true)) {
                    return $deny($status);
                }
                if ($paid + 1 < (int) $ck['external_amount']) {
                    return $deny('amount_mismatch');
                }
                if ($wallet > 0) {
                    $lockStmt = $pdo->prepare("SELECT Balance FROM user WHERE id = ? FOR UPDATE");
                    $lockStmt->execute([$userId]);
                    $balance = $lockStmt->fetchColumn();
                    if ($balance === false) {
                        return ['action' => 'error', 'checkout' => $ck, 'code' => 'user_missing'];
                    }
                    if ((int) $balance < $wallet) {
                        if ($paid <= 0) {
                            return $deny('amount_mismatch');
                        }
                        $pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE id = ?")->execute([$paid, $userId]);
                        $after = $pdo->prepare("SELECT Balance FROM user WHERE id = ?");
                        $after->execute([$userId]);
                        $balanceAfter = (int) $after->fetchColumn();
                        $pdo->prepare(
                            "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id, fulfillment_key) VALUES (?, 'credit', ?, ?, 'checkout_credit', ?, ?, 'checkout_order', ?, ?)"
                        )->execute([$userId, $paid, $balanceAfter, 'اعتبار پرداخت تأییدشده - موجودی کیف پول برای تکمیل خرید کافی نبود', $orderId, (string) $ck['checkout_id'], 'ckcredit:' . $orderId]);
                        $pdo->prepare(
                            "UPDATE checkout_order SET status = 'resolved_credit', resolution = 'wallet_credit', wallet_state = 'none', settle_order_id = ?, payment_order_id = ?, fulfillment_state = 'credited', updated_at = ? WHERE checkout_id = ?"
                        )->execute([$orderId, $orderId, $now, $ck['checkout_id']]);
                        $pdo->prepare(
                            "UPDATE payment_fulfillment SET state = 'completed', result_state = ?, lease_expires_at = NULL, completed_at = ?, updated_at = ? WHERE id_order = ?"
                        )->execute([json_encode(['resolved' => 'wallet_credit', 'credited' => $paid, 'balance_after' => $balanceAfter]), $now, $now, $orderId]);
                        $paidRow = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid', direct_payment_done = 1 WHERE id_order = ? AND COALESCE(direct_payment_done, 0) = 0");
                        $paidRow->execute([$orderId]);
                        if ($paidRow->rowCount() < 1) {
                            throw new RuntimeException('checkout_credit_finalize_failed');
                        }
                        $ck['status'] = 'resolved_credit';
                        return ['action' => 'resolved_credit', 'checkout' => $ck, 'code' => 'wallet_insufficient', 'credited' => $paid];
                    }
                    $pdo->prepare("UPDATE user SET Balance = Balance - ? WHERE id = ? AND Balance >= ?")->execute([$wallet, $userId, $wallet]);
                    $after = $pdo->prepare("SELECT Balance FROM user WHERE id = ?");
                    $after->execute([$userId]);
                    $balanceAfter = (int) $after->fetchColumn();
                    $pdo->prepare(
                        "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id, fulfillment_key) VALUES (?, 'debit', ?, ?, 'checkout', ?, ?, 'checkout_order', ?, ?)"
                    )->execute([$userId, $wallet, $balanceAfter, 'سهم کیف پول از پرداخت ترکیبی سرویس', $orderId, (string) $ck['checkout_id'], 'ckwallet:' . (string) $ck['checkout_id']]);
                }
                $pdo->prepare(
                    "UPDATE checkout_order SET status = 'awaiting_payment', wallet_state = ?, settle_order_id = ?, payment_order_id = ?, fulfillment_state = 'processing', updated_at = ? WHERE checkout_id = ?"
                )->execute([$wallet > 0 ? 'debited' : 'none', $orderId, $orderId, $now, $ck['checkout_id']]);
                return ['action' => 'proceed', 'checkout' => $ck, 'code' => 'settled'];
            });
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_GATE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return ['action' => 'error', 'checkout' => $ck0, 'code' => 'error'];
        }
        rx_ck_clear_user_cache($userId);
        if (function_exists('rx_pf_clear_cache')) {
            rx_pf_clear_cache(['Payment_report', 'user']);
        }
        return $out;
    }
}

if (!function_exists('rx_ck_settlement_statements')) {
    function rx_ck_settlement_statements(?array $ck, string $orderId, $userId, int $legacyAmount = 0, string $description = 'پرداخت ترکیبی سرویس'): array
    {
        $userId = (string) $userId;
        if ($ck === null) {
            if ($legacyAmount === -1) {
                return [["UPDATE user SET Balance = 0 WHERE id = ?", [$userId]]];
            }
            if ($legacyAmount <= 0) {
                return [];
            }
            return [["UPDATE user SET Balance = GREATEST(Balance - ?, 0) WHERE id = ?", [$legacyAmount, $userId]]];
        }
        return [[
            "UPDATE checkout_order SET status = 'completed', resolution = 'service', fulfillment_state = 'completed', payment_order_id = ?, updated_at = ? WHERE checkout_id = ? AND settle_order_id = ?",
            [$orderId, time(), (string) $ck['checkout_id'], $orderId],
        ]];
    }
}


if (!function_exists('rx_ck_release_for_report')) {
    function rx_ck_release_for_report($report, string $newStatus = 'cancelled', string $reason = 'payment_closed', bool $force = false): string
    {
        global $pdo;
        if (!is_array($report) || !($pdo instanceof PDO)) {
            return 'noop';
        }
        $ck = rx_ck_find_for_report($report);
        if ($ck === null) {
            return 'noop';
        }
        try {
            return rx_ck_tx(function (PDO $pdo) use ($ck, $report, $newStatus, $reason) {
                $ck = rx_ck_lock($pdo, (string) $ck['checkout_id'], null);
                if ($ck === null || in_array((string) $ck['status'], ['completed', 'cancelled', 'expired', 'failed', 'resolved_credit'], true) || (string) ($ck['settle_order_id'] ?? '') !== '') {
                    return 'noop';
                }
                $orderId = (string) ($report['id_order'] ?? '');
                $live = "'" . implode("','", rx_ck_live_payment_statuses()) . "'";
                $others = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE id_user = ? AND id_invoice = ? AND id_order <> ? AND payment_Status IN ($live)");
                $others->execute([(string) $ck['id_user'], (string) $ck['op_key'], $orderId]);
                if ((int) $others->fetchColumn() > 0) {
                    return 'still_live';
                }
                $own = $pdo->prepare("SELECT payment_Status FROM Payment_report WHERE id_order = ? LIMIT 1");
                $own->execute([$orderId]);
                if (in_array((string) $own->fetchColumn(), array_merge(rx_ck_fulfilling_payment_statuses(), ['paid']), true)) {
                    return 'in_fulfillment';
                }
                $pdo->prepare("UPDATE checkout_order SET status = ?, fulfillment_state = ?, updated_at = ? WHERE checkout_id = ?")
                    ->execute([$newStatus, $reason, time(), $ck['checkout_id']]);
                return 'released';
            });
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_RELEASE_FAILED', $e->getMessage(), ['id_order' => $report['id_order'] ?? '']);
            return 'error';
        }
    }
}

if (!function_exists('rx_ck_release_for_order')) {
    function rx_ck_release_for_order(string $orderId, string $newStatus = 'cancelled', string $reason = 'payment_closed', bool $force = false): string
    {
        global $pdo;
        if ($orderId === '' || !($pdo instanceof PDO)) {
            return 'noop';
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return 'error';
        }
        return is_array($report) ? rx_ck_release_for_report($report, $newStatus, $reason) : 'noop';
    }
}

if (!function_exists('rx_ck_release_failed_fulfillment')) {
    function rx_ck_release_failed_fulfillment(string $orderId, string $reason): string
    {
        global $pdo;
        if ($orderId === '' || !($pdo instanceof PDO) || !rx_ck_ensure_schema()) {
            return 'noop';
        }
        try {
            $out = rx_ck_tx(function (PDO $pdo) use ($orderId, $reason) {
                $stmt = $pdo->prepare("SELECT * FROM checkout_order WHERE settle_order_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
                $stmt->execute([$orderId]);
                $ck = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($ck === false || in_array((string) $ck['status'], ['completed', 'resolved_credit', 'failed'], true)) {
                    return 'noop';
                }
                $wallet = (int) $ck['wallet_amount'];
                $restored = false;
                if ((string) $ck['wallet_state'] === 'debited' && $wallet > 0) {
                    $userId = (string) $ck['id_user'];
                    $pdo->prepare("SELECT Balance FROM user WHERE id = ? FOR UPDATE")->execute([$userId]);
                    $pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE id = ?")->execute([$wallet, $userId]);
                    $after = $pdo->prepare("SELECT Balance FROM user WHERE id = ?");
                    $after->execute([$userId]);
                    $pdo->prepare(
                        "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id, fulfillment_key) VALUES (?, 'credit', ?, ?, 'refund', ?, ?, 'checkout_order', ?, ?)"
                    )->execute([$userId, $wallet, (int) $after->fetchColumn(), 'بازگشت سهم کیف پول - خرید تکمیل نشد', $orderId, (string) $ck['checkout_id'], 'ckwalletrefund:' . (string) $ck['checkout_id']]);
                    $restored = true;
                }
                $pdo->prepare("UPDATE checkout_order SET status = 'failed', wallet_state = ?, fulfillment_state = ?, updated_at = ? WHERE checkout_id = ?")
                    ->execute([$restored ? 'refunded' : (string) $ck['wallet_state'], substr($reason, 0, 20), time(), $ck['checkout_id']]);
                return $restored ? 'wallet_restored' : 'failed';
            });
        } catch (Throwable $e) {
            rx_ck_log('CHECKOUT_FAILURE_RELEASE_FAILED', $e->getMessage(), ['id_order' => $orderId]);
            return 'error';
        }
        if (function_exists('rx_pf_clear_cache')) {
            rx_pf_clear_cache(['user']);
        }
        return $out;
    }
}
if (!function_exists('rx_ck_refund_unsecured')) {
    function rx_ck_refund_unsecured(array $report, string $description): string
    {
        if (!function_exists('rx_refund_payment_once')) {
            return 'failed';
        }
        return rx_refund_payment_once((string) $report['id_order'], (string) $report['id_user'], $report['price'], $description, null);
    }
}

if (!function_exists('rx_ck_money')) {
    function rx_ck_money($value): string
    {
        return number_format((int) round((float) $value));
    }
}

if (!function_exists('rx_ck_text')) {
    function rx_ck_text(string $key, string $default): string
    {
        if (function_exists('faoxima_textbot_get')) {
            return (string) faoxima_textbot_get($key, $default);
        }
        return $default;
    }
}

if (!function_exists('rx_ck_choice_text')) {
    function rx_ck_choice_text(array $preview): string
    {
        $lines = [];
        $lines[] = rx_ck_text('dyn_ck_title', '🧾 <b>پرداخت سفارش</b>');
        $lines[] = '';
        $lines[] = '💰 ' . rx_ck_text('dyn_ck_final_price', 'قیمت نهایی') . ': <b>' . rx_ck_money($preview['final_price']) . '</b> تومان';
        $lines[] = '💎 ' . rx_ck_text('dyn_ck_recorded_balance', 'موجودی کیف پول') . ': ' . rx_ck_money($preview['recorded_balance']) . ' تومان';
        $lines[] = '✅ ' . rx_ck_text('dyn_ck_available', 'موجودی قابل استفاده') . ': ' . rx_ck_money($preview['available_balance']) . ' تومان';
        $lines[] = '';
        $lines[] = rx_ck_text('dyn_ck_choose', 'آیا می‌خواهید از موجودی کیف پول در این پرداخت استفاده کنید؟');
        return implode("\n", $lines);
    }
}

if (!function_exists('rx_ck_summary_text')) {
    function rx_ck_summary_text(array $preview, string $choice): string
    {
        $amounts = $choice === 'use' ? $preview['use_wallet'] : $preview['no_wallet'];
        $lines = [];
        $lines[] = rx_ck_text('dyn_ck_summary_title', '🧾 <b>خلاصه پرداخت</b>');
        $lines[] = '';
        $lines[] = '💰 ' . rx_ck_text('dyn_ck_final_price', 'قیمت نهایی') . ': <b>' . rx_ck_money($preview['final_price']) . '</b> تومان';
        if ($choice === 'use') {
            $lines[] = '💼 ' . rx_ck_text('dyn_ck_wallet_part', 'سهم کیف پول') . ': <b>' . rx_ck_money($amounts['wallet_amount']) . '</b> تومان';
            $lines[] = '💳 ' . rx_ck_text('dyn_ck_external_part', 'مبلغ پرداخت خارجی') . ': <b>' . rx_ck_money($amounts['external_amount']) . '</b> تومان';
            $lines[] = '';
            $lines[] = rx_ck_text('dyn_ck_wallet_note', 'سهم کیف پول تا زمان تأیید پرداخت کسر نمی‌شود و موجودی شما تا آن زمان قابل استفاده است. اگر هنگام تأیید پرداخت موجودی کافی نباشد، مبلغ پرداخت‌شده به کیف پول شما اضافه می‌شود.');
        } else {
            $lines[] = '💼 ' . rx_ck_text('dyn_ck_wallet_part', 'سهم کیف پول') . ': <b>0</b> تومان';
            $lines[] = '💳 ' . rx_ck_text('dyn_ck_external_part', 'مبلغ پرداخت خارجی') . ': <b>' . rx_ck_money($amounts['external_amount']) . '</b> تومان';
            $lines[] = '';
            $lines[] = rx_ck_text('dyn_ck_no_wallet_note', 'کل مبلغ از طریق روش پرداخت انتخابی دریافت می‌شود و موجودی کیف پول شما تغییری نمی‌کند.');
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('rx_ck_button_style')) {
    function rx_ck_button_style(string $key): ?string
    {
        static $styles = null;
        if ($styles === null) {
            global $pdo;
            $saved = [];
            try {
                if ($pdo instanceof PDO) {
                    $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                        $all = json_decode((string) $row['keyboard_styles_all'], true);
                        if (is_array($all) && !empty($all['wallet_checkout']) && is_array($all['wallet_checkout'])) {
                            $saved = array_filter($all['wallet_checkout'], static function ($v) { return $v !== 'default'; });
                        }
                    }
                }
            } catch (Throwable $e) {
            }
            $defaults = [];
            if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                $defaults = (array) rx_getKeyboardDefaultStyles('wallet_checkout');
            }
            $styles = $saved + $defaults;
        }
        $style = $styles[$key] ?? null;
        return in_array($style, ['primary', 'success', 'danger'], true) ? $style : null;
    }
}

if (!function_exists('rx_ck_btn')) {
    function rx_ck_btn(string $key, string $text, string $callback): array
    {
        $btn = ['text' => $text, 'callback_data' => $callback];
        $style = rx_ck_button_style($key);
        if ($style === null) {
            $btn['_rxap'] = 1;
        } else {
            $btn['style'] = $style;
            $btn['_rxkeep'] = 1;
        }
        return $btn;
    }
}

if (!function_exists('rx_ck_bot_choice_keyboard')) {
    function rx_ck_bot_choice_keyboard(array $preview): string
    {
        $id = (string) $preview['checkout_id'];
        $rows = [];
        if (!empty($preview['can_use_wallet'])) {
            $rows[] = [rx_ck_btn('ck_use_wallet', rx_ck_text('dyn_ck_btn_use_wallet', '💼 استفاده از کیف پول'), 'ckw_pick_' . $id . '_use')];
        }
        $rows[] = [rx_ck_btn('ck_pay_external', rx_ck_text('dyn_ck_btn_no_wallet', '💳 پرداخت بدون کیف پول'), 'ckw_pick_' . $id . '_none')];
        $rows[] = [rx_ck_btn('ck_cancel', rx_ck_text('dyn_ck_btn_cancel', '❌ لغو خرید'), 'ckw_cancel_' . $id)];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rx_ck_bot_summary_keyboard')) {
    function rx_ck_bot_summary_keyboard(string $checkoutId): string
    {
        return json_encode(['inline_keyboard' => [
            [rx_ck_btn('ck_confirm', rx_ck_text('dyn_ck_btn_confirm', '✅ تایید و ادامه'), 'ckw_ok_' . $checkoutId)],
            [rx_ck_btn('ck_change', rx_ck_text('dyn_ck_btn_change', '↩️ تغییر انتخاب'), 'ckw_menu_' . $checkoutId)],
            [rx_ck_btn('ck_cancel', rx_ck_text('dyn_ck_btn_cancel', '❌ لغو خرید'), 'ckw_cancel_' . $checkoutId)],
        ]], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('rx_ck_bot_show')) {
    function rx_ck_bot_show($chatId, $messageId, string $text, string $keyboard): void
    {
        if ((int) $messageId > 0 && function_exists('Editmessagetext')) {
            Editmessagetext($chatId, $messageId, $text, $keyboard, 'HTML');
        } else {
            sendmessage($chatId, $text, $keyboard, 'HTML');
        }
    }
}

if (!function_exists('rx_ck_bot_begin')) {
    function rx_ck_bot_begin(array $user, array $p)
    {
        global $step_payment;
        $chatId = $user['id'];
        $messageId = (int) ($p['message_id'] ?? 0);
        $ck = rx_ck_open([
            'id_user' => $chatId,
            'operation' => (string) $p['operation'],
            'op_key' => (string) $p['op_key'],
            'target' => (string) ($p['target'] ?? ''),
            'final_price' => $p['final_price'],
            'source' => 'bot',
            'meta' => ['one' => (string) ($p['one'] ?? ''), 'tow' => (string) ($p['tow'] ?? ''), 'four' => (string) ($p['four'] ?? '')],
        ]);
        if ($ck === null) {
            sendmessage($chatId, rx_ck_text('dyn_ck_failed', '❌ خطایی در آماده‌سازی پرداخت رخ داد. لطفاً مراحل را دوباره انجام دهید.'), null, 'HTML');
            return null;
        }
        $preview = rx_ck_preview($ck);
        if (!$preview['can_use_wallet']) {
            $sel = rx_ck_select((string) $ck['checkout_id'], $chatId, 'none');
            $conf = rx_ck_confirm((string) $ck['checkout_id'], $chatId, 'none', 0, (int) $ck['final_price']);
            if (empty($conf['ok'])) {
                sendmessage($chatId, rx_ck_text('dyn_ck_failed', '❌ خطایی در آماده‌سازی پرداخت رخ داد. لطفاً مراحل را دوباره انجام دهید.'), null, 'HTML');
                return null;
            }
            rx_ck_bot_enter_payment($user, $conf['checkout'], $messageId);
            return $conf['checkout'];
        }
        update('user', 'Processing_value_one', (string) ($p['one'] ?? ''), 'id', $chatId);
        update('user', 'Processing_value_tow', (string) ($p['tow'] ?? ''), 'id', $chatId);
        step('ckw_choice', $chatId);
        rx_ck_bot_show($chatId, $messageId, rx_ck_choice_text($preview), rx_ck_bot_choice_keyboard($preview));
        return $ck;
    }
}

if (!function_exists('rx_ck_bot_enter_payment')) {
    function rx_ck_bot_enter_payment(array $user, array $ck, int $messageId = 0): void
    {
        global $step_payment, $textbotlang;
        $chatId = $user['id'];
        $meta = rx_ck_meta($ck);
        update('user', 'Processing_value', (string) (int) $ck['external_amount'], 'id', $chatId);
        update('user', 'Processing_value_one', (string) ($meta['one'] ?? ''), 'id', $chatId);
        update('user', 'Processing_value_tow', (string) ($meta['tow'] ?? ''), 'id', $chatId);
        if (($meta['four'] ?? '') !== '') {
            update('user', 'Processing_value_four', (string) $meta['four'], 'id', $chatId);
        }
        step('get_step_payment', $chatId);
        $text = '';
        if ((int) $ck['wallet_amount'] > 0) {
            $text .= '💼 ' . rx_ck_text('dyn_ck_wallet_part', 'سهم کیف پول') . ': ' . rx_ck_money($ck['wallet_amount']) . " تومان\n";
        }
        $text .= '💳 ' . rx_ck_text('dyn_ck_external_part', 'مبلغ پرداخت خارجی') . ': ' . rx_ck_money($ck['external_amount']) . " تومان\n\n";
        $text .= (string) ($textbotlang['users']['sell']['None-credit'] ?? rx_ck_text('dyn_ck_select_method', 'روش پرداخت را انتخاب کنید:'));
        rx_ck_bot_show($chatId, $messageId, $text, $step_payment);
    }
}

if (!function_exists('rx_ck_bot_callback')) {
    function rx_ck_bot_callback(array $user, string $datain, $messageId, $callbackQueryId = null): bool
    {
        if (!preg_match('/^ckw_(pick|ok|menu|cancel)_([a-f0-9]{16})(?:_(use|none))?$/', $datain, $m)) {
            return false;
        }
        global $keyboard;
        $chatId = $user['id'];
        $action = $m[1];
        $checkoutId = $m[2];
        $choice = $m[3] ?? '';
        $ck = rx_ck_fetch($checkoutId, $chatId);
        if ($ck === null) {
            rx_ck_bot_show($chatId, $messageId, rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), json_encode(['inline_keyboard' => []]));
            return true;
        }
        if ($action === 'cancel') {
            $res = rx_ck_cancel($checkoutId, $chatId);
            step('home', $chatId);
            if (!empty($res['ok'])) {
                rx_ck_bot_show($chatId, $messageId, rx_ck_text('dyn_ck_cancelled', '❌ خرید لغو شد و مبلغی از کیف پول شما کسر نشد.'), json_encode(['inline_keyboard' => []]));
            } else {
                rx_ck_bot_show($chatId, $messageId, rx_ck_text('dyn_ck_cancel_blocked', '⏳ این سفارش در حال پردازش است و لغو آن ممکن نیست.'), json_encode(['inline_keyboard' => []]));
            }
            return true;
        }
        if (in_array((string) $ck['status'], ['cancelled', 'expired', 'failed', 'completed'], true)) {
            rx_ck_bot_show($chatId, $messageId, rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), json_encode(['inline_keyboard' => []]));
            return true;
        }
        if ($action === 'menu') {
            $preview = rx_ck_preview($ck);
            rx_ck_bot_show($chatId, $messageId, rx_ck_choice_text($preview), rx_ck_bot_choice_keyboard($preview));
            return true;
        }
        if ($action === 'pick') {
            $sel = rx_ck_select($checkoutId, $chatId, $choice);
            if (empty($sel['ok'])) {
                $fresh = rx_ck_fetch($checkoutId, $chatId);
                $preview = $fresh !== null ? rx_ck_preview($fresh) : null;
                if ($preview !== null && in_array((string) $fresh['status'], ['awaiting_choice', 'confirmed'], true)) {
                    rx_ck_bot_show($chatId, $messageId, rx_ck_text('dyn_ck_refresh', '⚠️ موجودی شما تغییر کرده است. لطفاً دوباره انتخاب کنید.') . "\n\n" . rx_ck_choice_text($preview), rx_ck_bot_choice_keyboard($preview));
                } else {
                    rx_ck_bot_show($chatId, $messageId, rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), json_encode(['inline_keyboard' => []]));
                }
                return true;
            }
            rx_ck_bot_show($chatId, $messageId, rx_ck_summary_text($sel['preview'], $choice), rx_ck_bot_summary_keyboard($checkoutId));
            return true;
        }
        $choice = (string) $ck['wallet_choice'];
        if (!in_array($choice, ['use', 'none'], true)) {
            $preview = rx_ck_preview($ck);
            rx_ck_bot_show($chatId, $messageId, rx_ck_choice_text($preview), rx_ck_bot_choice_keyboard($preview));
            return true;
        }
        $conf = rx_ck_confirm($checkoutId, $chatId, $choice, (int) $ck['wallet_amount'], (int) $ck['external_amount']);
        if (empty($conf['ok'])) {
            $fresh = rx_ck_fetch($checkoutId, $chatId);
            if ($fresh !== null && in_array((string) $fresh['status'], ['awaiting_choice', 'confirmed'], true)) {
                $preview = rx_ck_preview($fresh);
                rx_ck_bot_show($chatId, $messageId, rx_ck_text('dyn_ck_refresh', '⚠️ موجودی شما تغییر کرده است. لطفاً دوباره انتخاب کنید.') . "\n\n" . rx_ck_choice_text($preview), rx_ck_bot_choice_keyboard($preview));
            } else {
                rx_ck_bot_show($chatId, $messageId, rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), json_encode(['inline_keyboard' => []]));
            }
            return true;
        }
        rx_ck_bot_enter_payment($user, $conf['checkout'], (int) $messageId);
        return true;
    }
}

if (!function_exists('rx_ck_bot_payment_guard')) {
    function rx_ck_bot_payment_guard(array &$user, $messageId = 0): bool
    {
        $tow = (string) ($user['Processing_value_tow'] ?? '');
        $one = (string) ($user['Processing_value_one'] ?? '');
        if (!in_array($tow, rx_ck_operations(), true) || $one === '') {
            return true;
        }
        $opKey = $tow . '|' . $one;
        $ck = rx_ck_find_for_key($user['id'], $opKey, ['awaiting_choice', 'confirmed', 'awaiting_payment']);
        if ($ck === null) {
            return true;
        }
        if ((string) $ck['status'] === 'awaiting_choice') {
            $preview = rx_ck_preview($ck);
            step('ckw_choice', $user['id']);
            rx_ck_bot_show($user['id'], (int) $messageId, rx_ck_choice_text($preview), rx_ck_bot_choice_keyboard($preview));
            return false;
        }
        $res = rx_ck_validate_payment((string) $ck['checkout_id'], $user['id']);
        if (empty($res['ok'])) {
            $fresh = rx_ck_fetch((string) $ck['checkout_id'], $user['id']);
            if ($fresh !== null && (string) $fresh['status'] === 'awaiting_choice') {
                $preview = rx_ck_preview($fresh);
                step('ckw_choice', $user['id']);
                rx_ck_bot_show($user['id'], (int) $messageId, rx_ck_text('dyn_ck_refresh', '⚠️ موجودی شما تغییر کرده است. لطفاً دوباره انتخاب کنید.') . "\n\n" . rx_ck_choice_text($preview), rx_ck_bot_choice_keyboard($preview));
            } else {
                step('home', $user['id']);
                sendmessage($user['id'], rx_ck_text('dyn_ck_not_found', '❌ این سفارش دیگر معتبر نیست. لطفاً مراحل را از ابتدا انجام دهید.'), null, 'HTML');
            }
            return false;
        }
        $user['Processing_value'] = (string) (int) $res['checkout']['external_amount'];
        update('user', 'Processing_value', $user['Processing_value'], 'id', $user['id']);
        return true;
    }
}
