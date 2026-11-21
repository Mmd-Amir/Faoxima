<?php

namespace Faoxima\Payments\Gateways\Plisio;

final class CronJobs
{
    public static function statusplisio($tx_id){
    global $connect;
    static $api_key = null;
    if ($api_key === null) {
        $rowPlisio = mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = 'api_plisio'"));
        $api_key = is_array($rowPlisio) ? trim((string)($rowPlisio['ValuePay'] ?? '')) : '';
        if ($api_key === '' || $api_key === '0') {
            $rowLegacy = mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = 'apinowpayment'"));
            $api_key = is_array($rowLegacy) ? trim((string)($rowLegacy['ValuePay'] ?? '')) : '';
        }
    }
    $url = 'https://api.plisio.net/api/v1/operations?';
    $url .= '&api_key=' . urlencode($api_key);
    $url .= '&search='.$tx_id;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
}
