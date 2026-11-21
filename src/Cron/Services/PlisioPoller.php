<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use Faoxima\Core\Support\Formatting;
use Faoxima\Cron\Runtime\CronBatches;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Payments\Fulfillment\PaymentPolicy;
use Faoxima\Payments\Gateways\Plisio\CronJobs;
use Faoxima\Payments\Settlement\ConfirmationFunctions;
use mysqli;
use PDO;
use RuntimeException;
use Throwable;

final class PlisioPoller
{
    private mysqli $connect;
    private ?PDO $pdo;
    private $reportThread;
    private string $cursorKey;
    private string $shardSql;

    public function __construct(mysqli $connect, $pdo, $reportThread)
    {
        $this->connect = $connect;
        $this->pdo = $pdo instanceof PDO ? $pdo : null;
        $this->reportThread = $reportThread;
        [$worker, $workers] = CronRuntime::rx_cron_shard();
        $this->shardSql = ($workers > 1) ? " AND MOD(id, $workers) = $worker " : "";
        $this->cursorKey = 'plisio_poll:' . $workers . ':' . $worker;
    }

    public function run(): void
    {
        $rows = $this->selectPending();
        if ($rows === null) {
            return;
        }
        CronBatches::rx_cron_run_poll_batch(
            $rows,
            function (array $row): void {
                $this->check($row);
            },
            static function (array $row, Throwable $e): void {
                error_log('[plisio] poll error for order ' . (string) ($row['id_order'] ?? '') . ': ' . $e->getMessage());
            },
            function (array $row): void {
                CronBatches::rx_cron_invoice_cursor($this->cursorKey, (string) $row['id']);
            }
        );
        TaskState::rx_cron_task_complete('plisio_poll_finished');
    }

    private function selectPending(): ?array
    {
        $window = PaymentPolicy::cronWindowSql();
        $sql = "SELECT id, id_order FROM Payment_report WHERE $window AND payment_Status = 'Unpaid' AND Payment_Method = 'plisio'{$this->shardSql} AND id > ? AND id <= ? ORDER BY id ASC LIMIT 15";
        try {
            $stmt = $this->connect->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('Plisio payment selection could not be prepared');
            }
            try {
                return CronBatches::rx_cron_payment_cursor_page($this->pdo, $this->cursorKey, static function (string $after, $ceiling) use ($stmt): array {
                    $through = (string) $ceiling;
                    $stmt->bind_param('ss', $after, $through);
                    if (!$stmt->execute() || !($result = $stmt->get_result())) {
                        throw new RuntimeException('Plisio payment selection failed');
                    }
                    try {
                        $rows = [];
                        while ($row = $result->fetch_assoc()) {
                            $rows[] = $row;
                        }
                        return $rows;
                    } finally {
                        $result->free();
                    }
                });
            } finally {
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('[plisio] poll selection failed: ' . get_class($e));
            TaskState::rx_cron_task_defer('select_failed', 120);
            return null;
        }
    }

    private function check(array $row): void
    {
        $reportStmt = $this->connect->prepare("SELECT id_order, price, dec_not_confirmed, payment_Status FROM Payment_report WHERE id_order = ? LIMIT 1");
        $reportStmt->bind_param('s', $row['id_order']);
        $reportStmt->execute();
        $payment = $reportStmt->get_result()->fetch_assoc();
        $reportStmt->close();
        if (!is_array($payment)) {
            return;
        }
        if ($payment['payment_Status'] == 'paid') {
            return;
        }
        if (!isset($payment['dec_not_confirmed']) || $payment['dec_not_confirmed'] == null) {
            return;
        }

        $status = CronJobs::statusplisio($payment['id_order']);
        if (!is_array($status) || !isset($status['data']['operations'][0]['status'])) {
            return;
        }
        $opStatus = $status['data']['operations'][0]['status'];

        if ($opStatus === 'cancelled' || $opStatus === 'expired') {
            $text = "❌ تراکنش زیر بدلیل عدم پرداخت منقضی شد، لطفا وجهی بابت این تراکنش پرداخت نکنید\n\n🛒 کد سفارش: {$payment['id_order']}\n💰 مبلغ:  " . Formatting::rxFormatToman($payment['price']) . " تومان";
            ConfirmationFunctions::payment_mark_expired($payment['id_order'], $text);
            return;
        }
        if ($opStatus !== 'completed') {
            return;
        }

        $op = $status['data']['operations'][0] ?? [];
        $invoiceUrl = (string) ($op['invoice_url'] ?? ($status['invoice_url'] ?? ''));
        $sourceAmount = trim((string) ($op['source_amount'] ?? ''));
        $invoiceAmount = trim((string) ($op['amount'] ?? ($status['invoice_total_sum'] ?? '')));
        $sourceCurrency = trim((string) ($op['source_currency'] ?? ''));
        $invoiceCurrency = trim((string) ($op['currency'] ?? ''));
        $paidAmount = $sourceAmount !== '' && $sourceAmount !== '0' ? $sourceAmount : $invoiceAmount;
        $paidCurrency = $sourceCurrency !== '' ? $sourceCurrency : $invoiceCurrency;
        $txUrl = (string) ($op['tx_url'][0] ?? ($status['tx_url'][0] ?? ''));

        if ($paidAmount !== '') {
            try {
                Db::update('Payment_report', 'crypto_amount', $paidAmount, 'id_order', $payment['id_order']);
            } catch (Throwable $e) {
                error_log('[plisio] crypto amount persistence failed: ' . get_class($e));
            }
        }
        if ($paidCurrency !== '') {
            try {
                Db::update('Payment_report', 'crypto_currency', strtoupper($paidCurrency), 'id_order', $payment['id_order']);
            } catch (Throwable $e) {
                error_log('[plisio] crypto currency persistence failed: ' . get_class($e));
            }
        }

        $lines = [ConfirmationFunctions::rx_plisio_paid_line($paidAmount, $paidCurrency)];
        if ($txUrl !== '') {
            $lines[] = "🔗 <a href=\"{$txUrl}\">لینک تراکنش </a>";
        }

        ConfirmationFunctions::payment_confirm_paid($payment['id_order'], 'chashbackplisio', [
            'method'      => 'plisio',
            'link_label'  => 'لینک پرداخت plisio',
            'link_url'    => $invoiceUrl,
            'thread_id'   => $this->reportThread,
            'extra_lines' => $lines,
        ]);
    }
}
