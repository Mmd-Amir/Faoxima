<?php

ini_set('display_errors', '0');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';

function noapayWebhookRespond(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    noapayWebhookRespond(405, 'method not allowed');
}
$body = file_get_contents('php://input', false, null, 0, 65537);
if (!is_string($body) || $body === '' || strlen($body) > 65536) {
    noapayWebhookRespond(400, 'invalid body');
}
if (!noapayValidSignature($body, (string) ($_SERVER['HTTP_X_STARBOT_SIGNATURE'] ?? ''), trim(noapaySetting('noapay_webhook_secret')))) {
    noapayWebhookRespond(403, 'invalid signature');
}
$payload = json_decode($body, true);
if (!is_array($payload) || !is_string($payload['event'] ?? null)) {
    noapayWebhookRespond(400, 'invalid payload');
}
$events = [
    'order.receipt_sent' => ['paid', 'receipt_sent'],
    'order.confirmed' => ['confirmed'],
    'order.completed' => ['completed'],
    'order.rejected' => ['rejected'],
    'order.partial_payment' => ['partial_payment'],
];
if (!isset($events[$payload['event']])) {
    noapayWebhookRespond(200, 'ignored');
}
if (!in_array($payload['status'] ?? '', $events[$payload['event']], true)) {
    noapayWebhookRespond(400, 'event status mismatch');
}

require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';
try {
    $result = noapayApplyStatus($payload);
    // A callback can arrive before create's response has been persisted; request a retry.
    noapayWebhookRespond(!empty($result['ok']) ? 200 : (($result['reason'] ?? '') === 'invoice mismatch' ? 400 : 503), $result['reason'] ?? 'retry');
} catch (Throwable $e) {
    error_log('[noapay] Webhook processing failed');
    noapayWebhookRespond(503, 'retry');
}
