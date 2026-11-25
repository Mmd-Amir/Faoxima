<?php

namespace Faoxima\Payments\Gateways\Tetraminator;

use PDO;
use Throwable;

final class Client
{
    public static function logEvent($event, $message, array $ctx = []): void
    {
        if (function_exists('rx_log_event')) {
            try {
                rx_log_event($event, $message, $ctx);
                return;
            } catch (Throwable $e) {
            }
        }
        $line = (string) $event . ': ' . (string) $message;
        if (!empty($ctx)) {
            $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log('[tetraminator] ' . $line);
    }

    private static $loggedOnce = [];

    public static function logEventOnce($event, $message, array $ctx = []): void
    {
        $event = (string) $event;
        if (isset(self::$loggedOnce[$event])) {
            return;
        }
        self::$loggedOnce[$event] = true;
        self::logEvent($event, $message, $ctx);
    }

    public static function setting($runtimePdo, $name, $default = '')
    {
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $row = \Faoxima\Core\Database\Db::select('PaySetting', 'ValuePay', 'NamePay', (string) $name, 'select');
            if (is_array($row)) {
                return (string) ($row['ValuePay'] ?? $default);
            }
            return (string) $default;
        }
        $pdo = $runtimePdo;
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare('SELECT ValuePay FROM PaySetting WHERE NamePay = ? LIMIT 1');
                $stmt->execute([(string) $name]);
                $value = $stmt->fetchColumn();
                return $value === false ? (string) $default : (string) $value;
            } catch (Throwable $e) {
            }
        }
        return (string) $default;
    }

    public static function saveSetting($runtimePdo, $name, $value): bool
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?');
            $exists->execute([(string) $name]);
            if ((int) $exists->fetchColumn() > 0) {
                $stmt = $pdo->prepare('UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?');
            } else {
                $stmt = $pdo->prepare('INSERT INTO PaySetting (ValuePay, NamePay) VALUES (?, ?)');
            }
            $stmt->execute([(string) $value, (string) $name]);
            if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCacheRow')) {
                \Faoxima\Core\Database\SelectCache::clearSelectCacheRow('PaySetting', 'NamePay', (string) $name);
            }
            return true;
        } catch (Throwable $e) {
            self::logEvent('TETRAMINATOR_SETTING_SAVE_FAILED', $e->getMessage(), ['name' => (string) $name]);
            return false;
        }
    }

    public static function apiKey($runtimePdo): string
    {
        return trim(self::setting($runtimePdo, 'apitetraminator', ''));
    }

    public static function enabled($runtimePdo): bool
    {
        return self::setting($runtimePdo, 'statustetraminator', 'offtetraminator') === 'ontetraminator';
    }

    public static function displayName(): string
    {
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_textbot_get')) {
            $value = trim((string) \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('tetraminator', ''));
            if ($value !== '') {
                return $value;
            }
        }
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $row = \Faoxima\Core\Database\Db::select('textbot', 'text', 'id_text', 'tetraminator', 'select');
            $value = is_array($row) ? trim((string) ($row['text'] ?? '')) : '';
            if ($value !== '') {
                return $value;
            }
        }
        return TETRAMINATOR_DISPLAY_NAME;
    }

    public static function maskSecret($secret): string
    {
        $secret = trim((string) $secret);
        $length = strlen($secret);
        if ($length === 0) {
            return '';
        }
        if ($length < 12) {
            return str_repeat('•', 6);
        }
        return substr($secret, 0, 4) . '…' . substr($secret, -4);
    }

    public static function amountRange($runtimePdo): array
    {
        $min = (int) self::setting($runtimePdo, 'minbalancetetraminator', (string) TETRAMINATOR_MIN_AMOUNT);
        $max = (int) self::setting($runtimePdo, 'maxbalancetetraminator', (string) TETRAMINATOR_MAX_AMOUNT);
        if ($min <= 0) {
            $min = TETRAMINATOR_MIN_AMOUNT;
        }
        if ($max <= 0) {
            $max = TETRAMINATOR_MAX_AMOUNT;
        }
        if ($min > $max) {
            $min = TETRAMINATOR_MIN_AMOUNT;
            $max = TETRAMINATOR_MAX_AMOUNT;
        }
        return [$min, $max];
    }

    public static function validPayId($payId): bool
    {
        return is_string($payId) && preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $payId) === 1;
    }

    public static function validCallbackToken($token): bool
    {
        return is_string($token) && preg_match('/^[a-f0-9]{40}$/', $token) === 1;
    }

    public static function newCallbackToken(): string
    {
        return bin2hex(random_bytes(20));
    }

    public static function callbackBaseUrl($runtimeDomainhosts = null): string
    {
        $host = trim((string) ($runtimeDomainhosts ?? ($GLOBALS['domainhosts'] ?? '')));
        if ($host === '' && defined('APP_ORIGIN')) {
            $host = (string) APP_ORIGIN;
        }
        $host = preg_replace('#^https?://#i', '', $host);
        $host = rtrim(trim((string) $host), '/');
        if ($host === '') {
            return '';
        }
        return 'https://' . $host . '/payment/tetraminator.php';
    }

    public static function callbackUrl($runtimeDomainhosts, $callbackToken): string
    {
        $base = self::callbackBaseUrl($runtimeDomainhosts);
        if ($base === '' || !self::validCallbackToken((string) $callbackToken)) {
            return '';
        }
        return $base . '?cb=' . $callbackToken;
    }

    public static function error($code, $message, $statusCode = 0, $retryable = false): array
    {
        return [
            'success' => false,
            'error_code' => (string) $code,
            'error' => (string) $message,
            'status_code' => (int) $statusCode,
            'retryable' => (bool) $retryable,
        ];
    }

    public static function apiRequest($runtimePdo, $method, $path, ?array $payload = null, $attempts = 1, $timeout = 20)
    {
        $apiKey = self::apiKey($runtimePdo);
        if ($apiKey === '') {
            return self::error('MISSING_API_KEY', 'کلید API تترامیناتور تنظیم نشده است', 0, false);
        }
        $method = strtoupper((string) $method);
        $url = TETRAMINATOR_API_BASE_URL . (string) $path;
        $attempts = max(1, min(4, (int) $attempts));
        $timeout = max(3, min(60, (int) $timeout));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $curl = curl_init();
            $opts = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => [
                    'X-API-KEY: ' . $apiKey,
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
            ];
            if ($method === 'POST') {
                $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?? new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            curl_setopt_array($curl, $opts);
            $response = curl_exec($curl);
            $curlErrno = curl_errno($curl);
            $curlError = curl_error($curl);
            $statusCode = (int) (curl_getinfo($curl, CURLINFO_HTTP_CODE) ?: 0);
            curl_close($curl);

            if ($response === false) {
                if ($attempt < $attempts) {
                    usleep(600000 * $attempt);
                    continue;
                }
                self::logEventOnce('TETRAMINATOR_TRANSPORT_ERROR', 'Tetraminator request failed', [
                    'path' => preg_replace('#/payment/inquiry/.+$#', '/payment/inquiry/*', (string) $path),
                    'errno' => (int) $curlErrno,
                    'error' => (string) $curlError,
                    'attempts' => $attempt,
                ]);
                return self::error('TRANSPORT_ERROR', 'ارتباط با تترامیناتور برقرار نشد', 0, true);
            }

            if ($statusCode === 429 || $statusCode >= 500) {
                if ($attempt < $attempts) {
                    usleep(800000 * $attempt);
                    continue;
                }
                return self::error($statusCode === 429 ? 'RATE_LIMIT_EXCEEDED' : 'UPSTREAM_UNAVAILABLE', 'سرویس تترامیناتور موقتاً در دسترس نیست', $statusCode, true);
            }

            $decoded = json_decode((string) $response, true);
            if (!is_array($decoded)) {
                return self::error('BAD_RESPONSE', 'پاسخ نامعتبر از سرویس تترامیناتور', $statusCode, true);
            }
            $decoded['status_code'] = $statusCode;
            if ($statusCode >= 200 && $statusCode < 300) {
                return $decoded;
            }
            $message = '';
            foreach (['message', 'error'] as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key]) && trim((string) $decoded[$key]) !== '') {
                    $message = trim((string) $decoded[$key]);
                    break;
                }
            }
            $code = 'HTTP_' . $statusCode;
            if ($statusCode === 400) {
                $code = 'INVALID_REQUEST';
            } elseif ($statusCode === 401) {
                $code = 'AUTH_FAILED';
                self::logEventOnce('TETRAMINATOR_AUTH_FAILED', 'Tetraminator rejected the request credentials', ['status' => $statusCode]);
            } elseif ($statusCode === 403) {
                $code = 'FORBIDDEN';
                self::logEventOnce('TETRAMINATOR_FORBIDDEN', 'Tetraminator refused the request for this store', ['status' => $statusCode]);
            } elseif ($statusCode === 404) {
                $code = 'NOT_FOUND';
            }
            return self::error($code, $message !== '' ? mb_substr($message, 0, 200) : 'خطا در پاسخ تترامیناتور', $statusCode, false);
        }
        return self::error('UPSTREAM_UNAVAILABLE', 'سرویس تترامیناتور موقتاً در دسترس نیست', 0, true);
    }

    public static function selectPaymentUrl(array $decoded): string
    {
        foreach (['url', 'gateway_link', 'mini_app_link', 'payment_link'] as $key) {
            if (!isset($decoded[$key]) || !is_string($decoded[$key])) {
                continue;
            }
            $candidate = trim($decoded[$key]);
            if ($candidate === '' || strlen($candidate) > 500 || preg_match('/\s/', $candidate) === 1) {
                continue;
            }
            $parts = parse_url($candidate);
            if (is_array($parts)
                && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
                && !empty($parts['host'])
                && !isset($parts['user'], $parts['pass'])
                && filter_var($candidate, FILTER_VALIDATE_URL) !== false) {
                return $candidate;
            }
        }
        return '';
    }

    public static function createInvoice($runtimePdo, $amount, $callbackUrl, $chatId = 0)
    {
        $amount = (int) $amount;
        $callbackUrl = trim((string) $callbackUrl);
        $chatId = (int) $chatId;
        [$min, $max] = self::amountRange($runtimePdo);
        if ($amount <= 0 || $amount < $min || $amount > $max) {
            return self::error('INVALID_AMOUNT', 'مبلغ خارج از محدوده مجاز تترامیناتور است', 0, false);
        }
        if (stripos($callbackUrl, 'https://') !== 0 || filter_var($callbackUrl, FILTER_VALIDATE_URL) === false) {
            return self::error('INVALID_CALLBACK', 'آدرس بازگشت معتبر نیست؛ آدرس نصب باید با https تنظیم شده باشد', 0, false);
        }
        $payload = [
            'price' => $amount,
            'callback_url' => $callbackUrl,
        ];
        if ($chatId > 0) {
            $payload['chat_id'] = $chatId;
        }
        $decoded = self::apiRequest($runtimePdo, 'POST', '/invoice/create', $payload, 2, TETRAMINATOR_CREATE_TIMEOUT_SECONDS);
        if (!is_array($decoded) || (isset($decoded['success']) && $decoded['success'] === false)) {
            return is_array($decoded) ? $decoded : self::error('BAD_RESPONSE', 'پاسخ نامعتبر از سرویس تترامیناتور', 0, true);
        }
        $statusCode = (int) ($decoded['status_code'] ?? 0);
        if (($statusCode !== 201 && $statusCode !== 200) || ($decoded['status'] ?? null) !== true) {
            $message = isset($decoded['message']) && is_scalar($decoded['message']) ? trim((string) $decoded['message']) : '';
            return self::error('HTTP_' . $statusCode, $message !== '' ? mb_substr($message, 0, 200) : 'ساخت فاکتور در تترامیناتور ناموفق بود', $statusCode, false);
        }
        $payId = isset($decoded['pay_id']) && is_scalar($decoded['pay_id']) ? trim((string) $decoded['pay_id']) : '';
        $url = self::selectPaymentUrl($decoded);
        if (!self::validPayId($payId) || $url === '') {
            self::logEvent('TETRAMINATOR_CREATE_BAD_RESPONSE', 'Tetraminator invoice response lacked a usable identifier or link', [
                'has_pay_id' => $payId !== '' ? 'yes' : 'no',
                'has_url' => $url !== '' ? 'yes' : 'no',
            ]);
            return self::error('BAD_RESPONSE', 'پاسخ نامعتبر از سرویس تترامیناتور', $statusCode, true);
        }
        return [
            'success' => true,
            'pay_id' => $payId,
            'url' => $url,
            'amount' => $amount,
            'http_status' => $statusCode,
        ];
    }

    public static function inquire($runtimePdo, $payId, $timeout = TETRAMINATOR_CRON_TIMEOUT_SECONDS, $attempts = 2)
    {
        $payId = trim((string) $payId);
        if (!self::validPayId($payId)) {
            return self::error('INVALID_PAY_ID', 'شناسه فاکتور نامعتبر است', 0, false);
        }
        return self::apiRequest($runtimePdo, 'GET', '/payment/inquiry/' . rawurlencode($payId), null, $attempts, $timeout);
    }
}
