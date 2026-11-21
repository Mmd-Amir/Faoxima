<?php

declare(strict_types=1);

namespace Faoxima\Api\Endpoints;

/**
 * Handler stage. Inherited by value: none.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Uiactionlog
{
    public static function handle(
        
    ) {
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


        $logFile = (dirname(__DIR__, 3) . '/api') . '/../logs/ui-actions-' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }


        $raw = file_get_contents('php://input', false, null, 0, 16384);
        $raw = $raw === false ? '' : $raw;


        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rateFile = $logDir . '/ui-actions-rate.json';
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
        if (count($ipHits) >= 120) {
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
            'ts'     => date('Y-m-d H:i:s'),
            'ip'     => $ip,
            'ua'     => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400),
            'action' => isset($payload['action']) ? substr((string)$payload['action'], 0, 100) : '',
            'phase'  => isset($payload['phase']) ? substr((string)$payload['phase'], 0, 50) : '',
            'href'   => isset($payload['href']) ? substr((string)$payload['href'], 0, 400) : '',
            'extra'  => $payload['extra'] ?? null,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = '{"ts":"' . date('Y-m-d H:i:s') . '","msg":"<unencodable ui-action entry>"}';
        }

        $ok = @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($ok === false) {
            error_log('[faoxima-uiactionlog fallback] ' . $line);
        }

        echo json_encode(['ok' => true]);

        return 1;
    }
}
