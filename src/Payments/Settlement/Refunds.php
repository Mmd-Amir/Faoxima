<?php

namespace Faoxima\Payments\Settlement;

use Faoxima\Orders\Discounts\SellDiscounts;
use Faoxima\Wallet\Ledger\Ledger;
use PDO;
use RuntimeException;
use Throwable;

final class Refunds
{
    public static function walletChargeOnce(
        PDO $pdo, array $user, string $operation, string $invoice, float $amount, string $discount, string $description
    ): string {
        if ((string) ($user['id'] ?? '') === '' || $operation === '' || $invoice === '' || !is_finite($amount) || $amount < 0) {
            return 'failed';
        }
        $own = !$pdo->inTransaction();
        $savepoint = 'wallet_refund_' . bin2hex(random_bytes(4));
        try {
            if ($own) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            $outcome = 'refunded';
            if ($amount > 0) {
                $identity = [(string) $user['id'], $operation, $invoice];
                $debit = $pdo->prepare("SELECT amount FROM wallet_ledger
                    WHERE id_user = ? AND id_order = ? AND ref_table = 'invoice' AND ref_id = ?
                    AND direction = 'debit' AND category IN ('purchase', 'service_action') LIMIT 2 FOR UPDATE");
                if (!$debit->execute($identity)) {
                    throw new RuntimeException('wallet_refund_debit_lookup_failed');
                }
                $amounts = $debit->fetchAll(PDO::FETCH_COLUMN);
                if (count($amounts) !== 1 || (int) $amounts[0] !== (int) round($amount)) {
                    throw new RuntimeException('wallet_refund_debit_mismatch');
                }
                $credit = $pdo->prepare("SELECT id FROM wallet_ledger
                    WHERE id_user = ? AND id_order = ? AND ref_table = 'invoice' AND ref_id = ?
                    AND direction = 'credit' AND category = 'refund' LIMIT 1 FOR UPDATE");
                if (!$credit->execute($identity)) {
                    throw new RuntimeException('wallet_refund_credit_lookup_failed');
                }
                if ($credit->fetchColumn() !== false) {
                    $outcome = 'duplicate';
                }
            }
            if ($outcome === 'refunded') {
                if (!SellDiscounts::releaseWalletDiscount($pdo, $discount, $user, $operation)) {
                    throw new RuntimeException('wallet_refund_discount_release_failed');
                }
                if ($amount > 0 && Ledger::creditAndRecord(
                    $pdo, $user['id'], $amount, 'refund', static fn () => $description, $operation, 'invoice', $invoice
                ) !== 'credited') {
                    throw new RuntimeException('wallet_refund_ledger_failed');
                }
            }
            if ($own) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $outcome;
        } catch (Throwable $error) {
            try {
                if ($own) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } else {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            } catch (Throwable $rollbackError) {
                error_log('Wallet refund rollback failed for ' . $operation . ': ' . $rollbackError->getMessage());
            }
            error_log('Wallet refund failed for ' . $operation . ': ' . $error->getMessage());
            return 'failed';
        }
    }

    public static function paymentOnce($runtimePdo, $orderId, $userId, $amount, string $description, $invoiceId = null): string
    {
        $pdo = $runtimePdo;
        $amount = is_numeric($amount) ? (int) round((float) $amount) : 0;
        $orderId = (string) $orderId;
        $userId = (string) $userId;
        if (!($pdo instanceof PDO) || $amount <= 0 || $orderId === '' || $userId === '') {
            if (function_exists('rx_log_event')) {
                rx_log_event('REFUND_REJECTED', 'invalid refund input', ['id_order' => $orderId, 'id_user' => $userId, 'amount' => $amount]);
            }
            return 'failed';
        }
        $invoiceId = ($invoiceId === null || (string) $invoiceId === '') ? null : (string) $invoiceId;
        $ownTx = !$pdo->inTransaction();
        $savepoint = $ownTx ? null : 'rx_payment_refund_' . bin2hex(random_bytes(4));
        try {
            if ($ownTx) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            $note = '[auto-refund: ' . $description . ' at ' . date('Y-m-d H:i:s') . ']';
            $claim = $pdo->prepare(
                "UPDATE Payment_report SET direct_payment_done = 1, "
                . "dec_not_confirmed = CASE WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :n1 ELSE CONCAT(dec_not_confirmed, ' | ', :n2) END "
                . "WHERE id_order = :o AND id_user = :u AND (direct_payment_done IS NULL OR direct_payment_done = 0) "
                . "AND (dec_not_confirmed IS NULL OR dec_not_confirmed NOT LIKE '%auto-refund%')"
            );
            $claim->execute([':n1' => $note, ':n2' => $note, ':o' => $orderId, ':u' => $userId]);
            if ($claim->rowCount() < 1) {
                if ($ownTx) {
                    $pdo->rollBack();
                } else {
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                    $savepoint = null;
                }
                if (function_exists('rx_log_event')) {
                    rx_log_event('REFUND_DUPLICATE_SKIPPED', 'refund already applied or payment already fulfilled', ['id_order' => $orderId, 'id_user' => $userId]);
                }
                return 'duplicate';
            }
            \Faoxima\Wallet\Ledger\Ledger::creditWithLedger($runtimePdo, $userId, $amount, 'refund', $description, $orderId, $invoiceId !== null ? 'invoice' : 'Payment_report', $invoiceId ?? $orderId);
            if ($ownTx) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                $savepoint = null;
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            }
            if (function_exists('rx_log_event')) {
                rx_log_event('REFUND_APPLIED', $description, ['id_order' => $orderId, 'id_user' => $userId, 'amount' => $amount, 'id_invoice' => $invoiceId]);
            }
            return 'refunded';
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            } elseif ($savepoint !== null && $pdo->inTransaction()) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            error_log('rx_refund_payment_once failed for ' . $orderId . ': ' . $e->getMessage());
            if (function_exists('rx_log_event')) {
                rx_log_event('REFUND_FAILED', $e->getMessage(), ['id_order' => $orderId, 'id_user' => $userId, 'amount' => $amount, 'id_invoice' => $invoiceId]);
            }
            return 'failed';
        }
    }

    public static function invoiceOnce($runtimePdo, string $invoiceId, string $claimSql, array $claimParams, $userId, $amount, string $description): string
    {
        $pdo = $runtimePdo;
        $amount = is_numeric($amount) ? (int) round((float) $amount) : -1;
        $userId = (string) $userId;
        if (!($pdo instanceof PDO) || $invoiceId === '' || $userId === '' || $amount < 0) {
            return 'failed';
        }
        $ownTx = !$pdo->inTransaction();
        $savepoint = $ownTx ? null : 'rx_invoice_refund_' . bin2hex(random_bytes(4));
        try {
            if ($ownTx) {
                $pdo->beginTransaction();
            } else {
                $pdo->exec('SAVEPOINT ' . $savepoint);
            }
            $claim = $pdo->prepare($claimSql);
            $claim->execute($claimParams);
            if ($claim->rowCount() < 1) {
                if ($ownTx) {
                    $pdo->rollBack();
                } else {
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                    $savepoint = null;
                }
                return 'claimed';
            }
            if ($amount > 0) {
                \Faoxima\Wallet\Ledger\Ledger::creditWithLedger($runtimePdo, $userId, $amount, 'refund', $description, null, 'invoice', $invoiceId);
            }
            if ($ownTx) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                $savepoint = null;
            }
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('invoice', 'id_invoice', $invoiceId);
            }
            if (function_exists('rx_log_event')) {
                rx_log_event('REFUND_APPLIED', $description, ['id_invoice' => $invoiceId, 'id_user' => $userId, 'amount' => $amount]);
            }
            return 'refunded';
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            } elseif ($savepoint !== null && $pdo->inTransaction()) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            error_log('rx_refund_invoice_once failed for ' . $invoiceId . ': ' . $e->getMessage());
            if (function_exists('rx_log_event')) {
                rx_log_event('REFUND_FAILED', $e->getMessage(), ['id_invoice' => $invoiceId, 'id_user' => $userId, 'amount' => $amount]);
            }
            return 'failed';
        }
    }
}
