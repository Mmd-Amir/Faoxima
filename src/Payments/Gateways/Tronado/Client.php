<?php

namespace Faoxima\Payments\Gateways\Tronado;

final class Client
{
    public static function extractPaymentToken($payment)
    {
        if (!is_array($payment)) {
            return '';
        }
        $candidate = $payment['Token'] ?? ($payment['Data']['Token'] ?? '');
        return trim((string) $candidate);
        }

    public static function normalizeOrderTokenResponse($decoded)
    {
        if (!is_array($decoded)) {
            return $decoded;
        }
        if (array_key_exists('Token', $decoded)) {
            return $decoded;
        }
        if (isset($decoded['Data']) && is_array($decoded['Data'])) {
            $normalized = $decoded['Data'];
            if (!array_key_exists('ErrorMessage', $normalized) && isset($decoded['Message'])) {
                $normalized['ErrorMessage'] = $decoded['Message'];
            }
            return $normalized;
        }

        // A rejected GetOrderToken comes back as
        //   {"IsSuccessful":false,"Code":-31,"Message":"Please specify your Tron Wallet Address.","Data":null}
        // Data is null there, so isset() skips the branch above and the reason was dropped: the
        // seller only ever saw the generic "پاسخ نامعتبر از سرویس ترونادو" with nothing to act on.
        if (!array_key_exists('ErrorMessage', $decoded)
            && isset($decoded['Message'])
            && trim((string) $decoded['Message']) !== '') {
            $decoded['ErrorMessage'] = (string) $decoded['Message'];
        }

        return $decoded;
        }

    public static function isValidTronAddress($address)
    {
        if (!is_string($address)) {
            return false;
        }
        $address = trim($address);
        if (strlen($address) !== 34 || $address[0] !== 'T') {
            return false;
        }

        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $bytes = [];
        for ($i = 0; $i < 34; $i++) {
            $carry = strpos($alphabet, $address[$i]);
            if ($carry === false) {
                return false;
            }
            for ($j = 0, $n = count($bytes); $j < $n; $j++) {
                $carry += $bytes[$j] * 58;
                $bytes[$j] = $carry & 0xFF;
                $carry >>= 8;
            }
            while ($carry > 0) {
                $bytes[] = $carry & 0xFF;
                $carry >>= 8;
            }
        }
        $bytes = array_reverse($bytes);
        if (count($bytes) !== 25 || $bytes[0] !== 0x41) {
            return false;
        }

        $raw = '';
        foreach ($bytes as $byte) {
            $raw .= chr($byte);
        }

        return hash_equals(substr(hash('sha256', hash('sha256', substr($raw, 0, 21), true), true), 0, 4), substr($raw, 21, 4));
        }

    public static function walletsCollide($tronadoWallet, $offlineTrxWallet)
    {
        $a = trim((string) $tronadoWallet);
        $b = trim((string) $offlineTrxWallet);
        return $a !== '' && $b !== '' && strcasecmp($a, $b) === 0;
        }

    public static function resolveWalletAddress()
    {
        // Tronado pays into its own wallet only. There is deliberately no fallback to the crypto
        // wallets section: that TRX wallet is also watched by the offline hash-checker, which does not
        // know Tronado's payout transactions, so a buyer could submit a Tronado payout hash as proof
        // for an offline invoice and be credited twice for one payment.
        $walletSetting = \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "walletaddress", "select");
        return trim((string) ($walletSetting['ValuePay'] ?? ''));
        }

    public static function curlJson($endpoint, array $payload, $apiKey = null)
    {
        $headers = ['Content-Type: application/json'];
        if ($apiKey !== null) {
            $headers[] = 'x-api-key: ' . $apiKey;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $curlInfo = curl_getinfo($curl);
        $statusCode = $curlInfo['http_code'] ?? null;
        curl_close($curl);

        if ($response === false) {
            error_log('Tronado request failed: ' . json_encode([
                'url' => $endpoint,
                'error' => $curlError,
                'errno' => $curlErrno,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            error_log('Tronado invalid response: ' . json_encode([
                'url' => $endpoint,
                'status_code' => $statusCode,
                'raw_response' => $response,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return null;
        }

        return $decoded;
        }

    public static function getTronPriceToman()
    {
        $config = defined('TRONADO_API_CONFIGURATION') ? TRONADO_API_CONFIGURATION : [];
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://bot.tronado.cloud'), '/');
        $path = '/' . ltrim((string) ($config['price_toman_path'] ?? '/Tron/GetPriceToToman'), '/');

        $decoded = self::curlJson($baseUrl . $path, []);
        if (!is_array($decoded) || !isset($decoded['TronPriceToman']) || !is_numeric($decoded['TronPriceToman'])) {
            return null;
        }

        return (float) $decoded['TronPriceToman'];
        }

    public static function createPayment($runtimeDomainhosts, $order_id, $price)
    {
        $domainhosts = $runtimeDomainhosts;

        $apitronseller = trim((string) \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "apiternado", "select")['ValuePay']);
        $walletaddress = self::resolveWalletAddress();
        $wageFromBusinessPercentage = (int) \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "wageFromBusinessPercentageTronado", "select")['ValuePay'];

        if ($apitronseller === '') {
            return [
                'success' => false,
                'error' => 'کلید API ترونادو تنظیم نشده است',
            ];
        }

        if ($walletaddress === '') {
            return [
                'success' => false,
                'error' => 'آدرس کیف پول ترونادو تنظیم نشده است. از منوی ترونادو «💼 آدرس کیف پول ترونادو» آن را ثبت کنید.',
            ];
        }

        $tronPriceToman = self::getTronPriceToman();
        if ($tronPriceToman === null || $tronPriceToman <= 0) {
            return [
                'success' => false,
                'error' => 'دریافت قیمت ترون از ترونادو با خطا مواجه شد',
            ];
        }

        $tronAmount = round(((float) $price) / $tronPriceToman, 6);

        $endpoints = defined('TRONADO_ORDER_TOKEN_ENDPOINTS') ? TRONADO_ORDER_TOKEN_ENDPOINTS : [];
        if (empty($endpoints)) {
            $endpoints = ['https://bot.tronado.cloud/api/v5/GetOrderToken'];
        }

        if (trim((string) $domainhosts) === '' || preg_match('#^(localhost|127\.|\d{1,3}(\.\d{1,3}){3}$)#i', trim((string) $domainhosts))) {
            return [
                'success' => false,
                'error' => 'دامنه‌ی ربات برای آدرس Callback ترونادو تنظیم نشده یا معتبر نیست',
            ];
        }

        $callbackUrl = 'https://' . $domainhosts . '/payment/tronado.php';
        $requestPayload = [
            'PaymentID' => (string) $order_id,
            'WalletAddress' => $walletaddress,
            'TronAmount' => $tronAmount,
            'CallbackUrl' => $callbackUrl,
        ];

        $endpoint = $endpoints[0] . '?wageFromBusinessPercentage=' . $wageFromBusinessPercentage;

        $rawDecodedResponse = self::curlJson($endpoint, $requestPayload, $apitronseller);
        $decodedResponse = self::normalizeOrderTokenResponse($rawDecodedResponse);

        if (!is_array($decodedResponse) || !array_key_exists('Token', $decodedResponse) || trim((string) $decodedResponse['Token']) === '') {
            $errorText = is_array($decodedResponse) ? (string) ($decodedResponse['ErrorMessage'] ?? 'پاسخ نامعتبر از سرویس ترونادو') : 'پاسخ نامعتبر از سرویس ترونادو';
            if (!self::isValidTronAddress($walletaddress)) {
                // Most often an address saved upper-cased by an older version; Base58 is case sensitive.
                $errorText .= ' | آدرس کیف پول ترونادو ذخیره‌شده معتبر نیست؛ از منوی ترونادو «💼 آدرس کیف پول ترونادو» آن را دوباره ثبت کنید.';
            }
            $errorPayload = [
                'success' => false,
                'error' => $errorText,
                'url' => $endpoint,
            ];

            error_log('Tronado GetOrderToken failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $errorPayload;
        }

        $decodedResponse['rx_tron_amount'] = $tronAmount;
        $decodedResponse['rx_wage'] = $wageFromBusinessPercentage;

        return $decodedResponse;
        }
}
