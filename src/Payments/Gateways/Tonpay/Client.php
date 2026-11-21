<?php

namespace Faoxima\Payments\Gateways\Tonpay;

use PDO;
use Throwable;

final class Client
{
    public static function parseRetryAfter($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 60;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $ts = strtotime($value);
        return $ts === false ? 60 : max(1, $ts - time());
        }

    public static function errorCode($decoded)
    {
        $detail = is_array($decoded) ? ($decoded['detail'] ?? null) : null;
        if (is_array($detail) && isset($detail['code']) && is_scalar($detail['code'])) {
            return trim((string) $detail['code']);
        }
        return '';
        }

    public static function normalizeError($decoded, $fallbackMessage)
    {
        $statusCode = is_array($decoded) ? (int) ($decoded['status_code'] ?? 0) : 0;
        $detail = is_array($decoded) ? ($decoded['detail'] ?? null) : null;
        $code = self::errorCode($decoded);
        $message = '';
        if (is_array($detail)) {
            if (isset($detail['message']) && is_scalar($detail['message'])) {
                $message = trim((string) $detail['message']);
            } elseif (isset($detail[0]['msg']) && is_scalar($detail[0]['msg'])) {
                $message = trim((string) $detail[0]['msg']);
            }
        } elseif (is_scalar($detail)) {
            $message = trim((string) $detail);
        }
        if ($code === '' && $statusCode === 429) {
            $code = 'RATE_LIMIT_EXCEEDED';
        }
        return [
            'success' => false,
            'error_code' => $code,
            'error' => $message !== '' ? $message : (string) $fallbackMessage,
            'status_code' => $statusCode,
            'rate_limited' => $code === 'RATE_LIMIT_EXCEEDED',
        ];
        }

    public static function redactUrl($url)
    {
        $url = (string) $url;
        $cut = strcspn($url, '?#');
        return $cut < strlen($url) ? substr($url, 0, $cut) . '?[redacted]' : $url;
        }

    public static function maskSecret($secret)
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

    public static function normalizeBuyerChatId($buyerChatId)
    {
        if (is_int($buyerChatId)) {
            return $buyerChatId > 0 ? $buyerChatId : null;
        }
        if (is_string($buyerChatId)) {
            $buyerChatId = trim($buyerChatId);
            if ($buyerChatId !== '' && strlen($buyerChatId) <= 15 && ctype_digit($buyerChatId) && (int) $buyerChatId > 0) {
                return (int) $buyerChatId;
            }
        }
        return null;
        }

    public static function validPaymentUrl($url)
    {
        if (!is_string($url)) {
            return '';
        }
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : '';
        }

    public static function paymentMode()
    {
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "tonpay_payment_mode", "select");
        $mode = is_array($row) ? strtolower(trim((string) ($row['ValuePay'] ?? ''))) : '';
        if ($mode === 'web' || $mode === 'custom') {
            return $mode;
        }
        return 'bot';
        }

    public static function selectPaymentUrl($botUrl, $webUrl, $logOrderId = null)
    {
        $botUrl = is_scalar($botUrl) ? trim((string) $botUrl) : '';
        $webUrl = self::validPaymentUrl($webUrl);
        if (self::paymentMode() !== 'web') {
            return $botUrl;
        }
        if ($webUrl !== '') {
            return $webUrl;
        }
        if ($logOrderId !== null) {
            error_log('TonPay web payment url missing, falling back to bot url: ' . json_encode([
                'order_id' => (string) $logOrderId,
                'has_bot_url' => $botUrl !== '',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return $botUrl;
        }

    public static function storeWebInvoiceUrl($runtimePdo, $orderId, $webUrl)
    {
        $pdo = $runtimePdo;
        $webUrl = self::validPaymentUrl($webUrl);
        if ($webUrl === '' || !($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("UPDATE Payment_report SET tonpay_web_invoice_url = ? WHERE id_order = ?");
            $stmt->execute([$webUrl, (string) $orderId]);
            return true;
        } catch (Throwable $e) {
            error_log('TonPay store web invoice url failed: ' . json_encode([
                'order_id' => (string) $orderId,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        }
        }

    public static function amountsMatch($requestAmount, $expectedPrice)
    {
        if (!is_numeric($requestAmount) || !is_numeric($expectedPrice)) {
            return false;
        }
        $received = (float) $requestAmount;
        $expected = (float) $expectedPrice;
        if ($received != floor($received) || $expected != floor($expected)) {
            return false;
        }
        return (int) $received === (int) $expected;
        }

    public static function logAmountMismatch($source, $orderId, $invoiceId, $expectedPrice, $requestAmount)
    {
        error_log('TonPay amount mismatch: ' . json_encode([
            'source' => (string) $source,
            'order_id' => (string) $orderId,
            'invoice_id' => (string) $invoiceId,
            'expected_amount' => is_scalar($expectedPrice) ? (string) $expectedPrice : null,
            'received_request_amount' => is_scalar($requestAmount) ? (string) $requestAmount : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

    public static function terminalLocalStatus($remoteStatus)
    {
        $map = [
            'expired' => 'expire',
            'canceled' => 'cancelled',
            'cancelled' => 'cancelled',
            'rejected' => 'reject',
        ];
        $remoteStatus = strtolower(trim(is_scalar($remoteStatus) ? (string) $remoteStatus : ''));
        return $map[$remoteStatus] ?? null;
        }

    public static function syncTerminalStatus($runtimePdo, $orderId, $remoteStatus)
    {
        $pdo = $runtimePdo;
        $localStatus = self::terminalLocalStatus($remoteStatus);
        if ($localStatus === null || !($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                SET payment_Status = ?
              WHERE id_order = ?
                AND Payment_Method = 'tonpay'
                AND payment_Status IN ('Unpaid','pending','waiting')"
            );
            $stmt->execute([$localStatus, (string) $orderId]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('TonPay terminal status sync failed: ' . json_encode([
                'order_id' => (string) $orderId,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        }
        }

    public static function curlJson($method, $endpoint, array $payload, $apiKey, $multipart = false, $bodyless = false)
    {
        if (!\Faoxima\Payments\Gateways\Tonpay\RateLimit::rateLimitAcquire()) {
            error_log('TonPay request skipped by local rate limiter: ' . json_encode([
                'url' => $endpoint,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'detail' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'سقف درخواست‌های تون‌پی موقتاً پر شده است؛ لطفاً کمی بعد دوباره تلاش کنید',
                ],
                'status_code' => 429,
                'rate_limited' => true,
                'local_rate_limited' => true,
            ];
        }

        $headers = ($multipart || $bodyless) ? [] : ['Content-Type: application/json'];
        if ($apiKey !== null) {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        $retryAfter = '';
        $curl = curl_init();
        $opts = array(
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) use (&$retryAfter) {
                if (stripos($headerLine, 'Retry-After:') === 0) {
                    $retryAfter = trim(substr($headerLine, strlen('Retry-After:')));
                }
                return strlen($headerLine);
            },
        );
        if ($method === 'POST') {
            if ($bodyless) {
                unset($opts[CURLOPT_POSTFIELDS]);
            } elseif ($multipart) {
                $opts[CURLOPT_POSTFIELDS] = $payload;
            } else {
                $opts[CURLOPT_POSTFIELDS] = empty($payload) ? '{}' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        curl_setopt_array($curl, $opts);

        $response = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $curlInfo = curl_getinfo($curl);
        $statusCode = $curlInfo['http_code'] ?? null;
        curl_close($curl);

        if ($response === false) {
            error_log('TonPay request failed: ' . json_encode([
                'url' => $endpoint,
                'error' => $curlError,
                'errno' => $curlErrno,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }

        $decoded = json_decode($response, true);
        if ((int) $statusCode === 429 || self::errorCode($decoded) === 'RATE_LIMIT_EXCEEDED') {
            \Faoxima\Payments\Gateways\Tonpay\RateLimit::rateLimitBlock(self::parseRetryAfter($retryAfter));
            error_log('TonPay rate limit response: ' . json_encode([
                'url' => $endpoint,
                'status_code' => $statusCode,
                'retry_after' => $retryAfter,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (!is_array($decoded)) {
                $decoded = ['detail' => ['code' => 'RATE_LIMIT_EXCEEDED', 'message' => 'Rate limit exceeded']];
            }
            $decoded['rate_limited'] = true;
        }
        if (!is_array($decoded)) {
            error_log('TonPay invalid response: ' . json_encode([
                'url' => $endpoint,
                'status_code' => $statusCode,
                'raw_response' => $response,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }

        $decoded['status_code'] = $statusCode;
        return $decoded;
        }

    public static function apiKey()
    {
        return trim((string) \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "apitonpay", "select")['ValuePay']);
        }

    public static function createInvoice($runtimeDomainhosts, $order_id, $amount, $buyer_chat_id = null)
    {
        $domainhosts = $runtimeDomainhosts;

        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return [
                'success' => false,
                'error' => 'کلید API تون‌پی تنظیم نشده است',
                'error_code' => 'MISSING_API_KEY',
                'status_code' => 0,
            ];
        }

        $callbackUrl = 'https://' . $domainhosts . '/payment/tonpay.php';
        $requestPayload = [
            'amount' => (int) $amount,
            'order_id' => (string) $order_id,
            'callback_url' => $callbackUrl,
        ];
        $buyerChatId = self::normalizeBuyerChatId($buyer_chat_id);
        if ($buyerChatId !== null) {
            $requestPayload['buyer_chat_id'] = $buyerChatId;
        }

        $endpoint = 'https://tonpays.online/api/v1/invoices/create';
        $decoded = self::curlJson('POST', $endpoint, $requestPayload, $apiKey);

        $invoiceId = is_array($decoded) && is_scalar($decoded['invoice_id'] ?? null) ? trim((string) $decoded['invoice_id']) : '';
        $invoiceUrl = is_array($decoded) && is_scalar($decoded['invoice_url'] ?? null) ? trim((string) $decoded['invoice_url']) : '';
        if ($invoiceId === '' || $invoiceUrl === '') {
            $errorPayload = self::normalizeError($decoded, 'پاسخ نامعتبر از سرویس تون‌پی');
            error_log('TonPay create invoice failed: ' . json_encode([
                'order_id' => (string) $order_id,
                'status_code' => $errorPayload['status_code'],
                'error_code' => $errorPayload['error_code'],
                'error' => $errorPayload['error'],
                'buyer_chat_id_sent' => $buyerChatId !== null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $errorPayload;
        }

        $decoded['invoice_id'] = $invoiceId;
        $decoded['invoice_url'] = $invoiceUrl;
        $decoded['web_invoice_url'] = self::validPaymentUrl($decoded['web_invoice_url'] ?? null);
        return $decoded;
        }

    public static function checkInvoice($invoice_id)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }

        $endpoint = 'https://tonpays.online/api/v1/invoices/check/' . rawurlencode((string) $invoice_id);
        return self::curlJson('GET', $endpoint, [], $apiKey);
        }
}
