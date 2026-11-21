<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../src/bootstrap.php';



if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    \Faoxima\Payments\Gateways\Tronado\DisputeCallbacks::tronado_dispute_respond(405, 'Method not allowed');
}

$rawBody = (string) file_get_contents('php://input');
$signingRow = \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "ipnsigningkeytronado", "select");
$signingKey = is_array($signingRow) ? trim((string) ($signingRow['ValuePay'] ?? '')) : '';
if ($signingKey === '') {
    \Faoxima\Payments\Gateways\Tronado\DisputeCallbacks::tronado_dispute_respond(500, 'Signing key not configured');
}
$signatureHeader = isset($_SERVER['HTTP_X_TRONADO_SIG']) ? (string) $_SERVER['HTTP_X_TRONADO_SIG'] : '';
$expectedSignature = hash_hmac('sha512', $rawBody, $signingKey);
if ($signatureHeader === '' || !hash_equals($expectedSignature, strtolower($signatureHeader))) {
    error_log('[tronado-dispute] signature verification failed from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    \Faoxima\Payments\Gateways\Tronado\DisputeCallbacks::tronado_dispute_respond(401, 'Invalid signature');
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    \Faoxima\Payments\Gateways\Tronado\DisputeCallbacks::tronado_dispute_respond(400, 'Invalid body');
}

if (!is_callable([\Faoxima\Payments\Gateways\Tronado\Settlement::class, 'handleDispute'])) {
    \Faoxima\Payments\Gateways\Tronado\DisputeCallbacks::tronado_dispute_respond(500, 'Handler unavailable');
}
$setting = \Faoxima\Core\Database\Db::select("setting", "*");
[$code, $body] = \Faoxima\Payments\Gateways\Tronado\Settlement::handleDispute($pdo, $data);
\Faoxima\Payments\Gateways\Tronado\DisputeCallbacks::tronado_dispute_respond($code, $body);
