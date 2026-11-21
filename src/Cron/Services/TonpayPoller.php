<?php

namespace Faoxima\Cron\Services;

use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Payments\Fulfillment\PaymentPolicy;
use Faoxima\Payments\Gateways\Tonpay\Client;
use Faoxima\Payments\Gateways\Tonpay\RateLimit;
use Faoxima\Payments\Settlement\Confirmation;
use PDO;
use PDOStatement;
use Throwable;

final class TonpayPoller
{
    private PDO $pdo;
    private $setting;
    private $reportThread;
    private bool $fairPolling = true;

    public function __construct(PDO $pdo, $setting, $reportThread)
    {
        $this->pdo = $pdo;
        $this->setting = $setting;
        $this->reportThread = $reportThread;
    }

    public function run(): void
    {
        $rows = $this->selectPending();
        if ($rows === null) {
            return;
        }
        if (empty($rows)) {
            TaskState::rx_cron_task_complete('no_pending_payments');
            return;
        }

        $markChecked = $this->fairPolling
            ? $this->pdo->prepare("UPDATE Payment_report SET tonpay_last_checked_at = ? WHERE id_order = ?")
            : null;

        foreach ($rows as $row) {
            if (CronRuntime::rx_cron_budget_exhausted()) {
                break;
            }
            if (!$this->poll($row, $markChecked)) {
                break;
            }
        }
        TaskState::rx_cron_task_complete('tonpay_poll_finished');
    }

    private function selectPending(): ?array
    {
        $window = PaymentPolicy::cronWindowSql();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id_order, tonpay_invoice_id, price
                   FROM Payment_report
                  WHERE $window AND payment_Status = 'Unpaid'
                    AND Payment_Method = 'tonpay'
                    AND (tonpay_gateway_mode IS NULL OR tonpay_gateway_mode <> 'custom')
                    AND tonpay_invoice_id IS NOT NULL
                    AND tonpay_invoice_id <> ''
                    AND time >= ?
                    AND (tonpay_last_checked_at IS NULL OR tonpay_last_checked_at <= ?)
                  ORDER BY tonpay_last_checked_at IS NOT NULL, tonpay_last_checked_at ASC, id ASC
                  LIMIT 30"
            );
            $stmt->execute([date('Y/m/d H:i:s', time() - 86400), time() - 50]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[tonpaycheck] fair select failed, using legacy order: ' . $e->getMessage());
            $this->fairPolling = false;
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT id_order, tonpay_invoice_id, price
                       FROM Payment_report
                      WHERE $window AND payment_Status = 'Unpaid'
                        AND Payment_Method = 'tonpay'
                        AND tonpay_invoice_id IS NOT NULL
                        AND tonpay_invoice_id <> ''
                      ORDER BY id DESC
                      LIMIT 30"
                );
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                error_log('[tonpaycheck] select pending failed: ' . $e->getMessage());
                return null;
            }
        }
    }

    /** Returns false when polling must stop (rate limited). */
    private function poll(array $row, ?PDOStatement $markChecked): bool
    {
        $orderId = (string) $row['id_order'];
        $invoiceId = trim((string) $row['tonpay_invoice_id']);
        if ($invoiceId === '') {
            return true;
        }

        try {
            $check = Client::checkInvoice($invoiceId);
        } catch (Throwable $e) {
            error_log('[tonpaycheck] tonpayCheckInvoice threw for order ' . $orderId . ': ' . $e->getMessage());
            $check = null;
        }

        if (is_array($check) && !empty($check['local_rate_limited'])) {
            return false;
        }

        if ($markChecked !== null) {
            try {
                $markChecked->execute([time(), $orderId]);
            } catch (Throwable $e) {
                error_log('[tonpaycheck] mark checked failed for order ' . $orderId . ': ' . $e->getMessage());
            }
        }

        if (RateLimit::isRateLimited($check)) {
            return false;
        }
        if (!is_array($check)) {
            return true;
        }

        $statusCode = (int) ($check['status_code'] ?? 0);
        if ($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) {
            return true;
        }

        $remoteStatus = strtolower((string) ($check['status'] ?? ''));
        if (in_array($remoteStatus, ['need_action', 'action_required_less', 'action_required_more'], true)) {
            error_log('[tonpaycheck] invoice needs buyer action: ' . json_encode([
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'status' => $remoteStatus,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return true;
        }

        if (Client::terminalLocalStatus($remoteStatus) !== null) {
            Client::syncTerminalStatus($this->pdo, $orderId, $remoteStatus);
            return true;
        }

        if (empty($check['paid']) || $remoteStatus !== 'completed') {
            return true;
        }

        if (!Client::amountsMatch($check['request_amount'] ?? null, $row['price'])) {
            Client::logAmountMismatch('poller', $orderId, $invoiceId, $row['price'], $check['request_amount'] ?? null);
            return true;
        }

        $finalAmount = isset($check['final_amount']) ? (float) $check['final_amount'] : (float) $row['price'];
        Confirmation::confirmPaid($this->pdo, $this->setting, $orderId, 'chashbacktonpay', [
            'method'      => 'tonpay',
            'thread_id'   => $this->reportThread,
            'extra_lines' => array_filter([
                '💸 مبلغ نهایی تأییدشده توسط تون‌پی : ' . number_format($finalAmount) . ' تومان',
                '🔁 تایید از طریق پولر',
            ]),
        ]);
        return true;
    }
}
