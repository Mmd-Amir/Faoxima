<?php

namespace Faoxima\Payments\Fulfillment;

use PDO;
use Throwable;
use RuntimeException;

final class Store
{
    public static function clearCache(array $tables = ['Payment_report']): void
    {
        if (!method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            return;
        }
        foreach ($tables as $table) {
            try {
                \Faoxima\Core\Database\SelectCache::clearSelectCache($table);
            } catch (Throwable $e) {
            }
        }
    }

    public static function tx($runtimePdo, callable $fn)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
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
                } catch (Throwable $rollbackError) {
                }
            }
            throw $e;
        }
    }

    public static function lockPayment(PDO $pdo, string $orderId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function lockRecord(PDO $pdo, string $orderId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM payment_fulfillment WHERE id_order = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function fetch($runtimePdo, string $orderId): ?array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT * FROM payment_fulfillment WHERE id_order = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function setPaymentStatus(PDO $pdo, string $orderId, string $status, array $fromStatuses, ?string $decNote = null): int
    {
        $placeholders = implode(', ', array_fill(0, count($fromStatuses), '?'));
        $sql = "UPDATE Payment_report SET payment_Status = ?" . ($decNote !== null ? ", dec_not_confirmed = ?" : "")
            . " WHERE id_order = ? AND payment_Status IN ($placeholders) AND COALESCE(direct_payment_done, 0) = 0";
        $params = [$status];
        if ($decNote !== null) {
            $params[] = $decNote;
        }
        $params[] = $orderId;
        foreach ($fromStatuses as $from) {
            $params[] = $from;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function markPaidDone(PDO $pdo, string $orderId): int
    {
        $stmt = $pdo->prepare(
            "UPDATE Payment_report SET direct_payment_done = 1, payment_Status = CASE WHEN payment_Status IN ('processing', 'reconciling') THEN 'paid' ELSE payment_Status END "
            . "WHERE id_order = ? AND (COALESCE(direct_payment_done, 0) = 0 OR payment_Status IN ('processing', 'reconciling'))"
        );
        $stmt->execute([$orderId]);
        return $stmt->rowCount();
    }
}
