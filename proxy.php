<?php

/**
 * Outbound HTTP/SOCKS proxy support for connections to VPN panels.
 * Telegram and mini-app proxy settings are intentionally not supported.
 */

if (!function_exists('faoxima_proxy_settings')) {
    function faoxima_proxy_settings($forceReload = false)
    {
        static $cache = null;
        if ($cache !== null && !$forceReload) {
            return $cache;
        }
        $cache = ['panel_status' => '0', 'panel_url' => ''];
        $pdo = $GLOBALS['pdo'] ?? null;
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->query('SELECT proxy_panel_status, proxy_panel_url FROM setting LIMIT 1');
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if (is_array($row)) {
                    $cache['panel_status'] = (string) ($row['proxy_panel_status'] ?? '0');
                    $cache['panel_url'] = trim((string) ($row['proxy_panel_url'] ?? ''));
                }
            } catch (Throwable $e) {
                // The settings table may not be migrated on a fresh install.
            }
        }
        return $cache;
    }
}

if (!function_exists('faoxima_parse_proxy_url')) {
    function faoxima_parse_proxy_url($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('~^[a-z0-9]+://~i', $url)) {
            $url = 'http://' . $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $types = [
            'http' => CURLPROXY_HTTP,
            'https' => CURLPROXY_HTTP,
            'socks4' => CURLPROXY_SOCKS4,
            'socks4a' => CURLPROXY_SOCKS4A,
            'socks5' => CURLPROXY_SOCKS5,
            'socks5h' => CURLPROXY_SOCKS5_HOSTNAME,
        ];
        return [
            'type' => $types[$scheme] ?? CURLPROXY_HTTP,
            'host' => $parts['host'],
            'port' => isset($parts['port']) ? (int) $parts['port'] : (strpos($scheme, 'socks') === 0 ? 1080 : 8080),
            'userpwd' => isset($parts['user'])
                ? rawurldecode($parts['user']) . ':' . rawurldecode($parts['pass'] ?? '')
                : '',
        ];
    }
}

if (!function_exists('faoxima_proxy_for')) {
    function faoxima_proxy_for($scope)
    {
        if ($scope !== 'panel') {
            return null;
        }
        $settings = faoxima_proxy_settings();
        if ((string) $settings['panel_status'] !== '1' || $settings['panel_url'] === '') {
            return null;
        }
        return faoxima_parse_proxy_url($settings['panel_url']);
    }
}

if (!function_exists('faoxima_apply_curl_proxy')) {
    function faoxima_apply_curl_proxy($ch, $scope)
    {
        if (!is_resource($ch) && !(class_exists('CurlHandle') && $ch instanceof CurlHandle)) {
            return false;
        }
        $proxy = faoxima_proxy_for($scope);
        if ($proxy === null) {
            return false;
        }
        curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
        curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy['type']);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
        if ($proxy['userpwd'] !== '') {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['userpwd']);
        }
        return true;
    }
}
