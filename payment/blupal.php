<?php
ini_set('error_log', 'error_log');
ignore_user_abort(true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../lib/PaymentConfirm.php';

function blupal_log_event($type, $message, array $context = [])
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    $line = $type . ': ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log('[blupal] ' . $line);
}

function blupal_process_webhook()
{
    global $connect, $ManagePanel;

    $ManagePanel = new ManagePanel();

    $rawBody = file_get_contents("php://input");
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        blupal_log_event('BLUPAL_BAD_BODY', 'Callback body was not valid JSON', [
            'remote_ip' => $remoteIp,
            'body_excerpt' => substr((string) $rawBody, 0, 200),
        ]);
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid payload']));
    }

    if (($data['event'] ?? '') !== 'payment.completed') {
        blupal_log_event('BLUPAL_UNKNOWN_EVENT', 'Callback event is not payment.completed', [
            'remote_ip' => $remoteIp,
            'event' => $data['event'] ?? null,
        ]);
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid payload']));
    }

    $invoiceId = $data['invoice_id'] ?? null;
    if ($invoiceId === null || (!is_string($invoiceId) && !is_numeric($invoiceId))) {
        blupal_log_event('BLUPAL_NO_ID', 'Callback missing invoice_id', [
            'remote_ip' => $remoteIp,
            'keys' => array_keys($data),
        ]);
        http_response_code(400);
        exit(json_encode(['error' => 'Missing invoice id']));
    }
    $invoiceId = (string) $invoiceId;

    $lookupStmt = $connect->prepare("SELECT * FROM Payment_report WHERE blupal_invoice_id = ? LIMIT 1");
    $lookupStmt->bind_param('s', $invoiceId);
    $lookupStmt->execute();
    $Payment_report = $lookupStmt->get_result()->fetch_assoc();
    $lookupStmt->close();
    if (!is_array($Payment_report)) {
        blupal_log_event('BLUPAL_UNKNOWN_INVOICE', 'Payment_report row not found for invoice_id', [
            'invoice_id' => $invoiceId,
            'remote_ip' => $remoteIp,
        ]);
        http_response_code(404);
        exit(json_encode(['error' => 'Order not found']));
    }

    $orderId = (string) $Payment_report['id_order'];

    if ($Payment_report['payment_Status'] == "expire" || $Payment_report['payment_Status'] == "cancelled") {
        http_response_code(200);
        exit(json_encode(['received' => true]));
    }
    if ($Payment_report['payment_Status'] == "paid") {
        http_response_code(200);
        exit(json_encode(['received' => true]));
    }

    $check = function_exists('blupalCheckInvoice') ? blupalCheckInvoice($invoiceId) : null;
    if (!is_array($check) || (int) ($check['invoice_id'] ?? 0) !== (int) $invoiceId) {
        blupal_log_event('BLUPAL_CHECK_MISMATCH', 'Server-side check did not confirm this invoice', [
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'check' => $check,
        ]);
        http_response_code(409);
        exit(json_encode(['error' => 'Verification failed']));
    }
    if (empty($check['success'])) {
        http_response_code(200);
        exit(json_encode(['received' => true]));
    }

    $blupalStatus = (string) ($check['status'] ?? '');
    if (in_array($blupalStatus, ['EXPIRED', 'CANCELED'], true)) {
        payment_mark_expired($orderId);
        http_response_code(200);
        exit(json_encode(['received' => true]));
    }
    if ($blupalStatus !== 'PAID') {
        http_response_code(200);
        exit(json_encode(['received' => true]));
    }

    $expectedRial = blupalTomanToRial($Payment_report['price']);
    $checkAmountRial = isset($check['amount']) ? (int) $check['amount'] : null;
    if ($checkAmountRial === null || $checkAmountRial !== $expectedRial) {
        blupal_log_event('BLUPAL_AMOUNT_MISMATCH', 'Verified amount does not match stored order amount', [
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'expected_rial' => $expectedRial,
            'received_rial' => $checkAmountRial,
        ]);
        http_response_code(409);
        exit(json_encode(['error' => 'Amount mismatch']));
    }

    $finalAmountRial = isset($check['final_amount']) ? (float) $check['final_amount'] : null;
    if ($finalAmountRial === null) {
        blupal_log_event('BLUPAL_AMOUNT_MISSING', 'Check response lacks final_amount', [
            'order_id' => $orderId,
        ]);
        http_response_code(400);
        exit(json_encode(['error' => 'Amount missing']));
    }
    $creditAmountToman = blupalRialToToman($finalAmountRial);

    $payerName = trim((string) ($check['payer_name'] ?? ''));
    $payerCard = trim((string) ($check['payer_card'] ?? ''));
    $payerBank = trim((string) ($check['payer_bank_name'] ?? ''));

    $paymentReportsThreadId = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'] ?? null;

    $extra = [
        'method'      => 'blupal',
        'thread_id'   => $paymentReportsThreadId,
        'extra_lines' => array_filter([
            $creditAmountToman > 0 ? ('💸 مبلغ نهایی تأییدشده توسط بلوپال : ' . number_format($creditAmountToman) . ' تومان') : '',
            $payerName !== '' ? ('👤 نام واریزکننده : ' . $payerName) : '',
            $payerCard !== '' ? ('💳 شماره کارت/شبا واریزکننده : ' . $payerCard) : '',
            $payerBank !== '' ? ('🏦 بانک واریزکننده : ' . $payerBank) : '',
        ]),
    ];

    $result = payment_confirm_paid($orderId, 'chashbackblupal', $extra);
    if (empty($result['ok'])) {
        blupal_log_event('BLUPAL_CONFIRM_SKIPPED', 'payment_confirm_paid did not apply (duplicate or race)', [
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'reason' => $result['reason'] ?? null,
        ]);
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
}

blupal_process_webhook();
