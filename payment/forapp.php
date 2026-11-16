<?php

ini_set('error_log', 'error_log');
ini_set('display_errors', '0');

function forapp_respond(int $code, array $body): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($code === 503) {
        header('Retry-After: 30');
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    forapp_respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

if (!function_exists('rx_sbv_ingest')) {
    forapp_respond(503, ['ok' => false, 'error' => 'unavailable']);
}

if (!class_exists(\Faoxima\Payment\Sbv\SbvWebhookProvider::class)) {
    // Autoloader not refreshed (missing `composer dump-autoload`).
    forapp_respond(503, ['ok' => false, 'error' => 'unavailable']);
}

// Oversized bodies are truncated (as before) so the ingest layer rejects them
// instead of the process exhausting memory.
$maxBody = defined('RX_SBV_MAX_BODY') ? (int) RX_SBV_MAX_BODY : 1048576;
if ($maxBody <= 0) {
    $maxBody = 1048576;
}

$raw = file_get_contents('php://input', false, null, 0, $maxBody + 1);
if (!is_string($raw) || $raw === '') {
    forapp_respond(400, ['ok' => false, 'error' => 'empty_body']);
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    }
}

$request = new \Faoxima\Payment\WebhookRequest(
    $raw,
    $headers,
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'POST'),
    (string) ($_SERVER['REMOTE_ADDR'] ?? '')
);

$provider = new \Faoxima\Payment\Sbv\SbvWebhookProvider(
    new \Faoxima\Payment\Legacy\LegacySbvGateway(),
    new \Faoxima\Payment\Legacy\LegacySbvRuntime(dirname(__DIR__)),
    new \Faoxima\Payment\Legacy\LegacyWebhookLog('sbv'),
    dirname(__DIR__) . '/images.jpg'
);

try {
    $response = $provider->handleWebhook($request);
} catch (Throwable $e) {
    if (function_exists('rx_sbv_log')) {
        rx_sbv_log('SBV_WEBHOOK_FATAL', $e->getMessage());
    }
    forapp_respond(503, ['ok' => false, 'error' => 'error', 'retryable' => true]);
}

forapp_respond($response->status, $response->body);
