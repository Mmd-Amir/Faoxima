<?php

namespace Faoxima\Cron\Services;

use Faoxima\Cron\Runtime\PollRun;
use Faoxima\Payments\Fulfillment\PaymentPolicy;
use Faoxima\Payments\Gateways\Blupal\Client;
use Faoxima\Payments\Settlement\ConfirmationFunctions;
use PDO;

final class BlupalPoller
{
    private PDO $pdo;
    private $reportThread;

    public function __construct(PDO $pdo, $reportThread)
    {
        $this->pdo = $pdo;
        $this->reportThread = $reportThread;
    }

    public function run(): void
    {
        $window = PaymentPolicy::cronWindowSql();
        $stmt = null;
        PollRun::cursorBatch(
            $this->pdo,
            'blupalcheck',
            'blupal_poll',
            function (string $after, $ceiling) use ($window, &$stmt): array {
                if ($stmt === null) {
                    $stmt = $this->pdo->prepare(
                        "SELECT id, id_order, blupal_invoice_id, price
                           FROM Payment_report
                          WHERE $window AND payment_Status = 'Unpaid'
                            AND Payment_Method = 'blupal'
                            AND blupal_invoice_id IS NOT NULL
                            AND blupal_invoice_id <> ''
                            AND id > ? AND id <= ?
                          ORDER BY id ASC
                          LIMIT 30"
                    );
                }
                $stmt->execute([$after, $ceiling]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            },
            function (array $row): void {
                $this->check($row);
            },
            'blupal_poll_finished'
        );
    }

    private function check(array $row): void
    {
        $orderId = (string) $row['id_order'];
        $invoiceId = trim((string) $row['blupal_invoice_id']);
        if ($invoiceId === '') {
            return;
        }

        $check = Client::checkInvoice($invoiceId);
        if (!is_array($check) || (int) ($check['invoice_id'] ?? 0) !== (int) $invoiceId) {
            return;
        }
        if (empty($check['success'])) {
            return;
        }

        $status = (string) ($check['status'] ?? '');
        if (in_array($status, ['EXPIRED', 'CANCELED'], true)) {
            ConfirmationFunctions::payment_mark_expired($orderId);
            return;
        }
        if ($status !== 'PAID') {
            return;
        }

        $expectedRial = Client::tomanToRial($row['price']);
        $amountRial = isset($check['amount']) ? (int) $check['amount'] : null;
        if ($amountRial === null || $amountRial !== $expectedRial) {
            error_log('[blupalcheck] amount mismatch on polled invoice, skipping: order=' . $orderId . ' invoice=' . $invoiceId);
            return;
        }

        $finalRial = isset($check['final_amount']) ? (float) $check['final_amount'] : null;
        if ($finalRial === null) {
            return;
        }

        $credit = Client::rialToToman($finalRial);
        $lines = ConfirmationFunctions::rx_blupal_confirmation_lines(
            $credit,
            trim((string) ($check['payer_name'] ?? '')),
            trim((string) ($check['payer_card'] ?? '')),
            trim((string) ($check['payer_bank_name'] ?? ''))
        ) + [4 => '🔁 تایید از طریق پولر (وب‌هوک دیر یا گم‌شده)'];

        ConfirmationFunctions::payment_confirm_paid($orderId, 'chashbackblupal', [
            'method'      => 'blupal',
            'thread_id'   => $this->reportThread,
            'extra_lines' => $lines,
        ]);
    }
}
