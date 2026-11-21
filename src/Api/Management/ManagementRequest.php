<?php

namespace Faoxima\Api\Management;

use Exception;
use Faoxima\Api\Runtime\EndpointSupport;
use FaoximaApiCredential;
use PDO;

final readonly class ManagementRequest
{
    private function __construct(public string $method, public array $data)
    {
    }

    public static function accept(
        PDO $pdo,
        array $headers,
        string $method,
        string $credential,
        ?string $ip,
        callable $readBody,
        callable $sanitize,
        bool $strictAudit = false,
    ): self {
        require_once dirname(__DIR__, 3) . '/lib/ApiCredential.php';
        if (!FaoximaApiCredential::validHeaders($headers, $credential)) {
            EndpointSupport::sendJsonResponse(false, 'token invalid', [], 403);
        }
        $data = json_decode($readBody(), true);
        if (!is_array($data)) {
            EndpointSupport::sendJsonResponse(false, 'data invalid', []);
        }
        $data = $sanitize($data);
        try {
            self::record($pdo, $headers, $data, $ip, $strictAudit);
        } catch (Exception $exception) {
            if ($strictAudit) {
                throw $exception;
            }
            error_log('API logging error: ' . $exception->getMessage());
        }
        return new self($method, $data);
    }

    private static function record(PDO $pdo, array $headers, array $data, ?string $ip, bool $strict): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO logs_api (header, data, time, ip, actions) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            json_encode(FaoximaApiCredential::redactHeaders($headers)),
            json_encode(FaoximaApiCredential::redactAuditFields($data)),
            date('Y/m/d H:i:s'),
            $ip ?? ($strict ? null : 'unknown'),
            $data['actions'] ?? ($strict ? null : 'unknown'),
        ]);
    }
}
