<?php

namespace Faoxima\Services\Panels\Drivers;

use PDO;
use CURLFile;
use Throwable;

final class JovadPanel
{
    public static function jovadpanelPdo()
{
    global $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (\Faoxima\Core\Database\Db::runtimeReady()) {
        try {
            $connection = \Faoxima\Core\Database\Db::getDatabaseConnection();
            if ($connection instanceof PDO) {
                return $connection;
            }
        } catch (Throwable $e) {
        }
    }
    return null;
}

    public static function jovadpanelBaseUrl($url)
{
    $base = rtrim(trim((string) $url), '/');
    if ($base !== '' && !preg_match('#/api/v1$#i', $base)) {
        $base .= '/api/v1';
    }
    return $base;
}

    public static function jovadpanelHostUrl($url)
{
    $base = rtrim(trim((string) $url), '/');
    $base = preg_replace('#/api/v1$#i', '', $base);
    return rtrim((string) $base, '/');
}

    public static function jovadpanelEnsureSchema()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return $ready = false;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS jovadpanel_configs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code_panel VARCHAR(100) NOT NULL,
            name_panel VARCHAR(191) NOT NULL,
            username VARCHAR(191) NOT NULL,
            id_invoice VARCHAR(191) NULL,
            remote_id VARCHAR(191) NULL,
            label VARCHAR(64) NOT NULL,
            sub_url TEXT NULL,
            proto VARCHAR(32) NULL,
            status VARCHAR(32) NULL,
            renewal_state MEDIUMTEXT NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_jovad_service (code_panel, username),
            KEY idx_jovad_remote (code_panel, remote_id),
            KEY idx_jovad_label (code_panel, label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $column = $pdo->query("SHOW COLUMNS FROM jovadpanel_configs LIKE 'renewal_state'");
        if (!$column->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE jovadpanel_configs ADD COLUMN renewal_state MEDIUMTEXT NULL");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS jovadpanel_orders (
            id_invoice VARCHAR(191) NOT NULL PRIMARY KEY,
            code_panel VARCHAR(100) NOT NULL,
            username VARCHAR(191) NOT NULL,
            kind VARCHAR(16) NOT NULL DEFAULT 'unlimited',
            devices INT NOT NULL DEFAULT 0,
            days INT NOT NULL DEFAULT 0,
            channels VARCHAR(64) NULL,
            channel_source VARCHAR(20) NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0,
            KEY idx_jovad_order_panel (code_panel, username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach (['channels' => 'VARCHAR(64) NULL', 'channel_source' => 'VARCHAR(20) NULL'] as $column => $definition) {
            $existing = $pdo->query("SHOW COLUMNS FROM jovadpanel_orders LIKE '" . $column . "'");
            if (!$existing->fetch(PDO::FETCH_ASSOC)) {
                $pdo->exec("ALTER TABLE jovadpanel_orders ADD COLUMN " . $column . " " . $definition);
            }
        }
    } catch (Throwable $e) {
        return $ready = false;
    }
    return $ready = true;
}

    public static function jovadpanelPanelConfig($namePanelOrRow)
{
    if (is_array($namePanelOrRow)) {
        $panel = $namePanelOrRow;
    } else {
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", (string) $namePanelOrRow, "select");
    }
    if (!is_array($panel) || ($panel['type'] ?? null) !== 'jovadpanel') {
        return ['status' => false, 'msg' => 'JovadPanel panel not found'];
    }
    $apiKey = trim((string) ($panel['api_key'] ?? ''));
    if ($apiKey === '') {
        return ['status' => false, 'msg' => 'API key is not configured for this JovadPanel panel'];
    }
    $baseUrl = self::jovadpanelBaseUrl($panel['url_panel'] ?? '');
    if ($baseUrl === '') {
        return ['status' => false, 'msg' => 'JovadPanel panel URL is not configured'];
    }
    return ['status' => true, 'panel' => $panel, 'api_key' => $apiKey, 'base_url' => $baseUrl];
}

    public static function jovadpanelRateAllow($panelName, $method = 'GET')
{
    $method = strtoupper((string) $method);
    $isBackground = PHP_SAPI === 'cli';
    $readLimit = $isBackground ? 80 : 110;
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jovad_rl_' . substr(sha1((string) $panelName), 0, 12) . '.json';
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return true;
    }
    $allowed = true;
    try {
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return true;
        }
        $raw = stream_get_contents($handle);
        $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $minute = (int) floor(time() / 60);
        if (!is_array($state) || (int) ($state['minute'] ?? 0) !== $minute) {
            $state = ['minute' => $minute, 'count' => 0];
        }
        $count = (int) ($state['count'] ?? 0);
        if ($method === 'GET' && $count >= $readLimit) {
            $allowed = false;
        } else {
            $count++;
        }
        $state['count'] = $count;
        @ftruncate($handle, 0);
        @rewind($handle);
        @fwrite($handle, json_encode($state));
        @fflush($handle);
        flock($handle, LOCK_UN);
    } catch (Throwable $e) {
        $allowed = true;
    }
    fclose($handle);
    return $allowed;
}

    public static function jovadpanelHttp(array $panel, string $method, string $path, $payload = null)
{
    $config = self::jovadpanelPanelConfig($panel);
    if ($config['status'] !== true) {
        return ['status' => 0, 'body' => '', 'error' => (string) ($config['msg'] ?? 'invalid panel config')];
    }
    $method = strtoupper($method);
    if (!self::jovadpanelRateAllow((string) ($panel['name_panel'] ?? ''), $method)) {
        return ['status' => 0, 'body' => '', 'error' => 'rate_limited'];
    }
    $url = $config['base_url'] . '/' . ltrim($path, '/');
    $headers = [
        'accept: application/json',
        'Authorization: Bearer ' . $config['api_key'],
    ];
    $body = null;
    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = json_encode((object) array());
        }
        $headers[] = 'Content-Type: application/json';
    } elseif (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $body = '{}';
        $headers[] = 'Content-Type: application/json';
    }
    $timeoutInfo = method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_panel_timeout_info')
        ? \Faoxima\Services\Panels\PanelHttp::faoxima_panel_timeout_info()
        : ['timeout' => 20, 'connect_timeout' => 8];
    $attempts = $method === 'GET' ? 2 : 1;
    $lastError = '';
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeoutInfo['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeoutInfo['connect_timeout']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if (defined('BOT_CURL_VERIFY_TLS') && BOT_CURL_VERIFY_TLS !== true) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        if (function_exists('faoxima_apply_curl_proxy')) {
            faoxima_apply_curl_proxy($ch, 'panel');
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($method !== 'GET' && preg_match('~^/?unlimited(?:/|$)~', $path)) {
            self::jovadpanelUnlimitedCache($panel, '', null, true);
        }
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response !== false && $httpCode > 0) {
            return ['status' => $httpCode, 'body' => (string) $response];
        }
        $lastError = $error !== '' ? $error : 'connection failed';
        $isTimeout = stripos($lastError, 'timed out') !== false;
        if ($attempt >= $attempts || !$isTimeout) {
            break;
        }
    }
    return ['status' => 0, 'body' => '', 'error' => $lastError];
}

    public static function jovadpanelDecode(array $response)
{
    if (!empty($response['error'])) {
        $error = (string) $response['error'];
        return [
            'ok' => false,
            'http' => (int) ($response['status'] ?? 0),
            'code' => $error === 'rate_limited' ? 'rate_limited' : 'network_error',
            'message' => $error,
            'data' => null,
        ];
    }
    $status = (int) ($response['status'] ?? 0);
    $raw = is_string($response['body'] ?? null) ? $response['body'] : '';
    $data = $raw !== '' ? json_decode($raw, true) : null;
    if (is_array($data) && is_array($data['data'] ?? null) && !isset($data['configs']) && !isset($data['id'])) {
        $data = $data['data'];
    }
    if ($status >= 200 && $status < 300) {
        if (!is_array($data)) {
            return ['ok' => false, 'http' => $status, 'code' => 'invalid_response', 'message' => 'Invalid JSON from panel', 'data' => null];
        }
        return ['ok' => true, 'http' => $status, 'code' => '', 'message' => '', 'data' => $data];
    }
    $code = is_array($data) && isset($data['error']) && is_scalar($data['error']) ? (string) $data['error'] : (string) $status;
    $message = is_array($data) && isset($data['message']) && is_scalar($data['message']) ? (string) $data['message'] : '';
    if ($message === '' && is_array($data) && isset($data['detail'])) {
        $message = is_scalar($data['detail']) ? (string) $data['detail'] : json_encode($data['detail'], JSON_UNESCAPED_UNICODE);
    }
    return ['ok' => false, 'http' => $status, 'code' => $code, 'message' => $message, 'data' => is_array($data) ? $data : null];
}

    public static function jovadpanelTestConnection($baseUrl, $apiKey)
{
    $apiKey = trim((string) $apiKey);
    $base = self::jovadpanelBaseUrl($baseUrl);
    if ($apiKey === '') {
        return ['status' => false, 'msg' => 'JovadPanel API key is missing'];
    }
    if ($base === '') {
        return ['status' => false, 'msg' => 'JovadPanel panel URL is missing'];
    }
    $panel = [
        'name_panel' => '__jovad_connection_test__',
        'type' => 'jovadpanel',
        'url_panel' => $base,
        'api_key' => $apiKey,
    ];
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'ping'));
    if ($decoded['ok'] !== true) {
        $message = $decoded['message'] !== '' ? $decoded['message'] : ($decoded['code'] !== '' ? $decoded['code'] : 'connection failed');
        if ($decoded['http'] > 0) {
            $message .= ' (HTTP ' . $decoded['http'] . ')';
        }
        return ['status' => false, 'msg' => $message];
    }
    $account = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'me'));
    return [
        'status' => true,
        'msg' => 'JovadPanel connection succeeded',
        'data' => $account['ok'] === true ? $account['data'] : null,
    ];
}

    public static function jovadpanelIsoToTs($value)
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false || $ts < 0) {
        return null;
    }
    return (int) $ts;
}

    public static function jovadpanelGbToBytes($gb)
{
    if (!is_numeric($gb)) {
        return null;
    }
    return (int) round(((float) $gb) * JOVAD_GIB);
}

    public static function jovadpanelBytesToGb($bytes)
{
    return round(((float) $bytes) / JOVAD_GIB, 3);
}

    public static function jovadpanelAllowedChannels()
{
    return ['xray', 'wireguard', 'openvpn', 'dns'];
}

    public static function jovadpanelCanonicalChannels($channels)
{
    $allowed = self::jovadpanelAllowedChannels();
    $seen = [];
    $result = [];
    foreach ((array) $channels as $channel) {
        $value = strtolower(trim((string) $channel));
        if ($value === '' || !in_array($value, $allowed, true) || isset($seen[$value])) {
            continue;
        }
        $seen[$value] = true;
        $result[$value] = $value;
    }
    $ordered = [];
    foreach ($allowed as $channel) {
        if (isset($result[$channel])) {
            $ordered[] = $channel;
        }
    }
    return $ordered;
}

    public static function jovadpanelParseChannels($raw)
{
    $result = ['present' => false, 'channels' => [], 'invalid' => []];
    if ($raw === null) {
        return $result;
    }
    $result['present'] = true;
    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw === '') {
            $result['present'] = false;
            return $result;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        } else {
            $raw = preg_split('/[,\s]+/', $raw);
        }
    }
    if (!is_array($raw)) {
        $result['invalid'][] = gettype($raw);
        return $result;
    }
    $allowed = self::jovadpanelAllowedChannels();
    foreach ($raw as $item) {
        if (!is_scalar($item)) {
            $result['invalid'][] = gettype($item);
            continue;
        }
        $value = strtolower(trim((string) $item));
        if ($value === '') {
            continue;
        }
        if (!in_array($value, $allowed, true)) {
            $result['invalid'][] = $value;
            continue;
        }
        $result['channels'][] = $value;
    }
    $result['channels'] = self::jovadpanelCanonicalChannels($result['channels']);
    return $result;
}

    public static function jovadpanelChannelsFromProto($proto)
{
    $proto = strtolower(trim((string) $proto));
    if ($proto === '') {
        return [];
    }
    if (strpos($proto, ',') !== false) {
        return self::jovadpanelParseChannels($proto)['channels'];
    }
    if ($proto === 'openvpn') {
        return ['openvpn'];
    }
    if ($proto === 'wireguard') {
        return ['wireguard'];
    }
    if ($proto === 'xray') {
        return ['xray'];
    }
    if ($proto === 'dns') {
        return ['dns'];
    }
    if ($proto === 'both') {
        return ['xray', 'wireguard'];
    }
    return [];
}

    public static function jovadpanelChannelsFromConfig(array $cfg, $fallbackProto = '')
{
    $parsed = self::jovadpanelParseChannels($cfg['channels'] ?? null);
    if (!empty($parsed['channels'])) {
        return ['channels' => $parsed['channels'], 'source' => 'remote_channels', 'invalid' => $parsed['invalid']];
    }
    $remoteProto = trim((string) ($cfg['proto'] ?? ''));
    $channels = self::jovadpanelChannelsFromProto($remoteProto);
    $source = 'remote_proto';
    if (empty($channels)) {
        $channels = self::jovadpanelChannelsFromProto($fallbackProto);
        $source = 'local_proto';
    }
    if (($cfg['dns'] ?? null) === true && !in_array('dns', $channels, true)) {
        $channels[] = 'dns';
    }
    if (!empty($channels)) {
        return ['channels' => self::jovadpanelCanonicalChannels($channels), 'source' => $source, 'invalid' => []];
    }
    return ['channels' => [], 'source' => 'unknown', 'invalid' => []];
}

    public static function jovadpanelResolveChannels(array $panel, array $product, $idInvoice = '', $username = '')
{
    $idInvoice = trim((string) $idInvoice);
    if ($idInvoice !== '') {
        $order = self::jovadpanelOrderRow($idInvoice);
        if (is_array($order)) {
            if ((string) ($order['kind'] ?? '') === 'metered') {
                if ((string) ($order['code_panel'] ?? '') !== (string) ($panel['code_panel'] ?? '')
                    || (string) ($order['username'] ?? '') !== (string) $username) {
                    return ['status' => false, 'code' => 'order_mismatch', 'msg' => '❌ مشخصات سفارش با فاکتور هم‌خوان نیست؛ خرید انجام نشد.', 'channels' => [], 'source' => 'order', 'frozen' => true];
                }
                $stored = trim((string) ($order['channels'] ?? ''));
                $source = trim((string) ($order['channel_source'] ?? ''));
                if ($source === '') {
                    $source = $stored === '' ? 'api_default' : 'order';
                }
                if ($stored === '') {
                    return ['status' => true, 'channels' => [], 'source' => $source, 'frozen' => true, 'invalid' => []];
                }
                $parsed = self::jovadpanelParseChannels($stored);
                if (empty($parsed['channels']) || !empty($parsed['invalid'])) {
                    return ['status' => false, 'code' => 'order_channels_invalid', 'msg' => '❌ کانال‌های ثبت‌شده برای این سفارش معتبر نیستند؛ با پشتیبانی در ارتباط باشید.', 'channels' => [], 'source' => 'order', 'frozen' => true];
                }
                return ['status' => true, 'channels' => $parsed['channels'], 'source' => $source, 'frozen' => true, 'invalid' => []];
            }
        }
    }
    if (array_key_exists('inbounds', $product)) {
        $parsed = self::jovadpanelParseChannels($product['inbounds'] ?? null);
        if ($parsed['present']) {
            if (empty($parsed['channels']) || !empty($parsed['invalid'])) {
                return ['status' => false, 'code' => 'invalid_product_channels', 'msg' => '❌ کانال‌های تعریف‌شده برای این محصول معتبر نیستند. فقط xray، wireguard، openvpn و dns مجاز هستند.', 'channels' => [], 'source' => 'product', 'invalid' => $parsed['invalid']];
            }
            return ['status' => true, 'channels' => $parsed['channels'], 'source' => 'product', 'frozen' => false, 'invalid' => []];
        }
    }
    $panelParsed = self::jovadpanelParseChannels($panel['jovad_channels'] ?? null);
    if ($panelParsed['present']) {
        if (empty($panelParsed['channels']) || !empty($panelParsed['invalid'])) {
            return ['status' => false, 'code' => 'invalid_panel_channels', 'msg' => '❌ کانال‌های پیش‌فرض این پنل معتبر نیستند. فقط xray، wireguard، openvpn و dns مجاز هستند.', 'channels' => [], 'source' => 'panel_default', 'invalid' => $panelParsed['invalid']];
        }
        return ['status' => true, 'channels' => $panelParsed['channels'], 'source' => 'panel_default', 'frozen' => false, 'invalid' => []];
    }
    return ['status' => true, 'channels' => [], 'source' => 'api_default', 'frozen' => false, 'invalid' => []];
}

    public static function jovadpanelFreezeChannelOrder(array $panel, $idInvoice, $username, array $product = [])
{
    $idInvoice = trim((string) $idInvoice);
    if (($panel['type'] ?? '') !== 'jovadpanel' || $idInvoice === '') {
        $resolved = self::jovadpanelResolveChannels($panel, $product, $idInvoice, $username);
        return $resolved;
    }
    $resolved = self::jovadpanelResolveChannels($panel, $product, $idInvoice, $username);
    if (empty($resolved['status'])) {
        return $resolved;
    }
    if (!empty($resolved['frozen'])) {
        return $resolved;
    }
    if (!self::jovadpanelEnsureSchema()) {
        return ['status' => false, 'code' => 'mapping_unavailable', 'msg' => '❌ ثبت انتخاب کانال‌های سفارش در دیتابیس ناموفق بود؛ خرید انجام نشد.', 'channels' => [], 'source' => $resolved['source']];
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return ['status' => false, 'code' => 'mapping_unavailable', 'msg' => '❌ ثبت انتخاب کانال‌های سفارش در دیتابیس ناموفق بود؛ خرید انجام نشد.', 'channels' => [], 'source' => $resolved['source']];
    }
    $channels = implode(',', $resolved['channels']);
    $source = (string) $resolved['source'];
    $now = time();
    try {
        $stmt = $pdo->prepare("INSERT INTO jovadpanel_orders
            (id_invoice, code_panel, username, kind, devices, days, channels, channel_source, created_at, updated_at)
            VALUES (?, ?, ?, 'metered', 0, 0, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)");
        $stmt->execute([$idInvoice, (string) ($panel['code_panel'] ?? ''), (string) $username, $channels, $source, $now, $now]);
    } catch (Throwable $e) {
        return ['status' => false, 'code' => 'mapping_unavailable', 'msg' => '❌ ثبت انتخاب کانال‌های سفارش در دیتابیس ناموفق بود؛ خرید انجام نشد.', 'channels' => [], 'source' => $source];
    }
    $stored = self::jovadpanelOrderRow($idInvoice);
    if (!is_array($stored) || (string) ($stored['kind'] ?? '') !== 'metered'
        || (string) ($stored['code_panel'] ?? '') !== (string) ($panel['code_panel'] ?? '')
        || (string) ($stored['username'] ?? '') !== (string) $username) {
        return ['status' => false, 'code' => 'order_mismatch', 'msg' => '❌ تثبیت کانال‌های سفارش ناموفق بود؛ خرید انجام نشد.', 'channels' => [], 'source' => $source];
    }
    $storedChannels = trim((string) ($stored['channels'] ?? ''));
    $storedParsed = $storedChannels === '' ? ['channels' => []] : self::jovadpanelParseChannels($storedChannels);
    $storedSource = trim((string) ($stored['channel_source'] ?? $source));
    return ['status' => true, 'channels' => (array) ($storedParsed['channels'] ?? []), 'source' => $storedSource, 'frozen' => true, 'invalid' => []];
}

    public static function jovadpanelInvoiceId($panel, $username, $explicit = null)
{
    if (is_string($explicit) && trim($explicit) !== '') {
        return trim($explicit);
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return '';
    }
    try {
        $stmt = $pdo->prepare("SELECT id_invoice FROM invoice WHERE username = ? AND Service_location = ? ORDER BY time_sell DESC LIMIT 1");
        $stmt->execute([(string) $username, (string) ($panel['name_panel'] ?? '')]);
        $value = $stmt->fetchColumn();
        return $value === false ? '' : (string) $value;
    } catch (Throwable $e) {
        return '';
    }
}

    public static function jovadpanelLabelFor(array $panel, $username, $idInvoice = '')
{
    $prefix = preg_replace('/[^A-Za-z0-9._-]+/', '', (string) $username);
    $prefix = trim(substr((string) $prefix, 0, 20), '._-');
    if ($prefix === '') {
        $prefix = 'jv';
    }
    $salt = substr(sha1((string) ($panel['code_panel'] ?? '') . '|' . (string) $idInvoice . '|' . (string) $username), 0, 10);
    return substr($prefix . '-' . $salt, 0, 40);
}

    public static function jovadpanelMapping($panel, $username)
{
    if (!self::jovadpanelEnsureSchema()) {
        return null;
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM jovadpanel_configs WHERE code_panel = ? AND username = ? LIMIT 1");
        $stmt->execute([(string) ($panel['code_panel'] ?? ''), (string) $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

    public static function jovadpanelMappingUpsert(array $panel, $username, $idInvoice, $label, $remoteId, $subUrl, $proto)
{
    if (!self::jovadpanelEnsureSchema()) {
        return false;
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return false;
    }
    $now = time();
    try {
        $stmt = $pdo->prepare("INSERT INTO jovadpanel_configs
            (code_panel, name_panel, username, id_invoice, remote_id, label, sub_url, proto, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
            ON DUPLICATE KEY UPDATE
                name_panel = VALUES(name_panel),
                id_invoice = COALESCE(NULLIF(VALUES(id_invoice), ''), id_invoice),
                remote_id = COALESCE(NULLIF(VALUES(remote_id), ''), remote_id),
                label = VALUES(label),
                sub_url = COALESCE(NULLIF(VALUES(sub_url), ''), sub_url),
                proto = COALESCE(NULLIF(proto, ''), NULLIF(VALUES(proto), '')),
                status = VALUES(status),
                updated_at = VALUES(updated_at)");
        $stmt->execute([
            (string) ($panel['code_panel'] ?? ''),
            (string) ($panel['name_panel'] ?? ''),
            (string) $username,
            (string) $idInvoice,
            (string) $remoteId,
            (string) $label,
            (string) $subUrl,
            (string) $proto,
            $now,
            $now,
        ]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

    public static function jovadpanelMappingStatus(array $panel, $username, $status)
{
    if (!self::jovadpanelEnsureSchema()) {
        return false;
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("UPDATE jovadpanel_configs SET status = ?, updated_at = ? WHERE code_panel = ? AND username = ?");
        $stmt->execute([(string) $status, time(), (string) ($panel['code_panel'] ?? ''), (string) $username]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

    public static function jovadpanelMappingPrepare(array $panel, $username, $idInvoice, $label, $proto = '')
{
    if (!self::jovadpanelEnsureSchema()) {
        return false;
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return false;
    }
    $now = time();
    $codePanel = (string) ($panel['code_panel'] ?? '');
    try {
        $stmt = $pdo->prepare("INSERT INTO jovadpanel_configs
            (code_panel, name_panel, username, id_invoice, remote_id, label, sub_url, proto, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, NULL, ?, NULL, ?, 'pending', ?, ?)
            ON DUPLICATE KEY UPDATE
                name_panel = VALUES(name_panel),
                id_invoice = VALUES(id_invoice),
                label = VALUES(label),
                remote_id = NULL,
                sub_url = NULL,
                proto = VALUES(proto),
                status = 'pending',
                updated_at = VALUES(updated_at)");
        $stmt->execute([
            $codePanel,
            (string) ($panel['name_panel'] ?? ''),
            (string) $username,
            (string) $idInvoice,
            (string) $label,
            (string) $proto,
            $now,
            $now,
        ]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

    public static function jovadpanelMappingClearRemote(array $panel, $username)
{
    if (!self::jovadpanelEnsureSchema()) {
        return false;
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("UPDATE jovadpanel_configs SET remote_id = NULL, sub_url = NULL, updated_at = ? WHERE code_panel = ? AND username = ?");
        $stmt->execute([time(), (string) ($panel['code_panel'] ?? ''), (string) $username]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

    public static function jovadpanelMappingByRemote(array $panel, $remoteId)
{
    $remoteId = trim((string) $remoteId);
    if ($remoteId === '' || !self::jovadpanelEnsureSchema()) {
        return null;
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM jovadpanel_configs WHERE code_panel = ? AND remote_id = ? LIMIT 1");
        $stmt->execute([(string) ($panel['code_panel'] ?? ''), $remoteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

    public static function jovadpanelUnlimitedCache(array $panel, $scope, $value = null, $invalidate = false)
{
    static $cache = [];
    $key = hash('sha256', (string) ($panel['code_panel'] ?? '') . '|' . (string) ($panel['name_panel'] ?? '') . '|' . (string) ($panel['url_panel'] ?? ''));
    if ($invalidate) {
        unset($cache[$key]);
        return null;
    }
    if ($value !== null) $cache[$key][$scope] = $value;
    return $cache[$key][$scope] ?? null;
}

    public static function jovadpanelUnlimitedStatus(array $panel)
{
    $cached = self::jovadpanelUnlimitedCache($panel, 'status');
    if ($cached !== null) {
        return $cached;
    }
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'unlimited/status'));
    if ($decoded['ok'] !== true) {
        return [
            'status' => false,
            'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code'],
            'options' => [],
            'default_devices' => 0,
            'days' => 0,
            'units' => null,
            'data' => null,
        ];
    }
    $data = $decoded['data'];
    $options = [];
    foreach ((array) ($data['options'] ?? []) as $option) {
        if (!is_array($option)) {
            continue;
        }
        $devices = (int) ($option['devices'] ?? 0);
        if ($devices <= 0) {
            continue;
        }
        $options[$devices] = [
            'devices' => $devices,
            'min_days' => (int) ($option['minDays'] ?? 0),
            'max_days' => (int) ($option['maxDays'] ?? 0),
            'price_toman' => isset($option['priceToman']) ? (float) $option['priceToman'] : null,
        ];
    }
    ksort($options);
    return self::jovadpanelUnlimitedCache($panel, 'status', [
        'status' => true,
        'enabled' => !(isset($data['enabled']) && $data['enabled'] === false),
        'options' => array_values($options),
        'default_devices' => (int) ($data['defaultDevices'] ?? 0),
        'days' => (int) ($data['days'] ?? 0),
        'units' => isset($data['wallet']['sellerUnlimitedUnits']) && is_numeric($data['wallet']['sellerUnlimitedUnits'])
            ? (float) $data['wallet']['sellerUnlimitedUnits']
            : null,
        'data' => $data,
    ]);
}

    public static function jovadpanelUnlimitedDevices(array $panel)
{
    $raw = trim((string) ($panel['jovad_devices'] ?? ''));
    return ($raw !== '' && is_numeric($raw)) ? max(0, (int) $raw) : 0;
}

    public static function jovadpanelUnlimitedTier(array $panel, $devices, $days)
{
    $status = self::jovadpanelUnlimitedStatus($panel);
    if ($status['status'] !== true) {
        return ['status' => false, 'code' => 'unlimited_status_unavailable', 'msg' => 'دریافت اطلاعات اشتراک نامحدود از پنل ناموفق بود: ' . (string) $status['msg']];
    }
    if (empty($status['enabled'])) {
        return ['status' => false, 'code' => 'unlimited_disabled', 'msg' => 'فروش اشتراک نامحدود در پنل فعال نیست.'];
    }
    if (empty($status['options'])) {
        return ['status' => false, 'code' => 'unlimited_tier_unavailable', 'msg' => 'پنل هیچ پلن دستگاهی برای اشتراک نامحدود اعلام نکرده است.'];
    }
    $wanted = (int) $devices;
    $chosen = null;
    foreach ($status['options'] as $option) {
        if ((int) $option['devices'] === $wanted) {
            $chosen = $option;
            break;
        }
    }
    if ($chosen === null) {
        return ['status' => false, 'code' => 'unlimited_tier_unavailable', 'msg' => 'تعداد دستگاه انتخاب‌شده برای اشتراک نامحدود در پنل موجود نیست.'];
    }
    $days = (int) $days;
    if ($days > 0) {
        if ($chosen['min_days'] > 0 && $days < $chosen['min_days']) {
            return ['status' => false, 'code' => 'unlimited_term_unsupported', 'msg' => "حداقل مدت این پلن {$chosen['min_days']} روز است."];
        }
        if ($chosen['max_days'] > 0 && $days > $chosen['max_days']) {
            return ['status' => false, 'code' => 'unlimited_term_unsupported', 'msg' => "حداکثر مدت این پلن {$chosen['max_days']} روز است."];
        }
    }
    return ['status' => true, 'option' => $chosen, 'days' => $days];
}

    public static function jovadpanelFreezeUnlimitedOrder(array $panel, $idInvoice, $username, $days)
{
    if (!self::jovadpanelEnsureSchema()) {
        return ['status' => false, 'msg' => 'ثبت سفارش اشتراک نامحدود در دیتابیس ناموفق بود.'];
    }
    $idInvoice = trim((string) $idInvoice);
    if ($idInvoice === '') {
        return ['status' => false, 'msg' => 'شناسه فاکتور برای ثبت سفارش نامحدود موجود نیست.'];
    }
    $storedOrder = self::jovadpanelOrderRow($idInvoice);
    if (is_array($storedOrder)) {
        if ((string) ($storedOrder['code_panel'] ?? '') !== (string) ($panel['code_panel'] ?? '')
            || (string) ($storedOrder['username'] ?? '') !== (string) $username
            || ($storedOrder['kind'] ?? '') !== 'unlimited') {
            return ['status' => false, 'msg' => 'مشخصات سفارش نامحدود با فاکتور هم‌خوان نیست.'];
        }
        $devices = (int) ($storedOrder['devices'] ?? 0);
        $days = $storedOrder['days'] ?? 0;
    } else {
        $devices = self::jovadpanelUnlimitedDevices($panel);
    }
    if (!is_numeric($days) || (float) $days <= 0 || floor((float) $days) != (float) $days || (float) $days > PHP_INT_MAX) {
        return ['status' => false, 'msg' => 'مدت محصول نامحدود باید تعداد روزِ صحیح و بیشتر از صفر باشد؛ زمان نامحدود پشتیبانی نمی‌شود. مدت محصول را اصلاح کنید.'];
    }
    $days = (int) $days;
    if ($devices === 0 && !is_array($storedOrder)) {
        $status = self::jovadpanelUnlimitedStatus($panel);
        if ($status['status'] === true) {
            $devices = (int) $status['default_devices'];
        }
    }
    if ($devices === 0) {
        return ['status' => false, 'msg' => 'تعداد دستگاه اشتراک نامحدود برای این پنل انتخاب نشده است؛ ابتدا از مدیریت پنل آن را تنظیم کنید.'];
    }
    $tier = self::jovadpanelUnlimitedTier($panel, $devices, (int) $days);
    if ($tier['status'] !== true) {
        return ['status' => false, 'msg' => (string) $tier['msg']];
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return ['status' => false, 'msg' => 'ثبت سفارش اشتراک نامحدود در دیتابیس ناموفق بود.'];
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO jovadpanel_orders (id_invoice, code_panel, username, kind, devices, days, created_at, updated_at) VALUES (?, ?, ?, 'unlimited', ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id_invoice = VALUES(id_invoice)");
        $now = time();
        if (!$stmt->execute([$idInvoice, (string) ($panel['code_panel'] ?? ''), (string) $username, (int) $devices, max(0, (int) $days), $now, $now])) {
            return ['status' => false, 'msg' => 'ثبت سفارش اشتراک نامحدود در دیتابیس ناموفق بود.'];
        }
    } catch (Throwable $e) {
        return ['status' => false, 'msg' => 'ثبت سفارش اشتراک نامحدود در دیتابیس ناموفق بود.'];
    }
    $frozen = self::jovadpanelOrderRow($idInvoice);
    if (!is_array($frozen) || ($frozen['kind'] ?? '') !== 'unlimited'
        || (string) ($frozen['code_panel'] ?? '') !== (string) ($panel['code_panel'] ?? '')
        || (string) ($frozen['username'] ?? '') !== (string) $username
        || (int) ($frozen['devices'] ?? 0) <= 0 || (int) ($frozen['days'] ?? 0) <= 0) {
        return ['status' => false, 'msg' => 'تثبیت مشخصات سفارش نامحدود ناموفق بود؛ پرداخت انجام ندهید.'];
    }
    $devices = (int) $frozen['devices'];
    $days = (int) $frozen['days'];
    $frozenTier = self::jovadpanelUnlimitedTier($panel, $devices, $days);
    if (empty($frozenTier['status'])) return ['status' => false, 'msg' => (string) $frozenTier['msg']];
    return ['status' => true, 'devices' => $devices, 'days' => max(0, (int) $days)];
}

    public static function jovadpanelOrderRow($idInvoice)
{
    $idInvoice = trim((string) $idInvoice);
    if ($idInvoice === '' || !self::jovadpanelEnsureSchema()) {
        return null;
    }
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM jovadpanel_orders WHERE id_invoice = ? LIMIT 1");
        if (!$stmt->execute([$idInvoice])) {
            return null;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

    public static function jovadpanelUnlimitedList(array $panel)
{
    $cached = self::jovadpanelUnlimitedCache($panel, 'list');
    if ($cached !== null) {
        return $cached;
    }
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'unlimited'));
    if ($decoded['ok'] !== true) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code'], 'configs' => []];
    }
    $configs = [];
    foreach ((array) ($decoded['data']['configs'] ?? []) as $cfg) {
        if (is_array($cfg) && (string) ($cfg['id'] ?? '') !== '') {
            $configs[] = $cfg;
        }
    }
    return self::jovadpanelUnlimitedCache($panel, 'list', ['status' => true, 'configs' => $configs]);
}

    public static function jovadpanelUnlimitedLookup(array $panel, $remoteId)
{
    $remoteId = trim((string) $remoteId);
    if ($remoteId === '') {
        return ['status' => 'missing'];
    }
    $list = self::jovadpanelUnlimitedList($panel);
    if ($list['status'] !== true) {
        return ['status' => 'error', 'msg' => (string) $list['msg']];
    }
    $row = null;
    foreach ($list['configs'] as $cfg) {
        if ((string) ($cfg['id'] ?? '') === $remoteId) {
            $row = $cfg;
            break;
        }
    }
    if ($row === null) {
        $detail = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'unlimited/' . rawurlencode($remoteId)));
        if ($detail['ok'] === true) {
            return ['status' => 'error', 'msg' => 'unlimited identity is not yet visible in the authoritative list'];
        }
        if ((int) $detail['http'] !== 404 && ($detail['code'] ?? '') !== 'not_found') {
            return ['status' => 'error', 'msg' => $detail['message'] !== '' ? $detail['message'] : $detail['code']];
        }
        return ['status' => 'missing'];
    }
    $row['unlimited'] = true;
    $row['proto'] = 'unlimited';
    $detail = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'unlimited/' . rawurlencode($remoteId)));
    if ($detail['ok'] === true) {
        foreach (['label', 'devices', 'subUrl', 'daysLeft', 'expiresAt'] as $field) {
            if (array_key_exists($field, $detail['data'])) {
                $row[$field] = $detail['data'][$field];
            }
        }
        if (is_array($detail['data']['links'] ?? null)) {
            $row['links'] = $detail['data']['links'];
        }
    }
    return ['status' => 'found', 'config' => $row];
}

    public static function jovadpanelUnlimitedFindByLabel(array $panel, $label)
{
    $label = trim((string) $label);
    if ($label === '') {
        return ['status' => 'missing'];
    }
    $list = self::jovadpanelUnlimitedList($panel);
    if ($list['status'] !== true) {
        return ['status' => 'error', 'msg' => (string) $list['msg']];
    }
    foreach ($list['configs'] as $cfg) {
        if ((string) ($cfg['label'] ?? '') === $label) {
            $cfg['unlimited'] = true;
            $cfg['proto'] = 'unlimited';
            return ['status' => 'found', 'config' => $cfg];
        }
    }
    return ['status' => 'missing'];
}

    public static function jovadpanelNormalizeUnlimited(array $panel, array $cfg, $subUrlFallback = '')
{
    $rawStatus = strtolower(trim((string) ($cfg['status'] ?? '')));
    $accountStatus = $rawStatus !== '' ? $rawStatus : 'active';
    $expire = 0;
    if (array_key_exists('expiresAt', $cfg) && $cfg['expiresAt'] !== null) {
        $parsed = self::jovadpanelIsoToTs($cfg['expiresAt']);
        $expire = $parsed === null ? 0 : $parsed;
    } elseif (isset($cfg['daysLeft']) && is_numeric($cfg['daysLeft'])) {
        $expire = time() + (int) round(((float) $cfg['daysLeft']) * 86400);
    }
    $now = time();
    if ($accountStatus === 'active') {
        $status = ($expire > 0 && $expire <= $now) ? 'expired' : 'active';
    } elseif ($accountStatus === 'paused') {
        $status = 'disabled';
    } elseif ($accountStatus === 'expired') {
        $status = 'expired';
    } else {
        $status = $accountStatus === '' ? 'active' : 'disabled';
    }
    $links = [];
    foreach ((array) ($cfg['links'] ?? []) as $link) {
        if (is_string($link) && trim($link) !== '') {
            $links[] = trim($link);
        } elseif (is_array($link) && isset($link['uri']) && is_string($link['uri']) && trim($link['uri']) !== '') {
            $links[] = trim($link['uri']);
        }
    }
    $links = array_values(array_unique($links));
    $subUrl = trim((string) ($cfg['subUrl'] ?? ''));
    if ($subUrl === '') {
        $subUrl = trim((string) $subUrlFallback);
    }
    return [
        'status' => $status,
        'username' => (string) ($cfg['label'] ?? ''),
        'data_limit' => 0,
        'used_traffic' => 0,
        'expire' => $expire,
        'data_limit_reset' => 'no_reset',
        'account_status' => $accountStatus,
        'paused_by' => '',
        'online_at' => null,
        'links' => $links,
        'configs' => $links,
        'subscription_url' => $subUrl,
        'sub_updated_at' => null,
        'sub_last_user_agent' => null,
        'links_diagnostic' => [
            'panel_type' => 'jovadpanel',
            'outcome' => empty($links) ? 'empty' : 'ok',
        ],
        'remote_id' => (string) ($cfg['id'] ?? ''),
        'label' => (string) ($cfg['label'] ?? ''),
        'proto' => 'unlimited',
        'unlimited' => true,
        'devices' => isset($cfg['devices']) ? (int) $cfg['devices'] : null,
    ];
}

    public static function jovadpanelFindByLabel(array $panel, $label, $proto = '')
{
    if (strtolower((string) $proto) === 'unlimited') {
        return self::jovadpanelUnlimitedFindByLabel($panel, $label);
    }
    $label = trim((string) $label);
    if ($label === '') {
        return ['status' => 'missing'];
    }
    $list = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'configs?status=all&limit=100&q=' . urlencode($label)));
    if ($list['ok'] !== true) {
        return ['status' => 'error', 'msg' => $list['message'] !== '' ? $list['message'] : $list['code']];
    }
    foreach ((array) ($list['data']['configs'] ?? []) as $cfg) {
        if (is_array($cfg) && (string) ($cfg['label'] ?? '') === $label) {
            return ['status' => 'found', 'config' => $cfg];
        }
    }
    $skip = 0;
    while ($skip < 1000) {
        $finished = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'configs/finished?limit=200&skip=' . $skip));
        if ($finished['ok'] !== true) {
            return ['status' => 'error', 'msg' => $finished['message'] !== '' ? $finished['message'] : $finished['code']];
        }
        $rows = (array) ($finished['data']['configs'] ?? []);
        foreach ($rows as $cfg) {
            if (is_array($cfg) && (string) ($cfg['label'] ?? '') === $label) {
                return ['status' => 'found', 'config' => $cfg];
            }
        }
        if (empty($finished['data']['hasMore']) || count($rows) === 0) {
            return ['status' => 'missing'];
        }
        $skip += 200;
    }
    return ['status' => 'missing'];
}

    public static function jovadpanelConfigLookup(array $panel, $remoteId, $proto = '')
{
    if (strtolower((string) $proto) === 'unlimited') {
        return self::jovadpanelUnlimitedLookup($panel, $remoteId);
    }
    $remoteId = trim((string) $remoteId);
    if ($remoteId === '') {
        return ['status' => 'missing'];
    }
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'configs/' . rawurlencode($remoteId)));
    if ($decoded['ok'] === true) {
        return ['status' => 'found', 'config' => $decoded['data']];
    }
    if ((int) $decoded['http'] === 404 || $decoded['code'] === 'not_found') {
        return ['status' => 'missing'];
    }
    return ['status' => 'error', 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
}

    public static function jovadpanelFetchConfig(array $panel, $remoteId)
{
    $lookup = self::jovadpanelConfigLookup($panel, $remoteId);
    return $lookup['status'] === 'found' ? $lookup['config'] : null;
}

    public static function jovadpanelEnrichConfigChannels(array $panel, array $cfg)
{
    static $cache = [];
    $parsed = self::jovadpanelParseChannels($cfg['channels'] ?? null);
    if (!empty($parsed['channels'])) {
        return $cfg;
    }
    $proto = strtolower(trim((string) ($cfg['proto'] ?? '')));
    if ($proto !== '' && $proto !== 'both') {
        return $cfg;
    }
    $label = trim((string) ($cfg['label'] ?? ''));
    if ($label === '') {
        return $cfg;
    }
    $remoteId = trim((string) ($cfg['id'] ?? ''));
    $key = hash('sha256', (string) ($panel['code_panel'] ?? '') . '|' . $remoteId . '|' . $label);
    if (array_key_exists($key, $cache)) {
        if (!empty($cache[$key])) {
            $cfg['channels'] = $cache[$key];
        }
        return $cfg;
    }
    $cache[$key] = [];
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'configs?status=all&limit=100&q=' . urlencode($label)));
    if ($decoded['ok'] !== true) {
        return $cfg;
    }
    foreach ((array) ($decoded['data']['configs'] ?? []) as $row) {
        if (!is_array($row) || (string) ($row['label'] ?? '') !== $label) {
            continue;
        }
        if ($remoteId !== '' && (string) ($row['id'] ?? '') !== $remoteId) {
            continue;
        }
        $rowParsed = self::jovadpanelParseChannels($row['channels'] ?? null);
        if (empty($rowParsed['channels'])) {
            continue;
        }
        $cache[$key] = $rowParsed['channels'];
        $cfg['channels'] = $rowParsed['channels'];
        if (trim((string) ($cfg['proto'] ?? '')) === '' && isset($row['proto'])) {
            $cfg['proto'] = $row['proto'];
        }
        return $cfg;
    }
    return $cfg;
}

    public static function jovadpanelResolve(array $panel, $username)
{
    if (!self::jovadpanelEnsureSchema()) {
        return ['ok' => false, 'reason' => 'error', 'msg' => 'mapping unavailable'];
    }
    $row = self::jovadpanelMapping($panel, $username);
    $rowProto = is_array($row) ? strtolower((string) ($row['proto'] ?? '')) : '';
    if (is_array($row) && trim((string) ($row['remote_id'] ?? '')) !== '') {
        $lookup = self::jovadpanelConfigLookup($panel, $row['remote_id'], $rowProto);
        if ($lookup['status'] === 'found') {
            return ['ok' => true, 'row' => $row, 'config' => self::jovadpanelEnrichConfigChannels($panel, $lookup['config'])];
        }
        if ($lookup['status'] === 'error') {
            return ['ok' => false, 'reason' => 'error', 'msg' => (string) ($lookup['msg'] ?? 'panel read failed')];
        }
        $found = self::jovadpanelFindByLabel($panel, (string) ($row['label'] ?? ''), $rowProto);
        if (($found['status'] ?? '') !== 'found') {
            return ['ok' => false, 'reason' => 'error', 'msg' => 'remote identity requires reconciliation'];
        }
        $cfg = $found['config'];
        if (!self::jovadpanelMappingUpsert($panel, $username, $row['id_invoice'] ?? '', $row['label'], $cfg['id'] ?? '', $cfg['subUrl'] ?? '', $row['proto'] ?? ($cfg['proto'] ?? ''))) {
            return ['ok' => false, 'reason' => 'error', 'msg' => 'mapping update failed'];
        }
        return ['ok' => true, 'row' => self::jovadpanelMapping($panel, $username), 'config' => $cfg];
    }
    $rowStatus = is_array($row) ? strtolower((string) ($row['status'] ?? '')) : '';
    if ($rowStatus === 'orphan' || $rowStatus === 'deleted') {
        return ['ok' => false, 'reason' => 'missing'];
    }
    $idInvoice = is_array($row) ? (string) ($row['id_invoice'] ?? '') : '';
    if ($idInvoice === '') {
        $idInvoice = self::jovadpanelInvoiceId($panel, $username);
    }
    $label = is_array($row) && trim((string) ($row['label'] ?? '')) !== ''
        ? (string) $row['label']
        : self::jovadpanelLabelFor($panel, $username, $idInvoice);
    $found = self::jovadpanelFindByLabel($panel, $label, $rowProto);
    if ($found['status'] === 'error') {
        return ['ok' => false, 'reason' => 'error', 'msg' => (string) ($found['msg'] ?? 'panel read failed')];
    }
    if ($found['status'] !== 'found') {
        if (in_array($rowStatus, ['creating', 'uncertain'], true)) {
            return ['ok' => false, 'reason' => 'error', 'msg' => 'creation result is pending reconciliation'];
        }
        return ['ok' => false, 'reason' => 'missing'];
    }
    $cfg = $found['config'];
    $remoteId = (string) ($cfg['id'] ?? '');
    if ($remoteId === '') {
        return ['ok' => false, 'reason' => 'missing'];
    }
    self::jovadpanelMappingUpsert($panel, $username, $idInvoice, $label, $remoteId, (string) ($cfg['subUrl'] ?? ''), (string) ($cfg['proto'] ?? ''));
    $freshRow = self::jovadpanelMapping($panel, $username);
    return ['ok' => true, 'row' => is_array($freshRow) ? $freshRow : array(), 'config' => $cfg];
}

    public static function jovadpanelLinkDisplayName($uri, $name)
{
    $name = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $name));
    if ($name === '' || strpos($uri, '#') !== false || preg_match('#^(?:https?|vmess)://#i', $uri)) {
        return $uri;
    }
    return $uri . '#' . $name;
}

    public static function jovadpanelNormalizeLinks(array $entries)
{
    $links = [];
    $seen = [];
    foreach ($entries as $entry) {
        if (is_string($entry)) {
            $value = trim($entry);
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $links[] = $value;
            continue;
        }
        if (is_array($entry)) {
            $uri = isset($entry['uri']) && is_string($entry['uri']) ? trim($entry['uri']) : '';
            if ($uri === '') {
                continue;
            }
            $name = isset($entry['name']) && is_scalar($entry['name']) ? (string) $entry['name'] : '';
            $value = self::jovadpanelLinkDisplayName($uri, $name);
            if (isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $links[] = $value;
        }
    }
    return $links;
}

    public static function jovadpanelFetchLinks(array $panel, $remoteId)
{
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'configs/' . rawurlencode((string) $remoteId) . '/links'));
    if ($decoded['ok'] !== true) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
    }
    $links = self::jovadpanelNormalizeLinks((array) ($decoded['data']['links'] ?? []));
    return [
        'status' => true,
        'links' => array_values(array_unique($links)),
        'sub_url' => is_string($decoded['data']['subUrl'] ?? null) ? $decoded['data']['subUrl'] : '',
        'protocol_files' => is_array($decoded['data']['protocolFiles'] ?? null) ? $decoded['data']['protocolFiles'] : [],
    ];
}

    public static function jovadpanelProtocolMarker($kind, $remoteId)
{
    $kind = strtolower((string) $kind);
    if ($kind === 'openvpn') {
        return 'jovadovpn://' . rawurlencode((string) $remoteId) . '#OpenVPN';
    }
    if ($kind === 'dns') {
        return 'jovaddns://' . rawurlencode((string) $remoteId) . '#DNS';
    }
    return 'jovadwg://' . rawurlencode((string) $remoteId) . '#WireGuard';
}

    public static function jovadIsProtocolMarker($value)
{
    return is_string($value) && preg_match('~^jovad(wg|ovpn|dns)://~i', $value) === 1;
}

    public static function rxDeliveryTextConfigs($configs)
{
    if (!is_array($configs)) {
        return [];
    }
    $result = [];
    foreach ($configs as $item) {
        if (!is_string($item) || trim($item) === '' || self::jovadIsProtocolMarker($item)) {
            continue;
        }
        $result[] = $item;
    }
    return $result;
}

    public static function jovadpanelDeliveryConfigs(array $panel, $remoteId, $proto, $channels = null, $discoverFiles = true)
{
    $configs = [];
    if ((string) ($panel['config'] ?? '') !== 'onconfig') {
        return $configs;
    }
    $channels = is_array($channels) ? self::jovadpanelCanonicalChannels($channels) : self::jovadpanelChannelsFromProto($proto);
    $links = self::jovadpanelFetchLinks($panel, $remoteId);
    if ($links['status'] === true) {
        foreach ($links['links'] as $link) {
            if (self::jovadIsProtocolMarker($link)) {
                continue;
            }
            $configs[] = $link;
        }
        $protocolFiles = $discoverFiles ? (array) ($links['protocol_files'] ?? []) : [];
        foreach (['wireguard' => ['wireguardPage', 'wgPage'], 'openvpn' => ['openvpnPage', 'ovpnPage']] as $channel => $keys) {
            foreach ($keys as $key) {
                if (is_string($protocolFiles[$key] ?? null) && trim($protocolFiles[$key]) !== '') {
                    $channels[] = $channel;
                    break;
                }
            }
        }
        if (is_string($protocolFiles['json'] ?? null)
            && (!in_array('wireguard', $channels, true) || !in_array('openvpn', $channels, true))) {
            $baseUrl = trim((string) ($links['sub_url'] ?? ''));
            if ($baseUrl === '') {
                $mapping = self::jovadpanelMappingByRemote($panel, $remoteId);
                $baseUrl = is_array($mapping) ? (string) ($mapping['sub_url'] ?? '') : '';
            }
            $jsonUrl = self::jovadpanelResolveUrl($protocolFiles['json'], $baseUrl);
            if ($jsonUrl !== '') {
                $subscription = self::jovadpanelFetchSubJson($jsonUrl, $baseUrl);
                if ($subscription['status'] === true) {
                    foreach (['wireguard', 'openvpn'] as $channel) {
                        if (!empty(self::jovadpanelProtocolRows($subscription['data'], $channel))) {
                            $channels[] = $channel;
                        }
                    }
                }
            }
        }
    }
    $channels = self::jovadpanelCanonicalChannels($channels);
    foreach ($channels as $channel) {
        if (in_array($channel, ['wireguard', 'openvpn', 'dns'], true)) {
            $configs[] = self::jovadpanelProtocolMarker($channel, $remoteId);
        }
    }
    $configs = array_values(array_unique($configs));
    return $configs;
}

    public static function jovadpanelNormalizeConfig(array $panel, array $cfg, $subUrlFallback = '', $protoFallback = '', $includeLinks = true)
{
    $limit = self::jovadpanelGbToBytes($cfg['limitGb'] ?? null);
    $used = self::jovadpanelGbToBytes($cfg['usedGb'] ?? null);
    $remaining = self::jovadpanelGbToBytes($cfg['remainingGb'] ?? null);
    if ($limit === null || $used === null || !array_key_exists('expiresAt', $cfg)) {
        return ['status' => 'Unsuccessful', 'msg' => 'malformed panel response'];
    }
    $expire = $cfg['expiresAt'] === null ? 0 : self::jovadpanelIsoToTs($cfg['expiresAt']);
    if ($expire === null) {
        return ['status' => 'Unsuccessful', 'msg' => 'malformed panel response'];
    }
    if ($used < 0) {
        $used = 0;
    }
    if ($remaining === null) {
        $remaining = max(0, $limit - $used);
    }
    $rawStatus = strtolower(trim((string) ($cfg['status'] ?? '')));
    $accountStatus = $rawStatus !== '' ? $rawStatus : 'active';
    $pausedBy = strtolower(trim((string) ($cfg['pausedBy'] ?? '')));
    $now = time();
    if ($rawStatus === 'active') {
        $status = 'active';
    } elseif ($rawStatus === 'paused') {
        $status = $pausedBy === 'credit' ? 'Unknown' : 'disabled';
    } else {
        $disableReason = strtolower(trim((string) ($cfg['disableReason'] ?? '')));
        if ($expire > 0 && $expire <= $now) {
            $status = 'expired';
        } elseif ($remaining <= 0 && $limit > 0) {
            $status = 'limited';
        } elseif ($disableReason === 'traffic_limit') {
            $status = 'limited';
        } elseif ($disableReason === 'time' || $disableReason === 'expiry') {
            $status = 'expired';
        } else {
            $status = 'disabled';
        }
    }
    $channelInfo = self::jovadpanelChannelsFromConfig($cfg, (string) $protoFallback);
    $channels = (array) $channelInfo['channels'];
    $proto = !empty($channels) ? implode(',', $channels) : trim((string) ($cfg['proto'] ?? ''));
    if ($proto === '') {
        $proto = trim((string) $protoFallback);
    }
    $subUrl = trim((string) ($cfg['subUrl'] ?? ''));
    if ($subUrl === '') {
        $subUrl = trim((string) $subUrlFallback);
    }
    $links = $includeLinks ? self::jovadpanelDeliveryConfigs($panel, (string) ($cfg['id'] ?? ''), $proto, $channels, $channelInfo['source'] !== 'remote_channels') : [];
    return [
        'status' => $status,
        'username' => (string) ($cfg['label'] ?? ''),
        'data_limit' => $limit,
        'used_traffic' => $used,
        'expire' => $expire,
        'data_limit_reset' => 'no_reset',
        'account_status' => $accountStatus,
        'paused_by' => $pausedBy,
        'online_at' => null,
        'links' => $links,
        'configs' => $links,
        'subscription_url' => $subUrl,
        'sub_updated_at' => null,
        'sub_last_user_agent' => null,
        'links_diagnostic' => [
            'panel_type' => 'jovadpanel',
            'outcome' => empty($links) ? 'empty' : 'ok',
        ],
        'remote_id' => (string) ($cfg['id'] ?? ''),
        'label' => (string) ($cfg['label'] ?? ''),
        'proto' => $proto,
        'channels' => $channels,
        'channels_source' => (string) ($channelInfo['source'] ?? ''),
        'remaining_bytes' => $remaining,
    ];
}

    public static function jovadpanelResolveError(array $resolved)
{
    return ($resolved['reason'] ?? '') === 'error'
        ? 'panel read failed: ' . (string) ($resolved['msg'] ?? '')
        : 'user not found on panel';
}

    public static function jovadpanelDataUser(array $panel, $username)
{
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        if (($resolved['reason'] ?? '') === 'error') {
            return ['status' => 'Unsuccessful', 'msg' => 'panel read failed'];
        }
        return ['status' => 'Unsuccessful', 'msg' => 'user not found on panel'];
    }
    $cfg = is_array($resolved['config'] ?? null) ? $resolved['config'] : [];
    $subFallback = (string) ($resolved['row']['sub_url'] ?? '');
    $protoFallback = (string) ($resolved['row']['proto'] ?? '');
    if (empty(self::jovadpanelParseChannels($cfg['channels'] ?? null)['channels'])) {
        $frozenOrder = self::jovadpanelOrderRow((string) ($resolved['row']['id_invoice'] ?? ''));
        if (is_array($frozenOrder) && (string) ($frozenOrder['kind'] ?? '') === 'metered'
            && (string) ($frozenOrder['code_panel'] ?? '') === (string) ($panel['code_panel'] ?? '')
            && (string) ($frozenOrder['username'] ?? '') === (string) $username) {
            $frozenChannels = self::jovadpanelParseChannels($frozenOrder['channels'] ?? null);
            if (!empty($frozenChannels['channels'])) {
                $protoFallback = implode(',', $frozenChannels['channels']);
            }
        }
    }
    $normalized = strtolower($protoFallback) === 'unlimited'
        ? self::jovadpanelNormalizeUnlimited($panel, $cfg, $subFallback)
        : self::jovadpanelNormalizeConfig($panel, $cfg, $subFallback, $protoFallback);
    if (($normalized['status'] ?? '') !== 'Unsuccessful') {
        $normalized['username'] = (string) $username;
        $normalized['jovad_renewal_state'] = json_decode((string) ($resolved['row']['renewal_state'] ?? ''), true);
    }
    return $normalized;
}

    public static function jovadpanelExtendRemote(array $panel, $remoteId, $gb = null, $days = null)
{
    $payload = [];
    if ($gb !== null && (float) $gb > 0) {
        $payload['gb'] = round((float) $gb, 3);
    }
    if ($days !== null && (int) $days > 0) {
        $payload['days'] = (int) $days;
    }
    if (empty($payload)) {
        return ['status' => true, 'msg' => ''];
    }
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'POST', 'configs/' . rawurlencode((string) $remoteId) . '/extend', $payload));
    if ($decoded['ok'] !== true) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
    }
    return ['status' => true, 'data' => $decoded['data']];
}

    public static function jovadpanelSetExpiryRemote(array $panel, $remoteId, $days = null, $noExpiry = false)
{
    $payload = $noExpiry ? ['noExpiry' => true] : ['days' => max(1, (int) $days)];
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'PUT', 'configs/' . rawurlencode((string) $remoteId) . '/expiry', $payload));
    if ($decoded['ok'] !== true) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
    }
    return ['status' => true, 'data' => $decoded['data']];
}

    public static function jovadpanelResetTrafficRemote(array $panel, $remoteId)
{
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'POST', 'configs/' . rawurlencode((string) $remoteId) . '/reset-traffic'));
    if ($decoded['ok'] !== true) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
    }
    return ['status' => true, 'data' => $decoded['data']];
}

    public static function jovadpanelReviveRemote(array $panel, $remoteId, $gb, $days = null)
{
    $payload = ['gb' => round((float) $gb, 3)];
    if ($days !== null && (int) $days > 0) {
        $payload['days'] = (int) $days;
    }
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'POST', 'configs/' . rawurlencode((string) $remoteId) . '/revive', $payload));
    if ($decoded['ok'] !== true) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
    }
    return ['status' => true, 'data' => $decoded['data']];
}

    public static function jovadpanelPauseRemote(array $panel, $remoteId, $proto = '')
{
    $prefix = strtolower((string) $proto) === 'unlimited' ? 'unlimited/' : 'configs/';
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'POST', $prefix . rawurlencode((string) $remoteId) . '/pause'));
    if ($decoded['ok'] !== true) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
    }
    return ['status' => true, 'data' => $decoded['data']];
}

    public static function jovadpanelResumeRemote(array $panel, $remoteId, $proto = '')
{
    $prefix = strtolower((string) $proto) === 'unlimited' ? 'unlimited/' : 'configs/';
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'POST', $prefix . rawurlencode((string) $remoteId) . '/resume'));
    if ($decoded['ok'] !== true) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
    }
    return ['status' => true, 'data' => $decoded['data']];
}

    public static function jovadpanelDeleteRemote(array $panel, $remoteId, $proto = '')
{
    $prefix = strtolower((string) $proto) === 'unlimited' ? 'unlimited/' : 'configs/';
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'DELETE', $prefix . rawurlencode((string) $remoteId)));
    if ($decoded['ok'] !== true && (int) $decoded['http'] !== 404) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
    }
    return ['status' => true, 'data' => $decoded['data']];
}

    public static function jovadpanelRotateRemote(array $panel, $remoteId, $proto = '')
{
    $prefix = strtolower((string) $proto) === 'unlimited' ? 'unlimited/' : 'configs/';
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'POST', $prefix . rawurlencode((string) $remoteId) . '/rotate-link'));
    if ($decoded['ok'] !== true) {
        return ['status' => false, 'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code']];
    }
    return ['status' => true, 'data' => $decoded['data']];
}

    public static function jovadpanelUnlimitedOutput(array $panel, $username, array $cfg, array $row)
{
    global $domainhosts;
    $remoteId = (string) ($cfg['id'] ?? ($row['remote_id'] ?? ''));
    $subUrl = trim((string) ($cfg['subUrl'] ?? ''));
    if ($subUrl === '') {
        $subUrl = trim((string) ($row['sub_url'] ?? ''));
    }
    $links = [];
    foreach ((array) ($cfg['links'] ?? []) as $link) {
        if (is_string($link) && trim($link) !== '') {
            $links[] = trim($link);
        } elseif (is_array($link) && isset($link['uri']) && is_string($link['uri']) && trim($link['uri']) !== '') {
            $links[] = trim($link['uri']);
        }
    }
    $links = array_values(array_unique($links));
    if ((string) ($panel['config'] ?? '') !== 'onconfig') {
        $links = [];
    }
    if ((string) ($panel['subvip'] ?? '') === 'onsubvip') {
        $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "username", (string) $username, "select");
        if (is_array($invoice)) {
            $subUrl = "https://$domainhosts/sub/" . $invoice['id_invoice'];
        }
    }
    return [
        'status' => 'successful',
        'username' => (string) $username,
        'subscription_url' => $subUrl,
        'configs' => $links,
        'remote_id' => $remoteId,
        'label' => (string) ($cfg['label'] ?? ($row['label'] ?? '')),
        'unlimited' => true,
        'devices' => isset($cfg['devices']) ? (int) $cfg['devices'] : (int) ($row['devices'] ?? 0),
    ];
}

    public static function jovadpanelCreateUnlimitedUser(array $panel, $username, array $dataConfig, $note)
{
    if (!self::jovadpanelEnsureSchema()) {
        return ['status' => 'Unsuccessful', 'msg' => 'jovad_mapping_unavailable'];
    }
    $idInvoice = self::jovadpanelInvoiceId($panel, $username, $dataConfig['id_invoice'] ?? ($dataConfig['invoice'] ?? null));
    $order = self::jovadpanelOrderRow($idInvoice);
    if (!is_array($order) || (string) ($order['kind'] ?? '') !== 'unlimited'
        || (string) ($order['code_panel'] ?? '') !== (string) ($panel['code_panel'] ?? '')
        || (string) ($order['username'] ?? '') !== (string) $username) {
        return ['status' => 'Unsuccessful', 'msg' => 'این محصول نامحدود است اما سفارش آن با تنظیمات اشتراک نامحدود ثبت نشده است؛ خرید انجام نشد.', 'code' => 'unlimited_order_missing'];
    }
    $devices = (int) ($order['devices'] ?? 0);
    $days = max(0, (int) ($order['days'] ?? 0));
    if ($devices <= 0 || $days <= 0) {
        return ['status' => 'Unsuccessful', 'msg' => 'تعداد دستگاه و مدت این سفارش نامحدود معتبر نیست؛ سفارش باید پیش از پرداخت با مدت مشخص ثبت شود.', 'code' => 'unlimited_order_invalid'];
    }
    $tier = $devices > 0
        ? self::jovadpanelUnlimitedTier($panel, $devices, $days)
        : ['status' => false, 'code' => 'unlimited_tier_unavailable', 'msg' => 'تعداد دستگاه اشتراک نامحدود برای این سفارش مشخص نیست.'];
    if ($tier['status'] !== true) {
        return ['status' => 'Unsuccessful', 'msg' => (string) $tier['msg'], 'code' => (string) ($tier['code'] ?? 'unlimited_tier_unavailable')];
    }
    $row = self::jovadpanelMapping($panel, $username);
    if (is_array($row) && $idInvoice !== '' && (string) ($row['id_invoice'] ?? '') !== (string) $idInvoice) {
        $row = null;
    }
    $remoteGone = false;
    $rowStatus = is_array($row) ? strtolower((string) ($row['status'] ?? '')) : '';
    if (is_array($row) && ($rowStatus === 'orphan' || $rowStatus === 'deleted')) {
        $remoteGone = true;
        $row['remote_id'] = '';
    }
    if (is_array($row) && trim((string) ($row['remote_id'] ?? '')) !== '') {
        $lookup = self::jovadpanelUnlimitedLookup($panel, $row['remote_id']);
        if ($lookup['status'] === 'found') {
            return self::jovadpanelUnlimitedOutput($panel, $username, $lookup['config'], $row);
        }
        if ($lookup['status'] === 'error') {
            return ['status' => 'Unsuccessful', 'msg' => 'panel_read_failed_before_retry', 'code' => 'panel_read_failed'];
        }
        self::jovadpanelMappingClearRemote($panel, $username);
        $row['remote_id'] = '';
        $remoteGone = true;
    }
    if (!$remoteGone && is_array($row) && trim((string) ($row['remote_id'] ?? '')) === '' && trim((string) ($row['label'] ?? '')) !== '') {
        $found = self::jovadpanelUnlimitedFindByLabel($panel, (string) $row['label']);
        if ($found['status'] === 'error') {
            return ['status' => 'Unsuccessful', 'msg' => 'panel_read_failed_before_retry', 'code' => 'panel_read_failed'];
        }
        if ($found['status'] === 'found' && (string) ($found['config']['id'] ?? '') !== '') {
            $existing = $found['config'];
            self::jovadpanelMappingUpsert($panel, $username, $idInvoice, (string) ($row['label'] ?? ''), (string) $existing['id'], (string) ($existing['subUrl'] ?? ''), 'unlimited');
            return self::jovadpanelUnlimitedOutput($panel, $username, $existing, ['remote_id' => (string) $existing['id'], 'sub_url' => (string) ($existing['subUrl'] ?? '')]);
        }
        if (in_array($rowStatus, ['creating', 'uncertain'], true)) {
            return ['status' => 'Unsuccessful', 'msg' => 'panel_result_unknown', 'code' => 'uncertain'];
        }
    }
    $label = (!$remoteGone && is_array($row) && trim((string) ($row['label'] ?? '')) !== '')
        ? (string) $row['label']
        : (($idInvoice !== '' && !$remoteGone) ? self::jovadpanelLabelFor($panel, $username, $idInvoice) : 'ju-' . bin2hex(random_bytes(5)));
    if (!self::jovadpanelMappingPrepare($panel, $username, $idInvoice, $label, 'unlimited')) {
        return ['status' => 'Unsuccessful', 'msg' => 'jovad_mapping_unavailable'];
    }
    $payload = [
        'label' => $label,
        'devices' => $devices,
        'days' => $days,
    ];
    if (trim((string) $note) !== '') {
        $payload['note'] = substr((string) $note, 0, 500);
    }
    if (!self::jovadpanelMappingStatus($panel, $username, 'creating')) {
        return ['status' => 'Unsuccessful', 'msg' => 'jovad_mapping_unavailable'];
    }
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'POST', 'unlimited', $payload));
    if ($decoded['ok'] === true) {
        $cfg = $decoded['data'];
        $remoteId = (string) ($cfg['id'] ?? '');
        if ($remoteId === '') {
            self::jovadpanelMappingStatus($panel, $username, 'uncertain');
            return ['status' => 'Unsuccessful', 'msg' => 'jovad_invalid_create_response', 'code' => 'uncertain'];
        }
        self::jovadpanelMappingUpsert($panel, $username, $idInvoice, $label, $remoteId, (string) ($cfg['subUrl'] ?? ''), 'unlimited');
        return self::jovadpanelUnlimitedOutput($panel, $username, $cfg, ['remote_id' => $remoteId, 'sub_url' => (string) ($cfg['subUrl'] ?? '')]);
    }
    if ($decoded['code'] === 'label_duplicate' || (int) $decoded['http'] === 409) {
        $found = self::jovadpanelUnlimitedFindByLabel($panel, $label);
        if ($found['status'] === 'found') {
            $existing = $found['config'];
            $remoteId = (string) ($existing['id'] ?? '');
            if ($remoteId !== '') {
                self::jovadpanelMappingUpsert($panel, $username, $idInvoice, $label, $remoteId, (string) ($existing['subUrl'] ?? ''), 'unlimited');
                return self::jovadpanelUnlimitedOutput($panel, $username, $existing, ['remote_id' => $remoteId, 'sub_url' => (string) ($existing['subUrl'] ?? '')]);
            }
        }
        return ['status' => 'Unsuccessful', 'msg' => 'label_conflict_needs_review', 'code' => 'label_conflict'];
    }
    if ((int) $decoded['http'] === 0 || (int) $decoded['http'] >= 500 || in_array($decoded['code'], ['network_error', 'rate_limited', 'invalid_response'], true)) {
        self::jovadpanelMappingStatus($panel, $username, 'uncertain');
        $found = self::jovadpanelUnlimitedFindByLabel($panel, $label);
        if ($found['status'] === 'found' && (string) ($found['config']['id'] ?? '') !== '') {
            $existing = $found['config'];
            self::jovadpanelMappingUpsert($panel, $username, $idInvoice, $label, (string) $existing['id'], (string) ($existing['subUrl'] ?? ''), 'unlimited');
            return self::jovadpanelUnlimitedOutput($panel, $username, $existing, ['remote_id' => (string) $existing['id'], 'sub_url' => (string) ($existing['subUrl'] ?? '')]);
        }
        return ['status' => 'Unsuccessful', 'msg' => 'panel_result_unknown', 'code' => 'uncertain', 'label' => $label];
    }
    self::jovadpanelMappingStatus($panel, $username, 'rejected');
    return [
        'status' => 'Unsuccessful',
        'msg' => $decoded['message'] !== '' ? $decoded['message'] : $decoded['code'],
        'detail' => $decoded['code'],
    ];
}

    public static function jovadpanelCreateUser(array $panel, $codeProduct, $username, array $dataConfig, array $product, $note)
{
    $limitBytes = (int) ($dataConfig['data_limit'] ?? 0);
    $expireTs = (int) ($dataConfig['expire'] ?? 0);
    if ($limitBytes <= 0) {
        return self::jovadpanelCreateUnlimitedUser($panel, $username, $dataConfig, $note);
    }
    if (!self::jovadpanelEnsureSchema()) {
        return ['status' => 'Unsuccessful', 'msg' => 'jovad_mapping_unavailable'];
    }
    $idInvoice = self::jovadpanelInvoiceId($panel, $username, $dataConfig['id_invoice'] ?? ($dataConfig['invoice'] ?? null));
    $row = self::jovadpanelMapping($panel, $username);
    if (is_array($row) && $idInvoice !== '' && (string) ($row['id_invoice'] ?? '') !== (string) $idInvoice) {
        $row = null;
    }
    $remoteGone = false;
    $rowStatus = is_array($row) ? strtolower((string) ($row['status'] ?? '')) : '';
    if (is_array($row) && ($rowStatus === 'orphan' || $rowStatus === 'deleted')) {
        $remoteGone = true;
        $row['remote_id'] = '';
    }
    if (is_array($row) && trim((string) ($row['remote_id'] ?? '')) !== '') {
        $lookup = self::jovadpanelConfigLookup($panel, $row['remote_id']);
        if ($lookup['status'] === 'found') {
            return self::jovadpanelCreatedOutput($panel, $username, $lookup['config'], $row);
        }
        if ($lookup['status'] === 'error') {
            return ['status' => 'Unsuccessful', 'msg' => 'panel_read_failed_before_retry', 'code' => 'panel_read_failed'];
        }
        if ($lookup['status'] === 'missing') {
            self::jovadpanelMappingClearRemote($panel, $username);
            $row['remote_id'] = '';
            $remoteGone = true;
        }
    }
    if (!$remoteGone && is_array($row) && trim((string) ($row['remote_id'] ?? '')) === '' && trim((string) ($row['label'] ?? '')) !== '') {
        $found = self::jovadpanelFindByLabel($panel, (string) $row['label']);
        if ($found['status'] === 'error') {
            return ['status' => 'Unsuccessful', 'msg' => 'panel_read_failed_before_retry', 'code' => 'panel_read_failed'];
        }
        if ($found['status'] === 'found' && (string) ($found['config']['id'] ?? '') !== '') {
            $existing = $found['config'];
            self::jovadpanelMappingUpsert($panel, $username, $idInvoice, (string) ($row['label'] ?? ''), (string) $existing['id'], (string) ($existing['subUrl'] ?? ''), (string) ($existing['proto'] ?? ''));
            return self::jovadpanelCreatedOutput($panel, $username, $existing, ['remote_id' => (string) $existing['id'], 'sub_url' => (string) ($existing['subUrl'] ?? '')]);
        }
        if (in_array($rowStatus, ['creating', 'uncertain'], true)) {
            return ['status' => 'Unsuccessful', 'msg' => 'panel_result_unknown', 'code' => 'uncertain'];
        }
    }
    $label = (!$remoteGone && is_array($row) && trim((string) ($row['label'] ?? '')) !== '')
        ? (string) $row['label']
        : (($idInvoice !== '' && !$remoteGone) ? self::jovadpanelLabelFor($panel, $username, $idInvoice) : 'jv-' . bin2hex(random_bytes(5)));
    $channelState = self::jovadpanelResolveChannels($panel, $product, $idInvoice, $username);
    if (empty($channelState['status'])) {
        return ['status' => 'Unsuccessful', 'msg' => (string) ($channelState['msg'] ?? 'کانال‌های انتخاب‌شده معتبر نیستند.'), 'code' => (string) ($channelState['code'] ?? 'invalid_channels')];
    }
    if (empty($channelState['frozen']) && $idInvoice !== '') {
        $frozenState = self::jovadpanelFreezeChannelOrder($panel, $idInvoice, $username, $product);
        if (empty($frozenState['status'])) {
            return ['status' => 'Unsuccessful', 'msg' => (string) ($frozenState['msg'] ?? 'تثبیت کانال‌های سفارش ناموفق بود.'), 'code' => (string) ($frozenState['code'] ?? 'channel_freeze_failed')];
        }
        $channelState = $frozenState;
    }
    $channels = (array) ($channelState['channels'] ?? []);
    if (!self::jovadpanelMappingPrepare($panel, $username, $idInvoice, $label, implode(',', $channels))) {
        return ['status' => 'Unsuccessful', 'msg' => 'jovad_mapping_unavailable'];
    }
    $payload = [
        'gb' => self::jovadpanelBytesToGb($limitBytes),
        'label' => $label,
    ];
    if (trim((string) $note) !== '') {
        $payload['note'] = substr((string) $note, 0, 500);
    }
    if ($expireTs > 0) {
        $hours = max(1, (int) ceil(($expireTs - time()) / 3600));
        $payload['expiryDays'] = intdiv($hours, 24);
        $payload['expiryHours'] = $hours % 24;
    } else {
        $payload['noExpiry'] = true;
    }
    if (!empty($channels)) {
        $payload['channels'] = $channels;
    }
    if (!self::jovadpanelMappingStatus($panel, $username, 'creating')) {
        return ['status' => 'Unsuccessful', 'msg' => 'jovad_mapping_unavailable'];
    }
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'POST', 'configs', $payload));
    if ($decoded['ok'] === true) {
        $cfg = $decoded['data'];
        $remoteId = (string) ($cfg['id'] ?? '');
        if ($remoteId === '') {
            self::jovadpanelMappingStatus($panel, $username, 'uncertain');
            return ['status' => 'Unsuccessful', 'msg' => 'jovad_invalid_create_response', 'code' => 'uncertain'];
        }
        $subUrl = (string) ($cfg['subUrl'] ?? '');
        $proto = !empty($channels) ? implode(',', $channels) : (string) ($cfg['proto'] ?? '');
        self::jovadpanelMappingUpsert($panel, $username, $idInvoice, $label, $remoteId, $subUrl, $proto);
        return self::jovadpanelCreatedOutput($panel, $username, $cfg, ['remote_id' => $remoteId, 'sub_url' => $subUrl, 'proto' => $proto]);
    }
    if ($decoded['code'] === 'label_duplicate' || (int) $decoded['http'] === 409) {
        $found = self::jovadpanelFindByLabel($panel, $label);
        if ($found['status'] === 'found') {
            $existing = $found['config'];
            $remoteId = (string) ($existing['id'] ?? '');
            if ($remoteId !== '') {
                self::jovadpanelMappingUpsert($panel, $username, $idInvoice, $label, $remoteId, (string) ($existing['subUrl'] ?? ''), (string) ($existing['proto'] ?? ''));
                return self::jovadpanelCreatedOutput($panel, $username, $existing, ['remote_id' => $remoteId, 'sub_url' => (string) ($existing['subUrl'] ?? '')]);
            }
        }
        return ['status' => 'Unsuccessful', 'msg' => 'label_conflict_needs_review', 'code' => 'label_conflict'];
    }
    if ((int) $decoded['http'] === 0 || (int) $decoded['http'] >= 500 || in_array($decoded['code'], ['network_error', 'rate_limited', 'invalid_response'], true)) {
        self::jovadpanelMappingStatus($panel, $username, 'uncertain');
        $found = self::jovadpanelFindByLabel($panel, $label);
        if ($found['status'] === 'found' && (string) ($found['config']['id'] ?? '') !== '') {
            $existing = $found['config'];
            self::jovadpanelMappingUpsert($panel, $username, $idInvoice, $label, (string) $existing['id'], (string) ($existing['subUrl'] ?? ''), (string) ($existing['proto'] ?? ''));
            return self::jovadpanelCreatedOutput($panel, $username, $existing, ['remote_id' => (string) $existing['id'], 'sub_url' => (string) ($existing['subUrl'] ?? '')]);
        }
        if (method_exists(\Faoxima\Services\Renewal\RenewalLog::class, 'rx_renewal_log')) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('jovad_create', 'uncertain_result', [
                'panel' => (string) ($panel['name_panel'] ?? ''),
                'username' => (string) $username,
                'label' => $label,
                'mutation' => 'uncertain',
                'error' => (string) ($decoded['message'] ?? ''),
            ]);
        }
        return ['status' => 'Unsuccessful', 'msg' => 'panel_result_unknown', 'code' => 'uncertain', 'label' => $label];
    }
    self::jovadpanelMappingStatus($panel, $username, 'rejected');
    return [
        'status' => 'Unsuccessful',
        'msg' => self::jovadpanelCreateErrorMessage((string) $decoded['code'], (string) $decoded['message']),
        'detail' => $decoded['code'],
    ];
}

    public static function jovadpanelCreateErrorMessage($code, $message)
{
    $code = strtolower(trim((string) $code));
    if ($code === 'dns_unavailable') {
        return '❌ سرویس DNS در حال حاضر در سطح پلتفرم غیرفعال است و هیچ مبلغی کسر نشد.';
    }
    if ($code === 'dns_off_for_seller') {
        return '❌ سرویس DNS در تنظیمات پنل فروشنده غیرفعال است؛ ابتدا آن را در بخش Servers پنل JovadPanel فعال کنید. مبلغی کسر نشد.';
    }
    if ($code === 'proto_unavailable') {
        return '❌ فروش این پروتکل موقتاً در پنل غیرفعال است و مبلغی کسر نشد.';
    }
    if ($code === 'no_channels') {
        return '❌ هیچ کانال معتبری برای ساخت سرویس انتخاب نشده است؛ تنظیمات کانال‌های محصول یا پنل را بررسی کنید.';
    }
    $message = trim((string) $message);
    return $message !== '' ? $message : $code;
}

    public static function jovadpanelCreatedOutput(array $panel, $username, array $cfg, $row)
{
    global $domainhosts;
    $remoteId = (string) ($cfg['id'] ?? ($row['remote_id'] ?? ''));
    $subUrl = trim((string) ($cfg['subUrl'] ?? ''));
    if ($subUrl === '') {
        $subUrl = trim((string) ($row['sub_url'] ?? ''));
    }
    $rowProto = (string) ($row['proto'] ?? '');
    if ($rowProto === '') {
        $storedMapping = self::jovadpanelMapping($panel, $username);
        $rowProto = (string) ($storedMapping['proto'] ?? '');
    }
    $resolvedChannels = self::jovadpanelChannelsFromConfig($cfg, $rowProto);
    $channels = $resolvedChannels['channels'];
    $proto = !empty($channels) ? implode(',', $channels) : $rowProto;
    $configs = self::jovadpanelDeliveryConfigs($panel, $remoteId, $proto, $channels, $resolvedChannels['source'] !== 'remote_channels');
    if ((string) ($panel['subvip'] ?? '') === 'onsubvip') {
        $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "username", (string) $username, "select");
        if (is_array($invoice)) {
            $subUrl = "https://$domainhosts/sub/" . $invoice['id_invoice'];
        }
    }
    return [
        'status' => 'successful',
        'username' => (string) $username,
        'subscription_url' => $subUrl,
        'configs' => $configs,
        'remote_id' => $remoteId,
        'label' => (string) ($cfg['label'] ?? ($row['label'] ?? '')),
    ];
}

    public static function jovadpanelModifyUser(array $panel, $username, array $config)
{
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        return ['status' => false, 'msg' => self::jovadpanelResolveError($resolved)];
    }
    $remoteId = (string) ($resolved['row']['remote_id'] ?? '');
    $proto = (string) ($resolved['row']['proto'] ?? '');
    $current = strtolower($proto) === 'unlimited'
        ? self::jovadpanelNormalizeUnlimited($panel, $resolved['config'], (string) ($resolved['row']['sub_url'] ?? ''))
        : self::jovadpanelNormalizeConfig($panel, $resolved['config'], '', $proto, false);
    $accountStatus = strtolower((string) ($current['account_status'] ?? ''));
    if (array_key_exists('status', $config)) {
        $wanted = strtolower((string) $config['status']);
        if ($wanted === 'active') {
            if (($current['paused_by'] ?? '') === 'credit') {
                return ['status' => false, 'msg' => 'seller_credit_suspended'];
            }
            if ($accountStatus === 'paused') {
                $result = self::jovadpanelResumeRemote($panel, $remoteId, $proto);
            } elseif ($accountStatus === 'active') {
                $result = ['status' => true];
            } else {
                return ['status' => false, 'msg' => 'service is finished and cannot be resumed'];
            }
        } elseif ($wanted === 'disabled') {
            if ($accountStatus === 'active') {
                $result = self::jovadpanelPauseRemote($panel, $remoteId, $proto);
            } elseif ($accountStatus === 'paused') {
                $result = ['status' => true];
            } else {
                $result = ['status' => true];
            }
        } else {
            return ['status' => false, 'msg' => 'unsupported status for JovadPanel'];
        }
        return $result['status'] ? ['status' => true, 'data' => $result['data'] ?? null] : ['status' => false, 'msg' => $result['msg'] ?? 'status update failed'];
    }
    return ['status' => false, 'msg' => 'unsupported modification for JovadPanel'];
}

    public static function jovadpanelSetStatus(array $panel, $username, array $dataUser)
{
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        return ['status' => 'Unsuccessful', 'msg' => self::jovadpanelResolveError($resolved)];
    }
    $remoteId = (string) ($resolved['row']['remote_id'] ?? '');
    $proto = (string) ($resolved['row']['proto'] ?? '');
    $accountStatus = strtolower((string) ($dataUser['account_status'] ?? ''));
    $pausedBy = strtolower((string) ($dataUser['paused_by'] ?? ''));
    if ($accountStatus === 'active') {
        $result = self::jovadpanelPauseRemote($panel, $remoteId, $proto);
        return $result['status']
            ? ['status' => 'successful', 'msg' => null]
            : ['status' => 'Unsuccessful', 'msg' => $result['msg'] ?? 'pause failed'];
    }
    if ($accountStatus === 'paused') {
        if ($pausedBy === 'credit') {
            return ['status' => 'Unsuccessful', 'code' => 'credit_suspended', 'msg' => 'service is suspended by seller credit; it resumes automatically after a top-up'];
        }
        $result = self::jovadpanelResumeRemote($panel, $remoteId, $proto);
        return $result['status']
            ? ['status' => 'successful', 'msg' => null]
            : ['status' => 'Unsuccessful', 'msg' => $result['msg'] ?? 'resume failed'];
    }
    return ['status' => 'Unsuccessful', 'msg' => 'service is finished and cannot be toggled'];
}

    public static function jovadpanelRemoveUser(array $panel, $username)
{
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        if (($resolved['reason'] ?? '') === 'missing') {
            self::jovadpanelMappingStatus($panel, $username, 'deleted');
            return ['status' => 'successful', 'username' => (string) $username];
        }
        return ['status' => 'Unsuccessful', 'msg' => self::jovadpanelResolveError($resolved)];
    }
    $remoteId = (string) ($resolved['row']['remote_id'] ?? '');
    $result = self::jovadpanelDeleteRemote($panel, $remoteId, (string) ($resolved['row']['proto'] ?? ''));
    if ($result['status'] !== true) {
        return ['status' => 'Unsuccessful', 'msg' => $result['msg'] ?? 'delete failed'];
    }
    self::jovadpanelMappingStatus($panel, $username, 'deleted');
    return ['status' => 'successful', 'username' => (string) $username];
}

    public static function jovadpanelRotate(array $panel, $username)
{
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        return ['status' => 'Unsuccessful', 'msg' => self::jovadpanelResolveError($resolved)];
    }
    $row = is_array($resolved['row']) ? $resolved['row'] : array();
    $oldId = (string) ($row['remote_id'] ?? '');
    $proto = (string) ($row['proto'] ?? '');
    $result = self::jovadpanelRotateRemote($panel, $oldId, $proto);
    if ($result['status'] !== true) {
        return ['status' => 'Unsuccessful', 'msg' => $result['msg'] ?? 'rotate failed'];
    }
    $data = is_array($result['data']) ? $result['data'] : array();
    $newId = (string) ($data['id'] ?? '');
    $newSub = (string) ($data['subUrl'] ?? '');
    if ($newId === '' || $newId === $oldId || trim($newSub) === '') {
        return ['status' => 'Unsuccessful', 'code' => 'uncertain', 'msg' => 'نتیجه تغییر لینک کامل نیست؛ برای بررسی پشتیبانی ثبت شد. درخواست را دوباره تکرار نکنید.'];
    }
    if ($newId !== '') {
        $stored = self::jovadpanelMappingUpsert(
            $panel,
            $username,
            (string) ($row['id_invoice'] ?? ''),
            (string) ($row['label'] ?? ''),
            $newId,
            $newSub,
            $proto
        );
        $verifyRow = self::jovadpanelMapping($panel, $username);
        $verifyId = is_array($verifyRow) ? (string) ($verifyRow['remote_id'] ?? '') : '';
        $verifyLookup = $verifyId !== '' ? self::jovadpanelConfigLookup($panel, $verifyId, $proto) : ['status' => 'missing'];
        if (!$stored || $verifyId !== $newId || $verifyLookup['status'] !== 'found') {
            return ['status' => 'Unsuccessful', 'code' => 'uncertain', 'msg' => 'لینک در پنل تغییر کرد اما ذخیره یا بررسی نتیجه کامل نشد. درخواست تغییر لینک را تکرار نکنید؛ با پشتیبانی پیگیری کنید.'];
        }
    }
    return ['status' => 'successful', 'remote_id' => $newId, 'subscription_url' => $newSub];
}

    public static function jovadpanelResetUsage(array $panel, $username)
{
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        return ['status' => false, 'msg' => self::jovadpanelResolveError($resolved)];
    }
    if (strtolower((string) ($resolved['row']['proto'] ?? '')) === 'unlimited') {
        return ['status' => false, 'msg' => 'این سرویس نامحدود است و مصرف قابل بازنشانی ندارد.', 'code' => 'service_has_no_traffic_limit'];
    }
    $remoteId = (string) ($resolved['row']['remote_id'] ?? '');
    $quote = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'configs/' . rawurlencode($remoteId) . '/reset-traffic/quote'));
    if ($quote['ok'] !== true) {
        return ['status' => false, 'msg' => $quote['message'] !== '' ? $quote['message'] : $quote['code']];
    }
    if (array_key_exists('allowed', $quote['data']) && $quote['data']['allowed'] === false) {
        $reason = (string) ($quote['data']['message'] ?? $quote['data']['reason'] ?? 'reset not allowed');
        return ['status' => false, 'msg' => $reason];
    }
    $result = self::jovadpanelResetTrafficRemote($panel, $remoteId);
    if ($result['status'] !== true) {
        return ['status' => false, 'msg' => $result['msg'] ?? 'reset failed'];
    }
    return ['status' => true, 'data' => $result['data'] ?? null];
}

    public static function jovadpanelAddVolume(array $panel, $username, $newLimitBytes)
{
    $current = self::jovadpanelDataUser($panel, $username);
    if (!is_array($current) || ($current['status'] ?? '') === 'Unsuccessful') {
        return ['status' => false, 'msg' => is_array($current) ? ($current['msg'] ?? '') : 'panel read failed'];
    }
    if (!empty($current['unlimited'])) {
        return ['status' => false, 'msg' => 'این سرویس نامحدود است و حجم اضافه‌ای ندارد.', 'code' => 'service_unlimited'];
    }
    if (in_array(strtolower((string) ($current['status'] ?? '')), ['expired', 'limited'], true)) {
        return ['status' => false, 'msg' => '⚠️ این سرویس پایان یافته است؛ برای ادامه از گزینهٔ تمدید سرویس استفاده کنید.', 'code' => 'service_finished'];
    }
    $delta = (int) $newLimitBytes - (int) ($current['data_limit'] ?? 0);
    if ($delta === 0) {
        return ['status' => true, 'data' => null];
    }
    if ($delta < 0) {
        return ['status' => false, 'msg' => 'traffic_reduction_unsupported'];
    }
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        return ['status' => false, 'msg' => self::jovadpanelResolveError($resolved)];
    }
    $result = self::jovadpanelExtendRemote($panel, (string) ($resolved['row']['remote_id'] ?? ''), self::jovadpanelBytesToGb($delta), null);
    return $result['status'] === true ? ['status' => true, 'data' => $result['data'] ?? null] : $result;
}

    public static function jovadpanelAddTime(array $panel, $username, $targetExpireTs)
{
    $current = self::jovadpanelDataUser($panel, $username);
    if (!is_array($current) || ($current['status'] ?? '') === 'Unsuccessful') {
        return ['status' => false, 'msg' => is_array($current) ? ($current['msg'] ?? '') : 'panel read failed'];
    }
    if (!empty($current['unlimited'])) {
        return ['status' => false, 'msg' => 'مدت اشتراک نامحدود پس از خرید قابل افزایش نیست.', 'code' => 'service_unlimited'];
    }
    if (in_array(strtolower((string) ($current['status'] ?? '')), ['expired', 'limited'], true)) {
        return ['status' => false, 'msg' => '⚠️ این سرویس پایان یافته است؛ برای ادامه از گزینهٔ تمدید سرویس استفاده کنید.', 'code' => 'service_finished'];
    }
    $target = (int) $targetExpireTs;
    if ($target <= 0) {
        return ['status' => false, 'msg' => 'invalid expire target'];
    }
    $base = max(time(), (int) ($current['expire'] ?? 0));
    $days = (int) round(($target - $base) / 86400);
    if ($days === 0) {
        return ['status' => true, 'data' => null];
    }
    if ($days < 0) {
        return ['status' => false, 'msg' => 'time_reduction_unsupported'];
    }
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        return ['status' => false, 'msg' => self::jovadpanelResolveError($resolved)];
    }
    $result = self::jovadpanelExtendRemote($panel, (string) ($resolved['row']['remote_id'] ?? ''), null, $days);
    return $result['status'] === true ? ['status' => true, 'data' => $result['data'] ?? null] : $result;
}

    public static function jovadpanelRenewalSignature(array $plan)
{
    $values = [];
    foreach (['policy', 'data_limit', 'target_used_traffic', 'expire', 'before_data_limit', 'before_used_traffic', 'before_expire', 'now'] as $key) {
        $values[$key] = $plan[$key] ?? null;
    }
    return hash('sha256', json_encode($values));
}

    public static function jovadpanelSaveRenewal(array $panel, $username, array $journal)
{
    $pdo = self::jovadpanelPdo();
    if ($pdo === null) {
        return false;
    }
    try {
        $json = json_encode($journal, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = $pdo->prepare("UPDATE jovadpanel_configs SET renewal_state = ?, updated_at = ? WHERE code_panel = ? AND username = ?");
        if (!$stmt->execute([$json, time(), (string) $panel['code_panel'], (string) $username])) return false;
        if ($stmt->rowCount() > 0) return true;
        $stored = self::jovadpanelMapping($panel, $username);
        return is_array($stored) && ($stored['renewal_state'] ?? null) === $json;
    } catch (Throwable $e) {
        return false;
    }
}

    public static function jovadpanelVerificationPlan(array $snapshot, array $plan)
{
    $raw = is_array($snapshot['raw'] ?? null) ? $snapshot['raw'] : $snapshot;
    $journal = $raw['jovad_renewal_state'] ?? null;
    if (!is_array($journal) || ($journal['signature'] ?? '') !== self::jovadpanelRenewalSignature($plan)) {
        return $plan;
    }
    $time = $journal['steps']['time'] ?? null;
    if (!is_array($time)) {
        return $plan;
    }
    $expire = (int) ($snapshot['expire'] ?? ($raw['expire'] ?? 0));
    if (isset($time['effective_expire'])) {
        $plan['expire'] = (int) $time['effective_expire'];
    } elseif (isset($time['expire_min'], $time['expire_max']) && $expire >= (int) $time['expire_min'] && $expire <= (int) $time['expire_max']) {
        $plan['expire'] = $expire;
    }
    return $plan;
}

    public static function jovadpanelRenewalStep(array $panel, $username, array &$journal, $step, array $intent, callable $write, callable $confirm)
{
    $previous = $journal['steps'][$step] ?? null;
    if (is_array($previous) && ($previous['state'] ?? '') === 'done') {
        return ['status' => true];
    }
    if (is_array($previous)) {
        $fresh = self::jovadpanelResolve($panel, $username);
        if (!empty($fresh['ok']) && $confirm($fresh['config'], $previous)) {
            $journal['steps'][$step]['state'] = 'done';
            if ($step === 'time') {
                $journal['steps'][$step]['effective_expire'] = $fresh['config']['expiresAt'] === null ? 0 : self::jovadpanelIsoToTs($fresh['config']['expiresAt']);
            }
            if (!self::jovadpanelSaveRenewal($panel, $username, $journal)) {
                return ['status' => false, 'uncertain' => true, 'msg' => 'renewal_progress_not_persisted'];
            }
            return ['status' => true];
        }
        return ['status' => false, 'uncertain' => true, 'msg' => 'jovad_' . $step . '_result_pending_reconciliation'];
    }
    $journal['steps'][$step] = array_merge($intent, ['state' => 'sent', 'sent_at' => time()]);
    if (!self::jovadpanelSaveRenewal($panel, $username, $journal)) {
        return ['status' => false, 'uncertain' => true, 'msg' => 'renewal_intent_not_persisted'];
    }
    $result = $write();
    if (empty($result['status'])) {
        return ['status' => false, 'uncertain' => true, 'msg' => 'jovad_' . $step . '_result_unknown: ' . (string) ($result['msg'] ?? '')];
    }
    if ($step === 'time') {
        $fresh = self::jovadpanelResolve($panel, $username);
        if (empty($fresh['ok']) || !$confirm($fresh['config'], $journal['steps'][$step])) {
            return ['status' => false, 'uncertain' => true, 'msg' => 'jovad_expiry_not_verified'];
        }
        $journal['steps'][$step]['effective_expire'] = $fresh['config']['expiresAt'] === null ? 0 : self::jovadpanelIsoToTs($fresh['config']['expiresAt']);
    }
    $journal['steps'][$step]['state'] = 'done';
    if (!self::jovadpanelSaveRenewal($panel, $username, $journal)) {
        return ['status' => false, 'uncertain' => true, 'msg' => 'renewal_result_not_persisted'];
    }
    return ['status' => true];
}

    public static function jovadpanelApplyRenewalPlan(array $panel, array $dataUser, array $plan, $username, $opId = '')
{
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        return ['status' => false, 'uncertain' => true, 'msg' => self::jovadpanelResolveError($resolved)];
    }
    $current = self::jovadpanelNormalizeConfig($panel, $resolved['config'], '', (string) ($resolved['row']['proto'] ?? ''), false);
    if (($current['status'] ?? '') === 'Unsuccessful') {
        return ['status' => false, 'uncertain' => true, 'msg' => 'panel read failed'];
    }
    if (($current['paused_by'] ?? '') === 'credit') {
        return ['status' => false, 'uncertain' => true, 'msg' => 'seller_credit_suspended'];
    }
    $targetLimit = (int) ($plan['data_limit'] ?? 0);
    $targetExpire = (int) ($plan['expire'] ?? 0);
    $timeMode = (string) ($plan['time_mode'] ?? 'keep');
    if ($targetLimit <= 0) {
        return ['status' => false, 'msg' => 'panel_policy_unsupported'];
    }
    $curLimit = (int) $current['data_limit'];
    $curUsed = (int) $current['used_traffic'];
    $curExpire = (int) $current['expire'];
    $curStatus = strtolower((string) $current['status']);
    $accountStatus = strtolower((string) ($current['account_status'] ?? ''));
    $finished = in_array($curStatus, ['expired', 'limited'], true) && $accountStatus !== 'paused';
    $remoteId = (string) ($resolved['row']['remote_id'] ?? '');
    if ((string) $opId === '') {
        return ['status' => false, 'msg' => 'renewal_operation_required'];
    }
    $signature = self::jovadpanelRenewalSignature($plan);
    $journal = json_decode((string) ($resolved['row']['renewal_state'] ?? ''), true);
    if (!is_array($journal) || ($journal['op_id'] ?? '') !== (string) $opId) {
        if (is_array($journal) && !empty($journal['op_id'])) {
            $pdo = self::jovadpanelPdo();
            if ($pdo === null) return ['status' => false, 'uncertain' => true, 'msg' => 'previous_renewal_unavailable'];
            $previousOp = $pdo->prepare("SELECT state FROM renewal_operation WHERE op_id = ?");
            $previousOp->execute([(string) $journal['op_id']]);
            if (!in_array((string) $previousOp->fetchColumn(), ['verified', 'failed'], true)) {
                return ['status' => false, 'uncertain' => true, 'msg' => 'previous_renewal_requires_reconciliation'];
            }
        }
        $journal = ['op_id' => (string) $opId, 'signature' => $signature, 'steps' => []];
    } elseif (($journal['signature'] ?? '') !== $signature) {
        return ['status' => false, 'uncertain' => true, 'msg' => 'renewal_target_changed'];
    }
    if (!$finished && $curLimit > $targetLimit && empty($journal['steps'])) {
        return ['status' => false, 'msg' => 'traffic_reduction_unsupported'];
    }
    $needsReset = !empty($plan['reset_usage']);
    if ($finished || isset($journal['steps']['revive'])) {
        $result = self::jovadpanelRenewalStep($panel, $username, $journal, 'revive', ['limit' => $targetLimit],
            function () use ($panel, $remoteId, $targetLimit) {
                return self::jovadpanelReviveRemote($panel, $remoteId, self::jovadpanelBytesToGb($targetLimit));
            },
            function ($cfg, $intent) {
                return ($cfg['status'] ?? '') === 'active' && self::jovadpanelGbToBytes($cfg['limitGb'] ?? null) === (int) $intent['limit'];
            });
        if (empty($result['status'])) return $result;
    } elseif ($needsReset && !isset($journal['steps']['reset'])) {
        if ($curUsed > 0) {
            $offset = $resolved['config']['usageResetBytes'] ?? null;
            $result = self::jovadpanelRenewalStep($panel, $username, $journal, 'reset', ['offset' => $offset],
                function () use ($panel, $remoteId) {
                    $quote = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'configs/' . rawurlencode($remoteId) . '/reset-traffic/quote'));
                    if (empty($quote['ok']) || empty($quote['data']['allowed'])) {
                        return ['status' => false, 'msg' => 'traffic_reset_not_allowed'];
                    }
                    return self::jovadpanelResetTrafficRemote($panel, $remoteId);
                },
                function ($cfg, $intent) {
                    return (isset($intent['offset'], $cfg['usageResetBytes']) && (float) $cfg['usageResetBytes'] > (float) $intent['offset'])
                        || (isset($cfg['usedGb']) && (float) $cfg['usedGb'] === 0.0);
                });
            if (empty($result['status'])) return $result;
        } else {
            $journal['steps']['reset'] = ['state' => 'done'];
            if (!self::jovadpanelSaveRenewal($panel, $username, $journal)) {
                return ['status' => false, 'uncertain' => true, 'msg' => 'renewal_progress_not_persisted'];
            }
        }
    } elseif ($needsReset && ($journal['steps']['reset']['state'] ?? '') !== 'done') {
        $result = self::jovadpanelRenewalStep($panel, $username, $journal, 'reset', [],
            function () { return ['status' => false]; },
            function ($cfg, $intent) {
                return (isset($intent['offset'], $cfg['usageResetBytes']) && (float) $cfg['usageResetBytes'] > (float) $intent['offset'])
                    || (isset($cfg['usedGb']) && (float) $cfg['usedGb'] === 0.0);
            });
        if (empty($result['status'])) return $result;
    }
    if ($finished || $needsReset || isset($journal['steps']['revive'])) {
        $fresh = self::jovadpanelResolve($panel, $username);
        if (empty($fresh['ok'])) {
            return ['status' => false, 'uncertain' => true, 'msg' => 'panel_read_failed_after_reset'];
        }
        $current = self::jovadpanelNormalizeConfig($panel, $fresh['config'], '', (string) ($fresh['row']['proto'] ?? ''), false);
        if (($current['status'] ?? '') === 'Unsuccessful') {
            return ['status' => false, 'uncertain' => true, 'msg' => 'panel_read_failed_after_reset'];
        }
        $curLimit = (int) $current['data_limit'];
        $curExpire = (int) $current['expire'];
    }

    if ($curLimit < $targetLimit || isset($journal['steps']['volume'])) {
        $result = self::jovadpanelRenewalStep($panel, $username, $journal, 'volume', ['limit' => $targetLimit],
            function () use ($panel, $remoteId, $targetLimit, $curLimit) {
                return self::jovadpanelExtendRemote($panel, $remoteId, self::jovadpanelBytesToGb($targetLimit - $curLimit), null);
            },
            function ($cfg, $intent) {
                return self::jovadpanelGbToBytes($cfg['limitGb'] ?? null) === (int) $intent['limit'];
            });
        if (empty($result['status'])) return $result;
        $fresh = self::jovadpanelResolve($panel, $username);
        if (empty($fresh['ok'])) {
            return ['status' => false, 'uncertain' => true, 'msg' => 'panel_read_failed_after_extend'];
        }
        $current = self::jovadpanelNormalizeConfig($panel, $fresh['config'], '', (string) ($fresh['row']['proto'] ?? ''), false);
        if (($current['status'] ?? '') === 'Unsuccessful') {
            return ['status' => false, 'uncertain' => true, 'msg' => 'panel_read_failed_after_extend'];
        }
        $curExpire = (int) $current['expire'];
    } elseif ($curLimit > $targetLimit) {
        return ['status' => false, 'uncertain' => !empty($journal['steps']), 'msg' => 'traffic_reduction_unsupported'];
    }

    if ($timeMode !== 'keep' && ($targetExpire > 0 || $curExpire > 0 || isset($journal['steps']['time']))) {
        $now = time();
        $days = max(1, (int) round((float) ($plan['purchased_days'] ?? 0)));
        $append = $timeMode === 'append' && !isset($journal['steps']['revive']);
        $base = $append ? max($now, $curExpire) : $now;
        $minimum = $targetExpire <= 0 ? 0 : $base + $days * 86400;
        $maximum = $minimum === 0 ? 0 : $minimum + 120;
        $result = self::jovadpanelRenewalStep($panel, $username, $journal, 'time', ['expire_min' => $minimum, 'expire_max' => $maximum],
            function () use ($panel, $remoteId, $targetExpire, $days, $append) {
                if ($targetExpire <= 0) return self::jovadpanelSetExpiryRemote($panel, $remoteId, null, true);
                return $append ? self::jovadpanelExtendRemote($panel, $remoteId, null, $days) : self::jovadpanelSetExpiryRemote($panel, $remoteId, $days, false);
            },
            function ($cfg, $intent) {
                if (!array_key_exists('expiresAt', $cfg)) return false;
                $expiry = $cfg['expiresAt'] === null ? 0 : self::jovadpanelIsoToTs($cfg['expiresAt']);
                return $expiry !== null && $expiry >= $intent['expire_min'] && $expiry <= $intent['expire_max'];
            });
        if (empty($result['status'])) return $result;
    }
    return ['status' => true, 'data' => null];
}

    public static function jovadpanelResolveUrl($value, $baseUrl)
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\x00-\x20\x7f"<>\\\\]/', $value)) {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        $parts = parse_url($value);
        return is_array($parts) && !empty($parts['host']) ? $value : '';
    }
    if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value)) {
        return '';
    }
    $base = parse_url((string) $baseUrl);
    if (!is_array($base) || empty($base['host'])) {
        return '';
    }
    $scheme = !empty($base['scheme']) ? $base['scheme'] : 'https';
    $authority = $base['host'] . (isset($base['port']) ? ':' . (int) $base['port'] : '');
    if (strpos($value, '//') === 0) {
        return $scheme . ':' . $value;
    }
    $relative = parse_url($value);
    if (!is_array($relative)) {
        return '';
    }
    $relativePath = (string) ($relative['path'] ?? '');
    $path = $relativePath;
    if ($relativePath === '') {
        $path = (string) ($base['path'] ?? '/');
    } elseif ($relativePath[0] !== '/') {
        $basePath = (string) ($base['path'] ?? '/');
        $directory = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
        if ($directory === '') {
            $directory = '/';
        }
        $path = $directory . $relativePath;
    }
    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }
    $resolved = $scheme . '://' . $authority . '/' . implode('/', $segments);
    if (substr($path, -1) === '/' && substr($resolved, -1) !== '/') {
        $resolved .= '/';
    }
    if (array_key_exists('query', $relative)) {
        $resolved .= '?' . $relative['query'];
    } elseif ($relativePath === '' && array_key_exists('query', $base)) {
        $resolved .= '?' . $base['query'];
    }
    if (array_key_exists('fragment', $relative)) {
        $resolved .= '#' . $relative['fragment'];
    }
    return $resolved;
}

    public static function jovadpanelUrlSource($value)
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\x00-\x20\x7f"<>\\\\]/', $value) || strpos($value, '://') !== false && !preg_match('#^https?://#i', $value)) {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        $parts = parse_url($value);
        return is_array($parts) && !empty($parts['host']) ? 'absolute' : '';
    }
    if (strpos($value, '//') === 0 || $value[0] === '/') {
        return 'relative';
    }
    $relative = parse_url($value);
    if (is_array($relative) && !isset($relative['scheme']) && !isset($relative['host'])
        && ($value[0] === '?' || $value[0] === '#'
            || strpos((string) ($relative['path'] ?? ''), '/') !== false
            || preg_match('/\.[A-Za-z0-9]{1,8}$/', (string) ($relative['path'] ?? '')))) {
        return 'relative';
    }
    return '';
}

    public static function jovadpanelConfigContentKind($content)
{
    $content = is_string($content) ? $content : '';
    if ($content === '' || strpos($content, "\0") !== false) {
        return '';
    }
    if (preg_match('/^\h*\[Interface\]\h*\r?$/mi', $content) && preg_match('/^\h*\[Peer\]\h*\r?$/mi', $content)) {
        return 'wireguard';
    }
    if (preg_match('/^\h*(?:client|tls-client|remote)(?:\h|\r?$)/mi', $content)) {
        return 'openvpn';
    }
    return '';
}

    public static function jovadpanelNameFieldPriority()
{
    return ['name' => 0, 'label' => 1, 'title' => 2, 'remark' => 3, 'displayname' => 4, 'display_name' => 4, 'filename' => 5, 'file_name' => 5, 'server' => 6, 'node' => 7];
}

    public static function jovadpanelIdentifierFieldPriority()
{
    return ['serverid' => 0, 'server_id' => 0, 'nodeid' => 0, 'node_id' => 0, 'serverkey' => 0, 'server_key' => 0, 'nodekey' => 0, 'node_key' => 0, 'key' => 1];
}

    public static function jovadpanelScanScopeMetadata(array $source, array &$result, &$namePriority, &$idPriority, array $nameFields, array $idFields)
{
    foreach ($source as $key => $value) {
        if (!is_scalar($value)) {
            continue;
        }
        $text = trim((string) $value);
        if ($text === '') {
            continue;
        }
        $keyLower = strtolower((string) preg_replace('/[^A-Za-z0-9_]/', '', (string) $key));
        if (array_key_exists($keyLower, $idFields)) {
            $priority = $idFields[$keyLower];
            if ($priority < $idPriority) {
                $result['server_id'] = $text;
                $result['server_id_field'] = (string) $key;
                $idPriority = $priority;
            }
        }
        if (array_key_exists($keyLower, $nameFields)) {
            $priority = $nameFields[$keyLower];
            if ($priority < $namePriority) {
                $result['name'] = $text;
                $result['name_field'] = (string) $key;
                $namePriority = $priority;
            }
        }
    }
}

    public static function jovadpanelScopeMetadata(array $node)
{
    $result = ['name' => '', 'name_field' => '', 'server_id' => '', 'server_id_field' => ''];
    $nameFields = self::jovadpanelNameFieldPriority();
    $idFields = self::jovadpanelIdentifierFieldPriority();
    $namePriority = PHP_INT_MAX;
    $idPriority = PHP_INT_MAX;
    self::jovadpanelScanScopeMetadata($node, $result, $namePriority, $idPriority, $nameFields, $idFields);
    $nestedIdFields = $idFields;
    $nestedIdFields['id'] = 0;
    foreach (['server', 'node', 'serverinfo', 'nodeinfo'] as $key) {
        if (isset($node[$key]) && is_array($node[$key])) {
            self::jovadpanelScanScopeMetadata($node[$key], $result, $namePriority, $idPriority, $nameFields, $nestedIdFields);
        }
    }
    return $result;
}

    public static function jovadpanelCollectEntrySources(array $row, $kind, $baseUrl)
{
    $urlKeys = ['downloadurl' => 0, 'download_url' => 0, 'url' => 1, 'href' => 1, 'uri' => 1, 'file' => 2, 'link' => 3, 'src' => 3];
    $contentKeys = ['content', 'config', 'configuration', 'body', 'ovpn', 'wg'];
    $nameFields = self::jovadpanelNameFieldPriority();
    $idFields = self::jovadpanelIdentifierFieldPriority();
    $urls = [];
    $contents = [];
    $walk = function ($node, $path, $depth, $inherited, $group, $collection = false) use (&$walk, &$urls, &$contents, $urlKeys, $contentKeys, $nameFields, $idFields, $kind, $baseUrl) {
        if (!is_array($node) || $depth > 3) {
            return;
        }
        $meta = self::jovadpanelScopeMetadata($node);
        $scopeName = $meta['name'] !== '' ? $meta['name'] : (string) ($inherited['name'] ?? '');
        $scopeNameField = $meta['name'] !== '' ? $meta['name_field'] : (string) ($inherited['name_field'] ?? '');
        $scopeId = $meta['server_id'] !== '' ? $meta['server_id'] : (string) ($inherited['server_id'] ?? '');
        $scopeIdField = $meta['server_id'] !== '' ? $meta['server_id_field'] : (string) ($inherited['server_id_field'] ?? '');
        $scope = [
            'name' => $scopeName,
            'name_field' => $scopeNameField,
            'server_id' => $scopeId,
            'server_id_field' => $scopeIdField,
        ];
        foreach ($node as $key => $value) {
            $fieldPath = $path === '' ? (string) $key : $path . '.' . $key;
            $keyLower = strtolower((string) preg_replace('/[^A-Za-z0-9_]/', '', (string) $key));
            if (is_array($value)) {
                if ($depth < 3) {
                    $separateFile = $collection || is_int($key) || ctype_digit((string) $key);
                    $walk($value, $fieldPath, $depth + 1, $scope, $separateFile ? $fieldPath : $group, in_array($keyLower, ['files', 'configs', 'items'], true));
                }
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            if (array_key_exists($keyLower, $nameFields) || array_key_exists($keyLower, $idFields)) {
                continue;
            }
            if (array_key_exists($keyLower, $urlKeys)) {
                $source = self::jovadpanelUrlSource($text);
                if ($source === '') {
                    continue;
                }
                $resolved = self::jovadpanelResolveUrl($text, $baseUrl);
                if ($resolved === '') {
                    continue;
                }
                $urls[] = [
                    'value' => $resolved,
                    'source' => $source,
                    'path' => $fieldPath,
                    'priority' => $urlKeys[$keyLower],
                    'depth' => $depth,
                    'group' => $group,
                    'name' => $scope['name'],
                    'name_field' => $scope['name_field'],
                    'server_id' => $scope['server_id'],
                    'server_id_field' => $scope['server_id_field'],
                ];
                continue;
            }
            if (in_array($keyLower, $contentKeys, true)) {
                $contentKind = self::jovadpanelConfigContentKind($text);
                if ($contentKind !== $kind) {
                    continue;
                }
                $contents[] = [
                    'value' => (string) $value,
                    'path' => $fieldPath,
                    'group' => $group,
                    'name' => $scope['name'],
                    'name_field' => $scope['name_field'],
                    'server_id' => $scope['server_id'],
                    'server_id_field' => $scope['server_id_field'],
                ];
            }
        }
    };
    $walk($row, '', 0, ['name' => '', 'name_field' => '', 'server_id' => '', 'server_id_field' => ''], 'root');
    usort($urls, function ($a, $b) {
        if ($a['priority'] === $b['priority']) {
            return $a['depth'] <=> $b['depth'];
        }
        return $a['priority'] <=> $b['priority'];
    });
    return [
        'urls' => $urls,
        'contents' => $contents,
    ];
}

    public static function jovadpanelFetchSubJson($url, $baseUrl)
{
    $ch = curl_init($url);
    $timeoutInfo = method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_panel_timeout_info') ? \Faoxima\Services\Panels\PanelHttp::faoxima_panel_timeout_info() : ['timeout' => 20, 'connect_timeout' => 8];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeoutInfo['timeout']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeoutInfo['connect_timeout']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    $body = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $httpStatus < 200 || $httpStatus >= 300) {
        return ['status' => false, 'msg' => $error !== '' ? $error : 'HTTP ' . $httpStatus, 'data' => null, 'effective_url' => $effectiveUrl !== '' ? $effectiveUrl : (string) $baseUrl];
    }
    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        return ['status' => false, 'msg' => 'invalid files response', 'data' => null, 'effective_url' => $effectiveUrl !== '' ? $effectiveUrl : (string) $baseUrl];
    }
    if (is_array($decoded['data'] ?? null) && !isset($decoded['configs']) && !isset($decoded['wgConfigs']) && !isset($decoded['ovpnConfigs'])) {
        $decoded = $decoded['data'];
    }
    return ['status' => true, 'msg' => '', 'data' => $decoded, 'effective_url' => $effectiveUrl !== '' ? $effectiveUrl : (string) $baseUrl];
}

    public static function jovadpanelProtocolRows(array $data, $kind)
{
    $keys = $kind === 'openvpn' ? ['ovpnConfigs', 'openvpnConfigs'] : ['wgConfigs', 'wireguardConfigs'];
    $rows = [];
    foreach ($keys as $key) {
        if (!empty($data[$key]) && is_array($data[$key])) {
            $entries = $data[$key];
            $fields = array_map('strtolower', array_keys($entries));
            if (array_intersect($fields, ['downloadurl', 'download_url', 'url', 'href', 'uri', 'file', 'link', 'src', 'content', 'config', 'configuration', 'body', 'ovpn', 'wg', 'files', 'configs', 'items'])) {
                $entries = [$entries];
            }
            foreach ($entries as $entry) {
                $rows[] = $entry;
            }
        }
    }
    if (!empty($rows)) {
        return $rows;
    }
    foreach ((array) ($data['configs'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $type = strtolower((string) ($entry['type'] ?? ($entry['proto'] ?? ($entry['kind'] ?? ''))));
        if ($type !== $kind && $type !== ($kind === 'openvpn' ? 'ovpn' : 'wg')) {
            continue;
        }
        if (is_array($entry['configs'] ?? null)) {
            foreach ($entry['configs'] as $child) {
                $rows[] = $child;
            }
        } else {
            $rows[] = $entry;
        }
    }
    return $rows;
}

    public static function jovadpanelServerNameMap(array $panel)
{
    static $cache = [];
    $key = hash('sha256', (string) ($panel['code_panel'] ?? '') . '|' . (string) ($panel['name_panel'] ?? '') . '|' . (string) ($panel['url_panel'] ?? ''));
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $map = [];
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'servers'));
    if ($decoded['ok'] === true) {
        foreach ((array) ($decoded['data']['servers'] ?? []) as $server) {
            if (!is_array($server)) {
                continue;
            }
            $name = trim((string) ($server['customName'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($server['name'] ?? ($server['defaultName'] ?? '')));
            }
            if ($name === '') {
                continue;
            }
            $id = trim((string) ($server['id'] ?? ''));
            $serverKey = trim((string) ($server['key'] ?? ''));
            if ($id !== '') {
                $map['id:' . $id] = $name;
            }
            if ($serverKey !== '') {
                $map['key:' . $serverKey] = $name;
            }
        }
    }
    return $cache[$key] = $map;
}

    public static function jovadpanelServerDisplayName(array $panel, $identifier)
{
    $identifier = trim((string) $identifier);
    if ($identifier === '') {
        return '';
    }
    $map = self::jovadpanelServerNameMap($panel);
    return (string) ($map['id:' . $identifier] ?? ($map['key:' . $identifier] ?? ''));
}

    public static function jovadpanelProtocolFiles(array $panel, $remoteId, $kind)
{
    $kind = $kind === 'openvpn' ? 'openvpn' : 'wireguard';
    $cfg = self::jovadpanelFetchConfig($panel, $remoteId);
    $subUrl = is_array($cfg) ? trim((string) ($cfg['subUrl'] ?? '')) : '';
    if ($subUrl === '') {
        $mapping = self::jovadpanelMappingByRemote($panel, $remoteId);
        $subUrl = is_array($mapping) ? trim((string) ($mapping['sub_url'] ?? '')) : '';
    }
    if ($subUrl === '') {
        return ['status' => false, 'msg' => 'subscription url unavailable'];
    }
    $protocolFiles = [];
    $linksMeta = self::jovadpanelFetchLinks($panel, $remoteId);
    if ($linksMeta['status'] === true && is_array($linksMeta['protocol_files'] ?? null)) {
        $protocolFiles = $linksMeta['protocol_files'];
    }
    $jsonUrl = isset($protocolFiles['json']) && is_string($protocolFiles['json']) ? self::jovadpanelResolveUrl($protocolFiles['json'], $subUrl) : '';
    if ($jsonUrl === '') {
        $jsonUrl = $subUrl . (strpos($subUrl, '?') === false ? '?' : '&') . 'format=json';
    }
    $fetched = self::jovadpanelFetchSubJson($jsonUrl, $subUrl);
    if ($fetched['status'] !== true) {
        return ['status' => false, 'msg' => (string) ($fetched['msg'] ?? 'file list unavailable')];
    }
    $decoded = $fetched['data'];
    $baseUrl = (string) ($fetched['effective_url'] ?? $subUrl);
    $rows = self::jovadpanelProtocolRows($decoded, $kind);
    if (!is_array($rows) || empty($rows)) {
        return ['status' => false, 'msg' => 'no ' . $kind . ' configuration in response'];
    }
    $files = [];
    foreach ($rows as $index => $row) {
        $candidates = [];
        if (is_string($row)) {
            $text = trim($row);
            $urlSource = self::jovadpanelUrlSource($text);
            if ($urlSource !== '') {
                $resolved = self::jovadpanelResolveUrl($text, $baseUrl);
                if ($resolved !== '') {
                    $candidates[] = ['url' => $resolved, 'content' => '', 'source' => $urlSource];
                }
            } elseif (self::jovadpanelConfigContentKind($row) === $kind) {
                $candidates[] = ['url' => '', 'content' => $row, 'source' => 'inline'];
            }
        } elseif (is_array($row)) {
            $collected = self::jovadpanelCollectEntrySources($row, $kind, $baseUrl);
            foreach ($collected['urls'] as $candidate) {
                $candidates[] = [
                    'url' => (string) $candidate['value'],
                    'content' => '',
                    'source' => (string) $candidate['source'],
                    'group' => $candidate['group'],
                    'name' => (string) ($candidate['name'] ?? ''),
                    'name_field' => (string) ($candidate['name_field'] ?? ''),
                    'server_id' => (string) ($candidate['server_id'] ?? ''),
                ];
            }
            foreach ($collected['contents'] as $candidate) {
                $candidates[] = [
                    'url' => '',
                    'content' => (string) $candidate['value'],
                    'source' => 'inline',
                    'group' => $candidate['group'],
                    'name' => (string) ($candidate['name'] ?? ''),
                    'name_field' => (string) ($candidate['name_field'] ?? ''),
                    'server_id' => (string) ($candidate['server_id'] ?? ''),
                ];
            }
        }
        $uniqueCandidates = [];
        foreach ($candidates as $candidate) {
            $key = ($candidate['group'] ?? 'root') . ':' . ($candidate['url'] !== '' ? 'u:' . $candidate['url'] : 'c:' . sha1($candidate['content']));
            if (!isset($uniqueCandidates[$key])) {
                $uniqueCandidates[$key] = $candidate;
            }
        }
        $groups = [];
        foreach ($uniqueCandidates as $candidate) {
            $groups[$candidate['group'] ?? 'root'][] = $candidate;
        }
        foreach (array_values($groups) as $alternatives) {
            $candidate = $alternatives[0];
            $candidateName = '';
            $candidateNameField = '';
            foreach ($alternatives as $alternative) {
                $alternativeName = trim((string) ($alternative['name'] ?? ''));
                if ($alternativeName !== '') {
                    $candidateName = $alternativeName;
                    $candidateNameField = (string) ($alternative['name_field'] ?? '');
                    break;
                }
            }
            $serverRef = trim((string) ($candidate['server_id'] ?? ''));
            if ($serverRef === '' && in_array(strtolower($candidateNameField), ['server', 'node'], true)) {
                $serverRef = $candidateName;
            }
            if ($serverRef !== '') {
                $mappedName = self::jovadpanelServerDisplayName($panel, $serverRef);
                if ($mappedName !== '' && ($candidateName === '' || in_array(strtolower($candidateNameField), ['server', 'node'], true))) {
                    $candidateName = $mappedName;
                }
            }
            $files[] = [
                'url' => $candidate['url'],
                'content' => $candidate['content'],
                'name' => $candidateName,
                'server_id' => trim((string) ($candidate['server_id'] ?? '')),
                'index' => (int) $index + 1,
                'kind' => $kind,
                'source' => $candidate['source'],
                'alternatives' => $alternatives,
            ];
        }
    }
    if (empty($files)) {
        return ['status' => false, 'msg' => 'no downloadable ' . $kind . ' file'];
    }
    return ['status' => true, 'files' => $files];
}

    public static function jovadpanelFilenameFromDisposition($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/filename\*\s*=\s*([^;]+)/i', $value, $match)) {
        $raw = trim($match[1]);
        if (strlen($raw) >= 2 && $raw[0] === '"' && substr($raw, -1) === '"') {
            $raw = substr($raw, 1, -1);
        }
        $charset = 'UTF-8';
        $encoded = $raw;
        if (preg_match("/^([^']*)'([^']*)'(.*)$/s", $raw, $parts)) {
            $charset = $parts[1] !== '' ? $parts[1] : 'UTF-8';
            $encoded = $parts[3];
        }
        $decoded = rawurldecode($encoded);
        if (strtoupper($charset) !== 'UTF-8' && function_exists('iconv')) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $decoded);
            if ($converted !== false) {
                $decoded = $converted;
            }
        }
        if ($decoded !== '' && preg_match('//u', $decoded)) {
            return $decoded;
        }
    }
    if (preg_match('/filename\s*=\s*"((?:\\\\.|[^"\\\\])*)"/is', $value, $match)) {
        $decoded = stripcslashes($match[1]);
        return preg_match('//u', $decoded) ? $decoded : '';
    }
    if (preg_match('/filename\s*=\s*([^;]+)/i', $value, $match)) {
        $decoded = trim(trim($match[1]), '"');
        return preg_match('//u', $decoded) ? $decoded : '';
    }
    return '';
}

    public static function jovadpanelDownloadFile($url, $destination, $kind = '', &$dispositionName = null)
{
    $dispositionName = '';
    $handle = @fopen($destination, 'wb');
    if ($handle === false) {
        return false;
    }
    $ch = null;
    $success = false;
    $httpStatus = 0;
    try {
        $ch = curl_init($url);
        $timeoutInfo = method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_panel_timeout_info') ? \Faoxima\Services\Panels\PanelHttp::faoxima_panel_timeout_info() : ['timeout' => 20, 'connect_timeout' => 8];
        curl_setopt($ch, CURLOPT_FILE, $handle);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeoutInfo['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeoutInfo['connect_timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $dispositionHeader = '';
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($handle, $header) use (&$dispositionHeader) {
            if (preg_match('#^HTTP/\S+\s+\d{3}(?:\s|$)#i', $header)) {
                $dispositionHeader = '';
            } elseif (stripos($header, 'content-disposition:') === 0) {
                $dispositionHeader = trim(substr($header, strlen('content-disposition:')));
            }
            return strlen($header);
        });
        $success = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $dispositionName = self::jovadpanelFilenameFromDisposition($dispositionHeader);
    } catch (Throwable $e) {
    } finally {
        if ($ch !== null && $ch !== false) curl_close($ch);
        fclose($handle);
    }
    clearstatcache(true, $destination);
    $bytes = (int) @filesize($destination);
    if (!$success || $httpStatus < 200 || $httpStatus >= 300 || $bytes <= 0) {
        @unlink($destination);
        return false;
    }
    $prefix = @file_get_contents($destination, false, null, 0, 65536);
    if (is_string($prefix)) $prefix = preg_replace('/^\xEF\xBB\xBF/', '', $prefix);
    $validText = is_string($prefix) && strpos($prefix, "\0") === false
        && !preg_match('~^\s*(?:<!doctype\s+html|<html\b|<head\b|<body\b|\{)~i', $prefix);
    if ($kind === '') $kind = strtolower(pathinfo($destination, PATHINFO_EXTENSION)) === 'ovpn' ? 'openvpn' : 'wireguard';
    $validConfig = false;
    if ($validText && $kind === 'wireguard') {
        $validConfig = preg_match('/^\h*\[Interface\]\h*\r?$/mi', $prefix)
            && preg_match('/^\h*\[Peer\]\h*\r?$/mi', $prefix);
    } elseif ($validText && $kind === 'openvpn') {
        $validConfig = preg_match('/^\h*(?:client|tls-client|remote)(?:\h|\r?$)/mi', $prefix) === 1;
    }
    if (!$validConfig) {
        @unlink($destination);
        return false;
    }
    return true;
}

    public static function jovadpanelSafeFilename($name, $kind)
{
    $requiredExt = $kind === 'openvpn' ? '.ovpn' : '.conf';
    $wrongExt = $kind === 'openvpn' ? '.conf' : '.ovpn';
    $name = is_string($name) ? $name : '';
    $name = str_replace(["\r", "\n", "\t", "\0"], '', $name);
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    $parts = preg_split('#[\\\\/]+#', $name);
    $name = is_array($parts) && !empty($parts) ? (string) end($parts) : '';
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..' || preg_match('/^\.+$/', $name)) {
        return '';
    }
    $lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    if (substr($lower, -strlen($requiredExt)) === $requiredExt) {
        return $name;
    }
    if (substr($lower, -strlen($wrongExt)) === $wrongExt) {
        $name = substr($name, 0, -strlen($wrongExt));
    }
    $name = rtrim($name, '. ');
    if ($name === '') {
        return '';
    }
    return $name . $requiredExt;
}

    public static function jovadpanelDeliverProtocolFiles($chatId, array $panel, $remoteId, $kind, $caption, $replyMarkup = null, $options = null)
{
    $files = self::jovadpanelProtocolFiles($panel, $remoteId, $kind);
    if ($files['status'] !== true) {
        return ['ok' => false, 'sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0, 'msg' => (string) ($files['msg'] ?? 'file retrieval failed')];
    }
    $sent = 0;
    $failed = 0;
    $unknown = 0;
    $retry = 0;
    $skipped = 0;
    $deliveredHashes = [];
    $total = 0;
    $shouldSend = is_array($options) && isset($options['should_send']) && is_callable($options['should_send']) ? $options['should_send'] : null;
    $onResult = is_array($options) && isset($options['on_result']) && is_callable($options['on_result']) ? $options['on_result'] : null;
    $deliverFile = is_array($options) && is_callable($options['deliver_file'] ?? null) ? $options['deliver_file'] : null;
    $attemptedKeys = [];
    $rxJovadNameCounts = [];
    if (\Faoxima\Services\Delivery\FileNaming::rxFileNamingEnabled()) {
        foreach ($files['files'] as $rxJovadMetaFile) {
            $rxJovadDescriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingJovadDescriptor($rxJovadMetaFile, $kind);
            if (is_array($rxJovadDescriptor)) {
                $rxJovadMetaKey = $rxJovadDescriptor['key'];
                $rxJovadNameCounts[$rxJovadMetaKey] = isset($rxJovadNameCounts[$rxJovadMetaKey]) ? $rxJovadNameCounts[$rxJovadMetaKey] + 1 : 1;
            }
        }
    }
    foreach ($files['files'] as $file) {
        $rxJovadFileKey = hash('sha256', 'jovad|' . $kind . '|' . (string) ($file['server_id'] ?? '') . '|' . (string) ($file['name'] ?? '') . '|' . (string) ($file['source'] ?? '') . '|' . (string) ($file['url'] ?? '') . '|' . hash('sha256', (string) ($file['content'] ?? '')));
        if (!empty($attemptedKeys[$rxJovadFileKey]) || ($shouldSend !== null && !$shouldSend($rxJovadFileKey))) {
            $skipped++;
            continue;
        }
        $attemptedKeys[$rxJovadFileKey] = true;
        $rxJovadDescriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingJovadDescriptor($file, $kind);
        $rxJovadKey = is_array($rxJovadDescriptor) ? $rxJovadDescriptor['key'] : '';
        $rxJovadAmbiguous = ($rxJovadKey !== '' && (($rxJovadNameCounts[$rxJovadKey] ?? 0) > 1));
        $thisCaption = $total === 0 ? (string) $caption : '';
        if ($deliverFile !== null) {
            $resume = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($panel, 'jovad', $rxJovadKey, '', $thisCaption, $rxJovadAmbiguous);
            $resumedStatus = $deliverFile($rxJovadFileKey, null, '', $resume['caption']);
            if ($resumedStatus !== null) {
                $total++;
                ${$resumedStatus}++;
                continue;
            }
        }
        $tempExt = $kind === 'openvpn' ? '.ovpn' : '.conf';
        $tempPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'jovad-tmp-' . bin2hex(random_bytes(8)) . $tempExt;
        $attempted = false;
        try {
            $downloadOk = false;
            $attemptName = '';
            $dispositionName = '';
            foreach ((array) ($file['alternatives'] ?? [$file]) as $alternative) {
                $attemptName = trim((string) ($alternative['name'] ?? ''));
                $dispositionName = '';
                if (trim((string) ($alternative['content'] ?? '')) !== '') {
                    $content = (string) $alternative['content'];
                    $downloadOk = self::jovadpanelConfigContentKind($content) === $kind
                        && @file_put_contents($tempPath, $content) !== false;
                } else {
                    $downloadOk = self::jovadpanelDownloadFile((string) ($alternative['url'] ?? ''), $tempPath, $kind, $dispositionName);
                }
                if ($downloadOk) {
                    break;
                }
            }
            if (!$downloadOk) {
                $total++;
                $attempted = true;
                $status = 'failed';
                if ($deliverFile !== null) {
                    $resolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($panel, 'jovad', $rxJovadKey, '', $thisCaption, $rxJovadAmbiguous);
                    $status = $deliverFile($rxJovadFileKey, '', '', $resolved['caption']);
                }
                $failed += (int) ($status === 'failed');
                $unknown += (int) ($status === 'unknown');
                $retry += (int) ($status === 'retry');
                if ($onResult !== null) {
                    $onResult($rxJovadFileKey, $status, 'configuration download failed');
                }
                continue;
            }
            $panelName = trim((string) ($file['name'] ?? ''));
            if ($panelName === '' && $attemptName !== '') {
                $panelName = $attemptName;
            }
            $publicName = $panelName !== '' ? self::jovadpanelSafeFilename($panelName, $kind) : '';
            if ($publicName === '' && $dispositionName !== '') {
                $publicName = self::jovadpanelSafeFilename($dispositionName, $kind);
            }
            if ($publicName === '') {
                $publicName = $kind === 'openvpn' ? 'openvpn.ovpn' : 'wireguard.conf';
            }
            $contentHash = @hash_file('sha256', $tempPath);
            if (is_string($contentHash) && isset($deliveredHashes[$contentHash])) {
                continue;
            }
            $total++;
            $attempted = true;
            $rxJovadResolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($panel, 'jovad', $rxJovadKey, $publicName, $thisCaption, $rxJovadAmbiguous);
            $publicName = $rxJovadResolved['filename'];
            $thisCaption = $rxJovadResolved['caption'];
            if (is_string($contentHash)) {
                $deliveredHashes[$contentHash] = true;
            }
            if ($deliverFile !== null) {
                $sendStatus = $deliverFile($rxJovadFileKey, $tempPath, $publicName, $thisCaption);
            } else {
                rxSendMediaWithCaption('sendDocument', [
                    'chat_id' => $chatId,
                    'document' => new CURLFile($tempPath, 'application/octet-stream', $publicName),
                    'caption' => $thisCaption,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $replyMarkup,
                ], null, $delivery, true);
                $sendStatus = ['completed' => 'sent', 'unknown' => 'unknown'][$delivery['delivery_status']] ?? 'failed';
            }
            if ($sendStatus === 'sent') {
                $sent++;
            } elseif ($sendStatus === 'failed') {
                $failed++;
            } elseif ($sendStatus === 'retry') {
                $retry++;
            } else {
                $unknown++;
            }
            if ($onResult !== null) {
                $onResult($rxJovadFileKey, $sendStatus, 'document delivery ' . $sendStatus);
            }
        } catch (\Faoxima\Services\Delivery\AutoFileRateLimit $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($attempted) {
                $unknown++;
                if ($onResult !== null) {
                    $onResult($rxJovadFileKey, 'unknown', 'document delivery outcome unknown');
                }
            } else {
                $total++;
                $failed++;
            }
        } finally {
            @unlink($tempPath);
        }
    }
    if ($sent !== $total) {
        return ['ok' => false, 'sent' => $sent, 'failed' => $failed, 'unknown' => $unknown, 'retry' => $retry, 'skipped' => $skipped, 'total' => $total, 'msg' => "ارسال فایل‌ها کامل نشد ({$sent} از {$total}). برای پیگیری با پشتیبانی تماس بگیرید."];
    }
    return ['ok' => true, 'sent' => $sent, 'failed' => $failed, 'unknown' => $unknown, 'skipped' => $skipped, 'total' => $total];
}

    public static function jovadpanelDnsServerState(array $panel)
{
    static $cache = [];
    $key = hash('sha256', (string) ($panel['code_panel'] ?? '') . '|' . (string) ($panel['name_panel'] ?? ''));
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $decoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'servers'));
    if ($decoded['ok'] !== true) {
        return $cache[$key] = ['available' => null, 'reason' => 'servers_read_failed'];
    }
    $row = null;
    foreach ((array) ($decoded['data']['servers'] ?? []) as $server) {
        if (!is_array($server)) {
            continue;
        }
        $type = strtolower((string) ($server['type'] ?? ''));
        if ($type === 'dns' || (string) ($server['id'] ?? '') === 'dns') {
            $row = $server;
            break;
        }
    }
    if ($row === null) {
        return $cache[$key] = ['available' => false, 'reason' => 'dns_row_missing'];
    }
    $enabled = !array_key_exists('enabled', $row) || $row['enabled'] !== false;
    return $cache[$key] = ['available' => $enabled, 'reason' => $enabled ? '' : 'dns_disabled_in_servers'];
}

    public static function jovadpanelDnsExtractValue($value, $path, $depth, array &$lines, array &$seen)
{
    if ($depth > 3) {
        return;
    }
    if (is_string($value)) {
        $text = trim($value);
        if ($text === '' || strpos($text, "\0") !== false) {
            return;
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            self::jovadpanelDnsExtractValue($decoded, $path, $depth + 1, $lines, $seen);
            return;
        }
        $key = substr((string) $path, (int) strrpos('.' . (string) $path, '.'));
        $field = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', ltrim($key, '.')));
        $allowed = ['dns', 'dnsaddress', 'dnsaddresses', 'dnsserver', 'dnsservers', 'dnsurl', 'dnsurls', 'dnsprofile', 'dnsconfig', 'dnsconfigs', 'dnsconfiguration', 'dnssettings', 'doh', 'dohurl', 'dot', 'doturl', 'address', 'addresses', 'server', 'servers', 'resolver', 'resolvers', 'endpoint', 'endpoints', 'url', 'urls', 'uri', 'hostname', 'host', 'ip', 'ipv4', 'ipv6', 'primary', 'secondary'];
        if (!in_array($field, $allowed, true)) {
            return;
        }
        $endpoints = preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($endpoints)) {
            return;
        }
        foreach ($endpoints as $endpoint) {
            if (filter_var($endpoint, FILTER_VALIDATE_IP)) {
                continue;
            }
            $parts = parse_url($endpoint);
            $host = is_array($parts) ? trim((string) ($parts['host'] ?? ''), '[]') : '';
            if (is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https', 'tls', 'quic'], true)
                && !isset($parts['user']) && !isset($parts['pass'])
                && (filter_var($host, FILTER_VALIDATE_IP) || (strlen($host) <= 253 && preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}\.?$/D', $host)))) {
                continue;
            }
            if (strlen($endpoint) <= 253 && preg_match('/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}\.?$/D', $endpoint)) {
                continue;
            }
            return;
        }
        $line = $text;
        if ($key !== '.' && $key !== '') {
            $line = ltrim($key, '.') . ': ' . $text;
        }
        $hash = sha1($line);
        if (!isset($seen[$hash])) {
            $seen[$hash] = true;
            $lines[] = $line;
        }
        return;
    }
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $key => $item) {
        if (!is_string($item) && !is_array($item)) {
            continue;
        }
        $field = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $key));
        if (in_array($field, ['error', 'errors', 'status', 'message', 'description', 'label', 'name', 'type', 'proto', 'kind', 'enabled', 'available', 'devices', 'count', 'id'], true)) {
            continue;
        }
        $childPath = is_int($key) || ctype_digit((string) $key) ? $path : ($path === '' ? (string) $key : $path . '.' . $key);
        if (is_array($item)) {
            self::jovadpanelDnsExtractValue($item, $childPath, $depth + 1, $lines, $seen);
            continue;
        }
        self::jovadpanelDnsExtractValue((string) $item, $childPath, $depth + 1, $lines, $seen);
    }
}

    public static function jovadpanelDnsExtract(array $data)
{
    $lines = [];
    $seen = [];
    foreach ($data as $key => $value) {
        $field = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $key));
        if (!in_array($field, ['dns', 'dnsaddress', 'dnsaddresses', 'dnsserver', 'dnsservers', 'dnsurl', 'dnsurls', 'dnsprofile', 'dnsconfig', 'dnsconfigs', 'dnsconfiguration', 'dnssettings', 'doh', 'dohurl', 'dot', 'doturl'], true)) {
            continue;
        }
        self::jovadpanelDnsExtractValue($value, (string) $key, 0, $lines, $seen);
    }
    if (is_array($data['configs'] ?? null)) {
        foreach ($data['configs'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryKind = strtolower((string) ($entry['type'] ?? ($entry['proto'] ?? ($entry['kind'] ?? ''))));
            if ($entryKind === 'dns') {
                self::jovadpanelDnsExtractValue($entry, 'configs', 0, $lines, $seen);
            }
        }
    }
    $text = trim(implode("\n", $lines));
    return $text;
}

    public static function jovadpanelDeliverDns($chatId, array $panel, $remoteId, $caption, $replyMarkup = null)
{
    $cfg = self::jovadpanelFetchConfig($panel, $remoteId);
    $subUrl = is_array($cfg) ? trim((string) ($cfg['subUrl'] ?? '')) : '';
    if ($subUrl === '') {
        $mapping = self::jovadpanelMappingByRemote($panel, $remoteId);
        $subUrl = is_array($mapping) ? trim((string) ($mapping['sub_url'] ?? '')) : '';
    }
    $serverState = self::jovadpanelDnsServerState($panel);
    $lines = [];
    if (is_array($cfg)) {
        $serviceDns = self::jovadpanelDnsExtract($cfg);
        if ($serviceDns !== '') {
            $lines[] = $serviceDns;
        }
    }
    if ($subUrl !== '') {
        $fetched = self::jovadpanelFetchSubJson($subUrl . (strpos($subUrl, '?') === false ? '?' : '&') . 'format=json', $subUrl);
        if ($fetched['status'] === true && is_array($fetched['data'])) {
            $subText = self::jovadpanelDnsExtract($fetched['data']);
            if ($subText !== '') {
                $lines[] = $subText;
            }
        }
    }
    $info = trim(implode("\n", array_filter(array_map('trim', $lines), 'strlen')));
    if ($info !== '') {
        $info = function_exists('mb_substr') ? mb_substr($info, 0, 3500, 'UTF-8') : substr($info, 0, 3500);
    }
    if ($info === '' || $serverState['available'] === false) {
        if ($serverState['available'] === false) {
            return ['ok' => false, 'msg' => $serverState['reason'] === 'dns_row_missing'
                ? '❌ سرویس DNS در حال حاضر در سطح پلتفرم در دسترس نیست.'
                : '❌ سرویس DNS برای این فروشنده در پنل غیرفعال است؛ برای فعال‌سازی از بخش Servers پنل JovadPanel اقدام کنید.'];
        }
        return ['ok' => false, 'msg' => 'ℹ️ پنل اطلاعات DNS را در پاسخ خود ارائه نکرد؛ لطفاً با پشتیبانی پنل تماس بگیرید.'];
    }
    $text = trim((string) $caption) . "\n\n" . htmlspecialchars($info, ENT_QUOTES, 'UTF-8');
    $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($replyMarkup !== null) {
        $payload['reply_markup'] = $replyMarkup;
    }
    $result = telegram('sendmessage', $payload);
    $ok = is_array($result) && !empty($result['ok']);
    return $ok ? ['ok' => true] : ['ok' => false, 'msg' => 'ارسال اطلاعات DNS ناموفق بود؛ دوباره تلاش کنید.'];
}

    public static function jovadpanelDeliverMarkerFiles($chatId, $panel, $marker, $replyMarkup = null)
{
    if (!is_array($panel) || (string) ($panel['type'] ?? '') !== 'jovadpanel') {
        return ['ok' => false, 'msg' => 'panel mismatch'];
    }
    if ((string) ($panel['config'] ?? '') !== 'onconfig') {
        return ['ok' => false, 'msg' => 'configuration delivery is disabled for this panel'];
    }
    $marker = trim((string) $marker);
    if (!preg_match('~^jovad(wg|ovpn|dns)://([^#]*)~i', $marker, $matches)) {
        return ['ok' => false, 'msg' => 'invalid marker'];
    }
    $markerKind = strtolower($matches[1]);
    $kind = $markerKind === 'ovpn' ? 'openvpn' : ($markerKind === 'dns' ? 'dns' : 'wireguard');
    $remoteId = rawurldecode((string) $matches[2]);
    if ($remoteId === '') {
        return ['ok' => false, 'msg' => 'missing remote id'];
    }
    if ($kind === 'dns') {
        return self::jovadpanelDeliverDns($chatId, $panel, $remoteId, "🧭 تنظیمات DNS شما", $replyMarkup);
    }
    return self::jovadpanelDeliverProtocolFiles($chatId, $panel, $remoteId, $kind, '', $replyMarkup);
}

    public static function jovadpanelAutoDeliverFiles($chatId, array $panel, $username, $options = null)
{
    $result = ['ok' => true, 'sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0];
    if ((string) ($panel['auto_file'] ?? 'offautofile') !== 'onautofile') {
        return $result;
    }
    $resolved = self::jovadpanelResolve($panel, $username);
    if (empty($resolved['ok'])) {
        $result['ok'] = false;
        return $result;
    }
    $cfg = is_array($resolved['config'] ?? null) ? $resolved['config'] : [];
    $protoFallback = (string) ($resolved['row']['proto'] ?? '');
    $channelInfo = self::jovadpanelChannelsFromConfig($cfg, $protoFallback);
    $channels = [];
    foreach ((array) ($channelInfo['channels'] ?? []) as $channel) {
        $channel = strtolower(trim((string) $channel));
        if (in_array($channel, ['wireguard', 'openvpn'], true)) {
            $channels[] = $channel;
        }
    }
    if (empty($channels)) {
        foreach ((array) preg_split('/[,\s]+/', $protoFallback) as $channel) {
            $channel = strtolower(trim((string) $channel));
            if (in_array($channel, ['wireguard', 'openvpn'], true)) {
                $channels[] = $channel;
            }
        }
    }
    $remoteId = (string) ($cfg['id'] ?? ($resolved['row']['remote_id'] ?? ''));
    if ($remoteId === '' || empty($channels)) {
        return $result;
    }
    foreach (array_values(array_unique($channels)) as $kind) {
        $delivery = self::jovadpanelDeliverProtocolFiles($chatId, $panel, $remoteId, $kind, '', null, $options);
        $result['sent'] += (int) ($delivery['sent'] ?? 0);
        $result['failed'] += (int) ($delivery['failed'] ?? 0);
        $result['unknown'] += (int) ($delivery['unknown'] ?? 0);
        $result['retry'] = (int) ($result['retry'] ?? 0) + (int) ($delivery['retry'] ?? 0);
        $result['skipped'] += (int) ($delivery['skipped'] ?? 0);
        $result['total'] += (int) ($delivery['total'] ?? 0);
        if (empty($delivery['ok'])) {
            $result['ok'] = false;
        }
    }
    return $result;
}

    public static function jovadMiniAppPanelBlocked($panel)
{
    return is_array($panel) && (string) ($panel['type'] ?? '') === 'jovadpanel';
}

    public static function jovadMiniAppReject($panel)
{
    if (!self::jovadMiniAppPanelBlocked($panel)) {
        return false;
    }
    if (class_exists(\Faoxima\Api\Runtime\Response::class)) {
        \Faoxima\Api\Runtime\Response::fail(403, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_jovad_miniapp_blocked', '❌ خرید این پنل از طریق مینی‌اپ امکان‌پذیر نیست. لطفاً از ربات تلگرام استفاده کنید.'));
    }
    return true;
}

    public static function jovadpanelCapabilityReport(array $panel, array &$report)
{
    $panel = is_array($panel) ? $panel : array();
    $apiKey = trim((string) ($panel['api_key'] ?? ''));
    $baseUrl = self::jovadpanelBaseUrl($panel['url_panel'] ?? '');
    if ($apiKey === '' || $baseUrl === '') {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'failed', $apiKey === '' ? 'کلید API برای این پنل تنظیم نشده است.' : 'آدرس پنل تنظیم نشده است.');
        \Faoxima\Services\Panels\PanelCapability::rx_cap_mark_untested_after_connection_failure($report);
        return;
    }
    $account = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'me'));
    if ($account['ok'] !== true) {
        $message = $account['message'] !== '' ? $account['message'] : $account['code'];
        if ((int) $account['http'] > 0) {
            $message .= ' (HTTP ' . (int) $account['http'] . ')';
        }
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'failed', \Faoxima\Services\Panels\PanelCapability::rx_cap_clean_text($message, 160));
        \Faoxima\Services\Panels\PanelCapability::rx_cap_mark_untested_after_connection_failure($report);
        return;
    }
    \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'verified', 'GET /me موفق بود.');
    $data = is_array($account['data']) ? $account['data'] : array();
    \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'نام فروشگاه', (string) ($data['shopName'] ?? ($data['displayName'] ?? '')));
    if (isset($data['creditGb'])) {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'اعتبار فروشنده (GB)', (string) $data['creditGb']);
    }
    if (isset($data['activeConfigs'])) {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'کانفیگ‌های فعال', (string) $data['activeConfigs']);
    }
    if (isset($data['limits']['maxConfigs'])) {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'سقف تعداد کانفیگ', (string) $data['limits']['maxConfigs']);
    }
    if (isset($data['limits']['rateLimitPerMinute'])) {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'محدودیت درخواست در دقیقه', (string) $data['limits']['rateLimitPerMinute']);
    }
    $walletInfo = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'wallet'));
    if ($walletInfo['ok'] === true && is_array($walletInfo['data']['balances'] ?? null)) {
        $walletBalances = $walletInfo['data']['balances'];
        if (isset($walletBalances['sellerCreditGb']) && is_numeric($walletBalances['sellerCreditGb'])) {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'اعتبار حجم فروشنده (GB)', (string) $walletBalances['sellerCreditGb']);
        }
        if (isset($walletBalances['unlimitedUnits']) && is_numeric($walletBalances['unlimitedUnits'])) {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'موجودی اشتراک نامحدود', (string) $walletBalances['unlimitedUnits']);
        }
        if (isset($walletBalances['vpoints']) && is_numeric($walletBalances['vpoints'])) {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'موجودی VPoints', (string) $walletBalances['vpoints']);
        }
        if (isset($walletBalances['shopRevenueToman']) && is_numeric($walletBalances['shopRevenueToman'])) {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'درآمد فروشگاه (تومان)', (string) $walletBalances['shopRevenueToman']);
        }
        if (is_array($walletInfo['data']['billing'] ?? null) && !empty($walletInfo['data']['billing']['mode'])) {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'حالت پرداخت', (string) $walletInfo['data']['billing']['mode']);
        }
    }
    $list = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'configs?limit=1&status=active'));
    if ($list['ok'] === true) {
        $total = isset($list['data']['total']) ? (int) $list['data']['total'] : null;
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'list_users', 'verified', 'GET /configs موفق بود' . ($total !== null ? '؛ تعداد قابل مشاهده: ' . $total : '') . '.');
    } else {
        $listStatus = (int) $list['http'] === 403 ? 'denied' : 'failed';
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'list_users', $listStatus, 'GET /configs: ' . \Faoxima\Services\Panels\PanelCapability::rx_cap_clean_text($list['message'] !== '' ? $list['message'] : $list['code'], 120));
    }
    \Faoxima\Services\Panels\PanelCapability::rx_cap_probe_sample_user_via_manager($panel, $report);
    $writeNote = 'مطابق مستندات API این پنل موجود است؛ هیچ عملیات نوشتنی در این بررسی اجرا نشد.';
    foreach (['create', 'update', 'delete', 'reset', 'revoke'] as $key) {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, $key, 'allowed', $writeNote);
    }
    $servers = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'servers'));
    if ($servers['ok'] === true) {
        $count = is_array($servers['data']['servers'] ?? null) ? count($servers['data']['servers']) : 0;
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'resources', 'verified', 'GET /servers موفق بود؛ ' . $count . ' سرور قابل مشاهده.');
    } else {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'resources', 'failed', 'GET /servers: ' . \Faoxima\Services\Panels\PanelCapability::rx_cap_clean_text($servers['message'] !== '' ? $servers['message'] : $servers['code'], 120));
    }
    \Faoxima\Services\Panels\PanelCapability::rx_cap_note($report, 'این پنل امکان گزارش اتصال کاربر و محدودیت IP را ارائه نمی‌دهد و این قابلیت‌ها برای آن فعال نشده‌اند.');
}

    public static function jovadpanelFmtNum($value, $decimals = 2)
{
    if (!is_numeric($value)) {
        return '';
    }
    $number = (float) $value;
    if (floor($number) === $number) {
        return number_format($number, 0, '.', ',');
    }
    return rtrim(rtrim(number_format($number, $decimals, '.', ','), '0'), '.');
}

    public static function jovadpanelAccountSummary(array $panel)
{
    $me = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'me'));
    if ($me['ok'] !== true) {
        $message = $me['message'] !== '' ? $me['message'] : $me['code'];
        if ((int) $me['http'] > 0) {
            $message .= ' (HTTP ' . (int) $me['http'] . ')';
        }
        return ['status' => false, 'msg' => $message, 'text' => ''];
    }
    $account = is_array($me['data']) ? $me['data'] : [];
    $walletDecoded = self::jovadpanelDecode(self::jovadpanelHttp($panel, 'GET', 'wallet'));
    $wallet = ($walletDecoded['ok'] === true && is_array($walletDecoded['data'])) ? $walletDecoded['data'] : null;
    $unlimited = self::jovadpanelUnlimitedStatus($panel);
    $balances = is_array($wallet['balances'] ?? null) ? $wallet['balances'] : [];
    $limits = is_array($wallet['limits'] ?? null) ? $wallet['limits'] : (is_array($account['limits'] ?? null) ? $account['limits'] : []);
    $unavailable = 'در دسترس نیست';

    $creditGb = $balances['sellerCreditGb'] ?? ($wallet['creditGb'] ?? ($account['creditGb'] ?? null));
    $units = $balances['unlimitedUnits'] ?? ($unlimited['status'] === true ? $unlimited['units'] : null);
    $vpoints = $balances['vpoints'] ?? null;
    $revenue = $balances['shopRevenueToman'] ?? null;
    $billingMode = is_array($wallet['billing'] ?? null) ? trim((string) ($wallet['billing']['mode'] ?? '')) : '';
    $billingDisplay = $billingMode === 'live' ? 'مصرفی (live)' : ($billingMode === 'prepaid' ? 'پیش‌پرداخت (prepaid)' : $billingMode);

    $lines = [];
    $lines[] = '🖥 وضعیت اتصال پنل JovadPanel: ✅ پنل متصل است';
    $shopName = trim((string) ($account['shopName'] ?? ($account['displayName'] ?? '')));
    if ($shopName !== '') {
        $lines[] = '🏪 نام فروشگاه: ' . $shopName;
    }
    if (isset($account['sellerId']) && is_scalar($account['sellerId'])) {
        $lines[] = '👤 شناسه فروشنده: ' . (string) $account['sellerId'];
    }

    $lines[] = '';
    $lines[] = '‏──────────────';
    $lines[] = '💳 «موجودی فروشنده»';
    $lines[] = '‏──────────────';
    if ($wallet === null) {
        $lines[] = '⚠️ دریافت اطلاعات کیف پول از پنل ناموفق بود.';
    }
    $lines[] = '📦 اعتبار حجم (GB): ' . (($creditGb !== null && is_numeric($creditGb)) ? self::jovadpanelFmtNum($creditGb) : $unavailable);
    $lines[] = '♾ موجودی اشتراک نامحدود: ' . (($units !== null && is_numeric($units)) ? self::jovadpanelFmtNum($units) . ' واحد' : $unavailable);
    $lines[] = '💎 موجودی VPoints: ' . (($vpoints !== null && is_numeric($vpoints)) ? self::jovadpanelFmtNum($vpoints) : $unavailable);
    $lines[] = '💰 درآمد فروشگاه: ' . (($revenue !== null && is_numeric($revenue)) ? self::jovadpanelFmtNum($revenue) . ' تومان' : $unavailable);
    if ($billingDisplay !== '') {
        $lines[] = '💳 حالت پرداخت: ' . $billingDisplay;
    }

    $lines[] = '';
    $lines[] = '‏──────────────';
    $lines[] = '📊 «محدودیت‌های حساب»';
    $lines[] = '‏──────────────';
    if (isset($account['activeConfigs']) && is_numeric($account['activeConfigs'])) {
        $maxConfigs = isset($limits['maxConfigs']) && is_numeric($limits['maxConfigs']) ? ' از ' . self::jovadpanelFmtNum($limits['maxConfigs']) : '';
        $lines[] = '👥 کانفیگ‌های فعال: ' . self::jovadpanelFmtNum($account['activeConfigs']) . $maxConfigs;
    } elseif (isset($limits['maxConfigs']) && is_numeric($limits['maxConfigs'])) {
        $lines[] = '👥 سقف تعداد کانفیگ: ' . self::jovadpanelFmtNum($limits['maxConfigs']);
    }
    if (isset($limits['minMbPerConfig']) && is_numeric($limits['minMbPerConfig'])) {
        $lines[] = '📥 حداقل حجم هر کانفیگ: ' . self::jovadpanelFmtNum($limits['minMbPerConfig']) . ' مگابایت';
    }
    if (isset($limits['maxMbPerConfig']) && is_numeric($limits['maxMbPerConfig'])) {
        $lines[] = '📤 حداکثر حجم هر کانفیگ: ' . self::jovadpanelFmtNum(round(((float) $limits['maxMbPerConfig']) / 1024, 2)) . ' گیگابایت';
    }
    if (isset($limits['minTopupGb']) && is_numeric($limits['minTopupGb'])) {
        $lines[] = '🔁 حداقل شارژ حجم: ' . self::jovadpanelFmtNum($limits['minTopupGb']) . ' GB';
    }
    if (isset($limits['rateLimitPerMinute']) && is_numeric($limits['rateLimitPerMinute'])) {
        $lines[] = '⏱ محدودیت درخواست: ' . self::jovadpanelFmtNum($limits['rateLimitPerMinute']) . ' در دقیقه';
    }

    $lines[] = '';
    $lines[] = '‏──────────────';
    $lines[] = '♾ «اشتراک نامحدود»';
    $lines[] = '‏──────────────';
    if ($unlimited['status'] !== true) {
        $lines[] = '⚠️ دریافت اطلاعات اشتراک نامحدود از پنل ناموفق بود.';
    } else {
        $lines[] = '⚡ وضعیت فروش: ' . (!empty($unlimited['enabled']) ? 'فعال ✅' : 'غیرفعال ❌');
        if ($unlimited['default_devices'] > 0) {
            $lines[] = '👥 تعداد دستگاه پیش‌فرض پنل: ' . $unlimited['default_devices'];
        }
        if ($unlimited['days'] > 0) {
            $lines[] = '📅 مدت پیش‌فرض: ' . $unlimited['days'] . ' روز';
        }
        if (!empty($unlimited['options'])) {
            $tiers = [];
            foreach ($unlimited['options'] as $option) {
                $tiers[] = (int) $option['devices'];
            }
            $lines[] = '🎚 پلن‌های دستگاه: ' . implode('، ', $tiers);
        }
    }
    $selectedDevices = self::jovadpanelUnlimitedDevices($panel);
    $lines[] = '👥 تعداد دستگاه انتخابی شما: ' . ($selectedDevices > 0 ? (string) $selectedDevices : 'تنظیم نشده');

    return ['status' => true, 'msg' => '', 'text' => implode("\n", $lines), 'account' => $account, 'wallet' => $wallet];
}
}
