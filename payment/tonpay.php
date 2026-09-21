<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';

function tonpay_log_event($type, $message, array $context = [])
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    $line = $type . ': ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log('[tonpay] ' . $line);
}

function tonpay_process_webhook()
{
    global $connect, $ManagePanel, $textbotlang, $datatextbot;

    $ManagePanel = new ManagePanel();
    $rawBody = file_get_contents("php://input");

    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        tonpay_log_event('TONPAY_BAD_BODY', 'Callback body was not valid JSON', [
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'body_excerpt' => substr((string) $rawBody, 0, 200),
        ]);
        http_response_code(400);
        exit('Invalid body');
    }

    $orderId = $data['order_id'] ?? null;
    if ($orderId === null || (!is_string($orderId) && !is_numeric($orderId))) {
        tonpay_log_event('TONPAY_NO_ID', 'Callback missing order_id', [
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'keys' => array_keys($data),
        ]);
        http_response_code(400);
        exit('Missing order id');
    }
    $orderId = (string) $orderId;
    if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $orderId)) {
        tonpay_log_event('TONPAY_BAD_ID_FORMAT', 'order_id failed format check', [
            'order_id_excerpt' => substr($orderId, 0, 40),
        ]);
        http_response_code(400);
        exit('Invalid order id');
    }

    $invoiceId = $data['invoice_id'] ?? null;
    if ($invoiceId === null || !is_string($invoiceId) || trim($invoiceId) === '') {
        tonpay_log_event('TONPAY_NO_INVOICE_ID', 'Callback missing invoice_id', [
            'order_id' => $orderId,
        ]);
        http_response_code(400);
        exit('Missing invoice id');
    }
    $invoiceId = trim($invoiceId);

    $lookupStmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1");
    $lookupStmt->bind_param('s', $orderId);
    $lookupStmt->execute();
    $Payment_report = $lookupStmt->get_result()->fetch_assoc();
    $lookupStmt->close();
    if (!is_array($Payment_report)) {
        tonpay_log_event('TONPAY_UNKNOWN_ORDER', 'Payment_report row not found', [
            'order_id' => $orderId,
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        http_response_code(404);
        exit('Order not found');
    }
    if ($Payment_report['payment_Status'] == "expire" || $Payment_report['payment_Status'] == "cancelled") {
        return;
    }

    $storedInvoiceId = trim((string) ($Payment_report['tonpay_invoice_id'] ?? ''));
    if ($storedInvoiceId !== '' && $storedInvoiceId !== $invoiceId) {
        tonpay_log_event('TONPAY_INVOICE_MISMATCH', 'invoice_id does not match stored value for this order', [
            'order_id' => $orderId,
            'expected' => $storedInvoiceId,
            'received' => $invoiceId,
        ]);
        http_response_code(409);
        exit('Invoice mismatch');
    }

    if ($Payment_report['payment_Status'] == "paid") {
        exit('Already processed');
    }

    $check = function_exists('tonpayCheckInvoice') ? tonpayCheckInvoice($invoiceId) : null;
    if (!is_array($check) || (string) ($check['order_id'] ?? '') !== $orderId) {
        tonpay_log_event('TONPAY_CHECK_MISMATCH', 'Server-side check did not confirm this order', [
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'check' => $check,
        ]);
        http_response_code(409);
        exit('Verification failed');
    }
    if (empty($check['paid']) || (string) ($check['status'] ?? '') !== 'completed') {
        tonpay_log_event('TONPAY_NOT_PAID', 'Server-side check reports order not paid', [
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'status' => $check['status'] ?? null,
        ]);
        exit('Not paid');
    }

    $finalAmount = isset($check['final_amount']) ? (float) $check['final_amount'] : null;
    if ($finalAmount === null) {
        tonpay_log_event('TONPAY_AMOUNT_MISSING', 'Check response lacks final_amount', [
            'order_id' => $orderId,
        ]);
        http_response_code(400);
        exit('Amount missing');
    }

    $creditAmount = $finalAmount;

    $atomic = $connect->prepare(
        "UPDATE Payment_report SET payment_Status = ? WHERE id_order = ? AND payment_Status NOT IN ('paid', 'cancelled')"
    );
    $statusPaid = 'paid';
    $atomic->bind_param('ss', $statusPaid, $orderId);
    $atomic->execute();
    $affected = $atomic->affected_rows;
    $atomic->close();
    if ($affected < 1) {
        tonpay_log_event('TONPAY_DUPLICATE', 'Duplicate or already-paid callback ignored', [
            'order_id' => $orderId,
        ]);
        exit('Already processed');
    }

    echo "پرداخت با موفقیت انجام شد";

    $setting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM setting"));
    $price = $Payment_report['price'];

    $datatextbotget = select("textbot", "*", null, null, "fetchAll");
    $datatxtbot = array();
    foreach ($datatextbotget as $row) {
        $datatxtbot[] = array(
            'id_text' => $row['id_text'],
            'text' => $row['text']
        );
    }
    $datatextbot = array(
        'textafterpay' => '',
        'textaftertext' => '',
        'textmanual' => '',
        'textselectlocation' => '',
        'text_wgdashboard' => ''
    );
    foreach ($datatxtbot as $item) {
        if (isset($datatextbot[$item['id_text']])) {
            $datatextbot[$item['id_text']] = $item['text'];
        }
    }
    $textbotlang = languagechange('../text.json');

    DirectPayment($orderId, "../images.jpg");

    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbacktonpay", "select")['ValuePay'];
    $balanceLookup = $connect->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
    $balanceLookup->bind_param('s', $Payment_report['id_user']);
    $balanceLookup->execute();
    $Balance_id = $balanceLookup->get_result()->fetch_assoc();
    $balanceLookup->close();
    if (!is_array($Balance_id)) {
        tonpay_log_event('TONPAY_USER_MISSING', 'Linked user row not found after paid update', [
            'order_id' => $orderId,
            'id_user' => $Payment_report['id_user'] ?? null,
        ]);
        $Balance_id = ['id' => $Payment_report['id_user'] ?? '', 'username' => '—', 'Balance' => 0];
    }
    $cashbackEligible = !function_exists('rx_cashbackEligibleForKey')
        || rx_cashbackEligibleForKey("chashbacktonpay", $Balance_id['register'] ?? null, $Payment_report['id_invoice'] ?? null, $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
    if ($cashbackEligible && $pricecashback != "0") {
        $result = round(($Payment_report['price'] * $pricecashback) / 100);
        $Balance_confrim = intval($Balance_id['Balance']) + $result;
        update("user", "Balance", $Balance_confrim, "id", $Balance_id['id']);
        $pricecashback = number_format($pricecashback);
        $text_report = "🎁 کاربر عزیز مبلغ " . rxFormatToman($result) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }

    $paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
    $usernameEsc = htmlspecialchars((string) $Balance_id['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $userIdEsc = htmlspecialchars((string) $Balance_id['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rlm = "\xE2\x80\x8F";
    $rxFmtPrice = rxFormatToman($price);
    $rxFmtCreditAmount = rxFormatToman($creditAmount);
    $text_reportpayment = "💵 پرداخت جدید
<blockquote>- 👤 نام کاربری کاربر : @{$usernameEsc}</blockquote>
<blockquote>- 👤 آیدی عددی کاربر : {$rlm}<code>{$userIdEsc}</code></blockquote>
<blockquote>- 💰 مبلغ اعتباردهی : {$rxFmtPrice} تومان</blockquote>
<blockquote>- 💸 مبلغ نهایی تأییدشده توسط تون‌پی : {$rxFmtCreditAmount} تومان</blockquote>
<blockquote>- 💳 روش پرداخت : تون‌پی</blockquote>";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_reportpayment,
            'parse_mode' => "HTML"
        ]);
    }
}

tonpay_process_webhook();
