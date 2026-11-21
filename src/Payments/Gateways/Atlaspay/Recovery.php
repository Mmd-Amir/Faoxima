<?php

namespace Faoxima\Payments\Gateways\Atlaspay;

use PDO;
use Throwable;

final class Recovery
{
    public static function runCron($runtimePdo, $runtimeSetting)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        \Faoxima\Payments\Gateways\Atlaspay\Settlement::purgeCards($runtimePdo);
        $rxPaymentWindow = \Faoxima\Payments\Fulfillment\PaymentPolicy::cronWindowSql();
        $now = time();
        $batches = [
            [
                "SELECT * FROM Payment_report
              WHERE $rxPaymentWindow AND Payment_Method = 'atlaspay' AND atlaspay_gateway_mode = 'custom'
                AND atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> ''
                AND payment_Status = 'Unpaid'
                AND (atlaspay_local_state IS NULL OR atlaspay_local_state <> 'manual_review')
                AND atlaspay_created_at >= ?
                AND (atlaspay_last_checked_at IS NULL OR atlaspay_last_checked_at <= ?)
              ORDER BY atlaspay_last_checked_at ASC, id ASC
              LIMIT 30",
                [$now - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS, $now - ATLASPAY_CUSTOM_CRON_INTERVAL_SECONDS],
            ],
            [
                "SELECT * FROM Payment_report
              WHERE $rxPaymentWindow AND Payment_Method = 'atlaspay' AND atlaspay_gateway_mode = 'custom'
                AND atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> ''
                AND payment_Status IN ('Unpaid','expire','reject')
                AND atlaspay_local_state = 'manual_review'
                AND atlaspay_created_at >= ?
                AND (atlaspay_last_checked_at IS NULL OR atlaspay_last_checked_at <= ?)
              ORDER BY atlaspay_last_checked_at ASC, id ASC
              LIMIT 10",
                [$now - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS, $now - ATLASPAY_CUSTOM_RECHECK_SECONDS],
            ],
            [
                "SELECT * FROM Payment_report
              WHERE $rxPaymentWindow AND Payment_Method = 'atlaspay' AND atlaspay_gateway_mode = 'custom'
                AND atlaspay_order_id IS NOT NULL AND atlaspay_order_id <> ''
                AND payment_Status IN ('expire','reject')
                AND (atlaspay_local_state IS NULL OR atlaspay_local_state NOT IN ('closed','manual_review'))
                AND atlaspay_created_at >= ?
                AND (atlaspay_last_checked_at IS NULL OR atlaspay_last_checked_at <= ?)
              ORDER BY atlaspay_last_checked_at ASC, id ASC
              LIMIT 10",
                [$now - ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS, $now - ATLASPAY_CUSTOM_RECHECK_SECONDS],
            ],
        ];
        foreach ($batches as [$sql, $params]) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                error_log('[atlaspaycheck] custom select failed: ' . $e->getMessage());
                continue;
            }
            foreach ($rows as $row) {
                if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                    return;
                }
                if (!\Faoxima\Payments\Fulfillment\PaymentPolicy::cronPaymentAllowed($row)) {
                    continue;
                }
                \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::updateColumns($runtimePdo, $row['id_order'], ['atlaspay_last_checked_at' => time()], false);
                try {
                    $result = \Faoxima\Payments\Gateways\Atlaspay\Settlement::pollOrder($runtimePdo, $runtimeSetting, $row, true, false);
                } catch (Throwable $e) {
                    error_log('[atlaspaycheck] custom poll threw for order ' . $row['id_order'] . ': ' . $e->getMessage());
                    continue;
                }
                if ($result['state'] === 'rate_limited') {
                    return;
                }
            }
        }
        }

    public static function updateOfficialChecked($runtimePdo, $orderId)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        try {
            $stmt = $pdo->prepare("UPDATE Payment_report SET atlaspay_last_checked_at = ? WHERE id_order = ? AND payment_processing_version >= 2");
            $stmt->execute([time(), (string) $orderId]);
        } catch (Throwable $e) {
            error_log('[atlaspaycheck] mark checked failed: ' . $e->getMessage());
        }
        }

    public static function officialV2Process($runtimePdo, $runtimeSetting, array $row, $check)
    {
        $pdo = $runtimePdo;
        $orderId = (string) $row['id_order'];
        $remoteId = trim((string) ($row['atlaspay_order_id'] ?? ''));
        if (!is_array($check) || !($pdo instanceof PDO)) {
            return 'error';
        }
        $statusCode = (int) ($check['status_code'] ?? 0);
        if (($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) || (array_key_exists('success', $check) && $check['success'] === false)) {
            return 'error';
        }
        $checkId = $check['id'] ?? ($check['orderId'] ?? null);
        if ($remoteId === '' || !is_numeric($checkId) || (int) $checkId !== (int) $remoteId) {
            return 'error';
        }
        if (isset($check['merchantOrderRef']) && is_scalar($check['merchantOrderRef']) && (string) $check['merchantOrderRef'] !== $orderId) {
            error_log('[atlaspaycheck] official reference mismatch for order ' . $orderId);
            return 'error';
        }
        $storedTotal = (int) ($row['atlaspay_total_amount'] ?? 0);
        if (isset($check['totalAmountToman']) && is_numeric($check['totalAmountToman']) && $storedTotal > 0 && (int) $check['totalAmountToman'] !== $storedTotal) {
            error_log('[atlaspaycheck] official total mismatch for order ' . $orderId);
            return 'error';
        }
        if (isset($check['baseAmountToman']) && is_numeric($check['baseAmountToman']) && (int) $check['baseAmountToman'] !== (int) $row['price']) {
            error_log('[atlaspaycheck] official base mismatch for order ' . $orderId);
            return 'error';
        }
        $status = strtolower(trim(is_scalar($check['status'] ?? null) ? (string) $check['status'] : ''));
        $late = (string) ($row['payment_Status'] ?? '') === 'expire';
        if (in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
            if (!$late) {
                if ($status === 'rejected') {
                    \Faoxima\Payments\Settlement\ConfirmationFunctions::payment_notify_user_failed($orderId, 'پرداخت توسط اطلس‌پی رد شد');
                } else {
                    \Faoxima\Payments\Settlement\Confirmation::markExpired($runtimePdo, $orderId);
                }
            }
            return 'terminal';
        }
        if (!in_array($status, ['confirmed', 'settled'], true)) {
            return 'open';
        }
        if (array_key_exists('paid', $check) && empty($check['paid'])) {
            return 'open';
        }
        $actual = isset($check['actualReceivedAmountToman']) && is_numeric($check['actualReceivedAmountToman']) ? (int) $check['actualReceivedAmountToman'] : null;
        $manual = !empty($check['requiresManualDelivery']) || $storedTotal <= 0 || ($actual !== null && $actual < $storedTotal);
        if ($manual) {
            try {
                $claim = $pdo->prepare(
                    "UPDATE Payment_report
                    SET atlaspay_local_state = 'manual_review', atlaspay_manual_delivery = 1, atlaspay_actual_received = ?
                  WHERE id_order = ?
                    AND payment_processing_version >= 2
                    AND payment_Status IN ('Unpaid','expire')
                    AND (atlaspay_local_state IS NULL OR atlaspay_local_state <> 'manual_review')"
                );
                $claim->execute([$actual, $orderId]);
                $claimed = $claim->rowCount() > 0;
            } catch (Throwable $e) {
                error_log('[atlaspaycheck] manual claim failed: ' . $e->getMessage());
                return 'error';
            }
            if ($claimed) {
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomReportToAdmin(
                    "⚠️ سفارش اطلس‌پی با کسری واریز تایید شد — نیاز به بررسی دستی.\n"
                    . "🛒 کد فاکتور: <code>" . \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::escape($orderId) . "</code>\n"
                    . "👤 آیدی کاربر: <code>" . \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::escape($row['id_user'] ?? '') . "</code>\n"
                    . "💰 مبلغ درخواستی (اعتبار/خرید): " . number_format((int) $row['price']) . " تومان\n"
                    . "💳 مبلغ سفارش اطلس‌پی: " . number_format($storedTotal) . " تومان\n"
                    . ($actual !== null ? '💸 مبلغ واریزی واقعی: ' . number_format($actual) . ' تومان' : '💸 مبلغ واریزی واقعی: نامشخص')
                );
            }
            return 'manual';
        }
        if (!method_exists(\Faoxima\Payments\Settlement\ConfirmationFunctions::class, 'payment_confirm_paid')) {
            require_once dirname(__DIR__, 4) . '/src/bootstrap.php';
        }
        $threadRow = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select");
        $lines = [
            '💰 مبلغ اعتباردهی: ' . number_format((int) $row['price']) . ' تومان',
            '💸 مبلغ سفارش اطلس‌پی: ' . number_format($storedTotal) . ' تومان',
        ];
        if ($actual !== null) {
            $lines[] = '💸 مبلغ نهایی تأییدشده توسط اطلس‌پی: ' . number_format($actual) . ' تومان';
        }
        $lines[] = '🔁 تایید از طریق پولر';
        if ($late) {
            $lines[] = '⏰ تأیید دیرهنگام پس از پایان مهلت محلی';
        }
        \Faoxima\Payments\Settlement\Confirmation::confirmPaid($runtimePdo, $runtimeSetting, $orderId, 'chashbackatlaspay', [
            'method' => 'atlaspay',
            'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
            'extra_lines' => $lines,
        ]);
        return 'completed';
        }
}
