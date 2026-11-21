<?php

namespace Faoxima\Payments\Gateways\Tonpay;

use PDO;
use Throwable;

final class Recovery
{
    public static function recoverStoredReferences($runtimePdo, $runtimeSetting)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM Payment_report
              WHERE Payment_Method = 'tonpay' AND payment_Status = 'reject'
                AND dec_not_confirmed LIKE 'tonpay_custom_store_unresolved:%'
                AND (tonpay_last_checked_at IS NULL OR tonpay_last_checked_at <= ?)
              ORDER BY tonpay_last_checked_at ASC, id ASC
              LIMIT 5"
            );
            $stmt->execute([time() - 900]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('TonPay custom recovery lookup failed: ' . $e->getMessage());
            return;
        }
        foreach ($rows as $row) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                return;
            }
            $parts = explode(':', (string) ($row['dec_not_confirmed'] ?? ''), 3);
            $invoiceId = trim((string) ($parts[1] ?? ''));
            if ($invoiceId === '') {
                continue;
            }
            try {
                $attempt = $pdo->prepare("UPDATE Payment_report SET tonpay_last_checked_at = ? WHERE id_order = ? AND Payment_Method = 'tonpay'");
                $attempt->execute([time(), (string) $row['id_order']]);
                $check = \Faoxima\Payments\Gateways\Tonpay\CustomClient::checkInvoice($invoiceId);
                if (\Faoxima\Payments\Gateways\Tonpay\RateLimit::isRateLimited($check)) {
                    return;
                }
                $invoice = \Faoxima\Payments\Gateways\Tonpay\CustomClient::normalizeInvoice($check);
                if ($invoice === null || $invoice['invoice_id'] !== $invoiceId
                    || (string) ($invoice['order_id'] ?? '') !== (string) $row['id_order']
                    || !\Faoxima\Payments\Gateways\Tonpay\Client::amountsMatch($invoice['request_amount'], $row['price'] ?? null)) {
                    continue;
                }
                $storedInvoiceId = trim((string) ($row['tonpay_invoice_id'] ?? ''));
                if ($storedInvoiceId !== '' && $storedInvoiceId !== $invoiceId) {
                    continue;
                }
                if (($storedInvoiceId === '' || (string) ($row['tonpay_gateway_mode'] ?? '') !== 'custom')
                    && !\Faoxima\Payments\Gateways\Tonpay\PaymentOrders::storeInvoice($runtimePdo, $row['id_order'], $invoice)) {
                    continue;
                }
                $restore = $pdo->prepare(
                    "UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = ?
                  WHERE id_order = ? AND Payment_Method = 'tonpay' AND tonpay_invoice_id = ?
                    AND payment_Status = 'reject' AND dec_not_confirmed = ?"
                );
                $restore->execute(['tonpay_custom_store_recovered:' . $invoiceId, (string) $row['id_order'], $invoiceId, (string) $row['dec_not_confirmed']]);
                $report = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($runtimePdo, $row['id_order']);
                if ($report !== null) {
                    \Faoxima\Payments\Gateways\Tonpay\Settlement::applyRemote($runtimePdo, $report, $check);
                    if ($invoice['status'] === 'completed' && !empty($invoice['paid'])) {
                        \Faoxima\Payments\Gateways\Tonpay\Settlement::fulfill($runtimePdo, $runtimeSetting, $report, $check);
                    } elseif (\Faoxima\Payments\Gateways\Tonpay\Client::terminalLocalStatus($invoice['status']) !== null) {
                        \Faoxima\Payments\Gateways\Tonpay\Client::syncTerminalStatus($runtimePdo, $row['id_order'], $invoice['status']);
                    }
                }
            } catch (Throwable $e) {
                error_log('TonPay custom stored reference recovery failed: ' . $e->getMessage());
            }
        }
        }

    public static function runCron($runtimePdo, $runtimeSetting)
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return;
        }
        \Faoxima\Payments\Gateways\Tonpay\Settlement::expireOverdue($runtimePdo);
        self::recoverStoredReferences($runtimePdo, $runtimeSetting);
        $now = time();
        $batches = [
            [
                "SELECT * FROM Payment_report
              WHERE Payment_Method = 'tonpay' AND tonpay_gateway_mode = 'custom'
                AND tonpay_invoice_id IS NOT NULL AND tonpay_invoice_id <> ''
                AND payment_Status = 'Unpaid'
                AND (tonpay_last_checked_at IS NULL OR tonpay_last_checked_at <= ?)
              ORDER BY tonpay_last_checked_at ASC, id ASC
              LIMIT 30",
                [$now - 45],
            ],
            [
                "SELECT * FROM Payment_report
              WHERE Payment_Method = 'tonpay' AND tonpay_gateway_mode = 'custom'
                AND tonpay_invoice_id IS NOT NULL AND tonpay_invoice_id <> ''
                AND payment_Status IN ('expire','reject','cancelled')
                AND (tonpay_local_state IS NULL OR tonpay_local_state <> 'closed')
                AND tonpay_created_at >= ?
                AND (tonpay_last_checked_at IS NULL OR tonpay_last_checked_at <= ?)
              ORDER BY tonpay_last_checked_at ASC, id ASC
              LIMIT 10",
                [$now - TONPAY_CUSTOM_LATE_RECONCILE_SECONDS, $now - 900],
            ],
        ];
        foreach ($batches as [$sql, $params]) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                error_log('[tonpaycheck] custom select failed: ' . $e->getMessage());
                continue;
            }
            foreach ($rows as $row) {
                if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                    return;
                }
                \Faoxima\Payments\Gateways\Tonpay\Settlement::updateColumns($runtimePdo, $row['id_order'], ['tonpay_last_checked_at' => time()], false);
                try {
                    $result = \Faoxima\Payments\Gateways\Tonpay\Settlement::pollOrder($runtimePdo, $runtimeSetting, $row, true);
                } catch (Throwable $e) {
                    error_log('[tonpaycheck] custom poll threw for order ' . $row['id_order'] . ': ' . $e->getMessage());
                    continue;
                }
                if (in_array($result['state'], ['rate_limited', 'local_rate_limited'], true)) {
                    return;
                }
            }
        }
        }
}
