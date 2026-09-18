<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
@chdir(__DIR__);

include('config.php');
require_once 'request.php';
date_default_timezone_set('Asia/Tehran');

function pasarguardFormatUptime($seconds)
{
    $seconds = (int) $seconds;
    if ($seconds < 60) {
        return "{$seconds} ثانیه";
    }
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if ($days > 0) {
        $parts = ["{$days} روز"];
        if ($hours > 0) {
            $parts[] = "{$hours} ساعت";
        }
        return implode(' و ', $parts);
    }
    if ($hours > 0) {
        $parts = ["{$hours} ساعت"];
        if ($minutes > 0) {
            $parts[] = "{$minutes} دقیقه";
        }
        return implode(' و ', $parts);
    }
    return "{$minutes} دقیقه";
}

function findPasarGuardPanelByName($location)
{
    $panel = select("marzban_panel", "*", "name_panel", $location, "select");
    if (!is_array($panel) || empty($panel)) {
        return null;
    }
    return $panel;
}

function pasarguardApiKey(array $panel)
{
    return $panel['api_key'] ?? null;
}

function pasarguardAuthMode(array $panel)
{
    $mode = $panel['pasarguard_auth_mode'] ?? null;
    if ($mode === 'password') {
        return 'password';
    }
    if ($mode === 'api_key') {
        return 'api_key';
    }
    return !empty($panel['api_key']) ? 'api_key' : 'password';
}

function pasarguardTokenCacheKey($baseUrl, $username)
{
    return 'pasarguard_token:' . md5($baseUrl . '|' . $username);
}

function pasarguardCachedToken($baseUrl, $username)
{
    static $localCache = [];
    $key = pasarguardTokenCacheKey($baseUrl, $username);
    if (function_exists('rx_redis_get')) {
        $token = rx_redis_get($key);
        if ($token !== null && $token !== false && $token !== '') {
            return (string) $token;
        }
    }
    return $localCache[$key] ?? null;
}

function pasarguardStoreToken($baseUrl, $username, $token, $ttlSeconds = 3000)
{
    static $localCache = [];
    $key = pasarguardTokenCacheKey($baseUrl, $username);
    if (function_exists('rx_redis_set')) {
        rx_redis_set($key, $token, $ttlSeconds);
    }
    $localCache[$key] = $token;
}

function pasarguardClearToken($baseUrl, $username)
{
    static $localCache = [];
    $key = pasarguardTokenCacheKey($baseUrl, $username);
    if (function_exists('rx_redis_del')) {
        rx_redis_del($key);
    }
    unset($localCache[$key]);
}

function pasarguardLogin($baseUrl, $username, $password)
{
    $normalizedUrl = rtrim((string) $baseUrl, '/');
    $req = new CurlRequest($normalizedUrl . '/api/admin/token');
    $req->setHeaders(['accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
    $response = $req->post([
        'username' => (string) $username,
        'password' => (string) $password,
        'grant_type' => 'password',
    ]);
    if (!empty($response['error'])) {
        return ['status' => false, 'msg' => $response['error']];
    }
    $httpStatus = $response['status'] ?? null;
    if ($httpStatus === null || $httpStatus >= 400) {
        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        $message = is_array($decoded) && isset($decoded['detail']) ? $decoded['detail'] : "HTTP {$httpStatus}";
        return ['status' => false, 'msg' => is_array($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message];
    }
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($decoded) || empty($decoded['access_token'])) {
        return ['status' => false, 'msg' => 'Login response did not include an access token.'];
    }
    return ['status' => true, 'access_token' => (string) $decoded['access_token']];
}

function pasarguardGetToken($panel, $forceRefresh = false)
{
    $baseUrl = rtrim((string) ($panel['url_panel'] ?? ''), '/');
    $username = (string) ($panel['username_panel'] ?? '');
    $password = (string) ($panel['password_panel'] ?? '');
    if (!$forceRefresh) {
        $cached = pasarguardCachedToken($baseUrl, $username);
        if ($cached !== null) {
            return ['status' => true, 'access_token' => $cached];
        }
    }
    $loginResult = pasarguardLogin($baseUrl, $username, $password);
    if (empty($loginResult['status'])) {
        return $loginResult;
    }
    pasarguardStoreToken($baseUrl, $username, $loginResult['access_token']);
    return $loginResult;
}

function pasarguardRequest($panel, $method, $path, $data = null)
{
    $authMode = pasarguardAuthMode($panel);
    $url = rtrim($panel['url_panel'], '/') . $path;
    $headers = ['accept: application/json'];
    if ($data !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $doRequest = function ($bearerToken, $apiKey) use ($url, $headers, $method, $data) {
        $req = new CurlRequest($url);
        $req->setHeaders($headers);
        if ($bearerToken !== null) {
            $req->setBearerToken($bearerToken);
        }
        if ($apiKey !== null) {
            $req->api_key($apiKey);
        }
        switch (strtoupper($method)) {
            case 'POST':
                return $req->post($data !== null ? json_encode($data) : []);
            case 'PUT':
                return $req->put($data !== null ? json_encode($data) : null);
            case 'DELETE':
                return $req->delete($data !== null ? json_encode($data) : null);
            default:
                return $req->get();
        }
    };

    if ($authMode === 'password') {
        $baseUrl = rtrim((string) ($panel['url_panel'] ?? ''), '/');
        $username = (string) ($panel['username_panel'] ?? '');
        $tokenResult = pasarguardGetToken($panel);
        if (empty($tokenResult['status'])) {
            return ['status' => false, 'error' => $tokenResult['msg'] ?? 'PasarGuard login failed'];
        }
        $response = $doRequest($tokenResult['access_token'], null);
        if (($response['status'] ?? 0) == 401) {
            pasarguardClearToken($baseUrl, $username);
            $retryToken = pasarguardGetToken($panel, true);
            if (empty($retryToken['status'])) {
                return ['status' => false, 'error' => $retryToken['msg'] ?? 'PasarGuard login failed'];
            }
            $response = $doRequest($retryToken['access_token'], null);
        }
        return $response;
    }

    $apiKey = pasarguardApiKey($panel);
    return $doRequest(null, $apiKey);
}

function pasarguardGetUser($username_account, $location)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return pasarguardRequest($panel, 'GET', '/api/user/' . $username_account);
}

function pasarguardGetSubscriptionLinks($username_account, $location)
{
    $userResponse = pasarguardGetUser($username_account, $location);
    if (!empty($userResponse['error'])) {
        return $userResponse;
    }
    if (empty($userResponse['body'])) {
        return ["error" => "Empty response from panel when resolving subscription url."];
    }
    $userData = json_decode($userResponse['body'], true);
    if (!is_array($userData) || empty($userData['subscription_url'])) {
        return ["error" => "Unable to resolve subscription url."];
    }
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $subUrl = rtrim($panel['url_panel'], '/') . '/' . ltrim($userData['subscription_url'], '/');
    $req = new CurlRequest(rtrim($subUrl, '/') . '/links');
    $req->setHeaders(['accept: text/plain']);
    $response = $req->get();
    if (!empty($response['error']) || empty($response['status']) || $response['status'] >= 400) {
        return ["error" => "Unable to fetch subscription links."];
    }
    $links = array_values(array_filter(array_map('trim', explode("\n", (string) $response['body'])), function ($line) {
        return $line !== '';
    }));
    return ["status" => true, "links" => $links];
}

function pasarguardGetUsersByStatus($location, $status)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $response = pasarguardRequest($panel, 'GET', '/api/users?status=' . rawurlencode($status) . '&limit=200');
    if (!empty($response['error'])) {
        return $response;
    }
    if (empty($response['status']) || $response['status'] >= 400) {
        return ["error" => "Unable to fetch users by status."];
    }
    $data = json_decode((string) $response['body'], true);
    $users = is_array($data['users'] ?? null) ? $data['users'] : [];
    return ["status" => true, "users" => $users];
}

function pasarguardGetNodes($location)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return pasarguardRequest($panel, 'GET', '/api/nodes');
}

function pasarguardGetSystemStats($location)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return pasarguardRequest($panel, 'GET', '/api/system');
}

function pasarguardGetInbounds($location)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return pasarguardRequest($panel, 'GET', '/api/inbounds');
}

function pasarguardGetUserHwids($username_account, $location)
{
    $userResponse = pasarguardGetUser($username_account, $location);
    if (!empty($userResponse['error'])) {
        return $userResponse;
    }
    if (empty($userResponse['body'])) {
        return ["error" => "Empty response from panel when resolving user id."];
    }
    $userData = json_decode($userResponse['body'], true);
    if (!is_array($userData) || !isset($userData['id'])) {
        return ["error" => "Unable to resolve numeric user id for HWID lookup."];
    }
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return pasarguardRequest($panel, 'GET', '/api/user/' . $userData['id'] . '/hwids');
}

function pasarguardAddUser($location, $data_limit, $username_ac, $timestamp, $note = '', $data_limit_reset = 'no_reset', $name_product = false, $hwid_limit = null)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $inbounds = null;
    $product = null;
    if (!empty($panel['inbounds']) && $panel['inbounds'] != "null") {
        if ($name_product != false && $name_product != "usertest") {
            $product = select("product", "*", "name_product", $name_product, "select");
            if ($product == false || $product['inbounds'] == false) {
                $inbounds = json_decode($panel['inbounds'], true);
            } else {
                $inbounds = json_decode($product['inbounds'], true);
                $panel['proxies'] = $product['proxies'];
            }
        } else {
            $inbounds = json_decode($panel['inbounds'], true);
        }
    }
    if ($hwid_limit === null && $name_product != false && $name_product != "usertest") {
        if ($product === null) {
            $product = select("product", "*", "name_product", $name_product, "select");
        }
        if (is_array($product) && isset($product['hwid_limit'])) {
            $hwid_limit = (int) $product['hwid_limit'];
        }
    }
    $data = array(
        "proxy_settings" => json_decode($panel['proxies']),
        "data_limit" => $data_limit,
        "username" => $username_ac,
        "note" => $note,
        "data_limit_reset_strategy" => $data_limit_reset
    );
    if (isset($inbounds)) {
        // Issue #22 (feature request): merge the bot-configured groups with the
        // groups the user currently has on the panel — add-only, never remove —
        // so groups manually granted on the panel survive renewals.
        $groupIds = array_values(array_map('intval', (array) $inbounds));
        $userResponse = pasarguardGetUser($username_ac, $location);
        $httpStatus = is_array($userResponse) ? (int) ($userResponse['status'] ?? 0) : 0;
        if ($httpStatus === 200) {
            $panelUser = json_decode($userResponse['body'] ?? '', true);
            $currentGroups = is_array($panelUser) ? ($panelUser['group_ids'] ?? null) : null;
            if (is_array($currentGroups) && !empty($currentGroups)) {
                $groupIds = array_values(array_unique(array_merge(
                    array_map('intval', $currentGroups),
                    $groupIds
                )));
            }
        } elseif ($httpStatus !== 404) {
            // Live read failed for a reason other than "user not found" (e.g.
            // network or 5xx): omit group_ids entirely instead of sending the
            // default list, which could move an existing user between groups.
            return pasarguardRequest($panel, 'POST', '/api/user', $data);
        }
        $data['group_ids'] = $groupIds;
    }
    if (!empty($hwid_limit)) {
        $data['hwid_limit'] = (int) $hwid_limit;
    }
    if ($name_product == "usertest") {
        if ($panel['on_hold_test'] == "0") {
            $data["expire"] = $timestamp == 0 ? 0 : $timestamp;
        } else {
            if ($timestamp == 0) {
                $data["expire"] = 0;
            } else {
                $data["expire"] = 0;
                $data["status"] = "on_hold";
                $data["on_hold_expire_duration"] = $timestamp - time();
            }
        }
    } else {
        if ($panel['conecton'] == "offconecton") {
            $data["expire"] = $timestamp == 0 ? 0 : $timestamp;
        } else {
            if ($timestamp == 0) {
                $data["expire"] = 0;
            } else {
                $data["expire"] = 0;
                $data["status"] = "on_hold";
                $data["on_hold_expire_duration"] = $timestamp - time();
            }
        }
    }
    return pasarguardRequest($panel, 'POST', '/api/user', $data);
}

function pasarguardModifyUser($location, $username, array $data)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return pasarguardRequest($panel, 'PUT', '/api/user/' . $username, $data);
}

function pasarguardResetUserDataUsage($username_account, $location)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return pasarguardRequest($panel, 'POST', '/api/user/' . $username_account . '/reset', []);
}

function pasarguardRevokeSub($username_account, $location)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return pasarguardRequest($panel, 'POST', '/api/user/' . $username_account . '/revoke_sub', []);
}

function pasarguardTestConnection($baseUrl, $apiKey)
{
    $apiKey = trim((string) $apiKey);
    $normalizedUrl = rtrim((string) $baseUrl, '/');
    if ($apiKey === '') {
        return [
            'status' => false,
            'msg' => 'PasarGuard API key is missing'
        ];
    }
    $panel = [
        'url_panel' => $normalizedUrl,
        'api_key' => $apiKey,
    ];
    $response = pasarguardRequest($panel, 'GET', '/api/admin');
    if (!empty($response['error'])) {
        return [
            'status' => false,
            'msg' => $response['error']
        ];
    }
    $httpStatus = $response['status'] ?? null;
    if ($httpStatus === null || $httpStatus >= 400) {
        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        $message = is_array($decoded) && isset($decoded['detail']) ? $decoded['detail'] : "HTTP {$httpStatus}";
        return [
            'status' => false,
            'msg' => is_array($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message
        ];
    }
    return [
        'status' => true,
        'msg' => 'PasarGuard connection succeeded',
        'data' => json_decode((string) ($response['body'] ?? ''), true)
    ];
}

function pasarguardTestConnectionUserPass($baseUrl, $username, $password)
{
    $username = trim((string) $username);
    $password = trim((string) $password);
    $normalizedUrl = rtrim((string) $baseUrl, '/');
    if ($username === '' || $password === '') {
        return [
            'status' => false,
            'msg' => 'PasarGuard username or password is missing'
        ];
    }
    $loginResult = pasarguardLogin($normalizedUrl, $username, $password);
    if (empty($loginResult['status'])) {
        return [
            'status' => false,
            'msg' => $loginResult['msg'] ?? 'PasarGuard login failed'
        ];
    }
    $panel = [
        'url_panel' => $normalizedUrl,
        'username_panel' => $username,
        'password_panel' => $password,
        'pasarguard_auth_mode' => 'password',
    ];
    pasarguardStoreToken($normalizedUrl, $username, $loginResult['access_token']);
    $response = pasarguardRequest($panel, 'GET', '/api/admin');
    if (!empty($response['error'])) {
        return [
            'status' => false,
            'msg' => $response['error']
        ];
    }
    $httpStatus = $response['status'] ?? null;
    if ($httpStatus === null || $httpStatus >= 400) {
        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        $message = is_array($decoded) && isset($decoded['detail']) ? $decoded['detail'] : "HTTP {$httpStatus}";
        return [
            'status' => false,
            'msg' => is_array($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message
        ];
    }
    return [
        'status' => true,
        'msg' => 'PasarGuard connection succeeded',
        'data' => json_decode((string) ($response['body'] ?? ''), true)
    ];
}

function pasarguardRemoveUser($location, $username)
{
    $panel = findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return pasarguardRequest($panel, 'DELETE', '/api/user/' . $username);
}
