<?php

ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';

$ManagePanel = new ManagePanel();
$setting = \Faoxima\Core\Database\Db::select("setting", "*");
$reportRow = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'paymentreport', 'select');
$paymentreports = is_array($reportRow) ? ($reportRow['idreport'] ?? null) : null;







$data = \Faoxima\Payments\Gateways\Plisio\Callbacks::rxPlisio_readBody();
if (empty($data)) {
    http_response_code(400);
    \Faoxima\Payments\Gateways\Plisio\Callbacks::rxPlisio_log('PLISIO_BAD_BODY', 'Empty body', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '?']);
    exit('Empty body');
}

$apiKey = (string) (\Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "api_plisio", "select")['ValuePay'] ?? '');
if ($apiKey === '' || $apiKey === '0') {
    $apiKey = (string) (\Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apinowpayment", "select")['ValuePay'] ?? '');
}
if ($apiKey === '' || $apiKey === '0') {
    http_response_code(503);
    \Faoxima\Payments\Gateways\Plisio\Callbacks::rxPlisio_log('PLISIO_NO_KEY', 'API key not configured', []);
    exit('no api key');
}

if (!\Faoxima\Payments\Gateways\Plisio\Webhook::verifySignature($data, $apiKey)) {
    // Unverified IPN: ACK with 200 (not 401) so Plisio stops retrying and this
    // expected, self-recovering case is not recorded as a failed request. We never
    // process unverified data below; the polling cron (cronbot/plisio.php) confirms
    // the payment, so nothing is lost.
    http_response_code(200);
    echo 'ok';
    return;
}

$status     = strtolower(trim((string)($data['status']        ?? '')));
$orderId    = trim((string)($data['order_number'] ?? ''));
$txnId      = trim((string)($data['txn_id']       ?? ''));
$invoiceTot = (string)($data['invoice_total_sum'] ?? '0');
$invoiceUrl = (string)($data['invoice_url']       ?? '');
$sourceAmt  = trim((string)($data['source_amount']   ?? ''));
$sourceCur  = trim((string)($data['source_currency'] ?? ''));
$invoiceCur = trim((string)($data['currency']        ?? ''));
$paidAmount = '';
if ($sourceAmt !== '' && $sourceAmt !== '0') {
    $paidAmount = $sourceAmt;
} elseif ($invoiceTot !== '' && $invoiceTot !== '0') {
    $paidAmount = $invoiceTot;
}
$paidCurrency = $sourceCur !== '' ? $sourceCur : $invoiceCur;


if ($status === 'new' || $status === 'pending') {
    http_response_code(200);
    echo 'ok';
    return;
}

if ($orderId === '' && $txnId === '') {
    http_response_code(400);
    \Faoxima\Payments\Gateways\Plisio\Callbacks::rxPlisio_log('PLISIO_NO_REF', 'Missing order_number/txn_id', []);
    exit('no ref');
}


$Payment_report = null;
if ($orderId !== '') {
    $Payment_report = \Faoxima\Core\Database\Db::select('Payment_report', '*', 'id_order', $orderId, 'select');
}
if (!is_array($Payment_report) && $txnId !== '') {
    $Payment_report = \Faoxima\Core\Database\Db::select('Payment_report', '*', 'dec_not_confirmed', $txnId, 'select');
}

if (!is_array($Payment_report) || ($Payment_report['Payment_Method'] ?? '') !== 'plisio'
    || ($txnId !== '' && (string) ($Payment_report['dec_not_confirmed'] ?? '') !== ''
        && !hash_equals((string) $Payment_report['dec_not_confirmed'], $txnId))) {
    http_response_code(404);
    \Faoxima\Payments\Gateways\Plisio\Callbacks::rxPlisio_log('PLISIO_REPORT_MISSING', 'Payment_report not found', [
        'order' => $orderId, 'txn' => $txnId,
    ]);
    exit('not found');
}


if ($status === 'expired') {
    $textexpire = "❌ تراکنش زیر بدلیل عدم پرداخت منقضی شد، لطفا وجهی بابت این تراکنش پرداخت نکنید\n\n🛒 کد سفارش: {$Payment_report['id_order']}\n💰 مبلغ:  " . \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price']) . " تومان";
    \Faoxima\Payments\Settlement\Confirmation::markExpired($pdo, $Payment_report['id_order'], $textexpire);
    http_response_code(200);
    echo 'ok';
    return;
}
if (in_array($status, ['cancelled', 'mismatch', 'error'], true)) {
    $reasonMap = [
        'cancelled' => 'پرداخت از سمت کاربر/درگاه لغو شد',
        'mismatch'  => 'مبلغ واریزی با فاکتور همخوانی نداشت',
        'error'     => 'خطایی در پردازش پرداخت رخ داد',
    ];
    \Faoxima\Payments\Settlement\ConfirmationFunctions::payment_notify_user_failed($Payment_report['id_order'], $reasonMap[$status] ?? '');
    http_response_code(200);
    echo 'ok';
    return;
}


if ($status !== 'completed') {
    http_response_code(200);
    \Faoxima\Payments\Gateways\Plisio\Callbacks::rxPlisio_log('PLISIO_STATUS_OTHER', 'Unhandled status, acked', ['s' => $status, 'order' => $orderId]);
    echo 'ok';
    return;
}

if ($paidAmount !== '') {
    try {
        \Faoxima\Core\Database\Db::update('Payment_report', 'crypto_amount', $paidAmount, 'id_order', $Payment_report['id_order']);
    } catch (\Throwable $e) {}
}
if ($paidCurrency !== '') {
    try {
        \Faoxima\Core\Database\Db::update('Payment_report', 'crypto_currency', strtoupper($paidCurrency), 'id_order', $Payment_report['id_order']);
    } catch (\Throwable $e) {}
}

$paidLine = \Faoxima\Payments\Settlement\ConfirmationFunctions::rx_plisio_paid_line($paidAmount, $paidCurrency);

$result = \Faoxima\Payments\Settlement\Confirmation::confirmPaid($pdo, $setting,
    $Payment_report['id_order'],
    'chashbackplisio',
    [
        'method'      => 'plisio (webhook)',
        'link_label'  => 'لینک پرداخت plisio',
        'link_url'    => $invoiceUrl,
        'thread_id'   => $paymentreports,
        'extra_lines' => [$paidLine],
    ]
);

if (!empty($result['ok'])) {
    \Faoxima\Payments\Gateways\Plisio\Callbacks::rxPlisio_log('PLISIO_PAID', 'Confirmed via webhook', [
        'order' => $Payment_report['id_order'],
        'user'  => $Payment_report['id_user'],
        'price' => $Payment_report['price'],
    ]);
}

http_response_code(200);
echo 'ok';
