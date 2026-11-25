<?php

namespace Faoxima\Payments\Gateways\Tetraminator;

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
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND Payment_Method = 'tetraminator' LIMIT 1");
            $stmt->execute([(string) $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_FETCH_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return null;
        }
    }

    public static function fetchByCallbackToken($runtimePdo, $callbackToken)
    {
        $pdo = $runtimePdo;
        $callbackToken = (string) $callbackToken;
        if (!($pdo instanceof PDO) || !\Faoxima\Payments\Gateways\Tetraminator\Client::validCallbackToken($callbackToken)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE tetraminator_callback_token = ? AND Payment_Method = 'tetraminator' LIMIT 1");
            $stmt->execute([$callbackToken]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_FETCH_BY_TOKEN_FAILED', $e->getMessage());
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
        $sql = 'UPDATE Payment_report SET ' . implode(', ', $sets) . " WHERE id_order = ? AND Payment_Method = 'tetraminator'";
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
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_UPDATE_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return 0;
        }
    }

    public static function prepareOrder($runtimePdo, $orderId, $amount, $payerId)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET tetraminator_callback_token = CASE WHEN tetraminator_callback_token IS NULL OR tetraminator_callback_token = '' THEN ? ELSE tetraminator_callback_token END,
                        tetraminator_created_at = COALESCE(tetraminator_created_at, ?),
                        tetraminator_local_state = COALESCE(tetraminator_local_state, 'awaiting_payment')
                  WHERE id_order = ? AND Payment_Method = 'tetraminator'"
            );
            $stmt->execute([
                \Faoxima\Payments\Gateways\Tetraminator\Client::newCallbackToken(),
                time(),
                (string) $orderId,
            ]);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            $report = self::fetchReport($runtimePdo, $orderId);
            if ($report === null
                || (int) ($report['tetraminator_created_at'] ?? 0) <= 0
                || (int) ($report['price'] ?? 0) !== (int) $amount
                || (string) ($report['id_user'] ?? '') !== (string) $payerId
                || !\Faoxima\Payments\Gateways\Tetraminator\Client::validCallbackToken((string) ($report['tetraminator_callback_token'] ?? ''))) {
                return null;
            }
            return $report;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_PREPARE_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return null;
        }
    }

    public static function openInvoice($runtimePdo, $orderId, $amount, $payerId): array
    {
        $report = self::prepareOrder($runtimePdo, $orderId, $amount, $payerId);
        if ($report === null) {
            return \Faoxima\Payments\Gateways\Tetraminator\Client::error('PREPARE_FAILED', 'ثبت سفارش ناموفق بود', 0, true);
        }
        $callbackUrl = \Faoxima\Payments\Gateways\Tetraminator\Client::callbackUrl(null, (string) $report['tetraminator_callback_token']);
        $created = \Faoxima\Payments\Gateways\Tetraminator\Client::createInvoice($runtimePdo, (int) $amount, $callbackUrl, (int) $payerId);
        if (empty($created['success'])) {
            return $created;
        }
        if (!self::storeInvoice($runtimePdo, $orderId, (string) $created['pay_id'], (string) $created['url'])) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_STORE_INVOICE_UNRESOLVED', 'Tetraminator invoice was created but could not be stored locally', ['order_id' => (string) $orderId]);
            return \Faoxima\Payments\Gateways\Tetraminator\Client::error('STORE_FAILED', 'ثبت فاکتور ناموفق بود', 0, true);
        }
        return $created;
    }

    public static function storeInvoice($runtimePdo, $orderId, $payId, $url): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || !\Faoxima\Payments\Gateways\Tetraminator\Client::validPayId((string) $payId)) {
            return false;
        }
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET tetraminator_pay_id = ?,
                        tetraminator_payment_url = ?,
                        tetraminator_status = COALESCE(tetraminator_status, 'pending'),
                        tetraminator_last_checked_at = ?,
                        tetraminator_local_state = CASE WHEN payment_Status = 'paid' THEN tetraminator_local_state ELSE 'awaiting_payment' END
                  WHERE id_order = ? AND Payment_Method = 'tetraminator'
                    AND (tetraminator_pay_id IS NULL OR tetraminator_pay_id = '' OR BINARY tetraminator_pay_id = BINARY ?)"
            );
            $stmt->execute([(string) $payId, mb_substr((string) $url, 0, 500), $now, (string) $orderId, (string) $payId]);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            $stored = self::fetchReport($runtimePdo, $orderId);
            return $stored !== null && hash_equals((string) $payId, (string) ($stored['tetraminator_pay_id'] ?? ''));
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_STORE_INVOICE_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return false;
        }
    }

    public static function reportCreatedAt(array $report): int
    {
        $created = (int) ($report['tetraminator_created_at'] ?? 0);
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
        return $created > 0 ? $created + TETRAMINATOR_LINK_TTL_SECONDS : 0;
    }

    public static function paymentUrl(array $report): string
    {
        $url = trim((string) ($report['tetraminator_payment_url'] ?? ''));
        return (stripos($url, 'https://') === 0) ? $url : '';
    }

    public static function linkAvailable(array $report): bool
    {
        if ((int) ($report['tetraminator_abandoned_at'] ?? 0) > 0) {
            return false;
        }
        if (self::paymentUrl($report) === '' || trim((string) ($report['tetraminator_pay_id'] ?? '')) === '') {
            return false;
        }
        if (strtolower((string) ($report['payment_Status'] ?? '')) === 'paid') {
            return false;
        }
        if (strtolower(trim((string) ($report['tetraminator_status'] ?? ''))) === 'paid') {
            return false;
        }
        if (!in_array(strtolower((string) ($report['payment_Status'] ?? '')), ['unpaid', 'pending', 'waiting'], true)) {
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
                    AND Payment_Method = 'tetraminator'
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND COALESCE(tetraminator_abandoned_at, 0) = 0
                    AND tetraminator_pay_id IS NOT NULL AND tetraminator_pay_id <> ''"
            );
            $stmt->execute([(string) $userId]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static $heldUserLocks = [];

    public static function userLockName($userId): string
    {
        return 'faoxima_payment_user_' . preg_replace('/[^0-9A-Za-z_\-]/', '', (string) $userId);
    }

    public static function holdUserLock($runtimePdo, $userId, $wait = 5): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        $name = self::userLockName($userId);
        if (isset(self::$heldUserLocks[$name])) {
            return true;
        }
        try {
            $stmt = $pdo->prepare('SELECT GET_LOCK(?, ' . max(0, min(15, (int) $wait)) . ')');
            $stmt->execute([$name]);
            if ((int) $stmt->fetchColumn() !== 1) {
                return false;
            }
        } catch (Throwable $e) {
            return false;
        }
        self::$heldUserLocks[$name] = true;
        register_shutdown_function(static function () use ($pdo, $name): void {
            try {
                $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
            } catch (Throwable $e) {
            }
        });
        return true;
    }

    public static function activeForUser($runtimePdo, $userId)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM Payment_report
                  WHERE id_user = ?
                    AND Payment_Method = 'tetraminator'
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND COALESCE(tetraminator_abandoned_at, 0) = 0
                    AND tetraminator_pay_id IS NOT NULL AND tetraminator_pay_id <> ''
                  ORDER BY id DESC
                  LIMIT 1"
            );
            $stmt->execute([(string) $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_ACTIVE_LOOKUP_FAILED', $e->getMessage(), ['user_id' => (string) $userId]);
            return false;
        }
    }

    public static function resolveBlockingInvoice($runtimePdo, $runtimeSetting, $userId): ?array
    {
        $report = self::activeForUser($runtimePdo, $userId);
        if ($report === null) {
            return null;
        }
        if ($report === false) {
            return ['report' => [], 'ambiguous' => true, 'lookup_failed' => true];
        }
        $ambiguous = false;
        $expires = self::linkExpiresAt($report);
        if ($expires > 0 && time() >= $expires) {
            $result = \Faoxima\Payments\Gateways\Tetraminator\Recovery::verifyPayment($runtimePdo, $runtimeSetting, (string) $report['id_order'], [
                'source' => 'guard',
                'timeout' => 10,
                'attempts' => 1,
                'lock_wait' => 0,
            ]);
            $state = (string) ($result['state'] ?? 'error');
            $refreshed = self::activeForUser($runtimePdo, $userId);
            if ($refreshed === null) {
                return null;
            }
            if ($refreshed === false) {
                return ['report' => $report, 'ambiguous' => true];
            }
            $report = $refreshed;
            $ambiguous = in_array($state, ['retry', 'busy', 'error', 'auth_error', 'mismatch', 'not_found', 'no_invoice'], true);
        }
        return ['report' => $report, 'ambiguous' => $ambiguous];
    }

    public static function activeInvoicePayload(array $blocking): array
    {
        $report = (array) ($blocking['report'] ?? []);
        $linkAvailable = !empty($report['id_order']) && self::linkAvailable($report);
        return [
            'kind' => 'active_invoice',
            'created' => false,
            'code' => 'active_tetraminator_invoice',
            'method' => 'tetraminator',
            'order_id' => (string) ($report['id_order'] ?? ''),
            'amount' => (int) ($report['price'] ?? 0),
            'url' => $linkAvailable ? self::paymentUrl($report) : '',
            'expires_at' => self::linkExpiresAt($report),
            'link_available' => $linkAvailable,
            'awaiting_result' => !$linkAvailable,
            'ambiguous' => !empty($blocking['ambiguous']),
            'can_cancel' => true,
            'cancel_scope' => 'local',
        ];
    }

    public static function isNewInvoiceSelection($datain): bool
    {
        $datain = (string) $datain;
        if (in_array($datain, ['cart_to_offline', 'zarinpal', 'plisio', 'nowpayment', 'iranpay2', 'tonpay', 'rialit', 'tetraminator', 'blupal', 'atlaspay', 'cubepay', 'variza', 'abangateway', 'startelegrams'], true)) {
            return true;
        }
        return preg_match('/^digitaltron_(?:pay|paymode_(?:ext|ir))_[A-Za-z0-9_]{2,20}$/', $datain) === 1;
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
                    AND Payment_Method = 'tetraminator'
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND id_invoice = ?
                    AND price = ?
                    AND COALESCE(tetraminator_abandoned_at, 0) = 0
                    AND COALESCE(charge_bonus, 0) = ?
                    AND COALESCE(discount_code, '') = ?
                    AND COALESCE(discount_amount, 0) = ?
                    AND COALESCE(price_before_discount, 0) = ?
                    AND tetraminator_pay_id IS NOT NULL AND tetraminator_pay_id <> ''
                    AND tetraminator_created_at >= ?
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
                time() - TETRAMINATOR_LINK_TTL_SECONDS, $checkoutId, $checkoutId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_FIND_PENDING_FAILED', $e->getMessage(), ['user_id' => (string) $userId]);
            return null;
        }
    }

    public static function cancelLocally($runtimePdo, $orderId, $userId, $runtimeSetting = null): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || $pdo->inTransaction()) {
            return ['ok' => false, 'message' => 'ثبت انصراف ممکن نیست؛ دوباره تلاش کنید.'];
        }
        $current = self::fetchReport($runtimePdo, $orderId);
        if ($current !== null
            && (string) ($current['id_user'] ?? '') === (string) $userId
            && (int) ($current['tetraminator_abandoned_at'] ?? 0) === 0
            && trim((string) ($current['tetraminator_pay_id'] ?? '')) !== '') {
            $reconciled = \Faoxima\Payments\Gateways\Tetraminator\Recovery::verifyPayment(
                $runtimePdo,
                $runtimeSetting ?? ($GLOBALS['setting'] ?? null),
                (string) $orderId,
                ['source' => 'cancel', 'timeout' => 10, 'attempts' => 1, 'lock_wait' => 3]
            );
            $reconciledState = (string) ($reconciled['state'] ?? 'error');
            if ($reconciledState === 'paid') {
                return ['ok' => false, 'paid' => true, 'message' => 'پرداخت این فاکتور در تترامیناتور تأیید شده است و در حال تکمیل می‌باشد؛ امکان انصراف وجود ندارد.'];
            }
            if (in_array($reconciledState, ['retry', 'busy', 'mismatch'], true) || ($reconciledState === 'error' && (string) ($reconciled['reason'] ?? '') === 'exception')) {
                return ['ok' => false, 'ambiguous' => true, 'message' => 'وضعیت پرداخت در تترامیناتور هم‌اکنون قابل بررسی نیست؛ برای جلوگیری از انصراف یک پرداخت تأییدشده، چند لحظه بعد دوباره تلاش کنید.'];
            }
        }
        $locks = [
            self::userLockName($userId),
            'faoxima_payment_init_' . $userId . '_tetraminator',
            'tetraminator_verify_' . substr(md5((string) $orderId), 0, 24),
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
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND id_user = ? AND Payment_Method = 'tetraminator' LIMIT 1 FOR UPDATE");
            $stmt->execute([(string) $orderId, (string) $userId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($report)) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
            }
            $status = strtolower((string) ($report['payment_Status'] ?? ''));
            if (!in_array($status, ['unpaid', 'pending', 'waiting', 'expire', 'cancelled'], true)
                || (int) ($report['direct_payment_done'] ?? 0) === 1
                || strtolower((string) ($report['tetraminator_status'] ?? '')) === 'paid') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'این پرداخت تأیید شده یا در حال تکمیل است و امکان انصراف ندارد.'];
            }
            $pdo->prepare("UPDATE Payment_report SET payment_Status = 'cancelled', tetraminator_abandoned_at = COALESCE(tetraminator_abandoned_at, ?), tetraminator_local_state = 'locally_cancelled' WHERE id_order = ? AND id_user = ? AND Payment_Method = 'tetraminator'")
                ->execute([time(), (string) $orderId, (string) $userId]);
            $pdo->commit();
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
                \Faoxima\Core\Cache\RedisCache::rx_redis_del(\Faoxima\Core\Cache\RedisCache::rx_payment_status_cache_key($orderId, $userId));
            }
            return ['ok' => true, 'message' => 'انصراف ثبت شد؛ می‌توانید پرداخت جدید بسازید. این کار لینک تترامیناتور را باطل نمی‌کند؛ اگر پرداخت قبلی بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_LOCAL_CANCEL_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
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
