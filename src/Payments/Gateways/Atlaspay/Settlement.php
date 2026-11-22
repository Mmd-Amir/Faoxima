<?php

namespace Faoxima\Payments\Gateways\Atlaspay;

use PDO;
use Throwable;

final class Settlement
{
    public static function localStatusIsOpen(array $report)
    {
        return in_array((string) ($report['payment_Status'] ?? ''), ['Unpaid', 'pending', 'waiting'], true);
        }

    public static function isLateReconcilable(array $report)
    {
        return in_array((string) ($report['payment_Status'] ?? ''), ['expire', 'reject'], true)
            && (string) ($report['atlaspay_local_state'] ?? '') !== 'closed'
            && (int) ($report['atlaspay_created_at'] ?? 0) >= time() - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS;
        }

    public static function fulfill($runtimePdo, $runtimeSetting, array $report, array $check)
    {
        $orderId = (string) $report['id_order'];
        $status = strtolower(trim(is_scalar($check['status'] ?? null) ? (string) $check['status'] : ''));
        if (!in_array($status, ['confirmed', 'settled'], true)) {
            return ['ok' => false, 'reason' => 'not confirmed'];
        }
        if (array_key_exists('paid', $check) && empty($check['paid'])) {
            return ['ok' => false, 'reason' => 'not paid'];
        }
        if (isset($check['baseAmountToman']) && is_numeric($check['baseAmountToman']) && (int) $check['baseAmountToman'] !== (int) $report['price']) {
            error_log('AtlasPay custom fulfill base mismatch: ' . json_encode(['order_id' => $orderId, 'expected' => (int) $report['price'], 'received' => $check['baseAmountToman']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return ['ok' => false, 'reason' => 'base amount mismatch'];
        }
        if ((string) ($report['payment_Status'] ?? '') === 'cancelled') {
            return ['ok' => false, 'reason' => 'cancelled'];
        }
        if (!method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_confirm_paid')) {
            require_once dirname(__DIR__, 4) . '/src/bootstrap.php';
        }
        $previousStatus = (string) ($report['payment_Status'] ?? '');
        $actual = isset($check['actualReceivedAmountToman']) && is_numeric($check['actualReceivedAmountToman']) ? (int) $check['actualReceivedAmountToman'] : null;
        $lines = [
            '🧾 شناسه پیگیری اطلس‌پی: ' . ($report['atlaspay_tracking_code'] ?? ''),
            '💰 مبلغ اعتباردهی: ' . number_format((int) $report['price']) . ' تومان',
            '💸 مبلغ سفارش اطلس‌پی: ' . number_format((int) ($report['atlaspay_total_amount'] ?? 0)) . ' تومان',
        ];
        if ($actual !== null) {
            $lines[] = '💸 مبلغ نهایی تأییدشده توسط اطلس‌پی: ' . number_format($actual) . ' تومان';
        }
        $lines[] = '🔐 درگاه داخلی اطلس‌پی';
        if (in_array($previousStatus, ['expire', 'reject'], true)) {
            $lines[] = '⏰ تأیید دیرهنگام پس از ' . ($previousStatus === 'reject' ? 'رد اولیه' : 'پایان مهلت محلی');
        }
        $threadRow = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select");
        $result = \Faoxima\Payments\Settlement\Confirmation::confirmPaid($runtimePdo, $runtimeSetting, $orderId, 'chashbackatlaspay', [
            'method' => 'atlaspay',
            'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
            'extra_lines' => $lines,
        ]);
        if (!empty($result['ok'])) {
            \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::updateColumns($runtimePdo, $orderId, ['atlaspay_remote_status' => $status, 'atlaspay_local_state' => 'completed'], false);
        }
        return $result;
        }

    public static function processCheck($runtimePdo, $runtimeSetting, array $report, $check, $fulfill = true)
    {
        $remoteId = trim((string) ($report['atlaspay_order_id'] ?? ''));
        if (!is_array($check)) {
            return ['state' => 'error'];
        }
        $statusCode = (int) ($check['status_code'] ?? 0);
        if ($statusCode === 429) {
            return ['state' => 'rate_limited'];
        }
        if (($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) || (array_key_exists('success', $check) && $check['success'] === false)) {
            return ['state' => 'error'];
        }
        $checkId = $check['id'] ?? ($check['orderId'] ?? null);
        if ($remoteId === '' || !is_numeric($checkId) || (int) $checkId !== (int) $remoteId) {
            error_log('AtlasPay custom check identity mismatch: ' . json_encode(['order_id' => (string) $report['id_order']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return ['state' => 'error'];
        }
        if (isset($check['merchantOrderRef']) && is_scalar($check['merchantOrderRef']) && (string) $check['merchantOrderRef'] !== (string) $report['id_order']) {
            error_log('AtlasPay custom check reference mismatch: ' . json_encode(['order_id' => (string) $report['id_order']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return ['state' => 'error'];
        }
        $storedTotal = (int) ($report['atlaspay_total_amount'] ?? 0);
        if (isset($check['totalAmountToman']) && is_numeric($check['totalAmountToman']) && $storedTotal > 0 && (int) $check['totalAmountToman'] !== $storedTotal) {
            error_log('AtlasPay custom check total mismatch: ' . json_encode(['order_id' => (string) $report['id_order'], 'expected' => $storedTotal, 'received' => $check['totalAmountToman']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return ['state' => 'error'];
        }

        $orderId = (string) $report['id_order'];
        $remote = strtolower(trim(is_scalar($check['status'] ?? null) ? (string) $check['status'] : ''));
        $actual = isset($check['actualReceivedAmountToman']) && is_numeric($check['actualReceivedAmountToman']) ? (int) $check['actualReceivedAmountToman'] : null;
        $columns = ['atlaspay_last_checked_at' => time()];
        if ($remote !== '') {
            $columns['atlaspay_remote_status'] = $remote;
        }
        if ($actual !== null) {
            $columns['atlaspay_actual_received'] = $actual;
        }
        \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::updateColumns($runtimePdo, $orderId, $columns, false);

        $localStatus = (string) ($report['payment_Status'] ?? '');
        $openStatuses = ['Unpaid', 'pending', 'waiting'];

        if (in_array($remote, ['confirmed', 'settled'], true)) {
            if (array_key_exists('paid', $check) && empty($check['paid'])) {
                return ['state' => 'open', 'status' => $remote];
            }
            $manual = !empty($check['requiresManualDelivery']) || ($actual !== null && $storedTotal > 0 && $actual < $storedTotal);
            if ($manual) {
                \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::updateColumns($runtimePdo, $orderId, ['atlaspay_manual_delivery' => 1], false);
                if (\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::claimLocalState($runtimePdo, $orderId, 'manual_review', ['Unpaid', 'pending', 'waiting', 'expire', 'reject'])) {
                    \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomNotifyManualDelivery($report, $actual);
                }
                return ['state' => 'manual', 'status' => $remote];
            }
            if (!$fulfill) {
                return ['state' => 'completed', 'status' => $remote];
            }
            $result = self::fulfill($runtimePdo, $runtimeSetting, $report, $check);
            return ['state' => 'completed', 'status' => $remote, 'fulfilled' => !empty($result['ok'])];
        }

        if ($remote === 'rejected') {
            $reason = '';
            foreach (['reason', 'rejectReason', 'rejectionReason'] as $key) {
                if (isset($check[$key]) && is_scalar($check[$key]) && trim((string) $check[$key]) !== '') {
                    $reason = mb_substr(trim((string) $check[$key]), 0, 250);
                    break;
                }
            }
            $extra = $reason !== '' ? ['dec_not_confirmed' => $reason] : [];
            if (\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::transition($runtimePdo, $orderId, $openStatuses, 'reject', 'rejected', $extra)) {
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomNotifyUser($report, 'rejected');
            }
            return ['state' => 'terminal', 'status' => $remote];
        }

        if ($remote === 'expired') {
            if (\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::transition($runtimePdo, $orderId, $openStatuses, 'expire', 'expired')) {
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomNotifyUser($report, 'expired');
            }
            return ['state' => 'terminal', 'status' => $remote];
        }

        if ($remote === 'cancelled') {
            \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::transition($runtimePdo, $orderId, array_merge($openStatuses, ['expire', 'reject']), 'cancelled', 'closed');
            return ['state' => 'terminal', 'status' => $remote];
        }

        if (in_array($remote, ['admin_review', 'underpaid_review', 'underpaid_awaiting_remainder'], true)) {
            if (in_array($localStatus, ['expire', 'reject'], true)) {
                \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::transition($runtimePdo, $orderId, ['expire', 'reject'], 'Unpaid', $remote);
            } else {
                \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::claimLocalState($runtimePdo, $orderId, $remote, $openStatuses);
            }
            return ['state' => 'open', 'status' => $remote];
        }

        $deadlineAt = (int) ($report['atlaspay_deadline_at'] ?? 0);
        if ($deadlineAt > 0 && time() > $deadlineAt + ATLASPAY_CUSTOM_DEADLINE_GRACE_SECONDS && in_array($localStatus, $openStatuses, true)) {
            if (\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::transition($runtimePdo, $orderId, $openStatuses, 'expire', 'unpaid_timeout')) {
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomNotifyUser($report, 'expired');
            }
            return ['state' => 'terminal', 'status' => $remote];
        }
        return ['state' => 'open', 'status' => $remote];
        }

    public static function pollOrder($runtimePdo, $runtimeSetting, array $report, $fulfill = true, $verify = false)
    {
        $remoteId = trim((string) ($report['atlaspay_order_id'] ?? ''));
        if ($remoteId === '') {
            return ['state' => 'error'];
        }
        $check = $verify ? \Faoxima\Payments\Gateways\Atlaspay\Client::verifyOrder($remoteId) : \Faoxima\Payments\Gateways\Atlaspay\Client::checkOrder($remoteId);
        return self::processCheck($runtimePdo, $runtimeSetting, $report, $check, $fulfill);
        }

    public static function pollThrottled($runtimePdo, $runtimeSetting, $orderId, $userId)
    {
        $report = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $userId) {
            return null;
        }
        $pollable = self::localStatusIsOpen($report) || self::isLateReconcilable($report);
        if ($pollable && time() - (int) ($report['atlaspay_last_checked_at'] ?? 0) >= ATLASPAY_CUSTOM_POLL_THROTTLE_SECONDS) {
            \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::updateColumns($runtimePdo, $orderId, ['atlaspay_last_checked_at' => time()], false);
            self::pollOrder($runtimePdo, $runtimeSetting, $report, true, true);
            $report = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($runtimePdo, $orderId) ?? $report;
        }
        return $report;
        }

    public static function cancel($runtimePdo, $runtimeSetting, $orderId, $userId, $telegram = false)
    {
        $report = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $userId) {
            return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
        }
        $state = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::buildState($report);
        if (!$state['can_cancel']) {
            return ['ok' => false, 'message' => \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomCancelBlockedMessage($state, $telegram)];
        }
        $remoteId = trim((string) ($report['atlaspay_order_id'] ?? ''));
        $poll = self::pollOrder($runtimePdo, $runtimeSetting, $report, true, false);
        if (in_array($poll['state'], ['error', 'rate_limited'], true)) {
            return ['ok' => false, 'message' => 'در حال حاضر امکان بررسی وضعیت پرداخت در اطلس‌پی وجود ندارد؛ کمی بعد دوباره تلاش کنید.'];
        }
        $report = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null) {
            return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
        }
        $state = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::buildState($report);
        if (!$state['can_cancel']) {
            return ['ok' => false, 'message' => \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomCancelBlockedMessage($state, $telegram)];
        }
        $response = \Faoxima\Payments\Gateways\Atlaspay\Client::cancelOrder($remoteId);
        $accepted = is_array($response)
            && !empty($response['success'])
            && strtolower((string) ($response['status'] ?? 'cancelled')) === 'cancelled'
            && ((int) ($response['status_code'] ?? 200) >= 200 && (int) ($response['status_code'] ?? 200) < 300);
        if (!$accepted) {
            $message = is_array($response) && isset($response['message']) && is_scalar($response['message']) && trim((string) $response['message']) !== ''
                ? trim((string) $response['message'])
                : 'اطلس‌پی اجازه لغو این سفارش را نداد.';
            $fresh = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($runtimePdo, $orderId);
            if ($fresh !== null) {
                self::pollOrder($runtimePdo, $runtimeSetting, $fresh, true, false);
            }
            return ['ok' => false, 'message' => $message];
        }
        $changed = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::updateColumns($runtimePdo, $orderId, [
            'payment_Status' => 'cancelled',
            'atlaspay_remote_status' => 'cancelled',
            'atlaspay_local_state' => 'closed',
        ]);
        return $changed > 0 ? ['ok' => true] : ['ok' => false, 'message' => 'این فاکتور دیگر قابل لغو نیست.'];
        }

    public static function purgeProtected($runtimePdo, $orderId)
    {
        $report = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($runtimePdo, $orderId);
        if ($report === null) {
            return false;
        }
        $state = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::buildState($report);
        return in_array($state['phase'], array_merge(\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::openPhases(), ['confirming', 'manual_review']), true);
        }

    public static function purgeCards($runtimePdo)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                SET atlaspay_card_number = NULL, atlaspay_card_name = NULL
              WHERE atlaspay_gateway_mode = 'custom'
                AND atlaspay_created_at < ?
                AND atlaspay_card_number IS NOT NULL"
            );
            $stmt->execute([time() - ATLASPAY_CUSTOM_CARD_RETENTION_SECONDS]);
        } catch (Throwable $e) {
            error_log('[atlaspaycheck] card purge failed: ' . $e->getMessage());
        }
        }

    public static function userPending($runtimePdo, $userId, $limit = 10)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return [];
        }
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM Payment_report
              WHERE id_user = ?
                AND Payment_Method = 'atlaspay'
                AND atlaspay_gateway_mode = 'custom'
                AND atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> ''
                AND (
                    payment_Status IN ('Unpaid','pending','waiting')
                    OR (
                        payment_Status IN ('expire','reject')
                        AND (atlaspay_local_state IS NULL OR atlaspay_local_state <> 'closed')
                        AND atlaspay_created_at >= ?
                    )
                )
              ORDER BY id DESC
              LIMIT " . max(1, (int) $limit)
            );
            $stmt->execute([(string) $userId, $now - ATLASPAY_CUSTOM_PENDING_VISIBLE_SECONDS]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('AtlasPay custom user pending failed: ' . $e->getMessage());
            return [];
        }
        }
}
