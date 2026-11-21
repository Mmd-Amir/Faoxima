<?php

namespace Faoxima\Payments\Receipts;

use PDO;
use Throwable;

final class Cancellation
{
    public static function cardPaymentMethods(): array
    {
        return ['cart to cart', 'carttocart_pv'];
    }

    public static function invoiceHasInvalidatedAt(PDO $pdo): bool
    {
        static $has = null;
        if ($has !== null) return $has;
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'invalidated_at'");
            $has = $chk !== false && $chk->rowCount() === 1;
        } catch (Throwable $e) {
            $has = false;
        }
        return $has;
    }

    public static function cancelAbandonedCardPayment($runtimePdo, string $orderId, $userId, string $reason, array $opts = []): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'reason' => 'pdo_unavailable'];
        }
        $orderId = trim($orderId);
        $userId = trim((string) $userId);
        if ($orderId === '' || $userId === '') {
            return ['ok' => false, 'reason' => 'invalid_args'];
        }
        $targetStatus = (string) ($opts['status'] ?? 'cancelled');
        if (!in_array($targetStatus, ['cancelled', 'expire'], true)) {
            $targetStatus = 'cancelled';
        }
        $requiredSource = isset($opts['source']) ? (string) $opts['source'] : null;
        $preserveKey = isset($opts['preserve_invoice_key']) ? trim((string) $opts['preserve_invoice_key']) : '';
        $marker = substr(($targetStatus === 'expire' ? 'expired:' : 'cancelled:') . $reason, 0, 190);
        $now = date('Y/m/d H:i:s');
        $invoiceInvalidated = false;
        $ownTx = !$pdo->inTransaction();

        try {
            if ($ownTx) $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = :o LIMIT 1 FOR UPDATE");
            $stmt->execute([':o' => $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $refuse = null;
            if (!is_array($row)) {
                $refuse = 'not_found';
            } elseif ((string) ($row['id_user'] ?? '') !== $userId) {
                $refuse = 'not_owner';
            } elseif (!in_array((string) ($row['Payment_Method'] ?? ''), self::cardPaymentMethods(), true)) {
                $refuse = 'not_card';
            } elseif ($requiredSource !== null && (string) ($row['source'] ?? '') !== $requiredSource) {
                $refuse = 'source_mismatch';
            } else {
                $status = strtolower((string) ($row['payment_Status'] ?? ''));
                $rowMarker = trim((string) ($row['dec_not_confirmed'] ?? ''));
                $delivered = (int) ($row['report_message_id'] ?? 0) > 0
                    || trim((string) ($row['private_receipt_targets'] ?? '')) !== '';
                if (!in_array($status, ['unpaid', 'pending'], true)) {
                    $refuse = 'status_' . ($status === '' ? 'empty' : $status);
                } elseif ($rowMarker !== '') {
                    $refuse = 'receipt_in_progress';
                } elseif ($delivered || (int) ($row['direct_payment_done'] ?? 0) !== 0) {
                    $refuse = 'receipt_delivered';
                }
            }
            if ($refuse === null && is_callable([\Faoxima\Payments\Receipts\ReceiptOrders::class, 'cancelGuard'])) {
                $refuse = \Faoxima\Payments\Receipts\ReceiptOrders::cancelGuard($runtimePdo, $pdo, $orderId, $reason, $targetStatus);
            }
            if ($refuse !== null) {
                if ($ownTx) $pdo->rollBack();
                return ['ok' => false, 'reason' => $refuse];
            }

            $upd = $pdo->prepare(
                "UPDATE Payment_report SET payment_Status = :s, dec_not_confirmed = :d, at_updated = :au
                  WHERE id_order = :o AND id_user = :u AND payment_Status IN ('Unpaid','pending')
                    AND (dec_not_confirmed IS NULL OR dec_not_confirmed = '')
                    AND COALESCE(direct_payment_done, 0) = 0"
            );
            $upd->execute([':s' => $targetStatus, ':d' => $marker, ':au' => $now, ':o' => $orderId, ':u' => $userId]);
            if ($upd->rowCount() !== 1) {
                if ($ownTx) $pdo->rollBack();
                return ['ok' => false, 'reason' => 'race_lost'];
            }

            $idInvoice = (string) ($row['id_invoice'] ?? '');
            $parts = explode('|', $idInvoice, 2);
            $invUsername = trim((string) ($parts[1] ?? ''));
            if (($parts[0] ?? '') === 'getconfigafterpay' && $invUsername !== '' && $idInvoice !== $preserveKey) {
                $live = $pdo->prepare(
                    "SELECT COUNT(*) FROM Payment_report
                      WHERE id_user = :u AND id_invoice = :i AND id_order <> :o
                        AND COALESCE(payment_Status, '') NOT IN ('expire','cancelled')"
                );
                $live->execute([':u' => $userId, ':i' => $idInvoice, ':o' => $orderId]);
                if ((int) $live->fetchColumn() === 0) {
                    if (self::invoiceHasInvalidatedAt($pdo)) {
                        $inv = $pdo->prepare("UPDATE invoice SET Status = 'Unsuccessful', invalidated_at = :t WHERE id_user = :u AND username = :n AND Status = 'unpaid'");
                        $inv->execute([':t' => time(), ':u' => $userId, ':n' => $invUsername]);
                    } else {
                        $inv = $pdo->prepare("UPDATE invoice SET Status = 'Unsuccessful' WHERE id_user = :u AND username = :n AND Status = 'unpaid'");
                        $inv->execute([':u' => $userId, ':n' => $invUsername]);
                    }
                    $invoiceInvalidated = $inv->rowCount() > 0;
                }
            }

            if ($ownTx) $pdo->commit();
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                try { $pdo->rollBack(); } catch (Throwable $re) {}
            }
            if (function_exists('rx_log_event')) {
                rx_log_event('CARD_PAYMENT_CANCEL_FAILED', $e->getMessage(), [
                    'id_order' => $orderId,
                    'id_user' => $userId,
                    'reason' => $reason,
                ]);
            }
            return ['ok' => false, 'reason' => 'db_error'];
        }

        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            if ($invoiceInvalidated) \Faoxima\Core\Database\SelectCache::clearSelectCache('invoice');
        }
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
            \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, $userId));
        }

        return ['ok' => true, 'status' => $targetStatus, 'invoice_invalidated' => $invoiceInvalidated, 'report' => $row];
    }

    public static function cancelAbandonedCardPaymentsForUser($runtimePdo, $userId, string $reason, array $opts = []): int
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) return 0;
        $userId = trim((string) $userId);
        if ($userId === '') return 0;

        $methods = isset($opts['methods']) && is_array($opts['methods']) ? array_values($opts['methods']) : self::cardPaymentMethods();
        if (empty($methods)) return 0;
        $sql = "SELECT id_order FROM Payment_report
                 WHERE id_user = ? AND payment_Status IN ('Unpaid','pending')
                   AND Payment_Method IN (" . implode(',', array_fill(0, count($methods), '?')) . ")
                   AND (dec_not_confirmed IS NULL OR dec_not_confirmed = '')";
        $params = array_merge([$userId], $methods);
        if (isset($opts['source'])) {
            $sql .= " AND source = ?";
            $params[] = (string) $opts['source'];
        }
        if (!empty($opts['created_before'])) {
            $sql .= " AND time < ?";
            $params[] = (string) $opts['created_before'];
        }
        $sql .= " ORDER BY id ASC LIMIT 50";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('CARD_PAYMENT_CANCEL_FAILED', $e->getMessage(), ['id_user' => $userId, 'reason' => $reason]);
            }
            return 0;
        }

        $cancelOpts = [];
        if (isset($opts['source'])) $cancelOpts['source'] = (string) $opts['source'];
        if (isset($opts['preserve_invoice_key'])) $cancelOpts['preserve_invoice_key'] = (string) $opts['preserve_invoice_key'];
        $count = 0;
        foreach ($orders as $orderId) {
            $res = self::cancelAbandonedCardPayment($runtimePdo, (string) $orderId, $userId, $reason, $cancelOpts);
            if (!empty($res['ok'])) $count++;
        }
        return $count;
    }

    public static function recoverStaleReceiptUploads($runtimePdo, int $olderThanSeconds = 600, int $limit = 50): array
    {
        $pdo = $runtimePdo;
        $out = ['waiting' => 0, 'unpaid' => 0, 'failed' => 0];
        if (!($pdo instanceof PDO)) return $out;
        $cutoff = date('Y/m/d H:i:s', time() - max(120, $olderThanSeconds));
        $methods = self::cardPaymentMethods();

        try {
            $stmt = $pdo->prepare(
                "SELECT id_order, id_user, report_message_id, private_receipt_targets FROM Payment_report
                  WHERE payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'
                    AND Payment_Method IN (" . implode(',', array_fill(0, count($methods), '?')) . ")
                    AND COALESCE(NULLIF(at_updated, ''), time) < ?
                  ORDER BY id ASC LIMIT " . max(1, $limit)
            );
            $stmt->execute(array_merge($methods, [$cutoff]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_UPLOAD_RECOVERY_FAILED', $e->getMessage(), ['stage' => 'scan']);
            }
            $out['failed']++;
            return $out;
        }

        $toWaiting = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting', dec_not_confirmed = 'receipt-submitted', at_updated = :au WHERE id_order = :o AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'");
        $toUnpaid = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = NULL, at_updated = NULL WHERE id_order = :o AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading' AND (report_message_id IS NULL OR report_message_id = 0) AND (private_receipt_targets IS NULL OR private_receipt_targets = '')");

        foreach ($rows as $row) {
            $orderId = (string) $row['id_order'];
            $delivered = (int) ($row['report_message_id'] ?? 0) > 0
                || trim((string) ($row['private_receipt_targets'] ?? '')) !== '';
            try {
                if ($delivered) {
                    $toWaiting->execute([':au' => date('Y/m/d H:i:s'), ':o' => $orderId]);
                    if ($toWaiting->rowCount() === 1) $out['waiting']++;
                } else {
                    $toUnpaid->execute([':o' => $orderId]);
                    if ($toUnpaid->rowCount() === 1) $out['unpaid']++;
                }
                if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
                    \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, $row['id_user']));
                }
            } catch (Throwable $e) {
                $out['failed']++;
                if (function_exists('rx_log_event')) {
                    rx_log_event('RECEIPT_UPLOAD_RECOVERY_FAILED', $e->getMessage(), ['id_order' => $orderId, 'delivered' => $delivered]);
                }
            }
        }
        if (($out['waiting'] + $out['unpaid']) > 0 && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
        }
        return $out;
    }

    public static function receiptHardDelete($runtimePdo, string $orderId): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) return false;
        if (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_is_verify_order') && \Faoxima\Payments\Receipts\ReceiptOrders::isVerifyOrder($runtimePdo, $orderId)) return false;
        $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :id_order AND " . \Faoxima\Payments\Receipts\ManualReview::receiptScopeSql());
        $stmt->bindValue(':id_order', $orderId, PDO::PARAM_STR);
        $stmt->execute();
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
        return $stmt->rowCount() > 0;
    }
}
