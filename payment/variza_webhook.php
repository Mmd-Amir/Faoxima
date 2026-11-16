<?php

/**
 * Variza push handler — verifies the HMAC, then settles exactly once.
 *
 * Mirrors payment/nowpayment.php (pure push, no pull/verify round-trip):
 * nothing in the JSON body is trusted. The slug is a handle for looking the
 * invoice up, not proof, so this file checks the signature, checks the slug
 * belongs to the amount it billed, and only then calls payment_confirm_paid().
 *
 * Configure in the Variza panel: profile → webhook →
 * https://{domain}/payment/variza_webhook.php
 */

ini_set('error_log', 'error_log');
ini_set('display_errors', '0');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';

function variza_webhook_respond(int $code, string $msg): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function variza_webhook_log(string $type, string $message, array $context = []): void
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    error_log('[variza] ' . $type . ': ' . $message);
}

// Raw body is what the HMAC is over — never use $_POST here.
// All verification and settlement now lives in VarizaWebhookProvider; this
// file only builds the request and emits the provider's response.
if (!class_exists(\Faoxima\Payment\Variza\VarizaWebhookProvider::class)) {
    // Autoloader not refreshed (missing `composer dump-autoload`). Answer 5xx
    // so Variza retries instead of silently dropping a paid invoice.
    variza_webhook_log('VARIZA_BOOTSTRAP_FAILED', 'VarizaWebhookProvider is not autoloadable');
    variza_webhook_respond(500, 'not configured');
}

$request = \Faoxima\Payment\WebhookRequest::fromGlobals();

$provider = new \Faoxima\Payment\Variza\VarizaWebhookProvider(
    new \Faoxima\Payment\Legacy\LegacySettings(),
    new \Faoxima\Payment\Legacy\LegacyPaymentReports(),
    new \Faoxima\Payment\Legacy\LegacyPaymentSettlement(),
    new \Faoxima\Payment\Legacy\LegacyWebhookLog('variza')
);

try {
    $response = $provider->handleWebhook($request);
} catch (Throwable $e) {
    variza_webhook_log('VARIZA_PROVIDER_FAILED', $e->getMessage());
    variza_webhook_respond(500, 'delivery failed');
}

variza_webhook_respond($response->status, (string) ($response->body['status'] ?? 'error'));
