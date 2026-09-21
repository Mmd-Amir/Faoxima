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
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    variza_webhook_respond(400, 'empty body');
}

$sigHeader = (string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '');
if ($sigHeader === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        if (strtolower((string) $k) === 'x-webhook-signature') {
            $sigHeader = (string) $v;
            break;
        }
    }
}

$secretRow = select('PaySetting', 'ValuePay', 'NamePay', 'variza_webhook_secret', 'select');
$secret = is_array($secretRow) ? trim((string) ($secretRow['ValuePay'] ?? '')) : '';
if ($secret === '' || $secret === '0') {
    variza_webhook_log('VARIZA_NOT_CONFIGURED', 'webhook_secret not configured');
    variza_webhook_respond(500, 'not configured');
}

$provided = $sigHeader;
if (str_starts_with($provided, 'sha256=')) {
    $provided = substr($provided, 7);
}

$expected = hash_hmac('sha256', $raw, $secret);
if ($provided === '' || !hash_equals($expected, $provided)) {
    variza_webhook_log('VARIZA_BAD_SIG', 'signature mismatch', [
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    variza_webhook_respond(401, 'invalid signature');
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    variza_webhook_respond(400, 'invalid json');
}

if (($data['event'] ?? '') !== 'payment.paid') {
    variza_webhook_respond(200, 'ignored');
}

$slug = (string) ($data['slug'] ?? '');
$amount = (int) ($data['amount'] ?? 0);
// attempt_code available as $data['attempt_code'] for audit if needed.

if ($slug === '') {
    variza_webhook_respond(400, 'missing slug');
}

// Variza stores slug in dec_not_confirmed (like nowpayment invoice_id).
$payment = select("Payment_report", "*", "dec_not_confirmed", $slug, "select");
if (!$payment) {
    // Fallback: some installs may have slug in id_invoice; try there too.
    $payment = select("Payment_report", "*", "id_invoice", $slug, "select");
}
if (!$payment) {
    variza_webhook_log('VARIZA_UNKNOWN_SLUG', 'no Payment_report matched slug', ['slug' => $slug]);
    variza_webhook_respond(404, 'order not found');
}

// Already settled — idempotent. Variza retries up to 5 times.
if (($payment['payment_Status'] ?? '') === 'paid') {
    variza_webhook_respond(200, 'already paid');
}

// Amount check — both in Toman. Variza reports final_amount which is the base
// amount plus a small random identifier, so it is always >= what was billed.
$billed = (int) ($payment['price'] ?? 0);
if ($amount < $billed) {
    variza_webhook_log('VARIZA_AMOUNT_MISMATCH', 'gateway reported less than billed', [
        'slug' => $slug,
        'billed' => $billed,
        'got' => $amount,
    ]);
    variza_webhook_respond(400, 'amount mismatch');
}

$orderId = (string) $payment['id_order'];

try {
    $result = payment_confirm_paid($orderId, 'chashbackvariza', [
        'method' => 'واریزا 💳',
        'extra_lines' => [
            '🔗 شناسه پرداخت واریزا: ' . $slug,
        ],
    ]);
} catch (Throwable $e) {
    variza_webhook_log('VARIZA_CONFIRM_FAILED', 'payment_confirm_paid threw', [
        'order_id' => $orderId,
        'error' => $e->getMessage(),
    ]);
    variza_webhook_respond(500, 'delivery failed');
}

if (empty($result['ok'])) {
    variza_webhook_respond(200, (string) ($result['reason'] ?? 'noop'));
}

variza_webhook_respond(200, 'ok');
