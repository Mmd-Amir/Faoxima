<?php

namespace Faoxima\Wallet\Transfers;

use PDO;
use Throwable;
use RuntimeException;

final class Transfers
{
    public static function writeLedger(PDO $pdo, bool $hasKey, string $senderId, string $recipientId, int $amount, string $operationId, float $senderAfter, float $recipientAfter): void
    {
        $key = hash('sha256', $senderId . ':' . $operationId);
        $sql = 'INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id'
            . ($hasKey ? ', fulfillment_key' : '') . ") VALUES (?, ?, ?, ?, ?, ?, ?, 'user', ?" . ($hasKey ? ', ?' : '') . ')';
        $ledger = $pdo->prepare($sql);
        $out = [$senderId, 'debit', $amount, (int)$senderAfter, 'transfer_out', 'انتقال موجودی به کاربر ' . $recipientId, $operationId, $recipientId];
        $in = [$recipientId, 'credit', $amount, (int)$recipientAfter, 'transfer_in', 'انتقال موجودی از کاربر ' . $senderId, $operationId, $senderId];
        if ($hasKey) {
            $out[] = 'transfer_out:' . $key;
            $in[] = 'transfer_in:' . $key;
        }
        $ledger->execute($out);
        $ledger->execute($in);
        }

    public static function lockAccounts(PDO $pdo, string $senderId, string $recipientId): array
    {
        $ids = [$senderId, $recipientId];
        usort($ids, static fn($a, $b) => (int)$a <=> (int)$b);
        $users = [];
        $lock = $pdo->prepare('SELECT id, Balance, username FROM user WHERE id = ? FOR UPDATE');
        foreach ($ids as $id) {
            $lock->execute([$id]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) throw new RuntimeException('user_missing');
            $users[(string)$row['id']] = $row;
        }
        return $users;
        }

    public static function transfer($runtimePdo, string $senderId, string $recipientId, int $amount, string $operationId): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || $pdo->inTransaction() || $senderId === $recipientId || $amount <= 0 || !ctype_digit($senderId) || !ctype_digit($recipientId) || !preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $operationId)) {
            return ['ok' => false, 'reason' => 'invalid_transfer'];
        }
        try {
            $column = $pdo->query("SHOW COLUMNS FROM wallet_ledger LIKE 'fulfillment_key'");
            $hasKey = $column !== false && $column->fetch(PDO::FETCH_ASSOC) !== false;
            $pdo->beginTransaction();
            $users = self::lockAccounts($pdo, $senderId, $recipientId);
            $seen = $pdo->prepare("SELECT amount, ref_id FROM wallet_ledger WHERE id_user = ? AND id_order = ? AND category = 'transfer_out' ORDER BY id ASC LIMIT 1 FOR UPDATE");
            $seen->execute([$senderId, $operationId]);
            $previous = $seen->fetch(PDO::FETCH_ASSOC);
            if (is_array($previous)) {
                if ((string)$previous['ref_id'] !== $recipientId || (int)$previous['amount'] !== $amount) {
                    throw new RuntimeException('operation_conflict');
                }
                $pdo->commit();
                return ['ok' => true, 'replayed' => true, 'new_balance' => (float)$users[$senderId]['Balance'], 'operation_id' => $operationId];
            }
            $debit = $pdo->prepare('UPDATE user SET Balance = Balance - :amount WHERE id = :u AND Balance >= :minimum');
            $debit->execute([':amount' => $amount, ':u' => $senderId, ':minimum' => $amount]);
            if ($debit->rowCount() !== 1) throw new RuntimeException('insufficient_balance');
            $credit = $pdo->prepare('UPDATE user SET Balance = Balance + ? WHERE id = ?');
            $credit->execute([$amount, $recipientId]);
            if ($credit->rowCount() !== 1) throw new RuntimeException('recipient_missing');
            $senderAfter = (float)$users[$senderId]['Balance'] - $amount;
            $recipientAfter = (float)$users[$recipientId]['Balance'] + $amount;
            self::writeLedger($pdo, $hasKey, $senderId, $recipientId, $amount, $operationId, $senderAfter, $recipientAfter);
            $pdo->commit();
            try {
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                    \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $senderId);
                    \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $recipientId);
                }
            } catch (Throwable $cacheError) {
                error_log('Wallet transfer cache refresh failed: ' . $operationId);
            }
            return [
                'ok' => true, 'replayed' => false, 'operation_id' => $operationId,
                'new_balance' => $senderAfter, 'recipient_balance' => $recipientAfter,
                'sender' => $users[$senderId], 'recipient' => $users[$recipientId],
            ];
        } catch (Throwable $e) {
            $rollbackFailed = false;
            if ($pdo->inTransaction()) {
                try { $pdo->rollBack(); } catch (Throwable $rollbackError) { $rollbackFailed = true; }
            }
            error_log('Wallet transfer failed: ' . $operationId . ' ' . $e->getMessage());
            return ['ok' => false, 'reason' => $rollbackFailed ? 'outcome_unknown' : $e->getMessage()];
        }
    }
}
