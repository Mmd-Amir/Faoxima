<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('blupalcheck', 120);

ini_set('error_log', 'error_log');

$ctx = rx_cron_require_payment_context();
if ($ctx === null) {
    return;
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $pdo, $ManagePanel;
$ManagePanel = $ctx['managePanel'];

if (!($pdo instanceof PDO)) {
    error_log('[blupalcheck] no PDO connection');
    return;
}

if (!function_exists('blupalCheckInvoice')) {
    error_log('[blupalcheck] blupal functions not available');
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('blupal_functions_unavailable', 300);
    }
    return;
}

try {
    $rxPaymentWindow = rx_pf_cron_window_sql();
    $rxBlupalSql = "SELECT id, id_order, blupal_invoice_id, price
           FROM Payment_report
          WHERE $rxPaymentWindow AND payment_Status = 'Unpaid'
            AND Payment_Method = 'blupal'
            AND blupal_invoice_id IS NOT NULL
            AND blupal_invoice_id <> ''
            AND id > ? AND id <= ?
          ORDER BY id ASC
          LIMIT 30";
    $stmt = $pdo->prepare($rxBlupalSql);
    $rows = rx_cron_payment_cursor_page($pdo, 'blupal_poll', static function (string $after, $ceiling) use ($stmt): array {
        $stmt->execute([$after, $ceiling]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });

} catch (Throwable $e) {
    error_log('[blupalcheck] select pending failed: ' . $e->getMessage());
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('select_failed', 120);
    }
    return;
}

if (empty($rows)) {
    if (function_exists('rx_cron_task_complete')) {
        rx_cron_task_complete('no_pending_payments');
    }
    return;
}

rx_cron_run_poll_batch(
    $rows,
    static function (array $row) use ($ctx): void {
        $orderId   = (string) $row['id_order'];
        $invoiceId = trim((string) $row['blupal_invoice_id']);
        if ($invoiceId === '') {
            return;
        }

        $check = blupalCheckInvoice($invoiceId);
        if (!is_array($check) || (int) ($check['invoice_id'] ?? 0) !== (int) $invoiceId) {
            return;
        }
        if (empty($check['success'])) {
            return;
        }

        $blupalStatus = (string) ($check['status'] ?? '');
        if (in_array($blupalStatus, ['EXPIRED', 'CANCELED'], true)) {
            payment_mark_expired($orderId);
            return;
        }
        if ($blupalStatus !== 'PAID') {
            return;
        }

        $expectedRial = blupalTomanToRial($row['price']);
        $checkAmountRial = isset($check['amount']) ? (int) $check['amount'] : null;
        if ($checkAmountRial === null || $checkAmountRial !== $expectedRial) {
            error_log('[blupalcheck] amount mismatch on polled invoice, skipping: order=' . $orderId . ' invoice=' . $invoiceId);
            return;
        }

        $finalAmountRial = isset($check['final_amount']) ? (float) $check['final_amount'] : null;
        if ($finalAmountRial === null) {
            return;
        }

        $creditAmountToman = blupalRialToToman($finalAmountRial);
        $payerName = trim((string) ($check['payer_name'] ?? ''));
        $payerCard = trim((string) ($check['payer_card'] ?? ''));
        $payerBank = trim((string) ($check['payer_bank_name'] ?? ''));

        $extra = [
            'method'      => 'blupal',
            'thread_id'   => $ctx['paymentreports'] ?? null,
            'extra_lines' => rx_blupal_confirmation_lines($creditAmountToman, $payerName, $payerCard, $payerBank)
                + [4 => '🔁 تایید از طریق پولر (وب‌هوک دیر یا گم‌شده)'],
        ];

        payment_confirm_paid($orderId, 'chashbackblupal', $extra);
    },
    static function (array $row, Throwable $e): void {
        error_log('[blupal_poll] order ' . (string) ($row['id_order'] ?? '') . ': ' . $e->getMessage());
    },
    static function (array $row): void {
        if ((int) ($row['id'] ?? 0) > 0 && function_exists('rx_cron_invoice_cursor')) {
            rx_cron_invoice_cursor('blupal_poll', (string) $row['id']);
        }
    }
);
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('blupal_poll_finished');
}
