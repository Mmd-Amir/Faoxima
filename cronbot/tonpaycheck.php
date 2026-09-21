<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('tonpaycheck', 60);

ini_set('error_log', 'error_log');

$ctx = rx_cron_load_payment_context();
if (empty($ctx['db_ready'])) {
    return;
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $pdo, $ManagePanel, $setting;
$ManagePanel = $ctx['managePanel'];
$setting = $ctx['setting'];

if (!($pdo instanceof PDO)) {
    error_log('[tonpaycheck] no PDO connection');
    return;
}

if (!function_exists('tonpayCheckInvoice')) {
    error_log('[tonpaycheck] tonpay functions not available');
    return;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id_order, tonpay_invoice_id, price
           FROM Payment_report
          WHERE payment_Status = 'Unpaid'
            AND Payment_Method = 'tonpay'
            AND tonpay_invoice_id IS NOT NULL
            AND tonpay_invoice_id <> ''
          ORDER BY id DESC
          LIMIT 30"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[tonpaycheck] select pending failed: ' . $e->getMessage());
    return;
}

if (empty($rows)) {
    return;
}

foreach ($rows as $row) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;

    $orderId = (string) $row['id_order'];
    $invoiceId = trim((string) $row['tonpay_invoice_id']);
    if ($invoiceId === '') {
        continue;
    }

    try {
        $check = tonpayCheckInvoice($invoiceId);
    } catch (Throwable $e) {
        error_log('[tonpaycheck] tonpayCheckInvoice threw for order ' . $orderId . ': ' . $e->getMessage());
        continue;
    }

    if (!is_array($check)) {
        continue;
    }

    $statusCode = (int) ($check['status_code'] ?? 0);
    if ($statusCode !== 0 && ($statusCode < 200 || $statusCode >= 300)) {
        continue;
    }

    if (empty($check['paid']) || (string) ($check['status'] ?? '') !== 'completed') {
        continue;
    }

    $finalAmount = isset($check['final_amount']) ? (float) $check['final_amount'] : (float) $row['price'];
    $extra = [
        'method'      => 'tonpay',
        'thread_id'   => $ctx['paymentreports'] ?? null,
        'extra_lines' => array_filter([
            '💸 مبلغ نهایی تأییدشده توسط تون‌پی : ' . number_format($finalAmount) . ' تومان',
            '🔁 تایید از طریق پولر',
        ]),
    ];

    payment_confirm_paid($orderId, 'chashbacktonpay', $extra);
}
