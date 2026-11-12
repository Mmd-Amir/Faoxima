<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('blupalcheck', 120);

ini_set('error_log', 'error_log');

$ctx = rx_cron_load_payment_context();
if (empty($ctx['db_ready'])) {
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('payment_context_unavailable', 180);
    }
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
    $rxBlupalCursor = function_exists('rx_cron_invoice_cursor') ? rx_cron_invoice_cursor('blupal_poll') : '';
    $rxBlupalSql = "SELECT id, id_order, blupal_invoice_id, price
           FROM Payment_report
          WHERE payment_Status = 'Unpaid'
            AND Payment_Method = 'blupal'
            AND blupal_invoice_id IS NOT NULL
            AND blupal_invoice_id <> ''
            AND id > ?
          ORDER BY id ASC
          LIMIT 30";
    $stmt = $pdo->prepare($rxBlupalSql);
    $stmt->execute([$rxBlupalCursor]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows && $rxBlupalCursor !== '') {
        $stmt->execute(['']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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

$rxBlupalTimedOut = false;
foreach ($rows as $row) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
        $rxBlupalTimedOut = true;
        if (function_exists('rx_cron_task_defer')) {
            rx_cron_task_defer('time_budget', 60);
        }
        break;
    }
    try {
        $orderId   = (string) $row['id_order'];
        $invoiceId = trim((string) $row['blupal_invoice_id']);
        if ($invoiceId === '') {
            continue;
        }

        $check = blupalCheckInvoice($invoiceId);
        if (!is_array($check) || (int) ($check['invoice_id'] ?? 0) !== (int) $invoiceId) {
            continue;
        }
        if (empty($check['success'])) {
            continue;
        }

        $blupalStatus = (string) ($check['status'] ?? '');
        if (in_array($blupalStatus, ['EXPIRED', 'CANCELED'], true)) {
            payment_mark_expired($orderId);
            continue;
        }
        if ($blupalStatus !== 'PAID') {
            continue;
        }

        $expectedRial = blupalTomanToRial($row['price']);
        $checkAmountRial = isset($check['amount']) ? (int) $check['amount'] : null;
        if ($checkAmountRial === null || $checkAmountRial !== $expectedRial) {
            error_log('[blupalcheck] amount mismatch on polled invoice, skipping: order=' . $orderId . ' invoice=' . $invoiceId);
            continue;
        }

        $finalAmountRial = isset($check['final_amount']) ? (float) $check['final_amount'] : null;
        if ($finalAmountRial === null) {
            continue;
        }

        $creditAmountToman = blupalRialToToman($finalAmountRial);
        $payerName = trim((string) ($check['payer_name'] ?? ''));
        $payerCard = trim((string) ($check['payer_card'] ?? ''));
        $payerBank = trim((string) ($check['payer_bank_name'] ?? ''));

        $extra = [
            'method'      => 'blupal',
            'thread_id'   => $ctx['paymentreports'] ?? null,
            'extra_lines' => array_filter([
                $creditAmountToman > 0 ? ('💸 مبلغ نهایی تأییدشده توسط بلوپال : ' . number_format($creditAmountToman) . ' تومان') : '',
                $payerName !== '' ? ('👤 نام واریزکننده : ' . $payerName) : '',
                $payerCard !== '' ? ('💳 شماره کارت/شبا واریزکننده : ' . $payerCard) : '',
                $payerBank !== '' ? ('🏦 بانک واریزکننده : ' . $payerBank) : '',
                '🔁 تایید از طریق پولر (وب‌هوک دیر یا گم‌شده)',
            ]),
        ];

        payment_confirm_paid($orderId, 'chashbackblupal', $extra);
    } catch (Throwable $e) {
        error_log('[blupal_poll] order ' . (string) ($row['id_order'] ?? '') . ': ' . $e->getMessage());
    } finally {
        if ((int) ($row['id'] ?? 0) > 0 && function_exists('rx_cron_invoice_cursor')) {
            rx_cron_invoice_cursor('blupal_poll', (string) $row['id']);
        }
    }
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('blupal_poll_finished');
}
