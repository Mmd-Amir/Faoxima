<?php

namespace Faoxima\Wallet\Balance;

use PDO;
use PDOStatement;
use Throwable;

final class Balances
{
    public static function read($pdo, $userId)
    {
        $stmt = $pdo->prepare("SELECT Balance FROM user WHERE id = :u");
        $stmt->execute([':u' => $userId]);
        return $stmt->fetchColumn();
        }

    public static function incrementLocked(PDO $pdo, $userId, $amount): PDOStatement
    {
        $stmt = $pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);
        return $stmt;
        }

    public static function charge($runtimePdo, $userId, $delta, $allowNegativeUpTo = 0) {
        $pdo = $runtimePdo;
        $delta = (float) $delta;
        if ($delta <= 0) return ['ok' => false, 'reason' => 'invalid-delta', 'new_balance' => null];
        $allowNegativeUpTo = max(0.0, (float) $allowNegativeUpTo);


        $minBalance = $delta - $allowNegativeUpTo;
        try {
            $stmt = $pdo->prepare("UPDATE user SET Balance = Balance - :d WHERE id = :u AND Balance >= :m");
            $stmt->execute([':d' => $delta, ':u' => $userId, ':m' => $minBalance]);
            if ($stmt->rowCount() < 1) {
                return ['ok' => false, 'reason' => 'insufficient-or-stale', 'new_balance' => null];
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $userId);
            }
            $newBal = self::read($pdo, $userId);
            return ['ok' => true, 'reason' => 'charged', 'new_balance' => (float) $newBal];
        } catch (Throwable $e) {
            error_log('balance_atomic_charge failed: ' . $e->getMessage());
            return ['ok' => false, 'reason' => 'db-error', 'new_balance' => null];
        }
    }

    public static function credit($runtimePdo, $userId, $delta) {
        $pdo = $runtimePdo;
        if (!is_numeric($delta)) return false;
        $delta = (float) $delta;
        if (!is_finite($delta) || $delta <= 0) return false;
        if ($userId === null || trim((string) $userId) === '') return false;
        if (!($pdo instanceof PDO)) return false;
        try {
            $stmt = $pdo->prepare("UPDATE user SET Balance = Balance + :d WHERE id = :u");
            $stmt->execute([':d' => $delta, ':u' => $userId]);
            if ($stmt->rowCount() < 1) {
                error_log('balance_atomic_credit: no row updated for user ' . $userId);
                return false;
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('user', 'id', $userId);
            }
            return true;
        } catch (Throwable $e) {
            error_log('balance_atomic_credit failed: ' . $e->getMessage());
            return false;
        }
    }
}
