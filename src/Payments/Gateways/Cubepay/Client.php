<?php

namespace Faoxima\Payments\Gateways\Cubepay;

use Throwable;

final class Client
{
    public static function backfillAuthority($connect, string $orderId, string $authority): void
    {
        $statement = $connect->prepare("UPDATE Payment_report SET cubepay_authority = ?
            WHERE id_order = ? AND (cubepay_authority IS NULL OR cubepay_authority = '')");
        $statement->bind_param('ss', $authority, $orderId);
        $statement->execute();
        $statement->close();
    }

    public static function curlJson($method, $endpoint, array $payload, $apiToken)
    {
        $headers = ['Content-Type: application/json'];
        if ($apiToken !== null && $apiToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiToken;
        }

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
        );
        if ($method === 'POST') {
            if ($multipart) {
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
            error_log('CubePay request failed: ' . json_encode([
                'url' => $endpoint,
                'error' => $curlError,
                'errno' => $curlErrno,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            error_log('CubePay invalid response: ' . json_encode([
                'url' => $endpoint,
                'status_code' => $statusCode,
                'raw_response' => $response,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }

        $decoded['status_code'] = $statusCode;
        return $decoded;
        }

    public static function apiToken()
    {
        return trim((string) \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "apicubepay", "select")['ValuePay']);
        }

    public static function payableAmount($amount_toman)
    {
        $amount = (int) $amount_toman;
        $raw = str_replace([',', '،'], '', (string) \Faoxima\Core\Database\Db::getPaySettingValue('feecubepay', '0'));
        $fee = is_numeric($raw) ? (float) $raw : 0.0;

        if ($fee <= 0 || $amount <= 0) {
            return $amount;
        }

        return $fee <= 100
            ? (int) ceil($amount * (1 + $fee / 100))
            : $amount + (int) round($fee);
        }

    public static function createPayment($runtimeDomainhosts, $order_id, $amount_toman, $customer_user_id = null, $description = '', $inlineInBot = false)
    {
        $domainhosts = $runtimeDomainhosts;

        $apiToken = self::apiToken();
        if ($apiToken === '') {
            return [
                'success' => false,
                'message' => 'توکن API کیوب‌پی تنظیم نشده است',
            ];
        }

        $callbackUrl = 'https://' . $domainhosts . '/payment/cubepay.php';
        $requestPayload = [
            'order_id' => (string) $order_id,
            'price_amount' => self::payableAmount($amount_toman),
            'callback_url' => $callbackUrl,
            'redirect_after_payment' => false,
        ];

        if ($inlineInBot === true) {
            $requestPayload['inline_card'] = true;
        }

        // Forward the buyer ID already supplied by the bot or authenticated API caller.
        $buyerId = (is_int($customer_user_id) || is_string($customer_user_id))
            ? trim((string) $customer_user_id) : '';
        if (preg_match('/\A[1-9][0-9]{0,15}\z/D', $buyerId)) {
            $requestPayload['customer_user_id'] = $buyerId;
            try {
                // The lookup is scoped to the same buyer. Missing username is normal.
                $buyerRow = \Faoxima\Core\Database\Db::select('user', 'username', 'id', $buyerId, 'select');
                $buyerUsernameValue = is_array($buyerRow) ? ($buyerRow['username'] ?? null) : $buyerRow;
                $buyerUsername = is_string($buyerUsernameValue)
                    ? ltrim(trim($buyerUsernameValue), '@') : '';
                if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $buyerUsername)
                    && !in_array(strtolower($buyerUsername), ['none', 'null', 'undefined'], true)) {
                    $requestPayload['customer_username'] = $buyerUsername;
                }
            } catch (Throwable $buyerMetadataError) {
                // Optional display metadata cannot interrupt payment creation.
                // The numeric buyer ID is still sent if the profile lookup fails.
            }
        }

        $endpoint = 'https://cubevps.ir/pay/create-order.php';
        $decoded = self::curlJson('POST', $endpoint, $requestPayload, $apiToken);

        if (!is_array($decoded) || empty($decoded['success']) || empty($decoded['pay_page_url'])) {
            $errorPayload = [
                'success' => false,
                'message' => is_array($decoded) ? ($decoded['message'] ?? 'پاسخ نامعتبر از سرویس کیوب‌پی') : 'پاسخ نامعتبر از سرویس کیوب‌پی',
                'raw' => $decoded,
            ];
            error_log('CubePay create payment failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $errorPayload;
        }

        $method = (string) ($decoded['method'] ?? 'choice');
        $normalized = [
            'success' => true,
            'method' => $method,
            'authority' => (string) ($decoded['authority'] ?? ''),
            'payment_link' => (string) $decoded['pay_page_url'],
        ];

        if (isset($decoded['card']) && is_array($decoded['card'])) {
            $normalized['card'] = $decoded['card'];
        }
        if (array_key_exists('pay_amount_toman', $decoded) && is_scalar($decoded['pay_amount_toman'])) {
            $normalized['pay_amount_toman'] = $decoded['pay_amount_toman'];
        }
        if (array_key_exists('pay_amount_rial', $decoded) && is_scalar($decoded['pay_amount_rial'])) {
            $normalized['pay_amount_rial'] = $decoded['pay_amount_rial'];
        }
        if (array_key_exists('expires_in_minutes', $decoded) && is_scalar($decoded['expires_in_minutes'])) {
            $normalized['expires_in_minutes'] = (int) $decoded['expires_in_minutes'];
        }
        if (array_key_exists('show_card_in_bot', $decoded)) {
            $normalized['show_card_in_bot'] = !empty($decoded['show_card_in_bot']);
        }
        if (isset($decoded['crypto_payment_url']) && is_string($decoded['crypto_payment_url'])) {
            $cryptoUrl = trim($decoded['crypto_payment_url']);
            $cryptoParts = parse_url($cryptoUrl);
            if (
                $cryptoUrl !== ''
                && filter_var($cryptoUrl, FILTER_VALIDATE_URL)
                && is_array($cryptoParts)
                && ($cryptoParts['scheme'] ?? '') === 'https'
                && strtolower($cryptoParts['host'] ?? '') === 'cubevps.ir'
                && !isset($cryptoParts['user'])
                && !isset($cryptoParts['pass'])
                && !isset($cryptoParts['port'])
            ) {
                $normalized['crypto_payment_url'] = $cryptoUrl;
            }
        }

        return $normalized;
        }

    public static function checkOrderStatus($order_id)
    {
        $apiToken = self::apiToken();
        if ($apiToken === '') {
            return null;
        }

        $endpoint = 'https://cubevps.ir/pay/check-order-status.php?order_id=' . rawurlencode((string) $order_id);
        return self::curlJson('GET', $endpoint, [], $apiToken);
        }

    public static function verifyPayment($authority)
    {
        $apiToken = self::apiToken();
        if ($apiToken === '') {
            return null;
        }

        $endpoint = 'https://cubevps.ir/smspay/api/verify-payment.php';
        return self::curlJson('POST', $endpoint, ['authority' => (string) $authority], $apiToken);
        }

    public static function resolveOrderStatus($order_id, $authority = '')
    {
        $orderId = (string) $order_id;
        $authority = trim((string) $authority);

        $check = self::checkOrderStatus($orderId);
        if (is_array($check) && !empty($check['success'])) {
            return $check;
        }

        if ($authority === '') {
            return $check;
        }

        $verify = self::verifyPayment($authority);
        if (!is_array($verify)) {
            return $check;
        }

        return [
            'success' => !empty($verify['success']),
            'method' => 'card',
            'status' => !empty($verify['success']) ? 'verified' : (string) ($verify['message'] ?? 'pending'),
            'order_id' => (string) ($verify['order_id'] ?? $orderId),
            'amount' => $verify['amount'] ?? null,
        ];
        }

    public static function verifyCryptoCallbackSignature($orderId, $status, $amount, $sig)
    {
        $apiToken = self::apiToken();
        if ($apiToken === '') {
            return false;
        }
        $expectedSig = hash_hmac('sha256', $orderId . '|' . $status . '|' . $amount, $apiToken);
        return hash_equals($expectedSig, (string) $sig);
        }
}
