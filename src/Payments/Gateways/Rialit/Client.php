<?php

namespace Faoxima\Payments\Gateways\Rialit;

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
        error_log('[rialit] ' . $line);
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
            self::logEvent('RIALIT_SETTING_SAVE_FAILED', $e->getMessage(), ['name' => (string) $name]);
            return false;
        }
    }

    public static function apiKey($runtimePdo): string
    {
        return trim(self::setting($runtimePdo, 'apirialit', ''));
    }

    public static function enabled($runtimePdo): bool
    {
        return self::setting($runtimePdo, 'statusrialit', 'offrialit') === 'onrialit';
    }

    public static function displayName(): string
    {
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_textbot_get')) {
            $value = trim((string) \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('rialit', ''));
            if ($value !== '') {
                return $value;
            }
        }
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $row = \Faoxima\Core\Database\Db::select('textbot', 'text', 'id_text', 'rialit', 'select');
            $value = is_array($row) ? trim((string) ($row['text'] ?? '')) : '';
            if ($value !== '') {
                return $value;
            }
        }
        return RIALIT_DISPLAY_NAME;
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
        $min = (int) self::setting($runtimePdo, 'minbalancerialit', (string) RIALIT_MIN_AMOUNT);
        $max = (int) self::setting($runtimePdo, 'maxbalancerialit', (string) RIALIT_MAX_AMOUNT);
        if ($min < RIALIT_MIN_AMOUNT) {
            $min = RIALIT_MIN_AMOUNT;
        }
        if ($max <= 0 || $max > RIALIT_MAX_AMOUNT) {
            $max = RIALIT_MAX_AMOUNT;
        }
        if ($max < RIALIT_MIN_AMOUNT) {
            $max = RIALIT_MAX_AMOUNT;
        }
        if ($min > $max) {
            $min = RIALIT_MIN_AMOUNT;
            $max = RIALIT_MAX_AMOUNT;
        }
        return [$min, $max];
    }

    public static function paymentUrl($token): string
    {
        $token = trim((string) $token);
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
            return '';
        }
        return RIALIT_PAY_BASE_URL . $token;
    }

    public static function error($code, $message, $statusCode = 0, $retryable = false): array
    {
        return [
            'success' => false,
            'error_code' => (string) $code,
            'error' => (string) $message,
            'status_code' => (int) $statusCode,
            'retryable' => (bool) $retryable,
            'rate_limited' => (string) $code === 'RATE_LIMIT_EXCEEDED',
        ];
    }

    public static function apiRequest($runtimePdo, $method, $path, ?array $payload = null, $attempts = 1, $bucket = 'read')
    {
        $apiKey = self::apiKey($runtimePdo);
        if ($apiKey === '') {
            return self::error('MISSING_API_KEY', 'کلید API ریالیت تنظیم نشده است', 0, false);
        }
        $method = strtoupper((string) $method);
        $url = RIALIT_API_BASE_URL . (string) $path;
        $attempts = max(1, min(5, (int) $attempts));
        $bucket = (string) $bucket;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if (!\Faoxima\Payments\Gateways\Rialit\RateLimit::rateLimitAcquire($bucket)) {
                return self::error('RATE_LIMIT_EXCEEDED', 'سقف درخواست‌های ریالیت موقتاً پر است', 429, true);
            }
            $headers = [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ];
            $curl = curl_init();
            $opts = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ];
            if ($method === 'POST') {
                $opts[CURLOPT_POSTFIELDS] = $payload === null
                    ? '{}'
                    : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            curl_setopt_array($curl, $opts);
            $response = curl_exec($curl);
            $curlErrno = curl_errno($curl);
            $curlError = curl_error($curl);
            $statusCode = (int) (curl_getinfo($curl, CURLINFO_HTTP_CODE) ?: 0);
            curl_close($curl);

            if ($response === false) {
                self::logEvent('RIALIT_TRANSPORT_ERROR', 'Rialit request failed', [
                    'path' => (string) parse_url($url, PHP_URL_PATH),
                    'errno' => (int) $curlErrno,
                    'error' => (string) $curlError,
                    'attempt' => $attempt,
                ]);
                if ($attempt < $attempts) {
                    usleep(1000000 * $attempt);
                    continue;
                }
                return self::error('TRANSPORT_ERROR', 'ارتباط با ریالیت برقرار نشد', 0, true);
            }

            $decoded = json_decode((string) $response, true);
            if ($statusCode === 429) {
                \Faoxima\Payments\Gateways\Rialit\RateLimit::rateLimitBlock($bucket, 1);
                if ($attempt < $attempts) {
                    usleep(1000000);
                    continue;
                }
                return self::error('RATE_LIMIT_EXCEEDED', 'سقف درخواست‌های ریالیت موقتاً پر است', 429, true);
            }
            if ($statusCode === 502 || $statusCode === 503 || $statusCode >= 500) {
                if ($attempt < $attempts) {
                    usleep(1000000 * $attempt);
                    continue;
                }
                return self::error('UPSTREAM_UNAVAILABLE', 'سرویس ریالیت موقتاً در دسترس نیست', $statusCode, true);
            }
            if (!is_array($decoded)) {
                return self::error('BAD_RESPONSE', 'پاسخ نامعتبر از سرویس ریالیت', $statusCode, false);
            }
            $decoded['status_code'] = $statusCode;
            if ($statusCode >= 200 && $statusCode < 300) {
                return $decoded;
            }
            $message = isset($decoded['error']) && is_scalar($decoded['error']) ? trim((string) $decoded['error']) : '';
            $code = 'HTTP_' . $statusCode;
            if ($statusCode === 400) {
                $code = 'INVALID_REQUEST';
            } elseif ($statusCode === 401) {
                $code = 'AUTH_FAILED';
                self::logEvent('RIALIT_AUTH_FAILED', 'Rialit rejected the configured API key', [
                    'path' => (string) parse_url($url, PHP_URL_PATH),
                ]);
            } elseif ($statusCode === 403) {
                $code = 'FORBIDDEN';
            } elseif ($statusCode === 404) {
                $code = 'NOT_FOUND';
            } elseif ($statusCode === 409) {
                $code = 'CONFLICT';
            }
            return self::error($code, $message !== '' ? $message : 'خطا در پاسخ ریالیت', $statusCode, false);
        }
        return self::error('UPSTREAM_UNAVAILABLE', 'سرویس ریالیت موقتاً در دسترس نیست', 0, true);
    }

    public static function normalizeCreatedPayment(array $decoded)
    {
        $token = isset($decoded['token']) && is_scalar($decoded['token']) ? trim((string) $decoded['token']) : '';
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
            return null;
        }
        if (!isset($decoded['amount']) || !is_numeric($decoded['amount'])) {
            return null;
        }
        $amount = (int) $decoded['amount'];
        $status = isset($decoded['status']) && is_scalar($decoded['status']) ? strtolower(trim((string) $decoded['status'])) : 'pending';
        $payerAmount = isset($decoded['payer_amount']) && is_numeric($decoded['payer_amount']) ? (int) $decoded['payer_amount'] : $amount;
        $payerFee = isset($decoded['payer_fee']) && is_numeric($decoded['payer_fee']) ? (int) $decoded['payer_fee'] : max(0, $payerAmount - $amount);
        return [
            'success' => true,
            'token' => $token,
            'status' => $status,
            'amount' => $amount,
            'payer_amount' => $payerAmount,
            'payer_fee' => $payerFee,
            'http_status' => (int) ($decoded['status_code'] ?? 0),
        ];
    }

    public static function createPayment($runtimePdo, $orderId, $amount, $payerId)
    {
        $orderId = trim((string) $orderId);
        $amount = (int) $amount;
        $payerId = (int) $payerId;
        if ($orderId === '' || strlen($orderId) > 64) {
            return self::error('INVALID_ORDER_ID', 'شناسه سفارش نامعتبر است', 0, false);
        }
        if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $orderId)) {
            return self::error('INVALID_ORDER_ID', 'شناسه سفارش نامعتبر است', 0, false);
        }
        if ($amount < RIALIT_MIN_AMOUNT || $amount > RIALIT_MAX_AMOUNT) {
            return self::error('INVALID_AMOUNT', 'مبلغ باید بین ۵۰,۰۰۰ و ۱۰,۰۰۰,۰۰۰ تومان باشد', 400, false);
        }
        if ($payerId <= 0) {
            return self::error('INVALID_PAYER', 'شناسه تلگرام خریدار نامعتبر است', 0, false);
        }
        $payload = [
            'amount' => $amount,
            'order_id' => $orderId,
            'payer_id' => $payerId,
        ];
        $decoded = self::apiRequest($runtimePdo, 'POST', '/send', $payload, RIALIT_CREATE_ATTEMPTS, 'send');
        if (!is_array($decoded) || (isset($decoded['success']) && $decoded['success'] === false)) {
            return is_array($decoded) ? $decoded : self::error('BAD_RESPONSE', 'پاسخ نامعتبر از سرویس ریالیت', 0, true);
        }
        $statusCode = (int) ($decoded['status_code'] ?? 0);
        if ($statusCode !== 200 && $statusCode !== 201) {
            $message = isset($decoded['error']) && is_scalar($decoded['error']) ? trim((string) $decoded['error']) : '';
            return self::error('HTTP_' . $statusCode, $message !== '' ? $message : 'ساخت پرداخت در ریالیت ناموفق بود', $statusCode, false);
        }
        $payment = self::normalizeCreatedPayment($decoded);
        if ($payment === null) {
            return self::error('BAD_RESPONSE', 'پاسخ نامعتبر از سرویس ریالیت', $statusCode, true);
        }
        if ($payment['amount'] !== $amount) {
            self::logEvent('RIALIT_CREATE_AMOUNT_MISMATCH', 'Rialit created payment amount does not match the local order', [
                'order_id' => $orderId,
                'requested' => $amount,
                'returned' => $payment['amount'],
            ]);
            return self::error('AMOUNT_MISMATCH', 'مبلغ پرداخت با سفارش مطابقت ندارد', $statusCode, false);
        }
        return $payment;
    }

    public static function checkPayment($runtimePdo, $token)
    {
        $token = trim((string) $token);
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
            return self::error('INVALID_TOKEN', 'شناسه پرداخت نامعتبر است', 0, false);
        }
        return self::apiRequest($runtimePdo, 'GET', '/status/' . rawurlencode($token), [], RIALIT_STATUS_ATTEMPTS, 'read');
    }

    public static function readWebhook($runtimePdo): array
    {
        $decoded = self::apiRequest($runtimePdo, 'GET', '/webhook', [], 2, 'read');
        if (!is_array($decoded) || (isset($decoded['success']) && $decoded['success'] === false)) {
            $message = is_array($decoded) && isset($decoded['error']) ? (string) $decoded['error'] : 'خواندن وب‌هوک ریالیت ناموفق بود';
            self::saveWebhookState($runtimePdo, '', false, $message, 'read');
            return ['ok' => false, 'message' => $message, 'webhook_url' => ''];
        }
        $statusCode = (int) ($decoded['status_code'] ?? 0);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = isset($decoded['error']) && is_scalar($decoded['error']) ? (string) $decoded['error'] : 'خواندن وب‌هوک ریالیت ناموفق بود';
            self::saveWebhookState($runtimePdo, '', false, $message, 'read');
            return ['ok' => false, 'message' => $message, 'webhook_url' => ''];
        }
        $url = '';
        if (array_key_exists('webhook_url', $decoded)) {
            $url = is_scalar($decoded['webhook_url']) ? trim((string) $decoded['webhook_url']) : '';
        }
        self::saveWebhookState($runtimePdo, $url, true, $url === '' ? 'وب‌هوک روی ریالیت تنظیم نشده است' : 'وب‌هوک ریالیت خوانده شد', 'read');
        return ['ok' => true, 'message' => $url === '' ? 'وب‌هوک روی ریالیت تنظیم نشده است' : 'وب‌هوک ریالیت خوانده شد', 'webhook_url' => $url];
    }

    public static function registerWebhook($runtimeDomainhosts, $runtimePdo, $url = null): array
    {
        $url = $url === null ? \Faoxima\Payments\Gateways\Rialit\Access::webhookUrl($runtimeDomainhosts) : trim((string) $url);
        if ($url === '' || stripos($url, 'https://') !== 0 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $message = 'آدرس وب‌هوک نامعتبر است؛ آدرس نصب باید با https تنظیم شده باشد';
            self::saveWebhookState($runtimePdo, $url, false, $message, 'register');
            return ['ok' => false, 'message' => $message, 'webhook_url' => $url];
        }
        $decoded = self::apiRequest($runtimePdo, 'POST', '/webhook', ['webhook_url' => $url], 1, 'webhook_change');
        if (!is_array($decoded) || (isset($decoded['success']) && $decoded['success'] === false)) {
            $message = is_array($decoded) && isset($decoded['error']) ? (string) $decoded['error'] : 'ثبت وب‌هوک ریالیت ناموفق بود';
            self::saveWebhookState($runtimePdo, $url, false, $message, 'register');
            return ['ok' => false, 'message' => $message, 'webhook_url' => $url];
        }
        $statusCode = (int) ($decoded['status_code'] ?? 0);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = isset($decoded['error']) && is_scalar($decoded['error']) ? (string) $decoded['error'] : 'ثبت وب‌هوک ریالیت ناموفق بود';
            self::saveWebhookState($runtimePdo, $url, false, $message, 'register');
            return ['ok' => false, 'message' => $message, 'webhook_url' => $url];
        }
        $saved = '';
        if (array_key_exists('webhook_url', $decoded)) {
            $saved = is_scalar($decoded['webhook_url']) ? trim((string) $decoded['webhook_url']) : '';
        }
        $effective = $saved !== '' ? $saved : $url;
        self::saveWebhookState($runtimePdo, $effective, true, 'وب‌هوک ریالیت با موفقیت ثبت شد', 'register');
        return ['ok' => true, 'message' => 'وب‌هوک ریالیت با موفقیت ثبت شد', 'webhook_url' => $effective];
    }

    public static function saveWebhookState($runtimePdo, $url, $ok, $message, $action): void
    {
        self::saveSetting($runtimePdo, 'rialit_webhook_url', (string) $url);
        self::saveSetting($runtimePdo, 'rialit_webhook_status', $ok ? 'ok' : 'error');
        self::saveSetting($runtimePdo, 'rialit_webhook_checked_at', (string) time());
        self::saveSetting($runtimePdo, 'rialit_webhook_last_action', (string) $action);
        self::saveSetting($runtimePdo, 'rialit_webhook_last_message', mb_substr((string) $message, 0, 240));
    }

    public static function webhookStatusSummary($runtimeDomainhosts, $runtimePdo): array
    {
        return [
            'generated_url' => \Faoxima\Payments\Gateways\Rialit\Access::webhookUrl($runtimeDomainhosts),
            'remote_url' => trim(self::setting($runtimePdo, 'rialit_webhook_url', '')),
            'status' => trim(self::setting($runtimePdo, 'rialit_webhook_status', 'not_configured')),
            'checked_at' => (int) self::setting($runtimePdo, 'rialit_webhook_checked_at', '0'),
            'last_action' => trim(self::setting($runtimePdo, 'rialit_webhook_last_action', '')),
            'last_message' => trim(self::setting($runtimePdo, 'rialit_webhook_last_message', '')),
        ];
    }
}
