<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('nowpaymentcheck', 120);

ini_set('error_log', 'error_log');

$ctx = rx_cron_load_payment_context();
if (empty($ctx['db_ready'])) {
    return;
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';

global $pdo, $setting;
$setting        = $ctx['setting'];
$paymentreports = $ctx['paymentreports'];

if (!($pdo instanceof PDO)) {
    error_log('[nowpaymentcheck] no PDO connection');
    return;
}

$logFile = __DIR__ . '/../logs/nowpayment_poll.log';
$log = static function (string $level, string $msg, array $ctx = []) use ($logFile): void {
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] [nowpaymentcheck] ' . $msg;
    if (!empty($ctx)) {
        $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
    if ($level === 'ERROR' || $level === 'WARN') {
        error_log($line);
    }
};

try {
    $stmt = $pdo->prepare(
        "SELECT id_order, dec_not_confirmed, price, id_user, time
           FROM Payment_report
          WHERE payment_Status = 'Unpaid'
            AND Payment_Method = 'nowpayment'
            AND dec_not_confirmed IS NOT NULL
            AND dec_not_confirmed <> ''
          ORDER BY id DESC
          LIMIT 30"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $log('ERROR', 'select pending failed', ['err' => $e->getMessage()]);
    return;
}

if (empty($rows)) {
    return;
}

$apiKeyRow = select('PaySetting', 'ValuePay', 'NamePay', 'api_nowpayment', 'select');
$apiKey    = is_array($apiKeyRow) ? trim((string)($apiKeyRow['ValuePay'] ?? '')) : '';
if ($apiKey === '') {

    $apiKeyRow = select('PaySetting', 'ValuePay', 'NamePay', 'marchent_tronseller', 'select');
    $apiKey    = is_array($apiKeyRow) ? trim((string)($apiKeyRow['ValuePay'] ?? '')) : '';
}
if ($apiKey === '') {
    $log('WARN', 'no NowPayments api key configured');
    return;
}

$noticeMarker = __DIR__ . '/_nowpayment_poll_disabled.flag';
$noticeAge    = is_file($noticeMarker) ? (time() - (int) @filemtime($noticeMarker)) : 99999;
if ($noticeAge > 86400) {
    $log('INFO', 'NowPayments list-by-invoice endpoint requires JWT auth (not available with x-api-key). '
        . 'Polling DISABLED — relying on IPN only. Make sure IPN URL is set in NowPayments dashboard: '
        . 'https://' . ($GLOBALS['domainhosts'] ?? '?') . '/payment/nowpayment.php');
    @touch($noticeMarker);
}
return;
$fetchInvoicePayments = static function (string $invoiceId) use ($apiKey, $log): ?array {
    return null;
};

foreach ($rows as $row) {
    $orderId   = (string)$row['id_order'];
    $invoiceId = trim((string)$row['dec_not_confirmed']);

    if (!preg_match('/^\d{1,20}$/', $invoiceId)) {

        continue;
    }

    $payments = $fetchInvoicePayments($invoiceId);
    if (!is_array($payments)) {
        continue;
    }

    $finishedPayment = null;
    foreach ($payments as $p) {
        $status = strtolower((string)($p['payment_status'] ?? ''));
        if (in_array($status, ['finished', 'confirmed', 'sending'], true)) {
            $finishedPayment = $p;
            break;
        }
        if ($status === 'partially_paid') {
            $price  = (float)($p['price_amount']   ?? 0);
            $paid   = (float)($p['actually_paid']  ?? 0);
            if ($price > 0 && $paid >= ($price - 0.000001)) {
                $finishedPayment = $p;
                break;
            }
        }
    }

    if ($finishedPayment === null) {
        continue;
    }

    $log('INFO', 'detected finished payment via polling', [
        'order_id'   => $orderId,
        'invoice_id' => $invoiceId,
        'status'     => $finishedPayment['payment_status'] ?? null,
    ]);

    $txHash    = (string)($finishedPayment['payin_hash'] ?? '');
    $actually  = (float)($finishedPayment['actually_paid'] ?? 0);
    $extra = [
        'method'      => 'nowpayment',
        'link_label'  => 'لینک پرداخت',
        'link_url'    => $txHash !== '' ? 'https://tronscan.org/#/transaction/' . $txHash : '',
        'thread_id'   => $paymentreports,
        'extra_lines' => [
            '📥 مبلغ واریز شده : ' . ($actually > 0 ? $actually : ($finishedPayment['pay_amount'] ?? '—')),
            '🔁 تایید از طریق پولر (IPN دیر یا گم‌شده)',
        ],
    ];

    $result = payment_confirm_paid($orderId, 'cashbacknowpayment', $extra);
    $log($result['ok'] ? 'OK' : 'WARN', 'payment_confirm_paid result', $result);
}
