<?php

namespace Faoxima\Payments\Gateways\Abangateway;

final class Client
{
    public static function endpoint()
    {
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "urlabangateway", "select");
        $url = is_array($row) ? rtrim(trim((string) ($row['ValuePay'] ?? '')), '/') : '';
        // https only: the key rides in a header on every call.
        if ($url === '' || stripos($url, 'https://') !== 0 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        return $url;
        }

    public static function apiKey()
    {
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apiabangateway", "select");
        $key = is_array($row) ? trim((string) ($row['ValuePay'] ?? '')) : '';
        return ($key === '0') ? '' : $key;
        }

    public static function isReady()
    {
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "statusabangateway", "select");
        $status = is_array($row) ? (string) ($row['ValuePay'] ?? '') : '';
        return $status === 'onabangateway' && self::endpoint() !== null && self::apiKey() !== '';
        }

    public static function payUrlFor($authority)
    {
        $authority = trim((string) $authority);
        $endpoint = self::endpoint();
        if ($endpoint === null || !preg_match('/^abn_([A-Za-z0-9_]{6,64})$/', $authority, $match)) {
            return null;
        }
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $origin = 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $origin . '/pay/' . $match[1];
        }

    public static function request($path, array $payload)
    {
        $endpoint = self::endpoint();
        $apiKey = self::apiKey();
        if ($endpoint === null || $apiKey === '') {
            return [
                'ok' => false,
                'status_code' => 0,
                'data' => null,
                'message' => 'آدرس درگاه یا کلید اتصال آبان گیت وی تنظیم نشده است',
            ];
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $response = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) (curl_getinfo($curl, CURLINFO_HTTP_CODE) ?? 0);
        curl_close($curl);

        if ($response === false) {
            error_log('AbanGateway request failed: ' . json_encode([
                'path' => $path,
                'error' => $curlError,
                'errno' => $curlErrno,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'ok' => false,
                'status_code' => 0,
                'data' => null,
                'message' => 'خطا در ارتباط با آبان گیت وی',
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            error_log('AbanGateway invalid response: ' . json_encode([
                'path' => $path,
                'status_code' => $statusCode,
                'raw_response' => mb_substr((string) $response, 0, 500),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'ok' => false,
                'status_code' => $statusCode,
                'data' => null,
                'message' => 'پاسخ نامعتبر از آبان گیت وی',
            ];
        }

        // A refusal says why in `message`; a rejected key says it in `error`.
        $message = (string) ($decoded['message'] ?? ($decoded['error'] ?? ''));
        return [
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'data' => $decoded,
            'message' => $message,
        ];
        }

    public static function createPayment($runtimeDomainhosts, $order_id, $amount_toman, $user_id = null)
    {
        $domainhosts = $runtimeDomainhosts;

        $payload = [
            // Toman, like every other gateway here; AbanGateway converts once.
            'amount' => (int) $amount_toman,
            'order_id' => (string) $order_id,
            'callback_url' => 'https://' . $domainhosts . '/payment/abangateway.php?order=' . rawurlencode((string) $order_id),
        ];
        if ($user_id !== null && $user_id !== '') {
            $payload['user_id'] = (string) $user_id;
        }

        $answer = self::request('/create', $payload);
        $data = is_array($answer['data']) ? $answer['data'] : [];

        if (!$answer['ok'] || empty($data['success'])) {
            return [
                'success' => false,
                'message' => $answer['message'] !== '' ? $answer['message'] : 'ساخت لینک پرداخت در آبان گیت وی ناموفق بود',
                'status_code' => $answer['status_code'],
            ];
        }

        $authority = trim((string) ($data['authority'] ?? ''));
        $payUrl = trim((string) ($data['payment_link'] ?? ''));
        if ($authority === '' || stripos($payUrl, 'https://') !== 0) {
            error_log('AbanGateway missing authority/payment_link: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'success' => false,
                'message' => 'پاسخ نامعتبر از آبان گیت وی',
            ];
        }

        return [
            'success' => true,
            'authority' => $authority,
            'pay_url' => $payUrl,
            'card_in_bot' => !empty($data['card_in_bot']),
            'card' => is_array($data['card'] ?? null)
                ? $data['card']
                : [],
            'payable_rial' => (int) ($data['payable_rial'] ?? 0),
            'payable_toman' => (int) ($data['payable_toman'] ?? 0),
            'minutes_left' => (int) ($data['minutes_left'] ?? 0),
        ];
        }

    public static function verifyPayment($authority, $order_id, $billed_toman)
    {
        $answer = self::request('/verify', [
            'authority' => (string) $authority,
            'order_id' => (string) $order_id,
        ]);
        $data = is_array($answer['data']) ? $answer['data'] : [];

        $result = [
            'paid' => false,
            'reachable' => $answer['status_code'] > 0,
            'status_code' => $answer['status_code'],
            'message' => $answer['message'],
            'amount_rial' => (int) ($data['amount'] ?? 0),
        ];
        if (!$answer['ok'] || empty($data['success'])) {
            return $result;
        }
        if ((string) ($data['order_id'] ?? '') !== (string) $order_id) {
            $result['message'] = 'order mismatch';
            return $result;
        }
        if ($result['amount_rial'] < ((int) $billed_toman) * 10) {
            $result['message'] = 'amount mismatch';
            return $result;
        }
        $result['paid'] = true;
        return $result;
        }
}
