<?php

namespace Faoxima\Payments\Gateways\Nowpayments;



final class Client
{
    public static function countsAsPaid(string $status, float $requested, float $paid): bool
    {
        return in_array($status, ['finished', 'confirmed', 'sending'], true)
            || ($status === 'partially_paid' && $requested > 0 && $paid >= $requested - 0.000001);
    }

    public static function create($domainhosts, $payment, $price_amount, $order_id, $order_description)
    {
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "api_nowpayment", "select");
        $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
        if ($apinowpayments === '' || $apinowpayments === '0') {
            $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller", "select");
            $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.nowpayments.io/v1/' . $payment,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 7000,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array(
                'x-api-key:' . $apinowpayments,
                'Content-Type: application/json'
            ),
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
            'price_amount' => $price_amount,
            'price_currency' => 'usd',
            'order_id' => $order_id,
            'order_description' => $order_description,
            'ipn_callback_url' => "https://" . $domainhosts . "/payment/nowpayment.php"
        ]));

        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }

    public static function status($paymentid)
    {
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "api_nowpayment", "select");
        $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
        if ($apinowpayments === '' || $apinowpayments === '0') {
            $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller", "select");
            $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.nowpayments.io/v1/payment/' . $paymentid,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'x-api-key:' . $apinowpayments
            ),
        ));
        $response = curl_exec($curl);
        $response = json_decode($response, true);
        curl_close($curl);
        return $response;
    }
}
