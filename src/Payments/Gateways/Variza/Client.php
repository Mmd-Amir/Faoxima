<?php

namespace Faoxima\Payments\Gateways\Variza;

final class Client
{
    public static function apiToken()
    {
        return trim((string) \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "apivariza", "select")['ValuePay']);
        }

    public static function createPayment($runtimeDomainhosts, $order_id, $amount_toman)
    {
        $domainhosts = $runtimeDomainhosts;

        $apiToken = self::apiToken();
        if ($apiToken === '' || $apiToken === '0') {
            return [
                'success' => false,
                'message' => 'توکن API واریزا تنظیم نشده است',
            ];
        }

        $payload = [
            'amount' => (int) $amount_toman,
            'return_url' => 'https://' . $domainhosts . '/payment/variza_return.php?order=' . $order_id,
            'title' => 'Faoxima order ' . $order_id,
            'expires_in' => '1h',
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://variza.ir/api/v1/pay',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apiToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $response = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) (curl_getinfo($curl, CURLINFO_HTTP_CODE) ?? 0);
        curl_close($curl);

        if ($response === false) {
            error_log('Variza create payment failed: ' . json_encode([
                'error' => $curlError,
                'errno' => $curlErrno,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'success' => false,
                'message' => 'خطا در ارتباط با سرویس واریزا',
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || $statusCode < 200 || $statusCode >= 300) {
            error_log('Variza invalid response: ' . json_encode([
                'status_code' => $statusCode,
                'raw_response' => $response,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'success' => false,
                'message' => is_array($decoded) ? ($decoded['message'] ?? 'پاسخ نامعتبر از سرویس واریزا') : 'پاسخ نامعتبر از سرویس واریزا',
                'raw' => $decoded,
            ];
        }

        $slug = trim((string) ($decoded['slug'] ?? ''));
        $payUrl = trim((string) ($decoded['pay_url'] ?? ''));
        if ($slug === '' || $payUrl === '') {
            error_log('Variza missing slug/pay_url: ' . json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'success' => false,
                'message' => 'پاسخ نامعتبر از سرویس واریزا',
                'raw' => $decoded,
            ];
        }

        return [
            'success' => true,
            'slug' => $slug,
            'pay_url' => $payUrl,
        ];
        }
}
