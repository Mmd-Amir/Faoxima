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

$raw = file_get_contents('php://input', false, null, 0, RX_SBV_MAX_BODY + 1);
if (!is_string($raw) || $raw === '') {
    forapp_respond(400, ['ok' => false, 'error' => 'empty_body']);
}

[$code, $body] = rx_sbv_ingest(rx_sbv_header('X-API-Key'), rx_sbv_header('Idempotency-Key'), $raw);

if ($code === 200 && isset($body['id']) && is_numeric($body['id'])) {
    $rxSbvDepositId = (int) $body['id'];
    try {
        $rxSbvDeposit = rx_sbv_deposit($rxSbvDepositId);
    } catch (Throwable $e) {
        rx_sbv_log('SBV_WEBHOOK_LOOKUP_FAILED', $e->getMessage(), ['deposit_id' => $rxSbvDepositId]);
        $body['ok'] = false;
        $body['retryable'] = true;
        $body['payment'] = ['status' => 'error', 'deposit_id' => $rxSbvDepositId];
        forapp_respond(503, $body);
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
            if (function_exists('rxEnsurePaymentRuntime')) {
                rxEnsurePaymentRuntime();
            }
            if (function_exists('rxPaymentV2PrepareContext')) {
                rxPaymentV2PrepareContext();
            }
        } catch (Throwable $e) {
            if (function_exists('rx_sbv_log')) {
                rx_sbv_log('SBV_RUNTIME_INIT_FAILED', $e->getMessage(), ['deposit_id' => $rxSbvDepositId]);
            }
            $body['ok'] = false;
            $body['retryable'] = true;
            $body['payment'] = ['status' => 'error', 'deposit_id' => $rxSbvDepositId];
            forapp_respond(503, $body);
        }
    }
    if (function_exists('rx_sbv_process_deposit')) {
        try {
            $body['payment'] = rx_sbv_process_deposit($rxSbvDepositId, dirname(__DIR__) . '/images.jpg');
        } catch (Throwable $e) {
            if (function_exists('rx_sbv_log')) {
                rx_sbv_log('SBV_WEBHOOK_PROCESS_FAILED', $e->getMessage(), ['deposit_id' => $rxSbvDepositId]);
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

forapp_respond($code, $body);
