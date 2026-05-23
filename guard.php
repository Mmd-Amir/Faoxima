<?php
require_once 'config.php';
require_once 'function.php';
require_once 'request.php';
ini_set('error_log', 'error_log');

if (!defined('GUARD_CORE_BASE_URL')) {
    define('GUARD_CORE_BASE_URL', 'https://core.erfjab.com');
}

function guardGetBaseUrl($panelUrl = null)
{
    $normalizedUrl = trim((string) $panelUrl);
    if ($normalizedUrl === '' || $normalizedUrl === '/') {
        return GUARD_CORE_BASE_URL;
    }
    return rtrim($normalizedUrl, '/');
}

function getGuardPanelConfig($namePanel)
{
    $panel = select("marzban_panel", "*", "name_panel", $namePanel, "select");
    if (!$panel || !is_array($panel) || ($panel['type'] ?? null) !== "guard") {
        return [
            'status' => false,
            'msg' => 'Guard panel not found'
        ];
    }
    if (empty($panel['api_key']) && empty($panel['password_panel'])) {
        return [
            'status' => false,
            'msg' => 'API key is not configured for this Guard panel'
        ];
    }

    $normalizedUrl = guardGetBaseUrl($panel['url_panel'] ?? null);
    if (($panel['url_panel'] ?? '') !== $normalizedUrl) {
        update("marzban_panel", "url_panel", $normalizedUrl, "id", $panel['id']);
    }

    return [
        'status' => true,
        'panel' => array_merge($panel, ['url_panel' => guardGetBaseUrl($panel['url_panel'] ?? null)]),
        'api_key' => !empty($panel['api_key']) ? $panel['api_key'] : $panel['password_panel']
    ];
}

function guardApiRequest(array $panelConfig, string $method, string $endpoint, $payload = null, bool $asJson = true)
{
    $panel = $panelConfig['panel'];
    $apiKey = $panelConfig['api_key'];
    $baseUrl = guardGetBaseUrl($panel['url_panel'] ?? null);
    $url = rtrim($baseUrl, '/') . $endpoint;
    $request = new CurlRequest($url);
    $headers = [
        'accept: application/json',
        'X-API-Key: ' . $apiKey,
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
            return $request->post($payload);
        case 'PUT':
            return $request->put($payload);
        case 'PATCH':
            return $request->PATCH($payload);
        case 'DELETE':
            return $request->delete($payload);
        default:
            return $request->get();
    }
}

function guardDecodeResponse(array $response)
{
    if (!empty($response['error'])) {
        return [
            'status' => false,
            'msg' => $response['error']
        ];
    }

    $statusCode = $response['status'] ?? null;
    $decodedBody = [];
    if (!empty($response['body'])) {
        $decoded = json_decode($response['body'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $decodedBody = $decoded;
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
            'msg' => $message
        ];
    }

    return [
        'status' => true,
        'data' => $decodedBody,
        'raw' => $response
    ];
}

function guardNormalizeSubscriptionEntry(array $panelConfig, array $subscription)
{
    $baseUrl = guardGetBaseUrl($panelConfig['panel']['url_panel'] ?? null);

    $tag = isset($subscription['tag']) ? trim((string) $subscription['tag']) : '';
    $accessKey = isset($subscription['access_key']) ? trim((string) $subscription['access_key']) : '';
    if ($accessKey === '' && isset($subscription['access_key_secret'])) {
        $accessKey = trim((string) $subscription['access_key_secret']);
    }

    $existingUrl = '';
    foreach (['subscription_url', 'subscription', 'link'] as $key) {
        if (!empty($subscription[$key])) {
            $existingUrl = trim((string) $subscription[$key]);
            break;
        }
    }

    $normalizedExisting = '';
    if ($existingUrl !== '') {
        if (preg_match('/^https?:\/\//i', $existingUrl)) {
            $normalizedExisting = $existingUrl;
        } else {
            $normalizedExisting = rtrim($baseUrl, '/') . '/' . ltrim($existingUrl, '/');
        }
    }

    $computedUrl = '';
    if ($tag !== '' && $accessKey !== '') {
        $computedUrl = rtrim($baseUrl, '/') . '/' . rawurlencode($tag) . '/' . rawurlencode($accessKey);
    }

    $finalUrl = $computedUrl !== '' ? $computedUrl : $normalizedExisting;
    if ($finalUrl !== '') {
        $subscription['subscription_url'] = $finalUrl;
        $subscription['subscription'] = $finalUrl;
    }

    return $subscription;
}

function guardNormalizeSubscriptionItems(array $panelConfig, $data)
{
    if (!is_array($data)) {
        return $data;
    }

    $isSubscription = isset($data['subscription']) || isset($data['subscription_url']) || isset($data['tag']) || isset($data['access_key']) || isset($data['link']);
    if ($isSubscription) {
        return guardNormalizeSubscriptionEntry($panelConfig, $data);
    }

    foreach (['data', 'subscription', 'subscriptions'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            $data[$key] = guardNormalizeSubscriptionItems($panelConfig, $data[$key]);
        }
    }

    $isList = array_keys($data) === range(0, count($data) - 1);
    if ($isList) {
        foreach ($data as $index => $item) {
            if (is_array($item)) {
                $data[$index] = guardNormalizeSubscriptionItems($panelConfig, $item);
            }
        }
    }

    return $data;
}

function guardExtractAdminName($adminData)
{
    if (!is_array($adminData)) {
        return '';
    }
    foreach (['name', 'username', 'email', 'id'] as $key) {
        if (!empty($adminData[$key])) {
            return (string) $adminData[$key];
        }
    }
    return '';
}

function guardTestConnection($baseUrl, $apiKey)
{
    $apiKey = trim((string) $apiKey);
    $normalizedBaseUrl = guardGetBaseUrl($baseUrl);

    if ($apiKey === '') {
        return [
            'status' => false,
            'msg' => 'Guard API key is missing'
        ];
    }

    $panelConfig = [
        'status' => true,
        'panel' => [
            'type' => 'guard',
            'url_panel' => $normalizedBaseUrl,
            'api_key' => $apiKey,
            'password_panel' => null,
        ],
        'api_key' => $apiKey,
    ];

    $response = guardApiRequest($panelConfig, 'GET', '/api/admins/current');
    $decoded = guardDecodeResponse($response);

    if ($decoded['status'] === false) {
        $message = $decoded['msg'] ?? 'Unable to connect to Guard';
        if (!empty($response['status'])) {
            $message .= " (HTTP {$response['status']})";
        }
        return [
            'status' => false,
            'msg' => $message,
            'response' => $response
        ];
    }

    return [
        'status' => true,
        'msg' => 'Guard connection succeeded',
        'data' => $decoded['data'],
        'response' => $response,
        'panel_config' => $panelConfig,
    ];
}

function guardGetServices($namePanelOrConfig)
{
    if (is_array($namePanelOrConfig)) {
        $config = $namePanelOrConfig;
    } else {
        $config = getGuardPanelConfig($namePanelOrConfig);
    }
    if ($config['status'] === false) {
        return $config;
    }
    $response = guardApiRequest($config, 'GET', '/api/services');
    $decoded = guardDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }
    $services = $decoded['data'];
    if (isset($services['services']) && is_array($services['services'])) {
        $services = $services['services'];
    }
    if (!is_array($services)) {
        return [
            'status' => false,
            'msg' => 'Invalid services response from Guard'
        ];
    }
    return [
        'status' => true,
        'services' => $services
    ];
}

function guardServiceLabel(array $service)
{
    $idValue = isset($service['id']) ? $service['id'] : '?';
    $id = is_numeric($idValue) ? intval($idValue) : $idValue;

    foreach (['remark', 'name', 'title'] as $key) {
        if (!empty($service[$key]) && is_string($service[$key])) {
            $label = trim($service[$key]);
            if ($label !== '') {
                return $label;
            }
        }
    }

    return "service-{$id}";
}

function guardNormalizeExpire($timestamp)
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

function guardParseServiceIds($serviceValue)
{
    if (is_array($serviceValue)) {
        return $serviceValue;
    }
    if ($serviceValue === null || $serviceValue === '' || $serviceValue === false) {
        return [];
    }
    if (is_string($serviceValue)) {
        $serviceValue = trim($serviceValue);
        if (in_array(strtolower($serviceValue), ['all', '0'], true)) {
            return ['all'];
        }
        $decoded = json_decode($serviceValue, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        if (strpos($serviceValue, ',') !== false) {
            $parts = array_map('trim', explode(',', $serviceValue));
            $ids = [];
            foreach ($parts as $part) {
                if (ctype_digit($part)) {
                    $ids[] = intval($part);
                }
            }
            return $ids;
        }
        if (ctype_digit($serviceValue)) {
            return [intval($serviceValue)];
        }
    }
    return [];
}

function guardExtractServiceIdsFromList(array $services)
{
    $serviceIds = [];
    foreach ($services as $service) {
        if (isset($service['id'])) {
            $serviceIds[] = intval($service['id']);
        }
    }
    return $serviceIds;
}

function guardResolveServiceIds($namePanel, $serviceValue = null)
{
    $parsedServices = guardParseServiceIds($serviceValue);
    $needsAll = empty($parsedServices) || in_array('all', $parsedServices, true) || in_array(0, $parsedServices, true);
    if ($needsAll) {
        $servicesResponse = guardGetServices($namePanel);
        if ($servicesResponse['status'] === false) {
            return $servicesResponse;
        }
        $services = $servicesResponse['services'];
        $serviceIds = [];
        foreach ($services as $service) {
            if (isset($service['id'])) {
                $serviceIds[] = intval($service['id']);
            }
        }
        if (empty($serviceIds)) {
            return [
                'status' => false,
                'msg' => 'No services available on Guard panel'
            ];
        }
        return [
            'status' => true,
            'service_ids' => $serviceIds
        ];
    }

    $serviceIds = array_values(array_unique(array_map('intval', $parsedServices)));
    if (empty($serviceIds)) {
        return [
            'status' => false,
            'msg' => 'No valid service id provided'
        ];
    }

    return [
        'status' => true,
        'service_ids' => $serviceIds
    ];
}

function guardDecodeAutoRenewalsConfig($value)
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $value = $decoded;
        }
    }
    if (!is_array($value)) {
        return [];
    }
    $isList = array_keys($value) === range(0, count($value) - 1);
    if (!$isList) {
        $value = [$value];
    }
    $normalized = [];
    foreach ($value as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $normalized[] = [
            'expire_days' => isset($entry['expire_days']) ? intval($entry['expire_days']) : 0,
            'usage_gb' => isset($entry['usage_gb']) ? floatval($entry['usage_gb']) : 0,
            'reset_usage' => !empty($entry['reset_usage']) || (!empty($entry['reset']) && $entry['reset'] === true),
        ];
    }
    return $normalized;
}

function guardBuildAutoRenewalsPayload(array $entries)
{
    $payload = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $expireDays = max(0, intval($entry['expire_days'] ?? 0));
        $usageGb = isset($entry['usage_gb']) ? floatval($entry['usage_gb']) : 0;
        $resetUsage = !empty($entry['reset_usage']);

        $limitExpire = 0;
        if ($expireDays > 0) {
            $limitExpire = guardNormalizeExpire(time() + ($expireDays * 86400));
        }
        $limitUsage = $usageGb > 0 ? intval(round($usageGb * 1024 * 1024 * 1024)) : 0;

        $payload[] = [
            "limit_expire" => $limitExpire,
            "limit_usage" => $limitUsage,
            "reset_usage" => $resetUsage
        ];
    }
    return $payload;
}

function guardParseAutoRenewalsInput($input)
{
    $input = trim((string) $input);
    if ($input === '' || $input === '-' || $input === '0') {
        return [
            'status' => true,
            'entries' => []
        ];
    }

    $lines = preg_split('/[\n;]+/', $input);
    $entries = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode(',', $line));
        if (count($parts) !== 3) {
            return [
                'status' => false,
                'msg' => 'Invalid auto renewal format'
            ];
        }
        [$expireDays, $usageGb, $resetFlag] = $parts;
        if ($expireDays === '' || !is_numeric($expireDays) || $usageGb === '' || !is_numeric($usageGb)) {
            return [
                'status' => false,
                'msg' => 'Invalid numbers in auto renewal'
            ];
        }
        $resetUsage = in_array(strtolower($resetFlag), ['1', 'true', 'yes'], true);
        $entries[] = [
            'expire_days' => intval($expireDays),
            'usage_gb' => floatval($usageGb),
            'reset_usage' => $resetUsage,
        ];
    }

    return [
        'status' => true,
        'entries' => $entries,
    ];
}

function guardCreateSubscription($namePanel, array $payload)
{
    $config = getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }

    if (isset($payload['username'])) {
        $payload = [$payload];
    }

    foreach ($payload as &$subscription) {
        if (isset($subscription['limit_expire'])) {
            $limitExpire = $subscription['limit_expire'];
            if (is_numeric($limitExpire)) {
                $limitExpire = intval($limitExpire);
                if ($limitExpire > 0 && $limitExpire < 315576000) {
                    $limitExpire = time() + ($limitExpire * 86400);
                }
            }
            $subscription['limit_expire'] = guardNormalizeExpire($limitExpire);
        }

        $parsedServices = guardParseServiceIds($subscription['service_ids'] ?? null);
        if (empty($parsedServices) || in_array('all', $parsedServices, true) || in_array(0, $parsedServices, true)) {
            $serviceResult = guardResolveServiceIds($namePanel, $parsedServices);
            if ($serviceResult['status'] === false) {
                return $serviceResult;
            }
            $subscription['service_ids'] = $serviceResult['service_ids'];
        } else {
            $subscription['service_ids'] = array_values(array_unique(array_map('intval', $parsedServices)));
        }
    }
    unset($subscription);

    $response = guardApiRequest($config, 'POST', '/api/subscriptions', $payload);
    $decoded = guardDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }
    $normalized = guardNormalizeSubscriptionItems($config, $decoded['data']);
    return [
        'status' => true,
        'data' => $normalized,
        'raw' => $response
    ];
}

function guardUpdateSubscription($namePanel, string $username, array $payload)
{
    $config = getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $encodedUsername = urlencode($username);
    $response = guardApiRequest($config, 'PUT', "/api/subscriptions/{$encodedUsername}", $payload);
    return guardDecodeResponse($response);
}

function guardDeleteSubscriptions($namePanel, array $usernames)
{
    $config = getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $response = guardApiRequest($config, 'DELETE', '/api/subscriptions', ['usernames' => $usernames]);
    return guardDecodeResponse($response);
}

function guardGetSubscription($namePanel, string $username)
{
    $config = getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $response = guardApiRequest($config, 'GET', "/api/subscriptions/{$username}");
    $decoded = guardDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }

    $decoded['data'] = guardNormalizeSubscriptionItems($config, $decoded['data']);
    return $decoded;
}

function guardGetSubscriptionUsages($namePanel, string $username)
{
    $config = getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $response = guardApiRequest($config, 'GET', "/api/subscriptions/{$username}/usages");
    return guardDecodeResponse($response);
}

function guardToggleSubscriptions($namePanel, array $usernames, string $action)
{
    $config = getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $endpoint = $action === "disable" ? '/api/subscriptions/disable' : '/api/subscriptions/enable';
    $response = guardApiRequest($config, 'POST', $endpoint, ['usernames' => $usernames]);
    return guardDecodeResponse($response);
}

function guardResetSubscriptions($namePanel, array $usernames)
{
    $config = getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $response = guardApiRequest($config, 'POST', '/api/subscriptions/reset', ['usernames' => $usernames]);
    return guardDecodeResponse($response);
}

function guardRevokeSubscriptions($namePanel, array $usernames)
{
    $config = getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $response = guardApiRequest($config, 'POST', '/api/subscriptions/revoke', ['usernames' => $usernames]);
    return guardDecodeResponse($response);
}

