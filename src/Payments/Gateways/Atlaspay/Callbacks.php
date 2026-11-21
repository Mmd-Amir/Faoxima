<?php

namespace Faoxima\Payments\Gateways\Atlaspay;

use ManagePanel;
use Throwable;

final class Callbacks
{
    public static function atlaspay_log_event($type, $message, array $context = [])
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    $line = $type . ': ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log('[atlaspay] ' . $line);
}

    public static function atlaspay_process_webhook()
{
    global $pdo, $ManagePanel, $setting, $textbotlang, $datatextbot;

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }

    $rawBody = (string) file_get_contents('php://input');
    $signature = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) ? (string) $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] : '';
    if (!is_callable([\Faoxima\Payments\Gateways\Atlaspay\CustomClient::class, 'verifyWebhookSignature']) || !\Faoxima\Payments\Gateways\Atlaspay\CustomClient::verifyWebhookSignature($rawBody, $signature)) {
        self::atlaspay_log_event('ATLASPAY_BAD_SIGNATURE', 'Webhook signature verification failed', [
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        http_response_code(401);
        exit('Unauthorized');
    }

    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        http_response_code(400);
        exit('Invalid body');
    }

    $remoteId = isset($data['orderId']) && is_scalar($data['orderId']) ? trim((string) $data['orderId']) : '';
    $orderRef = isset($data['merchantOrderRef']) && is_scalar($data['merchantOrderRef']) ? trim((string) $data['merchantOrderRef']) : '';
    if ($remoteId === '' || !ctype_digit($remoteId) || !preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $orderRef)) {
        http_response_code(400);
        exit('Invalid order reference');
    }

    $report = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($pdo, $orderRef);
    if ($report === null || (string) ($report['atlaspay_order_id'] ?? '') !== $remoteId) {
        http_response_code(200);
        exit('Ignored');
    }

    http_response_code(200);
    echo 'OK';
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    if ((string) ($report['payment_Status'] ?? '') === 'paid' && (int) ($report['direct_payment_done'] ?? 0) === 1) {
        return;
    }

    $ManagePanel = new ManagePanel();
    $setting = \Faoxima\Core\Database\Db::select("setting", "*");
    $datatextbotget = \Faoxima\Core\Database\Db::select("textbot", "*", null, null, "fetchAll");
    $datatextbot = [
        'textafterpay' => '',
        'textaftertext' => '',
        'textmanual' => '',
        'textselectlocation' => '',
        'text_wgdashboard' => '',
    ];
    if (is_array($datatextbotget)) {
        foreach ($datatextbotget as $row) {
            if (array_key_exists($row['id_text'], $datatextbot) || (is_string($row['text']) && trim($row['text']) !== '')) {
                $datatextbot[$row['id_text']] = $row['text'];
            }
        }
    }
    $textbotlang = \Faoxima\Telegram\Conversation\ConversationData::languagechange((dirname(__DIR__, 4) . '/payment') . '/../text.json');

    try {
        \Faoxima\Payments\Gateways\Atlaspay\Settlement::pollOrder($pdo, $setting, $report, true, false);
    } catch (Throwable $e) {
        self::atlaspay_log_event('ATLASPAY_WEBHOOK_POLL_FAILED', $e->getMessage(), ['order_id' => $orderRef]);
    }
}
}
