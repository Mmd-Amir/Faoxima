<?php

namespace Faoxima\Api\Runtime;

use Exception;
use FaoximaApiCredential;
use Faoxima\Api\Runtime\Response;

final class EndpointSupport
{
    public static function sendJsonResponse($status, $message, $data = [], $httpCode = 200)
    {
        http_response_code($httpCode);
        echo json_encode([
            'status' => $status,
            'msg' => $message,
            'obj' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function sendReport($text, $groupid, $topic_id, $reply_markup = null)
    {
        if (strlen($groupid) > 0) {
            telegram('sendmessage', [
                'chat_id' => $groupid,
                'message_thread_id' => $topic_id,
                'text' => $text,
                'parse_mode' => "HTML",
                'reply_markup' => $reply_markup
            ]);
        }
    }

    public static function validateToken($headers)
    {
        global $APIKEY;
        require_once (dirname((dirname(__DIR__, 3) . '/src/Api/Endpoints'), 3) . '/api') . '/../lib/ApiCredential.php';
        return FaoximaApiCredential::validHeaders($headers, (string) ($APIKEY ?? ''));
    }

    public static function sanitizeRecursive($data)
    {
        if (is_array($data)) {
            return array_map([\Faoxima\Api\Runtime\EndpointSupport::class, 'sanitizeRecursive'], $data);
        }
        return is_string($data) ? htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8') : $data;
    }

    public static function validateMethod($expected, $actual, $responseData = [], $httpCode = 200)
    {
        if (strtoupper($expected) !== strtoupper($actual)) {
            self::sendJsonResponse(false, "method invalid; method must be {$expected}", $responseData, $httpCode);
        }
    }

    public static function logApiRequest($headers, $data, $action)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO logs_api (header, data, time, ip, actions) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                json_encode(FaoximaApiCredential::redactHeaders($headers)),
                json_encode(FaoximaApiCredential::redactAuditFields($data)),
                date('Y/m/d H:i:s'),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $action
            ]);
        } catch (Exception $e) {
            error_log("API logging error: " . $e->getMessage());
        }
    }

    public static function __verify_emit(int $http, array $payload): void
    {
        if (class_exists(\Faoxima\Api\Runtime\Response::class)) {
            \Faoxima\Api\Runtime\Response::emitJson($http, $payload);
        } else {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            if (!headers_sent()) {
                http_response_code($http);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store, max-age=0');
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $GLOBALS['__verify_response_sent'] = true;
    }
}
