<?php

namespace Faoxima\Services\Panels\Drivers;

use PDO;
use Faoxima\Core\Http\CurlRequest;
use Throwable;

final class PasarGuard
{
    public static function pasarguardFormatUptime($seconds)
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

    public static function findPasarGuardPanelByName($location)
{
    $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "select");
    if (!is_array($panel) || empty($panel)) {
        return null;
    }
    return $panel;
}

    public static function pasarguardApiKey(array $panel)
{
    return $panel['api_key'] ?? null;
}

    public static function pasarguardAuthMode(array $panel)
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

    public static function pasarguardTokenCacheKey($baseUrl, $username)
{
    return 'pasarguard_token:' . md5($baseUrl . '|' . $username);
}

    public static function pasarguardTokenTtl($token, $ttlSeconds = 3000)
{
    $ttlSeconds = (int) $ttlSeconds;
    $exp = method_exists(\Faoxima\Services\Panels\PanelSetup::class, 'rx_panel_token_jwt_exp') ? \Faoxima\Services\Panels\PanelSetup::rx_panel_token_jwt_exp($token) : null;
    if ($exp !== null) {
        $ttlSeconds = min($ttlSeconds, $exp - time() - 60);
    }
    return $ttlSeconds;
}

    public static function pasarguardCachedToken($baseUrl, $username)
{
    $key = self::pasarguardTokenCacheKey($baseUrl, $username);
    if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_get')) {
        $token = \Faoxima\Core\Cache\RedisCache::rx_redis_get($key);
        if ($token !== null && $token !== false && $token !== '') {
            return (string) $token;
        }
    }
    $entry = $GLOBALS['__faoxima_panel_token_cache']['pasarguard'][$key] ?? null;
    if (is_array($entry) && isset($entry['token'], $entry['expires_at']) && $entry['expires_at'] > time()) {
        return (string) $entry['token'];
    }
    unset($GLOBALS['__faoxima_panel_token_cache']['pasarguard'][$key]);
    return null;
}

    public static function pasarguardStoreToken($baseUrl, $username, $token, $ttlSeconds = 3000)
{
    $token = (string) $token;
    if ($token === '') {
        return;
    }
    $ttlSeconds = self::pasarguardTokenTtl($token, $ttlSeconds);
    if ($ttlSeconds <= 0) {
        return;
    }
    $key = self::pasarguardTokenCacheKey($baseUrl, $username);
    if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_set')) {
        \Faoxima\Core\Cache\RedisCache::rx_redis_set($key, $token, $ttlSeconds);
    }
    $GLOBALS['__faoxima_panel_token_cache']['pasarguard'][$key] = [
        'token' => $token,
        'expires_at' => time() + $ttlSeconds,
    ];
}

    public static function pasarguardClearToken($baseUrl, $username, $onlyToken = null)
{
    $key = self::pasarguardTokenCacheKey($baseUrl, $username);
    if ($onlyToken === null) {
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_del')) {
            \Faoxima\Core\Cache\RedisCache::rx_redis_del($key);
        }
        unset($GLOBALS['__faoxima_panel_token_cache']['pasarguard'][$key]);
        return;
    }
    if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_release_lock')) {
        \Faoxima\Core\Cache\RedisCache::rx_redis_release_lock($key, (string) $onlyToken);
    }
    $entry = $GLOBALS['__faoxima_panel_token_cache']['pasarguard'][$key] ?? null;
    if (is_array($entry) && ($entry['token'] ?? null) === (string) $onlyToken) {
        unset($GLOBALS['__faoxima_panel_token_cache']['pasarguard'][$key]);
    }
}

    public static function pasarguardPanelPdo()
{
    $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
    return $pdo instanceof PDO ? $pdo : null;
}

    public static function pasarguardReadPanelSession(array $panel)
{
    $name = (string) ($panel['name_panel'] ?? '');
    $pdo = $name !== '' ? self::pasarguardPanelPdo() : null;
    if ($pdo === null) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT datelogin FROM marzban_panel WHERE name_panel = ? LIMIT 1");
        $stmt->execute([$name]);
        $raw = $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('pasarguardReadPanelSession: ' . $e->getMessage());
        return null;
    }
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    return ['raw' => $raw, 'data' => $decoded];
}

    public static function pasarguardStoredPanelToken(array $panel)
{
    $session = self::pasarguardReadPanelSession($panel);
    if ($session === null) {
        return null;
    }
    $data = $session['data'];
    $baseUrl = rtrim((string) ($panel['url_panel'] ?? ''), '/');
    $username = (string) ($panel['username_panel'] ?? '');
    $token = (string) ($data['pasarguard_token'] ?? '');
    if ($token === '' || ($data['pasarguard_key'] ?? null) !== self::pasarguardTokenCacheKey($baseUrl, $username)) {
        return null;
    }
    $remaining = 3000 - (time() - (int) ($data['pasarguard_time'] ?? 0));
    $remaining = min($remaining, self::pasarguardTokenTtl($token, 3000));
    if ($remaining <= 0) {
        return null;
    }
    return ['token' => $token, 'ttl' => $remaining];
}

    public static function pasarguardSavePanelToken(array $panel, $token)
{
    $name = (string) ($panel['name_panel'] ?? '');
    $pdo = $name !== '' ? self::pasarguardPanelPdo() : null;
    if ($pdo === null || (string) $token === '') {
        return;
    }
    $baseUrl = rtrim((string) ($panel['url_panel'] ?? ''), '/');
    $username = (string) ($panel['username_panel'] ?? '');
    $payload = json_encode([
        'pasarguard_key' => self::pasarguardTokenCacheKey($baseUrl, $username),
        'pasarguard_token' => (string) $token,
        'pasarguard_time' => time(),
    ]);
    try {
        $stmt = $pdo->prepare("UPDATE marzban_panel SET datelogin = ? WHERE name_panel = ?");
        $stmt->execute([$payload, $name]);
    } catch (Throwable $e) {
        error_log('pasarguardSavePanelToken: ' . $e->getMessage());
    }
}

    public static function pasarguardForgetPanelToken(array $panel, $token)
{
    $session = self::pasarguardReadPanelSession($panel);
    if ($session === null || ($session['data']['pasarguard_token'] ?? null) !== (string) $token) {
        return;
    }
    try {
        $stmt = self::pasarguardPanelPdo()->prepare("UPDATE marzban_panel SET datelogin = NULL WHERE name_panel = ? AND datelogin = ?");
        $stmt->execute([(string) $panel['name_panel'], $session['raw']]);
    } catch (Throwable $e) {
        error_log('pasarguardForgetPanelToken: ' . $e->getMessage());
    }
}

    public static function pasarguardLogin($baseUrl, $username, $password)
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

    public static function pasarguardReusableToken($panel, $rejectedToken = null)
{
    $baseUrl = rtrim((string) ($panel['url_panel'] ?? ''), '/');
    $username = (string) ($panel['username_panel'] ?? '');
    $cached = self::pasarguardCachedToken($baseUrl, $username);
    if ($cached !== null && $cached !== $rejectedToken) {
        return $cached;
    }
    $stored = self::pasarguardStoredPanelToken($panel);
    if ($stored !== null && $stored['token'] !== $rejectedToken) {
        self::pasarguardStoreToken($baseUrl, $username, $stored['token'], $stored['ttl']);
        return $stored['token'];
    }
    return null;
}

    public static function pasarguardGetToken($panel, $forceRefresh = false, $rejectedToken = null)
{
    $baseUrl = rtrim((string) ($panel['url_panel'] ?? ''), '/');
    $username = (string) ($panel['username_panel'] ?? '');
    $password = (string) ($panel['password_panel'] ?? '');
    $mayReuse = !$forceRefresh || $rejectedToken !== null;
    if ($mayReuse) {
        $reusable = self::pasarguardReusableToken($panel, $rejectedToken);
        if ($reusable !== null) {
            return ['status' => true, 'access_token' => $reusable];
        }
    }
    $canLock = method_exists(\Faoxima\Services\Panels\PanelSetup::class, 'rx_panel_token_lock_acquire');
    $lock = $canLock ? \Faoxima\Services\Panels\PanelSetup::rx_panel_token_lock_acquire('pasarguard', $baseUrl . '|' . $username) : null;
    try {
        if ($mayReuse || ($canLock && $lock === null)) {
            $reusable = self::pasarguardReusableToken($panel, $rejectedToken);
            if ($reusable !== null) {
                return ['status' => true, 'access_token' => $reusable];
            }
        }
        if ($canLock && $lock === null) {
            return ['status' => false, 'msg' => 'PasarGuard authentication is busy, please try again shortly.'];
        }
        $loginResult = self::pasarguardLogin($baseUrl, $username, $password);
        if (empty($loginResult['status'])) {
            return $loginResult;
        }
        self::pasarguardStoreToken($baseUrl, $username, $loginResult['access_token']);
        self::pasarguardSavePanelToken($panel, $loginResult['access_token']);
        return $loginResult;
    } finally {
        if ($lock !== null && method_exists(\Faoxima\Services\Panels\PanelSetup::class, 'rx_panel_token_lock_release')) {
            \Faoxima\Services\Panels\PanelSetup::rx_panel_token_lock_release($lock);
        }
    }
}

    public static function pasarguardRequest($panel, $method, $path, $data = null)
{
    $authMode = self::pasarguardAuthMode($panel);
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
        $tokenResult = self::pasarguardGetToken($panel);
        if (empty($tokenResult['status'])) {
            return ['status' => false, 'error' => $tokenResult['msg'] ?? 'PasarGuard login failed'];
        }
        $response = $doRequest($tokenResult['access_token'], null);
        if (($response['status'] ?? 0) == 401) {
            $rejectedToken = (string) $tokenResult['access_token'];
            self::pasarguardClearToken($baseUrl, $username, $rejectedToken);
            self::pasarguardForgetPanelToken($panel, $rejectedToken);
            $retryToken = self::pasarguardGetToken($panel, true, $rejectedToken);
            if (empty($retryToken['status'])) {
                return ['status' => false, 'error' => $retryToken['msg'] ?? 'PasarGuard login failed'];
            }
            $response = $doRequest($retryToken['access_token'], null);
        }
        return $response;
    }

    $apiKey = self::pasarguardApiKey($panel);
    return $doRequest(null, $apiKey);
}

    public static function pasarguardGetUser($username_account, $location)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return self::pasarguardRequest($panel, 'GET', '/api/user/' . $username_account);
}

    public static function pasarguardSubscriptionClientTypes()
{
    return ['links', 'xray', 'sing_box', 'clash_meta'];
}

    public static function pasarguardNormalizeClientType($client_type)
{
    $clientType = strtolower(trim((string) $client_type));
    return in_array($clientType, self::pasarguardSubscriptionClientTypes(), true) ? $clientType : 'links';
}

    public static function pasarguardIsJsonClientConfig($config)
{
    if (!is_array($config) || $config === [] || array_keys($config) === range(0, count($config) - 1)) {
        return false;
    }
    return isset($config['outbounds']) && is_array($config['outbounds']) && $config['outbounds'] !== [];
}

    public static function pasarguardParseClientDocuments($clientType, $body)
{
    $raw = (string) $body;
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return [];
    }
    if ($clientType === 'xray' || $clientType === 'sing_box') {
        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        $isList = array_keys($decoded) === range(0, count($decoded) - 1);
        if (!$isList) {
            if (!self::pasarguardIsJsonClientConfig($decoded)) {
                return [];
            }
            return [$raw];
        }
        $items = $decoded;
        if ($clientType === 'sing_box' && count($items) !== 1) {
            return [];
        }
        $documents = [];
        foreach ($items as $item) {
            if (!self::pasarguardIsJsonClientConfig($item)) {
                return [];
            }
            $encoded = json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                return [];
            }
            $documents[] = $encoded;
        }
        return $documents;
    }
    if ($clientType === 'clash_meta') {
        if ($trimmed[0] === '{' || $trimmed[0] === '[' || preg_match('#^[a-z][a-z0-9+.\-]*://#i', $trimmed)) {
            return [];
        }
        if (!preg_match('/^proxies\s*:/m', $trimmed)) {
            return [];
        }
        return [$raw];
    }
    return [];
}

    public static function pasarguardMaskSegment($segment)
{
    $segment = (string) $segment;
    if ($segment === '') {
        return '';
    }
    if (preg_match('/^[A-Za-z_\-]{1,16}$/', $segment)) {
        return $segment;
    }
    return '[redacted:' . strlen($segment) . ']';
}

    public static function pasarguardSanitizeUrl($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return '[unparsable-url]';
    }
    $out = strtolower((string) ($parts['scheme'] ?? 'http')) . '://' . strtolower((string) $parts['host']);
    if (!empty($parts['port'])) {
        $out .= ':' . (int) $parts['port'];
    }
    $out .= self::pasarguardSanitizePath((string) ($parts['path'] ?? ''));
    if (isset($parts['query']) && $parts['query'] !== '') {
        $out .= '?[redacted]';
    }
    return $out;
}

    public static function pasarguardSanitizePath($path)
{
    $path = (string) $path;
    if ($path === '') {
        return '';
    }
    return implode('/', array_map([\Faoxima\Services\Panels\Drivers\PasarGuard::class, 'pasarguardMaskSegment'], explode('/', $path)));
}

    public static function pasarguardTruncate($text, $limit)
{
    $text = (string) $text;
    $limit = max(16, (int) $limit);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '...' : $text;
    }
    return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
}

    public static function pasarguardSanitizeText($text, $limit = 300)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    if (!preg_match('//u', $text)) {
        $text = function_exists('mb_convert_encoding') ? (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8') : (string) preg_replace('/[\x80-\xFF]/', '?', $text);
    }
    $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    $text = (string) preg_replace_callback('#\b([a-z][a-z0-9+.\-]{1,24})://[^\s"\'<>`]+#i', function ($match) {
        $scheme = strtolower($match[1]);
        if ($scheme === 'http' || $scheme === 'https') {
            return self::pasarguardSanitizeUrl($match[0]);
        }
        return '[' . $scheme . '-link redacted]';
    }, $text);
    $text = (string) preg_replace('/\beyJ[A-Za-z0-9_\-]{4,}\.[A-Za-z0-9_\-]{4,}(?:\.[A-Za-z0-9_\-]*)?/', '[jwt redacted]', $text);
    $text = (string) preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '[uuid redacted]', $text);
    $text = (string) preg_replace('/((?:password|passwd|pass|secret|token|access_token|refresh_token|api[_-]?key|authorization|x-api-key|private[_-]?key|pre[_-]?shared[_-]?key|psk|sid|subscription_url|sub_url)["\']?\s*[:=]\s*["\']?)(?:Bearer\s+)?[^\s"\'&,;}]+/i', '$1[redacted]', $text);
    $text = (string) preg_replace_callback('/(?<![A-Za-z0-9])[A-Za-z0-9+\/_\-]{20,}={0,2}/', function ($match) {
        return (preg_match('/[0-9]/', $match[0]) && preg_match('/[A-Za-z]/', $match[0])) || strlen($match[0]) >= 40 ? '[blob redacted]' : $match[0];
    }, $text);
    $text = (string) preg_replace('/\s+/u', ' ', $text);
    return self::pasarguardTruncate(trim($text), $limit);
}

    public static function pasarguardFetchSubscription($url, $accept)
{
    $timeoutInfo = \Faoxima\Services\Panels\PanelHttp::faoxima_panel_timeout_info();
    $timeout = (int) $timeoutInfo['timeout'];
    $connectTimeout = (int) $timeoutInfo['connect_timeout'];
    $verifyTls = defined('BOT_CURL_VERIFY_TLS') && BOT_CURL_VERIFY_TLS === true;
    $body = false;
    $httpCode = 0;
    $errno = 0;
    $error = '';
    $contentType = '';
    $redirectUrl = '';
    $redirectCount = 0;
    $durationMs = 0;
    $attempts = 0;
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $attempts = $attempt;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: ' . $accept]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyTls);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyTls ? 2 : 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            @curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        if (function_exists('faoxima_apply_curl_proxy')) {
            faoxima_apply_curl_proxy($ch, 'panel');
        }
        $startedAt = microtime(true);
        $body = curl_exec($ch);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = (int) curl_errno($ch);
        $error = $errno ? (string) curl_error($ch) : '';
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $redirectCount = (int) curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $pendingRedirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        $redirectUrl = $pendingRedirect !== '' ? $pendingRedirect : ($redirectCount > 0 ? $effectiveUrl : '');
        if ($errno === CURLE_OPERATION_TIMEDOUT && $attempt < 2) {
            continue;
        }
        break;
    }
    $bodyText = is_string($body) ? $body : '';
    $emptyBody = trim($bodyText) === '';
    if ($error !== '' || $httpCode === 0 || $httpCode >= 400 || ($httpCode >= 200 && $httpCode < 300 && $emptyBody)) {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $message = sprintf(
            'PasarGuard subscription fetch to host %s failed (HTTP %d%s%s)',
            $host,
            $httpCode,
            $errno ? ', curl errno ' . $errno . ': ' . self::pasarguardSanitizeText($error, 160) : '',
            ($httpCode >= 200 && $httpCode < 300 && $emptyBody) ? ', empty body' : ''
        );
        if (method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_dedup_error_log')) {
            \Faoxima\Services\Panels\PanelHttp::faoxima_dedup_error_log('pgsub|' . $host . '|' . $httpCode . '|' . $errno . '|' . ($emptyBody ? 1 : 0), $message);
        } else {
            error_log($message);
        }
    }
    $result = [
        'status' => $httpCode,
        'body' => $bodyText,
        'content_type' => $contentType,
        'size' => strlen($bodyText),
        'duration_ms' => $durationMs,
        'attempts' => $attempts,
        'errno' => $errno,
        'redirect_count' => $redirectCount,
    ];
    if ($redirectUrl !== '') {
        $result['redirect_url'] = $redirectUrl;
    }
    if ($error !== '') {
        $result['error'] = 'cURL error ' . $errno . ': ' . self::pasarguardSanitizeText($error, 200);
    }
    return $result;
}

    public static function pasarguardParseLinksBody($body, $contentType = '')
{
    $result = [
        'links' => [],
        'entries' => 0,
        'rejected' => 0,
        'rejected_reasons' => [],
        'format' => 'plain',
        'invalid_reason' => null,
    ];
    $text = trim((string) $body);
    if ($text === '') {
        $result['format'] = 'empty';
        return $result;
    }
    if ($text[0] === '<' || stripos((string) $contentType, 'text/html') !== false) {
        $result['format'] = 'html';
        $result['invalid_reason'] = 'html_response';
        return $result;
    }
    if (($text[0] === '{' || $text[0] === '[') && json_decode($text, true) !== null) {
        $result['format'] = 'json';
        $result['invalid_reason'] = 'json_response';
        return $result;
    }
    if (strpos($text, '://') === false) {
        $compact = rtrim(strtr((string) preg_replace('/\s+/', '', $text), '-_', '+/'), '=');
        $compact .= str_repeat('=', (4 - strlen($compact) % 4) % 4);
        $decoded = base64_decode($compact, true);
        if (is_string($decoded) && strpos($decoded, '://') !== false) {
            $text = trim($decoded);
            $result['format'] = 'base64';
        }
    }
    foreach (preg_split('/\r\n|\n|\r/', $text) as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        $result['entries']++;
        $reason = null;
        if (!preg_match('//u', $line)) {
            $reason = 'invalid_utf8';
        } elseif (!preg_match('#^[a-z][a-z0-9+.\-]*://\S#i', $line)) {
            $reason = 'missing_scheme';
        }
        if ($reason !== null) {
            $result['rejected']++;
            $result['rejected_reasons'][$reason] = ($result['rejected_reasons'][$reason] ?? 0) + 1;
            continue;
        }
        $result['links'][] = $line;
    }
    if ($result['entries'] === 0) {
        $result['format'] = 'empty';
    } elseif (empty($result['links'])) {
        $result['invalid_reason'] = 'no_valid_entries';
    }
    return $result;
}

    public static function pasarguardGetSubscriptionLinks($username_account, $location, $client_type = 'links', $prefetchedUser = null)
{
    $requestedType = self::pasarguardNormalizeClientType($client_type);
    $fail = function ($stage, $reason, $message, array $extra = []) {
        return array_merge($extra, [
            'error' => $message,
            'stage' => $stage,
            'reason' => $reason,
            'outcome' => $extra['outcome'] ?? 'failed',
        ]);
    };
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return $fail('panel', 'panel_not_found', 'Panel configuration not found for the requested location.');
    }
    $userResponse = is_array($prefetchedUser) ? $prefetchedUser : self::pasarguardGetUser($username_account, $location);
    $userStatus = (int) ($userResponse['status'] ?? 0);
    $userBody = is_string($userResponse['body'] ?? null) ? $userResponse['body'] : '';
    $userData = $userBody !== '' ? json_decode($userBody, true) : null;
    $userJsonValid = is_array($userData);
    $hasSubscriptionUrl = $userJsonValid && is_string($userData['subscription_url'] ?? null) && trim($userData['subscription_url']) !== '';
    if (!empty($userResponse['error'])) {
        return $fail('user_lookup', 'request_error', (string) $userResponse['error'], ['http_status' => $userStatus]);
    }
    if ($userBody === '') {
        return $fail('user_lookup', 'empty_body', 'Empty response from panel when resolving subscription url.', ['http_status' => $userStatus]);
    }
    if ($userStatus === 0 || $userStatus >= 400) {
        return $fail('user_lookup', 'http_' . $userStatus, 'Unable to resolve subscription url.', ['http_status' => $userStatus]);
    }
    if (!$userJsonValid) {
        return $fail('user_lookup', 'invalid_json', 'Unable to resolve subscription url.', ['http_status' => $userStatus]);
    }
    if (!$hasSubscriptionUrl) {
        return $fail('user_lookup', 'missing_subscription_url', 'Unable to resolve subscription url.', ['http_status' => $userStatus]);
    }
    $subscriptionPath = trim((string) $userData['subscription_url']);
    $absolute = (bool) preg_match('#^https?://#i', $subscriptionPath);
    $subUrl = $absolute
        ? $subscriptionPath
        : rtrim($panel['url_panel'], '/') . '/' . ltrim($subscriptionPath, '/');
    $subUrl = rtrim($subUrl, '/');
    $fallbackReason = null;
    if ($requestedType !== 'links') {
        $response = self::pasarguardFetchSubscription(
            $subUrl . '/' . $requestedType,
            $requestedType === 'clash_meta' ? 'text/yaml, text/plain;q=0.9, */*;q=0.1' : 'application/json, text/plain;q=0.9, */*;q=0.1'
        );
        if (!empty($response['error']) || empty($response['status']) || $response['status'] >= 300) {
            $fallbackReason = !empty($response['errno']) ? 'curl_' . (int) $response['errno'] : 'http_' . (int) ($response['status'] ?? 0);
        } else {
            $documents = self::pasarguardParseClientDocuments($requestedType, $response['body'] ?? '');
            if (!empty($documents)) {
                return [
                    "status" => true,
                    "links" => $documents,
                    "client_type" => $requestedType,
                    "requested_client_type" => $requestedType,
                    "outcome" => 'ok',
                ];
            }
            $fallbackReason = trim((string) ($response['body'] ?? '')) === '' ? 'empty_body' : 'invalid_format';
        }
    }
    $response = self::pasarguardFetchSubscription($subUrl . '/links', 'text/plain');
    $fetchStatus = (int) ($response['status'] ?? 0);
    $fetchExtra = ['http_status' => $fetchStatus];
    if ($fallbackReason !== null) {
        $fetchExtra['fallback_reason'] = $fallbackReason;
    }
    if (!empty($response['error']) || $fetchStatus === 0) {
        $reason = !empty($response['errno']) ? 'curl_' . (int) $response['errno'] : 'http_0';
        return $fail('subscription_fetch', $reason, 'Unable to fetch subscription links.', $fetchExtra + ['curl_errno' => (int) ($response['errno'] ?? 0)]);
    }
    if ($fetchStatus >= 400) {
        return $fail('subscription_fetch', 'http_' . $fetchStatus, 'Unable to fetch subscription links.', $fetchExtra);
    }
    if ($fetchStatus >= 300) {
        return $fail('subscription_fetch', 'unfollowed_redirect', 'Unable to fetch subscription links.', $fetchExtra);
    }
    $parsed = self::pasarguardParseLinksBody($response['body'] ?? '', $response['content_type'] ?? '');
    if ($parsed['invalid_reason'] !== null) {
        return $fail('parse', $parsed['invalid_reason'], 'Subscription content did not contain usable configuration links.', $fetchExtra + ['outcome' => 'invalid']);
    }
    $outcome = empty($parsed['links']) ? 'empty' : 'ok';
    $result = [
        "status" => true,
        "links" => $parsed['links'],
        "client_type" => 'links',
        "requested_client_type" => $requestedType,
        "outcome" => $outcome,
    ];
    if ($outcome === 'empty') {
        $result['stage'] = 'parse';
        $result['reason'] = 'empty_body';
    }
    if ($fallbackReason !== null) {
        $result['fallback_reason'] = $fallbackReason;
    }
    return $result;
}

    public static function pasarguardGetUsersByStatus($location, $status)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $response = self::pasarguardRequest($panel, 'GET', '/api/users?status=' . rawurlencode($status) . '&limit=200');
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

    public static function pasarguardGetNodes($location)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return self::pasarguardRequest($panel, 'GET', '/api/nodes');
}

    public static function pasarguardGetSystemStats($location)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return self::pasarguardRequest($panel, 'GET', '/api/system');
}

    public static function pasarguardGetInbounds($location)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return self::pasarguardRequest($panel, 'GET', '/api/inbounds');
}

    public static function pasarguardGetUserHwids($username_account, $location)
{
    $userResponse = self::pasarguardGetUser($username_account, $location);
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
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return self::pasarguardRequest($panel, 'GET', '/api/user/' . $userData['id'] . '/hwids');
}

    public static function pasarguardAddUser($location, $data_limit, $username_ac, $timestamp, $note = '', $data_limit_reset = 'no_reset', $name_product = false, $hwid_limit = null)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    $inbounds = null;
    $product = null;
    if (!empty($panel['inbounds']) && $panel['inbounds'] != "null") {
        if ($name_product != false && $name_product != "usertest") {
            $product = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $name_product, "select");
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
            $product = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $name_product, "select");
        }
        if (is_array($product) && isset($product['hwid_limit'])) {
            $hwid_limit = (int) $product['hwid_limit'];
        }
    }
    $proxySettings = json_decode($panel['proxies'] ?? 'null');
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
    $data = array(
        "proxy_settings" => $proxySettings,
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
        $userResponse = self::pasarguardGetUser($username_ac, $location);
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
            return self::pasarguardRequest($panel, 'POST', '/api/user', $data);
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
    return self::pasarguardRequest($panel, 'POST', '/api/user', $data);
}

    public static function pasarguardModifyUser($location, $username, array $data)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return self::pasarguardRequest($panel, 'PUT', '/api/user/' . $username, $data);
}

    public static function pasarguardResetUserDataUsage($username_account, $location)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return self::pasarguardRequest($panel, 'POST', '/api/user/' . $username_account . '/reset', []);
}

    public static function pasarguardRevokeSub($username_account, $location)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return self::pasarguardRequest($panel, 'POST', '/api/user/' . $username_account . '/revoke_sub', []);
}

    public static function pasarguardTestConnection($baseUrl, $apiKey)
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
    $response = self::pasarguardRequest($panel, 'GET', '/api/admin');
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

    public static function pasarguardTestConnectionUserPass($baseUrl, $username, $password)
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
    $loginResult = self::pasarguardLogin($normalizedUrl, $username, $password);
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
    self::pasarguardStoreToken($normalizedUrl, $username, $loginResult['access_token']);
    $response = self::pasarguardRequest($panel, 'GET', '/api/admin');
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

    public static function pasarguardTestConnectionPanel(array $panel)
{
    $panel['url_panel'] = (string) ($panel['url_panel'] ?? '');
    $panel['pasarguard_auth_mode'] = 'password';
    if (trim((string) ($panel['username_panel'] ?? '')) === '' || trim((string) ($panel['password_panel'] ?? '')) === '') {
        return [
            'status' => false,
            'msg' => 'PasarGuard username or password is missing'
        ];
    }
    $response = self::pasarguardRequest($panel, 'GET', '/api/admin');
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

    public static function pasarguardRemoveUser($location, $username)
{
    $panel = self::findPasarGuardPanelByName($location);
    if ($panel === null) {
        return ["error" => "Panel configuration not found for the requested location."];
    }
    return self::pasarguardRequest($panel, 'DELETE', '/api/user/' . $username);
}

    public static function pasarguardCapabilityReport(array $panel, array &$report)
{
    $authMode = self::pasarguardAuthMode($panel);
    \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'روش احراز هویت', $authMode === 'password' ? 'نام کاربری و رمز عبور' : 'کلید API');
    if ($authMode === 'password') {
        $username = trim((string) ($panel['username_panel'] ?? ''));
        $password = trim((string) ($panel['password_panel'] ?? ''));
        if ($username === '' || $password === '' || strtolower($username) === 'null' || strtolower($password) === 'null') {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'failed', 'نام کاربری یا رمز عبور برای این پنل تنظیم نشده است.');
            \Faoxima\Services\Panels\PanelCapability::rx_cap_mark_untested_after_connection_failure($report);
            return;
        }
    } else {
        $apiKey = trim((string) ($panel['api_key'] ?? ''));
        if ($apiKey === '' || strtolower($apiKey) === 'null') {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'failed', 'کلید API برای این پنل تنظیم نشده است.');
            \Faoxima\Services\Panels\PanelCapability::rx_cap_mark_untested_after_connection_failure($report);
            return;
        }
    }
    $adminResponse = self::pasarguardRequest($panel, 'GET', '/api/admin');
    $adminStatus = (int) ($adminResponse['status'] ?? 0);
    $admin = \Faoxima\Services\Panels\PanelCapability::rx_cap_json($adminResponse);
    if (!empty($adminResponse['error']) || $adminStatus < 200 || $adminStatus >= 300 || $admin === null) {
        $note = !empty($adminResponse['error'])
            ? \Faoxima\Services\Panels\PanelCapability::rx_cap_clean_text((string) $adminResponse['error'], 160)
            : ($admin === null && $adminStatus >= 200 && $adminStatus < 300 ? 'پاسخ /api/admin JSON معتبر نبود.' : \Faoxima\Services\Panels\PanelCapability::rx_cap_http_read($adminResponse)[1]);
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'failed', $note);
        \Faoxima\Services\Panels\PanelCapability::rx_cap_mark_untested_after_connection_failure($report);
        return;
    }
    \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'verified', 'GET /api/admin موفق بود.');
    \Faoxima\Services\Panels\PanelCapability::rx_cap_info($report, 'ادمین متصل', (string) ($admin['username'] ?? ''));
    $perm = \Faoxima\Services\Panels\PanelCapability::rx_cap_apply_marzban_family($report, $admin);

    $listResponse = self::pasarguardRequest($panel, 'GET', '/api/users?limit=1');
    $list = \Faoxima\Services\Panels\PanelCapability::rx_cap_http_read($listResponse);
    if ($list[0] === 'verified') {
        $listData = \Faoxima\Services\Panels\PanelCapability::rx_cap_json($listResponse);
        $total = is_array($listData) && isset($listData['total']) ? (int) $listData['total'] : null;
        $scope = $perm['actions']['list_users'][1] ?? null;
        $list[1] = 'GET /api/users موفق بود' . ($total !== null ? '؛ تعداد قابل مشاهده: ' . $total : '') . ($scope === 'own' ? '؛ فقط کاربران متعلق به این ادمین.' : '.');
    }
    \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'list_users', $list[0], $list[1]);

    $candidates = \Faoxima\Services\Panels\PanelCapability::rx_cap_sample_usernames($panel['name_panel'] ?? '');
    $sampleResponse = null;
    $sampleUser = null;
    $lastRead = null;
    foreach ($candidates as $candidate) {
        $response = self::pasarguardRequest($panel, 'GET', '/api/user/' . rawurlencode($candidate));
        $status = (int) ($response['status'] ?? 0);
        if (empty($response['error']) && $status >= 200 && $status < 300) {
            $sampleResponse = $response;
            $sampleUser = $candidate;
            break;
        }
        $lastRead = $response;
        if ($status !== 404) {
            break;
        }
    }
    if (empty($candidates)) {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_no_sample_user($report);
    } elseif ($sampleResponse === null) {
        $read = \Faoxima\Services\Panels\PanelCapability::rx_cap_http_read(is_array($lastRead) ? $lastRead : []);
        if ((int) ($lastRead['status'] ?? 0) === 404) {
            $read = ['failed', 'هیچ‌کدام از ' . count($candidates) . ' کاربر نمونه در پنل یافت نشد (HTTP 404).'];
        }
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'read_user', $read[0], $read[1]);
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'configs', 'not_tested', 'چون خواندن کاربر نمونه ناموفق بود، دریافت کانفیگ بررسی نشد.');
    } else {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'read_user', 'verified', 'کاربر نمونه ' . $sampleUser . ' خوانده شد؛ فقط دسترسی به همین کاربر تأیید شده است.');
        $links = self::pasarguardGetSubscriptionLinks($sampleUser, $panel['name_panel'], 'links', $sampleResponse);
        $outcome = (string) ($links['outcome'] ?? (!empty($links['error']) ? 'failed' : 'ok'));
        if ($outcome === 'ok' && !empty($links['links'])) {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'configs', 'verified', count($links['links']) . ' کانفیگ از لینک اشتراک کاربر نمونه دریافت شد.');
        } elseif ($outcome === 'empty') {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'configs', 'unknown', 'درخواست اشتراک موفق بود اما پنل برای کاربر نمونه کانفیگی تولید نکرد (پاسخ خالی).');
        } else {
            $stage = (string) ($links['stage'] ?? '');
            $reason = (string) ($links['reason'] ?? '');
            $denied = in_array((int) ($links['http_status'] ?? 0), [403], true);
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'configs', $denied ? 'denied' : 'failed', 'مرحله: ' . $stage . '، علت: ' . $reason);
        }
    }

    $groupsResponse = self::pasarguardRequest($panel, 'GET', '/api/groups');
    $groupsRead = \Faoxima\Services\Panels\PanelCapability::rx_cap_http_read($groupsResponse);
    if ($groupsRead[0] === 'verified') {
        $groupsData = \Faoxima\Services\Panels\PanelCapability::rx_cap_json($groupsResponse);
        $groups = is_array($groupsData['groups'] ?? null) ? $groupsData['groups'] : (is_array($groupsData) && array_keys($groupsData) === range(0, count($groupsData) - 1) ? $groupsData : []);
        $groupIds = [];
        foreach ($groups as $group) {
            if (is_array($group) && isset($group['id'])) {
                $groupIds[] = (int) $group['id'];
            }
        }
        $configured = json_decode((string) ($panel['inbounds'] ?? ''), true);
        $configured = is_array($configured) ? array_values(array_map('intval', $configured)) : [];
        $missing = array_values(array_diff($configured, $groupIds));
        $note = 'GET /api/groups موفق بود؛ ' . count($groupIds) . ' گروه قابل مشاهده.';
        if (!empty($configured)) {
            $note .= empty($missing)
                ? ' همه گروه‌های تنظیم‌شده در ربات موجودند.'
                : ' گروه‌های تنظیم‌شده ناموجود یا خارج از دسترس: ' . implode(', ', $missing);
        } else {
            $note .= ' گروهی در تنظیمات ربات برای این پنل ثبت نشده است.';
        }
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'resources', empty($missing) ? 'verified' : 'failed', $note);
    } else {
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'resources', $groupsRead[0], 'GET /api/groups: ' . $groupsRead[1]);
    }
}
}
