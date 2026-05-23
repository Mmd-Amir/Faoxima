<?php
session_start();

ini_set('error_log', 'error_log');

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config.php';
require_once $projectRoot . '/function.php';

$textbotlang = languagechange($projectRoot . '/text.json');

$user_id = htmlspecialchars($_GET['user_id'] ?? '', ENT_QUOTES, 'UTF-8');
$amount = htmlspecialchars($_GET['price'] ?? '', ENT_QUOTES, 'UTF-8');
$invoice_id = htmlspecialchars($_GET['order_id'] ?? '', ENT_QUOTES, 'UTF-8');

$amountInt = filter_var($amount, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$invoiceIdSanitized = trim($invoice_id);
$userIdSanitized = trim($user_id);

if ($userIdSanitized === '' || $amountInt === false || $invoiceIdSanitized === '') {
    http_response_code(400);
    echo 'پارامترهای ارسالی نامعتبر است.';
    exit;
}

$paymentReport = select('Payment_report', '*', 'id_order', $invoiceIdSanitized, 'select');
if (!is_array($paymentReport) || !isset($paymentReport['price'])) {
    http_response_code(404);
    echo 'تراکنش مورد نظر یافت نشد.';
    exit;
}

$expectedAmount = (int) $paymentReport['price'];

if ($expectedAmount !== $amountInt) {
    http_response_code(400);
    echo 'مبلغ پرداختی با فاکتور مطابقت ندارد.';
    exit;
}

if (($paymentReport['Payment_Method'] ?? '') !== 'zarinpay') {
    http_response_code(400);
    echo 'روش پرداخت انتخاب شده معتبر نیست.';
    exit;
}

if ($paymentReport['payment_Status'] === 'paid') {
    http_response_code(409);
    echo 'این تراکنش قبلاً پرداخت شده است.';
    exit;
}

$zarinpayToken = getPaySettingValue('token_zarinpey');
if (empty($zarinpayToken) || $zarinpayToken === '0') {
    http_response_code(500);
    echo 'توکن زرین پی تنظیم نشده است.';
    exit;
}

$creationResult = createPayZarinpey($amountInt, $invoiceIdSanitized, $userIdSanitized);
if (empty($creationResult['success'])) {
    $errorMessage = $creationResult['message'] ?? 'خطا در ایجاد درگاه پرداخت.';
    $statusCode = $creationResult['http_code'] ?? 500;
    if (!is_int($statusCode) || $statusCode < 100 || $statusCode > 599) {
        $statusCode = 500;
    }

    http_response_code($statusCode);
    echo $errorMessage;
    exit;
}

$_SESSION['authority'] = $creationResult['authority'];
$_SESSION['order_id'] = $invoiceIdSanitized;

header('Location: ' . $creationResult['payment_link']);
exit;

