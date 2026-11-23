<?php

final class FaoximaTelegramTransport
{
    public static function validateProxy(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $parts = strlen($url) <= 1024 && !preg_match('/[^!-~]/', $url) ? parse_url($url) : false;
        if ($parts === false
            || !in_array($parts['scheme'] ?? '', ['socks5h', 'socks5', 'http', 'https'], true)
            || ($parts['port'] ?? 0) < 1
            || array_intersect_key($parts, array_flip(['path', 'query', 'fragment']))) {
            throw new InvalidArgumentException('Invalid Telegram proxy URL.');
        }
        $host = $parts['host'] ?? '';
        $address = str_starts_with($host, '[') && str_ends_with($host, ']') ? substr($host, 1, -1) : $host;
        if (!filter_var($address, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException('Invalid Telegram proxy host.');
        }
        foreach (['user', 'pass'] as $key) {
            if (isset($parts[$key])
                && (!preg_match('/^(?:[a-zA-Z0-9._~-]|%[0-9a-fA-F]{2})*$/D', $parts[$key])
                || preg_match('/[\x00-\x1f\x7f]/', rawurldecode($parts[$key])))) {
                throw new InvalidArgumentException('Invalid Telegram proxy credentials.');
            }
        }
        return $url;
    }

    public static function settings(?PDO $db): ?array
    {
        if ($db === null) {
            return null;
        }
        try {
            $row = $db->query('SELECT proxy_url, polling, revision, last_poll_ok, poll_state FROM telegram_transport WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            // Existing installations can continue in webhook mode until configured.
            if ($e->getCode() === '42S02') {
                return null;
            }
            throw $e;
        }
    }

    public static function currentSettings(): ?array
    {
        static $loaded = false, $settings;
        if (!$loaded) {
            $settings = self::settings($GLOBALS['pdo'] ?? null);
            $loaded = true;
        }
        return $settings;
    }

    public static function applyProxy(CurlHandle $handle, ?string $proxy = null): void
    {
        $proxy ??= self::currentSettings()['proxy_url'] ?? '';
        if ($proxy !== '') {
            curl_setopt_array($handle, [
                CURLOPT_PROXY => $proxy,
                CURLOPT_NOPROXY => '',
                CURLOPT_HTTPPROXYTUNNEL => true,
            ]);
        } elseif (function_exists('faoxima_apply_curl_proxy')) {
            faoxima_apply_curl_proxy($handle, 'telegram');
        }
    }

    public static function open(string $url, int $timeout, ?CurlHandle $reuse = null, ?string $proxy = null): CurlHandle
    {
        $handle = $reuse ?? curl_init();
        if ($reuse !== null) {
            // Reset request options, but keep libcurl's connection and DNS caches.
            curl_reset($handle);
        }
        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 15,
            CURLOPT_TCP_KEEPINTVL => 10,
        ]);
        self::applyProxy($handle, $proxy);
        return $handle;
    }

    public static function control(string $token, string $method, array $data, int $timeout, ?CurlHandle $handle, string $proxy): array
    {
        $handle = self::open("https://api.telegram.org/bot{$token}/{$method}", $timeout, $handle, $proxy);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data));
        $raw = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $body = is_string($raw) ? json_decode($raw, true) : null;
        return [
            'body' => is_array($body) ? $body : [],
            'code' => $code,
            'errno' => curl_errno($handle),
        ];
    }

    public static function download(string $token, string $filePath): string|false
    {
        $path = implode('/', array_map('rawurlencode', explode('/', $filePath)));
        $handle = self::open("https://api.telegram.org/file/bot{$token}/{$path}", 20);
        $body = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        self::noteUpdateFailure(curl_errno($handle), $status, ['ok' => $status === 200]);
        return $status === 200 && is_string($body) ? $body : false;
    }

    public static function mayRetryBeforeSend(int $errno): bool
    {
        // Timeout and receive errors can occur after Telegram accepted a POST.
        return in_array($errno, [CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT, CURLE_SSL_CONNECT_ERROR], true);
    }

    public static function noteUpdateFailure(int $errno, int $status, array $response = []): void
    {
        if (PHP_SAPI === 'cli' && defined('FAOXIMA_POLL_UPDATE')
            && ($errno !== 0 || $status === 0 || $status === 429 || $status >= 500
                || !is_bool($response['ok'] ?? null))) {
            $GLOBALS['faoximaPollTransportFailed'] = true;
        }
    }
}
