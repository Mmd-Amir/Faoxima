<?php

ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../lib/PaymentConfirm.php';
require __DIR__ . '/../vendor/autoload.php';

$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$reportRow = select('topicid', 'idreport', 'report', 'paymentreport', 'select');
$paymentreports = is_array($reportRow) ? ($reportRow['idreport'] ?? null) : null;


function rxPlisio_log($event, $msg, $ctx = []) {
    if (function_exists('rx_log_event')) {
        rx_log_event($event, $msg, $ctx);
    } else {
        error_log('[plisio-cb] ' . $event . ': ' . $msg . ' ' . json_encode($ctx));
    }
}


function rxPlisio_readBody(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    if (!empty($_POST)) return $_POST;
    if (!empty($_GET))  return $_GET;
    return [];
}


function rxPlisio_stringifyScalars(array $data): array
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $data[$k] = rxPlisio_stringifyScalars($v);
        } elseif (is_bool($v)) {
            $data[$k] = $v ? '1' : '';
        } elseif ($v === null) {
            $data[$k] = '';
        } elseif (is_int($v) || is_float($v)) {
            $data[$k] = (string) $v;
        }
    }
    return $data;
}


function rxPlisio_verifySignature(array $data, string $apiKey): bool
{
    if ($apiKey === '') return false;
    if (empty($data['verify_hash']) || !is_string($data['verify_hash'])) return false;

    $received = (string) $data['verify_hash'];
    unset($data['verify_hash']);
    ksort($data);


    $data = rxPlisio_stringifyScalars($data);

    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $data[$k] = json_encode($v);
        }
    }

    $postString = serialize($data);
    $expectedMd5  = hash_hmac('md5',  $postString, $apiKey);
    $expectedSha1 = hash_hmac('sha1', $postString, $apiKey);

    if (hash_equals($expectedMd5, $received))  return true;
    if (hash_equals($expectedSha1, $received)) return true;

    $altQuery     = http_build_query($data);
    $expectedQMd5 = hash_hmac('md5',  $altQuery, $apiKey);
    if (hash_equals($expectedQMd5, $received)) return true;

    return false;
}


$data = rxPlisio_readBody();
if (empty($data)) {
    http_response_code(400);
    rxPlisio_log('PLISIO_BAD_BODY', 'Empty body', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '?']);
    exit('Empty body');
}

$apiKey = (string) (select("PaySetting", "ValuePay", "NamePay", "api_plisio", "select")['ValuePay'] ?? '');
if ($apiKey === '' || $apiKey === '0') {
    $apiKey = (string) (select("PaySetting", "ValuePay", "NamePay", "apinowpayment", "select")['ValuePay'] ?? '');
}
if ($apiKey === '' || $apiKey === '0') {
    http_response_code(503);
    rxPlisio_log('PLISIO_NO_KEY', 'API key not configured', []);
    exit('no api key');
}

if (!rxPlisio_verifySignature($data, $apiKey)) {
    http_response_code(401);
    rxPlisio_log('PLISIO_SIG_FAIL', 'Signature verification failed', [
        'ip'    => $_SERVER['REMOTE_ADDR'] ?? '?',
        'order' => $data['order_number'] ?? null,
    ]);
    exit('bad signature');
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
    rxPlisio_log('PLISIO_NO_REF', 'Missing order_number/txn_id', []);
    exit('no ref');
}


$Payment_report = null;
if ($orderId !== '') {
    $Payment_report = select('Payment_report', '*', 'id_order', $orderId, 'select');
}
if (!is_array($Payment_report) && $txnId !== '') {
    $Payment_report = select('Payment_report', '*', 'dec_not_confirmed', $txnId, 'select');
}

if (!is_array($Payment_report) || empty($Payment_report)) {
    http_response_code(404);
    rxPlisio_log('PLISIO_REPORT_MISSING', 'Payment_report not found', [
        'order' => $orderId, 'txn' => $txnId,
    ]);
    exit('not found');
}


if ($status === 'expired') {
    $textexpire = "❌ تراکنش زیر بدلیل عدم پرداخت منقضی شد، لطفا وجهی بابت این تراکنش پرداخت نکنید\n\n🛒 کد سفارش: {$Payment_report['id_order']}\n💰 مبلغ:  {$Payment_report['price']} تومان";
    payment_mark_expired($Payment_report['id_order'], $textexpire);
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
    payment_notify_user_failed($Payment_report['id_order'], $reasonMap[$status] ?? '');
    http_response_code(200);
    echo 'ok';
    return;
}


if ($status !== 'completed') {
    http_response_code(200);
    rxPlisio_log('PLISIO_STATUS_OTHER', 'Unhandled status, acked', ['s' => $status, 'order' => $orderId]);
    echo 'ok';
    return;
}

if ($paidAmount !== '') {
    try {
        update('Payment_report', 'crypto_amount', $paidAmount, 'id_order', $Payment_report['id_order']);
    } catch (\Throwable $e) {}
}
if ($paidCurrency !== '') {
    try {
        update('Payment_report', 'crypto_currency', strtoupper($paidCurrency), 'id_order', $Payment_report['id_order']);
    } catch (\Throwable $e) {}
}

$paidLine = $paidAmount !== ''
    ? ("📥 مبلغ واریز شده : <b>{$paidAmount}</b>" . ($paidCurrency !== '' ? ' ' . strtoupper($paidCurrency) : ''))
    : "📥 مبلغ واریز شده : —";

$result = payment_confirm_paid(
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
    rxPlisio_log('PLISIO_PAID', 'Confirmed via webhook', [
        'order' => $Payment_report['id_order'],
        'user'  => $Payment_report['id_user'],
        'price' => $Payment_report['price'],
    ]);
}

http_response_code(200);
echo 'ok';
