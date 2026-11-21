<?php

namespace Faoxima\Cashback;

use PDO;
use Throwable;

final class Credits
{
    public static function creditOnce($runtimePdo, $orderId, $userId, $amount, string $cashbackKey, string $description): string
    {
        $pdo = $runtimePdo;
        $amount = is_numeric($amount) ? (int) floor((float) $amount) : 0;
        $orderId = (string) $orderId;
        $userId = (string) $userId;
        if ($amount <= 0) {
            return 'skipped';
        }
        if (!($pdo instanceof PDO) || $orderId === '' || $userId === '' || $cashbackKey === '') {
            return 'failed';
        }
        $ownTx = !$pdo->inTransaction();
        $savepoint = $ownTx ? null : 'rx_cashback_' . bin2hex(random_bytes(4));
        try {
            if ($ownTx) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            $lockSql = "SELECT id_user, direct_payment_done FROM Payment_report WHERE id_order = :o";
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $lockSql .= " FOR UPDATE";
            }
            $lock = $pdo->prepare($lockSql);
            $lock->execute([':o' => $orderId]);
            $payment = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($payment) || (int) ($payment['direct_payment_done'] ?? 0) !== 1 || (string) ($payment['id_user'] ?? '') !== $userId) {
                if ($ownTx) {
                    $pdo->rollBack();
                } else {
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                    $savepoint = null;
                }
                if (function_exists('rx_log_event')) {
                    rx_log_event('CASHBACK_SKIPPED', 'payment not fulfilled or owner mismatch', ['id_order' => $orderId, 'id_user' => $userId, 'key' => $cashbackKey]);
                }
                return 'skipped';
            }
            $dup = $pdo->prepare("SELECT COUNT(*) FROM wallet_ledger WHERE id_order = :o AND category = 'cashback' AND ref_table = 'cashback' AND ref_id = :k");
            $dup->execute([':o' => $orderId, ':k' => $cashbackKey]);
            if ((int) $dup->fetchColumn() > 0) {
                if ($ownTx) {
                    $pdo->rollBack();
                } else {
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                    $savepoint = null;
                }
                if (function_exists('rx_log_event')) {
                    rx_log_event('CASHBACK_DUPLICATE_SKIPPED', 'cashback already paid', ['id_order' => $orderId, 'id_user' => $userId, 'key' => $cashbackKey]);
                }
                return 'duplicate';
            }
            \Faoxima\Wallet\Ledger\Ledger::creditWithLedger($runtimePdo, $userId, $amount, 'cashback', $description, $orderId, 'cashback', $cashbackKey);
            if ($ownTx) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                $savepoint = null;
            }
            return 'credited';
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            } elseif ($savepoint !== null && $pdo->inTransaction()) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            error_log('rx_cashback_credit_once failed for ' . $orderId . ': ' . $e->getMessage());
            if (function_exists('rx_log_event')) {
                rx_log_event('CASHBACK_FAILED', $e->getMessage(), ['id_order' => $orderId, 'id_user' => $userId, 'amount' => $amount, 'key' => $cashbackKey]);
            }
            return 'failed';
        }
    }
}
