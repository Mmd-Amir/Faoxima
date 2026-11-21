<?php

namespace Faoxima\Payments\Gateways\Blupal;

final class Client
{
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
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'Faoxima-BluPal-Client/1.0',
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
            error_log('BluPal request failed: ' . json_encode([
                'url' => $endpoint,
                'error' => $curlError,
                'errno' => $curlErrno,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            error_log('BluPal invalid response: ' . json_encode([
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
        return trim((string) \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "apiblupal", "select")['ValuePay']);
        }

    public static function tomanToRial($tomanAmount)
    {
        return (int) round(((float) $tomanAmount) * BLUPAL_RIAL_PER_TOMAN);
        }

    public static function rialToToman($rialAmount)
    {
        return (int) round(((float) $rialAmount) / BLUPAL_RIAL_PER_TOMAN);
        }

    public static function reconcileCallbackAmount(array $verified, $invoicePrice): array
    {
        $expected = self::tomanToRial($invoicePrice);
        $received = isset($verified['amount']) ? (int) $verified['amount'] : null;
        if ($received === null || $received !== $expected) {
            return ['status' => 'mismatch', 'expected' => $expected, 'received' => $received];
        }
        if (!isset($verified['final_amount'])) {
            return ['status' => 'missing_final_amount'];
        }
        return ['status' => 'matched', 'credit' => self::rialToToman((float) $verified['final_amount'])];
    }

    public static function createInvoice($runtimeDomainhosts, $order_id, $amountToman)
    {
        $domainhosts = $runtimeDomainhosts;

        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return [
                'success' => false,
                'error' => 'کلید API بلوپال تنظیم نشده است',
            ];
        }

        $requestPayload = [
            'amount' => self::tomanToRial($amountToman),
        ];

        $endpoint = 'https://blupal.net/api/v1/invoices/create';
        $decoded = self::curlJson('POST', $endpoint, $requestPayload, $apiKey);

        if (!is_array($decoded) || empty($decoded['success']) || empty($decoded['invoice_id']) || empty($decoded['payment_link'])) {
            $errorPayload = [
                'success' => false,
                'error' => is_array($decoded) ? ($decoded['message'] ?? ($decoded['error'] ?? 'پاسخ نامعتبر از سرویس بلوپال')) : 'پاسخ نامعتبر از سرویس بلوپال',
                'raw' => $decoded,
            ];
            error_log('BluPal create invoice failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $errorPayload;
        }

        return $decoded;
        }

    public static function checkInvoice($invoice_id)
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }

        $endpoint = 'https://blupal.net/api/v1/invoices/' . rawurlencode((string) $invoice_id);
        return self::curlJson('GET', $endpoint, [], $apiKey);
        }
}
