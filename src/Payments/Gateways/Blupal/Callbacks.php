<?php

namespace Faoxima\Payments\Gateways\Blupal;

use ManagePanel;

final class Callbacks
{
    public static function blupal_log_event($type, $message, array $context = [])
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

    public static function blupal_process_webhook()
    {
        global $connect, $ManagePanel, $pdo, $setting;

        $ManagePanel = new ManagePanel();

        $rawBody = file_get_contents("php://input");
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            self::blupal_log_event('BLUPAL_BAD_BODY', 'Callback body was not valid JSON', [
                'remote_ip' => $remoteIp,
                'body_excerpt' => substr((string) $rawBody, 0, 200),
            ]);
            http_response_code(400);
            exit(json_encode(['error' => 'Invalid payload']));
        }

        if (($data['event'] ?? '') !== 'payment.completed') {
            self::blupal_log_event('BLUPAL_UNKNOWN_EVENT', 'Callback event is not payment.completed', [
                'remote_ip' => $remoteIp,
                'event' => $data['event'] ?? null,
            ]);
            http_response_code(400);
            exit(json_encode(['error' => 'Invalid payload']));
        }

        $invoiceId = $data['invoice_id'] ?? null;
        if ($invoiceId === null || (!is_string($invoiceId) && !is_numeric($invoiceId))) {
            self::blupal_log_event('BLUPAL_NO_ID', 'Callback missing invoice_id', [
                'remote_ip' => $remoteIp,
                'keys' => array_keys($data),
            ]);
            http_response_code(400);
            exit(json_encode(['error' => 'Missing invoice id']));
        }
        $invoiceId = (string) $invoiceId;

        $lookupStmt = $connect->prepare("SELECT * FROM Payment_report WHERE blupal_invoice_id = ? AND Payment_Method = 'blupal' LIMIT 1");
        $lookupStmt->bind_param('s', $invoiceId);
        $lookupStmt->execute();
        $Payment_report = $lookupStmt->get_result()->fetch_assoc();
        $lookupStmt->close();
        if (!is_array($Payment_report)) {
            self::blupal_log_event('BLUPAL_UNKNOWN_INVOICE', 'Payment_report row not found for invoice_id', [
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
        if ($Payment_report['payment_Status'] == "paid" && (int)($Payment_report['direct_payment_done'] ?? 0) === 1) {
            http_response_code(200);
            exit(json_encode(['received' => true]));
        }

        $check = is_callable([\Faoxima\Payments\Gateways\Blupal\Client::class, 'checkInvoice']) ? \Faoxima\Payments\Gateways\Blupal\Client::checkInvoice($invoiceId) : null;
        if (!is_array($check) || (int) ($check['invoice_id'] ?? 0) !== (int) $invoiceId) {
            self::blupal_log_event('BLUPAL_CHECK_MISMATCH', 'Server-side check did not confirm this invoice', [
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
            \Faoxima\Payments\Settlement\ConfirmationFunctions::payment_mark_expired($orderId);
            http_response_code(200);
            exit(json_encode(['received' => true]));
        }
        if ($blupalStatus !== 'PAID') {
            http_response_code(200);
            exit(json_encode(['received' => true]));
        }

        $amount = \Faoxima\Payments\Gateways\Blupal\Client::reconcileCallbackAmount($check, $Payment_report['price']);
        if ($amount['status'] === 'mismatch') {
            self::blupal_log_event('BLUPAL_AMOUNT_MISMATCH', 'Verified amount does not match stored order amount', [
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'expected_rial' => $amount['expected'],
                'received_rial' => $amount['received'],
            ]);
            http_response_code(409);
            exit(json_encode(['error' => 'Amount mismatch']));
        }

        if ($amount['status'] === 'missing_final_amount') {
            self::blupal_log_event('BLUPAL_AMOUNT_MISSING', 'Check response lacks final_amount', [
                'order_id' => $orderId,
            ]);
            http_response_code(400);
            exit(json_encode(['error' => 'Amount missing']));
        }
        $creditAmountToman = $amount['credit'];

        $payerName = trim((string) ($check['payer_name'] ?? ''));
        $payerCard = trim((string) ($check['payer_card'] ?? ''));
        $payerBank = trim((string) ($check['payer_bank_name'] ?? ''));

        $paymentReportsThreadId = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select")['idreport'] ?? null;

        $extra = [
            'method'      => 'blupal',
            'thread_id'   => $paymentReportsThreadId,
            'extra_lines' => \Faoxima\Payments\Settlement\ConfirmationFunctions::rx_blupal_confirmation_lines($creditAmountToman, $payerName, $payerCard, $payerBank),
        ];

        $result = \Faoxima\Payments\Settlement\Confirmation::confirmPaid($pdo, $setting, $orderId, 'chashbackblupal', $extra);
        if (empty($result['ok'])) {
            self::blupal_log_event('BLUPAL_CONFIRM_SKIPPED', 'payment_confirm_paid did not apply (duplicate or race)', [
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'reason' => $result['reason'] ?? null,
            ]);
        }

        http_response_code(200);
        echo json_encode(['received' => true]);
    }
}
