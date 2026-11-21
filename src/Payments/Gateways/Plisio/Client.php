<?php

namespace Faoxima\Payments\Gateways\Plisio;



final class Client
{
    public static function create($domainhosts, $order_id, $price)
    {
        $rowPlisio = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "api_plisio", "select");
        $api_key = is_array($rowPlisio) ? trim((string)($rowPlisio['ValuePay'] ?? '')) : '';
        if ($api_key === '' || $api_key === '0') {
            $rowLegacy = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apinowpayment", "select");
            $api_key = is_array($rowLegacy) ? trim((string)($rowLegacy['ValuePay'] ?? '')) : '';
        }

        $callbackUrl = '';
        $successUrl  = '';
        $failUrl     = '';
        if (isset($domainhosts) && is_string($domainhosts) && $domainhosts !== '') {
            $host = rtrim(preg_replace('#^https?://#', '', $domainhosts), '/');
            if ($host !== '') {
                $callbackUrl = 'https://' . $host . '/payment/plisio.php?json=true';
                $successUrl  = 'https://' . $host . '/payment/plisio_return.php?kind=success&order=' . urlencode($order_id);
                $failUrl     = 'https://' . $host . '/payment/plisio_return.php?kind=fail&order=' . urlencode($order_id);
            }
        }

        $url = 'https://api.plisio.net/api/v1/invoices/new';
        $url .= '?currency=TRX';
        $url .= '&amount=' . urlencode($price);
        $url .= '&order_number=' . urlencode($order_id);
        $url .= '&email=customer@plisio.net';
        $url .= '&order_name=plisio';
        $url .= '&language=fa';
        if ($callbackUrl !== '') {
            $url .= '&callback_url=' . urlencode($callbackUrl);
        }
        if ($successUrl !== '') {
            $url .= '&success_callback_url=' . urlencode($successUrl);
            $url .= '&success_invoice_url='  . urlencode($successUrl);
        }
        if ($failUrl !== '') {
            $url .= '&fail_callback_url=' . urlencode($failUrl);
            $url .= '&fail_invoice_url='  . urlencode($failUrl);
        }
        $url .= '&api_key=' . urlencode($api_key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $response['data'] ?? null;
    }
}
