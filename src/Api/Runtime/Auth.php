<?php

declare(strict_types=1);

namespace Faoxima\Api\Runtime;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class Auth
{


    public static function validateInitData($rawData, string $botToken): array
    {
        if (is_string($rawData)) {
            $rawData = trim($rawData);
            if ($rawData === '') {
                throw new InvalidArgumentException('Telegram init data is missing or invalid');
            }
            // Telegram spec: parse the raw initData as a query string. No
            // html_entity_decode — it can corrupt URL-encoded values whose
            // bytes happen to resemble HTML5 named entities.
            parse_str($rawData, $initData);
        } elseif (is_array($rawData)) {
            $initData = $rawData;
        } else {
            throw new InvalidArgumentException('Telegram init data is missing or invalid');
        }

        if (!is_array($initData) || $initData === []) {
            throw new InvalidArgumentException('Telegram init data payload is empty');
        }
        if (!isset($initData['hash'])) {
            throw new InvalidArgumentException('Telegram init data is missing required signature');
        }

        $receivedHash = (string)$initData['hash'];
        unset($initData['hash']);

        // Telegram spec: build data_check_string from EVERY remaining field
        // (including empty ones), sorted by key, joined with \n as `key=value`.
        $checkString = self::signaturePayload($initData, 'Telegram init data payload is empty');

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calcHash = hash_hmac('sha256', $checkString, $secretKey);

        if (!hash_equals($calcHash, $receivedHash)) {
            throw new RuntimeException('User verification failed');
        }

        $authDate = $initData['auth_date'] ?? null;
        if (!is_scalar($authDate) || !ctype_digit((string) $authDate)) {
            throw new RuntimeException('Telegram auth date is missing or invalid');
        }
        $authDate = (int) $authDate;
        $now = time();
        if ($authDate <= 0 || $authDate > $now + 30 || $now - $authDate > 3600) {
            throw new RuntimeException('Telegram init data has expired or has an invalid date');
        }

        $userRaw = $initData['user'] ?? null;
        if (is_string($userRaw)) {
            $userData = json_decode($userRaw, true);
        } elseif (is_array($userRaw)) {
            $userData = $userRaw;
        } else {
            $userData = null;
        }

        if (!is_array($userData) || !isset($userData['id'])) {
            throw new RuntimeException('User data is missing or malformed in init data');
        }

        return $userData;
    }


    public static function validateContactResponse($rawData, string $botToken): array
    {
        if (!is_string($rawData)) {
            throw new InvalidArgumentException('Contact data is missing or invalid');
        }
        $rawData = trim($rawData);
        if ($rawData === '') {
            throw new InvalidArgumentException('Contact data is missing or invalid');
        }

        parse_str($rawData, $data);
        if (!is_array($data) || !isset($data['hash'])) {
            throw new InvalidArgumentException('Contact data is missing required signature');
        }

        $receivedHash = (string)$data['hash'];
        unset($data['hash']);

        $checkString = self::signaturePayload($data, 'Contact data payload is empty');

        $secrets = [
            hash_hmac('sha256', $botToken, 'WebAppData', true),
            $botToken,
        ];
        $valid = false;
        foreach ($secrets as $secret) {
            $calc = hash_hmac('sha256', $checkString, $secret);
            if (hash_equals($calc, $receivedHash)) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            throw new RuntimeException('Contact verification failed');
        }

        if (isset($data['auth_date'])) {
            $authDate = (int)$data['auth_date'];
            if ($authDate > 0 && (time() - $authDate) > 86400) {
                throw new RuntimeException('Contact data has expired');
            }
        }

        $contactRaw = $data['contact'] ?? null;
        if (is_string($contactRaw)) {
            $contact = json_decode($contactRaw, true);
        } elseif (is_array($contactRaw)) {
            $contact = $contactRaw;
        } else {
            $contact = null;
        }
        if (!is_array($contact) || (!isset($contact['user_id']) && !isset($contact['phone_number']))) {
            throw new RuntimeException('Contact payload is missing or malformed');
        }

        return $contact;
    }


    private static function signaturePayload(array $fields, string $emptyMessage): string
    {
        $parts = [];
        foreach ($fields as $key => $value) {
            $parts[] = $key . '=' . self::normalize($value);
        }
        if ($parts === []) {
            throw new InvalidArgumentException($emptyMessage);
        }
        sort($parts, SORT_STRING);
        return implode("\n", $parts);
    }

    public static function isMiniAppAdministrator(int $userId): bool
    {
        $adminIds = \Faoxima\Core\Database\Db::select('admin', 'id_admin', null, null, 'FETCH_COLUMN', ['cache' => false]);
        return is_array($adminIds) && in_array($userId, array_map('intval', $adminIds), true);
    }

    public static function collectInitDataCandidates(): array
    {
        $candidates = [];

        $headers = self::getAllHeadersCompat();
        $headerKeys = ['X-Telegram-Init-Data', 'X-Telegram-Web-App-Init-Data', 'Telegram-Init-Data'];
        foreach ($headerKeys as $hk) {
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, $hk) === 0) {
                    $value = trim((string)$value);
                    if ($value !== '') {
                        $candidates[] = $value;
                    }
                }
            }
        }

        $rawInput = file_get_contents('php://input');
        $rawInput = $rawInput === false ? '' : trim($rawInput);
        $jsonBody = null;
        if ($rawInput !== '') {
            $jsonBody = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonBody = null;
            }
        }

        $candidateSources = [
            $_POST['initData'] ?? null,
            $_POST['init_data'] ?? null,
            $_POST['initDataUnsafe'] ?? null,
            $_GET['initData'] ?? null,
            $_GET['init_data'] ?? null,
        ];

        if (is_array($jsonBody)) {
            $candidateSources[] = $jsonBody;
            $candidateSources[] = $jsonBody['initData'] ?? null;
            $candidateSources[] = $jsonBody['init_data'] ?? null;
            $candidateSources[] = $jsonBody['initDataUnsafe'] ?? null;
        } elseif ($rawInput !== '') {
            $candidateSources[] = $rawInput;
        }

        foreach ($candidateSources as $value) {
            if ($value === null) continue;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') continue;
            }
            $candidates[] = $value;
        }

        return $candidates;
    }


    public static function extractBearerToken(): ?string
    {
        $headers = self::getAllHeadersCompat();
        $auth = null;
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $auth = $v;
                break;
            }
        }
        if ($auth === null) {
            $auth = $_SERVER['HTTP_AUTHORIZATION']
                 ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                 ?? null;
        }
        if (empty($auth)) {
            return null;
        }
        if (!preg_match('/^\s*Bearer\s+(\S.*)$/i', $auth, $m)) {
            return null;
        }
        return trim($m[1]);
    }


    public static function userFromToken(string $token): ?array
    {
        if ($token === '') return null;
        $row = \Faoxima\Core\Database\Db::select('user', '*', 'token', $token, 'select');
        return is_array($row) ? $row : null;
    }


    public static function issueToken(int $userId): string
    {
        try {
            $token = bin2hex(random_bytes(20));
        } catch (Throwable $e) {
            \Faoxima\Api\Runtime\Logger::exception($e, 'Failed to generate session token');
            throw new RuntimeException('Failed to generate session token');
        }
        \Faoxima\Core\Database\Db::update('user', 'token', $token, 'id', $userId);
        return $token;
    }

    private static function getAllHeadersCompat(): array
    {
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            return is_array($h) ? $h : [];
        }
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                $headers[$name] = $v;
            }
        }
        return $headers;
    }

    private static function normalize($value): string
    {
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($value === null) return '';
        return (string)$value;
    }
}

