<?php

class RemnawaveManager
{
    private $panel;
    private $baseUrl;
    private $timeout = 30;

    public function __construct(array $panel)
    {
        $this->panel = $panel;
        $this->baseUrl = rtrim((string)($panel['url_panel'] ?? ''), '/');
    }

    public static function fromPdo($pdo, $name_panel)
    {
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel = :n LIMIT 1");
        $stmt->execute([':n' => $name_panel]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return new self($row);
    }

    public function getToken($pdo = null)
    {
        $token = trim((string)($this->panel['remna_api_token'] ?? ''));
        if ($token === '') {
            error_log("[REMNAMWAVE-AUTH-REQ] missing static API token for panel: " . ($this->panel['name_panel'] ?? $this->baseUrl));
            return null;
        }
        return $token;
    }

    public function testConnection($pdo = null)
    {
        $token = $this->getToken($pdo);
        if ($token === null || $token === '') {
            error_log("[REMNAMWAVE-PANEL-TEST-FAIL] URL: " . $this->baseUrl . " | could not obtain token");
            return ['ok' => false, 'message' => 'دریافت توکن ناموفق بود'];
        }
        $path = parse_url($this->baseUrl, PHP_URL_PATH);
        if ($path !== null && trim($path, '/') !== '') {
            error_log("[REMNAMWAVE-PANEL-TEST-FAIL] URL contains extra path: " . $this->baseUrl . " | only the panel domain must be entered, without /dashboard");
            return ['ok' => false, 'message' => 'اتصال به پنل ناموفق بود (آدرس باید فقط دامنه باشد، بدون /dashboard)'];
        }
        $result = $this->request('GET', '/api/system/stats', null, $token);
        if ($result['code'] === 401 || $result['code'] === 403) {
            error_log("[REMNAMWAVE-PANEL-TEST-FAIL] HTTP Code: {$result['code']} | Response: {$result['raw']}");
            return ['ok' => false, 'message' => 'توکن API نامعتبر است (' . $result['code'] . ')'];
        }
        if ($result['code'] !== 200) {
            error_log("[REMNAMWAVE-PANEL-TEST-FAIL] HTTP Code: {$result['code']} | Response: {$result['raw']}");
            return ['ok' => false, 'message' => 'اتصال به پنل ناموفق بود (' . $result['code'] . ')'];
        }
        if (!is_array($result['json'])) {
            error_log("[REMNAMWAVE-PANEL-TEST-FAIL] Non-JSON response (likely dashboard URL): " . $this->baseUrl);
            return ['ok' => false, 'message' => 'اتصال به پنل ناموفق بود (پاسخ نامعتبر، آدرس را بدون /dashboard وارد کنید)'];
        }
        return ['ok' => true, 'message' => 'اتصال موفق'];
    }

    public function createUser(array $params, $invoice_id = '', $pdo = null)
    {
        $username = (string)($params['username'] ?? '');
        $token = $this->getToken($pdo);
        if ($token === null) {
            error_log("[REMNAMWAVE-CREATE-ERR] Invoice: $invoice_id | User: $username | Res: token_failed");
            return ['ok' => false, 'message' => 'authentication failed', 'data' => null];
        }

        if (isset($params['activeInternalSquads'])) {
            if (!is_array($params['activeInternalSquads'])) {
                $params['activeInternalSquads'] = [trim((string) $params['activeInternalSquads'])];
            } else {
                $params['activeInternalSquads'] = array_values(array_map(function ($v) {
                    return trim((string) $v);
                }, $params['activeInternalSquads']));
            }
        }

        $result = $this->request('POST', '/api/users', $params, $token);
        if ($result['code'] !== 201 && $result['code'] !== 200) {
            $response = $result['raw'];
            error_log("[REMNAMWAVE-CREATE-ERR] Invoice: $invoice_id | User: $username | Res: $response");
            return ['ok' => false, 'message' => 'create failed (' . $result['code'] . ')', 'data' => null];
        }

        $data = is_array($result['json']) ? ($result['json']['response'] ?? $result['json']) : null;
        return ['ok' => true, 'message' => 'created', 'data' => $data];
    }

    public function updateUser(array $params, $token = null, $pdo = null)
    {
        if ($token === null) {
            $token = $this->getToken($pdo);
        }
        $username = (string)($params['username'] ?? ($params['id'] ?? ''));
        if ($token === null) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: update | User: $username | Code: 0");
            return ['ok' => false, 'data' => null];
        }
        $result = $this->request('PATCH', '/api/users', $params, $token);
        if ($result['code'] !== 200) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: update | User: $username | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $data = is_array($result['json']) ? ($result['json']['response'] ?? $result['json']) : null;
        return ['ok' => true, 'data' => $data];
    }

    public function extendUser($userId, $expireAtIso, $pdo = null)
    {
        return $this->actionUpdate(['id' => (int) $userId, 'expireAt' => $expireAtIso], 'extend', $userId, $pdo);
    }

    public function addVolume($userId, $trafficLimitBytes, $pdo = null)
    {
        return $this->actionUpdate(['id' => (int) $userId, 'trafficLimitBytes' => $trafficLimitBytes], 'add-volume', $userId, $pdo);
    }

    public function enableUser($userId, $pdo = null)
    {
        return $this->actionRaw('POST', '/api/users/' . rawurlencode((string)(int) $userId) . '/actions/enable', 'enable', $userId, $pdo);
    }

    public function disableUser($userId, $pdo = null)
    {
        return $this->actionRaw('POST', '/api/users/' . rawurlencode((string)(int) $userId) . '/actions/disable', 'disable', $userId, $pdo);
    }

    public function revokeUser($userId, $revokeOnlyPasswords = false, $pdo = null)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: revoke | User: $userId | Code: 0");
            return ['ok' => false, 'data' => null];
        }
        $body = ['revokeOnlyPasswords' => (bool) $revokeOnlyPasswords];
        $result = $this->request('POST', '/api/users/' . rawurlencode((string)(int) $userId) . '/actions/revoke', $body, $token);
        if ($result['code'] !== 200 && $result['code'] !== 201) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: revoke | User: $userId | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $data = is_array($result['json']) ? ($result['json']['response'] ?? $result['json']) : null;
        return ['ok' => true, 'data' => $data];
    }

    public function resetTraffic($userId, $pdo = null)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: reset-traffic | User: $userId | Code: 0");
            return ['ok' => false, 'data' => null];
        }
        $result = $this->request('POST', '/api/users/' . rawurlencode((string)(int) $userId) . '/actions/reset-traffic', null, $token);
        if ($result['code'] !== 200 && $result['code'] !== 201) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: reset-traffic | User: $userId | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $data = is_array($result['json']) ? ($result['json']['response'] ?? $result['json']) : null;
        return ['ok' => true, 'data' => $data];
    }

    public function getUserById($userId, $pdo = null)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            return ['ok' => false, 'data' => null];
        }
        $result = $this->request('GET', '/api/users/' . rawurlencode((string)(int) $userId), null, $token);
        if ($result['code'] !== 200) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: get | User: $userId | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $data = is_array($result['json']) ? ($result['json']['response'] ?? $result['json']) : null;
        return ['ok' => true, 'data' => $data];
    }

    public function getSubscriptionByShortUuid($shortUuid, $pdo = null)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            return ['ok' => false, 'data' => null];
        }
        $shortUuid = trim((string) $shortUuid);
        if ($shortUuid === '') {
            return ['ok' => false, 'data' => null];
        }
        $endpoints = ['/api/sub/' . rawurlencode($shortUuid) . '/info', '/api/subscriptions/by-short-uuid/' . rawurlencode($shortUuid)];
        foreach ($endpoints as $ep) {
            $result = $this->request('GET', $ep, null, $token);
            if ($result['code'] === 200 && is_array($result['json'])) {
                $data = $result['json']['response'] ?? $result['json'];
                return ['ok' => true, 'data' => $data];
            }
        }
        return ['ok' => false, 'data' => null];
    }

    public function getSystemStats($pdo = null)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            return ['ok' => false, 'data' => null];
        }
        $result = $this->request('GET', '/api/system/stats', null, $token);
        if ($result['code'] !== 200 || !is_array($result['json'])) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: stats | User: - | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $payload = $result['json']['response'] ?? $result['json'];
        if (!is_array($payload)) {
            return ['ok' => false, 'data' => null];
        }
        return ['ok' => true, 'data' => $payload];
    }

    private function getJson($path, $action, $pdo = null)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: $action | User: - | Code: 0");
            return ['ok' => false, 'data' => null];
        }
        $result = $this->request('GET', $path, null, $token);
        if ($result['code'] !== 200 || !is_array($result['json'])) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: $action | User: - | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $data = $result['json']['response'] ?? $result['json'];
        return ['ok' => true, 'data' => $data];
    }

    public function getNodes($pdo = null)
    {
        return $this->getJson('/api/nodes', 'get-nodes', $pdo);
    }

    public function getNodesMetrics($pdo = null)
    {
        return $this->getJson('/api/system/nodes/metrics', 'nodes-metrics', $pdo);
    }

    public function getSystemMetadata($pdo = null)
    {
        return $this->getJson('/api/system/metadata', 'system-metadata', $pdo);
    }

    public function getInternalSquads($pdo = null)
    {
        return $this->getJson('/api/internal-squads', 'internal-squads', $pdo);
    }

    public function getUserByTelegramId($telegramId, $pdo = null)
    {
        return $this->getJson('/api/users/by-telegram-id/' . rawurlencode((string)$telegramId), 'get-by-tg', $pdo);
    }

    public function getUserBandwidth($userId, $start, $end, $topNodesLimit = 20, $pdo = null)
    {
        $query = '?start=' . rawurlencode((string)$start) . '&end=' . rawurlencode((string)$end) . '&topNodesLimit=' . (int)$topNodesLimit;
        return $this->getJson('/api/bandwidth-stats/users/' . rawurlencode((string)(int) $userId) . $query, 'user-bandwidth', $pdo);
    }

    public function setUserSquads($userId, array $squadUuids, $pdo = null)
    {
        $squads = array_values(array_map(function ($v) {
            return trim((string)$v);
        }, $squadUuids));
        return $this->actionUpdate(['id' => (int) $userId, 'activeInternalSquads' => $squads], 'set-squads', $userId, $pdo);
    }

    public function flushHwid($userId, $pdo = null)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: hwid-flush | User: $userId | Code: 0");
            return ['ok' => false, 'data' => null];
        }
        $result = $this->request('POST', '/api/hwid/devices/delete-all', ['userId' => (int) $userId], $token);
        if ($result['code'] !== 200 && $result['code'] !== 201) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: hwid-flush | User: $userId | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $data = is_array($result['json']) ? ($result['json']['response'] ?? $result['json']) : null;
        return ['ok' => true, 'data' => $data];
    }

    public function getUserHwidDevices($userId, $pdo = null)
    {
        return $this->getJson('/api/hwid/devices/' . rawurlencode((string)(int) $userId), 'hwid-devices', $pdo);
    }

    public function getRawSubscriptionByShortUuid($shortUuid, $pdo = null)
    {
        $shortUuid = trim((string) $shortUuid);
        if ($shortUuid === '') {
            return ['ok' => false, 'data' => null];
        }
        return $this->getJson('/api/subscriptions/by-short-uuid/' . rawurlencode($shortUuid) . '/raw', 'raw-subscription', $pdo);
    }

    public function bulkExtend(array $userIds, $extendDays, $pdo = null)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            return ['ok' => false, 'data' => null];
        }
        $userIds = array_values(array_filter(array_map(function ($v) {
            return (int) $v;
        }, $userIds), function ($v) {
            return $v > 0;
        }));
        if (count($userIds) === 0) {
            return ['ok' => false, 'data' => null];
        }
        $body = ['userIds' => $userIds, 'extendDays' => (int)$extendDays];
        $result = $this->request('POST', '/api/users/bulk/extend-expiration-date', $body, $token);
        if ($result['code'] !== 200 && $result['code'] !== 201) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: bulk-extend | Count: " . count($userIds) . " | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $data = is_array($result['json']) ? ($result['json']['response'] ?? $result['json']) : null;
        return ['ok' => true, 'data' => $data];
    }

    private function actionUpdate(array $params, $action, $username, $pdo)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: $action | User: $username | Code: 0");
            return ['ok' => false, 'data' => null];
        }
        $result = $this->request('PATCH', '/api/users', $params, $token);
        if ($result['code'] !== 200) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: $action | User: $username | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $data = is_array($result['json']) ? ($result['json']['response'] ?? $result['json']) : null;
        return ['ok' => true, 'data' => $data];
    }

    private function actionRaw($method, $path, $action, $username, $pdo)
    {
        $token = $this->getToken($pdo);
        if ($token === null) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: $action | User: $username | Code: 0");
            return ['ok' => false, 'data' => null];
        }
        $result = $this->request($method, $path, null, $token);
        if ($result['code'] !== 200) {
            error_log("[REMNAMWAVE-ACTION-ERR] Action: $action | User: $username | Code: {$result['code']}");
            return ['ok' => false, 'data' => null];
        }
        $data = is_array($result['json']) ? ($result['json']['response'] ?? $result['json']) : null;
        return ['ok' => true, 'data' => $data];
    }

    private function request($method, $path, $body = null, $token = null)
    {
        $url = $this->baseUrl . $path;
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }


        $curl = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($curl, $opts);

        $rxHttpStarted = microtime(true);
        $raw = curl_exec($curl);
        $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($curl);
        $rxHttpInfo = curl_getinfo($curl);
        if (function_exists('rx_perf_log')) {
            rx_perf_log('http', 'http.request', [
                'method' => (string) $method,
                'url' => function_exists('rx_perf_safe_url') ? rx_perf_safe_url($url) : '',
                'attempt' => 1,
                'duration_ms' => round((microtime(true) - $rxHttpStarted) * 1000, 3),
                'http_code' => $code,
                'curl_errno' => (int) curl_errno($curl),
                'curl_error' => (string) $curlErr,
                'connect_ms' => round(((float) ($rxHttpInfo['connect_time'] ?? 0)) * 1000, 3),
                'tls_ready_ms' => round(((float) ($rxHttpInfo['appconnect_time'] ?? 0)) * 1000, 3),
                'namelookup_ms' => round(((float) ($rxHttpInfo['namelookup_time'] ?? 0)) * 1000, 3),
                'starttransfer_ms' => round(((float) ($rxHttpInfo['starttransfer_time'] ?? 0)) * 1000, 3),
            ]);
        }
        curl_close($curl);

        if ($raw === false) {
            return ['code' => 0, 'raw' => $curlErr, 'json' => null];
        }

        $json = json_decode($raw, true);
        return ['code' => $code, 'raw' => (string)$raw, 'json' => is_array($json) ? $json : null];
    }
}
