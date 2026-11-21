<?php

namespace Faoxima\Payments\Gateways\Atlaspay;

use PDO;
use Throwable;
use RuntimeException;

final class Client
{
    public static function apiKey()
    {
        return trim((string) \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "apiatlaspay", "select")['ValuePay']);
        }

    public static function curlJson($method, $endpoint, array $payload, $apiKey)
    {
        $headers = ['Content-Type: application/json'];
        if ($apiKey !== null) {
            $headers[] = 'X-API-Key: ' . $apiKey;
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
            $opts[CURLOPT_POSTFIELDS] = empty($payload) ? '{}' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($curl, $opts);

        $response = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $curlInfo = curl_getinfo($curl);
        $statusCode = $curlInfo['http_code'] ?? null;
        curl_close($curl);

        if ($response === false) {
            error_log('AtlasPay request failed: ' . json_encode([
                'url' => $endpoint,
                'error' => $curlError,
                'errno' => $curlErrno,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            error_log('AtlasPay invalid response: ' . json_encode([
                'url' => $endpoint,
                'status_code' => $statusCode,
                'raw_response' => $response,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }

        $decoded['status_code'] = $statusCode;
        return $decoded;
        }

    public static function ensureReceiptSchema($runtimePdo)
    {
        $pdo = $runtimePdo;
        static $ready = false;
        if ($ready) {
            return true;
        }
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $column = $pdo->query("SHOW COLUMNS FROM Payment_report WHERE Field = 'atlaspay_receipt_url'");
            if ($column === false) {
                return false;
            }
            if ($column->fetch(PDO::FETCH_ASSOC) === false) {
                try {
                    if ($pdo->exec("ALTER TABLE Payment_report ADD COLUMN atlaspay_receipt_url VARCHAR(500) NULL") === false) {
                        throw new RuntimeException('AtlasPay receipt column could not be added');
                    }
                } catch (Throwable $e) {
                    $column = $pdo->query("SHOW COLUMNS FROM Payment_report WHERE Field = 'atlaspay_receipt_url'");
                    if ($column === false || $column->fetch(PDO::FETCH_ASSOC) === false) {
                        throw $e;
                    }
                }
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            error_log('AtlasPay receipt schema failed: ' . $e->getMessage());
            return false;
        }
        }

    public static function createOrder($runtimePdo, $order_id, $amount, $telegramId = null)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return [
                'success' => false,
                'error' => 'کلید API اطلس‌پی تنظیم نشده است',
            ];
        }

        if (!self::ensureReceiptSchema($runtimePdo)) {
            return ['success' => false, 'error' => 'آماده‌سازی ذخیره لینک رسید اطلس‌پی ناموفق بود؛ با پشتیبانی فروشگاه تماس بگیرید.'];
        }

        $requestPayload = [
            'merchantOrderRef' => (string) $order_id,
            'baseAmountToman' => (int) $amount,
        ];
        if ($telegramId !== null) {
            $requestPayload['customerTelegramId'] = (int) $telegramId;
        }

        $endpoint = ATLASPAY_BASE_URL . '/orders';
        $decoded = self::curlJson('POST', $endpoint, $requestPayload, $apiKey);

        if (!is_array($decoded) || empty($decoded['orderId']) || empty($decoded['customerStartLink'])) {
            $errorPayload = [
                'success' => false,
                'error' => is_array($decoded) ? ($decoded['message'] ?? 'پاسخ نامعتبر از سرویس اطلس‌پی') : 'پاسخ نامعتبر از سرویس اطلس‌پی',
                'raw' => $decoded,
            ];
            error_log('AtlasPay create order failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $errorPayload;
        }

        return $decoded;
        }

    public static function checkOrder($atlaspayOrderId)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }

        $endpoint = ATLASPAY_BASE_URL . '/orders/' . rawurlencode((string) $atlaspayOrderId);
        return self::curlJson('GET', $endpoint, [], $apiKey);
        }

    public static function verifyOrder($atlaspayOrderId)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }

        $endpoint = ATLASPAY_BASE_URL . '/orders/' . rawurlencode((string) $atlaspayOrderId) . '/verify';
        return self::curlJson('POST', $endpoint, [], $apiKey);
        }

    public static function cancelOrder($atlaspayOrderId)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }

        $endpoint = ATLASPAY_BASE_URL . '/orders/' . rawurlencode((string) $atlaspayOrderId) . '/cancel';
        return self::curlJson('POST', $endpoint, [], $apiKey);
        }

    public static function balance()
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }

        return self::curlJson('GET', ATLASPAY_BASE_URL . '/balance', [], $apiKey);
        }

    public static function account()
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }

        return self::curlJson('GET', ATLASPAY_BASE_URL . '/account', [], $apiKey);
        }
}
