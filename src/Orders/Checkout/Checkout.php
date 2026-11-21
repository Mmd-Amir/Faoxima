<?php

namespace Faoxima\Orders\Checkout;

use PDO;
use Throwable;
use RuntimeException;

final class Checkout
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

    public static function operations(): array
    {
        return ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'];
    }

    public static function livePaymentStatuses(): array
    {
        return ['Unpaid', 'pending', 'waiting', 'AwaitingHash', 'processing', 'reconciling', 'manual_review'];
    }

    public static function fulfillingPaymentStatuses(): array
    {
        return ['processing', 'reconciling', 'manual_review'];
    }

    public static function tx($runtimePdo, callable $fn)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            throw new RuntimeException('pdo_unavailable');
        }
        if ($pdo->inTransaction()) {
            return $fn($pdo);
        }
        \Faoxima\Orders\Checkout\Schema::ensureSchema($runtimePdo);
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

    public static function newId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public static function keyParts(string $opKey): array
    {
        $parts = explode('|', $opKey, 2);
        return [(string) ($parts[0] ?? ''), (string) ($parts[1] ?? '')];
    }

    public static function clearUserCache($userId): void
    {
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
            try {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $userId);
            } catch (Throwable $e) {
            }
        }
    }

    public static function walletState($runtimePdo, $userId, ?string $excludeCheckoutId = null, bool $lock = false): array
    {
        $pdo = $runtimePdo;
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

    public static function available($runtimePdo, $userId): array
    {
        try {
            return self::walletState($runtimePdo, $userId);
        } catch (Throwable $e) {
            return ['balance' => 0, 'available' => 0];
        }
    }

    public static function availableForUser(array $user): float
    {
        return (float) ($user['Balance'] ?? 0);
    }

    public static function meta(?array $ck): array
    {
        if (!is_array($ck) || empty($ck['meta'])) {
            return [];
        }
        $decoded = json_decode((string) $ck['meta'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function fetch($runtimePdo, string $checkoutId, $userId = null): ?array
    {
        $pdo = $runtimePdo;
        if ($checkoutId === '' || !($pdo instanceof PDO) || !\Faoxima\Orders\Checkout\Schema::ensureSchema($runtimePdo)) {
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

    public static function lock(PDO $pdo, string $checkoutId, $userId = null): ?array
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

    public static function expireStale($runtimePdo, bool $force = false): int
    {
        $pdo = $runtimePdo;
        static $lastRun = 0;
        $now = time();
        if (!$force && $now - $lastRun < 30) {
            return 0;
        }
        $lastRun = $now;
        if (!($pdo instanceof PDO) || !\Faoxima\Orders\Checkout\Schema::ensureSchema($runtimePdo)) {
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
            self::log('CHECKOUT_EXPIRE_FAILED', $e->getMessage());
            return 0;
        }
    }

    public static function open($runtimePdo, array $p): ?array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || !\Faoxima\Orders\Checkout\Schema::ensureSchema($runtimePdo)) {
            return null;
        }
        $userId = (string) ($p['id_user'] ?? '');
        $operation = (string) ($p['operation'] ?? '');
        $opKey = (string) ($p['op_key'] ?? '');
        $finalPrice = (int) round((float) ($p['final_price'] ?? 0));
        if ($userId === '' || !in_array($operation, self::operations(), true) || $opKey === '' || $finalPrice <= 0) {
            return null;
        }
        self::expireStale($runtimePdo);
        $now = time();
        $checkoutId = self::newId();
        $meta = isset($p['meta']) && is_array($p['meta']) ? json_encode($p['meta'], JSON_UNESCAPED_UNICODE) : null;
        try {
            self::tx($runtimePdo, function (PDO $pdo) use ($userId, $operation, $opKey, $finalPrice, $now, $checkoutId, $meta, $p) {
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
            self::log('CHECKOUT_OPEN_FAILED', $e->getMessage(), ['id_user' => $userId, 'op_key' => $opKey]);
            return null;
        }
        return self::fetch($runtimePdo, $checkoutId, $userId);
    }

    public static function preview($runtimePdo, array $ck): array
    {
        $final = (int) $ck['final_price'];
        $state = self::walletState($runtimePdo, $ck['id_user']);
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

    public static function select($runtimePdo, string $checkoutId, $userId, string $choice): array
    {
        if (!in_array($choice, ['use', 'none'], true)) {
            return ['ok' => false, 'code' => 'invalid_choice'];
        }
        try {
            $out = self::tx($runtimePdo, function (PDO $pdo) use ($runtimePdo, $checkoutId, $userId, $choice) {
                $ck = self::lock($pdo, $checkoutId, $userId);
                if ($ck === null) {
                    return ['ok' => false, 'code' => 'not_found'];
                }
                if (!in_array((string) $ck['status'], ['awaiting_choice', 'confirmed'], true)) {
                    return ['ok' => false, 'code' => 'wrong_state:' . $ck['status'], 'checkout' => $ck];
                }
                if ((int) $ck['expires_at'] > 0 && (int) $ck['expires_at'] <= time()) {
                    return ['ok' => false, 'code' => 'expired', 'checkout' => $ck];
                }
                $preview = self::preview($runtimePdo, $ck);
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
            self::log('CHECKOUT_SELECT_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId]);
            return ['ok' => false, 'code' => 'error'];
        }
        return $out;
    }

    public static function confirm($runtimePdo, string $checkoutId, $userId, string $choice, ?int $expectedWallet = null, ?int $expectedExternal = null): array
    {
        if (!in_array($choice, ['use', 'none'], true)) {
            return ['ok' => false, 'code' => 'invalid_choice'];
        }
        try {
            $out = self::tx($runtimePdo, function (PDO $pdo) use ($runtimePdo, $checkoutId, $userId, $choice, $expectedWallet, $expectedExternal) {
                $now = time();
                $ck = self::lock($pdo, $checkoutId, $userId);
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
                        return ['ok' => false, 'code' => 'refresh_required', 'checkout' => $ck, 'preview' => self::preview($runtimePdo, $ck)];
                    }
                    $pdo->prepare(
                        "UPDATE checkout_order SET wallet_choice = 'none', wallet_amount = 0, external_amount = ?, status = 'confirmed', expires_at = ?, updated_at = ? WHERE checkout_id = ?"
                    )->execute([$final, $now + RX_CK_PAYMENT_TTL, $now, $checkoutId]);
                    return ['ok' => true, 'code' => 'confirmed', 'checkout' => self::lock($pdo, $checkoutId, $userId)];
                }
                $state = self::walletState($runtimePdo, $userId, null, true);
                $wallet = min($state['available'], $final);
                $external = $final - $wallet;
                $stale = $wallet <= 0 || $external <= 1
                    || ($expectedWallet !== null && $expectedWallet !== $wallet)
                    || ($expectedExternal !== null && $expectedExternal !== $external);
                if ($stale) {
                    $pdo->prepare(
                        "UPDATE checkout_order SET wallet_choice = 'pending', wallet_amount = 0, external_amount = ?, status = 'awaiting_choice', updated_at = ? WHERE checkout_id = ?"
                    )->execute([$final, $now, $checkoutId]);
                    $ck = self::lock($pdo, $checkoutId, $userId);
                    return ['ok' => false, 'code' => 'refresh_required', 'checkout' => $ck, 'preview' => self::preview($runtimePdo, $ck)];
                }
                $pdo->prepare(
                    "UPDATE checkout_order SET wallet_choice = 'use', wallet_amount = ?, external_amount = ?, status = 'confirmed', expires_at = ?, updated_at = ? WHERE checkout_id = ?"
                )->execute([$wallet, $external, $now + RX_CK_PAYMENT_TTL, $now, $checkoutId]);
                return ['ok' => true, 'code' => 'confirmed', 'checkout' => self::lock($pdo, $checkoutId, $userId)];
            });
        } catch (Throwable $e) {
            self::log('CHECKOUT_CONFIRM_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId]);
            return ['ok' => false, 'code' => 'error'];
        }
        return $out;
    }

    public static function cancel($runtimePdo, string $checkoutId, $userId, string $reason = 'user_cancelled'): array
    {
        try {
            $out = self::tx($runtimePdo, function (PDO $pdo) use ($checkoutId, $userId, $reason) {
                $ck = self::lock($pdo, $checkoutId, $userId);
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
                    $fulfilling = "'" . implode("','", self::fulfillingPaymentStatuses()) . "'";
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
            self::log('CHECKOUT_CANCEL_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId]);
            return ['ok' => false, 'code' => 'error'];
        }
        return $out;
    }

    public static function validatePayment($runtimePdo, string $checkoutId, $userId, ?string $method = null): array
    {
        try {
            $out = self::tx($runtimePdo, function (PDO $pdo) use ($runtimePdo, $checkoutId, $userId, $method) {
                $now = time();
                $ck = self::lock($pdo, $checkoutId, $userId);
                if ($ck === null) {
                    return ['ok' => false, 'code' => 'not_found'];
                }
                $status = (string) $ck['status'];
                if (!in_array($status, ['confirmed', 'awaiting_payment'], true)) {
                    return ['ok' => false, 'code' => $status === 'awaiting_choice' ? 'choice_required' : $status, 'checkout' => $ck];
                }
                $wallet = (int) $ck['wallet_amount'];
                if ((string) $ck['wallet_choice'] === 'use' && $wallet > 0 && (string) ($ck['settle_order_id'] ?? '') === '') {
                    $state = self::walletState($runtimePdo, $userId, null, false);
                    if ($state['balance'] < $wallet) {
                        $pdo->prepare(
                            "UPDATE checkout_order SET wallet_choice = 'pending', wallet_amount = 0, external_amount = final_price, status = 'awaiting_choice', updated_at = ? WHERE checkout_id = ?"
                        )->execute([$now, $checkoutId]);
                        $ck = self::lock($pdo, $checkoutId, $userId);
                        return ['ok' => false, 'code' => 'refresh_required', 'checkout' => $ck, 'preview' => self::preview($runtimePdo, $ck)];
                    }
                }
                $pdo->prepare("UPDATE checkout_order SET status = 'awaiting_payment', payment_method = ?, updated_at = ?, expires_at = ? WHERE checkout_id = ?")
                    ->execute([$method, $now, $now + RX_CK_PAYMENT_TTL, $checkoutId]);
                $ck['status'] = 'awaiting_payment';
                return ['ok' => true, 'code' => 'validated', 'checkout' => $ck];
            });
        } catch (Throwable $e) {
            self::log('CHECKOUT_VALIDATE_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId]);
            return ['ok' => false, 'code' => 'error'];
        }
        return $out;
    }

    public static function attachPayment($runtimePdo, string $checkoutId, string $orderId, ?string $method = null): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || $checkoutId === '' || $orderId === '') {
            return false;
        }
        try {
            $stmt = $pdo->prepare("UPDATE checkout_order SET payment_order_id = ?, payment_method = COALESCE(?, payment_method), updated_at = ? WHERE checkout_id = ? AND status IN ('confirmed', 'awaiting_payment')");
            $stmt->execute([$orderId, $method, time(), $checkoutId]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            self::log('CHECKOUT_ATTACH_FAILED', $e->getMessage(), ['checkout_id' => $checkoutId, 'id_order' => $orderId]);
            return false;
        }
    }

    public static function findForKey($runtimePdo, $userId, string $opKey, array $statuses = ['confirmed', 'awaiting_payment']): ?array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || $opKey === '' || !\Faoxima\Orders\Checkout\Schema::ensureSchema($runtimePdo)) {
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
