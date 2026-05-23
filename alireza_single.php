<?php
require_once 'config.php';
require_once 'x-ui_single.php';
require_once 'request.php';
ini_set('error_log', 'error_log');


if (!function_exists('alireza_tls_verifypeer')) {
    function alireza_tls_verifypeer() {
        return defined('BOT_PANEL_VERIFY_TLS') && BOT_PANEL_VERIFY_TLS;
    }
}
if (!function_exists('alireza_tls_verifyhost')) {
    function alireza_tls_verifyhost() {
        return alireza_tls_verifypeer() ? 2 : 0;
    }
}

if (!function_exists('getCookieStoragePath')) {
    function getCookieStoragePath() {
        if (function_exists('xuisingle_cookie_path')) {
            return xuisingle_cookie_path();
        }
        static $path = null;
        if ($path === null) {
            try {
                $entropy = bin2hex(random_bytes(8));
            } catch (\Throwable $e) {
                $entropy = uniqid('', true);
            }
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'alirezasin_cookie_' . getmypid() . '_' . $entropy . '.txt';
        }
        return $path;
    }
}

function get_clinetsalireza($username,$namepanel){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    login($marzban_list_get['code_panel']);
    $curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/inbounds',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_SSL_VERIFYHOST => alireza_tls_verifyhost(),
  CURLOPT_SSL_VERIFYPEER => alireza_tls_verifypeer(),
  CURLOPT_TIMEOUT_MS => 4000,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json'
  ),
  CURLOPT_COOKIEFILE => getCookieStoragePath(),
  CURLOPT_COOKIEJAR => getCookieStoragePath(),
));
$output = [];
$response = json_decode(curl_exec($curl),true)['obj'];
if(!isset($response))return;
foreach ($response as $client){
    $clientdata= json_decode($client['settings'],true)['clients'];
    foreach($clientdata as $clinets){
    if($clinets['email'] == $username){
        $output[] = $clinets;
        break;
    }
    }
    $clientStats= $client['clientStats'];
    foreach($clientStats as $clinetsup){
    if($clinetsup['email'] == $username){
        $output[] = $clinetsup;
        break;
    }
    }

}
curl_close($curl);
$cookiePath = getCookieStoragePath();
if (is_file($cookiePath)) {
    unlink($cookiePath);
}
return $output;
}
function addClientalireza_singel($namepanel, $usernameac, $Expire,$Total, $Uuid,$Flow,$subid,$inboundid){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    login($marzban_list_get['code_panel']);
    $config = array(
        "id" => intval($inboundid),
        'settings' => json_encode(array(
            'clients' => array(
                array(
                "id" => $Uuid,
                "flow" => $Flow,
                "email" => $usernameac,
                "totalGB" => $Total,
                "expiryTime" => $Expire,
                "enable" => true,
                "tgId" => "",
                "subId" => $subid,
                "reset" => 0
            )),
             'decryption' => 'none',
            'fallbacks' => array(),
        ))
        );

    $configpanel = json_encode($config,true);
    $url = $marzban_list_get['url_panel'].'/xui/API/inbounds/addClient';
    $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(getCookieStoragePath());
    $response = $req->post($configpanel);
    $cookiePath = getCookieStoragePath();
    if (is_file($cookiePath)) {
        unlink($cookiePath);
    }
    return $response;
}
function updateClientalireza($namepanel, $username,array $config){
    $UsernameData = get_clinetsalireza($username,$namepanel)[0];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    login($marzban_list_get['code_panel']);
    $configpanel = json_encode($config,true);
    $url = $marzban_list_get['url_panel'].'/xui/API/inbounds/updateClient/'.$UsernameData['id'];
    $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(getCookieStoragePath());
    $response = $req->post($configpanel);
    $cookiePath = getCookieStoragePath();
    if (is_file($cookiePath)) {
        unlink($cookiePath);
    }
    return $response;
}
function ResetUserDataUsagealirezasin($usernamepanel, $namepanel){
    $data_user = get_clinetsalireza($usernamepanel,$namepanel)[0];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    login($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel']."/xui/API/inbounds/{$marzban_list_get['inboundid']}/resetClientTraffic/".$data_user['email'];
    $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(getCookieStoragePath());
    $response = $req->post($configpanel);
    $cookiePath = getCookieStoragePath();
    if (is_file($cookiePath)) {
        unlink($cookiePath);
    }
    return $response;
}
function removeClientalireza_single($location,$username){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $data_user = get_clinetsalireza($username,$location)[0];
    login($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel']."/xui/API/inbounds/{$marzban_list_get['inboundid']}/delClient/".$data_user['id'];
    $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(getCookieStoragePath());
    $response = $req->post(array());
    $cookiePath = getCookieStoragePath();
    if (is_file($cookiePath)) {
        unlink($cookiePath);
    }
    return $response;

}
function get_onlineclialireza($name_panel,$username){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $name_panel,"select");
    login($marzban_list_get['code_panel']);
    $curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => $marzban_list_get['url_panel'].'/xui/API/inbounds/onlines',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_SSL_VERIFYHOST => alireza_tls_verifyhost(),
  CURLOPT_SSL_VERIFYPEER => alireza_tls_verifypeer(),
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json'
  ),
  CURLOPT_COOKIEFILE => getCookieStoragePath(),
  CURLOPT_COOKIEJAR => getCookieStoragePath(),
));
$response = json_decode(curl_exec($curl),true)['obj'];
if($response == null)return "offline";
if(in_array($username,$response))return "online";
return "offline";
curl_close($curl);
$cookiePath = getCookieStoragePath();
if (is_file($cookiePath)) {
    unlink($cookiePath);
}

}
