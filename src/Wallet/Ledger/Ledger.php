<?php

namespace Faoxima\Wallet\Ledger;

use Throwable;
use RuntimeException;

final class Ledger
{
    public static function record($runtimePdo, $userId, string $direction, $amount, string $category, ?string $description = null, ?string $idOrder = null, ?string $refTable = null, ?string $refId = null): bool {
        $pdo = $runtimePdo;
        $amount = (int) round((float) $amount);
        if ($amount <= 0 || !in_array($direction, ['credit', 'debit'], true)) return false;
        try {
            $balanceAfter = null;
            $bal = \Faoxima\Wallet\Balance\Balances::read($pdo, $userId);
            if ($bal !== false) {
                $balanceAfter = (int) $bal;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id) "
                . "VALUES (:id_user, :direction, :amount, :balance_after, :category, :description, :id_order, :ref_table, :ref_id)"
            );
            return $stmt->execute([
                ':id_user' => $userId,
                ':direction' => $direction,
                ':amount' => $amount,
                ':balance_after' => $balanceAfter,
                ':category' => $category,
                ':description' => $description,
                ':id_order' => $idOrder,
                ':ref_table' => $refTable,
                ':ref_id' => $refId,
            ]);
        } catch (Throwable $e) {
            error_log('wallet_ledger_record failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function creditAndRecord($runtimePdo, $userId, $amount, string $category, callable $description, ?string $orderId, string $refTable, string $refId): string
    {
        if (!\Faoxima\Wallet\Balance\Balances::credit($runtimePdo, $userId, $amount)) {
            return 'credit_failed';
        }
        if (!self::record($runtimePdo, $userId, 'credit', $amount, $category, $description(), $orderId, $refTable, $refId)) {
            return 'ledger_failed';
        }
        return 'credited';
        }

    public static function creditWithLedger($runtimePdo, string $userId, int $amount, string $category, string $description, ?string $orderId, string $refTable, string $refId): void
    {
        $outcome = self::creditAndRecord($runtimePdo, $userId, $amount, $category, static fn () => $description, $orderId, $refTable, $refId);
        if ($outcome === 'credit_failed') {
            throw new RuntimeException('balance_atomic_credit returned false');
        }
        if ($outcome === 'ledger_failed') {
            throw new RuntimeException('wallet_ledger_record failed');
        }
        }
}
