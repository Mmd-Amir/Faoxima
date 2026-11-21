<?php

namespace Faoxima\Payments\Gateways\Zarinpal;

final class Client
{
    public static function createPayZarinpal($runtimeDomainhosts, $price, $order_id)
    {
        $domainhosts = $runtimeDomainhosts;
        $marchent_zarinpal = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal", "select")['ValuePay'];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.zarinpal.com/pg/v4/payment/request.json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json'
            ),
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
            "merchant_id" => $marchent_zarinpal,
            "currency" => "IRT",
            "amount" => $price,
            "callback_url" => "https://$domainhosts/payment/zarinpal.php",
            "description" => $order_id,
            "metadata" => array(
                "order_id" => $order_id
            )
        ]));
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
        }

    public static function verify($PaySetting, $price, $Authority, $userId)
    {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.zarinpal.com/pg/v4/payment/verify.json',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_CONNECTTIMEOUT => 5,
          CURLOPT_TIMEOUT => 20,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
          ),
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
          "merchant_id" => $PaySetting,
          "amount"=> $price,
          "authority" => $Authority,
          "description" => $userId
                ]));
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response,true);
    }
}
