<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
@chdir(__DIR__);

include('config.php');
require_once 'request.php';
date_default_timezone_set('Asia/Tehran');

function token_panel($code_panel,$verify = true){
    if ($verify) {
        $cachedSession = marzbanCachedSession($code_panel);
        if ($cachedSession !== null) {
            return $cachedSession;
        }
    }
    $panel = select("marzban_panel","*","code_panel",$code_panel,"select");
    if (!is_array($panel) || empty($panel)) {
        return ["error" => "Panel configuration not found for the provided code."];
    }

    $requestedAt = time();
    if($panel['datelogin'] != null && $verify){
        $date = marzbanReusableSession($panel['datelogin'], 3600);
        if($date !== null){
            marzbanRememberSession($code_panel, $date);
            return $date;
        }
    }
    return marzbanAcquireToken($panel, $code_panel, $verify ? null : $requestedAt, null);
}

function marzbanReusableSession($datelogin, $maxAge, $notBefore = null, $rejectedToken = null)
{
    $date = is_array($datelogin) ? $datelogin : (is_string($datelogin) ? json_decode($datelogin, true) : null);
    if (!is_array($date) || !isset($date['time']) || empty($date['access_token']) || !is_string($date['access_token'])) {
        return null;
    }
    if ($rejectedToken !== null && $date['access_token'] === $rejectedToken) {
        return null;
    }
    $issuedAt = strtotime($date['time']);
    if ($issuedAt === false || time() - $issuedAt > $maxAge) {
        return null;
    }
    if ($notBefore !== null && $issuedAt < $notBefore) {
        return null;
    }
    $exp = function_exists('rx_panel_token_jwt_exp') ? rx_panel_token_jwt_exp($date['access_token']) : null;
    if ($exp !== null && $exp - 60 <= time()) {
        return null;
    }
    return $date;
}

function marzbanCachedSession($code_panel)
{
    $cached = $GLOBALS['__faoxima_panel_token_cache']['marzban'][$code_panel] ?? null;
    if (is_array($cached) && isset($cached['__cached_at']) && (time() - $cached['__cached_at']) < 300) {
        $session = marzbanReusableSession($cached['payload'] ?? null, 3600);
        if ($session !== null) {
            return $session;
        }
    }
    unset($GLOBALS['__faoxima_panel_token_cache']['marzban'][$code_panel]);
    return null;
}

function marzbanRememberSession($code_panel, array $session)
{
    $GLOBALS['__faoxima_panel_token_cache']['marzban'][$code_panel] = [
        '__cached_at' => time(),
        'payload'     => $session,
    ];
}

function marzbanPdo()
{
    $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
    return $pdo instanceof PDO ? $pdo : null;
}

function marzbanFreshDatelogin($code_panel)
{
    $pdo = marzbanPdo();
    if ($pdo === null) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT datelogin FROM marzban_panel WHERE code_panel = ? LIMIT 1");
        $stmt->execute([$code_panel]);
        $value = $stmt->fetchColumn();
        return is_string($value) ? $value : null;
    } catch (Throwable $e) {
        error_log('marzbanFreshDatelogin: ' . $e->getMessage());
        return null;
    }
}

function marzbanForgetSession($code_panel, $rejectedToken)
{
    $cached = $GLOBALS['__faoxima_panel_token_cache']['marzban'][$code_panel]['payload']['access_token'] ?? null;
    if ($cached === $rejectedToken) {
        unset($GLOBALS['__faoxima_panel_token_cache']['marzban'][$code_panel]);
    }
    $raw = marzbanFreshDatelogin($code_panel);
    $stored = $raw !== null ? json_decode($raw, true) : null;
    if (!is_array($stored) || ($stored['access_token'] ?? null) !== $rejectedToken) {
        return;
    }
    try {
        $stmt = marzbanPdo()->prepare("UPDATE marzban_panel SET datelogin = NULL WHERE code_panel = ? AND datelogin = ?");
        $stmt->execute([$code_panel, $raw]);
        $cleared = $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('marzbanForgetSession: ' . $e->getMessage());
        return;
    }
    if ($cleared) {
        if (function_exists('clearSelectCache')) {
            clearSelectCache('marzban_panel');
        }
        if (function_exists('faoxima_bust_bot_selectcache')) {
            faoxima_bust_bot_selectcache('marzban_panel');
        }
    }
}

function marzbanAcquireToken($panel, $code_panel, $notBefore = null, $rejectedToken = null)
{
    $canLock = function_exists('rx_panel_token_lock_acquire');
    $lock = $canLock ? rx_panel_token_lock_acquire('marzban', $panel['url_panel'] . '|' . $panel['username_panel']) : null;
    try {
        $fresh = marzbanReusableSession(marzbanFreshDatelogin($code_panel), 3600, $notBefore, $rejectedToken);
        if ($fresh !== null) {
            marzbanRememberSession($code_panel, $fresh);
            return $fresh;
        }
        if ($canLock && $lock === null) {
            return ["error" => "دریافت دسترسی پنل موقتاً انجام نشد؛ لطفاً کمی بعد دوباره تلاش کنید.", "error_code" => "auth_busy"];
        }
        return marzbanLoginLocked($panel, $code_panel);
    } finally {
        if ($lock !== null && function_exists('rx_panel_token_lock_release')) {
            rx_panel_token_lock_release($lock);
        }
    }
}

function marzbanRefreshRejectedToken($code_panel, $rejectedToken)
{
    marzbanForgetSession($code_panel, $rejectedToken);
    $panel = select("marzban_panel","*","code_panel",$code_panel,"select");
    if (!is_array($panel) || empty($panel)) {
        return ["error" => "Panel configuration not found for the provided code."];
    }
    return marzbanAcquireToken($panel, $code_panel, null, $rejectedToken);
}

function marzbanTokenError($Check_token)
{
    if (is_array($Check_token) && isset($Check_token['access_token']) && is_string($Check_token['access_token']) && $Check_token['access_token'] !== '') {
        return null;
    }
    if (is_array($Check_token) && !empty($Check_token['error'])) {
        return $Check_token;
    }
    if (is_array($Check_token) && ($Check_token['detail'] ?? null) === 'Incorrect username or password') {
        return ["error" => "ورود به پنل ناموفق بود؛ نام کاربری یا رمز عبور پنل اشتباه است.", "error_code" => "auth_rejected"];
    }
    return ["error" => "ورود به پنل ناموفق بود؛ پنل توکن دسترسی معتبری برنگرداند.", "error_code" => "auth_failed"];
}

function marzbanSendAuthorized($code_panel, $accessToken, callable $send)
{
    $response = $send($accessToken);
    if (!is_array($response) || (int) ($response['status'] ?? 0) !== 401 || !empty($response['error'])) {
        return $response;
    }
    if (!is_string($accessToken) || $accessToken === '') {
        return $response;
    }
    $fresh = marzbanRefreshRejectedToken($code_panel, $accessToken);
    $freshToken = $fresh['access_token'] ?? null;
    if (!is_string($freshToken) || $freshToken === '' || $freshToken === $accessToken) {
        return $response;
    }
    return $send($freshToken);
}

function marzbanLoginLocked($panel, $code_panel)
{
    $url_get_token = $panel['url_panel'].'/api/admin/token';
    $username_panel = $panel['username_panel'];
    $password_panel = $panel['password_panel'];
    $data_token = array(
        'username' => $username_panel,
        'password' => $password_panel
    );
    $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'accept: application/json'
    );
    $req = new CurlRequest($url_get_token);
    $req->setHeaders($headers);
    $response = $req->post($data_token);
    if(!empty($response['error'])){
        return array("error" => $response['error']);
    }
    $body = json_decode($response['body'], true);
    if(isset($body['access_token']) && is_string($body['access_token']) && $body['access_token'] !== ''){
        $time = date('Y/m/d H:i:s');
        $data = json_encode(array(
            'time' => $time,
            'access_token' => $body['access_token']
            ));
        if (isset($panel['name_panel'])) {
            update("marzban_panel","datelogin",$data,'name_panel',$panel['name_panel']);
        }
        $GLOBALS['__faoxima_panel_token_cache']['marzban'][$code_panel] = [
            '__cached_at' => time(),
            'payload'     => ['time' => $time, 'access_token' => $body['access_token']],
        ];
    }
    return $body;
}


function findMarzbanPanelByName($location)
{
    $panel = select("marzban_panel", "*", "name_panel", $location, "select");
    if (!is_array($panel) || empty($panel)) {
        return null;
    }

    return $panel;
}


function getuser($username_account,$location)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/user/' . $username_account;
    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->get();
    });
    return $response;
}


function Get_Nodes($location)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/nodes';
    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->get();
    });
    return $response;
}
function Get_usage_Nodes($location)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/nodes/usage';
    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->get();
    });
    return $response;
}
function Get_Node($location,$Nodeid)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/node/'.$Nodeid;
    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->get();
    });
    return $response;
}

function getusers($location,$status)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/users?status='.$status;
    if(!isset($Check_token['access_token']))return;
    $header_value = 'Bearer ';

    $output = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'], function ($token) use ($url, $header_value) {
        $ch = curl_init();
        if (function_exists('faoxima_apply_curl_proxy')) faoxima_apply_curl_proxy($ch, 'panel');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Authorization: ' . $header_value .  $token
        ));

        $output = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $output];
    })['body'];
    $data_user = json_decode($output, true);

    if (!is_array($data_user)) {
        return ['error' => 'Unable to decode panel response.'];
    }

    if (isset($data_user['error'])) {
        return $data_user;
    }

    if (!isset($data_user['users'])) {
        $data_user['users'] = [];
    }

    return $data_user;
}

function getinbounds($location)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/inbounds';
    $header_value = 'Bearer ';

    $output = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $header_value) {
        $ch = curl_init();
        if (function_exists('faoxima_apply_curl_proxy')) faoxima_apply_curl_proxy($ch, 'panel');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Authorization: ' . $header_value .  $token
        ));

        $output = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $output];
    })['body'];
    $inbounds = json_decode($output, true);
    return $inbounds;
}

function ResetUserDataUsage($username_account,$location)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/user/' . $username_account.'/reset';

    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->post(array());
    });
    return $response;
}
function revoke_sub($username_account,$location)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/user/' . $username_account.'/revoke_sub';
    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->post(array());
    });
    return $response;
}

function adduser($location,$data_limit,$username_ac,$timestamp,$note ='',$data_limit_reset = 'no_reset',$name_product = false)
{
    global $pdo;
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url = $marzban_list_get['url_panel']."/api/user";
    if($marzban_list_get['inbounds'] != null and $marzban_list_get['inbounds'] != "null"){
            if($name_product != false and $name_product != "usertest"){
             $product = select("product","*","name_product",$name_product,"select");
             if($product == false || $product['inbounds'] == false){
                 $inbounds = json_decode($marzban_list_get['inbounds'],true);
             }else{
                 $inbounds = json_decode($product['inbounds'],true);
                 $marzban_list_get['proxies'] = $product['proxies'];
             }
            }else{
        $inbounds = json_decode($marzban_list_get['inbounds'],true);
            }
        }
    $proxySettings = json_decode($marzban_list_get['proxies'] ?? 'null');
    if (is_object($proxySettings)) {
        foreach ($proxySettings as $options) {
            if (!is_object($options)) {
                continue;
            }
            foreach (['id', 'uuid', 'password', 'secret', 'auth', 'private_key', 'public_key', 'pre_shared_key', 'peer_ips'] as $field) {
                unset($options->{$field});
            }
        }
    }
    if ((string)($marzban_list_get['version_panel'] ?? '0') === '1') {
            $data = array(
            "proxy_settings" => $proxySettings,
            "data_limit" => $data_limit,
            "username" => $username_ac,
            "note" => $note,
            "data_limit_reset_strategy" => $data_limit_reset
        );
        if(isset($inbounds)){
            $data['group_ids'] = $inbounds;
        }
        if($name_product == "usertest"){
            if($marzban_list_get['on_hold_test'] == "0"){
        if ($timestamp == 0) {
            $data["expire"] = 0;
        } else {
            $data["expire"] = date('c',$timestamp);
        }
        }else{
            if($timestamp == 0 ){
                $data["expire"] = 0;
            }else{
            $data["expire"] = 0;
            $data["status"] = "on_hold";
            $data["on_hold_expire_duration"] = $timestamp - time();
            }
        }
        }else{
        if($marzban_list_get['conecton'] == "offconecton"){
        if ($timestamp == 0) {
            $data["expire"] = 0;
        } else {
            $data["expire"] = date('c',$timestamp);
        }
        }else{
            if($timestamp == 0 ){
                $data["expire"] = 0;
            }else{
            $data["expire"] = 0;
            $data["status"] = "on_hold";
            $data["on_hold_expire_duration"] = $timestamp - time();
            }
        }
        }
    }else{
        $data = array(
            "proxies" => $proxySettings,
            "data_limit" => $data_limit,
            "username" => $username_ac,
            "note" => $note,
            "data_limit_reset_strategy" => $data_limit_reset
        );
        if(isset($inbounds)){
            $data['inbounds'] = $inbounds;
        }
        if($name_product == "usertest"){
            if($marzban_list_get['on_hold_test'] == "0"){
        if ($timestamp == 0) {
            $data["expire"] = 0;
        } else {
            $data["expire"] = $timestamp;
        }
        }else{
            if($timestamp == 0 ){
                $data["expire"] = 0;
            }else{
            $data["expire"] = 0;
            $data["status"] = "on_hold";
            $data["on_hold_expire_duration"] = $timestamp - time();
            }
        }
        }else{
        if($marzban_list_get['conecton'] == "offconecton"){
        if ($timestamp == 0) {
            $data["expire"] = 0;
        } else {
            $data["expire"] = $timestamp;
        }
        }else{
            if($timestamp == 0 ){
                $data["expire"] = 0;
            }else{
            $data["expire"] = 0;
            $data["status"] = "on_hold";
            $data["on_hold_expire_duration"] = $timestamp - time();
            }
        }
        }
        }
    $headers = array(
            'accept: application/json',
            'Content-Type: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers, $data) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->post(json_encode($data));
    });
    return $response;
}

function Get_System_Stats($location){
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/system';
    $header_value = 'Bearer ';

    $output = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $header_value) {
        $ch = curl_init();
        if (function_exists('faoxima_apply_curl_proxy')) faoxima_apply_curl_proxy($ch, 'panel');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Authorization: ' . $header_value .  $token
        ));

        $output = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $output];
    })['body'];
    $Get_System_Stats = json_decode($output, true);
    return $Get_System_Stats;
}

function removeuser($location,$username)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/user/'.$username;
    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->delete();
    });
    return $response;
}
function removenode($location,$nodeid){
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/node/'.$nodeid;
    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->delete();
    });
    return $response;
}

function Modifyuser($location,$username,array $data)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/user/'.$username;
    $headers = array(
            'accept: application/json',
            'Content-Type: application/json'
    );
    $payload = json_encode($data);
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers, $payload) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->put($payload);
    });
    return $response;
}


function Modifyuser_node($location,$id_node,array $data)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/node/'.$id_node;
    $payload = json_encode($data);
    $result = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $payload) {
        $ch = curl_init();
        if (function_exists('faoxima_apply_curl_proxy')) faoxima_apply_curl_proxy($ch, 'panel');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Authorization: Bearer '.$token;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $result];
    })['body'];
     $data_useer = json_decode($result, true);
    return $data_useer;
}
function hosts($location)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/hosts';
    $result = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url) {
        $ch = curl_init();
        if (function_exists('faoxima_apply_curl_proxy')) faoxima_apply_curl_proxy($ch, 'panel');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Authorization: Bearer '.$token;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $result];
    })['body'];
     $data_hosts = $result;
    return $data_hosts;
}

function reconnect_node($location,$id_node)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel'].'/api/node/'.$id_node.'/reconnect';
    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->post(array());
    });
    return $response;
}

function get_list_update($location,$username)
{
    $marzban_list_get = findMarzbanPanelByName($location);
    if ($marzban_list_get === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $tokenError = marzbanTokenError($Check_token);
    if ($tokenError !== null) {
        return $tokenError;
    }
    $url =  $marzban_list_get['url_panel']."/api/user/$username/sub_update?offset=0&limit=1";
    $headers = array(
            'accept: application/json'
    );
    $response = marzbanSendAuthorized($marzban_list_get['code_panel'], $Check_token['access_token'] ?? null, function ($token) use ($url, $headers) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        $req->setBearerToken($token);
        return $req->get();
    });
    return $response;
}

function marzbanCapabilityGet(array $panel, $path)
{
    $token = token_panel($panel['code_panel']);
    $tokenError = marzbanTokenError($token);
    if ($tokenError !== null) {
        return ['status' => 0, 'body' => '', 'error' => (string) ($tokenError['error'] ?? 'Marzban login failed')];
    }
    $url = rtrim((string) $panel['url_panel'], '/') . $path;
    return marzbanSendAuthorized($panel['code_panel'], $token['access_token'] ?? null, function ($accessToken) use ($url) {
        $req = new CurlRequest($url);
        $req->setHeaders(['accept: application/json']);
        $req->setBearerToken($accessToken);
        return $req->get();
    });
}

function marzbanCapabilityReport(array $panel, array &$report)
{
    $isV1 = (string) ($panel['version_panel'] ?? '0') === '1';
    rx_cap_info($report, 'نسخه API', $isV1 ? 'v1 (گروه‌محور)' : 'کلاسیک');
    $username = trim((string) ($panel['username_panel'] ?? ''));
    $password = trim((string) ($panel['password_panel'] ?? ''));
    if ($username === '' || $password === '') {
        rx_cap_set($report, 'connection', 'failed', 'نام کاربری یا رمز عبور برای این پنل تنظیم نشده است.');
        rx_cap_mark_untested_after_connection_failure($report);
        return;
    }
    $adminResponse = marzbanCapabilityGet($panel, '/api/admin');
    $adminStatus = (int) ($adminResponse['status'] ?? 0);
    $admin = rx_cap_json($adminResponse);
    if (!empty($adminResponse['error']) || $adminStatus < 200 || $adminStatus >= 300 || $admin === null) {
        $note = !empty($adminResponse['error'])
            ? rx_cap_clean_text((string) $adminResponse['error'], 160)
            : ($admin === null && $adminStatus >= 200 && $adminStatus < 300 ? 'پاسخ /api/admin JSON معتبر نبود.' : rx_cap_http_read($adminResponse)[1]);
        rx_cap_set($report, 'connection', 'failed', $note);
        rx_cap_mark_untested_after_connection_failure($report);
        return;
    }
    rx_cap_set($report, 'connection', 'verified', 'ورود و GET /api/admin موفق بود.');
    rx_cap_info($report, 'ادمین متصل', (string) ($admin['username'] ?? ''));
    $perm = rx_cap_apply_marzban_family($report, $admin);

    $listResponse = marzbanCapabilityGet($panel, '/api/users?limit=1');
    $list = rx_cap_http_read($listResponse);
    if ($list[0] === 'verified') {
        $listData = rx_cap_json($listResponse);
        $total = is_array($listData) && isset($listData['total']) ? (int) $listData['total'] : null;
        $scope = $perm['actions']['list_users'][1] ?? null;
        $list[1] = 'GET /api/users موفق بود' . ($total !== null ? '؛ تعداد قابل مشاهده: ' . $total : '') . ($scope === 'own' ? '؛ فقط کاربران متعلق به این ادمین.' : '.');
    }
    rx_cap_set($report, 'list_users', $list[0], $list[1]);

    rx_cap_probe_sample_user_via_manager($panel, $report);

    $resourcePath = $isV1 ? '/api/groups' : '/api/inbounds';
    $resourceResponse = marzbanCapabilityGet($panel, $resourcePath);
    $resource = rx_cap_http_read($resourceResponse);
    if ($resource[0] === 'verified') {
        $data = rx_cap_json($resourceResponse);
        if ($isV1) {
            $groups = is_array($data['groups'] ?? null) ? $data['groups'] : [];
            $resource[1] = 'GET /api/groups موفق بود؛ ' . count($groups) . ' گروه قابل مشاهده.';
        } else {
            $tags = 0;
            foreach ((array) $data as $protocolInbounds) {
                $tags += is_array($protocolInbounds) ? count($protocolInbounds) : 0;
            }
            $resource[1] = 'GET /api/inbounds موفق بود؛ ' . $tags . ' اینباند قابل مشاهده.';
        }
    } else {
        $resource[1] = 'GET ' . $resourcePath . ': ' . $resource[1];
    }
    rx_cap_set($report, 'resources', $resource[0], $resource[1]);
}
