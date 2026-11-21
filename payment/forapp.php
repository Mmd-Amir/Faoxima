<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';

ini_set('error_log', 'error_log');
ini_set('display_errors', '0');



if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    \Faoxima\Payments\Receipts\ForappCallbacks::forapp_respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

if (!is_callable([\Faoxima\Payments\Receipts\DepositMatching::class, 'ingest'])) {
    \Faoxima\Payments\Receipts\ForappCallbacks::forapp_respond(503, ['ok' => false, 'error' => 'unavailable']);
}

$raw = file_get_contents('php://input', false, null, 0, RX_SBV_MAX_BODY + 1);
if (!is_string($raw) || $raw === '') {
    \Faoxima\Payments\Receipts\ForappCallbacks::forapp_respond(400, ['ok' => false, 'error' => 'empty_body']);
}

[$code, $body] = \Faoxima\Payments\Receipts\DepositMatching::ingest($pdo, \Faoxima\Payments\Receipts\DepositMatching::header('X-API-Key'), \Faoxima\Payments\Receipts\DepositMatching::header('Idempotency-Key'), $raw);

if ($code === 200 && isset($body['id']) && is_numeric($body['id'])) {
    $rxSbvDepositId = (int) $body['id'];
    try {
        $rxSbvDeposit = \Faoxima\Payments\Receipts\ReceiptOrders::deposit($pdo, $rxSbvDepositId);
    } catch (Throwable $e) {
        \Faoxima\Payments\Receipts\Settings::log('SBV_WEBHOOK_LOOKUP_FAILED', $e->getMessage(), ['deposit_id' => $rxSbvDepositId]);
        $body['ok'] = false;
        $body['retryable'] = true;
        $body['payment'] = ['status' => 'error', 'deposit_id' => $rxSbvDepositId];
        \Faoxima\Payments\Receipts\ForappCallbacks::forapp_respond(503, $body);
    }
    $rxSbvState = is_array($rxSbvDeposit) ? (string) ($rxSbvDeposit['state'] ?? '') : '';
    if ($rxSbvState === 'pending' || $rxSbvState === 'matched') {
        try {
            if (!class_exists('ManagePanel', false) && is_file(__DIR__ . '/../panels.php')) {
                require_once __DIR__ . '/../panels.php';
            }
            if (is_file(__DIR__ . '/../keyboard.php')) {
                require_once __DIR__ . '/../keyboard.php';
            }
            if (is_file(__DIR__ . '/../jdf.php')) {
                require_once __DIR__ . '/../jdf.php';
            }
            if (is_file(__DIR__ . '/../vendor/autoload.php')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            }
            if (class_exists('ManagePanel', false)) {
                $ManagePanel = new ManagePanel();
            }
            if (method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'rxEnsurePaymentRuntime')) {
                \Faoxima\Payments\Gateways\GatewayRuntime::rxEnsurePaymentRuntime();
            }
            if (method_exists(\Faoxima\Payments\Gateways\Tronado\Runtime::class, 'rxPaymentV2PrepareContext')) {
                \Faoxima\Payments\Gateways\Tronado\Runtime::rxPaymentV2PrepareContext();
            }
        } catch (Throwable $e) {
            if (is_callable([\Faoxima\Payments\Receipts\Settings::class, 'log'])) {
                \Faoxima\Payments\Receipts\Settings::log('SBV_RUNTIME_INIT_FAILED', $e->getMessage(), ['deposit_id' => $rxSbvDepositId]);
            }
            $body['ok'] = false;
            $body['retryable'] = true;
            $body['payment'] = ['status' => 'error', 'deposit_id' => $rxSbvDepositId];
            \Faoxima\Payments\Receipts\ForappCallbacks::forapp_respond(503, $body);
        }
    }
    if (is_callable([\Faoxima\Payments\Receipts\Recovery::class, 'processDeposit'])) {
        try {
            $body['payment'] = \Faoxima\Payments\Receipts\Recovery::processDeposit($pdo, $rxSbvDepositId, dirname(__DIR__) . '/images.jpg');
        } catch (Throwable $e) {
            if (is_callable([\Faoxima\Payments\Receipts\Settings::class, 'log'])) {
                \Faoxima\Payments\Receipts\Settings::log('SBV_WEBHOOK_PROCESS_FAILED', $e->getMessage(), ['deposit_id' => $rxSbvDepositId]);
            }
            $body['payment'] = ['status' => 'error', 'deposit_id' => $rxSbvDepositId, 'deposit_state' => $rxSbvState];
        }
    }
    if (!isset($body['payment']) || !in_array((string) ($body['payment']['status'] ?? ''), ['completed', 'already_processed', 'ignored', 'unmatched', 'ambiguous', 'late', 'failed', 'closed', 'resolved'], true)) {
        $code = 503;
        $body['ok'] = false;
        $body['retryable'] = true;
    }
}

\Faoxima\Payments\Receipts\ForappCallbacks::forapp_respond($code, $body);
