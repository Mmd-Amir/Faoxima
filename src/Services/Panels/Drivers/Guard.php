<?php

namespace Faoxima\Services\Panels\Drivers;

use Faoxima\Core\Http\CurlRequest;

final class Guard
{
    public static function guardGetBaseUrl($panelUrl = null)
{
    $normalizedUrl = trim((string) $panelUrl);
    if ($normalizedUrl === '' || $normalizedUrl === '/') {
        return GUARD_CORE_BASE_URL;
    }
    return rtrim($normalizedUrl, '/');
}

    public static function guardVersionOf(array $panelConfig)
{
    $version = strtolower(trim((string) ($panelConfig['panel']['guard_version'] ?? 'v1')));
    return $version === 'v2' ? 'v2' : 'v1';
}

    public static function getGuardPanelConfig($namePanel)
{
    $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $namePanel, "select");
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

    $normalizedUrl = self::guardGetBaseUrl($panel['url_panel'] ?? null);
    if (($panel['url_panel'] ?? '') !== $normalizedUrl) {
        \Faoxima\Core\Database\Db::update("marzban_panel", "url_panel", $normalizedUrl, "id", $panel['id']);
    }

    return [
        'status' => true,
        'panel' => array_merge($panel, ['url_panel' => self::guardGetBaseUrl($panel['url_panel'] ?? null)]),
        'api_key' => !empty($panel['api_key']) ? $panel['api_key'] : $panel['password_panel']
    ];
}

    public static function guardApiRequest(array $panelConfig, string $method, string $endpoint, $payload = null, bool $asJson = true)
{
    $panel = $panelConfig['panel'];
    $apiKey = $panelConfig['api_key'];
    $baseUrl = self::guardGetBaseUrl($panel['url_panel'] ?? null);
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

    public static function guardDecodeResponse(array $response)
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
        } elseif ($statusCode === null || $statusCode < 400) {
            return [
                'status' => false,
                'msg' => 'Invalid response from the Guard panel (not JSON)'
            ];
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
        if (is_array($decodedBody) && !empty($decodedBody['errors'])) {
            $errors = $decodedBody['errors'];
            $details = [];
            if (is_array($errors)) {
                foreach ($errors as $errorKey => $errorItem) {
                    $itemText = is_scalar($errorItem) ? (string) $errorItem : json_encode($errorItem, JSON_UNESCAPED_UNICODE);
                    $details[] = is_string($errorKey) ? $errorKey . ': ' . $itemText : $itemText;
                }
            } else {
                $details[] = is_scalar($errors) ? (string) $errors : json_encode($errors, JSON_UNESCAPED_UNICODE);
            }
            if (!empty($details)) {
                $message = trim((string) $message) . ' | ' . implode(' ; ', $details);
            }
        }
        return [
            'status' => false,
            'msg' => $message,
            'http_code' => (int) $statusCode
        ];
    }

    return [
        'status' => true,
        'data' => $decodedBody,
        'raw' => $response
    ];
}

    public static function guardNormalizeSubscriptionEntry(array $panelConfig, array $subscription)
{
    $baseUrl = self::guardGetBaseUrl($panelConfig['panel']['url_panel'] ?? null);

    $tag = isset($subscription['tag']) ? trim((string) $subscription['tag']) : '';
    $accessKey = isset($subscription['access_key']) ? trim((string) $subscription['access_key']) : '';
    if ($accessKey === '' && isset($subscription['access_key_secret'])) {
        $accessKey = trim((string) $subscription['access_key_secret']);
    }

    $existingUrl = '';
    foreach (['subscription_url', 'subscription_link', 'subscription', 'link'] as $key) {
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

    $finalUrl = $normalizedExisting !== '' ? $normalizedExisting : $computedUrl;
    if ($finalUrl !== '') {
        $subscription['subscription_url'] = $finalUrl;
        $subscription['subscription'] = $finalUrl;
    }

    return $subscription;
}

    public static function guardNormalizeSubscriptionItems(array $panelConfig, $data)
{
    if (!is_array($data)) {
        return $data;
    }

    $isSubscription = isset($data['subscription']) || isset($data['subscription_url']) || isset($data['subscription_link']) || isset($data['tag']) || isset($data['access_key']) || isset($data['link']);
    if ($isSubscription) {
        return self::guardNormalizeSubscriptionEntry($panelConfig, $data);
    }

    foreach (['data', 'subscription', 'subscriptions'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            $data[$key] = self::guardNormalizeSubscriptionItems($panelConfig, $data[$key]);
        }
    }

    $isList = array_keys($data) === range(0, count($data) - 1);
    if ($isList) {
        foreach ($data as $index => $item) {
            if (is_array($item)) {
                $data[$index] = self::guardNormalizeSubscriptionItems($panelConfig, $item);
            }
        }
    }

    return $data;
}

    public static function guardExtractAdminName($adminData)
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

    public static function guardTestConnection($baseUrl, $apiKey, $guardVersion = 'v1')
{
    $apiKey = trim((string) $apiKey);
    $normalizedBaseUrl = self::guardGetBaseUrl($baseUrl);
    $guardVersion = strtolower(trim((string) $guardVersion)) === 'v2' ? 'v2' : 'v1';

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
            'guard_version' => $guardVersion,
        ],
        'api_key' => $apiKey,
    ];

    $response = self::guardApiRequest($panelConfig, 'GET', '/api/admins/current');
    $decoded = self::guardDecodeResponse($response);

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

    public static function guardGetServices($namePanelOrConfig)
{
    if (is_array($namePanelOrConfig)) {
        $config = $namePanelOrConfig;
    } else {
        $config = self::getGuardPanelConfig($namePanelOrConfig);
    }
    if ($config['status'] === false) {
        return $config;
    }
    $response = self::guardApiRequest($config, 'GET', '/api/services');
    $decoded = self::guardDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }
    $services = $decoded['data'];
    if (self::guardVersionOf($config) === 'v2') {
        if (isset($services['items']) && is_array($services['items'])) {
            $services = $services['items'];
        }
    } elseif (isset($services['services']) && is_array($services['services'])) {
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

    public static function guardGetAdminsStats($namePanelOrConfig)
{
    if (is_array($namePanelOrConfig)) {
        $config = $namePanelOrConfig;
    } else {
        $config = self::getGuardPanelConfig($namePanelOrConfig);
    }
    if ($config['status'] === false) {
        return $config;
    }
    $response = self::guardApiRequest($config, 'GET', '/api/admins/stats');
    $decoded = self::guardDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }
    return [
        'status' => true,
        'stats' => is_array($decoded['data']) ? $decoded['data'] : []
    ];
}

    public static function guardGetSubscriptionsStats($namePanelOrConfig)
{
    if (is_array($namePanelOrConfig)) {
        $config = $namePanelOrConfig;
    } else {
        $config = self::getGuardPanelConfig($namePanelOrConfig);
    }
    if ($config['status'] === false) {
        return $config;
    }
    $response = self::guardApiRequest($config, 'GET', '/api/subscriptions/stats');
    $decoded = self::guardDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }
    return [
        'status' => true,
        'stats' => is_array($decoded['data']) ? $decoded['data'] : []
    ];
}

    public static function guardServiceLabel(array $service)
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

    public static function guardNormalizeExpire($timestamp)
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

    public static function guardParseServiceIds($serviceValue)
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

    public static function guardExtractServiceIdsFromList(array $services)
{
    $serviceIds = [];
    foreach ($services as $service) {
        if (isset($service['id'])) {
            $serviceIds[] = intval($service['id']);
        }
    }
    return $serviceIds;
}

    public static function guardResolveServiceIds($namePanel, $serviceValue = null)
{
    if ($serviceValue === null || $serviceValue === '') {
        return [
            'status' => false,
            'msg' => 'سرویس‌های قابل ساخت روی این پنل Guard هنوز تنظیم نشده‌اند. از منوی «مدیریت پنل‌ها ← ⚙️ تنظیم سرویس‌ها» ابتدا سرویس‌ها را مشخص کنید.'
        ];
    }
    $parsedServices = self::guardParseServiceIds($serviceValue);
    $needsAll = empty($parsedServices) || in_array('all', $parsedServices, true) || in_array(0, $parsedServices, true);
    if ($needsAll) {
        $servicesResponse = self::guardGetServices($namePanel);
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

    public static function guardNormalizeUsername($username)
{
    $username = strtolower(trim((string) $username));
    $username = str_replace('-', '_', $username);
    $username = preg_replace('/[^a-z0-9_]/', '_', $username);
    if (strlen($username) > 30) {
        $username = substr($username, 0, 30);
    }
    if (strlen($username) < 3) {
        $username = str_pad($username, 3, '_');
    }
    return $username;
}

    public static function guardDisplayUsername($username, $panel)
{
    $username = (string) $username;
    if ($username === '') {
        return $username;
    }
    if (!is_array($panel)) {
        $namePanel = trim((string) $panel);
        if ($namePanel === '' || !\Faoxima\Core\Database\Db::runtimeReady()) {
            return $username;
        }
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $namePanel, "select");
    }
    if (is_array($panel) && ($panel['type'] ?? null) === 'guard') {
        return self::guardNormalizeUsername($username);
    }
    return $username;
}

    public static function guardNormalizeUsernames(array $usernames)
{
    return array_values(array_map([\Faoxima\Services\Panels\Drivers\Guard::class, 'guardNormalizeUsername'], $usernames));
}

    public static function guardAdaptSubscriptionPayloadForVersion(array $subscription, string $version)
{
    if (array_key_exists('service_ids', $subscription)) {
        $subscription['services'] = $subscription['service_ids'];
        unset($subscription['service_ids']);
    }
    if ($version !== 'v2') {
        return $subscription;
    }
    unset($subscription['discord_webhook_url'], $subscription['auto_delete_days'], $subscription['auto_renewals']);
    return $subscription;
}

    public static function guardCreateSubscription($namePanel, array $payload)
{
    $config = self::getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }

    if (isset($payload['username'])) {
        $payload = [$payload];
    }

    foreach ($payload as &$subscription) {
        if (isset($subscription['username'])) {
            $subscription['username'] = self::guardNormalizeUsername($subscription['username']);
        }
        if (isset($subscription['limit_expire'])) {
            $limitExpire = $subscription['limit_expire'];
            if (is_numeric($limitExpire)) {
                $limitExpire = intval($limitExpire);
                if ($limitExpire > 0 && $limitExpire < 315576000) {
                    $limitExpire = time() + ($limitExpire * 86400);
                }
            }
            if (intval($limitExpire) < 0) {
                $subscription['limit_expire'] = intval($limitExpire);
            } else {
                $subscription['limit_expire'] = self::guardNormalizeExpire($limitExpire);
            }
        }

        $parsedServices = self::guardParseServiceIds($subscription['service_ids'] ?? null);
        if (empty($parsedServices) || in_array('all', $parsedServices, true) || in_array(0, $parsedServices, true)) {
            $serviceResult = self::guardResolveServiceIds($namePanel, $parsedServices);
            if ($serviceResult['status'] === false) {
                return $serviceResult;
            }
            $subscription['service_ids'] = $serviceResult['service_ids'];
        } else {
            $subscription['service_ids'] = array_values(array_unique(array_map('intval', $parsedServices)));
        }

        $subscription = self::guardAdaptSubscriptionPayloadForVersion($subscription, self::guardVersionOf($config));

        $subscription['services'] = array_values(array_map('intval', (array) ($subscription['services'] ?? [])));
        foreach (['limit_usage', 'limit_expire'] as $intField) {
            if (isset($subscription[$intField]) && is_numeric($subscription[$intField])) {
                $subscription[$intField] = (int) $subscription[$intField];
            }
        }
        if (isset($subscription['telegram_id'])) {
            $subscription['telegram_id'] = trim((string) $subscription['telegram_id']);
        }
        foreach ($subscription as $fieldKey => $fieldValue) {
            if ($fieldValue === null || ($fieldKey === 'telegram_id' && $fieldValue === '')) {
                unset($subscription[$fieldKey]);
            }
        }
    }
    unset($subscription);

    $response = self::guardApiRequest($config, 'POST', '/api/subscriptions', $payload);
    $decoded = self::guardDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }
    $normalized = self::guardNormalizeSubscriptionItems($config, $decoded['data']);
    return [
        'status' => true,
        'data' => $normalized,
        'raw' => $response
    ];
}

    public static function guardUpdateSubscription($namePanel, string $username, array $payload)
{
    $config = self::getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $username = self::guardNormalizeUsername($username);
    if (isset($payload['username'])) {
        $payload['username'] = self::guardNormalizeUsername($payload['username']);
    }
    if (self::guardVersionOf($config) === 'v2') {
        $bulkPayload = self::guardAdaptSubscriptionPayloadForVersion($payload, 'v2');
        $bulkPayload['usernames'] = [$username];
        $response = self::guardApiRequest($config, 'PUT', '/api/subscriptions', $bulkPayload);
        $decoded = self::guardDecodeResponse($response);
        if ($decoded['status'] === false) {
            return $decoded;
        }
        $first = is_array($decoded['data']) && isset($decoded['data'][0]) ? $decoded['data'][0] : $decoded['data'];
        return [
            'status' => true,
            'data' => $first,
            'raw' => $response,
        ];
    }
    $encodedUsername = urlencode($username);
    $response = self::guardApiRequest($config, 'PUT', "/api/subscriptions/{$encodedUsername}", $payload);
    return self::guardDecodeResponse($response);
}

    public static function guardDeleteSubscriptions($namePanel, array $usernames)
{
    $config = self::getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $usernames = self::guardNormalizeUsernames($usernames);
    $response = self::guardApiRequest($config, 'DELETE', '/api/subscriptions', ['usernames' => $usernames]);
    return self::guardDecodeResponse($response);
}

    public static function guardGetSubscription($namePanel, string $username)
{
    $config = self::getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $username = self::guardNormalizeUsername($username);

    if (self::guardVersionOf($config) === 'v2') {
        $query = http_build_query(['usernames' => $username, 'limit' => 1]);
        $response = self::guardApiRequest($config, 'GET', "/api/subscriptions?{$query}");
        $decoded = self::guardDecodeResponse($response);
        if ($decoded['status'] === false) {
            return $decoded;
        }
        $items = is_array($decoded['data']) && isset($decoded['data']['items']) && is_array($decoded['data']['items'])
            ? $decoded['data']['items']
            : [];
        if (empty($items)) {
            return [
                'status' => false,
                'msg' => 'User not found'
            ];
        }
        $decoded['data'] = self::guardNormalizeSubscriptionItems($config, $items[0]);
        return $decoded;
    }

    $encodedUsername = urlencode($username);
    $response = self::guardApiRequest($config, 'GET', "/api/subscriptions/{$encodedUsername}");
    $decoded = self::guardDecodeResponse($response);
    if ($decoded['status'] === false) {
        return $decoded;
    }

    $decoded['data'] = self::guardNormalizeSubscriptionItems($config, $decoded['data']);
    return $decoded;
}

    public static function guardGetSubscriptionUsages($namePanel, string $username)
{
    $config = self::getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $username = self::guardNormalizeUsername($username);
    $encodedUsername = urlencode($username);
    if (self::guardVersionOf($config) === 'v2') {
        $response = self::guardApiRequest($config, 'GET', "/api/subscriptions/{$encodedUsername}/hourly-usage");
        return self::guardDecodeResponse($response);
    }
    $response = self::guardApiRequest($config, 'GET', "/api/subscriptions/{$encodedUsername}/usages");
    return self::guardDecodeResponse($response);
}

    public static function guardGetSubscriptionLinks($namePanel, string $username)
{
    $config = self::getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $username = self::guardNormalizeUsername($username);
    if (self::guardVersionOf($config) !== 'v2') {
        return [
            'status' => false,
            'msg' => 'Per-config links are only available on Guard v2'
        ];
    }
    $encodedUsername = urlencode($username);
    $response = self::guardApiRequest($config, 'GET', "/api/subscriptions/{$encodedUsername}/links");
    return self::guardDecodeResponse($response);
}

    public static function guardToggleSubscriptions($namePanel, array $usernames, string $action)
{
    $config = self::getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $usernames = self::guardNormalizeUsernames($usernames);
    $endpoint = $action === "disable" ? '/api/subscriptions/disable' : '/api/subscriptions/enable';
    $response = self::guardApiRequest($config, 'POST', $endpoint, ['usernames' => $usernames]);
    return self::guardDecodeResponse($response);
}

    public static function guardResetSubscriptions($namePanel, array $usernames)
{
    $config = self::getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $usernames = self::guardNormalizeUsernames($usernames);
    $response = self::guardApiRequest($config, 'POST', '/api/subscriptions/reset', ['usernames' => $usernames]);
    return self::guardDecodeResponse($response);
}

    public static function guardRevokeSubscriptions($namePanel, array $usernames)
{
    $config = self::getGuardPanelConfig($namePanel);
    if ($config['status'] === false) {
        return $config;
    }
    $usernames = self::guardNormalizeUsernames($usernames);
    $response = self::guardApiRequest($config, 'POST', '/api/subscriptions/revoke', ['usernames' => $usernames]);
    return self::guardDecodeResponse($response);
}

    public static function guardCapabilityReport(array $panel, array &$report)
{
    $apiKey = trim((string) (!empty($panel['api_key']) ? $panel['api_key'] : ($panel['password_panel'] ?? '')));
    $version = strtolower(trim((string) ($panel['guard_version'] ?? 'v1'))) === 'v2' ? 'v2' : 'v1';
    \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'نسخه Guard', $version);
    if ($apiKey === '') {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'failed', 'کلید API برای این پنل تنظیم نشده است.');
        \Faoxima\Services\Panels\PanelCapability::rx_cap_mark_untested_after_connection_failure($report);
        return;
    }
    $test = self::guardTestConnection($panel['url_panel'] ?? null, $apiKey, $version);
    if (empty($test['status'])) {
        $response = is_array($test['response'] ?? null) ? $test['response'] : ['status' => 0, 'error' => (string) ($test['msg'] ?? '')];
        $read = \Faoxima\Services\Panels\PanelCapability::rx_cap_http_read($response);
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'failed', (int) ($response['status'] ?? 0) === 403 ? 'HTTP 403؛ کلید API پذیرفته نشد.' : $read[1]);
        \Faoxima\Services\Panels\PanelCapability::rx_cap_mark_untested_after_connection_failure($report);
        return;
    }
    \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'verified', 'GET /api/admins/current موفق بود.');
    $admin = is_array($test['data'] ?? null) ? $test['data'] : [];
    \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'ادمین متصل', self::guardExtractAdminName($admin));
    $role = is_string($admin['role'] ?? null) ? strtolower($admin['role']) : null;
    \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'نقش ادمین', $role);
    $flags = ['create' => 'create_access', 'update' => 'update_access', 'delete' => 'remove_access'];
    foreach ($flags as $key => $field) {
        if ($role === 'owner') {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, $key, 'allowed', 'نقش owner.');
        } elseif (array_key_exists($field, $admin) && is_bool($admin[$field])) {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, $key, $admin[$field] ? 'allowed' : 'denied', 'بر اساس ' . $field . '.');
        } else {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, $key, 'unknown', 'فیلد ' . $field . ' در پاسخ پنل نیست.');
        }
    }
    foreach (['reset', 'revoke'] as $key) {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, $key, $role === 'owner' ? 'allowed' : 'unknown', $role === 'owner' ? 'نقش owner.' : 'Guard برای این عملیات مجوز جداگانه‌ای اعلام نمی‌کند.');
    }
    if (isset($admin['count_limit']) && $admin['count_limit'] !== null) {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'سقف تعداد کاربر', (int) $admin['count_limit']);
        if (isset($admin['left_count']) && (int) $admin['left_count'] <= 0 && $report['checks']['create']['status'] === 'allowed') {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'create', 'unknown', 'مجوز ساخت اعلام شده، اما سهمیه تعداد کاربر این ادمین تمام شده است.');
        }
    }
    $config = $test['panel_config'];
    $listResponse = self::guardApiRequest($config, 'GET', '/api/subscriptions?limit=1');
    $list = \Faoxima\Services\Panels\PanelCapability::rx_cap_http_read($listResponse);
    if ($list[0] === 'verified') {
        $list[1] = 'GET /api/subscriptions موفق بود.';
    }
    \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'list_users', $list[0], $list[1]);

    \Faoxima\Services\Panels\PanelCapability::rx_cap_probe_sample_user_via_manager($panel, $report);
    if ($version === 'v1' && $report['checks']['configs']['status'] === 'unknown') {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'configs', 'unsupported', 'Guard v1 لینک تک‌تک کانفیگ‌ها را ارائه نمی‌دهد؛ فقط لینک اشتراک در دسترس است.');
    }

    $services = self::guardGetServices($config);
    if (($services['status'] ?? false) === false) {
        $httpCode = (int) ($services['http_code'] ?? 0);
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'resources', $httpCode === 403 ? 'denied' : 'failed', 'GET /api/services: ' . \Faoxima\Services\Panels\PanelCapability::rx_cap_clean_text((string) ($services['msg'] ?? ''), 120));
    } else {
        $available = self::guardExtractServiceIdsFromList((array) ($services['services'] ?? []));
        $stored = self::guardParseServiceIds($panel['guard_service_ids'] ?? null);
        $missing = [];
        if (!in_array('all', $stored, true) && !in_array(0, $stored, true)) {
            $missing = array_values(array_diff(array_map('intval', $stored), $available));
        }
        $note = count($available) . ' سرویس قابل مشاهده.' . (empty($missing) ? '' : ' سرویس‌های تنظیم‌شده ناموجود: ' . implode(', ', $missing));
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'resources', empty($missing) ? 'verified' : 'failed', $note);
    }
}
}
