<?php

namespace Faoxima\Payments\Gateways\Rialit;

use PDO;
use Throwable;

final class PaymentOrders
{
    public static function fetchReport($runtimePdo, $orderId)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND Payment_Method = 'rialit' LIMIT 1");
            $stmt->execute([(string) $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_FETCH_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return null;
        }
    }

    public static function updateColumns($runtimePdo, $orderId, array $columns, $onlyOpen = false): int
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || empty($columns)) {
            return 0;
        }
        $sets = [];
        $params = [];
        foreach ($columns as $name => $value) {
            $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $name);
            if ($name === '') {
                continue;
            }
            $sets[] = '`' . $name . '` = ?';
            $params[] = $value;
        }
        if (empty($sets)) {
            return 0;
        }
        $params[] = (string) $orderId;
        $sql = 'UPDATE Payment_report SET ' . implode(', ', $sets) . " WHERE id_order = ? AND Payment_Method = 'rialit'";
        if ($onlyOpen) {
            $sql .= " AND payment_Status IN ('Unpaid','pending','waiting')";
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() > 0 && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount();
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_UPDATE_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return 0;
        }
    }

    public static function markCreated($runtimePdo, $orderId, $amount, $payerId): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET rialit_created_at = COALESCE(rialit_created_at, ?),
                        rialit_payer_id = ?,
                        rialit_local_state = COALESCE(rialit_local_state, 'awaiting_payment')
                  WHERE id_order = ? AND Payment_Method = 'rialit'"
            );
            $stmt->execute([time(), (int) $payerId, (string) $orderId]);
            $report = self::fetchReport($runtimePdo, $orderId);
            return $report !== null
                && (int) ($report['rialit_created_at'] ?? 0) > 0
                && (int) ($report['rialit_payer_id'] ?? 0) === (int) $payerId
                && (int) ($report['price'] ?? 0) === (int) $amount;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_MARK_CREATED_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return false;
        }
    }

    public static function storePayment($runtimePdo, $orderId, array $payment): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET rialit_token = ?,
                        rialit_status = CASE WHEN rialit_status = 'paid' OR payment_Status = 'paid' THEN rialit_status ELSE ? END,
                        rialit_payer_amount = ?,
                        rialit_payer_fee = ?,
                        rialit_created_at = COALESCE(rialit_created_at, ?),
                        rialit_last_checked_at = ?,
                        rialit_local_state = CASE
                            WHEN payment_Status = 'paid' THEN rialit_local_state
                            WHEN ? = 'paid' THEN 'paid_verified'
                            ELSE 'awaiting_payment'
                        END
                  WHERE id_order = ? AND Payment_Method = 'rialit'
                    AND (rialit_token IS NULL OR rialit_token = '' OR BINARY rialit_token = BINARY ?)"
            );
            $stmt->execute([
                (string) $payment['token'],
                (string) $payment['status'],
                (int) $payment['payer_amount'],
                (int) $payment['payer_fee'],
                $now,
                $now,
                (string) $payment['status'],
                (string) $orderId,
                (string) $payment['token'],
            ]);
            if ($stmt->rowCount() > 0 && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            $stored = self::fetchReport($runtimePdo, $orderId);
            return $stored !== null && hash_equals((string) $payment['token'], (string) ($stored['rialit_token'] ?? ''));
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_STORE_PAYMENT_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return false;
        }
    }

    public static function reportCreatedAt(array $report): int
    {
        $created = (int) ($report['rialit_created_at'] ?? 0);
        if ($created > 0) {
            return $created;
        }
        $raw = trim((string) ($report['time'] ?? ''));
        if ($raw !== '') {
            if (ctype_digit($raw)) {
                return (int) $raw;
            }
            $raw = strtr($raw, [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            ]);
            $ts = strtotime(str_replace('/', '-', $raw));
            if ($ts !== false) {
                return (int) $ts;
            }
        }
        return 0;
    }

    public static function linkExpiresAt(array $report): int
    {
        $created = self::reportCreatedAt($report);
        return $created > 0 ? $created + RIALIT_LINK_TTL_SECONDS : 0;
    }

    public static function linkAvailable(array $report): bool
    {
        if ((int) ($report['rialit_abandoned_at'] ?? 0) > 0) {
            return false;
        }
        $token = trim((string) ($report['rialit_token'] ?? ''));
        if ($token === '') {
            return false;
        }
        if (strtolower((string) ($report['payment_Status'] ?? '')) === 'paid') {
            return false;
        }
        $remote = strtolower(trim((string) ($report['rialit_status'] ?? '')));
        if (in_array($remote, ['paid', 'expired', 'cancelled'], true)) {
            return false;
        }
        $expires = self::linkExpiresAt($report);
        return $expires > 0 && time() < $expires;
    }

    public static function userHasPending($runtimePdo, $userId): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM Payment_report
                  WHERE id_user = ?
                    AND Payment_Method = 'rialit'
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND COALESCE(rialit_abandoned_at, 0) = 0
                    AND (rialit_status IS NULL OR rialit_status IN ('pending','paid'))"
            );
            $stmt->execute([(string) $userId]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function findPendingForUser($runtimePdo, $userId, $invoice, $amount, array $context = [])
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM Payment_report
                  WHERE id_user = ?
                    AND Payment_Method = 'rialit'
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND id_invoice = ?
                    AND price = ?
                    AND COALESCE(rialit_abandoned_at, 0) = 0
                    AND COALESCE(charge_bonus, 0) = ?
                    AND COALESCE(discount_code, '') = ?
                    AND COALESCE(discount_amount, 0) = ?
                    AND COALESCE(price_before_discount, 0) = ?
                    AND (rialit_status IS NULL OR rialit_status NOT IN ('expired','cancelled'))
                    AND (rialit_created_at IS NULL OR rialit_created_at >= ?)
                    AND (? = '' OR EXISTS (SELECT 1 FROM checkout_order ck WHERE ck.checkout_id = ? AND ck.payment_order_id = Payment_report.id_order AND ck.id_user = Payment_report.id_user))
                  ORDER BY id DESC
                  LIMIT 1"
            );
            $checkoutId = (string) ($context['checkout_id'] ?? '');
            $stmt->execute([
                (string) $userId, (string) $invoice, (int) $amount,
                (int) ($context['charge_bonus'] ?? 0),
                (string) ($context['discount_code'] ?? ''),
                (float) ($context['discount_amount'] ?? 0),
                (float) ($context['price_before_discount'] ?? 0),
                time() - RIALIT_RECONCILE_SECONDS, $checkoutId, $checkoutId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_FIND_PENDING_FAILED', $e->getMessage(), ['user_id' => (string) $userId]);
            return null;
        }
    }

    public static function cancelLocally($runtimePdo, $orderId, $userId): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || $pdo->inTransaction()) {
            return ['ok' => false, 'message' => 'ثبت انصراف ممکن نیست؛ دوباره تلاش کنید.'];
        }
        $locks = [
            'faoxima_payment_init_' . $userId . '_rialit',
            'rialit_verify_' . substr(md5((string) $orderId), 0, 24),
        ];
        $acquired = [];
        try {
            foreach ($locks as $name) {
                $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
                $stmt->execute([$name]);
                if ((int) $stmt->fetchColumn() !== 1) {
                    return ['ok' => false, 'message' => 'پرداخت در حال بررسی است؛ چند لحظه بعد دوباره تلاش کنید.'];
                }
                $acquired[] = $name;
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND id_user = ? AND Payment_Method = 'rialit' LIMIT 1 FOR UPDATE");
            $stmt->execute([(string) $orderId, (string) $userId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($report)) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
            }
            $status = strtolower((string) ($report['payment_Status'] ?? ''));
            if (!in_array($status, ['unpaid','pending','waiting','expire','cancelled'], true)
                || (int) ($report['direct_payment_done'] ?? 0) === 1
                || (string) ($report['rialit_status'] ?? '') === 'paid') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'این پرداخت تأیید شده یا در حال تکمیل است و امکان انصراف ندارد.'];
            }
            $pdo->prepare("UPDATE Payment_report SET payment_Status = 'cancelled', rialit_abandoned_at = COALESCE(rialit_abandoned_at, ?), rialit_local_state = 'locally_cancelled' WHERE id_order = ? AND id_user = ? AND Payment_Method = 'rialit'")
                ->execute([time(), (string) $orderId, (string) $userId]);
            $pdo->commit();
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
                \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, $userId));
            }
            return ['ok' => true, 'message' => 'انصراف ثبت شد؛ می‌توانید پرداخت جدید بسازید. این کار لینک ریالیت را باطل نمی‌کند؛ اگر پرداخت قبلی بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_LOCAL_CANCEL_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return ['ok' => false, 'message' => 'ثبت انصراف ناموفق بود؛ دوباره تلاش کنید.'];
        } finally {
            foreach (array_reverse($acquired) as $name) {
                try {
                    $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
                } catch (Throwable $e) {
                }
            }
        }
    }
}
