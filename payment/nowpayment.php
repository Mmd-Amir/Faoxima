<?php


$__rxNowpayLogDir = __DIR__ . '/../logs';
if (!is_dir($__rxNowpayLogDir)) {
    @mkdir($__rxNowpayLogDir, 0775, true);
}
$__rxNowpayLogFile = $__rxNowpayLogDir . '/nowpayment_ipn.log';


$__rxNowpayVerbose = false;

$__rxRawLog = static function (string $msg, $ctx = null, bool $important = false) use ($__rxNowpayLogFile, $__rxNowpayVerbose): void {
    if (!$important && !$__rxNowpayVerbose) {
        return;
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($ctx !== null) {
        $line .= ' ' . (is_string($ctx) ? $ctx : json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    @file_put_contents($__rxNowpayLogFile, $line . PHP_EOL, FILE_APPEND);
    if ($important) {
        @error_log($line);
    }
};


$__rxRawBody = file_get_contents('php://input');
$__rxHeaders = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $__rxHeaders[$k] = is_scalar($v) ? (string)$v : '<non-scalar>';
    }
}
$__rxRawLog('=== IPN HIT ===', [
    'method'      => $_SERVER['REQUEST_METHOD'] ?? '?',
    'remote_ip'   => $_SERVER['REMOTE_ADDR'] ?? '?',
    'content_len' => strlen((string)$__rxRawBody),
]);


register_shutdown_function(static function () use ($__rxRawLog) {
    $err = error_get_last();
    if (!$err) return;
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (!in_array($err['type'] ?? 0, $fatal, true)) return;
    $__rxRawLog('FATAL during IPN handling', [
        'message' => $err['message'] ?? '?',
        'file'    => $err['file'] ?? '?',
        'line'    => $err['line'] ?? '?',
    ], true);
});


ini_set('display_errors', '0');
ini_set('log_errors', '1');


try {
    require_once __DIR__ . '/../config.php';
    $__rxRawLog('config.php loaded', ['has_pdo' => isset($pdo) && $pdo instanceof PDO]);
} catch (Throwable $e) {
    $__rxRawLog('FAILED loading config.php', ['error' => $e->getMessage()], true);
    http_response_code(500);
    exit('config-failed');
}

try {
    require_once __DIR__ . '/../botapi.php';
    require_once __DIR__ . '/../panels.php';
    require_once __DIR__ . '/../function.php';
    require_once __DIR__ . '/../keyboard.php';
    require_once __DIR__ . '/../jdf.php';
    if (is_file(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
    require_once __DIR__ . '/../lib/PaymentConfirm.php';
    $__rxRawLog('all deps loaded');
} catch (Throwable $e) {
    $__rxRawLog('FAILED loading dependencies', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], true);
    http_response_code(500);
    exit('deps-failed');
}


if (!isset($pdo) || !($pdo instanceof PDO)) {
    $__rxRawLog('ABORT: no PDO connection (config.php credentials empty?)', null, true);
    http_response_code(500);
    exit('no-db');
}


$ManagePanel    = class_exists('ManagePanel') ? new ManagePanel() : null;
$setting        = function_exists('select') ? select('setting', '*') : [];
$paymentreports = function_exists('select')
    ? (select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? null)
    : null;


$data = json_decode((string)$__rxRawBody, true);
if (!is_array($data)) {
    $__rxRawLog('REJECT: body is not valid JSON');
    http_response_code(400);
    exit('Invalid body');
}


$ipnSecretRow = select('PaySetting', 'ValuePay', 'NamePay', 'nowpayment_ipn_secret', 'select');
$ipnSecret    = is_array($ipnSecretRow) ? trim((string)($ipnSecretRow['ValuePay'] ?? '')) : '';
if ($ipnSecret !== '') {
    $sig = (string)($_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '');
    if ($sig === '') {
        $__rxRawLog('REJECT: signature header missing (secret configured)', null, true);
        http_response_code(401);
        exit('Missing signature');
    }
    ksort($data);
    $sortedJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $expected   = hash_hmac('sha512', $sortedJson, $ipnSecret);
    if (!hash_equals($expected, $sig)) {
        $__rxRawLog('REJECT: signature mismatch', [
            'expected_prefix' => substr($expected, 0, 16),
            'got_prefix'      => substr($sig, 0, 16),
        ], true);
        http_response_code(401);
        exit('Bad signature');
    }
    $__rxRawLog('signature OK');
} else {
    $__rxRawLog('no IPN secret configured — skipping signature check');
}

$paymentStatus = strtolower(trim((string)($data['payment_status'] ?? '')));
$paymentIdRaw  = isset($data['payment_id']) ? (string)$data['payment_id'] : '';
$orderIdRaw    = isset($data['order_id'])   ? (string)$data['order_id']   : '';
$invoiceIdRaw  = isset($data['invoice_id']) ? (string)$data['invoice_id'] : '';
$priceAmount   = isset($data['price_amount']) ? (float)$data['price_amount'] : 0.0;
$actuallyPaid  = isset($data['actually_paid']) ? (float)$data['actually_paid'] : 0.0;

$__rxRawLog('parsed callback', [
    'status'    => $paymentStatus,
    'order_id'  => $orderIdRaw,
    'payment_id'=> $paymentIdRaw,
    'invoice_id'=> $invoiceIdRaw,
    'price'     => $priceAmount,
    'paid'      => $actuallyPaid,
]);

$terminalStatuses = ['finished', 'confirmed', 'sending'];
$paidStatuses     = $terminalStatuses;
if ($paymentStatus === 'partially_paid' && $priceAmount > 0 && $actuallyPaid >= ($priceAmount - 0.000001)) {
    $paidStatuses[] = 'partially_paid';
}

if (!in_array($paymentStatus, $paidStatuses, true)) {
    $__rxRawLog('IGNORED: status not in paid list', ['status' => $paymentStatus]);
    http_response_code(200);
    echo 'ignored';
    exit;
}

if ($paymentIdRaw !== '' && !preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $paymentIdRaw)) {
    $__rxRawLog('REJECT: invalid payment_id format');
    http_response_code(400);
    exit('Invalid payment_id');
}

$Payment_report = null;
$matchedBy = '';

if ($orderIdRaw !== '' && preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $orderIdRaw)) {
    $Payment_report = select("Payment_report", "*", "id_order", $orderIdRaw, "select");
    if ($Payment_report) {
        $matchedBy = 'order_id(IPN)';
    }
}

if (!$Payment_report && $invoiceIdRaw !== '') {
    $Payment_report = select("Payment_report", "*", "dec_not_confirmed", $invoiceIdRaw, "select");
    if ($Payment_report) {
        $matchedBy = 'invoice_id(IPN)';
    }
}

$pay = null;
if (!$Payment_report && $paymentIdRaw !== '' && function_exists('StatusPayment')) {
    $__rxRawLog('falling back to StatusPayment lookup', ['payment_id' => $paymentIdRaw]);
    $pay = StatusPayment($paymentIdRaw);
    if (is_array($pay)) {
        $__rxRawLog('StatusPayment response', $pay);
        $invFromApi = isset($pay['invoice_id']) ? (string)$pay['invoice_id'] : '';
        $ordFromApi = isset($pay['order_id'])   ? (string)$pay['order_id']   : '';

        if ($ordFromApi !== '' && preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $ordFromApi)) {
            $Payment_report = select("Payment_report", "*", "id_order", $ordFromApi, "select");
            if ($Payment_report) { $matchedBy = 'order_id(API)'; }
        }
        if (!$Payment_report && $invFromApi !== '') {
            $Payment_report = select("Payment_report", "*", "dec_not_confirmed", $invFromApi, "select");
            if ($Payment_report) { $matchedBy = 'invoice_id(API)'; }
        }
    } else {
        $__rxRawLog('StatusPayment returned non-array', ['response' => $pay]);
    }
}

if (!$Payment_report) {
    $__rxRawLog('FAILED: no Payment_report matched any lookup', [
        'order_id'   => $orderIdRaw,
        'invoice_id' => $invoiceIdRaw,
        'payment_id' => $paymentIdRaw,
    ], true);
    http_response_code(200);
    echo 'no-match';
    exit;
}

$orderId = (string)$Payment_report['id_order'];
$__rxRawLog('matched Payment_report', [
    'matched_by' => $matchedBy,
    'id_order'   => $orderId,
    'current_status' => $Payment_report['payment_Status'] ?? '?',
    'method'     => $Payment_report['Payment_Method'] ?? '?',
    'price'      => $Payment_report['price'] ?? '?',
    'id_user'    => $Payment_report['id_user'] ?? '?',
]);

if ((string)$Payment_report['payment_Status'] === 'paid') {
    $__rxRawLog('SKIP: already paid', ['order_id' => $orderId]);
    http_response_code(200);
    echo 'already-paid';
    exit;
}

$txHash = (string)($data['payin_hash'] ?? '');
if ($txHash === '' && is_array($pay) && isset($pay['payin_hash'])) {
    $txHash = (string)$pay['payin_hash'];
}
$reportExtra = [
    'method'      => 'nowpayment',
    'link_label'  => 'لینک پرداخت',
    'link_url'    => $txHash !== '' ? 'https://tronscan.org/#/transaction/' . $txHash : '',
    'thread_id'   => $paymentreports,
    'extra_lines' => [
        '📥 مبلغ واریز شده : ' . ($actuallyPaid > 0 ? $actuallyPaid : ($data['pay_amount'] ?? '—')),
    ],
];

try {
    $result = payment_confirm_paid($orderId, 'cashbacknowpayment', $reportExtra);

    $__rxRawLog($result['ok'] ? 'OK confirmed' : 'WARN confirm noop', $result, true);
} catch (Throwable $e) {
    $__rxRawLog('EXCEPTION in payment_confirm_paid', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ], true);
    http_response_code(500);
    exit('confirm-failed');
}

http_response_code(200);
echo isset($result['ok']) && $result['ok'] ? 'ok' : 'noop';
