<?php


declare(strict_types=1);

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Response.php';

if (class_exists('FaoximaAuth')) {
    return;
}

final class FaoximaAuth
{


    public static function validateInitData($rawData, string $botToken): array
    {
        if (is_string($rawData)) {
            $rawData = trim($rawData);
            if ($rawData === '') {
                throw new InvalidArgumentException('Telegram init data is missing or invalid');
            }
            $decoded = html_entity_decode($rawData, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            parse_str($decoded, $initData);
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

        $checkArr = [];
        foreach ($initData as $key => $value) {
            if ($value === null) continue;
            $strValue = self::normalize($value);
            if ($strValue === '') continue;
            $checkArr[] = $key . '=' . $strValue;
        }
        if ($checkArr === []) {
            throw new InvalidArgumentException('Telegram init data payload is empty');
        }
        sort($checkArr, SORT_STRING);
        $checkString = implode("\n", $checkArr);

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calcHash = hash_hmac('sha256', $checkString, $secretKey);

        if (!hash_equals($calcHash, $receivedHash)) {
            throw new RuntimeException('User verification failed');
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
        $row = select('user', '*', 'token', $token, 'select');
        return is_array($row) ? $row : null;
    }


    public static function issueToken(int $userId): string
    {
        try {
            $token = bin2hex(random_bytes(20));
        } catch (Throwable $e) {
            FaoximaLogger::exception($e, 'Failed to generate session token');
            throw new RuntimeException('Failed to generate session token');
        }
        update('user', 'token', $token, 'id', $userId);
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

