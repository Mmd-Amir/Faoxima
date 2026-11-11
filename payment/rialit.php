<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
}

if (!function_exists('rialitWebhookAllowed')) {
    http_response_code(503);
    echo 'unavailable';
    return;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo 'method not allowed';
    return;
}

$clientIp = rialitClientIp();
if (!rialitWebhookAllowed($clientIp)) {
    rialitLogEvent('RIALIT_WEBHOOK_FORBIDDEN_IP', 'Rialit webhook rejected from an untrusted address', [
        'client_ip' => $clientIp,
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    http_response_code(403);
    echo 'forbidden';
    return;
}

$rawBody = file_get_contents('php://input');
$data = json_decode((string) $rawBody, true);
if (!is_array($data)) {
    rialitLogEvent('RIALIT_WEBHOOK_BAD_BODY', 'Rialit webhook body was not valid JSON', [
        'remote_ip' => $clientIp,
        'body_excerpt' => substr((string) $rawBody, 0, 200),
    ]);
    http_response_code(400);
    echo 'invalid body';
    return;
}

$bodyType = isset($data['type']) && is_scalar($data['type']) ? trim((string) $data['type']) : '';
$headerType = isset($_SERVER['HTTP_X_RIALIT_EVENT']) ? trim((string) $_SERVER['HTTP_X_RIALIT_EVENT']) : '';
if ($bodyType !== '' && $headerType !== '' && $bodyType !== $headerType) {
    rialitLogEvent('RIALIT_WEBHOOK_TYPE_MISMATCH', 'Rialit webhook event header did not match the body', [
        'body_type' => $bodyType,
        'header_type' => $headerType,
    ]);
    http_response_code(400);
    echo 'event mismatch';
    return;
}
$eventType = $bodyType !== '' ? $bodyType : $headerType;

$bodyEventId = isset($data['event_id']) && is_scalar($data['event_id']) ? trim((string) $data['event_id']) : '';
$deliveryId = isset($_SERVER['HTTP_X_RIALIT_DELIVERY']) ? trim((string) $_SERVER['HTTP_X_RIALIT_DELIVERY']) : '';
if ($bodyEventId !== '' && $deliveryId !== '' && $bodyEventId !== $deliveryId) {
    rialitLogEvent('RIALIT_WEBHOOK_DELIVERY_MISMATCH', 'Rialit delivery header did not match the body event id', [
        'body_event_id' => $bodyEventId,
        'delivery' => $deliveryId,
    ]);
    http_response_code(400);
    echo 'delivery mismatch';
    return;
}
$eventId = $deliveryId !== '' ? $deliveryId : $bodyEventId;
if ($eventId === '' || !preg_match('/^[A-Za-z0-9\-_.:]{1,64}$/', $eventId)) {
    rialitLogEvent('RIALIT_WEBHOOK_BAD_EVENT_ID', 'Rialit webhook event id was missing or invalid', [
        'has_delivery' => $deliveryId !== '' ? 'yes' : 'no',
    ]);
    http_response_code(400);
    echo 'invalid event id';
    return;
}

if ($eventType === 'webhook.test') {
    http_response_code(200);
    echo 'ok';
    return;
}

if (!in_array($eventType, ['payment.paid', 'payment.expired', 'payment.cancelled'], true)) {
    rialitLogEvent('RIALIT_WEBHOOK_UNKNOWN_TYPE', 'Rialit webhook used an unsupported event type', [
        'event_type' => $eventType,
        'event_id' => $eventId,
    ]);
    http_response_code(200);
    echo 'ignored';
    return;
}

$dataObj = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
$token = isset($dataObj['id']) && is_scalar($dataObj['id']) ? trim((string) $dataObj['id']) : '';
$orderId = isset($dataObj['order_id']) && is_scalar($dataObj['order_id']) ? trim((string) $dataObj['order_id']) : '';
if ($token === '' || !preg_match('/^[A-Za-z0-9]{1,64}$/', $token) || $orderId === '' || strlen($orderId) > 64) {
    rialitLogEvent('RIALIT_WEBHOOK_BAD_REFERENCE', 'Rialit webhook lacked a usable payment reference', [
        'event_id' => $eventId,
        'event_type' => $eventType,
    ]);
    http_response_code(400);
    echo 'invalid payment reference';
    return;
}

$payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$store = rialitStoreWebhookEvent($eventId, $eventType, $token, $orderId, is_string($payload) ? $payload : '');
if (empty($store['ok'])) {
    rialitLogEvent('RIALIT_WEBHOOK_PERSIST_FAILED', 'Rialit webhook event could not be persisted', [
        'event_id' => $eventId,
        'order_id' => $orderId,
        'reason' => (string) ($store['message'] ?? ''),
    ]);
    http_response_code(500);
    echo 'persist failed';
    return;
}

http_response_code(200);
echo 'ok';
