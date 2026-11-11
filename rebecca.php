<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
@chdir(__DIR__);

require_once 'config.php';
require_once 'function.php';
require_once 'request.php';
ini_set('error_log', 'error_log');

function rebeccaGetBaseUrl($panelUrl = null)
{
    return rtrim((string) $panelUrl, '/');
}

function getRebeccaPanelConfig($namePanel)
{
    $panel = select("marzban_panel", "*", "name_panel", $namePanel, "select");
    if (!$panel || !is_array($panel) || ($panel['type'] ?? null) !== "rebecca") {
        return [
            'status' => false,
            'msg' => 'Rebecca panel not found'
        ];
    }
    if (empty($panel['api_key'])) {
        return [
            'status' => false,
            'msg' => 'API key is not configured for this Rebecca panel'
        ];
    }
    $baseUrl = rebeccaGetBaseUrl($panel['url_panel'] ?? null);
    if ($baseUrl === '') {
        return [
            'status' => false,
            'msg' => 'Rebecca panel URL is not configured'
        ];
    }
    if (($panel['url_panel'] ?? '') !== $baseUrl) {
        update("marzban_panel", "url_panel", $baseUrl, "id", $panel['id']);
    }
    return [
        'status' => true,
        'panel' => array_merge($panel, ['url_panel' => $baseUrl]),
        'api_key' => $panel['api_key']
    ];
}

function rebeccaApiRequest(array $panelConfig, string $method, string $endpoint, $payload = null, bool $asJson = true)
{
    $panel = $panelConfig['panel'];
    $apiKey = $panelConfig['api_key'];
    $baseUrl = rebeccaGetBaseUrl($panel['url_panel'] ?? null);
    $url = rtrim($baseUrl, '/') . $endpoint;
    $request = new CurlRequest($url);
    $headers = [
        'accept: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];
    if ($payload !== null && $asJson) {
        $headers[] = 'Content-Type: application/json';
    }
    $request->setHeaders($headers);

    if ($asJson && $payload !== null && is_array($payload)) {
        $payload = json_encode($payload);
    }

    switch (strtoupper($method)) {
        case 'POST':
            $response = $request->post($payload);
            break;
        case 'PUT':
            $response = $request->put($payload);
            break;
        case 'PATCH':
            $response = $request->PATCH($payload);
            break;
        case 'DELETE':
            $response = $request->delete($payload);
            break;
        default:
            $response = $request->get();
            break;
    }

    return $response;
}

function rebeccaDecodeResponse(array $response, bool $strictJson = false)
{
    if (!empty($response['error'])) {
        return [
            'status' => false,
            'msg' => $response['error']
        ];
    }

    $statusCode = $response['status'] ?? null;
    $decodedBody = [];
    $jsonError = null;
    if (!empty($response['body'])) {
        $decoded = json_decode($response['body'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $decodedBody = $decoded;
        } else {
            $jsonError = json_last_error_msg();
        }
    }

    if ($statusCode !== null && $statusCode >= 400) {
        $message = '';
        if (is_array($decodedBody) && isset($decodedBody['message'])) {
            $message = $decodedBody['message'];
        } elseif (is_array($decodedBody) && isset($decodedBody['detail'])) {
            $detail = $decodedBody['detail'];
            $message = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : $detail;
        } else {
            $message = $statusCode;
        }
        return [
            'status' => false,
            'msg' => $message,
            'http_code' => (int) $statusCode
        ];
    }

    if ($strictJson && ($jsonError !== null || empty($response['body']) || !is_array($decodedBody))) {
        return [
            'status' => false,
            'msg' => $jsonError !== null ? 'Invalid JSON from panel: ' . $jsonError : 'Empty or non-object response from panel',
            'http_code' => $statusCode !== null ? (int) $statusCode : null,
            'invalid_response' => true
        ];
    }

    return [
        'status' => true,
        'data' => $decodedBody,
        'raw' => $response
    ];
}

function rebeccaTestConnection($baseUrl, $apiKey)
{
    $apiKey = trim((string) $apiKey);
    $normalizedBaseUrl = rebeccaGetBaseUrl($baseUrl);

    if ($apiKey === '') {
        return ['status' => false, 'msg' => 'Rebecca API key is missing'];
    }
    if ($normalizedBaseUrl === '') {
        return ['status' => false, 'msg' => 'Rebecca panel URL is missing'];
    }

    $panelConfig = [
        'status' => true,
        'panel' => [
            'type' => 'rebecca',
            'url_panel' => $normalizedBaseUrl,
            'api_key' => $apiKey,
        ],
        'api_key' => $apiKey,
    ];

    $response = rebeccaApiRequest($panelConfig, 'GET', '/api/system');
    $decoded = rebeccaDecodeResponse($response);

    if ($decoded['status'] === false) {
        $message = $decoded['msg'] ?? 'Unable to connect to Rebecca';
        if (!empty($response['status'])) {
            $message .= " (HTTP {$response['status']})";
        }
        return ['status' => false, 'msg' => $message, 'response' => $response];
    }

    return [
        'status' => true,
        'msg' => 'Rebecca connection succeeded',
        'data' => $decoded['data'],
        'response' => $response,
        'panel_config' => $panelConfig,
    ];
}

function rebeccaGetServices($namePanelOrConfig, $offset = null, $limit = null)
{
    $config = is_array($namePanelOrConfig) ? $namePanelOrConfig : getRebeccaPanelConfig($namePanelOrConfig);
    if ($config['status'] === false) {
        return $config;
    }
    $endpoint = '/api/v2/services';
    $paginated = $offset !== null && $limit !== null;
    if ($paginated) {
        $endpoint .= '?' . http_build_query(['offset' => max(0, intval($offset)), 'limit' => max(1, intval($limit))]);
    }
    $response = rebeccaApiRequest($config, 'GET', $endpoint);
    $decoded = rebeccaDecodeResponse($response, $paginated);
    if ($decoded['status'] === false) {
        return $decoded;
    }
    $services = $decoded['data'];
    if (isset($services['services']) && is_array($services['services'])) {
        $services = $services['services'];
    } elseif (isset($services['items']) && is_array($services['items'])) {
        $services = $services['items'];
    }
    if (!is_array($services) || ($paginated && !array_is_list($services))) {
        return ['status' => false, 'msg' => 'Invalid services response from Rebecca'];
    }
    return ['status' => true, 'services' => $services];
}

function rebeccaGetServicePage($namePanelOrConfig, $page = 1, $perPage = 5)
{
    $page = max(1, intval($page));
    $perPage = max(1, intval($perPage));
    $offset = ($page - 1) * $perPage;
    $config = is_array($namePanelOrConfig) ? $namePanelOrConfig : getRebeccaPanelConfig($namePanelOrConfig);
    $result = rebeccaGetServices($config, $offset, $perPage);
    if ($result['status'] === false) {
        return $result;
    }
    $hasNext = false;
    if (count($result['services']) === $perPage) {
        $next = rebeccaGetServices($config, $offset + $perPage, 1);
        if ($next['status'] === false) {
            return $next;
        }
        $hasNext = count($next['services']) > 0;
    }
    return array_merge($result, ['page' => $page, 'has_next' => $hasNext]);
}

function rebeccaServiceLabel(array $service)
{
    $idValue = $service['id'] ?? ($service['service_id'] ?? '?');
    $id = is_numeric($idValue) ? intval($idValue) : $idValue;
    foreach (['name', 'title', 'remark'] as $key) {
        if (!empty($service[$key]) && is_string($service[$key])) {
            $label = trim($service[$key]);
            if ($label !== '') {
                return $label;
            }
        }
    }
    return "service-{$id}";
}

function rebeccaUsableServices(array $services)
{
    $usable = [];
    foreach ($services as $serviceRow) {
        if (!is_array($serviceRow) || !isset($serviceRow['id']) || !is_numeric($serviceRow['id']) || intval($serviceRow['id']) <= 0) {
            continue;
        }
        $usable[intval($serviceRow['id'])] = $serviceRow;
    }
    return array_values($usable);
}

function rebeccaBuildServiceKeyboard(array $services, $currentServiceId, $page = 1, $perPage = 5, $hasNext = null)
{
    $usable = rebeccaUsableServices($services);
    $total = count($usable);
    if ($total === 0 && ($hasNext === null || intval($page) <= 1)) {
        return null;
    }
    $perPage = max(1, intval($perPage));
    $pages = $hasNext === null ? (int) ceil($total / $perPage) : null;
    $page = max(1, intval($page));
    if ($pages !== null) {
        $page = min($page, $pages);
    }
    $currentServiceId = trim((string) $currentServiceId);
    $rows = [];
    $visible = $hasNext === null ? array_slice($usable, ($page - 1) * $perPage, $perPage) : $usable;
    foreach ($visible as $serviceRow) {
        $serviceId = (string) intval($serviceRow['id']);
        $checkIcon = ($currentServiceId === $serviceId) ? '✅' : '❌';
        $callback = 'rebeccasetservice#' . $serviceId;
        if ($hasNext !== null || $pages > 1) {
            $callback .= '#' . $page;
        }
        $rows[] = [[
            'text' => rebeccaServiceLabel($serviceRow) . " {$checkIcon}",
            'callback_data' => $callback,
        ]];
    }
    if ($hasNext !== null || $pages > 1) {
        $nav = [];
        if ($page > 1) {
            $nav[] = ['text' => '◀️ قبلی', 'callback_data' => 'rebeccaservicepage#' . ($page - 1)];
        }
        $nav[] = ['text' => $pages === null ? (string) $page : "{$page} / {$pages}", 'callback_data' => 'rebeccaservicenoop'];
        if ($hasNext === null ? $page < $pages : $hasNext) {
            $nav[] = ['text' => 'بعدی ▶️', 'callback_data' => 'rebeccaservicepage#' . ($page + 1)];
        }
        $rows[] = $nav;
    }
    return [
        'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE),
        'page' => $page,
        'pages' => $pages,
        'ids' => array_map(function ($row) {
            return (string) intval($row['id']);
        }, $usable),
    ];
}

function rebeccaResolveServiceId($namePanel, $serviceValue = null)
{
    $serviceValue = is_string($serviceValue) ? trim($serviceValue) : $serviceValue;
    if (is_numeric($serviceValue) && intval($serviceValue) > 0) {
        return ['status' => true, 'service_id' => intval($serviceValue)];
    }
    $servicesResponse = rebeccaGetServices($namePanel);
    if ($servicesResponse['status'] === false) {
        return $servicesResponse;
    }
    $services = $servicesResponse['services'];
    if (empty($services) || !isset($services[0]['id'])) {
        return ['status' => false, 'msg' => 'No services available on Rebecca panel'];
    }
    return ['status' => true, 'service_id' => intval($services[0]['id'])];
}

function rebeccaNormalizeExpire($timestamp)
{
    $timestamp = intval($timestamp);
    if ($timestamp <= 0) {
        return time() + (86400 * 365 * 10);
    }
    if ($timestamp <= time()) {
        return time() + 300;
    }
    return $timestamp;
}

function rebeccaCreateUser($namePanel, string $username, int $dataLimitBytes, int $expireTs, $serviceValue, $note = '', int $ipLimit = 0, ?int $onHoldExpireDurationSeconds = null)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $serviceResult = rebeccaResolveServiceId($namePanel, $serviceValue);
    if ($serviceResult['status'] === false) {
        return $serviceResult;
    }
    $payload = [
        "username" => $username,
        "data_limit" => $dataLimitBytes,
        "service_id" => $serviceResult['service_id'],
        "note" => (string) $note,
        "ip_limit" => max(0, $ipLimit),
    ];
    if ($onHoldExpireDurationSeconds !== null && $onHoldExpireDurationSeconds > 0) {
        $payload["status"] = "on_hold";
        $payload["on_hold_expire_duration"] = $onHoldExpireDurationSeconds;
    } else {
        $payload["expire"] = rebeccaNormalizeExpire($expireTs);
    }
    $response = rebeccaApiRequest($config, 'POST', '/api/v2/users', $payload);
    return rebeccaDecodeResponse($response);
}

function rebeccaGetUser($namePanel, string $username)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $encodedUsername = urlencode($username);
    $response = rebeccaApiRequest($config, 'GET', "/api/user/{$encodedUsername}");
    return rebeccaDecodeResponse($response, true);
}

function rebeccaGetOnHoldUsers($namePanel)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $response = rebeccaApiRequest($config, 'GET', '/api/users?status=on_hold&limit=200');
    $decoded = rebeccaDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }
    $users = is_array($decoded['data']['users'] ?? null) ? $decoded['data']['users'] : [];
    return ['status' => true, 'users' => $users];
}

function rebeccaUpdateUser($namePanel, string $username, array $payload)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    if (isset($payload['expire'])) {
        $payload['expire'] = rebeccaNormalizeExpire($payload['expire']);
    }
    $encodedUsername = urlencode($username);
    $response = rebeccaApiRequest($config, 'PUT', "/api/v2/users/{$encodedUsername}", $payload);
    return rebeccaDecodeResponse($response);
}

function rebeccaRemoveUser($namePanel, string $username)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $encodedUsername = urlencode($username);
    $response = rebeccaApiRequest($config, 'DELETE', "/api/user/{$encodedUsername}");
    return rebeccaDecodeResponse($response);
}

function rebeccaGetUserUsage($namePanel, string $username)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $encodedUsername = urlencode($username);
    $response = rebeccaApiRequest($config, 'GET', "/api/user/{$encodedUsername}/usage");
    return rebeccaDecodeResponse($response);
}

function rebeccaResetUsage($namePanel, string $username)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $encodedUsername = urlencode($username);
    $response = rebeccaApiRequest($config, 'POST', "/api/user/{$encodedUsername}/reset");
    return rebeccaDecodeResponse($response);
}

function rebeccaRevokeSub($namePanel, string $username)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $encodedUsername = urlencode($username);
    $response = rebeccaApiRequest($config, 'POST', "/api/user/{$encodedUsername}/revoke_sub");
    return rebeccaDecodeResponse($response);
}

function rebeccaChangeStatus($namePanel, string $username, bool $enable)
{
    $status = $enable ? 'active' : 'disabled';
    return rebeccaUpdateUser($namePanel, $username, ['status' => $status]);
}

function rebeccaSystemStats($namePanel)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $response = rebeccaApiRequest($config, 'GET', '/api/system');
    return rebeccaDecodeResponse($response);
}

function rebeccaListNodes($namePanel)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $response = rebeccaApiRequest($config, 'GET', '/api/nodes');
    return rebeccaDecodeResponse($response);
}

function rebeccaGetSubInfo($namePanel, string $username, string $credentialKey)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $encodedUsername = urlencode($username);
    $encodedKey = urlencode($credentialKey);
    $response = rebeccaApiRequest($config, 'GET', "/sub/{$encodedUsername}/{$encodedKey}/info");
    return rebeccaDecodeResponse($response, true);
}

function rebeccaFormatConnectionInfoBlock(string $title, array $fields)
{
    $lines = [$title];
    foreach ($fields as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $lines[] = "{$label}: {$value}";
    }
    return implode("\n", $lines);
}

function rebeccaBuildExtraProtocolLinks(array $subInfo)
{
    $links = [];

    $anyconnect = is_array($subInfo['anyconnect'] ?? null) ? $subInfo['anyconnect'] : [];
    foreach ($anyconnect as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $remark = (string) ($entry['remark'] ?? 'AnyConnect');
        $links[] = rebeccaFormatConnectionInfoBlock($remark, [
            'Protocol' => 'AnyConnect',
            'Server' => $entry['address'] ?? ($entry['server'] ?? ''),
            'Port' => $entry['port'] ?? '',
            'Username' => $entry['username'] ?? '',
            'Password' => $entry['password'] ?? '',
        ]);
    }

    $l2tp = is_array($subInfo['l2tp'] ?? null) ? $subInfo['l2tp'] : [];
    foreach ($l2tp as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $remark = (string) ($entry['remark'] ?? 'L2TP/IPsec');
        $links[] = rebeccaFormatConnectionInfoBlock($remark, [
            'Protocol' => 'L2TP/IPsec',
            'Server' => $entry['address'] ?? ($entry['server'] ?? ''),
            'Port' => $entry['port'] ?? '',
            'Username' => $entry['username'] ?? '',
            'Password' => $entry['password'] ?? '',
            'IPsec PSK' => $entry['ipsec_psk'] ?? '',
        ]);
    }

    $ikev2 = is_array($subInfo['ikev2'] ?? null) ? $subInfo['ikev2'] : [];
    foreach ($ikev2 as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $remark = (string) ($entry['remark'] ?? 'IKEv2');
        $links[] = rebeccaFormatConnectionInfoBlock($remark, [
            'Protocol' => 'IKEv2',
            'Server' => $entry['address'] ?? ($entry['server'] ?? ''),
            'Port' => $entry['port'] ?? '',
            'Username' => $entry['username'] ?? '',
            'Password' => $entry['password'] ?? '',
        ]);
    }

    $pptp = is_array($subInfo['pptp'] ?? null) ? $subInfo['pptp'] : [];
    foreach ($pptp as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $remark = (string) ($entry['remark'] ?? 'PPTP');
        $links[] = rebeccaFormatConnectionInfoBlock($remark, [
            'Protocol' => 'PPTP',
            'Server' => $entry['address'] ?? ($entry['server'] ?? ''),
            'Port' => $entry['port'] ?? '',
            'Username' => $entry['username'] ?? '',
            'Password' => $entry['password'] ?? '',
        ]);
    }

    $openvpn = is_array($subInfo['openvpn'] ?? null) ? $subInfo['openvpn'] : [];
    $ovpnProfiles = is_array($openvpn['profiles'] ?? null) ? $openvpn['profiles'] : [];
    foreach ($ovpnProfiles as $profile) {
        if (!is_array($profile)) {
            continue;
        }
        $downloadUrl = (string) ($profile['download_url'] ?? '');
        if ($downloadUrl === '') {
            continue;
        }
        $remark = (string) ($profile['remark'] ?? 'OpenVPN');
        $links[] = $downloadUrl . '#' . rawurlencode($remark);
    }

    $wireguard = is_array($subInfo['wireguard'] ?? null) ? $subInfo['wireguard'] : [];
    $wgProfiles = is_array($wireguard['profiles'] ?? null) ? $wireguard['profiles'] : [];
    $wgDownloadUrls = [];
    foreach ($wgProfiles as $profile) {
        if (!is_array($profile)) {
            continue;
        }
        $downloadUrl = (string) ($profile['download_url'] ?? '');
        if ($downloadUrl === '') {
            continue;
        }
        $remark = (string) ($profile['remark'] ?? 'WireGuard');
        $wgDownloadUrls[] = $downloadUrl . '#' . rawurlencode($remark);
    }

    return ['links' => $links, 'wireguard_download_links' => $wgDownloadUrls];
}

function rebeccaBuildDisplayLinks($namePanel, array $userData, string $username)
{
    $baseLinks = is_array($userData['links'] ?? null) ? $userData['links'] : [];
    $taggedLinks = [];
    foreach ($baseLinks as $rawLink) {
        if (!is_string($rawLink) || trim($rawLink) === '') {
            continue;
        }
        if (stripos($rawLink, 'wireguard://') === 0) {
            continue;
        }
        $taggedLinks[] = $rawLink;
    }

    $credentialKey = (string) ($userData['credential_key'] ?? '');
    if ($credentialKey === '') {
        $usernameKeyPath = (string) ($userData['subscription_urls']['username-key'] ?? '');
        if ($usernameKeyPath !== '' && preg_match('#/sub/[^/]+/([a-zA-Z0-9]+)#', $usernameKeyPath, $m)) {
            $credentialKey = $m[1];
        }
    }
    if ($credentialKey === '') {
        return $taggedLinks;
    }

    $subInfoResponse = rebeccaGetSubInfo($namePanel, $username, $credentialKey);
    if (($subInfoResponse['status'] ?? false) === false) {
        return $taggedLinks;
    }
    $subInfo = $subInfoResponse['data'] ?? null;
    if (!is_array($subInfo)) {
        return $taggedLinks;
    }

    $extra = rebeccaBuildExtraProtocolLinks($subInfo);
    $merged = array_merge($taggedLinks, $extra['wireguard_download_links'], $extra['links']);
    return $merged;
}

function rebeccaConfigDownloadInfo(string $link)
{
    if (!preg_match('/^https?:\/\//i', $link)) {
        return null;
    }
    $urlOnly = strtok($link, '#');
    $path = parse_url($urlOnly, PHP_URL_PATH) ?: '';
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    if (preg_match('#/(?:wg|wireguard)(?:/|$)#i', $path)) {
        $kind = 'wireguard';
        $ext = '.conf';
    } elseif (preg_match('#/(?:ov|openvpn)(?:/|$)#i', $path) || $extension === 'ovpn') {
        $kind = 'openvpn';
        $ext = '.ovpn';
    } else {
        return null;
    }
    $basename = basename(parse_url($urlOnly, PHP_URL_PATH) ?: '');
    if ($basename === '' || strtolower(substr($basename, -strlen($ext))) !== strtolower($ext)) {
        $basename = $kind . $ext;
    }
    return ['url' => $urlOnly, 'kind' => $kind, 'filename' => $basename];
}

function rebeccaDownloadConfigFile(string $url, string $destPath)
{
    $ch = curl_init($url);
    $fp = @fopen($destPath, 'wb');
    if ($fp === false) {
        curl_close($ch);
        return false;
    }
    $timeoutInfo = faoxima_panel_timeout_info();
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => (int) $timeoutInfo['timeout'],
        CURLOPT_CONNECTTIMEOUT => (int) $timeoutInfo['connect_timeout'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$success || $httpCode >= 400 || (int) @filesize($destPath) === 0) {
        @unlink($destPath);
        return false;
    }
    return true;
}

function rebeccaAccessInsightsPermissionStatus($namePanel)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return ['status' => false, 'reason' => 'config_error', 'msg' => $config['msg'] ?? ''];
    }
    $response = rebeccaApiRequest($config, 'GET', '/api/core/access/insights');
    $httpStatus = $response['status'] ?? null;
    if ($httpStatus === 403) {
        return ['status' => false, 'reason' => 'forbidden', 'msg' => "You're not allowed"];
    }
    $decoded = rebeccaDecodeResponse($response);
    if ($decoded['status'] === false) {
        return ['status' => false, 'reason' => 'error', 'msg' => $decoded['msg'] ?? ''];
    }
    return ['status' => true, 'reason' => 'ok', 'msg' => ''];
}

function rebeccaClientIpSummary($namePanel, string $username)
{
    $config = getRebeccaPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $response = rebeccaApiRequest($config, 'GET', '/api/core/access/insights');
    $decoded = rebeccaDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }
    $items = is_array($decoded['data']['items'] ?? null) ? $decoded['data']['items'] : [];
    $rawIps = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemUserKey = (string) ($item['user_key'] ?? '');
        $itemUserLabel = (string) ($item['user_label'] ?? '');
        if ($itemUserKey !== $username && $itemUserLabel !== $username) {
            continue;
        }
        $sources = is_array($item['sources'] ?? null) ? $item['sources'] : [];
        foreach ($sources as $ip) {
            if (is_string($ip) && $ip !== '' && !in_array($ip, $rawIps, true)) {
                $rawIps[] = $ip;
            }
        }
    }
    return ['status' => true, 'count' => count($rawIps), 'raw' => $rawIps];
}

function rebeccaCapabilityPermissions(array $admin)
{
    $defaults = [
        'standard' => ['create' => true, 'delete' => false, 'reset_usage' => false, 'revoke' => true],
        'reseller' => ['create' => true, 'delete' => false, 'reset_usage' => false, 'revoke' => true],
        'sudo' => ['create' => true, 'delete' => false, 'reset_usage' => false, 'revoke' => true],
        'full_access' => ['create' => true, 'delete' => true, 'reset_usage' => true, 'revoke' => true],
    ];
    $role = is_string($admin['role'] ?? null) ? strtolower($admin['role']) : null;
    $users = is_array($admin['permissions']['users'] ?? null) ? $admin['permissions']['users'] : null;
    if ($users === null && $role !== null && isset($defaults[$role])) {
        return ['source' => 'role_default', 'role' => $role, 'users' => $defaults[$role]];
    }
    if ($users !== null) {
        return ['source' => 'permissions', 'role' => $role, 'users' => $users];
    }
    if (array_key_exists('is_sudo', $admin)) {
        return ['source' => 'legacy', 'role' => !empty($admin['is_sudo']) ? 'sudo' : 'admin', 'users' => null];
    }
    return ['source' => 'unknown', 'role' => $role, 'users' => null];
}

function rebeccaCapabilityReport(array $panel, array &$report)
{
    $apiKey = trim((string) ($panel['api_key'] ?? ''));
    $baseUrl = rebeccaGetBaseUrl($panel['url_panel'] ?? null);
    if ($apiKey === '' || $baseUrl === '') {
        rx_cap_set($report, 'connection', 'failed', $apiKey === '' ? 'کلید API برای این پنل تنظیم نشده است.' : 'آدرس پنل تنظیم نشده است.');
        rx_cap_mark_untested_after_connection_failure($report);
        return;
    }
    $config = ['status' => true, 'panel' => array_merge($panel, ['url_panel' => $baseUrl]), 'api_key' => $apiKey];
    $adminResponse = rebeccaApiRequest($config, 'GET', '/api/admin');
    $adminStatus = (int) ($adminResponse['status'] ?? 0);
    $admin = rx_cap_json($adminResponse);
    $adminAvailable = empty($adminResponse['error']) && $adminStatus >= 200 && $adminStatus < 300 && $admin !== null;
    if (!$adminAvailable && empty($adminResponse['error']) && in_array($adminStatus, [404, 405], true)) {
        $systemResponse = rebeccaApiRequest($config, 'GET', '/api/system');
        $systemStatus = (int) ($systemResponse['status'] ?? 0);
        if (empty($systemResponse['error']) && $systemStatus >= 200 && $systemStatus < 300) {
            rx_cap_set($report, 'connection', 'verified', 'GET /api/system موفق بود؛ /api/admin در این نسخه در دسترس نیست.');
            rx_cap_set_writes($report, 'unknown', 'این نسخه اطلاعات نقش و مجوز ادمین را ارائه نمی‌دهد.');
            $admin = [];
        } else {
            $adminResponse = $systemResponse;
            $adminStatus = $systemStatus;
        }
    }
    if (!$adminAvailable && $admin !== []) {
        $note = !empty($adminResponse['error'])
            ? rx_cap_clean_text((string) $adminResponse['error'], 160)
            : ($admin === null && $adminStatus >= 200 && $adminStatus < 300 ? 'پاسخ /api/admin JSON معتبر نبود.' : rx_cap_http_read($adminResponse)[1]);
        rx_cap_set($report, 'connection', 'failed', $note);
        rx_cap_mark_untested_after_connection_failure($report);
        return;
    }
    if ($adminAvailable) {
        rx_cap_set($report, 'connection', 'verified', 'GET /api/admin موفق بود.');
        rx_cap_info($report, 'ادمین متصل', (string) ($admin['username'] ?? ''));
        $perm = rebeccaCapabilityPermissions($admin);
        rx_cap_info($report, 'نقش ادمین', $perm['role']);
        if ($perm['source'] === 'legacy') {
            rx_cap_apply_marzban_family($report, $admin);
        } elseif ($perm['users'] === null) {
            rx_cap_set_writes($report, 'unknown', 'پاسخ /api/admin شامل اطلاعات نقش یا مجوز نیست.');
        } else {
            $source = $perm['source'] === 'role_default' ? 'بر اساس پیش‌فرض نقش ' . $perm['role'] : 'بر اساس permissions.users';
            $map = ['create' => 'create', 'delete' => 'delete', 'reset' => 'reset_usage', 'revoke' => 'revoke'];
            foreach ($map as $key => $field) {
                $value = $perm['users'][$field] ?? null;
                if ($value === true) {
                    rx_cap_set($report, $key, 'allowed', $source . '.');
                } elseif ($value === false) {
                    rx_cap_set($report, $key, 'denied', $source . '.');
                } else {
                    rx_cap_set($report, $key, 'unknown', 'فیلد users.' . $field . ' در پاسخ پنل نیست.');
                }
            }
            rx_cap_set($report, 'update', 'unknown', 'Rebecca مجوز جداگانه‌ای برای ویرایش/تمدید کاربر اعلام نمی‌کند.');
        }
    }
    $usersLimit = $admin['users_limit'] ?? null;
    if ($usersLimit !== null) {
        rx_cap_info($report, 'سقف تعداد کاربر', (int) $usersLimit);
    }
    $status = $admin['status'] ?? null;
    if (is_string($status) && $status !== '' && $status !== 'active') {
        rx_cap_info($report, 'وضعیت ادمین', $status);
        if ($report['checks']['create']['status'] === 'allowed') {
            rx_cap_set($report, 'create', 'unknown', 'مجوز ساخت اعلام شده، اما وضعیت ادمین «' . $status . '» است و ممکن است ساخت کاربر مسدود باشد.');
        }
    }

    $listResponse = rebeccaApiRequest($config, 'GET', '/api/users?limit=1');
    $list = rx_cap_http_read($listResponse);
    if ($list[0] === 'verified') {
        $listData = rx_cap_json($listResponse);
        $total = is_array($listData) && isset($listData['total']) ? (int) $listData['total'] : null;
        $list[1] = 'GET /api/users موفق بود' . ($total !== null ? '؛ تعداد قابل مشاهده: ' . $total : '') . '.';
    }
    rx_cap_set($report, 'list_users', $list[0], $list[1]);

    rx_cap_probe_sample_user_via_manager($panel, $report);

    $services = rebeccaGetServices($config);
    if (($services['status'] ?? false) === false) {
        $message = rx_cap_clean_text((string) ($services['msg'] ?? ''), 120);
        rx_cap_set($report, 'resources', preg_match('/\b403\b|not allowed/i', $message) ? 'denied' : 'failed', 'GET /api/v2/services: ' . $message);
    } else {
        $ids = [];
        foreach ($services['services'] as $service) {
            if (is_array($service) && isset($service['id'])) {
                $ids[] = (string) intval($service['id']);
            }
        }
        $configured = trim((string) ($panel['rebecca_service_id'] ?? ''));
        $note = count($ids) . ' سرویس قابل مشاهده.';
        $ok = true;
        if ($configured !== '') {
            $ok = in_array($configured, $ids, true);
            $note .= $ok ? ' سرویس پیش‌فرض تنظیم‌شده موجود است.' : ' سرویس پیش‌فرض تنظیم‌شده (' . $configured . ') در دسترس نیست.';
        }
        rx_cap_set($report, 'resources', $ok ? 'verified' : 'failed', $note);
    }
}
