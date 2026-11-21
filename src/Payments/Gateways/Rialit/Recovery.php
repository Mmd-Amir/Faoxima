<?php

namespace Faoxima\Payments\Gateways\Rialit;

use PDO;
use Throwable;
use Exception;

final class Recovery
{
    public static function reopenCancelledForConfirmation($runtimePdo, $orderId): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET payment_Status = 'expire', rialit_local_state = 'late_confirmation'
                  WHERE id_order = ?
                    AND Payment_Method = 'rialit'
                    AND payment_Status = 'cancelled'"
            );
            $stmt->execute([(string) $orderId]);
            if ($stmt->rowCount() > 0 && method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_REOPEN_CANCELLED_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return false;
        }
    }

    public static function verifyPayment($runtimePdo, $runtimeSetting, $orderId, array $options = []): array
    {
        $pdo = $runtimePdo;
        $orderId = trim((string) $orderId);
        if ($orderId === '' || !($pdo instanceof PDO)) {
            return ['state' => 'error', 'reason' => 'unavailable'];
        }
        $report = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null) {
            return ['state' => 'not_found', 'reason' => 'order_not_found'];
        }
        if ((string) ($report['Payment_Method'] ?? '') !== 'rialit') {
            return ['state' => 'mismatch', 'reason' => 'payment_method'];
        }
        $token = trim((string) ($report['rialit_token'] ?? ''));
        $expectedPayer = (int) ($report['rialit_payer_id'] ?? 0);
        if ($expectedPayer <= 0) {
            $expectedPayer = (int) ($report['id_user'] ?? 0);
        }
        $expectedAmount = (int) ($report['price'] ?? 0);
        if ($expectedAmount <= 0) {
            return ['state' => 'error', 'reason' => 'amount_missing'];
        }

        $lockName = 'rialit_verify_' . substr(md5($orderId), 0, 24);
        $lock = null;
        try {
            $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
            $lock->execute([$lockName]);
            if ((int) $lock->fetchColumn() !== 1) {
                return ['state' => 'busy', 'reason' => 'locked'];
            }
            $report = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport($runtimePdo, $orderId);
            if ($report === null) {
                return ['state' => 'retry', 'reason' => 'report_unavailable'];
            }
            $token = trim((string) ($report['rialit_token'] ?? ''));
            $eventToken = trim((string) ($options['token'] ?? ''));
            if ($token === '' && $eventToken === '' && (int) ($report['rialit_abandoned_at'] ?? 0) > 0) {
                return ['state' => 'cancelled', 'reason' => 'locally_cancelled_without_token'];
            }
            $needsTokenPersistence = $token === '' && $eventToken !== '';
            if ($needsTokenPersistence) {
                $token = $eventToken;
            }
            if ($token === '') {
                $created = \Faoxima\Payments\Gateways\Rialit\Client::createPayment($runtimePdo, $orderId, $expectedAmount, $expectedPayer);
                if (empty($created['success']) || !\Faoxima\Payments\Gateways\Rialit\PaymentOrders::storePayment($runtimePdo, $orderId, $created)) {
                    return ['state' => 'retry', 'reason' => 'creation_recovery_failed'];
                }
                $token = (string) $created['token'];
            }
            if (!preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
                return ['state' => 'mismatch', 'reason' => 'invalid_token'];
            }
            if ($eventToken !== '' && !hash_equals($token, $eventToken)) {
                return ['state' => 'mismatch', 'reason' => 'webhook_token'];
            }
            $check = \Faoxima\Payments\Gateways\Rialit\Client::checkPayment($runtimePdo, $token);
            if (!is_array($check) || (isset($check['success']) && $check['success'] === false)) {
                $code = is_array($check) ? (string) ($check['error_code'] ?? 'ERROR') : 'ERROR';
                if ($code === 'AUTH_FAILED') {
                    \Faoxima\Payments\Gateways\Rialit\PaymentOrders::updateColumns($runtimePdo, $orderId, ['rialit_local_state' => 'auth_error']);
                    \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_VERIFY_AUTH_FAILED', 'Rialit status check was rejected by authentication', ['order_id' => $orderId]);
                    return ['state' => 'auth_error', 'reason' => 'auth_failed'];
                }
                $state = ($code === 'RATE_LIMIT_EXCEEDED' || $code === 'UPSTREAM_UNAVAILABLE' || $code === 'TRANSPORT_ERROR') ? 'retry' : 'error';
                return ['state' => $state, 'reason' => strtolower($code)];
            }
            $statusCode = (int) ($check['status_code'] ?? 0);
            if ($statusCode < 200 || $statusCode >= 300) {
                if ($statusCode === 401) {
                    \Faoxima\Payments\Gateways\Rialit\PaymentOrders::updateColumns($runtimePdo, $orderId, ['rialit_local_state' => 'auth_error']);
                    return ['state' => 'auth_error', 'reason' => 'auth_failed'];
                }
                $retryable = $statusCode === 429 || $statusCode >= 500;
                return ['state' => $retryable ? 'retry' : 'error', 'reason' => 'http_' . $statusCode];
            }

            $remoteToken = isset($check['token']) && is_scalar($check['token']) ? trim((string) $check['token']) : '';
            $remoteOrder = isset($check['order_id']) && is_scalar($check['order_id']) ? trim((string) $check['order_id']) : '';
            $remotePayer = isset($check['payer_id']) && is_numeric($check['payer_id']) ? (int) $check['payer_id'] : 0;
            $remoteAmount = isset($check['amount']) && is_numeric($check['amount']) ? (int) $check['amount'] : 0;
            $remoteStatus = isset($check['status']) && is_scalar($check['status']) ? strtolower(trim((string) $check['status'])) : '';
            $payerAmount = isset($check['payer_amount']) && is_numeric($check['payer_amount']) ? (int) $check['payer_amount'] : $remoteAmount;
            $payerFee = isset($check['payer_fee']) && is_numeric($check['payer_fee']) ? (int) $check['payer_fee'] : max(0, $payerAmount - $remoteAmount);

            $identityOk = $remoteToken !== ''
                && hash_equals($token, $remoteToken)
                && $remoteOrder !== ''
                && hash_equals($orderId, $remoteOrder)
                && $remotePayer > 0
                && $remotePayer === $expectedPayer;
            if (!$identityOk) {
                \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_VERIFY_IDENTITY_MISMATCH', 'Rialit payment identity does not match the local order', [
                    'order_id' => $orderId,
                    'token_match' => $remoteToken !== '' && hash_equals($token, $remoteToken),
                    'order_match' => $remoteOrder !== '' && hash_equals($orderId, $remoteOrder),
                    'payer_match' => $remotePayer > 0 && $remotePayer === $expectedPayer,
                ]);
                \Faoxima\Payments\Gateways\Rialit\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                    'rialit_status' => $remoteStatus,
                    'rialit_last_checked_at' => time(),
                    'rialit_local_state' => 'identity_mismatch',
                ]);
                return ['state' => 'mismatch', 'reason' => 'identity'];
            }
            if ($remoteAmount !== $expectedAmount) {
                \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_VERIFY_AMOUNT_MISMATCH', 'Rialit payment amount does not match the local order', [
                    'order_id' => $orderId,
                    'expected' => $expectedAmount,
                    'received' => $remoteAmount,
                ]);
                \Faoxima\Payments\Gateways\Rialit\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                    'rialit_status' => $remoteStatus,
                    'rialit_last_checked_at' => time(),
                    'rialit_local_state' => 'amount_mismatch',
                ]);
                return ['state' => 'mismatch', 'reason' => 'amount'];
            }
            if ($needsTokenPersistence && !\Faoxima\Payments\Gateways\Rialit\PaymentOrders::storePayment($runtimePdo, $orderId, [
                'token' => $remoteToken,
                'status' => $remoteStatus,
                'payer_amount' => $payerAmount,
                'payer_fee' => $payerFee,
            ])) {
                return ['state' => 'retry', 'reason' => 'token_persistence_failed'];
            }

            $columns = [
                'rialit_status' => $remoteStatus,
                'rialit_payer_amount' => $payerAmount,
                'rialit_payer_fee' => $payerFee,
                'rialit_last_checked_at' => time(),
            ];
            if (isset($check['paid_at']) && is_scalar($check['paid_at']) && trim((string) $check['paid_at']) !== '') {
                $columns['rialit_paid_at'] = mb_substr(trim((string) $check['paid_at']), 0, 40);
            }
            if (isset($check['reference']) && is_scalar($check['reference']) && trim((string) $check['reference']) !== '') {
                $columns['rialit_reference'] = mb_substr(trim((string) $check['reference']), 0, 64);
            }
            if ($remoteStatus !== 'paid') {
                if (!in_array($remoteStatus, ['pending', 'expired', 'cancelled'], true)) {
                    return ['state' => 'retry', 'reason' => 'invalid_remote_status'];
                }
                if (strtolower((string) ($report['payment_Status'] ?? '')) === 'paid') {
                    return ['state' => 'retry', 'reason' => 'paid_status_conflict'];
                }
                $columns['rialit_local_state'] = $remoteStatus !== '' ? 'remote_' . $remoteStatus : 'remote_unknown';
                if ($remoteStatus === 'expired' || $remoteStatus === 'cancelled') {
                    $columns['payment_Status'] = $remoteStatus === 'expired' ? 'expire' : 'cancelled';
                }
                \Faoxima\Payments\Gateways\Rialit\PaymentOrders::updateColumns($runtimePdo, $orderId, $columns);
                $saved = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport($runtimePdo, $orderId);
                if ($saved === null || (string) ($saved['rialit_status'] ?? '') !== $remoteStatus) {
                    return ['state' => 'retry', 'reason' => 'status_persistence_failed'];
                }
                return ['state' => $remoteStatus !== '' ? $remoteStatus : 'pending', 'reason' => 'not_paid'];
            }

            $columns['rialit_local_state'] = 'paid_verified';
            \Faoxima\Payments\Gateways\Rialit\PaymentOrders::updateColumns($runtimePdo, $orderId, $columns);

            $previousStatus = strtolower(trim((string) ($report['payment_Status'] ?? '')));
            if ($previousStatus === 'cancelled') {
                self::reopenCancelledForConfirmation($runtimePdo, $orderId);
            }
            if (!method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_confirm_paid')) {
                require_once dirname(__DIR__, 4) . '/src/bootstrap.php';
            }

            $lines = [
                '🧾 شناسه پرداخت ریالیت: ' . $token,
                '💸 مبلغ نهایی پرداخت‌شده: ' . number_format($payerAmount) . ' تومان',
                '🧾 کارمزد ریالیت: ' . number_format($payerFee) . ' تومان',
            ];
            if (!empty($check['reference']) && is_scalar($check['reference'])) {
                $lines[] = '🔗 شماره پیگیری بانکی: ' . mb_substr(trim((string) $check['reference']), 0, 64);
            }
            if (in_array($previousStatus, ['cancelled', 'expire'], true)) {
                $lines[] = '⏰ تأیید دیرهنگام پس از ' . ($previousStatus === 'cancelled' ? 'لغو محلی فاکتور' : 'پایان مهلت محلی');
            }
            $threadRow = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'paymentreport', 'select') : null;
            $result = \Faoxima\Payments\Settlement\Confirmation::confirmPaid($runtimePdo, $runtimeSetting, $orderId, (int) ($report['rialit_abandoned_at'] ?? 0) > 0 ? '' : 'chashbackrialit', [
                'method' => \Faoxima\Payments\Gateways\Rialit\Client::displayName(),
                'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
                'extra_lines' => $lines,
            ]);
            $fulfilled = !empty($result['ok']);
            $confirmReason = (string) ($result['reason'] ?? '');
            \Faoxima\Payments\Gateways\Rialit\PaymentOrders::updateColumns($runtimePdo, $orderId, [
                'rialit_local_state' => $fulfilled ? 'completed' : 'paid_pending_fulfillment',
            ]);
            $confirmedReport = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport($runtimePdo, $orderId);
            if ($confirmedReport === null || strtolower((string) ($confirmedReport['payment_Status'] ?? '')) !== 'paid') {
                \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_CONFIRM_BLOCKED', 'Rialit paid verification could not be applied to the local payment', [
                    'order_id' => $orderId,
                    'reason' => $confirmReason,
                ]);
                return ['state' => 'retry', 'reason' => $confirmReason !== '' ? $confirmReason : 'confirmation_not_persisted'];
            }
            return [
                'state' => 'paid',
                'reason' => '',
                'confirmed' => true,
                'fulfilled' => $fulfilled,
                'result' => $result,
            ];
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_VERIFY_FAILED', $e->getMessage(), ['order_id' => $orderId]);
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

    public static function pollThrottled($runtimePdo, $runtimeSetting, $orderId, $userId = null)
    {
        $report = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport($runtimePdo, $orderId);
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
        if (!in_array($localStatus, ['unpaid', 'pending', 'waiting', 'expire', 'cancelled'], true)) {
            return $report;
        }
        if ((int) ($report['rialit_last_checked_at'] ?? 0) > time() - RIALIT_USER_CHECK_THROTTLE_SECONDS) {
            return $report;
        }
        \Faoxima\Payments\Gateways\Rialit\PaymentOrders::updateColumns($runtimePdo, $orderId, ['rialit_last_checked_at' => time()], false);
        self::verifyPayment($runtimePdo, $runtimeSetting, $orderId, ['source' => 'user']);
        return \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $report;
    }
}
