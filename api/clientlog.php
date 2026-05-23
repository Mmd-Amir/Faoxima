<?php


declare(strict_types=1);


header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$_allowed_origins = defined('APP_ORIGIN') ? [APP_ORIGIN] : [];
$_req_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($_req_origin) && in_array($_req_origin, $_allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $_req_origin);
    header('Vary: Origin');
}


header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'POST only']);
    exit;
}


$logFile = __DIR__ . '/../logs/client-' . date('Y-m-d') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}


$raw = file_get_contents('php://input', false, null, 0, 16384);
$raw = $raw === false ? '' : $raw;


$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = $logDir . '/client-rate.json';
$now = time();
$hits = [];
if (is_file($rateFile)) {
    $raw2 = @file_get_contents($rateFile);
    if (is_string($raw2)) {
        $decoded = json_decode($raw2, true);
        if (is_array($decoded)) $hits = $decoded;
    }
}

$ipHits = $hits[$ip] ?? [];
$ipHits = array_filter($ipHits, function ($t) use ($now) { return $t > $now - 60; });
if (count($ipHits) >= 30) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'msg' => 'rate limited']);
    exit;
}
$ipHits[] = $now;
$hits[$ip] = $ipHits;
@file_put_contents($rateFile, json_encode($hits), LOCK_EX);


$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = ['raw' => substr($raw, 0, 4000)];
}

$entry = [
    'ts'    => date('Y-m-d H:i:s'),
    'ip'    => $ip,
    'ua'    => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400),
    'ref'   => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 400),
    'level' => isset($payload['level']) ? (string)$payload['level'] : 'error',
    'msg'   => isset($payload['msg']) ? substr((string)$payload['msg'], 0, 1000) : '',
    'where' => isset($payload['where']) ? substr((string)$payload['where'], 0, 400) : '',
    'stack' => isset($payload['stack']) ? substr((string)$payload['stack'], 0, 4000) : '',
    'diag'  => $payload['diag'] ?? null,
    'extra' => $payload['extra'] ?? null,
];

$line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($line === false) {
    $line = '{"ts":"' . date('Y-m-d H:i:s') . '","msg":"<unencodable>"}';
}

$ok = @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
if ($ok === false) {
    error_log('[faoxima-clientlog fallback] ' . $line);
}

echo json_encode(['ok' => true]);

