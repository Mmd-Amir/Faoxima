<?php

namespace Faoxima\Payments\Gateways\Tetraminator;

use PDO;
use Throwable;

final class Recovery
{
    private const LOCAL_EXPIRY_GRACE_SECONDS = 600;
    private const INCOMPLETE_INVOICE_SECONDS = 1800;
    private const MIDDLE_AGE_SECONDS = 21600;
    private const VERIFIED_STATES = ['paid_verified', 'paid_pending_fulfillment', 'completed'];

    public static function reopenCancelledForConfirmation($runtimePdo, $orderId): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET payment_Status = 'expire', tetraminator_local_state = 'late_confirmation'
                  WHERE id_order = ?
                    AND Payment_Method = 'tetraminator'
                    AND payment_Status = 'cancelled'"
            );
            $stmt->execute([(string) $orderId]);
            if ($stmt->rowCount() > 0 && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_REOPEN_CANCELLED_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return false;
        }
    }

    public static function normalizeAmount($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[0-9]{1,15}$/', $value) === 1) {
            return (int) $value;
        }
        if (is_float($value) && is_finite($value) && floor($value) === $value && $value >= 0 && $value < 1.0E15) {
            return (int) $value;
        }
        return null;
    }

    public static function normalizeRemoteStatus($value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9_\-]/', '', $value);
        return substr((string) $value, 0, 32);
    }

    public static function verifyPayment($runtimePdo, $runtimeSetting, $orderId, array $options = []): array
    {
        $pdo = $runtimePdo;
        $orderId = trim((string) $orderId);
        if ($orderId === '' || !($pdo instanceof PDO)) {
            return ['state' => 'error', 'reason' => 'unavailable'];
        }
        $report = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null) {
            return ['state' => 'not_found', 'reason' => 'order_not_found'];
        }
        if (strtolower((string) ($report['payment_Status'] ?? '')) === 'paid' && (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return ['state' => 'paid', 'reason' => '', 'confirmed' => true, 'fulfilled' => true, 'already' => true];
        }

        $lockWait = max(0, min(15, (int) ($options['lock_wait'] ?? 0)));
        $timeout = max(3, min(40, (int) ($options['timeout'] ?? TETRAMINATOR_CRON_TIMEOUT_SECONDS)));
        $attempts = max(1, min(3, (int) ($options['attempts'] ?? 2)));
        $source = (string) ($options['source'] ?? 'poll');

        $lockName = 'tetraminator_verify_' . substr(md5($orderId), 0, 24);
        $lock = null;
        try {
            $lock = $pdo->prepare('SELECT GET_LOCK(?, ' . $lockWait . ')');
            $lock->execute([$lockName]);
            if ((int) $lock->fetchColumn() !== 1) {
                $lock = null;
                return ['state' => 'busy', 'reason' => 'locked'];
            }
            $report = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::fetchReport($runtimePdo, $orderId);
            if ($report === null) {
                return ['state' => 'retry', 'reason' => 'report_unavailable'];
            }
            $payId = trim((string) ($report['tetraminator_pay_id'] ?? ''));
            $expectedAmount = (int) ($report['price'] ?? 0);
            if ($expectedAmount <= 0) {
                return ['state' => 'error', 'reason' => 'amount_missing'];
            }
            if ($payId === '' || !\Faoxima\Payments\Gateways\Tetraminator\Client::validPayId($payId)) {
                return ['state' => 'no_invoice', 'reason' => 'invoice_identifier_missing'];
            }

            $localStatus = strtolower(trim((string) ($report['payment_Status'] ?? '')));
            $alreadyVerified = (int) ($report['tetraminator_confirmed_at'] ?? 0) > 0
                && strtolower((string) ($report['tetraminator_status'] ?? '')) === 'paid'
                && in_array((string) ($report['tetraminator_local_state'] ?? ''), self::VERIFIED_STATES, true)
                && (int) ($report['tetraminator_amount'] ?? 0) === $expectedAmount;

            $paidAmount = $expectedAmount;
            if (!$alreadyVerified) {
                $check = \Faoxima\Payments\Gateways\Tetraminator\Client::inquire($runtimePdo, $payId, $timeout, $attempts);
                if (!is_array($check) || (isset($check['success']) && $check['success'] === false)) {
                    $code = is_array($check) ? (string) ($check['error_code'] ?? 'ERROR') : 'ERROR';
                    if ($code === 'AUTH_FAILED' || $code === 'FORBIDDEN' || $code === 'MISSING_API_KEY') {
                        \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                            'tetraminator_local_state' => 'auth_error',
                            'tetraminator_last_checked_at' => time(),
                        ]);
                        return ['state' => 'auth_error', 'reason' => strtolower($code)];
                    }
                    if ($code === 'NOT_FOUND') {
                        \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                            'tetraminator_local_state' => 'remote_not_found',
                            'tetraminator_last_checked_at' => time(),
                        ]);
                        return ['state' => 'error', 'reason' => 'remote_not_found'];
                    }
                    $transient = !empty($check['retryable']) || in_array($code, ['TRANSPORT_ERROR', 'UPSTREAM_UNAVAILABLE', 'RATE_LIMIT_EXCEEDED', 'BAD_RESPONSE'], true);
                    return ['state' => $transient ? 'retry' : 'error', 'reason' => strtolower($code)];
                }
                $statusCode = (int) ($check['status_code'] ?? 0);
                if ($statusCode !== 200) {
                    return ['state' => 'retry', 'reason' => 'http_' . $statusCode];
                }

                $remotePayId = isset($check['pay_id']) && is_scalar($check['pay_id']) ? trim((string) $check['pay_id']) : '';
                $remoteStatusRaw = isset($check['payment_status']) && is_scalar($check['payment_status']) ? (string) $check['payment_status'] : '';
                $remoteStatus = self::normalizeRemoteStatus($remoteStatusRaw);
                $remoteAmount = array_key_exists('amount', $check) ? self::normalizeAmount($check['amount']) : null;
                $statusFlag = $check['status'] ?? null;

                if ($remotePayId === '' || !hash_equals($payId, $remotePayId)) {
                    if ((string) ($report['tetraminator_local_state'] ?? '') !== 'identity_mismatch') {
                        \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_VERIFY_IDENTITY_MISMATCH', 'Tetraminator inquiry did not return the stored invoice identifier', [
                            'order_id' => $orderId,
                        ]);
                    }
                    \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                        'tetraminator_last_checked_at' => time(),
                        'tetraminator_local_state' => 'identity_mismatch',
                    ]);
                    return ['state' => 'mismatch', 'reason' => 'identity'];
                }
                if (!is_bool($statusFlag) || $remoteStatus === '') {
                    return ['state' => 'retry', 'reason' => 'invalid_remote_payload'];
                }
                $remotePaid = $statusFlag === true && $remoteStatusRaw === 'paid';
                if (($statusFlag === true) !== ($remoteStatusRaw === 'paid')) {
                    if ((string) ($report['tetraminator_local_state'] ?? '') !== 'status_conflict') {
                        \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_VERIFY_STATUS_CONFLICT', 'Tetraminator inquiry status flag and payment status disagree', [
                            'order_id' => $orderId,
                            'status_flag' => $statusFlag ? 'true' : 'false',
                            'payment_status' => $remoteStatus,
                        ]);
                    }
                    \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                        'tetraminator_last_checked_at' => time(),
                        'tetraminator_local_state' => 'status_conflict',
                    ]);
                    return ['state' => 'retry', 'reason' => 'status_conflict'];
                }
                if (($remotePaid && $remoteAmount === null) || ($remoteAmount !== null && $remoteAmount !== $expectedAmount)) {
                    if ((string) ($report['tetraminator_local_state'] ?? '') !== 'amount_mismatch') {
                        \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_VERIFY_AMOUNT_MISMATCH', 'Tetraminator inquiry amount does not match the local order', [
                            'order_id' => $orderId,
                            'expected' => $expectedAmount,
                            'received' => $remoteAmount === null ? 'missing' : $remoteAmount,
                        ]);
                    }
                    \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                        'tetraminator_status' => $remoteStatus,
                        'tetraminator_last_checked_at' => time(),
                        'tetraminator_local_state' => 'amount_mismatch',
                    ]);
                    return ['state' => 'mismatch', 'reason' => 'amount'];
                }

                if (!$remotePaid) {
                    return self::applyNotPaid($runtimePdo, $report, $remoteStatus);
                }

                $paidAmount = (int) $remoteAmount;
                \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                    'tetraminator_status' => 'paid',
                    'tetraminator_amount' => $paidAmount,
                    'tetraminator_confirmed_at' => time(),
                    'tetraminator_last_checked_at' => time(),
                    'tetraminator_local_state' => 'paid_verified',
                ]);
                $persisted = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::fetchReport($runtimePdo, $orderId);
                if ($persisted === null || strtolower((string) ($persisted['tetraminator_status'] ?? '')) !== 'paid' || (int) ($persisted['tetraminator_confirmed_at'] ?? 0) <= 0) {
                    return ['state' => 'retry', 'reason' => 'status_persistence_failed'];
                }
                $report = $persisted;
            }

            $previousStatus = strtolower(trim((string) ($report['payment_Status'] ?? '')));
            if ($previousStatus === 'cancelled') {
                self::reopenCancelledForConfirmation($runtimePdo, $orderId);
            }
            if (!method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_confirm_paid')) {
                require_once dirname(__DIR__, 4) . '/src/bootstrap.php';
            }

            $lines = [
                '🧾 شناسه فاکتور تترامیناتور: ' . $payId,
                '💸 مبلغ فاکتور: ' . number_format($paidAmount) . ' تومان',
            ];
            if (in_array($previousStatus, ['cancelled', 'expire'], true)) {
                $lines[] = '⏰ تأیید دیرهنگام پس از ' . ($previousStatus === 'cancelled' ? 'لغو محلی فاکتور' : 'پایان مهلت محلی');
            }
            $threadRow = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'paymentreport', 'select') : null;
            $result = \Faoxima\Payments\Settlement\Confirmation::confirmPaid($runtimePdo, $runtimeSetting, $orderId, (int) ($report['tetraminator_abandoned_at'] ?? 0) > 0 ? '' : 'chashbacktetraminator', [
                'method' => \Faoxima\Payments\Gateways\Tetraminator\Client::displayName(),
                'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
                'extra_lines' => $lines,
            ]);
            $fulfilled = !empty($result['ok']);
            $confirmReason = (string) ($result['reason'] ?? '');
            $confirmedReport = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::fetchReport($runtimePdo, $orderId);
            if ($confirmedReport === null || strtolower((string) ($confirmedReport['payment_Status'] ?? '')) !== 'paid') {
                \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_CONFIRM_BLOCKED', 'Tetraminator paid verification could not be applied to the local payment', [
                    'order_id' => $orderId,
                    'source' => $source,
                    'reason' => $confirmReason,
                ]);
                return ['state' => 'retry', 'reason' => $confirmReason !== '' ? $confirmReason : 'confirmation_not_persisted'];
            }
            \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                'tetraminator_local_state' => $fulfilled ? 'completed' : 'paid_pending_fulfillment',
            ]);
            return [
                'state' => 'paid',
                'reason' => '',
                'confirmed' => true,
                'fulfilled' => $fulfilled,
                'result' => $result,
            ];
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_VERIFY_FAILED', $e->getMessage(), ['order_id' => $orderId, 'source' => $source]);
            return ['state' => 'error', 'reason' => 'exception'];
        } finally {
            if ($lock !== null) {
                try {
                    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                } catch (Throwable $e) {
                }
            }
        }
    }

    private static function applyNotPaid($runtimePdo, array $report, string $remoteStatus): array
    {
        $orderId = (string) ($report['id_order'] ?? '');
        $now = time();
        $columns = [
            'tetraminator_status' => $remoteStatus,
            'tetraminator_last_checked_at' => $now,
            'tetraminator_local_state' => 'remote_' . ($remoteStatus !== '' ? $remoteStatus : 'unknown'),
        ];
        \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, $columns);
        $localStatus = strtolower(trim((string) ($report['payment_Status'] ?? '')));
        $created = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::reportCreatedAt($report);
        $expired = $created > 0 && $now >= $created + TETRAMINATOR_LINK_TTL_SECONDS + self::LOCAL_EXPIRY_GRACE_SECONDS;
        if ($expired && in_array($localStatus, ['unpaid', 'pending', 'waiting'], true) && (int) ($report['direct_payment_done'] ?? 0) === 0) {
            \Faoxima\Payments\Settlement\Confirmation::markUnsettledTerminal($runtimePdo, $orderId, 'expire');
            \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, ['tetraminator_local_state' => 'expired_local']);
            return ['state' => 'expired', 'reason' => 'not_paid'];
        }
        return ['state' => $expired ? 'expired' : 'pending', 'reason' => 'not_paid'];
    }

    public static function handleCallback($runtimePdo, $runtimeSetting, $callbackToken): array
    {
        $report = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::fetchByCallbackToken($runtimePdo, $callbackToken);
        if ($report === null) {
            return ['http' => 404, 'body' => 'not found', 'state' => 'not_found'];
        }
        $orderId = (string) ($report['id_order'] ?? '');
        \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, ['tetraminator_webhook_at' => time()]);
        $result = self::verifyPayment($runtimePdo, $runtimeSetting, $orderId, [
            'source' => 'webhook',
            'lock_wait' => 8,
            'timeout' => TETRAMINATOR_WEBHOOK_TIMEOUT_SECONDS,
            'attempts' => 2,
        ]);
        $state = (string) ($result['state'] ?? 'error');
        if ($state === 'paid') {
            return ['http' => 200, 'body' => !empty($result['fulfilled']) ? 'ok' : 'ok pending fulfillment', 'state' => $state];
        }
        if ($state === 'pending' || $state === 'expired') {
            \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, ['tetraminator_last_checked_at' => 0]);
            return ['http' => 200, 'body' => 'not paid yet', 'state' => $state];
        }
        if ($state === 'mismatch') {
            return ['http' => 409, 'body' => 'mismatch', 'state' => $state];
        }
        if ($state === 'not_found' || $state === 'no_invoice') {
            return ['http' => 404, 'body' => 'not found', 'state' => $state];
        }
        if ($state === 'auth_error') {
            return ['http' => 500, 'body' => 'misconfigured', 'state' => $state];
        }
        \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, ['tetraminator_last_checked_at' => 0]);
        return ['http' => 503, 'body' => 'retry later', 'state' => $state];
    }

    public static function pollThrottled($runtimePdo, $runtimeSetting, $orderId, $userId = null)
    {
        $report = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null) {
            return null;
        }
        if ($userId !== null && (string) ($report['id_user'] ?? '') !== (string) $userId) {
            return null;
        }
        $localStatus = strtolower(trim((string) ($report['payment_Status'] ?? '')));
        if ($localStatus === 'paid' && (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return $report;
        }
        if (!in_array($localStatus, ['unpaid', 'pending', 'waiting', 'expire', 'cancelled', 'paid'], true)) {
            return $report;
        }
        if ((string) ($report['tetraminator_local_state'] ?? '') === 'closed') {
            return $report;
        }
        if ((int) ($report['tetraminator_last_checked_at'] ?? 0) > time() - TETRAMINATOR_USER_CHECK_THROTTLE_SECONDS) {
            return $report;
        }
        \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, ['tetraminator_last_checked_at' => time()], false);
        self::verifyPayment($runtimePdo, $runtimeSetting, $orderId, ['source' => 'user', 'timeout' => 10, 'attempts' => 1]);
        return \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $report;
    }

    public static function closeStale($runtimePdo): array
    {
        $pdo = $runtimePdo;
        $stats = ['incomplete' => 0, 'stale' => 0, 'closed' => 0];
        if (!($pdo instanceof PDO)) {
            return $stats;
        }
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "SELECT id_order FROM Payment_report
                  WHERE Payment_Method = 'tetraminator'
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND (tetraminator_pay_id IS NULL OR tetraminator_pay_id = '')
                    AND tetraminator_created_at IS NOT NULL AND tetraminator_created_at < ?
                  ORDER BY id ASC LIMIT 100"
            );
            $stmt->execute([$now - self::INCOMPLETE_INVOICE_SECONDS]);
            foreach ((array) $stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $orderId) {
                \Faoxima\Payments\Settlement\Confirmation::markUnsettledTerminal($runtimePdo, (string) $orderId, 'expire');
                \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, (string) $orderId, ['tetraminator_local_state' => 'closed']);
                $stats['incomplete']++;
            }

            $stmt = $pdo->prepare(
                "SELECT id_order FROM Payment_report
                  WHERE Payment_Method = 'tetraminator'
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND tetraminator_pay_id IS NOT NULL AND tetraminator_pay_id <> ''
                    AND tetraminator_created_at IS NOT NULL AND tetraminator_created_at < ?
                    AND (tetraminator_local_state IS NULL OR tetraminator_local_state NOT IN ('paid_verified','paid_pending_fulfillment','completed'))
                  ORDER BY id ASC LIMIT 100"
            );
            $stmt->execute([$now - TETRAMINATOR_RECONCILE_SECONDS]);
            foreach ((array) $stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $orderId) {
                \Faoxima\Payments\Settlement\Confirmation::markUnsettledTerminal($runtimePdo, (string) $orderId, 'expire');
                \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, (string) $orderId, ['tetraminator_local_state' => 'closed']);
                $stats['stale']++;
            }

            $close = $pdo->prepare(
                "UPDATE Payment_report
                    SET tetraminator_local_state = 'closed'
                  WHERE Payment_Method = 'tetraminator'
                    AND payment_Status IN ('expire','cancelled')
                    AND (tetraminator_local_state IS NULL OR tetraminator_local_state <> 'closed')
                    AND tetraminator_created_at IS NOT NULL AND tetraminator_created_at < ?
                  LIMIT 200"
            );
            $close->execute([$now - TETRAMINATOR_RECONCILE_SECONDS]);
            $stats['closed'] = $close->rowCount();
            if (($stats['incomplete'] + $stats['stale'] + $stats['closed']) > 0 && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
            }
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_CLOSE_STALE_FAILED', $e->getMessage());
        }
        return $stats;
    }

    public static function processPendingOrders($runtimePdo, $runtimeSetting, $limit = 20): array
    {
        $pdo = $runtimePdo;
        $stats = ['checked' => 0, 'paid' => 0, 'retry' => 0];
        if (!($pdo instanceof PDO)) {
            return $stats;
        }
        $limit = max(1, min(100, (int) $limit));
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "SELECT id_order FROM Payment_report
                  WHERE Payment_Method = 'tetraminator'
                    AND tetraminator_pay_id IS NOT NULL AND tetraminator_pay_id <> ''
                    AND tetraminator_created_at >= ?
                    AND payment_Status IN ('Unpaid','pending','waiting','expire','cancelled')
                    AND (tetraminator_local_state IS NULL OR tetraminator_local_state <> 'closed')
                    AND (
                        tetraminator_last_checked_at IS NULL
                        OR (tetraminator_created_at >= ? AND tetraminator_last_checked_at <= ?)
                        OR (tetraminator_created_at < ? AND tetraminator_created_at >= ? AND tetraminator_last_checked_at <= ?)
                        OR (tetraminator_created_at < ? AND tetraminator_last_checked_at <= ?)
                    )
                  ORDER BY tetraminator_last_checked_at IS NOT NULL, tetraminator_last_checked_at ASC, id ASC
                  LIMIT " . $limit
            );
            $young = $now - (TETRAMINATOR_LINK_TTL_SECONDS + self::LOCAL_EXPIRY_GRACE_SECONDS);
            $middle = $now - self::MIDDLE_AGE_SECONDS;
            $stmt->execute([
                $now - TETRAMINATOR_RECONCILE_SECONDS,
                $young, $now - TETRAMINATOR_POLL_THROTTLE_SECONDS,
                $young, $middle, $now - 600,
                $middle, $now - 3600,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Tetraminator\Client::logEvent('TETRAMINATOR_POLL_SELECT_FAILED', $e->getMessage());
            return $stats;
        }
        foreach ((array) $rows as $orderId) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                break;
            }
            $orderId = (string) $orderId;
            if ($orderId === '') {
                continue;
            }
            $stats['checked']++;
            \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::updateColumns($runtimePdo, $orderId, ['tetraminator_last_checked_at' => time()], false);
            $result = self::verifyPayment($runtimePdo, $runtimeSetting, $orderId, ['source' => 'poll']);
            $state = (string) ($result['state'] ?? '');
            if ($state === 'paid') {
                $stats['paid']++;
            } elseif (in_array($state, ['retry', 'busy', 'error'], true)) {
                $stats['retry']++;
            }
        }
        return $stats;
    }

    public static function recoverVerifiedOrders($runtimePdo, $runtimeSetting, $limit = 10): int
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return 0;
        }
        $limit = max(1, min(50, (int) $limit));
        try {
            $stmt = $pdo->prepare(
                "SELECT id_order, payment_Status FROM Payment_report
                  WHERE Payment_Method = 'tetraminator'
                    AND tetraminator_confirmed_at IS NOT NULL AND tetraminator_confirmed_at >= ?
                    AND tetraminator_local_state IN ('paid_verified','paid_pending_fulfillment')
                    AND (payment_Status <> 'paid' OR COALESCE(direct_payment_done, 0) = 0)
                    AND NOT EXISTS (
                        SELECT 1 FROM payment_fulfillment pf
                         WHERE pf.id_order = Payment_report.id_order
                           AND pf.state IN ('completed','manual_review')
                    )
                  ORDER BY id ASC
                  LIMIT " . $limit
            );
            $stmt->execute([time() - TETRAMINATOR_RECONCILE_SECONDS * 2]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return 0;
        }
        $recovered = 0;
        foreach ((array) $rows as $row) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                break;
            }
            $orderId = (string) ($row['id_order'] ?? '');
            if ($orderId === '') {
                continue;
            }
            if (strtolower((string) ($row['payment_Status'] ?? '')) !== 'paid') {
                $result = self::verifyPayment($runtimePdo, $runtimeSetting, $orderId, ['source' => 'recovery']);
                if (($result['state'] ?? '') === 'paid') {
                    $recovered++;
                }
                continue;
            }
            if (!method_exists(\Faoxima\Payments\Fulfillment\FulfillmentRuntime::class, 'rx_pf_recover')) {
                continue;
            }
            $result = \Faoxima\Payments\Fulfillment\Recovery::recover($runtimePdo, $orderId);
            $status = (string) ($result['status'] ?? '');
            if ($status === 'claimed' || $status === 'reconcile') {
                $token = (string) ($result['token'] ?? '');
                if ($token !== '' && is_callable([\Faoxima\Services\Fulfillment\Delivery::class, 'runClaimed'])) {
                    \Faoxima\Services\Fulfillment\Delivery::runClaimed($runtimePdo, $orderId, $token, dirname(__DIR__, 4) . '/images.jpg', 'waiting');
                }
            }
            if (in_array($status, ['claimed', 'reconcile', 'finalized'], true)) {
                $recovered++;
            }
        }
        return $recovered;
    }
}
