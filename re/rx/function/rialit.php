<?php

const RIALIT_API_BASE_URL = 'https://api.rialit.io/api/v1';
const RIALIT_PAY_BASE_URL = 'https://pay.rialit.io/v1/';
const RIALIT_MIN_AMOUNT = 50000;
const RIALIT_MAX_AMOUNT = 10000000;
const RIALIT_LINK_TTL_SECONDS = 1800;
const RIALIT_RECONCILE_SECONDS = 86400;
const RIALIT_LATE_RECONCILE_SECONDS = 172800;
const RIALIT_POLL_THROTTLE_SECONDS = 120;
const RIALIT_USER_CHECK_THROTTLE_SECONDS = 15;
const RIALIT_CREATE_ATTEMPTS = 3;
const RIALIT_STATUS_ATTEMPTS = 3;
const RIALIT_WEBHOOK_MAX_ATTEMPTS = 12;
const RIALIT_DISPLAY_NAME = '💚 ریالیت';

if (!function_exists('rialitLogEvent')) {
    function rialitLogEvent($event, $message, array $ctx = []): void
    {
        if (function_exists('rx_log_event')) {
            try {
                rx_log_event($event, $message, $ctx);
                return;
            } catch (Throwable $e) {
            }
        }
        $line = (string) $event . ': ' . (string) $message;
        if (!empty($ctx)) {
            $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log('[rialit] ' . $line);
    }
}

if (!function_exists('rialitSetting')) {
    function rialitSetting($name, $default = '')
    {
        if (function_exists('select')) {
            $row = select('PaySetting', 'ValuePay', 'NamePay', (string) $name, 'select');
            if (is_array($row)) {
                return (string) ($row['ValuePay'] ?? $default);
            }
            return (string) $default;
        }
        global $pdo;
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare('SELECT ValuePay FROM PaySetting WHERE NamePay = ? LIMIT 1');
                $stmt->execute([(string) $name]);
                $value = $stmt->fetchColumn();
                return $value === false ? (string) $default : (string) $value;
            } catch (Throwable $e) {
            }
        }
        return (string) $default;
    }
}

if (!function_exists('rialitSaveSetting')) {
    function rialitSaveSetting($name, $value): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?');
            $exists->execute([(string) $name]);
            if ((int) $exists->fetchColumn() > 0) {
                $stmt = $pdo->prepare('UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?');
            } else {
                $stmt = $pdo->prepare('INSERT INTO PaySetting (ValuePay, NamePay) VALUES (?, ?)');
            }
            $stmt->execute([(string) $value, (string) $name]);
            if (function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('PaySetting', 'NamePay', (string) $name);
            }
            return true;
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_SETTING_SAVE_FAILED', $e->getMessage(), ['name' => (string) $name]);
            return false;
        }
    }
}

if (!function_exists('rialitApiKey')) {
    function rialitApiKey(): string
    {
        return trim(rialitSetting('apirialit', ''));
    }
}

if (!function_exists('rialitEnabled')) {
    function rialitEnabled(): bool
    {
        return rialitSetting('statusrialit', 'offrialit') === 'onrialit';
    }
}

if (!function_exists('rialitDisplayName')) {
    function rialitDisplayName(): string
    {
        if (function_exists('faoxima_textbot_get')) {
            $value = trim((string) faoxima_textbot_get('rialit', ''));
            if ($value !== '') {
                return $value;
            }
        }
        if (function_exists('select')) {
            $row = select('textbot', 'text', 'id_text', 'rialit', 'select');
            $value = is_array($row) ? trim((string) ($row['text'] ?? '')) : '';
            if ($value !== '') {
                return $value;
            }
        }
        return RIALIT_DISPLAY_NAME;
    }
}

if (!function_exists('rialitMaskSecret')) {
    function rialitMaskSecret($secret): string
    {
        $secret = trim((string) $secret);
        $length = strlen($secret);
        if ($length === 0) {
            return '';
        }
        if ($length < 12) {
            return str_repeat('•', 6);
        }
        return substr($secret, 0, 4) . '…' . substr($secret, -4);
    }
}

if (!function_exists('rialitAmountRange')) {
    function rialitAmountRange(): array
    {
        $min = (int) rialitSetting('minbalancerialit', (string) RIALIT_MIN_AMOUNT);
        $max = (int) rialitSetting('maxbalancerialit', (string) RIALIT_MAX_AMOUNT);
        if ($min < RIALIT_MIN_AMOUNT) {
            $min = RIALIT_MIN_AMOUNT;
        }
        if ($max <= 0 || $max > RIALIT_MAX_AMOUNT) {
            $max = RIALIT_MAX_AMOUNT;
        }
        if ($max < RIALIT_MIN_AMOUNT) {
            $max = RIALIT_MAX_AMOUNT;
        }
        if ($min > $max) {
            $min = RIALIT_MIN_AMOUNT;
            $max = RIALIT_MAX_AMOUNT;
        }
        return [$min, $max];
    }
}

if (!function_exists('rialitPaymentUrl')) {
    function rialitPaymentUrl($token): string
    {
        $token = trim((string) $token);
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
            return '';
        }
        return RIALIT_PAY_BASE_URL . $token;
    }
}

if (!function_exists('rialitWebhookUrl')) {
    function rialitWebhookUrl(): string
    {
        global $domainhosts;
        $host = trim((string) $domainhosts);
        if ($host === '' && defined('APP_ORIGIN')) {
            $host = (string) APP_ORIGIN;
        }
        $host = preg_replace('#^https?://#i', '', $host);
        $host = rtrim(trim((string) $host), '/');
        if ($host === '') {
            return '';
        }
        return 'https://' . $host . '/payment/rialit.php';
    }
}

if (!function_exists('rialitParseIpList')) {
    function rialitParseIpList($value): array
    {
        $parts = preg_split('/[\s,;]+/', (string) $value);
        $out = [];
        foreach ((array) $parts as $part) {
            $part = trim((string) $part);
            if ($part !== '' && preg_match('/^[0-9A-Fa-f:.]+(?:\/[0-9]{1,3})?$/', $part)) {
                $out[] = $part;
            }
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('rialitTrustedIps')) {
    function rialitTrustedIps(): array
    {
        return rialitParseIpList(rialitSetting('rialit_trusted_ips', '3.123.103.100'));
    }
}

if (!function_exists('rialitTrustedProxies')) {
    function rialitTrustedProxies(): array
    {
        return rialitParseIpList(rialitSetting('rialit_trusted_proxies', ''));
    }
}

if (!function_exists('rialitIpInCidr')) {
    function rialitIpInCidr($ip, $cidr): bool
    {
        $ip = trim((string) $ip);
        $cidr = trim((string) $cidr);
        if ($ip === '' || $cidr === '') {
            return false;
        }
        $prefix = null;
        if (strpos($cidr, '/') !== false) {
            [$cidr, $prefix] = explode('/', $cidr, 2);
            $prefix = (int) $prefix;
        }
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($cidr);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }
        $maxBits = strlen($ipBin) * 8;
        if ($prefix === null) {
            return hash_equals($netBin, $ipBin);
        }
        if ($prefix < 0 || $prefix > $maxBits) {
            return false;
        }
        $fullBytes = intdiv($prefix, 8);
        $remainder = $prefix % 8;
        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($netBin, 0, $fullBytes)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainder)) & 0xFF;
        return (ord($ipBin[$fullBytes]) & $mask) === (ord($netBin[$fullBytes]) & $mask);
    }
}

if (!function_exists('rialitIpMatchesAny')) {
    function rialitIpMatchesAny($ip, array $list): bool
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return false;
        }
        foreach ($list as $entry) {
            if (rialitIpInCidr($ip, (string) $entry)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('rialitClientIp')) {
    function rialitClientIp(): string
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        $proxies = rialitTrustedProxies();
        if ($remote === '' || empty($proxies) || !rialitIpMatchesAny($remote, $proxies)) {
            return $remote;
        }
        $forwarded = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = trim((string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $forwarded = trim((string) $_SERVER['HTTP_X_REAL_IP']);
        }
        if ($forwarded === '') {
            return $remote;
        }
        $chain = array_map('trim', explode(',', $forwarded));
        $chain[] = $remote;
        for ($index = count($chain) - 1; $index >= 0; $index--) {
            $candidate = $chain[$index];
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                return $remote;
            }
            if (!rialitIpMatchesAny($candidate, $proxies)) {
                return $candidate;
            }
        }
        return $remote;
    }
}

if (!function_exists('rialitWebhookAllowed')) {
    function rialitWebhookAllowed($ip): bool
    {
        $trusted = rialitTrustedIps();
        if (empty($trusted)) {
            return false;
        }
        return rialitIpMatchesAny($ip, $trusted);
    }
}

if (!function_exists('rialitRateLimitStatePath')) {
    function rialitRateLimitStatePath(): string
    {
        $root = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT
            : (defined('APP_ROOT_PATH') ? APP_ROOT_PATH : dirname(__DIR__, 3));
        $runtime = $root . DIRECTORY_SEPARATOR . 'cronbot' . DIRECTORY_SEPARATOR . '.runtime';
        if (@is_dir($runtime) || @mkdir($runtime, 0775, true) || @is_dir($runtime)) {
            return $runtime . DIRECTORY_SEPARATOR . 'rialit_ratelimit.json';
        }
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_rialit_ratelimit_' . md5((string) $root) . '.json';
    }
}

if (!function_exists('rialitRateLimitRedisEval')) {
    function rialitRateLimitRedisEval($script, array $keys, array $args)
    {
        if (!function_exists('getRedisConnection')) {
            return null;
        }
        $client = getRedisConnection();
        if ($client === null) {
            return null;
        }
        if (function_exists('rx_redis_key')) {
            $keys = array_map('rx_redis_key', array_values($keys));
        }
        try {
            if ($client instanceof \Redis) {
                $result = $client->eval($script, array_merge($keys, $args), count($keys));
            } else {
                $result = $client->eval($script, count($keys), ...array_merge($keys, $args));
            }
        } catch (\Throwable $e) {
            if (function_exists('rx_redis_mark_unavailable')) {
                rx_redis_mark_unavailable('exception');
            }
            return null;
        }
        return ($result === false || $result === null) ? null : (int) $result;
    }
}

if (!function_exists('rialitRateLimitFileMutate')) {
    function rialitRateLimitFileMutate(callable $mutator)
    {
        $path = rialitRateLimitStatePath();
        $isNew = !is_file($path);
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            return null;
        }
        if ($isNew) {
            @chmod($path, 0664);
        }
        if (!@flock($fh, LOCK_EX)) {
            @fclose($fh);
            return null;
        }
        $state = json_decode((string) stream_get_contents($fh), true);
        if (!is_array($state)) {
            $state = [];
        }
        $result = $mutator($state);
        @ftruncate($fh, 0);
        @rewind($fh);
        @fwrite($fh, (string) json_encode($state));
        @fflush($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);
        return $result;
    }
}

if (!function_exists('rialitRateLimitLimits')) {
    function rialitRateLimitLimits($bucket): array
    {
        if ($bucket === 'send') {
            return [10, 1000];
        }
        if ($bucket === 'webhook_change') {
            return [20, 3600000];
        }
        return [5, 1000];
    }
}

if (!function_exists('rialitRateLimitAcquire')) {
    function rialitRateLimitAcquire($bucket): bool
    {
        [$limit, $windowMs] = rialitRateLimitLimits((string) $bucket);
        $bucket = (string) $bucket;
        $nowMs = (int) floor(microtime(true) * 1000);
        $cacheKey = 'faoxima:rialit:ratelimit:' . $bucket;
        $script = "local now = tonumber(ARGV[1]) "
            . "local blocked = tonumber(redis.call('get', KEYS[2]) or '0') or 0 "
            . "if blocked > now then return 0 end "
            . "redis.call('zremrangebyscore', KEYS[1], '-inf', now - tonumber(ARGV[2])) "
            . "if redis.call('zcard', KEYS[1]) >= tonumber(ARGV[3]) then return 0 end "
            . "redis.call('zadd', KEYS[1], now, ARGV[4]) "
            . "redis.call('pexpire', KEYS[1], tonumber(ARGV[2])) "
            . "return 1";
        $member = $nowMs . ':' . bin2hex(random_bytes(4));
        $redisResult = rialitRateLimitRedisEval(
            $script,
            [$cacheKey . ':window', $cacheKey . ':blocked'],
            [$nowMs, $windowMs, $limit, $member]
        );
        if ($redisResult !== null) {
            return $redisResult === 1;
        }
        $fileResult = rialitRateLimitFileMutate(function (array &$state) use ($bucket, $limit, $windowMs, $nowMs) {
            if (!isset($state['buckets']) || !is_array($state['buckets'])) {
                $state['buckets'] = [];
            }
            $bucketState = isset($state['buckets'][$bucket]) && is_array($state['buckets'][$bucket]) ? $state['buckets'][$bucket] : [];
            if ((int) ($bucketState['blocked_until'] ?? 0) > $nowMs) {
                return false;
            }
            $hits = [];
            foreach ((array) ($bucketState['hits'] ?? []) as $hit) {
                if ((int) $hit > $nowMs - $windowMs) {
                    $hits[] = (int) $hit;
                }
            }
            if (count($hits) >= $limit) {
                $bucketState['hits'] = $hits;
                $state['buckets'][$bucket] = $bucketState;
                return false;
            }
            $hits[] = $nowMs;
            $bucketState['hits'] = $hits;
            $state['buckets'][$bucket] = $bucketState;
            return true;
        });
        if ($fileResult === null) {
            error_log('Rialit rate limiter storage unavailable; request allowed without local accounting');
            return true;
        }
        return $fileResult;
    }
}

if (!function_exists('rialitRateLimitBlock')) {
    function rialitRateLimitBlock($bucket, $seconds): void
    {
        $bucket = (string) $bucket;
        $seconds = max(1, min(3600, (int) $seconds));
        $untilMs = (int) floor(microtime(true) * 1000) + ($seconds * 1000);
        $cacheKey = 'faoxima:rialit:ratelimit:' . $bucket;
        $script = "local cur = tonumber(redis.call('get', KEYS[1]) or '0') or 0 "
            . "if tonumber(ARGV[1]) > cur then redis.call('set', KEYS[1], ARGV[1], 'PX', tonumber(ARGV[2])) end "
            . "return 1";
        if (rialitRateLimitRedisEval($script, [$cacheKey . ':blocked'], [$untilMs, $seconds * 1000]) !== null) {
            return;
        }
        rialitRateLimitFileMutate(function (array &$state) use ($bucket, $untilMs) {
            if (!isset($state['buckets']) || !is_array($state['buckets'])) {
                $state['buckets'] = [];
            }
            $bucketState = isset($state['buckets'][$bucket]) && is_array($state['buckets'][$bucket]) ? $state['buckets'][$bucket] : [];
            if ($untilMs > (int) ($bucketState['blocked_until'] ?? 0)) {
                $bucketState['blocked_until'] = $untilMs;
            }
            $state['buckets'][$bucket] = $bucketState;
            return true;
        });
    }
}

if (!function_exists('rialitError')) {
    function rialitError($code, $message, $statusCode = 0, $retryable = false): array
    {
        return [
            'success' => false,
            'error_code' => (string) $code,
            'error' => (string) $message,
            'status_code' => (int) $statusCode,
            'retryable' => (bool) $retryable,
            'rate_limited' => (string) $code === 'RATE_LIMIT_EXCEEDED',
        ];
    }
}

if (!function_exists('rialitApiRequest')) {
    function rialitApiRequest($method, $path, ?array $payload = null, $attempts = 1, $bucket = 'read')
    {
        $apiKey = rialitApiKey();
        if ($apiKey === '') {
            return rialitError('MISSING_API_KEY', 'کلید API ریالیت تنظیم نشده است', 0, false);
        }
        $method = strtoupper((string) $method);
        $url = RIALIT_API_BASE_URL . (string) $path;
        $attempts = max(1, min(5, (int) $attempts));
        $bucket = (string) $bucket;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if (!rialitRateLimitAcquire($bucket)) {
                return rialitError('RATE_LIMIT_EXCEEDED', 'سقف درخواست‌های ریالیت موقتاً پر است', 429, true);
            }
            $headers = [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ];
            $curl = curl_init();
            $opts = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ];
            if ($method === 'POST') {
                $opts[CURLOPT_POSTFIELDS] = $payload === null
                    ? '{}'
                    : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            curl_setopt_array($curl, $opts);
            $response = curl_exec($curl);
            $curlErrno = curl_errno($curl);
            $curlError = curl_error($curl);
            $statusCode = (int) (curl_getinfo($curl, CURLINFO_HTTP_CODE) ?: 0);
            curl_close($curl);

            if ($response === false) {
                rialitLogEvent('RIALIT_TRANSPORT_ERROR', 'Rialit request failed', [
                    'path' => (string) parse_url($url, PHP_URL_PATH),
                    'errno' => (int) $curlErrno,
                    'error' => (string) $curlError,
                    'attempt' => $attempt,
                ]);
                if ($attempt < $attempts) {
                    usleep(1000000 * $attempt);
                    continue;
                }
                return rialitError('TRANSPORT_ERROR', 'ارتباط با ریالیت برقرار نشد', 0, true);
            }

            $decoded = json_decode((string) $response, true);
            if ($statusCode === 429) {
                rialitRateLimitBlock($bucket, 1);
                if ($attempt < $attempts) {
                    usleep(1000000);
                    continue;
                }
                return rialitError('RATE_LIMIT_EXCEEDED', 'سقف درخواست‌های ریالیت موقتاً پر است', 429, true);
            }
            if ($statusCode === 502 || $statusCode === 503 || $statusCode >= 500) {
                if ($attempt < $attempts) {
                    usleep(1000000 * $attempt);
                    continue;
                }
                return rialitError('UPSTREAM_UNAVAILABLE', 'سرویس ریالیت موقتاً در دسترس نیست', $statusCode, true);
            }
            if (!is_array($decoded)) {
                return rialitError('BAD_RESPONSE', 'پاسخ نامعتبر از سرویس ریالیت', $statusCode, false);
            }
            $decoded['status_code'] = $statusCode;
            if ($statusCode >= 200 && $statusCode < 300) {
                return $decoded;
            }
            $message = isset($decoded['error']) && is_scalar($decoded['error']) ? trim((string) $decoded['error']) : '';
            $code = 'HTTP_' . $statusCode;
            if ($statusCode === 400) {
                $code = 'INVALID_REQUEST';
            } elseif ($statusCode === 401) {
                $code = 'AUTH_FAILED';
                rialitLogEvent('RIALIT_AUTH_FAILED', 'Rialit rejected the configured API key', [
                    'path' => (string) parse_url($url, PHP_URL_PATH),
                ]);
            } elseif ($statusCode === 403) {
                $code = 'FORBIDDEN';
            } elseif ($statusCode === 404) {
                $code = 'NOT_FOUND';
            } elseif ($statusCode === 409) {
                $code = 'CONFLICT';
            }
            return rialitError($code, $message !== '' ? $message : 'خطا در پاسخ ریالیت', $statusCode, false);
        }
        return rialitError('UPSTREAM_UNAVAILABLE', 'سرویس ریالیت موقتاً در دسترس نیست', 0, true);
    }
}

if (!function_exists('rialitNormalizeCreatedPayment')) {
    function rialitNormalizeCreatedPayment(array $decoded)
    {
        $token = isset($decoded['token']) && is_scalar($decoded['token']) ? trim((string) $decoded['token']) : '';
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
            return null;
        }
        if (!isset($decoded['amount']) || !is_numeric($decoded['amount'])) {
            return null;
        }
        $amount = (int) $decoded['amount'];
        $status = isset($decoded['status']) && is_scalar($decoded['status']) ? strtolower(trim((string) $decoded['status'])) : 'pending';
        $payerAmount = isset($decoded['payer_amount']) && is_numeric($decoded['payer_amount']) ? (int) $decoded['payer_amount'] : $amount;
        $payerFee = isset($decoded['payer_fee']) && is_numeric($decoded['payer_fee']) ? (int) $decoded['payer_fee'] : max(0, $payerAmount - $amount);
        return [
            'success' => true,
            'token' => $token,
            'status' => $status,
            'amount' => $amount,
            'payer_amount' => $payerAmount,
            'payer_fee' => $payerFee,
            'http_status' => (int) ($decoded['status_code'] ?? 0),
        ];
    }
}

if (!function_exists('rialitCreatePayment')) {
    function rialitCreatePayment($orderId, $amount, $payerId)
    {
        $orderId = trim((string) $orderId);
        $amount = (int) $amount;
        $payerId = (int) $payerId;
        if ($orderId === '' || strlen($orderId) > 64) {
            return rialitError('INVALID_ORDER_ID', 'شناسه سفارش نامعتبر است', 0, false);
        }
        if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $orderId)) {
            return rialitError('INVALID_ORDER_ID', 'شناسه سفارش نامعتبر است', 0, false);
        }
        if ($amount < RIALIT_MIN_AMOUNT || $amount > RIALIT_MAX_AMOUNT) {
            return rialitError('INVALID_AMOUNT', 'مبلغ باید بین ۵۰,۰۰۰ و ۱۰,۰۰۰,۰۰۰ تومان باشد', 400, false);
        }
        if ($payerId <= 0) {
            return rialitError('INVALID_PAYER', 'شناسه تلگرام خریدار نامعتبر است', 0, false);
        }
        $payload = [
            'amount' => $amount,
            'order_id' => $orderId,
            'payer_id' => $payerId,
        ];
        $decoded = rialitApiRequest('POST', '/send', $payload, RIALIT_CREATE_ATTEMPTS, 'send');
        if (!is_array($decoded) || (isset($decoded['success']) && $decoded['success'] === false)) {
            return is_array($decoded) ? $decoded : rialitError('BAD_RESPONSE', 'پاسخ نامعتبر از سرویس ریالیت', 0, true);
        }
        $statusCode = (int) ($decoded['status_code'] ?? 0);
        if ($statusCode !== 200 && $statusCode !== 201) {
            $message = isset($decoded['error']) && is_scalar($decoded['error']) ? trim((string) $decoded['error']) : '';
            return rialitError('HTTP_' . $statusCode, $message !== '' ? $message : 'ساخت پرداخت در ریالیت ناموفق بود', $statusCode, false);
        }
        $payment = rialitNormalizeCreatedPayment($decoded);
        if ($payment === null) {
            return rialitError('BAD_RESPONSE', 'پاسخ نامعتبر از سرویس ریالیت', $statusCode, true);
        }
        if ($payment['amount'] !== $amount) {
            rialitLogEvent('RIALIT_CREATE_AMOUNT_MISMATCH', 'Rialit created payment amount does not match the local order', [
                'order_id' => $orderId,
                'requested' => $amount,
                'returned' => $payment['amount'],
            ]);
            return rialitError('AMOUNT_MISMATCH', 'مبلغ پرداخت با سفارش مطابقت ندارد', $statusCode, false);
        }
        return $payment;
    }
}

if (!function_exists('rialitCheckPayment')) {
    function rialitCheckPayment($token)
    {
        $token = trim((string) $token);
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
            return rialitError('INVALID_TOKEN', 'شناسه پرداخت نامعتبر است', 0, false);
        }
        return rialitApiRequest('GET', '/status/' . rawurlencode($token), [], RIALIT_STATUS_ATTEMPTS, 'read');
    }
}

if (!function_exists('rialitReadWebhook')) {
    function rialitReadWebhook(): array
    {
        $decoded = rialitApiRequest('GET', '/webhook', [], 2, 'read');
        if (!is_array($decoded) || (isset($decoded['success']) && $decoded['success'] === false)) {
            $message = is_array($decoded) && isset($decoded['error']) ? (string) $decoded['error'] : 'خواندن وب‌هوک ریالیت ناموفق بود';
            rialitSaveWebhookState('', false, $message, 'read');
            return ['ok' => false, 'message' => $message, 'webhook_url' => ''];
        }
        $statusCode = (int) ($decoded['status_code'] ?? 0);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = isset($decoded['error']) && is_scalar($decoded['error']) ? (string) $decoded['error'] : 'خواندن وب‌هوک ریالیت ناموفق بود';
            rialitSaveWebhookState('', false, $message, 'read');
            return ['ok' => false, 'message' => $message, 'webhook_url' => ''];
        }
        $url = '';
        if (array_key_exists('webhook_url', $decoded)) {
            $url = is_scalar($decoded['webhook_url']) ? trim((string) $decoded['webhook_url']) : '';
        }
        rialitSaveWebhookState($url, true, $url === '' ? 'وب‌هوک روی ریالیت تنظیم نشده است' : 'وب‌هوک ریالیت خوانده شد', 'read');
        return ['ok' => true, 'message' => $url === '' ? 'وب‌هوک روی ریالیت تنظیم نشده است' : 'وب‌هوک ریالیت خوانده شد', 'webhook_url' => $url];
    }
}

if (!function_exists('rialitRegisterWebhook')) {
    function rialitRegisterWebhook($url = null): array
    {
        $url = $url === null ? rialitWebhookUrl() : trim((string) $url);
        if ($url === '' || stripos($url, 'https://') !== 0 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $message = 'آدرس وب‌هوک نامعتبر است؛ آدرس نصب باید با https تنظیم شده باشد';
            rialitSaveWebhookState($url, false, $message, 'register');
            return ['ok' => false, 'message' => $message, 'webhook_url' => $url];
        }
        $decoded = rialitApiRequest('POST', '/webhook', ['webhook_url' => $url], 1, 'webhook_change');
        if (!is_array($decoded) || (isset($decoded['success']) && $decoded['success'] === false)) {
            $message = is_array($decoded) && isset($decoded['error']) ? (string) $decoded['error'] : 'ثبت وب‌هوک ریالیت ناموفق بود';
            rialitSaveWebhookState($url, false, $message, 'register');
            return ['ok' => false, 'message' => $message, 'webhook_url' => $url];
        }
        $statusCode = (int) ($decoded['status_code'] ?? 0);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = isset($decoded['error']) && is_scalar($decoded['error']) ? (string) $decoded['error'] : 'ثبت وب‌هوک ریالیت ناموفق بود';
            rialitSaveWebhookState($url, false, $message, 'register');
            return ['ok' => false, 'message' => $message, 'webhook_url' => $url];
        }
        $saved = '';
        if (array_key_exists('webhook_url', $decoded)) {
            $saved = is_scalar($decoded['webhook_url']) ? trim((string) $decoded['webhook_url']) : '';
        }
        $effective = $saved !== '' ? $saved : $url;
        rialitSaveWebhookState($effective, true, 'وب‌هوک ریالیت با موفقیت ثبت شد', 'register');
        return ['ok' => true, 'message' => 'وب‌هوک ریالیت با موفقیت ثبت شد', 'webhook_url' => $effective];
    }
}

if (!function_exists('rialitSaveWebhookState')) {
    function rialitSaveWebhookState($url, $ok, $message, $action): void
    {
        rialitSaveSetting('rialit_webhook_url', (string) $url);
        rialitSaveSetting('rialit_webhook_status', $ok ? 'ok' : 'error');
        rialitSaveSetting('rialit_webhook_checked_at', (string) time());
        rialitSaveSetting('rialit_webhook_last_action', (string) $action);
        rialitSaveSetting('rialit_webhook_last_message', mb_substr((string) $message, 0, 240));
    }
}

if (!function_exists('rialitWebhookStatusSummary')) {
    function rialitWebhookStatusSummary(): array
    {
        return [
            'generated_url' => rialitWebhookUrl(),
            'remote_url' => trim(rialitSetting('rialit_webhook_url', '')),
            'status' => trim(rialitSetting('rialit_webhook_status', 'not_configured')),
            'checked_at' => (int) rialitSetting('rialit_webhook_checked_at', '0'),
            'last_action' => trim(rialitSetting('rialit_webhook_last_action', '')),
            'last_message' => trim(rialitSetting('rialit_webhook_last_message', '')),
        ];
    }
}

if (!function_exists('rialitFetchReport')) {
    function rialitFetchReport($orderId)
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND Payment_Method = 'rialit' LIMIT 1");
            $stmt->execute([(string) $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_FETCH_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return null;
        }
    }
}

if (!function_exists('rialitUpdateColumns')) {
    function rialitUpdateColumns($orderId, array $columns, $onlyOpen = false): int
    {
        global $pdo;
        if (!($pdo instanceof PDO) || empty($columns)) {
            return 0;
        }
        $sets = [];
        $params = [];
        foreach ($columns as $name => $value) {
            $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $name);
            if ($name === '') {
                continue;
            }
            $sets[] = '`' . $name . '` = ?';
            $params[] = $value;
        }
        if (empty($sets)) {
            return 0;
        }
        $params[] = (string) $orderId;
        $sql = 'UPDATE Payment_report SET ' . implode(', ', $sets) . " WHERE id_order = ? AND Payment_Method = 'rialit'";
        if ($onlyOpen) {
            $sql .= " AND payment_Status IN ('Unpaid','pending','waiting')";
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() > 0 && function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount();
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_UPDATE_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return 0;
        }
    }
}

if (!function_exists('rialitMarkCreated')) {
    function rialitMarkCreated($orderId, $amount, $payerId): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET rialit_created_at = COALESCE(rialit_created_at, ?),
                        rialit_payer_id = ?,
                        rialit_local_state = COALESCE(rialit_local_state, 'awaiting_payment')
                  WHERE id_order = ? AND Payment_Method = 'rialit'"
            );
            $stmt->execute([time(), (int) $payerId, (string) $orderId]);
            $report = rialitFetchReport($orderId);
            return $report !== null
                && (int) ($report['rialit_created_at'] ?? 0) > 0
                && (int) ($report['rialit_payer_id'] ?? 0) === (int) $payerId
                && (int) ($report['price'] ?? 0) === (int) $amount;
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_MARK_CREATED_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return false;
        }
    }
}

if (!function_exists('rialitStorePayment')) {
    function rialitStorePayment($orderId, array $payment): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET rialit_token = ?,
                        rialit_status = CASE WHEN rialit_status = 'paid' OR payment_Status = 'paid' THEN rialit_status ELSE ? END,
                        rialit_payer_amount = ?,
                        rialit_payer_fee = ?,
                        rialit_created_at = COALESCE(rialit_created_at, ?),
                        rialit_last_checked_at = ?,
                        rialit_local_state = CASE
                            WHEN payment_Status = 'paid' THEN rialit_local_state
                            WHEN ? = 'paid' THEN 'paid_verified'
                            ELSE 'awaiting_payment'
                        END
                  WHERE id_order = ? AND Payment_Method = 'rialit'
                    AND (rialit_token IS NULL OR rialit_token = '' OR BINARY rialit_token = BINARY ?)"
            );
            $stmt->execute([
                (string) $payment['token'],
                (string) $payment['status'],
                (int) $payment['payer_amount'],
                (int) $payment['payer_fee'],
                $now,
                $now,
                (string) $payment['status'],
                (string) $orderId,
                (string) $payment['token'],
            ]);
            if ($stmt->rowCount() > 0 && function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            $stored = rialitFetchReport($orderId);
            return $stored !== null && hash_equals((string) $payment['token'], (string) ($stored['rialit_token'] ?? ''));
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_STORE_PAYMENT_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return false;
        }
    }
}

if (!function_exists('rialitReportCreatedAt')) {
    function rialitReportCreatedAt(array $report): int
    {
        $created = (int) ($report['rialit_created_at'] ?? 0);
        if ($created > 0) {
            return $created;
        }
        $raw = trim((string) ($report['time'] ?? ''));
        if ($raw !== '') {
            if (ctype_digit($raw)) {
                return (int) $raw;
            }
            $raw = strtr($raw, [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            ]);
            $ts = strtotime(str_replace('/', '-', $raw));
            if ($ts !== false) {
                return (int) $ts;
            }
        }
        return 0;
    }
}

if (!function_exists('rialitLinkExpiresAt')) {
    function rialitLinkExpiresAt(array $report): int
    {
        $created = rialitReportCreatedAt($report);
        return $created > 0 ? $created + RIALIT_LINK_TTL_SECONDS : 0;
    }
}

if (!function_exists('rialitLinkAvailable')) {
    function rialitLinkAvailable(array $report): bool
    {
        if ((int) ($report['rialit_abandoned_at'] ?? 0) > 0) {
            return false;
        }
        $token = trim((string) ($report['rialit_token'] ?? ''));
        if ($token === '') {
            return false;
        }
        if (strtolower((string) ($report['payment_Status'] ?? '')) === 'paid') {
            return false;
        }
        $remote = strtolower(trim((string) ($report['rialit_status'] ?? '')));
        if (in_array($remote, ['paid', 'expired', 'cancelled'], true)) {
            return false;
        }
        $expires = rialitLinkExpiresAt($report);
        return $expires > 0 && time() < $expires;
    }
}

if (!function_exists('rialitUserHasPending')) {
    function rialitUserHasPending($userId): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM Payment_report
                  WHERE id_user = ?
                    AND Payment_Method = 'rialit'
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND COALESCE(rialit_abandoned_at, 0) = 0
                    AND (rialit_status IS NULL OR rialit_status IN ('pending','paid'))"
            );
            $stmt->execute([(string) $userId]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('rialitFindPendingForUser')) {
    function rialitFindPendingForUser($userId, $invoice, $amount, array $context = [])
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM Payment_report
                  WHERE id_user = ?
                    AND Payment_Method = 'rialit'
                    AND payment_Status IN ('Unpaid','pending','waiting')
                    AND id_invoice = ?
                    AND price = ?
                    AND COALESCE(rialit_abandoned_at, 0) = 0
                    AND COALESCE(charge_bonus, 0) = ?
                    AND COALESCE(discount_code, '') = ?
                    AND COALESCE(discount_amount, 0) = ?
                    AND COALESCE(price_before_discount, 0) = ?
                    AND (rialit_status IS NULL OR rialit_status NOT IN ('expired','cancelled'))
                    AND (rialit_created_at IS NULL OR rialit_created_at >= ?)
                    AND (? = '' OR EXISTS (SELECT 1 FROM checkout_order ck WHERE ck.checkout_id = ? AND ck.payment_order_id = Payment_report.id_order AND ck.id_user = Payment_report.id_user))
                  ORDER BY id DESC
                  LIMIT 1"
            );
            $checkoutId = (string) ($context['checkout_id'] ?? '');
            $stmt->execute([
                (string) $userId, (string) $invoice, (int) $amount,
                (int) ($context['charge_bonus'] ?? 0),
                (string) ($context['discount_code'] ?? ''),
                (float) ($context['discount_amount'] ?? 0),
                (float) ($context['price_before_discount'] ?? 0),
                time() - RIALIT_RECONCILE_SECONDS, $checkoutId, $checkoutId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_FIND_PENDING_FAILED', $e->getMessage(), ['user_id' => (string) $userId]);
            return null;
        }
    }
}

if (!function_exists('rialitBotPaymentView')) {
    function rialitBotPaymentView(array $report): array
    {
        $escape = static function ($value): string {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $orderId = (string) $report['id_order'];
        $localStatus = strtolower((string) ($report['payment_Status'] ?? ''));
        $remoteStatus = (string) ($report['rialit_status'] ?? '');
        $status = '⏳ در انتظار نتیجه';
        if ((int) ($report['rialit_abandoned_at'] ?? 0) > 0 && $localStatus !== 'paid') {
            $status = '🚫 از این پرداخت انصراف داده‌اید. اگر بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود.';
        } elseif ($localStatus === 'paid') {
            $status = !empty($report['direct_payment_done']) ? '✅ پرداخت و سفارش تکمیل شد' : '⏳ پرداخت تأیید شد؛ در حال تکمیل سفارش';
        } elseif ($remoteStatus === 'expired' || $remoteStatus === 'cancelled') {
            $status = $remoteStatus === 'expired' ? '⌛ پرداخت منقضی شده است' : '❌ پرداخت در ریالیت لغو شده است';
        } elseif (empty($report['rialit_token'])) {
            $status = '⏳ در حال بازیابی لینک پرداخت';
        } elseif (!rialitLinkAvailable($report)) {
            $status = '⏳ مهلت ورود به پرداخت تمام شده؛ در انتظار نتیجه';
        }
        $text = '<b>' . $escape(rialitDisplayName()) . "</b>\n\n" . $status
            . "\n🛒 کد پیگیری: <code>" . $escape($orderId) . '</code>'
            . "\n💰 مبلغ سفارش: <b>" . number_format((int) $report['price']) . '</b> تومان';
        if ((int) ($report['rialit_payer_fee'] ?? 0) > 0) {
            $text .= "\n💳 مبلغ پرداختی با کارمزد: <b>" . number_format((int) $report['rialit_payer_amount']) . '</b> تومان';
        }
        $rows = [];
        if (rialitLinkAvailable($report)) {
            $rows[] = [rx_payment_button('rialit', 'continue_link', ['text' => '💳 ادامه پرداخت', 'url' => rialitPaymentUrl($report['rialit_token'])])];
        }
        $rows[] = [rx_payment_button('rialit', 'check_status', ['text' => '🔄 بررسی وضعیت', 'callback_data' => 'rl_view_' . $orderId])];
        if ((int) ($report['rialit_abandoned_at'] ?? 0) === 0 && in_array($localStatus, ['unpaid','pending','waiting','expire','cancelled'], true) && $remoteStatus !== 'paid') {
            $rows[] = [rx_payment_button('rialit', 'cancel_transaction', ['text' => '🚫 انصراف و پرداخت جدید', 'callback_data' => 'rl_cancel_' . $orderId])];
        }
        $rows[] = [rx_payment_button('rialit', 'pending_list', ['text' => '🔙 تراکنش‌های در انتظار', 'callback_data' => 'tpc_list'])];
        return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
    }
}

if (!function_exists('rialitCancelLocally')) {
    function rialitCancelLocally($orderId, $userId): array
    {
        global $pdo;
        if (!($pdo instanceof PDO) || $pdo->inTransaction()) {
            return ['ok' => false, 'message' => 'ثبت انصراف ممکن نیست؛ دوباره تلاش کنید.'];
        }
        $locks = [
            'faoxima_payment_init_' . $userId . '_rialit',
            'rialit_verify_' . substr(md5((string) $orderId), 0, 24),
        ];
        $acquired = [];
        try {
            foreach ($locks as $name) {
                $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
                $stmt->execute([$name]);
                if ((int) $stmt->fetchColumn() !== 1) {
                    return ['ok' => false, 'message' => 'پرداخت در حال بررسی است؛ چند لحظه بعد دوباره تلاش کنید.'];
                }
                $acquired[] = $name;
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND id_user = ? AND Payment_Method = 'rialit' LIMIT 1 FOR UPDATE");
            $stmt->execute([(string) $orderId, (string) $userId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($report)) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
            }
            $status = strtolower((string) ($report['payment_Status'] ?? ''));
            if (!in_array($status, ['unpaid','pending','waiting','expire','cancelled'], true)
                || (int) ($report['direct_payment_done'] ?? 0) === 1
                || (string) ($report['rialit_status'] ?? '') === 'paid') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'این پرداخت تأیید شده یا در حال تکمیل است و امکان انصراف ندارد.'];
            }
            $pdo->prepare("UPDATE Payment_report SET payment_Status = 'cancelled', rialit_abandoned_at = COALESCE(rialit_abandoned_at, ?), rialit_local_state = 'locally_cancelled' WHERE id_order = ? AND id_user = ? AND Payment_Method = 'rialit'")
                ->execute([time(), (string) $orderId, (string) $userId]);
            $pdo->commit();
            if (function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            if (function_exists('rx_redis_del')) {
                rx_redis_del('faoxima:paystatus:' . (string) $orderId . ':' . (string) $userId);
            }
            return ['ok' => true, 'message' => 'انصراف ثبت شد؛ می‌توانید پرداخت جدید بسازید. این کار لینک ریالیت را باطل نمی‌کند؛ اگر پرداخت قبلی بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            rialitLogEvent('RIALIT_LOCAL_CANCEL_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return ['ok' => false, 'message' => 'ثبت انصراف ناموفق بود؛ دوباره تلاش کنید.'];
        } finally {
            foreach (array_reverse($acquired) as $name) {
                try {
                    $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
                } catch (Throwable $e) {
                }
            }
        }
    }
}

if (!function_exists('rialitBotListMerge')) {
    function rialitBotListMerge($userId, array $view): array
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return $view;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = ? AND Payment_Method = 'rialit' AND payment_Status IN ('Unpaid','pending','waiting') AND COALESCE(rialit_abandoned_at, 0) = 0 AND (rialit_status IS NULL OR rialit_status IN ('pending','paid')) ORDER BY id DESC LIMIT 8");
            $stmt->execute([(string) $userId]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($reports)) {
                return $view;
            }
            $keyboard = json_decode((string) ($view['keyboard'] ?? ''), true);
            $rows = is_array($keyboard['inline_keyboard'] ?? null) ? $keyboard['inline_keyboard'] : [];
            if (!empty($rows) && ($rows[count($rows) - 1][0]['callback_data'] ?? '') === 'account') {
                array_pop($rows);
            }
            $text = str_replace('در حال حاضر تراکنش پرداخت نیمه‌تمامی ندارید.', '', (string) ($view['text'] ?? ''));
            foreach ($reports as $report) {
                $paymentView = rialitBotPaymentView($report);
                $text .= "\n\n" . $paymentView['text'];
                $rows[] = [rx_payment_button('rialit', 'list_view', ['text' => '💚 پیگیری ریالیت · ' . (string) $report['id_order'], 'callback_data' => 'rl_view_' . (string) $report['id_order']])];
            }
            $rows[] = [rx_payment_button('rialit', 'back_account', ['text' => '🔙 بازگشت', 'callback_data' => 'account'])];
            return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_BOT_LIST_FAILED', $e->getMessage());
            return $view;
        }
    }
}

if (!function_exists('rialitReopenCancelledForConfirmation')) {
    function rialitReopenCancelledForConfirmation($orderId): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE Payment_report
                    SET payment_Status = 'expire', rialit_local_state = 'late_confirmation'
                  WHERE id_order = ?
                    AND Payment_Method = 'rialit'
                    AND payment_Status = 'cancelled'"
            );
            $stmt->execute([(string) $orderId]);
            if ($stmt->rowCount() > 0 && function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('Payment_report', 'id_order', (string) $orderId);
            }
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_REOPEN_CANCELLED_FAILED', $e->getMessage(), ['order_id' => (string) $orderId]);
            return false;
        }
    }
}

if (!function_exists('rialitVerifyPayment')) {
    function rialitVerifyPayment($orderId, array $options = []): array
    {
        global $pdo;
        $orderId = trim((string) $orderId);
        if ($orderId === '' || !($pdo instanceof PDO)) {
            return ['state' => 'error', 'reason' => 'unavailable'];
        }
        $report = rialitFetchReport($orderId);
        if ($report === null) {
            return ['state' => 'not_found', 'reason' => 'order_not_found'];
        }
        if ((string) ($report['Payment_Method'] ?? '') !== 'rialit') {
            return ['state' => 'mismatch', 'reason' => 'payment_method'];
        }
        $token = trim((string) ($report['rialit_token'] ?? ''));
        $expectedPayer = (int) ($report['rialit_payer_id'] ?? 0);
        if ($expectedPayer <= 0) {
            $expectedPayer = (int) ($report['id_user'] ?? 0);
        }
        $expectedAmount = (int) ($report['price'] ?? 0);
        if ($expectedAmount <= 0) {
            return ['state' => 'error', 'reason' => 'amount_missing'];
        }

        $lockName = 'rialit_verify_' . substr(md5($orderId), 0, 24);
        $lock = null;
        try {
            $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
            $lock->execute([$lockName]);
            if ((int) $lock->fetchColumn() !== 1) {
                return ['state' => 'busy', 'reason' => 'locked'];
            }
            $report = rialitFetchReport($orderId);
            if ($report === null) {
                return ['state' => 'retry', 'reason' => 'report_unavailable'];
            }
            $token = trim((string) ($report['rialit_token'] ?? ''));
            $eventToken = trim((string) ($options['token'] ?? ''));
            if ($token === '' && $eventToken === '' && (int) ($report['rialit_abandoned_at'] ?? 0) > 0) {
                return ['state' => 'cancelled', 'reason' => 'locally_cancelled_without_token'];
            }
            $needsTokenPersistence = $token === '' && $eventToken !== '';
            if ($needsTokenPersistence) {
                $token = $eventToken;
            }
            if ($token === '') {
                $created = rialitCreatePayment($orderId, $expectedAmount, $expectedPayer);
                if (empty($created['success']) || !rialitStorePayment($orderId, $created)) {
                    return ['state' => 'retry', 'reason' => 'creation_recovery_failed'];
                }
                $token = (string) $created['token'];
            }
            if (!preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
                return ['state' => 'mismatch', 'reason' => 'invalid_token'];
            }
            if ($eventToken !== '' && !hash_equals($token, $eventToken)) {
                return ['state' => 'mismatch', 'reason' => 'webhook_token'];
            }
            $check = rialitCheckPayment($token);
            if (!is_array($check) || (isset($check['success']) && $check['success'] === false)) {
                $code = is_array($check) ? (string) ($check['error_code'] ?? 'ERROR') : 'ERROR';
                if ($code === 'AUTH_FAILED') {
                    rialitUpdateColumns($orderId, ['rialit_local_state' => 'auth_error']);
                    rialitLogEvent('RIALIT_VERIFY_AUTH_FAILED', 'Rialit status check was rejected by authentication', ['order_id' => $orderId]);
                    return ['state' => 'auth_error', 'reason' => 'auth_failed'];
                }
                $state = ($code === 'RATE_LIMIT_EXCEEDED' || $code === 'UPSTREAM_UNAVAILABLE' || $code === 'TRANSPORT_ERROR') ? 'retry' : 'error';
                return ['state' => $state, 'reason' => strtolower($code)];
            }
            $statusCode = (int) ($check['status_code'] ?? 0);
            if ($statusCode < 200 || $statusCode >= 300) {
                if ($statusCode === 401) {
                    rialitUpdateColumns($orderId, ['rialit_local_state' => 'auth_error']);
                    return ['state' => 'auth_error', 'reason' => 'auth_failed'];
                }
                $retryable = $statusCode === 429 || $statusCode >= 500;
                return ['state' => $retryable ? 'retry' : 'error', 'reason' => 'http_' . $statusCode];
            }

            $remoteToken = isset($check['token']) && is_scalar($check['token']) ? trim((string) $check['token']) : '';
            $remoteOrder = isset($check['order_id']) && is_scalar($check['order_id']) ? trim((string) $check['order_id']) : '';
            $remotePayer = isset($check['payer_id']) && is_numeric($check['payer_id']) ? (int) $check['payer_id'] : 0;
            $remoteAmount = isset($check['amount']) && is_numeric($check['amount']) ? (int) $check['amount'] : 0;
            $remoteStatus = isset($check['status']) && is_scalar($check['status']) ? strtolower(trim((string) $check['status'])) : '';
            $payerAmount = isset($check['payer_amount']) && is_numeric($check['payer_amount']) ? (int) $check['payer_amount'] : $remoteAmount;
            $payerFee = isset($check['payer_fee']) && is_numeric($check['payer_fee']) ? (int) $check['payer_fee'] : max(0, $payerAmount - $remoteAmount);

            $identityOk = $remoteToken !== ''
                && hash_equals($token, $remoteToken)
                && $remoteOrder !== ''
                && hash_equals($orderId, $remoteOrder)
                && $remotePayer > 0
                && $remotePayer === $expectedPayer;
            if (!$identityOk) {
                rialitLogEvent('RIALIT_VERIFY_IDENTITY_MISMATCH', 'Rialit payment identity does not match the local order', [
                    'order_id' => $orderId,
                    'token_match' => $remoteToken !== '' && hash_equals($token, $remoteToken),
                    'order_match' => $remoteOrder !== '' && hash_equals($orderId, $remoteOrder),
                    'payer_match' => $remotePayer > 0 && $remotePayer === $expectedPayer,
                ]);
                rialitUpdateColumns($orderId, [
                    'rialit_status' => $remoteStatus,
                    'rialit_last_checked_at' => time(),
                    'rialit_local_state' => 'identity_mismatch',
                ]);
                return ['state' => 'mismatch', 'reason' => 'identity'];
            }
            if ($remoteAmount !== $expectedAmount) {
                rialitLogEvent('RIALIT_VERIFY_AMOUNT_MISMATCH', 'Rialit payment amount does not match the local order', [
                    'order_id' => $orderId,
                    'expected' => $expectedAmount,
                    'received' => $remoteAmount,
                ]);
                rialitUpdateColumns($orderId, [
                    'rialit_status' => $remoteStatus,
                    'rialit_last_checked_at' => time(),
                    'rialit_local_state' => 'amount_mismatch',
                ]);
                return ['state' => 'mismatch', 'reason' => 'amount'];
            }
            if ($needsTokenPersistence && !rialitStorePayment($orderId, [
                'token' => $remoteToken,
                'status' => $remoteStatus,
                'payer_amount' => $payerAmount,
                'payer_fee' => $payerFee,
            ])) {
                return ['state' => 'retry', 'reason' => 'token_persistence_failed'];
            }

            $columns = [
                'rialit_status' => $remoteStatus,
                'rialit_payer_amount' => $payerAmount,
                'rialit_payer_fee' => $payerFee,
                'rialit_last_checked_at' => time(),
            ];
            if (isset($check['paid_at']) && is_scalar($check['paid_at']) && trim((string) $check['paid_at']) !== '') {
                $columns['rialit_paid_at'] = mb_substr(trim((string) $check['paid_at']), 0, 40);
            }
            if (isset($check['reference']) && is_scalar($check['reference']) && trim((string) $check['reference']) !== '') {
                $columns['rialit_reference'] = mb_substr(trim((string) $check['reference']), 0, 64);
            }
            if ($remoteStatus !== 'paid') {
                if (!in_array($remoteStatus, ['pending', 'expired', 'cancelled'], true)) {
                    return ['state' => 'retry', 'reason' => 'invalid_remote_status'];
                }
                if (strtolower((string) ($report['payment_Status'] ?? '')) === 'paid') {
                    return ['state' => 'retry', 'reason' => 'paid_status_conflict'];
                }
                $columns['rialit_local_state'] = $remoteStatus !== '' ? 'remote_' . $remoteStatus : 'remote_unknown';
                if ($remoteStatus === 'expired' || $remoteStatus === 'cancelled') {
                    $columns['payment_Status'] = $remoteStatus === 'expired' ? 'expire' : 'cancelled';
                }
                rialitUpdateColumns($orderId, $columns);
                $saved = rialitFetchReport($orderId);
                if ($saved === null || (string) ($saved['rialit_status'] ?? '') !== $remoteStatus) {
                    return ['state' => 'retry', 'reason' => 'status_persistence_failed'];
                }
                return ['state' => $remoteStatus !== '' ? $remoteStatus : 'pending', 'reason' => 'not_paid'];
            }

            $columns['rialit_local_state'] = 'paid_verified';
            rialitUpdateColumns($orderId, $columns);

            $previousStatus = strtolower(trim((string) ($report['payment_Status'] ?? '')));
            if ($previousStatus === 'cancelled') {
                rialitReopenCancelledForConfirmation($orderId);
            }
            if (!function_exists('payment_confirm_paid')) {
                require_once __DIR__ . '/../../../lib/PaymentConfirm.php';
            }

            $lines = [
                '🧾 شناسه پرداخت ریالیت: ' . $token,
                '💸 مبلغ نهایی پرداخت‌شده: ' . number_format($payerAmount) . ' تومان',
                '🧾 کارمزد ریالیت: ' . number_format($payerFee) . ' تومان',
            ];
            if (!empty($check['reference']) && is_scalar($check['reference'])) {
                $lines[] = '🔗 شماره پیگیری بانکی: ' . mb_substr(trim((string) $check['reference']), 0, 64);
            }
            if (in_array($previousStatus, ['cancelled', 'expire'], true)) {
                $lines[] = '⏰ تأیید دیرهنگام پس از ' . ($previousStatus === 'cancelled' ? 'لغو محلی فاکتور' : 'پایان مهلت محلی');
            }
            $threadRow = function_exists('select') ? select('topicid', 'idreport', 'report', 'paymentreport', 'select') : null;
            $result = payment_confirm_paid($orderId, (int) ($report['rialit_abandoned_at'] ?? 0) > 0 ? '' : 'chashbackrialit', [
                'method' => rialitDisplayName(),
                'thread_id' => is_array($threadRow) ? ($threadRow['idreport'] ?? null) : null,
                'extra_lines' => $lines,
            ]);
            $fulfilled = !empty($result['ok']);
            $confirmReason = (string) ($result['reason'] ?? '');
            rialitUpdateColumns($orderId, [
                'rialit_local_state' => $fulfilled ? 'completed' : 'paid_pending_fulfillment',
            ]);
            $confirmedReport = rialitFetchReport($orderId);
            if ($confirmedReport === null || strtolower((string) ($confirmedReport['payment_Status'] ?? '')) !== 'paid') {
                rialitLogEvent('RIALIT_CONFIRM_BLOCKED', 'Rialit paid verification could not be applied to the local payment', [
                    'order_id' => $orderId,
                    'reason' => $confirmReason,
                ]);
                return ['state' => 'retry', 'reason' => $confirmReason !== '' ? $confirmReason : 'confirmation_not_persisted'];
            }
            return [
                'state' => 'paid',
                'reason' => '',
                'confirmed' => true,
                'fulfilled' => $fulfilled,
                'result' => $result,
            ];
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_VERIFY_FAILED', $e->getMessage(), ['order_id' => $orderId]);
            return ['state' => 'error', 'reason' => 'exception'];
        } finally {
            if ($lock !== null) {
                try {
                    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                } catch (Throwable $e) {
                }
            }
        }
    }
}

if (!function_exists('rialitPollThrottled')) {
    function rialitPollThrottled($orderId, $userId = null)
    {
        $report = rialitFetchReport($orderId);
        if ($report === null) {
            return null;
        }
        if ($userId !== null && (string) ($report['id_user'] ?? '') !== (string) $userId) {
            return null;
        }
        $localStatus = strtolower(trim((string) ($report['payment_Status'] ?? '')));
        if ($localStatus === 'paid' && (int) ($report['direct_payment_done'] ?? 0) === 1) {
            return $report;
        }
        if (!in_array($localStatus, ['unpaid', 'pending', 'waiting', 'expire', 'cancelled'], true)) {
            return $report;
        }
        if ((int) ($report['rialit_last_checked_at'] ?? 0) > time() - RIALIT_USER_CHECK_THROTTLE_SECONDS) {
            return $report;
        }
        rialitUpdateColumns($orderId, ['rialit_last_checked_at' => time()], false);
        rialitVerifyPayment($orderId, ['source' => 'user']);
        return rialitFetchReport($orderId) ?? $report;
    }
}

if (!function_exists('rialitWebhookTableDdl')) {
    function rialitWebhookTableDdl(): string
    {
        return "CREATE TABLE IF NOT EXISTS rialit_webhook_event (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(64) NOT NULL,
            event_type VARCHAR(40) NOT NULL DEFAULT '',
            token VARCHAR(64) NULL,
            order_id VARCHAR(64) NULL,
            payload MEDIUMTEXT NULL,
            state VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            next_attempt_at BIGINT NOT NULL DEFAULT 0,
            last_error VARCHAR(255) NULL,
            received_at BIGINT NOT NULL DEFAULT 0,
            processed_at BIGINT NULL,
            UNIQUE KEY uniq_rialit_event (event_id),
            KEY idx_rialit_event_state (state, next_attempt_at),
            KEY idx_rialit_event_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}

if (!function_exists('rialitEnsureWebhookSchema')) {
    function rialitEnsureWebhookSchema(?PDO $pdo = null): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        $pdo = $pdo instanceof PDO ? $pdo : (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? $GLOBALS['pdo'] : null);
        if (!($pdo instanceof PDO)) {
            return false;
        }
        try {
            $pdo->query('SELECT 1 FROM rialit_webhook_event LIMIT 1');
            $ready = true;
            return true;
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec(rialitWebhookTableDdl());
            $ready = true;
            return true;
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_WEBHOOK_SCHEMA_FAILED', $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('rialitStoreWebhookEvent')) {
    function rialitStoreWebhookEvent($eventId, $eventType, $token, $orderId, $payload): array
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !rialitEnsureWebhookSchema($pdo)) {
            return ['ok' => false, 'duplicate' => false, 'message' => 'schema_unavailable'];
        }
        $eventId = trim((string) $eventId);
        if ($eventId === '' || !preg_match('/^[A-Za-z0-9\-_.:]{1,64}$/', $eventId)) {
            return ['ok' => false, 'duplicate' => false, 'message' => 'invalid_event_id'];
        }
        $now = time();
        try {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO rialit_webhook_event (event_id, event_type, token, order_id, payload, state, attempts, next_attempt_at, received_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)'
            );
            $stmt->execute([
                $eventId,
                mb_substr((string) $eventType, 0, 40),
                $token !== '' ? mb_substr((string) $token, 0, 64) : null,
                $orderId !== '' ? mb_substr((string) $orderId, 0, 64) : null,
                (string) $payload,
                'pending',
                $now,
                $now,
            ]);
            return ['ok' => true, 'duplicate' => $stmt->rowCount() === 0, 'message' => ''];
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_WEBHOOK_STORE_FAILED', $e->getMessage(), ['event_id' => $eventId]);
            return ['ok' => false, 'duplicate' => false, 'message' => 'store_failed'];
        }
    }
}

if (!function_exists('rialitWebhookRetryDelay')) {
    function rialitWebhookRetryDelay($attempts): int
    {
        $steps = [30, 120, 600, 1800, 7200, 21600];
        $index = max(0, min(count($steps) - 1, (int) $attempts));
        return $steps[$index];
    }
}

if (!function_exists('rialitProcessWebhookEvents')) {
    function rialitProcessWebhookEvents($limit = 20): array
    {
        global $pdo;
        $stats = ['processed' => 0, 'completed' => 0, 'retry' => 0, 'failed' => 0];
        if (!($pdo instanceof PDO) || !rialitEnsureWebhookSchema($pdo)) {
            return $stats;
        }
        $limit = max(1, min(100, (int) $limit));
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM rialit_webhook_event
                  WHERE state IN ('pending','retry')
                    AND next_attempt_at <= ?
                  ORDER BY id ASC
                  LIMIT " . $limit
            );
            $stmt->execute([$now]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_WEBHOOK_SELECT_FAILED', $e->getMessage());
            return $stats;
        }
        foreach ((array) $rows as $row) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
                break;
            }
            $stats['processed']++;
            $eventId = (string) ($row['event_id'] ?? '');
            $orderId = trim((string) ($row['order_id'] ?? ''));
            $token = trim((string) ($row['token'] ?? ''));
            $attempts = (int) ($row['attempts'] ?? 0);
            if ($orderId === '' || $token === '') {
                try {
                    $pdo->prepare("UPDATE rialit_webhook_event SET state = 'failed', last_error = ?, processed_at = ? WHERE event_id = ?")
                        ->execute(['missing_payment_reference', time(), $eventId]);
                } catch (Throwable $e) {
                }
                $stats['failed']++;
                continue;
            }
            $result = rialitVerifyPayment($orderId, ['source' => 'webhook', 'token' => $token]);
            $state = (string) ($result['state'] ?? 'error');
            if ((string) ($row['event_type'] ?? '') === 'payment.paid' && in_array($state, ['pending','expired','cancelled'], true)) {
                $state = 'retry';
            }
            if (in_array($state, ['paid', 'pending', 'expired', 'cancelled'], true)) {
                try {
                    $pdo->prepare("UPDATE rialit_webhook_event SET state = 'completed', processed_at = ?, last_error = NULL WHERE event_id = ?")
                        ->execute([time(), $eventId]);
                } catch (Throwable $e) {
                }
                $stats['completed']++;
                continue;
            }
            $attempts++;
            if ($state === 'mismatch') {
                try {
                    $pdo->prepare("UPDATE rialit_webhook_event SET state = 'failed', attempts = ?, last_error = ?, processed_at = ? WHERE event_id = ?")
                        ->execute([$attempts, mb_substr($state, 0, 250), time(), $eventId]);
                } catch (Throwable $e) {
                }
                $stats['failed']++;
                rialitLogEvent('RIALIT_WEBHOOK_EVENT_FAILED', 'Rialit webhook event could not be verified', [
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                    'state' => $state,
                ]);
                continue;
            }
            if ($attempts >= RIALIT_WEBHOOK_MAX_ATTEMPTS) {
                try {
                    $pdo->prepare("UPDATE rialit_webhook_event SET state = 'failed', attempts = ?, last_error = ?, processed_at = ? WHERE event_id = ?")
                        ->execute([$attempts, mb_substr($state, 0, 250), time(), $eventId]);
                } catch (Throwable $e) {
                }
                $stats['failed']++;
                rialitLogEvent('RIALIT_WEBHOOK_EVENT_EXHAUSTED', 'Rialit webhook event reached the retry limit', [
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                    'state' => $state,
                ]);
                continue;
            }
            try {
                $pdo->prepare("UPDATE rialit_webhook_event SET state = 'retry', attempts = ?, next_attempt_at = ?, last_error = ? WHERE event_id = ?")
                    ->execute([$attempts, time() + rialitWebhookRetryDelay($attempts), mb_substr($state, 0, 250), $eventId]);
            } catch (Throwable $e) {
            }
            $stats['retry']++;
        }
        return $stats;
    }
}

if (!function_exists('rialitProcessPendingOrders')) {
    function rialitProcessPendingOrders($limit = 20): array
    {
        global $pdo;
        $stats = ['checked' => 0, 'paid' => 0, 'retry' => 0];
        if (!($pdo instanceof PDO)) {
            return $stats;
        }
        $limit = max(1, min(100, (int) $limit));
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "SELECT id_order FROM Payment_report
                  WHERE Payment_Method = 'rialit'
                    AND (
                        (payment_Status IN ('Unpaid','pending','waiting')
                         AND rialit_created_at >= ?
                         AND (rialit_last_checked_at IS NULL OR rialit_last_checked_at <= ?))
                        OR
                        (payment_Status IN ('expire','cancelled')
                         AND (rialit_local_state IS NULL OR rialit_local_state <> 'closed')
                         AND rialit_created_at >= ?
                         AND (rialit_last_checked_at IS NULL OR rialit_last_checked_at <= ?))
                    )
                  ORDER BY rialit_last_checked_at IS NOT NULL, rialit_last_checked_at ASC, id ASC
                  LIMIT " . $limit
            );
            $stmt->execute([
                $now - RIALIT_RECONCILE_SECONDS,
                $now - RIALIT_POLL_THROTTLE_SECONDS,
                $now - RIALIT_LATE_RECONCILE_SECONDS,
                $now - RIALIT_POLL_THROTTLE_SECONDS,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            rialitLogEvent('RIALIT_POLL_SELECT_FAILED', $e->getMessage());
            return $stats;
        }
        foreach ((array) $rows as $row) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
                break;
            }
            $orderId = (string) ($row['id_order'] ?? '');
            if ($orderId === '') {
                continue;
            }
            $stats['checked']++;
            rialitUpdateColumns($orderId, ['rialit_last_checked_at' => time()], false);
            $result = rialitVerifyPayment($orderId, ['source' => 'poll']);
            $state = (string) ($result['state'] ?? '');
            if ($state === 'paid') {
                $stats['paid']++;
            } elseif (in_array($state, ['retry', 'busy', 'error'], true)) {
                $stats['retry']++;
            }
        }
        return $stats;
    }
}

if (!function_exists('rialitRecoverPaidOrders')) {
    function rialitRecoverPaidOrders($limit = 10): int
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !function_exists('rx_pf_recover')) {
            return 0;
        }
        $limit = max(1, min(50, (int) $limit));
        try {
            $stmt = $pdo->query(
                "SELECT id_order FROM Payment_report
                  WHERE Payment_Method = 'rialit'
                    AND payment_Status = 'paid'
                    AND COALESCE(direct_payment_done, 0) = 0
                    AND NOT EXISTS (
                        SELECT 1 FROM payment_fulfillment pf
                         WHERE pf.id_order = Payment_report.id_order
                           AND pf.state IN ('completed','manual_review')
                    )
                  ORDER BY id ASC
                  LIMIT " . $limit
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        } catch (Throwable $e) {
            return 0;
        }
        $recovered = 0;
        foreach ((array) $rows as $orderId) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
                break;
            }
            $result = rx_pf_recover((string) $orderId);
            $status = (string) ($result['status'] ?? '');
            if ($status === 'claimed' || $status === 'reconcile') {
                $token = (string) ($result['token'] ?? '');
                if ($token !== '' && function_exists('rx_pf_run_claimed')) {
                    rx_pf_run_claimed((string) $orderId, $token, __DIR__ . '/../../../images.jpg', 'waiting');
                }
            }
            if (in_array($status, ['claimed', 'reconcile', 'finalized'], true)) {
                $recovered++;
            }
        }
        return $recovered;
    }
}

if (!function_exists('rialitPruneWebhookEvents')) {
    function rialitPruneWebhookEvents(): void
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !rialitEnsureWebhookSchema($pdo)) {
            return;
        }
        try {
            $pdo->prepare("DELETE FROM rialit_webhook_event WHERE state IN ('completed','failed') AND processed_at IS NOT NULL AND processed_at < ? LIMIT 500")
                ->execute([time() - 604800]);
        } catch (Throwable $e) {
        }
    }
}
