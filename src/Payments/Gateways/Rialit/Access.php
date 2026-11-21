<?php

namespace Faoxima\Payments\Gateways\Rialit;

final class Access
{
    public static function webhookUrl($runtimeDomainhosts): string
    {
        $domainhosts = $runtimeDomainhosts;
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

    public static function parseIpList($value): array
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

    public static function trustedIps($runtimePdo): array
    {
        return self::parseIpList(\Faoxima\Payments\Gateways\Rialit\Client::setting($runtimePdo, 'rialit_trusted_ips', '3.123.103.100'));
    }

    public static function trustedProxies($runtimePdo): array
    {
        return self::parseIpList(\Faoxima\Payments\Gateways\Rialit\Client::setting($runtimePdo, 'rialit_trusted_proxies', ''));
    }

    public static function ipInCidr($ip, $cidr): bool
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

    public static function ipMatchesAny($ip, array $list): bool
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return false;
        }
        foreach ($list as $entry) {
            if (self::ipInCidr($ip, (string) $entry)) {
                return true;
            }
        }
        return false;
    }

    public static function clientIp($runtimePdo): string
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        $proxies = self::trustedProxies($runtimePdo);
        if ($remote === '' || empty($proxies) || !self::ipMatchesAny($remote, $proxies)) {
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
            if (!self::ipMatchesAny($candidate, $proxies)) {
                return $candidate;
            }
        }
        return $remote;
    }

    public static function webhookAllowed($runtimePdo, $ip): bool
    {
        $trusted = self::trustedIps($runtimePdo);
        if (empty($trusted)) {
            return false;
        }
        return self::ipMatchesAny($ip, $trusted);
    }
}
